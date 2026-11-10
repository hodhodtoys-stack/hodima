<?php
/**
 * بخش 5 از آزمون‌های دستار سئو (tools/wp-harness/advisor-tests.php آن را به ترتیب و در همان دامنه سراسری می‌خواند):
 * «انجام شد / رد شد» در همه تب‌ها، سنجش کارها و خزنده داخلی (سایت ساختگی، مکث، ۴۲۹).
 * تنهایی اجرا نمی‌شود: متغیرها و توابع کمکی (hodima_ad، $cap، …) از فایل اصلی و بخش‌های قبلی می‌آیند.
 */

declare(strict_types=1);

echo "=== مرحله ۳ (SEO 3.0.2): «انجام شد / رد شد» در همه تب‌ها و سنجش هر نوع کار\n";
$p3_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, false );
$p3_tasks  = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
wp_set_current_user( 1 );

hodima_ad( 'منبع هر کار از قاعده‌اش؛ قاعده با رقم (crawl_no_h1) پذیرفته می‌شود', [ hodima_seo_advisor_task_source( 'crawl_no_h1' ), hodima_seo_advisor_task_source( 'competition' ), hodima_seo_advisor_task_source( 'health_unseen' ), hodima_seo_advisor_task_source( 'index_problem' ), hodima_seo_advisor_task_source( 'low_ctr' ), hodima_seo_advisor_task_set( '/p3/x/', 'crawl_no_h1', '', 'بدون H1', 'done' ) ], [ 'crawl', 'competition', 'health', 'indexing', 'opportunities', true ] );
hodima_seo_advisor_task_set( '/p3/x/', 'low_ctr', '', 'نرخ کلیک', 'done' );
hodima_seo_advisor_task_set( '/p3/x/', 'missing_headings', 'پلومریا', 'کلمه در تیترها نیست', 'done' );
hodima_seo_advisor_tasks_sync( '/p3/x/', [] );
hodima_ad( 'تحلیل آدرس فقط کار متنی پیشنهادها را «برطرف» می‌کند؛ آماری منتظر داده گوگل می‌ماند و ایراد فنی همان آدرس دست نمی‌خورد', [ hodima_seo_advisor_task_state( hodima_seo_advisor_task( '/p3/x/', 'missing_headings', 'پلومریا' ) ), hodima_seo_advisor_task_state( hodima_seo_advisor_task( '/p3/x/', 'low_ctr', '' ) ), hodima_seo_advisor_task_state( hodima_seo_advisor_task( '/p3/x/', 'crawl_no_h1', '' ) ) ], [ 'fixed', 'waiting', 'waiting' ] );

// نوشتن امن: حکم سنجش روی نسخه تازه کارها می‌نشیند و کار تغییرکرده توسط مدیر را پس نمی‌زند (SEO 3.0.3)
$p3_key_a = hodima_seo_advisor_task_key( '/p3/x/', 'low_ctr', '' );
$p3_seen  = [ $p3_key_a => [ 'at' => hodima_seo_advisor_tasks()[ $p3_key_a ]['at'], 'status' => 'done' ] ];
hodima_seo_advisor_task_set( '/p3/y/', 'low_ctr', '', 'کار تازه', 'done' ); // مدیر وسط سنجش کار دیگری ثبت کرد
$p3_written = hodima_seo_advisor_tasks_patch( [ $p3_key_a => [ 'check' => [ 'verdict' => 'no_effect', 'at' => time() ] ] ], $p3_seen );
hodima_ad( 'حکم سنجش نوشته می‌شود و کار تازه‌ای که وسط سنجش ثبت شد نمی‌پرد', [ $p3_written, hodima_seo_advisor_tasks()[ $p3_key_a ]['check']['verdict'] ?? '', null !== hodima_seo_advisor_task( '/p3/y/', 'low_ctr', '' ) ], [ 1, 'no_effect', true ] );
hodima_seo_advisor_task_set( '/p3/x/', 'low_ctr', '', 'نرخ کلیک', 'rejected' ); // مدیر وسط سنجش «لازم نیست» زد
$p3_written = hodima_seo_advisor_tasks_patch( [ $p3_key_a => [ 'fixed' => time() ] ], $p3_seen );
hodima_ad( 'کاری که مدیر وسط سنجش وضعیتش را عوض کرد حکم کهنه نمی‌گیرد', [ $p3_written, hodima_seo_advisor_task_state( hodima_seo_advisor_task( '/p3/x/', 'low_ctr', '' ) ) ], [ 0, 'rejected' ] );

// صفحه‌بندی آرشیو (خزش واقعی هدهدلی): H1 مشترک و لینک ورودی کم ایراد نیست (SEO 3.0.5)
$p3_pg = static fn( string $path, string $h1, int $inlinks ): array => [ 'path' => $path, 'status' => 200, 'indexable' => 1, 'reason' => '', 'title' => 'وبلاگ ' . substr( md5( $path ), 0, 6 ), 'title_len' => 40, 'meta_len' => 120, 'title_px' => hodima_ad_tpx( 40 ), 'meta_px' => hodima_ad_mpx( 120 ), 'h1' => $h1, 'h2' => '', 'resp_ms' => 100, 'inlinks' => $inlinks, 'text_links' => 1, 'depth' => 1, 'score' => 0.0, 'redirect' => '' ];
hodima_seo_advisor_crawl_store( [ '/pg/' => $p3_pg( '/pg/', 'وبلاگ', 30 ), '/pg/page/2/' => $p3_pg( '/pg/page/2/', 'وبلاگ', 2 ), '/pg/a/' => $p3_pg( '/pg/a/', 'مقاله الف', 30 ), '/pg/b/' => $p3_pg( '/pg/b/', 'مقاله الف', 30 ) ], [] );
$p3_codes = [];
foreach ( hodima_seo_advisor_crawl_issues() as $p3_r ) {
	if ( str_starts_with( (string) $p3_r['path'], '/pg/' ) ) {
		$p3_codes[ $p3_r['path'] ] = array_column( $p3_r['issues'], 'code' );
	}
}
hodima_ad( 'صفحه‌بندی آرشیو H1 تکراری و لینک ورودی کم نمی‌گیرد؛ دو مقاله با H1 یکسان می‌گیرند', $p3_codes, [ '/pg/a/' => [ 'dup_h1' ], '/pg/b/' => [ 'dup_h1' ] ] );

// ایراد «متن زیر عنوان ممنوع» (SEO 3.0.6؛ خزش واقعی: ۸ محصول max-snippet:0)
hodima_ad( 'دستور robots: max-snippet:0 و nosnippet ممنوع؛ max-snippet:-1 و ۰۵ و ۱۶۰ نه', array_map( static fn( string $v ): bool => hodima_seo_advisor_robots_no_snippet( $v ), [ 'index, follow, max-snippet:0, max-image-preview:large', 'noindex, nosnippet', 'max-snippet: 0', 'index, max-snippet:-1', 'max-snippet:05', 'max-snippet:160', '' ] ), [ true, true, true, false, false, false, false ] );
hodima_ad( 'دستور robots در هدر X-Robots-Tag هم دیده می‌شود', hodima_seo_advisor_robots_no_snippet( 'index', 'nosnippet' ), true );
$p3_csv = tempnam( sys_get_temp_dir(), 'crawl' );
file_put_contents( $p3_csv, "\xEF\xBB\xBF\"Address\",\"Status Code\",\"Indexability\",\"Title 1\",\"Meta Robots 1\"\n\"" . home_url( '/ns/a/' ) . "\",\"200\",\"Indexable\",\"عنوان\",\"index, follow, max-snippet:0\"\n\"" . home_url( '/ns/b/' ) . "\",\"200\",\"Indexable\",\"عنوان\",\"index, follow, max-snippet:-1\"\n" );
$p3_parsed = hodima_seo_advisor_crawl_parse_pages( $p3_csv );
unlink( $p3_csv );
hodima_ad( 'خواندن CSV: ستون Meta Robots 1 ← no_snippet فقط برای صفحه max-snippet:0', is_array( $p3_parsed ) ? [ $p3_parsed['/ns/a/']['no_snippet'] ?? null, $p3_parsed['/ns/b/']['no_snippet'] ?? null ] : $p3_parsed, [ 1, 0 ] );
$p3_ns = $p3_pg( '/ns/a/', 'الف', 30 ) + [ 'no_snippet' => 1 ];
hodima_seo_advisor_crawl_store( [ '/ns/a/' => $p3_ns, '/ns/b/' => $p3_pg( '/ns/b/', 'ب', 30 ) ], [] );
$p3_codes = [];
foreach ( hodima_seo_advisor_crawl_issues() as $p3_r ) {
	if ( str_starts_with( (string) $p3_r['path'], '/ns/' ) ) {
		$p3_codes[ $p3_r['path'] ] = array_column( $p3_r['issues'], 'code' );
	}
}
hodima_ad( 'ایراد no_snippet فقط برای صفحه دارای max-snippet:0؛ برچسب و دستور فارسی', [ $p3_codes, hodima_seo_advisor_crawl_issue_info( 'no_snippet' )['label'], in_array( 'no_snippet', HODIMA_SEO_ADVISOR_CRAWL_GROUPS['meta'], true ) ], [ [ '/ns/a/' => [ 'no_snippet' ] ], 'گوگل اجازه نشان دادن خلاصه ندارد', true ] ); // SEO 3.6.9: برچسب ساده‌تر
hodima_ad( 'سنجش فوری: no_snippet با خواندن دوباره صفحه از سایت سنجیده می‌شود', in_array( 'no_snippet', HODIMA_SEO_ADVISOR_MARK_LIVE_CODES, true ), true );

// نرخ کلیک کلمه‌به‌کلمه (SEO 3.0.4): یک کلمه خوب نباید کمبود کلمه بد را بپوشاند
$p3_table = static fn(): array => [ 2 => 25.0, 3 => 14.0, 5 => 9.0, 7 => 5.5, 10 => 3.5, 20 => 1.5 ];
add_filter( 'hodima_seo_advisor_ctr_table', $p3_table );
$p3_q   = static fn( string $term, int $clicks, int $impressions, float $position ): array => [ 'term' => $term, 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
$p3_row = [ 'path' => '/p3/gap/', 'clicks' => 300, 'impressions' => 6000, 'position' => 5.3, 'prev_clicks' => 300 ];
$p3_two = [ $p3_q( 'کلمه خوب', 250, 1000, 2.0 ), $p3_q( 'کلمه بد', 50, 5000, 6.0 ) ];
$p3_gap = hodima_seo_advisor_ctr_gap( $p3_two, 6000, 20.0 );
hodima_ad( 'کمبود کلیک کلمه‌به‌کلمه: ۵۲۵ انتظار در برابر ۳۰۰ واقعی ← ۲۲۵؛ بدترین کلمه «کلمه بد»', [ $p3_gap['gain'] ?? null, $p3_gap['worst']['term'] ?? null ], [ 225, 'کلمه بد' ] );
$p3_low = array_values( array_filter( hodima_seo_advisor_suggestions( $p3_row, $p3_two, null ), static fn( array $i ): bool => 'low_ctr' === $i['rule'] ) );
hodima_ad( 'پیشنهاد نرخ کلیک با حساب کلمه‌ای: کلیک بالقوه ۲۲۵ و کلمه کم‌کلیک در متن (حساب قدیمی کل صفحه فقط ۳۰ می‌داد)', [ $p3_low[0]['potential'] ?? null, str_contains( (string) ( $p3_low[0]['detail'] ?? '' ), '«کلمه بد»' ) ], [ 225, true ] );
hodima_ad( 'کلمه‌های ذخیره‌شده کمتر از نیمی از نمایش صفحه ← حساب قدیمی کل صفحه (null)', hodima_seo_advisor_ctr_gap( [ $p3_q( 'کم', 5, 1000, 5.0 ) ], 6000, 20.0 ), null );
hodima_ad( 'نمایش صفحه بیشتر بیرون ۲۰ نتیجه اول است ← بدون کمبود (نرخ معمول رتبه ۴۰ معنا ندارد)', hodima_seo_advisor_ctr_gap( [ $p3_q( 'عقب', 1, 5000, 40.0 ), $p3_q( 'جلو', 0, 500, 3.0 ) ], 6000, 20.0 )['gain'] ?? null, 0 );
remove_filter( 'hodima_seo_advisor_ctr_table', $p3_table );

delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
hodima_ad( 'سقف کارها ۳۰۰۰ (ایرادهای فنی هر کدام یک کار؛ SEO 3.4.7)', HODIMA_SEO_ADVISOR_TASKS_MAX, 3000 );

// فرم ردیف: admin-post با ریدایرکت (در آزمون استثنا به‌جای exit)؛ پیام بالای صفحه فقط در پیشخوان لود می‌شود
if ( ! function_exists( 'hodima_admin_flash' ) ) {
	require_once HODIMA_SEO_DIR . '/inc/admin-ui-fallback.php';
}
class Hodima_P3_Redirect extends Exception {}
$p3_redirect = static function ( string $location ): string { throw new Hodima_P3_Redirect( $location ); };
add_filter( 'wp_redirect', $p3_redirect, 1 );
$p3_mark = static function ( array $post ): string {
	$_POST    = $post + [ '_wpnonce' => wp_create_nonce( 'hodima_advisor_mark' ), 'action' => 'hodima_advisor_mark' ];
	$_REQUEST = $_POST;
	delete_transient( 'hodima_admin_flash_' . get_current_user_id() );
	try {
		hodima_seo_advisor_handle_mark();
	} catch ( Hodima_P3_Redirect ) {
		// پایان عادی: ریدایرکت به صفحه قبل
	}
	$_POST    = [];
	$_REQUEST = [];
	$flash    = get_transient( 'hodima_admin_flash_' . get_current_user_id() );
	return is_array( $flash ) ? implode( ' | ', array_column( $flash, 1 ) ) : '';
};

// ایرادهای فنی: یک صفحه پرایراد، یک صفحه ۴۰۴ با لینک ورودی، یک ریدایرکت فقط با نمایش گوگل
$p3_post = (int) wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'صفحه مرحله سه', 'post_name' => 'p3-crawl', 'post_content' => 'x' ] );
$p3_path = (string) hodima_seo_advisor_path_key( (string) get_permalink( $p3_post ) );
$p3_row  = static fn( string $path, int $status, array $over = [] ): array => $over + [ 'path' => $path, 'status' => $status, 'indexable' => 200 === $status ? 1 : 0, 'reason' => '', 'title' => 'عنوان', 'title_len' => 5, 'meta_len' => 0, 'title_px' => hodima_ad_tpx( 5 ), 'meta_px' => hodima_ad_mpx( 0 ), 'h1' => '', 'h2' => '', 'resp_ms' => 2600, 'inlinks' => 1, 'text_links' => 0, 'depth' => 2, 'score' => 0.0, 'redirect' => '' ];
hodima_seo_advisor_store( [ '/p3/gone/' => [ 'clicks' => 0, 'impressions' => 400, 'position' => 9.0 ], '/p3/moved/' => [ 'clicks' => 0, 'impressions' => 400, 'position' => 9.0 ] ], [], [] );
hodima_seo_advisor_crawl_store(
	[
		$p3_path      => $p3_row( $p3_path, 200 ),
		'/p3/gone/'   => $p3_row( '/p3/gone/', 404, [ 'inlinks' => 2 ] ),
		'/p3/moved/'  => $p3_row( '/p3/moved/', 301, [ 'inlinks' => 0, 'redirect' => '/p3/new/' ] ),
	],
	[]
);
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time() - 60, 'pages' => 3, 'links' => 0, 'files' => [] ], false );
hodima_seo_advisor_crawl_map( true );
$p3_codes = static function ( string $path ): array {
	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		if ( $row['path'] === $path ) {
			return array_column( $row['issues'], 'code' );
		}
	}
	return [];
};
hodima_ad( 'ریدایرکت فقط با لینک داخلی ایراد است (فقط با نمایش گوگل هیچ‌وقت برطرف نمی‌شد)؛ ۴۰۴ با لینک ورودی «خطا»', [ $p3_codes( '/p3/moved/' ), $p3_codes( '/p3/gone/' ) ], [ [], [ 'broken' ] ] );

