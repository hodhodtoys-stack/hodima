<?php
/**
 * ماژول «بومی‌سازی (جلالی و شهرها)» افزونه Hodima Commerce.
 *
 * زیرماژول‌ها (هر کدام با یک کلید در صفحه «ابزارهای هدیما ← بومی‌سازی»):
 *   modules/class-jalali-date.php       تاریخ شمسی سایت (کتابخانه: Hodima Core includes/jalali.php)
 *   modules/class-jalali-admin.php      تاریخ شمسی کنار فیلدهای تاریخ پیشخوان ووکامرس + تقویم شمسی
 *   modules/class-iran-cities.php       استان‌ها و فهرست کامل شهرها (data/iran-cities.php)
 *   modules/class-checkout-optimizer.php ترتیب فیلدها، موبایل/کد پستی، کد ملی اختیاری
 * هر فایل CSS/JS فقط در صفحه‌ای لود می‌شود که لازم است (قبلا در همه صفحه‌های سایت).
 *
 * @package Hodima_Commerce
 */

declare( strict_types=1 );
namespace Hodima\Core\Time_Jalali;

defined( 'ABSPATH' ) || exit;

/** راه‌انداز ماژول و صفحه تنظیمات. */
final class Hodima_Localizer_WC {

	public const PAGE = 'hodima-woocommerce';

	private const GROUP = 'hodima_localizer_group';

	/**
	 * گزینه ← [عنوان، توضیح، آیکون]. نام گزینه‌ها روی سایت ذخیره‌اند؛ عوض نشوند.
	 */
	private const SWITCHES = [
		'hodima_wc_jalali_status'             => [ 'تاریخ شمسی', 'تاریخ شمسی با رقم فارسی در سراسر سایت و ووکامرس؛ در پیشخوان ووکامرس، تاریخ شمسی کنار هر فیلد تاریخ و دکمه تقویم شمسی.', 'dashicons-calendar-alt' ],
		'hodima_wc_iran_cities_status'        => [ 'استان‌ها و شهرهای ایران', 'فهرست ۳۱ استان و همه شهرهای ایران (با جستجو) در تسویه‌حساب و ویرایش آدرس؛ گزینه «شهر من در فهرست نیست» برای نوشتن نام شهر.', 'dashicons-location' ],
		'hodima_wc_checkout_optimizer_status' => [ 'بهینه‌ساز فرم صورتحساب', 'ترتیب فیلدهای آدرس، یکدست‌سازی و بررسی موبایل ۱۱ رقمی و کد پستی ۱۰ رقمی در تسویه‌حساب و ویرایش آدرس.', 'dashicons-feedback' ],
		'hodima_wc_national_id_status'        => [ 'کد ملی (اختیاری)', 'فیلد اختیاری کد ملی برای فاکتور رسمی، با بررسی رقم کنترل؛ در صفحه سفارش پیشخوان و ایمیل مدیر نمایش داده می‌شود. به «بهینه‌ساز فرم صورتحساب» نیاز دارد.', 'dashicons-id' ],
	];

	private static ?Hodima_Localizer_WC $instance = null;

