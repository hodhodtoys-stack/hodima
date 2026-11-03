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
	[ 'متای خالی در صفحه پربازدید (۱۵۰۰ نمایش) ← no_meta', 'no_meta', [ 'meta_len' => 0, 'impressions' => 1500 ], [], [ 'no_meta' ] ],
	[ 'متای خالی در صفحه اصلی ← no_meta', 'no_meta', [ 'path' => '/', 'meta_len' => 0 ], [], [ 'no_meta' ] ],
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
	[ 'عنوان تکراری با «ي» عربی و رقم فارسی ← dup_title (کلید یکسان‌سازی‌شده، SEO 3.6.3)', 'dup_title', [ 'title' => 'کيف ۱۲ جيب', 'title_len' => 11 ], [ 'titles' => [ hodima_seo_advisor_dup_key( 'کیف 12 جیب' ) => 2 ] ], [ 'dup_title' ] ],
	[ 'H1 تکراری با ک/ک عربی و نیم‌فاصله ← dup_h1', 'dup_h1', [ 'h1' => 'كلیپس‌مو' ], [ 'h1s' => [ hodima_seo_advisor_dup_key( 'کلیپس مو' ) => 2 ] ], [ 'dup_h1' ] ],
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
	[ 'سایت پنهان (blog_public=0) + صفحه اصلی ← site_hidden', 'site_hidden', [ 'path' => '/' ], [ 'hidden' => true ], [ 'site_hidden' ] ],
	[ 'سایت پنهان + صفحه دیگر ← هیچ (فقط روی صفحه اصلی می‌آید)', 'site_hidden', [ 'path' => '/a/' ], [ 'hidden' => true ], [] ],
	[ 'سایت دیده‌شدنی + صفحه اصلی ← هیچ', 'site_hidden', [ 'path' => '/' ], [ 'hidden' => false ], [] ],
	[ 'سایت پنهان ← noindex جداجدای صفحه‌ها نمی‌آید (همه از همان یک تنظیم‌اند)', 'status', [ 'path' => '/a/', 'indexable' => 0, 'reason' => 'Noindex' ], [ 'hidden' => true ], [] ],
	[ 'سایت دیده‌شدنی ← noindex صفحه همچنان noindex', 'status', [ 'path' => '/a/', 'indexable' => 0, 'reason' => 'Noindex' ], [ 'hidden' => false ], [ 'noindex' ] ],
	[ 'سایت پنهان ← canonical به صفحه دیگر همچنان canonicalised', 'status', [ 'path' => '/a/', 'indexable' => 0, 'reason' => 'Canonicalised', 'canonical' => '/b/' ], [ 'hidden' => true ], [ 'canonicalised' ] ],
	[ 'canonical فقط در body (نشانه !body) ← canonical_in_body (Google: فقط در head پذیرفته می‌شود)', 'canonical_in_body', [ 'canonical' => '!body' ], [], [ 'canonical_in_body' ] ],
	[ 'canonical در head ← canonical_in_body نمی‌دهد', 'canonical_in_body', [], [], [] ],
	[ 'بدون canonical ← canonical_in_body نمی‌دهد', 'canonical_in_body', [ 'canonical' => '' ], [], [] ],
	[ 'canonical فقط در body ← no_canonical نمی‌دهد (ایراد اختصاصی خودش را دارد)', 'no_canonical', [ 'canonical' => '!body' ], [], [] ],
	[ 'canonical فقط در body ← canonical_bad نمی‌دهد', 'canonical_bad', [ 'canonical' => '!body' ], [], [] ],
	[ 'توضیح متای یکسان در ۲ صفحه ← dup_meta', 'dup_meta', [ 'meta_hash' => 'abc123abc123' ], [ 'metas' => [ 'abc123abc123' => 2 ] ], [ 'dup_meta' ] ],
	[ 'توضیح متای یگانه ← هیچ', 'dup_meta', [ 'meta_hash' => 'abc123abc123' ], [ 'metas' => [ 'abc123abc123' => 1 ] ], [] ],
	[ 'ردیف قدیمی بی‌hash (متا نامعلوم) ← dup_meta نمی‌دهد', 'dup_meta', [ 'meta_hash' => '' ], [ 'metas' => [ '' => 5 ] ], [] ],
	[ 'توضیح متای تکراری در /page/2/ ← هیچ', 'dup_meta', [ 'path' => '/b/page/2/', 'meta_hash' => 'abc123abc123' ], [ 'metas' => [ 'abc123abc123' => 3 ] ], [] ],
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
	[ 'متن کم در صفحه ویدیو ← هیچ (ذاتا متن کم دارد)', 'thin', [ 'words' => 50, 'kind' => 'video' ], [], [] ],
	[ 'دو تصویر بدون alt ← img_alt', 'images', [ 'img_noalt' => 2 ], [], [ 'img_alt' ] ],
	[ 'یک alt خالی ← img_empty (فقط «بررسی کنید»)', 'images', [ 'img_empty' => 1 ], [], [ 'img_empty' ] ],
	[ 'تصویر سالم ← هیچ', 'images', [], [], [] ],
	// ── وضعیت (ریدایرکت، خطا، noindex)
	[ 'ریدایرکت با لینک داخلی ← redirect', 'status', [ 'status' => 301, 'redirect_to' => '/x/', 'inlinks' => 2 ], [], [ 'redirect' ] ],
	[ 'ریدایرکت بی‌لینک داخلی ← هیچ (فقط نمایش گوگل ایراد نیست)', 'status', [ 'status' => 301, 'redirect_to' => '/x/', 'inlinks' => 0 ], [], [] ],
	[ 'ریدایرکت به ریدایرکت (۲ پرش) ← redirect_chain', 'status', [ 'status' => 301, 'redirect_to' => '/b/', 'inlinks' => 0 ], [ 'by_path' => [ '/ck-page/' => [ 'status' => 301, 'redirect_to' => '/b/' ], '/b/' => [ 'status' => 301, 'redirect_to' => '/c/' ], '/c/' => [ 'status' => 200, 'redirect_to' => '' ] ] ], [ 'redirect_chain' ] ],
	[ 'ریدایرکت مستقیم به صفحه ۲۰۰ (۱ پرش) ← هیچ (فقط با لینک داخلی «redirect»)', 'status', [ 'status' => 301, 'redirect_to' => '/c/', 'inlinks' => 0 ], [ 'by_path' => [ '/ck-page/' => [ 'status' => 301, 'redirect_to' => '/c/' ], '/c/' => [ 'status' => 200, 'redirect_to' => '' ] ] ], [] ],
	[ 'حلقه ریدایرکت (a ← b ← a) ← redirect_loop (نه redirect_chain)', 'status', [ 'status' => 301, 'redirect_to' => '/b/', 'inlinks' => 0 ], [ 'by_path' => [ '/ck-page/' => [ 'status' => 301, 'redirect_to' => '/b/' ], '/b/' => [ 'status' => 301, 'redirect_to' => '/ck-page/' ] ] ], [ 'redirect_loop' ] ],
	[ 'ریدایرکت به خودش ← redirect_loop', 'status', [ 'status' => 301, 'redirect_to' => '/ck-page/', 'inlinks' => 0 ], [ 'by_path' => [ '/ck-page/' => [ 'status' => 301, 'redirect_to' => '/ck-page/' ] ] ], [ 'redirect_loop' ] ],
	[ 'صفحه ۵۰۳ ← server_error (نه broken)', 'status', [ 'status' => 503 ], [], [ 'server_error' ] ],
	[ 'صفحه ۴۰۴ ← broken (نه server_error)', 'status', [ 'status' => 404 ], [], [ 'broken' ] ],
	[ 'صفحه ۴۱۰ ← broken', 'status', [ 'status' => 410 ], [], [ 'broken' ] ],
	[ 'canonical به صفحه دیگر (reason = Canonicalised) ← canonicalised (نه noindex)', 'status', [ 'indexable' => 0, 'reason' => 'Canonicalised', 'canonical' => '/ok/' ], [], [ 'canonicalised' ] ],
	[ 'noindex معمولی (reason = Noindex) ← noindex (نه canonicalised)', 'status', [ 'indexable' => 0, 'reason' => 'Noindex' ], [], [ 'noindex' ] ],
	[ 'canonical فقط در صفحه سیستمی ← هیچ (زیرمسیر حساب)', 'status', [ 'path' => '/my-account/lost-password/', 'indexable' => 0, 'reason' => 'Canonicalised', 'canonical' => '/my-account/' ], [], [] ],
	[ 'فراموشی رمز با canonical به /my-account/ (noindex) ← canonical_bad نمی‌دهد (۱۲ آبان ۱۴۰۵، سایت واقعی)', 'canonical_bad', [ 'path' => '/my-account/lost-password/', 'canonical' => '/my-account/' ], [ 'by_path' => [ '/my-account/' => [ 'status' => 200, 'reason' => 'Noindex' ] ] ], [] ],
	[ 'canonical به صفحه noindex غیرسیستمی ← همچنان canonical_bad', 'canonical_bad', [ 'path' => '/p1/', 'canonical' => '/n2/' ], [ 'by_path' => [ '/n2/' => [ 'status' => 200, 'reason' => 'Noindex' ] ] ], [ 'canonical_bad' ] ],
	[ 'صفحه ۴۰۴ ← broken', 'status', [ 'status' => 404 ], [], [ 'broken' ] ],
	[ 'noindex ← noindex', 'status', [ 'indexable' => 0, 'reason' => 'Noindex' ], [], [ 'noindex' ] ],
	[ 'noindex در صفحه سیستمی حساب کاربری ← هیچ (۱۲ آبان ۱۴۰۵: /my-account/lost-password/ ایراد کاذب بود)', 'status', [ 'path' => '/my-account/lost-password/', 'indexable' => 0, 'reason' => 'Noindex' ], [], [] ],
	[ 'noindex در /my-account/ ← هیچ', 'status', [ 'path' => '/my-account/', 'indexable' => 0, 'reason' => 'Noindex' ], [], [] ],
	[ 'noindex در /cart/ ← هیچ', 'status', [ 'path' => '/cart/', 'indexable' => 0, 'reason' => 'Noindex' ], [], [] ],
	[ 'noindex در صفحه عادی ← noindex', 'status', [ 'path' => '/my-accounting-guide/', 'indexable' => 0, 'reason' => 'Noindex' ], [], [ 'noindex' ] ],
];

