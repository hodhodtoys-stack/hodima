<?php
/**
 * اسکیمای تکمیلی صفحه اصلی: فهرست دسته‌های اصلی، تصاویر و ویدیوی hero
 * Path: hodima-seo/schema/front-page-extra-schema.php
 *
 * از قالب (home/logic.php، توابع arian_*_homepage_schema_*) منتقل شد — بازسازی
 * قالب، مرحله ۲. اسکیما کار افزونه سئوست؛ با عوض شدن قالب نباید گم شود.
 *
 * تغییرها نسبت به نسخه قالب:
 *   - فهرست دسته‌ها (ItemList #homepage-categories) قبلا ۱۷ نامک ثابت در کد
 *     بود؛ حالا «اسکیما ← صفحه اصلی ← دسته‌های فهرست صفحه اصلی». تا ذخیره
 *     نشده، همان ۱۷ نامک قبلی (خروجی بدون تغییر).
 *   - نام سازنده تصاویر قبلا «بازرگانی هدهد» ثابت بود؛ حالا نام سازمان پنل
 *     (همان نود #organization).
 *   - تصویر پیش‌فرض ویدیو قبلا site icon یا مسیر حدسی /uploads/logo.png بود؛
 *     حالا لوگوی سازمان پنل.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** نامک‌های فهرست قبلی (پیش‌فرض تا وقتی مدیر انتخاب نکرده). */
const HODIMA_SEO_FRONT_DEFAULT_CATEGORIES = [
	'beauty', 'accessory', 'hairband', 'hair-ties', 'hair-clips', 'plumeria-hair-clips',
	'birds-nest-claw-clips', 'metal-hair-clips', 'hair-pins', 'alligator-hair-clips',
	'snap-hair-clips', 'mini-clips', 'latest-products', 'charms', 'packaging-supplies',
	'hodhod-plus', 'zip-lock-pouches',
];

const HODIMA_SEO_FRONT_CATEGORIES_OPTION = 'hodima_schema_home_category_slugs';
const HODIMA_SEO_FRONT_CACHE_KEY         = 'hodima_seo_front_extra_graph_v1';

/** نامک دسته‌های محصول برای ItemList صفحه اصلی، به ترتیب. */
function hodima_seo_front_category_slugs(): array {

	$stored = get_option( HODIMA_SEO_FRONT_CATEGORIES_OPTION, false );
	$slugs  = is_array( $stored ) ? $stored : HODIMA_SEO_FRONT_DEFAULT_CATEGORIES;

	return array_values( array_filter( array_map( 'sanitize_title', (array) apply_filters( 'hodima_seo_front_category_slugs', $slugs ) ) ) );
}

