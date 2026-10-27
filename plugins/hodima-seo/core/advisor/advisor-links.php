<?php
/**
 * ماژول «دستار سئو» — لینک‌های داخلی یک گروه رقابت
 * Path: core/advisor/advisor-links.php
 *
 * کارت رقابت می‌گفت «رقیب ۱۷ لینک با متن کلمه دارد؛ منتقل کنید» ولی نمی‌گفت کدام نوشته. این فایل برای یک گروه
 * («کلمه + برنده + رقیب‌ها») متن نوشته‌ها، توضیح دسته‌ها، منوها و تنظیمات قالب را می‌گردد و هر لینکی را که
 * متنش کلمه را دارد در سه دسته می‌گذارد:
 *   wrong    به رقیب می‌رود (مستقیم یا با ریدایرکت) ← باید به برنده برود
 *   broken   مقصدش ۴۰۴ یا مرده است
 *   missing  کلمه در متن هست ولی به برنده لینک ندارد (hodima_seo_advisor_link_sources)
 * لینک درست به برنده فقط شمرده می‌شود (good).
 *
 * مقصد هر لینک با روتر آدرس تمیز به «نوع:شناسه» شیء تبدیل می‌شود (hodima_router_resolve_url: آدرس با پایه قدیمی،
 * /product/x/، حروف بزرگ، فارسی کدگذاری‌شده و نامک عددی مثل /207/ همه به همان شیء می‌رسند)، نه مقایسه رشته؛
 * پس «/207/» با «/2207/» قاطی نمی‌شود. بدون روتر، فقط مقایسه آدرس یکسان‌شده می‌ماند.
 *
 * فقط می‌خواند؛ چیزی در سایت عوض نمی‌شود (مدیر خودش لینک را اصلاح می‌کند). با دکمه «بررسی لینک‌ها» و
 * admin-ajax اجرا می‌شود (نه در کرون)، نتیجه ۱۰ دقیقه کش می‌ماند. طرح تأییدشده: Artifact «طرح لینک‌های رقابت».
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** حداکثر نوشته‌ای که در یک بررسی خوانده می‌شود، اندازه هر دسته خواندن، حداکثر ردیف نتیجه و آدرس یکتای بررسی‌شده. */
const HODIMA_SEO_ADVISOR_LINKS_MAX_POSTS = 1500;
const HODIMA_SEO_ADVISOR_LINKS_CHUNK     = 150;
const HODIMA_SEO_ADVISOR_LINKS_MAX_ROWS  = 80;
const HODIMA_SEO_ADVISOR_LINKS_MAX_HREFS = 500;

/** عمر کش نتیجه (ثانیه). */
const HODIMA_SEO_ADVISOR_LINKS_TTL = 600;

add_action( 'wp_ajax_hodima_advisor_links', 'hodima_seo_advisor_handle_links_ajax' );

/**
 * لینک‌های یک متن HTML با متن لینک. متن لینک بدون تگ و فاصله اضافه است.
 *
 * @return list<array{href: string, anchor: string}>
 */
function hodima_seo_advisor_links_anchors( string $html ): array {

	if ( '' === trim( $html ) || ! str_contains( strtolower( $html ), '<a' ) ) {
		return [];
	}

	if ( ! preg_match_all( '/<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/isu', $html, $matches, PREG_SET_ORDER ) ) {
		return [];
	}

	$out = [];
	foreach ( $matches as $m ) {
		$anchor = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $m[3] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$href   = trim( html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' !== $anchor && '' !== $href ) {
			$out[] = [ 'href' => $href, 'anchor' => $anchor ];
		}
	}

	return $out;
}

/** نام فارسی نوع شیء (تابع نمایش پیشخوان فقط در پیشخوان لود می‌شود). */
function hodima_seo_advisor_links_label( string $kind ): string {
	return function_exists( 'hodima_seo_advisor_kind_label' ) ? hodima_seo_advisor_kind_label( $kind ) : $kind;
}

/** کلید «نوع:شناسه» یک شیء (برای مقایسه مقصد لینک با صفحه‌های گروه). */
function hodima_seo_advisor_links_key( string $type, int $id ): string {
	return $type . ':' . $id;
}