$p3_code = 200;
$p3_html = '<html><head><title>' . str_repeat( 'ع', 52 ) . '</title><meta name="description" content="' . str_repeat( 'م', 130 ) . '"></head><body><h1>تیتر تازه</h1></body></html>';
$p3_head = [];
$p3_hits = 0;
$p3_live = static function ( $pre, array $args, string $url ) use ( &$p3_code, &$p3_html, &$p3_head, &$p3_hits ) {
	if ( ! str_contains( $url, HODIMA_SEO_ADVISOR_LIVE_PARAM . '=' ) ) {
		return $pre;
	}
	++$p3_hits;
	return 0 === $p3_code ? new WP_Error( 'http_request_failed', 'x' ) : [ 'headers' => [ 'content-type' => 'text/html' ] + $p3_head, 'body' => $p3_html, 'response' => [ 'code' => $p3_code, 'message' => '' ], 'cookies' => [], 'filename' => null ];
};
add_filter( 'pre_http_request', $p3_live, 30, 3 );

$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
$p3_c0 = $cap( static fn() => hodima_seo_advisor_render_overview() );
hodima_ad( '«کارها»: هر ایراد فرم خودش (انجام دادم / لازم نیست) با نام صفحه‌خوان؛ nonce بدون شناسه تکراری', [ substr_count( $p3_c0, 'class="hodima-advisor__ov-item"' ) >= 5, str_contains( $p3_c0, 'name="action" value="hodima_advisor_mark"' ), str_contains( $p3_c0, 'aria-label="انجام دادم: صفحه مرحله سه' ), str_contains( $p3_c0, 'value="rejected"' ), substr_count( $p3_c0, 'id="_wpnonce"' ) ], [ true, true, true, true, 0 ] );

$p3_flash = $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_meta', 'no_h1', 'title_short', 'slow', 'nope' ] ] );
$p3_st    = static fn( string $code ): string => ( $t = hodima_seo_advisor_task( $p3_path, 'crawl_' . $code, '' ) ) ? hodima_seo_advisor_task_state( $t ) : '';
hodima_ad( '«انجام دادم» روی ۴ ایراد (کد نامعتبر کنار): صفحه همان لحظه خوانده شد؛ متا، H1 و عنوان برطرف، «کند» منتظر خزش تازه', [ $p3_hits, $p3_st( 'no_meta' ), $p3_st( 'no_h1' ), $p3_st( 'title_short' ), $p3_st( 'slow' ), $p3_st( 'nope' ), hodima_seo_advisor_task( $p3_path, 'crawl_no_meta', '' )['title'] ?? '' ], [ 1, 'fixed', 'fixed', 'fixed', 'waiting', '', 'بدون توضیح متا' ] );
hodima_ad( 'پیام نتیجه: شمار، برطرف‌ها، منتظر خزش تازه و پیوند «کارها»', [ str_contains( $p3_flash, '4 مورد «انجام دادم» ثبت شد' ), str_contains( $p3_flash, '3 برطرف شد' ), str_contains( $p3_flash, '1 منتظر نتیجه (کند بودن، لینک ورودی و ریدایرکت با خزش تازه' ), str_contains( $p3_flash, 'tab=tasks' ) ], [ true, true, true, true ] );
hodima_ad( 'برچسب منتظر کار خزش و برچسب «هنوز مانده» هر نوع', [ hodima_seo_advisor_task_view( hodima_seo_advisor_task( $p3_path, 'crawl_slow', '' ) )['label'], hodima_seo_advisor_task_still_label( 'crawl_no_meta' ), hodima_seo_advisor_task_still_label( 'health_dead' ) ], [ 'انجام شد · منتظر خزش تازه', 'انجام شد؛ هنوز دیده می‌شود', 'هنوز خراب است' ] );

$p3_c1 = $cap( static fn() => hodima_seo_advisor_render_overview() );
hodima_ad( 'برطرف‌شده‌ها دیگر در «کارها» نیستند؛ «کند» (منتظر) پنهان؛ ایراد باز (لینک ورودی کم) می‌ماند؛ علامت‌خورده‌ها در «کارهای انجام‌شده»', [ str_contains( $p3_c1, 'بدون توضیح متا' ), str_contains( $p3_c1, 'منتظر خزش تازه ·' ), str_contains( $p3_c1, 'لینک ورودی کم' ), str_contains( $p3_c1, 'نمایش 1 ایراد علامت‌خورده' ) ], [ false, false, true, false ] );
$_GET = [];

// خزش تازه: کند هنوز هست ← هنوز مانده؛ بعد سریع ← برطرف؛ دوباره کند ← برگشت
hodima_seo_advisor_tasks_sync_crawl();
$p3_a = $p3_st( 'slow' );
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'resp_ms' => 200 ], [ 'path' => $p3_path ] );
hodima_seo_advisor_crawl_map( true );
hodima_seo_advisor_tasks_sync_crawl();
$p3_b = $p3_st( 'slow' );
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'resp_ms' => 3000 ], [ 'path' => $p3_path ] );
hodima_seo_advisor_crawl_map( true );
hodima_seo_advisor_tasks_sync_crawl();
hodima_ad( 'خزش تازه: «کند» هنوز هست ← هنوز مانده (باز)؛ برطرف؛ دوباره کند ← برگشت', [ $p3_a, $p3_b, $p3_st( 'slow' ), hodima_seo_advisor_task_group( hodima_seo_advisor_task( $p3_path, 'crawl_slow', '' ) ) ], [ 'still', 'fixed', 'back', 'open' ] );

// متن هنوز در صفحه: «انجام دادم» دوباره ← هنوز مانده؛ شبکه نرسید ← منتظر
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'meta_len' => 0, 'meta_px' => 0 ], [ 'path' => $p3_path ] );
hodima_seo_advisor_crawl_map( true );
$p3_html = '<html><head><title>' . str_repeat( 'ع', 52 ) . '</title></head><body><h1>تیتر تازه</h1></body></html>';
$p3_flash2 = $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_meta' ] ] );
hodima_ad( 'متا هنوز نیست: کار «هنوز دیده می‌شود» و به «در دست انجام» می‌رود؛ پیام همین را می‌گوید', [ $p3_st( 'no_meta' ), hodima_seo_advisor_task_group( hodima_seo_advisor_task( $p3_path, 'crawl_no_meta', '' ) ), str_contains( $p3_flash2, '1 هنوز دیده می‌شود' ) ], [ 'still', 'open', true ] );
$p3_code = 0;
$p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_meta' ] ] );
hodima_ad( 'صفحه از سایت خوانده نشد ← «منتظر خزش تازه» (نه برطرف و نه مانده)', $p3_st( 'no_meta' ), 'waiting' );

