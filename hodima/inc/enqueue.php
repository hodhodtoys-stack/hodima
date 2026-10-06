<?php
/**
 * بارگذاری CSS و JS قالب (فقط در صفحه‌هایی که لازم است) و ظاهر فرم نظرات
 * Path: hodima/inc/enqueue.php
 *
 * همه فایل‌ها با hodima_enqueue_asset() (کلاس Hodima_Theme_Asset): آدرس از
 * hodima_URI، نسخه از زمان تغییر فایل. ترتیب فراخوانی‌ها = ترتیب <link>ها در
 * صفحه (توکن‌ها اول)؛ عوضش نکنید.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * نسخه‌دهی امن برای فایل‌های استاتیک.
 * اگر فایل وجود نداشته باشد، به جای PHP Warning ناشی از filemtime()
 * به نسخه قالب برمی‌گردد.
 *
 * @param string $rel_path مسیر نسبی از ریشه قالب، مثلا: assets/css/header.css
 */
function hodima_asset_version( string $rel_path ): string {

	static $cache = [];

	if ( ! isset( $cache[ $rel_path ] ) ) {
		$abs_path             = hodima_DIR . '/' . ltrim( $rel_path, '/' );
		$fallback             = defined( 'hodima_VERSION' ) ? (string) hodima_VERSION : '1.0.0';
		$cache[ $rel_path ] = is_file( $abs_path ) ? (string) filemtime( $abs_path ) : $fallback;
	}

	return $cache[ $rel_path ];
}

/**
 * true اگر فایل استاتیک واقعا روی دیسک وجود داشته باشد.
 */
function hodima_asset_exists( string $rel_path ): bool {
	return is_file( hodima_DIR . '/' . ltrim( $rel_path, '/' ) );
}

/**
 * یک فایل CSS/JS قالب را در صف بارگذاری بگذار (فایل ناموجود رد می‌شود).
 *
 * @param list<string>               $deps
 * @param array<string, string|bool> $args اسکریپت: in_footer/strategy؛ استایل: media.
 */
function hodima_enqueue_asset( string $handle, string $path, array $deps = [], array $args = [] ): bool {
	return ( new Hodima_Theme_Asset( $handle, $path, $deps, $args ) )->enqueue();
}

/**
 * CSS مشترک همه صفحه‌ها، به همین ترتیب (توکن‌ها اول تا متغیرها پیش از بقیه
 * تعریف شده باشند). کلید = handle وردپرس (افزونه/قالب فرزند ممکن است به آن وابسته باشد).
 * bin/build-css.mjs همین فهرست را در ZIP یک فایل می‌کند (HODIMA_COMMON_CSS_BUNDLE).
 */
const HODIMA_COMMON_CSS = [
	'hodima-tokens' => 'assets/css/tokens.css',
	'hodima-style'  => 'style.css',
	'hodima-header' => 'assets/css/header.css',
	'hodima-footer' => 'assets/css/footer.css',
];

/** فایل یکی‌شده CSS مشترک — فقط در ZIP نصبی (ساخته bin/build-css.mjs)، نه در مخزن. */
const HODIMA_COMMON_CSS_BUNDLE = 'assets/css/hodima-common.css';

/**
 * CSS مشترک: نسخه نصبی یک درخواست (hodima-common.css)، سورس مخزن چهار فایل.
 *
 * نوسازی قالب، مرحله ۸ (HODIMA-AUDIT.md بخش ۶۵): قبلا چهار درخواست جدا
 * (توکن‌ها، style.css، هدر، فوتر) در همه صفحه‌ها. با فایل یکی، handleهای
 * قبلی «نام مستعار» بی‌فایل می‌مانند (وابستگی 404.css، رنگ‌های «برند» که
 * با wp_add_inline_style به hodima-tokens می‌چسبند، و هر کد بیرونی).
 * هدر و فوتر از 2.9.5 پیش از CSS هر صفحه‌اند (قبلا بعد از آن) — در هر دو حالت یکسان.
 */
