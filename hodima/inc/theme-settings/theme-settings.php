<?php
/**
 * Theme Settings — برند، اطلاعات تماس، فوتر و Google Analytics
 * Path: inc/theme-settings/theme-settings.php
 *
 * پیش از این، لوگو (با آدرس کامل دامنه سایت اصلی)، شماره تلفن، لینک
 * روبیکا و تلگرام، آدرس، نماد اعتماد، متن‌های فوتر و شناسه Google Analytics
 * مستقیم داخل header.php، footer.php و functions.php نوشته شده بودند:
 *   - قالب روی دامنه یا استیجینگ دیگری هنوز تصاویر را از سایت اصلی می‌کشید؛
 *   - آمار استیجینگ و بازدید مدیران با آمار واقعی سایت قاطی می‌شد؛
 *   - هر تغییر ساده (مثلا شماره تماس) ویرایش کد لازم داشت.
 *
 * حالا همه از «نمایش ← تنظیمات هدیما» در پیشخوان خوانده می‌شوند.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HODIMA_SETTINGS_OPTION = 'hodima_theme_settings';
const HODIMA_SETTINGS_PAGE   = 'hodima-settings';

/* =========================================================================
 * ۱. تعریف فیلدها و خواندن تنظیمات
 * ========================================================================= */

/**
 * تعریف همه فیلدها: نوع، مقدار پیش‌فرض، برچسب و بخش.
 *
 * @return array<string, array{section:string, type:string, label:string, default:mixed, help?:string, placeholder?:string}>
 */
function hodima_settings_fields(): array {
	return [
		// ── برند ────────────────────────────────────────────────────
		'logo_id'          => [ 'section' => 'brand', 'type' => 'image', 'label' => 'لوگو', 'default' => 0, 'help' => 'بهتر است نسخه SVG یا WebP با پس‌زمینه شفاف باشد. اگر خالی بماند نام سایت نمایش داده می‌شود.' ],
		'logo_invert'      => [ 'section' => 'brand', 'type' => 'toggle', 'label' => 'نمایش لوگو به رنگ سفید روی هدر', 'default' => true, 'help' => 'برای لوگوی رنگی روی پس‌زمینه تیره هدر.' ],

		// ── اطلاعات تماس ───────────────────────────────────────────
		'phone'            => [ 'section' => 'contact', 'type' => 'tel', 'label' => 'شماره تماس', 'default' => '', 'placeholder' => '09120000000', 'help' => 'دکمه «تماس تلفنی» پنجره پشتیبانی.' ],
		'phone_2'          => [ 'section' => 'contact', 'type' => 'tel', 'label' => 'شماره تماس دوم (اختیاری)', 'default' => '', 'placeholder' => '02100000000', 'help' => 'مثلا تلفن ثابت؛ در نسخه ماشین‌خوان (llms.txt) کنار شماره اصلی می‌آید.' ],
		'whatsapp_url'     => [ 'section' => 'contact', 'type' => 'url', 'label' => 'لینک واتس‌اپ', 'default' => '', 'placeholder' => 'https://wa.me/989120000000' ],
		'telegram_url'     => [ 'section' => 'contact', 'type' => 'url', 'label' => 'لینک تلگرام', 'default' => '', 'placeholder' => 'https://t.me/username' ],
		'rubika_url'       => [ 'section' => 'contact', 'type' => 'url', 'label' => 'لینک روبیکا', 'default' => '', 'placeholder' => 'https://rubika.ir/username' ],
		'address'          => [ 'section' => 'contact', 'type' => 'text', 'label' => 'آدرس', 'default' => '' ],
		'map_url'          => [ 'section' => 'contact', 'type' => 'url', 'label' => 'لینک نقشه آدرس', 'default' => '', 'placeholder' => 'https://maps.app.goo.gl/...' ],

		// ── فوتر ────────────────────────────────────────────────────
		'about_title'      => [ 'section' => 'footer', 'type' => 'text', 'label' => 'عنوان ستون درباره ما', 'default' => 'درباره ما' ],
		'about_text'       => [ 'section' => 'footer', 'type' => 'textarea', 'label' => 'متن درباره ما', 'default' => '' ],
		'guide_title'      => [ 'section' => 'footer', 'type' => 'text', 'label' => 'عنوان ستون راهنمای خرید', 'default' => 'راهنمای خرید' ],
		'guide_text'       => [ 'section' => 'footer', 'type' => 'textarea', 'label' => 'متن راهنمای خرید', 'default' => '', 'help' => 'آدرس بخش «اطلاعات تماس» زیر همین متن نمایش داده می‌شود.' ],
		'trust_title'      => [ 'section' => 'footer', 'type' => 'text', 'label' => 'عنوان ستون نماد اعتماد', 'default' => 'نماد اعتماد' ],
		'trust_image_id'   => [ 'section' => 'footer', 'type' => 'image', 'label' => 'تصویر نماد اعتماد', 'default' => 0 ],
		'trust_url'        => [ 'section' => 'footer', 'type' => 'url', 'label' => 'لینک نماد اعتماد', 'default' => '', 'help' => 'آدرس صفحه اعتبارسنجی نماد (مثلا لینک اینماد).' ],
		'consult_title'    => [ 'section' => 'footer', 'type' => 'text', 'label' => 'عنوان ستون فرم مشاوره', 'default' => 'مشاوره خرید' ],
		'copyright'        => [ 'section' => 'footer', 'type' => 'text', 'label' => 'متن کپی‌رایت', 'default' => '', 'help' => 'اگر خالی بماند نام سایت نمایش داده می‌شود.' ],

		// ── Google Analytics ───────────────────────────────────────
		'ga_id'            => [ 'section' => 'analytics', 'type' => 'ga', 'label' => 'شناسه Google Analytics 4', 'default' => '', 'placeholder' => 'G-XXXXXXXXXX' ],
		'ga_skip_editors'  => [ 'section' => 'analytics', 'type' => 'toggle', 'label' => 'بازدید مدیران و نویسندگان ثبت نشود', 'default' => true ],
		'ga_production'    => [ 'section' => 'analytics', 'type' => 'toggle', 'label' => 'فقط روی سایت اصلی (Production) فعال باشد', 'default' => true, 'help' => 'روی استیجینگ یا لوکال (WP_ENVIRONMENT_TYPE) کد آمار چاپ نمی‌شود تا آمار واقعی آلوده نشود.' ],
	];
}

