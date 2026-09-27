<?php
// php render.php <path> <full|hooks>
[ , $path, $mode ] = $argv + [ 1 => '/', 2 => 'full' ];
$harness_wp = rtrim( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp', '/' );

$u = parse_url( $path );
$_SERVER['HTTP_HOST'] = 'hodima.test'; $_SERVER['SERVER_NAME'] = 'hodima.test'; $_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PORT'] = 443;
$_SERVER['REQUEST_URI'] = $path; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTP_USER_AGENT'] = 'harness';
parse_str( $u['query'] ?? '', $_GET ); $_REQUEST = $_GET;
if ( $mode === 'full' ) {
	define( 'WP_USE_THEMES', true );
	ob_start();
	require "$harness_wp/wp-blog-header.php";
	$out = ob_get_clean();
} else {
	require "$harness_wp/wp-load.php";
	wp();
	ob_start();
	do_action( 'template_redirect' );
	do_action( 'wp_head' );
	do_action( 'wp_footer' );
	$out = ob_get_clean();
}
echo $out;
