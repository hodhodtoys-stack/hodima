<?php
/**
 * بخش 6 از آزمون‌های دستار سئو (tools/wp-harness/advisor-tests.php آن را به ترتیب و در همان دامنه سراسری می‌خواند):
 * پایان دور خزش در پایگاه داده، دور سریع، ایرادهای خزش و پیشخوان.
 * تنهایی اجرا نمی‌شود: متغیرها و توابع کمکی (hodima_ad، $cap، …) از فایل اصلی و بخش‌های قبلی می‌آیند.
 */

declare(strict_types=1);

echo "=== خزش: پایان دور در پایگاه داده و دور سریع (SEO 3.3.8)\n";
$fz_pt = hodima_seo_advisor_table( 'crawl_pages' );
$fz_lt = hodima_seo_advisor_table( 'crawl_links' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $fz_pt ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $fz_lt ) );
foreach ( [ '/', '/a/', '/b/', '/c/', '/d/', '/e/', '/f/' ] as $fz_p ) {
	$GLOBALS['wpdb']->insert( $fz_pt, [ 'path' => $fz_p, 'status' => 200, 'indexable' => 1, 'title' => 'عنوان ' . $fz_p, 'title_len' => 50, 'meta_len' => 100, 'title_px' => hodima_ad_tpx( 50 ), 'meta_px' => hodima_ad_mpx( 100 ), 'h1' => 'تیتر ' . $fz_p, 'words' => 300, 'h1_count' => 1, 'img_noalt' => 0, 'http_links' => 0, 'canonical' => $fz_p, 'inlinks' => 99, 'text_links' => 99, 'depth' => 99 ] );
}
// / ← a (متنی)، b (منو)؛ a ← c (متنی، nofollow)؛ b ← c (متنی)؛ c ← d؛ جزیره: e ← f
foreach ( [ [ '/', '/a/', 'content', 1 ], [ '/', '/b/', 'navigation', 1 ], [ '/a/', '/c/', 'content', 0 ], [ '/b/', '/c/', 'content', 1 ], [ '/b/', '/c/', 'footer', 1 ], [ '/c/', '/d/', 'content', 1 ], [ '/e/', '/f/', 'content', 1 ] ] as [ $fz_s, $fz_d, $fz_pos, $fz_fol ] ) {
	$GLOBALS['wpdb']->insert( $fz_lt, [ 'source_path' => $fz_s, 'dest_path' => $fz_d, 'link_position' => $fz_pos, 'anchor' => 'x', 'is_follow' => $fz_fol ] );
}
$fz_state = hodima_seo_advisor_crawler_state();
$fz_state['seen'] = array_fill_keys( [ '/', '/a/', '/b/', '/c/', '/d/', '/e/', '/f/' ], 1 );
hodima_seo_advisor_crawler_finalize( $fz_state );
$fz_rows = [];
foreach ( (array) $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( 'SELECT path, inlinks, text_links, depth FROM %i ORDER BY path', $fz_pt ), ARRAY_A ) as $fz_r ) {
	$fz_rows[ $fz_r['path'] ] = [ (int) $fz_r['inlinks'], (int) $fz_r['text_links'], (int) $fz_r['depth'] ];
}
hodima_ad(
	'پایان دور با شمارش در پایگاه داده: لینک ورودی یکتا follow (۳.۹.۳: لینک nofollow از a به c حساب نمی‌شود؛ قبلا c دو لینک ورودی داشت)، لینک متنی follow، عمق؛ جزیره = نارسیده',
	$fz_rows,
	[ '/' => [ 0, 0, 0 ], '/a/' => [ 1, 1, 1 ], '/b/' => [ 1, 0, 1 ], '/c/' => [ 1, 1, 2 ], '/d/' => [ 1, 1, 3 ], '/e/' => [ 0, 0, HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE ], '/f/' => [ 1, 1, HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE ] ]
);
// SEO 3.9.3: لینک فقط‌nofollow و لینک از راه ریدایرکت. / ← /old/ (۳۰۱ به /new/)، / ← /nf/ فقط با nofollow؛ /new/ و /nf/ خزیده‌اند.
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $fz_pt ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $fz_lt ) );
foreach ( [ '/', '/old/', '/new/', '/nf/', '/other/' ] as $fz_p ) {
	$GLOBALS['wpdb']->insert( $fz_pt, [ 'path' => $fz_p, 'status' => '/old/' === $fz_p ? 301 : 200, 'indexable' => '/old/' === $fz_p ? 0 : 1, 'redirect_to' => '/old/' === $fz_p ? '/new/' : '', 'title' => 'عنوان ' . $fz_p, 'title_len' => 50, 'meta_len' => 100, 'h1' => 'تیتر ' . $fz_p, 'words' => 300, 'h1_count' => 1, 'canonical' => $fz_p ] );
}
foreach ( [ [ '/', '/old/', 'content', 1 ], [ '/', '/nf/', 'content', 0 ], [ '/other/', '/new/', 'content', 1 ], [ '/', '/other/', 'content', 1 ] ] as [ $fz_s, $fz_d, $fz_pos, $fz_fol ] ) {
	$GLOBALS['wpdb']->insert( $fz_lt, [ 'source_path' => $fz_s, 'dest_path' => $fz_d, 'link_position' => $fz_pos, 'anchor' => 'x', 'is_follow' => $fz_fol ] );
}
$fz_state['seen'] = array_fill_keys( [ '/', '/old/', '/new/', '/nf/', '/other/' ], 1 );
hodima_seo_advisor_crawler_finalize( $fz_state );
$fz_rows2 = [];
foreach ( (array) $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( 'SELECT path, inlinks, text_links, depth FROM %i ORDER BY path', $fz_pt ), ARRAY_A ) as $fz_r ) {
	$fz_rows2[ $fz_r['path'] ] = [ (int) $fz_r['inlinks'], (int) $fz_r['text_links'], (int) $fz_r['depth'] ];
}
hodima_ad(
	'لینک فقط‌nofollow ← /nf/ بدون لینک ورودی و جزیره؛ لینک به /old/ (ریدایرکت به /new/) برای /new/ هم لینک ورودی است (از / و /other/ = ۲) با عمق ۱ (ریدایرکت کلیک اضافه نیست)',
	$fz_rows2,
	[ '/' => [ 0, 0, 0 ], '/new/' => [ 2, 2, 1 ], '/nf/' => [ 0, 0, HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE ], '/old/' => [ 1, 1, 1 ], '/other/' => [ 1, 1, 1 ] ]
);

$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $fz_pt ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $fz_lt ) );
hodima_seo_advisor_crawl_map( true );

$fz_now = time();
$fz_set = [ 'interval' => 7 ];
$fz_st  = static fn( array $over ): array => $over + [ 'finished' => $fz_now - 3 * HOUR_IN_SECONDS, 'full_at' => $fz_now - 2 * DAY_IN_SECONDS ];
hodima_ad(
	'دور سررسید: کامل (هرگز، فاصله دورها)، سریع (بیش از یک روز از آخرین دور)، هیچ',
	[
		hodima_seo_advisor_crawler_due_mode( $fz_st( [ 'finished' => 0 ] ), $fz_set ),
		hodima_seo_advisor_crawler_due_mode( $fz_st( [ 'full_at' => $fz_now - 8 * DAY_IN_SECONDS, 'finished' => $fz_now - 8 * DAY_IN_SECONDS ] ), $fz_set ),
		hodima_seo_advisor_crawler_due_mode( $fz_st( [ 'finished' => $fz_now - 26 * HOUR_IN_SECONDS ] ), $fz_set ),
		hodima_seo_advisor_crawler_due_mode( $fz_st( [] ), $fz_set ),
		hodima_seo_advisor_crawler_due_mode( $fz_st( [ 'finished' => $fz_now - 26 * HOUR_IN_SECONDS ] ), [ 'interval' => 1 ] ),
	],
	[ 'full', 'full', 'quick', '', 'full' ]
);
add_filter( 'hodima_seo_advisor_crawler_quick', '__return_false' );
hodima_ad( 'دور سریع با فیلتر خاموش نمی‌شود (فقط دور کامل می‌ماند)', hodima_seo_advisor_crawler_due_mode( $fz_st( [ 'finished' => $fz_now - 26 * HOUR_IN_SECONDS ] ), $fz_set ), '' );
remove_filter( 'hodima_seo_advisor_crawler_quick', '__return_false' );
hodima_ad( 'حالت پیش‌فرض بدون full_at: همان زمان آخرین دور (پیش از ۳.۳.۸ همه کامل بودند)', [ ( static function (): array { update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => 1234567 ], false ); $st = hodima_seo_advisor_crawler_state(); delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ); return [ $st['full_at'], $st['mode'] ]; } )() ], [ [ 1234567, 'full' ] ] );