/**
 * مقصد یک لینک: کلید شیء (در صورت شناخته‌شدن)، آدرس یکسان‌شده و وضعیت.
 *
 * state: ok (صفحه‌ای از سایت)، broken (۴۰۴ یا مرده)، skip (بیرونی، فایل، #، صفحه اصلی، بخش دیگر وردپرس)
 *
 * @return array{key: string, path: string, state: string}
 */
function hodima_seo_advisor_links_dest( string $href ): array {

	static $cache = [];

	$cache[ $href ] ??= hodima_seo_advisor_links_dest_resolve( $href );

	return $cache[ $href ];
}

/**
 * حل یک لینک (بدون کش؛ hodima_seo_advisor_links_dest هر آدرس را یک بار صدا می‌زند).
 *
 * @return array{key: string, path: string, state: string}
 */
function hodima_seo_advisor_links_dest_resolve( string $href ): array {

	$skip = [ 'key' => '', 'path' => '', 'state' => 'skip' ];

	if ( '' === $href || str_starts_with( $href, '#' ) || 1 === preg_match( '/^(mailto|tel|javascript|data|sms|whatsapp):/i', $href ) ) {
		return $skip;
	}

	if ( str_starts_with( $href, '//' ) ) {
		$absolute = 'https:' . $href;
	} elseif ( str_starts_with( $href, '/' ) ) {
		$absolute = home_url( $href );
	} elseif ( 1 === preg_match( '#^https?://#i', $href ) ) {
		$absolute = $href;
	} else {
		return $skip; // آدرس نسبی بدون «/» به صفحه جاری بستگی دارد
	}

	$path = hodima_seo_advisor_path_key( $absolute );

	if ( null === $path || 1 === preg_match( '#^/(wp-content|wp-includes|wp-admin|wp-json)(/|$)#', $path ) || str_contains( basename( $path ), '.' ) ) {
		return $skip; // دامنه دیگر، فایل یا مسیر سیستمی
	}

	$dest = [ 'key' => '', 'path' => $path, 'state' => 'ok' ];

	if ( ! function_exists( 'hodima_router_resolve_url' ) || ! function_exists( 'hodima_router_active' ) || ! hodima_router_active() ) {
		return $dest; // بدون روتر: فقط آدرس یکسان‌شده
	}

	// صفحه خصوصی را بازدیدکننده نمی‌بیند: حل مقصد از دید او (report.php روتر؛ فقط اگر ماژول آدرس تمیز روشن است)
	$router  = hodima_seo_advisor_health_router_ready();
	$resolve = static function () use ( $absolute, $dest, $skip ): array {

		$hit = hodima_router_resolve_url( $absolute, true );
		if ( null !== $hit ) {
			return [ 'key' => hodima_seo_advisor_links_key( (string) $hit['type'], (int) $hit['id'] ) ] + $dest;
		}

		if ( ! function_exists( 'hodima_router_inspect_url' ) ) {
			return $skip;
		}

		$check = hodima_router_inspect_url( $absolute );
		$state = function_exists( 'hodima_router_check_key' ) ? hodima_router_check_key( $check ) : (string) $check['status'];

		if ( in_array( $state, [ 'missing', 'rule_broken', 'rule_gone' ], true ) ) {
			return [ 'state' => 'broken' ] + $dest;
		}

		// ریدایرکت (روتر یا قانون دستی): به شیء مقصد نهایی می‌رسد
		$final  = function_exists( 'hodima_router_final_url' ) ? hodima_router_final_url( $check ) : '';
		$target = '' !== $final ? hodima_router_resolve_url( $final, true ) : null;

		return null !== $target ? [ 'key' => hodima_seo_advisor_links_key( (string) $target['type'], (int) $target['id'] ) ] + $dest : $skip;
	};

	return $router && function_exists( 'hodima_router_as_visitor' ) ? hodima_router_as_visitor( $resolve ) : $resolve();
}

/**
 * پیش‌فیلتر SQL برای متن‌هایی که ممکن است کلمه داشته باشند: بلندترین واژه کلمه، با املای عربی ی/ک هم.
 *
 * @return list<string> الگوهای LIKE (۱ یا ۲ مورد)؛ خالی = کلمه واژه قابل‌جستجو ندارد
 */
