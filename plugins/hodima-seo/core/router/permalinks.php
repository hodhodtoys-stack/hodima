<?php
/**
 * Hodima Router — ساخت لینک‌های بدون پایه
 * Path: core/router/permalinks.php
 *
 * محصول: /نامک/ به جای /product/نامک/
 * دسته محصول و دسته نوشته: بدون /product-category/ و /category/
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_filter( 'post_type_link', 'hodima_router_product_link', 10, 3 );

/**
 * لینک محصول. برای پیش‌نویس، در انتظار و زمان‌بندی‌شده لینک پیش‌فرض می‌ماند تا
 * پیش‌نمایش نشکند. با $leavename (پیوند نمونه پیشخوان) نشانگر %product% برمی‌گردد
 * تا دکمه «ویرایش» نامک زیر عنوان محصول کار کند؛ نسخه قبلی نامک ثابت می‌داد.
 *
 * @param string  $permalink
 * @param WP_Post $post
 * @param bool    $leavename
 */
function hodima_router_product_link( $permalink, $post, $leavename = false ) {

	if ( ! ( $post instanceof WP_Post ) || 'product' !== $post->post_type || ! hodima_router_active() ) {
		return $permalink;
	}

	if ( in_array( $post->post_status, [ 'draft', 'pending', 'auto-draft', 'future' ], true ) || '' === $post->post_name ) {
		return $permalink;
	}

	return home_url( user_trailingslashit( $leavename ? '%product%' : $post->post_name ) );
}

add_filter( 'term_link', 'hodima_router_term_link', 10, 3 );

/**
 * @param string  $termlink
 * @param WP_Term $term
 * @param string  $taxonomy
 */
function hodima_router_term_link( $termlink, $term, $taxonomy ) {

	if ( ! hodima_router_active() ) {
		return $termlink;
	}

	$bases = hodima_router_bases();

	return match ( (string) $taxonomy ) {
		'category'    => hodima_router_strip_base( (string) $termlink, $bases['category'] ),
		'product_cat' => hodima_router_strip_base( (string) $termlink, $bases['product_cat'] ),
		default       => $termlink,
	};
}
