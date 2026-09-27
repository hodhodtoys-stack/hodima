<?php
// php admin-render.php <page-slug>
$page = $argv[1];
$_SERVER['HTTP_HOST'] = 'hodima.test'; $_SERVER['SERVER_NAME'] = 'hodima.test'; $_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=' . $page; $_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/wp-admin/admin.php'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET['page'] = $_REQUEST['page'] = $page;
$harness_wp = rtrim( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp', '/' );
chdir( "$harness_wp/wp-admin" );
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) fwrite( STDERR, "FATAL: {$e['message']} {$e['file']}:{$e['line']}\n" ); } );
require "$harness_wp/wp-admin/admin.php";
