<?php
/**
 * Hodima Theme Helpers
 * Path: hodima/inc/helpers.php
 *
 * فقط توابعی که خود قالب (و ماژول اختیاری پنل کاربری) لازم دارد.
 * توابع مشترک افزونه‌ها (IP واقعی، محدودیت نرخ، تشخیص ربات، Canonical،
 * محتوای محافظت‌شده) به افزونه Hodima Core منتقل شدند.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * ۱. تشخیص صفحه پنل کاربری
 * ============================================================ */

/**
 * آیا درخواست جاری صفحه پنل کاربری است؟
 *
 * پنل تنها یک نقطه ورود دارد: تمپلیت page-user-panel.php
 * (شورت‌کدی وجود ندارد). اسلاگ هم بررسی می‌شود چون وردپرس همان
 * تمپلیت را برای برگه‌ای با اسلاگ user-panel هم به کار می‌برد.
 */
function hodima_is_user_panel_page(): bool {

    if ( is_admin() ) {
        return false;
    }

    $is_panel = is_page_template( 'page-user-panel.php' ) || is_page( 'user-panel' );

    return (bool) apply_filters( 'hodima_is_user_panel_page', $is_panel );
}

/* ============================================================
 * ۲. وابستگی به ووکامرس
 * ------------------------------------------------------------
 * توابعی مثل is_shop() و wc_get_product() را خود ووکامرس تعریف
 * می‌کند. اگر ووکامرس غیرفعال باشد (آپدیت ناموفق، عیب‌یابی تداخل،
 * حالت Recovery) هر فراخوانی بدون گارد کل سایت را با Fatal Error
 * از کار می‌انداخت. افزونه‌ها قبل از functions.php قالب لود می‌شوند،
 * پس این بررسی از همان ابتدای بارگذاری قالب معتبر است.
 * ============================================================ */
// همین تابع در افزونه Hodima Core هم هست؛ هر کدام زودتر لود شود تعریفش می‌کند.
if ( ! function_exists( 'hodima_wc_active' ) ) {
    function hodima_wc_active(): bool {
        static $active = null;
        return $active ??= class_exists( 'WooCommerce' );
    }
}

/* ============================================================
 * ۴. شورت‌کد افزونه‌ها در قالب
 * ------------------------------------------------------------
 * اگر افزونه صاحب شورت‌کد فعال نباشد، do_shortcode متن خام
 * «[hook_faq ...]» را در صفحه چاپ می‌کند. این تابع در آن حالت رشته
 * خالی برمی‌گرداند تا قالب بتواند بخش مربوط را کلا نمایش ندهد.
 * ============================================================ */
/**
 * @param array<string, scalar> $atts
 */
function hodima_shortcode( string $tag, array $atts = [] ): string {

    if ( ! shortcode_exists( $tag ) ) {
        return '';
    }

    $attr = '';
    foreach ( $atts as $name => $value ) {
        $attr .= sprintf( ' %s="%s"', sanitize_key( (string) $name ), esc_attr( (string) $value ) );
    }

    return trim( (string) do_shortcode( "[{$tag}{$attr}]" ) );
}

/* ============================================================
 * گراف واحد اسکیما — فالبک
 * ------------------------------------------------------------
 * hodima_schema_add() را افزونه Hodima Core تعریف می‌کند: همه JSON-LDهای
 * صفحه (قالب و افزونه‌ها) در یک @graph ادغام و یک بار در فوتر چاپ
 * می‌شوند. افزونه‌ها قبل از قالب لود می‌شوند، پس اگر Hodima Core فعال
 * باشد این تعریف نادیده گرفته می‌شود. بدون آن، هر payload مثل قبل در
 * یک تگ جداگانه چاپ می‌شود تا هیچ اسکیمایی گم نشود.
 * ============================================================ */
if ( ! function_exists( 'hodima_schema_add' ) ) {
    /**
     * @param array<mixed> $payload
     */
    function hodima_schema_add( array $payload, string $source = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- امضای همان تابع Hodima Core
        if ( empty( $payload ) ) {
            return;
        }
        if ( ! isset( $payload['@context'] ) && ! array_is_list( $payload ) ) {
            $payload = [ '@context' => 'https://schema.org' ] + $payload;
        } elseif ( array_is_list( $payload ) ) {
            $payload = [ '@context' => 'https://schema.org', '@graph' => $payload ];
        }
        echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
    }
}
