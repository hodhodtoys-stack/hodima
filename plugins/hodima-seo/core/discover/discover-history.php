<?php
/**
 * ماژول «گوگل دیسکاور» — تاریخچه بلندمدت آمار و ثبت تغییرها
 * Path: core/discover/discover-history.php
 *
 * SEO 2.1.6. تا 2.1.5 فقط ۲۸ روز آخر، ۲۸ روز قبل و روند ۹۰ روزه نگه داشته
 * می‌شد و هر روز روی قبلی نوشته می‌شد؛ مقایسه ماه‌به‌ماه یا سال‌به‌سال ممکن
 * نبود و معلوم نبود عوض کردن عنوان یا تصویر کارت اثری داشته یا نه.
 *
 *   تاریخچه (گزینه hodima_discover_sc_history، autoload خاموش):
 *     daily        کل سایت روز به روز، همیشگی؛ بار اول ۱۶ ماه گذشته از سرچ
 *                  کنسول (بیشترین مدتی که نگه می‌دارد) یک‌جا گرفته می‌شود
 *     months       هر صفحه ماه به ماه (ماه کامل‌شده؛ هر به‌روزرسانی چند ماه
 *                  گذشته را هم پر می‌کند تا ۱۶ ماه)
 *     pages_daily  روز به روز صفحه‌هایی که تغییری ثبت‌شده دارند (برای «قبل و
 *                  بعد» هر تغییر)
 *   تغییرها (گزینه hodima_discover_changes): عنوان کارت، تصویر کارت و نقطه
 *     تمرکز هر صفحه با تاریخ و مقدار قبل/بعد؛ روی نمودار گزارش علامت می‌خورند.
 *
 * همه درخواست‌ها به گوگل فقط هنگام به‌روزرسانی آمار (کرون روزانه یا دکمه
 * پیشخوان؛ discover-stats.php)، هرگز هنگام بازدید سایت.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** گزینه تاریخچه آمار. */
const HODIMA_SEO_DISCOVER_HISTORY_OPTION = 'hodima_discover_sc_history';

/** گزینه فهرست تغییرها. */
const HODIMA_SEO_DISCOVER_CHANGES_OPTION = 'hodima_discover_changes';

/** بیشترین ماه‌هایی که سرچ کنسول نگه می‌دارد. */
const HODIMA_SEO_DISCOVER_HISTORY_MONTHS = 16;

/** ماه‌های گذشته‌ای که هر به‌روزرسانی (به‌جز ماه تازه) پر می‌کند. */
const HODIMA_SEO_DISCOVER_HISTORY_MONTHS_PER_RUN = 3;

/** بیشترین تغییرهای نگه‌داشته‌شده. */
const HODIMA_SEO_DISCOVER_CHANGES_MAX = 300;

/** روزهای «قبل» و «بعد» هر تغییر برای مقایسه. */
const HODIMA_SEO_DISCOVER_CHANGE_WINDOW = 28;

/* =====================================================================
 * تاریخچه آمار
 * ===================================================================== */

/**
 * تاریخچه ذخیره‌شده. اعداد فشرده [ کلیک, نمایش ].
 *
 * @return array{property: string, backfilled: bool, daily: array<string, array{0: int, 1: int}>, months: array<string, array<string, array{0: int, 1: int}>>, pages_daily: array<string, array<string, array{0: int, 1: int}>>}
 */
function hodima_seo_discover_history(): array {

	$h    = get_option( HODIMA_SEO_DISCOVER_HISTORY_OPTION, [] );
	$h    = is_array( $h ) ? $h : [];
	$pair = static fn( mixed $v ): array => [ (int) ( is_array( $v ) ? ( $v[0] ?? 0 ) : 0 ), (int) ( is_array( $v ) ? ( $v[1] ?? 0 ) : 0 ) ];
	$map  = static function ( mixed $rows ) use ( $pair ): array {
		$out = [];
		foreach ( is_array( $rows ) ? $rows : [] as $key => $row ) {
			$out[ (string) $key ] = $pair( $row );
		}
		return $out;
	};
	$nest = static function ( mixed $groups ) use ( $map ): array {
		$out = [];
		foreach ( is_array( $groups ) ? $groups : [] as $key => $rows ) {
			$out[ (string) $key ] = $map( $rows );
		}
		return $out;
	};

	return [
		'property'    => (string) ( $h['property'] ?? '' ),
		'backfilled'  => ! empty( $h['backfilled'] ),
		'daily'       => $map( $h['daily'] ?? [] ),
		'months'      => $nest( $h['months'] ?? [] ),
		'pages_daily' => $nest( $h['pages_daily'] ?? [] ),
	];
}

