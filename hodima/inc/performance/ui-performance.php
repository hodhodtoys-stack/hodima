<?php
/**
 * Module: UI Performance & Critical CSS (Custom Theme)
 * Path: hodima/inc/performance/ui-performance.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * ۱. Preconnect و DNS Prefetch
 * ------------------------------------------------------------
 * تا پیش از این، not-crawl.php کل اکشن wp_resource_hints را
 * remove_action می‌کرد و این فیلتر هرگز اجرا نمی‌شد (کد مرده).
 * با اصلاح wp-cleanup.php حالا فعال است.
 * ============================================================ */
add_filter( 'wp_resource_hints', 'hodima_add_resource_hints', 10, 2 );

function hodima_add_resource_hints( array $urls, string $relation_type ): array {

    if ( function_exists( 'hodima_litespeed_handles' ) && hodima_litespeed_handles( 'resource_hints' ) ) {
        return $urls;
    }

    if ( 'preconnect' !== $relation_type && 'dns-prefetch' !== $relation_type ) {
        return $urls;
    }

    /**
     * دامنه‌های خارجی که قالب به آن‌ها متصل می‌شود.
     * برای افزودن/حذف دامنه، این فیلتر را در functions.php استفاده کنید.
     */
    $hosts = (array) apply_filters( 'hodima_preconnect_hosts', [
        'https://dl.hodima.com',
    ] );

    foreach ( $hosts as $host ) {
        if ( ! in_array( $host, $urls, true ) ) {
            $urls[] = $host;
        }
    }

    return $urls;
}

/* ============================================================
 * ۲. Preload فونت‌ها
 * ------------------------------------------------------------
 * قبلا فقط Vazirmatn-Medium (وزن ۵۰۰) preload می‌شد، در حالی که
 * body هیچ font-weight صریحی ندارد و روی ۴۰۰ (Regular) می‌افتد.
 * یعنی مرورگر با اولویت بالا فایلی را می‌گرفت که برای متن اصلی
 * لازم نبود و Regular با تاخیر لود می‌شد.
 * ============================================================ */
add_action( 'wp_head', 'hodima_preload_fonts', 1 );

function hodima_preload_fonts(): void {

    $fonts = [
        'assets/fonts/Vazirmatn-Regular.woff2', // متن اصلی (font-weight: 400)
        'assets/fonts/Vazirmatn-Bold.woff2',    // تیترها (font-weight: 700)
    ];

    foreach ( $fonts as $font ) {

        if ( ! is_file( get_theme_file_path( $font ) ) ) {
            continue;
        }

        printf(
            '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
            esc_url( get_theme_file_uri( $font ) )
        );
    }
}

/* ============================================================
 * ۳. تزریق استایل‌های حیاتی با رنگ‌های سازمانی
 * ============================================================ */
add_action( 'wp_head', 'hodima_print_critical_css', 2 );

function hodima_print_critical_css(): void {

    /*
     * لایت‌اسپید Critical CSS را با QUIC.cloud برای هر قالب صفحه جداگانه
     * تولید می‌کند و بقیه CSS را async می‌کند. بلوک دستی زیر پر از
     * !important است، پس روی CCSS تولیدشده غالب می‌شود و همان چیزی را
     * که لایت‌اسپید دقیق محاسبه کرده خراب می‌کند.
     *
     * برای برگرداندن کنترل به قالب:
     *     add_filter( 'hodima_litespeed_handles_critical_css', '__return_false' );
     */
    if ( function_exists( 'hodima_litespeed_handles' ) && hodima_litespeed_handles( 'critical_css' ) ) {
        return;
    }

    $critical_css  = '.btn-primary,.button{background-color:#25316a!important;color:#fff!important;border-color:#25316a!important}';
    $critical_css .= '.btn-primary:hover,.button:hover{background-color:#607bbd!important;border-color:#607bbd!important}';
    $critical_css .= '.card-top-line{background:linear-gradient(90deg,#25316a,#607bbd,#c1c9ec)!important}';

    if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
        $critical_css .= '.single_add_to_cart_button{background-color:#25316a!important;color:#fff!important;border-color:#25316a!important}';
        $critical_css .= '.single_add_to_cart_button:hover{background-color:#607bbd!important;border-color:#607bbd!important}';
    }

    if ( is_404() ) {
        $critical_css .= '.error-404-number,.highlight-number{background:linear-gradient(45deg,#25316a,#607bbd);-webkit-background-clip:text;-webkit-text-fill-color:transparent}';
    }

    echo '<style id="hodima-critical-css">' . $critical_css . '</style>' . "\n";
}
