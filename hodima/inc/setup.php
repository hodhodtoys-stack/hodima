<?php
/**
 * Theme setup: قابلیت‌های قالب و مکان منوها
 * Path: hodima/inc/setup.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** شناسه مکان منوی اصلی هدر. */
const HODIMA_MENU_PRIMARY = 'primary';

add_action( 'after_setup_theme', 'hodima_theme_setup' );

function hodima_theme_setup(): void {

    // ترجمه رشته‌های قالب (پوشه languages)
    load_theme_textdomain( 'hodima', get_template_directory() . '/languages' );

    // عنوان صفحه را وردپرس (و افزونه Hodima SEO) می‌سازد
    add_theme_support( 'title-tag' );

    add_theme_support( 'post-thumbnails' );

    // خروجی HTML5 معتبر برای فرم‌ها، گالری‌ها و تگ‌های style/script
    add_theme_support( 'html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
        'navigation-widgets',
    ] );

    // لوگوی وردپرس؛ پشتیبان لوگوی «تنظیمات هدیما» (hodima_logo_html)
    add_theme_support( 'custom-logo', [
        'height'      => 110,
        'width'       => 320,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    // ویدیو و iframe‌های جاسازی‌شده متناسب با عرض صفحه
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );

    add_theme_support( 'woocommerce' );

    /*
     * wc-product-gallery-zoom/lightbox/slider ثبت نمی‌شوند: گالری اختصاصی قالب
     * (inc/woocommerce/product-hooks.php) جایگزین آن‌هاست. قبلا اینجا ثبت و
     * همان‌جا دوباره حذف می‌شدند.
     */

    register_nav_menus( [
        HODIMA_MENU_PRIMARY => __( 'منوی اصلی (هدر)', 'hodima' ),
    ] );
}

/**
 * انتقال یک‌باره منوی قبلی به مکان جدید.
 *
 * قبلا مکان منو با کلید فارسی «منوی اصلی» ثبت می‌شد و هدر منو را با *نام*
 * صدا می‌زد، نه با *مکان*. اگر نام منو در پیشخوان عوض می‌شد، هدر خالی
 * می‌ماند. حالا هدر از مکان «primary» می‌خواند؛ این تابع منوی فعلی سایت
 * را یک‌بار به آن وصل می‌کند تا بعد از به‌روزرسانی چیزی تغییر نکند.
 */
add_action( 'after_setup_theme', 'hodima_migrate_primary_menu', 20 );

function hodima_migrate_primary_menu(): void {

    $locations = get_nav_menu_locations();

    if ( ! empty( $locations[ HODIMA_MENU_PRIMARY ] ) ) {
        return;
    }

    // ۱. منویی که به مکان قدیمی (کلید فارسی) وصل بود
    $menu_id = (int) ( $locations['منوی اصلی'] ?? 0 );

    // ۲. منویی که هدر با نام صدا می‌زد
    if ( ! $menu_id ) {
        $menu    = wp_get_nav_menu_object( 'منوی اصلی' );
        $menu_id = $menu ? (int) $menu->term_id : 0;
    }

    if ( ! $menu_id ) {
        return;
    }

    unset( $locations['منوی اصلی'] );
    $locations[ HODIMA_MENU_PRIMARY ] = $menu_id;
    set_theme_mod( 'nav_menu_locations', $locations );
}
