<?php
/**
 * بخش 8 از آزمون‌های دستار سئو (tools/wp-harness/advisor-tests.php آن را به ترتیب و در همان دامنه سراسری می‌خواند):
 * خط‌کش پیکسلی مشترک سئوباکس و دستار، راهنمای «کارها»، گزارش و ارقام فارسی.
 * تنهایی اجرا نمی‌شود: متغیرها و توابع کمکی (hodima_ad، $cap، …) از فایل اصلی و بخش‌های قبلی می‌آیند.
 */

declare(strict_types=1);

echo "=== خط‌کش پیکسلی مشترک سئوباکس و دستار (SEO 3.5.4)\n";
// مقدار مرجع: canvas در Chromium با همان فونت (وزیرمتن ۴۰۰؛ ۲۰px عنوان، ۱۴px متا)، tools/fonts/make-seo-metrics.py
$px_ref = [
	[ 'گیره انبری فلزی | هدیما', 182.6, 127.8 ],
	[ 'خرید عمده اکسسوری مو؛ قیمت و مشخصات کلیپ و گیره', 449.0, 314.3 ],
	[ 'خرید عمده گیره مو فانتزی با بهترین قیمت از هدهدلی', 418.6, 293.0 ],
	[ "تل مو پارچه\u{200C}ای مدل پاپیونی؛ خرید اینترنتی", 319.9, 223.9 ],
	[ 'Hair accessories wholesale | Hodhodli', 340.2, 238.1 ],
];
$px_worst = 0.0;
foreach ( $px_ref as [ $px_text, $px_t, $px_m ] ) {
	$px_worst = max( $px_worst, abs( hodima_seo_text_px( $px_text, 'title' ) - $px_t ) / $px_t, abs( hodima_seo_text_px( $px_text, 'description' ) - $px_m ) / $px_m );
}
hodima_ad( 'برآوردگر پیکسل PHP در برابر canvas واقعی (فونت وزیرمتن): خطا کمتر از ۵٪ روی ۵ متن فارسی/لاتین', $px_worst < 0.05, true );
hodima_ad( 'متن خالی و فاصله‌های اضافه: صفر / هم‌ارز', [ hodima_seo_text_px( '' ), hodima_seo_text_px( "  \n " ), hodima_seo_text_px( 'کش  مو' ) === hodima_seo_text_px( 'کش مو' ) ], [ 0, 0, true ] );
hodima_ad( 'نیم‌فاصله اتصال را قطع می‌کند و عرض ندارد (می‌رو ≠ میرو)', hodima_seo_text_px( "می\u{200C}رو" ) !== hodima_seo_text_px( 'میرو' ), true );
hodima_ad( 'سقف‌ها یک جا: عنوان ۵۸۰/۲۹۰ در ۲۰px، متا ۹۲۰/۳۶۸ در ۱۴px', [ hodima_seo_px_limit( 'title' ), hodima_seo_px_limit( 'description' ), hodima_seo_px_limit( 'meta' ) ], [ [ 'max' => 580, 'min' => 290, 'font_px' => 20 ], [ 'max' => 920, 'min' => 368, 'font_px' => 14 ], [ 'max' => 920, 'min' => 368, 'font_px' => 14 ] ] );
hodima_ad( 'فایل فونت مرجع کنار سئوباکس هست و جاوااسکریپت سقف را از سرور می‌گیرد (نه عدد ثابت)', [ is_file( HODIMA_SEO_DIR . '/core/seobox/assets/vazirmatn-ref.woff2' ), str_contains( (string) file_get_contents( HODIMA_SEO_DIR . '/core/seobox/admin-script.js' ), 'window.hodimaSeoPx' ), str_contains( (string) file_get_contents( HODIMA_SEO_DIR . '/core/seobox/admin-script.js' ), 'Arial, Tahoma' ) ], [ true, true, true ] ); // Arial فقط برای حالت شکست بارگذاری فونت
hodima_ad( 'ردیف خزنده: title_px و meta_px با همان خط‌کش ذخیره می‌شود', [ (int) $cw_rows['/']['title_px'], (int) $cw_rows['/']['meta_px'] ], [ hodima_seo_text_px( 'خانه با عنوان خیلی بلند برای آزمون خزنده', 'title' ), hodima_seo_text_px( 'توضیح', 'description' ) ] );

