<?php
/**
 * Media System — Helpers (داده و قرارداد عمومی)
 * Path: media-system/media-helpers.php
 *
 * ─────────────────────────────────────────────────────────────────────
 * نام‌گذاری (نسخه ۴)
 * ─────────────────────────────────────────────────────────────────────
 * همه توابع با پیشوند hodima_media_ هستند. نسخه قبلی پیشوند عمومی «hook_»
 * داشت (hook_get_media_data و…) بدون هیچ گاردی؛ هر افزونه دیگری که تابعی
 * با همین نام عمومی تعریف می‌کرد، سایت را با «Cannot redeclare» از کار
 * می‌انداخت. نام‌های قدیمی برای قالب، افزونه سئو و کد سفارشی سایت در
 * media-legacy.php — فقط اگر تعریف نشده باشند — به این توابع ارجاع می‌دهند.
 *
 * کلیدهای متا عوض نشده‌اند (داده سایت): نوشته «_hook_{key}»، ترم «hook_{key}».
 *
 * قرارداد عمومی (امضا ثابت):
 *   hodima_media_get_data()          همه داده یک نوشته/ترم
 *   hodima_media_video()             ویدیوی یکسان‌شده (media-video.php)
 *   hodima_media_video_node()        VideoObject واحد برای همه اسکیماها
 *   hodima_media_duration_iso()      مدت به ISO 8601
 *   hodima_media_is_direct_video()   فایل مستقیم یا صفحه آپارات/یوتیوب
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * کلیدها و پشتیبانی
 * ===================================================================== */

/**
 * کلیدهای نسخه‌های قبلی که ممکن است بدون پیشوند ذخیره شده باشند.
 * کلیدهای تازه (کاور با شناسه، نسبت، فصل‌ها، متن گفتار، تصویر Discover)
 * هرگز بدون پیشوند ذخیره نشده‌اند.
 */
const HODIMA_MEDIA_LEGACY_KEYS = [
	'enabled', 'content',
	'video_url', 'video_thumb', 'video_keywords', 'video_duration', 'video_date', 'video_title', 'video_cover',
	'voice_url', 'voice_keywords', 'voice_duration', 'voice_date', 'voice_title',
	'discover_title', 'ai_summary', 'key_entities', 'faq',
];

/** نسبت‌های تصویر ویدیو (مقدار ذخیره‌شده ← برچسب). */
const HODIMA_MEDIA_RATIOS = [
	'auto' => 'خودکار',
	'16:9' => 'افقی ۱۶:۹',
	'9:16' => 'عمودی ۹:۱۶ (ریلز، شورتز)',
	'4:5'  => 'عمودی ۴:۵',
	'1:1'  => 'مربع ۱:۱',
	'4:3'  => '۴:۳',
];

/**
 * کلیدهای متای سیستم رسانه — منبع واحد برای خواندن و نوشتن.
 *
 * ai_summary فقط برای نگه داشتن داده قبلی خوانده می‌شود؛ بخش «خلاصه هوش
 * مصنوعی» از فرم و سایت حذف شده و هیچ‌جا استفاده نمی‌شود.
 *
 * @return list<string>
 */
function hodima_media_meta_keys(): array {
	return [
		...HODIMA_MEDIA_LEGACY_KEYS,
		'video_cover_id', 'video_ratio', 'video_chapters', 'video_transcript',
		'voice_transcript', 'discover_image_id',
	];
}

/** @return list<string> */
function hodima_media_post_types(): array {
	return array_values( (array) apply_filters( 'hook_media_post_types', [ 'product', 'post', 'page' ] ) );
}

/** @return list<string> */
function hodima_media_taxonomies(): array {
	return array_values( (array) apply_filters( 'hook_media_taxonomies', [ 'product_cat', 'category' ] ) );
}

/** «post» یا «term»؛ هر مقدار دیگر «post» است. */
function hodima_media_context( string $context ): string {
	return 'term' === $context ? 'term' : 'post';
}

/** پیشوند متا: نوشته «_hook_»، ترم «hook_» (همان کلیدهای قبلی). */
function hodima_media_meta_prefix( string $context ): string {
	return 'term' === $context ? 'hook_' : '_hook_';
}

/** نسخه دارایی = زمان تغییر فایل (نسخه ثابت، به‌روزرسانی را از کش پنهان می‌کرد). */
function hodima_media_asset_version( string $relative ): string {
	$path = HODIMA_MEDIA_DIR . '/media-system/' . ltrim( $relative, '/' );
	return is_file( $path ) ? (string) filemtime( $path ) : HODIMA_MEDIA_VERSION;
}

