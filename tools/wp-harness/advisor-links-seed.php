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
echo "ok\n";