// خطای ۴۰۴: «انجام دادم» ← درخواست؛ حالا ۳۰۱ ← کد در ردیف خزش، «خطا» برطرف
$p3_code = 301;
$p3_head = [ 'location' => hodima_seo_advisor_url( '/p3/new/' ) ];
$p3_mark( [ 'source' => 'crawl', 'path' => '/p3/gone/', 'status' => 'done', 'items' => [ 'broken' ] ] );
hodima_ad( 'خطای ۴۰۴ ← حالا ریدایرکت: ردیف خزش ۳۰۱ با مقصد، کار برطرف؛ ایراد تازه «ریدایرکت» (لینک ورودی دارد)', [ hodima_seo_advisor_crawl_row( '/p3/gone/' )['status'], hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/gone/', 'crawl_broken', '' ) ), $p3_codes( '/p3/gone/' ) ], [ 301, 'fixed', [ 'redirect' ] ] );
$p3_code = 404;
$p3_head = [];
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'status' => 404, 'redirect_to' => '' ], [ 'path' => '/p3/gone/' ] );
hodima_seo_advisor_crawl_map( true );
$p3_mark( [ 'source' => 'crawl', 'path' => '/p3/gone/', 'status' => 'done', 'items' => [ 'broken' ] ] );
hodima_ad( 'هنوز ۴۰۴ ← «هنوز دیده می‌شود»', hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/gone/', 'crawl_broken', '' ) ), 'still' );
$p3_code = 200;

// «دوباره انجام دادم» (فرم تب «در دست انجام») هم همان لحظه می‌سنجد
$p3_html = '<html><head><title>' . str_repeat( 'ع', 52 ) . '</title><meta name="description" content="' . str_repeat( 'م', 130 ) . '"></head><body><h1>تیتر تازه</h1></body></html>';
hodima_seo_advisor_task_set( $p3_path, 'crawl_no_meta', '', '', 'done' );
hodima_seo_advisor_tasks_judge( 'crawl', static fn(): string => 'still', [ $p3_path ], 'crawl_no_meta' );
$_POST = $_REQUEST = [ '_wpnonce' => wp_create_nonce( 'hodima_advisor_task' ), 'path' => $p3_path, 'rule' => 'crawl_no_meta', 'term' => '', 'title' => '', 'status' => 'done', 'redo' => '1' ];
try {
	hodima_seo_advisor_handle_task();
} catch ( Hodima_P3_Redirect ) {
	// ریدایرکت
}
$_POST = $_REQUEST = [];
hodima_ad( '«دوباره انجام دادم» روی ایراد فنی: صفحه دوباره خوانده و برطرف شد', $p3_st( 'no_meta' ), 'fixed' );

// لینک تغییر وضعیت: nonce مخصوص همان کار و وضعیت (SEO 3.0.9)
parse_str( (string) wp_parse_url( html_entity_decode( hodima_seo_advisor_task_url( '/nn/x/', 'low_ctr', '', 'عنوان', 'done' ) ), PHP_URL_QUERY ), $nn_q );
$nn_nonce  = (string) ( $nn_q['_wpnonce'] ?? '' );
$nn_action = hodima_seo_advisor_task_nonce_action( '/nn/x/', 'low_ctr', '', 'done', false );
hodima_ad( 'nonce لینک تغییر وضعیت فقط برای همان کار و وضعیت معتبر است (نه عمومی، نه وضعیت یا آدرس دیگر)', [ (bool) wp_verify_nonce( $nn_nonce, $nn_action ), (bool) wp_verify_nonce( $nn_nonce, 'hodima_advisor_task' ), (bool) wp_verify_nonce( $nn_nonce, hodima_seo_advisor_task_nonce_action( '/nn/x/', 'low_ctr', '', 'rejected', false ) ), (bool) wp_verify_nonce( $nn_nonce, hodima_seo_advisor_task_nonce_action( '/nn/y/', 'low_ctr', '', 'done', false ) ) ], [ true, false, false, false ] );
$_REQUEST = [ '_wpnonce' => $nn_nonce, 'path' => '/nn/x/', 'rule' => 'low_ctr', 'term' => '', 'title' => 'عنوان', 'status' => 'done' ];
$_POST    = [];
try {
	hodima_seo_advisor_handle_task();
} catch ( Hodima_P3_Redirect ) {
	// ریدایرکت
}
$_REQUEST = [];
hodima_ad( 'لینک GET با nonce مخصوص کار را ثبت می‌کند', hodima_seo_advisor_task( '/nn/x/', 'low_ctr', '' )['status'] ?? '', 'done' );
hodima_seo_advisor_task_set( '/nn/x/', 'low_ctr', '', '', '' );

// رد شد و ورودی نامعتبر
$p3_flash3 = $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'rejected', 'items' => [ 'low_inlinks' ] ] );
$p3_bad    = [ $p3_mark( [ 'source' => 'nope', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_h1' ] ] ), $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [] ] ), $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'doing', 'items' => [ 'no_h1' ] ] ) ];
hodima_ad( '«لازم نیست» ثبت و پیامش؛ منبع نامعتبر، بی‌تیک و وضعیت غیرمجاز ← هیچ ثبت نمی‌شود', [ $p3_st( 'low_inlinks' ), str_contains( $p3_flash3, '«لازم نیست» ثبت شد' ), count( array_filter( $p3_bad, static fn( string $f ): bool => str_contains( $f, 'چیزی ثبت نشد' ) ) ) ], [ 'rejected', true, 3 ] );
wp_set_current_user( 0 );
add_filter( 'wp_die_handler', static fn() => static function (): void { throw new Hodima_P3_Redirect( 'die' ); } );
$p3_denied = '';
try {
	$_POST = $_REQUEST = [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_h1' ] ];
	hodima_seo_advisor_handle_mark();
} catch ( Hodima_P3_Redirect $e ) {
	$p3_denied = $e->getMessage();
}
remove_all_filters( 'wp_die_handler' );
wp_set_current_user( 1 );
$p3_nonce = '';
try {
	$_POST = $_REQUEST = [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_h1' ], '_wpnonce' => 'bad' ];
	add_filter( 'wp_die_handler', static fn() => static function (): void { throw new Hodima_P3_Redirect( 'die' ); } );
	hodima_seo_advisor_handle_mark();
} catch ( Hodima_P3_Redirect $e ) {
	$p3_nonce = $e->getMessage();
}
remove_all_filters( 'wp_die_handler' );
$_POST = $_REQUEST = [];
hodima_ad( 'بدون دسترسی مدیر و nonce غلط رد می‌شوند', [ $p3_denied, $p3_nonce ], [ 'die', 'die' ] );
remove_filter( 'pre_http_request', $p3_live, 30 );

// آدرس‌های خراب: روتر همان لحظه
$p3_dead_ok = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'زنده', 'post_name' => 'p3-alive' ] );
$p3_alive   = (string) hodima_seo_advisor_path_key( (string) get_permalink( $p3_dead_ok ) );
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'router' => true, 'dead' => [ [ 'path' => '/p3-really-missing/', 'impressions' => 900, 'clicks' => 2, 'state' => 'missing', 'target' => '', 'code' => 404 ], [ 'path' => $p3_alive, 'impressions' => 500, 'clicks' => 1, 'state' => 'missing', 'target' => '', 'code' => 404 ], [ 'path' => '/p3-redirected/', 'impressions' => 300, 'clicks' => 0, 'state' => 'redirect', 'target' => '/x/', 'code' => 301 ] ], 'total' => 3, 'seen' => 1, 'fresh' => 0, 'unseen' => [ [ 'id' => $p3_dead_ok, 'path' => '/p3/unseen/', 'title' => 'بدون نمایش', 'type' => 'page', 'days' => 90 ] ] ], false );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'health' ];
$p3_h0 = $cap( static fn() => hodima_seo_advisor_render_health( hodima_seo_advisor_status() ) );
hodima_ad( 'تب آدرس‌های خراب: دکمه فقط روی ۴۰۴/ریدایرکت خراب (۲) و صفحه بدون نمایش (۱)؛ ریدایرکت سالم «درست کار می‌کند»', [ substr_count( $p3_h0, 'value="hodima_advisor_mark"' ), str_contains( $p3_h0, 'درست کار می‌کند' ), str_contains( $p3_h0, 'value="unseen"' ) ], [ 3, true, true ] );
$p3_mark( [ 'source' => 'health', 'path' => '/p3-really-missing/', 'status' => 'done', 'items' => [ 'dead' ] ] );
$p3_mark( [ 'source' => 'health', 'path' => $p3_alive, 'status' => 'done', 'items' => [ 'dead' ] ] );
$p3_dead_paths = array_column( (array) hodima_seo_advisor_health()['dead'], 'path' );
hodima_ad( 'آدرس مرده: هنوز ۴۰۴ ← «هنوز خراب است»؛ حالا صفحه سالم ← برطرف و از فهرست ذخیره‌شده هم رفت', [ hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3-really-missing/', 'health_dead', '' ) ), hodima_seo_advisor_state_or( hodima_seo_advisor_task( $p3_alive, 'health_dead', '' ) ), in_array( $p3_alive, $p3_dead_paths, true ), in_array( '/p3-really-missing/', $p3_dead_paths, true ) ], [ 'still', 'fixed', false, true ] );

// صفحه بدون نمایش: اول صف بررسی؛ ایندکس‌شده بعد از «انجام دادم» ← برطرف؛ ۳۰ روز بعد هنوز نه ← مانده
$p3_mark( [ 'source' => 'health', 'path' => '/p3/unseen/', 'status' => 'done', 'items' => [ 'unseen' ] ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/unseen/' => [ 'verdict' => 'NEUTRAL', 'coverage' => 'Discovered - currently not indexed', 'robots' => '', 'indexing' => '', 'fetch' => '', 'last_crawl' => '', 'google_canonical' => '', 'user_canonical' => '', 'crawled_as' => '', 'checked' => time() - 3 * DAY_IN_SECONDS ] ], false );
hodima_ad( 'صفحه بدون نمایش «انجام دادم»: منتظر، اول صف بررسی ایندکس (بررسی قبلی پیش از انجام بود)', [ hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/unseen/', 'health_unseen', '' ) ), hodima_seo_advisor_inspect_queue( 1 )[0] ?? '' ], [ 'waiting', '/p3/unseen/' ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/unseen/' => [ 'verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'robots' => '', 'indexing' => '', 'fetch' => '', 'last_crawl' => gmdate( 'c' ), 'google_canonical' => '', 'user_canonical' => '', 'crawled_as' => '', 'checked' => time() + 5 ] ], false );
hodima_seo_advisor_tasks_sync_inspected();
hodima_ad( 'بررسی بعد از انجام: ایندکس‌شده ← برطرف', hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/unseen/', 'health_unseen', '' ) ), 'fixed' );
hodima_ad( 'حکم بدون نمایش: ۳۱ روز بعد هنوز در فهرست ← مانده؛ ۵ روز ← منتظر؛ از فهرست رفته ← برطرف', [ hodima_seo_advisor_task_unseen_verdict( [ 'path' => '/u/', 'at' => time() - 31 * DAY_IN_SECONDS ], [ '/u/' => true ], [] ), hodima_seo_advisor_task_unseen_verdict( [ 'path' => '/u/', 'at' => time() - 5 * DAY_IN_SECONDS ], [ '/u/' => true ], [] ), hodima_seo_advisor_task_unseen_verdict( [ 'path' => '/u/', 'at' => time() ], [], [] ) ], [ 'still', 'wait', 'fixed' ] );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'health' ];
$p3_h1 = $cap( static fn() => hodima_seo_advisor_render_health( hodima_seo_advisor_status() ) );
hodima_ad( 'تب آدرس‌های خراب بعد از علامت: برطرف/منتظرها پنهان؛ «هنوز خراب است» با نشان و دکمه می‌ماند', [ str_contains( $p3_h1, 'p3-really-missing' ), str_contains( $p3_h1, 'هنوز خراب است' ), str_contains( $p3_h1, 'بدون نمایش</th>' ) || str_contains( $p3_h1, '>بدون نمایش <' ), str_contains( $p3_h1, 'علامت‌خورده' ) ], [ true, true, false, true ] );

// وضعیت در گوگل: ایندکس‌نشده؛ گوگل بعد از انجام دوباره نخزیده ← منتظر؛ خزیده و هنوز نه ← مانده؛ ایندکس ← برطرف
$p3_rec = static fn( string $coverage, string $verdict, int $checked, string $crawl ): array => [ 'verdict' => $verdict, 'coverage' => $coverage, 'robots' => '', 'indexing' => '', 'fetch' => '', 'last_crawl' => $crawl, 'google_canonical' => '', 'user_canonical' => '', 'crawled_as' => '', 'checked' => $checked ];
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/idx/' => $p3_rec( 'Crawled - currently not indexed', 'NEUTRAL', time() - DAY_IN_SECONDS, gmdate( 'c', time() - 9 * DAY_IN_SECONDS ) ) ], false );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'indexing' ];
$p3_i0 = $cap( static fn() => hodima_seo_advisor_render_indexing() );
$p3_mark( [ 'source' => 'indexing', 'path' => '/p3/idx/', 'status' => 'done' ] );
$p3_it = static fn(): string => hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/idx/', 'index_problem', '' ) );
$p3_i_a = [ $p3_it(), hodima_seo_advisor_task( '/p3/idx/', 'index_problem', '' )['title'] ?? '', in_array( '/p3/idx/', hodima_seo_advisor_inspect_queue( 3 ), true ) ];
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/idx/' => $p3_rec( 'Crawled - currently not indexed', 'NEUTRAL', time() + 5, gmdate( 'c', time() - 9 * DAY_IN_SECONDS ) ) ], false );
hodima_seo_advisor_tasks_sync_inspected();
$p3_i_b = $p3_it();
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/idx/' => $p3_rec( 'Crawled - currently not indexed', 'NEUTRAL', time() + 5, gmdate( 'c', time() + 5 ) ) ], false );
hodima_seo_advisor_tasks_sync_inspected();
$p3_i_c = $p3_it();
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/idx/' => $p3_rec( 'Submitted and indexed', 'PASS', time() + 10, gmdate( 'c', time() + 5 ) ) ], false );
hodima_seo_advisor_tasks_sync_inspected();
hodima_ad( 'ایندکس: دکمه در جدول؛ «انجام دادم» ← منتظر با عنوان وضعیت و در صف؛ گوگل نخزیده ← منتظر؛ خزیده و نه ← مانده؛ ایندکس ← برطرف', [ str_contains( $p3_i0, 'value="hodima_advisor_mark"' ), $p3_i_a, $p3_i_b, $p3_i_c, $p3_it() ], [ true, [ 'waiting', 'ایندکس: خزیده شد ولی ایندکس نشد', true ], 'waiting', 'still', 'fixed' ] );

// صفحه‌های هم‌کلمه: یک کار برای کلمه؛ ۱۴ روز؛ سهم ≥ ۷۰٪ ← برطرف
hodima_seo_advisor_store(
	[
		'/p3/c1/' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ],
		'/p3/c2/' => [ 'clicks' => 25, 'impressions' => 2000, 'position' => 7.5 ],
	],
	[],
	[
		'/p3/c1/' => [ 'گیره سه' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ] ],
		'/p3/c2/' => [ 'گیره سه' => [ 'clicks' => 25, 'impressions' => 2000, 'position' => 7.5 ] ],
	]
);
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [], false );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
$p3_k0 = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'هم‌کلمه: فرم کلمه بیرون از summary (کنش داخل summary نامعتبر است) با کلمه و مسیر برنده', [ str_contains( $p3_k0, 'name="term" value="گیره سه"' ), 1 === preg_match( '#<summary[^>]*>(?:(?!</summary>).)*hodima-advisor__mark#su', $p3_k0 ), str_contains( $p3_k0, 'aria-label="انجام دادم: گیره سه"' ) ], [ true, false, true ] );
hodima_seo_advisor_task_set( '/p3/old-winner/', 'competition', 'گيره سه', 'قدیمی', 'doing' );
$p3_mark( [ 'source' => 'competition', 'path' => '/p3/c1/', 'term' => 'گیره سه', 'status' => 'done' ] );
$p3_ct = static fn(): string => hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/c1/', 'competition', 'گیره سه' ) );
hodima_ad( 'یک کار برای هر کلمه: کار قبلی همان کلمه (برنده دیگر، املای عربی) جایش را داد؛ برچسب «۰ از ۱۴ روز»', [ hodima_seo_advisor_task( '/p3/old-winner/', 'competition', 'گیره سه' ), $p3_ct(), str_contains( hodima_seo_advisor_task_view( hodima_seo_advisor_task( '/p3/c1/', 'competition', 'گیره سه' ) )['label'], '0 از 14 روز' ) ], [ null, 'waiting', true ] );
$p3_k1 = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'کلمه علامت‌خورده از فهرست پنهان با پیوند نمایش', [ str_contains( $p3_k1, 'name="term" value="گیره سه"' ), str_contains( $p3_k1, 'کار بازی نمانده' ), str_contains( $p3_k1, 'نمایش 1 کلمه علامت‌خورده' ) ], [ false, true, true ] );
hodima_seo_advisor_tasks_sync_competition();
$p3_c_a = $p3_ct();
$p3_ts  = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
$p3_ts[ hodima_seo_advisor_task_key( '/p3/c1/', 'competition', 'گیره سه' ) ]['at'] = time() - 15 * DAY_IN_SECONDS;
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $p3_ts, false );
hodima_seo_advisor_tasks_sync_competition();
$p3_c_b = $p3_ct();
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'queries' ), [ 'impressions' => 300 ], [ 'path' => '/p3/c2/' ] );
hodima_seo_advisor_tasks_sync_competition();
hodima_ad( 'هم‌کلمه: همان روز ← منتظر؛ ۱۵ روز بعد هنوز رقابت ← «هنوز رقابت دارد»؛ برنده ≥ ۷۰٪ ← برطرف', [ $p3_c_a, $p3_c_b, $p3_ct() ], [ 'waiting', 'still', 'fixed' ] );

// پیشنهادها: تیک برای هر ایراد، عنوان از داده خود سایت؛ همه علامت‌خورده ← صفحه پنهان
hodima_seo_advisor_store( [ '/p3/opp/' => [ 'clicks' => 2, 'impressions' => 5000, 'position' => 4.0 ] ], [], [] );
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'pages' ), [ 'advice' => wp_json_encode( [ [ 'rule' => 'low_ctr', 'term' => '', 'title' => 'عنوان و توضیح متا را بازنویسی کنید', 'gain' => 40 ], [ 'rule' => 'near_first', 'term' => 'گیره سه', 'title' => 'کلمه «گیره سه» نزدیک صفحه اول', 'gain' => 10 ] ] ), 'advice_count' => 2, 'potential' => 50 ], [ 'path' => '/p3/opp/' ] );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
$p3_o0 = $cap( static fn() => hodima_seo_advisor_render_list( hodima_seo_advisor_status() ) );
hodima_ad( 'پیشنهادها (3.1.1): دو تیک «قاعده|کلمه» در جزئیات، فرم بی‌دکمه پنهان، «همه را انجام دادم» و «این صفحه را نادیده بگیر» در منوی ⋯ با ویژگی form', [ str_contains( $p3_o0, 'value="low_ctr|"' ), str_contains( $p3_o0, 'value="near_first|گیره سه"' ), str_contains( $p3_o0, 'value="opportunities"' ), str_contains( $p3_o0, 'hodima-advisor__menu-item hodima-advisor__menu-item--muted">این صفحه را نادیده بگیر' ), 1 === preg_match( '#<button type="submit" form="hodima-advisor-mark-\d+" name="status" value="done" class="hodima-advisor__menu-item hodima-advisor__menu-item--ok">همه را انجام دادم#u', $p3_o0 ), str_contains( $p3_o0, 'class="hodima-advisor__mark" method="post"' ) && str_contains( $p3_o0, ' hidden>' ) ], [ true, true, true, true, true, true ] );
$p3_mark( [ 'source' => 'opportunities', 'path' => '/p3/opp/', 'status' => 'done', 'items' => [ 'low_ctr|', 'bogus|x' ] ] );
hodima_ad( 'یک ایراد «انجام دادم»: عنوان از پیشنهاد ذخیره‌شده؛ مورد ساختگی ثبت نمی‌شود؛ صفحه هنوز در فهرست (ایراد دوم باز)', [ hodima_seo_advisor_task( '/p3/opp/', 'low_ctr', '' )['title'] ?? '', count( hodima_seo_advisor_tasks() ) > 0 && null === hodima_seo_advisor_task( '/p3/opp/', 'bogus', 'x' ), isset( hodima_seo_advisor_handled_paths()['/p3/opp/'] ) ], [ 'عنوان و توضیح متا را بازنویسی کنید', true, false ] );
$p3_mark( [ 'source' => 'opportunities', 'path' => '/p3/opp/', 'status' => 'rejected', 'items' => [ 'near_first|گیره سه' ] ] );
hodima_ad( 'هر دو علامت خورد ← صفحه تمام‌علامت‌خورده (پنهان در فهرست)', isset( hodima_seo_advisor_handled_paths()['/p3/opp/'] ), true );

