<?php
if (!defined('ABSPATH')) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


hodima_view_header(
    'پیشخوان اسکیما',
    'مدیریت متمرکز داده‌های ساختاریافته (JSON-LD)، نقشه سایت، فید پادکست و فایل‌های هوش مصنوعی. همه اسکیماها در یک گراف واحد چاپ می‌شوند.',
    'dashicons-dashboard'
);

// کارت‌ها از همان فهرست صفحه‌های منو ساخته می‌شوند تا عنوان و آیکون همه‌جا یکی باشد
$descriptions = [
    'hodima-schema-health'     => 'بررسی خودکار اسکیمای صفحه‌های نمونه سایت (مثل ابزار نتایج غنی گوگل) و فهرست مشکلات.',
    'hodima-schema-homepage'   => 'گراف دانش (Knowledge Graph)، لوگو، اطلاعات تماس، ساعات کاری و هدف‌گذاری هوش مصنوعی.',
    'hodima-sitemap'           => 'تولید و مدیریت یکپارچه و سریع نقشه سایت XML با تصاویر و ویدیوها.',
    'hodima-schema-breadcrumb' => 'اسکیمای مسیر راهنما (BreadcrumbList) برای همه صفحات.',
    'hodima-schema-blog'       => 'BlogPosting برای مقالات و بهبود نمایش در گوگل دیسکاور.',
    'hodima-schema-category'   => 'CollectionPage پیشرفته برای دسته‌بندی‌های سایت.',
    'hodima-schema-product'    => 'اسکیمای Product: قیمت، موجودی، مرجوعی و ارسال (Merchant Listing).',
    'hodima-schema-image'      => 'ImageObject برای سئوی بهتر تصاویر در Google Images.',
    'hodima-podcast'           => 'فید استاندارد RSS فایل‌های صوتی برای اپل و گوگل پادکست.',
    'hodima-schema-page'       => 'گراف WebPage و شناسایی خودکار ویدیوهای داخل برگه‌ها.',
    'hodima-schema-tables'     => 'راهنما و شورت‌کدهای جداول داینامیک و مشخصات محصول.',
    'hodima-llms'              => 'llms.txt، نسخه‌های Markdown و فیدهای ماشین‌خوان برای ربات‌های هوش مصنوعی.',
    'hodima-schema-cleaner'    => 'حذف اسکیماهای مزاحم قالب، ووکامرس و افزونه‌های دیگر.',
];
?>

<div class="hs-groups">
    <?php
    $pages = hodima_schema_admin_pages();
    foreach ( hodima_schema_admin_groups() as [ $group_label, $group_slugs ] ) :
        ?>
        <section class="hs-group" aria-label="<?php echo esc_attr( $group_label ); ?>">
            <h2 class="hs-group__title"><?php echo esc_html( $group_label ); ?><span><?php echo esc_html( sprintf( '%s صفحه', number_format_i18n( count( $group_slugs ) ) ) ); ?></span></h2>
            <?php foreach ( $group_slugs as $slug ) : ?>
                <?php if ( 'hodima-schema' === $slug ) continue; ?>
                <a class="hs-row" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>">
                    <?php echo hodima_admin_icon( $pages[ $slug ][3] ?? 'dashicons-editor-code', 'hs-row__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    <span class="hs-row__text">
                        <b><?php echo esc_html( $pages[ $slug ][0] ); ?></b>
                        <span><?php echo esc_html( $descriptions[ $slug ] ?? '' ); ?></span>
                    </span>
                    <span class="hs-row__go" aria-hidden="true">←</span>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>

<?php hodima_view_footer(); ?>