function hodima_seo_advisor_links_like( string $term ): array {

	global $wpdb;

	$tokens = array_filter( explode( ' ', hodima_seo_advisor_normalize( $term ) ), static fn( string $t ): bool => mb_strlen( $t ) >= 2 );

	if ( ! $tokens ) {
		return [];
	}

	usort( $tokens, static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a ) );

	return array_values( array_unique( [
		'%' . $wpdb->esc_like( $tokens[0] ) . '%',
		'%' . $wpdb->esc_like( strtr( $tokens[0], [ 'ی' => 'ي', 'ک' => 'ك' ] ) ) . '%',
	] ) );
}

/**
 * لینک‌هایی که متنشان کلمه را دارد، از همه منبع‌ها.
 *
 * هر عنصر: source (label، title، field، edit، ref برای حذف تکرار)، anchor و یا href (لینک متنی) یا key (آیتم منو
 * که شیء را مستقیم نشان می‌دهد).
 *
 * @return array{entries: list<array<string, mixed>>, capped: bool}
 */
function hodima_seo_advisor_links_collect( string $term ): array {

	global $wpdb;

	$entries = [];
	$capped  = false;
	$likes   = hodima_seo_advisor_links_like( $term );
	$has     = static fn( string $anchor ): bool => hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( $anchor ), $term );

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
				$edit  = (string) get_edit_post_link( (int) $post['ID'], 'raw' );
				$title = trim( wp_strip_all_tags( (string) $post['post_title'] ) );
				foreach ( [ 'post_content' => 'متن', 'post_excerpt' => 'product' === $post['post_type'] ? 'توضیح کوتاه' : 'خلاصه' ] as $column => $field ) {
					foreach ( hodima_seo_advisor_links_anchors( (string) $post[ $column ] ) as $link ) {
						if ( $has( $link['anchor'] ) ) {
							$entries[] = [
								'source' => [ 'label' => hodima_seo_advisor_links_label( (string) $post['post_type'] ), 'title' => '' !== $title ? $title : '#' . $post['ID'], 'field' => $field, 'edit' => $edit, 'ref' => 'post:' . $post['ID'] ],
								'anchor' => $link['anchor'],
								'href'   => $link['href'],
							];
						}
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
		$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderها با array_fill هم‌اندازه‌اند
				'SELECT t.term_id, t.name, tt.taxonomy, tt.description FROM %i tt INNER JOIN %i t ON t.term_id = tt.term_id WHERE tt.taxonomy IN (' . implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) ) . ') AND (tt.description LIKE %s OR tt.description LIKE %s) LIMIT 300',
				$wpdb->term_taxonomy,
				$wpdb->terms,
				...$taxonomies,
				...[ $likes[0], $likes[1] ?? $likes[0] ]
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$edit = (string) get_edit_term_link( (int) $row['term_id'], (string) $row['taxonomy'] );
			foreach ( hodima_seo_advisor_links_anchors( (string) $row['description'] ) as $link ) {
				if ( $has( $link['anchor'] ) ) {
					$entries[] = [
						'source' => [ 'label' => hodima_seo_advisor_links_label( (string) $row['taxonomy'] ), 'title' => (string) $row['name'], 'field' => 'توضیح دسته', 'edit' => $edit, 'ref' => 'term:' . $row['term_id'] ],
						'anchor' => $link['anchor'],
						'href'   => $link['href'],
					];
				}
			}
		}
	}

	// ۳. منوها (عنوان آیتم = متن لینک؛ آیتم صفحه/دسته مقصدش را مستقیم می‌دهد)
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

			if ( '' === $title || ! $has( $title ) ) {
				continue;
			}

			$menus = wp_get_object_terms( $id, 'nav_menu' );
			$menu  = is_array( $menus ) && isset( $menus[0] ) && $menus[0] instanceof WP_Term ? $menus[0] : null;
			$entry = [
				'source' => [ 'label' => 'منو', 'title' => ( null !== $menu ? $menu->name . ' ← ' : '' ) . $title, 'field' => 'آیتم منو', 'edit' => null !== $menu ? admin_url( 'nav-menus.php?action=edit&menu=' . $menu->term_id ) : admin_url( 'nav-menus.php' ), 'ref' => 'menu:' . $id ],
				'anchor' => $title,
			];

			if ( 'custom' === $type ) {
				$entry['href'] = (string) get_post_meta( $id, '_menu_item_url', true );
			} elseif ( $ref > 0 && '' !== $object ) {
				$entry['key'] = hodima_seo_advisor_links_key( $object, $ref );
				$link         = 'taxonomy' === $type ? get_term_link( $ref, $object ) : get_permalink( $ref );
				$entry['path'] = is_string( $link ) ? (string) hodima_seo_advisor_path_key( $link ) : '';
			} else {
				continue;
			}

			$entries[] = $entry;
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
			foreach ( hodima_seo_advisor_links_anchors( $value ) as $link ) {
				if ( $has( $link['anchor'] ) ) {
					$entries[] = [
						'source' => [ 'label' => 'تنظیمات قالب', 'title' => (string) ( $fields[ $key ]['label'] ?? $key ), 'field' => (string) $key, 'edit' => $edit, 'ref' => 'setting:' . $key ],
						'anchor' => $link['anchor'],
						'href'   => $link['href'],
					];
				}
			}
		}
	}

	return [ 'entries' => $entries, 'capped' => $capped ];
}

