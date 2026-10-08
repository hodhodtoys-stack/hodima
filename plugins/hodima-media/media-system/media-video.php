<?php
/**
 * Media System — ویدیوی یکسان‌شده و VideoObject واحد
 * Path: media-system/media-video.php
 *
 * پیش از این VideoObject در پنج جای جدا ساخته می‌شد (سیستم رسانه، اسکیمای
 * محصول، اسکیمای دسته، صفحه اصلی قالب و سایت‌مپ) و هر کدام قانون خودش را
 * برای کاور، تاریخ و آدرس پخش‌کننده داشت؛ مثلا تاریخ محصول بدون مقدار
 * ثبت‌شده «یک ماه پیش» می‌شد و اسکیمای دسته کلید اشتباهی می‌خواند.
 * حالا همه از این دو تابع:
 *
 *   hodima_media_video()       داده یکسان‌شده (پخش‌کننده، اسکیما، سایت‌مپ)
 *   hodima_media_video_node()  نود JSON-LD کامل با شناسه «#video»
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * تصویر کاور: [ id, url, width, height ] یا null.
 *
 * ترتیب: کاوری که مدیر انتخاب کرده (با شناسه، یا فقط آدرس) ← تصویر شاخص
 * نوشته/محصول ← تصویر دسته.
 *
 * @param string $size اندازه وردپرس؛ برای اسکیما «full»، برای پوستر «large».
 * @return array{id: int, url: string, width: int, height: int}|null
 */
function hodima_media_video_cover( int $object_id, string $context, string $size = 'full', bool $fallback = true ): ?array {

	$data = hodima_media_get_data( $object_id, $context );
	$id   = (int) ( $data['video_cover_id'] ?? 0 );

	if ( ! $id && '' !== (string) ( $data['video_cover'] ?? '' ) ) {
		return [ 'id' => 0, 'url' => esc_url_raw( (string) $data['video_cover'] ), 'width' => 0, 'height' => 0 ];
	}

	if ( ! $id && $fallback ) {
		$id = 'post' === $context
			? (int) get_post_thumbnail_id( $object_id )
			: (int) get_term_meta( $object_id, 'thumbnail_id', true );
	}

	$src = $id ? wp_get_attachment_image_src( $id, $size ) : false;

	return is_array( $src ) && ! empty( $src[0] )
		? [ 'id' => $id, 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] ]
		: null;
}

/**
 * نسبت تصویر نهایی ویدیو («16:9»، «9:16»…).
 * «خودکار»: شورتز یوتیوب عمودی، بقیه افقی. (برای فایل آپلودشده، نسبت واقعی
 * هنگام ذخیره از اطلاعات فایل خوانده و ذخیره می‌شود.)
 */
function hodima_media_video_ratio( array $data, array $parsed ): string {

	$ratio = (string) ( $data['video_ratio'] ?? 'auto' );

	if ( 'auto' !== $ratio && isset( HODIMA_MEDIA_RATIOS[ $ratio ] ) ) {
		return $ratio;
	}

	return $parsed['vertical'] ? '9:16' : '16:9';
}

/** نزدیک‌ترین نسبت استاندارد به ابعاد واقعی (برای فایل آپلودشده). */
function hodima_media_nearest_ratio( int $width, int $height ): string {

	if ( $width <= 0 || $height <= 0 ) {
		return 'auto';
	}

	$best  = '16:9';
	$delta = PHP_FLOAT_MAX;

	foreach ( array_keys( HODIMA_MEDIA_RATIOS ) as $ratio ) {
		if ( 'auto' === $ratio ) {
			continue;
		}
		[ $w, $h ] = array_map( 'intval', explode( ':', $ratio ) );
		$d = abs( $width / $height - $w / $h );
		if ( $d < $delta ) {
			[ $best, $delta ] = [ $ratio, $d ];
		}
	}

	return $best;
}

/**
 * تاریخ پایدار انتشار رسانه (uploadDate).
 *
 * ترتیب: تاریخ ثبت‌شده هنگام ذخیره آدرس ← تاریخ آپلود فایل (اگر در
 * کتابخانه رسانه سایت باشد) ← تاریخ *انتشار* نوشته (نه آخرین ویرایش) ←
 * برای ترم بدون تاریخ (داده قبل از نسخه ۳)، ثبت یک‌باره همین لحظه.
 */
