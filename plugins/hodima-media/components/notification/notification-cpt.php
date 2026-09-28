<?php
/**
 * Register Custom Post Type for Notifications
 *
 * @version 1.1.3
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // جلوگیری از دسترسی مستقیم
}

function hodima_register_notification_cpt(): void {
    $labels = array(
        'name'               => 'نوتفیکیشن‌ها',
        'singular_name'      => 'نوتفیکیشن',
        'menu_name'          => 'نوتفیکیشن‌ها',
        'name_admin_bar'     => 'نوتفیکیشن',
        'add_new'            => 'افزودن اعلان جدید',
        'add_new_item'       => 'افزودن نوتفیکیشن جدید',
        'new_item'           => 'نوتفیکیشن جدید',
        'edit_item'          => 'ویرایش نوتفیکیشن',
        'view_item'          => 'مشاهده نوتفیکیشن',
        'all_items'          => 'همه نوتفیکیشن‌ها',
        'search_items'       => 'جستجوی نوتفیکیشن',
        'not_found'          => 'نوتفیکیشنی یافت نشد.',
        'not_found_in_trash' => 'در زباله‌دان چیزی یافت نشد.'
    );

    // زیر «ابزارهای هدیما» (آدرس edit.php?post_type=hd_notification همان قبلی است)؛
    // بدون Hodima Core مثل قبل منوی سطح اول
    $menu_parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';
    if ( '' !== $menu_parent ) {
        $labels['all_items'] = 'نوتیفیکیشن‌ها'; // عنوان زیرمنو
    }

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => '' !== $menu_parent ? $menu_parent : true,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 30,
        'menu_icon'          => 'dashicons-megaphone',
        'supports'           => array( 'title' ),
    );

    register_post_type( 'hd_notification', $args );
}
add_action( 'init', 'hodima_register_notification_cpt' );

// -------------------------------------------------------------
// افزودن ستون وضعیت به لیست نوتفیکیشن‌ها
// -------------------------------------------------------------

// 1. تعریف ستون جدید در جدول
add_filter( 'manage_hd_notification_posts_columns', 'hodima_set_custom_notification_columns' );
function hodima_set_custom_notification_columns( array $columns ): array {
    $new_columns = array();
    foreach ( $columns as $key => $title ) {
        $new_columns[$key] = $title;
        if ( $key === 'title' ) {
            $new_columns['notif_status'] = 'وضعیت نمایش';
        }
    }
    return $new_columns;
}

// 2. نمایش محتوای ستون (فعال یا غیرفعال)
add_action( 'manage_hd_notification_posts_custom_column', 'hodima_custom_notification_column_content', 10, 2 );
function hodima_custom_notification_column_content( string $column, int $post_id ): void {
    if ( $column === 'notif_status' ) {
        $status = get_post_meta( $post_id, '_hd_notif_is_active', true );

        if ( $status === '1' ) {
            echo '<span style="color: #2f7a55; font-weight: bold; background: rgba(47, 122, 85, 0.1); padding: 4px 10px; border-radius: 4px;">فعال</span>';
        } else {
            echo '<span style="color: #a32a2a; font-weight: bold; background: #f8ecec; padding: 4px 10px; border-radius: 4px;">غیرفعال</span>';
        }
    }
}

// -------------------------------------------------------------
// برعکس کردن آیکون بلندگو در منوی پیشخوان وردپرس
// -------------------------------------------------------------
add_action( 'admin_head', 'hodima_flip_notification_menu_icon' );
function hodima_flip_notification_menu_icon(): void {
    ?>
    <style>
        #adminmenu #menu-posts-hd_notification .wp-menu-image::before {
            transform: scaleX(-1);
            display: inline-block;
        }
    </style>
    <?php
}
