<?php
/**
 * پیام‌ها و پشتیبانی: تیکت‌ها و گفتگوی سفارش‌ها در یک فهرست، رشته پیام، پاسخ (طرح تأییدشده، مرحله ۵).
 * پردازش فرم‌ها: legacy/support.php (نام فیلدها همان قبلی).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Page;
use Hodima\Panel\View;
use function Hodima\Panel\show_date;
use function Hodima\Panel\table;
use function Hodima\Panel\ticket_departments;
use function Hodima\Panel\ticket_priorities;
use function Hodima\Panel\ticket_statuses;

global $wpdb;
$hodima_user_id = get_current_user_id();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما.
$hodima_open = absint( $_GET['thread_id'] ?? 0 );
$hodima_new  = isset( $_GET['new'] );
// phpcs:enable
$hodima_tickets = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY last_message_at DESC, id DESC LIMIT 50', table( 'tickets' ), $hodima_user_id ) );
$hodima_chats   = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE user_id = %d AND last_message_preview <> '' ORDER BY last_message_at DESC LIMIT 20", table( 'chats' ), $hodima_user_id ) );
$hodima_ticket  = null;
$hodima_msgs    = [];
if ( $hodima_open ) {
	$hodima_ticket = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d AND user_id = %d', table( 'tickets' ), $hodima_open, $hodima_user_id ) );
	if ( $hodima_ticket ) {
		$hodima_msgs = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ticket_id = %d ORDER BY id ASC', table( 'ticket_messages' ), $hodima_open ) );
	}
}
if ( ! $hodima_ticket && ! $hodima_tickets ) {
	$hodima_new = true;
}
$hodima_status_class = static fn( string $s ): string => 'closed' === $s ? 'cancel' : ( 'answered' === $s ? 'done' : 'review' );
?>
<header class="hpn-head">
	<h1 class="hpn-title">پیام‌ها و پشتیبانی</h1>
	<p class="hpn-lead">سؤال عمومی را اینجا بپرسید؛ گفتگوی هر سفارش هم در همین فهرست است.</p>
</header>

<div class="hpn-inbox">
	<section class="hpn-card hpn-inbox__list" aria-label="فهرست گفتگوها">
		<a class="hpn-btn hpn-btn--block" href="<?php echo esc_url( Page::url( [ 'section' => 'support', 'new' => 1 ] ) ); ?>">پیام تازه به پشتیبانی</a>
		<?php if ( ! $hodima_tickets && ! $hodima_chats ) : ?>
			<p class="hpn-muted">هنوز گفتگویی ندارید.</p>
		<?php endif; ?>
		<ul class="hpn-threads">
			<?php foreach ( $hodima_tickets as $hodima_t ) : ?>
				<?php $hodima_active = $hodima_ticket && (int) $hodima_ticket->id === (int) $hodima_t->id; ?>
				<li>
					<a class="hpn-threads__item<?php echo $hodima_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( Page::url( [ 'section' => 'support', 'thread_id' => (int) $hodima_t->id ] ) ); ?>"<?php echo $hodima_active ? ' aria-current="true"' : ''; ?>>
						<span class="hpn-threads__top"><strong><?php echo esc_html( (string) $hodima_t->subject ); ?></strong><?php echo View::badge( (int) $hodima_t->user_unread, 'پاسخ تازه' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن. ?></span>
						<span class="hpn-threads__preview"><?php echo esc_html( ( 'admin' === $hodima_t->last_message_sender ? 'پشتیبانی: ' : '' ) . (string) $hodima_t->last_message_preview ); ?></span>
						<span class="hpn-muted hpn-threads__meta"><?php echo esc_html( ( ticket_departments()[ $hodima_t->department ] ?? '' ) . ' · ' . ( ticket_statuses()[ $hodima_t->status ] ?? '' ) . ' · ' . show_date( (string) ( $hodima_t->last_message_at ?: $hodima_t->created_at ), 'j F' ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
			<?php foreach ( $hodima_chats as $hodima_c ) : ?>
				<li>
					<a class="hpn-threads__item" href="<?php echo esc_url( Page::url( [ 'section' => 'orders', 'chat' => (int) $hodima_c->order_id ] ) . '#hpn-order-' . (int) $hodima_c->order_id ); ?>">
						<span class="hpn-threads__top"><strong><?php echo esc_html( 'گفتگوی سفارش #' . number_format_i18n( (int) $hodima_c->order_id ) ); ?></strong><?php echo View::badge( (int) $hodima_c->user_unread, 'پیام تازه' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن. ?></span>
						<span class="hpn-threads__preview"><?php echo esc_html( (string) $hodima_c->last_message_preview ); ?></span>
						<span class="hpn-muted hpn-threads__meta"><?php echo esc_html( 'سفارش · ' . show_date( (string) $hodima_c->last_message_at, 'j F' ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="hpn-card hpn-inbox__main" aria-labelledby="hpn-thread-title">
		<?php if ( $hodima_ticket ) : ?>
			<header class="hpn-inbox__head">
				<h2 class="hpn-card__title" id="hpn-thread-title"><?php echo esc_html( (string) $hodima_ticket->subject ); ?></h2>
				<span class="hpn-muted"><?php echo esc_html( (string) $hodima_ticket->reference_code . ' · ' . ( ticket_departments()[ $hodima_ticket->department ] ?? '' ) ); ?></span>
				<span class="hpn-pill hpn-pill--<?php echo esc_attr( $hodima_status_class( (string) $hodima_ticket->status ) ); ?>"><?php echo esc_html( ticket_statuses()[ $hodima_ticket->status ] ?? '' ); ?></span>
			</header>
			<div class="hpn-thread" tabindex="0" aria-label="پیام‌ها" data-hpn-scroll>
				<?php foreach ( $hodima_msgs as $hodima_m ) : ?>
					<?php $hodima_mine = 'user' === $hodima_m->sender_type; ?>
					<div class="hpn-msg hpn-msg--<?php echo esc_attr( $hodima_mine ? 'me' : ( 'system' === $hodima_m->sender_type ? 'system' : 'them' ) ); ?>">
						<p><?php echo nl2br( esc_html( (string) $hodima_m->message ) ); ?></p>
						<span class="hpn-msg__meta"><?php echo esc_html( ( $hodima_mine ? 'شما' : ( 'system' === $hodima_m->sender_type ? 'سیستم' : 'پشتیبانی' ) ) . ' · ' . show_date( (string) $hodima_m->created_at, 'j F، H:i' ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( 'closed' !== $hodima_ticket->status ) : ?>
				<form class="hpn-compose" method="post" action="<?php echo esc_url( Page::url( [ 'section' => 'support', 'thread_id' => (int) $hodima_ticket->id ] ) ); ?>">
					<input type="hidden" name="support_action" value="reply_to_ticket">
					<input type="hidden" name="thread_id" value="<?php echo esc_attr( (string) $hodima_ticket->id ); ?>">
					<?php wp_nonce_field( 'reply_ticket_nonce', '_wpnonce_reply_ticket', false ); ?>
					<label class="screen-reader-text" for="hpn-reply">پاسخ شما</label>
					<textarea id="hpn-reply" name="message" rows="2" required placeholder="پاسخ خود را بنویسید…"></textarea>
					<button class="hpn-btn" type="submit">ارسال</button>
				</form>
			<?php else : ?>
				<p class="hpn-muted hpn-inbox__closed">این گفتگو بسته شده است؛ برای سؤال تازه «پیام تازه به پشتیبانی» را بزنید.</p>
			<?php endif; ?>
		<?php elseif ( $hodima_new ) : ?>
			<h2 class="hpn-card__title" id="hpn-thread-title">پیام تازه به پشتیبانی</h2>
			<form class="hpn-new-ticket" method="post" action="<?php echo esc_url( Page::url( [ 'section' => 'support' ] ) ); ?>">
				<input type="hidden" name="support_action" value="create_ticket">
				<?php wp_nonce_field( 'create_ticket_nonce', '_wpnonce_create_ticket', false ); ?>
				<div class="hpn-field">
					<label class="hpn-label" for="hpn-subject">موضوع</label>
					<input class="hpn-input" id="hpn-subject" name="subject" required maxlength="200">
				</div>
				<div class="hpn-grid2">
					<div class="hpn-field">
						<label class="hpn-label" for="hpn-dept">با کدام واحد؟</label>
						<select class="hpn-input" id="hpn-dept" name="department">
							<?php foreach ( ticket_departments() as $hodima_key => $hodima_label ) : ?>
								<option value="<?php echo esc_attr( $hodima_key ); ?>"><?php echo esc_html( $hodima_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="hpn-field">
						<label class="hpn-label" for="hpn-priority">فوریت</label>
						<select class="hpn-input" id="hpn-priority" name="priority">
							<?php foreach ( ticket_priorities() as $hodima_key => $hodima_label ) : ?>
								<option value="<?php echo esc_attr( $hodima_key ); ?>"><?php echo esc_html( $hodima_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="hpn-field">
					<label class="hpn-label" for="hpn-message">پیام</label>
					<textarea class="hpn-input" id="hpn-message" name="message" rows="5" required></textarea>
				</div>
				<button class="hpn-btn hpn-btn--lg" type="submit">ارسال پیام</button>
			</form>
		<?php else : ?>
			<h2 class="screen-reader-text" id="hpn-thread-title">گفتگو</h2>
			<p class="hpn-empty">یک گفتگو را از فهرست انتخاب کنید.</p>
		<?php endif; ?>
	</section>
</div>
