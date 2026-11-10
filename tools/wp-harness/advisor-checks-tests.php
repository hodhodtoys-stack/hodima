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
			'notext_links' => 0,
			'js_links'     => 0,
			'soft404'      => '',
			'ld_count'     => 1,
			'ld_errors'    => '',
			'impressions' => 0,
			'kind'        => 'page',
		],
		$over
	);
}

/** زمینه ساختگی (بدون دیتابیس). */
function hodima_ck_ctx( array $over = [] ): array {
	return $over + [ 'titles' => [], 'h1s' => [], 'similar' => [], 'by_path' => [], 'sitemap' => [], 'bad' => [], 'kinds' => [], 'taxonomies' => [ 'category' => true, 'post_tag' => true, 'product_cat' => true, 'product_tag' => true ] ];
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
	[ 'متن کم، نوع از زمینه (صفحه بی‌نمایش) ← thin', 'thin', [ 'words' => 50, 'kind' => '' ], [ 'kinds' => [ '/ck-page/' => 'product' ] ], [ 'thin' ] ],
	[ 'متن کم، نوع زمینه دسته محصول ← هیچ', 'thin', [ 'words' => 50, 'kind' => '' ], [ 'kinds' => [ '/ck-page/' => 'product_cat' ] ], [] ],
	[ 'متن کم، نوع ناشناخته ← هیچ (تابع خالص؛ دیتابیس نمی‌پرسد)', 'thin', [ 'words' => 50, 'kind' => '' ], [], [] ],
	[ 'دو تصویر بدون alt ← img_alt', 'images', [ 'img_noalt' => 2 ], [], [ 'img_alt' ] ],
	[ 'یک alt خالی ← img_empty (فقط «بررسی کنید»)', 'images', [ 'img_empty' => 1 ], [], [ 'img_empty' ] ],
	[ 'تصویر سالم ← هیچ', 'images', [], [], [] ],
	// ── لینک بی‌متن و لینک جاوااسکریپتی (SEO 3.8.4؛ گوگل: «Make your links crawlable»)
	[ 'دو لینک بی‌متن در متن ← link_no_text', 'link_no_text', [ 'notext_links' => 2, 'notext_list' => '[["/a/",""],["/b/",""]]' ], [], [ 'link_no_text' ] ],
	[ 'بدون لینک بی‌متن ← هیچ', 'link_no_text', [], [], [] ],
	[ 'ردیف خزش قدیمی (-1) ← link_no_text نمی‌دهد', 'link_no_text', [ 'notext_links' => -1 ], [], [] ],
	[ 'یک لینک onclick ← link_js', 'link_js', [ 'js_links' => 1, 'js_list' => '[["/x/","مشاهده"]]' ], [], [ 'link_js' ] ],
	[ 'بدون لینک جاوااسکریپتی ← هیچ', 'link_js', [], [], [] ],
	[ 'ردیف خزش قدیمی (-1) ← link_js نمی‌دهد', 'link_js', [ 'js_links' => -1 ], [], [] ],
	// ── Soft 404 و داده ساختاریافته خراب (SEO 3.8.6)
	[ 'H1 «محتوایی یافت نشد!» با کد ۲۰۰ ← soft_404', 'soft_404', [ 'soft404' => 'title|یافت نشد' ], [], [ 'soft_404' ] ],
	[ 'دسته بی‌محصول (پیام ووکامرس) ← soft_404', 'soft_404', [ 'soft404' => 'empty|woocommerce-no-products-found' ], [], [ 'soft_404' ] ],
	[ 'صفحه عادی ← هیچ', 'soft_404', [], [], [] ],
	[ 'soft 404 در صفحه سیستمی (/cart/) ← هیچ', 'soft_404', [ 'path' => '/cart/', 'soft404' => 'empty|x' ], [], [] ],
	[ 'JSON-LD خوانده نمی‌شود ← ld_invalid', 'ld_invalid', [ 'ld_errors' => '[["json","Syntax error"]]' ], [], [ 'ld_invalid' ] ],
	[ 'گره بی @type ← ld_invalid', 'ld_invalid', [ 'ld_errors' => '[["type","#x"]]' ], [], [ 'ld_invalid' ] ],
	[ 'JSON-LD سالم ← هیچ', 'ld_invalid', [], [], [] ],
	[ 'ردیف قدیمی (ld_count = -1) ← ld_invalid نمی‌دهد', 'ld_invalid', [ 'ld_count' => -1, 'ld_errors' => '[["json","x"]]' ], [], [] ],
	// ── FAQPage اسکیما (SEO 3.9.4)
	[ 'پرسش‌هایی که در صفحه نیستند ← faq_schema', 'faq_schema', [ 'ld_errors' => '[["faq_hidden","10 از 20 پرسش در متن صفحه دیده نمی‌شود"]]' ], [], [ 'faq_schema' ] ],
	[ 'نشانه ناشناخته (faq_same قدیمی) ← faq_schema نمی‌دهد: بی مرجع رسمی ایراد نیست', 'faq_schema', [ 'ld_errors' => '[["faq_same","x"]]' ], [], [] ],
	[ 'FAQ سالم (بی ایراد در ld_errors) ← هیچ', 'faq_schema', [ 'ld_errors' => '' ], [], [] ],
	[ 'ایراد دیگر JSON-LD (json) ← faq_schema نمی‌دهد', 'faq_schema', [ 'ld_errors' => '[["json","Syntax error"]]' ], [], [] ],
	[ 'ردیف قدیمی (ld_count = -1) ← faq_schema نمی‌دهد', 'faq_schema', [ 'ld_count' => -1, 'ld_errors' => '[["faq_hidden","x"]]' ], [], [] ],
	[ 'ایراد FAQ ← ld_invalid نمی‌دهد (دو ایراد جدا)', 'ld_invalid', [ 'ld_errors' => '[["faq_hidden","x"]]' ], [], [] ],
	// ── اسکیمای محصول ناقص (SEO 3.9.5)
	[ 'صفحه محصول بی Product ← product_schema', 'product_schema', [ 'ld_errors' => '[["product_missing","x"]]' ], [], [ 'product_schema' ] ],
	[ 'Product بی قیمت ← product_schema', 'product_schema', [ 'ld_errors' => '[["product_fields","اسکیمای Product این فیلدها را ندارد: قیمت (offers.price)"]]' ], [], [ 'product_schema' ] ],
	[ 'Product کامل (بی ایراد) ← هیچ', 'product_schema', [ 'ld_errors' => '' ], [], [] ],
	[ 'ایراد FAQ ← product_schema نمی‌دهد', 'product_schema', [ 'ld_errors' => '[["faq_hidden","x"]]' ], [], [] ],
	[ 'ردیف قدیمی (ld_count = -1) ← product_schema نمی‌دهد', 'product_schema', [ 'ld_count' => -1, 'ld_errors' => '[["product_missing","x"]]' ], [], [] ],
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
	// ── SEO 3.9.3: canonical دامنه دیگر / شکل دیگر
	[ 'canonical به سایت دیگر (!ext) ← canonical_external', 'canonical_external', [ 'canonical' => '!ext', 'canonical_url' => 'https://other.example/p/' ], [], [ 'canonical_external' ] ],
	[ 'canonical به سایت دیگر در ردیف قدیمی بی‌آدرس ← باز هم canonical_external', 'canonical_external', [ 'canonical' => '!ext' ], [], [ 'canonical_external' ] ],
	[ 'canonical به خودش ← canonical_external نمی‌دهد', 'canonical_external', [], [], [] ],
	[ 'canonical مسیر بلند (!long) ← «سایت دیگر» نیست', 'canonical_external', [ 'canonical' => '!long' ], [], [] ],
	[ 'canonical سایت دیگر در صفحه سیستمی (/cart/) ← هیچ', 'canonical_external', [ 'path' => '/cart/', 'canonical' => '!ext' ], [], [] ],
	[ 'canonical با http:// در سایت https ← canonical_variant', 'canonical_variant', [ 'canonical_url' => 'http://hodima.test/ck-page/' ], [ 'site' => [ 'https', 'hodima.test' ] ], [ 'canonical_variant' ] ],
	[ 'canonical با پارامتر ?color=red ← canonical_variant', 'canonical_variant', [ 'canonical_url' => 'https://hodima.test/ck-page/?color=red' ], [ 'site' => [ 'https', 'hodima.test' ] ], [ 'canonical_variant' ] ],
	[ 'canonical با www در سایت بی‌www ← canonical_variant', 'canonical_variant', [ 'canonical_url' => 'https://www.hodima.test/ck-page/' ], [ 'site' => [ 'https', 'hodima.test' ] ], [ 'canonical_variant' ] ],
	[ 'canonical https بدون پارامتر ← canonical_variant نمی‌دهد (ستون خالی)', 'canonical_variant', [], [ 'site' => [ 'https', 'hodima.test' ] ], [] ],
	[ 'canonical_url پر ولی شکل درست (ردیف از سایت دیگری با همان دامنه) ← هیچ', 'canonical_variant', [ 'canonical_url' => 'https://hodima.test/ck-page/' ], [ 'site' => [ 'https', 'hodima.test' ] ], [] ],
	[ 'canonical سایت دیگر ← canonical_variant جداست و نمی‌دهد', 'canonical_variant', [ 'canonical' => '!ext', 'canonical_url' => 'https://other.example/p/' ], [ 'site' => [ 'https', 'hodima.test' ] ], [] ],
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

echo "=== خزنده: لینک بی‌متن و لینک جاوااسکریپتی (SEO 3.8.4)\n";
$lk = $doc( '', '<main>'
	. '<a href="/empty-img/"><img src="/i.jpg" alt=""></a>'                          // بی‌متن ← ایراد
	. '<a href="/card/"><img src="/c.jpg" alt=""></a><a href="/card/">نام محصول</a>' // همان مقصد با متن ← نه
	. '<a href="/alt-img/"><img src="/a.jpg" alt="کش مو"></a>'                       // alt تصویر متن لینک است ← نه
	. '<a href="https://other.test/x/"></a>'                                          // بیرونی ← نه
	. '<span onclick="location.href=\'/js-a/\'">برو</span>'                         // ← js
	. '<a href="javascript:window.location=\'/js-b/\'">ب</a>'                        // ← js
	. '<a data-href="/js-c/">ج</a>'                                                   // ← js
	. '<a href="/real/" onclick="location.href=\'/real/\'">واقعی</a>'                // href واقعی ← نه
	. '<button onclick="ga(\'send\',\'/event/\')">آمار</button>'                   // آمار، نه جابه‌جایی ← نه
	. '<a href="javascript:void(0)">باز کن</a>'                                       // بی‌آدرس ← نه
	. '<a href="/in/"><span onclick="location.href=\'/in/\'">x</span></a>'           // داخل لینک واقعی ← نه
	. '</main><nav><a href="/icon/"><i class="ico"></i></a></nav>' );               // آیکون منو ← نه (فقط متن صفحه)
hodima_ck( 'لینک بی‌متن: فقط /empty-img/ (نه کارت با لینک متن‌دار، نه alt، نه بیرونی، نه منو)', [ 1, '/empty-img/' ] === [ $lk['notext_links'], $lk['notext_list'][0][0] ?? '' ], wp_json_encode( $lk['notext_list'] ) );
hodima_ck( 'لینک جاوااسکریپتی: onclick location، javascript: با آدرس، data-href (نه href واقعی، آمار، void(0)، داخل لینک)', [ 3, [ '/js-a/', '/js-b/', '/js-c/' ] ] === [ $lk['js_links'], array_column( $lk['js_list'], 0 ) ], wp_json_encode( $lk['js_list'], JSON_UNESCAPED_UNICODE ) );
$lk_row = $row_of( $lk );
hodima_ck( 'ردیف صفحه: شمار و فهرست JSON هر دو؛ صفحه بی‌لینک ← ۰ (نه -1)', [ 1, 3, true, 0, 0 ] === [ $lk_row['notext_links'], $lk_row['js_links'], str_contains( $lk_row['js_list'], '/js-a/' ), $row_of( $p_none )['notext_links'], $row_of( $p_none )['js_links'] ] );

echo "=== خزنده: Soft 404 و داده ساختاریافته (SEO 3.8.6)\n";
$sf = static fn( string $title, string $body ): string => $doc( '', $body )['soft404'] ?? 'x';
hodima_ck( 'Soft 404: H1 قالب «محتوایی یافت نشد!» ← title', str_starts_with( (string) $doc( '', '<main><div class="hodima-empty"><h1 class="hodima-empty__title">محتوایی یافت نشد!</h1></div></main>' )['soft404'], 'title|' ) );
hodima_ck( 'Soft 404: پیام ووکامرس دسته خالی ← empty', 'empty|woocommerce-no-products-found' === $sf( '', '<main><h1>گیره مو</h1><div class="woocommerce-no-products-found"><div class="woocommerce-info">No products were found matching your selection.</div></div></main>' ) );
hodima_ck( 'Soft 404: «هیچ مقاله‌ای یافت نشد.» آرشیو مقاله ← empty', 'empty|blog-page-empty' === $sf( '', '<main><h1>وبلاگ</h1><p class="blog-page-empty">هیچ مقاله‌ای یافت نشد.</p></main>' ) );
hodima_ck( 'Soft 404: صفحه عادی، و «ناموجود» در متن محصول ← هیچ', '' === $sf( '', '<main><h1>کش مو فنری</h1><p>این رنگ فعلا ناموجود است.</p></main>' ) );
hodima_ck( 'Soft 404: عنوان «404» ← title|404', 'title|404' === hodima_seo_advisor_crawler_parse( '<html><head><title>404 | هدهد</title></head><body><main><p>x</p></main></body></html>', 'https://hodima.test/p/' )['soft404'] );
// SEO 3.9.3: عبارت‌های دقیق‌تر و شرط متن کم (باگ‌های واقعی: «گیره مو کد 404» و «چرا … وجود ندارد» Soft 404 می‌شدند)
$sf_t = static fn( string $title, string $h1, string $body = '<p>x</p>' ): string => hodima_seo_advisor_crawler_parse( '<!doctype html><html><head><title>' . $title . '</title></head><body><main><h1>' . $h1 . '</h1>' . $body . '</main></body></html>', 'https://hodima.test/p/' )['soft404'] ?? 'x'; // $doc عنوان «t» خودش را دارد؛ اینجا عنوان باید واقعی باشد
hodima_ck( 'Soft 404 (۳.۹.۳): محصول «گیره مو کد 404» ← هیچ (عدد وسط نام محصول)', '' === $sf_t( 'گیره مو کد 404 | هدهد', 'گیره مو کد 404' ) );
hodima_ck( 'Soft 404 (۳.۹.۳): مقاله «چرا این محصول وجود ندارد؟» ← هیچ (عبارت «وجود ندارد» تنها کافی نیست)', '' === $sf_t( 'چرا این محصول وجود ندارد؟', 'چرا این محصول وجود ندارد؟' ) );
hodima_ck( 'Soft 404 (۳.۹.۳): «صفحه مورد نظر شما» در عنوان مقاله ← هیچ', '' === $sf_t( 'صفحه مورد نظر شما برای سفارش', 'راهنمای سفارش' ) );
hodima_ck( 'Soft 404 (۳.۹.۳): «خطای ۴۰۴ | هدهد» با رقم فارسی ← title|404', 'title|404' === $sf_t( 'خطای ۴۰۴ | هدهد', 'ای وای' ) );
hodima_ck( 'Soft 404 (۳.۹.۳): «صفحه یافت نشد» با متن کم ← title', str_starts_with( $sf_t( 'صفحه یافت نشد', 'صفحه یافت نشد' ), 'title|' ) );
hodima_ck( 'Soft 404 (۳.۹.۳): «صفحه یافت نشد» ولی ۳۰۰ کلمه متن ← هیچ (صفحه واقعی)', '' === $sf_t( 'صفحه یافت نشد', 'صفحه یافت نشد', '<p>' . str_repeat( 'کلمه ', 300 ) . '</p>' ) );
$ldp = static fn( string $head ): array => $doc( $head );
$ld_ok   = $ldp( '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"WebPage","@id":"#w"},{"@type":"Organization","@id":"#o"}]}</script>' );
$ld_bad  = $ldp( '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Product",}</script>' );
$ld_mix  = $ldp( '<script type="application/ld+json">{"@type":"Product","name":"x"}</script><script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@id":"#n"}]}</script>' );
hodima_ck( 'JSON-LD سالم (@graph با @type) ← ۱ اسکریپت، بی ایراد', [ 1, [] ] === [ $ld_ok['ld_count'], $ld_ok['ld_errors'] ] );
hodima_ck( 'JSON-LD با ویرگول اضافه ← json', [ 1, 'json' ] === [ $ld_bad['ld_count'], $ld_bad['ld_errors'][0][0] ?? '' ], wp_json_encode( $ld_bad['ld_errors'] ) );
hodima_ck( 'بی @context و گره @graph بی @type ← context و type', [ 2, [ 'context', 'type' ] ] === [ $ld_mix['ld_count'], array_column( $ld_mix['ld_errors'], 0 ) ], wp_json_encode( $ld_mix['ld_errors'], JSON_UNESCAPED_UNICODE ) );
// SEO 3.9.4: FAQPage فقط در اسکیما (همان وضع واقعی صفحه محصول سایت: ۱۰ پرسش انگلیسی + ۱۰ فارسی در اسکیما و هیچ‌کدام در صفحه)
$faq_ld = static fn( array $qa ): string => '<script type="application/ld+json">' . wp_json_encode( [ '@context' => 'https://schema.org', '@graph' => [ [ '@type' => 'FAQPage', '@id' => '#faq', 'mainEntity' => array_map( static fn( array $x ): array => [ '@type' => 'Question', 'name' => $x[0], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $x[1] ] ], $qa ) ] ] ], JSON_UNESCAPED_UNICODE ) . '</script>';
$faq_p  = static fn( array $qa, string $body ): array => $doc( $faq_ld( $qa ), '<main><h1>پد پنکک</h1>' . $body . '</main>' )['ld_errors'];
$faq_in = [ [ 'جنس پد پنکک دوتایی چیست؟', 'جنس محصول اسفنج فومی نرم است.' ], [ 'سایز چقدر است؟', 'سایز ۵ سانتی است.' ] ];
hodima_ck( 'FAQ در صفحه هم هست (با «؟» فارسی و بی «؟» در اسکیما/صفحه) ← بی ایراد', [] === $faq_p( $faq_in, '<h2>سوالات متداول</h2><h3>جنس پد پنکک دوتایی چیست</h3><p>جنس محصول اسفنج فومی نرم است.</p><h3>سایز چقدر است؟</h3><p>سایز ۵ سانتی است.</p>' ), wp_json_encode( $faq_p( $faq_in, '<h3>جنس پد پنکک دوتایی چیست</h3><h3>سایز چقدر است؟</h3>' ), JSON_UNESCAPED_UNICODE ) );
hodima_ck( 'FAQ در اسکیماست ولی در صفحه نیست ← faq_hidden با شمار «۲ از ۲»', [ [ 'faq_hidden', '2 از 2 پرسش در متن صفحه دیده نمی‌شود' ] ] === $faq_p( $faq_in, '<p>متن دیگر</p>' ), wp_json_encode( $faq_p( $faq_in, '<p>متن دیگر</p>' ), JSON_UNESCAPED_UNICODE ) );
hodima_ck( 'یکی از دو پرسش در صفحه است ← «۱ از ۲»', [ [ 'faq_hidden', '1 از 2 پرسش در متن صفحه دیده نمی‌شود' ] ] === $faq_p( $faq_in, '<h3>سایز چقدر است؟</h3>' ) );
$faq_mix = [ [ 'What material is it?', 'Foam.' ], [ 'جنس چیست؟', 'جنس چیست؟' ] ];
hodima_ck( 'پرسش انگلیسی در صفحه فارسی ← faq_hidden با «(۱ انگلیسی)»؛ پاسخ برابر پرسش ایراد نیست', [ [ 'faq_hidden', '1 از 2 پرسش در متن صفحه دیده نمی‌شود (1 انگلیسی)' ] ] === $faq_p( $faq_mix, '<h3>جنس چیست؟</h3>' ), wp_json_encode( $faq_p( $faq_mix, '<h3>جنس چیست؟</h3>' ), JSON_UNESCAPED_UNICODE ) );
hodima_ck( 'پرسش فقط داخل script/template/noscript صفحه ← دیده‌نشدنی (faq_hidden)', 'faq_hidden' === ( $faq_p( $faq_in, '<script>var q="جنس پد پنکک دوتایی چیست؟ سایز چقدر است؟"</script><template>جنس پد پنکک دوتایی چیست</template><noscript>سایز چقدر است</noscript>' )[0][0] ?? '' ) );
hodima_ck( 'ارقام و املا یکسان‌سازی می‌شود (۵ و 5؛ «ي» عربی)', [] === $faq_p( [ [ 'سايز ۵ سانتي چيست؟', 'x' ] ], '<h3>سایز 5 سانتی چیست</h3>' ) );
hodima_ck( 'صفحه بی FAQPage ← هیچ ایراد FAQ (فقط سایر JSON-LD)', [] === $ld_ok['ld_errors'] );
hodima_ck( 'صفحه با FAQ و JSON خراب هم ایراد json را نگه می‌دارد و کار می‌کند', 'json' === ( $doc( '<script type="application/ld+json">{bad}</script>' . $faq_ld( $faq_in ), '<p>x</p>' )['ld_errors'][0][0] ?? '' ) );
// SEO 3.9.5: اسکیمای Product ناقص فقط در صفحه محصول (کلاس بدنه single-product یا og:type)
$pr_doc  = static fn( string $json, string $body_class = 'single-product woocommerce', string $head = '' ): array => hodima_seo_advisor_crawler_parse( '<!doctype html><html><head><title>t</title>' . $head . ( '' === $json ? '' : '<script type="application/ld+json">' . $json . '</script>' ) . '</head><body class="' . $body_class . '"><main><h1>پد</h1><p>x</p></main></body></html>', 'https://hodima.test/p/' )['ld_errors'];
$pr_full = static fn( array $over = [] ): string => (string) wp_json_encode( [ '@context' => 'https://schema.org', '@graph' => [ array_merge( [ '@type' => 'Product', 'name' => 'پد پنکک', 'image' => [ 'https://hodima.test/a.webp' ], 'offers' => [ '@type' => 'Offer', 'price' => '308000', 'priceCurrency' => 'IRR', 'availability' => 'https://schema.org/InStock' ] ], $over ) ] ], JSON_UNESCAPED_UNICODE );
hodima_ck( 'محصول کامل (نام، تصویر، قیمت، ارز) ← بی ایراد', [] === $pr_doc( $pr_full() ), wp_json_encode( $pr_doc( $pr_full() ), JSON_UNESCAPED_UNICODE ) );
hodima_ck( 'محصول بی قیمت و ارز ← product_fields با نام هر دو فیلد', [ [ 'product_fields', 'اسکیمای Product این فیلدها را ندارد: قیمت (offers.price)، ارز (priceCurrency)' ] ] === $pr_doc( $pr_full( [ 'offers' => [ '@type' => 'Offer', 'availability' => 'x' ] ] ) ), wp_json_encode( $pr_doc( $pr_full( [ 'offers' => [ '@type' => 'Offer' ] ] ) ), JSON_UNESCAPED_UNICODE ) );
hodima_ck( 'قیمت صفر (merchant listing: بزرگ‌تر از صفر) ← قیمت کم است؛ قیمت رشته‌ای «308000» درست', [ 'اسکیمای Product این فیلدها را ندارد: قیمت (offers.price)', [] ] === [ $pr_doc( $pr_full( [ 'offers' => [ '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'IRR' ] ] ) )[0][1] ?? '', $pr_doc( $pr_full() ) ] );
hodima_ck( 'محصول بی تصویر و بی offers ← تصویر، قیمت و ارز', [ [ 'product_fields', 'اسکیمای Product این فیلدها را ندارد: تصویر (image)، قیمت (offers.price)، ارز (priceCurrency)' ] ] === $pr_doc( $pr_full( [ 'image' => '', 'offers' => [] ] ) ) );
hodima_ck( 'صفحه محصول بی هیچ Product (فقط FAQ/WebPage) ← product_missing', 'product_missing' === ( $pr_doc( '{"@context":"https://schema.org","@graph":[{"@type":"WebPage","@id":"#w"}]}' )[0][0] ?? '' ) );
hodima_ck( 'صفحه محصول بی هیچ JSON-LD ← product_missing', 'product_missing' === ( $pr_doc( '' )[0][0] ?? '' ) );
hodima_ck( 'همان بی‌Product در صفحه عادی (مقاله) ← هیچ', [] === $pr_doc( '{"@context":"https://schema.org","@graph":[{"@type":"WebPage","@id":"#w"}]}', 'single-post' ) );
hodima_ck( 'Product ناقص (فقط نام و آدرس) در صفحه دسته/آرشیو ← هیچ', [] === $pr_doc( $pr_full( [ 'image' => '', 'offers' => [] ] ), 'archive tax-product_cat woocommerce' ) );
hodima_ck( 'تشخیص محصول با og:type=product بدون کلاس بدنه', 'product_missing' === ( $pr_doc( '', 'single', '<meta property="og:type" content="product">' )[0][0] ?? '' ) );
hodima_ck( 'AggregateOffer با lowPrice و priceCurrency (محصول متغیر) ← بی ایراد', [] === $pr_doc( $pr_full( [ 'offers' => [ '@type' => 'AggregateOffer', 'lowPrice' => '10', 'priceCurrency' => 'IRR' ] ] ) ) );
hodima_ck( 'چند offer که یکی قیمت و ارز دارد ← بی ایراد', [] === $pr_doc( $pr_full( [ 'offers' => [ [ '@type' => 'Offer', 'availability' => 'x' ], [ '@type' => 'Offer', 'price' => '5', 'priceCurrency' => 'IRR' ] ] ] ) ) );
hodima_ck( 'دو گره Product: یکی ناقص و یکی کامل ← بی ایراد', [] === $pr_doc( (string) wp_json_encode( [ '@context' => 'https://schema.org', '@graph' => [ [ '@type' => 'Product', 'name' => 'x' ], json_decode( $pr_full(), true )['@graph'][0] ] ] ) ) );
hodima_ck( 'Product با @type آرایه (Product + Thing) هم شمرده می‌شود', [] === $pr_doc( $pr_full( [ '@type' => [ 'Product', 'Thing' ] ] ) ) );
hodima_ck( 'priceSpecification (price + priceCurrency) هم قیمت حساب می‌شود', [] === $pr_doc( $pr_full( [ 'offers' => [ '@type' => 'Offer', 'priceSpecification' => [ 'price' => '9', 'priceCurrency' => 'IRR' ] ] ] ) ) );
hodima_ck( 'صفحه بی JSON-LD ← ۰ اسکریپت (ردیف: ld_count ۰، نه -1)', [ 0, 0, '' ] === [ $p_none['ld_count'], $row_of( $p_none )['ld_count'], $row_of( $p_none )['ld_errors'] ] );

echo "=== ریدایرکت: کدام صفحه‌ها هنوز به آدرس ریدایرکت‌شده لینک داده‌اند (SEO 3.8.4)\n";
$rd_from = hodima_seo_advisor_redirect_sources( [ '/src-a/' => [ 'link_redirect' => [ [ 'path' => '/old/', 'note' => 'می‌رود به /new/؛ متن لینک «کش مو»' ] ] ], '/src-b/' => [ 'link_redirect' => [ [ 'path' => '/old/', 'note' => 'ریدایرکت می‌شود؛ بدون متن لینک' ] ], 'link_broken' => [ [ 'path' => '/x/', 'note' => '' ] ] ] ] );
hodima_ck( 'وارونه لینک به ریدایرکت: مقصد ← مبدأها با متن لینک؛ لینک خراب جدا', [ '/old/' => [ [ 'path' => '/src-a/', 'note' => 'متن لینک «کش مو»' ], [ 'path' => '/src-b/', 'note' => 'بدون متن لینک' ] ] ] === $rd_from, wp_json_encode( $rd_from, JSON_UNESCAPED_UNICODE ) );
$rd_issue = hodima_seo_advisor_check_status( hodima_ck_row( [ 'path' => '/old/', 'status' => 301, 'redirect_to' => '/new/', 'inlinks' => 2 ] ), hodima_ck_ctx( [ 'redirect_from' => $rd_from ] ) );
hodima_ck( 'ایراد redirect فهرست صفحه‌های لینک‌دهنده را دارد', [ '/src-a/', '/src-b/' ] === array_column( $rd_issue[0]['items'] ?? [], 'path' ) );

echo "=== canonical و نقشه سایت: آدرس درست با مقصد ریدایرکت یا canonical (SEO 3.8.5)\n";
$cs_by = [ '/old/' => [ 'status' => 301, 'redirect_to' => '/mid/', 'reason' => 'Redirected' ], '/mid/' => [ 'status' => 301, 'redirect_to' => '/new/', 'reason' => 'Redirected' ], '/new/' => [ 'status' => 200, 'redirect_to' => '', 'reason' => '' ], '/loop-a/' => [ 'status' => 301, 'redirect_to' => '/loop-b/', 'reason' => '' ], '/loop-b/' => [ 'status' => 301, 'redirect_to' => '/loop-a/', 'reason' => '' ] ];
$cs_one = static fn( string $fn, array $row, array $ctx ): array => ( $S . $fn )( hodima_ck_row( $row ), hodima_ck_ctx( $ctx ) )[0] ?? [];
$cs_a   = $cs_one( 'canonical_bad', [ 'canonical' => '/old/' ], [ 'by_path' => $cs_by ] );
hodima_ck( 'canonical به ریدایرکت (۲ پرش) ← canonical_bad با مقصد نهایی سالم /new/ برای کپی', [ 'canonical_bad', '/new/', true ] === [ $cs_a['code'] ?? '', $cs_a['url'] ?? '', str_contains( (string) ( $cs_a['detail'] ?? '' ), 'خودش ریدایرکت می‌شود' ) ], wp_json_encode( $cs_a, JSON_UNESCAPED_UNICODE ) );
$cs_l = $cs_one( 'canonical_bad', [ 'canonical' => '/loop-a/' ], [ 'by_path' => $cs_by ] );
hodima_ck( 'canonical به حلقه ریدایرکت ← canonical_bad بی آدرس پیشنهادی', [ 'canonical_bad', '' ] === [ $cs_l['code'] ?? '', $cs_l['url'] ?? 'x' ] );
$cs_s = $cs_one( 'sitemap_bad', [ 'path' => '/old/', 'status' => 301, 'redirect_to' => '/mid/', 'indexable' => 0 ], [ 'sitemap' => [ '/old/' => true ], 'by_path' => $cs_by ] );
hodima_ck( 'نقشه سایت: آدرس ریدایرکت‌شده ← sitemap_bad با مقصد نهایی /new/', [ 'sitemap_bad', '/new/' ] === [ $cs_s['code'] ?? '', $cs_s['url'] ?? '' ], wp_json_encode( $cs_s, JSON_UNESCAPED_UNICODE ) );
$cs_c = $cs_one( 'sitemap_bad', [ 'indexable' => 0, 'reason' => 'Canonicalised', 'canonical' => '/main/' ], [ 'sitemap' => [ '/ck-page/' => true ] ] );
hodima_ck( 'نقشه سایت: canonical به صفحه دیگر ← sitemap_bad با همان صفحه /main/', [ 'sitemap_bad', '/main/' ] === [ $cs_c['code'] ?? '', $cs_c['url'] ?? '' ] );
$cs_n = $cs_one( 'sitemap_bad', [ 'indexable' => 0, 'reason' => 'Noindex' ], [ 'sitemap' => [ '/ck-page/' => true ] ] );
hodima_ck( 'نقشه سایت: noindex ← بی آدرس پیشنهادی', [ 'noindex', '' ] === [ $cs_n['detail'] ?? '', $cs_n['url'] ?? 'x' ] );

echo "=== مهاجرت جدول (DB 1.14.0 تا 1.16.0): ستون‌های تازه بعد از حذف دوباره ساخته می‌شوند\n";
global $wpdb;
$mg_t = hodima_seo_advisor_table( 'crawl_pages' );
foreach ( [ 'notext_links', 'notext_list', 'js_links', 'js_list', 'soft404', 'ld_count', 'ld_errors', 'canonical_url' ] as $mg_c ) {
	$wpdb->query( "ALTER TABLE {$mg_t} DROP COLUMN {$mg_c}" ); // phpcs:ignore WordPress.DB
}
$mg_before = array_intersect( [ 'notext_links', 'notext_list', 'js_links', 'js_list', 'soft404', 'ld_count', 'ld_errors', 'canonical_url' ], (array) $wpdb->get_col( "SELECT * FROM {$mg_t} LIMIT 0" ) ?: array_column( (array) $wpdb->get_results( "PRAGMA table_info({$mg_t})", ARRAY_A ), 'name' ) );
update_option( HODIMA_SEO_ADVISOR_VERSION_OPTION, '1.13.0', false );
hodima_seo_advisor_ensure_tables();
$mg_cols = array_column( (array) $wpdb->get_results( "PRAGMA table_info({$mg_t})", ARRAY_A ), 'name' );
if ( ! $mg_cols ) {
	$mg_cols = array_column( (array) $wpdb->get_results( "SHOW COLUMNS FROM {$mg_t}", ARRAY_A ), 'Field' );
}
hodima_ck( 'ensure_tables بعد از حذف ستون‌ها: هر هشت ستون تازه (۱.۱۴.۰ تا ۱.۱۶.۰) دوباره هست', [ [], [ 'notext_links', 'notext_list', 'js_links', 'js_list', 'soft404', 'ld_count', 'ld_errors', 'canonical_url' ], HODIMA_SEO_ADVISOR_DB_VERSION ] === [ array_values( $mg_before ), array_values( array_intersect( [ 'notext_links', 'notext_list', 'js_links', 'js_list', 'soft404', 'ld_count', 'ld_errors', 'canonical_url' ], $mg_cols ) ), get_option( HODIMA_SEO_ADVISOR_VERSION_OPTION ) ], wp_json_encode( [ $mg_before, $mg_cols ] ) );

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
$google_pages = [ 'title-link', 'snippet', 'seo-starter-guide', 'consolidate-duplicate-urls', 'robots-meta-tag', 'http-network-errors', 'build-sitemap', 'links-crawlable', 'spam-policies', 'sd-policies', 'product-snippet', 'merchant-listing' ];
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

// SEO 3.6.9: راهنمای پنج‌بخشی هر ایراد (خواست کاربر: «راهنمایی گنگ است»). با جزئیات واقعی عکس مرجع (golden) ساخته می‌شود:
// «چه دیدیم» جمله کامل با جزئیات (نه عدد چسبیده به دستور)، گام‌ها با نام «ویرایش» همان صفحه، «از کجا بفهمم» و «چرا»؛
// و هیچ دستوری «افزونه دیگر» را مقصر نمی‌داند (سایت افزونه خارجی ندارد؛ همه کد خودمان است).
echo "=== فهرست‌های زیر ایراد (SEO 3.7.3): صفحه‌های تکراری، لینک‌های http، پیشنهاد لینک به صفحه بی‌لینک\n";
$fl_ctx  = hodima_seo_advisor_checks_context( [
	hodima_ck_row( [ 'path' => '/fa/', 'title' => 'گل سر یکسان', 'h1' => 'تیتر الف', 'meta_hash' => 'h1h1h1h1h1h1' ] ),
	hodima_ck_row( [ 'path' => '/fb/', 'title' => 'گل سر یکسان', 'h1' => 'تیتر ب', 'meta_hash' => 'h1h1h1h1h1h1' ] ),
	hodima_ck_row( [ 'path' => '/fc/', 'title' => 'گل سر یکسان', 'h1' => 'تیتر ج', 'status' => 404 ] ), // صفحه خراب جزو تکراری‌ها نیست
	hodima_ck_row( [ 'path' => '/clips/', 'title' => 'کلیپس فلزی مو', 'h1' => 'کلیپس فلزی', 'inlinks' => 40 ] ),
	hodima_ck_row( [ 'path' => '/clips-b/', 'title' => 'کلیپس مو بزرگ', 'h1' => 'کلیپس بزرگ', 'inlinks' => 3 ] ),
	hodima_ck_row( [ 'path' => '/lonely/', 'title' => 'کلیپس فلزی طلایی', 'h1' => 'کلیپس فلزی طلایی', 'inlinks' => 0, 'text_links' => 0 ] ),
] );
$fl_items = static function ( string $topic, string $path, array $over = [] ) use ( $S, $fl_ctx ): array {
	$out = [];
	foreach ( ( $S . $topic )( hodima_ck_row( [ 'path' => $path ] + $over ), $fl_ctx ) as $issue ) {
		$out[ $issue['code'] ] = array_column( (array) $issue['items'], 'path' );
	}
	return $out;
};
hodima_ck( 'عنوان تکراری: «کدام صفحه‌ها» فقط صفحه‌های دیگر سالم (نه خودش، نه ۴۰۴)', [ 'dup_title' => [ '/fb/' ] ] === $fl_items( 'dup_title', '/fa/', [ 'title' => 'گل سر یکسان' ] ) );
hodima_ck( 'توضیح متای تکراری: صفحه دیگر همان متا', [ 'dup_meta' => [ '/fa/' ] ] === $fl_items( 'dup_meta', '/fb/', [ 'title' => 'گل سر یکسان', 'h1' => 'تیتر ب', 'meta_hash' => 'h1h1h1h1h1h1' ] ) );
hodima_ck( 'بی‌لینک: پیشنهاد صفحه‌های هم‌کلمه، بیشترین کلمه مشترک و بعد پرلینک‌تر اول؛ نه خودش', [ 'orphan' => [ '/clips/', '/clips-b/' ] ] === $fl_items( 'inlinks', '/lonely/', [ 'title' => 'کلیپس فلزی طلایی', 'h1' => 'کلیپس فلزی طلایی', 'inlinks' => 0, 'text_links' => 0 ] ) );
$fl_http = ( $S . 'http_link' )( hodima_ck_row( [ 'path' => '/h/', 'http_links' => 2, 'http_list' => '[["http://x.test/a/","متن"],["http://x.test/b/",""]]' ] ), hodima_ck_ctx() );
hodima_ck( 'لینک http: فهرست آدرس نوشته‌شده با متن لینک؛ ردیف قدیمی بی‌فهرست هم ایراد را می‌دهد', [ [ 'http://x.test/a/', 'http://x.test/b/' ], 'متن لینک «متن»', [ 'http_link' ] ] === [ array_column( $fl_http[0]['items'], 'path' ), $fl_http[0]['items'][0]['note'], hodima_ck_codes( $S . 'http_link', hodima_ck_row( [ 'path' => '/h/', 'http_links' => 2 ] ), hodima_ck_ctx() ) ] );
$fl_parsed = hodima_seo_advisor_crawler_parse( '<html><head><title>t</title></head><body><a href="http://' . wp_parse_url( home_url(), PHP_URL_HOST ) . '/p/">پیوند ناامن</a><a href="/q/">امن</a></body></html>', home_url( '/x/' ) );
hodima_ck( 'خزنده: لینک http در http_list با متن لینک ذخیره می‌شود (فقط در سایت https)', str_starts_with( home_url(), 'https://' ) ? ( 1 === $fl_parsed['http_links'] && 'پیوند ناامن' === ( $fl_parsed['http_list'][0][1] ?? '' ) ) : ( 0 === $fl_parsed['http_links'] ) );

echo "=== راهنمای هر ایراد (چه دیدیم، لازم است؟، گام‌ها، از کجا بفهمم، چرا)\n";
$golden_file = __DIR__ . '/golden/advisor-issues.json';
$golden      = is_file( $golden_file ) ? json_decode( (string) file_get_contents( $golden_file ), true ) : [];
$samples     = [];
foreach ( (array) ( $golden['pages'] ?? [] ) as $gp ) {
	foreach ( (array) ( $gp['issues'] ?? [] ) as $gi ) {
		$samples[ (string) $gi['code'] ][] = [ (string) ( $gi['detail'] ?? '' ), (string) ( $gi['level'] ?? '' ) ];
	}
}
$guide_bad = [];
foreach ( hodima_seo_advisor_issue_catalog() as $code => $record ) {
	foreach ( [ 'seen', 'why' ] as $key ) {
		if ( '' === trim( (string) ( $record[ $key ] ?? '' ) ) ) {
			$guide_bad[] = "{$code}: بدون {$key}";
		}
	}
	if ( empty( $record['steps'] ) || ! array_is_list( $record['steps'] ) ) {
		$guide_bad[] = "{$code}: بدون گام";
	}
	foreach ( $samples[ $code ] ?? [ [ '', '' ] ] as [ $detail, $level ] ) {
		$g    = hodima_seo_advisor_issue_guide( $code, $detail, 'ویرایش محصول', $level );
		$text = $g['seen'] . ' ' . implode( ' ', $g['steps'] ) . ' ' . $g['verify'] . ' ' . $g['why'];
		if ( str_contains( $text, '{d}' ) || str_contains( $text, '{where}' ) || str_contains( $text, '()' ) || str_contains( $text, '  ' ) ) {
			$guide_bad[] = "{$code}: جانگهدار/پرانتز خالی/فاصله دوتایی در «{$g['seen']}»";
		}
		$core = trim( $detail, " .\t" );
		if ( '' !== $core && str_contains( $record['seen'], '{d}' ) && ! str_contains( $g['seen'], $core ) ) {
			$guide_bad[] = "{$code}: جزئیات «{$core}» در «چه دیدیم» نیامد";
		}
		if ( 1 === preg_match( '/افزونه دیگر|یک افزونه/u', $text ) ) {
			$guide_bad[] = "{$code}: «افزونه دیگر» در دستور";
		}
		if ( '' === $g['verify'] || ! isset( HODIMA_SEO_ADVISOR_NEED_LABELS[ $g['need'] ] ) ) {
			$guide_bad[] = "{$code}: بدون «از کجا بفهمم» یا «لازم است؟»";
		}
	}
	foreach ( $record['steps'] as $step ) {
		if ( str_contains( $step, '{where}' ) && ! str_contains( implode( ' ', hodima_seo_advisor_issue_guide( $code, '', 'ویرایش دسته' )['steps'] ), 'ویرایش دسته' ) ) {
			$guide_bad[] = "{$code}: نام «ویرایش» صفحه جایگزین نشد";
		}
	}
}
hodima_ck( 'هر ایراد راهنمای کامل دارد و با جزئیات واقعی جمله سالم می‌سازد (بدون عدد چسبیده، جانگهدار، «افزونه دیگر»)', [] === $guide_bad, implode( ' | ', $guide_bad ) );
hodima_ck( '«لازم است؟» از سطح همین صفحه: قرمز باید، کهربایی بهتر است، اطلاع اختیاری (no_meta کم‌بازدید = اختیاری)', [ 'must', 'rec', 'opt', null ] === ( $ckv = [ hodima_seo_advisor_issue_guide( 'server_error' )['need'], hodima_seo_advisor_issue_guide( 'no_meta' )['need'], hodima_seo_advisor_issue_guide( 'no_meta', '', '', 'info' )['need'], hodima_seo_advisor_issue_guide( 'x_none' ) ] ), var_export( $ckv, true ) );
hodima_ck( '«از کجا بفهمم» از نوع سنجش «انجام دادم»: لحظه‌ای / خواندن دوباره صفحه / خزش بعدی', [ true, true, true, true ] === ( $ckv = [ str_contains( hodima_seo_advisor_issue_verify_text( 'no_title' ), 'همان لحظه صفحه را از سایت' ), str_contains( hodima_seo_advisor_issue_verify_text( 'img_alt' ), 'دوباره می‌خواند' ), str_contains( hodima_seo_advisor_issue_verify_text( 'orphan' ), 'خزش شبانه' ), str_contains( hodima_seo_advisor_issue_verify_text( 'server_error' ), 'همان لحظه' ) ] ), var_export( $ckv, true ) );
hodima_ck( 'دستورهای غلط قبلی رفع شد: no_snippet تب «ایندکس / نوایندکس» و «حداکثر طول متن نتیجه»؛ no_canonical فیلد «آدرس قانونی»', [ true, false, true ] === ( $ckv = [ str_contains( hodima_seo_advisor_issue_fix( 'no_snippet' ), 'تب «ایندکس / نوایندکس»' ) && str_contains( hodima_seo_advisor_issue_fix( 'no_snippet' ), '«حداکثر طول متن نتیجه»' ), str_contains( hodima_seo_advisor_issue_fix( 'no_snippet' ), 'پیشرفته' ), str_contains( hodima_seo_advisor_issue_fix( 'no_canonical' ), '«آدرس قانونی»' ) ] ), var_export( $ckv, true ) );

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
// کد بی‌گروه در فیلتر چیپ‌های «کارها» دیده نمی‌شود. شکاف img_empty (از SEO 3.4.9) در SEO 3.8.1 بسته شد: حالا هیچ کدی بی‌گروه نیست.
$ungrouped = array_values( array_diff( $catalog, $grouped ) );
hodima_ck( 'هر کد کاتالوگ گروه چیپ دارد (img_empty در «تصویر و محتوا»)', [] === $ungrouped && in_array( 'img_empty', HODIMA_SEO_ADVISOR_CRAWL_GROUPS['content'], true ), implode( '، ', $ungrouped ) );

// شکاف شناخته‌شده: کدهایی که در هیچ بخش نمره نیستند (img_empty «بررسی کنید» عمدا بی‌نمره است)؛ کد تازه بی‌نمره رد می‌شود
$unscored = array_values( array_diff( $catalog, $scored ) );
echo '  ℹ کدهای بی‌بخش نمره: ' . ( $unscored ? implode( '، ', $unscored ) : 'هیچ' ) . "\n";
hodima_ck( 'کد تازه بی‌بخش نمره نیست (جز img_empty عمدی)', [] === array_diff( $unscored, [ 'img_empty' ] ), implode( '، ', $unscored ) );

echo "=== قانون ثابت پیشنهادهای گوگل (SEO 3.8.2): هر قاعده رکورد، مرجع، تابع خالص و آزمون «دارد/ندارد» دارد\n";
$sg_pages   = [ ...$google_pages, 'traffic-drops' ]; // نشانی‌ها: docs/seo-advisor-rulebook.md بخش «پیشنهادهای گوگل»
$sg_bad     = [];
$sg_codes   = array_merge( ...array_column( HODIMA_SEO_ADVISOR_SUGGEST_RUN, 'codes' ) );
foreach ( HODIMA_SEO_ADVISOR_SUGGEST_RULES as $code => $record ) {
	$ref = (string) ( $record['ref'] ?? '' );
	if ( '' === (string) ( $record['label'] ?? '' ) || ! in_array( $record['effort'] ?? 0, [ 1, 2, 3 ], true ) ) {
		$sg_bad[] = "{$code}: نام یا سختی نامعتبر";
	}
	foreach ( '' === $ref ? [ '' ] : explode( '|', $ref ) as $part ) {
		if ( str_starts_with( $part, 'google:' ) ) {
			$unknown = array_diff( array_filter( explode( ',', substr( $part, 7 ) ) ), $sg_pages );
			if ( $unknown || 'google:' === $part ) {
				$sg_bad[] = "{$code}: صفحه گوگل ناشناخته " . implode( ',', $unknown );
			}
		} elseif ( 'own' !== $part && ! str_starts_with( $part, 'refs:' ) ) {
			$sg_bad[] = "{$code}: مرجع نامعتبر «{$part}»";
		}
	}
	if ( ! in_array( $code, $sg_codes, true ) ) {
		$sg_bad[] = "{$code}: هیچ تابعی آن را نمی‌سازد";
	}
}
foreach ( HODIMA_SEO_ADVISOR_SUGGEST_RUN as $key => $run ) {
	if ( ! function_exists( $run['fn'] ) ) {
		$sg_bad[] = "{$key}: تابع {$run['fn']} نیست";
	}
	foreach ( array_diff( $run['codes'], array_keys( HODIMA_SEO_ADVISOR_SUGGEST_RULES ) ) as $code ) {
		$sg_bad[] = "{$key}: کد بی‌رکورد {$code}";
	}
}
hodima_ck( 'هر پیشنهاد گوگل نام، سختی ۱ تا ۳، مرجع (صفحه شناخته‌شده گوگل یا own) و تابع سازنده دارد؛ هر کد تابع رکورد دارد', [] === $sg_bad, implode( ' | ', $sg_bad ) );
// فهرست‌های متنی جدا (راهنما، پیوند جزئیات، سنجش اثر) فقط به قاعده ثبت‌شده اشاره کنند
foreach ( [ 'advisor-detail-view.php', 'advisor-help.php' ] as $sg_file ) { // فقط در پیشخوان لود می‌شوند
	require_once HODIMA_SEO_DIR . '/core/advisor/' . $sg_file;
}
$sg_lists = [ 'STEPS_HINTS' => array_keys( HODIMA_SEO_ADVISOR_STEPS_HINTS ), 'VERIFY_RULES' => HODIMA_SEO_ADVISOR_VERIFY_RULES ];
$sg_stray = [];
foreach ( $sg_lists as $name => $keys ) {
	foreach ( array_diff( $keys, array_keys( HODIMA_SEO_ADVISOR_SUGGEST_RULES ) ) as $code ) {
		$sg_stray[] = "{$name}:{$code}";
	}
}
if ( function_exists( 'hodima_seo_advisor_rule_guide' ) ) {
	foreach ( array_keys( HODIMA_SEO_ADVISOR_SUGGEST_RULES ) as $code ) {
		if ( '' === hodima_seo_advisor_rule_guide( $code )['why'] ) {
			$sg_stray[] = "rule_guide:{$code} بی‌توضیح";
		}
	}
}
hodima_ck( 'فهرست‌های راهنما و سنجش فقط قاعده ثبت‌شده دارند و هر قاعده «چرا» دارد', [] === $sg_stray, implode( '، ', $sg_stray ) );

// «دارد/ندارد» هر تابع (خالص: فقط ورودی؛ جدول نرخ کلیک عمومی)
$sg_ctr = get_option( HODIMA_SEO_ADVISOR_CTR_OPTION, null );
delete_option( HODIMA_SEO_ADVISOR_CTR_OPTION );
$sg_q   = static fn( string $term, int $impressions, float $position, int $clicks = 0 ): array => [ 'term' => $term, 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
$sg_in  = static fn( array $over = [] ): array => $over + [ 'row' => [ 'path' => '/sg/', 'clicks' => 10, 'impressions' => 500, 'position' => 30.0, 'prev_clicks' => 0 ], 'queries' => [], 'text' => null, 'competitors' => [], 'site' => null, 'meta' => null, 'links' => null, 'cfg' => hodima_seo_advisor_config() ];
$sg_row = static fn( array $over ): array => $over + [ 'path' => '/sg/', 'clicks' => 10, 'impressions' => 500, 'position' => 30.0, 'prev_clicks' => 0 ];
$sg_cases = [
	[ 'نرخ کلیک کم: ۵۰۰۰ نمایش، رتبه ۶، ۵ کلیک ← low_ctr', 'low_ctr', [ 'row' => $sg_row( [ 'clicks' => 5, 'impressions' => 5000, 'position' => 6.0 ] ) ], [ 'low_ctr' ] ],
	[ 'نرخ کلیک خوب (۳۰۰ کلیک) ← هیچ', 'low_ctr', [ 'row' => $sg_row( [ 'clicks' => 300, 'impressions' => 5000, 'position' => 6.0 ] ) ], [] ],
	[ 'زیر حداقل نمایش صفحه ← هیچ', 'low_ctr', [ 'row' => $sg_row( [ 'clicks' => 0, 'impressions' => 900, 'position' => 6.0 ] ) ], [] ],
	[ 'کلمه در متن، نه در تیتر ← missing_headings', 'missing', [ 'queries' => [ $sg_q( 'کش مو', 800, 5.0 ) ], 'text' => [ 'title_headings' => 'دیگر', 'content' => 'کش مو' ] ], [ 'missing_headings' ] ],
	[ 'کلمه در متن هم نیست ← missing_content', 'missing', [ 'queries' => [ $sg_q( 'کش مو', 800, 5.0 ) ], 'text' => [ 'title_headings' => 'دیگر', 'content' => 'دیگر' ] ], [ 'missing_content' ] ],
	[ 'کلمه در تیتر هست ← هیچ', 'missing', [ 'queries' => [ $sg_q( 'کش مو', 800, 5.0 ) ], 'text' => [ 'title_headings' => 'کش مو', 'content' => '' ] ], [] ],
	[ 'متن صفحه ناشناخته ← هیچ', 'missing', [ 'queries' => [ $sg_q( 'کش مو', 800, 5.0 ) ] ], [] ],
	[ 'کلمه رتبه ۳۰ (عقب‌تر از صفحه دوم) ← missing نه', 'missing', [ 'queries' => [ $sg_q( 'کش مو', 800, 30.0 ) ], 'text' => [ 'title_headings' => '', 'content' => '' ] ], [] ],
	[ 'رتبه ۱۲ ← near_first', 'near_first', [ 'queries' => [ $sg_q( 'گیره', 900, 12.0 ) ] ], [ 'near_first' ] ],
	[ 'رتبه ۵ (صفحه اول) ← near_first نه', 'near_first', [ 'queries' => [ $sg_q( 'گیره', 900, 5.0 ) ] ], [] ],
	[ 'کلمه کم‌نمایش (۲۰۰) ← near_first نه', 'near_first', [ 'queries' => [ $sg_q( 'گیره', 200, 12.0 ) ] ], [] ],
	[ 'رتبه ۳۵ ← weak_query', 'weak_query', [ 'queries' => [ $sg_q( 'تل', 700, 35.0 ) ] ], [ 'weak_query' ] ],
	[ 'رتبه ۷۰ (بیش از سقف ۶۰) ← هیچ', 'weak_query', [ 'queries' => [ $sg_q( 'تل', 700, 70.0 ) ] ], [] ],
	[ 'کلمه برندی ← هیچ', 'weak_query', [ 'queries' => [ $sg_q( strtolower( (string) get_bloginfo( 'name' ) ) . ' تل', 700, 35.0 ) ] ], [] ],
	[ 'صفحه دیگر برای همان کلمه ← cannibal', 'cannibal', [ 'queries' => [ $sg_q( 'کش مو', 400, 25.0 ) ], 'competitors' => [ hodima_seo_advisor_term_key( 'کش مو' ) => [ [ 'path' => '/a/', 'impressions' => 9 ] ] ] ], [ 'cannibal' ] ],
	[ 'بی رقیب ← هیچ', 'cannibal', [ 'queries' => [ $sg_q( 'کش مو', 400, 25.0 ) ] ], [] ],
	[ 'افت ۸۰٪ کلیک با رتبه بدتر ← decline', 'decline', [ 'row' => $sg_row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 900, 'prev_impressions' => 950, 'position' => 15.0, 'prev_position' => 8.0 ] ) ], [ 'decline' ] ],
	[ 'افت ۱۰٪ ← هیچ', 'decline', [ 'row' => $sg_row( [ 'clicks' => 90, 'prev_clicks' => 100, 'impressions' => 900, 'position' => 8.0 ] ) ], [] ],
	[ 'کل سایت همان‌قدر افت کرده ← هیچ', 'decline', [ 'row' => $sg_row( [ 'clicks' => 40, 'prev_clicks' => 100, 'impressions' => 1000, 'prev_impressions' => 1000, 'position' => 8.2, 'prev_position' => 8.0 ] ), 'site' => [ 'clicks_ratio' => 0.45, 'impressions_ratio' => 0.9 ] ], [] ],
];
$sg_tested = [];
foreach ( $sg_cases as [ $name, $key, $over, $expect ] ) {
	$got = array_column( ( HODIMA_SEO_ADVISOR_SUGGEST_RUN[ $key ]['fn'] )( $sg_in( $over ) ), 'rule' );
	hodima_ck( $name, $expect === $got, 'انتظار ' . implode( ',', $expect ) . ' ← ' . implode( ',', $got ) );
	$sg_tested[ $key ][ $expect ? 'has' : 'not' ] = true;
	foreach ( $expect as $code ) {
		$sg_tested[ 'code:' . $code ] = true;
	}
}
$sg_gap = [];
foreach ( HODIMA_SEO_ADVISOR_SUGGEST_RUN as $key => $run ) {
	if ( empty( $sg_tested[ $key ]['has'] ) || empty( $sg_tested[ $key ]['not'] ) ) {
		$sg_gap[] = $key;
	}
}
foreach ( array_keys( HODIMA_SEO_ADVISOR_SUGGEST_RULES ) as $code ) {
	if ( empty( $sg_tested[ 'code:' . $code ] ) ) {
		$sg_gap[] = 'code:' . $code;
	}
}
hodima_ck( 'هر تابع پیشنهاد دست‌کم یک «دارد» و یک «ندارد» و هر کد دست‌کم یک «دارد» دارد', [] === $sg_gap, implode( '، ', $sg_gap ) );

echo "=== گام‌های «دقیقا چه کنم» با خزش (SEO 3.8.3): گامی که انجام شده نمی‌آید\n";
$sg_page  = static fn( array $over = [] ): array => $over + [ 'title' => 'کش مو فنری | هدهد', 'h1' => 'کش مو فنری', 'h2' => [ 'مشخصات', 'نحوه ارسال' ], 'words' => 400, 'meta_len' => 120, 'index' => null ];
$sg_todo  = static fn( string $key, array $over ): array => array_merge( ...array_map( static fn( array $i ): array => $i['todo'], ( HODIMA_SEO_ADVISOR_SUGGEST_RUN[ $key ]['fn'] )( $sg_in( $over ) ) ) );
$sg_has   = static fn( array $todo, string $needle ): bool => (bool) array_filter( $todo, static fn( string $t ): bool => str_contains( $t, $needle ) );
$sg_ctr_r = $sg_row( [ 'clicks' => 20, 'impressions' => 9000, 'position' => 9.0 ] );
$sg_ctr_q = [ $sg_q( 'کش مو فنری', 4000, 4.0, 5 ) ];
hodima_ck( 'نرخ کلیک: عنوان با کلمه شروع می‌شود ← گام «عنوان سئو» نمی‌آید', ! $sg_has( $sg_todo( 'low_ctr', [ 'row' => $sg_ctr_r, 'queries' => $sg_ctr_q, 'page' => $sg_page( [ 'title' => 'کش مو فنری رنگی' ] ) ] ), 'عنوان سئو' ) );
hodima_ck( 'نرخ کلیک: کلمه وسط عنوان ← عنوان فعلی نام برده و «به ابتدای عنوان ببرید»', $sg_has( $sg_todo( 'low_ctr', [ 'row' => $sg_ctr_r, 'queries' => $sg_ctr_q, 'page' => $sg_page( [ 'title' => 'خرید کش مو فنری' ] ) ] ), 'عنوانی که گوگل الان می‌بیند «خرید کش مو فنری» کلمه «کش مو فنری» را دارد ولی در ابتدا نیست' ) );
hodima_ck( 'نرخ کلیک: متای خالی طبق خزش ← «توضیحات متا» این صفحه خالی است', $sg_has( $sg_todo( 'low_ctr', [ 'row' => $sg_ctr_r, 'queries' => $sg_ctr_q, 'page' => $sg_page( [ 'meta_len' => 0 ] ) ] ), 'این صفحه خالی است' ) );
// SEO 3.9.9: نیت جستجو (داده واقعی ۳ ماه Search Console سایت: «کلیپس» ۱۷٬۲۶۳ نمایش با ۰٫۹۸٪ در برابر «کلیپس عمده» ۵۸۴ نمایش با ۳۱٪)
$it_row = $sg_row( [ 'clicks' => 340, 'impressions' => 40000, 'position' => 6.2 ] );
$it_q   = [ $sg_q( 'کلیپس', 17263, 6.08, 170 ), $sg_q( 'کلیپس عمده', 584, 4.34, 181 ), $sg_q( 'گیره مو', 3000, 6.0, 30 ) ];
$it_run = static fn( array $queries, array $row = [] ): array => ( HODIMA_SEO_ADVISOR_SUGGEST_RUN['low_ctr']['fn'] )( $sg_in( [ 'row' => $it_row, 'queries' => $queries ] ) )[0] ?? [];
$it_got = $it_run( $it_q );
hodima_ck( 'نیت: «کلیپس» کم‌کلیک و «کلیپس عمده» پرکلیک در همان صفحه ← شاهد با هر دو عدد در «چه دیدیم» و intro گزارش', [ true, true, true, true ] === [ str_contains( $it_got['seen'] ?? '', '«کلیپس عمده»' ), str_contains( $it_got['seen'] ?? '', 'خریدار تکی' ), str_contains( $it_got['seen'] ?? '', 'نه مرجع رسمی گوگل' ), '' !== (string) ( $it_got['report']['intro'] ?? '' ) ] );
hodima_ck( 'نیت: گام اول روشن‌کردن مخاطب عمده و گام «لازم نیست» برای صفحه تکی‌نفروش', [ true, true ] === [ str_starts_with( $it_got['todo'][0] ?? '', 'اگر فقط عمده می‌فروشید' ), $sg_has( $it_got['todo'] ?? [], 'چون تکی نمی‌فروشید' ) ] );
hodima_ck( 'نیت: بدون «کلیپس عمده» در کلمه‌های صفحه ← بی شاهد (کارت مثل قبل)', '' === (string) ( $it_run( [ $sg_q( 'کلیپس', 17263, 6.08, 170 ), $sg_q( 'گیره مو', 3000, 6.0, 30 ) ] )['report']['intro'] ?? '' ) );
hodima_ck( 'نیت: جفت با نمایش کم (۱۰ نمایش) ← بی شاهد', '' === (string) ( $it_run( [ $sg_q( 'کلیپس', 17263, 6.08, 170 ), $sg_q( 'کلیپس عمده', 10, 4.0, 5 ) ] )['report']['intro'] ?? '' ) );
hodima_ck( 'نیت: جفت با نرخ کلیک پایین (۵٪، زیر ۱۰٪) ← بی شاهد', '' === (string) ( $it_run( [ $sg_q( 'کلیپس', 17263, 6.08, 170 ), $sg_q( 'کلیپس عمده', 600, 4.0, 30 ) ] )['report']['intro'] ?? '' ) );
hodima_ck( 'نیت: کلمه بی‌نشانه که خودش کم‌کلیک نیست (۵٪ در رتبه ۶) ← بی شاهد', '' === (string) ( $it_run( [ $sg_q( 'کلیپس', 17263, 6.08, 860 ), $sg_q( 'کلیپس عمده', 584, 4.34, 181 ) ] )['report']['intro'] ?? '' ) );
hodima_ck( 'نیت: کلمه اصلی خودش «عمده» دارد (کلیپس عمده کم‌کلیک و «فروش عمده کلیپس» پرکلیک) ← بی شاهد', '' === (string) ( $it_run( [ $sg_q( 'کلیپس عمده', 17263, 6.08, 170 ), $sg_q( 'فروش عمده کلیپس', 584, 4.34, 181 ) ] )['report']['intro'] ?? '' ) );
hodima_ck( 'نیت: جفت از کلمه دیگر (گیره مو با «پخش عمده کش») ← بی شاهد', '' === (string) ( $it_run( [ $sg_q( 'گیره مو', 17263, 6.08, 170 ), $sg_q( 'پخش عمده کش', 584, 4.34, 181 ) ] )['report']['intro'] ?? '' ) );
$it_brand = (string) ( hodima_seo_advisor_brand_terms()[0] ?? '' );
hodima_ck( 'نیت: کلمه برند کم‌کلیک شاهد نمی‌سازد (نام سایت در برابر نام سایت + عمده)', '' !== $it_brand && hodima_seo_advisor_is_brand( $it_brand ) && '' === (string) ( $it_run( [ $sg_q( $it_brand, 17263, 6.08, 170 ), $sg_q( $it_brand . ' عمده', 584, 4.34, 181 ) ] )['report']['intro'] ?? '' ), $it_brand );
hodima_ck( 'نیت: نشانه «پخش» و «واردکننده» (املا بدون فاصله) هم شمرده می‌شود', [ 'گیره مو' => true, 'کش مو' => true ] === [ 'گیره مو' => '' !== (string) ( $it_run( [ $sg_q( 'گیره مو', 17263, 6.08, 170 ), $sg_q( 'پخش گیره مو', 300, 4.0, 90 ) ] )['report']['intro'] ?? '' ), 'کش مو' => '' !== (string) ( $it_run( [ $sg_q( 'کش مو', 17263, 6.08, 170 ), $sg_q( 'واردکننده کش مو', 300, 4.0, 90 ) ] )['report']['intro'] ?? '' ) ] );
hodima_ck( 'نیت: فیلتر hodima_seo_advisor_intent_cues نشانه تازه می‌افزاید', ( static function () use ( $it_run, $sg_q ): bool {
	add_filter( 'hodima_seo_advisor_intent_cues', $cb = static fn( array $c ): array => [ ...$c, 'تامین' ] );
	$ok = '' !== (string) ( $it_run( [ $sg_q( 'کلیپس', 17263, 6.08, 170 ), $sg_q( 'تامین کلیپس', 300, 4.0, 90 ) ] )['report']['intro'] ?? '' );
	remove_filter( 'hodima_seo_advisor_intent_cues', $cb );
	return $ok;
} )() );
hodima_ck( 'نیت: کارت بی‌شاهد بدون intro و «اگر فقط عمده می‌فروشید» (رفتار قبلی)', [ false, '' ] === [ str_starts_with( ( $it_run( [ $sg_q( 'کلیپس', 17263, 6.08, 170 ) ] )['todo'][0] ?? '' ), 'اگر فقط عمده می‌فروشید' ), (string) ( $it_run( [ $sg_q( 'کلیپس', 17263, 6.08, 170 ) ] )['report']['intro'] ?? '' ) ] );
$sg_near = static fn( array $page, array $links = null ): array => $sg_todo( 'near_first', [ 'queries' => [ $sg_q( 'کش مو فنری', 900, 11.0 ) ], 'page' => $page, 'links' => $links ] );
hodima_ck( 'نزدیک صفحه اول: کلمه در عنوان ← گام عنوان نمی‌آید', ! $sg_has( $sg_near( $sg_page() ), 'عنوان سئو' ) );
hodima_ck( 'نزدیک صفحه اول: تیتر ۲ با کلمه هست ← «تیتر ۲ بسازید» نمی‌آید', ! $sg_has( $sg_near( $sg_page( [ 'h2' => [ 'چرا کش مو فنری؟' ] ] ) ), 'تیتر ۲ با «کش مو فنری» بسازید' ) );
hodima_ck( 'نزدیک صفحه اول: لینک‌های بی‌کلمه ← متن لینک‌های فعلی نام برده می‌شود', $sg_has( $sg_near( $sg_page(), [ 'total' => 4, 'text' => 4, 'score' => 0.5, 'depth' => 2, 'anchors' => [ [ 'anchor' => 'اینجا', 'count' => 2 ] ] ] ), '(مثلا «اینجا»)' ) );
hodima_ck( 'کلمه در متن نیست: متن کم طبق خزش ← شمار کلمه و «دست‌کم ۵۰ کلمه»', $sg_has( $sg_todo( 'missing', [ 'queries' => [ $sg_q( 'کش مو بچگانه', 800, 5.0 ) ], 'text' => [ 'title_headings' => '', 'content' => '' ], 'page' => $sg_page( [ 'words' => 60 ] ) ] ), 'دست‌کم ۵۰ کلمه' ) );
$sg_dec = static fn( array $index ): array => $sg_todo( 'decline', [ 'row' => $sg_row( [ 'clicks' => 20, 'prev_clicks' => 100, 'impressions' => 300, 'prev_impressions' => 1200, 'position' => 8.2, 'prev_position' => 8.0 ] ), 'page' => $sg_page( [ 'index' => $index ] ) ] );
hodima_ck( 'افت نمایش: ایندکس سالم ← «فیلتر ایندکس را ببینید» نمی‌آید و «مشکل ایندکس نیست» می‌آید', ! $sg_has( $sg_dec( [ 'state' => 'indexed', 'days' => 3, 'coverage' => '', 'google_canonical' => '', 'mismatch' => false ] ), 'فیلتر «ایندکس» را ببینید' ) && $sg_has( $sg_dec( [ 'state' => 'indexed', 'days' => 3, 'coverage' => '', 'google_canonical' => '', 'mismatch' => false ] ), 'مشکل ایندکس نیست' ) );
hodima_ck( 'افت نمایش: ایندکس‌نشده ← علت با گفته گوگل، بی گام متناقض «فصلی است؟»', [ true, false ] === [ $sg_has( $sg_dec( [ 'state' => 'crawled_not_indexed', 'days' => 0, 'coverage' => 'Crawled - currently not indexed', 'google_canonical' => '', 'mismatch' => false ] ), 'Crawled - currently not indexed' ), $sg_has( $sg_dec( [ 'state' => 'crawled_not_indexed', 'days' => 0, 'coverage' => 'x', 'google_canonical' => '', 'mismatch' => false ] ), 'فصلی' ) ] );

// خواندن واقعیت‌ها از جدول خزش و بررسی ایندکس (تنها بخش ناخالص)
global $wpdb;
$sg_t   = hodima_seo_advisor_table( 'crawl_pages' );
$wpdb->delete( $sg_t, [ 'path' => '/sg-facts/' ] );
$wpdb->insert( $sg_t, [ 'path' => '/sg-facts/', 'status' => 200, 'indexable' => 1, 'title' => 'کش مو | هدهد', 'h1' => 'کش مو', 'h2' => 'مشخصات | ارسال', 'words' => 75, 'meta_len' => 0 ] );
hodima_seo_advisor_crawl_map( true );
$sg_ins = get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, null );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/sg-facts/' => [ 'verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'robots' => '', 'indexing' => '', 'fetch' => '', 'last_crawl' => '', 'google_canonical' => '', 'user_canonical' => '', 'crawled_as' => '', 'checked' => time() - 2 * DAY_IN_SECONDS - 60 ] ], false );
$sg_f = hodima_seo_advisor_suggest_page_facts( '/sg-facts/' );
hodima_ck( 'واقعیت‌های صفحه از خزش و بررسی ایندکس: عنوان، تیترهای ۲، کلمه، متا، ایندکس ۲ روز پیش', [ 'کش مو | هدهد', [ 'مشخصات', 'ارسال' ], 75, 0, 'indexed', 2 ] === [ $sg_f['title'] ?? null, $sg_f['h2'] ?? null, $sg_f['words'] ?? null, $sg_f['meta_len'] ?? null, $sg_f['index']['state'] ?? null, $sg_f['index']['days'] ?? null ], wp_json_encode( $sg_f, JSON_UNESCAPED_UNICODE ) );
hodima_ck( 'صفحه خزیده‌نشده ← null (گام کلی)', null === hodima_seo_advisor_suggest_page_facts( '/sg-none/' ) );
$wpdb->delete( $sg_t, [ 'path' => '/sg-facts/' ] );
hodima_seo_advisor_crawl_map( true );
null === $sg_ins ? delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION ) : update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $sg_ins, false );
null === $sg_ctr ? delete_option( HODIMA_SEO_ADVISOR_CTR_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CTR_OPTION, $sg_ctr, false );

