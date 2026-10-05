<?php
/**
 * Product category archives — pagination & SEO
 * Path: inc/woocommerce/category-archive.php
 *
 * خودکار بارگذاری می‌شود (functions.php همه فایل‌های inc/woocommerce را
 * بارگذاری می‌کند). این هوک‌ها باید *پیش از* اجرای کوئری اصلی ثبت شوند،
 * پس نمی‌توانند داخل قالب taxonomy-product_cat.php باشند.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** آیا صفحه جاری آرشیو محصولات است (دسته، برچسب، فروشگاه)؟ */
function hodima_is_product_archive(): bool {
	return function_exists( 'is_product_taxonomy' ) && ( is_product_taxonomy() || is_shop() );
}

/* =====================================================================
 * ۱. تعداد محصول در هر صفحه
 * ---------------------------------------------------------------------
 * قبلا ۲۰۰ محصول در یک صفحه (از تنظیم «ردیف در هر صفحه» ووکامرس):
 * هزاران گره DOM، ۲۰۰ تصویر، Core Web Vitals ضعیف روی موبایل، و متن
 * اصلی دسته (توضیحات و FAQ) زیر ۲۰۰ محصول.
 *
 * ۳۶: بر ۶، ۴، ۳ و ۲ ستون شبکه بخش‌پذیر است، پس هر صفحه در همه اندازه‌های
 * صفحه‌نمایش با ردیف کامل تمام می‌شود (۶ ردیف کامل در دسکتاپ). برای ۲۰۰
 * محصول ۶ صفحه — عمق کمتر از ۲۴ تایی (۹ صفحه)، یعنی محصولات با کلیک
 * کمتری از دسته در دسترس خزنده‌اند.
 * ===================================================================== */
add_filter( 'loop_shop_per_page', static function (): int {
	// «تنظیمات قالب ← فروشگاه و دسته‌ها» (پیش‌فرض همان ۳۶)
	$setting = function_exists( 'hodima_setting' ) ? (int) hodima_setting( 'shop_per_page' ) : 36;
	return max( 1, (int) apply_filters( 'hodima_category_per_page', $setting > 0 ? $setting : 36 ) );
}, 20 );

/* =====================================================================
 * ۲. عنوان صفحات ۲ به بعد
 * ---------------------------------------------------------------------
 * سئوباکس (اولویت ۹۹۹۹) شماره صفحه را اضافه نمی‌کند؛ /cat/ و
 * /cat/page/2/ عنوان یکسان داشتند. گوگل با دو صفحه هم‌عنوان و هم‌محتوا
 * گاهی صفحه ۲ را نماینده دسته انتخاب می‌کرد — یکی از علت‌های محتمل
 * نوسان رتبه.
 * ===================================================================== */
add_filter( 'pre_get_document_title', static function ( $title ) {

	$paged = (int) get_query_var( 'paged' );

	if ( $paged < 2 || '' === (string) $title || ! hodima_is_product_archive() ) {
		return $title;
	}

	return $title . ' - صفحه ' . $paged;
}, 10000 );

/* =====================================================================
 * ۳. اسکیمای رسانه فقط در صفحه اول
 * ---------------------------------------------------------------------
 * FAQ، صوت و ویدیوی دسته (media-system) روی همه صفحات صفحه‌بندی چاپ
 * می‌شدند: یک FAQPage یکسان روی /cat/، /cat/page/2/، /cat/page/3/ …
 * قالب هم این بخش‌ها را حالا فقط در صفحه اول نمایش می‌دهد؛ اسکیما باید
 * با محتوای قابل‌مشاهده یکی باشد.
 * ===================================================================== */
add_action( 'wp', static function (): void {
	if ( hodima_is_product_archive() && (int) get_query_var( 'paged' ) > 1 ) {
		remove_action( 'wp_head', 'hook_auto_inject_head_schema' );
	}
} );

/* =====================================================================
 * ۳.۱ هوک woocommerce_shop_loop_header (ووکامرس ۸.۶ به بعد)
 * ---------------------------------------------------------------------
 * قالب‌های فروشگاه و دسته حالا این هوک را صدا می‌زنند تا افزونه‌هایی که
 * بالای فهرست محصولات چیزی اضافه می‌کنند (فیلتر، بنر) کار کنند. خروجی
 * پیش‌فرض ووکامرس روی این هوک (عنوان و توضیح دسته) حذف می‌شود، چون قالب
 * عنوان و توضیح اختصاصی خودش را دارد و وگرنه دوبار چاپ می‌شد.
 * ===================================================================== */
remove_action( 'woocommerce_shop_loop_header', 'woocommerce_product_taxonomy_archive_header', 10 );

/* =====================================================================
 * ۴. صفحه‌بندی: اعداد انگلیسی
 * ===================================================================== */
add_filter( 'woocommerce_pagination_args', static function ( array $args ): array {
	return array_merge( $args, [
		'mid_size'           => 1,
		'end_size'           => 1,
		'prev_text' => '<span aria-hidden="true">&lsaquo;</span> قبلی',
		'next_text' => 'بعدی <span aria-hidden="true">&rsaquo;</span>',
		// عدد در عنصر جدا تا CSS فونت لاتین برایش بگذارد
		'before_page_number' => '<span class="hodima-num">',
		'after_page_number'  => '</span>',
	] );
} );

/**
 * رقم‌های فارسی خروجی صفحه‌بندی → انگلیسی.
 * paginate_links() شماره‌ها را با number_format_i18n() می‌سازد که با
 * زبان فارسی رقم فارسی برمی‌گرداند.
 */
add_filter( 'paginate_links_output', static function ( $html ) {

	if ( ! hodima_is_product_archive() ) {
		return $html;
	}

	return strtr( (string) $html, [
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	] );
} );

/**
 * آیتم «صفحه N» مسیر راهنمای ووکامرس هم با رقم انگلیسی.
 */
add_filter( 'woocommerce_get_breadcrumb', static function ( $crumbs ) {

	$paged = (int) get_query_var( 'paged' );

	if ( $paged < 2 || ! hodima_is_product_archive() || empty( $crumbs ) ) {
		return $crumbs;
	}

	$last = count( $crumbs ) - 1;
	if ( isset( $crumbs[ $last ][0] ) ) {
		// فقط ارقام؛ متن خود ووکامرس («برگه» / «صفحه») دست‌نخورده می‌ماند
		$crumbs[ $last ][0] = strtr( (string) $crumbs[ $last ][0], [
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		] );
	}

	return $crumbs;
} );
