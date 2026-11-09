<?php
/**
 * بخش 4 از آزمون‌های دستار سئو (tools/wp-harness/advisor-tests.php آن را به ترتیب و در همان دامنه سراسری می‌خواند):
 * بررسی فوری عنوان و متا از خود سایت، علامت «انجام دادم» و سنجش اثر.
 * تنهایی اجرا نمی‌شود: متغیرها و توابع کمکی (hodima_ad، $cap، …) از فایل اصلی و بخش‌های قبلی می‌آیند.
 */

declare(strict_types=1);

echo "=== بررسی فوری عنوان، متا و تیترها از خود سایت (advisor-live.php)\n";
$lp = hodima_seo_advisor_crawler_parse( '<!doctype html><html><head><title> عنوان  تازه صفحه </title><meta name="Description" content="توضیح متای تازه"><meta name="robots" content="index, follow"></head><body><h1>تیتر <b>اصلی</b></h1><h2>یک</h2><h2></h2><h2>دو</h2><h2>سه</h2></body></html>', home_url( '/x/' ) );
hodima_ad( 'خواندن HTML: عنوان، طول متا، H1 با تگ درونی، دو H2 اول غیرخالی، ایندکس‌پذیر', [ $lp['title'], mb_strlen( $lp['title'] ), mb_strlen( $lp['meta'] ), $lp['h1'], $lp['h2'], hodima_seo_advisor_crawler_page_row( '/x/', 200, 1, $lp, '', '' )['indexable'] ], [ 'عنوان تازه صفحه', 15, 15, 'تیتر اصلی', 'یک | دو', 1 ] );
$lp0 = hodima_seo_advisor_crawler_parse( '<html><head><meta name="robots" content="NOINDEX,follow"></head><body></body></html>', home_url( '/x/' ) );
$lp0_row = hodima_seo_advisor_crawler_page_row( '/x/', 200, 1, $lp0, '', '' );
hodima_ad( 'صفحه بی‌عنوان و noindex', [ $lp0['title'], $lp0_row['meta_len'], $lp0['h1'], $lp0_row['indexable'], $lp0_row['reason'] ], [ '', 0, '', 0, 'Noindex' ] );
$live_code = 200;
$live_headers = [];
$live_hits = [];
$live_html = '<html><head><title>' . $ok4 . '</title><meta name="description" content="' . str_repeat( 'م', 90 ) . '"></head><body><h1>تیتر تازه بدون کلیپس</h1><h2>بخش تازه</h2></body></html>';
$live_filter = static function ( $pre, array $args, string $url ) use ( &$live_code, &$live_hits, &$live_html, &$live_headers ) {
	if ( ! str_contains( $url, HODIMA_SEO_ADVISOR_LIVE_PARAM . '=' ) ) {
		return $pre;
	}
	$live_hits[] = [ $url, $args['redirection'] ?? null ];
	return [ 'headers' => $live_headers + [ 'content-type' => 'text/html; charset=UTF-8' ], 'body' => $live_html, 'response' => [ 'code' => $live_code, 'message' => '' ], 'cookies' => [], 'filename' => null ];
};
add_filter( 'pre_http_request', $live_filter, 30, 3 );
hodima_ad( 'پیش از بررسی: /noh1/ ایراد «بدون H1» دارد و زمان بررسی صفر', [ in_array( 'no_h1', $iss4['/noh1/'] ?? [], true ), hodima_seo_advisor_live_time( '/noh1/' ) ], [ true, 0 ] );
hodima_ad( 'آدرس خارج از خزش بررسی نمی‌شود (درخواستی نمی‌رود)', [ hodima_seo_advisor_live_check( '/not-there/' ), count( $live_hits ) ], [ false, 0 ] );
$live_code = 404;
hodima_ad( 'صفحه ۴۰۴: داده خزش دست نمی‌خورد', [ hodima_seo_advisor_live_check( '/noh1/' ), hodima_seo_advisor_crawl_row( '/noh1/' )['h1'] ], [ false, '' ] );
$live_code = 200;
hodima_ad( 'صفحه ۲۰۰: ردیف خزش با H1 تازه نوشته می‌شود، با پارامتر دور زدن کش و بدون دنبال کردن ریدایرکت', [ hodima_seo_advisor_live_check( '/noh1/' ), hodima_seo_advisor_crawl_row( '/noh1/' )['h1'], str_contains( (string) end( $live_hits )[0], '/noh1/?' . HODIMA_SEO_ADVISOR_LIVE_PARAM . '=' ), end( $live_hits )[1] ], [ true, 'تیتر تازه بدون کلیپس', true, 0 ] );
$iss_live = [];
foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
	$iss_live[ $row['path'] ] = array_column( $row['issues'], 'code' );
}
hodima_ad( 'ایراد «بدون H1» همان لحظه از «سلامت خزش» رفت و زمان بررسی ثبت شد', [ in_array( 'no_h1', $iss_live['/noh1/'] ?? [], true ), hodima_seo_advisor_live_time( '/noh1/' ) > 0 ], [ false, true ] );
if ( $has_obj ) {
	hodima_ad( 'H1 رندرشده کلمه را دارد ← پیش از بررسی «غایب» نیست', array_intersect( $rules4( hodima_seo_advisor_analyze( $r4_path ) ), [ 'missing_headings', 'missing_content' ] ), [] );
	hodima_seo_advisor_live_check( $r4_path ); // H1 تازه کلمه را ندارد
	hodima_ad( 'بعد از بررسی از سایت (H1 تازه بدون کلمه): پیشنهاد ذخیره‌شده فهرست هم «غایب» دارد', array_intersect( array_column( (array) hodima_seo_advisor_page( $r4_path )['advice'], 'rule' ), [ 'missing_headings', 'missing_content' ] ) !== [], true );
	wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_LIVE_CRON, [ $r4_path ] );
	wp_update_post( [ 'ID' => $r4_id, 'post_content' => '<p>متن ساده دیگر</p>' ] );
	hodima_ad( 'ذخیره صفحه‌ای که در خزش هست بررسی از سایت را زمان‌بندی می‌کند', (bool) wp_next_scheduled( HODIMA_SEO_ADVISOR_LIVE_CRON, [ $r4_path ] ), true );
	wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_LIVE_CRON, [ $r4_path ] );
	$r4_draft = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'پیش‌نویس چهار', 'post_name' => 'advisor-r4-draft' ] );
	hodima_ad( 'پیش‌نویس زمان‌بندی نمی‌شود', count( array_filter( (array) _get_cron_array(), static fn( $e ): bool => isset( $e[ HODIMA_SEO_ADVISOR_LIVE_CRON ] ) ) ), 0 );
	wp_delete_post( $r4_draft, true );
}
$GLOBALS['wpdb']->insert( hodima_seo_advisor_table( 'crawl_pages' ), [ 'path' => '/گل-سر/', 'status' => 200, 'indexable' => 1, 'reason' => '', 'title' => 'x', 'title_len' => 1, 'meta_len' => 0, 'title_px' => hodima_ad_tpx( 1 ), 'meta_px' => hodima_ad_mpx( 0 ), 'h1' => '', 'h2' => '', 'resp_ms' => 100, 'inlinks' => 3, 'text_links' => -1, 'depth' => 1, 'score' => 1, 'redirect_to' => '' ] );
hodima_seo_advisor_crawl_map( true );
hodima_seo_advisor_live_check( '/گل-سر/' );
hodima_ad( 'آدرس فارسی: مسیر در درخواست کدگذاری می‌شود و ردیف به‌روز می‌شود', [ str_contains( (string) end( $live_hits )[0], '/' . rawurlencode( 'گل-سر' ) . '/?' ), hodima_seo_advisor_crawl_row( '/گل-سر/' )['h1'] ], [ true, 'تیتر تازه بدون کلیپس' ] );
// بررسی فوری و خزنده شبانه یک خواندن دارند (SEO 3.1.9): همان HTML از دو مسیر ← همان ردیف
$lv_path = '/noh1/';
$lv_html = '<html><head><title>' . str_repeat( 'ت', 40 ) . '</title><meta name="description" content="' . str_repeat( 'م', 100 ) . '"><link rel="canonical" href="' . home_url( '/other/' ) . '"></head><body><main><h1>تیتر یک</h1><h1>تیتر دو</h1><p>یک دو سه چهار</p><a href="' . home_url( '/b/' ) . '">لینک ب</a><a href="' . home_url( '/b/' ) . '">لینک ب</a><img src="/a.jpg"></main></body></html>';
$live_html = $lv_html;
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'inlinks' => 7, 'text_links' => 5, 'depth' => 2, 'score' => 3.5 ], [ 'path' => $lv_path ] );
$lv_ms0 = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT resp_ms FROM %i WHERE path = %s', hodima_seo_advisor_table( 'crawl_pages' ), $lv_path ) );
hodima_seo_advisor_live_check( $lv_path );
$lv_live = hodima_seo_advisor_crawl_row( $lv_path );
$lv_cols = (array) $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i WHERE path = %s', hodima_seo_advisor_table( 'crawl_pages' ), $lv_path ), ARRAY_A );
$lv_ref  = hodima_seo_advisor_crawler_page_row( $lv_path, 200, (int) $lv_cols['resp_ms'], hodima_seo_advisor_crawler_parse( $lv_html, hodima_seo_advisor_url( ltrim( $lv_path, '/' ) ) ), '', '' );
$lv_diff = [];
foreach ( $lv_ref as $col => $val ) {
	if ( ! in_array( $col, [ 'inlinks', 'text_links', 'depth', 'score' ], true ) && (string) $lv_cols[ $col ] !== (string) $val ) {
		$lv_diff[] = $col;
	}
}
hodima_ad( 'بررسی فوری همان ردیفی را می‌نویسد که خزنده برای همان HTML می‌نویسد (همه ستون‌ها، جز آمار لینک)', $lv_diff, [] );
hodima_ad( 'زمان پاسخ خزش با بررسی فوری عوض نمی‌شود (درخواست بررسی بی‌کش است، معیار «کند» نیست)', (int) $lv_cols['resp_ms'], $lv_ms0 );
hodima_ad( 'ستون‌هایی که پیش‌تر کهنه می‌ماندند تازه شدند: canonical، دو H1، کلمه‌ها، تصویر بدون alt، دلیل canonical', [ $lv_cols['canonical'], (int) $lv_cols['h1_count'], (int) $lv_cols['words'] > 0, (int) $lv_cols['img_noalt'], $lv_cols['reason'], (int) $lv_cols['indexable'] ], [ '/other/', 2, true, 1, 'Canonicalised', 0 ] );
hodima_ad( 'آمار لینک ورودی، لینک متنی، عمق و امتیاز می‌ماند (فقط دور کامل خزش آن را می‌سازد)', [ (int) $lv_cols['inlinks'], (int) $lv_cols['text_links'], (int) $lv_cols['depth'], (float) $lv_cols['score'] ], [ 7, 5, 2, 3.5 ] );
hodima_ad( 'لینک‌های خروجی صفحه تازه شد (یکتا)', $GLOBALS['wpdb']->get_col( $GLOBALS['wpdb']->prepare( 'SELECT dest_path FROM %i WHERE source_path = %s', hodima_seo_advisor_table( 'crawl_links' ), $lv_path ) ), [ '/b/' ] );
$live_html    = '<html><head><title>x</title></head><body><h1>تیتر</h1></body></html>';
$live_headers = [ 'x-robots-tag' => 'noindex' ];
hodima_seo_advisor_live_check( $lv_path );
hodima_ad( 'noindex از سربرگ X-Robots-Tag هم دیده می‌شود (پیش‌تر فقط متای robots)', [ hodima_seo_advisor_crawl_row( $lv_path )['indexable'], hodima_seo_advisor_crawl_row( $lv_path )['reason'] ], [ false, 'Noindex' ] );
$live_headers = [ 'content-type' => 'application/pdf' ];
$live_h1_before = hodima_seo_advisor_crawl_row( $lv_path )['h1'];
$live_html = '<html><body><h1>نباید نوشته شود</h1></body></html>';
hodima_ad( 'پاسخ ۲۰۰ غیر HTML نوشته نمی‌شود', [ hodima_seo_advisor_live_check( $lv_path ), hodima_seo_advisor_crawl_row( $lv_path )['h1'] ], [ false, $live_h1_before ] );
$live_headers = [];
remove_filter( 'pre_http_request', $live_filter, 30 );
delete_option( HODIMA_SEO_ADVISOR_LIVE_OPTION );

