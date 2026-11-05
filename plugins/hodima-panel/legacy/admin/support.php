<?php
/**
 * پیشخوان: تیکت‌های پشتیبانی (منتقل‌شده از پنل قدیمی؛ بازطراحی: مرحله ۶).
 *
 * تغییر این مرحله: پاسخ و بستن تیکت پیش از چاپ صفحه پردازش می‌شوند (قبلا
 * ریدایرکت بعد از چاپ سربرگ پیشخوان صفحه سفید می‌داد)؛ نام‌های سراسری بدون پیشوند
 * (get_support_table_names، display_all_threads_list، …) داخل فضای نام رفتند.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy\Admin;

use function Hodima\Panel\clear_admin_badge;
use function Hodima\Panel\now;
use function Hodima\Panel\preview;
use function Hodima\Panel\show_date;
use function Hodima\Panel\table;
use function Hodima\Panel\ticket_departments;
use function Hodima\Panel\ticket_priorities;
use function Hodima\Panel\ticket_statuses;

defined( 'ABSPATH' ) || exit;

/** پاسخ و بستن تیکت (load-{صفحه}). */
function handle_support_forms(): void {
	if ( ! current_user_can( CAPABILITY ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	global $wpdb;
	$ticket_id = absint( $_POST['thread_id'] ?? 0 );
	$action    = sanitize_key( wp_unslash( $_POST['hp_action'] ?? '' ) );
	$exists    = $ticket_id ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', table( 'tickets' ), $ticket_id ) ) : 0;
	if ( ! $exists ) {
		return;
	}
	$view = [ 'page' => SUPPORT_PAGE, 'action' => 'view_thread', 'thread_id' => $ticket_id ];
	$now  = now();

	if ( 'admin_reply' === $action ) {
		check_admin_referer( 'admin_reply_to_ticket_action' );
		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		if ( '' === trim( $message ) ) {
			back( $view );
		}
		$wpdb->insert( table( 'ticket_messages' ), [ 'ticket_id' => $ticket_id, 'sender_id' => get_current_user_id(), 'sender_type' => 'admin', 'message' => $message, 'created_at' => $now ] );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'answered', admin_unread = 0, user_unread = user_unread + 1, last_message_sender = 'admin', last_message_preview = %s, last_message_at = %s, updated_at = %s WHERE id = %d",
				table( 'tickets' ),
				preview( $message ),
				$now,
				$now,
				$ticket_id
			)
		);
		clear_admin_badge();
		back( $view + [ 'hp_msg' => 'reply' ] );
	}

	if ( 'close_ticket' === $action ) {
		check_admin_referer( 'admin_close_ticket_action' );
		$wpdb->update( table( 'tickets' ), [ 'status' => 'closed', 'updated_at' => $now ], [ 'id' => $ticket_id ] );
		back( $view + [ 'hp_msg' => 'closed' ] );
	}
}

/** صفحه تیکت‌ها: فهرست یا یک تیکت. */
function render_support_page(): void {
	if ( ! current_user_can( CAPABILITY ) ) {
		wp_die( 'شما اجازه دسترسی به این صفحه را ندارید.' );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما.
	$ticket_id = absint( $_GET['thread_id'] ?? 0 );
	$is_view   = 'view_thread' === sanitize_key( wp_unslash( $_GET['action'] ?? '' ) );
	// phpcs:enable
	echo '<div class="wrap hodima-panel-admin"><h1>تیکت‌های پشتیبانی</h1>';
	notice();
	if ( $is_view && $ticket_id ) {
		render_ticket( $ticket_id );
	} else {
		render_ticket_list();
	}
	echo '</div>';
}

/** فهرست همه تیکت‌ها با صفحه‌بندی. */
function render_ticket_list(): void {
	global $wpdb;
	$per_page = 20;
	$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$total    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(id) FROM %i', table( 'tickets' ) ) );
	$pages    = (int) ceil( $total / $per_page );
	$rows     = (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT t.*, u.display_name FROM %i t LEFT JOIN %i u ON t.user_id = u.ID ORDER BY t.last_message_at DESC, t.id DESC LIMIT %d OFFSET %d',
			table( 'tickets' ),
			$wpdb->users,
			$per_page,
			( $paged - 1 ) * $per_page
		)
	);
	?>
	<table class="wp-list-table widefat fixed striped hp-tickets">
		<thead>
			<tr>
				<th scope="col">کد پیگیری</th>
				<th scope="col">موضوع</th>
				<th scope="col">کاربر</th>
				<th scope="col">دپارتمان</th>
				<th scope="col">اولویت</th>
				<th scope="col">وضعیت</th>
				<th scope="col">آخرین بروزرسانی</th>
				<th scope="col">خوانده نشده</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="8">هیچ تیکتی یافت نشد.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $t ) : ?>
				<?php $url = add_query_arg( [ 'page' => SUPPORT_PAGE, 'action' => 'view_thread', 'thread_id' => (int) $t->id ], admin_url( 'admin.php' ) ); ?>
				<tr class="<?php echo (int) $t->admin_unread > 0 ? 'row-unread' : ''; ?>">
					<td><strong><?php echo esc_html( (string) $t->reference_code ?: '#' . $t->id ); ?></strong></td>
					<td>
						<strong><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( (string) $t->subject ?: 'بدون موضوع' ); ?></a></strong>
						<?php if ( '' !== (string) $t->last_message_preview ) : ?>
							<br><small class="hp-muted"><?php echo esc_html( (string) $t->last_message_preview ); ?></small>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) ( $t->display_name ?? '' ) ?: 'کاربر حذف‌شده' ); ?></td>
					<td><?php echo esc_html( ticket_departments()[ $t->department ] ?? 'نامشخص' ); ?></td>
					<td><span class="hp-priority hp-priority--<?php echo esc_attr( (string) $t->priority ); ?>"><?php echo esc_html( ticket_priorities()[ $t->priority ] ?? (string) $t->priority ); ?></span></td>
					<td><span class="hp-ticket-status hp-ticket-status--<?php echo esc_attr( (string) $t->status ); ?>"><?php echo esc_html( ticket_statuses()[ $t->status ] ?? (string) $t->status ); ?></span></td>
					<td><?php echo esc_html( show_date( (string) ( $t->last_message_at ?: $t->created_at ) ) ); ?></td>
					<td>
						<?php if ( (int) $t->admin_unread > 0 ) : ?>
							<?php echo badge( number_format_i18n( (int) $t->admin_unread ), '#d63638' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() امن است. ?>
						<?php else : ?>
							<span class="hp-muted">0</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	if ( $pages > 1 ) {
		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		echo wp_kses_post( (string) paginate_links( [ 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ] ) );
		echo '</div></div>';
	}
}

