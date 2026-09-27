<?php
/**
 * Topic Cluster — Bootstrap
 * Path: core/topiccluster/topiccluster-init.php
 * Version: 3.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TOPICCLUSTER_DIR', get_template_directory() . '/core/topiccluster/' );
define( 'TOPICCLUSTER_URL', get_template_directory_uri() . '/core/topiccluster/' );
define( 'TOPICCLUSTER_VERSION', '3.0.0' );

require_once TOPICCLUSTER_DIR . 'helper.php';
require_once TOPICCLUSTER_DIR . 'ajax-handler.php';
require_once TOPICCLUSTER_DIR . 'admin-metabox.php';
require_once TOPICCLUSTER_DIR . 'admin-term.php';
require_once TOPICCLUSTER_DIR . 'admin-orphan.php';
require_once TOPICCLUSTER_DIR . 'admin-visual-map.php';
require_once TOPICCLUSTER_DIR . 'front-output.php';
require_once TOPICCLUSTER_DIR . 'front-schema.php';

/**
 * دارایی‌های پیشخوان — فقط صفحاتی که واقعا از آن‌ها استفاده می‌کنند.
 *
 * نسخه قبلی روی *هر* صفحه پیشخوان (فهرست نوشته‌ها، تنظیمات، افزونه‌ها،
 * همه‌جا) CSS و JS این ماژول را لود می‌کرد و یک nonce می‌ساخت.
 */
add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$is_editor = ( 'post' === $screen->base && in_array( $screen->post_type, Hodima_TC_Helper::post_types(), true ) )
		|| ( in_array( $screen->base, [ 'term', 'edit-tags' ], true ) && in_array( $screen->taxonomy, Hodima_TC_Helper::taxonomies(), true ) );

	$is_module_page = str_contains( $hook, 'hodima-tc-' );
	$is_dashboard   = ( 'dashboard' === $screen->base );

	if ( ! $is_editor && ! $is_module_page && ! $is_dashboard ) {
		return;
	}

	wp_enqueue_style( 'hodima-tc-admin', TOPICCLUSTER_URL . 'assets/admin.css', [], TOPICCLUSTER_VERSION );

	// اسکریپت انتخابگر فقط در ویرایشگرها لازم است
	if ( $is_editor ) {
		wp_enqueue_script( 'hodima-tc-admin-js', TOPICCLUSTER_URL . 'assets/admin.js', [ 'jquery' ], TOPICCLUSTER_VERSION, true );

		wp_localize_script( 'hodima-tc-admin-js', 'hodimaTcConfig', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hodima_tc_secure_nonce' ),
		] );
	}
} );

/**
 * استایل فرانت فقط ثبت می‌شود و هنگام رندر واقعی باکس enqueue می‌شود.
 * نسخه قبلی روی همه نوشته‌ها، برگه‌ها، محصولات و دسته‌بندی‌ها لود
 * می‌شد، حتی آن‌هایی که هیچ خوشه‌ای نداشتند.
 */
add_action( 'wp_enqueue_scripts', static function (): void {
	wp_register_style( 'hodima-tc-front', TOPICCLUSTER_URL . 'assets/style.css', [], TOPICCLUSTER_VERSION );
} );
