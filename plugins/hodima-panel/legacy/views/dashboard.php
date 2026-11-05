<?php
/**
 * نمای داشبورد مشتری (منتقل‌شده از پنل قدیمی؛ بازطراحی: مرحله ۵).
 *
 * تغییر این مرحله: پنل قدیمی دو جدول ناموجود را می‌خواند (خطای پایگاه داده در هر
 * بازدید، نشان پشتیبانی همیشه ۰) و منطق حذف سفارش را تکراری داشت (هرگز اجرا نمی‌شد).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Page;
use function Hodima\Panel\show_date;
use function Hodima\Panel\status_label;
use function Hodima\Panel\table;
use function Hodima\Panel\type_label;

global $wpdb;
$current_user = wp_get_current_user();
$user_id      = (int) $current_user->ID;

$order_stats = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed FROM %i WHERE user_id = %d",
		table( 'orders' ),
		$user_id
	)
);
$total_orders     = (int) ( $order_stats->total ?? 0 );
$pending_orders   = (int) ( $order_stats->pending ?? 0 );
$completed_orders = (int) ( $order_stats->completed ?? 0 );

$last_order = $wpdb->get_row( $wpdb->prepare( 'SELECT id, type, status, created_at FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT 1', table( 'orders' ), $user_id ) );

$total_unread_messages = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(user_unread) FROM %i WHERE user_id = %d', table( 'tickets' ), $user_id ) );

$display_name = '' !== $current_user->display_name ? $current_user->display_name : $current_user->user_login;
?>

<div class="dashboard-container">

	<div class="welcome-section-wrapper">
		<div class="welcome-text">
			<h2>درود <?php echo esc_html( $display_name ); ?> عزیز!</h2>
			<p>به پنل کاربری خود خوش آمدید.</p>
		</div>
		<div class="welcome-logout">
			<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="btn-logout-dash">خروج از حساب</a>
		</div>
	</div>

	<div class="stats-grid">
		<div class="stat-card blue">
			<span>تعداد کل سفارش‌ها</span>
			<strong><?php echo esc_html( number_format_i18n( $total_orders ) ); ?></strong>
		</div>
		<div class="stat-card orange">
			<span>در انتظار بررسی</span>
			<strong><?php echo esc_html( number_format_i18n( $pending_orders ) ); ?></strong>
		</div>
		<div class="stat-card green">
			<span>تکمیل شده</span>
			<strong><?php echo esc_html( number_format_i18n( $completed_orders ) ); ?></strong>
		</div>
	</div>

	<div class="dashboard-content-grid">

		<div class="card-box">
			<h3>دسترسی سریع</h3>
			<div class="actions-list">
				<a href="<?php echo esc_url( Page::url( [ 'section' => 'preinvoice' ] ) ); ?>" class="btn-action btn-primary">ثبت پیش‌فاکتور</a>
				<a href="<?php echo esc_url( Page::url( [ 'section' => 'prebuy' ] ) ); ?>" class="btn-action btn-secondary">ثبت پیش‌خرید</a>
				<a href="<?php echo esc_url( Page::url( [ 'section' => 'support' ] ) ); ?>" class="btn-action btn-secondary">
					پشتیبانی
					<?php if ( $total_unread_messages > 0 ) : ?>
						<span class="unread-count-badge"><?php echo esc_html( number_format_i18n( $total_unread_messages ) ); ?></span>
					<?php endif; ?>
				</a>
				<a href="<?php echo esc_url( Page::url( [ 'section' => 'profile' ] ) ); ?>" class="btn-action btn-secondary">پروفایل</a>
				<a href="<?php echo esc_url( Page::url( [ 'section' => 'orders' ] ) ); ?>" class="btn-action btn-secondary">مشاهده همه سفارش‌ها</a>
			</div>
		</div>

		<div class="card-box">
			<h3>آخرین وضعیت سفارش</h3>
			<?php if ( $last_order ) : ?>
				<div class="order-meta">
					<p>شماره سفارش: <strong>#<?php echo esc_html( (string) $last_order->id ); ?></strong></p>
					<p>نوع درخواست: <strong><?php echo esc_html( type_label( (string) $last_order->type ) ); ?></strong></p>
					<p>وضعیت فعلی:
						<span class="badge status-<?php echo esc_attr( (string) $last_order->status ); ?>"><?php echo esc_html( status_label( (string) $last_order->status ) ); ?></span>
					</p>
					<p>تاریخ ثبت: <span><?php echo esc_html( show_date( (string) $last_order->created_at, 'Y/m/d' ) ); ?></span></p>
				</div>

				<?php if ( 'pending' === $last_order->status ) : ?>
					<div class="order-actions">
						<a href="<?php echo esc_url( Page::url( [ 'section' => (string) $last_order->type, 'edit_order' => (int) $last_order->id ] ) ); ?>" class="btn-edit">ویرایش</a>
						<a href="<?php echo esc_url( wp_nonce_url( Page::url( [ 'section' => 'orders', 'action' => 'delete_order', 'order_id' => (int) $last_order->id ] ), 'delete_order_' . (int) $last_order->id ) ); ?>" onclick="return confirm('آیا از حذف این سفارش مطمئن هستید؟');" class="btn-delete">حذف سفارش</a>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<p class="no-data">هنوز هیچ سفارشی ثبت نشده است.</p>
			<?php endif; ?>
		</div>

	</div>
</div>
