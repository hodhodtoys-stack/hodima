<?php
/**
 * Hodima Shared Helpers
 * Path: hodima/inc/helpers.php
 *
 * توابع مشترکی که چند ماژول به آن‌ها نیاز دارند.
 * هدف: یک منبع واحد حقیقت برای تشخیص IP کاربر و تشخیص ربات موتور جستجو،
 * به جای پیاده‌سازی‌های پراکنده و ناسازگار در ماژول‌های مختلف.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * ۱. تشخیص IP واقعی کاربر (سازگار با Cloudflare)
 * ============================================================ */

/**
 * محدوده‌های رسمی Cloudflare.
 * فقط وقتی REMOTE_ADDR داخل این محدوده‌ها باشد به هدرهای پراکسی اعتماد می‌کنیم.
 * در غیر این صورت هر کاربری می‌توانست با ارسال هدر جعلی، محدودیت نرخ را دور بزند.
 *
 * منبع: https://www.cloudflare.com/ips/
 */
function hodima_cloudflare_ranges(): array {
    return (array) apply_filters( 'hodima_cloudflare_ranges', [
        // IPv4
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ] );
}

/**
 * تطبیق IP با CIDR — پشتیبانی همزمان از IPv4 و IPv6.
 * پیاده‌سازی باینری با inet_pton تا باگ محدوده‌های IPv6 تکرار نشود.
 */
function hodima_ip_in_cidr( string $ip, string $cidr ): bool {

    if ( ! str_contains( $cidr, '/' ) ) {
        return $ip === $cidr;
    }

    [ $subnet, $bits ] = explode( '/', $cidr, 2 );

    $ip_bin     = @inet_pton( $ip );
    $subnet_bin = @inet_pton( $subnet );

    if ( false === $ip_bin || false === $subnet_bin ) {
        return false;
    }

    // خانواده آدرس باید یکی باشد (۴ بایت برای IPv4، ۱۶ بایت برای IPv6)
    if ( strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
        return false;
    }

    $bits      = (int) $bits;
    $max_bits  = strlen( $ip_bin ) * 8;

    if ( $bits < 0 || $bits > $max_bits ) {
        return false;
    }

    $whole_bytes = intdiv( $bits, 8 );
    $rest_bits   = $bits % 8;

    if ( $whole_bytes > 0 && strncmp( $ip_bin, $subnet_bin, $whole_bytes ) !== 0 ) {
        return false;
    }

    if ( 0 === $rest_bits ) {
        return true;
    }

    $mask = ~( ( 1 << ( 8 - $rest_bits ) ) - 1 ) & 0xFF;

    return ( ord( $ip_bin[ $whole_bytes ] ) & $mask ) === ( ord( $subnet_bin[ $whole_bytes ] ) & $mask );
}

/**
 * IP واقعی بازدیدکننده.
 *
 * به هدرهای CF-Connecting-IP / X-Forwarded-For فقط زمانی اعتماد می‌شود
 * که خود اتصال از یک IP معتبر Cloudflare آمده باشد.
 *
 * @return string یک IP معتبر، یا رشته خالی اگر قابل تشخیص نباشد.
 */
function hodima_get_client_ip(): string {

    static $resolved = null;

    if ( null !== $resolved ) {
        return $resolved;
    }

    $remote = isset( $_SERVER['REMOTE_ADDR'] )
        ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) )
        : '';

    $remote = filter_var( $remote, FILTER_VALIDATE_IP ) ?: '';

    if ( '' === $remote ) {
        return $resolved = '';
    }

    $behind_cloudflare = false;
    foreach ( hodima_cloudflare_ranges() as $cidr ) {
        if ( hodima_ip_in_cidr( $remote, $cidr ) ) {
            $behind_cloudflare = true;
            break;
        }
    }

    if ( ! $behind_cloudflare ) {
        return $resolved = $remote;
    }

    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $cf = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
        if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
            return $resolved = $cf;
        }
    }

    if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $chain = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        $first = trim( (string) reset( $chain ) );
        if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
            return $resolved = $first;
        }
    }

    return $resolved = $remote;
}

/**
 * کلید یکتا برای محدودیت نرخ.
 * اگر IP قابل تشخیص نبود، از هش User-Agent استفاده می‌شود تا
 * همه بازدیدکنندگان داخل یک سطل مشترک نیفتند.
 */
function hodima_rate_limit_key( string $prefix ): string {

    $ip = hodima_get_client_ip();

    if ( '' === $ip ) {
        $ip = 'ua:' . ( $_SERVER['HTTP_USER_AGENT'] ?? 'unknown' );
    }

    return $prefix . md5( $ip );
}

/* ============================================================
 * ۲. تشخیص ربات موتور جستجو
 * ============================================================ */

