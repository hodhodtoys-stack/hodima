<?php
/**
 * Theme Settings — ساختار صفحه تنظیمات: شبکه‌های اجتماعی (ثابت‌ها)، گروه‌های منوی کناری، تب‌ها (sections) و قاب‌ها (panels)
 * Path: hodima/inc/theme-settings/structure.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** گزینه فهرست شبکه‌های اجتماعی (فهرست پویا از ۳.۲.۳؛ تا ۳.۲.۲ هر شبکه دو کلید ثابت در گزینه تنظیمات بود). */
const HODIMA_SOCIAL_OPTION = 'hodima_social_links';
const HODIMA_SOCIAL_MAX    = 20; // سقف منطقی تعداد شبکه‌ها

/**
 * نام شبکه‌های ثابت قدیمی (۳.۲.۲ و پیش از آن) — فقط برای خواندن داده قدیمی؛ شبکه تازه را مدیر خودش پر می‌کند.
 *
 * @return array<string, string> شناسه => نام نمایشی
 */
function hodima_social_legacy_networks(): array {
	return [
		'instagram' => 'اینستاگرام',
		'telegram'  => 'تلگرام',
		'whatsapp'  => 'واتس‌اپ',
		'rubika'    => 'روبیکا',
		'aparat'    => 'آپارات',
		'eitaa'     => 'ایتا',
		'bale'      => 'بله',
		'youtube'   => 'یوتیوب',
		'linkedin'  => 'لینکدین',
	];
}

/**
 * گروه‌های منوی کناری صفحه تنظیمات.
 *
 * @return array<string, string>
 */
function hodima_settings_nav_groups(): array {
	return [
		'look'    => 'ظاهر سایت',
		'pages'   => 'صفحه‌ها',
		'contact' => 'ارتباط با مشتری',
		'tools'   => 'آمار و ابزارها',
	];
}

/**
 * بخش‌های صفحه تنظیمات؛ هر بخش یک تب. ترتیب = ترتیب منوی کناری.
 *
 * قبلا ده تب بی‌ترتیب در یک ردیف بودند (و در صفحه‌های کوچک‌تر آخرین تب‌ها
 * بیرون از کادر می‌افتادند)؛ «هدر و ۴۰۴» هم دو چیز بی‌ربط را در یک تب داشت.
 * حالا تب‌ها به ترتیب جای‌شان در سایت (بالا به پایین، بعد صفحه‌ها) و در
 * چهار گروه‌اند. کلید تب‌ها همان قبلی است (لینک‌های ?tab=… قدیمی کار می‌کنند).
 *
 * @return array<string, array{title:string, description:string, icon:string, nav:string}>
 */
