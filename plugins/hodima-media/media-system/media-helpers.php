<?php
/**
 * Media System — Helpers
 * Path: media-system/media-helpers.php
 * Version: 3.0.0
 *
 * ─────────────────────────────────────────────────────────────────────
 * قرارداد عمومی
 * ─────────────────────────────────────────────────────────────────────
 * این سه تابع توسط ماژول‌های دیگر استفاده می‌شوند و امضایشان ثابت است:
 *
 *   hook_get_media_data()       ← seobox، blog-schema، product-schema-pro،
 *                                  sitemap-core، template-page-videos
 *   hook_format_duration_iso()  ← home/logic، product-schema-pro،
 *                                  template-page-videos
 *   hook_is_direct_video_file() ← product-schema-pro
 *
 * چون hook_get_media_data() تنها مسیر خواندن داده برای همه است، اصلاح
 * داده *اینجا* (کاور، موجودیت‌ها) بدون تغییر در آن فایل‌ها به همه
 * می‌رسد.
 * ─────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HOOK_MEDIA_VERSION = '3.0.0';

/* =====================================================================
 * کلیدها و پشتیبانی
 * ===================================================================== */

/**
 * کلیدهای متای سیستم رسانه.
 * منبع واحد برای خواندن و نوشتن.
 */
function hook_media_meta_keys(): array {
	return [
		'enabled', 'content',
		'video_url', 'video_thumb', 'video_keywords', 'video_duration',
		'video_date', 'video_title', 'video_cover',
		'voice_url', 'voice_keywords', 'voice_duration', 'voice_date', 'voice_title',
		'discover_title', 'ai_summary', 'key_entities', 'faq',
	];
}

function hook_get_supported_post_types(): array {
	return (array) apply_filters( 'hook_media_post_types', [ 'product', 'post', 'page' ] );
}

function hook_get_supported_taxonomies(): array {
	return (array) apply_filters( 'hook_media_taxonomies', [ 'product_cat', 'category' ] );
}

