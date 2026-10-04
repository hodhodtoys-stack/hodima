<?php
/**
 * Module: WordPress Core Cleanup
 * Path: hodima/inc/performance/wp-cleanup.php
 *
 * تنها مالک پاکسازی هسته وردپرس.
 * بلوک تکراری که قبلا داخل not-crawl.php بود به اینجا منتقل و ادغام شد.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * ۱. پاکسازی جامع هدر وردپرس
 * ============================================================ */
add_action( 'init', 'hodima_cleanup_wp_core', 1 );

function hodima_cleanup_wp_core(): void {

    // لینک‌ها و متاتگ‌های اضافی
    remove_action( 'wp_head', 'wp_generator' );
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    remove_action( 'wp_head', 'feed_links_extra', 3 );
    remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
    remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
    remove_action( 'template_redirect', 'rest_output_link_header', 11 ); // remove_action سه آرگومان دارد (چهارمی اضافه بود)

    // اسکریپت و استایل ایموجی
    // لایت‌اسپید گزینه Remove WordPress Emoji دارد؛ اگر روشن باشد این
    // بخش تکراری است. حذف تکراری ضرری ندارد ولی بی‌مصرف است.
    if ( function_exists( 'hodima_litespeed_handles' ) && hodima_litespeed_handles( 'emoji' ) ) {
        return;
    }

    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

    add_filter( 'tiny_mce_plugins', static fn( $plugins ) => is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [] );
}

/* ============================================================
 * ۲. Resource Hints
 * ------------------------------------------------------------
 * قبلا not-crawl.php با remove_action کل wp_resource_hints را حذف می‌کرد،
 * که باعث می‌شد فیلترهای preconnect در ui-performance.php هرگز اجرا نشوند.
 * حالا به جای حذف کل اکشن، فقط دامنه‌های بی‌مصرف وردپرس فیلتر می‌شوند.
 * ============================================================ */
add_filter( 'wp_resource_hints', 'hodima_filter_core_resource_hints', 10, 2 );

function hodima_filter_core_resource_hints( array $urls, string $relation_type ): array {

    if ( 'dns-prefetch' !== $relation_type ) {
        return $urls;
    }

    return array_values( array_filter(
        $urls,
        static function ( $url ): bool {
            $host = is_array( $url ) ? ( $url['href'] ?? '' ) : (string) $url;
            return ! str_contains( $host, 's.w.org' ) && ! str_contains( $host, 'wp.org' );
        }
    ) );
}

/* ============================================================
 * ۳. حذف استایل‌های حجیم پیش‌فرض
 * ============================================================ */
add_action( 'wp_enqueue_scripts', static function (): void {

    wp_dequeue_style( 'global-styles' );

    /*
     * هشدار: اگر در محتوای نوشته‌ها یا برگه‌ها از بلوک‌های گوتنبرگ
     * (ستون‌ها، گالری، جدول، دکمه) استفاده می‌کنید، حذف این استایل
     * ظاهرشان را می‌شکند. این ربطی به سئو ندارد ولی خطای بصری
     * واقعی است.
     *
     * برای برگرداندن:
     *     add_filter( 'hodima_dequeue_block_library', '__return_false' );
     */
    if ( apply_filters( 'hodima_dequeue_block_library', true ) ) {
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
    }

    // dashicons فقط در فرانت‌اند و فقط برای مهمان‌ها حذف می‌شود.
    // ماژول hodima-table آن را در ادمین enqueue می‌کند و دست‌نخورده می‌ماند.
    if ( ! is_user_logged_in() ) {
        wp_deregister_style( 'dashicons' );
    }
}, 100 );

/* ============================================================
 * ۴. Heartbeat و oEmbed در فرانت‌اند
 * ============================================================ */
add_action( 'wp_enqueue_scripts', static function (): void {

    // لایت‌اسپید در Toolbox → Heartbeat کنترل دقیق‌تری دارد: به جای حذف
    // کامل، فاصله زمانی را تنظیم می‌کند. حذف کامل باعث می‌شود قفل ویرایش
    // همزمان پست و ذخیره خودکار در ادمین از کار بیفتد.
    if ( function_exists( 'hodima_litespeed_handles' ) && hodima_litespeed_handles( 'heartbeat' ) ) {
        return;
    }

    if ( ! is_admin() ) {
        wp_deregister_script( 'heartbeat' );
    }
}, 1 );

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
