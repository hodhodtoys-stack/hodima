<?php
/**
 * خبر «پاسخ تازه پشتیبانی» به مشتری با پیامک و ایمیل.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * اطلاع‌رسانی پاسخ پشتیبانی (از 0.2.8، خواست کاربر: پیامک و ایمیل، فقط برای «پاسخ پشتیبانی»).
 *
 * تا 0.2.7 مشتری فقط با سر زدن به پنل از پاسخ باخبر می‌شد. حالا هر پاسخ مدیر در گفتگوی
 * سفارش یا تیکت:
 * - پیامک با الگوی «خبر پاسخ پشتیبانی» سرویس پیامک (Sms::send_notice؛ بی الگو = بی پیامک) به موبایل حساب؛
 * - ایمیل فقط به ایمیل حساب اگر واقعی است (ایمیل ساختگی ثبت‌نام پیامکی صندوق ندارد؛ ایمیل حساب
 *   از 0.2.6 فقط با لینک تأیید عوض می‌شود، پس تأییدشده است)؛
 * - برای هر گفتگو حداکثر یک خبر در THROTTLE (چند پیام پشت‌سرهم مدیر = یک پیامک).
 * متن پیام در پیامک/ایمیل نمی‌آید؛ فقط شماره سفارش یا کد پیگیری و لینک پنل.
 */
final class Notify {

	/** کمترین فاصله دو خبر برای یک گفتگو. */
	public const THROTTLE = 30 * MINUTE_IN_SECONDS;

	/**
	 * خبر پاسخ پشتیبانی.
	 *
	 * @param int    $user_id مشتری.
	 * @param string $kind    order|ticket.
	 * @param int    $id      شناسه سفارش یا تیکت.
	 * @param string $ref     مرجع نمایشی: شماره سفارش یا کد پیگیری تیکت (تنها متغیر الگوی پیامک).
	 * @return array{sms: string, email: string} وضعیت هر راه: sent|failed|skipped|throttled
	 */
	public static function support_reply( int $user_id, string $kind, int $id, string $ref ): array {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return [ 'sms' => 'skipped', 'email' => 'skipped' ];
		}
		$key = 'hodima_panel_notify_' . $kind . '_' . $id;
		if ( false !== get_transient( $key ) ) {
			return [ 'sms' => 'throttled', 'email' => 'throttled' ];
		}
		set_transient( $key, 1, self::THROTTLE );

		$out    = [ 'sms' => 'skipped', 'email' => 'skipped' ];
		$mobile = Profile::mobile( $user );
		if ( '' !== $mobile ) {
			$result     = Sms::send_notice( $mobile, $ref );
			$out['sms'] = $result['ok'] ? 'sent' : ( '' === $result['provider'] ? 'skipped' : 'failed' );
		}
		$email = (string) $user->user_email;
		if ( is_email( $email ) && ! Profile::is_placeholder_email( $email ) ) {
			$site  = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
			$link  = 'order' === $kind
				? Page::url( [ 'section' => 'orders', 'chat' => $id ] )
				: Page::url( [ 'section' => 'support', 'thread_id' => $id ] );
			$what  = 'order' === $kind ? 'سفارش #' . $ref : 'تیکت ' . $ref;
			$body  = "سلام،\n\nپشتیبانی {$site} به پیام شما درباره {$what} پاسخ داد. برای دیدن پاسخ وارد پنل شوید:\n{$link}\n";
			$sent  = wp_mail( $email, 'پاسخ تازه پشتیبانی — ' . $site, $body );
			$out['email'] = $sent ? 'sent' : 'failed';
		}
		return $out;
	}
}
