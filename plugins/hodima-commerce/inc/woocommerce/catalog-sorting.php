<?php
/**
 * Hodima Commerce — گزینه‌های مرتب‌سازی کاتالوگ
 * Path: plugins/hodima-commerce/inc/woocommerce/catalog-sorting.php
 *
 * از قالب (inc/enqueue.php) منتقل شد — بازسازی قالب، مرحله ۲. اینکه محصولات
 * با «ارزان‌ترین» یا «محبوب‌ترین» چطور مرتب شوند منطق فروشگاه است؛ قالب
 * فقط نوار مرتب‌سازی را نمایش می‌دهد.
 *
 * چهار گزینه با برچسب کوتاه فارسی: جدیدترین، محبوب‌ترین، ارزان‌ترین، گران‌ترین.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// قالب هدیما قبل از 2.3.0 همین کارها را خودش انجام می‌دهد
if ( function_exists( 'hodima_theme_has_legacy_logic' ) && hodima_theme_has_legacy_logic() ) {
	return;
}

add_filter( 'woocommerce_default_catalog_orderby_options', 'hodima_commerce_sorting_options' );
add_filter( 'woocommerce_catalog_orderby', 'hodima_commerce_sorting_options' );

/** معرفی گزینه‌ها به ووکامرس (تا مقدار ?orderby= معتبر شناخته شود) با برچسب کوتاه. */
function hodima_commerce_sorting_options( $options ): array {

	$options = (array) $options;

	$options['date']       = 'جدیدترین';
	$options['popularity'] = 'محبوب‌ترین';
	$options['price']      = 'ارزان‌ترین';
	$options['price-desc'] = 'گران‌ترین';

	return $options;
}

add_filter( 'woocommerce_get_catalog_ordering_args', 'hodima_commerce_catalog_ordering_args', 999 );

/** منطق هر گزینه روی کوئری محصولات. */
function hodima_commerce_catalog_ordering_args( $args ) {

	if ( ! isset( $_GET['orderby'] ) ) {
		return $args;
	}

	$sort = match ( sanitize_text_field( wp_unslash( (string) $_GET['orderby'] ) ) ) {
		'date'       => [ 'orderby' => 'date ID', 'order' => 'DESC' ],
		'popularity' => [ 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => 'total_sales' ],
		'price'      => [ 'orderby' => 'meta_value_num', 'order' => 'ASC', 'meta_key' => '_price' ],
		'price-desc' => [ 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_price' ],
		default      => [],
	};

	return array_merge( (array) $args, $sort );
}
