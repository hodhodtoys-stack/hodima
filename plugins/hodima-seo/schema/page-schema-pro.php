<?php
/**
 * HOOK PAGE SCHEMA PRO
 * Path: /wp-content/themes/hodima/schema/page-schema-pro.php
 * Status: Display Only (UI Only)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Page Schema Pro Hook
 * فعلاً این بخش فقط برای آماده‌سازی ساختار نگهداری شده
 * و هیچ اسکیما یا خروجی‌ای در فرانت تولید نمی‌کند.
 */
// هوک wp_head برداشته شد: کالبک فقط return می‌کرد، پس روی هر بارگذاری
// صفحه یک فراخوانی بی‌مصرف اجرا می‌شد. خود تابع نگه داشته شده تا وقتی
// پیاده‌سازی واقعی آماده شد، فقط همین یک خط برگردد.
// add_action( 'wp_head', 'hook_render_page_schema_pro_dev', 99 );

function hook_render_page_schema_pro_dev() {
    // Display only mode:
    // فعلاً هیچ خروجی یا اسکیما در فرانت‌اند چاپ نمی‌شود.
    return;
}
