<?php
/**
 * Hodima Core — سیستم طراحی مشترک پیشخوان («ابزارهای هدیما»)
 * Path: plugins/hodima-core/includes/admin-ui.php
 *
 * پیش از این هر صفحه افزونه‌ها هدر، ناوبری، رنگ، فونت و عرض خودش را داشت:
 *   - نوار صفحه‌های اسکیما *بالای* هدر چاپ می‌شد؛
 *   - init.css اسکیما روی هر صفحه‌ای که «hodima» در نامش بود (اسلایدر، استوری،
 *     بومی‌سازی و...) و حتی ویرایش نوشته لود می‌شد و با :root و قوانین سراسری
 *     .form-table / .description ظاهر آن صفحه‌ها را به هم می‌ریخت؛
 *   - بعضی صفحه‌ها فوتر داشتند و بعضی نه؛ فونت‌ها ثابت (Tahoma، Arial، Vazirmatn)
 *     نوشته شده بودند و ایموجی‌ها با اسکریپت ایموجی وردپرس از CDN خارجی
 *     (s.w.org) به تصویر تبدیل می‌شدند.
 *
 * این فایل یک بار همه این‌ها را یکدست می‌کند:
 *   hodima_admin_header()   هدر + تب‌ها (زیر هدر) + نقطه قرار گرفتن اعلان‌ها
 *   hodima_admin_icon()     آیکون داخلی (Dashicons خود وردپرس، بدون لینک خارجی)
 *   فوتر یکسان               برای همه صفحه‌های هدیما (admin_footer_text)
 *   admin-ui.css            توکن‌های پالت، عرض ۹۵٪ دسکتاپ و واکنش‌گرا؛ فونت فقط
 *                           از قالب ارث می‌برد (font-family در هیچ‌جا تعریف نمی‌شود)
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_ADMIN_UI_HANDLE = 'hodima-admin-ui';

/** نام نمایشی پنل در منو و فوتر. */
const HODIMA_ADMIN_BRAND = 'ابزارهای هدیما';

/**
 * پست‌تایپ‌هایی که صفحه فهرست/ویرایششان جزو پیشخوان هدیما است.
 *
 * @return list<string>
 */
function hodima_admin_post_types(): array {
	return (array) apply_filters( 'hodima_admin_post_types', [ 'hd_notification', 'video', 'hodima_phone_lead' ] );
}

/**
 * آیا صفحه فعلی پیشخوان یکی از صفحه‌های هدیما است؟
 * (همه صفحه‌های افزونه‌ها با page=hodima… شروع می‌شوند؛ تنظیمات قالب هم.)
 */
