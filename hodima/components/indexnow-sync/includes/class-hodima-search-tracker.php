<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_Search_Tracker {

    public const BOTS = [
        'Bingbot'   => 'Bing',
        'YandexBot' => 'Yandex',
        'SeznamBot' => 'Seznam',
    ];

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'track_bot' ] );
        add_action( 'admin_post_hodima_export_search_bots', [ __CLASS__, 'export_logs' ] );
    }

    public static function track_bot(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
        if ( get_option( 'hodima_indexnow_status', 'enabled' ) === 'disabled' ) return;

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ( empty( $ua ) ) return;

        $detected = '';
        foreach ( self::BOTS as $sig => $name ) {
            if ( stripos( $ua, $sig ) !== false ) { $detected = $name; break; }
        }
        if ( ! $detected ) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ( empty( $ip ) ) return;

        $verify_enabled = get_option( 'hodima_verify_search_bots', '1' ) === '1';

        if ( ! $verify_enabled ) {
            self::record_visit( $detected );
            return;
        }

        $cache_key = 'hodima_bot_ip_' . md5( $ip . $detected );
        $status    = get_transient( $cache_key );

        if ( $status === false ) {
            $status = self::verify_bot_ip( $ip, $detected ) ? 'valid' : 'invalid';
            set_transient( $cache_key, $status, 30 * DAY_IN_SECONDS );
        }

        if ( $status === 'valid' ) {
            self::record_visit( $detected );
        }
    }

    private static function record_visit( string $bot_name ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}hodima_search_bot_logs (bot_name, visit_count, last_visit)
             VALUES (%s, 1, current_timestamp())
             ON DUPLICATE KEY UPDATE visit_count = visit_count + 1, last_visit = current_timestamp()",
            $bot_name
        ) );
    }

    private static function verify_bot_ip( string $ip, string $bot_name ): bool {
        $hostname = @gethostbyaddr( $ip );
        if ( $hostname === $ip || $hostname === false ) return false;

        $pattern_map = [
            'Bing'   => '/search\.msn\.com$/i',
            'Yandex' => '/yandex\.(com|ru|net)$/i',
            'Seznam' => '/seznam\.cz$/i',
        ];
        if ( isset( $pattern_map[ $bot_name ] ) && ! preg_match( $pattern_map[ $bot_name ], $hostname ) ) {
            return false;
        }

        $forward_ip = @gethostbyname( $hostname );
        return $forward_ip === $ip;
    }

    public static function get_stats( int $limit = 30 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT bot_name, visit_count, last_visit FROM {$wpdb->prefix}hodima_search_bot_logs ORDER BY last_visit DESC LIMIT %d", $limit
        ), ARRAY_A ) ?? [];
    }

    public static function prune_old_logs(): void {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}hodima_search_bot_logs WHERE last_visit < NOW() - INTERVAL 180 DAY" );
    }

    public static function export_logs(): void {
        Hodima_Core_Helpers::assert_export_access( 'hodima_export_search_bots' );
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT bot_name, visit_count, last_visit FROM {$wpdb->prefix}hodima_search_bot_logs ORDER BY visit_count DESC"
        , ARRAY_A ) ?? [];
        Hodima_Core_Helpers::export_csv(
            'hodima_export_search_bots',
            [ 'نام ربات', 'تعداد بازدید', 'آخرین بازدید' ],
            $rows,
            'hodima-search-bots'
        );
    }
}