$fz_old = wp_insert_post( [ 'post_title' => 'قدیمی', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => 'متن' ] );
$fz_new = wp_insert_post( [ 'post_title' => 'تازه', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => 'متن' ] );
$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, [ 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', $fz_now - 10 * DAY_IN_SECONDS ) ], [ 'ID' => $fz_old ] );
$GLOBALS['wpdb']->replace( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/fz-hot/', 'kind' => 'post', 'impressions' => 900 ] );
$GLOBALS['wpdb']->replace( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/fz-cold/', 'kind' => 'post', 'impressions' => 0 ] );
$fz_seed = hodima_seo_advisor_crawler_quick_seed( $fz_now - DAY_IN_SECONDS );
hodima_ad( 'seed دور سریع: پرنمایش‌ها (نه بدون نمایش)، نوشته تازه‌ویرایش‌شده (نه قدیمی)', [ in_array( 'u:/fz-hot/', $fz_seed, true ), in_array( 'u:/fz-cold/', $fz_seed, true ), in_array( 'p:' . $fz_new, $fz_seed, true ), in_array( 'p:' . $fz_old, $fz_seed, true ) ], [ true, false, true, false ] );
$GLOBALS['wpdb']->delete( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/fz-hot/' ] );
$GLOBALS['wpdb']->delete( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/fz-cold/' ] );
wp_delete_post( $fz_old, true );
wp_delete_post( $fz_new, true );

echo "=== موتور اولویت (SEO 3.4.0)\n";
hodima_ad( 'ارزش صفحه: لگاریتمی نسبت به بیشینه؛ بیشتر از نمایش یا فروش؛ صفر بدون هر دو', [ hodima_seo_advisor_page_value( 1000, 0, 1000, 0 ), hodima_seo_advisor_page_value( 0, 0, 1000, 0 ), hodima_seo_advisor_page_value( 0, 5000000, 1000, 5000000 ), round( hodima_seo_advisor_page_value( 10, 0, 10000, 0 ), 2 ), hodima_seo_advisor_page_value( 10, 0, 0, 0 ) ], [ 1.0, 0.0, 1.0, 0.26, 0.0 ] );
$pr_item = static fn( string $src, string $level, string $mark = 'no_meta', int $potential = 0 ): array => [ 'src' => $src, 'level' => $level, 'mark' => $mark, 'potential' => $potential ];
$pr_a = hodima_seo_advisor_item_score( $pr_item( 'crawl', 'bad', 'no_meta' ), 1.0 );
$pr_b = hodima_seo_advisor_item_score( $pr_item( 'crawl', 'warn', 'no_meta' ), 1.0 );
$pr_c = hodima_seo_advisor_item_score( $pr_item( 'crawl', 'bad', 'slow' ), 1.0 );
$pr_d = hodima_seo_advisor_item_score( $pr_item( 'opp', 'warn', 'low_ctr|' ), 1.0 );
$pr_e = hodima_seo_advisor_item_score( $pr_item( 'crawl', 'warn', 'no_meta' ), 0.0 );
hodima_ad( 'امتیاز: قرمز > کهربایی؛ سختی کمتر بیشتر؛ ارزش کمتر کمتر؛ پیشنهاد گوگل (تخمین) کمتر از ایراد فنی هم‌سطح', [ $pr_a > $pr_b, $pr_a > $pr_c, $pr_b > $pr_e, hodima_seo_advisor_item_score( $pr_item( 'crawl', 'warn', 'no_meta' ), 1.0 ) > $pr_d, round( $pr_a, 2 ) ], [ true, true, true, true, 1.0 ] );
hodima_ad( 'پیشنهاد گوگل با کلیک بالقوه زیاد حتی در صفحه کم‌نمایش ارزش می‌گیرد', hodima_seo_advisor_item_score( $pr_item( 'opp', 'warn', 'near_first|x', 900 ), 0.0 ) > hodima_seo_advisor_item_score( $pr_item( 'opp', 'warn', 'near_first|x', 0 ), 0.0 ), true );
hodima_ad( 'نشان اولویت از امتیاز: بالا، متوسط، کم', [ hodima_seo_advisor_priority_band( 0.5 ), hodima_seo_advisor_priority_band( 0.2 ), hodima_seo_advisor_priority_band( 0.05 ) ], [ 'high', 'mid', 'low' ] );
hodima_ad( 'سختی رفع: ایراد فنی از فهرست واحد، پیشنهاد از قاعده، ایندکس ۱، بقیه ۲', [ hodima_seo_advisor_item_effort( [ 'src' => 'crawl', 'mark' => 'slow' ] ), hodima_seo_advisor_item_effort( [ 'src' => 'crawl', 'mark' => 'no_meta' ] ), hodima_seo_advisor_item_effort( [ 'src' => 'opp', 'mark' => 'weak_query|x' ] ), hodima_seo_advisor_item_effort( [ 'src' => 'index', 'mark' => '' ] ), hodima_seo_advisor_item_effort( [ 'src' => 'dead', 'mark' => '' ] ) ], [ 3, 1, 3, 1, 2 ] );

// فروش ۹۰ روز اخیر از جدول آمار ووکامرس (فقط خواندن)
$pr_tbl = $GLOBALS['wpdb']->prefix . 'wc_order_product_lookup';
$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS `{$pr_tbl}`" );
delete_transient( HODIMA_SEO_ADVISOR_SALES_CACHE );
hodima_ad( 'بدون جدول آمار ووکامرس: فروش خالی و کش می‌شود', [ hodima_seo_advisor_sales_map(), is_array( get_transient( HODIMA_SEO_ADVISOR_SALES_CACHE ) ) ], [ [], true ] );
delete_transient( HODIMA_SEO_ADVISOR_SALES_CACHE );
$GLOBALS['wpdb']->query( "CREATE TABLE `{$pr_tbl}` ( order_item_id bigint(20) NOT NULL, order_id bigint(20) NOT NULL, product_id bigint(20) NOT NULL, variation_id bigint(20) NOT NULL, date_created datetime NOT NULL, product_net_revenue double NOT NULL )" );
$pr_p1 = wp_insert_post( [ 'post_title' => 'محصول پرفروش', 'post_status' => 'publish', 'post_type' => 'post' ] );
$pr_p2 = wp_insert_post( [ 'post_title' => 'محصول کم‌فروش', 'post_status' => 'publish', 'post_type' => 'post' ] );
$pr_new = gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS );
$pr_old = gmdate( 'Y-m-d H:i:s', time() - 200 * DAY_IN_SECONDS );
foreach ( [ [ $pr_p1, 6000000, $pr_new ], [ $pr_p1, 4000000, $pr_new ], [ $pr_p2, 500000, $pr_new ], [ $pr_p2, 99000000, $pr_old ] ] as $pr_i => [ $pr_id, $pr_amount, $pr_date ] ) {
	$GLOBALS['wpdb']->insert( $pr_tbl, [ 'order_item_id' => $pr_i + 1, 'order_id' => $pr_i + 1, 'product_id' => $pr_id, 'variation_id' => 0, 'date_created' => $pr_date, 'product_net_revenue' => $pr_amount ] );
}
$pr_sales = hodima_seo_advisor_sales_map();
$pr_k1    = (string) hodima_seo_advisor_path_key( (string) get_permalink( $pr_p1 ) );
$pr_k2    = (string) hodima_seo_advisor_path_key( (string) get_permalink( $pr_p2 ) );
hodima_ad( 'فروش ۹۰ روز: جمع هر محصول، سفارش قدیمی‌تر از ۹۰ روز نه', [ $pr_sales[ $pr_k1 ] ?? null, $pr_sales[ $pr_k2 ] ?? null ], [ 10000000, 500000 ] );
add_filter( 'hodima_seo_advisor_use_sales', '__return_false' );
hodima_ad( 'فیلتر خاموش‌کردن فروش: نقشه خالی', hodima_seo_advisor_sales_map(), [] );
remove_filter( 'hodima_seo_advisor_use_sales', '__return_false' );
hodima_ad( 'برچسب مبلغ: میلیون یا عدد کامل', [ str_contains( hodima_seo_advisor_money_label( 14000000 ), 'میلیون' ), str_contains( hodima_seo_advisor_money_label( 500000 ), '۵۰۰' ) || str_contains( hodima_seo_advisor_money_label( 500000 ), '500' ) ], [ true, true ] );

// ترتیب اولویت: صفحه با فروش بالاتر از هم‌نمایش بی‌فروش
set_transient( HODIMA_SEO_ADVISOR_SALES_CACHE, [ '/pr-sale/' => 9000000 ], HOUR_IN_SECONDS );
$pr_page = static fn( string $path, int $imp, array $items ): array => [ 'path' => $path, 'title' => '', 'impressions' => $imp, 'items' => $items ];
$pr_mk   = static fn( string $mark, string $level = 'bad' ): array => [ 'src' => 'crawl', 'label' => $mark, 'text' => '', 'level' => $level, 'source' => 'crawl', 'mark' => $mark, 'term' => '' ];
$pr_rank = hodima_seo_advisor_priority_rank( [
	$pr_page( '/pr-nosale/', 10, [ $pr_mk( 'no_meta' ) ] ),
	$pr_page( '/pr-big/', 100, [ $pr_mk( 'no_meta' ) ] ),
	$pr_page( '/pr-sale/', 10, [ $pr_mk( 'dup_h1', 'warn' ), $pr_mk( 'no_meta' ) ] ),
] );
$pr_order = array_column( $pr_rank, 'path' );
hodima_ad( 'ترتیب اولویت: فروش بالا جلو می‌افتد، بی‌فروش هم‌نمایش عقب؛ ایرادها درون صفحه به ترتیب امتیاز؛ فروش و امتیاز روی صفحه', [ array_search( '/pr-sale/', $pr_order, true ) < array_search( '/pr-nosale/', $pr_order, true ), $pr_rank[ array_search( '/pr-sale/', $pr_order, true ) ]['items'][0]['mark'], $pr_rank[ array_search( '/pr-sale/', $pr_order, true ) ]['sales'], isset( $pr_rank[0]['score'], $pr_rank[0]['items'][0]['band'] ) ], [ true, 'no_meta', 9000000, true ] );
delete_transient( HODIMA_SEO_ADVISOR_SALES_CACHE );

// ۵ کار مهم: کار کلی (۵ صفحه یا بیشتر) یک بار؛ اعضایش جدا نمی‌آیند
$pr_pages = [];
foreach ( range( 1, 6 ) as $pr_n ) {
	$pr_pages[] = $pr_page( '/pr-bulk' . $pr_n . '/', 50, [ $pr_mk( 'no_meta' ) ] );
}
$pr_pages[] = $pr_page( '/pr-single/', 200, [ $pr_mk( 'broken' ), $pr_mk( 'title_long', 'warn' ) ] );
// صفحه «کارها»: کادر کار مهم بدون فیلتر؛ با فیلتر کد ایراد فقط همان ایراد و یادداشت «برداشتن فیلتر»
$pr_ct = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $pr_ct ) );
foreach ( range( 1, 6 ) as $pr_n ) {
	$GLOBALS['wpdb']->insert( $pr_ct, [ 'path' => '/pr-r' . $pr_n . '/', 'status' => 200, 'indexable' => 1, 'title' => 'عنوان ' . $pr_n, 'title_len' => 50, 'meta_len' => 0, 'title_px' => hodima_ad_tpx( 50 ), 'meta_px' => hodima_ad_mpx( 0 ), 'h1' => 'تیتر ' . $pr_n, 'inlinks' => 9, 'depth' => 1, 'words' => 300, 'h1_count' => 1, 'img_noalt' => 0, 'http_links' => 0, 'canonical' => '/pr-r' . $pr_n . '/' ] );
}
hodima_seo_advisor_crawl_map( true );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
$pr_ov = $cap( static fn() => hodima_seo_advisor_render_overview() );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'code' => 'no_meta' ];
$pr_ovf = $cap( static fn() => hodima_seo_advisor_render_overview() );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'code' => 'nope_x' ];
$pr_ovx = $cap( static fn() => hodima_seo_advisor_render_overview() );
$_GET  = [];
hodima_ad( 'صفحه «کارها»: کادر «۵ کار مهم امروز» دیگر نیست (SEO 3.6.2، خواست کاربر)؛ ردیف با لنگر؛ با کد ایراد فقط همان ایراد، با «برداشتن فیلتر»؛ کد ناشناخته نادیده', [ str_contains( $pr_ov, '۵ کار مهم امروز' ), str_contains( $pr_ov, 'id="' . hodima_seo_advisor_row_anchor( '/pr-r1/' ) . '"' ), str_contains( $pr_ov, 'به ترتیب اولویت' ), str_contains( $pr_ovf, '۵ کار مهم امروز' ), str_contains( $pr_ovf, 'فقط نمایش: «بدون توضیح متا»' ), str_contains( $pr_ovf, 'برداشتن فیلتر' ), str_contains( $pr_ovx, 'فقط نمایش' ) ], [ false, true, true, false, true, true, false ] );
hodima_ad( 'نشان ایراد «اطلاع» آبی است نه کهربایی (بدون متا کم‌بازدید، SEO 3.6.4)', [ str_contains( $pr_ov, 'hds-pill hds-pill--info">بدون توضیح متا' ), str_contains( $pr_ov, 'hds-pill hds-pill--warn">بدون توضیح متا' ) ], [ true, false ] );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $pr_ct ) );
hodima_seo_advisor_crawl_map( true );
wp_delete_post( $pr_p1, true );
wp_delete_post( $pr_p2, true );
$GLOBALS['wpdb']->query( "DROP TABLE IF EXISTS `{$pr_tbl}`" );
delete_transient( HODIMA_SEO_ADVISOR_SALES_CACHE );