function hodima_enqueue_common_css(): void {

	if ( hodima_enqueue_asset( 'hodima-common', HODIMA_COMMON_CSS_BUNDLE ) ) {
		foreach ( array_keys( HODIMA_COMMON_CSS ) as $handle ) {
			wp_register_style( $handle, false, [ 'hodima-common' ], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- نام مستعار بی‌فایل
			wp_enqueue_style( $handle );
		}
		return;
	}

	foreach ( HODIMA_COMMON_CSS as $handle => $path ) {
		hodima_enqueue_asset( $handle, $path );
	}
}

function hodima_enqueue_scripts(): void {

	// 1. توکن‌ها، استایل اصلی، هدر و فوتر (همه صفحه‌ها)
	hodima_enqueue_common_css();

	/*
	 * صفحات فروشگاهی.
	 * توابع is_shop() و مشابه را ووکامرس تعریف می‌کند؛ بدون گارد، غیرفعال
	 * شدن ووکامرس کل سایت را با Fatal Error از کار می‌انداخت.
	 */
	$wc          = hodima_wc_active();
	$is_product  = $wc && is_product();
	$is_shop     = $wc && is_shop();
	$is_cat_tag  = $wc && ( is_product_category() || is_product_tag() );
	$is_prod_src = is_search() && 'product' === get_query_var( 'post_type' );
	$in_footer   = [ 'in_footer' => true ];

	// 2. کارت محصول (همه صفحات فروشگاهی و نتایج جستجو)
	if ( $is_shop || $is_cat_tag || $is_product || is_search() ) {
		hodima_enqueue_asset( 'hodima-product-card', 'assets/css/product-card.css' );
	}

	// 3. صفحه اصلی فروشگاه
	if ( $is_shop && ! $is_cat_tag ) {
		hodima_enqueue_asset( 'hodima-shop-page', 'assets/css/shop-page.css' );
	}

	// 4. دسته، برچسب، فروشگاه و جستجوی محصول: نوار مرتب‌سازی و صفحه‌بندی مشترک
	// اسکریپت Vanilla JS است و به jQuery وابستگی ندارد.
	if ( $is_cat_tag || $is_shop || $is_prod_src ) {
		hodima_enqueue_asset( 'hodima-taxonomy-cat', 'assets/css/taxonomy-product_cat.css' );
		hodima_enqueue_asset( 'hodima-taxonomy-js', 'assets/js/taxonomy-product_cat.js', [], $in_footer );
	}

	// 5. صفحه محصول
	if ( $is_product ) {
		hodima_enqueue_asset( 'hodima-single-product', 'assets/css/single-product.css' );
		hodima_enqueue_asset( 'hodima-single-product-js', 'assets/js/single-product.js', [], $in_footer );
	}

	// 6. سبد خرید (فایل خالی assets/js/cart-page.js حذف شد: یک درخواست بی‌فایده در هر بار باز شدن سبد)
	if ( $wc && is_cart() ) {
		hodima_enqueue_asset( 'hodima-cart-page', 'assets/css/cart-page.css' );
	}

	/*
	 * 7. استایل اختصاصی صفحه نوشته‌های وبلاگ (Single Post).
	 * برگه‌ها (page.php) هم همان بخش‌های رسانه و دیدگاه را دارند: فقط وقتی
	 * کادر «رسانه» برگه روشن است یا دیدگاه دارد (برگه ساده CSS اضافه نمی‌گیرد).
	 */
	$page_id       = (int) get_queried_object_id();
	$is_media_page = is_page() && ! is_front_page() && (
		( function_exists( 'hodima_media_is_enabled' ) && hodima_media_is_enabled( $page_id, 'post' ) )
		|| comments_open( $page_id )
		|| get_comments_number( $page_id )
	);
	if ( is_singular( 'post' ) || $is_media_page ) {
		hodima_enqueue_asset( 'hodima-single-post', 'assets/css/single-post.css' );
	}

	// 7.1 جستجوی سایت (search.php): کارت‌ها و صفحه‌بندی آرشیو وبلاگ؛ جستجوی محصول قالب فروشگاه است
	if ( is_search() && ! $is_prod_src ) {
		hodima_enqueue_asset( 'hodima-archive-blog', 'assets/css/archive-blog.css' );
	}

	// 8. استایل صفحه ۴۰۴
	if ( is_404() ) {
		hodima_enqueue_asset( 'hodima-404', 'assets/css/404.css', [ 'hodima-tokens' ] );
	}
}
add_action( 'wp_enqueue_scripts', 'hodima_enqueue_scripts', 20 );

/*
 * Placeholder فیلدهای فرم نظرات ووکامرس، بدون دست زدن به ستاره‌های امتیازدهی.
 * نام توابع قبلی بی‌پیشوند بود (custom_review_form_placeholders،
 * add_placeholder_to_comment_textarea) و با هر افزونه‌ای با همان نام «Cannot
 * redeclare» می‌داد؛ حالا hodima_*.
 */

// ─── ۱. فیلدهای نام و ایمیل ───
add_filter( 'woocommerce_product_review_comment_form_args', 'hodima_review_form_placeholders', 20 );

/**
 * @param array<string, mixed> $comment_form
 * @return array<string, mixed>
 */
function hodima_review_form_placeholders( mixed $comment_form ): mixed {

	if ( ! is_array( $comment_form ) ) {
		return $comment_form;
	}

	$commenter = wp_get_current_commenter();

	$comment_form['fields']['author'] = '<p class="comment-form-author">'
		. '<input id="author" name="author" type="text" '
		. 'placeholder="نام *" value="' . esc_attr( $commenter['comment_author'] ) . '" '
		. 'size="30" required /></p>';

	$comment_form['fields']['email'] = '<p class="comment-form-email">'
		. '<input id="email" name="email" type="email" '
		. 'placeholder="ایمیل *" value="' . esc_attr( $commenter['comment_author_email'] ) . '" '
		. 'size="30" required /></p>';

	$comment_form['comment_notes_before'] = '<p class="comment-notes-before">'
		. '<span class="email-notes">نشانی ایمیل شما منتشر نخواهد شد. '
		. 'بخش‌های موردنیاز علامت‌گذاری شده‌اند <span class="required">*</span></span></p>';

	return $comment_form;
}

// ─── ۲. placeholder برای textarea بدون حذف ستاره‌ها ───
add_filter( 'comment_form_field_comment', 'hodima_comment_textarea_placeholder', 20 );

function hodima_comment_textarea_placeholder( mixed $comment_field ): mixed {

	if ( ! is_string( $comment_field ) ) {
		return $comment_field;
	}

	$comment_field = (string) preg_replace( '/<label[^>]*for=["\']comment["\'][^>]*>.*?<\/label>/is', '', $comment_field );

	if ( ! str_contains( $comment_field, 'placeholder=' ) ) {
		$comment_field = str_replace( '<textarea', '<textarea placeholder="دیدگاه شما *"', $comment_field );
	}

	return $comment_field;
}

/* ============================================================
 * Custom Product Gallery (SP Gallery)
 * استایل گالری داخل assets/css/single-product.css است.
 * (نام قبلی تابع: suspended_enqueue_custom_gallery — بی‌پیشوند)
 * ============================================================ */
add_action( 'wp_enqueue_scripts', 'hodima_enqueue_product_gallery', 20 );

function hodima_enqueue_product_gallery(): void {
	if ( hodima_wc_active() && is_product() ) {
		hodima_enqueue_asset( 'sp-gallery-js', 'assets/js/sp-gallery.js', [], [ 'in_footer' => true ] );
	}
}

/* ============================================================
 * منطق منتقل‌شده به افزونه‌ها — بازسازی قالب، مرحله ۲
 * ------------------------------------------------------------
 * این فایل فقط بارگذاری CSS/JS و ظاهر فرم‌ها را دارد. بقیه (که با عوض شدن
 * قالب نباید از بین برود) به افزونه‌ها رفت:
 *   - مرتب‌سازی کاتالوگ (جدیدترین/محبوب‌ترین/ارزان‌ترین/گران‌ترین)
 *       → Hodima Commerce: inc/woocommerce/catalog-sorting.php
 *   - اسکیمای فهرست محصولات فروشگاه و برچسب محصول
 *       → Hodima SEO: schema/collection-lists-schema.php
 *   - ویرایشگر پیشرفته توضیحات دسته محصول و مجوز HTML آن
 *       → Hodima SEO: core/cat-blog/cat-blog.php (ماژول «دسته‌های وبلاگ و محصول»)
 *   - دسته «جدیدترین محصولات» در مسیر راهنما (قبلا نامک ثابت)
 *       → Hodima SEO: schema/breadcrumb-schema.php (تنظیم در «اسکیما ← بردکرامب»)
 *   - «بارگذاری ویدیوی بیشتر» (AJAX بلااستفاده) در نسخه 2.2.3 حذف شد.
 * ============================================================ */

/* ============================================================
 * توکن‌های طراحی در پنل مدیریت
 * ------------------------------------------------------------
 * تا پیش از این هیچ استایل مشترکی در ادمین لود نمی‌شد و هر ماژول
 * رنگ‌ها را جداگانه هاردکد می‌کرد. اولویت ۱ تضمین می‌کند متغیرها
 * قبل از CSS ماژول‌ها تعریف شده باشند.
 * ============================================================ */
add_action( 'admin_enqueue_scripts', 'hodima_enqueue_admin_tokens', 1 );

function hodima_enqueue_admin_tokens(): void {
	hodima_enqueue_asset( 'hodima-tokens', 'assets/css/tokens.css' );
}
