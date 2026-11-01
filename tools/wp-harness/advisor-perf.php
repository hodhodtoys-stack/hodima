<?php
/**
 * سنجش سرعت دستار سئو روی داده ساختگی بزرگ (SEO 3.4.3): ۳٬۰۰۰ صفحه خزش‌شده، ۴۰ هزار لینک، ۱٬۵۰۰ صفحه گوگل، ۳۰ هزار کلمه.
 *   HARNESS=1 php tools/wp-harness/advisor-perf.php
 * زمان و پرسش‌های پایگاه داده هر کار را می‌نویسد (SAVEQUERIES). فقط روی ابزار تست؛ داده دستار را پاک و دوباره می‌سازد.
 */

declare(strict_types=1);

define( 'SAVEQUERIES', true );
$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}

foreach ( [ 'steps-view', 'help', 'admin', 'tasks-view', 'overview-view', 'crawl-view', 'detail-view', 'score-view' ] as $file ) {
	require_once HODIMA_SEO_DIR . "/core/advisor/advisor-{$file}.php";
}
hodima_seo_advisor_install_tables();
wp_set_current_user( 1 );

global $wpdb;
foreach ( [ 'pages', 'queries', 'crawl_pages', 'crawl_links' ] as $name ) {
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( $name ) ) );
}

$pages_n = (int) ( getenv( 'PERF_PAGES' ) ?: 3000 );
$rows    = [];
$links   = [];
for ( $i = 0; $i < $pages_n; $i++ ) {
	$path   = "/p{$i}/";
	$status = 0 === $i % 97 ? 404 : 200;
	// هر سوم صفحه ایراد دارد: عنوان کوتاه، بدون متا، تصویر بدون alt
	$rows[] = [ $path, $status, 200 === $status ? 1 : 0, '', 0 === $i % 3 ? 'کوتاه' : "عنوان نمونه شماره {$i} برای آزمون سرعت دستار", 0 === $i % 3 ? 5 : 45, 0 === $i % 2 ? 0 : 120, "h1 {$i}", 'h2', 300, 1 + $i % 5, 1 + $i % 3, $i % 7, 0, '', 0, 150, 1, 0 === $i % 5 ? 2 : 0, 0, $path, 0 === $i % 5 ? 'https://hodima.test/a.jpg' : '' ];
	for ( $j = 0; $j < (int) ( getenv( 'PERF_LINKS' ) ?: 13 ); $j++ ) {
		$links[] = [ $path, '/p' . ( ( $i * 7 + $j * 13 ) % $pages_n ) . '/', 0 === $j % 4 ? 'content' : 'navigation', "متن {$j}", 1 ];
	}
}
foreach ( array_chunk( $rows, 200 ) as $chunk ) {
	hodima_seo_advisor_insert( hodima_seo_advisor_table( 'crawl_pages' ), 'path, status, indexable, reason, title, title_len, meta_len, h1, h2, resp_ms, inlinks, text_links, depth, score, redirect_to, no_snippet, words, h1_count, img_noalt, http_links, canonical, img_list', '(%s,%d,%d,%s,%s,%d,%d,%s,%s,%d,%d,%d,%d,%f,%s,%d,%d,%d,%d,%d,%s,%s)', $chunk );
}
foreach ( array_chunk( $links, 400 ) as $chunk ) {
	hodima_seo_advisor_insert( hodima_seo_advisor_table( 'crawl_links' ), 'source_path, dest_path, link_position, anchor, is_follow', '(%s,%s,%s,%s,%d)', $chunk );
}
$gsc = [];
for ( $i = 0; $i < 1500; $i++ ) {
	$gsc[ "/p{$i}/" ] = [ 'clicks' => $i % 40, 'impressions' => 3000 - $i, 'position' => 4.0 + $i % 20 ];
}
$queries = [];
for ( $i = 0; $i < 1500; $i++ ) {
	for ( $q = 0; $q < 20; $q++ ) {
		$queries[ "/p{$i}/" ][ 'کلمه ' . ( ( $i + $q * 3 ) % 1200 ) ] = [ 'clicks' => $q, 'impressions' => 60 + $q, 'position' => 5.0 + $q ];
	}
}
hodima_seo_advisor_store( $gsc, [], $queries );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'property' => 'sc-domain:hodima.test', 'days' => 90, 'pages' => 1500, 'queries' => 30000 ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => $pages_n, 'links' => count( $links ), 'files' => [], 'source' => 'crawler' ], false );
hodima_seo_advisor_crawl_map( true );