function hodima_is_search_bot(): bool {

    static $is_bot = null;

    if ( null !== $is_bot ) {
        return $is_bot;
    }

    $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
        ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) )
        : '';

    if ( '' === $user_agent ) {
        return $is_bot = false;
    }

    $bots = (array) apply_filters( 'hodima_search_bot_agents', [
        'googlebot',
        'bingbot',
        'yandex',
        'duckduckbot',
        'slurp',
        'baiduspider',
        'applebot',
        'petalbot',
    ] );

    foreach ( $bots as $bot ) {
        if ( str_contains( $user_agent, (string) $bot ) ) {
            return $is_bot = true;
        }
    }

    return $is_bot = false;
}

/* ============================================================
 * ۳. ساخت ItemList از کوئری اصلی آرشیو
 * ============================================================ */

/**
 * عناصر ListItem را از کوئری اصلی صفحه می‌سازد.
 *
 * عمدا از the_post() استفاده نمی‌شود: توابع اسکیما روی wp_head و wp_footer
 * اجرا می‌شوند و the_post() متغیر سراسری $post و اشاره‌گر حلقه را جابه‌جا
 * می‌کند. خواندن مستقیم از $wp_query->posts هیچ وضعیت سراسری‌ای را دست
 * نمی‌زند و کوئری اضافه هم به دیتابیس نمی‌فرستد.
 *
 * @param int $limit سقف تعداد آیتم‌ها (گوگل برای ItemList بیش از ۱۰۰ را نادیده می‌گیرد).
 */
function hodima_get_archive_itemlist_elements( int $limit = 30 ): array {

    global $wp_query;

    if ( ! ( $wp_query instanceof WP_Query ) || empty( $wp_query->posts ) ) {
        return [];
    }

    $elements = [];
    $position = 1;

    foreach ( $wp_query->posts as $queried_post ) {

        if ( $position > $limit ) {
            break;
        }

        $post_id = ( $queried_post instanceof WP_Post ) ? (int) $queried_post->ID : (int) $queried_post;

        if ( $post_id <= 0 ) {
            continue;
        }

        $url = get_permalink( $post_id );

        if ( ! $url || is_wp_error( $url ) ) {
            continue;
        }

        $elements[] = [
            '@type'    => 'ListItem',
            'position' => $position,
            'url'      => $url,
            'name'     => wp_strip_all_tags( (string) get_the_title( $post_id ) ),
        ];

        $position++;
    }

    return $elements;
}

/* ============================================================
 * ۴. تشخیص صفحه پنل کاربری
 * ============================================================ */

/**
 * آیا درخواست جاری صفحه پنل کاربری است؟
 *
 * پنل تنها یک نقطه ورود دارد: تمپلیت page-user-panel.php
 * (شورت‌کدی وجود ندارد). اسلاگ هم بررسی می‌شود چون وردپرس همان
 * تمپلیت را برای برگه‌ای با اسلاگ user-panel هم به کار می‌برد.
 */
function hodima_is_user_panel_page(): bool {

    if ( is_admin() ) {
        return false;
    }

    $is_panel = is_page_template( 'page-user-panel.php' ) || is_page( 'user-panel' );

    return (bool) apply_filters( 'hodima_is_user_panel_page', $is_panel );
}

/* ============================================================
 * ۵. موتور Canonical — تنها منبع حقیقت آدرس صفحه
 * ------------------------------------------------------------
 * سه ماژول به یک آدرس یکسان نیاز دارند:
 *   core/seobox/  → تگ <link rel="canonical"> و og:url
 *   schema/       → فیلد @id و url در JSON-LD
 *   core/router/  → تشخیص اینکه مسیر درخواستی معتبر است یا نه
 *
 * تا پیش از این هر کدام آدرس را جداگانه می‌ساختند و در چند حالت
 * به نتایج متفاوت می‌رسیدند. وقتی @id اسکیما با canonical فرق کند،
 * گوگل دو موجودیت جدا برای یک صفحه می‌بیند.
 *
 * همه مسیرها از get_permalink() و get_term_link() عبور می‌کنند،
 * بنابراین فیلترهای روتر (post_type_link و term_link) روی خروجی
 * اعمال می‌شوند و آدرس تمیز تولید می‌شود.
 * ============================================================ */

/**
 * آدرس کنونیکال صفحه جاری.
 *
 * @return string آدرس کامل، یا رشته خالی برای صفحاتی که کنونیکال
 *                معنادار ندارند (۴۰۴).
 */
