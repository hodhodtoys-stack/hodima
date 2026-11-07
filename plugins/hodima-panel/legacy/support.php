<?php
/**
 * تیکت پشتیبانی در پنل مشتری: ثبت تیکت و پاسخ (منتقل‌شده از پنل قدیمی).
 *
 * تغییر این مرحله: پردازش پیش از چاپ صفحه (ریدایرکت بعد از ارسال واقعا کار
 * می‌کند و رفرش صفحه پیام را دوباره نمی‌فرستد)؛ دپارتمان و اولویت فقط از فهرست
 * مجاز؛ متن‌ها بدون «\» اضافه (wp_unslash).
 *
 * از 0.2.0 «پیام تازه» دو راه دارد (طرح تأییدشده کاربر): «مشاوره قبل از خرید» = تیکت
 * تازه (موضوع اختیاری)؛ «پیگیری سفارش» = پیام در گفتگوی همان سفارش، نه تیکت جدا
 * (تا یک سؤال سفارش در دو رشته پخش نشود). «با کدام واحد؟» و «فوریت» حذف شد.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy;

use Hodima\Panel\Orders;
use Hodima\Panel\Page;
use function Hodima\Panel\clear_admin_badge;
use function Hodima\Panel\now;
use function Hodima\Panel\preview;
use function Hodima\Panel\table;

defined( 'ABSPATH' ) || exit;

/**
 * برگشت به بخش پشتیبانی با پارامترها (ریدایرکت پیش از چاپ صفحه).
 *
 * @param array<string, int|string> $args پارامترهای آدرس (msg، thread_id، new).
 */
function support_back( array $args ): never {
	wp_safe_redirect( Page::url( [ 'section' => 'support' ] + $args ) );
	exit;
}

add_action( 'hodima_panel_request', static function ( string $section ): void {
	if ( 'support' !== $section || ! is_user_logged_in() ) {
		return;
	}
	global $wpdb;
	$user_id = get_current_user_id();

	// باز کردن یک تیکت = خواندن پاسخ‌ها؛ پیش از چاپ منو تا عدد منو هم درست باشد
	$viewing = absint( $_GET['thread_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $viewing ) {
		$wpdb->update( table( 'tickets' ), [ 'user_unread' => 0 ], [ 'id' => $viewing, 'user_id' => $user_id ], [ '%d' ], [ '%d', '%d' ] );
	}
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	$action  = sanitize_key( wp_unslash( $_POST['support_action'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
	$now     = now();

	if ( 'create_ticket' === $action ) {
		check_admin_referer( 'create_ticket_nonce', '_wpnonce_create_ticket' );
		// دکمه فشرده‌شده نوع را تعیین می‌کند (بدون JS هر دو بخش فرم دیده می‌شوند)، بعد گزینه انتخاب‌شده
		$topic = sanitize_key( wp_unslash( $_POST['topic_submit'] ?? $_POST['topic'] ?? '' ) );

		if ( 'order' === $topic ) {
			$order_id = absint( $_POST['order_id'] ?? 0 );
			$text     = sanitize_textarea_field( wp_unslash( $_POST['order_message'] ?? '' ) );
			if ( ! Orders::owned( $order_id, $user_id ) ) {
				support_back( [ 'new' => 1, 'msg' => 'error_owner' ] );
			}
			if ( '' === trim( $text ) ) {
				support_back( [ 'new' => 1, 'msg' => 'message_empty' ] );
			}
			Orders::add_chat_message( $order_id, $user_id, 'user', $text );
			support_back( [ 'msg' => 'order_message_sent' ] );
		}

		$subject = mb_substr( sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ), 0, 200 );
		if ( '' === trim( $message ) ) {
			support_back( [ 'new' => 1, 'msg' => 'message_empty' ] );
		}
		$ok = $wpdb->insert(
			table( 'tickets' ),
			[
				'user_id'              => $user_id,
				'department'           => 'consult',
				'subject'              => '' !== $subject ? $subject : 'مشاوره قبل از خرید',
				'status'               => 'open',
				'priority'             => 'normal',
				'admin_unread'         => 1,
				'last_message_sender'  => 'user',
				'last_message_preview' => preview( $message ),
				'last_message_at'      => $now,
				'created_at'           => $now,
				'updated_at'           => $now,
			]
		);
		$ticket_id = $ok ? (int) $wpdb->insert_id : 0;
		if ( ! $ticket_id ) {
			support_back( [ 'new' => 1, 'msg' => 'ticket_error' ] );
		}
		$wpdb->update( table( 'tickets' ), [ 'reference_code' => sprintf( 'TKT-%06d', $ticket_id ) ], [ 'id' => $ticket_id ] );
		$wpdb->insert( table( 'ticket_messages' ), [ 'ticket_id' => $ticket_id, 'sender_id' => $user_id, 'sender_type' => 'user', 'message' => $message, 'created_at' => $now ] );
		clear_admin_badge();
		support_back( [ 'thread_id' => $ticket_id, 'msg' => 'ticket_sent' ] );
	}

	if ( 'reply_to_ticket' === $action ) {
		check_admin_referer( 'reply_ticket_nonce', '_wpnonce_reply_ticket' );
		$ticket_id = absint( $_POST['thread_id'] ?? 0 );
		$status    = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id = %d AND user_id = %d', table( 'tickets' ), $ticket_id, $user_id ) );
		if ( null === $status || 'closed' === $status || '' === trim( $message ) ) {
			return;
		}
		$wpdb->insert( table( 'ticket_messages' ), [ 'ticket_id' => $ticket_id, 'sender_id' => $user_id, 'sender_type' => 'user', 'message' => $message, 'created_at' => $now ] );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'open', admin_unread = admin_unread + 1, user_unread = 0, last_message_sender = 'user', last_message_preview = %s, last_message_at = %s, updated_at = %s WHERE id = %d",
				table( 'tickets' ),
				preview( $message ),
				$now,
				$now,
				$ticket_id
			)
		);
		clear_admin_badge();
		wp_safe_redirect( Page::url( [ 'section' => 'support', 'thread_id' => $ticket_id, 'msg' => 'ticket_sent' ] ) );
		exit;
	}
} );
