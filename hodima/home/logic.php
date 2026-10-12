<?php
/**
 * مدیریت یکپارچه، هوشمند و اتوماتیک سکشن‌های صفحه اصلی هدهدلی
 * فایل: logic.php
 * عملکرد: 
 * ۱. موتور رندر اسلایدر بهینه
 * ۲. تبدیل خودکار دسته‌بندی‌ها به شورت‌کد (مثل [plasco])
 * ۳. پشتیبانی از فایل‌های فیزیکی پوشه home با شورت‌کدهای [section01] الی [section20]
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit;

// ========================================================================
// ۰. اولویت بارگذاری تصویر بالای صفحه (LCP)
// ========================================================================
if ( ! function_exists( 'hodima_home_claim_priority' ) ) {
    /**
     * فقط «اولین» تصویر صفحه اصلی fetchpriority=high بگیرد.
     * باگ قبلی: اولین تصویر هر اسلایدر و مقالات این را می‌گرفت؛ چند تصویر (حتی زیر
     * صفحه) هم‌زمان «فوری» می‌شد و اولین تصویر مهم (LCP) کند می‌شد. هر بخش پیش از چاپ
     * تصویر اولش این تابع را صدا می‌زند؛ فقط اولین فراخوانی در هر درخواست true می‌گیرد.
     */
    function hodima_home_claim_priority(): bool {
        static $claimed = false;

        if ( $claimed ) {
            return false;
        }

        $claimed = true;

        return true;
    }
}

// ========================================================================
// ۰.۱ کارت و باکس مشترک بخش‌های افقی صفحه اصلی (محصولات یک دسته، جدیدترین، آخرین مقالات)
// ------------------------------------------------------------------------
// تا 3.1.4 مقالات مارک‌آپ و CSS جدای خودشان را داشتند (modern-blog-card، blog-wrapper…)
// و کوچک‌تر از کارت محصول درمی‌آمدند. حالا هر دو همین دو تابع و همین CSS (.arian-card،
// .arian-product-section) را به کار می‌برند؛ تفاوت مقاله فقط نبود قیمت است.
// ========================================================================
if ( ! function_exists( 'hodima_home_card_html' ) ) {
    /**
     * یک کارت: تصویر مربع (برش مرکزی)، عنوان دو خطی و (اختیاری) قیمت.
     *
     * @param array{link:string, title:string, thumbnail_id?:int, price_html?:string, first?:bool, class?:string, size?:string, placeholder?:string} $card
     *   first = اولین کارت بخش (می‌تواند اولویت بارگذاری تصویر بالای صفحه را بگیرد)،
     *   class = کلاس اضافه روی article، placeholder = آدرس تصویر جایگزین (خالی ← آیکون برند).
     */
    function hodima_home_card_html( array $card ): string {

        $card = wp_parse_args( $card, [
            'thumbnail_id' => 0,
            'price_html'   => '',
            'first'        => false,
            'class'        => '',
            'size'         => 'medium_large',
            'placeholder'  => '',
        ] );

        $title = (string) $card['title'];
        $link  = (string) $card['link'];

        if ( ! empty( $card['thumbnail_id'] ) ) {
            $image_attrs = [
                'alt'      => $title,
                'decoding' => 'async',
                'class'    => 'arian-card-img',
                // کارت ۱۷۰px (در موبایل ۴۰٪ عرض)؛ بدون این، مرورگر نسخه‌ای به اندازه تمام صفحه می‌گرفت
                'sizes'    => '(max-width: 768px) 40vw, 170px',
            ];
            if ( $card['first'] && hodima_home_claim_priority() ) {
                $image_attrs['fetchpriority'] = 'high';
            } else {
                $image_attrs['loading'] = 'lazy';
            }
            $image = wp_get_attachment_image( (int) $card['thumbnail_id'], (string) $card['size'], false, $image_attrs );
        } elseif ( '' !== $card['placeholder'] ) {
            $image = '<img src="' . esc_url( (string) $card['placeholder'] ) . '" alt="' . esc_attr( $title ) . '" width="300" height="300" loading="lazy" decoding="async" class="arian-card-img">';
        } else {
            // بدون تصویر: کادر جایگزین با رنگ برند (فایل جایگزین در قالب نیست)
            $image = '<span class="arian-card-img arian-card-img--placeholder" role="img" aria-label="' . esc_attr( $title ) . '"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span>';
        }

        $classes = trim( 'arian-card ' . (string) $card['class'] );
        $price   = '' !== (string) $card['price_html'] ? '<div class="arian-card-price">' . wp_kses_post( (string) $card['price_html'] ) . '</div>' : '';

        return '<article class="' . esc_attr( $classes ) . '">'
            . '<a href="' . esc_url( $link ) . '" class="arian-card-img-link">' . $image . '</a>'
            . '<div class="arian-card-info"><h3 class="arian-card-name"><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>' . $price . '</div>'
            . '</article>';
    }
}

