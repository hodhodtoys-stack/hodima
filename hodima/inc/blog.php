<?php
/**
 * وبلاگ: نام وبلاگ و مقالات مرتبط (نمایش)
 * Path: hodima/inc/blog.php
 *
 * بازسازی قالب، مرحله ۴. تنظیمات: «تنظیمات قالب هدیما ← وبلاگ». از 3.0.0
 * اطلاعات زیر عنوان مقاله (تاریخ، به‌روزرسانی، زمان مطالعه، نویسنده) و خلاصه کارت‌ها.
 * قبلا نام «وبلاگ»، عنوان و تعداد «مقالات مرتبط» در single-post.php و
 * archive-blog.php ثابت بودند و صفحه اصلی وبلاگ اصلا H1 نداشت.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * آرشیوهایی که با بدنه وبلاگ (template-parts/blog/archive.php) نمایش داده می‌شوند:
 * صفحه وبلاگ و آرشیو نوشته‌ها (دسته، برچسب، نویسنده، تاریخ).
 */
function hodima_is_blog_archive(): bool {
	// دسته/برچسب بدون مقاله get_post_type() خالی می‌دهد؛ جداگانه بررسی می‌شوند تا قالب وبلاگ را بگیرند
	return is_home() || is_category() || is_tag() || ( is_archive() && 'post' === get_post_type() );
}

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

	// method_exists: نسخه قدیمی‌تر افزونه SEO این متد را ندارد (PHPStan افزونه فعلی را می‌بیند)
	// @phpstan-ignore function.alreadyNarrowedType
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

/** زمان مطالعه (دقیقه، دست‌کم ۱): حدود ۲۰۰ کلمه در دقیقه برای متن فارسی. */
function hodima_reading_minutes( int $post_id ): int {

	$text  = wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) );
	$words = preg_match_all( '/[\p{L}\p{N}]+/u', $text );

	return max( 1, (int) ceil( (int) $words / 200 ) );
}

/** «۵ دقیقه مطالعه» */
function hodima_reading_label( int $post_id ): string {
	return hodima_fa_digits( hodima_reading_minutes( $post_id ) ) . ' دقیقه مطالعه';
}

/**
 * اطلاعات زیر عنوان مقاله (تاریخ انتشار، به‌روزرسانی، زمان مطالعه، نویسنده) طبق
 * «تنظیمات قالب ← وبلاگ ← اطلاعات زیر عنوان مقاله»؛ همه خاموش ← رشته خالی.
 * تا 2.9.9 صفحه مقاله هیچ تاریخ یا نویسنده‌ای نشان نمی‌داد.
 */
