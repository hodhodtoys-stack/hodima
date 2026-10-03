<?php
/**
 * [hook_intro id="" context=""] — «متن معرفی» سیستم رسانه.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_shortcode( 'hook_intro', 'hodima_media_shortcode_intro' );

function hodima_media_shortcode_intro( mixed $atts ): string {

	if ( is_admin() && ! wp_doing_ajax() ) {
		return '';
	}

	[ $object_id, $context ] = hodima_media_shortcode_context( $atts );

	if ( ! $object_id ) {
		return '';
	}

	$data    = hodima_media_get_data( $object_id, $context );
	$content = (string) ( $data['content'] ?? '' );

	if ( 'yes' !== ( $data['enabled'] ?? '' ) || '' === trim( $content ) ) {
		return '';
	}

	hodima_media_enqueue_assets();

	// shortcode_unautop: شورت‌کد در خط خودش داخل <p> پیچیده نشود (div داخل p نامعتبر است)
	return sprintf( '<div class="hook-intro-wrapper">%s</div>', do_shortcode( shortcode_unautop( wpautop( $content ) ) ) );
}
