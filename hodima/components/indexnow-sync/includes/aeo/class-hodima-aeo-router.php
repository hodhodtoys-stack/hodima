<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_AEO_Router {

    public static function init(): void {
        add_action( 'template_redirect', [ __CLASS__, 'handle_edge_endpoints' ], 0 );
        add_action( 'rest_api_init', [ __CLASS__, 'register_rest' ] );
        add_filter( 'robots_txt', [ __CLASS__, 'auto_robots_txt' ], 99, 2 );
    }

    public static function auto_robots_txt( $output, $public ): string {
        $rules  = "\n\n# --- Hodima AEO Shield (Bilingual) ---\n";
        $rules .= "User-agent: *\nDisallow: /*/*.md$\nDisallow: /*/llm-search\nDisallow: /*/ai-feed.json\nDisallow: /ai-analytics.json\nDisallow: /*ai-internal-data.md\n\n";

        $settings = get_option( 'hodima_ai_bot_settings', [] );
        foreach ( Hodima_Bot_Shield::BOTS as $sig => $name ) {
            if ( ( $settings[ $sig ] ?? '1' ) === '1' ) {
                $rules .= "User-agent: {$sig}\nAllow: /*/*.md$\nAllow: /*/llm-search\nDisallow: /*ai-internal-data.md\n\n";
            }
        }
        
        // اگر ایندکس شدن فایل‌های .md فعال شده باشد، باید قانون Disallow
        // عمومی هم برای موتورهای جستجوی معمولی برداشته شود؛ وگرنه ثبت
        // سایت‌مپ در بینگ هیچ اثری ندارد چون خزش اصلا مجاز نیست.
        if ( self::md_indexing_enabled() ) {
            $rules .= "User-agent: bingbot\nAllow: /*/*.md$\nDisallow: /*ai-internal-data.md\n\n";
            $rules .= "User-agent: BingPreview\nAllow: /*/*.md$\nDisallow: /*ai-internal-data.md\n\n";
        }

        $rules .= 'Sitemap: ' . home_url( '/fa/llms.txt' ) . "\n";
        $rules .= 'Sitemap: ' . home_url( '/en/llms.txt' ) . "\n";
        $rules .= 'Sitemap: ' . home_url( '/fa/sitemap-md.xml' ) . "\n";
        $rules .= 'Sitemap: ' . home_url( '/en/sitemap-md.xml' ) . "\n";

        return (string) $output . $rules;
    }

    public static function register_rest(): void {
        register_rest_route( 'hodima/v1', '/semantic/post/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => fn() => rest_ensure_response( [ 'meta' => [ 'engine' => 'Hodima AEO' ] ] ),
            'permission_callback' => '__return_true',
        ] );
    }

    // =========================================================================
    // 🛡️ توابع کمکی امنیتی و زیرساختی (Enterprise Utilities)
    // =========================================================================

    /** پاکسازی کامل بافر خروجی وردپرس برای جلوگیری از نشت کاراکترهای اضافی پلاگین‌ها */
    private static function clean_output_buffer(): void {
        while ( ob_get_level() ) {
            ob_end_clean();
        }
    }

    /** هدرهای سخت‌گیرانه CORS و امنیت محتوا */
    private static function set_global_security_headers(): void {
        header( 'Access-Control-Allow-Origin: *' );
        header( 'X-Content-Type-Options: nosniff' );
    }

    /** پردازش ETag برای پیاده‌سازی کش 304 و کاهش 90 درصدی مصرف پهنای باند سرور */
    private static function handle_etag_cache( string $content ): void {
        $etag = md5( $content );
        if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( $_SERVER['HTTP_IF_NONE_MATCH'], '"' ) === $etag ) {
            status_header( 304 ); // Not Modified
            exit;
        }
        header( 'ETag: "' . $etag . '"' );
    }

    /** ثبت فوری بازدید ربات قبل از توقف اجرای قالب وردپرس */
    private static function track_bot_visit_early(): void {
        if ( ! class_exists('Hodima_Bot_Shield') ) return;
        
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = class_exists('Hodima_Core_Helpers') ? Hodima_Core_Helpers::get_client_ip() : '';
        if ( ! $ua || ! $ip ) return;

        $settings  = get_option( 'hodima_ai_bot_settings', [] );
        $rl_limit  = (int) get_option( 'hodima_ai_rl_limit', 50 );

        foreach ( Hodima_Bot_Shield::BOTS as $sig => $name ) {
            if ( stripos( $ua, $sig ) === false ) continue;

            if ( isset( $settings[ $sig ] ) && $settings[ $sig ] === '0' ) {
                header( 'HTTP/1.1 403 Forbidden' );
                exit( 'دسترسی این ربات هوش مصنوعی مسدود شده است.' );
            }

            if ( ! Hodima_Bot_Shield::check_rate_limit( $ip, $rl_limit ) ) {
                Hodima_Bot_Shield::ban_ip( $ip, "عبور از سقف نرخ درخواست ({$name})" );
                Hodima_Bot_Shield::block_ip_cloudflare( $ip );
                header( 'HTTP/1.1 429 Too Many Requests' );
                header( 'Retry-After: 60' );
                exit( 'محدودیت تعداد درخواست رد شد.' );
            }

            $raw_path   = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
            $clean_path = class_exists('Hodima_Core_Helpers') ? Hodima_Core_Helpers::clean_url( rtrim( home_url(), '/' ) . '/' . ltrim( $raw_path, '/' ) ) : $raw_path;
            
            Hodima_Bot_Shield::log_bot( $name, $ip, $ua, $clean_path );
            break;
        }
    }

    // =========================================================================
    // ⚙️ هسته مسیریاب مرکزی (Central Router)
    // =========================================================================

    public static function handle_edge_endpoints(): void {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;

        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = (string) wp_parse_url( $uri, PHP_URL_PATH );

        $home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
        if ( $home_path && $home_path !== '/' ) {
            $path = preg_replace( '#^' . preg_quote( $home_path, '#' ) . '#', '', $path );
        }
        $path = trim( (string) $path, '/' );
        if ( $path === '' ) return;

        // 1. Honeypot (امنیتی)
        if ( str_ends_with( $path, 'ai-internal-data.md' ) ) {
            $ip = class_exists('Hodima_Core_Helpers') ? Hodima_Core_Helpers::get_client_ip() : '';
            if ( $ip && class_exists('Hodima_Bot_Shield') ) {
                Hodima_Bot_Shield::ban_ip( $ip, 'برخورد با هانی‌پات ai-internal-data.md' );
                Hodima_Bot_Shield::block_ip_cloudflare( $ip );
            }
            status_header( 403 );
            exit( 'شکار شدید. آی‌پی شما به‌طور دائم ثبت و مسدود شد.' );
        }

        // 2. مسیرهای تحلیلی
        if ( $path === 'ai-analytics.json' ) {
            self::handle_analytics_request();
            exit;
        }

        // 3. ریدایرکت‌های ۳۰۱
        $legacy_exact = ['llms.txt', 'llms-full.txt', 'ai-feed.json', 'openapi.json', 'sitemap-md.xml'];
        if ( in_array( $path, $legacy_exact, true ) || strpos( $path, 'llm-search' ) === 0 ) {
            $query_string = isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
            wp_redirect( home_url( "/fa/{$path}{$query_string}" ), 301 );
            exit;
        }
        if ( preg_match( '/^(.+)\.md\/?$/i', $path ) && ! preg_match( '/^(fa|en)\//i', $path ) ) {
            $clean_path = rtrim($path, '/');
            wp_redirect( home_url( "/fa/{$clean_path}" ), 301 );
            exit;
        }

        // 4. معماری ماژولار Controllers برای مسیرهای دوزبانه
        if ( preg_match( '/^(fa|en)\/(.+)$/i', $path, $matches ) ) {
            $lang     = strtolower( $matches[1] );
            $endpoint = rtrim( $matches[2], '/' );

            // آماده‌سازی اولیه قبل از اجرای کنترلرها
            self::track_bot_visit_early();
            self::clean_output_buffer();
            self::set_global_security_headers();

            if ( $endpoint === 'llms.txt' || $endpoint === 'llms-full.txt' ) {
                self::render_llms_txt( $endpoint, $lang );
            } elseif ( $endpoint === 'sitemap-md.xml' ) {
                self::render_md_sitemap( $lang );
            } elseif ( strpos( $endpoint, 'llm-search' ) === 0 ) {
                self::render_llm_search( $lang );
            } elseif ( $endpoint === 'openapi.json' ) {
                self::render_openapi( $lang );
            } elseif ( $endpoint === 'ai-feed.json' ) {
                self::render_ai_feed( $lang );
            } elseif ( preg_match( '/^(.+)\.md$/i', $endpoint, $m ) ) {
                self::render_markdown( $m[1], $lang );
            }
        }
    }

    // =========================================================================
    // 🧱 کنترلرها (Controllers - Separation of Concerns)
    // =========================================================================

    private static function render_llms_txt( string $endpoint, string $lang ): void {
        $content = Hodima_AEO_Generator::generate_llms_txt( $endpoint === 'llms-full.txt' ? 500 : 20, $lang );
        
        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow' );
        self::handle_etag_cache( $content );
        
        echo $content;
        exit;
    }

    private static function render_md_sitemap( string $lang ): void {

        $content = Hodima_AEO_Generator::generate_md_sitemap_xml( $lang );

        status_header( 200 );

        // نوع MIME استاندارد سایت‌مپ application/xml است. سند پیشنهادی
        // text/xml داشت که بینگ آن را می‌پذیرد ولی استاندارد نیست.
        header( 'Content-Type: application/xml; charset=utf-8' );

        // خود فایل سایت‌مپ هیچ‌وقت نباید ایندکس شود، مستقل از اینکه
        // آدرس‌های داخلش ایندکس‌پذیر باشند یا نه.
        header( 'X-Robots-Tag: noindex, follow' );

        self::handle_etag_cache( $content );

        echo $content;
        exit;
    }

    /**
     * آیا فایل‌های .md باید توسط موتورهای جستجوی معمولی ایندکس شوند؟
     *
     * پیش‌فرض خاموش است. روشن کردنش یعنی نسخه مارک‌داون هر صفحه به عنوان
     * یک سند مستقل وارد ایندکس می‌شود — که می‌تواند محتوای تکراری تلقی
     * شود. فقط وقتی روشن کنید که واقعا می‌خواهید بینگ آن‌ها را ایندکس کند:
     *
     *     add_filter( 'hodima_md_indexing_enabled', '__return_true' );
     *
     * این یک سوییچ است و همزمان هم robots.txt و هم هدر X-Robots-Tag را
     * تغییر می‌دهد، تا حالت نیمه‌کاره (اجازه خزش بدون اجازه ایندکس) پیش نیاید.
     */
    private static function md_indexing_enabled(): bool {
        return (bool) apply_filters( 'hodima_md_indexing_enabled', false );
    }

    private static function render_llm_search( string $lang ): void {
        $q = sanitize_text_field( $_GET['q'] ?? '' );
        $content = Hodima_AEO_Generator::generate_search_results( $q, $lang );

        status_header( 200 );
        header( 'Content-Type: text/markdown; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet' );
        
        echo $content;
        exit;
    }

    private static function render_openapi( string $lang ): void {
        $content = wp_json_encode( Hodima_AEO_Generator::generate_openapi_spec( $lang ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

        status_header( 200 );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow' );
        self::handle_etag_cache( $content );
        
        echo $content;
        exit;
    }

    private static function render_ai_feed( string $lang ): void {
        $content = wp_json_encode( Hodima_AEO_Generator::generate_ai_feed( $lang ), JSON_UNESCAPED_UNICODE );

        status_header( 200 );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
        self::handle_etag_cache( $content );
        
        echo $content;
        exit;
    }

    private static function render_markdown( string $identifier, string $lang ): void {

        $type = '';

        $id = class_exists( 'Hodima_AEO_Data' )
            ? Hodima_AEO_Data::resolve_entity( $identifier, $type )
            : 0;

        if ( ! $id || $type === '' ) {
            self::render_md_error( 404, 'Entity Not Found', self::not_found_body( $identifier ) );
        }

        // آدرس HTML متناظر. رشته خالی یعنی موجودیت مسیر عمومی ندارد
        // (مثلا ترمی از یک تکسونومی داخلی افزونه). در آن حالت نباید
        // سند مارک‌داون تولید شود — نسخه قبلی سندی با آدرس شکسته
        // «/fa/.md?taxonomy=...» می‌ساخت.
        $permalink = class_exists( 'Hodima_AEO_Data' )
            ? Hodima_AEO_Data::get_entity_permalink( $id, $type )
            : '';

        if ( '' === $permalink ) {
            self::render_md_error( 404, 'Entity Not Addressable', 'این موجودیت آدرس عمومی ندارد و نسخه ماشین‌خوان برایش تولید نمی‌شود.' );
        }

        $canonical_md = Hodima_AEO_Generator::format_md_url( $permalink, $lang );

        // آدرس عددی به آدرس نامکی هدایت می‌شود تا هر موجودیت دقیقا یک
        // آدرس ماشین‌خوان داشته باشد. بدون این، /fa/217.md و
        // /fa/نامک-محصول.md دو سند جدا با محتوای یکسان بودند.
        if ( ctype_digit( trim( $identifier, '/' ) ) && $canonical_md !== '' ) {
            $current = home_url( '/' . $lang . '/' . trim( $identifier, '/' ) . '.md' );
            if ( untrailingslashit( $canonical_md ) !== untrailingslashit( $current ) ) {
                wp_redirect( $canonical_md, 301 );
                exit;
            }
        }

        try {
            $md = Hodima_AEO_Generator::generate_markdown( $id, $type, $lang );

            status_header( 200 );
            header( 'Content-Type: text/markdown; charset=utf-8' );
            header( 'X-Robots-Tag: ' . self::md_robots_directive() );

            // canonical به نسخه HTML اشاره می‌کند، نه به خود فایل .md
            header( 'Link: <' . esc_url_raw( $permalink ) . '>; rel="canonical"' );
            header( 'Link: <' . esc_url_raw( $canonical_md ) . '>; rel="alternate"; type="text/markdown"', false );

            self::handle_etag_cache( $md );

            echo $md;
        } catch ( Throwable $e ) {
            error_log( 'Hodima AEO Critical Error on ID [' . $id . ']: ' . $e->getMessage() );
            self::render_md_error( 500, 'Internal Server Error', 'یک خطای سیستمی در سرور رخ داده است. لاگ‌های سرور را بررسی کنید.' );
        }
        exit;
    }

    /**
     * متن خطای ۴۰۴ با تشخیص دقیق اینکه شناسه واقعا چیست.
     *
     * بدون این، هر ۴۰۴ به یک حدس‌زنی تبدیل می‌شد: «۱۳۰ یعنی چه؟».
     * حالا پاسخ صریح است، مثلا: ترمی از تکسونومی pa_color که آرشیو
     * عمومی ندارد.
     */
    private static function not_found_body( string $identifier ): string {

        $clean = trim( $identifier, '/' );

        $body  = 'هیچ موجودیتِ دارای صفحه عمومی با شناسه «' . esc_html( $clean ) . "» یافت نشد.\n\n";

        if ( ctype_digit( $clean ) && class_exists( 'Hodima_AEO_Data' ) ) {
            $body .= "وضعیت این شناسه در دیتابیس:\n";
            $body .= Hodima_AEO_Data::describe_numeric_id( (int) $clean ) . "\n\n";
        }

        $body .= "قانون: نسخه ماشین‌خوان فقط برای موجودیتی تولید می‌شود که\n";
        $body .= "صفحه HTML عمومی داشته باشد.\n\n";
        $body .= "- نوشته/برگه/محصول: باید منتشرشده و نوعش عمومی باشد.\n";
        $body .= "- دسته‌بندی و ویژگی: باید آرشیو عمومی فعال داشته باشد\n";
        $body .= "  (ووکامرس ← محصولات ← ویژگی‌ها ← Enable archives).\n\n";
        $body .= "شناسه عددی فقط میانبر است و به آدرس نامکی هدایت می‌شود.\n\n";
        $body .= "فهرست کامل آدرس‌های معتبر .md:\n";
        $body .= home_url( '/fa/sitemap-md.xml' ) . "\n";

        return $body;
    }

    /** پاسخ خطای یکدست برای مسیرهای .md */
    private static function render_md_error( int $code, string $message, string $body ): void {
        status_header( $code );
        header( 'Content-Type: text/markdown; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow' );
        echo "---\nError: {$code}\nMessage: {$message}\n---\n\n# {$code}\n\n{$body}";
        exit;
    }

    /**
     * دستور ربات برای فایل‌های .md
     *
     * پیش‌فرض noindex است، چون این فایل‌ها نسخه ماشین‌خوان صفحات HTML
     * هستند و ایندکس شدنشان یعنی محتوای تکراری.
     *
     * اگر می‌خواهید بینگ یا موتور دیگری آن‌ها را ایندکس کند (مثلا برای
     * سایت‌مپ sitemap-md.xml)، این فیلتر را در functions.php برگردانید:
     *
     *     add_filter( 'hodima_md_robots_directive', fn() => 'index, follow' );
     */
    private static function md_robots_directive(): string {

        $default = self::md_indexing_enabled()
            ? 'index, follow'
            : 'noindex, nofollow, noarchive, nosnippet';

        return (string) apply_filters( 'hodima_md_robots_directive', $default );
    }

    // =========================================================================
    // 📊 مدیریت آنالیتیکس و داشبوردها
    // =========================================================================

    private static function handle_analytics_request(): void {
        $token = get_option( 'hodima_api_token', '' );
        $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ( empty( $auth ) && function_exists( 'apache_request_headers' ) ) {
            $headers = apache_request_headers();
            $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        $is_valid = current_user_can( 'manage_options' ) || ( ! empty( $token ) && trim( $auth ) === "Bearer {$token}" );
        if ( ! $is_valid ) {
            status_header( 401 );
            header( 'Content-Type: application/json; charset=utf-8' );
            exit( wp_json_encode( [ 'error' => 'دسترسی غیرمجاز. توکن نامعتبر است.' ], JSON_UNESCAPED_UNICODE ) );
        }

        global $wpdb;
        $since    = sanitize_text_field( $_GET['since'] ?? '' );
        $page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
        $per_page = max( 1, min( 1000, (int) ( $_GET['per_page'] ?? 500 ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where = '1=1';
        if ( ! empty( $since ) ) $where .= $wpdb->prepare( ' AND created_at >= %s', $since . ' 00:00:00' );

        $logs = $wpdb->get_results(
            "SELECT bot_name, ip_address, url_path, created_at FROM {$wpdb->prefix}hodima_ai_bot_logs WHERE {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}", ARRAY_A 
        );

        status_header( 200 );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow' );
        echo wp_json_encode( [ 'status' => 'success', 'page' => $page, 'per_page' => $per_page, 'count' => count( $logs ), 'data' => $logs ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    }
}