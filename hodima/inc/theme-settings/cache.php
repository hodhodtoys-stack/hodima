<?php
/**
 * Theme Settings — باطل‌کردن کش‌های وابسته به تنظیمات و بارگذاری ماژول شبکه‌های اجتماعی پیشخوان
 * Path: hodima/inc/theme-settings/cache.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * ۷. باطل کردن کش‌هایی که از این تنظیمات استفاده می‌کنند
 * ========================================================================= */

add_action( 'update_option_' . HODIMA_SETTINGS_OPTION, static function (): void {

	// llms.txt شماره‌های تماس را در خود دارد
	foreach ( [ 'fa', 'en' ] as $lang ) {
		foreach ( [ 20, 500 ] as $limit ) {
			delete_transient( "hodima_llms_txt_cache_{$lang}_{$limit}_siloed" );
		}
	}

	// هدر و فوتر در همه صفحات کش‌شده تغییر کرده‌اند
	do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API خود لایت‌اسپید
} );

// فهرست پویای شبکه‌های اجتماعی (فقط پیشخوان)
if ( is_admin() ) {
	require_once __DIR__ . '/social-admin.php';
}