// انجام‌شده: چیپ‌های «از کدام تب»، فیلتر و سه گام هر نوع
update_option( 'hodima_discover_sc_key', $key_json, false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 3, 'queries' => 2, 'advised' => 1, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks', 'tstatus' => 'fixed' ];
$p3_d0 = $cap( static fn() => hodima_seo_advisor_render_admin() );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks', 'tstatus' => 'fixed', 'tsource' => 'crawl' ];
$p3_d1 = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( '«کارها» ← «برطرف شد»: ردیف چیپ «از کدام بخش» و «همه» حذف (SEO 3.5.5: منو فقط وقتی دو بخش هر کدام ≥۳ کار دارند)؛ فیلتر ایرادهای فنی فقط کار خزش؛ گام «صفحه دوباره از سایت خوانده شد»', [ str_contains( $p3_d0, 'aria-label="از کدام بخش"' ), str_contains( $p3_d0, 'tsource=competition' ), str_contains( $p3_d1, 'رقابت صفحه‌ها برای' ), str_contains( $p3_d1, 'بدون تیتر اصلی (H1)' ), str_contains( $p3_d1, 'صفحه دوباره از سایت خوانده شد' ) ], [ false, false, false, true, true ] );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks' ];
$p3_d2 = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'در دست انجام: «هنوز خراب است» با قدم بعدی و «دوباره انجام دادم»؛ نشان تب مبدأ', [ str_contains( $p3_d2, 'هنوز خراب است' ), str_contains( $p3_d2, 'قانون ۳۰۱ بگذارید' ), str_contains( $p3_d2, 'دوباره انجام دادم' ), str_contains( $p3_d2, '<span class="hodima-advisor__task-src">آدرس‌های خراب</span>' ) ], [ true, true, true, true ] );
delete_option( 'hodima_discover_sc_key' );
$_GET = [];

remove_filter( 'wp_redirect', $p3_redirect, 1 );
wp_delete_post( $p3_post, true );
wp_delete_post( $p3_dead_ok, true );
hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
delete_option( HODIMA_SEO_ADVISOR_LIVE_OPTION );
false === $p3_tasks ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $p3_tasks, false );
false === $p3_status ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $p3_status, false );
wp_set_current_user( 0 );

