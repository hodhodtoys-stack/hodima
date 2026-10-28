<?php
/**
 * ماژول «دستار سئو» — بررسی ایندکس (URL Inspection API)
 * Path: core/advisor/advisor-inspect.php
 *
 * برای آدرس‌های مهم از گوگل می‌پرسد: ایندکس شده؟ اگر نه چرا؟ canonical انتخابی گوگل همان canonical شماست؟
 * آخرین خزش کی بوده؟ همان کلید مخصوص مشاور (فقط‌خواندنی). سهمیه گوگل ≈ ۲٬۰۰۰ بررسی در روز برای هر property؛
 * ما در هر دسته فقط ۲۰ آدرس و روزی ۲ بار (کرون twicedaily) می‌پرسیم و با «۴۲۹» (سهمیه) فورا می‌ایستیم.
 *
 * صف: اول صفحه‌های بدون نمایش (advisor-health)، بعد صفحه‌های دارای پیشنهاد (کلیک بالقوه بیشتر اول)، بعد
 * پرنمایش‌ترین‌ها؛ آدرسی که در ۱۴ روز اخیر بررسی شده دوباره نمی‌آید. نتیجه در گزینه hodima_advisor_inspect
 * (autoload خاموش، حداکثر ۶۰۰ آدرس؛ قدیمی‌ترین‌ها می‌روند). فقط می‌خواند؛ چیزی را در سایت یا Search
 * Console عوض نمی‌کند. «پاک کردن داده‌ها» آن را پاک می‌کند. فیلتر hodima_seo_advisor_inspect_enabled خاموشش می‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_INSPECT_OPTION = 'hodima_advisor_inspect';
const HODIMA_SEO_ADVISOR_INSPECT_CRON   = 'hodima_advisor_inspect';
const HODIMA_SEO_ADVISOR_INSPECT_BATCH  = 20;
const HODIMA_SEO_ADVISOR_INSPECT_KEEP   = 600;
const HODIMA_SEO_ADVISOR_INSPECT_RECHECK_DAYS = 14;

/** قفل بررسی ایندکس: کلیک دستی و کرون هم‌زمان هر کدام کل گزینه نتیجه را می‌خواندند و می‌نوشتند و نتیجه یکی گم می‌شد. */
const HODIMA_SEO_ADVISOR_INSPECT_LOCK = 'hodima_advisor_inspect_lock';

add_action( HODIMA_SEO_ADVISOR_INSPECT_CRON, static function (): void {
	if ( '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR ) ) {
		hodima_seo_advisor_inspect_batch();
	}
} );

add_action( 'admin_init', static function (): void {
	$has_key   = '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR );
	$scheduled = (bool) wp_next_scheduled( HODIMA_SEO_ADVISOR_INSPECT_CRON );
	if ( $has_key && ! $scheduled ) {
		wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
	} elseif ( ! $has_key && $scheduled ) {
		wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
	}
} );

/**
 * پاسخ گوگل ← رکورد فشرده.
 *
 * @param array<string, mixed> $result بخش inspectionResult
 * @return array{verdict: string, coverage: string, robots: string, indexing: string, fetch: string, last_crawl: string, google_canonical: string, user_canonical: string, crawled_as: string, checked: int}
 */
function hodima_seo_advisor_inspect_parse( array $result ): array {

	$index = (array) ( $result['indexStatusResult'] ?? [] );

	return [
		'verdict'          => (string) ( $index['verdict'] ?? '' ),
		'coverage'         => (string) ( $index['coverageState'] ?? '' ),
		'robots'           => (string) ( $index['robotsTxtState'] ?? '' ),
		'indexing'         => (string) ( $index['indexingState'] ?? '' ),
		'fetch'            => (string) ( $index['pageFetchState'] ?? '' ),
		'last_crawl'       => (string) ( $index['lastCrawlTime'] ?? '' ),
		'google_canonical' => (string) ( $index['googleCanonical'] ?? '' ),
		'user_canonical'   => (string) ( $index['userCanonical'] ?? '' ),
		'crawled_as'       => (string) ( $index['crawledAs'] ?? '' ),
		'checked'          => time(),
	];
}

