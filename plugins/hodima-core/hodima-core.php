<?php
/**
 * Plugin Name:       Hodima Core
 * Plugin URI:        https://hodima.com
 * Description:       کتابخانه مشترک افزونه‌های هدیما: تشخیص IP واقعی (سازگار با Cloudflare)، محدودیت نرخ، تشخیص ربات موتور جستجو، موتور Canonical، گراف واحد اسکیما (JSON-LD) و بررسی محتوای محافظت‌شده. پیش‌نیاز Hodima SEO، Hodima Commerce و Hodima Media.
 * Version:           1.1.8
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
const HODIMA_CORE_PLUGIN_VERSION = '1.1.8';
define( 'HODIMA_CORE_PLUGIN_FILE', __FILE__ );
define( 'HODIMA_CORE_PLUGIN_DIR', __DIR__ );
define( 'HODIMA_CORE_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

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

/*
 * قالب هدیما نسخه ۱ همین توابع مشترک (hodima_get_client_ip، hodima_get_canonical_url و …)
 * را بدون گارد در inc/helpers.php خودش تعریف می‌کند. باگ قبلی: helpers.php بدون این
 * بررسی لود می‌شد؛ روی سایتی که هنوز قالب نسخه ۱ فعال بود، فعال‌سازی افزونه با خطای
 * «Cannot redeclare function hodima_cloudflare_ranges()» شکست می‌خورد و اگر افزونه
 * (مثلا از طریق FTP) فعال می‌ماند، کل سایت از کار می‌افتاد. در این حالت فقط
 * به‌روزرسانی خودکار (برای رساندن قالب به نسخه ۲) و یک اعلان پیشخوان لود می‌شود.
 */
if ( hodima_legacy_theme_active() ) {
	hodima_legacy_theme_notice( 'Hodima Core' );
	require_once HODIMA_CORE_PLUGIN_DIR . '/includes/updates.php';
	return;
}

// بلافاصله لود می‌شود (نه در plugins_loaded) تا توابع برای افزونه‌های دیگر و
// قالب، مستقل از ترتیب الفبایی بارگذاری افزونه‌ها، در دسترس باشند.
require_once HODIMA_CORE_PLUGIN_DIR . '/includes/helpers.php';

// مدیریت ماژول‌ها (هر افزونه ماژول‌هایش را در plugins_loaded ثبت می‌کند)
require_once HODIMA_CORE_PLUGIN_DIR . '/includes/modules.php';

// گراف واحد اسکیما: همه JSON-LDهای صفحه در یک @graph (hodima_schema_add)
require_once HODIMA_CORE_PLUGIN_DIR . '/includes/schema-graph.php';

// به‌روزرسانی خودکار قالب و افزونه‌ها از گیت‌هاب (Plugin Update Checker)
// همه‌جا لود می‌شود، نه فقط پیشخوان: بررسی زمان‌بندی‌شده با WP-Cron هم اجرا می‌شود.
require_once HODIMA_CORE_PLUGIN_DIR . '/includes/updates.php';

// پنل «ابزارهای هدیما» در پیشخوان: وضعیت، روشن/خاموش کردن ماژول‌ها و
// سیستم طراحی مشترک همه صفحه‌های افزونه‌ها (هدر، تب‌ها، فوتر، عرض ۹۵٪)
if ( is_admin() ) {
	require_once HODIMA_CORE_PLUGIN_DIR . '/includes/admin-hub.php';
	require_once HODIMA_CORE_PLUGIN_DIR . '/includes/admin-ui.php';
}
