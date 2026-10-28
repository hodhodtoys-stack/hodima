<?php
/**
 * داده آزمایشی «لینک‌های این کلمه» برای advisor-links-check.mjs (مرورگر واقعی): همان داده advisor-links-fixture.php
 * (که advisor-tests.php هم می‌سازد) + مدیر ورود و وضعیت دریافت داده.
 *   HARNESS=1 php tools/wp-harness/advisor-links-seed.php
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "این اسکریپت داده می‌سازد؛ فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}

if ( ! username_exists( 'advadmin' ) ) {
	wp_insert_user( [ 'user_login' => 'advadmin', 'user_pass' => 'adv-pass-123', 'role' => 'administrator' ] );
}
wp_set_current_user( 1 );

require __DIR__ . '/advisor-links-fixture.php';
hodima_lk_fixture();

update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:hodima.test', 'days' => 90, 'pages' => 7, 'queries' => 6, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
update_option( 'hodima_discover_sc_key', '{}', false );

// «انجام شد / رد شد» تب ایرادهای فنی (SEO 3.0.2): یک صفحه با چهار ایراد، کنار ردیف‌های خزش همان داده (جایگزین نه)
$GLOBALS['wpdb']->insert( hodima_seo_advisor_table( 'crawl_pages' ), [ 'path' => '/mk-page/', 'status' => 200, 'indexable' => 1, 'reason' => '', 'title' => 'کوتاه', 'title_len' => 5, 'meta_len' => 0, 'h1' => '', 'h2' => '', 'resp_ms' => 2600, 'inlinks' => 4, 'text_links' => 2, 'depth' => 2, 'score' => 0, 'redirect_to' => '' ] );
hodima_seo_advisor_crawl_map( true );
if ( hodima_seo_advisor_crawl_status()['imported'] <= 0 ) { // تب ایرادهای فنی فقط بعد از یک بار خواندن خزش فهرست را نشان می‌دهد
	update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time() - 3600, 'pages' => (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM %i', hodima_seo_advisor_table( 'crawl_pages' ) ) ), 'links' => 0, 'files' => [] ], false );
}
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
echo "ok\n";
