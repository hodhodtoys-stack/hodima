<?php
/**
 * جدول‌های پیشخوان: مشتری‌های دارای سفارش و سفارش‌های یک مشتری (منتقل‌شده از پنل قدیمی).
 *
 * تغییر این مرحله: حذف گروهی سفارش‌ها nonce می‌خواهد (قبلا یک لینک ساختگی کافی
 * بود تا سفارش‌ها پاک شوند — گزارش، مورد ۴) و حذف کامل است (پیام‌ها و فایل‌ها هم)؛
 * مرتب‌سازی فقط با ستون‌های مجاز؛ سفارش مشتری حذف‌شده هم در فهرست می‌آید.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy\Admin;

use function Hodima\Panel\order_statuses;
use function Hodima\Panel\show_date;
use function Hodima\Panel\table;
use function Hodima\Panel\type_label;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/** ستون و جهت مرتب‌سازی از آدرس، فقط از فهرست مجاز. */
function sort_args( array $allowed, string $default ): array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط مرتب‌سازی.
	$by    = sanitize_key( wp_unslash( $_GET['orderby'] ?? '' ) );
	$order = 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ?? '' ) ) ) ? 'ASC' : 'DESC';
	// phpcs:enable
	return [ in_array( $by, $allowed, true ) ? $by : $default, $order ];
}

/** سفارش‌های یک مشتری. */
final class Orders_List extends \WP_List_Table {

	/** @var array<int, list<array<string, mixed>>> */
	private array $items_by_order = [];

	/** @var int[] */
	private array $unread = [];

	public function __construct( private readonly int $user_id ) {
		parent::__construct( [ 'singular' => 'hodima_panel_order', 'plural' => 'hodima_panel_orders', 'ajax' => false ] );
	}

	public function get_columns(): array {
		return [
			'cb'          => '<input type="checkbox">',
			'id'          => 'شناسه',
			'actions'     => 'عملیات',
			'type'        => 'نوع سفارش',
			'note'        => 'جزئیات اقلام',
			'total_price' => 'مبلغ کل',
			'status'      => 'وضعیت',
			'created_at'  => 'تاریخ ثبت',
		];
	}

	protected function get_sortable_columns(): array {
		return [ 'id' => [ 'id', true ], 'total_price' => [ 'total_price', false ], 'status' => [ 'status', false ], 'created_at' => [ 'created_at', false ] ];
	}

	protected function get_bulk_actions(): array {
		return [ 'delete' => 'حذف' ];
	}

	private function view_url( int $order_id ): string {
		return add_query_arg( [ 'page' => ORDERS_PAGE, 'action' => 'view', 'order_id' => $order_id ], admin_url( 'admin.php' ) );
	}

