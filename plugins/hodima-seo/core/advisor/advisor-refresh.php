<?php
/**
 * ماژول «مشاور سئو» — دریافت روزانه داده از Search Console
 * Path: core/advisor/advisor-refresh.php
 *
 * سه درخواست برای یک property (جستجوی وب، نه دیسکاور):
 *   ۱ همه آدرس‌ها، بازه فعلی        ۲ همه آدرس‌ها، بازه قبلی هم‌اندازه (مقایسه)
 *   ۳ آدرس + کلمه، بازه فعلی
 * هر کدام تا ۲۵ هزار ردیف (سقف سرچ کنسول؛ سایت‌های بزرگ‌تر فقط پرنمایش‌ترین‌ها).
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

		$current = hodima_seo_sc_request( $property, $range['start'], $range['end'], [ 'page' ], 25000 );

		if ( is_wp_error( $current ) ) {
			if ( hodima_seo_sc_is_key_error( $current ) ) {
				hodima_seo_advisor_store_error( $current->get_error_message() ); // با property دیگر درست نمی‌شود
				return $current;
			}
			$last = $current;
			continue;
		}

		$previous = hodima_seo_sc_request( $property, $range['prev_start'], $range['prev_end'], [ 'page' ], 25000 );
		$queries  = is_wp_error( $previous ) ? $previous : hodima_seo_sc_request( $property, $range['start'], $range['end'], [ 'page', 'query' ], 25000 );

		foreach ( [ $previous, $queries ] as $part ) {
			if ( is_wp_error( $part ) ) {
				$message = 'خطای سرچ کنسول: ' . $part->get_error_message();
				hodima_seo_advisor_store_error( $message );
				return new WP_Error( 'hodima_advisor_partial', $message );
			}
		}

		$stored = hodima_seo_advisor_store(
			hodima_seo_advisor_aggregate_pages( $current['rows'] ),
			hodima_seo_advisor_aggregate_pages( $previous['rows'] ),
			hodima_seo_advisor_aggregate_queries( $queries['rows'] )
		);

		$advised = hodima_seo_advisor_analyze_all(); // پیشنهادها یک‌جا ساخته می‌شوند تا فهرست پیشخوان با SQL فیلتر و مرتب شود

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