echo "=== «دارد» و «ندارد» برای هر ایراد\n";
foreach ( $cases as [ $name, $topic, $row, $ctx, $expect ] ) {
	$codes = hodima_ck_codes( $S . $topic, hodima_ck_row( $row ), hodima_ck_ctx( $ctx ) );
	hodima_ck( $name, $codes === $expect, 'انتظار: ' . wp_json_encode( $expect ) . ' واقعی: ' . wp_json_encode( $codes ) );
}

echo "=== سطح هر ایراد برای همان صفحه (SEO 3.6.2) و سطح کاتالوگ طبق Google Search Central\n";
$lvl = static fn( array $over ): array => array_map( static fn( array $i ): string => (string) ( $i['level'] ?? '-' ), hodima_seo_advisor_check_no_meta( hodima_ck_row( $over ), hodima_ck_ctx() ) );
hodima_ck( 'بدون متا: صفحه کم‌بازدید سطح info می‌گیرد', [ 'info' ] === $lvl( [ 'meta_len' => 0, 'impressions' => 50 ] ) );
hodima_ck( 'بدون متا: صفحه پربازدید و صفحه اصلی سطح کاتالوگ را می‌گیرند (کلید level ندارند)', [ '-' ] === $lvl( [ 'meta_len' => 0, 'impressions' => 1500 ] ) && [ '-' ] === $lvl( [ 'path' => '/', 'meta_len' => 0 ] ) );
$lv = static fn( string $code ): string => hodima_seo_advisor_crawl_issue_info( $code )['level'];
hodima_ck( 'سطح کاتالوگ: عنوان کوتاه و بلند info (گوگل حداقل/حداکثر نمی‌گذارد)، بدون متا warn (فقط صفحه‌های مهم)', [ 'info', 'info', 'warn' ] === [ $lv( 'title_short' ), $lv( 'title_long' ), $lv( 'no_meta' ) ] );
$sc_info = hodima_seo_advisor_score( [ [ 'path' => '/a/', 'issues' => [ [ 'code' => 'no_meta', 'level' => 'info' ] ] ] ], 10 );
$sc_warn = hodima_seo_advisor_score( [ [ 'path' => '/a/', 'issues' => [ [ 'code' => 'no_meta' ] ] ] ], 10 );
hodima_ck( 'نمره: ایراد با level=info برای همان صفحه در نمره نمی‌آید (بخش «عنوان و متا» ۱۰۰ و بی‌صفحه در برابر ۹۰ برای کهربایی؛ SEO 3.6.4)', 100 === ( $sc_info['categories']['cat-meta']['score'] ?? -1 ) && 0 === ( $sc_info['categories']['cat-meta']['pages'] ?? -1 ) && 90 === ( $sc_warn['categories']['cat-meta']['score'] ?? -1 ), wp_json_encode( [ $sc_info['categories']['cat-meta'] ?? null, $sc_warn['categories']['cat-meta'] ?? null ] ) );
$sc_row2 = static fn( int $i, array $codes ): array => [ 'path' => "/q{$i}/", 'issues' => array_map( static fn( string $c ): array => [ 'code' => $c ], $codes ) ];
$sc_wide = hodima_seo_advisor_score( array_map( static fn( int $i ): array => $sc_row2( $i, [ 'no_h1' ] ), range( 1, 100 ) ), 100 );
hodima_ck( 'نمره: یک ایراد فراگیر (بدون H1 روی ۱۰۰ از ۱۰۰ صفحه) حداکثر ۲۵ امتیاز بخش را کم می‌کند، نه همه را (قبلا بخش ۰ و نمره کل ۸۰)', 75 === ( $sc_wide['categories']['cat-content']['score'] ?? -1 ) && 95 === $sc_wide['score'], wp_json_encode( $sc_wide ) );
$sc_info_all = hodima_seo_advisor_score( array_map( static fn( int $i ): array => $sc_row2( $i, [ 'low_inlinks', 'multi_h1', 'title_short', 'deep', 'island' ] ), range( 1, 100 ) ), 100 );
hodima_ck( 'نمره: اطلاع‌های فراگیر نمره را کم نمی‌کنند (کل ۱۰۰؛ قبلا ۸۴)', 100 === $sc_info_all['score'] && [] === $sc_info_all['top'], wp_json_encode( $sc_info_all ) );
hodima_ck( 'نمره: ثابت‌ها (ضریب اطلاع صفر، سقف هر ایراد ۲۵)', 0.0 === HODIMA_SEO_ADVISOR_SCORE_WEIGHTS['info'] && 25.0 === HODIMA_SEO_ADVISOR_SCORE_CODE_CAP );

