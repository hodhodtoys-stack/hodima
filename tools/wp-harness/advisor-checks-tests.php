<?php
/**
 * آزمون قاعده‌های ایراد فنی دستار سئو (SEO 3.6.1، core/advisor/advisor-checks.php): برای هر ایراد یک مورد «ایراد دارد» و یک
 * مورد «ندارد» (الگوی SEOnaut)، بدون دیتابیس و بدون خزش — فقط ردیف و زمینه ساختگی؛ به‌علاوه آزمون‌های ساختاری:
 * هر کد کاتالوگ دقیقا یک صاحب (تابع) دارد و گروه‌ها و بخش‌های نمره به کد ناشناخته اشاره نمی‌کنند.
 * مورد تازه = یک ردیف در $cases (ترجیحا از یک باگ یا مثبت کاذب واقعی سایت؛ نام مورد را با تاریخ و آدرس بنویس).
 *   HARNESS=1 php tools/wp-harness/advisor-checks-tests.php
 * کد خروج ۱ اگر موردی رد شود. ci-check.sh هم اجرا می‌کند.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! function_exists( 'hodima_seo_advisor_checks_run' ) ) {
	echo "✘ قاعده‌های دستار سئو لود نشده است\n";
	exit( 1 );
}

$failed = 0;
$passed = 0;

function hodima_ck( string $name, bool $ok, string $detail = '' ): void {
	global $failed, $passed;
	if ( $ok ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}" . ( '' !== $detail ? "\n      {$detail}" : '' ) . "\n";
}

/** ردیف سالم پایه (هیچ ایرادی نمی‌سازد)؛ هر مورد فقط چیزی را که می‌خواهد خراب کند بازنویسی می‌کند. */
function hodima_ck_row( array $over = [] ): array {
	$path = (string) ( $over['path'] ?? '/ck-page/' );
	return array_merge(
		[
			'path'        => $path,
			'status'      => 200,
			'indexable'   => 1,
			'reason'      => '',
			'title'       => 'عنوان یگانه آزمون',
			'title_len'   => 50,
			'meta_len'    => 120,
			'title_px'    => 400,
			'meta_px'     => 800,
			'h1'          => 'تیتر یگانه آزمون',
			'resp_ms'     => 200,
			'inlinks'     => 9,
			'text_links'  => 5,
			'depth'       => 1,
			'redirect_to' => '',
			'no_snippet'  => 0,
			'words'       => 400,
			'h1_count'    => 1,
			'img_noalt'   => 0,
			'http_links'  => 0,
			'canonical'   => $path,
			'img_list'    => '',
			'img_empty'   => 0,
			'impressions' => 0,
			'kind'        => 'page',
		],
		$over
	);
}

/** زمینه ساختگی (بدون دیتابیس). */
function hodima_ck_ctx( array $over = [] ): array {
	return $over + [ 'titles' => [], 'h1s' => [], 'similar' => [], 'by_path' => [], 'sitemap' => [], 'bad' => [] ];
}

/** کدهای ایراد خروجی یک تابع تکی. */
function hodima_ck_codes( string $fn, array $row, array $ctx ): array {
	return array_values( array_map( static fn( array $i ): string => (string) $i['code'], $fn( $row, $ctx ) ) );
}

$S = 'hodima_seo_advisor_check_';

