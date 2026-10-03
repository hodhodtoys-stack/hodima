<?php
/**
 * Hodima Router — آدرس تمیز (بدون /product/، /product-category/ و /category/)
 * Path: core/router/router.php
 *
 * نقطه ورود واحد (ماژول «router» در hodima-seo.php). فایل‌ها:
 *   helpers.php     پایه‌ها، مسیر، مرز با قانون‌های وردپرس، اندپوینت‌ها
 *   resolver.php    مسیر → شیء، با یک آدرس اصلی برای هر شیء؛ API عمومی
 *   permalinks.php  ساخت لینک‌های بدون پایه
 *   request.php     فیلتر request (فقط آدرس‌هایی که وردپرس خودش نشناخته)
 *   canonical.php   ۳۰۱ به آدرس اصلی
 *   slug-guard.php  جلوگیری از نامک تکراری بین انواع + گزارش تداخل‌ها
 *   admin.php       «ابزارهای هدیما ← آدرس تمیز»
 *   legacy.php      نام‌های قدیمی arian_*
 *
 * API برای ماژول‌های دیگر (همیشه با function_exists):
 *   hodima_router_resolve_url( $url, $loose )  آدرس → شیء
 *   hodima_router_clean_url( $url )            حذف پایه از آدرس
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_ROUTER_DIR = __DIR__;

require_once HODIMA_ROUTER_DIR . '/helpers.php';
require_once HODIMA_ROUTER_DIR . '/resolver.php';
require_once HODIMA_ROUTER_DIR . '/permalinks.php';
require_once HODIMA_ROUTER_DIR . '/request.php';
require_once HODIMA_ROUTER_DIR . '/canonical.php';
require_once HODIMA_ROUTER_DIR . '/slug-guard.php';
require_once HODIMA_ROUTER_DIR . '/legacy.php';

if ( is_admin() ) {
	require_once HODIMA_ROUTER_DIR . '/admin.php';
}

add_filter( 'url_to_postid', 'hodima_router_url_to_postid', 5 );

/**
 * url_to_postid() وردپرس آدرس بدون پایه محصول را نمی‌شناخت (۰) و برای نامک
 * مشترک، نوشته را به جای محصولی که آدرس واقعا نشان می‌دهد برمی‌گرداند.
 * آدرس حل‌شده به شکل ?p=ID به وردپرس داده می‌شود که خودش شناسه را برمی‌گرداند.
 *
 * @param string $url
 */
function hodima_router_url_to_postid( $url ) {

	static $busy = false;

	if ( $busy || ! is_string( $url ) || preg_match( '/[?&](p|page_id|attachment_id)=\d/', $url ) ) {
		return $url;
	}

	$busy = true;
	$hit  = hodima_router_resolve_url( $url );
	$busy = false;

	return null !== $hit && 'post' === $hit['kind'] ? add_query_arg( 'p', $hit['id'], home_url( '/' ) ) : $url;
}

/*
 * پاکسازی یک‌باره گزینه‌های نسخه قبلی: arian_router_flushed (روتر هیچ قانون
 * بازنویسی ندارد و flush آن بی‌اثر بود) و arian_router_cache_gen (کش حالا با
 * last_changed خود وردپرس باطل می‌شود).
 */
add_action( 'admin_init', static function (): void {
	if ( false !== get_option( 'arian_router_flushed' ) ) {
		delete_option( 'arian_router_flushed' );
		delete_option( 'arian_router_cache_gen' );
	}
} );