<?php
/**
 * ورود و امنیت — صفحه ورود پیشخوان (/wp-admin/ ← wp-login.php)
 * Path: plugins/hodima-core/includes/login.php
 *
 * چرا در Core و نه قالب: امنیت ورود مدیر نباید با عوض شدن قالب یا خاموش شدن
 * افزونه دیگری (مثلا پنل کاربری) از بین برود (قانون «قالب فقط نمایش است»).
 *
 * این فایل (انتشار ۱) دو کار می‌کند:
 *  ۱) ظاهر تازه صفحه ورود (CSS فقط زیر body.login، لوگو از «آدرس لوگو» در
 *     تنظیمات «ابزارهای هدیما ← ورود و امنیت»؛ خالی = حرف اول نام سایت).
 *  ۲) بستن راه‌های شناسایی نام کاربری: پیام خطای یکسان، ?author=N، فهرست
 *     کاربران REST برای بازدیدکننده بی‌ورود، و دو هدر امنیتی ساده.
 * محدودیت تلاش و بازیابی رمز بدون افشا: انتشار بعدی.
 *
 * نام گزینه تازه است (hodima_core_login_settings)؛ داده قبلی سایت لمس نمی‌شود.
 *
 * راه اضطراری (اگر صفحه ورود خراب دیده شد): در wp-config.php بنویسید
 *   define( 'HODIMA_CORE_LOGIN_OFF', true );
 * تا ظاهر تازه خاموش شود و صفحه ورود پیش‌فرض وردپرس برگردد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** نام گزینه تنظیمات (آرایه: enabled، logo_url، logo_height). */
const HODIMA_CORE_LOGIN_OPTION = 'hodima_core_login_settings';

/** حداقل و حداکثر ارتفاع نمایش لوگو (پیکسل). */
const HODIMA_CORE_LOGIN_LOGO_MIN = 24;
const HODIMA_CORE_LOGIN_LOGO_MAX = 120;

/* =====================================================================
 * تنظیمات
 * ===================================================================== */

/** @return array{enabled: bool, logo_url: string, logo_height: int} */
function hodima_core_login_defaults(): array {
	return [
		'enabled'     => true,
		'logo_url'    => '',
		'logo_height' => 48,
	];
}

/** @return array{enabled: bool, logo_url: string, logo_height: int} */
function hodima_core_login_settings(): array {
	$saved = get_option( HODIMA_CORE_LOGIN_OPTION, [] );

	return hodima_core_login_sanitize( is_array( $saved ) ? $saved : [], true );
}

/**
 * پاک‌سازی تنظیمات (هم هنگام ذخیره، هم هنگام خواندن).
 *
 * @param array<string, mixed> $in
 * @param bool                 $stored true = خواندن از دیتابیس (کلید نبود = پیش‌فرض)؛ false = فرم (چک‌باکس تیک‌نخورده = خاموش)
 * @return array{enabled: bool, logo_url: string, logo_height: int}
 */
function hodima_core_login_sanitize( array $in, bool $stored = false ): array {

	$defaults = hodima_core_login_defaults();

	$enabled = $stored && ! array_key_exists( 'enabled', $in )
		? $defaults['enabled']
		: ! empty( $in['enabled'] );

	$height = isset( $in['logo_height'] ) && is_numeric( $in['logo_height'] ) ? (int) $in['logo_height'] : $defaults['logo_height'];

	return [
		'enabled'     => $enabled,
		'logo_url'    => hodima_core_login_clean_logo_url( is_string( $in['logo_url'] ?? null ) ? $in['logo_url'] : '' ),
		'logo_height' => max( HODIMA_CORE_LOGIN_LOGO_MIN, min( HODIMA_CORE_LOGIN_LOGO_MAX, $height ) ),
	];
}

/**
 * آدرس لوگوی معتبر یا رشته خالی.
 *
 * چرا سخت‌گیر: آدرس داخل CSS (url("…")) چاپ می‌شود؛ نقل‌قول، پرانتز، فاصله و
 * بک‌اسلش می‌توانست CSS دلخواه تزریق کند. فقط https، بدون نام کاربری/رمز در آدرس،
 * و پسوند تصویر (png، jpg، jpeg، webp، svg؛ svg فقط با url() پس اسکریپتش اجرا نمی‌شود).
 */
function hodima_core_login_clean_logo_url( string $url ): string {

	$url = trim( $url );

	if ( '' === $url || strlen( $url ) > 500 || preg_match( '/[\s"\'()<>\\\\]/u', $url ) ) {
		return '';
	}

	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return '';
	}

	$extension = strtolower( pathinfo( (string) ( $parts['path'] ?? '' ), PATHINFO_EXTENSION ) );
	if ( ! in_array( $extension, [ 'png', 'jpg', 'jpeg', 'webp', 'svg' ], true ) ) {
		return '';
	}

	$clean = esc_url_raw( $url, [ 'https' ] );

	// esc_url_raw چیزی را عوض کرده = آدرس مشکوک بود
	return $clean === $url ? $clean : '';
}

