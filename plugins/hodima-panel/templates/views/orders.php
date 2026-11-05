<?php
/**
 * سفارش‌های مشتری: فیلتر، کارت هر سفارش با نوار مرحله، اسناد، فیش، گفتگو (طرح تأییدشده، مرحله ۵).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Files;
use Hodima\Panel\Orders;
use Hodima\Panel\Page;
use Hodima\Panel\View;
use function Hodima\Panel\show_date;
use function Hodima\Panel\table;
use function Hodima\Panel\type_label;

global $wpdb;
$hodima_user_id = get_current_user_id();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما.
$hodima_filter = sanitize_key( wp_unslash( (string) ( $_GET['filter'] ?? '' ) ) );
$hodima_paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
// phpcs:enable
$hodima_filters = [
	''       => [ 'همه', [] ],
	'wait'   => [ 'منتظر شما', [ 'awaiting_payment' ] ],
	'review' => [ 'در بررسی', [ 'pending', 'receipt_uploaded' ] ],
	'done'   => [ 'تکمیل یا لغو', [ 'completed', 'cancelled' ] ],
];
$hodima_filter = isset( $hodima_filters[ $hodima_filter ] ) ? $hodima_filter : '';
$hodima_counts = [];
foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i WHERE user_id = %d GROUP BY status', table( 'orders' ), $hodima_user_id ) ) as $hodima_c ) {
	$hodima_counts[ (string) $hodima_c->status ] = (int) $hodima_c->n;
}
$hodima_count_of = static fn( array $statuses ): int => $statuses ? array_sum( array_intersect_key( $hodima_counts, array_flip( $statuses ) ) ) : array_sum( $hodima_counts );

$hodima_per   = 15;
$hodima_where = $hodima_filters[ $hodima_filter ][1];
if ( $hodima_where ) {
	$hodima_in   = implode( ',', array_fill( 0, count( $hodima_where ), '%s' ) );
	$hodima_sql  = "SELECT * FROM %i WHERE user_id = %d AND status IN ($hodima_in) ORDER BY id DESC LIMIT %d OFFSET %d";
	$hodima_rows = (array) $wpdb->get_results( $wpdb->prepare( $hodima_sql, ...[ table( 'orders' ), $hodima_user_id, ...$hodima_where, $hodima_per, ( $hodima_paged - 1 ) * $hodima_per ] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $hodima_in فقط %s است.
} else {
	$hodima_rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', table( 'orders' ), $hodima_user_id, $hodima_per, ( $hodima_paged - 1 ) * $hodima_per ) );
}
$hodima_total = $hodima_count_of( $hodima_where );
$hodima_ids   = array_map( static fn( object $o ): int => (int) $o->id, $hodima_rows );
$hodima_files = Files::for_orders( $hodima_ids );
$hodima_items = [];
$hodima_chat  = [];
if ( $hodima_ids ) {
	$hodima_in = implode( ',', array_fill( 0, count( $hodima_ids ), '%d' ) );
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- فقط %d.
	$hodima_sql = "SELECT order_id, product_id, quantity, sale_unit, unit_capacity FROM %i WHERE order_id IN ($hodima_in) ORDER BY id";
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( $hodima_sql, ...[ table( 'order_items' ), ...$hodima_ids ] ) ) as $hodima_item ) {
		$hodima_items[ (int) $hodima_item->order_id ][] = $hodima_item;
	}
	$hodima_sql = "SELECT order_id, user_unread FROM %i WHERE user_id = %d AND order_id IN ($hodima_in)";
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( $hodima_sql, ...[ table( 'chats' ), $hodima_user_id, ...$hodima_ids ] ) ) as $hodima_c ) {
		$hodima_chat[ (int) $hodima_c->order_id ] = (int) $hodima_c->user_unread;
	}
	// phpcs:enable
}
$hodima_item_text = static function ( object $item ): string {
	$text = get_the_title( (int) $item->product_id ) . ' — ' . View::qty( (float) $item->quantity ) . ' ' . $item->sale_unit;
	return (int) $item->unit_capacity > 1 ? $text . ' (هر ' . $item->sale_unit . ' ' . number_format_i18n( (int) $item->unit_capacity ) . ' عدد)' : $text;
};
?>
<header class="hpn-head">
	<h1 class="hpn-title">سفارش‌ها</h1>
	<p class="hpn-lead">هر سفارش مرحله‌اش، اسنادش و گفتگو با پشتیبانی را دارد.</p>
</header>

<nav class="hpn-chips" aria-label="فیلتر سفارش‌ها">
	<?php foreach ( $hodima_filters as $hodima_key => [ $hodima_label, $hodima_statuses ] ) : ?>
		<a class="hpn-chip<?php echo $hodima_key === $hodima_filter ? ' is-active' : ''; ?>" href="<?php echo esc_url( Page::url( array_filter( [ 'section' => 'orders', 'filter' => $hodima_key ] ) ) ); ?>"<?php echo $hodima_key === $hodima_filter ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $hodima_label . ' · ' . number_format_i18n( $hodima_count_of( $hodima_statuses ) ) ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( ! $hodima_rows ) : ?>
	<p class="hpn-card hpn-empty"><?php echo esc_html( '' === $hodima_filter ? 'هنوز سفارشی ندارید.' : 'سفارشی در این گروه نیست.' ); ?> <a href="<?php echo esc_url( Page::url( [ 'section' => 'prebuy' ] ) ); ?>">ثبت پیش‌خرید تازه</a></p>
<?php endif; ?>

<?php foreach ( $hodima_rows as $hodima_order ) : ?>
	<?php
	$hodima_oid      = (int) $hodima_order->id;
	$hodima_status   = (string) $hodima_order->status;
	$hodima_group    = View::group( $hodima_status );
	$hodima_invoices = array_values( array_filter( $hodima_files[ $hodima_oid ] ?? [], static fn( object $f ): bool => Files::INVOICE === $f->kind ) );
	$hodima_receipts = array_values( array_filter( $hodima_files[ $hodima_oid ] ?? [], static fn( object $f ): bool => Files::RECEIPT === $f->kind ) );
	$hodima_unread   = $hodima_chat[ $hodima_oid ] ?? 0;
	$hodima_compact  = in_array( $hodima_status, [ 'completed', 'cancelled' ], true );
	$hodima_title    = 'سفارش #' . number_format_i18n( $hodima_oid );
	?>
	<article class="hpn-card hpn-order hpn-order--<?php echo esc_attr( $hodima_group . ( $hodima_compact ? ' hpn-order--compact' : '' ) ); ?>" id="hpn-order-<?php echo esc_attr( (string) $hodima_oid ); ?>" aria-labelledby="hpn-order-title-<?php echo esc_attr( (string) $hodima_oid ); ?>">
		<header class="hpn-order__head">
			<h2 class="hpn-order__title" id="hpn-order-title-<?php echo esc_attr( (string) $hodima_oid ); ?>"><?php echo esc_html( $hodima_title ); ?></h2>
			<span class="hpn-tag"><?php echo esc_html( type_label( (string) $hodima_order->type ) ); ?></span>
			<?php if ( $hodima_compact ) : ?>
				<?php echo View::pill( $hodima_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن. ?>
			<?php endif; ?>
			<span class="hpn-muted hpn-order__date"><?php echo esc_html( show_date( (string) $hodima_order->created_at, 'j F Y' ) ); ?></span>
			<strong class="hpn-order__total"><?php echo esc_html( (float) $hodima_order->total_price > 0 ? View::money( (float) $hodima_order->total_price ) : 'در انتظار محاسبه' ); ?></strong>
		</header>

		<?php if ( ! $hodima_compact ) : ?>
			<?php echo View::steps( $hodima_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن. ?>
			<p class="hpn-muted hpn-order__items"><?php echo esc_html( implode( ' · ', array_map( $hodima_item_text, $hodima_items[ $hodima_oid ] ?? [] ) ) ); ?></p>
		<?php endif; ?>

		<div class="hpn-order__actions">
			<?php foreach ( $hodima_invoices as $hodima_i => $hodima_file ) : ?>
				<a class="hpn-btn hpn-btn--ghost" href="<?php echo esc_url( $hodima_file->url ); ?>" target="_blank" rel="noopener"><?php echo View::icon( 'down', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?><?php echo esc_html( count( $hodima_invoices ) > 1 ? 'پیش‌فاکتور ' . number_format_i18n( $hodima_i + 1 ) : 'پیش‌فاکتور' ); ?></a>
			<?php endforeach; ?>
			<?php foreach ( $hodima_receipts as $hodima_i => $hodima_file ) : ?>
				<a class="hpn-btn hpn-btn--ghost" href="<?php echo esc_url( $hodima_file->url ); ?>" target="_blank" rel="noopener"><?php echo View::icon( 'receipt', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?><?php echo esc_html( 'فیش ' . number_format_i18n( $hodima_i + 1 ) ); ?></a>
			<?php endforeach; ?>

			<?php if ( Orders::can_upload_receipt( $hodima_order ) ) : ?>
				<form class="hpn-upload" method="post" enctype="multipart/form-data" action="<?php echo esc_url( Page::url( [ 'section' => 'orders' ] ) ); ?>">
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $hodima_oid ); ?>">
					<?php wp_nonce_field( 'upload_receipt_' . $hodima_oid, '_wpnonce_receipt', false ); ?>
					<label class="hpn-upload__label" for="hpn-receipt-<?php echo esc_attr( (string) $hodima_oid ); ?>">
						<span><?php echo esc_html( $hodima_receipts ? 'فیش دیگری دارید؟' : 'فیش واریزی را انتخاب کنید' ); ?></span>
						<span class="hpn-muted">عکس یا PDF تا ۵ مگابایت</span>
					</label>
					<input class="hpn-upload__file" type="file" id="hpn-receipt-<?php echo esc_attr( (string) $hodima_oid ); ?>" name="receipt_file" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
					<button class="hpn-btn" type="submit" name="upload_receipt" value="1">ارسال فیش</button>
				</form>
			<?php endif; ?>

			<?php if ( Orders::can_edit( $hodima_order ) ) : ?>
				<a class="hpn-btn" href="<?php echo esc_url( Page::url( [ 'section' => (string) $hodima_order->type, 'edit_order' => $hodima_oid ] ) ); ?>">ویرایش سفارش</a>
			<?php endif; ?>

			<button type="button" class="hpn-btn hpn-btn--ghost" data-hpn-chat="<?php echo esc_attr( (string) $hodima_oid ); ?>" data-hpn-chat-title="<?php echo esc_attr( 'گفتگوی ' . $hodima_title ); ?>">
				<?php echo View::icon( 'chat', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?><span>گفتگو با پشتیبانی</span><?php echo View::badge( $hodima_unread, 'پیام تازه' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن. ?>
			</button>

			<?php if ( Orders::can_edit( $hodima_order ) ) : ?>
				<a class="hpn-link-danger" href="<?php echo esc_url( wp_nonce_url( Page::url( [ 'section' => 'orders', 'action' => 'delete_order', 'order_id' => $hodima_oid ] ), 'delete_order_' . $hodima_oid ) ); ?>" data-hpn-confirm="<?php echo esc_attr( $hodima_title . ' حذف شود؟' ); ?>">حذف سفارش</a>
			<?php endif; ?>
		</div>
	</article>
<?php endforeach; ?>

<?php
$hodima_pages = (int) ceil( $hodima_total / $hodima_per );
if ( $hodima_pages > 1 ) {
	echo '<nav class="hpn-pager" aria-label="صفحه‌های سفارش‌ها">';
	echo wp_kses_post(
		(string) paginate_links(
			[
				'base'      => add_query_arg( 'paged', '%#%', Page::url( array_filter( [ 'section' => 'orders', 'filter' => $hodima_filter ] ) ) ),
				'format'    => '',
				'current'   => $hodima_paged,
				'total'     => $hodima_pages,
				'prev_text' => 'قبلی',
				'next_text' => 'بعدی',
			]
		)
	);
	echo '</nav>';
}
?>

<dialog class="hpn-dialog" id="hpn-chat" aria-labelledby="hpn-chat-title">
	<div class="hpn-dialog__head">
		<h2 class="hpn-card__title" id="hpn-chat-title">گفتگوی سفارش</h2>
		<button type="button" class="hpn-icon-btn" data-hpn-close aria-label="بستن"><?php echo View::icon( 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?></button>
	</div>
	<div class="hpn-thread" id="hpn-chat-history" aria-live="polite" tabindex="0"></div>
	<p class="hpn-chat__error" id="hpn-chat-error" role="alert" hidden></p>
	<form class="hpn-compose" id="hpn-chat-form" hidden>
		<label class="screen-reader-text" for="hpn-chat-message">پیام شما</label>
		<textarea id="hpn-chat-message" name="message" rows="2" required placeholder="پیام خود را بنویسید…"></textarea>
		<button class="hpn-btn" type="submit">ارسال</button>
	</form>
</dialog>
