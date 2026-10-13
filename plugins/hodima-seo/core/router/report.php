<?php
/**
 * Hodima Router — گزارش‌های فقط‌خواندنی صفحه «آدرس تمیز»
 * Path: core/router/report.php
 *
 * چرا: کادر «بررسی یک آدرس» فقط یک آدرس را می‌سنجید و جدول «آدرس‌های مشترک»
 * فقط دو شیء با آدرس *یکسان* را می‌شمرد. آدرس کوتاه قدیمی یک زیردسته
 * (/plumeria-hair-clips/ پیش از آدرس کامل /accessory/hair-clips/plumeria-hair-clips/)
 * که حالا محصول یا نوشته‌ای با همان نامک آن را گرفته، در هیچ‌کدام دیده نمی‌شد.
 *
 * هیچ تابعی در این فایل چیزی ذخیره نمی‌کند یا رفتار سایت را عوض نمی‌کند؛ فقط
 * همان محاسبه‌ای را که روتر و ماژول ریدایرکت‌ها برای یک بازدیدکننده انجام
 * می‌دهند، تکرار می‌کند. فقط در پیشخوان لود می‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * اجرای یک تابع از دید بازدیدکننده (بدون ورود).
 * برگه/نوشته خصوصی برای مدیر قابل‌خواندن است و روتر آن را نشان می‌دهد، ولی گوگل
 * و بازدیدکننده نه؛ گزارش باید همان چیزی را بگوید که آن‌ها می‌بینند.
 *
 * @template T
 * @param callable(): T $callback
 * @return T
 */
function hodima_router_as_visitor( callable $callback ): mixed {

	$user = get_current_user_id();
	wp_set_current_user( 0 );

	try {
		return $callback();
	} finally {
		wp_set_current_user( $user );
	}
}

/**
 * قانون ماژول «ریدایرکت‌ها» برای یک آدرس (آن ماژول پیش از روتر اجرا می‌شود).
 *
 * @return array{status:int, target:string}|null
 */
function hodima_router_redirect_rule( string $url ): ?array {

	if ( ! function_exists( 'Hodima\Redirects\get_rules' ) || ! function_exists( 'Hodima\Redirects\normalize_path' ) ) {
		return null; // ماژول ریدایرکت‌ها خاموش است
	}

	$rule = \Hodima\Redirects\get_rules()[ \Hodima\Redirects\normalize_path( $url ) ] ?? null;

	if ( ! is_array( $rule ) ) {
		return null;
	}

	return [
		'status' => (int) ( $rule['status'] ?? 301 ),
		'target' => (string) ( $rule['target'] ?? '' ),
	];
}

/**
 * متغیرهای قانون بازنویسی‌ای که وردپرس برای این مسیر انتخاب می‌کند، یا null.
 * همان ترتیب WP::parse_request: اولین قانون منطبق؛ قانون برگه (pagename) در
 * حالت verbose فقط وقتی برگه‌ای با آن مسیر هست.
 *
 * @return list<string>|null
 */
function hodima_router_wp_rule_vars( string $path ): ?array {

	global $wp_rewrite;

	if ( ! ( $wp_rewrite instanceof WP_Rewrite ) ) {
		return null;
	}

	foreach ( (array) $wp_rewrite->wp_rewrite_rules() as $pattern => $query ) {

		$matches = [];
		if ( ! preg_match( '#^' . $pattern . '#', $path, $matches ) && ! preg_match( '#^' . $pattern . '#', rawurldecode( $path ), $matches ) ) {
			continue;
		}

		$page_var = [];
		if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', (string) $query, $page_var ) ) {
			$page_path = (string) ( $matches[ (int) $page_var[1] ] ?? '' );
			if ( ! get_page_by_path( $page_path ) && ! get_page_by_path( rawurldecode( $page_path ) ) ) {
				continue;
			}
		}

		$vars = [];
		parse_str( (string) preg_replace( '!^.+\?!', '', (string) $query ), $vars );

		return array_map( 'strval', array_keys( $vars ) );
	}

	return null;
}

/**
 * آدرسی که روتر نمی‌شناسد: مال بخش دیگری از وردپرس است یا پیدا نمی‌شود (۴۰۴)؟
 * قانون «عمومی» (برگه/نامک/پیوست) بدون شیء = ۴۰۴؛ قانون اختصاصی (تاریخ، فید
 * سایت، صفحه‌بندی وبلاگ …) یعنی صفحه‌ای که وردپرس خودش می‌سازد.
 */
function hodima_router_unknown_is_wordpress( string $path ): bool {

	$vars = hodima_router_wp_rule_vars( $path );

	if ( null === $vars ) {
		return false;
	}

	$endpoint_vars = array_column( hodima_router_endpoints(), 'var' );
	$not_object    = array_merge( [ 'page', 'feed', 'embed', 'paged', 'cpage', 'tb', 'error' ], $endpoint_vars );
	$object_vars   = array_diff( hodima_router_generic_vars(), $not_object );

	// قانون اختصاصی، یا قانون عمومی بدون شیء (مثل /feed/ و /page/2/ خود سایت)
	return (bool) array_diff( $vars, hodima_router_generic_vars() ) || ! array_intersect( $vars, $object_vars );
}

