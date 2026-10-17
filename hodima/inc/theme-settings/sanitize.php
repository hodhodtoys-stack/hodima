<?php
/**
 * Theme Settings — پاک‌سازی و اعتبارسنجی فیلدها (رنگ، تصویر، عدد، کد، آدرس، دامنه، تلفن، GA)
 * Path: hodima/inc/theme-settings/sanitize.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پاک‌سازی و اعتبارسنجی همه فیلدها.
 * مقدار نامعتبر رد و به کاربر اطلاع داده می‌شود؛ مقدار قبلی حفظ نمی‌شود
 * تا خطا بی‌صدا پنهان نماند.
 *
 * @param mixed $input
 * @return array<string, mixed>
 */
function hodima_settings_sanitize( $input ): array {

	/*
	 * بدون wp_unslash: options.php پیش از update_option خودش unslash می‌کند و این
	 * callback داده unslash‌شده می‌گیرد. تا 2.9.7 دوباره unslash می‌شد و هر «\» در
	 * متن تنظیمات (و از 2.9.8 در کد سفارشی، مثل "\n" در اسکریپت) پاک می‌شد.
	 */
	$input  = is_array( $input ) ? $input : [];
	$clean  = [];

	foreach ( hodima_settings_fields() as $key => $field ) {

		// رفتار هر نوع: enum Hodima_Setting_Type (inc/classes)؛ نوع ناشناخته مثل قبل = متن
		$clean[ $key ] = hodima_setting_type( $field )->sanitize( $key, $field, $input[ $key ] ?? null );
	}

	// «فونت آپلودی» بدون فایل (inc/typography.php)
	return hodima_typography_validate( $clean );
}

/**
 * نوع یک فیلد تنظیمات.
 *
 * @param array<string, mixed> $field
 */
function hodima_setting_type( array $field ): Hodima_Setting_Type {
	return Hodima_Setting_Type::tryFrom( (string) ( $field['type'] ?? '' ) ) ?? Hodima_Setting_Type::Text;
}

/** رنگ: #abc → #aabbcc (input type=color فقط شش رقمی می‌پذیرد)؛ نامعتبر = پیش‌فرض. */
function hodima_settings_sanitize_color( mixed $raw, string $fallback ): string {

	$hex = sanitize_hex_color( is_string( $raw ) ? trim( $raw ) : '' );

	return $hex ? hodima_rgb_hex( hodima_hex_rgb( $hex ) ) : strtolower( $fallback );
}