if ( ! function_exists( 'hodima_home_cards_section' ) ) {
    /**
     * باکس بخش: سرتیتر (با خط گرادیان و لینک «مشاهده همه»)، و اسکرولر افقی کارت‌ها.
     *
     * @param array{id:string, title:string, title_url?:string, more_url?:string, more_text?:string, bg_color?:string, scroller_class?:string} $args
     * @param string $cards_html خروجی hodima_home_card_html() (هر کدام escape شده)
     */
    function hodima_home_cards_section( array $args, string $cards_html ): string {

        $args = wp_parse_args( $args, [
            'title_url'      => '',
            'more_url'       => '',
            'more_text'      => 'مشاهده همه',
            'bg_color'       => 'transparent',
            'scroller_class' => '',
        ] );

        $title = esc_html( (string) $args['title'] );
        if ( '' !== (string) $args['title_url'] ) {
            $title = '<a class="arian-title-link" href="' . esc_url( (string) $args['title_url'] ) . '">' . $title . '</a>';
        }

        $more = '' !== (string) $args['more_url'] && '' !== (string) $args['more_text']
            ? '<a href="' . esc_url( (string) $args['more_url'] ) . '" class="arian-view-all">' . esc_html( (string) $args['more_text'] ) . '</a>'
            : '';

        return '<section class="arian-section arian-product-section" aria-labelledby="' . esc_attr( (string) $args['id'] ) . '" style="--section-bg: ' . esc_attr( (string) $args['bg_color'] ) . ';">'
            . '<div class="arian-header"><div class="arian-title-group"><h2 id="' . esc_attr( (string) $args['id'] ) . '" class="arian-title">' . $title . '</h2><div class="arian-line"></div></div>' . $more . '</div>'
            . '<div class="' . esc_attr( trim( 'arian-scroller ' . (string) $args['scroller_class'] ) ) . '">' . $cards_html . '</div>'
            . '</section>';
    }
}