/** زبان محتوا برای اسکیما (مثلا fa-IR). */
function hodima_media_language(): string {
	return (string) ( get_bloginfo( 'language' ) ?: 'fa-IR' );
}

/* =====================================================================
 * یکسان‌سازی
 * ===================================================================== */

/** ارقام فارسی و عربی → لاتین. */
function hodima_media_normalize_digits( string $value ): string {
	return strtr( $value, [
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	] );
}

/**
 * موجودیت‌های کلیدی به صورت آرایه.
 * جداکننده‌ها: خط جدید، «,» «،» «;» «؛» «|» (ویرگول فارسی هم).
 *
 * @return list<string>
 */
function hodima_media_parse_entities( mixed $raw ): array {

	$parts = preg_split( '/[\r\n,،;؛|]+/u', is_scalar( $raw ) ? (string) $raw : '' ) ?: [];
	$out   = [];

	foreach ( $parts as $part ) {
		$part = trim( str_replace( "\u{200C}", ' ', wp_strip_all_tags( $part ) ) );
		if ( '' !== $part && mb_strlen( $part ) <= 120 ) {
			$out[ mb_strtolower( $part ) ] = $part;
		}
	}

	return array_values( $out );
}

/* =====================================================================
 * خواندن داده
 * ===================================================================== */

/**
 * آیا این شیء داده نسخه خیلی قدیمی (کلیدهای بی‌پیشوند) دارد؟
 *
 * باگ قبلی: برای *هر* فیلد خالی، کلید بی‌پیشوند همنام خوانده می‌شد
 * («content»، «enabled»، «faq»، «video_url») و هر ذخیره همان کلیدها را
 * پاک می‌کرد. این نام‌ها آن‌قدر عمومی‌اند که افزونه‌های دیگر هم از آن‌ها
 * استفاده می‌کنند: داده آن‌ها در بلوک رسانه نمایش داده می‌شد و با ذخیره
 * نوشته پاک می‌شد.
 *
 * حالا فقط وقتی سراغ کلیدهای قدیمی می‌رویم که شیء هرگز با نسخه جدید
 * ذخیره نشده (ردیف «enabled» با پیشوند ندارد — از نسخه ۴ همیشه yes/no
 * نوشته می‌شود) *و* نشانه نسخه قدیمی (enabled = yes) را دارد. هیچ کلید
 * بی‌پیشوندی دیگر حذف نمی‌شود.
 */
function hodima_media_has_legacy_data( int $object_id, string $context ): bool {
	$type = hodima_media_context( $context );
	return ! metadata_exists( $type, $object_id, hodima_media_meta_prefix( $context ) . 'enabled' )
		&& 'yes' === get_metadata( $type, $object_id, 'enabled', true );
}

/**
 * تمام متادیتای رسانه یک نوشته یا ترم (با کش درون‌درخواستی).
 *
 * مقادیر مشتق‌شده (تا همه مصرف‌کننده‌ها بدون تغییر، داده درست بگیرند):
 *   video_thumb     اگر خالی بود = video_cover (فرم فقط کاور را می‌نویسد)
 *   video_cover_id  اگر فقط آدرس بود، شناسه پیوست از روی آدرس
 *   key_entities    به شکل یکسان «الف, ب, ج»
 *   video_ratio     پیش‌فرض «auto»
 *
 * @param bool $force کش را دور بزن و دوباره از دیتابیس بخوان.
 * @return array<string, mixed>
 */
