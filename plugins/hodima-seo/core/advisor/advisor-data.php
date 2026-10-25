<?php
/**
 * ماژول «مشاور سئو» — داده: جدول‌ها، یکسان‌سازی آدرس، ذخیره و خواندن
 * Path: core/advisor/advisor-data.php
 *
 * دو جدول (هر دریافت جدید همه ردیف‌ها را عوض می‌کند، نه افزودن):
 *   hodima_advisor_pages    یک ردیف برای هر آدرس: کلیک، نمایش، رتبه برای بازه فعلی و
 *                           بازه قبلی هم‌اندازه (مقایسه)، به‌علاوه نوع و شناسه شیء سایت
 *   hodima_advisor_queries  کلمه‌های هر آدرس (حداکثر ۵۰ کلمه پرنمایش)
 *
 * آدرس همیشه «مسیر یکسان‌شده» است (hodima_seo_advisor_path_key): بدون دامنه، پارامتر و
 * #بخش، رمزگشایی‌شده، حروف لاتین کوچک؛ آدرس‌های هم‌مسیر (با #toc، www، http) یکی
 * می‌شوند و رتبه با وزن نمایش میانگین گرفته می‌شود.
 *
 * هیچ بازدیدی از سایت به گوگل درخواست نمی‌زند (دریافت: advisor-refresh.php).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** نسخه ساختار جدول‌ها (با تغییر جدول بالا برود). */
const HODIMA_SEO_ADVISOR_DB_VERSION = '1.2.0';

const HODIMA_SEO_ADVISOR_VERSION_OPTION  = 'hodima_advisor_db_version';
const HODIMA_SEO_ADVISOR_SETTINGS_OPTION = 'hodima_advisor_settings';
const HODIMA_SEO_ADVISOR_STATUS_OPTION   = 'hodima_advisor_status';

/** آدرس‌هایی که مدیر «نادیده گرفتن» زده (مثلا صفحه‌های برندی مثل /about-us/): پیشنهاد ندارند. */
const HODIMA_SEO_ADVISOR_IGNORED_OPTION = 'hodima_advisor_ignored';

/** گروه‌های فهرست پیشخوان ← نوع شیء‌ها. */
const HODIMA_SEO_ADVISOR_KIND_GROUPS = [
	'product'  => [ 'product' ],
	'category' => [ 'product_cat', 'category' ],
	'content'  => [ 'post', 'page' ],
];

/** بازه‌های مجاز (روز). */
const HODIMA_SEO_ADVISOR_DAYS = [ 28, 90, 180 ];

/** حداکثر کلمه ذخیره‌شده برای هر آدرس. */
const HODIMA_SEO_ADVISOR_QUERIES_PER_PAGE = 50;

/** کلمه‌ای که کمتر از این بار نمایش داشته باشد ذخیره نمی‌شود (نویز). */
const HODIMA_SEO_ADVISOR_MIN_QUERY_IMPRESSIONS = 5;

/** نوع و شناسه شیء فقط برای این تعداد آدرس پرنمایش پیدا می‌شود (هزینه روتر). */
const HODIMA_SEO_ADVISOR_CLASSIFY_LIMIT = 1500;

/** حداکثر طول آدرس و کلمه (ستون varchar(191)). */
const HODIMA_SEO_ADVISOR_KEY_LENGTH = 191;

/** نام کامل جدول: «pages» یا «queries». */
function hodima_seo_advisor_table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'hodima_advisor_' . $name;
}

/** بازه فعلی (روز): ۲۸، ۹۰ یا ۱۸۰؛ پیش‌فرض ۹۰. */
function hodima_seo_advisor_days(): int {
	$settings = get_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [] );
	$days     = is_array( $settings ) ? (int) ( $settings['days'] ?? 90 ) : 90;
	return in_array( $days, HODIMA_SEO_ADVISOR_DAYS, true ) ? $days : 90;
}

/* ── جدول‌ها ── */

