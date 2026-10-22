<?php
// php admin-render-screen.php <script.php> [query]   مثلا: edit.php post_type=hd_notification  یا  post.php post=12&action=edit
// مثل admin-render.php ولی برای صفحه‌های خود وردپرس (فهرست/ویرایش/افزودن نوشته)، نه فقط admin.php?page=…
$script = basename( $argv[1] );
$query  = $argv[2] ?? '';
parse_str( $query, $params );
$_SERVER['HTTP_HOST'] = 'hodima.test'; $_SERVER['SERVER_NAME'] = 'hodima.test'; $_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/wp-admin/' . $script . ( '' !== $query ? '?' . $query : '' ); $_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/wp-admin/' . $script; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET = $params; $_REQUEST = $params;
$harness_wp = rtrim( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp', '/' );
chdir( "$harness_wp/wp-admin" );
register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) fwrite( STDERR, "FATAL: {$e['message']} {$e['file']}:{$e['line']}\n" ); } );
// متغیرهای سراسری که فایل‌های پیشخوان وردپرس در دامنه سراسری انتظار دارند
global $post, $post_type, $post_type_object, $typenow, $pagenow, $wp_query, $wp_list_table, $title, $hook_suffix, $current_screen, $action, $post_ID;
require "$harness_wp/wp-admin/$script";
