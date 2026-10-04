<?php
/**
 * HOOK BREADCRUMB SCHEMA - DEEP HIERARCHY EDITION (BUG FIXED & SECURED)
 */
if (!defined('ABSPATH')) exit;

add_action('wp_head', 'hook_generate_breadcrumb_schema', 99);

/* =====================================================================
 * خاموش کردن BreadcrumbList خود ووکامرس
 * ---------------------------------------------------------------------
 * این ماژول روی هر صفحه‌ای به جز خانه یک BreadcrumbList کامل با @id
 * تولید می‌کند. ووکامرس هم مستقل از آن، نسخه خودش را در فوتر چاپ
 * می‌کند — یعنی دو مسیر راهنمای متفاوت روی هر صفحه محصول و دسته‌بندی.
 * گوگل در این حالت گاهی یکی را می‌پذیرد و گاهی هر دو را نامعتبر
 * می‌داند؛ نتیجه‌اش نوسان تعداد BreadcrumbList در سرچ کنسول است.
 *
 * نکته: هر دو املای فیلتر ثبت می‌شود.
 * کد قبلی قالب «breadcrumlist» نوشته بود (بدون b دوم) در حالی که نام
 * واقعی ووکامرس «breadcrumblist» است. چون نسخه‌های ووکامرس را از اینجا
 * نمی‌توان بررسی کرد، هر دو ثبت می‌شوند؛ آن‌که وجود ندارد بی‌اثر است.
 * ===================================================================== */
add_action( 'wp', 'hodima_disable_woo_breadcrumb_schema' );

function hodima_disable_woo_breadcrumb_schema() {

    if ( get_option( 'hodima_breadcrumb_schema_status', 'on' ) !== 'on' ) {
        return; // ماژول ما خاموش است → بردکرامب ووکامرس باید بماند
    }

    add_filter( 'woocommerce_structured_data_breadcrumblist', '__return_empty_array' );
    add_filter( 'woocommerce_structured_data_breadcrumlist', '__return_empty_array' );
}


/* =====================================================================
 * دسته‌هایی که دسته اصلی مسیر راهنما نمی‌شوند
 * ---------------------------------------------------------------------
 * دسته‌های عمومی مثل «جدیدترین محصولات» (latest-products) به محصول ربطی
 * موضوعی ندارند؛ اگر ووکامرس یا این ماژول آن را دسته اصلی انتخاب کند، مسیر
 * «خانه › جدیدترین محصولات › کش مو» ساخته می‌شود. قبلا فقط مسیر قابل‌مشاهده
 * ووکامرس با فیلتری در قالب (inc/enqueue.php، نامک ثابت) اصلاح می‌شد و اسکیما
 * همچنان می‌توانست همان دسته را بگیرد. حالا هر دو از یک فهرست می‌خوانند:
 * «اسکیما ← بردکرامب». دسته اصلی که دستی انتخاب شده (_hodima_primary_*) دست
 * نمی‌خورد.
 * ===================================================================== */
const HODIMA_BREADCRUMB_EXCLUDE_OPTION = 'hodima_breadcrumb_exclude_slugs';

/** نامک دسته‌های مستثنا (پیش‌فرض: latest-products، همان رفتار قبلی قالب). */
function hodima_breadcrumb_excluded_slugs(): array {
    $stored = get_option( HODIMA_BREADCRUMB_EXCLUDE_OPTION, false );
    return array_values( array_filter( array_map( 'sanitize_title', (array) ( is_array( $stored ) ? $stored : [ 'latest-products' ] ) ) ) );
}

/** دسته‌های غیرمستثنا؛ اگر همه مستثنا باشند، همان فهرست اصلی. */
function hodima_breadcrumb_filter_terms( array $terms ): array {
    $excluded = hodima_breadcrumb_excluded_slugs();
    $kept     = array_values( array_filter( $terms, static fn( $t ): bool => $t instanceof WP_Term && ! in_array( $t->slug, $excluded, true ) ) );
    return $kept ?: $terms;
}

add_filter( 'woocommerce_breadcrumb_main_term', 'hodima_seo_breadcrumb_main_term', 10, 2 );

/** مسیر راهنمای قابل‌مشاهده ووکامرس. */
function hodima_seo_breadcrumb_main_term( $main_term, $terms ) {

    // قالب قبل از 2.3.0 همین فیلتر را خودش دارد
    if ( function_exists( 'hodima_theme_has_legacy_logic' ) && hodima_theme_has_legacy_logic() ) {
        return $main_term;
    }

    if ( $main_term instanceof WP_Term && in_array( $main_term->slug, hodima_breadcrumb_excluded_slugs(), true ) ) {
        return hodima_breadcrumb_filter_terms( (array) $terms )[0] ?? $main_term;
    }

    return $main_term;
}