/**
 * آدرس با پایه قدیمی (/product/…، /product-category/…، /category/…).
 * روتر به این‌ها دست نمی‌زند؛ وردپرس خودش شیء را از *نامک آخر* پیدا می‌کند
 * (ووکامرس برای دسته سلسله‌مراتبی wp_basename می‌زند) و canonical.php با ۳۰۱
 * به آدرس تمیز می‌برد. حل این مسیر با روتر اشتباه بود: /product-category/clips/
 * به محصول هم‌نام clips می‌رسید، نه به دسته.
 *
 * @return array{object:WP_Post|WP_Term|null, suffix:list<string>}|null null = مسیر پایه‌دار نیست
 */
function hodima_router_inspect_base( string $path ): ?array {

	$compare = hodima_router_compare_form( $path );

	foreach ( hodima_router_bases() as $type => $base ) {

		$base = hodima_router_compare_form( $base );
		if ( '' === $base || ! str_starts_with( $compare, $base . '/' ) ) {
			continue;
		}

		$rest = array_values( array_filter( explode( '/', substr( $compare, strlen( $base ) + 1 ) ), static fn( string $part ): bool => '' !== $part ) );
		[ $segments, , $suffix ] = hodima_router_split_suffix( $rest );
		$slug   = $segments ? hodima_router_normalize_slug( (string) end( $segments ) ) : '';
		$object = null;

		if ( '' !== $slug && 'product' === $type ) {
			$posts  = get_posts( [ 'post_type' => 'product', 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1 ] );
			$object = $posts[0] ?? null;
		} elseif ( '' !== $slug ) {
			$term   = get_term_by( 'slug', $slug, 'product_cat' === $type ? 'product_cat' : 'category' );
			$object = $term instanceof WP_Term ? $term : null;
		}

		return [ 'object' => $object instanceof WP_Post || $object instanceof WP_Term ? $object : null, 'suffix' => $suffix ];
	}

	return null;
}

/**
 * وضعیت یک آدرس همان‌طور که بازدیدکننده و گوگل می‌بینند.
 *
 * status:
 *   main     آدرس اصلی یک صفحه
 *   shared   آدرس اصلی، ولی شیء دیگری هم همین نامک را دارد (شاید آدرس قدیمی آن بوده)
 *   redirect با ۳۰۱ روتر به آدرس اصلی (target) می‌رود
 *   rule     قانون ماژول «ریدایرکت‌ها» (پیش از روتر)
 *   other    مال بخش دیگری از وردپرس (برچسب، نویسنده، تاریخ …)؛ روتر دست نمی‌زند
 *   missing  صفحه‌ای پیدا نمی‌شود (۴۰۴)
 *   home     صفحه اصلی سایت
 *   external دامنه دیگر
 *
 * @return array{url:string, status:string, object:WP_Post|WP_Term|null, target:string, code:int, others:list<WP_Post|WP_Term>}
 */
function hodima_router_inspect_url( string $url ): array {

	$result = [ 'url' => $url, 'status' => 'missing', 'object' => null, 'target' => '', 'code' => 0, 'others' => [] ];

	$path = hodima_router_url_path( $url );
	if ( null === $path ) {
		return [ 'status' => 'external' ] + $result;
	}

	$rule = hodima_router_redirect_rule( '/' . $path . '/' );
	if ( null !== $rule ) {
		return [ 'status' => 'rule', 'target' => $rule['target'], 'code' => $rule['status'] ] + $result;
	}

	if ( '' === $path ) {
		return [ 'status' => 'home' ] + $result;
	}

	if ( ! hodima_router_active() ) {
		return [ 'status' => hodima_router_unknown_is_wordpress( $path ) ? 'other' : 'missing' ] + $result;
	}

	return hodima_router_as_visitor( static function () use ( $result, $path ): array {

		$based = hodima_router_inspect_base( $path );
		if ( null !== $based ) {
			if ( null === $based['object'] ) {
				return $result; // missing
			}
			$url = hodima_router_object_url( $based['object'] );
			return [
				'status' => 'redirect',
				'object' => $based['object'],
				'target' => hodima_router_target_url( [ 'url' => $url, 'suffix' => $based['suffix'] ] ),
				'code'   => 301,
			] + $result;
		}

		if ( hodima_router_path_is_reserved( $path ) ) {
			return [ 'status' => 'other' ] + $result;
		}

		$match = hodima_router_match( $path, true );

		if ( null === $match || isset( $match['error'] ) ) {
			$wordpress = null === $match && hodima_router_unknown_is_wordpress( $path );
			return [ 'status' => $wordpress ? 'other' : 'missing' ] + $result;
		}

		$object = $match['object'];
		$target = hodima_router_target_url( $match );

		if ( ! $match['exact'] ) {
			return [ 'status' => 'redirect', 'object' => $object, 'target' => $target, 'code' => 301 ] + $result;
		}

		/*
		 * آدرس یک‌بخشی (/clips/) که شیء دیگری هم همین نامک را دارد ولی آدرسش
		 * جای دیگری است (زیردسته /hair/clips/، برگه فرزند): شاید این آدرس قبلا
		 * مال آن بوده و حالا صفحه دیگری نشان داده می‌شود. آدرس چندبخشی خطری ندارد.
		 */
		$parts  = explode( '/', (string) $match['base'] );
		$others = [];
		if ( 1 === count( $parts ) ) {
			foreach ( hodima_router_candidates( $parts[0] ) as $candidate ) {
				if ( $candidate['kind'] !== $match['kind'] || $candidate['id'] !== $match['id'] ) {
					$others[] = $candidate['object'];
				}
			}
		}

		return [
			'status' => $others ? 'shared' : 'main',
			'object' => $object,
			'target' => $target,
			'others' => $others,
		] + $result;
	} );
}

