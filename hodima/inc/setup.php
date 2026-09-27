<?php
/**
 * Theme basic setup and menu registration
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function hodima_theme_setup() {
    // پشتیبانی از تگ عنوان سایت
    add_theme_support( 'title-tag' );

    // پشتیبانی از تصویر شاخص
    add_theme_support( 'post-thumbnails' );

    // پشتیبانی از ووکامرس
    add_theme_support( 'woocommerce' );
    
    // امکانات گالری محصول ووکامرس (زوم، لایت‌باکس، اسلایدر)
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'hodima_theme_setup' );

function hodima_register_my_menu() {
    register_nav_menus( array(
        'منوی اصلی' => 'منوی اصلی'
    ) );
}
add_action( 'init', 'hodima_register_my_menu' );