/**
 * وضعیت ساده یک رکورد: indexed، crawled_not_indexed، discovered_not_indexed، duplicate، noindex، robots،
 * redirect، soft404، not_found، server_error، unknown_to_google، other.
 *
 * @param array<string, mixed> $record
 */
function hodima_seo_advisor_inspect_state( array $record ): string {

	$coverage = strtolower( (string) $record['coverage'] );
	$indexing = strtoupper( (string) $record['indexing'] );

	return match ( true ) {
		str_contains( $coverage, 'blocked by robots' ) || 'BLOCKED_BY_ROBOTS_TXT' === $indexing => 'robots',
		str_contains( $coverage, 'noindex' ) || str_contains( $indexing, 'NOINDEX' ) => 'noindex',
		str_contains( $coverage, 'crawled - currently not indexed' ) => 'crawled_not_indexed',
		str_contains( $coverage, 'discovered - currently not indexed' ) => 'discovered_not_indexed',
		str_contains( $coverage, 'duplicate' ) || str_contains( $coverage, 'alternate page' ) => 'duplicate',
		str_contains( $coverage, 'soft 404' ) => 'soft404',
		str_contains( $coverage, 'not found' ) => 'not_found',
		str_contains( $coverage, 'server error' ) => 'server_error',
		str_contains( $coverage, 'redirect' ) => 'redirect',
		str_contains( $coverage, 'unknown to google' ) => 'unknown_to_google',
		'PASS' === strtoupper( (string) $record['verdict'] ) => 'indexed',
		default => 'other',
	};
}

/**
 * canonical گوگل با canonical خود صفحه فرق دارد؟ (هر دو معلوم باشند و بعد از یکسان‌سازی یکی نباشند)
 *
 * @param array<string, mixed> $record
 */
function hodima_seo_advisor_inspect_canonical_mismatch( array $record ): bool {

	$google = hodima_seo_advisor_path_key( (string) $record['google_canonical'] );
	$user   = hodima_seo_advisor_path_key( (string) $record['user_canonical'] );

	return null !== $google && null !== $user && $google !== $user;
}

/**
 * نتیجه‌های ذخیره‌شده (مسیر ← رکورد).
 *
 * @return array<string, array<string, mixed>>
 */
function hodima_seo_advisor_inspections(): array {
	$stored = get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [] );
	return is_array( $stored ) ? array_filter( $stored, 'is_array' ) : [];
}

/** فاصله بررسی دوباره آدرس کار «انجام شد» تا وقتی خزش بعد از تغییر دیده شود (روز). */
const HODIMA_SEO_ADVISOR_INSPECT_TASK_RECHECK_DAYS = 2;

/**
 * گوگل صفحه را بعد از یک زمان (مثلا روز تغییر مدیر) دوباره خزیده؟ از آخرین بررسی ایندکس همان آدرس.
 *
 * @return array{state: string, crawled: int, checked: int} state: yes، no (آخرین خزش پیش از آن)، unknown (بررسی نشده یا بی‌تاریخ)
 */
function hodima_seo_advisor_inspect_after( string $path, int $since ): array {

	$record  = hodima_seo_advisor_inspections()[ $path ] ?? null;
	$crawled = null !== $record && '' !== (string) ( $record['last_crawl'] ?? '' ) ? (int) strtotime( (string) $record['last_crawl'] ) : 0;
	$checked = null !== $record ? (int) ( $record['checked'] ?? 0 ) : 0;

	return [
		'state'   => $crawled <= 0 ? 'unknown' : ( $crawled >= $since ? 'yes' : 'no' ),
		'crawled' => $crawled,
		'checked' => $checked,
	];
}

