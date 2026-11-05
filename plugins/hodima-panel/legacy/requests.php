<?php
/**
 * ثبت و ویرایش سفارش، آپلود فیش و حذف سفارش (منتقل‌شده از پنل قدیمی).
 *
 * باقی‌مانده برای مرحله ۲ (امنیت): ویرایش/حذف فقط در وضعیت «در انتظار»، آپلود
 * فیش فقط در وضعیت درست و با نوع فایل مجاز در پوشه محافظت‌شده، بررسی حداقل
 * سفارش و مجاز بودن محصول در سرور.
 *
 * تغییر این مرحله: قیمت، ظرفیت و جمع هر قلم هنگام ثبت ذخیره می‌شود (مبلغ پنل
 * و پیشخوان یکی)، تعداد اعشاری درست ذخیره می‌شود، و پیام سیستمی «فیش آپلود شد»
 * واقعا ثبت و برای مدیر نخوانده شمرده می‌شود (پنل قدیمی در ستون ناموجود
 * is_read می‌نوشت و پیام هیچ‌وقت ذخیره نمی‌شد).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy;

use Hodima\Panel\Files;
use Hodima\Panel\Page;
use function Hodima\Panel\clear_admin_badge;
use function Hodima\Panel\now;
use function Hodima\Panel\table;

defined( 'ABSPATH' ) || exit;

/**
 * اقلام سفارش با قیمت و ظرفیت لحظه ثبت.
 *
 * @param int                 $order_id   شناسه سفارش.
 * @param array<mixed>        $products   شناسه محصولات انتخاب‌شده.
 * @param array<mixed, mixed> $quantities تعداد هر محصول (کلید = شناسه).
 * @return float جمع کل.
 */
function save_order_items( int $order_id, array $products, array $quantities ): float {
	global $wpdb;
	$items_table = table( 'order_items' );
	$wpdb->delete( $items_table, [ 'order_id' => $order_id ], [ '%d' ] );

	$total = 0.0;
	foreach ( array_unique( array_map( 'intval', $products ) ) as $product_id ) {
		$product = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			continue;
		}
		$qty = isset( $quantities[ $product_id ] ) ? (float) $quantities[ $product_id ] : 1.0;
		if ( $qty <= 0 ) {
			continue;
		}
		$price    = (float) $product->get_price();
		$capacity = max( 1, (int) get_post_meta( $product_id, '_up_unit_capacity', true ) );
		$line     = round( $qty * $capacity * $price, 2 );
		$total   += $line;
		$wpdb->insert(
			$items_table,
			[
				'order_id'      => $order_id,
				'product_id'    => $product_id,
				'quantity'      => round( $qty, 3 ),
				'unit_price'    => $price,
				'unit_capacity' => $capacity,
				'sale_unit'     => (string) get_post_meta( $product_id, '_up_sale_unit', true ),
				'line_total'    => $line,
			],
			[ '%d', '%d', '%f', '%f', '%d', '%s', '%f' ]
		);
	}
	return $total;
}

