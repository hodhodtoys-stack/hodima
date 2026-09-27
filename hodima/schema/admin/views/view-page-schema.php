<?php
/**
 * Admin View: Sitemap / Schema Settings
 * Status: Display Only (View Mode)
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


// استفاده از هدر استاندارد قالب در صورت وجود
if ( function_exists( 'hodima_view_header' ) ) {
    hodima_view_header(
        'تنظیمات اسکیما و سایت‌مپ',
        'وضعیت نمایش اسکیما برای صفحات اصلی سایت',
        '📄'
    );
}

$schema_pages = array(
    array(
        'title' => 'صفحه اصلی',
        'slug'  => 'home',
        'icon'  => '🏠',
    ),
    array(
        'title' => 'فروشگاه',
        'slug'  => 'shop',
        'icon'  => '🛍️',
    ),
    array(
        'title' => 'درباره ما',
        'slug'  => 'about-us',
        'icon'  => 'ℹ️',
    ),
    array(
        'title' => 'تماس با ما',
        'slug'  => 'contact-us',
        'icon'  => '📞',
    ),
);
?>

<div class="h-card" style="margin-top:20px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 10px 30px rgba(15,23,42,.04);overflow:hidden;">

    <div style="padding:24px 24px 18px;border-bottom:1px solid #f1f5f9;background:linear-gradient(180deg,#fff 0%,#f8fafc 100%);">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
            <div style="width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-size:22px;">📄</div>

            <div>
                <h2 style="margin:0;font-size:22px;font-weight:700;color:#0f172a;line-height:1.6;">
                    اسکیمای برگه ها
                </h2>

                <p style="margin:4px 0 0;color:#64748b;font-size:14px;line-height:1.9;">
                    نمایش وضعیت برگه‌های اصلی سایت در این بخش قابل مشاهده است.
                </p>
            </div>
        </div>
    </div>

    <div style="padding:24px;">

        <!-- وضعیت -->
        <div style="margin-bottom:22px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">

            <div style="display:inline-flex;align-items:center;gap:8px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;border-radius:999px;padding:8px 14px;font-size:13px;font-weight:600;">
                <span style="width:8px;height:8px;border-radius:50%;background:#16a34a;display:inline-block;"></span>
                فعال
            </div>

            <div style="display:inline-flex;align-items:center;gap:8px;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;border-radius:999px;padding:8px 14px;font-size:13px;font-weight:600;">
                <span style="width:8px;height:8px;border-radius:50%;background:#dc2626;display:inline-block;"></span>
                غیرفعال
            </div>

        </div>

        <!-- کارت ها -->
        <div style="
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:16px;
        ">

            <?php foreach ( $schema_pages as $page ) : ?>

                <div style="
                    background:#f8fafc;
                    border:1px solid #e2e8f0;
                    border-radius:14px;
                    padding:22px 16px;
                    text-align:center;
                    transition:.2s;
                ">

                    <div style="
                        display:flex;
                        flex-direction:column;
                        align-items:center;
                        gap:12px;
                    ">

                        <div style="
                            width:52px;
                            height:52px;
                            border-radius:14px;
                            background:#fff;
                            border:1px solid #e2e8f0;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            font-size:24px;
                        ">
                            <?php echo $page['icon']; ?>
                        </div>

                        <div>
                            <div style="font-size:16px;font-weight:700;color:#1e293b;line-height:1.8;">
                                <?php echo $page['title']; ?>
                            </div>

                            <div style="font-size:12px;color:#94a3b8;line-height:1.8;margin-top:2px;">
                                <?php echo $page['slug']; ?>
                            </div>
                        </div>

                        <span style="
                            width:10px;
                            height:10px;
                            border-radius:50%;
                            background:#16a34a;
                            display:inline-block;
                            box-shadow:0 0 0 4px rgba(22,163,74,.15);
                        "></span>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</div>

<?php
if ( function_exists( 'hodima_view_footer' ) ) {
    hodima_view_footer();
}
?>