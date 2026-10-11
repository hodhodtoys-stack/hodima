<?php
/**
 * اسکیمای مسیر راهنما (BreadcrumbList «#breadcrumb») با سلسله‌مراتب کامل دسته‌ها و برگه‌ها
 * Path: plugins/hodima-seo/schema/breadcrumb-schema.php
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', 'hodima_seo_render_breadcrumb_schema', 99 );

/*
 * خاموش کردن BreadcrumbList خود ووکامرس (دو مسیر راهنمای متفاوت روی هر محصول
 * و دسته = نوسان BreadcrumbList در سرچ کنسول). هر دو املای فیلتر ثبت می‌شود:
 * قالب قدیمی «breadcrumlist» (بدون b دوم) نوشته بود و نام واقعی «breadcrumblist» است.
 */
add_action( 'wp', 'hodima_disable_woo_breadcrumb_schema' );

function hodima_disable_woo_breadcrumb_schema(): void {

	if ( 'on' !== get_option( 'hodima_breadcrumb_schema_status', 'on' ) ) {
		return; // ماژول ما خاموش است → بردکرامب ووکامرس باید بماند
	}

	add_filter( 'woocommerce_structured_data_breadcrumblist', '__return_empty_array' );
	add_filter( 'woocommerce_structured_data_breadcrumlist', '__return_empty_array' );
}

/*
 * دسته‌هایی که دسته اصلی مسیر راهنما نمی‌شوند (مثل «جدیدترین محصولات» که ربط
 * موضوعی ندارد). مسیر قابل‌مشاهده ووکامرس و اسکیما از یک فهرست می‌خوانند:
 * «اسکیما ← بردکرامب». دسته اصلی دستی (_hodima_primary_*) دست نمی‌خورد.
 */
const HODIMA_BREADCRUMB_EXCLUDE_OPTION = 'hodima_breadcrumb_exclude_slugs';

/**
 * نامک دسته‌های مستثنا (پیش‌فرض: latest-products، همان رفتار قبلی قالب).
 *
 * @return list<string>
 */
function hodima_breadcrumb_excluded_slugs(): array {
	$stored = get_option( HODIMA_BREADCRUMB_EXCLUDE_OPTION, false );
	return array_values( array_filter( array_map( 'sanitize_title', is_array( $stored ) ? $stored : [ 'latest-products' ] ) ) );
}

/**
 * دسته‌های غیرمستثنا؛ اگر همه مستثنا باشند، همان فهرست اصلی.
 *
 * @param array<mixed> $terms
 * @return array<mixed>
 */
function hodima_breadcrumb_filter_terms( array $terms ): array {
	$excluded = hodima_breadcrumb_excluded_slugs();
	$kept     = array_values( array_filter( $terms, static fn( $t ): bool => $t instanceof WP_Term && ! in_array( $t->slug, $excluded, true ) ) );
	return $kept ?: $terms;
}

add_filter( 'woocommerce_breadcrumb_main_term', 'hodima_seo_breadcrumb_main_term', 10, 2 );

/** مسیر راهنمای قابل‌مشاهده ووکامرس. */
function hodima_seo_breadcrumb_main_term( mixed $main_term, mixed $terms ): mixed {

	// قالب قبل از 2.3.0 همین فیلتر را خودش دارد
	if ( function_exists( 'hodima_theme_has_legacy_logic' ) && hodima_theme_has_legacy_logic() ) {
		return $main_term;
	}

	if ( $main_term instanceof WP_Term && in_array( $main_term->slug, hodima_breadcrumb_excluded_slugs(), true ) ) {
		return hodima_breadcrumb_filter_terms( (array) $terms )[0] ?? $main_term;
	}

	return $main_term;
}

/**
 * دسته اصلی یک نوشته/محصول در یک تکسونومی، یا null.
 * اول انتخاب دستی «_hodima_primary_{taxonomy}» (انتخاب‌های قبلی Rank Math را
 * schema-cleaner.php پیش از حذف به همین کلید منتقل کرده) یا Yoast؛ وگرنه
 * عمیق‌ترین دسته غیرمستثنا.
 */
function hodima_breadcrumb_primary_term( int $post_id, string $taxonomy ): ?WP_Term {

	$primary_id = get_post_meta( $post_id, "_hodima_primary_{$taxonomy}", true ) ?: get_post_meta( $post_id, "_yoast_wpseo_primary_{$taxonomy}", true );
	if ( $primary_id ) {
		$term = get_term( $primary_id, $taxonomy );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}

	$terms = get_the_terms( $post_id, $taxonomy );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}

	$terms     = hodima_breadcrumb_filter_terms( $terms );
	$main_term = $terms[0];
	$max_depth = -1;
	foreach ( $terms as $t ) {
		$depth = count( get_ancestors( $t->term_id, $taxonomy ) );
		if ( $depth > $max_depth ) {
			$max_depth = $depth;
			$main_term = $t;
		}
	}

	return $main_term;
}

/**
 * آیتم‌های مسیر راهنمای صفحه جاری (یا آرایه خالی). یک بار حساب می‌شود.
 *
 * از تابع چاپ جداست تا homepage-schema.php — که *قبل از* این فایل اجرا
 * می‌شود — بداند بردکرامبی هست و از نود صفحه به آن ارجاع دهد (WebPage.breadcrumb).
 * آدرس‌ها با esc_url_raw (نه esc_url که «&» را «&#038;» می‌کند و آدرس JSON را خراب).
 *
 * @return list<array<string, mixed>>
 */
