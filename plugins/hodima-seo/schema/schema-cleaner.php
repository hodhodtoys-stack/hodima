<?php
/**
 * HOOK SCHEMA CLEANER PRO - SURGEON EDITION V5.2 (Deep Node Sweeper)
 * Path: wp-content/plugins/hodima-seo/schema/schema-cleaner.php
 * جراحی و مهار هوشمند اسکیماهای افزونه‌ها در کل سایت (رفع تداخل سرچ کنسول)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
 * 0. تابع کمکی: آیا imageobject-schema.php واقعاً این صفحه را پوشش می‌دهد؟
 * ---------------------------------------------------------
 * باگ مهم رفع‌شده: قبلاً این فایل بدون هیچ بررسی‌ای، ImageObject رنک‌مث را
 * در تمام صفحات محصول/نوشته/آرشیو/ویدیو حذف می‌کرد، با این فرض که ماژول
 * اختصاصی ما (imageobject-schema.php) همیشه جایگزینش می‌شود. اما آن ماژول
 * فقط روی صفحاتی اجرا می‌شود که در تنظیمات «Targeting» آن
 * (hodima_schema_image_targets) صراحتاً تیک خورده باشند — پیش‌فرض آن فقط
 * ['product','post'] است و 'product_cat'/'category' را شامل نمی‌شود مگر
 * دستی فعال شوند. نتیجه: در تمام صفحات دسته‌بندی، ImageObject رنک‌مث حذف
 * می‌شد بدون اینکه چیزی جایگزینش شود — دقیقاً همان چیزی که باعث افت شمارش
 * Image Metadata در سرچ کنسول می‌شود. حالا حذف فقط زمانی انجام می‌شود که
 * ماژول ما واقعاً برای همین صفحه فعال و پوشش‌دهنده باشد.
 * ========================================================= */
function hodima_our_image_schema_covers_current_page() {
    if ( get_option( 'hodima_schema_image_enable' ) !== '1' ) {
        return false;
    }

    $targets = get_option( 'hodima_schema_image_targets', ['product', 'post', 'product_cat', 'category'] );
    if ( empty( $targets ) || ! is_array( $targets ) ) {
        return false;
    }

    if ( is_singular() ) {
        return in_array( get_post_type(), $targets, true );
    }

    if ( is_tax() || is_category() || is_tag() ) {
        $term = get_queried_object();
        return $term && isset( $term->taxonomy ) && in_array( $term->taxonomy, $targets, true );
    }

    return false;
}

/* =========================================================
 * 0b. تابع کمکی: آیا breadcrumb-schema.php این صفحه را پوشش می‌دهد؟
 * ---------------------------------------------------------
 * همان کلاس باگ ImageObject: قبلاً BreadcrumbList رنک‌مث در کل سایت بدون
 * هیچ قید و شرطی حذف می‌شد، با این فرض که breadcrumb-schema.php همیشه
 * جایگزینش می‌کند.
 * 🛠️ رفع دقیق‌تر: قبلاً این تابع فقط چک می‌کرد سوییچ روشن است و صفحه
 * اصلی نیست — یعنی روی صفحات جستجو (is_search)، ۴۰۴ (is_404)، آرشیو
 * تاریخ (is_date) و آرشیو نویسنده (is_author) هم می‌گفت «بله پوشش می‌دهیم»،
 * درحالی‌که breadcrumb-schema.php هیچ شاخه‌ای برای این نوع صفحات ندارد و
 * چیزی چاپ نمی‌کند (تابع add_term_ancestors/آیتم‌ها خالی می‌ماند و به خاطر
 * شرط count($items) < 2 اصلاً هیچ‌چیز echo نمی‌شود). نتیجه این بود که در
 * این صفحات، BreadcrumbList رنک‌مث حذف می‌شد بدون این‌که چیزی جایگزینش
 * شود. حالا برای این نوع صفحات مشخص، false برمی‌گرداند.
 * ========================================================= */
