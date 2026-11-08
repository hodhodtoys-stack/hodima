<?php
/**
 * حریم خصوصی پنل مشتری: متن پیشنهادی سیاست، خروجی و حذف داده شخصی، داده کاربر حذف‌شده.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * داده شخصی پنل در ابزارهای حریم خصوصی وردپرس (ابزارها ← خروجی/حذف داده شخصی).
 *
 * پنل قدیمی هیچ‌کدام را نداشت: سفارش، فیش، گفتگو و تیکت مشتری نه در «خروجی داده» می‌آمد
 * و نه با حذف حساب پاک می‌شد (ردیف‌ها و فایل فیش بی‌صاحب در سرور می‌ماندند).
 * درخواست‌ها با ایمیل‌اند؛ مشتری با حساب وردپرس پیدا می‌شود (ایمیل ساختگی ثبت‌نام هم ایمیل حساب است).
 */
final class Privacy {

	/** هر صفحه خروجی/حذف چند سفارش یا تیکت (وردپرس صفحه‌به‌صفحه صدا می‌زند). */
	private const PER_PAGE = 20;

	/** ثبت هوک‌ها. */
	public static function boot(): void {
		add_action( 'admin_init', [ self::class, 'policy' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'register_eraser' ] );
		add_action( 'delete_user', [ self::class, 'before_user_delete' ], 10, 2 );
		add_action( 'deleted_user', [ self::class, 'user_deleted' ], 10, 2 );
	}

