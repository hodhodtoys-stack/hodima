<?php
/**
 * ظاهر صفحه‌های فهرست و ویرایش نوتیفیکیشن در پیشخوان.
 *
 * - سربرگ هدیما با تب‌های «نوتیفیکیشن‌ها | افزودن | آمار» بالای فهرست و
 *   صفحه ویرایش (به‌جای عنوان ساده وردپرس؛ همان تب‌های صفحه آمار).
 * - فهرست: عکس کوچک هر نوتیفیکیشن و کلید روشن/خاموش در خود فهرست (بدون
 *   باز کردن صفحه ویرایش؛ admin-ajax با nonce و دسترسی edit_post).
 *
 * @package Hodima\Media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/notification-settings.php';

/**
 * صفحه فعلی پیشخوان: 'list'، 'new'، 'edit' یا '' (صفحه نوتیفیکیشن نیست).
 */
function hodima_notification_screen(): string {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || HODIMA_NOTIFICATION_POST_TYPE !== $screen->post_type ) {
		return '';
	}
	return match ( $screen->base ) {
		'edit' => 'list',
		'post' => 'add' === $screen->action ? 'new' : 'edit',
		default => '',
	};
}

add_filter(
	'admin_body_class',
	static fn( string $classes ): string => '' !== hodima_notification_screen() ? $classes . ' hdn-screen ' : $classes
);

add_action( 'admin_enqueue_scripts', 'hodima_notification_admin_assets' );

/** CSS سربرگ/فهرست و JS کلید روشن/خاموش. */
function hodima_notification_admin_assets(): void {
	$screen = hodima_notification_screen();
	if ( '' === $screen ) {
		return;
	}
	wp_enqueue_style( 'hd-notification-admin-css', hodima_notification_asset_url( 'css/notification-admin.css' ), [], hodima_notification_asset_version( 'css/notification-admin.css' ) );
	if ( 'list' === $screen ) {
		wp_enqueue_script( 'hd-notification-list-js', hodima_notification_asset_url( 'js/notification-list.js' ), [], hodima_notification_asset_version( 'js/notification-list.js' ), [ 'in_footer' => true ] );
		wp_add_inline_script(
			'hd-notification-list-js',
			'window.hodimaNotifList=' . wp_json_encode(
				[
					'ajax'  => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'hodima_notif_toggle' ),
				]
			) . ';',
			'before'
		);
	}
}

/*
 * سربرگ پیش از .wrap خود وردپرس (all_admin_notices اولین خروجی داخل
 * #wpbody-content است). wp-header-end سربرگ برداشته می‌شود: صفحه وردپرس
 * خودش یکی دارد و common.js پیام‌ها را بعد از *همه* آن‌ها کپی می‌کرد.
 */
add_action( 'all_admin_notices', 'hodima_notification_admin_header', 0 );

/** سربرگ و تب‌های صفحه فهرست/ویرایش. */
function hodima_notification_admin_header(): void {
	$screen = hodima_notification_screen();
	if ( '' === $screen || ! function_exists( 'hodima_notification_admin_tabs' ) || ! function_exists( 'hodima_admin_header' ) ) {
		return;
	}
	$titles = [
		'list' => [ 'نوتیفیکیشن‌ها', 'پاپ‌آپ‌های اطلاع‌رسانی سایت: ساخت، زمان‌بندی، هدف‌گیری و آمار.' ],
		'new'  => [ 'افزودن نوتیفیکیشن', 'عکس را انتخاب کنید، جای نمایش و مخاطب را تعیین کنید و منتشر کنید.' ],
		'edit' => [ 'ویرایش نوتیفیکیشن', 'تغییرها بعد از «به‌روزرسانی» روی سایت می‌آیند (کش لایت‌اسپید را هم پاک کنید).' ],
	];
	ob_start();
	hodima_admin_header(
		[
			'title'       => $titles[ $screen ][0],
			'description' => $titles[ $screen ][1],
			'icon'        => 'dashicons-megaphone',
			'tabs'        => hodima_notification_admin_tabs(),
			'current'     => 'edit' === $screen ? 'list' : $screen,
			'tabs_label'  => 'بخش‌های نوتیفیکیشن',
		]
	);
	$html = str_replace( '<hr class="wp-header-end">', '', (string) ob_get_clean() );
	echo '<div class="hd-wrap hdn-screen-head">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی hodima_admin_header (escape شده)
}

/* ─── فهرست: عکس کوچک و کلید روشن/خاموش ──────────────────────────── */

add_filter(
	'manage_hd_notification_posts_columns',
	static function ( array $columns ): array {
		$out = [];
		foreach ( $columns as $key => $title ) {
			if ( 'title' === $key ) {
				$out['notif_thumb'] = '<span class="screen-reader-text">عکس</span>';
			}
			$out[ $key ] = $title;
		}
		return $out;
	},
	5
);

add_action(
	'manage_hd_notification_posts_custom_column',
	static function ( string $column, int $post_id ): void {
		if ( 'notif_thumb' === $column ) {
			$meta  = hodima_notification_meta( $post_id );
			$id    = (int) $meta['hd_notif_bg_image_id'];
			$thumb = $id > 0 ? wp_get_attachment_image_url( $id, 'thumbnail' ) : false;
			$thumb = $thumb ? $thumb : $meta['hd_notif_bg_image'];
			printf(
				'<span class="hdn-thumb%1$s">%2$s</span>',
				'1' === $meta['hd_notif_bg_transparent'] ? ' is-transparent' : '',
				'' !== $thumb ? sprintf( '<img src="%s" alt="" loading="lazy" decoding="async">', esc_url( (string) $thumb ) ) : hodima_notification_admin_icon( 'format-image' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape بالا و در تابع
			);
			return;
		}
		if ( 'notif_status' === $column && current_user_can( 'edit_post', $post_id ) ) {
			$on = '0' !== hodima_notification_meta( $post_id )['hd_notif_is_active'];
			printf(
				'<button type="button" class="hdn-switch" role="switch" aria-checked="%1$s" data-hdn-toggle="%2$d" hidden><span class="screen-reader-text">%3$s</span></button>',
				$on ? 'true' : 'false',
				(int) $post_id,
				esc_html( sprintf( 'نمایش «%s» در سایت', get_the_title( $post_id ) ) )
			);
		}
	},
	5,
	2
);

/** آیکون Dashicons (بدون وابستگی به Core). */
function hodima_notification_admin_icon( string $name ): string {
	return sprintf( '<span class="dashicons dashicons-%s" aria-hidden="true"></span>', esc_attr( $name ) );
}

add_action( 'wp_ajax_hodima_notif_toggle', 'hodima_notification_toggle' );

/** روشن/خاموش کردن از فهرست؛ پاسخ: وضعیت تازه برای ستون. */
function hodima_notification_toggle(): void {
	check_ajax_referer( 'hodima_notif_toggle', 'nonce' );
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( HODIMA_NOTIFICATION_POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_send_json_error( [ 'message' => 'اجازه این کار را ندارید.' ], 403 );
	}
	$on = isset( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['active'] ) );
	update_post_meta( $id, '_hd_notif_is_active', $on ? '1' : '0' );
	hodima_notification_clear_cache();
	[ $type, $label, $note ] = hodima_notification_display_state( $id );
	wp_send_json_success(
		[
			'active' => $on,
			'type'   => $type,
			'label'  => $label,
			'note'   => $note,
		]
	);
}