// ========================================================================
// ۱. تابع مرکزی اسلایدر محصولات (موتور رندر)
// ========================================================================
if ( ! function_exists( 'hodima_home_product_slider' ) ) {
    /**
     * @param array<string, mixed> $args title، link، category، limit، bg_color، id.
     */
    function hodima_home_product_slider( array $args = [] ): string {
        
        if ( ! function_exists( 'wc_get_products' ) ) {
            return ''; 
        }

        $defaults = [
            'title'    => 'محصولات',
            'link'     => '',
            'category' => '',
            'limit'    => 10,
            'bg_color' => 'transparent',
            // شناسه ثابت در هر بار ساخت صفحه (قبلا uniqid: HTML صفحه اصلی هر بار فرق می‌کرد)
            'id'       => wp_unique_id( 'arian-sec-' ),
        ];
        $args = wp_parse_args( $args, $defaults );

        // نامک/مسیر داخلی (شورت‌کدهای قدیمی) یا آدرس کامل (چیدمان صفحه اصلی: لینک واقعی دسته)
        $clean_slug = trim( (string) $args['link'], '/' );
        $final_link = match ( true ) {
            '' === $clean_slug                             => esc_url( home_url( '/' ) ),
            (bool) preg_match( '#^https?://#i', $clean_slug ) => esc_url( (string) $args['link'] ),
            default                                        => esc_url( home_url( '/' . $clean_slug . '/' ) ),
        };

        $query_args = [
            'status'       => 'publish',
            'limit'        => $args['limit'],
            'orderby'      => 'date',
            'order'        => 'DESC',
            'stock_status' => 'instock',
        ];

        if ( ! empty( $args['category'] ) ) {
            $query_args['category'] = (array) $args['category'];
        }

        // نسل کش در کلید: پاک کردن = بالا بردن نسل (hodima_home_flush_product_sliders)
        $transient_key = 'arian_pslider_' . md5( (string) wp_json_encode( $query_args ) . '|' . hodima_home_slider_generation() );
        $products_data = get_transient( $transient_key );

        if ( false === $products_data ) {
            $product_objects = wc_get_products( $query_args );
            $products_data   = [];

            if ( ! empty( $product_objects ) ) {
                foreach ( $product_objects as $product ) {
                    $products_data[] = [
                        'name'         => $product->get_name(),
                        'link'         => $product->get_permalink(),
                        'price_html'   => $product->get_price_html(),
                        'thumbnail_id' => $product->get_image_id(),
                    ];
                }
            }
            
            set_transient( $transient_key, $products_data, 10 * MINUTE_IN_SECONDS );
        }

        if ( empty( $products_data ) ) {
            return '';
        }

        $cards = '';

        foreach ( $products_data as $index => $product ) {
            $cards .= hodima_home_card_html( [
                'link'         => $product['link'],
                'title'        => $product['name'],
                'thumbnail_id' => (int) $product['thumbnail_id'],
                'price_html'   => $product['price_html'],
                'first'        => 0 === $index,
                'class'        => 'product-card',
                'size'         => 'woocommerce_thumbnail',
                'placeholder'  => wc_placeholder_img_src( 'woocommerce_thumbnail' ),
            ] );
        }

        return hodima_home_cards_section( [
            'id'            => (string) $args['id'],
            'title'         => (string) $args['title'],
            'more_url'      => '' !== $clean_slug ? $final_link : '',
            'more_text'     => 'مشاهده همه',
            'bg_color'      => (string) $args['bg_color'],
            'scroller_class' => 'product-wrapper',
        ], $cards );
    }
}

// ========================================================================
// ۱.۱ داده بخش‌های «دسته‌بندی کالاها» و «آخرین مقالات» (با کش)
// ------------------------------------------------------------------------
// مشترک بین چیدمان «تنظیمات قالب ← صفحه اصلی» و شورت‌کدهای قدیمی
// [section03] و [section09] (home/parts/*.php). دسته‌های مستثنا و تعداد
// مقاله‌ها هنگام نمایش اعمال می‌شوند تا یک کش برای همه تنظیمات کافی باشد؛
// کش با تغییر دسته/نوشته پاک می‌شود (پایین همین فایل).
// ========================================================================

/**
 * همه دسته‌های محصول دارای کالا: نامک، نام، لینک، شناسه تصویر.
 *
 * @return list<array{slug: string, name: string, link: string, thumbnail_id: int}>
 */
function hodima_home_categories_data(): array {

    $data = get_transient( 'hodima_home_categories_v5' );

    if ( is_array( $data ) ) {
        return $data;
    }

    $data  = [];
    $terms = taxonomy_exists( 'product_cat' ) ? get_terms( [
        'taxonomy'               => 'product_cat',
        'hide_empty'             => true,
        'update_term_meta_cache' => true,
    ] ) : [];

    if ( is_array( $terms ) ) {
        foreach ( $terms as $term ) {
            $link = get_term_link( $term );
            if ( is_wp_error( $link ) ) {
                continue;
            }
            $data[] = [
                'slug'         => $term->slug,
                'name'         => $term->name,
                'link'         => $link,
                'thumbnail_id' => (int) get_term_meta( $term->term_id, 'thumbnail_id', true ),
            ];
        }
    }

    set_transient( 'hodima_home_categories_v5', $data, WEEK_IN_SECONDS );

    return $data;
}

/**
 * ۲۰ مقاله آخر: عنوان، لینک، شناسه تصویر شاخص (۱۵ دقیقه کش).
 *
 * @return list<array{title: string, link: string, thumbnail_id: int}>
 */
