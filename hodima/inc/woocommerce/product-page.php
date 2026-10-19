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

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
 * ۱. موجودی
 * ---------------------------------------------------------------------
 * hodima_product_stock_location() و hodima_product_schema_availability()
 * به افزونه Hodima Commerce منتقل شدند (فیلد «وضعیت موجودی» همان‌جاست).
 * ===================================================================== */

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

	// مسیر راهنما داخل قاب صفحه چاپ می‌شود (content-single-product.php)، نه بیرون آن
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
} );

/* =====================================================================
 * ۴. تصویر LCP
 * ---------------------------------------------------------------------
 * تصویر اصلی گالری بزرگ‌ترین عنصر بالای صفحه است. در منبع واقعی نه
 * preload داشت، نه fetchpriority، نه ابعاد. preload باید دقیقا همان
 * srcset/sizes تگ img را داشته باشد، وگرنه مرورگر دو بار دانلود می‌کند.
 * ===================================================================== */

/**
 * sizes مشترک بین preload و خود تصویر = عرض واقعی کادر تصویر اصلی گالری (اندازه‌گیری‌شده در
 * Chromium با CSS قالب): تا ۴۸۰px برابر عرض صفحه منهای ۵۴px، تا ۷۶۸ منهای ۷۴px، تا ۹۹۲ منهای ۹۴px،
 * و دو ستونه از ۹۹۳px که کادر حداکثر ۴۵۸px است.
 *
 * تا 3.7.3 «۱۰۰vw» و «۶۰۰px» بود: روی گوشی ۳۹۰px با صفحه نمایش ۳برابر، مرورگر ۱۱۷۰px می‌خواست
 * (و ۱۵۳۶ را می‌گرفت) برای کادر ۳۳۶px؛ روی دسکتاپ ۶۰۰px برای کادر ۴۵۸px. اگر فاصله‌های
 * کنار گالری در single-product.css عوض شد، این اعداد باید دوباره اندازه‌گیری شوند.
 */
function hodima_gallery_sizes(): string {
	return (string) apply_filters( 'hodima_gallery_sizes', '(max-width: 480px) calc(100vw - 54px), (max-width: 768px) calc(100vw - 74px), (max-width: 992px) calc(100vw - 94px), 458px' );
}

/**
 * srcset تصویر اصلی گالری، حداکثر ۱۰۲۴px. وردپرس همه اندازه‌ها تا ۲۰۴۸px را می‌گذارد، ولی کادر
 * هیچ‌وقت بیشتر از ۴۵۸ تا ۸۹۸px CSS نیست؛ ۱۰۲۴px برای صفحه ۳برابر هم کافی است و تصویر بزرگ‌تر
 * فقط زمان LCP را بالا می‌برد. بزرگنمایی گالری از data-full (تصویر کامل) می‌آید، نه از این srcset.
 * preload و تصویر باید دقیقا همین را بگیرند، وگرنه مرورگر دو بار دانلود می‌کند.
 */
function hodima_gallery_srcset( int $image_id ): string {

	$srcset = (string) wp_get_attachment_image_srcset( $image_id, 'woocommerce_single' );

	if ( '' === $srcset ) {
		return '';
	}

	$max_width = max( 500, (int) apply_filters( 'hodima_gallery_srcset_max_width', 1024 ) );
	$kept      = array_filter(
		array_map( 'trim', explode( ',', $srcset ) ),
		static function ( string $candidate ) use ( $max_width ): bool {
			return 1 !== preg_match( '/\s(\d+)w$/', $candidate, $match ) || (int) $match[1] <= $max_width;
		}
	);

	return [] === $kept ? $srcset : implode( ', ', $kept );
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
	$srcset = hodima_gallery_srcset( $img_id );

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
	echo '<h3 class="' . esc_attr( apply_filters( 'woocommerce_product_loop_title_classes', 'woocommerce-loop-product__title' ) ) . '">' . esc_html( get_the_title() ) . '</h3>'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- فیلتر خود ووکامرس
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
 * از 3.8.9 متن از تنظیمات قالب (تب «صفحه محصول ← نظرات کاربران»، فیلد
 * product_reviews_title؛ {product} = نام محصول) و برای محصول بدون نظر هم: قالب
 * woocommerce/single-product-reviews.php (از 3.9.0) فیلتر را در هر دو حالت صدا می‌زند
 * (ووکامرس فقط با نظر؛ در 3.8.9 محصول بی‌نظر از راه gettext «Reviews» می‌گرفت).
 * متن خام برمی‌گردد؛ esc_html در فیلتر.
 * ===================================================================== */
function hodima_product_reviews_title( mixed $product = null ): string {

	$title = trim( (string) hodima_setting( 'product_reviews_title' ) ); // خالی = پیش‌فرض (hodima_settings)

	$product = $product instanceof WC_Product ? $product : wc_get_product( get_the_ID() );
	if ( ! $product instanceof WC_Product ) {
		// بیرون از صفحه محصول نام نداریم؛ «درباره {product}» بی‌معنا می‌شود
		$title = trim( (string) preg_replace( '/\s*(درباره\s*)?\{product\}\s*/u', ' ', $title ) );
		return '' !== $title ? $title : 'نظرات کاربران';
	}

	return str_replace( '{product}', $product->get_name(), $title );
}

add_filter( 'woocommerce_reviews_title', static function ( mixed $title, mixed $count = 0, mixed $product = null ): string {
	return esc_html( hodima_product_reviews_title( $product ) );
}, 99, 3 );

/* =====================================================================
 * ۸. اندازه عنوان «نظرات کاربران» و «محصولات پیشنهادی» (از 3.8.9)
 * ---------------------------------------------------------------------
 * پیش‌فرض‌ها در tokens.css (همان اندازه ثابت قبلی)؛ مثل تایپوگرافی فقط
 * مقدار تغییرکرده بعد از tokens.css چاپ می‌شود و فقط در صفحه محصول.
 * قانون‌ها: single-product.css (اندازه روان ۳۹۰ تا ۱۲۰۰، مثل تیترها).
 * ===================================================================== */

/**
 * متغیرهای :root که با پیش‌فرض فرق دارند.
 *
 * @return array<string, string>
 */
function hodima_product_title_size_vars(): array {

	$fields = hodima_settings_fields();
	$map    = [
		'product_reviews_title_size'        => '--hodima-product-reviews-title-max',
		'product_reviews_title_size_mobile' => '--hodima-product-reviews-title-min',
		'product_upsells_title_size'        => '--hodima-product-upsells-title-max',
		'product_upsells_title_size_mobile' => '--hodima-product-upsells-title-min',
	];

	$vars = [];
	foreach ( $map as $key => $var ) {
		$value = (float) hodima_setting( $key );
		if ( $value > 0 && $value !== (float) ( $fields[ $key ]['default'] ?? 0 ) ) {
			$vars[ $var ] = hodima_number_text( $value );
		}
	}

	return $vars;
}

add_action( 'wp_enqueue_scripts', static function (): void {

	if ( ! function_exists( 'is_product' ) || ! is_product() || ! wp_style_is( 'hodima-tokens', 'enqueued' ) ) {
		return;
	}

	$css = '';
	foreach ( hodima_product_title_size_vars() as $name => $value ) {
		$css .= $name . ':' . $value . ';';
	}
	if ( '' !== $css ) {
		wp_add_inline_style( 'hodima-tokens', ':root{' . $css . '}' );
	}
}, 21 );