hodima_ck( 'کلید تکراری: ي/ی، ك/ک، رقم فارسی/عربی/لاتین، نیم‌فاصله و حروف بزرگ یکی می‌شوند و عنوان‌های متفاوت یکی نمی‌شوند', hodima_seo_advisor_dup_key( 'كيف ۱۲ Pack' ) === hodima_seo_advisor_dup_key( 'کیف 12 pack' ) && hodima_seo_advisor_dup_key( 'کیف ۱۲' ) !== hodima_seo_advisor_dup_key( 'کیف ۱۳' ) );
$lv2 = static fn( string $code ): string => hodima_seo_advisor_crawl_issue_info( $code )['level'];
hodima_ck( 'سطح کاتالوگ (قانون‌نامه): H1 تکراری، چند H1، عنوان مشابه، لینک ورودی کم، جزیره = info؛ بدون لینک ورودی = warn؛ عنوان تکراری warn', [ 'info', 'info', 'info', 'info', 'info', 'warn', 'warn' ] === array_map( $lv2, [ 'dup_h1', 'multi_h1', 'near_dup', 'low_inlinks', 'island', 'orphan', 'dup_title' ] ) );
hodima_ck( 'سطح زنجیره: ۴ پرش info (بدون کلید level)، ۵ پرش warn', [ [], [ 'warn' ] ] === array_map( static function ( int $n ): array {
	$by = [];
	for ( $i = 1; $i <= $n; ++$i ) {
		$by[ "/h{$i}/" ] = [ 'status' => 301, 'redirect_to' => '/h' . ( $i + 1 ) . '/' ];
	}
	$by[ '/h' . ( $n + 1 ) . '/' ] = [ 'status' => 200, 'redirect_to' => '' ];
	$out = hodima_seo_advisor_check_status( hodima_ck_row( [ 'path' => '/h1/', 'status' => 301, 'redirect_to' => '/h2/', 'inlinks' => 0 ] ), hodima_ck_ctx( [ 'by_path' => $by ] ) );
	return array_values( array_filter( array_map( static fn( array $i ): string => (string) ( $i['level'] ?? '' ), $out ) ) );
}, [ 4, 5 ] ) );
hodima_ck( 'ردیابی ریدایرکت: مقصد ناشناخته (صفحه خزیده نشده) زنجیره و حلقه نمی‌سازد', 1 === hodima_seo_advisor_redirect_trace( '/a/', [ '/a/' => [ 'status' => 301, 'redirect_to' => '/unknown/' ] ] )['hops'] && false === hodima_seo_advisor_redirect_trace( '/a/', [ '/a/' => [ 'status' => 301, 'redirect_to' => '/unknown/' ] ] )['loop'] );

