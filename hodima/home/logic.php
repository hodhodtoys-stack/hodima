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
if ( ! function_exists( 'arian_render_product_slider' ) ) {
    function arian_render_product_slider( $args = [] ) {
        
        if ( ! function_exists( 'wc_get_products' ) ) {
            return ''; 
        }

        $defaults = [
            'title'    => 'محصولات',
            'link'     => '',
            'category' => '',
            'limit'    => 10,
            'bg_color' => 'transparent',
            'id'       => uniqid('arian-sec-') 
        ];
        $args = wp_parse_args( $args, $defaults );

        $clean_slug = trim( $args['link'], '/' );
        $final_link = ! empty( $clean_slug ) ? esc_url( home_url( '/' . $clean_slug . '/' ) ) : esc_url( home_url( '/' ) );

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

        $transient_key = 'arian_pslider_' . md5( serialize( $query_args ) );
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
// ۲. تابع لود سکشن‌های فیزیکی (برای section01 تا section20)
// ========================================================================
if ( ! function_exists( 'arian_load_home_section' ) ) {
    function arian_load_home_section( $tag ) {
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
function arian_get_category_shortcode_map() {

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

function arian_clear_category_shortcode_map() {
    delete_transient( 'arian_cat_shortcode_map' );
}
add_action( 'created_product_cat', 'arian_clear_category_shortcode_map' );
add_action( 'edited_product_cat',  'arian_clear_category_shortcode_map' );
add_action( 'delete_product_cat',  'arian_clear_category_shortcode_map' );

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
            return arian_load_home_section( $shortcode_tag );
        } );
    }

    // ب) شورت‌کد اختصاصی «جدیدترین محصولات»
    if ( function_exists('arian_render_product_slider') ) {
        add_shortcode( 'latest-products', function($atts) {
            $atts = shortcode_atts(['limit' => 12], $atts);
            return arian_render_product_slider([
                'title'    => 'جدیدترین محصولات',
                'link'     => 'latest-products',
                'category' => '',
                'limit'    => $atts['limit'],
                'bg_color' => 'rgba(182, 194, 243, 0.25)',
            ]);
        });
    }

    // ج) شورت‌کد خودکار برای دسته‌بندی‌های ووکامرس (مثل [plasco])
    if ( function_exists('arian_render_product_slider') ) {

        $brand_colors = [
            'rgba(37, 49, 106, 0.08)',   // رنگ اصلی
            'rgba(96, 123, 189, 0.1)',   // رنگ ثانویه
            'rgba(182, 194, 243, 0.25)', // رنگ سوم
        ];

        $color_index = 0;

        foreach ( arian_get_category_shortcode_map() as $slug => $name ) {

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

                return arian_render_product_slider([
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
add_action( 'save_post_product', 'arian_flush_product_sliders_cache', 10, 3 );
add_action( 'woocommerce_product_deleted', 'arian_flush_product_sliders_cache' );
add_action( 'woocommerce_product_set_stock_status', 'arian_flush_product_sliders_cache' );

if ( ! function_exists( 'arian_flush_product_sliders_cache' ) ) {
    function arian_flush_product_sliders_cache( $post_id = 0 ) {
        
        // جلوگیری از اجرای بی‌دلیل هنگام ذخیره خودکار (Autosave) وردپرس
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        global $wpdb;
        
        // اجرای کوئری برای پاک کردن تمام کش‌هایی که با 'arian_pslider_' شروع می‌شوند
        $wpdb->query( "
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_arian_pslider_%' 
            OR option_name LIKE '_transient_timeout_arian_pslider_%'
        " );
    }
}


/**
 * لود هوشمند دارایی‌ها (CSS و JS)
 */
add_action( 'wp_enqueue_scripts', function() {

    // فقط در صفحه اصلی
    if ( ! ( is_front_page() || is_home() ) ) return;

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
function arian_clear_category_cache_force() {
    // کلید با فایل نمایشی یکپارچه شد (v4)
    delete_transient( 'arian_categories_hyper_v4' );
    
    // سازگاری با افزونه‌های کش معروف (در صورت نصب بودن)
    if ( function_exists( 'rocket_clean_home' ) ) {
        rocket_clean_home();
    }
    if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
        LiteSpeed_Cache_API::purge_all(); // استفاده از purge_all استانداردتر از force است
    }
}

// ۱. هوک‌های عمومی تغییرات دسته‌بندی ووکامرس
function arian_clear_category_cache_on_term_change( $term_id, $tt_id, $taxonomy ) {
    if ( 'product_cat' === $taxonomy ) {
        arian_clear_category_cache_force();
    }
}
add_action( 'create_term', 'arian_clear_category_cache_on_term_change', 10, 3 );
add_action( 'edit_term', 'arian_clear_category_cache_on_term_change', 10, 3 );
add_action( 'delete_term', 'arian_clear_category_cache_on_term_change', 10, 3 );

// ۲. هوک‌های تغییر متادیتاهای دسته‌بندی (زمانی که فقط تصویر شاخص عوض می‌شود)
function arian_clear_category_cache_on_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) {
    // وقتی تصویر شاخص دسته‌بندی تغییر می‌کند
    if ( 'thumbnail_id' === $meta_key ) {
        arian_clear_category_cache_force();
    }
}
add_action( 'added_term_meta', 'arian_clear_category_cache_on_meta_change', 10, 4 );
add_action( 'updated_term_meta', 'arian_clear_category_cache_on_meta_change', 10, 4 );
add_action( 'deleted_term_meta', 'arian_clear_category_cache_on_meta_change', 10, 4 );





/**
 * =========================================================================
 * مدیریت و پاک‌سازی هوشمند کش آخرین مقالات وبلاگ (Hyper Performance)
 * =========================================================================
 */

/**
 * تابع پاک‌کننده ترنزینت مقالات وبلاگ
 */
function arian_clear_blog_cache_force() {
    // کلید مربوط به کش وبلاگ که در section9.php تعریف شده است
    delete_transient( 'arian_latest_blog_posts_hyper_v2' );
    
    // سازگاری با افزونه‌های کش معروف (در صورت نصب بودن)
    if ( function_exists( 'rocket_clean_home' ) ) {
        rocket_clean_home();
    }
    if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
        LiteSpeed_Cache_API::purge_all(); 
    }
}

// ۱. هوک‌های عمومی تغییرات پست‌ها (انتشار، ویرایش، حذف و انتقال به زباله‌دان)
function arian_clear_blog_cache_on_post_change( $post_id, $post = null ) {
    // جلوگیری از اجرای کد هنگام ذخیره خودکار (Autosave) و ریویژن‌ها
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    
    // دریافت نوع پست
    $post_type = $post ? $post->post_type : get_post_type( $post_id );

    // فقط اگر تغییرات روی نوشته‌ها (post) بود کش را خالی کن
    if ( 'post' === $post_type ) {
        arian_clear_blog_cache_force();
    }
}
add_action( 'save_post', 'arian_clear_blog_cache_on_post_change', 10, 2 );
add_action( 'deleted_post', 'arian_clear_blog_cache_on_post_change', 10, 2 );
add_action( 'trashed_post', 'arian_clear_blog_cache_on_post_change', 10, 2 );

// ۲. هوک‌های تغییر متادیتاهای پست (زمانی که فقط تصویر شاخص عوض می‌شود)
function arian_clear_blog_cache_on_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
    // تصویر شاخص پست‌ها با کلید _thumbnail_id ذخیره می‌شود (دقت کنید آندرلاین دارد)
    if ( '_thumbnail_id' === $meta_key && get_post_type( $post_id ) === 'post' ) {
        arian_clear_blog_cache_force();
    }
}
add_action( 'added_post_meta', 'arian_clear_blog_cache_on_meta_change', 10, 4 );
add_action( 'updated_post_meta', 'arian_clear_blog_cache_on_meta_change', 10, 4 );
add_action( 'deleted_post_meta', 'arian_clear_blog_cache_on_meta_change', 10, 4 );







/**
 * =========================================================================
 * اسکیمای صفحه اصلی هدهدلی (Homepage Schema Graph) - Single Hero Video
 * =========================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns selected category slugs for homepage schema.
 */
function arian_homepage_schema_category_slugs() {
    return [
        'beauty',
        'accessory',
        'hairband',
        'hair-ties',
        'hair-clips',
        'plumeria-hair-clips',
        'birds-nest-claw-clips',
        'metal-hair-clips',
        'hair-pins',
        'alligator-hair-clips',
        'snap-hair-clips',
        'mini-clips',
        'latest-products',
        'charms',
        'packaging-supplies',
        'hodhod-plus',
        'zip-lock-pouches',
    ];
}

/**
 * Builds the homepage schema graph.
 */
function arian_build_homepage_schema_graph() {
    $site_url       = trailingslashit( home_url( '/' ) );
    $site_name      = get_bloginfo('name');
    $category_slugs = arian_homepage_schema_category_slugs();
    $graph          = [];

    // ==========================================
    // ۱. لیست دسته‌بندی‌ها (ItemList)
    // ==========================================
    if ( is_array( $category_slugs ) && ! empty( $category_slugs ) ) {
        $item_list_elements = [];
        $position           = 1;

        foreach ( $category_slugs as $slug ) {
            $term = get_term_by( 'slug', $slug, 'product_cat' );
            if ( ! $term || is_wp_error( $term ) ) continue;

            $term_link = get_term_link( $term );
            if ( is_wp_error( $term_link ) ) continue;

            $item_list_elements[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => wp_strip_all_tags( $term->name ),
                'url'      => $term_link,
            ];
        }

        if ( ! empty( $item_list_elements ) ) {
            $graph[] = [
                '@type'            => 'ItemList',
                '@id'              => $site_url . '#homepage-categories',
                'name'             => 'دسته بندی های اصلی محصولات',
                'itemListElement'  => $item_list_elements,
                'mainEntityOfPage' => [
                    '@id' => $site_url . '#webpage',
                ],
            ];
        }
    }

    // ==========================================
    // ۲. مسیر راهنما (BreadcrumbList) — حذف شد
    // ==========================================
    // بردکرامب صفحه اصلی فقط یک پله («خانه») داشت: مسیری که به هیچ جا
    // نمی‌رود و گوگل حداقل دو پله می‌خواهد. هیچ نودی به آن ارجاع نمی‌داد.

// ==========================================
    // ۳. تصاویر و ویدئوی اصلی (Hook Media System)
    // ==========================================
    $front_page_id = get_option('page_on_front'); 
    
    if ( $front_page_id ) {
        $raw_content      = get_post_field('post_content', $front_page_id);
        $rendered_content = do_shortcode($raw_content) . ' ' . $raw_content; 
        $clean_content    = str_replace('\/', '/', $rendered_content);
        
        $company_name = get_option('hodima_corp_name', 'بازرگانی هدهد');
        $org_id       = $site_url . '#organization';
        $logo_url     = get_site_icon_url() ?: $site_url . 'wp-content/uploads/logo.png';

        // --- الف) استخراج تصاویر (فقط برای Image Metadata) ---
        $images = array();
        if ( has_post_thumbnail($front_page_id) ) $images[] = get_the_post_thumbnail_url($front_page_id, 'full');
        if ( preg_match_all('/https?:\/\/[^\s"\'<>]+\.(?:jpg|jpeg|png|gif|webp)[^\s"\'<>]*/i', $clean_content, $img_m) ) $images = array_merge($images, $img_m[0]);
        if ( preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $clean_content, $img_t) ) $images = array_merge($images, $img_t[1]);
        
        $images = array_unique(array_filter($images));
        $opt_credit  = get_option('hodima_schema_image_credit', 'عکس متعلق به ' . $company_name . ' است.');
        $opt_license = get_option('hodima_schema_image_license', $site_url . 'terms/');
        
        $img_counter = 0;
        foreach ($images as $index => $img_url) {
            if ($img_counter >= 5) break; 
            
            $img_id = $site_url . '#image-' . ($index + 1);
            $graph[] = array(
                "@type"              => "ImageObject",
                "@id"                => $img_id,
                "url"                => esc_url_raw($img_url),
                "contentUrl"         => esc_url_raw($img_url),
                "caption"            => $site_name . ' - تصویر ' . ($index + 1),
                "creator"            => array("@type" => "Organization", "name" => $company_name),
                "creditText"         => $opt_credit,
                "copyrightNotice"    => "© " . date('Y') . " " . $company_name . ".",
                "license"            => esc_url_raw($opt_license),
                "acquireLicensePage" => $site_url
            );
            $img_counter++;
        }

        // --- ب) استخراج فقط یک ویدئو (اختصاصی سیستم Hook Media) ---
        $v_url = get_post_meta($front_page_id, '_hook_video_url', true);
        
        if ( !empty($v_url) ) {
            $v_thumb = get_post_meta($front_page_id, '_hook_video_cover', true) ?: get_option('hodima_page_video_default_img', (!empty($images) ? reset($images) : $logo_url));
            $v_title = get_post_meta($front_page_id, '_hook_video_title', true) ?: 'معرفی وب‌سایت ' . $site_name;
            $v_dur   = get_post_meta($front_page_id, '_hook_video_duration', true);

            $v_url   = esc_url_raw($v_url);
            $is_mp4  = preg_match('/\.m(?:p4|3u8|kv|ebm)/i', $v_url);
            
            // 🔴 دور زدن قطعی افزونه‌های شمسی‌ساز با استفاده از توابع خام PHP
            $raw_date = get_post_field('post_date_gmt', $front_page_id);
            if (empty($raw_date) || strpos($raw_date, '0000') !== false) {
                $raw_date = get_post_field('post_date', $front_page_id);
            }
            $timestamp = strtotime($raw_date);
            if (!$timestamp) {
                $timestamp = time(); // فال‌بک به زمان حال
            }
            // فرمت سخت‌گیرانه استاندارد ایزو بدون دخالت وردپرس
            $safe_upload_date = gmdate('Y-m-d\TH:i:s+00:00', $timestamp);
            
            $video_schema = array(
                "@type"            => "VideoObject",
                "@id"              => $site_url . '#hero-video',
                "name"             => $v_title,
                "description"      => 'ویدیوی معرفی و بررسی تخصصی ' . $site_name,
                "thumbnailUrl"     => [ esc_url_raw($v_thumb) ],
                "uploadDate"       => $safe_upload_date, // 👈 از متغیر جدید و ایمن استفاده شد
                "inLanguage"       => "fa-IR",
                "isFamilyFriendly" => true,
                "publisher"        => array( '@id' => $org_id )
            );

            if ( $is_mp4 ) {
                $video_schema["contentUrl"] = $v_url;
                $video_schema["encodingFormat"] = strpos($v_url, '.m3u8') !== false ? 'application/x-mpegURL' : 'video/mp4';
            } else {
                $video_schema["embedUrl"] = $v_url;
                $video_schema["url"]      = $site_url; 
            }

            // نام جدید سیستم رسانه (Hodima Media 1.2+)، با فالبک نام قدیمی
            $v_iso = function_exists('hodima_media_duration_iso') ? hodima_media_duration_iso($v_dur)
                : ( function_exists('hook_format_duration_iso') ? hook_format_duration_iso($v_dur) : '' );
            if ( !empty($v_dur) && '' !== $v_iso ) {
                $video_schema["duration"] = $v_iso;
            }

            $graph[] = $video_schema;
        }
    }

    return $graph;
}

/**
 * Returns cached homepage schema graph.
 */
function arian_get_cached_homepage_schema_graph() {
    // 🔴 نام کلید کش تغییر کرد تا کش قبلی که ارور داشت کلا نادیده گرفته شود
    $cache_key = 'arian_homepage_schema_graph_v6_hero_video'; 
    $graph     = get_transient( $cache_key );

    if ( false !== $graph && is_array( $graph ) ) {
        return $graph;
    }

    $graph = arian_build_homepage_schema_graph();

    set_transient( $cache_key, $graph, 12 * HOUR_IN_SECONDS );

    return $graph;
}

/**
 * Outputs homepage schema in <head>.
 */
function arian_add_homepage_schema_extra() {
    if ( ! is_front_page() ) {
        return;
    }

    $graph = arian_get_cached_homepage_schema_graph();

    if ( empty( $graph ) ) {
        return;
    }

    /*
     * ویدیوی hero تکراری نشود.
     * این ویدیو از متای «_hook_video_url» همان برگه صفحه اصلی خوانده می‌شود —
     * دقیقا همان متایی که سیستم رسانه (hodima-media/media-system) وقتی برای
     * این برگه فعال است، با شناسه «#video» به گراف می‌دهد. نتیجه دو
     * VideoObject متفاوت (#hero-video و #video) برای یک فایل ویدیو بود.
     * سیستم رسانه نسخه کامل‌تر (isPartOf، توضیح، کلمات کلیدی) را می‌سازد؛
     * پس فقط وقتی او ویدیو را نمی‌سازد، #hero-video چاپ می‌شود.
     * (بررسی هنگام چاپ، نه در کش ۱۲ ساعته گراف، تا با روشن/خاموش شدن
     * سیستم رسانه بلافاصله درست شود.)
     */
    if ( arian_media_system_owns_front_video() ) {
        $graph = array_values( array_filter(
            $graph,
            static fn( array $node ): bool => ! str_ends_with( (string) ( $node['@id'] ?? '' ), '#hero-video' )
        ) );
    }

    // بردکرامب تک‌پله‌ای قدیمی (بخش ۲ بالا) ممکن است تا ۱۲ ساعت در کش مانده باشد
    $graph = array_values( array_filter(
        $graph,
        static fn( array $node ): bool => 'BreadcrumbList' !== ( $node['@type'] ?? '' )
    ) );

    if ( empty( $graph ) ) {
        return;
    }

    // گراف واحد صفحه (hodima-core)
    hodima_schema_add( [ '@graph' => $graph ], 'theme: home/logic.php' );
}

/**
 * آیا سیستم رسانه برای برگه صفحه اصلی VideoObject می‌سازد؟
 * Hodima Media 1.2+: همان سازنده واحد (hodima_media_video_node)؛ قبل از آن
 * همان شرط‌های hook_auto_inject_head_schema() و hook_print_schema('video').
 */
function arian_media_system_owns_front_video(): bool {

    $front_id = (int) get_option( 'page_on_front' );

    if ( $front_id > 0 && function_exists( 'hodima_media_video_node' ) && function_exists( 'hodima_media_post_types' ) ) {
        return in_array( 'page', hodima_media_post_types(), true )
            && ( ! function_exists( 'hodima_post_content_is_visible' ) || hodima_post_content_is_visible( $front_id ) )
            && null !== hodima_media_video_node( $front_id, 'post' );
    }

    if ( $front_id <= 0 || ! function_exists( 'hook_get_media_data' ) || ! function_exists( 'hook_print_schema' ) ) {
        return false;
    }

    if ( ! in_array( 'page', function_exists( 'hook_get_supported_post_types' ) ? hook_get_supported_post_types() : [], true ) ) {
        return false;
    }

    if ( function_exists( 'hodima_post_content_is_visible' ) && ! hodima_post_content_is_visible( $front_id ) ) {
        return false;
    }

    $data = hook_get_media_data( $front_id, 'post' );

    if ( 'yes' !== ( $data['enabled'] ?? '' ) || empty( $data['video_url'] ) ) {
        return false;
    }

    // بدون تصویر، سیستم رسانه ویدیو را چاپ نمی‌کند (thumbnailUrl الزامی است)
    return '' !== (string) ( $data['video_thumb'] ?? '' ) || has_post_thumbnail( $front_id );
}
add_action( 'wp_head', 'arian_add_homepage_schema_extra', 30 );

/**
 * Clears homepage schema cache.
 */
function arian_clear_homepage_schema_cache() {
    // 🔴 کلید کش جدید به لیست پاک‌سازی اضافه شد
    delete_transient( 'arian_homepage_schema_graph_v6_hero_video' ); 
    delete_transient( 'arian_homepage_schema_graph_v5_hero_video' );
    delete_transient( 'arian_homepage_schema_graph_v4_media' );
    delete_transient( 'arian_homepage_schema_graph_v3_media' );
}

add_action( 'created_product_cat', 'arian_clear_homepage_schema_cache' );
add_action( 'edited_product_cat', 'arian_clear_homepage_schema_cache' );
add_action( 'delete_product_cat', 'arian_clear_homepage_schema_cache' );
add_action( 'after_switch_theme', 'arian_clear_homepage_schema_cache' );
add_action( 'save_post', 'arian_clear_homepage_schema_cache' );






