<?php
/**
 * ماژول «دستار سئو» — دریافت روزانه داده از Search Console
 * Path: core/advisor/advisor-refresh.php
 *
 * سه درخواست برای یک property (جستجوی وب، نه دیسکاور):
 *   ۱ همه آدرس‌ها، بازه فعلی        ۲ همه آدرس‌ها، بازه قبلی هم‌اندازه (مقایسه)
 *   ۳ آدرس + کلمه، بازه فعلی
 * هر کدام با صفحه‌بندی تا ۴ × ۲۵ هزار ردیف (hodima_seo_advisor_fetch).
 * اگر درخواست ۲ یا ۳ شکست بخورد هیچ چیز ذخیره نمی‌شود و داده قبلی می‌ماند، تا
 * دو بازه هرگز نصفه‌کاره قاطی نشوند.
 *
 * روزی یک بار با WP-Cron و با دکمه «به‌روزرسانی» (صفحه پیشخوان، مرحله ۵)؛ هرگز در
 * بازدید سایت. بازه به وقت اقیانوس آرام است (سرچ کنسول روزها را همان‌طور می‌شمارد) و
 * ۲ روز پیش تمام می‌شود، چون داده روزهای اخیر ناقص است.
 *
 * مرحله‌بندی (SEO 2.7.5): درخواست اصلی فقط می‌گیرد و ذخیره می‌کند. کارهای سنگین بعدی «نوبت» (stage)اند:
 * ctr (جدول نرخ کلیک از داده سایت، پیش از analyze)، analyze (ساخت پیشنهادها، نوبت‌به‌نوبت)، breakdown، trend، health، history. هر درخواست تا
 * HODIMA_SEO_ADVISOR_STAGE_BUDGET ثانیه نوبت‌ها را پیش می‌برد و اگر چیزی ماند، رویداد کرون
 * hodima_advisor_stage ادامه می‌دهد؛ پس یک درخواست طولانی هاست را نمی‌کشد و شکست یک نوبت بقیه را نمی‌اندازد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** رویداد روزانه دریافت داده. */
const HODIMA_SEO_ADVISOR_CRON = 'hodima_advisor_refresh';

/**
 * بازه فعلی و بازه قبلی هم‌اندازه.
 *
 * @return array{start: string, end: string, prev_start: string, prev_end: string}
 */
function hodima_seo_advisor_ranges( int $days ): array {

	$end = new DateTimeImmutable( 'today -2 days', new DateTimeZone( 'America/Los_Angeles' ) );
	$day = static fn( int $back ): string => $end->modify( "-{$back} days" )->format( 'Y-m-d' );

	return [
		'start'      => $day( $days - 1 ),
		'end'        => $end->format( 'Y-m-d' ),
		'prev_start' => $day( 2 * $days - 1 ),
		'prev_end'   => $day( $days ),
	];
}

/** گزینه صف نوبت‌های باقی‌مانده، رویداد ادامه، و بودجه زمانی هر درخواست (ثانیه). */
const HODIMA_SEO_ADVISOR_PIPELINE_OPTION = 'hodima_advisor_pipeline';
const HODIMA_SEO_ADVISOR_STAGE_CRON      = 'hodima_advisor_stage';
const HODIMA_SEO_ADVISOR_STAGE_BUDGET    = 90;

/**
 * بودجه نوبت‌ها وقتی مدیر منتظر صفحه است (دکمه «به‌روزرسانی همه»، ذخیره تنظیم، خواندن فایل خزش)؛ باقی‌مانده در پس‌زمینه.
 * تا SEO 2.9.8 همان ۹۰ ثانیه کرون (و برای «حداقل نمایش» بدون سقف) بود و صفحه چند دقیقه منتظر می‌ماند.
 */
const HODIMA_SEO_ADVISOR_MANUAL_BUDGET = 15;

/** درخواست «پیشنهادها از نو ساخته شوند» (تنظیم یا خزش تازه)؛ اولین اجرای نوبت‌ها آن را به صف می‌برد. autoload خاموش. */
const HODIMA_SEO_ADVISOR_REANALYZE_OPTION = 'hodima_advisor_reanalyze';

