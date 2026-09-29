<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_Bot_Shield {

    public const BOTS = [
        'GPTBot'            => 'OpenAI (آموزش و دیتا)',
        'ChatGPT-User'      => 'ChatGPT (سرچ زنده)',
        'OAI-SearchBot'     => 'SearchGPT',
        'ClaudeBot'         => 'Anthropic Claude',
        'Claude-User'       => 'Claude (درخواست کاربر)',
        'Claude-SearchBot'  => 'Claude (جستجو)',
        'Claude-Web'        => 'Claude (نام قدیمی)',
        'Google-Extended'   => 'Google Gemini (آموزش)',
        'PerplexityBot'     => 'Perplexity AI',
        'Perplexity-User'   => 'Perplexity (درخواست کاربر)',
        'Applebot-Extended' => 'Apple Intelligence',
        'Amazonbot'         => 'Amazon AI',
        'FacebookBot'       => 'Meta Llama',
        'Bytespider'        => 'ByteDance (TikTok AI)',
        'CCBot'             => 'CommonCrawl (دیتاست)',
        'Cohere-ai'         => 'Cohere AI',
        'YouBot'            => 'You.com AI',
        'Diffbot'           => 'Diffbot AI',
        'PetalBot'          => 'Petal Search AI',
    ];

    /** گزینه autoload: زمان پایان آخرین مسدودیت (برای رد کردن کوئری در هر بازدید). */
    private const BAN_UNTIL_OPTION = 'hodima_ban_until';

    /**
     * این درخواست قبلا بررسی و ثبت شده؟
     *
     * باگ قبلی: scan() روی init هر بازدید ربات را ثبت می‌کرد و برای مسیرهای
     * AEO (llms.txt، .md، ai-feed) مسیریاب در template_redirect دوباره همان
     * بررسی را انجام می‌داد — هر بازدید دو ردیف لاگ و دو واحد از سقف نرخ.
     */
    private static bool $handled = false;

    public static function already_handled(): bool {
        return self::$handled;
    }

    public static function mark_handled(): void {
        self::$handled = true;
    }

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'scan' ], 1 );

        add_action( 'admin_post_hodima_clear_ai_logs', [ __CLASS__, 'clear_logs' ] );
        add_action( 'admin_post_hodima_export_ai_logs', [ __CLASS__, 'export_logs' ] );
        add_action( 'admin_post_hodima_unban_ip', [ __CLASS__, 'unban_ip' ] );
    }

    /**
     * مدت مسدودیت IP (ثانیه).
     *
     * مسدودیت قبلا دائمی بود. در ایران اپراتورهای همراه با CGNAT هزاران
     * کاربر را پشت یک IP عمومی می‌برند؛ بن دائمی یک IP یعنی بستن سایت
     * روی همه آن مشتری‌ها برای همیشه. حالا مسدودیت خودبه‌خود منقضی می‌شود.
     */
    public static function ban_duration(): int {
        return max( 60, (int) apply_filters( 'hodima_bot_shield_ban_seconds', HOUR_IN_SECONDS ) );
    }

    public static function ban_ip( string $ip, string $reason = '' ): void {
        global $wpdb;
        // created_at با هر بن دوباره تازه می‌شود تا شروع مهلت از آخرین تخلف باشد
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}hodima_banned_ips (ip_address, reason) VALUES (%s, %s)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), created_at = CURRENT_TIMESTAMP",
            $ip, mb_substr( $reason, 0, 250 )
        ) );
        update_option( self::BAN_UNTIL_OPTION, time() + self::ban_duration(), true );
    }

    /**
     * آیا اصلا مسدودیت فعالی وجود دارد؟ (بدون کوئری؛ گزینه autoload است)
     *
     * قبلا is_ip_banned() در *هر* بازدید صفحه (نه فقط ربات‌ها) یک کوئری
     * دیتابیس اجرا می‌کرد، حتی وقتی جدول خالی بود.
     */
    /** مقدار گزینه را از روی جدول دوباره می‌سازد (ارتقا، آزادسازی، هرس). */
    public static function sync_ban_until(): void {
        global $wpdb;
        $last = $wpdb->get_var( "SELECT MAX(created_at) FROM {$wpdb->prefix}hodima_banned_ips" );
        // created_at با CURRENT_TIMESTAMP خود دیتابیس ثبت می‌شود؛ مقایسه هم با همان ساعت
        $left = $last ? (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(%s, INTERVAL %d SECOND))', $last, self::ban_duration()
        ) ) : -1;
        update_option( self::BAN_UNTIL_OPTION, $left > 0 ? time() + $left : 0, true );
    }

    private static function has_active_bans(): bool {
        return (int) get_option( self::BAN_UNTIL_OPTION, PHP_INT_MAX ) >= time();
    }

    /**
     * مسدودسازی موقت IP و — فقط اگر صراحتا فعال شده باشد — ارسال به فایروال Cloudflare.
     */
    public static function penalize( string $ip, string $reason ): void {
        if ( '' === $ip ) {
            return;
        }
        self::ban_ip( $ip, $reason );
        self::block_ip_cloudflare( $ip );
    }

    public static function unban_ip(): void {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'hodima_unban_ip' ) ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }
        global $wpdb;
        $ip = sanitize_text_field( wp_unslash( $_GET['ip'] ?? '' ) );
        if ( $ip ) {
            $wpdb->delete( $wpdb->prefix . 'hodima_banned_ips', [ 'ip_address' => $ip ] );
            self::sync_ban_until();
        }
        wp_safe_redirect( admin_url( 'admin.php?page=hodima-core&tab=ai-shield&msg=unbanned' ) );
        exit;
    }

    public static function is_ip_banned( string $ip ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}hodima_banned_ips
             WHERE ip_address = %s AND created_at >= DATE_SUB(NOW(), INTERVAL %d SECOND) LIMIT 1",
            $ip, self::ban_duration()
        ) );
    }

    /** فقط مسدودیت‌های فعال (منقضی‌نشده) */
    public static function get_banned_ips( int $limit = 100 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT ip_address, reason, created_at FROM {$wpdb->prefix}hodima_banned_ips
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d SECOND)
             ORDER BY created_at DESC LIMIT %d",
            self::ban_duration(), $limit
        ), ARRAY_A ) ?? [];
    }

    /**
     * قانون مسدودسازی در Cloudflare.
     *
     * قوانین Cloudflare خودبه‌خود منقضی نمی‌شوند؛ پس این کار به‌صورت
     * پیش‌فرض خاموش است و فقط با فیلتر زیر روشن می‌شود:
     *     add_filter( 'hodima_bot_shield_cloudflare_block', '__return_true' );
     * توکن و Zone ID ترجیحا در wp-config.php تعریف شوند:
     *     define( 'HODIMA_CF_API_TOKEN', '...' ); define( 'HODIMA_CF_ZONE_ID', '...' );
     */
    public static function block_ip_cloudflare( string $ip ): void {
        if ( ! apply_filters( 'hodima_bot_shield_cloudflare_block', false, $ip ) ) return;

        // همان اعتبارنامه‌ای که ماژول Google Indexing برای پاکسازی کش استفاده می‌کند
        if ( class_exists( 'Hodima_GI_Helper' ) ) {
            $cf    = Hodima_GI_Helper::cloudflare_credentials();
            $token = $cf['token'];
            $zone  = $cf['zone'];
        } else {
            $token = defined( 'HODIMA_CF_API_TOKEN' ) ? (string) HODIMA_CF_API_TOKEN : (string) get_option( 'hodima_cf_token' );
            $zone  = defined( 'HODIMA_CF_ZONE_ID' ) ? (string) HODIMA_CF_ZONE_ID : (string) get_option( 'hodima_cf_zone_id' );
        }
        if ( '' === $token || '' === $zone || ! preg_match( '/^[a-f0-9]{32}$/i', $zone ) ) return;

        wp_remote_post( "https://api.cloudflare.com/client/v4/zones/{$zone}/firewall/access_rules/rules", [
            'headers'  => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
            'body'     => wp_json_encode( [
                'mode'          => 'block',
                'configuration' => [ 'target' => 'ip', 'value' => $ip ],
                'notes'         => 'Blocked by Hodima AEO Shield',
            ] ),
            'blocking' => false,
        ] );
    }

    /**
     * محدودیت نرخ به ازای «IP + نام ربات».
     *
     * کلید قبلا فقط IP بود و عبور از سقف به بن کل IP می‌انجامید؛ با
     * جعل User-Agent می‌شد IP مشترک مشتریان واقعی را بست. حالا فقط
     * درخواست‌های همان ربات از همان IP محدود می‌شوند.
     */
    public static function check_rate_limit( string $ip, int $limit, string $bot = '' ): bool {
        $key     = 'hodima_rl_' . md5( $ip . '|' . $bot );
        $current = get_transient( $key );

        if ( $current === false ) {
            set_transient( $key, 1, MINUTE_IN_SECONDS );
            return true;
        }
        if ( (int) $current >= $limit ) return false;

        set_transient( $key, (int) $current + 1, MINUTE_IN_SECONDS );
        return true;
    }

    public static function scan(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = Hodima_Core_Helpers::get_client_ip();
        if ( ! $ua || ! $ip ) return;

        // مقدار پیش‌فرض PHP_INT_MAX: تا اولین بن بعد از به‌روزرسانی، رفتار قبلی (کوئری) حفظ می‌شود
        if ( self::has_active_bans() && self::is_ip_banned( $ip ) ) {
            header( 'HTTP/1.1 403 Forbidden' );
            header( 'Retry-After: ' . self::ban_duration() );
            exit( 'دسترسی موقتا مسدود شده است. لطفا بعدا تلاش کنید.' );
        }

        $settings  = get_option( 'hodima_ai_bot_settings', [] );
        $rl_limit  = (int) get_option( 'hodima_ai_rl_limit', 50 );

        foreach ( self::BOTS as $sig => $name ) {
            if ( stripos( $ua, $sig ) === false ) continue;

            if ( isset( $settings[ $sig ] ) && $settings[ $sig ] === '0' ) {
                header( 'HTTP/1.1 403 Forbidden' );
                exit( 'دسترسی این ربات هوش مصنوعی طبق سیاست سایت مسدود شده است.' );
            }

            // عبور از سقف فقط همین درخواست ربات را رد می‌کند؛ IP بن نمی‌شود
            if ( ! self::check_rate_limit( $ip, $rl_limit, $sig ) ) {
                header( 'HTTP/1.1 429 Too Many Requests' );
                header( 'Retry-After: 60' );
                exit( 'محدودیت تعداد درخواست رد شد. لطفا یک دقیقه بعد تلاش کنید.' );
            }

            $raw_path   = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
            $clean_path = Hodima_Core_Helpers::clean_url( rtrim( home_url(), '/' ) . '/' . ltrim( $raw_path, '/' ) );
            self::log_bot( $name, $ip, $ua, $clean_path );
            self::mark_handled();
            break;
        }
    }

    public static function log_bot( string $bot, string $ip, string $ua, string $url ): void {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'hodima_ai_bot_logs', [
            'bot_name'   => $bot,
            'ip_address' => $ip,
            'user_agent' => mb_substr( $ua, 0, 250 ),
            // ستون varchar(500): آدرس بلندتر در حالت strict مای‌اس‌کیوال کل درج را رد می‌کرد
            'url_path'   => mb_substr( $url, 0, 500 ),
        ] );
    }

    public static function get_stats(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT bot_name, COUNT(*) as hits FROM {$wpdb->prefix}hodima_ai_bot_logs GROUP BY bot_name ORDER BY hits DESC"
        , ARRAY_A ) ?? [];
    }

    public static function get_chart_data( string $timeframe = '7d' ): array {
        global $wpdb;
        if ( $timeframe === '24h' ) {
            return $wpdb->get_results(
                "SELECT DATE_FORMAT(created_at, '%H:00') as label, COUNT(*) as count
                 FROM {$wpdb->prefix}hodima_ai_bot_logs
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY HOUR(created_at) ORDER BY created_at ASC"
            , ARRAY_A ) ?? [];
        }
        return $wpdb->get_results(
            "SELECT DATE(created_at) as label, COUNT(*) as count
             FROM {$wpdb->prefix}hodima_ai_bot_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at) ORDER BY label ASC"
        , ARRAY_A ) ?? [];
    }

    public static function get_dashboard_summary(): array {
        global $wpdb;
        return [
            'logs'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hodima_ai_bot_logs" ),
            // فقط مسدودیت‌های فعال (همان چیزی که جدول فهرست سیاه نشان می‌دهد)
            'banned' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hodima_banned_ips WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d SECOND)",
                self::ban_duration()
            ) ),
        ];
    }

    public static function get_bot_console_data( string $bot_name ): array {
        global $wpdb;
        $last_visit = $wpdb->get_var( $wpdb->prepare(
            "SELECT created_at FROM {$wpdb->prefix}hodima_ai_bot_logs WHERE bot_name = %s ORDER BY created_at DESC LIMIT 1", $bot_name
        ) );
        $top_urls = $wpdb->get_results( $wpdb->prepare(
            "SELECT url_path, COUNT(*) as hits FROM {$wpdb->prefix}hodima_ai_bot_logs WHERE bot_name = %s GROUP BY url_path ORDER BY hits DESC LIMIT 5", $bot_name
        ), ARRAY_A );
        return [
            'last_visit' => $last_visit ? (string) $last_visit : 'تاکنون رصدی ثبت نشده',
            'top_urls'   => is_array( $top_urls ) ? $top_urls : [],
        ];
    }

    public static function prune_old_logs(): void {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->prefix}hodima_ai_bot_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)" );
        // مسدودیت‌های منقضی‌شده
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}hodima_banned_ips WHERE created_at < DATE_SUB(NOW(), INTERVAL %d SECOND)",
            self::ban_duration()
        ) );
        self::sync_ban_until();
    }

    public static function clear_logs(): void {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'hodima_clear_ai' ) ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}hodima_ai_bot_logs" );
        wp_safe_redirect( admin_url( 'admin.php?page=hodima-core&tab=ai-shield&msg=cleared' ) );
        exit;
    }

    public static function export_logs(): void {
        Hodima_Core_Helpers::assert_export_access( 'hodima_export_ai' );
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT bot_name, ip_address, url_path, created_at FROM {$wpdb->prefix}hodima_ai_bot_logs ORDER BY created_at DESC LIMIT 5000"
        , ARRAY_A ) ?? [];
        Hodima_Core_Helpers::export_csv(
            'hodima_export_ai',
            [ 'نام ربات', 'آی‌پی', 'مسیر URL', 'تاریخ' ],
            $rows,
            'hodima-ai-bots'
        );
    }
}