function hodima_post_meta_html( int $post_id ): string {

	$items     = [];
	$published = get_post_datetime( $post_id );
	$modified  = get_post_datetime( $post_id, 'modified' );

	if ( hodima_setting( 'blog_meta_date' ) && $published ) {
		$items[] = sprintf( '<span class="single-post-meta__item">انتشار: <time datetime="%s">%s</time></span>', esc_attr( $published->format( 'c' ) ), esc_html( (string) get_the_date( '', $post_id ) ) );
	}

	// «به‌روزرسانی» فقط اگر دست‌کم یک روز بعد از انتشار ویرایش شده (اصلاح غلط تایپی همان روز نه)
	if ( hodima_setting( 'blog_meta_updated' ) && $published && $modified && $modified->getTimestamp() - $published->getTimestamp() > DAY_IN_SECONDS ) {
		$items[] = sprintf( '<span class="single-post-meta__item">به‌روزرسانی: <time datetime="%s">%s</time></span>', esc_attr( $modified->format( 'c' ) ), esc_html( (string) get_the_modified_date( '', $post_id ) ) );
	}

	if ( hodima_setting( 'blog_meta_reading' ) ) {
		$items[] = '<span class="single-post-meta__item">' . esc_html( hodima_reading_label( $post_id ) ) . '</span>';
	}

	if ( hodima_setting( 'blog_meta_category' ) ) {
		$term = hodima_post_main_category( $post_id );
		$link = $term ? get_term_link( $term ) : null;
		if ( $term && is_string( $link ) ) {
			$items[] = '<span class="single-post-meta__item">دسته: <a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a></span>';
		}
	}

	if ( hodima_setting( 'blog_meta_author' ) ) {
		$author = (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
		if ( '' !== $author ) {
			$items[] = '<span class="single-post-meta__item">نویسنده: ' . esc_html( $author ) . '</span>';
		}
	}

	return $items ? '<p class="single-post-meta">' . implode( '', $items ) . '</p>' : '';
}

/**
 * کارت مقاله — یک کارت برای آرشیو وبلاگ، نتایج جستجو و «مقالات مرتبط».
 * قبلا آرشیو `blog-page-card` و مقاله `related-post-card` جدا (HTML و CSS تکراری، مرتبط‌ها
 * بدون تاریخ و تصویر جایگزین) بودند. CSS: assets/css/blog-card.css.
 *
 * @param array{heading?:string, lazy?:bool, empty_label?:string, meta_text?:string} $args
 *        heading: تگ عنوان (h2 در فهرست، h3 زیر عنوان بخش)؛ lazy: بارگذاری تنبل تصویر (زیر صفحه)؛
 *        empty_label: متن کادر بی‌تصویر؛ meta_text: به‌جای تاریخ/زمان مطالعه (مثل نوع محتوا در جستجو).
 */
function hodima_blog_card_html( int $post_id, array $args = [] ): string {

	$heading = in_array( $args['heading'] ?? 'h2', [ 'h2', 'h3', 'h4' ], true ) ? (string) ( $args['heading'] ?? 'h2' ) : 'h2';
	$title   = get_the_title( $post_id );

	if ( has_post_thumbnail( $post_id ) ) {
		// alt خالی: عنوان همان کارت کنارش خوانده می‌شود (تکرارش برای صفحه‌خوان زائد بود)
		$thumb_args = [
			'class' => 'blog-page-thumbnail',
			'alt'   => '',
			'sizes' => '(max-width: 480px) 100vw, (max-width: 768px) 50vw, (max-width: 1140px) 33vw, 25vw',
		];
		if ( ! empty( $args['lazy'] ) ) {
			$thumb_args['loading'] = 'lazy';
		}
		$thumb = (string) get_the_post_thumbnail( $post_id, 'medium', $thumb_args );
	} else {
		$thumb = '<div class="blog-page-thumbnail blog-page-thumbnail--empty"><span>' . esc_html( (string) ( $args['empty_label'] ?? 'بدون تصویر' ) ) . '</span></div>';
	}

	$excerpt = '';
	if ( hodima_setting( 'blog_card_excerpt' ) && empty( $args['meta_text'] ) ) {
		$excerpt = '<p class="blog-page-excerpt">' . esc_html( wp_trim_words( get_the_excerpt( $post_id ), 22 ) ) . '</p>';
	}

	$meta = '';
	if ( ! empty( $args['meta_text'] ) ) {
		$meta = '<span class="blog-date">' . esc_html( (string) $args['meta_text'] ) . '</span>';
	} else {
		if ( hodima_setting( 'blog_meta_date' ) ) {
			$meta .= '<span class="blog-date">' . esc_html( (string) get_the_date( 'j F Y', $post_id ) ) . '</span>';
		}
		if ( hodima_setting( 'blog_meta_reading' ) ) {
			$meta .= '<span class="blog-reading">' . esc_html( hodima_reading_label( $post_id ) ) . '</span>';
		}
	}

	return '<a href="' . esc_url( (string) get_permalink( $post_id ) ) . '" class="blog-page-card">'
		. $thumb
		. '<div class="blog-page-content">'
		. '<' . $heading . ' class="blog-page-title">' . esc_html( $title ) . '</' . $heading . '>'
		. $excerpt
		. ( '' !== $meta ? '<div class="blog-page-meta">' . $meta . '</div>' : '' )
		. '</div></a>';
}

/**
 * دسته اصلی مقاله: همان دسته مسیر راهنما (افزونه SEO؛ انتخاب دستی یا عمیق‌ترین دسته)،
 * وگرنه اولین دسته غیر از «بدون دسته‌بندی». بدون دسته معنادار: null.
 */
function hodima_post_main_category( int $post_id ): ?WP_Term {

	$default = (int) get_option( 'default_category' );
	$term    = function_exists( 'hodima_breadcrumb_primary_term' ) ? hodima_breadcrumb_primary_term( $post_id, 'category' ) : null;

	if ( $term instanceof WP_Term && $term->term_id !== $default ) {
		return $term;
	}

	foreach ( (array) get_the_category( $post_id ) as $category ) {
		if ( $category instanceof WP_Term && $category->term_id !== $default ) {
			return $category;
		}
	}

	return null;
}

/**
 * دسته‌های اصلی (سطح اول) وبلاگ برای نوار دسته‌ها؛ فقط دسته‌های دارای مقاله و بدون
 * «بدون دسته‌بندی». فیلتر hodima_blog_category_chips_args برای تغییر کوئری (مثلا زیردسته‌ها).
 *
 * @return list<WP_Term>
 */
function hodima_blog_category_chips(): array {

	$terms = get_categories( (array) apply_filters( 'hodima_blog_category_chips_args', [ // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- پیشوند hodima_ دارد
		'taxonomy'   => 'category',
		'parent'     => 0,
		'hide_empty' => true,
		'exclude'    => [ (int) get_option( 'default_category' ) ],
		'orderby'    => 'name',
	] ) );

	return array_values( array_filter( $terms, static fn( mixed $term ): bool => $term instanceof WP_Term ) );
}

/**
 * نوار دسته‌های وبلاگ (صفحه وبلاگ، دسته و برچسب). «همه» + هر دسته؛ دسته جاری (یا پدر
 * دسته جاری) aria-current می‌گیرد. کلید «نوار دسته‌های وبلاگ» یا کمتر از دو دسته ← رشته خالی.
 */
function hodima_blog_categories_bar_html(): string {

	if ( ! hodima_setting( 'blog_cats_bar' ) ) {
		return '';
	}

	$terms = hodima_blog_category_chips();
	if ( count( $terms ) < 2 ) {
		return '';
	}

	$current = is_category() ? (int) get_queried_object_id() : 0;
	$items   = [ [ 'name' => 'همه', 'url' => hodima_blog_url(), 'active' => is_home() ] ];

	foreach ( $terms as $term ) {
		$link    = get_term_link( $term );
		$items[] = [
			'name'   => $term->name,
			'url'    => is_string( $link ) ? $link : '',
			'active' => $current > 0 && ( $current === $term->term_id || cat_is_ancestor_of( $term, $current ) ),
		];
	}

	$html = '';
	foreach ( $items as $item ) {
		if ( '' === $item['url'] ) {
			continue;
		}
		$html .= '<li><a class="blog-cats__link" href="' . esc_url( $item['url'] ) . '"' . ( $item['active'] ? ' aria-current="page"' : '' ) . '>' . esc_html( $item['name'] ) . '</a></li>';
	}

	return '<nav class="blog-cats" aria-label="دسته‌های وبلاگ"><ul class="blog-cats__list">' . $html . '</ul></nav>';
}
