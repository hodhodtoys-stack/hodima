<?php
/**
 * ماژول «دستار سئو» — منبع‌های لینک برای «لینک‌های این کلمه»
 * Path: core/advisor/advisor-links-sources.php
 *
 * advisor-links.php هر لینک را از این منبع‌ها می‌گیرد و فقط مقصدشان را با روتر حل می‌کند. هر عنصر («ورودی»):
 *   source  {label, title, field, edit, ref, path}  جایی که لینک ذخیره شده (path = آدرس صفحه میزبان، اگر یک صفحه است)
 *   store   متن، توضیح دسته، لینک‌های مرتبط، منو یا تنظیمات (برای ستون «جای ذخیره»)
 *   bucket  text | related | menu | settings (برای شمارش «لینک‌های ورودی»)
 *   anchor  متن لینک
 *   href    آدرس نوشته‌شده، یا key = «نوع:شناسه» وقتی لینک شناسه‌ای است (منوی صفحه/دسته، لینک مرتبط)
 *   hit     متن لینک کلمه را دارد؟
 *
 * فقط لینک‌هایی نگه داشته می‌شوند که «متنشان کلمه را دارد» یا «آدرسشان نامک یکی از صفحه‌های گروه را دارد»؛ پس
 * شمار «لینک‌های ورودی هر صفحه» کامل است (حتی لینک بدون کلمه) و هزینه حل مقصد کم می‌ماند. همه‌چیز فقط خواندنی است.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** حداکثر ردیف خوانده‌شده از متای لینک‌های مرتبط و حداکثر پیشنهاد «فرصت لینک». */
const HODIMA_SEO_ADVISOR_LINKS_MAX_RELATED = 600;
const HODIMA_SEO_ADVISOR_LINKS_MAX_OPPS    = 30;

/** نام فارسی جایگاه لینک در خزش. */
function hodima_seo_advisor_links_position_label( string $position ): string {
	return match ( strtolower( $position ) ) {
		'content'    => 'متن (طبق خزش)',
		'navigation' => 'منو (طبق خزش)',
		'footer'     => 'فوتر',
		'sidebar'    => 'کنار صفحه',
		'header'     => 'سربرگ',
		default      => 'قالب',
	};
}

/**
 * آیا آدرس نوشته‌شده یکی از نامک‌ها را به‌عنوان یک بخش مسیر دارد؟ (پیش‌فیلتر ارزان پیش از حل مقصد با روتر؛
 * «/207/» با «/20700/» یکی نمی‌شود.)
 *
 * @param list<string> $slugs نامک‌های یکسان‌شده (حروف کوچک، رمزگشایی‌شده)
 */
function hodima_seo_advisor_links_href_has_slug( string $href, array $slugs ): bool {

	$href = strtolower( rawurldecode( $href ) );

	foreach ( $slugs as $slug ) {
		if ( '' !== $slug && 1 === preg_match( '#(?:^|/)' . preg_quote( $slug, '#' ) . '(?:/|$|\?|\#)#u', $href ) ) {
			return true;
		}
	}

	return false;
}

/**
 * الگوهای LIKE برای پیش‌فیلتر SQL: بلندترین واژه کلمه (با املای عربی ی/ک هم) و نامک‌های صفحه‌های گروه.
 *
 * @param list<string> $slugs
 * @return list<string>
 */
function hodima_seo_advisor_links_like( string $term, array $slugs ): array {

	global $wpdb;

	$likes  = [];
	$tokens = array_filter( explode( ' ', hodima_seo_advisor_normalize( $term ) ), static fn( string $t ): bool => mb_strlen( $t ) >= 2 );

	if ( $tokens ) {
		usort( $tokens, static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a ) );
		$likes[] = '%' . $wpdb->esc_like( $tokens[0] ) . '%';
		$likes[] = '%' . $wpdb->esc_like( strtr( $tokens[0], [ 'ی' => 'ي', 'ک' => 'ك' ] ) ) . '%';
	}

	foreach ( $slugs as $slug ) {
		if ( '' !== $slug ) {
			$likes[] = '%' . $wpdb->esc_like( $slug ) . '%';
			$likes[] = '%' . $wpdb->esc_like( rawurlencode( $slug ) ) . '%'; // آدرس فارسی کدگذاری‌شده در متن
		}
	}

	return array_values( array_unique( $likes ) );
}

