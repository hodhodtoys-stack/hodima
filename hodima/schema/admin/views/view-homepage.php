<?php
/**
 * Admin View: Master Schema System Settings (Unified)
 * Description: مدیریت یکپارچه تمام اطلاعات گراف دانش، لوکال بیزینس، صفحات شرکتی و سیگنال‌های AI GEO
 */

if (!defined('ABSPATH')) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$notice_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hodima_homepage_schema_nonce'])) {
    if (wp_verify_nonce($_POST['hodima_homepage_schema_nonce'], 'hodima_save_homepage_schema')) {
        
        // ۱. تنظیمات عمومی
        update_option('hodima_schema_homepage_enable', isset($_POST['hodima_schema_homepage_enable']) ? '1' : '0');
        if (isset($_POST['hodima_schema_homepage_org_type'])) update_option('hodima_schema_homepage_org_type', sanitize_text_field( wp_unslash( $_POST['hodima_schema_homepage_org_type'] ) ));
        if (isset($_POST['hodima_schema_homepage_org_name'])) update_option('hodima_schema_homepage_org_name', sanitize_text_field( wp_unslash( $_POST['hodima_schema_homepage_org_name'] ) ));
        if (isset($_POST['hodima_corp_about_slug'])) update_option('hodima_corp_about_slug', sanitize_text_field( wp_unslash( $_POST['hodima_corp_about_slug'] ) ));
        if (isset($_POST['hodima_corp_contact_slug'])) update_option('hodima_corp_contact_slug', sanitize_text_field( wp_unslash( $_POST['hodima_corp_contact_slug'] ) ));

        // ۲. هویت بصری و برندینگ
        if (isset($_POST['hodima_schema_homepage_logo'])) update_option('hodima_schema_homepage_logo', esc_url_raw( wp_unslash( $_POST['hodima_schema_homepage_logo'] ) ));
        if (isset($_POST['hodima_schema_homepage_image'])) update_option('hodima_schema_homepage_image', esc_url_raw( wp_unslash( $_POST['hodima_schema_homepage_image'] ) ));
        if (isset($_POST['hodima_schema_geo_alt_names'])) update_option('hodima_schema_geo_alt_names', sanitize_textarea_field( wp_unslash( $_POST['hodima_schema_geo_alt_names'] ) ));
        if (isset($_POST['hodima_schema_geo_description'])) update_option('hodima_schema_geo_description', sanitize_textarea_field( wp_unslash( $_POST['hodima_schema_geo_description'] ) ));

        // ۳. اطلاعات تماس و آدرس
        if (isset($_POST['hodima_schema_geo_telephones'])) update_option('hodima_schema_geo_telephones', sanitize_textarea_field( wp_unslash( $_POST['hodima_schema_geo_telephones'] ) ));
        if (isset($_POST['hodima_schema_homepage_postal_code'])) update_option('hodima_schema_homepage_postal_code', sanitize_text_field( wp_unslash( $_POST['hodima_schema_homepage_postal_code'] ) ));
        if (isset($_POST['hodima_schema_homepage_street_address'])) update_option('hodima_schema_homepage_street_address', sanitize_textarea_field( wp_unslash( $_POST['hodima_schema_homepage_street_address'] ) ));
        if (isset($_POST['hodima_schema_homepage_address_locality'])) update_option('hodima_schema_homepage_address_locality', sanitize_text_field( wp_unslash( $_POST['hodima_schema_homepage_address_locality'] ) ));
        if (isset($_POST['hodima_schema_homepage_address_region'])) update_option('hodima_schema_homepage_address_region', sanitize_text_field( wp_unslash( $_POST['hodima_schema_homepage_address_region'] ) ));

        // ۴. ساعات کاری
        if (isset($_POST['hodima_schema_geo_weekday_open'])) update_option('hodima_schema_geo_weekday_open', sanitize_text_field( wp_unslash( $_POST['hodima_schema_geo_weekday_open'] ) ));
        if (isset($_POST['hodima_schema_geo_weekday_close'])) update_option('hodima_schema_geo_weekday_close', sanitize_text_field( wp_unslash( $_POST['hodima_schema_geo_weekday_close'] ) ));
        if (isset($_POST['hodima_schema_geo_thursday_open'])) update_option('hodima_schema_geo_thursday_open', sanitize_text_field( wp_unslash( $_POST['hodima_schema_geo_thursday_open'] ) ));
        if (isset($_POST['hodima_schema_geo_thursday_close'])) update_option('hodima_schema_geo_thursday_close', sanitize_text_field( wp_unslash( $_POST['hodima_schema_geo_thursday_close'] ) ));

        // ۵. اطلاعات تجاری، تخصص‌ها و شبکه‌های اجتماعی
        if (isset($_POST['hodima_schema_homepage_price_range'])) update_option('hodima_schema_homepage_price_range', sanitize_text_field( wp_unslash( $_POST['hodima_schema_homepage_price_range'] ) ));
        if (isset($_POST['hodima_schema_homepage_catalog_name'])) update_option('hodima_schema_homepage_catalog_name', sanitize_text_field( wp_unslash( $_POST['hodima_schema_homepage_catalog_name'] ) ));
        if (isset($_POST['hodima_schema_homepage_knows_about'])) update_option('hodima_schema_homepage_knows_about', sanitize_textarea_field( wp_unslash( $_POST['hodima_schema_homepage_knows_about'] ) ));
        if (isset($_POST['hodima_schema_homepage_slogan'])) update_option('hodima_schema_homepage_slogan', sanitize_text_field( wp_unslash( $_POST['hodima_schema_homepage_slogan'] ) ));
        if (isset($_POST['hodima_schema_geo_socials'])) update_option('hodima_schema_geo_socials', sanitize_textarea_field( wp_unslash( $_POST['hodima_schema_geo_socials'] ) ));

        // ۶. تنظیمات هوش مصنوعی (AI & GEO)
        if (isset($_POST['hodima_schema_ai_countries'])) update_option('hodima_schema_ai_countries', sanitize_textarea_field( wp_unslash( $_POST['hodima_schema_ai_countries'] ) ));
        if (isset($_POST['hodima_schema_ai_languages'])) update_option('hodima_schema_ai_languages', sanitize_text_field( wp_unslash( $_POST['hodima_schema_ai_languages'] ) ));
        if (isset($_POST['hodima_schema_ai_audience'])) update_option('hodima_schema_ai_audience', sanitize_text_field( wp_unslash( $_POST['hodima_schema_ai_audience'] ) ));

        $notice_msg = '<div class="notice notice-success is-dismissible" style="border-right: 4px solid #25316a; background: #fff;"><p style="font-weight: inherit;">تنظیمات سیستم یکپارچه گراف دانش و AI GEO با موفقیت ذخیره شد.</p></div>';
    }
}

