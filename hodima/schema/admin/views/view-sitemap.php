<?php
/**
 * Admin View: XML Sitemap Settings
 * Path: wp-content/themes/hodima/schema/views/view-sitemap.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$message = '';

// ۱. ذخیره تنظیمات عمومی و ویدیو
if ( isset($_POST['hodima_save_sitemap_settings']) && check_admin_referer('hodima_sitemap_nonce_action', 'hodima_sitemap_nonce') ) {

    $status = isset($_POST['sitemap_status']) ? '1' : '0';
    update_option('hodima_sitemap_status', $status);

    $post_types = isset($_POST['sitemap_post_types']) ? array_map('sanitize_text_field', wp_unslash( (array) $_POST['sitemap_post_types'] )) : [];
    update_option('hodima_sitemap_post_types', $post_types);

    $taxonomies = isset($_POST['sitemap_taxonomies']) ? array_map('sanitize_text_field', wp_unslash( (array) $_POST['sitemap_taxonomies'] )) : [];
    update_option('hodima_sitemap_taxonomies', $taxonomies);

    $limit = isset($_POST['sitemap_links_limit']) ? absint($_POST['sitemap_links_limit']) : 1000;
    if ( $limit < 100 ) $limit = 100;
    if ( $limit > 5000 ) $limit = 5000;
    update_option('hodima_sitemap_links_limit', $limit);

    if ( function_exists('hodima_sitemap_init_rules') ) {
        hodima_sitemap_init_rules();
        flush_rewrite_rules();
    }

    if ( function_exists('hodima_sitemap_clear_cache') ) {
        hodima_sitemap_clear_cache();
    }

    $message = '<div class="notice notice-success is-dismissible" style="border-right: 4px solid #25316a; background: #fff; border-left: none; border-top: none; border-bottom: none;"><p style="font-weight: inherit;">تنظیمات نقشه سایت ذخیره شد، کش بازسازی گردید و رول‌های آدرس به‌روزرسانی شدند.</p></div>';
}

// ۲. پاکسازی کش نقشه سایت‌ها
if ( isset($_POST['hodima_clear_sitemap_cache']) && check_admin_referer('hodima_sitemap_nonce_action', 'hodima_sitemap_nonce') ) {
    if ( function_exists('hodima_sitemap_clear_cache') ) {
        hodima_sitemap_clear_cache();
    }
    $message = '<div class="notice notice-success is-dismissible" style="border-right: 4px solid #607bbd; background: #fff; border-left: none; border-top: none; border-bottom: none;"><p style="font-weight: inherit;">کش تمامی نقشه‌های سایت با موفقیت پاکسازی شد.</p></div>';
}

$status        = get_option('hodima_sitemap_status', '1');
$saved_pts     = get_option('hodima_sitemap_post_types', ['post', 'page', 'product', 'video']);
$saved_taxs    = get_option('hodima_sitemap_taxonomies', ['category', 'product_cat']);
$limit         = get_option('hodima_sitemap_links_limit', '1000');

$all_post_types = get_post_types(['public' => true], 'objects');
$all_taxonomies = get_taxonomies(['public' => true], 'objects');

$sitemap_url = site_url('/sitemap.xml');

hodima_view_header(
    'نقشه سایت اختصاصی (XML Sitemap)',
    'مدیریت قدرتمند و یکپارچه نقشه سایت با پشتیبانی از صفحه‌بندی، تصاویر، ویدیو، متادیتای پیشرفته (منطبق با آپدیت‌های جدید ایندکس گوگل).'
);
?>

<div class="h-card">
    <?php echo $message; ?>

    <!-- نمایش متمرکز آدرس‌های خروجی نقشه‌ها -->
    <div class="notice notice-info" style="border-right: 4px solid #25316a; background: #fff; padding: 15px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(37,49,106,.04); border-left: none; border-top: none; border-bottom: none;">
        <h3 style="margin-top: 0; color: #25316a; font-size: 15px; font-weight: inherit;">آدرس‌ نقشه سایت جهت معرفی به گوگل:</h3>
        
        <div style="margin-bottom: 12px;">
            <span style="display:inline-block; min-width: 170px; font-weight: bold;">نقشه سایت یکپارچه (Index):</span>
            <code style="font-size: 14px; padding: 6px 10px; display: inline-block; background: #f8fafc; border-radius: 4px; border: 1px solid #c1c9ec; color: #25316a; direction: ltr;">
                <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" style="text-decoration: none; color: inherit;">
                    <?php echo esc_html($sitemap_url); ?>
                </a>
            </code>
        </div>
        
        <p class="description" style="margin-top: 12px; border-top: 1px solid #c1c9ec; padding-top: 10px;">
            ویدیوهای سیستم فروشگاهی اختصاصی شما و مقالات، اکنون به صورت کاملاً ساختاریافته درون همین نقشه سایت یکپارچه (تگ‌های &lt;video:video&gt;) به موتورهای جستجو معرفی می‌شوند. نیازی به ثبت آدرس مجزا نیست.
        </p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('hodima_sitemap_nonce_action', 'hodima_sitemap_nonce'); ?>
        <input type="hidden" name="hodima_save_sitemap_settings" value="1">

        <!-- کارت اول: تنظیمات عمومی -->
        <div class="h-card" style="margin-bottom: 20px;">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0; color: #25316a;">تنظیمات عمومی</h3>

            <div class="h-form-group" style="margin-bottom: 15px;">
                <label class="h-checkbox-label">
                    <input type="checkbox" name="sitemap_status" value="1" <?php checked($status, '1'); ?>>
                    <span style="font-weight: inherit;">فعال‌سازی موتور نقشه سایت هدیما</span>
                </label>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #c1c9ec; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="sitemap_links_limit" style="display:block; margin-bottom: 5px; font-weight: inherit;">
                    تعداد لینک در هر نقشه سایت (Pagination)
                </label>
                <input
                    type="number"
                    name="sitemap_links_limit"
                    id="sitemap_links_limit"
                    class="small-text hodima-input"
                    value="<?php echo esc_attr($limit); ?>"
                    min="100"
                    max="5000"
                    style="border: 1px solid #c1c9ec; outline-color: #25316a;"
                >
                <p class="description">
                    جهت جلوگیری از فشار به سرور، لینک‌ها صفحه‌بندی می‌شوند. مقدار پیشنهادی: <strong>1000</strong>
                </p>
            </div>
        </div>

        <!-- کارت سوم: انتخاب پست‌تایپ‌ها -->
        <div class="h-card" style="margin-bottom: 20px;">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0; color: #25316a;">پست‌تایپ‌ها (Post Types)</h3>
            <p class="description" style="margin-bottom: 15px; font-weight: inherit;">
                آیتم‌هایی که به صورت دستی Noindex شده باشند یا دارای کلمه عبور باشند، به طور خودکار فیلتر می‌شوند. (شامل بخش‌های سیستم فروشگاهی اختصاصی شما)
            </p>

            <div style="display: flex; flex-wrap: wrap; gap: 15px; background: #f8fafc; padding: 15px; border: 1px dashed #c1c9ec; border-radius: 6px;">
                <?php foreach ( $all_post_types as $pt ) : ?>
                    <?php if ( $pt->name === 'attachment' ) continue; ?>
                    <label style="display: flex; align-items: center; gap: 5px; min-width: 170px; cursor: pointer;">
                        <input
                            type="checkbox"
                            name="sitemap_post_types[]"
                            value="<?php echo esc_attr($pt->name); ?>"
                            <?php checked(in_array($pt->name, $saved_pts, true)); ?>
                        >
                        <span style="font-weight: inherit; color: #25316a;">
                            <?php echo esc_html($pt->labels->name); ?>
                            <code style="font-size: 11px; color: #607bbd; background: transparent; border: none;">(<?php echo esc_html($pt->name); ?>)</code>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- کارت چهارم: انتخاب طبقه‌بندی‌ها -->
        <div class="h-card" style="margin-bottom: 20px;">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0; color: #25316a;">دسته‌بندی‌ها و Taxonomyها</h3>
            <p class="description" style="margin-bottom: 15px; font-weight: inherit;">
                Taxonomyهایی که انتخاب شوند، به صورت صفحه‌بندی شده همراه با رسانه‌های زیرمجموعه در سایت‌مپ لود می‌شوند.
            </p>

            <div style="display: flex; flex-wrap: wrap; gap: 15px; background: #f8fafc; padding: 15px; border: 1px dashed #c1c9ec; border-radius: 6px;">
                <?php foreach ( $all_taxonomies as $tax ) : ?>
                    <label style="display: flex; align-items: center; gap: 5px; min-width: 170px; cursor: pointer;">
                        <input
                            type="checkbox"
                            name="sitemap_taxonomies[]"
                            value="<?php echo esc_attr($tax->name); ?>"
                            <?php checked(in_array($tax->name, $saved_taxs, true)); ?>
                        >
                        <span style="font-weight: inherit; color: #25316a;">
                            <?php echo esc_html($tax->labels->name); ?>
                            <code style="font-size: 11px; color: #607bbd; background: transparent; border: none;">(<?php echo esc_html($tax->name); ?>)</code>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ابزارها و دکمه‌های کنترلی -->
        <div class="h-card" style="margin-bottom: 20px;">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0; color: #25316a;">ابزارها و ذخیره‌سازی</h3>

            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <!-- دکمه اصلی ذخیره -->
                <button type="submit" class="button button-primary" style="background: #25316a; border-color: #25316a;">
                    ذخیره تنظیمات
                </button>

                <!-- دکمه پاکسازی کش -->
                <button
                    type="submit"
                    name="hodima_clear_sitemap_cache"
                    class="button"
                    style="border-color: #607bbd; color: #607bbd; background: #fff;"
                    onclick="return confirm('آیا از پاک کردن کش تمام نسخه‌های نقشه سایت مطمئن هستید؟');"
                >
                    <span class="dashicons dashicons-update" style="line-height: 1.5; font-size: 16px;"></span>
                    <span style="font-weight: inherit;">پاک‌سازی دستی کش سایت‌مپ</span>
                </button>
            </div>
        </div>
    </form>
</div>

<?php hodima_view_footer(); ?>