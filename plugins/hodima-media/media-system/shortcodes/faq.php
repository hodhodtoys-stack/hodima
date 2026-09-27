<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'hook_faq', 'hook_render_shortcode_faq' );
function hook_render_shortcode_faq( $atts ) {
    if ( is_admin() && ! wp_doing_ajax() ) return ''; 
    list( $object_id, $context ) = hook_get_shortcode_context( $atts );
    if ( ! $object_id || ! $context ) return ''; 

    $data = hook_get_media_data( $object_id, $context );
    if ( empty( $data['enabled'] ) || $data['enabled'] !== 'yes' || empty( $data['faq'] ) || ! is_array( $data['faq'] ) ) return '';

    hook_enqueue_media_assets();

    $html = '<div class="hook-faq">';
    foreach ( $data['faq'] as $f ) {
        if ( empty( $f['q'] ) ) continue;
        $html .= sprintf(
            '<details class="hook-faq-item"><summary class="hook-faq-summary">%s</summary><div class="hook-faq-content">%s</div></details>',
            esc_html( $f['q'] ), wpautop( wp_kses_post( $f['a'] ?? '' ) )
        );
    }
    $html .= '</div>';
    return $html;
}
