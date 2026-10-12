<?php
/**
 * اسلایدر محصولات (موتور رندر) و داده‌های کش‌شده دسته‌ها و مقاله‌ها
 * Path: hodima/home/slider.php
 *
 * 3.1.5: logic.php (۶۷۰ خط، چند کار) به چند فایل با یک کار تقسیم شد؛ نام توابع و هوک‌ها
 * و خروجی صفحه همان است. فهرست فایل‌ها: home/logic.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// ========================================================================
// ۱. تابع مرکزی اسلایدر محصولات (موتور رندر)
// ========================================================================
/**
 * آرگومان‌های wc_get_products برای یک بخش محصول (جدا از رندر تا جداگانه آزمون شود).
 * برای «جدیدترین موجود» (پیش‌فرض) دقیقا همان آرگومان‌های قبلی است؛ پس کلید کش هم همان می‌ماند.
 *
 * @param array<string, mixed> $args limit، category، source (newest|bestselling|sale|featured)، only_instock
 * @return array<string, mixed>
 */
function hodima_home_product_query_args( array $args ): array {

	$query = [
		'status'  => 'publish',
		'limit'   => $args['limit'] ?? 10,
		'orderby' => 'date',
		'order'   => 'DESC',
	];

	if ( ! array_key_exists( 'only_instock', $args ) || $args['only_instock'] ) {
		$query['stock_status'] = 'instock';
	}

	if ( ! empty( $args['category'] ) ) {
		$query['category'] = (array) $args['category'];
	}

	switch ( (string) ( $args['source'] ?? 'newest' ) ) {
		case 'bestselling':
			$query['orderby'] = 'popularity'; // wc_get_products: meta total_sales، نزولی
			break;
		case 'featured':
			$query['featured'] = true;
			break;
		case 'sale':
			// فقط محصولات تخفیف‌دار فعلی؛ شناسه ۰ = «هیچ» (include خالی یعنی همه)
			$query['include'] = function_exists( 'wc_get_product_ids_on_sale' ) ? ( array_map( 'intval', wc_get_product_ids_on_sale() ) ?: [ 0 ] ) : [ 0 ];
			break;
	}

	return $query;
}

if ( ! function_exists( 'hodima_home_product_slider' ) ) {
	/**
	 * @param array<string, mixed> $args title، link، category، limit، bg_color، id.
	 */
	function hodima_home_product_slider( array $args = [] ): string {

		if ( ! function_exists( 'wc_get_products' ) ) {
			return '';
		}

		$defaults = [
			'title'        => 'محصولات',
			'subtitle'     => '',
			'link'         => '',
			'category'     => '',
			'limit'        => 10,
			'source'       => 'newest',
			'only_instock' => true,
			'bg_color'     => 'transparent',
			// شناسه ثابت در هر بار ساخت صفحه (قبلا uniqid: HTML صفحه اصلی هر بار فرق می‌کرد)
			'id'       => wp_unique_id( 'arian-sec-' ),
		];
		$args = wp_parse_args( $args, $defaults );

		// نامک/مسیر داخلی (شورت‌کدهای قدیمی) یا آدرس کامل (چیدمان صفحه اصلی: لینک واقعی دسته)
		$clean_slug = trim( (string) $args['link'], '/' );
		$final_link = match ( true ) {
			'' === $clean_slug                             => esc_url( home_url( '/' ) ),
			(bool) preg_match( '#^https?://#i', $clean_slug ) => esc_url( (string) $args['link'] ),
			default                                        => esc_url( home_url( '/' . $clean_slug . '/' ) ),
		};

		$query_args = hodima_home_product_query_args( $args );

		// نسل کش در کلید: پاک کردن = بالا بردن نسل (hodima_home_flush_product_sliders)
		$transient_key = 'arian_pslider_' . md5( (string) wp_json_encode( $query_args ) . '|' . hodima_home_slider_generation() );
		$products_data = get_transient( $transient_key );

		if ( false === $products_data ) {
			$product_objects = wc_get_products( $query_args );
			$products_data   = [];

			if ( ! empty( $product_objects ) ) {
				foreach ( $product_objects as $product ) {
					$products_data[] = [
						'name'         => $product->get_name(),
						'link'         => $product->get_permalink(),
						'price_html'   => $product->get_price_html(),
						'thumbnail_id' => $product->get_image_id(),
					];
				}
			}

			set_transient( $transient_key, $products_data, 10 * MINUTE_IN_SECONDS );
		}

		if ( empty( $products_data ) ) {
			return '';
		}

		$cards = '';

		foreach ( $products_data as $index => $product ) {
			$cards .= hodima_home_card_html( [
				'link'         => $product['link'],
				'title'        => $product['name'],
				'thumbnail_id' => (int) $product['thumbnail_id'],
				'price_html'   => $product['price_html'],
				'first'        => 0 === $index,
				'class'        => 'product-card',
				'size'         => 'woocommerce_thumbnail',
				'placeholder'  => wc_placeholder_img_src( 'woocommerce_thumbnail' ),
			] );
		}

		return hodima_home_cards_section( [
			'id'            => (string) $args['id'],
			'title'         => (string) $args['title'],
			'subtitle'      => (string) $args['subtitle'],
			'more_url'      => '' !== $clean_slug ? $final_link : '',
			'more_text'     => 'مشاهده همه',
			'bg_color'      => (string) $args['bg_color'],
			'scroller_class' => 'product-wrapper',
		], $cards );
	}
}