function hodima_seo_advisor_install_tables(): void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$pages   = hodima_seo_advisor_table( 'pages' );
	$queries = hodima_seo_advisor_table( 'queries' );

	dbDelta(
		"CREATE TABLE {$pages} (
		path varchar(191) NOT NULL,
		clicks int(10) unsigned NOT NULL DEFAULT 0,
		impressions int(10) unsigned NOT NULL DEFAULT 0,
		avg_position decimal(6,2) NOT NULL DEFAULT 0,
		prev_clicks int(10) unsigned NOT NULL DEFAULT 0,
		prev_impressions int(10) unsigned NOT NULL DEFAULT 0,
		prev_position decimal(6,2) NOT NULL DEFAULT 0,
		kind varchar(32) NOT NULL DEFAULT '',
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(191) NOT NULL DEFAULT '',
		potential int(10) unsigned NOT NULL DEFAULT 0,
		advice_count smallint(5) unsigned NOT NULL DEFAULT 0,
		advice longtext NOT NULL,
		PRIMARY KEY (path),
		KEY impressions_idx (impressions),
		KEY potential_idx (potential)
		) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$queries} (
		path varchar(191) NOT NULL,
		term varchar(191) NOT NULL,
		clicks int(10) unsigned NOT NULL DEFAULT 0,
		impressions int(10) unsigned NOT NULL DEFAULT 0,
		avg_position decimal(6,2) NOT NULL DEFAULT 0,
		PRIMARY KEY (path, term)
		) {$charset};"
	);

	update_option( HODIMA_SEO_ADVISOR_VERSION_OPTION, HODIMA_SEO_ADVISOR_DB_VERSION, false );
}

/** ساخت/ارتقای جدول‌ها اگر لازم است (کرون و پیشخوان؛ ارزان: یک get_option). */
function hodima_seo_advisor_ensure_tables(): void {
	if ( version_compare( (string) get_option( HODIMA_SEO_ADVISOR_VERSION_OPTION, '0' ), HODIMA_SEO_ADVISOR_DB_VERSION, '<' ) ) {
		hodima_seo_advisor_install_tables();
	}
}

add_action( 'admin_init', 'hodima_seo_advisor_ensure_tables' );

/* ── یکسان‌سازی ── */

/**
 * آدرس صفحه (از سرچ کنسول) ← مسیر یکسان‌شده، یا null (دامنه دیگر، بلندتر از ستون).
 *
 *   https://www.example.com/Hair/#toc-1 ← /hair/
 *   %D8%A7… ← رمزگشایی‌شده (آدرس فارسی)
 */
