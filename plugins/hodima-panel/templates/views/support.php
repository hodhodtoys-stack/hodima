<?php
/**
 * پیام‌ها و پشتیبانی: تیکت‌ها و گفتگوی سفارش‌ها در یک فهرست، رشته پیام، پاسخ (طرح تأییدشده، مرحله ۵).
 * «پیام تازه» فقط دو راه: مشاوره قبل از خرید (تیکت) یا پیگیری سفارش (گفتگوی همان سفارش؛ طرح تأییدشده 0.2.0).
 * پردازش فرم‌ها و داده: Support (includes/support.php).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Page;
use Hodima\Panel\Support;
use Hodima\Panel\View;
use function Hodima\Panel\show_date;
use function Hodima\Panel\ticket_departments;
use function Hodima\Panel\ticket_statuses;
use function Hodima\Panel\type_label;
use function Hodima\Panel\status_label;

$hodima_user_id = get_current_user_id();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما.
$hodima_inbox = Support::inbox( $hodima_user_id, absint( $_GET['thread_id'] ?? 0 ), isset( $_GET['new'] ), absint( $_GET['paged'] ?? 1 ) );
// phpcs:enable
$hodima_tickets = $hodima_inbox['tickets'];
$hodima_chats   = $hodima_inbox['chats'];
$hodima_ticket  = $hodima_inbox['ticket'];
$hodima_msgs    = $hodima_inbox['messages'];
$hodima_new     = $hodima_inbox['new'];
$hodima_orders  = $hodima_inbox['orders'];
$hodima_paged   = $hodima_inbox['paged'];
$hodima_status_class = static fn( string $s ): string => 'closed' === $s ? 'cancel' : ( 'answered' === $s ? 'done' : 'review' );
?>
<header class="hpn-head">
	<h1 class="hpn-title">پیام‌ها و پشتیبانی</h1>
	<p class="hpn-lead">پیش از خرید سؤال دارید یا درباره سفارشتان پیام می‌دهید؟ همه گفتگوها در همین فهرست است.</p>
</header>

<div class="hpn-inbox<?php echo $hodima_new && ! $hodima_ticket ? ' hpn-inbox--new' : ''; ?>">
	<section class="hpn-card hpn-inbox__list" aria-label="فهرست گفتگوها">
		<a class="hpn-btn hpn-btn--block" href="<?php echo esc_url( Page::url( [ 'section' => 'support', 'new' => 1 ] ) ); ?>">پیام تازه به پشتیبانی</a>
		<?php if ( ! $hodima_tickets && ! $hodima_chats ) : ?>
			<p class="hpn-muted">هنوز گفتگویی ندارید.</p>
		<?php endif; ?>
		<ul class="hpn-threads">
			<?php foreach ( $hodima_tickets as $hodima_t ) : ?>
				<?php $hodima_active = $hodima_ticket && (int) $hodima_ticket->id === (int) $hodima_t->id; ?>
				<li>
					<a class="hpn-threads__item<?php echo $hodima_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( Page::url( array_filter( [ 'section' => 'support', 'paged' => $hodima_paged > 1 ? $hodima_paged : 0, 'thread_id' => (int) $hodima_t->id ] ) ) ); ?>"<?php echo $hodima_active ? ' aria-current="true"' : ''; ?>>
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
		<?php
		if ( $hodima_inbox['pages'] > 1 ) {
			echo '<nav class="hpn-pager" aria-label="صفحه‌های گفتگوها">';
			echo wp_kses_post(
				(string) paginate_links(
					[
						'base'      => add_query_arg( 'paged', '%#%', Page::url( [ 'section' => 'support' ] ) ),
						'format'    => '',
						'current'   => $hodima_paged,
						'total'     => $hodima_inbox['pages'],
						'prev_text' => 'قبلی',
						'next_text' => 'بعدی',
					]
				)
			);
			echo '</nav>';
		}
		?>
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
			<form class="hpn-new-ticket" method="post" action="<?php echo esc_url( Page::url( [ 'section' => 'support' ] ) ); ?>" data-hpn-topic-form>
				<input type="hidden" name="support_action" value="create_ticket">
				<?php wp_nonce_field( 'create_ticket_nonce', '_wpnonce_create_ticket', false ); ?>
				<fieldset class="hpn-topics">
					<legend>درباره چه چیزی پیام می‌دهید؟</legend>
					<div class="hpn-topics__list">
						<label class="hpn-topic">
							<input type="radio" name="topic" value="consult" checked>
							<span class="hpn-topic__text"><strong>مشاوره قبل از خرید</strong><span class="hpn-muted">سؤال درباره محصول، موجودی، قیمت یا حداقل سفارش</span></span>
						</label>
						<label class="hpn-topic<?php echo $hodima_orders ? '' : ' is-disabled'; ?>">
							<input type="radio" name="topic" value="order"<?php disabled( ! $hodima_orders ); ?>>
							<span class="hpn-topic__text"><strong>پیگیری سفارش</strong><span class="hpn-muted"><?php echo esc_html( $hodima_orders ? 'پیام شما در گفتگوی همان سفارش ثبت می‌شود' : 'هنوز سفارشی ندارید' ); ?></span></span>
						</label>
					</div>
				</fieldset>

				<fieldset class="hpn-topic-panel" data-hpn-topic-panel="consult">
					<legend>مشاوره قبل از خرید</legend>
					<div class="hpn-field">
						<label class="hpn-label" for="hpn-subject">موضوع <span class="hpn-muted">(اختیاری)</span></label>
						<input class="hpn-input" id="hpn-subject" name="subject" maxlength="200" placeholder="مثلا: موجودی کش موی مخملی">
					</div>
					<div class="hpn-field">
						<label class="hpn-label" for="hpn-message">پیام</label>
						<textarea class="hpn-input" id="hpn-message" name="message" rows="5"></textarea>
					</div>
					<button class="hpn-btn hpn-btn--lg" type="submit" name="topic_submit" value="consult">ارسال پیام</button>
				</fieldset>

				<?php if ( $hodima_orders ) : ?>
					<fieldset class="hpn-topic-panel" data-hpn-topic-panel="order">
						<legend>پیگیری سفارش</legend>
						<div class="hpn-field">
							<label class="hpn-label" for="hpn-order">کدام سفارش؟</label>
							<select class="hpn-input" id="hpn-order" name="order_id" aria-describedby="hpn-order-help">
								<?php foreach ( $hodima_orders as $hodima_o ) : ?>
									<option value="<?php echo esc_attr( (string) $hodima_o->id ); ?>"><?php echo esc_html( 'سفارش #' . number_format_i18n( (int) $hodima_o->id ) . ' · ' . type_label( (string) $hodima_o->type ) . ' · ' . status_label( (string) $hodima_o->status ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="hpn-help" id="hpn-order-help">پاسخ پشتیبانی هم در همین فهرست و هم روی کارت همان سفارش در «سفارش‌ها» دیده می‌شود.</p>
						</div>
						<div class="hpn-field">
							<label class="hpn-label" for="hpn-order-message">پیام</label>
							<textarea class="hpn-input" id="hpn-order-message" name="order_message" rows="5"></textarea>
						</div>
						<button class="hpn-btn hpn-btn--lg" type="submit" name="topic_submit" value="order">ارسال در گفتگوی سفارش</button>
					</fieldset>
				<?php endif; ?>
			</form>
		<?php else : ?>
			<h2 class="screen-reader-text" id="hpn-thread-title">گفتگو</h2>
			<p class="hpn-empty">یک گفتگو را از فهرست انتخاب کنید.</p>
		<?php endif; ?>
	</section>
</div>