// ========================================================================
// داده بخش‌های «دسته‌بندی کالاها» و «آخرین مقالات» (با کش)
// ------------------------------------------------------------------------
// مشترک بین چیدمان «تنظیمات قالب ← صفحه اصلی» و شورت‌کدهای قدیمی
// [section03] و [section09] (home/parts/*.php). دسته‌های مستثنا و تعداد
// مقاله‌ها هنگام نمایش اعمال می‌شوند تا یک کش برای همه تنظیمات کافی باشد؛
// کش با تغییر دسته/نوشته پاک می‌شود (home/cache.php).
// ========================================================================

/**
 * همه دسته‌های محصول دارای کالا: نامک، نام، لینک، شناسه تصویر.
 *
 * @return list<array{slug: string, name: string, link: string, thumbnail_id: int, count: int}>
 */
function hodima_home_categories_data(): array {

	$data = get_transient( 'hodima_home_categories_v6' );

	if ( is_array( $data ) ) {
		return $data;
	}

	$data  = [];
	$terms = taxonomy_exists( 'product_cat' ) ? get_terms( [
		'taxonomy'               => 'product_cat',
		'hide_empty'             => true,
		'update_term_meta_cache' => true,
	] ) : [];

	if ( is_array( $terms ) ) {
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$data[] = [
				'slug'         => $term->slug,
				'name'         => $term->name,
				'link'         => $link,
				'thumbnail_id' => (int) get_term_meta( $term->term_id, 'thumbnail_id', true ),
				'count'        => (int) $term->count, // مرتب‌سازی «بیشترین محصول اول» (v6)
			];
		}
	}

	set_transient( 'hodima_home_categories_v6', $data, WEEK_IN_SECONDS );

	return $data;
}

/**
 * ۲۰ مقاله آخر: عنوان، لینک، شناسه تصویر شاخص (۱۵ دقیقه کش).
 *
 * @return list<array{title: string, link: string, thumbnail_id: int}>
 */
function hodima_home_blog_posts_data(): array {

	$data = get_transient( 'hodima_home_blog_posts_v3' );

	if ( is_array( $data ) ) {
		return $data;
	}

	$data = [];

	foreach ( get_posts( [
		'post_type'      => 'post',
		'posts_per_page' => 20,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	] ) as $post_item ) {
		$data[] = [
			'title'        => $post_item->post_title,
			'link'         => get_permalink( $post_item->ID ),
			'thumbnail_id' => (int) get_post_thumbnail_id( $post_item->ID ),
		];
	}

	set_transient( 'hodima_home_blog_posts_v3', $data, 15 * MINUTE_IN_SECONDS );

	return $data;
}
