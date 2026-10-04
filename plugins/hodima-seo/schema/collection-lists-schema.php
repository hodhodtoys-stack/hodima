<?php
/**
 * فهرست محصولات فروشگاه/برچسب محصول و فهرست مقالات وبلاگ (ItemList)
 * Path: hodima-seo/schema/collection-lists-schema.php
 *
 * از قالب منتقل شد — بازسازی قالب، مرحله ۲:
 *   - فروشگاه و برچسب محصول: inc/enqueue.php (hodima_shop_*)
 *   - صفحه وبلاگ و آرشیو نوشته‌ها: archive-blog.php (داخل خود قالب صفحه)
 * دسته محصول را category-schema-pro.php پوشش می‌دهد.
 *
 * نام توابع با نسخه قالب فرق دارد: قالب قبل از 2.3.0 آن‌ها را بدون گارد
 * تعریف می‌کند و هم‌نامی خطای «Cannot redeclare» می‌داد؛ در آن حالت این فایل
 * کاری نمی‌کند تا اسکیما دوبار ساخته نشود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

function hodima_seo_lists_legacy_theme(): bool {
	return function_exists( 'hodima_theme_has_legacy_logic' ) && hodima_theme_has_legacy_logic();
}

/* =========================================================================
 * ۱. فروشگاه و برچسب محصول
 * ------------------------------------------------------------------------
 * نود صفحه (#webpage، CollectionPage) را homepage-schema.php می‌سازد؛ اینجا
 * فقط فهرست محصولات به‌عنوان mainEntity آن اضافه می‌شود. اگر گراف اصلی در
 * پنل خاموش باشد، همان نود کامل در فوتر چاپ می‌شود.
 * ========================================================================= */
add_filter( 'hodima_schema_webpage_node', 'hodima_seo_shop_enrich_webpage_node', 10, 2 );
add_action( 'wp_footer', 'hodima_seo_shop_fallback_schema', 20 );

/** فهرست محصولات صفحه فروشگاه/برچسب، یا null. */
function hodima_seo_shop_itemlist_node(): ?array {

	static $memo = false;

	if ( false !== $memo ) {
		return $memo;
	}

	$memo = null;

	if ( hodima_seo_lists_legacy_theme() || ! function_exists( 'is_shop' ) || ! function_exists( 'hodima_get_archive_itemlist_elements' ) ) {
		return $memo;
	}

	// دسته‌بندی‌ها را category-schema-pro.php پوشش می‌دهد
	if ( is_product_category() || is_category() || ( ! is_shop() && ! is_product_tag() ) ) {
		return $memo;
	}

	$items = hodima_get_archive_itemlist_elements();

	if ( ! $items ) {
		return $memo;
	}

	return $memo = [
		'@type'           => 'ItemList',
		'numberOfItems'   => count( $items ),
		'itemListElement' => $items,
	];
}

function hodima_seo_shop_enrich_webpage_node( array $node, string $page_url = '' ): array {

	$list = hodima_seo_shop_itemlist_node();

	if ( null !== $list ) {
		$node['mainEntity'] = $list;
	}

	return $node;
}

/** فالبک: فقط وقتی گراف اصلی نود صفحه را چاپ نکرده باشد. */
function hodima_seo_shop_fallback_schema(): void {

	if ( function_exists( 'hodima_schema_webpage_emitted' ) && hodima_schema_webpage_emitted() ) {
		return;
	}

	$list     = hodima_seo_shop_itemlist_node();
	$page_url = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';

	if ( null === $list || '' === $page_url ) {
		return;
	}

	$site_url = trailingslashit( home_url() );

	hodima_schema_add( [
		'@type'      => 'CollectionPage',
		'@id'        => $page_url . '#webpage',
		'url'        => $page_url,
		'name'       => function_exists( 'hodima_schema_page_name' ) ? hodima_schema_page_name() : '',
		'inLanguage' => 'fa-IR',
		'isPartOf'   => [ '@id' => $site_url . '#website' ],
		'about'      => [ '@id' => $site_url . '#organization' ],
		'mainEntity' => $list,
	], 'hodima-seo: collection-lists-schema.php (shop fallback)' );
}

/* =========================================================================
 * ۲. صفحه وبلاگ و آرشیو نوشته‌ها (دسته، برچسب، نویسنده، تاریخ)
 * ------------------------------------------------------------------------
 * همان مقاله‌هایی که در صفحه فهرست شده‌اند (کوئری اصلی)، با تصویر شاخص.
 * پایه شناسه از موتور canonical مشترک — همان آدرس نود «#webpage».
 * ========================================================================= */
add_action( 'wp_footer', 'hodima_seo_blog_archive_itemlist', 20 );

/** آیا صفحه جاری فهرست نوشته‌های وبلاگ است؟ */
function hodima_seo_is_blog_listing(): bool {

	global $wp_query;

	if ( is_home() ) {
		return true;
	}

	if ( ! is_archive() || ! ( $wp_query instanceof WP_Query ) || empty( $wp_query->posts ) ) {
		return false;
	}

	$first = $wp_query->posts[0];

	return 'post' === get_post_type( $first instanceof WP_Post ? $first : (int) $first );
}

function hodima_seo_blog_archive_itemlist(): void {

	global $wp_query, $wp;

	if ( hodima_seo_lists_legacy_theme() || ! hodima_seo_is_blog_listing() || empty( $wp_query->posts ) ) {
		return;
	}

	$items    = [];
	$position = 1;

	foreach ( $wp_query->posts as $listed ) {
		$post_id = $listed instanceof WP_Post ? $listed->ID : (int) $listed;
		$item    = [
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => get_the_title( $post_id ),
			'url'      => get_permalink( $post_id ),
		];
		$thumb = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $thumb ) {
			$item['image'] = $thumb;
		}
		$items[] = $item;
	}

	$current_url = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
	if ( '' === $current_url ) {
		$current_url = trailingslashit( home_url( (string) ( $wp->request ?? '' ) ) );
	}

	$archive_name = 'وبلاگ';
	if ( is_archive() ) {
		$archive_name = is_category() || is_tag() || is_tax() ? single_term_title( '', false ) : wp_strip_all_tags( get_the_archive_title() );
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );

	hodima_schema_add( [
		'@type'            => 'ItemList',
		'@id'              => $current_url . '#itemlist',
		'mainEntityOfPage' => [ '@id' => $current_url . '#webpage' ],
		'name'             => 'آرشیو ' . $archive_name . ( $paged > 1 ? ' - صفحه ' . $paged : '' ),
		'description'      => 'لیست مقالات و نوشته‌های مرتبط',
		'itemListElement'  => $items,
	], 'hodima-seo: collection-lists-schema.php (blog)' );
}
