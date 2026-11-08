<?php
/**
 * پیام‌ها و پشتیبانی در پنل مشتری: ثبت تیکت مشاوره، پیگیری سفارش، پاسخ به تیکت و داده نما.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * پشتیبانی سمت مشتری.
 *
 * تا 0.2.4 در legacy/support.php (کلوژر سراسری بی کلاس، بیرون از PHPCS/PHPStan) و کوئری‌های
 * نما داخل templates/views/support.php بود. «پیام تازه» فقط دو راه (طرح تأییدشده 0.2.0):
 * «مشاوره قبل از خرید» = تیکت تازه؛ «پیگیری سفارش» = پیام در گفتگوی همان سفارش، نه تیکت جدا.
 * نام فیلدها و nonceها همان قبلی (فرم‌های نما).
 */
final class Support {

	/** بیشترین تیکت و گفتگوی فهرست پنل مشتری. */
	public const LIST_TICKETS = 50;

	public const LIST_CHATS = 20;

	/** ثبت هوک. */
	public static function boot(): void {
		add_action( 'hodima_panel_request', [ self::class, 'handle' ] );
	}

	/**
	 * برگشت به بخش پشتیبانی با پارامترها (ریدایرکت پیش از چاپ صفحه).
	 *
	 * @param array<string, int|string> $args پارامترهای آدرس (msg، thread_id، new).
	 */
	private static function back( array $args ): never {
		wp_safe_redirect( Page::url( [ 'section' => 'support' ] + $args ) );
		exit;
	}

