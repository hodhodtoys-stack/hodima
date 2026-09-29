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
 * حالا همه از «نمایش ← تنظیمات قالب هدیما» در پیشخوان خوانده می‌شوند.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HODIMA_SETTINGS_OPTION = 'hodima_theme_settings';
const HODIMA_SETTINGS_PAGE   = 'hodima-settings';
const HODIMA_TRUST_SLOTS     = 3; // تعداد نمادهای اعتماد فوتر

/* =========================================================================
 * ۱. تعریف فیلدها و خواندن تنظیمات
 * ========================================================================= */

/**
 * تعریف همه فیلدها: نوع، مقدار پیش‌فرض، برچسب و بخش.
 *
 * @return array<string, array{section:string, type:string, label:string, default:mixed, help?:string, placeholder?:string, wide?:bool}>
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
		'consult_title'    => [ 'section' => 'footer', 'type' => 'text', 'label' => 'عنوان ستون فرم مشاوره', 'default' => 'مشاوره خرید' ],
		'copyright'        => [ 'section' => 'footer', 'type' => 'text', 'label' => 'متن کپی‌رایت', 'default' => '', 'help' => 'اگر خالی بماند نام سایت نمایش داده می‌شود.' ],
		...hodima_trust_fields(),

		// ── Google Analytics ───────────────────────────────────────
		'ga_id'            => [ 'section' => 'analytics', 'type' => 'ga', 'label' => 'شناسه Google Analytics 4', 'default' => '', 'placeholder' => 'G-XXXXXXXXXX' ],
		'ga_skip_editors'  => [ 'section' => 'analytics', 'type' => 'toggle', 'label' => 'بازدید مدیران و نویسندگان ثبت نشود', 'default' => true ],
		'ga_production'    => [ 'section' => 'analytics', 'type' => 'toggle', 'label' => 'فقط روی سایت اصلی (Production) فعال باشد', 'default' => true, 'help' => 'روی استیجینگ یا لوکال (WP_ENVIRONMENT_TYPE) کد آمار چاپ نمی‌شود تا آمار واقعی آلوده نشود.' ],

		// ── صفحه فروشگاه ─────────────────────────────────────────────
		'shop_title'       => [ 'section' => 'shop', 'type' => 'text', 'label' => 'عنوان سربرگ فروشگاه', 'default' => '', 'help' => 'اگر خالی بماند عنوان برگه فروشگاه ووکامرس نمایش داده می‌شود.' ],
		'shop_subtitle'    => [ 'section' => 'shop', 'type' => 'text', 'label' => 'زیرعنوان', 'default' => '' ],
		'shop_features'    => [ 'section' => 'shop', 'type' => 'textarea', 'label' => 'ویژگی‌ها (هر خط یک مورد)', 'default' => '', 'help' => 'مثلا: اصالت کالا، قیمت رقابتی، ارسال سریع — هر کدام در یک خط.' ],

		// ── شبکه‌های اجتماعی ─────────────────────────────────────────
		'social_title'     => [ 'section' => 'social', 'type' => 'text', 'label' => 'عنوان بخش', 'default' => 'شبکه‌های اجتماعی' ],
		'social_hide_urls' => [ 'section' => 'social', 'type' => 'urllist', 'label' => 'صفحه‌هایی که شبکه‌های اجتماعی در آن‌ها نمایش داده نشود', 'default' => '', 'placeholder' => "https://example.com/cart/\n/checkout/\n/product/*", 'help' => 'هر آدرس در یک خط؛ آدرس کامل صفحه یا فقط مسیر آن (مثلا /cart/). برای صفحه اصلی / و برای یک صفحه و همه زیرصفحه‌هایش * در انتها (مثلا /blog/*). پارامترهای بعد از ? در نظر گرفته نمی‌شوند.' ],
		...hodima_social_fields(),
	];
}

/**
 * سه نماد اعتماد فوتر؛ هر کدام تصویر و لینک.
 * نماد اول همان کلیدهای قدیمی (trust_image_id / trust_url) را نگه می‌دارد تا
 * نماد ذخیره‌شده سایت بعد از به‌روزرسانی از دست نرود؛ بقیه پسوند _2 و _3 دارند.
 */