/** نسخه دارایی = زمان تغییر فایل (نسخه ثابت، به‌روزرسانی را پنهان می‌کرد). */
function hook_media_asset_version( string $relative ): string {
	$path = HODIMA_MEDIA_DIR . '/media-system/' . ltrim( $relative, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : HOOK_MEDIA_VERSION;
}

/* =====================================================================
 * یکسان‌سازی
 * ===================================================================== */

/** ارقام فارسی و عربی → لاتین. */
function hook_normalize_digits( string $value ): string {
	return strtr( $value, [
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	] );
}

/**
 * موجودیت‌های کلیدی به صورت آرایه.
 *
 * نسخه قبلی در پنج جای مختلف با سه قانون متفاوت تفکیک می‌شد: بیشترشان
 * فقط ویرگول لاتین «,» را می‌شناختند و ویرگول فارسی «،» — که ویرایشگر
 * فارسی طبیعتا تایپ می‌کند — تفکیک نمی‌شد. نتیجه در اسکیمای محصول یک
 * «مرتبط با» با کل متن در یک مقدار بود.
 *
 * جداکننده‌ها: خط جدید، «,» «،» «;» «؛» «|»
 *
 * @return string[]
 */
function hook_parse_key_entities( $raw ): array {

	$parts = preg_split( '/[\r\n,،;؛|]+/u', (string) $raw ) ?: [];
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
 * تمام متادیتای رسانه یک نوشته یا ترم (با کش درون‌درخواستی).
 *
 * مقادیر مشتق‌شده — تا همه مصرف‌کننده‌ها بدون تغییر، داده درست بگیرند:
 *
 *   video_thumb   اگر خالی بود = video_cover
 *                 فرم فقط «کاور» را می‌نویسد ولی همه کدهای اسکیما
 *                 video_thumb را می‌خواندند؛ پس کاوری که مدیر انتخاب
 *                 می‌کرد هرگز به گوگل نمی‌رسید و همیشه عکس اصلی
 *                 محصول/دسته به عنوان تصویر ویدیو فرستاده می‌شد.
 *
 *   key_entities  به شکل یکسان «الف, ب, ج» — هر مصرف‌کننده‌ای که با
 *                 explode(',') تفکیک می‌کند، درست کار می‌کند.
 *
 * @param bool $force کش را دور بزن و دوباره از دیتابیس بخوان.
 */
function hook_get_media_data( int $object_id, string $context = 'post', bool $force = false ): array {

	static $cache = [];

	$cache_key = $context . ':' . $object_id;

	if ( ! $force && isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	if ( $object_id <= 0 ) {
		return [];
	}

	$is_post = ( 'post' === $context );
	$prefix  = $is_post ? '_hook_' : 'hook_';

	$data = [];

	foreach ( hook_media_meta_keys() as $key ) {

		$val = $is_post
			? get_post_meta( $object_id, $prefix . $key, true )
			: get_term_meta( $object_id, $prefix . $key, true );

		// سازگاری با نسخه قدیمی که کلیدها را بدون پیشوند ذخیره می‌کرد.
		// (ذخیره بعدی این کلیدهای قدیمی را پاک می‌کند؛ hook_media_save را ببینید.)
		if ( '' === $val ) {
			$val = $is_post ? get_post_meta( $object_id, $key, true ) : get_term_meta( $object_id, $key, true );
		}

		$data[ $key ] = $val;
	}

	$data['faq'] = is_array( $data['faq'] ) ? $data['faq'] : [];

	if ( '' === (string) $data['video_thumb'] && '' !== (string) $data['video_cover'] ) {
		$data['video_thumb'] = $data['video_cover'];
	}

	if ( '' !== (string) $data['key_entities'] ) {
		$data['key_entities'] = implode( ', ', hook_parse_key_entities( $data['key_entities'] ) );
	}

	return $cache[ $cache_key ] = $data;
}

/**
 * آدرس یک شیء برای @id در JSON-LD.
 *
 * نسخه قبلی *همیشه* canonical صفحه جاری را برمی‌گرداند، حتی وقتی
 * شیء دیگری خواسته شده بود (مثلا [hook_video id="123"] در صفحه‌ای دیگر)؛
 * شناسه‌های اسکیمای آن شیء به صفحه اشتباه اشاره می‌کردند.
 */
function hook_media_page_url( int $object_id, string $context ): string {

	$queried = get_queried_object();

	$is_current = ( 'post' === $context && $queried instanceof WP_Post && (int) $queried->ID === $object_id )
		|| ( 'term' === $context && $queried instanceof WP_Term && (int) $queried->term_id === $object_id );

	if ( $is_current && function_exists( 'hodima_get_canonical_url' ) ) {
		$canonical = hodima_get_canonical_url();
		if ( '' !== $canonical ) {
			return $canonical;
		}
	}

	if ( 'post' === $context ) {
		$link = get_permalink( $object_id );
		return $link ? (string) $link : '';
	}

	$link = get_term_link( $object_id );
	return is_wp_error( $link ) ? '' : (string) $link;
}

/* =====================================================================
 * ویدیو و زمان
 * ===================================================================== */

/**
 * آیا آدرس، فایل مستقیم ویدیو است (در مقابل صفحه آپارات/یوتیوب)؟
 * نسخه قبلی parse_url() بدون بررسی null به pathinfo() می‌داد که در
 * PHP 8.1+ اخطار deprecated تولید می‌کرد.
 */
function hook_is_direct_video_file( $url ): bool {
	$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
	$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	return in_array( $ext, [ 'mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v' ], true );
}

/**
 * مدت زمان به قالب ISO 8601 (PT5M30S)، یا رشته خالی.
 *
 * تغییرات:
 *   - ارقام فارسی پشتیبانی می‌شوند؛ «۰۵:۳۰» قبلا به PT0M0S تبدیل می‌شد.
 *   - ورودی ناشناخته رشته خالی برمی‌گرداند. نسخه قبلی خود مقدار خام را
 *     برمی‌گرداند («۵ دقیقه») که در اسکیما مقدار نامعتبر duration بود.
 */
function hook_format_duration_iso( $duration ): string {

	$duration = trim( hook_normalize_digits( (string) $duration ) );

	if ( '' === $duration ) {
		return '';
	}

	if ( preg_match( '/^PT(\d+H)?(\d+M)?(\d+(\.\d+)?S)?$/i', $duration ) && strlen( $duration ) > 2 ) {
		return strtoupper( $duration );
	}

	if ( ctype_digit( $duration ) ) {
		return 'PT' . (int) $duration . 'S';
	}

	if ( preg_match( '/^(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $duration, $m ) ) {
		return sprintf( 'PT%dH%dM%dS', $m[1], $m[2], $m[3] );
	}

	if ( preg_match( '/^(\d{1,3}):(\d{1,2})$/', $duration, $m ) ) {
		return sprintf( 'PT%dM%dS', $m[1], $m[2] );
	}

	return '';
}

/** @deprecated نام قدیمی؛ به hook_format_duration_iso() ارجاع می‌دهد. */
function hook_duration_to_iso( $duration ): string {
	return hook_format_duration_iso( $duration );
}

/**
 * تاریخ به قالب ISO 8601، یا رشته خالی.
 * نسخه قبلی در صورت شکست خود مقدار خام را برمی‌گرداند؛ تاریخ شمسی
 * («۱۴۰۵/۰۶/۳۱») مستقیم به uploadDate می‌رفت که مقدار نامعتبر بود.
 */
function hook_normalize_iso_date( $date ): string {

	$date = trim( hook_normalize_digits( (string) $date ) );

	if ( '' === $date ) {
		return '';
	}

	$ts = ctype_digit( $date ) ? (int) $date : strtotime( $date );

	return ( $ts && $ts > 0 ) ? gmdate( 'c', $ts ) : '';
}

/* =====================================================================
 * «سئو مدرن» (عنوان Discover، موجودیت‌های کلیدی، خلاصه هوش مصنوعی)
 * ===================================================================== */

/**
 * آیا بخش «سئو مدرن» برای این شیء فعال است؟
 *
 *   نوشته و برگه: بله — برای مقاله، abstract و about ویژگی‌های استاندارد
 *     Article‌اند و blog-schema.php درست از آن‌ها استفاده می‌کند.
 *   محصول: خیر — داده تکراری بود (خلاصه هم در باکس هم در description
 *     اسکیما) و دو فیلد نادرست به کار می‌رفتند: عنوان Discover به عنوان
 *     alternateName (نام دیگر محصول، نه تیتر تبلیغاتی) و موجودیت‌ها به
 *     عنوان مشخصه جعلی «مرتبط با».
 *   دسته‌بندی: خیر — توضیح و معرفی و FAQ دسته وجود دارد؛ این بخش یک نود
 *     WebPage *دوم* برای همان آدرس می‌ساخت.
 *
 * داده ذخیره‌شده حذف نمی‌شود؛ فقط نمایش و استفاده متوقف می‌شود. برای
 * برگرداندن (مثلا برای محصولات):
 *     add_filter( 'hook_modern_seo_post_types', fn( $t ) => [ ...$t, 'product' ] );
 */
function hook_modern_seo_enabled( string $context, int|string $object_id = 0 ): bool {

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
