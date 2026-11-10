<?php
/**
 * عکس مرجع (golden) خروجی ایرادهای فنی دستار سئو: `hodima_seo_advisor_crawl_issues()` روی یک داده خزش ساختگی که هر نوع ایراد
 * را می‌سازد. برای بازسازی ساختار قاعده‌ها (SEO 3.6.1): پیش از تغییر `--save`، بعد از تغییر `--check` ← «رفتار عوض نشد».
 *   HARNESS=1 php tools/wp-harness/advisor-issues-golden.php --save tools/wp-harness/golden/advisor-issues.json
 *   HARNESS=1 php tools/wp-harness/advisor-issues-golden.php --check tools/wp-harness/golden/advisor-issues.json
 * بدون پرچم فقط خلاصه و کدهایی را چاپ می‌کند که داده ساختگی نساخته (پوشش). کد خروج ۱ اگر --check تفاوت ببیند.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "این ابزار داده می‌سازد و پاک می‌کند؛ فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}
if ( ! function_exists( 'hodima_seo_advisor_crawl_issues' ) ) {
	echo "✘ ماژول دستار سئو لود نشده است\n";
	exit( 1 );
}

global $wpdb;

$mode = $argv[1] ?? '';
$file = $argv[2] ?? '';

$pages_t = hodima_seo_advisor_table( 'crawl_pages' );
$links_t = hodima_seo_advisor_table( 'crawl_links' );
$gsc_t   = hodima_seo_advisor_table( 'pages' );

$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $pages_t ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $links_t ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $gsc_t ) );

/** ردیف سالم پایه؛ هر مورد فقط چیزی را که می‌خواهد خراب کند بازنویسی می‌کند. */
$base = static fn( string $path ): array => [
	'path'       => $path,
	'status'     => 200,
	'indexable'  => 1,
	'reason'     => '',
	'title'      => 'عنوان یگانه ' . md5( $path ),
	'title_len'  => 50,
	'meta_len'   => 120,
	'title_px'   => 400,
	'meta_px'    => 800,
	'h1'         => 'تیتر ' . md5( $path ),
	'h2'         => '',
	'resp_ms'    => 200,
	'inlinks'    => 9,
	'text_links' => 5,
	'depth'      => 1,
	'score'      => 1,
	'redirect_to' => '',
	'no_snippet' => 0,
	'words'      => 400,
	'h1_count'   => 1,
	'img_noalt'  => 0,
	'http_links' => 0,
	'canonical'  => $path,
	'img_list'   => '',
	'img_empty'  => 0,
	'meta_hash'  => '',
	'http_list'  => '',
	'notext_links' => 0,
	'notext_list'  => '',
	'js_links'     => 0,
	'js_list'      => '',
	'soft404'      => '',
	'ld_count'     => 1,
	'ld_errors'    => '',
	'canonical_url' => '',
];