/**
 * آدرس یکسان‌شده صفحه میزبان یک نوشته/ترم (برای حذف تکراری‌ها بین پایگاه داده و خزش).
 */
function hodima_seo_advisor_links_object_path( string $kind, int $id ): string {

	static $cache = [];

	$key = $kind . ':' . $id;

	if ( ! isset( $cache[ $key ] ) ) {
		$url = '';
		if ( 'term' === $kind ) {
			$link = get_term_link( $id );
			$url  = is_string( $link ) ? $link : '';
		} else {
			$url = (string) get_permalink( $id );
		}
		$cache[ $key ] = '' !== $url ? (string) hodima_seo_advisor_path_key( $url ) : '';
	}

	return $cache[ $key ];
}

/**
 * ورودی‌های لینک از متن نوشته‌ها (محتوا و خلاصه)، توضیح دسته‌ها، منوها و تنظیمات قالب.
 *
 * @param array{term: string, slugs: list<string>, keys: list<string>} $ctx
 * @return array{entries: list<array<string, mixed>>, capped: bool}
 */
function hodima_seo_advisor_links_collect_html( array $ctx ): array {

	global $wpdb;

	$entries = [];
	$capped  = false;
	$term    = $ctx['term'];
	$likes   = hodima_seo_advisor_links_like( $term, $ctx['slugs'] );
	$has     = static fn( string $anchor ): bool => hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( $anchor ), $term );
	$keep    = static fn( string $anchor, string $href ): bool => $has( $anchor ) || hodima_seo_advisor_links_href_has_slug( $href, $ctx['slugs'] );
	$push    = static function ( array $source, string $store, string $bucket, array $link ) use ( &$entries, $has, $keep ): void {
		if ( $keep( $link['anchor'], $link['href'] ) ) {
			$entries[] = [ 'source' => $source, 'store' => $store, 'bucket' => $bucket, 'anchor' => $link['anchor'], 'href' => $link['href'], 'hit' => $has( $link['anchor'] ) ];
		}
	};

	if ( ! $likes ) {
		return [ 'entries' => [], 'capped' => false ];
	}

	// ۱. نوشته‌ها، برگه‌ها، محصول‌ها (متن و خلاصه)
	$types = function_exists( 'hodima_router_post_types' ) ? hodima_router_post_types() : array_values( array_filter( [ 'post', 'page', 'product' ], 'post_type_exists' ) );

	if ( $types ) {
		$last    = 0;
		$scanned = 0;
		$in      = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		while ( $scanned < HODIMA_SEO_ADVISOR_LINKS_MAX_POSTS ) {
			$conditions = [];
			$args       = [ $wpdb->posts, 'publish', ...$types, $last ];
			foreach ( [ 'post_content', 'post_excerpt' ] as $column ) {
				foreach ( $likes as $like ) {
					$conditions[] = $column . ' LIKE %s';
					$args[]       = $like;
				}
			}
			$args[] = HODIMA_SEO_ADVISOR_LINKS_CHUNK;

			$posts = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- placeholderها با array_fill و شمار الگوها هم‌اندازه‌اند؛ نام ستون از فهرست ثابت
					'SELECT ID, post_type, post_title, post_content, post_excerpt FROM %i WHERE post_status = %s AND post_type IN (' . $in . ') AND ID > %d AND (' . implode( ' OR ', $conditions ) . ') ORDER BY ID ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- شرط‌ها و IN فقط placeholder و نام ستون ثابت‌اند
					...$args
				),
				ARRAY_A
			);

			foreach ( $posts as $post ) {
				$last = (int) $post['ID'];
				++$scanned;
				$title  = trim( wp_strip_all_tags( (string) $post['post_title'] ) );
				$source = [
					'label' => hodima_seo_advisor_links_label( (string) $post['post_type'] ),
					'title' => '' !== $title ? $title : '#' . $post['ID'],
					'edit'  => (string) get_edit_post_link( (int) $post['ID'], 'raw' ),
					'ref'   => 'post:' . $post['ID'],
					'path'  => hodima_seo_advisor_links_object_path( 'post', (int) $post['ID'] ),
				];
				foreach ( [ 'post_content' => 'متن نوشته', 'post_excerpt' => 'product' === $post['post_type'] ? 'توضیح کوتاه' : 'خلاصه' ] as $column => $store ) {
					foreach ( hodima_seo_advisor_links_anchors( (string) $post[ $column ] ) as $link ) {
						$push( $source + [ 'field' => $store ], $store, 'text', $link );
					}
				}
			}

			if ( count( $posts ) < HODIMA_SEO_ADVISOR_LINKS_CHUNK ) {
				break;
			}
		}

		$capped = $scanned >= HODIMA_SEO_ADVISOR_LINKS_MAX_POSTS;
	}

	// ۲. توضیح دسته‌ها
	$taxonomies = function_exists( 'hodima_router_taxonomies' ) ? hodima_router_taxonomies() : array_values( array_filter( [ 'product_cat', 'category' ], 'taxonomy_exists' ) );

	if ( $taxonomies ) {
		$conditions = implode( ' OR ', array_fill( 0, count( $likes ), 'tt.description LIKE %s' ) );
		$rows       = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- placeholderها با array_fill هم‌اندازه‌اند
				'SELECT t.term_id, t.name, tt.taxonomy, tt.description FROM %i tt INNER JOIN %i t ON t.term_id = tt.term_id WHERE tt.taxonomy IN (' . implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) ) . ') AND (' . $conditions . ') LIMIT 300', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- فقط placeholder
				$wpdb->term_taxonomy,
				$wpdb->terms,
				...$taxonomies,
				...$likes
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$source = [
				'label' => hodima_seo_advisor_links_label( (string) $row['taxonomy'] ),
				'title' => (string) $row['name'],
				'field' => 'توضیح دسته',
				'edit'  => (string) get_edit_term_link( (int) $row['term_id'], (string) $row['taxonomy'] ),
				'ref'   => 'term:' . $row['term_id'],
				'path'  => hodima_seo_advisor_links_object_path( 'term', (int) $row['term_id'] ),
			];
			foreach ( hodima_seo_advisor_links_anchors( (string) $row['description'] ) as $link ) {
				$push( $source, 'توضیح دسته', 'text', $link );
			}
		}
	}

	// ۳. منوها (عنوان آیتم = متن لینک؛ آیتم صفحه/دسته مقصدش را مستقیم دارد)
	$items = array_map( 'intval', (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT ID FROM %i WHERE post_type = %s AND post_status = %s LIMIT 800', $wpdb->posts, 'nav_menu_item', 'publish' )
	) );

	if ( $items ) {
		update_meta_cache( 'post', $items );

		foreach ( $items as $id ) {
			$type   = (string) get_post_meta( $id, '_menu_item_type', true );
			$object = (string) get_post_meta( $id, '_menu_item_object', true );
			$ref    = (int) get_post_meta( $id, '_menu_item_object_id', true );
			$title  = trim( (string) get_the_title( $id ) );

			if ( '' === $title && $ref > 0 ) {
				$title = 'taxonomy' === $type ? (string) ( get_term( $ref, $object )->name ?? '' ) : (string) get_the_title( $ref );
			}

			if ( '' === $title ) {
				continue;
			}

			$entry = [ 'anchor' => $title, 'hit' => $has( $title ) ];

			if ( 'custom' === $type ) {
				$entry['href'] = (string) get_post_meta( $id, '_menu_item_url', true );
				if ( ! $entry['hit'] && ! hodima_seo_advisor_links_href_has_slug( $entry['href'], $ctx['slugs'] ) ) {
					continue;
				}
			} elseif ( $ref > 0 && '' !== $object ) {
				$entry['key'] = hodima_seo_advisor_links_key( $object, $ref );
				if ( ! $entry['hit'] && ! in_array( $entry['key'], $ctx['keys'], true ) ) {
					continue;
				}
				$link          = 'taxonomy' === $type ? get_term_link( $ref, $object ) : get_permalink( $ref );
				$entry['path'] = is_string( $link ) ? (string) hodima_seo_advisor_path_key( $link ) : '';
			} else {
				continue;
			}

			$menus = wp_get_object_terms( $id, 'nav_menu' );
			$menu  = is_array( $menus ) && isset( $menus[0] ) && $menus[0] instanceof WP_Term ? $menus[0] : null;

			$entries[] = $entry + [
				'source' => [ 'label' => 'منو', 'title' => ( null !== $menu ? $menu->name . ' ← ' : '' ) . $title, 'field' => 'آیتم منو', 'edit' => null !== $menu ? admin_url( 'nav-menus.php?action=edit&menu=' . $menu->term_id ) : admin_url( 'nav-menus.php' ), 'ref' => 'menu:' . $id, 'path' => '' ],
				'store'  => 'منو',
				'bucket' => 'menu',
			];
		}
	}

	// ۴. تنظیمات قالب (فقط فیلدهای متنی که خودشان لینک دارند)
	if ( function_exists( 'hodima_settings' ) && function_exists( 'hodima_settings_fields' ) ) {
		$fields = (array) hodima_settings_fields();
		$edit   = function_exists( 'hodima_settings_url' ) ? (string) hodima_settings_url() : '';
		foreach ( (array) hodima_settings() as $key => $value ) {
			if ( ! is_string( $value ) || ! in_array( (string) ( $fields[ $key ]['type'] ?? '' ), [ 'text', 'textarea' ], true ) ) {
				continue;
			}
			$source = [ 'label' => 'تنظیمات قالب', 'title' => (string) ( $fields[ $key ]['label'] ?? $key ), 'field' => (string) $key, 'edit' => $edit, 'ref' => 'setting:' . $key, 'path' => '' ];
			foreach ( hodima_seo_advisor_links_anchors( $value ) as $link ) {
				$push( $source, 'تنظیمات قالب', 'settings', $link );
			}
		}
	}

	return [ 'entries' => $entries, 'capped' => $capped ];
}