echo "=== SEO 3.9.3: باگ‌های گزارش دستار (هدر robots ربات دیگر، نمره سراسری، نقشه سایت، برند، کلمه ربط، base)\n";

// باگ ۳: X-Robots-Tag مخصوص ربات دیگر
$rg = static fn( string $h ): string => hodima_seo_advisor_robots_for_google( $h );
hodima_ck( 'X-Robots-Tag: «bingbot: noindex» برای گوگل چیزی نیست', '' === $rg( 'bingbot: noindex' ) );
hodima_ck( 'X-Robots-Tag: دستور بی‌نام و «googlebot: noindex» برای گوگل است', 'noindex' === $rg( 'noindex' ) && 'noindex' === $rg( 'googlebot: noindex' ) );
hodima_ck( 'X-Robots-Tag: اسم ربات تا اسم بعدی اثر دارد («bingbot: noindex, nofollow, googlebot: nosnippet» ← فقط nosnippet)', 'nosnippet' === $rg( 'bingbot: noindex, nofollow, googlebot: nosnippet' ), $rg( 'bingbot: noindex, nofollow, googlebot: nosnippet' ) );
hodima_ck( 'X-Robots-Tag: max-snippet:0 و unavailable_after نام ربات حساب نمی‌شوند', str_contains( $rg( 'max-snippet:0' ), 'max-snippet:0' ) && str_contains( $rg( 'unavailable_after: 25 Jun 2030 15:00:00 PST' ), 'unavailable_after' ) );
$rg_row = static fn( string $x ): array => hodima_seo_advisor_crawler_page_row( '/p/', 200, 100, hodima_seo_advisor_crawler_parse( '<html><head><title>t</title></head><body><main><h1>x</h1></main></body></html>', 'https://hodima.test/p/' ), '', $x );
hodima_ck( 'ردیف صفحه: هدر «bingbot: noindex» صفحه را ایندکس‌پذیر نگه می‌دارد (باگ قبلی: noindex گوگل حساب می‌شد)', 1 === $rg_row( 'bingbot: noindex' )['indexable'] && '' === $rg_row( 'bingbot: noindex' )['reason'] );
hodima_ck( 'ردیف صفحه: هدر بی‌نام و googlebot هنوز noindex می‌کند', [ 0, 0 ] === [ $rg_row( 'noindex' )['indexable'], $rg_row( 'googlebot: noindex' )['indexable'] ] );