function hodima_home_blog_posts_data(): array {

    $data = get_transient( 'hodima_home_blog_posts_v3' );

    if ( is_array( $data ) ) {
        return $data;
    }

    $data = [];

    foreach ( get_posts( [
        'post_type'      => 'post',
        'posts_per_page' => 20,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ] ) as $post_item ) {
        $data[] = [
            'title'        => $post_item->post_title,
            'link'         => get_permalink( $post_item->ID ),
            'thumbnail_id' => (int) get_post_thumbnail_id( $post_item->ID ),
        ];
    }

    set_transient( 'hodima_home_blog_posts_v3', $data, 15 * MINUTE_IN_SECONDS );

    return $data;
}

// ========================================================================
// ۲. تابع لود سکشن‌های فیزیکی (برای section01 تا section20)
// ========================================================================
if ( ! function_exists( 'hodima_home_load_section' ) ) {
    function hodima_home_load_section( string $tag ): string {
        $tag = sanitize_key( $tag );
        if ( empty( $tag ) ) return '';

        $php_file = get_stylesheet_directory() . "/home/{$tag}.php";

        if ( ! file_exists( $php_file ) ) {
            return "<!-- خطای هدیما: فایل {$tag}.php در مسیر {$php_file} پیدا نشد -->";
        }

        ob_start();
        include $php_file;
        return trim( (string) ob_get_clean() ); // strict_types: ob_get_clean ممکن است false باشد
    }
}

// ========================================================================
// ۳. ثبت تمامی شورت‌کدها در یک هوک یکپارچه
// ========================================================================
/**
 * فهرست دسته‌بندی‌های محصول برای ثبت شورت‌کد، با کش.
 *
 * نسخه قبلی get_terms() را مستقیما داخل هوک init صدا می‌زد. init روی
 * *هر* درخواست اجرا می‌شود — فرانت، پنل مدیریت، admin-ajax و کرون —
 * یعنی یک کوئری روی جدول ترم‌ها در هر بارگذاری صفحه، فقط برای اینکه
 * چند شورت‌کد ثبت شود که شاید در آن صفحه اصلا استفاده نشوند.
 *
 * هوک‌های create_term / edit_term / delete_term از قبل در همین فایل
 * وجود دارند و حالا این کش را هم باطل می‌کنند.
 */
/** @return array<string, string> نامک => نام دسته */
function hodima_home_category_shortcode_map(): array {

    $cached = get_transient( 'arian_cat_shortcode_map' );

    if ( is_array( $cached ) ) {
        return $cached;
    }

    $map = [];

    if ( taxonomy_exists( 'product_cat' ) ) {

        // fields پیش‌فرض شیء کامل WP_Term برمی‌گرداند که هم slug دارد هم name،
        // پس نیازی به get_term() داخل حلقه نیست (آن یک N+1 بود).
        $categories = get_terms( [
            'taxonomy'               => 'product_cat',
            'hide_empty'             => true,
            'update_term_meta_cache' => false,
        ] );

        if ( ! is_wp_error( $categories ) ) {
            foreach ( $categories as $term ) {
                if ( $term instanceof WP_Term ) {
                    $map[ $term->slug ] = $term->name;
                }
            }
        }
    }

    set_transient( 'arian_cat_shortcode_map', $map, DAY_IN_SECONDS );

    return $map;
}

function hodima_home_clear_category_shortcode_map(): void {
    delete_transient( 'arian_cat_shortcode_map' );
}
add_action( 'created_product_cat', 'hodima_home_clear_category_shortcode_map' );
add_action( 'edited_product_cat',  'hodima_home_clear_category_shortcode_map' );
add_action( 'delete_product_cat',  'hodima_home_clear_category_shortcode_map' );