/**
 * ردیف‌های پاسخ سرچ کنسول ← [ کلید ← [کلیک, نمایش] ] (کلید صفحه با
 * hodima_seo_discover_url_key؛ ردیف‌های هم‌کلید جمع می‌شوند).
 *
 * @param list<array<string, mixed>> $rows
 * @return array<string, array{0: int, 1: int}>
 */
function hodima_seo_discover_history_pairs( array $rows, bool $by_page ): array {
	$out = [];
	foreach ( hodima_seo_discover_sc_rows( $rows, $by_page )['rows'] as $key => $row ) {
		$out[ $key ] = [ $row['clicks'], $row['impressions'] ];
	}
	return $out;
}

/**
 * به‌روزرسانی تاریخچه بعد از دریافت موفق آمار (discover-stats.php).
 *
 * @param callable(string, string, string, list<string>, int=): (array{rows: list<array<string, mixed>>}|WP_Error) $query
 * @param array{start: string, end: string, prev_start: string, prev_end: string, daily_start: string} $range
 * @param array{rows: list<array<string, mixed>>}|WP_Error $daily  روزانه ۹۰ روز همین دور
 */
function hodima_seo_discover_history_update( string $property, callable $query, array $range, array|WP_Error $daily ): void {

	$h = hodima_seo_discover_history();

	// property دیگر = سایت دیگر: تاریخچه از نو
	if ( $h['property'] !== $property ) {
		$h = [ 'property' => $property, 'backfilled' => false, 'daily' => [], 'months' => [], 'pages_daily' => [] ];
	}

	$tz    = new DateTimeZone( 'America/Los_Angeles' );
	$end   = new DateTimeImmutable( $range['end'], $tz );
	$first = $end->modify( 'first day of this month' )->modify( '-' . HODIMA_SEO_DISCOVER_HISTORY_MONTHS . ' months' );

	// ۱. روزانه کل سایت: ۹۰ روز همین دور روی تاریخچه (داده تازه‌تر جایگزین می‌شود)
	if ( ! is_wp_error( $daily ) ) {
		$h['daily'] = hodima_seo_discover_history_pairs( $daily['rows'], false ) + $h['daily'];
	}

	// ۲. بار اول: ۱۶ ماه گذشته یک‌جا
	if ( ! $h['backfilled'] ) {
		$old = $query( $property, $first->format( 'Y-m-d' ), $range['end'], [ 'date' ], 5000 );
		if ( ! is_wp_error( $old ) ) {
			$h['daily']      = $h['daily'] + hodima_seo_discover_history_pairs( $old['rows'], false );
			$h['backfilled'] = true;
		}
	}
	ksort( $h['daily'] );

	// ۳. هر صفحه ماه به ماه: ماه‌های کامل‌شده‌ای که هنوز نیستند (تازه‌ترها اول)
	$months = [];
	for ( $m = $end->modify( 'first day of this month' ); $m >= $first; $m = $m->modify( '-1 month' ) ) {
		$last = $m->modify( 'last day of this month' );
		if ( $last <= $end && ! isset( $h['months'][ $m->format( 'Y-m' ) ] ) ) {
			$months[] = $m;
		}
	}
	foreach ( array_slice( $months, 0, HODIMA_SEO_DISCOVER_HISTORY_MONTHS_PER_RUN ) as $m ) {
		$rows = $query( $property, $m->format( 'Y-m-d' ), $m->modify( 'last day of this month' )->format( 'Y-m-d' ), [ 'page' ], 5000 );
		if ( ! is_wp_error( $rows ) ) {
			$h['months'][ $m->format( 'Y-m' ) ] = hodima_seo_discover_history_pairs( $rows['rows'], true );
		}
	}
	// ماه‌های بیرون از ۱۶ ماه نگه داشته می‌شوند (تاریخچه همیشگی)؛ فقط مرتب
	krsort( $h['months'] );

	// ۴. روز به روز صفحه‌هایی که تغییر ثبت‌شده دارند (برای «قبل و بعد»)
	$tracked = hodima_seo_discover_tracked_keys();
	$seen    = [];
	if ( $tracked ) {
		$rows = $query( $property, $range['daily_start'], $range['end'], [ 'page', 'date' ], 25000 );
		if ( ! is_wp_error( $rows ) ) {
			foreach ( $rows['rows'] as $row ) {
				$key  = hodima_seo_discover_url_key( (string) ( $row['keys'][0] ?? '' ) );
				$date = (string) ( $row['keys'][1] ?? '' );
				if ( '' === $date || ! isset( $tracked[ $key ] ) ) {
					continue;
				}
				$prev                             = $h['pages_daily'][ $key ][ $date ] ?? [ 0, 0 ];
				$fresh                            = [ (int) round( (float) ( $row['clicks'] ?? 0 ) ), (int) round( (float) ( $row['impressions'] ?? 0 ) ) ];
				$h['pages_daily'][ $key ][ $date ] = isset( $seen[ $key ][ $date ] ) ? [ $prev[0] + $fresh[0], $prev[1] + $fresh[1] ] : $fresh;
				$seen[ $key ][ $date ]            = true;
			}
		}
	}
	// صفحه‌ای که دیگر تغییر تازه‌ای ندارد و روزهای خیلی کهنه کنار می‌روند
	$cutoff = $end->modify( '-400 days' )->format( 'Y-m-d' );
	foreach ( $h['pages_daily'] as $key => $days ) {
		if ( ! isset( $tracked[ $key ] ) ) {
			unset( $h['pages_daily'][ $key ] );
			continue;
		}
		$days = array_filter( $days, static fn( string $d ): bool => $d >= $cutoff, ARRAY_FILTER_USE_KEY );
		ksort( $days );
		$h['pages_daily'][ $key ] = $days;
	}

	update_option( HODIMA_SEO_DISCOVER_HISTORY_OPTION, $h, false );
}

