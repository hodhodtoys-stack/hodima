<?php
/**
 * عکس مرجع (golden) خروجی پیشنهادهای گوگل دستار سئو: `hodima_seo_advisor_suggestions()` روی ورودی‌های ساختگی که هر قاعده و هر
 * شاخه‌اش (نرخ کلیک با/بی کلمه، طول متا سالم/ناسالم/ناشناخته، کلمه در تیتر/متن، نزدیک صفحه اول، رتبه عقب، رقابت، افت با علت‌های
 * مختلف، سقف‌ها) را می‌سازد. برای بازسازی ساختار قاعده‌ها (SEO 3.8.2): پیش از تغییر `--save`، بعد از تغییر `--check` ← «رفتار عوض نشد».
 *   HARNESS=1 php tools/wp-harness/advisor-suggest-golden.php --save tools/wp-harness/golden/advisor-suggest.json
 *   HARNESS=1 php tools/wp-harness/advisor-suggest-golden.php --check tools/wp-harness/golden/advisor-suggest.json
 * بدون پرچم فقط خلاصه و قاعده‌هایی را چاپ می‌کند که ورودی ساختگی نساخته (پوشش). کد خروج ۱ اگر --check تفاوت ببیند.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}
if ( ! function_exists( 'hodima_seo_advisor_suggestions' ) ) {
	echo "✘ ماژول دستار سئو لود نشده است\n";
	exit( 1 );
}

$mode = $argv[1] ?? '';
$file = $argv[2] ?? '';

// جدول نرخ کلیک: یک بار عمومی (بی‌داده سایت) و یک بار «آموخته از سایت» تا جمله «از داده خود سایت» هم پوشش داده شود
$ctr_backup = get_option( HODIMA_SEO_ADVISOR_CTR_OPTION, null );
$settings   = get_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, null );
delete_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION ); // min_impressions پیش‌فرض ۱۰۰۰

$q = static fn( string $term, int $clicks, int $impressions, float $position ): array => [ 'term' => $term, 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
$row = static fn( array $over = [] ): array => $over + [ 'path' => '/sg/', 'clicks' => 10, 'impressions' => 500, 'position' => 30.0, 'prev_clicks' => 0 ];
$text_none = [ 'title_headings' => 'هیچ', 'content' => 'هیچ' ];
$text_body = static fn( string $content ): array => [ 'title_headings' => 'سرتیتر دیگر', 'content' => $content ];
$healthy   = [ 'healthy' => true, 'title_px' => 450, 'meta_px' => 820, 'problems' => [] ];
$unhealthy = [ 'healthy' => false, 'title_px' => 640, 'meta_px' => 300, 'problems' => [ 'عنوان بلند است (۶۴۰ پیکسل)', 'توضیح متا کوتاه است (۳۰۰ پیکسل)' ] ];
$links_few  = [ 'total' => 1, 'text' => 0, 'score' => 0.1, 'depth' => 4, 'anchors' => [] ];
$page      = static fn( array $over = [] ): array => $over + [ 'title' => 'کش مو فنری | هدهد', 'h1' => 'کش مو فنری', 'h2' => [ 'مشخصات', 'نحوه ارسال' ], 'words' => 400, 'meta_len' => 120, 'index' => null ];
$links_well = [ 'total' => 40, 'text' => 12, 'score' => 3.0, 'depth' => 1, 'anchors' => [ [ 'anchor' => 'کش مو', 'count' => 6 ], [ 'anchor' => 'گیره', 'count' => 2 ] ] ];

/** نام ← [row, queries, text, competitors, site, meta, links, page?] */
$cases = [
	'low_ctr: صفحه با میانگین رتبه (بی کلمه)'          => [ $row( [ 'clicks' => 5, 'impressions' => 5000, 'position' => 6.0 ] ), [], null, [], null, null, null ],
	'low_ctr: با کلمه‌ها (gap) و طول ناشناخته'          => [ $row( [ 'clicks' => 20, 'impressions' => 9000, 'position' => 9.0 ] ), [ $q( 'کش مو', 5, 4000, 4.0 ), $q( 'گیره سر', 3, 3000, 7.0 ), $q( 'تل مو', 1, 900, 12.0 ) ], null, [], null, null, null ],
	'low_ctr: طول سالم'                                  => [ $row( [ 'clicks' => 20, 'impressions' => 9000, 'position' => 9.0 ] ), [ $q( 'کش مو', 5, 4000, 4.0 ) ], null, [], null, $healthy, null ],
	'low_ctr: طول ناسالم'                                => [ $row( [ 'clicks' => 20, 'impressions' => 9000, 'position' => 9.0 ] ), [ $q( 'کش مو', 5, 4000, 4.0 ) ], null, [], null, $unhealthy, null ],
	'low_ctr: زیر حداقل نمایش ← هیچ'                    => [ $row( [ 'clicks' => 0, 'impressions' => 900, 'position' => 5.0 ] ), [], null, [], null, null, null ],
	'low_ctr: رتبه عقب بی gap ← هیچ'                    => [ $row( [ 'clicks' => 0, 'impressions' => 5000, 'position' => 40.0 ] ), [], null, [], null, null, null ],
	'missing: در متن هست، در تیتر نه + رندر خزش'        => [ $row(), [ $q( 'کش مو', 2, 800, 5.0 ) ], $text_body( 'این متن درباره کش مو است' ) + [ 'rendered' => [ 'title' => 'عنوان فعلی', 'h1' => 'تیتر فعلی' ] ], [], null, null, null ],
	'missing: در متن هم نیست'                           => [ $row(), [ $q( 'کش مو', 2, 800, 5.0 ) ], $text_none, [], null, null, null ],
	'missing + near ادغام (یک کارت)'                    => [ $row(), [ $q( 'کش مو', 2, 900, 12.0 ) ], $text_body( 'کش مو' ), [], null, null, $links_few ],
	'near_first: لینک کم'                               => [ $row(), [ $q( 'گیره', 2, 900, 11.0 ) ], [ 'title_headings' => 'گیره', 'content' => 'گیره' ], [], null, null, $links_few ],
	'near_first: لینک خوب با متن لینک'                  => [ $row(), [ $q( 'کش مو', 2, 900, 11.0 ) ], [ 'title_headings' => 'کش مو', 'content' => 'کش مو' ], [], null, null, $links_well ],
	'near_first: لینک ناشناخته'                         => [ $row(), [ $q( 'گیره', 2, 900, 11.0 ) ], null, [], null, null, null ],
	'weak_query'                                         => [ $row(), [ $q( 'تل عروس', 0, 700, 35.0 ) ], null, [], null, null, null ],
	'weak_query: بعد از ۶۰ ← هیچ'                       => [ $row(), [ $q( 'تل عروس', 0, 700, 70.0 ) ], null, [], null, null, null ],
	'کلمه زیر حداقل نمایش ← هیچ'                        => [ $row(), [ $q( 'کش مو', 0, 200, 11.0 ) ], $text_none, [], null, null, null ],
	'سقف هر قاعده کلمه (۲ از هر نوع، پربالقوه اول)'     => [ $row(), [ $q( 'الف یک', 0, 600, 12.0 ), $q( 'ب دو', 0, 3000, 13.0 ), $q( 'ج سه', 0, 1500, 14.0 ), $q( 'د چهار', 0, 800, 30.0 ), $q( 'ه پنج', 0, 2000, 33.0 ), $q( 'و شش', 0, 900, 40.0 ) ], [ 'title_headings' => 'الف یک ب دو ج سه', 'content' => '' ], [], null, null, null ],
	'سقف کل پیشنهادها (۶)'                              => [ $row( [ 'clicks' => 5, 'impressions' => 20000, 'position' => 9.0 ] ), [ $q( 'الف یک', 0, 6000, 5.0 ), $q( 'ب دو', 0, 3000, 13.0 ), $q( 'ج سه', 0, 1500, 14.0 ), $q( 'د چهار', 0, 800, 30.0 ), $q( 'ه پنج', 0, 2000, 33.0 ), $q( 'و شش', 0, 900, 15.0 ) ], $text_none, [], null, null, null ],
	'cannibal: دو رقیب'                                  => [ $row(), [ $q( 'کش مو', 3, 400, 25.0 ) ], null, [ hodima_seo_advisor_term_key( 'کش مو' ) => [ [ 'path' => '/a/', 'impressions' => 900 ], [ 'path' => '/b/', 'impressions' => 120 ] ] ], null, null, null ],
	'cannibal: سقف ۳ کلمه و کلید املای قدیمی'           => [ $row(), [ $q( 'یک', 0, 400, 25.0 ), $q( 'دو', 0, 300, 25.0 ), $q( 'سه', 0, 200, 25.0 ), $q( 'چهار', 0, 150, 25.0 ) ], null, [ 'یک' => [ [ 'path' => '/a/', 'impressions' => 1 ] ], hodima_seo_advisor_term_key( 'دو' ) => [ [ 'path' => '/b/', 'impressions' => 1 ] ], 'سه' => [ [ 'path' => '/c/', 'impressions' => 1 ] ], 'چهار' => [ [ 'path' => '/d/', 'impressions' => 1 ] ] ], null, null, null ],
	'cannibal: زیر حداقل ← هیچ'                         => [ $row(), [ $q( 'کش مو', 0, 50, 25.0 ) ], null, [ 'کش مو' => [ [ 'path' => '/a/', 'impressions' => 1 ] ] ], null, null, null ],
	'decline: رتبه بدتر'                                 => [ $row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 900, 'prev_impressions' => 950, 'position' => 15.0, 'prev_position' => 8.0 ] ), [], null, [], null, null, null ],
	'decline: نمایش کمتر'                                => [ $row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 300, 'prev_impressions' => 1200, 'position' => 8.2, 'prev_position' => 8.0 ] ), [], null, [], null, null, null ],
	'decline: نرخ کلیک کمتر'                             => [ $row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 1000, 'prev_impressions' => 1000, 'position' => 8.2, 'prev_position' => 8.0 ] ), [], null, [], null, null, null ],
	'decline: کل سایت هم افت کرده ← هیچ'                => [ $row( [ 'clicks' => 40, 'prev_clicks' => 100, 'impressions' => 1000, 'prev_impressions' => 1000, 'position' => 8.2, 'prev_position' => 8.0 ] ), [], null, [], [ 'clicks_ratio' => 0.45, 'impressions_ratio' => 0.9 ], null, null ],
	'decline: سایت کمی افت، صفحه بیشتر'                 => [ $row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 1000, 'prev_impressions' => 1100, 'position' => 8.5, 'prev_position' => 8.0 ] ), [], null, [], [ 'clicks_ratio' => 0.8, 'impressions_ratio' => 0.95 ], null, null ],
	'صفحه‌بندی ← هیچ'                                    => [ $row( [ 'path' => '/shop/page/2/', 'clicks' => 5, 'impressions' => 5000, 'position' => 6.0 ] ), [], null, [], null, null, null ],
	'کلمه برندی کنار می‌ماند'                            => [ $row(), [ $q( strtolower( (string) get_bloginfo( 'name' ) ) . ' کش', 0, 900, 11.0 ) ], $text_none, [], null, null, null ],
	// گام‌های «دقیقا چه کنم» با واقعیت‌های خزش و بررسی ایندکس (SEO 3.8.3، طرح تأییدشده کاربر)
	'خزش: نرخ کلیک، کلمه وسط عنوان، متا خالی'            => [ $row( [ 'clicks' => 20, 'impressions' => 9000, 'position' => 9.0 ] ), [ $q( 'کش مو فنری', 5, 4000, 4.0 ) ], null, [], null, $unhealthy, null, $page( [ 'title' => 'خرید عمده کش مو فنری رنگی | هدهد', 'meta_len' => 0 ] ) ],
	'خزش: نرخ کلیک، عنوان با کلمه شروع می‌شود'          => [ $row( [ 'clicks' => 20, 'impressions' => 9000, 'position' => 9.0 ] ), [ $q( 'کش مو فنری', 5, 4000, 4.0 ) ], null, [], null, null, null, $page( [ 'title' => 'کش مو فنری رنگی | هدهد' ] ) ],
	'خزش: نرخ کلیک، کلمه در عنوان نیست، طول سالم'       => [ $row( [ 'clicks' => 20, 'impressions' => 9000, 'position' => 9.0 ] ), [ $q( 'کش مو فنری', 5, 4000, 4.0 ) ], null, [], null, $healthy, null, $page( [ 'title' => 'گیره سر | هدهد' ] ) ],
	'خزش: کلمه در متن نیست، متن کم، محصول'              => [ $row( [ 'kind' => 'product' ] ), [ $q( 'کش مو بچگانه', 2, 800, 5.0 ) ], $text_none, [], null, null, null, $page( [ 'words' => 60 ] ) ],
	'خزش: کلمه در متن هست، تیتر ۲ ندارد'               => [ $row(), [ $q( 'کش مو', 2, 800, 5.0 ) ], $text_body( 'کش مو' ), [], null, null, null, $page( [ 'h2' => [] ] ) ],
	'خزش: نزدیک صفحه اول، کلمه در عنوان، لینک بی‌کلمه' => [ $row(), [ $q( 'کش مو فنری', 2, 900, 11.0 ) ], [ 'title_headings' => 'کش مو فنری', 'content' => 'کش مو فنری' ], [], null, null, [ 'total' => 4, 'text' => 4, 'score' => 0.5, 'depth' => 2, 'anchors' => [ [ 'anchor' => 'اینجا', 'count' => 2 ], [ 'anchor' => 'مشاهده محصول', 'count' => 1 ] ] ], $page( [ 'title' => 'کش مو فنری رنگی | هدهد' ] ) ],
	'خزش: نزدیک صفحه اول، تیتر ۲ با کلمه'              => [ $row(), [ $q( 'مشخصات', 2, 900, 11.0 ) ], [ 'title_headings' => 'مشخصات', 'content' => 'مشخصات' ], [], null, null, null, $page() ],
	'خزش: افت نمایش، ایندکس سالم'                       => [ $row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 300, 'prev_impressions' => 1200, 'position' => 8.2, 'prev_position' => 8.0 ] ), [], null, [], null, null, null, $page( [ 'index' => [ 'state' => 'indexed', 'days' => 3, 'coverage' => 'Submitted and indexed', 'google_canonical' => '', 'mismatch' => false ] ] ) ],
	'خزش: افت نمایش، ایندکس نشده'                       => [ $row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 300, 'prev_impressions' => 1200, 'position' => 8.2, 'prev_position' => 8.0 ] ), [], null, [], null, null, null, $page( [ 'index' => [ 'state' => 'crawled_not_indexed', 'days' => 0, 'coverage' => 'Crawled - currently not indexed', 'google_canonical' => '', 'mismatch' => false ] ] ) ],
	'خزش: افت نمایش، canonical گوگل دیگر'               => [ $row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 300, 'prev_impressions' => 1200, 'position' => 8.2, 'prev_position' => 8.0 ] ), [], null, [], null, null, null, $page( [ 'index' => [ 'state' => 'indexed', 'days' => 5, 'coverage' => 'Submitted and indexed', 'google_canonical' => 'https://hodima.test/a/', 'mismatch' => true ] ] ) ],
	'خزش: افت رتبه (ایندکس بی‌اثر)'                     => [ $row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 900, 'prev_impressions' => 950, 'position' => 15.0, 'prev_position' => 8.0 ] ), [], null, [], null, null, null, $page( [ 'index' => [ 'state' => 'indexed', 'days' => 3, 'coverage' => '', 'google_canonical' => '', 'mismatch' => false ] ] ) ],
];