/**
 * ورودی‌های «لینک‌های مرتبط دستی» (متای _hodima_rl_groups نوشته/محصول/دسته). این لینک‌ها آدرس ندارند؛
 * فقط شناسه مقصد و عنوان دلخواه ذخیره می‌شود، پس با متن نوشته دیده نمی‌شوند.
 *
 * @param array{term: string, slugs: list<string>, keys: list<string>, ids: list<int>} $ctx
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_links_collect_related( array $ctx ): array {

	global $wpdb;

	$meta = '_hodima_rl_groups';
	$like = [];

	foreach ( $ctx['ids'] as $id ) {
		$like[] = '%' . $wpdb->esc_like( 's:2:"id";i:' . $id . ';' ) . '%'; // مقدار سریال‌شده: s:2:"id";i:55;
	}
	foreach ( $ctx['slugs'] as $slug ) {
		if ( '' !== $slug ) {
			$like[] = '%' . $wpdb->esc_like( $slug ) . '%'; // آیتم از نوع «آدرس»
		}
	}

	if ( ! $like ) {
		return [];
	}

	$has     = static fn( string $anchor ): bool => hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( $anchor ), $ctx['term'] );
	$entries = [];
	$sql     = implode( ' OR ', array_fill( 0, count( $like ), 'meta_value LIKE %s' ) );

	foreach ( [ 'post' => [ $wpdb->postmeta, 'post_id' ], 'term' => [ $wpdb->termmeta, 'term_id' ] ] as $context => [ $table, $column ] ) {

		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- placeholderها با array_fill هم‌اندازه‌اند؛ نام ستون از فهرست ثابت
				'SELECT ' . $column . ' AS owner, meta_value FROM %i WHERE meta_key = %s AND (' . $sql . ') LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- فقط placeholder
				...[ $table, $meta, ...$like, HODIMA_SEO_ADVISOR_LINKS_MAX_RELATED ]
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$groups = maybe_unserialize( (string) $row['meta_value'] );
			$owner  = (int) $row['owner'];

			if ( ! is_array( $groups ) || $owner <= 0 ) {
				continue;
			}

			if ( 'term' === $context ) {
				$object = get_term( $owner );
				if ( ! $object instanceof WP_Term ) {
					continue;
				}
				$source = [ 'label' => hodima_seo_advisor_links_label( $object->taxonomy ), 'title' => $object->name, 'edit' => (string) get_edit_term_link( $owner, $object->taxonomy ), 'ref' => 'term:' . $owner, 'path' => hodima_seo_advisor_links_object_path( 'term', $owner ) ];
			} else {
				$object = get_post( $owner );
				if ( ! $object instanceof WP_Post || 'publish' !== $object->post_status ) {
					continue;
				}
				$source = [ 'label' => hodima_seo_advisor_links_label( $object->post_type ), 'title' => trim( wp_strip_all_tags( $object->post_title ) ) ?: '#' . $owner, 'edit' => (string) get_edit_post_link( $owner, 'raw' ), 'ref' => 'post:' . $owner, 'path' => hodima_seo_advisor_links_object_path( 'post', $owner ) ];
			}

			foreach ( [ 'products', 'article' ] as $group ) { // گروه «categories» حذف‌شده است و در سایت نمایش داده نمی‌شود
				foreach ( (array) ( $groups[ $group ] ?? [] ) as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}

					$kind  = (string) ( $item['kind'] ?? '' );
					$id    = (int) ( $item['id'] ?? 0 );
					$title = trim( (string) ( $item['title'] ?? '' ) );
					$entry = [ 'source' => $source + [ 'field' => 'لینک‌های مرتبط' ], 'store' => 'لینک‌های مرتبط', 'bucket' => 'related' ];

					if ( 'post' === $kind && $id > 0 ) {
						$type = get_post_type( $id );
						if ( false === $type ) {
							continue;
						}
						$entry['key']  = hodima_seo_advisor_links_key( $type, $id );
						$entry['path'] = hodima_seo_advisor_links_object_path( 'post', $id );
						$title         = '' !== $title ? $title : trim( wp_strip_all_tags( get_the_title( $id ) ) );
					} elseif ( 'term' === $kind && $id > 0 ) {
						$target = get_term( $id );
						if ( ! $target instanceof WP_Term ) {
							continue;
						}
						$entry['key']  = hodima_seo_advisor_links_key( $target->taxonomy, $id );
						$entry['path'] = hodima_seo_advisor_links_object_path( 'term', $id );
						$title         = '' !== $title ? $title : $target->name;
					} elseif ( 'url' === $kind && '' !== trim( (string) ( $item['url'] ?? '' ) ) ) {
						$entry['href'] = trim( (string) $item['url'] );
					} else {
						continue;
					}

					$entry['anchor'] = $title;
					$entry['hit']    = '' !== $title && $has( $title );
					$entries[]       = $entry;
				}
			}
		}
	}

	return $entries;
}

/**
 * لینک‌های خزش (Screaming Frog: جدول crawl_links) به این صفحه‌ها: شمار هر صفحه به تفکیک جایگاه، و ردیف لینک‌هایی که
 * متنشان کلمه را دارد. خزش لینک‌های ساختاری قالب (منوی بزرگ، فوتر، کارت زیردسته‌ها) را هم می‌بیند که در پایگاه داده نیستند.
 *
 * @param list<array<string, mixed>> $infos صفحه‌های گروه (hodima_seo_advisor_links_page_info)
 * @return array{has: bool, pages: array<string, array{total: int, content: int, other: int}>, rows: list<array<string, mixed>>}
 */
