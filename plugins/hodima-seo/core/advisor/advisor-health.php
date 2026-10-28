<?php
/**
 * ماژول «دستار سئو» — سلامت آدرس‌ها: صفحه‌های بدون نمایش و آدرس‌های مرده‌ای که هنوز در گوگل‌اند
 * Path: core/advisor/advisor-health.php
 *
 *  ۱. صفحه‌های بدون نمایش: نوشته/برگه/محصول منتشرشده (غیر noindex، بدون رمز) که در بازه اخیر هیچ نمایشی
 *     در گوگل نگرفته‌اند؛ یعنی ایندکس نشده‌اند یا اصلا دیده نمی‌شوند. صفحه تازه (کمتر از ۳۰ روز) جدا
 *     شمرده می‌شود چون هنوز زود است.
 *  ۲. آدرس‌های مرده: آدرسی که در گوگل نمایش می‌گیرد ولی در خود سایت ۴۰۴ است، حذف عمدی (۴۰۴/۴۱۰)
 *     شده، ریدایرکت می‌شود یا ریدایرکتش به مقصد خراب می‌رود. بررسی با تابع روتر سایت
 *     (hodima_router_inspect_url) است، بدون درخواست HTTP به گوگل و بدون درخواست به خود سایت.
 * هر بار بعد از دریافت موفق داده ساخته و در گزینه hodima_advisor_health ذخیره می‌شود (autoload خاموش)؛
 * فقط‌خواندنی است و چیزی را عوض نمی‌کند. «پاک کردن داده‌ها» آن را هم پاک می‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_HEALTH_OPTION = 'hodima_advisor_health';

/** صفحه کمتر از این روز «تازه» است و بدون نمایش بودنش مشکل حساب نمی‌شود. */
const HODIMA_SEO_ADVISOR_HEALTH_FRESH_DAYS = 30;

/** بیشترین صفحه بررسی‌شده برای آدرس مرده (پرنمایش‌ترین‌ها) و حداکثر مورد ذخیره‌شده. */
const HODIMA_SEO_ADVISOR_HEALTH_DEAD_SCAN = 200;
const HODIMA_SEO_ADVISOR_HEALTH_KEEP      = 60;

/**
 * تابع بررسی آدرس روتر آماده است؟ report.php روتر فقط در پیشخوان لود می‌شود، ولی دریافت داده با کرون
 * (بیرون از پیشخوان) هم اجرا می‌شود؛ پس اینجا (اگر ماژول آدرس تمیز روشن است) لود می‌شود. وابستگی
 * رابط کاربری ندارد.
 */
function hodima_seo_advisor_health_router_ready(): bool {

	if ( ! function_exists( 'hodima_router_inspect_url' ) && defined( 'HODIMA_ROUTER_DIR' ) && is_file( HODIMA_ROUTER_DIR . '/report.php' ) ) {
		require_once HODIMA_ROUTER_DIR . '/report.php';
	}

	return function_exists( 'hodima_router_inspect_url' );
}

/** وضعیت‌های آدرس مرده که کار دارند (بقیه ریدایرکت سالم یا حذف عمدی‌اند). */
const HODIMA_SEO_ADVISOR_HEALTH_BAD = [ 'missing', 'rule_broken' ];

/**
 * وضعیت یک آدرس در خود سایت با روتر (بدون درخواست HTTP). state خالی = صفحه سالم؛ بقیه مثل فهرست پایین.
 * جدا شد تا «انجام شد» روی یک آدرس مرده همان لحظه دوباره بسنجد (advisor-marks.php).
 *
 * @return array{state: string, target: string, code: int}
 */
function hodima_seo_advisor_health_dead_state( string $path ): array {

	if ( ! hodima_seo_advisor_health_router_ready() ) {
		return [ 'state' => '', 'target' => '', 'code' => 0 ];
	}

	$check = hodima_router_inspect_url( hodima_seo_advisor_url( $path ) );
	$next  = $check['next'] ?? null;
	$state = match ( (string) $check['status'] ) {
		'missing'  => 'missing',
		'rule'     => in_array( (int) $check['code'], [ 404, 410 ], true ) ? 'gone' : ( is_array( $next ) && 'missing' === ( $next['status'] ?? '' ) ? 'rule_broken' : 'rule' ),
		'redirect' => 'redirect',
		default    => '',
	};

	return [ 'state' => $state, 'target' => (string) ( $check['target'] ?? '' ), 'code' => (int) ( $check['code'] ?? 0 ) ];
}

/**
 * آدرس‌های پرنمایش گوگل که در خود سایت مرده‌اند یا ریدایرکت می‌شوند.
 *
 * state: missing (۴۰۴)، gone (حذف عمدی ۴۰۴/۴۱۰)، rule_broken (قانون ریدایرکت به مقصد خراب)،
 *        rule (ریدایرکت قانون‌دار سالم)، redirect (ریدایرکت ۳۰۱ روتر به آدرس اصلی).
 *
 * @return list<array{path: string, impressions: int, clicks: int, state: string, target: string, code: int}>
 */
