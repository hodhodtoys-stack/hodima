<?php
/**
 * نوع پست «ویدیو» و ستون‌های آمار فهرست ویدیوها (پیشخوان)
 * Path: components/video-watch/post-type.php
 *
 * نامک «video» و آدرس ‎/video/… عوض نشوند. فهرست ویدیوها یک برگه است (قالب
 * template-page-videos.php)، پس آرشیو نوع پست خاموش است (hodima-media.php هم
 * با فیلتر register_post_type_args همین را می‌گذارد؛ تا Media 2.1.3 اینجا
 * «videos» بود و فقط آن فیلتر خاموشش می‌کرد).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function (): void {
		register_post_type(
			'video',
			[
				'labels'       => [
					'name'          => __( 'ویدئوها', 'hodima' ),
					'singular_name' => __( 'ویدئو', 'hodima' ),
					'menu_name'     => __( 'ویدئوها', 'hodima' ),
					'add_new'       => __( 'افزودن ویدئو', 'hodima' ),
					'add_new_item'  => __( 'افزودن ویدئو جدید', 'hodima' ),
					'edit_item'     => __( 'ویرایش ویدئو', 'hodima' ),
					'new_item'      => __( 'ویدئو جدید', 'hodima' ),
					'view_item'     => __( 'مشاهده ویدئو', 'hodima' ),
					'search_items'  => __( 'جستجوی ویدئو', 'hodima' ),
					'not_found'     => __( 'ویدئویی پیدا نشد', 'hodima' ),
					'all_items'     => __( 'همه ویدئوها', 'hodima' ),
				],
				'public'       => true,
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'video' ],
				'menu_icon'    => 'dashicons-video-alt3',
				'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
				'show_in_rest' => true,
				'hierarchical' => false,
			]
		);
	}
);

/**
 * آمار ردیف‌های فهرست ویدیوها در یک بار (نه یک کوئری برای هر ردیف و ستون).
 *
 * تا Media 2.1.3 فقط جدول آمار خوانده می‌شد و بازدیدهای صف (حداکثر ۵ دقیقه
 * اخیر) در ستون نبود؛ حالا همان جمع صفحه ویدیو و اسکیما (get_total_views).
 */
final class Hodima_Video_Watch_Admin_Stats {

	/** @var array<int, array{views: int, shares: int}> */
	private static array $rows = [];

	/** @param list<int> $post_ids شناسه ویدیوها. */
	public static function prime( array $post_ids ): void {
		$post_ids = array_values( array_diff( array_map( 'intval', $post_ids ), array_keys( self::$rows ) ) );
		if ( ! $post_ids ) {
			return;
		}

		foreach ( $post_ids as $id ) {
			self::$rows[ $id ] = [
				'views'  => 0,
				'shares' => 0,
			];
		}

		global $wpdb;
		$in = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $in فقط %d است
		$stats = $wpdb->get_results( $wpdb->prepare( 'SELECT video_id, views, shares FROM %i WHERE video_id IN (' . $in . ')', $wpdb->prefix . 'video_watch_stats', ...$post_ids ), ARRAY_A );
		$queue = $wpdb->get_results( $wpdb->prepare( 'SELECT video_id, counter_type, SUM(delta) AS delta FROM %i WHERE video_id IN (' . $in . ') GROUP BY video_id, counter_type', $wpdb->prefix . 'video_watch_counter_queue', ...$post_ids ), ARRAY_A );
		// phpcs:enable

		foreach ( (array) $stats as $row ) {
			self::$rows[ (int) $row['video_id'] ]['views']  += (int) $row['views'];
			self::$rows[ (int) $row['video_id'] ]['shares'] += (int) $row['shares'];
		}
		foreach ( (array) $queue as $row ) {
			$field = 'shares' === $row['counter_type'] ? 'shares' : ( 'views' === $row['counter_type'] ? 'views' : '' );
			if ( '' !== $field && isset( self::$rows[ (int) $row['video_id'] ] ) ) {
				self::$rows[ (int) $row['video_id'] ][ $field ] += (int) $row['delta'];
			}
		}
	}

	/** @param 'views'|'shares' $field ستون. */
	public static function get( int $post_id, string $field ): int {
		if ( ! isset( self::$rows[ $post_id ] ) ) {
			self::prime( [ $post_id ] );
		}
		return self::$rows[ $post_id ][ $field ] ?? 0;
	}
}

add_filter(
	'manage_video_posts_columns',
	/**
	 * @param array<string, string> $columns ستون‌ها.
	 * @return array<string, string>
	 */
	static function ( array $columns ): array {
		$new_columns = [];
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'title' === $key ) {
				$new_columns['video_views']  = __( 'تعداد بازدید', 'hodima' );
				$new_columns['video_shares'] = __( 'تعداد اشتراک', 'hodima' );
			}
		}
		return $new_columns;
	}
);

add_filter(
	'the_posts',
	/**
	 * @param array<int, WP_Post|int> $posts نوشته‌های صفحه فهرست.
	 * @return array<int, WP_Post|int>
	 */
	static function ( array $posts, WP_Query $query ): array {
		if ( is_admin() && $query->is_main_query() && 'video' === $query->get( 'post_type' ) && $posts ) {
			Hodima_Video_Watch_Admin_Stats::prime( array_map( static fn( WP_Post|int $p ): int => $p instanceof WP_Post ? $p->ID : (int) $p, array_values( $posts ) ) );
		}
		return $posts;
	},
	10,
	2
);

add_action(
	'manage_video_posts_custom_column',
	static function ( string $column, int $post_id ): void {
		if ( 'video_views' === $column || 'video_shares' === $column ) {
			echo esc_html( number_format_i18n( Hodima_Video_Watch_Admin_Stats::get( $post_id, 'video_views' === $column ? 'views' : 'shares' ) ) );
		}
	},
	10,
	2
);

add_filter(
	'manage_edit-video_sortable_columns',
	/**
	 * @param array<string, string> $columns ستون‌های مرتب‌شدنی.
	 * @return array<string, string>
	 */
	static function ( array $columns ): array {
		$columns['video_views']  = 'video_views';
		$columns['video_shares'] = 'video_shares';
		return $columns;
	}
);

add_filter(
	'posts_clauses',
	/**
	 * مرتب‌سازی فهرست بر اساس بازدید/اشتراک (جدول آمار؛ صف چند دقیقه‌ای در ترتیب اثر ندارد).
	 *
	 * @param array<string, string> $clauses بخش‌های کوئری.
	 * @return array<string, string>
	 */
	static function ( array $clauses, WP_Query $query ): array {
		if ( ! is_admin() || ! $query->is_main_query() || 'video' !== $query->get( 'post_type' ) ) {
			return $clauses;
		}

		$orderby = (string) $query->get( 'orderby' );
		if ( ! in_array( $orderby, [ 'video_views', 'video_shares' ], true ) ) {
			return $clauses;
		}

		global $wpdb;
		$column = 'video_views' === $orderby ? 'views' : 'shares';
		$order  = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';

		$clauses['join']   .= ' LEFT JOIN ' . esc_sql( $wpdb->prefix . 'video_watch_stats' ) . " hs_stats ON {$wpdb->posts}.ID = hs_stats.video_id ";
		$clauses['orderby'] = "COALESCE(hs_stats.{$column}, 0) {$order}";
		return $clauses;
	},
	10,
	2
);
