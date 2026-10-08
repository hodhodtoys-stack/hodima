<?php
/**
 * [hook_faq id="" context=""]
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_shortcode( 'hook_faq', 'hodima_media_shortcode_faq' );

function hodima_media_shortcode_faq( mixed $atts ): string {

	if ( is_admin() && ! wp_doing_ajax() ) {
		return '';
	}

	[ $object_id, $context ] = hodima_media_shortcode_context( $atts );

	if ( ! $object_id ) {
		return '';
	}

	$data = hodima_media_get_data( $object_id, $context );

	if ( 'yes' !== ( $data['enabled'] ?? '' ) || ! $data['faq'] ) {
		return '';
	}

	$items = '';

	foreach ( $data['faq'] as $item ) {
		if ( ! is_array( $item ) || empty( $item['q'] ) ) {
			continue;
		}
		$items .= sprintf(
			'<details class="hook-faq-item"><summary class="hook-faq-summary">%s</summary><div class="hook-faq-content">%s</div></details>',
			esc_html( (string) $item['q'] ),
			wpautop( wp_kses_post( (string) ( $item['a'] ?? '' ) ) )
		);
	}

	if ( '' === $items ) {
		return '';
	}

	hodima_media_enqueue_assets();
	hodima_media_schema_on_render( 'faq', $object_id, $context, $data );

	return '<div class="hook-faq">' . $items . '</div>';
}