$reds = array_keys( array_filter( hodima_seo_advisor_issue_catalog(), static fn( array $rec ): bool => 'bad' === $rec['level'] ) );
sort( $reds );
hodima_ck( 'سطح کاتالوگ طبق قانون‌نامه (۳.۶.۷): فقط redirect_loop، server_error و site_hidden قرمزند', [ 'redirect_loop', 'server_error', 'site_hidden' ] === $reds, wp_json_encode( $reds ) );
hodima_ck( 'سطح کاتالوگ: لینک به ریدایرکت و بدون canonical اطلاع؛ لینک خراب، canonical اشتباه، نقشه سایت خراب، بدون عنوان و متن زیر عنوان ممنوع کهربایی', [ 'info', 'info', 'warn', 'warn', 'warn', 'warn', 'warn' ] === array_map( static fn( string $c ): string => hodima_seo_advisor_crawl_issue_info( $c )['level'], [ 'link_redirect', 'no_canonical', 'link_broken', 'canonical_bad', 'sitemap_bad', 'no_title', 'no_snippet' ] ) );

echo "=== لینک‌های غیر صفحه: پروتکل غیر http (اپ‌ها) صفحه نیست (۱۲ آبان ۱۴۰۵: snapp:// و tapsi:// «۴۰۴» گزارش می‌شد)\n";
$res_base = 'https://hodima.test/contact-us/';
foreach ( [ 'snapp://open', 'tapsi://open', 'tg://resolve?domain=x', 'viber://chat', 'intent://scan#Intent;end', 'mailto:a@b.c', 'tel:+98', 'javascript:void(0)', 'whatsapp://send', '#top', '' ] as $href ) {
	hodima_ck( "resolve({$href}) ← null (لینک صفحه نیست)", null === hodima_seo_advisor_crawler_resolve( $href, $res_base ) );
}
hodima_ck( 'resolve: آدرس http(s) و مسیر نسبی و مطلق همچنان درست‌اند', [ 'https://hodima.test/a/', 'https://hodima.test/x/', 'https://hodima.test/contact-us/foo/bar', 'http://other.test/p/' ] === [ hodima_seo_advisor_crawler_resolve( 'https://hodima.test/a/', $res_base ), hodima_seo_advisor_crawler_resolve( '/x/', $res_base ), hodima_seo_advisor_crawler_resolve( 'foo/bar', $res_base ), hodima_seo_advisor_crawler_resolve( 'http://other.test/p/', $res_base ) ] );