function hodima_trust_fields(): array {

	$fields = [];

	for ( $i = 1; $i <= HODIMA_TRUST_SLOTS; $i++ ) {
		$suffix = 1 === $i ? '' : "_{$i}";
		$num    = hodima_fa_digits( $i );
		$fields[ "trust_image_id{$suffix}" ] = [ 'section' => 'footer', 'group' => "trust_{$i}", 'type' => 'image', 'label' => "تصویر نماد {$num}", 'default' => 0 ];
		$fields[ "trust_url{$suffix}" ]      = [ 'section' => 'footer', 'group' => "trust_{$i}", 'type' => 'url', 'label' => "لینک نماد {$num}", 'default' => '', 'placeholder' => 'https://', 'help' => 'صفحه اعتبارسنجی نماد (اختیاری).' ];
	}

	return $fields;
}

/** ارقام فارسی برای برچسب‌ها (مستقل از زبان پیشخوان). */
function hodima_fa_digits( int|string $value ): string {
	return strtr( (string) $value, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/**
 * گروه‌های فیلد: فیلدهای پشت‌سرهم با یک group در یک قاب نمایش داده می‌شوند و
 * گروه‌های پشت‌سرهم با یک set در یک ظرف (کنار هم یا ردیف به ردیف).
 *
 * @return array<string, array{title:string, set:string}>
 */
function hodima_settings_groups(): array {

	$groups = [];

	for ( $i = 1; $i <= HODIMA_TRUST_SLOTS; $i++ ) {
		$groups[ "trust_{$i}" ] = [ 'title' => 'نماد اعتماد ' . hodima_fa_digits( $i ), 'set' => 'trust' ];
	}

	foreach ( hodima_social_networks() as $key => $label ) {
		$groups[ "social_{$key}" ] = [ 'title' => $label, 'set' => 'social' ];
	}

	return $groups;
}

/**
 * ظرف هر مجموعه گروه.
 *   cards: قاب‌ها کنار هم (نمادهای اعتماد به ترتیب ۱، ۲، ۳)
 *   rows : هر گروه یک ردیف با ستون‌های هم‌تراز (شبکه‌های اجتماعی)
 *
 * @return array<string, array{layout:string, title:string, help:string, columns?:list<string>}>
 */
function hodima_settings_sets(): array {
	return [
		'trust'  => [ 'layout' => 'cards', 'title' => 'نمادهای اعتماد', 'help' => 'تا سه نماد (مثلا اینماد، ساماندهی، اتحادیه). نمادهای دارای تصویر به همین ترتیب کنار هم در فوتر نمایش داده می‌شوند؛ نماد بدون تصویر نادیده گرفته می‌شود.' ],
		'social' => [ 'layout' => 'rows', 'title' => 'شبکه‌ها', 'help' => 'شبکه‌ای که آدرس نداشته باشد نمایش داده نمی‌شود؛ بدون آیکون، نام شبکه نمایش داده می‌شود. آیکون مربعی و شفاف (SVG یا PNG) بهترین نتیجه را دارد.', 'columns' => [ 'شبکه', 'آدرس صفحه', 'آیکون (اختیاری)' ] ],
	];
}

/**
 * شبکه‌های اجتماعی قابل تنظیم (ترتیب نمایش در صفحه اصلی).
 *
 * @return array<string, string> شناسه => نام نمایشی
 */
function hodima_social_networks(): array {
	return (array) apply_filters( 'hodima_social_networks', [
		'instagram' => 'اینستاگرام',
		'telegram'  => 'تلگرام',
		'whatsapp'  => 'واتس‌اپ',
		'rubika'    => 'روبیکا',
		'aparat'    => 'آپارات',
		'eitaa'     => 'ایتا',
		'bale'      => 'بله',
		'youtube'   => 'یوتیوب',
		'linkedin'  => 'لینکدین',
	] );
}

/** برای هر شبکه دو فیلد در یک ردیف: آدرس صفحه و آیکون (اختیاری). */
function hodima_social_fields(): array {

	$fields = [];

	foreach ( hodima_social_networks() as $key => $label ) {
		$fields[ "social_{$key}_url" ]  = [ 'section' => 'social', 'group' => "social_{$key}", 'type' => 'url', 'label' => $label, 'default' => '', 'placeholder' => 'https://' ];
		$fields[ "social_{$key}_icon" ] = [ 'section' => 'social', 'group' => "social_{$key}", 'type' => 'image', 'label' => 'آیکون ' . $label, 'default' => 0 ];
	}

	return $fields;
}

/**
 * بخش‌های صفحه تنظیمات؛ هر بخش یک تب جداگانه است.
 *
 * @return array<string, array{title:string, description:string, icon:string}>
 */
function hodima_settings_sections(): array {
	return [
		'brand'     => [ 'title' => 'برند', 'icon' => 'dashicons-art', 'description' => 'لوگوی هدر سایت.' ],
		'contact'   => [ 'title' => 'اطلاعات تماس', 'icon' => 'dashicons-phone', 'description' => 'در پنجره «پشتیبانی» هدر و فوتر نمایش داده می‌شود. هر گزینه خالی، نمایش داده نمی‌شود.' ],
		'footer'    => [ 'title' => 'فوتر', 'icon' => 'dashicons-align-wide', 'description' => 'ستون‌های فوتر. ستونی که محتوا نداشته باشد نمایش داده نمی‌شود (ستون نماد اعتماد بدون هیچ تصویری).' ],
		'analytics' => [ 'title' => 'Google Analytics', 'icon' => 'dashicons-chart-area', 'description' => 'کد آمار GA4 با بارگذاری async و بدون مسدود کردن رندر صفحه اضافه می‌شود.' ],
		'shop'      => [ 'title' => 'صفحه فروشگاه', 'icon' => 'dashicons-store', 'description' => 'سربرگ صفحه اول فروشگاه. زیرعنوان و ویژگی‌های خالی نمایش داده نمی‌شوند.' ],
		'social'    => [ 'title' => 'شبکه‌های اجتماعی', 'icon' => 'dashicons-share', 'description' => 'نوار شبکه‌های اجتماعی بالای فوتر همه صفحه‌ها (به جز صفحه‌هایی که در فهرست زیر آمده‌اند). بدون هیچ شبکه‌ای نوار نمایش داده نمی‌شود.' ],
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
		'تنظیمات قالب هدیما',
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
			'urllist'  => hodima_settings_sanitize_url_list( $raw ),
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

/**
 * فهرست آدرس (هر خط یکی): آدرس کامل http(s) یا مسیر با / ابتدایی، و * فقط در انتها.
 * «hodhodli.com/cart» به آدرس کامل و «cart/» به مسیر تبدیل می‌شود؛ خط تکراری یا
 * نامعتبر حذف و به کاربر اطلاع داده می‌شود.
 */
function hodima_settings_sanitize_url_list( mixed $raw ): string {

	$lines   = preg_split( '/\R/u', is_string( $raw ) ? $raw : '' ) ?: [];
	$clean   = [];
	$invalid = [];

	foreach ( $lines as $line ) {

		// sanitize_text_field نه: کدهای %XX نامک فارسی کپی‌شده از نوار آدرس را پاک می‌کند
		$line = trim( wp_strip_all_tags( $line ) );
		if ( '' === $line ) {
			continue;
		}

		$wildcard = str_ends_with( $line, '*' );
		$line     = rtrim( $line, '*' );

		if ( ! preg_match( '#^https?://#i', $line ) ) {
			// دامنه بدون http یا مسیر بدون / ابتدایی
			$line = preg_match( '#^[^/\s]+\.[a-z]{2,}(/|$)#i', $line ) ? 'https://' . $line : '/' . ltrim( $line, '/' );
		}

		$url = esc_url_raw( $line, [ 'https', 'http' ] );

		if ( '' === $url || ( ! str_starts_with( $url, '/' ) && ! wp_parse_url( $url, PHP_URL_HOST ) ) ) {
			$invalid[] = $line;
			continue;
		}

		$clean[] = $url . ( $wildcard ? '*' : '' );
	}

	if ( $invalid ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, 'invalid_social_hide_urls', sprintf( 'این آدرس‌ها در فهرست صفحه‌های بدون شبکه‌های اجتماعی معتبر نبودند و ذخیره نشدند: %s', implode( '، ', $invalid ) ) );
	}

	return implode( "\n", array_values( array_unique( $clean ) ) );
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

/**
 * صفحه «نمایش ← تنظیمات قالب هدیما» با تب جداگانه برای هر بخش.
 *
 * قبلا همه بخش‌ها زیر هم در یک صفحه بلند بودند و «تب‌ها» فقط لینک پرش به
 * همان صفحه بودند. حالا هر بخش پنل خودش را دارد:
 *   - بدون جاوااسکریپت: تب‌ها لینک ?tab=… هستند و سرور پنل همان تب را نشان می‌دهد؛
 *   - با جاوااسکریپت (admin.js): جابه‌جایی فوری بدون بارگذاری مجدد، و تب فعال
 *     بعد از «ذخیره» حفظ می‌شود (آدرس بازگشت فرم به‌روز می‌شود).
 * همه فیلدها در همان یک فرم و یک گزینه (hodima_theme_settings) می‌مانند؛ پنل‌های
 * پنهان هم ارسال می‌شوند، پس ذخیره یک تب مقدار تب‌های دیگر را پاک نمی‌کند.
 */
function hodima_settings_render_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = hodima_settings();
	$fields   = hodima_settings_fields();
	$sections = hodima_settings_sections();

	$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب تب
	$current   = isset( $sections[ $requested ] ) ? $requested : (string) array_key_first( $sections );
	?>
	<div class="wrap hodima-settings">
		<header class="hodima-settings__header">
			<span class="dashicons dashicons-admin-appearance hodima-settings__header-icon" aria-hidden="true"></span>
			<div>
				<h1>تنظیمات قالب هدیما</h1>
				<p>برند، اطلاعات تماس، فوتر و نمادهای اعتماد، آمار، صفحه فروشگاه و شبکه‌های اجتماعی قالب.</p>
			</div>
		</header>

		<nav class="hodima-settings__tabs" role="tablist" aria-label="بخش‌های تنظیمات" data-hodima-tabs>
			<?php foreach ( $sections as $section_key => $section ) : ?>
				<a
					class="hodima-settings__tab"
					id="hodima-tab-<?php echo esc_attr( $section_key ); ?>"
					href="<?php echo esc_url( add_query_arg( [ 'page' => HODIMA_SETTINGS_PAGE, 'tab' => $section_key ], admin_url( 'themes.php' ) ) ); ?>"
					role="tab"
					aria-controls="hodima-section-<?php echo esc_attr( $section_key ); ?>"
					aria-selected="<?php echo $section_key === $current ? 'true' : 'false'; ?>"
					tabindex="<?php echo $section_key === $current ? '0' : '-1'; ?>"
					data-tab="<?php echo esc_attr( $section_key ); ?>"
				>
					<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $section['title'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<hr class="wp-header-end">

		<?php
		/*
		 * بدون آرگومان: پیام «تنظیمات ذخیره شد» را options.php زیر نامک general
		 * ثبت می‌کند. نسخه قبلی فقط خطاهای همین گزینه را چاپ می‌کرد و بعد از
		 * ذخیره موفق هیچ پیامی دیده نمی‌شد.
		 */
		settings_errors();
		?>

		<form method="post" action="options.php" class="hodima-settings__form" novalidate>
			<?php settings_fields( 'hodima_theme_settings_group' ); ?>

			<?php foreach ( $sections as $section_key => $section ) : ?>
				<section
					class="hodima-settings__card"
					id="hodima-section-<?php echo esc_attr( $section_key ); ?>"
					role="tabpanel"
					aria-labelledby="hodima-tab-<?php echo esc_attr( $section_key ); ?>"
					<?php echo $section_key === $current ? '' : 'hidden'; ?>
				>
					<h2><?php echo esc_html( $section['title'] ); ?></h2>
					<p class="hodima-settings__desc"><?php echo esc_html( $section['description'] ); ?></p>

					<div class="hodima-settings__grid">
						<?php
						hodima_settings_render_section_fields(
							array_filter( $fields, static fn( array $field ): bool => $field['section'] === $section_key ),
							$settings
						);
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
 * فیلدهای یک بخش، با قاب گروه‌ها و ظرف مجموعه‌ها (hodima_settings_groups/sets).
 *
 * قبلا فیلدهای شبکه‌های اجتماعی (آدرس و آیکون ۹ شبکه) همه در یک شبکه
 * خودکار ریخته می‌شدند؛ آیکون هر شبکه کنار آدرس شبکه دیگری می‌افتاد و
 * معلوم نبود کدام به کدام تعلق دارد. حالا هر شبکه یک ردیف است.
 *
 * @param array<string, array> $fields
 */
function hodima_settings_render_section_fields( array $fields, array $settings ): void {

	$groups     = hodima_settings_groups();
	$sets       = hodima_settings_sets();
	$open_group = null;
	$open_set   = null;

	$close_group = static function () use ( &$open_group ): void {
		if ( null !== $open_group ) {
			echo '</div></fieldset>';
			$open_group = null;
		}
	};

	$close_set = static function () use ( &$open_set, $close_group ): void {
		$close_group();
		if ( null !== $open_set ) {
			echo '</div></div>';
			$open_set = null;
		}
	};

	foreach ( $fields as $key => $field ) {

		$group = isset( $field['group'], $groups[ $field['group'] ] ) ? $field['group'] : null;
		$set   = null !== $group ? $groups[ $group ]['set'] : null;

		if ( $group !== $open_group ) {
			$close_group();
		}

		if ( $set !== $open_set ) {
			$close_set();

			if ( null !== $set && isset( $sets[ $set ] ) ) {
				$meta = $sets[ $set ];
				printf(
					'<div class="hodima-set hodima-set--%1$s"><div class="hodima-set__head"><h3 class="hodima-set__title">%2$s</h3><p class="hodima-field__help">%3$s</p></div>',
					esc_attr( $meta['layout'] ),
					esc_html( $meta['title'] ),
					esc_html( $meta['help'] )
				);
				if ( ! empty( $meta['columns'] ) ) {
					echo '<div class="hodima-set__columns" aria-hidden="true">';
					foreach ( $meta['columns'] as $column ) {
						echo '<span>' . esc_html( $column ) . '</span>';
					}
					echo '</div>';
				}
				echo '<div class="hodima-set__items">';
				$open_set = $set;
			}
		}

		if ( null !== $group && $group !== $open_group ) {
			printf(
				'<fieldset class="hodima-group" data-group="%1$s"><legend class="hodima-group__title">%2$s</legend><div class="hodima-group__fields">',
				esc_attr( $group ),
				esc_html( $groups[ $group ]['title'] )
			);
			$open_group = $group;
		}

		hodima_settings_render_field( $key, $field, $settings[ $key ] );
	}

	$close_set();
}

/**
 * @param array{section:string, type:string, label:string, default:mixed, group?:string, help?:string, placeholder?:string, wide?:bool} $field
 */
function hodima_settings_render_field( string $key, array $field, mixed $value ): void {

	$id   = 'hodima-setting-' . $key;
	$name = HODIMA_SETTINGS_OPTION . '[' . $key . ']';
	$help = $field['help'] ?? '';
	$wide = in_array( $field['type'], [ 'textarea', 'urllist', 'image' ], true ) && ( $field['wide'] ?? true ) && ! isset( $field['group'] ) ? ' hodima-field--wide' : '';
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
			<?php elseif ( 'urllist' === $field['type'] ) : ?>
				<textarea
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					rows="5"
					dir="ltr"
					spellcheck="false"
					autocomplete="off"
					<?php echo isset( $field['placeholder'] ) ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
					<?php echo '' !== $help ? 'aria-describedby="' . esc_attr( $id ) . '-help"' : ''; ?>
				><?php echo esc_textarea( (string) $value ); ?></textarea>
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
			<p class="hodima-field__help" id="<?php echo esc_attr( $id ); ?>-help"><?php echo esc_html( $help ); ?></p>
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

/**
 * شبکه‌های اجتماعی پیکربندی‌شده، به ترتیب hodima_social_networks().
 *
 * @return list<array{key:string, label:string, url:string, icon_id:int}>
 */
function hodima_social_links(): array {

	$links = [];

	foreach ( hodima_social_networks() as $key => $label ) {
		$url = (string) hodima_setting( "social_{$key}_url" );
		if ( '' !== $url ) {
			$links[] = [
				'key'     => $key,
				'label'   => $label,
				'url'     => $url,
				'icon_id' => (int) hodima_setting( "social_{$key}_icon" ),
			];
		}
	}

	return $links;
}

/**
 * نمادهای اعتماد دارای تصویر، به ترتیب ۱ تا ۳ (نماد بدون تصویر رد می‌شود).
 *
 * @return list<array{image_id:int, url:string}>
 */
function hodima_trust_badges(): array {

	$badges = [];

	for ( $i = 1; $i <= HODIMA_TRUST_SLOTS; $i++ ) {
		$suffix   = 1 === $i ? '' : "_{$i}";
		$image_id = (int) hodima_setting( "trust_image_id{$suffix}" );

		if ( $image_id && wp_attachment_is_image( $image_id ) ) {
			$badges[] = [ 'image_id' => $image_id, 'url' => (string) hodima_setting( "trust_url{$suffix}" ) ];
		}
	}

	return $badges;
}

/**
 * مسیر یک آدرس برای مقایسه: بدون دامنه، پارامتر و پوشه نصب وردپرس، رمزگشایی‌شده
 * (نامک فارسی کپی‌شده از نوار آدرس کدگذاری شده است)، با / در ابتدا و انتها.
 */
function hodima_normalize_path( string $url ): string {

	$path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

	if ( '' !== $base && str_starts_with( $path, $base . '/' ) ) {
		$path = substr( $path, strlen( $base ) );
	}

	$path = trim( strtolower( $path ), '/' ); // strtolower در PHP 8 فقط ASCII را تغییر می‌دهد؛ نامک فارسی سالم می‌ماند

	return '' === $path ? '/' : '/' . $path . '/';
}

/**
 * آیا نوار شبکه‌های اجتماعی در صفحه فعلی نمایش داده شود؟
 * صفحه‌های فهرست «social_hide_urls» مستثنا هستند؛ خط با * در انتها همه زیرصفحه‌ها را هم می‌گیرد.
 */
function hodima_socials_visible_here(): bool {

	$rules = trim( (string) hodima_setting( 'social_hide_urls' ) );
	$show  = true;

	if ( '' !== $rules ) {
		$current = hodima_normalize_path( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- فقط مقایسه مسیر

		foreach ( preg_split( '/\R/u', $rules ) ?: [] as $rule ) {

			$rule = trim( $rule );
			if ( '' === $rule ) {
				continue;
			}

			$prefix = str_ends_with( $rule, '*' );
			$path   = hodima_normalize_path( rtrim( $rule, '*' ) );

			if ( $prefix ? str_starts_with( $current, $path ) : $current === $path ) {
				$show = false;
				break;
			}
		}
	}

	/** برای استثنای برنامه‌نویسی (مثلا بر اساس نوع صفحه). */
	return (bool) apply_filters( 'hodima_show_socials', $show );
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
