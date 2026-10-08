<?php
/**
 * قیمتی که اسکیما و ترب از محصول می‌بینند
 * Path: plugins/hodima-seo/inc/product-price.php
 *
 * «تنظیمات قالب ← صفحه محصول» دو قاب جدا دارد، هر کدام «قیمت تک» (پیش‌فرض) یا
 * «حداقل سفارش» (فیلد حداقل مبلغ هر محصول، _wholesale_price، همان عددی که صفحه
 * محصول کنار «قیمت» نشان می‌دهد):
 * - «قیمت در اسکیما (گوگل)» (product_offer_price): اسکیمای محصول، متاتگ‌های قیمت
 *   Open Graph، پیش‌نمایش لینک و AEO — هدف 'schema'.
 * - «قیمت در ترب» (product_torob_price، از 1.14.0): وب‌سرویس افزونه رسمی ترب — هدف 'torob'.
 * تا 1.13.0 یک گزینه برای همه بود و ایمالز هم پوشش داده می‌شد (به خواست کاربر حذف شد).
 *
 * افزونه رسمی ترب («استخراج محصولات ووکامرس برای ترب»، products-extractor-for-woocommerce)
 * قیمت را نه از اسکیما و متاتگ، که با get_price()/get_regular_price() ووکامرس در
 * مسیر REST خودش (‎/wp-json/wcpe/v1/products) می‌خواند؛ پس فقط در همان درخواست قیمت
 * محصول ساده قیمت ترب می‌شود (بخش «وب‌سرویس ترب» پایین). سبد، پرداخت و صفحه‌های
 * سایت دست نمی‌خورند. باگ 1.13.0: تشخیص با کلمه torob در آدرس بود و مسیر واقعی
 * افزونه (wcpe) آن را ندارد؛ ترب همیشه قیمت تک می‌گرفت.
 *
 * محصول متغیر یا تنوع آن: همیشه قیمت تک (حداقل مبلغ محصول مادر برای هر تنوع
 * معنی ندارد و حداقل تعداد هم فقط روی محصول ساده اعمال می‌شود). محصول بی‌قیمت:
 * بی‌قیمت (مثل قبل بدون Offer).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * @param string $target 'schema' (اسکیما، متاتگ، AEO) یا 'torob' (وب‌سرویس ترب).
 * @return array{amount: string, min_order: bool}
 *   amount: عدد خام به واحد پول فروشگاه ('' = بدون قیمت)؛ min_order: آیا مبلغ حداقل سفارش است.
 */
function hodima_seo_product_feed_price( WC_Product $product, string $target = 'schema' ): array {

	$unit = hodima_seo_unit_price( $product );
	$key  = 'torob' === $target ? 'product_torob_price' : 'product_offer_price';
	$mode = function_exists( 'hodima_setting' ) ? (string) hodima_setting( $key ) : 'unit';

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
 * وب‌سرویس ترب
 * ========================================================================= */

/**
 * آیا این درخواست REST از ترب است؟
 * - مسیر افزونه ترب: فضای نام wcpe (محصولات) یا torob… (فیلتر hodima_seo_torob_routes
 *   اگر نسخه بعدی افزونه مسیر دیگری ساخت)؛
 * - یا سربرگ X-Torob-Token که ترب روی هر درخواستش می‌فرستد (نسخه‌های بعدی افزونه با
 *   هر مسیری)، به‌جز مسیرهای خود ووکامرس و وردپرس (wc…، wp…): سربرگ را هر کسی
 *   می‌تواند بفرستد و نباید قیمت سبد/پرداخت فروشگاه (Store API) را عوض کند.
 * صفحه HTML هرگز (قیمت صفحه و کش لایت‌اسپید نباید عوض شود).
 */
function hodima_seo_rest_is_torob( WP_REST_Request $request ): bool {

	$route = strtolower( $request->get_route() );

	foreach ( (array) apply_filters( 'hodima_seo_torob_routes', [ '/wcpe/', '/torob' ] ) as $prefix ) {
		if ( '' !== (string) $prefix && str_starts_with( $route, strtolower( (string) $prefix ) ) ) {
			return true;
		}
	}

	return '' !== (string) $request->get_header( 'X-Torob-Token' )
		&& ! str_starts_with( $route, '/wc' ) && ! str_starts_with( $route, '/wp' );
}

/**
 * صفحه «پیش‌نمایش اطلاعات محصولات» خود افزونه ترب (پیشخوان ← ترب ← مشاهده پیش‌نمایش)
 * همان داده وب‌سرویس را می‌سازد؛ با همان قیمت ترب تا مدیر نتیجه را همان‌جا ببیند.
 */
function hodima_seo_is_torob_preview(): bool {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط خواندن نام صفحه پیشخوان
	return is_admin()
		&& 'torob-settings' === sanitize_key( wp_unslash( $_GET['page'] ?? '' ) )
		&& '1' === sanitize_key( wp_unslash( $_GET['torob_products_preview'] ?? '' ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}

/** آیا قیمت ووکامرس در این درخواست باید قیمت ترب باشد؟ */
function hodima_seo_is_feed_request(): bool {
	return ! empty( $GLOBALS['hodima_seo_torob_request'] ) || hodima_seo_is_torob_preview();
}

add_filter( 'rest_pre_dispatch', static function ( mixed $result, mixed $server, mixed $request ): mixed {
	if ( $request instanceof WP_REST_Request ) {
		$GLOBALS['hodima_seo_torob_request'] = hodima_seo_rest_is_torob( $request );
	}
	return $result;
}, 1, 3 );

/**
 * در وب‌سرویس ترب با گزینه «حداقل سفارش»: قیمت، قیمت قبلی و حراج محصول
 * ساده = مبلغ حداقل سفارش (قیمت قبلی کمتر از قیمت فعلی، «تخفیف» منفی نشان نمی‌دهد).
 */
function hodima_seo_feed_price_filter( mixed $price, mixed $product ): mixed {

	if ( ! empty( $GLOBALS['hodima_seo_feed_busy'] ) || ! $product instanceof WC_Product || ! hodima_seo_is_feed_request() ) {
		return $price;
	}

	$feed = hodima_seo_product_feed_price( $product, 'torob' );

	if ( ! $feed['min_order'] ) {
		return $price;
	}

	return 'woocommerce_product_get_sale_price' === current_filter() ? '' : $feed['amount'];
}

foreach ( [ 'woocommerce_product_get_price', 'woocommerce_product_get_regular_price', 'woocommerce_product_get_sale_price' ] as $hodima_seo_hook ) {
	add_filter( $hodima_seo_hook, 'hodima_seo_feed_price_filter', 99, 2 );
}
unset( $hodima_seo_hook );
