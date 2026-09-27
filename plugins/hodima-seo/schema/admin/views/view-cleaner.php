<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}

$notice_msg = '';

// ذخیره فرم
if ( isset( $_POST['hodima_save_schema_cleaner'] ) ) {
    check_admin_referer( 'hodima_schema_cleaner_nonce' );

    $cleaner_rm     = isset( $_POST['hodima_cleaner_rm'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_cleaner_rm'] ) ) : 'yes';
    $cleaner_woo    = isset( $_POST['hodima_cleaner_woo'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_cleaner_woo'] ) ) : 'yes';
    $cleaner_hentry = isset( $_POST['hodima_cleaner_hentry'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_cleaner_hentry'] ) ) : 'yes';
    $dedupe_guard   = isset( $_POST['hodima_cleaner_dedupe_guard'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_cleaner_dedupe_guard'] ) ) : 'yes';

    $allowed = array( 'yes', 'no' );

    $cleaner_rm     = in_array( $cleaner_rm, $allowed, true ) ? $cleaner_rm : 'yes';
    $cleaner_woo    = in_array( $cleaner_woo, $allowed, true ) ? $cleaner_woo : 'yes';
    $cleaner_hentry = in_array( $cleaner_hentry, $allowed, true ) ? $cleaner_hentry : 'yes';
    $dedupe_guard   = in_array( $dedupe_guard, $allowed, true ) ? $dedupe_guard : 'yes';

    update_option( 'hodima_cleaner_rm', $cleaner_rm );
    update_option( 'hodima_cleaner_woo', $cleaner_woo );
    update_option( 'hodima_cleaner_hentry', $cleaner_hentry );
    update_option( 'hodima_cleaner_dedupe_guard', $dedupe_guard );

    $notice_msg = '<div class="notice notice-success is-dismissible" style="margin-bottom:20px; border-radius:8px; border-right: 4px solid #25316a; background: #fff;"><p style="font-weight: inherit;">✅ تنظیمات پاکسازی اسکیما با موفقیت به‌روزرسانی شد.</p></div>';
}

// پاکسازی دائمی متادیتای باقی‌مانده‌ی افزونه‌های سئوی حذف‌شده (مثل Rank Math)
if ( isset( $_POST['hodima_purge_orphan_seo_meta'] ) ) {
    check_admin_referer( 'hodima_schema_cleaner_nonce' );

    $deleted_count = function_exists( 'hodima_purge_orphan_seo_meta' ) ? hodima_purge_orphan_seo_meta() : 0;

    $notice_msg = '<div class="notice notice-success is-dismissible" style="margin-bottom:20px; border-radius:8px; border-right: 4px solid #25316a; background: #fff;"><p style="font-weight: inherit;">🧹 ' . intval( $deleted_count ) . ' ردیف متادیتای باقی‌مانده حذف شد و کش سایت‌مپ نیز پاکسازی گردید. اگر محصولی به دلیل این باقیمانده‌ها از سایت‌مپ حذف شده بود، اکنون بازمی‌گردد.</p></div>';
}

$orphan_meta_counts = function_exists( 'hodima_count_orphan_seo_meta' ) ? hodima_count_orphan_seo_meta() : [ 'postmeta' => 0, 'termmeta' => 0 ];
$orphan_meta_total  = $orphan_meta_counts['postmeta'] + $orphan_meta_counts['termmeta'];

// مقادیر فعلی
$cleaner_rm     = get_option( 'hodima_cleaner_rm', 'yes' );
$cleaner_woo    = get_option( 'hodima_cleaner_woo', 'yes' );
$cleaner_hentry = get_option( 'hodima_cleaner_hentry', 'yes' );
$dedupe_guard   = get_option( 'hodima_cleaner_dedupe_guard', 'yes' );

// هدر
hodima_view_header(
    'جراحی و پاکسازی هوشمند اسکیماها',
    'کنترل تداخل اسکیماهای افزونه‌های دیگر مانند Rank Math و WooCommerce با اسکیماهای اختصاصی سایت.',
    '🧹'
);
?>

<div class="h-card">

    <?php echo $notice_msg; ?>

    <div style="background: #f8fafc; border-right: 4px solid #25316a; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
        <h4 style="margin: 0 0 10px 0; color: #25316a; font-weight: inherit; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">🛡️</span> دیوار آتشین اسکیما (ورژن ۵.۱)
        </h4>
        <p style="margin: 0; font-size: 13px; color: #444; line-height: 1.6; font-weight: inherit;">
            اگر از افزونه‌های سئو مثل Rank Math استفاده می‌کنید، ممکن است در صفحات سایت اسکیماهای تکراری تولید شود (که باعث خطای سرچ کنسول می‌شود). با فعال‌سازی گزینه‌های زیر، اسکیماهای عمومی، گراف‌های هویتی تداخلی و مشکل <strong>Duplicate URL</strong> در دسته‌بندی‌ها به طور کامل حذف می‌شوند تا فقط گراف دانش یکپارچه و اختصاصی شما به گوگل معرفی شود.
        </p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field( 'hodima_schema_cleaner_nonce' ); ?>

        <table class="form-table">
            <tr valign="top">
                <th scope="row" style="font-weight: inherit;">مهار هوشمند Rank Math</th>
                <td>
                    <select name="hodima_cleaner_rm" style="min-width:150px; border-radius: 6px;" class="hodima-input">
                        <option value="yes" <?php selected( $cleaner_rm, 'yes' ); ?>>✅ فعال (پیشنهادی)</option>
                        <option value="no" <?php selected( $cleaner_rm, 'no' ); ?>>❌ غیرفعال</option>
                    </select>
                    <p class="description">
                        حذف اسکیماهای هویتی مزاحم (مانند LocalBusiness و Organization) در کل سایت، رفع ارور Duplicate در دسته‌بندی‌ها و پاکسازی اسکیماهای تکراری مقالات، محصولات و ویدئوها.
                    </p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row" style="font-weight: inherit;">حذف اسکیماهای پیش‌فرض ووکامرس</th>
                <td>
                    <select name="hodima_cleaner_woo" style="min-width:150px; border-radius: 6px;" class="hodima-input">
                        <option value="yes" <?php selected( $cleaner_woo, 'yes' ); ?>>✅ فعال (پیشنهادی)</option>
                        <option value="no" <?php selected( $cleaner_woo, 'no' ); ?>>❌ غیرفعال</option>
                    </select>
                    <p class="description">
                        غیرفعال‌سازی اسکیماهای پایه‌ای WooCommerce برای اولویت دادن به اسکیمای اختصاصی و حرفه‌ای محصولات شما.
                    </p>
                </td>
            </tr>

            <tr valign="top" style="border-top:1px dashed #e2e8f0;">
                <th scope="row" style="padding-top:20px; font-weight: inherit;">حذف کلاس hentry قالب</th>
                <td style="padding-top:20px;">
                    <select name="hodima_cleaner_hentry" style="min-width:150px; border-radius: 6px;" class="hodima-input">
                        <option value="yes" <?php selected( $cleaner_hentry, 'yes' ); ?>>✅ فعال (پیشنهادی)</option>
                        <option value="no" <?php selected( $cleaner_hentry, 'no' ); ?>>❌ غیرفعال</option>
                    </select>
                    <p class="description">
                        حذف کلاس <code>hentry</code> از ساختار HTML قالب برای جلوگیری از خطاهای رایج <code>Missing author</code> و <code>Missing updated</code> در گوگل سرچ کنسول.
                    </p>
                </td>
            </tr>

            <tr valign="top" style="border-top:1px dashed #e2e8f0;">
                <th scope="row" style="padding-top:20px; font-weight: inherit;">نگهبان سراسری تکرار اسکیما</th>
                <td style="padding-top:20px;">
                    <select name="hodima_cleaner_dedupe_guard" style="min-width:150px; border-radius: 6px;" class="hodima-input">
                        <option value="yes" <?php selected( $dedupe_guard, 'yes' ); ?>>✅ فعال (پیشنهادی)</option>
                        <option value="no" <?php selected( $dedupe_guard, 'no' ); ?>>❌ غیرفعال</option>
                    </select>
                    <p class="description">
                        کل خروجی &lt;head&gt; صفحه را — صرف‌نظر از این‌که هر بلوک اسکیما از کجا آمده (این پوشه، قالب، یا افزونه‌ی دیگری) — درست قبل از ارسال به مرورگر اسکن می‌کند و هر نود دقیقاً تکراری (همان @type + همان شناسه/آدرس) را حذف می‌کند. این همان چیزی است که خطای «CollectionPage: 2 ITEMS» یا مشابه آن در اعتبارسنج schema.org را رفع می‌کند. کاملاً ایمن (در صورت هر خطایی، خروجی اصلی دست‌نخورده باقی می‌ماند) — اگر با این حال به مشکلی برخوردید، از همین‌جا غیرفعالش کنید.
                    </p>
                </td>
            </tr>
        </table>

        <?php hodima_view_form_footer(); ?>
        <input type="hidden" name="hodima_save_schema_cleaner" value="1">
    </form>

    <!-- کارت پاکسازی دائمی باقیمانده‌ی افزونه‌های سئوی حذف‌شده -->
    <div class="h-card" style="margin-top: 25px; border-top: 2px dashed #e2e8f0; padding-top: 20px;">
        <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0; color: #b91c1c;">🗑️ پاکسازی دائمی متادیتای باقیمانده (Rank Math و مشابه)</h3>
        <p class="description" style="margin-bottom: 15px;">
            وقتی یک افزونه‌ی سئو مثل Rank Math از سایت حذف می‌شود، معمولاً اطلاعاتی که روی تک‌تک محصولات/مقالات نوشته بود
            (مثل وضعیت noindex یا دسته‌بندی اصلی) در دیتابیس باقی می‌ماند. این باقیمانده‌ها می‌توانند باعث شوند برخی
            محصولات همچنان از سایت‌مپ حذف بمانند، بدون این‌که راهی برای رفعشان از پنل داشته باشید.
        </p>

        <?php if ( $orphan_meta_total > 0 ) : ?>
            <div class="notice notice-warning" style="border-right: 4px solid #d97706; background: #fffbeb; padding: 12px 15px; margin-bottom: 15px; border-left: none; border-top: none; border-bottom: none;">
                <p style="font-weight: inherit; margin: 0;">
                    در حال حاضر <strong><?php echo intval( $orphan_meta_total ); ?></strong> ردیف متادیتای باقیمانده پیدا شد
                    (<?php echo intval( $orphan_meta_counts['postmeta'] ); ?> روی پست‌ها/محصولات، <?php echo intval( $orphan_meta_counts['termmeta'] ); ?> روی دسته‌بندی‌ها).
                </p>
            </div>
            <form method="post" action="" onsubmit="return confirm('این عملیات غیرقابل بازگشت است و همه‌ی متاهای باقیمانده‌ی Rank Math را برای همیشه حذف می‌کند. ادامه می‌دهید؟');">
                <?php wp_nonce_field( 'hodima_schema_cleaner_nonce' ); ?>
                <button type="submit" name="hodima_purge_orphan_seo_meta" class="button" style="border-color: #b91c1c; color: #b91c1c; background: #fff;">
                    پاکسازی دائمی <?php echo intval( $orphan_meta_total ); ?> ردیف باقیمانده
                </button>
            </form>
        <?php else : ?>
            <div class="notice notice-success" style="border-right: 4px solid #16a34a; background: #f0fdf4; padding: 12px 15px; border-left: none; border-top: none; border-bottom: none;">
                <p style="font-weight: inherit; margin: 0;">✅ هیچ متادیتای باقیمانده‌ای از Rank Math در دیتابیس پیدا نشد. دیتابیس تمیز است.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php hodima_view_footer(); ?>