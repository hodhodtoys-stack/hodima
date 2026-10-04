<?php
/**
 * Hodima Commerce — سبک‌سازی پیشخوان ووکامرس و سشن ربات‌ها
 * Path: plugins/hodima-commerce/inc/woocommerce/store-optimizer.php
 *
 * از قالب (inc/performance/woo-optimizer.php، بخش‌های ۵، ۶، ۷، ۱۰ و ۱۱) منتقل
 * شد — بازسازی قالب، مرحله ۲. این‌ها به ظاهر سایت ربطی ندارند: پیشخوان
 * ووکامرس، ابزارک‌ها و رفتار سشن با عوض شدن قالب نباید تغییر کنند.
 * حذف CSS/JS ووکامرس از صفحه‌های غیرفروشگاهی (بهینه‌سازی نمایش) در قالب ماند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// قالب هدیما قبل از 2.3.0 همین کارها را خودش انجام می‌دهد
if ( function_exists( 'hodima_theme_has_legacy_logic' ) && hodima_theme_has_legacy_logic() ) {
	return;
}

/* =====================================================================
 * ۱. پیشخوان ووکامرس بدون تبلیغ و امکانات بلااستفاده
 * ===================================================================== */
add_filter( 'woocommerce_marketing_menu_items', '__return_empty_array' );
add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true' );
add_filter( 'woocommerce_admin_features', static fn( $features ): array => array_values( array_diff( (array) $features, [ 'marketing', 'analytics', 'wc-pay-promotion', 'wc-pay-welcome-page', 'suggestions' ] ) ) );

// ابزارک سنگین «وضعیت ووکامرس» در پیشخوان اصلی
add_action( 'wp_dashboard_setup', static fn() => remove_meta_box( 'woocommerce_dashboard_status', 'dashboard', 'normal' ), 99 );

// یادداشت‌های پیشخوان (Admin Notes) از منابع خارجی ووکامرس دریافت و ذخیره نشوند (تورم دیتابیس)
add_filter( 'woocommerce_admin_notes_default_sources', '__return_empty_array' );

/* =====================================================================
 * ۲. ابزارک‌های قدیمی ووکامرس (قالب هیچ سایدبار/ناحیه ابزارکی ندارد)
 * ===================================================================== */
add_action( 'widgets_init', static function (): void {
	foreach ( [
		'WC_Widget_Products', 'WC_Widget_Product_Categories', 'WC_Widget_Product_Tag_Cloud', 'WC_Widget_Cart',
		'WC_Widget_Layered_Nav', 'WC_Widget_Layered_Nav_Filters', 'WC_Widget_Price_Filter', 'WC_Widget_Product_Search',
		'WC_Widget_Top_Rated_Products', 'WC_Widget_Recent_Reviews', 'WC_Widget_Recently_Viewed', 'WC_Widget_Rating_Filter',
	] as $widget ) {
		unregister_widget( $widget );
	}
}, 99 );

/* =====================================================================
 * ۳. بدون سشن ووکامرس برای ربات‌های موتور جستجو
 * ---------------------------------------------------------------------
 * هر بازدید ربات یک ردیف سشن در دیتابیس و یک کوکی می‌ساخت. کلاس جایگزین از
 * WC_Session_Handler واقعی ارث می‌برد (یک نسخه خیلی قدیمی‌تر کلاس مستقل بود و
 * روی ترافیک Googlebot خطای Fatal می‌داد) و فقط نوشتن در دیتابیس و کوکی را
 * خنثی می‌کند. فقط درخواست‌های خواندنی؛ هر POST/AJAX مسیر عادی ووکامرس را
 * می‌رود. سیاست کش پاسخ ربات‌ها: Hodima Core (includes/litespeed.php).
 * ===================================================================== */
add_filter( 'woocommerce_session_handler', 'hodima_commerce_bot_session_handler' );

function hodima_commerce_bot_session_handler( $session_class ) {

	if ( ! function_exists( 'hodima_is_search_bot' ) || ! hodima_is_search_bot() ) {
		return $session_class;
	}

	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) || wp_doing_ajax() || ! class_exists( 'WC_Session_Handler' ) ) {
		return $session_class;
	}

	if ( ! class_exists( 'Hodima_Commerce_Bot_Session', false ) ) {
		require_once __DIR__ . '/class-bot-session.php';
	}

	return 'Hodima_Commerce_Bot_Session';
}