/** @return array<string, array{title:string, description:string}> */
function hodima_settings_sections(): array {
	return [
		'brand'     => [ 'title' => 'برند', 'description' => 'لوگوی هدر سایت.' ],
		'contact'   => [ 'title' => 'اطلاعات تماس', 'description' => 'در پنجره «پشتیبانی» هدر و فوتر نمایش داده می‌شود. هر گزینه خالی، نمایش داده نمی‌شود.' ],
		'footer'    => [ 'title' => 'فوتر', 'description' => 'ستون‌های فوتر. ستونی که محتوا نداشته باشد نمایش داده نمی‌شود.' ],
		'analytics' => [ 'title' => 'Google Analytics', 'description' => 'کد آمار GA4 با بارگذاری async و بدون مسدود کردن رندر صفحه اضافه می‌شود.' ],
	];
}

/** همه تنظیمات، ادغام‌شده با پیش‌فرض‌ها. */
function hodima_settings(): array {

	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$defaults = array_map( static fn( array $field ): mixed => $field['default'], hodima_settings_fields() );
	$stored   = get_option( HODIMA_SETTINGS_OPTION, [] );
	$settings = array_merge( $defaults, is_array( $stored ) ? array_intersect_key( $stored, $defaults ) : [] );

	// عنوان خالی (مثلا عنوان ستون فوتر) به پیش‌فرض برمی‌گردد تا تیتر خالی چاپ نشود
	foreach ( $defaults as $key => $default ) {
		if ( is_string( $default ) && '' !== $default && '' === trim( (string) $settings[ $key ] ) ) {
			$settings[ $key ] = $default;
		}
	}

	return $cache = $settings;
}

/** یک مقدار از تنظیمات. */
function hodima_setting( string $key ): mixed {
	return hodima_settings()[ $key ] ?? null;
}

/* =========================================================================
 * ۲. ثبت تنظیمات و صفحه پیشخوان
 * ========================================================================= */

add_action( 'admin_init', static function (): void {
	register_setting( 'hodima_theme_settings_group', HODIMA_SETTINGS_OPTION, [
		'type'              => 'array',
		'sanitize_callback' => 'hodima_settings_sanitize',
		'default'           => [],
		'show_in_rest'      => false,
	] );
} );

