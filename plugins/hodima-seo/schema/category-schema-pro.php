<?php
/**
 * اسکیمای دسته محصول (product_cat) و دسته مقاله (category): CollectionPage + OfferCatalog
 * Path: plugins/hodima-seo/schema/category-schema-pro.php
 *
 * تنظیمات: پنل «اسکیما ← دسته‌بندی» (admin/views/view-category.php، گزینه‌های hodima_cat_*).
 *
 * نود صفحه دوم ساخته نمی‌شود: قبلا هر دسته دو موجودیت داشت (WebPage «#webpage»
 * و CollectionPage «#collection»). حالا فیلدهای این فایل به همان «#webpage»
 * (homepage-schema.php) اضافه می‌شوند؛ اگر گراف اصلی در پنل خاموش باشد،
 * همین فایل نود را کامل چاپ می‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_filter( 'hodima_schema_webpage_node', 'hodima_category_enrich_webpage_node' );
add_action( 'wp_head', 'hodima_seo_render_category_schema_fallback', 10 );

/**
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function hodima_category_enrich_webpage_node( array $node ): array {

	$fields = hodima_category_schema_fields();

	return null === $fields ? $node : array_merge( $node, $fields );
}

/** فالبک: فقط وقتی گراف اصلی نود صفحه را چاپ نکرده باشد. */
function hodima_seo_render_category_schema_fallback(): void {

	if ( function_exists( 'hodima_schema_webpage_emitted' ) && hodima_schema_webpage_emitted() ) {
		return;
	}

	$fields = hodima_category_schema_fields();

	if ( null === $fields ) {
		return;
	}

	$site_url = trailingslashit( home_url() );

	$node = array_merge( [
		'@id'        => $fields['url'] . '#webpage',
		'isPartOf'   => [ '@id' => $site_url . '#website' ],
		'about'      => [ '@id' => $site_url . '#organization' ],
		'inLanguage' => 'fa-IR',
	], $fields );

	hodima_schema_add( $node, 'hodima-seo: category-schema-pro (fallback)' );
}

/**
 * فیلدهای اسکیمای دسته جاری، یا null اگر این صفحه دسته نیست.
 * یک بار در هر درخواست حساب می‌شود (فیلتر و فالبک هر دو صدا می‌زنند).
 *
 * @return array<string, mixed>|null
 */
