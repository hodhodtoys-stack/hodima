<?php
/**
 * ماژول «مشاور سئو» — تحلیل‌های کل سایت از همان داده Search Console
 * Path: core/advisor/advisor-insights.php
 *
 *  ۱. آدرس‌های تکراری یک صفحه: وقتی گوگل یک صفحه را با چند آدرس نشان می‌دهد (با و بی www، http و
 *     https، پارامتر، اسلش پایانی یا حروف بزرگ). آدرس‌ها در ذخیره‌سازی یکی می‌شوند، پس فهرست
 *     آدرس‌های خام در هر دریافت داده جدا ساخته و در گزینه‌ای نگه داشته می‌شود.
 *  ۲. کلمه‌های برندی در برابر غیربرندی: سهم کلیک و نمایش کلمه‌هایی که نام سایت را دارند.
 *
 * فقط می‌خواند و پیشنهاد می‌دهد؛ چیزی را در سایت عوض نمی‌کند. «پاک کردن داده‌های مشاور» این‌ها را
 * هم پاک می‌کند (از همان دریافت بعدی دوباره ساخته می‌شوند).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** گزینه فهرست آدرس‌های تکراری (autoload خاموش). */
const HODIMA_SEO_ADVISOR_VARIANTS_OPTION = 'hodima_advisor_variants';

/** حداکثر صفحه با آدرس تکراری که نگه داشته می‌شود. */
const HODIMA_SEO_ADVISOR_VARIANTS_MAX = 100;

/**
 * علت‌های فرق چند آدرس یک صفحه.
 *
 * @param list<string> $urls آدرس‌های خام سرچ کنسول
 * @return list<string> host (دامنه/پروتکل)، query (پارامتر)، case (حروف بزرگ)، slash (اسلش پایانی)
 */
function hodima_seo_advisor_variant_causes( array $urls ): array {

	$causes = [];
	$origin = [];
	$paths  = [];

	foreach ( $urls as $url ) {
		$parts = (array) wp_parse_url( $url );
		$origin[ strtolower( (string) ( $parts['scheme'] ?? '' ) . '://' . (string) ( $parts['host'] ?? '' ) ) ] = true;
		$paths[]                                                                                               = (string) ( $parts['path'] ?? '/' );
		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$causes['query'] = true;
		}
	}

	if ( count( $origin ) > 1 ) {
		$causes['host'] = true;
	}

	$decoded = array_map( static fn( string $p ): string => rawurldecode( $p ), $paths );
	$bare    = array_map( static fn( string $p ): string => rtrim( $p, '/' ), $decoded ); // بدون اسلش پایانی
	$lower   = array_map( 'strtolower', $decoded );
	$both    = array_map( 'strtolower', $bare );

	if ( count( array_unique( $bare ) ) > count( array_unique( $both ) ) ) {
		$causes['case'] = true; // فقط حروف بزرگ و کوچک فرق دارد
	}

	if ( count( array_unique( $lower ) ) > count( array_unique( $both ) ) ) {
		$causes['slash'] = true; // فقط اسلش پایانی فرق دارد
	}

	return array_values( array_intersect( [ 'host', 'query', 'case', 'slash' ], array_keys( $causes ) ) ); // ترتیب ثابت
}

/**
 * دستور رفع آدرس‌های تکراری بر اساس علت.
 *
 * @param list<string> $causes
 */
function hodima_seo_advisor_variant_advice( array $causes ): string {

	$steps = [];

	if ( in_array( 'host', $causes, true ) ) {
		$steps[] = 'نسخه‌ای از آدرس را که نباید باشد (با یا بدون www، http یا https) با ریدایرکت ۳۰۱ به نسخه اصلی ببرید.';
	}
	if ( in_array( 'query', $causes, true ) ) {
		$steps[] = 'آدرس‌های دارای پارامتر (بعد از ؟) را با canonical به آدرس تمیز ببرید یا اگر لازم نیستند ریدایرکت کنید.';
	}
	if ( in_array( 'case', $causes, true ) || in_array( 'slash', $causes, true ) ) {
		$steps[] = 'آدرس با حروف بزرگ یا بدون اسلش پایانی را به شکل استاندارد ریدایرکت ۳۰۱ کنید.';
	}

	return $steps ? implode( ' ', $steps ) . ' بعد از اصلاح، لینک‌های داخلی را هم فقط به آدرس اصلی بدهید.' : 'یک آدرس را اصلی کنید و بقیه را ریدایرکت ۳۰۱ کنید.';
}

/**
 * صفحه‌هایی که با بیش از یک آدرس خام در سرچ کنسول آمده‌اند (پرنمایش‌ترین‌ها اول).
 *
 * @param list<array<string, mixed>> $rows ردیف‌های گزارش «صفحه» سرچ کنسول
 * @return list<array{path: string, impressions: int, clicks: int, urls: list<array{url: string, impressions: int, clicks: int}>, causes: list<string>, advice: string}>
 */