/**
 * صفحه‌های یک گروه رقابت، برنده (هدف لینک‌ها) اول. همان قاعده تب رقابت: برنده بیشترین کلیک؛ برای دستور «دسته
 * اصلی شود» هدف خود دسته است. گروهی که رقابت واقعی ندارد یا جهتش روشن نیست خطا می‌دهد.
 *
 * @return array{pages: list<array<string, mixed>>, type: string}|WP_Error
 */
function hodima_seo_advisor_links_group( string $term ): array|WP_Error {

	$ignored  = hodima_seo_advisor_ignored();
	$resolved = hodima_seo_advisor_resolved_paths();
	$pages    = array_values( array_filter(
		hodima_seo_advisor_term_pages( $term, HODIMA_SEO_ADVISOR_COMPETITION_MAX_PAGES + 5 ),
		static fn( array $p ): bool => ! in_array( $p['path'], $ignored, true ) && 1 !== preg_match( '#/page/\d+/?$#', $p['path'] ) && ! isset( $resolved[ $p['path'] ] )
	) );

	if ( count( $pages ) < 2 ) {
		return new WP_Error( 'hodima_advisor_links_group', 'برای این کلمه رقابت واقعی بین چند صفحه پیدا نشد.' );
	}

	usort(
		$pages,
		static fn( array $a, array $b ): int => [ $b['clicks'], $a['position'], $b['impressions'] ] <=> [ $a['clicks'], $b['position'], $a['impressions'] ]
	);
	$pages = array_slice( $pages, 0, HODIMA_SEO_ADVISOR_COMPETITION_MAX_PAGES );

	$total = array_sum( array_column( $pages, 'impressions' ) );
	$share = $total > 0 ? max( array_column( $pages, 'impressions' ) ) / $total : 0.0;
	$type  = hodima_seo_advisor_competition_advice( $term, $pages, $share )['type'];

	if ( 'choose' === $type ) {
		return new WP_Error( 'hodima_advisor_links_choose', 'نمایش بین چند صفحه پخش است و جهت لینک‌دهی هنوز روشن نیست. اول یک صفحه را اصلی کنید.' );
	}

	if ( 'category' === $type ) {
		foreach ( $pages as $i => $page ) {
			if ( in_array( (string) $page['kind'], [ 'product_cat', 'category' ], true ) ) {
				$hit   = array_splice( $pages, $i, 1 );
				$pages = [ ...$hit, ...$pages ]; // هدف: خود دسته
				break;
			}
		}
	}

	return [ 'pages' => $pages, 'type' => $type ];
}

/**
 * عنوان و آدرس ویرایش یک صفحه گروه برای نمایش.
 *
 * @param array<string, mixed> $page
 * @return array{path: string, title: string, kind: string, edit: string, keys: list<string>}
 */
