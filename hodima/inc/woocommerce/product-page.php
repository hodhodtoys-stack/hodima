<?php
/**
 * Single product page — shared helpers, SEO & performance
 * Path: inc/woocommerce/product-page.php
 *
 * خودکار بارگذاری می‌شود (functions.php همه inc/woocommerce را بارگذاری می‌کند).
 *
 * توابع کمکی این فایل منبع واحد حقیقت‌اند برای *هم* قالب صفحه محصول و
 * *هم* اسکیمای محصول (schema/product-schema-pro.php). قبلا این دو هر کدام
 * موجودی را جداگانه تشخیص می‌دادند و روی همین سایت با هم نمی‌خواندند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
 * ۱. موجودی
 * ===================================================================== */

/**
 * وضعیت انبار: 'iran' | 'china' | 'out' | ''
 * ترکیب فیلد سفارشی «وضعیت موجودی» و موجودی خود ووکامرس.
 */
function hodima_product_stock_location( WC_Product $product ): string {

	$meta = (string) get_post_meta( $product->get_id(), '_stock_location_status', true );

	if ( 'out_of_stock' === $meta || ! $product->is_in_stock() ) {
		return 'out';
	}

	return [ 'iran_stock' => 'iran', 'china_stock' => 'china' ][ $meta ] ?? '';
}

/**
 * availability در اسکیما — دقیقا همان چیزی که بازدیدکننده می‌بیند.
 *
 * نسخه قبلی فقط is_in_stock() ووکامرس را می‌خواند. روی همین سایت محصولی
 * با «موجود در انبار چین» در اسکیما InStock با ارسال ۱ تا ۴ روزه بود.
 * ناهمخوانی availability بین صفحه و داده ساختاریافته از دلایل رد شدن
 * در Merchant Listings گوگل است.
 *
 *   انبار چین → BackOrder (موجود، ولی با تاخیر ارسال می‌شود)
 */
function hodima_product_schema_availability( WC_Product $product ): string {
	$map = [
		'out'   => 'OutOfStock',
		'china' => 'BackOrder',
	];

	return 'https://schema.org/' . ( $map[ hodima_product_stock_location( $product ) ] ?? 'InStock' );
}

/* =====================================================================
 * ۲. حداقل سفارش
 * ---------------------------------------------------------------------
 * حداقل *مبلغ* سفارش (فیلد _wholesale_price) در product-hooks.php سمت
 * سرور اعمال می‌شود و در اسکیما به صورت eligibleTransactionVolume می‌آید.
 * تابع «حداقل تعداد» مشتق‌شده حذف شد تا یک قانون، یک نمایش داشته باشیم.
 * ===================================================================== */

/* =====================================================================
 * ۳. ساختار صفحه
 * ===================================================================== */

add_action( 'wp', static function (): void {

	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	/*
	 * wrapper پیش‌فرض ووکامرس حذف می‌شود (قالب single-product.php خود
	 * ووکامرس آن را روی woocommerce_before_main_content باز می‌کند).
	 * content-single-product.php خودش <main> دارد؛ منبع صفحه واقعی دو
	 * <main> داشت. هیچ استایلی به #primary یا .site-main وابسته نیست.
	 */
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
} );

/* =====================================================================
 * ۴. تصویر LCP
 * ---------------------------------------------------------------------
 * تصویر اصلی گالری بزرگ‌ترین عنصر بالای صفحه است. در منبع واقعی نه
 * preload داشت، نه fetchpriority، نه ابعاد. preload باید دقیقا همان
 * srcset/sizes تگ img را داشته باشد، وگرنه مرورگر دو بار دانلود می‌کند.
 * ===================================================================== */

/** srcset و sizes مشترک بین preload و خود تصویر. */
function hodima_gallery_sizes(): string {
	return (string) apply_filters( 'hodima_gallery_sizes', '(max-width: 768px) 100vw, 600px' );
}

add_action( 'wp_head', static function (): void {

	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$product = wc_get_product( (int) get_queried_object_id() );
	$img_id  = $product ? (int) $product->get_image_id() : 0;

	if ( ! $img_id ) {
		return;
	}

	$src    = wp_get_attachment_image_url( $img_id, 'woocommerce_single' );
	$srcset = wp_get_attachment_image_srcset( $img_id, 'woocommerce_single' );

	if ( ! $src ) {
		return;
	}

	printf(
		'<link rel="preload" as="image" href="%1$s"%2$s fetchpriority="high">' . "\n",
		esc_url( $src ),
		$srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '" imagesizes="' . esc_attr( hodima_gallery_sizes() ) . '"' : ''
	);
}, 2 );

/* =====================================================================
 * ۵. jquery-migrate
 * ---------------------------------------------------------------------
 * در head و مسدودکننده رندر بود. ووکامرس و اسکریپت‌های این قالب به آن
 * نیازی ندارند (فقط کد jQuery نسخه‌های خیلی قدیمی). در صورت مشکل:
 *     add_filter( 'hodima_product_keep_jquery_migrate', '__return_true' );
 * ===================================================================== */
add_action( 'wp_enqueue_scripts', static function (): void {

	if ( ! function_exists( 'is_product' ) || ! is_product() || apply_filters( 'hodima_product_keep_jquery_migrate', false ) ) {
		return;
	}

	$scripts = wp_scripts();
	if ( isset( $scripts->registered['jquery'] ) ) {
		$scripts->registered['jquery']->deps = array_values( array_diff( $scripts->registered['jquery']->deps, [ 'jquery-migrate' ] ) );
	}
}, 100 );

/* =====================================================================
 * ۶. محصولات مشابه: عنوان کارت‌ها h3
 * ---------------------------------------------------------------------
 * هر کارت h2 داشت — هم‌سطح عنوان خود بخش «محصولات مشابه». ساختار
 * سرتیترها برای گوگل و صفحه‌خوان تخت می‌شد. استایل کارت از کلاس
 * woocommerce-loop-product__title می‌آید و تغییری نمی‌کند.
 * ===================================================================== */
function hodima_loop_product_title_h3(): void {
	echo '<h3 class="' . esc_attr( apply_filters( 'woocommerce_product_loop_title_classes', 'woocommerce-loop-product__title' ) ) . '">' . esc_html( get_the_title() ) . '</h3>';
}

function hodima_render_upsells( int $limit = 6 ): void {

	$swap = has_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title' );

	if ( $swap ) {
		remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
		add_action( 'woocommerce_shop_loop_item_title', 'hodima_loop_product_title_h3', 10 );
	}

	woocommerce_upsell_display( $limit, $limit );

	if ( $swap ) {
		remove_action( 'woocommerce_shop_loop_item_title', 'hodima_loop_product_title_h3', 10 );
		add_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
	}
}

/* =====================================================================
 * ۷. عنوان بخش نظرات همراه نام محصول
 * ---------------------------------------------------------------------
 * «نظرات کاربران» به تنهایی برای موتورهای پاسخ‌گو (AEO) بدون زمینه است؛
 * «نظرات کاربران درباره چهل گیس» یک بخش قابل‌استناد و مستقل می‌سازد.
 * ===================================================================== */
add_filter( 'woocommerce_reviews_title', static function ( $title, $count = 0, $product = null ) {
	return $product instanceof WC_Product
		? 'نظرات کاربران درباره ' . $product->get_name()
		: 'نظرات کاربران';
}, 99, 3 );
