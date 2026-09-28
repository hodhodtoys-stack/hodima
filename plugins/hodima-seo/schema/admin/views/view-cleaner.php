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
    $graph_debug    = isset( $_POST['hodima_schema_graph_debug'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_schema_graph_debug'] ) ) : 'no';

    $allowed = array( 'yes', 'no' );

    $cleaner_rm     = in_array( $cleaner_rm, $allowed, true ) ? $cleaner_rm : 'yes';
    $cleaner_woo    = in_array( $cleaner_woo, $allowed, true ) ? $cleaner_woo : 'yes';
    $cleaner_hentry = in_array( $cleaner_hentry, $allowed, true ) ? $cleaner_hentry : 'yes';
    $graph_debug    = in_array( $graph_debug, $allowed, true ) ? $graph_debug : 'no';

    update_option( 'hodima_cleaner_rm', $cleaner_rm );
    update_option( 'hodima_cleaner_woo', $cleaner_woo );
    update_option( 'hodima_cleaner_hentry', $cleaner_hentry );
    update_option( 'hodima_schema_graph_debug', $graph_debug );
    delete_option( 'hodima_cleaner_dedupe_guard' ); // گارد قدیمی حذف شد (گراف واحد جایگزین آن است)

    $notice_msg = '<div class="notice notice-success is-dismissible"><p>تنظیمات پاکسازی اسکیما با موفقیت به‌روزرسانی شد.</p></div>';
}

// پاکسازی دائمی متادیتای باقی‌مانده‌ی افزونه‌های سئوی حذف‌شده (مثل Rank Math)
if ( isset( $_POST['hodima_purge_orphan_seo_meta'] ) ) {
    check_admin_referer( 'hodima_schema_cleaner_nonce' );

    $deleted_count = function_exists( 'hodima_purge_orphan_seo_meta' ) ? hodima_purge_orphan_seo_meta() : 0;

    $notice_msg = '<div class="notice notice-success is-dismissible"><p>' . intval( $deleted_count ) . ' ردیف متادیتای باقی‌مانده حذف شد و کش سایت‌مپ نیز پاکسازی گردید. اگر محصولی به دلیل این باقیمانده‌ها از سایت‌مپ حذف شده بود، اکنون بازمی‌گردد.</p></div>';
}

$orphan_meta_counts = function_exists( 'hodima_count_orphan_seo_meta' ) ? hodima_count_orphan_seo_meta() : [ 'postmeta' => 0, 'termmeta' => 0 ];
$orphan_meta_total  = $orphan_meta_counts['postmeta'] + $orphan_meta_counts['termmeta'];

// مقادیر فعلی
$cleaner_rm     = get_option( 'hodima_cleaner_rm', 'yes' );
$cleaner_woo    = get_option( 'hodima_cleaner_woo', 'yes' );
$cleaner_hentry = get_option( 'hodima_cleaner_hentry', 'yes' );
$graph_debug    = get_option( 'hodima_schema_graph_debug', 'no' );

// هدر
hodima_view_header(
    'پاکسازی هوشمند اسکیما',
    'کنترل تداخل اسکیماهای افزونه‌های دیگر مانند Rank Math و WooCommerce با اسکیمای اختصاصی سایت.',
    'dashicons-shield'
);

// گزینه‌های «فعال / غیرفعال» هر سلکت (قبلا با ایموجی تیک و ضربدر)
$hodima_yes_no = static function ( string $name, string $value, bool $recommend_yes = true, string $id = '' ): void {
    printf( '<select name="%1$s"%2$s>', esc_attr( $name ), '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' );
    $options = $recommend_yes
        ? [ 'yes' => 'فعال (پیشنهادی)', 'no' => 'غیرفعال' ]
        : [ 'no' => 'غیرفعال (پیشنهادی)', 'yes' => 'فعال' ];
    foreach ( $options as $key => $label ) {
        printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) );
    }
    echo '</select>';
};
?>

<?php echo $notice_msg; ?>

