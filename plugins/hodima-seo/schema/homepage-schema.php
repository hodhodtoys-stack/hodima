<?php
/**
 * نودهای هویتی گراف هر صفحه: سازمان (#organization)، وب‌سایت (#website) و صفحه جاری (#webpage)
 * Path: plugins/hodima-seo/schema/homepage-schema.php
 *
 * این فایل *تنها سازنده* نود «#webpage» است (به‌جز برگه‌های درباره‌ما/تماس:
 * corporate-schema.php). ماژول‌های دیگر به جای ساختن نود صفحه دوم برای همان
 * آدرس، از فیلتر hodima_schema_webpage_node ویژگی اضافه می‌کنند:
 *   category-schema-pro.php     → نام/توضیح/تصویر دسته، OfferCatalog، ویدیو
 *   collection-lists-schema.php → فهرست محصولات فروشگاه/برچسب
 *   product-schema-pro.php      → mainEntity → #product
 *   breadcrumb-schema.php       → breadcrumb → #breadcrumb
 *   core/topiccluster           → isPartOf والدها، hasPart فرزندان
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// اولویت ۰: نودهای هویتی اولین نودهای گراف واحدند (در ادغام، مقدار سازنده
// زودتر می‌ماند) و پرچم hodima_schema_webpage_emitted() پیش از همه ست می‌شود.
add_action( 'wp_head', 'hodima_seo_render_master_schema', 0 );

function hodima_seo_render_master_schema(): void {

	if ( is_admin() || '1' !== get_option( 'hodima_schema_homepage_enable', '1' ) ) {
		return;
	}

	$site_url = trailingslashit( home_url() );

	/*
	 * آدرس صفحه از موتور canonical مشترک (همان تگ canonical سئوباکس).
	 * باگ‌های قبلی: trailingslashit دستی (@id با canonical یکی نمی‌شد)،
	 * نادیده گرفتن صفحه‌بندی، و @id خانه برای ۴۰۴/جستجو (که حالا اصلا نود
	 * صفحه نمی‌گیرند).
	 */
	$has_identity = function_exists( 'hodima_page_has_schema_identity' )
		? hodima_page_has_schema_identity()
		: ( ! is_404() && ! is_search() );

	$canonical   = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
	$current_url = '' !== $canonical ? $canonical : $site_url;

	// سازمان از سازنده مشترک (schema-helpers.php)؛ برگه‌های درباره‌ما/تماس همان را چاپ می‌کنند
	$org_name            = hodima_seo_schema_org_name();
	$organization_schema = hodima_seo_schema_organization_node();

	$website_schema = [
		'@type'           => 'WebSite',
		'@id'             => $site_url . '#website',
		'url'             => $site_url,
		'name'            => $org_name,
		'publisher'       => [ '@id' => $site_url . '#organization' ],
		'inLanguage'      => 'fa-IR',
		// جعبه جستجوی سایت‌لینک را گوگل از نوامبر ۲۰۲۴ نشان نمی‌دهد؛ برای موتورها و دستیارهای دیگر می‌ماند
		'potentialAction' => [
			'@type'       => 'SearchAction',
			'target'      => [
				'@type'       => 'EntryPoint',
				'urlTemplate' => $site_url . '?s={search_term_string}',
			],
			'query-input' => 'required name=search_term_string',
		],
	];

	// نام‌های دیگر سایت (گوگل «نام سایت» نتایج را از WebSite.name و alternateName انتخاب می‌کند)
	$site_alt_names = array_values( array_unique( array_filter(
		array_merge( [ (string) get_bloginfo( 'name' ) ], (array) ( $organization_schema['alternateName'] ?? [] ) ),
		static fn( $alt ): bool => is_string( $alt ) && '' !== trim( $alt ) && $alt !== $org_name
	) ) );
	if ( [] !== $site_alt_names ) {
		$website_schema['alternateName'] = $site_alt_names;
	}

	$page_type         = hodima_seo_schema_page_type();
	$is_corporate_page = in_array( $page_type, [ 'AboutPage', 'ContactPage' ], true );
	$page_desc         = hodima_seo_schema_page_description();

	$webpage_schema = [
		'@type'      => $page_type,
		'@id'        => $current_url . '#webpage',
		'url'        => $current_url,
		'name'       => hodima_schema_page_name(),
		'isPartOf'   => [ '@id' => $site_url . '#website' ],
		'about'      => [ '@id' => $site_url . '#organization' ],
		'inLanguage' => 'fa-IR',
	];

	if ( ! empty( $page_desc ) ) {
		$webpage_schema['description'] = $page_desc;
	}

	// برگه درباره‌ما/تماس: سازمان و نود صفحه را corporate-schema.php می‌سازد (دو نود هم‌شناسه نه)
	if ( $has_identity && ! $is_corporate_page ) {
		$webpage_schema = (array) apply_filters( 'hodima_schema_webpage_node', $webpage_schema, $current_url );
		hodima_schema_webpage_emitted( true );
	}

	if ( $is_corporate_page ) {
		$graph_nodes = [ $website_schema ];
	} elseif ( $has_identity ) {
		$graph_nodes = [ $organization_schema, $website_schema, $webpage_schema ];
	} else {
		$graph_nodes = [ $organization_schema, $website_schema ];
	}

	hodima_schema_add( [ '@graph' => $graph_nodes ], 'hodima-seo: homepage-schema' );
}