$cases = [
	'/g-ok/'        => [],
	'/'             => [], // صفحه اصلی سالم؛ در نقشه سایت هم هست (برای گذر «سایت پنهان»)
	'/g-no-title/'  => [ 'title' => '', 'title_len' => 0, 'title_px' => 0 ],
	'/g-short/'     => [ 'title_px' => 100, 'title_len' => 10 ],
	'/g-long/'      => [ 'title_px' => 700, 'title_len' => 90 ],
	'/g-no-meta/'   => [ 'meta_len' => 0, 'meta_px' => 0 ],
	'/g-pop-nometa/' => [ 'meta_len' => 0, 'meta_px' => 0 ], // پربازدید ← no_meta با سطح کاتالوگ (زرد)، نه اطلاع (SEO 3.6.2)
	'/g-nosnip/'    => [ 'no_snippet' => 1 ],
	'/g-no-h1/'     => [ 'h1' => '', 'h1_count' => 0 ],
	'/g-multi-h1/'  => [ 'h1_count' => 3 ],
	'/g-dup-a/'     => [ 'title' => 'عنوان تکراری آزمون', 'h1' => 'تیتر تکراری آزمون' ],
	'/g-dup-b/'     => [ 'title' => 'عنوان تکراری آزمون', 'h1' => 'تیتر تکراری آزمون' ],
	'/g-sim-a/'     => [ 'title' => 'گیره مو مرواریدی کوچک مجلسی زنانه', 'h1' => 'گیره مو مرواریدی کوچک مجلسی زنانه' ],
	'/g-sim-b/'     => [ 'title' => 'گیره مو مرواریدی کوچیک مجلسی زنانه', 'h1' => 'گیره مو مرواریدی کوچیک مجلسی زنانه' ],
	'/g-slow/'      => [ 'resp_ms' => 3000 ],
	'/g-orphan/'    => [ 'inlinks' => 0, 'text_links' => 0 ],
	'/g-low/'       => [ 'inlinks' => 1, 'text_links' => 1 ],
	'/g-deep/'      => [ 'depth' => 6 ],
	'/g-island/'    => [ 'depth' => 65535, 'inlinks' => 2 ],
	'/g-http/'      => [ 'http_links' => 3, 'http_list' => '[["http://hodima.test/g-ok/","صفحه سالم"],["http://hodima.test/g-a/",""]]' ], // SEO 3.7.3: فهرست «کدام لینک‌ها؟»
	'/g-notext/'    => [ 'notext_links' => 2, 'notext_list' => '[["/g-ok/",""],["/g-a/",""]]' ], // SEO 3.8.4: لینک بی‌متن
	'/g-js/'        => [ 'js_links' => 1, 'js_list' => '[["/g-ok/","مشاهده"]]' ], // SEO 3.8.4: لینک جاوااسکریپتی
	'/g-soft404/'   => [ 'soft404' => 'empty|woocommerce-no-products-found' ], // SEO 3.8.6: دسته خالی
	'/g-ld/'        => [ 'ld_count' => 2, 'ld_errors' => '[["json","Syntax error"],["type","#n"]]' ], // SEO 3.8.6: JSON-LD خراب
	'/g-prod/'      => [ 'ld_count' => 1, 'ld_errors' => '[["product_fields","اسکیمای Product این فیلدها را ندارد: قیمت (offers.price)، ارز (priceCurrency)"]]' ], // SEO 3.9.5: اسکیمای محصول ناقص
	'/g-faq/'       => [ 'ld_count' => 1, 'ld_errors' => '[["faq_hidden","10 از 20 پرسش در متن صفحه دیده نمی‌شود (10 انگلیسی)"]]' ], // SEO 3.9.4: FAQ فقط در اسکیما
	'/g-nocanon/'   => [ 'canonical' => '' ],
	'/g-canbad/'    => [ 'canonical' => '/g-gone/' ],
	'/g-canext/'    => [ 'canonical' => '!ext', 'canonical_url' => 'https://other.example/g-canext/' ], // SEO 3.9.3: canonical به سایت دیگر
	'/g-canvar/'    => [ 'canonical_url' => 'https://hodima.test/g-canvar/?color=red' ], // SEO 3.9.3: canonical با پارامتر
	'/g-canredir/'  => [ 'canonical' => '/g-redir/' ], // SEO 3.8.5: canonical به ریدایرکت ← آدرس پیشنهادی مقصد نهایی /g-ok/
	'/g-gone/'      => [ 'status' => 404, 'inlinks' => 2 ],
	'/g-redir/'     => [ 'status' => 301, 'inlinks' => 2, 'redirect_to' => '/g-redir2/' ],
	'/g-redir2/'    => [ 'status' => 301, 'inlinks' => 2, 'redirect_to' => '/g-ok/' ],
	'/g-noindex/'   => [ 'indexable' => 0, 'reason' => 'Noindex', 'inlinks' => 2 ],
	'/my-account/lost-password/' => [ 'canonical' => '/my-account/', 'inlinks' => 2 ], // صفحه سیستمی عمدا noindex ← ایراد نیست (SEO 3.6.2)
	'/g-canon-other/' => [ 'indexable' => 0, 'reason' => 'Canonicalised', 'canonical' => '/g-ok/', 'inlinks' => 2 ],
	'/g-thin/'      => [ 'words' => 50 ],
	'/g-img/'       => [ 'img_noalt' => 2, 'img_empty' => 1 ],
	'/g-paged/page/2/' => [ 'title_px' => 100, 'meta_len' => 0, 'inlinks' => 1 ],
	'/g-old/'       => [ 'words' => -1, 'h1_count' => -1, 'img_noalt' => -1, 'http_links' => -1, 'notext_links' => -1, 'js_links' => -1, 'ld_count' => -1, 'img_empty' => -1, 'canonical' => '', 'text_links' => -1 ],
	'/g-linker/'    => [],
	'/g-500/'       => [ 'status' => 503, 'inlinks' => 2 ], // خطای سرور ← server_error (قرمز؛ گوگل: ۵xx دائمی از ایندکس برمی‌افتد)
	'/g-loop-a/'    => [ 'status' => 301, 'inlinks' => 2, 'redirect_to' => '/g-loop-b/' ], // حلقه ← redirect_loop
	'/g-loop-b/'    => [ 'status' => 301, 'inlinks' => 2, 'redirect_to' => '/g-loop-a/' ],
	'/g-c1/'        => [ 'status' => 301, 'inlinks' => 2, 'redirect_to' => '/g-c2/' ], // زنجیره ۵ پرشی: c1 کهربایی، c2 تا c4 اطلاع
	'/g-c2/'        => [ 'status' => 301, 'inlinks' => 2, 'redirect_to' => '/g-c3/' ],
	'/g-c3/'        => [ 'status' => 301, 'inlinks' => 2, 'redirect_to' => '/g-c4/' ],
	'/g-c4/'        => [ 'status' => 301, 'inlinks' => 2, 'redirect_to' => '/g-c5/' ],
	'/g-c5/'        => [ 'status' => 301, 'inlinks' => 2, 'redirect_to' => '/g-ok/' ],
	'/cart/'        => [ 'indexable' => 0, 'reason' => 'Noindex', 'inlinks' => 2 ], // صفحه سیستمی noindex ← ایراد نیست (SEO 3.6.2)
	'/my-account/'  => [ 'indexable' => 0, 'reason' => 'Noindex', 'inlinks' => 2 ], // صفحه سیستمی noindex؛ فراموشی رمز با canonical به آن ← canonical_bad نمی‌دهد (SEO 3.6.6)
	'/g-meta-a/'    => [ 'meta_hash' => 'abc123abc123' ], // توضیح متای یکسان در ۲ صفحه ← dup_meta (SEO 3.6.5)
	'/g-meta-b/'    => [ 'meta_hash' => 'abc123abc123' ],
	'/g-canbody/'   => [ 'canonical' => '!body' ], // canonical فقط در body ← canonical_in_body
];

