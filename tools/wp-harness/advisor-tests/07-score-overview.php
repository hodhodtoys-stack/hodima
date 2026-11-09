<?php
/**
 * بخش 7 از آزمون‌های دستار سئو (tools/wp-harness/advisor-tests.php آن را به ترتیب و در همان دامنه سراسری می‌خواند):
 * فشرده‌سازی پیشخوان، نمره، «کارها»، تب «خزش و داده» و پیشرفت.
 * تنهایی اجرا نمی‌شود: متغیرها و توابع کمکی (hodima_ad، $cap، …) از فایل اصلی و بخش‌های قبلی می‌آیند.
 */

declare(strict_types=1);

echo "=== فشرده‌سازی پیشخوان (SEO 3.3.0)\n";
$fs_keep = [ get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, null ), get_option( HODIMA_SEO_ADVISOR_CAPS_OPTION, null ) ];
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - HOUR_IN_SECONDS, 'end' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ), 'days' => 90, 'error' => '' ] + (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] ), false );
$fs_bar = $cap( static fn() => hodima_seo_advisor_render_toolbar( 'opportunities' ) );
hodima_ad( 'نوار کیفیت داده داخل همان کارت «گرفتن داده تازه» است (یک کارت، نه دو کارت)', [ substr_count( $fs_bar, '<section' ), str_contains( $fs_bar, 'hodima-advisor__quality--embedded' ), str_contains( $fs_bar, 'hds-card hodima-advisor__quality' ) ], [ 1, true, false ] );
wp_set_current_user( 1 );
$_GET = [];
$fs_page = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'صفحه: نوار کیفیت یک بار و از نوع جاسازی‌شده', [ substr_count( $fs_page, 'id="hodima-advisor-quality-title"' ), str_contains( $fs_page, 'hodima-advisor__quality--embedded' ) ], [ 1, true ] );
// ردیف یک کار تب «خزش و داده» (SEO 3.4.8؛ تا ۳.۴.۷ تابع render_job_crawler بود)
$job_card = static function ( string $key ) use ( $cap ): string {
	foreach ( hodima_seo_advisor_cron_health()['jobs'] as $job ) {
		if ( $key === $job['key'] ) {
			return $cap( static fn() => hodima_seo_advisor_render_job_card( $job, hodima_seo_advisor_cron_pills() ) );
		}
	}
	return '';
};
$fs_card = $job_card( 'crawler' );
hodima_ad( 'ردیف خزنده (SEO 3.4.8): «اجرا همین حالا» فرم جدا در ردیف؛ تنظیمات در پنل مستقل (نه details) با بازگشت به همین تب', [ ! str_contains( $fs_card, '<details' ), str_contains( $fs_card, 'class="hodima-advisor__cron-run"' ), str_contains( $fs_card, 'data-hdsa-job-panel' ), strpos( $fs_card, 'hodima-advisor__cron-run' ) < strpos( $fs_card, 'hodima-advisor__job-set' ), str_contains( $fs_card, 'name="tab" value="jobs"' ) ], [ true, true, true, true, true ] );
hodima_ad( 'فرم زمان‌بندی همان نام فیلدها را دارد (روشن‌بودن با کلید، حالت ساعت، از، تا، فاصله)', [ str_contains( $fs_card, 'class="hds-switch" name="enabled"' ), str_contains( $fs_card, 'name="always" value="0"' ), str_contains( $fs_card, 'name="always" value="1"' ), str_contains( $fs_card, 'name="start"' ), str_contains( $fs_card, 'name="end"' ), str_contains( $fs_card, 'name="interval"' ), str_contains( $fs_card, 'value="hodima_advisor_crawler_settings"' ), str_contains( $fs_card, 'value="hodima_advisor_crawler_now"' ) ], [ true, true, true, true, true, true, true, true ] );
hodima_ad( 'کارت خزش دیگر تب ندارد', str_contains( $fs_card, 'hds-tabset' ), false );
// پاکسازی (SEO 3.4.7)
hodima_ad( 'seed دور سریع: مسیر فارسی کدگذاری می‌شود (مثل live_fetch)', hodima_seo_advisor_crawler_seed_url( 'u:/گل-سر/' ), hodima_seo_advisor_url( '/' . rawurlencode( 'گل-سر' ) . '/' ) );
$pc_host = 'https://' . wp_parse_url( home_url(), PHP_URL_HOST ); // پیش از فیلتر: داخل فیلتر home_url() بازگشتی می‌شد
add_filter( 'pre_option_home', $pc_home = static fn(): string => $pc_host . '/blog' );
hodima_ad( 'نصب زیرپوشه‌ای: پیشوند /blog دوبار نمی‌آید', hodima_seo_advisor_crawler_normalize( $pc_host . '/blog/post/' ), $pc_host . '/blog/post/' );
remove_filter( 'pre_option_home', $pc_home );
$pc_p = hodima_seo_advisor_crawler_parse( '<html><head><title>t</title><meta name="googlebot" content="noindex, nosnippet"></head><body><main>x</main></body></html>', home_url( '/g/' ) );
hodima_ad( 'متای googlebot هم noindex و nosnippet را می‌رساند', [ str_contains( $pc_p['robots'], 'noindex' ), hodima_seo_advisor_robots_no_snippet( $pc_p['robots'] ) ], [ true, true ] );
$pc_t = hodima_seo_advisor_table( 'crawl_pages' );
$pc_long = str_repeat( 'ا', HODIMA_SEO_ADVISOR_KEY_LENGTH ); // عنوان بریده‌شده در ستون
foreach ( [ '/dt-a/' => 200, '/dt-b/' => 200, '/dt-c/' => 260 ] as $pc_path => $pc_len ) {
	$GLOBALS['wpdb']->delete( $pc_t, [ 'path' => $pc_path ] );
	$GLOBALS['wpdb']->insert( $pc_t, [ 'path' => $pc_path, 'status' => 200, 'indexable' => 1, 'title' => $pc_long, 'title_len' => $pc_len, 'meta_len' => 100, 'title_px' => hodima_ad_tpx( $pc_len ), 'meta_px' => hodima_ad_mpx( 100 ), 'h1' => 'تیتر ' . $pc_path, 'words' => 300, 'h1_count' => 1, 'canonical' => $pc_path, 'inlinks' => 5, 'text_links' => 3, 'depth' => 1 ] );
}
hodima_seo_advisor_crawl_map( true );
$pc_dup = [];
foreach ( hodima_seo_advisor_crawl_issues() as $pc_row ) {
	if ( str_starts_with( $pc_row['path'], '/dt-' ) ) {
		$pc_dup[ $pc_row['path'] ] = in_array( 'dup_title', array_column( $pc_row['issues'], 'code' ), true );
	}
}
hodima_ad( 'عنوان تکراری: بریده‌شده با طول واقعی یکسان تکراری است؛ همان ابتدا با طول واقعی متفاوت نه', [ $pc_dup['/dt-a/'] ?? false, $pc_dup['/dt-b/'] ?? false, $pc_dup['/dt-c/'] ?? false ], [ true, true, false ] );
$GLOBALS['wpdb']->query( "DELETE FROM {$pc_t} WHERE path LIKE '/dt-%'" );
hodima_seo_advisor_crawl_map( true );

// دقت خزنده (SEO 3.4.5): تلاش دوباره بعد از خطا، خواندن دوباره پاسخ کند، «محتوای کم» برای صفحه بدون نمایش گوگل
$fc_n      = 0;
$fc_filter = static function ( mixed $pre, array $args, string $url ) use ( &$fc_n ): mixed {
	if ( ! str_contains( $url, 'fc-accuracy-test' ) ) {
		return $pre;
	}
	++$fc_n;
	if ( 1 === $fc_n && str_contains( $url, 'timeout' ) ) {
		return new WP_Error( 'http_request_failed', 'timeout' );
	}
	if ( 1 === $fc_n && str_contains( $url, 'slow' ) ) {
		usleep( 2100000 ); // اولین خواندن کش سرد
	}
	return [ 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html></html>', 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [], 'filename' => null ];
};
add_filter( 'pre_http_request', $fc_filter, 5, 3 );
add_filter( 'hodima_seo_advisor_crawler_retry_delay', '__return_zero' );
$fc_res = hodima_seo_advisor_crawler_fetch_checked( home_url( '/fc-accuracy-test-timeout/' ) );
hodima_ad( 'خطای اتصال: یک بار دوباره تلاش می‌شود و نتیجه می‌آید', [ null !== $fc_res, $fc_n ], [ true, 2 ] );
$fc_n   = 0;
$fc_res = hodima_seo_advisor_crawler_fetch_checked( home_url( '/fc-accuracy-test-slow/' ) );
hodima_ad( 'پاسخ کندتر از آستانه: دوباره خوانده و سریع‌تر ثبت می‌شود', [ $fc_n, null !== $fc_res && $fc_res['ms'] < HODIMA_SEO_ADVISOR_CRAWL_SLOW_MS ], [ 2, true ] );
$fc_n   = 0;
$fc_res = hodima_seo_advisor_crawler_fetch_checked( home_url( '/fc-accuracy-test-fast/' ) );
hodima_ad( 'پاسخ سریع: فقط یک درخواست', [ $fc_n, null !== $fc_res ], [ 1, true ] );
remove_filter( 'pre_http_request', $fc_filter, 5 );
remove_filter( 'hodima_seo_advisor_crawler_retry_delay', '__return_zero' );

if ( function_exists( 'hodima_router_active' ) && hodima_router_active() ) {
	$th_post = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'صفحه کم‌محتوا', 'post_name' => 'thin-no-gsc-test', 'post_content' => 'متن کوتاه' ] );
	$th_path = (string) hodima_seo_advisor_path_key( (string) get_permalink( $th_post ) );
	$th_t    = hodima_seo_advisor_table( 'crawl_pages' );
	$GLOBALS['wpdb']->delete( $th_t, [ 'path' => $th_path ] );
	$GLOBALS['wpdb']->insert( $th_t, [ 'path' => $th_path, 'status' => 200, 'indexable' => 1, 'title' => 'عنوان آزمون محتوای کم با طول کافی', 'title_len' => 40, 'meta_len' => 100, 'title_px' => hodima_ad_tpx( 40 ), 'meta_px' => hodima_ad_mpx( 100 ), 'h1' => 'تیتر', 'words' => 12, 'h1_count' => 1, 'canonical' => $th_path, 'inlinks' => 5, 'text_links' => 3, 'depth' => 1 ] );
	hodima_seo_advisor_crawl_map( true );
	$th_codes = [];
	foreach ( hodima_seo_advisor_crawl_issues() as $th_row ) {
		if ( $th_path === $th_row['path'] ) {
			$th_codes = array_column( $th_row['issues'], 'code' );
		}
	}
	hodima_ad( 'محتوای کم: صفحه بدون نمایش گوگل (نوعش از روتر) هم گزارش می‌شود', in_array( 'thin', $th_codes, true ), true );
	$GLOBALS['wpdb']->delete( $th_t, [ 'path' => $th_path ] );
	wp_delete_post( $th_post, true );
	hodima_seo_advisor_crawl_map( true );
}

