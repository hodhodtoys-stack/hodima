<?php
/**
 * [hook_voice id="" context="" heading="h2|h3|…|none" title="" layout="inline"]
 *
 *   title:  متن نمایشی جایگزین (مثلا فقط «پادکست»)؛ عنوان کامل ذخیره‌شده
 *           برچسب دسترس‌پذیری پلیر (aria-label) می‌ماند.
 *   layout: «inline» = برچسب داخل کادر پلیر، بدون سرتیتر جدا.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_shortcode( 'hook_voice', 'hodima_media_shortcode_voice' );

function hodima_media_shortcode_voice( mixed $atts ): string {

	if ( is_admin() && ! wp_doing_ajax() ) {
		return '';
	}

	[ $object_id, $context ] = hodima_media_shortcode_context( $atts );
	$atts                    = is_array( $atts ) ? $atts : [];

	if ( ! $object_id ) {
		return '';
	}

	$data = hodima_media_get_data( $object_id, $context );

	if ( 'yes' !== ( $data['enabled'] ?? '' ) || empty( $data['voice_url'] ) ) {
		return '';
	}

	hodima_media_enqueue_assets();
	hodima_media_schema_on_render( 'audio', $object_id, $context, $data );

	$url    = esc_url_raw( (string) preg_replace( '/\s+/', '%20', trim( (string) $data['voice_url'] ) ) );
	$url    = ( is_ssl() && str_starts_with( $url, 'http://' ) ) ? set_url_scheme( $url, 'https' ) : $url;
	$title  = sanitize_text_field( (string) ( $data['voice_title'] ?? '' ) );
	$label  = '' !== trim( (string) ( $atts['title'] ?? '' ) ) ? sanitize_text_field( (string) $atts['title'] ) : $title;
	$inline = 'inline' === ( $atts['layout'] ?? '' );
	$path   = (string) wp_parse_url( $url, PHP_URL_PATH );

	if ( hodima_media_is_direct_audio( $url ) ) {

		$mime  = wp_check_filetype( $path, wp_get_mime_types() )['type'] ?: 'audio/mpeg';
		$media = sprintf(
			'<audio class="hook-audio-el" controls preload="none" aria-label="%1$s"><source src="%2$s" type="%3$s"><p>مرورگر شما از پخش صوت پشتیبانی نمی‌کند. <a href="%2$s">دانلود فایل</a></p></audio>',
			esc_attr( '' !== $title ? $title : 'پادکست' ),
			esc_url( $url ),
			esc_attr( (string) $mime )
		);

	} else {

		$embed = hodima_media_cached_oembed( $url, [ 'width' => 600, 'height' => 150 ] );
		$media = '' !== $embed
			? hodima_media_prepare_iframe( $embed, $title )
			: sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer" class="button">پخش پادکست</a>', esc_url( $url ) );
	}

	$transcript = hodima_media_transcript_html( (string) ( $data['voice_transcript'] ?? '' ), 'متن کامل پادکست' );

	if ( $inline ) {
		return sprintf(
			'<div class="hook-voice-wrapper hook-voice-wrapper--inline"><div class="hook-voice-container">%s%s</div>%s</div>',
			'' !== $label ? '<span class="hook-voice-label">' . esc_html( $label ) . '</span>' : '',
			$media,
			$transcript
		);
	}

	return sprintf(
		'<div class="hook-voice-wrapper">%s<div class="hook-voice-container">%s</div>%s</div>',
		hodima_media_heading( $label, $atts['heading'] ?? 'h2' ),
		$media,
		$transcript
	);
}