$px_t = hodima_seo_advisor_table( 'crawl_pages' );
$px_row = static fn( string $path, int $tpx, int $mpx ): array => [ 'path' => $path, 'status' => 200, 'indexable' => 1, 'title' => 'عنوان ' . $path, 'title_len' => 45, 'meta_len' => 120, 'title_px' => $tpx, 'meta_px' => $mpx, 'h1' => 'تیتر', 'words' => 300, 'h1_count' => 1, 'inlinks' => 5, 'text_links' => 5, 'depth' => 1 ];
foreach ( [ [ '/px-old/', 0, 0 ], [ '/px-short/', 150, 500 ], [ '/px-long/', 640, 1000 ], [ '/px-ok/', 450, 700 ] ] as [ $px_p, $px_a, $px_b ] ) {
	$GLOBALS['wpdb']->insert( $px_t, $px_row( $px_p, $px_a, $px_b ) );
}
hodima_seo_advisor_crawl_map( true );
$px_codes = static function ( string $path ): array {
	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		if ( $row['path'] === $path ) {
			return array_values( array_intersect( array_column( $row['issues'], 'code' ), [ 'title_short', 'title_long' ] ) );
		}
	}
	return [];
};
hodima_ad( 'ایراد عنوان با پیکسل: کوتاه، بلند، سالم؛ ردیف بی‌پیکسل (خزش قدیمی) ایرادی نمی‌سازد', [ $px_codes( '/px-short/' ), $px_codes( '/px-long/' ), $px_codes( '/px-ok/' ), $px_codes( '/px-old/' ) ], [ [ 'title_short' ], [ 'title_long' ], [], [] ] );
hodima_ad( 'سلامت عنوان/متا: ردیف بی‌پیکسل = ناشناخته (null)؛ سالم و ناسالم با پیکسل', [ hodima_seo_advisor_crawl_meta_health( '/px-old/' ), hodima_seo_advisor_crawl_meta_health( '/px-ok/' )['healthy'] ?? null, hodima_seo_advisor_crawl_meta_health( '/px-long/' )['problems'] ?? null, hodima_seo_advisor_crawl_meta_health( '/px-short/' )['problems'] ?? null ], [ null, true, [ 'عنوان بلند است', 'توضیح متا بلند است' ], [ 'عنوان کوتاه است' ] ] );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i WHERE path LIKE %s', $px_t, '/px-%' ) );
hodima_seo_advisor_crawl_map( true );
$px_draft_meta = hodima_seo_advisor_draft_cut_px( str_repeat( 'گیره مو فانتزی با کیفیت عالی و قیمت مناسب. ', 12 ), 'description', hodima_seo_advisor_draft_px_max( 'description' ), 90 );
hodima_ad( 'پیش‌نویس متا: در ۹۵٪ سقف پیکسلی می‌ماند و خالی نیست', [ hodima_seo_text_px( $px_draft_meta, 'description' ) <= hodima_seo_advisor_draft_px_max( 'description' ), mb_strlen( $px_draft_meta ) > 90 ], [ true, true ] );