// نوار پیشرفت «اجرا همین حالا» (SEO 3.4.8)
delete_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION );
hodima_ad( 'پیشرفت خالی: در جریان نیست', hodima_seo_advisor_progress_get( 'inspect' )['running'], false );
hodima_seo_advisor_progress_set( 'inspect', 3, 10, 'سه از ده' );
$pg_a = hodima_seo_advisor_progress_get( 'inspect' );
hodima_ad( 'گام ۳ از ۱۰ ← در جریان، ۲۰٪ (از گام حساب می‌شود)، برچسب می‌ماند', [ $pg_a['running'], $pg_a['percent'], $pg_a['label'] ], [ true, 20, 'سه از ده' ] );
hodima_seo_advisor_progress_context( [ 'pipeline', 2, 4 ] );
hodima_seo_advisor_progress_substep( 'نیمه گام', 0.5 );
hodima_seo_advisor_progress_context( null );
hodima_ad( 'زیرگام: گام ۲ از ۴ و نیمه آن ← ۳۸٪', hodima_seo_advisor_progress_get( 'pipeline' )['percent'], 38 );
hodima_seo_advisor_progress_substep( 'بی‌صاحب', 0.9 );
hodima_ad( 'زیرگام بدون کار فعال هیچ‌چیز نمی‌نویسد', hodima_seo_advisor_progress_get( 'pipeline' )['label'], 'نیمه گام' );
hodima_seo_advisor_progress_finish( 'inspect', 'تمام شد' );
$pg_b = hodima_seo_advisor_progress_get( 'inspect' );
hodima_ad( 'پایان: در جریان نیست، ۱۰۰٪، متن نتیجه', [ $pg_b['running'], $pg_b['percent'], $pg_b['text'], $pg_b['error'] ], [ false, 100, 'تمام شد', false ] );
hodima_seo_advisor_progress_finish( 'inspect', 'سهمیه', true );
hodima_ad( 'پایان با خطا: ۰٪ و نشان خطا', [ hodima_seo_advisor_progress_get( 'inspect' )['percent'], hodima_seo_advisor_progress_get( 'inspect' )['error'] ], [ 0, true ] );
$pg_all = get_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION );
$pg_all['pipeline'] = [ 'running' => true, 'step' => 1, 'total' => 2, 'label' => 'کهنه', 'percent' => 50, 'at' => time() - 600, 'text' => '', 'error' => false ];
update_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION, $pg_all, false );
hodima_ad( 'اجرای قطع‌شده (۱۰ دقیقه بی‌خبر) «در جریان» حساب نمی‌شود', hodima_seo_advisor_progress_get( 'pipeline' )['running'], false );
hodima_ad( 'نام فارسی نوبت‌ها', [ hodima_seo_advisor_progress_stage_label( 'analyze' ), hodima_seo_advisor_progress_stage_label( 'x' ) ], [ 'تحلیل آدرس‌ها', 'x' ] );

// ایجکس وضعیت و اجرا
wp_set_current_user( 1 );
$pg_ajax = static function ( string $action, array $post ): array {
	$_POST    = $post + [ '_ajax_nonce' => wp_create_nonce( 'hodima_advisor_job' ) ];
	$_REQUEST = $_POST;
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $h = static fn(): callable => static function (): void {
		throw new RuntimeException( 'die' );
	} );
	ob_start();
	try {
		$action();
	} catch ( RuntimeException ) {
		// wp_send_json بعد از چاپ می‌میرد
	}
	$out = (string) ob_get_clean();
	remove_filter( 'wp_die_ajax_handler', $h );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	$_POST = $_REQUEST = [];
	return (array) json_decode( $out, true );
};
hodima_seo_advisor_progress_set( 'inspect', 2, 5, 'دو از پنج' );
$pg_st = $pg_ajax( 'hodima_seo_advisor_ajax_job_status', [ 'job' => 'inspect' ] );
hodima_ad( 'ایجکس وضعیت: پیشرفت و ردیف وضعیت برمی‌گردد', [ $pg_st['success'] ?? null, $pg_st['data']['progress']['percent'] ?? null, isset( $pg_st['data']['facts']['pill'] ) ], [ true, 20, true ] );
$pg_bad = $pg_ajax( 'hodima_seo_advisor_ajax_job_run', [ 'job' => 'evil' ] );
hodima_ad( 'ایجکس اجرا: کار ناشناخته رد می‌شود', $pg_bad['success'] ?? null, false );
delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
delete_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION );
$pg_run = $pg_ajax( 'hodima_seo_advisor_ajax_job_run', [ 'job' => 'pipeline' ] );
hodima_ad( 'ایجکس اجرا: ساخت پیشنهادها وقتی کاری نیست ← موفق، پیام «کاری برای ادامه نیست»، پیشرفت تمام‌شده', [ $pg_run['success'] ?? null, str_contains( (string) ( $pg_run['data']['html'] ?? '' ), 'کاری برای ادامه نیست' ), $pg_run['data']['progress']['running'] ?? null, $pg_run['data']['progress']['percent'] ?? null ], [ true, true, false, 100 ] );
$pg_nonce = $pg_ajax( 'hodima_seo_advisor_ajax_job_status', [ 'job' => 'inspect', '_ajax_nonce' => 'bad' ] );
hodima_ad( 'ایجکس با nonce نادرست کار نمی‌کند', $pg_nonce['success'] ?? null, null );

// نمای تب «خزش و داده»: ردیف تمام‌عرض، دکمه تنظیمات، نوار فقط وقتی در جریان است
update_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION, [ 'pipeline' => [ 'running' => true, 'step' => 3, 'total' => 7, 'label' => 'تحلیل آدرس‌ها', 'percent' => 38, 'at' => time(), 'text' => '', 'error' => false ] ], false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'jobs' ];
$pg_html = $cap( static fn() => hodima_seo_advisor_render_jobs() );
hodima_ad( 'تب خزش و داده: چهار ردیف، چهار دکمه تنظیمات، بدون شبکه دوستونه و بدون details', [ substr_count( $pg_html, 'class="hodima-advisor__job-row"' ), substr_count( $pg_html, 'data-hdsa-job-toggle' ), str_contains( $pg_html, 'jobs-grid' ), str_contains( $pg_html, '<details class="hodima-advisor__job-set"' ) ], [ 4, 4, false, false ] );
hodima_ad( 'کار در جریان: ردیف قاب‌دار، ۳۸٪، پیمایش زنده؛ بقیه نوار پنهان', [ str_contains( $pg_html, 'data-job="pipeline"' ) && str_contains( $pg_html, 'data-running="1"' ), str_contains( $pg_html, 'aria-valuenow="38"' ), str_contains( $pg_html, 'data-hdsa-job-poll="1"' ), substr_count( $pg_html, 'data-hdsa-job-prog hidden' ) ], [ true, true, true, 3 ] );
hodima_ad( 'فیلد بازه دریافت داده با ویژگی form به فرم اجرا وصل است (فرم یکی، پنل جدا)', [ str_contains( $pg_html, 'form="hodima-advisor-job-form-refresh"' ), substr_count( $pg_html, 'name="days"' ) ], [ true, 3 ] );
// ارقام فارسی تب «خزش و داده» (SEO 3.8.2؛ عکس کاربر: «گام ۲ از ۳: 2 از 3 آدرس»، «10 دقیقه پیش» کنار «۴۸ ساعت»)
$pg_text   = (string) preg_replace( [ '#<code\b.*?</code>#su', '#<[^>]*>#u', '#&\#?\w+;#u' ], ' ', $pg_html );
$pg_latin  = preg_match_all( '#(?<![A-Za-z/=_-])[0-9]+(?![A-Za-z/_-])#u', $pg_text, $pg_m ) ? array_slice( $pg_m[0], 0, 8 ) : [];
hodima_ad( 'تب خزش و داده: متن بی‌رقم لاتین (جز دستور کرون)؛ ویژگی‌ها (aria-valuenow، عرض نوار) لاتین می‌مانند؛ ساعت‌های فرم خزش فارسی', [ $pg_latin, str_contains( $pg_html, 'aria-valuenow="38"' ), str_contains( $pg_html, 'inline-size: 38%' ), str_contains( $pg_html, '>۲۳:۰۰<' ) ], [ [], true, true, true ] );
hodima_ad( 'fa_digits_html: متن فارسی، ویژگی/موجودیت/کد دست‌نخورده', hodima_seo_advisor_fa_digits_html( '<p data-n="12" style="inline-size: 3%">گام 2 از 3: 10 دقیقه &#8211; 1,250 <code>*/5 * * * * wget</code> http://a.test/2/</p>' ), '<p data-n="12" style="inline-size: 3%">گام ۲ از ۳: ۱۰ دقیقه &#8211; ۱٬۲۵۰ <code>*/5 * * * * wget</code> http://a.test/2/</p>' );
$ov_html  = $cap( static fn() => hodima_seo_advisor_echo_fa( static function (): void {
	echo '<p>249 ایراد باز روی 202 صفحه</p><span class="n">11,854 نمایش</span><a data-n="12" href="/x/2/">https://a.test/p/2/</a><button data-hdsa-copy-text="پک 12 عددی">کپی</button>';
} ) );
hodima_ad( 'echo_fa («کارها»، SEO 3.8.5): متن فارسی؛ آدرس، ویژگی و متن کپی دست‌نخورده', $ov_html, '<p>۲۴۹ ایراد باز روی ۲۰۲ صفحه</p><span class="n">۱۱٬۸۵۴ نمایش</span><a data-n="12" href="/x/2/">https://a.test/p/2/</a><button data-hdsa-copy-text="پک 12 عددی">کپی</button>' );
hodima_seo_advisor_progress_set( 'inspect', 2, 3, '2 از 3 آدرس پرسیده می‌شود' );
hodima_ad( 'پیشرفت (ایجکس وضعیت): برچسب با ارقام فارسی', hodima_seo_advisor_progress_get( 'inspect' )['label'], '۲ از ۳ آدرس پرسیده می‌شود' );
$_GET = [];
delete_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION );

// گام ایجکس خزنده (SEO 3.4.4): فقط «خزش همین حالا» یا کرون ایستاده نوبت می‌زند
$cs_now  = time();
$cs_keep = get_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'always' => true ], false );
hodima_ad( 'گام ایجکس: دستی همیشه نوبت می‌زند', hodima_seo_advisor_crawler_step_drives( [ 'manual' => true, 'tick' => $cs_now, 'started' => $cs_now ], $cs_now ), true );
hodima_ad( 'گام ایجکس: خودکار با نوبت تازه کرون نوبت نمی‌زند', hodima_seo_advisor_crawler_step_drives( [ 'manual' => false, 'tick' => $cs_now - 120, 'started' => $cs_now - 3600 ], $cs_now ), false );
hodima_ad( 'گام ایجکس: خودکار با کرون ۱۱ دقیقه ساکت نوبت می‌زند', hodima_seo_advisor_crawler_step_drives( [ 'manual' => false, 'tick' => $cs_now - 660, 'started' => $cs_now - 3600 ], $cs_now ), true );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'always' => false, 'start' => 2, 'end' => 3 ], false );
hodima_ad( 'گام ایجکس: بیرون از پنجره خزش هرگز (غیر دستی)', hodima_seo_advisor_crawler_step_drives( [ 'manual' => false, 'tick' => 0, 'started' => 0 ], strtotime( '2026-01-01 12:00:00 UTC' ) ), false );
false === $cs_keep ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, $cs_keep, false );
// دریافت دسته‌به‌دسته (SEO 3.4.6): مجموع چند دسته = مجموع یک‌جای همان ردیف‌ها (حتی وقتی یک مسیر در دو دسته می‌آید)
$ch_rows = [];
foreach ( range( 0, 59 ) as $i ) {
	$ch_rows[] = [ 'keys' => [ ( 0 === $i % 5 ? str_replace( '://', '://www.', home_url( '/ch-' . ( $i % 7 ) . '/' ) ) : home_url( '/ch-' . ( $i % 7 ) . '/' ) ), 'کلمه ' . ( $i % 4 ) ], 'clicks' => $i % 3, 'impressions' => 4 + $i, 'position' => 3.0 + $i % 9 ];
}
$ch_pages = hodima_seo_advisor_pages_sums();
$ch_terms = hodima_seo_advisor_queries_sums();
$ch_vars  = hodima_seo_advisor_variants_sums();
foreach ( array_chunk( $ch_rows, 17 ) as $ch_part ) {
	hodima_seo_advisor_pages_sum( $ch_pages, $ch_part );
	hodima_seo_advisor_queries_sum( $ch_terms, $ch_part );
	hodima_seo_advisor_variants_sum( $ch_vars, $ch_part );
}
hodima_ad( 'دسته‌به‌دسته: صفحه‌ها همان یک‌جاست', hodima_seo_advisor_pages_finish( $ch_pages ), hodima_seo_advisor_aggregate_pages( $ch_rows ) );
hodima_ad( 'دسته‌به‌دسته: کلمه‌ها همان یک‌جاست', hodima_seo_advisor_queries_finish( $ch_terms ), hodima_seo_advisor_aggregate_queries( $ch_rows ) );
hodima_ad( 'دسته‌به‌دسته: آدرس‌های تکراری همان یک‌جاست', hodima_seo_advisor_variants_finish( $ch_vars ), hodima_seo_advisor_find_variants( $ch_rows ) );
hodima_ad( 'دسته‌به‌دسته: دست‌کم یک صفحه با دو آدرس خام پیدا می‌شود (آزمون بی‌معنا نباشد)', count( hodima_seo_advisor_find_variants( $ch_rows ) ) > 0, true );

// کش درخواست (SEO 3.4.3): کارها و ایرادهای خزش با نوشتن دوباره ساخته می‌شوند
$pf_old = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, [] );
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, [], false );
hodima_ad( 'کش کارها: بعد از خالی کردن گزینه خالی', hodima_seo_advisor_tasks(), [] );
hodima_seo_advisor_task_set( '/pf-cache/', 'crawl_no_meta', '', 'x', 'done' );
hodima_ad( 'کش کارها: بعد از ثبت کار تازه همان لحظه دیده می‌شود', array_column( hodima_seo_advisor_tasks(), 'path' ), [ '/pf-cache/' ] );
hodima_seo_advisor_task_set( '/pf-cache/', 'crawl_no_meta', '', '', '' );
hodima_ad( 'کش کارها: برداشتن کار هم همان لحظه', hodima_seo_advisor_tasks(), [] );
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $pf_old, false );
$pf_t = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->delete( $pf_t, [ 'path' => '/pf-memo/' ] );
$GLOBALS['wpdb']->insert( $pf_t, [ 'path' => '/pf-memo/', 'status' => 200, 'indexable' => 1, 'title' => 'عنوان آزمون کش ایرادها با طول کافی', 'title_len' => 40, 'meta_len' => 100, 'title_px' => hodima_ad_tpx( 40 ), 'meta_px' => hodima_ad_mpx( 100 ), 'h1' => 'تیتر آزمون کش', 'words' => 300, 'h1_count' => 1, 'canonical' => '/pf-memo/', 'inlinks' => 5, 'text_links' => 3, 'depth' => 1 ] );
hodima_seo_advisor_crawl_map( true );
$pf_has = static function (): bool {
	foreach ( hodima_seo_advisor_crawl_issues() as $r ) {
		if ( '/pf-memo/' === $r['path'] && in_array( 'no_meta', array_column( $r['issues'], 'code' ), true ) ) {
			return true;
		}
	}
	return false;
};
hodima_ad( 'کش ایرادهای خزش: متا دارد ← ایراد no_meta نیست', $pf_has(), false );
$GLOBALS['wpdb']->update( $pf_t, [ 'meta_len' => 0, 'meta_px' => 0 ], [ 'path' => '/pf-memo/' ] );
hodima_ad( 'کش ایرادهای خزش: بدون crawl_map(true) همان نتیجه قبلی (کش درخواست)', $pf_has(), false );
hodima_seo_advisor_crawl_map( true );
hodima_ad( 'کش ایرادهای خزش: بعد از crawl_map(true) دوباره ساخته می‌شود (no_meta می‌آید)', $pf_has(), true );
$GLOBALS['wpdb']->delete( $pf_t, [ 'path' => '/pf-memo/' ] );
hodima_seo_advisor_crawl_map( true );
// تب «خزش و داده» (SEO 3.3.9)
$jb_tabs = $cap( static fn() => hodima_seo_advisor_render_tabs( 'jobs' ) );
hodima_ad( 'ردیف تب روی «خزش و داده»: چهار تب (SEO 3.5.8)، فقط همین فعال، «گرفتن داده تازه» نیست', [ substr_count( $jb_tabs, '<a ' ), substr_count( $jb_tabs, 'aria-current="page"' ), str_contains( $jb_tabs, 'tab=jobs" aria-current="page"' ), str_contains( $jb_tabs, 'value="hodima_advisor_refresh"' ) ], [ 4, 1, true, false ] );
$sh_pos = [ strpos( $jb_tabs, 'tab=tasks' ), strpos( $jb_tabs, 'tab=jobs' ) ];
hodima_ad( 'ترتیب تب‌ها: «کارهای انجام‌شده» پیش از «خزش و داده»؛ «سلامت سایت» تب نیست و tab=crawl قدیمی به «کارها» می‌رود', [ $sh_pos[0] < $sh_pos[1], str_contains( $jb_tabs, 'tab=crawl' ), str_contains( $jb_tabs, '>سلامت سایت<' ), HODIMA_SEO_ADVISOR_LEGACY_TABS['crawl'][0] ], [ true, false, false, 'overview' ] );
$jb_post = $_POST;
$_POST   = [ 'tab' => 'jobs' ];
$jb_a    = hodima_seo_advisor_back_tab( 'crawl' );
$_POST   = [ 'tab' => 'settings' ];
$jb_b    = hodima_seo_advisor_back_tab( 'crawl' );
$_POST   = [ 'tab' => 'x' ];
$jb_c    = hodima_seo_advisor_back_tab( 'indexing' );
$_POST   = [];
$jb_d    = hodima_seo_advisor_back_tab( 'crawl' );
$_POST   = $jb_post;
hodima_ad( 'تب بازگشت بعد از کنش فرم: خزش و داده، تنظیمات؛ ناشناخته و نبود ← پیش‌فرض همان کنش', [ $jb_a, $jb_b, $jb_c, $jb_d ], [ 'jobs', 'settings', 'indexing', 'crawl' ] );
hodima_ad( 'درصد پیشرفت خزش: بر پایه انجام‌شده به کل (انجام‌شده + صف + seed)؛ خالی صفر؛ هرگز ۱۰۰ نمی‌شود', [ hodima_seo_advisor_crawler_percent( [ 'done' => 25, 'queued' => 50, 'seeding' => 25 ] ), hodima_seo_advisor_crawler_percent( [ 'done' => 0, 'queued' => 0, 'seeding' => 0 ] ), hodima_seo_advisor_crawler_percent( [ 'done' => 500, 'queued' => 0, 'seeding' => 0 ] ) ], [ 25, 0, 99 ] );
$jb_keep = get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, null );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'running', 'manual' => true, 'done' => 10, 'queue' => array_fill( 0, 30, 'https://hodima.test/q/' ) ], false );
$jb_run = $job_card( 'crawler' );
hodima_ad( 'کارت خزنده در حال اجرا: نوار پیشرفت با درصد و گام‌های خودکار JS؛ بیکار: بدون نوار', [ str_contains( $jb_run, 'role="progressbar"' ), str_contains( $jb_run, 'aria-valuenow="25"' ), str_contains( $jb_run, 'data-hdsa-crawl-live=' ), str_contains( $jb_run, 'data-hdsa-crawl-bar' ) ], [ true, true, true, true ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => time() - HOUR_IN_SECONDS ], false );
hodima_ad( 'ردیف خزنده بیکار: نوار پیشرفت پنهان (hidden) و بدون پیمایش زنده', [ str_contains( $job_card( 'crawler' ), 'data-hdsa-job-prog hidden' ), str_contains( $job_card( 'crawler' ), 'data-hdsa-crawl-live=' ) ], [ true, false ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
$jb_expect = '';
foreach ( hodima_seo_advisor_cron_health()['jobs'] as $jb_job ) {
	$jb_expect = 'crawler' === $jb_job['key'] ? $jb_job['expect'] : $jb_expect;
}
hodima_ad( 'اجرای بعدی خزنده: دور سریع هر روز و کامل هر ۷ روز (فاصله دورها بیش از ۱)', [ str_contains( $jb_expect, 'دور سریع هر روز' ), str_contains( $jb_expect, '۷' ) || str_contains( $jb_expect, '7' ) ], [ true, true ] );
null === $jb_keep ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, $jb_keep, false );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS );
$fs_gl = $cap( static fn() => hodima_seo_advisor_render_glossary() );
hodima_ad( 'اصطلاح‌ها هنوز details با همان فهرست است', [ str_contains( $fs_gl, '<details class="hds-card hodima-advisor__glossary">' ), str_contains( $fs_gl, '<dt>' ) ], [ true, true ] );
null === $fs_keep[0] ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $fs_keep[0], false );
null === $fs_keep[1] ? delete_option( HODIMA_SEO_ADVISOR_CAPS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CAPS_OPTION, $fs_keep[1], false );