/** فرم پیش‌خرید و پیش‌فاکتور (admin-post.php). */
function handle_order_form(): void {
	if ( ! is_user_logged_in() ) {
		wp_die( 'لطفاً ابتدا وارد حساب کاربری خود شوید.' );
	}
	check_admin_referer( 'user_panel_action' );

	global $wpdb;
	$user_id    = get_current_user_id();
	$type       = 'save_preinvoice' === sanitize_key( wp_unslash( $_POST['action'] ?? '' ) ) ? 'preinvoice' : 'prebuy';
	$products   = isset( $_POST['products'] ) ? (array) wp_unslash( $_POST['products'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- intval در save_order_items.
	$quantities = isset( $_POST['qty'] ) ? (array) wp_unslash( $_POST['qty'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- (float) در save_order_items.
	$order_id   = absint( $_POST['order_id'] ?? 0 );

	if ( ! $products ) {
		wp_safe_redirect( Page::url( [ 'section' => $type, 'msg' => 'empty' ] ) );
		exit;
	}

	$orders = table( 'orders' );
	$now    = now();
	if ( $order_id > 0 ) {
		$owner = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE id = %d', $orders, $order_id ) );
		if ( $owner !== $user_id ) {
			wp_die( 'عدم دسترسی مجاز.' );
		}
		$msg = 'order_updated';
	} else {
		$wpdb->insert( $orders, [ 'user_id' => $user_id, 'type' => $type, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now ], [ '%d', '%s', '%s', '%s', '%s' ] );
		$order_id = (int) $wpdb->insert_id;
		$msg      = 'order_saved';
	}

	if ( $order_id > 0 ) {
		$total = save_order_items( $order_id, $products, $quantities );
		$wpdb->update( $orders, [ 'total_price' => $total, 'updated_at' => $now ], [ 'id' => $order_id ], [ '%f', '%s' ], [ '%d' ] );
		clear_admin_badge();
	}

	wp_safe_redirect( Page::url( [ 'section' => 'orders', 'msg' => $msg ] ) );
	exit;
}
add_action( 'admin_post_save_preinvoice', __NAMESPACE__ . '\handle_order_form' );
add_action( 'admin_post_save_prebuy', __NAMESPACE__ . '\handle_order_form' );

/**
 * پیام سیستمی در گفتگوی سفارش (ساخت گفتگو اگر نیست).
 *
 * @param int    $order_id شناسه سفارش.
 * @param int    $user_id  صاحب سفارش.
 * @param string $sender   user|admin|system.
 * @param string $message  متن.
 * @param string $to       خواننده‌ای که پیام برایش «نخوانده» شمرده شود: user|admin (خالی = طرف مقابل فرستنده).
 */
function add_chat_message( int $order_id, int $user_id, string $sender, string $message, string $to = '' ): void {
	global $wpdb;
	$chats = table( 'chats' );
	$now   = now();

	$chat_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE order_id = %d', $chats, $order_id ) );
	if ( ! $chat_id ) {
		$wpdb->insert( $chats, [ 'order_id' => $order_id, 'user_id' => $user_id, 'created_at' => $now ], [ '%d', '%d', '%s' ] );
		$chat_id = (int) $wpdb->insert_id;
	}
	if ( ! $chat_id ) {
		return;
	}

	$wpdb->insert(
		table( 'chat_messages' ),
		[ 'chat_id' => $chat_id, 'sender_id' => 'system' === $sender ? 0 : get_current_user_id(), 'sender_type' => $sender, 'message' => $message, 'created_at' => $now ],
		[ '%d', '%d', '%s', '%s', '%s' ]
	);

	// پیام مشتری برای مدیر نخوانده است و پیام مدیر برای مشتری؛ پیام سیستمی را فراخوان تعیین می‌کند
	$to      = '' !== $to ? $to : ( 'admin' === $sender ? 'user' : 'admin' );
	$counter = 'user' === $to ? 'user_unread' : 'admin_unread';
	$wpdb->query(
		$wpdb->prepare(
			'UPDATE %i SET %i = %i + 1, last_message_preview = %s, last_message_sender = %s, last_message_at = %s WHERE id = %d',
			$chats,
			$counter,
			$counter,
			\Hodima\Panel\preview( $message ),
			$sender,
			$now,
			$chat_id
		)
	);
	clear_admin_badge();
}

add_action( 'hodima_panel_request', static function (): void {
	if ( ! is_user_logged_in() ) {
		return;
	}
	global $wpdb;
	$user_id = get_current_user_id();
	$orders  = table( 'orders' );

	// --- آپلود فیش ---
	if ( isset( $_POST['upload_receipt'], $_POST['order_id'] ) && ! empty( $_FILES['receipt_file']['name'] ) ) {
		$order_id = absint( $_POST['order_id'] );
		check_admin_referer( 'upload_receipt_' . $order_id, '_wpnonce_receipt' );

		$owner = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE id = %d', $orders, $order_id ) );
		if ( $owner !== $user_id ) {
			return;
		}
		if ( Files::count( $order_id, Files::RECEIPT ) >= Files::MAX_PER_KIND ) {
			wp_safe_redirect( Page::url( [ 'section' => 'orders', 'msg' => 'max_receipts' ] ) );
			exit;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$name   = sanitize_file_name( wp_unslash( (string) $_FILES['receipt_file']['name'] ) );
		$upload = wp_handle_upload( $_FILES['receipt_file'], [ 'test_form' => false ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- wp_handle_upload خودش بررسی می‌کند (نوع فایل: مرحله ۲).
		if ( isset( $upload['error'] ) || ! Files::add( $order_id, Files::RECEIPT, $upload, $name ) ) {
			wp_safe_redirect( Page::url( [ 'section' => 'orders', 'msg' => 'upload_error' ] ) );
			exit;
		}
		$wpdb->update( $orders, [ 'status' => 'receipt_uploaded', 'updated_at' => now() ], [ 'id' => $order_id ], [ '%s', '%s' ], [ '%d' ] );
		add_chat_message( $order_id, $user_id, 'system', 'فیش واریزی توسط مشتری آپلود شد. لطفاً بررسی نمایید.' );
		wp_safe_redirect( Page::url( [ 'section' => 'orders', 'msg' => 'receipt_uploaded' ] ) );
		exit;
	}

	// --- حذف سفارش ---
	if ( isset( $_GET['action'], $_GET['order_id'] ) && 'delete_order' === $_GET['action'] ) {
		$order_id = absint( $_GET['order_id'] );
		check_admin_referer( 'delete_order_' . $order_id );

		$owner = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE id = %d', $orders, $order_id ) );
		if ( $owner !== $user_id ) {
			return;
		}
		delete_order( $order_id );
		wp_safe_redirect( Page::url( [ 'section' => 'orders', 'msg' => 'deleted' ] ) );
		exit;
	}
} );

/**
 * حذف کامل یک سفارش: اقلام، فایل‌ها، گفتگو و پیام‌هایش.
 *
 * @param int $order_id شناسه سفارش.
 */
function delete_order( int $order_id ): void {
	global $wpdb;
	$chat_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE order_id = %d', table( 'chats' ), $order_id ) );
	if ( $chat_id ) {
		$wpdb->delete( table( 'chat_messages' ), [ 'chat_id' => $chat_id ], [ '%d' ] );
		$wpdb->delete( table( 'chats' ), [ 'id' => $chat_id ], [ '%d' ] );
	}
	Files::delete_for_order( $order_id );
	$wpdb->delete( table( 'order_items' ), [ 'order_id' => $order_id ], [ '%d' ] );
	$wpdb->delete( table( 'orders' ), [ 'id' => $order_id ], [ '%d' ] );
	clear_admin_badge();
}
