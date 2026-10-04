<?php
/**
 * مدیریت یکپارچه، هوشمند و اتوماتیک سکشن‌های صفحه اصلی هدهدلی
 * فایل: logic.php
 * عملکرد: 
 * ۱. موتور رندر اسلایدر بهینه
 * ۲. تبدیل خودکار دسته‌بندی‌ها به شورت‌کد (مثل [plasco])
 * ۳. پشتیبانی از فایل‌های فیزیکی پوشه home با شورت‌کدهای [section01] الی [section20]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ========================================================================
// ۱. تابع مرکزی اسلایدر محصولات (موتور رندر)
// ========================================================================
if ( ! function_exists( 'hodima_home_product_slider' ) ) {
    function hodima_home_product_slider( $args = [] ) {
        
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
        $transient_key = 'arian_pslider_' . md5( serialize( $query_args ) . '|' . hodima_home_slider_generation() );
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

        ob_start();
        ?>
        <section class="arian-section arian-product-section" aria-labelledby="<?php echo esc_attr( $args['id'] ); ?>" style="--section-bg: <?php echo esc_attr( $args['bg_color'] ); ?>;">
            <div class="arian-header">
                <div class="arian-title-group">
                    <h2 id="<?php echo esc_attr( $args['id'] ); ?>" class="arian-title"><?php echo esc_html( $args['title'] ); ?></h2>
                    <div class="arian-line"></div>
                </div>
                <?php if ( ! empty( $clean_slug ) ) : ?>
                <a href="<?php echo $final_link; ?>" class="arian-view-all">مشاهده همه</a>
                <?php endif; ?>
            </div>

            <div class="arian-scroller product-wrapper">
                <?php foreach ( $products_data as $index => $product ) : ?>
                    <article class="arian-card product-card">
                        <a href="<?php echo esc_url( $product['link'] ); ?>" class="arian-card-img-link">
                            <?php
                            if ( ! empty( $product['thumbnail_id'] ) ) {
                                $image_attrs = [
                                    'alt'      => esc_attr( $product['name'] ),
                                    'decoding' => 'async',
                                    'class'    => 'arian-card-img',
                                ];
                                if ( $index === 0 ) {
                                    $image_attrs['fetchpriority'] = 'high';
                                } else {
                                    $image_attrs['loading'] = 'lazy';
                                }
                                echo wp_get_attachment_image( $product['thumbnail_id'], 'woocommerce_thumbnail', false, $image_attrs );
                            } else {
                                echo '<img src="' . esc_url( wc_placeholder_img_src('woocommerce_thumbnail') ) . '" alt="' . esc_attr( $product['name'] ) . '" width="300" height="300" loading="lazy" decoding="async" class="arian-card-img">';
                            }
                            ?>
                        </a>

                        <div class="arian-card-info">
                            <h3 class="arian-card-name">
                                <a href="<?php echo esc_url( $product['link'] ); ?>"><?php echo esc_html( $product['name'] ); ?></a>
                            </h3>
                            <div class="arian-card-price">
                                <?php echo wp_kses_post( $product['price_html'] ); ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
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

/** همه دسته‌های محصول دارای کالا: نامک، نام، لینک، شناسه تصویر. */
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