echo "=== جدول‌های آدرس‌های خراب و وضعیت در گوگل (SEO 3.2.5)\n";
wp_set_current_user( 1 );
$_GET = [];
$ht_keep = [ get_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, null ), get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, null ) ];
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [
	'computed' => time(), 'router' => true, 'total' => 10, 'seen' => 8, 'fresh' => 1,
	'dead'     => [ [ 'path' => '/ht-dead/', 'impressions' => 500, 'clicks' => 5, 'state' => 'missing', 'target' => '', 'code' => 404 ] ],
	'unseen'   => [
		[ 'id' => 1, 'path' => '/ht-titled/', 'title' => 'عنوان جدا', 'type' => 'post', 'days' => 40 ],
		[ 'id' => 2, 'path' => '/ht-bare/', 'title' => '', 'type' => 'post', 'days' => 50 ],
	],
], false );
$ht_html = $cap( static fn() => hodima_seo_advisor_render_health( hodima_seo_advisor_status() ) );
hodima_ad( 'آدرس‌های خراب: هر دو جدول کلاس کارت موبایل دارند و سلول‌ها برچسب (data-label)', [ substr_count( $ht_html, 'hodima-advisor__terms hodima-advisor__cards' ), str_contains( $ht_html, 'data-label="نمایش"' ), str_contains( $ht_html, 'data-label="مقصد"' ), str_contains( $ht_html, 'data-label="نوع"' ), str_contains( $ht_html, 'data-label="سن"' ) ], [ 2, true, true, true, true ] );
hodima_ad( 'توضیح‌ها داخل دو جمع‌شونده بسته (نه کادرهای باز)؛ متن‌شان هنوز هست', [ substr_count( $ht_html, 'class="hds-more hodima-advisor__explain"' ), str_contains( $ht_html, 'open' ) && str_contains( $ht_html, '<details class="hds-more hodima-advisor__explain" open' ), str_contains( $ht_html, 'آدرس‌های «۴۰۴»' ) || str_contains( $ht_html, 'هنوز در گوگل نمایش می‌گیرند' ), str_contains( $ht_html, 'چه کنید:' ) ], [ 2, false, true, true ] );
hodima_ad( 'دکمه‌های ردیف صفحه بدون نمایش یک ردیف (inline)؛ عنوان تکراری نمی‌آید وقتی عنوان همان مسیر است', [ str_contains( $ht_html, 'hodima-advisor__row-acts hodima-advisor__row-acts--inline' ), str_contains( $ht_html, 'عنوان جدا' ), 1 === substr_count( $ht_html, 'ht-bare/</bdi>' ) || 1 === substr_count( $ht_html, 'ht-bare/' ) ], [ true, true, true ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/ht-index/' => [ 'verdict' => 'NEUTRAL', 'coverage' => 'Crawled - currently not indexed', 'robots' => '', 'indexing' => '', 'fetch' => 'SUCCESSFUL', 'last_crawl' => gmdate( 'c', time() - DAY_IN_SECONDS ), 'google_canonical' => 'https://hodima.test/ht-index/', 'user_canonical' => 'https://hodima.test/ht-index/', 'crawled_as' => 'MOBILE', 'checked' => time() ] ], false );
$ix_html = $cap( static fn() => hodima_seo_advisor_render_indexing() );
hodima_ad( 'وضعیت در گوگل: جدول کارت موبایل با برچسب، توضیح‌ها در جمع‌شونده بسته', [ str_contains( $ix_html, 'hodima-advisor__terms hodima-advisor__cards' ), str_contains( $ix_html, 'data-label="آخرین خزش"' ), str_contains( $ix_html, 'class="hds-more hodima-advisor__explain"' ), str_contains( $ix_html, 'hodima-advisor__explain" open' ), str_contains( $ix_html, 'بعد از رفع، «انجام دادم» را بزنید' ) ], [ true, true, true, false, true ] );
null === $ht_keep[0] ? delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION ) : update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, $ht_keep[0], false );
null === $ht_keep[1] ? delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION ) : update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $ht_keep[1], false );

echo "=== بازخوانی از خود صفحه، خزش هدفمند و نوار وضعیت خزش (SEO 3.4.9)\n";
global $wpdb;
$rc_keep  = [ get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, false ), get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false ), get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false ) ];
$rc_n     = static fn( int $v ): string => number_format_i18n( $v );
hodima_ad(
	'زمان تقریبی: ۱۰ صفحه کمتر از یک دقیقه؛ ۲۲۰ صفحه (۱٫۵ ثانیه هر کدام) ≈ ۶ دقیقه؛ «مانده» فقط وقتی صفی هست',
	[ hodima_seo_advisor_crawler_duration( 10 ), hodima_seo_advisor_crawler_duration( 220 ), hodima_seo_advisor_crawler_eta( [ 'queued' => 0, 'seeding' => 0 ] ), hodima_seo_advisor_crawler_eta( [ 'queued' => 100, 'seeding' => 20 ] ) ],
	[ 'کمتر از یک دقیقه', 'حدود ' . $rc_n( 6 ) . ' دقیقه', '', 'حدود ' . $rc_n( 3 ) . ' دقیقه مانده' ]
);

// خزش هدفمند: فقط مسیرهای معتبر، حالت سریع (چیزی پاک نمی‌شود)، دستی؛ وقتی دوری در جریان است شروع نمی‌شود
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
hodima_ad( 'خزش هدفمند: بدون مسیر معتبر شروع نمی‌شود', hodima_seo_advisor_crawler_start_paths( [ 'x', '', 'p/' ] ), false );
hodima_ad( 'خزش هدفمند: مسیرهای معتبر و یکتا شروع می‌شود', hodima_seo_advisor_crawler_start_paths( [ '/rc-a/', 'x', '/rc-b/', '/rc-a/' ] ), true );
$rc_state = hodima_seo_advisor_crawler_state();
hodima_ad(
	'خزش هدفمند: در جریان، دستی، حالت سریع؛ seed فقط همان دو صفحه',
	[ $rc_state['status'], $rc_state['manual'], $rc_state['mode'], $rc_state['seed'] ],
	[ 'running', true, 'quick', [ 'u:/rc-a/', 'u:/rc-b/' ] ]
);
hodima_ad( 'خزش هدفمند: وقتی دوری در جریان است دوباره شروع نمی‌شود', hodima_seo_advisor_crawler_start_paths( [ '/rc-c/' ] ), false );

// نوار وضعیت خزش
$rc_bar_run = $cap( static fn() => hodima_seo_advisor_render_crawlbar( 'overview' ) );
hodima_ad(
	'نوار خزش: در جریان ← پیشرفت زنده (همان JS «خزش و داده»)، نوار پیشرفت، پیوند جزئیات؛ در تب‌های دیگر نمی‌آید',
	[ str_contains( $rc_bar_run, 'data-hdsa-crawl-live=' ), str_contains( $rc_bar_run, 'data-hdsa-crawl-bar' ), str_contains( $rc_bar_run, 'خزنده در حال خواندن سایت است' ), str_contains( $rc_bar_run, 'tab=jobs' ), $cap( static fn() => hodima_seo_advisor_render_crawlbar( 'jobs' ) ), $cap( static fn() => hodima_seo_advisor_render_crawlbar( 'tasks' ) ), str_contains( $cap( static fn() => hodima_seo_advisor_render_crawlbar( 'crawl' ) ), 'data-hdsa-crawl-live=' ) ],
	[ true, true, true, true, '', '', true ]
);
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => time() - 60, 'pages' => 42 ], false );
$rc_bar_done = $cap( static fn() => hodima_seo_advisor_render_crawlbar( 'crawl' ) );
hodima_ad( 'نوار خزش: تازه تمام‌شده ← «خزش تمام شد» با دکمه بستن و بی‌پیشرفت زنده', [ str_contains( $rc_bar_done, 'خزش تمام شد' ), str_contains( $rc_bar_done, 'data-hdsa-crawlbar-close' ), str_contains( $rc_bar_done, 'data-hdsa-crawl-live=' ) ], [ true, true, false ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => time() - HODIMA_SEO_ADVISOR_CRAWLBAR_DONE_WINDOW - 60, 'pages' => 42 ], false );
hodima_ad( 'نوار خزش: بیش از ۱۵ دقیقه از پایان گذشته یا هیچ خزشی نبوده ← هیچ', [ $cap( static fn() => hodima_seo_advisor_render_crawlbar( 'overview' ) ), ( static function (): string { delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ); return ''; } )() ], [ '', '' ] );
hodima_ad( 'نوار خزش: بدون خزش اصلا ← هیچ', $cap( static fn() => hodima_seo_advisor_render_crawlbar( 'overview' ) ), '' );

// فرم‌ها
$rc_form = hodima_seo_advisor_recrawl_form( [ 'scope' => 'code', 'code' => 'img_alt' ], 'بازخوانی این ۲ صفحه', 'کمتر از یک دقیقه', true, true );
hodima_ad(
	'فرم بازخوانی: کنش و nonce و فیلدها، دکمه خاموش، یادداشت؛ بدون شناسه (nonce تکراری نشود)',
	[ str_contains( $rc_form, 'name="action" value="hodima_advisor_recrawl"' ), str_contains( $rc_form, 'name="_wpnonce" value="' . wp_create_nonce( 'hodima_advisor_recrawl' ) . '"' ), str_contains( $rc_form, 'name="scope" value="code"' ), str_contains( $rc_form, ' disabled' ), str_contains( $rc_form, 'کمتر از یک دقیقه' ), str_contains( $rc_form, ' id=' ) ],
	[ true, true, true, true, true, false ]
);
$rc_now = hodima_seo_advisor_crawl_now_form( 'crawl' );
hodima_ad( 'فرم «خزش همین حالا» سربرگ: همان کنش تب «خزش و داده»، تب بازگشت همین تب، فعال وقتی دوری نیست', [ str_contains( $rc_now, 'value="hodima_advisor_crawler_now"' ), str_contains( $rc_now, 'name="tab" value="crawl"' ), str_contains( $rc_now, ' disabled' ), str_contains( $rc_now, 'data-hdsa-job-form' ) ], [ true, true, false, false ] );
hodima_seo_advisor_crawler_start_paths( [ '/rc-a/' ] );
hodima_ad( 'فرم «خزش همین حالا»: وقتی خزش در جریان است خاموش با برچسب «خزش در جریان است»', [ str_contains( hodima_seo_advisor_crawl_now_form( 'overview' ), ' disabled' ), str_contains( hodima_seo_advisor_crawl_now_form( 'overview' ), 'خزش در جریان است' ) ], [ true, true ] );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ 0 ] );