hodima_seo_advisor_clear_data();
wp_delete_post( $r4_id, true );
remove_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter4 );
array_map( 'unlink', glob( "{$crawl_dir4}/{,.}*", GLOB_BRACE ) ?: [] );
@rmdir( $crawl_dir4 );

echo "=== صفحه اصلی رقیب حساب نمی‌شود\n";
$h_term = static fn( int $imp, float $pos, int $clicks ): array => [ 'clicks' => $clicks, 'impressions' => $imp, 'position' => $pos ];
hodima_seo_advisor_store(
	[ '/' => [ 'clicks' => 13, 'impressions' => 500, 'position' => 7.8 ], '/cat-x/' => [ 'clicks' => 263, 'impressions' => 23000, 'position' => 6.5 ], '/h-one/' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ], '/h-two/' => [ 'clicks' => 20, 'impressions' => 1800, 'position' => 9.0 ] ],
	[],
	[
		'/' => [ 'گیره کوچک' => $h_term( 400, 7.8, 13 ), 'گیره بزرگ' => $h_term( 300, 8.0, 10 ) ], '/cat-x/' => [ 'گیره کوچک' => $h_term( 22000, 6.5, 260 ) ],
		'/h-one/' => [ 'گیره بزرگ' => $h_term( 1800, 7.0, 28 ) ], '/h-two/' => [ 'گیره بزرگ' => $h_term( 1700, 9.0, 18 ) ],
	]
);
hodima_ad( 'صفحه اصلی همیشه «حل‌شده» است با برچسب «صفحه اصلی»', [ hodima_seo_advisor_resolved_paths()['/'] ?? '', hodima_seo_advisor_resolved_label( 'home' ) ], [ 'home', 'صفحه اصلی' ] );
$hc = hodima_seo_advisor_competition( [ 'limit' => 15 ] );
$hc_terms = array_column( $hc['rows'], 'term' );
hodima_ad( 'گروه فقط «صفحه اصلی + یک دسته» از فهرست اصلی کنار رفت', in_array( 'گیره کوچک', $hc_terms, true ), false );
$big = array_values( array_filter( $hc['rows'], static fn( array $g ): bool => 'گیره بزرگ' === $g['term'] ) )[0] ?? null;
hodima_ad( 'گروه سه‌صفحه‌ای: فقط صفحه اصلی کم می‌شود و دو صفحه می‌ماند', null === $big ? null : [ $big['count'], array_column( $big['pages'], 'path' ) ], [ 2, [ '/h-one/', '/h-two/' ] ] );
$hs = hodima_seo_advisor_competition( [ 'resolved' => true ] );
$small = array_values( array_filter( $hs['rows'], static fn( array $g ): bool => 'گیره کوچک' === $g['term'] ) )[0] ?? null;
hodima_ad( 'نمای حل‌شده‌ها: گروه با دلیل «home» و دستور «اقدامی لازم نیست»', null === $small ? null : [ array_column( $small['pages'], 'resolved', 'path' ), str_contains( $small['advice']['detail'], 'اقدامی لازم نیست' ) ], [ [ '/cat-x/' => '', '/' => 'home' ], true ] );
hodima_ad( 'رقیب‌های یک دسته: صفحه اصلی نمی‌آید؛ برای خود صفحه اصلی رقیبی نیست', [ hodima_seo_advisor_competitors( '/cat-x/', [ [ 'term' => 'گیره کوچک', 'impressions' => 22000 ] ] ), hodima_seo_advisor_competitors( '/', [ [ 'term' => 'گیره کوچک', 'impressions' => 400 ] ] ) ], [ [], [] ] );
hodima_seo_advisor_clear_data();

echo "=== «منتظر داده گوگل»: سنجش با داده فقط بعد از تغییر (advisor-verify.php)\n";
$vf_saved = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
$vf_task = static fn( string $rule, int $days_ago, array $extra = [] ): array => $extra + [ 'path' => '/vf/a/', 'rule' => $rule, 'term' => '', 'title' => 't', 'status' => 'done', 'at' => time() - $days_ago * DAY_IN_SECONDS, 'fixed' => 0, 'back' => 0, 'check' => [] ];
$vd = hodima_seo_advisor_verify_dates( $vf_task( 'low_ctr', 35 ) );
$vlen = static fn( array $w ): int => (int) ( new DateTimeImmutable( $w[0] ) )->diff( new DateTimeImmutable( $w[1] ) )->days + 1;
hodima_ad( 'بازه‌ها: ۳۵ روز پیش ← بیش از ۲۸ روز داده، پنجره ۲۸، قبل و بعد هم‌اندازه و چسبیده به روز تغییر', [ $vd['after_days'] >= 27, $vd['window'], $vlen( $vd['before'] ), $vlen( $vd['after'] ), ( new DateTimeImmutable( $vd['before'][1] ) )->modify( '+2 days' )->format( 'Y-m-d' ) === $vd['after'][0] ], [ true, 28, 28, 28, true ] );
hodima_ad( 'برچسب انتظار: کار همین امروز ← «۰ از ۱۴ روز»؛ متنی ← بدون شمار', [ (bool) preg_match( '/\((0|۰) از (14|۱۴) روز\)/u', hodima_seo_advisor_verify_wait_label( $vf_task( 'low_ctr', 0 ) ) ), hodima_seo_advisor_verify_wait_label( $vf_task( 'missing_content', 0 ) ) ], [ true, 'انجام شد · منتظر داده گوگل' ] );
hodima_ad( 'سررسید: ۲۰ روز پیش بله؛ ۵ روز نه؛ متنی/در حال انجام نه؛ «اثر داشت» نه؛ موقت ۲۸روزه نه؛ موقت ۱۴روزه هفته بعد بله', [
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 20 ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 5 ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'missing_content', 20 ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 20, [ 'status' => 'doing' ] ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 40, [ 'check' => [ 'verdict' => 'effective', 'window' => 14, 'at' => 1 ] ] ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 40, [ 'check' => [ 'verdict' => 'no_effect', 'window' => 28, 'at' => 1 ] ] ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 20, [ 'check' => [ 'verdict' => 'no_effect', 'window' => 14, 'at' => time() ] ] ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 20, [ 'check' => [ 'verdict' => 'no_effect', 'window' => 14, 'at' => time() - 8 * DAY_IN_SECONDS ] ] ) ),
], [ true, false, false, false, false, false, false, true ] );
$vm = static fn( int $c, int $i, float $p ): array => [ 'clicks' => $c, 'impressions' => $i, 'ctr' => $i > 0 ? round( $c / $i * 100, 2 ) : 0.0, 'position' => $p ];
$vj = static fn( string $rule, array $b, array $a, array $bt = [], array $at = [], string $term = '' ): string => hodima_seo_advisor_verify_judge( [ 'rule' => $rule, 'term' => $term ], [ 'page' => $b, 'terms' => $bt ], [ 'page' => $a, 'terms' => $at ] )['verdict'];
$tk = hodima_seo_advisor_term_key( 'کلیپس طلایی' );
hodima_ad( 'حکم: نرخ کلیک ۰٫۵٪←۱٫۵٪ اثر داشت؛ ۰٫۵٪←۰٫۵۲٪ نداشت؛ نمایش کم = داده کم؛ رسیدن به نرخ معمول رتبه = اثر داشت', [
	$vj( 'low_ctr', $vm( 50, 10000, 4.0 ), $vm( 150, 10000, 4.0 ) ),
	$vj( 'low_ctr', $vm( 50, 10000, 4.0 ), $vm( 52, 10000, 4.0 ) ),
	$vj( 'low_ctr', $vm( 5, 1000, 4.0 ), $vm( 9, 80, 4.0 ) ),
	$vj( 'low_ctr', $vm( 30, 1000, 15.0 ), $vm( 16, 1000, 15.0 ) ),
], [ 'effective', 'no_effect', 'low_data', 'effective' ] );
// حکم نرخ کلیک کلمه‌ای (SEO 3.0.8): شاخص کلیک واقعی ÷ مورد انتظار، نه خود نرخ کلیک
$vt_table = static fn(): array => [ 2 => 25.0, 3 => 14.0, 5 => 9.0, 7 => 5.5, 10 => 3.5, 20 => 1.5 ];
add_filter( 'hodima_seo_advisor_ctr_table', $vt_table );
$vt_good = hodima_seo_advisor_term_key( 'کلمه خوب' );
$vt_bad  = hodima_seo_advisor_term_key( 'کلمه بد' );
$vt_page = static fn( int $clicks, int $impressions ): array => [ 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => round( $clicks / $impressions * 100, 2 ), 'position' => 5.3 ];
$vt_before = [ $vt_good => $vm( 250, 1000, 2.0 ), $vt_bad => $vm( 50, 5000, 6.0 ) ]; // مورد انتظار ۵۲۵، واقعی ۳۰۰ ← شاخص ۰٫۵۷
hodima_ad( 'حکم نرخ کلیک کلمه‌ای: کلمه بد به مورد انتظار رسید ← اثر داشت؛ ۱۰٪ بهتر ← نداشت؛ ۳۰٪ بهتر ← داشت؛ جابه‌جایی سهم کلمه خوب ← نداشت (حساب کل صفحه اثر داشت می‌گفت)', [
	$vj( 'low_ctr', $vt_page( 300, 6000 ), $vt_page( 550, 6000 ), $vt_before, [ $vt_good => $vm( 250, 1000, 2.0 ), $vt_bad => $vm( 300, 5000, 6.0 ) ] ),
	$vj( 'low_ctr', $vt_page( 300, 6000 ), $vt_page( 330, 6000 ), $vt_before, [ $vt_good => $vm( 250, 1000, 2.0 ), $vt_bad => $vm( 80, 5000, 6.0 ) ] ),
	$vj( 'low_ctr', $vt_page( 300, 6000 ), $vt_page( 390, 6000 ), $vt_before, [ $vt_good => $vm( 250, 1000, 2.0 ), $vt_bad => $vm( 140, 5000, 6.0 ) ] ),
	$vj( 'low_ctr', $vt_page( 300, 6000 ), $vt_page( 550, 9000 ), $vt_before, [ $vt_good => $vm( 500, 2000, 2.0 ), $vt_bad => $vm( 50, 7000, 6.0 ) ] ),
], [ 'effective', 'no_effect', 'effective', 'no_effect' ] );
remove_filter( 'hodima_seo_advisor_ctr_table', $vt_table );
hodima_ad( 'حکم کلمه: ۱۲←۹٫۵ اثر داشت؛ ۱۲←۱۱٫۵ نداشت؛ رسیدن به ۷ (نزدیک صفحه اول) داشت؛ بدون کلمه بعد از تغییر داده کم', [
	$vj( 'near_first', $vm( 1, 900, 9 ), $vm( 1, 900, 9 ), [ $tk => $vm( 1, 300, 12.0 ) ], [ $tk => $vm( 2, 300, 9.5 ) ], 'کلیپس طلایی' ),
	$vj( 'near_first', $vm( 1, 900, 9 ), $vm( 1, 900, 9 ), [ $tk => $vm( 1, 300, 12.0 ) ], [ $tk => $vm( 2, 300, 11.5 ) ], 'کلیپس طلایی' ),
	$vj( 'near_first', $vm( 1, 900, 9 ), $vm( 1, 900, 9 ), [ $tk => $vm( 1, 300, 8.0 ) ], [ $tk => $vm( 2, 300, 7.0 ) ], 'کلیپس طلایی' ),
	$vj( 'weak_query', $vm( 1, 900, 9 ), $vm( 1, 900, 9 ), [ $tk => $vm( 1, 300, 30.0 ) ], [], 'کلیپس طلایی' ),
], [ 'effective', 'no_effect', 'effective', 'low_data' ] );
hodima_ad( 'حکم افت: کلیک ۴۰←۶۰ اثر داشت؛ ۴۰←۴۲ نداشت', [ $vj( 'decline', $vm( 40, 5000, 6 ), $vm( 60, 5000, 6 ) ), $vj( 'decline', $vm( 40, 5000, 6 ), $vm( 42, 5000, 6 ) ) ], [ 'effective', 'no_effect' ] );