/**
 * یک تیکت با پیام‌ها و فرم پاسخ.
 *
 * @param int $ticket_id شناسه تیکت.
 */
function render_ticket( int $ticket_id ): void {
	global $wpdb;
	$t = $wpdb->get_row( $wpdb->prepare( 'SELECT t.*, u.display_name FROM %i t LEFT JOIN %i u ON t.user_id = u.ID WHERE t.id = %d', table( 'tickets' ), $wpdb->users, $ticket_id ) );
	if ( ! $t ) {
		echo '<div class="notice notice-error"><p>تیکت پیدا نشد.</p></div>';
		return;
	}
	if ( (int) $t->admin_unread > 0 ) {
		$wpdb->update( table( 'tickets' ), [ 'admin_unread' => 0 ], [ 'id' => $ticket_id ] );
		clear_admin_badge();
	}
	$messages = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ticket_id = %d ORDER BY id ASC', table( 'ticket_messages' ), $ticket_id ) );
	$self     = add_query_arg( [ 'page' => SUPPORT_PAGE, 'action' => 'view_thread', 'thread_id' => $ticket_id ], admin_url( 'admin.php' ) );
	?>
	<div class="hp-ticket-head">
		<h2>موضوع: <?php echo esc_html( (string) $t->subject ); ?> <span class="hp-muted">(<?php echo esc_html( (string) $t->reference_code ); ?>)</span></h2>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . SUPPORT_PAGE ) ); ?>" class="button">&larr; بازگشت به لیست</a>
	</div>

	<div class="hp-ticket-meta">
		<div><strong>کاربر:</strong> <?php echo esc_html( (string) ( $t->display_name ?? '' ) ?: 'کاربر حذف‌شده' ); ?></div>
		<div><strong>دپارتمان:</strong> <?php echo esc_html( ticket_departments()[ $t->department ] ?? 'نامشخص' ); ?></div>
		<div><strong>اولویت:</strong> <?php echo esc_html( ticket_priorities()[ $t->priority ] ?? (string) $t->priority ); ?></div>
		<div><strong>وضعیت:</strong> <?php echo esc_html( ticket_statuses()[ $t->status ] ?? (string) $t->status ); ?></div>
	</div>

	<div id="hodima-thread-messages" class="hp-ticket-messages">
		<?php foreach ( $messages as $m ) : ?>
			<?php $kind = match ( $m->sender_type ) { 'admin' => 'admin-reply', 'system' => 'system-reply', default => 'user-reply' }; ?>
			<div class="message-bubble <?php echo esc_attr( $kind ); ?>">
				<p><?php echo nl2br( esc_html( (string) $m->message ) ); ?></p>
				<small class="hp-muted">فرستنده: <?php echo esc_html( match ( $m->sender_type ) { 'admin' => 'پشتیبانی', 'system' => 'سیستم', default => 'کاربر' } ); ?> | تاریخ: <?php echo esc_html( show_date( (string) $m->created_at ) ); ?></small>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( 'closed' !== $t->status ) : ?>
		<h3>پاسخ خود را ارسال کنید</h3>
		<div class="hp-ticket-reply">
			<form method="post" action="<?php echo esc_url( $self ); ?>" class="hp-ticket-reply__form">
				<input type="hidden" name="hp_action" value="admin_reply">
				<input type="hidden" name="thread_id" value="<?php echo esc_attr( (string) $ticket_id ); ?>">
				<?php wp_nonce_field( 'admin_reply_to_ticket_action' ); ?>
				<label for="hp-reply" class="screen-reader-text">متن پاسخ</label>
				<textarea id="hp-reply" name="message" rows="5" required></textarea>
				<button type="submit" class="button button-primary">ارسال پاسخ</button>
			</form>
			<div class="hp-ticket-reply__side">
				<h4>مدیریت تیکت</h4>
				<form method="post" action="<?php echo esc_url( $self ); ?>" onsubmit="return confirm('آیا از بستن این تیکت اطمینان دارید؟');">
					<input type="hidden" name="hp_action" value="close_ticket">
					<input type="hidden" name="thread_id" value="<?php echo esc_attr( (string) $ticket_id ); ?>">
					<?php wp_nonce_field( 'admin_close_ticket_action' ); ?>
					<button type="submit" class="button">بستن تیکت</button>
				</form>
			</div>
		</div>
	<?php else : ?>
		<div class="notice notice-warning inline"><p>این تیکت بسته شده است و امکان ارسال پاسخ جدید وجود ندارد.</p></div>
	<?php endif; ?>
	<?php
}