$cx_pt = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $cx_pt ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'crawl_links' ) ) );
$GLOBALS['wpdb']->replace( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/thin/', 'kind' => 'page' ] ); // نوع صفحه از داده گوگل: فقط نوشته/برگه/محصول «متن کم» می‌گیرد
hodima_seo_advisor_crawler_start();
$cx_s         = hodima_seo_advisor_crawler_state();
$cx_s['seed'] = array_values( array_filter( $cx_s['seed'], static fn( string $item ): bool => str_starts_with( $item, 'm:' ) ) ); // نوشته‌های واقعی ابزار تست جدا
hodima_seo_advisor_crawler_save( $cx_s );
hodima_seo_advisor_crawler_tick( true );
$cx_state = hodima_seo_advisor_crawler_state();
hodima_ad( 'نقشه سایت (فهرست ← فایل ← آدرس‌ها): ۴ آدرس همین سایت؛ بیرونی و تصویر نه', [ array_keys( hodima_seo_advisor_sitemap_paths() ) ], [ [ '/', '/b/', '/in-sitemap-404/', '/orphan-sm/' ] ] );
hodima_ad( 'دور تمام شد و صفحه فقط-در-نقشه (/orphan-sm/) هم خزیده شد', [ $cx_state['status'], (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT status FROM %i WHERE path = %s', $cx_pt, '/orphan-sm/' ) ) ], [ 'idle', 200 ] );
$cx_row = (array) $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i WHERE path = %s', $cx_pt, '/' ), ARRAY_A );
hodima_ad( 'داده تازه صفحه اصلی: ۲ H1، یک تصویر بدون alt (تزئینی و دارای alt نه)، یک لینک http، canonical ندارد، متن کم', [ (int) $cx_row['h1_count'], (int) $cx_row['img_noalt'], (int) $cx_row['http_links'], $cx_row['canonical'], (int) $cx_row['words'] < 20 ], [ 2, 1, 1, '', true ] );
hodima_ad( 'canonical ذخیره می‌شود (مسیر) و شمار کلمه صفحه بلند', [ $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT canonical FROM %i WHERE path = %s', $cx_pt, '/b/' ) ), (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT words FROM %i WHERE path = %s', $cx_pt, '/b/' ) ) >= 190 ], [ '/b/', true ] );
$cx_codes = [];
$cx_items = [];
foreach ( hodima_seo_advisor_crawl_issues() as $issue_row ) {
	foreach ( $issue_row['issues'] as $issue ) {
		$cx_codes[ $issue_row['path'] ][] = $issue['code'];
		if ( ! empty( $issue['items'] ) ) {
			$cx_items[ $issue_row['path'] . '|' . $issue['code'] ] = array_column( $issue['items'], 'path' );
		}
	}
}
$cx_has = static fn( string $path, string $code ): bool => in_array( $code, $cx_codes[ $path ] ?? [], true );
hodima_ad( 'صفحه اصلی: لینک به خراب، به ریدایرکت، به ناایندکس، چند H1، تصویر بدون alt، لینک http، بدون canonical', array_map( static fn( string $c ): bool => $cx_has( '/', $c ), [ 'link_broken', 'link_redirect', 'link_noindex', 'multi_h1', 'img_alt', 'http_link', 'no_canonical' ] ), [ true, true, true, true, true, true, true ] );
hodima_ad( 'مقصدهای لینک: خراب فقط /broken/، ریدایرکت‌ها /old/ و /chain1/ (هر کدام یک بار)', [ $cx_items['/|link_broken'] ?? [], $cx_items['/|link_redirect'] ?? [] ], [ [ '/broken/' ], [ '/old/', '/chain1/' ] ] );
hodima_ad( 'زنجیره ریدایرکت: /chain1/ بله، /old/ نه', [ $cx_has( '/chain1/', 'redirect_chain' ), $cx_has( '/old/', 'redirect_chain' ) ], [ true, false ] );
hodima_ad( 'canonical به صفحه ۴۰۴ ← canonical_bad؛ صفحه ب سالم نه', [ $cx_has( '/canbad/', 'canonical_bad' ), $cx_has( '/b/', 'canonical_bad' ) ], [ true, false ] );
hodima_ad( 'نقشه سایت: آدرس ۴۰۴ داخل نقشه ← sitemap_bad؛ صفحه ایندکس‌پذیر بیرون نقشه ← not_in_sitemap؛ صفحه داخل نقشه نه', [ $cx_has( '/in-sitemap-404/', 'sitemap_bad' ), $cx_has( '/thin/', 'not_in_sitemap' ), $cx_has( '/b/', 'not_in_sitemap' ), $cx_has( '/noidx/', 'not_in_sitemap' ) ], [ true, true, false, false ] );
hodima_ad( 'متن کم فقط برای نوع شناخته‌شده (/thin/ برگه)؛ خانه و صفحه بلند نه', [ $cx_has( '/thin/', 'thin' ), $cx_has( '/', 'thin' ), $cx_has( '/b/', 'thin' ) ], [ true, false, false ] );
hodima_ad( 'صفحه عمیق: /d5/ و /d6/ (۵ و ۶ کلیک)؛ /d4/ نه', [ $cx_has( '/d5/', 'deep' ), $cx_has( '/d6/', 'deep' ), $cx_has( '/d4/', 'deep' ) ], [ true, true, false ] );
$cx_chips = HODIMA_SEO_ADVISOR_CRAWL_GROUPS;
hodima_ad( 'چیپ‌های تازه: لینک اشتباه، تصویر و محتوا، ساختار و نقشه سایت', [ in_array( 'link_broken', $cx_chips['linkfix'], true ), in_array( 'img_alt', $cx_chips['content'], true ), in_array( 'sitemap_bad', $cx_chips['tech'], true ), in_array( 'multi_h1', $cx_chips['title'], true ) ], [ true, true, true, true ] );

// خزش دوباره یک صفحه بعد از رفع: آمار لینک ورودی و عمق می‌ماند، ایراد صفحه برطرف می‌شود
$cx_inl_b = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT inlinks FROM %i WHERE path = %s', $cx_pt, '/b/' ) );
$cx_depth = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT depth FROM %i WHERE path = %s', $cx_pt, '/d3/' ) );
hodima_seo_advisor_task_set( '/', 'crawl_link_broken', '', 'لینک خراب', 'done' );
$cx_pages['/'] = $cx_page( 'خانه آزمایشی با عنوان به اندازه کافی بلند', '<h1>یک</h1><a href="/b/">ب</a> <a href="/d1/">عمق</a>', '<link rel="canonical" href="' . $cx_site . '/">' );
hodima_seo_advisor_mark_check_now( 'crawl', '/', [ [ 'rule' => 'crawl_link_broken', 'term' => '' ] ] );
$cx_after = [];
foreach ( hodima_seo_advisor_crawl_issues() as $issue_row ) {
	if ( '/' === $issue_row['path'] ) {
		$cx_after = array_column( $issue_row['issues'], 'code' );
	}
}
hodima_ad( 'بعد از رفع و «انجام دادم»: صفحه دوباره خوانده شد؛ ایرادهای لینک، H1، alt، http و canonical رفتند و کار «برطرف شد» است', [ array_intersect( [ 'link_broken', 'link_redirect', 'link_noindex', 'multi_h1', 'img_alt', 'http_link', 'no_canonical' ], $cx_after ), hodima_seo_advisor_task_state( (array) hodima_seo_advisor_task( '/', 'crawl_link_broken', '' ) ) ], [ [], 'fixed' ] );
hodima_ad( 'خزش دوباره آمار دور را نگه می‌دارد: لینک ورودی /b/ و عمق /d3/ همان قبل', [ (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT inlinks FROM %i WHERE path = %s', $cx_pt, '/b/' ) ) === $cx_inl_b, (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT depth FROM %i WHERE path = %s', $cx_pt, '/d3/' ) ) === $cx_depth ], [ true, true ] );

// دور سریع (SEO 3.3.8): فقط صفحه اصلی و پرنمایش‌ها + آدرس تازه؛ صفحه‌های شناخته‌شده دیگر خزیده و پاک نمی‌شوند؛ نقشه سایت نمی‌خزد
$cq_hits = [];
add_filter(
	'pre_http_request',
	$cq_count = static function ( mixed $pre, array $args, string $url ) use ( &$cq_hits, $cx_site ): mixed {
		if ( str_starts_with( $url, $cx_site ) ) {
			$cq_hits[] = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		}
		return $pre;
	},
	5,
	3
);
$cq_sitemap_before = get_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION );
$cq_pages_before   = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM %i', $cx_pt ) );
$cq_full_at        = time() - 3 * DAY_IN_SECONDS;
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( "UPDATE {$GLOBALS['wpdb']->posts} SET post_modified_gmt = %s", gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ) ) ); // نوشته‌های واقعی ابزار تست «ویرایش‌شده» حساب نشوند
$cx_pages['/']        .= '<a href="/cq-new/">تازه</a>';
$cx_pages['/cq-new/']  = $cx_page( 'صفحه تازه دور سریع با عنوان به اندازه کافی بلند', '<h1>تازه</h1><p>' . $cx_words( 200 ) . '</p>', '' );
$GLOBALS['wpdb']->replace( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/b/', 'kind' => 'page', 'impressions' => 700 ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => time() - 26 * HOUR_IN_SECONDS, 'full_at' => $cq_full_at, 'pages' => $cq_pages_before ], false );
for ( $cq_i = 0; $cq_i < 10 && 'running' === hodima_seo_advisor_crawler_state()['status'] || 0 === $cq_i; $cq_i++ ) {
	hodima_seo_advisor_crawler_tick( true );
}
$cq_state = hodima_seo_advisor_crawler_state();
sort( $cq_hits );
hodima_ad( 'دور سریع: فقط صفحه اصلی، پرنمایش /b/ و آدرس تازه خزیده شد؛ نقشه سایت و صفحه‌های دیگر نه', array_values( array_unique( $cq_hits ) ), [ '/', '/b/', '/cq-new/' ] );
hodima_ad(
	'بعد از دور سریع: آدرس تازه ثبت شد، هیچ صفحه‌ای پاک نشد، نقشه سایت و زمان دور کامل همان قبل، دور تمام‌شده',
	[
		(int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT status FROM %i WHERE path = %s', $cx_pt, '/cq-new/' ) ),
		(int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM %i', $cx_pt ) ) === $cq_pages_before + 1,
		get_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION ) === $cq_sitemap_before,
		$cq_state['full_at'],
		$cq_state['status'],
		$cq_state['finished'] >= time() - 60,
	],
	[ 200, true, true, $cq_full_at, 'idle', true ]
);
remove_filter( 'pre_http_request', $cq_count, 5 );
$GLOBALS['wpdb']->delete( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/b/' ] );

remove_filter( 'pre_http_request', $cx_filter, 10 );
remove_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
$GLOBALS['wpdb']->delete( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/thin/' ] );
hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS );
false === $cx_crawl ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $cx_crawl, false );

echo "=== خزش همین حالا و ۲۴ ساعته (SEO 3.1.7)\n";
$nw_at = static fn( string $when ): int => ( new DateTimeImmutable( $when, wp_timezone() ) )->getTimestamp();
$nw_old = get_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, false );
$nw_state = get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, false );
$nw_crawl = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
hodima_ad( 'گزینه «همه ساعت‌ها»: هر ساعت داخل پنجره است (حتی ظهر)', [ hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 12:00' ) ), hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 23:59' ) ), hodima_seo_advisor_crawler_settings()['always'] ], [ true, true, true ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => false, 'start' => 18, 'end' => 24, 'interval' => 7 ], false );
hodima_ad( 'پایان ۲۴:۰۰ = تا نیمه‌شب: ۲۳:۳۰ داخل، ۱۷:۵۹ و ۰۰:۳۰ بیرون', [ hodima_seo_advisor_crawler_settings()['end'], hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 23:30' ) ), hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 17:59' ) ), hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 00:30' ) ) ], [ 24, true, false, false ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => false, 'always' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
$nw_site  = untrailingslashit( home_url() );
$nw_calls = [];
add_filter(
	'pre_http_request',
	$nw_filter = static function ( mixed $pre, array $args, string $url ) use ( &$nw_calls, $nw_site ): mixed {
		if ( ! str_starts_with( $url, $nw_site ) ) {
			return $pre;
		}
		$nw_calls[] = $url;
		$path       = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( '/' === $path ) {
			return [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html><head><title>خانه</title></head><body><main><h1>خانه</h1><a href="/x/">ایکس</a></main></body></html>' ];
		}
		return [ 'response' => [ 'code' => '/x/' === $path ? 200 : 404 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html><head><title>ایکس</title></head><body><main><h1>ایکس</h1></main></body></html>' ];
	},
	10,
	3
);
add_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
hodima_ad( 'خاموش و بیرون از ساعت: نوبت عادی هیچ درخواستی نمی‌زند', [ hodima_seo_advisor_crawler_tick()['ran'], count( $nw_calls ) ], [ 0, 0 ] );
hodima_seo_advisor_crawler_start_now();
$nw_s = hodima_seo_advisor_crawler_state();
$nw_s['seed'] = []; // آزمون فقط با سایت ساختگی
hodima_seo_advisor_crawler_save( $nw_s );
hodima_ad( '«خزش همین حالا»: دور در جریان با نشان manual؛ وضعیت «running» حتی با خاموش بودن', [ $nw_s['status'], $nw_s['manual'], hodima_seo_advisor_crawler_status()['state'], hodima_seo_advisor_crawler_status_view()[3] ], [ 'running', true, 'running', true ] );
$nw_out = hodima_seo_advisor_crawler_tick();
$nw_after = hodima_seo_advisor_crawler_state();
hodima_ad( 'نوبت عادی: دور دستی بی‌توجه به ساعت و خاموشی تمام می‌شود (۲ صفحه)، نشان manual پاک و بیکار', [ $nw_out['finished'], $nw_out['ran'], $nw_after['status'], $nw_after['manual'], hodima_seo_advisor_crawler_status()['state'] ], [ true, 2, 'idle', false, 'off' ] );
hodima_seo_advisor_crawler_start_now();
$nw_p = hodima_seo_advisor_crawler_state();
hodima_ad( 'شروع دوباره وقتی دوری در جریان نیست دور تازه می‌سازد؛ زنجیره کرون زمان‌بندی شد', [ $nw_p['status'], (bool) wp_next_scheduled( HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ 0 ] ) ], [ 'running', true ] );
$nw_p['pause_until'] = time() + 600;
hodima_seo_advisor_crawler_save( $nw_p );
$nw_calls = [];
hodima_ad( 'مکث کندی سایت دور دستی را هم نگه می‌دارد', [ hodima_seo_advisor_crawler_tick()['ran'], count( $nw_calls ), hodima_seo_advisor_crawler_status()['state'] ], [ 0, 0, 'paused' ] );
remove_filter( 'pre_http_request', $nw_filter, 10 );
remove_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ 0 ] );
hodima_seo_advisor_clear_data();
false === $nw_old ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, $nw_old, false );
false === $nw_state ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, $nw_state, false );
false === $nw_crawl ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $nw_crawl, false );