echo "=== تب «کارهای انجام‌شده»: پاک کردن، پاک‌سازی گروهی، خط زمانی فشرده (SEO 3.5.5)\n";
$tk_saved = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
$tk_cleared_saved = get_option( HODIMA_SEO_ADVISOR_TASKS_CLEARED_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_TASKS_CLEARED_OPTION );
$tk_now = time();
$tk_task = static fn( string $path, string $rule, string $status, int $fixed_days_ago, string $title ): array => [ 'path' => $path, 'rule' => $rule, 'term' => '', 'title' => $title, 'status' => $status, 'at' => $tk_now - ( $fixed_days_ago + 1 ) * DAY_IN_SECONDS, 'fixed' => $fixed_days_ago >= 0 ? $tk_now - $fixed_days_ago * DAY_IN_SECONDS : 0, 'back' => 0, 'check' => [] ];
$tk_rows = [];
foreach ( [ 200, 100, 10 ] as $tk_i => $tk_ago ) {
	$tk_rows[ hodima_seo_advisor_task_key( "/tk-c{$tk_i}/", 'crawl_no_meta', '' ) ] = $tk_task( "/tk-c{$tk_i}/", 'crawl_no_meta', 'done', $tk_ago, 'بدون توضیح متا' );
	$tk_rows[ hodima_seo_advisor_task_key( "/tk-h{$tk_i}/", 'health_dead', '' ) ] = $tk_task( "/tk-h{$tk_i}/", 'health_dead', 'done', $tk_ago, 'آدرس خراب' );
}
$tk_back_key  = hodima_seo_advisor_task_key( '/tk-back/', 'crawl_no_h1', '' );
$tk_doing_key = hodima_seo_advisor_task_key( '/tk-doing/', 'crawl_no_h1', '' );
$tk_rows[ $tk_back_key ]  = [ 'back' => $tk_now - 100 ] + $tk_task( '/tk-back/', 'crawl_no_h1', 'done', -1, 'تیتر برگشته' );
$tk_rows[ $tk_doing_key ] = $tk_task( '/tk-doing/', 'crawl_no_h1', 'doing', -1, 'تیتر در حال انجام' );
$tk_rows[ $tk_doing_key ]['at'] = $tk_now - 10;
$tk_rows[ hodima_seo_advisor_task_key( '/tk-rej/', 'crawl_no_h1', '' ) ] = $tk_task( '/tk-rej/', 'crawl_no_h1', 'rejected', -1, 'تیتر رد شده' );
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $tk_rows, false );
hodima_seo_advisor_tasks_cache_reset();
wp_set_current_user( 1 );
$tk_render = static function ( array $get ) use ( $cap ): string {
	$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks' ] + $get;
	$html = $cap( static fn() => hodima_seo_advisor_render_tasks() );
	$_GET = [];
	return $html;
};
$tk_fixed = $tk_render( [ 'tstatus' => 'fixed' ] );
hodima_ad( 'برطرف شد: فقط «تمام شد، پاک کن» (۶ ردیف)، بی «در حال انجام» و «لازم نیست» و «برگردان به فهرست»', [ substr_count( $tk_fixed, '>تمام شد، پاک کن</button>' ), str_contains( $tk_fixed, '>در حال انجام</button>' ), str_contains( $tk_fixed, '>لازم نیست</button>' ), str_contains( $tk_fixed, '>برگردان به فهرست</button>' ) ], [ 6, false, false, false ] );
hodima_ad( 'برطرف شد: خط زمانی فشرده به‌جای سه کارت؛ nonce بی شناسه (یک فرم و چند دکمه، بدون id="_wpnonce" تکراری)', [ substr_count( $tk_fixed, 'hodima-advisor__tl-line' ), str_contains( $tk_fixed, 'hodima-advisor__step' ), substr_count( $tk_fixed, 'id="_wpnonce"' ) < 2, substr_count( $tk_fixed, '<form method="post"' ) >= 6 ], [ 6, false, true, true ] );
hodima_ad( 'برطرف شد: دکمه پاک‌سازی گروهی با شمار، پنجره تأیید، منوی «قدیمی‌تر از»، CSV؛ بی شمارنده تا چیزی پاک نشده', [ str_contains( $tk_fixed, 'پاک کردن همه برطرف‌شده‌ها (6)' ), str_contains( $tk_fixed, 'id="hodima-advisor-clean"' ), str_contains( $tk_fixed, 'قدیمی‌تر از 90 روز (4)' ), str_contains( $tk_fixed, 'action=hodima_advisor_task_csv' ), str_contains( $tk_fixed, 'تا امروز' ) ], [ true, true, true, true, false ] );
hodima_ad( 'منوی «بخش» فقط با دو بخش ≥۳ کار (۳ خزش + ۳ آدرس خراب)؛ «همه» و ردیف چیپ بخش نیست', [ str_contains( $tk_fixed, 'id="hodima-advisor-tsource"' ), str_contains( $tk_fixed, 'aria-label="از کدام بخش"' ) ], [ true, false ] );
$tk_filtered = $tk_render( [ 'tstatus' => 'fixed', 'tsource' => 'health' ] );
hodima_ad( 'فیلتر بخش: فقط آدرس‌های خراب', [ substr_count( $tk_filtered, '>تمام شد، پاک کن</button>' ), str_contains( $tk_filtered, 'بدون توضیح متا' ) ], [ 3, false ] );
$tk_open = $tk_render( [ 'tstatus' => 'open' ] );
hodima_ad( 'نیاز به کار: «برگشته» بالاتر از «در حال انجام» با اینکه کار دوم تازه‌تر است', [ mb_strpos( $tk_open, 'تیتر برگشته' ) < mb_strpos( $tk_open, 'تیتر در حال انجام' ), str_contains( $tk_open, '>دوباره انجام دادم</button>' ), str_contains( $tk_open, '>انجام دادم</button>' ) ], [ true, true, true ] );
$tk_rej = $tk_render( [ 'tstatus' => 'rejected' ] );
hodima_ad( 'لازم نیست: فقط «برگردان به فهرست» (همان پاک‌کردن رکورد)؛ بی دکمه پاک‌سازی گروهی', [ str_contains( $tk_rej, '>برگردان به فهرست</button>' ), str_contains( $tk_rej, '>پاک کردن</button>' ), str_contains( $tk_rej, 'hodima-advisor-clean' ) ], [ true, false, false ] );

hodima_ad( 'گزینه‌های «قدیمی‌تر از» فقط از فهرست مجاز', [ hodima_seo_advisor_tasks_clean_days( '90' ), hodima_seo_advisor_tasks_clean_days( '7' ), hodima_seo_advisor_tasks_clean_days( [ 1 ] ), hodima_seo_advisor_tasks_clean_days( '0' ) ], [ 90, 0, 0, 0 ] );
hodima_ad( 'کارهای برطرف‌شده قدیمی‌تر از ۹۰ روز: ۴ کار (۲۰۰ و ۱۰۰ روز، دو بخش)؛ «همه» ۶؛ برگشته و در حال انجام و رد شده نه', [ count( hodima_seo_advisor_tasks_fixed_before( 90 ) ), count( hodima_seo_advisor_tasks_fixed_before( 0 ) ), isset( hodima_seo_advisor_tasks_fixed_before( 0 )[ $tk_back_key ] ) ], [ 4, 6, false ] );

