<?php
/**
 * سفارش‌های پنل: قانون‌ها (چه کسی، در چه وضعیتی)، اعتبارسنجی اقلام، ثبت، فیش، حذف، گفتگو.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * منطق سفارش‌ها؛ تنها جای قانون وضعیت.
 *
 * پنل قدیمی فقط دکمه‌ها را پنهان می‌کرد و سرور هیچ وضعیتی را بررسی نمی‌کرد:
 * مشتری می‌توانست سفارش تأییدشده را ویرایش یا حذف کند (فیش‌ها و گفتگو هم پاک
 * می‌شد) و روی سفارش لغوشده/تکمیل‌شده فیش بفرستد تا وضعیتش به «بررسی فیش»
 * برگردد. حداقل سفارش و مضرب بودن هم فقط در مرورگر سنجیده می‌شد.
 */
final class Orders {

	/** واحدهایی که فقط «حداقل» دارند (بقیه باید مضرب حداقل باشند؛ همان قاعده order-form.js). */
	public const MINIMUM_UNITS = [ 'کارتن', 'جین', 'بسته' ];

	/** بیشترین تعداد هر قلم (ورودی ناممکن را رد می‌کند). */
	public const MAX_QTY = 100000;

	/** ثبت هوک‌ها. */
	public static function boot(): void {
		add_action( 'admin_post_save_preinvoice', [ self::class, 'handle_form' ] );
		add_action( 'admin_post_save_prebuy', [ self::class, 'handle_form' ] );
		add_action( 'hodima_panel_request', [ self::class, 'handle_request' ] );
	}

	/**
	 * یک سفارش.
	 *
	 * @param int $order_id شناسه.
	 */
	public static function get( int $order_id ): ?object {
		global $wpdb;
		$row = $order_id > 0 ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', table( 'orders' ), $order_id ) ) : null;
		return is_object( $row ) ? $row : null;
	}

	/**
	 * سفارشِ مال یک کاربر (وگرنه null).
	 *
	 * @param int $order_id شناسه.
	 * @param int $user_id  کاربر.
	 */
	public static function owned( int $order_id, int $user_id ): ?object {
		$order = self::get( $order_id );
		return ( $order && $user_id > 0 && (int) $order->user_id === $user_id ) ? $order : null;
	}

	/**
	 * مشتری می‌تواند ویرایش/حذف کند؟ فقط پیش از بررسی مدیر.
	 *
	 * @param object $order ردیف سفارش.
	 */
	public static function can_edit( object $order ): bool {
		return 'pending' === $order->status;
	}

	/**
	 * مشتری می‌تواند فیش بفرستد؟ فقط بعد از صدور پیش‌فاکتور و تا پیش از تکمیل/لغو.
	 *
	 * @param object $order ردیف سفارش.
	 */
	public static function can_upload_receipt( object $order ): bool {
		return in_array( (string) $order->status, [ 'awaiting_payment', 'receipt_uploaded' ], true )
			&& Files::count( (int) $order->id, Files::RECEIPT ) < Files::MAX_PER_KIND;
	}

	/**
	 * قانون خرید یک محصول (همان مقدارهایی که فرم نشان می‌دهد).
	 *
	 * @param int $product_id شناسه محصول.
	 * @return array{capacity: int, min: float, unit: string, multiple: bool}
	 */
	public static function product_rules( int $product_id ): array {
		$unit = (string) get_post_meta( $product_id, '_up_sale_unit', true );
		$unit = '' !== $unit ? $unit : 'بسته';
		$min  = (float) get_post_meta( $product_id, '_up_min_qty', true );
		return [
			'capacity' => max( 1, (int) get_post_meta( $product_id, '_up_unit_capacity', true ) ),
			'min'      => $min > 0 ? $min : 1.0,
			'unit'     => $unit,
			'multiple' => ! in_array( $unit, self::MINIMUM_UNITS, true ),
		];
	}

