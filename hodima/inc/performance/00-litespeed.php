<?php
/**
 * Module: LiteSpeed Cache Bridge
 * Path: hodima/inc/performance/00-litespeed.php
 *
 * پیشوند عددی عمدی است: فایل‌های این پوشه با glob و به ترتیب الفبا لود
 * می‌شوند، پس این فایل قبل از بقیه اجرا می‌شود و توابعش در دسترس‌اند.
 *
 * وظیفه: وقتی افزونه LiteSpeed Cache فعال است،
 *   ۱. از کارهایی که لایت‌اسپید بهتر انجام می‌دهد کنار بکش (تداخل نساز)
 *   ۲. جلوی کش شدن نسخه‌های خطرناک را بگیر
 *   ۳. کش را در نقاط درستی که خود قالب داده را عوض می‌کند پاک کن
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * ۱. تشخیص
 * ============================================================ */

function hodima_litespeed_active(): bool {

    static $active = null;

    if ( null !== $active ) {
        return $active;
    }

    $active = defined( 'LSCWP_V' )
        || class_exists( '\\LiteSpeed\\Core' )
        || class_exists( 'LiteSpeed_Cache' );

    return $active = (bool) apply_filters( 'hodima_litespeed_active', $active );
}

/**
 * آیا لایت‌اسپید خودش این قابلیت را بر عهده دارد؟
 *
 * دو دسته تصمیم وجود دارد:
 *
 *   الف) قابلیت‌های پرتداخل (defer جاوااسکریپت، Critical CSS).
 *        اینجا صرفِ فعال بودن لایت‌اسپید کافی است تا قالب کنار بکشد،
 *        چون اجرای همزمان هر دو می‌تواند ترتیب اجرای اسکریپت‌ها را
 *        به هم بریزد یا CSS حیاتی اشتباه را غالب کند.
 *
 *   ب) قابلیت‌های کم‌خطر (حذف ایموجی، lazy آیفریم، resource hints).
 *        اینجا تنظیم واقعی افزونه خوانده می‌شود. اگر نتوانستیم آن را
 *        بخوانیم، قالب کار خودش را ادامه می‌دهد — یعنی جهت محافظه‌کارانه
 *        «هیچ‌وقت چیزی را بی‌گدار خاموش نکن».
 */
function hodima_litespeed_handles( string $feature ): bool {

    if ( ! hodima_litespeed_active() ) {
        return false;
    }

    // دسته الف — تداخل بالا
    $always_yield = [ 'js_defer', 'critical_css' ];

    if ( in_array( $feature, $always_yield, true ) ) {
        return (bool) apply_filters( "hodima_litespeed_handles_{$feature}", true );
    }

    // دسته ب — خواندن تنظیم واقعی افزونه
    $option_map = [
        'emoji'          => 'litespeed.conf.optm-emoji_rm',
        'iframe_lazy'    => 'litespeed.conf.media-iframe_lazy',
        'resource_hints' => 'litespeed.conf.optm-dns_prefetch',
        'heartbeat'      => 'litespeed.conf.misc-heartbeat_front',
    ];

    $handles = false;

    if ( isset( $option_map[ $feature ] ) ) {
        $value = get_option( $option_map[ $feature ], null );
        // null یعنی نتوانستیم تنظیم را بخوانیم → قالب کنار نمی‌کشد
        $handles = ( null !== $value ) && ! empty( $value );
    }

    return (bool) apply_filters( "hodima_litespeed_handles_{$feature}", $handles );
}

/* ============================================================
 * ۲. بهداشت کش: نماهای فیلترشده کش نشوند
 * ------------------------------------------------------------
 * هر ترکیبی از پارامترهای فیلتر و مرتب‌سازی یک آدرس یکتا می‌سازد:
 *
 *     ?orderby=price
 *     ?orderby=price&min_price=10000
 *     ?orderby=price&min_price=10000&filter_color=12
 *     ...
 *
 * تعداد این ترکیب‌ها عملا نامحدود است. اگر همه کش شوند، فضای کش
 * با نسخه‌هایی پر می‌شود که هرکدام شاید یک بار بازدید شوند، و
 * صفحات واقعی سایت زودتر از کش بیرون می‌افتند.
 *
 * این یک تصمیم *کارایی* است، نه سئو. هیچ متاتگ یا هدر رباتی
 * اینجا چاپ نمی‌شود.
 * ============================================================ */
add_action( 'template_redirect', 'hodima_litespeed_skip_cache_for_variants', 1 );

