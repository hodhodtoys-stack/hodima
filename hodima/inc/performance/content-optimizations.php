<?php
/**
 * Module: Content Optimizations (Lazyload Iframes & JS Defer)
 * Path: hodima/inc/performance/content-optimizations.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * ۱. اضافه کردن lazy به iframe
 * ------------------------------------------------------------
 * باگ نسخه قبلی: شرط !str_contains($content, 'loading=') کل محتوا را
 * بررسی می‌کرد، بنابراین وجود یک <img loading="lazy"> در متن باعث
 * می‌شد هیچ iframe ای lazy نشود. حالا هر iframe جداگانه بررسی می‌شود.
 * ============================================================ */
add_filter( 'the_content', 'hodima_lazyload_content_iframes', 99 );

function hodima_lazyload_content_iframes( $content ) {

    // لایت‌اسپید خودش lazy load آیفریم دارد و src را با data-src جایگزین
    // می‌کند. اجرای همزمان هر دو مکانیزم روی یک آیفریم، بعضی امبدها
    // (به‌ویژه پلیرهای ویدیو) را از کار می‌اندازد.
    if ( function_exists( 'hodima_litespeed_handles' ) && hodima_litespeed_handles( 'iframe_lazy' ) ) {
        return $content;
    }

    if ( ! is_string( $content ) || ! str_contains( $content, '<iframe' ) ) {
        return $content;
    }

    return (string) preg_replace_callback(
        '/<iframe\b([^>]*)>/i',
        static function ( array $match ): string {

            $attributes = $match[1];

            if ( preg_match( '/\bloading\s*=/i', $attributes ) ) {
                return $match[0];
            }

            return '<iframe loading="lazy"' . $attributes . '>';
        },
        $content
    );
}

/* ============================================================
 * ۲. Defer کردن جاوااسکریپت‌ها
 * ------------------------------------------------------------
 * نسخه قبلی همه چیز را به جز jquery-core و jquery-migrate defer می‌کرد.
 * مشکل اصلی: وقتی اسکریپتی inline از نوع "after" داشته باشد، وردپرس آن
 * inline را بلافاصله بعد از تگ اصلی و بدون defer چاپ می‌کند. پس کد inline
 * زودتر از اسکریپت defer شده اجرا می‌شود و با خطای undefined می‌شکند —
 * دقیقا همان چیزی که اسکریپت‌های ووکامرس را از کار می‌اندازد.
 * ============================================================ */
add_filter( 'script_loader_tag', 'hodima_defer_script_tag', 10, 3 );

function hodima_defer_script_tag( $tag, $handle, $src ) {

    /*
     * اگر لایت‌اسپید فعال است، این کار را به او واگذار کن.
     *
     * دلیلش فقط تکراری بودن نیست. لایت‌اسپید گزینه «JS Combine» دارد که
     * چند فایل را در یکی ادغام می‌کند. وقتی قالب بعضی تگ‌ها را defer کرده
     * و بعضی را نه، ادغام آن‌ها ترتیب اجرا را عوض می‌کند و نتیجه‌اش
     * خطاهای «undefined is not a function» است که بازتولیدشان سخت است.
     *
     * لایت‌اسپید defer و combine را با هم و آگاهانه مدیریت می‌کند.
     *
     * برای برگرداندن کنترل به قالب:
     *     add_filter( 'hodima_litespeed_handles_js_defer', '__return_false' );
     */
    if ( function_exists( 'hodima_litespeed_handles' ) && hodima_litespeed_handles( 'js_defer' ) ) {
        return $tag;
    }

    if ( is_admin() || is_customize_preview() || is_feed() ) {
        return $tag;
    }

    if ( ! is_string( $tag ) || ! str_contains( $tag, ' src=' ) ) {
        return $tag;
    }

    /**
     * هندل‌هایی که هرگز نباید defer شوند.
     * برای افزودن مورد جدید از همین فیلتر استفاده کنید.
     */
    $never_defer = (array) apply_filters( 'hodima_no_defer_handles', [
        'jquery',
        'jquery-core',
        'jquery-migrate',
    ] );

    if ( in_array( $handle, $never_defer, true ) ) {
        return $tag;
    }

    // اسکریپت inline از نوع after → defer کردن آن را می‌شکند
    $scripts = wp_scripts();
    if ( isset( $scripts->registered[ $handle ] ) ) {
        $extra = $scripts->registered[ $handle ]->extra;
        if ( ! empty( $extra['after'] ) ) {
            return $tag;
        }
    }

    // از قبل async / defer / module دارد
    if ( preg_match( '/\s(async|defer)[\s=>]/i', $tag ) || str_contains( $tag, 'type="module"' ) ) {
        return $tag;
    }

    // نکته: ' src=' و نه ' src' تا با srcset تداخل نکند
    return str_replace( ' src=', ' defer src=', $tag );
}