// نوبت سنجش با پاسخ ساختگی سرچ کنسول
hodima_seo_advisor_store( [ '/vf/a/' => [ 'clicks' => 20, 'impressions' => 10000, 'position' => 4.0 ] ], [], [] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 1, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_save_advice( '/vf/a/' );
hodima_ad( 'آدرس نمونه پیشنهاد «نرخ کلیک کم» دارد', in_array( 'low_ctr', array_column( (array) hodima_seo_advisor_page( '/vf/a/' )['advice'], 'rule' ), true ), true );
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, [
	hodima_seo_advisor_task_key( '/vf/a/', 'low_ctr', '' ) => $vf_task( 'low_ctr', 21 ),
	hodima_seo_advisor_task_key( '/vf/a/', 'near_first', 'کلیپس طلایی' ) => $vf_task( 'near_first', 21, [ 'term' => 'کلیپس طلایی' ] ),
], false );
update_option( 'hodima_discover_sc_key', $key_json, false );
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) );
$vf_calls = [];
$vf_code  = 200;
$vf_dates = hodima_seo_advisor_verify_dates( $vf_task( 'low_ctr', 21 ) );
$vf_filter = static function ( $pre, array $args, string $url ) use ( &$vf_calls, &$vf_code, $vf_dates, $u ) {
	if ( ! str_contains( $url, '/searchAnalytics/query' ) ) {
		return $pre;
	}
	$body = json_decode( (string) $args['body'], true );
	if ( empty( $body['dimensionFilterGroups'] ) ) {
		return $pre;
	}
	$vf_calls[] = $body;
	$after      = $body['startDate'] === $vf_dates['after'][0];
	$rows       = 1 === count( $body['dimensions'] )
		? [ [ 'keys' => [ $u( '/vf/a/' ) ], 'clicks' => $after ? 150 : 50, 'impressions' => 10000, 'position' => 4.0 ], [ 'keys' => [ $u( '/x/vf/a/' ) ], 'clicks' => 9999, 'impressions' => 10000, 'position' => 1.0 ] ]
		: [ [ 'keys' => [ $u( '/vf/a/' ), 'كليپس طلايي' ], 'clicks' => 2, 'impressions' => 300, 'position' => $after ? 11.5 : 12.0 ] ];
	return [ 'headers' => [], 'body' => (string) wp_json_encode( $vf_code === 200 ? [ 'rows' => $rows ] : [ 'error' => [ 'message' => 'quota' ] ] ), 'response' => [ 'code' => $vf_code, 'message' => '' ], 'cookies' => [], 'filename' => null ];
};
add_filter( 'pre_http_request', $vf_filter, 50, 3 );
$vf_code = 429;
hodima_ad( 'سهمیه (۴۲۹): هیچ کاری سنجیده نمی‌شود و داده دست نمی‌خورد', [ hodima_seo_advisor_verify_batch( 'sc-domain:' . $host, INF ), hodima_seo_advisor_task( '/vf/a/', 'low_ctr', '' )['check'] ], [ 0, [] ] );
$vf_code  = 200;
$vf_calls = [];
add_filter( 'hodima_seo_advisor_verify_enabled', '__return_false' );
hodima_ad( 'فیلتر خاموش: نوبت کاری نمی‌کند', [ hodima_seo_advisor_verify_batch( 'sc-domain:' . $host, INF ), count( $vf_calls ) ], [ 0, 0 ] );
remove_filter( 'hodima_seo_advisor_verify_enabled', '__return_false' );
hodima_ad( 'نوبت: هر دو کار یک گروه (یک آدرس و روز) با ۴ درخواست فیلترشده', [ hodima_seo_advisor_verify_batch( 'sc-domain:' . $host, INF ), count( $vf_calls ), $vf_calls[0]['dimensionFilterGroups'][0]['filters'][0]['operator'] ?? '', $vf_calls[0]['dimensionFilterGroups'][0]['filters'][0]['expression'] ?? '' ], [ 2, 4, 'contains', '/vf/a/' ] );
$vf_low  = hodima_seo_advisor_task( '/vf/a/', 'low_ctr', '' );
$vf_near = hodima_seo_advisor_task( '/vf/a/', 'near_first', 'کلیپس طلایی' );
hodima_ad( 'نرخ کلیک ۰٫۵٪←۱٫۵٪ (آدرس مشابه /x/vf/a/ شمرده نشد): «اثر داشت» و «برطرف شد»', [ $vf_low['check']['verdict'] ?? '', $vf_low['check']['before'] ?? 0, $vf_low['check']['after'] ?? 0, hodima_seo_advisor_task_state( $vf_low ) ], [ 'effective', 0.5, 1.5, 'fixed' ] );
hodima_ad( 'کلمه (املای عربی ي/ك هم‌ارز) ۱۲←۱۱٫۵: «اثر نداشت» و گروه «نیاز به کار»', [ $vf_near['check']['verdict'] ?? '', hodima_seo_advisor_task_state( $vf_near ), hodima_seo_advisor_task_group( $vf_near ), hodima_seo_advisor_task_view( $vf_near )['label'] ], [ 'no_effect', 'no_effect', 'open', 'اثر نداشت؛ قدم بعدی را ببینید' ] );
hodima_ad( 'پیشنهاد «نرخ کلیک کم» تا وقتی بازه تحلیل روزهای پیش از تغییر را دارد ساخته نمی‌شود', in_array( 'low_ctr', array_column( (array) hodima_seo_advisor_page( '/vf/a/' )['advice'], 'rule' ), true ), false );
hodima_ad( 'سنجش دوباره همان روز: سررسید نیست', hodima_seo_advisor_verify_batch( 'sc-domain:' . $host, INF ), 0 );
$vf_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'start' => ( new DateTimeImmutable( 'tomorrow' ) )->format( 'Y-m-d' ) ] + $vf_status, false );
hodima_seo_advisor_save_advice( '/vf/a/' );
hodima_ad( 'بازه تحلیل تازه کاملا بعد از تغییر و هنوز نرخ کلیک کم: پیشنهاد برمی‌گردد و کار «برگشت»', [ in_array( 'low_ctr', array_column( (array) hodima_seo_advisor_page( '/vf/a/' )['advice'], 'rule' ), true ), hodima_seo_advisor_task_state( (array) hodima_seo_advisor_task( '/vf/a/', 'low_ctr', '' ) ) ], [ true, 'back' ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $vf_status, false );
hodima_ad( 'جمله سنجش: اثر داشت با اعداد؛ اثر نداشت با قدم بعدی (لینک داخلی و بخش H2)', [ str_contains( hodima_seo_advisor_verify_text( $vf_low ), 'اثر داشت' ), str_contains( hodima_seo_advisor_verify_text( $vf_near ), 'قدم بعدی' ) && str_contains( hodima_seo_advisor_verify_text( $vf_near ), 'H2' ), hodima_seo_advisor_verify_text( $vf_task( 'low_ctr', 1 ) ) ], [ true, true, '' ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks' ];
ob_start();
hodima_seo_advisor_render_tasks();
$vf_html = (string) ob_get_clean();
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks', 'tstatus' => 'fixed' ];
ob_start();
hodima_seo_advisor_render_tasks();
$vf_done = (string) ob_get_clean();
$_GET = [];
hodima_ad( '«کارها» ← «نیاز به کار»: «اثر نداشت» با قدم بعدی و دکمه «دوباره انجام دادم» (status=done_redo در همان فرم؛ SEO 3.5.5)؛ گام سوم کار «اثر داشت» سبز؛ فیلتر «برطرف شد» کار باز را نشان نمی‌دهد', [ str_contains( $vf_html, 'اثر نداشت؛ قدم بعدی را ببینید' ), str_contains( $vf_html, 'دوباره انجام دادم' ) && str_contains( $vf_html, 'name="status" value="done_redo"' ) && ! str_contains( $vf_html, 'name="redo"' ), hodima_seo_advisor_task_timeline( $vf_low )[2]['label'] . '/' . hodima_seo_advisor_task_timeline( $vf_low )[2]['state'], str_contains( $vf_done, 'اثر نداشت؛ قدم بعدی' ) ], [ true, true, 'اثر داشت/ok', false ] );
// بی‌کار: چهار کارت آمار «—» نمی‌آید، فقط حالت خالی (SEO 3.1.2)
$ec_tasks = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
ob_start();
hodima_seo_advisor_render_tasks();
$ec_open = (string) ob_get_clean();
false === $ec_tasks ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $ec_tasks, false );
hodima_ad( 'بدون کار: «کارها» کارت آمار و فیلتر ندارد و فقط حالت خالی؛ با کار، نوار وضعیت', [ substr_count( $ec_open, 'class="hds-stat"' ), str_contains( $ec_open, 'هنوز کاری ثبت نشده' ), str_contains( $ec_open, 'aria-label="وضعیت کار"' ), str_contains( $vf_html, 'aria-label="وضعیت کار"' ) ], [ 0, true, false, true ] );
hodima_ad( 'کادر ویرایش دکمه کار ندارد (SEO 3.7.6؛ «دوباره انجام دادم» در «کارهای انجام‌شده»)', function_exists( 'hodima_seo_advisor_render_task_controls' ), false );
hodima_seo_advisor_task_set( '/vf/a/', 'near_first', 'کلیپس طلایی', '', 'done', true );
$vf_redo = hodima_seo_advisor_task( '/vf/a/', 'near_first', 'کلیپس طلایی' );
hodima_ad( '«دوباره انجام دادم»: سنجش پاک، تاریخ امروز، عنوان قبلی، «منتظر داده گوگل»', [ $vf_redo['check'], time() - $vf_redo['at'] < 60, $vf_redo['title'], hodima_seo_advisor_task_state( $vf_redo ) ], [ [], true, 't', 'waiting' ] );
hodima_ad( 'نوبت verify آخر دریافت روزانه است', HODIMA_SEO_ADVISOR_STAGES[ count( HODIMA_SEO_ADVISOR_STAGES ) - 1 ], 'verify' );
remove_filter( 'pre_http_request', $vf_filter, 50 );
delete_option( 'hodima_discover_sc_key' );
false === $vf_saved ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $vf_saved, false );
hodima_seo_advisor_clear_data();

echo "=== لینک‌های داخلی یک گروه رقابت (advisor-links.php)\n";
require_once dirname( __DIR__ ) . '/advisor-links-fixture.php';
$lk_ids  = hodima_lk_fixture();
$lk_scan = hodima_seo_advisor_links_scan( 'گیره انبری فلزی', true );
hodima_ad( 'بررسی لینک‌ها خطا نمی‌دهد و هدف دسته برنده و رقیب /207/ است', [ ! is_wp_error( $lk_scan ), $lk_scan['target']['path'] ?? '', array_column( (array) ( $lk_scan['rivals'] ?? [] ), 'path' ) ], [ true, '/lk-cat/', [ '/207/' ] ] );
$lk_counts = (array) ( $lk_scan['counts'] ?? [] );
hodima_ad( 'لینک به رقیب ۸ (۲ متن، توضیح دسته، ۲ منو، لینک مرتبط فقط‌شناسه‌ای، متن خزش بدون ردیف دیتابیس، ساختاری فوتر)، خراب ۱، درست ۲؛ لینک خزش تکراری متن نوشته دوبار نمی‌آید', [ $lk_counts['wrong'] ?? -1, $lk_counts['broken'] ?? -1, $lk_counts['good'] ?? -1 ], [ 8, 1, 2 ] );
$lk_rows = (array) ( $lk_scan['rows'] ?? [] );
$lk_stores = array_column( $lk_rows, 'store' );
sort( $lk_stores );
hodima_ad( 'جای ذخیره هر ردیف: متن نوشته، توضیح دسته، لینک‌های مرتبط، منو، متن و ساختاری خزش', array_count_values( $lk_stores ), [ 'توضیح دسته' => 1, 'قالب (ساختاری)' => 1, 'لینک‌های مرتبط' => 1, 'متن (طبق خزش)' => 1, 'متن نوشته' => 3, 'منو' => 2 ] );
$lk_order = array_map( static fn( array $r ): int => $r['structural'] ? 2 : ( 'wrong' === $r['issue'] ? 0 : 1 ), $lk_rows );
$lk_sorted = $lk_order;
sort( $lk_sorted );
hodima_ad( 'ردیف‌ها به ترتیب: لینک قابل‌ویرایش اشتباه، خراب، ساختاری', $lk_order, $lk_sorted );
$lk_by = static fn( string $store ): array => array_values( array_filter( $lk_rows, static fn( array $r ): bool => $store === $r['store'] ) )[0] ?? [];
hodima_ad( 'لینک مرتبط: متن لینک عنوان دلخواه، مقصد /207/، آدرس نوشته‌شده همان آدرس اصلی', [ $lk_by( 'لینک‌های مرتبط' )['anchor'] ?? '', $lk_by( 'لینک‌های مرتبط' )['now']['path'] ?? '', $lk_by( 'لینک‌های مرتبط' )['source']['title'] ?? '' ], [ 'گیره انبری فلزی', '/207/', 'محصول با لینک مرتبط' ] );
$lk_struct = $lk_by( 'قالب (ساختاری)' );
hodima_ad( 'لینک ساختاری فوتر: یک ردیف برای ۳ صفحه، بدون دکمه ویرایش', [ $lk_struct['times'] ?? 0, $lk_struct['structural'] ?? null, $lk_struct['source']['edit'] ?? 'x' ], [ 3, true, '' ] );
hodima_ad( 'متن خزش بدون ردیف دیتابیس: میزبان /c-x/ با آدرس نوشته‌شده و یادداشت', [ $lk_by( 'متن (طبق خزش)' )['href'] ?? '', str_contains( (string) ( $lk_by( 'متن (طبق خزش)' )['now']['note'] ?? '' ), 'خزش دیده' ) ], [ '/207/', true ] );
hodima_ad( '/207 بدون اسلش به رقیب می‌رسد؛ /20700/ و دامنه دیگر قاطی نمی‌شود', [ in_array( '/20700/', array_column( array_column( $lk_rows, 'now' ), 'path' ), true ), array_values( array_unique( array_map( static fn( array $r ): string => (string) $r['now']['path'], array_filter( $lk_rows, static fn( array $r ): bool => 'wrong' === $r['issue'] ) ) ) ) ], [ false, [ '/207/' ] ] );
$lk_note = array_values( array_filter( $lk_rows, static fn( array $r ): bool => '/207' === $r['href'] ) )[0]['now']['note'] ?? '';
hodima_ad( 'آدرس نوشته‌شده /207 با آدرس اصلی فرق دارد ← یادداشت «ریدایرکت می‌شود»', str_contains( $lk_note, 'ریدایرکت' ), true );
hodima_ad( 'لینک‌های ورودی: رقیب ۳ متن (دو نوشته و توضیح دسته)، ۱ لینک مرتبط، ۱ منو (آیتم صفحه)؛ برنده ۲ متن', [ array_map( static fn( array $p ): array => [ $p['text'], $p['related'], $p['menu'] ], (array) ( $lk_scan['inventory'] ?? [] ) ) ], [ [ [ 2, 0, 0 ], [ 3, 1, 2 ] ] ] );
hodima_ad( 'لینک‌های ورودی طبق خزش: رقیب ۵ صفحه (۲ متن، ۳ ساختاری)', [ $lk_scan['has_crawl'] ?? false, $lk_scan['inventory'][1]['crawl'] ?? null ], [ true, [ 'total' => 5, 'content' => 2, 'other' => 3 ] ] );
hodima_ad( 'رابطه: رقیب داخل دسته برنده است (in_target)', array_column( (array) ( $lk_scan['relations'] ?? [] ), 'type' ), [ 'in_target' ] );
hodima_ad( 'فرصت لینک: نوشته‌ای که فقط کلمه را دارد، با تکه‌ای از متن و پیشنهاد', [ ( $lk_counts['missing'] ?? 0 ) >= 1, (array) ( $lk_scan['opps'][0]['snippet'] ?? [] ) ], [ true, [ 'یک ', 'گیره انبری فلزی', ' مشکی را ببینید و بخرید' ] ] );
$lk_titles = array_column( array_column( $lk_rows, 'source' ), 'title' );
hodima_ad( 'منبع‌ها: عنوان نوشته، توضیح دسته و منو در ردیف‌ها هست', [ in_array( 'راهنمای خرید گیره', $lk_titles, true ), in_array( 'دسته لینک', $lk_titles, true ), (bool) array_filter( $lk_titles, static fn( string $t ): bool => str_starts_with( $t, 'lk-menu' ) ) ], [ true, true, true ] );

// رابطه والد/فرزند/هم‌ردیف دسته‌ها
$lk_p = wp_insert_term( 'گیره مو', 'category', [ 'slug' => 'lk-par' ] );
$lk_c1 = wp_insert_term( 'گیره انبری', 'category', [ 'slug' => 'lk-ch1', 'parent' => (int) $lk_p['term_id'] ] );
$lk_c2 = wp_insert_term( 'گیره ماری', 'category', [ 'slug' => 'lk-ch2', 'parent' => (int) $lk_p['term_id'] ] );
$lk_info = static fn( array $t, string $title ): array => [ 'kind' => 'category', 'id' => (int) $t['term_id'], 'title' => $title ];
$lk_rel = static fn( array $a, array $b ): string => hodima_seo_advisor_links_relation( $a, $b, 'گیره مو انبری' )['type'] ?? '-';
hodima_ad( 'رابطه دسته‌ها: رقیب والد برنده، رقیب زیردسته برنده، دو زیردسته یک والد، بی‌ارتباط', [ $lk_rel( $lk_info( $lk_c1, 'گیره انبری' ), $lk_info( $lk_p, 'گیره مو' ) ), $lk_rel( $lk_info( $lk_p, 'گیره مو' ), $lk_info( $lk_c1, 'گیره انبری' ) ), $lk_rel( $lk_info( $lk_c1, 'گیره انبری' ), $lk_info( $lk_c2, 'گیره ماری' ) ), $lk_rel( $lk_info( $lk_p, 'گیره مو' ), [ 'kind' => 'category', 'id' => $lk_ids['cat'], 'title' => 'x' ] ) ], [ 'parent', 'child', 'sibling', '-' ] );
foreach ( [ $lk_c1, $lk_c2, $lk_p ] as $lk_t ) {
	wp_delete_term( (int) $lk_t['term_id'], 'category' );
}

// تکه متن
hodima_ad( 'تکه متن: عبارت کامل با نیم‌فاصله و ی/ک عربی، و بلندترین واژه وقتی عبارت کامل نیست', [ hodima_seo_advisor_links_snippet( '<p>این گیره‌ انبری فلزی خوب است</p>', 'گیره انبری فلزی' )[1] ?? null, hodima_seo_advisor_links_snippet( '<p>بهترین كليپس انبري</p>', 'کلیپس انبری' )[1] ?? null, hodima_seo_advisor_links_snippet( '<p>فقط انبری</p>', 'گیره مو انبری' )[1] ?? null, hodima_seo_advisor_links_snippet( '<p>هیچ</p>', 'گیره' ) ], [ 'گیره‌ انبری فلزی', 'كليپس انبري', 'انبری', null ] );

$lk_choose = hodima_seo_advisor_links_scan( 'گیره هم‌وزن', true );
hodima_ad( 'گروه هم‌وزن جهت ندارد (خطا با توضیح)', [ is_wp_error( $lk_choose ), is_wp_error( $lk_choose ) ? $lk_choose->get_error_code() : '' ], [ true, 'hodima_advisor_links_choose' ] );
hodima_ad( 'کلمه بدون رقابت خطا می‌دهد', is_wp_error( hodima_seo_advisor_links_scan( 'کلمه ناموجود', true ) ), true );
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-links-view.php';
$lk_html = hodima_seo_advisor_render_links_result( $lk_scan );
hodima_ad( 'نمای نتیجه: رابطه، لینک‌های ورودی، جدول ۷ ستونه با جای ذخیره و آدرس نوشته‌شده، فرصت لینک با تکه متن، دکمه ویرایش', [ str_contains( $lk_html, 'hodima-advisor__lk-rel' ), str_contains( $lk_html, 'لینک‌های ورودی هر صفحه' ), str_contains( $lk_html, 'جای ذخیره' ), str_contains( $lk_html, 'آدرس نوشته‌شده' ), str_contains( $lk_html, 'فرصت لینک' ), str_contains( $lk_html, '<mark>گیره انبری فلزی</mark>' ), str_contains( $lk_html, 'hodima-advisor__act' ), str_contains( $lk_html, 'در این صفحه' ) || str_contains( $lk_html, 'در 3 صفحه' ) || str_contains( $lk_html, 'در ۳ صفحه' ) ], [ true, true, true, true, true, true, true, true ] );
hodima_ad( 'نمای نتیجه: ۸ ردیف اول باز و بقیه (اگر بود) پشت «همه ردیف‌ها»؛ جدول لینک‌های ورودی + لینک‌ها + فرصت', substr_count( $lk_html, '<table' ) >= 3, true );
$lk_box = $cap( static fn() => hodima_seo_advisor_render_links_box( 'گیره <b>', 'دسته' ) );
hodima_ad( 'کادر: دکمه بررسی، کلمه escape‌شده در data-term', [ str_contains( $lk_box, 'data-hdsa-links-run' ), str_contains( $lk_box, 'data-term="گیره &lt;b&gt;"' ) ], [ true, true ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
wp_set_current_user( 1 );
$lk_tab = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'تب رقابت: بلوک لینک‌ها فقط برای «برنده روشن» (گروه هم‌وزن ندارد)؛ نشان نوع صفحه کنار رقیب', [ substr_count( $lk_tab, 'data-hdsa-links data-term' ), str_contains( $lk_tab, 'data-term="گیره انبری فلزی"' ), str_contains( $lk_tab, 'دسته نوشته' ) ], [ 1, true, true ] );
$_GET = [];

// admin-ajax: بدون nonce رد می‌شود، با nonce HTML نتیجه می‌آید
class Hodima_Lk_Stop extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function (): void { throw new Hodima_Lk_Stop(); } );
$lk_ajax = static function ( array $post ): array {
	$_POST    = $post;
	$_REQUEST = $post;
	ob_start();
	try {
		hodima_seo_advisor_handle_links_ajax();
	} catch ( Hodima_Lk_Stop ) {
		// wp_send_json_* بعد از چاپ پاسخ می‌میرد؛ در آزمون استثنا می‌شود
	}
	return (array) json_decode( (string) ob_get_clean(), true );
};
$lk_bad = $lk_ajax( [ 'term' => 'گیره انبری فلزی', 'nonce' => 'x' ] );
hodima_ad( 'admin-ajax: nonce غلط رد می‌شود', $lk_bad['success'] ?? null, false );
$lk_good = $lk_ajax( [ 'term' => 'گیره انبری فلزی', 'nonce' => wp_create_nonce( 'hodima_advisor_links' ) ] );
hodima_ad( 'admin-ajax: با nonce درست HTML جدول برمی‌گردد', [ $lk_good['success'] ?? null, str_contains( (string) ( $lk_good['data']['html'] ?? '' ), 'hodima-advisor__lk-table' ) ], [ true, true ] );
wp_set_current_user( 0 );
$lk_anon = $lk_ajax( [ 'term' => 'گیره انبری فلزی', 'nonce' => wp_create_nonce( 'hodima_advisor_links' ) ] );
hodima_ad( 'admin-ajax: بدون دسترسی مدیر رد می‌شود', $lk_anon['success'] ?? null, false );
wp_set_current_user( 1 );
remove_all_filters( 'wp_doing_ajax' );
remove_all_filters( 'wp_die_ajax_handler' );
$_POST    = [];
$_REQUEST = [];
hodima_lk_fixture_clean( $lk_ids );

echo "=== سبک‌سازی (SEO 2.9.9): ساخت دوباره در پس‌زمینه، خزش تک‌ردیفی، رقابت یک‌باره، صفحه‌بندی کارها\n";
$lt_tasks  = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
$lt_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
delete_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
hodima_seo_advisor_store( [ '/lt/a/' => [ 'clicks' => 10, 'impressions' => 8000, 'position' => 3.0 ], '/lt/b/' => [ 'clicks' => 2, 'impressions' => 500, 'position' => 4.0 ] ], [], [] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => 0 ], false );
hodima_seo_advisor_request_reanalyze();
hodima_ad( 'بدون داده دریافتی: درخواست ساخت دوباره ثبت نمی‌شود', [ hodima_seo_advisor_pipeline_pending(), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ false, false ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'property' => 'sc-domain:' . $host, 'days' => 90 ], false );
hodima_seo_advisor_request_reanalyze();
hodima_ad( 'درخواست ساخت دوباره: «در پس‌زمینه» و رویداد ادامه همین حالا', [ hodima_seo_advisor_pipeline_pending(), false !== wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ true, true ] );
$lt_q = hodima_seo_advisor_pipeline_take_reanalyze( [ 'stages' => [ 'ctr', 'breakdown' ], 'property' => 'p', 'range' => [], 'days' => 90 ] );
hodima_ad( 'دریافتی در میانه (پیش از analyze): analyze بعد از ctr می‌آید', $lt_q['stages'], [ 'ctr', 'analyze', 'breakdown' ] );
update_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, time(), false );
update_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, [ 'queue' => [ '/old/' ] ], false );
$lt_q = hodima_seo_advisor_pipeline_take_reanalyze( [ 'stages' => [ 'health', 'history' ], 'property' => 'p', 'range' => [], 'days' => 90 ] );
hodima_ad( 'نوبت analyze دریافت گذشته (خزش تازه وسط کار): analyze اول صف، صف تحلیل قدیمی دور ریخته و درخواست برداشته شد', [ $lt_q['stages'], get_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, 'none' ), get_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, 'none' ) ], [ [ 'analyze', 'health', 'history' ], 'none', 'none' ] );
delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
hodima_seo_advisor_request_reanalyze();
hodima_seo_advisor_pipeline_run( INF );
hodima_ad( 'اجرای نوبت‌ها: پیشنهادها ساخته شد، صف خالی و رویداد بی‌کار برداشته شد', [ hodima_seo_advisor_page( '/lt/a/' )['advice'][0]['rule'] ?? '', hodima_seo_advisor_pipeline_pending(), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ 'low_ctr', false, false ] );
hodima_ad( 'پیشنهاد ذخیره‌شده گام ندارد', array_key_exists( 'steps', hodima_seo_advisor_page( '/lt/a/' )['advice'][0] ?? [] ), false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'opportunities' ];
hodima_seo_advisor_request_reanalyze();
$lt_bar = $cap( static fn() => hodima_seo_advisor_render_toolbar() );
hodima_ad( 'نوار بالا: نشان «ساخت پیشنهادها در پس‌زمینه» وقتی کار مانده', str_contains( $lt_bar, 'ساخت پیشنهادها در پس‌زمینه ادامه دارد' ), true );
hodima_seo_advisor_clear_data();
hodima_ad( 'پاک کردن داده درخواست ساخت دوباره را هم برمی‌دارد', [ get_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, 'none' ), hodima_seo_advisor_pipeline_pending() ], [ 'none', false ] );