// [نام مورد، تابع، ردیف، زمینه، کدهای انتظار]
$cases = [
	// ── عنوان (خط‌کش پیکسلی: کف ۲۹۰، سقف ۵۸۰)
	[ 'عنوان خالی ← no_title', 'title', [ 'title' => '', 'title_len' => 0, 'title_px' => 0 ], [], [ 'no_title' ] ],
	[ 'عنوان کوتاه (۱۰۰px) ← title_short', 'title', [ 'title_px' => 100, 'title_len' => 10 ], [], [ 'title_short' ] ],
	[ 'عنوان بلند (۷۰۰px) ← title_long', 'title', [ 'title_px' => 700, 'title_len' => 90 ], [], [ 'title_long' ] ],
	[ 'عنوان درست (۴۰۰px) ← هیچ', 'title', [], [], [] ],
	[ 'عنوان کوتاه در صفحه‌بندی /page/2/ ← هیچ (عمدی آرشیو)', 'title', [ 'path' => '/blog/page/2/', 'title_px' => 100, 'title_len' => 10 ], [], [] ],
	[ 'ردیف قدیمی بی‌پیکسل (title_px = 0) ← هیچ', 'title', [ 'title_px' => 0 ], [], [] ],
	// ── متا و robots
	[ 'متای خالی ← no_meta', 'no_meta', [ 'meta_len' => 0 ], [], [ 'no_meta' ] ],
	[ 'متا دارد ← هیچ', 'no_meta', [], [], [] ],
	[ 'متای خالی در /page/2/ ← هیچ', 'no_meta', [ 'path' => '/blog/page/2/', 'meta_len' => 0 ], [], [] ],
	[ 'nosnippet ← no_snippet', 'no_snippet', [ 'no_snippet' => 1 ], [], [ 'no_snippet' ] ],
	[ 'بدون nosnippet ← هیچ', 'no_snippet', [], [], [] ],
	// ── تیترها
	[ 'H1 خالی ← no_h1', 'no_h1', [ 'h1' => '' ], [], [ 'no_h1' ] ],
	[ 'H1 دارد ← هیچ', 'no_h1', [], [], [] ],
	[ 'سه H1 ← multi_h1', 'multi_h1', [ 'h1_count' => 3 ], [], [ 'multi_h1' ] ],
	[ 'یک H1 ← هیچ', 'multi_h1', [], [], [] ],
	// ── تکراری و مشابه
	[ 'عنوان در ۲ صفحه ← dup_title', 'dup_title', [ 'title' => 'عنوان تکراری', 'title_len' => 12 ], [ 'titles' => [ 'عنوان تکراری' => 2 ] ], [ 'dup_title' ] ],
	[ 'عنوان در ۱ صفحه ← هیچ', 'dup_title', [ 'title' => 'عنوان تکراری', 'title_len' => 12 ], [ 'titles' => [ 'عنوان تکراری' => 1 ] ], [] ],
	[ 'عنوان تکراری در /page/2/ ← هیچ', 'dup_title', [ 'path' => '/b/page/2/', 'title' => 'عنوان تکراری', 'title_len' => 12 ], [ 'titles' => [ 'عنوان تکراری' => 2 ] ], [] ],
	[ 'H1 در ۲ صفحه ← dup_h1', 'dup_h1', [ 'h1' => 'تیتر' ], [ 'h1s' => [ 'تیتر' => 2 ] ], [ 'dup_h1' ] ],
	[ 'H1 در ۱ صفحه ← هیچ', 'dup_h1', [ 'h1' => 'تیتر' ], [ 'h1s' => [ 'تیتر' => 1 ] ], [] ],
	[ 'شریک مشابه ← near_dup', 'near_dup', [ 'path' => '/a/' ], [ 'similar' => [ '/a/' => [ '/b/' => [ 'عنوان' ] ] ], 'by_path' => [ '/b/' => [ 'title' => 'ب', 'h1' => 'ب' ] ] ], [ 'near_dup' ] ],
	[ 'بی‌شریک ← هیچ', 'near_dup', [ 'path' => '/a/' ], [], [] ],
	// ── سرعت و لینک ورودی
	[ 'پاسخ ۳ ثانیه ← slow', 'slow', [ 'resp_ms' => 3000 ], [], [ 'slow' ] ],
	[ 'پاسخ ۲۰۰ms ← هیچ', 'slow', [], [], [] ],
	[ 'بدون لینک ورودی ← orphan', 'inlinks', [ 'inlinks' => 0 ], [], [ 'orphan' ] ],
	[ 'یک لینک ورودی ← low_inlinks', 'inlinks', [ 'inlinks' => 1, 'text_links' => 1 ], [], [ 'low_inlinks' ] ],
	[ 'لینک ورودی کافی ← هیچ', 'inlinks', [ 'inlinks' => 9 ], [], [] ],
	[ 'صفحه اصلی بدون لینک ورودی ← هیچ', 'inlinks', [ 'path' => '/', 'inlinks' => 0 ], [], [] ],
	[ '/page/2/ با لینک ورودی کم ← هیچ', 'inlinks', [ 'path' => '/b/page/2/', 'inlinks' => 1 ], [], [] ],
	[ 'عمق ۶ ← deep', 'depth', [ 'depth' => 6 ], [], [ 'deep' ] ],
	[ 'عمق ۲ ← هیچ', 'depth', [ 'depth' => 2 ], [], [] ],
	[ 'نارسیده با لینک ورودی ← island', 'depth', [ 'depth' => HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE, 'inlinks' => 2 ], [], [ 'island' ] ],
	[ 'نارسیده بی‌لینک (orphan است نه island) ← هیچ', 'depth', [ 'depth' => HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE, 'inlinks' => 0 ], [], [] ],
	// ── لینک‌ها و آدرس‌ها
	[ 'لینک به صفحه خراب ← link_broken', 'link_targets', [ 'path' => '/a/' ], [ 'bad' => [ '/a/' => [ 'link_broken' => [ [ 'path' => '/x/', 'note' => 'کد 404' ] ] ] ] ], [ 'link_broken' ] ],
	[ 'لینک سالم ← هیچ', 'link_targets', [ 'path' => '/a/' ], [], [] ],
	[ 'لینک http در سایت https ← http_link', 'http_link', [ 'http_links' => 3 ], [], [ 'http_link' ] ],
	[ 'بدون لینک http ← هیچ', 'http_link', [], [], [] ],
	[ 'canonical خالی ← no_canonical', 'no_canonical', [ 'canonical' => '' ], [], [ 'no_canonical' ] ],
	[ 'canonical دارد ← هیچ', 'no_canonical', [], [], [] ],
	[ 'canonical به صفحه ۴۰۴ ← canonical_bad', 'canonical_bad', [ 'canonical' => '/gone/' ], [ 'by_path' => [ '/gone/' => [ 'status' => 404, 'reason' => '' ] ] ], [ 'canonical_bad' ] ],
	[ 'canonical به صفحه noindex ← canonical_bad', 'canonical_bad', [ 'canonical' => '/n/' ], [ 'by_path' => [ '/n/' => [ 'status' => 200, 'reason' => 'Noindex' ] ] ], [ 'canonical_bad' ] ],
	[ 'canonical به صفحه سالم دیگر ← هیچ', 'canonical_bad', [ 'canonical' => '/ok/' ], [ 'by_path' => [ '/ok/' => [ 'status' => 200, 'reason' => '' ] ] ], [] ],
	[ 'canonical به خودش ← هیچ', 'canonical_bad', [], [], [] ],
	[ 'ردیف قدیمی (words = -1) ← canonical_bad نمی‌دهد', 'canonical_bad', [ 'words' => -1, 'canonical' => '/gone/' ], [ 'by_path' => [ '/gone/' => [ 'status' => 404, 'reason' => '' ] ] ], [] ],
	// ── نقشه سایت
	[ 'در نقشه ولی ۴۰۴ ← sitemap_bad', 'sitemap_bad', [ 'status' => 404 ], [ 'sitemap' => [ '/ck-page/' => true ] ], [ 'sitemap_bad' ] ],
	[ 'در نقشه ولی noindex ← sitemap_bad', 'sitemap_bad', [ 'indexable' => 0, 'reason' => 'Noindex' ], [ 'sitemap' => [ '/ck-page/' => true ] ], [ 'sitemap_bad' ] ],
	[ 'در نقشه و سالم ← هیچ', 'sitemap_bad', [], [ 'sitemap' => [ '/ck-page/' => true ] ], [] ],
	[ 'نقشه سایت ناشناخته ← sitemap_bad نمی‌دهد', 'sitemap_bad', [ 'status' => 404 ], [], [] ],
	[ 'سالم ولی در نقشه نیست ← not_in_sitemap', 'not_in_sitemap', [], [ 'sitemap' => [ '/other/' => true ] ], [ 'not_in_sitemap' ] ],
	[ 'در نقشه است ← هیچ', 'not_in_sitemap', [], [ 'sitemap' => [ '/ck-page/' => true ] ], [] ],
	[ 'نقشه سایت ناشناخته ← not_in_sitemap نمی‌دهد', 'not_in_sitemap', [], [], [] ],
	// ── محتوا
	[ 'متن ۵۰ کلمه (صفحه شناخته‌شده) ← thin', 'thin', [ 'words' => 50 ], [], [ 'thin' ] ],
	[ 'متن ۴۰۰ کلمه ← هیچ', 'thin', [], [], [] ],
	[ 'متن کم در دسته ← هیچ', 'thin', [ 'words' => 50, 'kind' => 'category' ], [], [] ],
	[ 'متن کم در صفحه اصلی ← هیچ', 'thin', [ 'path' => '/', 'words' => 50 ], [], [] ],
	[ 'دو تصویر بدون alt ← img_alt', 'images', [ 'img_noalt' => 2 ], [], [ 'img_alt' ] ],
	[ 'یک alt خالی ← img_empty (فقط «بررسی کنید»)', 'images', [ 'img_empty' => 1 ], [], [ 'img_empty' ] ],
	[ 'تصویر سالم ← هیچ', 'images', [], [], [] ],
	// ── وضعیت (ریدایرکت، خطا، noindex)
	[ 'ریدایرکت با لینک داخلی ← redirect', 'status', [ 'status' => 301, 'redirect_to' => '/x/', 'inlinks' => 2 ], [], [ 'redirect' ] ],
	[ 'ریدایرکت بی‌لینک داخلی ← هیچ (فقط نمایش گوگل ایراد نیست)', 'status', [ 'status' => 301, 'redirect_to' => '/x/', 'inlinks' => 0 ], [], [] ],
	[ 'ریدایرکت به ریدایرکت ← redirect_chain', 'status', [ 'status' => 301, 'redirect_to' => '/b/', 'inlinks' => 0 ], [ 'by_path' => [ '/b/' => [ 'status' => 301 ] ] ], [ 'redirect_chain' ] ],
	[ 'صفحه ۴۰۴ ← broken', 'status', [ 'status' => 404 ], [], [ 'broken' ] ],
	[ 'noindex ← noindex', 'status', [ 'indexable' => 0, 'reason' => 'Noindex' ], [], [ 'noindex' ] ],
];

