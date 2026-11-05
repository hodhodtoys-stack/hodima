<?php
/**
 * پیشخوان: منوی پنل، فهرست مشتری‌ها و سفارش‌ها، جزئیات سفارش (منتقل‌شده از پنل قدیمی).
 *
 * ظاهر همان پنل قدیمی است؛ بازطراحی با کیت پیشخوان (قانون ۸): مرحله ۶.
 *
 * تغییر این مرحله:
 * - یک منوی سطح اول «پنل مشتریان» با زیرمنوی «تیکت‌ها» (قبلا دو منوی جدا).
 * - فرم‌ها پیش از چاپ صفحه پردازش و بعد ریدایرکت می‌شوند (load-{صفحه})؛ رفرش
 *   صفحه پیام یا فاکتور را دوباره نمی‌فرستد.
 * - باز کردن یک سفارش فقط گفتگوی همان سفارش را خوانده می‌کند (قبلا همه گفتگوها
 *   و همه تیکت‌های آن مشتری).
 * - جدول اقلام همان قیمت و ظرفیتِ لحظه ثبت را نشان می‌دهد (مبلغ با پنل مشتری یکی).
 * - وضعیت سفارش فقط از فهرست مجاز.
 * - اسکریپت نظرسنجی هر ۱۵ ثانیه حذف شد: عدد منو را با عدد ناقص جایگزین می‌کرد و
 *   هایلایت ردیف‌هایش هیچ‌وقت کار نمی‌کرد.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy\Admin;

use Hodima\Panel\Files;
use function Hodima\Panel\asset_version;
use function Hodima\Panel\clear_admin_badge;
use function Hodima\Panel\now;
use function Hodima\Panel\order_statuses;
use function Hodima\Panel\show_date;
use function Hodima\Panel\status_label;
use function Hodima\Panel\table;

defined( 'ABSPATH' ) || exit;

const ORDERS_PAGE  = 'hodima-panel-orders';
const SUPPORT_PAGE = 'hodima-panel-support';
const CAPABILITY   = 'manage_options';

/** نشان پیام آبی/قرمز (HTML امن). */
function badge( string $text, string $color ): string {
	return '<span class="hp-badge" style="background-color:' . esc_attr( $color ) . ';">' . esc_html( $text ) . '</span>';
}

/**
 * عدد کنار منو: سفارش‌های در انتظار + پیام‌های نخوانده گفتگو و تیکت (۶۰ ثانیه کش؛
 * هر نوشتنی با clear_admin_badge() کش را باطل می‌کند).
 */
function badge_count(): int {
	$cached = get_transient( 'hodima_panel_badge_count' );
	if ( false !== $cached ) {
		return (int) $cached;
	}
	global $wpdb;
	$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %i WHERE status = 'pending'", table( 'orders' ) ) );
	$total += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(admin_unread) FROM %i', table( 'chats' ) ) );
	$total += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(admin_unread) FROM %i', table( 'tickets' ) ) );
	set_transient( 'hodima_panel_badge_count', $total, MINUTE_IN_SECONDS );
	return $total;
}

add_action( 'admin_menu', static function (): void {
	$count = badge_count();
	$title = 'پنل مشتریان' . ( $count > 0 ? sprintf( ' <span class="update-plugins count-%1$d"><span class="pending-count">%2$s</span></span>', $count, esc_html( number_format_i18n( $count ) ) ) : '' );

	$hook = add_menu_page( 'سفارش‌های پنل کاربری', $title, CAPABILITY, ORDERS_PAGE, __NAMESPACE__ . '\render_orders_page', 'dashicons-list-view', 58 );
	add_submenu_page( ORDERS_PAGE, 'سفارش‌های پنل کاربری', 'سفارش‌ها', CAPABILITY, ORDERS_PAGE, __NAMESPACE__ . '\render_orders_page' );
	$support = add_submenu_page( ORDERS_PAGE, 'تیکت‌های پشتیبانی', 'تیکت‌های پشتیبانی', CAPABILITY, SUPPORT_PAGE, __NAMESPACE__ . '\render_support_page' );

	if ( $hook ) {
		add_action( 'load-' . $hook, __NAMESPACE__ . '\handle_order_forms' );
	}
	if ( $support ) {
		add_action( 'load-' . $support, __NAMESPACE__ . '\handle_support_forms' );
	}
} );

