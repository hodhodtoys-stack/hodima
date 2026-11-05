<?php
/**
 * Plugin Name:       Hodima Panel
 * Plugin URI:        https://hodima.com
 * Description:       پنل کاربری عمده‌فروشی هدیما: ورود و ثبت‌نام با موبایل، پیش‌خرید و پیش‌فاکتور، فهرست سفارش‌ها با فاکتور و فیش، گفتگوی هر سفارش و تیکت پشتیبانی؛ همراه صفحه‌های مدیریت در پیشخوان.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Author:            آرین فتحی
 * Author URI:        https://hodima.com
 * License:           GPL-2.0-or-later
 * Text Domain:       hodima-panel
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_PANEL_VERSION = '0.1.1';
define( 'HODIMA_PANEL_FILE', __FILE__ );
define( 'HODIMA_PANEL_DIR', __DIR__ );
define( 'HODIMA_PANEL_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/install.php';
require_once __DIR__ . '/includes/files.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/page.php';

// جدول‌ها و برگه پنل هنگام فعال‌سازی ساخته می‌شوند (پنل قدیمی قالب فقط با
// اولین ورود مدیر به پیشخوان جدول می‌ساخت؛ تا آن موقع بخش‌های سایت خطای پایگاه داده می‌دادند).
register_activation_hook( __FILE__, [ \Hodima\Panel\Install::class, 'activate' ] );

/*
 * راه‌اندازی بعد از لود قالب: پنل قدیمی داخل قالب (پوشه hodima/user-panel) همان
 * جدول‌ها، برگه و اکشن‌ها را دارد و ثابتش را functions.php قالب تعریف می‌کند —
 * یعنی بعد از plugins_loaded. اگر آن پوشه هنوز روی سایت است، این افزونه کاری نمی‌کند.
 */
add_action( 'after_setup_theme', static function (): void {

	if ( defined( 'HODIMA_USER_PANEL_ENABLED' ) && HODIMA_USER_PANEL_ENABLED ) {
		add_action( 'admin_notices', static function (): void {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Hodima Panel غیرفعال ماند: پنل کاربری قدیمی هنوز داخل قالب است (پوشه user-panel قالب). آن پوشه را حذف کنید تا افزونه کار کند.', 'hodima-panel' );
				echo '</p></div>';
			}
		} );
		return;
	}

	\Hodima\Panel\Install::maybe_upgrade();
	\Hodima\Panel\Page::boot();
	\Hodima\Panel\Files::boot();
	\Hodima\Panel\Orders::boot();

	// بخش‌هایی که هنوز از پنل قدیمی منتقل شده‌اند و در مرحله‌های بعد بازنویسی می‌شوند
	// (فهرست باگ‌های هر کدام: HODIMA-AUDIT.md بخش «پنل کاربری»).
	foreach ( [ 'auth', 'router', 'profile', 'chat', 'support', 'product-fields' ] as $file ) {
		require_once HODIMA_PANEL_DIR . '/legacy/' . $file . '.php';
	}

	if ( is_admin() ) {
		require_once HODIMA_PANEL_DIR . '/legacy/admin/menu.php';
		require_once HODIMA_PANEL_DIR . '/legacy/admin/support.php';
	}
}, 20 );