/** نوبت‌های بعد از ذخیره داده، به ترتیب. */
const HODIMA_SEO_ADVISOR_STAGES = [ 'ctr', 'analyze', 'breakdown', 'trend', 'health', 'history', 'verify' ];

/** حداکثر صفحه‌های درخواست برای یک گزارش (هر صفحه ۲۵ هزار ردیف، سقف سرچ کنسول). */
const HODIMA_SEO_ADVISOR_FETCH_PAGES = 4;

/**
 * همه ردیف‌های یک گزارش با صفحه‌بندی (startRow)؛ سرچ کنسول بر اساس کلیک مرتب می‌دهد، پس بدون صفحه‌بندی
 * آدرس‌های «نمایش زیاد، کلیک صفر» (همان فرصت‌ها) آخر فهرست می‌افتادند و بریده می‌شدند. هر صفحه شکست
 * بخورد کل گزارش خطا می‌دهد (داده نصفه ذخیره نمی‌شود).
 *
 * @param list<string> $dimensions
 * @return array{rows: list<array<string, mixed>>}|WP_Error
 */
function hodima_seo_advisor_fetch( string $property, string $start, string $end, array $dimensions ): array|WP_Error {

	$rows = [];

	for ( $page = 0; $page < HODIMA_SEO_ADVISOR_FETCH_PAGES; $page++ ) {
		$part = hodima_seo_sc_request( $property, $start, $end, $dimensions, 25000, 'web', $page * 25000, HODIMA_SEO_SC_ADVISOR );
		if ( is_wp_error( $part ) ) {
			return $part;
		}
		array_push( $rows, ...$part['rows'] );
		if ( count( $part['rows'] ) < 25000 ) {
			break;
		}
	}

	return [ 'rows' => $rows ];
}

/** ثبت خطا بدون پاک کردن آخرین داده موفق. */
function hodima_seo_advisor_store_error( string $message ): void {
	$status          = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] );
	$status          = is_array( $status ) ? $status : [];
	$status['error'] = $message;
	update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $status, false );
}

/**
 * دریافت و ذخیره داده سرچ کنسول.
 *
 * @param float|null $budget ثانیه نوبت‌های بعد از ذخیره در همین درخواست؛ null = بودجه کرون (دکمه دستی: HODIMA_SEO_ADVISOR_MANUAL_BUDGET)
 * @return true|WP_Error
 */
function hodima_seo_advisor_refresh( bool $force = false, ?float $budget = null ): bool|WP_Error {

	// کلیک دستی و کرون هم‌زمان نشوند (دو دریافت داده روی هم، جدول را قاطی می‌کنند)
	if ( ! hodima_seo_advisor_lock() ) {
		return new WP_Error( 'hodima_advisor_busy', 'دریافت داده هم‌اکنون در حال انجام است؛ چند دقیقه بعد دوباره امتحان کنید.' );
	}

	try {
		return hodima_seo_advisor_run_refresh( $force, $budget );
	} finally {
		hodima_seo_advisor_unlock();
	}
}

/**
 * بدنه دریافت داده (بعد از گرفتن قفل).
 *
 * @return true|WP_Error
 */
