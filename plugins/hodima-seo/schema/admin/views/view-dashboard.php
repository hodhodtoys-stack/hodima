<?php
if (!defined('ABSPATH')) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


hodima_view_header(
    'پیشخوان اسکیمای هدیما  : v 1.2.8',
    'به پنل مدیریت متمرکز اسکیمای هدیما خوش آمدید. از اینجا می‌توانید به تمام بخش‌های سئو فنی دسترسی داشته باشید.',
    '🧩'
);

$modules = [
    [
        'title' => 'صفحه اصلی (Homepage)',
        'desc'  => 'مدیریت گراف دانش (Knowledge Graph)، لوگو و اطلاعات پایه.',
        'icon'  => '🏠',
        'url'   => admin_url('admin.php?page=hodima-schema-homepage'),
        'color' => '#25316a'
    ],
    [
        'title' => 'نقشه سایت (XML)',
        'desc'  => 'تولید و مدیریت یکپارچه و سریع نقشه سایت.',
        'icon'  => '🌐',
        'url'   => admin_url('admin.php?page=hodima-sitemap'),
        'color' => '#607bbd'
    ],
    [
        'title' => 'بردکرامب (Breadcrumb)',
        'desc'  => 'مدیریت اسکیمای مسیر راهنما (BreadcrumbList) در صفحات.',
        'icon'  => '🛤️',
        'url'   => admin_url('admin.php?page=hodima-schema-breadcrumb'),
        'color' => '#25316a'
    ],
    [
        'title' => 'بلاگ (Blog)',
        'desc'  => 'تنظیمات BlogPosting برای مقالات و بهبود در Google Discover.',
        'icon'  => '📝',
        'url'   => admin_url('admin.php?page=hodima-schema-blog'),
        'color' => '#607bbd'
    ],
    [
        'title' => 'دسته‌بندی (Category)',
        'desc'  => 'اسکیمای پیشرفته CollectionPage برای دسته‌بندی‌های سایت.',
        'icon'  => '📂',
        'url'   => admin_url('admin.php?page=hodima-schema-category'),
        'color' => '#25316a'
    ],
    [
        'title' => 'محصولات (Product)',
        'desc'  => 'اسکیمای Product برای فروشگاه، قیمت‌ها، موجودی و نظرات.',
        'icon'  => '🛍️',
        'url'   => admin_url('admin.php?page=hodima-schema-product'),
        'color' => '#607bbd'
    ],
    [
        'title' => 'تصاویر (Image)',
        'desc'  => 'تزریق اسکیمای ImageObject برای سئو بهتر تصاویر در نتایج.',
        'icon'  => '🖼️',
        'url'   => admin_url('admin.php?page=hodima-schema-image'),
        'color' => '#25316a'
    ],
    [
        'title' => 'فید پادکست (RSS)',
        'desc'  => 'تولید فید استاندارد و حرفه‌ای ویژه فایل‌های صوتی برای موتورهای جستجو.',
        'icon'  => '🎙️',
        'url'   => admin_url('admin.php?page=hodima-podcast'),
        'color' => '#607bbd'
    ],
    [
        'title' => 'برگه‌ها (Pages)', // <-- کارت جایگزین شده
        'desc'  => 'تزریق گراف WebPage و شناسایی و معرفی خودکار ویدئوهای داخل برگه‌ها.',
        'icon'  => '📄',
        'url'   => admin_url('admin.php?page=hodima-schema-page'),
        'color' => '#25316a'
    ],
    [
        'title' => 'جداول هدیما (ItemList)',
        'desc'  => 'راهنما و شورت‌کدهای جداول داینامیک و مشخصات محصولات ووکامرس.',
        'icon'  => '📊',
        'url'   => admin_url('admin.php?page=hodima-schema-tables'), 
        'color' => '#607bbd'
    ],
    [
        'title' => 'هوش مصنوعی (LLMs.txt)',
        'desc'  => 'تولید فایل استاندارد و اختصاصی برای هدایت ربات‌های هوش مصنوعی.',
        'icon'  => '🧠',
        'url'   => admin_url('admin.php?page=hodima-llms'),
        'color' => '#25316a'
    ],
    [
        'title' => 'پاکسازی اسکیما (Cleaner)',
        'desc'  => 'حذف اسکیماهای مزاحم قالب، ووکامرس و افزونه‌های دیگر.',
        'icon'  => '🧹',
        'url'   => admin_url('admin.php?page=hodima-schema-cleaner'),
        'color' => '#607bbd'
    ]
];
?>

<div class="h-dashboard-wrapper" style="border: 1px solid #cbd5e1; border-radius: 12px; padding: 25px; background: #f8fafc; margin-top: 10px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
    <div class="h-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
        <?php foreach ($modules as $module) : ?>
            <a href="<?php echo esc_url($module['url']); ?>" class="h-dash-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff; text-decoration: none; color: inherit; display: block; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.2s ease;">
                
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                    <div class="h-dash-icon-wrap" style="color: <?php echo $module['color']; ?>; border: 1px solid <?php echo $module['color']; ?>33; border-radius: 6px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: <?php echo $module['color']; ?>11;">
                        <?php echo $module['icon']; ?>
                    </div>
                    <h3 class="h-dash-title" style="margin: 0; font-size: 15px; color: #1e293b; font-weight: inherit;"><?php echo esc_html($module['title']); ?></h3>
                </div>

                <p class="h-dash-desc" style="margin: 0 0 10px 0; font-size: 13px; color: #64748b; line-height: 1.5; font-weight: inherit;"><?php echo esc_html($module['desc']); ?></p>
                
                <span class="h-dash-arrow" style="display: block; text-align: left; color: #94a3b8; font-size: 18px; direction: ltr;">&larr;</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php hodima_view_footer(); ?>