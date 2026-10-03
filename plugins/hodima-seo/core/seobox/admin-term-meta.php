<?php
/**
 * SeoBox — term fields
 * Path: core/seobox/admin-term-meta.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * اولویت ۲۰ بعد از ثبت تکسونومی‌های ووکامرس (ویژگی‌ها در init).
 *
 * کادر از ردیف جدول فرم ({taxonomy}_edit_form_fields، بدون کادر و با
 * استایل‌های .form-field وردپرس روی ورودی‌ها) به یک postbox عنوان‌دار بعد
 * از جدول ({taxonomy}_edit_form، داخل همان فرم) رفت — همان الگوی
 * «خوشه‌بندی» (اولویت ۸) و «لینک‌های مرتبط» (۹)؛ سئو اولشان.
 */
add_action( 'init', static function (): void {
	foreach ( seobox_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_edit_form", 'seobox_render_term_ui', 7, 2 );
		add_action( "edited_{$taxonomy}", 'seobox_save_term_meta', 10, 1 );
	}
}, 20 );

function seobox_render_term_ui( mixed $term, string $taxonomy = '' ): void {

	if ( ! $term instanceof WP_Term ) {
		return;
	}
	?>
	<div class="postbox seobox-postbox">
		<div class="postbox-header"><h2 class="hndle">سئو (SeoBox)</h2></div>
		<div class="inside"><?php seobox_render_html( seobox_editor_data( (int) $term->term_id, 'term' ) ); ?></div>
	</div>
	<?php
}

function seobox_save_term_meta( int $term_id ): void {

	$nonce = isset( $_POST['seobox_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['seobox_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'seobox_save_action' ) ) {
		return;
	}

	/*
	 * تکسونومی از خود ترم خوانده می‌شود، نه از $_POST['taxonomy'].
	 * نسخه قدیمی قابلیت را روی تکسونومی‌ای بررسی می‌کرد که کاربر فرستاده بود.
	 */
	$term = get_term( $term_id );

	if ( ! ( $term instanceof WP_Term ) || ! in_array( $term->taxonomy, seobox_taxonomies(), true ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}

	seobox_save_fields( $term_id, 'term' );
}