// ۸۰۰ کار ثبت‌شده مدیر (نیمی «انجام دادم»، نیمی «لازم نیست»): هر ایراد باید با این‌ها سنجیده شود
$tasks = [];
for ( $i = 0; $i < 800; $i++ ) {
	$path = 'p' . ( $i * 3 ) . '/';
	$rule = 0 === $i % 2 ? 'crawl_no_meta' : 'crawl_title_short';
	$key  = hodima_seo_advisor_task_key( "/{$path}", $rule, '' );
	$tasks[ $key ] = [ 'path' => "/{$path}", 'rule' => $rule, 'term' => '', 'title' => '', 'status' => 0 === $i % 4 ? 'done' : 'rejected', 'at' => time() - $i, 'fixed' => 0, 'back' => 0, 'check' => [] ];
}
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $tasks, false );

$measure = static function ( string $label, callable $fn ): void {
	global $wpdb;
	$q0 = count( (array) $wpdb->queries );
	$t0 = microtime( true );
	$m0 = memory_get_peak_usage( true );
	$fn();
	printf( "%-46s %7.0f ms  %5d پرسش  حافظه +%d MB\n", $label, ( microtime( true ) - $t0 ) * 1000, count( (array) $wpdb->queries ) - $q0, ( memory_get_peak_usage( true ) - $m0 ) / MB_IN_BYTES );
};

printf( "داده: %d صفحه خزش، %d لینک، %d صفحه گوگل\n", $pages_n, count( $links ), count( $gsc ) );
$measure( 'crawl_issues() بار اول', static fn() => hodima_seo_advisor_crawl_issues() );
$measure( 'crawl_issues() بار دوم (همان درخواست)', static fn() => hodima_seo_advisor_crawl_issues() );
$measure( 'competition_groups()', static fn() => hodima_seo_advisor_competition_groups() );
$measure( 'overview_build() (صفحه «کارها»)', static fn() => hodima_seo_advisor_overview_build() );
$measure( 'overview_build() بار دوم', static fn() => hodima_seo_advisor_overview_build() );
$measure( 'expected_ctr() × ۲۰٬۰۰۰', static function (): void {
	for ( $i = 0; $i < 20000; $i++ ) {
		hodima_seo_advisor_expected_ctr( 3.0 + $i % 15 );
	}
} );
$measure( 'is_brand() × ۲۰٬۰۰۰', static function (): void {
	for ( $i = 0; $i < 20000; $i++ ) {
		hodima_seo_advisor_is_brand( 'کلمه ' . $i );
	}
} );

if ( getenv( 'PERF_PARTS' ) ) {
	$issues = hodima_seo_advisor_crawl_issues();
	$count  = 0;
	foreach ( $issues as $row ) {
		$count += count( $row['issues'] );
	}
	printf( "ایرادها: %d صفحه، %d ایراد\n", count( $issues ), $count );
	$measure( 'task_closed() به شمار ایرادها', static function () use ( $issues ): void {
		foreach ( $issues as $row ) {
			foreach ( $row['issues'] as $issue ) {
				hodima_seo_advisor_task_closed( $row['path'], 'crawl_' . $issue['code'] );
			}
		}
	} );
	$measure( 'crawl_issue_info() به شمار ایرادها', static function () use ( $issues ): void {
		foreach ( $issues as $row ) {
			foreach ( $row['issues'] as $issue ) {
				hodima_seo_advisor_crawl_issue_info( $issue['code'] );
			}
		}
	} );
	$measure( 'health_dead_urls()', static fn() => hodima_seo_advisor_health_dead_urls() );
	$measure( 'health_unseen()', static fn() => hodima_seo_advisor_health_unseen() );
	$measure( 'pages(has_advice, 500)', static fn() => hodima_seo_advisor_pages( [ 'has_advice' => true, 'limit' => 500 ] ) );
}