$tk_expect = [];
foreach ( hodima_seo_advisor_tasks_fixed_before( 90 ) as $tk_key => $tk_t ) {
	$tk_expect[ $tk_key ] = [ 'at' => $tk_t['at'], 'status' => $tk_t['status'] ];
}
// کاری که بین انتخاب و پاک‌کردن عوض شد (مدیر «لازم نیست» زد) نباید پاک شود
$tk_victim = array_key_first( $tk_expect );
$tk_mut    = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
$tk_mut[ $tk_victim ]['status'] = 'rejected';
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $tk_mut, false );
$tk_removed = hodima_seo_advisor_tasks_remove( $tk_expect );
hodima_seo_advisor_tasks_cleared_add( $tk_removed );
hodima_ad( 'پاک‌سازی از نسخه تازه گزینه: کار عوض‌شده می‌ماند (۳ از ۴ پاک)، شمارنده ۳، باقی: ۶ − ۳ + همان کار + ۳ کار دیگر', [ $tk_removed, hodima_seo_advisor_tasks_cleared(), count( hodima_seo_advisor_tasks() ), isset( hodima_seo_advisor_tasks()[ $tk_victim ] ) ], [ 3, 3, 6, true ] );
$tk_after = $tk_render( [ 'tstatus' => 'fixed' ] );
hodima_ad( 'بعد از پاک‌سازی: شمارنده «تا امروز N کار برطرف شد» = پاک‌شده + مانده (۳ + ۲)', [ str_contains( $tk_after, 'تا امروز 5 کار برطرف شد' ), str_contains( $tk_after, 'پاک کردن همه برطرف‌شده‌ها (2)' ) ], [ true, true ] );

$tk_csv = hodima_seo_advisor_tasks_csv( 'fixed', 0 );
$tk_lines = array_filter( explode( "\n", trim( $tk_csv ) ) );
hodima_ad( 'CSV: BOM، سطر عنوان، یک سطر برای هر کار برطرف‌شده، ستون‌ها', [ str_starts_with( $tk_csv, "\xEF\xBB\xBF" ), count( $tk_lines ), str_contains( $tk_lines[0], 'تاریخ برطرف شدن' ), str_contains( $tk_csv, '/tk-c2/' ), str_contains( $tk_csv, '/tk-back/' ) ], [ true, 3, true, true, false ] );
$tk_inj = [ hodima_seo_advisor_task_key( '/=cmd/', 'crawl_no_meta', '' ) => $tk_task( '/=cmd/', 'crawl_no_meta', 'done', 1, '=HYPERLINK("x")' ) ];
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $tk_inj, false );
hodima_seo_advisor_tasks_cache_reset();
hodima_ad( 'CSV: سلول با = شروع شود با آپستروف خنثی می‌شود (فرمول اکسل)', [ str_contains( hodima_seo_advisor_tasks_csv( 'fixed', 0 ), "'=HYPERLINK" ), str_contains( hodima_seo_advisor_tasks_csv( 'fixed', 0 ), ',=HYPERLINK' ) ], [ true, false ] );

// یادآوری پر شدن: ≥۵۰۰ کار بسته ← نوار ملایم، ≥۲۶۰۰ کل ← هشدار؛ زیر آن هیچ
$tk_note = static function ( int $total, array $counts ): string {
	ob_start();
	hodima_seo_advisor_render_tasks_note( $total, $counts + [ 'open' => 0, 'waiting' => 0, 'fixed' => 0, 'rejected' => 0 ], 'open' );
	return (string) ob_get_clean();
};
hodima_ad( 'یادآوری پر شدن: خالی زیر ۵۰۰ بسته، ملایم از ۵۰۰، هشدار سقف از ۲۶۰۰ (پاک‌کردن خودکار نیست)', [ $tk_note( 100, [ 'fixed' => 100 ] ), str_contains( $tk_note( 600, [ 'fixed' => 600 ] ), '600 کار بسته' ), str_contains( $tk_note( 2700, [ 'fixed' => 100 ] ), 'بی‌صدا پاک می‌شوند' ) ], [ '', true, true ] );
false === $tk_saved ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $tk_saved, false );
false === $tk_cleared_saved ? delete_option( HODIMA_SEO_ADVISOR_TASKS_CLEARED_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_CLEARED_OPTION, $tk_cleared_saved, false );
hodima_seo_advisor_tasks_cache_reset();
wp_set_current_user( 0 );