// دکمه «اجرا همین حالا» ردیف خزنده: وقتی کار در جریان است خاموش با «در حال اجرا…» و برچسب اول برای بازگردانی JS
$rc_idle = hodima_seo_advisor_cron_run_form( 'crawler' );
$rc_busy = hodima_seo_advisor_cron_run_form( 'crawler', true );
hodima_ad( 'دکمه اجرا: آزاد ← فعال با «اجرا همین حالا»؛ در جریان ← خاموش «در حال اجرا…» با data-was', [ str_contains( $rc_idle, ' disabled' ), str_contains( $rc_idle, '>اجرا همین حالا</span>' ), str_contains( $rc_busy, ' disabled' ), str_contains( $rc_busy, '>در حال اجرا…</span>' ), str_contains( $rc_busy, 'data-was="اجرا همین حالا"' ) ], [ false, true, true, true, true ] );

// صفحه‌های دارای ایراد برای «بازخوانی این N صفحه»: ایراد علامت‌خورده شمرده نمی‌شود
$rc_table = hodima_seo_advisor_table( 'crawl_pages' );
foreach ( [ '/rc-1/', '/rc-2/', '/rc-3/' ] as $rc_path ) {
	$wpdb->replace( $rc_table, [ 'path' => $rc_path, 'status' => 200, 'indexable' => 1, 'title' => 'عنوان آزمون ' . $rc_path, 'title_len' => 45, 'meta_len' => 120, 'title_px' => hodima_ad_tpx( 45 ), 'meta_px' => hodima_ad_mpx( 120 ), 'h1' => 'تیتر ' . $rc_path, 'resp_ms' => 100, 'inlinks' => 5, 'text_links' => 3, 'depth' => 2, 'words' => 300, 'h1_count' => 1, 'img_noalt' => '/rc-3/' === $rc_path ? 0 : 1, 'http_links' => 0, 'canonical' => $rc_path, 'img_list' => '' ] );
}
hodima_seo_advisor_crawl_map( true );
hodima_ad( 'صفحه‌های دارای ایراد «تصویر بدون alt»: فقط دو صفحه (سومی ندارد)', array_values( array_intersect( hodima_seo_advisor_recrawl_paths( 'img_alt' ), [ '/rc-1/', '/rc-2/', '/rc-3/' ] ) ), [ '/rc-1/', '/rc-2/' ] );
hodima_seo_advisor_task_set( '/rc-2/', 'crawl_img_alt', '', 'تصویر بدون alt', 'done' );
hodima_seo_advisor_crawl_map( true );
hodima_ad( 'ایراد علامت‌خورده (انجام دادم) از «بازخوانی این N صفحه» بیرون است', array_values( array_intersect( hodima_seo_advisor_recrawl_paths( 'img_alt' ), [ '/rc-1/', '/rc-2/', '/rc-3/' ] ) ), [ '/rc-1/' ] );
hodima_seo_advisor_task_set( '/rc-2/', 'crawl_img_alt', '', '', '' );

// کنش admin-post (در آزمون ریدایرکت استثنا می‌دهد؛ پاسخ سایت ساختگی)
if ( ! function_exists( 'hodima_admin_flash' ) ) {
	require_once HODIMA_SEO_DIR . '/inc/admin-ui-fallback.php';
}
class Hodima_RC_Redirect extends Exception {}
$rc_redirect = static function ( string $location ): string { throw new Hodima_RC_Redirect( $location ); };
add_filter( 'wp_redirect', $rc_redirect, 0 ); // قبل از فیلتر mu-plugin ابزار تست (اولویت ۱) که آدرس را false می‌کند
$rc_body = '<html><head><title>' . str_repeat( 'ع', 50 ) . '</title></head><body><main><h1>تیتر</h1><img src="/wp-content/uploads/rc-one.jpg"></main></body></html>';
$rc_http = static function ( $pre, array $args, string $url ) use ( &$rc_body ) {
	if ( ! str_contains( $url, '/rc-1/' ) ) {
		return $pre;
	}
	return [ 'headers' => [ 'content-type' => 'text/html' ], 'body' => $rc_body, 'response' => [ 'code' => 200, 'message' => '' ], 'cookies' => [], 'filename' => null ];
};
add_filter( 'pre_http_request', $rc_http, 30, 3 );
$rc_act = static function ( array $post ): array {
	$_POST    = $post + [ '_wpnonce' => wp_create_nonce( 'hodima_advisor_recrawl' ), 'action' => 'hodima_advisor_recrawl' ];
	$_REQUEST = $_POST;
	delete_transient( 'hodima_admin_flash_' . get_current_user_id() );
	$to = '';
	try {
		hodima_seo_advisor_handle_recrawl();
	} catch ( Hodima_RC_Redirect $e ) {
		$to = $e->getMessage();
	}
	$_POST    = [];
	$_REQUEST = [];
	$flash    = get_transient( 'hodima_admin_flash_' . get_current_user_id() );
	return [ is_array( $flash ) ? implode( ' | ', array_column( $flash, 1 ) ) : '', $to, is_array( $flash ) ? implode( ',', array_column( $flash, 0 ) ) : '' ];
};