foreach ( $cases as $path => $over ) {
	$wpdb->insert( $pages_t, array_merge( $base( $path ), $over ) );
}

// نوع صفحه و نمایش گوگل (برای «متن کم»، نشان اولویت و ردیف‌های ریدایرکت/noindex/خطا که نمایش یا لینک می‌خواهند)
foreach ( [ '/g-thin/' => [ 'page', 120 ], '/g-short/' => [ 'page', 900 ], '/g-no-meta/' => [ 'product', 50 ], '/g-noindex/' => [ 'page', 10 ], '/g-pop-nometa/' => [ 'page', 1500 ] ] as $path => [ $kind, $imp ] ) {
	$wpdb->insert( $gsc_t, [ 'path' => $path, 'kind' => $kind, 'impressions' => $imp, 'avg_position' => 8, 'advice' => '' ] );
}

// لینک‌های داخلی به صفحه خراب، ریدایرکت‌شده و noindex (ایرادهای link_*)
foreach ( [ [ '/g-linker/', '/g-gone/', 'content', 'لینک خراب' ], [ '/g-linker/', '/g-redir/', 'content', 'لینک ریدایرکت' ], [ '/g-linker/', '/g-noindex/', 'content', 'لینک noindex' ], [ '/g-linker/', '/my-account/lost-password/', 'content', 'لینک حساب کاربری' ] ] as [ $src, $dst, $pos, $anchor ] ) {
	$wpdb->insert( $links_t, [ 'source_path' => $src, 'dest_path' => $dst, 'link_position' => $pos, 'anchor' => $anchor, 'is_follow' => 1 ] );
}