/**
 * جمع ماه به ماه کل سایت از تاریخچه روزانه (جدیدترین اول).
 *
 * @return array<string, array{clicks: int, impressions: int, days: int}>
 */
function hodima_seo_discover_history_monthly(): array {
	$out = [];
	foreach ( hodima_seo_discover_history()['daily'] as $date => [ $clicks, $impressions ] ) {
		$month                        = substr( $date, 0, 7 );
		$out[ $month ]              ??= [ 'clicks' => 0, 'impressions' => 0, 'days' => 0 ];
		$out[ $month ]['clicks']      += $clicks;
		$out[ $month ]['impressions'] += $impressions;
		++$out[ $month ]['days'];
	}
	krsort( $out );
	return $out;
}

/**
 * روزانه کل سایت در یک بازه (به شکل hodima_seo_discover_stats()['daily']):
 * تاریخچه، وگرنه همان ۹۰ روز آمار.
 *
 * @return array<string, array{clicks: int, impressions: int}>
 */
function hodima_seo_discover_history_daily( int $days ): array {

	$daily = [];
	foreach ( hodima_seo_discover_history()['daily'] as $date => [ $clicks, $impressions ] ) {
		$daily[ $date ] = [ 'clicks' => $clicks, 'impressions' => $impressions ];
	}

	$daily = $daily + hodima_seo_discover_stats()['daily'];
	ksort( $daily );

	return $days > 0 ? array_slice( $daily, -$days, null, true ) : $daily;
}

/**
 * یک صفحه ماه به ماه (جدیدترین اول؛ ماه بدون نمایش = صفر اگر آن ماه گرفته شده).
 *
 * @return array<string, array{clicks: int, impressions: int}>
 */
function hodima_seo_discover_page_months( string $url_key, int $limit = 12 ): array {
	$out = [];
	foreach ( hodima_seo_discover_history()['months'] as $month => $rows ) {
		[ $clicks, $impressions ] = $rows[ $url_key ] ?? [ 0, 0 ];
		$out[ $month ]            = [ 'clicks' => $clicks, 'impressions' => $impressions ];
	}
	krsort( $out );
	return array_slice( $out, 0, $limit, true );
}

/* =====================================================================
 * ثبت تغییرها
 * ===================================================================== */

/**
 * فهرست تغییرها (قدیمی اول).
 *
 * @return list<array{t: int, ctx: string, id: int, key: string, what: string, from: string, to: string}>
 */
