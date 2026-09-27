<?php
/**
 * Media System — Shortcodes loader
 * Path: media-system/media-shortcodes.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'hook_register_media_assets' );

function hook_register_media_assets(): void {

	$url = HODIMA_MEDIA_URL . '/media-system';

	// نسخه از زمان تغییر فایل؛ نسخه ثابت «1.0.1» به‌روزرسانی را از مرورگر
	// و کش لایت‌اسپید پنهان می‌کرد.
	wp_register_style( 'hook-media-css', $url . '/css/media-style.css', [], hook_media_asset_version( 'css/media-style.css' ) );
	wp_register_script( 'hook-media-js', $url . '/js/media-style.js', [], hook_media_asset_version( 'js/media-style.js' ), true );

	/*
	 * بارگذاری زودهنگام در head برای صفحه‌ای که سیستم رسانه‌اش فعال است.
	 * بدون این، استایل فقط هنگام اجرای شورت‌کد (وسط body) صف می‌شد و در
	 * فوتر چاپ می‌شد؛ بلوک ویدیو و FAQ اول بدون استایل نمایش داده و بعد
	 * جابه‌جا می‌شدند (FOUC و CLS).
	 */
	$queried = get_queried_object();
	$id      = 0;
	$context = '';

	if ( $queried instanceof WP_Post ) {
		$id      = (int) $queried->ID;
		$context = 'post';
	} elseif ( $queried instanceof WP_Term ) {
		$id      = (int) $queried->term_id;
		$context = 'term';
	}

	if ( $id && 'yes' === ( hook_get_media_data( $id, $context )['enabled'] ?? '' ) ) {
		hook_enqueue_media_assets();
	}
}

/** فالبک: شورت‌کد روی صفحه‌ای که در head تشخیص داده نشده بود. */
function hook_enqueue_media_assets(): void {
	wp_enqueue_style( 'hook-media-css' );
	wp_enqueue_script( 'hook-media-js' );
}

/** تشخیص شیء و context شورت‌کد. */
function hook_get_shortcode_context( $atts ): array {

	$atts      = shortcode_atts( [ 'id' => 0, 'context' => '' ], (array) $atts );
	$object_id = absint( $atts['id'] );
	$context   = sanitize_key( (string) $atts['context'] );

	if ( ! $object_id ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post ) {
			$object_id = (int) $queried->ID;
			$context   = 'post';
		} elseif ( $queried instanceof WP_Term ) {
			$object_id = (int) $queried->term_id;
			$context   = 'term';
		}
	}

	if ( $object_id && ! in_array( $context, [ 'post', 'term' ], true ) ) {
		$context = 'post';
	}

	// بلوک رسانه نوشته رمزدار یا منتشرنشده نباید بدون رمز/دسترسی نمایش
	// داده شود؛ شناسه دلخواه در شورت‌کد هم نباید آن را دور بزند.
	if ( $object_id && 'post' === $context && function_exists( 'hodima_post_content_is_visible' )
		&& ! hodima_post_content_is_visible( $object_id ) ) {
		return [ 0, '' ];
	}

	return [ $object_id, $context ];
}

/** سطح عنوان بلوک رسانه (پیش‌فرض h2؛ «none» یعنی بدون عنوان). */
function hook_media_heading( string $text, $level = 'h2' ): string {

	if ( '' === $text ) {
		return '';
	}

	$level = strtolower( (string) $level );

	if ( 'none' === $level ) {
		return '';
	}

	$tag = in_array( $level, [ 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ], true ) ? $level : 'h2';

	return sprintf( '<%1$s class="hook-media-title">%2$s</%1$s>', $tag, esc_html( $text ) );
}

/** کد جاسازی با کش (wp_oembed_get در هر بازدید درخواست HTTP می‌زند). */
function hook_get_cached_oembed( string $url, array $args = [] ): string {

	$cache_key = 'hook_oembed_' . md5( $url . wp_json_encode( $args ) );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return (string) $cached;
	}

	$embed = wp_oembed_get( $url, $args );
	$embed = is_string( $embed ) ? $embed : '';

	set_transient( $cache_key, $embed, '' === $embed ? HOUR_IN_SECONDS : WEEK_IN_SECONDS );

	return $embed;
}

$hook_shortcodes_dir = __DIR__ . '/shortcodes/';

require_once $hook_shortcodes_dir . 'video.php';
require_once $hook_shortcodes_dir . 'voice.php';
require_once $hook_shortcodes_dir . 'faq.php';
require_once $hook_shortcodes_dir . 'intro.php';
require_once $hook_shortcodes_dir . 'ai-box.php';
