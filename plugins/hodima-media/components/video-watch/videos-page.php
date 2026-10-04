<?php
/**
 * صفحه فهرست ویدئوها (برگه با قالب template-page-videos.php): کوئری و اسکیما
 * Path: hodima-media/components/video-watch/videos-page.php
 *
 * از قالب (template-page-videos.php) منتقل شد — بازسازی قالب، مرحله ۲.
 * کوئری فهرست (۲۴ ویدئو در هر صفحه، /video/page/N/) و ItemList آن داده
 * افزونه رسانه است؛ قالب فقط کارت‌ها را از همین کوئری نمایش می‌دهد
 * (hodima_media_videos_page_query) تا HTML و اسکیما یک منبع داشته باشند و
 * کوئری دو بار اجرا نشود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** شماره صفحه فعلی فهرست (/video/page/N/ با قانون بازنویسی vid-w-schema.php). */
function hodima_media_videos_page_paged(): int {
	return max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
}

/** کوئری ویدئوهای صفحه فعلی؛ یک بار در هر درخواست. */
function hodima_media_videos_page_query(): WP_Query {

	static $query = null;

	return $query ??= new WP_Query( [
		'post_type'           => 'video',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, (int) apply_filters( 'hodima_videos_per_page', 24 ) ),
		'paged'               => hodima_media_videos_page_paged(),
		'ignore_sticky_posts' => true,
	] );
}

add_action( 'wp_footer', 'hodima_media_videos_page_schema', 20 );

/**
 * ItemList آدرس ویدئوها (الگوی «صفحه خلاصه» گوگل)، متصل به نود صفحه.
 *
 * فقط آدرس صفحه هر ویدئو: نود کامل VideoObject روی صفحه خود ویدئو
 * (vid-w-schema.php) است؛ نسخه بسیار قدیمی‌تر برای هر کارت یک VideoObject
 * ناقص با همان شناسه می‌ساخت (دو تعریف متناقض از یک موجودیت).
 */
function hodima_media_videos_page_schema(): void {

	// قالب قبل از 2.3.0 همین اسکیما را داخل قالب صفحه می‌سازد
	if ( ! is_page_template( 'template-page-videos.php' ) || ( function_exists( 'hodima_theme_has_legacy_logic' ) && hodima_theme_has_legacy_logic() ) ) {
		return;
	}

	if ( ! function_exists( 'hodima_schema_add' ) ) {
		return;
	}

	$query = hodima_media_videos_page_query();

	if ( empty( $query->posts ) ) {
		return;
	}

	$page_id  = (int) get_queried_object_id();
	$page_url = (string) get_permalink( $page_id );
	$base     = function_exists( 'hodima_get_canonical_url' ) && '' !== hodima_get_canonical_url() ? hodima_get_canonical_url() : $page_url;
	$position = ( hodima_media_videos_page_paged() - 1 ) * max( 1, (int) $query->get( 'posts_per_page' ) ) + 1;
	$items    = [];

	foreach ( $query->posts as $video ) {
		$items[] = [
			'@type'    => 'ListItem',
			'position' => $position++,
			'url'      => (string) get_permalink( $video ),
		];
	}

	// نود صفحه با همان شناسه «#webpage» گراف اصلی (homepage-schema.php) ادغام می‌شود
	$master_ran = function_exists( 'hodima_schema_webpage_emitted' ) && hodima_schema_webpage_emitted();

	hodima_schema_add( [
		'@graph' => [
			$master_ran
				? [ '@id' => $base . '#webpage', 'mainEntity' => [ '@id' => $base . '#itemlist' ] ]
				: [
					'@type'      => 'CollectionPage',
					'@id'        => $base . '#webpage',
					'url'        => $base,
					'name'       => (string) get_the_title( $page_id ),
					'isPartOf'   => [ '@id' => trailingslashit( home_url() ) . '#website' ],
					'mainEntity' => [ '@id' => $base . '#itemlist' ],
					'inLanguage' => 'fa-IR',
				],
			[
				'@type'            => 'ItemList',
				'@id'              => $base . '#itemlist',
				'mainEntityOfPage' => [ '@id' => $base . '#webpage' ],
				'numberOfItems'    => (int) $query->found_posts,
				'itemListElement'  => $items,
			],
		],
	], 'hodima-media: videos-page.php' );
}
