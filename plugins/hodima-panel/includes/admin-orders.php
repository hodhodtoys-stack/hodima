<?php
/**
 * پیشخوان: پنل مشتریان ← سفارش‌ها (فهرست همه سفارش‌ها و صفحه یک سفارش).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * سفارش‌ها در پیشخوان (طرح تأییدشده کاربر، مرحله ۶؛ قانون ۸ و اسکیل hodima-admin-ui).
 *
 * پنل قدیمی اول فهرست مشتری‌ها را نشان می‌داد و برای دیدن کار مانده باید تک‌تک باز می‌شدند؛
 * تغییر وضعیت با یک «ثبت» کنار هر ردیف بود و برای «تکمیل» باید از صفحه سفارش به فهرست
 * برمی‌گشتید؛ عدد کنار نام منو هم بود (قاعده ثابت ۴: نه). حالا یک فهرست با آمار «کار شما»،
 * فیلتر و جستجو؛ صفحه هر سفارش «کار بعدی» را بالای صفحه دارد.
 * کالبد: سربرگ ← آمار ← نوار ابزار ← جدول (موبایل کارت). اجزا از کیت همین افزونه (hdp-).
 */
final class Admin_Orders {

	public const SLUG = 'hodima-panel-orders';

	public const CAP = 'manage_options';

	private const PER_PAGE = 20;

	/** نماها: کلید => [برچسب، وضعیت‌ها]. «کار شما» = منتظر پیش‌فاکتور یا بررسی فیش. */
	public const VIEWS = [
		''       => [ 'همه', [] ],
		'todo'   => [ 'کار شما', [ 'pending', 'receipt_uploaded' ] ],
		'wait'   => [ 'منتظر پرداخت', [ 'awaiting_payment' ] ],
		'done'   => [ 'تکمیل', [ 'completed' ] ],
		'cancel' => [ 'لغو', [ 'cancelled' ] ],
	];

