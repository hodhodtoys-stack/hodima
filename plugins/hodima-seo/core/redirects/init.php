<?php
/**
 * Component Name: Hodima Redirections System
 * Description: High-performance, PHP 8+ optimized redirect handler.
 */

namespace Hodima\Redirects;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// بارگذاری پردازشگر اصلی
require_once __DIR__ . '/core-redirects.php';

// بارگذاری بخش مدیریت فقط در پیشخوان
if ( is_admin() ) {
    require_once __DIR__ . '/admin-redirects.php';
    
    add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );
}

/**
 * فراخوانی فایل‌های استایل مدیریت
 */
function enqueue_assets( string $hook ): void {
    if ( 'toplevel_page_hodima-redirects' === $hook ) {
        wp_enqueue_style( 
            'hodima-redirects-css', 
            HODIMA_SEO_URL . '/core/redirects/admin-redirects.css', 
            [],
            (string) filemtime( __DIR__ . '/admin-redirects.css' ) // نسخه ثابت، به‌روزرسانی را پنهان می‌کرد
        );
    }
}