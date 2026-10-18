<?php
/**
 * Module: اندازه دقیق تصویر کارت محصولات (فروشگاه و دسته‌ها)
 * Path: hodima/inc/performance/card-image-sizes.php
 *
 * چرا: ووکامرس برای تصویر کارت `sizes` پیش‌فرض وردپرس را می‌گذارد («تا ۵۰۰px»)، ولی کارت‌ها
 * در عمل ۱۶۵ تا ۲۰۰px عرض دارند (گزارش PageSpeed دسته: «Improve image delivery»، ۱۴ تصویر ۵۰۰px
 * برای نمایش ۱۸۵px). با `sizes` درست، مرورگر از `srcset` نسخه ۳۰۰px (یا ۱۵۰px) را برمی‌دارد.
 *
 * عرض واقعی از شبکه `assets/css/shop-page.css` (۲ ستون تا ۷۶۸، ۳ تا ۹۹۲، ۴ تا ۱۲۰۰، ۶ ستون بالاتر)
 * و اندازه‌گیری Lighthouse (۱۶۵px در ۴۱۲، ۱۸۵px در ۱۳۵۰) است؛ حاشیه‌ها عمدا کمی کمتر کسر شده‌اند
 * تا عرض کمی بیشتر از واقعی برآورد شود (تصویر هرگز کم‌وضوح نشود).
 * با عوض شدن شبکه (ستون‌ها یا فاصله‌ها)، این رشته را هم به‌روز کن.
 *
 * فقط حلقه اصلی فروشگاه/دسته/جستجو (نه «محصولات مرتبط»، بالابر فروش و صفحه اصلی؛ شبکه آن‌ها فرق دارد).
 * خاموش‌کردن: تنظیمات قالب ← سرعت و PageSpeed، یا `add_filter( 'hodima_card_image_sizes', '__return_empty_string' )`.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** رشته `sizes` کارت محصول در حلقه اصلی (خالی = دست نزن). */
function hodima_card_image_sizes(): string {

	if ( ! (bool) hodima_setting( 'card_img_sizes' ) ) {
		return '';
	}

	$default = '(max-width: 768px) calc((100vw - 70px) / 2), (max-width: 992px) calc((100vw - 110px) / 3), (max-width: 1200px) calc((100vw - 140px) / 4), calc((min(100vw, 1440px) - 160px) / 6)';

	return (string) apply_filters( 'hodima_card_image_sizes', $default );
}

add_filter( 'wp_get_attachment_image_attributes', static function ( mixed $attr, mixed $attachment = null, mixed $size = '' ): mixed {

	if ( ! is_array( $attr ) || 'woocommerce_thumbnail' !== $size || empty( $attr['srcset'] ) ) {
		return $attr;
	}

	// حلقه اصلی: «مرتبط/بالابر/تکمیلی» نام حلقه را می‌گذارند (wc_set_loop_prop)
	if ( ! function_exists( 'wc_get_loop_prop' ) || '' !== (string) wc_get_loop_prop( 'name' ) ) {
		return $attr;
	}

	if ( ! ( is_shop() || is_product_taxonomy() || is_search() ) ) {
		return $attr;
	}

	$sizes = hodima_card_image_sizes();

	if ( '' !== $sizes ) {
		$attr['sizes'] = $sizes;
	}

	return $attr;
}, 20, 3 );
