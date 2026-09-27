<?php
/**
 * SeoBox — term fields
 * Path: core/seobox/admin-term-meta.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', static function (): void {
	foreach ( seobox_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_edit_form_fields", 'seobox_render_term_ui', 10, 2 );
		add_action( "edited_{$taxonomy}", 'seobox_save_term_meta', 10, 1 );
	}
} );

function seobox_render_term_ui( WP_Term $term, string $taxonomy ): void {

	$term_id = (int) $term->term_id;
	$link    = get_term_link( $term );
	$meta    = static fn( string $key ): string => (string) get_term_meta( $term_id, '_seobox_' . $key, true );

	$fallback = wp_strip_all_tags( strip_shortcodes( (string) $term->description ) );
	$fallback = mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', $fallback ) ), 0, 155 );

	echo '<tr class="form-field seobox-term-row"><td colspan="2">';
	echo '<h3 class="seobox-term-title">تنظیمات سئو (SeoBox)</h3>';

	seobox_render_html( [
		'title'       => $meta( 'title' ),
		'description' => $meta( 'description' ),
		'canonical'   => $meta( 'canonical' ),
		'robots'      => seobox_normalize_robots( get_term_meta( $term_id, '_seobox_robots', true ) ),
		'adv_snippet' => '' !== $meta( 'adv_snippet' ) ? $meta( 'adv_snippet' ) : '-1',
		'adv_video'   => '' !== $meta( 'adv_video' ) ? $meta( 'adv_video' ) : '-1',
		'adv_image'   => '' !== $meta( 'adv_image' ) ? $meta( 'adv_image' ) : 'large',
		'current_url' => is_wp_error( $link ) ? '' : (string) $link,
		'fallback'    => $fallback,
	] );

	echo '</td></tr>';
}

function seobox_save_term_meta( int $term_id ): void {

	$nonce = isset( $_POST['seobox_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['seobox_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'seobox_save_action' ) ) {
		return;
	}

	/*
	 * تکسونومی از خود ترم خوانده می‌شود، نه از $_POST['taxonomy'].
	 * نسخه قبلی قابلیت را روی تکسونومی‌ای بررسی می‌کرد که کاربر فرستاده بود،
	 * و اگر آن فیلد در درخواست نبود به قابلیت ثابت manage_categories برمی‌گشت.
	 */
	$term = get_term( $term_id );

	if ( ! ( $term instanceof WP_Term ) || ! in_array( $term->taxonomy, seobox_taxonomies(), true ) ) {
		return;
	}

	$taxonomy = get_taxonomy( $term->taxonomy );

	if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
		return;
	}

	seobox_save_fields( $term_id, 'term' );
}