echo "=== عنوان/H1 مشابه: کانیبال بالقوه از خزنده (SEO 3.5.7)\n";
$sm_yes = [ [ 'کلیپ مو', 'کلیپس مو' ], [ 'خرید عمده کلیپ مو | هدهدلی', 'عمده کلیپ مو - هدهدلی' ], [ 'گیره مو فانتزی', 'گیره فانتزی مو' ], [ 'کش مو بچگانه', 'کش مو بچه‌گانه' ], [ 'تل مو پارچه‌ای', 'تل موی پارچه ای' ], [ 'شانه و برس مو', 'برس و شانه مو' ], [ 'قیمت گیره مو ۱۲ عددی', 'گیره‌ی مو ۱۲ عددی' ], [ 'كش مو ساتن', 'کش مو ساتن' ], [ 'آینه مو', 'اینه مو' ] ];
$sm_no  = [ [ 'کلیپ مو فلزی', 'کلیپ مو پلاستیکی' ], [ 'گیره مو ۱۲ عددی', 'گیره مو ۲۴ عددی' ], [ 'کلیپ مو', 'کلیپ مو فانتزی بزرگ' ], [ 'تل مو پارچه‌ای', 'تل مو چرمی' ], [ 'خرید عمده مو', 'عمده مو' ], [ 'گیره مو', 'کش مو' ], [ 'کش مو ساتن', 'کش مو ساده' ], [ 'برس مو بچگانه', 'برس مو زنانه' ], [ 'سنجاق سر مرواریدی', 'سنجاق مو مرواریدی' ] ];
$sm_site = hodima_seo_advisor_similar_tokens( 'هدهدلی' );
$sm_is   = static fn( array $pair ): bool => hodima_seo_advisor_similar_match( hodima_seo_advisor_similar_tokens( $pair[0], $sm_site ), hodima_seo_advisor_similar_tokens( $pair[1], $sm_site ) );
hodima_ad( 'عنوان مشابه: املا، ترتیب، نیم‌فاصله، «ی/ای»، ي/ك عربی، نام سایت و کلمه تجاری نادیده؛ هر ۹ جفت مشابه', array_map( $sm_is, $sm_yes ), array_fill( 0, count( $sm_yes ), true ) );
hodima_ad( 'عنوان غیرمشابه: جنس/نوع/عدد متفاوت، کلمه اضافه، کلمه دیگر؛ هیچ مثبت کاذبی', array_map( $sm_is, $sm_no ), array_fill( 0, count( $sm_no ), false ) );
hodima_ad( 'کلمه‌های مشابه: یک حرف فرق فقط برای کلمه ≥۴ حرف بدون رقم', [ hodima_seo_advisor_similar_token_match( 'کلیپ', 'کلیپس' ), hodima_seo_advisor_similar_token_match( 'مو', 'مه' ), hodima_seo_advisor_similar_token_match( 'مدل12', 'مدل13' ), hodima_seo_advisor_similar_token_match( 'قرمز', 'سبز' ) ], [ true, false, false, false ] );
hodima_ad( 'کمتر از ۲ کلمه معنی‌دار مشابه حساب نمی‌شود (فقط نام سایت یا کلمه تجاری)', [ hodima_seo_advisor_similar_match( [ 'کلیپ' ], [ 'کلیپ' ] ), hodima_seo_advisor_similar_match( [], [] ) ], [ false, false ] );
$sm_pairs = hodima_seo_advisor_similar_pairs( [ '/a/' => 'کلیپ مو فرانسوی | هدهدلی', '/b/' => 'کلیپ فرانسوی مو | هدهدلی', '/c/' => 'کلیپ مو فلزی | هدهدلی', '/d/' => 'کلیپ مو فرانسوی | هدهدلی' ], $sm_site );
hodima_ad( 'جفت‌های مشابه: ترتیب فرق دارد = مشابه؛ عنوان دقیقا یکسان را dup_title می‌گیرد، نه اینجا؛ «فلزی» جدا', [ $sm_pairs['/a/'] ?? null, $sm_pairs['/b/'] ?? null, isset( $sm_pairs['/c/'] ) ], [ [ '/b/' ], [ '/a/', '/d/' ], false ] );
$sm_big = [];
for ( $i = 0; $i < 120; $i++ ) {
	$sm_big[ "/g{$i}/" ] = 'کلیپ مو مدل' . $i . ' ' . ( $i % 2 ? 'فلزی' : 'پلاستیکی' );
}
$sm_t0 = microtime( true );
hodima_seo_advisor_similar_pairs( $sm_big );
hodima_ad( 'سرعت: ۱۲۰ عنوان هم‌خانواده زیر ۲۰۰ میلی‌ثانیه (دسته‌بندی، نه هر جفت)', ( microtime( true ) - $sm_t0 ) < 0.2, true );