function hodima_seo_advisor_links_collect_crawl( array $infos, string $term ): array {

	global $wpdb;

	$out   = [ 'has' => false, 'pages' => [], 'rows' => [] ];
	$table = hodima_seo_advisor_table( 'crawl_links' );
	$paths = array_column( $infos, 'path' );

	if ( ! $paths ) {
		return $out;
	}

	$marks = implode( ',', array_fill( 0, count( $paths ), '%s' ) );

	foreach ( (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- placeholderها با array_fill هم‌اندازه‌اند
			'SELECT dest_path, link_position, COUNT(DISTINCT source_path) AS n FROM %i WHERE dest_path IN (' . $marks . ') GROUP BY dest_path, link_position', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- فقط placeholder
			$table,
			...$paths
		),
		ARRAY_A
	) as $r ) {
		$bucket                          = 'content' === $r['link_position'] ? 'content' : 'other';
		$out['pages'][ $r['dest_path'] ] ??= [ 'total' => 0, 'content' => 0, 'other' => 0 ];
		$out['pages'][ $r['dest_path'] ][ $bucket ] += (int) $r['n'];
		$out['pages'][ $r['dest_path'] ]['total']   += (int) $r['n'];
		$out['has']                                  = true;
	}

	if ( ! $out['has'] ) {
		return $out;
	}

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- placeholderها با array_fill هم‌اندازه‌اند
			'SELECT source_path, dest_path, link_position, anchor FROM %i WHERE dest_path IN (' . $marks . ") AND anchor <> '' AND is_follow = 1 LIMIT 4000", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- فقط placeholder
			$table,
			...$paths
		),
		ARRAY_A
	);

	foreach ( $rows as $r ) {
		if ( hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( (string) $r['anchor'] ), $term ) ) {
			$out['rows'][] = [ 'source_path' => (string) $r['source_path'], 'dest_path' => (string) $r['dest_path'], 'position' => (string) $r['link_position'], 'anchor' => (string) $r['anchor'] ];
		}
	}

	return $out;
}

