<?php
/**
 * [hook_voice id="" context="" heading="h2|h3|…|none"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'hook_voice', 'hook_render_shortcode_voice' );

function hook_render_shortcode_voice( $atts ) {

	if ( is_admin() && ! wp_doing_ajax() ) {
		return '';
	}

	[ $object_id, $context ] = hook_get_shortcode_context( $atts );

	if ( ! $object_id ) {
		return '';
	}

	$data = hook_get_media_data( $object_id, $context );

	if ( 'yes' !== ( $data['enabled'] ?? '' ) || empty( $data['voice_url'] ) ) {
		return '';
	}

	hook_enqueue_media_assets();

	$url   = esc_url_raw( (string) $data['voice_url'] );
	$url   = ( is_ssl() && str_starts_with( $url, 'http://' ) ) ? set_url_scheme( $url, 'https' ) : $url;
	$title = (string) ( $data['voice_title'] ?? '' );
	$atts  = (array) $atts;

	/*
	 * title: متن نمایشی جایگزین (مثلا فقط «پادکست»). عنوان کامل ذخیره‌شده
	 * همچنان برچسب دسترس‌پذیری پلیر (aria-label) می‌ماند.
	 * layout="inline": برچسب *داخل* کادر پلیر، بدون سرتیتر جدا —
	 * فشرده، بدون فضای خالی بالا و پایین.
	 */
	$label  = isset( $atts['title'] ) && '' !== trim( (string) $atts['title'] ) ? sanitize_text_field( (string) $atts['title'] ) : $title;
	$inline = 'inline' === ( $atts['layout'] ?? '' );
	$path  = (string) wp_parse_url( $url, PHP_URL_PATH );

	if ( preg_match( '/\.(mp3|wav|ogg|oga|m4a|aac|opus)$/i', $path ) ) {

		$mime  = wp_check_filetype( $path, wp_get_mime_types() )['type'] ?: 'audio/mpeg';
		$media = sprintf(
			'<audio class="hook-audio-el" controls preload="none" aria-label="%1$s"><source src="%2$s" type="%3$s"><p>مرورگر شما از پخش صوت پشتیبانی نمی‌کند. <a href="%2$s">دانلود فایل</a></p></audio>',
			esc_attr( '' !== $title ? $title : 'پادکست' ),
			esc_url( $url ),
			esc_attr( $mime )
		);

	} else {

		$embed = hook_get_cached_oembed( $url, [ 'width' => 600, 'height' => 150 ] );
		$media = '' !== $embed
			? hook_prepare_embed_iframe( $embed, $title )
			: sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer" class="button">پخش پادکست</a>', esc_url( $url ) );
	}

	if ( $inline ) {
		return sprintf(
			'<div class="hook-voice-wrapper hook-voice-wrapper--inline"><div class="hook-voice-container">%s%s</div></div>',
			'' !== $label ? '<span class="hook-voice-label">' . esc_html( $label ) . '</span>' : '',
			$media
		);
	}

	return sprintf(
		'<div class="hook-voice-wrapper">%s<div class="hook-voice-container">%s</div></div>',
		hook_media_heading( $label, $atts['heading'] ?? 'h2' ),
		$media
	);
}
