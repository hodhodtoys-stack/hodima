<?php
/**
 * Header Module – Enqueue styles & scripts
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hodima_header_assets(): void {
    hodima_enqueue_asset( 'hodima-header', 'assets/css/header.css' );
    hodima_enqueue_asset( 'hodima-header', 'assets/js/header.js', [], [ 'in_footer' => true ] );
}
add_action( 'wp_enqueue_scripts', 'hodima_header_assets', 20 );
