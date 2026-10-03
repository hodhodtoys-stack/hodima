<?php
/**
 * Hodima Router — حل مسیر تمیز به شیء (محصول، نوشته، برگه، دسته محصول، دسته نوشته)
 * Path: core/router/resolver.php
 *
 * قاعده: هر شیء *یک* آدرس اصلی دارد = get_permalink() / get_term_link()
 * (بعد از حذف پایه در permalinks.php). مسیر درخواستی با آن مقایسه می‌شود:
 *   برابر  → همان صفحه (exact)
 *   متفاوت → ۳۰۱ به آدرس اصلی (canonical.php)
 *
 * نسخه قبلی برای دسته تودرتو چند آدرس ۲۰۰ می‌پذیرفت (/sub/ و /hair/sub/)،
 * برگه فرزند در ریشه (/faq/ به جای /about-us/faq/) و آدرس با حروف بزرگ هم ۲۰۰
 * بودند؛ همه تکراری بودند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * ردیف‌های پایگاه داده با این نامک (نوشته/برگه/محصول منتشرشده یا خصوصی، و ترم‌ها).
 *
 * کش: کلید با last_changed گروه‌های posts و terms ساخته می‌شود؛ وردپرس آن را با
 * *هر* تغییر نوشته یا ترم عوض می‌کند (clean_post_cache / clean_term_cache)،
 * پس نتیجه کهنه ممکن نیست.
 * باگ قبلی: شمارنده دستی فقط با post_updated بالا می‌رفت؛ انتشار زمان‌بندی‌شده
 * (wp_publish_post) و ساخت مستقیم با وضعیت publish (درون‌ریزی CSV، REST) آن را
 * بالا نمی‌بردند و با کش پایدار (Redis/LiteSpeed) نتیجه «پیدا نشد» تا یک ساعت
 * می‌ماند: محصول تازه ۴۰۴ می‌داد. شمارنده هم یک گزینه بدون autoload بود و در
 * هر درخواست یک کوئری اضافه داشت.
 *
 * @return list<array{kind:string, id:int, type:string, status:string}>
 */
