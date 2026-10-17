<?php
/**
 * پاکسازی هسته وردپرس در فرانت‌اند (head، ایموجی، dns-prefetch، heartbeat، lazy آیفریم)
 * Path: hodima-core/includes/wp-cleanup.php
 *
 * از قالب (inc/performance/wp-cleanup.php و content-optimizations.php) منتقل شد — قالب
 * 3.6.5 / Core 1.2.5. این‌ها تصمیم سایت‌اند نه ظاهر: با عوض شدن قالب، لینک‌های
 * wp_generator/RSD/REST در head دوباره برنمی‌گردند و ایموجی/heartbeat دوباره روشن نمی‌شود.
 * نام توابع با پیشوند hodima_core_ است چون قالب قدیمی (≤ 3.6.4) همان کار را با نام
 * hodima_cleanup_wp_core و … بدون گارد دارد و تعریف همنام «Cannot redeclare» می‌داد.
 * تا قالب قدیمی فعال است این فایل کاری نمی‌کند (دوبار اجرا نشود).
 *
 * خاموش کردن همه: add_filter( 'hodima_core_wp_cleanup', '__return_false' );
 * (استایل بلوک‌ها، dashicons و … در قالب می‌مانند: به ظاهر صفحه وابسته‌اند.)
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** آیا این پاکسازی باید اجرا شود؟ (قالب هدیما < 3.6.5 خودش انجام می‌دهد) */
function hodima_core_wp_cleanup_enabled(): bool {

	if ( hodima_theme_has_legacy_logic( '3.6.5' ) ) {
		return false;
	}

	return (bool) apply_filters( 'hodima_core_wp_cleanup', true );
}

/* ============================================================
 * ۱. پاکسازی هدر وردپرس و ایموجی
 * ============================================================ */
add_action( 'init', 'hodima_core_cleanup_wp_head', 1 );

function hodima_core_cleanup_wp_head(): void {

	if ( ! hodima_core_wp_cleanup_enabled() ) {
		return;
	}

	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );

	// لایت‌اسپید گزینه Remove WordPress Emoji دارد؛ اگر روشن باشد این بخش تکراری است.
	if ( hodima_core_litespeed_handles( 'emoji' ) ) {
		return;
	}

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	add_filter( 'tiny_mce_plugins', static fn( mixed $plugins ): array => is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [] );
}

/* ============================================================
 * ۲. Resource Hints
 * ------------------------------------------------------------
 * کل wp_resource_hints حذف نمی‌شود (فیلترهای preconnect دیگران کار کنند)؛
 * فقط dns-prefetch دامنه‌های بی‌مصرف وردپرس (s.w.org) برداشته می‌شود.
 * ============================================================ */
add_filter( 'wp_resource_hints', 'hodima_core_filter_resource_hints', 10, 2 );

/**
 * @param array<int, string|array<string, string>> $urls
 * @return array<int, string|array<string, string>>
 */
function hodima_core_filter_resource_hints( array $urls, string $relation_type ): array {

	if ( 'dns-prefetch' !== $relation_type || ! hodima_core_wp_cleanup_enabled() ) {
		return $urls;
	}

	return array_values( array_filter(
		$urls,
		static function ( mixed $url ): bool {
			$host = (string) ( is_array( $url ) ? ( $url['href'] ?? '' ) : $url );
			return ! str_contains( $host, 's.w.org' ) && ! str_contains( $host, 'wp.org' );
		}
	) );
}

/* ============================================================
 * ۳. Heartbeat در فرانت‌اند
 * ------------------------------------------------------------
 * لایت‌اسپید در Toolbox → Heartbeat کنترل دقیق‌تری دارد (فاصله زمانی)؛ اگر روشن باشد
 * کنار می‌کشیم. در ادمین دست نمی‌زنیم (قفل ویرایش همزمان و ذخیره خودکار).
 * ============================================================ */
add_action( 'wp_enqueue_scripts', 'hodima_core_deregister_heartbeat', 1 );

function hodima_core_deregister_heartbeat(): void {

	if ( ! hodima_core_wp_cleanup_enabled() || hodima_core_litespeed_handles( 'heartbeat' ) ) {
		return;
	}

	wp_deregister_script( 'heartbeat' );
}

/* ============================================================
 * ۴. lazy برای iframe متن مطالب
 * ------------------------------------------------------------
 * هسته وردپرس فقط iframe دارای width و height را lazy می‌کند؛ این فیلتر بقیه را هم.
 * هر iframe جداگانه بررسی می‌شود (باگ قدیمی: یک <img loading="lazy"> در متن باعث می‌شد
 * هیچ iframe lazy نشود). اگر لایت‌اسپید lazy آیفریم دارد، کنار می‌کشیم: اجرای همزمان
 * هر دو بعضی امبدها (پلیر ویدیو) را از کار می‌اندازد.
 * ============================================================ */
add_filter( 'the_content', 'hodima_core_lazyload_content_iframes', 99 );

function hodima_core_lazyload_content_iframes( mixed $content ): mixed {

	if ( ! is_string( $content ) || ! str_contains( $content, '<iframe' ) ) {
		return $content;
	}

	if ( ! hodima_core_wp_cleanup_enabled() || hodima_core_litespeed_handles( 'iframe_lazy' ) ) {
		return $content;
	}

	return (string) preg_replace_callback(
		'/<iframe\b([^>]*)>/i',
		static function ( array $found ): string {

			$attributes = $found[1];

			if ( preg_match( '/\bloading\s*=/i', $attributes ) ) {
				return $found[0];
			}

			return '<iframe loading="lazy"' . $attributes . '>';
		},
		$content
	);
}