echo "=== خزنده شبانه (SEO 3.1.4)\n";
$cw_site     = untrailingslashit( home_url() );
$cw_settings = get_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, false );
$cw_state    = get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, false );
$cw_crawl    = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false );
$cw_at       = static fn( string $when ): int => ( new DateTimeImmutable( $when, wp_timezone() ) )->getTimestamp();
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
hodima_ad( 'پنجره ۲ تا ۸: ۰۳:۰۰ داخل، ۱۲:۰۰ و ۰۸:۰۰ بیرون', [ hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 03:00' ) ), hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 12:00' ) ), hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 08:00' ) ) ], [ true, false, false ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 22, 'end' => 6, 'interval' => 7 ], false );
hodima_ad( 'پنجره از شب تا صبح (۲۲ تا ۶): ۲۳:۳۰ و ۰۱:۰۰ داخل، ۱۴:۰۰ بیرون', [ hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 23:30' ) ), hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 01:00' ) ), hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 14:00' ) ) ], [ true, true, false ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 5, 'end' => 5, 'interval' => 99 ], false );
hodima_ad( 'ساعت شروع و پایان برابر = همه ساعت‌ها؛ فاصله نامعتبر ← ۷ روز', [ hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 15:00' ) ), hodima_seo_advisor_crawler_settings()['interval'] ], [ true, 7 ] );
hodima_ad( 'کرون هر ۵ دقیقه ثبت شده', wp_get_schedules()['hodima_five_minutes']['interval'] ?? 0, 300 );

$cw_u = static fn( string $href, string $base = '' ): ?string => hodima_seo_advisor_crawler_resolve( $href, '' === $base ? $cw_site . '/dir/page/' : $base );
hodima_ad( 'آدرس لینک: نسبی، ریشه‌ای، کامل، بدون پروتکل و پوشه بالاتر', [ $cw_u( '../x/' ), $cw_u( '/y/' ), $cw_u( 'z/' ), $cw_u( '//cdn.test/a/' ), $cw_u( 'https://other.test/p/?q=1#f' ) ], [ $cw_site . '/dir/x/', $cw_site . '/y/', $cw_site . '/dir/page/z/', 'https://cdn.test/a/', 'https://other.test/p/?q=1' ] );
hodima_ad( 'لینک غیرصفحه: #، mailto، tel، javascript، خالی', [ $cw_u( '#top' ), $cw_u( 'mailto:a@b.test' ), $cw_u( 'tel:0912' ), $cw_u( 'javascript:void(0)' ), $cw_u( '' ) ], [ null, null, null, null, null ] );
hodima_ad( 'نخزیدن: پارامتردار، فایل، wp-admin، خارجی؛ می‌خزد: صفحه معمولی با حروف مسیر', [ hodima_seo_advisor_crawler_normalize( $cw_site . '/a/?x=1' ), hodima_seo_advisor_crawler_normalize( $cw_site . '/a.pdf' ), hodima_seo_advisor_crawler_normalize( $cw_site . '/wp-admin/x/' ), hodima_seo_advisor_crawler_normalize( 'https://other.test/a/' ), hodima_seo_advisor_crawler_normalize( $cw_site . '/Hair-Clips/' ) ], [ null, null, null, null, $cw_site . '/Hair-Clips/' ] );

$cw_html = '<!doctype html><html><head><title>  صفحه   نمونه </title><meta name="Description" content="توضیح نمونه"><meta name="robots" content="noindex, max-snippet:0"><link rel="canonical" href="' . $cw_site . '/other/"></head><body>'
	. '<header><a href="/menu/">منو</a></header><nav class="main-menu"><a href="/nav/">ناو</a></nav>'
	. '<main><h1>تیتر اصلی</h1><h2>یک</h2><h2>دو</h2><h2>سه</h2><p><a href="/in/">متن لینک</a> <a rel="nofollow" href="/nf/">بدون دنبال</a> <a href="/img/"><img src="x.jpg" alt="تصویر لینک"></a></p></main>'
	. '<div class="sidebar-box"><a href="/side/">کنار</a></div><footer><a href="/foot/">فوتر</a></footer></body></html>';
$cw_p     = hodima_seo_advisor_crawler_parse( $cw_html, $cw_site . '/p/' );
$cw_pos   = array_column( array_map( static fn( array $l ): array => [ hodima_seo_advisor_path_key( $l[0] ), $l[1] . ( 0 === $l[3] ? ':nf' : '' ) ], $cw_p['links'] ), 1, 0 );
hodima_ad( 'خواندن HTML: عنوان یکدست‌شده، متا، robots، canonical، H1 و دو H2 اول', [ $cw_p['title'], $cw_p['meta'], $cw_p['robots'], $cw_p['canonical'], $cw_p['h1'], $cw_p['h2'] ], [ 'صفحه نمونه', 'توضیح نمونه', 'noindex, max-snippet:0', $cw_site . '/other/', 'تیتر اصلی', 'یک | دو' ] );
hodima_ad( 'جایگاه لینک‌ها: منو، ناو، متن، nofollow، تصویر، کنار، فوتر', $cw_pos, [ '/menu/' => 'navigation', '/nav/' => 'navigation', '/in/' => 'content', '/nf/' => 'content:nf', '/img/' => 'content', '/side/' => 'aside', '/foot/' => 'footer' ] );
hodima_ad( 'متن لینک تصویری از alt', array_column( $cw_p['links'], 2, 0 )[ $cw_site . '/img/' ] ?? '', 'تصویر لینک' );
// تصویر بدون alt (SEO 3.3.3): alt خالی عمدی داخل لینک/دکمه/figure با متن ایراد نیست؛ نبود ویژگی alt، alt خالی تنها و لینکِ فقط-تصویر ایراد است
$cw_alt = static function ( string $body ) use ( $cw_site ): int {
	$parsed = hodima_seo_advisor_crawler_parse( '<html><head><title>t</title></head><body><main>' . $body . '</main></body></html>', $cw_site . '/alt/' );
	return $parsed['img_noalt'];
};
hodima_ad(
	'تصویر بدون alt (SEO 3.4.9): نبود ویژگی alt و لینکِ فقط-تصویر با alt خالی = ایراد؛ alt خالی تنها (حتی figure بی‌کپشن) = «بررسی کنید» نه ایراد؛ alt دارد، تزئینی، کارت با عنوان، لینک با aria-label، figure با کپشن = هیچ',
	[
		$cw_alt( '<img src="a.jpg">' ),
		$cw_alt( '<img src="a.jpg" alt="">' ),
		$cw_alt( '<img src="a.jpg" alt="توضیح">' ),
		$cw_alt( '<img src="a.jpg" alt="" aria-hidden="true">' ),
		$cw_alt( '<a href="/p/"><img src="a.jpg" alt=""><h3>عنوان کارت</h3></a>' ),
		$cw_alt( '<a href="/p/" aria-label="محصول"><img src="a.jpg" alt=""></a>' ),
		$cw_alt( '<a href="/p/"><img src="a.jpg" alt=""></a>' ),
		$cw_alt( '<a href="/p/"><img src="a.jpg"><h3>عنوان</h3></a>' ),
		$cw_alt( '<figure><img src="a.jpg" alt=""><figcaption>کپشن</figcaption></figure>' ),
		$cw_alt( '<figure><img src="a.jpg" alt=""><figcaption></figcaption></figure>' ),
		$cw_alt( '<button type="button"><img src="a.jpg" alt="">باز کن</button>' ),
	],
	[ 1, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0 ]
);
// فهرست آدرس تصویرهای بدون alt (SEO 3.3.6): آدرس واقعی (data-src تنبل‌بارگذاری)، جاگیر ریز نه، کارت با عنوان نه، سقف ۸
$cw_im = hodima_seo_advisor_crawler_parse(
	'<html><head><title>t</title></head><body><main>'
	. '<img src="/wp-content/uploads/a.jpg">'
	. '<img src="data:image/gif;base64,R0lGOD" data-src="/wp-content/uploads/lazy.jpg" alt="">'
	. '<img src="data:image/gif;base64,R0lGOD" alt="دارد">'
	. '<img src="data:image/gif;base64,R0lGOD">'
	. '<a href="/p/"><img src="/c.jpg" alt=""><h3>عنوان</h3></a>'
	. '<img src="https://cdn.test/x.png" alt=""><img src="/wp-content/uploads/a.jpg">'
	. '</main></body></html>',
	$cw_site . '/im/'
);
$cw_e = static fn( string $u, string $st = 'missing', string $c = '' ): array => [ 'u' => $u, 's' => $st, 'c' => $c ];
hodima_ad(
	'فهرست تصویرها: نبود alt (ایراد) اول، alt خالی (بررسی کنید) بعد؛ آدرس واقعی (data-src)، یکتا، جاگیر و کارت نه',
	[ $cw_im['img_noalt'], $cw_im['img_empty'], $cw_im['img_list'] ],
	[ 1, 2, [ $cw_e( $cw_site . '/wp-content/uploads/a.jpg' ), $cw_e( $cw_site . '/wp-content/uploads/lazy.jpg', 'empty' ), $cw_e( 'https://cdn.test/x.png', 'empty' ) ] ]
);
$cw_many = hodima_seo_advisor_crawler_parse( '<html><head><title>t</title></head><body><main>' . implode( '', array_map( static fn( int $i ): string => '<img src="/u/' . $i . '.jpg">', range( 1, 25 ) ) ) . '</main></body></html>', $cw_site . '/im/' );
hodima_ad( 'فهرست تصویر تا ۲۰ آدرس ولی شمار کامل ۲۵', [ $cw_many['img_noalt'], count( $cw_many['img_list'] ) ], [ 25, 20 ] );
$cw_imrow = hodima_seo_advisor_crawler_page_row( '/im/', 200, 100, $cw_im, '', '' );
hodima_ad( 'ردیف صفحه: تصویرها JSON و شمار «بررسی کنید» جدا ذخیره می‌شود', [ $cw_imrow['img_list'], $cw_imrow['img_empty'], hodima_seo_advisor_img_list_decode( $cw_imrow['img_list'] ) ], [ hodima_seo_advisor_img_list_encode( $cw_im['img_list'] ), 2, $cw_im['img_list'] ] );

// کلاس‌های مثبت کاذب (SEO 3.4.9): هر کدام یک آزمون تا برنگردد
$cw_scan = static function ( string $body ) use ( $cw_site ): array {
	$parsed = hodima_seo_advisor_crawler_parse( '<html><head><title>t</title></head><body><main>' . $body . '</main></body></html>', $cw_site . '/alt/' );
	return [ $parsed['img_noalt'], $parsed['img_empty'] ];
};
hodima_ad(
	'تصویر نامرئی ایراد نیست: <template>، <dialog> بسته (پاپ‌آپ)، hidden، display:none، aria-hidden؛ <dialog open> دیده می‌شود',
	[ $cw_scan( '<template><img src="/q.jpg"></template>' ), $cw_scan( '<dialog id="hd-notification-5"><img src="/q.jpg"></dialog>' ), $cw_scan( '<div hidden><img src="/q.jpg"></div>' ), $cw_scan( '<div style="display:none"><img src="/q.jpg"></div>' ), $cw_scan( '<div aria-hidden="true"><img src="/q.jpg"></div>' ), $cw_scan( '<dialog open><img src="/q.jpg"></dialog>' ) ],
	[ [ 0, 0 ], [ 0, 0 ], [ 0, 0 ], [ 0, 0 ], [ 0, 0 ], [ 1, 0 ] ]
);
hodima_ad(
	'alt خالی عمدی با نبود alt یکی نیست؛ فقط فاصله یا نویسه نامرئی = خالی؛ alt=0 درست است',
	[ $cw_scan( '<img src="/a.jpg" alt="">' ), $cw_scan( '<img src="/a.jpg" alt="  ">' ), $cw_scan( '<img src="/a.jpg" alt="&nbsp;">' ), $cw_scan( "<img src=\"/a.jpg\" alt=\"\u{200C}\">" ), $cw_scan( '<img src="/a.jpg">' ), $cw_scan( '<img src="/a.jpg" alt="0">' ) ],
	[ [ 0, 1 ], [ 0, 1 ], [ 0, 1 ], [ 0, 1 ], [ 1, 0 ], [ 0, 0 ] ]
);
hodima_ad(
	'یک فایل که جای دیگر همان صفحه alt دارد ایراد نمی‌گیرد (دسکتاپ/موبایل)؛ فایل دیگر می‌گیرد',
	[ $cw_scan( '<img src="/a.jpg" alt="کفش"><img src="/a.jpg" alt="">' ), $cw_scan( '<img src="/a.jpg" alt="کفش"><img src="/b.jpg">' ) ],
	[ [ 0, 0 ], [ 1, 0 ] ]
);
$cw_ctx = hodima_seo_advisor_crawler_parse( '<html><head><title>t</title></head><body><main><div class="sp-gallery big"><figure><img src="/g.jpg"></figure></div><section id="promo"><img src="/p.jpg"></section></main></body></html>', $cw_site . '/ctx/' );
hodima_ad( 'جایگاه در HTML: نزدیک‌ترین نیای معنادار با کلاس یا شناسه (figure رد می‌شود)', array_column( $cw_ctx['img_list'], 'c' ), [ 'div.sp-gallery.big', 'section#promo' ] );
$cw_src = hodima_seo_advisor_crawler_parse( '<html><head><title>t</title></head><body><main><img srcset="/s-300.jpg 300w, /s-600.jpg 600w"><img src=""><img src="data:image/svg+xml;base64,AAA"></main></body></html>', $cw_site . '/src/' );
hodima_ad( 'آدرس تصویر: فقط srcset ← اولین آدرس؛ src خالی یا data: ← آدرس خالی (ولی شمرده می‌شود، فهرست با «آدرسی در HTML نیست»)', [ $cw_src['img_noalt'], array_column( $cw_src['img_list'], 'u' ) ], [ 3, [ $cw_site . '/s-300.jpg', '', '' ] ] );
$cw_row = hodima_seo_advisor_crawler_page_row( '/p/', 200, 120, $cw_p, '', '' );
hodima_ad( 'ردیف صفحه noindex: غیرایندکس‌پذیر با علت، طول عنوان، max-snippet:0', [ $cw_row['indexable'], $cw_row['reason'], $cw_row['title_len'], $cw_row['no_snippet'] ], [ 0, 'Noindex', 10, 1 ] );
$cw_row2 = hodima_seo_advisor_crawler_page_row( '/p/', 200, 120, [ 'canonical' => $cw_site . '/p/' ] + $cw_p + [], '', '' );

// سایت ساختگی
$cw_pages = [
	'/'          => '<html><head><title>خانه با عنوان خیلی بلند برای آزمون خزنده</title><meta name="description" content="توضیح"></head><body><nav><a href="/a/">آ</a></nav><main><h1>خانه</h1><a href="/b/">ب</a> <a href="/old/">قدیمی</a> <a href="c/">ث</a> <a href="https://example.com/x/">بیرون</a> <a href="/a/?x=1">پارامتر</a> <a href="/file.pdf">فایل</a> <a href="mailto:a@b.test">ایمیل</a> <a href="#top">بالا</a></main><footer><a href="/about/">درباره</a></footer></body></html>',
	'/a/'        => '<html><head><title>صفحه آ</title><meta name="robots" content="noindex"></head><body><main><h1>آ</h1><a href="/b/">ب</a> <a rel="nofollow" href="/c/">ث</a></main></body></html>',
	'/b/'        => '<html><head><title>صفحه ب</title><link rel="canonical" href="' . $cw_site . '/a/"></head><body><main><h1>ب</h1><a href="/a/">آ</a></main></body></html>',
	'/about/'    => '<html><head><title>درباره</title></head><body><main><h1>درباره</h1></main></body></html>',
];
$cw_requested = [];
$cw_mode      = 'site';
add_filter(
	'pre_http_request',
	$cw_filter = static function ( mixed $pre, array $args, string $url ) use ( &$cw_requested, &$cw_mode, $cw_site, $cw_pages ): mixed {
		if ( ! str_starts_with( $url, $cw_site ) ) {
			return $pre;
		}
		$cw_requested[] = $url;
		if ( 'down' === $cw_mode ) {
			return new WP_Error( 'http_request_failed', 'timeout' );
		}
		if ( 'throttle' === $cw_mode ) { // SEO 3.8.7: دیوار امنیتی «درخواست زیاد است»
			return [ 'response' => [ 'code' => 429 ], 'headers' => [ 'retry-after' => '120' ], 'body' => 'Too Many Requests' ];
		}
		if ( 'maint' === $cw_mode ) { // ۵۰۳ نگهداری با تاریخ Retry-After دور ← سقف ۶ ساعت
			return [ 'response' => [ 'code' => 503 ], 'headers' => [ 'retry-after' => 'Wed, 21 Oct 2099 07:28:00 GMT' ], 'body' => '' ];
		}
		if ( 'error503' === $cw_mode ) { // ۵۰۳ بدون Retry-After = خطای واقعی سرور (رفتار قبلی می‌ماند)
			return [ 'response' => [ 'code' => 503 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '' ];
		}
		$path = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( '/old/' === $path ) {
			return [ 'response' => [ 'code' => 301 ], 'headers' => [ 'location' => $cw_site . '/b/' ], 'body' => '' ];
		}
		if ( isset( $cw_pages[ $path ] ) ) {
			return [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'text/html; charset=UTF-8' ], 'body' => $cw_pages[ $path ] ];
		}
		return [ 'response' => [ 'code' => 404 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html><head><title>پیدا نشد</title></head><body><h1>۴۰۴</h1></body></html>' ];
	},
	10,
	3
);
add_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
hodima_seo_advisor_ensure_tables();
$cw_t = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $cw_t ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'crawl_links' ) ) );
$GLOBALS['wpdb']->insert( $cw_t, [ 'path' => '/gone/', 'status' => 200 ] ); // صفحه دور قبل که حالا نیست

hodima_seo_advisor_crawler_start();
$cw_started = hodima_seo_advisor_crawler_state();
hodima_ad( 'شروع دور: صف با صفحه اصلی، نوشته‌ها و دسته‌ها در فهرست seed', [ $cw_started['status'], array_map( static fn( string $u ): ?string => hodima_seo_advisor_path_key( $u ), $cw_started['queue'] ), (bool) $cw_started['seed'] ], [ 'running', [ '/' ], true ] );
$cw_s         = $cw_started;
$cw_s['seed'] = []; // آزمون فقط با سایت ساختگی؛ seed واقعی بعدا جدا
hodima_seo_advisor_crawler_save( $cw_s );

$cw_out = hodima_seo_advisor_crawler_tick( true );
$cw_rows = [];
foreach ( (array) $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i', $cw_t ), ARRAY_A ) as $r ) {
	$cw_rows[ $r['path'] ] = $r;
}
ksort( $cw_rows );
hodima_ad( 'دور کامل در یک نوبت تمام شد؛ صفحه‌ها: خانه، آ، ب، قدیمی، ث، درباره (بدون پارامتر، فایل، بیرونی؛ صفحه دور قبل پاک)', [ $cw_out['finished'], array_keys( $cw_rows ) ], [ true, [ '/', '/a/', '/about/', '/b/', '/c/', '/old/' ] ] );
hodima_ad( 'درخواست‌ها فقط برای آدرس‌های داخلی بدون پارامتر و فایل (هر آدرس یک‌بار)', [ count( $cw_requested ), count( array_unique( $cw_requested ) ) ], [ 6, 6 ] );
hodima_ad( 'وضعیت‌ها: ۲۰۰، noindex، canonical دیگر، ریدایرکت ۳۰۱ به /b/، ۴۰۴', [ array_map( static fn( string $p ): array => [ (int) $cw_rows[ $p ]['status'], (int) $cw_rows[ $p ]['indexable'], $cw_rows[ $p ]['reason'], $cw_rows[ $p ]['redirect_to'] ], [ '/', '/a/', '/b/', '/old/', '/c/' ] ) ], [ [ [ 200, 1, '', '' ], [ 200, 0, 'Noindex', '' ], [ 200, 0, 'Canonicalised', '' ], [ 301, 0, 'Redirected', '/b/' ], [ 404, 0, '', '' ] ] ] );
hodima_ad( 'عنوان و متا و H1 خانه ذخیره شد', [ $cw_rows['/']['title_len'] > 30, (int) $cw_rows['/']['meta_len'], $cw_rows['/']['h1'] ], [ true, 5, 'خانه' ] );
hodima_ad( 'لینک ورودی یکتا: /b/ از خانه و آ (۲)، /c/ فقط از خانه (۱؛ لینک nofollow آ از ۳.۹.۳ حساب نمی‌شود)، /a/ از خانه و ب', [ (int) $cw_rows['/b/']['inlinks'], (int) $cw_rows['/c/']['inlinks'], (int) $cw_rows['/a/']['inlinks'] ], [ 2, 1, 2 ] );
hodima_ad( 'لینک متنی (محتوا و follow): /b/ دو، /c/ یکی (فقط از خانه؛ آ nofollow)، /a/ دو (از ب و از لینک پارامتری «/a/?x=1» خانه که به همان صفحه می‌رسد؛ لینک منوی خانه حساب نمی‌شود)', [ (int) $cw_rows['/b/']['text_links'], (int) $cw_rows['/c/']['text_links'], (int) $cw_rows['/a/']['text_links'] ], [ 2, 1, 2 ] );
hodima_ad( 'عمق: خانه ۰؛ آ، ب، قدیمی، ث، درباره ۱', array_map( static fn( string $p ): int => (int) $cw_rows[ $p ]['depth'], [ '/', '/a/', '/b/', '/old/', '/c/', '/about/' ] ), [ 0, 1, 1, 1, 1, 1 ] );
$cw_after = hodima_seo_advisor_crawler_state();
hodima_ad( 'بعد از دور: بیکار، تمام‌شده ثبت، صف و seen خالی؛ وضعیت صفحه «ایرادهای فنی» از خزنده', [ $cw_after['status'], $cw_after['finished'] > 0, $cw_after['queue'], $cw_after['seen'], hodima_seo_advisor_crawl_status()['pages'], get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION )['source'] ?? '' ], [ 'idle', true, [], [], 6, 'crawler' ] );
$cw_codes = [];
foreach ( hodima_seo_advisor_crawl_issues() as $issue_row ) {
	foreach ( $issue_row['issues'] as $issue ) {
		$cw_codes[ $issue_row['path'] ][] = $issue['code'];
	}
}
hodima_ad( 'ایرادهای موجود روی داده خزنده کار می‌کنند: خانه بدون H1 نیست، /about/ بدون متا، /b/ ریدایرکت نه', [ in_array( 'no_meta', $cw_codes['/about/'] ?? [], true ), in_array( 'no_h1', $cw_codes['/'] ?? [], true ), isset( $cw_codes['/old/'] ) ], [ true, false, true ] );

