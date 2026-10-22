<?php
/**
 * پست‌تایپ نوتیفیکیشن‌ها، ستون‌های فهرست و آیکون منو.
 *
 * @package Hodima\Media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/notification-settings.php';

add_action( 'init', 'hodima_register_notification_cpt' );

/** ثبت پست‌تایپ (نامک hd_notification و آدرس پیشخوان همان قبلی). */
function hodima_register_notification_cpt(): void {

	// زیر «ابزارهای هدیما»؛ بدون Hodima Core مثل قبل منوی سطح اول
	$menu_parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';

	register_post_type(
		HODIMA_NOTIFICATION_POST_TYPE,
		[
			'labels'             => [
				'name'               => 'نوتیفیکیشن‌ها',
				'singular_name'      => 'نوتیفیکیشن',
				'menu_name'          => 'نوتیفیکیشن‌ها',
				'name_admin_bar'     => 'نوتیفیکیشن',
				'add_new'            => 'افزودن نوتیفیکیشن',
				'add_new_item'       => 'افزودن نوتیفیکیشن تازه',
				'new_item'           => 'نوتیفیکیشن تازه',
				'edit_item'          => 'ویرایش نوتیفیکیشن',
				'view_item'          => 'مشاهده نوتیفیکیشن',
				'all_items'          => 'نوتیفیکیشن‌ها',
				'search_items'       => 'جستجوی نوتیفیکیشن',
				'not_found'          => 'نوتیفیکیشنی پیدا نشد.',
				'not_found_in_trash' => 'در زباله‌دان نوتیفیکیشنی نیست.',
			],
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
			'supports'           => [ 'title' ],
		]
	);
}

/* ─── ستون‌های فهرست ─────────────────────────────────────────────── */

add_filter( 'manage_hd_notification_posts_columns', 'hodima_set_custom_notification_columns' );

/**
 * ستون «وضعیت نمایش» و «جا» بعد از عنوان.
 *
 * @param array<string, string> $columns ستون‌های وردپرس.
 * @return array<string, string>
 */
function hodima_set_custom_notification_columns( array $columns ): array {
	$out = [];
	foreach ( $columns as $key => $title ) {
		$out[ $key ] = $title;
		if ( 'title' === $key ) {
			$out['notif_status']   = 'وضعیت نمایش';
			$out['notif_position'] = 'جای نمایش';
		}
	}
	return $out;
}

add_action( 'manage_hd_notification_posts_custom_column', 'hodima_custom_notification_column_content', 10, 2 );

/** محتوای ستون‌ها. */
function hodima_custom_notification_column_content( string $column, int $post_id ): void {

	if ( 'notif_status' === $column ) {
		[ $type, $label, $note ] = hodima_notification_display_state( $post_id );
		printf( '<span class="hd-pill hd-pill--%1$s">%2$s</span>', esc_attr( $type ), esc_html( $label ) );
		if ( '' !== $note ) {
			printf( '<br><small class="hd-muted">%s</small>', esc_html( $note ) );
		}
		return;
	}

	if ( 'notif_position' === $column ) {
		$meta     = hodima_notification_meta( $post_id );
		$position = hodima_notification_normalize_position( $meta['hd_notif_position'] );
		echo esc_html( hodima_notification_position_label( $position ) );
		if ( 'center' === $position ) {
			printf( '<br><small class="hd-muted">%s</small>', esc_html( 'corner' === $meta['hd_notif_mobile_mode'] ? 'موبایل: جعبه گوشه' : 'موبایل: پنجره وسط' ) );
		}
	}
}

/** نام فارسی موقعیت. */
function hodima_notification_position_label( string $position ): string {
	return match ( $position ) {
		'bottom_right' => 'گوشه پایین راست',
		'bottom_left'  => 'گوشه پایین چپ',
		default        => 'پنجره وسط صفحه',
	};
}

/**
 * وضعیت نمایش یک اعلان برای ستون فهرست (قبلا فقط کلید روشن/خاموش بود و
 * اعلان تمام‌شده، زمان‌بندی‌شده یا بدون عکس هم «فعال» نوشته می‌شد).
 *
 * @return array{0: string, 1: string, 2: string} نوع برچسب (ok/warn/error/info)، متن، توضیح
 */
function hodima_notification_display_state( int $post_id ): array {
	$status = (string) get_post_status( $post_id );
	$meta   = hodima_notification_meta( $post_id );
	$start  = $meta['hd_notif_start_date'];
	$end    = $meta['hd_notif_end_date'];
	$today  = current_time( 'Y-m-d' );

	return match ( true ) {
		'future' === $status => [ 'info', 'زمان‌بندی‌شده', 'انتشار: ' . get_the_date( '', $post_id ) ],
		'publish' !== $status => [ 'warn', 'منتشر نشده', '' ],
		'0' === $meta['hd_notif_is_active'] => [ 'error', 'غیرفعال', '' ],
		'' === $meta['hd_notif_bg_image'] => [ 'warn', 'بدون عکس', 'تا عکس انتخاب نشود نمایش داده نمی‌شود' ],
		'' !== $end && $today > $end => [ 'error', 'تمام‌شده', 'پایان: ' . hodima_notification_format_date( $end ) ],
		'' !== $start && $today < $start => [ 'info', 'هنوز شروع نشده', 'شروع: ' . hodima_notification_format_date( $start ) ],
		default => [ 'ok', 'فعال', '' !== $end ? 'تا ' . hodima_notification_format_date( $end ) : '' ],
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

/* ─── آیکون منو ──────────────────────────────────────────────────── */

/*
 * بلندگوی منو رو به راست (منوی راست‌به‌چپ). قبلا یک تگ <style> در admin_head
 * هر صفحه پیشخوان؛ حالا یک قانون کنار CSS خود منوی وردپرس.
 */
add_action(
	'admin_enqueue_scripts',
	static function (): void {
		wp_add_inline_style( 'admin-menu', '#adminmenu #menu-posts-hd_notification .wp-menu-image::before{display:inline-block;transform:scaleX(-1)}' );
	}
);