function hodima_media_stable_date( array $data, string $kind, int $object_id, string $context ): string {

	$stored = hodima_media_iso_date( $data[ "{$kind}_date" ] ?? '' );
	if ( '' !== $stored ) {
		return $stored;
	}

	$url           = (string) ( $data[ "{$kind}_url" ] ?? '' );
	$attachment_id = '' !== $url ? (int) attachment_url_to_postid( $url ) : 0;

	foreach ( [ $attachment_id, 'post' === $context ? $object_id : 0 ] as $post_id ) {
		$gmt = $post_id ? (string) get_post_field( 'post_date_gmt', $post_id ) : '';
		if ( '' !== $gmt && ! str_starts_with( $gmt, '0000' ) ) {
			return gmdate( 'c', (int) strtotime( $gmt . ' UTC' ) );
		}
	}

	$now = gmdate( 'c' );
	update_metadata( hodima_media_context( $context ), $object_id, hodima_media_meta_prefix( $context ) . "{$kind}_date", $now );
	hodima_media_get_data( $object_id, $context, true );

	return $now;
}

/**
 * خلاصه متنی همان صفحه برای description ویدیو و صوت:
 * خلاصه نوشته/محصول ← ابتدای متن؛ برای دسته، توضیح دسته؛ وگرنه متن قالبی.
 */
function hodima_media_summary( int $object_id, string $context, string $fallback ): string {

	if ( 'post' === $context ) {
		$post = get_post( $object_id );
		$text = $post ? ( '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content ) : '';
	} else {
		$text = (string) term_description( $object_id );
	}

	$text = trim( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $text ) ), 40, '…' ) );

	return '' !== $text ? $text : $fallback;
}

/**
 * داده یکسان‌شده ویدیوی یک نوشته یا ترم، یا null اگر ویدیو ندارد.
 *
 * @return array{
 *   enabled: bool, url: string, provider: string, provider_id: string, player: string,
 *   is_file: bool, mime: string, title: string, cover: ?array, ratio: string,
 *   seconds: int, duration: string, date: string, chapters: list<array{start:int,title:string}>,
 *   transcript: string, keywords: string, description: string, captions: string
 * }|null
 */
function hodima_media_video( int $object_id, string $context = 'post' ): ?array {

	$context = hodima_media_context( $context );
	$data    = hodima_media_get_data( $object_id, $context );
	$url     = trim( (string) ( $data['video_url'] ?? '' ) );

	if ( '' === $url ) {
		return null;
	}

	// فاصله در نام فایل → %20 (esc_url_raw فاصله را حذف می‌کرد و آدرس می‌شکست)
	$url    = esc_url_raw( (string) preg_replace( '/\s+/', '%20', $url ) );
	$parsed = hodima_media_parse_video_url( $url );
	$mime   = 'file' === $parsed['provider']
		? ( wp_check_filetype( (string) wp_parse_url( $url, PHP_URL_PATH ), wp_get_mime_types() )['type'] ?: 'video/mp4' )
		: '';

	$seconds = hodima_media_duration_seconds( $data['video_duration'] ?? '' );

	return [
		'enabled'     => 'yes' === ( $data['enabled'] ?? '' ),
		'url'         => $url,
		'provider'    => $parsed['provider'],
		'provider_id' => $parsed['id'],
		'player'      => $parsed['player'],
		'is_file'     => 'file' === $parsed['provider'],
		'mime'        => (string) $mime,
		'title'       => sanitize_text_field( (string) ( $data['video_title'] ?? '' ) ),
		'cover'       => hodima_media_video_cover( $object_id, $context ),
		'ratio'       => hodima_media_video_ratio( $data, $parsed ),
		'seconds'     => $seconds,
		'duration'    => hodima_media_duration_iso( $data['video_duration'] ?? '' ),
		'date'        => hodima_media_stable_date( $data, 'video', $object_id, $context ),
		'chapters'    => hodima_media_parse_chapters( $data['video_chapters'] ?? '' ),
		'transcript'  => trim( (string) ( $data['video_transcript'] ?? '' ) ),
		'keywords'    => sanitize_text_field( (string) ( $data['video_keywords'] ?? '' ) ),
		'description' => trim( sanitize_textarea_field( (string) ( $data['video_description'] ?? '' ) ) ),
		// زیرنویس فقط برای فایل خود سایت (پلیرهای آپارات/یوتیوب زیرنویس خودشان را دارند)
		'captions'    => 'file' === $parsed['provider'] ? esc_url_raw( (string) ( $data['video_captions'] ?? '' ) ) : '',
	];
}