function hodima_settings_sanitize_image( mixed $raw ): int {
	$id = absint( is_scalar( $raw ) ? $raw : 0 );
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/**
 * عدد در بازه min/max؛ خالی/نامعتبر = پیش‌فرض. با step اعشاری (مثلا ۰٫۰۵ برای
 * فاصله خطوط) عدد اعشاری گرد‌شده به همان گام، وگرنه صحیح (رفتار قبلی همه
 * فیلدهای عددی). ارقام فارسی هم پذیرفته می‌شوند.
 *
 * @param array<string, mixed> $field
 */
function hodima_settings_sanitize_number( array $field, mixed $raw ): int|float {

	$step    = (float) ( $field['step'] ?? 1 );
	$decimal = $step > 0 && floor( $step ) !== $step;
	$raw     = is_string( $raw ) ? strtr( trim( $raw ), [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.', '/' => '.' ] ) : $raw;

	if ( ! is_numeric( $raw ) ) {
		return $decimal ? (float) $field['default'] : (int) $field['default'];
	}

	if ( ! $decimal ) {
		return min( (int) ( $field['max'] ?? PHP_INT_MAX ), max( (int) ( $field['min'] ?? 0 ), (int) $raw ) );
	}

	$value = min( (float) ( $field['max'] ?? PHP_FLOAT_MAX ), max( (float) ( $field['min'] ?? 0 ), (float) $raw ) );

	return round( round( $value / $step ) * $step, 4 );
}

/** عدد برای نمایش در فیلد و CSS: «1.80» → «1.8»، «16.0» → «16». */
function hodima_number_text( int|float $value ): string {
	return rtrim( rtrim( number_format( (float) $value, 4, '.', '' ), '0' ), '.' );
}

/** فایل فونت (woff2/woff) از کتابخانه رسانه؛ هر چیز دیگر = ۰. */
function hodima_settings_sanitize_font( mixed $raw ): int {
	$id = absint( is_scalar( $raw ) ? $raw : 0 );
	return ( $id && hodima_is_font_attachment( $id ) ) ? $id : 0;
}

/**
 * کد خام (تب «کد سفارشی»): فقط کاربری که اجازه HTML نامحدود دارد (unfiltered_html؛
 * در چندسایته فقط مدیر کل) می‌تواند عوضش کند؛ برای بقیه مقدار قبلی می‌ماند.
 */
function hodima_settings_sanitize_code( string $key, mixed $raw ): string {

	$raw     = is_string( $raw ) ? trim( $raw ) : '';
	$stored  = get_option( HODIMA_SETTINGS_OPTION, [] );
	$current = is_array( $stored ) ? (string) ( $stored[ $key ] ?? '' ) : '';

	if ( $raw !== $current && ! current_user_can( 'unfiltered_html' ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, "denied_{$key}", sprintf( '«%s» ذخیره نشد: حساب شما اجازه افزودن کد HTML/اسکریپت را ندارد.', hodima_settings_field_label( $key ) ) );
		return $current;
	}

	return $raw;
}

function hodima_settings_sanitize_url( string $key, mixed $raw ): string {

	$raw = is_string( $raw ) ? trim( $raw ) : '';
	if ( '' === $raw ) {
		return '';
	}

	$url = esc_url_raw( $raw, [ 'https', 'http' ] );
	if ( '' === $url || ! wp_http_validate_url( $url ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, "invalid_{$key}", sprintf( 'آدرس وارد شده برای «%s» معتبر نیست و ذخیره نشد.', hodima_settings_field_label( $key ) ) );
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

/**
 * فهرست دامنه (هر خط یکی): «dl.example.com»، «https://dl.example.com/a.mp4» یا
 * «//cdn.example.com» → «https://dl.example.com» (فقط origin). تکراری حذف؛ نامعتبر
 * رد و به کاربر اطلاع داده می‌شود.
 */
function hodima_settings_sanitize_host_list( string $key, mixed $raw ): string {

	$lines   = preg_split( '/\R/u', is_string( $raw ) ? $raw : '' ) ?: [];
	$clean   = [];
	$invalid = [];

	foreach ( $lines as $line ) {

		$line = trim( wp_strip_all_tags( $line ) );
		if ( '' === $line ) {
			continue;
		}

		$url  = preg_match( '#^https?://#i', $line ) ? $line : 'https://' . ltrim( $line, '/' );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		if ( ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $host ) ) {
			$invalid[] = $line;
			continue;
		}

		$port    = wp_parse_url( $url, PHP_URL_PORT );
		$clean[] = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) . '://' . $host . ( $port ? ':' . (int) $port : '' );
	}

	if ( $invalid ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, "invalid_{$key}", sprintf( 'این دامنه‌ها در «%1$s» معتبر نبودند و ذخیره نشدند: %2$s', hodima_settings_field_label( $key ), implode( '، ', $invalid ) ) );
	}

	return implode( "\n", array_values( array_unique( $clean ) ) );
}

/** شماره تلفن: ارقام فارسی/عربی به لاتین؛ فقط ارقام و + ابتدایی. */
function hodima_settings_sanitize_phone( string $key, mixed $raw ): string {

	$raw = is_string( $raw ) ? $raw : '';
	$raw = hodima_latin_digits( $raw );

	$phone = (string) preg_replace( '/(?!^\+)[^\d]/', '', trim( $raw ) );

	if ( '' !== $phone && ! preg_match( '/^\+?\d{5,15}$/', $phone ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, "invalid_{$key}", sprintf( '«%s» معتبر نیست و ذخیره نشد.', hodima_settings_field_label( $key ) ) );
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