// وسط دور کامل دوم (SEO 3.7.4): صفحه خوانده‌شده لینک ورودی و عمق دور قبل را نگه می‌دارد تا finalize از نو بشمارد.
// باگ قبلی: هر صفحه با «لینک ورودی ۰» نوشته می‌شد و وسط خزش صدها ایراد ساختگی «بدون لینک ورودی» و افت نمره دیده می‌شد.
$cw_mid = [];
add_filter(
	'pre_http_request',
	$cw_snap = static function ( mixed $pre ) use ( &$cw_mid, $cw_t ): mixed {
		$cw_mid = []; // آخرین عکس پیش از درخواست آخر دور (بعد از خوانده شدن /a/ و /b/)
		foreach ( (array) $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( 'SELECT path, inlinks, depth FROM %i WHERE path IN (%s, %s)', $cw_t, '/a/', '/b/' ), ARRAY_A ) as $r ) {
			$cw_mid[ $r['path'] ] = [ (int) $r['inlinks'], (int) $r['depth'] ];
		}
		$memo = &hodima_seo_advisor_crawl_issues_cache();
		$memo = null;
		foreach ( hodima_seo_advisor_crawl_issues() as $issue_row ) {
			if ( in_array( 'orphan', array_column( $issue_row['issues'], 'code' ), true ) ) {
				$cw_mid['orphan'][] = $issue_row['path'];
			}
		}
		return $pre;
	},
	5
);
$cw_s         = hodima_seo_advisor_crawler_state();
$cw_s['status'] = 'idle';
hodima_seo_advisor_crawler_save( $cw_s );
hodima_seo_advisor_crawler_start();
$cw_s         = hodima_seo_advisor_crawler_state();
$cw_s['seed'] = [];
hodima_seo_advisor_crawler_save( $cw_s );
$cw_out2 = hodima_seo_advisor_crawler_tick( true );
remove_filter( 'pre_http_request', $cw_snap, 5 );
hodima_ad( 'وسط دور کامل: /a/ و /b/ (خوانده‌شده، پیش از پایان) لینک ورودی و عمق دور قبل را دارند و ایراد ساختگی «بدون لینک ورودی» ساخته نمی‌شود', [ $cw_out2['finished'], $cw_mid['/a/'] ?? null, $cw_mid['/b/'] ?? null, $cw_mid['orphan'] ?? [] ], [ true, [ 2, 1 ], [ 2, 1 ], [] ] );
hodima_ad( 'بعد از دور دوم همان شمارش دور اول (finalize از نو می‌شمارد)', array_map( static fn( string $p ): int => (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT inlinks FROM %i WHERE path = %s', $cw_t, $p ) ), [ '/b/', '/a/' ] ), [ 2, 2 ] );

$cw_requested = [];
$cw_again     = hodima_seo_advisor_crawler_tick();
hodima_ad( 'بیرون از موعد (دور تازه تمام شده): نوبت بعدی هیچ درخواستی نمی‌زند', [ $cw_again['ran'], count( $cw_requested ) ], [ 0, 0 ] );
hodima_seo_advisor_crawler_start();

// سایت از دسترس خارج: پنج خطای پی‌درپی ← مکث نیم‌ساعته؛ مکث ← هیچ درخواستی
$cw_s          = hodima_seo_advisor_crawler_state();
$cw_s['seed']  = [];
$cw_s['queue'] = array_map( static fn( int $i ): string => $cw_site . '/p' . $i . '/', range( 1, 8 ) ); // پنج خطا ← مکث، پیش از ته‌کشیدن صف
hodima_seo_advisor_crawler_save( $cw_s );
$cw_mode      = 'down';
$cw_requested = [];
hodima_seo_advisor_crawler_tick( true );
$cw_paused = hodima_seo_advisor_crawler_state();
hodima_ad( 'سایت پاسخ نمی‌دهد: خزنده به‌جای کوبیدن، مکث می‌کند (وضعیت «paused»)', [ $cw_paused['pause_until'] > time() + 1000, hodima_seo_advisor_crawler_status()['state'], 'running' === $cw_paused['status'] ], [ true, 'paused', true ] );
$cw_requested = [];
hodima_seo_advisor_crawler_tick( true );
hodima_ad( 'در مکث نوبت‌ها درخواستی نمی‌زنند', count( $cw_requested ), 0 );

// «درخواست زیاد است» (SEO 3.8.7): باگ تا ۳.۸.۶: ۴۲۹ مثل صفحه ذخیره و «صفحه خراب» می‌شد و ردیف سالم قبلی پاک می‌شد
$cw_throttle = static function ( string $mode ) use ( &$cw_mode, &$cw_requested, $cw_site ): array {
	$s                = hodima_seo_advisor_crawler_state();
	$s['status']      = 'running';
	$s['seed']        = [];
	$s['queue']       = [];
	$s['seen']        = [];
	hodima_seo_advisor_crawler_enqueue( $s, $cw_site . '/a/', true ); // با seen، تا پایان دور ردیف‌ها را «دیده‌نشده» پاک نکند
	hodima_seo_advisor_crawler_enqueue( $s, $cw_site . '/b/', true );
	$s['pause_until'] = 0;
	$s['throttled']   = 0;
	$s['errors']      = 0;
	hodima_seo_advisor_crawler_save( $s );
	$cw_mode      = $mode;
	$cw_requested = [];
	hodima_seo_advisor_crawler_tick( true );
	$after = hodima_seo_advisor_crawler_state();
	$row   = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT status, title_len FROM %i WHERE path = %s', hodima_seo_advisor_table( 'crawl_pages' ), '/a/' ), ARRAY_A );
	return [ 'requests' => count( $cw_requested ), 'pause' => $after['pause_until'] - time(), 'queue0' => $after['queue'][0] ?? '', 'queue_n' => count( $after['queue'] ), 'status' => (int) ( $row['status'] ?? 0 ), 'view' => hodima_seo_advisor_crawler_status() ];
};
$cw_th = $cw_throttle( 'throttle' );
hodima_ad( '۴۲۹: یک درخواست، مکث دست‌کم نیم‌ساعت، همان آدرس سر صف، ردیف قبلی /a/ (۲۰۰) دست‌نخورده، پیام «درخواست زیاد است»', [ $cw_th['requests'], $cw_th['pause'] >= HODIMA_SEO_ADVISOR_CRAWLER_PAUSE - 5, $cw_th['queue0'], $cw_th['queue_n'], $cw_th['status'], $cw_th['view']['state'], $cw_th['view']['throttled'] ], [ 1, true, $cw_site . '/a/', 2, 200, 'paused', true ] );
$cw_mt = $cw_throttle( 'maint' );
hodima_ad( '۵۰۳ با Retry-After دور: مکث تا سقف ۶ ساعت، ردیف دست‌نخورده', [ $cw_mt['requests'], $cw_mt['pause'] <= HODIMA_SEO_ADVISOR_CRAWLER_THROTTLE_MAX && $cw_mt['pause'] > 5 * HOUR_IN_SECONDS, $cw_mt['status'] ], [ 1, true, 200 ] );
hodima_ad( 'throttle_seconds: ۴۲۹ بی Retry-After ← نیم‌ساعت؛ ۵۰۳ بی Retry-After ← ۰ (خطای واقعی)؛ ۲۰۰ ← ۰', [ hodima_seo_advisor_crawler_throttle_seconds( [ 'status' => 429, 'retry' => '' ] ), hodima_seo_advisor_crawler_throttle_seconds( [ 'status' => 503, 'retry' => '' ] ), hodima_seo_advisor_crawler_throttle_seconds( [ 'status' => 200, 'retry' => '60' ] ) ], [ HODIMA_SEO_ADVISOR_CRAWLER_PAUSE, 0, 0 ] );
$cw_e5 = $cw_throttle( 'error503' );
hodima_ad( '۵۰۳ بدون Retry-After: مثل قبل ذخیره می‌شود (خطای سرور) و مکث از «درخواست زیاد» نیست', [ $cw_e5['status'], $cw_e5['view']['throttled'] ], [ 503, false ] );
$cw_mode = 'site';

// SEO 3.9.3: ۵۰۲/۵۰۴ گذرا یک بار دوباره خوانده می‌شود (قبلا فقط خطای اتصال)؛ ۴۰۴ و ۵۰۳ با Retry-After دوباره خوانده نمی‌شوند
$cw_calls  = [];
$cw_retry  = static function ( mixed $pre, array $args, string $url ) use ( &$cw_calls, $cw_site ): mixed {
	if ( ! str_starts_with( $url, $cw_site . '/retry-' ) ) {
		return $pre;
	}
	$name              = (string) wp_parse_url( $url, PHP_URL_PATH );
	$cw_calls[ $name ] = ( $cw_calls[ $name ] ?? 0 ) + 1;
	return match ( $name ) {
		'/retry-flaky/'  => $cw_calls[ $name ] < 2 ? [ 'response' => [ 'code' => 502 ], 'headers' => [], 'body' => '' ] : [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html></html>' ],
		'/retry-dead/'   => [ 'response' => [ 'code' => 504 ], 'headers' => [], 'body' => '' ],
		'/retry-after/'  => [ 'response' => [ 'code' => 503 ], 'headers' => [ 'retry-after' => '120' ], 'body' => '' ],
		default          => [ 'response' => [ 'code' => 404 ], 'headers' => [], 'body' => '' ],
	};
};
add_filter( 'pre_http_request', $cw_retry, 20, 3 ); // بعد از فیلتر سایت ساختگی (اولویت ۱۰) که برای مسیر ناشناخته ۴۰۴ می‌دهد
add_filter( 'hodima_seo_advisor_crawler_retry_delay', '__return_zero' );
$cw_f1 = hodima_seo_advisor_crawler_fetch_checked( $cw_site . '/retry-flaky/' );
$cw_f2 = hodima_seo_advisor_crawler_fetch_checked( $cw_site . '/retry-dead/' );
$cw_f3 = hodima_seo_advisor_crawler_fetch_checked( $cw_site . '/retry-after/' );
$cw_f4 = hodima_seo_advisor_crawler_fetch_checked( $cw_site . '/retry-gone/' );
remove_filter( 'pre_http_request', $cw_retry, 20 );
remove_filter( 'hodima_seo_advisor_crawler_retry_delay', '__return_zero' );
hodima_ad( '۵۰۲ گذرا: بار دوم ۲۰۰ می‌شود و همان ثبت می‌شود (دو درخواست)', [ $cw_f1['status'] ?? 0, $cw_calls['/retry-flaky/'] ?? 0 ], [ 200, 2 ] );
hodima_ad( '۵۰۴ ماندگار: یک تلاش دوباره، بعد همان ۵۰۴ (دو درخواست، نه بیشتر)', [ $cw_f2['status'] ?? 0, $cw_calls['/retry-dead/'] ?? 0 ], [ 504, 2 ] );
hodima_ad( '۵۰۳ با Retry-After و ۴۰۴: تلاش دوباره ندارند (یک درخواست)', [ $cw_calls['/retry-after/'] ?? 0, $cw_calls['/retry-gone/'] ?? 0, $cw_f3['status'] ?? 0, $cw_f4['status'] ?? 0 ], [ 1, 1, 503, 404 ] );

// SEO 3.9.3: «خزش همین حالا» وسط مکث: ادامه زنجیره برای بعد از پایان مکث زمان‌بندی می‌شود (قبلا نه، و با خزنده شبانه خاموش خزش نیمه‌کاره می‌ماند)
$cw_m                = hodima_seo_advisor_crawler_state();
$cw_m['status']      = 'running';
$cw_m['manual']      = true;
$cw_m['seed']        = [];
$cw_m['queue']       = [];
$cw_m['seen']        = [];
hodima_seo_advisor_crawler_enqueue( $cw_m, $cw_site . '/a/', true );
$cw_m['pause_until'] = 0;
$cw_m['throttled']   = 0;
$cw_m['errors']      = 0;
hodima_seo_advisor_crawler_save( $cw_m );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRAWLER_CRON );
$cw_mode = 'throttle';
hodima_seo_advisor_crawler_tick( true );
$cw_pm   = hodima_seo_advisor_crawler_state();
$cw_next = wp_next_scheduled( HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ (int) $cw_pm['pause_until'] + 5 ] );
hodima_ad( 'مکث وسط «خزش همین حالا»: ادامه ۵ ثانیه بعد از پایان مکث زمان‌بندی می‌شود', [ $cw_pm['pause_until'] > time(), (int) $cw_pm['pause_until'] + 5 === $cw_next ], [ true, true ] );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ (int) $cw_pm['pause_until'] + 5 ] );
$cw_pm['manual'] = false;
$cw_pm['pause_until'] = 0;
hodima_seo_advisor_crawler_save( $cw_pm );
$cw_mode = 'site';

// SEO 3.9.3: نقشه سایت خراب بی‌صدا نیست؛ gzip باز می‌شود
$cw_sm_urlset = static fn( string $loc ): string => '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>' . $loc . '</loc></url></urlset>';
$cw_sm        = static function ( mixed $pre, array $args, string $url ) use ( $cw_site, $cw_sm_urlset ): mixed {
	if ( ! str_starts_with( $url, $cw_site . '/sm-' ) ) {
		return $pre;
	}
	$body = match ( (string) wp_parse_url( $url, PHP_URL_PATH ) ) {
		'/sm-bad.xml'   => '<urlset><url><loc>https://x',
		'/sm-empty.xml' => '',
		'/sm-big.xml'   => $cw_sm_urlset( $cw_site . '/big/' ) . str_repeat( ' ', 2 * MB_IN_BYTES ),
		'/sm-gz.xml.gz' => (string) gzencode( $cw_sm_urlset( $cw_site . '/gz-page/' ) ),
		default         => $cw_sm_urlset( $cw_site . '/ok-page/' ),
	};
	return [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'application/xml' ], 'body' => $body ];
};
add_filter( 'pre_http_request', $cw_sm, 20, 3 );
$cw_smk = static function ( string $name ) use ( $cw_site ): array {
	$st                     = hodima_seo_advisor_crawler_state();
	$st['maps']             = 0;
	$st['sitemap']          = [];
	$st['sitemap_problems'] = [];
	hodima_seo_advisor_crawler_sitemap( $cw_site . $name, $st );
	return [ 'kinds' => array_column( $st['sitemap_problems'], 'k' ), 'paths' => array_keys( $st['sitemap'] ) ];
};
$cw_sm_bad = $cw_smk( '/sm-bad.xml' );
$cw_sm_emp = $cw_smk( '/sm-empty.xml' );
$cw_sm_big = $cw_smk( '/sm-big.xml' );
$cw_sm_gz  = $cw_smk( '/sm-gz.xml.gz' );
$cw_sm_ok  = $cw_smk( '/sm-ok.xml' );
remove_filter( 'pre_http_request', $cw_sm, 20 );
hodima_ad( 'نقشه سایت: XML نامعتبر و فایل خالی ← مشکل «invalid» و بدون آدرس؛ بیشتر از ۲ مگابایت ← «big»', [ $cw_sm_bad, $cw_sm_emp, $cw_sm_big ], [ [ 'kinds' => [ 'invalid' ], 'paths' => [] ], [ 'kinds' => [ 'invalid' ], 'paths' => [] ], [ 'kinds' => [ 'big' ], 'paths' => [] ] ] );
hodima_ad( 'نقشه سایت: فایل gzip باز و خوانده می‌شود (بدون مشکل)؛ فایل سالم بدون مشکل', [ $cw_sm_gz, $cw_sm_ok ], [ [ 'kinds' => [], 'paths' => [ '/gz-page/' ] ], [ 'kinds' => [], 'paths' => [ '/ok-page/' ] ] ] );
$cw_q = hodima_seo_advisor_sitemap_problems_clean( [ [ 'u' => '/a.xml', 'k' => 'invalid' ], [ 'u' => '/b.xml', 'k' => 'big' ], [ 'u' => '/c.xml', 'k' => 'zzz' ] ] );
hodima_ad( 'تمیزکاری مشکل‌های نقشه: «invalid» و «big» پذیرفته، نوع ناشناخته رد', array_column( $cw_q, 'k' ), [ 'invalid', 'big' ] );

update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
$cw_s                = hodima_seo_advisor_crawler_state();
$cw_s['pause_until'] = 0;
hodima_seo_advisor_crawler_save( $cw_s );
$cw_requested = [];
hodima_ad( 'خاموش: نوبت چیزی نمی‌خزد و وضعیت «off»', [ hodima_seo_advisor_crawler_tick()['ran'], count( $cw_requested ), hodima_seo_advisor_crawler_status()['state'] ], [ 0, 0, 'off' ] );
hodima_ad( 'پاک کردن داده‌های دستار وضعیت خزنده را پاک می‌کند ولی تنظیمات می‌ماند', [ hodima_seo_advisor_clear_data(), get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, 'none' ), get_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS )['enabled'] ?? null ], [ true, 'none', false ] );

remove_filter( 'pre_http_request', $cw_filter, 10 );
remove_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
false === $cw_settings ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, $cw_settings, false );
false === $cw_state ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, $cw_state, false );
false === $cw_crawl ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $cw_crawl, false );