function hodima_media_get_data( int $object_id, string $context = 'post', bool $force = false ): array {

	$cache = &hodima_media_data_cache();

	if ( $object_id <= 0 ) {
		return [];
	}

	$context   = hodima_media_context( $context );
	$cache_key = $context . ':' . $object_id;

	if ( ! $force && isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$prefix = hodima_media_meta_prefix( $context );
	$legacy = hodima_media_has_legacy_data( $object_id, $context );
	$data   = [];

	foreach ( hodima_media_meta_keys() as $key ) {

		$val = get_metadata( $context, $object_id, $prefix . $key, true );

		if ( '' === $val && $legacy && in_array( $key, HODIMA_MEDIA_LEGACY_KEYS, true ) ) {
			$val = get_metadata( $context, $object_id, $key, true );
		}

		$data[ $key ] = $val;
	}

	$data['faq']               = is_array( $data['faq'] ) ? array_values( $data['faq'] ) : [];
	$data['video_cover_id']    = (int) $data['video_cover_id'];
	$data['discover_image_id'] = (int) $data['discover_image_id'];
	$data['video_ratio']       = isset( HODIMA_MEDIA_RATIOS[ (string) $data['video_ratio'] ] ) ? (string) $data['video_ratio'] : 'auto';

	if ( 0 === $data['video_cover_id'] && '' !== (string) $data['video_cover'] ) {
		$data['video_cover_id'] = (int) attachment_url_to_postid( (string) $data['video_cover'] );
	}

	if ( '' === (string) $data['video_thumb'] && '' !== (string) $data['video_cover'] ) {
		$data['video_thumb'] = $data['video_cover'];
	}

	if ( '' !== (string) $data['key_entities'] ) {
		$data['key_entities'] = implode( ', ', hodima_media_parse_entities( $data['key_entities'] ) );
	}

	return $cache[ $cache_key ] = $data;
}

/** کش درون‌درخواستی hodima_media_get_data (با ارجاع، برای پاک کردن). */
function &hodima_media_data_cache(): array {
	static $cache = [];
	return $cache;
}

/*
 * با هر تغییر متای رسانه یک شیء، کش همان شیء پاک می‌شود. بدون این، اگر در
 * یک درخواست داده زودتر خوانده شده بود (مثلا save_post هنگام ساخت نوشته)
 * و بعد متا نوشته می‌شد (درون‌ریزی، کد سفارشی)، تا پایان درخواست داده
 * کهنه (خالی) برگردانده می‌شد.
 */
foreach ( [ 'post', 'term' ] as $hodima_media_type ) {
	foreach ( [ 'added', 'updated', 'deleted' ] as $hodima_media_action ) {
		add_action( "{$hodima_media_action}_{$hodima_media_type}_meta", static function ( $meta_ids, $object_id, $meta_key ) use ( $hodima_media_type ): void {
			if ( str_starts_with( (string) $meta_key, hodima_media_meta_prefix( $hodima_media_type ) ) ) {
				$cache = &hodima_media_data_cache();
				unset( $cache[ $hodima_media_type . ':' . (int) $object_id ] );
			}
		}, 10, 3 );
	}
}
unset( $hodima_media_type, $hodima_media_action );

/** آیا سیستم رسانه برای این شیء روشن است؟ */
function hodima_media_is_enabled( int $object_id, string $context = 'post' ): bool {
	return 'yes' === ( hodima_media_get_data( $object_id, $context )['enabled'] ?? '' );
}

/**
 * آدرس یک شیء برای @id در JSON-LD: صفحه جاری ← canonical مشترک؛ شیء
 * دیگر (مثلا [hook_video id="123"] در صفحه‌ای دیگر) ← پیوند خود آن.
 */
function hodima_media_page_url( int $object_id, string $context ): string {

	$queried = get_queried_object();

	$is_current = ( 'post' === $context && $queried instanceof WP_Post && (int) $queried->ID === $object_id )
		|| ( 'term' === $context && $queried instanceof WP_Term && (int) $queried->term_id === $object_id );

	if ( $is_current && function_exists( 'hodima_get_canonical_url' ) ) {
		$canonical = (string) hodima_get_canonical_url();
		if ( '' !== $canonical ) {
			return $canonical;
		}
	}

	if ( 'post' === $context ) {
		return (string) ( get_permalink( $object_id ) ?: '' );
	}

	$link = get_term_link( $object_id );
	return is_wp_error( $link ) ? '' : (string) $link;
}

/** عنوان نوشته یا نام ترم. */
function hodima_media_object_title( int $object_id, string $context ): string {

	if ( 'post' === $context ) {
		return (string) get_the_title( $object_id );
	}

	$term = get_term( $object_id );
	return $term instanceof WP_Term ? $term->name : '';
}

/* =====================================================================
 * آدرس ویدیو
 * ===================================================================== */

/** آیا آدرس، فایل مستقیم ویدیو است (در مقابل صفحه آپارات/یوتیوب)؟ */
function hodima_media_is_direct_video( mixed $url ): bool {
	$path = (string) wp_parse_url( is_scalar( $url ) ? (string) $url : '', PHP_URL_PATH );
	return in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), [ 'mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v' ], true );
}

/**
 * تشخیص سرویس ویدیو و آدرس *پخش‌کننده* (همان src داخل iframe).
 *
 * باگ قبلی: پخش‌کننده صفحه از oEmbed وردپرس ساخته می‌شد. آپارات در فهرست
 * سرویس‌های مورد اعتماد وردپرس نیست؛ نتیجه به «کشف خودکار» وابسته بود که
 * کد جاسازی را سانسور می‌کند (حذف تمام‌صفحه، افزودن sandbox) یا کلا
 * شکست می‌خورد و فقط دکمه «مشاهده ویدیو» به سایت آپارات می‌آمد. اسکیما
 * در همان حال آدرس درست پخش‌کننده را می‌فرستاد — دو مسیر جدا.
 * حالا پخش‌کننده، اسکیما و سایت‌مپ همه از همین تابع.
 *
 * @return array{provider: string, id: string, player: string, vertical: bool}
 *   provider: file | aparat | youtube | vimeo | other | '' (خالی)
 */