/**
 * جمله رابطه دو صفحه (والد و فرزند دسته، محصول داخل دسته). null = رابطه‌ای نیست.
 *
 * @param array<string, mixed> $target
 * @param array<string, mixed> $rival
 * @return array{type: string, text: string}|null
 */
function hodima_seo_advisor_links_relation( array $target, array $rival, string $term ): ?array {

	$t_kind = (string) $target['kind'];
	$r_kind = (string) $rival['kind'];
	$t_id   = (int) $target['id'];
	$r_id   = (int) $rival['id'];

	if ( $t_id <= 0 || $r_id <= 0 || '' === $t_kind || '' === $r_kind ) {
		return null;
	}

	$t_title = (string) $target['title'];
	$r_title = (string) $rival['title'];

	if ( taxonomy_exists( $t_kind ) && $t_kind === $r_kind ) {
		if ( in_array( $r_id, array_map( 'intval', get_ancestors( $t_id, $t_kind, 'taxonomy' ) ), true ) ) {
			return [ 'type' => 'parent', 'text' => sprintf( '«%1$s» دسته والد «%2$s» است. گوگل صفحه والد را برای کلمه کلی‌تر نشان می‌دهد، پس این دو دقیقا رقیب نیستند. پیشنهاد: عنوان و متن دسته والد را برای کلمه کلی‌ترش نگه دارید و ویژگی دقیق‌تر را از آن‌ها کم کنید؛ از دسته والد با متن «%3$s» به «%2$s» لینک بدهید.', $r_title, $t_title, $term ) ];
		}
		if ( in_array( $t_id, array_map( 'intval', get_ancestors( $r_id, $r_kind, 'taxonomy' ) ), true ) ) {
			return [ 'type' => 'child', 'text' => sprintf( '«%1$s» زیردسته «%2$s» است؛ رقابت بین والد و فرزند طبیعی است. از زیردسته با متن «%3$s» به «%2$s» لینک بدهید و عنوان زیردسته را برای کلمه دقیق‌تر خودش نگه دارید.', $r_title, $t_title, $term ) ];
		}
		$t_term = get_term( $t_id, $t_kind );
		$r_term = get_term( $r_id, $r_kind );
		if ( $t_term instanceof WP_Term && $r_term instanceof WP_Term && $t_term->parent > 0 && $t_term->parent === $r_term->parent ) {
			return [ 'type' => 'sibling', 'text' => sprintf( 'هر دو زیردسته یک دسته والدند. موضوع «%1$s» و «%2$s» را از هم جدا کنید و فقط برای «%3$s» با «%2$s» رقابت کنید.', $r_title, $t_title, $term ) ];
		}
		return null;
	}

	$children = static fn( int $id, string $taxonomy ): array => array_merge( [ $id ], array_map( 'intval', (array) get_term_children( $id, $taxonomy ) ) );

	if ( taxonomy_exists( $t_kind ) && post_type_exists( $r_kind ) && is_object_in_term( $r_id, $t_kind, $children( $t_id, $t_kind ) ) ) {
		return [ 'type' => 'in_target', 'text' => sprintf( '«%1$s» در دسته «%2$s» است. برای کلمه کلی صفحه دسته مناسب‌تر است: از محصول با متن «%3$s» به دسته لینک بدهید و کلمه را از عنوان محصول بردارید.', $r_title, $t_title, $term ) ];
	}

	if ( taxonomy_exists( $r_kind ) && post_type_exists( $t_kind ) && is_object_in_term( $t_id, $r_kind, $children( $r_id, $r_kind ) ) ) {
		return [ 'type' => 'target_in', 'text' => sprintf( 'دسته «%1$s» شامل «%2$s» است. اگر «%2$s» باید برای «%3$s» دیده شود، از دسته با متن «%3$s» به آن لینک بدهید؛ وگرنه صفحه دسته را اصلی کنید.', $r_title, $t_title, $term ) ];
	}

	return null;
}