// ========================================================================
// ۳. ثبت تمامی شورت‌کدها در یک هوک یکپارچه
// ========================================================================
add_action('init', function() {

    // شورت‌کدها فقط جایی لازم‌اند که محتوا رندر می‌شود.
    // کرون هیچ‌وقت شورت‌کد پردازش نمی‌کند.
    if ( wp_doing_cron() ) {
        return;
    }

    // الف) شورت‌کد برای فایل‌های بخش موجود.
    // نسخه قبلی کورکورانه section01 تا section20 را ثبت می‌کرد در حالی که
    // فقط چهار فایل وجود دارد؛ ۱۶ شورت‌کد مرده که خروجی‌شان یک کامنت خطا بود.
    // (array) false = [false] و basename(false) با strict_types خطا می‌داد
    foreach ( glob( get_stylesheet_directory() . '/home/section*.php' ) ?: [] as $section_file ) {

        $tag = basename( $section_file, '.php' );

        if ( ! preg_match( '/^section\d{2}$/', $tag ) ) {
            continue;
        }

        add_shortcode( $tag, function( $atts, $content, $shortcode_tag ) {
            return hodima_home_load_section( $shortcode_tag );
        } );
    }

    // ب) شورت‌کد اختصاصی «جدیدترین محصولات»
    if ( function_exists('hodima_home_product_slider') ) {
        add_shortcode( 'latest-products', function($atts) {
            $atts = shortcode_atts(['limit' => 12], $atts);
            return hodima_home_product_slider([
                'title'    => 'جدیدترین محصولات',
                'link'     => 'latest-products',
                'category' => '',
                'limit'    => $atts['limit'],
                'bg_color' => Hodima_Home_Tone::Third->css(),
            ]);
        });
    }

    // ج) شورت‌کد خودکار برای دسته‌بندی‌های ووکامرس (مثل [plasco])
    if ( function_exists('hodima_home_product_slider') ) {

        // سه رنگ برند به نوبت (از پالت: با عوض شدن پالت همراه می‌شود)
        $brand_colors = [
            Hodima_Home_Tone::Primary->css(),
            Hodima_Home_Tone::Secondary->css(),
            Hodima_Home_Tone::Third->css(),
        ];

        $color_index = 0;

        foreach ( hodima_home_category_shortcode_map() as $slug => $name ) {

            // شورت‌کدهای ثبت‌شده بالاتر را بازنویسی نکن
            if ( shortcode_exists( $slug ) ) {
                continue;
            }

            $bg_color = $brand_colors[ $color_index % 3 ];
            $color_index++;

            add_shortcode( $slug, function($atts) use ( $slug, $name, $bg_color ) {
                $atts = shortcode_atts([
                    'limit'    => 12,
                    'title'    => $name,
                    'bg_color' => $bg_color
                ], $atts);

                return hodima_home_product_slider([
                    'title'    => $atts['title'],
                    'link'     => $slug,
                    'category' => $slug,
                    'limit'    => $atts['limit'],
                    'bg_color' => $atts['bg_color'],
                ]);
            });
        }
    }
});

// ========================================================================
// ۴. پاکسازی خودکار و هوشمند کشِ اسلایدرها هنگام تغییر محصولات
// ========================================================================
add_action( 'save_post_product', 'hodima_home_flush_product_sliders', 10, 1 );
add_action( 'woocommerce_product_deleted', 'hodima_home_flush_product_sliders' );
add_action( 'woocommerce_product_set_stock_status', 'hodima_home_flush_product_sliders' );

/**
 * نسل کش اسلایدرها (گزینه hodima_home_slider_gen).
 *
 * باگ قبلی: پاک کردن فقط با DELETE روی جدول options بود. با کش شیء پایدار
 * (Redis/Memcached، مثلا کش شیء لایت‌اسپید) ترنزینت‌ها اصلا در آن جدول
 * نیستند، پس بعد از ویرایش/ناموجود شدن محصول تا ۱۰ دقیقه محصول و قیمت
 * قدیمی در صفحه اصلی می‌ماند. حالا کلید هر کش نسل را دارد و پاک کردن یعنی
 * بالا بردن نسل — در هر دو حالت فورا کش تازه ساخته می‌شود.
 */
function hodima_home_slider_generation(): int {
    return (int) get_option( 'hodima_home_slider_gen', 0 );
}

