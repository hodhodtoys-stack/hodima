<?php
/**
 * Header Module – Enqueue styles & scripts
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hodima_header_assets(): void {
    // CSS هدر با CSS مشترک همه صفحه‌ها (hodima_enqueue_common_css در inc/enqueue.php)
    hodima_enqueue_asset( 'hodima-header', 'assets/js/header.js', [], [ 'in_footer' => true, 'strategy' => 'defer' ] );
}
add_action( 'wp_enqueue_scripts', 'hodima_header_assets', 20 );

/**
 * دکمه باز/بسته‌کردن زیرمنو کنار هر آیتم «منوی اصلی» که زیرمنو دارد (موبایل؛ دسکتاپ با CSS پنهان است).
 *
 * قبلا کلیک روی خود لینک والد زیرمنو را باز می‌کرد و همیشه از رفتن به صفحه‌اش جلوگیری می‌شد:
 * صفحه والد (مثلا «فروشگاه») در موبایل قابل دیدن نبود و صفحه‌خوان/کیبورد دکمه‌ای با
 * aria-expanded نداشتند (3.5.8). حالا لینک مثل هر لینکی می‌رود و دکمه جدا زیرمنو را باز می‌کند.
 *
 * @param mixed $item_output HTML آیتم (لینک).
 * @param mixed $item        آیتم منو.
 * @param mixed $depth       عمق آیتم.
 * @param mixed $args        آرگومان‌های wp_nav_menu.
 */
function hodima_menu_submenu_toggle( mixed $item_output, mixed $item, mixed $depth, mixed $args ): mixed {

    if ( ! is_string( $item_output ) || ! is_object( $item ) || ! is_object( $args ) || HODIMA_MENU_PRIMARY !== ( $args->theme_location ?? '' ) ) {
        return $item_output;
    }

    // classes و title خصوصیت‌های پویای آیتم منو هستند (WP_Post آن‌ها را تعریف نمی‌کند)
    $data = get_object_vars( $item );

    if ( ! in_array( 'menu-item-has-children', (array) ( $data['classes'] ?? [] ), true ) ) {
        return $item_output;
    }

    return $item_output . sprintf(
        '<button type="button" class="submenu-toggle" aria-expanded="false" aria-label="%s"></button>',
        esc_attr( sprintf( 'زیرمنوی «%s»', wp_strip_all_tags( (string) ( $data['title'] ?? '' ) ) ) )
    );
}
add_filter( 'walker_nav_menu_start_el', 'hodima_menu_submenu_toggle', 10, 4 );