function hodima_seo_advisor_health_dead_urls(): array {

	global $wpdb;

	if ( ! hodima_seo_advisor_health_router_ready() ) {
		return [];
	}

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "SELECT path, impressions, clicks FROM %i WHERE impressions >= 20 AND path <> '/' ORDER BY impressions DESC LIMIT %d", hodima_seo_advisor_table( 'pages' ), HODIMA_SEO_ADVISOR_HEALTH_DEAD_SCAN ),
		ARRAY_A
	);

	$out = [];

	foreach ( $rows as $row ) {

		$path = (string) $row['path'];

		if ( 1 === preg_match( '#/page/\d+/?$#', $path ) ) {
			continue;
		}

		$check = hodima_seo_advisor_health_dead_state( $path );
		$state = $check['state'];

		if ( '' !== $state ) {
			$out[] = [
				'path'        => $path,
				'impressions' => (int) $row['impressions'],
				'clicks'      => (int) $row['clicks'],
				'state'       => $state,
				'target'      => $check['target'],
				'code'        => $check['code'],
			];
		}
	}

	$weight = [ 'missing' => 0, 'rule_broken' => 1, 'gone' => 2, 'redirect' => 3, 'rule' => 3 ];
	usort( $out, static fn( array $a, array $b ): int => [ $weight[ $a['state'] ], $b['impressions'] ] <=> [ $weight[ $b['state'] ], $a['impressions'] ] );

	return array_slice( $out, 0, HODIMA_SEO_ADVISOR_HEALTH_KEEP );
}

/**
 * نوشته/برگه/محصول‌های منتشرشده‌ای که در بازه اخیر نمایشی نگرفته‌اند.
 *
 * @return array{total: int, seen: int, fresh: int, unseen: list<array{id: int, path: string, title: string, type: string, days: int}>}
 */
function hodima_seo_advisor_health_unseen(): array {

	global $wpdb;

	$types = hodima_seo_advisor_box_post_types_all();

	if ( ! $types ) {
		return [ 'total' => 0, 'seen' => 0, 'fresh' => 0, 'unseen' => [] ];
	}

	$noindex = function_exists( 'hodima_get_noindex_post_ids' ) ? array_map( 'intval', (array) hodima_get_noindex_post_ids() ) : [];

	$ids = get_posts( [
		'post_type'              => $types,
		'post_status'            => 'publish',
		'has_password'           => false,
		'posts_per_page'         => 3000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- فقط شناسه‌ها و فقط در دریافت روزانه (نه بازدید سایت)
		'fields'                 => 'ids',
		'orderby'                => 'date',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'post__not_in'           => $noindex,
	] );

	$seen_paths = array_flip( array_map( 'strval', (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT path FROM %i WHERE impressions > 0', hodima_seo_advisor_table( 'pages' ) )
	) ) );

	$now    = time();
	$result = [ 'total' => 0, 'seen' => 0, 'fresh' => 0, 'unseen' => [] ];

	foreach ( $ids as $id ) {

		$path = hodima_seo_advisor_path_key( (string) get_permalink( (int) $id ) );
		if ( null === $path ) {
			continue;
		}

		++$result['total'];

		if ( isset( $seen_paths[ $path ] ) ) {
			++$result['seen'];
			continue;
		}

		$days = (int) floor( ( $now - (int) get_post_time( 'U', true, (int) $id ) ) / DAY_IN_SECONDS );

		if ( $days < HODIMA_SEO_ADVISOR_HEALTH_FRESH_DAYS ) {
			++$result['fresh'];
			continue;
		}

		$result['unseen'][] = [ 'id' => (int) $id, 'path' => $path, 'title' => (string) get_the_title( (int) $id ), 'type' => (string) get_post_type( (int) $id ), 'days' => $days ];
	}

	usort( $result['unseen'], static fn( array $a, array $b ): int => $b['days'] <=> $a['days'] );
	$result['unseen'] = array_slice( $result['unseen'], 0, 100 );

	return $result;
}

/**
 * نتیجه ذخیره‌شده (خالی اگر هنوز ساخته نشده).
 *
 * @return array<string, mixed>
 */
function hodima_seo_advisor_health(): array {
	$stored = get_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [] );
	return is_array( $stored ) ? $stored : [];
}

/** هر دو بخش را می‌سازد و ذخیره می‌کند (بعد از دریافت موفق داده). */
function hodima_seo_advisor_health_sync(): void {

	if ( ! (bool) apply_filters( 'hodima_seo_advisor_health_enabled', true ) ) {
		return;
	}

	$unseen = hodima_seo_advisor_health_unseen();

	update_option(
		HODIMA_SEO_ADVISOR_HEALTH_OPTION,
		[
			'computed' => time(),
			'router'   => hodima_seo_advisor_health_router_ready(),
			'dead'     => hodima_seo_advisor_health_dead_urls(),
			'total'    => $unseen['total'],
			'seen'     => $unseen['seen'],
			'fresh'    => $unseen['fresh'],
			'unseen'   => $unseen['unseen'],
		],
		false
	);

	hodima_seo_advisor_tasks_sync_health(); // کارهای «انجام شد» آدرس مرده و صفحه بدون نمایش با همین فهرست تازه
}
