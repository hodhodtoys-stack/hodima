<?php
/**
 * خزنده دستار سئو را روی وردپرس ابزار تست با درخواست واقعی اجرا می‌کند (نه پاسخ ساختگی): درخواست‌های https://hodima.test
 * با curl به php -S محلی (advisor-router.php) می‌روند. بعد از یک دور کامل جدول crawl_pages و ایرادها را خلاصه می‌کند.
 *   PORT=8791 HARNESS=1 php tools/wp-harness/advisor-crawl-local.php
 * (راه‌اندازی سرور: سربرگ advisor-router.php). فقط برای ابزار تست؛ داده خزش را پاک و دوباره می‌سازد.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';

require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "این اسکریپت داده خزش را عوض می‌کند؛ فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}

$port = (int) ( getenv( 'PORT' ) ?: 8791 );

// وردپرس ابزار تست درخواست بیرونی را می‌بندد؛ خودمان با curl به سرور محلی می‌فرستیم
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( $port ) {
		if ( ! str_starts_with( (string) $url, 'https://hodima.test' ) ) {
			return $pre;
		}
		$ch = curl_init( 'http://127.0.0.1:' . $port . substr( (string) $url, strlen( 'https://hodima.test' ) ) );
		curl_setopt_array( $ch, [ CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => [ 'Host: hodima.test', 'Accept: text/html' ] ] );
		$raw = curl_exec( $ch );
		if ( false === $raw ) {
			return new WP_Error( 'curl', curl_error( $ch ) );
		}
		$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		$hdr  = [];
		foreach ( explode( "\r\n", substr( (string) $raw, 0, $size ) ) as $line ) {
			if ( str_contains( $line, ':' ) ) {
				[ $name, $value ]            = explode( ':', $line, 2 );
				$hdr[ strtolower( trim( $name ) ) ] = trim( $value );
			}
		}
		return [ 'headers' => $hdr, 'body' => substr( (string) $raw, $size ), 'response' => [ 'code' => $code, 'message' => '' ], 'cookies' => [], 'filename' => null ];
	},
	10,
	3
);
add_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );

wp_set_current_user( 1 );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => true, 'start' => 0, 'end' => 24, 'interval' => 7 ] );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
hodima_seo_advisor_crawler_start_now();

for ( $i = 0; $i < 80; $i++ ) {
	if ( hodima_seo_advisor_crawler_tick( true )['finished'] ) {
		break;
	}
}

$issues = hodima_seo_advisor_crawl_issues();
$count  = [];
foreach ( $issues as $row ) {
	foreach ( $row['issues'] as $issue ) {
		$count[ $issue['code'] ] = ( $count[ $issue['code'] ] ?? 0 ) + 1;
	}
}
arsort( $count );
echo json_encode( hodima_seo_advisor_crawler_status(), JSON_UNESCAPED_UNICODE ), "\n";
echo count( $issues ), ' صفحه با ایراد؛ ', json_encode( $count, JSON_UNESCAPED_UNICODE ), "\n";
