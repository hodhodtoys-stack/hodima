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

// نوار وضعیت: چند حفاظت اصلی (ووکامرس و hentry) روشن است
$protections_on = ( 'yes' === $cleaner_woo ? 1 : 0 ) + ( 'yes' === $cleaner_hentry ? 1 : 0 );
$all_on         = 2 === $protections_on;

/**
 * یک سطر کلید: hidden «no» + چک‌باکس «yes» (همان مقدارهای yes/no گزینه‌های قبلی).
 * عنوان همان label کلید است تا نام قابل‌خواندن برای صفحه‌خوان داشته باشد (قبلا select بی‌نام بود).
 */
$hodima_switch_row = static function ( string $name, bool $on, string $icon, string $title, string $desc, bool $recommended = false ): void {
    ?>
    <div class="hs-opt">
        <?php echo hodima_admin_icon( $icon, 'hs-opt__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <div class="hs-opt__text">
            <label class="hs-opt__title" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $title ); ?></label>
            <?php if ( $recommended ) : ?><span class="hd-pill hs-opt__tag">پیشنهادی</span><?php endif; ?>
            <p class="hs-opt__desc"><?php echo esc_html( $desc ); ?></p>
        </div>
        <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="no">
        <input type="checkbox" class="hd-switch" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="yes" role="switch" <?php checked( $on ); ?>>
    </div>
    <?php
};
?>

<?php echo $notice_msg; ?>

<section class="hs-status<?php echo $all_on ? '' : ' is-warn'; ?>" aria-label="وضعیت پاکسازی اسکیما">
    <?php echo hodima_admin_icon( 'dashicons-shield-alt', 'hs-status__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <div class="hs-status__body">
        <h2 class="hs-status__title"><?php echo esc_html( $all_on ? 'دیوار آتش اسکیما کامل فعال است' : sprintf( '%s از ۲ حفاظت روشن است', number_format_i18n( $protections_on ) ) ); ?></h2>
        <p class="hs-status__text">همه اسکیماهای سایت از قالب و افزونه‌های هدیما در یک گراف واحد می‌آیند؛ این گزینه‌ها اسکیمای تکراری را حذف می‌کنند تا فقط گراف یکپارچه به گوگل معرفی شود.</p>
    </div>
    <span class="hd-pill <?php echo $all_on ? 'hd-pill--ok' : 'hd-pill--warn'; ?>"><?php echo esc_html( $all_on ? 'همه روشن' : 'پیشنهاد: روشن کنید' ); ?></span>
</section>

<form method="post" action="">
    <?php wp_nonce_field( 'hodima_schema_cleaner_nonce' ); ?>
    <input type="hidden" name="hodima_save_schema_cleaner" value="1">

    <section class="hd-card hs-card" aria-labelledby="hs-protections">
        <h2 class="hd-section-title" id="hs-protections">حفاظت‌ها</h2>
        <?php
        $hodima_switch_row( 'hodima_cleaner_woo', 'yes' === $cleaner_woo, 'dashicons-cart', 'حذف اسکیماهای پیش‌فرض ووکامرس', 'اسکیمای اختصاصی محصولات اولویت دارد؛ اسکیمای پایه ووکامرس چاپ نمی‌شود.', true );
        $hodima_switch_row( 'hodima_cleaner_hentry', 'yes' === $cleaner_hentry, 'dashicons-editor-code', 'حذف کلاس hentry قالب', 'جلوی خطاهای «Missing author» و «Missing updated» در سرچ کنسول را می‌گیرد.', true );
        $hodima_switch_row( 'hodima_schema_graph_debug', 'yes' === $graph_debug, 'dashicons-visibility', 'گزارش گراف برای مدیر', 'کامنتی در سورس صفحه (فقط وقتی با حساب مدیر وارد شده‌اید) می‌گوید هر نود از کدام بخش آمده، کدام مقادیر تعارض داشتند و کدام ارجاع‌ها مقصد ندارند. بازدیدکننده و گوگل نمی‌بینند.' );
        ?>
        <details class="hs-more">
            <summary>گراف واحد چطور کار می‌کند؟</summary>
            <p>
                همه اسکیماهای سایت (قالب و افزونه‌های هدیما) در <strong>یک</strong> تگ و یک <code>@graph</code> در انتهای صفحه چاپ می‌شوند و همیشه فعال است.
                نودهای هم‌شناسه (<code>@id</code> یکسان) <strong>ادغام</strong> می‌شوند، نه حذف؛ پس هیچ اسکیمایی به اشتباه دور ریخته نمی‌شود.
            </p>
        </details>
    </section>

    <?php if ( is_array( $rankmath_cleanup ) && ( (int) $rankmath_cleanup['meta'] + (int) $rankmath_cleanup['options'] + count( (array) $rankmath_cleanup['tables'] ) + (int) $rankmath_cleanup['cron'] ) > 0 ) : ?>
        <section class="hd-card hs-card" aria-labelledby="hs-rankmath">
            <h2 class="hd-section-title" id="hs-rankmath">پاکسازی یک‌باره Rank Math (انجام شده)</h2>
            <div class="hs-results">
                <?php
                foreach ( [
                    [ 'ردیف متا', (int) $rankmath_cleanup['meta'] ],
                    [ 'گزینه', (int) $rankmath_cleanup['options'] ],
                    [ 'جدول', count( (array) $rankmath_cleanup['tables'] ) ],
                    [ 'کار زمان‌بندی‌شده', (int) $rankmath_cleanup['cron'] ],
                    [ '«دسته اصلی» منتقل شد', (int) $rankmath_cleanup['primary'] ],
                ] as [ $label, $number ] ) {
                    if ( $number > 0 || 'گزینه' === $label ) {
                        printf( '<div><b>%1$s</b><span>%2$s</span></div>', esc_html( number_format_i18n( $number ) ), esc_html( $label ) );
                    }
                }
                ?>
            </div>
            <p class="hs-results__note">
                این پاکسازی یک بار خودکار انجام شد.
                <?php if ( (int) $rankmath_cleanup['primary'] > 0 ) : ?>مسیر راهنما (بردکرامب) تغییری نکرد.<?php endif; ?>
            </p>
        </section>
    <?php endif; ?>

    <div class="hs-save">
        <?php submit_button( 'ذخیره تنظیمات', 'primary', 'submit', false ); ?>
    </div>
</form>

<?php hodima_view_footer(); ?>