// دریافت مقادیر عمومی
$is_enabled       = get_option('hodima_schema_homepage_enable', '1');
$org_type         = get_option('hodima_schema_homepage_org_type', 'WholesaleStore');
$org_name         = get_option('hodima_schema_homepage_org_name', get_bloginfo('name') ?: 'شرکت بازرگانی هدهد');
$about_slug       = get_option('hodima_corp_about_slug', 'about-us');
$contact_slug     = get_option('hodima_corp_contact_slug', 'contact-us');

$logo             = get_option('hodima_schema_homepage_logo', '');
$image            = get_option('hodima_schema_homepage_image', '');
$alt_names        = get_option('hodima_schema_geo_alt_names', "عمده فروشی هدهد\nبازرگانی هدهد\nhodima");
$description      = get_option('hodima_schema_geo_description', 'مرجع تخصصی واردات و پخش عمده جدیدترین مدل‌های اکسسوری مو در ایران.');

$telephones       = get_option('hodima_schema_geo_telephones', "+989124093140\n02177322684");
$postal_code      = get_option('hodima_schema_homepage_postal_code', '1657883361');
$street_address   = get_option('hodima_schema_homepage_street_address', 'تهرانپارس، خیابان احسان، پلاک ۸۴');
$address_locality = get_option('hodima_schema_homepage_address_locality', 'تهران');
// مقدار پیش‌فرض همان «تهران» است تا برای سایت‌هایی که هنوز این فیلد جدید را
// پر نکرده‌اند، خروجی نسبت به قبل تغییری نکند (چون استان تهران هم تهران است).
$address_region   = get_option('hodima_schema_homepage_address_region', 'تهران');

$wd_open          = get_option('hodima_schema_geo_weekday_open', '09:00');
$wd_close         = get_option('hodima_schema_geo_weekday_close', '17:30');
$th_open          = get_option('hodima_schema_geo_thursday_open', '09:00');
$th_close         = get_option('hodima_schema_geo_thursday_close', '13:00');

