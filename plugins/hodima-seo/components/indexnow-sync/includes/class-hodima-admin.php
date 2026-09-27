<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_Admin {

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
            // Chart.js حذف شد؛ نمودار حالا با SVG وانیلا در admin.js رسم می‌شود.
            wp_enqueue_script( 'hodima-core-admin', HODIMA_CORE_URL . '/assets/admin.js', [ 'jquery' ], HODIMA_CORE_VERSION, true );
            wp_localize_script( 'hodima-core-admin', 'hodimaCoreObj', [ 'ajax_url' => admin_url( 'admin-ajax.php' ) ] );
        }
    }

    public static function handle_actions(): void {
        if ( ! isset( $_POST['hodima_action'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! check_admin_referer( 'hodima_core_action' ) ) return;

        $action     = sanitize_text_field( $_POST['hodima_action'] );
        $active_tab = sanitize_text_field( $_POST['active_tab'] ?? 'dashboard' );
        $redirect   = admin_url( "admin.php?page=hodima-core&tab={$active_tab}" );

        switch ( $action ) {

            case 'sync_all':
                Hodima_IndexNow::process_queue( true );
                wp_safe_redirect( $redirect . '&synced=1' );
                exit;

            case 'test_bing':
                $key = ! empty( $_POST['bing_api_key'] ) ? sanitize_text_field( $_POST['bing_api_key'] ) : get_option( 'hodima_bing_api_key' );
                $result = Hodima_IndexNow::send_to_bing( [ home_url() ], $key );
                wp_safe_redirect( $result['success']
                    ? $redirect . '&test_status=success'
                    : $redirect . '&test_status=failed&test_error=' . urlencode( $result['error_msg'] )
                );
                exit;

            case 'manual_sync':
                $url = esc_url_raw( $_POST['manual_url'] ?? '' );
                if ( $url ) Hodima_IndexNow::add_to_queue( Hodima_Core_Helpers::clean_url( $url ), 'update' );
                wp_safe_redirect( $redirect . '&queued=1' );
                exit;

            case 'save_settings':
                update_option( 'hodima_bing_api_key', sanitize_text_field( $_POST['bing_api_key'] ?? '' ) );
                update_option( 'hodima_indexnow_status', sanitize_text_field( $_POST['module_status'] ?? 'enabled' ) );
                update_option( 'hodima_verify_search_bots', isset( $_POST['verify_search_bots'] ) ? '1' : '0' );
                update_option( 'hodima_ai_rl_limit', max( 1, (int) ( $_POST['ai_rl_limit'] ?? 50 ) ) );
                update_option( 'hodima_api_token', sanitize_text_field( $_POST['api_token'] ?? '' ) );
                
                // ذخیره نرخ دلار سراسری
                update_option( 'hodima_usd_exchange_rate', max( 1, (int) ( $_POST['usd_exchange_rate'] ?? 60000 ) ) );

                $allowed_content = isset( $_POST['allowed_content'] ) && is_array( $_POST['allowed_content'] )
                    ? array_map( 'sanitize_text_field', $_POST['allowed_content'] ) : [];
                update_option( 'hodima_indexnow_allowed_content', $allowed_content );

                $bot_settings = [];
                foreach ( Hodima_Bot_Shield::BOTS as $sig => $name ) {
                    $bot_settings[ $sig ] = isset( $_POST['bot_' . $sig] ) ? '1' : '0';
                }
                update_option( 'hodima_ai_bot_settings', $bot_settings );

                // 1. پاک کردن کش نقشه‌های دوزبانه (llms.txt)
                delete_transient( 'hodima_llms_txt_cache_fa_20_siloed' );
                delete_transient( 'hodima_llms_txt_cache_en_20_siloed' );
                delete_transient( 'hodima_llms_txt_cache_fa_500_siloed' );
                delete_transient( 'hodima_llms_txt_cache_en_500_siloed' );

                // 2. پاک کردن گروهیِ کش تمام فایل‌های دوزبانه (.md) برای اعمال فوری نرخ جدید ارز
                global $wpdb;
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hodima_md_%' OR option_name LIKE '_transient_timeout_hodima_md_%'" );

                wp_safe_redirect( $redirect . '&updated=1' );
                exit;
        }
    }

    public static function render(): void {
        global $wpdb;

        $active_tab = sanitize_text_field( $_GET['tab'] ?? 'dashboard' );

        $content_types = [
            'post' => 'نوشته‌ها', 'category' => 'دسته‌بندی نوشته‌ها', 'page' => 'برگه‌ها',
            'product' => 'محصولات', 'product_cat' => 'دسته‌بندی محصولات', 'attachment' => 'رسانه‌ها',
        ];
        $selected_content = get_option( 'hodima_indexnow_allowed_content', [ 'post', 'category', 'page', 'product' ] );

        $bing_key       = get_option( 'hodima_bing_api_key', '' );
        $module_status  = get_option( 'hodima_indexnow_status', 'enabled' );
        $is_locked      = (bool) get_transient( 'hodima_indexnow_lock' );
        $verify_bots    = get_option( 'hodima_verify_search_bots', '1' ) === '1';
        $ai_rl_limit    = (int) get_option( 'hodima_ai_rl_limit', 50 );
        $api_token      = get_option( 'hodima_api_token', '' );
        $ai_bot_settings = get_option( 'hodima_ai_bot_settings', [] );
        
        // فراخوانی نرخ فعلی دلار برای نمایش در فرم
        $usd_rate       = (int) get_option( 'hodima_usd_exchange_rate', 60000 );

        $search_bot_logs = Hodima_Search_Tracker::get_stats( 30 );

        $queue_pending = $wpdb->get_results( "SELECT url_path, action, status, updated_at FROM {$wpdb->prefix}hodima_indexnow_queue WHERE status = 'pending' ORDER BY updated_at DESC LIMIT 100" );
        $queue_history = $wpdb->get_results( "SELECT url_path, action, status, error_message, updated_at FROM {$wpdb->prefix}hodima_indexnow_queue WHERE status != 'pending' ORDER BY updated_at DESC LIMIT 100" );
        $queue_stats   = $wpdb->get_row( "SELECT
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'synced'  THEN 1 END) as synced,
            COUNT(CASE WHEN status = 'failed'  THEN 1 END) as failed
            FROM {$wpdb->prefix}hodima_indexnow_queue" );

        $ai_summary   = Hodima_Bot_Shield::get_dashboard_summary();
        $ai_stats     = Hodima_Bot_Shield::get_stats();
        $ai_chart_7d  = Hodima_Bot_Shield::get_chart_data( '7d' );
        $banned_ips   = Hodima_Bot_Shield::get_banned_ips();

        require HODIMA_CORE_DIR . '/views/admin-page.php';
    }
}