/**
 * تکه‌ای از متن که کلمه در آن آمده: [قبل، خود عبارت، بعد] (متن ساده، escape نشده). عبارت کامل را می‌جوید و اگر نبود
 * بلندترین واژه را؛ املای ی/ک و نیم‌فاصله یکی گرفته می‌شود.
 *
 * @return array{0: string, 1: string, 2: string}|null
 */
function hodima_seo_advisor_links_snippet( string $html, string $term, int $context = 60 ): ?array {

	$text  = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( strip_shortcodes( $html ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	$words = array_values( array_filter( preg_split( '/\s+/u', trim( $term ) ) ?: [], static fn( string $w ): bool => mb_strlen( $w ) >= 2 ) );

	if ( '' === $text || ! $words ) {
		return null;
	}

	$letter = static function ( string $word ): string {
		$out = '';
		foreach ( mb_str_split( hodima_seo_advisor_normalize( $word ) ) as $ch ) {
			$out .= match ( $ch ) {
				'ی' => '[یي]',
				'ک' => '[کك]',
				default => preg_quote( $ch, '/' ),
			};
		}
		return $out;
	};

	$phrase = implode( '[\s\x{200C}]+', array_map( $letter, $words ) );
	$longest = $words;
	usort( $longest, static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a ) );

	foreach ( [ $phrase, $letter( $longest[0] ) ] as $pattern ) {
		if ( 1 === preg_match( '/' . $pattern . '/u', $text, $m, PREG_OFFSET_CAPTURE ) ) {
			$start  = mb_strlen( substr( $text, 0, (int) $m[0][1] ) );
			$length = mb_strlen( $m[0][0] );
			$from   = max( 0, $start - $context );
			return [
				( $from > 0 ? '… ' : '' ) . mb_substr( $text, $from, $start - $from ),
				mb_substr( $text, $start, $length ),
				mb_substr( $text, $start + $length, $context ) . ( $start + $length + $context < mb_strlen( $text ) ? ' …' : '' ),
			];
		}
	}

	return null;
}
