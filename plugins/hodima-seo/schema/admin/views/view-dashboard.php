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
    'hodima-schema-homepage'   => 'گراف دانش (Knowledge Graph)، لوگو، اطلاعات تماس، ساعات کاری و هدف‌گذاری هوش مصنوعی.',
    'hodima-sitemap'           => 'تولید و مدیریت یکپارچه و سریع نقشه سایت XML با تصاویر و ویدیوها.',
    'hodima-schema-breadcrumb' => 'اسکیمای مسیر راهنما (BreadcrumbList) برای همه صفحات.',
    'hodima-schema-blog'       => 'BlogPosting برای مقالات و بهبود نمایش در Google Discover.',
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

<div class="hd-grid">
    <?php foreach ( hodima_schema_admin_pages() as $slug => $page ) : ?>
        <?php if ( 'hodima-schema' === $slug ) continue; ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="hd-link-card">
            <?php echo hodima_admin_icon( $page[3] ?? 'dashicons-editor-code' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-link-card__title"><?php echo esc_html( $page[0] ); ?></h2>
            <p class="hd-link-card__desc"><?php echo esc_html( $descriptions[ $slug ] ?? '' ); ?></p>
            <span class="hd-link-card__more">تنظیمات ←</span>
        </a>
    <?php endforeach; ?>
</div>

<?php hodima_view_footer(); ?>