function hodima_settings_sections(): array {
	return [
		'brand'     => [ 'nav' => 'look', 'title' => 'برند و رنگ‌ها', 'icon' => 'dashicons-art', 'description' => 'لوگوی هدر و پنج رنگ برند که کل سایت از آن‌ها ساخته می‌شود.' ],
		'typography' => [ 'nav' => 'look', 'title' => 'تایپوگرافی', 'icon' => 'dashicons-editor-textcolor', 'description' => 'فونت، اندازه و رنگ متن، لینک‌ها و تیترهای H1 تا H6 در کل سایت (و ویرایشگر نوشته‌ها).' ],
		'layout'    => [ 'nav' => 'look', 'title' => 'ظاهر عمومی', 'icon' => 'dashicons-layout', 'description' => 'گردی گوشه‌ها، سبک دکمه‌ها و عرض محتوای کل سایت.' ],
		'extras'    => [ 'nav' => 'look', 'title' => 'امکانات کاربری', 'icon' => 'dashicons-smartphone', 'description' => 'دکمه بازگشت به بالا و نوار پایین موبایل. هر دو تا روشن نشوند در سایت نمایش داده نمی‌شوند.' ],
		'header'    => [ 'nav' => 'look', 'title' => 'هدر', 'icon' => 'dashicons-editor-kitchensink', 'description' => 'نوار بالای همه صفحه‌ها. متن خالی به پیش‌فرض برمی‌گردد.' ],
		'footer'    => [ 'nav' => 'look', 'title' => 'فوتر', 'icon' => 'dashicons-table-row-after', 'description' => 'ستون‌های پایین همه صفحه‌ها. ستونی که محتوا نداشته باشد نمایش داده نمی‌شود.' ],
		'home'      => [ 'nav' => 'pages', 'title' => 'صفحه اصلی', 'icon' => 'dashicons-admin-home', 'description' => 'بخش‌های صفحه اصلی سایت: ترتیب، روشن/خاموش و تنظیمات هر بخش.' ],
		'shop'      => [ 'nav' => 'pages', 'title' => 'فروشگاه', 'icon' => 'dashicons-store', 'description' => 'سربرگ صفحه فروشگاه و تعداد محصول در هر صفحه.' ],
		'product'   => [ 'nav' => 'pages', 'title' => 'صفحه محصول', 'icon' => 'dashicons-products', 'description' => 'متن‌ها و بخش‌های صفحه هر محصول.' ],
		'blog'      => [ 'nav' => 'pages', 'title' => 'وبلاگ', 'icon' => 'dashicons-welcome-write-blog', 'description' => 'صفحه وبلاگ و کارت‌ها، اطلاعات و متن هر مقاله، و «مقالات مرتبط».' ],
		'notfound'  => [ 'nav' => 'pages', 'title' => 'صفحه ۴۰۴', 'icon' => 'dashicons-warning', 'description' => 'صفحه‌ای که بازدیدکننده با آدرس اشتباه یا حذف‌شده می‌بیند. متن خالی به پیش‌فرض برمی‌گردد.' ],
		'contact'   => [ 'nav' => 'contact', 'title' => 'اطلاعات تماس', 'icon' => 'dashicons-phone', 'description' => 'در پنجره «پشتیبانی» هدر و فوتر نمایش داده می‌شود. هر گزینه خالی نمایش داده نمی‌شود.' ],
		'social'    => [ 'nav' => 'contact', 'title' => 'شبکه‌های اجتماعی', 'icon' => 'dashicons-share', 'description' => 'نوار شبکه‌های اجتماعی بالای فوتر همه صفحه‌ها. بدون هیچ شبکه‌ای نوار نمایش داده نمی‌شود.' ],
		'analytics' => [ 'nav' => 'tools', 'title' => 'Google Analytics', 'icon' => 'dashicons-chart-area', 'description' => 'کد آمار GA4 با بارگذاری async و بدون مسدود کردن رندر صفحه اضافه می‌شود.' ],
		'speed'     => [ 'nav' => 'tools', 'title' => 'سرعت و PageSpeed', 'icon' => 'dashicons-performance', 'description' => 'همه تنظیم‌های سرعت سایت یک‌جا: بارگذاری کد آمار و jQuery، اندازه تصویر کارت محصولات، و اتصال زودهنگام به دامنه ویدیو. هر گزینه می‌گوید کدام مورد گزارش PageSpeed را بهتر می‌کند.' ],
		'code'      => [ 'nav' => 'tools', 'title' => 'کد سفارشی', 'icon' => 'dashicons-editor-code', 'description' => 'کد HTML یا اسکریپت دلخواه در همه صفحه‌های سایت (نه پیشخوان).' ],
		'backup'    => [ 'nav' => 'tools', 'title' => 'پشتیبان تنظیمات', 'icon' => 'dashicons-backup', 'description' => 'همه تنظیمات این صفحه (و چیدمان صفحه اصلی) در یک فایل؛ برای نگه‌داری یا انتقال به سایت دیگر.' ],
	];
}

/**
 * قاب‌های هر تب (کارت با عنوان و توضیح). ترتیب = ترتیب نمایش در تب.
 *
 *   layout: grid (پیش‌فرض) | toggles (کلیدها کنار هم) | swatches (رنگ‌ها) |
 *           cards (زیرگروه‌ها کنار هم: نمادهای اعتماد) |
 *           rows (هر زیرگروه یک ردیف با ستون‌های هم‌تراز: شبکه‌ها)
 *   half:   در صفحه پهن، نصف عرض (دو قاب کوچک کنار هم)
 *
 * @return array<string, array{section:string, title:string, help?:string, icon?:string, layout?:string, half?:bool, columns?:list<string>}>
 */
