<?php
/**
 * Hodima Router — تحلیل درخواست (فیلتر request)
 * Path: core/router/request.php
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * نتیجه روتر برای درخواست فعلی (برای canonical.php).
 * بدون آرگومان مقدار ذخیره‌شده را برمی‌گرداند.
 */
function hodima_router_current( ?array $match = null ): ?array {
	static $current = null;
	if ( null !== $match ) {
		$current = $match;
	}
	return $current;
}

/** پارامترهایی در آدرس که خودشان شیء را مشخص می‌کنند (?p=، ?s= …)؛ روتر کنار می‌رود. */
function hodima_router_has_identity_query(): bool {
	foreach ( [ 'p', 'page_id', 'attachment_id', 'cat', 'tag', 'name', 'pagename', 'category_name', 'product', 'product_cat', 'product_tag', 'post_type', 's', 'author', 'rest_route' ] as $key ) {
		if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
	}
	return false;
}

/**
 * آیا قانونی که وردپرس برای این آدرس پیدا کرد «عمومی» است (برگه/نامک/پیوست)؟
 * قانون اختصاصی (برچسب، نویسنده، تاریخ، پست‌تایپ سفارشی، سایت‌مپ…) مال روتر نیست.
 */
function hodima_router_rule_is_generic( WP $wp ): bool {

	if ( '' === (string) $wp->matched_query ) {
		return true; // هیچ قانونی پیدا نشد
	}

	$rule_vars = [];
	parse_str( (string) $wp->matched_query, $rule_vars );

	return ! array_diff( array_keys( $rule_vars ), hodima_router_generic_vars() );
}

add_filter( 'request', 'hodima_router_filter_request', 5 );

/**
 * باگ‌های نسخه قبلی که این‌جا رفع شده‌اند:
 *  - روی همه آدرس‌ها اجرا می‌شد و مسیرهایی را که وردپرس درست شناخته بود جایگزین
 *    می‌کرد (/tag/clips/ → دسته محصول، /product-tag/best/ → برگه best).
 *  - همه متغیرهای کوئری جز چند کلید دور ریخته می‌شد؛ ?paged=2 و پارامترهای
 *    عمومی دیگر کار نمی‌کردند.
 *
 * @param array<string, mixed> $query_vars
 * @return array<string, mixed>
 */
function hodima_router_filter_request( $query_vars ) {

	$query_vars = (array) $query_vars;

	if ( ! hodima_router_active() || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $query_vars;
	}

	if ( hodima_router_has_identity_query() || isset( $query_vars['s'] ) || isset( $query_vars['rest_route'] ) || isset( $query_vars['sitemap'] ) ) {
		return $query_vars;
	}

	global $wp;
	if ( ! ( $wp instanceof WP ) || ! hodima_router_rule_is_generic( $wp ) ) {
		return $query_vars;
	}

	$path = hodima_router_request_path();
	if ( '' === $path || hodima_router_path_is_reserved( $path ) ) {
		return $query_vars;
	}

	$match = hodima_router_match( $path );
	if ( null === $match ) {
		return $query_vars;
	}

	// متغیرهایی که از رشته پرس‌وجو آمده‌اند (?paged=2، ?orderby=… ، پیش‌نمایش) حفظ می‌شوند
	$from_query = array_diff_key( array_intersect_key( $query_vars, $_GET + $_POST ), [ 'error' => 1 ] ); // phpcs:ignore WordPress.Security.NonceVerification

	if ( isset( $match['error'] ) ) {
		return $from_query + [ 'error' => '404' ];
	}

	hodima_router_current( $match );

	return array_merge( $from_query, $match['vars'] );
}
