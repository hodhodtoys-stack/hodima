<?php
/**
 * ماژول «دستار سئو» — روند ماهانه (۱۶ ماه) و تغییر کلمه‌ها
 * Path: core/advisor/advisor-trend.php
 *
 *  ۱. روند ماهانه: یک درخواست «تاریخ» (سطح سایت) برای ۱۶ ماه گذشته (سقف داده گوگل)؛ ماه‌ها شمسی
 *     (با کتابخانه Core، وگرنه میلادی) جمع می‌شوند تا فصل‌بندی ایرانی (نوروز، پاییز) دیده شود.
 *     ماه ناقص (اول و آخر بازه) نشانه‌گذاری می‌شود و در مقایسه با سال قبل نمی‌آید.
 *  ۲. کلمه‌های تازه و از دست رفته: دو درخواست «کلمه» (سطح سایت) برای ۲۸ روز اخیر و ۲۸ روز قبلش.
 *     تازه = دست‌کم ۱۰۰ نمایش حالا و کمتر از ۱۰ قبلا؛ از دست رفته = دست‌کم ۱۵۰ نمایش قبلا و کمتر از
 *     ۱۰ حالا. کلمه‌های برندی کنار می‌روند.
 * نتیجه فشرده در یک گزینه (hodima_advisor_trend، autoload خاموش)؛ هر بخش مستقل؛ حداکثر هر ۱۲ ساعت؛
 * شکستش دریافت اصلی را رد نمی‌کند. فیلتر hodima_seo_advisor_trend_enabled خاموشش می‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_TREND_OPTION   = 'hodima_advisor_trend';
const HODIMA_SEO_ADVISOR_TREND_INTERVAL = 12 * HOUR_IN_SECONDS;

/** پنجره مقایسه کلمه‌ها (روز). */
const HODIMA_SEO_ADVISOR_TERMS_WINDOW = 28;

/** حداقل نمایش برای «کلمه تازه» (حالا) و «از دست رفته» (قبلا)، و سقف نمایش «غایب». */
const HODIMA_SEO_ADVISOR_TERMS_NEW_MIN  = 100;
const HODIMA_SEO_ADVISOR_TERMS_LOST_MIN = 150;
const HODIMA_SEO_ADVISOR_TERMS_ABSENT   = 10;

/** حداکثر کلمه در هر فهرست. */
const HODIMA_SEO_ADVISOR_TERMS_MAX = 30;

/**
 * کلید و برچسب ماه یک روز میلادی (شمسی اگر Core هست).
 *
 * @return array{0: int, 1: string} کلید قابل‌مرتب‌سازی (سال×۱۰۰ + ماه) و برچسب
 */
function hodima_seo_advisor_trend_month_of( string $date ): array {

	$parts = array_map( 'intval', explode( '-', $date ) );

	if ( 3 !== count( $parts ) || $parts[1] < 1 || $parts[1] > 12 ) {
		return [ 0, $date ];
	}

	if ( function_exists( 'hodima_jalali_from_gregorian' ) && function_exists( 'hodima_jalali_months' ) ) {
		[ $jy, $jm ] = hodima_jalali_from_gregorian( $parts[0], $parts[1], $parts[2] );
		return [ $jy * 100 + $jm, hodima_jalali_months()[ $jm ] . ' ' . $jy ];
	}

	return [ $parts[0] * 100 + $parts[1], gmdate( 'M Y', (int) gmmktime( 0, 0, 0, $parts[1], 1, $parts[0] ) ) ];
}

/**
 * ردیف‌های «تاریخ» ← جمع ماهانه (قدیمی‌ترین اول). ماهی که کمتر از ۲۶ روز داده دارد «ناقص» است.
 *
 * @param list<array<string, mixed>> $rows keys = [تاریخ]
 * @return list<array{key: int, label: string, clicks: int, impressions: int, position: float, days: int, partial: bool}>
 */
