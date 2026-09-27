<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'hook_ai_box', 'hook_render_shortcode_ai_box' );
function hook_render_shortcode_ai_box( $atts ) {
    if ( is_admin() && ! wp_doing_ajax() ) return ''; 

    $parsed_atts = shortcode_atts( array(
        'title' => '', 
    ), $atts, 'hook_ai_box' );

    list( $object_id, $context ) = hook_get_shortcode_context( $atts );
    if ( ! $object_id || ! $context ) return '';

    // محصول و دسته‌بندی: خاموش (hook_modern_seo_enabled در media-helpers.php)
    if ( function_exists( 'hook_modern_seo_enabled' ) && ! hook_modern_seo_enabled( $context, $object_id ) ) return '';

    $data = hook_get_media_data( $object_id, $context );
    if ( empty( $data['enabled'] ) || $data['enabled'] !== 'yes' ) return '';

    $ai_summary = $data['ai_summary'] ?? '';

    if ( empty( $ai_summary ) ) {
        return '';
    }

    hook_enqueue_media_assets();

    $ai_summary = apply_filters( 'hook_ai_box_summary_text', $ai_summary, $object_id, $context );

    ob_start();
    ?>
    <section class="hodima-ai-summary-wrapper" aria-label="خلاصه مطلب">
        <?php 
        if ( ! empty( $parsed_atts['title'] ) ) : 
        ?>
            <h4 class="hodima-ai-title"><?php echo esc_html( $parsed_atts['title'] ); ?></h4>
        <?php endif; ?>
        
        <div class="hodima-ai-text">
            <?php echo wp_kses_post( $ai_summary ); ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