// خزش: کادر ویرایش فقط ردیف همان صفحه را می‌خواند؛ نتیجه با نقشه کامل یکی است
hodima_seo_advisor_crawl_store(
	[
		'/lt/a/'  => [ 'path' => '/lt/a/', 'status' => 200, 'indexable' => 1, 'reason' => '', 'title' => 'عنوان الف', 'title_len' => 40, 'meta_len' => 120, 'title_px' => hodima_ad_tpx( 40 ), 'meta_px' => hodima_ad_mpx( 120 ), 'h1' => 'الف', 'h2' => '', 'resp_ms' => 100, 'inlinks' => 4, 'text_links' => 2, 'depth' => 2, 'score' => 1.5, 'redirect' => '' ],
		'/lt/r/'  => [ 'path' => '/lt/r/', 'status' => 301, 'indexable' => 0, 'reason' => 'Redirected', 'title' => '', 'title_len' => 0, 'meta_len' => 0, 'title_px' => hodima_ad_tpx( 0 ), 'meta_px' => hodima_ad_mpx( 0 ), 'h1' => '', 'h2' => '', 'resp_ms' => 50, 'inlinks' => 1, 'text_links' => 0, 'depth' => 3, 'score' => 0.0, 'redirect' => '/lt/a/' ],
		'/lt/n/'  => [ 'path' => '/lt/n/', 'status' => 200, 'indexable' => 0, 'reason' => 'noindex', 'title' => 'ن', 'title_len' => 1, 'meta_len' => 0, 'title_px' => hodima_ad_tpx( 1 ), 'meta_px' => hodima_ad_mpx( 0 ), 'h1' => '', 'h2' => '', 'resp_ms' => 50, 'inlinks' => 1, 'text_links' => 0, 'depth' => 3, 'score' => 0.0, 'redirect' => '' ],
		'/lt/g/'  => [ 'path' => '/lt/g/', 'status' => 404, 'indexable' => 0, 'reason' => '', 'title' => '', 'title_len' => 0, 'meta_len' => 0, 'title_px' => hodima_ad_tpx( 0 ), 'meta_px' => hodima_ad_mpx( 0 ), 'h1' => '', 'h2' => '', 'resp_ms' => 50, 'inlinks' => 1, 'text_links' => 0, 'depth' => 3, 'score' => 0.0, 'redirect' => '' ],
	],
	[]
);
hodima_seo_advisor_crawl_map( true );
$lt_one = [ hodima_seo_advisor_crawl_row( '/lt/a/' ), hodima_seo_advisor_crawl_row( '/lt/none/' ) ];
$lt_map = hodima_seo_advisor_crawl_map();
hodima_ad( 'ردیف تک خزش همان ردیف نقشه کامل است؛ آدرس بیرون از خزش null', [ $lt_one[0] === ( $lt_map['/lt/a/'] ?? 'x' ), $lt_one[0]['title'] ?? '', $lt_one[1] ], [ true, 'عنوان الف', null ] );
hodima_seo_advisor_crawl_map( true );
$lt_res = hodima_seo_advisor_resolved_paths();
hodima_ad( 'حل‌شده‌ها از پرسش ردیف‌های ناسالم: ریدایرکت، ۴۰۴، noindex؛ صفحه سالم نه', [ $lt_res['/lt/r/'] ?? '', $lt_res['/lt/g/'] ?? '', $lt_res['/lt/n/'] ?? '', isset( $lt_res['/lt/a/'] ) ], [ 'redirect', 'gone', 'noindex', false ] );
hodima_seo_advisor_clear_data();

