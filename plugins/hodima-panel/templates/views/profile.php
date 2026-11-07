<?php
/**
 * پروفایل و آدرس (طرح تأییدشده، مرحله ۵). ذخیره: Profile::ajax_save (AJAX) و بی JS Profile::handle_form (POST معمولی) — نام فیلدها همان قبلی.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Page;
use Hodima\Panel\Profile;

$hodima_user   = wp_get_current_user();
$hodima_staff  = Profile::is_staff( $hodima_user );
$hodima_error  = Profile::take_error( (int) $hodima_user->ID ); // خطای ذخیره بی JS
$hodima_f      = Profile::fields( $hodima_user );
$hodima_states = Profile::states();
$hodima_cities = $hodima_states ? Profile::cities() : [];
?>
<header class="hpn-head">
	<h1 class="hpn-title">پروفایل و آدرس</h1>
	<p class="hpn-lead">همین اطلاعات در پیش‌فاکتور و صفحه پرداخت فروشگاه استفاده می‌شود.</p>
</header>

<form class="hpn-profile" id="hpn-profile" method="post" action="<?php echo esc_url( Page::url( [ 'section' => 'profile' ] ) ); ?>" data-hpn-profile novalidate>
	<?php wp_nonce_field( 'up_update_profile_action', 'up_profile_nonce', false ); ?>

	<section class="hpn-card" aria-labelledby="hpn-p-contact">
		<h2 class="hpn-card__title" id="hpn-p-contact">اطلاعات تماس</h2>
		<div class="hpn-grid3">
			<div class="hpn-field">
				<label class="hpn-label" for="display_name">نام و نام خانوادگی <span class="hpn-req" aria-hidden="true">*</span></label>
				<input class="hpn-input" id="display_name" name="display_name" value="<?php echo esc_attr( $hodima_f['name'] ); ?>" autocomplete="name" required>
			</div>
			<div class="hpn-field">
				<label class="hpn-label" for="user_phone">شماره موبایل</label>
				<input class="hpn-input" id="user_phone" value="<?php echo esc_attr( $hodima_f['mobile'] ); ?>" dir="ltr" readonly>
				<p class="hpn-help">با همین شماره وارد می‌شوید.</p>
			</div>
			<div class="hpn-field">
				<label class="hpn-label" for="user_email">ایمیل (برای فاکتور) <span class="hpn-req" aria-hidden="true">*</span></label>
				<input class="hpn-input" id="user_email" name="user_email" type="email" value="<?php echo esc_attr( $hodima_f['email'] ); ?>" dir="ltr" autocomplete="email" required>
			</div>
		</div>
	</section>

	<section class="hpn-card" aria-labelledby="hpn-p-address">
		<h2 class="hpn-card__title" id="hpn-p-address">آدرس ارسال</h2>
		<div class="hpn-grid3">
			<div class="hpn-field">
				<label class="hpn-label" for="hpn-state">استان</label>
				<?php if ( $hodima_states ) : ?>
					<select class="hpn-input" id="hpn-state" name="state" autocomplete="address-level1" data-hpn-state>
						<option value="">انتخاب کنید</option>
						<?php foreach ( $hodima_states as $hodima_code => $hodima_name ) : ?>
							<option value="<?php echo esc_attr( (string) $hodima_code ); ?>"<?php selected( $hodima_f['state'], (string) $hodima_code ); ?>><?php echo esc_html( $hodima_name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input class="hpn-input" id="hpn-state" name="state" value="<?php echo esc_attr( $hodima_f['state'] ); ?>" autocomplete="address-level1">
				<?php endif; ?>
			</div>
			<div class="hpn-field">
				<label class="hpn-label" for="hpn-city">شهر</label>
				<input class="hpn-input" id="hpn-city" name="city" value="<?php echo esc_attr( $hodima_f['city'] ); ?>" autocomplete="address-level2"<?php echo $hodima_cities ? ' list="hpn-city-list"' : ''; ?>>
				<?php if ( $hodima_cities ) : ?>
					<datalist id="hpn-city-list"></datalist>
					<script type="application/json" id="hpn-cities"><?php echo wp_json_encode( $hodima_cities, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ); ?></script>
				<?php endif; ?>
			</div>
			<div class="hpn-field">
				<label class="hpn-label" for="postcode">کد پستی <span class="hpn-req" aria-hidden="true">*</span></label>
				<input class="hpn-input" id="postcode" name="postcode" value="<?php echo esc_attr( $hodima_f['postcode'] ); ?>" dir="ltr" inputmode="numeric" autocomplete="postal-code" maxlength="12" required>
				<p class="hpn-help">۱۰ رقم؛ رقم فارسی هم پذیرفته می‌شود.</p>
			</div>
		</div>
		<div class="hpn-field">
			<label class="hpn-label" for="address">آدرس کامل <span class="hpn-req" aria-hidden="true">*</span></label>
			<textarea class="hpn-input" id="address" name="address" rows="2" autocomplete="street-address" required><?php echo esc_textarea( $hodima_f['address'] ); ?></textarea>
		</div>
	</section>

	<details class="hpn-card hpn-more">
		<summary class="hpn-more__summary">رمز عبور <span class="hpn-muted">— اختیاری؛ برای ورود بدون پیامک</span></summary>
		<div class="hpn-grid3">
			<div class="hpn-field">
				<label class="hpn-label" for="new_password">رمز تازه</label>
				<input class="hpn-input" id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="8">
				<p class="hpn-help">دست‌کم ۸ حرف.</p>
			</div>
			<div class="hpn-field">
				<label class="hpn-label" for="confirm_password">تکرار رمز تازه</label>
				<input class="hpn-input" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
			</div>
		</div>
		<fieldset class="hpn-verify">
			<legend class="hpn-label">تأیید تغییر رمز یا ایمیل</legend>
			<p class="hpn-help"><?php echo esc_html( $hodima_staff ? 'فقط وقتی رمز یا ایمیل را عوض می‌کنید: رمز فعلی (حساب مدیریتی کد پیامکی نمی‌پذیرد).' : 'فقط وقتی رمز یا ایمیل را عوض می‌کنید: رمز فعلی، یا کدی که به موبایلتان پیامک می‌شود.' ); ?></p>
			<div class="hpn-grid3">
				<div class="hpn-field">
					<label class="hpn-label" for="current_password">رمز فعلی</label>
					<input class="hpn-input" id="current_password" name="current_password" type="password" autocomplete="current-password">
				</div>
				<?php if ( ! $hodima_staff ) : ?>
					<div class="hpn-field">
						<label class="hpn-label" for="verify_code">یا کد پیامکی</label>
						<div class="hpn-inline">
							<input class="hpn-input" id="verify_code" name="verify_code" inputmode="numeric" autocomplete="one-time-code" maxlength="5" dir="ltr">
							<button type="button" class="hpn-btn hpn-btn--ghost" data-hpn-send-code>ارسال کد</button>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</fieldset>
	</details>

	<div class="hpn-actions">
		<button class="hpn-btn hpn-btn--lg" type="submit">ذخیره تغییرات</button>
		<p class="hpn-status" role="status" data-hpn-status<?php echo '' !== $hodima_error ? ' data-ok="0"' : ''; ?>><?php echo esc_html( $hodima_error ); ?></p>
	</div>
</form>
