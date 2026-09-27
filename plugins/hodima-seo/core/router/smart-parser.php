<?php
/**
 * Arian Clean Router - Smart request parser
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function arian_get_request_path() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $uri = esc_url_raw( $uri );

    $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
    $path = trim( $path, '/' );
    if ( $path === '' ) return '';

    $home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( $home_path !== '' ) {
        if ( $path === $home_path ) { return ''; }
        if ( strpos( $path, $home_path . '/' ) === 0 ) {
            $path = substr( $path, strlen( $home_path ) + 1 );
        }
    }
    return trim( $path, '/' );
}

function arian_extract_endpoints( array &$segments ) {
    $extra = array();
    $woo_endpoints = array( 'order-received', 'edit-address', 'downloads', 'lost-password', 'customer-logout', 'payment-methods', 'edit-account', 'view-order', 'orders' );
    $feed_types = array( 'feed', 'rdf', 'rss', 'rss2', 'atom' );

    $count = count( $segments );
    if ( $count >= 2 ) {
        $last = $segments[ $count - 1 ];
        $prev = $segments[ $count - 2 ];

        if ( in_array( $prev, $woo_endpoints, true ) ) {
            $val = array_pop( $segments );
            $ep  = array_pop( $segments );
            $extra[ $ep ] = $val;
        } elseif ( $last === 'feed' ) {
            array_pop( $segments );
            $extra['feed'] = 'feed';
        } elseif ( in_array( $last, $feed_types, true ) && $prev === 'feed' ) {
            array_pop( $segments );
            array_pop( $segments );
            $extra['feed'] = $last;
        }
    }
    
    $count = count( $segments );
    if ( $count >= 1 ) {
        $last = $segments[ $count - 1 ];
        if ( in_array( $last, $woo_endpoints, true ) ) {
            $ep = array_pop( $segments );
            $extra[ $ep ] = '';
        }
    }

    $count = count( $segments );
    if ( $count >= 2 && $segments[ $count - 2 ] === 'page' && ctype_digit( (string) $segments[ $count - 1 ] ) ) {
        $extra['paged'] = (int) array_pop( $segments );
        array_pop( $segments ); 
    }
    return $extra;
}

function arian_resolve_path( $path, array $segments ) {
    global $wpdb;
    $leaf   = end( $segments );
    $single = ( count( $segments ) === 1 );
    if ( empty( $leaf ) ) return array();

    /*
     * برگه تودرتو با مسیر کامل.
     * جستجوی نامک آخر به‌تنهایی، دو برگه هم‌نام زیر والدهای مختلف
     * (مثلا /خدمات/درباره/ و /شرکت/درباره/) را از هم تشخیص نمی‌داد و
     * همیشه اولی را نشان می‌داد.
     */
    if ( ! $single ) {
        $page = get_page_by_path( implode( '/', $segments ) );
        if ( $page instanceof WP_Post
            && ( 'publish' === $page->post_status
                || ( 'private' === $page->post_status && current_user_can( 'read_post', $page->ID ) ) ) ) {
            return [ 'page_id' => $page->ID ];
        }
    }

    $query = $wpdb->prepare( "
        SELECT 'post' AS type, p.ID as id, p.post_type as subtype, p.post_status as status, p.post_name as slug
        FROM {$wpdb->posts} p
        WHERE p.post_name = %s AND p.post_status IN ('publish', 'private') AND p.post_type IN ('product', 'page', 'post')
        UNION ALL
        SELECT 'term' AS type, t.term_id as id, tt.taxonomy as subtype, '' as status, t.slug as slug
        FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE t.slug = %s AND tt.taxonomy IN ('product_cat', 'category')
    ", $leaf, $leaf );

    $results = $wpdb->get_results( $query );
    if ( empty( $results ) ) return array();

    $priorities = [ 'product_cat' => 1, 'product' => 2, 'category' => 3, 'page' => 4, 'post' => 5 ];
    usort( $results, function($a, $b) use ($priorities) {
        $pA = $priorities[$a->subtype] ?? 99;
        $pB = $priorities[$b->subtype] ?? 99;
        return $pA <=> $pB;
    } );

    foreach ( $results as $row ) {
        if ( $row->type === 'post' ) {
            if ( $row->status === 'private' && !current_user_can( 'edit_post', $row->id ) ) continue;
            if ( $row->subtype === 'page' ) {
                return array( 'page_id' => $row->id );
            } elseif ( $row->subtype === 'product' && $single ) {
                return array( 'post_type' => 'product', 'name' => $row->slug );
            } elseif ( $row->subtype === 'post' && $single ) {
                return array( 'post_type' => 'post', 'name' => $row->slug );
            }
        } elseif ( $row->type === 'term' ) {
            if ( $row->subtype === 'product_cat' ) {
                return array( 'product_cat' => $row->slug, 'taxonomy' => 'product_cat', 'term' => $row->slug );
            } elseif ( $row->subtype === 'category' ) {
                return array( 'category_name' => $row->slug );
            }
        }
    }
    return array();
}

