<?php
/**
 * خواندن سفارش‌های مشتری برای نماهای پنل.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * کوئری‌های خواندنی سفارش‌ها (فقط خواندن؛ نوشتن در Orders).
 *
 * تا 0.2.4 هر نما (داشبورد، سفارش‌ها، فرم سفارش) کوئری‌هایش را خودش می‌نوشت: منطق و نما
 * قاطی، بی آزمون جدا، و هر بهینه‌سازی باید در چند فایل تکرار می‌شد. هیچ فهرستی بی سقف نیست.
 */
final class Order_Query {

	/**
	 * فیلترهای فهرست «سفارش‌ها»: کلید => [برچسب، وضعیت‌ها].
	 *
	 * @return array<string, array{0: string, 1: list<string>}>
	 */
	public static function filters(): array {
		return [
			''       => [ 'همه', [] ],
			'wait'   => [ 'منتظر شما', [ 'awaiting_payment' ] ],
			'review' => [ 'در بررسی', [ 'pending', 'receipt_uploaded' ] ],
			'done'   => [ 'تکمیل یا لغو', [ 'completed', 'cancelled' ] ],
		];
	}

	/**
	 * شمار سفارش‌های یک مشتری در هر وضعیت.
	 *
	 * @param int $user_id مشتری.
	 * @return array<string, int>
	 */
	public static function status_counts( int $user_id ): array {
		global $wpdb;
		$out = [];
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i WHERE user_id = %d GROUP BY status', table( 'orders' ), $user_id ) ) as $row ) {
			$out[ (string) $row->status ] = (int) $row->n;
		}
		return $out;
	}

	/**
	 * آخرین سفارش‌ها.
	 *
	 * @param int $user_id مشتری.
	 * @param int $limit   تعداد.
	 * @return list<object>
	 */
	public static function recent( int $user_id, int $limit ): array {
		global $wpdb;
		return array_values( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, status, total_price, created_at FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT %d', table( 'orders' ), $user_id, $limit ) ) );
	}

	/**
	 * قدیمی‌ترین سفارشِ منتظر پرداخت مشتری (کار بعدی داشبورد).
	 *
	 * @param int $user_id مشتری.
	 */
	public static function oldest_awaiting( int $user_id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, total_price FROM %i WHERE user_id = %d AND status = 'awaiting_payment' ORDER BY id ASC LIMIT 1", table( 'orders' ), $user_id ) );
		return is_object( $row ) ? $row : null;
	}

	/**
	 * یک صفحه از سفارش‌های مشتری.
	 *
	 * @param int          $user_id  مشتری.
	 * @param list<string> $statuses فقط این وضعیت‌ها (خالی = همه).
	 * @param int          $per      تعداد در صفحه.
	 * @param int          $paged    شماره صفحه.
	 * @return list<object>
	 */
	public static function page( int $user_id, array $statuses, int $per, int $paged ): array {
		global $wpdb;
		$offset = ( max( 1, $paged ) - 1 ) * $per;
		if ( ! $statuses ) {
			return array_values( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', table( 'orders' ), $user_id, $per, $offset ) ) );
		}
		$in  = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$sql = "SELECT * FROM %i WHERE user_id = %d AND status IN ($in) ORDER BY id DESC LIMIT %d OFFSET %d";
		return array_values( (array) $wpdb->get_results( $wpdb->prepare( $sql, ...[ table( 'orders' ), $user_id, ...$statuses, $per, $offset ] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $in فقط %s است.
	}

	/**
	 * اقلام چند سفارش، دسته‌بندی‌شده با شناسه سفارش؛ نام محصولات با یک کوئری آماده می‌شود.
	 *
	 * @param int[] $order_ids شناسه‌ها.
	 * @return array<int, list<object>> order_id => ردیف‌ها (product_id، quantity، sale_unit، unit_capacity).
	 */
	public static function items( array $order_ids ): array {
		global $wpdb;
		$order_ids = array_values( array_filter( array_map( 'intval', $order_ids ) ) );
		if ( ! $order_ids ) {
			return [];
		}
		$in   = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$sql  = "SELECT order_id, product_id, quantity, sale_unit, unit_capacity FROM %i WHERE order_id IN ($in) ORDER BY id";
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, ...[ table( 'order_items' ), ...$order_ids ] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $in فقط %d است.
		_prime_post_caches( array_map( static fn( object $i ): int => (int) $i->product_id, $rows ), false, false );
		$out = [];
		foreach ( $rows as $row ) {
			$out[ (int) $row->order_id ][] = $row;
		}
		return $out;
	}

	/**
	 * تعداد هر محصول یک سفارش (فرم ویرایش).
	 *
	 * @param int $order_id سفارش.
	 * @return array<int, float> شناسه محصول => تعداد.
	 */
	public static function quantities( int $order_id ): array {
		$out = [];
		foreach ( self::items( [ $order_id ] )[ $order_id ] ?? [] as $item ) {
			$out[ (int) $item->product_id ] = (float) $item->quantity;
		}
		return $out;
	}

	/**
	 * پیام‌های نخوانده گفتگوی چند سفارش مشتری.
	 *
	 * @param int   $user_id   مشتری.
	 * @param int[] $order_ids شناسه‌ها.
	 * @return array<int, int> order_id => نخوانده.
	 */
	public static function chat_unread( int $user_id, array $order_ids ): array {
		global $wpdb;
		$order_ids = array_values( array_filter( array_map( 'intval', $order_ids ) ) );
		if ( ! $order_ids ) {
			return [];
		}
		$in  = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$sql = "SELECT order_id, user_unread FROM %i WHERE user_id = %d AND order_id IN ($in)";
		$out = [];
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, ...[ table( 'chats' ), $user_id, ...$order_ids ] ) ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $in فقط %d است.
			$out[ (int) $row->order_id ] = (int) $row->user_unread;
		}
		return $out;
	}

	/**
	 * محصولات فرم سفارش: فعال برای نوع، سفارش‌پذیر (Orders::price_of)، با عکس آماده.
	 *
	 * سقف Orders::FORM_MAX_PRODUCTS؛ اقلام سفارش در حال ویرایش که بیرون از سقف افتاده‌اند جدا
	 * افزوده می‌شوند تا با ذخیره حذف نشوند.
	 *
	 * @param string $type     prebuy|preinvoice.
	 * @param int[]  $keep_ids محصولاتی که اگر فعال‌اند حتما باشند (اقلام سفارش در حال ویرایش).
	 * @return array{posts: list<\WP_Post>, prices: array<int, float>}
	 */
	public static function form_products( string $type, array $keep_ids ): array {
		if ( ! wc_active() ) {
			return [ 'posts' => [], 'prices' => [] ];
		}
		$query = new \WP_Query(
			[
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => Orders::FORM_MAX_PRODUCTS,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false, // فرم دسته/برچسب نمی‌خواند
				'meta_query'             => [ [ 'key' => '_' . $type . '_enabled', 'value' => 'yes' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			]
		);
		$posts = array_values( array_filter( $query->posts, static fn( mixed $p ): bool => $p instanceof \WP_Post ) );
		$have  = array_map( static fn( \WP_Post $p ): int => (int) $p->ID, $posts );
		foreach ( array_diff( array_map( 'intval', $keep_ids ), $have ) as $pid ) {
			$post = get_post( $pid );
			if ( $post instanceof \WP_Post && 'product' === $post->post_type && 'publish' === $post->post_status && 'yes' === get_post_meta( $post->ID, '_' . $type . '_enabled', true ) ) {
				$posts[] = $post;
			}
		}
		// عکس همه کارت‌ها با یک کوئری (تا 0.2.3 یک کوئری پیوست برای هر محصول)
		update_post_thumbnail_cache( $query );
		$prices = [];
		foreach ( $posts as $i => $post ) {
			$price = Orders::price_of( wc_get_product( $post->ID ) );
			if ( null === $price ) {
				unset( $posts[ $i ] ); // فقط سفارش‌پذیر؛ همان قاعده Orders::validate_items
				continue;
			}
			$prices[ (int) $post->ID ] = $price;
		}
		return [ 'posts' => array_values( $posts ), 'prices' => $prices ];
	}

	/**
	 * سفارش‌های مشتری برای «پیگیری سفارش» (تازه‌ترین بالا).
	 *
	 * @param int $user_id مشتری.
	 * @return list<object>
	 */
	public static function for_topic( int $user_id ): array {
		global $wpdb;
		return array_values( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, status FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT 100', table( 'orders' ), $user_id ) ) );
	}
}
