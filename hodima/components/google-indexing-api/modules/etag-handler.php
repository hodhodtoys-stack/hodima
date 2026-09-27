<?php
/**
 * Conditional GET (ETag)
 * Path: components/google-indexing-api/modules/etag-handler.php
 *
 * ─────────────────────────────────────────────────────────────────────
 * چرا محدود شد
 * ─────────────────────────────────────────────────────────────────────
 * نسخه قبلی روی هر صفحه برای کاربر مهمان این را می‌فرستاد:
 *
 *     Cache-Control: public, max-age=3600
 *
 * یعنی مرورگر و هر پراکسی میانی صفحه را یک ساعت *بدون پرسیدن از سرور*
 * نگه می‌داشت — حتی بعد از اینکه لایت‌اسپید آن را پاک کرده بود.
 *
 * و ETag فقط از post_modified ساخته می‌شد. تغییر موجودی با یک سفارش،
 * post_modified محصول را عوض نمی‌کند؛ پس ETag ثابت می‌ماند و سرور تا
 * ابد «۳۰۴ تغییری نکرده» برمی‌گرداند — به کاربر و به گوگل‌بات — در
 * حالی که صفحه «ناموجود» شده بود. nonceهای جاسازی‌شده در صفحه (افزودن
 * به سبد، پنل کاربری) هم با همان نسخه کهنه منقضی می‌شدند.
 *
 * حالا:
 *   - وقتی لایت‌اسپید فعال است کاملا کنار می‌کشد؛ لایت‌اسپید درخواست
 *     شرطی را همراه با پاکسازی دقیق خودش مدیریت می‌کند.
 *   - در غیر این صورت هرگز max-age نمی‌فرستد (no-cache = همیشه بپرس)،
 *     و صفحات ووکامرس را که محتوایشان خارج از post_modified تغییر
 *     می‌کند پوشش نمی‌دهد.
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_ETag_Handler {

	private static ?self $instance = null;

	private function __construct() {
		add_action( 'wp', [ $this, 'process_etag_headers' ], 5 );
	}

	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	public function process_etag_headers(): void {

		if ( ! apply_filters( 'hodima_gi_etag_enabled', ! Hodima_GI_Helper::litespeed_active() ) ) {
			return;
		}

		if ( is_admin() || is_user_logged_in() || headers_sent() ) {
			return;
		}

		if ( 'GET' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			return;
		}

		// صفحات ووکامرس: قیمت و موجودی خارج از post_modified تغییر می‌کنند
		if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
			return;
		}

		$etag = $this->generate_current_etag();
		if ( null === $etag ) {
			return;
		}

		$client = trim( str_replace( 'W/', '', (string) ( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) ) );

		header( 'ETag: ' . $etag );
		header( 'Cache-Control: no-cache' );

		if ( $client === $etag ) {
			status_header( 304 );
			exit;
		}
	}

	private function generate_current_etag(): ?string {

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! ( $post instanceof WP_Post ) ) {
				return null;
			}
			$seed = "post_{$post->ID}_{$post->post_modified_gmt}_{$post->comment_count}";

		} elseif ( is_category() || is_tag() || is_tax() ) {

			$term = get_queried_object();
			if ( ! ( $term instanceof WP_Term ) ) {
				return null;
			}

			$cache_key = 'hodima_etag_term_' . $term->term_taxonomy_id;
			$latest    = get_transient( $cache_key );

			if ( false === $latest ) {
				global $wpdb;
				$latest = (string) ( $wpdb->get_var( $wpdb->prepare(
					"SELECT MAX(p.post_modified_gmt) FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					 WHERE tr.term_taxonomy_id = %d AND p.post_status = 'publish'",
					$term->term_taxonomy_id
				) ) ?: '0' );
				set_transient( $cache_key, $latest, HOUR_IN_SECONDS );
			}

			$seed = "term_{$term->term_id}_{$term->count}_{$latest}_" . get_query_var( 'paged' );

		} else {
			return null;
		}

		// نسخه قالب هم در ETag است تا تغییر قالب همه نسخه‌های قدیمی را باطل کند
		$seed .= '_' . ( defined( 'hodima_VERSION' ) ? hodima_VERSION : '' );

		return '"' . md5( $seed ) . '"';
	}
}

Hodima_ETag_Handler::get_instance();
