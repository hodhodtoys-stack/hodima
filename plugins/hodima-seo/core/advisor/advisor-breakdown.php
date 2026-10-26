<?php
/**
 * ماژول «مشاور سئو» — بخش‌بندی داده: دستگاه، کشور، جستجوی تصویر/ویدیو، نوع نمایش
 * Path: core/advisor/advisor-breakdown.php
 *
 * ابعاد تازه از همان Search Console API (همان کلید مخصوص مشاور، فقط‌خواندنی) با همان بازه دریافت اصلی:
 *   device         گزارش صفحه × دستگاه ← سهم هر دستگاه و صفحه‌هایی که رتبه موبایلشان خیلی بدتر است
 *   country        گزارش کشور (سطح سایت)
 *   appearance     نوع نمایش در گوگل (ریچ‌اسنیپت محصول، ویدیو، …؛ گوگل اجازه نمی‌دهد با بعد دیگر ترکیب شود)
 *   image / video  گزارش صفحه برای جستجوی تصویر و ویدیو (نوع جستجوی جدا)
 * نتیجه فشرده در یک گزینه (hodima_advisor_breakdown، autoload خاموش) می‌ماند، نه جدول؛ حجمش کوچک است.
 * هر بخش جدا دریافت می‌شود: شکست یک بخش بخش‌های دیگر و آخرین داده همان بخش را خراب نمی‌کند.
 * حداکثر هر ۱۲ ساعت یک بار (داده این ابعاد کند عوض می‌شود). شکستش هرگز دریافت اصلی را رد نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** گزینه نتیجه بخش‌بندی. */
const HODIMA_SEO_ADVISOR_BREAKDOWN_OPTION = 'hodima_advisor_breakdown';

/** کم‌ترین فاصله دو دریافت بخش‌بندی (ثانیه). */
const HODIMA_SEO_ADVISOR_BREAKDOWN_INTERVAL = 12 * HOUR_IN_SECONDS;

/** حداقل نمایش موبایل برای ارزیابی فاصله رتبه موبایل و دسکتاپ، و حداقل فاصله رتبه. */
const HODIMA_SEO_ADVISOR_GAP_MIN_IMPRESSIONS = 500;
const HODIMA_SEO_ADVISOR_GAP_MIN_POSITIONS   = 3.0;

/**
 * جمع کلیک/نمایش/رتبه وزنی ردیف‌ها به تفکیک کلید (بعد شماره $index).
 *
 * @param list<array<string, mixed>> $rows
 * @return array<string, array{clicks: int, impressions: int, position: float}>
 */
function hodima_seo_advisor_breakdown_sum( array $rows, int $index = 0 ): array {

	$sums = [];

	foreach ( $rows as $row ) {
		$key = strtolower( trim( (string) ( $row['keys'][ $index ] ?? '' ) ) );
		if ( '' === $key ) {
			continue;
		}
		$impressions = (int) round( (float) ( $row['impressions'] ?? 0 ) );
		$sums[ $key ] ??= [ 'clicks' => 0, 'impressions' => 0, 'weighted' => 0.0 ];
		$sums[ $key ]['clicks']      += (int) round( (float) ( $row['clicks'] ?? 0 ) );
		$sums[ $key ]['impressions'] += $impressions;
		$sums[ $key ]['weighted']    += (float) ( $row['position'] ?? 0 ) * $impressions;
	}

	return array_map(
		static fn( array $s ): array => [
			'clicks'      => $s['clicks'],
			'impressions' => $s['impressions'],
			'position'    => $s['impressions'] > 0 ? round( $s['weighted'] / $s['impressions'], 1 ) : 0.0,
		],
		$sums
	);
}

/**
 * گزارش صفحه × دستگاه ← سهم هر دستگاه و صفحه‌های با رتبه موبایل خیلی بدتر.
 *
 * @param list<array<string, mixed>> $rows keys = [آدرس، دستگاه]
 * @return array{devices: array<string, array{clicks: int, impressions: int, position: float}>, gaps: list<array<string, mixed>>}
 */
function hodima_seo_advisor_breakdown_devices( array $rows ): array {

	$by_page = [];

	foreach ( $rows as $row ) {
		$path   = hodima_seo_advisor_path_key( (string) ( $row['keys'][0] ?? '' ) );
		$device = strtolower( trim( (string) ( $row['keys'][1] ?? '' ) ) );
		if ( null === $path || '' === $device ) {
			continue;
		}
		$by_page[ $path ][] = [ 'keys' => [ $device ], 'clicks' => $row['clicks'] ?? 0, 'impressions' => $row['impressions'] ?? 0, 'position' => $row['position'] ?? 0 ];
	}

	$all_rows = [];
	$gaps     = [];

	foreach ( $by_page as $path => $list ) {
		array_push( $all_rows, ...$list );
		$sum    = hodima_seo_advisor_breakdown_sum( $list );
		$mobile = $sum['mobile'] ?? null;
		$desk   = $sum['desktop'] ?? null;

		if ( null === $mobile || null === $desk || $mobile['impressions'] < HODIMA_SEO_ADVISOR_GAP_MIN_IMPRESSIONS || $desk['impressions'] < 100 ) {
			continue;
		}

		$diff = $mobile['position'] - $desk['position'];
		if ( $diff >= HODIMA_SEO_ADVISOR_GAP_MIN_POSITIONS ) {
			$gaps[] = [ 'path' => (string) $path, 'mobile' => $mobile, 'desktop' => $desk, 'diff' => round( $diff, 1 ) ];
		}
	}

	usort( $gaps, static fn( array $a, array $b ): int => $b['mobile']['impressions'] <=> $a['mobile']['impressions'] );

	return [
		'devices' => hodima_seo_advisor_breakdown_sum( $all_rows ),
		'gaps'    => array_slice( $gaps, 0, 30 ),
	];
}

