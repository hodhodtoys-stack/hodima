<?php
/**
 * Header Module – Enqueue styles & scripts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hodima_header_assets() {

    /* ---------- CSS ---------- */
    $css_file = '/assets/css/header.css';
    wp_enqueue_style(
        'hodima-header',
        hodima_URI . $css_file,
        array(),
        file_exists( hodima_DIR . $css_file )
            ? filemtime( hodima_DIR . $css_file )
            : hodima_VERSION
    );

    /* ---------- JS ---------- */
    $js_file = '/assets/js/header.js';
    wp_enqueue_script(
        'hodima-header',
        hodima_URI . $js_file,
        array(),
        file_exists( hodima_DIR . $js_file )
            ? filemtime( hodima_DIR . $js_file )
            : hodima_VERSION,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'hodima_header_assets', 20 );