function hodima_our_breadcrumb_covers_current_page() {
    if ( get_option( 'hodima_breadcrumb_schema_status', 'on' ) !== 'on' ) {
        return false;
    }
    if ( is_home() || is_front_page() ) {
        return false; // breadcrumb-schema.php هم عمداً در صفحه اصلی چیزی چاپ نمی‌کند
    }
    if ( is_search() || is_404() || is_date() || is_author() ) {
        return false; // breadcrumb-schema.php برای این نوع صفحات هیچ شاخه‌ای ندارد
    }
    return true;
}

/* =========================================================
 * 0c. تابع کمکی: آیا category-schema-pro.php این صفحه را پوشش می‌دهد؟
 * ---------------------------------------------------------
 * همان کلاس باگ‌های قبلی، پیشگیرانه: اگر روزی hodima_cat_status یا
 * hodima_cat_include_post_category خاموش شوند، این تابع جلوی حذف
 * بی‌جهت CollectionPage رنک‌مث را می‌گیرد.
 * ========================================================= */
function hodima_our_category_schema_covers_current_page() {
    if ( get_option( 'hodima_cat_status', 'on' ) !== 'on' ) {
        return false;
    }
    if ( is_tax( 'product_cat' ) ) {
        return true;
    }
    if ( is_category() ) {
        return get_option( 'hodima_cat_include_post_category', 'on' ) === 'on';
    }
    return false;
}

/* =========================================================
 * 0d. تابع کمکی: حذف عمیق بر اساس نوع (@type) — نه بر اساس نام کلید
 * ---------------------------------------------------------
 * چرا لازم شد: unset($data['CollectionPage']) فرض می‌کند رنک‌مث همیشه
 * این نوع را دقیقاً زیر کلیدی به همین نام نگه می‌دارد. اما رنک‌مث گاهی
 * چند «قطعه» (piece) را زیر یک کلید عمومی مثل 'primary' یا در یک آرایه‌ی
 * عددی برمی‌گرداند که '@type' آن CollectionPage است ولی نام کلیدش چیز
 * دیگری است — در این حالت unset ساده هرگز آن را پیدا نمی‌کند و همان
 * چیزی رخ می‌دهد که در schema.org دیدید: ۲ آیتم CollectionPage روی یک
 * صفحه. این تابع مستقل از نام کلید، هر جای آرایه (حتی تو در تو) که
 * '@type' برابر نوع داده‌شده باشد را پیدا و حذف می‌کند.
 * ========================================================= */
function hodima_deep_remove_by_type( &$data, $type ) {
    if ( ! is_array( $data ) ) {
        return;
    }
    foreach ( $data as $key => &$value ) {
        if ( ! is_array( $value ) ) {
            continue;
        }
        $node_type = $value['@type'] ?? null;
        $matches   = is_array( $node_type ) ? in_array( $type, $node_type, true ) : ( $node_type === $type );
        if ( $matches ) {
            unset( $data[ $key ] );
            continue;
        }
        hodima_deep_remove_by_type( $value, $type );
    }
    unset( $value );
}

/* =========================================================
 * 0e. تابع کمکی: آیا homepage-schema.php گراف هویتی (Organization/
 *     WebSite/WebPage) را واقعاً برای این درخواست تولید می‌کند؟
 * ---------------------------------------------------------
 * 🛠️ باگ مهم رفع‌شده: بخش‌های ۲، ۳، ۵، ۶، ۷ و ۸ این فایل، بدون هیچ
 * شرطی، Organization/WebSite/publisher/LocalBusiness/WholesaleStore/
 * Store/ProfilePage و WebPage رنک‌مث را در سراسر سایت حذف می‌کردند، با
 * این فرض قطعی که homepage-schema.php همیشه این نودها را جایگزین می‌کند.
 * این فرض معمولاً درست است چون آن فایل روی هر درخواست فرانت‌اند اجرا
 * می‌شود — ولی فقط تا وقتی کلید سراسری آن (hodima_schema_homepage_enable)
 * روشن باشد. اگر ادمین آن ماژول را خاموش کند ولی این پاک‌کننده را روشن
 * نگه دارد، این نودها از سایت به‌کل حذف می‌شدند بدون هیچ جایگزینی. حالا
 * حذف این نودهای عمومی فقط زمانی انجام می‌شود که واقعاً جایگزینی وجود دارد.
 * ========================================================= */
