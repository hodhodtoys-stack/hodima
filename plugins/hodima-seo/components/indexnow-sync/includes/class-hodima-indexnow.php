<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_IndexNow {

    public static function init(): void {
        add_action( 'parse_request', [ __CLASS__, 'serve_key_file' ] );

        add_action( 'save_post', [ __CLASS__, 'on_save_post' ], 10, 3 );
        add_action( 'wp_trash_post', [ __CLASS__, 'on_delete_post' ] );
        add_action( 'before_delete_post', [ __CLASS__, 'on_delete_post' ] );

        add_action( 'created_term', [ __CLASS__, 'on_term_change' ], 10, 3 );
        add_action( 'edited_term', [ __CLASS__, 'on_term_change' ], 10, 3 );
        add_action( 'delete_term', [ __CLASS__, 'on_term_delete' ], 10, 4 );

        add_action( 'add_attachment', [ __CLASS__, 'on_attachment_change' ] );
        add_action( 'edit_attachment', [ __CLASS__, 'on_attachment_change' ] );
        add_action( 'delete_attachment', [ __CLASS__, 'on_attachment_delete' ] );

        add_action( 'admin_post_hodima_export_queue', [ __CLASS__, 'export_queue' ] );
        add_action( 'admin_post_hodima_export_history', [ __CLASS__, 'export_history' ] );
    }

    public static function serve_key_file(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
        $key = get_option( 'hodima_bing_api_key' );
        if ( empty( $key ) ) return;

        // فقط مسیر دقیق /{key}.txt در ریشه سایت؛ قبلا هر آدرسی که این رشته را
        // در هر جایش (حتی در رشته کوئری) داشت، فایل کلید را برمی‌گرداند.
        $path      = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        $home_path = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
        if ( $path === "{$home_path}/{$key}.txt" ) {
            status_header( 200 );
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'X-Robots-Tag: noindex' );
            echo esc_html( $key );
            exit;
        }
    }

    public static function add_to_queue( string $url, string $action ): void {
        global $wpdb;
        $hash = md5( $url );
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}hodima_indexnow_queue (url_path, url_hash, action, status, error_message, retries)
             VALUES (%s, %s, %s, 'pending', NULL, 0)
             ON DUPLICATE KEY UPDATE status = 'pending', action = %s, error_message = NULL, retries = 0, updated_at = current_timestamp()",
            $url, $hash, $action, $action
        ) );
    }

    public static function get_pending( int $limit = 500, bool $force = false ): array {
        global $wpdb;
        $time_condition = $force
            ? "AND (status = 'pending' OR (status = 'failed' AND retries <= 3))"
            : "AND (
                (status = 'pending' AND updated_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)) OR
                (status = 'failed' AND retries = 1 AND updated_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)) OR
                (status = 'failed' AND retries = 2 AND updated_at <= DATE_SUB(NOW(), INTERVAL 4 HOUR)) OR
                (status = 'failed' AND retries = 3 AND updated_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR))
            )";

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT url_path, url_hash FROM {$wpdb->prefix}hodima_indexnow_queue WHERE 1=1 {$time_condition} ORDER BY updated_at ASC LIMIT %d",
            $limit
        ), ARRAY_A ) ?? [];
    }

    public static function update_status_by_hash( array $hashes, string $status, ?string $error = null ): void {
        if ( empty( $hashes ) ) return;
        global $wpdb;
        $placeholders = implode( ', ', array_fill( 0, count( $hashes ), '%s' ) );

        if ( $status === 'failed' ) {
            /*
             * ستون error_message از نوع varchar(255) است. پاسخ HTML بینگ (مثلا
             * صفحه خطای ۵۰۰) بلندتر است و در حالت strict مای‌اس‌کیوال کل UPDATE
             * رد می‌شد: وضعیت و شمارنده تلاش عوض نمی‌شد و همان آدرس‌ها هر ساعت
             * بی‌پایان دوباره ارسال می‌شدند.
             */
            $error  = mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', (string) $error ) ), 0, 250 );
            $sql    = "UPDATE {$wpdb->prefix}hodima_indexnow_queue SET status = 'failed', error_message = %s, retries = retries + 1 WHERE url_hash IN ({$placeholders})";
            $params = array_merge( [ $error ], $hashes );
        } else {
            $sql    = "UPDATE {$wpdb->prefix}hodima_indexnow_queue SET status = 'synced', error_message = NULL, retries = 0 WHERE url_hash IN ({$placeholders})";
            $params = $hashes;
        }
        $wpdb->query( $wpdb->prepare( $sql, ...$params ) );
    }

    public static function clean_old_logs(): void {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}hodima_indexnow_queue WHERE status = 'synced' AND updated_at < NOW() - INTERVAL 30 DAY" );
        // ناموفق‌هایی که هر ۳ تلاش را مصرف کرده‌اند دیگر هرگز ارسال نمی‌شوند؛ قبلا تا ابد می‌ماندند
        $wpdb->query( "DELETE FROM {$wpdb->prefix}hodima_indexnow_queue WHERE status = 'failed' AND retries > 3 AND updated_at < NOW() - INTERVAL 30 DAY" );
    }

    public static function send_to_bing( array $urls, string $api_key ): array {
        if ( empty( $api_key ) ) {
            return [ 'success' => false, 'rate_limit' => false, 'error_msg' => 'کلید API بینگ وارد نشده است.' ];
        }

        $response = wp_remote_post( 'https://www.bing.com/indexnow', [
            'body'    => wp_json_encode( [
                'host'    => parse_url( home_url(), PHP_URL_HOST ),
                'key'     => $api_key,
                'urlList' => $urls,
            ] ),
            'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'rate_limit' => false, 'error_msg' => 'خطای شبکه (بینگ): ' . $response->get_error_message() ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code === 200 || $code === 202 ) return [ 'success' => true, 'rate_limit' => false, 'error_msg' => null ];
        if ( $code === 429 ) return [ 'success' => false, 'rate_limit' => true, 'error_msg' => 'HTTP 429: محدودیت ترافیک سرور بینگ (Rate Limit)' ];
        if ( $code === 403 ) return [ 'success' => false, 'rate_limit' => false, 'error_msg' => 'HTTP 403: کلید شما توسط بینگ تایید نشد.' ];
        if ( $code === 422 ) return [ 'success' => false, 'rate_limit' => false, 'error_msg' => 'HTTP 422: دامنه شما با کلید همخوانی ندارد.' ];

        return [ 'success' => false, 'rate_limit' => false, 'error_msg' => "HTTP {$code} (بینگ): " . mb_substr( trim( wp_strip_all_tags( (string) wp_remote_retrieve_body( $response ) ) ), 0, 200 ) ];
    }

    public static function process_queue( bool $force = false ): void {
        if ( get_option( 'hodima_indexnow_status', 'enabled' ) === 'disabled' ) return;
        if ( get_transient( 'hodima_indexnow_lock' ) ) return;

        set_transient( 'hodima_indexnow_lock', true, 2 * MINUTE_IN_SECONDS );

        $api_key = get_option( 'hodima_bing_api_key' );
        if ( empty( $api_key ) ) { delete_transient( 'hodima_indexnow_lock' ); return; }

        $items = self::get_pending( 500, $force );
        if ( empty( $items ) ) { delete_transient( 'hodima_indexnow_lock' ); return; }

        foreach ( array_chunk( $items, 50 ) as $chunk ) {
            $urls   = array_column( $chunk, 'url_path' );
            $hashes = array_column( $chunk, 'url_hash' );
            $result = self::send_to_bing( $urls, $api_key );

            if ( $result['success'] ) {
                self::update_status_by_hash( $hashes, 'synced' );
            } else {
                self::update_status_by_hash( $hashes, 'failed', $result['error_msg'] );
                if ( $result['rate_limit'] ) {
                    set_transient( 'hodima_indexnow_lock', true, HOUR_IN_SECONDS );
                    return;
                }
            }
            usleep( 200000 );
        }

        delete_transient( 'hodima_indexnow_lock' );
    }

    private static function content_type_allowed( string $type ): bool {
        // (array): گزینه خراب (رشته) در PHP 8 با in_array خطای کشنده می‌داد — روی هر ذخیره نوشته
        return in_array( $type, (array) get_option( 'hodima_indexnow_allowed_content', [] ), true );
    }

    /**
     * آیا آدرس متعلق به همین سایت است؟
     *
     * IndexNow کل دسته ۵۰تایی را رد می‌کند (HTTP 422) اگر حتی یک آدرس از
     * دامنه دیگری باشد؛ یک لینک دستی اشتباه، ۴۹ آدرس سالم را هم ناموفق می‌کرد.
     */
    public static function is_own_url( string $url ): bool {
        $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        return '' !== $host && strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) === $host;
    }

    public static function on_save_post( int $post_id, WP_Post $post, bool $update ): void {
        if ( get_option( 'hodima_indexnow_status', 'enabled' ) === 'disabled' ) return;
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        if ( $post->post_status !== 'publish' ) return;
        if ( ! self::content_type_allowed( $post->post_type ) ) return;

        $url = get_permalink( $post_id );
        if ( empty( $url ) ) return;
        $url = Hodima_Core_Helpers::clean_url( $url );

        $action = Hodima_Core_Helpers::is_noindex( $post_id, 'post' ) ? 'del' : ( $update ? 'update' : 'new' );
        self::add_to_queue( $url, $action );
    }

    public static function on_delete_post( int $post_id ): void {
        if ( get_option( 'hodima_indexnow_status', 'enabled' ) === 'disabled' ) return;
        if ( get_post_status( $post_id ) !== 'publish' ) return;
        if ( ! self::content_type_allowed( (string) get_post_type( $post_id ) ) ) return;

        $url = get_permalink( $post_id );
        if ( ! empty( $url ) ) self::add_to_queue( Hodima_Core_Helpers::clean_url( $url ), 'del' );
    }

    public static function on_term_change( int $term_id, int $tt_id, string $taxonomy ): void {
        if ( get_option( 'hodima_indexnow_status', 'enabled' ) === 'disabled' ) return;
        if ( ! self::content_type_allowed( $taxonomy ) ) return;

        $url = get_term_link( $term_id, $taxonomy );
        if ( is_wp_error( $url ) || empty( $url ) ) return;
        $url = Hodima_Core_Helpers::clean_url( $url );

        $action = Hodima_Core_Helpers::is_noindex( $term_id, 'term' ) ? 'del' : 'update';
        self::add_to_queue( $url, $action );
    }

    public static function on_term_delete( int $term_id, int $tt_id, string $taxonomy, $deleted_term ): void {
        if ( get_option( 'hodima_indexnow_status', 'enabled' ) === 'disabled' ) return;
        if ( ! self::content_type_allowed( $taxonomy ) ) return;

        $url = get_term_link( $deleted_term );
        if ( ! is_wp_error( $url ) && ! empty( $url ) ) self::add_to_queue( Hodima_Core_Helpers::clean_url( $url ), 'del' );
    }

    public static function on_attachment_change( int $attachment_id ): void {
        if ( get_option( 'hodima_indexnow_status', 'enabled' ) === 'disabled' ) return;
        if ( ! self::content_type_allowed( 'attachment' ) ) return;

        $url = wp_get_attachment_url( $attachment_id );
        if ( ! empty( $url ) ) self::add_to_queue( Hodima_Core_Helpers::clean_url( $url ), 'update' );
    }

    public static function on_attachment_delete( int $attachment_id ): void {
        if ( get_option( 'hodima_indexnow_status', 'enabled' ) === 'disabled' ) return;
        if ( ! self::content_type_allowed( 'attachment' ) ) return;

        $url = wp_get_attachment_url( $attachment_id );
        if ( ! empty( $url ) ) self::add_to_queue( Hodima_Core_Helpers::clean_url( $url ), 'del' );
    }

    public static function export_queue(): void {
        Hodima_Core_Helpers::assert_export_access( 'hodima_export_queue' );
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT url_path, action, status, updated_at FROM {$wpdb->prefix}hodima_indexnow_queue WHERE status = 'pending' ORDER BY updated_at DESC"
        , ARRAY_A ) ?? [];
        Hodima_Core_Helpers::export_csv( 'hodima_export_queue', [ 'URL', 'عملیات', 'وضعیت', 'تاریخ' ], $rows, 'hodima-bing-queue' );
    }

    public static function export_history(): void {
        Hodima_Core_Helpers::assert_export_access( 'hodima_export_history' );
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT url_path, action, status, error_message, updated_at FROM {$wpdb->prefix}hodima_indexnow_queue WHERE status != 'pending' ORDER BY updated_at DESC LIMIT 5000"
        , ARRAY_A ) ?? [];
        Hodima_Core_Helpers::export_csv( 'hodima_export_history', [ 'URL', 'عملیات', 'وضعیت', 'پیام خطا', 'تاریخ' ], $rows, 'hodima-bing-history' );
    }
}