add_action( 'admin_menu', static function (): void {
	add_theme_page(
		'تنظیمات قالب هدیما',
		'تنظیمات هدیما',
		'manage_options',
		HODIMA_SETTINGS_PAGE,
		'hodima_settings_render_page'
	);
} );

/**
 * پاک‌سازی و اعتبارسنجی همه فیلدها.
 * مقدار نامعتبر رد و به کاربر اطلاع داده می‌شود؛ مقدار قبلی حفظ نمی‌شود
 * تا خطا بی‌صدا پنهان نماند.
 *
 * @param mixed $input
 */
function hodima_settings_sanitize( $input ): array {

	$input  = is_array( $input ) ? wp_unslash( $input ) : [];
	$clean  = [];

	foreach ( hodima_settings_fields() as $key => $field ) {

		$raw = $input[ $key ] ?? null;

		$clean[ $key ] = match ( $field['type'] ) {
			'toggle'   => ! empty( $raw ),
			'image'    => hodima_settings_sanitize_image( $raw ),
			'url'      => hodima_settings_sanitize_url( $key, $raw ),
			'tel'      => hodima_settings_sanitize_phone( $key, $raw ),
			'ga'       => hodima_settings_sanitize_ga( $raw ),
			'textarea' => sanitize_textarea_field( is_string( $raw ) ? $raw : '' ),
			default    => sanitize_text_field( is_string( $raw ) ? $raw : '' ),
		};
	}

	return $clean;
}

function hodima_settings_sanitize_image( mixed $raw ): int {
	$id = absint( is_scalar( $raw ) ? $raw : 0 );
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

function hodima_settings_sanitize_url( string $key, mixed $raw ): string {

	$raw = is_string( $raw ) ? trim( $raw ) : '';
	if ( '' === $raw ) {
		return '';
	}

	$url = esc_url_raw( $raw, [ 'https', 'http' ] );
	if ( '' === $url || ! wp_http_validate_url( $url ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, "invalid_{$key}", sprintf( 'آدرس وارد شده برای «%s» معتبر نیست و ذخیره نشد.', hodima_settings_fields()[ $key ]['label'] ) );
		return '';
	}

	return $url;
}

/** شماره تلفن: ارقام فارسی/عربی به لاتین؛ فقط ارقام و + ابتدایی. */
function hodima_settings_sanitize_phone( string $key, mixed $raw ): string {

	$raw = is_string( $raw ) ? $raw : '';
	$raw = strtr( $raw, [
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	] );

	$phone = (string) preg_replace( '/(?!^\+)[^\d]/', '', trim( $raw ) );

	if ( '' !== $phone && ! preg_match( '/^\+?\d{5,15}$/', $phone ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, "invalid_{$key}", sprintf( '«%s» معتبر نیست و ذخیره نشد.', hodima_settings_fields()[ $key ]['label'] ) );
		return '';
	}

	return $phone;
}

function hodima_settings_sanitize_ga( mixed $raw ): string {

	$id = strtoupper( trim( is_string( $raw ) ? $raw : '' ) );

	if ( '' !== $id && ! preg_match( '/^G-[A-Z0-9]{4,16}$/', $id ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, 'invalid_ga', 'شناسه Google Analytics باید به شکل G-XXXXXXXXXX باشد و ذخیره نشد.' );
		return '';
	}

	return $id;
}

/* =========================================================================
 * ۳. رندر صفحه تنظیمات
 * ========================================================================= */

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {

	if ( 'appearance_page_' . HODIMA_SETTINGS_PAGE !== $hook ) {
		return;
	}

	wp_enqueue_media();

	$base = '/inc/theme-settings/';
	wp_enqueue_style( 'hodima-theme-settings', hodima_URI . $base . 'admin.css', [], hodima_asset_version( $base . 'admin.css' ) );
	wp_enqueue_script( 'hodima-theme-settings', hodima_URI . $base . 'admin.js', [ 'media-editor' ], hodima_asset_version( $base . 'admin.js' ), [ 'in_footer' => true, 'strategy' => 'defer' ] );
} );