echo "=== «دارد» و «ندارد» برای هر ایراد\n";
foreach ( $cases as [ $name, $topic, $row, $ctx, $expect ] ) {
	$codes = hodima_ck_codes( $S . $topic, hodima_ck_row( $row ), hodima_ck_ctx( $ctx ) );
	hodima_ck( $name, $codes === $expect, 'انتظار: ' . wp_json_encode( $expect ) . ' واقعی: ' . wp_json_encode( $codes ) );
}

echo "=== ساختار: هر کد کاتالوگ یک صاحب دارد\n";
$catalog = array_keys( hodima_seo_advisor_issue_catalog() );
$owners  = [];
foreach ( hodima_seo_advisor_checks() as $topic => $check ) {
	foreach ( $check['codes'] as $code ) {
		$owners[ $code ][] = $topic;
	}
	hodima_ck( "تابع «{$topic}» وجود دارد", function_exists( $check['fn'] ) );
	hodima_ck( "شرط اجرای «{$topic}» شناخته‌شده است", in_array( $check['when'], [ 'always', 'live', 'known', 'dead' ], true ) );
}
$no_owner = array_values( array_diff( $catalog, array_keys( $owners ) ) );
$dangling = array_values( array_diff( array_keys( $owners ), $catalog ) );
$multi    = array_keys( array_filter( $owners, static fn( array $o ): bool => count( $o ) > 1 ) );
hodima_ck( 'هر کد کاتالوگ در ثبت مرکزی صاحب دارد', [] === $no_owner, 'بدون صاحب: ' . implode( '، ', $no_owner ) );
hodima_ck( 'ثبت مرکزی کد ناشناخته نمی‌سازد', [] === $dangling, 'ناشناخته: ' . implode( '، ', $dangling ) );
hodima_ck( 'هر کد فقط یک صاحب دارد', [] === $multi, 'چندصاحبه: ' . implode( '، ', $multi ) );

