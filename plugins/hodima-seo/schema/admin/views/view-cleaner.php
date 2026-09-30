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

    $cleaner_woo    = isset( $_POST['hodima_cleaner_woo'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_cleaner_woo'] ) ) : 'yes';
    $cleaner_hentry = isset( $_POST['hodima_cleaner_hentry'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_cleaner_hentry'] ) ) : 'yes';
    $graph_debug    = isset( $_POST['hodima_schema_graph_debug'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_schema_graph_debug'] ) ) : 'no';

    $allowed = array( 'yes', 'no' );

    $cleaner_woo    = in_array( $cleaner_woo, $allowed, true ) ? $cleaner_woo : 'yes';
    $cleaner_hentry = in_array( $cleaner_hentry, $allowed, true ) ? $cleaner_hentry : 'yes';
    $graph_debug    = in_array( $graph_debug, $allowed, true ) ? $graph_debug : 'no';

    update_option( 'hodima_cleaner_woo', $cleaner_woo );
    update_option( 'hodima_cleaner_hentry', $cleaner_hentry );
    update_option( 'hodima_schema_graph_debug', $graph_debug );
    delete_option( 'hodima_cleaner_dedupe_guard' ); // گارد قدیمی حذف شد (گراف واحد جایگزین آن است)

    $notice_msg = '<div class="notice notice-success is-dismissible"><p>تنظیمات پاکسازی اسکیما با موفقیت به‌روزرسانی شد.</p></div>';
}

// نتیجه پاکسازی یک‌باره Rank Math (schema-cleaner.php)؛ بخش دکمه «پاکسازی
// دائمی متادیتا» حذف شد چون این کار حالا یک بار خودکار انجام می‌شود.
$rankmath_cleanup = get_option( 'hodima_seo_rankmath_cleanup', false );

// مقادیر فعلی
$cleaner_woo    = get_option( 'hodima_cleaner_woo', 'yes' );
$cleaner_hentry = get_option( 'hodima_cleaner_hentry', 'yes' );
$graph_debug    = get_option( 'hodima_schema_graph_debug', 'no' );

// هدر
hodima_view_header(
    'پاکسازی هوشمند اسکیما',
    'حذف اسکیمای پیش‌فرض ووکامرس، کلاس hentry و گزارش گراف واحد اسکیما.',
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
        <p>همه اسکیماهای سایت از قالب و افزونه‌های هدیما در یک گراف واحد می‌آیند. گزینه‌های زیر اسکیمای تکراری ووکامرس و نشانه‌گذاری قدیمی <code>hentry</code> را حذف می‌کنند تا فقط گراف دانش یکپارچه سایت به گوگل معرفی شود.</p>
    </div>
</div>

<form method="post" action="" class="hd-card">
    <?php wp_nonce_field( 'hodima_schema_cleaner_nonce' ); ?>

    <table class="form-table" role="presentation">
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

<?php if ( is_array( $rankmath_cleanup ) && ( (int) $rankmath_cleanup['meta'] + (int) $rankmath_cleanup['options'] + count( (array) $rankmath_cleanup['tables'] ) + (int) $rankmath_cleanup['cron'] ) > 0 ) : ?>
    <div class="hd-callout hd-callout--success">
        <?php echo hodima_admin_icon( 'dashicons-yes-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <p>
            داده‌های باقی‌مانده Rank Math یک بار خودکار پاک شد
            (<?php echo esc_html( sprintf( '%s ردیف متا، %s گزینه، %s جدول، %s کار زمان‌بندی‌شده', number_format_i18n( (int) $rankmath_cleanup['meta'] ), number_format_i18n( (int) $rankmath_cleanup['options'] ), number_format_i18n( count( (array) $rankmath_cleanup['tables'] ) ), number_format_i18n( (int) $rankmath_cleanup['cron'] ) ) ); ?>).
            <?php if ( (int) $rankmath_cleanup['primary'] > 0 ) : ?>
                «دسته اصلی» <?php echo esc_html( number_format_i18n( (int) $rankmath_cleanup['primary'] ) ); ?> نوشته/محصول پیش از حذف منتقل شد و مسیر راهنما (بردکرامب) تغییری نکرد.
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<?php hodima_view_footer(); ?>