/**
 * VideoObject واحد با شناسه «{صفحه}#video»، یا null.
 *
 * null وقتی: ویدیو ندارد، سیستم رسانه برای این شیء خاموش است یا قالب
 * بخش رسانه آن دسته را نمایش نمی‌دهد (ویدیو روی صفحه نیست، پس نباید به
 * گوگل اعلام شود — اسکیمای محصول قبلا بدون این شرط اعلام می‌کرد)، یا
 * تصویری برای thumbnailUrl نیست (الزامی گوگل).
 *
 * @param array<string, mixed> $extra ویژگی‌های اضافه (about، mainEntityOfPage…).
 *   «_name» = عنوان جایگزین وقتی ویدیو عنوان ندارد.
 */
function hodima_media_video_node( int $object_id, string $context = 'post', array $extra = [] ): ?array {

	$context = hodima_media_context( $context );
	$video   = hodima_media_video( $object_id, $context );

	// دسته‌ای که قالب بخش رسانه‌اش را نشان نمی‌دهد (دسته وبلاگ): ویدیو اعلام نمی‌شود
	if ( null === $video || ! $video['enabled'] || null === $video['cover'] || ! hodima_media_is_displayed( $object_id, $context ) ) {
		return null;
	}

	$page_url = hodima_media_page_url( $object_id, $context );

	if ( '' === $page_url ) {
		return null;
	}

	$title = hodima_media_object_title( $object_id, $context );
	$brand = (string) ( get_option( 'hodima_brand_name', '' ) ?: get_bloginfo( 'name' ) );

	// سازنده واحد (inc/video-object.php)؛ همان نود ماژول «ویدیوها»
	$node = hodima_media_video_object( [
		'base'        => $page_url,
		'name'        => '' !== $video['title'] ? $video['title'] : (string) ( $extra['_name'] ?? 'ویدیوی معرفی: ' . $title ),
		'description' => '' !== $video['description'] ? $video['description'] : hodima_media_summary( $object_id, $context, 'بررسی و نمایش ویدیویی ' . $title . ' توسط ' . $brand ),
		'thumbnails'  => [ $video['cover']['url'] ],
		'upload_date' => $video['date'],
		'content_url' => $video['is_file'] ? $video['url'] : '',
		'embed_url'   => $video['is_file'] ? '' : $video['player'],
		'mime'        => $video['mime'],
		'duration'    => $video['duration'],
		'seconds'     => $video['seconds'],
		'transcript'  => $video['transcript'],
		'keywords'    => $video['keywords'],
		'chapters'    => $video['chapters'],
		'captions'    => $video['captions'],
		// پلیر صفحه ?t= را برای فایل، یوتیوب و ویمئو اجرا می‌کند (media-style.js)؛ آپارات زمان شروع ندارد
		'seekable'    => in_array( $video['provider'], [ 'file', 'youtube', 'vimeo' ], true ),
		'publisher'   => function_exists( 'hodima_seo_schema_organization_node' ),
	] );

	foreach ( $extra as $key => $value ) {
		if ( ! str_starts_with( (string) $key, '_' ) ) {
			$node[ $key ] = $value;
		}
	}

	return $node;
}

/**
 * وضعیت آمادگی ویدیوی یک شیء برای نتایج ویدیویی گوگل (کادر رسانه).
 *
 * @return list<array{key: string, status: string, label: string, detail: string}>
 *   status: ok | warn | error
 */