// باگ ۴ و ۵: ردیف صفحه canonical_url
$cv_row = static fn( string $canonical ): array => hodima_seo_advisor_crawler_page_row( '/p/', 200, 100, hodima_seo_advisor_crawler_parse( '<html><head><title>t</title><link rel="canonical" href="' . $canonical . '"></head><body><main><h1>x</h1></main></body></html>', 'https://hodima.test/p/' ), '', '' );
$cv_ext = $cv_row( 'https://other.example/p/' );
$cv_var = $cv_row( 'http://hodima.test/p/' );
$cv_prm = $cv_row( 'https://hodima.test/p/?color=red' );
$cv_ok  = $cv_row( 'https://hodima.test/p/' );
hodima_ck( 'خزنده: canonical دامنه دیگر ← «!ext» و آدرس کامل در canonical_url', '!ext' === $cv_ext['canonical'] && 'https://other.example/p/' === $cv_ext['canonical_url'], wp_json_encode( [ $cv_ext['canonical'], $cv_ext['canonical_url'] ] ) );
hodima_ck( 'خزنده: canonical با http و با پارامتر ← مسیر /p/ ولی canonical_url پر؛ canonical درست ← canonical_url خالی', '/p/' === $cv_var['canonical'] && 'http://hodima.test/p/' === $cv_var['canonical_url'] && 'https://hodima.test/p/?color=red' === $cv_prm['canonical_url'] && '' === $cv_ok['canonical_url'] );
hodima_ck( 'خزنده: canonical مسیر بلندتر از ستون در همین سایت ← «!long» (قبلا «!ext» و «سایت دیگر» می‌شد)', '!long' === $cv_row( 'https://hodima.test/' . str_repeat( 'a', 250 ) . '/' )['canonical'] );
$cv_ctx = [ 'site' => [ 'https', 'hodima.test' ] ];
$cv_e2e = hodima_seo_advisor_checks_run( hodima_ck_row( $cv_prm + [ 'inlinks' => 9, 'impressions' => 0, 'kind' => 'page', 'canonical_url' => $cv_prm['canonical_url'] ] ), hodima_ck_ctx( $cv_ctx ) );
hodima_ck( 'زنجیره کامل: ردیف خزنده با canonical پارامتری ← canonical_variant در خروجی checks_run', in_array( 'canonical_variant', array_column( $cv_e2e, 'code' ), true ), wp_json_encode( array_column( $cv_e2e, 'code' ) ) );

