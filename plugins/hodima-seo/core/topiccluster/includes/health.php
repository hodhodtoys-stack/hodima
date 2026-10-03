<?php
/**
 * خوشه موضوعی — یتیم‌ها و گزارش سلامت خوشه‌ها
 * Path: core/topiccluster/includes/health.php
 *
 * یتیم خوشه‌ای: گره‌ای که نه پیلار است، نه «خارج از خوشه» و نه والد مؤثر
 * دارد (با همان قاعده سایت: والد دستی معتبر یا والد خودکار).
 * باگ نسخه قبلی: وجود ردیف _hodima_pillar_id کافی بود؛ اگر والد حذف،
 * پیش‌نویس یا زباله شده بود، صفحه «دارای والد» حساب می‌شد و یتیم‌ها کمتر
 * از واقعیت شمرده می‌شدند. برچسب‌ها هم با اینکه عضو خوشه‌بندی‌اند در
 * گزارش نبودند.
 *
 * یتیم لینکی (جدید): صفحه‌ای که از متن هیچ صفحه دیگری لینک نگرفته
 * (فهرست links.php) — تعریف رایج «صفحه یتیم» در سئو.
 *
 * گزارش سلامت هر پیلار: noindex، بدون/کم فرزند، کادر نمایش داده نمی‌شود،
 * فرزندی که در متنش به پیلار لینک نداده، محتوای قدیمی یا کم‌حجم، و
 * عنوان‌های بسیار شبیه در یک خوشه (رقابت دو صفحه روی یک کلمه کلیدی).
 */

declare(strict_types=1);

namespace Hodima\TopicCluster;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Health {

	private const TTL       = 12 * HOUR_IN_SECONDS;
	private const MAX_SCAN  = 20000;

	/** شدت هر مشکل: error (قرمز)، warn، info. */
	public const LEVELS = [
		'pillar_noindex'    => 'error',
		'no_children'       => 'warn',
		'few_children'      => 'info',
		'pillar_no_box'     => 'warn',
		'child_noindex'     => 'warn',
		'no_path_to_pillar' => 'error',
		'no_link_to_pillar' => 'info',
		'stale'             => 'info',
		'thin'              => 'warn',
		'similar_titles'    => 'warn',
	];

	public static function init(): void {
		// فعلا چیزی برای ثبت نیست؛ کش‌ها با شماره نسل Graph باطل می‌شوند.
	}

	public static function label( string $code ): string {
		return match ( $code ) {
			'pillar_noindex'    => 'خود پیلار noindex است؛ هسته خوشه در گوگل نیست',
			'no_children'       => 'پیلار هیچ زیرمجموعه‌ای ندارد',
			'few_children'      => 'کمتر از ۳ زیرمجموعه (خوشه کوچک)',
			'pillar_no_box'     => 'کادر خوشه روی صفحه پیلار نمایش داده نمی‌شود (شورت‌کد یا نمایش خودکار لازم است)',
			'child_noindex'     => 'زیرمجموعه noindex',
			'no_path_to_pillar' => 'نه در متن به پیلار لینک داده و نه کادر خوشه دارد؛ هیچ لینکی به پیلار نمی‌دهد',
			'no_link_to_pillar' => 'در متن به پیلار لینک نداده (فقط کادر خوشه)',
			'stale'             => 'مدت زیادی به‌روزرسانی نشده',
			'thin'              => 'محتوای کم‌حجم',
			'similar_titles'    => 'عنوان بسیار شبیه به صفحه دیگری در همین خوشه (رقابت روی یک کلمه کلیدی)',
			default             => $code,
		};
	}

	/* =================================================================
	 * یتیم‌ها
	 * ================================================================= */

