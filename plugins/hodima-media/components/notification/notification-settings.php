<?php
/**
 * تنظیمات اعلان‌ها: تنها منبع فیلدها، پیش‌فرض‌ها و پاک‌سازی.
 *
 * قبلا فهرست پیش‌فرض‌ها جدا در کادر ویرایش و نمایش سایت تکرار شده بود و
 * از هم جدا افتاده بودند (کلید «سایه» فقط در یکی، «رنگ پس‌زمینه» در فهرست
 * ذخیره نبود و هرگز ذخیره نمی‌شد). کادر، ذخیره و نمایش همه از همین می‌خوانند.
 *
 * نام متاها (`_hd_notif_*`) و پست‌تایپ `hd_notification` داده سایت‌اند و
 * عوض نمی‌شوند.
 *
 * @package Hodima\Media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** پست‌تایپ اعلان‌ها. */
const HODIMA_NOTIFICATION_POST_TYPE = 'hd_notification';

/** کش فهرست اعلان‌های فعال (ترنزینت). */
const HODIMA_NOTIFICATION_CACHE = 'hd_active_notifications';

/**
 * همه فیلدهای یک اعلان: کلید (بدون `_` متا) ← نوع و پیش‌فرض.
 *
 * نوع‌ها: bool ('1'/'0')، int (با min/max)، priority (۰۱ تا ۹۹)، url، color،
 * date (Y-m-d میلادی)، text، lines (هر خط یک آدرس)، choice (یکی از choices)، id (پیوست).
 *
 * @return array<string, array{type: string, default: string, min?: int, max?: int, choices?: list<string>}>
 */
function hodima_notification_fields(): array {
	return [
		'hd_notif_is_active'          => [ 'type' => 'bool', 'default' => '1' ],
		'hd_notif_priority'           => [ 'type' => 'priority', 'default' => '01' ],
		'hd_notif_bg_image'           => [ 'type' => 'url', 'default' => '' ],
		// شناسه پیوست (تازه): اندازه‌های کوچک‌تر (srcset) برای موبایل؛ اعلان قدیمی فقط آدرس دارد
		'hd_notif_bg_image_id'        => [ 'type' => 'id', 'default' => '0' ],
		'hd_notif_main_link'          => [ 'type' => 'url', 'default' => '' ],
		'hd_notif_bg_transparent'     => [ 'type' => 'bool', 'default' => '0' ],
		'hd_notif_bg_color'           => [ 'type' => 'color', 'default' => '#ffffff' ],
		'hd_notif_position'           => [ 'type' => 'choice', 'default' => 'center', 'choices' => hodima_notification_positions() ],
		// پاپ‌آپ وسط در موبایل: «modal» همان پنجره وسط؛ «corner» جعبه گوشه پایین (توصیه گوگل)
		'hd_notif_mobile_mode'        => [ 'type' => 'choice', 'default' => 'modal', 'choices' => [ 'modal', 'corner' ] ],
		'hd_notif_width'              => [ 'type' => 'int', 'default' => '400', 'min' => 100, 'max' => 2000 ],
		'hd_notif_height'             => [ 'type' => 'int', 'default' => '0', 'min' => 0, 'max' => 2000 ],
		'hd_notif_close_btn'          => [ 'type' => 'bool', 'default' => '1' ],
		'hd_notif_close_color'        => [ 'type' => 'color', 'default' => '#ff0000' ],
		'hd_notif_anim_in'            => [ 'type' => 'choice', 'default' => 'zoom_in', 'choices' => [ 'zoom_in', 'fade_in', 'slide_up', 'bounce' ] ],
		'hd_notif_anim_out'           => [ 'type' => 'choice', 'default' => 'fade_out', 'choices' => [ 'fade_out', 'slide_down' ] ],
		'hd_notif_delay'              => [ 'type' => 'int', 'default' => '0', 'min' => 0, 'max' => 3600 ],
		'hd_notif_trigger_exit'       => [ 'type' => 'bool', 'default' => '0' ],
		'hd_notif_trigger_scroll'     => [ 'type' => 'int', 'default' => '0', 'min' => 0, 'max' => 100 ],
		'hd_notif_trigger_inactivity' => [ 'type' => 'int', 'default' => '0', 'min' => 0, 'max' => 3600 ],
		'hd_notif_auto_close_time'    => [ 'type' => 'int', 'default' => '0', 'min' => 0, 'max' => 3600 ],
		'hd_notif_device'             => [ 'type' => 'choice', 'default' => 'all', 'choices' => [ 'all', 'desktop', 'mobile' ] ],
		'hd_notif_user_status'        => [ 'type' => 'choice', 'default' => 'all', 'choices' => [ 'all', 'logged_in', 'logged_out' ] ],
		'hd_notif_target_role'        => [ 'type' => 'choice', 'default' => 'all', 'choices' => [ 'all', 'customer', 'subscriber', 'administrator' ] ],
		'hd_notif_target_post_type'   => [ 'type' => 'choice', 'default' => 'all', 'choices' => [ 'all', 'post', 'page', 'product' ] ],
		'hd_notif_target_type'        => [ 'type' => 'choice', 'default' => 'all', 'choices' => [ 'all', 'specific', 'exclude' ] ],
		'hd_notif_target_page'        => [ 'type' => 'lines', 'default' => '' ],
		'hd_notif_target_utm'         => [ 'type' => 'text', 'default' => '' ],
		'hd_notif_target_referrer'    => [ 'type' => 'text', 'default' => '' ],
		// بازدیدکننده‌ای که همین الان از نتیجه جستجو (گوگل، بینگ…) آمده، در صفحه ورود نبیند
		'hd_notif_skip_search'        => [ 'type' => 'bool', 'default' => '0' ],
		'hd_notif_start_date'         => [ 'type' => 'date', 'default' => '' ],
		'hd_notif_end_date'           => [ 'type' => 'date', 'default' => '' ],
		'hd_notif_show_count'         => [ 'type' => 'int', 'default' => '0', 'min' => 0, 'max' => 1000 ],
	];
}