	/** @param array<string, mixed> $item */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="order_ids[]" value="%d">', absint( $item['id'] ) );
	}

	/** @param array<string, mixed> $item */
	protected function column_id( array $item ): string {
		$id     = absint( $item['id'] );
		$badges = 'pending' === $item['status'] ? badge( 'جدید', '#d63638' ) : '';
		$badges .= in_array( $id, $this->unread, true ) ? badge( 'پیام جدید', '#2271b1' ) : '';
		return sprintf( '<strong><a class="row-title" href="%s">#%d</a></strong>%s', esc_url( $this->view_url( $id ) ), $id, $badges );
	}

	/** @param array<string, mixed> $item */
	protected function column_actions( array $item ): string {
		return sprintf( '<a href="%s" class="button">نمایش</a>', esc_url( $this->view_url( absint( $item['id'] ) ) ) );
	}

	/** @param array<string, mixed> $item */
	protected function column_type( array $item ): string {
		return '<span class="hp-type hp-type--' . esc_attr( (string) $item['type'] ) . '">' . esc_html( type_label( (string) $item['type'] ) ) . '</span>';
	}

	/** @param array<string, mixed> $item */
	protected function column_note( array $item ): string {
		$rows = $this->items_by_order[ absint( $item['id'] ) ] ?? [];
		$out  = '';
		if ( $rows ) {
			$out .= '<ul class="hp-items">';
			foreach ( $rows as $row ) {
				$qty  = rtrim( rtrim( number_format( (float) $row['quantity'], 3, '.', '' ), '0' ), '.' );
				$out .= '<li>' . esc_html( get_the_title( (int) $row['product_id'] ) ) . ' <strong>(' . esc_html( $qty . ' ' . ( (string) $row['sale_unit'] ?: 'عدد' ) ) . ')</strong></li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<span class="hp-muted">بدون آیتم</span>';
		}
		if ( ! empty( $item['note'] ) ) {
			$out .= '<div class="hp-note"><strong>یادداشت:</strong><br>' . nl2br( esc_html( (string) $item['note'] ) ) . '</div>';
		}
		return $out;
	}

	/** @param array<string, mixed> $item */
	protected function column_total_price( array $item ): string {
		return (float) $item['total_price'] > 0
			? '<strong class="hp-money">' . esc_html( number_format_i18n( (float) $item['total_price'] ) ) . ' تومان</strong>'
			: '<span class="hp-muted">محاسبه نشده</span>';
	}

	/** @param array<string, mixed> $item */
	protected function column_status( array $item ): string {
		$id   = absint( $item['id'] );
		$html = '<label class="screen-reader-text" for="hp-status-' . $id . '">وضعیت سفارش #' . $id . '</label>';
		$html .= '<div class="hp-status"><select id="hp-status-' . $id . '" name="status_update[' . $id . ']">';
		foreach ( order_statuses() as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '"' . selected( (string) $item['status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select><button type="submit" name="do_update_status" value="' . $id . '" class="button button-small">ثبت</button></div>';
	}

	/** @param array<string, mixed> $item */
	protected function column_created_at( array $item ): string {
		return esc_html( show_date( (string) $item['created_at'] ) );
	}

	public function single_row( $item ): void {
		$unread = 'pending' === $item['status'] || in_array( absint( $item['id'] ), $this->unread, true );
		echo '<tr' . ( $unread ? ' class="row-unread"' : '' ) . '>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/** حذف گروهی (با nonce جدول). */
	private function process_bulk_action(): void {
		if ( 'delete' !== $this->current_action() || empty( $_REQUEST['order_ids'] ) ) {
			return;
		}
		check_admin_referer( 'bulk-' . $this->_args['plural'] );
		foreach ( array_map( 'absint', (array) wp_unslash( $_REQUEST['order_ids'] ) ) as $order_id ) {
			if ( $order_id ) {
				\Hodima\Panel\Orders::delete( $order_id );
			}
		}
		echo '<div class="notice notice-success is-dismissible"><p>سفارش‌های انتخاب‌شده و اطلاعات مرتبط با آن‌ها حذف شدند.</p></div>';
	}

	public function prepare_items(): void {
		global $wpdb;
		$this->process_bulk_action();
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

		$per_page        = 20;
		[ $by, $order ]  = sort_args( [ 'id', 'created_at', 'status', 'total_price' ], 'id' );
		$this->items     = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE user_id = %d ORDER BY %i $order LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order فقط ASC/DESC.
				table( 'orders' ),
				$this->user_id,
				$by,
				$per_page,
				( $this->get_pagenum() - 1 ) * $per_page
			),
			ARRAY_A
		);

		$ids = array_map( static fn( array $row ): int => (int) $row['id'], $this->items );
		if ( $ids ) {
			$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $in فقط %d است.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT order_id, product_id, quantity, sale_unit FROM %i WHERE order_id IN ($in)", table( 'order_items' ), ...$ids ), ARRAY_A );
			$this->unread = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM %i WHERE admin_unread > 0 AND order_id IN ($in)", table( 'chats' ), ...$ids ) ) );
			// phpcs:enable
			foreach ( $rows as $row ) {
				$this->items_by_order[ (int) $row['order_id'] ][] = $row;
			}
			_prime_post_caches( array_values( array_unique( array_map( static fn( array $r ): int => (int) $r['product_id'], $rows ) ) ), false, false );
		}

		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(id) FROM %i WHERE user_id = %d', table( 'orders' ), $this->user_id ) );
		$this->set_pagination_args( [ 'total_items' => $total, 'per_page' => $per_page ] );
	}
}

/** مشتری‌های دارای سفارش. */
final class Users_List extends \WP_List_Table {

	/** @var int[] */
	private array $flagged_chat = [];

	/** @var int[] */
	private array $flagged_order = [];

	public function __construct() {
		parent::__construct( [ 'singular' => 'hodima_panel_customer', 'plural' => 'hodima_panel_customers', 'ajax' => false ] );
	}

	public function get_columns(): array {
		return [ 'display_name' => 'نام کاربر', 'actions' => 'عملیات', 'user_email' => 'ایمیل', 'order_count' => 'تعداد سفارش‌ها', 'last_order_date' => 'تاریخ آخرین سفارش' ];
	}

	protected function get_sortable_columns(): array {
		return [ 'order_count' => [ 'order_count', false ], 'last_order_date' => [ 'last_order_date', true ] ];
	}

	/** @param array<string, mixed> $item */
	protected function column_default( $item, $column_name ): string {
		return match ( $column_name ) {
			'last_order_date' => esc_html( show_date( (string) $item['last_order_date'] ) ),
			'order_count'     => esc_html( number_format_i18n( (int) $item['order_count'] ) ),
			default           => esc_html( (string) ( $item[ $column_name ] ?? '' ) ),
		};
	}

	/** @param array<string, mixed> $item */
	protected function column_display_name( array $item ): string {
		$id     = (int) $item['user_id'];
		$name   = '' !== (string) $item['display_name'] ? (string) $item['display_name'] : 'کاربر حذف‌شده #' . $id;
		$badges = in_array( $id, $this->flagged_order, true ) ? badge( 'سفارش جدید', '#d63638' ) : '';
		$badges .= in_array( $id, $this->flagged_chat, true ) ? badge( 'پیام جدید', '#2271b1' ) : '';
		return '<strong>' . esc_html( $name ) . '</strong>' . $badges;
	}

	/** @param array<string, mixed> $item */
	protected function column_actions( array $item ): string {
		$url = add_query_arg( [ 'page' => ORDERS_PAGE, 'user_id' => (int) $item['user_id'] ], admin_url( 'admin.php' ) );
		return '<a href="' . esc_url( $url ) . '" class="button button-primary">مشاهده سفارش‌ها</a>';
	}

	public function single_row( $item ): void {
		$id = (int) $item['user_id'];
		echo '<tr' . ( in_array( $id, $this->flagged_chat, true ) || in_array( $id, $this->flagged_order, true ) ? ' class="row-unread"' : '' ) . '>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	public function prepare_items(): void {
		global $wpdb;
		$orders = table( 'orders' );

		$this->flagged_chat  = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT user_id FROM %i WHERE admin_unread > 0', table( 'chats' ) ) ) );
		$this->flagged_order = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM %i WHERE status = 'pending'", $orders ) ) );

		$per_page       = 20;
		[ $by, $order ] = sort_args( [ 'order_count', 'last_order_date' ], 'last_order_date' );
		$this->items    = (array) $wpdb->get_results(
			$wpdb->prepare(
				// LEFT JOIN: سفارش مشتری حذف‌شده هم دیده شود (INNER JOIN قدیمی پنهانش می‌کرد)
				"SELECT o.user_id, COALESCE(u.display_name, '') AS display_name, COALESCE(u.user_email, '') AS user_email, COUNT(o.id) AS order_count, MAX(o.created_at) AS last_order_date
				FROM %i o LEFT JOIN %i u ON u.ID = o.user_id GROUP BY o.user_id, u.display_name, u.user_email ORDER BY %i $order LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order فقط ASC/DESC.
				$orders,
				$wpdb->users,
				$by,
				$per_page,
				( $this->get_pagenum() - 1 ) * $per_page
			),
			ARRAY_A
		);
		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		$this->set_pagination_args( [ 'total_items' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT user_id) FROM %i', $orders ) ), 'per_page' => $per_page ] );
	}
}