function hodima_settings_panels(): array {
	return [
		'brand_logo'         => [ 'section' => 'brand', 'title' => 'لوگو', 'icon' => 'dashicons-format-image', 'layout' => 'logo', 'help' => 'لوگوی هدر سایت؛ پیش‌نمایش روی رنگ هدر است.' ],
		'brand_palette'      => [ 'section' => 'brand', 'title' => 'پالت رنگ برند', 'icon' => 'dashicons-admin-appearance', 'layout' => 'swatches', 'help' => 'رنگ‌های کل سایت (و صفحه‌های تنظیمات پیشخوان) از این پنج رنگ ساخته می‌شوند. بعد از ذخیره، کش لایت‌اسپید خودکار پاک می‌شود.' ],

		'type_preview'       => [ 'section' => 'typography', 'title' => 'پیش‌نمایش', 'icon' => 'dashicons-visibility', 'layout' => 'typepreview', 'collapsible' => true, 'help' => 'برای دیدن، باز کنید. با هر تغییر پایین همین‌جا به‌روز می‌شود (پیش از ذخیره). اندازه تیترها در عرض همین کادر نمایش داده می‌شود.' ],
		'type_fonts'         => [ 'section' => 'typography', 'title' => 'فونت و اندازه متن', 'icon' => 'dashicons-editor-textcolor', 'help' => 'فایل‌های وزیرمتن (هفت وزن ۳۰۰ تا ۹۰۰) همراه قالب‌اند و از همین سایت بارگذاری می‌شوند. مرورگر هر وزن را فقط وقتی دانلود می‌کند که در صفحه به کار رفته باشد.' ],
		'type_colors'        => [ 'section' => 'typography', 'title' => 'رنگ متن و لینک‌ها', 'icon' => 'dashicons-art', 'layout' => 'colors', 'help' => 'رنگ اصلی نوشته‌ها، رنگ لینک‌ها و حالت زیر نشانگر ماوس. رنگ لینک‌ها از پالت برند است و با عوض شدن پالت، خودکار عوض می‌شود.' ],
		'type_headings'      => [ 'section' => 'typography', 'title' => 'تیترها (H1 تا H6)', 'icon' => 'dashicons-heading', 'layout' => 'rows', 'headings' => true, 'columns' => [ 'تیتر', 'اندازه دسکتاپ (px)', 'اندازه موبایل (px)' ], 'columns_more' => [ 'تیتر', 'وزن', 'فاصله خطوط', 'رنگ' ], 'help' => 'اندازه از «موبایل» (صفحه ۳۹۰ پیکسل) تا «دسکتاپ» (۱۲۰۰ پیکسل و بیشتر) نرم تغییر می‌کند. پایه تیترهای داخل متن مقاله، برگه، توضیح محصول و دسته است؛ H1 عنوان همه صفحه‌ها هم هست (محصول، مقاله، دسته، فروشگاه، وبلاگ، برگه، جستجو، ویدیو). تیترهای طراحی‌شده بخش‌ها (کارت محصول، فوتر، عنوان بخش‌های صفحه اصلی) اندازه خودشان را دارند. ترتیب درست: هر سطح کوچک‌تر از سطح بالاتر.' ],
		'type_custom'        => [ 'section' => 'typography', 'title' => 'فونت آپلودی', 'icon' => 'dashicons-upload', 'layout' => 'tiles', 'collapsible' => true, 'force_open' => 'hodima_typography_custom_selected', 'open_on' => [ 'font_body', 'font_heading' ], 'primary' => [ 'font_w400', 'font_w700' ], 'help' => 'برای فونتی که مجوز استفاده در وب را دارید (مثلا ایران‌سنس یا یکان‌بخ). فایل woff2 هر وزن را از کتابخانه رسانه انتخاب یا آپلود کنید؛ وزن «معمولی (۴۰۰)» لازم است و وزن خالی از نزدیک‌ترین وزن موجود ساخته می‌شود. بعد در قاب «فونت‌ها» گزینه «فونت آپلودی» را انتخاب کنید.' ],

		'look_preview'       => [ 'section' => 'layout', 'title' => 'پیش‌نمایش', 'icon' => 'dashicons-visibility', 'layout' => 'lookpreview', 'help' => 'نمونه کارت و دکمه با انتخاب‌های پایین (پیش از ذخیره).' ],
		'look_shape'         => [ 'section' => 'layout', 'title' => 'گوشه‌ها و دکمه‌ها', 'icon' => 'dashicons-art', 'half' => true ],
		'look_width'         => [ 'section' => 'layout', 'title' => 'عرض صفحه', 'icon' => 'dashicons-editor-expand', 'half' => true ],

		'header_main'        => [ 'section' => 'header', 'title' => 'پشتیبانی و جستجو', 'icon' => 'dashicons-format-chat', 'help' => 'دکمه پشتیبانی فقط وقتی دیده می‌شود که در تب «اطلاعات تماس» دست‌کم یک راه ارتباطی وارد شده باشد.' ],

		'footer_about'       => [ 'section' => 'footer', 'title' => 'ستون «درباره ما»', 'icon' => 'dashicons-info', 'half' => true ],
		'footer_guide'       => [ 'section' => 'footer', 'title' => 'ستون «راهنمای خرید»', 'icon' => 'dashicons-sos', 'half' => true ],
		'footer_trust'       => [ 'section' => 'footer', 'title' => 'ستون «نماد اعتماد»', 'icon' => 'dashicons-shield', 'layout' => 'cards', 'help' => 'تا سه نماد (مثلا اینماد، ساماندهی، اتحادیه). نمادهای دارای تصویر به همین ترتیب کنار هم نمایش داده می‌شوند؛ نماد بدون تصویر نادیده گرفته می‌شود.' ],
		'footer_consult'     => [ 'section' => 'footer', 'title' => 'فرم مشاوره و کپی‌رایت', 'icon' => 'dashicons-edit' ],

		'home_mode'          => [ 'section' => 'home', 'title' => 'روش ساخت صفحه اصلی', 'icon' => 'dashicons-admin-generic' ],
		// قاب «بخش‌های صفحه اصلی» را home-layout-admin.php می‌سازد

		'shop_header'        => [ 'section' => 'shop', 'title' => 'سربرگ صفحه فروشگاه', 'icon' => 'dashicons-cover-image', 'help' => 'زیرعنوان و ویژگی‌های خالی نمایش داده نمی‌شوند.' ],
		'shop_list'          => [ 'section' => 'shop', 'title' => 'فهرست محصولات', 'icon' => 'dashicons-grid-view', 'help' => 'فروشگاه، دسته‌ها و برچسب‌های محصول.' ],

		'product_texts'      => [ 'section' => 'product', 'title' => 'متن‌ها', 'icon' => 'dashicons-editor-textcolor' ],
		'product_sections'   => [ 'section' => 'product', 'title' => 'بخش‌های صفحه محصول', 'icon' => 'dashicons-visibility', 'layout' => 'toggles', 'help' => 'بخش خاموش برای همه محصولات پنهان می‌شود؛ بخش روشن فقط وقتی محتوا دارد دیده می‌شود (کادر «رسانه» هر محصول و نظرات ووکامرس).' ],
		'product_price_feed' => [ 'section' => 'product', 'title' => 'قیمت در اسکیما (گوگل)', 'icon' => 'dashicons-tag', 'help' => 'عددی که گوگل (اسکیما)، پیش‌نمایش لینک در شبکه‌های اجتماعی (متاتگ قیمت) و فایل‌های هوش مصنوعی (llms) به‌عنوان قیمت محصول می‌بینند. قیمت در صفحه محصول، سبد و پرداخت عوض نمی‌شود. بعد از تغییر، کش لایت‌اسپید را پاک کنید. قیمت و نام محصولات در ترب: «ابزارهای هدیما ← ترب».' ],
		'product_upsells'    => [ 'section' => 'product', 'title' => 'محصولات پیشنهادی (Upsell)', 'icon' => 'dashicons-cart', 'help' => 'محصولاتی که در ویرایش محصول، بخش «محصولات مرتبط ← افزایش فروش» انتخاب می‌کنید.' ],

		'blog_page'          => [ 'section' => 'blog', 'title' => 'صفحه وبلاگ', 'icon' => 'dashicons-welcome-write-blog' ],
		'blog_post_meta'     => [ 'section' => 'blog', 'title' => 'اطلاعات زیر عنوان مقاله', 'icon' => 'dashicons-calendar-alt', 'layout' => 'toggles', 'help' => 'تاریخ، زمان مطالعه و نویسنده به خواننده و گوگل نشان می‌دهد مقاله تازه و معتبر است.' ],
		'blog_post_extra'    => [ 'section' => 'blog', 'title' => 'امکانات صفحه مقاله', 'icon' => 'dashicons-admin-generic', 'layout' => 'toggles', 'help' => 'فهرست مطالب، نوار پیشرفت، برچسب‌ها، اشتراک‌گذاری، کادر نویسنده و مقاله قبلی/بعدی.' ],
		'blog_post_content'  => [ 'section' => 'blog', 'title' => 'متن مقاله', 'icon' => 'dashicons-editor-expand' ],
		'blog_related'       => [ 'section' => 'blog', 'title' => 'مقالات مرتبط', 'icon' => 'dashicons-admin-links', 'help' => 'فهرست مقاله‌های دیگر زیر هر مقاله، بعد از دیدگاه‌ها.' ],

		'notfound_main'      => [ 'section' => 'notfound', 'title' => 'متن‌ها و دکمه‌ها', 'icon' => 'dashicons-editor-textcolor' ],

		'contact_phone'      => [ 'section' => 'contact', 'title' => 'تلفن', 'icon' => 'dashicons-phone' ],
		'contact_messengers' => [ 'section' => 'contact', 'title' => 'پیام‌رسان‌ها', 'icon' => 'dashicons-format-chat', 'help' => 'دکمه‌های پنجره پشتیبانی هدر.' ],
		'contact_address'    => [ 'section' => 'contact', 'title' => 'آدرس', 'icon' => 'dashicons-location' ],

		'social_visibility'  => [ 'section' => 'social', 'title' => 'نمایش در صفحه‌ها', 'icon' => 'dashicons-visibility' ],

		'analytics_ga'       => [ 'section' => 'analytics', 'title' => 'Google Analytics 4', 'icon' => 'dashicons-chart-area' ],

		'extras_top'         => [ 'section' => 'extras', 'title' => 'بازگشت به بالا', 'icon' => 'dashicons-arrow-up-alt', 'half' => true ],
		'extras_mobile_nav'  => [ 'section' => 'extras', 'title' => 'نوار پایین موبایل', 'icon' => 'dashicons-smartphone', 'layout' => 'toggles', 'help' => '«خانه» همیشه هست؛ فروشگاه و سبد فقط با ووکامرس. بیشتر از ۵ مورد نیست تا روی صفحه کوچک جا شود.' ],
		'code_main'          => [ 'section' => 'code', 'title' => 'کد دلخواه', 'icon' => 'dashicons-editor-code' ],
		'backup_main'        => [ 'section' => 'backup', 'title' => 'دریافت و بازگردانی', 'icon' => 'dashicons-backup', 'layout' => 'backup', 'help' => 'تصویرها (لوگو، نمادها، فونت‌ها) با شناسه کتابخانه رسانه ذخیره می‌شوند؛ روی سایت دیگر اگر همان فایل نباشد خالی می‌مانند و باید دوباره انتخاب شوند.' ],
		'speed_scripts'      => [ 'section' => 'speed', 'title' => 'بارگذاری اسکریپت‌ها', 'icon' => 'dashicons-media-code', 'help' => 'گزارش PageSpeed: «Eliminate render-blocking resources»، «Reduce unused JavaScript» و «Third-party code». اثرش را بعد از ذخیره و پاک‌کردن کش لایت‌اسپید در pagespeed.web.dev ببینید.' ],
		'speed_images'       => [ 'section' => 'speed', 'title' => 'تصاویر', 'icon' => 'dashicons-format-image', 'help' => 'گزارش PageSpeed: «Improve image delivery». تبدیل تصویرها به WebP و کیفیت آن‌ها در افزونه لایت‌اسپید (Image Optimization ← WebP Quality حدود ۷۵ تا ۸۰) تنظیم می‌شود، نه اینجا.' ],
		'speed_preconnect'   => [ 'section' => 'speed', 'title' => 'دامنه پخش ویدیو و پادکست', 'icon' => 'dashicons-video-alt3', 'help' => 'اگر ویدیو و پادکست‌ها روی دامنه جدا (هاست دانلود یا CDN) هستند، اتصال زودهنگام چند صدم ثانیه از شروع پخش کم می‌کند. دامنه را خودتان وارد کنید یا از «دامنه‌های پیدا شده» زیر کادر انتخاب کنید.' ],
	];
}