function hodima_seo_advisor_trend_months( array $rows ): array {

	$months = [];

	foreach ( $rows as $row ) {
		[ $key, $label ] = hodima_seo_advisor_trend_month_of( (string) ( $row['keys'][0] ?? '' ) );
		if ( 0 === $key ) {
			continue;
		}
		$impressions = (int) round( (float) ( $row['impressions'] ?? 0 ) );
		$months[ $key ] ??= [ 'key' => $key, 'label' => $label, 'clicks' => 0, 'impressions' => 0, 'weighted' => 0.0, 'days' => 0 ];
		$months[ $key ]['clicks']      += (int) round( (float) ( $row['clicks'] ?? 0 ) );
		$months[ $key ]['impressions'] += $impressions;
		$months[ $key ]['weighted']    += (float) ( $row['position'] ?? 0 ) * $impressions;
		++$months[ $key ]['days'];
	}

	ksort( $months );

	return array_values( array_map(
		static fn( array $m ): array => [
			'key'         => $m['key'],
			'label'       => $m['label'],
			'clicks'      => $m['clicks'],
			'impressions' => $m['impressions'],
			'position'    => $m['impressions'] > 0 ? round( $m['weighted'] / $m['impressions'], 1 ) : 0.0,
			'days'        => $m['days'],
			'partial'     => $m['days'] < 26,
		],
		$months
	) );
}

/**
 * مقایسه یک ماه کامل با همان ماه سال قبل (کلید ۱۰۰ تا کمتر = یک سال پیش).
 *
 * @param list<array<string, mixed>> $months
 * @return array{label: string, clicks: int, prev_clicks: int, change: float}|null null اگر ماه سال قبل کامل نیست
 */
function hodima_seo_advisor_trend_year_over_year( array $months ): ?array {

	$complete = array_values( array_filter( $months, static fn( array $m ): bool => ! $m['partial'] ) );
	$by_key   = array_column( $complete, null, 'key' );
	$last     = end( $complete );

	if ( false === $last || ! isset( $by_key[ $last['key'] - 100 ] ) ) {
		return null;
	}

	$prev = $by_key[ $last['key'] - 100 ];

	return [
		'label'       => (string) $last['label'],
		'clicks'      => (int) $last['clicks'],
		'prev_clicks' => (int) $prev['clicks'],
		'change'      => $prev['clicks'] > 0 ? round( ( $last['clicks'] - $prev['clicks'] ) / $prev['clicks'] * 100, 1 ) : 0.0,
	];
}

/**
 * تغییر کلمه‌ها بین دو پنجره (سطح سایت).
 *
 * @param list<array<string, mixed>> $current ردیف‌های ۲۸ روز اخیر (keys = [کلمه])
 * @param list<array<string, mixed>> $previous ردیف‌های ۲۸ روز قبلش
 * @return array{new: list<array<string, mixed>>, lost: list<array<string, mixed>>}
 */
function hodima_seo_advisor_trend_terms( array $current, array $previous ): array {

	$collect = static function ( array $rows ): array {
		$out = [];
		foreach ( $rows as $row ) {
			$term = trim( (string) ( $row['keys'][0] ?? '' ) );
			if ( '' === $term || mb_strlen( $term ) > HODIMA_SEO_ADVISOR_KEY_LENGTH ) {
				continue;
			}
			$key = hodima_seo_advisor_term_key( $term );
			$out[ $key ] ??= [ 'term' => $term, 'clicks' => 0, 'impressions' => 0, 'weighted' => 0.0 ];
			$impressions                  = (int) round( (float) ( $row['impressions'] ?? 0 ) );
			$out[ $key ]['clicks']       += (int) round( (float) ( $row['clicks'] ?? 0 ) );
			$out[ $key ]['impressions']  += $impressions;
			$out[ $key ]['weighted']     += (float) ( $row['position'] ?? 0 ) * $impressions;
		}
		return $out;
	};

	$now  = $collect( $current );
	$prev = $collect( $previous );

	$shape = static fn( array $s ): array => [
		'term'        => $s['term'],
		'clicks'      => $s['clicks'],
		'impressions' => $s['impressions'],
		'position'    => $s['impressions'] > 0 ? round( $s['weighted'] / $s['impressions'], 1 ) : 0.0,
	];

	$new  = [];
	$lost = [];

	foreach ( $now as $key => $s ) {
		if ( $s['impressions'] >= HODIMA_SEO_ADVISOR_TERMS_NEW_MIN && ( $prev[ $key ]['impressions'] ?? 0 ) < HODIMA_SEO_ADVISOR_TERMS_ABSENT && ! hodima_seo_advisor_is_brand( $s['term'] ) ) {
			$new[] = $shape( $s );
		}
	}

	foreach ( $prev as $key => $s ) {
		if ( $s['impressions'] >= HODIMA_SEO_ADVISOR_TERMS_LOST_MIN && ( $now[ $key ]['impressions'] ?? 0 ) < HODIMA_SEO_ADVISOR_TERMS_ABSENT && ! hodima_seo_advisor_is_brand( $s['term'] ) ) {
			$lost[] = $shape( $s );
		}
	}

	$by_impressions = static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'];
	usort( $new, $by_impressions );
	usort( $lost, $by_impressions );

	return [ 'new' => array_slice( $new, 0, HODIMA_SEO_ADVISOR_TERMS_MAX ), 'lost' => array_slice( $lost, 0, HODIMA_SEO_ADVISOR_TERMS_MAX ) ];
}