function hodima_settings_render_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = hodima_settings();
	$fields   = hodima_settings_fields();
	?>
	<div class="wrap hodima-settings">
		<header class="hodima-settings__header">
			<h1>تنظیمات قالب هدیما</h1>
			<p>برند، اطلاعات تماس، فوتر و آمار سایت. این مقادیر جایگزین مقادیری شده‌اند که قبلا داخل کد قالب نوشته شده بودند.</p>
		</header>

		<?php settings_errors( HODIMA_SETTINGS_OPTION ); ?>

		<form method="post" action="options.php" class="hodima-settings__form" novalidate>
			<?php settings_fields( 'hodima_theme_settings_group' ); ?>

			<nav class="hodima-settings__tabs" aria-label="بخش‌های تنظیمات">
				<?php foreach ( hodima_settings_sections() as $section_key => $section ) : ?>
					<a class="hodima-settings__tab" href="#hodima-section-<?php echo esc_attr( $section_key ); ?>"><?php echo esc_html( $section['title'] ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php foreach ( hodima_settings_sections() as $section_key => $section ) : ?>
				<section class="hodima-settings__card" id="hodima-section-<?php echo esc_attr( $section_key ); ?>" aria-labelledby="hodima-section-title-<?php echo esc_attr( $section_key ); ?>">
					<h2 id="hodima-section-title-<?php echo esc_attr( $section_key ); ?>"><?php echo esc_html( $section['title'] ); ?></h2>
					<p class="hodima-settings__desc"><?php echo esc_html( $section['description'] ); ?></p>

					<div class="hodima-settings__grid">
						<?php
						foreach ( $fields as $key => $field ) {
							if ( $field['section'] === $section_key ) {
								hodima_settings_render_field( $key, $field, $settings[ $key ] );
							}
						}
						?>
					</div>
				</section>
			<?php endforeach; ?>

			<footer class="hodima-settings__actions">
				<?php submit_button( 'ذخیره تنظیمات', 'primary hodima-settings__save', 'submit', false ); ?>
			</footer>
		</form>
	</div>
	<?php
}

/**
 * @param array{section:string, type:string, label:string, default:mixed, help?:string, placeholder?:string} $field
 */