echo "=== ایرادهای تازه خزش (SEO 3.1.5)\n";
$cx_site = untrailingslashit( home_url() );
$cx_words = static fn( int $n ): string => implode( ' ', array_fill( 0, $n, 'کلمه' ) );
$cx_crawl = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false );
$cx_page  = static fn( string $title, string $body, string $head = '' ): string => '<html><head><title>' . $title . '</title><meta name="description" content="توضیح کافی برای این صفحه آزمایشی">' . $head . '</head><body><main>' . $body . '</main></body></html>';
$cx_pages = [
	'/'                => $cx_page( 'خانه آزمایشی با عنوان به اندازه کافی بلند', '<h1>یک</h1><h1>دو</h1><a href="/broken/">خراب</a> <a href="/old/">قدیمی</a> <a href="/chain1/">زنجیره</a> <a href="/noidx/">ناایندکس</a> <a href="http://hodima.test/b/">ب</a> <a href="/d1/">عمق</a> <a href="/canbad/">ک</a> <a href="/thin/">کم</a> <img src="/a.jpg"><img src="/b.jpg" alt="دارد"><img src="/c.jpg" alt="تزئینی" aria-hidden="true">' ),
	'/b/'              => $cx_page( 'صفحه ب با متن کافی و عنوان بلند برای آزمون', '<h1>ب</h1><p>' . $cx_words( 200 ) . '</p><a href="/">خانه</a>', '<link rel="canonical" href="' . $cx_site . '/b/">' ),
	'/noidx/'          => $cx_page( 'صفحه ناایندکس با عنوان به اندازه کافی بلند', '<h1>ن</h1>', '<meta name="robots" content="noindex"><link rel="canonical" href="' . $cx_site . '/noidx/">' ),
	'/canbad/'         => $cx_page( 'صفحه با canonical اشتباه و عنوان بلند کافی', '<h1>ک</h1><p>' . $cx_words( 200 ) . '</p>', '<link rel="canonical" href="' . $cx_site . '/broken/">' ),
	'/thin/'           => $cx_page( 'صفحه کم‌متن با عنوان به اندازه کافی بلند', '<h1>کم</h1><p>' . $cx_words( 30 ) . '</p>', '<link rel="canonical" href="' . $cx_site . '/thin/">' ),
	'/orphan-sm/'      => $cx_page( 'صفحه فقط در نقشه سایت با عنوان بلند کافی', '<h1>ی</h1><p>' . $cx_words( 200 ) . '</p>', '<link rel="canonical" href="' . $cx_site . '/orphan-sm/">' ),
];
foreach ( range( 1, 6 ) as $i ) {
	$cx_pages[ "/d{$i}/" ] = $cx_page( "عمق {$i} با عنوان به اندازه کافی بلند", "<h1>د{$i}</h1><p>" . $cx_words( 200 ) . '</p>' . ( $i < 6 ? '<a href="/d' . ( $i + 1 ) . '/">بعدی</a>' : '' ), '<link rel="canonical" href="' . $cx_site . "/d{$i}/\">" );
}
$cx_sitemap = '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"><url><loc>' . $cx_site . '/</loc></url><url><loc>' . $cx_site . '/b/</loc><image:image><image:loc>' . $cx_site . '/wp-content/uploads/x.jpg</image:loc></image:image></url><url><loc>' . $cx_site . '/in-sitemap-404/</loc></url><url><loc>' . $cx_site . '/orphan-sm/</loc></url><url><loc>https://elsewhere.test/x/</loc></url></urlset>';
$cx_index   = '<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>' . $cx_site . '/part-sitemap.xml</loc></sitemap></sitemapindex>';
add_filter(
	'pre_http_request',
	$cx_filter = static function ( mixed $pre, array $args, string $url ) use ( &$cx_pages, $cx_site, $cx_sitemap, $cx_index ): mixed {
		if ( ! str_starts_with( $url, $cx_site ) ) {
			return $pre;
		}
		$path = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$xml  = static fn( string $body ): array => [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'application/xml' ], 'body' => $body ];
		if ( '/sitemap.xml' === $path ) {
			return $xml( $cx_index );
		}
		if ( '/part-sitemap.xml' === $path ) {
			return $xml( $cx_sitemap );
		}
		$redirects = [ '/old/' => '/b/', '/chain1/' => '/chain2/', '/chain2/' => '/b/' ];
		if ( isset( $redirects[ $path ] ) ) {
			return [ 'response' => [ 'code' => 301 ], 'headers' => [ 'location' => $cx_site . $redirects[ $path ] ], 'body' => '' ];
		}
		if ( isset( $cx_pages[ $path ] ) ) {
			return [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'text/html; charset=UTF-8' ], 'body' => $cx_pages[ $path ] ];
		}
		return [ 'response' => [ 'code' => 404 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html><head><title>پیدا نشد</title></head><body><h1>404</h1></body></html>' ];
	},
	10,
	3
);
add_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
echo "=== فهرست واحد ایرادها و رفع مثبت‌های کاذب خزش (SEO 3.3.5)\n";
$ic_codes = array_keys( hodima_seo_advisor_issue_catalog() );
$ic_used  = array_unique( array_merge( ...array_values( HODIMA_SEO_ADVISOR_CRAWL_GROUPS ), ...array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'codes' ) ) );
hodima_ad( 'هر کد گروه چیپ‌ها و بخش نمره در فهرست واحد ایرادها هست و هر رکورد برچسب، سطح و سختی معتبر دارد', [
	array_values( array_diff( $ic_used, $ic_codes ) ),
	array_filter( hodima_seo_advisor_issue_catalog(), static fn( array $r ): bool => '' === $r['label'] || ! in_array( $r['level'], [ 'bad', 'warn', 'info' ], true ) || ! in_array( $r['effort'], [ 1, 2, 3 ], true ) || '' === $r['fix'] ),
], [ [], [] ] );
hodima_ad( 'سازگاری crawl_issue_info: برچسب، سطح، دستور بدون جانگهدار', [ hodima_seo_advisor_crawl_issue_info( 'no_meta' )['label'], hodima_seo_advisor_crawl_issue_info( 'no_meta' )['level'], str_contains( hodima_seo_advisor_crawl_issue_info( 'no_meta' )['fix'], '{where}' ), hodima_seo_advisor_crawl_issue_info( 'x_none' ) ], [ 'بدون توضیح متا', 'warn', false, [ 'label' => 'x_none', 'level' => 'info', 'fix' => '' ] ] ); // SEO 3.6.2: no_meta از bad به warn (و برای صفحه کم‌بازدید info)
hodima_ad( 'دستور با نام «ویرایش» نوع صفحه؛ خالی = «ویرایش صفحه»؛ ایراد بی‌جانگهدار بدون تغییر', [ str_contains( hodima_seo_advisor_issue_fix( 'no_meta', 'ویرایش محصول' ), '«ویرایش محصول» را بزنید' ), str_contains( hodima_seo_advisor_issue_fix( 'no_meta' ), '«ویرایش صفحه» را بزنید' ), hodima_seo_advisor_issue_fix( 'orphan', 'ویرایش دسته' ), hodima_seo_advisor_issue_fix( 'x_none' ) ], [ true, true, 'یک یا دو صفحه مرتبط انتخاب کنید: صفحه‌های پیشنهادی زیر (کلمه مشترک دارند) یا دسته همین محصول. در متن آن صفحه یک لینک با نام این صفحه بدهید و ذخیره کنید.', '' ] ); // SEO 3.6.3: متن orphan طبق گوگل («دست‌کم یک صفحه»)؛ SEO 3.6.9: دستور گام‌به‌گام
$ic_pt = get_post_type_object( 'post' );
hodima_ad( 'نام «ویرایش» نوع صفحه: صفحه اصلی، نوشته و دسته از برچسب‌های وردپرس؛ ناشناخته خالی', [ hodima_seo_advisor_issue_where( '/' ), hodima_seo_advisor_issue_where( '/x/', 'post' ) === (string) $ic_pt->labels->edit_item, hodima_seo_advisor_issue_where( '/x/', 'category' ) === (string) get_taxonomy( 'category' )->labels->edit_item, hodima_seo_advisor_issue_where( '/x/', 'nothing-here' ) ], [ 'ویرایش صفحه اصلی', true, true, '' ] );
// SEO 3.6.2: طبق Google Search Central عنوان حداقل/حداکثر ندارد؛ «کوتاه» و «بلند» سطح اطلاع‌اند، دستور کوتاه عدد کف نمی‌گوید و دستور بلند حدود سقف (بریده‌شدن) را
hodima_ad( 'عنوان کوتاه/بلند: آستانه پیکسلی (کف ۲۹۰، سقف ۵۸۰)؛ هر دو سطح اطلاع؛ دستور کوتاه بدون عدد کف، دستور بلند با سقف؛ بدون «نویسه»', [ hodima_seo_px_limit( 'title' ), hodima_seo_advisor_crawl_issue_info( 'title_short' )['level'], hodima_seo_advisor_crawl_issue_info( 'title_long' )['level'], str_contains( hodima_seo_advisor_issue_fix( 'title_short' ), '290' ), str_contains( hodima_seo_advisor_issue_catalog()['title_long']['seen'], '580' ) /* SEO 3.6.9: سقف در «چه دیدیم» */, str_contains( hodima_seo_advisor_issue_fix( 'title_short' ) . hodima_seo_advisor_issue_fix( 'title_long' ), 'نویسه' ) ], [ [ 'max' => 580, 'min' => 290, 'font_px' => 20 ], 'info', 'info', false, true, false ] );

$ic_t = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $ic_t ) );
foreach ( [ [ '/', 0, 5, 100, 50 ], [ '/blog/page/2/', 1, 5, 0, 10 ], [ '/plain/', 1, 5, 0, 10 ], [ '/island/', HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE, 5, 100, 50 ], [ '/orphan/', HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE, 0, 100, 50 ], [ '/deep/', 6, 5, 100, 50 ], [ '/ok/', 1, 4, 100, 50 ] ] as [ $ic_p, $ic_d, $ic_in, $ic_meta, $ic_tl ] ) {
	$GLOBALS['wpdb']->insert( $ic_t, [ 'path' => $ic_p, 'status' => 200, 'indexable' => 1, 'title' => 'عنوان ' . $ic_p, 'title_len' => $ic_tl, 'meta_len' => $ic_meta, 'title_px' => hodima_ad_tpx( $ic_tl ), 'meta_px' => hodima_ad_mpx( $ic_meta ), 'h1' => 'تیتر ' . $ic_p, 'resp_ms' => 100, 'inlinks' => $ic_in, 'text_links' => 1, 'depth' => $ic_d, 'words' => 300, 'h1_count' => 1, 'img_noalt' => 0, 'http_links' => 0, 'canonical' => $ic_p ] );
}
hodima_seo_advisor_crawl_map( true );
$ic_issues = [];
foreach ( hodima_seo_advisor_crawl_issues() as $ic_row ) {
	$ic_issues[ $ic_row['path'] ] = array_column( $ic_row['issues'], 'code' );
}
hodima_ad( 'صفحه‌بندی (/page/N/) بدون «بدون متا» و «عنوان کوتاه»؛ صفحه معمولی هر دو را می‌گیرد', [ $ic_issues['/blog/page/2/'] ?? [], $ic_issues['/plain/'] ?? [] ], [ [], [ 'title_short', 'no_meta' ] ] );
hodima_ad( 'عمق: صفحه نارسیده با لینک ورودی «جدا از صفحه اصلی» (نه «عمیق»)؛ نارسیده بی‌لینک فقط «بدون لینک ورودی»؛ عمق ۶ «عمیق»؛ سالم بی‌ایراد', [ $ic_issues['/island/'] ?? [], $ic_issues['/orphan/'] ?? [], $ic_issues['/deep/'] ?? [], $ic_issues['/ok/'] ?? [] ], [ [ 'island' ], [ 'orphan' ], [ 'deep' ], [] ] );
// فهرست تصویر در ایرادها: آدرس‌ها و شمار کل
$GLOBALS['wpdb']->update( $ic_t, [ 'img_noalt' => 10, 'img_list' => "https://x.test/a.jpg\nhttps://x.test/b.jpg" ], [ 'path' => '/ok/' ] );
hodima_seo_advisor_crawl_map( true ); // کش ایرادها (SEO 3.4.3)
$ic_img = null;
foreach ( hodima_seo_advisor_crawl_issues() as $ic_row ) {
	foreach ( '/ok/' === $ic_row['path'] ? $ic_row['issues'] : [] as $ic_issue ) {
		$ic_img = 'img_alt' === $ic_issue['code'] ? $ic_issue : $ic_img;
	}
}
hodima_ad( 'ایراد img_alt: ردیف قدیمی (یک آدرس در هر خط) خوانده می‌شود و شمار کل می‌ماند', [ $ic_img['images'] ?? null, $ic_img['total'] ?? null ], [ [ [ 'u' => 'https://x.test/a.jpg', 's' => 'missing', 'c' => '' ], [ 'u' => 'https://x.test/b.jpg', 's' => 'missing', 'c' => '' ] ], 10 ] );
// «alt خالی (بررسی کنید)»: ایراد جدا، «تزئینی است» از ایراد برمی‌دارد و فقط ادمین دستی می‌زند
$GLOBALS['wpdb']->update( $ic_t, [ 'img_noalt' => 0, 'img_empty' => 2, 'img_list' => '[{"u":"https://x.test/l1.png","s":"empty","c":"div.logos"},{"u":"https://x.test/l2.png","s":"empty","c":"div.logos"}]' ], [ 'path' => '/ok/' ] );
hodima_seo_advisor_crawl_map( true );
$ic_codes = static function () use ( $ic_t ): array {
	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		if ( '/ok/' === $row['path'] ) {
			return array_column( $row['issues'], 'total', 'code' );
		}
	}
	return [];
};
hodima_ad( 'alt خالی تنها: فقط «بررسی کنید» (img_empty)، نه img_alt؛ در گروه نمره نیست', [ $ic_codes()['img_empty'] ?? null, isset( $ic_codes()['img_alt'] ), in_array( 'img_empty', array_merge( ...array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'codes' ) ), true ) ], [ 2, false, false ] );
delete_option( HODIMA_SEO_ADVISOR_IMG_MARKS_OPTION );
hodima_ad( 'علامت: «تزئینی است» یکی را برمی‌دارد و شمار می‌شود ۱؛ «alt می‌خواهد» آن یکی را به ایراد img_alt می‌برد', [ hodima_seo_advisor_img_mark_set( 'https://x.test/l1.png', 'ok' ), $ic_codes()['img_empty'] ?? null ], [ true, 1 ] );
hodima_seo_advisor_img_mark_set( 'https://x.test/l2.png', 'need' );
hodima_ad( 'علامت «alt می‌خواهد»: بررسی‌کنیدی نمی‌ماند، ایراد img_alt با ۱ تصویر', [ isset( $ic_codes()['img_empty'] ), $ic_codes()['img_alt'] ?? null ], [ false, 1 ] );
hodima_seo_advisor_img_mark_set( 'https://x.test/l1.png', 'reset' );
hodima_seo_advisor_img_mark_set( 'https://x.test/l2.png', 'reset' );
hodima_ad( 'علامت برداشته شد: هر دو دوباره «بررسی کنید»؛ آدرس نامعتبر و علامت ناشناخته رد می‌شود', [ $ic_codes()['img_empty'] ?? null, hodima_seo_advisor_img_mark_set( '', 'ok' ), hodima_seo_advisor_img_mark_set( 'https://x.test/a.png', 'zzz' ) ], [ 2, false, false ] );
delete_option( HODIMA_SEO_ADVISOR_IMG_MARKS_OPTION );
$GLOBALS['wpdb']->update( $ic_t, [ 'img_noalt' => 10, 'img_empty' => -1, 'img_list' => "https://x.test/a.jpg\nhttps://x.test/b.jpg" ], [ 'path' => '/ok/' ] );
hodima_seo_advisor_crawl_map( true );