function hodima_seo_advisor_find_variants( array $rows ): array {

	$by_path = [];

	foreach ( $rows as $row ) {
		$raw  = (string) ( $row['keys'][0] ?? '' );
		$path = hodima_seo_advisor_path_key( $raw );
		if ( null === $path ) {
			continue;
		}
		$url = (string) strtok( $raw, '#' ); // بخش #… یک آدرس دیگر نیست
		$by_path[ $path ][ $url ]['impressions'] = ( $by_path[ $path ][ $url ]['impressions'] ?? 0 ) + (int) round( (float) ( $row['impressions'] ?? 0 ) );
		$by_path[ $path ][ $url ]['clicks']      = ( $by_path[ $path ][ $url ]['clicks'] ?? 0 ) + (int) round( (float) ( $row['clicks'] ?? 0 ) );
	}

	$out = [];

	foreach ( $by_path as $path => $urls ) {
		if ( count( $urls ) < 2 ) {
			continue;
		}

		$list = [];
		foreach ( $urls as $url => $stat ) {
			$list[] = [ 'url' => (string) $url, 'impressions' => $stat['impressions'], 'clicks' => $stat['clicks'] ];
		}
		usort( $list, static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );

		$causes = hodima_seo_advisor_variant_causes( array_column( $list, 'url' ) );

		$out[] = [
			'path'        => (string) $path,
			'impressions' => array_sum( array_column( $list, 'impressions' ) ),
			'clicks'      => array_sum( array_column( $list, 'clicks' ) ),
			'urls'        => array_slice( $list, 0, 5 ),
			'causes'      => $causes,
			'advice'      => hodima_seo_advisor_variant_advice( $causes ),
		];
	}

	usort( $out, static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );

	return array_slice( $out, 0, HODIMA_SEO_ADVISOR_VARIANTS_MAX );
}

/**
 * فهرست ذخیره‌شده آدرس‌های تکراری از آخرین دریافت داده.
 *
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_variants(): array {
	$stored = get_option( HODIMA_SEO_ADVISOR_VARIANTS_OPTION, [] );
	return is_array( $stored ) ? array_values( array_filter( $stored, 'is_array' ) ) : [];
}

/**
 * سهم کلمه‌های برندی (دارای نام سایت) و غیربرندی از کلیک و نمایش، از جدول کلمه‌ها.
 * جدول کلمه‌ها برای هر آدرس فقط ≤ ۵۰ کلمه با ≥ ۵ نمایش نگه می‌دارد و گوگل کلمه‌های ناشناس را
 * نمی‌دهد؛ پس سهم‌ها «از کلمه‌های ثبت‌شده»‌اند و پوشش (coverage) نسبت نمایش آن‌ها به کل صفحه‌هاست.
 *
 * @return array{brand: array{clicks: int, impressions: int, terms: int}, other: array{clicks: int, impressions: int, terms: int}, brand_top: list<array<string, mixed>>, other_top: list<array<string, mixed>>, coverage: float, words: list<string>}
 */
function hodima_seo_advisor_brand_split( int $top = 10 ): array {

	global $wpdb;

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT MAX(term) AS term, COUNT(*) AS pages, SUM(clicks) AS total_clicks, SUM(impressions) AS total_impressions, SUM(avg_position * impressions) AS weighted FROM %i GROUP BY term_key ORDER BY total_impressions DESC LIMIT 5000', hodima_seo_advisor_table( 'queries' ) ),
		ARRAY_A
	);

	$split = [
		'brand'     => [ 'clicks' => 0, 'impressions' => 0, 'terms' => 0 ],
		'other'     => [ 'clicks' => 0, 'impressions' => 0, 'terms' => 0 ],
		'brand_top' => [],
		'other_top' => [],
	];

	foreach ( $rows as $row ) {
		$impressions = (int) $row['total_impressions'];
		$key         = hodima_seo_advisor_is_brand( (string) $row['term'] ) ? 'brand' : 'other';

		$split[ $key ]['clicks']      += (int) $row['total_clicks'];
		$split[ $key ]['impressions'] += $impressions;
		++$split[ $key ]['terms'];

		if ( count( $split[ $key . '_top' ] ) < $top ) {
			$split[ $key . '_top' ][] = [
				'term'        => (string) $row['term'],
				'pages'       => (int) $row['pages'],
				'clicks'      => (int) $row['total_clicks'],
				'impressions' => $impressions,
				'position'    => $impressions > 0 ? round( (float) $row['weighted'] / $impressions, 1 ) : 0.0,
			];
		}
	}

	$total_impressions = hodima_seo_advisor_totals()['impressions'];
	$listed            = $split['brand']['impressions'] + $split['other']['impressions'];

	return $split + [
		'coverage' => $total_impressions > 0 ? min( 1.0, $listed / $total_impressions ) : 0.0,
		'words'    => hodima_seo_advisor_brand_terms(),
	];
}