if ( ! function_exists( 'hodima_home_flush_product_sliders' ) ) {
    /**
     * کش اسلایدرها را باطل کن.
     *
     * 3.1.3: در یک درخواست (درون‌ریزی گروهی صدها محصول) نسل فقط یک‌بار بالا می‌رود و
     * پاکسازی ردیف‌های قدیمی (DELETE LIKE) و کش صفحه اصلی لایت‌اسپید فقط یک‌بار در
     * پایان درخواست (shutdown) انجام می‌شود. قبلا هر ذخیره یک update_option و یک
     * DELETE جدا می‌زد و با تغییر فقط وضعیت موجودی، کش HTML صفحه اصلی پاک نمی‌شد.
     */
    function hodima_home_flush_product_sliders(): void {

        // جلوگیری از اجرای بی‌دلیل هنگام ذخیره خودکار (Autosave) وردپرس
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        static $scheduled = false;

        if ( $scheduled ) {
            return;
        }

        $scheduled = true;

        update_option( 'hodima_home_slider_gen', hodima_home_slider_generation() + 1, true );
        add_action( 'shutdown', 'hodima_home_flush_product_sliders_late' );
    }

    /** پایان درخواست: نسل دوباره (تغییرات بعد از اولین فراخوانی)، پاکسازی ردیف‌های قدیمی و کش صفحه اصلی. */
    function hodima_home_flush_product_sliders_late(): void {

        update_option( 'hodima_home_slider_gen', hodima_home_slider_generation() + 1, true );

        // بدون کش شیء: ردیف‌های نسل قبلی را هم از جدول پاک کن (وگرنه تا انقضا می‌مانند)
        if ( ! wp_using_ext_object_cache() ) {
            global $wpdb;
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_arian_pslider_%' OR option_name LIKE '_transient_timeout_arian_pslider_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- پاکسازی گروهی ترنزینت‌ها؛ API وردپرس برای الگو ندارد
        }

        if ( function_exists( 'hodima_litespeed_purge_home' ) ) {
            hodima_litespeed_purge_home();
        }
    }
}


/**
 * لود هوشمند دارایی‌ها (CSS و JS)
 */
add_action( 'wp_enqueue_scripts', static function (): void {

    // فقط صفحه اصلی (قبلا برگه وبلاگ هم — is_home — بی‌دلیل این CSS/JS را می‌گرفت)
    if ( ! is_front_page() ) {
        return;
    }

    hodima_enqueue_asset( 'hodima-home-style', 'home/home.css' );
    hodima_enqueue_asset( 'hodima-home-js', 'home/home.js', [], [ 'in_footer' => true ] ); // Vanilla JS؛ بدون jQuery
}, 20 );


/**
 * =========================================================================
 * مدیریت و پاک‌سازی هوشمند کش دسته‌بندی‌های صفحه اصلی (Hyper Performance)
 * =========================================================================
 */

/**
 * تابع پاک‌کننده ترنزینت دسته‌بندی‌ها
 */
function hodima_home_clear_categories_cache(): void {
    // v5: همه دسته‌ها با نامک؛ استثناها هنگام نمایش (home/parts/categories.php). v4 نسخه قبلی
    delete_transient( 'hodima_home_categories_v5' );
    delete_transient( 'arian_categories_hyper_v4' );
    
    // سازگاری با افزونه‌های کش معروف (در صورت نصب بودن)
    if ( function_exists( 'rocket_clean_home' ) ) {
        rocket_clean_home();
    }
    /*
     * فقط صفحه اصلی از کش لایت‌اسپید پاک شود. قبلا purge_all بود: ذخیره هر
     * نوشته یا دسته کل کش سایت را خالی می‌کرد و تا ساخته شدن دوباره کش،
     * همه صفحه‌ها برای بازدیدکننده و ربات‌ها کند می‌شدند.
     */
    if ( function_exists( 'hodima_litespeed_purge_home' ) ) {
        hodima_litespeed_purge_home();
    }
}

// ۱. هوک‌های عمومی تغییرات دسته‌بندی ووکامرس
function hodima_home_categories_cache_on_term( mixed $term_id, mixed $tt_id, mixed $taxonomy ): void {
    if ( 'product_cat' === $taxonomy ) {
        hodima_home_clear_categories_cache();
    }
}
add_action( 'create_term', 'hodima_home_categories_cache_on_term', 10, 3 );
add_action( 'edit_term', 'hodima_home_categories_cache_on_term', 10, 3 );
add_action( 'delete_term', 'hodima_home_categories_cache_on_term', 10, 3 );