$sm_t = hodima_seo_advisor_table( 'crawl_pages' );
$sm_row = static fn( string $path, string $title, string $h1, int $status = 200, int $indexable = 1 ): array => [ 'path' => $path, 'status' => $status, 'indexable' => $indexable, 'title' => $title, 'title_len' => 30, 'meta_len' => 120, 'title_px' => 400, 'meta_px' => 700, 'h1' => $h1, 'words' => 300, 'h1_count' => 1, 'inlinks' => 5, 'text_links' => 5, 'depth' => 1, 'canonical' => $path ];
foreach ( [
	$sm_row( '/sm-a/', 'کلیپ مو', 'کلیپ مو' ),
	$sm_row( '/sm-b/', 'کلیپس مو', 'کلیپس مو' ),
	$sm_row( '/sm-c/', 'کلیپ مو فلزی', 'کلیپ فلزی' ),
	$sm_row( '/sm-d/page/2/', 'کلیپ مو', 'کلیپ مو' ),
	$sm_row( '/sm-e/', 'کلیپ مو', 'کلیپ مو', 200, 0 ),
	$sm_row( '/sm-f/', 'عنوان یکسان دقیق', 'تیتر ف' ),
	$sm_row( '/sm-g/', 'عنوان یکسان دقیق', 'تیتر گ' ),
] as $sm_r ) {
	$GLOBALS['wpdb']->insert( $sm_t, $sm_r );
}
hodima_seo_advisor_crawl_map( true );
$sm_issues = static function ( string $path ): array {
	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		if ( $row['path'] === $path ) {
			return $row['issues'];
		}
	}
	return [];
};
$sm_a = array_values( array_filter( $sm_issues( '/sm-a/' ), static fn( array $i ): bool => 'near_dup' === $i['code'] ) )[0] ?? null;
hodima_ad( 'ایراد «عنوان مشابه»: صفحه ا با ب شبیه است (املا) و فقط با ب؛ نه با ج (فلزی)، نه با صفحه‌بندی/نوایندکس', [ $sm_a['detail'] ?? '', array_column( (array) ( $sm_a['items'] ?? [] ), 'path' ), count( (array) ( $sm_a['items'] ?? [] ) ) ], [ 'شبیه 1 صفحه دیگر', [ '/sm-b/' ], 1 ] );
hodima_ad( 'ایراد «عنوان مشابه»: یادداشت هر صفحه شبیه نوع شباهت (عنوان و H1) و متن آن را دارد', str_contains( (string) ( $sm_a['items'][0]['note'] ?? '' ), 'عنوان و H1 شبیه' ), true );
hodima_ad( 'صفحه ج (فلزی): بدون ایراد مشابه با ا یا ب', array_column( $sm_issues( '/sm-c/' ), 'code' ) === array_values( array_diff( array_column( $sm_issues( '/sm-c/' ), 'code' ), [ 'near_dup' ] ) ), true );
hodima_ad( 'عنوان دقیقا یکسان فقط dup_title می‌گیرد، نه near_dup (جلوگیری از ایراد دوبار)', [ in_array( 'dup_title', array_column( $sm_issues( '/sm-f/' ), 'code' ), true ), in_array( 'near_dup', array_column( $sm_issues( '/sm-f/' ), 'code' ), true ) ], [ true, false ] );
$sm_info = hodima_seo_advisor_crawl_issue_info( 'near_dup' );
hodima_ad( 'کاتالوگ ایراد: برچسب، سطح، دستور با «۳۰۱» و «ریدایرکت‌ها»، در گروه «تکراری»؛ علامت «انجام دادم» با خزش دوباره سنجیده می‌شود', [ $sm_info['label'], str_contains( $sm_info['fix'], '۳۰۱' ), in_array( 'near_dup', HODIMA_SEO_ADVISOR_CRAWL_GROUPS['dup'], true ), in_array( 'near_dup', HODIMA_SEO_ADVISOR_MARK_RECRAWL_CODES, true ) ], [ 'عنوان مشابه صفحه دیگر', true, true, true ] );
$sm_detail = $cap( static fn() => hodima_seo_advisor_render_issue_detail( [ 'src' => 'crawl', 'mark' => 'near_dup', 'targets' => (array) ( $sm_a['items'] ?? [] ) ], '/sm-a/' ) );
hodima_ad( 'جزئیات ایراد: «کدام صفحه‌ها شبیه‌اند؟ (1)» با آدرس صفحه شبیه و دکمه کپی', [ str_contains( $sm_detail, 'کدام صفحه‌ها شبیه‌اند؟ (1)' ), str_contains( $sm_detail, '/sm-b/' ) ], [ true, true ] );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i WHERE path LIKE %s', $sm_t, '/sm-%' ) );
hodima_seo_advisor_crawl_map( true );

