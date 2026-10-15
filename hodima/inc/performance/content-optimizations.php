<?php
/**
 * Module: Content Optimizations (Lazyload Iframes)
 * Path: hodima/inc/performance/content-optimizations.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * ۱. اضافه کردن lazy به iframe
 * ------------------------------------------------------------
 * باگ نسخه قبلی: شرط !str_contains($content, 'loading=') کل محتوا را
 * بررسی می‌کرد، بنابراین وجود یک <img loading="lazy"> در متن باعث
 * می‌شد هیچ iframe ای lazy نشود. حالا هر iframe جداگانه بررسی می‌شود.
 * ============================================================ */
add_filter( 'the_content', 'hodima_lazyload_content_iframes', 99 );

function hodima_lazyload_content_iframes( mixed $content ): mixed {

    // لایت‌اسپید خودش lazy load آیفریم دارد و src را با data-src جایگزین
    // می‌کند. اجرای همزمان هر دو مکانیزم روی یک آیفریم، بعضی امبدها
    // (به‌ویژه پلیرهای ویدیو) را از کار می‌اندازد.
    if ( function_exists( 'hodima_litespeed_handles' ) && hodima_litespeed_handles( 'iframe_lazy' ) ) {
        return $content;
    }

    if ( ! is_string( $content ) || ! str_contains( $content, '<iframe' ) ) {
        return $content;
    }

    return (string) preg_replace_callback(
        '/<iframe\b([^>]*)>/i',
        static function ( array $found ): string {

            $attributes = $found[1];

            if ( preg_match( '/\bloading\s*=/i', $attributes ) ) {
                return $found[0];
            }

            return '<iframe loading="lazy"' . $attributes . '>';
        },
        $content
    );
}

/* ============================================================
 * Defer کردن جاوااسکریپت: فیلتر دستی script_loader_tag حذف شد (قالب 3.5.7)
 * ------------------------------------------------------------
 * رشته «src=» را در تگ همه اسکریپت‌ها به «defer src=» عوض می‌کرد و فقط اسکریپتی را
 * که خودش inline بعد از خودش (after) داشت کنار می‌گذاشت، نه وابستگی‌های آن را. اگر
 * اسکریپت A (بدون inline) در head و B (وابسته به A، با inline بعد از خودش) در فوتر
 * بود، A «defer» می‌شد و B فوری اجرا می‌شد، یعنی B و inline آن پیش از A اجرا
 * می‌شدند و می‌شکستند (بازتولید با ابزار تست، بخش ۱۵۵ HODIMA-AUDIT.md).
 *
 * از وردپرس 6.3 همین کار بومی است (wp_enqueue_script با 'strategy' => 'defer')
 * و وردپرس خودش وابستگی‌ها، وابسته‌ها و اسکریپت inline را می‌سنجد و اسکریپت ناایمن
 * را بدون defer می‌گذارد. حداقل وردپرس قالب 6.5 است. اسکریپت‌های خود قالب همه
 * 'strategy' => 'defer' بومی دارند؛ اسکریپت افزونه‌ها هر کدام خودشان تصمیم می‌گیرند.
 * ============================================================ */
