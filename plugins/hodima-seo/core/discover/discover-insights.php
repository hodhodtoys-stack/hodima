<?php
/**
 * ماژول «گوگل دیسکاور» — فرصت‌ها و نمودار (پیشخوان)
 * Path: core/discover/discover-insights.php
 *
 * «فرصت‌ها» آمادگی هر صفحه را کنار آمار واقعی سرچ کنسول می‌گذارد و
 * می‌گوید وقت را کجا بگذارید (قبلا مدیر باید دو جدول را خودش کنار هم می‌گذاشت):
 *   - کم‌کلیک: در دیسکاور زیاد دیده می‌شود ولی نرخ کلیکش خیلی کمتر از میانگین
 *     سایت است ← عنوان و تصویر کارت؛
 *   - افت نمایش: نسبت به ۲۸ روز قبل نصف یا کمتر ← به‌روزرسانی مطلب؛
 *   - دیده می‌شود ولی آماده نیست ← رفع موارد آمادگی صفحه‌ای که گوگل انتخابش کرده؛
 *   - مقاله تازه بدون نمایش ← رفع آمادگی تا هنوز تازه است.
 * و نمودار روزانه نمایش و کلیک (SVG ساده، بدون کتابخانه بیرونی).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** کمترین نمایش برای «کم‌کلیک» و «افت نمایش» (کمتر از این، نوسان عادی است). */
const HODIMA_SEO_DISCOVER_OPP_MIN_IMPRESSIONS = 200;

/** «کم‌کلیک» = نرخ کلیک کمتر از این کسر میانگین سایت. */
const HODIMA_SEO_DISCOVER_OPP_CTR_RATIO = 0.6;

/** «افت نمایش» = نمایش این دوره کمتر از این کسر دوره قبل. */
const HODIMA_SEO_DISCOVER_OPP_DROP_RATIO = 0.5;

/** بیشترین ردیف هر گروه فرصت. */
const HODIMA_SEO_DISCOVER_OPP_LIMIT = 20;

/**
 * گروه‌های فرصت.
 *
 * @param list<array<string, mixed>> $rows  ردیف‌های گزارش (hodima_seo_discover_report_rows)
 * @param array<string, mixed>       $stats hodima_seo_discover_stats()
 * @return array{low_ctr: list<array<string, mixed>>, dropping: list<array<string, mixed>>, not_ready: list<array<string, mixed>>, new_unseen: list<array<string, mixed>>, site_ctr: float}
 */