function hodima_seo_advisor_path_key( string $url ): ?string {

	$parts = wp_parse_url( trim( $url ) );

	if ( ! is_array( $parts ) ) {
		return null;
	}

	$bare = static fn( string $host ): string => (string) preg_replace( '/^www\./', '', strtolower( $host ) );
	$host = (string) ( $parts['host'] ?? '' );

	if ( '' !== $host && $bare( $host ) !== $bare( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
		return null;
	}

	$path = '/' . ltrim( strtolower( rawurldecode( (string) ( $parts['path'] ?? '/' ) ) ), '/' );

	return mb_strlen( $path ) > HODIMA_SEO_ADVISOR_KEY_LENGTH ? null : $path;
}

/**
 * ردیف‌های صفحه‌ای سرچ کنسول ← مسیر ← کلیک/نمایش/رتبه (آدرس‌های هم‌مسیر یکی؛ رتبه با وزن نمایش).
 *
 * @param list<array<string, mixed>> $rows
 * @return array<string, array{clicks: int, impressions: int, position: float}>
 */
function hodima_seo_advisor_aggregate_pages( array $rows ): array {

	$sums = [];

	foreach ( $rows as $row ) {
		$path = hodima_seo_advisor_path_key( (string) ( $row['keys'][0] ?? '' ) );
		if ( null === $path ) {
			continue;
		}
		$impressions = (int) round( (float) ( $row['impressions'] ?? 0 ) );
		$sums[ $path ] ??= [ 'clicks' => 0, 'impressions' => 0, 'weighted' => 0.0 ];
		$sums[ $path ]['clicks']      += (int) round( (float) ( $row['clicks'] ?? 0 ) );
		$sums[ $path ]['impressions'] += $impressions;
		$sums[ $path ]['weighted']    += (float) ( $row['position'] ?? 0 ) * $impressions;
	}

	return array_map(
		static fn( array $s ): array => [
			'clicks'      => $s['clicks'],
			'impressions' => $s['impressions'],
			'position'    => $s['impressions'] > 0 ? round( $s['weighted'] / $s['impressions'], 2 ) : 0.0,
		],
		$sums
	);
}

/**
 * ردیف‌های صفحه + کلمه ← مسیر ← کلمه ← داده؛ کلمه کم‌نمایش حذف و برای هر مسیر
 * حداکثر ۵۰ کلمه پرنمایش می‌ماند.
 *
 * @param list<array<string, mixed>> $rows
 * @return array<string, array<string, array{clicks: int, impressions: int, position: float}>>
 */
function hodima_seo_advisor_aggregate_queries( array $rows ): array {

	$sums = [];

	foreach ( $rows as $row ) {
		$path = hodima_seo_advisor_path_key( (string) ( $row['keys'][0] ?? '' ) );
		$term = trim( (string) ( $row['keys'][1] ?? '' ) );
		if ( null === $path || '' === $term || mb_strlen( $term ) > HODIMA_SEO_ADVISOR_KEY_LENGTH ) {
			continue;
		}
		// کلمه‌هایی که فقط در اعراب، کشیده، حروف بزرگ یا ی/ک عربی فرق دارند یکی می‌شوند؛ ستون term
		// با مقایسه بدون حساسیت به این تفاوت‌ها کلید اصلی است و دو کلمه «متفاوت» آن را می‌شکستند
		$key         = hodima_seo_advisor_normalize( $term );
		$key         = '' !== $key ? $key : $term;
		$impressions = (int) round( (float) ( $row['impressions'] ?? 0 ) );
		$sums[ $path ][ $key ] ??= [ 'term' => $term, 'clicks' => 0, 'impressions' => 0, 'weighted' => 0.0 ];
		$sums[ $path ][ $key ]['clicks']      += (int) round( (float) ( $row['clicks'] ?? 0 ) );
		$sums[ $path ][ $key ]['impressions'] += $impressions;
		$sums[ $path ][ $key ]['weighted']    += (float) ( $row['position'] ?? 0 ) * $impressions;
	}

	$out = [];

	foreach ( $sums as $path => $terms ) {
		$terms = array_filter( $terms, static fn( array $s ): bool => $s['impressions'] >= HODIMA_SEO_ADVISOR_MIN_QUERY_IMPRESSIONS );
		uasort( $terms, static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );
		foreach ( array_slice( $terms, 0, HODIMA_SEO_ADVISOR_QUERIES_PER_PAGE, true ) as $s ) {
			$out[ $path ][ $s['term'] ] = [
				'clicks'      => $s['clicks'],
				'impressions' => $s['impressions'],
				'position'    => round( $s['weighted'] / $s['impressions'], 2 ),
			];
		}
	}

	return $out;
}

/**
 * مسیر ← نوع و شناسه شیء سایت (محصول، دسته، نوشته، برگه) با روتر آدرس تمیز.
 * نوع = نوع پست یا تاکسونومی (product، product_cat، post، page، category …)؛
 * صفحه اصلی «home»؛ ناشناخته: رشته خالی.
 *
 * @return array{kind: string, id: int}
 */
function hodima_seo_advisor_classify( string $path ): array {

	if ( '/' === $path ) {
		return [ 'kind' => 'home', 'id' => 0 ];
	}

	if ( function_exists( 'hodima_router_resolve_url' ) ) {
		$match = hodima_router_resolve_url( home_url( $path ), true );
		if ( null !== $match ) {
			return [ 'kind' => (string) $match['type'], 'id' => (int) $match['id'] ];
		}
	}

	return [ 'kind' => '', 'id' => 0 ];
}

/**
 * عنوان صفحه برای نمایش در پیشخوان: نام محصول/نوشته/برگه یا نام دسته (همان H1 صفحه، نه عنوان سئو)،
 * یا رشته خالی اگر شیء پیدا نشد.
 */
function hodima_seo_advisor_object_title( string $kind, int $id ): string {

	if ( $id <= 0 || '' === $kind ) {
		return '';
	}

	if ( taxonomy_exists( $kind ) ) {
		$term  = get_term( $id, $kind );
		$title = $term instanceof WP_Term ? $term->name : '';
	} elseif ( post_type_exists( $kind ) ) {
		$post  = get_post( $id );
		$title = $post instanceof WP_Post ? $post->post_title : '';
	} else {
		return '';
	}

	return mb_substr( trim( html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ), 0, HODIMA_SEO_ADVISOR_KEY_LENGTH );
}

/* ── تراکنش و قفل ── */

/** کلید قفل دریافت داده و حداکثر عمر آن (ثانیه)؛ قفل مانده از اجرای نیمه‌کاره بعد از این مدت آزاد می‌شود. */
const HODIMA_SEO_ADVISOR_LOCK_OPTION = 'hodima_advisor_lock';
const HODIMA_SEO_ADVISOR_LOCK_TTL    = 600;

/**
 * قفل دریافت داده (کلیک دستی و کرون هم‌زمان نشوند). add_option اتمی است: دومی شکست می‌خورد.
 */
function hodima_seo_advisor_lock(): bool {

	$now = time();

	if ( add_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, $now, '', false ) ) {
		return true;
	}

	if ( $now - (int) get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, 0 ) > HODIMA_SEO_ADVISOR_LOCK_TTL ) {
		update_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, $now, false ); // اجرای قبلی وسط کار مرده
		return true;
	}

	return false;
}