add_filter( 'request', function ( $query_vars ) {
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return $query_vars;
    }

    foreach ( array( 'p', 'page_id', 'product_cat', 'cat', 'name', 's', 'rest_route' ) as $k ) {
        if ( isset( $_GET[ $k ] ) ) return $query_vars;
    }
    if ( isset( $query_vars['s'] ) || isset( $query_vars['sitemap'] ) || isset( $query_vars['rest_route'] ) ) {
        return $query_vars;
    }

    $path = arian_get_request_path();
    if ( $path === '' ) return $query_vars;

    $segments = explode( '/', $path );
    $extra = arian_extract_endpoints( $segments );
    if ( empty( $segments ) ) return $query_vars;

    $preserve = array();
    foreach ( array( 'preview', 'preview_id', 'preview_nonce', 'cpage', 'embed', 'page' ) as $k ) {
        if ( isset( $query_vars[ $k ] ) ) $preserve[ $k ] = $query_vars[ $k ];
    }

    if ( is_user_logged_in() ) {
        $resolved = arian_resolve_path( $path, $segments );
    } else {
        $key = 'route_' . arian_router_cache_generation() . '_' . md5( $path );
        $resolved = wp_cache_get( $key, 'arian_router' );
        if ( false === $resolved ) {
            $resolved = arian_resolve_path( $path, $segments );
            wp_cache_set( $key, $resolved, 'arian_router', HOUR_IN_SECONDS );
        }
    }

    if ( ! empty( $resolved ) ) {
        // مسیر پایه (بدون endpoint و صفحه‌بندی) برای بررسی تکراری‌بودن
        // در template_redirect نگه داشته می‌شود.
        arian_router_matched_path( implode( '/', $segments ) );
        return array_merge( $preserve, $extra, $resolved );
    }
    return $query_vars;
}, 5 );

/**
 * نگهدارنده مسیری که روتر برای این درخواست تطبیق داده است.
 * بدون آرگومان، مقدار ذخیره‌شده را برمی‌گرداند.
 */
function arian_router_matched_path( ?string $path = null ): ?string {
    static $matched = null;
    if ( $path !== null ) {
        $matched = $path;
    }
    return $matched;
}

add_action( 'template_redirect', function () {
    if ( is_admin() || wp_doing_ajax() ) return;

    $path = arian_get_request_path();
    if ( $path === '' ) return;

    $segments = explode( '/', $path );
    $first    = $segments[0];
    $target   = '';
    
    $product_base = function_exists('arian_product_base') ? arian_product_base() : 'product';

    if ( $first === $product_base && ! empty( $segments[1] ) ) {
        $p = get_page_by_path( $segments[1], OBJECT, 'product' );
        if ( $p && $p->post_type === 'product' ) {
            $target = home_url( user_trailingslashit( $p->post_name ) );
        }
    } elseif ( $first === arian_product_cat_base() || $first === arian_category_base() ) {
        array_shift( $segments );
        if ( ! empty( $segments ) ) {
            $target = home_url( user_trailingslashit( implode( '/', $segments ) ) );
        }
    }

    if ( $target === '' ) return;

    $current = home_url( user_trailingslashit( $path ) );
    if ( untrailingslashit( $target ) === untrailingslashit( $current ) ) return;

    $qs = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';
    if ( $qs !== '' ) $target .= '?' . $qs;

    wp_safe_redirect( $target, 301 );
    exit;
}, 1 );

