<?php
/**
 * Theme Settings — خواندن تنظیمات (hodima_settings، hodima_setting) و برچسب کامل فیلد
 * Path: hodima/inc/theme-settings/reader.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * همه تنظیمات، ادغام‌شده با پیش‌فرض‌ها.
 *
 * @return array<string, mixed>
 */
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

	$cache = $settings;

	return $cache;
}

/** یک مقدار از تنظیمات. */
function hodima_setting( string $key ): mixed {
	return hodima_settings()[ $key ] ?? null;
}

/**
 * تب یک فیلد (از قابش).
 *
 * @param array<string, mixed> $field
 */
function hodima_settings_field_section( array $field ): string {
	return (string) ( hodima_settings_panels()[ $field['panel'] ?? '' ]['section'] ?? '' );
}

/**
 * نام کامل فیلد برای پیام خطا، مثل «فوتر › نماد ۲ › لینک».
 * برچسب‌ها داخل قاب کوتاه‌اند («لینک»)؛ بیرون از قاب مبهم می‌شدند.
 */
function hodima_settings_field_label( string $key ): string {

	$field = hodima_settings_fields()[ $key ] ?? null;
	if ( null === $field ) {
		return $key;
	}

	$panel = hodima_settings_panels()[ $field['panel'] ] ?? [];
	$parts = [
		hodima_settings_sections()[ hodima_settings_field_section( $field ) ]['title'] ?? '',
		isset( $field['group'] ) ? ( hodima_settings_groups()[ $field['group'] ]['title'] ?? '' ) : ( $panel['title'] ?? '' ),
		$field['label'],
	];

	// بدون تکه خالی و تکرار پشت‌سرهم (ردیف «اینستاگرام» با برچسب «اینستاگرام»)
	$parts = array_values( array_filter( $parts, static fn( string $part ): bool => '' !== $part ) );
	$parts = array_filter( $parts, static fn( string $part, int $i ): bool => 0 === $i || $part !== $parts[ $i - 1 ], ARRAY_FILTER_USE_BOTH );

	return implode( ' › ', $parts );
}