/**
 * آدرس‌های منتظر بررسی (به ترتیب اولویت).
 *
 * @return list<string> مسیرها
 */
function hodima_seo_advisor_inspect_queue( int $limit = HODIMA_SEO_ADVISOR_INSPECT_BATCH ): array {

	global $wpdb;

	$done   = hodima_seo_advisor_inspections();
	$cutoff = time() - HODIMA_SEO_ADVISOR_INSPECT_RECHECK_DAYS * DAY_IN_SECONDS;
	$queue  = [];

	$add = static function ( string $path ) use ( &$queue, $done, $cutoff ): void {
		if ( '' === $path || 1 === preg_match( '#/page/\d+/?$#', $path ) || isset( $queue[ $path ] ) ) {
			return;
		}
		if ( isset( $done[ $path ] ) && (int) ( $done[ $path ]['checked'] ?? 0 ) > $cutoff ) {
			return;
		}
		$queue[ $path ] = true;
	};

	// اول: آدرس کارهای «انجام شد» که هنوز معلوم نیست گوگل بعد از تغییر دوباره خوانده (تب «انجام‌شده»)؛ هر ۲ روز تا دیده شود
	// کار «صفحه بدون نمایش» و «مشکل ایندکس» (SEO 3.0.2) نتیجه‌اش همین بررسی است: اولین بررسی بعد از «انجام شد» بی‌درنگ
	// ایراد فنی، رقابت و آدرس مرده سنجش خودشان را دارند و سهمیه گوگل را نمی‌گیرند
	foreach ( function_exists( 'hodima_seo_advisor_tasks' ) ? hodima_seo_advisor_tasks() : [] as $task ) {
		$seen = 'done' === $task['status'] && ( 'opportunities' === hodima_seo_advisor_task_source( $task['rule'] ) || in_array( $task['rule'], [ 'health_unseen', 'index_problem' ], true ) ) ? hodima_seo_advisor_inspect_after( $task['path'], $task['at'] ) : null;
		$asks = in_array( $task['rule'], [ 'health_unseen', 'index_problem' ], true ) && null !== $seen && $seen['checked'] < $task['at'];
		if ( null !== $seen && 'yes' !== $seen['state'] && ( $asks || $seen['checked'] < time() - HODIMA_SEO_ADVISOR_INSPECT_TASK_RECHECK_DAYS * DAY_IN_SECONDS ) && 1 !== preg_match( '#/page/\d+/?$#', $task['path'] ) ) {
			$queue[ $task['path'] ] = true;
		}
	}

	foreach ( (array) ( hodima_seo_advisor_health()['unseen'] ?? [] ) as $row ) {
		$add( (string) ( $row['path'] ?? '' ) );
	}

	$ordered = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT path FROM %i ORDER BY (advice_count > 0) DESC, potential DESC, impressions DESC LIMIT %d', hodima_seo_advisor_table( 'pages' ), 600 )
	);

	foreach ( $ordered as $path ) {
		$add( (string) $path );
	}

	return array_slice( array_keys( $queue ), 0, max( 1, $limit ) );
}

/** شمار آدرس‌های منتظر بررسی (برای نمایش). */
function hodima_seo_advisor_inspect_pending(): int {
	return count( hodima_seo_advisor_inspect_queue( 600 ) );
}

/**
 * یک دسته از صف را از گوگل می‌پرسد و ذخیره می‌کند. با سهمیه (۴۲۹) یا خطای کلید فورا می‌ایستد.
 *
 * @return array{checked: int, error: string, quota: bool}
 */
