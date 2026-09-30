<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_Admin {

    /** تعداد ردیف هر صفحه در جدول‌های صف و تاریخچه. */
    public const PER_PAGE = 20;

    /**
     * تب‌های صفحه (نامک => [عنوان، آیکون]).
     *
     * بازطراحی ۱.۱.۷: تب «همگام‌سازی بینگ» به «صف انتظار» و «تاریخچه
     * عملیات» تقسیم شد و تب «تنظیمات» که چهار موضوع بی‌ربط را قاطی داشت
     * سبک شد؛ هر تنظیم کنار بخشی آمد که رویش اثر دارد. نامک تب‌های قبلی
     * حفظ شده تا لینک‌ها و ریدایرکت‌های قدیمی کار کنند.
     */
    public static function tabs(): array {
        return [
            'dashboard'    => [ 'نمای کلی', 'dashicons-chart-bar' ],
            'queue'        => [ 'صف انتظار', 'dashicons-clock' ],
            'history'      => [ 'تاریخچه عملیات', 'dashicons-backup' ],
            'ai-shield'    => [ 'ربات‌های هوش مصنوعی', 'dashicons-shield' ],
            'search-bots'  => [ 'موتورهای جستجو', 'dashicons-visibility' ],
            'aeo-exporter' => [ 'خروجی‌های AEO', 'dashicons-media-code' ],
            'settings'     => [ 'تنظیمات', 'dashicons-admin-settings' ],
        ];
    }

    /** نامک تب معتبر؛ تب قدیمی «bing-sync» به «صف انتظار» می‌رود. */
    public static function current_tab( string $tab ): string {
        $tab = 'bing-sync' === $tab ? 'queue' : $tab;
        return array_key_exists( $tab, self::tabs() ) ? $tab : 'dashboard';
    }

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_actions' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function menu(): void {
        add_menu_page(
            'ایندکس جهانی',
            'ایندکس جهانی',
            'manage_options',
            'hodima-core',
            [ __CLASS__, 'render' ],
            'dashicons-shield',
            81
        );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'toplevel_page_hodima-core', 'post.php', 'post-new.php' ], true ) ) return;

        wp_enqueue_style( 'hodima-core-admin', HODIMA_CORE_URL . '/assets/admin.css', [], HODIMA_CORE_VERSION );

        if ( $hook === 'toplevel_page_hodima-core' ) {
            // نمودار با SVG وانیلا؛ بدون jQuery (قانون پروژه: جاوااسکریپت خالص)
            wp_enqueue_script( 'hodima-core-admin', HODIMA_CORE_URL . '/assets/admin.js', [], HODIMA_CORE_VERSION, true );
            wp_localize_script( 'hodima-core-admin', 'hodimaCoreObj', [ 'ajax_url' => admin_url( 'admin-ajax.php' ) ] );
        }
    }

    /** نوع‌های محتوای قابل انتخاب برای IndexNow (فرم و اعتبارسنجی ذخیره). */
    public static function content_types(): array {
        return [
            'post' => 'نوشته‌ها', 'category' => 'دسته‌بندی نوشته‌ها', 'page' => 'برگه‌ها',
            'product' => 'محصولات', 'product_cat' => 'دسته‌بندی محصولات', 'attachment' => 'رسانه‌ها',
        ];
    }

    public static function handle_actions(): void {
        if ( ! isset( $_POST['hodima_action'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! check_admin_referer( 'hodima_core_action' ) ) return;

        $action     = sanitize_key( wp_unslash( $_POST['hodima_action'] ) );
        $active_tab = self::current_tab( sanitize_key( wp_unslash( $_POST['active_tab'] ?? 'dashboard' ) ) );
        $redirect   = admin_url( "admin.php?page=hodima-core&tab={$active_tab}" );

        switch ( $action ) {

            case 'sync_all':
                Hodima_IndexNow::process_queue( true );
                wp_safe_redirect( $redirect . '&synced=1' );
                exit;

            case 'test_bing':
                $key = ! empty( $_POST['bing_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['bing_api_key'] ) ) : (string) get_option( 'hodima_bing_api_key' );
                $result = Hodima_IndexNow::send_to_bing( [ home_url() ], $key );
                wp_safe_redirect( $result['success']
                    ? $redirect . '&test_status=success'
                    : $redirect . '&test_status=failed&test_error=' . urlencode( $result['error_msg'] )
                );
                exit;

            case 'manual_sync':
                $url = esc_url_raw( wp_unslash( $_POST['manual_url'] ?? '' ) );
                // آدرس دامنه دیگر کل دسته ارسال را در بینگ رد می‌کرد (۴۲۲)
                if ( ! $url || ! Hodima_IndexNow::is_own_url( $url ) ) {
                    wp_safe_redirect( $redirect . '&msg=foreign_url' );
                    exit;
                }
                Hodima_IndexNow::add_to_queue( Hodima_Core_Helpers::clean_url( $url ), 'update' );
                wp_safe_redirect( $redirect . '&queued=1' );
                exit;

            /*
             * هر تب ذخیره خودش را دارد و *فقط* فیلدهای همان تب را می‌نویسد.
             * (فرم واحد قبلی همه را با هم ذخیره می‌کرد؛ اگر فرم‌ها جدا می‌شدند
             * و یک اکشن مشترک می‌ماند، ذخیره یک تب تیک همه ربات‌ها را برمی‌داشت.)
             */
            case 'save_settings': // تب «تنظیمات»: IndexNow
                /*
                 * کلید طبق پروتکل IndexNow: ۸ تا ۱۲۸ نویسه از a-z، A-Z، 0-9 و «-».
                 * کلید نامعتبر ذخیره نمی‌شود و کلید قبلی حفظ می‌شود.
                 */
                $new_key   = trim( sanitize_text_field( wp_unslash( $_POST['bing_api_key'] ?? '' ) ) );
                $key_error = '' !== $new_key && ! preg_match( '/^[A-Za-z0-9-]{8,128}$/', $new_key );
                if ( ! $key_error && '' !== $new_key ) {
                    update_option( 'hodima_bing_api_key', $new_key );
                }

                update_option( 'hodima_indexnow_status', isset( $_POST['module_enabled'] ) ? 'enabled' : 'disabled' );

                // فقط نوع‌هایی که در فرم هستند
                $allowed_content = isset( $_POST['allowed_content'] ) && is_array( $_POST['allowed_content'] )
                    ? array_values( array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST['allowed_content'] ) ), array_keys( self::content_types() ) ) ) : [];
                update_option( 'hodima_indexnow_allowed_content', $allowed_content );

                wp_safe_redirect( $redirect . ( $key_error ? '&msg=bad_key' : '&updated=1' ) );
                exit;

            case 'save_bots': // تب «ربات‌های هوش مصنوعی»
                $bot_settings = [];
                foreach ( Hodima_Bot_Shield::BOTS as $sig => $name ) {
                    $bot_settings[ $sig ] = isset( $_POST[ 'bot_' . $sig ] ) ? '1' : '0';
                }
                update_option( 'hodima_ai_bot_settings', $bot_settings );
                update_option( 'hodima_ai_rl_limit', min( 10000, max( 1, (int) ( $_POST['ai_rl_limit'] ?? 50 ) ) ) );
                wp_safe_redirect( $redirect . '&updated=1' );
                exit;

            case 'save_radar': // تب «موتورهای جستجو»
                update_option( 'hodima_verify_search_bots', isset( $_POST['verify_search_bots'] ) ? '1' : '0' );
                wp_safe_redirect( $redirect . '&updated=1' );
                exit;

            case 'save_aeo': // تب «خروجی‌های AEO»
                update_option( 'hodima_api_token', sanitize_text_field( wp_unslash( $_POST['api_token'] ?? '' ) ) );
                update_option( 'hodima_usd_exchange_rate', max( 1, (int) ( $_POST['usd_exchange_rate'] ?? 60000 ) ) );

                // نرخ ارز روی قیمت دلاری llms.txt و نسخه‌های .md و فید اثر دارد
                foreach ( [ 'fa', 'en' ] as $lang ) {
                    foreach ( [ 20, 500 ] as $limit ) {
                        delete_transient( "hodima_llms_txt_cache_{$lang}_{$limit}_siloed" );
                    }
                }
                if ( class_exists( 'Hodima_AEO_Generator' ) ) {
                    Hodima_AEO_Generator::bump_cache_generation();
                }

                wp_safe_redirect( $redirect . '&updated=1' );
                exit;
        }
    }

    /**
     * یک صفحه از جدول صف IndexNow.
     *
     * @param 'pending'|'done'|'synced'|'failed' $scope
     * @return array{rows:list<object>, total:int, pages:int, page:int}
     */
    public static function queue_page( string $scope, int $page ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'hodima_indexnow_queue';
        $where = match ( $scope ) {
            'pending' => "status = 'pending'",
            'synced'  => "status = 'synced'",
            'failed'  => "status = 'failed'",
            default   => "status != 'pending'",
        };

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
        $pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
        $page  = min( max( 1, $page ), $pages );

        // UNIX_TIMESTAMP: زمان ذخیره‌شده با ساعت خود دیتابیس است؛ برای نمایش به وقت سایت
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT url_path, action, status, error_message, retries, updated_at, UNIX_TIMESTAMP(updated_at) AS ts
             FROM {$table} WHERE {$where} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d",
            self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE
        ) ) ?: [];

        return [ 'rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page ];
    }

    public static function render(): void {
        global $wpdb;

        $active_tab = self::current_tab( sanitize_key( wp_unslash( $_GET['tab'] ?? 'dashboard' ) ) );

        $content_types    = self::content_types();
        $selected_content = (array) get_option( 'hodima_indexnow_allowed_content', [ 'post', 'category', 'page', 'product' ] );

        $bing_key        = (string) get_option( 'hodima_bing_api_key', '' );
        $module_status   = get_option( 'hodima_indexnow_status', 'enabled' );
        $is_locked       = (bool) get_transient( 'hodima_indexnow_lock' );
        $verify_bots     = get_option( 'hodima_verify_search_bots', '1' ) === '1';
        $ai_rl_limit     = (int) get_option( 'hodima_ai_rl_limit', 50 );
        $api_token       = (string) get_option( 'hodima_api_token', '' );
        $ai_bot_settings = (array) get_option( 'hodima_ai_bot_settings', [] );
        $usd_rate        = (int) get_option( 'hodima_usd_exchange_rate', 60000 );

        $queue_stats = $wpdb->get_row( "SELECT
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'synced'  THEN 1 END) as synced,
            COUNT(CASE WHEN status = 'failed'  THEN 1 END) as failed
            FROM {$wpdb->prefix}hodima_indexnow_queue" );

        $ai_summary = Hodima_Bot_Shield::get_dashboard_summary();
        $cache_diag = Hodima_Bot_Shield::litespeed_diagnostics();

        // داده فقط برای تب باز (قبلا همه کوئری‌های همه تب‌ها در هر بار باز شدن صفحه اجرا می‌شد)
        $ai_chart_7d = $search_bot_logs = $banned_ips = $bot_visits = [];
        $queue_view  = $history_view = null;
        $history_filter = 'done';

        switch ( $active_tab ) {
            case 'dashboard':
                $ai_chart_7d = Hodima_Bot_Shield::get_chart_data( '7d' );
                break;
            case 'queue':
                $queue_view = self::queue_page( 'pending', (int) ( $_GET['qpage'] ?? 1 ) );
                break;
            case 'history':
                $history_filter = sanitize_key( wp_unslash( $_GET['status'] ?? 'done' ) );
                $history_filter = in_array( $history_filter, [ 'synced', 'failed' ], true ) ? $history_filter : 'done';
                $history_view   = self::queue_page( $history_filter, (int) ( $_GET['hpage'] ?? 1 ) );
                break;
            case 'ai-shield':
                $bot_visits = Hodima_Bot_Shield::get_bot_visits();
                $banned_ips = Hodima_Bot_Shield::get_banned_ips();
                break;
            case 'search-bots':
                $search_bot_logs = Hodima_Search_Tracker::get_stats( 30 );
                break;
        }

        require HODIMA_CORE_DIR . '/views/admin-page.php';
    }
}