if ( getenv( 'PERF_CRAWLER' ) ) {
	// وضعیت خزنده در سقف: ۵٬۰۰۰ مسیر دیده‌شده + ۵٬۰۰۰ آدرس در صف + ۳٬۰۰۰ seed
	$seen  = [];
	$queue = [];
	for ( $i = 0; $i < 5000; $i++ ) {
		$seen[ "/p{$i}/" ] = 1;
		$queue[]           = home_url( "/p{$i}/" );
	}
	$state = hodima_seo_advisor_crawler_state();
	$state = array_merge( $state, [ 'status' => 'running', 'seen' => $seen, 'queue' => $queue, 'seed' => array_map( static fn( int $i ): string => "p:{$i}", range( 1, 3000 ) ) ] );
	$measure( 'crawler_save() ×۱۰ (وضعیت سقف)', static function () use ( $state ): void {
		for ( $i = 0; $i < 10; $i++ ) {
			$state['done'] = $i;
			hodima_seo_advisor_crawler_save( $state );
		}
	} );
	$measure( 'crawler_state() ×۱۰۰', static function (): void {
		for ( $i = 0; $i < 100; $i++ ) {
			hodima_seo_advisor_crawler_state();
		}
	} );
	printf( "اندازه گزینه وضعیت: %d KB\n", strlen( serialize( get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ) ) ) / 1024 );
	$state = hodima_seo_advisor_crawler_state();
	$state = array_merge( $state, [ 'seen' => $seen, 'queue' => [], 'seed' => [], 'mode' => 'full', 'sitemap' => [] ] );
	$measure( 'crawler_finalize() کامل', static function () use ( &$state ): void {
		hodima_seo_advisor_crawler_finalize( $state );
	} );
}

if ( getenv( 'PERF_ANALYZE' ) ) {
	$measure( 'store() دوباره (۱٬۵۰۰ صفحه، ۳۰ هزار کلمه)', static fn() => hodima_seo_advisor_store( $gsc, [], $queries ) );
	$measure( 'analyze_batch() همه آدرس‌ها', static function (): void {
		delete_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION );
		hodima_seo_advisor_analyze_batch( microtime( true ) + 600 );
	} );
	$measure( 'save_advice() یک آدرس (ذخیره نوشته)', static fn() => hodima_seo_advisor_save_advice( '/p10/' ) );
	$measure( 'resave_object() یک شیء', static fn() => hodima_seo_advisor_resave_object( 'page', 1 ) );
}

if ( getenv( 'PERF_STORE_PARTS' ) ) {
	$paths = array_keys( $gsc );
	$measure( 'classify() ×۱٬۵۰۰ (روتر)', static function () use ( $paths ): void {
		foreach ( $paths as $p ) {
			hodima_seo_advisor_classify( $p );
		}
	} );
	$measure( 'object_title() ×۱٬۵۰۰', static function () use ( $paths ): void {
		foreach ( $paths as $i => $p ) {
			hodima_seo_advisor_object_title( 'page', 1 + $i % 5 );
		}
	} );
	$rows = [];
	foreach ( $queries as $path => $terms ) {
		foreach ( $terms as $term => $s ) {
			$rows[] = [ $path, $term, hodima_seo_advisor_term_key( (string) $term ), $s['clicks'], $s['impressions'], $s['position'] ];
		}
	}
	$measure( 'term_key() ×۳۰٬۰۰۰', static function () use ( $rows ): void {
		foreach ( $rows as $r ) {
			hodima_seo_advisor_term_key( (string) $r[1] );
		}
	} );
	$measure( 'INSERT ۳۰ هزار کلمه (بسته ۲۰۰)', static function () use ( $rows ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'queries' ) ) );
		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			hodima_seo_advisor_insert( hodima_seo_advisor_table( 'queries' ), 'path, term, term_key, clicks, impressions, avg_position', '(%s,%s,%s,%d,%d,%f)', $chunk );
		}
	} );
}

