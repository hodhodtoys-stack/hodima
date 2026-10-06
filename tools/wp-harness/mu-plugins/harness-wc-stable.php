<?php
/* ابزار wc-*: خروجی ثابت بین دو اجرا (محصولات مرتبط/مکمل ووکامرس پیش‌فرض تصادفی‌اند). */
if ( ! getenv( 'HARNESS' ) ) return;
add_filter( 'woocommerce_product_related_posts_shuffle', '__return_false' );
add_filter( 'woocommerce_upsells_orderby', static fn() => 'id' );
add_filter( 'woocommerce_cross_sells_orderby', static fn() => 'id' );
add_filter( 'woocommerce_output_related_products_args', static fn( $a ) => array_merge( (array) $a, [ 'orderby' => 'id', 'order' => 'asc' ] ) );
