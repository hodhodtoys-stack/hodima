<?php
/**
 * Arian Clean Router - Permalink output
 * تولید لینک‌های تمیز: حذف پایه دسته‌بندی و محصولات.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** حذف یک پایه از ابتدای لینک، فقط بعد از مسیر خانه (مرز-امن) */
function arian_strip_leading_base( $link, $base ) {
    if ( $base === '' ) return $link;
    $home = trailingslashit( home_url() );
    $needle = $home . $base . '/';
    if ( strpos( $link, $needle ) === 0 ) {
        return $home . substr( $link, strlen( $needle ) );
    }
    return $link;
}

/**
 * لینک محصول → حذف پایه و استفاده از نامک (Slug).
 * برای وضعیت‌های غیرمنتشر لینک پیش‌فرض حفظ می‌شود تا پیش‌نمایش نشکند.
 */
add_filter( 'post_type_link', function ( $permalink, $post ) {
    if ( empty( $post->post_type ) || $post->post_type !== 'product' ) {
        return $permalink;
    }
    $unpublished = array( 'draft', 'pending', 'auto-draft', 'future' );
    if ( in_array( $post->post_status, $unpublished, true ) ) {
        return $permalink; // اجازه بده WP لینک پیش‌نمایش بسازد
    }
    // جایگزینی ID با post_name برای برگرداندن نامک در آدرس
    return home_url( user_trailingslashit( $post->post_name ) );
}, 10, 2 );

/** حذف پایه دسته نوشته و دسته محصول از لینک ترم‌ها */
add_filter( 'term_link', function ( $termlink, $term, $taxonomy ) {
    if ( $taxonomy === 'category' ) {
        return arian_strip_leading_base( $termlink, arian_category_base() );
    }
    if ( $taxonomy === 'product_cat' ) {
        return arian_strip_leading_base( $termlink, arian_product_cat_base() );
    }
    return $termlink;
}, 10, 3 );