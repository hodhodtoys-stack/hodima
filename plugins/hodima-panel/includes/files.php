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
 * نگهداری و نمایش فایل‌های سفارش در پوشه خصوصی.
 *
 * فیش بانکی اطلاعات مالی شخصی است (نام، شماره کارت، مبلغ). پنل قدیمی فایل را با
 * نام اصلی در پوشه عمومی uploads/سال/ماه می‌گذاشت (آدرس قابل حدس، هر نوع فایل مجاز
 * وردپرس). حالا:
 * - پوشه جدا (`uploads/hodima-panel-private` یا ثابت HODIMA_PANEL_PRIVATE_DIR بیرون از
 *   ریشه سایت) با .htaccess «همه ممنوع» و نام تصادفی ۲۴ حرفی (روی Nginx که .htaccess
 *   را نمی‌خواند، نام حدس‌زدنی نیست)؛
 * - فقط JPG، PNG، WEBP و PDF تا ۵ مگابایت، با بررسی محتوای واقعی فایل (نه فقط پسوند)؛
 * - نمایش فقط از راه آدرس ?hodima_panel_file=شناسه برای صاحب سفارش یا مدیر.
 */
final class Files {

	public const INVOICE = 'invoice';

	public const RECEIPT = 'receipt';

	/** بیشترین تعداد فایل از هر نوع برای یک سفارش. */
	public const MAX_PER_KIND = 9;

	/** بیشترین اندازه هر فایل (بایت). */
	public const MAX_BYTES = 5 * MB_IN_BYTES;

	/** پسوندهای مجاز => نوع. */
	public const MIMES = [
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'pdf'          => 'application/pdf',
	];

	public const QUERY_VAR = 'hodima_panel_file';

	/** ثبت هوک نمایش فایل. */
	public static function boot(): void {
		add_action( 'init', [ self::class, 'serve' ], 1 );
	}

