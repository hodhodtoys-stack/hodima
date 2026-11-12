<?php
/**
 * ورود بسته — نوع‌ها و کادرها
 * Path: core/package-import/package-import-types.php
 *
 * هر «نوع» بسته (نسخه ۱ فقط محصول) می‌گوید روی ویرایش کدام نوع نوشته کار می‌کند و کدام
 * کادرها را می‌شناسد. نوع تازه (دسته، نوشته، ویدیو) فقط با افزودن یک ردیف به همین فهرست
 * (یا فیلتر hodima_seo_pkg_types) و نرمال‌ساز کادرهای تازه‌اش در package-import-check.php
 * اضافه می‌شود؛ جاوااسکریپت هم برای هر کادر یک «آداپتور» دارد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** قالب پذیرفته‌شده بسته. */
const HODIMA_SEO_PKG_FORMAT = 'hodima-package/1';

/** بیشترین حجم متن بسته (بایت)؛ بسته واقعی یک محصول ≈ ۲۰ کیلوبایت است. */
const HODIMA_SEO_PKG_MAX_BYTES = 1048576;

/**
 * نوع‌های بسته: نوع ← نوع نوشته وردپرس و کادرهای شناخته‌شده (به ترتیب صفحه ویرایش).
 *
 * @return array<string, array{label: string, post_type: string, boxes: list<string>}>
 */
function hodima_seo_pkg_types(): array {

	$types = [
		'product' => [
			'label'     => 'محصول',
			'post_type' => 'product',
			'boxes'     => [ 'body', 'upsells', 'cross_sells', 'table', 'faq', 'related_links', 'topic_cluster', 'aeo', 'seobox' ],
		],
	];

	return (array) apply_filters( 'hodima_seo_pkg_types', $types );
}

/** نوع بسته‌ای که این نوع نوشته را ویرایش می‌کند، یا null. */
function hodima_seo_pkg_type_for( string $post_type ): ?string {
	foreach ( hodima_seo_pkg_types() as $type => $spec ) {
		if ( ( $spec['post_type'] ?? '' ) === $post_type ) {
			return (string) $type;
		}
	}
	return null;
}

/**
 * نام هر کادر در پیشخوان (همان نامی که مدیر بالای کادر می‌بیند).
 *
 * @return array<string, string>
 */
function hodima_seo_pkg_box_labels(): array {
	return [
		'body'          => 'متن اصلی (ویرایشگر)',
		'upsells'       => 'محصولات پیشنهادی (Upsells)',
		'cross_sells'   => 'محصولات مکمل (Cross-sells)',
		'table'         => 'جدول تکمیلی محصول',
		'faq'           => 'پرسش‌های متداول (تنظیمات رسانه)',
		'related_links' => 'لینک‌های مرتبط',
		'topic_cluster' => 'خوشه‌بندی محتوا',
		'aeo'           => 'پنل AEO و GEO',
		'seobox'        => 'سئوباکس (عنوان و توضیح)',
	];
}

/**
 * کادرهایی که عمدا در نسخه ۱ نیستند (کلیدشان در بسته فقط هشدار می‌دهد).
 *
 * @return array<string, string>
 */
function hodima_seo_pkg_out_of_scope(): array {
	return [
		'discover'   => 'گوگل دیسکاور',
		'images'     => 'عکس‌ها',
		'alt'        => 'متن جایگزین عکس‌ها',
		'attributes' => 'ویژگی‌های ووکامرس',
		'aeo_media'  => 'رسانه‌های پنل AEO',
	];
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
 * گزینه‌های مجاز «مبنای قیمت» (همان فهرست بسته پنل AEO).
 *
 * @return array{fa: list<string>, en: list<string>}
 */
function hodima_seo_pkg_aeo_units(): array {
	return [
		'fa' => [ 'کیلوگرم', 'کارتن', 'بسته', 'جین' ],
		'en' => [ 'kg', 'Carton', 'Pack', 'Dozen' ],
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
