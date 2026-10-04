<?php
/**
 * پل LiteSpeed Cache — سیاست کش (مستقل از قالب)
 * Path: hodima-core/includes/litespeed.php
 *
 * از قالب (inc/performance/00-litespeed.php) منتقل شد — بازسازی قالب، مرحله ۲.
 * تصمیم «چه چیزی کش شود و کی پاک شود» به داده سایت مربوط است، نه به ظاهر؛
 * با عوض شدن قالب نباید از کار بیفتد. افزونه SEO هم تشخیص لایت‌اسپید را از
 * همین‌جا می‌خواند (قبلا از قالب).
 *
 * تشخیص hodima_litespeed_handles() (کنار کشیدن بهینه‌سازی‌های ظاهری قالب وقتی
 * لایت‌اسپید همان کار را می‌کند) و پاکسازی صفحه اصلی بعد از تغییر بخش‌های آن
 * در قالب می‌مانند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * آیا افزونه LiteSpeed Cache فعال است؟
 * نام جدا از hodima_litespeed_active() قالب: قالب قبل از 2.3.0 آن را بدون گارد
 * تعریف می‌کند و تعریف دوباره اینجا خطای «Cannot redeclare» می‌داد.
 */
function hodima_core_litespeed_active(): bool {

	static $active = null;

	if ( null === $active ) {
		$active = defined( 'LSCWP_V' )
			|| class_exists( '\\LiteSpeed\\Core' )
			|| class_exists( 'LiteSpeed_Cache' );

		// همان فیلتر قبلی قالب
		$active = (bool) apply_filters( 'hodima_litespeed_active', $active );
	}

	return $active;
}

/* ============================================================
 * ۱. نماهای فیلترشده و مرتب‌شده کش نشوند
 * ------------------------------------------------------------
 * هر ترکیب پارامتر فیلتر/مرتب‌سازی (?orderby=price&min_price=…&filter_color=…)
 * یک آدرس یکتاست و تعدادشان عملا نامحدود. کش کردن همه، فضای کش را با
 * نسخه‌های یک‌بارمصرف پر می‌کند و صفحه‌های واقعی زودتر از کش بیرون می‌افتند.
 * تصمیم کارایی است، نه سئو: هیچ متاتگ یا هدر رباتی چاپ نمی‌شود.
 * ============================================================ */
add_action( 'template_redirect', 'hodima_core_litespeed_skip_variants', 1 );

function hodima_core_litespeed_skip_variants(): void {

	if ( hodima_theme_has_legacy_logic() || ! hodima_core_litespeed_active() || is_admin() || wp_doing_ajax() ) {
		return;
	}

	if ( hodima_core_request_has_filter_params() ) {
		// API رسمی افزونه برای «این درخواست را کش نکن»
		do_action( 'litespeed_control_set_nocache', 'hodima: filter/sort variant' );
	}
}

/** آیا درخواست جاری پارامتر فیلتر یا مرتب‌سازی دارد؟ */
function hodima_core_request_has_filter_params(): bool {

	if ( empty( $_GET ) ) {
		return false;
	}

	$params = (array) apply_filters( 'hodima_cache_skip_query_params', [
		'orderby', 'min_price', 'max_price', 'stock_status',
		'rating_filter', 'per_page', 'per_row',
	] );

	foreach ( $params as $param ) {
		if ( isset( $_GET[ $param ] ) ) {
			return true;
		}
	}

	foreach ( array_keys( $_GET ) as $key ) {
		$key = (string) $key;
		if ( str_starts_with( $key, 'filter_' ) || str_starts_with( $key, 'wc-ajax' ) ) {
			return true;
		}
	}

	return false;
}

/* ============================================================
 * ۲. پاکسازی کش صفحه‌های یک ترم بعد از ویرایش یا حذف آن
 * ------------------------------------------------------------
 * لایت‌اسپید با ویرایش ترم صفحه‌های آن را باطل نمی‌کند؛ مدیر تغییر می‌دهد
 * ولی بازدیدکننده تا انقضای کش نسخه قدیمی را می‌بیند.
 * ویژگی‌های محصول (pa_*) روی جدول مشخصات همه محصولات آن ترم اثر دارند.
 * ============================================================ */
add_action( 'edited_term', 'hodima_core_litespeed_purge_term', 10, 3 );
add_action( 'delete_term', 'hodima_core_litespeed_purge_term', 10, 3 );

function hodima_core_litespeed_purge_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {

	if ( hodima_theme_has_legacy_logic() || ! hodima_core_litespeed_active() ) {
		return;
	}

	$taxonomy = (string) $taxonomy;

	if ( str_starts_with( $taxonomy, 'pa_' ) || in_array( $taxonomy, [ 'product_cat', 'category' ], true ) ) {
		do_action( 'litespeed_purge_post_tag', (int) $term_id );
	}
}

/* ============================================================
 * ۳. سشن ربات‌ها و کش صفحه
 * ------------------------------------------------------------
 * Hodima Commerce برای ربات‌های موتور جستجو سشن ووکامرس نمی‌سازد (بدون کوکی).
 * این با کش صفحه سازگار است چون HTML تغییری نمی‌کند. اگر چیزی خروجی را بر
 * اساس سشن تغییر داد، با این فیلتر پاسخ ربات‌ها کش نمی‌شود:
 *     add_filter( 'hodima_litespeed_nocache_bots', '__return_true' );
 * ============================================================ */
add_action( 'template_redirect', 'hodima_core_litespeed_bot_cache_policy', 2 );

function hodima_core_litespeed_bot_cache_policy(): void {

	if ( hodima_theme_has_legacy_logic() || ! hodima_core_litespeed_active() || is_admin() ) {
		return;
	}

	if ( apply_filters( 'hodima_litespeed_nocache_bots', false ) && hodima_is_search_bot() ) {
		do_action( 'litespeed_control_set_nocache', 'hodima: bot session handler active' );
	}
}
