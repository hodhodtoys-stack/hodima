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
 * Console عوض نمی‌کند. «پاک کردن داده‌های دستار» آن را پاک می‌کند. فیلتر hodima_seo_advisor_inspect_enabled خاموشش می‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_INSPECT_OPTION = 'hodima_advisor_inspect';
const HODIMA_SEO_ADVISOR_INSPECT_CRON   = 'hodima_advisor_inspect';
const HODIMA_SEO_ADVISOR_INSPECT_BATCH  = 20;
const HODIMA_SEO_ADVISOR_INSPECT_KEEP   = 600;
const HODIMA_SEO_ADVISOR_INSPECT_RECHECK_DAYS = 14;

/**
 * خطای آخرین پرسش هر آدرس از گوگل (مسیر ← [at، text]).
 * تا SEO ۳.۹.۴ خطای یک آدرس فقط شمارنده را بالا می‌برد و `continue` می‌شد: آدرس رکوردی نمی‌گرفت و کارت صفحه (مثلا «بدون
 * نمایش») هیچ نشانه‌ای نداشت که وضعیت ایندکسش هنوز معلوم نیست. autoload خاموش؛ با «پاک کردن داده‌های دستار» پاک می‌شود.
 */
const HODIMA_SEO_ADVISOR_INSPECT_FAIL_OPTION = 'hodima_advisor_inspect_fail';
const HODIMA_SEO_ADVISOR_INSPECT_FAIL_KEEP   = 200;

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

/**
 * آدرس‌هایی که آخرین پرسش‌شان از گوگل خطا داد و بعدش پاسخ درست نگرفته‌اند.
 *
 * @return array<string, array{at: int, text: string}>
 */
function hodima_seo_advisor_inspect_failures(): array {

	$stored = get_option( HODIMA_SEO_ADVISOR_INSPECT_FAIL_OPTION, [] );
	$out    = [];

	foreach ( is_array( $stored ) ? $stored : [] as $path => $row ) {
		if ( is_array( $row ) && is_string( $path ) && '' !== $path ) {
			$out[ $path ] = [ 'at' => (int) ( $row['at'] ?? 0 ), 'text' => (string) ( $row['text'] ?? '' ) ];
		}
	}

	return $out;
}

/**
 * جمله کوتاه «وضعیت ایندکس» برای کارت صفحه‌ای که ایراد ایندکسی ندارد (مثلا «بدون نمایش»): ایندکس‌شده، یا هنوز پرسیده‌نشده.
 * آدرس مشکل‌دار یا خطادار کارت جدای خودش را دارد؛ پس برای آن‌ها رشته خالی. بدون کلید Search Console یا با بررسی خاموش (فیلتر hodima_seo_advisor_inspect_enabled) هم خالی: پرسیدنی در کار نیست.
 */
function hodima_seo_advisor_inspect_note( string $path ): string {

	if ( ! (bool) apply_filters( 'hodima_seo_advisor_inspect_enabled', true ) || ! function_exists( 'hodima_seo_sc_key_json' ) || '' === hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR ) ) {
		return '';
	}

	$record = hodima_seo_advisor_inspections()[ $path ] ?? null;

	if ( null !== $record ) {
		return 'indexed' === hodima_seo_advisor_inspect_state( $record ) ? 'گوگل این صفحه را ایندکس کرده؛ پس بی‌نمایش بودنش از ایندکس‌نشدن نیست.' : '';
	}

	if ( isset( hodima_seo_advisor_inspect_failures()[ $path ] ) ) {
		return '';
	}

	return 'وضعیت ایندکس این صفحه هنوز از گوگل پرسیده نشده (تب «ایندکس» ← «بررسی … آدرس بعدی»).';
}

/**
 * وضعیت هر رکورد با در نظر گرفتن خزنده خودمان: «noindex» گوگل وقتی خزنده دستار همین صفحه را الان ایندکس‌پذیر می‌بیند و آخرین
 * خزش گوگل پیش از آخرین خزش ماست، یعنی گوگل نسخه قدیمی را نگه داشته (`noindex_stale`)؛ راهش «Request indexing» است نه «noindex را بردارید».
 * اگر گوگل بعد از خزش ما هم noindex دیده، قدیمی نیست (مثلا هدر یا کش فقط برای گوگل) و همان `noindex` می‌ماند.
 * وضعیت‌های دیگر دست نمی‌خورند.
 *
 * @param array<string, array<string, mixed>> $records مسیر ← رکورد
 * @return array<string, string> مسیر ← وضعیت
 */