/** ۲۰ مقاله آخر: عنوان، لینک، شناسه تصویر شاخص (۱۵ دقیقه کش). */
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
    function hodima_home_load_section( $tag ) {
        $tag = sanitize_key( $tag );
        if ( empty( $tag ) ) return '';

        $php_file = get_stylesheet_directory() . "/home/{$tag}.php";

        if ( ! file_exists( $php_file ) ) {
            return "<!-- خطای هدیما: فایل {$tag}.php در مسیر {$php_file} پیدا نشد -->";
        }

        ob_start();
        include $php_file;
        return trim( ob_get_clean() );
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
function hodima_home_category_shortcode_map() {

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

function hodima_home_clear_category_shortcode_map() {
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
    foreach ( (array) glob( get_stylesheet_directory() . '/home/section*.php' ) as $section_file ) {

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
                'bg_color' => 'rgba(182, 194, 243, 0.25)',
            ]);
        });
    }

    // ج) شورت‌کد خودکار برای دسته‌بندی‌های ووکامرس (مثل [plasco])
    if ( function_exists('hodima_home_product_slider') ) {

        $brand_colors = [
            'rgba(37, 49, 106, 0.08)',   // رنگ اصلی
            'rgba(96, 123, 189, 0.1)',   // رنگ ثانویه
            'rgba(182, 194, 243, 0.25)', // رنگ سوم
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
    function hodima_home_flush_product_sliders( mixed $post_id = 0 ): void {

        // جلوگیری از اجرای بی‌دلیل هنگام ذخیره خودکار (Autosave) وردپرس
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        update_option( 'hodima_home_slider_gen', hodima_home_slider_generation() + 1, true );

        // بدون کش شیء: ردیف‌های نسل قبلی را هم از جدول پاک کن (وگرنه تا انقضا می‌مانند)
        if ( ! wp_using_ext_object_cache() ) {
            global $wpdb;
            $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_arian_pslider_%' OR option_name LIKE '_transient_timeout_arian_pslider_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- پاکسازی گروهی ترنزینت‌ها؛ API وردپرس برای الگو ندارد
        }
    }
}


/**
 * لود هوشمند دارایی‌ها (CSS و JS)
 */
add_action( 'wp_enqueue_scripts', function() {

    // فقط در صفحه اصلی
    // فقط صفحه اصلی (قبلا برگه وبلاگ هم — is_home — بی‌دلیل این CSS/JS را می‌گرفت)
    if ( ! is_front_page() ) return;

    $base_path = get_stylesheet_directory() . '/home/';
    $base_uri  = get_stylesheet_directory_uri() . '/home/';

    // CSS
    if ( file_exists( $base_path . 'home.css' ) ) {
        wp_enqueue_style(
            'hodima-home-style',
            $base_uri . 'home.css',
            [],
            filemtime( $base_path . 'home.css' )
        );
    }

    // JS
    if ( file_exists( $base_path . 'home.js' ) ) {
        wp_enqueue_script(
            'hodima-home-js',
            $base_uri . 'home.js',
            [], // Vanilla JS؛ وابستگی jQuery لازم نیست
            filemtime( $base_path . 'home.js' ),
            true
        );
    }

}, 20);


/**
 * =========================================================================
 * مدیریت و پاک‌سازی هوشمند کش دسته‌بندی‌های صفحه اصلی (Hyper Performance)
 * =========================================================================
 */

/**
 * تابع پاک‌کننده ترنزینت دسته‌بندی‌ها
 */
function hodima_home_clear_categories_cache() {
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
function hodima_home_categories_cache_on_term( $term_id, $tt_id, $taxonomy ) {
    if ( 'product_cat' === $taxonomy ) {
        hodima_home_clear_categories_cache();
    }
}
add_action( 'create_term', 'hodima_home_categories_cache_on_term', 10, 3 );
add_action( 'edit_term', 'hodima_home_categories_cache_on_term', 10, 3 );
add_action( 'delete_term', 'hodima_home_categories_cache_on_term', 10, 3 );

// ۲. هوک‌های تغییر متادیتاهای دسته‌بندی (زمانی که فقط تصویر شاخص عوض می‌شود)
function hodima_home_categories_cache_on_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
    // وقتی تصویر شاخص دسته‌بندی تغییر می‌کند
    if ( 'thumbnail_id' === $meta_key ) {
        hodima_home_clear_categories_cache();
    }
}
add_action( 'added_term_meta', 'hodima_home_categories_cache_on_meta', 10, 4 );
add_action( 'updated_term_meta', 'hodima_home_categories_cache_on_meta', 10, 4 );
add_action( 'deleted_term_meta', 'hodima_home_categories_cache_on_meta', 10, 4 );





/**
 * =========================================================================
 * مدیریت و پاک‌سازی هوشمند کش آخرین مقالات وبلاگ (Hyper Performance)
 * =========================================================================
 */

/**
 * تابع پاک‌کننده ترنزینت مقالات وبلاگ
 */
function hodima_home_clear_blog_cache() {
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
function hodima_home_blog_cache_on_post( $post_id, $post = null ) {
    // جلوگیری از اجرای کد هنگام ذخیره خودکار (Autosave) و ریویژن‌ها
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    
    // دریافت نوع پست
    $post_type = $post ? $post->post_type : get_post_type( $post_id );

    // فقط اگر تغییرات روی نوشته‌ها (post) بود کش را خالی کن
    if ( 'post' === $post_type ) {
        hodima_home_clear_blog_cache();
    }
}
add_action( 'save_post', 'hodima_home_blog_cache_on_post', 10, 2 );
add_action( 'deleted_post', 'hodima_home_blog_cache_on_post', 10, 2 );
add_action( 'trashed_post', 'hodima_home_blog_cache_on_post', 10, 2 );

// ۲. هوک‌های تغییر متادیتاهای پست (زمانی که فقط تصویر شاخص عوض می‌شود)
function hodima_home_blog_cache_on_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
    // تصویر شاخص پست‌ها با کلید _thumbnail_id ذخیره می‌شود (دقت کنید آندرلاین دارد)
    if ( '_thumbnail_id' === $meta_key && get_post_type( $post_id ) === 'post' ) {
        hodima_home_clear_blog_cache();
    }
}
add_action( 'added_post_meta', 'hodima_home_blog_cache_on_meta', 10, 4 );
add_action( 'updated_post_meta', 'hodima_home_blog_cache_on_meta', 10, 4 );
add_action( 'deleted_post_meta', 'hodima_home_blog_cache_on_meta', 10, 4 );


/*
 * اسکیمای صفحه اصلی (فهرست دسته‌های اصلی، تصاویر و ویدیوی hero) به افزونه
 * Hodima SEO منتقل شد: schema/front-page-extra-schema.php — بازسازی قالب،
 * مرحله ۲. فهرست دسته‌ها دیگر ۱۷ نامک ثابت در کد نیست: «اسکیما ← صفحه اصلی».
 */
