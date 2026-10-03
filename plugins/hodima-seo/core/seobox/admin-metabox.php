<?php
/**
 * SeoBox — post / page / product meta box
 * Path: core/seobox/admin-metabox.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', static function (): void {
	foreach ( seobox_post_types() as $screen ) {
		if ( post_type_exists( $screen ) ) {
			add_meta_box( 'seobox_meta_box', 'سئو (SeoBox)', 'seobox_render_post_ui', $screen, 'normal', 'high' );
		}
	}
} );

function seobox_render_post_ui( WP_Post $post ): void {
	seobox_render_html( seobox_editor_data( (int) $post->ID, 'post' ) );
}

add_action( 'save_post', static function ( int $post_id, WP_Post $post ): void {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// بازنگری‌ها شناسه مستقل دارند؛ نسخه قدیمی متای سئو را روی آن‌ها هم می‌نوشت
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! in_array( $post->post_type, seobox_post_types(), true ) ) {
		return;
	}

	$nonce = isset( $_POST['seobox_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['seobox_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'seobox_save_action' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	seobox_save_fields( $post_id, 'post' );
}, 10, 2 );

/*
 * «تکثیر» محصول ووکامرس همه متاها را کپی می‌کرد: نسخه تازه همان عنوان و
 * توضیحات سئو (محتوای تکراری) و همان canonical سفارشی را می‌گرفت.
 * ربات‌ها و تنظیمات پیش‌نمایش (سیاست، نه محتوا) کپی می‌شوند.
 */
add_filter( 'woocommerce_duplicate_product_exclude_meta', static function ( mixed $exclude ): array {
	return [ ...(array) $exclude, '_seobox_title', '_seobox_description', '_seobox_canonical' ];
} );
