<?php
/**
 * Admin View: LLMs.txt Configuration (Display Only Version)
 * Path: wp-content/plugins/hodima-seo/schema/admin/views/view-llms.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$base_url = untrailingslashit( home_url() );

hodima_view_header(
    'داشبورد جامع هوش مصنوعی (AEO & Semantic Web)',
    'راهنمای دسترسی به تمامی مسیرها، فایل‌ها و وب‌سرویس‌هایی که سایت بازرگانی هدهد را برای ربات‌های هوش مصنوعی خوانا کرده است.'
);
?>

<div class="h-card" style="font-family: 'Vazirmatn', sans-serif; direction: rtl; text-align: right;">

    <style>
        .aeo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-top: 20px; }
        .aeo-card { background: #ffffff; border: 1px solid #b6c2f3; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(37,49,106,.05); }
        .aeo-card-header { background: linear-gradient(90deg, #25316a, #607bbd, #b6c2f3); padding: 4px; }
        .aeo-card-body { padding: 20px; }
        .aeo-card-title { color: #25316a; font-size: 17px; font-weight: bold; margin: 0 0 15px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .aeo-list { list-style: none; padding: 0; margin: 0; }
        .aeo-list li { margin-bottom: 15px; }
        .aeo-list strong { color: #25316a; display: block; margin-bottom: 4px; font-size: 14px; }
        .aeo-list p { color: #607bbd; font-size: 13px; margin: 0 0 5px 0; line-height: 1.5; }
        .aeo-code-box { display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border: 1px solid #b6c2f3; border-radius: 4px; padding: 6px 10px; font-family: monospace; font-size: 13px; color: #25316a; direction: ltr; text-align: left; }
        .aeo-link { color: #607bbd; text-decoration: none; font-weight: bold; font-family: 'Vazirmatn', sans-serif; }
        .aeo-link:hover { color: #25316a; text-decoration: underline; }
        .aeo-tag { display: inline-block; background: #25316a; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 5px; }
        .aeo-tag-danger { background: #25316a; }
    </style>

    <!-- معرفی تگ‌های هدر -->
    <div class="notice notice-info" style="border-right: 4px solid #25316a; background: #fff; padding: 15px; margin-bottom: 25px; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,.05); display: flex; flex-direction: column; gap: 15px;">
        
        <div>
            <h3 style="margin: 0 0 5px 0; color: #25316a; font-size: 15px;">۱. دروازه ورود اصلی (Global Discovery Tag)</h3>
            <p style="color: #607bbd; font-size: 13px; margin: 0 0 8px 0;">این تگ به هوش مصنوعی نقشه راه کل سایت (<code>llms.txt</code>) را معرفی می‌کند.</p>
            <code style="display: block; background: #f8fafc; border: 1px solid #b6c2f3; padding: 10px; border-radius: 4px; color: #25316a; direction: ltr; text-align: left;">
                &lt;link rel="llms" href="<?php echo esc_url($base_url); ?>/llms.txt"&gt;
            </code>
        </div>

        <div style="border-top: 1px dashed #b6c2f3; padding-top: 15px;">
            <h3 style="margin: 0 0 5px 0; color: #25316a; font-size: 15px;">۲. کشف سطح صفحه (Page-Level Discovery Tag)</h3>
            <p style="color: #607bbd; font-size: 13px; margin: 0 0 8px 0;">این تگ به صورت داینامیک در محصولات/مقالات چاپ می‌شود تا نسخه مارک‌داونِ همان صفحه را به ماشین معرفی کند.</p>
            <code style="display: block; background: #f8fafc; border: 1px solid #b6c2f3; padding: 10px; border-radius: 4px; color: #25316a; direction: ltr; text-align: left;">
                &lt;link rel="alternate" type="text/markdown" href="<?php echo esc_url($base_url); ?>/product-url.md"&gt;
            </code>
        </div>

    </div>

    <div class="aeo-grid">
        <!-- 1. نقشه‌های راه -->
        <div class="aeo-card">
            <div class="aeo-card-header"></div>
            <div class="aeo-card-body">
                <h3 class="aeo-card-title">۱. نقشه‌های راه (AI Sitemaps)</h3>
                <ul class="aeo-list">
                    <li>
                        <strong>نقشه سبک و ساختار سیلویی <span class="aeo-tag">llms.txt</span></strong>
                        <p>نمایش معماری سایت، اطلاعات شرکت و ۱۰ محصول آپدیت شده آخر هر دسته.</p>
                        <div class="aeo-code-box">
                            <span>/llms.txt</span>
                            <a href="<?php echo esc_url($base_url); ?>/llms.txt" target="_blank" class="aeo-link">مشاهده</a>
                        </div>
                    </li>
                    <li>
                        <strong>نقشه کامل کاتالوگ <span class="aeo-tag">llms-full.txt</span></strong>
                        <p>لیست کامل تا ۵۰۰ لینک از تمامی محصولات و مقالات سایت برای خزش عمیق.</p>
                        <div class="aeo-code-box">
                            <span>/llms-full.txt</span>
                            <a href="<?php echo esc_url($base_url); ?>/llms-full.txt" target="_blank" class="aeo-link">مشاهده</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- 2. صفحات مارک‌داون -->
        <div class="aeo-card">
            <div class="aeo-card-header"></div>
            <div class="aeo-card-body">
                <h3 class="aeo-card-title">۲. صفحات خوانا برای ماشین (.md)</h3>
                <ul class="aeo-list">
                    <li>
                        <strong>جادوی پسوند مارک‌داون</strong>
                        <p>با اضافه کردن <code>.md</code> به انتهای آدرس هر محصول یا مقاله، نسخه پردازش‌شده (شامل متادیتا، MOQ و گراف دانش) را ببینید.</p>
                        <div class="aeo-code-box" style="margin-bottom: 8px;">
                            <span>/product-name.md</span>
                            <span style="color:#607bbd; font-family:'Vazirmatn'; font-size:11px;">محصولات</span>
                        </div>
                        <div class="aeo-code-box" style="margin-bottom: 8px;">
                            <span>/category-name.md</span>
                            <span style="color:#607bbd; font-family:'Vazirmatn'; font-size:11px;">دسته‌بندی‌ها</span>
                        </div>
                        <div class="aeo-code-box">
                            <span>/article-name.md</span>
                            <span style="color:#607bbd; font-family:'Vazirmatn'; font-size:11px;">مقالات</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- 3. وب‌سرویس‌ها و فیدها -->
        <div class="aeo-card">
            <div class="aeo-card-header"></div>
            <div class="aeo-card-body">
                <h3 class="aeo-card-title">۳. وب‌سرویس‌ها و فیدهای زنده</h3>
                <ul class="aeo-list">
                    <li>
                        <strong>جستجوی زنده (Live Search)</strong>
                        <p>جستجو در دیتابیس هدهد با خروجی اختصاصی مارک‌داون برای هوش مصنوعی.</p>
                        <div class="aeo-code-box">
                            <span>/llm-search?q=keyword</span>
                            <a href="<?php echo esc_url($base_url); ?>/llm-search?q=کلیپس" target="_blank" class="aeo-link">تست</a>
                        </div>
                    </li>
                    <li>
                        <strong>فید تازه‌ها (24h Feed)</strong>
                        <p>خروجی JSON از موجودیت‌هایی که در ۲۴ ساعت گذشته آپدیت شده‌اند.</p>
                        <div class="aeo-code-box">
                            <span>/ai-feed.json</span>
                            <a href="<?php echo esc_url($base_url); ?>/ai-feed.json" target="_blank" class="aeo-link">مشاهده</a>
                        </div>
                    </li>
                    <li>
                        <strong>معرفی وب‌سرویس (OpenAPI)</strong>
                        <p>استاندارد اتصال Custom GPTs به سیستم سرچ سایت هدهد.</p>
                        <div class="aeo-code-box">
                            <span>/openapi.json</span>
                            <a href="<?php echo esc_url($base_url); ?>/openapi.json" target="_blank" class="aeo-link">مشاهده</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- 4. امنیت و ردیابی -->
        <div class="aeo-card">
            <div class="aeo-card-header" style="background: linear-gradient(90deg, #25316a, #1b244d);"></div>
            <div class="aeo-card-body">
                <h3 class="aeo-card-title" style="color: #25316a;">۴. امنیت و مانیتورینگ (Security)</h3>
                <ul class="aeo-list">
                    <li>
                        <strong>تله امنیتی <span class="aeo-tag aeo-tag-danger">Honeypot</span></strong>
                        <p>اسکرپرها با باز کردن این لینک بلافاصله مسدود (Ban) می‌شوند. <strong style="display:inline; color:#25316a;">(شما کلیک نکنید!)</strong></p>
                        <div class="aeo-code-box">
                            <span>/ai-internal-data.md</span>
                        </div>
                    </li>
                    <li>
                        <strong>لاگ ربات‌ها (AI Analytics)</strong>
                        <p>گزارش JSON از رفتار و آی‌پی ربات‌های هوش مصنوعی (نیاز به دسترسی ادمین).</p>
                        <div class="aeo-code-box">
                            <span>/ai-analytics.json</span>
                            <a href="<?php echo esc_url($base_url); ?>/ai-analytics.json" target="_blank" class="aeo-link">مشاهده</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</div>

<?php hodima_view_footer(); ?>