echo "=== خزنده: canonical فقط در head پذیرفته می‌شود و hash توضیح متا\n";
$doc = static fn( string $head, string $body = '<p>x</p>' ): array => hodima_seo_advisor_crawler_parse( '<!doctype html><html><head><title>t</title>' . $head . '</head><body>' . $body . '</body></html>', 'https://hodima.test/p/' );
$p_head = $doc( '<link rel="canonical" href="https://hodima.test/a/">' );
$p_body = $doc( '', '<div><link rel="canonical" href="https://hodima.test/a/"></div>' );
$p_both = $doc( '<link rel="canonical" href="https://hodima.test/a/">', '<link rel="canonical" href="https://hodima.test/b/">' );
$p_none = $doc( '' );
hodima_ck( 'canonical در head: خوانده می‌شود و canonical_body نیست', 'https://hodima.test/a/' === $p_head['canonical'] && false === $p_head['canonical_body'] );
hodima_ck( 'canonical فقط در body: canonical خالی و canonical_body درست', '' === $p_body['canonical'] && true === $p_body['canonical_body'] );
hodima_ck( 'canonical هم در head هم در body: head معتبر است', 'https://hodima.test/a/' === $p_both['canonical'] && false === $p_both['canonical_body'] );
hodima_ck( 'بدون canonical: هیچ‌کدام', '' === $p_none['canonical'] && false === $p_none['canonical_body'] );
$row_of = static fn( array $parsed ): array => hodima_seo_advisor_crawler_page_row( '/p/', 200, 100, $parsed, '', '' );
hodima_ck( 'ردیف صفحه: canonical فقط در body ← «!body»؛ در head ← مسیر؛ هیچ ← خالی', [ '!body', '/a/', '' ] === [ $row_of( $p_body )['canonical'], $row_of( $p_head )['canonical'], $row_of( $p_none )['canonical'] ] );
$m1 = $row_of( $doc( '<meta name="description" content="توضیح یکسان کيف ۱۲">' ) )['meta_hash'];
$m2 = $row_of( $doc( '<meta name="description" content="توضیح  یکسان کیف 12">' ) )['meta_hash'];
$m3 = $row_of( $doc( '<meta name="description" content="توضیح دیگر">' ) )['meta_hash'];
hodima_ck( 'meta_hash: املای عربی/فارسی و فاصله یکی می‌شود؛ متن دیگر فرق می‌کند؛ بدون متا خالی', $m1 === $m2 && $m1 !== $m3 && '' !== $m1 && '' === $row_of( $p_none )['meta_hash'] );