function hodima_seo_advisor_run_refresh( bool $force = false, ?float $budget = null ): bool|WP_Error {

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 180 ); // کرون هم مثل دکمه دستی: سه درخواست بزرگ و تحلیل همه آدرس‌ها
	}

	$token = hodima_seo_sc_token( false, HODIMA_SEO_SC_ADVISOR );

	if ( is_wp_error( $token ) ) {
		hodima_seo_advisor_store_error( $token->get_error_message() );
		return $token;
	}

	// propertyهای در دسترس حساب (برای انتخاب خودکار و پیام خطا)؛ شکستش مانع کار نیست
	$sites = hodima_seo_sc_list_sites( $token );
	if ( is_array( $sites ) ) {
		update_option( hodima_seo_sc_sites_option( HODIMA_SEO_SC_ADVISOR ), $sites, false );
	}

	$days  = hodima_seo_advisor_days();
	$range = hodima_seo_advisor_ranges( $days );
	$last  = null;

	foreach ( hodima_seo_sc_candidates( HODIMA_SEO_SC_ADVISOR ) as $property ) {

		$current = hodima_seo_advisor_fetch( $property, $range['start'], $range['end'], [ 'page' ] );

		if ( is_wp_error( $current ) ) {
			if ( hodima_seo_sc_is_key_error( $current ) ) {
				hodima_seo_advisor_store_error( $current->get_error_message() ); // با property دیگر درست نمی‌شود
				return $current;
			}
			$last = $current;
			continue;
		}

		$previous = hodima_seo_advisor_fetch( $property, $range['prev_start'], $range['prev_end'], [ 'page' ] );
		$queries  = is_wp_error( $previous ) ? $previous : hodima_seo_advisor_fetch( $property, $range['start'], $range['end'], [ 'page', 'query' ] );

		foreach ( [ $previous, $queries ] as $part ) {
			if ( is_wp_error( $part ) ) {
				$message = 'خطای سرچ کنسول: ' . $part->get_error_message();
				hodima_seo_advisor_store_error( $message );
				return new WP_Error( 'hodima_advisor_partial', $message );
			}
		}

		// ذخیره داده (تراکنش خود store): یا همه، یا هیچ‌چیز (داده قبلی می‌ماند). ساخت پیشنهادها و کارهای سنگین
		// بعدی نوبت‌اند (پایین) و پیشنهاد قبلی هر آدرس تا تحلیل تازه‌اش می‌ماند
		$stored = hodima_seo_advisor_store(
			hodima_seo_advisor_aggregate_pages( $current['rows'] ),
			hodima_seo_advisor_aggregate_pages( $previous['rows'] ),
			hodima_seo_advisor_aggregate_queries( $queries['rows'] )
		);

		if ( is_wp_error( $stored ) ) {
			hodima_seo_advisor_store_error( $stored->get_error_message() );
			return $stored;
		}

		update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [
			'advised'    => hodima_seo_advisor_advised_count(),
			'fetched'    => time(),
			'property'   => $property,
			'days'       => $days,
			'pages'      => $stored['pages'],
			'queries'    => $stored['queries'],
			'error'      => '',
		] + $range, false );

		// آدرس‌های تکراری یک صفحه (آدرس خام پیش از یکسان‌سازی)؛ برای فهرست تب «صفحه‌های هم‌کلمه»
		update_option( HODIMA_SEO_ADVISOR_VARIANTS_OPTION, hodima_seo_advisor_find_variants( $current['rows'] ), false );

		// بقیه کارها نوبت‌به‌نوبت: ساخت پیشنهادها، بخش‌بندی، روند، سلامت و تاریخچه (هرکدام مستقل؛ شکستشان
		// دریافت اصلی را رد نمی‌کند). تا بودجه زمانی همین‌جا؛ باقی‌مانده با رویداد کرون
		delete_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION );
		delete_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION ); // صف تازه خودش analyze را دارد
		update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, [ 'stages' => HODIMA_SEO_ADVISOR_STAGES, 'property' => $property, 'range' => $range, 'days' => $days, 'force' => $force ], false );
		hodima_seo_advisor_pipeline_run( $budget );

		return true;
	}

	$message = hodima_seo_sc_failure_message( $last, HODIMA_SEO_SC_ADVISOR );
	hodima_seo_advisor_store_error( $message );

	return new WP_Error( 'hodima_advisor_failed', $message );
}

/* ── نوبت‌ها ── */

/** نوبت‌های باقی‌مانده دارد؟ (یا درخواست ساخت دوباره پیشنهادها که هنوز به صف نرفته) */
function hodima_seo_advisor_pipeline_pending(): bool {
	$state = get_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, [] );
	return ( is_array( $state ) && ! empty( $state['stages'] ) ) || false !== get_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, false );
}