[ $rc_f1, $rc_to1 ] = $rc_act( [ 'scope' => 'page', 'path' => '/rc-1/', 'code' => 'img_alt' ] );
hodima_ad(
	'بازخوانی یک صفحه: همان صفحه از سایت خوانده شد؛ آدرس تصویر در فهرست ردیف ذخیره شد؛ پیام تعداد را می‌گوید؛ ریدایرکت به همان ردیف',
	[ str_contains( $rc_f1, 'صفحه دوباره از سایت خوانده شد' ), str_contains( $rc_f1, '1 تصویر بدون alt پیدا شد' ) && str_contains( $rc_f1, 'فهرستشان زیر همین کارت است' ), (string) ( hodima_seo_advisor_img_list_decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT img_list FROM %i WHERE path = %s', $rc_table, '/rc-1/' ) ) )[0]['u'] ?? '' ), str_contains( $rc_to1, '#' . hodima_seo_advisor_row_anchor( '/rc-1/' ) ) ],
	[ true, true, 'https://' . wp_parse_url( home_url(), PHP_URL_HOST ) . '/wp-content/uploads/rc-one.jpg', true ]
);

$rc_body = '<html><head><title>' . str_repeat( 'ع', 50 ) . '</title></head><body><main><h1>تیتر</h1><img src="/wp-content/uploads/rc-one.jpg" alt="کفش"></main></body></html>';
[ $rc_f2 ] = $rc_act( [ 'scope' => 'page', 'path' => '/rc-1/', 'code' => 'img_alt' ] );
hodima_ad( 'بازخوانی بعد از نوشتن alt: «دیگر تصویر بدون alt ندارد»', [ str_contains( $rc_f2, 'دیگر تصویر بدون alt ندارد' ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT img_noalt FROM %i WHERE path = %s', $rc_table, '/rc-1/' ) ) ], [ true, 0 ] );

$rc_body = '<html><head><title>' . str_repeat( 'ع', 50 ) . '</title></head><body><main><h1>تیتر</h1><img src=""></main></body></html>';
[ $rc_f3 ] = $rc_act( [ 'scope' => 'page', 'path' => '/rc-1/', 'code' => 'img_alt' ] );
hodima_ad( 'تصویر بی‌آدرس (ساخته‌شده با JS): پیام شفاف به‌جای «با خزش بعدی» تا ابد', [ str_contains( $rc_f3, '1 تصویر بدون alt پیدا شد' ), str_contains( $rc_f3, 'آدرسی برایشان در HTML صفحه نبود' ) ], [ true, true ] );

[ $rc_f4 ] = $rc_act( [ 'scope' => 'page', 'path' => '/rc-not-crawled/', 'code' => 'img_alt' ] );
[ $rc_f5 ] = $rc_act( [ 'scope' => 'page', 'path' => 'relative', 'code' => 'img_alt' ] );
hodima_ad( 'بازخوانی صفحه‌ای که در خزش نیست یا مسیر نامعتبر: خطای روشن با راه «خزش همین حالا»، چیزی ذخیره نمی‌شود', [ str_contains( $rc_f4, 'خوانده نشد' ) && str_contains( $rc_f4, 'خزش همین حالا' ), str_contains( $rc_f5, 'خوانده نشد' ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE path = %s', $rc_table, '/rc-not-crawled/' ) ) ], [ true, true, 0 ] );

delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
[ $rc_f6, $rc_to6 ] = $rc_act( [ 'scope' => 'code', 'code' => 'img_alt' ] );
$rc_state6 = hodima_seo_advisor_crawler_state();
hodima_ad(
	'بازخوانی کار کلی: «N صفحه در صف خزش رفت» با زمان تقریبی؛ حالت سریع، دستی، seed همان صفحه‌های ایراد؛ برمی‌گردد به همان صفحه',
	[ str_contains( $rc_f6, 'صفحه در صف خزش رفت' ), str_contains( $rc_f6, 'پیشرفت بالای همین صفحه' ), $rc_state6['status'], $rc_state6['mode'], $rc_state6['manual'], in_array( 'u:/rc-2/', $rc_state6['seed'], true ) && in_array( 'u:/rc-1/', $rc_state6['seed'], true ), ! in_array( 'u:/rc-3/', $rc_state6['seed'], true ), '' !== $rc_to6 && ! str_contains( $rc_to6, '#' ) ],
	[ true, true, 'running', 'quick', true, true, true, true ]
);
[ $rc_f7 ] = $rc_act( [ 'scope' => 'code', 'code' => 'img_alt' ] );
hodima_ad( 'بازخوانی کار کلی وقتی خزش دیگری در جریان است: خطای روشن و بی‌تغییر', [ str_contains( $rc_f7, 'خزش دیگری در جریان است' ), hodima_seo_advisor_crawler_state()['mode'] ], [ true, 'quick' ] );
[ $rc_f8 ] = $rc_act( [ 'scope' => 'code', 'code' => 'nope_code' ] );
hodima_ad( 'کد ایراد ناشناخته: هیچ خزشی شروع نمی‌شود', str_contains( $rc_f8, 'صفحه‌ای برای بازخوانی پیدا نشد' ), true );
$_POST = [ 'scope' => 'page', 'path' => '/rc-1/', '_wpnonce' => 'bad' ];
$_REQUEST = $_POST;
$rc_die = static fn(): callable => static function (): void { throw new RuntimeException( 'wp_die' ); };
add_filter( 'wp_die_handler', $rc_die, 99 );
$rc_bad = false;
try {
	hodima_seo_advisor_handle_recrawl();
} catch ( RuntimeException $e ) {
	$rc_bad = 'wp_die' === $e->getMessage(); // nonce نامعتبر wp_die می‌کند
}
remove_filter( 'wp_die_handler', $rc_die, 99 );
$_POST = [];
$_REQUEST = [];
hodima_ad( 'nonce نامعتبر: بازخوانی انجام نمی‌شود', $rc_bad, true );


// ── تصویرهای بدون alt: متن پیشنهادی، علت رفع، گروه مشترک، کارت و علامت‌ها (SEO 3.4.9)
hodima_ad(
	'متن پیشنهادی از نام فایل: اندازه و پسوند و خط تیره پاک می‌شود؛ IMG_1234 و عدد تنها پیشنهاد نمی‌دهد',
	[ hodima_seo_advisor_alt_from_name( 'Black-Hair-Clip-300x300.jpg' ), hodima_seo_advisor_alt_from_name( 'گیره-مو-عمده.webp' ), hodima_seo_advisor_alt_from_name( 'IMG_1234.jpg' ), hodima_seo_advisor_alt_from_name( '1234.png' ), hodima_seo_advisor_alt_from_name( 'photo-20240101123456.jpg' ) ],
	[ 'Black Hair Clip', 'گیره مو عمده', '', '', '' ]
);
$rc_up   = wp_upload_dir();
$rc_aurl = $rc_up['baseurl'] . '/hodima-test/rc-img-one.jpg';
$rc_att  = (int) wp_insert_attachment( [ 'post_title' => 'گیره فلزی طلایی', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ], 'hodima-test/rc-img-one.jpg' );
$rc_post = (int) wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'عنوان نوشته آزمون تصویر', 'post_name' => 'rc-alt-post', 'post_content' => 'x' ] );
$rc_path = (string) hodima_seo_advisor_path_key( (string) get_permalink( $rc_post ) );
hodima_ad( 'پیشنهاد متن: عنوان پیوست؛ اگر تصویر شاخص همان نوشته است عنوان نوشته؛ بدون پیوست از نام فایل', [ hodima_seo_advisor_alt_suggestion( $rc_aurl, $rc_att, '/rc-nowhere/' ) ], [ 'گیره فلزی طلایی' ] );
set_post_thumbnail( $rc_post, $rc_att );
hodima_ad( 'پیشنهاد متن: تصویر شاخص ← عنوان نوشته', hodima_seo_advisor_alt_suggestion( $rc_aurl, $rc_att, $rc_path ), 'عنوان نوشته آزمون تصویر' );
hodima_ad( 'پیشنهاد متن: بیرون از کتابخانه از نام فایل', hodima_seo_advisor_alt_suggestion( 'https://cdn.test/red-shoe.png', 0, '' ), 'red shoe' );

$rc_info = static fn( string $url, string $path = '/rc-nowhere/' ): array => hodima_seo_advisor_img_fix_info( [ 'u' => $url, 's' => 'missing', 'c' => '' ], $path );
update_post_meta( $rc_att, '_wp_attachment_image_alt', '' );
$rc_i_lib = $rc_info( $rc_aurl );
update_post_meta( $rc_att, '_wp_attachment_image_alt', 'توضیح کتابخانه' );
$rc_i_comp = $rc_info( $rc_aurl );
wp_update_post( [ 'ID' => $rc_post, 'post_content' => '<img src="' . $rc_aurl . '">' ] );
$rc_i_page = $rc_info( $rc_aurl, $rc_path );
hodima_ad(
	'علت رفع: کتابخانه (alt خالی) / متن صفحه (alt پر و کپی‌شده) / قالب یا افزونه (alt پر، در متن نیست) / بیرونی / بی‌آدرس',
	[ $rc_i_lib['case'], $rc_i_page['case'], $rc_i_comp['case'], $rc_info( 'https://cdn.test/x.png' )['case'], $rc_info( '' )['case'], $rc_i_lib['id'] === $rc_att, str_contains( $rc_i_lib['edit'], 'post=' . $rc_att ), '' !== $rc_i_page['page_edit'] ],
	[ 'library', 'page', 'component', 'external', 'none', true, true, true ]
);

// تصویر داخل توضیح دسته/خلاصه محصول/برگه اصلی (SEO 3.5.1): «ویرایش همان صفحه»، نه «قالب یا افزونه» (باگ ۳.۵.۰: فقط post_content دیده می‌شد)
$rc_term = wp_insert_term( 'دسته آزمون تصویر', 'category', [ 'description' => '<p><img src="' . $rc_aurl . '" alt=""></p>' ] );
$rc_tid  = is_array( $rc_term ) ? (int) $rc_term['term_id'] : 0;
$rc_tpath = (string) hodima_seo_advisor_path_key( (string) get_term_link( $rc_tid, 'category' ) );
update_post_meta( $rc_att, '_wp_attachment_image_alt', 'پخش عمده انواع گیره مو' );
$rc_i_term = $rc_info( $rc_aurl, $rc_tpath );
hodima_ad(
	'تصویر داخل توضیح دسته (alt کتابخانه پر): حالت «page»، ویرایشگر «توضیح»، «ویرایش دسته» با پیوند ویرایش دسته، متن پیشنهادی = alt کتابخانه',
	[ $rc_i_term['case'], $rc_i_term['field'], $rc_i_term['edit_label'], str_contains( $rc_i_term['page_edit'], 'term_id=' . $rc_tid ) || str_contains( $rc_i_term['page_edit'], 'tag_ID=' . $rc_tid ), $rc_i_term['suggest'] ],
	[ 'page', 'توضیح', 'ویرایش دسته', true, 'پخش عمده انواع گیره مو' ]
);
$rc_help_term = hodima_seo_advisor_img_help_text( 'page', $rc_i_term );
hodima_ad( 'متن «چه کنم؟» دسته: «ویرایش دسته» ← ویرایشگر «توضیح» ← «متن جایگزین»؛ بدون «قابل رفع نیست»', [ str_contains( $rc_help_term, '«ویرایش دسته»' ), str_contains( $rc_help_term, 'ویرایشگر «توضیح»' ), str_contains( $rc_help_term, '«متن جایگزین»' ), str_contains( $rc_help_term, 'قابل رفع نیست' ) ], [ true, true, true, false ] );
update_post_meta( $rc_att, '_wp_attachment_image_alt', '' );
hodima_ad( 'alt کتابخانه هم خالی ولی تصویر در توضیح دسته است ← باز «page» (کتابخانه آن را درست نمی‌کند)', hodima_seo_advisor_img_fix_info( [ 'u' => $rc_aurl, 's' => 'missing', 'c' => 'div.category-content' ], $rc_tpath )['case'], 'page' );
wp_delete_term( $rc_tid, 'category' );
$rc_ex = (int) wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'نوشته خلاصه آزمون', 'post_name' => 'rc-excerpt-post', 'post_content' => 'بدون تصویر', 'post_excerpt' => '<img src="' . $rc_aurl . '">' ] );
$rc_i_ex = $rc_info( $rc_aurl, (string) hodima_seo_advisor_path_key( (string) get_permalink( $rc_ex ) ) );
hodima_ad( 'تصویر داخل خلاصه نوشته: «page»، ویرایشگر «خلاصه»، «ویرایش نوشته»', [ $rc_i_ex['case'], $rc_i_ex['field'], $rc_i_ex['edit_label'] ], [ 'page', 'خلاصه', 'ویرایش نوشته' ] );
wp_delete_post( $rc_ex, true );
hodima_ad( 'تصویر بیرونی (CDN) که در متن صفحه درج شده ← «page» نه «external»', ( static function () use ( $rc_post, $rc_path, $rc_info ): string {
	wp_update_post( [ 'ID' => $rc_post, 'post_content' => '<img src="https://cdn.test/in-body-shoe.png">' ] );
	return $rc_info( 'https://cdn.test/in-body-shoe.png', $rc_path )['case'];
} )(), 'page' );
hodima_ad( 'تصویر نه در متن، نه در دسته/خلاصه: «component» با متن نرم (صفحه‌ساز/ویجت) و بدون «قابل رفع نیست»', [ $rc_info( $rc_aurl, '/rc-nowhere/' )['case'], str_contains( hodima_seo_advisor_img_help_text( 'component' ), 'صفحه‌ساز' ), str_contains( hodima_seo_advisor_img_help_text( 'component' ), 'قابل رفع نیست' ) ], [ 'library', true, false ] );
update_post_meta( $rc_att, '_wp_attachment_image_alt', 'توضیح کتابخانه' );
hodima_ad( 'alt کتابخانه پر و نه در متن: «component» و متن پیشنهادی = alt کتابخانه', [ $rc_info( $rc_aurl, '/rc-nowhere/' )['case'], $rc_info( $rc_aurl, '/rc-nowhere/' )['suggest'] ], [ 'component', 'توضیح کتابخانه' ] );
update_post_meta( $rc_att, '_wp_attachment_image_alt', '' );
wp_update_post( [ 'ID' => $rc_post, 'post_content' => 'x' ] );

// تصویر مشترک: ۶ صفحه یک فایل ← یک گروه؛ بسته‌شدن کار یک صفحه آن را ۵ می‌کند؛ ۴ صفحه «مشترک» نیست
$rc_share = 'https://hodima.test/wp-content/uploads/rc-shared.jpg';
foreach ( range( 1, 6 ) as $rc_k ) {
	$wpdb->replace( $rc_table, [ 'path' => "/rc-g{$rc_k}/", 'status' => 200, 'indexable' => 1, 'title' => 'عنوان گروه ' . $rc_k, 'title_len' => 45, 'meta_len' => 120, 'title_px' => hodima_ad_tpx( 45 ), 'meta_px' => hodima_ad_mpx( 120 ), 'h1' => 'تیتر ' . $rc_k, 'resp_ms' => 100, 'inlinks' => 5, 'text_links' => 3, 'depth' => 2, 'words' => 300, 'h1_count' => 1, 'img_noalt' => 1, 'img_empty' => 0, 'http_links' => 0, 'canonical' => "/rc-g{$rc_k}/", 'img_list' => hodima_seo_advisor_img_list_encode( [ [ 'u' => $rc_share, 's' => 'missing', 'c' => 'div.hero' ] ] ) ] );
	$wpdb->replace( hodima_seo_advisor_table( 'pages' ), [ 'path' => "/rc-g{$rc_k}/", 'impressions' => 100 * $rc_k, 'kind' => 'post', 'advice' => '[]' ] );
}
hodima_seo_advisor_crawl_map( true );
$rc_groups = hodima_seo_advisor_image_groups();
hodima_ad( 'تصویر مشترک: یک گروه با ۶ صفحه، جمع نمایش و جایگاه', [ count( $rc_groups[ $rc_share ]['pages'] ?? [] ), $rc_groups[ $rc_share ]['impressions'] ?? 0, $rc_groups[ $rc_share ]['context'] ?? '' ], [ 6, 2100, 'div.hero' ] );
$rc_card = $cap( static fn() => hodima_seo_advisor_render_shared_images( $rc_n ) );
hodima_ad(
	'کارت تصویرهای مشترک: نام فایل، «در 6 صفحه»، نمایش، کجا/چه کنم، فرم بازخوانی scope=img، پیوند فیلتر ?img=',
	[ str_contains( $rc_card, 'rc-shared.jpg' ), str_contains( $rc_card, 'در 6 صفحه' ), str_contains( $rc_card, '2,100' ) || str_contains( $rc_card, '2٬100' ) || str_contains( $rc_card, '2100' ), str_contains( $rc_card, 'کجاست؟' ) && str_contains( $rc_card, 'چه کنم؟' ), str_contains( $rc_card, 'name="scope" value="img"' ), str_contains( $rc_card, 'img=' . hodima_seo_advisor_img_key( $rc_share ) ), str_contains( $rc_card, 'id="hodima-advisor-shared-images"' ) ],
	[ true, true, true, true, true, true, true ]
);
hodima_seo_advisor_task_set( '/rc-g1/', 'crawl_img_alt', '', 'تصویر بدون alt', 'done' );
hodima_seo_advisor_crawl_map( true );
hodima_ad( 'کار «انجام دادم» یک صفحه از گروه بیرون می‌رود', count( hodima_seo_advisor_image_groups()[ $rc_share ]['pages'] ?? [] ), 5 );
hodima_seo_advisor_task_set( '/rc-g2/', 'crawl_img_alt', '', 'تصویر بدون alt', 'done' );
hodima_seo_advisor_task_set( '/rc-g3/', 'crawl_img_alt', '', 'تصویر بدون alt', 'done' );
hodima_seo_advisor_crawl_map( true );
hodima_ad( 'کمتر از ۵ صفحه: گروه هست ولی «مشترک» نیست ← کارت هیچ نمی‌آید', [ count( hodima_seo_advisor_image_groups()[ $rc_share ]['pages'] ?? [] ), $cap( static fn() => hodima_seo_advisor_render_shared_images( $rc_n ) ) ], [ 3, '' ] );
foreach ( [ '/rc-g1/', '/rc-g2/', '/rc-g3/' ] as $rc_p ) {
	hodima_seo_advisor_task_set( $rc_p, 'crawl_img_alt', '', '', '' );
}
hodima_seo_advisor_crawl_map( true );


// «بررسی کنید» (alt خالی) هیچ‌وقت «اولویت بالا» و «کار مهم امروز» نمی‌شود، حتی در صفحه پرنمایش
$rc_it = [ 'level' => 'info', 'src' => 'crawl', 'mark' => 'img_empty', 'effort' => 1 ];
// SEO 3.6.4: هر ایراد «اطلاع» (نه فقط alt خالی) هرگز اولویت بالا/متوسط نمی‌شود؛ ایراد کهربایی با همان ارزش «کم» نیست
hodima_ad( 'امتیاز ایراد اطلاع (alt خالی و ریدایرکت): نشان «کم» حتی با ارزش ۱٫۰؛ ایراد کهربایی با همان ارزش «کم» نیست', [ hodima_seo_advisor_priority_band( hodima_seo_advisor_item_score( $rc_it, 1.0 ) ), hodima_seo_advisor_priority_band( hodima_seo_advisor_item_score( [ 'mark' => 'redirect' ] + $rc_it, 1.0 ) ), 'low' !== hodima_seo_advisor_priority_band( hodima_seo_advisor_item_score( [ 'mark' => 'redirect', 'level' => 'warn' ] + $rc_it, 1.0 ) ) ], [ 'low', 'low', true ] );

// بازخوانی صفحه‌های یک تصویر مشترک (scope=img)
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
[ $rc_fi ] = $rc_act( [ 'scope' => 'img', 'img' => hodima_seo_advisor_img_key( $rc_share ) ] );
$rc_si = hodima_seo_advisor_crawler_state();
hodima_ad( 'بازخوانی صفحه‌های یک تصویر: ۶ صفحه در صف (حالت سریع، دستی)', [ str_contains( $rc_fi, 'صفحه در صف خزش رفت' ), $rc_si['mode'], $rc_si['manual'], count( array_filter( $rc_si['seed'], static fn( string $i ): bool => str_starts_with( $i, 'u:/rc-g' ) ) ) ], [ true, 'quick', true, 6 ] );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ 0 ] );
[ $rc_fn ] = $rc_act( [ 'scope' => 'img', 'img' => 'zzzzzzzzzzzz' ] );
hodima_ad( 'کلید تصویر ناشناخته: هیچ خزشی شروع نمی‌شود', str_contains( $rc_fn, 'صفحه‌ای برای بازخوانی پیدا نشد' ), true );

// کارت «بررسی کنید» و کنش علامت‌ها
$rc_rev = $cap( static fn() => hodima_seo_advisor_render_image_review( [ [ 'u' => 'https://cdn.test/logo.png', 's' => 'empty', 'c' => 'div.logos' ] ], 1, '/rc-1/', [ [ 'u' => 'https://cdn.test/icon.png', 's' => 'empty', 'c' => '' ] ] ) );
hodima_ad(
	'بخش «بررسی کنید»: «تزئینی است» و «alt می‌خواهد» برای هر تصویر (فرم با nonce)؛ علامت‌خورده‌ها با «برگرداندن»؛ جایگاه',
	[ substr_count( $rc_rev, 'name="mark" value="ok"' ), substr_count( $rc_rev, 'name="mark" value="need"' ), substr_count( $rc_rev, 'name="mark" value="reset"' ), str_contains( $rc_rev, 'div.logos' ), str_contains( $rc_rev, 'تصویرهای «تزئینی» علامت‌خورده' ), str_contains( $rc_rev, ' id=' ) ],
	[ 1, 1, 1, true, true, false ]
);
$rc_mark = static function ( array $post ): array {
	$_POST    = $post + [ '_wpnonce' => wp_create_nonce( 'hodima_advisor_img_mark' ), 'action' => 'hodima_advisor_img_mark' ];
	$_REQUEST = $_POST;
	delete_transient( 'hodima_admin_flash_' . get_current_user_id() );
	try {
		hodima_seo_advisor_handle_img_mark();
	} catch ( Hodima_RC_Redirect ) {
		// پایان عادی: ریدایرکت
	}
	$_POST    = [];
	$_REQUEST = [];
	$flash    = get_transient( 'hodima_admin_flash_' . get_current_user_id() );
	return [ is_array( $flash ) ? implode( ' | ', array_column( $flash, 1 ) ) : '', hodima_seo_advisor_img_marks() ];
};
delete_option( HODIMA_SEO_ADVISOR_IMG_MARKS_OPTION );
[ $rc_m1, $rc_marks1 ] = $rc_mark( [ 'url' => 'https://cdn.test/logo.png', 'mark' => 'ok' ] );
hodima_ad( 'کنش «تزئینی است»: ذخیره با کلید آدرس و پیام روشن', [ $rc_marks1[ hodima_seo_advisor_img_key( 'https://cdn.test/logo.png' ) ] ?? '', str_contains( $rc_m1, 'تزئینی' ) ], [ 'ok', true ] );
[ $rc_m2, $rc_marks2 ] = $rc_mark( [ 'url' => 'https://cdn.test/logo.png', 'mark' => 'reset' ] );
hodima_ad( 'کنش «برگرداندن»: علامت برداشته شد', [ $rc_marks2, str_contains( $rc_m2, 'علامت برداشته شد' ) ], [ [], true ] );
[ $rc_m3 ] = $rc_mark( [ 'url' => '', 'mark' => 'ok' ] );
hodima_ad( 'کنش علامت با آدرس خالی: خطا', str_contains( $rc_m3, 'ثبت نشد' ), true );
delete_option( HODIMA_SEO_ADVISOR_IMG_MARKS_OPTION );
wp_delete_attachment( $rc_att, true );
wp_delete_post( $rc_post, true );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE path LIKE %s', hodima_seo_advisor_table( 'pages' ), '/rc-g%' ) );

remove_filter( 'wp_redirect', $rc_redirect, 0 );
remove_filter( 'pre_http_request', $rc_http, 30 );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ 0 ] );
$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE path LIKE %s", $rc_table, '/rc-%' ) );
false === $rc_keep[0] ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, $rc_keep[0], false );
false === $rc_keep[1] ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $rc_keep[1], false );
false === $rc_keep[2] ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $rc_keep[2], false );
hodima_seo_advisor_crawl_map( true );