// باگ ۱: سقف نمره
$cap_site = hodima_seo_advisor_score( array_merge( [ [ 'path' => '/', 'issues' => [ [ 'code' => 'site_hidden' ] ] ] ], array_map( static fn( int $i ): array => $sc_row2( $i, [ 'title_short' ] ), range( 1, 299 ) ) ), 300 );
hodima_ck( 'نمره: سایت ۳۰۰ صفحه‌ای پنهان از گوگل (site_hidden) نمره کل ≤ ۴۰ می‌گیرد (قبلا ۱۰۰ «عالی»)', $cap_site['score'] <= 40 && 'site_hidden' === $cap_site['cap'] && 'site_hidden' === ( $cap_site['top'][0]['code'] ?? '' ), wp_json_encode( $cap_site ) );
$cap_home = hodima_seo_advisor_score( [ [ 'path' => '/', 'issues' => [ [ 'code' => 'server_error' ] ] ] ], 300 );
hodima_ck( 'نمره: خطای ۵۰۰ صفحه اصلی نمره را به ۴۰ می‌رساند (قبلا ۱۰۰)', 40 === $cap_home['score'] && 'server_error' === $cap_home['cap'], wp_json_encode( $cap_home ) );
$cap_other = hodima_seo_advisor_score( [ [ 'path' => '/other/', 'issues' => [ [ 'code' => 'server_error' ] ] ] ], 300 );
hodima_ck( 'نمره: همان خطای ۵۰۰ روی صفحه دیگر نمره را به سقف نمی‌رساند (متناسب می‌ماند)', '' === $cap_other['cap'] && $cap_other['score'] > 90, wp_json_encode( $cap_other ) );
$cap_info = hodima_seo_advisor_score( [ [ 'path' => '/', 'issues' => [ [ 'code' => 'site_hidden', 'level' => 'info' ] ] ] ], 300 );
hodima_ck( 'نمره: ایراد سراسری با level=info (علامت‌خورده) سقف نمی‌گذارد', '' === $cap_info['cap'] && 100 === $cap_info['score'] );