echo "=== ثابت‌ها (invariant): پیش‌نویس باید از تشخیص همان ایراد رد شود\n";
$limit = hodima_seo_px_limit( 'title' );
$posts = array_merge(
	get_posts( [ 'post_type' => [ 'post', 'page' ], 'post_status' => 'publish', 'numberposts' => 8 ] ),
	post_type_exists( 'product' ) ? get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 4 ] ) : []
);
$bad_short = [];
$bad_long  = [];
$checked   = 0;
foreach ( $posts as $post_obj ) {
	$path = hodima_seo_advisor_path_key( (string) get_permalink( $post_obj ) );
	$d    = hodima_seo_advisor_draft_title( $path, 'title_short', '' );
	if ( '' !== $d && ( hodima_seo_text_px( $d, 'title' ) < $limit['min'] || hodima_seo_text_px( $d, 'title' ) > $limit['max'] ) ) {
		$bad_short[] = $path . ' ← ' . hodima_seo_text_px( $d, 'title' ) . 'px';
	}
	$dl = hodima_seo_advisor_draft_title( $path, 'title_long', str_repeat( 'گیره مو مرواریدی ', 12 ) );
	if ( '' !== $dl && hodima_seo_text_px( $dl, 'title' ) > $limit['max'] ) {
		$bad_long[] = $path . ' ← ' . hodima_seo_text_px( $dl, 'title' ) . 'px';
	}
	++$checked;
}
hodima_ck( "پیش‌نویس «عنوان کوتاه» در {$checked} صفحه واقعی ابزار تست: یا خالی است یا بین کف و سقف (باگ /contact-us/)", [] === $bad_short, implode( '، ', $bad_short ) );
$kept = 0;
$lost = [];
foreach ( $posts as $post_obj ) {
	$path = hodima_seo_advisor_path_key( (string) get_permalink( $post_obj ) );
	$own  = 'خرید عمده گیره شمعی گل ۱۲ تایی';
	$d2   = hodima_seo_advisor_draft_title( $path, 'title_short', $own );
	if ( '' !== $d2 && ! str_starts_with( $d2, $own ) ) {
		$lost[] = $path . ' ← ' . $d2;
	}
	$kept += '' !== $d2 ? 1 : 0;
}
hodima_ck( 'پیش‌نویس «عنوان کوتاه» با عنوان فعلی: اگر نشان داده شود با همان عنوان خود ادمین شروع می‌شود، نه نام صفحه (محصول /348/ ۱۲ آبان ۱۴۰۵)', [] === $lost, implode( '، ', $lost ) );
hodima_ck( "پیش‌نویس «عنوان بلند» در {$checked} صفحه: از سقف بیشتر نیست", [] === $bad_long, implode( '، ', $bad_long ) );