function hodima_media_parse_video_url( string $url ): array {

	$url  = trim( $url );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$out  = [ 'provider' => '', 'id' => '', 'player' => '', 'vertical' => false ];

	if ( '' === $url ) {
		return $out;
	}

	if ( hodima_media_is_direct_video( $url ) ) {
		return [ 'provider' => 'file', 'id' => '', 'player' => $url, 'vertical' => false ];
	}

	// آپارات: صفحه تماشا /v/{hash} یا خود پخش‌کننده …/videohash/{hash}/…
	if ( str_ends_with( $host, 'aparat.com' )
		&& preg_match( '#^/(?:v/|video/video/embed/videohash/)([A-Za-z0-9]+)#', $path, $m ) ) {
		return [
			'provider' => 'aparat',
			'id'       => $m[1],
			'player'   => 'https://www.aparat.com/video/video/embed/videohash/' . $m[1] . '/vt/frame',
			'vertical' => false,
		];
	}

	// یوتیوب: watch?v= / youtu.be / shorts / embed
	if ( str_ends_with( $host, 'youtube.com' ) || str_ends_with( $host, 'youtube-nocookie.com' ) || 'youtu.be' === $host ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$shorts = 1 === preg_match( '#^/shorts/([\w-]+)#', $path, $s );
		$id     = match ( true ) {
			'youtu.be' === $host                             => trim( $path, '/' ),
			$shorts                                          => $s[1],
			1 === preg_match( '#^/embed/([\w-]+)#', $path, $e ) => $e[1],
			default                                          => is_string( $query['v'] ?? null ) ? $query['v'] : '',
		};
		if ( 1 === preg_match( '/^[\w-]{6,20}$/', $id ) ) {
			return [ 'provider' => 'youtube', 'id' => $id, 'player' => 'https://www.youtube.com/embed/' . $id, 'vertical' => $shorts ];
		}
	}

	// ویمئو
	if ( preg_match( '/(^|\.)vimeo\.com$/', $host ) && preg_match( '#^/(?:video/)?(\d+)#', $path, $m ) ) {
		return [ 'provider' => 'vimeo', 'id' => $m[1], 'player' => 'https://player.vimeo.com/video/' . $m[1], 'vertical' => false ];
	}

	$player = function_exists( 'hodima_video_player_url' ) ? (string) hodima_video_player_url( $url ) : $url;

	return [ 'provider' => 'other', 'id' => '', 'player' => $player, 'vertical' => false ];
}

/** آدرس پخش‌کننده (embedUrl) یک ویدیو؛ فایل مستقیم همان آدرس فایل. */
function hodima_media_player_url( string $url ): string {
	return hodima_media_parse_video_url( $url )['player'];
}

/* =====================================================================
 * زمان و تاریخ
 * ===================================================================== */

/**
 * مدت به ثانیه، یا ۰.
 * ورودی: «2:35»، «1:05:20»، «155» (ثانیه)، «PT2M35S» — با ارقام فارسی هم.
 */
function hodima_media_duration_seconds( mixed $duration ): int {

	$duration = trim( hodima_media_normalize_digits( is_scalar( $duration ) ? (string) $duration : '' ) );

	return match ( true ) {
		'' === $duration                                                     => 0,
		ctype_digit( $duration )                                             => (int) $duration,
		1 === preg_match( '/^(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $duration, $m ) => (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3],
		1 === preg_match( '/^(\d{1,3}):(\d{1,2})$/', $duration, $m )          => (int) $m[1] * 60 + (int) $m[2],
		1 === preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)(?:\.\d+)?S)?$/i', $duration, $m ) && strlen( $duration ) > 2
			=> (int) ( $m[1] ?? 0 ) * 3600 + (int) ( $m[2] ?? 0 ) * 60 + (int) ( $m[3] ?? 0 ),
		default                                                              => 0,
	};
}

/**
 * مدت به قالب ISO 8601 (PT5M30S)، یا رشته خالی.
 * ورودی نامفهوم («۵ دقیقه») رشته خالی می‌دهد، نه مقدار نامعتبر در اسکیما.
 */
