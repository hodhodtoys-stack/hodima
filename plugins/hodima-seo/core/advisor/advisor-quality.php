<?php
/**
 * ماژول «دستار سئو» — کیفیت داده (SEO 3.2.1)
 * Path: core/advisor/advisor-quality.php
 *
 * مدیر عددهای دستار را درست فرض می‌کند، ولی بخشی از آن‌ها ممکن است کامل نباشد (سقف داده گوگل، سقف تحلیل، سقف
 * خزش، نقشه سایتی که همه‌اش خزیده نشده). این فایل سه جنبه را از داده‌ای که همین حالا هم ذخیره می‌شود می‌سنجد و برای
 * هر کدام یکی از سه حالت می‌دهد: `ok` (کامل)، `warn` (ناقص)، `unknown` (نامعلوم؛ مشکل شمرده نمی‌شود). چیزی ذخیره
 * یا عوض نمی‌کند. نمایش: advisor-quality-view.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** کمترین نسبت آدرس‌های نقشه سایت که خزش باید دیده باشد تا «کامل» بماند. */
const HODIMA_SEO_ADVISOR_QUALITY_CRAWL_RATIO = 0.9;

/**
 * کیفیت داده.
 *
 * @return array{items: list<array{key: string, label: string, level: string, value: string, note: string}>, issues: int, level: string}
 */
function hodima_seo_advisor_quality(): array {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$status = hodima_seo_advisor_status();
	$caps   = hodima_seo_advisor_caps();
	$totals = hodima_seo_advisor_totals();
	$items  = [];

	// ۱) داده گوگل
	if ( $status['fetched'] <= 0 ) {
		$items[] = [ 'key' => 'google', 'label' => 'داده گوگل', 'level' => 'unknown', 'value' => 'هنوز دریافتی نشده', 'note' => '' ];
	} else {
		$until = '' !== $status['end'] && false !== strtotime( $status['end'] ) ? (int) strtotime( $status['end'] ) : $status['fetched'];
		$notes = array_filter( [
			'' !== $status['error'] ? 'آخرین دریافت ناموفق بود؛ عددها از دریافت قبلی‌اند. کلید و دسترسی را در تب «تنظیمات» ببینید.' : '',
			$caps['fetch'] ? sprintf( 'داده سرچ کنسول از سقف %s ردیف گزارش بیشتر بود و بقیه نیامد؛ آدرس‌ها یا کلمه‌های کم‌نمایش در دستار نیستند. بازه را کوتاه‌تر (۲۸ روز) کنید.', $n( HODIMA_SEO_ADVISOR_FETCH_PAGES * 25000 ) ) : '',
		] );
		$items[] = [
			'key'   => 'google',
			'label' => 'داده گوگل',
			'level' => $notes ? 'warn' : 'ok',
			'value' => sprintf( 'تا %1$s پیش، %2$s روز', human_time_diff( $until ), $n( max( 1, $status['days'] ) ) ),
			'note'  => implode( ' ', $notes ),
		];
	}

	// ۲) پوشش تحلیل (چند آدرس پیشنهاد گرفتند)
	if ( $totals['pages'] <= 0 ) {
		$items[] = [ 'key' => 'analysis', 'label' => 'پوشش تحلیل', 'level' => 'unknown', 'value' => 'داده‌ای نیست', 'note' => '' ];
	} else {
		$notes = array_filter( [
			$caps['analyze'] ? sprintf( 'فقط %s آدرس پرنمایش‌تر برای پیشنهاد بررسی می‌شوند و بقیه پیشنهاد ندارند. «حداقل نمایش» را در تب «تنظیمات» بالاتر ببرید تا آدرس‌های کم‌نمایش کنار بروند.', $n( HODIMA_SEO_ADVISOR_ANALYZE_LIMIT ) ) : '',
			$caps['classify'] > 0 ? sprintf( '%1$s آدرس کم‌نمایش (بعد از %2$s آدرس برتر) نوع و عنوان نگرفتند؛ پیشنهاد متنی و تحلیل دوباره هنگام ذخیره برایشان کار نمی‌کند.', $n( $caps['classify'] ), $n( HODIMA_SEO_ADVISOR_CLASSIFY_LIMIT ) ) : '',
		] );
		$items[] = [
			'key'   => 'analysis',
			'label' => 'پوشش تحلیل',
			'level' => $notes ? 'warn' : 'ok',
			'value' => $caps['analyze'] ? sprintf( '%1$s از %2$s آدرس', $n( min( HODIMA_SEO_ADVISOR_ANALYZE_LIMIT, $totals['pages'] ) ), $n( $totals['pages'] ) ) : sprintf( 'همه %s آدرس', $n( $totals['pages'] ) ),
			'note'  => implode( ' ', $notes ),
		];
	}

	// ۳) پوشش خزش (در برابر نقشه سایت)
	$crawl   = hodima_seo_advisor_crawl_status();
	$capped  = hodima_seo_advisor_crawler_state()['capped'];
	$sitemap = hodima_seo_advisor_sitemap_paths();

	if ( $crawl['imported'] <= 0 || $crawl['pages'] <= 0 ) {
		$items[] = [ 'key' => 'crawl', 'label' => 'پوشش خزش', 'level' => 'unknown', 'value' => 'هنوز خزشی نشده', 'note' => '' ];
	} elseif ( $capped ) {
		$items[] = [ 'key' => 'crawl', 'label' => 'پوشش خزش', 'level' => 'warn', 'value' => sprintf( '%s صفحه (سقف)', $n( $crawl['pages'] ) ), 'note' => sprintf( 'سایت بیش از %s صفحه دارد و بقیه خزیده نشد؛ ایرادهای فنی آن صفحه‌ها دیده نمی‌شود.', $n( HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES ) ) ];
	} elseif ( ! $sitemap ) {
		$items[] = [ 'key' => 'crawl', 'label' => 'پوشش خزش', 'level' => 'unknown', 'value' => sprintf( '%s صفحه؛ نقشه سایت خوانده نشده', $n( $crawl['pages'] ) ), 'note' => '' ];
	} else {
		$covered = count( array_intersect_key( $sitemap, hodima_seo_advisor_crawl_map() ) );
		$short   = $covered < count( $sitemap ) * HODIMA_SEO_ADVISOR_QUALITY_CRAWL_RATIO;
		$items[] = [
			'key'   => 'crawl',
			'label' => 'پوشش خزش',
			'level' => $short ? 'warn' : 'ok',
			'value' => sprintf( '%1$s از %2$s آدرس نقشه سایت', $n( $covered ), $n( count( $sitemap ) ) ),
			'note'  => $short ? sprintf( 'فقط %1$s از %2$s آدرس نقشه سایت خزیده شده؛ ایرادهای فنی بقیه دیده نمی‌شود. «خزش همین حالا» را در تب «ایرادهای فنی» بزنید و اگر نشد، سلامت کارهای خودکار را در تب «تنظیمات» ببینید.', $n( $covered ), $n( count( $sitemap ) ) ) : '',
		];
	}

	$levels = array_column( $items, 'level' );
	$issues = count( array_filter( $levels, static fn( string $level ): bool => 'warn' === $level ) );

	return [
		'items'  => $items,
		'issues' => $issues,
		'level'  => $issues > 0 ? 'warn' : ( count( array_filter( $levels, static fn( string $level ): bool => 'ok' === $level ) ) > 0 ? 'ok' : 'unknown' ),
	];
}