function hodima_seo_advisor_unlock(): void {
	delete_option( HODIMA_SEO_ADVISOR_LOCK_OPTION );
}

/**
 * کار را در یک تراکنش اجرا می‌کند: اگر WP_Error برگرداند یا استثنا بیندازد، همه تغییرها برمی‌گردند
 * (داده قبلی سالم می‌ماند). تودرتو امن است: فقط بیرونی‌ترین فراخوانی COMMIT/ROLLBACK می‌زند و
 * اگر درونی خطا بدهد، نتیجه خطا همان‌جا به بیرونی می‌رسد. روی موتور بدون تراکنش (MyISAM) کار
 * همان‌طور اجرا می‌شود ولی برگشت‌پذیر نیست.
 *
 * @template T
 * @param callable(): T $work
 * @return T|WP_Error
 */
function hodima_seo_advisor_transaction( callable $work ): mixed {

	global $wpdb;
	static $depth = 0;

	if ( $depth > 0 ) {
		return $work();
	}

	++$depth;
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	try {
		$result = $work();
	} catch ( Throwable $e ) {
		$result = new WP_Error( 'hodima_advisor_exception', 'خطای داخلی: ' . $e->getMessage() );
	}

	$end = is_wp_error( $result ) ? 'ROLLBACK' : 'COMMIT';
	$wpdb->query( $end ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- فقط دو رشته ثابت
	--$depth;

	return $result;
}

/* ── ذخیره ── */

/**
 * جایگزینی کامل داده: آدرس‌های بازه فعلی و قبلی (آدرسی که فقط قبلا نمایش داشته
 * هم می‌ماند، با عدد فعلی صفر، برای قاعده «افت») و کلمه‌ها.
 *
 * همه‌چیز در یک تراکنش است و نتیجه هر دستور بررسی می‌شود: اگر چیزی ذخیره نشود، داده قبلی
 * سر جایش می‌ماند و WP_Error برمی‌گردد (نه داده نصفه با پیام موفقیت). ردیف تکراری که
 * مقایسه پایگاه داده آن را همان ردیف دیگر می‌بیند رد می‌شود (skipped) نه اینکه کل دسته بیفتد.
 *
 * @param array<string, array{clicks: int, impressions: int, position: float}> $current
 * @param array<string, array{clicks: int, impressions: int, position: float}> $previous
 * @param array<string, array<string, array{clicks: int, impressions: int, position: float}>> $queries
 * @return array{pages: int, queries: int, skipped: int}|WP_Error
 */
function hodima_seo_advisor_store( array $current, array $previous, array $queries ): array|WP_Error {

	global $wpdb;

	hodima_seo_advisor_ensure_tables();

	$zero  = [ 'clicks' => 0, 'impressions' => 0, 'position' => 0.0 ];
	$paths = array_keys( $current + $previous );

	// پرنمایش‌ترین‌ها اول (برای سقف شناسایی شیء)
	usort(
		$paths,
		static fn( string $a, string $b ): int => ( $current[ $b ]['impressions'] ?? $previous[ $b ]['impressions'] ?? 0 ) <=> ( $current[ $a ]['impressions'] ?? $previous[ $a ]['impressions'] ?? 0 )
	);

	$page_rows = [];
	foreach ( $paths as $i => $path ) {
		$now   = $current[ $path ] ?? $zero;
		$prev  = $previous[ $path ] ?? $zero;
		$class = $i < HODIMA_SEO_ADVISOR_CLASSIFY_LIMIT ? hodima_seo_advisor_classify( $path ) : [ 'kind' => '', 'id' => 0 ];
		$page_rows[] = [ $path, $now['clicks'], $now['impressions'], $now['position'], $prev['clicks'], $prev['impressions'], $prev['position'], $class['kind'], $class['id'], hodima_seo_advisor_object_title( $class['kind'], $class['id'] ), 0, 0, '' ];
	}

	$query_rows = [];
	foreach ( $queries as $path => $terms ) {
		foreach ( $terms as $term => $s ) {
			$query_rows[] = [ $path, $term, $s['clicks'], $s['impressions'], $s['position'] ];
		}
	}

	$pages_table   = hodima_seo_advisor_table( 'pages' );
	$queries_table = hodima_seo_advisor_table( 'queries' );

	return hodima_seo_advisor_transaction( static function () use ( $wpdb, $page_rows, $query_rows, $pages_table, $queries_table ): array|WP_Error {

		$skipped = 0;
		$fail    = static fn( string $what ): WP_Error => new WP_Error( 'hodima_advisor_store', sprintf( 'ذخیره %1$s در پایگاه داده ناموفق بود (%2$s)؛ داده قبلی دست‌نخورده ماند.', $what, $wpdb->last_error ) );

		foreach ( [ $pages_table, $queries_table ] as $table ) {
			if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $fail( 'داده' );
			}
		}

		$insert = [
			[ $pages_table, 'path, clicks, impressions, avg_position, prev_clicks, prev_impressions, prev_position, kind, object_id, title, potential, advice_count, advice', '(%s,%d,%d,%f,%d,%d,%f,%s,%d,%s,%d,%d,%s)', $page_rows, 'آدرس‌ها' ],
			[ $queries_table, 'path, term, clicks, impressions, avg_position', '(%s,%s,%d,%d,%f)', $query_rows, 'کلمه‌ها' ],
		];

		foreach ( $insert as [ $table, $columns, $row_format, $rows, $what ] ) {
			foreach ( array_chunk( $rows, 200 ) as $chunk ) {
				if ( false !== hodima_seo_advisor_insert( $table, $columns, $row_format, $chunk ) ) {
					continue;
				}
				// دسته افتاد (معمولا یک ردیف تکراری): تک‌تک؛ ردیف مشکل‌دار رد می‌شود نه همه
				foreach ( $chunk as $one ) {
					if ( false === hodima_seo_advisor_insert( $table, $columns, $row_format, [ $one ] ) ) {
						++$skipped;
					}
				}
			}
			if ( $skipped > max( 5, (int) ( count( $rows ) * 0.05 ) ) ) {
				return $fail( $what );
			}
		}

		return [ 'pages' => count( $page_rows ), 'queries' => count( $query_rows ) - $skipped, 'skipped' => $skipped ];
	} );
}

