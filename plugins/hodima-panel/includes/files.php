<?php
/**
 * فایل‌های هر سفارش: پیش‌فاکتورِ مدیر و فیش واریزی مشتری.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * دسترسی به جدول order_files.
 *
 * مسیر فایل نسبت به پوشه uploads ذخیره می‌شود (نه آدرس کامل) تا جابه‌جایی
 * فایل‌ها به پوشه محافظت‌شده در مرحله بعد فقط همین کلاس را عوض کند.
 */
final class Files {

	public const INVOICE = 'invoice';

	public const RECEIPT = 'receipt';

	/** بیشترین تعداد فایل از هر نوع برای یک سفارش. */
	public const MAX_PER_KIND = 9;

	/**
	 * ثبت فایلی که wp_handle_upload ذخیره کرده است.
	 *
	 * @param int                  $order_id شناسه سفارش.
	 * @param string               $kind     invoice|receipt.
	 * @param array<string, mixed> $upload   خروجی موفق wp_handle_upload (file، url، type).
	 * @param string               $name     نام اصلی فایل.
	 * @return int شناسه ردیف (۰ = شکست).
	 */
	public static function add( int $order_id, string $kind, array $upload, string $name ): int {
		global $wpdb;

		$base = wp_normalize_path( (string) wp_upload_dir()['basedir'] );
		$file = wp_normalize_path( (string) ( $upload['file'] ?? '' ) );
		if ( '' === $file || ! str_starts_with( $file, $base ) ) {
			return 0;
		}

		$ok = $wpdb->insert(
			table( 'order_files' ),
			[
				'order_id'    => $order_id,
				'kind'        => $kind,
				'file'        => ltrim( substr( $file, strlen( $base ) ), '/' ),
				'name'        => sanitize_file_name( $name ),
				'mime'        => (string) ( $upload['type'] ?? '' ),
				'uploaded_by' => get_current_user_id(),
				'created_at'  => now(),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * فایل‌های چند سفارش، دسته‌بندی‌شده با شناسه سفارش.
	 *
	 * @param int[]       $order_ids شناسه‌ها.
	 * @param string|null $kind      فقط یک نوع (null = همه).
	 * @return array<int, list<object>> order_id => ردیف‌ها (هر ردیف با ویژگی url).
	 */
	public static function for_orders( array $order_ids, ?string $kind = null ): array {
		global $wpdb;

		$order_ids = array_values( array_filter( array_map( 'intval', $order_ids ) ) );
		if ( ! $order_ids ) {
			return [];
		}

		$in   = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$args = [ table( 'order_files' ), ...$order_ids ];
		$sql  = "SELECT id, order_id, kind, file, name, mime, created_at FROM %i WHERE order_id IN ($in)";
		if ( null !== $kind ) {
			$sql   .= ' AND kind = %s';
			$args[] = $kind;
		}
		$sql .= ' ORDER BY id ASC';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $in فقط %d است.

		$base = (string) wp_upload_dir()['baseurl'];
		$out  = [];
		foreach ( (array) $rows as $row ) {
			$row->url                        = $base . '/' . $row->file;
			$out[ (int) $row->order_id ][] = $row;
		}
		return $out;
	}

	/**
	 * فایل‌های یک سفارش.
	 *
	 * @param int         $order_id شناسه سفارش.
	 * @param string|null $kind     فقط یک نوع.
	 * @return list<object>
	 */
	public static function for_order( int $order_id, ?string $kind = null ): array {
		return self::for_orders( [ $order_id ], $kind )[ $order_id ] ?? [];
	}

	/**
	 * تعداد فایل‌های یک نوع.
	 *
	 * @param int    $order_id شناسه سفارش.
	 * @param string $kind     invoice|receipt.
	 */
	public static function count( int $order_id, string $kind ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE order_id = %d AND kind = %s', table( 'order_files' ), $order_id, $kind )
		);
	}

	/**
	 * حذف همه فایل‌های یک سفارش (خود فایل و ردیف).
	 *
	 * @param int $order_id شناسه سفارش.
	 */
	public static function delete_for_order( int $order_id ): void {
		global $wpdb;

		$base = wp_normalize_path( (string) wp_upload_dir()['basedir'] );
		foreach ( self::for_order( $order_id ) as $row ) {
			$path = wp_normalize_path( $base . '/' . $row->file );
			// فقط داخل uploads؛ مسیر با ../ از پوشه بیرون نمی‌زند
			if ( str_starts_with( $path, $base . '/' ) && ! str_contains( (string) $row->file, '..' ) && is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
		$wpdb->delete( table( 'order_files' ), [ 'order_id' => $order_id ], [ '%d' ] );
	}
}