echo "=== ساختار: گروه‌ها و بخش‌های نمره به کد ناشناخته اشاره نمی‌کنند\n";
$bad_group = [];
foreach ( HODIMA_SEO_ADVISOR_CRAWL_GROUPS as $group => $members ) {
	foreach ( array_diff( $members, $catalog ) as $code ) {
		$bad_group[] = $group . ':' . $code;
	}
}
$bad_cat = [];
foreach ( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES as $key => $category ) {
	foreach ( array_diff( $category['codes'], $catalog ) as $code ) {
		$bad_cat[] = $key . ':' . $code;
	}
}
hodima_ck( 'گروه‌های چیپ فقط کد کاتالوگ دارند', [] === $bad_group, implode( '، ', $bad_group ) );
hodima_ck( 'بخش‌های نمره فقط کد کاتالوگ دارند', [] === $bad_cat, implode( '، ', $bad_cat ) );

$grouped = array_unique( array_merge( ...array_values( HODIMA_SEO_ADVISOR_CRAWL_GROUPS ) ) );
$scored  = array_unique( array_merge( ...array_map( static fn( array $c ): array => $c['codes'], array_values( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES ) ) ) );
// شکاف شناخته‌شده: کد بی‌گروه در فیلتر چیپ‌های «کارها» دیده نمی‌شود (img_empty از SEO 3.4.9؛ فقط در «همه ایرادها» هست). رفتار
// 3.6.1 عوض نشد؛ در انتشار بعدی به گروه «تصویر و محتوا» می‌رود و این خط به hodima_ck( … ) رد‌شدنی تبدیل می‌شود.
$ungrouped = array_values( array_diff( $catalog, $grouped ) );
echo '  ℹ کدهای بی‌گروه چیپ (شکاف شناخته‌شده): ' . ( $ungrouped ? implode( '، ', $ungrouped ) : 'هیچ' ) . "\n";
hodima_ck( 'شکاف بی‌گروه فقط همان شناخته‌شده است (کد تازه بی‌گروه نشود)', [] === array_diff( $ungrouped, [ 'img_empty' ] ), implode( '، ', $ungrouped ) );

// شکاف شناخته‌شده: کدهایی که در هیچ بخش نمره نیستند (img_empty «بررسی کنید» عمدا بی‌نمره است)؛ کد تازه بی‌نمره رد می‌شود
$unscored = array_values( array_diff( $catalog, $scored ) );
echo '  ℹ کدهای بی‌بخش نمره: ' . ( $unscored ? implode( '، ', $unscored ) : 'هیچ' ) . "\n";
hodima_ck( 'کد تازه بی‌بخش نمره نیست (جز img_empty عمدی)', [] === array_diff( $unscored, [ 'img_empty' ] ), implode( '، ', $unscored ) );

echo "\n";
if ( $failed ) {
	echo "✘ {$failed} آزمون رد شد ({$passed} قبول)\n";
	exit( 1 );
}
echo "✔ همه {$passed} آزمون قاعده‌های ایراد قبول\n";
