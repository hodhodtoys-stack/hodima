<?php
/**
 * توابع مشترک پنل: نام جدول‌ها، زمان، وضعیت سفارش‌ها.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * نام کامل جدول پنل (با پیشوند وردپرس).
 *
 * همه جدول‌ها یک پیشوند واحد دارند (hodima_panel_). پنل قدیمی نام‌های پراکنده
 * داشت و داشبوردش دو جدولِ ناموجود (user_hodima_order_items، support_inbox_cache)
 * را می‌خواند؛ این تابع تنها جای نام جدول‌هاست.
 *
 * @param string $name orders|order_items|order_files|chats|chat_messages|tickets|ticket_messages.
 */
function table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'hodima_panel_' . $name;
}

/**
 * زمان فعلی برای ذخیره: همیشه UTC.
 *
 * پنل قدیمی بعضی ستون‌ها را با پیش‌فرض MySQL (منطقه زمانی سرور) و بعضی را
 * با current_time('mysql') (ساعت ایران) پر می‌کرد و داشبورد زمان محلی را دوباره
 * با wp_date جابه‌جا می‌کرد؛ نتیجه: ساعت‌ها تا ۳:۳۰ جابه‌جا.
 */
function now(): string {
	return current_time( 'mysql', true );
}

/**
 * نمایش زمان ذخیره‌شده (UTC) به ساعت سایت.
 *
 * @param string|null $gmt    مقدار ستون تاریخ (UTC).
 * @param string      $format قالب نمایش.
 */
function show_date( ?string $gmt, string $format = 'Y/m/d H:i' ): string {
	if ( null === $gmt || '' === $gmt || str_starts_with( $gmt, '0000' ) ) {
		return '';
	}
	$time = strtotime( $gmt . ' UTC' );
	return false === $time ? '' : (string) wp_date( $format, $time );
}

/**
 * نسخه فایل استاتیک از زمان تغییرش (کش مرورگر با هر تغییر باطل می‌شود).
 *
 * @param string $relative مسیر نسبت به پوشه افزونه.
 */
function asset_version( string $relative ): string {
	$path = HODIMA_PANEL_DIR . '/' . ltrim( $relative, '/' );
	return is_file( $path ) ? (string) filemtime( $path ) : HODIMA_PANEL_VERSION;
}

/** آیا ووکامرس فعال است؟ (پیش‌خرید و پیش‌فاکتور محصولات ووکامرس را می‌خواهند) */
function wc_active(): bool {
	return function_exists( 'wc_get_product' );
}

/**
 * وضعیت‌های سفارش پنل.
 *
 * @return array<string, string> کلید => برچسب.
 */
function order_statuses(): array {
	return [
		'pending'          => 'در انتظار بررسی',
		'awaiting_payment' => 'در انتظار پرداخت',
		'receipt_uploaded' => 'در حال بررسی فیش',
		'completed'        => 'تکمیل شده',
		'cancelled'        => 'لغو شده',
	];
}

/**
 * نوع‌های سفارش پنل.
 *
 * @return array<string, string> کلید => برچسب.
 */
function order_types(): array {
	return [
		'prebuy'     => 'پیش‌خرید',
		'preinvoice' => 'پیش‌فاکتور',
	];
}

/**
 * برچسب فارسی یک وضعیت (کلید ناشناخته همان کلید).
 *
 * @param string $status کلید وضعیت.
 */
function status_label( string $status ): string {
	return order_statuses()[ $status ] ?? $status;
}

/**
 * برچسب فارسی یک نوع سفارش.
 *
 * @param string $type کلید نوع.
 */
function type_label( string $type ): string {
	return order_types()[ $type ] ?? $type;
}

/**
 * دپارتمان‌های تیکت پشتیبانی.
 *
 * @return array<string, string> کلید => برچسب.
 */
function ticket_departments(): array {
	return [
		'sales'     => 'واحد فروش',
		'finance'   => 'واحد مالی',
		'purchase'  => 'واحد خرید',
		'warehouse' => 'واحد انبار',
		'feedback'  => 'انتقادات و پیشنهادات',
	];
}

/**
 * اولویت‌های تیکت پشتیبانی.
 *
 * @return array<string, string> کلید => برچسب.
 */
function ticket_priorities(): array {
	return [
		'normal' => 'متوسط',
		'high'   => 'زیاد',
		'low'    => 'کم',
	];
}

/**
 * وضعیت‌های تیکت پشتیبانی.
 *
 * @return array<string, string> کلید => برچسب.
 */
function ticket_statuses(): array {
	return [
		'open'     => 'در انتظار پاسخ',
		'answered' => 'پاسخ داده شده',
		'closed'   => 'بسته شده',
	];
}

/**
 * پیش‌نمایش کوتاه یک پیام برای فهرست‌ها.
 *
 * @param string $text متن پیام.
 * @param int    $max  بیشترین تعداد حرف.
 */
function preview( string $text, int $max = 80 ): string {
	$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max ) . '…' : $text;
}

/** پاک کردن کش قدیمی عدد منو (از مرحله ۶ عدد کنار منو نیست؛ نسخه‌های 0.1.x تا 0.1.7 ذخیره‌اش می‌کردند). */
function clear_admin_badge(): void {
	delete_transient( 'hodima_panel_badge_count' );
}