function hodima_seo_advisor_inspect_effective_states( array $records ): array {

	global $wpdb;

	$states  = array_map( 'hodima_seo_advisor_inspect_state', $records );
	$noindex = array_keys( array_filter( $states, static fn( string $s ): bool => 'noindex' === $s ) );
	$crawled = function_exists( 'hodima_seo_advisor_crawler_status' ) ? (int) hodima_seo_advisor_crawler_status()['finished'] : 0;

	if ( ! $noindex || $crawled <= 0 ) {
		return $states;
	}

	$marks = implode( ',', array_fill( 0, count( $noindex ), '%s' ) ); // تعداد %s هم‌اندازه مسیرها
	$live  = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( "SELECT path FROM %i WHERE indexable = 1 AND path IN ({$marks})", hodima_seo_advisor_table( 'crawl_pages' ), ...$noindex ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	);

	foreach ( $live as $path ) {
		$google = '' !== (string) ( $records[ $path ]['last_crawl'] ?? '' ) ? (int) strtotime( (string) $records[ $path ]['last_crawl'] ) : 0;
		if ( $google > 0 && $google < $crawled - DAY_IN_SECONDS ) {
			$states[ $path ] = 'noindex_stale';
		}
	}

	return $states;
}

/** فاصله بررسی دوباره آدرس کار «انجام دادم» تا وقتی خزش بعد از تغییر دیده شود (روز). */
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

	// اول: آدرس کارهای «انجام دادم» که هنوز معلوم نیست گوگل بعد از تغییر دوباره خوانده (تب «انجام‌شده»)؛ هر ۲ روز تا دیده شود
	// کار «صفحه بدون نمایش» و «مشکل ایندکس» (SEO 3.0.2) نتیجه‌اش همین بررسی است: اولین بررسی بعد از «انجام دادم» بی‌درنگ
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

/** چند خطای پی‌درپی گوگل (بدون هیچ پاسخ درست) یعنی مشکل از property یا دسترسی است، نه از یک آدرس: دسته می‌ایستد. */
const HODIMA_SEO_ADVISOR_INSPECT_FAIL_STREAK = 3;

/**
 * یک دسته از صف را از گوگل می‌پرسد و ذخیره می‌کند. با سهمیه (۴۲۹) یا خطای کلید فورا می‌ایستد.
 *
 * @return array{checked: int, error: string, quota: bool, failed: int, queued: int, fail: string} failed = آدرس‌هایی که گوگل برایشان
 *         خطا داد؛ queued = اندازه صف این دسته (۰ = همه آدرس‌ها تازه بررسی شده‌اند)؛ fail = متن آخرین خطای گوگل
 */