echo "=== راهنمای پنج‌بخشی پیشنهادهای گوگل، ایندکس و هم‌کلمه (SEO 3.7.0)\n";
// خواست کاربر (عکس «نرخ کلیک کم»): «چه دیدیم» آمار و دستور و «طبق خزش» را قاطی داشت و «دقیقا چه کنم» نداشت
$g7_q    = [ [ 'term' => 'کلیپس پلومریا', 'clicks' => 116, 'impressions' => 15480, 'position' => 5.08 ], [ 'term' => 'گل سر', 'clicks' => 2, 'impressions' => 900, 'position' => 12.0 ], [ 'term' => 'کش مو', 'clicks' => 0, 'impressions' => 800, 'position' => 35.0 ] ];
$g7_text = [ 'title_headings' => 'هیچ', 'content' => 'متن درباره گل سر' ];
$g7_all  = [
	...hodima_seo_advisor_suggestions( $base, $g7_q, $g7_text, [ hodima_seo_advisor_term_key( 'کلیپس پلومریا' ) => [ [ 'path' => '/rival/', 'impressions' => 500 ] ] ] ),
	...hodima_seo_advisor_suggestions( $base, $g7_q, null, [], null, [ 'healthy' => true, 'title_px' => 400, 'meta_px' => 800, 'problems' => [] ] ),
	...hodima_seo_advisor_suggestions( $base, $g7_q, null, [], null, [ 'healthy' => false, 'title_px' => 100, 'meta_px' => 0, 'problems' => [ 'توضیح متا ندارد' ] ] ),
	...hodima_seo_advisor_suggestions( [ 'path' => '/d/', 'clicks' => 40, 'impressions' => 9000, 'position' => 9.0, 'prev_clicks' => 200, 'prev_impressions' => 9000, 'prev_position' => 4.0 ], [], null ),
];
$g7_bad   = [];
$g7_rules = [];
foreach ( $g7_all as $g7_s ) {
	$g7_rules[ $g7_s['rule'] ] = true;
	$g7_seen = (string) ( $g7_s['seen'] ?? '' );
	if ( '' === $g7_seen || empty( $g7_s['todo'] ) || ! array_is_list( $g7_s['todo'] ) ) {
		$g7_bad[] = $g7_s['rule'] . ': بدون seen/todo';
	}
	// «چه دیدیم» دستور ندارد (فعل امری «بیاورید/بدهید/کنید» مال گام‌هاست)
	if ( 1 === preg_match( '/بیاورید|لینک بدهید|بازنویسی کنید|بسازید/u', $g7_seen ) ) {
		$g7_bad[] = $g7_s['rule'] . ': دستور در «چه دیدیم» — ' . $g7_seen;
	}
}
hodima_ad( 'هر پیشنهاد گوگل «چه دیدیم» بی‌دستور و گام‌های جدا دارد (همه ۷ نوع؛ نرخ کلیک هر دو حالت؛ افت با علت)', [ $g7_bad, array_keys( array_diff_key( array_flip( [ 'low_ctr', 'near_first', 'missing_content', 'weak_query', 'cannibal', 'decline' ] ), $g7_rules ) ) ], [ [], [] ] );
// «گزارش» باکس‌دار (SEO 3.7.9، طرح تأییدشده): هر پیشنهاد گوگل report دارد (باکس یا کارت صفحه)، هر باکس برچسب، و نرخ کلیک نوار ۰ تا ۱؛ بی دستور
$rp_bad = [];
foreach ( $g7_all as $g7_s ) {
	$rp = (array) ( $g7_s['report'] ?? [] );
	if ( ! $rp || ( empty( $rp['boxes'] ) && empty( $rp['pages'] ) ) ) {
		$rp_bad[] = $g7_s['rule'] . ': بدون گزارش';
		continue;
	}
	foreach ( (array) ( $rp['boxes'] ?? [] ) as $rp_b ) {
		if ( '' === (string) ( $rp_b['label'] ?? '' ) || ( '' === (string) ( $rp_b['value'] ?? '' ) && empty( $rp_b['chips'] ) ) || ( isset( $rp_b['bar'] ) && ( $rp_b['bar'] < 0 || $rp_b['bar'] > 1 ) ) ) {
			$rp_bad[] = $g7_s['rule'] . ': باکس ناقص ' . wp_json_encode( $rp_b, JSON_UNESCAPED_UNICODE );
		}
	}
	if ( 1 === preg_match( '/بیاورید|لینک بدهید|بازنویسی کنید|بسازید/u', (string) wp_json_encode( $rp, JSON_UNESCAPED_UNICODE ) ) ) {
		$rp_bad[] = $g7_s['rule'] . ': دستور در گزارش';
	}
}
hodima_ad( 'گزارش باکس‌دار: هر ۷ نوع پیشنهاد گوگل باکس یا کارت دارد، باکس‌ها کامل و بی‌دستور (SEO 3.7.9)', $rp_bad, [] );
$rp_ctr = array_values( array_filter( $g7_all, static fn( array $x ): bool => 'low_ctr' === $x['rule'] ) )[0]['report'] ?? [];
ob_start();
hodima_seo_advisor_render_report( (array) $rp_ctr );
$rp_html = (string) ob_get_clean();
hodima_ad( 'گزارش نرخ کلیک: عنوان «گزارش»، باکس نارنجی نرخ کلیک با نوار، ارقام فارسی؛ متن «چه دیدیم» نیست', [ str_contains( $rp_html, '>گزارش' ), str_contains( $rp_html, 'hodima-advisor__rbox--warn' ) && str_contains( $rp_html, 'hodima-advisor__rbox-bar' ), 1 === preg_match( '/[۰-۹]/u', $rp_html ), str_contains( $rp_html, 'چه دیدیم' ) ], [ true, true, true, false ] );
$g7_low = array_values( array_filter( $g7_all, static fn( array $x ): bool => 'low_ctr' === $x['rule'] ) );
hodima_ad( 'نرخ کلیک کم: گام اول نام کلمه و «عنوان سئو» با جای «ویرایش»؛ «طبق خزش» در «چه دیدیم»؛ detail همان متن کامل قبلی', [ str_contains( $g7_low[0]['todo'][0], '«{where}»' ) && str_contains( $g7_low[0]['todo'][0], '«عنوان سئو»' ), str_contains( $g7_low[2]['seen'] ?? '', 'طبق خزش: توضیح متا ندارد' ), str_contains( $g7_low[0]['detail'], 'آن را ابتدای عنوان بیاورید' ) ], [ true, true, true ] );
$g7_dc = hodima_seo_advisor_decline_cause( [ 'clicks' => 40, 'impressions' => 9000, 'position' => 9.0, 'prev_clicks' => 200, 'prev_impressions' => 9000, 'prev_position' => 4.0 ], null );
hodima_ad( 'افت کلیک: علت در «چه دیدیم» و گام‌های همان علت؛ متن کامل قدیمی بدون تغییر', [ $g7_dc['cause'], str_starts_with( $g7_dc['seen'], 'علت اصلی: رتبه' ), count( $g7_dc['todo'] ) >= 2, str_contains( $g7_dc['text'], 'را بررسی کنید.' ) ], [ 'position', true, true, true ] );
$g7_ev = hodima_seo_advisor_links_evidence_parts( [ 'total' => 9, 'text' => 2, 'score' => 0.0, 'depth' => 5, 'anchors' => [] ], 'گل سر' );
hodima_ad( 'شواهد لینک جدا: «چه دیدیم» شمار لینک و عمق؛ گام‌ها لینک با همان متن و نزدیک کردن', [ count( $g7_ev['seen'] ), count( $g7_ev['steps'] ), $g7_ev['well'], str_contains( implode( '', $g7_ev['steps'] ), '«گل سر»' ) ], [ 3, 2, false, true ] );

