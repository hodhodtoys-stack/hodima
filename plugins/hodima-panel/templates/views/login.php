<?php
/**
 * ورود یا ثبت‌نام (طرح تأییدشده، مرحله ۵). پردازش: Auth::handle (نام فیلدها همان قبلی).
 *
 * بدون JS هر دو فرم پشت‌سرهم دیده می‌شوند؛ JS تب‌ها، شمارش معکوس و دکمه نمایش رمز را اضافه می‌کند.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Auth;
use Hodima\Panel\Sms;
use Hodima\Panel\View;

$hodima_auth   = Auth::instance();
$hodima_sms    = Sms::ready();
$hodima_verify = 'otp_verify' === $hodima_auth->view && '' !== $hodima_auth->mobile;
$hodima_tab    = ( ! $hodima_sms || ( 'login' === $hodima_auth->view && isset( $_POST['login_with_password'] ) ) ) ? 'password' : 'sms'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- فقط انتخاب تب؛ Auth::handle nonce را بررسی کرده.
$hodima_trap   = '<input type="text" name="' . esc_attr( Auth::TRAP ) . '" class="hpn-trap" tabindex="-1" autocomplete="off" aria-hidden="true" value="">';
?>
<div class="hpn-auth">
	<aside class="hpn-auth__brand">
		<strong class="hpn-auth__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
		<ul class="hpn-auth__points">
			<li>ثبت پیش‌خرید و پیش‌فاکتور با قیمت روز</li>
			<li>پیگیری مرحله به مرحله سفارش، فاکتور و فیش</li>
			<li>گفتگو با پشتیبانی برای هر سفارش</li>
		</ul>
	</aside>

	<div class="hpn-auth__body">
		<?php if ( '' !== $hodima_auth->error ) : ?>
			<div class="hpn-notice hpn-notice--error" role="alert"><?php echo View::icon( 'alert', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?><span><?php echo esc_html( $hodima_auth->error ); ?></span></div>
		<?php elseif ( '' !== $hodima_auth->success ) : ?>
			<div class="hpn-notice hpn-notice--ok" role="status"><?php echo View::icon( 'check', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?><span><?php echo esc_html( $hodima_auth->success ); ?></span></div>
		<?php endif; ?>

		<?php if ( $hodima_verify ) : ?>
			<h1 class="hpn-title">کد تأیید را وارد کنید</h1>
			<p class="hpn-lead">کد ۵ رقمی به <bdi dir="ltr"><?php echo esc_html( $hodima_auth->mobile ); ?></bdi> پیامک شد.</p>
			<form class="hpn-auth__form" method="post">
				<?php wp_nonce_field( Auth::NONCE, 'hodima_auth_nonce', false ); ?>
				<?php echo $hodima_trap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ساخته‌شده بالا با esc_attr. ?>
				<label class="hpn-label" for="hpn-otp">کد ۵ رقمی</label>
				<input class="hpn-input hpn-otp" id="hpn-otp" name="otp_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9۰-۹]{5}" maxlength="5" dir="ltr" required autofocus>
				<button class="hpn-btn hpn-btn--lg hpn-btn--block" type="submit" name="verify_otp" value="1">تأیید و ورود</button>
			</form>
			<div class="hpn-auth__row">
				<p class="hpn-muted" data-hpn-timer="<?php echo esc_attr( (string) $hodima_auth->wait ); ?>"<?php echo $hodima_auth->wait > 0 ? '' : ' hidden'; ?>>ارسال دوباره تا <span data-hpn-timer-text><?php echo esc_html( gmdate( 'i:s', $hodima_auth->wait ) ); ?></span></p>
				<form method="post" data-hpn-resend<?php echo $hodima_auth->wait > 0 ? ' hidden' : ''; ?>>
					<?php wp_nonce_field( Auth::NONCE, 'hodima_auth_nonce', false ); ?>
					<button class="hpn-link" type="submit" name="resend_otp" value="1">ارسال دوباره کد</button>
				</form>
				<a class="hpn-link" href="<?php echo esc_url( \Hodima\Panel\Page::url() ); ?>">تغییر شماره</a>
			</div>
		<?php else : ?>
			<h1 class="hpn-title">ورود یا ثبت‌نام</h1>
			<p class="hpn-lead"><?php echo esc_html( $hodima_sms ? 'حساب ندارید؟ با همین کد پیامکی ساخته می‌شود.' : 'با شماره موبایل و رمز عبور وارد شوید.' ); ?></p>

			<?php if ( $hodima_sms ) : ?>
				<div class="hpn-seg" role="tablist" aria-label="روش ورود" hidden data-hpn-tabs>
					<button type="button" class="hpn-seg__btn" role="tab" id="hpn-tab-sms" aria-controls="hpn-panel-sms" aria-selected="<?php echo 'sms' === $hodima_tab ? 'true' : 'false'; ?>">با کد پیامکی</button>
					<button type="button" class="hpn-seg__btn" role="tab" id="hpn-tab-password" aria-controls="hpn-panel-password" aria-selected="<?php echo 'password' === $hodima_tab ? 'true' : 'false'; ?>">با رمز عبور</button>
				</div>
				<div role="tabpanel" id="hpn-panel-sms" aria-labelledby="hpn-tab-sms">
					<form class="hpn-auth__form" method="post">
						<?php wp_nonce_field( Auth::NONCE, 'hodima_auth_nonce', false ); ?>
						<?php echo $hodima_trap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ساخته‌شده بالا با esc_attr. ?>
						<label class="hpn-label" for="hpn-otp-mobile">شماره موبایل</label>
						<input class="hpn-input hpn-input--lg" id="hpn-otp-mobile" name="otp_mobile" value="<?php echo esc_attr( $hodima_auth->mobile ); ?>" placeholder="09121234567" dir="ltr" inputmode="tel" autocomplete="tel" required>
						<button class="hpn-btn hpn-btn--lg hpn-btn--block" type="submit" name="send_otp" value="1">دریافت کد تأیید</button>
					</form>
				</div>
			<?php endif; ?>

			<div role="<?php echo $hodima_sms ? 'tabpanel' : 'region'; ?>" id="hpn-panel-password" aria-labelledby="<?php echo $hodima_sms ? 'hpn-tab-password' : 'hpn-password-title'; ?>">
				<?php if ( ! $hodima_sms ) : ?>
					<h2 class="screen-reader-text" id="hpn-password-title">ورود با رمز عبور</h2>
				<?php endif; ?>
				<form class="hpn-auth__form" method="post">
					<?php wp_nonce_field( Auth::NONCE, 'hodima_auth_nonce', false ); ?>
					<?php echo $hodima_trap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ساخته‌شده بالا با esc_attr. ?>
					<label class="hpn-label" for="hpn-mobile">شماره موبایل</label>
					<input class="hpn-input hpn-input--lg" id="hpn-mobile" name="mobile" value="<?php echo esc_attr( $hodima_auth->mobile ); ?>" placeholder="09121234567" dir="ltr" inputmode="tel" autocomplete="username" required>
					<label class="hpn-label" for="hpn-password">رمز عبور</label>
					<div class="hpn-inline">
						<input class="hpn-input hpn-input--lg" id="hpn-password" name="password" type="password" autocomplete="current-password" required>
						<button type="button" class="hpn-btn hpn-btn--ghost" data-hpn-reveal="hpn-password" aria-pressed="false" hidden>نمایش</button>
					</div>
					<label class="hpn-check"><input type="checkbox" name="remember_me" value="1" checked> مرا به خاطر بسپار</label>
					<button class="hpn-btn hpn-btn--lg hpn-btn--block" type="submit" name="login_with_password" value="1">ورود</button>
				</form>
			</div>
			<?php if ( $hodima_sms ) : ?>
				<p class="hpn-help hpn-auth__note">کد ۵ رقمی تا ۵ دقیقه معتبر است.</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