$price_range      = get_option('hodima_schema_homepage_price_range', 'IRR');
$catalog_name     = get_option('hodima_schema_homepage_catalog_name', 'خدمات پخش و فروش عمده کالا');
$slogan           = get_option('hodima_schema_homepage_slogan', 'مرجع تخصصی خرید عمده و پخش سراسری در ایران');
$knows_about      = get_option('hodima_schema_homepage_knows_about', "واردات اکسسوری مو\nپخش عمده کلیپس\nفروش عمده کش مو\nتولید و پخش گلسر\nلوازم خرازی و خرج‌کار");
$socials          = get_option('hodima_schema_geo_socials', "https://instagram.com/hodima\nhttps://t.me/hodimaaccessory\nhttps://wa.me/989124093140");

// دریافت مقادیر AI GEO
$ai_countries     = get_option('hodima_schema_ai_countries', "Iran\nIraq\nUnited Arab Emirates\nAfghanistan\nPakistan\nOman\nTurkey");
$ai_languages     = get_option('hodima_schema_ai_languages', 'fa, ar, ps, ur, en, tr');
$ai_audience      = get_option('hodima_schema_ai_audience', 'B2B, Wholesale Buyers, Importers, Exporters');

hodima_view_header(
    'تنظیمات سیستم جامع اسکیما (Master Knowledge Graph)',
    'در این بخش تمام اطلاعات هویتی، آدرس، تلفن‌ها، شبکه‌های اجتماعی و هدف‌گذاری هوش مصنوعی (AI Target) را به صورت یکپارچه مدیریت کنید.',
    '👑'
);
?>

