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
 * ۱. Preconnect و DNS Prefetch — دامنه پخش ویدیو و پادکست
 * ------------------------------------------------------------
 * قبلا https://dl.hodima.com ثابت در کد بود و در *همه* صفحه‌ها (حتی
 * بدون هیچ ویدیو/صوتی) اتصال زودهنگام می‌گرفت؛ روی سایت دیگری که قالب را
 * نصب می‌کرد هم به همان دامنه وصل می‌شد. حالا «هوشمند»:
 *   ۱. خودکار: دامنه ویدیو، کاور و پادکست همین صفحه (کادر «رسانه» افزونه
 *      Hodima Media، و صفحه هر ویدیو) — فقط صفحه‌ای که رسانه دارد.
 *   ۲. دستی: «تنظیمات قالب ← سرعت بارگذاری ← دامنه‌های همیشگی» (همه صفحه‌ها).
 * دامنه خود سایت حذف می‌شود (اتصالش از قبل باز است). فیلتر hodima_preconnect_hosts
 * مثل قبل برای کد سفارشی.
 * ============================================================ */
add_filter( 'wp_resource_hints', 'hodima_add_resource_hints', 10, 2 );

/**
 * @param array<int, string|array<string, string>> $urls
 * @return array<int, string|array<string, string>>
 */
function hodima_add_resource_hints( array $urls, string $relation_type ): array {

    if ( function_exists( 'hodima_litespeed_handles' ) && hodima_litespeed_handles( 'resource_hints' ) ) {
        return $urls;
    }

    if ( 'preconnect' !== $relation_type && 'dns-prefetch' !== $relation_type ) {
        return $urls;
    }

    /**
     * دامنه‌های خارجی که این صفحه از اول به آن‌ها وصل شود.
     * برای افزودن/حذف دامنه، این فیلتر را در کد سفارشی استفاده کنید.
     */
    $hosts = (array) apply_filters( 'hodima_preconnect_hosts', hodima_preconnect_hosts() );

    foreach ( $hosts as $host ) {
        if ( is_string( $host ) && '' !== $host && ! in_array( $host, $urls, true ) ) {
            $urls[] = $host;
        }
    }

    return $urls;
}

/** بیشترین تعداد دامنه preconnect در یک صفحه. */
const HODIMA_PRECONNECT_MAX = 4;

/**
 * دامنه‌های preconnect صفحه جاری (origin مثل https://dl.example.com).
 * بیشتر از HODIMA_PRECONNECT_MAX نه: هر اتصال زودهنگام هزینه دارد.
 *
 * @return list<string>
 */
function hodima_preconnect_hosts(): array {

    static $memo = null;
    if ( null !== $memo ) {
        return $memo;
    }

    // اول رسانه همین صفحه (با سقف تعداد، مهم‌تر از دامنه‌های همیشگی است)
    $auto   = ! function_exists( 'hodima_setting' ) || hodima_setting( 'preconnect_media_auto' );
    $always = preg_split( '/\R/u', function_exists( 'hodima_setting' ) ? (string) hodima_setting( 'preconnect_hosts' ) : '' ) ?: [];
    $urls   = [ ...( $auto ? hodima_page_media_urls() : [] ), ...$always ];

    $own   = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    $hosts = [];

    foreach ( $urls as $url ) {
        $origin = hodima_url_origin( (string) $url );
        if ( '' !== $origin && strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) ) !== $own ) {
            $hosts[ $origin ] = true;
        }
    }

    $memo = array_slice( array_keys( $hosts ), 0, HODIMA_PRECONNECT_MAX );

    return $memo;
}

/** «https://dl.example.com/a/b.mp4» → «https://dl.example.com» (یا '' اگر آدرس http(s) نیست). */
function hodima_url_origin( string $url ): string {

    $parts  = wp_parse_url( trim( $url ) );
    $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
    $host   = strtolower( (string) ( $parts['host'] ?? '' ) );

    if ( '' === $host || ! in_array( $scheme, [ 'https', 'http' ], true ) ) {
        return '';
    }

    return $scheme . '://' . $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
}

/**
 * آدرس‌های ویدیو، کاور و پادکست صفحه جاری که مرورگر بلافاصله دریافت می‌کند.
 *   - کادر «رسانه» (نوشته، برگه، محصول، دسته) وقتی روشن است — Hodima Media؛
 *   - صفحه هر ویدیو (نوع «video» افزونه رسانه، متای _hod_video_*).
 * ویدیوی آپارات/یوتیوب: دامنه پخش‌کننده iframe (نه صفحه تماشا).
 *
 * @return list<string>
 */
function hodima_page_media_urls(): array {

    $object = get_queried_object();
    $player = static fn( string $url ): string => ( '' !== $url && function_exists( 'hodima_media_player_url' ) ) ? hodima_media_player_url( $url ) : $url;
    $urls   = [];

    [ $id, $context ] = match ( true ) {
        $object instanceof WP_Post => [ (int) $object->ID, 'post' ],
        $object instanceof WP_Term => [ (int) $object->term_id, 'term' ],
        default                    => [ 0, '' ],
    };

    if ( $id && function_exists( 'hodima_media_get_data' ) && function_exists( 'hodima_media_is_enabled' ) && hodima_media_is_enabled( $id, $context ) ) {
        $data   = hodima_media_get_data( $id, $context );
        $urls[] = $player( (string) ( $data['video_url'] ?? '' ) );
        $urls[] = (string) ( $data['video_cover'] ?? '' );
        $urls[] = (string) ( $data['voice_url'] ?? '' );
    }

    if ( $object instanceof WP_Post && 'video' === $object->post_type ) {
        $urls[] = $player( (string) get_post_meta( $object->ID, '_hod_video_url', true ) );
        $urls[] = (string) get_post_meta( $object->ID, '_hod_video_thumbnail', true );
    }

    return array_values( array_filter( $urls ) );
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

    // رنگ‌ها از توکن‌ها (پالت «تنظیمات قالب ← برند»)، با همان مقدار پیش‌فرض
    $critical_css  = '.btn-primary,.button{background-color:var(--hodima-primary,#25316a)!important;color:#fff!important;border-color:var(--hodima-primary,#25316a)!important}';
    $critical_css .= '.btn-primary:hover,.button:hover{background-color:var(--hodima-secondary,#607bbd)!important;border-color:var(--hodima-secondary,#607bbd)!important}';
    $critical_css .= '.card-top-line{background:linear-gradient(90deg,var(--hodima-primary,#25316a),var(--hodima-secondary,#607bbd),var(--hodima-third,#b6c2f3))!important}';

    if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
        $critical_css .= '.single_add_to_cart_button{background-color:var(--hodima-primary,#25316a)!important;color:#fff!important;border-color:var(--hodima-primary,#25316a)!important}';
        $critical_css .= '.single_add_to_cart_button:hover{background-color:var(--hodima-secondary,#607bbd)!important;border-color:var(--hodima-secondary,#607bbd)!important}';
    }

    if ( is_404() ) {
        $critical_css .= '.error-404-number,.highlight-number{background:linear-gradient(45deg,var(--hodima-primary,#25316a),var(--hodima-secondary,#607bbd));-webkit-background-clip:text;-webkit-text-fill-color:transparent}';
    }

    echo '<style id="hodima-critical-css">' . $critical_css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS ثابت همین تابع
}