// ایندکس: هر وضعیت مشکل‌دار راهنمای کامل دارد و «افزونه/تنظیمات همان صفحه» مبهم ندارد
$g7_ix = [];
foreach ( [ 'crawled_not_indexed', 'discovered_not_indexed', 'noindex', 'robots', 'soft404', 'not_found', 'server_error', 'duplicate', 'redirect', 'unknown_to_google', 'other' ] as $g7_state ) {
	$g7_i = hodima_seo_advisor_inspect_state_info( $g7_state );
	if ( '' === (string) ( $g7_i['seen'] ?? '' ) || empty( $g7_i['steps'] ) || '' === (string) ( $g7_i['why'] ?? '' ) || '' === $g7_i['advice'] || str_contains( implode( ' ', $g7_i['steps'] ), 'تنظیمات همان صفحه' ) ) {
		$g7_ix[] = $g7_state;
	}
}
hodima_ad( 'ایندکس: هر ۱۱ وضعیت «چه دیدیم»، گام و «چرا» دارد؛ برچسب و دستور کوتاه جدول تب «ایندکس» همان قبلی', [ $g7_ix, hodima_seo_advisor_inspect_state_info( 'noindex' )['label'], hodima_seo_advisor_inspect_state_info( 'indexed' )['advice'] ], [ [], 'noindex', '' ] );

// هم‌کلمه: تا ۳.۶.۹ «کارها» $group['detail'] (ناموجود) را می‌خواند و همیشه جمله پیش‌فرض می‌آمد
$g7_grp = [ 'term' => 'کلیپس', 'pages' => [ [ 'path' => '/cat/', 'share' => 0.7 ], [ 'path' => '/p1/', 'share' => 0.3 ] ], 'advice' => [ 'type' => 'strengthen', 'detail' => 'x' ] ];
$g7_win = hodima_seo_advisor_competition_guide( $g7_grp, '/cat/' );
$g7_los = hodima_seo_advisor_competition_guide( $g7_grp, '/p1/' );
hodima_ad( 'هم‌کلمه: برنده و بازنده گام متفاوت؛ سهم و آدرس کامل رقیب در «چه دیدیم»؛ «چرا» از راهنمای رقابت', [ str_contains( $g7_win['seen'], '۷۰٪' ) || str_contains( $g7_win['seen'], '70٪' ), str_contains( $g7_win['seen'], hodima_seo_advisor_url( '/p1/' ) ), str_contains( $g7_win['steps'][0], 'صفحه اصلی «کلیپس»' ) && str_contains( $g7_win['steps'][0], 'عوض نکنید' ), str_contains( implode( ' ', $g7_los['steps'] ), hodima_seo_advisor_url( '/cat/' ) ), '' !== $g7_win['why'] ], [ true, true, true, true, true ] ); // SEO 3.8.0: متن گام اول برنده عمدا دقیق‌تر شد؛ آدرس صفحه اصلی در گام لینک بازنده

// دکمه ویرایش دسته و ارقام فارسی
wp_set_current_user( 1 );
$g7_cat = (int) wp_insert_term( 'دسته آزمون راهنما', 'category' )['term_id'];
hodima_ad( 'دکمه ویرایش برای دسته هم ساخته می‌شود (قبلا «دیدن در سایت»)', [ str_contains( hodima_seo_advisor_overview_link( 'category', $g7_cat, '/x/' )[0], 'term.php' ), hodima_seo_advisor_overview_link( 'category', $g7_cat, '/x/' )[2] ], [ true, 'edit' ] );
wp_delete_term( $g7_cat, 'category' );
hodima_ad( 'ارقام فارسی در راهنما؛ آدرس، H1 و http دست نمی‌خورند؛ هزارگان «٬» و اعشار «٫»', hodima_seo_advisor_fa_digits( '7,821 بار و 3,25٪؛ کد 503 به /g-404/ و 3 تیتر H1 و http://a.b/1 (رتبه 5.7).' ), '۷٬۸۲۱ بار و ۳٫۲۵٪؛ کد ۵۰۳ به /g-404/ و ۳ تیتر H1 و http://a.b/1 (رتبه ۵٫۷).' );
