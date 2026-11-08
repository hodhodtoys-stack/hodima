<?php
/**
 * قاب پنل مشتری: منوی کناری (دسکتاپ)، نوار پایین (موبایل)، پیام نتیجه، انتخاب نما.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * قاب پنل (طرح تأییدشده کاربر، مرحله ۵؛ جای legacy/router.php).
 *
 * پنل قدیمی منوی ردیفی داشت که در موبایل برچسب‌هایش بریده می‌شد و پیام‌ها با
 * اسکریپت «Toast» می‌آمدند (بدون JS دیده نمی‌شدند و صفحه‌خوان نمی‌خواند). حالا
 * پیام با سرور در ناحیه role=status چاپ می‌شود.
 */
final class Front {

	/** نماها: بخش => فایل در templates/views. */
	private const VIEWS = [
		'dashboard'  => 'dashboard',
		'prebuy'     => 'order-form',
		'preinvoice' => 'order-form',
		'orders'     => 'orders',
		'support'    => 'support',
		'profile'    => 'profile',
	];

	/** محتوای کامل پنل (templates/panel.php). */
	public static function render(): void {
		if ( ! is_user_logged_in() ) {
			echo '<div class="hpn hpn--guest">';
			self::message(); // نتیجه لینک تأیید ایمیل وقتی وارد نیست
			self::view( 'login' );
			echo '</div>';
			return;
		}
		$section = Page::section();
		$counts  = self::unread();
		echo '<div class="hpn">';
		self::nav( $section, $counts );
		echo '<div class="hpn-main" id="hpn-main">';
		self::message();
		self::view( self::VIEWS[ $section ] ?? 'dashboard', [ 'section' => $section, 'counts' => $counts ] );
		echo '</div>';
		self::bottom_nav( $section, $counts );
		echo '</div>';
	}