function hodima_admin_is_screen(): bool {

	static $is = null;

	if ( null !== $is ) {
		return $is;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط تشخیص صفحه
	$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	// phpcs:enable

	// صفحه ویرایش (post.php?post=…) پارامتر post_type ندارد؛ از screen خوانده می‌شود.
	// تا screen ساخته نشده، نتیجه کش نمی‌شود.
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( '' === $post_type && $screen ) {
		$post_type = (string) $screen->post_type;
	}

	$result = str_starts_with( $page, 'hodima' )
		|| ( '' !== $post_type && in_array( $post_type, hodima_admin_post_types(), true ) );

	$result = (bool) apply_filters( 'hodima_admin_is_screen', $result, $page, $post_type );

	if ( $screen ) {
		$is = $result;
	}

	return $result;
}

// توابع عمومی این فایل با function_exists گارد شده‌اند: SEO/Commerce/Media وقتی Core
// فعال نیست فالبک همین توابع را تعریف می‌کنند (inc/admin-ui-fallback.php) و بدون گارد،
// فعال‌سازی دوباره Core در همان درخواست با «Cannot redeclare» شکست می‌خورد.

/** آیکون داخلی: Dashicons همراه وردپرس (فونت محلی، هیچ درخواست خارجی). */
if ( ! function_exists( 'hodima_admin_icon' ) ) {
	function hodima_admin_icon( string $icon, string $class = '' ): string {
		$icon = str_starts_with( $icon, 'dashicons-' ) ? $icon : 'dashicons-' . ( '' !== $icon ? $icon : 'admin-generic' );
		return sprintf( '<span class="dashicons %1$s %2$s" aria-hidden="true"></span>', esc_attr( $icon ), esc_attr( $class ) );
	}
}

/**
 * هدر یکسان صفحه‌های هدیما.
 *
 * ترتیب خروجی: هدر ← تب‌ها ← <hr class="wp-header-end">
 * وردپرس (common.js) همه اعلان‌ها را بعد از wp-header-end جابه‌جا می‌کند؛ پس
 * اعلان‌ها دیگر داخل هدر رنگی نمی‌افتند و تب‌ها همیشه درست زیر هدر هستند.
 *
 * @param array{
 *   title:string,
 *   description?:string,
 *   icon?:string,
 *   tabs?:array<string, array{label:string, url:string, icon?:string}>,
 *   current?:string,
 *   badge?:string,
 *   actions?:string,
 *   tabs_label?:string
 * } $args
 */
if ( ! function_exists( 'hodima_admin_header' ) ) {
	function hodima_admin_header( array $args ): void {

		$title       = (string) ( $args['title'] ?? '' );
		$description = (string) ( $args['description'] ?? '' );
		$icon        = (string) ( $args['icon'] ?? 'dashicons-admin-generic' );
		$tabs        = (array) ( $args['tabs'] ?? [] );
		$current     = (string) ( $args['current'] ?? '' );
		$badge       = (string) ( $args['badge'] ?? HODIMA_ADMIN_BRAND );
		$actions     = (string) ( $args['actions'] ?? '' );
		?>
		<header class="hd-header">
			<?php echo hodima_admin_icon( $icon, 'hd-header__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			<div class="hd-header__body">
				<?php if ( '' !== $badge ) : ?>
					<span class="hd-header__badge"><?php echo esc_html( $badge ); ?></span>
				<?php endif; ?>
				<h1 class="hd-header__title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $description ) : ?>
					<p class="hd-header__desc"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $actions ) : ?>
				<div class="hd-header__actions"><?php echo wp_kses_post( $actions ); ?></div>
			<?php endif; ?>
		</header>
		<?php
		if ( $tabs ) {
			hodima_admin_tabs( $tabs, $current, (string) ( $args['tabs_label'] ?? 'بخش‌ها' ) );
		}
		echo '<hr class="wp-header-end">';
	}
}

/**
 * نوار تب‌ها (ناوبری بین صفحه‌ها یا بخش‌های یک صفحه).
 *
 * @param array<string, array{label:string, url:string, icon?:string}> $tabs
 */
if ( ! function_exists( 'hodima_admin_tabs' ) ) {
	function hodima_admin_tabs( array $tabs, string $current, string $label = 'بخش‌ها' ): void {
		?>
		<nav class="hd-tabs" aria-label="<?php echo esc_attr( $label ); ?>">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<a class="hd-tabs__item" href="<?php echo esc_url( $tab['url'] ); ?>"<?php echo (string) $key === $current ? ' aria-current="page"' : ''; ?>>
					<?php
					if ( ! empty( $tab['icon'] ) ) {
						echo hodima_admin_icon( $tab['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					echo esc_html( $tab['label'] );
					?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}
}

/** شروع صفحه: wrap با کلاس سیستم طراحی + هدر. */
if ( ! function_exists( 'hodima_admin_page_open' ) ) {
	function hodima_admin_page_open( array $args, string $class = '' ): void {
		printf( '<div class="wrap hd-wrap %s">', esc_attr( $class ) );
		hodima_admin_header( $args );
	}
}

if ( ! function_exists( 'hodima_admin_page_close' ) ) {
	function hodima_admin_page_close(): void {
		echo '</div>';
	}
}

/* =========================================================================
 * پیام‌های نتیجه (ذخیره شد / خطا)
 * -------------------------------------------------------------------------
 * پیش از این هر صفحه پیام خودش را داشت: بعضی بدون دکمه بستن (ریدایرکت‌ها)،
 * بعضی با دکمه‌ای که کار نمی‌کرد (اسلایدر: hidden در برابر display:flex)،
 * بعضی اصلا چیزی نشان نمی‌دادند (تنظیمات قالب، ایندکس گوگل که پیام را
 * بالای صفحه نشان می‌داد و ۱.۵ ثانیه بعد صفحه را از نو بارگذاری می‌کرد).
 * حالا همه یک نشانه‌گذاری استاندارد وردپرس دارند: زیر هدر و تب‌ها جابه‌جا
 * می‌شود و دکمه X خود وردپرس (common.js) آن را می‌بندد.
 * ========================================================================= */

/** نوع مجاز پیام. */
function hodima_admin_notice_type( string $type ): string {
	return in_array( $type, [ 'success', 'error', 'warning', 'info' ], true ) ? $type : 'info';
}

/**
 * نشانه‌گذاری یک پیام قابل بستن. متن با wp_kses_post (strong، code، a و...) پاک می‌شود.
 */
if ( ! function_exists( 'hodima_admin_notice' ) ) {
	function hodima_admin_notice( string $message, string $type = 'success', bool $echo = true ): string {
		$html = sprintf(
			'<div class="notice notice-%1$s is-dismissible hd-notice" role="%2$s"><p>%3$s</p></div>',
			esc_attr( hodima_admin_notice_type( $type ) ),
			'error' === $type ? 'alert' : 'status',
			wp_kses_post( $message )
		);
		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
		}
		return $html;
	}
}

/** کلید ذخیره پیام‌های درخواست بعدی (برای هر کاربر جدا). */
function hodima_admin_flash_key(): string {
	return 'hodima_admin_flash_' . get_current_user_id();
}

/**
 * پیامی برای بارگذاری بعدی صفحه (الگوی Post/Redirect/Get): بعد از ذخیره و
 * ریدایرکت، یا بعد از ذخیره AJAX و بارگذاری مجدد، زیر هدر نمایش داده می‌شود.
 */
if ( ! function_exists( 'hodima_admin_flash' ) ) {
	function hodima_admin_flash( string $message, string $type = 'success' ): void {
		$queue   = get_transient( hodima_admin_flash_key() );
		$queue   = is_array( $queue ) ? $queue : [];
		$queue[] = [ hodima_admin_notice_type( $type ), $message ];
		set_transient( hodima_admin_flash_key(), array_slice( $queue, -5 ), 5 * MINUTE_IN_SECONDS );
	}
}

add_action( 'admin_notices', static function (): void {
	$queue = get_transient( hodima_admin_flash_key() );
	if ( ! is_array( $queue ) || ! $queue ) {
		return;
	}
	delete_transient( hodima_admin_flash_key() );
	foreach ( $queue as [ $type, $message ] ) {
		hodima_admin_notice( (string) $message, (string) $type );
	}
} );

/** والد منوی مشترک: زیرمنوهای افزونه‌ها زیر «ابزارهای هدیما» می‌روند. */
if ( ! function_exists( 'hodima_admin_menu_parent' ) ) {
	function hodima_admin_menu_parent(): string {
		return \Hodima\Core\Admin\HUB_SLUG;
	}
}

/* =========================================================================
 * دارایی‌ها، کلاس body و فوتر
 * ========================================================================= */

add_action( 'admin_enqueue_scripts', static function (): void {

	$file = HODIMA_CORE_PLUGIN_DIR . '/assets/admin-ui.css';

	wp_register_style(
		HODIMA_ADMIN_UI_HANDLE,
		HODIMA_CORE_PLUGIN_URL . '/assets/admin-ui.css',
		[ 'dashicons' ],
		is_file( $file ) ? (string) filemtime( $file ) : HODIMA_CORE_PLUGIN_VERSION
	);

	if ( hodima_admin_is_screen() ) {
		wp_enqueue_style( HODIMA_ADMIN_UI_HANDLE );
	}
}, 5 );

add_filter( 'admin_body_class', static function ( string $classes ): string {
	return hodima_admin_is_screen() ? $classes . ' hodima-admin ' : $classes;
} );

/*
 * اسکریپت ایموجی وردپرس در پیشخوان: هر ایموجی را با تصویر SVG از CDN خارجی
 * (s.w.org) جایگزین می‌کرد. ایموجی‌های پنل‌های هدیما با آیکون داخلی
 * جایگزین شده‌اند و مرورگرهای امروزی ایموجی را خودشان نمایش می‌دهند؛ پس
 * این درخواست خارجی و اسکریپت درون‌خطی از کل پیشخوان حذف می‌شود.
 */
add_action( 'admin_init', static function (): void {
	if ( ! apply_filters( 'hodima_admin_disable_emoji', true ) ) {
		return;
	}
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
} );

/** فوتر یکسان همه صفحه‌های هدیما (سمت برند و پیوندها). */
add_filter( 'admin_footer_text', static function ( $text ) {

	if ( ! hodima_admin_is_screen() ) {
		return $text;
	}

	$links = [ 'وضعیت' => admin_url( 'admin.php?page=' . \Hodima\Core\Admin\HUB_SLUG ) ];

	$first = array_key_first( \Hodima\Core\Modules::plugins() );
	if ( null !== $first ) {
		$links['ماژول‌ها'] = \Hodima\Core\Admin\modules_page_url( (string) $first );
	}

	if ( function_exists( 'hodima_settings' ) ) {
		$links['تنظیمات هدیما'] = admin_url( 'themes.php?page=hodima-settings' );
	}

	$items = '';
	foreach ( $links as $label => $url ) {
		$items .= sprintf( '<a class="hd-footer__link" href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
	}

	return sprintf(
		'<span class="hd-footer"><span class="hd-footer__brand"><span class="hd-footer__mark" aria-hidden="true">ه</span>%1$s</span><span class="hd-footer__links">%2$s</span></span>',
		esc_html( HODIMA_ADMIN_BRAND ),
		$items
	);
}, 99 );

/** فوتر یکسان (سمت نسخه‌ها). */
add_filter( 'update_footer', static function ( $text ) {

	if ( ! hodima_admin_is_screen() ) {
		return $text;
	}

	global $wp_version;

	return sprintf(
		'<span class="hd-footer__meta">Hodima Core %1$s · وردپرس %2$s · PHP %3$s</span>',
		esc_html( HODIMA_CORE_PLUGIN_VERSION ),
		esc_html( (string) $wp_version ),
		esc_html( PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION )
	);
}, 99 );