/** ساخت گراف (بدون کش). */
function hodima_seo_front_build_graph(): array {

	$site_url = trailingslashit( home_url( '/' ) );
	$graph    = [];

	// ۱. فهرست دسته‌های اصلی
	$elements = [];
	$position = 1;

	foreach ( hodima_seo_front_category_slugs() as $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$elements[] = [
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => wp_strip_all_tags( $term->name ),
			'url'      => $link,
		];
	}

	if ( $elements ) {
		$graph[] = [
			'@type'            => 'ItemList',
			'@id'              => $site_url . '#homepage-categories',
			'name'             => 'دسته بندی های اصلی محصولات',
			'itemListElement'  => $elements,
			'mainEntityOfPage' => [ '@id' => $site_url . '#webpage' ],
		];
	}

	// بردکرامب صفحه اصلی عمدا ساخته نمی‌شود: مسیر یک‌پله‌ای «خانه» معنی ندارد.

	// ۲. تصاویر و ویدیوی برگه صفحه اصلی
	$front_id = (int) get_option( 'page_on_front' );

	if ( $front_id <= 0 ) {
		return $graph;
	}

	$raw_content   = (string) get_post_field( 'post_content', $front_id );
	$clean_content = str_replace( '\/', '/', do_shortcode( $raw_content ) . ' ' . $raw_content );

	$company   = (string) ( get_option( 'hodima_corp_name' ) ?: hodima_seo_schema_org_name() );
	$site_name = (string) get_bloginfo( 'name' );

	// الف) تصاویر (برای Image Metadata گوگل)، حداکثر ۵
	$images = [];
	if ( has_post_thumbnail( $front_id ) ) {
		$images[] = (string) get_the_post_thumbnail_url( $front_id, 'full' );
	}
	if ( preg_match_all( '/https?:\/\/[^\s"\'<>]+\.(?:jpg|jpeg|png|gif|webp)[^\s"\'<>]*/i', $clean_content, $m ) ) {
		$images = array_merge( $images, $m[0] );
	}
	if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $clean_content, $m ) ) {
		$images = array_merge( $images, $m[1] );
	}
	$images = array_values( array_unique( array_filter( $images ) ) );

	$credit  = (string) get_option( 'hodima_schema_image_credit', 'عکس متعلق به ' . $company . ' است.' );
	$license = (string) get_option( 'hodima_schema_image_license', $site_url . 'terms/' );

	foreach ( array_slice( $images, 0, 5 ) as $index => $img_url ) {
		$graph[] = [
			'@type'              => 'ImageObject',
			'@id'                => $site_url . '#image-' . ( $index + 1 ),
			'url'                => esc_url_raw( $img_url ),
			'contentUrl'         => esc_url_raw( $img_url ),
			'caption'            => $site_name . ' - تصویر ' . ( $index + 1 ),
			'creator'            => [ '@type' => 'Organization', 'name' => $company ],
			'creditText'         => $credit,
			'copyrightNotice'    => '© ' . gmdate( 'Y' ) . ' ' . $company . '.',
			'license'            => esc_url_raw( $license ),
			'acquireLicensePage' => $site_url,
		];
	}

	// ب) ویدیوی hero (متاهای سیستم رسانه روی برگه صفحه اصلی)
	$v_url = (string) get_post_meta( $front_id, '_hook_video_url', true );

	if ( '' === $v_url ) {
		return $graph;
	}

	$v_thumb = (string) ( get_post_meta( $front_id, '_hook_video_cover', true )
		?: get_option( 'hodima_page_video_default_img', $images[0] ?? hodima_seo_schema_logo_url() ) );
	$v_title = (string) ( get_post_meta( $front_id, '_hook_video_title', true ) ?: 'معرفی وب‌سایت ' . $site_name );
	$v_dur   = (string) get_post_meta( $front_id, '_hook_video_duration', true );
	$v_url   = esc_url_raw( $v_url );

	// تاریخ خام GMT (نه wp_date که از مبدل شمسی رد می‌شود)
	$raw_date = (string) get_post_field( 'post_date_gmt', $front_id );
	if ( '' === $raw_date || str_contains( $raw_date, '0000' ) ) {
		$raw_date = (string) get_post_field( 'post_date', $front_id );
	}
	$timestamp = strtotime( $raw_date ) ?: time();

	$video = [
		'@type'            => 'VideoObject',
		'@id'              => $site_url . '#hero-video',
		'name'             => $v_title,
		'description'      => 'ویدیوی معرفی و بررسی تخصصی ' . $site_name,
		'thumbnailUrl'     => [ esc_url_raw( $v_thumb ) ],
		'uploadDate'       => gmdate( 'Y-m-d\TH:i:s+00:00', $timestamp ),
		'inLanguage'       => 'fa-IR',
		'isFamilyFriendly' => true,
		'publisher'        => [ '@id' => $site_url . '#organization' ],
	];

	if ( preg_match( '/\.m(?:p4|3u8|kv|ebm)/i', $v_url ) ) {
		$video['contentUrl']     = $v_url;
		$video['encodingFormat'] = str_contains( $v_url, '.m3u8' ) ? 'application/x-mpegURL' : 'video/mp4';
	} else {
		$video['embedUrl'] = $v_url;
		$video['url']      = $site_url;
	}

	// نام جدید سیستم رسانه (Hodima Media 1.2+)، با فالبک نام قدیمی
	$v_iso = function_exists( 'hodima_media_duration_iso' ) ? hodima_media_duration_iso( $v_dur )
		: ( function_exists( 'hook_format_duration_iso' ) ? hook_format_duration_iso( $v_dur ) : '' );
	if ( '' !== $v_dur && '' !== (string) $v_iso ) {
		$video['duration'] = $v_iso;
	}

	$graph[] = $video;

	return $graph;
}

