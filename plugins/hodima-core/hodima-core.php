<?php
/**
 * Plugin Name:       Hodima Core
 * Plugin URI:        https://hodima.com
 * Description:       کتابخانه مشترک افزونه‌های هدیما: تشخیص IP واقعی (سازگار با Cloudflare)، محدودیت نرخ، تشخیص ربات موتور جستجو، موتور Canonical و بررسی محتوای محافظت‌شده. پیش‌نیاز Hodima SEO، Hodima Commerce و Hodima Media.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Author:            آرین فتحی
 * Author URI:        https://hodima.com
 * License:           GPL-2.0-or-later
 * Text Domain:       hodima-core
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// پیشوند HODIMA_CORE_PLUGIN_ چون HODIMA_CORE_* قبلا در ماژول IndexNow (افزونه SEO) استفاده شده است.
const HODIMA_CORE_PLUGIN_VERSION = '1.0.0';
define( 'HODIMA_CORE_PLUGIN_FILE', __FILE__ );
define( 'HODIMA_CORE_PLUGIN_DIR', __DIR__ );

// بلافاصله لود می‌شود (نه در plugins_loaded) تا توابع برای افزونه‌های دیگر و
// قالب، مستقل از ترتیب الفبایی بارگذاری افزونه‌ها، در دسترس باشند.
require_once HODIMA_CORE_PLUGIN_DIR . '/includes/helpers.php';

/**
 * آیا نسخه ۱ قالب هدیما (که همین ماژول‌ها را داخل خودش دارد) فعال است؟
 *
 * افزونه‌ها قبل از قالب لود می‌شوند. اگر قالب قدیمی فعال باشد، functions.php
 * آن همان توابع را دوباره تعریف می‌کند و کل سایت با خطای «Cannot redeclare»
 * از کار می‌افتد. افزونه‌های هدیما در این حالت ماژول‌هایشان را لود نمی‌کنند
 * تا قالب به نسخه ۲ به‌روز شود.
 */
function hodima_legacy_theme_active(): bool {
	static $legacy = null;

	if ( null === $legacy ) {
		$theme  = wp_get_theme( get_template() );
		$legacy = 'Hodima' === $theme->get( 'Name' ) && version_compare( (string) $theme->get( 'Version' ), '2.0.0', '<' );
	}

	return $legacy;
}

/** اعلان پیشخوان برای افزونه‌ای که به‌خاطر قالب قدیمی متوقف شده است. */
function hodima_legacy_theme_notice( string $plugin_name ): void {
	add_action( 'admin_notices', static function () use ( $plugin_name ): void {
		if ( current_user_can( 'switch_themes' ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%1$s:</strong> %2$s</p></div>',
				esc_html( $plugin_name ),
				esc_html( 'قالب هدیما نسخه ۱ فعال است و همین امکانات را داخل خودش دارد؛ برای جلوگیری از خطای سایت، این افزونه تا به‌روزرسانی قالب به نسخه ۲ غیرفعال مانده است.' )
			);
		}
	} );
}