	/** نمونه واحد. */
	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	/** بارگذاری زیرماژول‌ها و صفحه تنظیمات. */
	private function __construct() {
		// سازگاری HPOS در فایل اصلی افزونه (hodima-commerce.php) اعلام می‌شود؛
		// ووکامرس آن را فقط برای فایل اصلی افزونه می‌پذیرد، نه این فایل.
		$this->load_active_modules();

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
			add_action( 'admin_init', [ $this, 'register_plugin_settings' ] );
			// وردپرس برای صفحه‌ای که زیر «تنظیمات» نیست پیش از admin_init «اجازه دسترسی ندارید» می‌دهد؛
			// این هوک درست پیش از همان پیام اجرا می‌شود
			add_action( 'admin_page_access_denied', [ $this, 'redirect_old_page' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_page_assets' ] );
		}
	}

	/** جلوگیری از کپی. */
	private function __clone() {}

	/** آیا کلید یک زیرماژول روشن است (پیش‌فرض روشن). */
	public static function enabled( string $option ): bool {
		return get_option( $option, 'yes' ) === 'yes';
	}

	/** زیرماژول‌های روشن. */
	private function load_active_modules(): void {
		$modules_dir = __DIR__ . '/modules/';

		if ( self::enabled( 'hodima_wc_jalali_status' ) ) {
			require_once $modules_dir . 'class-jalali-date.php';
			if ( Modules\Jalali_Date::available() ) {
				new Modules\Jalali_Date();
				if ( is_admin() ) {
					require_once $modules_dir . 'class-jalali-admin.php';
					new Modules\Jalali_Admin();
				}
			} else {
				// کتابخانه شمسی از Hodima Core 1.2.4 است؛ اگر Commerce زودتر به‌روز شد، تاریخ‌ها تا به‌روزرسانی Core میلادی‌اند
				add_action( 'admin_notices', [ $this, 'core_outdated_notice' ] );
			}
		}
		if ( self::enabled( 'hodima_wc_iran_cities_status' ) ) {
			require_once $modules_dir . 'class-iran-cities.php';
			new Modules\Iran_Cities();
		}
		if ( self::enabled( 'hodima_wc_checkout_optimizer_status' ) ) {
			require_once $modules_dir . 'class-checkout-optimizer.php';
			new Modules\Checkout_Optimizer();
		}
	}

	/** Hodima Core قدیمی: تاریخ شمسی خاموش است تا Core به‌روز شود. */
	public function core_outdated_notice(): void {
		if ( current_user_can( 'update_plugins' ) ) {
			echo '<div class="notice notice-warning"><p><strong>تاریخ شمسی</strong> تا به‌روزرسانی افزونه <strong>Hodima Core</strong> به نسخه ۱.۲.۴ یا بالاتر غیرفعال است. از «پیشخوان ← به‌روزرسانی‌ها» Hodima Core را به‌روز کنید.</p></div>';
		}
	}

	/** آدرس صفحه تنظیمات. */
	public static function page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * زیر «ابزارهای هدیما» (قبلا زیر «تنظیمات» وردپرس)؛ بدون Hodima Core مثل قبل زیر «تنظیمات».
	 * نام صفحه (hodima-woocommerce) همان قبلی است.
	 */
	public function register_admin_menu(): void {
		$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';
		if ( '' !== $parent ) {
			add_submenu_page( $parent, 'بومی‌سازی سایت', 'بومی‌سازی', 'manage_options', self::PAGE, [ $this, 'render_admin_page' ] );
			return;
		}
		add_options_page( 'بومی‌سازی سایت', 'بومی‌سازی سایت', 'manage_options', self::PAGE, [ $this, 'render_admin_page' ] );
	}

	/** آدرس قدیمی (تنظیمات ← بومی‌سازی، نشانک‌ها و پیوند ماژول‌ها) به آدرس تازه برود. */
	public function redirect_old_page(): void {
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط تشخیص صفحه
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'options-general.php' === $pagenow && self::PAGE === $page && function_exists( 'hodima_admin_menu_parent' ) && '' !== hodima_admin_menu_parent() ) {
			wp_safe_redirect( self::page_url() );
			exit;
		}
	}

	/** گزینه‌ها فقط «yes» یا «no» ذخیره شوند (قبلا هر مقداری پذیرفته می‌شد). */
	public function register_plugin_settings(): void {
		foreach ( array_keys( self::SWITCHES ) as $option ) {
			register_setting(
				self::GROUP,
				$option,
				[
					'type'              => 'string',
					'default'           => 'yes',
					'sanitize_callback' => static fn( mixed $value ): string => 'yes' === $value ? 'yes' : 'no',
				]
			);
		}
	}