echo "=== بخش ۳: نقشه سایت بدون ریدایرکت، خزنده نقشه، اولویت کالیبره (SEO 3.5.2)\n";
// نقشه سایت ما برای redirect_canonical وردپرس «دست‌نخورده» است
$GLOBALS['wp_query']->set( 'hodima_sitemap', 'index' );
$sm_on = apply_filters( 'redirect_canonical', 'https://hodima.test/sitemap.xml/', 'https://hodima.test/sitemap.xml' );
$GLOBALS['wp_query']->set( 'hodima_sitemap', '' );
$sm_off = apply_filters( 'redirect_canonical', 'https://hodima.test/other/', 'https://hodima.test/other' );
hodima_ad( 'redirect_canonical: روی نقشه ما (hodima_sitemap) خاموش، روی صفحه‌های دیگر دست‌نخورده', [ $sm_on, $sm_off ], [ false, 'https://hodima.test/other/' ] );

// خزنده: ریدایرکت فایل نقشه تا ۲ پله دنبال می‌شود و ثبت می‌شود
$sm_xml = static fn( array $urls ): string => '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . implode( '', array_map( static fn( string $u ): string => "<url><loc>{$u}</loc></url>", $urls ) ) . '</urlset>';
$sm_calls = [];
$sm_map   = [];
$sm_http  = static function ( $pre, array $args, string $url ) use ( &$sm_map, &$sm_calls ) {
	if ( ! str_contains( $url, 'hodima.test' ) && ! str_contains( $url, 'evil.test' ) ) {
		return $pre;
	}
	$sm_calls[] = $url;
	$hit        = $sm_map[ $url ] ?? null;
	if ( null === $hit ) {
		return $pre;
	}
	return [ 'headers' => [ 'content-type' => 'text/xml' ] + ( isset( $hit['loc'] ) ? [ 'location' => $hit['loc'] ] : [] ), 'body' => $hit['body'] ?? '', 'response' => [ 'code' => $hit['code'], 'message' => '' ], 'cookies' => [], 'filename' => null ];
};
add_filter( 'pre_http_request', $sm_http, 40, 3 );
$sm_new = static fn(): array => hodima_seo_advisor_crawler_new_state( hodima_seo_advisor_crawler_state(), 'full', [] );
$sm_home = rtrim( home_url( '/' ), '/' );

