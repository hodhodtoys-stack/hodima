<?php
/**
 * ماژول «دستار سئو» — فهرست واحد ایرادهای فنی (SEO 3.3.5)
 * Path: core/advisor/advisor-issues.php
 *
 * هر ایراد خزنده یک رکورد دارد: برچسب، سطح، سختی (۱ = چند دقیقه، ۲ = ویرایش محتوا یا لینک، ۳ = کار بزرگ‌تر یا فنی) و دستور.
 * قبلا برچسب و دستور در advisor-crawl.php یک آرایه بزرگ بودند و هیچ‌کدام نمی‌دانستند صفحه محصول است یا دسته؛ حالا دستور
 * جای «ویرایش» همان نوع صفحه را می‌گیرد (`{where}`؛ مثلا «ویرایش محصول»، «ویرایش دسته»). فقط می‌خواند؛ چیزی را عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * رکورد همه ایرادهای خزنده.
 *
 * @return array<string, array{label: string, level: string, effort: int, fix: string}>
 */
function hodima_seo_advisor_issue_catalog(): array {

	static $catalog = null;

	return $catalog ??= [
		'no_meta'        => [ 'label' => 'بدون توضیح متا', 'level' => 'bad', 'effort' => 1, 'fix' => 'در «{where}» کادر «سئو (SeoBox)» را باز کنید و در «توضیحات متا» یک توضیح ۱۲۰ تا ۱۶۰ نویسه‌ای بنویسید؛ کلمه اصلی صفحه در جمله اول بیاید.' ],
		'no_snippet'     => [ 'label' => 'متن زیر عنوان ممنوع', 'level' => 'bad', 'effort' => 1, 'fix' => 'در «{where}» کادر «سئو (SeoBox)»، بخش «پیشرفته»، حداکثر طول متن (max-snippet) را روی پیش‌فرض بگذارید؛ مقدار ۰ به گوگل می‌گوید زیر عنوان متنی نشان ندهد و نرخ کلیک را کم می‌کند.' ],
		'no_h1'          => [ 'label' => 'بدون H1', 'level' => 'warn', 'effort' => 1, 'fix' => 'هر صفحه یک تیتر اصلی (H1) لازم دارد.' ],
		'no_title'       => [ 'label' => 'بدون عنوان', 'level' => 'bad', 'effort' => 1, 'fix' => 'در «{where}» کادر «سئو (SeoBox)» را باز کنید و «عنوان سئو» بنویسید.' ],
		'title_short'    => [ 'label' => 'عنوان کوتاه', 'level' => 'bad', 'effort' => 1, 'fix' => 'عنوان کمتر از ۳۰ نویسه است و جای کلمه‌های مهم‌تر را خالی می‌گذارد؛ در «{where}» کادر «سئو (SeoBox)» ← «عنوان سئو» را به حدود ۴۸ تا ۶۲ نویسه برسانید.' ],
		'title_long'     => [ 'label' => 'عنوان بلند', 'level' => 'warn', 'effort' => 1, 'fix' => 'عنوان بیش از ۶۵ نویسه است و در گوگل بریده می‌شود؛ در «{where}» کادر «سئو (SeoBox)» ← «عنوان سئو» را به حدود ۴۸ تا ۶۲ نویسه برسانید.' ],
		'dup_title'      => [ 'label' => 'عنوان تکراری', 'level' => 'warn', 'effort' => 2, 'fix' => 'برای هر صفحه عنوان یگانه بنویسید.' ],
		'dup_h1'         => [ 'label' => 'H1 تکراری', 'level' => 'warn', 'effort' => 2, 'fix' => 'تیتر اصلی هر صفحه یگانه باشد.' ],
		'slow'           => [ 'label' => 'کند', 'level' => 'warn', 'effort' => 3, 'fix' => 'کش و سرعت سرور را بررسی کنید.' ],
		'redirect'       => [ 'label' => 'ریدایرکت', 'level' => 'info', 'effort' => 2, 'fix' => 'لینک‌های داخلی را مستقیم به آدرس مقصد بدهید.' ],
		'noindex'        => [ 'label' => 'noindex یا canonical', 'level' => 'info', 'effort' => 1, 'fix' => 'اگر این صفحه باید در گوگل باشد، noindex/canonical را بردارید.' ],
		'broken'         => [ 'label' => 'خطای ۴۰۴/۵۰۰', 'level' => 'bad', 'effort' => 2, 'fix' => 'لینک‌های داخلی به آن را درست کنید یا ریدایرکت بدهید.' ],
		'low_inlinks'    => [ 'label' => 'لینک ورودی کم', 'level' => 'warn', 'effort' => 2, 'fix' => 'از صفحه‌های مرتبط (دسته، مقاله) لینک متنی بدهید.' ],
		'link_broken'    => [ 'label' => 'لینک به صفحه خراب', 'level' => 'bad', 'effort' => 2, 'fix' => 'این صفحه به آدرس‌هایی لینک می‌دهد که خطای ۴۰۴/۵۰۰ می‌دهند؛ هر لینک را به آدرس درست تغییر بدهید یا بردارید.' ],
		'link_redirect'  => [ 'label' => 'لینک به ریدایرکت', 'level' => 'warn', 'effort' => 2, 'fix' => 'این صفحه به آدرس‌هایی لینک می‌دهد که به جای دیگر می‌روند؛ لینک را مستقیم به آدرس مقصد بدهید.' ],
		'link_noindex'   => [ 'label' => 'لینک به صفحه ایندکس‌نشدنی', 'level' => 'info', 'effort' => 2, 'fix' => 'در متن این صفحه به صفحه‌ای لینک داده شده که noindex است یا به صفحه دیگری canonical دارد؛ اگر لازم نیست لینک را بردارید یا به آدرس اصلی بدهید.' ],
		'redirect_chain' => [ 'label' => 'زنجیره ریدایرکت', 'level' => 'warn', 'effort' => 2, 'fix' => 'این آدرس به آدرسی می‌رود که خودش دوباره ریدایرکت می‌شود؛ ریدایرکت را مستقیم به مقصد نهایی بدهید.' ],
		'multi_h1'       => [ 'label' => 'چند H1', 'level' => 'warn', 'effort' => 1, 'fix' => 'هر صفحه فقط یک تیتر اصلی (H1) داشته باشد؛ بقیه را H2 کنید.' ],
		'img_alt'        => [ 'label' => 'تصویر بدون alt', 'level' => 'warn', 'effort' => 2, 'fix' => 'این تصویرهای داخل متن صفحه توضیح (alt) ندارند؛ برای هر کدام یک توضیح کوتاه بنویسید (کتابخانه رسانه یا همان‌جا که تصویر را گذاشته‌اید). بندانگشتی کارت‌هایی که عنوان لینکشان کنارشان است لازم ندارد.' ],
		'thin'           => [ 'label' => 'متن کم', 'level' => 'info', 'effort' => 3, 'fix' => 'متن اصلی این صفحه کم است؛ اگر قرار است در گوگل رتبه بگیرد در «{where}» توضیح کامل‌تر اضافه کنید (برای محصول بخش «توضیحات محصول»).' ],
		'canonical_bad'  => [ 'label' => 'canonical اشتباه', 'level' => 'bad', 'effort' => 2, 'fix' => 'canonical این صفحه به آدرسی اشاره می‌کند که ۲۰۰ نیست یا noindex است؛ آن را به آدرس درست یا خود صفحه برگردانید.' ],
		'no_canonical'   => [ 'label' => 'بدون canonical', 'level' => 'warn', 'effort' => 2, 'fix' => 'صفحه آدرس canonical ندارد؛ در کادر سئو یا افزونه سئو آن را فعال کنید.' ],
		'http_link'      => [ 'label' => 'لینک http', 'level' => 'warn', 'effort' => 2, 'fix' => 'در سایت https لینک داخلی با http می‌دهد؛ لینک‌ها را https کنید تا ریدایرکت اضافه نشود.' ],
		'sitemap_bad'    => [ 'label' => 'ایراد در نقشه سایت', 'level' => 'bad', 'effort' => 2, 'fix' => 'این آدرس در نقشه سایت است ولی صفحه سالم و ایندکس‌پذیر نیست؛ از نقشه سایت بردارید یا صفحه را درست کنید.' ],
		'not_in_sitemap' => [ 'label' => 'خارج از نقشه سایت', 'level' => 'info', 'effort' => 1, 'fix' => 'صفحه سالم و ایندکس‌پذیر است ولی در نقشه سایت نیست؛ اگر باید در گوگل باشد به نقشه سایت اضافه کنید.' ],
		'deep'           => [ 'label' => 'صفحه عمیق', 'level' => 'info', 'effort' => 2, 'fix' => 'از صفحه اصلی چند کلیک فاصله دارد؛ از صفحه‌های نزدیک‌تر (دسته، مقاله) به آن لینک بدهید.' ],
		'orphan'         => [ 'label' => 'بدون لینک ورودی', 'level' => 'bad', 'effort' => 2, 'fix' => 'از دست‌کم دو صفحه مرتبط به آن لینک بدهید.' ],
		'island'         => [ 'label' => 'جدا از صفحه اصلی', 'level' => 'warn', 'effort' => 2, 'fix' => 'از صفحه اصلی با هیچ مسیر لینکی به این صفحه نمی‌رسیم؛ فقط صفحه‌های جدا از سایت به آن لینک می‌دهند. از منو، صفحه اصلی یا یک صفحه پربازدید (دسته، مقاله) به آن لینک بدهید.' ],
	];
}

