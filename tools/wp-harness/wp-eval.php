<?php
// Run PHP against the harness site: HARNESS=1 php wp-eval.php 'update_option( "x", "y" );'
$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
require rtrim( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp', '/' ) . '/wp-load.php';
eval( $argv[1] );