function hodima_litespeed_skip_cache_for_variants(): void {

    if ( ! hodima_litespeed_active() || is_admin() || wp_doing_ajax() ) {
        return;
    }

    if ( ! hodima_request_has_filter_params() ) {
        return;
    }

    // API رسمی افزونه برای «این درخواست را کش نکن»
    do_action( 'litespeed_control_set_nocache', 'hodima: filter/sort variant' );
}

/** آیا درخواست جاری پارامتر فیلتر یا مرتب‌سازی دارد؟ */
function hodima_request_has_filter_params(): bool {

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
 * ۳. پاکسازی کش در نقاطی که قالب داده را عوض می‌کند
 * ------------------------------------------------------------
 * ماژول‌های قالب ترنزینت‌های خودشان را پاک می‌کنند، ولی HTML
 * کش‌شده لایت‌اسپید دست‌نخورده می‌ماند. بدون این، مدیر تغییری
 * می‌دهد، ترنزینت پاک می‌شود، ولی بازدیدکننده تا انقضای کش صفحه
 * همان نسخه قدیمی را می‌بیند و فکر می‌کند تغییر ذخیره نشده.
 * ============================================================ */
add_action( 'edited_term', 'hodima_litespeed_purge_term', 10, 3 );
add_action( 'delete_term', 'hodima_litespeed_purge_term', 10, 3 );

function hodima_litespeed_purge_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {

    if ( ! hodima_litespeed_active() ) {
        return;
    }

    $taxonomy = (string) $taxonomy;

    // ویژگی‌های محصول روی جدول مشخصات تمام محصولات آن ترم اثر می‌گذارند
    if ( str_starts_with( $taxonomy, 'pa_' ) ) {
        do_action( 'litespeed_purge_post_tag', (int) $term_id );
        return;
    }

    if ( in_array( $taxonomy, [ 'product_cat', 'category' ], true ) ) {
        do_action( 'litespeed_purge_post_tag', (int) $term_id );
    }
}

/**
 * پاکسازی کش صفحه اصلی وقتی محصولی منتشر یا ویرایش می‌شود.
 *
 * اسلایدرهای صفحه اصلی از آخرین محصولات ساخته می‌شوند، ولی
 * لایت‌اسپید با ذخیره یک محصول، صفحه اصلی را باطل نمی‌کند.
 */
add_action( 'save_post_product', 'hodima_litespeed_purge_front', 20, 1 );

function hodima_litespeed_purge_front( int $post_id ): void {

    if ( ! hodima_litespeed_active() || wp_is_post_revision( $post_id ) ) {
        return;
    }

    if ( 'publish' !== get_post_status( $post_id ) ) {
        return;
    }

    hodima_litespeed_purge_home();
}

/**
 * پاک کردن فقط صفحه اصلی از کش لایت‌اسپید (نه کل سایت).
 * بخش‌های صفحه اصلی (دسته‌ها، آخرین مقالات) با تغییر داده همین را صدا می‌زنند.
 */
function hodima_litespeed_purge_home(): void {

    if ( ! hodima_litespeed_active() ) {
        return;
    }

    do_action( 'litespeed_purge_url', home_url( '/' ) );
}

/* ============================================================
 * ۴. سشن ربات‌ها و کش صفحه
 * ------------------------------------------------------------
 * woo-optimizer.php برای ربات‌های موتور جستجو یک session handler
 * خنثی می‌گذارد تا ردیف سشن و کوکی ساخته نشود. این با کش صفحه
 * سازگار است، چون HTML خروجی تغییری نمی‌کند — فقط کوکی ساخته
 * نمی‌شود، و نبودِ کوکی دقیقا چیزی است که لایت‌اسپید برای کش کردن
 * لازم دارد.
 *
 * تنها حالت ناسازگار وقتی است که چیزی در قالب یا افزونه‌ای، خروجی
 * را بر اساس وجود سشن تغییر دهد. اگر چنین موردی دیدید، این فیلتر
 * را در functions.php برگردانید تا پاسخ ربات‌ها کش نشود:
 *
 *     add_filter( 'hodima_litespeed_nocache_bots', '__return_true' );
 * ============================================================ */
add_action( 'template_redirect', 'hodima_litespeed_bot_cache_policy', 2 );

function hodima_litespeed_bot_cache_policy(): void {

    if ( ! hodima_litespeed_active() || is_admin() ) {
        return;
    }

    if ( ! apply_filters( 'hodima_litespeed_nocache_bots', false ) ) {
        return;
    }

    if ( function_exists( 'hodima_is_search_bot' ) && hodima_is_search_bot() ) {
        do_action( 'litespeed_control_set_nocache', 'hodima: bot session handler active' );
    }
}
