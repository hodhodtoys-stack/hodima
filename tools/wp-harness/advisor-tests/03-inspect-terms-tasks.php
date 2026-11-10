<?php
/**
 * بخش 3 از آزمون‌های دستار سئو (tools/wp-harness/advisor-tests.php آن را به ترتیب و در همان دامنه سراسری می‌خواند):
 * بررسی ایندکس (URL Inspection)، رقابت و کلمه‌ها، کارها.
 * تنهایی اجرا نمی‌شود: متغیرها و توابع کمکی (hodima_ad، $cap، …) از فایل اصلی و بخش‌های قبلی می‌آیند.
 */

declare(strict_types=1);

echo "=== بررسی ایندکس (URL Inspection)\n";
$ins = static fn( string $coverage, string $verdict = 'NEUTRAL', string $indexing = '', string $google = '', string $user = '' ): array => hodima_seo_advisor_inspect_parse( [ 'indexStatusResult' => [ 'verdict' => $verdict, 'coverageState' => $coverage, 'indexingState' => $indexing, 'lastCrawlTime' => '2026-10-01T10:00:00Z', 'googleCanonical' => $google, 'userCanonical' => $user, 'pageFetchState' => 'SUCCESSFUL', 'robotsTxtState' => 'ALLOWED', 'crawledAs' => 'MOBILE' ] ] );
$rec = $ins( 'Submitted and indexed', 'PASS' );
hodima_ad( 'پاسخ گوگل ← رکورد فشرده', [ $rec['verdict'], $rec['coverage'], $rec['last_crawl'], $rec['crawled_as'], $rec['checked'] > 0 ], [ 'PASS', 'Submitted and indexed', '2026-10-01T10:00:00Z', 'MOBILE', true ] );
hodima_ad( 'وضعیت‌ها از متن coverage', array_map( static fn( array $r ): string => hodima_seo_advisor_inspect_state( $r ), [
	$ins( 'Submitted and indexed', 'PASS' ), $ins( 'Indexed, not submitted in sitemap', 'PASS' ), $ins( 'Crawled - currently not indexed' ), $ins( 'Discovered - currently not indexed' ),
	$ins( 'Duplicate, Google chose different canonical than user' ), $ins( 'Alternate page with proper canonical tag' ), $ins( 'Excluded by ‘noindex’ tag' ), $ins( 'Blocked by robots.txt' ),
	$ins( 'Soft 404' ), $ins( 'Not found (404)' ), $ins( 'Server error (5xx)' ), $ins( 'Page with redirect' ), $ins( 'URL is unknown to Google' ), $ins( 'Something new' ), $ins( '', 'NEUTRAL', 'BLOCKED_BY_ROBOTS_TXT' ),
] ), [ 'indexed', 'indexed', 'crawled_not_indexed', 'discovered_not_indexed', 'duplicate', 'duplicate', 'noindex', 'robots', 'soft404', 'not_found', 'server_error', 'redirect', 'unknown_to_google', 'other', 'robots' ] );
hodima_ad( 'اختلاف canonical: فرق مسیر (بعد از یکسان‌سازی) ← بله؛ www/اسلش ← نه؛ ناشناخته ← نه', [ hodima_seo_advisor_inspect_canonical_mismatch( $ins( 'x', 'PASS', '', 'https://' . $host . '/a/', 'https://' . $host . '/b/' ) ), hodima_seo_advisor_inspect_canonical_mismatch( $ins( 'x', 'PASS', '', 'https://www.' . $host . '/a', 'https://' . $host . '/a/' ) ), hodima_seo_advisor_inspect_canonical_mismatch( $ins( 'x', 'PASS' ) ) ], [ true, false, false ] );

// صف: بدون نمایش‌ها اول، بعد دارای پیشنهاد، بعد پرنمایش؛ تازه‌بررسی‌شده و صفحه‌بندی کنار
hodima_seo_advisor_store(
	[ '/q/a/' => [ 'clicks' => 5, 'impressions' => 9000, 'position' => 7.0 ], '/q/b/' => [ 'clicks' => 5, 'impressions' => 3000, 'position' => 7.0 ], '/q/c/' => [ 'clicks' => 5, 'impressions' => 8000, 'position' => 7.0 ], '/q/page/2/' => [ 'clicks' => 1, 'impressions' => 99999, 'position' => 7.0 ] ],
	[],
	[]
);
$wpdb->update( hodima_seo_advisor_table( 'pages' ), [ 'potential' => 500, 'advice_count' => 1 ], [ 'path' => '/q/b/' ] );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'unseen' => [ [ 'path' => '/q/unseen/' ] ] ], false );
hodima_ad( 'ترتیب صف: بدون نمایش ← دارای پیشنهاد (بیشترین بالقوه) ← پرنمایش‌ترین؛ صفحه‌بندی نه', hodima_seo_advisor_inspect_queue( 10 ), [ '/q/unseen/', '/q/b/', '/q/a/', '/q/c/' ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/q/unseen/' => [ 'checked' => time() - DAY_IN_SECONDS ] + $rec, '/q/a/' => [ 'checked' => time() - 20 * DAY_IN_SECONDS ] + $rec ], false );
hodima_ad( 'تازه‌بررسی‌شده (۱ روز) دوباره نمی‌آید؛ قدیمی (۲۰ روز) می‌آید؛ سقف دسته', [ hodima_seo_advisor_inspect_queue( 10 ), hodima_seo_advisor_inspect_queue( 2 ), hodima_seo_advisor_inspect_pending() ], [ [ '/q/b/', '/q/a/', '/q/c/' ], [ '/q/b/', '/q/a/' ], 3 ] );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );

// همگام‌سازی با پاسخ ساختگی
remove_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
update_option( 'hodima_discover_sc_key', $key_json, false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 4, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
$in_calls = [];
$GLOBALS['inspect_quota_after'] = 99;
add_filter(
	'pre_http_request',
	$in_filter = static function ( mixed $pre, array $args, string $url ) use ( &$in_calls ): mixed {
		if ( ! str_contains( $url, '/urlInspection/index:inspect' ) ) {
			return $pre;
		}
		$body       = json_decode( (string) $args['body'], true );
		$in_calls[] = $body;
		if ( count( $in_calls ) > $GLOBALS['inspect_quota_after'] ) {
			return [ 'response' => [ 'code' => 429 ], 'body' => (string) wp_json_encode( [ 'error' => [ 'message' => 'quota' ] ] ) ];
		}
		$coverage = str_contains( $body['inspectionUrl'], '/q/b/' ) ? 'Crawled - currently not indexed' : 'Submitted and indexed';
		return [ 'response' => [ 'code' => 200 ], 'body' => (string) wp_json_encode( [ 'inspectionResult' => [ 'indexStatusResult' => [ 'verdict' => 'Submitted and indexed' === $coverage ? 'PASS' : 'NEUTRAL', 'coverageState' => $coverage, 'lastCrawlTime' => '2026-10-02T08:00:00Z', 'googleCanonical' => $body['inspectionUrl'], 'userCanonical' => $body['inspectionUrl'] ] ] ] ) ];
	},
	20,
	3
);
$batch = hodima_seo_advisor_inspect_batch( 3 );
$stored_in = hodima_seo_advisor_inspections();
hodima_ad( 'دسته: ۳ آدرس پرسیده، property درست، رکوردها ذخیره، وضعیت‌ها درست', [ array_intersect_key( $batch, [ 'checked' => 0, 'error' => 0, 'quota' => 0 ] ), count( $in_calls ), $in_calls[0]['siteUrl'], array_keys( $stored_in ), hodima_seo_advisor_inspect_state( $stored_in['/q/b/'] ) ], [ [ 'checked' => 3, 'error' => '', 'quota' => false ], 3, 'sc-domain:' . $host, [ '/q/unseen/', '/q/b/', '/q/a/' ], 'crawled_not_indexed' ] );
hodima_ad( 'inspectionUrl آدرس کامل سایت است', str_starts_with( $in_calls[0]['inspectionUrl'], 'https://' . $host ), true );
$in_calls = [];
$GLOBALS['inspect_quota_after'] = 1;
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
$batch2 = hodima_seo_advisor_inspect_batch( 5 );
hodima_ad( 'سهمیه (۴۲۹) ← فورا می‌ایستد، آنچه پرسیده شده ذخیره می‌شود', [ $batch2['checked'], $batch2['quota'], '' !== $batch2['error'], count( $in_calls ), count( hodima_seo_advisor_inspections() ) ], [ 1, true, true, 2, 1 ] );

// «۰ آدرس بررسی شد» (SEO 3.8.1): تا ۳.۸.۰ خطای گوگل برای هر آدرس (مثلا ۴۰۳ «آدرس جزو property نیست») بی‌صدا رد می‌شد و پیام
// موفق «۰ آدرس بررسی شد» می‌آمد؛ صف خالی هم همان پیام را می‌داد. حالا خطا با کد و پیام گوگل، و صف خالی با پیام جدا.
$GLOBALS['inspect_fail'] = 'all';
add_filter( 'pre_http_request', $in_fail = static function ( mixed $pre, array $args, string $url ): mixed { // شمار درخواست‌ها را $in_filter (اولویت ۲۰) نگه می‌دارد
	if ( ! str_contains( $url, '/urlInspection/index:inspect' ) ) {
		return $pre;
	}
	$body = json_decode( (string) $args['body'], true );
	if ( 'all' === $GLOBALS['inspect_fail'] || str_contains( (string) $body['inspectionUrl'], '/q/c/' ) ) {
		return [ 'response' => [ 'code' => 403 ], 'body' => (string) wp_json_encode( [ 'error' => [ 'message' => 'You do not own this site, or the inspected URL is not part of this property.' ] ] ) ];
	}
	return $pre;
}, 30, 3 ); // بعد از پاسخ ساختگی سالم (اولویت ۲۰) تا جایش را بگیرد
$in_calls = [];
$GLOBALS['inspect_quota_after'] = 99;
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
$f_all = hodima_seo_advisor_inspect_batch( 10 );
$f_job = get_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION, [] )['inspect'] ?? [];
hodima_ad( 'همه آدرس‌ها ۴۰۳: بعد از ۳ خطای پی‌درپی می‌ایستد؛ خطا (نه «۰ آدرس بررسی شد») با کد، پیام گوگل و راهنمای property', [ $f_all['checked'], $f_all['failed'], count( $in_calls ), str_contains( $f_all['error'], 'هیچ آدرسی بررسی نشد' ), str_contains( $f_all['error'], '۴۰۳' ) || str_contains( $f_all['error'], '403' ), str_contains( $f_all['error'], 'not part of this property' ), str_contains( $f_all['error'], 'property انتخاب‌شده' ), str_contains( (string) ( $f_job['text'] ?? '' ), 'هیچ آدرسی بررسی نشد' ), ! empty( $f_job['error'] ) ], [ 0, 3, 3, true, true, true, true, true, true ] );
hodima_ad( 'همه ۴۰۳ (مشکل از دسترسی property است): خطای هر آدرس ثبت نمی‌شود تا کارت‌های ساختگی «بررسی نشد» نیاید (SEO 3.9.4)', hodima_seo_advisor_inspect_failures(), [] );
[ $f_type, $f_html ] = hodima_seo_advisor_do_inspect();
hodima_ad( 'دکمه «اجرا همین حالا» با همه ۴۰۳: پیام خطا، نه موفق', [ $f_type, str_contains( $f_html, '۰ آدرس بررسی شد' ) || str_contains( $f_html, '0 آدرس بررسی شد' ) ], [ 'error', false ] );
$GLOBALS['inspect_fail'] = 'one';
$in_calls = [];
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
$f_part = hodima_seo_advisor_inspect_batch( 10 );
hodima_ad( 'یک آدرس ۴۰۳ و بقیه سالم: بقیه بررسی و ذخیره می‌شوند، خطا نیست ولی پیام شمار خطا را می‌گوید', [ $f_part['checked'], $f_part['failed'], $f_part['error'], str_contains( hodima_seo_advisor_inspect_message( $f_part ), 'گوگل برای ۱ آدرس خطا داد' ) || str_contains( hodima_seo_advisor_inspect_message( $f_part ), 'گوگل برای 1 آدرس خطا داد' ) ], [ 3, 1, '', true ] );
$f_fails = hodima_seo_advisor_inspect_failures();
hodima_ad( 'یک آدرس ۴۰۳ و بقیه سالم: خطای همان آدرس با متن گوگل ذخیره می‌شود (پیش‌تر بی‌صدا رد می‌شد)', [ array_keys( $f_fails ), str_contains( $f_fails['/q/c/']['text'] ?? '', 'You do not own this site' ), ( $f_fails['/q/c/']['at'] ?? 0 ) > time() - 60 ], [ [ '/q/c/' ], true, true ] );
[ $f_type ] = hodima_seo_advisor_do_inspect(); // صف حالا خالی است (همه تازه بررسی شده‌اند یا /q/c/ دوباره ۴۰۳)
remove_filter( 'pre_http_request', $in_fail, 30 );
$f_empty = hodima_seo_advisor_inspect_batch( 10 );
$f_empty_q = hodima_seo_advisor_inspect_queue( 10 );
hodima_ad( 'صف خالی (همه در ۱۴ روز اخیر بررسی شده): پیام «آدرسی برای پرسیدن نبود»، نه «۰ آدرس بررسی شد»', [ $f_empty_q, $f_empty['queued'] > 0 || str_contains( hodima_seo_advisor_inspect_message( $f_empty ), 'آدرسی برای پرسیدن نبود' ) ], [ [], true ] );
hodima_ad( 'بعد از پاسخ درست گوگل برای همان آدرس، خطای ذخیره‌شده‌اش پاک می‌شود', hodima_seo_advisor_inspect_failures(), [] );
hodima_ad( 'پیام صف خالی', str_starts_with( hodima_seo_advisor_inspect_message( [ 'checked' => 0, 'error' => '', 'quota' => false, 'failed' => 0, 'queued' => 0, 'fail' => '' ] ), 'آدرسی برای پرسیدن نبود: همه آدرس‌ها در ' ), true );
unset( $GLOBALS['inspect_fail'] );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );

// SEO 3.9.4: «حافظه خطا» و «noindex قدیمی» (صفحه‌ای که گوگل نسخه ۱۴ ماه پیشش را با noindex نگه داشته بود و الان ایندکس‌پذیر است؛ دستار فقط
// می‌گفت «noindex را بردارید» و برای آدرسی که پرسشش خطا داده بود هیچ نشانه‌ای نبود)
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_FAIL_OPTION );
$st_crawler = get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, false );
$st_health  = get_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, false );
$st_ct      = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->query( "DELETE FROM {$st_ct} WHERE path LIKE '/st-%'" );
foreach ( [ '/st-stale/' => 1, '/st-off/' => 0, '/st-real/' => 1 ] as $st_path => $st_ok ) {
	$GLOBALS['wpdb']->insert( $st_ct, [ 'path' => $st_path, 'status' => 200, 'indexable' => $st_ok ] );
}
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, array_merge( (array) $st_crawler, [ 'finished' => (int) strtotime( '2026-11-05T10:00:00Z' ) ] ), false );
$st_noindex = $ins( "Excluded by 'noindex' tag" ); // آخرین خزش گوگل: ۱ اکتبر
$st_recs    = [
	'/st-stale/' => $st_noindex,                                                       // گوگل پیش از خزش ما و ما ایندکس‌پذیر می‌بینیم ← قدیمی
	'/st-real/'  => array_merge( $st_noindex, [ 'last_crawl' => '2026-11-06T10:00:00Z' ] ), // گوگل بعد از ما هم noindex دیده ← قدیمی نیست
	'/st-off/'   => $st_noindex,                                                       // خودمان هم noindex می‌بینیم
	'/st-none/'  => $st_noindex,                                                       // هنوز خزیده نشده
	'/st-idx/'   => $ins( 'Submitted and indexed', 'PASS' ),
];
hodima_ad( 'noindex قدیمی: فقط وقتی گوگل پیش از خزش ما خزیده و خزنده ما صفحه را ایندکس‌پذیر می‌بیند؛ بقیه حالت‌ها همان وضعیت قبلی', hodima_seo_advisor_inspect_effective_states( $st_recs ), [ '/st-stale/' => 'noindex_stale', '/st-real/' => 'noindex', '/st-off/' => 'noindex', '/st-none/' => 'noindex', '/st-idx/' => 'indexed' ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, array_merge( (array) $st_crawler, [ 'finished' => 0 ] ), false );
hodima_ad( 'خزنده هیچ‌وقت کامل نشده: noindex همان noindex می‌ماند', hodima_seo_advisor_inspect_effective_states( [ '/st-stale/' => $st_noindex ] )['/st-stale/'], 'noindex' );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, array_merge( (array) $st_crawler, [ 'finished' => (int) strtotime( '2026-11-05T10:00:00Z' ) ] ), false );

update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $st_recs + [ '/st-unseen-idx/' => $ins( 'Submitted and indexed', 'PASS' ) ], false );
update_option( HODIMA_SEO_ADVISOR_INSPECT_FAIL_OPTION, [ '/st-fail/' => [ 'at' => time(), 'text' => 'کد 403؛ پیام گوگل: «x»' ] ], false );
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'router' => true, 'dead' => [], 'total' => 10, 'seen' => 5, 'fresh' => 0, 'unseen' => [
	[ 'id' => 1, 'path' => '/st-unseen/', 'title' => 'پد', 'type' => 'product', 'days' => 285 ],
	[ 'id' => 2, 'path' => '/st-unseen-idx/', 'title' => 'پد دو', 'type' => 'product', 'days' => 100 ],
	[ 'id' => 3, 'path' => '/st-fail/', 'title' => 'پد سه', 'type' => 'product', 'days' => 90 ],
	[ 'id' => 4, 'path' => '/st-idx/', 'title' => 'پد چهار', 'type' => 'product', 'days' => 80 ],
] ], false );
$st_over = array_column( hodima_seo_advisor_overview_build(), 'items', 'path' );
$st_item = static function ( string $path, string $label ) use ( $st_over ): array {
	foreach ( $st_over[ $path ] ?? [] as $item ) {
		if ( $label === $item['label'] ) {
			return [ $item['level'], $item['guide']['seen'], implode( ' ', $item['guide']['steps'] ), $item['guide']['need'] ];
		}
	}
	return [];
};
$st_stale = $st_item( '/st-stale/', 'ایندکس: noindex قدیمی در گوگل' );
hodima_ad( 'کارها: «noindex قدیمی» کهربایی با تاریخ آخرین خزش گوگل و گام «Request indexing» (نه «noindex را بردارید»)', [ $st_stale[0] ?? '', str_contains( $st_stale[1] ?? '', 'آخرین خزش گوگل' ), str_contains( $st_stale[2] ?? '', 'Request indexing' ), str_contains( $st_stale[2] ?? '', 'ایندکس / نوایندکس' ), $st_stale[3] ?? '' ], [ 'warn', true, true, false, 'rec' ] );
hodima_ad( 'کارها: noindex واقعی (گوگل بعد از ما هم دیده) با راهنمای قبلی می‌ماند و «قدیمی» نمی‌شود', [ array_keys( array_filter( [ '/st-real/' => $st_item( '/st-real/', 'ایندکس: noindex' ), '/st-off/' => $st_item( '/st-off/', 'ایندکس: noindex' ), '/st-stale/' => $st_item( '/st-stale/', 'ایندکس: noindex' ) ] ) ) ], [ [ '/st-real/', '/st-off/' ] ] );
$st_fail = $st_item( '/st-fail/', 'ایندکس: بررسی ایندکس خطا داد' );
hodima_ad( 'کارها: آدرس خطادار کارت اطلاع (نه کهربایی) با متن خطای گوگل دارد', [ $st_fail[0] ?? '', str_contains( $st_fail[1] ?? '', 'کد 403' ), $st_fail[3] ?? '' ], [ 'info', true, 'opt' ] );
hodima_ad( 'کارها: «بدون نمایش» که هنوز پرسیده نشده می‌گوید پرسیده نشده؛ ایندکس‌شده می‌گوید ایندکس است؛ خطادار و مشکل‌دار جمله اضافه ندارند', [ str_contains( $st_item( '/st-unseen/', 'بدون نمایش در گوگل' )[1] ?? '', 'هنوز از گوگل پرسیده نشده' ), str_contains( $st_item( '/st-unseen-idx/', 'بدون نمایش در گوگل' )[1] ?? '', 'ایندکس کرده' ), str_contains( $st_item( '/st-fail/', 'بدون نمایش در گوگل' )[1] ?? '', 'پرسیده نشده' ), str_contains( $st_item( '/st-idx/', 'بدون نمایش در گوگل' )[1] ?? '', 'ایندکس کرده' ) ], [ true, true, false, true ] );
hodima_ad( 'جمله وضعیت ایندکس: مشکل‌دار و خطادار ← خالی (کارت جدا دارند)', [ hodima_seo_advisor_inspect_note( '/st-stale/' ), hodima_seo_advisor_inspect_note( '/st-fail/' ) ], [ '', '' ] );
hodima_ad( '«انجام دادم» روی کارت خطادار کار می‌سازد (بدون رکورد هم) تا آدرس اول صف برود', array_column( hodima_seo_advisor_mark_items( 'indexing', '/st-fail/', [], '' ), 'rule' ), [ 'index_problem' ] );
hodima_ad( '«انجام دادم» روی noindex قدیمی عنوان «noindex قدیمی» می‌گیرد', array_column( hodima_seo_advisor_mark_items( 'indexing', '/st-stale/', [], '' ), 'title' ), [ 'ایندکس: noindex قدیمی در گوگل' ] );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_FAIL_OPTION );
add_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
hodima_ad( 'بررسی ایندکس خاموش (فیلتر): جمله «هنوز پرسیده نشده» نمی‌آید', hodima_seo_advisor_inspect_note( '/st-unseen/' ), '' );
remove_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
$GLOBALS['wpdb']->query( "DELETE FROM {$st_ct} WHERE path LIKE '/st-%'" );
false === $st_crawler ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, $st_crawler, false );
false === $st_health ? delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION ) : update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, $st_health, false );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );

// ذخیره تدریجی (SEO 3.4.6): قطع شدن وسط دسته نتیجه ۱۰ آدرس پرسیده‌شده را از دست نمی‌دهد
$ip_t = hodima_seo_advisor_table( 'pages' );
foreach ( range( 1, 14 ) as $ip_i ) {
	$GLOBALS['wpdb']->insert( $ip_t, [ 'path' => "/ip-{$ip_i}/", 'impressions' => 9000 - $ip_i, 'kind' => 'page', 'advice' => '' ] );
}
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
$GLOBALS['inspect_quota_after'] = 99;
$in_calls = [];
add_filter( 'pre_http_request', $ip_die = static function ( mixed $pre, array $args, string $url ) use ( &$in_calls ): mixed {
	if ( str_contains( $url, '/urlInspection/index:inspect' ) && count( $in_calls ) >= 11 ) { // ۱۱امین پرسش: قطع ناگهانی (مثل مهلت PHP)
		throw new RuntimeException( 'cut' );
	}
	return $pre;
}, 10, 3 );
try {
	hodima_seo_advisor_inspect_run( 'sc-domain:' . $host, 14, [ 'checked' => 0, 'error' => '', 'quota' => false ] );
} catch ( RuntimeException ) {
	// قطع‌شدن عمدی
}
remove_filter( 'pre_http_request', $ip_die, 10 );
hodima_ad( 'قطع وسط دسته: ۱۰ نتیجه پرسش‌شده (یک دسته ۱۰تایی) ذخیره مانده، نه صفر', count( hodima_seo_advisor_inspections() ), 10 );
$GLOBALS['wpdb']->query( "DELETE FROM {$ip_t} WHERE path LIKE '/ip-%'" );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );

// نما
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'indexing' ];
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/q/b/' => $ins( 'Crawled - currently not indexed' ), '/q/a/' => $ins( 'Submitted and indexed', 'PASS' ), '/q/c/' => $ins( 'Duplicate, Google chose different canonical than user', 'NEUTRAL', '', 'https://' . $host . '/q/a/', 'https://' . $host . '/q/c/' ) ], false );
wp_set_current_user( 1 );
ob_start();
hodima_seo_advisor_render_admin();
$in_html = (string) ob_get_clean();
hodima_ad( 'تب ایندکس: ۴ کارت آمار، دکمه با nonce، مشکل‌دارها (خزیده ولی ایندکس نشد و canonical دیگر) و دستورشان', [ substr_count( $in_html, 'class="hds-stat"' ), str_contains( $in_html, 'name="action" value="hodima_advisor_inspect_now"' ), str_contains( $in_html, 'خزیده شد ولی ایندکس نشد' ), str_contains( $in_html, 'تکراری / canonical دیگر' ), str_contains( $in_html, '/q/a/' ), str_contains( $in_html, 'درخواست ایندکس' ), str_contains( $in_html, 'برگشت به فهرست کارها' ) ], [ 4, true, true, true, true, true, true ] );
$_GET = [];
remove_filter( 'pre_http_request', $in_filter, 20 );
update_option( HODIMA_SEO_ADVISOR_INSPECT_FAIL_OPTION, [ '/x/' => [ 'at' => 1, 'text' => 't' ] ], false );
hodima_ad( 'پاک کردن داده‌های مشاور نتیجه بررسی ایندکس و خطاهای ثبت‌شده را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), hodima_seo_advisor_inspections(), hodima_seo_advisor_inspect_failures() ], [ true, [], [] ] );
add_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
delete_option( 'hodima_discover_sc_key' );

echo "=== کلید کلمه (term_key): املای متفاوت یک کلمه بین آدرس‌ها یکی است (بخش ۲۷۹)\n";
hodima_ad( 'term_key: ی/ي عربی، اعراب، کشیده، ارقام و حروف بزرگ یکی می‌شوند', [ hodima_seo_advisor_term_key( 'کيف مو ۲' ) === hodima_seo_advisor_term_key( 'کیف مو 2' ), hodima_seo_advisor_term_key( 'کِیـف' ) === hodima_seo_advisor_term_key( 'کیف' ), hodima_seo_advisor_term_key( 'Hair CLIP' ), hodima_seo_advisor_term_key( '  ' ) ], [ true, true, 'hair clip', '' ] );
$arabic = "کيف مو"; // ي عربی
$farsi  = 'کیف مو';
hodima_seo_advisor_store(
	[
		'/k/a/' => [ 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0 ],
		'/k/b/' => [ 'clicks' => 10, 'impressions' => 2000, 'position' => 9.0 ],
		'/k/c/' => [ 'clicks' => 5, 'impressions' => 800, 'position' => 12.0 ],
	],
	[],
	[
		'/k/a/' => [ $arabic => [ 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0 ] ],
		'/k/b/' => [ $farsi => [ 'clicks' => 10, 'impressions' => 2000, 'position' => 9.0 ] ],
		'/k/c/' => [ 'Hair Clip' => [ 'clicks' => 5, 'impressions' => 800, 'position' => 12.0 ] ],
	]
);
$keyed = $wpdb->get_col( $wpdb->prepare( 'SELECT term_key FROM %i ORDER BY path, term_key', hodima_seo_advisor_table( 'queries' ) ) );
hodima_ad( 'ذخیره: term_key هر ردیف پر و یکسان‌شده است', $keyed, [ hodima_seo_advisor_term_key( $arabic ), hodima_seo_advisor_term_key( $farsi ), 'hair clip' ] );
$kcomp = hodima_seo_advisor_competition();
hodima_ad( 'رقابت: دو املای ي/ی دو آدرس یک گروه است (قبلا اصلا دیده نمی‌شد)', [ $kcomp['total'], $kcomp['rows'][0]['count'] ?? 0, array_column( $kcomp['rows'][0]['pages'] ?? [], 'path' ) ], [ 1, 2, [ '/k/a/', '/k/b/' ] ] );
hodima_ad( 'رقابت: برچسب گروه املای پرنمایش‌تر است', $kcomp['rows'][0]['term'] ?? '', $arabic );
$kq = [ [ 'term' => $arabic, 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0 ] ];
$kc = hodima_seo_advisor_competitors( '/k/a/', $kq );
hodima_ad( 'رقبای یک آدرس: با term_key وصل می‌شود و کلید خروجی term_key است', [ array_keys( $kc ), array_column( $kc[ hodima_seo_advisor_term_key( $arabic ) ] ?? [], 'path' ) ], [ [ hodima_seo_advisor_term_key( $arabic ) ], [ '/k/b/' ] ] );
$ks = hodima_seo_advisor_suggestions( [ 'path' => '/k/a/', 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0, 'prev_clicks' => 0 ], $kq, null, $kc );
hodima_ad( 'قاعده cannibal با املای متفاوت فعال می‌شود', in_array( 'cannibal', array_column( $ks, 'rule' ), true ), true );
hodima_ad( 'صفحه‌های رقیب برای گام «صفحه مرجع» با املای دیگر هم پیدا می‌شود', array_column( hodima_seo_advisor_term_pages( $farsi ), 'path' ), [ '/k/a/', '/k/b/' ] );
$ksplit = hodima_seo_advisor_brand_split();
hodima_ad( 'سهم برندی: املاهای یک کلمه یک ردیف می‌شود', [ $ksplit['other']['terms'], $ksplit['other']['impressions'] ], [ 2, 5800 ] );
// مهاجرت ۱٫۳٫۰ ← ۱٫۴٫۰: ردیف بدون term_key موقتا همان کلمه خام می‌شود
$wpdb->query( $wpdb->prepare( "UPDATE %i SET term_key = ''", hodima_seo_advisor_table( 'queries' ) ) );
update_option( HODIMA_SEO_ADVISOR_VERSION_OPTION, '1.3.0', false );
hodima_seo_advisor_ensure_tables();
hodima_ad( 'مهاجرت: ردیف‌های قدیمی term_key خالی نمی‌مانند و نسخه بالا می‌رود', [ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE term_key = ''", hodima_seo_advisor_table( 'queries' ) ) ), get_option( HODIMA_SEO_ADVISOR_VERSION_OPTION ) ], [ 0, HODIMA_SEO_ADVISOR_DB_VERSION ] );

echo "=== نوبت‌ها: تحلیل زمان‌دار و ادامه با کرون (بخش ۲۸۰)\n";
$pz = [ 'clicks' => 0, 'impressions' => 0, 'position' => 0.0 ];
$p_cur = [
	'/st/a/' => [ 'clicks' => 496, 'impressions' => 36492, 'position' => 5.81 ],
	'/st/b/' => [ 'clicks' => 5, 'impressions' => 500, 'position' => 6.0 ],
	'/st/d/' => [ 'clicks' => 600, 'impressions' => 6000, 'position' => 6.0 ],
];
$p_prv = [ '/st/d/' => [ 'clicks' => 1000, 'impressions' => 6000, 'position' => 6.0 ] ];
hodima_seo_advisor_store( $p_cur, $p_prv, [] );
delete_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION );
hodima_ad( 'نوبت با وقت تمام‌شده: هیچ آدرسی تحلیل نمی‌شود و صف می‌ماند', [ hodima_seo_advisor_analyze_batch( 0.0 ), (array) ( get_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION )['queue'] ?? [] ), hodima_seo_advisor_advised_count() ], [ false, [ '/st/a/', '/st/d/' ], 0 ] );
hodima_ad( 'نوبت بعد از همان صف ادامه می‌دهد و تمام می‌شود', [ hodima_seo_advisor_analyze_batch( INF ), get_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, 'none' ), hodima_seo_advisor_advised_count() ], [ true, 'none', 2 ] );
// ذخیره دوباره داده: پیشنهاد قبلی تا تحلیل تازه می‌ماند (فهرست خالی نمی‌شود)
hodima_seo_advisor_store( $p_cur, $p_prv, [] );
hodima_ad( 'ذخیره تازه داده پیشنهاد قبلی را نگه می‌دارد', [ hodima_seo_advisor_advised_count(), hodima_seo_advisor_page( '/st/a/' )['advice'][0]['rule'] ?? '' ], [ 2, 'low_ctr' ] );
// آدرسی که دیگر قابل‌بررسی نیست (نمایش کم شد) در نوبت اول پیشنهادش پاک می‌شود
hodima_seo_advisor_store( [ '/st/a/' => $p_cur['/st/a/'], '/st/d/' => [ 'clicks' => 1, 'impressions' => 10, 'position' => 6.0 ] ], [], [] );
hodima_ad( 'پیشنهاد آدرس غیرقابل‌بررسی پاک می‌شود، بقیه می‌ماند', [ hodima_seo_advisor_analyze_batch( INF ), hodima_seo_advisor_advised_count(), hodima_seo_advisor_page( '/st/d/' )['advice_count'] ], [ true, 1, 0 ] );
hodima_ad( 'analyze_all همزمان هنوز همان نتیجه را می‌دهد و صف قدیمی را دور می‌ریزد', [ update_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, [ 'queue' => [ '/nowhere/' ] ], false ), hodima_seo_advisor_analyze_all(), get_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, 'none' ) ], [ true, 1, 'none' ] );

// صف نوبت‌ها
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
$st_state = [ 'stages' => [ 'analyze', 'health', 'bogus' ], 'property' => 'sc-domain:' . $host, 'range' => hodima_seo_advisor_ranges( 90 ), 'days' => 90 ];
update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, $st_state, false );
hodima_seo_advisor_pipeline_run( 0.0 );
hodima_ad( 'بودجه تمام‌شده: نوبت‌ها می‌مانند و رویداد ادامه زمان‌بندی می‌شود', [ hodima_seo_advisor_pipeline_pending(), false !== wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ true, true ] );
// قفل دست دیگری است ← رویداد ادامه نمی‌دهد و دوباره زمان‌بندی می‌کند
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
hodima_seo_advisor_lock();
do_action( HODIMA_SEO_ADVISOR_STAGE_CRON );
$late = wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON );
hodima_seo_advisor_unlock();
hodima_ad( 'رویداد با قفل مشغول: چیزی اجرا نمی‌شود و حدود ۵ دقیقه بعد دوباره امتحان می‌شود', [ count( (array) get_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION )['stages'] ), is_int( $late ) && $late >= time() + 290 ], [ 3, true ] );
// نوبت خراب (استثنا) بقیه را متوقف نمی‌کند
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
add_filter( 'hodima_seo_advisor_health_enabled', $boom = static function (): bool { throw new RuntimeException( 'boom' ); } );
do_action( HODIMA_SEO_ADVISOR_STAGE_CRON );
remove_filter( 'hodima_seo_advisor_health_enabled', $boom );
hodima_ad( 'استثنای یک نوبت: ثبت خطا، بقیه نوبت‌ها اجرا و صف خالی می‌شود', [ hodima_seo_advisor_pipeline_pending(), str_contains( hodima_seo_advisor_status()['error'], 'health' ), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ), hodima_seo_advisor_status()['advised'] ], [ false, true, false, 1 ] );
// دریافت کامل: همه نوبت‌ها همان درخواست تمام می‌شوند (سایت کوچک)
hodima_ad( 'پاک کردن داده‌های دستار صف و رویداد نوبت‌ها را هم برمی‌دارد', [ update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, $st_state, false ), wp_schedule_single_event( time() + 60, HODIMA_SEO_ADVISOR_STAGE_CRON ), hodima_seo_advisor_clear_data(), get_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, 'none' ), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ true, true, true, 'none', false ] );

echo "=== افت کلیک: علت و مقایسه با کل سایت؛ اثر خالص در برابر روند سایت (بخش ۲۸۱)\n";
$drow = static fn( array $o = [] ): array => $o + [ 'path' => '/dc/', 'clicks' => 600, 'impressions' => 6000, 'position' => 6.0, 'prev_clicks' => 1000, 'prev_impressions' => 6000, 'prev_position' => 6.0 ];
hodima_ad( 'علت افت: رتبه ۳ پله بدتر ← position', hodima_seo_advisor_decline_cause( $drow( [ 'position' => 9.0, 'impressions' => 3000 ] ), null )['cause'], 'position' );
hodima_ad( 'علت افت: نمایش نصف و رتبه ثابت ← impressions', hodima_seo_advisor_decline_cause( $drow( [ 'impressions' => 3000, 'clicks' => 300 ] ), null )['cause'], 'impressions' );
hodima_ad( 'علت افت: نمایش و رتبه ثابت، نرخ کلیک کم ← ctr', hodima_seo_advisor_decline_cause( $drow(), null )['cause'], 'ctr' );
hodima_ad( 'علت افت: تغییر کوچک ← mixed', hodima_seo_advisor_decline_cause( $drow( [ 'impressions' => 5500, 'position' => 7.0, 'clicks' => 660, 'prev_clicks' => 700 ] ), null )['cause'], 'mixed' );
hodima_ad( 'علت افت: رتبه بدتر بر نمایش کمتر مقدم است', hodima_seo_advisor_decline_cause( $drow( [ 'impressions' => 2000, 'position' => 12.0 ] ), null )['cause'], 'position' );
hodima_ad( 'جمله علت: کل سایت هم افت کرده ← توجه؛ رشد کرده ← مخصوص همین صفحه؛ ثابت ← بدون توجه', [ str_contains( hodima_seo_advisor_decline_cause( $drow(), [ 'clicks_ratio' => 0.5, 'impressions_ratio' => null ] )['text'], 'کل سایت هم' ), str_contains( hodima_seo_advisor_decline_cause( $drow(), [ 'clicks_ratio' => 1.3, 'impressions_ratio' => null ] )['text'], 'مخصوص همین صفحه' ), str_contains( hodima_seo_advisor_decline_cause( $drow(), [ 'clicks_ratio' => 0.95, 'impressions_ratio' => null ] )['text'], 'توجه' ) ], [ true, true, false ] );
$decl = static fn( array $row, ?array $site ): array => array_values( array_filter( hodima_seo_advisor_suggestions( $row, [], null, [], $site ), static fn( array $x ): bool => 'decline' === $x['rule'] ) );
hodima_ad( 'افت بدون اطلاعات سایت مثل قبل: کلیک از دست‌رفته ۴۰۰', array_column( $decl( $drow(), null ), 'potential' ), [ 400 ] );
hodima_ad( 'کل سایت ۴۰٪ افت کرده و صفحه ۴۰٪ ← همراه سایت است، پیشنهادی نیست', $decl( $drow(), [ 'clicks_ratio' => 0.6, 'impressions_ratio' => null ] ), [] );
hodima_ad( 'کل سایت ۴۰٪ افت کرده و صفحه ۷۰٪ ← فقط افت اضافه (۵۰۰ − ۳۰۰ = ۲۰۰)', array_column( $decl( $drow( [ 'clicks' => 300 ] ), [ 'clicks_ratio' => 0.5, 'impressions_ratio' => null ] ), 'potential' ), [ 200 ] );
hodima_ad( 'سایت رشد کرده ← انتظار بالا نمی‌رود: همان ۴۰۰', array_column( $decl( $drow(), [ 'clicks_ratio' => 1.4, 'impressions_ratio' => null ] ), 'potential' ), [ 400 ] );
hodima_ad( 'پیشنهاد افت علت را در خود دارد', $decl( $drow(), null )[0]['cause'] ?? '', 'ctr' );