// رقابت: ساخت یک‌باره گروه‌ها و برداشتن نما همان نتیجه تابع قدیمی
hodima_seo_advisor_store(
	[ '/lt/c1/' => [ 'clicks' => 50, 'impressions' => 3000, 'position' => 4.0 ], '/lt/c2/' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 9.0 ], '/lt/c3/' => [ 'clicks' => 9, 'impressions' => 900, 'position' => 8.0 ] ],
	[],
	[
		'/lt/c1/' => [ 'گیره مو مخمل' => [ 'clicks' => 40, 'impressions' => 2500, 'position' => 4.0 ], 'تل سر ساتن' => [ 'clicks' => 3, 'impressions' => 400, 'position' => 9.0 ] ],
		'/lt/c2/' => [ 'گیره مو مخمل' => [ 'clicks' => 2, 'impressions' => 300, 'position' => 12.0 ] ],
		'/lt/c3/' => [ 'تل سر ساتن' => [ 'clicks' => 4, 'impressions' => 420, 'position' => 8.0 ] ],
	]
);
$lt_built = hodima_seo_advisor_competition_groups();
$lt_page  = hodima_seo_advisor_competition_select( $lt_built, [ 'limit' => 1, 'offset' => 1 ] );
$lt_old   = hodima_seo_advisor_competition( [ 'limit' => 1, 'offset' => 1 ] );
hodima_ad( 'رقابت: گروه‌ها یک بار ساخته و هر نما از آن برداشته می‌شود (همان نتیجه، ۲ گروه، صفحه دوم یک گروه)', [ $lt_page === $lt_old, $lt_page['total'], count( $lt_page['rows'] ), count( $lt_built['groups'] ) ], [ true, 2, 1, 2 ] );
hodima_ad( 'رقابت: فیلتر نوع دستور بعد از ساخت گروه‌ها', [ hodima_seo_advisor_competition_select( $lt_built, [ 'type' => 'strengthen' ] )['total'] + hodima_seo_advisor_competition_select( $lt_built, [ 'type' => 'choose' ] )['total'] + hodima_seo_advisor_competition_select( $lt_built, [ 'type' => 'category' ] )['total'] ], [ 2 ] );
// یک کار برای یک مشکل (SEO 3.7.8، خواست کاربر): «رقابت داخلی» پیشنهاد گوگل و کار هم‌کلمه برای همان صفحه و همان کلمه یکی می‌شوند
if ( function_exists( 'hodima_seo_advisor_analyze_all' ) ) {
	hodima_seo_advisor_analyze_all();
}
$mg_rows = [];
foreach ( hodima_seo_advisor_overview_build() as $mg_page ) {
	if ( str_starts_with( $mg_page['path'], '/lt/c' ) ) {
		$mg_rows[ $mg_page['path'] ] = array_values( array_filter( $mg_page['items'], static fn( array $i ): bool => 'cannibal' === ( $i['rule'] ?? '' ) || 'comp' === $i['src'] ) );
	}
}
$mg_per = array_map( static fn( array $items ): array => array_count_values( array_map( static fn( array $i ): string => hodima_seo_advisor_normalize( (string) ( $i['rule_term'] ?? $i['term'] ?? '' ) ), $items ) ), $mg_rows );
hodima_ad(
	'رقابت یکی‌شده: هر صفحه برای هر کلمه فقط یک کار رقابت (هم‌کلمه، با برچسب «رقابت داخلی» و نشان کلمه)',
	[ count( $mg_rows ) >= 2, max( array_map( static fn( array $c ): int => $c ? max( $c ) : 0, $mg_per ) ), array_unique( array_merge( ...array_map( static fn( array $items ): array => array_map( static fn( array $i ): string => $i['src'] . '|' . $i['label'], $items ), array_values( $mg_rows ) ) ) ) ],
	[ true, 1, [ 'comp|' . hodima_seo_advisor_rule_label( 'cannibal' ) ] ]
);
$mg_guide = [];
foreach ( hodima_seo_advisor_overview_build() as $mg_page ) {
	foreach ( '/lt/c2/' === $mg_page['path'] ? $mg_page['items'] : [] as $mg_i ) {
		if ( 'comp' === $mg_i['src'] ) {
			$mg_guide = $mg_i['guide'];
		}
	}
}
hodima_ad(
	'گزارش رقابت: کارت اول صفحه اصلی (برنده /lt/c1/، سبز)، بعد «همین صفحه»؛ نتیجه «صفحه اصلی نیست»',
	[ $mg_guide['report']['pages'][0]['path'] ?? '', $mg_guide['report']['pages'][0]['win'] ?? null, $mg_guide['report']['pages'][1]['badge'] ?? '', str_contains( (string) ( $mg_guide['report']['note']['text'] ?? '' ), 'صفحه اصلی نیست' ) ],
	[ '/lt/c1/', true, 'همین صفحه', true ]
);
// «دقیقا چه کنم» رقابت با داده خزش (SEO 3.8.0): عنوان فعلی و تیتر دارای کلمه نام برده می‌شوند؛ لینک انجام‌شده گام نمی‌گیرد
$ck_pages = hodima_seo_advisor_table( 'crawl_pages' );
$ck_links = hodima_seo_advisor_table( 'crawl_links' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i WHERE path IN (%s, %s)', $ck_pages, '/lt/c1/', '/lt/c2/' ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i WHERE source_path IN (%s, %s)', $ck_links, '/lt/c1/', '/lt/c2/' ) );
$GLOBALS['wpdb']->insert( $ck_pages, [ 'path' => '/lt/c1/', 'status' => 200, 'indexable' => 1, 'title' => 'گیره مو مخمل عمده', 'h2' => 'راهنمای خرید' ] );
$GLOBALS['wpdb']->insert( $ck_pages, [ 'path' => '/lt/c2/', 'status' => 200, 'indexable' => 1, 'title' => 'خرید گیره مو مخمل ارزان', 'h2' => 'مدل‌های گیره مو مخمل | ارسال' ] );
hodima_seo_advisor_crawl_map( true );
$ck_guide = static function ( string $path ): array {
	foreach ( hodima_seo_advisor_overview_build() as $p ) {
		foreach ( $path === $p['path'] ? $p['items'] : [] as $i ) {
			if ( 'comp' === $i['src'] ) {
				return $i['guide'];
			}
		}
	}
	return [];
};
$ck_lose = implode( ' / ', (array) ( $ck_guide( '/lt/c2/' )['steps'] ?? [] ) );
hodima_ad(
	'رقابت، صفحه بازنده با خزش: عنوان فعلی و تیتر ۲ دارای کلمه نام برده می‌شوند و گام لینک با آدرس صفحه اصلی می‌آید',
	[ str_contains( $ck_lose, 'عنوان فعلی «خرید گیره مو مخمل ارزان»' ), str_contains( $ck_lose, '«مدل‌های گیره مو مخمل»' ), str_contains( $ck_lose, '/lt/c1/' ) && str_contains( $ck_lose, 'لینک بدهید' ) ],
	[ true, true, true ]
);
$GLOBALS['wpdb']->insert( $ck_links, [ 'source_path' => '/lt/c2/', 'dest_path' => '/lt/c1/', 'link_position' => 'content', 'anchor' => 'گیره مو مخمل', 'is_follow' => 1 ] );
hodima_seo_advisor_crawl_map( true );
$ck_g2   = $ck_guide( '/lt/c2/' );
$ck_lose = implode( ' / ', (array) ( $ck_g2['steps'] ?? [] ) );
$ck_win  = implode( ' / ', (array) ( $ck_guide( '/lt/c1/' )['steps'] ?? [] ) );
hodima_ad(
	'رقابت با لینک انجام‌شده: بازنده گام لینک ندارد و کارتش «لینک دارد»؛ برنده «کاری نمانده» (رقیب با همین کلمه لینک داده)؛ عنوان برنده کلمه را دارد پس گام عنوان نیست',
	[ str_contains( $ck_lose, 'لینک بدهید' ), $ck_g2['report']['pages'][1]['linked'] ?? null, str_contains( $ck_win, 'کاری نمانده' ), str_contains( $ck_win, 'به ابتدای عنوان بیاورید' ) ],
	[ false, true, true, false ]
);
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i WHERE path IN (%s, %s)', $ck_pages, '/lt/c1/', '/lt/c2/' ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i WHERE source_path IN (%s, %s)', $ck_links, '/lt/c1/', '/lt/c2/' ) );
hodima_seo_advisor_crawl_map( true );
$mg_steps = hodima_seo_advisor_steps_for( '/lt/c2/', 'cannibal', 'گیره مو مخمل' );
hodima_ad( 'جزئیات کار رقابت یکی‌شده: جدول رقبا از آمار همان کلمه، صفحه مرجع /lt/c1/ (بیشترین کلیک)', [ $mg_steps[0]['type'] ?? '', $mg_steps[0]['main'] ?? '', $mg_steps[0]['is_main'] ?? null ], [ 'canonical', '/lt/c1/', false ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition', 'resolved' => '1' ];
$lt_warn = [];
set_error_handler( static function ( int $no, string $msg ) use ( &$lt_warn ): bool { $lt_warn[] = $msg; return true; }, E_WARNING );
$cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
restore_error_handler();
hodima_ad( 'نمای «رقابت‌های حل‌شده» بدون هشدار PHP (کلید settled)', $lt_warn, [] );

// صفحه‌بندی کارها
$lt_list = [];
for ( $i = 0; $i < 30; $i++ ) {
	$lt_list[ hodima_seo_advisor_task_key( "/lt/t{$i}/", 'low_ctr', '' ) ] = [ 'path' => "/lt/t{$i}/", 'rule' => 'low_ctr', 'term' => '', 'title' => "کار {$i}", 'status' => 'doing', 'at' => time() - $i * 60, 'fixed' => 0, 'back' => 0, 'check' => [] ];
}
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $lt_list, false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks' ];
$lt_p1 = $cap( static fn() => hodima_seo_advisor_render_tasks() );
$_GET['paged'] = '2';
$lt_p2 = $cap( static fn() => hodima_seo_advisor_render_tasks() );
$_GET = [];
hodima_ad( 'تب «کارها»: ۲۵ کار در صفحه اول با صفحه‌بندی، ۵ کار در صفحه دوم، تازه‌ترین اول', [ substr_count( $lt_p1, 'class="hodima-advisor__task"' ), str_contains( $lt_p1, 'hds-pager' ), substr_count( $lt_p2, 'class="hodima-advisor__task"' ), str_contains( $lt_p1, 'کار 0' ), str_contains( $lt_p2, 'کار 29' ) ], [ 25, true, 5, true, true ] );
hodima_ad( 'شمار کارهای باز بالای تب همه ۳۰ کار است (نه فقط صفحه فعلی)', str_contains( $lt_p2, '>30<' ) || str_contains( $lt_p2, '>۳۰<' ), true );

hodima_seo_advisor_clear_data();
false === $lt_tasks ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $lt_tasks, false );
false === $lt_status ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $lt_status, false );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );

echo "=== مرحله ۲ (SEO 3.0.0): صفحه مرجع یکسان با تب رقابت، آدرس کامل، قفل ایندکس، ترتیب نوبت‌ها\n";
$m2_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, false );
$m2_health = get_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, false );
hodima_seo_advisor_clear_data();
hodima_seo_advisor_store(
	[
		'/m2/clicks/' => [ 'clicks' => 90, 'impressions' => 3000, 'position' => 6.0 ],
		'/m2/rank/'   => [ 'clicks' => 10, 'impressions' => 1500, 'position' => 2.0 ],
		'/m2/moved/'  => [ 'clicks' => 200, 'impressions' => 4000, 'position' => 1.5 ],
		'/m2/me/'     => [ 'clicks' => 5, 'impressions' => 2000, 'position' => 9.0 ],
	],
	[],
	[
		'/m2/clicks/' => [ 'گیره مخملی آبی' => [ 'clicks' => 80, 'impressions' => 1500, 'position' => 6.0 ] ],
		'/m2/rank/'   => [ 'گیره مخملی آبی' => [ 'clicks' => 5, 'impressions' => 500, 'position' => 2.0 ] ],
		'/m2/moved/'  => [ 'گیره مخملی آبی' => [ 'clicks' => 150, 'impressions' => 2500, 'position' => 1.5 ] ],
		'/m2/me/'     => [ 'گیره مخملی آبی' => [ 'clicks' => 1, 'impressions' => 900, 'position' => 9.0 ] ],
	]
);
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'property' => 'sc-domain:' . $host, 'days' => 90 ], false );
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'router' => true, 'dead' => [ [ 'path' => '/m2/moved/', 'impressions' => 4000, 'clicks' => 200, 'state' => 'redirect', 'target' => '', 'code' => 301 ] ], 'total' => 0, 'seen' => 0, 'fresh' => 0, 'unseen' => [] ], false );
hodima_seo_advisor_crawl_map( true );
$m2_rivals = array_column( hodima_seo_advisor_term_rivals( 'گیره مخملی آبی' ), 'path' );
hodima_ad( 'رقیب‌های کلمه: صفحه ریدایرکت‌شده کنار می‌رود؛ برنده بیشترین کلیک (نه بهترین رتبه)', $m2_rivals, [ '/m2/clicks/', '/m2/rank/', '/m2/me/' ] );
$m2_step = hodima_seo_advisor_step_canonical( 'گیره مخملی آبی', hodima_seo_advisor_term_rivals( 'گیره مخملی آبی' ), '/m2/me/' );
$m2_comp = array_values( array_filter( hodima_seo_advisor_competition()['rows'], static fn( array $g ): bool => 'گیره مخملی آبی' === $g['term'] ) )[0] ?? [];
hodima_ad( 'صفحه مرجع کادر ویرایش = برنده تب «صفحه‌های هم‌کلمه»', [ $m2_step['main'], $m2_comp['pages'][0]['path'] ?? '' ], [ '/m2/clicks/', '/m2/clicks/' ] );
$m2_can = array_values( array_filter( hodima_seo_advisor_analyze( '/m2/me/' )['suggestions'] ?? [], static fn( array $i ): bool => 'cannibal' === $i['rule'] ) )[0] ?? [];
hodima_ad( 'متن رقابت داخلی آدرس کامل رقیب را دارد (نه فقط مسیر)', str_contains( (string) ( $m2_can['detail'] ?? '' ), hodima_seo_advisor_url( '/m2/clicks/' ) ), true );
hodima_ad( 'یادداشت لینک‌های رقیب با آدرس کامل', str_contains( hodima_seo_advisor_competition_links_note( 'ک', [ [ 'path' => '/w/', 'term_links' => 3 ], [ 'path' => '/r/', 'term_links' => 9 ] ] ), hodima_seo_advisor_url( '/r/' ) ), true );
hodima_ad( '«دیدن در سایت» برای صفحه ناشناخته با آدرس کامل سایت', hodima_seo_advisor_competition_page_link( [ 'kind' => '', 'object_id' => 0, 'path' => '/m2/x/' ] )[0], hodima_seo_advisor_url( '/m2/x/' ) );
hodima_ad( 'تکه متن فرصت لینک: واژه لاتین با حروف بزرگ هم پیدا می‌شود', hodima_seo_advisor_links_snippet( '<p>best Hair Clips here</p>', 'hair clips' )[1] ?? null, 'Hair Clips' );

