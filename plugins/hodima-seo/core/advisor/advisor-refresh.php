<?php
/**
 * ماژول «مشاور سئو» — دریافت روزانه داده از Search Console
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
		$part = hodima_seo_sc_request( $property, $start, $end, $dimensions, 25000, 'web', $page * 25000 );
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
 * @return true|WP_Error
 */
function hodima_seo_advisor_refresh(): bool|WP_Error {

	// کلیک دستی و کرون هم‌زمان نشوند (دو دریافت داده روی هم، جدول را قاطی می‌کنند)
	if ( ! hodima_seo_advisor_lock() ) {
		return new WP_Error( 'hodima_advisor_busy', 'دریافت داده هم‌اکنون در حال انجام است؛ چند دقیقه بعد دوباره امتحان کنید.' );
	}

	try {
		return hodima_seo_advisor_run_refresh();
	} finally {
		hodima_seo_advisor_unlock();
	}
}

/**
 * بدنه دریافت داده (بعد از گرفتن قفل).
 *
 * @return true|WP_Error
 */
function hodima_seo_advisor_run_refresh(): bool|WP_Error {

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 180 ); // کرون هم مثل دکمه دستی: سه درخواست بزرگ و تحلیل همه آدرس‌ها
	}

	$token = hodima_seo_sc_token();

	if ( is_wp_error( $token ) ) {
		hodima_seo_advisor_store_error( $token->get_error_message() );
		return $token;
	}

	// propertyهای در دسترس حساب (برای انتخاب خودکار و پیام خطا)؛ شکستش مانع کار نیست
	$sites = hodima_seo_sc_list_sites( $token );
	if ( is_array( $sites ) ) {
		update_option( HODIMA_SEO_SC_SITES_OPTION, $sites, false );
	}

	$days  = hodima_seo_advisor_days();
	$range = hodima_seo_advisor_ranges( $days );
	$last  = null;

	foreach ( hodima_seo_sc_candidates() as $property ) {

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

		// ذخیره داده و ساخت پیشنهادها یک تراکنش‌اند: یا هر دو، یا هیچ‌کدام (داده قبلی می‌ماند)
		$saved = hodima_seo_advisor_transaction( static function () use ( $current, $previous, $queries ): array|WP_Error {

			$stored = hodima_seo_advisor_store(
				hodima_seo_advisor_aggregate_pages( $current['rows'] ),
				hodima_seo_advisor_aggregate_pages( $previous['rows'] ),
				hodima_seo_advisor_aggregate_queries( $queries['rows'] )
			);

			if ( is_wp_error( $stored ) ) {
				return $stored;
			}

			$advised = hodima_seo_advisor_analyze_all(); // پیشنهادها یک‌جا ساخته می‌شوند تا فهرست پیشخوان با SQL فیلتر و مرتب شود

			return is_wp_error( $advised ) ? $advised : $stored + [ 'advised' => $advised ];
		} );

		if ( is_wp_error( $saved ) ) {
			hodima_seo_advisor_store_error( $saved->get_error_message() );
			return $saved;
		}

		$stored  = $saved;
		$advised = $saved['advised'];

		update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [
			'advised'    => $advised,
			'fetched'    => time(),
			'property'   => $property,
			'days'       => $days,
			'pages'      => $stored['pages'],
			'queries'    => $stored['queries'],
			'error'      => '',
		] + $range, false );

		return true;
	}

	$message = hodima_seo_sc_failure_message( $last );
	hodima_seo_advisor_store_error( $message );

	return new WP_Error( 'hodima_advisor_failed', $message );
}

/* ── زمان‌بندی روزانه (فقط وقتی کلید هست) ── */
add_action( HODIMA_SEO_ADVISOR_CRON, static function (): void {
	if ( '' !== hodima_seo_sc_key_json() ) {
		hodima_seo_advisor_refresh();
	}
} );

add_action( 'admin_init', static function (): void {
	$has_key   = '' !== hodima_seo_sc_key_json();
	$scheduled = (bool) wp_next_scheduled( HODIMA_SEO_ADVISOR_CRON );
	if ( $has_key && ! $scheduled ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', HODIMA_SEO_ADVISOR_CRON );
	} elseif ( ! $has_key && $scheduled ) {
		wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRON ); // کلید برداشته شد: رویداد بی‌کار نماند
	}
} );
