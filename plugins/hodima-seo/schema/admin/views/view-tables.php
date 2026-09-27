<?php
if (!defined('ABSPATH')) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


// استفاده از توابع هدر و فوتر اختصاصی شما (در صورت وجود)
if (function_exists('hodima_view_header')) {
    hodima_view_header(
        'مدیریت جداول هدیما',
        'راهنمای جامع استفاده از جداول مشخصات و داینامیک برای تولید خودکار اسکیمای گوگل.',
        '📊'
    );
}
?>

<div class="h-dashboard-wrapper" style="border: 1px solid #e5e7eb; border-radius: 12px; padding: 25px; background: #f8fafc; margin-top: 10px;">
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 25px;">
        
        <!-- کارت 1: جدول داینامیک (Hodima_Dynamic_Table) -->
        <div class="h-table-card" style="border: 1px solid #b6c2f3; border-radius: 8px; padding: 25px; background: #fff; box-shadow: 0 4px 6px rgba(37,49,106,0.05);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;">
                <span style="font-size: 28px;">📝</span>
                <div>
                    <h3 style="margin: 0; font-size: 17px; color: #25316a; font-weight: inherit;">جدول داینامیک (پست‌ها، برگه‌ها و دسته‌ها)</h3>
                    <span style="font-size: 12px; color: #607bbd;">تولیدکننده اسکیمای ItemList</span>
                </div>
            </div>
            
            <p style="font-size: 13px; color: #475569; line-height: 1.7; margin-bottom: 15px; font-weight: inherit;">
                این ماژول یک باکس (متاباکس) در انتهای صفحه ویرایش نوشته‌ها، برگه‌ها و دسته‌بندی‌ها ایجاد می‌کند. شما می‌توانید بی‌نهایت ردیف (ویژگی و مقدار) به آن اضافه کنید. اطلاعات وارد شده به صورت خودکار به اسکیما <span style="font-weight: inherit; color:#25316a;">ItemList</span> تبدیل شده و به گوگل کمک می‌کند تا ساختار اطلاعات صفحه شما را بهتر درک کند.
            </p>

            <ul style="font-size: 13px; color: #475569; line-height: 1.6; padding-right: 20px; margin-bottom: 20px;">
                <li><span style="font-weight: inherit; color:#25316a;">قدم اول:</span> در صفحه ویرایش نوشته/دسته، به پایین صفحه (بخش "جدول مشخصات") اسکرول کنید.</li>
                <li><span style="font-weight: inherit; color:#25316a;">قدم دوم:</span> روی "افزودن ردیف" کلیک کرده و عنوان و مقدار را وارد کنید.</li>
                <li><span style="font-weight: inherit; color:#25316a;">قدم سوم:</span> شورت‌کد مربوطه را در متن محتوا قرار دهید تا جدول به کاربر هم نمایش داده شود.</li>
            </ul>
            
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px dashed #b6c2f3;">
                <span style="display: block; margin-bottom: 10px; font-size: 13px; color: #25316a; font-weight: inherit;">شورت‌کدهای نمایش جدول:</span>
                
                <div style="margin-bottom: 10px;">
                    <span style="font-size: 12px; color: #607bbd; display: block; margin-bottom: 4px;">نمایش خودکار (برای پست جاری):</span>
                    <code style="background: #fff; padding: 6px 10px; border-radius: 4px; border: 1px solid #b6c2f3; user-select: all; cursor: pointer; display: block; font-size: 14px; color: #25316a;">[hodima_table]</code>
                </div>

                <div>
                    <span style="font-size: 12px; color: #607bbd; display: block; margin-bottom: 4px;">نمایش پیشرفته (فراخوانی پست یا دسته دیگر با تغییر عنوان):</span>
                    <code style="background: #fff; padding: 6px 10px; border-radius: 4px; border: 1px solid #b6c2f3; user-select: all; cursor: pointer; display: block; margin-bottom: 5px; font-size: 13px; color: #25316a;">[hodima_table title="مشخصات فنی"]</code>
                    <code style="background: #fff; padding: 6px 10px; border-radius: 4px; border: 1px solid #b6c2f3; user-select: all; cursor: pointer; display: block; font-size: 13px; color: #25316a;">[hodima_table id="123" type="post"]</code>
                </div>
            </div>
        </div>

        <!-- کارت 2: جدول مشخصات ووکامرس (Hodima_Product_Specs_Table) -->
        <div class="h-table-card" style="border: 1px solid #b6c2f3; border-radius: 8px; padding: 25px; background: #fff; box-shadow: 0 4px 6px rgba(37,49,106,0.05);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;">
                <span style="font-size: 28px;">🛍️</span>
                <div>
                    <h3 style="margin: 0; font-size: 17px; color: #25316a; font-weight: inherit;">جدول مشخصات ووکامرس</h3>
                    <span style="font-size: 12px; color: #607bbd;">تولیدکننده اسکیمای PropertyValue</span>
                </div>
            </div>
            
            <p style="font-size: 13px; color: #475569; line-height: 1.7; margin-bottom: 15px; font-weight: inherit;">
                این جدول برای محصولات ووکامرس طراحی شده است. سیستم به صورت خودکار ویژگی‌های محصول (Attributes) مانند جنس، سایز، رنگ و برند را می‌خواند. اسکیمای تولید شده <span style="font-weight: inherit; color:#25316a;">PropertyValue</span> است که مستقیماً به اسکیمای اصلی محصول (Product) متصل می‌شود و شانس نمایش در نتایج غنی (Rich Snippets) را افزایش می‌دهد.
            </p>

            <ul style="font-size: 13px; color: #475569; line-height: 1.6; padding-right: 20px; margin-bottom: 20px;">
                <li>ویژگی‌ها را از مسیر <span style="font-weight: inherit; color:#25316a;">محصولات > ویژگی‌ها</span> تعریف کنید یا مستقیماً در تب "ویژگی‌ها" در صفحه محصول وارد کنید.</li>
                <li>حتماً تیک <span style="font-weight: inherit; color:#25316a;">"نمایش در برگه محصول"</span> را برای ویژگی‌ها بزنید.</li>
            </ul>

            <div style="background: #f8fafc; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; border-right: 4px solid #607bbd;">
                <p style="margin: 0; font-size: 12px; color: #25316a; line-height: 1.6;">
                    <span style="font-weight: inherit; font-size: 13px;">نکته مهم در مورد وزن:</span><br>
                    برای اینکه وزن محصول در جدول و اسکیما لحاظ شود، حتماً باید مقدار آن را در صفحه ویرایش محصول بخش <span style="font-weight: inherit;">"اطلاعات محصول > حمل و نقل > وزن"</span> وارد کنید.
                </p>
            </div>
            
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px dashed #b6c2f3;">
                <span style="display: block; margin-bottom: 10px; font-size: 13px; color: #25316a; font-weight: inherit;">شورت‌کد نمایش جدول در محصول:</span>
                <code style="background: #fff; padding: 6px 10px; border-radius: 4px; border: 1px solid #b6c2f3; user-select: all; cursor: pointer; display: block; font-size: 14px; color: #25316a;">[woo_specs_table]</code>
                <p style="font-size: 11px; color: #64748b; margin: 8px 0 0 0; line-height: 1.5;">این شورت‌کد را می‌توانید در بخش "توضیحات کوتاه محصول"، داخل متن اصلی، یا با استفاده از ابزارک شورت‌کد در المنتور قرار دهید.</p>
            </div>
        </div>

    </div>
</div>

<?php 
if (function_exists('hodima_view_footer')) {
    hodima_view_footer(); 
}
?>