/**
 * نتیجه ذخیره‌شده (خالی اگر هنوز گرفته نشده).
 *
 * @return array<string, mixed>
 */
function hodima_seo_advisor_trend(): array {
	$stored = get_option( HODIMA_SEO_ADVISOR_TREND_OPTION, [] );
	return is_array( $stored ) ? $stored : [];
}

/**
 * روند ماهانه و تغییر کلمه‌ها را از سرچ کنسول می‌گیرد (حداکثر هر ۱۲ ساعت).
 *
 * @return array{fetched: int, errors: list<string>}|null null اگر هنوز زود است یا خاموش است
 */
function hodima_seo_advisor_trend_sync( string $property, bool $force = false ): ?array {

	if ( ! (bool) apply_filters( 'hodima_seo_advisor_trend_enabled', true ) ) {
		return null;
	}

	$old = hodima_seo_advisor_trend();

	if ( ! $force && (int) ( $old['fetched'] ?? 0 ) > time() - HODIMA_SEO_ADVISOR_TREND_INTERVAL ) {
		return null;
	}

	$new    = $old;
	$errors = [];
	$part   = 0;

	$end   = new DateTimeImmutable( hodima_seo_advisor_ranges( 1 )['end'], new DateTimeZone( 'America/Los_Angeles' ) );
	$start = $end->modify( '-16 months' )->modify( '+3 days' ); // سقف داده سرچ کنسول ۱۶ ماه است
	$win   = hodima_seo_advisor_ranges( HODIMA_SEO_ADVISOR_TERMS_WINDOW );

	$request = static fn( string $from, string $to, array $dims ): array|WP_Error => hodima_seo_sc_request( $property, $from, $to, $dims, 25000, 'web', 0, HODIMA_SEO_SC_ADVISOR );

	$results = [
		'months'  => $request( $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ), [ 'date' ] ),
		'current' => $request( $win['start'], $win['end'], [ 'query' ] ),
		'prev'    => $request( $win['prev_start'], $win['prev_end'], [ 'query' ] ),
	];

	foreach ( $results as $name => $result ) {
		if ( is_wp_error( $result ) && hodima_seo_sc_is_key_error( $result ) ) {
			return [ 'fetched' => 0, 'errors' => [ $result->get_error_message() ] ];
		}
	}

	if ( ! is_wp_error( $results['months'] ) ) {
		$new['months'] = hodima_seo_advisor_trend_months( $results['months']['rows'] );
		++$part;
	} else {
		$errors[] = 'روند ماهانه: ' . $results['months']->get_error_message();
	}

	if ( ! is_wp_error( $results['current'] ) && ! is_wp_error( $results['prev'] ) ) {
		$change       = hodima_seo_advisor_trend_terms( $results['current']['rows'], $results['prev']['rows'] );
		$new['new']   = $change['new'];
		$new['lost']  = $change['lost'];
		$new['terms'] = $win;
		++$part;
	} else {
		$errors[] = 'کلمه‌های تازه و از دست رفته: ' . ( is_wp_error( $results['current'] ) ? $results['current'] : $results['prev'] )->get_error_message();
	}

	if ( $part > 0 ) {
		$new['fetched'] = time();
	}

	$new['errors'] = $errors;
	update_option( HODIMA_SEO_ADVISOR_TREND_OPTION, $new, false );

	return [ 'fetched' => $part, 'errors' => $errors ];
}