// نقشه سایت: صفحه خراب، noindex، ریدایرکت و canonical به دیگری داخلش (sitemap_bad)، بقیه نیستند (not_in_sitemap)
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'paths' => [ '/', '/g-gone/', '/g-noindex/', '/g-ok/', '/g-redir/', '/g-canon-other/' ], 'time' => time() ], false ); // ریدایرکت و canonical در نقشه ← آدرس درست (SEO 3.8.5)

hodima_seo_advisor_crawl_map( true );

$issues = hodima_seo_advisor_crawl_issues();

// گذر دوم: «سایت پنهان» (blog_public = 0؛ SEO 3.6.7) فقط برای سه صفحه: صفحه اصلی ← site_hidden؛ noindex و canonicalised جداگانه فقط canonicalised می‌ماند
$blog_public = get_option( 'blog_public', '1' );
update_option( 'blog_public', '0' );
hodima_seo_advisor_crawl_map( true );
$hidden_rows = array_values( array_filter( hodima_seo_advisor_crawl_issues(), static fn( array $row ): bool => in_array( (string) $row['path'], [ '/', '/g-noindex/', '/g-canon-other/' ], true ) ) );
update_option( 'blog_public', $blog_public );
hodima_seo_advisor_crawl_map( true );

// خروجی پایدار: زمان‌ها و شناسه‌های وابسته به اجرا حذف می‌شوند
$stable = array_map(
	static function ( array $row ): array {
		$row['issues'] = array_map( static fn( array $i ): array => $i, (array) $row['issues'] );
		return $row;
	},
	$issues
);
usort( $stable, static fn( array $a, array $b ): int => strcmp( (string) $a['path'], (string) $b['path'] ) );
usort( $hidden_rows, static fn( array $a, array $b ): int => strcmp( (string) $a['path'], (string) $b['path'] ) );
$json = (string) wp_json_encode( [ 'pages' => $stable, 'hidden_site' => $hidden_rows ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

$catalog   = array_keys( hodima_seo_advisor_issue_catalog() );
$triggered = [];
foreach ( [ ...$stable, ...$hidden_rows ] as $row ) {
	foreach ( $row['issues'] as $i ) {
		$triggered[ (string) $i['code'] ] = ( $triggered[ (string) $i['code'] ] ?? 0 ) + 1;
	}
}

if ( ! getenv( 'HODIMA_GOLDEN_KEEP' ) ) { // HODIMA_GOLDEN_KEEP=1: داده را نگه دار (برای عکس گرفتن از پیشخوان با همین داده)
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $pages_t ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $links_t ) );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $gsc_t ) );
	delete_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION );
}

$exit = 0;

if ( '--save' === $mode && '' !== $file ) {
	if ( ! is_dir( dirname( $file ) ) ) {
		mkdir( dirname( $file ), 0777, true );
	}
	file_put_contents( $file, $json . "\n" );
	echo "✔ عکس مرجع ذخیره شد: {$file} (" . count( $stable ) . " صفحه، " . array_sum( $triggered ) . " ایراد)\n";
} elseif ( '--check' === $mode && '' !== $file ) {
	$expected = str_replace( "\r\n", "\n", (string) file_get_contents( $file ) ); // tools/eol.py فایل JSON را CRLF می‌کند؛ مقایسه به پایان خط وابسته نباشد
	if ( trim( $expected ) === trim( $json ) ) {
		echo "✔ خروجی ایرادها با عکس مرجع یکی است (" . count( $stable ) . " صفحه، " . array_sum( $triggered ) . " ایراد)\n";
	} else {
		$tmp = tempnam( sys_get_temp_dir(), 'adv-golden' );
		file_put_contents( (string) $tmp, $json . "\n" );
		echo "✘ خروجی ایرادها با عکس مرجع فرق دارد:\n";
		passthru( 'diff -u --strip-trailing-cr ' . escapeshellarg( $file ) . ' ' . escapeshellarg( (string) $tmp ) . ' | head -90' );
		$exit = 1;
	}
}

ksort( $triggered );
echo 'ایراد به تفکیک کد: ' . wp_json_encode( $triggered, JSON_UNESCAPED_UNICODE ) . "\n";
$missing = array_values( array_diff( $catalog, array_keys( $triggered ) ) );
echo 'کدهای کاتالوگ که داده ساختگی نساخت: ' . ( $missing ? implode( '، ', $missing ) : 'هیچ' ) . "\n";

exit( $exit );