/**
 * درج چند ردیف هم‌قالب در یک دستور؛ false اگر پایگاه داده خطا داد.
 *
 * @param list<list<scalar>> $rows
 */
function hodima_seo_advisor_insert( string $table, string $columns, string $row_format, array $rows ): int|bool {

	global $wpdb;

	return $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- تعداد placeholderها با array_fill هم‌اندازه ردیف‌هاست و ستون‌ها/قالب از فراخواننده‌های همین فایل‌اند، نه ورودی کاربر
			'INSERT INTO %i (' . $columns . ') VALUES ' . implode( ',', array_fill( 0, count( $rows ), $row_format ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- ستون‌ها و قالب ردیف از فراخواننده‌های همین فایل‌اند، نه ورودی کاربر
			$table,
			...array_merge( ...$rows )
		)
	);
}

/* ── خواندن ── */

/**
 * ردیف جدول pages ← آرایه با نوع‌های درست.
 *
 * @param array<string, mixed> $row
 * @return array{path: string, clicks: int, impressions: int, position: float, prev_clicks: int, prev_impressions: int, prev_position: float, kind: string, object_id: int, title: string, potential: int, advice_count: int, advice: list<array<string, mixed>>}
 */
function hodima_seo_advisor_page_row( array $row ): array {
	$advice = json_decode( (string) $row['advice'], true );
	return [
		'path'             => (string) $row['path'],
		'clicks'           => (int) $row['clicks'],
		'impressions'      => (int) $row['impressions'],
		'position'         => (float) $row['avg_position'],
		'prev_clicks'      => (int) $row['prev_clicks'],
		'prev_impressions' => (int) $row['prev_impressions'],
		'prev_position'    => (float) $row['prev_position'],
		'kind'             => (string) $row['kind'],
		'object_id'        => (int) $row['object_id'],
		'title'            => (string) $row['title'],
		'potential'        => (int) $row['potential'],
		'advice_count'     => (int) $row['advice_count'],
		'advice'           => is_array( $advice ) ? array_values( $advice ) : [],
	];
}