// جمع کل سایت از جدول
hodima_seo_advisor_store(
	[ '/sc/a/' => [ 'clicks' => 600, 'impressions' => 6000, 'position' => 6.0 ], '/sc/b/' => [ 'clicks' => 400, 'impressions' => 4000, 'position' => 6.0 ], '/sc/c/' => [ 'clicks' => 100, 'impressions' => 2000, 'position' => 6.0 ] ],
	[ '/sc/a/' => [ 'clicks' => 1000, 'impressions' => 6000, 'position' => 6.0 ], '/sc/b/' => [ 'clicks' => 800, 'impressions' => 4000, 'position' => 6.0 ], '/sc/c/' => [ 'clicks' => 700, 'impressions' => 2000, 'position' => 6.0 ] ],
	[]
);
$sc = hodima_seo_advisor_site_change();
hodima_ad( 'جمع کل سایت بعد از ذخیره: کلیک ۱۱۰۰ از ۲٬۵۰۰ ← ۰٫۴۴؛ نمایش ۱۲٬۰۰۰ از ۱۲٬۰۰۰ ← ۱', [ round( (float) $sc['clicks_ratio'], 2 ), $sc['impressions_ratio'] ], [ 0.44, 1.0 ] );
$an = static fn( string $path ): array => array_column( hodima_seo_advisor_analyze( $path )['suggestions'] ?? [], 'rule' );
hodima_ad( 'تحلیل واقعی: a و b همراه سایت‌اند (بدون افت)؛ c افت اضافه دارد', [ in_array( 'decline', $an( '/sc/a/' ), true ), in_array( 'decline', $an( '/sc/b/' ), true ), hodima_seo_advisor_analyze( '/sc/c/' )['potential'] ], [ false, false, 208 ] );
hodima_ad( 'بازه قبل کم‌داده ← نسبت سایت null', ( static function (): array { hodima_seo_advisor_store( [ '/sc/x/' => [ 'clicks' => 5, 'impressions' => 100, 'position' => 6.0 ] ], [ '/sc/x/' => [ 'clicks' => 9, 'impressions' => 100, 'position' => 6.0 ] ], [] ); return hodima_seo_advisor_site_change(); } )(), [ 'clicks_ratio' => null, 'impressions_ratio' => null ] );
delete_option( HODIMA_SEO_ADVISOR_SITE_OPTION );
hodima_ad( 'گزینه جمع سایت نبود ← خودش از جدول ساخته می‌شود', array_key_exists( 'clicks_ratio', hodima_seo_advisor_site_change() ) && is_array( get_option( HODIMA_SEO_ADVISOR_SITE_OPTION ) ), true );

// اثر خالص در برابر روند سایت
$wk = [ '2025-01-05', '2025-01-12', '2025-01-19', '2025-01-26', '2025-02-02' ];
$put = static function ( array $page, array $other ) use ( $wk ): void {
	foreach ( $wk as $i => $end ) {
		hodima_seo_advisor_history_store_week( $end, [ '/eff/' => [ 'clicks' => $page[ $i ], 'impressions' => 500, 'position' => 6.0 ], '/other/' => [ 'clicks' => $other[ $i ], 'impressions' => 5000, 'position' => 6.0 ] ] );
	}
};
$mk = static fn( array $page ): array => array_map( static fn( string $end, int $c ): array => [ 'week' => $end, 'clicks' => $c, 'impressions' => 500, 'position' => 6.0 ], $wk, $page );
$put( [ 10, 10, 15, 20, 20 ], [ 990, 990, 995, 990, 990 ] );
$ba1 = hodima_seo_advisor_before_after( $mk( [ 10, 10, 15, 20, 20 ] ), '2025-01-14' );
hodima_ad( 'قبل/بعد حالا هفته‌های دو طرف را هم برمی‌گرداند (هفته وسط ویرایش بیرون است)', [ $ba1['before_weeks'], $ba1['after_weeks'] ], [ [ '2025-01-05', '2025-01-12' ], [ '2025-01-26', '2025-02-02' ] ] );
$vs1 = hodima_seo_advisor_effect_vs_site( $ba1 );
hodima_ad( 'صفحه دو برابر شده و سایت تقریبا ثابت (۱٪) ← above (۹۹ امتیاز بالاتر)', [ $vs1['page'], $vs1['site'], $vs1['net'], $vs1['verdict'] ], [ 100.0, 1.0, 99.0, 'above' ] );
$put( [ 10, 10, 15, 20, 20 ], [ 990, 990, 1500, 1980, 1980 ] );
$vs2 = hodima_seo_advisor_effect_vs_site( $ba1 );
hodima_ad( 'سایت هم دو برابر شده ← هم‌راستا با روند (same)', [ $vs2['site'], $vs2['net'], $vs2['verdict'] ], [ 100.0, 0.0, 'same' ] );
$put( [ 10, 10, 10, 10, 10 ], [ 990, 990, 1000, 1490, 1490 ] );
$vs3 = hodima_seo_advisor_effect_vs_site( hodima_seo_advisor_before_after( $mk( [ 10, 10, 10, 10, 10 ] ), '2025-01-14' ) );
hodima_ad( 'صفحه ثابت و سایت ۵۰٪ بالا ← below', [ $vs3['page'], $vs3['site'], $vs3['verdict'] ], [ 0.0, 50.0, 'below' ] );
hodima_ad( 'متن مقایسه سه حالت', [ str_contains( hodima_seo_advisor_effect_vs_site_text( $vs1 ), 'بهتر از روند کل سایت' ), str_contains( hodima_seo_advisor_effect_vs_site_text( $vs2 ), 'هم‌راستا' ), str_contains( hodima_seo_advisor_effect_vs_site_text( $vs3 ), 'ضعیف‌تر از روند کل سایت' ), hodima_seo_advisor_effect_vs_site_text( null ) ], [ true, true, true, '' ] );
hodima_ad( 'هفته‌های عکس‌نشده سایت ← مقایسه ممکن نیست (null)', hodima_seo_advisor_effect_vs_site( [ 'before' => [ 'clicks' => 5.0 ], 'after' => [ 'clicks' => 9.0 ], 'before_weeks' => [ '2020-03-01' ], 'after_weeks' => [ '2020-03-08' ] ] ), null );
$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE week_end < '2025-03-01'", hodima_seo_advisor_table( 'history' ) ) );

echo "=== نرخ کلیک معمول از داده خود سایت (بخش ۲۸۲)\n";
delete_option( HODIMA_SEO_ADVISOR_CTR_OPTION );
hodima_seo_advisor_store( [], [], [] );
$empty_learn = hodima_seo_advisor_ctr_learn();
hodima_ad( 'بدون داده: جدول آموخته‌شده همان جدول عمومی است و سهم سایت صفر', [ $empty_learn['table'], hodima_seo_advisor_ctr_learned_share() ], [ [ 2 => 25.0, 3 => 14.0, 5 => 9.0, 7 => 5.5, 10 => 3.5, 20 => 1.5 ], 0.0 ] );
hodima_ad( 'بدون داده: نرخ معمول مثل قبل (رتبه بالاتر از ۱۰ تا ۲۰ و بعدش ۱٫۵)', array_map( 'hodima_seo_advisor_expected_ctr', [ 1.5, 10.0, 15.0, 40.0 ] ), [ 25.0, 3.5, 1.5, 1.5 ] );
add_filter( 'hodima_seo_advisor_brand_terms', $ctr_brand = static fn(): array => [ 'brandx' ] );
$row_q = static fn( int $clicks, int $impressions, float $position ): array => [ 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
hodima_seo_advisor_store(
	[ '/ct/a/' => $row_q( 1, 100, 5.0 ) ],
	[],
	[
		'/ct/a/' => [
			'کلمه رتبه یک' => $row_q( 3000, 30000, 1.5 ),       // ۱۰٪ در سطل ≤۲ (عمومی ۲۵٪)
			'brandx shop' => $row_q( 50000, 100000, 1.2 ),      // برندی: کنار می‌رود
			'کلمه رتبه سه' => $row_q( 2000, 10000, 2.5 ),       // ۲۰٪ در سطل ≤۳ (نصف وزن)
			'کلمه رتبه چهار' => $row_q( 100, 1000, 4.0 ),       // زیر ۲٬۰۰۰ نمایش ← عمومی
			'کلمه رتبه شش' => $row_q( 2000, 40000, 6.0 ),       // ۵٪ در سطل ≤۷
			'کلمه رتبه پانزده' => $row_q( 20, 4000, 15.0 ),     // ۰٫۵٪ در سطل ≤۲۰ (وزن ۰٫۱۶)
		],
	]
);
$learn = hodima_seo_advisor_ctr_learn();
hodima_ad( 'جدول آموخته‌شده: ترکیب وزنی، برند حذف، رتبه بهتر هرگز کمتر از بدتر نمی‌شود', $learn['table'], [ 2 => 13.0, 3 => 13.0, 5 => 9.0, 7 => 5.1, 10 => 3.5, 20 => 1.34 ] );
hodima_ad( 'جزئیات سطل‌ها: نمایش و کلیک بدون برند، و وزن', [ $learn['buckets'][2]['impressions'], $learn['buckets'][2]['clicks'], $learn['buckets'][2]['weight'], $learn['buckets'][5]['weight'], $learn['impressions'] ], [ 30000, 3000, 0.8, 0.0, 85000 ] );
hodima_ad( 'نرخ معمول حالا از جدول سایت می‌آید (بعد از رتبه ۲۰ همان ۱٫۵ ثابت)', array_map( 'hodima_seo_advisor_expected_ctr', [ 1.5, 2.5, 4.0, 6.0, 9.0, 15.0, 40.0 ] ), [ 13.0, 13.0, 9.0, 5.1, 3.5, 1.34, 1.5 ] ); // بعد از رتبه ۲۰ همان ۱٫۵ ثابت قبلی
hodima_ad( 'سهم داده سایت بین ۰ و ۱ و بالاتر از صفر', [ hodima_seo_advisor_ctr_learned_share() > 0.3, hodima_seo_advisor_ctr_learned_share() < 1.0 ], [ true, true ] );
add_filter( 'hodima_seo_advisor_ctr_table', $ctr_flt = static fn(): array => [ 10 => 2.0 ] );
hodima_ad( 'فیلتر جدول نرخ کلیک بر جدول آموخته‌شده مقدم است', hodima_seo_advisor_expected_ctr( 5.0 ), 2.0 );
remove_filter( 'hodima_seo_advisor_ctr_table', $ctr_flt );
$low = hodima_seo_advisor_suggestions( [ 'path' => '/ct/z/', 'clicks' => 10, 'impressions' => 5000, 'position' => 2.0, 'prev_clicks' => 0 ], [], null );
hodima_ad( 'پیشنهاد نرخ کلیک پایین: با جدول آموخته‌شده (۱۳٪ در رتبه ۲) و یادداشت منبع', [ $low[0]['rule'] ?? '', $low[0]['potential'] ?? 0, str_contains( $low[0]['detail'] ?? '', 'از داده خود سایت' ) ], [ 'low_ctr', 640, true ] );
hodima_ad( 'پیشنهاد نرخ کلیک: بدون جدول آموخته‌شده ۲۵٪ و بدون یادداشت', ( static function () use ( $row_q ): array { delete_option( HODIMA_SEO_ADVISOR_CTR_OPTION ); $r = hodima_seo_advisor_suggestions( [ 'path' => '/ct/z/', 'clicks' => 10, 'impressions' => 5000, 'position' => 2.0, 'prev_clicks' => 0 ], [], null ); return [ $r[0]['potential'] ?? 0, str_contains( $r[0]['detail'] ?? '', 'از داده خود سایت' ) ]; } )(), [ 1240, false ] );
// نوبت ctr در صف دریافت است و پیش از analyze می‌آید؛ پاک کردن داده‌های دستار جدول را هم برمی‌دارد
hodima_ad( 'نوبت ctr اولین نوبت است و health (آدرس‌های مرده) پیش از analyze', array_slice( HODIMA_SEO_ADVISOR_STAGES, 0, 3 ), [ 'ctr', 'health', 'analyze' ] );
hodima_seo_advisor_ctr_learn();
hodima_ad( 'پاک کردن داده‌های دستار جدول نرخ کلیک آموخته‌شده را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), get_option( HODIMA_SEO_ADVISOR_CTR_OPTION, 'none' ) ], [ true, 'none' ] );
remove_filter( 'hodima_seo_advisor_brand_terms', $ctr_brand );

echo "=== ردیف بالا (به‌روزرسانی + تنظیمات) در همه تب‌ها و صفحه‌بندی مدرن (بخش ۲۸۳)\n";
$cap = static function ( callable $fn ): string { ob_start(); $fn(); return (string) ob_get_clean(); };
update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $own_key, false );
$tb_data = [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $tb_data, false );
hodima_seo_advisor_store( [ '/tb/a/' => [ 'clicks' => 5, 'impressions' => 5000, 'position' => 6.0 ] ], [], [] );
wp_set_current_user( 1 );
$_GET = [];
$bar = $cap( static fn() => hodima_seo_advisor_render_toolbar( 'competition' ) );
hodima_ad( 'ردیف «گرفتن داده تازه»: یک دکمه با تب برگشت و راهنمای «یک کلیک برای همه»', [ str_contains( $bar, 'name="action" value="hodima_advisor_refresh"' ), str_contains( $bar, 'name="tab" value="competition"' ), str_contains( $bar, 'گرفتن داده تازه' ), str_contains( $bar, 'یک کلیک برای همه بخش‌ها' ) ], [ true, true, true, true ] );
foreach ( [ 'opportunities', 'competition', 'terms', 'health', 'indexing' ] as $t ) { // «crawl» از 3.4.2 تب اصلی است و ردیف به‌روزرسانی ندارد
	$_GET   = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => $t ];
	$html_t = $cap( static fn() => hodima_seo_advisor_render_admin() );
	$bar_at = strpos( $html_t, 'value="hodima_advisor_refresh"' );
	hodima_ad( "تب {$t}: ردیف به‌روزرسانی دقیقا یک بار و زیر ردیف تب‌ها؛ پنل تنظیمات نیست", [ substr_count( $html_t, 'value="hodima_advisor_refresh"' ), false !== $bar_at && $bar_at > (int) strpos( $html_t, 'hodima-advisor__tabrow' ), str_contains( $html_t, 'id="hodima-advisor-settings"' ), str_contains( $html_t, 'name="tab" value="' . ( 'opportunities' === $t ? 'opportunities' : $t ) . '"' ) ], [ 1, true, false, true ] );
}
$_GET     = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'settings' ];
$page_set = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'تب «تنظیمات»: فقط باکس تنظیمات (باز)، تب فعال، بدون ردیف به‌روزرسانی، بدون کارت کارهای خودکار و فهرست (SEO 3.3.9)', [ substr_count( $page_set, 'id="hodima-advisor-settings"' ), 1 === preg_match( '/id="hodima-advisor-settings"[^>]*\shidden/', $page_set ), str_contains( $page_set, 'tab=settings" aria-current="page"' ), str_contains( $page_set, 'value="hodima_advisor_refresh"' ), str_contains( $page_set, 'hodima-advisor__item' ) ], [ 1, false, true, false, false ] );
$_GET     = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'terms', 'settings' => '1' ];
hodima_ad( 'پیوند قدیمی settings=1 ← تب «تنظیمات»', str_contains( $cap( static fn() => hodima_seo_advisor_render_admin() ), 'tab=settings" aria-current="page"' ), true );
hodima_ad( 'دکمه بازشونده قدیمی «تنظیمات» دیگر نیست', str_contains( $page_set . $html_t, 'data-hdsa-settings-toggle' ), false );
$_GET = [];

