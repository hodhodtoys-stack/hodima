<?php
/**
 * قیمتی که اسکیما و سایت‌های دیگر از محصول می‌بینند
 * Path: plugins/hodima-seo/inc/product-price.php
 *
 * «تنظیمات قالب ← صفحه محصول ← قیمت در اسکیما و سایت‌های دیگر»
 * (product_offer_price): «قیمت تک» (پیش‌فرض، رفتار قبلی) یا «حداقل سفارش»
 * (فیلد حداقل مبلغ هر محصول، _wholesale_price، همان عددی که صفحه محصول کنار
 * «قیمت» نشان می‌دهد). اسکیمای محصول، متاتگ‌های قیمت Open Graph (ترب و
 * سایت‌های مقایسه قیمت)، پیش‌نمایش لینک و AEO همه از همین تابع می‌خوانند تا
 * هیچ‌جا دو عدد متفاوت اعلام نشود. بیرون از ماژول‌ها: هر ماژولی روشن باشد.
 *
 * ترب و ایمالز (افزونه‌های رسمی‌شان) قیمت را نه از اسکیما و متاتگ، که مستقیم از
 * ووکامرس (get_price) در وب‌سرویس خودشان می‌خوانند؛ پس در همان درخواست‌ها
 * (مسیر REST یا اکشن admin-ajax با torob/emalls) قیمت محصول ساده همین قیمت بیرونی می‌شود
 * (بخش «وب‌سرویس» پایین). سبد، پرداخت و صفحه‌های سایت دست نمی‌خورند. تا 1.12.0 گزینه
 * روی ترب اثری نداشت.
 *
 * محصول متغیر یا تنوع آن: همیشه قیمت تک (حداقل مبلغ محصول مادر برای هر تنوع
 * معنی ندارد و حداقل تعداد هم فقط روی محصول ساده اعمال می‌شود). محصول بی‌قیمت:
 * بی‌قیمت (مثل قبل بدون Offer).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * @return array{amount: string, min_order: bool}
 *   amount: عدد خام به واحد پول فروشگاه ('' = بدون قیمت)؛ min_order: آیا مبلغ حداقل سفارش است.
 */
function hodima_seo_product_feed_price( WC_Product $product ): array {

	$unit = hodima_seo_unit_price( $product );
	$mode = function_exists( 'hodima_setting' ) ? (string) hodima_setting( 'product_offer_price' ) : 'unit';

	if ( 'min_order' !== $mode || ! is_numeric( $unit ) || (float) $unit <= 0 || $product->is_type( [ 'variable', 'variation' ] ) ) {
		return [ 'amount' => $unit, 'min_order' => false ];
	}

	$min_total = (float) get_post_meta( $product->get_id(), '_wholesale_price', true );

	if ( $min_total <= 0 ) {
		return [ 'amount' => $unit, 'min_order' => false ];
	}

	return [ 'amount' => function_exists( 'wc_format_decimal' ) ? (string) wc_format_decimal( $min_total ) : (string) $min_total, 'min_order' => true ];
}

/** قیمت تک ووکامرس، بدون فیلتر وب‌سرویس ترب پایین (وگرنه حلقه بی‌پایان). */
function hodima_seo_unit_price( WC_Product $product ): string {

	$GLOBALS['hodima_seo_feed_busy'] = true;
	try {
		return (string) $product->get_price();
	} finally {
		$GLOBALS['hodima_seo_feed_busy'] = false;
	}
}

/* =========================================================================
 * وب‌سرویس سایت‌های مقایسه قیمت (ترب، ایمالز)
 * ========================================================================= */

/**
 * آیا درخواست جاری وب‌سرویس یک سایت مقایسه قیمت است؟ مسیر REST یا اکشن
 * admin-ajax شامل torob یا emalls (فیلتر hodima_seo_feed_services برای سرویس
 * دیگر). هرگز صفحه HTML: آدرسی مثل ‎?torob=1 نباید قیمت صفحه را برای بازدیدکننده
 * (و کش لایت‌اسپید) عوض کند.
 */
function hodima_seo_is_feed_request(): bool {

	// فقط «بله» به خاطر می‌ماند: پیش از معلوم شدن مسیر REST پاسخ «نه» موقت است
	static $memo = false;
	if ( $memo ) {
		return true;
	}

	$services = array_filter( array_map( 'strtolower', (array) apply_filters( 'hodima_seo_feed_services', [ 'torob', 'emalls' ] ) ) );
	$route    = strtolower( (string) ( $GLOBALS['hodima_seo_rest_route'] ?? '' ) );
	$action   = wp_doing_ajax() ? strtolower( sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط نام اکشن

	foreach ( $services as $service ) {
		if ( ( '' !== $route && str_contains( $route, $service ) ) || ( '' !== $action && str_contains( $action, $service ) ) ) {
			return $memo = true;
		}
	}

	return false;
}

add_filter( 'rest_pre_dispatch', static function ( mixed $result, mixed $server, mixed $request ): mixed {
	if ( $request instanceof WP_REST_Request ) {
		$GLOBALS['hodima_seo_rest_route'] = $request->get_route();
	}
	return $result;
}, 1, 3 );

/**
 * در وب‌سرویس ترب/ایمالز با گزینه «حداقل سفارش»: قیمت، قیمت قبلی و حراج محصول
 * ساده = مبلغ حداقل سفارش (قیمت قبلی کمتر از قیمت فعلی، «تخفیف» منفی نشان نمی‌دهد).
 */
function hodima_seo_feed_price_filter( mixed $price, mixed $product ): mixed {

	if ( ! empty( $GLOBALS['hodima_seo_feed_busy'] ) || ! $product instanceof WC_Product || ! hodima_seo_is_feed_request() ) {
		return $price;
	}

	$feed = hodima_seo_product_feed_price( $product );

	if ( ! $feed['min_order'] ) {
		return $price;
	}

	return 'woocommerce_product_get_sale_price' === current_filter() ? '' : $feed['amount'];
}

foreach ( [ 'woocommerce_product_get_price', 'woocommerce_product_get_regular_price', 'woocommerce_product_get_sale_price' ] as $hodima_seo_hook ) {
	add_filter( $hodima_seo_hook, 'hodima_seo_feed_price_filter', 99, 2 );
}
unset( $hodima_seo_hook );
