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
 *
 * از 3.7.8: چند کارت اول شبکه‌ای که بالای صفحه است بدون lazy (پایین همین فایل؛ فیلتر hodima_card_eager_count).
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

/*
 * ============================================================
 * کارت‌های بالای صفحه: بدون lazy (از 3.7.8)
 * ============================================================
 * باگ قبلی: همه تصویرهای کارت lazy بودند و لایت‌اسپید هم تنبلشان می‌کرد (src ← data-src)؛ تصویر
 * اولین کارت فروشگاه، که بزرگ‌ترین عنصر صفحه موبایل (LCP) است، تا اجرای اسکریپت لایت‌اسپید کشف
 * نمی‌شد (PageSpeed فروشگاه موبایل: «تأخیر بارگذاری» ۴٫۸ ثانیه، requestDiscoverable نادرست).
 * حالا قالب صفحه (archive-product.php، taxonomy-product_cat.php) پیش از حلقه اعلام می‌کند که شبکه
 * بالای صفحه است یا نه؛ چند کارت اول loading="eager" + data-no-lazy (لایت‌اسپید دست نمی‌زند) و
 * اولی fetchpriority="high" می‌گیرد. دسته با ویدیو/معرفی بالای شبکه ← هیچ (تصویر ویدیو LCP است و
 * کارت‌ها زیر صفحه‌اند؛ دانلود زودشان با آن رقابت می‌کرد).
 */

/** چند کارت اول حلقه که بدون lazy چاپ می‌شوند (۴ = دو ردیف موبایل). فیلتر hodima_card_eager_count؛ ۰ = همه lazy مثل قبل. */
function hodima_card_eager_count(): int {
	return max( 0, (int) apply_filters( 'hodima_card_eager_count', 4 ) );
}

/**
 * شمارنده کارت‌های بالای صفحه.
 *
 * با عدد: شروع (پیش از حلقه، تعداد مجاز) یا پایان (بعد از حلقه، ۰)؛ خروجی ۰.
 * بدون عدد: شماره تصویر فعلی (۱ = اولین) و یکی جلو می‌رود؛ ۰ = بیرون از بازه.
 */
function hodima_card_eager_slot( ?int $reset = null ): int {

	static $limit = 0;
	static $used  = 0;

	if ( null !== $reset ) {
		$limit = max( 0, $reset );
		$used  = 0;
		return 0;
	}

	if ( $used >= $limit ) {
		return 0;
	}

	return ++$used;
}

add_filter( 'wp_get_attachment_image_attributes', static function ( mixed $attr, mixed $attachment = null, mixed $size = '' ): mixed {

	// فقط تصویر کارت در حلقه اصلی (نه «مرتبط/بالابر/تکمیلی» که نام حلقه دارند)
	if ( ! is_array( $attr ) || 'woocommerce_thumbnail' !== $size || ! function_exists( 'wc_get_loop_prop' ) || '' !== (string) wc_get_loop_prop( 'name' ) ) {
		return $attr;
	}

	$slot = hodima_card_eager_slot();

	if ( 0 === $slot ) {
		return $attr;
	}

	$attr['loading']      = 'eager';
	$attr['data-no-lazy'] = '1';

	if ( 1 === $slot ) {
		$attr['fetchpriority'] = 'high';
	}

	return $attr;
}, 20, 3 );