function hodima_our_identity_graph_covers_current_page() {
    return get_option( 'hodima_schema_homepage_enable', '1' ) === '1';
}


if ( get_option( 'hodima_cleaner_rm', 'yes' ) === 'yes' ) {
    
    add_filter( 'rank_math/json_ld', function( $data, $jsonld ) {

        if ( ! is_array( $data ) ) {
            return $data;
        }

        // آیا اجازه داریم ImageObject رنک‌مث را حذف کنیم؟ فقط اگر ماژول اختصاصی
        // ما قطعاً همین صفحه را پوشش می‌دهد — در غیر این صورت، حذفش یعنی
        // این صفحه هیچ ImageObject‌ای در خروجی نهایی نخواهد داشت.
        $can_remove_image_object = hodima_our_image_schema_covers_current_page();

        // --- ۱. حذف Breadcrumb (فقط اگر ماژول اختصاصی ما واقعاً همین صفحه را پوشش می‌دهد) ---
        if ( isset( $data['BreadcrumbList'] ) && hodima_our_breadcrumb_covers_current_page() ) {
            unset( $data['BreadcrumbList'] );
        }

        // --- ۲. ردیاب و نابودگر عمیق (Deep Sweeper) ---
        // این حلقه، کل آرایه رنک‌مث را می‌گردد و هرجا Publisher یا Organization پنهان شده بود، آن را پاک می‌کند
        // (این دقیقاً همان چیزی است که جلوی ارور Duplicate URL را در صفحات آرشیو می‌گیرد)
        // فقط وقتی homepage-schema.php روشن است این حذف انجام می‌شود، چون
        // جایگزین Organization/WebSite فقط از همان فایل می‌آید.
        $can_remove_identity = hodima_our_identity_graph_covers_current_page();

        if ( $can_remove_identity ) {
            foreach ( $data as $node_key => $node_value ) {
                if ( is_array( $node_value ) ) {
                    if ( isset( $node_value['publisher'] ) ) unset( $data[$node_key]['publisher'] );
                    if ( isset( $node_value['Organization'] ) ) unset( $data[$node_key]['Organization'] );
                }
            }

            // --- ۳. حذف هویت‌های اصلی مزاحم ---
            $org_keys = ['Organization', 'LocalBusiness', 'WholesaleStore', 'Store', 'publisher', 'ProfilePage', 'WebSite'];
            foreach ($org_keys as $key) {
                if (isset($data[$key])) unset($data[$key]);
            }
        }

        // --- ۴. پاکسازی صفحه اصلی ---
        // نکته مهم: قبلاً فقط WebPage پاک می‌شد، نه CollectionPage. اما در
        // صفحه اصلی، homepage-schema.php نوع CollectionPage تولید می‌کند و
        // اگر رنک‌مث هم مستقل خودش یک CollectionPage برای همان صفحه بسازد،
        // دو نود هم‌نوع روی یک صفحه چاپ می‌شود (دقیقاً همان «۲ آیتم» که در
        // اعتبارسنج schema.org دیده می‌شود).
        if ( ( is_front_page() || is_home() ) && $can_remove_identity ) {
            unset( $data['WebPage'] );
            unset( $data['CollectionPage'] );
            hodima_deep_remove_by_type( $data, 'CollectionPage' );
        }

        // --- ۵. پاکسازی صفحه محصول ---
        if ( is_singular( 'product' ) || ( function_exists( 'is_product' ) && is_product() ) ) {
            unset( $data['Product'] );
            unset( $data['Offer'] );
            if ( $can_remove_image_object ) unset( $data['ImageObject'] );
            if ( $can_remove_identity ) unset( $data['WebPage'] );
        }

        // --- ۶. پاکسازی صفحات مقالات ---
        if ( is_single() && 'post' === get_post_type() ) {
            unset( $data['Article'] );
            unset( $data['BlogPosting'] );
            unset( $data['NewsArticle'] );
            if ( $can_remove_image_object ) unset( $data['ImageObject'] );
            if ( $can_remove_identity ) unset( $data['WebPage'] );
        }

        // --- ۷. پاکسازی صفحات دسته‌بندی و آرشیو (از جمله آرشیو ویدئو) ---
        if ( is_archive() || is_tax() || is_category() || is_post_type_archive() ) {
            if ( hodima_our_category_schema_covers_current_page() ) {
                unset( $data['CollectionPage'] );
                hodima_deep_remove_by_type( $data, 'CollectionPage' ); // ایمنی مضاعف: حتی اگر زیر کلید دیگری پنهان باشد
            }
            if ( $can_remove_identity ) unset( $data['ProfilePage'] );
            if ( $can_remove_image_object ) unset( $data['ImageObject'] );
            if ( $can_remove_identity ) unset( $data['WebPage'] );
        }

        // --- ۸. پاکسازی صفحه ویدئو تکی ---
        if ( is_singular( 'video' ) ) {
            unset( $data['VideoObject'] );
            unset( $data['Video'] );
            unset( $data['Article'] );
            unset( $data['BlogPosting'] );
            unset( $data['NewsArticle'] );
            if ( $can_remove_image_object ) unset( $data['ImageObject'] );
            if ( $can_remove_identity ) unset( $data['WebPage'] );
        }

        return $data;

    }, 99, 2 );
}