$out = [];
foreach ( [ 'generic' => null, 'learned' => [ 'computed' => 1, 'impressions' => 50000, 'buckets' => [ 5 => [ 'impressions' => 50000, 'clicks' => 2000, 'weight' => 0.8, 'ctr' => 4.0 ] ], 'table' => [ 2 => 20.0, 3 => 12.0, 5 => 7.4, 7 => 5.0, 10 => 3.0, 20 => 1.2 ] ] ] as $table => $stored ) {
	if ( null === $stored ) {
		delete_option( HODIMA_SEO_ADVISOR_CTR_OPTION );
	} else {
		update_option( HODIMA_SEO_ADVISOR_CTR_OPTION, $stored, false );
	}
	foreach ( $cases as $name => $args ) {
		$out[ $table . ' | ' . $name ] = hodima_seo_advisor_suggestions( ...$args );
	}
}

null === $ctr_backup ? delete_option( HODIMA_SEO_ADVISOR_CTR_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CTR_OPTION, $ctr_backup, false );
if ( null !== $settings ) {
	update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, $settings, false );
}

$rules = [];
foreach ( $out as $list ) {
	foreach ( $list as $item ) {
		$rules[ $item['rule'] ] = ( $rules[ $item['rule'] ] ?? 0 ) + 1;
	}
}
ksort( $rules );
$json = (string) wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