echo "=== روتر: نوع‌هایی که روتر آدرس تمیز نمی‌شناسد (ویدیو) با url_to_postid شناخته می‌شوند\n";
$vid = get_posts( [ 'post_type' => 'video', 'post_status' => 'publish', 'numberposts' => 1 ] );
if ( $vid ) {
	$vpath = hodima_seo_advisor_path_key( (string) get_permalink( $vid[0] ) );
	hodima_ck( 'classify(/video/…) ← نوع video و شناسه همان نوشته (دکمه «ویرایش صفحه» در «کارها»)', [ 'video', $vid[0]->ID ] === array_values( hodima_seo_advisor_classify( $vpath ) ), $vpath . ' ' . wp_json_encode( hodima_seo_advisor_classify( $vpath ) ) );
} else {
	echo "  ℹ نوشته ویدیو در ابزار تست نیست؛ آزمون classify ویدیو رد شد\n";
}
hodima_ck( 'classify(آدرس ناشناخته) ← خالی', [ '', 0 ] === array_values( hodima_seo_advisor_classify( '/no-such-page-xyz/' ) ) );

echo "=== قانون ثابت دستار: هر ایراد رکورد، مرجع و سطح هم‌خوان دارد (docs/seo-advisor.md)\n";
// صفحه‌های رسمی Google Search Central که مرجع می‌توانند باشند (نشانی کامل در docs/seo-advisor-rulebook.md)
$google_pages = [ 'title-link', 'snippet', 'seo-starter-guide', 'consolidate-duplicate-urls', 'robots-meta-tag', 'http-network-errors', 'build-sitemap', 'links-crawlable', 'spam-policies' ];
$ref_bad      = [];
foreach ( hodima_seo_advisor_issue_catalog() as $code => $record ) {
	$ref   = (string) ( $record['ref'] ?? '' );
	$google = [];
	$refs   = [];
	foreach ( '' === $ref ? [] : explode( '|', $ref ) as $part ) {
		if ( str_starts_with( $part, 'google:' ) ) {
			$google = array_filter( explode( ',', substr( $part, 7 ) ) );
		} elseif ( str_starts_with( $part, 'refs:' ) ) {
			$refs = array_filter( explode( ',', substr( $part, 5 ) ) );
		} elseif ( 'own' !== $part ) {
			$ref_bad[] = "{$code}: بخش ناشناخته «{$part}»";
		}
	}
	if ( '' === $ref ) {
		$ref_bad[] = "{$code}: بدون مرجع";
	}
	if ( array_diff( $google, $google_pages ) ) {
		$ref_bad[] = "{$code}: صفحه گوگل ناشناخته " . implode( ',', array_diff( $google, $google_pages ) );
	}
	$level = (string) $record['level'];
	if ( 'bad' === $level && ! $google ) {
		$ref_bad[] = "{$code}: ایراد قرمز بدون جمله رسمی گوگل";
	}
	if ( 'warn' === $level && ! $google && count( $refs ) < 3 ) {
		$ref_bad[] = "{$code}: ایراد کهربایی بدون مرجع گوگل و با کمتر از ۳ پروژه مرجع";
	}
	if ( 'own' === $ref && 'info' !== $level ) {
		$ref_bad[] = "{$code}: بدون مرجع فقط می‌تواند اطلاع باشد";
	}
}
hodima_ck( 'هر ایراد مرجع دارد: قرمز = جمله رسمی گوگل؛ کهربایی = گوگل یا ≥۳ پروژه؛ «own» فقط اطلاع؛ صفحه گوگل از فهرست شناخته‌شده', [] === $ref_bad, implode( ' | ', $ref_bad ) );
$cat_bad = [];
foreach ( hodima_seo_advisor_issue_catalog() as $code => $record ) {
	foreach ( [ 'label', 'level', 'effort', 'fix', 'ref' ] as $key ) {
		if ( ! isset( $record[ $key ] ) || '' === (string) $record[ $key ] ) {
			$cat_bad[] = "{$code}.{$key}";
		}
	}
}
hodima_ck( 'هر رکورد کاتالوگ برچسب، سطح، سختی، دستور و مرجع دارد', [] === $cat_bad, implode( '، ', $cat_bad ) );

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