echo "=== سلامت سایت: نمره (SEO 3.1.6)\n";
$sc_row = static fn( string $path, string ...$codes ): array => [ 'path' => $path, 'issues' => array_map( static fn( string $c ): array => [ 'code' => $c, 'detail' => '', 'url' => '', 'items' => [] ], $codes ) ];
hodima_ad( 'بدون صفحه خزش‌شده: نمره null', hodima_seo_advisor_score( [], 0 ), null );
$sc_clean = hodima_seo_advisor_score( [], 100 );
hodima_ad( 'بدون ایراد: همه بخش‌ها و نمره کل ۱۰۰', [ $sc_clean['score'], array_unique( array_column( $sc_clean['categories'], 'score' ) ), $sc_clean['top'] ], [ 100, [ 100 ], [] ] );
// ۱۰۰ صفحه، ۵ صفحه با یک ایراد قرمز (لینک خراب) ← بخش لینک‌ها ۱۰۰−۵×۲ = ۹۰؛ نمره کل ۱۰۰−۱۰×۰٫۳ = ۹۷
$sc_a = hodima_seo_advisor_score( array_map( static fn( int $i ): array => $sc_row( "/p{$i}/", 'server_error' ), range( 1, 5 ) ), 100 ); // SEO 3.6.7: link_broken کهربایی شد؛ ایراد قرمز = server_error (۵xx)
hodima_ad( 'پنج درصد صفحه با ایراد قرمز: بخش لینک‌ها ۹۰، بقیه ۱۰۰، نمره کل ۹۷، اول فهرست همان ایراد', [ $sc_a['categories']['cat-links']['score'], $sc_a['categories']['cat-meta']['score'], $sc_a['score'], $sc_a['top'][0]['code'], $sc_a['top'][0]['pages'], $sc_a['categories']['cat-links']['pages'] ], [ 90, 100, 97, 'server_error', 5, 5 ] );
$sc_b = hodima_seo_advisor_score( [ $sc_row( '/x/', 'img_alt', 'no_h1', 'deep' ), $sc_row( '/y/', 'img_alt' ) ], 10 ); // SEO 3.6.3: multi_h1 اطلاع شد؛ برای «کهربایی هم‌سطح» همان بخش از no_h1 استفاده می‌شود
hodima_ad( 'ایرادهای هم‌سطح: کهربایی ضریب ۱ (۲ صفحه از ۱۰ = ۲۰)؛ آبی «اطلاع» ضریب صفر (SEO 3.6.4)؛ بخش محتوا ۱۰۰−۲۰−۱۰ = ۷۰ و ساختار ۱۰۰', [ $sc_b['categories']['cat-content']['score'], $sc_b['categories']['cat-structure']['score'], $sc_b['categories']['cat-content']['pages'] ], [ 70, 100, 2 ] );
$sc_c = hodima_seo_advisor_score( array_map( static fn( int $i ): array => $sc_row( "/z{$i}/", 'link_broken', 'broken', 'no_meta' ), range( 1, 10 ) ), 10 );
hodima_ad( 'همه صفحه‌ها ایراد دارند: کسر هر ایراد حداکثر ۲۵ (SEO 3.6.4)؛ دو ایراد فراگیر در بخش لینک‌ها = ۵۰؛ نمره کل بین ۰ و ۱۰۰', [ $sc_c['categories']['cat-links']['score'], $sc_c['score'] >= 0 && $sc_c['score'] <= 100, count( $sc_c['top'] ) ], [ 50, true, 3 ] );
hodima_ad( 'برچسب نمره: ۹۵ عالی، ۸۰ خوب، ۶۰ نیاز به کار (کهربایی)، ۴۰ ضعیف (قرمز)', [ hodima_seo_advisor_score_grade( 95 ), hodima_seo_advisor_score_grade( 80 ), hodima_seo_advisor_score_grade( 60 ), hodima_seo_advisor_score_grade( 40 ) ], [ [ 'label' => 'عالی', 'level' => 'ok' ], [ 'label' => 'خوب', 'level' => 'ok' ], [ 'label' => 'نیاز به کار', 'level' => 'warn' ], [ 'label' => 'ضعیف', 'level' => 'bad' ] ] );
hodima_ad( 'وزن بخش‌ها ۱۰۰ و هر کد ایراد فقط در یک بخش', [ array_sum( array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'weight' ) ), count( array_merge( ...array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'codes' ) ) ) === count( array_unique( array_merge( ...array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'codes' ) ) ) ) ], [ 100, true ] );
$sc_all_codes = array_merge( ...array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'codes' ) );
$sc_issue_codes = array_merge( ...array_values( HODIMA_SEO_ADVISOR_CRAWL_GROUPS ) );
hodima_ad( 'هر کد ایراد خزش در یکی از بخش‌های نمره است (ایرادی بی‌نمره نمی‌ماند؛ جز img_empty «بررسی کنید» که عمدا بی‌نمره است و از SEO 3.8.1 در گروه چیپ «تصویر و محتوا» هم هست)', array_values( array_diff( $sc_issue_codes, $sc_all_codes, [ 'img_empty' ] ) ), [] );
hodima_ad( 'فیلتر جدول: گروه چیپ و بخش نمره شناخته می‌شوند، ناشناس نه', [ hodima_seo_advisor_score_filter_codes( 'linkfix' ), in_array( 'slow', hodima_seo_advisor_score_filter_codes( 'cat-speed' ), true ), hodima_seo_advisor_score_filter_codes( 'nope' ) ], [ [ 'link_broken', 'link_redirect', 'link_noindex', 'link_no_text', 'link_js', 'redirect_chain', 'redirect_loop' ], true, [] ] );