function hodima_router_lookup( string $segment ): array {

	global $wpdb;

	$post_types = hodima_router_post_types();
	$taxonomies = hodima_router_taxonomies();
	$slugs      = array_values( array_unique( [ hodima_router_normalize_slug( $segment ), $segment ] ) );

	if ( '' === $slugs[0] || ( ! $post_types && ! $taxonomies ) ) {
		return [];
	}

	$key   = 'slug_' . md5( implode( '|', $slugs ) ) . '_' . wp_cache_get_last_changed( 'posts' ) . '_' . wp_cache_get_last_changed( 'terms' );
	$rows  = wp_cache_get( $key, 'hodima_router' );

	if ( is_array( $rows ) ) {
		return $rows;
	}

	$in_slugs = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
	$parts    = [];
	$args     = [];

	if ( $post_types ) {
		$parts[] = "SELECT 'post' AS kind, ID AS id, post_type AS type, post_status AS status
			FROM {$wpdb->posts}
			WHERE post_name IN ($in_slugs)
			  AND post_status IN ('publish','private')
			  AND post_type IN (" . implode( ',', array_fill( 0, count( $post_types ), '%s' ) ) . ')';
		array_push( $args, ...$slugs, ...$post_types );
	}

	if ( $taxonomies ) {
		$parts[] = "SELECT 'term' AS kind, t.term_id AS id, tt.taxonomy AS type, '' AS status
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			WHERE t.slug IN ($in_slugs)
			  AND tt.taxonomy IN (" . implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) ) . ')';
		array_push( $args, ...$slugs, ...$taxonomies );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders above
	$results = (array) $wpdb->get_results( $wpdb->prepare( implode( ' UNION ALL ', $parts ), ...$args ) );

	$rows = [];
	foreach ( $results as $row ) {
		$rows[ $row->kind . ':' . $row->id ] = [
			'kind'   => (string) $row->kind,
			'id'     => (int) $row->id,
			'type'   => (string) $row->type,
			'status' => (string) $row->status,
		];
	}
	$rows = array_values( $rows );

	wp_cache_set( $key, $rows, 'hodima_router', HOUR_IN_SECONDS );

	return $rows;
}

/** شیء وردپرس یک ردیف (یا null). */
function hodima_router_row_object( array $row ): WP_Post|WP_Term|null {

	if ( 'post' === $row['kind'] ) {
		$post = get_post( (int) $row['id'] );
		return $post instanceof WP_Post ? $post : null;
	}

	$term = get_term( (int) $row['id'], (string) $row['type'] );
	return $term instanceof WP_Term ? $term : null;
}

/** آدرس اصلی شیء (همان لینکی که سایت همه‌جا چاپ می‌کند). */
function hodima_router_object_url( WP_Post|WP_Term $object ): string {

	if ( $object instanceof WP_Post ) {
		return (string) get_permalink( $object );
	}

	$link = get_term_link( $object );
	return is_wp_error( $link ) ? '' : (string) $link;
}

/**
 * گزینه‌های قابل نمایش برای یک نامک، به ترتیب اولویت.
 * برگه/نوشته خصوصی فقط برای کسی که اجازه خواندنش را دارد (read_post).
 * باگ قبلی: یک مسیر read_post و مسیر دیگر edit_post بررسی می‌کرد.
 *
 * @return list<array{kind:string, id:int, type:string, object:WP_Post|WP_Term, url:string, path:string}>
 */
function hodima_router_candidates( string $segment ): array {

	$priorities = hodima_router_priorities();
	$out        = [];

	foreach ( hodima_router_lookup( $segment ) as $row ) {

		if ( 'private' === $row['status'] && ! current_user_can( 'read_post', $row['id'] ) ) {
			continue;
		}

		$object = hodima_router_row_object( $row );
		$url    = null !== $object ? hodima_router_object_url( $object ) : '';
		$path   = '' !== $url ? hodima_router_url_path( $url ) : null;

		if ( null === $object || null === $path ) {
			continue;
		}

		$out[] = $row + [ 'object' => $object, 'url' => $url, 'path' => hodima_router_compare_form( $path ) ];
	}

	usort( $out, static fn( array $a, array $b ): int => ( $priorities[ $a['type'] ] ?? 99 ) <=> ( $priorities[ $b['type'] ] ?? 99 ) );

	return $out;
}

/**
 * جدا کردن پسوند عمومی انتهای مسیر: فید، صفحه‌بندی، صفحه دیدگاه، embed، trackback.
 *
 * @param list<string> $segments
 * @return array{0:list<string>, 1:array<string, string|int>, 2:list<string>} [بخش‌های باقی‌مانده، متغیرها، بخش‌های پسوند]
 */
function hodima_router_split_suffix( array $segments ): array {

	$n     = count( $segments );
	$last  = $n ? hodima_router_compare_form( $segments[ $n - 1 ] ) : '';
	$prev  = $n > 1 ? hodima_router_compare_form( $segments[ $n - 2 ] ) : '';
	$feeds = hodima_router_feed_types();

	// فقط وقتی قبلش چیزی هست: برگه‌ای به نام «feed» یا «rss» هم باید باز شود
	if ( $n >= 3 && 'feed' === $prev && in_array( $last, $feeds, true ) ) {
		return [ array_slice( $segments, 0, -2 ), [ 'feed' => $last ], array_slice( $segments, -2 ) ];
	}
	if ( $n >= 2 && in_array( $last, $feeds, true ) ) {
		return [ array_slice( $segments, 0, -1 ), [ 'feed' => $last ], array_slice( $segments, -1 ) ];
	}
	if ( $n >= 3 && 'page' === $prev && ctype_digit( $last ) ) {
		return [ array_slice( $segments, 0, -2 ), [ 'paged' => (int) $last ], array_slice( $segments, -2 ) ];
	}
	if ( $n >= 2 && preg_match( '/^comment-page-(\d+)$/', $last, $m ) ) {
		return [ array_slice( $segments, 0, -1 ), [ 'cpage' => (int) $m[1] ], array_slice( $segments, -1 ) ];
	}
	if ( $n >= 2 && 'embed' === $last ) {
		return [ array_slice( $segments, 0, -1 ), [ 'embed' => 'true' ], array_slice( $segments, -1 ) ];
	}
	if ( $n >= 2 && 'trackback' === $last ) {
		return [ array_slice( $segments, 0, -1 ), [ 'tb' => 1 ], array_slice( $segments, -1 ) ];
	}

	return [ $segments, [], [] ];
}

/** متغیرهای کوئری وردپرس برای نمایش یک شیء (همان شکل نسخه قبلی روتر). */
function hodima_router_object_vars( WP_Post|WP_Term $object ): array {

	if ( $object instanceof WP_Term ) {
		return 'product_cat' === $object->taxonomy
			? [ 'product_cat' => $object->slug, 'taxonomy' => 'product_cat', 'term' => $object->slug ]
			: [ 'category_name' => $object->slug ];
	}

	return 'page' === $object->post_type
		? [ 'page_id' => $object->ID ]
		: [ 'post_type' => $object->post_type, 'name' => $object->post_name ];
}

/**
 * حل یک مسیر نسبی.
 *
 * تفسیرها به ترتیب: (۱) کل مسیر یک شیء است، (۲) انتهایش اندپوینت ووکامرس
 * روی برگه حساب/پرداخت است، (۳) انتهایش شماره صفحه نوشته چندصفحه‌ای است،
 * (۴) بدون جدا کردن پسوند (دسته‌ای به نام feed). اول تفسیری که دقیقا آدرس
 * اصلی شیء است، بعد (اگر $loose) نزدیک‌ترین که با ۳۰۱ اصلاح می‌شود.
 *
 * @return array{object:WP_Post|WP_Term, kind:string, id:int, type:string, vars:array, exact:bool, url:string, base:string, suffix:list<string>}|array{error:int}|null
 *         null = مال روتر نیست؛ ['error' => 404] = اندپوینت روی صفحه نامربوط
 */
function hodima_router_match( string $path, bool $loose = true ): ?array {

	$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );

	// نامک نقطه ندارد (sanitize_title آن را خط تیره می‌کند): robots.txt، sitemap.xml،
	// llms.txt، x.md و فایل‌ها بدون کوئری رد می‌شوند
	if ( ! $segments || str_contains( (string) end( $segments ), '.' ) ) {
		return null;
	}

	[ $base, $suffix_vars, $suffix ] = hodima_router_split_suffix( $segments );

	/** @var list<array{0:list<string>, 1:array, 2:list<string>, 3:string}> $plans [بخش‌ها، متغیرها، پسوند، نوع] */
	$plans = [];

	if ( $base ) {
		$plans[] = [ $base, $suffix_vars, $suffix, 'object' ];

		$n         = count( $base );
		$endpoints = hodima_router_endpoints();
		$last      = hodima_router_compare_form( $base[ $n - 1 ] );
		$prev      = $n > 1 ? hodima_router_compare_form( $base[ $n - 2 ] ) : '';

		if ( $n >= 2 && isset( $endpoints[ $last ] ) ) {
			$plans[] = [ array_slice( $base, 0, -1 ), $suffix_vars + [ $endpoints[ $last ]['var'] => '' ], array_merge( array_slice( $base, -1 ), $suffix ), 'endpoint:' . $endpoints[ $last ]['page'] ];
		} elseif ( $n >= 3 && isset( $endpoints[ $prev ] ) ) {
			$plans[] = [ array_slice( $base, 0, -2 ), $suffix_vars + [ $endpoints[ $prev ]['var'] => rawurldecode( $base[ $n - 1 ] ) ], array_merge( array_slice( $base, -2 ), $suffix ), 'endpoint:' . $endpoints[ $prev ]['page'] ];
		}

		if ( $n >= 2 && ! $suffix && ctype_digit( $last ) ) {
			$plans[] = [ array_slice( $base, 0, -1 ), [ 'page' => (int) $last ], array_slice( $base, -1 ), 'paged-post' ];
		}
	}

	if ( $suffix ) {
		$plans[] = [ $segments, [], [], 'object' ];
	}

	$exact          = null;
	$nearest        = null;
	$endpoint_abuse = false;

	foreach ( $plans as [ $plan_segments, $plan_vars, $plan_suffix, $plan_type ] ) {

		$requested = hodima_router_compare_form( implode( '/', $plan_segments ) );

		foreach ( hodima_router_candidates( (string) end( $plan_segments ) ) as $candidate ) {

			if ( str_starts_with( $plan_type, 'endpoint:' ) ) {
				$page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( substr( $plan_type, 9 ) ) : 0;
				if ( ! ( $candidate['object'] instanceof WP_Post ) || $page_id <= 0 || $candidate['id'] !== $page_id ) {
					$endpoint_abuse = true;
					continue;
				}
			} elseif ( 'paged-post' === $plan_type && ! ( $candidate['object'] instanceof WP_Post ) ) {
				continue;
			}

			$hit = [
				'object' => $candidate['object'],
				'kind'   => $candidate['kind'],
				'id'     => $candidate['id'],
				'type'   => $candidate['type'],
				'vars'   => $plan_vars + hodima_router_object_vars( $candidate['object'] ),
				'exact'  => $candidate['path'] === $requested,
				'url'    => $candidate['url'],
				'base'   => implode( '/', $plan_segments ),
				'suffix' => $plan_suffix,
			];

			if ( $hit['exact'] ) {
				$exact = $hit;
				break 2;
			}

			$nearest ??= $hit;
		}
	}

	if ( null !== $exact ) {
		return $exact;
	}

	if ( null !== $nearest && $loose ) {
		return $nearest;
	}

	// /hair/orders/، /103/view-order/5/، /about-us/edit-account/: پیش‌تر همان
	// صفحه با کد ۲۰۰ (بی‌نهایت آدرس تکراری)
	return $endpoint_abuse && null === $nearest ? [ 'error' => 404 ] : null;
}

/**
 * آدرس نهایی یک نتیجه: آدرس اصلی شیء + پسوند (صفحه‌بندی، فید، اندپوینت).
 */
function hodima_router_target_url( array $match ): string {

	$url = (string) $match['url'];

	if ( ! empty( $match['suffix'] ) ) {
		$url = user_trailingslashit( untrailingslashit( $url ) . '/' . implode( '/', $match['suffix'] ) );
	}

	return $url;
}

/**
 * API عمومی: آدرس داخلی → شیء.
 *
 * ماژول‌های دیگر (خوشه موضوعی، IndexNow/AEO، لینک‌های مرتبط، ریدایرکت‌ها) این
 * را صدا می‌زنند؛ پیش‌تر هر کدام حدس خودش را داشت یا به ماژول دیگری وابسته بود.
 * آدرس با پایه قدیمی (/product/x/) هم پذیرفته می‌شود.
 *
 * @param bool $loose true = آدرسی که با ۳۰۱ به شیء می‌رسد هم قبول است.
 * @return array{kind:string, id:int, type:string, object:WP_Post|WP_Term, url:string, exact:bool}|null
 */
function hodima_router_resolve_url( string $url, bool $loose = false ): ?array {

	if ( ! hodima_router_active() ) {
		return null;
	}

	$path = hodima_router_url_path( $url );
	if ( null === $path || '' === $path ) {
		return null;
	}

	// پایه قدیمی محصول/دسته
	$clean = hodima_router_url_path( hodima_router_clean_url( home_url( '/' . $path . '/' ) ) ) ?? $path;

	if ( hodima_router_path_is_reserved( $clean ) ) {
		return null;
	}

	$match = hodima_router_match( $clean, $loose );

	if ( null === $match || isset( $match['error'] ) ) {
		return null;
	}

	return [
		'kind'   => $match['kind'],
		'id'     => $match['id'],
		'type'   => $match['type'],
		'object' => $match['object'],
		'url'    => $match['url'],
		'exact'  => $match['exact'],
	];
}
