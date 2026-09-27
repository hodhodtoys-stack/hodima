<?php
/**
 * SeoBox — bootstrap
 * Path: core/seobox/seobox-init.php
 * Version: 4.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOBOX_DIR', get_template_directory() . '/core/seobox/' );
define( 'SEOBOX_URL', get_template_directory_uri() . '/core/seobox/' );
define( 'SEOBOX_VERSION', '4.0.0' );

require_once SEOBOX_DIR . 'core-variables.php';
require_once SEOBOX_DIR . 'robots-txt.php';

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

		wp_enqueue_style( 'seobox-admin-style', SEOBOX_URL . 'admin-style.css', [], SEOBOX_VERSION );

		if ( $is_post_editor || $is_term_editor ) {
			/*
			 * بدون وابستگی. نسخه قبلی ['wp-data', 'wp-editor'] را می‌خواست که
			 * اسکریپت اصلا از آن‌ها استفاده نمی‌کرد، ولی روی صفحه ویرایش
			 * دسته‌بندی و محصول (ویرایشگر کلاسیک) کل بسته ویرایشگر بلوکی را
			 * بارگذاری می‌کرد.
			 */
			wp_enqueue_script( 'seobox-admin-script', SEOBOX_URL . 'admin-script.js', [], SEOBOX_VERSION, true );
		}
	} );

} else {
	require_once SEOBOX_DIR . 'front-output.php';
}