// ایرادهای فنی: ریدایرکت با آدرس کامل مقصد
hodima_seo_advisor_crawl_store( [ '/m2/old/' => [ 'path' => '/m2/old/', 'status' => 301, 'indexable' => 0, 'reason' => '', 'title' => '', 'title_len' => 0, 'meta_len' => 0, 'title_px' => hodima_ad_tpx( 0 ), 'meta_px' => hodima_ad_mpx( 0 ), 'h1' => '', 'h2' => '', 'resp_ms' => 10, 'inlinks' => 2, 'text_links' => 0, 'depth' => 2, 'score' => 0.0, 'redirect' => '/m2/new/' ] ], [] );
$m2_issue = array_values( array_filter( hodima_seo_advisor_crawl_issues(), static fn( array $r ): bool => '/m2/old/' === $r['path'] ) )[0]['issues'][0] ?? [];
hodima_ad( 'ایراد ریدایرکت: مقصد جدا از جمله (برای آدرس کامل + کپی)', [ $m2_issue['detail'] ?? '', $m2_issue['url'] ?? '' ], [ 'به آدرس زیر می‌رود.', '/m2/new/' ] ); // SEO 3.6.6: دیگر «به این آدرس می‌رود:» نیست (دو نقطه معلق می‌ماند؛ آدرس در فهرست «مقصد ریدایرکت» زیر ایراد می‌آید)