function hodima_breadcrumb_items(): array {

	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$memo = [];

	if ( 'on' !== get_option( 'hodima_breadcrumb_schema_status', 'on' ) ) {
		return $memo;
	}
	if ( is_home() || is_front_page() || is_404() || is_search() ) {
		return $memo;
	}

	$items = [];

	$push = static function ( string $name, mixed $url ) use ( &$items ): void {
		$items[] = [
			'@type'    => 'ListItem',
			'position' => count( $items ) + 1,
			'name'     => $name,
			'item'     => $url,
		];
	};

	// یک ترم با آدرسش (ترم بی‌آدرس رد می‌شود)
	$push_term = static function ( WP_Term $term, mixed $link = null ) use ( $push ): void {
		$link ??= get_term_link( $term );
		if ( ! is_wp_error( $link ) ) {
			$push( wp_strip_all_tags( $term->name ), esc_url_raw( $link ) );
		}
	};

	$push_term_ancestors = static function ( int $term_id, string $taxonomy ) use ( $push_term ): void {
		foreach ( array_reverse( get_ancestors( $term_id, $taxonomy ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$push_term( $ancestor );
			}
		}
	};

	// خود صفحه جاری (نوشته/برگه/محصول)
	$push_current = static fn() => $push( wp_strip_all_tags( get_the_title() ), esc_url_raw( get_permalink() ) );

	$push( sanitize_text_field( get_option( 'hodima_breadcrumb_home_label', 'خانه' ) ), trailingslashit( home_url() ) );

	if ( function_exists( 'is_product' ) && is_product() ) {
		if ( function_exists( 'wc_get_product_terms' ) ) {
			$main_term = hodima_breadcrumb_primary_term( (int) get_the_ID(), 'product_cat' );
			if ( $main_term ) {
				$push_term_ancestors( $main_term->term_id, 'product_cat' );
				$push_term( $main_term );
			}
		}
		$push_current();
	} elseif ( is_tax( 'product_cat' ) || is_category() ) {
		$term = get_queried_object();
		$push_term_ancestors( $term->term_id, $term->taxonomy );
		$push_term( $term );
	} elseif ( is_single() && 'post' === get_post_type() ) {
		$main_cat = hodima_breadcrumb_primary_term( (int) get_the_ID(), 'category' );
		if ( $main_cat ) {
			$push_term_ancestors( $main_cat->term_id, 'category' );
			$push_term( $main_cat, get_category_link( $main_cat->term_id ) );
		}
		$push_current();
	} elseif ( ( function_exists( 'is_shop' ) && is_shop() ) || is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		$obj       = get_post_type_object( is_array( $post_type ) ? reset( $post_type ) : $post_type );
		if ( $obj ) {
			$push( wp_strip_all_tags( $obj->labels->name ), esc_url_raw( get_post_type_archive_link( $obj->name ) ) );
		}
	} elseif ( is_page() ) {
		$page = get_post();
		if ( $page->post_parent ) {
			foreach ( array_reverse( get_post_ancestors( $page->ID ) ) as $ancestor_id ) {
				$push( wp_strip_all_tags( get_the_title( $ancestor_id ) ), esc_url_raw( get_permalink( $ancestor_id ) ) );
			}
		}
		$push_current();
	} elseif ( is_tag() ) {
		$term = get_queried_object();
		if ( $term ) {
			$push_term( $term );
		}
	} elseif ( is_tax() ) {
		// باگ قبلی: تکسونومی‌های سفارشی شاخه‌ای نداشتند و اصلا بردکرامب نمی‌گرفتند
		$term = get_queried_object();
		if ( $term && isset( $term->taxonomy ) ) {
			$push_term_ancestors( $term->term_id, $term->taxonomy );
			$push_term( $term );
		}
	} elseif ( is_singular() ) {
		foreach ( [ 'product_cat', 'category' ] as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$main_term = hodima_breadcrumb_primary_term( (int) get_the_ID(), $tax );
				if ( $main_term ) {
					$push_term_ancestors( $main_term->term_id, $tax );
					$push_term( $main_term );
					break;
				}
			}
		}
		$push_current();
	}

	/*
	 * نقطه توسعه: ماژول‌هایی که مسیر خاص خودشان را دارند (مثل صفحه ویدیو با
	 * پله «ویدئوها») آیتم‌ها را اینجا تکمیل می‌کنند، به جای BreadcrumbList موازی.
	 */
	$items = (array) apply_filters( 'hodima_breadcrumb_items', $items );

	if ( count( $items ) < 2 ) {
		return $memo;
	}

	$memo = $items;

	return $memo;
}

/** شناسه بردکرامب — از همان آدرسی که نود «#webpage» می‌سازد. */
function hodima_breadcrumb_id(): string {

	$base = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';

	if ( '' === $base ) {
		$items = hodima_breadcrumb_items();
		$last  = end( $items );
		$base  = ! empty( $last['item'] ) ? trailingslashit( (string) $last['item'] ) : trailingslashit( home_url() );
	}

	return $base . '#breadcrumb';
}

/** WebPage.breadcrumb → #breadcrumb */
add_filter( 'hodima_schema_webpage_node', static function ( array $node ): array {
	if ( ! empty( hodima_breadcrumb_items() ) ) {
		$node['breadcrumb'] = [ '@id' => hodima_breadcrumb_id() ];
	}
	return $node;
}, 10 );

function hodima_seo_render_breadcrumb_schema(): void {

	$items = hodima_breadcrumb_items();

	if ( empty( $items ) ) {
		return;
	}

	hodima_schema_add( [
		'@type'           => 'BreadcrumbList',
		'@id'             => hodima_breadcrumb_id(),
		'itemListElement' => $items,
	], 'hodima-seo: breadcrumb-schema' );
}