// جزئیات داخل جدول «سلامت سایت» (SEO 3.5.3): همان رندر مشترک «کارها»
$GLOBALS['wpdb']->update( $ic_t, [ 'img_noalt' => 1, 'img_empty' => 0, 'img_list' => '[{"u":"https://x.test/wp-content/uploads/banner-a.webp","s":"missing","c":"div.category-content"}]' ], [ 'path' => '/ok/' ] );
hodima_seo_advisor_crawl_map( true );
$ic_crawl_opt = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false );
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => 5, 'links' => 0, 'files' => [], 'source' => 'crawler' ], false );
wp_set_current_user( 1 );
$_GET    = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
$ic_view = $cap( static fn() => hodima_seo_advisor_render_overview() );
hodima_ad( 'جدول سلامت سایت: ایراد alt زیرش فهرست «کدام تصویرها؟» با نام فایل و جایگاه دارد (همان رندر «کارها»)', [ str_contains( $ic_view, 'banner-a.webp' ), str_contains( $ic_view, 'div.category-content' ), str_contains( $ic_view, 'کدام تصویرها؟' ) ], [ true, true, true ] );
$GLOBALS['wpdb']->update( $ic_t, [ 'img_noalt' => 10, 'img_empty' => -1, 'img_list' => "https://x.test/a.jpg\nhttps://x.test/b.jpg" ], [ 'path' => '/ok/' ] );
hodima_seo_advisor_crawl_map( true );
false === $ic_crawl_opt ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $ic_crawl_opt, false );
$_GET = [];
wp_set_current_user( 0 );

echo "=== پیش‌نویس عنوان/متا و جزئیات ایراد در «کارها» (SEO 3.3.6)\n";
hodima_ad( 'کوتاه‌کردن تمیز: سر جمله، وگرنه آخرین فاصله؛ کوتاه‌تر از سقف دست نمی‌خورد', [ hodima_seo_advisor_draft_cut( 'سلام دنیا. خوش آمدید به سایت ما', 20, 5 ), hodima_seo_advisor_draft_cut( 'یک دو سه چهار پنج شش', 12 ), hodima_seo_advisor_draft_cut( 'کوتاه', 50 ) ], [ 'سلام دنیا.', 'یک دو سه', 'کوتاه' ] );
$dr_long = hodima_seo_advisor_draft_title( '/x/', 'title_long', 'یک عنوان نسبتا بلند برای آزمون کوتاه‌سازی از انتها | نام دسته مورد نظر | نام سایت آزمایشی بلند' );
hodima_ad( 'پیش‌نویس عنوان بلند: از آخر جداکننده می‌افتد تا در ۹۵٪ سقف پیکسلی جا شود', [ hodima_seo_text_px( $dr_long, 'title' ) <= hodima_seo_advisor_draft_px_max( 'title' ), str_starts_with( $dr_long, 'یک عنوان نسبتا بلند' ), str_contains( $dr_long, 'نام سایت آزمایشی' ), hodima_seo_advisor_draft_title( '/x/', 'title_long', '' ) ], [ true, true, false, '' ] );

wp_set_current_user( 1 );
$dr_cat  = wp_insert_term( 'کلیپس آزمون پیش‌نویس', 'category' );
$dr_post = wp_insert_post( [ 'post_title' => 'کلیپس فرانسوی', 'post_status' => 'publish', 'post_type' => 'post', 'post_excerpt' => '', 'post_content' => '<p>کلیپس فرانسوی با کیفیت بالا برای مو پرپشت. مناسب انواع مدل‌های بستن مو و موجود در رنگ‌های مختلف.</p><p>ارسال به سراسر کشور.</p>' ] );
wp_set_post_categories( $dr_post, [ (int) ( is_array( $dr_cat ) ? $dr_cat['term_id'] : 0 ) ] );
$dr_path  = (string) hodima_seo_advisor_path_key( (string) get_permalink( $dr_post ) );
$dr_title = hodima_seo_advisor_draft_title( $dr_path, 'no_title' ); // title_short پیش‌نویس زیر کف را نشان نمی‌دهد (SEO 3.6.2)
$dr_meta  = hodima_seo_advisor_draft_meta( $dr_path );
hodima_ad( 'پیش‌نویس عنوان: نام + دسته + نام سایت تا ۶۲ نویسه', [ str_starts_with( $dr_title, 'کلیپس فرانسوی' ), str_contains( $dr_title, ' | ' ), mb_strlen( $dr_title ) <= 62 ], [ true, true, true ] );
hodima_ad( 'پیش‌نویس متا: نام صفحه و متن، تا ۱۵۵ نویسه، تمام‌شده سر جمله یا کلمه', [ str_contains( $dr_meta, 'کلیپس فرانسوی' ), mb_strlen( $dr_meta ) <= 155, mb_strlen( $dr_meta ) >= 70 ], [ true, true, true ] );
hodima_ad( 'صفحه ناشناخته: بی‌پیش‌نویس', [ hodima_seo_advisor_draft_title( '/no-such-page-xyz/', 'no_title' ), hodima_seo_advisor_draft_meta( '/no-such-page-xyz/' ) ], [ '', '' ] );

$dr_att = wp_insert_attachment( [ 'post_title' => 'تصویر آزمون', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ], 'hodima-test/dr-img-a.jpg' );
$dr_up  = wp_upload_dir();
$dr_url = $dr_up['baseurl'] . '/hodima-test/dr-img-a.jpg';
$dr_out = static function ( array $item, string $path ): string {
	ob_start();
	hodima_seo_advisor_render_issue_detail( $item, $path );
	return (string) ob_get_clean();
};
$dr_e   = static fn( string $u, string $st = 'missing', string $c = '' ): array => [ 'u' => $u, 's' => $st, 'c' => $c ];
$dr_i3  = $dr_out( [ 'src' => 'crawl', 'mark' => 'img_alt', 'images' => [ $dr_e( $dr_url ), $dr_e( 'https://cdn.test/x.png', 'missing', 'dialog#hd-notification-5' ) ], 'total' => 2 ], '/p/' );
$dr_i5  = $dr_out( [ 'src' => 'crawl', 'mark' => 'img_alt', 'images' => [ $dr_e( $dr_url ), $dr_e( 'https://cdn.test/x.png' ), $dr_e( 'https://cdn.test/y.png' ), $dr_e( 'https://cdn.test/z.png' ) ], 'total' => 7 ], '/p/' );
hodima_ad( 'فهرست تصویر: کتابخانه ← «ویرایش در رسانه»، بیرونی ← کپی آدرس؛ ≤۳ باز', [ str_contains( $dr_i3, 'ویرایش در رسانه' ), str_contains( $dr_i3, 'post=' . $dr_att ), str_contains( $dr_i3, 'dr-img-a.jpg' ), str_contains( $dr_i3, 'data-hdsa-copy-text="https://cdn.test/x.png"' ), str_contains( $dr_i3, 'بیرون از کتابخانه' ), str_contains( $dr_i3, '<details class="hodima-advisor__more" open>' ), str_contains( $dr_i3, 'پنجره نوتیفیکیشن (Hodima Media)' ) && str_contains( $dr_i3, 'dialog#hd-notification-5' ), str_contains( $dr_i3, 'کجا؟' ) && str_contains( $dr_i3, 'چه کنم؟' ), str_contains( $dr_i3, 'پیشنهاد متن' ) ], [ true, true, true, true, true, true, true, true, true ] );
hodima_ad( 'فهرست تصویر بیش از ۳: بسته و «و N تصویر دیگر»', [ str_contains( $dr_i5, '<details class="hodima-advisor__more">' ), str_contains( $dr_i5, 'و 3 تصویر دیگر' ), str_contains( $dr_i5, 'کدام تصویرها؟ (7)' ) ], [ true, true, true ] );
$dr_empty = $dr_out( [ 'src' => 'crawl', 'mark' => 'img_alt', 'images' => [], 'total' => 5 ], '/p/' );
// alt کتابخانه پر ولی HTML صفحه خالی: پیام جدا و پیوند «ویرایش صفحه»، نه «alt را در کتابخانه بنویسید» (SEO 3.4.1)
update_post_meta( $dr_att, '_wp_attachment_image_alt', 'گیره مو عمده' );
$dr_pg    = wp_insert_post( [ 'post_title' => 'صفحه سفارش آزمون', 'post_status' => 'publish', 'post_type' => 'page', 'post_name' => 'alt-lib-test', 'post_content' => '<img src="' . $dr_url . '">' ] );
$dr_alt   = $dr_out( [ 'src' => 'crawl', 'mark' => 'img_alt', 'images' => [ $dr_e( $dr_url ) ], 'total' => 1 ], (string) hodima_seo_advisor_path_key( (string) get_permalink( $dr_pg ) ) );
$dr_comp  = $dr_out( [ 'src' => 'crawl', 'mark' => 'img_alt', 'images' => [ $dr_e( $dr_url ) ], 'total' => 1 ], '/p/' );
hodima_ad( 'alt کتابخانه پر ولی در متن صفحه نیست: «از قالب یا افزونه» با کپی آدرس؛ نه «ویرایش صفحه»', [ str_contains( $dr_comp, 'از قالب یا افزونه' ), str_contains( $dr_comp, 'data-hdsa-copy-text="' . $dr_url . '"' ), str_contains( $dr_comp, 'ویرایش صفحه' ) ], [ true, true, false ] );
hodima_ad( 'alt کتابخانه پر و کپی‌شده در متن صفحه: برچسب «alt کتابخانه پر است»، متن alt، «ویرایش صفحه» و نبودن «ویرایش در رسانه»', [ str_contains( $dr_alt, 'alt کتابخانه پر است' ), str_contains( $dr_alt, 'گیره مو عمده' ), str_contains( $dr_alt, 'post=' . $dr_pg ), str_contains( $dr_alt, 'ویرایش در رسانه' ) ], [ true, true, true, false ] );
delete_post_meta( $dr_att, '_wp_attachment_image_alt' );
hodima_ad( 'فهرست تصویر خالی (خزش قدیمی): به‌جای پیوند بن‌بست به تب «سلامت سایت» دکمه «بازخوانی همین صفحه» با مسیر همان صفحه (SEO 3.4.9)؛ ایراد بی‌جزئیات و ردیف غیرخزنده: هیچ', [ str_contains( $dr_empty, 'value="hodima_advisor_recrawl"' ) && str_contains( $dr_empty, 'name="path" value="/p/"' ) && str_contains( $dr_empty, 'بازخوانی همین صفحه' ), ! str_contains( $dr_empty, 'tab=crawl' ) && ! str_contains( $dr_empty, '<a ' ), $dr_out( [ 'src' => 'crawl', 'mark' => 'orphan' ], '/p/' ), $dr_out( [ 'src' => 'opp', 'mark' => 'img_alt', 'images' => [ $dr_url ] ], '/p/' ) ], [ true, true, '', '' ] );
$dr_links = $dr_out( [ 'src' => 'crawl', 'mark' => 'link_broken', 'targets' => array_map( static fn( int $i ): array => [ 'path' => '/gone-' . $i . '/', 'note' => 'کد 404؛ متن لینک «x»' ], range( 1, 7 ) ) ], '/p/' );
hodima_ad( 'فهرست لینک خراب: ۵ مورد با کپی، بسته، «و ۲ لینک دیگر»', [ substr_count( $dr_links, 'class="hodima-advisor__li"' ), str_contains( $dr_links, 'و 2 لینک دیگر' ), str_contains( $dr_links, '<details class="hodima-advisor__more">' ), str_contains( $dr_links, 'data-hdsa-copy-url="https://hodima.test/gone-1/"' ) ], [ 5, true, true, true ] );
$dr_t = $dr_out( [ 'src' => 'crawl', 'mark' => 'no_title' ], $dr_path );
$dr_m = $dr_out( [ 'src' => 'crawl', 'mark' => 'no_meta' ], $dr_path );
hodima_ad( 'پیش‌نویس در کارها: عنوان با کپی و عرض پیکسلی؛ متا با کپی؛ ایراد بدون پیش‌نویس هیچ', [ str_contains( $dr_t, 'پیش‌نویس عنوان' ), str_contains( $dr_t, 'data-hdsa-copy-text="' . esc_attr( $dr_title ) . '"' ), str_contains( $dr_t, hodima_seo_text_px( $dr_title, 'title' ) . ' از 580 پیکسل' ), str_contains( $dr_m, 'پیش‌نویس توضیح متا' ), str_contains( $dr_m, 'data-hdsa-copy-text="' . esc_attr( $dr_meta ) . '"' ) ], [ true, true, true, true, true ] );
wp_delete_attachment( $dr_att, true );
wp_delete_post( $dr_post, true );

$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $ic_t ) );
hodima_seo_advisor_crawl_map( true );