	/** CSS صفحه تنظیمات (نوار وضعیت و سطر کلیدها)؛ فقط روی همین صفحه، بدون وابستگی به Core تازه. */
	public function enqueue_page_assets(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط تشخیص صفحه
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE !== $page ) {
			return;
		}
		$file = __DIR__ . '/loc-admin.css';
		wp_enqueue_style(
			'hodima-loc-admin',
			HODIMA_COMMERCE_URL . '/core/time-jalali/loc-admin.css',
			wp_style_is( 'hodima-admin-ui', 'registered' ) ? [ 'hodima-admin-ui' ] : [],
			is_file( $file ) ? (string) filemtime( $file ) : HODIMA_COMMERCE_VERSION
		);
	}

	/** صفحه تنظیمات با سیستم طراحی مشترک «ابزارهای هدیما». */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $pagenow;
		?>
		<div class="wrap hd-wrap hodima-loc-admin">
			<?php
			hodima_admin_header(
				[
					'title'       => 'بومی‌سازی سایت',
					'description' => 'تاریخ شمسی، استان‌ها و شهرهای ایران، و بهینه‌سازی فرم تسویه‌حساب ووکامرس.',
					'icon'        => 'dashicons-translation',
				]
			);
			// «تنظیمات» وردپرس پیام ذخیره را خودش چاپ می‌کند (options-head.php)؛ صفحه‌های admin.php نه
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط نمایش پیام بعد از ذخیره options.php
			if ( 'admin.php' === $pagenow && isset( $_GET['settings-updated'] ) && function_exists( 'hodima_admin_notice' ) ) {
				hodima_admin_notice( 'تنظیمات ذخیره شد.' );
			}
			?>

			<form method="post" action="options.php" class="hd-body">
				<?php settings_fields( self::GROUP ); ?>

				<?php
				$total   = count( self::SWITCHES );
				$enabled = count( array_filter( array_keys( self::SWITCHES ), static fn( string $o ): bool => self::enabled( $o ) ) );
				$all_on  = $enabled === $total;
				?>
				<section class="hl-status<?php echo $all_on ? '' : ' is-warn'; ?>" aria-label="خلاصه بخش‌ها">
					<?php echo hodima_admin_icon( $all_on ? 'dashicons-yes-alt' : 'dashicons-info', 'hl-status__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی hodima_admin_icon از پیش امن است ?>
					<div class="hl-status__body">
						<h2 class="hl-status__title"><?php echo esc_html( $all_on ? sprintf( 'همه %s بخش روشن‌اند', number_format_i18n( $total ) ) : sprintf( '%1$s از %2$s بخش روشن است', number_format_i18n( $enabled ), number_format_i18n( $total ) ) ); ?></h2>
						<p class="hl-status__text">هر بخش جدا روشن و خاموش می‌شود؛ تغییر با دکمه ذخیره اعمال می‌شود.</p>
					</div>
					<span class="hd-pill <?php echo $all_on ? 'hd-pill--ok' : 'hd-pill--warn'; ?>"><?php echo esc_html( $all_on ? 'همه روشن' : 'برخی خاموش' ); ?></span>
				</section>

				<section class="hl-list" aria-label="بخش‌های بومی‌سازی">
					<?php foreach ( self::SWITCHES as $option => [ $title, $desc, $icon ] ) : ?>
						<div class="hl-item<?php echo self::enabled( $option ) ? '' : ' is-off'; ?>">
							<?php echo hodima_admin_icon( $icon, 'hl-item__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی hodima_admin_icon از پیش امن است ?>
							<div class="hl-item__text">
								<h3 class="hl-item__title"><label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $title ); ?></label></h3>
								<p class="hl-item__desc"><?php echo esc_html( $desc ); ?></p>
							</div>
							<input type="checkbox" class="hd-switch" role="switch" id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>" value="yes" <?php checked( self::enabled( $option ) ); ?> />
						</div>
					<?php endforeach; ?>
				</section>

				<div class="hd-actions">
					<button type="submit" name="submit" class="button button-primary">ذخیره تنظیمات</button>
				</div>
			</form>
		</div>
		<?php
	}
}

// ماژول خودش را راه‌اندازی می‌کند (مثل بقیه ماژول‌ها)؛ قبلا از functions.php قالب صدا زده می‌شد
Hodima_Localizer_WC::get_instance();
