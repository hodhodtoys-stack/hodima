<?php
/**
 * Plugin Name:       Hodima Media
 * Plugin URI:        https://hodima.com
 * Description:       رسانه و محتوای تعاملی هدیما: استوری، ویدیو با شمارش بازدید، اسلایدر، سیستم رسانه (ویدیو، پادکست، FAQ، خلاصه AI)، اعلان‌ها، باکس‌های بازشونده و جدول داینامیک.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Requires Plugins:  hodima-core
 * Author:            آرین فتحی
 * Author URI:        https://hodima.com
 * License:           GPL-2.0-or-later
 * Text Domain:       hodima-media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_MEDIA_VERSION = '1.0.0';
define( 'HODIMA_MEDIA_FILE', __FILE__ );
define( 'HODIMA_MEDIA_DIR', __DIR__ );
define( 'HODIMA_MEDIA_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

add_action( 'plugins_loaded', static function (): void {

	if ( function_exists( 'hodima_legacy_theme_active' ) && hodima_legacy_theme_active() ) {
		hodima_legacy_theme_notice( 'Hodima Media' );
		return;
	}

	foreach ( [
		'components/expandable-boxes/expandable-boxes.php',
		'components/hodima-slider/hodima-slider.php',
		'components/hodima-Stories/hodima-Stories.php',
		'components/notification/notification-cpt.php',
		'components/notification/notification-metabox.php',
		'components/notification/notification-front.php',
		'components/video-watch/video-watch.php',
		'media-system/media-init.php',
		'inc/hodima-table/hodima-table.php',
	] as $module ) {
		require_once HODIMA_MEDIA_DIR . '/' . $module;
	}
}, 5 );

/* =========================================================================
 * پست‌تایپ «ویدیو» (منتقل‌شده از functions.php قالب)
 * ========================================================================= */

// زیرنویس ویدیو (WebVTT)
add_filter( 'upload_mimes', static function ( array $mimes ): array {
	$mimes['vtt'] = 'text/vtt';
	return $mimes;
} );

// فهرست ویدیوها یک «برگه» است؛ آرشیو پیش‌فرض با نامک آن برگه تداخل داشت و ۴۰۴ می‌داد
add_filter( 'register_post_type_args', static function ( array $args, string $post_type ): array {
	if ( 'video' === $post_type ) {
		$args['public']      = true;
		$args['has_archive'] = false;
	}
	return $args;
}, 99, 2 );

register_activation_hook( __FILE__, static fn() => delete_option( 'rewrite_rules' ) );
register_deactivation_hook( __FILE__, static fn() => delete_option( 'rewrite_rules' ) );
