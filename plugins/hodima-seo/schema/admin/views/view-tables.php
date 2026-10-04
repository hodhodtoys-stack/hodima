<?php
if (!defined('ABSPATH')) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


hodima_view_header(
    'جداول هدیما',
    'راهنمای جداول مشخصات و داینامیک برای تولید خودکار اسکیمای گوگل.',
    'dashicons-editor-table'
);
?>

<div class="hd-grid hd-grid--wide">

    <?php // جدول داینامیک (Hodima_Dynamic_Table) ?>
    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-edit-page' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">جدول داینامیک (نوشته‌ها، برگه‌ها، محصولات و دسته‌ها)</h2>
            <p class="hd-card__desc">تولیدکننده اسکیمای Table و مشخصات محصول</p>
        </header>

        <p class="hd-text">
            کادر «جدول مشخصات» در صفحه ویرایش نوشته‌ها، برگه‌ها، محصولات و دسته‌بندی‌ها اضافه می‌شود. جدول در اسکیمای <strong>Table</strong>
            صفحه ثبت می‌شود؛ جدول <strong>دوستونه</strong> (ستون اول نام ویژگی، ستون دوم مقدار) مقدارها را هم به گوگل می‌دهد و در محصول به
            مشخصات اسکیمای محصول (additionalProperty) اضافه می‌شود — فقط وقتی شورت‌کدش در متن همان محصول باشد، چون گوگل داده‌ای را
            می‌خواهد که در صفحه دیده شود.
        </p>

        <div class="hd-callout">
            <?php echo hodima_admin_icon( 'dashicons-info-outline' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <p><strong>در محصولات:</strong> این جدول (کادر «جدول تکمیلی محصول») برای مقایسه، کاربرد و موارد متغیر هر محصول است.
            جنس، سایز، وزن، تعداد، رنگ، تولید و بروزرسانی را فقط «جدول مشخصات ووکامرس» (کارت کناری) می‌سازد؛ اگر این ردیف‌ها در جدول دستی محصول نوشته شوند،
            در سایت و اسکیما نادیده گرفته می‌شوند تا یک ویژگی دو مقدار متناقض نداشته باشد.</p>
        </div>

        <ol class="hd-list">
            <li>در صفحه ویرایش، به کادر «جدول مشخصات» (در محصول: «جدول تکمیلی محصول») بروید و ردیف‌ها را پر کنید (ستون‌های خالی هنگام ذخیره حذف می‌شوند).</li>
            <li>شورت‌کد را در متن (یا توضیح دسته) بگذارید تا جدول به کاربر هم نمایش داده شود.</li>
            <li>با «خروجی CSV» و «ورود CSV» می‌توانید جدول را در اکسل ویرایش کنید.</li>
        </ol>

        <span class="hd-field__label">نمایش خودکار (جدول همین نوشته یا دسته)</span>
        <div class="hd-code">[hodima_table]</div>

        <span class="hd-field__label">نمایش پیشرفته (با عنوان، یا جدول نوشته/دسته دیگر)</span>
        <div class="hd-code">[hodima_table title="مشخصات فنی"]</div>
        <div class="hd-code">[hodima_table id="123" type="post"]</div>
        <div class="hd-code">[hodima_table id="45" type="term"]</div>
    </section>

    <?php // جدول مشخصات ووکامرس (Hodima_Product_Specs_Table) ?>
    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-products' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">جدول مشخصات ووکامرس</h2>
            <p class="hd-card__desc">تولیدکننده اسکیمای PropertyValue</p>
        </header>

        <p class="hd-text">
            ویژگی‌های محصول (Attributes) مانند جنس، سایز، رنگ و برند خودکار خوانده می‌شوند. اسکیمای <strong>PropertyValue</strong>
            مستقیم به اسکیمای اصلی محصول (Product) وصل می‌شود و شانس نمایش نتایج غنی را بیشتر می‌کند. ردیف‌ها: جنس، سایز، وزن، تعداد، رنگ، تولید (کشور سازنده) و بروزرسانی —
            مرجع این ویژگی‌ها همین جدول است و جدول داینامیک آن‌ها را تکرار نمی‌کند.
        </p>

        <ul class="hd-list">
            <li>ویژگی‌ها را از «محصولات ← ویژگی‌ها» تعریف کنید یا در تب «ویژگی‌ها»ی صفحه محصول وارد کنید.</li>
            <li>گزینه «نمایش در برگه محصول» را برای ویژگی‌ها فعال کنید.</li>
        </ul>

        <div class="hd-callout">
            <?php echo hodima_admin_icon( 'dashicons-info-outline' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <p><strong>وزن محصول</strong> برای آمدن در جدول و اسکیما باید در «اطلاعات محصول ← حمل و نقل ← وزن» وارد شده باشد.</p>
        </div>

        <span class="hd-field__label">شورت‌کد نمایش جدول در محصول</span>
        <div class="hd-code">[woo_specs_table]</div>
        <p class="description">در توضیحات کوتاه محصول، متن اصلی یا ابزارک شورت‌کد صفحه‌ساز قرار دهید.</p>
    </section>

</div>

<?php hodima_view_footer(); ?>
