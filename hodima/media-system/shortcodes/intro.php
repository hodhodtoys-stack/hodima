<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'hook_intro', 'hook_render_shortcode_intro' );
function hook_render_shortcode_intro( $atts ) {
    if ( is_admin() && ! wp_doing_ajax() ) return ''; 
    
    list( $object_id, $context ) = hook_get_shortcode_context( $atts );
    
    if ( ! $object_id || ! $context ) return '';

    $data = hook_get_media_data( $object_id, $context );
    
    // hook_get_media_data() کلیدها را بدون پیشوند برمی‌گرداند، پس
    // فالبک‌های _hook_ و hook_ هیچ‌وقت اجرا نمی‌شدند و حذف شدند.
    $is_enabled = $data['enabled'] ?? '';
    $content    = $data['content'] ?? '';

    if ( $is_enabled !== 'yes' || empty( $content ) ) return '';

    if ( function_exists( 'hook_enqueue_media_assets' ) ) {
        hook_enqueue_media_assets();
    }
    
    // shortcode_unautop: بدون آن شورت‌کدی که در خط خودش نوشته شده داخل
    // <p> پیچیده می‌شد و خروجی بلوکی آن (div) داخل پاراگراف نامعتبر می‌افتاد.
    return sprintf( '<div class="hook-intro-wrapper">%s</div>', do_shortcode( shortcode_unautop( wpautop( $content ) ) ) );
}