	/**
	 * همه یتیم‌های خوشه‌ای (کش با شماره نسل).
	 *
	 * @return list<array{key:string, kind:string, id:int, type:string, title:string}>
	 */
	public static function orphans(): array {

		$key    = 'hodima_tc_orphans_' . Graph::gen();
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$orphans = [];

		// نوشته‌ها: نامزد = منتشرشده، نه پیلار، نه خارج از خوشه
		$types = Graph::post_types();
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID, p.post_type, p.post_title
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pil ON pil.post_id = p.ID AND pil.meta_key = %s AND pil.meta_value = '1'
			 LEFT JOIN {$wpdb->postmeta} exc ON exc.post_id = p.ID AND exc.meta_key = %s AND exc.meta_value = '1'
			 WHERE p.post_status = 'publish' AND p.post_type IN ({$in})
			   AND pil.meta_id IS NULL AND exc.meta_id IS NULL
			 ORDER BY p.post_modified DESC
			 LIMIT %d",
			...array_merge( [ Graph::META_PILLAR, Graph::META_EXCLUDE ], $types, [ self::MAX_SCAN ] )
		) );

		foreach ( array_chunk( (array) $rows, 300 ) as $chunk ) {

			_prime_post_caches( array_map( static fn( $r ): int => (int) $r->ID, $chunk ), true, true );

			foreach ( $chunk as $row ) {
				if ( ! Graph::parents( Ref::post( (int) $row->ID ) ) ) {
					$orphans[] = [ 'key' => 'post:' . (int) $row->ID, 'kind' => 'post', 'id' => (int) $row->ID, 'type' => (string) $row->post_type, 'title' => (string) $row->post_title ];
				}
			}

			Graph::reset_memo();
		}

		$terms = get_terms( [
			'taxonomy'               => array_values( array_filter( Graph::taxonomies(), 'taxonomy_exists' ) ),
			'hide_empty'             => false,
			'number'                 => self::MAX_SCAN,
			'orderby'                => 'name',
			'update_term_meta_cache' => true,
		] );

		foreach ( is_array( $terms ) ? $terms : [] as $term ) {
			$ref = Ref::term( (int) $term->term_id );
			if ( Graph::is_pillar( $ref ) || Graph::is_excluded( $ref ) || Graph::parents( $ref ) ) {
				continue;
			}
			$orphans[] = [ 'key' => $ref->key(), 'kind' => 'term', 'id' => (int) $term->term_id, 'type' => (string) $term->taxonomy, 'title' => (string) $term->name ];
		}

		set_transient( $key, $orphans, self::TTL );
		set_transient( 'hodima_tc_orphan_count', count( $orphans ), self::TTL );

		return $orphans;
	}

	public static function orphan_count(): int {
		$count = get_transient( 'hodima_tc_orphan_count' );
		return false === $count ? count( self::orphans() ) : (int) $count;
	}

	/**
	 * صفحه‌هایی که از متن هیچ صفحه دیگری لینک نگرفته‌اند (یتیم لینکی).
	 *
	 * @return array{rows: list<array{key:string, kind:string, id:int, type:string, title:string}>, total:int}
	 */
	public static function unlinked( int $page = 1, int $per_page = 30, string $type = '' ): array {

		global $wpdb;

		if ( ! Links::ready() ) {
			return [ 'rows' => [], 'total' => 0 ];
		}

		$table = Links::table();
		$types = '' !== $type && in_array( $type, Graph::post_types(), true ) ? [ $type ] : Graph::post_types();
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$front = (int) get_option( 'page_on_front' );

		$where = $wpdb->prepare(
			"FROM {$wpdb->posts} p
			 LEFT JOIN {$table} l ON l.target_kind = 'post' AND l.target_id = p.ID
			 WHERE p.post_status = 'publish' AND p.post_type IN ({$in}) AND p.ID <> %d AND l.id IS NULL",
			...array_merge( $types, [ $front ] )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $where آماده شده بالا
		$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) {$where}" );
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_type, p.post_title {$where} ORDER BY p.post_date DESC LIMIT %d OFFSET %d",
			$per_page,
			max( 0, ( $page - 1 ) * $per_page )
		) );
		// phpcs:enable

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = [ 'key' => 'post:' . (int) $row->ID, 'kind' => 'post', 'id' => (int) $row->ID, 'type' => (string) $row->post_type, 'title' => (string) $row->post_title ];
		}

		return [ 'rows' => $out, 'total' => $total ];
	}

	/* =================================================================
	 * گزارش سلامت
	 * ================================================================= */

	/**
	 * @return array{clusters: list<array<string, mixed>>, invalid: list<array<string, mixed>>, totals: array<string, int>}
	 */
	public static function report(): array {

		$key    = 'hodima_tc_health_' . Graph::gen() . '_' . ( Links::complete() ? 'l' : 'n' );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$clusters = [];
		$totals   = array_fill_keys( array_keys( self::LEVELS ), 0 );
		$links    = Links::complete();
		$stale    = time() - (int) Settings::get( 'stale_months' ) * MONTH_IN_SECONDS;
		$thin     = (int) Settings::get( 'thin_words' );

		foreach ( Graph::pillars() as $pillar ) {

			$node = Graph::node( $pillar );
			if ( null === $node ) {
				continue;
			}

			$children = Graph::children( $pillar );
			$issues   = [];
			$rows     = [];

			if ( $node['noindex'] ) {
				$issues[] = 'pillar_noindex';
			}
			if ( ! $children ) {
				$issues[] = 'no_children';
			} elseif ( count( $children ) < 3 ) {
				$issues[] = 'few_children';
			}
			if ( $children && ! Render::has_box( $pillar ) ) {
				$issues[] = 'pillar_no_box';
			}

			foreach ( $children as $child ) {

				$ref  = new Ref( Kind::from( $child['kind'] ), (int) $child['id'] );
				$list = [];

				if ( $child['noindex'] ) {
					$list[] = 'child_noindex';
				}

				if ( $ref->is_post() ) {

					if ( $links && ! Links::links_to( $ref, $pillar ) ) {
						$list[] = Render::has_box( $ref ) ? 'no_link_to_pillar' : 'no_path_to_pillar';
					}

					if ( $child['modified'] > 0 && $child['modified'] < $stale ) {
						$list[] = 'stale';
					}

					if ( $thin > 0 && self::word_count( $ref->id ) < $thin ) {
						$list[] = 'thin';
					}
				}

				$rows[ $child['key'] ] = [ 'node' => $child, 'issues' => $list ];
			}

			// عنوان‌های بسیار شبیه (پیلار + فرزندان)
			$all = array_merge( [ $node ], $children );
			for ( $i = 0, $n = count( $all ); $i < $n; $i++ ) {
				for ( $j = $i + 1; $j < $n; $j++ ) {
					if ( self::similar( $all[ $i ]['title'], $all[ $j ]['title'] ) ) {
						foreach ( [ $all[ $i ], $all[ $j ] ] as $hit ) {
							if ( isset( $rows[ $hit['key'] ] ) ) {
								$rows[ $hit['key'] ]['issues'][] = 'similar_titles';
								$rows[ $hit['key'] ]['similar']  = $hit === $all[ $i ] ? $all[ $j ]['title'] : $all[ $i ]['title'];
							} elseif ( $hit['key'] === $node['key'] ) {
								$issues[] = 'similar_titles';
							}
						}
					}
				}
			}

			$issues = array_values( array_unique( $issues ) );
			foreach ( $issues as $code ) {
				++$totals[ $code ];
			}
			foreach ( $rows as &$row ) {
				$row['issues'] = array_values( array_unique( $row['issues'] ) );
				foreach ( $row['issues'] as $code ) {
					++$totals[ $code ];
				}
			}
			unset( $row );

			$clusters[] = [
				'pillar'   => $node,
				'issues'   => $issues,
				'children' => array_values( $rows ),
				'score'    => self::score( $issues, $rows ),
			];
		}

		usort( $clusters, static fn( array $a, array $b ): int => $a['score'] <=> $b['score'] );

		$report = [ 'clusters' => $clusters, 'invalid' => self::invalid_parents(), 'totals' => $totals ];

		set_transient( $key, $report, self::TTL );

		return $report;
	}

	/**
	 * امتیاز ۰ تا ۱۰۰ (برای مرتب‌سازی: بدترین خوشه اول).
	 *
	 * @param list<string>                     $issues
	 * @param array<string, array<string, mixed>> $rows
	 */
	private static function score( array $issues, array $rows ): int {

		$weight = [ 'error' => 25, 'warn' => 10, 'info' => 3 ];
		$score  = 100;

		foreach ( $issues as $code ) {
			$score -= $weight[ self::LEVELS[ $code ] ?? 'info' ];
		}

		$child_penalty = 0;
		foreach ( $rows as $row ) {
			foreach ( $row['issues'] as $code ) {
				$child_penalty += (int) ( $weight[ self::LEVELS[ $code ] ?? 'info' ] / 2 );
			}
		}

		return max( 0, $score - min( 60, $child_penalty ) );
	}

	/**
	 * ردیف‌های والد دستی نامعتبر در کل سایت (حذف‌شده، از نوع نادرست،
	 * پیلارنشده، حلقه) — از جمله باقی‌مانده احتمالی مهاجرت ناقص نسخه ۳.
	 *
	 * @return list<array{child: array<string, mixed>, parent_id:int, parent_kind:string, problem:string}>
	 */
	public static function invalid_parents(): array {

		global $wpdb;

		$out = [];

		foreach ( [ 'post' => $wpdb->postmeta, 'term' => $wpdb->termmeta ] as $type => $table ) {

			$id_col = 'post' === $type ? 'post_id' : 'term_id';
			$rows   = $wpdb->get_results( $wpdb->prepare(
				"SELECT {$id_col} AS object_id, meta_key, meta_value FROM {$table} WHERE meta_key IN (%s, %s) LIMIT 5000",
				Graph::META_PARENT,
				Graph::META_PARENT_POST
			) );

			foreach ( (array) $rows as $row ) {

				$child = new Ref( Kind::from( $type ), (int) $row->object_id );
				$node  = Graph::node( $child );
				if ( null === $node || ! Graph::is_member( $child ) ) {
					continue; // مبدأ منتشرنشده/حذف‌شده: مهم نیست
				}

				$parent = Graph::META_PARENT_POST === $row->meta_key
					? Ref::post( (int) $row->meta_value )
					: new Ref( Graph::parent_kind( $child ), (int) $row->meta_value );

				$problem = Graph::parent_problem( $child, $parent );
				if ( '' === $problem && Graph::would_cycle( $child, $parent ) ) {
					$problem = 'cycle';
				}

				if ( '' !== $problem ) {
					$out[] = [ 'child' => $node, 'parent_id' => $parent->id, 'parent_kind' => $parent->kind->value, 'problem' => $problem ];
				}
			}
		}

		return $out;
	}

	/** تعداد کلمه متن نوشته (بدون شورت‌کد و HTML؛ برای فارسی هم درست). */
	public static function word_count( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return 0;
		}
		$text = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$text = str_replace( "\u{200C}", '', $text ); // نیم‌فاصله بخشی از کلمه است
		$words = preg_split( '/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	/** دو عنوان «تقریبا یکی»؟ (بدون علائم، نیم‌فاصله و فاصله‌های اضافه) */
	public static function similar( string $a, string $b ): bool {

		$norm = static function ( string $s ): string {
			$s = str_replace( [ "\u{200C}", 'ي', 'ك' ], [ ' ', 'ی', 'ک' ], mb_strtolower( $s ) );
			$s = (string) preg_replace( '/[\p{P}\p{S}]+/u', ' ', $s );
			return trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
		};

		$a = $norm( $a );
		$b = $norm( $b );

		if ( '' === $a || '' === $b ) {
			return false;
		}
		if ( $a === $b ) {
			return true;
		}

		similar_text( $a, $b, $percent );
		return $percent >= 88.0;
	}

	/** برچسب فارسی نوع یک گره. */
	public static function type_label( string $kind, string $type ): string {

		if ( 'term' === $kind ) {
			$tax = get_taxonomy( $type );
			return match ( $type ) {
				'category'    => 'دسته مقالات',
				'product_cat' => 'دسته محصولات',
				'post_tag'    => 'برچسب',
				default       => $tax ? (string) $tax->labels->singular_name : $type,
			};
		}

		$obj = get_post_type_object( $type );
		return match ( $type ) {
			'post'    => 'نوشته',
			'page'    => 'برگه',
			'product' => 'محصول',
			default   => $obj ? (string) $obj->labels->singular_name : $type,
		};
	}

	/** پیوند ویرایش یک گره. */
	public static function edit_link( string $kind, int $id ): string {
		if ( 'term' === $kind ) {
			$term = get_term( $id );
			return $term instanceof WP_Term ? (string) get_edit_term_link( $term->term_id, $term->taxonomy ) : '';
		}
		return (string) get_edit_post_link( $id, 'raw' );
	}
}
