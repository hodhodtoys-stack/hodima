<?php
/**
 * Admin View: XML Sitemap Settings
 * Path: wp-content/plugins/hodima-seo/schema/admin/views/view-sitemap.php
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

    $message = '<div class="notice notice-success is-dismissible"><p>تنظیمات نقشه سایت ذخیره شد، کش بازسازی گردید و رول‌های آدرس به‌روزرسانی شدند.</p></div>';
}

// ۲. پاکسازی کش نقشه سایت‌ها
if ( isset($_POST['hodima_clear_sitemap_cache']) && check_admin_referer('hodima_sitemap_nonce_action', 'hodima_sitemap_nonce') ) {
    if ( function_exists('hodima_sitemap_clear_cache') ) {
        hodima_sitemap_clear_cache();
    }
    $message = '<div class="notice notice-success is-dismissible"><p>کش تمامی نقشه‌های سایت با موفقیت پاکسازی شد.</p></div>';
}

$status        = get_option('hodima_sitemap_status', '1');
$saved_pts     = get_option('hodima_sitemap_post_types', ['post', 'page', 'product', 'video']);
$saved_taxs    = get_option('hodima_sitemap_taxonomies', ['category', 'product_cat']);
$limit         = get_option('hodima_sitemap_links_limit', '1000');

$all_post_types = get_post_types(['public' => true], 'objects');
$all_taxonomies = get_taxonomies(['public' => true], 'objects');

$sitemap_url = site_url('/sitemap.xml');

// خلاصه برای کارت‌های آمار (فقط نوع‌هایی که واقعا وجود دارند شمرده می‌شوند)
$live_pts  = array_values( array_filter( (array) $saved_pts, static fn( $name ) => isset( $all_post_types[ $name ] ) ) );
$live_taxs = array_values( array_filter( (array) $saved_taxs, static fn( $name ) => isset( $all_taxonomies[ $name ] ) ) );

/** نام‌ها را هر کدام در یک code (نامی که ممکن است حرف خاص داشته باشد escape می‌شود). */
$hodima_codes = static fn( array $names ): string => implode( ' ', array_map( static fn( $name ): string => '<code class="hds-code">' . esc_html( (string) $name ) . '</code>', $names ) );

hodima_view_header(
    'نقشه سایت اختصاصی (XML Sitemap)',
    'نقشه سایت یکپارچه با صفحه‌بندی، تصاویر، ویدیو و متادیتای پیشرفته، منطبق با به‌روزرسانی‌های ایندکس گوگل.',
    'dashicons-networking'
);
?>

<?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput -- پیام ثابت همین فایل ?>

<div class="hds-ui"><div class="hds-page__body">

<?php
hodima_seo_ui_stats( [
    [
        'label' => 'موتور نقشه سایت',
        'value' => '1' === (string) $status ? '<span class="hds-pill">فعال</span>' : '<span class="hds-pill hds-pill--warn">غیرفعال</span>',
        'sub'   => '1' === (string) $status ? 'نقشه با کش سریع سرو می‌شود' : 'نقشه‌ای ساخته نمی‌شود',
    ],
    [
        'label' => 'پست‌تایپ در نقشه',
        'value' => esc_html( number_format_i18n( count( $live_pts ) ) ),
        'sub'   => $hodima_codes( array_slice( $live_pts, 0, 4 ) ),
    ],
    [
        'label' => 'طبقه‌بندی در نقشه',
        'value' => esc_html( number_format_i18n( count( $live_taxs ) ) ),
        'sub'   => $hodima_codes( array_slice( $live_taxs, 0, 3 ) ),
    ],
    [
        'label' => 'لینک در هر نقشه',
        'value' => esc_html( number_format_i18n( (int) $limit ) ),
        'sub'   => 'صفحه‌بندی برای سبکی سرور',
    ],
] );

