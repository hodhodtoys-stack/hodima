<?php
/* Harness helpers: no canonical redirects, deterministic time. */
if ( ! getenv( 'HARNESS' ) ) return;
add_action( 'init', static function () {
	remove_action( 'template_redirect', 'redirect_canonical' );
	if ( getenv( 'HARNESS_FLUSH' ) ) { flush_rewrite_rules( true ); }
}, 999 );
add_filter( 'wp_redirect', static function ( $l ) { fwrite( STDERR, "REDIRECT -> $l\n" ); return false; }, 1 );
if ( getenv( 'HARNESS_USER' ) ) { add_action( 'init', static fn() => wp_set_current_user( (int) getenv( 'HARNESS_USER' ) ), 1 ); }
if ( getenv( 'HARNESS_USER' ) && ! function_exists( 'auth_redirect' ) ) { function auth_redirect() {} }
if ( getenv( 'HARNESS_USER' ) ) { add_filter( 'determine_current_user', static fn() => (int) getenv( 'HARNESS_USER' ), 99 ); }