/* =========================================================
 * 2. حذف اسکیماهای پیش‌فرض WooCommerce
 * ---------------------------------------------------------
 * 🛠️ باگ رفع‌شده: این حذف قبلاً فقط به سوییچ عمومی «hodima_cleaner_woo»
 * وابسته بود، نه به این‌که ماژول اختصاصی محصول (product-schema-pro.php)
 * واقعاً فعال باشد. اگر کسی سوییچ محصول را خاموش می‌کرد ولی این پاک‌کننده
 * را روشن نگه می‌داشت، خروجی پیش‌فرض ووکامرس هم حذف می‌شد و صفحه محصول
 * هیچ Product/Offer schema‌ای نداشت. حالا حذف فقط زمانی انجام می‌شود که
 * جایگزین واقعاً فعال باشد.
 * ========================================================= */
if ( get_option( 'hodima_cleaner_woo', 'yes' ) === 'yes' ) {
    add_action( 'init', function() {
        if (
            get_option( 'hodima_schema_product_enable', '1' ) === '1'
            && class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC() && isset( WC()->structured_data )
        ) {
            remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 );
            remove_action( 'woocommerce_email_order_details', array( WC()->structured_data, 'output_email_structured_data' ), 30 );
        }
    }, 20 );
}

/* =========================================================
 * 3. حذف کلاس hentry
 * ========================================================= */
