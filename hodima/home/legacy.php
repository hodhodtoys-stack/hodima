<?php
/**
 * نام‌های قدیمی توابع صفحه اصلی (arian_*) — فقط برای سازگاری
 * Path: home/legacy.php
 *
 * بازسازی قالب، مرحله ۵: توابع home/logic.php پیشوند hodima_home_* گرفتند.
 * این سه تابع ممکن است در کد سفارشی سایت (افزونه کوچک، قالب فرزند) صدا زده
 * شده باشند؛ نسخه قدیمی همان تابع جدید را صدا می‌زند. کد جدید از نام جدید
 * استفاده کند. بقیه (پاک‌کننده‌های کش روی هوک‌ها) فقط داخلی بودند.
 * کلیدهای کش (arian_pslider_*، arian_cat_shortcode_map) عمدا عوض نشدند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'arian_render_product_slider' ) ) {
	/** @deprecated 2.6.0 hodima_home_product_slider() */
	function arian_render_product_slider( mixed $args = [] ): string {
		return hodima_home_product_slider( (array) $args );
	}
}

if ( ! function_exists( 'arian_load_home_section' ) ) {
	/** @deprecated 2.6.0 hodima_home_load_section() */
	function arian_load_home_section( mixed $tag ): string {
		return hodima_home_load_section( (string) $tag );
	}
}

if ( ! function_exists( 'arian_get_category_shortcode_map' ) ) {
	/**
	 * @deprecated 2.6.0 hodima_home_category_shortcode_map()
	 * @return array<string, string>
	 */
	function arian_get_category_shortcode_map(): array {
		return hodima_home_category_shortcode_map();
	}
}