function hodima_category_schema_fields(): ?array {

	static $memo = false;

	if ( false !== $memo ) {
		return $memo;
	}

	$memo = null;

	if ( 'on' !== get_option( 'hodima_cat_status', 'on' ) ) {
		return $memo;
	}

	// دسته مقاله فقط با گزینه «hodima_cat_include_post_category» پنل
	$is_product_cat = is_tax( 'product_cat' );
	$is_post_cat    = 'on' === get_option( 'hodima_cat_include_post_category', 'on' ) && is_category();

	if ( ! $is_product_cat && ! $is_post_cat ) {
		return $memo;
	}

	$term = get_queried_object();
	if ( ! $term || is_wp_error( $term ) || empty( $term->term_id ) ) {
		return $memo;
	}

	// آدرس از موتور canonical مشترک تا @id با تگ canonical یکی باشد (با صفحه‌بندی)
	$term_link = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
	if ( '' === $term_link ) {
		$term_link = get_term_link( $term );
	}
	if ( is_wp_error( $term_link ) ) {
		return $memo;
	}

	// تصویر پیش‌فرض: لوگوی سازمان (تنها منبع لوگو، تنظیمات صفحه اصلی)
	$default_img     = hodima_seo_schema_logo_url();
	$name_tpl        = (string) get_option( 'hodima_cat_name_template', 'پخش عمده [category]' );
	$desc_tpl        = (string) get_option( 'hodima_cat_desc_template', 'مرجع تخصصی واردات و پخش عمده [category] با رقابتی‌ترین قیمت بازار در [site_name].' );
	$catalog_tpl     = (string) get_option( 'hodima_cat_catalog_template', 'کاتالوگ محصولات [category]' );
	$audience_txt    = get_option( 'hodima_cat_audience', 'خریداران عمده، همکاران و B2B' );
	$video_title_tpl = (string) get_option( 'hodima_cat_video_title', 'معرفی دسته‌بندی: [category]' );
	$video_desc_tpl  = (string) get_option( 'hodima_cat_video_desc', 'ویدیوی معرفی و راهنمای خرید عمده [category]' );

	$replace_vars = [ '[category]' => wp_strip_all_tags( $term->name ), '[site_name]' => get_bloginfo( 'name' ) ];

	// «متن معرفی» دسته (سیستم رسانه؛ inc/page-intro.php) توضیح واقعی صفحه است؛ وگرنه الگوی تنظیمات
	$intro      = function_exists( 'hodima_seo_page_intro_text' ) ? hodima_seo_page_intro_text( (int) $term->term_id, 'term', 40 ) : '';
	$final_desc = '' !== $intro ? $intro : wp_strip_all_tags( strtr( $desc_tpl, $replace_vars ) );

	// تصویر دسته (فقط متای ترم، بدون کوئری اضافه)
	$thumb_id  = get_term_meta( $term->term_id, 'thumbnail_id', true ) ?: get_term_meta( $term->term_id, 'product_cat_thumbnail_id', true );
	$image_url = $thumb_id ? wp_get_attachment_url( (int) $thumb_id ) : $default_img;
	if ( empty( $image_url ) ) {
		$image_url = $default_img;
	}

	$collection_page = [
		'@type'       => 'CollectionPage',
		'url'         => (string) $term_link,
		'name'        => strtr( $name_tpl, $replace_vars ),
		'description' => $final_desc,
		'audience'    => [
			'@type'        => 'Audience',
			'audienceType' => $audience_txt,
		],
		'mainEntity'  => [
			'@type'         => 'OfferCatalog',
			'@id'           => (string) $term_link . '#catalog',
			'name'          => strtr( $catalog_tpl, $replace_vars ),
			// شمارگر خود ترم (بدون کوئری اضافه)
			'numberOfItems' => isset( $term->count ) ? (int) $term->count : 0,
		],
	];

	if ( ! empty( $image_url ) ) {
		$collection_page['image'] = $image_url;
	}

	// محصولات همین صفحه داخل همان OfferCatalog (زیرنوع ItemList)؛ نه یک ItemList جداگانه
	$list_items = function_exists( 'hodima_get_archive_itemlist_elements' ) ? hodima_get_archive_itemlist_elements() : [];

	if ( ! empty( $list_items ) ) {
		$collection_page['mainEntity']['itemListElement'] = $list_items;
	}

	/*
	 * ویدیوی دسته: اگر سیستم رسانه VideoObject «#video» این دسته را می‌سازد
	 * (سازنده واحد)، صفحه با subjectOf به همان ارجاع می‌دهد؛ ویدیوی دوم ساخته
	 * نمی‌شود. کد پایین فقط برای داده خیلی قدیمی «_hod_video_*» ترم است.
	 * (باگ قبلی: دنبال «_hook_video_url» می‌گشت ولی متای ترم «hook_video_url» است.)
	 */
	$media_video = function_exists( 'hodima_media_video_node' ) ? hodima_media_video_node( (int) $term->term_id, 'term' ) : null;

	if ( null !== $media_video ) {
		// صفحه ۲ به بعد بخش ویدیو را نشان نمی‌دهد و #video چاپ نمی‌شود؛ ارجاع آویزان نساز
		if ( ! function_exists( 'hodima_media_schema_page_shows_media' ) || hodima_media_schema_page_shows_media( (int) $term->term_id, 'term' ) ) {
			$collection_page['subjectOf'] = [ '@id' => $media_video['@id'] ];
		}
		$memo = $collection_page;
		return $memo;
	}

	$video_url = get_term_meta( $term->term_id, '_hod_video_url', true );

	if ( ! empty( $video_url ) ) {
		$video_thumb = get_term_meta( $term->term_id, '_hod_video_thumbnail', true );
		$video_date  = get_term_meta( $term->term_id, '_hod_video_date', true );

		// تاریخ نامعتبر قبلا ۱۹۷۰ و تاریخ خالی «یک ماه پیش از امروز» (هر روز متفاوت) می‌شد؛
		// حالا تاریخ اولین مشاهده یک بار ثبت و ثابت می‌ماند (ارقام فارسی هم خوانده می‌شوند).
		$video_ts = $video_date ? strtotime( (string) $video_date ) : false;
		if ( ! $video_ts ) {
			$video_ts = strtotime( hodima_seo_schema_term_video_date( $video_date, (int) $term->term_id ) );
		}

		$video_prop = hodima_seo_schema_video_url( (string) $video_url );

		$collection_page['subjectOf'] = [
			'@type'        => 'VideoObject',
			'name'         => strtr( $video_title_tpl, $replace_vars ),
			'description'  => strtr( $video_desc_tpl, $replace_vars ),
			'thumbnailUrl' => [ esc_url_raw( (string) ( $video_thumb ?: $image_url ) ) ],
			'uploadDate'   => hodima_seo_schema_local_iso( $video_ts ?: strtotime( '-1 month' ) ),
			$video_prop[0] => $video_prop[1],
		];
	}

	$memo = $collection_page;

	return $memo;
}
