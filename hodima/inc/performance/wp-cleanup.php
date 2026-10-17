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

    add_filter( 'tiny_mce_plugins', static fn( mixed $plugins ): array => is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [] );
}

/* ============================================================
 * ۲. Resource Hints
 * ------------------------------------------------------------
 * قبلا not-crawl.php با remove_action کل wp_resource_hints را حذف می‌کرد،
 * که باعث می‌شد فیلترهای preconnect در ui-performance.php هرگز اجرا نشوند.
 * حالا به جای حذف کل اکشن، فقط دامنه‌های بی‌مصرف وردپرس فیلتر می‌شوند.
 * ============================================================ */
add_filter( 'wp_resource_hints', 'hodima_filter_core_resource_hints', 10, 2 );

/**
 * @param array<int, string|array<string, string>> $urls
 * @return array<int, string|array<string, string>>
 */
function hodima_filter_core_resource_hints( array $urls, string $relation_type ): array {

    if ( 'dns-prefetch' !== $relation_type ) {
        return $urls;
    }

    return array_values( array_filter(
        $urls,
        static function ( mixed $url ): bool {
            $host = (string) ( is_array( $url ) ? ( $url['href'] ?? '' ) : $url );
            return ! str_contains( $host, 's.w.org' ) && ! str_contains( $host, 'wp.org' );
        }
    ) );
}

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
