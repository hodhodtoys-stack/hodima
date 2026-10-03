<?php
/**
 * Hodima Router — نام‌های قدیمی (Arian Clean Router) فقط برای سازگاری
 * Path: core/router/legacy.php
 *
 * کد جدید این‌ها را صدا نزند؛ معادل hodima_router_* را به کار ببرد.
 * با function_exists گارد شده‌اند: نسخه‌های قدیمی قالب همین نام‌ها را داشتند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'arian_strip_leading_base' ) ) {
	function arian_strip_leading_base( $link, $base ) {
		return hodima_router_strip_base( (string) $link, (string) $base );
	}
}

if ( ! function_exists( 'arian_category_base' ) ) {
	function arian_category_base() {
		return hodima_router_bases()['category'];
	}
}

if ( ! function_exists( 'arian_product_cat_base' ) ) {
	function arian_product_cat_base() {
		return hodima_router_bases()['product_cat'];
	}
}

if ( ! function_exists( 'arian_product_base' ) ) {
	function arian_product_base() {
		return hodima_router_bases()['product'];
	}
}

if ( ! function_exists( 'arian_get_request_path' ) ) {
	function arian_get_request_path() {
		return hodima_router_request_path();
	}
}

if ( ! function_exists( 'arian_resolve_path' ) ) {
	/** همان خروجی قبلی: متغیرهای کوئری، یا آرایه خالی. */
	function arian_resolve_path( $path, array $segments = [] ) {
		$match = hodima_router_match( implode( '/', $segments ?: explode( '/', (string) $path ) ) );
		return null !== $match && isset( $match['vars'] ) ? $match['vars'] : [];
	}
}