/**
 * نوع نود صفحه جاری.
 *   - فهرست‌ها (خانه، وبلاگ، آرشیو، دسته، فروشگاه، برگه ویدیوها): CollectionPage —
 *     قبلا دسته‌ها WebPage بودند و category-schema-pro.php یک CollectionPage دوم می‌ساخت.
 *   - نویسنده: ProfilePage با mainEntity → Person (نتیجه غنی «پروفایل»).
 */
function hodima_seo_schema_page_type(): string {

	return match ( true ) {
		is_author()                                                                       => 'ProfilePage',
		is_front_page() || is_home() || is_archive() || is_page_template( 'template-page-videos.php' ) => 'CollectionPage',
		is_page( get_option( 'hodima_corp_about_slug', 'about-us' ) )                     => 'AboutPage',
		is_page( get_option( 'hodima_corp_contact_slug', 'contact-us' ) )                 => 'ContactPage',
		is_singular( 'product' )                                                          => 'ItemPage',
		default                                                                           => 'WebPage',
	};
}

/**
 * توضیح صفحه جاری (بدون HTML و شورت‌کد).
 * باگ قبلی: اول کوتاه می‌شد بعد شورت‌کد حذف؛ شورت‌کد بریده («[video src=…») در توضیح می‌ماند.
 * بدون چکیده/توضیح: «متن معرفی» صفحه (سیستم رسانه؛ inc/page-intro.php) و بعد ابتدای متن.
 */
function hodima_seo_schema_page_description(): string {

	$page_desc = '';

	if ( is_front_page() || is_home() ) {
		$page_desc = get_bloginfo( 'description' );
	} elseif ( is_singular() ) {
		$intro     = function_exists( 'hodima_seo_page_intro_text' ) ? hodima_seo_page_intro_text( (int) get_queried_object_id(), 'post', 40 ) : '';
		$page_desc = has_excerpt() ? get_the_excerpt() : ( '' !== $intro ? $intro : wp_trim_words( strip_shortcodes( (string) get_post_field( 'post_content', get_queried_object_id() ) ), 20 ) );
	} elseif ( is_archive() || is_tax() ) {
		$page_desc = get_the_archive_description();
		if ( '' === trim( wp_strip_all_tags( (string) $page_desc ) ) && ( is_category() || is_tax() ) && function_exists( 'hodima_seo_page_intro_text' ) ) {
			$page_desc = hodima_seo_page_intro_text( (int) get_queried_object_id(), 'term', 40 );
		}
	}

	return wp_strip_all_tags( strip_shortcodes( (string) $page_desc ) );
}

/*
 * غنی‌سازی پایه نود صفحه (اولویت ۵، پیش از بقیه؛ در برگه‌های درباره‌ما/تماس هم):
 *   - نوشته/برگه/محصول: datePublished، dateModified و primaryImageOfPage (#primaryimage)
 *   - صفحه نویسنده: mainEntity → Person (همان که مقاله‌ها به آن ارجاع می‌دهند)
 * تاریخ‌ها با get_post_time بدون ترجمه (wp_date روی این سایت شمسی می‌شود).
 */
add_filter( 'hodima_schema_webpage_node', 'hodima_schema_webpage_base_fields', 5, 2 );

/**
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function hodima_schema_webpage_base_fields( array $node, string $page_url ): array {

	if ( is_singular() ) {

		$post_id   = (int) get_queried_object_id();
		$published = get_post_time( 'c', false, $post_id, false );
		$modified  = get_post_modified_time( 'c', false, $post_id, false );

		if ( $published ) {
			$node['datePublished'] = $published;
		}
		if ( $modified ) {
			$node['dateModified'] = $modified;
		}

		$image = hodima_seo_schema_featured_image( $post_id );
		if ( null !== $image ) {
			$node['primaryImageOfPage'] = [
				'@type'  => 'ImageObject',
				'@id'    => $page_url . '#primaryimage',
				'url'    => $image[0],
				'width'  => $image[1],
				'height' => $image[2],
			];
		}
	} elseif ( is_author() ) {
		$node['mainEntity'] = hodima_seo_schema_person_node( (int) get_queried_object_id() );
	}

	return $node;
}

/**
 * نام موجودیت صفحه جاری.
 * باگ قبلی: get_the_title() بدون آرگومان در آرشیو نام *اولین پست لوپ* را می‌داد.
 */
function hodima_schema_page_name(): string {

	if ( is_front_page() ) {
		return (string) get_bloginfo( 'name' );
	}

	if ( is_home() ) {
		$blog_id = (int) get_option( 'page_for_posts' );
		return $blog_id ? (string) get_the_title( $blog_id ) : (string) get_bloginfo( 'name' );
	}

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		return $shop_id > 0 ? (string) get_the_title( $shop_id ) : (string) get_bloginfo( 'name' );
	}

	if ( is_category() || is_tag() || is_tax() ) {
		return (string) single_term_title( '', false );
	}

	if ( is_post_type_archive() ) {
		return (string) post_type_archive_title( '', false );
	}

	if ( is_author() ) {
		return (string) get_the_author_meta( 'display_name', (int) get_queried_object_id() );
	}

	if ( is_singular() ) {
		return (string) get_the_title( (int) get_queried_object_id() );
	}

	return wp_strip_all_tags( (string) get_the_archive_title() );
}

/**
 * آیا نود صفحه چاپ شده است؟ (برای فالبک ماژول‌های دیگر)
 * با $mark = true سازنده‌های نود صفحه (همین فایل و corporate-schema.php) پرچم را ست می‌کنند.
 */
function hodima_schema_webpage_emitted( bool $mark = false ): bool {

	static $emitted = false;

	if ( $mark ) {
		$emitted = true;
	}

	return $emitted;
}