function hodima_seo_discover_changes(): array {
	$list = get_option( HODIMA_SEO_DISCOVER_CHANGES_OPTION, [] );
	$out  = [];
	foreach ( is_array( $list ) ? $list : [] as $c ) {
		if ( is_array( $c ) && isset( $c['t'], $c['id'], $c['what'] ) ) {
			$out[] = [
				't'    => (int) $c['t'],
				'ctx'  => 'term' === ( $c['ctx'] ?? '' ) ? 'term' : 'post',
				'id'   => (int) $c['id'],
				'key'  => (string) ( $c['key'] ?? '' ),
				'what' => (string) $c['what'],
				'from' => (string) ( $c['from'] ?? '' ),
				'to'   => (string) ( $c['to'] ?? '' ),
			];
		}
	}
	return $out;
}

/** نام فارسی نوع تغییر. */
function hodima_seo_discover_change_label( string $what ): string {
	return match ( $what ) {
		'title' => 'عنوان کارت',
		'image' => 'تصویر کارت',
		'focus' => 'نقطه تمرکز برش‌ها',
		default => $what,
	};
}

/** متن کوتاه یک تصویر برای فهرست تغییرها (نام فایل). */
function hodima_seo_discover_change_image_text( int $attachment_id ): string {
	return $attachment_id > 0 ? wp_basename( (string) get_attached_file( $attachment_id ) ) : 'بدون تصویر';
}

/**
 * ثبت یک تغییر کارت. تغییرهای پشت هم یک صفحه و یک مورد در ۱۰ دقیقه یکی
 * می‌شوند (همان «قبل» اول، «بعد» آخر) تا چند ذخیره پشت هم نمودار را شلوغ نکند.
 */
function hodima_seo_discover_log_change( string $context, int $id, string $what, string $from, string $to ): void {

	// پیش‌نویس هنوز در گوگل نیست؛ تغییرش روی آمار اثری ندارد
	if ( $from === $to || $id <= 0 || ( 'post' === $context && 'publish' !== get_post_status( $id ) ) ) {
		return;
	}

	$list = hodima_seo_discover_changes();
	$now  = time();
	$last = array_key_last( $list );

	if ( null !== $last && $list[ $last ]['ctx'] === $context && $list[ $last ]['id'] === $id && $list[ $last ]['what'] === $what && $now - $list[ $last ]['t'] < 10 * MINUTE_IN_SECONDS ) {
		if ( $list[ $last ]['from'] === $to ) {
			array_pop( $list ); // به حالت قبل برگشت: تغییری نبود
		} else {
			$list[ $last ]['to'] = $to;
			$list[ $last ]['t']  = $now;
		}
	} else {
		$list[] = [
			't'    => $now,
			'ctx'  => $context,
			'id'   => $id,
			'key'  => hodima_seo_discover_url_key( hodima_seo_discover_object_url( $id, $context ) ),
			'what' => $what,
			'from' => $from,
			'to'   => $to,
		];
	}

	update_option( HODIMA_SEO_DISCOVER_CHANGES_OPTION, array_slice( $list, -HODIMA_SEO_DISCOVER_CHANGES_MAX ), false );
}

/**
 * تغییرهای یک صفحه (جدیدترین اول).
 *
 * @return list<array{t: int, ctx: string, id: int, key: string, what: string, from: string, to: string}>
 */
function hodima_seo_discover_object_changes( string $context, int $id ): array {
	return array_reverse( array_values( array_filter(
		hodima_seo_discover_changes(),
		static fn( array $c ): bool => $c['ctx'] === $context && $c['id'] === $id
	) ) );
}

/**
 * کلید آدرس صفحه‌هایی که در ۴۰۰ روز گذشته تغییر داشته‌اند (برای روزانه هر صفحه).
 *
 * @return array<string, true>
 */
function hodima_seo_discover_tracked_keys(): array {
	$out   = [];
	$since = time() - 400 * DAY_IN_SECONDS;
	foreach ( hodima_seo_discover_changes() as $c ) {
		if ( $c['t'] >= $since && '' !== $c['key'] ) {
			$out[ $c['key'] ] = true;
		}
	}
	return $out;
}

/**
 * اثر یک تغییر: میانگین روزانه نمایش و کلیک دیسکاور در ۲۸ روز پیش و پس از آن
 * (روز خود تغییر حساب نمی‌شود)، از روزانه همان صفحه. null اگر داده‌ای نیست.
 *
 * @param array{t: int, key: string} $change
 * @return array{before_days: int, after_days: int, before: array{clicks: float, impressions: float}, after: array{clicks: float, impressions: float}}|null
 */
