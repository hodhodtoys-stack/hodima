<?php
/**
 * مسیریاب پنل: کدام بخش نمایش داده شود، منو و پیام‌ها (منتقل‌شده از پنل قدیمی).
 *
 * تغییر این مرحله:
 * - قفل «تکمیل پروفایل» پیش از چاپ صفحه ریدایرکت می‌کند (قبلا بعد از سربرگ).
 * - بخش «order_chat» حذف شد: فایلش در پنل قدیمی وجود نداشت و فقط پیام خطا
 *   نشان می‌داد؛ گفتگوی هر سفارش در پنجره همان صفحه سفارش‌هاست.
 * - پردازش دوم و تکراری فرم پروفایل (غیر AJAX) حذف شد.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy;

use Hodima\Panel\Page;
use function Hodima\Panel\table;

defined( 'ABSPATH' ) || exit;

add_action( 'hodima_panel_request', static function ( string $section ): void {
	if ( ! is_user_logged_in() ) {
		return;
	}
	// مرحله ۳: قفل پروفایل برای مشتری‌های قدیمی و مدیر بازبینی می‌شود (گزارش، مورد ۲۲)
	if ( 'profile' !== $section && '1' !== get_user_meta( get_current_user_id(), 'up_profile_completed', true ) ) {
		wp_safe_redirect( Page::url( [ 'section' => 'profile', 'msg' => 'profile_locked' ] ) );
		exit;
	}
} );

/** محتوای کامل پنل (فراخوانی از templates/panel.php). */
function render_panel(): void {
	$views = HODIMA_PANEL_DIR . '/legacy/views/';

	if ( ! is_user_logged_in() ) {
		include $views . 'login.php';
		return;
	}

	$section = Page::section();

	render_menu( $section );

	echo '<div class="up-content-area">';
	render_messages();
	include $views . match ( $section ) {
		'prebuy', 'preinvoice' => 'order-form.php',
		default                => $section . '.php',
	};
	echo '</div>';
}

/** پیام نتیجه کارها (پارامتر msg یا error آدرس) به صورت Toast. */
function render_messages(): void {
	$messages = [
		'profile_locked'   => [ 'برای دسترسی به سایر بخش‌های پنل، ابتدا باید اطلاعات پروفایل خود را تکمیل کنید.', 'up-alert-error' ],
		'order_saved'      => [ 'سفارش شما با موفقیت ثبت شد و در لیست سفارشات قرار گرفت.', 'up-alert-success' ],
		'order_updated'    => [ 'سفارش با موفقیت ویرایش شد.', 'up-alert-success' ],
		'ticket_sent'      => [ 'پیام شما با موفقیت ارسال شد.', 'up-alert-success' ],
		'receipt_uploaded' => [ 'فیش با موفقیت آپلود شد.', 'up-alert-success' ],
		'max_receipts'     => [ 'خطا: شما حداکثر تعداد مجاز فیش (۹ عدد) را آپلود کرده‌اید.', 'up-alert-error' ],
		'deleted'          => [ 'مورد با موفقیت حذف شد.', 'up-alert-success' ],
		'upload_error'     => [ 'خطا در آپلود فایل رخ داده است.', 'up-alert-error' ],
		'empty'            => [ 'خطا: شما هیچ محصولی را برای ثبت انتخاب نکرده‌اید.', 'up-alert-error' ],
		'ticket_error'     => [ 'خطایی در ثبت پیام رخ داد. لطفا دوباره تلاش کنید.', 'up-alert-error' ],
	];
	$key = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	[ $text, $class ] = $messages[ $key ] ?? [ '', '' ];
	?>
	<div id="up-toast-container" aria-live="polite"></div>
	<script>
	window.showToastMessage = function ( text, typeClass ) {
		const container = document.getElementById( 'up-toast-container' );
		if ( ! container ) {
			return;
		}
		const toast = document.createElement( 'div' );
		toast.className = 'up-toast-msg ' + typeClass;
		toast.textContent = text;
		container.appendChild( toast );
		requestAnimationFrame( () => toast.classList.add( 'show-popup' ) );
		setTimeout( () => {
			toast.classList.remove( 'show-popup' );
			setTimeout( () => toast.remove(), 400 );
		}, 6000 );
	};
	document.addEventListener( 'DOMContentLoaded', function () {
		<?php if ( '' !== $text ) : ?>
		window.showToastMessage( <?php echo wp_json_encode( $text ); ?>, <?php echo wp_json_encode( $class ); ?> );
		<?php endif; ?>
		const url = new URL( window.location.href );
		if ( url.searchParams.has( 'msg' ) ) {
			url.searchParams.delete( 'msg' );
			window.history.replaceState( null, '', url.toString() );
		}
	} );
	</script>
	<?php
}

/**
 * منوی پنل با نشان پیام‌های خوانده‌نشده.
 *
 * @param string $current بخش فعلی.
 */
function render_menu( string $current ): void {
	global $wpdb;
	$user_id = get_current_user_id();

	$support_unread = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(user_unread) FROM %i WHERE user_id = %d', table( 'tickets' ), $user_id ) );
	$orders_unread  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(user_unread) FROM %i WHERE user_id = %d', table( 'chats' ), $user_id ) );

	$items = [
		'dashboard'  => 'داشبورد',
		'prebuy'     => 'پیش خرید',
		'preinvoice' => 'پیش فاکتور',
		'orders'     => 'سفارش‌ها',
		'support'    => 'ارسال پیام',
		'profile'    => 'پروفایل',
	];

	echo '<nav class="up-menu-nav" aria-label="منوی پنل کاربری">';
	foreach ( $items as $slug => $label ) {
		$active = $current === $slug;
		echo '<a href="' . esc_url( Page::url( [ 'section' => $slug ] ) ) . '" class="up-menu-item' . ( $active ? ' active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>';
		echo esc_html( $label );
		$count = match ( $slug ) {
			'support' => $support_unread,
			'orders'  => $orders_unread,
			default   => 0,
		};
		if ( $count > 0 ) {
			echo '<span class="menu-unread-badge ' . esc_attr( $slug ) . '-badge">' . esc_html( number_format_i18n( $count ) ) . '</span>';
		}
		echo '</a>';
	}
	echo '<a href="' . esc_url( wp_logout_url( Page::url() ) ) . '" class="up-logout-link">خروج</a>';
	echo '</nav>';
}
