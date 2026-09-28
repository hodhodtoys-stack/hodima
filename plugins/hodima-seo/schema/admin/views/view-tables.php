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
            <h2 class="hd-card__title">جدول داینامیک (نوشته‌ها، برگه‌ها و دسته‌ها)</h2>
            <p class="hd-card__desc">تولیدکننده اسکیمای ItemList</p>
        </header>

        <p class="hd-text">
            یک متاباکس در پایین صفحه ویرایش نوشته‌ها، برگه‌ها و دسته‌بندی‌ها اضافه می‌شود که می‌توانید هر تعداد ردیف (ویژگی و مقدار) به آن بدهید.
            اطلاعات خودکار به اسکیمای <strong>ItemList</strong> تبدیل می‌شود و به گوگل در درک ساختار صفحه کمک می‌کند.
        </p>

        <ol class="hd-list">
            <li>در صفحه ویرایش نوشته یا دسته، به بخش «جدول مشخصات» بروید.</li>
            <li>روی «افزودن ردیف» بزنید و عنوان و مقدار را وارد کنید.</li>
            <li>شورت‌کد را در متن بگذارید تا جدول به کاربر هم نمایش داده شود.</li>
        </ol>

        <span class="hd-field__label">نمایش خودکار (برای همین نوشته)</span>
        <div class="hd-code">[hodima_table]</div>

        <span class="hd-field__label">نمایش پیشرفته (نوشته یا دسته دیگر، با عنوان دلخواه)</span>
        <div class="hd-code">[hodima_table title="مشخصات فنی"]</div>
        <div class="hd-code">[hodima_table id="123" type="post"]</div>
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
            مستقیم به اسکیمای اصلی محصول (Product) وصل می‌شود و شانس نمایش نتایج غنی را بیشتر می‌کند.
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
