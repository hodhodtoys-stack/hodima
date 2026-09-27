<?php
/**
 * Module: WooCommerce Strict Optimization (Custom Theme)
 * Path: hodima/inc/performance/woo-optimizer.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ۱. جلوگیری از لود فایل‌های سنگین ووکامرس در صفحات غیر مرتبط
add_action( 'wp_enqueue_scripts', function() {
    if ( function_exists( 'is_woocommerce' ) ) {
        if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
            wp_dequeue_style( 'woocommerce-layout' );
            wp_dequeue_style( 'woocommerce-smallscreen' );
            wp_dequeue_style( 'woocommerce-general' );
            wp_dequeue_script( 'wc-add-to-cart' );
            wp_dequeue_script( 'woocommerce' );
            wp_dequeue_script( 'jquery-blockui' );
            wp_dequeue_script( 'wc-cart-fragments' ); 
        }
    }
}, 99 );

// ۲. حذف استایل‌های حجیم بلاک‌های گوتنبرگی ووکامرس
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wc-blocks-style' );
    wp_dequeue_style( 'wc-blocks-integration' );
    wp_dequeue_style( 'wc-blocks-vendors-style' );
}, 100 );

// ۳. پاکسازی تگ‌های متای ووکامرس از هدر سایت
add_action( 'init', fn() => remove_action( 'wp_head', 'wc_generator_tag' ) );

// ۴. (این بخش به schema/ منتقل شد)
// ------------------------------------------------------------------
// غیرفعال‌سازی اسکیمای پیش‌فرض ووکامرس یک تصمیم سئویی است، نه سرعتی.
// فیلتر Product از قبل در schema/product-schema-pro.php بود و اینجا
// تکراری ثبت می‌شد؛ فیلتر BreadcrumbList به schema/breadcrumb-schema.php
// منتقل شد — یعنی کنار همان ماژولی که جایگزینش را تولید می‌کند.
//
// این پوشه از این به بعد هیچ کد سئویی ندارد.

// ۵. غیرفعال کردن منوی مارکتینگ، تبلیغات و امکانات اضافی (PHP 8.4 Optimized)
add_filter( 'woocommerce_marketing_menu_items', '__return_empty_array' );
add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true' );
add_filter( 'woocommerce_admin_features', fn( $features ) => array_values( array_diff( $features, [ 'marketing', 'analytics', 'wc-pay-promotion', 'wc-pay-welcome-page', 'suggestions' ] ) ) );

// ۶. غیرفعال کردن ابزارک سنگین وضعیت داشبورد
add_action( 'wp_dashboard_setup', fn() => remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' ), 99 );

// ۷. متوقف کردن سیستم ثبت ادمین نوت‌ها (جلوگیری از تورم دیتابیس)
add_filter( 'woocommerce_admin_notes_default_sources', '__return_empty_array' );

// ۸. غیرفعال کردن SelectWoo در صفحات غیرضروری
add_action( 'wp_enqueue_scripts', function() {
    if ( function_exists( 'is_checkout' ) && ! is_checkout() && ! is_account_page() ) {
        wp_dequeue_style( 'select2' );
        wp_dequeue_script( 'selectWoo' );
    }
}, 100 );

// ۹. حذف jQuery فقط وقتی هیچ اسکریپتی در صف به آن نیاز ندارد
// ------------------------------------------------------------------
// نسخه قبلی wp_deregister_script('jquery') را بدون قید و شرط اجرا می‌کرد.
// چون وردپرس اسکریپت‌هایی با dependency مفقود را بی‌صدا حذف می‌کند،
// هر اسکریپتی که به jQuery وابسته بود (مثل hodima-taxonomy-js) بدون هیچ
// خطایی از کار می‌افتاد. حالا:
//   ۱. به جای deregister از dequeue استفاده می‌شود، پس اگر اسکریپتی در
//      فوتر به jQuery نیاز داشته باشد، وردپرس خودش آن را برمی‌گرداند.
//   ۲. اگر اسکریپت inline از نوع after به jQuery چسبیده باشد، حذف نمی‌شود.
add_action( 'wp_enqueue_scripts', 'hodima_maybe_dequeue_jquery', PHP_INT_MAX );

function hodima_maybe_dequeue_jquery(): void {

    if ( is_admin() ) {
        return;
    }

    if ( function_exists( 'is_woocommerce' ) ) {
        if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
            return;
        }
    }

    $handles = [ 'jquery', 'jquery-core', 'jquery-migrate' ];
    $scripts = wp_scripts();

    foreach ( $handles as $handle ) {

        if ( ! isset( $scripts->registered[ $handle ] ) ) {
            continue;
        }

        $extra = $scripts->registered[ $handle ]->extra;

        // اسکریپت inline چسبیده به jQuery وجود دارد → دست نزن
        if ( ! empty( $extra['before'] ) || ! empty( $extra['after'] ) ) {
            return;
        }
    }

    foreach ( $handles as $handle ) {
        wp_dequeue_script( $handle );
    }
}

// ۱۰. آزادسازی رم سرور با حذف ابزارک‌های پیش‌فرض ووکامرس
add_action( 'widgets_init', function() {
    $woo_widgets = [
        'WC_Widget_Products', 'WC_Widget_Product_Categories', 
        'WC_Widget_Product_Tag_Cloud', 'WC_Widget_Cart', 
        'WC_Widget_Layered_Nav', 'WC_Widget_Layered_Nav_Filters', 
        'WC_Widget_Price_Filter', 'WC_Widget_Product_Search', 
        'WC_Widget_Top_Rated_Products', 'WC_Widget_Recent_Reviews', 
        'WC_Widget_Recently_Viewed', 'WC_Widget_Rating_Filter'
    ];
    foreach ( $woo_widgets as $widget ) {
        unregister_widget( $widget );
    }
}, 99 );

// ۱۱. جلوگیری از ایجاد سشن (Session) برای ربات‌های موتور جستجو
// ------------------------------------------------------------------
// نسخه قبلی یک کلاس ساختگی مستقل معرفی می‌کرد که از WC_Session ارث‌بری
// نداشت و متدهایی مثل get_customer_id() و forget_session() را نداشت.
// نتیجه: Fatal Error روی ترافیک Googlebot — یعنی دقیقا روی حساس‌ترین
// ترافیک سایت. حالا کلاس از WC_Session_Handler واقعی ارث می‌برد و فقط
// متدهای نوشتن در دیتابیس و کوکی را خنثی می‌کند.
add_filter( 'woocommerce_session_handler', 'hodima_bot_session_handler' );

function hodima_bot_session_handler( $session_class ) {

    if ( ! function_exists( 'hodima_is_search_bot' ) || ! hodima_is_search_bot() ) {
        return $session_class;
    }

    // فقط درخواست‌های خواندنی. هر POST/AJAX مسیر عادی ووکامرس را می‌رود.
    if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' || wp_doing_ajax() ) {
        return $session_class;
    }

    if ( ! class_exists( 'WC_Session_Handler' ) ) {
        return $session_class;
    }

    if ( ! class_exists( 'WC_Session_Handler_Bot_Dummy', false ) ) {

        class WC_Session_Handler_Bot_Dummy extends WC_Session_Handler {

            public function init() {
                $this->_customer_id = 'bot';
                $this->_data        = [];
                $this->_dirty       = false;
            }

            public function get_session_cookie() {
                return false;
            }

            public function set_customer_session_cookie( $set ) {}

            public function has_session() {
                return false;
            }

            public function get_session( $customer_id, $default = false ) {
                return [];
            }

            public function save_data( $old_session_key = 0 ) {}

            public function update_session_timestamp( $customer_id, $timestamp ) {}

            public function destroy_session() {}

            public function forget_session() {}

            public function cleanup_sessions() {}
        }
    }

    return 'WC_Session_Handler_Bot_Dummy';
}
