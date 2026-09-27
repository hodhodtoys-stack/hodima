<?php
/**
 * Arian Clean Router - Rewrite rules & shared helpers
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function arian_category_base() {
    $b = (string) get_option( 'category_base' );
    $b = trim( $b, '/' );
    return $b !== '' ? $b : 'category';
}

function arian_product_cat_base() {
    $perms = get_option( 'woocommerce_permalinks' );
    if ( is_array( $perms ) && ! empty( $perms['category_base'] ) ) {
        return trim( $perms['category_base'], '/' );
    }
    return 'product-category';
}

function arian_product_base() {
    $perms = get_option( 'woocommerce_permalinks' );
    if ( is_array( $perms ) && ! empty( $perms['product_base'] ) ) {
        return trim( $perms['product_base'], '/' );
    }
    return 'product';
}

function arian_router_cache_generation(): int {
    $gen = get_option( 'arian_router_cache_gen', 1 );
    return is_numeric( $gen ) ? (int) $gen : 1;
}

function arian_router_bump_cache_generation(): void {
    update_option( 'arian_router_cache_gen', arian_router_cache_generation() + 1, false );
}

add_action( 'post_updated', function ( $post_id, $post_after, $post_before ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    if ( $post_after->post_name !== $post_before->post_name || $post_after->post_status !== $post_before->post_status ) {
        arian_router_bump_cache_generation();
    }
}, 10, 3 );

add_action( 'deleted_post', 'arian_router_bump_cache_generation' );
add_action( 'created_term', 'arian_router_bump_cache_generation' );
add_action( 'edited_term', 'arian_router_bump_cache_generation' );
add_action( 'delete_term', 'arian_router_bump_cache_generation' );