<?php
/**
 * Google Indexing — Admin controller
 * Path: components/google-indexing-api/admin/admin-ui.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_GI_Admin_UI {

	public function __construct() {

		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );

		foreach ( [ 'save_all_settings', 'test_google_api', 'send_bulk_urls', 'force_process_queue', 'process_selected_queue', 'delete_selected_queue', 'clear_log', 'manual_prune', 'clear_crawls', 'remove_key' ] as $action ) {
			add_action( "wp_ajax_hodima_{$action}", [ $this, "ajax_{$action}" ] );
		}

		add_action( 'wp_ajax_hodima_export_csv', [ $this, 'ajax_export_csv' ] );
	}

	public function menu(): void {
		add_menu_page( 'ایندکس گوگل', 'ایندکس گوگل', 'manage_options', 'hodima-google', [ $this, 'render' ], 'dashicons-update', 80 );
	}

	public function assets( string $hook ): void {

		if ( 'toplevel_page_hodima-google' !== $hook ) {
			return;
		}

		$uri = HODIMA_SEO_URL . '/components/google-indexing-api/admin';

		wp_enqueue_style( 'hodima-gi-css', $uri . '/admin-style.css', [], HODIMA_GI_VERSION );
		wp_enqueue_script( 'hodima-gi-js', $uri . '/admin-script.js', [], HODIMA_GI_VERSION, true );
		wp_localize_script( 'hodima-gi-js', 'hodimaObj', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'hodima_nonce' ),
		] );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include HODIMA_GI_DIR . '/admin/ui-template.php';
	}

	/* =================================================================
	 * متاباکس ویرایشگر
	 * ================================================================= */

	public function register_meta_box(): void {
		$post_types = (array) ( Hodima_GI_Helper::get_settings()['google_post_types'] ?? [] );
		if ( ! empty( $post_types ) ) {
			add_meta_box( 'hodima-gi-ping-box', 'سیگنال گوگل', [ $this, 'render_ping_box' ], $post_types, 'side', 'high' );
		}
	}

	public function render_ping_box( WP_Post $post ): void {
		wp_nonce_field( 'hodima_manual_ping_' . $post->ID, 'hodima_ping_nonce' );
		?>
		<label class="hodima-gi-pingbox">
			<input type="checkbox" name="hodima_manual_ping" value="1">
			<span>
				ارسال سیگنال به‌روزرسانی به گوگل پس از این ذخیره
				<small>
					فقط برای همین ذخیره اعمال می‌شود. انتشار جدید، تغییر پیوند،
					تغییر موجودی و کاهش قیمت همیشه خودکار ارسال می‌شوند.
					سهمیه باقی‌مانده امروز: <?php echo esc_html( number_format_i18n( Hodima_GI_Helper::quota_remaining() ) ); ?>
				</small>
			</span>
		</label>
		<?php
	}

	/* =================================================================
	 * AJAX
	 * ================================================================= */

	private function verify_access(): void {
		check_ajax_referer( 'hodima_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز' ], 403 );
		}
	}

	public function ajax_save_all_settings(): void {

		$this->verify_access();

		/*
		 * کلید حساب سرویس فقط وقتی جایگزین می‌شود که فایل جدیدی ارسال شده
		 * باشد، و قبل از ذخیره اعتبارسنجی می‌شود. نسخه قبلی هر JSON
		 * معتبری را بدون بررسی ساختار ذخیره می‌کرد؛ فایل اشتباه (مثلا
		 * OAuth client به جای service account) بی‌صدا ذخیره و بعد در
		 * ارسال‌ها شکست می‌خورد.
		 */
		$json = isset( $_POST['json_data'] ) ? trim( (string) wp_unslash( $_POST['json_data'] ) ) : '';

		if ( '' !== $json ) {
			$check = Hodima_GI_Helper::validate_service_account( $json );
			if ( ! $check['ok'] ) {
				wp_send_json_error( [ 'message' => $check['message'] ] );
			}
			update_option( HODIMA_GI_OPTION_JSON, $json, false );
			delete_transient( 'hodima_gi_token' );
		}

		$public_types = array_keys( get_post_types( [ 'public' => true ] ) );

		$settings                        = Hodima_GI_Helper::get_settings();
		$settings['google_post_types']   = isset( $_POST['google_post_types'] )
			? array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['google_post_types'] ) ), $public_types ) )
			: [];
		$settings['enable_google']       = ! empty( $_POST['enable_google'] ) ? 1 : 0;
		$settings['cf_token']            = sanitize_text_field( wp_unslash( (string) ( $_POST['cf_token'] ?? '' ) ) );
		$settings['cf_zone_id']          = sanitize_text_field( wp_unslash( (string) ( $_POST['cf_zone_id'] ?? '' ) ) );
		$settings['queue_debounce_time'] = isset( $_POST['debounce'] ) ? max( 1, absint( $_POST['debounce'] ) ) : 15;
		$settings['queue_penalty_time']  = isset( $_POST['penalty'] ) ? max( 1, absint( $_POST['penalty'] ) ) : 3;
		$settings['stale_content_days']  = isset( $_POST['stale_days'] ) ? max( 10, absint( $_POST['stale_days'] ) ) : 60;

		update_option( HODIMA_GI_OPTION_SETTINGS, $settings, false );

		wp_send_json_success( [ 'message' => 'پیکربندی ذخیره شد.' ] );
	}

	public function ajax_remove_key(): void {
		$this->verify_access();
		delete_option( HODIMA_GI_OPTION_JSON );
		delete_transient( 'hodima_gi_token' );
		wp_send_json_success( [ 'message' => 'کلید حساب سرویس حذف شد.' ] );
	}

	public function ajax_export_csv(): void {
		$this->verify_access();
		Hodima_Export_CSV::process_export( sanitize_key( wp_unslash( (string) ( $_GET['type'] ?? '' ) ) ) );
	}

	public function ajax_test_google_api(): void {
		$this->verify_access();
		$status = Hodima_Google_API::get_status( home_url( '/' ) );
		$status['success']
			? wp_send_json_success( [ 'message' => $status['message'] ] )
			: wp_send_json_error( [ 'message' => $status['message'] ] );
	}

	public function ajax_clear_log(): void {
		$this->verify_access();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Hodima_Crawler_DB_Schema::get_logs_table() );
		wp_send_json_success( [ 'message' => 'تاریخچه پاک شد.' ] );
	}

	public function ajax_clear_crawls(): void {
		$this->verify_access();
		Hodima_Crawler_DB_Queries::clear_crawls();
		wp_send_json_success( [ 'message' => 'گزارش خزش پاکسازی شد.' ] );
	}

	/** پیام خلاصه یکدست برای همه عملیات پردازش صف. */
	private function batch_message( array $r ): string {

		$parts = [];

		if ( $r['sent'] > 0 ) {
			$parts[] = sprintf( '%s لینک به گوگل ارسال شد.', number_format_i18n( $r['sent'] ) );
		}
		if ( $r['failed'] > 0 ) {
			$parts[] = sprintf( '%s لینک خطا داشت (جزئیات در تاریخچه).', number_format_i18n( $r['failed'] ) );
		}
		if ( ! empty( $r['quota'] ) ) {
			$parts[] = 'سهمیه امروز گوگل تمام شده؛ بقیه صف بعد از نیمه‌شب به وقت اقیانوس آرام ارسال می‌شود.';
		}
		if ( empty( $parts ) ) {
			$parts[] = 'چیزی برای ارسال نبود.';
		}

		$remaining = Hodima_GI_Queue::count();
		if ( $remaining > 0 ) {
			$parts[] = sprintf( '%s لینک در صف باقی مانده است.', number_format_i18n( $remaining ) );
		}

		return implode( ' ', $parts );
	}

	/**
	 * پردازش آیتم‌های انتخاب‌شده.
	 *
	 * باگ نسخه قبلی: آیتم را *حتی اگر ارسال شکست می‌خورد* از صف حذف
	 * می‌کرد و پیام «با موفقیت پردازش شد» می‌داد. حالا از همان مسیر صف
	 * استفاده می‌شود: موفق حذف، خطای موقت برای تلاش بعدی می‌ماند.
	 */
	public function ajax_process_selected_queue(): void {

		$this->verify_access();

		$ids = isset( $_POST['ids'] ) ? array_values( array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) ) : [];

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => 'موردی انتخاب نشده.' ] );
		}

		global $wpdb;

		if ( ! $wpdb->get_var( "SELECT GET_LOCK('hodima_gi_queue_mysql_lock', 2)" ) ) {
			wp_send_json_error( [ 'message' => 'صف در حال پردازش توسط سیستم است؛ چند لحظه دیگر تلاش کنید.' ] );
		}

		try {
			$table = Hodima_Crawler_DB_Schema::get_queue_table();
			$ids   = array_slice( $ids, 0, max( 0, Hodima_GI_Helper::quota_remaining() ) );
			$items = [];

			if ( ! empty( $ids ) ) {
				$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$in})", $ids ), ARRAY_A );
			}

			$result = Hodima_GI_Queue::process_items( (array) $items );

			if ( empty( $ids ) ) {
				$result['quota'] = true;
			}
		} finally {
			$wpdb->query( "SELECT RELEASE_LOCK('hodima_gi_queue_mysql_lock')" );
			Hodima_GI_Queue::schedule_next();
		}

		wp_send_json_success( [ 'message' => $this->batch_message( $result ) ] );
	}

	public function ajax_delete_selected_queue(): void {

		$this->verify_access();

		$ids = isset( $_POST['ids'] ) ? array_values( array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) ) : [];

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => 'موردی انتخاب نشده.' ] );
		}

		global $wpdb;
		$table = Hodima_Crawler_DB_Schema::get_queue_table();
		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$in})", $ids ) );
		Hodima_GI_Queue::schedule_next();

		wp_send_json_success( [ 'message' => 'موارد انتخابی از صف حذف شدند.' ] );
	}

	/**
	 * ارسال فوری صف.
	 * نسخه قبلی ۵۰ آیتم را بدون توجه به سهمیه ارسال می‌کرد؛ یک کلیک
	 * می‌توانست کل سهمیه روز را مصرف کند و بقیه ارسال‌های خودکار آن روز
	 * شکست بخورند.
	 */
	public function ajax_force_process_queue(): void {
		$this->verify_access();
		$result = Hodima_GI_Queue::process_batch( true );

		if ( ! empty( $result['locked'] ) ) {
			wp_send_json_error( [ 'message' => 'صف در حال پردازش است؛ چند لحظه دیگر تلاش کنید.' ] );
		}

		wp_send_json_success( [ 'message' => $this->batch_message( $result ) ] );
	}

	public function ajax_manual_prune(): void {
		$this->verify_access();
		$days    = (int) ( Hodima_GI_Helper::get_settings()['stale_content_days'] ?? 60 );
		$deleted = Hodima_Crawler_DB_Queries::prune_old_records( $days );
		wp_send_json_success( [ 'message' => sprintf( 'پاکسازی انجام شد. %s رکورد قدیمی حذف شد.', number_format_i18n( $deleted ) ) ] );
	}

	public function ajax_send_bulk_urls(): void {

		$this->verify_access();

		$raw  = (string) wp_unslash( $_POST['urls'] ?? '' );
		$urls = array_values( array_unique( array_filter(
			array_map( 'trim', (array) preg_split( '/\R/', $raw ) ),
			[ 'Hodima_GI_Helper', 'is_valid_url' ]
		) ) );

		if ( empty( $urls ) ) {
			wp_send_json_error( [ 'message' => 'هیچ آدرس معتبری وارد نشده.' ] );
		}

		$action = sanitize_key( wp_unslash( (string) ( $_POST['bulk_action_type'] ?? '' ) ) );
		$count  = 0;

		switch ( $action ) {
			case 'google_update':
			case 'google_delete':
				$type = ( 'google_update' === $action ) ? 'URL_UPDATED' : 'URL_DELETED';
				foreach ( array_slice( $urls, 0, 100 ) as $url ) {
					if ( Hodima_GI_Queue::push( Hodima_GI_Helper::clean_url( $url ), $type, 'manual' ) ) {
						$count++;
					}
				}
				break;

			case 'cf_purge':
				foreach ( array_slice( $urls, 0, 20 ) as $url ) {
					if ( Hodima_GI_Tools::cloudflare_purge( Hodima_GI_Helper::clean_url( $url ) ) ) {
						$count++;
					}
				}
				break;

			default:
				wp_send_json_error( [ 'message' => 'نوع عملیات نامعتبر است.' ] );
		}

		$message = sprintf( '%s آدرس پردازش شد.', number_format_i18n( $count ) );

		if ( $count > Hodima_GI_Helper::quota_remaining() && 'cf_purge' !== $action ) {
			$message .= sprintf( ' توجه: سهمیه باقی‌مانده امروز %s است؛ بقیه در روزهای بعد ارسال می‌شوند.', number_format_i18n( Hodima_GI_Helper::quota_remaining() ) );
		}

		wp_send_json_success( [ 'message' => $message ] );
	}
}
