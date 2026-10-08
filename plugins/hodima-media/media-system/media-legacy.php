<?php
/**
 * Media System — نام‌های قدیمی (سازگاری)
 * Path: media-system/media-legacy.php
 *
 * تا نسخه ۳ توابع سیستم رسانه پیشوند عمومی «hook_» داشتند و قالب، افزونه
 * سئو و شاید کد سفارشی سایت آن‌ها را صدا می‌زنند. هر نام فقط اگر تعریف
 * نشده باشد ساخته می‌شود و به تابع جدید (hodima_media_*) ارجاع می‌دهد؛
 * پس افزونه دیگری با تابعی همنام دیگر خطای «Cannot redeclare» نمی‌دهد.
 * کد جدید همیشه نام‌های hodima_media_* را صدا بزند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'HOOK_MEDIA_VERSION' ) ) {
	define( 'HOOK_MEDIA_VERSION', HODIMA_MEDIA_VERSION );
}

/**
 * نام قدیمی ← نام جدید. امضا و خروجی همان است؛ فقط نوع ورودی‌ها
 * انعطاف‌پذیرتر (کد قدیمی رشته یا عدد می‌فرستاد).
 */
if ( ! function_exists( 'hook_media_meta_keys' ) ) {
	function hook_media_meta_keys(): array {
		return hodima_media_meta_keys();
	}
}

if ( ! function_exists( 'hook_get_supported_post_types' ) ) {
	function hook_get_supported_post_types(): array {
		return hodima_media_post_types();
	}
}

if ( ! function_exists( 'hook_get_supported_taxonomies' ) ) {
	function hook_get_supported_taxonomies(): array {
		return hodima_media_taxonomies();
	}
}

if ( ! function_exists( 'hook_normalize_digits' ) ) {
	function hook_normalize_digits( $value ): string {
		return hodima_media_normalize_digits( (string) $value );
	}
}

if ( ! function_exists( 'hook_parse_key_entities' ) ) {
	function hook_parse_key_entities( $raw ): array {
		return hodima_media_parse_entities( $raw );
	}
}

if ( ! function_exists( 'hook_get_media_data' ) ) {
	function hook_get_media_data( $object_id, $context = 'post', $force = false ): array {
		return hodima_media_get_data( (int) $object_id, (string) $context, (bool) $force );
	}
}

if ( ! function_exists( 'hook_media_page_url' ) ) {
	function hook_media_page_url( $object_id, $context ): string {
		return hodima_media_page_url( (int) $object_id, hodima_media_context( (string) $context ) );
	}
}

if ( ! function_exists( 'hook_is_direct_video_file' ) ) {
	function hook_is_direct_video_file( $url ): bool {
		return hodima_media_is_direct_video( $url );
	}
}

if ( ! function_exists( 'hook_format_duration_iso' ) ) {
	function hook_format_duration_iso( $duration ): string {
		return hodima_media_duration_iso( $duration );
	}
}

if ( ! function_exists( 'hook_duration_to_iso' ) ) {
	function hook_duration_to_iso( $duration ): string {
		return hodima_media_duration_iso( $duration );
	}
}

if ( ! function_exists( 'hook_normalize_iso_date' ) ) {
	function hook_normalize_iso_date( $date ): string {
		return hodima_media_iso_date( $date );
	}
}

if ( ! function_exists( 'hook_modern_seo_enabled' ) ) {
	function hook_modern_seo_enabled( $context, $object_id = 0 ): bool {
		return hodima_media_discover_enabled( (string) $context, is_numeric( $object_id ) ? (int) $object_id : (string) $object_id );
	}
}

if ( ! function_exists( 'hook_media_stable_date' ) ) {
	function hook_media_stable_date( $data, $kind, $object_id, $context ): string {
		return hodima_media_stable_date( (array) $data, (string) $kind, (int) $object_id, hodima_media_context( (string) $context ) );
	}
}

if ( ! function_exists( 'hook_print_schema' ) ) {
	function hook_print_schema( $type, $data, $object_id, $context ): void {
		hodima_media_print_schema( (string) $type, (array) $data, (int) $object_id, (string) $context );
	}
}

if ( ! function_exists( 'hook_enqueue_media_assets' ) ) {
	function hook_enqueue_media_assets(): void {
		hodima_media_enqueue_assets();
	}
}

if ( ! function_exists( 'hook_get_shortcode_context' ) ) {
	function hook_get_shortcode_context( $atts ): array {
		return hodima_media_shortcode_context( $atts );
	}
}

/*
 * [hook_ai_box]: بخش «خلاصه هوش مصنوعی» حذف شده است. ثبت خالی فقط تا وقتی
 * می‌ماند که شورت‌کد از متن نوشته‌های سایت پاک شود (دستور SQL در
 * HODIMA-AUDIT.md بخش «پاک‌سازی AI»)؛ بدون آن، متن خام «[hook_ai_box]» در
 * صفحه دیده می‌شد. بعد از پاک‌سازی دیتابیس این دو خط حذف شود.
 */
add_shortcode( 'hook_ai_box', '__return_empty_string' );