/**
 * فهرست آدرس‌ها با فیلتر و صفحه‌بندی.
 *
 * @param array{min_impressions?: int, kind?: string|list<string>, search?: string, has_advice?: bool, rule?: string, paths?: list<string>, orderby?: string, order?: string, limit?: int, offset?: int} $args
 * @return array{rows: list<array<string, mixed>>, total: int}
 */
function hodima_seo_advisor_pages( array $args = [] ): array {

	global $wpdb;

	$table  = hodima_seo_advisor_table( 'pages' );
	$where  = [];
	$params = [];

	if ( ! empty( $args['min_impressions'] ) ) {
		$where[]  = 'impressions >= %d';
		$params[] = (int) $args['min_impressions'];
	}

	if ( ! empty( $args['has_advice'] ) ) {
		$where[] = 'advice_count > 0';
	}

	if ( 1 === preg_match( '/^[a-z_]+$/', (string) ( $args['rule'] ?? '' ) ) ) {
		$where[]  = 'advice LIKE %s';
		$params[] = '%' . $wpdb->esc_like( '"rule":"' . $args['rule'] . '"' ) . '%';
	}

	if ( isset( $args['paths'] ) ) {
		$paths = array_values( array_filter( (array) $args['paths'], 'is_string' ) );
		if ( $paths ) {
			$where[] = 'path IN (' . implode( ',', array_fill( 0, count( $paths ), '%s' ) ) . ')';
			array_push( $params, ...$paths );
		} else {
			$where[] = '1 = 0';
		}
	}

	$kinds = array_values( array_filter( (array) ( $args['kind'] ?? [] ), static fn( mixed $k ): bool => is_string( $k ) && '' !== $k ) );
	if ( $kinds ) {
		$where[] = 'kind IN (' . implode( ',', array_fill( 0, count( $kinds ), '%s' ) ) . ')';
		array_push( $params, ...$kinds );
	}

	if ( '' !== trim( (string) ( $args['search'] ?? '' ) ) ) {
		$where[]  = '(path LIKE %s OR title LIKE %s)';
		$params[] = '%' . $wpdb->esc_like( strtolower( trim( (string) $args['search'] ) ) ) . '%';
		$params[] = '%' . $wpdb->esc_like( trim( (string) $args['search'] ) ) . '%';
	}

	$clause  = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';
	$orderby = in_array( $args['orderby'] ?? '', [ 'clicks', 'impressions', 'avg_position', 'potential', 'path' ], true ) ? $args['orderby'] : 'impressions';
	$order   = 'ASC' === strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
	$limit   = max( 1, min( 500, (int) ( $args['limit'] ?? 50 ) ) );
	$offset  = max( 0, (int) ( $args['offset'] ?? 0 ) );

	$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i' . $clause, $table, ...$params ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- شرط‌ها فقط placeholder دارند و ستون مرتب‌سازی از فهرست سفید است
	$rows  = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT * FROM %i' . $clause . " ORDER BY {$orderby} {$order}, path ASC LIMIT %d OFFSET %d", $table, ...$params, ...[ $limit, $offset ] ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- شرط‌ها فقط placeholder دارند و ستون مرتب‌سازی از فهرست سفید است
		ARRAY_A
	);

	return [ 'rows' => array_map( 'hodima_seo_advisor_page_row', $rows ), 'total' => $total ];
}

/**
 * یک آدرس (مسیر یکسان‌شده یا آدرس کامل)، یا null.
 *
 * @return array<string, mixed>|null
 */
function hodima_seo_advisor_page( string $path_or_url ): ?array {

	global $wpdb;

	$path = str_contains( $path_or_url, '://' ) ? hodima_seo_advisor_path_key( $path_or_url ) : hodima_seo_advisor_path_key( home_url( '/' . ltrim( $path_or_url, '/' ) ) );

	if ( null === $path ) {
		return null;
	}

	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE path = %s', hodima_seo_advisor_table( 'pages' ), $path ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	return is_array( $row ) ? hodima_seo_advisor_page_row( $row ) : null;
}