	/** پوشه خصوصی (بدون اسلش پایانی)، ساخته‌شده و بسته. */
	public static function dir(): string {
		$dir = defined( 'HODIMA_PANEL_PRIVATE_DIR' ) && '' !== (string) HODIMA_PANEL_PRIVATE_DIR
			? (string) HODIMA_PANEL_PRIVATE_DIR
			: (string) wp_upload_dir( null, false )['basedir'] . '/hodima-panel-private';
		$dir = untrailingslashit( wp_normalize_path( $dir ) );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_file( $dir . '/.htaccess' ) ) {
			// Apache 2.4 و 2.2؛ بی‌اثر روی Nginx (آنجا نام تصادفی محافظ است)
			file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- فایل کوچک پیکربندی.
		}
		if ( ! is_file( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}

	/**
	 * پیام خطای آپلود برای کاربر (کلید => متن).
	 *
	 * @return array<string, string>
	 */
	public static function errors(): array {
		return [
			'file_missing' => 'فایلی انتخاب نشده است.',
			'file_size'    => 'حجم فایل بیشتر از ۵ مگابایت است.',
			'file_type'    => 'فقط فایل JPG، PNG، WEBP یا PDF پذیرفته می‌شود.',
			'file_limit'   => 'حداکثر ۹ فایل از هر نوع برای هر سفارش.',
			'file_save'    => 'ذخیره فایل ناموفق بود؛ دوباره تلاش کنید.',
		];
	}

	/**
	 * ذخیره یک فایل آپلودشده (یک ورودی $_FILES).
	 *
	 * @param int                  $order_id شناسه سفارش.
	 * @param string               $kind     invoice|receipt.
	 * @param array<string, mixed> $file     ورودی $_FILES['…'].
	 * @return int|string شناسه ردیف، یا کلید خطا از errors().
	 */
	public static function upload( int $order_id, string $kind, array $file ): int|string {
		if ( UPLOAD_ERR_NO_FILE === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) ) {
			return 'file_missing';
		}
		if ( in_array( (int) $file['error'], [ UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ], true ) ) {
			return 'file_size';
		}
		if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return 'file_save';
		}
		return self::store( $order_id, $kind, (string) $file['tmp_name'], (string) ( $file['name'] ?? '' ), true );
	}

	/**
	 * بررسی و انتقال فایل به پوشه خصوصی.
	 *
	 * @param int    $order_id شناسه سفارش.
	 * @param string $kind     invoice|receipt.
	 * @param string $tmp      مسیر فایل موقت.
	 * @param string $name     نام اصلی فایل.
	 * @param bool   $uploaded true = فایل آپلود HTTP (move_uploaded_file)؛ false = کپی (فقط آزمون).
	 * @return int|string شناسه ردیف، یا کلید خطا.
	 */
	public static function store( int $order_id, string $kind, string $tmp, string $name, bool $uploaded ): int|string {
		global $wpdb;

		if ( self::count( $order_id, $kind ) >= self::MAX_PER_KIND ) {
			return 'file_limit';
		}
		$size = is_file( $tmp ) ? (int) filesize( $tmp ) : 0;
		if ( $size <= 0 ) {
			return 'file_save';
		}
		if ( $size > self::MAX_BYTES ) {
			return 'file_size';
		}
		$mime = self::detect_mime( $tmp, $name );
		if ( null === $mime ) {
			return 'file_type';
		}

		$ext    = 'application/pdf' === $mime ? 'pdf' : ( 'image/png' === $mime ? 'png' : ( 'image/webp' === $mime ? 'webp' : 'jpg' ) );
		$stored = gmdate( 'Y' ) . '/' . strtolower( wp_generate_password( 24, false ) ) . '.' . $ext;
		$target = self::dir() . '/' . $stored;
		wp_mkdir_p( dirname( $target ) );
		// پوشه سال هم بی فهرست (روی سروری که فهرست پوشه روشن است و .htaccess را نمی‌خواند)
		if ( ! is_file( dirname( $target ) . '/index.php' ) ) {
			file_put_contents( dirname( $target ) . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$moved = $uploaded ? move_uploaded_file( $tmp, $target ) : copy( $tmp, $target );
		if ( ! $moved ) {
			return 'file_save';
		}
		chmod( $target, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- فایل خصوصی.

		$ok = $wpdb->insert(
			table( 'order_files' ),
			[
				'order_id'    => $order_id,
				'kind'        => $kind,
				'file'        => $stored,
				'name'        => sanitize_file_name( '' !== $name ? $name : 'file.' . $ext ),
				'mime'        => $mime,
				'uploaded_by' => get_current_user_id(),
				'created_at'  => now(),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);
		if ( ! $ok ) {
			wp_delete_file( $target );
			return 'file_save';
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * نوع واقعی فایل از محتوایش (پسوند باید با محتوا بخواند).
	 *
	 * @param string $path مسیر فایل.
	 * @param string $name نام اصلی (برای پسوند).
	 * @return string|null نوع مجاز، یا null.
	 */
	public static function detect_mime( string $path, string $name ): ?string {
		$by_name = wp_check_filetype( $name, self::MIMES )['type'];
		if ( ! is_string( $by_name ) || '' === $by_name ) {
			return null;
		}
		if ( 'application/pdf' === $by_name ) {
			$head = (string) file_get_contents( $path, false, null, 0, 5 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- فایل محلی.
			return '%PDF-' === $head ? 'application/pdf' : null;
		}
		$info = wp_getimagesize( $path );
		$real = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
		// پسوند اشتباه ولی تصویر واقعی مجاز (مثلا .jpg که PNG است) پذیرفته و با پسوند درست ذخیره می‌شود
		return in_array( $real, self::MIMES, true ) && 'application/pdf' !== $real ? $real : null;
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

		$out = [];
		foreach ( (array) $rows as $row ) {
			$row->url                      = self::url( (int) $row->id );
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
	 * آدرس نمایش یک فایل (فقط برای صاحب سفارش یا مدیر کار می‌کند).
	 *
	 * @param int $file_id شناسه ردیف.
	 */
	public static function url( int $file_id ): string {
		return add_query_arg( self::QUERY_VAR, $file_id, home_url( '/' ) );
	}

	/**
	 * فایل برای یک کاربر، اگر اجازه دیدنش را دارد.
	 *
	 * @param int $file_id شناسه ردیف.
	 * @param int $user_id کاربر.
	 * @return array{path: string, mime: string, name: string}|null
	 */
	public static function resolve( int $file_id, int $user_id ): ?array {
		global $wpdb;
		if ( $file_id <= 0 || $user_id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT f.file, f.mime, f.name, o.user_id FROM %i f INNER JOIN %i o ON o.id = f.order_id WHERE f.id = %d',
				table( 'order_files' ),
				table( 'orders' ),
				$file_id
			)
		);
		if ( ! $row || ( (int) $row->user_id !== $user_id && ! user_can( $user_id, 'manage_options' ) ) ) {
			return null;
		}
		$path = self::path( (string) $row->file );
		return null !== $path && is_file( $path ) ? [ 'path' => $path, 'mime' => (string) $row->mime, 'name' => (string) $row->name ] : null;
	}

	/**
	 * مسیر کامل یک فایل ذخیره‌شده (فقط داخل پوشه خصوصی).
	 *
	 * @param string $stored مقدار ستون file.
	 */
	public static function path( string $stored ): ?string {
		if ( '' === $stored || str_contains( $stored, '..' ) || str_starts_with( $stored, '/' ) ) {
			return null;
		}
		return self::dir() . '/' . $stored;
	}

	/** پاسخ به ?hodima_panel_file=شناسه: فایل برای صاحبش، وگرنه ۴۰۴. */
	public static function serve(): void {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- دسترسی با مالکیت سنجیده می‌شود.
			return;
		}
		$file_id = absint( $_GET[ self::QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Page::url() );
			exit;
		}
		$file = self::resolve( $file_id, get_current_user_id() );
		nocache_headers();
		if ( null === $file ) {
			status_header( 404 );
			wp_die( 'فایل پیدا نشد یا اجازه دیدنش را ندارید.', '', [ 'response' => 404 ] );
		}
		$disposition = str_starts_with( $file['mime'], 'image/' ) || 'application/pdf' === $file['mime'] ? 'inline' : 'attachment';
		header( 'Content-Type: ' . $file['mime'] );
		header( 'Content-Length: ' . (string) filesize( $file['path'] ) );
		// نام فارسی فقط با filename* (RFC 6266) درست می‌رسد؛ filename ساده فقط حروف لاتین امن
		$name  = sanitize_file_name( $file['name'] );
		$ascii = (string) preg_replace( '/[^A-Za-z0-9._-]/', '', $name );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . ( '' !== trim( $ascii, '.' ) ? $ascii : 'file' ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		readfile( $file['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- فرستادن فایل خصوصی.
		exit;
	}

	/**
	 * حذف همه فایل‌های یک سفارش (خود فایل و ردیف).
	 *
	 * @param int $order_id شناسه سفارش.
	 */
	public static function delete_for_order( int $order_id ): void {
		global $wpdb;
		foreach ( self::for_order( $order_id ) as $row ) {
			$path = self::path( (string) $row->file );
			if ( null !== $path && is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
		$wpdb->delete( table( 'order_files' ), [ 'order_id' => $order_id ], [ '%d' ] );
	}
}
