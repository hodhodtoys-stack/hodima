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

	$unit = (string) $product->get_price();
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