function hodima_seo_discover_change_effect( array $change ): ?array {

	$days = hodima_seo_discover_history()['pages_daily'][ $change['key'] ] ?? [];

	if ( ! $days ) {
		return null;
	}

	$tz     = new DateTimeZone( 'America/Los_Angeles' ); // روزهای سرچ کنسول
	$day    = ( new DateTimeImmutable( '@' . $change['t'] ) )->setTimezone( $tz );
	$from   = $day->modify( '-' . HODIMA_SEO_DISCOVER_CHANGE_WINDOW . ' days' )->format( 'Y-m-d' );
	$to     = $day->modify( '+' . HODIMA_SEO_DISCOVER_CHANGE_WINDOW . ' days' )->format( 'Y-m-d' );
	$last   = (string) array_key_last( $days );
	$sum    = static function ( string $a, string $b ) use ( $days ): array {
		$c = 0;
		$i = 0;
		foreach ( $days as $d => [ $clicks, $impressions ] ) {
			if ( $d >= $a && $d <= $b ) {
				$c += $clicks;
				$i += $impressions;
			}
		}
		return [ $c, $i ];
	};
	$span   = static fn( string $a, string $b ): int => max( 0, (int) round( ( strtotime( $b . ' 12:00 UTC' ) - strtotime( $a . ' 12:00 UTC' ) ) / DAY_IN_SECONDS ) + 1 );

	// روزهای پیش از تغییر که داده روزانه داریم (اولین روز ذخیره‌شده) و روزهای بعد تا آخرین روز موجود
	$before_from = max( $from, (string) array_key_first( $days ) );
	$before_to   = $day->modify( '-1 day' )->format( 'Y-m-d' );
	$after_from  = $day->modify( '+1 day' )->format( 'Y-m-d' );
	$after_to    = min( $to, $last );

	$before_days = $before_from <= $before_to ? $span( $before_from, $before_to ) : 0;
	$after_days  = $after_from <= $after_to ? $span( $after_from, $after_to ) : 0;

	[ $bc, $bi ] = $before_days ? $sum( $before_from, $before_to ) : [ 0, 0 ];
	[ $ac, $ai ] = $after_days ? $sum( $after_from, $after_to ) : [ 0, 0 ];

	return [
		'before_days' => $before_days,
		'after_days'  => $after_days,
		'before'      => [ 'clicks' => $before_days ? $bc / $before_days : 0.0, 'impressions' => $before_days ? $bi / $before_days : 0.0 ],
		'after'       => [ 'clicks' => $after_days ? $ac / $after_days : 0.0, 'impressions' => $after_days ? $ai / $after_days : 0.0 ],
	];
}

/* ── ثبت خودکار: تصویر شاخص (وقتی تصویر جدای دیسکاور نیست، همان تصویر کارت است) ── */
add_action( 'update_post_meta', static function ( int $meta_id, int $post_id, string $meta_key, mixed $value ): void {
	if ( '_thumbnail_id' === $meta_key && hodima_seo_discover_for_post( $post_id ) && ! hodima_seo_discover_data( $post_id )['image_id'] ) {
		$old = (int) get_post_meta( $post_id, '_thumbnail_id', true ); // پیش از ذخیره
		if ( $old !== (int) $value ) {
			hodima_seo_discover_log_change( 'post', $post_id, 'image', hodima_seo_discover_change_image_text( $old ), hodima_seo_discover_change_image_text( (int) $value ) );
		}
	}
}, 10, 4 );

add_action( 'added_post_meta', static function ( int $meta_id, int $post_id, string $meta_key, mixed $value ): void {
	if ( '_thumbnail_id' === $meta_key && hodima_seo_discover_for_post( $post_id ) && ! hodima_seo_discover_data( $post_id )['image_id'] ) {
		hodima_seo_discover_log_change( 'post', $post_id, 'image', hodima_seo_discover_change_image_text( 0 ), hodima_seo_discover_change_image_text( (int) $value ) );
	}
}, 10, 4 );

