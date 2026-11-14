<?php
/**
 * ورود بسته — نوع‌ها و کادرها
 * Path: core/package-import/package-import-types.php
 *
 * هر «نوع» بسته می‌گوید روی ویرایش کدام شیء (نوع نوشته یا تکسونومی) کار می‌کند و کدام کادرها را
 * می‌شناسد: کلید بسته ← «آداپتور» (یک نرمال‌ساز در package-import-check.php و یک آداپتور هم‌نام در
 * package-import.js). کلید یکسان در دو نوع می‌تواند آداپتور متفاوت داشته باشد («video» مقاله =
 * ویدیوی «تنظیمات رسانه»، «video» ویدیو = کادر «تنظیمات ویدیو»). نوع تازه فقط یک ردیف همین‌جاست.
 *
 * قالب: «hodima-package/1» فقط محصول (بسته‌های قبلی بی‌تغییر کار می‌کنند)؛ «hodima-package/2» همه نوع‌ها.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** قالب نسخه ۱ (فقط محصول) و نسخه ۲ (همه نوع‌ها). */
const HODIMA_SEO_PKG_FORMAT    = 'hodima-package/1';
const HODIMA_SEO_PKG_FORMAT_V2 = 'hodima-package/2';

/** بیشترین حجم متن بسته (بایت)؛ بسته واقعی یک محصول ≈ ۲۰ کیلوبایت است. */
const HODIMA_SEO_PKG_MAX_BYTES = 1048576;

/**
 * نوع‌های بسته: شیء (post/term)، نوع نوشته یا تکسونومی، قالب‌های پذیرفته و کادرها به ترتیب
 * صفحه ویرایش (کلید بسته ← آداپتور).
 *
 * @return array<string, array{label: string, object: string, post_type?: string, taxonomy?: string, formats: list<string>, boxes: array<string, string>}>
 */
function hodima_seo_pkg_types(): array {

	$same = static fn( string ...$keys ): array => array_combine( $keys, $keys );

	$types = [
		'product'  => [
			'label'     => 'محصول',
			'object'    => 'post',
			'post_type' => 'product',
			'formats'   => [ HODIMA_SEO_PKG_FORMAT, HODIMA_SEO_PKG_FORMAT_V2 ],
			'boxes'     => $same( 'body', 'upsells', 'cross_sells', 'table', 'faq', 'related_links', 'topic_cluster', 'aeo', 'seobox' ),
		],
		'category' => [
			'label'    => 'دسته محصول',
			'object'   => 'term',
			'taxonomy' => 'product_cat',
			'formats'  => [ HODIMA_SEO_PKG_FORMAT_V2 ],
			'boxes'    => $same( 'intro_html', 'description_html', 'video', 'podcast', 'faq', 'table', 'related_links', 'topic_cluster', 'aeo', 'seobox' ),
		],
		'article'  => [
			'label'     => 'مقاله',
			'object'    => 'post',
			'post_type' => 'post',
			'formats'   => [ HODIMA_SEO_PKG_FORMAT_V2 ],
			'boxes'     => $same( 'body', 'intro_html', 'video', 'podcast', 'faq', 'table', 'related_links', 'topic_cluster', 'discover', 'aeo', 'seobox' ),
		],
		'video'    => [
			'label'     => 'ویدیو',
			'object'    => 'post',
			'post_type' => 'video',
			'formats'   => [ HODIMA_SEO_PKG_FORMAT_V2 ],
			'boxes'     => [ 'body' => 'body', 'video' => 'video_watch', 'suggestions' => 'suggestions', 'product_button' => 'product_button', 'seobox' => 'seobox' ],
		],
	];

	return (array) apply_filters( 'hodima_seo_pkg_types', $types );
}

/** نوع بسته‌ای که ویرایش این شیء (نوشته یا ترم) را پر می‌کند، یا null. */
function hodima_seo_pkg_type_for_object( WP_Post|WP_Term $subject ): ?string {
	foreach ( hodima_seo_pkg_types() as $type => $spec ) {
		$match = $subject instanceof WP_Term
			? 'term' === ( $spec['object'] ?? '' ) && ( $spec['taxonomy'] ?? '' ) === $subject->taxonomy
			: 'term' !== ( $spec['object'] ?? 'post' ) && ( $spec['post_type'] ?? '' ) === $subject->post_type;
		if ( $match ) {
			return (string) $type;
		}
	}
	return null;
}