if ( get_option( 'hodima_cleaner_hentry', 'yes' ) === 'yes' ) {
    add_filter( 'post_class', function( $classes ) {
        $key = array_search( 'hentry', $classes, true );
        if ( $key !== false ) {
            unset( $classes[ $key ] );
        }
        return $classes;
    } );
}
/* =========================================================
 * 4. پاکسازی دائمی متادیتای باقی‌مانده‌ی افزونه‌های سئوی حذف‌شده
 * ---------------------------------------------------------
 * وقتی یک افزونه‌ی سئو (مثلاً Rank Math) از سایت حذف می‌شود، معمولاً
 * ردیف‌هایی که در wp_postmeta / wp_termmeta نوشته بود (rank_math_robots،
 * rank_math_primary_category و...) پاک نمی‌شوند. مشکل اینجاست که
 * hodima_get_noindex_post_ids() در sitemap-core.php هنوز صریحاً دنبال
 * rank_math_robots با مقدار noindex می‌گردد؛ یعنی اگر پستی زمانی (حتی
 * سال‌ها پیش) از طریق آن افزونه noindex شده و متایش پاک نشده باشد، برای
 * همیشه از سایت‌مپ حذف می‌ماند — بدون این‌که راهی برای رفعش از پنل داشته
 * باشید، چون خود افزونه دیگر نصب نیست. این بخش امکان شمارش و پاکسازی
 * کامل این باقیمانده‌ها را از پنل «پاکسازی اسکیما» فراهم می‌کند.
 * ========================================================= */

// الگوی متاکی‌های شناخته‌شده‌ی افزونه‌های سئوی رایج که ممکن است بعد از
// حذف افزونه، ردی از خودشان در دیتابیس باقی بگذارند.
function hodima_orphan_seo_meta_patterns() {
    return [
        'rank\\_math\\_%', // Rank Math (rank_math_robots, rank_math_primary_*, rank_math_title, ...)
    ];
}

function hodima_count_orphan_seo_meta() {
    global $wpdb;
    $total = ['postmeta' => 0, 'termmeta' => 0];
    foreach ( hodima_orphan_seo_meta_patterns() as $pattern ) {
        $total['postmeta'] += (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $pattern
        ) );
        $total['termmeta'] += (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $pattern
        ) );
    }
    return $total;
}

function hodima_purge_orphan_seo_meta() {
    global $wpdb;
    $deleted = 0;
    foreach ( hodima_orphan_seo_meta_patterns() as $pattern ) {
        $deleted += (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $pattern
        ) );
        $deleted += (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $pattern
        ) );
    }

    // چون این پاکسازی می‌تواند تعداد محصولات/پست‌های noindex شده را تغییر
    // دهد، کش لیست noindex و کش کل سایت‌مپ باید بلافاصله باطل شوند.
    delete_transient( 'hodima_noindex_ids_' . md5( (string) get_option( 'hodima_sitemap_cache_ver', 1 ) ) );
    if ( function_exists( 'hodima_sitemap_clear_cache' ) ) {
        hodima_sitemap_clear_cache();
    }

    return $deleted;
}

/* =========================================================
 * 5. نگهبان سراسری تکرار اسکیما — حذف شد
 * ---------------------------------------------------------
 * این بخش قبلا کل خروجی <head> را با ob_start بافر می‌کرد و با regex
 * هر بلوک JSON-LD را می‌خواند؛ هر نودی که @type و @id/url/name آن قبلا
 * دیده شده بود *کامل* حذف می‌شد. دو ایراد داشت:
 *
 *   - حذف اشتباه: دو نود هم‌شناسه با محتوای متفاوت (مثلا FAQPage «#faq»
 *     سیستم رسانه و FAQPage «#faq» ماژول AEO) تکراری فرض می‌شدند و
 *     سوال‌های دومی هرگز به گوگل نمی‌رسید.
 *   - فقط <head> را می‌دید؛ اسکیمای فوتر و بدنه بررسی نمی‌شد.
 *
 * جایگزین: گراف واحد hodima-core (includes/schema-graph.php). همه
 * سازنده‌ها نودهایشان را با hodima_schema_add() می‌دهند؛ نودهای هم‌شناسه
 * *ادغام* می‌شوند (نه حذف) و یک @graph در انتهای صفحه چاپ می‌شود.
 * گزینه hodima_cleaner_dedupe_guard دیگر خوانده نمی‌شود.
 * ========================================================= */