hodima_seo_ui_card_open( 'آدرس نقشه سایت برای معرفی به گوگل', 'این آدرس را در سرچ کنسول (Sitemaps) ثبت کنید. ویدیوها و مقالات درون همین نقشه (تگ‌های &lt;video:video&gt;) معرفی می‌شوند؛ ثبت آدرس جداگانه لازم نیست.', 'dashicons-admin-links' );
?>
    <div class="hds-action">
        <label class="screen-reader-text" for="hodima-sitemap-url">آدرس نقشه سایت</label>
        <input type="text" id="hodima-sitemap-url" class="hds-input" dir="ltr" readonly value="<?php echo esc_attr( $sitemap_url ); ?>">
        <button type="button" class="hds-btn hds-btn--outline" data-hds-copy="<?php echo esc_attr( $sitemap_url ); ?>">کپی آدرس</button>
        <a class="hds-btn hds-btn--outline" href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" rel="noopener">مشاهده</a>
    </div>
<?php hodima_seo_ui_card_close(); ?>

<form method="post" action="">
    <?php wp_nonce_field( 'hodima_sitemap_nonce_action', 'hodima_sitemap_nonce' ); ?>
    <input type="hidden" name="hodima_save_sitemap_settings" value="1">

    <?php hodima_seo_ui_card_open( 'تنظیمات نقشه سایت', '', 'dashicons-admin-settings' ); ?>
        <div class="hds-row">
            <div class="hds-field">
                <label class="hds-toggle">
                    <input type="checkbox" class="hds-switch" role="switch" name="sitemap_status" value="1" <?php checked( $status, '1' ); ?>>
                    <span>فعال‌سازی موتور نقشه سایت هدیما</span>
                </label>
            </div>
            <div class="hds-field">
                <label class="hds-field__label" for="sitemap_links_limit">تعداد لینک در هر نقشه (صفحه‌بندی)</label>
                <div class="hds-unit">
                    <input type="number" class="hds-input" name="sitemap_links_limit" id="sitemap_links_limit" value="<?php echo esc_attr( $limit ); ?>" min="100" max="5000">
                    <span>لینک</span>
                </div>
                <p class="hds-field__help">برای سبک ماندن سرور؛ پیشنهاد: ۱۰۰۰ (بین ۱۰۰ تا ۵۰۰۰).</p>
            </div>
        </div>

        <div class="hds-groups">
            <fieldset class="hds-fieldset">
                <legend class="hds-groups__title">پست‌تایپ‌هایی که در نقشه بیایند</legend>
                <p class="hds-field__help">موارد noindex یا دارای رمز عبور خودکار کنار گذاشته می‌شوند.</p>
                <div class="hds-chips">
                    <?php foreach ( $all_post_types as $pt ) : ?>
                        <?php if ( 'attachment' === $pt->name ) { continue; } ?>
                        <label class="hds-chip">
                            <input type="checkbox" name="sitemap_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $saved_pts, true ) ); ?>>
                            <span><?php echo esc_html( $pt->labels->name ); ?></span>
                            <code class="hds-code"><?php echo esc_html( $pt->name ); ?></code>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="hds-fieldset">
                <legend class="hds-groups__title">طبقه‌بندی‌هایی که در نقشه بیایند</legend>
                <p class="hds-field__help">صفحه‌بندی‌شده و همراه رسانه‌هایشان در نقشه می‌آیند.</p>
                <div class="hds-chips">
                    <?php foreach ( $all_taxonomies as $tax ) : ?>
                        <label class="hds-chip">
                            <input type="checkbox" name="sitemap_taxonomies[]" value="<?php echo esc_attr( $tax->name ); ?>" <?php checked( in_array( $tax->name, $saved_taxs, true ) ); ?>>
                            <span><?php echo esc_html( $tax->labels->name ); ?></span>
                            <code class="hds-code"><?php echo esc_html( $tax->name ); ?></code>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>

        <div class="hds-foot">
            <?php // دکمه ذخیره اول می‌آید تا Enter در فرم، ذخیره کند نه پاک‌سازی کش ?>
            <button type="submit" class="hds-btn">ذخیره تنظیمات</button>
            <button
                type="submit"
                name="hodima_clear_sitemap_cache"
                class="hds-btn hds-btn--outline"
                onclick="return confirm('آیا از پاک کردن کش تمام نسخه‌های نقشه سایت مطمئن هستید؟');"
            >پاک‌سازی دستی کش سایت‌مپ</button>
        </div>
    <?php hodima_seo_ui_card_close(); ?>
</form>

</div></div>

<?php hodima_view_footer(); ?>
