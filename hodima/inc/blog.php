<?php
/**
 * وبلاگ: نام وبلاگ و مقالات مرتبط (نمایش)
 * Path: hodima/inc/blog.php
 *
 * بازسازی قالب، مرحله ۴. تنظیمات: «نمایش ← تنظیمات قالب هدیما ← وبلاگ».
 * قبلا نام «وبلاگ»، عنوان و تعداد «مقالات مرتبط» در single-post.php و
 * archive-blog.php ثابت بودند و صفحه اصلی وبلاگ اصلا H1 نداشت.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** نام وبلاگ: تنظیم قالب ← عنوان برگه «نوشته‌ها» ← «وبلاگ». */
function hodima_blog_name(): string {

	$name = function_exists( 'hodima_setting' ) ? trim( (string) hodima_setting( 'blog_title' ) ) : '';

	if ( '' === $name ) {
		$page_id = (int) get_option( 'page_for_posts' );
		$name    = $page_id > 0 ? trim( (string) get_the_title( $page_id ) ) : '';
	}

	return '' !== $name ? $name : 'وبلاگ';
}

/** آدرس وبلاگ: برگه «نوشته‌ها»، وگرنه /blog/. */
function hodima_blog_url(): string {
	$page_id = (int) get_option( 'page_for_posts' );
	return $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/blog/' );
}

/**
 * شناسه مقالات مرتبط با یک مقاله.
 *
 * cluster: اول هم‌خوشه‌ها (خوشه موضوعی افزونه Hodima SEO، فقط نوشته‌های
 *          منتشرشده)، بعد اگر کم بود هم‌دسته‌ها.
 * category: آخرین مقاله‌های همان دسته‌ها (رفتار قبلی).
 *
 * @return list<int>
 */
function hodima_related_post_ids( int $post_id, int $limit, string $source = 'category' ): array {

	if ( $limit <= 0 ) {
		return [];
	}

	$ids = [];

	if ( 'cluster' === $source && class_exists( 'Hodima_TC_Helper' ) && method_exists( 'Hodima_TC_Helper', 'sibling_nodes' ) ) {
		foreach ( Hodima_TC_Helper::sibling_nodes( $post_id, 'post', $limit * 3 ) as $node ) {
			if ( 'post' === ( $node['kind'] ?? '' ) && 'post' === ( $node['type'] ?? '' ) && empty( $node['noindex'] ) && (int) $node['id'] !== $post_id ) {
				$ids[] = (int) $node['id'];
			}
			if ( count( $ids ) >= $limit ) {
				break;
			}
		}
	}

	$categories = wp_get_post_categories( $post_id );

	if ( count( $ids ) < $limit && $categories ) {
		$ids = array_merge( $ids, get_posts( [
			'category__in'        => $categories,
			'post__not_in'        => array_merge( [ $post_id ], $ids ),
			'posts_per_page'      => $limit - count( $ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'fields'              => 'ids',
		] ) );
	}

	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}
