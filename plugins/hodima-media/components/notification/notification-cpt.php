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

// 2. نمایش محتوای ستون: وضعیت واقعی نمایش، نه فقط کلید روشن/خاموش
// (قبلا اعلان تمام‌شده، زمان‌بندی‌شده یا بدون عکس هم «فعال» نوشته می‌شد)
add_action( 'manage_hd_notification_posts_custom_column', 'hodima_custom_notification_column_content', 10, 2 );
function hodima_custom_notification_column_content( string $column, int $post_id ): void {
    if ( $column !== 'notif_status' ) {
        return;
    }

    [ $type, $label, $note ] = hodima_notification_display_state( $post_id );

    printf( '<span class="hd-pill hd-pill--%1$s">%2$s</span>', esc_attr( $type ), esc_html( $label ) );
    if ( '' !== $note ) {
        printf( '<br><small class="hd-muted">%s</small>', esc_html( $note ) );
    }
}

/**
 * وضعیت نمایش یک اعلان برای ستون فهرست.
 *
 * @return array{0: string, 1: string, 2: string} نوع برچسب (ok/warn/error/info)، متن، توضیح
 */
function hodima_notification_display_state( int $post_id ): array {
    $status = (string) get_post_status( $post_id );
    $start  = (string) get_post_meta( $post_id, '_hd_notif_start_date', true );
    $end    = (string) get_post_meta( $post_id, '_hd_notif_end_date', true );
    $today  = current_time( 'Y-m-d' );
    $until  = '' !== $end ? 'تا ' . hodima_notification_format_date( $end ) : '';

    return match ( true ) {
        'future' === $status => [ 'info', 'زمان‌بندی‌شده', 'انتشار: ' . get_the_date( '', $post_id ) ],
        'publish' !== $status => [ 'warn', 'منتشر نشده', '' ],
        '0' === get_post_meta( $post_id, '_hd_notif_is_active', true ) => [ 'error', 'غیرفعال', '' ],
        '' === (string) get_post_meta( $post_id, '_hd_notif_bg_image', true ) => [ 'warn', 'بدون عکس', 'تا عکس انتخاب نشود نمایش داده نمی‌شود' ],
        '' !== $end && $today > $end => [ 'error', 'تمام‌شده', 'پایان: ' . hodima_notification_format_date( $end ) ],
        '' !== $start && $today < $start => [ 'info', 'هنوز شروع نشده', 'شروع: ' . hodima_notification_format_date( $start ) ],
        default => [ 'ok', 'فعال', $until ],
    };
}

/** تاریخ ذخیره‌شده (میلادی Y-m-d) برای نمایش؛ با Core شمسی. */
function hodima_notification_format_date( string $date ): string {
    $time = strtotime( $date . ' 12:00:00 UTC' );
    if ( false === $time ) {
        return $date;
    }
    return function_exists( 'hodima_jalali_format' ) ? hodima_jalali_format( 'j F Y', $time ) : wp_date( 'Y-m-d', $time, new DateTimeZone( 'UTC' ) );
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
