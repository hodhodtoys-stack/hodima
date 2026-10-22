<?php
/**
 * کادر تنظیمات نوتیفیکیشن در صفحه ویرایش.
 *
 * طراحی پیشخوان هدیما (کارت‌ها، کارت گزینه، شمارنده، انتخاب بخش‌بندی‌شده و
 * خلاصه از admin-ui.css/js هسته؛ اسکیل hodima-admin-ui)؛
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

	/**
	 * محتوای کادر.
	 *
	 * نسخه ۶ (استاندارد طراحی پیشخوان، اسکیل hodima-admin-ui): هر تب چند کارت
	 * با یک سؤال ساده؛ امکان‌های خاموش‌شدنی کارت با کلید (به‌جای «۰ = خاموش»)،
	 * عددها با واحد و دکمه +/−، گزینه‌های کوتاه به‌شکل دکمه‌های کنار هم (همان
	 * name و مقدار select قبلی)، جای نمایش با کاشی تصویری و یک جمله خلاصه بالای
	 * تب‌ها. نام و مقدار همه فیلدها مثل قبل است (آزمون «رفت‌وبرگشت» در
	 * notification-tests.php).
	 */
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
		$count                  = (int) $m['hd_notif_show_count'];
		$count_mode             = match ( true ) {
			0 === $count => 'all',
			1 === $count => 'once',
			default      => 'some',
		};
		?>
		<div class="hd-wrap hdn" data-hdn>

			<?php // وضعیت همیشه بالای فرم (نه داخل تب) ?>
			<section class="hdn-status" aria-label="وضعیت">
				<div class="hdn-status__active">
					<input type="checkbox" class="hd-switch" id="hd_notif_is_active" name="hd_notif_is_active" value="1" role="switch" aria-describedby="hdn-active-help" <?php checked( $m['hd_notif_is_active'], '1' ); ?>>
					<div class="hdn-status__text">
						<label class="hdn-status__label" for="hd_notif_is_active">نمایش در سایت</label>
						<p class="hd-field__help" id="hdn-active-help">خاموش: ذخیره می‌ماند ولی در سایت دیده نمی‌شود.</p>
					</div>
				</div>
				<div class="hdn-status__item">
					<label class="hd-field__label" for="hd_notif_priority">اولویت در صف نمایش</label>
					<?php $this->num( 'hd_notif_priority', (string) (int) $m['hd_notif_priority'], '', 'aria-describedby="hdn-priority-help"' ); ?>
					<p class="hd-field__help" id="hdn-priority-help">۱ تا ۹۹؛ عدد کمتر زودتر.</p>
				</div>
				<?php if ( 'auto-draft' !== $post->post_status && function_exists( 'hodima_notification_stats_url' ) ) : ?>
					<div class="hdn-status__item hdn-status__stats">
						<a class="button" href="<?php echo esc_url( hodima_notification_stats_url( [ 'notif' => $post->ID ] ) ); ?>"><?php echo $this->icon( 'chart-bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?> آمار این نوتیفیکیشن</a>
						<p class="hd-field__help">نمایش، کلیک و نمودار روزانه.</p>
					</div>
				<?php endif; ?>
			</section>

			<?php // خلاصه رفتار در یک جمله (متن را notification-metabox.js می‌سازد؛ بدون JS پنهان) ?>
			<div class="hd-summary" data-hdn-summary hidden>
				<?php echo $this->icon( 'lightbulb', 'hd-summary__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
				<div class="hd-summary__body">
					<span class="hd-summary__label">خلاصه به زبان ساده</span>
					<p class="hd-summary__text" data-hdn-summary-text aria-live="polite"></p>
					<div class="hd-summary__warn" data-hdn-summary-warn></div>
				</div>
			</div>

			<div class="hdn-layout">
				<div class="hdn-main">
					<?php // بدون JS همه بخش‌ها زیر هم دیده می‌شوند؛ JS آن‌ها را تب می‌کند ?>
					<div class="hdn-tabs" role="tablist" aria-label="بخش‌های تنظیمات" data-hdn-tabs hidden>
						<button type="button" role="tab" class="hdn-tabs__tab" id="hdn-tab-media" aria-controls="hdn-panel-media" aria-selected="true"><?php echo $this->icon( 'format-image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?><span>عکس و لینک</span></button>
						<button type="button" role="tab" class="hdn-tabs__tab" id="hdn-tab-place" aria-controls="hdn-panel-place" aria-selected="false" tabindex="-1"><?php echo $this->icon( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?><span>جا و اندازه</span></button>
						<button type="button" role="tab" class="hdn-tabs__tab" id="hdn-tab-time" aria-controls="hdn-panel-time" aria-selected="false" tabindex="-1"><?php echo $this->icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?><span>زمان و دفعات</span></button>
						<button type="button" role="tab" class="hdn-tabs__tab" id="hdn-tab-audience" aria-controls="hdn-panel-audience" aria-selected="false" tabindex="-1"><?php echo $this->icon( 'groups' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?><span>مخاطب</span></button>
						<button type="button" role="tab" class="hdn-tabs__tab" id="hdn-tab-look" aria-controls="hdn-panel-look" aria-selected="false" tabindex="-1"><?php echo $this->icon( 'admin-appearance' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?><span>دکمه و انیمیشن</span></button>
						<button type="button" role="tab" class="hdn-tabs__tab" id="hdn-tab-preview" aria-controls="hdn-panel-preview" aria-selected="false" tabindex="-1"><?php echo $this->icon( 'welcome-view-site' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?><span>پیش‌نمایش</span></button>
					</div>

					<section class="hdn-panel" id="hdn-panel-media" role="tabpanel" aria-labelledby="hdn-tab-media" data-hdn-panel>
						<h2 class="hdn-panel__title"><?php echo $this->icon( 'format-image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>عکس و لینک</h2>
						<?php $this->card_open( 'media-image', 'format-image', 'چه عکسی نشان داده شود؟', 'JPG، PNG یا WebP از کتابخانه رسانه (تا موبایل نسخه کوچک‌ترش را بگیرد). بدون عکس، نوتیفیکیشن نمایش داده نمی‌شود.' ); ?>
							<div class="hdn-media<?php echo '' === $m['hd_notif_bg_image'] ? ' is-empty' : ''; ?>" data-hdn-media>
								<div class="hdn-media__preview">
									<?php echo $this->icon( 'format-image', 'hdn-media__placeholder' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
									<img src="<?php echo esc_url( (string) $thumb ); ?>" alt="" data-hdn-thumb>
								</div>
								<div class="hdn-media__actions">
									<input type="hidden" name="hd_notif_bg_image" value="<?php echo esc_url( $m['hd_notif_bg_image'] ); ?>" data-hdn-image-url>
									<input type="hidden" name="hd_notif_bg_image_id" value="<?php echo esc_attr( (string) $image_id ); ?>" data-hdn-image-id>
									<span class="hdn-media__empty-text">هنوز عکسی انتخاب نشده</span>
									<button type="button" class="button button-primary" data-hdn-pick><?php echo $this->icon( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?> انتخاب عکس</button>
									<button type="button" class="button-link button-link-delete" data-hdn-remove>حذف عکس</button>
								</div>
							</div>
							<div class="hd-fields hdn-gap">
								<div class="hd-field hd-field--wide">
									<label class="hd-field__label" for="hd_notif_main_link">با کلیک روی عکس به کجا برود؟</label>
									<input type="url" class="ltr" id="hd_notif_main_link" name="hd_notif_main_link" value="<?php echo esc_url( $m['hd_notif_main_link'] ); ?>" placeholder="https://…" aria-describedby="hdn-link-help">
									<p class="hd-field__help" id="hdn-link-help">خالی = عکس کلیک نمی‌خورد.</p>
								</div>
							</div>
						<?php $this->card_close(); ?>
						<?php $this->card_open( 'media-bg', 'art', 'دور عکس چه باشد؟' ); ?>
							<div class="hd-opts">
								<?php
								$this->opt_switch( 'hd_notif_bg_transparent', $m, 'format-image', 'بدون کادر (PNG شفاف)', 'فقط خود شکل عکس با سایه دورش؛ با انتخاب عکس PNG خودکار روشن می‌شود.', 'data-hdn-transparent' );
								?>
								<div class="hd-field hdn-color-field" data-hdn-bg-field>
									<label class="hd-field__label" for="hd_notif_bg_color">رنگ کادر دور عکس</label>
									<input type="color" id="hd_notif_bg_color" name="hd_notif_bg_color" value="<?php echo esc_attr( $m['hd_notif_bg_color'] ); ?>">
									<p class="hd-field__help">گوشه‌های گرد و فاصله دور عکس به این رنگ.</p>
								</div>
							</div>
						<?php $this->card_close(); ?>
					</section>

					<section class="hdn-panel" id="hdn-panel-place" role="tabpanel" aria-labelledby="hdn-tab-place" data-hdn-panel>
						<h2 class="hdn-panel__title"><?php echo $this->icon( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>جا و اندازه</h2>
						<?php $this->card_open( 'place', 'layout', 'کجای صفحه نشان داده شود؟', 'پنجره وسط بقیه صفحه را می‌پوشاند تا بسته شود؛ جعبه گوشه مزاحم خواندن صفحه نیست.' ); ?>
							<div class="hd-tiles" role="radiogroup" aria-labelledby="hdn-h-place">
								<?php
								$descs = [
									'center'       => 'روی صفحه؛ تا بسته نشود بقیه پوشیده است.',
									'bottom_right' => 'کنار صفحه؛ مزاحم خواندن نیست.',
									'bottom_left'  => 'کنار صفحه؛ مزاحم خواندن نیست.',
								];
								foreach ( hodima_notification_positions() as $position ) :
									?>
									<label class="hd-tile">
										<input type="radio" name="hd_notif_position" value="<?php echo esc_attr( $position ); ?>" <?php checked( $m['hd_notif_position'], $position ); ?>>
										<span class="hd-tile__box">
											<span class="hd-tile__art hdn-art hdn-art--<?php echo esc_attr( $position ); ?>" aria-hidden="true"><span class="hdn-art__box"></span></span>
											<span class="hd-tile__title"><?php echo esc_html( hodima_notification_position_label( $position ) ); ?></span>
											<span class="hd-tile__desc"><?php echo esc_html( $descs[ $position ] ?? '' ); ?></span>
										</span>
									</label>
								<?php endforeach; ?>
							</div>
							<div class="hd-fields hdn-gap">
								<?php
								$this->seg(
									'hd_notif_mobile_mode',
									'پنجره وسط در موبایل',
									$m,
									[
										'corner' => 'جعبه گوشه پایین (پیشنهادی)',
										'modal'  => 'همان پنجره وسط',
									],
									'گوگل صفحه‌ای را که در موبایل با پنجره‌ای محتوا را می‌پوشاند پایین‌تر نشان می‌دهد؛ جعبه گوشه این مشکل را ندارد.',
									'data-hdn-mobile-field'
								);
								?>
							</div>
						<?php $this->card_close(); ?>
						<?php $this->card_open( 'size', 'editor-expand', 'چه اندازه‌ای؟' ); ?>
							<div class="hd-opts">
								<div class="hd-field hdn-plain-opt">
									<label class="hd-field__label" for="hd_notif_width">عرض</label>
									<?php $this->num( 'hd_notif_width', $m['hd_notif_width'], 'پیکسل', 'step="10" aria-describedby="hdn-width-help"' ); ?>
									<p class="hd-field__help" id="hdn-width-help">در موبایل حداکثر ۹۰٪ عرض صفحه.</p>
								</div>
								<?php $this->opt_value( 'hd_notif_height', $m, 'image-flip-vertical', 'ارتفاع ثابت', 'خاموش = به اندازه خود عکس (پیشنهادی).', 'پیکسل', '500', 'step="10"' ); ?>
							</div>
						<?php $this->card_close(); ?>
					</section>

					<section class="hdn-panel" id="hdn-panel-time" role="tabpanel" aria-labelledby="hdn-tab-time" data-hdn-panel>
						<h2 class="hdn-panel__title"><?php echo $this->icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>زمان و دفعات</h2>
						<?php $this->card_open( 'open', 'controls-play', 'چه وقتی باز شود؟', 'یک یا چند محرک را روشن کنید؛ هر کدام زودتر اتفاق بیفتد پاپ‌آپ را باز می‌کند. همه خاموش = همان لحظه باز شدن صفحه.', '<span class="hd-pill hd-pill--info" data-hdn-trigger-count hidden></span>' ); ?>
							<div class="hd-opts">
								<?php
								$this->opt_value( 'hd_notif_trigger_scroll', $m, 'arrow-down-alt', 'بعد از اسکرول', 'وقتی این مقدار از صفحه خوانده شد.', '٪ صفحه', '50', 'step="5"' );
								$this->opt_value( 'hd_notif_trigger_inactivity', $m, 'coffee', 'وقتی بازدیدکننده بی‌کار است', 'بدون حرکت ماوس، لمس، کلید یا اسکرول.', 'ثانیه', '20' );
								$this->opt_switch( 'hd_notif_trigger_exit', $m, 'migrate', 'هنگام ترک صفحه', 'وقتی ماوس به‌سمت بستن تب یا نوار آدرس می‌رود.', '', '<span class="hd-pill hd-pill--warn">فقط دسکتاپ (موبایل ماوس ندارد)</span>' );
								?>
							</div>
						<?php $this->card_close(); ?>
						<?php $this->card_open( 'wait', 'backup', 'مکث و بستن خودکار' ); ?>
							<div class="hd-opts">
								<?php
								$this->opt_value( 'hd_notif_delay', $m, 'clock', 'مکث پیش از نمایش', 'بعد از محرک (یا باز شدن صفحه) این‌قدر صبر می‌کند.', 'ثانیه', '5' );
								$this->opt_value( 'hd_notif_auto_close_time', $m, 'dismiss', 'بستن خودکار', 'بعد از این مدت خودش بسته می‌شود؛ تا وقتی ماوس روی پاپ‌آپ است صبر می‌کند.', 'ثانیه', '10' );
								?>
							</div>
						<?php $this->card_close(); ?>
						<?php $this->card_open( 'dates', 'calendar-alt', 'از چه روزی تا چه روزی؟', 'خالی = از همین حالا و بدون تاریخ پایان.' ); ?>
							<div class="hd-fields">
								<div class="hd-field">
									<label class="hd-field__label" for="hd_notif_start_date">از</label>
									<input type="date" id="hd_notif_start_date" name="hd_notif_start_date" value="<?php echo esc_attr( $m['hd_notif_start_date'] ); ?>" data-hdn-date aria-describedby="hdn-start-jalali">
									<p class="hd-field__help" id="hdn-start-jalali" data-hdn-jalali aria-live="polite"></p>
								</div>
								<div class="hd-field">
									<label class="hd-field__label" for="hd_notif_end_date">تا (آخر همان روز)</label>
									<input type="date" id="hd_notif_end_date" name="hd_notif_end_date" value="<?php echo esc_attr( $m['hd_notif_end_date'] ); ?>" data-hdn-date aria-describedby="hdn-end-jalali">
									<p class="hd-field__help" id="hdn-end-jalali" data-hdn-jalali aria-live="polite"></p>
								</div>
							</div>
						<?php $this->card_close(); ?>
						<?php $this->card_open( 'repeat', 'controls-repeat', 'چند بار به هر بازدیدکننده؟', 'بعد از بستن، در همان بازدید دوباره باز نمی‌شود.' ); ?>
							<div class="hd-fields">
								<div class="hd-field">
									<span class="hd-field__label" id="hdn-count-label">دفعات نمایش</span>
									<?php // حالت فقط برای ظاهر است (ذخیره نمی‌شود)؛ مقدار واقعی همان hd_notif_show_count (۰ = بی‌محدودیت) ?>
									<fieldset class="hd-seg" aria-labelledby="hdn-count-label" data-hdn-count-mode hidden>
										<?php foreach ( [ 'once' => 'فقط یک بار', 'some' => 'چند بار', 'all' => 'بی‌محدودیت' ] as $mode => $text ) : ?>
											<label class="hd-seg__opt"><input type="radio" name="hdn_count_mode" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $count_mode, $mode ); ?>><span><?php echo esc_html( $text ); ?></span></label>
										<?php endforeach; ?>
									</fieldset>
									<div class="hdn-count" data-hdn-count-field>
										<?php $this->num( 'hd_notif_show_count', $m['hd_notif_show_count'], 'بار', 'aria-label="حداکثر دفعات نمایش به هر نفر (۰ = بی‌محدودیت)"' ); ?>
									</div>
								</div>
							</div>
							<div class="hd-opts hdn-gap">
								<?php $this->opt_value( 'hd_notif_cooldown_days', $m, 'hidden', 'بعد از بستن یا کلیک، مدتی نشان نده', 'خاموش = فقط تا آخر همان بازدید؛ بازدید بعدی دوباره دیده می‌شود.', 'روز', '7' ); ?>
							</div>
						<?php $this->card_close(); ?>
					</section>

					<section class="hdn-panel" id="hdn-panel-audience" role="tabpanel" aria-labelledby="hdn-tab-audience" data-hdn-panel>
						<h2 class="hdn-panel__title"><?php echo $this->icon( 'groups' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>مخاطب</h2>
						<?php $this->card_open( 'who', 'groups', 'به چه کسی نشان داده شود؟' ); ?>
							<div class="hd-fields">
								<?php
								$this->seg(
									'hd_notif_device',
									'دستگاه',
									$m,
									[
										'all'     => 'همه',
										'desktop' => 'فقط دسکتاپ',
										'mobile'  => 'فقط موبایل',
									]
								);
								$this->seg(
									'hd_notif_user_status',
									'ورود به حساب',
									$m,
									[
										'all'        => 'همه',
										'logged_in'  => 'واردشده‌ها',
										'logged_out' => 'مهمان‌ها',
									]
								);
								$this->seg(
									'hd_notif_visitor',
									'بازدیدکننده',
									$m,
									[
										'all'       => 'همه',
										'new'       => 'بار اول',
										'returning' => 'برگشتی‌ها',
									],
									'«بار اول» یعنی اولین بازدید از سایت؛ «برگشتی» یعنی قبلا آمده.'
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
								?>
							</div>
							<div class="hd-opts hdn-gap">
								<?php $this->opt_value( 'hd_notif_min_pageviews', $m, 'visibility', 'فقط بعد از چند صفحه', 'به کسی که در همین بازدید دست‌کم این تعداد صفحه دیده (علاقه‌مند).', 'صفحه', '3' ); ?>
							</div>
						<?php $this->card_close(); ?>
						<?php $this->card_open( 'where', 'admin-page', 'در کدام صفحه‌ها؟' ); ?>
							<div class="hd-fields">
								<?php
								$this->seg(
									'hd_notif_target_post_type',
									'نوع صفحه',
									$m,
									[
										'all'     => 'همه',
										'post'    => 'مقاله‌ها',
										'page'    => 'برگه‌ها',
										'product' => 'محصولات',
									]
								);
								$this->seg(
									'hd_notif_target_type',
									'بر اساس آدرس',
									$m,
									[
										'all'      => 'همه آدرس‌ها',
										'specific' => 'فقط این آدرس‌ها',
										'exclude'  => 'به‌جز این آدرس‌ها',
									]
								);
								?>
								<div class="hd-field hd-field--wide" data-hdn-pages-field>
									<label class="hd-field__label" for="hd_notif_target_page">آدرس‌ها</label>
									<textarea id="hd_notif_target_page" name="hd_notif_target_page" rows="4" class="ltr" placeholder="/shop/&#10;/product-category/hair/&#10;123" aria-describedby="hdn-pages-help"><?php echo esc_textarea( $m['hd_notif_target_page'] ); ?></textarea>
									<p class="hd-field__help" id="hdn-pages-help">هر خط یک آدرس یا شناسه نوشته؛ زیرصفحه‌های هر آدرس هم حساب می‌شوند (حداکثر ۵۰ خط).</p>
								</div>
								<?php $this->render_product_cats( $m ); ?>
							</div>
						<?php $this->card_close(); ?>
						<?php $this->card_open( 'source', 'admin-site-alt3', 'از کجا آمده؟', 'خالی = از هر جایی.' ); ?>
							<div class="hd-fields">
								<div class="hd-field">
									<label class="hd-field__label" for="hd_notif_target_utm">فقط بازدید از تبلیغ</label>
									<input type="text" class="ltr" id="hd_notif_target_utm" name="hd_notif_target_utm" value="<?php echo esc_attr( $m['hd_notif_target_utm'] ); ?>" placeholder="yektanet" aria-describedby="hdn-utm-help">
									<p class="hd-field__help" id="hdn-utm-help">کسی که با این مقدار در utm_… آدرس آمده (تا آخر همان بازدید).</p>
								</div>
								<div class="hd-field">
									<label class="hd-field__label" for="hd_notif_target_referrer">فقط آمده از سایت</label>
									<input type="text" class="ltr" id="hd_notif_target_referrer" name="hd_notif_target_referrer" value="<?php echo esc_attr( $m['hd_notif_target_referrer'] ); ?>" placeholder="instagram.com">
								</div>
							</div>
							<div class="hd-opts hdn-gap">
								<?php $this->opt_switch( 'hd_notif_skip_search', $m, 'search', 'به تازه‌واردِ جستجوی گوگل نشان نده', 'فقط در همان صفحه اول؛ از صفحه دوم بازدید نمایش داده می‌شود. برای سئو امن‌تر است.' ); ?>
							</div>
						<?php $this->card_close(); ?>
					</section>

					<section class="hdn-panel" id="hdn-panel-look" role="tabpanel" aria-labelledby="hdn-tab-look" data-hdn-panel>
						<h2 class="hdn-panel__title"><?php echo $this->icon( 'admin-appearance' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>دکمه و انیمیشن</h2>
						<?php $this->card_open( 'close', 'dismiss', 'دکمه بستن' ); ?>
							<div class="hd-opts">
								<?php
								ob_start();
								?>
								<div class="hd-field">
									<label class="hd-field__label" for="hd_notif_close_color">رنگ دکمه</label>
									<input type="color" id="hd_notif_close_color" name="hd_notif_close_color" value="<?php echo esc_attr( $m['hd_notif_close_color'] ); ?>">
								</div>
								<?php
								$this->opt_switch( 'hd_notif_close_btn', $m, 'dismiss', 'دکمه بستن (×)', 'بدون آن، پنجره با Escape، کلیک بیرون یا «بستن خودکار» بسته می‌شود.', '', (string) ob_get_clean() );
								?>
							</div>
						<?php $this->card_close(); ?>
						<?php $this->card_open( 'anim', 'controls-play', 'انیمیشن' ); ?>
							<div class="hd-fields">
								<?php
								$this->seg(
									'hd_notif_anim_in',
									'باز شدن',
									$m,
									[
										'zoom_in'  => 'بزرگ شدن',
										'fade_in'  => 'محو شدن',
										'slide_up' => 'بالا آمدن',
										'bounce'   => 'جهش',
									]
								);
								$this->seg(
									'hd_notif_anim_out',
									'بسته شدن',
									$m,
									[
										'fade_out'   => 'محو شدن',
										'slide_down' => 'پایین رفتن',
									],
									'در تب «پیش‌نمایش» با «اجرای انیمیشن» ببینید.'
								);
								?>
							</div>
						<?php $this->card_close(); ?>
					</section>

					<?php // تب آخر: پیش‌نمایش تمام‌عرض (تا 2.0.6 ستون باریک کنار فرم بود و فرم را تنگ می‌کرد) ?>
					<section class="hdn-panel" id="hdn-panel-preview" role="tabpanel" aria-labelledby="hdn-tab-preview" data-hdn-panel>
						<h2 class="hdn-panel__title"><?php echo $this->icon( 'welcome-view-site' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>پیش‌نمایش</h2>
						<?php $this->card_open( 'preview', 'welcome-view-site', 'بازدیدکننده چه می‌بیند؟', 'همان ظاهر سایت؛ تغییرهای تب‌های دیگر (پیش از ذخیره) اینجا دیده می‌شوند.' ); ?>
							<div class="hdn-preview__bar">
								<div class="hdn-toggle-group" role="group" aria-label="اندازه پیش‌نمایش">
									<button type="button" class="hdn-toggle-group__btn" data-hdn-device="desktop" aria-pressed="true"><?php echo $this->icon( 'desktop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>دسکتاپ</button>
									<button type="button" class="hdn-toggle-group__btn" data-hdn-device="mobile" aria-pressed="false"><?php echo $this->icon( 'smartphone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>موبایل</button>
								</div>
								<button type="button" class="button" data-hdn-replay><?php echo $this->icon( 'controls-repeat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?> اجرای انیمیشن</button>
								<button type="button" class="button" data-hdn-zoom aria-haspopup="dialog"><?php echo $this->icon( 'editor-expand' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?> بزرگ‌نمایی</button>
							</div>
							<?php // صفحه مجازی (دسکتاپ ۱۲۸۰×۸۰۰، موبایل ۳۹۰×۷۸۰) با همان اندازه‌های واقعی، کوچک‌شده تا جا شود ?>
							<div class="hdn-preview__frame" data-hdn-frame>
								<div class="hdn-preview" data-hdn-preview data-device="desktop">
									<p class="hdn-preview__text" aria-hidden="true">متن صفحه سایت پشت پاپ‌آپ…</p>
								</div>
							</div>
						<?php $this->card_close(); ?>
					</section>
				</div>
			</div>

			<?php // همان صفحه مجازی پیش‌نمایش در اندازه بزرگ (JS آن را به اینجا می‌برد و برمی‌گرداند) ?>
			<dialog class="hdn-zoom" aria-labelledby="hdn-zoom-title" data-hdn-zoom-dialog>
				<div class="hdn-zoom__bar">
					<h2 class="hdn-zoom__title" id="hdn-zoom-title">پیش‌نمایش بزرگ</h2>
					<div class="hdn-preview__bar">
						<div class="hdn-toggle-group" role="group" aria-label="اندازه پیش‌نمایش بزرگ">
							<button type="button" class="hdn-toggle-group__btn" data-hdn-device="desktop" aria-pressed="true"><?php echo $this->icon( 'desktop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>دسکتاپ</button>
							<button type="button" class="hdn-toggle-group__btn" data-hdn-device="mobile" aria-pressed="false"><?php echo $this->icon( 'smartphone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>موبایل</button>
						</div>
						<button type="button" class="button button-primary" data-hdn-zoom-close>بستن</button>
					</div>
				</div>
				<div class="hdn-preview__frame" data-hdn-zoom-frame></div>
			</dialog>
		</div>
		<?php
	}

	/**
	 * شروع یک کارت (یک سؤال): آیکون، عنوان h3 (`hdn-h-{id}`)، توضیح و نشان اختیاری.
	 *
	 * @param string $id    شناسه کوتاه کارت.
	 * @param string $icon  نام Dashicon.
	 * @param string $title عنوان (سؤال).
	 * @param string $desc  توضیح یک‌خطی.
	 * @param string $extra HTML ثابت کنار عنوان (نشان).
	 */
	private function card_open( string $id, string $icon, string $title, string $desc = '', string $extra = '' ): void {
		?>
		<section class="hd-card hdn-card" aria-labelledby="hdn-h-<?php echo esc_attr( $id ); ?>">
			<div class="hd-card__head">
				<?php echo $this->icon( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
				<h3 class="hd-card__title" id="hdn-h-<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></h3>
				<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ثابت همین کلاس ?>
				<?php if ( '' !== $desc ) : ?>
					<p class="hd-card__desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</div>
		<?php
	}

	/** پایان کارت. */
	private function card_close(): void {
		echo '</section>';
	}

	/**
	 * فیلد عدد با دکمه‌های − و + و واحد (`.hd-num`؛ دکمه‌ها را admin-ui.js هسته نشان می‌دهد).
	 *
	 * @param string $key   کلید فیلد (name و id).
	 * @param string $value مقدار فعلی.
	 * @param string $unit  واحد کنار عدد.
	 * @param string $attrs ویژگی‌های اضافه (ثابت، escape‌شده).
	 */
	private function num( string $key, string $value, string $unit = '', string $attrs = '' ): void {
		$field = hodima_notification_fields()[ $key ] ?? [];
		// اولویت محدوده ندارد (۱ تا ۹۹ در پاک‌سازی)
		$min = 'priority' === ( $field['type'] ?? '' ) ? 1 : ( $field['min'] ?? 0 );
		$max = 'priority' === ( $field['type'] ?? '' ) ? 99 : ( $field['max'] ?? 9999 );
		?>
		<div class="hd-num">
			<button type="button" class="hd-num__btn" data-hd-step="-1" aria-label="کم کردن" hidden>−</button>
			<input type="number" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo (int) $min; ?>" max="<?php echo (int) $max; ?>" inputmode="numeric" <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>>
			<button type="button" class="hd-num__btn" data-hd-step="1" aria-label="زیاد کردن" hidden>+</button>
			<?php if ( '' !== $unit ) : ?>
				<span class="hd-num__unit" aria-hidden="true"><?php echo esc_html( $unit ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * کارت گزینه برای فیلد عددی که «۰ = خاموش» است: کلید (بدون name) عدد را بین ۰
	 * و آخرین مقدار جابه‌جا می‌کند (admin-ui.js هسته). داده همان عدد قبلی است.
	 *
	 * @param string                $key     کلید فیلد.
	 * @param array<string, string> $meta    مقدارهای فعلی.
	 * @param string                $icon    نام Dashicon.
	 * @param string                $title   عنوان.
	 * @param string                $desc    توضیح یک‌خطی.
	 * @param string                $unit    واحد.
	 * @param string                $suggest مقدار پیشنهادی وقتی روشن می‌شود.
	 * @param string                $attrs   ویژگی‌های اضافه فیلد عدد (ثابت).
	 */
	private function opt_value( string $key, array $meta, string $icon, string $title, string $desc, string $unit, string $suggest, string $attrs = '' ): void {
		$on = (int) $meta[ $key ] > 0;
		?>
		<div class="hd-opt">
			<div class="hd-opt__head">
				<?php echo $this->icon( $icon, 'hd-opt__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
				<label class="hd-opt__title" for="<?php echo esc_attr( $key ); ?>-switch"><?php echo esc_html( $title ); ?></label>
				<input type="checkbox" class="hd-switch hd-opt__switch" id="<?php echo esc_attr( $key ); ?>-switch" role="switch" data-hd-opt-switch <?php checked( $on ); ?>>
			</div>
			<div class="hd-opt__body">
				<p class="hd-opt__desc"><?php echo esc_html( $desc ); ?></p>
				<?php
				$this->num(
					$key,
					$meta[ $key ],
					$unit,
					sprintf( 'aria-label="%s" data-hd-opt-value data-hd-opt-default="%s" %s', esc_attr( $title . ( '' !== $unit ? " ({$unit})" : '' ) ), esc_attr( $suggest ), $attrs )
				);
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * کارت گزینه برای فیلد روشن/خاموش (کلید همان checkbox با name).
	 *
	 * @param string                $key   کلید فیلد.
	 * @param array<string, string> $meta  مقدارهای فعلی.
	 * @param string                $icon  نام Dashicon.
	 * @param string                $title عنوان.
	 * @param string                $desc  توضیح یک‌خطی.
	 * @param string                $attrs ویژگی‌های اضافه کلید (ثابت).
	 * @param string                $body  HTML اضافه بدنه (escape‌شده).
	 */
	private function opt_switch( string $key, array $meta, string $icon, string $title, string $desc, string $attrs = '', string $body = '' ): void {
		?>
		<div class="hd-opt">
			<div class="hd-opt__head">
				<?php echo $this->icon( $icon, 'hd-opt__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>
				<label class="hd-opt__title" for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $title ); ?></label>
				<input type="checkbox" class="hd-switch hd-opt__switch" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="1" role="switch" <?php checked( $meta[ $key ], '1' ); ?> <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>>
			</div>
			<div class="hd-opt__body">
				<p class="hd-opt__desc"><?php echo esc_html( $desc ); ?></p>
				<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در فراخواننده ?>
			</div>
		</div>
		<?php
	}

	/**
	 * انتخاب بخش‌بندی‌شده (`.hd-seg`): رادیوهای واقعی با همان name و مقدارهای select قبلی.
	 *
	 * @param string                $key     کلید فیلد.
	 * @param string                $label   برچسب.
	 * @param array<string, string> $meta    مقدارهای فعلی.
	 * @param array<string, string> $options مقدار ← متن.
	 * @param string                $help    راهنمای یک‌خطی.
	 * @param string                $attrs   ویژگی‌های اضافه ظرف (ثابت).
	 */
	private function seg( string $key, string $label, array $meta, array $options, string $help = '', string $attrs = '' ): void {
		?>
		<div class="hd-field" <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ثابت ?>>
			<span class="hd-field__label" id="<?php echo esc_attr( $key ); ?>-label"><?php echo esc_html( $label ); ?></span>
			<fieldset class="hd-seg" aria-labelledby="<?php echo esc_attr( $key ); ?>-label">
				<?php foreach ( $options as $value => $text ) : ?>
					<label class="hd-seg__opt"><input type="radio" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $meta[ $key ], $value ); ?>><span><?php echo esc_html( $text ); ?></span></label>
				<?php endforeach; ?>
			</fieldset>
			<?php if ( '' !== $help ) : ?>
				<p class="hd-field__help"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * انتخاب دسته‌های محصول (فقط اگر ووکامرس هست).
	 *
	 * @param array<string, string> $meta مقدارهای فعلی.
	 */
	private function render_product_cats( array $meta ): void {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return;
		}
		$terms = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 300,
				'orderby'    => 'name',
			]
		);
		if ( ! is_array( $terms ) || ! $terms ) {
			return;
		}
		$chosen = hodima_notification_ids( $meta['hd_notif_target_product_cat'] );
		?>
		<fieldset class="hd-field hd-field--wide">
			<legend class="hd-field__label">فقط در این دسته‌های محصول</legend>
			<?php // بدون تیک هم کلید فرستاده شود (وگرنه برداشتن همه تیک‌ها ذخیره نمی‌شد) ?>
			<input type="hidden" name="hd_notif_target_product_cat[]" value="">
			<div class="hd-choices hdn-cats">
				<?php foreach ( $this->sorted_terms( $terms ) as [ $term, $depth ] ) : ?>
					<label style="<?php echo esc_attr( sprintf( 'padding-inline-start:%.2frem', 0.5 + $depth * 1.25 ) ); ?>">
						<input type="checkbox" name="hd_notif_target_product_cat[]" value="<?php echo (int) $term->term_id; ?>" <?php checked( in_array( (int) $term->term_id, $chosen, true ) ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="hd-field__help">هیچ‌کدام = همه صفحه‌ها. با انتخاب، فقط صفحه همین دسته‌ها (و زیردسته‌هایشان) و محصولاتشان.</p>
		</fieldset>
		<?php
	}

	/**
	 * ترم‌ها به ترتیب درختی (والد، بعد فرزندها) با عمق.
	 *
	 * @param array<int|string, mixed> $terms ترم‌ها.
	 * @return list<array{0: WP_Term, 1: int}>
	 */
	private function sorted_terms( array $terms ): array {
		$children = [];
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$children[ $term->parent ][] = $term;
			}
		}
		$out  = [];
		$walk = static function ( int $parent_id, int $depth ) use ( &$walk, &$out, $children ): void {
			foreach ( $children[ $parent_id ] ?? [] as $term ) {
				$out[] = [ $term, $depth ];
				$walk( $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		// دسته‌ای که والدش در این فهرست نیامده (سقف ۳۰۰) گم نشود
		$shown = array_map( static fn( array $row ): int => $row[0]->term_id, $out );
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term && ! in_array( $term->term_id, $shown, true ) ) {
				$out[] = [ $term, 0 ];
			}
		}
		return $out;
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