	/**
	 * اعتبارسنجی اقلام فرم در سرور.
	 *
	 * @param string              $type       prebuy|preinvoice.
	 * @param array<mixed>        $products   شناسه‌های انتخاب‌شده.
	 * @param array<mixed, mixed> $quantities تعداد هر محصول (کلید = شناسه).
	 * @return array{items: list<array{product_id: int, qty: float}>, errors: list<string>}
	 */
	public static function validate_items( string $type, array $products, array $quantities ): array {
		$items  = [];
		$errors = [];
		foreach ( array_unique( array_map( 'intval', $products ) ) as $product_id ) {
			$post = $product_id > 0 ? get_post( $product_id ) : null;
			if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type || 'publish' !== $post->post_status
				|| 'yes' !== get_post_meta( $product_id, '_' . $type . '_enabled', true ) ) {
				$errors[] = 'محصول #' . $product_id . ' برای ' . type_label( $type ) . ' فعال نیست.';
				continue;
			}
			$rules = self::product_rules( $product_id );
			$raw   = $quantities[ $product_id ] ?? $quantities[ (string) $product_id ] ?? null;
			$qty   = is_scalar( $raw ) && is_numeric( $raw ) ? round( (float) $raw, 3 ) : 0.0;
			$name  = get_the_title( $post );
			if ( $qty < $rules['min'] - 1e-9 || $qty > self::MAX_QTY ) {
				$errors[] = $name . ': حداقل سفارش ' . self::format_qty( $rules['min'] ) . ' ' . $rules['unit'] . ' است.';
				continue;
			}
			if ( $rules['multiple'] && ! self::is_multiple( $qty, $rules['min'] ) ) {
				$errors[] = $name . ': تعداد باید مضربی از ' . self::format_qty( $rules['min'] ) . ' باشد.';
				continue;
			}
			$items[] = [ 'product_id' => $product_id, 'qty' => $qty ];
		}
		return [ 'items' => $items, 'errors' => $errors ];
	}

	/**
	 * آیا $qty مضرب $step است؟ (با تحمل خطای اعشار)
	 *
	 * @param float $qty  تعداد.
	 * @param float $step گام.
	 */
	public static function is_multiple( float $qty, float $step ): bool {
		if ( $step <= 0 ) {
			return true;
		}
		$ratio = $qty / $step;
		return abs( $ratio - round( $ratio ) ) < 1e-6;
	}

	/**
	 * تعداد بدون صفر اضافه (2.500 ← 2.5).
	 *
	 * @param float $qty تعداد.
	 */
	public static function format_qty( float $qty ): string {
		return rtrim( rtrim( number_format( $qty, 3, '.', '' ), '0' ), '.' );
	}

	/**
	 * ذخیره اقلام با قیمت و ظرفیت لحظه ثبت (مبلغ پنل مشتری و پیشخوان یکی).
	 *
	 * @param int                                              $order_id شناسه سفارش.
	 * @param list<array{product_id: int, qty: float}> $items    اقلام معتبر (validate_items).
	 * @return float جمع کل.
	 */
	public static function save_items( int $order_id, array $items ): float {
		global $wpdb;
		$wpdb->delete( table( 'order_items' ), [ 'order_id' => $order_id ], [ '%d' ] );

		$total = 0.0;
		foreach ( $items as $item ) {
			$product = wc_active() ? wc_get_product( $item['product_id'] ) : null;
			if ( ! $product ) {
				continue;
			}
			$rules  = self::product_rules( $item['product_id'] );
			$price  = (float) $product->get_price();
			$line   = round( $item['qty'] * $rules['capacity'] * $price, 2 );
			$total += $line;
			$wpdb->insert(
				table( 'order_items' ),
				[
					'order_id'      => $order_id,
					'product_id'    => $item['product_id'],
					'quantity'      => $item['qty'],
					'unit_price'    => $price,
					'unit_capacity' => $rules['capacity'],
					'sale_unit'     => $rules['unit'],
					'line_total'    => $line,
				],
				[ '%d', '%d', '%f', '%f', '%d', '%s', '%f' ]
			);
		}
		return $total;
	}

	/** فرم پیش‌خرید و پیش‌فاکتور (admin-post.php). */
	public static function handle_form(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( 'لطفاً ابتدا وارد حساب کاربری خود شوید.' );
		}
		check_admin_referer( 'user_panel_action' );
		// قفل پروفایل ناقص اینجا هم: فرم به admin-post.php می‌رود و enforce_lock فقط برگه پنل را
		// می‌بست؛ تا 0.2.1 با یک POST مستقیم سفارش بی نام و آدرس ثبت می‌شد.
		Profile::enforce_lock( 'orders' );

		global $wpdb;
		$user_id    = get_current_user_id();
		$type       = 'save_preinvoice' === sanitize_key( wp_unslash( $_POST['action'] ?? '' ) ) ? 'preinvoice' : 'prebuy';
		$products   = isset( $_POST['products'] ) ? (array) wp_unslash( $_POST['products'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- intval در validate_items.
		$quantities = isset( $_POST['qty'] ) ? (array) wp_unslash( $_POST['qty'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- عدد در validate_items.
		$order_id   = absint( $_POST['order_id'] ?? 0 );
		$order      = null;

		if ( $order_id > 0 ) {
			$order = self::owned( $order_id, $user_id );
			if ( ! $order ) {
				wp_die( 'عدم دسترسی مجاز.', '', [ 'response' => 403 ] );
			}
			if ( ! self::can_edit( $order ) ) {
				self::back( 'orders', 'locked' );
			}
			// نوع سفارش با ویرایش عوض نمی‌شود (فرم دیگر نوع دیگری می‌فرستاد)
			$type = (string) $order->type;
		}

		if ( ! $products ) {
			self::back( $type, 'empty', $order_id );
		}
		$checked = self::validate_items( $type, $products, $quantities );
		if ( $checked['errors'] || ! $checked['items'] ) {
			set_transient( 'hodima_panel_errors_' . $user_id, $checked['errors'], 5 * MINUTE_IN_SECONDS );
			self::back( $type, 'invalid_items', $order_id );
		}

		$now = now();
		if ( ! $order ) {
			$wpdb->insert( table( 'orders' ), [ 'user_id' => $user_id, 'type' => $type, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now ], [ '%d', '%s', '%s', '%s', '%s' ] );
			$order_id = (int) $wpdb->insert_id;
			if ( $order_id <= 0 ) {
				self::back( $type, 'db_error' );
			}
		}

		$total = self::save_items( $order_id, $checked['items'] );
		$wpdb->update( table( 'orders' ), [ 'total_price' => $total, 'updated_at' => $now ], [ 'id' => $order_id ], [ '%f', '%s' ], [ '%d' ] );
		clear_admin_badge();
		self::back( 'orders', $order ? 'order_updated' : 'order_saved' );
	}

	/**
	 * ریدایرکت به یک بخش پنل با پیام.
	 *
	 * @param string $section بخش.
	 * @param string $msg     کلید پیام.
	 * @param int    $edit    سفارش در حال ویرایش (برگشت به همان فرم).
	 */
	private static function back( string $section, string $msg, int $edit = 0 ): never {
		$args = [ 'section' => $section, 'msg' => $msg ];
		if ( $edit > 0 ) {
			$args['edit_order'] = $edit;
		}
		wp_safe_redirect( Page::url( $args ) );
		exit;
	}

	/** آپلود فیش و حذف سفارش از صفحه پنل (پیش از چاپ صفحه). */
	public static function handle_request(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user_id = get_current_user_id();

		if ( isset( $_POST['upload_receipt'], $_POST['order_id'] ) ) {
			$order_id = absint( $_POST['order_id'] );
			check_admin_referer( 'upload_receipt_' . $order_id, '_wpnonce_receipt' );
			$order = self::owned( $order_id, $user_id );
			if ( ! $order ) {
				self::back( 'orders', 'error_owner' );
			}
			if ( ! self::can_upload_receipt( $order ) ) {
				self::back( 'orders', 'receipt_not_allowed' );
			}
			$file   = isset( $_FILES['receipt_file'] ) && is_array( $_FILES['receipt_file'] ) ? $_FILES['receipt_file'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Files::upload نوع و اندازه و محتوا را می‌سنجد.
			$result = Files::upload( $order_id, Files::RECEIPT, $file );
			if ( is_string( $result ) ) {
				self::back( 'orders', $result );
			}
			global $wpdb;
			$wpdb->update( table( 'orders' ), [ 'status' => 'receipt_uploaded', 'updated_at' => now() ], [ 'id' => $order_id ], [ '%s', '%s' ], [ '%d' ] );
			self::add_chat_message( $order_id, $user_id, 'system', 'فیش واریزی توسط مشتری آپلود شد. لطفاً بررسی نمایید.' );
			self::back( 'orders', 'receipt_uploaded' );
		}

		if ( isset( $_GET['action'], $_GET['order_id'] ) && 'delete_order' === $_GET['action'] ) {
			$order_id = absint( $_GET['order_id'] );
			check_admin_referer( 'delete_order_' . $order_id );
			$order = self::owned( $order_id, $user_id );
			if ( ! $order ) {
				self::back( 'orders', 'error_owner' );
			}
			if ( ! self::can_edit( $order ) ) {
				self::back( 'orders', 'locked' );
			}
			self::delete( $order_id );
			self::back( 'orders', 'deleted' );
		}
	}

	/**
	 * پیام در گفتگوی سفارش (ساخت گفتگو اگر نیست) با شمارنده خوانده‌نشده.
	 *
	 * @param int    $order_id شناسه سفارش.
	 * @param int    $user_id  صاحب سفارش.
	 * @param string $sender   user|admin|system.
	 * @param string $message  متن.
	 * @param string $to       خواننده‌ای که پیام برایش «نخوانده» شمرده شود: user|admin (خالی = طرف مقابل فرستنده).
	 */
	public static function add_chat_message( int $order_id, int $user_id, string $sender, string $message, string $to = '' ): void {
		global $wpdb;
		$chats = table( 'chats' );
		$now   = now();

		$chat_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE order_id = %d', $chats, $order_id ) );
		if ( ! $chat_id ) {
			$wpdb->insert( $chats, [ 'order_id' => $order_id, 'user_id' => $user_id, 'created_at' => $now ], [ '%d', '%d', '%s' ] );
			$chat_id = (int) $wpdb->insert_id;
		}
		if ( ! $chat_id ) {
			return;
		}

		$wpdb->insert(
			table( 'chat_messages' ),
			[ 'chat_id' => $chat_id, 'sender_id' => 'system' === $sender ? 0 : get_current_user_id(), 'sender_type' => $sender, 'message' => $message, 'created_at' => $now ],
			[ '%d', '%d', '%s', '%s', '%s' ]
		);

		$to      = '' !== $to ? $to : ( 'admin' === $sender ? 'user' : 'admin' );
		$counter = 'user' === $to ? 'user_unread' : 'admin_unread';
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET %i = %i + 1, last_message_preview = %s, last_message_sender = %s, last_message_at = %s WHERE id = %d',
				$chats,
				$counter,
				$counter,
				preview( $message ),
				$sender,
				$now,
				$chat_id
			)
		);
		clear_admin_badge();
	}

	/**
	 * حذف کامل یک سفارش: اقلام، فایل‌ها، گفتگو و پیام‌هایش.
	 *
	 * @param int $order_id شناسه سفارش.
	 */
	public static function delete( int $order_id ): void {
		global $wpdb;
		$chat_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE order_id = %d', table( 'chats' ), $order_id ) );
		if ( $chat_id ) {
			$wpdb->delete( table( 'chat_messages' ), [ 'chat_id' => $chat_id ], [ '%d' ] );
			$wpdb->delete( table( 'chats' ), [ 'id' => $chat_id ], [ '%d' ] );
		}
		Files::delete_for_order( $order_id );
		$wpdb->delete( table( 'order_items' ), [ 'order_id' => $order_id ], [ '%d' ] );
		$wpdb->delete( table( 'orders' ), [ 'id' => $order_id ], [ '%d' ] );
		clear_admin_badge();
	}
}
