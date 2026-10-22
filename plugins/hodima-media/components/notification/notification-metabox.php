<?php
/**
 * کادر تنظیمات نوتیفیکیشن در صفحه ویرایش.
 *
 * طراحی پیشخوان هدیما (کارت‌ها، فیلدها و کلیدهای hd-* از admin-ui.css هسته)؛
 * پیش‌نمایش با همان CSS سایت (notification-front.css) ساخته می‌شود تا با
 * چیزی که بازدیدکننده می‌بیند یکی باشد.
 *
 * @package Hodima\Media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/notification-settings.php';
require_once __DIR__ . '/notification-save.php';

/**
 * کادر تنظیمات نوتیفیکیشن.
 */
final class Hodima_Notification_Metabox {

	public const NONCE_ACTION = 'hodima_save_notification_data';
	public const NONCE_NAME   = 'hodima_notification_meta_nonce';

	/** ثبت هوک‌ها. */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'add_meta_boxes_' . HODIMA_NOTIFICATION_POST_TYPE, [ $this, 'add' ] );
	}

	/** CSS/JS فقط در صفحه ویرایش/افزودن نوتیفیکیشن. */
	public function enqueue( string $hook ): void {
		$screen = get_current_screen();
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || ! $screen || HODIMA_NOTIFICATION_POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		// پیش‌نمایش با CSS خود سایت
		wp_enqueue_style( 'hd-notification-front-css', hodima_notification_asset_url( 'css/notification-front.css' ), [], hodima_notification_asset_version( 'css/notification-front.css' ) );
		wp_enqueue_style( 'hd-notification-metabox-css', hodima_notification_asset_url( 'css/notification-metabox.css' ), [ 'hd-notification-front-css' ], hodima_notification_asset_version( 'css/notification-metabox.css' ) );
		wp_enqueue_script( 'hd-notification-metabox-js', hodima_notification_asset_url( 'js/notification-metabox.js' ), [], hodima_notification_asset_version( 'js/notification-metabox.js' ), [ 'in_footer' => true ] );
	}

	/** ثبت کادر. */
	public function add(): void {
		add_meta_box( 'hd_notification_settings', 'تنظیمات نوتیفیکیشن', [ $this, 'render' ], HODIMA_NOTIFICATION_POST_TYPE, 'normal', 'high' );
	}

	/** محتوای کادر. */
	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$m = hodima_notification_meta( $post->ID );
		// نوتیفیکیشن تازه: پنجره وسط در موبایل جعبه گوشه شود (توصیه گوگل)؛
		// اعلان‌های قبلی همان رفتار قبلی را نگه می‌دارند
		if ( 'auto-draft' === $post->post_status ) {
			$m['hd_notif_mobile_mode'] = 'corner';
		}
		$m['hd_notif_position'] = hodima_notification_normalize_position( $m['hd_notif_position'] );
		$image_id               = (int) $m['hd_notif_bg_image_id'];
		$thumb                  = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'medium' ) : false;
		$thumb                  = $thumb ? $thumb : $m['hd_notif_bg_image'];
		?>
		<div class="hd-wrap hdn" data-hdn>

			<section class="hd-card">
				<div class="hd-card__head">
					<?php echo $this->icon( 'visibility' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
					<h2 class="hd-card__title">وضعیت</h2>
				</div>
				<div class="hd-fields">
					<div class="hd-field">
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" name="hd_notif_is_active" value="1" <?php checked( $m['hd_notif_is_active'], '1' ); ?>>
							<span>نمایش در سایت روشن است</span>
						</label>
						<p class="hd-field__help">خاموش: ذخیره می‌ماند ولی در سایت دیده نمی‌شود.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_priority">اولویت در صف نمایش</label>
						<input type="number" class="small-text" id="hd_notif_priority" name="hd_notif_priority" value="<?php echo esc_attr( (string) (int) $m['hd_notif_priority'] ); ?>" min="1" max="99" inputmode="numeric">
						<p class="hd-field__help">۱ تا ۹۹؛ عدد کمتر زودتر نمایش داده می‌شود.</p>
					</div>
				</div>
			</section>

			<section class="hd-card">
				<div class="hd-card__head">
					<?php echo $this->icon( 'format-image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
					<h2 class="hd-card__title">عکس و لینک</h2>
				</div>
				<div class="hd-fields">
					<div class="hd-field">
						<span class="hd-field__label" id="hdn-image-label">عکس پاپ‌آپ (JPG، PNG یا WebP)</span>
						<div class="hdn-media<?php echo '' === $m['hd_notif_bg_image'] ? ' is-empty' : ''; ?>" data-hdn-media>
							<div class="hdn-media__preview">
								<?php echo $this->icon( 'format-image', 'hdn-media__placeholder' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
								<img src="<?php echo esc_url( (string) $thumb ); ?>" alt="" data-hdn-thumb>
							</div>
							<div class="hdn-media__actions">
								<input type="hidden" name="hd_notif_bg_image" value="<?php echo esc_url( $m['hd_notif_bg_image'] ); ?>" data-hdn-image-url>
								<input type="hidden" name="hd_notif_bg_image_id" value="<?php echo esc_attr( (string) $image_id ); ?>" data-hdn-image-id>
								<button type="button" class="button" data-hdn-pick aria-describedby="hdn-image-label">انتخاب عکس</button>
								<button type="button" class="button-link button-link-delete" data-hdn-remove>حذف عکس</button>
							</div>
						</div>
						<p class="hd-field__help">بدون عکس، نوتیفیکیشن نمایش داده نمی‌شود. عکس از کتابخانه رسانه بیاید تا موبایل نسخه کوچک‌ترش را بگیرد.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_main_link">لینک مقصد</label>
						<input type="url" class="ltr" id="hd_notif_main_link" name="hd_notif_main_link" value="<?php echo esc_url( $m['hd_notif_main_link'] ); ?>" placeholder="https://…">
						<p class="hd-field__help">کل عکس به این لینک می‌رود؛ خالی = عکس کلیک نمی‌خورد.</p>
					</div>
					<div class="hd-field">
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" name="hd_notif_bg_transparent" value="1" <?php checked( $m['hd_notif_bg_transparent'], '1' ); ?> data-hdn-transparent>
							<span>پس‌زمینه کاملاً شفاف (عکس PNG بدون پس‌زمینه)</span>
						</label>
						<p class="hd-field__help">فقط خود شکل عکس دیده می‌شود: بدون کادر و گوشه گرد، و سایه دور خود شکل. با انتخاب عکس PNG خودکار روشن می‌شود.</p>
					</div>
					<div class="hd-field" data-hdn-bg-field>
						<label class="hd-field__label" for="hd_notif_bg_color">رنگ کادر دور عکس</label>
						<input type="color" id="hd_notif_bg_color" name="hd_notif_bg_color" value="<?php echo esc_attr( $m['hd_notif_bg_color'] ); ?>">
						<p class="hd-field__help">وقتی پس‌زمینه شفاف نیست.</p>
					</div>
				</div>
			</section>

			<section class="hd-card">
				<div class="hd-card__head">
					<?php echo $this->icon( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
					<h2 class="hd-card__title">جا و اندازه</h2>
				</div>
				<div class="hd-fields">
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_position">جای نمایش</label>
						<select id="hd_notif_position" name="hd_notif_position">
							<?php foreach ( hodima_notification_positions() as $position ) : ?>
								<option value="<?php echo esc_attr( $position ); ?>" <?php selected( $m['hd_notif_position'], $position ); ?>><?php echo esc_html( hodima_notification_position_label( $position ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="hd-field__help">پنجره وسط بقیه صفحه را می‌پوشاند تا بسته شود؛ جعبه گوشه مزاحم خواندن صفحه نیست.</p>
					</div>
					<div class="hd-field" data-hdn-mobile-field>
						<label class="hd-field__label" for="hd_notif_mobile_mode">پنجره وسط در موبایل</label>
						<select id="hd_notif_mobile_mode" name="hd_notif_mobile_mode">
							<option value="corner" <?php selected( $m['hd_notif_mobile_mode'], 'corner' ); ?>>جعبه گوشه پایین (پیشنهادی)</option>
							<option value="modal" <?php selected( $m['hd_notif_mobile_mode'], 'modal' ); ?>>همان پنجره وسط</option>
						</select>
						<p class="hd-field__help">گوگل صفحه‌ای را که در موبایل با پنجره‌ای محتوا را می‌پوشاند پایین‌تر نشان می‌دهد؛ جعبه گوشه این مشکل را ندارد.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_width">عرض (پیکسل)</label>
						<input type="number" class="small-text" id="hd_notif_width" name="hd_notif_width" value="<?php echo esc_attr( $m['hd_notif_width'] ); ?>" min="100" max="2000" inputmode="numeric">
						<p class="hd-field__help">در موبایل حداکثر ۹۰٪ عرض صفحه.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_height">ارتفاع (پیکسل)</label>
						<input type="number" class="small-text" id="hd_notif_height" name="hd_notif_height" value="<?php echo esc_attr( $m['hd_notif_height'] ); ?>" min="0" max="2000" inputmode="numeric">
						<p class="hd-field__help">۰ = خودکار به اندازه عکس (پیشنهادی).</p>
					</div>
				</div>
			</section>

			<section class="hd-card">
				<div class="hd-card__head">
					<?php echo $this->icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
					<h2 class="hd-card__title">کی باز شود و کی بسته شود</h2>
					<p class="hd-card__desc">بدون هیچ محرکی، پاپ‌آپ بعد از «تأخیر» باز می‌شود. با چند محرک، اولین اتفاق آن را باز می‌کند.</p>
				</div>
				<div class="hd-fields">
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_delay">تأخیر بعد از باز شدن صفحه (ثانیه)</label>
						<input type="number" class="small-text" id="hd_notif_delay" name="hd_notif_delay" value="<?php echo esc_attr( $m['hd_notif_delay'] ); ?>" min="0" max="3600" inputmode="numeric">
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_trigger_scroll">بعد از اسکرول (درصد صفحه)</label>
						<input type="number" class="small-text" id="hd_notif_trigger_scroll" name="hd_notif_trigger_scroll" value="<?php echo esc_attr( $m['hd_notif_trigger_scroll'] ); ?>" min="0" max="100" inputmode="numeric">
						<p class="hd-field__help">۰ = خاموش.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_trigger_inactivity">بعد از بی‌کاری کاربر (ثانیه)</label>
						<input type="number" class="small-text" id="hd_notif_trigger_inactivity" name="hd_notif_trigger_inactivity" value="<?php echo esc_attr( $m['hd_notif_trigger_inactivity'] ); ?>" min="0" max="3600" inputmode="numeric">
						<p class="hd-field__help">۰ = خاموش.</p>
					</div>
					<div class="hd-field">
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" name="hd_notif_trigger_exit" value="1" <?php checked( $m['hd_notif_trigger_exit'], '1' ); ?>>
							<span>هنگام ترک صفحه (بردن ماوس به بالای پنجره)</span>
						</label>
						<p class="hd-field__help">فقط دسکتاپ؛ موبایل ماوس ندارد.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_auto_close_time">بستن خودکار بعد از (ثانیه)</label>
						<input type="number" class="small-text" id="hd_notif_auto_close_time" name="hd_notif_auto_close_time" value="<?php echo esc_attr( $m['hd_notif_auto_close_time'] ); ?>" min="0" max="3600" inputmode="numeric">
						<p class="hd-field__help">۰ = خاموش. تا وقتی ماوس روی پاپ‌آپ است بسته نمی‌شود.</p>
					</div>
				</div>
			</section>

			<section class="hd-card">
				<div class="hd-card__head">
					<?php echo $this->icon( 'groups' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
					<h2 class="hd-card__title">به چه کسی و در کدام صفحه</h2>
				</div>
				<div class="hd-fields">
					<?php
					$this->select(
						'hd_notif_device',
						'دستگاه',
						$m,
						[
							'all'     => 'همه دستگاه‌ها',
							'desktop' => 'فقط دسکتاپ',
							'mobile'  => 'فقط موبایل',
						]
					);
					$this->select(
						'hd_notif_user_status',
						'وضعیت ورود',
						$m,
						[
							'all'        => 'همه بازدیدکننده‌ها',
							'logged_in'  => 'فقط کاربران وارد شده',
							'logged_out' => 'فقط مهمان‌ها',
						]
					);
					$this->select(
						'hd_notif_target_role',
						'نقش کاربر',
						$m,
						[
							'all'           => 'همه نقش‌ها',
							'customer'      => 'فقط مشتری‌ها (وارد شده)',
							'subscriber'    => 'فقط مشترک‌ها (وارد شده)',
							'administrator' => 'فقط مدیران (برای آزمایش)',
						]
					);
					$this->select(
						'hd_notif_target_post_type',
						'نوع صفحه',
						$m,
						[
							'all'     => 'همه صفحه‌های سایت',
							'post'    => 'فقط مقاله‌ها',
							'page'    => 'فقط برگه‌ها',
							'product' => 'فقط محصولات',
						]
					);
					$this->select(
						'hd_notif_target_type',
						'بر اساس آدرس',
						$m,
						[
							'all'      => 'همه آدرس‌ها',
							'specific' => 'فقط آدرس‌های زیر',
							'exclude'  => 'همه به‌جز آدرس‌های زیر',
						]
					);
					?>
					<div class="hd-field hd-field--wide" data-hdn-pages-field>
						<label class="hd-field__label" for="hd_notif_target_page">آدرس‌ها</label>
						<textarea id="hd_notif_target_page" name="hd_notif_target_page" rows="4" class="ltr" placeholder="/shop/&#10;/product-category/hair/&#10;123"><?php echo esc_textarea( $m['hd_notif_target_page'] ); ?></textarea>
						<p class="hd-field__help">هر خط یک آدرس یا شناسه نوشته؛ زیرصفحه‌های هر آدرس هم حساب می‌شوند (حداکثر ۵۰ خط).</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_target_utm">فقط بازدید از تبلیغ (UTM)</label>
						<input type="text" class="ltr" id="hd_notif_target_utm" name="hd_notif_target_utm" value="<?php echo esc_attr( $m['hd_notif_target_utm'] ); ?>" placeholder="yektanet">
						<p class="hd-field__help">اگر پر باشد، فقط کسی که با این مقدار در utm_… آدرس آمده (تا آخر همان بازدید).</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_target_referrer">فقط آمده از سایت</label>
						<input type="text" class="ltr" id="hd_notif_target_referrer" name="hd_notif_target_referrer" value="<?php echo esc_attr( $m['hd_notif_target_referrer'] ); ?>" placeholder="instagram.com">
					</div>
					<div class="hd-field">
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" name="hd_notif_skip_search" value="1" <?php checked( $m['hd_notif_skip_search'], '1' ); ?>>
							<span>به کسی که همین الان از جستجوی گوگل آمده نشان نده</span>
						</label>
						<p class="hd-field__help">فقط در همان صفحه اول؛ از صفحه دوم بازدید نمایش داده می‌شود. برای سئو امن‌تر است.</p>
					</div>
				</div>
			</section>

			<section class="hd-card">
				<div class="hd-card__head">
					<?php echo $this->icon( 'calendar-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
					<h2 class="hd-card__title">تاریخ و دفعات</h2>
				</div>
				<div class="hd-fields">
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_start_date">تاریخ شروع</label>
						<input type="date" id="hd_notif_start_date" name="hd_notif_start_date" value="<?php echo esc_attr( $m['hd_notif_start_date'] ); ?>" data-hdn-date>
						<p class="hd-field__help" data-hdn-jalali aria-live="polite"></p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_end_date">تاریخ پایان (تا آخر همان روز)</label>
						<input type="date" id="hd_notif_end_date" name="hd_notif_end_date" value="<?php echo esc_attr( $m['hd_notif_end_date'] ); ?>" data-hdn-date>
						<p class="hd-field__help" data-hdn-jalali aria-live="polite"></p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_show_count">حداکثر دفعات نمایش به هر نفر</label>
						<input type="number" class="small-text" id="hd_notif_show_count" name="hd_notif_show_count" value="<?php echo esc_attr( $m['hd_notif_show_count'] ); ?>" min="0" max="1000" inputmode="numeric">
						<p class="hd-field__help">۰ = نامحدود. بعد از بستن، در همان بازدید دوباره باز نمی‌شود.</p>
					</div>
				</div>
			</section>

			<section class="hd-card">
				<div class="hd-card__head">
					<?php echo $this->icon( 'admin-appearance' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
					<h2 class="hd-card__title">دکمه بستن و انیمیشن</h2>
				</div>
				<div class="hd-fields">
					<div class="hd-field">
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" name="hd_notif_close_btn" value="1" <?php checked( $m['hd_notif_close_btn'], '1' ); ?>>
							<span>دکمه بستن (×)</span>
						</label>
						<p class="hd-field__help">بدون آن، پنجره با Escape، کلیک بیرون یا «بستن خودکار» بسته می‌شود.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hd_notif_close_color">رنگ دکمه بستن</label>
						<input type="color" id="hd_notif_close_color" name="hd_notif_close_color" value="<?php echo esc_attr( $m['hd_notif_close_color'] ); ?>">
					</div>
					<?php
					$this->select(
						'hd_notif_anim_in',
						'انیمیشن باز شدن',
						$m,
						[
							'zoom_in'  => 'بزرگ شدن',
							'fade_in'  => 'محو شدن',
							'slide_up' => 'بالا آمدن',
							'bounce'   => 'جهش',
						]
					);
					$this->select(
						'hd_notif_anim_out',
						'انیمیشن بسته شدن',
						$m,
						[
							'fade_out'   => 'محو شدن',
							'slide_down' => 'پایین رفتن',
						]
					);
					?>
				</div>
			</section>

			<section class="hd-card">
				<div class="hd-card__head">
					<?php echo $this->icon( 'welcome-view-site' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
					<h2 class="hd-card__title">پیش‌نمایش</h2>
					<p class="hd-card__desc">با همان ظاهر سایت؛ تغییرهای فرم بلافاصله دیده می‌شوند (پیش از ذخیره).</p>
				</div>
				<div class="hdn-preview__bar" role="group" aria-label="اندازه پیش‌نمایش">
					<button type="button" class="button" data-hdn-device="desktop" aria-pressed="true">دسکتاپ</button>
					<button type="button" class="button" data-hdn-device="mobile" aria-pressed="false">موبایل</button>
					<button type="button" class="button" data-hdn-replay>اجرای دوباره انیمیشن</button>
				</div>
				<div class="hdn-preview" data-hdn-preview data-device="desktop">
					<p class="hdn-preview__text" aria-hidden="true">متن صفحه سایت پشت پاپ‌آپ…</p>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * یک فیلد انتخابی.
	 *
	 * @param string                $key     کلید فیلد.
	 * @param string                $label   برچسب.
	 * @param array<string, string> $meta    مقدارهای فعلی.
	 * @param array<string, string> $options مقدار ← متن.
	 */
	private function select( string $key, string $label, array $meta, array $options ): void {
		?>
		<div class="hd-field">
			<label class="hd-field__label" for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $options as $value => $text ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta[ $key ], $value ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/** آیکون Dashicons (با Core همان تابع مشترک). */
	private function icon( string $name, string $extra_class = '' ): string {
		return sprintf( '<span class="dashicons dashicons-%1$s %2$s" aria-hidden="true"></span>', esc_attr( $name ), esc_attr( $extra_class ) );
	}
}

new Hodima_Notification_Metabox();