add_filter( 'redirect_canonical', function ( $redirect_url, $requested_url ) {
    if ( empty( $redirect_url ) ) return $redirect_url;

    $bases = array_unique( array_filter( array(
        arian_category_base(),
        arian_product_cat_base(),
        function_exists( 'arian_product_base' ) ? arian_product_base() : 'product',
    ) ) );

    $req_path = wp_parse_url( $requested_url, PHP_URL_PATH ) ?: '';
    $red_path = wp_parse_url( $redirect_url, PHP_URL_PATH ) ?: '';

    if ( trailingslashit( strtolower( $req_path ) ) === trailingslashit( strtolower( $red_path ) ) ) {
        return $redirect_url;
    }

    foreach ( $bases as $base ) {
        $in_redirect = arian_strip_leading_base( $redirect_url, $base ) !== $redirect_url;
        $in_request  = arian_strip_leading_base( $requested_url, $base ) !== $requested_url;
        
        if ( $in_redirect && ! $in_request ) {
            return arian_strip_leading_base( $redirect_url, $base );
        }
    }
    return $redirect_url;
}, 10, 2 );

/* ============================================================
 * اعتبارسنجی مسیر (اختیاری — به صورت پیش‌فرض خاموش)
 * ------------------------------------------------------------
 * پیشینه: arian_resolve_path() عمدا فقط آخرین بخش مسیر را جستجو
 * می‌کند تا برگه‌های سلسله‌مراتبی و دسته‌بندی‌های تودرتو کار کنند.
 * عارضه‌اش این است که هر پیشوند دلخواهی هم کد ۲۰۰ می‌گیرد:
 *
 *     /گل-سر/            → درست
 *     /aaa/bbb/گل-سر/    → همان محتوا، همان کد ۲۰۰
 *
 * نسخه قبلی این فایل سعی می‌کرد با مقایسه مسیر درخواستی و خروجی
 * get_term_link() این را ببندد. آن منطق غلط بود: برای یک ترم
 * تودرتو هر دو مسیر «/گل-سر/» و «/اکسسوری/گل-سر/» معتبرند، ولی
 * get_term_link() فقط یکی را برمی‌گرداند (بسته به تنظیم
 * hierarchical در پیوندهای یکتای ووکامرس). نتیجه: ریدایرکت
 * اشتباه روی مسیرهای کاملا سالم.
 *
 * منطق درست این است: بخش آخر خودِ شیء است و بخش‌های قبل از آن
 * باید زنجیره‌ای از والدین واقعی همان شیء باشند. هر چیز دیگری
 * زباله است. این تابع دقیقا همین را بررسی می‌کند.
 *
 * این بررسی حالا به‌صورت پیش‌فرض روشن است. تگ canonical به تنهایی
 * کافی نبود: فضای بی‌نهایت آدرس‌های ۲۰۰، بودجه خزش را هدر می‌داد و
 * کش صفحه برای هر مسیر زباله یک نسخه جدا می‌ساخت. آدرس نامعتبر با
 * ۳۰۱ به شکل درست هدایت می‌شود (برگه: مسیر کامل والدین؛ ترم: نامک)
 * و صفحه‌بندی/اندپوینت انتهای آدرس حفظ می‌شود.
 *
 * برای خاموش کردن در صورت بروز مشکل:
 *
 *     add_filter( 'arian_router_enforce_path', '__return_false' );
 * ============================================================ */
add_action( 'template_redirect', 'arian_router_enforce_canonical_path', 2 );

