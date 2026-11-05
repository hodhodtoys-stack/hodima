<?php
/**
 * اجزای مشترک نماهای پنل مشتری: آیکون، نشان وضعیت، نوار مرحله، مبلغ.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * اجزای نما (طرح تأییدشده کاربر، مرحله ۵).
 *
 * هر چیزی که در چند نما تکرار می‌شود فقط اینجاست تا ظاهر یکی بماند.
 */
final class View {

	/** آیکون‌های خطی ۲۴×۲۴ (stroke) — بدون فونت و بدون درخواست شبکه. */
	private const ICONS = [
		'home'    => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
		'plus'    => '<path d="M12 5v14M5 12h14"/>',
		'doc'     => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>',
		'list'    => '<path d="M3 7h18M3 12h18M3 17h12"/>',
		'chat'    => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
		'logout'  => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
		'alert'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
		'check'   => '<path d="M5 12l4 4 10-10"/>',
		'down'    => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
		'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
		'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
	];

	/**
	 * آیکون SVG تزئینی.
	 *
	 * @param string $name نام آیکون.
	 * @param int    $size اندازه px.
	 */
	public static function icon( string $name, int $size = 20 ): string {
		$path = self::ICONS[ $name ] ?? '';
		return '' === $path ? '' : sprintf(
			'<svg class="hpn-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			$size,
			$path
		);
	}

	/**
	 * مبلغ به تومان.
	 *
	 * @param float $amount مبلغ.
	 */
	public static function money( float $amount ): string {
		return number_format_i18n( $amount ) . ' تومان';
	}

	/**
	 * تعداد با ارقام سایت و بی صفر اضافه (۲٫۵، ۳).
	 *
	 * @param float $qty تعداد.
	 */
	public static function qty( float $qty ): string {
		$plain    = Orders::format_qty( $qty );
		$decimals = str_contains( $plain, '.' ) ? strlen( substr( $plain, strpos( $plain, '.' ) + 1 ) ) : 0;
		return number_format_i18n( $qty, $decimals );
	}

	/**
	 * گروه وضعیت برای فیلتر و رنگ: wait (نوبت مشتری)، review (نوبت پشتیبانی)، done، cancel.
	 *
	 * @param string $status وضعیت سفارش.
	 */
	public static function group( string $status ): string {
		return match ( $status ) {
			'awaiting_payment'       => 'wait',
			'pending', 'receipt_uploaded' => 'review',
			'completed'              => 'done',
			default                  => 'cancel',
		};
	}

	/**
	 * برچسب کوتاه وضعیت از دید مشتری.
	 *
	 * @param string $status وضعیت.
	 */
	public static function status_text( string $status ): string {
		return match ( $status ) {
			'pending'          => 'در حال بررسی',
			'awaiting_payment' => 'منتظر فیش شما',
			'receipt_uploaded' => 'بررسی فیش',
			'completed'        => 'تکمیل‌شده',
			'cancelled'        => 'لغو شده',
			default            => status_label( $status ),
		};
	}

	/**
	 * نشان وضعیت.
	 *
	 * @param string $status وضعیت.
	 */
	public static function pill( string $status ): string {
		return '<span class="hpn-pill hpn-pill--' . esc_attr( self::group( $status ) ) . '">' . esc_html( self::status_text( $status ) ) . '</span>';
	}

	/**
	 * نوار چهار مرحله سفارش و جمله مرحله فعلی.
	 *
	 * @param string $status وضعیت.
	 */
	public static function steps( string $status ): string {
		if ( 'cancelled' === $status ) {
			return '';
		}
		$labels  = [ 'ثبت شد', 'بررسی و پیش‌فاکتور', 'پرداخت و فیش', 'تکمیل و ارسال' ];
		$current = match ( $status ) {
			'pending'          => 1,
			'awaiting_payment' => 2,
			'receipt_uploaded' => 2,
			default            => 4,
		};
		$now = match ( $status ) {
			'pending'          => 'در حال بررسی پشتیبانی',
			'awaiting_payment' => 'پرداخت و ارسال فیش — نوبت شما',
			'receipt_uploaded' => 'فیش رسید؛ پشتیبانی بررسی می‌کند',
			default            => 'تکمیل شد',
		};
		$out = '<ol class="hpn-steps" aria-label="مرحله سفارش">';
		foreach ( $labels as $i => $label ) {
			$state = $i < $current ? 'done' : ( $i === $current ? self::group( $status ) : 'todo' );
			$out  .= sprintf(
				'<li class="hpn-steps__item hpn-steps__item--%1$s"%2$s><span class="hpn-steps__bar"></span><span class="hpn-steps__label">%3$s</span></li>',
				esc_attr( $state ),
				$i === $current ? ' aria-current="step"' : '',
				esc_html( $i === $current ? $now : $label )
			);
		}
		$out .= '</ol>';
		if ( $current < 4 ) {
			$out .= '<p class="hpn-steps__now hpn-steps__now--' . esc_attr( self::group( $status ) ) . '">مرحله ' . esc_html( number_format_i18n( $current + 1 ) ) . ' از ۴: ' . esc_html( $now ) . '</p>';
		}
		return $out;
	}

	/**
	 * عدد کوچک (نشان) با متن برای صفحه‌خوان.
	 *
	 * @param int    $count عدد.
	 * @param string $label متن صفحه‌خوان.
	 */
	public static function badge( int $count, string $label ): string {
		return $count > 0 ? '<span class="hpn-badge"><span aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span><span class="screen-reader-text">' . esc_html( number_format_i18n( $count ) . ' ' . $label ) . '</span></span>' : '';
	}
}