	/**
	 * یک نما با متغیرهای داده‌شده.
	 *
	 * @param string               $name نام فایل.
	 * @param array<string, mixed> $vars متغیرها.
	 */
	private static function view( string $name, array $vars = [] ): void {
		$file = HODIMA_PANEL_DIR . '/templates/views/' . $name . '.php';
		if ( is_file( $file ) ) {
			( static function ( string $hpn_file, array $hpn_vars ): void {
				extract( $hpn_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- متغیرهای ثابت از همین کلاس.
				include $hpn_file;
			} )( $file, $vars );
		}
	}

	/**
	 * پیام‌های خوانده‌نشده مشتری.
	 *
	 * @return array{orders: int, support: int}
	 */
	public static function unread(): array {
		global $wpdb;
		$user_id = get_current_user_id();
		return [
			'orders'  => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(user_unread) FROM %i WHERE user_id = %d', table( 'chats' ), $user_id ) ),
			'support' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(user_unread) FROM %i WHERE user_id = %d', table( 'tickets' ), $user_id ) ),
		];
	}

	/**
	 * گزینه‌های منو.
	 *
	 * @param array{orders: int, support: int} $counts نخوانده‌ها.
	 * @return array<string, array{label: string, short: string, icon: string, count: int}>
	 */
	private static function items( array $counts ): array {
		return [
			'dashboard'  => [ 'label' => 'داشبورد', 'short' => 'خانه', 'icon' => 'home', 'count' => 0 ],
			'prebuy'     => [ 'label' => 'ثبت پیش‌خرید', 'short' => 'سفارش تازه', 'icon' => 'plus', 'count' => 0 ],
			'preinvoice' => [ 'label' => 'درخواست پیش‌فاکتور', 'short' => '', 'icon' => 'doc', 'count' => 0 ],
			'orders'     => [ 'label' => 'سفارش‌ها', 'short' => 'سفارش‌ها', 'icon' => 'list', 'count' => $counts['orders'] ],
			'support'    => [ 'label' => 'پیام‌ها و پشتیبانی', 'short' => 'پیام‌ها', 'icon' => 'chat', 'count' => $counts['support'] ],
			'profile'    => [ 'label' => 'پروفایل', 'short' => 'پروفایل', 'icon' => 'user', 'count' => 0 ],
		];
	}

	/**
	 * منوی کناری (دسکتاپ؛ در موبایل پنهان و نوار پایین جایش).
	 *
	 * @param string                           $current بخش فعلی.
	 * @param array{orders: int, support: int} $counts  نخوانده‌ها.
	 */
	private static function nav( string $current, array $counts ): void {
		$user    = wp_get_current_user();
		$fields  = Profile::fields( $user );
		$name    = '' !== $fields['name'] ? $fields['name'] : 'مشتری عزیز';
		$initial = mb_substr( $name, 0, 1 );
		echo '<nav class="hpn-side" aria-label="منوی پنل کاربری">';
		echo '<div class="hpn-side__user"><span class="hpn-avatar" aria-hidden="true">' . esc_html( $initial ) . '</span><span class="hpn-side__who"><strong>' . esc_html( $name ) . '</strong>';
		if ( '' !== $fields['mobile'] ) {
			echo '<bdi dir="ltr" class="hpn-muted">' . esc_html( $fields['mobile'] ) . '</bdi>';
		}
		echo '</span></div><ul class="hpn-side__list">';
		foreach ( self::items( $counts ) as $slug => $item ) {
			$active = $current === $slug;
			printf(
				'<li><a class="hpn-side__link%1$s" href="%2$s"%3$s>%4$s<span>%5$s</span>%6$s</a></li>',
				$active ? ' is-active' : '',
				esc_url( Page::url( [ 'section' => $slug ] ) ),
				$active ? ' aria-current="page"' : '',
				View::icon( $item['icon'], 18 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت.
				esc_html( $item['label'] ),
				View::badge( $item['count'], 'پیام تازه' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن.
			);
		}
		printf(
			'<li class="hpn-side__out"><a class="hpn-side__link" href="%1$s">%2$s<span>خروج</span></a></li>',
			esc_url( wp_logout_url( Page::url() ) ),
			View::icon( 'logout', 18 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت.
		);
		echo '</ul></nav>';
	}

	/**
	 * نوار پایین موبایل (پنج گزینه پرکاربرد).
	 *
	 * @param string                           $current بخش فعلی.
	 * @param array{orders: int, support: int} $counts  نخوانده‌ها.
	 */
	private static function bottom_nav( string $current, array $counts ): void {
		echo '<nav class="hpn-bottom" aria-label="منوی سریع پنل"><ul>';
		foreach ( self::items( $counts ) as $slug => $item ) {
			if ( '' === $item['short'] ) {
				continue;
			}
			$active = $current === $slug || ( 'prebuy' === $slug && 'preinvoice' === $current );
			printf(
				'<li><a class="hpn-bottom__link%1$s" href="%2$s"%3$s>%4$s<span>%5$s</span>%6$s</a></li>',
				$active ? ' is-active' : '',
				esc_url( Page::url( [ 'section' => $slug ] ) ),
				$active ? ' aria-current="page"' : '',
				View::icon( $item['icon'], 22 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت.
				esc_html( $item['short'] ),
				View::badge( $item['count'], 'پیام تازه' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن.
			);
		}
		echo '</ul></nav>';
	}

	/**
	 * متن پیام‌های نتیجه (کلید msg آدرس).
	 *
	 * @return array<string, array{0: string, 1: string}> کلید => [متن، نوع ok|error].
	 */
	public static function messages(): array {
		$out = [
			'profile_locked'      => [ 'برای استفاده از بقیه بخش‌ها، اول اطلاعات تماس و آدرس را کامل کنید.', 'error' ],
			'welcome'             => [ 'حساب شما ساخته شد. لطفا نام و آدرس را کامل کنید.', 'ok' ],
			'order_saved'         => [ 'سفارش ثبت شد؛ پشتیبانی بررسی می‌کند و پیش‌فاکتور را می‌فرستد.', 'ok' ],
			'order_updated'       => [ 'سفارش ویرایش شد.', 'ok' ],
			'ticket_sent'         => [ 'پیام شما ارسال شد.', 'ok' ],
			'receipt_uploaded'    => [ 'فیش ارسال شد؛ پشتیبانی بررسی می‌کند.', 'ok' ],
			'deleted'             => [ 'سفارش حذف شد.', 'ok' ],
			'empty'               => [ 'هیچ محصولی انتخاب نشده است.', 'error' ],
			'ticket_error'        => [ 'ارسال پیام ناموفق بود؛ دوباره تلاش کنید.', 'error' ],
			'message_empty'       => [ 'متن پیام را بنویسید.', 'error' ],
			'ticket_closed'       => [ 'این گفتگو بسته شده است؛ برای سؤال تازه «پیام تازه به پشتیبانی» را بزنید.', 'error' ],
			'profile_saved'       => [ 'اطلاعات با موفقیت ذخیره شد.', 'ok' ],
			'email_confirmed'     => [ 'ایمیل حساب تأیید و عوض شد.', 'ok' ],
			'email_link_invalid'  => [ 'لینک تأیید ایمیل نامعتبر یا منقضی است؛ در پروفایل «ارسال دوباره لینک» را بزنید.', 'error' ],
			'email_taken'         => [ 'این ایمیل در این فاصله برای حساب دیگری ثبت شد؛ ایمیل دیگری وارد کنید.', 'error' ],
			'profile_error'       => [ 'اطلاعات ذخیره نشد؛ پیام پایین فرم را ببینید.', 'error' ],
			'order_message_sent'  => [ 'پیام در گفتگوی سفارش ثبت شد؛ پاسخ پشتیبانی را در همین فهرست و روی کارت همان سفارش می‌بینید.', 'ok' ],
			'locked'              => [ 'این سفارش در حال بررسی است و دیگر ویرایش یا حذف نمی‌شود؛ در گفتگوی همان سفارش پیام بدهید.', 'error' ],
			'invalid_items'       => [ 'بعضی اقلام با حداقل سفارش یا تعداد مجاز نمی‌خواند؛ موارد مشخص‌شده را درست کنید.', 'error' ],
			'receipt_not_allowed' => [ 'برای این سفارش در وضعیت فعلی نمی‌شود فیش فرستاد.', 'error' ],
			'error_owner'         => [ 'سفارش پیدا نشد.', 'error' ],
			'db_error'            => [ 'ذخیره سفارش ناموفق بود؛ دوباره تلاش کنید.', 'error' ],
		];
		foreach ( Files::errors() as $code => $text ) {
			$out[ $code ] = [ $text, 'error' ];
		}
		foreach ( Profile::email_messages() as $status => $text ) {
			if ( 'same' !== $status ) {
				$out[ 'profile_email_' . $status ] = [ $text, in_array( $status, [ 'sent', 'pending' ], true ) ? 'ok' : 'error' ];
			}
		}
		return $out;
	}

	/** پیام نتیجه آخرین کار. */
	private static function message(): void {
		$key = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط پیام.
		$all = self::messages();
		if ( ! isset( $all[ $key ] ) ) {
			return;
		}
		[ $text, $type ] = $all[ $key ];
		printf(
			'<div class="hpn-notice hpn-notice--%1$s" role="%2$s">%3$s<span>%4$s</span></div>',
			esc_attr( $type ),
			'error' === $type ? 'alert' : 'status',
			View::icon( 'error' === $type ? 'alert' : 'check', 20 ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت.
			esc_html( $text )
		);
	}
}