/**
 * آدرس‌های داخل یک متن چسبانده‌شده (هر خط یک آدرس، یا خروجی CSV سرچ کنسول).
 *
 * @return list<string>
 */
function hodima_router_extract_urls( string $text, int $limit = 300 ): array {

	$urls = [];

	foreach ( preg_split( '/\R/u', $text ) ?: [] as $line ) {

		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		if ( preg_match( '#https?://[^\s,;"\'<>]+#iu', $line, $found ) ) {
			$urls[] = $found[0];
		} elseif ( str_starts_with( $line, '/' ) ) {
			$urls[] = home_url( (string) strtok( $line, " \t,;\"'" ) );
		}
	}

	// %D8… و حرف فارسی خام یک آدرس‌اند
	$unique = [];
	foreach ( $urls as $url ) {
		$unique[ rawurldecode( $url ) ] ??= $url;
	}

	return array_slice( array_values( $unique ), 0, $limit );
}

/**
 * آدرس کوتاه قدیمی (فقط نامک) هر زیردسته‌ای که آدرس اصلی‌اش مسیر والدها را دارد.
 *
 * state:
 *   ok      با ۳۰۱ (روتر یا قانون ریدایرکت) به همان دسته می‌رود
 *   rule    قانون ماژول «ریدایرکت‌ها» به جای دیگری می‌برد یا ۴۰۴/۴۱۰ می‌دهد
 *   taken   صفحه دیگری را نشان می‌دهد یا به آن می‌رود
 *   lost    ۴۰۴، یا مال بخش دیگری از وردپرس (مثلا نامک «tag»)
 *
 * @return array{rows:list<array{term:WP_Term, old:string, new:string, state:string, detail:array<string, mixed>}>, total:int, capped:bool}
 */
function hodima_router_nested_terms_report( int $limit = 2000 ): array {

	$rows   = [];
	$total  = 0;
	$capped = false;

	foreach ( hodima_router_taxonomies() as $taxonomy ) {

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $limit + 1,
		] );

		if ( ! is_array( $terms ) ) {
			continue;
		}

		if ( count( $terms ) > $limit ) {
			$capped = true;
			$terms  = array_slice( $terms, 0, $limit );
		}

		foreach ( $terms as $term ) {

			if ( ! ( $term instanceof WP_Term ) || $term->parent <= 0 ) {
				continue;
			}

			$new = hodima_router_object_url( $term );
			$old = home_url( user_trailingslashit( $term->slug ) );

			// تاکسونومی بدون پیوند سلسله‌مراتبی: آدرس اصلی همان آدرس کوتاه است
			if ( '' === $new || hodima_router_compare_form( (string) hodima_router_url_path( $new ) ) === hodima_router_compare_form( $term->slug ) ) {
				continue;
			}

			++$total;

			$check = hodima_router_inspect_url( $old );
			$mine  = $check['object'] instanceof WP_Term && $check['object']->term_id === $term->term_id;

			// قانون دستی که به همین دسته می‌برد هم سالم است
			if ( 'rule' === $check['status'] && '' !== $check['target'] ) {
				$hit  = hodima_router_resolve_url( (string) $check['target'], true );
				$mine = null !== $hit && 'term' === $hit['kind'] && $hit['id'] === $term->term_id;
			}

			$state = match ( true ) {
				$mine && in_array( $check['status'], [ 'redirect', 'rule' ], true ) => 'ok',
				'rule' === $check['status']                                         => 'rule',
				in_array( $check['status'], [ 'main', 'shared', 'redirect' ], true ) => 'taken',
				default                                                             => 'lost',
			};

			$rows[] = [ 'term' => $term, 'old' => $old, 'new' => $new, 'state' => $state, 'detail' => $check ];
		}
	}

	// مشکل‌دارها اول
	$order = [ 'taken' => 0, 'lost' => 1, 'rule' => 2, 'ok' => 3 ];
	usort( $rows, static fn( array $a, array $b ): int => $order[ $a['state'] ] <=> $order[ $b['state'] ] );

	return [ 'rows' => $rows, 'total' => $total, 'capped' => $capped ];
}