<style>
    .hodima-schema-card { 
        background: #fff; 
        box-shadow: 0 4px 12px rgba(37, 49, 106, 0.08); 
        border-radius: 12px; 
        margin-top: 20px; 
        overflow: hidden; 
    }
    .hodima-schema-card-top { 
        background: linear-gradient(90deg, #25316a, #607bbd); 
        height: 5px; 
        width: 100%; 
    }
    .hodima-section-title { 
        margin: 0; 
        color: #25316a; 
        font-size: 18px; 
        font-weight: inherit;
        display: flex; 
        align-items: center; 
        gap: 8px; 
        border-bottom: 2px dashed #b6c2f3; 
        padding-bottom: 12px; 
        padding-top: 25px; 
    }
    .hodima-textarea, .hodima-input { 
        padding: 10px; 
        line-height: 1.6; 
        border-radius: 6px; 
        border: 1px solid #b6c2f3; 
        transition: all 0.3s ease;
    }
    .hodima-textarea { width: 100%; }
    .hodima-textarea:focus, .hodima-input:focus {
        border-color: #25316a;
        box-shadow: 0 0 0 3px rgba(37, 49, 106, 0.1);
        outline: none;
    }
</style>

<div class="h-card hodima-schema-card">
    <div class="hodima-schema-card-top"></div>
    <div style="padding: 20px;">
        <?php echo $notice_msg; ?>

        <div class="notice notice-info" style="border-right: 4px solid #607bbd; background: #fff; padding: 15px; margin-bottom: 25px; box-shadow: 0 1px 1px rgba(37, 49, 106, 0.04);">
            <h4 style="margin: 0 0 10px 0; color: #25316a; font-weight: inherit; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px;">🌐</span> سیستم یکپارچه گراف دانش گوگل و AI GEO
            </h4>
            <p style="margin: 0; font-size: 13px; color: #444; line-height: 1.6; font-weight: inherit;">
                با این سیستم، تمامی اطلاعات بیزینسی و سیگنال‌های تارگتینگ منطقه‌ای شما به طور هوشمند بین صفحه اصلی، صفحات داخلی و ماژول‌های هوش مصنوعی تقسیم می‌شود. این کد به عنوان مرجع اصلی برای لوکال بیزینس و سازمان عمل می‌کند، لطفاً کدهای موازی (مانند رنک‌مث) را خاموش کنید تا گراف شما دچار اختلال نشود.
            </p>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('hodima_save_homepage_schema', 'hodima_homepage_schema_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row" style="font-weight: inherit;">وضعیت سیستم اسکیما</th>
                    <td>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="hodima_schema_homepage_enable" value="1" <?php checked($is_enabled, '1'); ?>>
                            <strong style="font-weight: inherit;">فعال‌سازی گراف یکپارچه در کل سایت</strong>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_org_type">نوع کسب‌وکار اصلی</label></th>
                    <td>
                        <select name="hodima_schema_homepage_org_type" id="hodima_schema_homepage_org_type" style="min-width: 250px; border-radius: 6px;">
                            <option value="WholesaleStore" <?php selected($org_type, 'WholesaleStore'); ?>>فروشگاه عمده‌فروشی (WholesaleStore)</option>
                            <option value="LocalBusiness" <?php selected($org_type, 'LocalBusiness'); ?>>کسب‌وکار محلی (LocalBusiness)</option>
                            <option value="Organization" <?php selected($org_type, 'Organization'); ?>>سازمان عمومی (Organization)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_org_name">نام رسمی کسب‌وکار</label></th>
                    <td>
                        <input type="text" name="hodima_schema_homepage_org_name" id="hodima_schema_homepage_org_name" class="regular-text hodima-input" value="<?php echo esc_attr($org_name); ?>">
                        <p class="description">جهت رفع ارور نام اشتباه در سرچ کنسول</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label>اسلاگ صفحات سازمانی</label></th>
                    <td>
                        <input type="text" name="hodima_corp_about_slug" class="small-text ltr hodima-input" value="<?php echo esc_attr($about_slug); ?>" placeholder="درباره ما">
                        <input type="text" name="hodima_corp_contact_slug" class="small-text ltr hodima-input" value="<?php echo esc_attr($contact_slug); ?>" placeholder="تماس با ما">
                        <p class="description">جهت تزریق خودکار اسکیما به صفحات AboutPage و ContactPage.</p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2" style="padding: 0;">
                        <h3 class="hodima-section-title">🤖 هوش مصنوعی و هدف‌گذاری منطقه‌ای (AI & GEO Target)</h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_ai_countries">کشورهای تحت پوشش (areaServed)<br><small>(هر کشور در یک خط به انگلیسی)</small></label></th>
                    <td>
                        <textarea name="hodima_schema_ai_countries" id="hodima_schema_ai_countries" rows="5" class="ltr hodima-textarea" dir="ltr"><?php echo esc_textarea($ai_countries); ?></textarea>
                        <p class="description">مثال: Iran, Iraq, United Arab Emirates (سیگنال قوی برای LLMها)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_ai_languages">زبان‌های قابل پشتیبانی (knowsLanguage)</label></th>
                    <td>
                        <input type="text" name="hodima_schema_ai_languages" id="hodima_schema_ai_languages" class="large-text ltr hodima-input" value="<?php echo esc_attr($ai_languages); ?>">
                        <p class="description">کدهای ISO زبان با کاما جدا شوند. مثال: fa, ar, ps, ur, en, tr</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_ai_audience">نوع مخاطب تجاری (audienceType)</label></th>
                    <td>
                        <input type="text" name="hodima_schema_ai_audience" id="hodima_schema_ai_audience" class="large-text ltr hodima-input" value="<?php echo esc_attr($ai_audience); ?>">
                        <p class="description">مثال: B2B, Wholesale Buyers, Importers, Exporters</p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2" style="padding: 0;">
                        <h3 class="hodima-section-title">🎨 هویت بصری و برندینگ</h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_logo">آدرس لوگو (URL)</label></th>
                    <td>
                        <input type="url" name="hodima_schema_homepage_logo" id="hodima_schema_homepage_logo" class="large-text ltr hodima-input" value="<?php echo esc_attr($logo); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_image">آدرس عکس کاور شرکت</label></th>
                    <td>
                        <input type="url" name="hodima_schema_homepage_image" id="hodima_schema_homepage_image" class="large-text ltr hodima-input" value="<?php echo esc_attr($image); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_geo_alt_names">نام‌های جایگزین شرکت<br><small>(هر نام در یک خط)</small></label></th>
                    <td>
                        <textarea name="hodima_schema_geo_alt_names" id="hodima_schema_geo_alt_names" rows="3" class="hodima-textarea"><?php echo esc_textarea($alt_names); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_geo_description">توضیحات بیزینس (سئو شده)</label></th>
                    <td>
                        <textarea name="hodima_schema_geo_description" id="hodima_schema_geo_description" rows="3" class="hodima-textarea"><?php echo esc_textarea($description); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th colspan="2" style="padding: 0;">
                        <h3 class="hodima-section-title">📍 اطلاعات تماس و آدرس</h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_geo_telephones">شماره‌های تماس<br><small>(هر شماره در یک خط با کد 98+)</small></label></th>
                    <td>
                        <textarea name="hodima_schema_geo_telephones" id="hodima_schema_geo_telephones" rows="3" class="ltr hodima-textarea" dir="ltr"><?php echo esc_textarea($telephones); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_address_locality">شهر</label></th>
                    <td>
                        <input type="text" name="hodima_schema_homepage_address_locality" id="hodima_schema_homepage_address_locality" class="regular-text hodima-input" value="<?php echo esc_attr($address_locality); ?>">
                    </td>
                </tr>
                <tr>
                    <!-- 🛠️ باگ رفع‌شده: قبلاً هیچ فیلد جداگانه‌ای برای «استان»
                    وجود نداشت و کد همان مقدار «شهر» را هم برای addressLocality
                    هم برای addressRegion در JSON-LD تکرار می‌کرد. برای تهران
                    این تصادفاً بی‌ضرر بود (چون نام استان و شهر یکی است)، ولی
                    برای هر شهر دیگری (مثلاً مشهد که استانش خراسان رضوی است)
                    داده‌ی غلط تولید می‌شد. -->
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_address_region">استان</label></th>
                    <td>
                        <input type="text" name="hodima_schema_homepage_address_region" id="hodima_schema_homepage_address_region" class="regular-text hodima-input" value="<?php echo esc_attr($address_region); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_street_address">آدرس دقیق پستی</label></th>
                    <td>
                        <textarea name="hodima_schema_homepage_street_address" id="hodima_schema_homepage_street_address" rows="2" class="hodima-textarea"><?php echo esc_textarea($street_address); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_postal_code">کد پستی</label></th>
                    <td>
                        <input type="text" name="hodima_schema_homepage_postal_code" id="hodima_schema_homepage_postal_code" class="regular-text ltr hodima-input" value="<?php echo esc_attr($postal_code); ?>">
                    </td>
                </tr>

                <tr>
                    <th colspan="2" style="padding: 0;">
                        <h3 class="hodima-section-title">🕒 ساعات کاری</h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label>شنبه تا چهارشنبه</label></th>
                    <td>
                        از <input type="time" name="hodima_schema_geo_weekday_open" class="hodima-input" value="<?php echo esc_attr($wd_open); ?>">
                        تا <input type="time" name="hodima_schema_geo_weekday_close" class="hodima-input" value="<?php echo esc_attr($wd_close); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label>پنج‌شنبه‌ها</label></th>
                    <td>
                        از <input type="time" name="hodima_schema_geo_thursday_open" class="hodima-input" value="<?php echo esc_attr($th_open); ?>">
                        تا <input type="time" name="hodima_schema_geo_thursday_close" class="hodima-input" value="<?php echo esc_attr($th_close); ?>">
                    </td>
                </tr>

                <tr>
                    <th colspan="2" style="padding: 0;">
                        <h3 class="hodima-section-title">💡 اطلاعات تجاری و شبکه‌های اجتماعی</h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_slogan">شعار تجاری (Slogan)</label></th>
                    <td>
                        <input type="text" name="hodima_schema_homepage_slogan" id="hodima_schema_homepage_slogan" class="large-text hodima-input" value="<?php echo esc_attr($slogan); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_catalog_name">نام کاتالوگ خدمات</label></th>
                    <td>
                        <input type="text" name="hodima_schema_homepage_catalog_name" id="hodima_schema_homepage_catalog_name" class="regular-text hodima-input" value="<?php echo esc_attr($catalog_name); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_price_range">محدوده قیمت</label></th>
                    <td>
                        <input type="text" name="hodima_schema_homepage_price_range" id="hodima_schema_homepage_price_range" class="small-text ltr hodima-input" value="<?php echo esc_attr($price_range); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_homepage_knows_about">تخصص‌ها (knowsAbout)<br><small>(هر مورد در یک خط)</small></label></th>
                    <td>
                        <textarea name="hodima_schema_homepage_knows_about" id="hodima_schema_homepage_knows_about" rows="5" class="hodima-textarea"><?php echo esc_textarea($knows_about); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: inherit;"><label for="hodima_schema_geo_socials">لینک شبکه‌های اجتماعی<br><small>(هر لینک در یک خط)</small></label></th>
                    <td>
                        <textarea name="hodima_schema_geo_socials" id="hodima_schema_geo_socials" rows="4" class="ltr hodima-textarea" dir="ltr" placeholder="https://instagram.com/..."><?php echo esc_textarea($socials); ?></textarea>
                    </td>
                </tr>
            </table>

            <?php hodima_view_form_footer(); ?>
        </form>
    </div>
</div>

<?php hodima_view_footer(); ?>