	/** متن پیشنهادی «تنظیمات ← حریم خصوصی» (فقط کاری که کد واقعا می‌کند). */
	public static function policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p>' . esc_html( 'پنل مشتریان (پیش‌خرید و پیش‌فاکتور) برای هر مشتری این‌ها را نگه می‌دارد: شماره موبایل، نام، ایمیل و آدرس (در اطلاعات صورت‌حساب حساب کاربری)، سفارش‌ها با اقلام و مبلغ لحظه ثبت، فیش واریزی و فاکتوری که بارگذاری می‌شود (در پوشه‌ای که از اینترنت مستقیم باز نمی‌شود)، و پیام‌های گفتگوی سفارش و تیکت‌های پشتیبانی.' ) . '</p>';
		$text .= '<p>' . esc_html( 'برای ورود با کد پیامکی، شماره موبایل به سرویس پیامک انتخاب‌شده فرستاده می‌شود. کد فقط به‌صورت رمزشده و حداکثر ۵ دقیقه نگه داشته می‌شود؛ شمارنده‌های ضد سوءاستفاده (شماره و IP) فقط به‌صورت هش و حداکثر یک ساعت. فهرست ۳۰ پیامک آخر فقط شماره نیمه‌پنهان دارد.' ) . '</p>';
		$text .= '<p>' . esc_html( 'وقتی پشتیبانی به پیام مشتری پاسخ می‌دهد، با پیامک (به موبایل حساب از طریق همان سرویس پیامک؛ فقط شماره سفارش یا کد پیگیری، نه متن پیام) و ایمیل (فقط به ایمیل تأییدشده حساب) خبر داده می‌شود.' ) . '</p>';
		$text .= '<p>' . esc_html( 'وقتی مشتری ایمیل تازه‌ای وارد می‌کند، لینک تأیید به همان ایمیل فرستاده می‌شود و ایمیل تازه تا ۲۴ ساعت (تا زدن لینک) جدا نگه داشته می‌شود؛ ایمیل حساب فقط بعد از تأیید عوض می‌شود.' ) . '</p>';
		$text .= '<p>' . esc_html( 'این داده تا وقتی حساب کاربری هست نگه داشته می‌شود. با حذف حساب، تیکت‌ها حذف می‌شوند و سفارش‌ها (با فایل‌ها و گفتگو و نام و موبایل مشتری) برای سابقه مالی می‌مانند. «خروجی داده شخصی» همه را می‌دهد؛ «حذف داده شخصی» تیکت‌ها و پیام‌ها را حذف می‌کند و سفارش‌ها را فقط اگر در ووکامرس «حذف داده سفارش‌ها هنگام درخواست حذف» روشن باشد (مدرک مالی).' ) . '</p>';
		wp_add_privacy_policy_content( 'Hodima Panel', wp_kses_post( $text ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $exporters خروجی‌گیرها.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['hodima-panel'] = [
			'exporter_friendly_name' => 'پنل مشتریان هدیما',
			'callback'               => [ self::class, 'export' ],
		];
		return $exporters;
	}

	/**
	 * @param array<string, array<string, mixed>> $erasers پاک‌کننده‌ها.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['hodima-panel'] = [
			'eraser_friendly_name' => 'پنل مشتریان هدیما',
			'callback'             => [ self::class, 'erase' ],
		];
		return $erasers;
	}

	/** شناسه کاربر یک ایمیل (۰ = نیست). */
	private static function user_id( string $email ): int {
		$user = get_user_by( 'email', sanitize_email( $email ) );
		return $user instanceof \WP_User ? (int) $user->ID : 0;
	}

	/**
	 * خروجی: هر صفحه چند سفارش (با اقلام، فایل‌ها و گفتگو) و همان تعداد تیکت (با پیام‌ها).
	 *
	 * @return array{data: list<array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		global $wpdb;
		$user_id = self::user_id( $email );
		if ( ! $user_id ) {
			return [ 'data' => [], 'done' => true ];
		}
		$page   = max( 1, $page );
		$offset = ( $page - 1 ) * self::PER_PAGE;
		$data   = [];

		$orders = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id LIMIT %d OFFSET %d', table( 'orders' ), $user_id, self::PER_PAGE, $offset ) );
		foreach ( $orders as $order ) {
			$id    = (int) $order->id;
			$items = [];
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, quantity, sale_unit, line_total FROM %i WHERE order_id = %d ORDER BY id', table( 'order_items' ), $id ) ) as $item ) {
				$items[] = get_the_title( (int) $item->product_id ) . ' — ' . Orders::format_qty( (float) $item->quantity ) . ' ' . $item->sale_unit . ' — ' . number_format( (float) $item->line_total ) . ' تومان';
			}
			$files = [];
			foreach ( Files::for_order( $id ) as $file ) {
				$files[] = ( 'invoice' === $file->kind ? 'فاکتور: ' : 'فیش: ' ) . $file->name . ' (' . Files::url( (int) $file->id ) . ')';
			}
			$messages = [];
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT m.sender_type, m.message, m.created_at FROM %i m INNER JOIN %i c ON c.id = m.chat_id WHERE c.order_id = %d ORDER BY m.id', table( 'chat_messages' ), table( 'chats' ), $id ) ) as $msg ) {
				$messages[] = $msg->created_at . ' ' . $msg->sender_type . ': ' . $msg->message;
			}
			$data[] = [
				'group_id'    => 'hodima-panel-orders',
				'group_label' => 'سفارش‌های پنل مشتریان',
				'item_id'     => 'hodima-panel-order-' . $id,
				'data'        => [
					[ 'name' => 'شماره سفارش', 'value' => (string) $id ],
					[ 'name' => 'نوع', 'value' => 'preinvoice' === $order->type ? 'پیش‌فاکتور' : 'پیش‌خرید' ],
					[ 'name' => 'وضعیت', 'value' => order_statuses()[ $order->status ] ?? (string) $order->status ],
					[ 'name' => 'مبلغ', 'value' => number_format( (float) $order->total_price ) . ' تومان' ],
					[ 'name' => 'تاریخ ثبت (UTC)', 'value' => (string) $order->created_at ],
					[ 'name' => 'اقلام', 'value' => implode( "\n", $items ) ],
					[ 'name' => 'فایل‌ها', 'value' => implode( "\n", $files ) ],
					[ 'name' => 'گفتگو', 'value' => implode( "\n", $messages ) ],
				],
			];
		}

		$tickets = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id LIMIT %d OFFSET %d', table( 'tickets' ), $user_id, self::PER_PAGE, $offset ) );
		foreach ( $tickets as $ticket ) {
			$messages = [];
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT sender_type, message, created_at FROM %i WHERE ticket_id = %d ORDER BY id', table( 'ticket_messages' ), (int) $ticket->id ) ) as $msg ) {
				$messages[] = $msg->created_at . ' ' . $msg->sender_type . ': ' . $msg->message;
			}
			$data[] = [
				'group_id'    => 'hodima-panel-tickets',
				'group_label' => 'تیکت‌های پشتیبانی',
				'item_id'     => 'hodima-panel-ticket-' . (int) $ticket->id,
				'data'        => [
					[ 'name' => 'کد پیگیری', 'value' => (string) $ticket->reference_code ],
					[ 'name' => 'موضوع', 'value' => (string) $ticket->subject ],
					[ 'name' => 'تاریخ (UTC)', 'value' => (string) $ticket->created_at ],
					[ 'name' => 'پیام‌ها', 'value' => implode( "\n", $messages ) ],
				],
			];
		}

		// ایمیل تازه‌ای که هنوز با لینک تأیید نشده (فقط صفحه اول)
		$pending = 1 === $page ? get_user_meta( $user_id, Profile::PENDING_META, true ) : null;
		if ( is_array( $pending ) && '' !== (string) ( $pending['email'] ?? '' ) ) {
			$data[] = [
				'group_id'    => 'hodima-panel-account',
				'group_label' => 'حساب پنل مشتریان',
				'item_id'     => 'hodima-panel-pending-email',
				'data'        => [ [ 'name' => 'ایمیل در انتظار تأیید', 'value' => (string) $pending['email'] ] ],
			];
		}

		return [
			'data' => $data,
			'done' => count( $orders ) < self::PER_PAGE && count( $tickets ) < self::PER_PAGE,
		];
	}

	/**
	 * حذف: تیکت‌ها و پیام‌هایشان همیشه؛ سفارش‌ها (با فایل و گفتگو) فقط با تنظیم ووکامرس
	 * «حذف داده سفارش‌ها» — همان قاعده خود ووکامرس برای مدرک مالی. هر صفحه از اول می‌خواند
	 * چون ردیف‌های صفحه قبل حذف شده‌اند.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		global $wpdb;
		unset( $page );
		$result  = [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
		$user_id = self::user_id( $email );
		if ( ! $user_id ) {
			return $result;
		}

		$tickets = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d ORDER BY id LIMIT %d', table( 'tickets' ), $user_id, self::PER_PAGE ) ) );
		foreach ( $tickets as $id ) {
			self::delete_ticket( $id );
		}
		$pending = delete_user_meta( $user_id, Profile::PENDING_META ); // ایمیل در انتظار تأیید

		$orders = [];
		if ( self::erase_orders() ) {
			$orders = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d ORDER BY id LIMIT %d', table( 'orders' ), $user_id, self::PER_PAGE ) ) );
			foreach ( $orders as $id ) {
				Orders::delete( $id );
			}
		} elseif ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', table( 'orders' ), $user_id ) ) > 0 ) {
			$result['items_retained'] = true;
			$result['messages'][]     = 'سفارش‌های پنل مشتریان (مدرک مالی) نگه داشته شد؛ برای حذف، در ووکامرس ← تنظیمات ← حساب‌ها و حریم خصوصی «حذف داده سفارش‌ها» را روشن کنید.';
		}

		$result['items_removed'] = (bool) ( $tickets || $orders || $pending );
		$result['done']          = count( $tickets ) < self::PER_PAGE && count( $orders ) < self::PER_PAGE;
		return $result;
	}

	/** تنظیم ووکامرس «حذف داده سفارش‌ها هنگام درخواست حذف». */
	private static function erase_orders(): bool {
		return 'yes' === get_option( 'woocommerce_erasure_request_removes_order_data', 'no' );
	}

	/** حذف یک تیکت و پیام‌هایش. */
	private static function delete_ticket( int $ticket_id ): void {
		global $wpdb;
		$wpdb->delete( table( 'ticket_messages' ), [ 'ticket_id' => $ticket_id ], [ '%d' ] );
		$wpdb->delete( table( 'tickets' ), [ 'id' => $ticket_id ], [ '%d' ] );
	}

	/**
	 * پیش از حذف کاربر (بی سپردن محتوا): مشخصات مشتری در گفتگوی هر سفارشش ثبت شود.
	 *
	 * سفارش مدرک مالی است و می‌ماند (user_deleted)، ولی بعد از حذف حساب، نام و موبایل
	 * دیگر از هیچ‌جا خوانده نمی‌شد و سفارش بی‌نام می‌ماند. پیام سیستمی برای مشتری «نخوانده»
	 * شمرده می‌شود تا عدد «پیام تازه» پیشخوان را بالا نبرد.
	 *
	 * @param int      $user_id  کاربری که حذف می‌شود.
	 * @param int|null $reassign کاربری که محتوا به او سپرده می‌شود.
	 */
	public static function before_user_delete( int $user_id, ?int $reassign = null ): void {
		global $wpdb;
		$user = get_userdata( $user_id );
		if ( $reassign || ! $user instanceof \WP_User ) {
			return;
		}
		$fields = Profile::fields( $user );
		$parts  = array_filter(
			[
				'نام: ' . ( '' !== $fields['name'] ? $fields['name'] : (string) $user->display_name ),
				'' !== $fields['mobile'] ? 'موبایل: ' . $fields['mobile'] : '',
				Profile::is_placeholder_email( (string) $user->user_email ) ? '' : 'ایمیل: ' . $user->user_email,
			]
		);
		$text = 'حساب کاربری این مشتری حذف شد؛ سفارش برای سابقه مالی نگه داشته شد. ' . implode( '، ', $parts );
		foreach ( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d', table( 'orders' ), $user_id ) ) ) as $order_id ) {
			Orders::add_chat_message( $order_id, $user_id, 'system', $text, 'user' );
		}
	}

	/**
	 * کاربر حذف شد: اگر مدیر محتوا را به کاربر دیگری سپرد، سفارش‌ها و تیکت‌ها به او می‌رسد؛
	 * وگرنه فقط تیکت‌های مشاوره حذف می‌شوند و سفارش‌ها (با فیش، فاکتور و گفتگو) می‌مانند.
	 *
	 * تا 0.2.0 همه سفارش‌ها و فایل‌ها هم پاک می‌شد؛ وردپرس برای مشتریِ بی‌نوشته گزینه
	 * «سپردن محتوا» را نشان نمی‌دهد، پس حذف هر مشتری سابقه مالی‌اش را از بین می‌برد
	 * (ووکامرس هم سفارش کاربر حذف‌شده را نگه می‌دارد). پاک کردن کامل سفارش‌ها فقط با
	 * «حذف داده شخصی» و تنظیم ووکامرس (erase).
	 *
	 * @param int      $user_id  کاربر حذف‌شده.
	 * @param int|null $reassign کاربری که محتوا به او سپرده شد.
	 */
	public static function user_deleted( int $user_id, ?int $reassign = null ): void {
		global $wpdb;
		if ( $reassign ) {
			foreach ( [ 'orders', 'chats', 'tickets' ] as $name ) {
				$wpdb->update( table( $name ), [ 'user_id' => $reassign ], [ 'user_id' => $user_id ], [ '%d' ], [ '%d' ] );
			}
			return;
		}
		foreach ( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d', table( 'tickets' ), $user_id ) ) ) as $id ) {
			self::delete_ticket( $id );
		}
		clear_admin_badge();
	}
}