function arian_router_enforce_canonical_path() {

    if ( ! apply_filters( 'arian_router_enforce_path', true ) ) {
        return;
    }

    if ( is_admin() || wp_doing_ajax() || is_404() || is_feed() || is_embed() || is_preview() ) {
        return;
    }

    // فقط درخواست‌هایی که خود روتر تطبیق داده است
    $matched = arian_router_matched_path();
    if ( $matched === null || $matched === '' ) {
        return;
    }

    $segments = explode( '/', $matched );

    // یک بخش یعنی فقط خود نامک — همیشه معتبر است
    if ( count( $segments ) < 2 ) {
        return;
    }

    array_pop( $segments );            // آخرین بخش = خود شیء
    $prefix_segments = $segments;      // بخش‌های ابتدایی که باید والد باشند

    $ancestor_slugs = arian_router_ancestor_slugs();

    // شیء بدون والد شناخته‌شده: نمی‌توان قضاوت کرد، دست نزن
    if ( $ancestor_slugs === null ) {
        return;
    }

    if ( arian_router_prefix_is_valid( $prefix_segments, $ancestor_slugs ) ) {
        return;
    }

    // مسیر زباله است → هدایت به شکلی که طبق همین قاعده معتبر است:
    // برگه با زنجیره کامل والدین (همان پیوند یکتای وردپرس)، ترم با نامک.
    $all_segments = explode( '/', $matched );
    $leaf         = (string) array_pop( $all_segments );

    if ( $leaf === '' ) {
        return;
    }

    $target_path = is_page()
        ? implode( '/', [ ...array_reverse( $ancestor_slugs ), $leaf ] )
        : $leaf;

    // صفحه‌بندی و اندپوینت (مثل /page/2/ یا /feed/) که روتر جدا کرده بود
    $full   = arian_get_request_path();
    $suffix = str_starts_with( $full, $matched . '/' ) ? substr( $full, strlen( $matched ) ) : '';

    $target = home_url( user_trailingslashit( $target_path . $suffix ) );

    if ( untrailingslashit( $target ) === untrailingslashit( home_url( $full ) ) ) {
        return; // جلوگیری از حلقه ریدایرکت
    }

    $qs = isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '';
    if ( $qs !== '' ) {
        $target .= '?' . $qs;
    }

    wp_safe_redirect( $target, 301 );
    exit;
}

/**
 * نامک والدین شیء جاری، از نزدیک‌ترین والد تا ریشه.
 *
 * @return array|null آرایه نامک‌ها، یا null اگر نوع شیء پشتیبانی نشود.
 */
function arian_router_ancestor_slugs() {

    if ( is_category() || is_tax() ) {

        $term = get_queried_object();
        if ( ! ( $term instanceof WP_Term ) ) {
            return null;
        }

        $slugs = [];
        foreach ( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) as $ancestor_id ) {
            $ancestor = get_term( $ancestor_id, $term->taxonomy );
            if ( $ancestor instanceof WP_Term ) {
                $slugs[] = $ancestor->slug;
            }
        }
        return $slugs;
    }

    if ( is_page() ) {

        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            return null;
        }

        $slugs = [];
        foreach ( get_post_ancestors( $post_id ) as $ancestor_id ) {
            $ancestor = get_post( $ancestor_id );
            if ( $ancestor ) {
                $slugs[] = $ancestor->post_name;
            }
        }
        return $slugs;
    }

    // نوشته و محصول در arian_resolve_path() فقط با یک بخش حل می‌شوند،
    // پس اصلا به اینجا نمی‌رسند.
    return null;
}

/**
 * آیا بخش‌های ابتدایی مسیر، زیرمجموعه‌ای پیوسته از زنجیره والدین‌اند؟
 *
 * زنجیره والدین از نزدیک‌ترین والد به ریشه مرتب است؛ مسیر برعکس.
 * مثال برای «ریشه > اکسسوری > گل‌سر»:
 *
 *     /گل-سر/                    ← بدون پیشوند، معتبر
 *     /اکسسوری/گل-سر/            ← معتبر
 *     /ریشه/اکسسوری/گل-سر/       ← معتبر
 *     /aaa/گل-سر/                ← نامعتبر
 *     /ریشه/گل-سر/               ← نامعتبر (اکسسوری جا افتاده)
 */
function arian_router_prefix_is_valid( array $prefix_segments, array $ancestor_slugs ) {

    if ( empty( $prefix_segments ) ) {
        return true;
    }

    // زنجیره والدین را به ترتیب مسیر (ریشه → نزدیک) برگردان
    $chain = array_reverse( $ancestor_slugs );
    $depth = count( $prefix_segments );

    if ( $depth > count( $chain ) ) {
        return false;
    }

    // پیشوند باید دقیقا انتهای زنجیره باشد
    $expected = array_slice( $chain, -$depth );

    foreach ( $expected as $index => $slug ) {
        if ( rawurldecode( (string) $prefix_segments[ $index ] ) !== rawurldecode( (string) $slug ) ) {
            return false;
        }
    }

    return true;
}