/**
 * دستور یک ایراد با نام «ویرایش» همان نوع صفحه (مثلا «ویرایش محصول»). `$where` خالی = «ویرایش صفحه».
 */
function hodima_seo_advisor_issue_fix( string $code, string $where = '' ): string {

	$fix = (string) ( hodima_seo_advisor_issue_catalog()[ $code ]['fix'] ?? '' );

	return '' === $fix ? '' : str_replace( '{where}', '' !== $where ? $where : 'ویرایش صفحه', $fix );
}

/**
 * نام «ویرایش» نوع صفحه از برچسب‌های خود وردپرس/ووکامرس (مثلا «ویرایش محصول»، «ویرایش دسته»)؛ صفحه اصلی «ویرایش صفحه اصلی».
 * `$kind` نوع نوشته یا طبقه‌بندی (ستون kind جدول آدرس‌ها)؛ خالی = از روتر پیدا می‌شود. بی‌نتیجه = رشته خالی (دستور «ویرایش صفحه» می‌گوید).
 */
function hodima_seo_advisor_issue_where( string $path, string $kind = '' ): string {

	if ( '/' === $path ) {
		return 'ویرایش صفحه اصلی';
	}

	if ( '' === $kind ) {
		$kind = hodima_seo_advisor_classify( $path )['kind'];
	}

	if ( '' !== $kind && post_type_exists( $kind ) ) {
		$object = get_post_type_object( $kind );
		return null !== $object && '' !== (string) $object->labels->edit_item ? (string) $object->labels->edit_item : '';
	}

	if ( '' !== $kind && taxonomy_exists( $kind ) ) {
		$object = get_taxonomy( $kind );
		return null !== $object && '' !== (string) $object->labels->edit_item ? (string) $object->labels->edit_item : '';
	}

	return '';
}