function hodima_settings_render_field( string $key, array $field, mixed $value ): void {

	$id   = 'hodima-setting-' . $key;
	$name = HODIMA_SETTINGS_OPTION . '[' . $key . ']';
	$help = $field['help'] ?? '';
	$wide = in_array( $field['type'], [ 'textarea', 'image' ], true ) ? ' hodima-field--wide' : '';
	?>
	<div class="hodima-field hodima-field--<?php echo esc_attr( $field['type'] . $wide ); ?>">
		<?php if ( 'toggle' === $field['type'] ) : ?>
			<label class="hodima-toggle" for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" role="switch" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( (bool) $value ); ?>>
				<span class="hodima-toggle__track" aria-hidden="true"></span>
				<span class="hodima-toggle__label"><?php echo esc_html( $field['label'] ); ?></span>
			</label>

		<?php elseif ( 'image' === $field['type'] ) : ?>
			<?php $image_id = (int) $value; ?>
			<span class="hodima-field__label" id="<?php echo esc_attr( $id ); ?>-label"><?php echo esc_html( $field['label'] ); ?></span>
			<div class="hodima-media" data-hodima-media>
				<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-hodima-media-input>
				<figure class="hodima-media__preview" data-hodima-media-preview <?php echo $image_id ? '' : 'hidden'; ?>>
					<?php echo $image_id ? wp_get_attachment_image( $image_id, 'medium', false, [ 'alt' => '' ] ) : ''; ?>
				</figure>
				<div class="hodima-media__buttons">
					<button type="button" class="button" data-hodima-media-select aria-describedby="<?php echo esc_attr( $id ); ?>-label" data-title="<?php echo esc_attr( $field['label'] ); ?>">انتخاب تصویر</button>
					<button type="button" class="button-link hodima-media__remove" data-hodima-media-remove <?php echo $image_id ? '' : 'hidden'; ?>>حذف</button>
				</div>
			</div>

		<?php else : ?>
			<label class="hodima-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<?php if ( 'textarea' === $field['type'] ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4"><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php else : ?>
				<?php
				$input_type = match ( $field['type'] ) {
					'url'   => 'url',
					'tel'   => 'tel',
					default => 'text',
				};
				$is_ltr = in_array( $field['type'], [ 'url', 'tel', 'ga' ], true );
				?>
				<input
					type="<?php echo esc_attr( $input_type ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( (string) $value ); ?>"
					<?php echo isset( $field['placeholder'] ) ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
					<?php echo $is_ltr ? 'dir="ltr"' : ''; ?>
					<?php echo 'ga' === $field['type'] ? 'pattern="G-[A-Za-z0-9]{4,16}" autocomplete="off" spellcheck="false"' : ''; ?>
				>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( '' !== $help ) : ?>
			<p class="hodima-field__help"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/* =========================================================================
 * ۴. توابع نمایشی برای قالب
 * ========================================================================= */

/** HTML لوگوی هدر: تصویر تنظیمات ← لوگوی سفارشی وردپرس ← نام سایت. */
function hodima_logo_html(): string {

	$site_name = get_bloginfo( 'name' );
	$logo_id   = (int) hodima_setting( 'logo_id' );

	if ( ! $logo_id ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
	}

	if ( $logo_id && wp_attachment_is_image( $logo_id ) ) {
		return (string) wp_get_attachment_image( $logo_id, 'medium', false, [
			'alt'           => $site_name,
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
			'class'         => 'header__logo-img',
		] );
	}

	return '<span class="header__logo-text">' . esc_html( $site_name ) . '</span>';
}

/**
 * کانال‌های تماس پیکربندی‌شده برای پنجره پشتیبانی.
 *
 * @return list<array{key:string, url:string, label:string, aria:string}>
 */
function hodima_contact_channels(): array {

	$channels = [];
	$phone    = (string) hodima_setting( 'phone' );

	if ( '' !== $phone ) {
		$channels[] = [ 'key' => 'call', 'url' => 'tel:' . $phone, 'label' => 'تماس تلفنی', 'aria' => 'تماس تلفنی با شماره ' . $phone ];
	}

	foreach ( [
		'whatsapp' => [ 'whatsapp_url', 'واتس‌اپ' ],
		'rubika'   => [ 'rubika_url', 'روبیکا' ],
		'telegram' => [ 'telegram_url', 'تلگرام' ],
	] as $key => [ $setting, $label ] ) {
		$url = (string) hodima_setting( $setting );
		if ( '' !== $url ) {
			$channels[] = [ 'key' => $key, 'url' => $url, 'label' => $label, 'aria' => 'ارتباط از طریق ' . $label ];
		}
	}

	return $channels;
}

/* =========================================================================
 * ۵. Google Analytics 4
 * ========================================================================= */

add_action( 'wp_enqueue_scripts', 'hodima_enqueue_google_analytics', 1 );

function hodima_enqueue_google_analytics(): void {

	$ga_id = (string) hodima_setting( 'ga_id' );

	if ( '' === $ga_id || ! preg_match( '/^G-[A-Z0-9]{4,16}$/', $ga_id ) ) {
		return;
	}

	if ( hodima_setting( 'ga_production' ) && 'production' !== wp_get_environment_type() ) {
		return;
	}

	if ( hodima_setting( 'ga_skip_editors' ) && current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_enqueue_script(
		'hodima-gtag',
		'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga_id ),
		[],
		null, // پارامتر نسخه به آدرس گوگل اضافه نشود
		[ 'in_footer' => false, 'strategy' => 'async' ]
	);

	wp_add_inline_script(
		'hodima-gtag',
		sprintf(
			'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",%s);',
			wp_json_encode( $ga_id )
		),
		'before'
	);
}

add_filter( 'wp_resource_hints', static function ( array $urls, string $relation ): array {
	if ( 'preconnect' === $relation && '' !== (string) hodima_setting( 'ga_id' ) ) {
		$urls[] = [ 'href' => 'https://www.googletagmanager.com', 'crossorigin' => 'anonymous' ];
	}
	return $urls;
}, 10, 2 );

/* =========================================================================
 * ۶. باطل کردن کش‌هایی که از این تنظیمات استفاده می‌کنند
 * ========================================================================= */

add_action( 'update_option_' . HODIMA_SETTINGS_OPTION, static function (): void {

	// llms.txt شماره‌های تماس را در خود دارد
	foreach ( [ 'fa', 'en' ] as $lang ) {
		foreach ( [ 20, 500 ] as $limit ) {
			delete_transient( "hodima_llms_txt_cache_{$lang}_{$limit}_siloed" );
		}
	}

	// هدر و فوتر در همه صفحات کش‌شده تغییر کرده‌اند
	do_action( 'litespeed_purge_all' );
} );
