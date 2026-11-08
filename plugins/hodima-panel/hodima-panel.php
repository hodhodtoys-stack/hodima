<?php
/**
 * Plugin Name:       Hodima Panel
 * Plugin URI:        https://hodima.com
 * Description:       پنل کاربری عمده‌فروشی هدیما: ورود و ثبت‌نام با موبایل، پیش‌خرید و پیش‌فاکتور، فهرست سفارش‌ها با فاکتور و فیش، گفتگوی هر سفارش و تیکت پشتیبانی؛ همراه صفحه‌های مدیریت در پیشخوان.
 * Version:           0.2.3
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

const HODIMA_PANEL_VERSION = '0.2.3';
define( 'HODIMA_PANEL_FILE', __FILE__ );
define( 'HODIMA_PANEL_DIR', __DIR__ );
define( 'HODIMA_PANEL_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/install.php';
require_once __DIR__ . '/includes/files.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/sms.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/view.php';
require_once __DIR__ . '/includes/front.php';
require_once __DIR__ . '/includes/privacy.php';

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
	\Hodima\Panel\Profile::boot();
	\Hodima\Panel\Auth::boot();
	\Hodima\Panel\Privacy::boot();

	// بخش‌هایی که هنوز از پنل قدیمی منتقل شده‌اند و در مرحله‌های بعد بازنویسی می‌شوند
	// (فهرست باگ‌های هر کدام: HODIMA-AUDIT.md بخش «پنل کاربری»).
	foreach ( [ 'chat', 'support' ] as $file ) {
		require_once HODIMA_PANEL_DIR . '/legacy/' . $file . '.php';
	}

	if ( is_admin() ) {
		require_once HODIMA_PANEL_DIR . '/includes/admin-orders.php';
		require_once HODIMA_PANEL_DIR . '/includes/admin-tickets.php';
		require_once HODIMA_PANEL_DIR . '/includes/admin-product.php';
		require_once HODIMA_PANEL_DIR . '/includes/admin-sms.php';
		\Hodima\Panel\Admin_Orders::boot();
		\Hodima\Panel\Admin_Tickets::boot();
		\Hodima\Panel\Admin_Product::boot();
		\Hodima\Panel\Admin_Sms::boot();
	}
}, 20 );