$pg = static fn( int $cur, int $tot ): string => $cap( static fn() => hodima_seo_ui_pager( $cur, $tot, static fn( int $i ): string => '/p/?paged=' . $i ) );
hodima_ad( 'صفحه‌بندی: یک صفحه = هیچ؛ صفحه اول بدون «قبلی»، با «بعدی»', [ $pg( 1, 1 ), str_contains( $pg( 1, 5 ), 'قبلی' ), str_contains( $pg( 1, 5 ), 'rel="next"' ), substr_count( $pg( 1, 5 ), 'hds-pager__page' ) ], [ '', false, true, 3 ] );
hodima_ad( 'صفحه‌بندی: وسط ۱۰ صفحه: اول، آخر، همسایه‌ها و «…»', [ substr_count( $pg( 5, 10 ), '<a class="hds-pager__page"' ), substr_count( $pg( 5, 10 ), 'hds-pager__gap' ), str_contains( $pg( 5, 10 ), 'rel="prev"' ), str_contains( $pg( 5, 10 ), 'rel="next"' ) ], [ 4, 2, true, true ] );
hodima_ad( 'صفحه‌بندی: صفحه جاری aria-current و بدون لینک؛ صفحه آخر بدون «بعدی»', [ str_contains( $pg( 3, 3 ), 'aria-current="page"' ), str_contains( $pg( 3, 3 ), 'rel="next"' ), str_contains( $pg( 3, 3 ), '?paged=3' ) ], [ true, false, false ] );
$_GET = [];
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION ); // کلید آزمایشی این بخش نماند (اجرای بعدی آزمون را خراب می‌کرد)

echo "=== سلامت خزش: خواندن فایل‌های CSV نمونه و ایرادها\n";
$crawl_dir = sys_get_temp_dir() . '/hodima-crawl-test-' . getmypid();
@mkdir( $crawl_dir );
$crawl_filter = static fn(): string => $crawl_dir;
add_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter );
$csv = static function ( string $name, array $rows, bool $bom = true ) use ( $crawl_dir ): void {
	$h = fopen( "{$crawl_dir}/{$name}", 'wb' );
	if ( $bom ) {
		fwrite( $h, "\xEF\xBB\xBF" );
	}
	foreach ( $rows as $r ) {
		fputcsv( $h, $r, ',', '"', '' );
	}
	fclose( $h );
	touch( "{$crawl_dir}/{$name}", time() - 3600 );
};
$cols = [ 'Address', 'Status Code', 'Indexability', 'Indexability Status', 'Title 1', 'Title 1 Length', 'Meta Description 1 Length', 'H1-1', 'Response Time', 'Unique Inlinks', 'Crawl Depth', 'Link Score', 'Redirect URL' ];
$long = str_repeat( 'عنوان خوب ', 5 );
$csv( 'internal_html.csv', [
	$cols,
	[ $u( '/' ), '200', 'Indexable', '', $long . 'z', (string) ( mb_strlen( $long ) + 1 ), '120', 'خانه', '0.4', '0', '0', '100', '' ],
	[ $u( '/short-title/' ), '200', 'Indexable', '', 'کوتاه', '5', '0', 'الف', '0.3', '10', '1', '5', '' ],
	[ $u( '/dup-a/' ), '200', 'Indexable', '', $long, (string) mb_strlen( $long ), '130', 'ب', '0.3', '8', '1', '5', '' ],
	[ $u( '/dup-b/' ), '200', 'Indexable', '', $long, (string) mb_strlen( $long ), '130', 'پ', '2.5', '9', '1', '5', '' ],
	[ $u( '/few-links/' ), '200', 'Indexable', '', $long . ' ایکس', (string) ( mb_strlen( $long ) + 5 ), '130', 'ت', '0.3', '2', '2', '1', '' ],
	[ $u( '/orphan/' ), '200', 'Indexable', '', $long . ' وای', (string) ( mb_strlen( $long ) + 4 ), '130', 'ث', '0.3', '0', '3', '1', '' ],
	[ $u( '/moved/' ), '301', 'Non-Indexable', 'Redirected', '', '0', '0', '', '0.2', '4', '2', '0', $u( '/dup-a/' ) ],
	[ $u( '/moved-unused/' ), '301', 'Non-Indexable', 'Redirected', '', '0', '0', '', '0.2', '0', '2', '0', '' ],
	[ $u( '/few-links/?utm_source=x' ), '200', 'Indexable', '', 'نسخه پارامتردار', '15', '0', '', '0.3', '1', '2', '0', '' ],
	[ 'https://other.example/x/', '200', 'Indexable', '', 'بیرونی', '6', '0', '', '0.3', '1', '2', '0', '' ],
] );
$csv( 'all_inlinks.csv', [
	[ 'Type', 'Source', 'Destination', 'Anchor', 'Follow', 'Link Position' ],
	[ 'Hyperlink', $u( '/dup-a/' ), $u( '/few-links/' ), 'لینک اول', 'true', 'Content' ],
	[ 'Hyperlink', $u( '/dup-a/' ), $u( '/few-links/' ), 'لینک اول', 'true', 'Content' ],
	[ 'Hyperlink', $u( '/dup-b/' ), $u( '/few-links/' ), 'لینک دوم', 'true', 'Navigation' ],
	[ 'Hyperlink', $u( '/few-links/' ), 'https://other.example/', 'بیرونی', 'true', 'Content' ],
	[ 'Image', $u( '/dup-a/' ), $u( '/few-links/' ), '', 'true', 'Content' ],
	[ 'Hyperlink', $u( '/dup-b/' ), $u( '/few-links/?x=1' ), 'پارامتردار', 'false', 'Content' ],
] );
hodima_seo_advisor_store( [ '/dup-b/' => [ 'clicks' => 3, 'impressions' => 9000, 'position' => 7.0 ], '/short-title/' => [ 'clicks' => 1, 'impressions' => 2000, 'position' => 9.0 ] ], [], [] );

touch( "{$crawl_dir}/all_inlinks.csv", time() );
touch( "{$crawl_dir}/all_inlinks.csv", time() - 3600 );

$imp = hodima_seo_advisor_crawl_import();
hodima_ad( 'خواندن موفق: ۸ صفحه (آدرس پارامتردار و دامنه دیگر نیامد) و ۳ لینک یکتا', is_wp_error( $imp ) ? $imp->get_error_message() : [ $imp['pages'], $imp['links'] ], [ 8, 3 ] );
add_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
remove_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
$cs = hodima_seo_advisor_crawl_status();
hodima_ad( 'وضعیت: زمان، شمار صفحه و لینک', [ $cs['imported'] > 0, $cs['pages'], $cs['links'] ], [ true, 8, 3 ] );

$cw = [];
foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
	$cw[ $row['path'] ] = $row;
}
$codes = static fn( string $path ): array => array_column( $cw[ $path ]['issues'] ?? [], 'code' );
hodima_ad( 'صفحه اصلی بدون لینک ورودی «یتیم» نیست و ایرادی ندارد', isset( $cw['/'] ), false );
hodima_ad( 'عنوان کوتاه و بدون متا', $codes( '/short-title/' ), [ 'title_short', 'no_meta' ] );
hodima_ad( 'عنوان تکراری بین دو صفحه + کند', [ $codes( '/dup-a/' ), $codes( '/dup-b/' ) ], [ [ 'dup_title' ], [ 'dup_title', 'slow' ] ] );
hodima_ad( 'لینک ورودی کم با شمار لینک متنی (۱ لینک یکتا از متن؛ تکرار و Navigation و تصویر نشمرده)', [ $codes( '/few-links/' ), $cw['/few-links/']['text_links'] ], [ [ 'low_inlinks' ], 1 ] );
hodima_ad( 'صفحه بدون لینک ورودی یتیم است', $codes( '/orphan/' ), [ 'orphan' ] );
hodima_ad( 'ریدایرکتِ دارای لینک ورودی می‌آید؛ ریدایرکتِ بی‌لینک و بی‌نمایش نه', [ $codes( '/moved/' ), isset( $cw['/moved-unused/'] ) ], [ [ 'redirect' ], false ] );
$order = array_keys( $cw );
hodima_ad( 'مرتب بر اساس نمایش گوگل (۹٬۰۰۰ سپس ۲٬۰۰۰ سپس بی‌نمایش)', array_slice( $order, 0, 2 ), [ '/dup-b/', '/short-title/' ] );
$sm = hodima_seo_advisor_crawl_summary( hodima_seo_advisor_crawl_issues() );
hodima_ad( 'آمار: خزش‌شده، دارای ایراد، پرنمایش، لینک کم', [ $sm['crawled'], $sm['with_issues'], $sm['big'], $sm['low_links'] ], [ 8, 6, 2, 2 ] );
hodima_ad( 'گروه‌های فیلتر شمرده می‌شوند', [ $sm['groups']['meta'], $sm['groups']['title'], $sm['groups']['dup'], $sm['groups']['slow'], $sm['groups']['redirect'], $sm['groups']['orphan'] ], [ 1, 1, 2, 1, 1, 1 ] );

// فایل نمونه با BOM + عنوان ستون‌ها و همه مقدارها در گیومه (باگ ۲.۸.۱)
copy( "{$crawl_dir}/internal_html.csv", "{$crawl_dir}/backup.tmp" );
file_put_contents( "{$crawl_dir}/internal_html.csv", "\xEF\xBB\xBF" . '"Address","Status Code","Indexability","Title 1","Title 1 Length","Meta Description 1 Length","Unique Inlinks"' . "\r\n" . '"' . $u( '/quoted/' ) . '","200","Indexable","' . $long . '","' . mb_strlen( $long ) . '","130","5"' . "\r\n" );
touch( "{$crawl_dir}/internal_html.csv", time() - 3600 );
$quoted = hodima_seo_advisor_crawl_import();
hodima_ad( 'فایل نمونه با BOM و گیومه خوانده می‌شود', is_wp_error( $quoted ) ? $quoted->get_error_message() : $quoted['pages'], 1 );
rename( "{$crawl_dir}/backup.tmp", "{$crawl_dir}/internal_html.csv" );
touch( "{$crawl_dir}/internal_html.csv", time() - 3600 );
hodima_seo_advisor_crawl_import();