$sc_old = get_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, false );
$sc_crawl = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_SCORE_OPTION );
hodima_seo_advisor_ensure_tables();
$sc_pt = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $sc_pt ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'crawl_links' ) ) );
foreach ( range( 1, 4 ) as $i ) {
	$GLOBALS['wpdb']->insert( $sc_pt, [ 'path' => "/sc{$i}/", 'status' => 200, 'indexable' => 1, 'title' => "عنوان {$i} با طول کافی برای آزمون", 'title_len' => 40, 'meta_len' => 100, 'title_px' => hodima_ad_tpx( 40 ), 'meta_px' => hodima_ad_mpx( 100 ), 'h1' => "تیتر {$i}", 'inlinks' => 5, 'text_links' => 3, 'depth' => 1 ] );
}
$GLOBALS['wpdb']->update( $sc_pt, [ 'title' => '', 'title_len' => 0, 'title_px' => 0 ], [ 'path' => '/sc1/' ] ); // یک صفحه بدون عنوان (قرمز؛ «بدون متا» کم‌بازدید از SEO 3.6.2 اطلاع است و در نمره نمی‌آید)
hodima_seo_advisor_crawl_map( true ); // خزنده واقعی بعد از هر نوشتن همین را می‌زند (کش ایرادها، SEO 3.4.3)
hodima_seo_advisor_score_record();
$GLOBALS['wpdb']->update( $sc_pt, [ 'title' => 'عنوان 1 با طول کافی برای آزمون', 'title_len' => 40, 'title_px' => hodima_ad_tpx( 40 ) ], [ 'path' => '/sc1/' ] ); // رفع شد
hodima_seo_advisor_crawl_map( true );
hodima_seo_advisor_score_record();
$sc_hist = hodima_seo_advisor_score_history();
hodima_ad( 'تاریخچه: دو دور ثبت شد؛ دور اول با ایراد «بدون عنوان» کمتر از دور دوم (بدون ایراد ۱۰۰)', [ count( $sc_hist ), $sc_hist[0]['score'] < $sc_hist[1]['score'], $sc_hist[1]['score'] ], [ 2, true, 100 ] );
update_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, array_fill( 0, 20, [ 'time' => time(), 'score' => 50 ] ), false );
hodima_seo_advisor_score_record();
hodima_ad( 'تاریخچه نهایتا ۱۲ دور', count( hodima_seo_advisor_score_history() ), 12 );

// نمای کارت در تب ایرادهای فنی
$GLOBALS['wpdb']->update( $sc_pt, [ 'meta_len' => 0, 'meta_px' => 0 ], [ 'path' => '/sc2/' ] );
update_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, [ [ 'time' => time() - 7 * DAY_IN_SECONDS, 'score' => 80 ], [ 'time' => time() - DAY_IN_SECONDS, 'score' => 90 ] ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time() - 3600, 'pages' => 4, 'links' => 0, 'files' => [], 'source' => 'crawler' ], false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_store( [ '/sc1/' => [ 'clicks' => 5, 'impressions' => 2000, 'position' => 6.0 ] ], [], [] );
update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $key_json, false );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
$sc_html = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'نوار سلامت سایت بالای «کارها» (SEO 3.5.8؛ عنوان «سلامت فنی سایت» از 3.9.9): عنوان، نمره با برچسب، ۵ بخش با پیوند فیلتر، بدون «سه کار اول» (تکرار ۵ کار مهم امروز)، «نمره چطور حساب می‌شود؟»', [ str_contains( $sc_html, '>سلامت فنی سایت<' ), 1 === substr_count( $sc_html, 'hodima-advisor__sc-gauge ' ), substr_count( $sc_html, 'class="hodima-advisor__sc-cat"' ), str_contains( $sc_html, 'cf=cat-meta' ), str_contains( $sc_html, 'سه کار اول برای بالا بردن نمره' ), str_contains( $sc_html, 'نمره چطور حساب می‌شود؟' ) ], [ true, true, 5, true, false, true ] );
hodima_ad( 'نمره نسبت به دور قبل: «بدتر» با اختلاف', str_contains( $sc_html, 'امتیاز بدتر از دور قبل' ) || str_contains( $sc_html, 'امتیاز بهتر از دور قبل' ) || str_contains( $sc_html, 'بدون تغییر نسبت به دور قبل' ), true );
$_GET    = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'cf' => 'cat-meta' ];
$sc_meta = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'فیلتر بخش «عنوان و متا»: «فیلتر فعال»، بخش فعال در نوار سلامت (aria-current)، ردیف /sc2/ با ایراد متا دیده می‌شود', [ str_contains( $sc_meta, 'فیلتر فعال: «عنوان و متا»' ), str_contains( $sc_meta, '/sc2/' ), 1 === preg_match( '#class="hodima-advisor__sc-cat" href="[^"]*"\s+aria-current="true"#u', $sc_meta ) ], [ true, true, true ] );
$_GET = [];
wp_set_current_user( 0 );
hodima_ad( 'پاک کردن داده‌های دستار تاریخچه نمره را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), get_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, 'none' ) ], [ true, 'none' ] );
false === $sc_old ? delete_option( HODIMA_SEO_ADVISOR_SCORE_OPTION ) : update_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, $sc_old, false );
false === $sc_crawl ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $sc_crawl, false );
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );

echo "=== ادغام تب‌ها (SEO 3.1.3)\n";
hodima_ad( 'کلید تب‌ها (هشت بخش قدیمی و «همه ایرادها»)؛ تب‌های حذف‌شده نیستند', [ hodima_seo_advisor_tab_keys(), array_intersect( [ 'done', 'breakdown', 'trend' ], hodima_seo_advisor_tab_keys() ) ], [ [ 'opportunities', 'overview', 'competition', 'terms', 'health', 'crawl', 'indexing', 'tasks', 'jobs', 'settings' ], [] ] );
hodima_ad( 'پیوند قدیمی: done ← کارها (منتظر نتیجه)، breakdown و trend ← آمار جستجو', [ HODIMA_SEO_ADVISOR_LEGACY_TABS['done'], HODIMA_SEO_ADVISOR_LEGACY_TABS['breakdown'][0], HODIMA_SEO_ADVISOR_LEGACY_TABS['trend'][0] ], [ [ 'tasks', [ 'tstatus' => 'waiting' ] ], 'terms', 'terms' ] );
hodima_ad( 'ریدایرکت پیوند قدیمی به هوک منو وصل است', function_exists( 'hodima_seo_advisor_legacy_tab_redirect' ), true );
$_GET = [];
wp_set_current_user( 0 );