if ( '--save' === $mode && '' !== $file ) {
	file_put_contents( $file, $json . "\n" );
	echo '✔ عکس مرجع ذخیره شد: ' . count( $out ) . ' ورودی، ' . array_sum( $rules ) . " پیشنهاد\n";
} elseif ( '--check' === $mode && '' !== $file ) {
	$want = json_decode( (string) file_get_contents( $file ), true );
	$got  = json_decode( $json, true );
	if ( $want === $got ) {
		echo '✔ خروجی پیشنهادها با عکس مرجع یکی است (' . count( $out ) . ' ورودی، ' . array_sum( $rules ) . " پیشنهاد)\n";
	} else {
		echo "✘ خروجی پیشنهادها با عکس مرجع فرق دارد\n";
		foreach ( array_unique( [ ...array_keys( (array) $want ), ...array_keys( (array) $got ) ] ) as $key ) {
			if ( ( $want[ $key ] ?? null ) !== ( $got[ $key ] ?? null ) ) {
				echo "  ✘ {$key}\n      انتظار: " . mb_substr( (string) wp_json_encode( $want[ $key ] ?? null, JSON_UNESCAPED_UNICODE ), 0, 600 ) . "\n      واقعی:  " . mb_substr( (string) wp_json_encode( $got[ $key ] ?? null, JSON_UNESCAPED_UNICODE ), 0, 600 ) . "\n";
			}
		}
		exit( 1 );
	}
}

echo 'پیشنهاد به تفکیک قاعده: ' . wp_json_encode( $rules, JSON_UNESCAPED_UNICODE ) . "\n";
$all     = [ 'low_ctr', 'missing_headings', 'missing_content', 'near_first', 'weak_query', 'cannibal', 'decline' ];
$missing = array_diff( $all, array_keys( $rules ) );
echo 'قاعده‌هایی که ورودی ساختگی نساخت: ' . ( $missing ? implode( '، ', $missing ) : 'هیچ' ) . "\n";
