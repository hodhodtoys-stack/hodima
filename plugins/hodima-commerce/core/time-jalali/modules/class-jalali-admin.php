<?php
/**
 * تاریخ شمسی در فیلدهای تاریخ پیشخوان ووکامرس.
 *
 * مقدار فیلد همان تاریخ میلادی «Y-m-d» می‌ماند که ووکامرس ذخیره می‌کند (بخش ۹۹: شمسی‌کردن خود
 * مقدار تاریخ سفارش را ۱۹۷۰ می‌کرد)؛ این ماژول فقط معادل شمسی را کنار فیلد نشان می‌دهد و با
 * دکمه تقویم شمسی تاریخ را انتخاب می‌کند و مقدار میلادی را در همان فیلد می‌نویسد.
 *
 * @package Hodima_Commerce
 */

declare( strict_types=1 );
namespace Hodima\Core\Time_Jalali\Modules;

defined( 'ABSPATH' ) || exit;

/** دارایی‌های تقویم شمسی پیشخوان ووکامرس. */
final class Jalali_Admin {

	/** ثبت هوک. */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * صفحه‌هایی که فیلد تاریخ ووکامرس دارند: محصول (حراج)، سفارش (تاریخ ثبت، انقضای دانلود)،
	 * کوپن (انقضا) و گزارش‌ها (بازه دلخواه).
	 */
	private function is_wc_date_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		return in_array( $screen->post_type, [ 'product', 'shop_order', 'shop_coupon' ], true )
			|| str_contains( $screen->id, 'wc-orders' )
			|| str_contains( $screen->id, 'wc-reports' );
	}

	/** فقط در همان صفحه‌ها. */
	public function enqueue(): void {
		if ( ! $this->is_wc_date_screen() ) {
			return;
		}
		$dir = dirname( __DIR__ );
		$ver = static fn( string $file ): string => is_file( "{$dir}/{$file}" ) ? (string) filemtime( "{$dir}/{$file}" ) : HODIMA_COMMERCE_VERSION;
		wp_enqueue_style( 'hodima-jalali-admin', HODIMA_COMMERCE_URL . '/core/time-jalali/admin-jalali.css', [ 'dashicons' ], $ver( 'admin-jalali.css' ) );
		wp_enqueue_script( 'hodima-jalali-admin', HODIMA_COMMERCE_URL . '/core/time-jalali/admin-jalali.js', [], $ver( 'admin-jalali.js' ), [ 'in_footer' => true ] );
		wp_add_inline_script(
			'hodima-jalali-admin',
			'window.hodimaJalaliAdmin = ' . wp_json_encode(
				[
					'months'   => array_values( hodima_jalali_months() ),
					'weekdays' => array_values( hodima_jalali_weekdays() ),
					'today'    => wp_date( 'Y-m-d' ),
				],
				JSON_UNESCAPED_UNICODE
			) . ';',
			'before'
		);
	}
}