// باگ ۸: ایراد خطا برای صفحه‌ای که فقط در نقشه سایت است
$dead_row = hodima_ck_row( [ 'path' => '/ghost/', 'status' => 500, 'indexable' => 0, 'inlinks' => 0, 'impressions' => 0 ] );
hodima_ck( 'خطای ۵۰۰ نوشته بی‌لینک و بی‌نمایش ولی در نقشه سایت ← server_error (باگ قبلی: بی‌صدا)', in_array( 'server_error', array_column( hodima_seo_advisor_checks_run( $dead_row, hodima_ck_ctx( [ 'sitemap' => [ '/ghost/' => true ] ] ) ), 'code' ), true ) );
hodima_ck( 'همان خطا بی‌لینک، بی‌نمایش، بیرون از نقشه سایت ← هنوز بی‌ایراد (ردیف مانده از دور قبل)', ! in_array( 'server_error', array_column( hodima_seo_advisor_checks_run( $dead_row, hodima_ck_ctx() ), 'code' ), true ) );

// باگ ۶: کلمه ربط
$norm = 'hodima_seo_advisor_normalize';
hodima_ck( '«کش مو برای عروس» در عنوان «خرید عمده کش مو عروس» هست (باگ قبلی: «برای» نیامده بود)', hodima_seo_advisor_text_has( $norm( 'خرید عمده کش مو عروس' ), 'کش مو برای عروس' ) );
hodima_ck( 'کلمه معنی‌دار نبودن هنوز تشخیص داده می‌شود («کش مو برای عروس» در «خرید عمده کش مو» نیست)', ! hodima_seo_advisor_text_has( $norm( 'خرید عمده کش مو' ), 'کش مو برای عروس' ) );
hodima_ck( 'فهرست ربط‌ها یکی است: عنوان مشابه همان را می‌خواند', [] === array_diff( HODIMA_SEO_ADVISOR_CONNECTORS, HODIMA_SEO_ADVISOR_SIMILAR_STOP ) );

