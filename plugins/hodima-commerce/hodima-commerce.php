<?php
/**
 * Plugin Name:       Hodima Commerce
 * Plugin URI:        https://hodima.com
 * Description:       امکانات فروشگاهی هدیما برای ووکامرس: تاریخ جلالی و شهرهای ایران در تسویه‌حساب، جستجوی زنده محصولات با ایندکس فارسی، فرم لید تلفنی، جدول مشخصات محصول، حداقل مبلغ و تعداد سفارش عمده و فیلدهای سفارشی محصول.
 * Version:           1.1.8
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

const HODIMA_COMMERCE_VERSION = '1.1.8';
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

/**
 * ماژول‌های افزونه. کلیدهای هر آرایه همان پارامترهای Hodima\Core\Module هستند.
 *
 * @return array<string, array<string, mixed>>
 */
function hodima_commerce_modules(): array {
	return [
		'phone' => [
			'title'       => 'فرم لید تلفنی',
			'description' => 'فرم «شماره تماس» (شورت‌کد hodima_phone_form) با ضد اسپم، فهرست لیدها در پیشخوان و خروجی CSV.',
			'files'       => [ 'components/phone/phone-form.php' ],
			'settings'    => 'edit.php?post_type=hodima_phone_lead',
			'icon'        => 'dashicons-phone',
		],
		'search' => [
			'title'       => 'جستجوی زنده محصولات',
			'description' => 'جستجوی فوری با ایندکس FULLTEXT، یکسان‌سازی حروف و اعداد فارسی و جستجو با SKU.',
			'files'       => [ 'components/search/search.php' ],
			'requires_wc' => true,
			'icon'        => 'dashicons-search',
		],
		'product-fields' => [
			'title'       => 'فیلدهای محصول عمده',
			'description' => 'حداقل مبلغ هر محصول، حداقل تعداد، و وضعیت موجودی (انبار ایران/چین).',
			'files'       => [ 'inc/woocommerce/product-fields.php' ],
			'requires_wc' => true,
			'settings'    => 'edit.php?post_type=product',
			'icon'        => 'dashicons-products',
		],
		'min-order' => [
			'title'       => 'حداقل مبلغ سفارش',
			'description' => 'نوار پیشرفت در سبد خرید و قفل تسویه‌حساب تا رسیدن به حداقل مبلغ سفارش عمده.',
			'files'       => [ 'inc/woocommerce/cart.php' ],
			'requires_wc' => true,
			'settings'    => 'admin.php?page=wc-settings&tab=general',
			'icon'        => 'dashicons-cart',
		],
		'specs-table' => [
			'title'       => 'جدول مشخصات محصول',
			'description' => 'جدول مشخصات فنی از ویژگی‌ها و فیلدهای محصول (شورت‌کد woo_specs_table).',
			'files'       => [ 'inc/hodima-woo-table/woo-table.php' ],
			'requires_wc' => true,
			'recommends'  => [ 'product-fields' ],
			'icon'        => 'dashicons-editor-table',
		],
		'jalali' => [
			'title'       => 'بومی‌سازی (جلالی و شهرها)',
			'description' => 'تاریخ شمسی در ووکامرس، استان‌ها و شهرهای ایران و اعتبارسنجی موبایل و کد پستی در تسویه‌حساب.',
			'files'       => [ 'core/time-jalali/time-jalali.php' ],
			'requires_wc' => true,
			'settings'    => 'options-general.php?page=hodima-woocommerce',
			'icon'        => 'dashicons-calendar-alt',
		],
	];
}

add_action( 'plugins_loaded', static function (): void {

	if ( function_exists( 'hodima_legacy_theme_active' ) && hodima_legacy_theme_active() ) {
		hodima_legacy_theme_notice( 'Hodima Commerce' );
		return;
	}

	// بدون Hodima Core، هدر/تب مشترک پیشخوان نسخه ساده می‌گیرد
	if ( is_admin() && ! function_exists( 'hodima_admin_header' ) ) {
		require_once __DIR__ . '/inc/admin-ui-fallback.php';
	}

	$specs = hodima_commerce_modules();

	if ( class_exists( \Hodima\Core\Modules::class ) ) {
		\Hodima\Core\Modules::register(
			'commerce',
			'فروشگاه',
			'امکانات فروشگاهی برای ووکامرس. ماژول‌هایی که «نیاز به ووکامرس» دارند بدون آن اجرا نمی‌شوند.',
			HODIMA_COMMERCE_FILE,
			HODIMA_COMMERCE_VERSION,
			...array_map(
				static fn( string $id, array $spec ): \Hodima\Core\Module => new \Hodima\Core\Module( $id, ...$spec ),
				array_keys( $specs ),
				$specs
			)
		);
		\Hodima\Core\Modules::load( 'commerce' );
		return;
	}

	// بدون Hodima Core: همه ماژول‌های قابل اجرا مثل قبل
	foreach ( $specs as $spec ) {
		if ( empty( $spec['requires_wc'] ) || class_exists( 'WooCommerce' ) ) {
			foreach ( $spec['files'] as $file ) {
				require_once HODIMA_COMMERCE_DIR . '/' . $file;
			}
		}
	}
}, 20 );