function hodima_seo_discover_opportunities( array $rows, array $stats ): array {

	$has_stats = ! empty( $stats['fetched'] );
	$has_prev  = '' !== (string) ( $stats['prev']['start'] ?? '' );
	$site_ctr  = ! empty( $stats['totals']['impressions'] ) ? (float) $stats['totals']['clicks'] / (float) $stats['totals']['impressions'] : 0.0;
	$groups    = [ 'low_ctr' => [], 'dropping' => [], 'not_ready' => [], 'new_unseen' => [] ];
	$now       = time();

	foreach ( $rows as $row ) {

		$key   = (string) $row['url_key'];
		$cur   = $stats['rows'][ $key ] ?? [ 'clicks' => 0, 'impressions' => 0 ];
		$prev  = $has_prev ? ( $stats['prev']['rows'][ $key ] ?? [ 'clicks' => 0, 'impressions' => 0 ] ) : null;
		$imp   = (int) $cur['impressions'];
		$ctr   = $imp > 0 ? (int) $cur['clicks'] / $imp : 0.0;
		$item  = $row + [
			'clicks'           => (int) $cur['clicks'],
			'impressions'      => $imp,
			'ctr'              => $ctr,
			'prev_impressions' => null !== $prev ? (int) $prev['impressions'] : null,
		];
		$needs = (int) $row['error'] + (int) $row['warn'] > 0;

		if ( $has_stats && $imp >= HODIMA_SEO_DISCOVER_OPP_MIN_IMPRESSIONS && $site_ctr > 0 && $ctr < $site_ctr * HODIMA_SEO_DISCOVER_OPP_CTR_RATIO ) {
			$groups['low_ctr'][] = $item + [ 'lost' => max( 0, (int) round( $imp * $site_ctr ) - (int) $cur['clicks'] ) ];
		}

		if ( null !== $prev && $prev['impressions'] >= HODIMA_SEO_DISCOVER_OPP_MIN_IMPRESSIONS && $imp < $prev['impressions'] * HODIMA_SEO_DISCOVER_OPP_DROP_RATIO ) {
			$groups['dropping'][] = $item;
		}

		if ( $has_stats && $imp > 0 && $needs ) {
			$groups['not_ready'][] = $item;
		}

		// مقاله ۳ تا ۳۰ روزه (داده دیسکاور دو روز تاخیر دارد) که هنوز دیده نشده و مشکل دارد
		$age = $now - (int) $row['ts'];
		if ( 'post' === $row['type'] && $needs && 0 === $imp && $age >= 3 * DAY_IN_SECONDS && $age <= 30 * DAY_IN_SECONDS ) {
			$groups['new_unseen'][] = $item;
		}
	}

	$sort = static function ( array &$items, callable $cmp ): void {
		usort( $items, $cmp );
		$items = array_slice( $items, 0, HODIMA_SEO_DISCOVER_OPP_LIMIT );
	};

	$sort( $groups['low_ctr'], static fn( array $a, array $b ): int => $b['lost'] <=> $a['lost'] );
	$sort( $groups['dropping'], static fn( array $a, array $b ): int => ( $b['prev_impressions'] - $b['impressions'] ) <=> ( $a['prev_impressions'] - $a['impressions'] ) );
	$sort( $groups['not_ready'], static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );
	$sort( $groups['new_unseen'], static fn( array $a, array $b ): int => $b['ts'] <=> $a['ts'] );

	return $groups + [ 'site_ctr' => $site_ctr ];
}

/**
 * روزهای پیوسته نمودار: روزهایی که سرچ کنسول ردیف نداده (بدون نمایش) صفر.
 *
 * @param array<string, array{clicks: int, impressions: int}> $daily
 * @return array<string, array{clicks: int, impressions: int}>
 */
function hodima_seo_discover_daily_filled( array $daily ): array {

	if ( ! $daily ) {
		return [];
	}

	ksort( $daily );
	$out = [];

	try {
		$day  = new DateTimeImmutable( (string) array_key_first( $daily ) );
		$last = new DateTimeImmutable( (string) array_key_last( $daily ) );
	} catch ( Exception ) {
		return $daily;
	}

	for ( $i = 0; $day <= $last && $i < 500; $day = $day->modify( '+1 day' ), $i++ ) {
		$key         = $day->format( 'Y-m-d' );
		$out[ $key ] = $daily[ $key ] ?? [ 'clicks' => 0, 'impressions' => 0 ];
	}

	return $out;
}

/** گرد کردن سقف محور به عدد خوانا (۱، ۲، ۵ × ۱۰ⁿ). */
function hodima_seo_discover_nice_max( int $value ): int {
	if ( $value <= 4 ) {
		return 4;
	}
	$pow = 10 ** (int) floor( log10( $value ) );
	foreach ( [ 1, 2, 2.5, 5, 10 ] as $step ) {
		if ( $value <= $step * $pow ) {
			return (int) ceil( $step * $pow );
		}
	}
	return $value;
}

/**
 * نمودار خطی یک سنجه (نمایش یا کلیک) در طول روزها؛ یک سری، یک محور.
 *
 * فقط خط‌ها SVG است (کشیده با عرض ظرف، ضخامت ثابت با non-scaling-stroke)؛
 * عددهای محور و تاریخ‌ها HTML با جای درصدی‌اند تا در هر عرضی خوانا بمانند.
 * داده راهنمای زیر نشانگر در data-* (JS گزارش: discover-report.js)؛ بدون JS
 * هم خط، محور و برچسب‌ها کامل‌اند و جدول روزانه زیر نمودارها هست.
 *
 * @param array<string, array{clicks: int, impressions: int}> $daily  روزهای پیوسته
 */