// باگ ۷: کلمه برند
$bn_old = get_option( 'blogname' );
update_option( 'blogname', 'هدهدلی | عمده فروشی اکسسوری مو' );
$bn_terms = hodima_seo_advisor_brand_terms();
hodima_ck( 'برند: از نام «هدهدلی | عمده فروشی اکسسوری مو» فقط «هدهدلی» (و دامنه) برند است', in_array( 'هدهدلی', $bn_terms, true ) && ! array_intersect( [ 'عمده', 'فروشی', 'اکسسوری' ], $bn_terms ), wp_json_encode( $bn_terms, JSON_UNESCAPED_UNICODE ) );
hodima_ck( 'برند: «اکسسوری مو عمده» برندی نیست ولی «هدهدلی اکسسوری» برندی است', ! hodima_seo_advisor_is_brand( 'اکسسوری مو عمده' ) && hodima_seo_advisor_is_brand( 'هدهدلی اکسسوری' ) );
update_option( 'blogname', 'هدهدلی' );
hodima_ck( 'برند: نام ساده «هدهدلی» ← همان واژه', in_array( 'هدهدلی', hodima_seo_advisor_brand_terms(), true ) );
update_option( 'blogname', $bn_old );

// باگ ۹: <base href>
$bs_html = '<html><head><title>t</title><base href="https://hodima.test/shop/"></head><body><main><h1>x</h1><a href="item/">آ</a> <a href="/abs/">ب</a> <img src="pic.jpg" alt="ع"></main></body></html>';
$bs      = hodima_seo_advisor_crawler_parse( $bs_html, 'https://hodima.test/blog/post/' );
$bs_dest = array_map( static fn( array $l ): ?string => hodima_seo_advisor_path_key( $l[0] ), $bs['links'] );
hodima_ck( '<base href>: لینک نسبی نسبت به base حل می‌شود (/shop/item/ نه /blog/post/item/) و لینک مطلق دست‌نخورده', [ '/shop/item/', '/abs/' ] === $bs_dest, wp_json_encode( $bs_dest ) );
$bs_none = hodima_seo_advisor_crawler_parse( str_replace( '<base href="https://hodima.test/shop/">', '', $bs_html ), 'https://hodima.test/blog/post/' );
hodima_ck( 'بدون <base>: همان رفتار قبلی (نسبت به آدرس صفحه)', '/blog/post/item/' === hodima_seo_advisor_path_key( $bs_none['links'][0][0] ) );
$bs_bad = hodima_seo_advisor_crawler_parse( str_replace( 'https://hodima.test/shop/', 'javascript:void(0)', $bs_html ), 'https://hodima.test/blog/post/' );
hodima_ck( '<base> نامعتبر نادیده گرفته می‌شود', '/blog/post/item/' === hodima_seo_advisor_path_key( $bs_bad['links'][0][0] ) );

echo "\n";
if ( $failed ) {
	echo "✘ {$failed} آزمون رد شد ({$passed} قبول)\n";
	exit( 1 );
}
echo "✔ همه {$passed} آزمون قاعده‌های ایراد قبول\n";