echo "=== سقف صفحه‌های خزنده (SEO 3.1.8)\n";
$cp_t = hodima_seo_advisor_table( 'crawl_pages' );
$cp_w = $GLOBALS['wpdb'];
$cp_w->query( $cp_w->prepare( 'DELETE FROM %i', $cp_t ) );
foreach ( [ '/', '/a/', '/b/', '/c/' ] as $cp_path ) {
	$cp_w->insert( $cp_t, [ 'path' => $cp_path, 'status' => 200, 'indexable' => 1 ] );
}
$cp_full = [ 'seen' => array_fill( 0, HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES, 1 ), 'queue' => [] ];
hodima_seo_advisor_crawler_enqueue( $cp_full, home_url( '/zzz/' ) );
hodima_ad( 'سقف پر: صفحه تازه به صف نمی‌رود ولی نشان «سقف» خورد', [ $cp_full['queue'], ! empty( $cp_full['capped'] ) ], [ [], true ] );
$cp_ok = [ 'seen' => [ '/' => 1 ], 'queue' => [] ];
hodima_seo_advisor_crawler_enqueue( $cp_ok, home_url( '/b/' ) );
hodima_ad( 'سقف پر نیست: صفحه به صف می‌رود و نشان سقف نمی‌خورد', [ count( $cp_ok['queue'] ), ! empty( $cp_ok['capped'] ) ], [ 1, false ] );

$cp_cap = [ 'seen' => [ '/' => 1, '/a/' => 1 ], 'queue' => [], 'sitemap' => [], 'capped' => true ];
hodima_seo_advisor_crawler_finalize( $cp_cap );
hodima_seo_advisor_crawler_save( $cp_cap ); // نوبت خزنده بعد از finalize ذخیره می‌کند
hodima_ad( 'دور سقف‌خورده: هیچ ردیفی پاک نمی‌شود', $cp_w->get_col( $cp_w->prepare( 'SELECT path FROM %i ORDER BY path', $cp_t ) ), [ '/', '/a/', '/b/', '/c/' ] );
hodima_ad( 'نشان سقف بعد از پایان دور می‌ماند و وضعیت صفحه هشدار می‌دهد', [ hodima_seo_advisor_crawler_state()['capped'], hodima_seo_advisor_crawler_status()['capped'], hodima_seo_advisor_crawler_status_view()[0] ], [ true, true, 'warn' ] );

$cp_norm = [ 'seen' => [ '/' => 1, '/a/' => 1 ], 'queue' => [], 'sitemap' => [] ];
hodima_seo_advisor_crawler_finalize( $cp_norm );
hodima_seo_advisor_crawler_save( $cp_norm );
hodima_ad( 'دور عادی (بدون سقف): صفحه دیده‌نشده مثل قبل پاک می‌شود', $cp_w->get_col( $cp_w->prepare( 'SELECT path FROM %i ORDER BY path', $cp_t ) ), [ '/', '/a/' ] );
hodima_ad( 'دور عادی نشان سقف را پاک می‌کند', [ hodima_seo_advisor_crawler_state()['capped'], hodima_seo_advisor_crawler_status_view()[0] ], [ false, 'ok' ] );
hodima_seo_advisor_crawler_start();
hodima_ad( 'شروع دور تازه نشان سقف را صفر می‌کند', hodima_seo_advisor_crawler_state()['capped'], false );
$cp_w->query( $cp_w->prepare( 'DELETE FROM %i', $cp_t ) );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );

echo "=== سلامت کارهای خودکار (SEO 3.2.0)\n";
$ch_keep = [];
foreach ( [ HODIMA_SEO_ADVISOR_STATUS_OPTION, HODIMA_SEO_ADVISOR_CRAWLER_OPTION, HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, HODIMA_SEO_ADVISOR_PIPELINE_OPTION, HODIMA_SEO_ADVISOR_RUNS_OPTION ] as $ch_opt ) {
	$ch_keep[ $ch_opt ] = get_option( $ch_opt, null );
}
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION ); // اجرای ثبت‌شده آزمون‌های پیشین نباید سنجش «بر پایه رویداد» را بپوشاند
$ch_now    = time();
$ch_clear  = static function (): void {
	foreach ( [ HODIMA_SEO_ADVISOR_CRON, HODIMA_SEO_ADVISOR_INSPECT_CRON, HODIMA_SEO_ADVISOR_STAGE_CRON, HODIMA_SEO_ADVISOR_CRAWLER_CRON ] as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
};
$ch_job    = static function ( string $key ) use ( $ch_now ): array {
	foreach ( hodima_seo_advisor_cron_health( $ch_now )['jobs'] as $job ) {
		if ( $key === $job['key'] ) {
			return $job;
		}
	}
	return [];
};
$ch_clear();
update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, [], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => $ch_now - 3 * DAY_IN_SECONDS ], false );

// دریافت روزانه
$ch_status = (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] );
$ch_levels = [];
foreach ( [ 10, 60, 100 ] as $ch_hours ) {
	update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => $ch_now - $ch_hours * HOUR_IN_SECONDS ] + $ch_status, false );
	$ch_levels[] = $ch_job( 'refresh' )['level'];
}
hodima_ad( 'دریافت روزانه: ۱۰ ساعت سالم، ۶۰ ساعت دیر شده، ۱۰۰ ساعت متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );
hodima_ad( 'متن «آخرین اجرا» فارسی و نسبی است', str_contains( $ch_job( 'refresh' )['last'], 'پیش' ), true );

// خزنده: بیکار
$ch_levels = [];
foreach ( [ 3, 12, 20 ] as $ch_days ) {
	update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => $ch_now - $ch_days * DAY_IN_SECONDS ], false );
	$ch_levels[] = $ch_job( 'crawler' )['level'];
}
hodima_ad( 'خزنده بیکار با دور هفت‌روزه: ۳ روز سالم، ۱۲ روز دیر شده، ۲۰ روز متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );

// خزنده: دور باز (پنجره ۲ تا ۸ ← ۲۴ ساعت مجاز)
$ch_levels = [];
foreach ( [ 5, 30, 60 ] as $ch_hours ) {
	update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'running', 'started' => $ch_now - 70 * HOUR_IN_SECONDS, 'tick' => $ch_now - $ch_hours * HOUR_IN_SECONDS ], false );
	$ch_levels[] = $ch_job( 'crawler' )['level'];
}
hodima_ad( 'دور باز با پنجره شبانه: نوبت ۵ ساعت پیش سالم (شبانه عادی است)، ۳۰ ساعت دیر شده، ۶۰ ساعت متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'running', 'tick' => $ch_now - 3 * HOUR_IN_SECONDS ], false );
hodima_ad( 'خزنده ۲۴ ساعته: نوبت ۳ ساعت پیش دیر شده است', $ch_job( 'crawler' )['level'], 'warn' );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => false, 'always' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => $ch_now - 60 * DAY_IN_SECONDS ], false );
hodima_ad( 'خزش خودکار خاموش: «غیرفعال» و در شمار مشکل‌ها نیست', [ $ch_job( 'crawler' )['level'], hodima_seo_advisor_cron_health( $ch_now )['level'] ], [ 'off', 'bad' ] ); // دریافت ۱۰۰ ساعته هنوز متوقف است
$ch_crawler_off = array_filter( hodima_seo_advisor_cron_health( $ch_now )['jobs'], static fn( array $job ): bool => 'crawler' === $job['key'] && 'off' === $job['level'] );
hodima_ad( 'کار خاموش مشکل شمرده نمی‌شود', count( $ch_crawler_off ), 1 );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => $ch_now - DAY_IN_SECONDS ], false );

// بررسی ایندکس: رویداد دو بار در روز که از موعد گذشته
$ch_levels = [];
foreach ( [ 3, 20, 70 ] as $ch_hours ) {
	wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
	wp_schedule_event( $ch_now - $ch_hours * HOUR_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
	$ch_levels[] = $ch_job( 'inspect' )['level'];
}
hodima_ad( 'بررسی ایندکس: ۳ ساعت از موعد سالم، ۲۰ ساعت دیر شده، ۷۰ ساعت متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
wp_schedule_event( $ch_now + HOUR_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
hodima_ad( 'بررسی ایندکس طبق برنامه سالم است', [ $ch_job( 'inspect' )['level'], $ch_job( 'inspect' )['last'] ], [ 'ok', 'هنوز اجرایی ثبت نشده؛ طبق برنامه' ] );

// ساخت پیشنهادها
hodima_ad( 'نوبت پس‌زمینه‌ای که نیست: بی‌کار و سالم', [ $ch_job( 'pipeline' )['level'], $ch_job( 'pipeline' )['last'] ], [ 'ok', 'بی‌کار' ] );
update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, [ 'stages' => [ 'trend' ] ], false );
$ch_levels = [];
foreach ( [ 10 * MINUTE_IN_SECONDS, 2 * HOUR_IN_SECONDS, 10 * HOUR_IN_SECONDS ] as $ch_late ) {
	wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
	wp_schedule_single_event( $ch_now - $ch_late, HODIMA_SEO_ADVISOR_STAGE_CRON );
	$ch_levels[] = $ch_job( 'pipeline' )['level'];
}
hodima_ad( 'نوبت ثبت‌شده که اجرا نشده: ۱۰ دقیقه سالم، ۲ ساعت دیر شده، ۱۰ ساعت متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );

// زمان آخرین اجرای واقعی (۳.۲.۶): دکمه دستی هم در کارت دیده می‌شود
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
wp_schedule_event( $ch_now - 5 * DAY_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
hodima_ad( 'بدون اجرای ثبت‌شده: رویداد ۵ روز از موعد گذشته ← متوقف با متن «اجرایی ثبت نشده؛ رویداد …»', [ $ch_job( 'inspect' )['level'], str_contains( $ch_job( 'inspect' )['last'], 'اجرایی ثبت نشده' ) ], [ 'bad', true ] );
update_option( HODIMA_SEO_ADVISOR_RUNS_OPTION, [ 'inspect' => $ch_now - 2 * HOUR_IN_SECONDS ], false );
hodima_ad( 'اجرای دستی ثبت‌شده (۲ ساعت پیش) حتی با رویداد ۵ روز عقب: سالم و «آخرین اجرا … پیش»', [ $ch_job( 'inspect' )['level'], str_starts_with( $ch_job( 'inspect' )['last'], 'آخرین اجرا' ) ], [ 'ok', true ] );
update_option( HODIMA_SEO_ADVISOR_RUNS_OPTION, [ 'inspect' => $ch_now - 40 * HOUR_IN_SECONDS ], false );
hodima_ad( 'آخرین اجرا ۴۰ ساعت پیش: دیر شده؛ ۸۰ ساعت: متوقف', [ $ch_job( 'inspect' )['level'], ( static function () use ( $ch_now, $ch_job ): string { update_option( HODIMA_SEO_ADVISOR_RUNS_OPTION, [ 'inspect' => $ch_now - 80 * HOUR_IN_SECONDS ], false ); return $ch_job( 'inspect' )['level']; } )() ], [ 'warn', 'bad' ] );
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION );
hodima_seo_advisor_run_mark( 'inspect' );
hodima_seo_advisor_run_mark( 'pipeline' );
hodima_ad( 'run_mark زمان را می‌نویسد و run_time می‌خواند (جدا برای هر کار)', [ hodima_seo_advisor_run_time( 'inspect' ) >= $ch_now, hodima_seo_advisor_run_time( 'pipeline' ) >= $ch_now, hodima_seo_advisor_run_time( 'nope' ) ], [ true, true, 0 ] );
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION );

// اجرای واقعی نوبت‌ها و بررسی ایندکس خودشان زمان را ثبت می‌کنند
update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, [ 'stages' => [ 'ctr' ], 'property' => 'sc-domain:x', 'range' => [], 'days' => 90 ], false );
hodima_seo_advisor_pipeline_run( 5.0 );
hodima_ad( 'اجرای نوبت‌ها (pipeline_run) زمان آخرین اجرا را ثبت می‌کند', hodima_seo_advisor_run_time( 'pipeline' ) >= $ch_now, true );
$ch_inspect_block = static fn( $pre ) => new WP_Error( 'http_request_failed', 'آزمون' );
add_filter( 'pre_http_request', $ch_inspect_block, 5 );
hodima_seo_advisor_inspect_run( 'sc-domain:x', 1, [ 'checked' => 0, 'error' => '', 'quota' => false ] );
remove_filter( 'pre_http_request', $ch_inspect_block, 5 );
hodima_ad( 'بررسی ایندکس (inspect_run) حتی با خطای شبکه همان آدرس زمان آخرین اجرا را ثبت می‌کند', hodima_seo_advisor_run_time( 'inspect' ) >= $ch_now, true );
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION );

// دکمه «گرفتن داده تازه» وقتی رویداد عقب است «در پس‌زمینه شروع شد» نمی‌گوید و همان‌جا چند آدرس را بررسی می‌کند
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
wp_schedule_event( $ch_now - 5 * DAY_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
hodima_ad( 'کرون‌ایستاده تشخیص داده می‌شود (رویداد ۵ روز از موعد گذشته)', hodima_seo_advisor_cron_stalled( $ch_now ), true );
add_filter( 'pre_http_request', $ch_inspect_block, 5 );
$ch_extras = hodima_seo_advisor_refresh_extras();
remove_filter( 'pre_http_request', $ch_inspect_block, 5 );
if ( hodima_seo_advisor_inspect_pending() > 0 ) {
	hodima_ad( 'refresh_extras با کرون ایستاده: پیام «کرون سایت اجرا نمی‌کند»، نه «در پس‌زمینه شروع شد»؛ و اجرا ثبت شد', [ str_contains( implode( ' ', $ch_extras ), 'کرون سایت اجرا نمی‌کند' ), str_contains( implode( ' ', $ch_extras ), 'در پس‌زمینه شروع شد' ), hodima_seo_advisor_run_time( 'inspect' ) >= $ch_now ], [ true, false, true ] );
}
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
wp_schedule_event( $ch_now + HOUR_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
hodima_ad( 'رویداد طبق برنامه: کرون ایستاده نیست', hodima_seo_advisor_cron_stalled( $ch_now ), false );

// جمع‌بندی و نمایش
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => $ch_now - 100 * HOUR_IN_SECONDS ] + $ch_status, false );
$ch_health = hodima_seo_advisor_cron_health( $ch_now );
hodima_ad( 'جمع‌بندی: دریافت متوقف ← سطح کل متوقف و یک مشکل', [ $ch_health['level'], $ch_health['problems'] ], [ 'bad', 1 ] );
$ch_notice = static function ( string $tab ): string {
	ob_start();
	hodima_seo_advisor_cron_notice( $tab );
	return (string) ob_get_clean();
};
$ch_html = $ch_notice( 'opportunities' );
hodima_ad( 'نوار هشدار روی «پیشنهادها» با نام کار، زمان و پیوند به تب «خزش و داده»', [ str_contains( $ch_html, 'کارهای خودکار دستار عقب‌اند' ), str_contains( $ch_html, 'دریافت داده گوگل' ), str_contains( $ch_html, 'tab=jobs#hodima-advisor-cron' ), str_contains( $ch_html, 'notice-error' ) ], [ true, true, true, true ] );
hodima_ad( 'نوار هشدار روی «کارها» (قبلا «ایرادهای فنی»؛ SEO 3.5.8) هم هست و روی تب‌های دیگر نیست', [ '' !== $ch_notice( 'overview' ), $ch_notice( 'terms' ), $ch_notice( 'tasks' ), $ch_notice( 'settings' ) ], [ true, '', '', '' ] );
ob_start();
hodima_seo_advisor_render_jobs();
$ch_card = (string) ob_get_clean();
hodima_ad( 'تب «خزش و داده» در حالت مشکل: شناسه پیوند، نوار وضعیت، چهار کارت، راهنمای باز با دستور و دکمه کپی', [ str_contains( $ch_card, 'id="hodima-advisor-cron"' ), str_contains( $ch_card, '1 کار از 4 کار خودکار عقب است' ) || str_contains( $ch_card, '۱ کار از ۴ کار خودکار عقب است' ), substr_count( $ch_card, 'class="hodima-advisor__job"' ), str_contains( $ch_card, 'wp-cron.php?doing_wp_cron' ), str_contains( $ch_card, 'data-hdsa-copy="hodima-advisor-cron-cmd"' ), str_contains( $ch_card, '<details class="hds-more hodima-advisor__cron-guide" open>' ) ], [ true, true, 4, true, true, true ] );
hodima_ad( 'کارت‌ها به ترتیب: دریافت داده، خزنده، بررسی ایندکس، ساخت پیشنهادها؛ هر کارت وضعیت و تنظیم خودش', [ strpos( $ch_card, 'دریافت داده گوگل' ) < strpos( $ch_card, 'خزنده داخلی' ), strpos( $ch_card, 'خزنده داخلی' ) < strpos( $ch_card, 'بررسی ایندکس گوگل' ), strpos( $ch_card, 'بررسی ایندکس گوگل' ) < strpos( $ch_card, 'ساخت پیشنهادها' ), substr_count( $ch_card, 'data-level="warn"' ) + substr_count( $ch_card, 'data-level="bad"' ) >= 1, substr_count( $ch_card, 'class="hodima-advisor__job-set"' ) ], [ true, true, true, true, 4 ] ); // هر ردیف پنل تنظیم خودش را دارد (SEO 3.4.8)
hodima_ad( 'دکمه «اجرا همین حالا»: هر چهار ردیف فرم خودش (دریافت داده با بازه، خزنده، ایندکس، پیشنهادها) و بازگشت به «خزش و داده»؛ زمان‌بندی خزش در ردیف خزنده', [ str_contains( $ch_card, 'name="action" value="hodima_advisor_refresh"' ), str_contains( $ch_card, 'name="days"' ), str_contains( $ch_card, 'name="tab" value="jobs"' ), str_contains( $ch_card, 'name="action" value="hodima_advisor_crawler_now"' ), substr_count( $ch_card, 'class="hodima-advisor__cron-run"' ), str_contains( $ch_card, 'name="action" value="hodima_advisor_crawler_settings"' ), str_contains( $ch_card, 'name="interval"' ) ], [ true, true, true, true, 4, true, true ] );
hodima_ad( 'فرم‌های دکمه برای چهار کار به چهار کنش دستی موجود می‌روند', array_map( static fn( string $key ): bool => 1 === preg_match( '#name="action" value="(hodima_advisor_(refresh|inspect_now|crawler_now|pipeline_now))"#', hodima_seo_advisor_cron_run_form( $key ) ), [ 'refresh', 'inspect', 'crawler', 'pipeline' ] ), [ true, true, true, true ] );
hodima_ad( 'کار ناشناخته فرم نمی‌سازد', hodima_seo_advisor_cron_run_form( 'nope' ), '' );

// همه سالم: هیچ نواری و کارت یک‌خط با جزئیات جمع‌شده
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => $ch_now - 5 * HOUR_IN_SECONDS ] + $ch_status, false );
hodima_ad( 'همه سالم: سطح ok و هیچ نواری نمی‌آید', [ hodima_seo_advisor_cron_health( $ch_now )['level'], $ch_notice( 'opportunities' ) ], [ 'ok', '' ] );
ob_start();
hodima_seo_advisor_render_jobs();
$ch_card = (string) ob_get_clean();
hodima_ad( 'تب سالم: یک خط وضعیت، چهار کارت، بدون راهنمای رفع', [ str_contains( $ch_card, 'خودکار به موقع اجرا شده‌اند' ), substr_count( $ch_card, 'class="hodima-advisor__job"' ), str_contains( $ch_card, 'چطور درستش کنم' ) ], [ true, 4, false ] );

// ذخیره وضعیت خزنده زمان آخرین نوبت را می‌نویسد
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle' ], false );
$ch_state = hodima_seo_advisor_crawler_state();
hodima_seo_advisor_crawler_save( $ch_state );
hodima_ad( 'ذخیره وضعیت خزنده زمان آخرین نوبت (tick) را می‌نویسد', hodima_seo_advisor_crawler_state()['tick'] >= $ch_now, true );
hodima_ad( 'دستور کرون واقعی با آدرس همین سایت', str_starts_with( hodima_seo_advisor_cron_command(), '*/5 * * * * wget -q -O - ' ) && str_contains( hodima_seo_advisor_cron_command(), 'wp-cron.php?doing_wp_cron' ), true );