function hodima_seo_advisor_links_page_info( array $page ): array {

	$kind  = (string) $page['kind'];
	$id    = (int) $page['object_id'];
	$title = hodima_seo_advisor_object_title( $kind, $id );
	$title = '' !== $title ? $title : trim( (string) $page['title'] );
	$edit  = '';

	if ( $id > 0 && post_type_exists( $kind ) ) {
		$edit = (string) get_edit_post_link( $id, 'raw' );
	} elseif ( $id > 0 && taxonomy_exists( $kind ) ) {
		$edit = (string) get_edit_term_link( $id, $kind );
	}

	return [
		'path'  => (string) $page['path'],
		'title' => '' !== $title ? $title : rawurldecode( (string) $page['path'] ),
		'kind'  => $kind,
		'edit'  => $edit,
		'keys'  => array_values( array_filter( [ $id > 0 && '' !== $kind ? hodima_seo_advisor_links_key( $kind, $id ) : '', 'path:' . $page['path'] ] ) ),
	];
}

/**
 * بررسی لینک‌های یک گروه رقابت.
 *
 * @return array{term: string, target: array<string, mixed>, rivals: list<array<string, mixed>>, counts: array<string, int>, rows: list<array<string, mixed>>, total: int, capped: bool, router: bool}|WP_Error
 */