function hodima_seo_advisor_inspect_batch( int $limit = HODIMA_SEO_ADVISOR_INSPECT_BATCH ): array {

	$summary = [ 'checked' => 0, 'error' => '', 'quota' => false, 'failed' => 0, 'queued' => -1, 'fail' => '' ];

	if ( ! (bool) apply_filters( 'hodima_seo_advisor_inspect_enabled', true ) ) {
		return $summary;
	}

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 180 );
	}

	$status   = hodima_seo_advisor_status();
	$property = '' !== $status['property'] ? $status['property'] : ( hodima_seo_sc_candidates( HODIMA_SEO_SC_ADVISOR )[0] ?? '' );

	if ( '' === $property ) {
		$summary['error'] = 'property سرچ کنسول معلوم نیست؛ اول «خزش و داده ← دریافت داده گوگل ← اجرا همین حالا» را بزنید.';
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
 * خطای گوگل برای یک آدرس ← جمله کوتاه فارسی با کد و پیام خود گوگل.
 */
function hodima_seo_advisor_inspect_fail_text( WP_Error $error ): string {

	$status  = (int) ( $error->get_error_data()['status'] ?? 0 );
	$message = trim( $error->get_error_message() );
	$text    = ( $status > 0 ? sprintf( 'کد %s', number_format_i18n( $status ) ) : 'اتصال برقرار نشد' ) . ( '' !== $message ? '؛ پیام گوگل: «' . mb_substr( $message, 0, 200 ) . '»' : '' );

	// ۴۰۳ گوگل در این API تقریبا همیشه یعنی آدرس جزو property نیست (www/بی‌www، http/https) یا حساب سرویس در Search Console کاربر آن property نیست
	if ( 403 === $status ) {
		$text .= '. یعنی معمولا آدرس سایت جزو property انتخاب‌شده نیست (با www یا بدون www، http یا https) یا حساب سرویس در Search Console به این property دسترسی ندارد';
	}

	return $text;
}

/**
 * پیام نتیجه یک دسته (نوار پیشرفت، پیام پیشخوان و کارت «کارهای خودکار» همه همین را نشان می‌دهند).
 *
 * باگ تا SEO ۳.۸.۰: اگر گوگل برای همه آدرس‌ها خطا می‌داد (مثلا ۴۰۳ «آدرس جزو property نیست») یا صف خالی بود، خطاها بی‌صدا
 * رد می‌شدند و پیام موفق «۰ آدرس بررسی شد» می‌آمد؛ مدیر نمی‌فهمید چرا هیچ آدرسی بررسی نمی‌شود.
 *
 * @param array{checked: int, error: string, quota: bool, failed: int, queued: int, fail: string} $summary
 */
function hodima_seo_advisor_inspect_message( array $summary ): string {

	if ( '' !== $summary['error'] ) {
		return $summary['error'];
	}

	if ( 0 === $summary['queued'] ) {
		return sprintf( 'آدرسی برای پرسیدن نبود: همه آدرس‌ها در %s روز اخیر بررسی شده‌اند.', number_format_i18n( HODIMA_SEO_ADVISOR_INSPECT_RECHECK_DAYS ) );
	}

	$text = sprintf( '%s آدرس بررسی شد.', number_format_i18n( $summary['checked'] ) );

	if ( $summary['failed'] > 0 ) {
		$text .= ' ' . sprintf( 'گوگل برای %1$s آدرس خطا داد (%2$s).', number_format_i18n( $summary['failed'] ), $summary['fail'] );
	}

	return $text;
}

/**
 * بدنه یک دسته بررسی ایندکس (بعد از گرفتن قفل).
 *
 * @param array{checked: int, error: string, quota: bool, failed: int, queued: int, fail: string} $summary
 * @return array{checked: int, error: string, quota: bool, failed: int, queued: int, fail: string}
 */
function hodima_seo_advisor_inspect_run( string $property, int $limit, array $summary ): array {

	$summary += [ 'failed' => 0, 'queued' => -1, 'fail' => '' ];
	$stored     = hodima_seo_advisor_inspections();
	$queue      = hodima_seo_advisor_inspect_queue( $limit );
	$count      = count( $queue );
	$streak     = 0;
	$fails      = hodima_seo_advisor_inspect_failures();
	$failed_now = [];

	$summary['queued'] = $count;

	foreach ( $queue as $index => $path ) {

		hodima_seo_advisor_progress_set( 'inspect', $index + 1, max( 1, $count ), sprintf( '%1$s از %2$s آدرس پرسیده می‌شود', number_format_i18n( $index + 1 ), number_format_i18n( $count ) ), (int) round( $index / max( 1, $count ) * 100 ) ); // نوار پیشرفت (SEO 3.4.8)

		$result = hodima_seo_sc_inspect_url( $property, hodima_seo_advisor_url( $path ), HODIMA_SEO_SC_ADVISOR );

		if ( is_wp_error( $result ) ) {
			$summary['quota'] = 429 === (int) ( $result->get_error_data()['status'] ?? 0 );
			if ( $summary['quota'] || hodima_seo_sc_is_key_error( $result ) ) {
				$summary['error'] = $summary['quota'] ? 'سهمیه روزانه بررسی ایندکس گوگل تمام شد؛ فردا ادامه می‌یابد.' : $result->get_error_message();
				break;
			}
			// خطای همین آدرس؛ بقیه ادامه دارند. ولی چند خطای پی‌درپی بی‌هیچ پاسخ درست = مشکل property یا دسترسی (نه آدرس)؛
			// ادامه دادن فقط وقت و سهمیه می‌برد و پیام باید خطای گوگل را نشان دهد (تا ۳.۸.۰ بی‌صدا «۰ آدرس بررسی شد»)
			++$summary['failed'];
			++$streak;
			$summary['fail'] = hodima_seo_advisor_inspect_fail_text( $result );
			$failed_now[ $path ] = [ 'at' => time(), 'text' => $summary['fail'] ];
			if ( 0 === $summary['checked'] && $streak >= HODIMA_SEO_ADVISOR_INSPECT_FAIL_STREAK ) {
				break;
			}
			continue;
		}

		$streak          = 0;
		$stored[ $path ] = hodima_seo_advisor_inspect_parse( $result );
		unset( $fails[ $path ] );
		++$summary['checked'];

		// هر ۱۰ آدرس ذخیره می‌شود (SEO 3.4.6): تا ۳.۴.۵ فقط آخر کار نوشته می‌شد و قطع شدن وسط ۱۸۰ ثانیه (مهلت، سهمیه، هاست)
		// همه نتیجه‌های پرسش‌شده از گوگل را دور می‌ریخت و سهمیه روزانه بیهوده خرج شده بود
		if ( 0 === $summary['checked'] % 10 ) {
			update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $stored, false );
		}
	}

	if ( count( $stored ) > HODIMA_SEO_ADVISOR_INSPECT_KEEP ) {
		uasort( $stored, static fn( array $a, array $b ): int => (int) ( $b['checked'] ?? 0 ) <=> (int) ( $a['checked'] ?? 0 ) );
		$stored = array_slice( $stored, 0, HODIMA_SEO_ADVISOR_INSPECT_KEEP, true );
	}

	update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $stored, false );

	// خطای هر آدرس فقط وقتی ثبت می‌شود که دست‌کم یک آدرس همین دسته پاسخ درست گرفته: وقتی همه خطا می‌دهند (۴۰۳ property) مشکل از
	// دسترسی است نه از آدرس‌ها، و پیام بالای صفحه همان را می‌گوید؛ سه کارت ساختگی «بررسی نشد» فقط شلوغی بود.
	if ( $summary['checked'] > 0 ) {
		$fails = array_merge( $fails, $failed_now );
		uasort( $fails, static fn( array $a, array $b ): int => $b['at'] <=> $a['at'] );
		update_option( HODIMA_SEO_ADVISOR_INSPECT_FAIL_OPTION, array_slice( $fails, 0, HODIMA_SEO_ADVISOR_INSPECT_FAIL_KEEP, true ), false );
	}

	hodima_seo_advisor_run_mark( 'inspect' ); // زمان آخرین اجرای واقعی (کرون یا دکمه)؛ کارت «کارهای خودکار» از همین می‌خواند

	if ( $summary['checked'] > 0 ) {
		hodima_seo_advisor_tasks_sync_inspected(); // کارهای «انجام دادم» ایندکس و صفحه بدون نمایش با نتیجه تازه
	}

	if ( '' === $summary['error'] && 0 === $summary['checked'] && $summary['failed'] > 0 ) {
		$summary['error'] = sprintf( 'هیچ آدرسی بررسی نشد؛ گوگل برای %1$s آدرس خطا داد (%2$s).', number_format_i18n( $summary['failed'] ), $summary['fail'] );
	}

	hodima_seo_advisor_progress_finish( 'inspect', hodima_seo_advisor_inspect_message( $summary ), '' !== $summary['error'] && ! $summary['quota'] );

	return $summary;
}