$ch_clear();
foreach ( $ch_keep as $ch_opt => $ch_val ) {
	null === $ch_val ? delete_option( $ch_opt ) : update_option( $ch_opt, $ch_val, false );
}

echo "=== کیفیت داده (SEO 3.2.1)\n";
$qd_keep = [];
foreach ( [ HODIMA_SEO_ADVISOR_STATUS_OPTION, HODIMA_SEO_ADVISOR_CAPS_OPTION, HODIMA_SEO_ADVISOR_CRAWL_OPTION, HODIMA_SEO_ADVISOR_SITEMAP_OPTION, HODIMA_SEO_ADVISOR_CRAWLER_OPTION ] as $qd_opt ) {
	$qd_keep[ $qd_opt ] = get_option( $qd_opt, null );
}
$qd_status = (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] );
$qd_item   = static function ( string $key ): array {
	foreach ( hodima_seo_advisor_quality()['items'] as $item ) {
		if ( $key === $item['key'] ) {
			return $item;
		}
	}
	return [];
};
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - HOUR_IN_SECONDS, 'end' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ), 'days' => 90, 'error' => '' ] + $qd_status, false );
delete_option( HODIMA_SEO_ADVISOR_CAPS_OPTION );
hodima_ad( 'داده گوگل: دریافت سالم بدون سقف و خطا ← کامل با «تا … پیش، ۹۰ روز»', [ $qd_item( 'google' )['level'], str_contains( $qd_item( 'google' )['value'], 'پیش' ) ], [ 'ok', true ] );
hodima_seo_advisor_caps( [ 'fetch' => true ] );
hodima_ad( 'داده گوگل: سقف ردیف رسیده ← ناقص با راه‌حل «بازه کوتاه‌تر»', [ $qd_item( 'google' )['level'], str_contains( $qd_item( 'google' )['note'], 'بازه را کوتاه‌تر' ) ], [ 'warn', true ] );
hodima_seo_advisor_caps( [ 'fetch' => false ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'error' => 'خطای آزمون' ] + (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] ), false );
hodima_ad( 'داده گوگل: آخرین دریافت ناموفق ← ناقص', [ $qd_item( 'google' )['level'], str_contains( $qd_item( 'google' )['note'], 'ناموفق' ) ], [ 'warn', true ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => 0, 'error' => '' ] + (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] ), false );
hodima_ad( 'داده گوگل: هنوز دریافتی نشده ← نامعلوم (مشکل شمرده نمی‌شود)', [ $qd_item( 'google' )['level'], hodima_seo_advisor_quality()['issues'] ], [ 'unknown', 0 ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - HOUR_IN_SECONDS, 'end' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ), 'days' => 90, 'error' => '' ] + $qd_status, false );

// پوشش تحلیل (به یک ردیف آدرس نیاز دارد؛ جدول در این نقطه خالی است)
$qd_pt = hodima_seo_advisor_table( 'pages' );
$qd_keep_pages = (array) $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i', $qd_pt ), ARRAY_A );
$GLOBALS['wpdb']->insert( $qd_pt, [ 'path' => '/qd-page/', 'clicks' => 1, 'impressions' => 2000, 'advice' => '' ] );
hodima_ad( 'پوشش تحلیل بدون سقف: کامل و «همه … آدرس»', [ $qd_item( 'analysis' )['level'], str_starts_with( $qd_item( 'analysis' )['value'], 'همه' ) ], [ 'ok', true ] );
hodima_seo_advisor_caps( [ 'analyze' => true ] );
hodima_ad( 'پوشش تحلیل: سقف تحلیل ← ناقص «N از M آدرس» با راه‌حل حداقل نمایش', [ $qd_item( 'analysis' )['level'], str_contains( $qd_item( 'analysis' )['value'], ' از ' ), str_contains( $qd_item( 'analysis' )['note'], 'حداقل نمایش' ) ], [ 'warn', true, true ] );
hodima_seo_advisor_caps( [ 'analyze' => false, 'classify' => 300 ] );
hodima_ad( 'پوشش تحلیل: آدرس‌های بی‌نوع ← ناقص با شمار', [ $qd_item( 'analysis' )['level'], str_contains( $qd_item( 'analysis' )['note'], '300' ) || str_contains( $qd_item( 'analysis' )['note'], '۳۰۰' ) ], [ 'warn', true ] );
hodima_seo_advisor_caps( [ 'classify' => 0 ] );

$GLOBALS['wpdb']->delete( $qd_pt, [ 'path' => '/qd-page/' ] );
// پوشش خزش
$qd_t = hodima_seo_advisor_table( 'crawl_pages' );
$qd_w = $GLOBALS['wpdb'];
$qd_w->query( $qd_w->prepare( 'DELETE FROM %i', $qd_t ) );
foreach ( [ '/q1/', '/q2/', '/q3/' ] as $qd_path ) {
	$qd_w->insert( $qd_t, [ 'path' => $qd_path, 'status' => 200, 'indexable' => 1 ] );
}
hodima_seo_advisor_crawl_map( true );
delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION );
delete_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle' ], false );
hodima_ad( 'پوشش خزش: هنوز خزشی نشده ← نامعلوم', $qd_item( 'crawl' )['level'], 'unknown' );
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => 3, 'links' => 0, 'files' => [], 'source' => 'crawler' ], false );
hodima_ad( 'پوشش خزش: نقشه سایت خوانده نشده ← نامعلوم (نه ناقص)', [ $qd_item( 'crawl' )['level'], str_contains( $qd_item( 'crawl' )['value'], 'نقشه سایت خوانده نشده' ) ], [ 'unknown', true ] );
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => [ '/q1/', '/q2/', '/q3/' ] ], false );
hodima_ad( 'پوشش خزش: همه آدرس‌های نقشه سایت خزیده ← کامل «۳ از ۳»', [ $qd_item( 'crawl' )['level'], str_contains( $qd_item( 'crawl' )['value'], hodima_seo_advisor_num( 3 ) . ' از ' . hodima_seo_advisor_num( 3 ) ) ], [ 'ok', true ] );
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => [ '/q1/', '/n1/', '/n2/', '/n3/' ] ], false );
hodima_ad( 'پوشش خزش: ۱ از ۴ آدرس نقشه سایت (زیر ۹۰٪) ← ناقص با «خزش همین حالا»', [ $qd_item( 'crawl' )['level'], str_contains( $qd_item( 'crawl' )['note'], 'خزش همین حالا' ) ], [ 'warn', true ] );
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => [ '/q1/', '/q2/', '/q3/' ] ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'capped' => true ], false );
hodima_ad( 'پوشش خزش: دور سقف‌خورده ← ناقص حتی با نقشه کامل', [ $qd_item( 'crawl' )['level'], str_contains( $qd_item( 'crawl' )['value'], 'سقف' ) ], [ 'warn', true ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle' ], false );

// جمع‌بندی و نمایش
$qd_q = hodima_seo_advisor_quality();
hodima_ad( 'جمع‌بندی همه کامل: سطح ok و صفر مورد', [ $qd_q['level'], $qd_q['issues'] ], [ 'ok', 0 ] );
$qd_render = static function ( string $tab ): string {
	ob_start();
	hodima_seo_advisor_render_quality( $tab );
	return (string) ob_get_clean();
};
$qd_ok = $qd_render( 'opportunities' );
hodima_ad( 'نوار کامل: عنوان «کیفیت داده»، نشان «کامل»، بدون فهرست توضیح', [ str_contains( $qd_ok, 'کیفیت داده' ), str_contains( $qd_ok, '>کامل<' ), str_contains( $qd_ok, 'hodima-advisor__quality-notes' ) ], [ true, true, false ] );
hodima_ad( 'نوار: چهار li (داده گوگل، پوشش تحلیل، پوشش خزش، نقشه سایت؛ ردیف نقشه از SEO 3.5.2 وقتی نقشه خوانده شده)', substr_count( $qd_ok, 'class="hodima-advisor__quality-item"' ), 4 );
$GLOBALS['wpdb']->insert( $qd_pt, [ 'path' => '/qd-page/', 'clicks' => 1, 'impressions' => 2000, 'advice' => '' ] );
hodima_seo_advisor_caps( [ 'analyze' => true ] );
$qd_warn = $qd_render( 'crawl' );
hodima_ad( 'نوار ناقص روی «ایرادهای فنی»: «کامل نیست (۱ مورد)» و فهرست توضیح با نام مورد', [ str_contains( $qd_warn, 'کامل نیست' ), str_contains( $qd_warn, 'hodima-advisor__quality-notes' ), str_contains( $qd_warn, '<b>پوشش تحلیل:</b>' ) ], [ true, true, true ] );
hodima_seo_advisor_caps( [ 'analyze' => false ] );
$GLOBALS['wpdb']->delete( $qd_pt, [ 'path' => '/qd-page/' ] );
hodima_ad( 'نوار فقط روی دو تب: تنظیمات، آمار، کارها و … خالی‌اند', [ $qd_render( 'settings' ), $qd_render( 'terms' ), $qd_render( 'tasks' ), $qd_render( 'health' ) ], [ '', '', '', '' ] );
delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION );
delete_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => 0 ] + $qd_status, false );
$qd_w->query( $qd_w->prepare( 'DELETE FROM %i', $qd_t ) );
hodima_seo_advisor_crawl_map( true );
$qd_empty_q = hodima_seo_advisor_quality();
hodima_ad( 'همه نامعلوم (هنوز داده‌ای نیست): سطح unknown و هیچ نواری نمی‌آید', [ $qd_empty_q['level'] === 'unknown' || $qd_empty_q['level'] === 'ok', $qd_empty_q['issues'] ], [ true, 0 ] );

foreach ( $qd_keep as $qd_opt => $qd_val ) {
	null === $qd_val ? delete_option( $qd_opt ) : update_option( $qd_opt, $qd_val, false );
}
hodima_seo_advisor_crawl_map( true );
