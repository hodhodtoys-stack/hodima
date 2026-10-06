<?php
/**
 * Module: UI Performance (preconnect رسانه، preload فونت)
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
 *   ۲. دستی: «تنظیمات قالب ← دامنه ویدئو» — فقط در صفحه‌ای که از همان دامنه رسانه دارد (2.9.1).
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

    $media  = hodima_page_media_urls();
    $auto   = ! function_exists( 'hodima_setting' ) || hodima_setting( 'preconnect_media_auto' );
    $domains = preg_split( '/\R/u', function_exists( 'hodima_setting' ) ? (string) hodima_setting( 'preconnect_hosts' ) : '' ) ?: [];

    /*
     * «تنظیمات قالب ← دامنه ویدئو»: هر دامنه فقط در صفحه‌ای که از آن ویدیو یا
     * پادکست دارد (رسانه همین صفحه یا آدرسی داخل متن نوشته/توضیح دسته). قبلا
     * (2.8.0) «دامنه‌های همیشگی» در همه صفحه‌ها بود.
     */
    $haystack = strtolower( implode( ' ', $media ) . ' ' . hodima_page_text() );
    $used     = array_filter(
        $domains,
        static function ( string $domain ) use ( $haystack ): bool {
            $host = strtolower( (string) wp_parse_url( hodima_url_origin( $domain ), PHP_URL_HOST ) );
            return '' !== $host && str_contains( $haystack, '//' . $host );
        }
    );

    // اول رسانه خود صفحه (با سقف تعداد)، بعد دامنه‌های تنظیمات که این صفحه استفاده می‌کند
    $urls = [ ...( $auto ? $media : [] ), ...$used ];

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

/** متن خام شیء جاری (متن نوشته/برگه/محصول یا توضیح دسته) برای یافتن آدرس رسانه جاسازی‌شده. */
function hodima_page_text(): string {

    $object = get_queried_object();

    return match ( true ) {
        $object instanceof WP_Post => (string) $object->post_content,
        $object instanceof WP_Term => (string) $object->description,
        default                    => '',
    };
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
 * ۳. «CSS حیاتی» درون‌خطی — حذف شد (نوسازی قالب، مرحله ۸؛ HODIMA-AUDIT.md بخش ۶۵)
 * ------------------------------------------------------------
 * قبلا hodima_print_critical_css() در هر صفحه (بدون لایت‌اسپید) یک <style>
 * پر از !important چاپ می‌کرد: رنگ .button/.btn-primary/.single_add_to_cart_button،
 * .card-top-line و عدد ۴۰۴. اندازه‌گیری روی ۴۰ صفحه (هر عنصر، عادی و hover):
 *   - .btn-primary، .card-top-line و .error-404-number/.highlight-number در
 *     قالب و افزونه‌ها هیچ عنصری ندارند (کد مرده)؛
 *   - دکمه خرید و بقیه .buttonها همین رنگ را از style.css/single-product.css
 *     می‌گیرند — تنها اثر: حاشیه ۱ پیکسلی و رنگ زیر گرادیان سه دکمه سبد/پرداخت ووکامرس؛
 *   - «حیاتی» هم نبود: CSS قالب در head مسدودکننده است و صفحه پیش از آن رسم
 *     نمی‌شود؛ و با لایت‌اسپید (سایت اصلی) اصلا چاپ نمی‌شد.
 * پس حذف شد؛ ظاهر سایت بدون لایت‌اسپید حالا همان سایت با لایت‌اسپید است.
 * ============================================================ */
