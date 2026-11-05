<?php
/**
 * نمای فهرست سفارش‌های مشتری با فاکتور، فیش و گفتگو (منتقل‌شده از پنل قدیمی).
 *
 * باقی‌مانده: بازطراحی و <dialog> (مرحله ۵).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Files;
use Hodima\Panel\Page;
use function Hodima\Panel\show_date;
use function Hodima\Panel\status_label;
use function Hodima\Panel\table;
use function Hodima\Panel\type_label;

global $wpdb;
$user_id  = get_current_user_id();
$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$per_page = 15;

$all_orders   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, user_id, type, status, total_price, created_at FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', table( 'orders' ), $user_id, $per_page, ( $paged - 1 ) * $per_page ) );
$total_orders = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(id) FROM %i WHERE user_id = %d', table( 'orders' ), $user_id ) );
$total_pages  = (int) ceil( $total_orders / $per_page );

$order_ids = array_map( static fn( object $o ): int => (int) $o->id, $all_orders );
$files     = Files::for_orders( $order_ids );
$titles    = [];
$unread    = [];
if ( $order_ids ) {
	$in = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $in فقط %d است.
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT order_id, product_id FROM %i WHERE order_id IN ($in)", table( 'order_items' ), ...$order_ids ) ) as $item ) {
		$titles[ (int) $item->order_id ][] = get_the_title( (int) $item->product_id ) ?: 'محصول ' . (int) $item->product_id;
	}
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT order_id, user_unread FROM %i WHERE user_id = %d AND order_id IN ($in)", table( 'chats' ), $user_id, ...$order_ids ) ) as $chat ) {
		$unread[ (int) $chat->order_id ] = (int) $chat->user_unread;
	}
	// phpcs:enable
}
?>

<div class="orders-wrapper">
	<div class="orders-card">

		<div class="section-header">
			<div class="header-info">
				<h2 class="header-title">لیست تمام سفارشات</h2>
				<p class="header-desc">تمامی سفارشات جاری و تکمیل‌شده شما در این بخش قابل مشاهده است.</p>
			</div>
		</div>

		<?php if ( ! $all_orders ) : ?>
			<div class="empty-state"><p>شما تاکنون سفارشی ثبت نکرده‌اید.</p></div>
		<?php else : ?>
			<div class="orders-table-container">
				<table class="user-orders-table">
					<thead>
						<tr>
							<th scope="col">شماره</th>
							<th scope="col">محصولات</th>
							<th scope="col">مبلغ کل</th>
							<th scope="col">نوع درخواست</th>
							<th scope="col">وضعیت</th>
							<th scope="col">تاریخ ثبت</th>
							<th scope="col">اقدامات / اسناد</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $all_orders as $order ) :
							$oid       = (int) $order->id;
							$names     = implode( '، ', array_unique( $titles[ $oid ] ?? [] ) );
							$short     = mb_strlen( $names ) > 25 ? mb_substr( $names, 0, 25 ) . '…' : $names;
							$has_new   = ( $unread[ $oid ] ?? 0 ) > 0;
							$invoices  = array_values( array_filter( $files[ $oid ] ?? [], static fn( object $f ): bool => Files::INVOICE === $f->kind ) );
							$receipts  = array_values( array_filter( $files[ $oid ] ?? [], static fn( object $f ): bool => Files::RECEIPT === $f->kind ) );
							$status    = (string) $order->status;
							$inv_class = match ( $status ) {
								'awaiting_payment'       => 'invoice-dl',
								'completed', 'cancelled' => 'invoice-success',
								default                  => 'invoice-view',
							};
							?>
							<tr class="<?php echo $has_new ? 'row-unread' : ''; ?>">
								<td class="order-id">
									#<?php echo esc_html( (string) $oid ); ?>
									<?php if ( $has_new ) : ?>
										<span class="unread-badge">پیام جدید</span>
									<?php endif; ?>
								</td>
								<td title="<?php echo esc_attr( $names ); ?>"><?php echo '' !== $names ? esc_html( $short ) : '<span class="empty-text">---</span>'; ?></td>
								<td>
									<?php if ( (float) $order->total_price > 0 ) : ?>
										<span class="price-val"><?php echo esc_html( number_format_i18n( (float) $order->total_price ) ); ?> تومان</span>
									<?php else : ?>
										<span class="price-empty">در انتظار محاسبه</span>
									<?php endif; ?>
								</td>
								<td><span class="badge type-<?php echo esc_attr( (string) $order->type ); ?>"><?php echo esc_html( type_label( (string) $order->type ) ); ?></span></td>
								<td><span class="badge status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( status_label( $status ) ); ?></span></td>
								<td class="order-date"><?php echo esc_html( show_date( (string) $order->created_at ) ); ?></td>
								<td class="order-actions">
									<div class="action-buttons">

										<?php if ( $invoices ) : ?>
											<div class="docs-grid">
												<?php foreach ( $invoices as $i => $file ) : ?>
													<a href="<?php echo esc_url( $file->url ); ?>" target="_blank" rel="noopener" class="btn-doc <?php echo esc_attr( $inv_class ); ?>">فاکتور <?php echo esc_html( number_format_i18n( $i + 1 ) ); ?></a>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>

										<?php // فیش‌ها در همه وضعیت‌ها دیده شوند (پنل قدیمی بعد از تأیید پنهانشان می‌کرد) ?>
										<?php if ( $receipts ) : ?>
											<div class="docs-grid">
												<?php foreach ( $receipts as $i => $file ) : ?>
													<a href="<?php echo esc_url( $file->url ); ?>" target="_blank" rel="noopener" class="btn-doc receipt-view">فیش <?php echo esc_html( number_format_i18n( $i + 1 ) ); ?></a>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>

										<?php if ( \Hodima\Panel\Orders::can_upload_receipt( $order ) ) : ?>
											<form method="post" enctype="multipart/form-data" class="upload-form-box" action="<?php echo esc_url( Page::url( [ 'section' => 'orders' ] ) ); ?>">
												<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $oid ); ?>">
												<?php wp_nonce_field( 'upload_receipt_' . $oid, '_wpnonce_receipt' ); ?>
												<label for="receipt-<?php echo esc_attr( (string) $oid ); ?>"><?php echo 'awaiting_payment' === $status ? 'آپلود فیش واریزی:' : 'آپلود فیش جدید:'; ?> <small>(JPG، PNG، WEBP یا PDF تا ۵ مگابایت)</small></label>
												<input type="file" id="receipt-<?php echo esc_attr( (string) $oid ); ?>" name="receipt_file" accept=".png,.jpg,.jpeg,.webp,.pdf" required>
												<button type="submit" name="upload_receipt" value="1" class="btn-submit-upload">ارسال فیش</button>
											</form>
										<?php endif; ?>

										<div class="action-row">
											<?php if ( \Hodima\Panel\Orders::can_edit( $order ) ) : ?>
												<a href="<?php echo esc_url( Page::url( [ 'section' => (string) $order->type, 'edit_order' => $oid ] ) ); ?>" class="btn-action btn-edit">ویرایش</a>
											<?php endif; ?>

											<button type="button" class="btn-action btn-chat open-chat-modal" data-order-id="<?php echo esc_attr( (string) $oid ); ?>">پشتیبانی سفارش</button>

											<?php if ( \Hodima\Panel\Orders::can_edit( $order ) ) : ?>
												<a href="<?php echo esc_url( wp_nonce_url( Page::url( [ 'section' => 'orders', 'action' => 'delete_order', 'order_id' => $oid ] ), 'delete_order_' . $oid ) ); ?>" onclick="return confirm('آیا از حذف این سفارش مطمئن هستید؟');" class="btn-action btn-delete">حذف</a>
											<?php endif; ?>
										</div>

									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="mobile-scroll-hint">برای مشاهده کامل اطلاعات جدول، به سمت چپ بکشید</div>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="pagination">
					<?php
					echo wp_kses_post(
						(string) paginate_links(
							[
								'base'      => add_query_arg( 'paged', '%#%', Page::url( [ 'section' => 'orders' ] ) ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => '« قبلی',
								'next_text' => 'بعدی »',
							]
						)
					);
					?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<div class="chat-modal-overlay" id="global-chat-modal" hidden>
	<div class="chat-modal-box" role="dialog" aria-modal="true" aria-labelledby="global-chat-title">
		<button type="button" class="close-chat-modal" aria-label="بستن">&times;</button>
		<div class="chat-modal-header">
			<h4 id="global-chat-title">گفتگوی سفارش</h4>
		</div>
		<div class="chat-modal-body chat-history" id="global-chat-history" aria-live="polite"></div>
		<div class="chat-modal-footer">
			<form class="user-chat-form" id="global-chat-form" hidden>
				<label for="global-chat-message" class="screen-reader-text">پیام شما</label>
				<textarea id="global-chat-message" name="message" class="chat_message_input" required placeholder="پیام خود را بنویسید..."></textarea>
				<button type="submit" class="chat-submit-btn">ارسال پیام</button>
			</form>
		</div>
	</div>
</div>