/* ── ثبت خودکار: نام نوشته/محصول (وقتی عنوان جدای دیسکاور نیست، همان عنوان کارت است) ── */
add_action( 'post_updated', static function ( int $post_id, WP_Post $after, WP_Post $before ): void {
	if ( $after->post_title !== $before->post_title && 'publish' === $before->post_status && hodima_seo_discover_for_post( $post_id ) && '' === hodima_seo_discover_data( $post_id )['title'] ) {
		hodima_seo_discover_log_change( 'post', $post_id, 'title', $before->post_title, $after->post_title );
	}
}, 10, 3 );

/** روز یک لحظه به وقت سرچ کنسول (اقیانوس آرام)، «Y-m-d». */
function hodima_seo_discover_sc_day( int $timestamp ): string {
	return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'America/Los_Angeles' ) )->format( 'Y-m-d' );
}

/**
 * متن یک تغییر: «عنوان کارت: «الف» ← «ب»» (با نام صفحه اگر $with_page).
 *
 * @param array{t: int, ctx: string, id: int, key: string, what: string, from: string, to: string} $change
 */
function hodima_seo_discover_change_entry_text( array $change, bool $with_page = true ): string {
	$page = $with_page ? hodima_seo_discover_object_title( (int) $change['id'], (string) $change['ctx'] ) : '';
	return ( '' !== $page ? $page . ' — ' : '' ) . hodima_seo_discover_change_label( (string) $change['what'] ) . ': «' . ( '' !== $change['from'] ? $change['from'] : '—' ) . '» ← «' . $change['to'] . '»';
}

/**
 * تغییرها به شکل علامت نمودار: روز ← متن‌ها.
 *
 * @param list<array{t: int, ctx: string, id: int, key: string, what: string, from: string, to: string}> $changes
 * @return array<string, list<string>>
 */
function hodima_seo_discover_change_marks( array $changes, bool $with_page = true ): array {
	$out = [];
	foreach ( $changes as $c ) {
		$out[ hodima_seo_discover_sc_day( $c['t'] ) ][] = hodima_seo_discover_change_entry_text( $c, $with_page );
	}
	return $out;
}

/** نام فارسی ماه میلادی «Y-m» (ماه‌های سرچ کنسول میلادی‌اند؛ تبدیل شمسی سایت آن‌ها را جابه‌جا نشان می‌داد). */
function hodima_seo_discover_month_label( string $month ): string {
	$names = [ 'ژانویه', 'فوریه', 'مارس', 'آوریل', 'مه', 'ژوئن', 'ژوئیه', 'اوت', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر' ];
	[ $year, $num ] = array_map( 'intval', array_pad( explode( '-', $month ), 2, '1' ) );
	// سال بدون جداکننده هزارگان («۲۰۲۶»، نه «۲٬۰۲۶»)
	return ( $names[ $num - 1 ] ?? $month ) . ' ' . strtr( (string) $year, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/**
 * متن «قبل ← بعد» اثر یک تغییر (میانگین روزانه).
 *
 * @param array{before_days: int, after_days: int, before: array{clicks: float, impressions: float}, after: array{clicks: float, impressions: float}}|null $effect
 */
function hodima_seo_discover_effect_text( ?array $effect ): string {

	if ( null === $effect || 0 === $effect['before_days'] + $effect['after_days'] ) {
		return 'آمار روزانه این صفحه بعد از به‌روزرسانی بعدی آمار می‌آید.';
	}

	if ( $effect['after_days'] < 3 ) {
		return 'هنوز زود است؛ چند روز بعد از تغییر مقایسه می‌شود.';
	}

	$n    = static fn( float $v ): string => number_format_i18n( $v, $v < 10 ? 1 : 0 );
	$diff = hodima_seo_discover_change( (int) round( 100 * $effect['after']['impressions'] ), $effect['before']['impressions'] > 0 ? (int) round( 100 * $effect['before']['impressions'] ) : null );

	return sprintf(
		'نمایش %1$s ← %2$s%3$s، کلیک %4$s ← %5$s%6$s',
		$n( $effect['before']['impressions'] ),
		$n( $effect['after']['impressions'] ),
		null !== $diff ? ' (' . hodima_seo_discover_change_text( $diff ) . ')' : '',
		$n( $effect['before']['clicks'] ),
		$n( $effect['after']['clicks'] ),
		$effect['after_days'] < HODIMA_SEO_DISCOVER_CHANGE_WINDOW ? sprintf( ' — %s روز بعد از تغییر', number_format_i18n( $effect['after_days'] ) ) : ''
	);
}
