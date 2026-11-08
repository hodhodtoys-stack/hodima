<?php
/**
 * گفتگوی هر سفارش در پنل مشتری (AJAX).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * گفتگوی سفارش: فرستادن پیام و تاریخچه.
 *
 * تا 0.2.4 در legacy/chat.php (تابع‌های سراسری بی کلاس، بیرون از PHPCS/PHPStan). گفتگو فقط از
 * روی سفارش پیدا می‌شود و فقط برای صاحب همان سفارش (پنل قدیمی شناسه گفتگو را از مرورگر
 * می‌پذیرفت — گزارش، مورد ۳). نام اکشن‌ها و nonce همان قبلی (chat.js می‌خواند).
 */
final class Chat {

	public const NONCE = 'hodima_panel_chat';

	/** ثبت هوک‌ها. */
	public static function boot(): void {
		add_action( 'wp_ajax_hodima_panel_chat_send', [ self::class, 'ajax_send' ] );
		add_action( 'wp_ajax_hodima_panel_chat_history', [ self::class, 'ajax_history' ] );
	}

	/**
	 * سفارشِ مال کاربر فعلی از درخواست (یا خروج با خطای JSON).
	 *
	 * @return int شناسه سفارش.
	 */
	private static function order_or_fail(): int {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'لطفا ابتدا وارد شوید.' ] );
		}
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'نشست شما منقضی شده است؛ صفحه را دوباره باز کنید.' ] );
		}
		$order_id = absint( $_POST['order_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد.
		if ( ! Orders::owned( $order_id, get_current_user_id() ) ) {
			wp_send_json_error( [ 'message' => 'سفارش پیدا نشد.' ] );
		}
		return $order_id;
	}

	/**
	 * HTML یک پیام گفتگو.
	 *
	 * @param object $msg ردیف پیام (sender_type، message، created_at).
	 */
	public static function message_html( object $msg ): string {
		[ $class, $name ] = match ( (string) $msg->sender_type ) {
			'user'   => [ 'me', 'شما' ],
			'system' => [ 'system', 'سیستم' ],
			default  => [ 'them', 'پشتیبانی' ],
		};
		return '<div class="hpn-msg hpn-msg--' . esc_attr( $class ) . '"><p>' . nl2br( esc_html( (string) $msg->message ) ) . '</p><span class="hpn-msg__meta">'
			. esc_html( $name . ' · ' . show_date( (string) $msg->created_at, 'j F، H:i' ) ) . '</span></div>';
	}

	/** فرستادن پیام مشتری. */
	public static function ajax_send(): void {
		$order_id = self::order_or_fail();
		$message  = sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce در order_or_fail.
		if ( '' === trim( $message ) ) {
			wp_send_json_error( [ 'message' => 'متن پیام خالی است.' ] );
		}
		Orders::add_chat_message( $order_id, get_current_user_id(), 'user', $message );
		wp_send_json_success( [ 'html' => self::message_html( (object) [ 'sender_type' => 'user', 'message' => $message, 'created_at' => now() ] ) ] );
	}

	/** تاریخچه گفتگو؛ باز کردن = خواندن پیام‌های پشتیبانی. */
	public static function ajax_history(): void {
		global $wpdb;
		$order_id = self::order_or_fail();
		$chat_id  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE order_id = %d', table( 'chats' ), $order_id ) );
		$rows     = $chat_id ? (array) $wpdb->get_results( $wpdb->prepare( 'SELECT sender_type, message, created_at FROM %i WHERE chat_id = %d ORDER BY id ASC', table( 'chat_messages' ), $chat_id ) ) : [];
		$html     = $rows
			? implode( '', array_map( [ self::class, 'message_html' ], $rows ) )
			: '<p class="no-messages hpn-muted">هنوز پیامی نیست. سؤالتان درباره این سفارش را بنویسید.</p>';
		if ( $chat_id ) {
			$wpdb->update( table( 'chats' ), [ 'user_unread' => 0 ], [ 'id' => $chat_id ], [ '%d' ], [ '%d' ] );
		}
		wp_send_json_success( [ 'html' => $html ] );
	}
}