/**
 * پیشنهادها از نو ساخته شوند (تغییر «حداقل نمایش»، فایل خزش تازه). خودش کاری نمی‌کند: درخواست ثبت و رویداد ادامه
 * زمان‌بندی می‌شود؛ hodima_seo_advisor_pipeline_run (با قفل) آن را به صف نوبت‌ها می‌برد — حتی وقتی دریافت داده‌ای در
 * میانه است و نوبت analyze آن گذشته (تا SEO 2.9.8 خزشِ خوانده‌شده در آن حالت تا فردا در پیشنهادها اثر نمی‌کرد).
 */
function hodima_seo_advisor_request_reanalyze(): void {

	if ( hodima_seo_advisor_status()['fetched'] <= 0 ) {
		return; // داده‌ای نیست که تحلیل شود
	}

	update_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, time(), false );
	hodima_seo_advisor_pipeline_schedule( 0 );
}

/**
 * درخواست ساخت دوباره را (اگر هست) به صف نوبت‌ها می‌برد: صف تحلیل نیمه‌کاره دور ریخته می‌شود و analyze بعد از ctr
 * (یا اول صف) می‌آید. فراخواننده قفل دریافت داده را دارد.
 *
 * @param array<string, mixed> $state
 * @return array<string, mixed>
 */
function hodima_seo_advisor_pipeline_take_reanalyze( array $state ): array {

	if ( false === get_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, false ) ) {
		return $state;
	}

	delete_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION );
	delete_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION ); // آستانه یا خزش عوض شده؛ صف قدیمی معتبر نیست

	$stages = array_values( array_map( 'strval', (array) ( $state['stages'] ?? [] ) ) );

	if ( ! in_array( 'analyze', $stages, true ) ) {
		$ctr = array_search( 'ctr', $stages, true );
		array_splice( $stages, false === $ctr ? 0 : (int) $ctr + 1, 0, [ 'analyze' ] );
	}

	$status = hodima_seo_advisor_status();
	$state  = [ 'stages' => $stages, 'property' => (string) ( $state['property'] ?? $status['property'] ), 'range' => (array) ( $state['range'] ?? [] ), 'days' => (int) ( $state['days'] ?? $status['days'] ) ] + $state;
	update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, $state, false );

	return $state;
}

/** بعد از ساخت پیشنهادها، «پیشنهاد دارد» در وضعیت آخرین دریافت به‌روز می‌شود. */
function hodima_seo_advisor_status_set_advised(): void {
	$status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] );
	if ( is_array( $status ) ) {
		$status['advised'] = hodima_seo_advisor_advised_count();
		update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $status, false );
	}
}

/**
 * یک نوبت. true = تمام شد (یا خطا ثبت شد و رد شد)، false = وقت تمام شد و باید ادامه یابد.
 *
 * @param array<string, mixed> $state
 */
function hodima_seo_advisor_pipeline_stage( string $stage, array $state, float $deadline ): bool {

	$property = (string) ( $state['property'] ?? '' );
	$range    = (array) ( $state['range'] ?? [] );
	$days     = (int) ( $state['days'] ?? 0 );

	switch ( $stage ) {
		case 'ctr':
			hodima_seo_advisor_ctr_learn(); // پیش از analyze تا پیشنهادها با جدول تازه ساخته شوند
			break;
		case 'analyze':
			$result = hodima_seo_advisor_analyze_batch( $deadline );
			if ( is_wp_error( $result ) ) {
				delete_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION );
				hodima_seo_advisor_store_error( $result->get_error_message() );
				return true;
			}
			if ( true === $result ) {
				hodima_seo_advisor_status_set_advised();
			}
			return $result;
		case 'breakdown':
			hodima_seo_advisor_breakdown_sync( $property, [ 'start' => (string) ( $range['start'] ?? '' ), 'end' => (string) ( $range['end'] ?? '' ) ], $days, ! empty( $state['force'] ) );
			break;
		case 'trend':
			hodima_seo_advisor_trend_sync( $property, ! empty( $state['force'] ) ); // دکمه دستی: بدون فاصله ۱۲ ساعته
			break;
		case 'health':
			hodima_seo_advisor_health_sync();
			break;
		case 'history':
			hodima_seo_advisor_history_sync( $property );
			break;
		case 'verify': // اثر کارهای «انجام شد» با داده فقط بعد از تغییر (advisor-verify.php)؛ باقی‌مانده فردا
			hodima_seo_advisor_verify_batch( $property, $deadline );
			break;
	}

	return true;
}