/**
 * موقعیت‌هایی که فرم و CSS دارند.
 *
 * @return list<string>
 */
function hodima_notification_positions(): array {
	return [ 'center', 'bottom_right', 'bottom_left' ];
}

/**
 * موقعیت ذخیره‌شده ← موقعیتی که سایت ظاهرش را دارد. «نوار بالا/پایین» و
 * «وسط کناره‌ها» در نسخه‌های قدیمی ذخیره می‌شدند ولی هرگز CSS نداشتند؛
 * به گوشه پایین همان سمت (غیرمسدودکننده) می‌روند.
 */
function hodima_notification_normalize_position( string $position ): string {
	return match ( true ) {
		in_array( $position, hodima_notification_positions(), true ) => $position,
		str_ends_with( $position, '_left' ) => 'bottom_left',
		default => 'bottom_right',
	};
}

/**
 * یک مقدار ورودی ← مقدار پاک ذخیره‌شدنی (رشته، مثل متاهای قدیمی).
 *
 * @param array{type: string, default: string, min?: int, max?: int, choices?: list<string>} $field تعریف فیلد.
 * @param mixed                                                                               $raw   مقدار خام (بدون slash).
 */
function hodima_notification_sanitize( array $field, mixed $raw ): string {
	$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';

	return match ( $field['type'] ) {
		'bool'     => in_array( $value, [ '1', 'on', 'yes', 'true' ], true ) ? '1' : '0',
		'int'      => (string) min( $field['max'] ?? PHP_INT_MAX, max( $field['min'] ?? 0, absint( $value ) ) ),
		'id'       => (string) absint( $value ),
		// همیشه دو رقمی (۰۱ تا ۹۹): مرتب‌سازی قدیمی رشته‌ای هم درست بماند
		'priority' => str_pad( (string) min( 99, max( 1, absint( $value ) ) ), 2, '0', STR_PAD_LEFT ),
		'url'      => esc_url_raw( $value ),
		'color'    => (string) ( sanitize_hex_color( $value ) ?? $field['default'] ),
		// فقط تاریخ معتبر (مقایسه تاریخ‌ها رشته‌ای است)
		'date'     => ( 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) ? $value : '',
		'choice'   => in_array( $value, $field['choices'] ?? [], true ) ? $value : $field['default'],
		'lines'    => hodima_notification_sanitize_lines( $value ),
		default    => sanitize_text_field( $value ),
	};
}

/** فهرست آدرس‌ها/شناسه‌ها، هر خط یکی، حداکثر ۵۰ خط. */
function hodima_notification_sanitize_lines( string $value ): string {
	$lines = preg_split( '/\R/u', $value ) ?: [];
	$lines = array_filter( array_map( static fn( string $line ): string => sanitize_text_field( $line ), $lines ), static fn( string $line ): bool => '' !== $line );
	return implode( "\n", array_slice( array_values( $lines ), 0, 50 ) );
}

/**
 * همه تنظیمات یک اعلان (متای خالی = پیش‌فرض).
 *
 * @return array<string, string>
 */
function hodima_notification_meta( int $post_id ): array {
	$meta = [];
	foreach ( hodima_notification_fields() as $key => $field ) {
		$value        = get_post_meta( $post_id, '_' . $key, true );
		$meta[ $key ] = is_scalar( $value ) && '' !== (string) $value ? (string) $value : $field['default'];
	}
	return $meta;
}

/** کش فهرست اعلان‌های فعال را پاک کن (هر تغییری که روی نمایش اثر دارد). */
function hodima_notification_clear_cache(): void {
	delete_transient( HODIMA_NOTIFICATION_CACHE );
}

/** نسخه فایل برای کش مرورگر (زمان تغییر فایل). */
function hodima_notification_asset_version( string $file ): string {
	$path = HODIMA_MEDIA_DIR . '/components/notification/assets/' . $file;
	return is_file( $path ) ? (string) filemtime( $path ) : '3.0.0';
}

/** آدرس فایل دارایی اعلان‌ها. */
function hodima_notification_asset_url( string $file ): string {
	return HODIMA_MEDIA_URL . '/components/notification/assets/' . $file;
}
