<?php
/**
 * Hodima Core - ماژول ادغام‌شده سئوی هوش مصنوعی (AEO) + سینک بینگ (IndexNow)
 *
 * مسیر نصب: wp-content/plugins/hodima-seo/components/indexnow-sync/indexnow-sync.php
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'HODIMA_CORE_DIR', __DIR__ );
define( 'HODIMA_CORE_URL', HODIMA_SEO_URL . '/components/indexnow-sync' );
define( 'HODIMA_CORE_VERSION', '1.2.0' ); // 1.2.0: بازطراحی پیشخوان
define( 'HODIMA_CORE_DB_VERSION', '1.1.0' ); // 1.1.0: گزینه hodima_ban_until

// ---------------------------------------------------------------------
// بارگذاری فایل‌های کلاس
// ---------------------------------------------------------------------
$hodima_core_files = [
    'includes/class-hodima-helpers.php',
    'includes/class-hodima-schema.php',
    'includes/class-hodima-bot-shield.php',
    'includes/class-hodima-search-tracker.php',
    'includes/class-hodima-indexnow.php',
    'includes/aeo/class-hodima-aeo-init.php',
    'includes/class-hodima-admin.php',
];

foreach ( $hodima_core_files as $hodima_core_file ) {
    $hodima_core_path = HODIMA_CORE_DIR . '/' . $hodima_core_file;
    if ( file_exists( $hodima_core_path ) ) {
        require_once $hodima_core_path;
    }
}

final class Hodima_Core {

    public static function boot(): void {

        add_action( 'after_switch_theme', [ 'Hodima_Core_Schema', 'create_tables' ] );
        add_action( 'admin_init', [ __CLASS__, 'maybe_upgrade_db' ], 1 );
        add_action( 'after_setup_theme', [ __CLASS__, 'first_run_setup' ] );

        Hodima_Bot_Shield::init();
        Hodima_Search_Tracker::init();
        Hodima_IndexNow::init();

        if ( is_admin() ) {
            Hodima_Admin::init();
        }

        // هندلر اختصاصی ایجکس برای ابزار استخراج‌گر لینک‌های AEO
        add_action( 'wp_ajax_hodima_get_aeo_links', [ __CLASS__, 'ajax_get_aeo_links' ] );

        // هندلرهای پاکسازی صف و تاریخچه IndexNow
        add_action( 'admin_post_hodima_clear_in_history', [ __CLASS__, 'clear_in_history' ] );
        add_action( 'admin_post_hodima_clear_in_queue', [ __CLASS__, 'clear_in_queue' ] );

        add_action( 'hodima_core_hourly_sync', [ 'Hodima_IndexNow', 'process_queue' ] );
        add_action( 'hodima_core_daily_cleanup', [ __CLASS__, 'run_daily_cleanup' ] );

        if ( ! wp_next_scheduled( 'hodima_core_hourly_sync' ) ) {
            wp_schedule_event( time(), 'hourly', 'hodima_core_hourly_sync' );
        }
        if ( ! wp_next_scheduled( 'hodima_core_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'hodima_core_daily_cleanup' );
        }
    }

    public static function maybe_upgrade_db(): void {
        if ( get_option( 'hodima_core_db_version' ) !== HODIMA_CORE_DB_VERSION ) {
            Hodima_Core_Schema::create_tables();
            Hodima_Bot_Shield::sync_ban_until();
            update_option( 'hodima_core_db_version', HODIMA_CORE_DB_VERSION );
        }
    }

    public static function first_run_setup(): void {
        if ( empty( get_option( 'hodima_bing_api_key' ) ) ) {
            update_option( 'hodima_bing_api_key', md5( wp_generate_password( 32, true, true ) ) );
        }
        if ( get_option( 'hodima_indexnow_allowed_content' ) === false ) {
            update_option( 'hodima_indexnow_allowed_content', [ 'post', 'category', 'page', 'product' ] );
        }
        if ( get_option( 'hodima_core_setup_done' ) !== '1' ) {
            Hodima_Core_Schema::create_tables();
            update_option( 'hodima_core_setup_done', '1' );
        }
    }

    public static function run_daily_cleanup(): void {
        Hodima_IndexNow::clean_old_logs();
        Hodima_Bot_Shield::prune_old_logs();
        Hodima_Search_Tracker::prune_old_logs();
    }

    // =========================================================================
    // توابع پاکسازی دیتابیس (IndexNow)
    // =========================================================================
    public static function clear_in_history(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز' );
        check_admin_referer( 'hodima_clear_inh' );
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}hodima_indexnow_queue WHERE status != 'pending'" );
        wp_safe_redirect( add_query_arg( [ 'page' => 'hodima-core', 'tab' => 'history', 'msg' => 'cleared' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function clear_in_queue(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز' );
        check_admin_referer( 'hodima_clear_inq' );
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}hodima_indexnow_queue WHERE status = 'pending'" );
        wp_safe_redirect( add_query_arg( [ 'page' => 'hodima-core', 'tab' => 'queue', 'msg' => 'cleared' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // =========================================================================
    // پردازشگر هوشمند AEO
    // =========================================================================
    public static function ajax_get_aeo_links(): void {
        check_ajax_referer( 'hodima_aeo_export_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'دسترسی غیرمجاز' );
        }

        $mode      = sanitize_key( wp_unslash( $_POST['filter_mode'] ?? '24h' ) );
        
        $lang_fa   = ! empty( $_POST['lang_fa'] );
        $lang_en   = ! empty( $_POST['lang_en'] );
        $type_md   = ! empty( $_POST['type_md'] );
        $type_llms = ! empty( $_POST['type_llms'] );
        $type_feed = ! empty( $_POST['type_feed'] );

        $links = [];
        $home_url = untrailingslashit( home_url() );

        if ( $type_llms ) {
            if ( $lang_fa ) $links[] = $home_url . '/fa/llms.txt';
            if ( $lang_en ) $links[] = $home_url . '/en/llms.txt';
        }

        if ( $type_feed ) {
            if ( $lang_fa ) $links[] = $home_url . '/fa/ai-feed.json';
            if ( $lang_en ) $links[] = $home_url . '/en/ai-feed.json';
        }

        if ( $type_md ) {
            $args = [
                'post_type'      => [ 'product', 'post' ],
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'modified',
                'order'          => 'DESC'
            ];

            if ( $mode === '24h' ) {
                $args['date_query'] = [ [ 'column' => 'post_modified', 'after' => '24 hours ago' ] ];
            } elseif ( $mode === 'limit' ) {
                $limit = isset($_POST['limit_count']) ? absint($_POST['limit_count']) : 50;
                $args['posts_per_page'] = $limit ?: 50;
            } elseif ( $mode === 'date_range' ) {
                // فقط قالب YYYY-MM-DD (ورودی type=date)
                $date_from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_POST['date_from'] ?? '' ) ) ? (string) $_POST['date_from'] : '';
                $date_to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_POST['date_to'] ?? '' ) ) ? (string) $_POST['date_to'] : '';
                $date_query = [ 'column' => 'post_modified' ];
                if ( ! empty($date_from) ) $date_query['after'] = $date_from . ' 00:00:00';
                if ( ! empty($date_to) )   $date_query['before'] = $date_to . ' 23:59:59';
                $args['date_query'] = [ $date_query ];
            }

            $args['no_found_rows'] = true;
            $post_ids = array_map( 'intval', get_posts( $args ) );

            // is_noindex همه متای هر نوشته را می‌خواند؛ در حالت «کامل» (هزاران
            // نوشته) هر مورد یک کوئری جدا بود. یک کوئری برای همه:
            if ( ! empty( $post_ids ) ) {
                update_meta_cache( 'post', $post_ids );
            }

            foreach ( $post_ids as $id ) {
                if ( class_exists('Hodima_Core_Helpers') && method_exists('Hodima_Core_Helpers', 'is_noindex') && Hodima_Core_Helpers::is_noindex( $id, 'post' ) ) {
                    continue;
                }

                if ( get_post_type( $id ) === 'product' && function_exists('wc_get_product') ) {
                    $wc_product = wc_get_product( $id );
                    if ( $wc_product && ! $wc_product->is_in_stock() ) {
                        continue;
                    }
                }

                $permalink = get_permalink( $id );
                if ( ! $permalink ) continue;

                // همان سازنده آدرس بقیه ماژول (llms.txt، سایت‌مپ .md). جایگزینی
                // رشته‌ای قبلی با نصب در زیرپوشه یا رشته کوئری آدرس خراب می‌ساخت.
                $permalink = Hodima_Core_Helpers::clean_url( (string) $permalink );
                foreach ( array_keys( array_filter( [ 'fa' => $lang_fa, 'en' => $lang_en ] ) ) as $lang ) {
                    $md = Hodima_AEO_Generator::format_md_url( $permalink, $lang );
                    if ( '' !== $md ) $links[] = $md;
                }
            }
        }

        wp_send_json_success( [ 'links' => $links ] );
    }
}

Hodima_Core::boot();