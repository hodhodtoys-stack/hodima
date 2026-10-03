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
 *   transcript: string, keywords: string
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
	];
}

/**
 * VideoObject واحد با شناسه «{صفحه}#video»، یا null.
 *
 * null وقتی: ویدیو ندارد، سیستم رسانه برای این شیء خاموش است (ویدیو روی
 * صفحه نمایش داده نمی‌شود، پس نباید به گوگل اعلام شود — اسکیمای محصول
 * قبلا بدون این شرط اعلام می‌کرد)، یا تصویری برای thumbnailUrl نیست
 * (الزامی گوگل).
 *
 * @param array<string, mixed> $extra ویژگی‌های اضافه (about، mainEntityOfPage…).
 *   «_name» = عنوان جایگزین وقتی ویدیو عنوان ندارد.
 */
function hodima_media_video_node( int $object_id, string $context = 'post', array $extra = [] ): ?array {

	$context = hodima_media_context( $context );
	$video   = hodima_media_video( $object_id, $context );

	if ( null === $video || ! $video['enabled'] || null === $video['cover'] ) {
		return null;
	}

	$page_url = hodima_media_page_url( $object_id, $context );

	if ( '' === $page_url ) {
		return null;
	}

	$base  = esc_url_raw( $page_url );
	$title = hodima_media_object_title( $object_id, $context );
	$brand = (string) ( get_option( 'hodima_brand_name', '' ) ?: get_bloginfo( 'name' ) );

	$node = [
		'@type'        => 'VideoObject',
		'@id'          => $base . '#video',
		'isPartOf'     => [ '@id' => $base . '#webpage' ],
		'name'         => '' !== $video['title'] ? $video['title'] : (string) ( $extra['_name'] ?? 'ویدیوی معرفی: ' . $title ),
		'description'  => hodima_media_summary( $object_id, $context, 'بررسی و نمایش ویدیویی ' . $title . ' توسط ' . $brand ),
		'thumbnailUrl' => [ esc_url_raw( $video['cover']['url'] ) ],
		'uploadDate'   => $video['date'],
		'inLanguage'   => hodima_media_language(),
	];

	if ( $video['is_file'] ) {
		$node['contentUrl']     = $video['url'];
		$node['encodingFormat'] = $video['mime'];
	} else {
		$node['embedUrl'] = esc_url_raw( $video['player'] );
	}

	if ( '' !== $video['duration'] ) {
		$node['duration'] = $video['duration'];
	}

	// ناشر = همان سازمان یکتای سایت (homepage-schema.php)، فقط وقتی افزونه سئو آن را می‌سازد
	if ( function_exists( 'hodima_seo_schema_organization_node' ) ) {
		$node['publisher'] = [ '@id' => trailingslashit( home_url() ) . '#organization' ];
	}

	if ( '' !== $video['transcript'] ) {
		$node['transcript'] = wp_strip_all_tags( $video['transcript'] );
	}

	if ( '' !== $video['keywords'] ) {
		$node['keywords'] = $video['keywords'];
	}

	/*
	 * «لحظه‌های کلیدی» گوگل: هر فصل یک Clip با آدرسی که ویدیو را از همان
	 * زمان شروع می‌کند (?t=ثانیه؛ media-style.js آن را اجرا می‌کند).
	 * پایان هر فصل = شروع فصل بعد؛ آخرین = مدت ویدیو (اگر معلوم است).
	 */
	$clips = [];
	foreach ( $video['chapters'] as $i => $chapter ) {
		$end = $video['chapters'][ $i + 1 ]['start'] ?? $video['seconds'];
		if ( $end > 0 && $end <= $chapter['start'] ) {
			continue;
		}
		$clip = [
			'@type'       => 'Clip',
			'name'        => $chapter['title'],
			'startOffset' => $chapter['start'],
			'url'         => add_query_arg( 't', $chapter['start'], $base ),
		];
		if ( $end > 0 ) {
			$clip['endOffset'] = $end;
		}
		$clips[] = $clip;
	}
	if ( $clips ) {
		$node['hasPart'] = $clips;
	}

	foreach ( $extra as $key => $value ) {
		if ( ! str_starts_with( (string) $key, '_' ) ) {
			$node[ $key ] = $value;
		}
	}

	return $node;
}
