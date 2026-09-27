<?php
/**
 * Plugin Name:       Hodima Commerce
 * Plugin URI:        https://hodima.com
 * Description:       امکانات فروشگاهی هدیما برای ووکامرس: تاریخ جلالی و شهرهای ایران در تسویه‌حساب، جستجوی زنده محصولات با ایندکس فارسی، فرم لید تلفنی، جدول مشخصات محصول، حداقل مبلغ و تعداد سفارش عمده و فیلدهای سفارشی محصول.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Requires Plugins:  hodima-core
 * Author:            آرین فتحی
 * Author URI:        https://hodima.com
 * License:           GPL-2.0-or-later
 * Text Domain:       hodima-commerce
 * WC requires at least: 8.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_COMMERCE_VERSION = '1.0.0';
define( 'HODIMA_COMMERCE_FILE', __FILE__ );
define( 'HODIMA_COMMERCE_DIR', __DIR__ );
define( 'HODIMA_COMMERCE_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/** نسخه فایل استاتیک از زمان تغییرش (کش مرورگر با هر تغییر باطل می‌شود). */
function hodima_commerce_asset_version( string $relative ): string {
	$path = HODIMA_COMMERCE_DIR . '/' . ltrim( $relative, '/' );
	return is_file( $path ) ? (string) filemtime( $path ) : HODIMA_COMMERCE_VERSION;
}

// سازگاری با ذخیره‌سازی جدید سفارش‌ها (HPOS)؛ باید با فایل اصلی افزونه اعلام شود
add_action( 'before_woocommerce_init', static function (): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', HODIMA_COMMERCE_FILE, true );
	}
} );

add_action( 'plugins_loaded', static function (): void {

	if ( function_exists( 'hodima_legacy_theme_active' ) && hodima_legacy_theme_active() ) {
		hodima_legacy_theme_notice( 'Hodima Commerce' );
		return;
	}

	// فرم لید تلفنی به ووکامرس وابسته نیست
	require_once HODIMA_COMMERCE_DIR . '/components/phone/phone-form.php';

	// بقیه ماژول‌ها بدون ووکامرس فقط Fatal Error تولید می‌کنند
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function (): void {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html( 'Hodima Commerce: ووکامرس فعال نیست؛ فقط فرم لید تلفنی فعال است.' ) . '</p></div>';
			}
		} );
		return;
	}

	foreach ( [
		'components/search/search.php',
		'inc/woocommerce/product-fields.php',
		'inc/woocommerce/cart.php',
		'inc/hodima-woo-table/woo-table.php',
		'core/time-jalali/time-jalali.php',
	] as $module ) {
		require_once HODIMA_COMMERCE_DIR . '/' . $module;
	}

	\Hodima\Core\Time_Jalali\Hodima_Localizer_WC::get_instance();
}, 20 );