/** نوع بسته‌ای که این نوع نوشته را ویرایش می‌کند، یا null (سازگاری نسخه ۱). */
function hodima_seo_pkg_type_for( string $post_type ): ?string {
	foreach ( hodima_seo_pkg_types() as $type => $spec ) {
		if ( 'term' !== ( $spec['object'] ?? 'post' ) && ( $spec['post_type'] ?? '' ) === $post_type ) {
			return (string) $type;
		}
	}
	return null;
}

/**
 * نام هر کادر در پیشخوان برای یک نوع (همان نامی که مدیر بالای کادر می‌بیند).
 *
 * @return array<string, string> کلید بسته ← نام
 */
function hodima_seo_pkg_box_labels( string $type = 'product' ): array {

	$labels = [
		'body'             => 'متن اصلی (ویرایشگر)',
		'upsells'          => 'محصولات پیشنهادی (Upsells)',
		'cross_sells'      => 'محصولات مکمل (Cross-sells)',
		'table'            => 'product' === $type ? 'جدول تکمیلی محصول' : 'جدول مشخصات',
		'faq'              => 'پرسش‌های متداول (تنظیمات رسانه)',
		'related_links'    => 'لینک‌های مرتبط',
		'topic_cluster'    => 'خوشه‌بندی محتوا',
		'aeo'              => 'پنل AEO و GEO',
		'seobox'           => 'سئوباکس (عنوان و توضیح)',
		'intro_html'       => 'متن معرفی (تنظیمات رسانه)',
		'description_html' => 'توضیحات دسته',
		'video'            => 'video' === $type ? 'ویدیو (تنظیمات ویدیو)' : 'ویدیو (تنظیمات رسانه)',
		'podcast'          => 'پادکست (تنظیمات رسانه)',
		'discover'         => 'گوگل دیسکاور',
		'suggestions'      => 'ویدیوهای مرتبط (کارت‌های زیر ویدیو)',
		'product_button'   => 'دکمه محصول زیر ویدیو',
	];

	$boxes = hodima_seo_pkg_types()[ $type ]['boxes'] ?? [];
	return array_intersect_key( $labels, $boxes );
}

/**
 * کادرهایی که عمدا در ابزار نیستند (کلیدشان در بسته فقط هشدار می‌دهد)؛ برای هر نوع جدا.
 *
 * @return array<string, string>
 */
function hodima_seo_pkg_out_of_scope( string $type = 'product' ): array {

	$common = [
		'images'     => 'عکس‌ها',
		'alt'        => 'متن جایگزین عکس‌ها',
		'attributes' => 'ویژگی‌های ووکامرس',
		'aeo_media'  => 'رسانه‌های پنل AEO',
		'categories' => 'دسته‌ها',
		'tags'       => 'برچسب‌ها',
	];

	return $common + match ( $type ) {
		'product'  => [ 'discover' => 'گوگل دیسکاور (فقط مقاله)' ],
		'category' => [ 'discover' => 'گوگل دیسکاور (فقط مقاله)', 'body' => 'متن اصلی (دسته «توضیحات» دارد: description_html)' ],
		'video'    => [
			'discover'      => 'گوگل دیسکاور (فقط مقاله)',
			'related_links' => 'لینک‌های مرتبط (برای ویدیو نیست)',
			'topic_cluster' => 'خوشه‌بندی محتوا (برای ویدیو نیست)',
			'aeo'           => 'پنل AEO (برای ویدیو نیست)',
			'download_url'  => 'لینک دانلود (دانلود همان آدرس ویدیو است: video.url)',
			'faq'           => 'پرسش‌ها (تنظیمات رسانه برای ویدیو نیست)',
			'intro_html'    => 'متن معرفی (تنظیمات رسانه برای ویدیو نیست)',
		],
		default    => [],
	};
}

/**
 * شماره‌های تماس سایت؛ لینک tel: دیگر در متن هشدار می‌گیرد (نه توقف).
 *
 * @return list<string>
 */
function hodima_seo_pkg_phones(): array {
	return array_values( array_map( 'strval', (array) apply_filters( 'hodima_seo_pkg_phones', [ '09124093140', '02177322684' ] ) ) );
}

/**
 * نگاشت کلیدهای AEO بسته ← نام خانه‌های فرم پنل AEO (class-hodima-aeo-admin.php).
 *
 * @return array<string, string>
 */
