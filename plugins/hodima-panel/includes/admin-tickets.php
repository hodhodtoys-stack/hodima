<?php
/**
 * پیشخوان: پنل مشتریان ← تیکت‌های پشتیبانی (فهرست و پاسخ کنار هم).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * تیکت‌ها در پیشخوان (طرح تأییدشده کاربر، مرحله ۶).
 *
 * قبلا فهرست و هر تیکت دو صفحه جدا بودند و صفحه تیکت نام/موبایل مشتری را نداشت؛
 * بستن تیکت با confirm() درون‌خطی بود. حالا فهرست (با «نیاز به پاسخ»، جستجو) و رشته پیام
 * کنار هم؛ در موبایل تیکت بازشده اول می‌آید و پیوند «همه تیکت‌ها» دارد.
 */
final class Admin_Tickets {

	public const SLUG = 'hodima-panel-support';

	private const PER_PAGE = 30;

	/** نماها: کلید => [برچسب، وضعیت‌ها]. «نیاز به پاسخ» = آخرین پیام از مشتری (open). */
	public const VIEWS = [
		'open'   => [ 'نیاز به پاسخ', [ 'open' ] ],
		'all'    => [ 'همه', [] ],
		'closed' => [ 'بسته', [ 'closed' ] ],
	];

	/** ثبت هوک‌ها. */
	public static function boot(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ], 11 );
	}

	/** زیرمنو زیر «پنل مشتریان». */
	public static function menu(): void {
		$hook = add_submenu_page( Admin_Orders::SLUG, 'تیکت‌های پشتیبانی', 'تیکت‌های پشتیبانی', Admin_Orders::CAP, self::SLUG, [ self::class, 'render' ] );
		if ( $hook ) {
			add_action( 'load-' . $hook, [ self::class, 'handle' ] );
		}
	}

	/**
	 * آدرس صفحه.
	 *
	 * @param array<string, int|string> $args پارامترها.
	 */
	public static function url( array $args = [] ): string {
		return add_query_arg( [ 'page' => self::SLUG ] + $args, admin_url( 'admin.php' ) );
	}

	/** پاسخ و بستن (load-{صفحه}؛ بعد ریدایرکت). */
	public static function handle(): void {
		if ( ! current_user_can( Admin_Orders::CAP ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		global $wpdb;
		$ticket_id = absint( $_POST['thread_id'] ?? 0 );
		if ( ! $ticket_id || ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', table( 'tickets' ), $ticket_id ) ) ) {
			return;
		}
		check_admin_referer( 'hodima_panel_ticket_' . $ticket_id );
		$action = sanitize_key( wp_unslash( (string) ( $_POST['hp_action'] ?? '' ) ) );
		$view   = [ 'thread_id' => $ticket_id ];
		$now    = now();

		if ( 'reply' === $action ) {
			$message = sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) );
			if ( '' === trim( $message ) ) {
				self::back( $view );
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
			self::back( $view + [ 'hp_msg' => 'reply' ] );
		}
		if ( 'close' === $action ) {
			$wpdb->update( table( 'tickets' ), [ 'status' => 'closed', 'admin_unread' => 0, 'updated_at' => $now ], [ 'id' => $ticket_id ] );
			clear_admin_badge();
			self::back( $view + [ 'hp_msg' => 'closed' ] );
		}
	}

	/**
	 * برگشت با پیام.
	 *
	 * @param array<string, int|string> $args پارامترها.
	 */
	private static function back( array $args ): never {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/** نشان وضعیت تیکت. */
	public static function pill( string $status ): string {
		[ $label, $class ] = match ( $status ) {
			'open'     => [ 'نیاز به پاسخ', 'hdp-pill--info' ],
			'answered' => [ 'پاسخ داده شد', '' ],
			default    => [ 'بسته', 'hpa-pill--muted' ],
		};
		return '<span class="hdp-pill ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/** صفحه. */
	public static function render(): void {
		global $wpdb;
		if ( ! current_user_can( Admin_Orders::CAP ) ) {
			wp_die( 'اجازه ندارید.' );
		}
		$counts = [];
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i GROUP BY status', table( 'tickets' ) ) ) as $row ) {
			$counts[ (string) $row->status ] = (int) $row->n;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فیلتر نما.
		$view      = sanitize_key( wp_unslash( (string) ( $_GET['view'] ?? '' ) ) );
		$search    = trim( sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) ) );
		$ticket_id = absint( $_GET['thread_id'] ?? 0 );
		$msg       = sanitize_key( wp_unslash( (string) ( $_GET['hp_msg'] ?? '' ) ) );
		// phpcs:enable
		if ( ! isset( self::VIEWS[ $view ] ) ) {
			$view = ( $counts['open'] ?? 0 ) > 0 ? 'open' : 'all';
		}

		$where = '1=1';
		$args  = [];
		if ( self::VIEWS[ $view ][1] ) {
			$where .= ' AND t.status = %s';
			$args[] = self::VIEWS[ $view ][1][0];
		}
		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= ' AND (t.subject LIKE %s OR t.reference_code LIKE %s OR u.display_name LIKE %s)';
			array_push( $args, $like, $like, $like );
		}
		$sql  = "SELECT t.*, u.display_name FROM %i t LEFT JOIN %i u ON u.ID = t.user_id WHERE $where ORDER BY t.last_message_at DESC, t.id DESC LIMIT %d";
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, ...[ table( 'tickets' ), $wpdb->users, ...$args, self::PER_PAGE ] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where فقط placeholder.
		$open = $ticket_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT t.*, u.display_name FROM %i t LEFT JOIN %i u ON u.ID = t.user_id WHERE t.id = %d', table( 'tickets' ), $wpdb->users, $ticket_id ) ) : null;
		if ( $open && (int) $open->admin_unread > 0 ) {
			$wpdb->update( table( 'tickets' ), [ 'admin_unread' => 0 ], [ 'id' => $ticket_id ] );
			clear_admin_badge();
		}
		$base = array_filter( [ 's' => $search ] );
		?>
		<div class="wrap hdp-ui hdp-page hodima-panel-admin">
			<header class="hdp-hero">
				<span class="hdp-hero__icon dashicons dashicons-format-chat" aria-hidden="true"></span>
				<div>
					<span class="hdp-hero__badge">پنل مشتریان</span>
					<h1 class="hdp-hero__title">تیکت‌های پشتیبانی</h1>
					<p class="hdp-hero__desc">سؤال‌های عمومی مشتری‌ها؛ گفتگوی هر سفارش در صفحه همان سفارش است.</p>
				</div>
			</header>
			<hr class="wp-header-end">
			<?php
			if ( 'reply' === $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>پاسخ برای مشتری فرستاده شد.</p></div>';
			} elseif ( 'closed' === $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>تیکت بسته شد.</p></div>';
			}
			?>
			<div class="hdp-page__body">
				<ul class="hdp-stats hpa-stats hpa-stats--3" aria-label="خلاصه تیکت‌ها">
					<li class="hdp-stat<?php echo ( $counts['open'] ?? 0 ) > 0 ? ' is-hot' : ''; ?>"><span class="hdp-stat__label">نیاز به پاسخ</span><span class="hdp-stat__value"><?php echo esc_html( number_format_i18n( $counts['open'] ?? 0 ) ); ?></span></li>
					<li class="hdp-stat"><span class="hdp-stat__label">پاسخ داده شد (منتظر مشتری)</span><span class="hdp-stat__value"><?php echo esc_html( number_format_i18n( $counts['answered'] ?? 0 ) ); ?></span></li>
					<li class="hdp-stat"><span class="hdp-stat__label">بسته</span><span class="hdp-stat__value"><?php echo esc_html( number_format_i18n( $counts['closed'] ?? 0 ) ); ?></span></li>
				</ul>

				<div class="hpa-inbox<?php echo $open ? ' has-open' : ''; ?>">
					<section class="hdp-card hpa-inbox__list" aria-label="فهرست تیکت‌ها">
						<nav class="hpa-views" aria-label="نماها">
							<?php
							foreach ( self::VIEWS as $key => [ $label, $statuses ] ) :
								$n = $statuses ? ( $counts[ $statuses[0] ] ?? 0 ) : array_sum( $counts );
								?>
								<a class="hpa-view<?php echo $key === $view ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::url( $base + [ 'view' => $key ] ) ); ?>"<?php echo $key === $view ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?> <b><?php echo esc_html( number_format_i18n( $n ) ); ?></b></a>
							<?php endforeach; ?>
						</nav>
						<form method="get" class="hpa-search" role="search">
							<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
							<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
							<label class="screen-reader-text" for="hpa-ts">جستجوی تیکت</label>
							<input type="search" id="hpa-ts" name="s" class="hdp-input" value="<?php echo esc_attr( $search ); ?>" placeholder="موضوع، نام یا کد پیگیری…">
						</form>
						<?php if ( ! $rows ) : ?>
							<p class="hpa-none"><?php echo esc_html( 'open' === $view && '' === $search ? '✓ تیکتی منتظر پاسخ نیست.' : 'تیکتی با این فیلتر نیست.' ); ?></p>
						<?php else : ?>
							<ul class="hpa-threads">
								<?php foreach ( $rows as $t ) : ?>
									<?php $active = $open && (int) $open->id === (int) $t->id; ?>
									<li>
										<a class="hpa-thread-item<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::url( $base + [ 'view' => $view, 'thread_id' => (int) $t->id ] ) ); ?>"<?php echo $active ? ' aria-current="true"' : ''; ?>>
											<span class="hpa-thread-item__top"><strong><?php echo esc_html( (string) $t->subject ?: 'بدون موضوع' ); ?></strong><?php echo self::pill( (string) $t->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- امن. ?></span>
											<span class="hpa-sub"><?php echo esc_html( ( (string) ( $t->display_name ?? '' ) ?: 'کاربر حذف‌شده' ) . ' · ' . ( ticket_departments()[ $t->department ] ?? '' ) . ' · ' . show_date( (string) ( $t->last_message_at ?: $t->created_at ), 'j F' ) ); ?></span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</section>

					<section class="hdp-card hpa-inbox__main" aria-labelledby="hpa-ticket-title">
						<?php if ( $open ) : ?>
							<?php self::render_thread( $open, $base + [ 'view' => $view ] ); ?>
						<?php else : ?>
							<h2 class="screen-reader-text" id="hpa-ticket-title">تیکت</h2>
							<p class="hpa-none">یک تیکت را از فهرست انتخاب کنید.</p>
						<?php endif; ?>
					</section>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * رشته پیام یک تیکت و فرم پاسخ.
	 *
	 * @param object               $t    تیکت.
	 * @param array<string, mixed> $base پارامترهای فهرست.
	 */
	private static function render_thread( object $t, array $base ): void {
		global $wpdb;
		$id       = (int) $t->id;
		$messages = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ticket_id = %d ORDER BY id ASC', table( 'ticket_messages' ), $id ) );
		$name     = (string) ( $t->display_name ?? '' ) ?: 'کاربر حذف‌شده';
		$mobile   = (string) ( get_user_meta( (int) $t->user_id, 'mobile', true ) ?: get_user_meta( (int) $t->user_id, 'billing_phone', true ) );
		$self     = self::url( $base + [ 'thread_id' => $id ] );
		?>
		<a class="hpa-link hpa-inbox__back" href="<?php echo esc_url( self::url( $base ) ); ?>">همه تیکت‌ها</a>
		<header class="hpa-ticket-head">
			<div>
				<h2 class="hdp-card__title" id="hpa-ticket-title"><?php echo esc_html( (string) $t->subject ?: 'بدون موضوع' ); ?></h2>
				<p class="hpa-sub"><?php echo esc_html( (string) $t->reference_code . ' · ' . $name ); ?><?php if ( '' !== $mobile ) : ?> (<bdi dir="ltr"><?php echo esc_html( $mobile ); ?></bdi>)<?php endif; ?><?php echo esc_html( ' · ' . ( ticket_departments()[ $t->department ] ?? '' ) ); ?></p>
			</div>
			<?php echo self::pill( (string) $t->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- امن. ?>
		</header>
		<div class="hpa-thread" tabindex="0" role="region" aria-label="پیام‌های تیکت" data-hpa-scroll>
			<?php foreach ( $messages as $m ) : ?>
				<?php
				$who = match ( (string) $m->sender_type ) {
					'admin'  => 'پشتیبانی',
					'system' => 'سیستم',
					default  => $name,
				};
				?>
				<div class="hpa-msg hpa-msg--<?php echo esc_attr( (string) $m->sender_type ); ?>"><p><?php echo nl2br( esc_html( (string) $m->message ) ); ?></p><span class="hpa-msg__meta"><?php echo esc_html( $who . ' · ' . show_date( (string) $m->created_at, 'j F، H:i' ) ); ?></span></div>
			<?php endforeach; ?>
		</div>
		<?php if ( 'closed' === $t->status ) : ?>
			<p class="hpa-none">این تیکت بسته شده است؛ مشتری برای سؤال تازه تیکت جدید می‌فرستد.</p>
			<?php return; ?>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( $self ); ?>" class="hpa-reply">
			<?php wp_nonce_field( 'hodima_panel_ticket_' . $id ); ?>
			<input type="hidden" name="thread_id" value="<?php echo esc_attr( (string) $id ); ?>">
			<label class="screen-reader-text" for="hpa-reply">پاسخ شما</label>
			<textarea id="hpa-reply" name="message" class="hdp-input" rows="4" placeholder="پاسخ شما…"></textarea>
			<div class="hpa-reply__actions">
				<button type="submit" name="hp_action" value="reply" class="hdp-btn" data-hpa-require="hpa-reply">ارسال پاسخ</button>
				<button type="submit" name="hp_action" value="close" class="hdp-btn hdp-btn--outline" data-hpa-confirm="این تیکت بسته شود؟">بستن تیکت</button>
				<span class="hpa-sub"><?php echo esc_html( 'پاسخ با نام «پشتیبانی» دیده می‌شود.' ); ?></span>
			</div>
		</form>
		<?php
	}
}