$sm_map = [ $sm_home . '/sitemap.xml' => [ 'code' => 301, 'loc' => $sm_home . '/sitemap.xml/' ], $sm_home . '/sitemap.xml/' => [ 'code' => 200, 'body' => $sm_xml( [ $sm_home . '/a/', $sm_home . '/b/' ] ) ] ];
$sm_s1 = $sm_new();
hodima_seo_advisor_crawler_sitemap( $sm_home . '/sitemap.xml', $sm_s1 );
hodima_ad(
	'فایل نقشه با ۳۰۱: دنبال می‌شود، دو آدرس خوانده می‌شود و ریدایرکت (کد ۳۰۱) با مبدأ و مقصد ثبت می‌شود',
	[ array_keys( $sm_s1['sitemap'] ), count( $sm_s1['sitemap_problems'] ), $sm_s1['sitemap_problems'][0]['k'] ?? '', $sm_s1['sitemap_problems'][0]['s'] ?? 0, str_starts_with( $sm_s1['sitemap_problems'][0]['u'] ?? '', '/sitemap.xml' ), str_starts_with( $sm_s1['sitemap_problems'][0]['t'] ?? '', '/sitemap.xml' ) ],
	[ [ '/a/', '/b/' ], 1, 'redirect', 301, true, true ]
);
$sm_map = [ $sm_home . '/sitemap.xml' => [ 'code' => 200, 'body' => $sm_xml( [ $sm_home . '/a/' ] ) ] ];
$sm_s2 = $sm_new();
hodima_seo_advisor_crawler_sitemap( $sm_home . '/sitemap.xml', $sm_s2 );
hodima_ad( 'فایل نقشه ۲۰۰ مستقیم: هیچ مشکلی ثبت نمی‌شود', [ array_keys( $sm_s2['sitemap'] ), $sm_s2['sitemap_problems'] ], [ [ '/a/' ], [] ] );
$sm_map = [ $sm_home . '/sitemap.xml' => [ 'code' => 301, 'loc' => $sm_home . '/gone/' ], $sm_home . '/gone/' => [ 'code' => 404 ] ];
$sm_s3 = $sm_new();
hodima_seo_advisor_crawler_sitemap( $sm_home . '/sitemap.xml', $sm_s3 );
hodima_ad( 'ریدایرکت به ۴۰۴: هم ریدایرکت و هم خطای ۴۰۴ ثبت می‌شود', [ array_column( $sm_s3['sitemap_problems'], 'k' ), array_column( $sm_s3['sitemap_problems'], 's' ) ], [ [ 'redirect', 'error' ], [ 301, 404 ] ] );
$sm_calls = [];
$sm_map   = [ $sm_home . '/sitemap.xml' => [ 'code' => 301, 'loc' => 'https://evil.test/sitemap.xml' ] ];
$sm_s4    = $sm_new();
hodima_seo_advisor_crawler_sitemap( $sm_home . '/sitemap.xml', $sm_s4 );
hodima_ad( 'ریدایرکت به بیرون از سایت دنبال نمی‌شود (درخواستی به آن نمی‌رود)؛ خطا ثبت می‌شود', [ array_filter( $sm_calls, static fn( string $u ): bool => str_contains( $u, 'evil.test' ) ), array_column( $sm_s4['sitemap_problems'], 'k' ) ], [ [], [ 'error' ] ] );
$sm_map = [ $sm_home . '/sitemap.xml' => [ 'code' => 301, 'loc' => $sm_home . '/l1/' ], $sm_home . '/l1/' => [ 'code' => 301, 'loc' => $sm_home . '/l2/' ], $sm_home . '/l2/' => [ 'code' => 301, 'loc' => $sm_home . '/l3/' ], $sm_home . '/l3/' => [ 'code' => 200, 'body' => $sm_xml( [ $sm_home . '/a/' ] ) ] ];
$sm_s5 = $sm_new();
hodima_seo_advisor_crawler_sitemap( $sm_home . '/sitemap.xml', $sm_s5 );
hodima_ad( 'زنجیره بیش از ۲ ریدایرکت: دنبال نمی‌شود (نقشه خوانده نشد)', $sm_s5['sitemap'], [] );
remove_filter( 'pre_http_request', $sm_http, 40 );

// پایان دور: مشکل نقشه حتی بدون آدرس ذخیره می‌شود؛ بدون مشکل و بدون آدرس گزینه پاک می‌شود
$sm_fin = [ 'seen' => [ '/' => 1 ], 'queue' => [], 'sitemap' => [], 'sitemap_problems' => [ [ 'u' => '/sitemap.xml', 's' => 301, 't' => '/sitemap.xml/', 'k' => 'redirect' ] ], 'maps' => 1 ];
hodima_seo_advisor_crawler_finalize( $sm_fin );
$sm_info = hodima_seo_advisor_sitemap_info();
hodima_ad( 'پایان دور: مشکل نقشه بدون هیچ آدرسی هم ذخیره می‌شود؛ شمار آدرس ۰ و فایل ۱', [ $sm_info['urls'], $sm_info['files'], count( $sm_info['problems'] ), $sm_info['fetched'] > 0 ], [ 0, 1, 1, true ] );
$sm_fin2 = [ 'seen' => [ '/' => 1 ], 'queue' => [], 'sitemap' => [] ];
hodima_seo_advisor_crawler_finalize( $sm_fin2 );
hodima_ad( 'پایان دور بدون نقشه و بدون مشکل: گزینه نقشه پاک می‌شود', get_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, 'none' ), 'none' );

// کیفیت داده: ردیف «نقشه سایت»
$sm_q = static function (): array {
	foreach ( hodima_seo_advisor_quality()['items'] as $it ) {
		if ( 'sitemap' === $it['key'] ) {
			return $it;
		}
	}
	return [];
};
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => [ '/a/', '/b/', '/c/' ], 'maps' => 6, 'problems' => [ [ 'u' => '/post-sitemap-1.xml', 's' => 301, 't' => '/post-sitemap-1.xml/', 'k' => 'redirect' ] ] ], false );
$sm_q1 = $sm_q();
hodima_ad( 'کیفیت: نقشه با ریدایرکت ← «ناقص» با مثال «مبدأ ← مقصد» و جمله «باید مستقیم ۲۰۰ بدهد»', [ $sm_q1['level'] ?? '', str_contains( $sm_q1['note'] ?? '', '/post-sitemap-1.xml' ) && str_contains( $sm_q1['note'] ?? '', '/post-sitemap-1.xml/' ), str_contains( $sm_q1['note'] ?? '', 'مستقیم ۲۰۰' ), str_contains( $sm_q1['value'] ?? '', '3' ) ], [ 'warn', true, true, true ] );
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => [ '/a/', '/b/', '/c/' ], 'maps' => 6, 'problems' => [] ], false );
hodima_ad( 'کیفیت: نقشه سالم ← «کامل» با «N آدرس در M فایل»', [ $sm_q()['level'] ?? '', str_contains( $sm_q()['value'] ?? '', '3' ) && str_contains( $sm_q()['value'] ?? '', '6' ) ], [ 'ok', true ] );
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => [], 'maps' => 1, 'problems' => [ [ 'u' => '/sitemap.xml', 's' => 404, 't' => '', 'k' => 'error' ] ] ], false );
hodima_ad( 'کیفیت: فایل نقشه خطا داد ← «ناقص»، «خوانده نشد» و کد ۴۰۴', [ $sm_q()['level'] ?? '', $sm_q()['value'] ?? '', str_contains( $sm_q()['note'] ?? '', '404' ) ], [ 'warn', 'خوانده نشد', true ] );
delete_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION );
hodima_ad( 'کیفیت: بدون نقشه و بدون مشکل ← ردیف «نقشه سایت» نمی‌آید', $sm_q(), [] );

// نمره: صفحه‌های علامت‌خورده هنوز در نمره‌اند ولی شمارشان جدا می‌آید (با «کار کلی» که فقط بازها را می‌شمارد یکی شود)
$sc3_issues = array_map( static fn( int $i ): array => [ 'path' => "/sc3-{$i}/", 'impressions' => 0, 'issues' => [ [ 'code' => 'no_meta', 'detail' => '', 'url' => '', 'items' => [] ] ] ], range( 1, 10 ) );
hodima_seo_advisor_task_set( '/sc3-1/', 'crawl_no_meta', '', 'بدون توضیح متا', 'done' );
hodima_seo_advisor_task_set( '/sc3-2/', 'crawl_no_meta', '', 'بدون توضیح متا', 'done' );
$sc3 = hodima_seo_advisor_score( $sc3_issues, 40 );
hodima_ad( 'نمره: «۱۰ صفحه بدون متا» با ۲ علامت‌خورده؛ هر ۱۰ در نمره می‌ماند', [ $sc3['top'][0]['pages'], $sc3['top'][0]['closed'] ], [ 10, 2 ] );
hodima_seo_advisor_task_set( '/sc3-1/', 'crawl_no_meta', '', '', '' );
hodima_seo_advisor_task_set( '/sc3-2/', 'crawl_no_meta', '', '', '' );

// اولویت: «+۱ کلیک (تخمین)» روی صفحه پرنمایش دیگر «بالا» نیست؛ سود زیاد هست
$pr3 = static fn( int $potential, string $mark = 'low_ctr|x' ): array => [ 'src' => 'opp', 'level' => 'warn', 'mark' => $mark, 'potential' => $potential ];
$pr3_band = static fn( int $potential, float $value ): string => hodima_seo_advisor_priority_band( hodima_seo_advisor_item_score( $pr3( $potential ), $value ) );
hodima_ad(
	'اولویت کالیبره: صفحه خیلی پرنمایش با +۱ و +۲ کلیک «متوسط»؛ +۴۱ و +۸۶ کلیک «بالا»؛ بدون پیشنهاد کلیک بدون تغییر',
	[ $pr3_band( 1, 0.9 ), $pr3_band( 2, 0.9 ), $pr3_band( 41, 0.9 ), $pr3_band( 86, 0.9 ) ],
	[ 'mid', 'mid', 'high', 'high' ]
);
hodima_ad(
	'صفحه کم‌نمایش با سود بزرگ از صفحه پرنمایش با سود ناچیز مهم‌تر است',
	hodima_seo_advisor_item_score( $pr3( 86 ), 0.2 ) > hodima_seo_advisor_item_score( $pr3( 1 ), 0.9 ),
	true
);