// فایل اشتباه: داده قبلی می‌ماند
$csv( 'internal_html.csv', [ [ 'Foo', 'Bar' ], [ 'a', 'b' ] ] );
$bad = hodima_seo_advisor_crawl_import();
hodima_ad( 'فایل با ستون اشتباه رد می‌شود و ستون‌های ناقص نام برده می‌شوند', [ is_wp_error( $bad ), is_wp_error( $bad ) && str_contains( $bad->get_error_message(), 'address' ) ], [ true, true ] );
hodima_ad( 'فایل خراب (ورود آزمایشی): داده قبلی سالم می‌ماند', hodima_seo_advisor_crawl_status()['pages'], 8 );
unlink( "{$crawl_dir}/internal_html.csv" );
$none = hodima_seo_advisor_crawl_import();
hodima_ad( 'نبودن internal_html.csv خطا می‌دهد و داده می‌ماند', [ is_wp_error( $none ), hodima_seo_advisor_crawl_status()['pages'] ], [ true, 8 ] );

// نمایش «کارها» با نوار سلامت (SEO 3.5.8؛ قبلا تب «سلامت سایت» با جدول ایرادها)
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
$crawl_html = $cap( static fn() => hodima_seo_advisor_render_overview() );
hodima_ad( '«کارها» با نوار سلامت: نمره با حلقه، پنج بخش با پیوند فیلتر، دکمه «خزش همین حالا» که به همین تب برمی‌گردد، بدون جدول ایرادها و کارت‌های آمار و «سه کار اول»، بدون کارت تنظیم خزنده', [ 1 === substr_count( $crawl_html, 'hodima-advisor__sc-gauge ' ), 5 === substr_count( $crawl_html, 'class="hodima-advisor__sc-cat"' ), str_contains( $crawl_html, 'value="hodima_advisor_crawler_now"' ), str_contains( $crawl_html, 'name="tab" value="overview"' ), str_contains( $crawl_html, 'hodima-advisor__crawl-table' ), str_contains( $crawl_html, 'hds-stat__label' ), str_contains( $crawl_html, 'سه کار اول' ), str_contains( $crawl_html, 'value="hodima_advisor_crawler_settings"' ) ], [ true, true, true, true, false, false, false, false ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'src' => 'crawl' ];
$crawl_src = $cap( static fn() => hodima_seo_advisor_render_overview() );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
hodima_ad( '«کارها»: «نوع ایراد فنی» ردیف دوم چیپ است نه منو (SEO 3.6.0)؛ فقط وقتی «ایراد فنی» انتخاب است؛ بی‌برچسب دیداری و بی‌«همه نوع‌ها»', [ str_contains( $crawl_src, '<nav class="hodima-advisor__chips hodima-advisor__chips--sm" aria-label="نوع ایراد فنی">' ), str_contains( $crawl_src, 'cf=orphan' ), str_contains( $crawl_src, 'hodima-advisor__chips-caption' ), str_contains( $crawl_src, 'همه نوع‌ها' ), str_contains( $crawl_src, '<select' ), str_contains( $crawl_html, 'aria-label="نوع ایراد فنی"' ) ], [ true, true, false, false, false, false ] );
hodima_ad( '«کارها»: پیوند بخش نمره به همین صفحه با cf و بدون tab=crawl', [ str_contains( $crawl_html, 'cf=cat-meta' ), str_contains( $crawl_html, 'tab=crawl' ) ], [ true, false ] );
$_GET['cf'] = 'orphan';
$orphan_html = $cap( static fn() => hodima_seo_advisor_render_overview() );
hodima_ad( 'فیلتر «بدون لینک ورودی» (cf) فقط همان صفحه را نشان می‌دهد؛ پیوند «برداشتن فیلتر» و «فیلتر فعال» هست', [ str_contains( $orphan_html, '/orphan/' ), str_contains( $orphan_html, 'id="' . hodima_seo_advisor_row_anchor( '/dup-b/' ) . '"' ) /* SEO 3.7.3: آدرس /dup-b/ ممکن است در «از این صفحه‌ها لینک بدهید» بیاید؛ فقط ردیفش نباید باشد */, str_contains( $orphan_html, 'فیلتر فعال' ) ], [ true, false, true ] );
hodima_ad( 'نوع فعال: چیپ aria-current دارد و پیوندش فیلتر را برمی‌دارد (بدون cf=orphan)؛ ردیف نوع‌ها هنوز دیده می‌شود', [ 1 === preg_match( '#<a class="hodima-advisor__chip" href="([^"]*)" aria-current="true">\s*<span>بدون لینک ورودی</span>#u', $orphan_html, $orphan_chip ), ! str_contains( (string) ( $orphan_chip[1] ?? 'cf=orphan' ), 'cf=orphan' ), str_contains( $orphan_html, 'aria-label="نوع ایراد فنی"' ) ], [ true, true, true ] );
// خط «اطلاع» زیر نمره و فیلتر lvl=info (SEO 3.8.8، طرح تأییدشده کاربر)
$_GET      = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
$info_pg   = hodima_seo_advisor_overview_build();
$info_n    = hodima_seo_advisor_info_counts( $info_pg );
$info_real = 0;
foreach ( $info_pg as $info_p ) {
	foreach ( $info_p['items'] as $info_i ) {
		$info_real += 'crawl' === $info_i['src'] && 'info' === $info_i['level'] ? 1 : 0;
	}
}
hodima_ad( 'شمار اطلاع‌ها = آیتم‌های crawl سطح info فهرست؛ صفحه‌ها ≤ آیتم‌ها؛ داده آزمون دست‌کم یکی دارد', [ $info_n['items'], $info_n['pages'] <= $info_n['items'], $info_n['items'] > 0 ], [ $info_real, true, true ] );
$info_html = $cap( static fn() => hodima_seo_advisor_render_overview() );
hodima_ad( 'خط «اطلاع» زیر نمره با شمار، صفحه‌ها و پیوند lvl=info؛ با نمره کمتر از ۱۰۰ جمله «نمره ۱۰۰ یعنی» نیست', [ str_contains( $info_html, 'hodima-advisor__sc-info' ), str_contains( $info_html, sprintf( '%s پیشنهاد «اطلاع» روی %s صفحه', number_format_i18n( $info_n['items'] ), number_format_i18n( $info_n['pages'] ) ) ), str_contains( $info_html, 'lvl=info' ), str_contains( $info_html, 'نمره ۱۰۰ یعنی' ) || 100 === ( hodima_seo_advisor_score( hodima_seo_advisor_crawl_issues(), hodima_seo_advisor_crawl_status()['pages'] )['score'] ?? 0 ) ], [ true, true, true, false ] );
$score_only = static fn( array $issues, array $info ): string => $cap( static fn() => hodima_seo_advisor_render_score( $issues, 10, '', $info ) );
$sc100 = $score_only( [], [ 'items' => 63, 'pages' => 41 ] );
$sc0   = $score_only( [], [ 'items' => 0, 'pages' => 0 ] );
hodima_ad( 'نمره ۱۰۰ با ۶۳ اطلاع ← «نمره ۱۰۰ یعنی هیچ ایراد قرمز یا کهربایی نیست»؛ بدون اطلاع ← خط نمی‌آید', [ str_contains( $sc100, 'نمره ۱۰۰ یعنی هیچ ایراد قرمز یا کهربایی نیست.' ) || str_contains( $sc100, 'نمره 100 یعنی' ), str_contains( $sc100, 'روی 41 صفحه' ) || str_contains( $sc100, 'روی ۴۱ صفحه' ), str_contains( $sc0, 'hodima-advisor__sc-info' ) ], [ true, true, false ] );
$_GET['lvl'] = 'info';
$lvl_html    = $cap( static fn() => hodima_seo_advisor_render_overview() );
hodima_ad( 'فیلتر lvl=info: فقط برچسب اطلاع، پیام «فقط نمایش» و «برداشتن فیلتر»؛ مقدار ناشناخته نادیده', [ substr_count( $lvl_html, 'hds-pill hds-pill--info' ) > 0, substr_count( $lvl_html, 'hds-pill hds-pill--warn' ) + substr_count( $lvl_html, 'hds-pill hds-pill--error' ), str_contains( $lvl_html, 'فقط نمایش: پیشنهادهای «اطلاع»' ), str_contains( $cap( static function () { $_GET['lvl'] = 'x'; hodima_seo_advisor_render_overview(); } ), 'فقط نمایش: پیشنهادهای «اطلاع»' ) ], [ true, 0, true, false ] );
$_GET = [];

// پاک کردن داده جدول‌های خزش را هم پاک می‌کند
hodima_seo_advisor_clear_data();
hodima_ad( 'پاک کردن داده: جدول‌ها و وضعیت خزش خالی', [ hodima_seo_advisor_crawl_summary( [] )['crawled'], hodima_seo_advisor_crawl_status()['imported'] ], [ 0, 0 ] );
$empty_crawl = $cap( static fn() => hodima_seo_advisor_render_overview() );
hodima_ad( 'بدون خزش: «کارها» بی‌نوار سلامت و بی‌خطا باز می‌شود', [ str_contains( $empty_crawl, 'hodima-advisor__sc-gauge' ), str_contains( $empty_crawl, 'hodima-advisor__toolbar' ) ], [ false, true ] );
remove_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter );
array_map( 'unlink', glob( "{$crawl_dir}/{,.}*", GLOB_BRACE ) ?: [] );
@rmdir( $crawl_dir );

echo "=== انتشار ۲: رقابت‌های حل‌شده و نرخ کلیک با داده خزش\n";
$crawl_dir2 = sys_get_temp_dir() . '/hodima-crawl-test2-' . getmypid();
@mkdir( $crawl_dir2 );
$crawl_filter2 = static fn(): string => $crawl_dir2;
add_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter2 );
$csv2 = static function ( string $name, array $rows ) use ( $crawl_dir2 ): void {
	$h = fopen( "{$crawl_dir2}/{$name}", 'wb' );
	fwrite( $h, "\xEF\xBB\xBF" );
	foreach ( $rows as $r ) {
		fputcsv( $h, $r, ',', '"', '' );
	}
	fclose( $h );
	touch( "{$crawl_dir2}/{$name}", time() - 3600 );
};
$term_row = static fn( int $imp, float $pos, int $clicks = 5 ): array => [ 'clicks' => $clicks, 'impressions' => $imp, 'position' => $pos ];
$ok_title = str_repeat( 'عنوان سالم ', 5 );
// دو گروه: «کلیپس ماری» بین /a-live/ و /a-moved/ (ریدایرکت طبق خزش)؛ «کلیپس گل» بین سه صفحه که یکی (/b-gone/) ۴۰۴ است
hodima_seo_advisor_store(
	[
		'/a-live/' => [ 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0 ], '/a-moved/' => [ 'clicks' => 10, 'impressions' => 1500, 'position' => 8.0 ],
		'/b-one/' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ], '/b-two/' => [ 'clicks' => 20, 'impressions' => 1800, 'position' => 9.0 ], '/b-gone/' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 12.0 ],
		'/ctr-ok/' => [ 'clicks' => 10, 'impressions' => 6000, 'position' => 3.0 ], '/ctr-bad/' => [ 'clicks' => 10, 'impressions' => 6000, 'position' => 3.0 ],
	],
	[],
	[
		'/a-live/' => [ 'کلیپس ماری' => $term_row( 2500, 6.0 ) ], '/a-moved/' => [ 'کلیپس ماری' => $term_row( 1400, 8.0 ) ],
		'/b-one/' => [ 'کلیپس گل' => $term_row( 1800, 7.0 ) ], '/b-two/' => [ 'کلیپس گل' => $term_row( 1700, 9.0 ) ], '/b-gone/' => [ 'کلیپس گل' => $term_row( 800, 12.0 ) ],
		'/ctr-ok/' => [ 'کلمه خنثی' => $term_row( 5000, 3.0, 5 ) ], '/ctr-bad/' => [ 'کلمه خنثی' => $term_row( 5000, 3.0, 5 ) ],
	]
);
$bare = static fn( string $path ): array => [ $u( $path ), '200', 'Indexable', '', $ok_title, (string) mb_strlen( $ok_title ), '130', 'تیتر', '0.4', '5', '1', '1', '' ];
$csv2( 'internal_html.csv', [
	[ 'Address', 'Status Code', 'Indexability', 'Indexability Status', 'Title 1', 'Title 1 Length', 'Meta Description 1 Length', 'H1-1', 'Response Time', 'Unique Inlinks', 'Crawl Depth', 'Link Score', 'Redirect URL' ],
	$bare( '/a-live/' ),
	[ $u( '/a-moved/' ), '301', 'Non-Indexable', 'Redirected', '', '0', '0', '', '0.2', '4', '2', '0', $u( '/a-live/' ) ],
	$bare( '/b-one/' ), $bare( '/b-two/' ), $bare( '/ctr-ok/' ),
	[ $u( '/ctr-bad/' ), '200', 'Indexable', '', 'کوتاه', '5', '0', 'تیتر', '0.4', '5', '1', '1', '' ],
] );
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'router' => true, 'dead' => [ [ 'path' => '/b-gone/', 'impressions' => 900, 'clicks' => 5, 'state' => 'missing', 'target' => '', 'code' => 404 ] ], 'total' => 0, 'seen' => 0, 'fresh' => 0, 'unseen' => [] ], false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'pages' => 7, 'days' => 90, 'property' => 'sc-domain:x' ], false );
delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
hodima_seo_advisor_crawl_map( true );
$imp2 = hodima_seo_advisor_crawl_import();
hodima_ad( 'خواندن فایل‌های خزش پیشنهادها را دوباره می‌سازد و نوبتی باقی نمی‌ماند', [ is_wp_error( $imp2 ) ? $imp2->get_error_message() : 'ok', hodima_seo_advisor_pipeline_pending() ], [ 'ok', false ] );

