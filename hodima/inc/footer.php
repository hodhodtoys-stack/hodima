<?php
/**
 * Footer Module – Enqueue styles & scripts
 *
 * @package suspended suspended suspended suspended
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue footer-specific CSS & JS.
 */
function hodima_footer_assets() {

    /* ---------- CSS ---------- */
    $css_file = '/assets/css/footer.css';
    wp_enqueue_style(
        'hodima-footer',
        hodima_URI . $css_file,
        array(),
        file_exists( hodima_DIR . $css_file )
            ? filemtime( hodima_DIR . $css_file )
            : hodima_VERSION
    );

    /* ---------- JS ---------- */
    $js_file = '/assets/js/footer.js';
    wp_enqueue_script(
        'hodima-footer',
        hodima_URI . $js_file,
        array(),                       // بدون وابستگی — Vanilla JS
        file_exists( hodima_DIR . $js_file )
            ? filemtime( hodima_DIR . $js_file )
            : hodima_VERSION,
        true                           // لود در فوتر
    );
}
add_action( 'wp_enqueue_scripts', 'hodima_footer_assets', 20 );

