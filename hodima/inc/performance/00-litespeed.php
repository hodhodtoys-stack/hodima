<?php
/**
 * Module: LiteSpeed Cache Bridge
 * Path: hodima/inc/performance/00-litespeed.php
 *
 * پیشوند عددی عمدی است: فایل‌های این پوشه با glob و به ترتیب الفبا لود
 * می‌شوند، پس این فایل قبل از بقیه اجرا می‌شود و توابعش در دسترس‌اند.
 *
 * وظیفه: وقتی افزونه LiteSpeed Cache فعال است،
 *   ۱. از بهینه‌سازی‌های نمایشی که لایت‌اسپید بهتر انجام می‌دهد کنار بکش
 *   ۲. صفحه اصلی را وقتی بخش‌هایش عوض می‌شوند از کش پاک کن
 * سیاست کش (نماهای فیلترشده، ترم‌ها، ربات‌ها) در Hodima Core است.
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

    // Hodima Core 1.2+ همین تشخیص (با همان فیلتر) را دارد؛ افزونه SEO هم از آنجا می‌خواند
    if ( function_exists( 'hodima_core_litespeed_active' ) ) {
        return $active = hodima_core_litespeed_active();
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
 * ۲. سیاست کش (کش‌نکردن نماهای فیلترشده/مرتب‌شده، پاکسازی صفحه‌های ترم
 *    بعد از ویرایش، کش پاسخ ربات‌ها) → افزونه Hodima Core
 *    (includes/litespeed.php) — بازسازی قالب، مرحله ۲. این‌ها به داده
 *    سایت مربوط‌اند نه ظاهر، و با عوض شدن قالب نباید از کار بیفتند.
 * ------------------------------------------------------------
 * اینجا فقط پاکسازی صفحه اصلی می‌ماند: بخش‌های صفحه اصلی (اسلایدر
 * محصولات، دسته‌ها، آخرین مقالات) را خود قالب نمایش می‌دهد.
 * ============================================================ */

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