/**
 * آیتم‌های مسیر راهنمای صفحه جاری (یا آرایه خالی).
 *
 * از تابع چاپ جدا شد تا homepage-schema.php — که *قبل از* این فایل اجرا
 * می‌شود — بتواند بداند بردکرامبی وجود خواهد داشت و از نود صفحه به آن
 * ارجاع دهد (WebPage.breadcrumb). یک بار محاسبه و کش می‌شود.
 */
function hodima_breadcrumb_items(): array {

    static $memo = null;
    if ( null !== $memo ) return $memo;
    $memo = [];

    if ( get_option('hodima_breadcrumb_schema_status', 'on') !== 'on' ) return $memo;
    if (is_home() || is_front_page() || is_404() || is_search()) return $memo;

    $items = [];
    $pos = 1;
    $site_url = trailingslashit(home_url());
    $home_label = sanitize_text_field(get_option('hodima_breadcrumb_home_label', 'خانه'));

    // آدرس‌ها با esc_url_raw (نه esc_url): esc_url برای HTML است و «&» را به
    // «&#038;» تبدیل می‌کند که داخل JSON آدرس خراب است.
    $items[] = [
        '@type'    => 'ListItem',
        'position' => $pos++,
        'name'     => $home_label,
        'item'     => $site_url
    ];

    $add_term_ancestors = function($term_id, $taxonomy) use (&$items, &$pos) {
        $ancestors = get_ancestors($term_id, $taxonomy);
        if (!empty($ancestors)) {
            $ancestors = array_reverse($ancestors);
            foreach ($ancestors as $ancestor_id) {
                $anc_term = get_term($ancestor_id, $taxonomy);
                if ($anc_term && !is_wp_error($anc_term)) {
                    $link = get_term_link($anc_term);
                    if (!is_wp_error($link)) {
                        $items[] = [
                            '@type'    => 'ListItem',
                            'position' => $pos++,
                            'name'     => wp_strip_all_tags($anc_term->name),
                            'item'     => esc_url_raw($link)
                        ];
                    }
                }
            }
        }
    };

    $add_post_ancestors = function($post_id) use (&$items, &$pos) {
        $ancestors = get_post_ancestors($post_id);
        if (!empty($ancestors)) {
            $ancestors = array_reverse($ancestors);
            foreach ($ancestors as $ancestor_id) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => wp_strip_all_tags(get_the_title($ancestor_id)),
                    'item'     => esc_url_raw(get_permalink($ancestor_id))
                ];
            }
        }
    };

    $get_primary_term = function($post_id, $taxonomy) {
        // دسته اصلی: «_hodima_primary_{taxonomy}» — همان انتخاب‌های قبلی Rank Math که
        // پاکسازی یک‌باره (schema-cleaner.php) پیش از حذف داده‌های Rank Math به
        // این کلید منتقل می‌کند؛ پس مسیر راهنمای محصولات عوض نمی‌شود.
        $primary_id = get_post_meta($post_id, "_hodima_primary_{$taxonomy}", true) ?: get_post_meta($post_id, "_yoast_wpseo_primary_{$taxonomy}", true);
        if ($primary_id) {
            $term = get_term($primary_id, $taxonomy);
            if ($term && !is_wp_error($term)) return $term;
        }
        
        $terms = get_the_terms($post_id, $taxonomy);
        if (!empty($terms) && !is_wp_error($terms)) {
            // همان دسته‌های مستثنای مسیر راهنمای ووکامرس (مثل «جدیدترین محصولات»)
            $terms     = hodima_breadcrumb_filter_terms( $terms );
            $main_term = $terms[0];
            $max_depth = -1;
            foreach ($terms as $t) {
                $depth = count(get_ancestors($t->term_id, $taxonomy));
                if ($depth > $max_depth) {
                    $max_depth = $depth;
                    $main_term = $t;
                }
            }
            return $main_term;
        }
        return false;
    };

    if (function_exists('is_product') && is_product()) {
        $product_id = get_the_ID();
        if ( function_exists('wc_get_product_terms') ) {
            $main_term = $get_primary_term($product_id, 'product_cat');
            if ($main_term) {
                $add_term_ancestors($main_term->term_id, 'product_cat');
                $link = get_term_link($main_term);
                if (!is_wp_error($link)) {
                    $items[] = [
                        '@type'    => 'ListItem',
                        'position' => $pos++,
                        'name'     => wp_strip_all_tags($main_term->name),
                        'item'     => esc_url_raw($link)
                    ];
                }
            }
        }
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => wp_strip_all_tags(get_the_title()),
            'item'     => esc_url_raw(get_permalink())
        ];
    } 
    elseif (is_tax('product_cat') || is_category()) {
        $term = get_queried_object();
        $add_term_ancestors($term->term_id, $term->taxonomy);
        $link = get_term_link($term);
        if (!is_wp_error($link)) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => wp_strip_all_tags($term->name),
                'item'     => esc_url_raw($link)
            ];
        }
    } 
    elseif (is_single() && 'post' === get_post_type()) {
        $main_cat = $get_primary_term(get_the_ID(), 'category');
        if ($main_cat) {
            $add_term_ancestors($main_cat->term_id, 'category');
            $link = get_category_link($main_cat->term_id);
            if (!is_wp_error($link)) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => wp_strip_all_tags($main_cat->name),
                    'item'     => esc_url_raw($link)
                ];
            }
        }
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => wp_strip_all_tags(get_the_title()),
            'item'     => esc_url_raw(get_permalink())
        ];
    }
    elseif ( (function_exists('is_shop') && is_shop()) || is_post_type_archive() ) {
        $post_type = get_query_var('post_type');
        $obj = get_post_type_object(is_array($post_type) ? reset($post_type) : $post_type);
        if ($obj) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => wp_strip_all_tags($obj->labels->name),
                'item'     => esc_url_raw(get_post_type_archive_link($obj->name))
            ];
        }
    }
    elseif (is_page()) {
        global $post;
        if ($post->post_parent) {
            $add_post_ancestors($post->ID); 
        }
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => wp_strip_all_tags(get_the_title()),
            'item'     => esc_url_raw(get_permalink())
        ];
    }
    elseif (is_tag()) {
        $term = get_queried_object();
        if ($term) {
            $link = get_term_link($term);
            if (!is_wp_error($link)) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => wp_strip_all_tags($term->name),
                    'item'     => esc_url_raw($link)
                ];
            }
        }
    }
    // حالت رفع‌شده: قبلاً هیچ شاخه‌ای برای آرشیو تکسونومی‌های سفارشی
    // (مثلاً یک taxonomy اختصاصی محصول غیر از product_cat) وجود نداشت.
    // نتیجه این بود که breadcrumb-schema.php برای این صفحات هیچ خروجی
    // تولید نمی‌کرد و این صفحات کلاً بدون breadcrumb schema می‌ماندند.
    elseif (is_tax()) {
        $term = get_queried_object();
        if ($term && isset($term->taxonomy)) {
            $add_term_ancestors($term->term_id, $term->taxonomy);
            $link = get_term_link($term);
            if (!is_wp_error($link)) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => wp_strip_all_tags($term->name),
                    'item'     => esc_url_raw($link)
                ];
            }
        }
    }
    elseif (is_singular()) {
        $obj_id = get_the_ID();
        $allowed_taxes = ['product_cat', 'category']; 
        foreach ($allowed_taxes as $tax) {
            if (taxonomy_exists($tax)) {
                $main_term = $get_primary_term($obj_id, $tax);
                if ($main_term) {
                    $add_term_ancestors($main_term->term_id, $tax);
                    $link = get_term_link($main_term);
                    if (!is_wp_error($link)) {
                        $items[] = [
                            '@type'    => 'ListItem',
                            'position' => $pos++,
                            'name'     => wp_strip_all_tags($main_term->name),
                            'item'     => esc_url_raw($link)
                        ];
                    }
                    break;
                }
            }
        }
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => wp_strip_all_tags(get_the_title()),
            'item'     => esc_url_raw(get_permalink())
        ];
    }

    /*
     * نقطه توسعه: ماژول‌هایی که مسیر خاص خودشان را دارند (مثل صفحه ویدیو
     * که پله «ویدئوها» را لازم دارد) آیتم‌ها را اینجا تکمیل می‌کنند، به جای
     * چاپ یک BreadcrumbList موازی با همان شناسه.
     */
    $items = (array) apply_filters( 'hodima_breadcrumb_items', $items );

    if (empty($items) || count($items) < 2) return $memo;

    return $memo = $items;
}

/** شناسه بردکرامب — از همان آدرسی که نود «#webpage» می‌سازد. */
function hodima_breadcrumb_id(): string {

    $base = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';

    if ( '' === $base ) {
        $items = hodima_breadcrumb_items();
        $last  = end( $items );
        $base  = ! empty( $last['item'] ) ? trailingslashit( (string) $last['item'] ) : trailingslashit( home_url() );
    }

    return $base . '#breadcrumb';
}

/** WebPage.breadcrumb → #breadcrumb */
add_filter( 'hodima_schema_webpage_node', static function ( array $node, string $page_url ): array {
    if ( ! empty( hodima_breadcrumb_items() ) ) {
        $node['breadcrumb'] = [ '@id' => hodima_breadcrumb_id() ];
    }
    return $node;
}, 10, 2 );

function hook_generate_breadcrumb_schema() {

    $items = hodima_breadcrumb_items();

    if ( empty( $items ) ) return;

    hodima_schema_add( [
        '@type'           => 'BreadcrumbList',
        '@id'             => hodima_breadcrumb_id(),
        'itemListElement' => $items
    ], 'hodima-seo: breadcrumb-schema' );
}