/**
 * گزارش صفحه برای یک نوع جستجو (تصویر/ویدیو) ← جمع و برترین صفحه‌ها.
 *
 * @param list<array<string, mixed>> $rows
 * @return array{clicks: int, impressions: int, pages: list<array{path: string, clicks: int, impressions: int, position: float}>}
 */
function hodima_seo_advisor_breakdown_pages( array $rows ): array {

	$by_path = hodima_seo_advisor_aggregate_pages( $rows );
	$pages   = [];

	foreach ( $by_path as $path => $s ) {
		$pages[] = [ 'path' => (string) $path ] + $s;
	}

	usort( $pages, static fn( array $a, array $b ): int => [ $b['clicks'], $b['impressions'] ] <=> [ $a['clicks'], $a['impressions'] ] );

	return [
		'clicks'      => array_sum( array_column( $pages, 'clicks' ) ),
		'impressions' => array_sum( array_column( $pages, 'impressions' ) ),
		'pages'       => array_slice( $pages, 0, 20 ),
	];
}

/**
 * نتیجه ذخیره‌شده آخرین بخش‌بندی (خالی اگر هنوز گرفته نشده).
 *
 * @return array<string, mixed>
 */
function hodima_seo_advisor_breakdown(): array {
	$stored = get_option( HODIMA_SEO_ADVISOR_BREAKDOWN_OPTION, [] );
	return is_array( $stored ) ? $stored : [];
}

/**
 * بخش‌های بخش‌بندی را از سرچ کنسول می‌گیرد و ذخیره می‌کند (حداکثر هر ۱۲ ساعت).
 *
 * @param array{start: string, end: string} $range بازه دریافت اصلی
 * @return array{fetched: int, errors: list<string>}|null null اگر هنوز زود است
 */
function hodima_seo_advisor_breakdown_sync( string $property, array $range, int $days, bool $force = false ): ?array {

	// فیلتر خاموش‌کردن (و آزمون‌های دریافت اصلی که فقط سه درخواست را می‌شمارند)
	if ( ! (bool) apply_filters( 'hodima_seo_advisor_breakdown_enabled', true ) ) {
		return null;
	}

	$old = hodima_seo_advisor_breakdown();

	if ( ! $force && (int) ( $old['fetched'] ?? 0 ) > time() - HODIMA_SEO_ADVISOR_BREAKDOWN_INTERVAL && (int) ( $old['days'] ?? 0 ) === $days ) {
		return null;
	}

	$new    = $old;
	$errors = [];
	$part   = 0;

	$request = static function ( array $dimensions, string $type = 'web' ) use ( $property, $range ): array|WP_Error {
		if ( 'web' === $type ) {
			return hodima_seo_advisor_fetch( $property, $range['start'], $range['end'], $dimensions );
		}
		return hodima_seo_sc_request( $property, $range['start'], $range['end'], $dimensions, 25000, $type, 0, HODIMA_SEO_SC_ADVISOR );
	};

	$label = [ 'device' => 'دستگاه', 'country' => 'کشور', 'appearance' => 'نوع نمایش', 'image' => 'جستجوی تصویر', 'video' => 'جستجوی ویدیو' ];

	$results = [
		'device'     => $request( [ 'page', 'device' ] ),
		'country'    => $request( [ 'country' ] ),
		'appearance' => $request( [ 'searchAppearance' ] ),
		'image'      => $request( [ 'page' ], 'image' ),
		'video'      => $request( [ 'page' ], 'video' ),
	];

	foreach ( $results as $name => $result ) {
		if ( is_wp_error( $result ) ) {
			if ( hodima_seo_sc_is_key_error( $result ) ) {
				return [ 'fetched' => 0, 'errors' => [ $result->get_error_message() ] ]; // با بخش دیگر هم درست نمی‌شود
			}
			$errors[] = $label[ $name ] . ': ' . $result->get_error_message();
			continue;
		}

		++$part;

		switch ( $name ) {
			case 'device':
				$new['device'] = hodima_seo_advisor_breakdown_devices( $result['rows'] );
				break;
			case 'country':
				$countries      = hodima_seo_advisor_breakdown_sum( $result['rows'] );
				uasort( $countries, static fn( array $a, array $b ): int => [ $b['clicks'], $b['impressions'] ] <=> [ $a['clicks'], $a['impressions'] ] );
				$new['country'] = array_slice( $countries, 0, 15, true );
				break;
			case 'appearance':
				$appearance        = hodima_seo_advisor_breakdown_sum( $result['rows'] );
				uasort( $appearance, static fn( array $a, array $b ): int => [ $b['clicks'], $b['impressions'] ] <=> [ $a['clicks'], $a['impressions'] ] );
				$new['appearance'] = array_slice( $appearance, 0, 30, true );
				break;
			default:
				$new[ $name ] = hodima_seo_advisor_breakdown_pages( $result['rows'] );
		}
	}

	if ( $part > 0 ) {
		$new['fetched'] = time();
		$new['days']    = $days;
		$new['start']   = $range['start'];
		$new['end']     = $range['end'];
	}

	$new['errors'] = $errors;
	update_option( HODIMA_SEO_ADVISOR_BREAKDOWN_OPTION, $new, false );

	return [ 'fetched' => $part, 'errors' => $errors ];
}
