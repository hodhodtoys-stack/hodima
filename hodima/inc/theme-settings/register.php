<?php
/**
 * Theme Settings — ثبت گزینه، منوی پیشخوان، ریدایرکت آدرس قدیمی و hodima_settings_url
 * Path: hodima/inc/theme-settings/register.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * ۲. ثبت تنظیمات و صفحه پیشخوان
 * ========================================================================= */

add_action( 'admin_init', static function (): void {
	register_setting( 'hodima_theme_settings_group', HODIMA_SETTINGS_OPTION, [
		'type'              => 'array',
		'sanitize_callback' => 'hodima_settings_sanitize',
		'default'           => [],
		'show_in_rest'      => false,
	] );
} );

/*
 * منوی سطح اول درست بالای «نمایش» (جایگاه ۶۰؛ ۵۹ جداکننده است). تا 2.9.9 زیرمنوی
 * «نمایش» بود (themes.php?page=hodima-settings)؛ آن آدرس (نشانک‌ها، لینک افزونه‌های
 * قدیمی) با همه پارامترهایش به آدرس تازه می‌رود.
 */
add_action( 'admin_menu', static function (): void {
	add_menu_page(
		'تنظیمات قالب هدیما', // عنوان تب مرورگر
		'قالب هدیما',          // نام منوی پیشخوان
		'manage_options',
		HODIMA_SETTINGS_PAGE,
		'hodima_settings_render_page',
		'dashicons-admin-customizer',
		59.5
	);
} );

add_action( 'admin_init', static function (): void {
	global $pagenow;

	if ( 'themes.php' === $pagenow && HODIMA_SETTINGS_PAGE === ( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط ریدایرکت آدرس قدیمی
		wp_safe_redirect( hodima_settings_url( array_map( 'sanitize_text_field', wp_unslash( array_diff_key( $_GET, [ 'page' => 1 ] ) ) ) ), 301 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- همان پارامترها (tab، settings-updated)
		exit;
	}
} );

/**
 * آدرس صفحه تنظیمات قالب (با پارامتر دلخواه، مثلا [ 'tab' => 'blog' ]).
 *
 * @param array<string, mixed> $args
 */
function hodima_settings_url( array $args = [] ): string {
	return add_query_arg( [ 'page' => HODIMA_SETTINGS_PAGE, ...$args ], admin_url( 'admin.php' ) );
}
