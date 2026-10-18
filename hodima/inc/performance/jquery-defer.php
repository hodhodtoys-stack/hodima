<?php
/**
 * Module: jQuery بدون مسدود کردن رندر (defer)
 * Path: hodima/inc/performance/jquery-defer.php
 *
 * جای فایل: جاوااسکریپتی که فقط ووکامرس لازم دارد.
 *
 * چرا: jquery.min.js در head بدون defer چاپ می‌شود و تا دانلود و اجرایش (گزارش PageSpeed صفحه
 * محصول: ۲۹٫۷KiB، ۶۰۰ms) پارسر HTML می‌ایستد و نمایش صفحه (و پیدا کردن تصویر LCP) عقب می‌افتد.
 * اسکریپت‌های ووکامرس همه defer دارند و به jQuery وابسته‌اند؛ پس jQuery هم defer شود،
 * وردپرس (۶٫۳+) ترتیب اجرا را با وابستگی‌ها درست نگه می‌دارد، و اگر اسکریپتی بدون defer
 * به jQuery وابسته باشد خودش jQuery را مسدودکننده می‌گذارد (قانون خود وردپرس).
 *
 * فقط برای بازدیدکننده مهمان در صفحه‌های عمومی: سبد، پرداخت و حساب کاربری (کدهای درون‌خطی
 * ووکامرس)، کاربر واردشده (نوار مدیریت، سفارشی‌ساز) و پیش‌نمایش دست‌نخورده می‌مانند.
 * اگر کدی (افزونه دیگر) jQuery را درون‌خطی و بدون وابستگی ثبت‌شده صدا بزند، با این کار می‌شکند:
 *     add_filter( 'hodima_defer_jquery', '__return_false' );
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', static function (): void {

    if ( is_admin() || is_user_logged_in() || is_customize_preview() || is_preview() ) {
        return;
    }

    if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
        return;
    }

    if ( ! (bool) apply_filters( 'hodima_defer_jquery', true ) ) {
        return;
    }

    foreach ( [ 'jquery-core', 'jquery-migrate' ] as $handle ) {
        if ( wp_script_is( $handle, 'registered' ) ) {
            wp_script_add_data( $handle, 'strategy', 'defer' );
        }
    }
}, 101 );