	/** ثبت هوک‌ها (فقط پیشخوان). */
	public static function boot(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'admin_notices', [ self::class, 'kit_notice' ] );
	}

	/** آیا کیت UI (پوشه ui/) نصب شده؟ فقط در ZIP انتشار است، نه در سورس گیت‌هاب. */
	public static function kit_ready(): bool {
		return is_file( HODIMA_PANEL_DIR . '/ui/ui-kit.css' );
	}

	/**
	 * هشدار نصب ناقص.
	 *
	 * پوشه ui/ (کیت ظاهر پیشخوان) در گیت نیست و bin/build.sh هنگام ساخت ZIP آن را می‌سازد.
	 * نصب از کپی پوشه سورس (مثلا «Download ZIP» گیت‌هاب) صفحه‌ها را بی‌استایل می‌کرد و هیچ
	 * پیامی نمی‌داد (گزارش کاربر، نصب لوکال 0.1.8).
	 */
	public static function kit_notice(): void {
		if ( self::kit_ready() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html( 'Hodima Panel ناقص نصب شده: پوشه ui (ظاهر صفحه‌های پیشخوان) نیست، پس صفحه‌های «پنل مشتریان» بی‌طراحی دیده می‌شوند. افزونه را حذف کنید و فایل hodima-panel.zip پوشه release مخزن را از «افزونه‌ها ← افزودن ← بارگذاری» نصب کنید (نه پوشه سورس گیت‌هاب).' );
		echo '</p></div>';
	}

	/** منوی «پنل مشتریان» بدون نشان عدد (شمار کار فقط داخل صفحه). */
	public static function menu(): void {
		$hook = add_menu_page( 'سفارش‌های پنل مشتریان', 'پنل مشتریان', self::CAP, self::SLUG, [ self::class, 'render' ], 'dashicons-list-view', 58 );
		add_submenu_page( self::SLUG, 'سفارش‌های پنل مشتریان', 'سفارش‌ها', self::CAP, self::SLUG, [ self::class, 'render' ] );
		if ( $hook ) {
			add_action( 'load-' . $hook, [ self::class, 'handle' ] );
		}
	}

	/**
	 * کیت UI و CSS صفحه‌های پنل مشتریان.
	 *
	 * @param string $hook شناسه صفحه.
	 */
	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, self::SLUG ) && ! str_contains( $hook, Admin_Tickets::SLUG ) ) {
			return;
		}
		self::enqueue_kit();
	}

	/** کیت مستقل افزونه + CSS مشترک صفحه‌های سفارش و تیکت. */
	public static function enqueue_kit(): void {
		$base = HODIMA_PANEL_URL;
		wp_enqueue_style( 'hodima-panel-ui', $base . '/ui/ui-kit.css', [], asset_version( 'ui/ui-kit.css' ) );
		wp_enqueue_script( 'hodima-panel-ui', $base . '/ui/ui-kit.js', [], asset_version( 'ui/ui-kit.js' ), true );
		wp_enqueue_style( 'hodima-panel-admin', $base . '/assets/css/admin.css', [ 'hodima-panel-ui' ], asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'hodima-panel-admin', $base . '/assets/js/admin.js', [], asset_version( 'assets/js/admin.js' ), true );
	}

	/**
	 * آدرس صفحه با پارامترها.
	 *
	 * @param array<string, int|string> $args پارامترها.
	 */
	public static function url( array $args = [] ): string {
		return add_query_arg( [ 'page' => self::SLUG ] + $args, admin_url( 'admin.php' ) );
	}

	/**
	 * برگشت با پیام نتیجه (PRG: رفرش فرم را دوباره نمی‌فرستد).
	 *
	 * @param array<string, int|string> $args پارامترها.
	 */
	private static function back( array $args ): never {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/** نام پاسخگو برای امضای پیام‌های مدیر. */
	public static function agent_name(): string {
		$user = wp_get_current_user();
		$name = trim( $user->first_name . ' ' . $user->last_name );
		return '' !== $name ? $name : $user->display_name;
	}

	/** پردازش فرم‌ها پیش از چاپ صفحه (load-{صفحه}). */
	public static function handle(): void {
		if ( ! current_user_can( self::CAP ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		global $wpdb;

		// حذف گروهی از فهرست
		if ( isset( $_POST['hp_bulk'] ) ) {
			check_admin_referer( 'hodima_panel_orders_bulk' );
			$ids = array_filter( array_map( 'absint', (array) wp_unslash( $_POST['order_ids'] ?? [] ) ) );
			if ( 'delete' === sanitize_key( wp_unslash( (string) $_POST['hp_bulk'] ) ) && $ids ) {
				foreach ( $ids as $id ) {
					Orders::delete( $id );
				}
				self::back( [ 'hp_msg' => 'deleted' ] );
			}
			self::back( [] );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = $order_id ? Orders::get( $order_id ) : null;
		if ( ! $order ) {
			return;
		}
		check_admin_referer( 'hodima_panel_order_' . $order_id );
		$owner  = (int) $order->user_id;
		$view   = [ 'action' => 'view', 'order_id' => $order_id ];
		$action = sanitize_key( wp_unslash( (string) ( $_POST['hp_action'] ?? '' ) ) );
		$set    = static function ( string $status ) use ( $wpdb, $order_id ): void {
			$wpdb->update( table( 'orders' ), [ 'status' => $status, 'updated_at' => now() ], [ 'id' => $order_id ], [ '%s', '%s' ], [ '%d' ] );
			clear_admin_badge();
		};

		if ( 'chat' === $action ) {
			$text = sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) );
			if ( '' !== trim( $text ) ) {
				Orders::add_chat_message( $order_id, $owner, 'admin', $text . "\n\nپاسخگو: " . self::agent_name() );
			}
			self::back( $view + [ 'hp_msg' => 'chat' ] );
		}
		if ( 'invoice' === $action ) {
			$file   = isset( $_FILES['invoice_file'] ) && is_array( $_FILES['invoice_file'] ) ? $_FILES['invoice_file'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Files::upload نوع، اندازه و محتوا را می‌سنجد.
			$result = Files::upload( $order_id, Files::INVOICE, $file );
			if ( is_string( $result ) ) {
				self::back( $view + [ 'hp_msg' => $result ] );
			}
			// پیش‌فاکتور تازه برای سفارش در حال بررسی = منتظر پرداخت؛ برای بقیه وضعیت دست نمی‌خورد
			if ( 'pending' === $order->status ) {
				$set( 'awaiting_payment' );
			}
			Orders::add_chat_message( $order_id, $owner, 'system', 'پیش‌فاکتور سفارش شما صادر شد. فایل را از کارت همین سفارش ببینید و پس از پرداخت، فیش واریزی را بفرستید.', 'user' );
			self::back( $view + [ 'hp_msg' => 'invoice' ] );
		}
		if ( 'complete' === $action ) {
			$set( 'completed' );
			Orders::add_chat_message( $order_id, $owner, 'system', 'پرداخت شما تأیید شد و سفارش تکمیل شد. از خرید شما سپاسگزاریم.', 'user' );
			self::back( $view + [ 'hp_msg' => 'completed' ] );
		}
		if ( 'status' === $action ) {
			$status = sanitize_key( wp_unslash( (string) ( $_POST['order_status'] ?? '' ) ) );
			if ( isset( order_statuses()[ $status ] ) ) {
				$set( $status );
			}
			self::back( $view + [ 'hp_msg' => 'status' ] );
		}
	}

	/** پیام نتیجه بعد از ریدایرکت. */
	public static function notice(): void {
		$messages = [
			'status'    => [ 'وضعیت سفارش به‌روز شد.', 'success' ],
			'chat'      => [ 'پیام برای مشتری فرستاده شد.', 'success' ],
			'invoice'   => [ 'پیش‌فاکتور فرستاده شد و مشتری پیام خودکار گرفت.', 'success' ],
			'completed' => [ 'سفارش تکمیل شد و مشتری پیام خودکار گرفت.', 'success' ],
			'deleted'   => [ 'سفارش‌های انتخاب‌شده حذف شدند.', 'success' ],
		];
		foreach ( Files::errors() as $code => $text ) {
			$messages[ $code ] = [ $text, 'error' ];
		}
		$key = sanitize_key( wp_unslash( (string) ( $_GET['hp_msg'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط پیام.
		if ( isset( $messages[ $key ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $key ][1] ), esc_html( $messages[ $key ][0] ) );
		}
	}

	/** نشان وضعیت سفارش برای مدیر (برچسب از دید مدیر؛ رنگ = آیا کار مدیر است). */
	public static function pill( string $status ): string {
		[ $label, $class ] = match ( $status ) {
			'pending'          => [ 'نیاز به پیش‌فاکتور', 'hdp-pill--info' ],
			'receipt_uploaded' => [ 'فیش برای بررسی', 'hdp-pill--info' ],
			'awaiting_payment' => [ 'منتظر پرداخت مشتری', 'hdp-pill--warn' ],
			'completed'        => [ 'تکمیل‌شده', '' ],
			'cancelled'        => [ 'لغوشده', 'hdp-pill--error' ],
			default            => [ status_label( $status ), 'hdp-pill--info' ],
		};
		return '<span class="hdp-pill ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * شمار سفارش‌ها در هر وضعیت.
	 *
	 * @return array<string, int>
	 */
	public static function counts(): array {
		global $wpdb;
		$out = [];
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i GROUP BY status', table( 'orders' ) ) ) as $row ) {
			$out[ (string) $row->status ] = (int) $row->n;
		}
		return $out;
	}

	/**
	 * شرط WHERE فهرست از فیلترهای آدرس.
	 *
	 * @return array{0: string, 1: list<int|string>}
	 */
	private static function where( string $view, int $user_id, string $search ): array {
		global $wpdb;
		$sql  = '1=1';
		$args = [];
		$st   = self::VIEWS[ $view ][1] ?? [];
		if ( $st ) {
			$sql .= ' AND o.status IN (' . implode( ',', array_fill( 0, count( $st ), '%s' ) ) . ')';
			$args = array_merge( $args, $st );
		}
		if ( $user_id ) {
			$sql   .= ' AND o.user_id = %d';
			$args[] = $user_id;
		}
		if ( '' !== $search ) {
			// شماره سفارش (با یا بی #)، یا نام/موبایل مشتری
			$num   = (int) ltrim( Profile::digits( $search ), '#' );
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$plike = '%' . $wpdb->esc_like( Profile::digits( $search ) ) . '%';
			$users = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT u.ID FROM %i u LEFT JOIN %i m ON m.user_id = u.ID AND m.meta_key IN ('mobile', 'billing_phone') WHERE u.display_name LIKE %s OR m.meta_value LIKE %s LIMIT 200", $wpdb->users, $wpdb->usermeta, $like, $plike ) ) );
			$parts = [];
			if ( $num > 0 && preg_match( '/^#?\d+$/', Profile::digits( $search ) ) ) {
				$parts[] = 'o.id = %d';
				$args[]  = $num;
			}
			if ( $users ) {
				$parts[] = 'o.user_id IN (' . implode( ',', array_fill( 0, count( $users ), '%d' ) ) . ')';
				$args    = array_merge( $args, $users );
			}
			$sql .= $parts ? ' AND (' . implode( ' OR ', $parts ) . ')' : ' AND 1=0';
		}
		return [ $sql, $args ];
	}

	/** صفحه: فهرست یا یک سفارش. */
	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'اجازه ندارید.' );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما.
		$order_id = absint( $_GET['order_id'] ?? 0 );
		$is_view  = 'view' === sanitize_key( wp_unslash( (string) ( $_GET['action'] ?? '' ) ) );
		// phpcs:enable
		if ( $is_view && $order_id ) {
			self::render_order( $order_id );
			return;
		}
		self::render_list();
	}

	/** فهرست همه سفارش‌ها. */
	private static function render_list(): void {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فیلتر نما.
		$view    = sanitize_key( wp_unslash( (string) ( $_GET['view'] ?? '' ) ) );
		$view    = isset( self::VIEWS[ $view ] ) ? $view : '';
		$user_id = absint( $_GET['user_id'] ?? 0 );
		$search  = trim( sanitize_text_field( wp_unslash( (string) ( $_GET['s'] ?? '' ) ) ) );
		$paged   = max( 1, absint( $_GET['paged'] ?? 1 ) );
		// phpcs:enable
		$counts = self::counts();
		$unread = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(admin_unread) FROM %i', table( 'chats' ) ) );

		[ $where, $args ] = self::where( $view, $user_id, $search );
		$sql_total        = "SELECT COUNT(*) FROM %i o WHERE $where";
		$total            = (int) $wpdb->get_var( $wpdb->prepare( $sql_total, ...[ table( 'orders' ), ...$args ] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where فقط از placeholder ساخته شده.
		$sql_rows         = "SELECT o.*, c.admin_unread FROM %i o LEFT JOIN %i c ON c.order_id = o.id WHERE $where ORDER BY o.id DESC LIMIT %d OFFSET %d";
		$rows             = (array) $wpdb->get_results( $wpdb->prepare( $sql_rows, ...[ table( 'orders' ), table( 'chats' ), ...$args, self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- همان.
		$items            = self::item_summaries( array_map( static fn( object $r ): int => (int) $r->id, $rows ) );
		$customer         = $user_id ? get_userdata( $user_id ) : null;
		$base             = array_filter( [ 'user_id' => $user_id, 's' => $search ] );
		?>
		<div class="wrap hdp-ui hdp-page hodima-panel-admin">
			<header class="hdp-hero">
				<span class="hdp-hero__icon dashicons dashicons-list-view" aria-hidden="true"></span>
				<div>
					<span class="hdp-hero__badge">پنل مشتریان</span>
					<h1 class="hdp-hero__title">سفارش‌های پنل مشتریان</h1>
					<p class="hdp-hero__desc">پیش‌خریدها و پیش‌فاکتورها؛ ردیف‌های سبز کار شما را دارند.</p>
				</div>
			</header>
			<hr class="wp-header-end">
			<?php self::notice(); ?>
			<div class="hdp-page__body">
				<ul class="hdp-stats hpa-stats" aria-label="خلاصه سفارش‌ها">
					<?php
					foreach ( [
						[ 'نیاز به پیش‌فاکتور', (int) ( $counts['pending'] ?? 0 ), true ],
						[ 'فیش برای بررسی', (int) ( $counts['receipt_uploaded'] ?? 0 ), true ],
						[ 'منتظر پرداخت مشتری', (int) ( $counts['awaiting_payment'] ?? 0 ), false ],
						[ 'پیام تازه مشتری‌ها', $unread, true ],
					] as [ $label, $n, $hot ] ) :
						?>
						<li class="hdp-stat<?php echo $hot && $n > 0 ? ' is-hot' : ''; ?>"><span class="hdp-stat__label"><?php echo esc_html( $label ); ?></span><span class="hdp-stat__value"><?php echo esc_html( number_format_i18n( $n ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>

				<section class="hdp-card hpa-list" aria-label="فهرست سفارش‌ها">
					<div class="hpa-toolbar">
						<div class="hpa-bulk">
							<label class="screen-reader-text" for="hpa-bulk">کار گروهی</label>
							<select id="hpa-bulk" name="hp_bulk" class="hdp-input" form="hpa-orders-form">
								<option value="">کار گروهی</option>
								<option value="delete">حذف</option>
							</select>
							<button type="submit" class="hdp-btn hdp-btn--outline" form="hpa-orders-form" data-hpa-confirm="سفارش‌های انتخاب‌شده با فایل‌ها و گفتگو حذف شوند؟">اجرا</button>
						</div>
						<nav class="hpa-views" aria-label="نماها">
							<?php
							foreach ( self::VIEWS as $key => [ $label, $statuses ] ) :
								$n = $statuses ? array_sum( array_intersect_key( $counts, array_flip( $statuses ) ) ) : array_sum( $counts );
								?>
								<a class="hpa-view<?php echo $key === $view ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::url( $base + array_filter( [ 'view' => $key ] ) ) ); ?>"<?php echo $key === $view ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?> <b><?php echo esc_html( number_format_i18n( $n ) ); ?></b></a>
							<?php endforeach; ?>
						</nav>
						<form method="get" class="hpa-search" role="search">
							<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
							<?php if ( '' !== $view ) : ?>
								<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
							<?php endif; ?>
							<label class="screen-reader-text" for="hpa-s">جستجوی سفارش</label>
							<input type="search" id="hpa-s" name="s" class="hdp-input" value="<?php echo esc_attr( $search ); ?>" placeholder="نام، موبایل یا شماره سفارش…">
						</form>
					</div>

					<?php if ( $customer || $user_id ) : ?>
						<p class="hpa-filter">مشتری: <strong><?php echo esc_html( $customer ? $customer->display_name : 'کاربر حذف‌شده' ); ?></strong> <a class="hpa-link" href="<?php echo esc_url( self::url( array_filter( [ 'view' => $view ] ) ) ); ?>">همه مشتری‌ها</a></p>
					<?php endif; ?>

					<form method="post" id="hpa-orders-form" action="<?php echo esc_url( self::url( $base + array_filter( [ 'view' => $view ] ) ) ); ?>">
						<?php wp_nonce_field( 'hodima_panel_orders_bulk' ); ?>
					</form>

					<?php if ( ! $rows ) : ?>
						<div class="hdp-empty"><span class="hdp-empty__icon dashicons dashicons-yes" aria-hidden="true"></span><div><strong class="hdp-empty__title"><?php echo esc_html( '' !== $search || $user_id || '' !== $view ? 'سفارشی با این فیلتر نیست.' : 'هنوز سفارشی ثبت نشده.' ); ?></strong><span class="hdp-empty__text">سفارش‌های پیش‌خرید و پیش‌فاکتور مشتری‌ها اینجا می‌آیند.</span></div></div>
					<?php else : ?>
						<div class="hdp-table-wrap hpa-table-wrap">
							<table class="hpa-table">
								<thead>
									<tr>
										<th scope="col" class="hpa-cb"><input type="checkbox" data-hpa-all aria-label="انتخاب همه"></th>
										<th scope="col">سفارش</th>
										<th scope="col">مشتری</th>
										<th scope="col">اقلام</th>
										<th scope="col">مبلغ</th>
										<th scope="col">وضعیت</th>
										<th scope="col">گفتگو</th>
										<th scope="col"><span class="screen-reader-text">کار</span></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $rows as $row ) : ?>
										<?php self::row( $row, $items[ (int) $row->id ] ?? '' ); ?>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php
						$pages = (int) ceil( $total / self::PER_PAGE );
						if ( $pages > 1 ) {
							echo '<nav class="hpa-pages" aria-label="صفحه‌ها">' . wp_kses_post( (string) paginate_links( [ 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ] ) ) . '</nav>';
						}
						?>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * خلاصه اقلام هر سفارش («کش مو ۲ بسته، گیره ۱٫۵ کیلو +۲»).
	 *
	 * @param list<int> $ids سفارش‌ها.
	 * @return array<int, string>
	 */
	public static function item_summaries( array $ids ): array {
		global $wpdb;
		if ( ! $ids ) {
			return [];
		}
		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql   = "SELECT order_id, product_id, quantity, sale_unit FROM %i WHERE order_id IN ($in) ORDER BY id";
		$lines = [];
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, ...[ table( 'order_items' ), ...$ids ] ) ) as $item ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $in فقط %d است.
			$lines[ (int) $item->order_id ][] = get_the_title( (int) $item->product_id ) . ' ' . View::qty( (float) $item->quantity ) . ' ' . $item->sale_unit;
		}
		$out = [];
		foreach ( $lines as $id => $list ) {
			$out[ $id ] = implode( '، ', array_slice( $list, 0, 2 ) ) . ( count( $list ) > 2 ? ' +' . number_format_i18n( count( $list ) - 2 ) : '' );
		}
		return $out;
	}

	/**
	 * یک ردیف فهرست.
	 *
	 * @param object $row   سفارش (+ admin_unread گفتگو).
	 * @param string $items خلاصه اقلام.
	 */
	private static function row( object $row, string $items ): void {
		$id     = (int) $row->id;
		$user   = get_userdata( (int) $row->user_id );
		$mobile = (string) ( get_user_meta( (int) $row->user_id, 'mobile', true ) ?: get_user_meta( (int) $row->user_id, 'billing_phone', true ) );
		$todo   = in_array( $row->status, [ 'pending', 'receipt_uploaded' ], true );
		$unread = (int) ( $row->admin_unread ?? 0 );
		$open   = self::url( [ 'action' => 'view', 'order_id' => $id ] );
		$label  = match ( (string) $row->status ) {
			'pending'          => 'ارسال پیش‌فاکتور',
			'receipt_uploaded' => 'بررسی فیش',
			default            => 'باز کردن',
		};
		?>
		<tr class="<?php echo $todo ? 'is-todo' : ''; ?>">
			<td class="hpa-cb"><input type="checkbox" name="order_ids[]" value="<?php echo esc_attr( (string) $id ); ?>" form="hpa-orders-form" aria-label="<?php echo esc_attr( 'انتخاب سفارش ' . number_format_i18n( $id ) ); ?>"></td>
			<td data-label="سفارش"><a class="hpa-strong" href="<?php echo esc_url( $open ); ?>">#<?php echo esc_html( number_format_i18n( $id ) ); ?></a><span class="hpa-sub"><?php echo esc_html( type_label( (string) $row->type ) . ' · ' . show_date( (string) $row->created_at, 'j F' ) ); ?></span></td>
			<td data-label="مشتری"><a class="hpa-strong" href="<?php echo esc_url( self::url( [ 'user_id' => (int) $row->user_id ] ) ); ?>"><?php echo esc_html( $user ? $user->display_name : 'کاربر حذف‌شده' ); ?></a><?php if ( '' !== $mobile ) : ?><span class="hpa-sub"><bdi dir="ltr"><?php echo esc_html( $mobile ); ?></bdi></span><?php endif; ?></td>
			<td data-label="اقلام" class="hpa-items"><?php echo esc_html( '' !== $items ? $items : '—' ); ?></td>
			<td data-label="مبلغ" class="hpa-money"><?php echo esc_html( (float) $row->total_price > 0 ? View::money( (float) $row->total_price ) : '—' ); ?></td>
			<td data-label="وضعیت"><?php echo self::pill( (string) $row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill() امن است. ?></td>
			<td data-label="گفتگو"><?php echo $unread > 0 ? '<span class="hpa-dot">' . esc_html( number_format_i18n( $unread ) . ' پیام تازه' ) . '</span>' : '<span class="hpa-none" aria-label="بدون پیام تازه">—</span>'; ?></td>
			<td class="hpa-act"><a class="hdp-btn<?php echo $todo ? '' : ' hdp-btn--outline'; ?>" href="<?php echo esc_url( $open ); ?>" aria-label="<?php echo esc_attr( $label . ': سفارش ' . number_format_i18n( $id ) ); ?>"><?php echo esc_html( $label ); ?></a></td>
		</tr>
		<?php
	}

	/**
	 * مراحل سفارش از دید مدیر: [برچسب‌ها، شماره مرحله جاری (۱ تا ۴؛ ۵ = همه انجام)].
	 *
	 * @return array{0: list<string>, 1: int}
	 */
	public static function steps( string $status ): array {
		$labels = [ 'ثبت شد', 'پیش‌فاکتور', 'پرداخت و فیش', 'تکمیل و ارسال' ];
		return match ( $status ) {
			'pending'          => [ [ 'ثبت شد', 'پیش‌فاکتور — نوبت شما', 'پرداخت و فیش', 'تکمیل و ارسال' ], 2 ],
			'awaiting_payment' => [ [ 'ثبت شد', 'پیش‌فاکتور فرستاده شد', 'منتظر فیش مشتری', 'تکمیل و ارسال' ], 3 ],
			'receipt_uploaded' => [ [ 'ثبت شد', 'پیش‌فاکتور فرستاده شد', 'فیش رسید — بررسی شما', 'تکمیل و ارسال' ], 3 ],
			'completed'        => [ $labels, 5 ],
			default            => [ $labels, 0 ],
		};
	}

	/**
	 * صفحه یک سفارش.
	 *
	 * @param int $order_id شناسه.
	 */
	private static function render_order( int $order_id ): void {
		global $wpdb;
		$order = Orders::get( $order_id );
		echo '<div class="wrap hdp-ui hdp-page hodima-panel-admin">';
		if ( ! $order ) {
			echo '<h1>سفارش پیدا نشد.</h1><p><a class="hpa-link" href="' . esc_url( self::url() ) . '">همه سفارش‌ها</a></p></div>';
			return;
		}
		$wpdb->update( table( 'chats' ), [ 'admin_unread' => 0 ], [ 'order_id' => $order_id ], [ '%d' ], [ '%d' ] );
		clear_admin_badge();

		$status   = (string) $order->status;
		$user     = get_userdata( (int) $order->user_id );
		$name     = $user ? $user->display_name : 'کاربر حذف‌شده';
		$page_url     = self::url( [ 'action' => 'view', 'order_id' => $order_id ] );
		$receipts = Files::for_order( $order_id, Files::RECEIPT );
		$invoices = Files::for_order( $order_id, Files::INVOICE );
		$items    = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d ORDER BY id ASC', table( 'order_items' ), $order_id ) );
		$chat_id  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE order_id = %d', table( 'chats' ), $order_id ) );
		$messages = $chat_id ? (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE chat_id = %d ORDER BY id ASC', table( 'chat_messages' ), $chat_id ) ) : [];
		$nonce    = static function () use ( $order_id ): void {
			wp_nonce_field( 'hodima_panel_order_' . $order_id );
			echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order_id ) . '">';
		};
		[ $labels, $now ] = self::steps( $status );
		?>
			<header class="hdp-hero">
				<span class="hdp-hero__icon dashicons dashicons-media-text" aria-hidden="true"></span>
				<div>
					<span class="hdp-hero__badge">پنل مشتریان</span>
					<h1 class="hdp-hero__title"><?php echo esc_html( 'سفارش #' . number_format_i18n( $order_id ) . ' · ' . $name ); ?> <?php echo self::pill( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- امن. ?></h1>
					<p class="hdp-hero__desc"><?php echo esc_html( type_label( (string) $order->type ) . ' · ثبت ' . show_date( (string) $order->created_at, 'j F Y، H:i' ) . ( (float) $order->total_price > 0 ? ' · ' . View::money( (float) $order->total_price ) : '' ) ); ?></p>
				</div>
				<a class="hpa-back" href="<?php echo esc_url( self::url() ); ?>">همه سفارش‌ها</a>
			</header>
			<hr class="wp-header-end">
			<?php self::notice(); ?>
			<div class="hdp-page__body">
				<?php if ( $now > 0 ) : ?>
					<ol class="hpa-steps" aria-label="مراحل سفارش">
						<?php foreach ( $labels as $i => $label ) : ?>
							<?php $state = $i + 1 < $now ? 'done' : ( $i + 1 === $now ? 'now' : 'todo' ); ?>
							<li class="hpa-step is-<?php echo esc_attr( $state ); ?>"<?php echo 'now' === $state ? ' aria-current="step"' : ''; ?>><?php echo esc_html( $label ); ?><?php echo 'done' === $state ? '<span class="screen-reader-text"> (انجام شد)</span>' : ''; ?></li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>

				<div class="hpa-grid">
					<div class="hpa-col">
						<?php self::next_card( $order, $receipts, $invoices, $nonce, $page_url ); ?>

						<section class="hdp-card" id="hpa-chat" aria-labelledby="hpa-chat-title">
							<h2 class="hdp-card__title" id="hpa-chat-title">گفتگو با مشتری</h2>
							<div class="hpa-thread" tabindex="0" role="region" aria-label="پیام‌های گفتگو" data-hpa-scroll>
								<?php if ( ! $messages ) : ?>
									<p class="hpa-none">هنوز پیامی نیست؛ می‌توانید گفتگو را شروع کنید.</p>
								<?php endif; ?>
								<?php foreach ( $messages as $msg ) : ?>
									<?php
									$who = match ( (string) $msg->sender_type ) {
										'system' => 'سیستم',
										'admin'  => 'پشتیبانی',
										default  => $name,
									};
									?>
									<div class="hpa-msg hpa-msg--<?php echo esc_attr( (string) $msg->sender_type ); ?>"><p><?php echo nl2br( esc_html( (string) $msg->message ) ); ?></p><span class="hpa-msg__meta"><?php echo esc_html( $who . ' · ' . show_date( (string) $msg->created_at, 'j F، H:i' ) ); ?></span></div>
								<?php endforeach; ?>
							</div>
							<form method="post" action="<?php echo esc_url( $page_url ); ?>" class="hpa-compose">
								<?php $nonce(); ?>
								<input type="hidden" name="hp_action" value="chat">
								<label class="screen-reader-text" for="hpa-message">پیام به مشتری</label>
								<textarea id="hpa-message" name="message" class="hdp-input" rows="2" required placeholder="پیام به مشتری…"></textarea>
								<button type="submit" class="hdp-btn hdp-btn--outline">ارسال</button>
							</form>
							<p class="hdp-field__help"><?php echo esc_html( 'پیام با امضای «پاسخگو: ' . self::agent_name() . '» فرستاده می‌شود.' ); ?></p>
						</section>

						<section class="hdp-card" aria-labelledby="hpa-items-title">
							<h2 class="hdp-card__title" id="hpa-items-title">اقلام (قیمت لحظه ثبت)</h2>
							<?php if ( ! $items ) : ?>
								<p class="hpa-none">قلمی ثبت نشده.</p>
							<?php else : ?>
								<div class="hdp-table-wrap hpa-items-wrap">
									<table class="hpa-table hpa-items-table">
										<thead><tr><th scope="col">محصول</th><th scope="col">مقدار</th><th scope="col">قیمت هر عدد</th><th scope="col">جمع</th></tr></thead>
										<tbody>
											<?php foreach ( $items as $item ) : ?>
												<tr>
													<td data-label="محصول"><strong><?php echo esc_html( get_the_title( (int) $item->product_id ) ?: 'محصول #' . (int) $item->product_id ); ?></strong></td>
													<td data-label="مقدار"><?php echo esc_html( View::qty( (float) $item->quantity ) . ' ' . $item->sale_unit . ( (int) $item->unit_capacity > 1 ? ' (' . number_format_i18n( (int) $item->unit_capacity ) . ' عدد)' : '' ) ); ?></td>
													<td data-label="قیمت هر عدد"><?php echo esc_html( (float) $item->unit_price > 0 ? number_format_i18n( (float) $item->unit_price ) : '—' ); ?></td>
													<td data-label="جمع"><strong><?php echo esc_html( (float) $item->line_total > 0 ? number_format_i18n( (float) $item->line_total ) : '—' ); ?></strong></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
										<tfoot><tr><th scope="row" colspan="3">جمع کل</th><td><strong><?php echo esc_html( View::money( (float) array_sum( array_map( static fn( object $i ): float => (float) $i->line_total, $items ) ) ) ); ?></strong></td></tr></tfoot>
									</table>
								</div>
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $order->note ) ) : ?>
								<p class="hpa-note"><strong>یادداشت مشتری:</strong> <?php echo nl2br( esc_html( (string) $order->note ) ); ?></p>
							<?php endif; ?>
						</section>
					</div>

					<aside class="hpa-col hpa-aside">
						<?php self::customer_card( (int) $order->user_id, $user ); ?>

						<section class="hdp-card" aria-labelledby="hpa-files-title">
							<h2 class="hdp-card__title" id="hpa-files-title">پیش‌فاکتور و فیش‌ها</h2>
							<?php if ( ! $invoices && ! $receipts ) : ?>
								<p class="hpa-none">هنوز فایلی نیست.</p>
							<?php endif; ?>
							<ul class="hpa-files">
								<?php foreach ( [ [ $invoices, 'پیش‌فاکتور' ], [ $receipts, 'فیش' ] ] as [ $list, $kind ] ) : ?>
									<?php foreach ( $list as $i => $file ) : ?>
										<li class="hpa-file"><span class="hpa-file__type"><?php echo esc_html( strtoupper( (string) pathinfo( (string) $file->name, PATHINFO_EXTENSION ) ) ?: 'فایل' ); ?></span><span><?php echo esc_html( $kind . ' ' . number_format_i18n( $i + 1 ) ); ?><span class="hpa-sub"><?php echo esc_html( show_date( (string) $file->created_at, 'j F، H:i' ) ); ?></span></span><a class="hpa-link" href="<?php echo esc_url( $file->url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( 'نمایش' ); ?><span class="screen-reader-text"><?php echo esc_html( ' ' . $kind . ' ' . number_format_i18n( $i + 1 ) ); ?></span></a></li>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</ul>
							<?php if ( 'pending' !== $status && count( $invoices ) < Files::MAX_PER_KIND ) : ?>
								<details class="hdp-more hpa-more">
									<summary class="hdp-more__summary">پیش‌فاکتور دیگری بفرستید</summary>
									<div class="hdp-more__body"><?php self::invoice_form( $nonce, $page_url, 'hpa-invoice-2' ); ?></div>
								</details>
							<?php endif; ?>
						</section>

						<details class="hdp-more hpa-more hpa-card-more">
							<summary class="hdp-more__summary">تغییر دستی وضعیت</summary>
							<div class="hdp-more__body">
								<form method="post" action="<?php echo esc_url( $page_url ); ?>" class="hpa-status">
									<?php $nonce(); ?>
									<input type="hidden" name="hp_action" value="status">
									<label class="screen-reader-text" for="hpa-status">وضعیت تازه</label>
									<select id="hpa-status" name="order_status" class="hdp-input">
										<?php foreach ( order_statuses() as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>"<?php selected( $key, $status ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="submit" class="hdp-btn hdp-btn--outline">ذخیره وضعیت</button>
								</form>
								<p class="hdp-field__help">بدون پیام خودکار به مشتری؛ برای لغو یا اصلاح اشتباه.</p>
							</div>
						</details>
					</aside>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * فرم ارسال پیش‌فاکتور.
	 *
	 * @param callable $nonce فیلدهای nonce و شناسه.
	 * @param string   $page_url آدرس صفحه.
	 * @param string   $id    شناسه فیلد فایل.
	 */
	private static function invoice_form( callable $nonce, string $page_url, string $id ): void {
		?>
		<form method="post" action="<?php echo esc_url( $page_url ); ?>" enctype="multipart/form-data" class="hpa-upload">
			<?php $nonce(); ?>
			<input type="hidden" name="hp_action" value="invoice">
			<label class="hdp-field__label" for="<?php echo esc_attr( $id ); ?>">فایل پیش‌فاکتور</label>
			<input type="file" id="<?php echo esc_attr( $id ); ?>" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
			<span class="hdp-field__help">PDF یا عکس، تا ۵ مگابایت.</span>
			<button type="submit" class="hdp-btn">ارسال پیش‌فاکتور</button>
		</form>
		<?php
	}

	/**
	 * کارت «کار بعدی» بر اساس وضعیت.
	 *
	 * @param object       $order    سفارش.
	 * @param list<object> $receipts فیش‌ها.
	 * @param list<object> $invoices پیش‌فاکتورها.
	 * @param callable     $nonce    فیلدهای nonce.
	 * @param string       $page_url     آدرس صفحه.
	 */
	private static function next_card( object $order, array $receipts, array $invoices, callable $nonce, string $page_url ): void {
		$status = (string) $order->status;
		if ( 'pending' === $status ) {
			?>
			<section class="hdp-card hpa-next" aria-labelledby="hpa-next-title">
				<h2 class="hdp-card__title" id="hpa-next-title">کار بعدی: پیش‌فاکتور را بفرستید</h2>
				<p class="hdp-card__desc">با ارسال، سفارش «منتظر پرداخت» می‌شود و مشتری پیام خودکار می‌گیرد.</p>
				<?php self::invoice_form( $nonce, $page_url, 'hpa-invoice' ); ?>
			</section>
			<?php
			return;
		}
		if ( 'receipt_uploaded' === $status ) {
			$last = $receipts ? $receipts[ count( $receipts ) - 1 ] : null;
			?>
			<section class="hdp-card hpa-next" aria-labelledby="hpa-next-title">
				<h2 class="hdp-card__title" id="hpa-next-title">کار بعدی: فیش را بررسی کنید</h2>
				<p class="hdp-card__desc">اگر مبلغ به حساب رسیده، سفارش را تکمیل کنید؛ مشتری پیام خودکار می‌گیرد.</p>
				<?php if ( $last ) : ?>
					<p class="hpa-file hpa-file--single"><span class="hpa-file__type"><?php echo esc_html( strtoupper( (string) pathinfo( (string) $last->name, PATHINFO_EXTENSION ) ) ?: 'فایل' ); ?></span><span><?php echo esc_html( 'فیش ' . number_format_i18n( count( $receipts ) ) ); ?><span class="hpa-sub"><?php echo esc_html( show_date( (string) $last->created_at, 'j F، H:i' ) ); ?></span></span><a class="hpa-link" href="<?php echo esc_url( $last->url ); ?>" target="_blank" rel="noopener">نمایش فیش</a></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( $page_url ); ?>" class="hdp-action hpa-next__actions">
					<?php $nonce(); ?>
					<input type="hidden" name="hp_action" value="complete">
					<button type="submit" class="hdp-btn hpa-btn-ok">پرداخت تأیید شد — تکمیل سفارش</button>
					<a class="hpa-link" href="#hpa-message">فیش مشکل دارد؟ به مشتری پیام بدهید</a>
				</form>
			</section>
			<?php
			return;
		}
		$text = match ( $status ) {
			'awaiting_payment' => [ 'منتظر فیش مشتری', 'پیش‌فاکتور فرستاده شد (' . number_format_i18n( count( $invoices ) ) . ' فایل). وقتی مشتری فیش بفرستد، این سفارش در «کار شما» می‌آید.' ],
			'completed'        => [ 'این سفارش تکمیل شده است', 'کاری نمانده؛ فیش‌ها و گفتگو برای سابقه می‌مانند.' ],
			default            => [ 'این سفارش لغو شده است', 'برای برگرداندن، از «تغییر دستی وضعیت» استفاده کنید.' ],
		};
		?>
		<section class="hdp-card hpa-state" aria-labelledby="hpa-next-title">
			<h2 class="hdp-card__title" id="hpa-next-title"><?php echo esc_html( $text[0] ); ?></h2>
			<p class="hdp-card__desc"><?php echo esc_html( $text[1] ); ?></p>
		</section>
		<?php
	}

	/**
	 * کارت مشتری.
	 *
	 * @param int           $user_id شناسه.
	 * @param \WP_User|false $user   کاربر.
	 */
	private static function customer_card( int $user_id, \WP_User|false $user ): void {
		global $wpdb;
		$rows = [];
		if ( $user ) {
			$p      = Profile::fields( $user );
			$state  = Profile::states()[ $p['state'] ] ?? $p['state'];
			$mobile = (string) ( get_user_meta( $user_id, 'mobile', true ) ?: get_user_meta( $user_id, 'billing_phone', true ) );
			$rows   = [
				'نام'     => $user->display_name,
				'موبایل'  => $mobile,
				'شهر'     => trim( $p['city'] . ( '' !== $state && '' !== $p['city'] ? '، ' : '' ) . $state ),
				'آدرس'    => $p['address'],
				'کد پستی' => $p['postcode'],
			];
		}
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', table( 'orders' ), $user_id ) );
		?>
		<section class="hdp-card" aria-labelledby="hpa-user-title">
			<h2 class="hdp-card__title" id="hpa-user-title">مشتری</h2>
			<?php if ( ! $user ) : ?>
				<p class="hpa-none">حساب این مشتری حذف شده است.</p>
			<?php else : ?>
				<dl class="hpa-kv">
					<?php foreach ( $rows as $label => $value ) : ?>
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd><?php echo 'موبایل' === $label && '' !== $value ? '<bdi dir="ltr">' . esc_html( $value ) . '</bdi>' : esc_html( '' !== $value ? $value : 'ثبت نشده' ); ?></dd>
					<?php endforeach; ?>
				</dl>
				<a class="hpa-link" href="<?php echo esc_url( self::url( [ 'user_id' => $user_id ] ) ); ?>"><?php echo esc_html( 'همه سفارش‌های ' . $user->display_name . ' (' . number_format_i18n( $count ) . ')' ); ?></a>
			<?php endif; ?>
		</section>
		<?php
	}
}
