<?php
/**
 * پیشخوان: پنل مشتریان ← تنظیمات پیامک.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * صفحه تنظیمات پیامک (طرح تأییدشده کاربر، Hodima Panel 0.1.4؛ قانون ۸ و اسکیل hodima-admin-ui).
 *
 * کالبد ثابت: سربرگ ← ۴ کارت وضعیت ← کنش اصلی «پیامک آزمایشی» ← کارت «با کدام سرویس»
 * (انتخاب اصلی/پشتیبان + تب هر سرویس + یک دکمه ذخیره) ← گزارش ارسال‌ها (جمع‌شونده).
 * اجزا از کیت همین افزونه (ui/ui-kit.*، پیشوند hdp-؛ منبع tools/ui-kit)، بی تکیه بر Core.
 * رمز/کلید ذخیره‌شده هرگز در HTML چاپ نمی‌شود؛ فیلد خالی = همان مقدار قبلی.
 */
final class Admin_Sms {

	public const SLUG = 'hodima-panel-sms';

	public const PARENT = 'hodima-panel-orders';

	public const CAP = 'manage_options';

	/** فیلدهایی که مثل رمز رفتار می‌کنند (چاپ نمی‌شوند). */
	private const SECRET = [ 'password', 'api_key' ];

	/** ثبت هوک‌ها (فقط پیشخوان). */
	public static function boot(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'admin_post_hodima_panel_sms_save', [ self::class, 'handle_save' ] );
		add_action( 'admin_post_hodima_panel_sms_test', [ self::class, 'handle_test' ] );
	}

	/** زیرمنو زیر «پنل مشتریان». */
	public static function menu(): void {
		add_submenu_page( self::PARENT, 'تنظیمات پیامک', 'تنظیمات پیامک', self::CAP, self::SLUG, [ self::class, 'render' ] );
	}

	/**
	 * کیت UI و CSS همین صفحه، فقط روی همین صفحه.
	 *
	 * @param string $hook شناسه صفحه.
	 */
	public static function assets( string $hook ): void {
		if ( ! str_ends_with( $hook, '_page_' . self::SLUG ) ) {
			return;
		}
		$base = HODIMA_PANEL_URL;
		wp_enqueue_style( 'hodima-panel-ui', $base . '/ui/ui-kit.css', [], asset_version( 'ui/ui-kit.css' ) );
		wp_enqueue_script( 'hodima-panel-ui', $base . '/ui/ui-kit.js', [], asset_version( 'ui/ui-kit.js' ), true );
		wp_enqueue_style( 'hodima-panel-admin-sms', $base . '/assets/css/admin-sms.css', [ 'hodima-panel-ui' ], asset_version( 'assets/css/admin-sms.css' ) );
	}

	/**
	 * فیلد از ثابت wp-config آمده؟ (قفل در فرم)
	 *
	 * @param string $id    سرویس.
	 * @param string $field فیلد.
	 */
	public static function from_constant( string $id, string $field ): bool {
		return defined( 'HODIMA_PANEL_SMS_' . strtoupper( $id . '_' . $field ) );
	}

	/**
	 * ذخیره تنظیم‌ها از ورودی فرم (بی nonce؛ handle_save بررسی می‌کند).
	 *
	 * @param array<string, mixed> $in ورودی wp_unslash شده.
	 */
	public static function save( array $in ): void {
		$providers = Sms::providers();
		$saved     = get_option( Sms::OPTION, [] );
		$saved     = is_array( $saved ) ? $saved : [];
		$primary   = sanitize_key( (string) ( $in['primary'] ?? '' ) );
		$fallback  = sanitize_key( (string) ( $in['fallback'] ?? '' ) );
		$out       = [
			'primary'   => isset( $providers[ $primary ] ) ? $primary : '',
			'fallback'  => isset( $providers[ $fallback ] ) && $fallback !== $primary ? $fallback : '',
			'providers' => [],
		];
		$posted    = is_array( $in['providers'] ?? null ) ? $in['providers'] : [];
		foreach ( $providers as $id => $spec ) {
			foreach ( array_keys( $spec['fields'] ) as $field ) {
				$old   = (string) ( $saved['providers'][ $id ][ $field ] ?? '' );
				$value = trim( sanitize_text_field( (string) ( $posted[ $id ][ $field ] ?? '' ) ) );
				// رمز چاپ نمی‌شود، پس فیلد خالی یعنی «همان قبلی»؛ برای پاک کردن تیک «پاک شود»
				if ( in_array( $field, self::SECRET, true ) && '' === $value && empty( $posted[ $id ][ $field . '_clear' ] ) ) {
					$value = $old;
				}
				$out['providers'][ $id ][ $field ] = $value;
			}
		}
		update_option( Sms::OPTION, $out, false );
	}

	/** فرم ذخیره (admin-post). */
	public static function handle_save(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'اجازه ندارید.', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'hodima_panel_sms_save' );
		self::save( (array) wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- هر فیلد در save() پاک‌سازی می‌شود.
		self::back( 'saved' );
	}

	/** پیامک آزمایشی (admin-post). */
	public static function handle_test(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'اجازه ندارید.', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'hodima_panel_sms_test' );
		$mobile  = Auth::normalize_mobile( sanitize_text_field( wp_unslash( (string) ( $_POST['test_mobile'] ?? '' ) ) ) );
		$service = sanitize_key( wp_unslash( (string) ( $_POST['test_service'] ?? '' ) ) );
		if ( '' === $mobile ) {
			self::back( 'test_mobile' );
		}
		$code   = (string) random_int( 10000, 99999 );
		$result = isset( Sms::providers()[ $service ] ) ? Sms::send_with( $service, $mobile, $code ) : Sms::send_code( $mobile, $code );
		set_transient( 'hodima_panel_sms_test_' . get_current_user_id(), $result + [ 'to' => Sms::mask( $mobile ) ], 10 * MINUTE_IN_SECONDS );
		self::back( 'tested' );
	}

	/**
	 * برگشت به صفحه با پیام.
	 *
	 * @param string $msg کلید پیام.
	 */
	private static function back( string $msg ): never {
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG, 'hp_msg' => $msg ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * خلاصه گزارش: [موفق، ناموفق].
	 *
	 * @return array{0: int, 1: int}
	 */
	private static function log_counts(): array {
		$ok = 0;
		$no = 0;
		foreach ( Sms::log_entries() as $entry ) {
			$entry['ok'] ? ++$ok : ++$no;
		}
		return [ $ok, $no ];
	}

	/**
	 * فیلدهای خالی یک سرویس.
	 *
	 * @param string $id سرویس.
	 */
	private static function missing( string $id ): int {
		return count( array_filter( Sms::settings()['providers'][ $id ] ?? [], static fn( string $v ): bool => '' === $v ) );
	}

	/** صفحه. */
	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'اجازه ندارید.' );
		}
		$settings  = Sms::settings();
		$providers = Sms::providers();
		$label     = static fn( string $id ): string => $providers[ $id ]['label'] ?? 'تنظیم نشده';
		[ $ok, $no ] = self::log_counts();
		$msg       = sanitize_key( wp_unslash( (string) ( $_GET['hp_msg'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط پیام.
		$test      = get_transient( 'hodima_panel_sms_test_' . get_current_user_id() );
		if ( 'tested' === $msg ) {
			delete_transient( 'hodima_panel_sms_test_' . get_current_user_id() );
		}
		$active    = '' !== $settings['primary'] ? $settings['primary'] : (string) array_key_first( $providers );
		$admin_url = admin_url( 'admin-post.php' );
		?>
		<div class="wrap hdp-ui hdp-page hodima-panel-sms">
			<header class="hdp-hero">
				<span class="hdp-hero__icon dashicons dashicons-smartphone" aria-hidden="true"></span>
				<div>
					<span class="hdp-hero__badge">پنل مشتریان</span>
					<h1 class="hdp-hero__title">تنظیمات پیامک</h1>
					<p class="hdp-hero__desc">کد ورود و ثبت‌نام مشتری‌ها با کدام سرویس پیامک شود؛ اگر سرویس اصلی خطا داد، همان کد با سرویس پشتیبان.</p>
				</div>
			</header>
			<hr class="wp-header-end">
			<?php
			if ( 'saved' === $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>تنظیمات پیامک ذخیره شد.</p></div>';
			} elseif ( 'test_mobile' === $msg ) {
				echo '<div class="notice notice-error is-dismissible"><p>شماره موبایل آزمایشی نامعتبر است (مثلا ۰۹۱۲۳۴۵۶۷۸۹).</p></div>';
			}
			?>
			<div class="hdp-page__body">

				<ul class="hdp-stats" aria-label="وضعیت پیامک">
					<li class="hdp-stat"><span class="hdp-stat__label">وضعیت ارسال</span><span class="hdp-stat__value">
						<?php if ( Sms::ready() ) : ?>
							<span class="hdp-pill">آماده</span>
						<?php else : ?>
							<span class="hdp-pill hdp-pill--warn">خاموش</span>
						<?php endif; ?>
					</span><span class="hdp-stat__sub"><?php echo esc_html( Sms::ready() ? 'ورود و ثبت‌نام با کد فعال است' : 'سرویس اصلی را کامل کنید' ); ?></span></li>
					<li class="hdp-stat"><span class="hdp-stat__label">سرویس اصلی</span><span class="hdp-stat__value"><?php echo esc_html( $label( $settings['primary'] ) ); ?></span></li>
					<li class="hdp-stat"><span class="hdp-stat__label">پشتیبان</span><span class="hdp-stat__value"><?php echo esc_html( '' !== $settings['fallback'] ? $label( $settings['fallback'] ) : 'خاموش' ); ?></span></li>
					<li class="hdp-stat"><span class="hdp-stat__label">۳۰ ارسال آخر</span><span class="hdp-stat__value"><?php echo esc_html( number_format_i18n( $ok ) ); ?> موفق</span>
						<?php if ( $no > 0 ) : ?>
							<span class="hdp-stat__sub hodima-panel-sms__bad"><?php echo esc_html( number_format_i18n( $no ) ); ?> ناموفق</span>
						<?php endif; ?>
					</li>
				</ul>

				<section class="hdp-card" aria-labelledby="hp-sms-test-title">
					<h2 class="hdp-card__title" id="hp-sms-test-title"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>کار می‌کند؟ یک پیامک آزمایشی بفرستید</h2>
					<p class="hdp-card__desc">یک کد نمونه با همان الگو به شماره خودتان؛ نتیجه همین‌جا و در گزارش پایین.</p>
					<form method="post" action="<?php echo esc_url( $admin_url ); ?>" class="hdp-action hodima-panel-sms__test">
						<input type="hidden" name="action" value="hodima_panel_sms_test">
						<?php wp_nonce_field( 'hodima_panel_sms_test' ); ?>
						<label class="screen-reader-text" for="hp-test-mobile">شماره موبایل</label>
						<input type="tel" id="hp-test-mobile" name="test_mobile" class="hdp-input" dir="ltr" inputmode="tel" placeholder="09121234567" required>
						<label class="screen-reader-text" for="hp-test-service">با کدام سرویس</label>
						<select id="hp-test-service" name="test_service" class="hdp-input hodima-panel-sms__service">
							<option value="">سرویس اصلی (و پشتیبان)</option>
							<?php foreach ( $providers as $id => $spec ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>"<?php disabled( ! Sms::is_configured( $id ) ); ?>><?php echo esc_html( $spec['label'] . ( Sms::is_configured( $id ) ? '' : ' (ناقص)' ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="hdp-btn"<?php disabled( ! Sms::ready() && ! array_filter( array_map( [ Sms::class, 'is_configured' ], array_keys( $providers ) ) ) ); ?>>ارسال پیامک آزمایشی</button>
					</form>
					<?php if ( 'tested' === $msg && is_array( $test ) ) : ?>
						<p class="hodima-panel-sms__result" data-ok="<?php echo $test['ok'] ? '1' : '0'; ?>" role="status">
							<?php if ( $test['ok'] ) : ?>
								<?php echo esc_html( 'ارسال شد با ' . $label( (string) $test['provider'] ) . ' به ' . (string) $test['to'] . ' — اگر پیامک نرسید، کد الگو و خط خدماتی را در پنل همان سرویس بررسی کنید.' ); ?>
							<?php else : ?>
								<?php echo esc_html( 'ارسال نشد: ' . (string) $test['error'] ); ?>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</section>

				<section class="hdp-card" aria-labelledby="hp-sms-svc-title">
					<h2 class="hdp-card__title" id="hp-sms-svc-title"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>با کدام سرویس پیامک شود؟</h2>
					<p class="hdp-card__desc">کلیدها فقط همین‌جا ذخیره می‌شوند؛ اگر در wp-config تعریف شده باشند، فیلد قفل است.</p>
					<form method="post" action="<?php echo esc_url( $admin_url ); ?>">
						<input type="hidden" name="action" value="hodima_panel_sms_save">
						<?php wp_nonce_field( 'hodima_panel_sms_save' ); ?>
						<div class="hdp-row">
							<fieldset class="hdp-field hodima-panel-sms__choice">
								<legend class="hdp-field__label">سرویس اصلی</legend>
								<div class="hdp-chips">
									<?php foreach ( $providers as $id => $spec ) : ?>
										<label class="hdp-chip"><input type="radio" name="primary" value="<?php echo esc_attr( $id ); ?>"<?php checked( $settings['primary'], $id ); ?>><?php echo esc_html( $spec['label'] ); ?></label>
									<?php endforeach; ?>
								</div>
							</fieldset>
							<fieldset class="hdp-field hodima-panel-sms__choice">
								<legend class="hdp-field__label">اگر اصلی خطا داد (پشتیبان خودکار)</legend>
								<div class="hdp-chips">
									<label class="hdp-chip"><input type="radio" name="fallback" value=""<?php checked( $settings['fallback'], '' ); ?>>خاموش</label>
									<?php foreach ( $providers as $id => $spec ) : ?>
										<label class="hdp-chip"><input type="radio" name="fallback" value="<?php echo esc_attr( $id ); ?>"<?php checked( $settings['fallback'], $id ); ?>><?php echo esc_html( $spec['label'] ); ?></label>
									<?php endforeach; ?>
								</div>
								<p class="hdp-field__help">همان سرویس اصلی را به‌عنوان پشتیبان نمی‌پذیرد.</p>
							</fieldset>
						</div>

						<div class="hdp-tabset" data-hdp-tabset>
							<div class="hdp-tablist" role="tablist" aria-label="تنظیم هر سرویس" hidden>
								<?php foreach ( $providers as $id => $spec ) : ?>
									<?php $miss = self::missing( $id ); ?>
									<button type="button" class="hdp-tab" role="tab" id="hp-sms-tab-<?php echo esc_attr( $id ); ?>" aria-controls="hp-sms-panel-<?php echo esc_attr( $id ); ?>" aria-selected="<?php echo $id === $active ? 'true' : 'false'; ?>"><?php echo esc_html( $spec['label'] ); ?>
										<span class="hdp-tab__count<?php echo 0 === $miss ? '' : ' hodima-panel-sms__missing'; ?>"><?php echo esc_html( 0 === $miss ? 'کامل' : number_format_i18n( $miss ) . ' فیلد خالی' ); ?></span>
									</button>
								<?php endforeach; ?>
							</div>
							<?php foreach ( $providers as $id => $spec ) : ?>
								<div class="hdp-tabpanel" role="tabpanel" id="hp-sms-panel-<?php echo esc_attr( $id ); ?>" aria-labelledby="hp-sms-tab-<?php echo esc_attr( $id ); ?>">
									<h3 class="screen-reader-text"><?php echo esc_html( $spec['label'] ); ?></h3>
									<div class="hdp-row">
										<?php foreach ( $spec['fields'] as $field => $field_label ) : ?>
											<?php
											$fid    = 'hp-sms-' . $id . '-' . $field;
											$value  = (string) ( $settings['providers'][ $id ][ $field ] ?? '' );
											$secret = in_array( $field, self::SECRET, true );
											$locked = self::from_constant( $id, $field );
											?>
											<div class="hdp-field">
												<label class="hdp-field__label" for="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $field_label ); ?></label>
												<input type="<?php echo $secret ? 'password' : 'text'; ?>" id="<?php echo esc_attr( $fid ); ?>" class="hdp-input" dir="ltr" autocomplete="off"
													name="<?php echo esc_attr( 'providers[' . $id . '][' . $field . ']' ); ?>"
													value="<?php echo esc_attr( $secret || $locked ? '' : $value ); ?>"
													<?php echo $locked ? 'readonly' : ''; ?>
													placeholder="<?php echo esc_attr( $locked ? 'از wp-config' : ( $secret && '' !== $value ? '•••••••• (ذخیره‌شده)' : '' ) ); ?>">
												<?php if ( $secret && '' !== $value && ! $locked ) : ?>
													<p class="hdp-field__help">ذخیره‌شده؛ برای عوض کردن، تازه بنویسید. <label><input type="checkbox" name="<?php echo esc_attr( 'providers[' . $id . '][' . $field . '_clear]' ); ?>" value="1"> پاک شود</label></p>
												<?php elseif ( 'body_id' === $field || 'template' === $field || 'template_id' === $field ) : ?>
													<p class="hdp-field__help">متن نمونه الگو: «کد ورود هدهدلی: <?php echo esc_html( 'kavenegar' === $id ? '%token' : ( 'smsir' === $id ? '#CODE#' : '{0}' ) ); ?>»</p>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="hdp-foot">
							<button type="submit" class="hdp-btn">ذخیره تنظیمات</button>
						</div>
					</form>
				</section>

				<?php $log = Sms::log_entries(); ?>
				<details class="hdp-more"<?php echo $no > 0 ? ' open' : ''; ?>>
					<summary class="hdp-more__summary">گزارش ارسال‌ها (۳۰ مورد آخر)<span class="hdp-more__hint"><?php echo esc_html( $log ? number_format_i18n( count( $log ) ) . ' مورد' : 'هنوز ارسالی نیست' ); ?></span></summary>
					<div class="hdp-more__body">
						<?php if ( ! $log ) : ?>
							<p class="hdp-card__desc">هنوز پیامکی فرستاده نشده است.</p>
						<?php else : ?>
							<div class="hdp-table-wrap hodima-panel-sms__log" tabindex="0" role="region" aria-label="گزارش ارسال‌ها">
								<table>
									<thead><tr><th scope="col">زمان</th><th scope="col">سرویس</th><th scope="col">شماره</th><th scope="col">نتیجه</th></tr></thead>
									<tbody>
										<?php foreach ( $log as $entry ) : ?>
											<tr>
												<td data-label="زمان"><?php echo esc_html( (string) wp_date( 'j F، H:i', (int) $entry['time'] ) ); ?></td>
												<td data-label="سرویس"><?php echo esc_html( $label( (string) $entry['provider'] ) ); ?></td>
												<td data-label="شماره" dir="ltr"><?php echo esc_html( (string) $entry['to'] ); ?></td>
												<td data-label="نتیجه" class="<?php echo $entry['ok'] ? 'hodima-panel-sms__ok' : 'hodima-panel-sms__bad'; ?>"><?php echo esc_html( $entry['ok'] ? 'ارسال شد' : 'ناموفق: ' . (string) $entry['error'] ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
					</div>
				</details>

			</div>
		</div>
		<?php
	}
}
