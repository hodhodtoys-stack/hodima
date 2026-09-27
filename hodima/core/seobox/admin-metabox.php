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
			add_meta_box( 'seobox_meta_box', 'دستیار هوشمند سئو (SeoBox)', 'seobox_render_post_ui', $screen, 'normal', 'high' );
		}
	}
} );

function seobox_render_post_ui( WP_Post $post ): void {

	$meta = static fn( string $key ): string => (string) get_post_meta( $post->ID, '_seobox_' . $key, true );

	seobox_render_html( [
		'title'       => $meta( 'title' ),
		'description' => $meta( 'description' ),
		'canonical'   => $meta( 'canonical' ),
		'robots'      => seobox_normalize_robots( get_post_meta( $post->ID, '_seobox_robots', true ) ),
		'adv_snippet' => '' !== $meta( 'adv_snippet' ) ? $meta( 'adv_snippet' ) : '-1',
		'adv_video'   => '' !== $meta( 'adv_video' ) ? $meta( 'adv_video' ) : '-1',
		'adv_image'   => '' !== $meta( 'adv_image' ) ? $meta( 'adv_image' ) : 'large',
		'current_url' => (string) get_permalink( $post->ID ),
		'fallback'    => seobox_fallback_description_for_post( $post ),
	] );
}

add_action( 'save_post', static function ( int $post_id, WP_Post $post ): void {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// بازنگری‌ها شناسه مستقل دارند؛ نسخه قبلی متای سئو را روی آن‌ها هم می‌نوشت
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! in_array( $post->post_type, seobox_post_types(), true ) ) {
		return;
	}

	$nonce = isset( $_POST['seobox_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['seobox_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'seobox_save_action' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	seobox_save_fields( $post_id, 'post' );
}, 10, 2 );

/**
 * متن جایگزین توضیحات برای نمایش به عنوان placeholder در پنل —
 * همان متنی که در نبود توضیحات دستی روی سایت چاپ می‌شود.
 */
function seobox_fallback_description_for_post( WP_Post $post ): string {

	$text = has_excerpt( $post ) ? (string) $post->post_excerpt : (string) $post->post_content;
	$text = wp_strip_all_tags( strip_shortcodes( $text ) );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

	return mb_substr( $text, 0, 155 );
}
