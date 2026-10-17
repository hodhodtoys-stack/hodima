<?php
/**
 * Module: WordPress Core Cleanup
 * Path: hodima/inc/performance/wp-cleanup.php
 *
 * استایل‌های حجیم وردپرس که به ظاهر صفحه وابسته‌اند (بلوک‌ها، global-styles، dashicons).
 * پاکسازی head/ایموجی/dns-prefetch/heartbeat/lazy آیفریم از 3.6.5 در Hodima Core است.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * ۱ و ۲. پاکسازی هدر/ایموجی و dns-prefetch → افزونه Hodima Core
 *    (includes/wp-cleanup.php) — قالب 3.6.5. تصمیم سایت است نه ظاهر.
 * ============================================================ */

/* ============================================================
 * ۳. حذف استایل‌های حجیم پیش‌فرض
 * ============================================================ */
/**
 * محتوای صفحه تکی (مقاله، برگه، محصول) بلوک گوتنبرگ دارد؟
 *
 * فقط برای تصمیم «استایل بلوک‌ها در head لازم است یا نه»؛ بلوکی که اینجا دیده نشود
 * (ابزارک، ...) را وردپرس خودش هنگام رندر در فوتر لود می‌کند، یعنی بی‌استایل نمی‌ماند.
 */
function hodima_queried_content_has_blocks(): bool {

    if ( ! is_singular() ) {
        return false;
    }

    $post = get_queried_object();

    return $post instanceof WP_Post && has_blocks( $post );
}

add_action( 'wp_enqueue_scripts', static function (): void {

    /*
     * استایل بلوک‌ها (wp-block-library) و global-styles فقط برای صفحه‌ای که بلوک دارد.
     *
     * باگ قبلی (3.5.5 و پیش‌تر): هر دو برای همه صفحه‌ها در head برداشته می‌شدند، ولی
     * وردپرس هنگام رندر محتوای بلوکی دوباره آن‌ها را اضافه می‌کند و چون محتوا بعد از
     * head رندر می‌شود، <link> و <style> بعد از </footer> چاپ می‌شدند: ستون‌ها، جدول،
     * گالری و دکمه‌های بلوکی ابتدا بی‌استایل نمایش داده می‌شدند و بعد می‌پریدند
     * (CLS). حالا صفحه‌ای که بلوک دارد آن‌ها را در head می‌گیرد (آخرِ صف، پایین‌تر
     * ببینید) و صفحه بدون بلوک مثل قبل هیچ‌کدام را نمی‌گیرد.
     *
     * برای همیشه نگه داشتن (در همه صفحه‌ها):
     *     add_filter( 'hodima_dequeue_block_library', '__return_false' );
     */
    $has_blocks = hodima_queried_content_has_blocks();

    if ( ! $has_blocks ) {
        wp_dequeue_style( 'global-styles' );
    }

    if ( apply_filters( 'hodima_dequeue_block_library', ! $has_blocks ) ) {
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
    }

    /*
     * dashicons فقط در فرانت‌اند و فقط برای مهمان‌ها از صف برداشته می‌شود.
     * dequeue نه deregister (نوسازی قالب، مرحله ۸): با deregister هر استایل
     * دیگری که dashicons را وابستگی دارد (CSS یک افزونه) بی‌صدا اصلا چاپ
     * نمی‌شد؛ حالا dashicons فقط وقتی لود می‌شود که استایلی به آن وابسته باشد.
     * ماژول hodima-table آن را در ادمین enqueue می‌کند و دست‌نخورده می‌ماند.
     */
    if ( ! is_user_logged_in() ) {
        wp_dequeue_style( 'dashicons' );
    }
}, 100 );

/**
 * استایل بلوک‌ها آخرِ صف CSS (بعد از CSS قالب و افزونه‌ها).
 *
 * تا 3.5.5 این دو در فوتر و بعد از همه CSSها چاپ می‌شدند، پس در برابر قانون‌های
 * هم‌وزن قالب برنده بودند (دکمه، جدول، تصویر…). برای اینکه ظاهر بلوک‌ها با انتقال به
 * head هیچ تغییری نکند همان ترتیب آبشار را نگه می‌داریم: اگر در جای عادی (اولویت ۱۰)
 * می‌ماندند، قانون‌های قالب برنده می‌شدند و ظاهر عوض می‌شد.
 */
add_action( 'wp_enqueue_scripts', static function (): void {

    if ( ! hodima_queried_content_has_blocks() ) {
        return;
    }

    // ترتیب فوتر قبلی: global-styles، بعد wp-block-library
    foreach ( [ 'global-styles', 'wp-block-library', 'wp-block-library-theme' ] as $handle ) {
        if ( wp_style_is( $handle, 'enqueued' ) ) {
            wp_dequeue_style( $handle );
            wp_enqueue_style( $handle );
        }
    }
}, PHP_INT_MAX );

/* ============================================================
 * ۴. Heartbeat فرانت‌اند → Hodima Core (includes/wp-cleanup.php)، قالب 3.6.5
 * ============================================================ */

/*
 * deregister «wp-embed» در فوتر حذف شد: از وردپرس 5.9 این اسکریپت دیگر در همه
 * صفحه‌ها چاپ نمی‌شود و فقط وقتی متن یک نوشته وردپرسی دیگر را embed کرده باشد
 * لود می‌شود — همان‌جا لازم است (اندازه iframe و پیام امن بین دو سایت).
 */

/* ============================================================
 * ۵. XML-RPC و Pingback → افزونه Hodima Core (includes/hardening.php)
 * ------------------------------------------------------------
 * بستن XML-RPC تصمیم امنیتی سایت است و با عوض شدن قالب نباید بی‌صدا دوباره
 * باز شود — بازسازی قالب، مرحله ۲.
 * ============================================================ */