	/**
	 * پردازش پیش از چاپ صفحه.
	 *
	 * @param string $section بخش درخواستی.
	 */
	public static function handle( string $section ): void {
		if ( 'support' !== $section || ! is_user_logged_in() ) {
			return;
		}
		global $wpdb;
		$user_id = get_current_user_id();

		// باز کردن یک تیکت = خواندن پاسخ‌ها؛ پیش از چاپ منو تا عدد منو هم درست باشد
		$viewing = absint( $_GET['thread_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط خوانده‌شدن تیکت خود کاربر.
		if ( $viewing ) {
			$wpdb->update( table( 'tickets' ), [ 'user_unread' => 0 ], [ 'id' => $viewing, 'user_id' => $user_id ], [ '%d' ], [ '%d', '%d' ] );
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce هر کار در create()/reply().
		$action = sanitize_key( wp_unslash( (string) ( $_POST['support_action'] ?? '' ) ) );
		// phpcs:enable
		if ( 'create_ticket' === $action ) {
			self::create( $user_id );
		}
		if ( 'reply_to_ticket' === $action ) {
			self::reply( $user_id );
		}
	}

	/**
	 * «پیام تازه»: مشاوره (تیکت) یا پیگیری سفارش (گفتگوی سفارش).
	 *
	 * @param int $user_id مشتری.
	 */
	private static function create( int $user_id ): never {
		global $wpdb;
		check_admin_referer( 'create_ticket_nonce', '_wpnonce_create_ticket' );
		// دکمه فشرده‌شده نوع را تعیین می‌کند (بدون JS هر دو بخش فرم دیده می‌شوند)، بعد گزینه انتخاب‌شده
		$topic = sanitize_key( wp_unslash( (string) ( $_POST['topic_submit'] ?? $_POST['topic'] ?? '' ) ) );

		if ( 'order' === $topic ) {
			$order_id = absint( $_POST['order_id'] ?? 0 );
			$text     = sanitize_textarea_field( wp_unslash( (string) ( $_POST['order_message'] ?? '' ) ) );
			if ( ! Orders::owned( $order_id, $user_id ) ) {
				self::back( [ 'new' => 1, 'msg' => 'error_owner' ] );
			}
			if ( '' === trim( $text ) ) {
				self::back( [ 'new' => 1, 'msg' => 'message_empty' ] );
			}
			Orders::add_chat_message( $order_id, $user_id, 'user', $text );
			self::back( [ 'msg' => 'order_message_sent' ] );
		}

		$message = sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) );
		$subject = mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['subject'] ?? '' ) ) ), 0, 200 );
		if ( '' === trim( $message ) ) {
			self::back( [ 'new' => 1, 'msg' => 'message_empty' ] );
		}
		$now = now();
		$ok  = $wpdb->insert(
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
			self::back( [ 'new' => 1, 'msg' => 'ticket_error' ] );
		}
		$wpdb->update( table( 'tickets' ), [ 'reference_code' => sprintf( 'TKT-%06d', $ticket_id ) ], [ 'id' => $ticket_id ] );
		if ( ! $wpdb->insert( table( 'ticket_messages' ), [ 'ticket_id' => $ticket_id, 'sender_id' => $user_id, 'sender_type' => 'user', 'message' => $message, 'created_at' => $now ] ) ) {
			// تیکت بی پیام نماند (تا 0.2.2 می‌ماند و مدیر رشته خالی می‌دید)
			$wpdb->delete( table( 'tickets' ), [ 'id' => $ticket_id ], [ '%d' ] );
			self::back( [ 'new' => 1, 'msg' => 'ticket_error' ] );
		}
		clear_admin_badge();
		self::back( [ 'thread_id' => $ticket_id, 'msg' => 'ticket_sent' ] );
	}

	/**
	 * پاسخ مشتری به تیکت خودش؛ هر رد با ریدایرکت و پیام (تا 0.2.2 بی‌صدا برمی‌گشت).
	 *
	 * @param int $user_id مشتری.
	 */
	private static function reply( int $user_id ): never {
		global $wpdb;
		check_admin_referer( 'reply_ticket_nonce', '_wpnonce_reply_ticket' );
		$ticket_id = absint( $_POST['thread_id'] ?? 0 );
		$message   = sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) );
		$status    = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id = %d AND user_id = %d', table( 'tickets' ), $ticket_id, $user_id ) );
		if ( null === $status ) {
			self::back( [ 'msg' => 'error_owner' ] );
		}
		if ( 'closed' === $status ) {
			self::back( [ 'thread_id' => $ticket_id, 'msg' => 'ticket_closed' ] );
		}
		if ( '' === trim( $message ) ) {
			self::back( [ 'thread_id' => $ticket_id, 'msg' => 'message_empty' ] );
		}
		$now = now();
		if ( ! $wpdb->insert( table( 'ticket_messages' ), [ 'ticket_id' => $ticket_id, 'sender_id' => $user_id, 'sender_type' => 'user', 'message' => $message, 'created_at' => $now ] ) ) {
			self::back( [ 'thread_id' => $ticket_id, 'msg' => 'ticket_error' ] );
		}
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
		self::back( [ 'thread_id' => $ticket_id, 'msg' => 'ticket_sent' ] );
	}

	/**
	 * داده نمای «پیام‌ها و پشتیبانی» (همه کوئری‌ها اینجا، نه در نما).
	 *
	 * @param int  $user_id مشتری.
	 * @param int  $open    تیکت باز (۰ = هیچ).
	 * @param bool $compose فرم «پیام تازه» خواسته شده.
	 * @return array{tickets: list<object>, chats: list<object>, ticket: object|null, messages: list<object>, new: bool, orders: list<object>}
	 */
	public static function inbox( int $user_id, int $open, bool $compose ): array {
		global $wpdb;
		$tickets  = array_values( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY last_message_at DESC, id DESC LIMIT %d', table( 'tickets' ), $user_id, self::LIST_TICKETS ) ) );
		$chats    = array_values( (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE user_id = %d AND last_message_preview <> '' ORDER BY last_message_at DESC LIMIT %d", table( 'chats' ), $user_id, self::LIST_CHATS ) ) );
		$ticket   = null;
		$messages = [];
		if ( $open ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND user_id = %d', table( 'tickets' ), $open, $user_id ) );
			if ( is_object( $row ) ) {
				$ticket   = $row;
				$messages = array_values( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ticket_id = %d ORDER BY id ASC', table( 'ticket_messages' ), $open ) ) );
			}
		}
		$new = $compose || ( ! $ticket && ! $tickets );
		// سفارش‌های خود مشتری برای «پیگیری سفارش» (تازه‌ترین بالا)
		$orders = $new && ! $ticket ? Order_Query::for_topic( $user_id ) : [];
		return [ 'tickets' => $tickets, 'chats' => $chats, 'ticket' => $ticket, 'messages' => $messages, 'new' => $new, 'orders' => $orders ];
	}
}
