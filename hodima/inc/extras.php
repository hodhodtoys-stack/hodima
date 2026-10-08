<?php
/**
 * امکانات کاربری و ابزارها: بازگشت به بالا، نوار پایین موبایل، کد سفارشی، پشتیبان تنظیمات
 * Path: hodima/inc/extras.php
 *
 * از 2.9.8 (تب‌های «امکانات کاربری»، «کد سفارشی» و «پشتیبان تنظیمات»).
 *   - دکمه و نوار پیش‌فرض خاموش‌اند: ظاهر سایت تا روشن کردن در پیشخوان همان است.
 *     CSS هر دو در assets/css/extras.css (بخشی از CSS مشترک؛ بدون عنصر بی‌اثر) و
 *     اسکریپت assets/js/extras.js فقط وقتی یکی روشن است.
 *   - نوار موبایل بدون جاوااسکریپت هم کار می‌کند (جستجو و پشتیبانی لینک به هدرند).
 *   - شمار سبد عمدا نیست: صفحه‌ها در کش لایت‌اسپید ذخیره می‌شوند و عدد کهنه نشان می‌داد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * ۱. بازگشت به بالا و نوار پایین موبایل
 * ========================================================================= */

/** آیکون‌های خطی (۲۴×۲۴، رنگ از متن). */
function hodima_extras_icon( string $name ): string {

	$paths = [
		'home'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h5v-6h4v6h5V9.5"/>',
		'shop'    => '<path d="M6 7h12l1 14H5L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'cart'    => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.4a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.8L21 7H6"/>',
		'support' => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>',
		'up'      => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
	];

	return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * موارد نوار پایین موبایل؛ «خانه» همیشه.
 * action: رفتار JS (search: کادر جستجوی هدر، support: پنجره پشتیبانی)؛ بدون JS لینک به همان بخش هدر.
 *
 * @return list<array{key:string, label:string, url:string, current:bool, action:string}>
 */
function hodima_mobile_nav_items(): array {

	$wc    = function_exists( 'hodima_wc_active' ) && hodima_wc_active();
	$items = [ [ 'key' => 'home', 'label' => 'خانه', 'url' => home_url( '/' ), 'current' => is_front_page(), 'action' => '' ] ];

	// هر تابع ووکامرس جدا بررسی می‌شود (کلاس بدون توابع در بارگذاری ناقص = Fatal)
	if ( $wc && hodima_setting( 'mobile_nav_shop' ) && function_exists( 'wc_get_page_permalink' ) && function_exists( 'is_shop' ) ) {
		$items[] = [ 'key' => 'shop', 'label' => 'فروشگاه', 'url' => (string) wc_get_page_permalink( 'shop' ), 'current' => is_shop() || is_product_taxonomy(), 'action' => '' ];
	}

	if ( hodima_setting( 'mobile_nav_search' ) && hodima_setting( 'header_search' ) ) {
		$items[] = [ 'key' => 'search', 'label' => 'جستجو', 'url' => '#mainHeader', 'current' => is_search(), 'action' => 'search' ];
	}

	if ( $wc && hodima_setting( 'mobile_nav_cart' ) && function_exists( 'wc_get_cart_url' ) && function_exists( 'is_cart' ) ) {
		$items[] = [ 'key' => 'cart', 'label' => 'سبد خرید', 'url' => (string) wc_get_cart_url(), 'current' => is_cart(), 'action' => '' ];
	}

	if ( hodima_setting( 'mobile_nav_support' ) && hodima_contact_channels() ) {
		$items[] = [ 'key' => 'support', 'label' => 'پشتیبانی', 'url' => '#supportTrigger', 'current' => false, 'action' => 'support' ];
	}

	return (array) apply_filters( 'hodima_mobile_nav_items', $items );
}

/** نوار موبایل روشن است و دست‌کم دو مورد دارد (فقط «خانه» نوار نمی‌سازد). */
function hodima_mobile_nav_enabled(): bool {
	return (bool) hodima_setting( 'mobile_nav' ) && count( hodima_mobile_nav_items() ) > 1;
}

add_filter( 'body_class', 'hodima_extras_body_class' );

/**
 * فاصله پایین صفحه برای نوار (فوتر زیر نوار پنهان نماند).
 *
 * @param mixed $classes
 * @return mixed
 */
function hodima_extras_body_class( mixed $classes ): mixed {

	if ( is_array( $classes ) && hodima_mobile_nav_enabled() ) {
		$classes[] = 'has-hodima-mobile-nav';
	}

	return $classes;
}

add_action( 'wp_enqueue_scripts', 'hodima_extras_enqueue', 25 );

function hodima_extras_enqueue(): void {
	if ( hodima_setting( 'back_to_top' ) || hodima_mobile_nav_enabled() ) {
		hodima_enqueue_asset( 'hodima-extras-js', 'assets/js/extras.js', [], [ 'in_footer' => true, 'strategy' => 'defer' ] );
	}
}

add_action( 'wp_footer', 'hodima_extras_render', 5 );

function hodima_extras_render(): void {

	if ( hodima_setting( 'back_to_top' ) ) {
		printf(
			'<button type="button" class="hodima-to-top" data-hodima-to-top aria-label="%s">%s</button>' . "\n",
			esc_attr( 'بازگشت به بالای صفحه' ),
			hodima_extras_icon( 'up' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت
		);
	}

	if ( ! hodima_mobile_nav_enabled() ) {
		return;
	}
	?>
	<nav class="hodima-mobile-nav" aria-label="منوی پایین">
		<ul>
			<?php foreach ( hodima_mobile_nav_items() as $item ) : ?>
				<li>
					<a
						class="hodima-mobile-nav__item hodima-mobile-nav__item--<?php echo esc_attr( $item['key'] ); ?>"
						href="<?php echo esc_url( $item['url'] ); ?>"
						<?php echo $item['current'] ? 'aria-current="page"' : ''; ?>
						<?php echo '' !== $item['action'] ? 'data-hodima-nav-action="' . esc_attr( $item['action'] ) . '"' : ''; ?>
					>
						<?php echo hodima_extras_icon( $item['key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت ?>
						<span><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}

/* =========================================================================
 * ۲. کد سفارشی (head و پایان صفحه) — ذخیره فقط با unfiltered_html (theme-settings.php)
 * ========================================================================= */

add_action( 'wp_head', 'hodima_extras_code_head', 99 );
add_action( 'wp_footer', 'hodima_extras_code_footer', 99 );

function hodima_extras_code_head(): void {
	$code = (string) hodima_setting( 'code_head' );
	if ( '' !== $code ) {
		echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- کد خام مدیر (فقط unfiltered_html ذخیره می‌کند)
	}
}

function hodima_extras_code_footer(): void {
	$code = (string) hodima_setting( 'code_body_end' );
	if ( '' !== $code ) {
		echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- کد خام مدیر (فقط unfiltered_html ذخیره می‌کند)
	}
}

/* =========================================================================
 * ۳. پشتیبان تنظیمات: دریافت (JSON) و بازگردانی
 * ========================================================================= */

/** نشانه فایل پشتیبان (فایل دیگری با پسوند json پذیرفته نشود). */
const HODIMA_SETTINGS_BACKUP_GENERATOR = 'hodima-theme-settings';

/** بیشترین اندازه فایل بازگردانی (بایت). */
const HODIMA_SETTINGS_BACKUP_MAX = 1048576;

add_action( 'admin_post_hodima_settings_export', 'hodima_settings_export' );

function hodima_settings_export(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', '', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_settings_export' );

	$data = [
		'generator'     => HODIMA_SETTINGS_BACKUP_GENERATOR,
		'format'        => 1,
		'theme_version' => (string) wp_get_theme( get_template() )->get( 'Version' ),
		'site'          => home_url( '/' ),
		'exported_at'   => gmdate( 'c' ),
		'settings'      => get_option( HODIMA_SETTINGS_OPTION, [] ),
		'home_layout'   => defined( 'HODIMA_HOME_LAYOUT_OPTION' ) ? get_option( HODIMA_HOME_LAYOUT_OPTION, null ) : null,
	];

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="hodima-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- فایل JSON دانلودی
	exit;
}

add_action( 'admin_post_hodima_settings_import', 'hodima_settings_import' );

function hodima_settings_import(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', '', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_settings_import' );

	$back = hodima_settings_url( [ 'tab' => 'backup', 'settings-updated' => 'true' ] );
	$data = hodima_settings_backup_read( $_FILES['hodima_settings_file'] ?? null ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- فایل در تابع بررسی می‌شود

	if ( is_string( $data ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, 'import_failed', $data );
	} else {
		hodima_settings_backup_apply( $data );
		add_settings_error( HODIMA_SETTINGS_OPTION, 'imported', 'تنظیمات از فایل پشتیبان بازگردانی شد.', 'success' );
	}

	// مثل options.php: پیام‌ها بعد از بازگشت به صفحه با settings_errors() نمایش داده می‌شوند
	set_transient( 'settings_errors', get_settings_errors(), 30 );
	wp_safe_redirect( $back );
	exit;
}

/**
 * فایل آپلودی ← داده پشتیبان، یا متن خطا.
 *
 * @return array{settings: array<string, mixed>, home_layout: mixed}|string
 */
function hodima_settings_backup_read( mixed $file ): array|string {

	if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_string( $file['tmp_name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return 'فایلی انتخاب نشد یا آپلود کامل نشد.';
	}

	if ( (int) ( $file['size'] ?? 0 ) > HODIMA_SETTINGS_BACKUP_MAX ) {
		return 'فایل بزرگ‌تر از حد مجاز (۱ مگابایت) است؛ فایل پشتیبان تنظیمات هدیما معمولا چند کیلوبایت است.';
	}

	$json = json_decode( (string) file_get_contents( $file['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- فایل موقت آپلود

	if ( ! is_array( $json ) || HODIMA_SETTINGS_BACKUP_GENERATOR !== ( $json['generator'] ?? '' ) || ! is_array( $json['settings'] ?? null ) ) {
		return 'این فایل، فایل پشتیبان تنظیمات قالب هدیما نیست (از دکمه «دریافت فایل تنظیمات» ساخته می‌شود). چیزی تغییر نکرد.';
	}

	return [ 'settings' => $json['settings'], 'home_layout' => $json['home_layout'] ?? null ];
}

/**
 * ذخیره داده پشتیبان با همان پاک‌سازی فرم تنظیمات (کلید ناشناخته کنار گذاشته
 * می‌شود؛ تصویر/فونتی که در این سایت نیست خالی می‌ماند؛ کد سفارشی فقط با unfiltered_html).
 *
 * @param array{settings: array<string, mixed>, home_layout: mixed} $data
 */
function hodima_settings_backup_apply( array $data ): void {

	$clean = hodima_settings_sanitize( $data['settings'] );

	// پاک‌سازی همین‌جا انجام شد؛ callback ثبت‌شده دوباره اجرا نشود (پیام‌های تکراری)
	remove_filter( 'sanitize_option_' . HODIMA_SETTINGS_OPTION, 'hodima_settings_sanitize' );
	update_option( HODIMA_SETTINGS_OPTION, $clean );

	if ( is_array( $data['home_layout'] ) && array_is_list( $data['home_layout'] ) && defined( 'HODIMA_HOME_LAYOUT_OPTION' ) && function_exists( 'hodima_home_sanitize_layout' ) ) {
		remove_filter( 'sanitize_option_' . HODIMA_HOME_LAYOUT_OPTION, 'hodima_home_sanitize_layout' );
		update_option( HODIMA_HOME_LAYOUT_OPTION, hodima_home_sanitize_layout( $data['home_layout'] ) );
	}
}

/** قاب تب «پشتیبان تنظیمات» (داخل فرم اصلی؛ ورودی‌ها با form= به فرم جدای بازگردانی وصل‌اند). */
function hodima_settings_backup_panel(): void {

	$export = wp_nonce_url( admin_url( 'admin-post.php?action=hodima_settings_export' ), 'hodima_settings_export' );
	?>
	<div class="hodima-backup">
		<div class="hodima-backup__item">
			<h4 class="hodima-backup__title">دریافت فایل تنظیمات</h4>
			<p class="hodima-field__help">همه تنظیمات ذخیره‌شده این صفحه در یک فایل JSON (تغییرهای ذخیره‌نشده در فایل نیستند).</p>
			<a class="button hodima-backup__button" href="<?php echo esc_url( $export ); ?>">
				<span class="dashicons dashicons-download" aria-hidden="true"></span> دریافت فایل تنظیمات
			</a>
		</div>
		<div class="hodima-backup__item">
			<h4 class="hodima-backup__title"><label for="hodima-settings-file">بازگردانی از فایل</label></h4>
			<p class="hodima-field__help" id="hodima-settings-file-help">همه تنظیمات فعلی با فایل جایگزین می‌شوند. بهتر است اول از تنظیمات فعلی فایل بگیرید.</p>
			<input type="file" id="hodima-settings-file" name="hodima_settings_file" accept=".json,application/json" form="hodima-import-form" aria-describedby="hodima-settings-file-help" required>
			<button type="submit" class="button hodima-backup__button" form="hodima-import-form">
				<span class="dashicons dashicons-upload" aria-hidden="true"></span> بازگردانی
			</button>
		</div>
	</div>
	<?php
}

/** فرم جدای بازگردانی (بیرون از فرم تنظیمات؛ فرم تودرتو در HTML مجاز نیست). */
function hodima_settings_backup_form(): void {
	?>
	<form id="hodima-import-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" hidden data-hodima-import-form>
		<input type="hidden" name="action" value="hodima_settings_import">
		<?php wp_nonce_field( 'hodima_settings_import' ); ?>
	</form>
	<?php
}