add_action( 'admin_post_hodima_advisor_inspect_now', 'hodima_seo_advisor_handle_inspect_now' );

/**
 * یک دسته بررسی ایندکس (کنش «اجرا همین حالا»)؛ فرم admin-post و ایجکس نوار پیشرفت هر دو از همین استفاده می‌کنند (SEO 3.4.8).
 *
 * @return array{0: string, 1: string} [نوع پیام (success|error)، HTML پیام]
 */
function hodima_seo_advisor_do_inspect(): array {

	$summary = hodima_seo_advisor_inspect_batch();

	if ( '' !== $summary['error'] ) {
		return [ 'error', '<strong>بررسی ایندکس:</strong> ' . esc_html( $summary['error'] ) . ( $summary['checked'] > 0 ? ' ' . esc_html( sprintf( '(%s آدرس بررسی شد)', number_format_i18n( $summary['checked'] ) ) ) : '' ) ];
	}

	// بخشی از آدرس‌ها خطا داد: پیام هشدار (نه موفق سبز)، با خطای گوگل
	return [ $summary['failed'] > 0 ? 'warning' : 'success', '<strong>بررسی ایندکس:</strong> ' . esc_html( hodima_seo_advisor_inspect_message( $summary ) ) ];
}

function hodima_seo_advisor_handle_inspect_now(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_inspect_now' );

	[ $type, $html ] = hodima_seo_advisor_do_inspect();
	hodima_admin_flash( $html, $type );

	$back = hodima_seo_advisor_back_tab( 'indexing' );
	wp_safe_redirect( admin_url( 'admin.php?page=' . HODIMA_SEO_ADVISOR_PAGE . '&tab=' . $back ) );
	exit;
}