/**
 * کلمه‌های یک آدرس، پرنمایش‌ترین اول.
 *
 * @return list<array{term: string, clicks: int, impressions: int, position: float}>
 */
function hodima_seo_advisor_queries( string $path, int $limit = 20 ): array {

	global $wpdb;

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT term, clicks, impressions, avg_position FROM %i WHERE path = %s ORDER BY impressions DESC, term ASC LIMIT %d', hodima_seo_advisor_table( 'queries' ), $path, max( 1, min( 100, $limit ) ) ),
		ARRAY_A
	);

	return array_map(
		static fn( array $r ): array => [
			'term'        => (string) $r['term'],
			'clicks'      => (int) $r['clicks'],
			'impressions' => (int) $r['impressions'],
			'position'    => (float) $r['avg_position'],
		],
		$rows
	);
}

/**
 * وضعیت آخرین دریافت.
 *
 * @return array{advised: int, fetched: int, property: string, days: int, start: string, end: string, prev_start: string, prev_end: string, pages: int, queries: int, error: string}
 */
function hodima_seo_advisor_status(): array {

	$stored = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] );
	$stored = is_array( $stored ) ? $stored : [];

	return [
		'advised'    => (int) ( $stored['advised'] ?? 0 ),
		'fetched'    => (int) ( $stored['fetched'] ?? 0 ),
		'property'   => (string) ( $stored['property'] ?? '' ),
		'days'       => (int) ( $stored['days'] ?? 0 ),
		'start'      => (string) ( $stored['start'] ?? '' ),
		'end'        => (string) ( $stored['end'] ?? '' ),
		'prev_start' => (string) ( $stored['prev_start'] ?? '' ),
		'prev_end'   => (string) ( $stored['prev_end'] ?? '' ),
		'pages'      => (int) ( $stored['pages'] ?? 0 ),
		'queries'    => (int) ( $stored['queries'] ?? 0 ),
		'error'      => (string) ( $stored['error'] ?? '' ),
	];
}

/* ── خلاصه و گروه‌ها (پیشخوان) ── */

/**
 * جمع کل آدرس‌های ذخیره‌شده.
 *
 * @return array{pages: int, advised: int, clicks: int, impressions: int, position: float}
 */
function hodima_seo_advisor_totals(): array {

	global $wpdb;

	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT COUNT(*) AS pages, SUM(advice_count > 0) AS advised, SUM(clicks) AS clicks, SUM(impressions) AS impressions, SUM(avg_position * impressions) AS weighted FROM %i', hodima_seo_advisor_table( 'pages' ) ),
		ARRAY_A
	);
	$row = is_array( $row ) ? $row : [];

	$impressions = (int) ( $row['impressions'] ?? 0 );

	return [
		'pages'       => (int) ( $row['pages'] ?? 0 ),
		'advised'     => (int) ( $row['advised'] ?? 0 ),
		'clicks'      => (int) ( $row['clicks'] ?? 0 ),
		'impressions' => $impressions,
		'position'    => $impressions > 0 ? round( (float) ( $row['weighted'] ?? 0 ) / $impressions, 1 ) : 0.0,
	];
}

/**
 * تعداد آدرس‌های دارای پیشنهاد در هر گروه (محصول، دسته، مقاله و برگه).
 *
 * @return array<string, int>
 */
function hodima_seo_advisor_group_counts(): array {

	global $wpdb;

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT kind, COUNT(*) AS n FROM %i WHERE advice_count > 0 GROUP BY kind', hodima_seo_advisor_table( 'pages' ) ),
		ARRAY_A
	);

	$out = array_fill_keys( array_keys( HODIMA_SEO_ADVISOR_KIND_GROUPS ), 0 );

	foreach ( $rows as $row ) {
		foreach ( HODIMA_SEO_ADVISOR_KIND_GROUPS as $group => $kinds ) {
			if ( in_array( (string) $row['kind'], $kinds, true ) ) {
				$out[ $group ] += (int) $row['n'];
			}
		}
	}

	return $out;
}

/** بیشترین کلیک بالقوه (برای طول نوار هر ردیف). */
function hodima_seo_advisor_max_potential(): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(potential) FROM %i', hodima_seo_advisor_table( 'pages' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/* ── نادیده گرفتن ── */

/** @return list<string> */
function hodima_seo_advisor_ignored(): array {
	$ignored = get_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [] );
	return is_array( $ignored ) ? array_values( array_filter( $ignored, 'is_string' ) ) : [];
}