function hodima_seo_advisor_inspect_batch( int $limit = HODIMA_SEO_ADVISOR_INSPECT_BATCH ): array {

	$summary = [ 'checked' => 0, 'error' => '', 'quota' => false ];

	if ( ! (bool) apply_filters( 'hodima_seo_advisor_inspect_enabled', true ) ) {
		return $summary;
	}

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 180 );
	}

	$status   = hodima_seo_advisor_status();
	$property = '' !== $status['property'] ? $status['property'] : ( hodima_seo_sc_candidates( HODIMA_SEO_SC_ADVISOR )[0] ?? '' );

	if ( '' === $property ) {
		$summary['error'] = 'property سرچ کنسول معلوم نیست؛ اول «به‌روزرسانی همه» را بزنید.';
		return $summary;
	}

	if ( ! hodima_seo_advisor_lock( HODIMA_SEO_ADVISOR_INSPECT_LOCK ) ) {
		$summary['error'] = 'بررسی ایندکس هم‌اکنون در حال اجراست؛ چند دقیقه بعد دوباره امتحان کنید.';
		return $summary;
	}

	try {
		return hodima_seo_advisor_inspect_run( $property, $limit, $summary );
	} finally {
		hodima_seo_advisor_unlock( HODIMA_SEO_ADVISOR_INSPECT_LOCK );
	}
}

/**
 * بدنه یک دسته بررسی ایندکس (بعد از گرفتن قفل).
 *
 * @param array{checked: int, error: string, quota: bool} $summary
 * @return array{checked: int, error: string, quota: bool}
 */
function hodima_seo_advisor_inspect_run( string $property, int $limit, array $summary ): array {

	$stored = hodima_seo_advisor_inspections();

	foreach ( hodima_seo_advisor_inspect_queue( $limit ) as $path ) {

		$result = hodima_seo_sc_inspect_url( $property, hodima_seo_advisor_url( $path ), HODIMA_SEO_SC_ADVISOR );

		if ( is_wp_error( $result ) ) {
			$summary['quota'] = 429 === (int) ( $result->get_error_data()['status'] ?? 0 );
			if ( $summary['quota'] || hodima_seo_sc_is_key_error( $result ) ) {
				$summary['error'] = $summary['quota'] ? 'سهمیه روزانه بررسی ایندکس گوگل تمام شد؛ فردا ادامه می‌یابد.' : $result->get_error_message();
				break;
			}
			continue; // خطای همین آدرس؛ بقیه ادامه دارند
		}

		$stored[ $path ] = hodima_seo_advisor_inspect_parse( $result );
		++$summary['checked'];
	}

	if ( count( $stored ) > HODIMA_SEO_ADVISOR_INSPECT_KEEP ) {
		uasort( $stored, static fn( array $a, array $b ): int => (int) ( $b['checked'] ?? 0 ) <=> (int) ( $a['checked'] ?? 0 ) );
		$stored = array_slice( $stored, 0, HODIMA_SEO_ADVISOR_INSPECT_KEEP, true );
	}

	update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $stored, false );

	if ( $summary['checked'] > 0 ) {
		hodima_seo_advisor_tasks_sync_inspected(); // کارهای «انجام شد» ایندکس و صفحه بدون نمایش با نتیجه تازه
	}

	return $summary;
}

add_action( 'admin_post_hodima_advisor_inspect_now', 'hodima_seo_advisor_handle_inspect_now' );

function hodima_seo_advisor_handle_inspect_now(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_inspect_now' );

	$summary = hodima_seo_advisor_inspect_batch();

	if ( '' !== $summary['error'] ) {
		hodima_admin_flash( '<strong>بررسی ایندکس:</strong> ' . esc_html( $summary['error'] ) . ( $summary['checked'] > 0 ? ' ' . esc_html( sprintf( '(%s آدرس بررسی شد)', number_format_i18n( $summary['checked'] ) ) ) : '' ), 'error' );
	} else {
		hodima_admin_flash( '<strong>بررسی ایندکس:</strong> ' . esc_html( sprintf( '%s آدرس بررسی شد.', number_format_i18n( $summary['checked'] ) ) ), 'success' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . HODIMA_SEO_ADVISOR_PAGE . '&tab=indexing' ) );
	exit;
}
