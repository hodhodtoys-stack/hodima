<?php
/**
 * گفتگوی هر سفارش در پنل مشتری (AJAX؛ منتقل‌شده از پنل قدیمی).
 *
 * تغییر این مرحله: گفتگو فقط از روی سفارش پیدا می‌شود و فقط برای صاحب همان سفارش
 * (پنل قدیمی شناسه گفتگو را از مرورگر می‌پذیرفت و هر مشتری می‌توانست در گفتگوی
 * سفارش دیگران پیام بگذارد — گزارش، مورد ۳). یک nonce برای همه درخواست‌ها.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy;

use function Hodima\Panel\show_date;
use function Hodima\Panel\table;

defined( 'ABSPATH' ) || exit;

/**
 * سفارشِ مال کاربر فعلی (یا خروج با خطای JSON).
 *
 * @return int شناسه سفارش.
 */
function chat_order_or_fail(): int {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( [ 'message' => 'لطفا ابتدا وارد شوید.' ] );
	}
	if ( ! check_ajax_referer( 'hodima_panel_chat', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'نشست شما منقضی شده است؛ صفحه را دوباره باز کنید.' ] );
	}
	global $wpdb;
	$order_id = absint( $_POST['order_id'] ?? 0 );
	$owner    = $order_id ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE id = %d', table( 'orders' ), $order_id ) ) : 0;
	if ( ! $order_id || $owner !== get_current_user_id() ) {
		wp_send_json_error( [ 'message' => 'سفارش پیدا نشد.' ] );
	}
	return $order_id;
}

/**
 * HTML یک پیام گفتگو.
 *
 * @param object $msg ردیف پیام (sender_type، message، created_at).
 */
function chat_message_html( object $msg ): string {
	[ $class, $name ] = match ( $msg->sender_type ) {
		'user'   => [ 'me', 'شما' ],
		'system' => [ 'system', 'سیستم' ],
		default  => [ 'them', 'پشتیبانی' ],
	};
	return '<div class="hpn-msg hpn-msg--' . esc_attr( $class ) . '"><p>' . nl2br( esc_html( (string) $msg->message ) ) . '</p><span class="hpn-msg__meta">'
		. esc_html( $name . ' · ' . show_date( (string) $msg->created_at, 'j F، H:i' ) ) . '</span></div>';
}

add_action( 'wp_ajax_hodima_panel_chat_send', static function (): void {
	$order_id = chat_order_or_fail();
	$message  = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
	if ( '' === trim( $message ) ) {
		wp_send_json_error( [ 'message' => 'متن پیام خالی است.' ] );
	}
	\Hodima\Panel\Orders::add_chat_message( $order_id, get_current_user_id(), 'user', $message );
	wp_send_json_success( [ 'html' => chat_message_html( (object) [ 'sender_type' => 'user', 'message' => $message, 'created_at' => \Hodima\Panel\now() ] ) ] );
} );

add_action( 'wp_ajax_hodima_panel_chat_history', static function (): void {
	global $wpdb;
	$order_id = chat_order_or_fail();
	$chat_id  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE order_id = %d', table( 'chats' ), $order_id ) );
	$rows     = $chat_id ? (array) $wpdb->get_results( $wpdb->prepare( 'SELECT sender_type, message, created_at FROM %i WHERE chat_id = %d ORDER BY id ASC', table( 'chat_messages' ), $chat_id ) ) : [];

	$html = $rows ? implode( '', array_map( __NAMESPACE__ . '\chat_message_html', $rows ) ) : '<p class="no-messages hpn-muted">هنوز پیامی نیست. سؤالتان درباره این سفارش را بنویسید.</p>';

	// باز کردن گفتگو = خواندن پیام‌های پشتیبانی
	if ( $chat_id ) {
		$wpdb->update( table( 'chats' ), [ 'user_unread' => 0 ], [ 'id' => $chat_id ], [ '%d' ], [ '%d' ] );
	}
	wp_send_json_success( [ 'html' => $html ] );
} );
