<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_AEO_Data {

    /**
     * تبدیل شناسه مسیر به شناسه واقعی شیء.
     */
    public static function resolve_entity( string $identifier, string &$type ): int {

        $path = self::normalize_identifier( $identifier );

        if ( $path === '' ) {
            return 0;
        }

        /* -----------------------------------------------------------------
         * اولویت اول: بررسی به عنوان نامک (Slug)
         * مشکل نامک‌های عددی (مثل محصولی با کد/مدل 103) در اینجا حل می‌شود.
         * سیستم ابتدا تلاش می‌کند مسیر را از طریق روتر به عنوان نامک پیدا کند
         * تا با شناسه‌های عددی (IDs) تداخل نداشته باشد.
         * ----------------------------------------------------------------- */
        foreach ( self::path_variants( $path ) as $variant ) {

            $segments = array_values( array_filter( explode( '/', $variant ), 'strlen' ) );

            if ( empty( $segments ) ) {
                continue;
            }

            if ( function_exists( 'arian_resolve_path' ) ) {
                $resolved = arian_resolve_path( $variant, $segments );
                $mapped   = self::map_router_vars( $resolved, $type );
                if ( $mapped && self::is_addressable( $mapped, $type ) ) {
                    return $mapped;
                }
            }

            $fallback = self::resolve_by_leaf_slug( (string) end( $segments ), $type );

            if ( $fallback && self::is_addressable( $fallback, $type ) ) {
                return $fallback;
            }
        }

        /* -----------------------------------------------------------------
         * اولویت دوم: فالبک به شناسه عددی (ID Shortcut)
         * اگر مسیر به عنوان نامک پیدا نشد و تماماً عدد بود، آنگاه آن را 
         * به عنوان میانبرِ شناسه (ID) دیتابیس در نظر می‌گیریم.
         * ----------------------------------------------------------------- */
        if ( ctype_digit( $path ) ) {
            $numeric_id = self::resolve_numeric_id( (int) $path, $type );
            if ( $numeric_id > 0 ) {
                return $numeric_id;
            }
        }

        return 0;
    }

    /**
     * شکل‌های ممکن یک مسیر: همان‌طور که آمده، کدگذاری‌شده، و decode شده.
     * ترتیب مهم است — شکل خام اول امتحان می‌شود چون معمولا همانی است
     * که در دیتابیس ذخیره شده.
     */
    private static function path_variants( string $path ): array {

        $variants = [ $path ];

        $decoded = rawurldecode( $path );
        if ( $decoded !== $path ) {
            $variants[] = $decoded;
        }

        // کدگذاری هر بخش جداگانه تا اسلش‌ها سالم بمانند
        $encoded = implode( '/', array_map( 'rawurlencode', explode( '/', $decoded ) ) );
        if ( ! in_array( $encoded, $variants, true ) ) {
            $variants[] = $encoded;
        }

        // نسخه حروف کوچک: وردپرس نامک‌ها را با حروف کوچک ذخیره می‌کند
        $lower = strtolower( $encoded );
        if ( ! in_array( $lower, $variants, true ) ) {
            $variants[] = $lower;
        }

        return $variants;
    }

    /** دروازه واحد: آیا این موجودیت صفحه عمومی دارد؟ */
    public static function is_addressable( int $id, string $type ): bool {
        return ( 'post' === $type )
            ? self::post_is_addressable( $id )
            : self::term_is_addressable( $id );
    }

    /**
     * نوع‌های پستی که *رزولور نامکی* پشتیبانی می‌کند.
     */
    public const ALLOWED_POST_TYPES = [ 'product', 'page', 'post' ];

    /**
     * تکسونومی‌هایی که *رزولور نامکی* پشتیبانی می‌کند.
     */
    public const ALLOWED_TAXONOMIES = [ 'product_cat', 'category' ];

    /** آیا این پست صفحه عمومی قابل مشاهده دارد؟ */
    public static function post_is_addressable( int $id ): bool {

        $post = get_post( $id );

        if ( ! ( $post instanceof WP_Post ) ) {
            return false;
        }

        if ( ! is_post_type_viewable( $post->post_type ) ) {
            return false;
        }

        if ( 'publish' === $post->post_status ) {
            return true;
        }

        if ( 'private' === $post->post_status ) {
            return current_user_can( 'read_post', $id );
        }

        return false;
    }

    /** آیا این ترم آرشیو عمومی قابل مشاهده دارد؟ */
    public static function term_is_addressable( int $id ): bool {

        $term = get_term( $id );

        if ( ! ( $term instanceof WP_Term ) ) {
            return false;
        }

        if ( ! is_taxonomy_viewable( $term->taxonomy ) ) {
            return false;
        }

        return self::term_link_has_path( $term );
    }

    /** آیا get_term_link مسیر واقعی می‌دهد یا فقط رشته کوئری؟ */
    private static function term_link_has_path( WP_Term $term ): bool {

        $link = get_term_link( $term );

        if ( is_wp_error( $link ) || empty( $link ) ) {
            return false;
        }

        $path = trim( (string) wp_parse_url( (string) $link, PHP_URL_PATH ), '/' );
        $home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );

        return $path !== '' && $path !== $home;
    }

    /**
     * حل شناسه عددی.
     */
    private static function resolve_numeric_id( int $id, string &$type ): int {

        if ( $id <= 0 ) {
            return 0;
        }

        $as_post = self::post_is_addressable( $id );
        $as_term = self::term_is_addressable( $id );

        // مبهم: هر دو معتبرند → حدس نمی‌زنیم
        if ( $as_post && $as_term ) {
            return 0;
        }

        if ( $as_post ) {
            $type = 'post';
            return $id;
        }

        if ( $as_term ) {
            $type = 'term';
            return $id;
        }

        return 0;
    }

    /**
     * توضیح انسان‌خوان اینکه یک شناسه عددی واقعا چیست.
     * فقط برای پیام خطا استفاده می‌شود تا اشکال‌زدایی حدسی نباشد.
     */
    public static function describe_numeric_id( int $id ): string {

        $lines = [];

        $post = get_post( $id );
        if ( $post instanceof WP_Post ) {
            $viewable = is_post_type_viewable( $post->post_type ) ? 'دارد' : 'ندارد';
            $lines[]  = sprintf(
                '- در جدول نوشته‌ها: نوع «%s»، وضعیت «%s»، صفحه عمومی %s.',
                $post->post_type,
                $post->post_status,
                $viewable
            );
        }

        $term = get_term( $id );
        if ( $term instanceof WP_Term ) {
            $has_path = self::term_link_has_path( $term ) ? 'دارد' : 'ندارد';
            $lines[]  = sprintf(
                '- در جدول ترم‌ها: تکسونومی «%s»، نامک «%s»، آرشیو عمومی %s.',
                $term->taxonomy,
                $term->slug,
                $has_path
            );
        }

        if ( empty( $lines ) ) {
            return '- این شناسه نه نوشته است، نه ترم.';
        }

        if ( count( $lines ) === 2 ) {
            $lines[] = '';
            $lines[] = 'هر دو وجود دارند، پس شناسه عددی مبهم است. از آدرس نامکی استفاده کنید.';
        }

        return implode( "\n", $lines );
    }

    /**
     * آدرس HTML یک موجودیت، یا رشته خالی اگر آدرس عمومی نداشته باشد.
     */
    public static function get_entity_permalink( int $id, string $type ): string {

        if ( 'post' === $type ) {
            if ( ! self::post_is_addressable( $id ) ) {
                return '';
            }
            $link = get_permalink( $id );
        } else {
            if ( ! self::term_is_addressable( $id ) ) {
                return '';
            }
            $link = get_term_link( $id );
        }

        if ( is_wp_error( $link ) || empty( $link ) ) {
            return '';
        }

        return class_exists( 'Hodima_Core_Helpers' )
            ? Hodima_Core_Helpers::clean_url( (string) $link )
            : (string) $link;
    }

    /**
     * ورودی می‌تواند آدرس کامل، مسیر، یا فقط نامک باشد.
     * خروجی همیشه مسیر نسبی بدون اسلش ابتدایی/انتهایی و بدون بخش زبان.
     */
    private static function normalize_identifier( string $identifier ): string {

        $identifier = trim( $identifier );

        if ( $identifier === '' ) {
            return '';
        }

        if ( str_contains( $identifier, '://' ) ) {
            $identifier = (string) wp_parse_url( $identifier, PHP_URL_PATH );
        }

        $home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
        $identifier = trim( $identifier, '/' );

        if ( $home_path !== '' && str_starts_with( $identifier, $home_path . '/' ) ) {
            $identifier = substr( $identifier, strlen( $home_path ) + 1 );
        }

        $identifier = (string) preg_replace( '#^(fa|en)/#i', '', $identifier );
        $identifier = (string) preg_replace( '#\.md$#i', '', $identifier );

        if ( function_exists( 'arian_strip_leading_base' ) ) {
            $full = home_url( '/' . $identifier );
            foreach ( [ 'arian_product_base', 'arian_product_cat_base', 'arian_category_base' ] as $base_fn ) {
                if ( function_exists( $base_fn ) ) {
                    $full = arian_strip_leading_base( $full, $base_fn() );
                }
            }
            $identifier = trim( (string) wp_parse_url( $full, PHP_URL_PATH ), '/' );
            if ( $home_path !== '' && str_starts_with( $identifier, $home_path . '/' ) ) {
                $identifier = substr( $identifier, strlen( $home_path ) + 1 );
            }
        }

        return trim( $identifier, '/' );
    }

    /**
     * تبدیل خروجی arian_resolve_path() به جفت (شناسه، نوع).
     */
    private static function map_router_vars( array $vars, string &$type ): int {

        if ( empty( $vars ) ) {
            return 0;
        }

        if ( isset( $vars['page_id'] ) ) {
            $type = 'post';
            return (int) $vars['page_id'];
        }

        foreach ( [ 'product_cat' => 'product_cat', 'category_name' => 'category' ] as $var => $taxonomy ) {
            if ( isset( $vars[ $var ] ) ) {
                $term_id = self::term_id_by_slug( (string) $vars[ $var ], $taxonomy );
                if ( $term_id ) {
                    $type = 'term';
                    return $term_id;
                }
            }
        }

        if ( isset( $vars['name'], $vars['post_type'] ) ) {
            $post_id = self::post_id_by_slug( (string) $vars['name'], (string) $vars['post_type'] );
            if ( $post_id ) {
                $type = 'post';
                return $post_id;
            }
        }

        return 0;
    }

    /** شناسه پست از روی نامک، با امتحان هر سه شکل. */
    private static function post_id_by_slug( string $slug, string $post_type ): int {

        global $wpdb;

        if ( $slug === '' ) {
            return 0;
        }

        $decoded = rawurldecode( $slug );
        $encoded = strtolower( rawurlencode( $decoded ) );

        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_name IN (%s, %s, %s) AND post_type = %s
               AND post_status IN ('publish','private')
             LIMIT 1",
            $slug, $decoded, $encoded, $post_type
        ) );

        return (int) $id;
    }

    /** شناسه ترم از روی نامک، با امتحان هر سه شکل. */
    private static function term_id_by_slug( string $slug, string $taxonomy ): int {

        global $wpdb;

        if ( $slug === '' ) {
            return 0;
        }

        $decoded = rawurldecode( $slug );
        $encoded = strtolower( rawurlencode( $decoded ) );

        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT t.term_id FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE t.slug IN (%s, %s, %s) AND tt.taxonomy = %s
             LIMIT 1",
            $slug, $decoded, $encoded, $taxonomy
        ) );

        return (int) $id;
    }

    /**
     * فالبک: وقتی روتر قالب در دسترس نیست.
     */
    private static function resolve_by_leaf_slug( string $leaf, string &$type ): int {

        global $wpdb;

        if ( $leaf === '' ) {
            return 0;
        }

        $raw     = $leaf;
        $decoded = rawurldecode( $leaf );
        $encoded = strtolower( rawurlencode( $decoded ) );

        $rows = $wpdb->get_results( $wpdb->prepare( "
            SELECT 'term' AS kind, t.term_id AS id, tt.taxonomy AS subtype
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE t.slug IN (%s, %s, %s) AND tt.taxonomy IN ('product_cat','category')
            UNION ALL
            SELECT 'post' AS kind, p.ID AS id, p.post_type AS subtype
            FROM {$wpdb->posts} p
            WHERE p.post_name IN (%s, %s, %s) AND p.post_status = 'publish' AND p.post_type IN ('product','page','post')
        ", $raw, $decoded, $encoded, $raw, $decoded, $encoded ) );

        if ( empty( $rows ) ) {
            return 0;
        }

        $priority = [ 'product_cat' => 1, 'product' => 2, 'category' => 3, 'page' => 4, 'post' => 5 ];

        usort( $rows, static function ( $a, $b ) use ( $priority ) {
            return ( $priority[ $a->subtype ] ?? 99 ) <=> ( $priority[ $b->subtype ] ?? 99 );
        } );

        $type = ( $rows[0]->kind === 'term' ) ? 'term' : 'post';

        return (int) $rows[0]->id;
    }

    public static function get_iso8601_local_time( string $gmt_time = '' ): string {
        $timestamp = (!empty($gmt_time) && $gmt_time !== '0000-00-00 00:00:00') ? (int) strtotime($gmt_time) : time();
        $offset_h  = (float) get_option('gmt_offset');
        $local_ts  = $timestamp + (int) ($offset_h * 3600);
        $tz_sign   = ($offset_h < 0) ? '-' : '+';
        $tz_h      = str_pad( (string) floor(abs($offset_h)), 2, '0', STR_PAD_LEFT );
        $tz_m      = str_pad( (string) ((abs($offset_h) - floor(abs($offset_h))) * 60), 2, '0', STR_PAD_LEFT );
        return gmdate('Y-m-d\TH:i:s', $local_ts) . $tz_sign . $tz_h . ':' . $tz_m;
    }

    public static function get_commerce_data( int $id ): array {
        if ( ! function_exists( 'wc_get_product' ) ) return [];
        $product = wc_get_product( $id );
        if ( ! $product ) return [];

        return [
            'price'    => html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' ),
            'status_fa'=> $product->is_in_stock() ? 'موجود در انبار' : 'ناموجود',
            'status_en'=> $product->is_in_stock() ? 'In Stock' : 'Out of Stock',
            'rating'   => $product->get_average_rating(),
            'reviews'  => $product->get_review_count(),
        ];
    }

    public static function get_product_attributes_table( int $id ): string {
        if ( ! function_exists( 'wc_get_product' ) ) return '';
        $product = wc_get_product( $id );
        if ( ! $product ) return '';

        $attributes = $product->get_attributes();
        if ( empty( $attributes ) ) return '';

        $md = "| Feature | Value (مقدار) |\n|---|---|\n";
        foreach ( $attributes as $attr ) {
            $name = wc_attribute_label( $attr->get_name() );
            $val  = '';
            if ( $attr->is_taxonomy() ) {
                $terms = wc_get_product_terms( $id, $attr->get_name(), [ 'fields' => 'names' ] );
                if ( ! is_wp_error( $terms ) && is_array( $terms ) ) $val = implode( '، ', $terms );
            } else {
                $opts = $attr->get_options();
                if ( is_array( $opts ) ) $val = implode( '، ', $opts );
            }
            $md .= "| {$name} | {$val} |\n";
        }
        return $md . "\n";
    }
    
    public static function get_product_gallery_content( int $id, string $en_title, string $fa_title ): string {
        if ( ! function_exists( 'wc_get_product' ) ) return '';
        $product = wc_get_product( $id );
        if ( ! $product ) return '';

        $gallery_ids = $product->get_gallery_image_ids();
        $main_id     = $product->get_image_id();
        if ( $main_id ) array_unshift( $gallery_ids, $main_id );
        $gallery_ids = array_filter( array_unique( $gallery_ids ) );

        if ( empty( $gallery_ids ) ) return '';

        $md = "";
        $fallback_title = $en_title . ' | ' . $fa_title;

        foreach ( $gallery_ids as $gid ) {
            $img_url = wp_get_attachment_image_url( $gid, 'full' );
            if ( $img_url ) {
                $alt = get_post_meta( $gid, '_wp_attachment_image_alt', true );
                $alt_str = $alt ? Hodima_Core_Helpers::anti_injection_shield( (string) $alt ) : $fallback_title;
                $md .= "![{$alt_str}]({$img_url})\n";
            }
        }
        return $md . "\n";
    }

    public static function get_native_wc_clusters_data( int $id, string $post_type ): array {
        $clusters = [];
        $data_array = [];
        
        if ( $post_type === 'product' && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $id );
            if ( $product ) {
                $manual_ids = array_unique( array_merge( $product->get_upsell_ids(), $product->get_cross_sell_ids() ) );
                if ( ! empty( $manual_ids ) ) {
                    $clusters = get_posts( [ 'post_type' => 'product', 'post__in' => $manual_ids, 'posts_per_page' => 5 ] );
                } else {
                    $related_ids = wc_get_related_products( $id, 5 );
                    if ( ! empty( $related_ids ) ) {
                        $clusters = get_posts( [ 'post_type' => 'product', 'post__in' => $related_ids, 'posts_per_page' => 5 ] );
                    }
                }
            }
        } else {
            $tax   = 'category';
            $terms = wp_get_post_terms( $id, $tax, [ 'fields' => 'ids' ] );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $clusters = get_posts( [ 'post_type' => $post_type, 'posts_per_page' => 5, 'post__not_in' => [ $id ], 'tax_query' => [ [ 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $terms ] ] ] );
            }
        }
        
        foreach ( $clusters as $c ) {
            $md_url = untrailingslashit( Hodima_Core_Helpers::clean_url( (string) get_permalink( $c->ID ) ) ) . '.md';
            $data_array[] = [ 'title' => $c->post_title, 'url' => $md_url ];
        }
        
        return $data_array;
    }
}