/** حرف اول نام سایت برای حالت «بدون لوگو» (فقط حرف و عدد؛ داخل CSS چاپ می‌شود). */
function hodima_core_login_letter(): string {

	$name   = (string) get_bloginfo( 'name', 'display' );
	$name   = (string) preg_replace( '/[^\p{L}\p{N}]/u', '', html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) );
	$letter = mb_substr( $name, 0, 1 );

	return '' !== $letter ? $letter : 'ه';
}

/** آیا ظاهر تازه روشن است؟ (کلید تنظیمات و راه اضطراری wp-config) */
function hodima_core_login_look_active(): bool {

	if ( defined( 'HODIMA_CORE_LOGIN_OFF' ) && HODIMA_CORE_LOGIN_OFF ) {
		return false;
	}

	return (bool) apply_filters( 'hodima_core_login_look', hodima_core_login_settings()['enabled'] );
}

/* =====================================================================
 * ظاهر صفحه ورود
 * ===================================================================== */

add_action( 'login_enqueue_scripts', static function (): void {

	if ( ! hodima_core_login_look_active() ) {
		return;
	}

	$file = HODIMA_CORE_PLUGIN_DIR . '/assets/login.css';

	wp_enqueue_style(
		'hodima-core-login',
		HODIMA_CORE_PLUGIN_URL . '/assets/login.css',
		[ 'login' ],
		is_readable( $file ) ? (string) filemtime( $file ) : HODIMA_CORE_PLUGIN_VERSION
	);

	$settings = hodima_core_login_settings();

	// فقط مقدارهای پاک‌سازی‌شده (آدرس بی نقل‌قول/پرانتز، عدد، حرف) وارد CSS می‌شوند
	$vars = sprintf( '--hd-login-logo-h:%dpx;--hd-login-letter:"%s";', $settings['logo_height'], hodima_core_login_letter() );
	if ( '' !== $settings['logo_url'] ) {
		$vars .= sprintf( '--hd-login-logo:url("%s");', $settings['logo_url'] );
	}

	wp_add_inline_style( 'hodima-core-login', 'body.login{' . $vars . '}' );
}, 20 );

