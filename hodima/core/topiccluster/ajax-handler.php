<?php
/**
 * Topic Cluster — Parent Search (admin AJAX)
 * Path: core/topiccluster/ajax-handler.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_hodima_tc_search', 'hodima_tc_ajax_search_pillars' );

function hodima_tc_ajax_search_pillars(): void {

	check_ajax_referer( 'hodima_tc_secure_nonce', 'nonce' );

	// nonce فقط ثابت می‌کند درخواست از سایت آمده، نه اینکه کاربر مجاز است.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'دسترسی غیرمجاز.', 403 );
	}

	$search  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$context = isset( $_GET['context'] ) ? sanitize_key( wp_unslash( $_GET['context'] ) ) : 'post';
	$type    = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'post';
	$exclude = isset( $_GET['exclude'] ) ? absint( $_GET['exclude'] ) : 0;

	$results = [];

	if ( 'post' === $context ) {

		/*
		 * نوع پست به فهرست مجاز محدود شد. نسخه قبلی هر مقداری را که
		 * کاربر می‌فرستاد مستقیم به WP_Query می‌داد، پس با تغییر پارامتر
		 * type می‌شد عناوین هر نوع پستی — از جمله انواع داخلی افزونه‌ها —
		 * را فهرست کرد.
		 */
		if ( ! in_array( $type, Hodima_TC_Helper::post_types(), true ) ) {
			wp_send_json_success( [] );
		}

		$args = [
			'post_type'              => $type,
			'post_status'            => 'publish',
			'posts_per_page'         => 50,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		if ( $exclude ) {
			$args['post__not_in'] = [ $exclude ];
		}

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		foreach ( ( new WP_Query( $args ) )->posts as $post ) {
			$results[] = [
				'value' => (string) $post->ID,
				'text'  => wp_strip_all_tags( get_the_title( $post ) ),
			];
		}

	} elseif ( 'term' === $context ) {

		if ( ! in_array( $type, Hodima_TC_Helper::taxonomies(), true ) || ! taxonomy_exists( $type ) ) {
			wp_send_json_success( [] );
		}

		$args = [
			'taxonomy'               => $type,
			'hide_empty'             => false,
			'number'                 => 50,
			'update_term_meta_cache' => false,
		];

		if ( $exclude ) {
			$args['exclude'] = [ $exclude ];
		}

		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$terms = get_terms( $args );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$results[] = [
					'value' => (string) $term->term_id,
					'text'  => $term->name,
				];
			}
		}
	}

	wp_send_json_success( $results );
}
