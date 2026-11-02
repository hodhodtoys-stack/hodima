<?php
/**
 * ماژول «دستار سئو» — نمره «سلامت سایت» (SEO 3.1.6)
 * Path: core/advisor/advisor-score.php
 *
 * از ایرادهای خزش (advisor-crawl.php) یک نمره ۰ تا ۱۰۰ و پنج نمره بخش می‌سازد (طرح تأییدشده کاربر ۸ آبان ۱۴۰۵):
 * هر ایراد نمره بخش خودش را به نسبت شمار صفحه‌های دارای آن ایراد کم می‌کند (ضریب: قرمز ۲، کهربایی ۱، آبی ۰٫۳؛
 * مثلا ۵٪ صفحه‌ها با یک ایراد قرمز = ۱۰ امتیاز). نمره کل میانگین وزنی بخش‌هاست. تاریخچه نمره بعد از هر دور خزش
 * ثبت می‌شود تا «بهتر یا بدتر از دور قبل» معلوم باشد. فقط می‌خواند و چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** تاریخچه نمره دورهای خزش (آخرین ۱۲؛ autoload نه). */
const HODIMA_SEO_ADVISOR_SCORE_OPTION = 'hodima_advisor_score';

/** ضریب کسر نمره هر سطح ایراد. */
const HODIMA_SEO_ADVISOR_SCORE_WEIGHTS = [ 'bad' => 2.0, 'warn' => 1.0, 'info' => 0.3 ];

/**
 * بخش‌های نمره: کلید فیلتر جدول ایرادها (cf)، برچسب، وزن در نمره کل و کدهای ایرادش.
 */
const HODIMA_SEO_ADVISOR_SCORE_CATEGORIES = [
	'cat-links'     => [
		'label'  => 'لینک‌ها و آدرس‌ها',
		'weight' => 30,
		'codes'  => [ 'link_broken', 'link_redirect', 'link_noindex', 'redirect_chain', 'broken', 'redirect', 'canonical_bad', 'sitemap_bad', 'http_link' ],
	],
	'cat-meta'      => [
		'label'  => 'عنوان و متا',
		'weight' => 20,
		'codes'  => [ 'no_title', 'title_short', 'title_long', 'no_meta', 'no_snippet', 'dup_title' ],
	],
	'cat-content'   => [
		'label'  => 'تیتر و محتوا',
		'weight' => 20,
		'codes'  => [ 'no_h1', 'multi_h1', 'dup_h1', 'img_alt', 'thin' ],
	],
	'cat-structure' => [
		'label'  => 'ساختار و نقشه سایت',
		'weight' => 15,
		'codes'  => [ 'orphan', 'island', 'low_inlinks', 'deep', 'no_canonical', 'not_in_sitemap', 'noindex' ],
	],
	'cat-speed'     => [
		'label'  => 'سرعت',
		'weight' => 15,
		'codes'  => [ 'slow' ],
	],
];

/**
 * کدهای ایراد یک کلید فیلتر (گروه چیپ یا بخش نمره)؛ خالی = کلید ناشناخته.
 *
 * @return list<string>
 */
function hodima_seo_advisor_score_filter_codes( string $key ): array {
	return HODIMA_SEO_ADVISOR_CRAWL_GROUPS[ $key ] ?? HODIMA_SEO_ADVISOR_SCORE_CATEGORIES[ $key ]['codes'] ?? [];
}

/**
 * برچسب و سطح رنگ یک نمره: ۹۰+ عالی، ۷۵+ خوب (سبز)، ۵۰+ نیاز به کار (کهربایی)، کمتر ضعیف (قرمز).
 *
 * @return array{label: string, level: string}
 */
function hodima_seo_advisor_score_grade( int $score ): array {
	return match ( true ) {
		$score >= 90 => [ 'label' => 'عالی', 'level' => 'ok' ],
		$score >= 75 => [ 'label' => 'خوب', 'level' => 'ok' ],
		$score >= 50 => [ 'label' => 'نیاز به کار', 'level' => 'warn' ],
		default      => [ 'label' => 'ضعیف', 'level' => 'bad' ],
	};
}

/**
 * نمره سلامت از ایرادهای خزش.
 *
 * @param list<array<string, mixed>> $issues  خروجی hodima_seo_advisor_crawl_issues() (بدون فیلتر علامت‌ها: نمره وضعیت واقعی سایت است)
 * @param int                        $crawled شمار صفحه‌های خزش‌شده
 * @return array{score: int, categories: array<string, array{label: string, score: int, pages: int}>, top: list<array{code: string, pages: int, penalty: float}>}|null null = خزشی نیست
 */
function hodima_seo_advisor_score( array $issues, int $crawled ): ?array {

	if ( $crawled <= 0 ) {
		return null;
	}

	$pages = []; // کد ← صفحه‌های دارای ایراد
	foreach ( $issues as $row ) {
		foreach ( array_unique( array_column( (array) $row['issues'], 'code' ) ) as $code ) {
			$pages[ (string) $code ][ (string) $row['path'] ] = true;
		}
	}

	$categories = [];
	$penalties  = [];
	$total      = 0.0;
	$weights    = 0;

	foreach ( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES as $key => $category ) {
		$cut      = 0.0;
		$affected = [];

		foreach ( $category['codes'] as $code ) {
			$count = count( $pages[ $code ] ?? [] );

			if ( 0 === $count ) {
				continue;
			}

			$level               = hodima_seo_advisor_crawl_issue_info( $code )['level'];
			$penalty             = ( HODIMA_SEO_ADVISOR_SCORE_WEIGHTS[ $level ] ?? 0.3 ) * $count / $crawled * 100;
			$cut                += $penalty;
			$penalties[ $code ]  = [ 'code' => $code, 'pages' => $count, 'penalty' => $penalty ];
			$affected           += $pages[ $code ];
		}

		$score             = max( 0, (int) round( 100 - $cut ) );
		$categories[ $key ] = [ 'label' => $category['label'], 'score' => $score, 'pages' => count( $affected ) ];
		$total             += $score * $category['weight'];
		$weights           += $category['weight'];
	}

	usort( $penalties, static fn( array $a, array $b ): int => $b['penalty'] <=> $a['penalty'] );

	return [
		'score'      => (int) round( $total / max( 1, $weights ) ),
		'categories' => $categories,
		'top'        => array_slice( $penalties, 0, 3 ),
	];
}

/**
 * تاریخچه نمره دورها (قدیمی‌ترین اول).
 *
 * @return list<array{time: int, score: int}>
 */
function hodima_seo_advisor_score_history(): array {

	$saved = get_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, [] );
	$out   = [];

	foreach ( is_array( $saved ) ? $saved : [] as $entry ) {
		if ( is_array( $entry ) && isset( $entry['time'], $entry['score'] ) ) {
			$out[] = [ 'time' => (int) $entry['time'], 'score' => (int) $entry['score'] ];
		}
	}

	return $out;
}

/** نمره فعلی را به‌عنوان نتیجه دور خزش ثبت می‌کند (پایان هر دور خزنده). */
function hodima_seo_advisor_score_record(): void {

	global $wpdb;

	$crawled = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', hodima_seo_advisor_table( 'crawl_pages' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$result  = hodima_seo_advisor_score( hodima_seo_advisor_crawl_issues(), $crawled );

	if ( null === $result ) {
		return;
	}

	$history   = hodima_seo_advisor_score_history();
	$history[] = [ 'time' => time(), 'score' => $result['score'] ];

	update_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, array_slice( $history, -12 ), false );
}