// ۲. هوک‌های تغییر متادیتاهای دسته‌بندی (زمانی که فقط تصویر شاخص عوض می‌شود)
function hodima_home_categories_cache_on_meta( mixed $meta_id, mixed $object_id, mixed $meta_key ): void {
    // وقتی تصویر شاخص دسته‌بندی تغییر می‌کند
    if ( 'thumbnail_id' === $meta_key ) {
        hodima_home_clear_categories_cache();
    }
}
add_action( 'added_term_meta', 'hodima_home_categories_cache_on_meta', 10, 3 );
add_action( 'updated_term_meta', 'hodima_home_categories_cache_on_meta', 10, 3 );
add_action( 'deleted_term_meta', 'hodima_home_categories_cache_on_meta', 10, 3 );





/**
 * =========================================================================
 * مدیریت و پاک‌سازی هوشمند کش آخرین مقالات وبلاگ (Hyper Performance)
 * =========================================================================
 */

/**
 * تابع پاک‌کننده ترنزینت مقالات وبلاگ
 */
function hodima_home_clear_blog_cache(): void {
    // v3: ۲۰ مقاله آخر؛ تعداد هر بخش هنگام نمایش (home/parts/blog.php). v2 نسخه قبلی
    delete_transient( 'hodima_home_blog_posts_v3' );
    delete_transient( 'arian_latest_blog_posts_hyper_v2' );
    
    // سازگاری با افزونه‌های کش معروف (در صورت نصب بودن)
    if ( function_exists( 'rocket_clean_home' ) ) {
        rocket_clean_home();
    }
    /*
     * فقط صفحه اصلی از کش لایت‌اسپید پاک شود. قبلا purge_all بود: ذخیره هر
     * نوشته یا دسته کل کش سایت را خالی می‌کرد و تا ساخته شدن دوباره کش،
     * همه صفحه‌ها برای بازدیدکننده و ربات‌ها کند می‌شدند.
     */
    if ( function_exists( 'hodima_litespeed_purge_home' ) ) {
        hodima_litespeed_purge_home();
    }
}

// ۱. هوک‌های عمومی تغییرات پست‌ها (انتشار، ویرایش، حذف و انتقال به زباله‌دان)
function hodima_home_blog_cache_on_post( mixed $post_id, mixed $post = null ): void {
    // جلوگیری از اجرای کد هنگام ذخیره خودکار (Autosave) و ریویژن‌ها
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    
    /*
     * نوع پست. باگ قبلی: «$post ? $post->post_type» — ولی trashed_post (از وردپرس
     * 6.3) به‌جای شیء نوشته وضعیت قبلی (رشته) را می‌فرستد؛ هر انتقال نوشته به
     * زباله‌دان هشدار PHP «Attempt to read property on string» می‌داد.
     */
    $post_type = $post instanceof WP_Post ? $post->post_type : get_post_type( (int) $post_id );

    // فقط اگر تغییرات روی نوشته‌ها (post) بود کش را خالی کن
    if ( 'post' === $post_type ) {
        hodima_home_clear_blog_cache();
    }
}
add_action( 'save_post', 'hodima_home_blog_cache_on_post', 10, 2 );
add_action( 'deleted_post', 'hodima_home_blog_cache_on_post', 10, 2 );
add_action( 'trashed_post', 'hodima_home_blog_cache_on_post', 10, 2 );

// ۲. هوک‌های تغییر متادیتاهای پست (زمانی که فقط تصویر شاخص عوض می‌شود)
function hodima_home_blog_cache_on_meta( mixed $meta_id, mixed $post_id, mixed $meta_key ): void {
    // تصویر شاخص پست‌ها با کلید _thumbnail_id ذخیره می‌شود (دقت کنید آندرلاین دارد)
    if ( '_thumbnail_id' === $meta_key && get_post_type( $post_id ) === 'post' ) {
        hodima_home_clear_blog_cache();
    }
}
add_action( 'added_post_meta', 'hodima_home_blog_cache_on_meta', 10, 3 );
add_action( 'updated_post_meta', 'hodima_home_blog_cache_on_meta', 10, 3 );
add_action( 'deleted_post_meta', 'hodima_home_blog_cache_on_meta', 10, 3 );


/*
 * اسکیمای صفحه اصلی (فهرست دسته‌های اصلی، تصاویر و ویدیوی hero) به افزونه
 * Hodima SEO منتقل شد: schema/front-page-extra-schema.php — بازسازی قالب،
 * مرحله ۲. فهرست دسته‌ها دیگر ۱۷ نامک ثابت در کد نیست: «اسکیما ← صفحه اصلی».
 */