function hodima_seo_discover_chart( array $daily, string $metric, string $title ): string {

	$values = array_map( static fn( array $d ): int => (int) $d[ $metric ], array_values( $daily ) );
	$days   = array_keys( $daily );
	$count  = count( $values );

	if ( $count < 2 ) {
		return '';
	}

	$max  = hodima_seo_discover_nice_max( max( $values ) );
	$fmt  = static fn( float $n ): string => rtrim( rtrim( number_format( $n, 2, '.', '' ), '0' ), '.' );
	$x    = static fn( int $i ): float => 1000 * $i / ( $count - 1 );   // viewBox 1000×200
	$y    = static fn( int $v ): float => 200 * ( 1 - $v / $max );
	$line = '';

	foreach ( $values as $i => $v ) {
		$line .= ( 0 === $i ? 'M' : 'L' ) . $fmt( $x( $i ) ) . ' ' . $fmt( $y( $v ) );
	}
	$area = $line . 'L1000 200L0 200Z';

	$date  = static fn( string $d, string $format ): string => (string) ( wp_date( $format, (int) strtotime( $d . ' 12:00:00 UTC' ) ) ?: $d );
	$dates = array_map( static fn( string $d ): string => $date( $d, 'j F Y' ), $days );
	$total = array_sum( $values );

	$yaxis = '';
	foreach ( [ 0, intdiv( $max, 2 ), $max ] as $tick ) {
		$yaxis .= sprintf( '<span style="inset-block-end: %s%%">%s</span>', $fmt( 100 * $tick / $max ), esc_html( number_format_i18n( $tick ) ) );
	}

	$xaxis = '';
	foreach ( array_unique( [ 0, intdiv( $count - 1, 2 ), $count - 1 ] ) as $i ) {
		// محور زمان dir=ltr است (جای فیزیکی left)؛ قالب «j F» چون «M» را تبدیل تاریخ شمسی سایت نمی‌شناسد
		$xaxis .= sprintf( '<span style="left: %s%%">%s</span>', $fmt( 100 * $i / ( $count - 1 ) ), esc_html( $date( $days[ $i ], 'j F' ) ) );
	}

	return sprintf(
		'<figure class="hodima-dr-chart" data-hodima-dr-chart data-values="%1$s" data-days="%2$s" data-max="%3$d">
			<figcaption class="hodima-dr-chart__title">%4$s <span class="hd-muted">— جمع %5$s در %6$s روز</span></figcaption>
			<div class="hodima-dr-chart__body" dir="ltr">
				<div class="hodima-dr-chart__y" aria-hidden="true">%7$s</div>
				<div class="hodima-dr-chart__plot">
					<svg viewBox="0 0 1000 200" preserveAspectRatio="none" role="img" aria-label="%8$s">
						<line class="hodima-dr-chart__grid" x1="0" x2="1000" y1="0" y2="0"/>
						<line class="hodima-dr-chart__grid" x1="0" x2="1000" y1="100" y2="100"/>
						<line class="hodima-dr-chart__base" x1="0" x2="1000" y1="200" y2="200"/>
						<path class="hodima-dr-chart__area" d="%9$s"/>
						<path class="hodima-dr-chart__line" d="%10$s"/>
					</svg>
					<span class="hodima-dr-chart__cross" hidden></span>
					<span class="hodima-dr-chart__dot" hidden></span>
					<div class="hodima-dr-chart__tip" hidden></div>
				</div>
				<div class="hodima-dr-chart__x" aria-hidden="true">%11$s</div>
			</div>
		</figure>',
		esc_attr( (string) wp_json_encode( $values ) ),
		esc_attr( (string) wp_json_encode( $dates, JSON_UNESCAPED_UNICODE ) ),
		$max,
		esc_html( $title ),
		esc_html( number_format_i18n( $total ) ),
		esc_html( number_format_i18n( $count ) ),
		$yaxis,
		esc_attr( sprintf( '%1$s روزانه، از %2$s تا %3$s؛ جمع %4$s', $title, $dates[0], $dates[ $count - 1 ], number_format_i18n( $total ) ) ),
		esc_attr( $area ),
		esc_attr( $line ),
		$xaxis
	);
}
