<?php
/**
 * ماژول «دستار سئو» — لینک‌های داخلی یک گروه رقابت
 * Path: core/advisor/advisor-links.php
 *
 * کارت رقابت می‌گفت «رقیب ۱۷ لینک با متن کلمه دارد؛ منتقل کنید» ولی نمی‌گفت کدام نوشته. این فایل برای یک گروه
 * («کلمه + برنده + رقیب‌ها») همه لینک‌های داخلی را از دیتابیس و خزش می‌آورد (منبع‌ها: advisor-links-sources.php:
 * متن نوشته/برگه/محصول، توضیح دسته، لینک‌های مرتبط دستی که فقط شناسه دارند، منو، تنظیمات قالب و جدول لینک‌های
 * خزش) و برای هر لینک می‌گوید کجا ذخیره شده، متن و آدرسش چیست، الان به کجا می‌رود و باید به کجا برود.
 *
 * دسته‌ها: wrong (متنش کلمه را دارد و به رقیب می‌رود، مستقیم یا با ریدایرکت)، broken (۴۰۴)، good (فقط شمرده می‌شود)،
 * و جدا «فرصت لینک» (کلمه در متن هست ولی به هدف لینک ندارد؛ با تکه‌ای از متن). «لینک‌های ورودی هر صفحه» به تفکیک
 * منبع هم شمرده می‌شود (متن، لینک مرتبط، منو، قالب و ساختاری)؛ لینک ساختاری قالب را فقط خزش می‌بیند.
 * رابطه دو صفحه (والد و فرزند دسته، محصول داخل دسته) هم خوانده می‌شود: رقیب والد برنده «رقیب واقعی» نیست.
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
 * @return array{path: string, title: string, kind: string, id: int, edit: string, keys: list<string>}
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
		'id'    => $id,
		'edit'  => $edit,
		'keys'  => array_values( array_filter( [ $id > 0 && '' !== $kind ? hodima_seo_advisor_links_key( $kind, $id ) : '', 'path:' . $page['path'] ] ) ),
	];
}


/** آخرین بخش مسیر یک آدرس یکسان‌شده (نامک)؛ برای پیش‌فیلتر ارزان. */
function hodima_seo_advisor_links_slug( string $path ): string {
	$parts = array_values( array_filter( explode( '/', trim( $path, '/' ) ), static fn( string $part ): bool => '' !== $part ) );
	return $parts ? (string) end( $parts ) : '';
}

/**
 * عنوان و آدرس ویرایش صفحه‌ای که خزش آدرسش را دیده (میزبان یک لینک). شیء ناشناخته: خود آدرس.
 *
 * @return array{label: string, title: string, edit: string, ref: string}
 */
function hodima_seo_advisor_links_host( string $path ): array {

	$class = hodima_seo_advisor_classify( $path );
	$kind  = (string) $class['kind'];
	$id    = (int) $class['id'];
	$title = hodima_seo_advisor_object_title( $kind, $id );
	$edit  = '';

	if ( $id > 0 && post_type_exists( $kind ) ) {
		$edit = (string) get_edit_post_link( $id, 'raw' );
	} elseif ( $id > 0 && taxonomy_exists( $kind ) ) {
		$edit = (string) get_edit_term_link( $id, $kind );
	}

	return [
		'label' => '' !== $kind ? hodima_seo_advisor_links_label( $kind ) : 'صفحه',
		'title' => '' !== $title ? $title : rawurldecode( $path ),
		'edit'  => $edit,
		'ref'   => ( taxonomy_exists( $kind ) ? 'term:' : 'post:' ) . $id,
	];
}