$rp = hodima_seo_advisor_resolved_paths();
hodima_ad( 'صفحه‌های حل‌شده: ریدایرکت طبق خزش، ۴۰۴ طبق سلامت آدرس‌ها؛ صفحه سالم نه', [ $rp['/a-moved/'] ?? '', $rp['/b-gone/'] ?? '', isset( $rp['/a-live/'] ) ], [ 'redirect', 'gone', false ] );
$comp = hodima_seo_advisor_competition();
$terms_main = array_column( $comp['rows'], 'term' );
hodima_ad( 'رقابت اصلی: گروه ریدایرکت‌شده کنار رفت؛ گروه سه‌صفحه‌ای با دو صفحه زنده می‌ماند', [ in_array( 'کلیپس ماری', $terms_main, true ), in_array( 'کلیپس گل', $terms_main, true ) ], [ false, true ] );
$flower = array_values( array_filter( $comp['rows'], static fn( array $g ): bool => 'کلیپس گل' === $g['term'] ) )[0];
hodima_ad( 'صفحه ۴۰۴ از گروه باقی‌مانده حذف شده (۲ صفحه)', [ $flower['count'], array_column( $flower['pages'], 'path' ) ], [ 2, [ '/b-one/', '/b-two/' ] ] );
hodima_ad( 'شمار حل‌شده‌ها برای یادداشت بالای فهرست', [ $comp['resolved']['groups'], $comp['resolved']['potential'] >= 0 ], [ 1, true ] );
$settled_rows = hodima_seo_advisor_competition( [ 'resolved' => true ] );
hodima_ad( 'نمای حل‌شده‌ها فقط همان گروه را با دلیل هر صفحه دارد', [ array_column( $settled_rows['rows'], 'term' ), $settled_rows['rows'][0]['advice']['type'], array_column( $settled_rows['rows'][0]['pages'], 'resolved', 'path' ) ], [ [ 'کلیپس ماری' ], 'settled', [ '/a-live/' => '', '/a-moved/' => 'redirect' ] ] );
hodima_ad( 'رقیب‌های یک صفحه: صفحه حل‌شده نمی‌آید؛ خود صفحه حل‌شده رقیب ندارد', [ array_keys( hodima_seo_advisor_competitors( '/b-one/', [ [ 'term' => 'کلیپس گل', 'impressions' => 1800 ] ] ) ), hodima_seo_advisor_competitors( '/b-gone/', [ [ 'term' => 'کلیپس گل', 'impressions' => 800 ] ] ) ], [ [ hodima_seo_advisor_term_key( 'کلیپس گل' ) ], [] ] );

// قاعده نرخ کلیک
$ok_adv = hodima_seo_advisor_analyze( '/ctr-ok/' );
$bad_adv = hodima_seo_advisor_analyze( '/ctr-bad/' );
$find = static fn( ?array $a ): ?array => array_values( array_filter( (array) ( $a['suggestions'] ?? [] ), static fn( array $s ): bool => 'low_ctr' === $s['rule'] ) )[0] ?? null;
$ok_s  = $find( $ok_adv );
$bad_s = $find( $bad_adv );
hodima_ad( 'عنوان و متا سالم: تشخیص «طول سالم است» و ضریب اطمینان روی کلیک بالقوه', [ $ok_s['title'] ?? '', str_contains( (string) ( $ok_s['detail'] ?? '' ), 'ایرادی ندارد' ), ( $ok_s['potential'] ?? 0 ) < ( $bad_s['potential'] ?? 0 ) ], [ 'نرخ کلیک کم است؛ طول عنوان و متا سالم است', true, true ] );
hodima_ad( 'عنوان کوتاه و بدون متا: همان بازنویسی با ایراد خزش و بدون کاهش تخمین', [ $bad_s['title'] ?? '', str_contains( (string) ( $bad_s['detail'] ?? '' ), 'طبق خزش: عنوان کوتاه است؛ توضیح متا ندارد' ), (int) round( ( $ok_s['potential'] ?? 0 ) / HODIMA_SEO_ADVISOR_LOW_CTR_HEALTHY_FACTOR ) >= ( $bad_s['potential'] ?? 0 ) - 1 ], [ 'عنوان و توضیح متا را بازنویسی کنید', true, true ] );
$no_crawl = $find( [ 'suggestions' => hodima_seo_advisor_suggestions( [ 'path' => '/z/', 'clicks' => 10, 'impressions' => 6000, 'position' => 3.0, 'prev_clicks' => 0 ], [], null ) ] );
hodima_ad( 'بدون داده خزش: متن قبلی بازنویسی بدون تغییر', $no_crawl['title'] ?? '', 'عنوان و توضیح متا را بازنویسی کنید' );

// نمای تب
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
$ch = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'تب رقابت: یادداشت «۱ گروه کنار رفت» با لینک دیدن', [ str_contains( $ch, '1 گروه رقابت از این فهرست کنار رفت' ) || str_contains( $ch, '۱ گروه رقابت از این فهرست کنار رفت' ), str_contains( $ch, 'resolved=1' ) ], [ true, true ] );
$_GET['resolved'] = '1';
$ch2 = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'نمای حل‌شده‌ها: عنوان، نشان دلیل هر صفحه و بدون «برنده پیشنهادی»', [ str_contains( $ch2, 'رقابت‌های حل‌شده' ), str_contains( $ch2, 'ریدایرکت می‌شود' ), str_contains( $ch2, 'برنده پیشنهادی' ) ], [ true, true, false ] );
$_GET = [];

hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION );
hodima_ad( 'پاک کردن داده: نقشه خزش و حل‌شده‌ها خالی', [ hodima_seo_advisor_crawl_map(), hodima_seo_advisor_resolved_paths() ], [ [], [ '/' => 'home' ] ] );
remove_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter2 );
array_map( 'unlink', glob( "{$crawl_dir2}/{,.}*", GLOB_BRACE ) ?: [] );
@rmdir( $crawl_dir2 );

echo "=== آدرس کامل و قابل‌کلیک (رقابت، سلامت، ردیف فهرست)\n";
$lk = hodima_seo_advisor_url_link( '/' . rawurlencode( 'گل-سر' ) . '/' );
hodima_ad( 'لینک آدرس: کامل با دامنه، رمزگشایی‌شده، زبانه تازه با noopener', [ str_contains( $lk, 'href="' . esc_url( hodima_seo_advisor_url( '/' . rawurlencode( 'گل-سر' ) . '/' ) ) . '"' ), str_contains( $lk, '<bdi>' . esc_html( home_url( '/گل-سر/' ) ) . '</bdi>' ) || str_contains( $lk, 'گل-سر' ), str_contains( $lk, 'target="_blank"' ), str_contains( $lk, 'rel="noopener"' ), str_contains( $lk, 'dir="ltr"' ) ], [ true, true, true, true, true ] );
hodima_ad( 'آدرس کامل (با http) دست‌نخورده لینک می‌شود', str_contains( hodima_seo_advisor_url_link( 'https://example.org/a/b/' ), 'href="https://example.org/a/b/"' ), true );
$home = hodima_seo_advisor_url_link( '/' );
hodima_ad( 'صفحه اصلی هم آدرس کامل دارد، نه فقط «/»', str_contains( $home, '>' . esc_html( rawurldecode( hodima_seo_advisor_url( '/' ) ) ) . '<' ), true );
hodima_ad( 'عنوان صفحه اصلی بدون عنوان «صفحه اصلی» است نه «/»', [ hodima_seo_advisor_competition_page_title( [ 'kind' => '', 'object_id' => 0, 'title' => '', 'path' => '/' ] ), hodima_seo_advisor_competition_page_title( [ 'kind' => '', 'object_id' => 0, 'title' => '', 'path' => '/x/' ] ) ], [ 'صفحه اصلی', '/x/' ] );

echo "=== انتشار ۳: شواهد لینک داخلی در «نزدیک صفحه اول» و رقابت\n";
$crawl_dir3 = sys_get_temp_dir() . '/hodima-crawl-test3-' . getmypid();
@mkdir( $crawl_dir3 );
$crawl_filter3 = static fn(): string => $crawl_dir3;
add_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter3 );
$csv3 = static function ( string $name, array $rows ) use ( $crawl_dir3 ): void {
	$h = fopen( "{$crawl_dir3}/{$name}", 'wb' );
	fwrite( $h, "\xEF\xBB\xBF" );
	foreach ( $rows as $r ) {
		fputcsv( $h, $r, ',', '"', '' );
	}
	fclose( $h );
	touch( "{$crawl_dir3}/{$name}", time() - 3600 );
};
$t3 = static fn( int $imp, float $pos, int $clicks ): array => [ 'clicks' => $clicks, 'impressions' => $imp, 'position' => $pos ];
hodima_seo_advisor_store(
	[ '/near/' => [ 'clicks' => 5, 'impressions' => 4000, 'position' => 8.5 ], '/bow-a/' => [ 'clicks' => 60, 'impressions' => 3000, 'position' => 5.0 ], '/bow-b/' => [ 'clicks' => 40, 'impressions' => 2500, 'position' => 6.0 ] ],
	[],
	[ '/near/' => [ 'کلیپس سنجاقی' => $t3( 3000, 8.5, 3 ) ], '/bow-a/' => [ 'کلیپس پاپیونی' => $t3( 2800, 5.0, 55 ) ], '/bow-b/' => [ 'کلیپس پاپیونی' => $t3( 2400, 6.0, 38 ) ] ]
);
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'pages' => 3, 'days' => 90, 'property' => 'sc-domain:x' ], false );
$ok3 = str_repeat( 'عنوان سالم ', 5 );
$page3 = static fn( string $p, int $in, int $depth, string $score ): array => [ $u( $p ), '200', 'Indexable', '', $ok3, (string) mb_strlen( $ok3 ), '130', 'تیتر', '0.4', (string) $in, (string) $depth, $score, '' ];
$csv3( 'internal_html.csv', [
	[ 'Address', 'Status Code', 'Indexability', 'Indexability Status', 'Title 1', 'Title 1 Length', 'Meta Description 1 Length', 'H1-1', 'Response Time', 'Unique Inlinks', 'Crawl Depth', 'Link Score', 'Redirect URL' ],
	$page3( '/near/', 2, 5, '1' ), $page3( '/bow-a/', 17, 2, '40' ), $page3( '/bow-b/', 16, 2, '38' ),
] );
$links3 = [ [ 'Type', 'Source', 'Destination', 'Anchor', 'Follow', 'Link Position' ] ];
foreach ( [ 1, 2 ] as $i ) {
	$links3[] = [ 'Hyperlink', $u( "/s{$i}/" ), $u( '/near/' ), 'گیره', 'true', 'Content' ];
}
for ( $i = 1; $i <= 17; $i++ ) {
	$links3[] = [ 'Hyperlink', $u( "/s{$i}/" ), $u( '/bow-a/' ), 'کلیپس پاپیونی', 'true', 'Content' ];
}
for ( $i = 1; $i <= 16; $i++ ) {
	$links3[] = [ 'Hyperlink', $u( "/s{$i}/" ), $u( '/bow-b/' ), 'کلیپس پاپیونی مدل ب', 'true', 'Content' ];
}
$links3[] = [ 'Hyperlink', $u( '/s3/' ), $u( '/bow-b/' ), 'منو', 'true', 'Navigation' ]; // منو نمی‌شمارد
$csv3( 'all_inlinks.csv', $links3 );
delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
$imp3 = hodima_seo_advisor_crawl_import();
hodima_ad( 'خواندن ۳ صفحه و لینک‌ها', is_wp_error( $imp3 ) ? $imp3->get_error_message() : $imp3['pages'], 3 );