function hodima_media_video_checks( int $object_id, string $context ): array {

	$context = hodima_media_context( $context );
	$video   = hodima_media_video( $object_id, $context );

	if ( null === $video ) {
		return [];
	}

	$checks = [];

	// ۱. نمایش (بدون آن به گوگل هم اعلام نمی‌شود)
	$checks[] = match ( true ) {
		! hodima_media_is_displayed( $object_id, $context ) => [ 'shown', 'error', 'نمایش', 'قالب بخش رسانه این نوع دسته را نشان نمی‌دهد؛ ویدیو به گوگل اعلام نمی‌شود.' ],
		! $video['enabled'] => [ 'shown', 'error', 'نمایش', 'کلید «نمایش ویدیو، پادکست…» بالای کادر خاموش است؛ ویدیو نه در صفحه است نه در گوگل.' ],
		default => [ 'shown', 'ok', 'نمایش', 'در صفحه نمایش داده و به گوگل اعلام می‌شود.' ],
	};

	// ۲. سرویس
	$checks[] = match ( $video['provider'] ) {
		'youtube' => [ 'provider', 'warn', 'سرویس', 'یوتیوب برای بیشتر بازدیدکنندگان ایرانی فیلتر است و پخش نمی‌شود؛ آپارات یا فایل MP4 خود سایت بهتر است.' ],
		'other'   => [ 'provider', 'warn', 'سرویس', 'سرویس شناخته نشد؛ پخش با جاسازی خودکار وردپرس امتحان می‌شود و گوگل ممکن است پخش‌کننده را نشناسد.' ],
		'file'    => [ 'provider', 'ok', 'سرویس', 'فایل ویدیوی خود سایت (بهترین حالت برای گوگل و سرعت پخش در ایران).' ],
		default   => [ 'provider', 'ok', 'سرویس', 'aparat' === $video['provider'] ? 'آپارات.' : 'ویمئو.' ],
	};

	// ۳. تصویر (thumbnailUrl الزامی گوگل)
	$checks[] = null === $video['cover']
		? [ 'cover', 'error', 'کاور', 'نه کاور دارد نه تصویر شاخص؛ بدون تصویر، ویدیو به گوگل اعلام نمی‌شود.' ]
		: [ 'cover', 'ok', 'کاور', $video['cover']['width'] ? sprintf( '%s×%s پیکسل.', number_format_i18n( $video['cover']['width'] ), number_format_i18n( $video['cover']['height'] ) ) : 'آدرس بیرونی.' ];

	// ۴. مدت
	$checks[] = '' === $video['duration']
		? [ 'duration', 'warn', 'مدت', 'مدت ندارد؛ گوگل مدت را کنار ویدیو نشان می‌دهد.' ]
		: [ 'duration', 'ok', 'مدت', hodima_media_clock( $video['seconds'] ) . '.' ];

	// ۵. توضیح اختصاصی
	$checks[] = '' === $video['description']
		? [ 'description', 'warn', 'توضیح', 'توضیح اختصاصی ویدیو ندارد؛ خلاصه صفحه استفاده می‌شود (برای ویدیوهای زیاد تکراری می‌شود).' ]
		: [ 'description', 'ok', 'توضیح', 'توضیح اختصاصی دارد.' ];

	// ۶. لحظه‌های کلیدی
	$checks[] = match ( true ) {
		(bool) $video['chapters'] => [ 'chapters', 'ok', 'لحظه‌های کلیدی', sprintf( '%s فصل.', number_format_i18n( count( $video['chapters'] ) ) ) ],
		'aparat' === $video['provider'] => [ 'chapters', 'warn', 'لحظه‌های کلیدی', 'فصل ندارد؛ پخش‌کننده آپارات پرش به زمان را پشتیبانی نمی‌کند، پس فقط با فصل‌ها «لحظه‌های کلیدی» ممکن است.' ],
		default => [ 'chapters', 'ok', 'لحظه‌های کلیدی', 'فصل ندارد؛ گوگل خودش لحظه‌ها را پیدا می‌کند (SeekToAction).' ],
	};

	return array_map(
		static fn( array $c ): array => array_combine( [ 'key', 'status', 'label', 'detail' ], $c ),
		$checks
	);
}