function hodima_get_canonical_url(): string {

    static $resolved = null;

    if ( null !== $resolved ) {
        return $resolved;
    }

    $url      = '';
    $override = '';

    if ( is_404() ) {
        return $resolved = '';
    }

    // ترتیب بررسی مهم است: برگه ثابت صفحه اصلی هم is_singular() را true می‌کند.
    if ( is_front_page() ) {

        $url = home_url( '/' );

    } elseif ( is_home() ) {

        $blog_id = (int) get_option( 'page_for_posts' );
        $url     = $blog_id > 0 ? (string) get_permalink( $blog_id ) : home_url( '/' );

    } elseif ( is_singular() ) {

        $post_id  = get_queried_object_id();
        $override = (string) get_post_meta( $post_id, '_seobox_canonical', true );
        $url      = $override !== '' ? $override : (string) get_permalink( $post_id );

    } elseif ( is_category() || is_tag() || is_tax() ) {

        $term = get_queried_object();

        if ( $term instanceof WP_Term ) {
            $override = (string) get_term_meta( $term->term_id, '_seobox_canonical', true );

            if ( $override !== '' ) {
                $url = $override;
            } else {
                $link = get_term_link( $term );
                $url  = is_wp_error( $link ) ? '' : (string) $link;
            }
        }

    } elseif ( function_exists( 'is_shop' ) && is_shop() ) {

        $shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
        $url     = $shop_id > 0 ? (string) get_permalink( $shop_id ) : home_url( '/' );

    } elseif ( is_post_type_archive() ) {

        $post_type = get_query_var( 'post_type' );
        $link      = get_post_type_archive_link( is_array( $post_type ) ? (string) reset( $post_type ) : (string) $post_type );
        $url       = $link ? (string) $link : '';

    } elseif ( is_author() ) {

        $url = (string) get_author_posts_url( get_queried_object_id() );

    } elseif ( is_search() ) {

        $url = (string) get_search_link( get_search_query() );

    } elseif ( is_day() ) {

        $url = (string) get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );

    } elseif ( is_month() ) {

        $url = (string) get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );

    } elseif ( is_year() ) {

        $url = (string) get_year_link( (int) get_query_var( 'year' ) );
    }

    if ( $url === '' ) {
        return $resolved = '';
    }

    // صفحه‌بندی: کنونیکال باید به همان صفحه اشاره کند، نه صفحه یک.
    // اگر مدیر کنونیکال دستی گذاشته، دست نمی‌زنیم.
    if ( $override === '' ) {
        $url = hodima_append_pagination_to_url( $url );
    }

    return $resolved = (string) apply_filters( 'hodima_canonical_url', $url );
}

/**
 * افزودن بخش صفحه‌بندی به یک آدرس پایه.
 *
 * بدون این، صفحه دوم آرشیو به صفحه اول کنونیکال می‌شد و گوگل
 * محتوای صفحات ۲ به بعد را کنار می‌گذاشت.
 */
function hodima_append_pagination_to_url( string $url ): string {

    $paged = (int) get_query_var( 'paged' );
    $page  = (int) get_query_var( 'page' );

    if ( $paged > 1 ) {
        return user_trailingslashit( trailingslashit( $url ) . 'page/' . $paged, 'paged' );
    }

    // صفحه‌بندی داخل یک نوشته با <!--nextpage-->
    if ( $page > 1 && is_singular() ) {
        return user_trailingslashit( trailingslashit( $url ) . $page, 'single_paged' );
    }

    return $url;
}

/**
 * آیا این صفحه ارزش تولید JSON-LD دارد؟
 *
 * ۴۰۴ و جستجو کنونیکال پایدار ندارند. تا پیش از این
 * homepage-schema.php روی این صفحات آدرس خانه را به عنوان @id
 * می‌نوشت — یعنی صفحه ۴۰۴ و صفحه اصلی یک شناسه مشترک داشتند.
 */
function hodima_page_has_schema_identity(): bool {

    if ( is_404() || is_search() ) {
        return false;
    }

    return hodima_get_canonical_url() !== '';
}

/* ============================================================
 * ۶. بررسی وجود جدول سفارشی، با کش
 * ------------------------------------------------------------
 * ماژول پنل کاربری در ۱۲ نقطه "SHOW TABLES LIKE" اجرا می‌کرد؛
 * چهار مورد از آن‌ها روی هوک admin_menu بود، یعنی در *هر* بارگذاری
 * صفحه پیشخوان. جدول‌ها بعد از نصب دیگر ناپدید نمی‌شوند، پس نتیجه
 * را هم در حافظه همان درخواست و هم در یک ترنزینت نگه می‌داریم.
 * ============================================================ */
function hodima_table_exists( string $table ): bool {

    static $memo = [];

    if ( isset( $memo[ $table ] ) ) {
        return $memo[ $table ];
    }

    $cache_key = 'hodima_tbl_' . md5( $table );
    $cached    = get_transient( $cache_key );

    if ( 'yes' === $cached ) {
        return $memo[ $table ] = true;
    }

    global $wpdb;

    $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

    // نتیجه مثبت طولانی کش می‌شود؛ نتیجه منفی کوتاه، تا بعد از نصب
    // جدول خیلی زود تشخیص داده شود.
    set_transient( $cache_key, $found ? 'yes' : 'no', $found ? WEEK_IN_SECONDS : MINUTE_IN_SECONDS );

    return $memo[ $table ] = $found;
}
