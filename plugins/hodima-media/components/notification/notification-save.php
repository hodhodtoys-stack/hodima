<?php
/**
 * ذخیره کادر تنظیمات نوتیفیکیشن و پاک کردن کش فهرست فعال‌ها.
 *
 * @package Hodima\Media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/notification-settings.php';

add_action( 'save_post_hd_notification', 'hodima_save_notification_data' );

/*
 * کش فهرست فعال‌ها با هر تغییر وضعیت (ویرایش سریع، کارهای دسته‌جمعی،
 * انتشار زمان‌بندی‌شده)، حذف، زباله‌دان و بازگردانی پاک می‌شود. تا نسخه
 * 2.0.1 فقط ذخیره فرم کامل (بعد از بررسی nonce) آن را پاک می‌کرد و تغییر از
 * فهرست تا ۱۲ ساعت روی سایت اثر نمی‌کرد.
 */
add_action( 'transition_post_status', 'hodima_notification_status_changed', 10, 3 );
add_action( 'deleted_post', 'hodima_clear_notification_cache' );
add_action( 'trashed_post', 'hodima_clear_notification_cache' );
add_action( 'untrashed_post', 'hodima_clear_notification_cache' );

/** حذف/زباله‌دان/بازگردانی یک نوتیفیکیشن. */
function hodima_clear_notification_cache( int $post_id ): void {
	if ( HODIMA_NOTIFICATION_POST_TYPE === get_post_type( $post_id ) ) {
		hodima_notification_clear_cache();
	}
}

/** تغییر وضعیت انتشار یک نوتیفیکیشن. */
function hodima_notification_status_changed( string $new_status, string $old_status, WP_Post $post ): void {
	if ( HODIMA_NOTIFICATION_POST_TYPE === $post->post_type ) {
		hodima_notification_clear_cache();
	}
}

/**
 * ذخیره کادر تنظیمات. فقط فیلدهایی که در فرم آمده‌اند عوض می‌شوند (کلید
 * روشن/خاموش نیامده = خاموش)؛ فیلدی که فرم ندارد دست نمی‌خورد (تا 2.0.1
 * «بستن خودکار» و «نقش کاربر» فیلد نداشتند و هر ذخیره صفرشان می‌کرد).
 */
function hodima_save_notification_data( int $post_id ): void {

	$nonce = isset( $_POST['hodima_notification_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_notification_meta_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'hodima_save_notification_data' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( hodima_notification_fields() as $key => $field ) {
		if ( 'bool' !== $field['type'] && ! array_key_exists( $key, $_POST ) ) {
			continue;
		}
		$raw = 'bool' === $field['type'] ? ( isset( $_POST[ $key ] ) ? '1' : '0' ) : wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- hodima_notification_sanitize() بر اساس نوع فیلد
		update_post_meta( $post_id, '_' . $key, hodima_notification_sanitize( $field, $raw ) );
	}

	// «همه صفحات»: فهرست آدرس‌ها بی‌معناست (فیلدش در فرم غیرفعال است و ارسال نمی‌شود)
	if ( 'all' === get_post_meta( $post_id, '_hd_notif_target_type', true ) ) {
		update_post_meta( $post_id, '_hd_notif_target_page', '' );
	}

	// شناسه پیوست فقط وقتی معتبر است که همان عکس انتخاب‌شده باشد
	$image_id = (int) get_post_meta( $post_id, '_hd_notif_bg_image_id', true );
	if ( $image_id > 0 && ( ! wp_attachment_is_image( $image_id ) || '' === (string) get_post_meta( $post_id, '_hd_notif_bg_image', true ) ) ) {
		update_post_meta( $post_id, '_hd_notif_bg_image_id', '0' );
	}

	hodima_notification_clear_cache();
}