<div class="hd-callout">
    <?php echo hodima_admin_icon( 'dashicons-shield-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <div>
        <strong>دیوار آتش اسکیما</strong>
        <p>افزونه‌های سئو مثل Rank Math ممکن است اسکیمای تکراری تولید کنند (خطای سرچ کنسول). با گزینه‌های زیر اسکیماهای عمومی، گراف‌های هویتی تداخلی و مشکل <strong>Duplicate URL</strong> دسته‌بندی‌ها حذف می‌شوند تا فقط گراف دانش یکپارچه شما به گوگل معرفی شود.</p>
    </div>
</div>

<form method="post" action="" class="hd-card">
    <?php wp_nonce_field( 'hodima_schema_cleaner_nonce' ); ?>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">مهار هوشمند Rank Math</th>
            <td>
                <?php $hodima_yes_no( 'hodima_cleaner_rm', (string) $cleaner_rm ); ?>
                <p class="description">حذف اسکیماهای هویتی مزاحم (LocalBusiness و Organization) در کل سایت، رفع خطای Duplicate دسته‌بندی‌ها و پاکسازی اسکیماهای تکراری مقالات، محصولات و ویدیوها.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">حذف اسکیماهای پیش‌فرض ووکامرس</th>
            <td>
                <?php $hodima_yes_no( 'hodima_cleaner_woo', (string) $cleaner_woo ); ?>
                <p class="description">غیرفعال‌سازی اسکیماهای پایه WooCommerce برای اولویت دادن به اسکیمای اختصاصی محصولات.</p>
            </td>
        </tr>

        <tr>
            <th scope="row">حذف کلاس hentry قالب</th>
            <td>
                <?php $hodima_yes_no( 'hodima_cleaner_hentry', (string) $cleaner_hentry ); ?>
                <p class="description">حذف کلاس <code>hentry</code> از HTML برای جلوگیری از خطاهای <code>Missing author</code> و <code>Missing updated</code> در سرچ کنسول.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="hodima_schema_graph_debug">گراف واحد اسکیما</label></th>
            <td>
                <p class="hd-text">
                    همه اسکیماهای سایت (قالب و افزونه‌های هدیما) در <strong>یک</strong> تگ و یک <code>@graph</code> در انتهای صفحه چاپ می‌شوند — همیشه فعال است.
                    نودهای هم‌شناسه (<code>@id</code> یکسان) <strong>ادغام</strong> می‌شوند، نه حذف؛ پس هیچ اسکیمایی به اشتباه دور ریخته نمی‌شود.
                </p>
                <?php $hodima_yes_no( 'hodima_schema_graph_debug', (string) $graph_debug, false, 'hodima_schema_graph_debug' ); ?>
                <p class="description">
                    «گزارش گراف برای مدیر»: وقتی فعال است و با حساب مدیر وارد شده‌اید، در سورس هر صفحه (کنار اسکیما) یک کامنت HTML می‌بینید: هر نود از کدام بخش آمده، کدام مقادیر تعارض داشتند و کدام ارجاع‌ها نود مقصد ندارند. بازدیدکنندگان و گوگل این گزارش را نمی‌بینند.
                </p>
            </td>
        </tr>
    </table>

    <?php hodima_view_form_footer(); ?>
    <input type="hidden" name="hodima_save_schema_cleaner" value="1">
</form>

<section class="hd-card hd-card--danger" aria-labelledby="hodima-orphan-meta-title">
    <header class="hd-card__head">
        <?php echo hodima_admin_icon( 'dashicons-trash' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <h2 class="hd-card__title" id="hodima-orphan-meta-title">پاکسازی دائمی متادیتای باقیمانده (Rank Math و مشابه)</h2>
        <p class="hd-card__desc">
            وقتی افزونه سئویی مثل Rank Math حذف می‌شود، اطلاعاتی که روی محصولات و مقالات نوشته بود (مثل noindex یا دسته اصلی) در دیتابیس می‌ماند
            و ممکن است بعضی محصولات را بدون راه رفع از پنل، بیرون از سایت‌مپ نگه دارد.
        </p>
    </header>

    <?php if ( $orphan_meta_total > 0 ) : ?>
        <div class="hd-callout hd-callout--warning">
            <?php echo hodima_admin_icon( 'dashicons-warning' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <p>
                <strong><?php echo intval( $orphan_meta_total ); ?></strong> ردیف متادیتای باقیمانده پیدا شد
                (<?php echo intval( $orphan_meta_counts['postmeta'] ); ?> روی پست‌ها/محصولات، <?php echo intval( $orphan_meta_counts['termmeta'] ); ?> روی دسته‌بندی‌ها).
            </p>
        </div>
        <form method="post" action="" onsubmit="return confirm('این عملیات غیرقابل بازگشت است و همه‌ی متاهای باقیمانده‌ی Rank Math را برای همیشه حذف می‌کند. ادامه می‌دهید؟');">
            <?php wp_nonce_field( 'hodima_schema_cleaner_nonce' ); ?>
            <button type="submit" name="hodima_purge_orphan_seo_meta" class="button hd-button--danger">
                <?php echo hodima_admin_icon( 'dashicons-trash' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                پاکسازی دائمی <?php echo intval( $orphan_meta_total ); ?> ردیف باقیمانده
            </button>
        </form>
    <?php else : ?>
        <div class="hd-callout hd-callout--success">
            <?php echo hodima_admin_icon( 'dashicons-yes-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <p>هیچ متادیتای باقیمانده‌ای از Rank Math در دیتابیس پیدا نشد. دیتابیس تمیز است.</p>
        </div>
    <?php endif; ?>
</section>

<?php hodima_view_footer(); ?>