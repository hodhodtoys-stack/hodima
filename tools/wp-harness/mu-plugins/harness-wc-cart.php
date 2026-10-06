<?php
/* ابزار wc-*: سبد خرید با کالا (HARNESS_CART=شناسه:تعداد) برای ساخت صفحه سبد/پرداخت پر. */
if ( ! getenv( 'HARNESS' ) || ! getenv( 'HARNESS_CART' ) ) return;
add_action( 'wp_loaded', static function () {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;
	[ $id, $qty ] = array_map( 'intval', explode( ':', (string) getenv( 'HARNESS_CART' ) ) + [ 1 => 1 ] );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $id, max( 1, $qty ) );
}, 20 );