function hodima_seo_pkg_aeo_text_fields(): array {
	return [
		'en_entity'     => 'h_ai_en_entity',
		'fa_aliases'    => 'h_ai_fa_aliases',
		'en_aliases'    => 'h_ai_en_aliases',
		'fa_intent'     => 'h_ai_entities',
		'en_intent'     => 'h_ai_en_synonyms',
		'fa_context'    => 'h_ai_text',
		'en_context'    => 'h_ai_en_context',
		'fa_usecases'   => 'h_ai_usecases',
		'en_usecases'   => 'h_ai_en_usecases',
		'fa_audience'   => 'h_ai_audience',
		'en_audience'   => 'h_ai_en_audience',
		'fa_comparison' => 'h_ai_comparison',
		'en_comparison' => 'h_ai_en_comparison',
		'fa_embedding'  => 'h_ai_fa_embedding_summary',
		'en_embedding'  => 'h_ai_en_embedding_summary',
	];
}

/**
 * خانه‌های پلن A (پسوند خالی) و B (پسوند _t2)؛ مقدار = پیش‌فرض همان خانه در پنل AEO.
 *
 * @return array<string, array{field: string, a: string, b: string}>
 */
function hodima_seo_pkg_aeo_tier_fields(): array {
	return [
		'price'        => [ 'field' => 'h_ai_fa_price', 'a' => '', 'b' => '' ],
		'fa_basis'     => [ 'field' => 'h_ai_fa_price_basis', 'a' => 'کیلوگرم', 'b' => 'بسته' ],
		'en_basis'     => [ 'field' => 'h_ai_en_price_basis', 'a' => 'kg', 'b' => 'Pack' ],
		'fa_sale_unit' => [ 'field' => 'h_ai_fa_sale_unit', 'a' => '', 'b' => '' ],
		'en_sale_unit' => [ 'field' => 'h_ai_en_sale_unit', 'a' => '', 'b' => '' ],
		'fa_packaging' => [ 'field' => 'h_ai_fa_packaging', 'a' => '', 'b' => '' ],
		'en_packaging' => [ 'field' => 'h_ai_en_packaging', 'a' => '', 'b' => '' ],
		'fa_moq'       => [ 'field' => 'h_ai_fa_moq', 'a' => '', 'b' => '' ],
		'en_moq'       => [ 'field' => 'h_ai_en_moq', 'a' => '', 'b' => '' ],
	];
}

/** نام خانه یک کلید پلن: قیمت A «_t1» دارد و بقیه A بی‌پسوندند؛ همه B «_t2». */
function hodima_seo_pkg_aeo_tier_name( string $base, string $tier ): string {
	if ( 'b' === $tier ) {
		return $base . '_t2';
	}
	return 'h_ai_fa_price' === $base ? 'h_ai_fa_price_t1' : $base;
}

/**
 * گزینه‌های مجاز «مبنای قیمت» (همان فهرست $units_fa/$units_en پنل AEO در class-hodima-aeo-admin.php؛ هر دو با هم عوض شوند).
 *
 * @return array{fa: list<string>, en: list<string>}
 */
function hodima_seo_pkg_aeo_units(): array {
	return [
		'fa' => [ 'کیلوگرم', 'کارتن', 'بسته', 'جین', 'عدد', 'کارت' ],
		'en' => [ 'kg', 'Carton', 'Pack', 'Dozen', 'Piece', 'Card' ],
	];
}

/**
 * نام خانه فرم AEO ← کلید همان خانه در بسته (برای پیش‌نمایش «فعلی ← جدید» خانه‌به‌خانه).
 *
 * @return array<string, string>
 */
function hodima_seo_pkg_aeo_keys(): array {

	$keys = array_flip( hodima_seo_pkg_aeo_text_fields() );
	$keys['h_ai_biz_model'] = 'biz_model';

	foreach ( [ 'a' => 'tier_a', 'b' => 'tier_b' ] as $tier => $label ) {
		foreach ( hodima_seo_pkg_aeo_tier_fields() as $sub => $spec ) {
			$keys[ hodima_seo_pkg_aeo_tier_name( $spec['field'], $tier ) ] = $label . '.' . $sub;
		}
	}

	return $keys + [
		'h_ai_en_specs'   => 'en_specs',
		'h_ai_faqs'       => 'fa_faqs',
		'h_ai_prompts'    => 'fa_prompts',
		'h_ai_en_faqs'    => 'en_faqs',
		'h_ai_en_prompts' => 'en_prompts',
	];
}