/**
 * بررسی لینک‌های یک گروه رقابت.
 *
 * @return array<string, mixed>|WP_Error
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

	$cache_key = 'hodima_adv_lk_' . md5( implode( '|', [ 'v2', $term, implode( ',', array_column( $group['pages'], 'path' ) ), wp_cache_get_last_changed( 'posts' ), wp_cache_get_last_changed( 'terms' ), (string) hodima_seo_advisor_status()['fetched'] ] ) );
	$cached    = $force ? false : get_transient( $cache_key );

	if ( is_array( $cached ) && isset( $cached['counts'], $cached['rows'], $cached['inventory'] ) ) {
		return $cached;
	}

	$infos  = array_map( 'hodima_seo_advisor_links_page_info', $group['pages'] );
	$target = $infos[0];
	$rivals = array_slice( $infos, 1 );

	$index = [];
	foreach ( $infos as $i => $info ) {
		foreach ( $info['keys'] as $key ) {
			$index[ $key ] = $i;
		}
	}

	$ctx = [
		'term'  => $term,
		'slugs' => array_values( array_unique( array_filter( array_map( static fn( array $i ): string => hodima_seo_advisor_links_slug( $i['path'] ), $infos ) ) ) ),
		'keys'  => array_values( array_filter( array_keys( $index ), static fn( string $k ): bool => ! str_starts_with( $k, 'path:' ) ) ),
		'ids'   => array_values( array_filter( array_map( static fn( array $i ): int => (int) $i['id'], $infos ) ) ),
	];

	$html      = hodima_seo_advisor_links_collect_html( $ctx );
	$entries   = array_merge( $html['entries'], hodima_seo_advisor_links_collect_related( $ctx ) );
	$crawl     = hodima_seo_advisor_links_collect_crawl( $infos, $term );
	$counts    = [ 'wrong' => 0, 'broken' => 0, 'missing' => 0, 'good' => 0 ];
	$inventory = array_fill( 0, count( $infos ), [ 'text' => 0, 'related' => 0, 'menu' => 0 ] );
	$counted   = [];
	$rows      = [];
	$seen      = [];
	$touched   = [];
	$pairs     = [];
	$globals   = [];
	$hrefs     = [];

	foreach ( $entries as $entry ) {

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

		$i      = $index[ $dest['key'] ] ?? $index[ 'path:' . $dest['path'] ] ?? null;
		$source = (array) $entry['source'];
		$bucket = in_array( $entry['bucket'], [ 'menu', 'settings' ], true ) ? 'menu' : (string) $entry['bucket'];

		if ( 'broken' === $dest['state'] ) {
			if ( ! $entry['hit'] ) {
				continue;
			}
			$issue = 'broken';
			$i     = null;
		} elseif ( null === $i ) {
			continue; // به صفحه‌ای بیرون از این گروه می‌رود
		} else {
			$unit = $source['ref'] . '|' . $source['field'] . '|' . $bucket;
			if ( ! isset( $counted[ $i ][ $unit ] ) ) {
				$counted[ $i ][ $unit ] = true;
				++$inventory[ $i ][ $bucket ];
			}
			if ( '' !== (string) ( $source['path'] ?? '' ) ) {
				$pairs[ $source['path'] . '|' . $infos[ $i ]['path'] ] = true;
			}
			if ( 'menu' === $bucket ) {
				$globals[ $infos[ $i ]['path'] . '|' . hodima_seo_advisor_normalize( (string) $entry['anchor'] ) ] = true;
			}
			if ( ! $entry['hit'] ) {
				continue; // لینک بدون کلمه فقط در «لینک‌های ورودی» شمرده می‌شود
			}
			$issue = 0 === $i ? 'good' : 'wrong';
		}

		if ( 'good' === $issue ) {
			$unit = $source['ref'] . '|' . $source['field'] . '|good';
			if ( ! isset( $seen[ $unit ] ) ) {
				$seen[ $unit ] = true;
				++$counts['good'];
			}
			continue;
		}

		$written = isset( $entry['href'] ) ? rawurldecode( (string) $entry['href'] ) : (string) ( $dest['path'] );
		$dedupe  = $source['ref'] . '|' . $source['field'] . '|' . hodima_seo_advisor_normalize( (string) $entry['anchor'] ) . '|' . $dest['path'] . '|' . $dest['key'];

		if ( isset( $seen[ $dedupe ] ) ) {
			++$rows[ $seen[ $dedupe ] ]['times'];
			++$counts[ $issue ];
			continue;
		}

		$page            = null !== $i ? $infos[ $i ] : null;
		$seen[ $dedupe ] = count( $rows );
		$touched[ (string) $source['ref'] ] = true;
		++$counts[ $issue ];
		$rows[] = [
			'issue'      => $issue,
			'source'     => $source,
			'store'      => (string) $entry['store'],
			'anchor'     => mb_substr( (string) $entry['anchor'], 0, 80 ),
			'href'       => $written,
			'now'        => [
				'path'  => '' !== $dest['path'] ? $dest['path'] : (string) ( $page['path'] ?? '' ),
				'title' => (string) ( $page['title'] ?? '' ),
				'kind'  => null !== $page ? hodima_seo_advisor_links_label( (string) $page['kind'] ) : '',
				'note'  => null !== $page && isset( $entry['href'] ) && strtolower( rawurldecode( (string) wp_parse_url( (string) $entry['href'], PHP_URL_PATH ) ) ) !== $page['path'] ? 'آدرس نوشته‌شده با آدرس اصلی فرق دارد (ریدایرکت می‌شود)' : '',
			],
			'times'      => 1,
			'structural' => false,
		];
	}

	// لینک‌های خزش: متن‌هایی که پایگاه داده نمی‌بیند (شورت‌کد، قالب، صفحه‌ساز) و لینک‌های ساختاری قالب
	$grouped = [];
	foreach ( $crawl['rows'] as $r ) {
		$i = array_search( $r['dest_path'], array_column( $infos, 'path' ), true );
		if ( false === $i ) {
			continue;
		}
		$content = 'content' === $r['position'];
		$unit    = $r['source_path'] . '|' . $r['dest_path'];

		if ( 0 === $i ) {
			if ( $content && ! isset( $pairs[ $unit ] ) ) {
				$pairs[ $unit ] = true;
				++$counts['good'];
			}
			continue;
		}

		if ( $content ) {
			if ( isset( $pairs[ $unit ] ) || count( $rows ) >= HODIMA_SEO_ADVISOR_LINKS_MAX_ROWS * 2 ) {
				continue;
			}
			$pairs[ $unit ] = true;
			$host           = hodima_seo_advisor_links_host( $r['source_path'] );
			++$counts['wrong'];
			$touched[ $host['ref'] ] = true;
			$rows[] = [
				'issue'      => 'wrong',
				'source'     => $host + [ 'field' => 'متن (طبق خزش)', 'path' => $r['source_path'] ],
				'store'      => hodima_seo_advisor_links_position_label( $r['position'] ),
				'anchor'     => mb_substr( $r['anchor'], 0, 80 ),
				'href'       => $r['dest_path'],
				'now'        => [ 'path' => $infos[ $i ]['path'], 'title' => $infos[ $i ]['title'], 'kind' => hodima_seo_advisor_links_label( (string) $infos[ $i ]['kind'] ), 'note' => 'این لینک را خزش دیده؛ در متن ذخیره‌شده پیدا نشد (شورت‌کد یا قالب؟)' ],
				'times'      => 1,
				'structural' => false,
			];
			continue;
		}

		if ( 'navigation' === strtolower( $r['position'] ) && isset( $globals[ $r['dest_path'] . '|' . hodima_seo_advisor_normalize( $r['anchor'] ) ] ) ) {
			continue; // همین لینک منو را پایگاه داده با جایش نشان داده است
		}

		$key                          = $r['position'] . '|' . $r['dest_path'] . '|' . hodima_seo_advisor_normalize( $r['anchor'] );
		$grouped[ $key ]              ??= [ 'position' => $r['position'], 'dest' => $i, 'anchor' => $r['anchor'], 'sources' => [] ];
		$grouped[ $key ]['sources'][] = $r['source_path'];
	}

	foreach ( $grouped as $g ) {
		$sources = array_values( array_unique( $g['sources'] ) );
		$counts['wrong'] += 1;
		$rows[] = [
			'issue'      => 'wrong',
			'source'     => [ 'label' => 'قالب', 'title' => sprintf( 'در %s صفحه', number_format_i18n( count( $sources ) ) ), 'field' => hodima_seo_advisor_links_position_label( $g['position'] ), 'edit' => '', 'ref' => 'crawl:' . $g['position'], 'path' => '' ],
			'store'      => 'قالب (ساختاری)',
			'anchor'     => mb_substr( $g['anchor'], 0, 80 ),
			'href'       => $infos[ $g['dest'] ]['path'],
			'now'        => [ 'path' => $infos[ $g['dest'] ]['path'], 'title' => $infos[ $g['dest'] ]['title'], 'kind' => hodima_seo_advisor_links_label( (string) $infos[ $g['dest'] ]['kind'] ), 'note' => '' ],
			'times'      => count( $sources ),
			'structural' => true,
		];
	}

	// فرصت لینک: کلمه در متن هست ولی به هدف لینک ندارد (منبعی که لینک اشتباه دارد دوباره نمی‌آید)
	$opps    = [];
	$missing = hodima_seo_advisor_link_sources( $term, [ 'path' => $target['path'], 'kind' => $target['kind'], 'object_id' => (int) $target['id'] ], HODIMA_SEO_ADVISOR_LINKS_MAX_OPPS );

	foreach ( $missing['items'] as $item ) {
		$is_term = taxonomy_exists( $item['kind'] );
		$ref     = ( $is_term ? 'term:' : 'post:' ) . $item['id'];
		if ( isset( $touched[ $ref ] ) ) {
			continue;
		}
		$body = $is_term
			? (string) ( get_term( $item['id'], $item['kind'] )->description ?? '' )
			: (string) get_post_field( 'post_content', $item['id'] ) . ' ' . (string) get_post_field( 'post_excerpt', $item['id'] );
		$opps[] = [
			'source'  => [ 'label' => hodima_seo_advisor_links_label( $item['kind'] ), 'title' => $item['title'], 'edit' => $is_term ? (string) get_edit_term_link( $item['id'], $item['kind'] ) : (string) get_edit_post_link( $item['id'], 'raw' ) ],
			'snippet' => hodima_seo_advisor_links_snippet( $body, $term ),
		];
	}
	$counts['missing'] = count( $opps ) + (int) $missing['more'];

	$relations = [];
	foreach ( $rivals as $rival ) {
		$relation = hodima_seo_advisor_links_relation( $target, $rival, $term );
		if ( null !== $relation ) {
			$relations[] = $relation + [ 'title' => $rival['title'], 'path' => $rival['path'] ];
		}
	}

	$inv = [];
	foreach ( $infos as $i => $info ) {
		$inv[] = [
			'role'  => 0 === $i ? 'target' : 'rival',
			'title' => $info['title'],
			'path'  => $info['path'],
			'text'  => $inventory[ $i ]['text'],
			'related' => $inventory[ $i ]['related'],
			'menu'  => $inventory[ $i ]['menu'],
			'crawl' => $crawl['has'] ? ( $crawl['pages'][ $info['path'] ] ?? [ 'total' => 0, 'content' => 0, 'other' => 0 ] ) : null,
		];
	}

	$order = static fn( array $r ): int => $r['structural'] ? 2 : ( 'wrong' === $r['issue'] ? 0 : 1 );
	/** @var list<array<string, mixed>> $rows */
	usort( $rows, static fn( array $a, array $b ): int => $order( $a ) <=> $order( $b ) );

	$result = [
		'term'      => $term,
		'target'    => $target,
		'rivals'    => $rivals,
		'relations' => $relations,
		'inventory' => $inv,
		'has_crawl' => $crawl['has'],
		'counts'    => $counts,
		'rows'      => array_slice( $rows, 0, HODIMA_SEO_ADVISOR_LINKS_MAX_ROWS ),
		'total'     => count( $rows ),
		'opps'      => $opps,
		'capped'    => $html['capped'],
		'router'    => function_exists( 'hodima_router_resolve_url' ) && function_exists( 'hodima_router_active' ) && hodima_router_active(),
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