function hodima_seo_advisor_links_scan( string $term, bool $force = false ): array|WP_Error {

	$term = trim( $term );

	if ( '' === $term ) {
		return new WP_Error( 'hodima_advisor_links_term', 'کلمه مشخص نیست.' );
	}

	$group = hodima_seo_advisor_links_group( $term );
	if ( is_wp_error( $group ) ) {
		return $group;
	}

	$cache_key = 'hodima_adv_lk_' . md5( implode( '|', [ $term, implode( ',', array_column( $group['pages'], 'path' ) ), wp_cache_get_last_changed( 'posts' ), wp_cache_get_last_changed( 'terms' ), (string) hodima_seo_advisor_status()['fetched'] ] ) );
	$cached    = $force ? false : get_transient( $cache_key );

	if ( is_array( $cached ) && isset( $cached['counts'], $cached['rows'] ) ) {
		return $cached;
	}

	$infos  = array_map( 'hodima_seo_advisor_links_page_info', $group['pages'] );
	$target = $infos[0];
	$rivals = array_slice( $infos, 1 );
	$rival_keys = array_merge( ...array_column( $rivals, 'keys' ) );

	$collected = hodima_seo_advisor_links_collect( $term );
	$counts    = [ 'wrong' => 0, 'broken' => 0, 'missing' => 0, 'good' => 0 ];
	$rows      = [];
	$seen      = [];
	$touched   = [];
	$hrefs     = [];
	$titles    = [];
	foreach ( $rivals as $rival ) {
		foreach ( $rival['keys'] as $key ) {
			$titles[ $key ] = $rival['title'];
		}
	}

	foreach ( $collected['entries'] as $entry ) {
		if ( isset( $entry['key'] ) ) {
			$dest = [ 'key' => (string) $entry['key'], 'path' => (string) ( $entry['path'] ?? '' ), 'state' => 'ok' ];
		} else {
			if ( ! isset( $hrefs[ $entry['href'] ] ) ) {
				if ( count( $hrefs ) >= HODIMA_SEO_ADVISOR_LINKS_MAX_HREFS ) {
					continue; // سقف کار: آدرس یکتای تازه دیگر حل نمی‌شود
				}
				$hrefs[ $entry['href'] ] = true;
			}
			$dest = hodima_seo_advisor_links_dest( (string) $entry['href'] );
		}

		if ( 'skip' === $dest['state'] ) {
			continue;
		}

		$is_target = ( '' !== $dest['key'] && in_array( $dest['key'], $target['keys'], true ) ) || in_array( 'path:' . $dest['path'], $target['keys'], true );
		$is_rival  = ( '' !== $dest['key'] && in_array( $dest['key'], $rival_keys, true ) ) || in_array( 'path:' . $dest['path'], $rival_keys, true );

		if ( 'broken' === $dest['state'] ) {
			$issue = 'broken';
		} elseif ( $is_rival && ! $is_target ) {
			$issue = 'wrong';
		} elseif ( $is_target ) {
			++$counts['good'];
			continue;
		} else {
			continue; // با متن کلمه به صفحه نامربوط می‌رود؛ به رقابت این گروه ربطی ندارد
		}

		$dedupe = $entry['source']['ref'] . '|' . $entry['source']['field'] . '|' . hodima_seo_advisor_normalize( (string) $entry['anchor'] ) . '|' . $dest['path'];
		if ( isset( $seen[ $dedupe ] ) ) {
			++$rows[ $seen[ $dedupe ] ]['times'];
			++$counts[ $issue ];
			continue;
		}

		$seen[ $dedupe ] = count( $rows );
		$touched[ (string) $entry['source']['ref'] ] = true;
		++$counts[ $issue ];
		$rows[] = [
			'issue'  => $issue,
			'source' => $entry['source'],
			'anchor' => mb_substr( (string) $entry['anchor'], 0, 80 ),
			'now'    => [ 'path' => $dest['path'], 'title' => $titles[ $dest['key'] ] ?? ( $titles[ 'path:' . $dest['path'] ] ?? '' ) ],
			'times'  => 1,
		];
	}

	// جای بدون لینک: کلمه در متن هست ولی به هدف لینک نیست (منبعی که لینک اشتباه دارد دوباره نیاید)
	$source_row = [ 'path' => $target['path'], 'kind' => $target['kind'], 'object_id' => (int) $group['pages'][0]['object_id'] ];
	$missing    = hodima_seo_advisor_link_sources_cached( $term, $source_row );

	foreach ( $missing['items'] as $item ) {
		$ref = ( taxonomy_exists( $item['kind'] ) ? 'term:' : 'post:' ) . $item['id'];
		if ( isset( $touched[ $ref ] ) ) {
			continue;
		}
		$edit = taxonomy_exists( $item['kind'] ) ? (string) get_edit_term_link( $item['id'], $item['kind'] ) : (string) get_edit_post_link( $item['id'], 'raw' );
		++$counts['missing'];
		$rows[] = [
			'issue'  => 'missing',
			'source' => [ 'label' => hodima_seo_advisor_links_label( $item['kind'] ), 'title' => $item['title'], 'field' => 'متن', 'edit' => $edit, 'ref' => $ref ],
			'anchor' => '',
			'now'    => [ 'path' => '', 'title' => '' ],
			'times'  => 1,
		];
	}
	$counts['missing'] += (int) $missing['more'];

	$order = [ 'wrong' => 0, 'broken' => 1, 'missing' => 2 ];
	usort( $rows, static fn( array $a, array $b ): int => $order[ $a['issue'] ] <=> $order[ $b['issue'] ] );

	$result = [
		'term'   => $term,
		'target' => $target,
		'rivals' => $rivals,
		'counts' => $counts,
		'rows'   => array_slice( $rows, 0, HODIMA_SEO_ADVISOR_LINKS_MAX_ROWS ),
		'total'  => count( $rows ),
		'capped' => $collected['capped'],
		'router' => function_exists( 'hodima_router_resolve_url' ) && function_exists( 'hodima_router_active' ) && hodima_router_active(),
	];

	set_transient( $cache_key, $result, HODIMA_SEO_ADVISOR_LINKS_TTL );

	return $result;
}

/** admin-ajax «بررسی لینک‌ها»: HTML نتیجه (escape‌شده در سرور) را برمی‌گرداند. */
function hodima_seo_advisor_handle_links_ajax(): void {

	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'hodima_advisor_links', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ندارید یا صفحه منقضی شده است؛ صفحه را دوباره باز کنید.' ], 403 );
	}

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 120 );
	}

	$term = sanitize_text_field( wp_unslash( (string) ( $_POST['term'] ?? '' ) ) );
	$scan = hodima_seo_advisor_links_scan( $term, ! empty( $_POST['force'] ) );

	if ( is_wp_error( $scan ) ) {
		wp_send_json_error( [ 'message' => $scan->get_error_message() ] );
	}

	wp_send_json_success( [ 'html' => function_exists( 'hodima_seo_advisor_render_links_result' ) ? hodima_seo_advisor_render_links_result( $scan ) : '' ] );
}