add_action( 'admin_enqueue_scripts', static function (): void {
	$screen = get_current_screen();
	if ( $screen && ( str_contains( (string) $screen->id, ORDERS_PAGE ) || str_contains( (string) $screen->id, SUPPORT_PAGE ) ) ) {
		wp_enqueue_style( 'hodima-panel-admin', HODIMA_PANEL_URL . '/assets/css/admin.css', [], asset_version( 'assets/css/admin.css' ) );
	}
} );

/** نام پاسخگو برای امضای پیام‌های مدیر. */
function agent_name(): string {
	$user = wp_get_current_user();
	$name = trim( $user->first_name . ' ' . $user->last_name );
	return '' !== $name ? $name : $user->display_name;
}

/**
 * ریدایرکت به همان صفحه با پیام نتیجه.
 *
 * @param array<string, int|string> $args پارامترهای آدرس.
 */
function back( array $args ): never {
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}

/** پردازش فرم‌های صفحه سفارش‌ها پیش از چاپ صفحه. */
function handle_order_forms(): void {
	if ( ! current_user_can( CAPABILITY ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	global $wpdb;
	$orders   = table( 'orders' );
	$order_id = absint( $_GET['order_id'] ?? 0 );

	// تغییر وضعیت (از فهرست سفارش‌های یک مشتری)
	if ( isset( $_POST['do_update_status'] ) ) {
		check_admin_referer( 'user_panel_admin_action', 'user_panel_nonce' );
		$id       = absint( $_POST['do_update_status'] );
		$statuses = isset( $_POST['status_update'] ) ? (array) wp_unslash( $_POST['status_update'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- فقط کلید مجاز پذیرفته می‌شود.
		$status   = sanitize_key( (string) ( $statuses[ $id ] ?? '' ) );
		if ( $id && isset( order_statuses()[ $status ] ) ) {
			$wpdb->update( $orders, [ 'status' => $status, 'updated_at' => now() ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );
			clear_admin_badge();
		}
		back( [ 'page' => ORDERS_PAGE, 'user_id' => absint( $_GET['user_id'] ?? 0 ), 'hp_msg' => 'status' ] );
	}

	if ( ! $order_id ) {
		return;
	}
	$owner = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE id = %d', $orders, $order_id ) );
	if ( ! $owner ) {
		return;
	}
	$view = [ 'page' => ORDERS_PAGE, 'action' => 'view', 'order_id' => $order_id ];

	// پیام مدیر در گفتگوی سفارش
	if ( isset( $_POST['send_chat_message'] ) ) {
		check_admin_referer( 'send_chat_action', 'chat_nonce' );
		$text = sanitize_textarea_field( wp_unslash( $_POST['chat_message_text'] ?? '' ) );
		if ( '' !== trim( $text ) ) {
			\Hodima\Panel\Orders::add_chat_message( $order_id, $owner, 'admin', $text . "\n\nپاسخگو: " . agent_name() );
		}
		back( $view + [ 'hp_msg' => 'chat' ] );
	}

	// آپلود پیش‌فاکتور
	if ( isset( $_POST['upload_admin_invoice'] ) ) {
		check_admin_referer( 'upload_admin_invoice_nonce', 'invoice_nonce' );
		$file   = isset( $_FILES['admin_invoice_file'] ) && is_array( $_FILES['admin_invoice_file'] ) ? $_FILES['admin_invoice_file'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Files::upload نوع و اندازه و محتوا را می‌سنجد.
		$result = Files::upload( $order_id, Files::INVOICE, $file );
		if ( is_string( $result ) ) {
			back( $view + [ 'hp_msg' => $result ] );
		}
		$wpdb->update( $orders, [ 'status' => 'awaiting_payment', 'updated_at' => now() ], [ 'id' => $order_id ], [ '%s', '%s' ], [ '%d' ] );
		\Hodima\Panel\Orders::add_chat_message(
			$order_id,
			$owner,
			'system',
			'کاربر گرامی، پیش‌فاکتور سفارش شما صادر گردید. لطفاً از قسمت اسناد، فاکتور را دانلود کرده و پس از پرداخت، فیش واریزی را ارسال نمایید.',
			'user'
		);
		back( $view + [ 'hp_msg' => 'invoice' ] );
	}
}

/** پیام نتیجه بعد از ریدایرکت. */
function notice(): void {
	$messages = [
		'status'       => [ 'وضعیت سفارش به‌روزرسانی شد.', 'success' ],
		'chat'         => [ 'پیام شما با موفقیت ارسال شد.', 'success' ],
		'invoice'      => [ 'پیش‌فاکتور با موفقیت آپلود شد و پیام خودکار ارسال گردید.', 'success' ],
		'deleted'      => [ 'سفارش‌های انتخاب‌شده حذف شدند.', 'success' ],
		'reply'        => [ 'پاسخ ارسال شد.', 'success' ],
		'closed'       => [ 'تیکت بسته شد.', 'success' ],
	];
	foreach ( Files::errors() as $code => $text ) {
		$messages[ $code ] = [ $text, 'error' ];
	}
	$key = sanitize_key( wp_unslash( $_GET['hp_msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $messages[ $key ] ) ) {
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $key ][1] ), esc_html( $messages[ $key ][0] ) );
	}
}

/** صفحه سفارش‌ها: فهرست مشتری‌ها ← سفارش‌های یک مشتری ← جزئیات یک سفارش. */
function render_orders_page(): void {
	if ( ! current_user_can( CAPABILITY ) ) {
		wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.' );
	}
	require_once __DIR__ . '/lists.php';

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما.
	$action   = sanitize_key( wp_unslash( $_GET['action'] ?? '' ) );
	$order_id = absint( $_GET['order_id'] ?? 0 );
	$user_id  = absint( $_GET['user_id'] ?? 0 );
	// phpcs:enable

	echo '<div class="wrap hodima-panel-admin">';
	notice();

	if ( 'view' === $action && $order_id > 0 ) {
		render_order_details( $order_id );
	} elseif ( $user_id > 0 ) {
		$user = get_userdata( $user_id );
		echo '<h1 class="wp-heading-inline">سفارش‌های کاربر: ' . esc_html( $user ? $user->display_name : 'کاربر حذف‌شده #' . $user_id ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . ORDERS_PAGE ) ) . '" class="page-title-action">بازگشت به لیست کاربران</a>';
		$table = new Orders_List( $user_id );
		$table->prepare_items();
		echo '<form method="post">';
		wp_nonce_field( 'user_panel_admin_action', 'user_panel_nonce' );
		$table->display();
		echo '</form>';
	} else {
		echo '<h1 class="wp-heading-inline">لیست کاربران دارای سفارش</h1>';
		$table = new Users_List();
		$table->prepare_items();
		$table->display();
	}
	echo '</div>';
}

/**
 * جزئیات یک سفارش: اطلاعات، گفتگو، اقلام، پیش‌فاکتورها.
 *
 * @param int $order_id شناسه سفارش.
 */
function render_order_details( int $order_id ): void {
	global $wpdb;
	$order = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', table( 'orders' ), $order_id ) );
	if ( ! $order ) {
		echo '<h1>سفارش یافت نشد.</h1><a href="' . esc_url( admin_url( 'admin.php?page=' . ORDERS_PAGE ) ) . '" class="page-title-action">بازگشت</a>';
		return;
	}

	$chat_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE order_id = %d', table( 'chats' ), $order_id ) );
	if ( $chat_id ) {
		$wpdb->update( table( 'chats' ), [ 'admin_unread' => 0 ], [ 'id' => $chat_id ], [ '%d' ], [ '%d' ] );
		clear_admin_badge();
	}

	$user      = get_userdata( (int) $order->user_id );
	$user_name = $user ? $user->display_name : 'کاربر حذف‌شده';
	$phone     = (string) ( get_user_meta( (int) $order->user_id, 'mobile', true ) ?: get_user_meta( (int) $order->user_id, 'billing_phone', true ) );
	$back_url  = add_query_arg( [ 'page' => ORDERS_PAGE, 'user_id' => (int) $order->user_id ], admin_url( 'admin.php' ) );
	$self_url  = add_query_arg( [ 'page' => ORDERS_PAGE, 'action' => 'view', 'order_id' => $order_id ], admin_url( 'admin.php' ) );
	$receipts  = Files::for_order( $order_id, Files::RECEIPT );
	$invoices  = Files::for_order( $order_id, Files::INVOICE );

	echo '<h1 class="wp-heading-inline">جزئیات سفارش #' . esc_html( (string) $order->id ) . '</h1>';
	echo '<a href="' . esc_url( $back_url ) . '" class="page-title-action">بازگشت به سفارش‌های ' . esc_html( $user_name ) . '</a>';

	echo '<div id="poststuff"><div class="custom-order-grid">';

	// اطلاعات کلی
	echo '<div class="order-col-30"><div class="postbox h-100"><h2 class="hndle"><span>اطلاعات کلی سفارش</span></h2><div class="inside"><table class="form-table">';
	echo '<tr><th scope="row">نام کاربر:</th><td>' . esc_html( $user_name ) . '</td></tr>';
	echo '<tr><th scope="row">تلفن تماس:</th><td>' . esc_html( '' !== $phone ? $phone : 'ثبت نشده' ) . '</td></tr>';
	if ( $user ) {
		$profile = \Hodima\Panel\Profile::fields( $user );
		echo '<tr><th scope="row">آدرس:</th><td>' . esc_html( '' !== $profile['address'] ? $profile['address'] : 'ثبت نشده' ) . '</td></tr>';
		echo '<tr><th scope="row">کد پستی:</th><td>' . esc_html( '' !== $profile['postcode'] ? $profile['postcode'] : 'ثبت نشده' ) . '</td></tr>';
	}
	echo '<tr><th scope="row">وضعیت سفارش:</th><td><strong>' . esc_html( status_label( (string) $order->status ) ) . '</strong></td></tr>';
	echo '<tr><th scope="row">مبلغ کل برآورد شده:</th><td><strong class="hp-money">' . esc_html( (float) $order->total_price > 0 ? number_format_i18n( (float) $order->total_price ) . ' تومان' : 'محاسبه نشده' ) . '</strong></td></tr>';
	echo '<tr><th scope="row">تاریخ ثبت:</th><td>' . esc_html( show_date( (string) $order->created_at ) ) . '</td></tr>';
	echo '<tr><th scope="row">فیش‌های پرداختی:</th><td>';
	if ( $receipts ) {
		echo '<div class="hp-files">';
		foreach ( $receipts as $i => $file ) {
			echo '<a href="' . esc_url( $file->url ) . '" target="_blank" rel="noopener" class="button button-small">مشاهده فیش ' . esc_html( number_format_i18n( $i + 1 ) ) . '</a>';
		}
		echo '</div>';
	} else {
		echo '<span class="hp-muted">هنوز فیشی ارسال نشده است.</span>';
	}
	echo '</td></tr>';
	if ( ! empty( $order->note ) ) {
		echo '<tr><th scope="row">یادداشت مشتری:</th><td><div class="hp-note">' . nl2br( esc_html( (string) $order->note ) ) . '</div></td></tr>';
	}
	echo '</table></div></div></div>';

	// گفتگو
	$messages = $chat_id ? (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE chat_id = %d ORDER BY id ASC', table( 'chat_messages' ), $chat_id ) ) : [];
	echo '<div class="order-col-70"><div id="chat-admin-metabox" class="postbox h-100"><h2 class="hndle"><span>گفتگو و پشتیبانی سفارش</span></h2><div class="inside hp-chat">';
	echo '<div id="admin-chat-history" class="hp-chat-history">';
	if ( $messages ) {
		foreach ( $messages as $msg ) {
			$who = match ( $msg->sender_type ) {
				'system' => 'سیستم (خودکار)',
				'admin'  => 'پشتیبانی',
				default  => $user_name,
			};
			echo '<div class="hp-msg hp-msg--' . esc_attr( (string) $msg->sender_type ) . '"><strong>' . esc_html( $who ) . ':</strong><br>' . nl2br( esc_html( (string) $msg->message ) ) . '<div class="hp-msg-date">' . esc_html( show_date( (string) $msg->created_at ) ) . '</div></div>';
		}
	} else {
		echo '<p class="hp-empty">پیامی وجود ندارد. شما می‌توانید گفتگو را شروع کنید.</p>';
	}
	echo '</div><script>document.addEventListener("DOMContentLoaded",function(){var c=document.getElementById("admin-chat-history");if(c){c.scrollTop=c.scrollHeight;}});</script>';
	echo '<form method="post" action="' . esc_url( $self_url ) . '" class="hp-chat-form">';
	wp_nonce_field( 'send_chat_action', 'chat_nonce' );
	echo '<div class="agent-select-wrapper">شما با نام «' . esc_html( agent_name() ) . '» در حال پاسخگویی هستید.</div>';
	echo '<label for="chat_message_text" class="screen-reader-text">پیام به مشتری</label><textarea id="chat_message_text" name="chat_message_text" rows="2" placeholder="پیام خود را به مشتری بنویسید..." required></textarea>';
	echo '<button type="submit" name="send_chat_message" value="1" class="button button-primary">ارسال پیام</button></form></div></div></div>';

	// اقلام (همان قیمت و ظرفیتِ لحظه ثبت)
	$items = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d ORDER BY id ASC', table( 'order_items' ), $order_id ) );
	echo '<div class="order-col-70"><div class="postbox h-100"><h2 class="hndle"><span>آیتم‌های درخواست شده</span></h2><div class="inside">';
	if ( $items ) {
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th scope="col">نام محصول</th><th scope="col">تعداد درخواستی</th><th scope="col">قیمت هر عدد</th><th scope="col">جمع آیتم</th></tr></thead><tbody>';
		$grand = 0.0;
		foreach ( $items as $item ) {
			$grand += (float) $item->line_total;
			$qty    = rtrim( rtrim( number_format( (float) $item->quantity, 3, '.', '' ), '0' ), '.' );
			$detail = (int) $item->unit_capacity > 1 ? ' × ' . number_format_i18n( (int) $item->unit_capacity ) . ' عدد' : '';
			echo '<tr><td><strong>' . esc_html( get_the_title( (int) $item->product_id ) ?: 'محصول #' . (int) $item->product_id ) . '</strong></td>';
			echo '<td><strong>' . esc_html( $qty ) . '</strong> ' . esc_html( (string) $item->sale_unit . $detail ) . '</td>';
			echo '<td>' . esc_html( (float) $item->unit_price > 0 ? number_format_i18n( (float) $item->unit_price ) . ' تومان' : 'نامشخص' ) . '</td>';
			echo '<td><strong>' . esc_html( (float) $item->line_total > 0 ? number_format_i18n( (float) $item->line_total ) . ' تومان' : '-' ) . '</strong></td></tr>';
		}
		echo '<tr class="hp-total-row"><td colspan="3">جمع کل مبالغ جدول:</td><td><strong class="hp-money">' . esc_html( $grand > 0 ? number_format_i18n( $grand ) . ' تومان' : 'نامشخص' ) . '</strong></td></tr>';
		echo '</tbody></table>';
	} else {
		echo '<p>آیتمی یافت نشد.</p>';
	}
	echo '</div></div></div>';

	// پیش‌فاکتورها
	echo '<div class="order-col-30"><div class="postbox h-100"><h2 class="hndle"><span>آپلود پیش‌فاکتور (ادمین)</span></h2><div class="inside">';
	if ( $invoices ) {
		echo '<div class="hp-invoices">';
		foreach ( $invoices as $i => $file ) {
			echo '<div class="hp-invoice"><span>پیش‌فاکتور ' . esc_html( number_format_i18n( $i + 1 ) ) . '</span><a href="' . esc_url( $file->url ) . '" target="_blank" rel="noopener" class="button button-small">مشاهده / دانلود</a></div>';
		}
		echo '</div><hr>';
	}
	if ( count( $invoices ) < Files::MAX_PER_KIND ) {
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $self_url ) . '">';
		wp_nonce_field( 'upload_admin_invoice_nonce', 'invoice_nonce' );
		echo '<p><label for="admin_invoice_file" class="screen-reader-text">فایل پیش‌فاکتور</label><input type="file" id="admin_invoice_file" name="admin_invoice_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required></p>';
		echo '<p><button type="submit" name="upload_admin_invoice" value="1" class="button button-primary hp-wide">آپلود پیش‌فاکتور جدید</button></p><p class="description">فرمت‌های مجاز: PDF، JPG، PNG، WEBP تا ۵ مگابایت (حداکثر ۹ فایل)</p></form>';
	} else {
		echo '<p class="hp-warn">شما به سقف مجاز (۹ پیش‌فاکتور) رسیده‌اید.</p>';
	}
	echo '</div></div></div>';

	echo '</div></div>';
}
