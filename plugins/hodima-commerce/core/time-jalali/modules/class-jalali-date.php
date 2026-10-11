<?php
/**
 * تاریخ شمسی در سراسر سایت و ووکامرس (فیلترهای تاریخ وردپرس).
 *
 * تبدیل و قالب‌بندی در Hodima Core است (includes/jalali.php، hodima_jalali_format)؛ این کلاس فقط
 * تصمیم می‌گیرد کدام تاریخ نمایشی است و شمسی شود و کدام ماشینی است و دست نخورد.
 *
 * @package Hodima_Commerce
 */

declare( strict_types=1 );
namespace Hodima\Core\Time_Jalali\Modules;

defined( 'ABSPATH' ) || exit;

/** فیلترهای تاریخ وردپرس و ووکامرس ← شمسی با رقم فارسی. */
final class Jalali_Date {

	/**
	 * نتیجه قالب‌بندی‌های تکراری در همین درخواست.
	 *
	 * @var array<string, string>
	 */
	private array $cache = [];

	/** بدون Hodima Core (کتابخانه شمسی) ماژول کاری نمی‌کند (Fatal نه). */
	public static function available(): bool {
		return function_exists( 'hodima_jalali_format' );
	}

	/** ثبت فیلترها. */
	public function __construct() {
		// فیلتر «wc_format_datetime» که اینجا بود در ووکامرس وجود ندارد و هرگز اجرا نمی‌شد (حذف شد)؛
		// تاریخ‌های ووکامرس (wc_format_datetime ← date_i18n) از فیلتر date_i18n شمسی می‌شوند.
		add_filter( 'woocommerce_rest_prepare_shop_order_object', [ $this, 'inject_jalali_to_rest_api' ] );
		add_filter( 'wp_date', [ $this, 'convert_wp_date_globally' ], 10, 4 );
		add_filter( 'date_i18n', [ $this, 'convert_date_i18n_globally' ], 10, 3 );
		add_filter( 'get_comment_date', [ $this, 'convert_comment_date' ], 10, 3 );
		add_filter( 'get_comment_time', [ $this, 'convert_comment_time' ], 10, 5 );
		add_filter( 'get_the_date', [ $this, 'convert_post_date' ], 10, 3 );
		add_filter( 'get_the_time', [ $this, 'convert_post_time' ], 10, 3 );
	}

	/*
	 * چهار فیلتر زیر ورودی mixed دارند: get_the_time( 'U' ) و مانندش عدد برمی‌گردانند؛
	 * نوع string آن را به متن تبدیل می‌کرد و کدی که عدد می‌خواست خطا می‌گرفت.
	 */

	/**
	 * تاریخ دیدگاه.
	 *
	 * @param mixed  $date    مقدار وردپرس.
	 * @param string $format  قالب درخواستی ('' = قالب تاریخ سایت).
	 * @param mixed  $comment دیدگاه.
	 */
	public function convert_comment_date( mixed $date, string $format, mixed $comment ): mixed {
		if ( $this->is_machine_format( $format ) || ! isset( $comment->comment_date ) ) {
			return $date;
		}
		return $this->format_local( (string) $comment->comment_date, $format ?: (string) get_option( 'date_format' ), $date );
	}

	/**
	 * ساعت دیدگاه.
	 *
	 * @param mixed  $time      مقدار وردپرس.
	 * @param string $format    قالب درخواستی ('' = قالب ساعت سایت).
	 * @param bool   $gmt       ساعت گرینویچ.
	 * @param bool   $translate ترجمه.
	 * @param mixed  $comment   دیدگاه.
	 */
	public function convert_comment_time( mixed $time, string $format, bool $gmt, bool $translate, mixed $comment ): mixed {
		if ( $this->is_machine_format( $format ) || ! isset( $comment->comment_date ) ) {
			return $time;
		}
		// باگ قبلی: ساعت گرینویچ خواسته‌شده هم از ساعت محلی ساخته می‌شد
		$source = $gmt && isset( $comment->comment_date_gmt ) ? $comment->comment_date_gmt : $comment->comment_date;
		return $this->format_local( (string) $source, $format ?: (string) get_option( 'time_format' ), $time );
	}

	/**
	 * تاریخ نوشته.
	 *
	 * @param mixed  $the_date مقدار وردپرس.
	 * @param string $format   قالب درخواستی.
	 * @param mixed  $post     نوشته.
	 */
	public function convert_post_date( mixed $the_date, string $format, mixed $post ): mixed {
		if ( $this->is_machine_format( $format ) ) {
			return $the_date;
		}
		$post = get_post( $post );
		return $post ? $this->format_local( $post->post_date, $format ?: (string) get_option( 'date_format' ), $the_date ) : $the_date;
	}

	/**
	 * ساعت نوشته.
	 *
	 * @param mixed  $the_time مقدار وردپرس.
	 * @param string $format   قالب درخواستی.
	 * @param mixed  $post     نوشته.
	 */
	public function convert_post_time( mixed $the_time, string $format, mixed $post ): mixed {
		if ( $this->is_machine_format( $format ) ) {
			return $the_time;
		}
		$post = get_post( $post );
		return $post ? $this->format_local( $post->post_date, $format ?: (string) get_option( 'time_format' ), $the_time ) : $the_time;
	}