if ( getenv( 'PERF_MEMORY' ) ) {
	// دریافت سقف: ۴×۲۵ هزار ردیف صفحه (بازه فعلی و قبلی) + ۱۰۰ هزار ردیف صفحه+کلمه، همه هم‌زمان در حافظه مثل hodima_seo_advisor_run_refresh
	$mk = static function ( int $n, bool $with_query ): array {
		$out = [];
		for ( $i = 0; $i < $n; $i++ ) {
			$out[] = [ 'keys' => $with_query ? [ home_url( '/page-' . ( $i % 40000 ) . '/' ), 'کلمه شماره ' . $i ] : [ home_url( '/page-' . $i . '/' ) ], 'clicks' => $i % 5, 'impressions' => 10 + $i % 90, 'ctr' => 0.1, 'position' => 8.5 ];
		}
		return $out;
	};
	$base = memory_get_usage( true );
	$cur  = $mk( 100000, false );
	$prev = $mk( 100000, false );
	$qs   = $mk( 100000, true );
	printf( "ردیف‌های خام ۳۰۰ هزار تایی: %d MB\n", ( memory_get_usage( true ) - $base ) / MB_IN_BYTES );
	$measure( 'aggregate_pages ×۲ + aggregate_queries', static function () use ( $cur, $prev, $qs ): void {
		hodima_seo_advisor_aggregate_pages( $cur );
		hodima_seo_advisor_aggregate_pages( $prev );
		hodima_seo_advisor_aggregate_queries( $qs );
	} );
	$measure( 'find_variants()', static fn() => hodima_seo_advisor_find_variants( $cur ) );
	printf( "اوج حافظه فرایند: %d MB\n", memory_get_peak_usage( true ) / MB_IN_BYTES );
}

if ( getenv( 'PERF_MEMORY_STREAM' ) ) {
	// همان داده سقف اما دسته‌به‌دسته مثل دریافت واقعی (SEO 3.4.6): هر دسته ۲۵ هزار ردیف، بعد از جمع دور ریخته می‌شود
	$chunk = static function ( int $part, bool $with_query ): array {
		$out = [];
		for ( $i = $part * 25000; $i < ( $part + 1 ) * 25000; $i++ ) {
			$out[] = [ 'keys' => $with_query ? [ home_url( '/page-' . ( $i % 40000 ) . '/' ), 'کلمه شماره ' . $i ] : [ home_url( '/page-' . $i . '/' ) ], 'clicks' => $i % 5, 'impressions' => 10 + $i % 90, 'ctr' => 0.1, 'position' => 8.5 ];
		}
		return $out;
	};
	$measure( 'دریافت دسته‌ای ۳۰۰ هزار ردیف (جمع تدریجی)', static function () use ( $chunk ): void {
		$cur = hodima_seo_advisor_pages_sums();
		$prev = hodima_seo_advisor_pages_sums();
		$qs = hodima_seo_advisor_queries_sums();
		$var = hodima_seo_advisor_variants_sums();
		for ( $part = 0; $part < 4; $part++ ) {
			$rows = $chunk( $part, false );
			hodima_seo_advisor_pages_sum( $cur, $rows );
			hodima_seo_advisor_variants_sum( $var, $rows );
			$rows = $chunk( $part, false );
			hodima_seo_advisor_pages_sum( $prev, $rows );
			$rows = $chunk( $part, true );
			hodima_seo_advisor_queries_sum( $qs, $rows );
			unset( $rows );
		}
		hodima_seo_advisor_pages_finish( $cur );
		hodima_seo_advisor_pages_finish( $prev );
		hodima_seo_advisor_queries_finish( $qs );
		hodima_seo_advisor_variants_finish( $var );
	} );
	printf( "اوج حافظه فرایند: %d MB\n", memory_get_peak_usage( true ) / MB_IN_BYTES );
}