/** گراف با کش ۱۲ ساعته. */
function hodima_seo_front_graph(): array {

	$graph = get_transient( HODIMA_SEO_FRONT_CACHE_KEY );

	if ( ! is_array( $graph ) ) {
		$graph = hodima_seo_front_build_graph();
		set_transient( HODIMA_SEO_FRONT_CACHE_KEY, $graph, 12 * HOUR_IN_SECONDS );
	}

	return $graph;
}

/**
 * آیا سیستم رسانه برای برگه صفحه اصلی VideoObject (#video) می‌سازد؟
 * ویدیوی hero از همان متای _hook_video_url خوانده می‌شود؛ اگر سیستم رسانه
 * نسخه کامل‌تر را می‌سازد، #hero-video تکراری چاپ نمی‌شود.
 * Hodima Media 1.2+: سازنده واحد (hodima_media_video_node)؛ قبل از آن شرط‌های
 * hook_auto_inject_head_schema() و hook_print_schema('video').
 */
function hodima_seo_front_media_owns_video(): bool {

	$front_id = (int) get_option( 'page_on_front' );

	if ( $front_id <= 0 ) {
		return false;
	}

	if ( function_exists( 'hodima_media_video_node' ) && function_exists( 'hodima_media_post_types' ) ) {
		return in_array( 'page', hodima_media_post_types(), true )
			&& ( ! function_exists( 'hodima_post_content_is_visible' ) || hodima_post_content_is_visible( $front_id ) )
			&& null !== hodima_media_video_node( $front_id, 'post' );
	}

	if ( ! function_exists( 'hook_get_media_data' ) || ! function_exists( 'hook_print_schema' ) ) {
		return false;
	}

	if ( ! in_array( 'page', function_exists( 'hook_get_supported_post_types' ) ? hook_get_supported_post_types() : [], true ) ) {
		return false;
	}

	if ( function_exists( 'hodima_post_content_is_visible' ) && ! hodima_post_content_is_visible( $front_id ) ) {
		return false;
	}

	$data = hook_get_media_data( $front_id, 'post' );

	if ( 'yes' !== ( $data['enabled'] ?? '' ) || empty( $data['video_url'] ) ) {
		return false;
	}

	// بدون تصویر، سیستم رسانه ویدیو را چاپ نمی‌کند (thumbnailUrl الزامی است)
	return '' !== (string) ( $data['video_thumb'] ?? '' ) || has_post_thumbnail( $front_id );
}

add_action( 'wp_head', 'hodima_seo_front_print_schema', 30 );

function hodima_seo_front_print_schema(): void {

	// قالب قبل از 2.3.0 همین گراف را خودش می‌سازد
	if ( ! is_front_page() || ( function_exists( 'hodima_theme_has_legacy_logic' ) && hodima_theme_has_legacy_logic() ) ) {
		return;
	}

	$graph = hodima_seo_front_graph();

	// بررسی هنگام چاپ، نه داخل کش: با روشن/خاموش شدن سیستم رسانه بلافاصله درست شود
	if ( hodima_seo_front_media_owns_video() ) {
		$graph = array_filter(
			$graph,
			static fn( array $node ): bool => ! str_ends_with( (string) ( $node['@id'] ?? '' ), '#hero-video' )
		);
	}

	if ( $graph ) {
		hodima_schema_add( [ '@graph' => array_values( $graph ) ], 'hodima-seo: front-page-extra-schema.php' );
	}
}

/* کش با تغییر داده‌های منبع باطل می‌شود (همان رویدادهای نسخه قالب + تغییر فهرست دسته‌ها) */
function hodima_seo_front_clear_cache(): void {
	delete_transient( HODIMA_SEO_FRONT_CACHE_KEY );
}

foreach ( [ 'created_product_cat', 'edited_product_cat', 'delete_product_cat', 'after_switch_theme', 'save_post', 'update_option_' . HODIMA_SEO_FRONT_CATEGORIES_OPTION, 'add_option_' . HODIMA_SEO_FRONT_CATEGORIES_OPTION ] as $hodima_hook ) {
	add_action( $hodima_hook, 'hodima_seo_front_clear_cache' );
}
unset( $hodima_hook );