	/**
	 * فیلتر wp_date: timestamp واقعی + منطقه زمانی مقصد.
	 *
	 * @param string $date      خروجی وردپرس.
	 * @param string $format    قالب.
	 * @param int    $timestamp Unix timestamp.
	 * @param mixed  $timezone  DateTimeZone.
	 */
	public function convert_wp_date_globally( string $date, string $format, int $timestamp, mixed $timezone ): string {
		if ( $this->is_machine_format( $format ) ) {
			return $date;
		}
		return $this->format( $format, $timestamp, $timezone instanceof \DateTimeZone ? $timezone : wp_timezone() );
	}

	/**
	 * فیلتر date_i18n: timestamp از قبل اختلاف ساعت محلی را دارد (UTC بدون جابه‌جایی؛ دوبار جابه‌جا نمی‌شود).
	 *
	 * @param string $date      خروجی وردپرس.
	 * @param string $format    قالب.
	 * @param int    $timestamp timestamp با اختلاف محلی.
	 */
	public function convert_date_i18n_globally( string $date, string $format, int $timestamp ): string {
		if ( $this->is_machine_format( $format ) ) {
			return $date;
		}
		return $this->format( $format, $timestamp, null );
	}

	/**
	 * فیلدهای *_jalali در REST سفارش (date_created_jalali و …) برای برنامه‌های بیرونی.
	 *
	 * @param mixed $response WP_REST_Response.
	 */
	public function inject_jalali_to_rest_api( mixed $response ): mixed {
		if ( ! $response instanceof \WP_REST_Response ) {
			return $response;
		}
		$data   = $response->get_data();
		$format = (string) get_option( 'date_format', 'Y/m/d' );
		foreach ( [ 'date_created', 'date_modified', 'date_completed', 'date_paid' ] as $field ) {
			if ( ! empty( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				// مقدار REST زمان محلی بدون منطقه است («2025-10-11T13:00:00»)
				$local = strtotime( $data[ $field ] . ' UTC' );
				if ( false !== $local ) {
					$data[ $field . '_jalali' ] = $this->format( $format, $local, null );
				}
			}
		}
		$response->set_data( $data );
		return $response;
	}

	/**
	 * قالب ماشین‌خوان (timestamp، ISO 8601، RFC) نباید شمسی یا فارسی شود.
	 * باگ قبلی: get_the_time( 'U' ) به‌جای عدد timestamp حرف «U» (یا رقم فارسی)
	 * برمی‌گرداند؛ ابزارک «فعالیت» پیشخوان وردپرس آن را به gmdate() می‌دهد و با
	 * TypeError کل صفحه پیشخوان از کار می‌افتاد. هر کدی که تاریخ را برای مقایسه
	 * یا ذخیره می‌خواهد همین قالب‌ها را به کار می‌برد.
	 *
	 * باگ قبلی (داده سایت را خراب می‌کرد): ووکامرس مقدار فیلدهای فرم پیشخوان را
	 * با date_i18n( 'Y-m-d' ) و date_i18n( 'H' / 'i' / 's' ) پر می‌کند (تاریخ و ساعت
	 * سفارش، تاریخ حراج محصول و تنوع، انقضای دانلود). شمسی‌شده‌اش («۱۴۰۴-۰۷-۱۹»)
	 * هنگام ذخیره با strtotime خوانده نمی‌شد و تاریخ سفارش ۱۹۷۰-۰۱-۰۱ و تاریخ
	 * حراج خراب ذخیره می‌شد. قالب میلادی ISO/MySQL (با خط تیره، «Y-m-d…» و
	 * «Ymd…») و جزء تنهای ساعت هیچ‌جا قالب نمایش فارسی نیستند (نمایش با «/» یا
	 * نام ماه است)، پس همیشه ماشینی‌اند. «Y» و «F» تنها عمدا نمایشی می‌مانند
	 * (سال/ماه شمسی در عنوان‌ها و مسیر راهنما).
	 *
	 * @param string $format قالب.
	 */
	private function is_machine_format( string $format ): bool {
		static $machine = null;
		$machine      ??= [ 'U', 'G', 'u', 'v', 'Z', 'c', 'r', 'H', 'i', 's', DATE_ATOM, DATE_COOKIE, DATE_ISO8601, DATE_RFC822, DATE_RFC850, DATE_RFC1036, DATE_RFC1123, DATE_RFC7231, DATE_RFC2822, DATE_RFC3339, DATE_RFC3339_EXTENDED, DATE_RSS, DATE_W3C ];
		return in_array( $format, $machine, true )
			|| str_contains( $format, '\\T' )
			|| str_starts_with( $format, 'Y-m-d' )
			|| str_starts_with( $format, 'Ymd' );
	}

	/**
	 * زمان محلی متنی پایگاه داده («2025-10-11 13:00:00») ← شمسی.
	 *
	 * @param string $local    زمان محلی.
	 * @param string $format   قالب.
	 * @param mixed  $fallback مقدار وردپرس اگر زمان خوانده نشد.
	 */
	private function format_local( string $local, string $format, mixed $fallback ): mixed {
		$timestamp = strtotime( $local . ' UTC' );
		return false === $timestamp ? $fallback : $this->format( $format, $timestamp, null );
	}

	/**
	 * قالب‌بندی شمسی با حافظه درخواست.
	 *
	 * @param string             $format    قالب.
	 * @param int                $timestamp Unix timestamp.
	 * @param \DateTimeZone|null $timezone  منطقه (null = UTC).
	 */
	private function format( string $format, int $timestamp, ?\DateTimeZone $timezone ): string {
		$key = $format . '|' . $timestamp . '|' . ( $timezone ? $timezone->getName() : '' );
		return $this->cache[ $key ] ??= hodima_jalali_format( $format, $timestamp, $timezone );
	}
}
