<?php
/**
 * ماژول «گوگل دیسکاور» — نام‌های قدیمی (سازگاری)
 * Path: core/discover/legacy.php
 *
 * تا Hodima Media 1.4.0 این توابع در سیستم رسانه بودند و کد سفارشی سایت
 * ممکن است آن‌ها را صدا بزند. فقط وقتی لود می‌شود که Media قدیمی خودش
 * دیسکاور را ندارد (discover-init.php)، و هر نام فقط اگر تعریف نشده باشد.
 * کد جدید همیشه نام‌های hodima_seo_discover_* را صدا بزند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hodima_media_discover_enabled' ) ) {
	function hodima_media_discover_enabled( string $context, int|string $object_id = 0 ): bool {
		return hodima_seo_discover_enabled( $context, $object_id );
	}
}

if ( ! function_exists( 'hodima_media_discover_for_post' ) ) {
	function hodima_media_discover_for_post( int $post_id ): bool {
		return hodima_seo_discover_for_post( $post_id );
	}
}

if ( ! function_exists( 'hodima_media_discover_image' ) ) {
	/** @return array{id: int, url: string, width: int, height: int, mime: string, alt: string}|null */
	function hodima_media_discover_image( int $post_id ): ?array {
		return hodima_seo_discover_image( $post_id );
	}
}

if ( ! function_exists( 'hodima_media_discover_images' ) ) {
	/** @return list<array{url: string, width: int, height: int, ratio: string}> */
	function hodima_media_discover_images( int $post_id ): array {
		return hodima_seo_discover_images( $post_id );
	}
}

if ( ! function_exists( 'hodima_media_discover_checks' ) ) {
	/** @return list<array{key: string, status: string, label: string, detail: string}> */
	function hodima_media_discover_checks( WP_Post $post ): array {
		return hodima_seo_discover_checks( $post );
	}
}

if ( ! function_exists( 'hodima_media_is_clickbait' ) ) {
	function hodima_media_is_clickbait( string $title ): bool {
		return hodima_seo_discover_is_clickbait( $title );
	}
}

if ( ! function_exists( 'hook_modern_seo_enabled' ) ) {
	function hook_modern_seo_enabled( mixed $context, mixed $object_id = 0 ): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- نام قدیمی برای کد سفارشی سایت
		return hodima_seo_discover_enabled( is_scalar( $context ) ? (string) $context : '', is_numeric( $object_id ) ? (int) $object_id : ( is_scalar( $object_id ) ? (string) $object_id : 0 ) );
	}
}
