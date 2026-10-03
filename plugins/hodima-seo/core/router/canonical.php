<?php
/**
 * Hodima Router — یک آدرس برای هر صفحه (ریدایرکت ۳۰۱ به آدرس اصلی)
 * Path: core/router/canonical.php
 *
 * دو حالت:
 *  ۱. روتر درخواست را حل کرده ولی مسیر با آدرس اصلی فرق دارد: پیشوند زباله
 *     (/aaa/گل-سر/)، برگه فرزند در ریشه (/faq/)، دسته تودرتو با شکل دیگر،
 *     حروف بزرگ (/Hair/)، محصول زیر دسته (/hair/103/).
 *  ۲. آدرس قدیمی با پایه (/product/103/، /product-category/hair/، /category/news/)
 *     که وردپرس خودش شناخته است.
 *
 * باگ‌های نسخه قبلی:
 *  - پایه را بدون بررسی وجود مقصد حذف می‌کرد: /product-category/no-such/ → ۳۰۱ به
 *    /no-such/ که ۴۰۴ بود.
 *  - در اولویت ۱ و زودتر از ماژول «ریدایرکت‌ها» اجرا می‌شد؛ قانون دستی برای آدرس
 *    قدیمی پایه‌دار هیچ‌وقت اجرا نمی‌شد. حالا اولویت ۳ است (بعد از ریدایرکت‌ها و
 *    هرس آدرس‌ها، پیش از redirect_canonical وردپرس در ۱۰).
 *  - ادامه آدرس (/product/103/feed/) دور ریخته می‌شد.
 *
 * خاموش کردن: add_filter( 'hodima_router_enforce_canonical', '__return_false' );
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', 'hodima_router_canonical_redirect', 3 );

function hodima_router_canonical_redirect(): void {

	$enabled = (bool) apply_filters( 'arian_router_enforce_path', true ); // نام قدیمی
	if ( ! apply_filters( 'hodima_router_enforce_canonical', $enabled ) ) {
		return;
	}

	if ( ! hodima_router_active() || is_admin() || wp_doing_ajax() || is_404() || is_preview() || is_customize_preview() ) {
		return;
	}

	if ( ! in_array( strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ), [ 'GET', 'HEAD' ], true ) ) {
		return;
	}

	$target = hodima_router_redirect_target();
	if ( '' === $target ) {
		return;
	}

	$path = hodima_router_request_path();
	if ( hodima_router_compare_form( (string) hodima_router_url_path( $target ) ) === hodima_router_compare_form( $path ) ) {
		return; // جلوگیری از حلقه
	}

	$qs = (string) ( $_SERVER['QUERY_STRING'] ?? '' );
	if ( '' !== $qs ) {
		$target .= ( str_contains( $target, '?' ) ? '&' : '?' ) . $qs;
	}

	wp_safe_redirect( $target, 301, 'Hodima Router' );
	exit;
}

/** آدرس مقصد ریدایرکت این درخواست، یا رشته خالی. */
function hodima_router_redirect_target(): string {

	// ۱. حل‌شده توسط روتر
	$match = hodima_router_current();
	if ( null !== $match && isset( $match['object'] ) ) {
		return $match['exact'] ? '' : hodima_router_target_url( $match );
	}

	// ۲. آدرس پایه‌دار قدیمی که وردپرس شناخته است
	$path = hodima_router_request_path();
	if ( '' === $path || ! hodima_router_starts_with_base( $path ) ) {
		return '';
	}

	$object = get_queried_object();
	$router_object = ( $object instanceof WP_Post && 'product' === $object->post_type && is_singular( 'product' ) )
		|| ( $object instanceof WP_Term && in_array( $object->taxonomy, [ 'product_cat', 'category' ], true ) && ( is_category() || is_tax( 'product_cat' ) ) );

	if ( ! $router_object ) {
		return '';
	}

	$url = hodima_router_object_url( $object );
	if ( '' === $url ) {
		return '';
	}

	// پسوند (صفحه‌بندی، فید، صفحه دیدگاه) حفظ می‌شود
	[ , , $suffix ] = hodima_router_split_suffix( array_values( array_filter( explode( '/', $path ), 'strlen' ) ) );

	return hodima_router_target_url( [ 'url' => $url, 'suffix' => $suffix ] );
}

/** آیا مسیر با پایه محصول، دسته محصول یا دسته نوشته شروع می‌شود؟ */
function hodima_router_starts_with_base( string $path ): bool {

	$path = hodima_router_compare_form( $path );

	foreach ( hodima_router_bases() as $base ) {
		$base = hodima_router_compare_form( $base );
		if ( '' !== $base && str_starts_with( $path, $base . '/' ) ) {
			return true;
		}
	}

	return false;
}

add_filter( 'redirect_canonical', 'hodima_router_filter_redirect_canonical', 10, 2 );

/**
 * اگر redirect_canonical وردپرس آدرسی *با* پایه ساخت در حالی که درخواست بدون
 * پایه بود، پایه حذف می‌شود (لینک‌ها از قبل بدون پایه‌اند؛ این فقط محافظ است).
 *
 * @param string|false $redirect_url
 * @param string       $requested_url
 * @return string|false
 */
function hodima_router_filter_redirect_canonical( $redirect_url, $requested_url ) {

	if ( ! is_string( $redirect_url ) || '' === $redirect_url || ! hodima_router_active() ) {
		return $redirect_url;
	}

	$requested_url = (string) $requested_url;

	foreach ( hodima_router_bases() as $base ) {
		$stripped = hodima_router_strip_base( $redirect_url, $base );
		if ( $stripped !== $redirect_url && hodima_router_strip_base( $requested_url, $base ) === $requested_url ) {
			return $stripped;
		}
	}

	return $redirect_url;
}