// قفل بررسی ایندکس
add_option( HODIMA_SEO_ADVISOR_INSPECT_LOCK, time(), '', false );
$m2_before = get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, 'none' );
$m2_ins    = hodima_seo_advisor_inspect_batch();
hodima_ad( 'بررسی ایندکس هم‌زمان: دومی کاری نمی‌کند و نتیجه اولی را خراب نمی‌کند', [ str_contains( $m2_ins['error'], 'در حال اجراست' ), $m2_ins['checked'], get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, 'none' ) === $m2_before, false !== get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ) ], [ true, 0, true, false ] );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_LOCK );

hodima_seo_advisor_clear_data();
false === $m2_status ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $m2_status, false );
false === $m2_health ? delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION ) : update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, $m2_health, false );
hodima_seo_advisor_crawl_map( true );

echo "=== مرحله ۲ ظاهری (SEO 3.0.1): آدرس کامل + کپی، ایرادهای فنی کامل، صفحه‌بندی\n";
hodima_ad( 'آدرس کامل: مسیر ← دامنه + مسیر؛ کامل همان؛ //میزبان ← https؛ فارسی رمزگشایی', [ hodima_seo_advisor_url_full( '/x/' ), hodima_seo_advisor_url_full( 'https://e.test/a/' ), hodima_seo_advisor_url_full( '//e.test/a/' ), hodima_seo_advisor_url_full( '/' . rawurlencode( 'گل' ) . '/' ) ], [ hodima_seo_advisor_url( '/x/' ), 'https://e.test/a/', 'https://e.test/a/', hodima_seo_advisor_url( '/گل/' ) ] );
$uc_raw = hodima_seo_advisor_url_copy( '/گل-سر/' );
$uc     = str_replace( '<wbr>', '', $uc_raw );
hodima_ad( 'جزء آدرس: جای شکستن خط فقط بعد از «/» مسیر، نه داخل https://', [ substr_count( $uc_raw, '<wbr>' ), str_contains( $uc_raw, 'https:/<wbr>' ) ], [ 2, false ] );
hodima_ad( 'جزء آدرس: لینک چپ‌به‌راست جدا (bdi)، زبانه تازه، دکمه کپی با نام صفحه‌خوان و آدرس خوانا؛ بدون کپی فقط لینک', [ str_contains( $uc, 'dir="ltr"><bdi>' . hodima_seo_advisor_url( '/گل-سر/' ) . '</bdi>' ), str_contains( $uc, 'target="_blank"' ), str_contains( $uc, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/گل-سر/' ) . '"' ), str_contains( $uc, 'aria-label="کپی آدرس ' ), str_contains( hodima_seo_advisor_url_copy( '/x/', false ), 'data-hdsa-copy-url' ), hodima_seo_advisor_url_copy( '' ) ], [ true, true, true, true, false, '' ] );
$th = hodima_seo_advisor_text_html( 'علاوه بر این صفحه، https://e.test/a/ و <b> هم. آخر https://e.test/b/.' );
hodima_ad( 'آدرس داخل متن: لینک جدا از جمله، نقطه آخر جمله بیرون از آدرس، بقیه متن escape', [ substr_count( $th, 'class="hodima-advisor-url__inline"' ), str_contains( $th, 'href="https://e.test/b/"' ) && str_contains( $th, '</a>.' ), str_contains( $th, '&lt;b&gt;' ), hodima_seo_advisor_text_html( 'بدون آدرس <i>' ) ], [ 2, true, true, 'بدون آدرس &lt;i&gt;' ] );

// لینک‌های این کلمه: «باید برود به» و «آدرس نوشته‌شده» کامل
$lk_target = [ 'path' => '/lk3/target/', 'title' => 'هدف' ];
$lk_row    = [ 'issue' => 'wrong', 'source' => [ 'title' => 'منبع', 'label' => 'برگه', 'edit' => '', 'field' => 'متن نوشته' ], 'store' => 'متن نوشته', 'anchor' => 'کلمه', 'href' => '/207/', 'now' => [ 'path' => '/207/', 'title' => 'رقیب', 'kind' => 'نوشته', 'note' => '' ], 'times' => 1, 'structural' => false ];
$lk_html   = hodima_seo_advisor_render_links_row( $lk_row, $lk_target );
hodima_ad( 'ردیف لینک: آدرس نوشته‌شده کامل + «در متن نسبی نوشته شده» و «باید برود به» آدرس کامل با کپی', [ str_contains( $lk_html, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/207/' ) . '"' ), str_contains( $lk_html, 'نوشته شده (نسبی)' ), str_contains( $lk_html, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/lk3/target/' ) . '"' ) ], [ true, true, true ] );
$lk_crawl = hodima_seo_advisor_render_links_row( [ 'source' => [ 'title' => 'x', 'label' => 'صفحه', 'edit' => '', 'field' => 'متن (طبق خزش)' ] ] + $lk_row, $lk_target );
$lk_opp   = hodima_seo_advisor_render_links_opp( [ 'source' => [ 'title' => 'منبع', 'label' => 'برگه', 'edit' => '' ], 'snippet' => null ], $lk_target );
hodima_ad( 'ردیف خزش: بدون «نسبی» (خزش همیشه مسیر است)؛ فرصت لینک: «همین عبارت را به این آدرس لینک کنید» + آدرس کامل', [ str_contains( $lk_crawl, 'نسبی' ), str_contains( $lk_opp, 'لینک کنید' ) && str_contains( $lk_opp, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/lk3/target/' ) . '"' ) ], [ false, true ] );

// ایرادهای فنی: همه ایرادها با دستور خودشان، ویرایش صفحه، مقصد ریدایرکت با کپی
$m3_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, false );
hodima_seo_advisor_clear_data();
$m3_post = (int) wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'صفحه پرایراد', 'post_name' => 'm3-many', 'post_content' => 'x' ] );
$m3_path = (string) hodima_seo_advisor_path_key( (string) get_permalink( $m3_post ) );
hodima_seo_advisor_crawl_store(
	[
		$m3_path    => [ 'path' => $m3_path, 'status' => 200, 'indexable' => 1, 'reason' => '', 'title' => 'کوتاه', 'title_len' => 5, 'meta_len' => 0, 'title_px' => hodima_ad_tpx( 5 ), 'meta_px' => hodima_ad_mpx( 0 ), 'h1' => '', 'h2' => '', 'resp_ms' => 2500, 'inlinks' => 1, 'text_links' => 0, 'depth' => 2, 'score' => 0.0, 'redirect' => '' ],
		'/m3/old/'  => [ 'path' => '/m3/old/', 'status' => 301, 'indexable' => 0, 'reason' => '', 'title' => '', 'title_len' => 0, 'meta_len' => 0, 'title_px' => hodima_ad_tpx( 0 ), 'meta_px' => hodima_ad_mpx( 0 ), 'h1' => '', 'h2' => '', 'resp_ms' => 10, 'inlinks' => 2, 'text_links' => 0, 'depth' => 2, 'score' => 0.0, 'redirect' => '/m3/new/' ],
	],
	[]
);
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => 2, 'links' => 0, 'files' => [] ], false );
hodima_seo_advisor_crawl_map( true );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
$m3_html = $cap( static fn() => hodima_seo_advisor_render_overview() );
$_GET = [];
hodima_ad( '«کارها»: هر ۵ ایراد صفحه + ریدایرکت یک خط با نشان و دستور خودش (نه فقط ۳)، «ویرایش صفحه»', [ substr_count( $m3_html, 'class="hodima-advisor__ov-item"' ), str_contains( $m3_html, 'درباره «کش صفحه» سرور' ) && str_contains( $m3_html, 'یک لینک با نام این صفحه بدهید' ) && str_contains( $m3_html, 'class="hodima-advisor__steps-list"' ) && str_contains( $m3_html, 'class="hodima-advisor__verify"' ) /* SEO 3.6.9: راهنمای پنج‌بخشی (گام‌ها + «از کجا بفهمم») */, str_contains( $m3_html, 'post=' . $m3_post ) ], [ 6, true, true ] );
wp_delete_post( $m3_post, true );

// صفحه‌بندی «صفحه‌هایی که نمایش نگرفته‌اند» و «وضعیت در گوگل»
$m3_unseen = [];
for ( $i = 0; $i < 60; $i++ ) {
	$m3_unseen[] = [ 'id' => 0, 'path' => "/m3/u{$i}/", 'title' => "بدون نمایش {$i}", 'type' => 'post', 'days' => 400 - $i ];
}
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'router' => true, 'dead' => [], 'total' => 100, 'seen' => 40, 'fresh' => 0, 'unseen' => $m3_unseen ], false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'health', 'paged' => '3' ];
$m3_h = $cap( static fn() => hodima_seo_advisor_render_health( hodima_seo_advisor_status() ) );
$m3_ins = [];
for ( $i = 0; $i < 30; $i++ ) {
	$m3_ins[ "/m3/i{$i}/" ] = [ 'verdict' => 'NEUTRAL', 'coverage' => 'Crawled - currently not indexed', 'robots' => '', 'indexing' => '', 'fetch' => '', 'last_crawl' => '', 'google_canonical' => '', 'user_canonical' => '', 'crawled_as' => '', 'checked' => time() - $i ];
}
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $m3_ins, false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'indexing', 'paged' => '2' ];
$m3_i = $cap( static fn() => hodima_seo_advisor_render_indexing() );
$_GET = [];
hodima_ad( 'صفحه‌بندی: بدون نمایش ۶۰ ← صفحه سوم ۱۰ ردیف (نه فقط ۳۰ اول)؛ ایندکس ۳۰ ← صفحه دوم ۵ ردیف', [ str_contains( $m3_h, 'بدون نمایش 59' ) && ! str_contains( $m3_h, 'بدون نمایش 49<' ), str_contains( $m3_h, 'hds-pager' ), substr_count( $m3_i, 'm3/i' ) >= 5 && str_contains( $m3_i, 'hds-pager' ) ], [ true, true, true ] );
hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION );
false === $m3_status ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $m3_status, false );
wp_set_current_user( 0 );

/** وضعیت چرخه عمر یک کار یا رشته خالی (آزمون مرحله ۳). */
function hodima_seo_advisor_state_or( ?array $task ): string {
	return null === $task ? '' : hodima_seo_advisor_task_state( $task );
}