// فونت پیشخوان (Vazirmatn همراه Core) برای صفحه ورود هم؛ همان فیلتر hodima_admin_font
add_action( 'login_head', static function (): void {

	if ( ! hodima_core_login_look_active() || ! apply_filters( 'hodima_admin_font', true ) ) {
		return;
	}

	$faces = '';
	foreach ( [ 'Regular' => 400, 'Medium' => 500, 'Bold' => 700 ] as $name => $weight ) {
		$faces .= sprintf(
			"@font-face{font-family:'Vazirmatn';src:url('%s') format('woff2');font-weight:%d;font-style:normal;font-display:swap}",
			esc_url( hodima_core_font_url( $name ) ),
			$weight
		);
	}

	printf( '<style id="hodima-login-font">%s</style>' . "\n", $faces ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- فقط آدرس‌های esc_url شده
} );

add_filter( 'login_body_class', static function ( array $classes ): array {

	if ( hodima_core_login_look_active() ) {
		$classes[] = 'hodima-login';
		$classes[] = '' !== hodima_core_login_settings()['logo_url'] ? 'hodima-login--logo' : 'hodima-login--letter';
	}

	return $classes;
} );

// لوگو به خود سایت لینک شود (نه wordpress.org) و عنوان بدون نام وردپرس
add_filter( 'login_headerurl', static fn( $url ) => hodima_core_login_look_active() ? home_url( '/' ) : $url );
add_filter( 'login_headertext', static fn( $text ) => hodima_core_login_look_active() ? (string) get_bloginfo( 'name', 'display' ) : $text );
add_filter( 'login_title', static function ( $title, $page ) {
	return hodima_core_login_look_active()
		? sprintf( '%s ‹ %s', (string) $page, (string) get_bloginfo( 'name', 'display' ) )
		: $title;
}, 10, 2 );

/**
 * نوار کناری رنگی (فقط دسکتاپ؛ CSS در موبایل پنهانش می‌کند).
 * جمله‌ها فقط چیزهایی‌اند که همین ماژول واقعا انجام می‌دهد.
 */
add_action( 'login_footer', static function (): void {

	if ( ! hodima_core_login_look_active() ) {
		return;
	}

	$aside = (array) apply_filters( 'hodima_core_login_aside', [
		'eyebrow' => (string) get_bloginfo( 'name', 'display' ),
		'title'   => 'مدیریت سایت، امن و ساده',
		'points'  => [
			'پیام خطای ورود، نام کاربری را لو نمی‌دهد.',
			'فهرست کاربران سایت از بیرون بسته است.',
			'حدس نام کاربری از راه آدرس نویسنده بسته است.',
		],
	] );

	echo '<aside class="hodima-login-aside"><p class="hodima-login-aside__eyebrow">' . esc_html( (string) ( $aside['eyebrow'] ?? '' ) ) . '</p>';
	echo '<h2 class="hodima-login-aside__title">' . esc_html( (string) ( $aside['title'] ?? '' ) ) . '</h2><ul class="hodima-login-aside__points">';
	foreach ( (array) ( $aside['points'] ?? [] ) as $point ) {
		echo '<li>' . esc_html( (string) $point ) . '</li>';
	}
	echo '</ul></aside>' . "\n";
}, 5 );

/* =====================================================================
 * امنیت: افشا نشدن نام کاربری
 * ===================================================================== */

/** کدهای خطایی که می‌گویند «این نام کاربری هست/نیست». */
const HODIMA_CORE_LOGIN_LEAKY_CODES = [ 'invalid_username', 'invalid_email', 'incorrect_password' ];

/**
 * پیام خطای ورود یکسان. وردپرس برای «کاربر نیست» و «رمز غلط» دو پیام جدا می‌دهد
 * (و پیام رمز غلط نام کاربری را هم تکرار می‌کند): مهاجم می‌فهمد کدام نام کاربری
 * وجود دارد. اینجا هر سه یک پیام می‌شوند.
 * بازیابی رمز (invalidcombo) عمدا دست‌نخورده است؛ انتشار بعدی.
 */
add_filter( 'wp_login_errors', static function ( $errors ) {

	if ( ! $errors instanceof WP_Error || ! apply_filters( 'hodima_core_login_generic_errors', true ) ) {
		return $errors;
	}

	$hit = false;
	foreach ( HODIMA_CORE_LOGIN_LEAKY_CODES as $code ) {
		if ( $errors->get_error_message( $code ) ) {
			$errors->remove( $code );
			$hit = true;
		}
	}

	if ( $hit ) {
		$errors->add( 'hodima_login_failed', '<strong>خطا:</strong> نام کاربری یا رمز عبور درست نیست.' );
	}

	return $errors;
} );

/**
 * آیا این درخواست حدس نام کاربری با ?author=N است؟
 * (وردپرس آن را به /author/نام-کاربری/ هدایت می‌کند و نام کاربری را لو می‌دهد.)
 * خالص و بی‌وابستگی به وردپرس تا آزمون‌پذیر باشد.
 *
 * @param array<string, mixed> $query معمولا $_GET
 */
function hodima_core_login_is_author_probe( array $query, bool $logged_in ): bool {

	if ( $logged_in || ! array_key_exists( 'author', $query ) ) {
		return false;
	}

	return is_scalar( $query['author'] ) && 1 === preg_match( '/^-?\d+$/', (string) $query['author'] );
}

add_action( 'init', static function (): void {

	if ( is_admin() || ! apply_filters( 'hodima_core_login_block_author_probe', true ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط تشخیص آدرس، چیزی ذخیره یا خوانده نمی‌شود
	if ( hodima_core_login_is_author_probe( $_GET, is_user_logged_in() ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 1 );

/**
 * فهرست کاربران REST (/wp/v2/users) فقط برای وارد‌شده‌ها.
 * بدون این، هر بازدیدکننده نام نمایشی و نامک (اغلب همان نام کاربری) همه نویسنده‌ها را می‌گرفت.
 *
 * @param array<string, mixed> $endpoints
 * @return array<string, mixed>
 */
add_filter( 'rest_endpoints', static function ( $endpoints ) {

	if ( ! is_array( $endpoints ) || is_user_logged_in() || ! apply_filters( 'hodima_core_login_block_rest_users', true ) ) {
		return $endpoints;
	}

	foreach ( array_keys( $endpoints ) as $route ) {
		if ( '/wp/v2/users' === $route || str_starts_with( (string) $route, '/wp/v2/users/' ) ) {
			unset( $endpoints[ $route ] );
		}
	}

	return $endpoints;
} );

/* =====================================================================
 * هدرهای امنیتی ساده
 * ===================================================================== */

/**
 * @return array<string, string>
 */
function hodima_core_login_security_headers(): array {
	return (array) apply_filters( 'hodima_core_security_headers', [
		'X-Content-Type-Options' => 'nosniff',
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
	] );
}

// سایت (فرانت)
add_filter( 'wp_headers', static function ( $headers ) {
	return is_array( $headers ) ? array_merge( hodima_core_login_security_headers(), $headers ) : $headers;
} );

// صفحه ورود (wp-login.php از wp_headers رد نمی‌شود)
add_action( 'login_init', static function (): void {
	if ( headers_sent() ) {
		return;
	}
	foreach ( hodima_core_login_security_headers() as $name => $value ) {
		header( $name . ': ' . $value );
	}
} );
