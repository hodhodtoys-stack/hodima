<?php
/**
 * SeoBox — bootstrap
 * Path: core/seobox/seobox-init.php
 * Version: 5.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'SEOBOX_DIR' ) || define( 'SEOBOX_DIR', HODIMA_SEO_DIR . '/core/seobox/' );
defined( 'SEOBOX_URL' ) || define( 'SEOBOX_URL', HODIMA_SEO_URL . '/core/seobox/' );
defined( 'SEOBOX_VERSION' ) || define( 'SEOBOX_VERSION', '5.0.0' );

require_once SEOBOX_DIR . 'core-variables.php';
// robots-txt.php ماژول جداگانه «robots.txt» در افزونه SEO است

if ( is_admin() ) {

	require_once SEOBOX_DIR . 'admin-ui.php';
	require_once SEOBOX_DIR . 'admin-metabox.php';
	require_once SEOBOX_DIR . 'admin-term-meta.php';
	require_once SEOBOX_DIR . 'admin-filters.php';

	add_action( 'admin_enqueue_scripts', static function ( string $hook_suffix ): void {

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_post_editor = in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) && in_array( $screen->post_type, seobox_post_types(), true );
		$is_term_editor = 'term.php' === $hook_suffix && in_array( $screen->taxonomy, seobox_taxonomies(), true );

		// فهرست‌ها فقط CSS نشان «Index / Noindex» ستون را لازم دارند
		$is_list = ( 'edit.php' === $hook_suffix && in_array( $screen->post_type, seobox_post_types(), true ) )
			|| ( 'edit-tags.php' === $hook_suffix && in_array( $screen->taxonomy, seobox_taxonomies(), true ) );

		if ( ! $is_post_editor && ! $is_term_editor && ! $is_list ) {
			return;
		}

		/*
		 * نسخه فایل‌ها = نسخه افزونه. قبلا ثابت «4.0.0» بود و با تغییر CSS
		 * (مثلا بازطراحی پیشخوان) مرورگر و کش لایت‌اسپید نسخه قدیمی را نگه
		 * می‌داشتند.
		 */
		$version = HODIMA_SEO_VERSION;

		wp_enqueue_style( 'seobox-admin-style', SEOBOX_URL . 'admin-style.css', [ 'dashicons' ], $version );

		if ( $is_post_editor || $is_term_editor ) {
			// بدون وابستگی: wp-data فقط اگر ویرایشگر بلوکی خودش لود کرده باشد استفاده می‌شود
			wp_enqueue_script( 'seobox-admin-script', SEOBOX_URL . 'admin-script.js', [], $version, [ 'in_footer' => true, 'strategy' => 'defer' ] );
		}
	} );

} else {
	require_once SEOBOX_DIR . 'front-output.php';
}