$li = hodima_seo_advisor_crawl_links_info( '/near/' );
hodima_ad( 'شواهد لینک صفحه: یکتا، از متن، Link Score، عمق و متن لینک‌ها', [ $li['total'], $li['text'], $li['score'], $li['depth'], $li['anchors'] ], [ 2, 2, 1.0, 5, [ [ 'anchor' => 'گیره', 'count' => 2 ] ] ] );
hodima_ad( 'بدون فایل لینک‌ها شواهد null است', hodima_seo_advisor_crawl_links_info( '/not-crawled/' ), null );
hodima_ad( 'لینک منو در متن لینک‌ها نیست', array_column( hodima_seo_advisor_crawl_anchors( '/bow-b/' ), 'anchor' ), [ 'کلیپس پاپیونی مدل ب' ] );
hodima_ad( 'شمار لینک با عبارت کلمه (املای ی/ي یکی)', [ hodima_seo_advisor_crawl_term_links( $li['anchors'], 'کلیپس سنجاقی' ), hodima_seo_advisor_crawl_term_links( [ [ 'anchor' => 'کليپس پاپیونی', 'count' => 3 ] ], 'کلیپس پاپیونی' ) ], [ 0, 3 ] );

$ev = hodima_seo_advisor_links_evidence( $li, 'کلیپس سنجاقی' );
hodima_ad( 'جمله شواهد: لینک‌ها، Link Score، نبود لینک با عبارت و عمق خزش', [ str_contains( $ev, 'طبق خزش این صفحه ' . number_format_i18n( 2 ) . ' لینک ورودی یکتا دارد (' . number_format_i18n( 2 ) . ' از متن)، Link Score ' . number_format_i18n( 1.0, 1 ) ), str_contains( $ev, 'هیچ لینک متنی با عبارت «کلیپس سنجاقی»' ), str_contains( $ev, 'عمق خزش ' . number_format_i18n( 5 ) . ' است' ) ], [ true, true, true ] );
hodima_ad( 'بدون شواهد جمله خالی است', hodima_seo_advisor_links_evidence( null, 'x' ), '' );
$ev_has = hodima_seo_advisor_links_evidence( [ 'total' => 2, 'text' => 2, 'score' => 0.0, 'depth' => 1, 'anchors' => [ [ 'anchor' => 'کلیپس سنجاقی ارزان', 'count' => 2 ] ] ], 'کلیپس سنجاقی' );
hodima_ad( 'لینک با عبارت هست ولی کم: «۲ لینک با متن» و «لینک متنی کم است»', [ str_contains( $ev_has, number_format_i18n( 2 ) . ' لینک با متن «کلیپس سنجاقی»' ), str_contains( $ev_has, 'لینک متنی کم است' ), str_contains( $ev_has, 'Link Score' ) ], [ true, true, false ] );

$an = hodima_seo_advisor_analyze( '/near/' );
$near_s = array_values( array_filter( (array) ( $an['suggestions'] ?? [] ), static fn( array $x ): bool => 'near_first' === $x['rule'] ) )[0] ?? null;
hodima_ad( 'پیشنهاد «نزدیک صفحه اول» شواهد لینک خزش را دارد', [ null !== $near_s, str_contains( (string) ( $near_s['detail'] ?? '' ), 'طبق خزش این صفحه' ) ], [ true, true ] );

$cmp = hodima_seo_advisor_competition( [ 'limit' => 15 ] );
$grp = array_values( array_filter( $cmp['rows'], static fn( array $g ): bool => 'کلیپس پاپیونی' === $g['term'] ) )[0] ?? null;
hodima_ad( 'رقابت: لینک با متن کلمه برای هر صفحه (۱۷ و ۱۶؛ منو نشمرده)', null === $grp ? null : array_column( $grp['pages'], 'term_links', 'path' ), [ '/bow-a/' => 17, '/bow-b/' => 16 ] );
hodima_ad( 'رقابت: دستور می‌گوید لینک‌ها تقریبا برابرند', null !== $grp && str_contains( $grp['advice']['detail'], 'تقریبا برابر است' ), true );
$csv_rows = hodima_seo_advisor_competition();
hodima_ad( 'بدون limit (CSV) شواهد پرسش اضافه نمی‌سازد', isset( $csv_rows['rows'][0]['pages'][0]['term_links'] ), false );
$w = static fn( string $path, ?int $tl ): array => [ 'path' => $path, 'term_links' => $tl ];
hodima_ad( 'مقایسه: رقیب لینک بیشتر دارد ← منتقل کنید', str_contains( hodima_seo_advisor_competition_links_note( 'ک', [ $w( '/w/', 3 ), $w( '/r/', 9 ) ] ), 'منتقل کنید' ), true );
hodima_ad( 'مقایسه: برنده خیلی جلوتر ← حفظ کنید', str_contains( hodima_seo_advisor_competition_links_note( 'ک', [ $w( '/w/', 30 ), $w( '/r/', 9 ) ] ), 'حفظ کنید' ), true );
hodima_ad( 'مقایسه: شواهد یک صفحه کافی نیست ← خالی', hodima_seo_advisor_competition_links_note( 'ک', [ $w( '/w/', 3 ), $w( '/r/', null ) ] ), '' );

$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
$ch3 = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'تب رقابت: زیر هر صفحه «لینک با متن کلمه · لینک ورودی»', [ str_contains( $ch3, '۱۷ لینک با متن «کلیپس پاپیونی» · ۱۷ لینک ورودی' ) || str_contains( $ch3, '17 لینک با متن «کلیپس پاپیونی» · 17 لینک ورودی' ) ], [ true ] );
$_GET = [];

hodima_seo_advisor_clear_data();
remove_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter3 );
array_map( 'unlink', glob( "{$crawl_dir3}/{,.}*", GLOB_BRACE ) ?: [] );
@rmdir( $crawl_dir3 );

echo "=== انتشار ۴: عنوان و تیترهای رندرشده، بدون H1، لینک کافی\n";
$crawl_dir4 = sys_get_temp_dir() . '/hodima-crawl-test4-' . getmypid();
@mkdir( $crawl_dir4 );
$crawl_filter4 = static fn(): string => $crawl_dir4;
add_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter4 );
$csv4 = static function ( string $name, array $rows ) use ( $crawl_dir4 ): void {
	$h = fopen( "{$crawl_dir4}/{$name}", 'wb' );
	fwrite( $h, "\xEF\xBB\xBF" );
	foreach ( $rows as $r ) {
		fputcsv( $h, $r, ',', '"', '' );
	}
	fclose( $h );
	touch( "{$crawl_dir4}/{$name}", time() - 3600 );
};
$r4_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'صفحه آزمون چهار', 'post_name' => 'advisor-r4', 'post_content' => '<p>متن ساده</p>' ] );
$r4_path = '/advisor-r4/';
hodima_seo_advisor_store( [ $r4_path => [ 'clicks' => 5, 'impressions' => 4000, 'position' => 8.5 ] ], [], [ $r4_path => [ 'کلیپس سنجاقی' => [ 'clicks' => 3, 'impressions' => 3000, 'position' => 8.5 ] ] ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'pages' => 1, 'days' => 90, 'property' => 'sc-domain:x' ], false );
$rules4 = static fn( ?array $a ): array => array_column( (array) ( $a['suggestions'] ?? [] ), 'rule' );
$has_obj = ( hodima_seo_advisor_page( $r4_path )['object_id'] ?? 0 ) > 0;
if ( $has_obj ) {
	$before = $rules4( hodima_seo_advisor_analyze( $r4_path ) );
	hodima_ad( 'بدون خزش: کلمه‌ای که در متن و تیتر نیست «غایب» گزارش می‌شود', array_intersect( $before, [ 'missing_headings', 'missing_content' ] ) !== [], true );
}
$hdr4 = [ 'Address', 'Status Code', 'Indexability', 'Indexability Status', 'Title 1', 'Title 1 Length', 'Meta Description 1 Length', 'H1-1', 'H2-1', 'H2-2', 'Response Time', 'Unique Inlinks', 'Crawl Depth', 'Link Score', 'Redirect URL' ];
$ok4  = str_repeat( 'عنوان سالم ', 5 );
$csv4( 'internal_html.csv', [
	$hdr4,
	[ $u( $r4_path ), '200', 'Indexable', '', $ok4, (string) mb_strlen( $ok4 ), '130', 'کلیپس سنجاقی ارزان', 'بخش اول', 'بخش دوم', '0.4', '5', '1', '1', '' ],
	[ $u( '/noh1/' ), '200', 'Indexable', '', $ok4, (string) mb_strlen( $ok4 ), '130', '', '', '', '0.4', '5', '1', '1', '' ],
] );
@unlink( "{$crawl_dir4}/all_inlinks.csv" );
$imp4 = hodima_seo_advisor_crawl_import();
hodima_ad( 'خواندن صفحه‌ها با ستون‌های H2', is_wp_error( $imp4 ) ? $imp4->get_error_message() : $imp4['pages'], 2 );
$rd = hodima_seo_advisor_crawl_rendered( $r4_path );
hodima_ad( 'عنوان و تیترهای رندرشده: متن یکسان‌شده شامل H1 و هر دو H2', [ str_contains( (string) $rd['text'], 'کلیپس سنجاقی ارزان' ), str_contains( (string) $rd['text'], 'بخش دوم' ), $rd['h1'] ], [ true, true, 'کلیپس سنجاقی ارزان' ] );
hodima_ad( 'صفحه خارج از خزش: رندرشده null', hodima_seo_advisor_crawl_rendered( '/not-there/' ), null );
if ( $has_obj ) {
	$after = $rules4( hodima_seo_advisor_analyze( $r4_path ) );
	hodima_ad( 'H1 رندرشده کلمه را دارد ← دیگر «غایب» نیست', array_intersect( $after, [ 'missing_headings', 'missing_content' ] ), [] );
}
$text4 = [ 'title_headings' => hodima_seo_advisor_normalize( 'صفحه' ), 'content' => hodima_seo_advisor_normalize( 'متن' ), 'rendered' => [ 'title' => 'عنوان فعلی', 'h1' => 'تیتر فعلی' ] ];
$sug4  = hodima_seo_advisor_suggestions( [ 'path' => '/q/', 'clicks' => 5, 'impressions' => 4000, 'position' => 8.5, 'prev_clicks' => 0 ], [ [ 'term' => 'کلیپس سنجاقی', 'clicks' => 3, 'impressions' => 3000, 'position' => 8.5 ] ], $text4 );
$miss4 = array_values( array_filter( $sug4, static fn( array $x ): bool => str_starts_with( $x['rule'], 'missing_' ) ) )[0] ?? null;
hodima_ad( 'پیشنهاد «غایب»: عنوان و H1 فعلی طبق خزش در متن می‌آید', str_contains( (string) ( $miss4['detail'] ?? '' ), 'طبق خزش، عنوان فعلی «عنوان فعلی» و H1 «تیتر فعلی» است' ), true );
hodima_ad( 'بدون rendered همان جمله نیست', str_contains( (string) ( array_values( array_filter( hodima_seo_advisor_suggestions( [ 'path' => '/q/', 'clicks' => 5, 'impressions' => 4000, 'position' => 8.5, 'prev_clicks' => 0 ], [ [ 'term' => 'کلیپس سنجاقی', 'clicks' => 3, 'impressions' => 3000, 'position' => 8.5 ] ], [ 'title_headings' => 'x', 'content' => 'y' ] ), static fn( array $x ): bool => str_starts_with( $x['rule'], 'missing_' ) ) )[0]['detail'] ?? '' ), 'طبق خزش، عنوان' ), false );

$iss4 = [];
foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
	$iss4[ $row['path'] ] = array_column( $row['issues'], 'code' );
}
hodima_ad( 'صفحه بدون H1 ایراد «بدون H1» می‌گیرد و صفحه با H1 نه', [ in_array( 'no_h1', $iss4['/noh1/'] ?? [], true ), in_array( 'no_h1', $iss4[ $r4_path ] ?? [], true ) ], [ true, false ] );
hodima_ad( '«بدون H1» در چیپ عنوان شمرده می‌شود', hodima_seo_advisor_crawl_summary( hodima_seo_advisor_crawl_issues() )['groups']['title'] >= 1, true );

$well = hodima_seo_advisor_links_evidence( [ 'total' => 30, 'text' => 20, 'score' => 50.0, 'depth' => 1, 'anchors' => [ [ 'anchor' => 'کلیپس سنجاقی', 'count' => 8 ] ] ], 'کلیپس سنجاقی' );
hodima_ad( 'لینک داخلی کافی: دستور تمرکز را روی عنوان و متن می‌گذارد', [ str_contains( $well, 'لینک داخلی این صفحه کم نیست' ), str_contains( $well, 'لینک متنی کم است' ) ], [ true, false ] );
$notwell = hodima_seo_advisor_links_evidence( [ 'total' => 30, 'text' => 20, 'score' => 50.0, 'depth' => 1, 'anchors' => [ [ 'anchor' => 'کلیپس سنجاقی', 'count' => 2 ] ] ], 'کلیپس سنجاقی' );
hodima_ad( 'لینک زیاد ولی با عبارت کم: همان جمله نمی‌آید', str_contains( $notwell, 'لینک داخلی این صفحه کم نیست' ), false );
