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

// ۵ تا ۷. پاکسازی پیشخوان ووکامرس (منوی بازاریابی، پیشنهادها، ابزارک وضعیت،
// یادداشت‌های پیشخوان) → افزونه Hodima Commerce، ماژول «سبک‌سازی ووکامرس»
// (inc/woocommerce/store-optimizer.php) — بازسازی قالب، مرحله ۲.

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

// ۱۰ و ۱۱. حذف ابزارک‌های قدیمی ووکامرس و سشن خنثی برای ربات‌های موتور جستجو
// → افزونه Hodima Commerce، ماژول «سبک‌سازی ووکامرس» (store-optimizer.php).
// این فایل فقط بهینه‌سازی‌های نمایشی (CSS/JS ووکامرس در صفحه‌ها) را دارد.