function hodima_media_duration_iso( mixed $duration ): string {

	$raw = strtoupper( trim( hodima_media_normalize_digits( is_scalar( $duration ) ? (string) $duration : '' ) ) );

	// مقدار ISO درست را همان‌طور نگه دار (مثلا PT1M30.5S)
	if ( preg_match( '/^PT(\d+H)?(\d+M)?(\d+(\.\d+)?S)?$/', $raw ) && strlen( $raw ) > 2 ) {
		return $raw;
	}

	$seconds = hodima_media_duration_seconds( $raw );

	if ( $seconds <= 0 ) {
		return '';
	}

	return intdiv( $seconds, 3600 ) > 0
		? sprintf( 'PT%dH%dM%dS', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 )
		: sprintf( 'PT%dM%dS', intdiv( $seconds, 60 ), $seconds % 60 );
}

/** ثانیه → «2:35» یا «1:05:20» برای نمایش. */
function hodima_media_clock( int $seconds ): string {
	$seconds = max( 0, $seconds );
	return $seconds >= 3600
		? sprintf( '%d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 )
		: sprintf( '%d:%02d', intdiv( $seconds, 60 ), $seconds % 60 );
}

/** «۲:۳۵» → «2:35»؛ مقدار نامعتبر → رشته خالی. */
function hodima_media_sanitize_duration( string $value ): string {
	$value = trim( hodima_media_normalize_digits( $value ) );
	return '' !== hodima_media_duration_iso( $value ) ? $value : '';
}

/**
 * تاریخ به قالب ISO 8601، یا رشته خالی.
 * تاریخ نامفهوم (مثلا شمسی) رشته خالی می‌دهد، نه مقدار نامعتبر در اسکیما.
 */
function hodima_media_iso_date( mixed $date ): string {

	$date = trim( hodima_media_normalize_digits( is_scalar( $date ) ? (string) $date : '' ) );

	if ( '' === $date ) {
		return '';
	}

	$ts = ctype_digit( $date ) ? (int) $date : strtotime( $date );

	return ( $ts && $ts > 0 ) ? gmdate( 'c', $ts ) : '';
}

/**
 * فصل‌های ویدیو («لحظه‌های کلیدی» گوگل): هر خط «زمان عنوان».
 *   ۰:۰۰ معرفی
 *   1:20 - رنگ‌بندی
 * مرتب بر اساس زمان، بدون زمان تکراری.
 *
 * @return list<array{start: int, title: string}>
 */
function hodima_media_parse_chapters( mixed $raw ): array {

	$lines = preg_split( '/\R/u', hodima_media_normalize_digits( is_scalar( $raw ) ? (string) $raw : '' ) ) ?: [];
	$out   = [];

	foreach ( $lines as $line ) {
		if ( ! preg_match( '/^\s*(\d{1,2}(?::\d{1,2}){1,2})\s*[-–—|:.)]?\s*(.+?)\s*$/u', $line, $m ) ) {
			continue;
		}
		$title = mb_substr( trim( wp_strip_all_tags( $m[2] ) ), 0, 100 );
		$start = hodima_media_duration_seconds( $m[1] );
		if ( '' !== $title && ! isset( $out[ $start ] ) ) {
			$out[ $start ] = [ 'start' => $start, 'title' => $title ];
		}
	}

	ksort( $out );

	return array_values( $out );
}

/* =====================================================================
 * Discover (نوشته‌ها و برگه‌ها)
 * ===================================================================== */

/**
 * آیا بخش «Discover» (عنوان Discover، تصویر Discover، موجودیت‌ها) برای
 * این شیء فعال است؟
 *
 *   نوشته و برگه: بله.
 *   محصول و دسته: خیر (عنوان تبلیغاتی جای نام محصول نیست و دسته نود
 *     WebPage خودش را دارد). داده ذخیره‌شده حذف نمی‌شود. برای برگرداندن:
 *     add_filter( 'hook_modern_seo_post_types', fn( $t ) => [ ...$t, 'product' ] );
 */
function hodima_media_discover_enabled( string $context, int|string $object_id = 0 ): bool {

	$enabled = false;

	if ( 'post' === $context ) {

		$post_type = ( is_numeric( $object_id ) && (int) $object_id > 0 ) ? (string) get_post_type( (int) $object_id ) : '';

		if ( '' === $post_type && function_exists( 'get_current_screen' ) && get_current_screen() ) {
			$post_type = (string) get_current_screen()->post_type;
		}

		$enabled = in_array( $post_type, (array) apply_filters( 'hook_modern_seo_post_types', [ 'post', 'page' ] ), true );
	}

	return (bool) apply_filters( 'hook_modern_seo_enabled', $enabled, $context, $object_id );
}
