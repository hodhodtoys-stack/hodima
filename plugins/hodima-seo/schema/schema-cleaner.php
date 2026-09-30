<?php
/**
 * HOOK SCHEMA CLEANER
 * Path: wp-content/plugins/hodima-seo/schema/schema-cleaner.php
 *
 * - حذف اسکیمای پیش‌فرض ووکامرس (جایگزین: product-schema-pro.php)
 * - حذف کلاس hentry
 * - پاکسازی یک‌باره داده‌های باقی‌مانده Rank Math
 *
 * بخش «مهار Rank Math» (فیلتر rank_math/json_ld و گزینه hodima_cleaner_rm)
 * حذف شد: Rank Math از سایت پاک شده و آن فیلتر هرگز اجرا نمی‌شد.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
 * 2. حذف اسکیماهای پیش‌فرض WooCommerce
 * ---------------------------------------------------------
 * باگ رفع‌شده: این حذف قبلاً فقط به سوییچ عمومی «hodima_cleaner_woo»
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
 * 4. پاکسازی یک‌باره داده‌های باقی‌مانده Rank Math
 * ---------------------------------------------------------
 * Rank Math از سایت حذف شده ولی داده‌هایش در دیتابیس مانده بود:
 * متای نوشته/دسته/کاربر (rank_math_*)، گزینه‌ها، جدول‌ها و کارهای
 * زمان‌بندی‌شده. قبلا یک دکمه «پاکسازی دائمی متادیتا» در پنل بود؛ حالا
 * یک بار خودکار (اولین بازدید مدیر از پیشخوان بعد از به‌روزرسانی) انجام
 * می‌شود و بخش پنل حذف شد.
 *
 * پیش از حذف: «دسته اصلی» که در Rank Math برای نوشته‌ها و محصولات
 * انتخاب شده بود (rank_math_primary_{taxonomy}) به «_hodima_primary_{taxonomy}»
 * منتقل می‌شود — breadcrumb-schema.php همین را می‌خواند، پس مسیر
 * راهنمای محصولات عوض نمی‌شود.
 *
 * اگر Rank Math هنوز نصب باشد (پوشه افزونه یا ثابت نسخه‌اش)، کاری انجام
 * نمی‌شود. نتیجه در گزینه hodima_seo_rankmath_cleanup ثبت و در صفحه
 * «اسکیما ← پاکسازی» نمایش داده می‌شود.
 * ========================================================= */
add_action( 'admin_init', 'hodima_seo_rankmath_cleanup' );

function hodima_seo_rankmath_cleanup(): void {

    if ( wp_doing_ajax() || wp_doing_cron() || false !== get_option( 'hodima_seo_rankmath_cleanup', false ) ) {
        return;
    }

    if ( defined( 'RANK_MATH_VERSION' ) || is_dir( WP_PLUGIN_DIR . '/seo-by-rank-math' ) ) {
        return; // هنوز نصب است؛ داده‌اش مال خودش است
    }

    // قفل ساده: دو درخواست هم‌زمان پیشخوان دو بار اجرا نکنند
    if ( ! add_option( 'hodima_seo_rankmath_cleanup_lock', time(), '', false ) ) {
        if ( time() - (int) get_option( 'hodima_seo_rankmath_cleanup_lock', 0 ) < 10 * MINUTE_IN_SECONDS ) {
            return;
        }
        update_option( 'hodima_seo_rankmath_cleanup_lock', time(), false );
    }

    global $wpdb;

    $like   = $wpdb->esc_like( 'rank_math_' ) . '%';
    $result = [ 'time' => time(), 'primary' => 0, 'meta' => 0, 'options' => 0, 'tables' => [], 'cron' => 0 ];

    // ۱. انتقال دسته اصلی (فقط اگر مقدار معتبر است و قبلا منتقل نشده)
    $primary_like = $wpdb->esc_like( 'rank_math_primary_' ) . '%';
    $rows         = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $primary_like ) );

    foreach ( (array) $rows as $row ) {
        $taxonomy = substr( (string) $row->meta_key, strlen( 'rank_math_primary_' ) );
        $term_id  = (int) $row->meta_value;
        if ( '' !== $taxonomy && $term_id > 0 && '' === (string) get_post_meta( (int) $row->post_id, "_hodima_primary_{$taxonomy}", true ) ) {
            update_post_meta( (int) $row->post_id, "_hodima_primary_{$taxonomy}", $term_id );
            $result['primary']++;
        }
    }

    // ۲. متای نوشته، دسته و کاربر
    foreach ( [ $wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta ] as $table ) {
        $result['meta'] += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE meta_key LIKE %s", $like ) );
    }

    // ۳. گزینه‌ها و کش‌های موقت (rank_math_*، rank-math-*، _transient_rank_math_*)
    $result['options'] = (int) $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '%' . $wpdb->esc_like( 'rank_math' ) . '%',
        '%' . $wpdb->esc_like( 'rank-math' ) . '%'
    ) );

    // ۴. جدول‌های اختصاصی ({prefix}rank_math_*: ریدایرکت‌ها، لاگ ۴۰۴، لینک‌های داخلی، آمار)
    $tables = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'rank_math_' ) . '%' ) );
    foreach ( $tables as $table ) {
        if ( str_starts_with( (string) $table, $wpdb->prefix . 'rank_math_' ) && 1 === preg_match( '/^[A-Za-z0-9_]+$/', (string) $table ) ) {
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL -- نام جدول بالا اعتبارسنجی شد
            $result['tables'][] = (string) $table;
        }
    }

    // ۵. کارهای زمان‌بندی‌شده بی‌صاحب (rank_math/…، rank_math_…)
    foreach ( (array) _get_cron_array() as $events ) {
        foreach ( array_keys( (array) $events ) as $hook ) {
            if ( str_starts_with( (string) $hook, 'rank_math' ) ) {
                $result['cron'] += max( 0, (int) wp_unschedule_hook( (string) $hook ) );
            }
        }
    }

    // گزینه قدیمی خود هدیما (کلید «مهار Rank Math» در پنل پاکسازی)
    delete_option( 'hodima_cleaner_rm' );

    // noindex قدیمی Rank Math دیگر خوانده نمی‌شود؛ سایت‌مپ از نو ساخته شود
    if ( function_exists( 'hodima_sitemap_clear_cache' ) ) {
        hodima_sitemap_clear_cache();
    }

    update_option( 'hodima_seo_rankmath_cleanup', $result, false );
    delete_option( 'hodima_seo_rankmath_cleanup_lock' );
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