/**
 * نوبت‌های باقی‌مانده را تا بودجه زمانی پیش می‌برد؛ اگر چیزی ماند رویداد ادامه زمان‌بندی می‌شود.
 * فراخواننده قفل دریافت داده را دارد.
 *
 * @param float|null $budget ثانیه؛ null = HODIMA_SEO_ADVISOR_STAGE_BUDGET
 */
function hodima_seo_advisor_pipeline_run( ?float $budget = null ): void {

	$state = get_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, [] );
	$state = hodima_seo_advisor_pipeline_take_reanalyze( is_array( $state ) ? $state : [] );

	if ( empty( $state['stages'] ) ) {
		delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
		return;
	}

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 180 );
	}

	$deadline = microtime( true ) + ( $budget ?? (float) HODIMA_SEO_ADVISOR_STAGE_BUDGET );
	$stages   = array_values( array_map( 'strval', (array) $state['stages'] ) );

	while ( $stages && microtime( true ) < $deadline ) {
		try {
			$finished = hodima_seo_advisor_pipeline_stage( $stages[0], $state, $deadline );
		} catch ( Throwable $e ) {
			hodima_seo_advisor_store_error( 'خطای داخلی در نوبت «' . $stages[0] . '»: ' . $e->getMessage() );
			$finished = true; // نوبت خراب بقیه را متوقف نمی‌کند
		}
		if ( ! $finished ) {
			break;
		}
		array_shift( $stages );
		$state['stages'] = $stages;
		update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, $state, false );
	}

	if ( ! $stages ) {
		delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
		// همه نوبت‌ها همین‌جا تمام شد: رویداد ادامه‌ای که درخواست ساخت دوباره گذاشته بود بی‌کار نماند (مگر درخواست تازه‌ای در این فاصله آمده)
		if ( false === get_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, false ) ) {
			wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
		}
		return;
	}

	hodima_seo_advisor_pipeline_schedule( 20 );
}

/** رویداد ادامه (یکی در هر زمان). */
function hodima_seo_advisor_pipeline_schedule( int $delay ): void {
	if ( ! wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ) {
		wp_schedule_single_event( time() + $delay, HODIMA_SEO_ADVISOR_STAGE_CRON );
	}
}

add_action( HODIMA_SEO_ADVISOR_STAGE_CRON, static function (): void {

	if ( ! hodima_seo_advisor_pipeline_pending() ) {
		return;
	}

	if ( ! hodima_seo_advisor_lock() ) {
		hodima_seo_advisor_pipeline_schedule( 300 ); // دریافت دیگری در کار است
		return;
	}

	try {
		hodima_seo_advisor_pipeline_run();
	} finally {
		hodima_seo_advisor_unlock();
	}
} );

/* ── زمان‌بندی روزانه (فقط وقتی کلید هست) ── */
add_action( HODIMA_SEO_ADVISOR_CRON, static function (): void {
	if ( '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR ) ) {
		hodima_seo_advisor_refresh();
	}
} );

add_action( 'admin_init', static function (): void {
	$has_key   = '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR );
	$scheduled = (bool) wp_next_scheduled( HODIMA_SEO_ADVISOR_CRON );
	if ( $has_key && ! $scheduled ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', HODIMA_SEO_ADVISOR_CRON );
	} elseif ( ! $has_key && $scheduled ) {
		wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRON ); // کلید برداشته شد: رویداد بی‌کار نماند
	}
} );
