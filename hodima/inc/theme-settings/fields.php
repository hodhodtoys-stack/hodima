<?php
/**
 * Theme Settings — تعریف فیلدهای تنظیمات (نوع، پیش‌فرض، برچسب، قاب)، فیلدهای تایپوگرافی و نمادهای اعتماد، گروه‌ها
 * Path: hodima/inc/theme-settings/fields.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HODIMA_SETTINGS_OPTION = 'hodima_theme_settings';
const HODIMA_SETTINGS_PAGE   = 'hodima-settings';
const HODIMA_SETTINGS_HOOK   = 'toplevel_page_' . HODIMA_SETTINGS_PAGE; // شناسه صفحه پیشخوان (از 3.0.0 منوی سطح اول؛ قبلا appearance_page_…)
const HODIMA_TRUST_SLOTS     = 3; // تعداد نمادهای اعتماد فوتر

/* =========================================================================
 * ۱. تعریف فیلدها و خواندن تنظیمات
 * ========================================================================= */

/**
 * تعریف همه فیلدها: نوع، مقدار پیش‌فرض، برچسب و قاب (panel).
 *
 * تب هر فیلد از قابش می‌آید (hodima_settings_panels). کلید فیلدها نام ذخیره‌شده در
 * گزینه hodima_theme_settings است و نباید عوض شود؛ ترتیب و جای نمایش آزاد است.
 *
 * @return array<string, array{panel:string, type:string, label:string, default:mixed, group?:string, help?:string, placeholder?:string, wide?:bool}>
 */
function hodima_settings_fields(): array {
	return [
		// ── برند ────────────────────────────────────────────────────
		'logo_id'          => [ 'panel' => 'brand_logo', 'type' => 'image', 'label' => 'تصویر لوگو', 'default' => 0, 'preview' => 'header', 'help' => 'بهتر است نسخه SVG یا WebP با پس‌زمینه شفاف باشد. اگر خالی بماند نام سایت نمایش داده می‌شود.' ],
		'logo_invert'      => [ 'panel' => 'brand_logo', 'type' => 'toggle', 'label' => 'لوگو سفید نمایش داده شود', 'default' => true, 'help' => 'برای لوگوی رنگی روی پس‌زمینه تیره هدر. نتیجه را در پیش‌نمایش کنار تصویر ببینید.' ],

		// ── پالت رنگ برند (بازسازی قالب، مرحله ۵؛ توکن‌های assets/css/tokens.css) ──
		'color_primary'      => [ 'panel' => 'brand_palette', 'type' => 'color', 'label' => 'رنگ اصلی', 'default' => '#25316a', 'help' => 'هدر، دکمه‌ها و عنوان‌ها؛ نسخه تیره و سایه‌ها خودکار ساخته می‌شوند.' ],
		'color_secondary'    => [ 'panel' => 'brand_palette', 'type' => 'color', 'label' => 'رنگ دوم', 'default' => '#607bbd', 'help' => 'انتهای گرادیان هدر و لینک‌ها.' ],
		'color_third'        => [ 'panel' => 'brand_palette', 'type' => 'color', 'label' => 'رنگ سوم (روشن)', 'default' => '#b6c2f3', 'help' => 'زمینه‌ها و خطوط.' ],
		'color_accent'       => [ 'panel' => 'brand_palette', 'type' => 'color', 'label' => 'رنگ تأکید', 'default' => '#6a2b9a', 'help' => 'دکمه‌های مهم و برچسب‌ها.' ],
		'color_accent_light' => [ 'panel' => 'brand_palette', 'type' => 'color', 'label' => 'رنگ تأکید روشن', 'default' => '#a341c8', 'help' => 'انتهای گرادیان دکمه‌های مهم.' ],

		// ── تایپوگرافی (2.9.6؛ CSS: inc/typography.php، پیش‌فرض‌ها = tokens.css) ──
		...hodima_typography_fields(),

		// ── ظاهر عمومی (2.9.7؛ CSS: inc/appearance.php، پیش‌فرض‌ها = tokens.css) ──
		'radius_style'     => [ 'panel' => 'look_shape', 'type' => 'select', 'label' => 'گردی گوشه‌ها', 'default' => 'default', 'options' => [ 'sharp' => 'تیز (بدون گردی)', 'soft' => 'کم', 'default' => 'معمولی (پیش‌فرض)', 'round' => 'گرد' ], 'help' => 'کارت‌ها، کادرها، دکمه‌ها، فیلدها و تصویرهای کل سایت با هم. شکل‌های کاملا گرد (دکمه‌های کپسولی، دایره‌ها) همان می‌مانند.' ],
		'button_style'     => [ 'panel' => 'look_shape', 'type' => 'select', 'label' => 'سبک دکمه‌ها', 'default' => 'gradient', 'options' => [ 'gradient' => 'گرادیان رنگ اصلی و دوم (پیش‌فرض)', 'solid' => 'یک‌رنگ: رنگ اصلی', 'secondary' => 'یک‌رنگ: رنگ دوم', 'accent' => 'گرادیان رنگ تأکید' ], 'help' => 'دکمه‌های عمومی سایت و ووکامرس (افزودن به سبد، ثبت، ارسال فرم‌ها). دکمه‌های طراحی‌شده بخش‌ها (مثل «تسویه حساب») رنگ خودشان را دارند.' ],
		'container_width'  => [ 'panel' => 'look_width', 'type' => 'number', 'label' => 'بیشترین عرض محتوا (پیکسل)', 'default' => 1440, 'min' => 960, 'max' => 1920, 'help' => 'هدر، فوتر، فروشگاه، دسته‌ها، محصول و وبلاگ در صفحه‌های پهن‌تر از این عدد وسط صفحه می‌مانند. رایج: ۱۲۰۰ تا ۱۴۴۰.' ],

		// ── امکانات کاربری (2.9.8؛ inc/extras.php) — پیش‌فرض خاموش: ظاهر سایت تا روشن کردن همان ──
		'back_to_top'        => [ 'panel' => 'extras_top', 'type' => 'toggle', 'label' => 'دکمه «بازگشت به بالا»', 'default' => false, 'wide' => true, 'help' => 'بعد از کمی اسکرول پایین صفحه (سمت راست) ظاهر می‌شود؛ با کیبورد هم کار می‌کند.' ],
		'mobile_nav'         => [ 'panel' => 'extras_mobile_nav', 'type' => 'toggle', 'label' => 'نوار پایین موبایل', 'default' => false, 'wide' => true, 'help' => 'نوار ثابت پایین صفحه در موبایل و تبلت عمودی (عرض ۷۶۸ پیکسل و کمتر)، با «خانه» و موارد روشن پایین.' ],
		'mobile_nav_shop'    => [ 'panel' => 'extras_mobile_nav', 'type' => 'toggle', 'label' => 'فروشگاه', 'default' => true ],
		'mobile_nav_search'  => [ 'panel' => 'extras_mobile_nav', 'type' => 'toggle', 'label' => 'جستجو', 'default' => true, 'help' => 'کادر جستجوی هدر را باز می‌کند (اگر در تب «هدر» روشن باشد).' ],
		'mobile_nav_cart'    => [ 'panel' => 'extras_mobile_nav', 'type' => 'toggle', 'label' => 'سبد خرید', 'default' => true ],
		'mobile_nav_support' => [ 'panel' => 'extras_mobile_nav', 'type' => 'toggle', 'label' => 'پشتیبانی', 'default' => true, 'help' => 'پنجره پشتیبانی هدر (اگر در «اطلاعات تماس» راهی وارد شده باشد).' ],

		// ── هدر ─────────────────────────────────────────────────────
		'header_support_text'  => [ 'panel' => 'header_main', 'type' => 'text', 'label' => 'متن دکمه پشتیبانی', 'default' => 'پشتیبانی' ],
		'header_popup_title'   => [ 'panel' => 'header_main', 'type' => 'text', 'label' => 'عنوان پنجره پشتیبانی', 'default' => 'ارتباط با ما' ],
		'header_search'        => [ 'panel' => 'header_main', 'type' => 'toggle', 'label' => 'جستجو در هدر', 'default' => true, 'help' => 'کادر جستجوی محصولات و مقاله‌ها.' ],

		// ── فوتر ────────────────────────────────────────────────────
		'about_title'      => [ 'panel' => 'footer_about', 'type' => 'text', 'label' => 'عنوان ستون', 'default' => 'درباره ما' ],
		'about_text'       => [ 'panel' => 'footer_about', 'type' => 'textarea', 'label' => 'متن', 'default' => '' ],
		'guide_title'      => [ 'panel' => 'footer_guide', 'type' => 'text', 'label' => 'عنوان ستون', 'default' => 'راهنمای خرید' ],
		'guide_text'       => [ 'panel' => 'footer_guide', 'type' => 'textarea', 'label' => 'متن', 'default' => '', 'help' => 'آدرس تب «اطلاعات تماس» زیر همین متن نمایش داده می‌شود.' ],
		'consult_title'    => [ 'panel' => 'footer_consult', 'type' => 'text', 'label' => 'عنوان ستون فرم مشاوره', 'default' => 'مشاوره خرید' ],
		'copyright'        => [ 'panel' => 'footer_consult', 'type' => 'text', 'label' => 'متن کپی‌رایت (پایین فوتر)', 'default' => '', 'help' => 'اگر خالی بماند نام سایت نمایش داده می‌شود.' ],
		'trust_title'      => [ 'panel' => 'footer_trust', 'type' => 'text', 'label' => 'عنوان ستون', 'default' => 'نماد اعتماد' ],
		...hodima_trust_fields(),

		// ── صفحه اصلی (بخش‌ها: inc/theme-settings/home-layout-admin.php) ──
		'home_builder'     => [ 'panel' => 'home_mode', 'type' => 'toggle', 'label' => 'ساخت صفحه اصلی از چیدمان پایین', 'default' => false, 'wide' => true, 'help' => 'خاموش: صفحه اصلی مثل قبل از متن برگه صفحه اصلی (ویرایشگر برگه و شورت‌کدهایش) ساخته می‌شود. روشن: از بخش‌های پایین، به همان ترتیب.' ],
		'home_scroll_arrows' => [ 'panel' => 'home_mode', 'type' => 'toggle', 'label' => 'فلش قبلی/بعدی روی اسلایدرهای صفحه اصلی', 'default' => true, 'wide' => true, 'help' => 'دکمه‌های گرد روی محصولات، مقالات و دسته‌ها، در دسکتاپ و موبایل؛ وقتی اسکرول لازم نیست یا به ابتدا/انتها رسیده‌اید پنهان می‌شوند.' ],

		// ── فروشگاه و دسته‌ها ─────────────────────────────────────────
		'shop_title'       => [ 'panel' => 'shop_header', 'type' => 'text', 'label' => 'عنوان', 'default' => '', 'help' => 'اگر خالی بماند عنوان برگه فروشگاه ووکامرس نمایش داده می‌شود.' ],
		'shop_subtitle'    => [ 'panel' => 'shop_header', 'type' => 'text', 'label' => 'زیرعنوان', 'default' => '' ],
		'shop_features'    => [ 'panel' => 'shop_header', 'type' => 'textarea', 'label' => 'ویژگی‌ها (هر خط یک مورد)', 'default' => '', 'help' => 'مثلا: اصالت کالا، قیمت رقابتی، ارسال سریع — هر کدام در یک خط.' ],
		'shop_per_page'    => [ 'panel' => 'shop_list', 'type' => 'number', 'label' => 'تعداد محصول در هر صفحه', 'default' => 36, 'min' => 6, 'max' => 120, 'help' => 'مضرب ۱۲ (مثلا ۲۴، ۳۶، ۴۸) در همه اندازه‌های صفحه‌نمایش ردیف کامل می‌سازد. عدد خیلی بزرگ صفحه را روی موبایل کند می‌کند.' ],

		// ── صفحه محصول (قبلا ثابت در woocommerce/content-single-product.php) ──
		'product_price_empty'   => [ 'panel' => 'product_texts', 'type' => 'text', 'label' => 'جای قیمت، وقتی قیمت ندارد', 'default' => 'تماس بگیرید' ],
		'product_out_of_stock'  => [ 'panel' => 'product_texts', 'type' => 'text', 'label' => 'پیام محصول ناموجود (جای دکمه خرید)', 'default' => 'برای اطلاع از شارژ مجدد تماس بگیرید.' ],
		'product_podcast_label' => [ 'panel' => 'product_texts', 'type' => 'text', 'label' => 'برچسب پلیر پادکست', 'default' => 'پادکست' ],
		'product_show_podcast'  => [ 'panel' => 'product_sections', 'type' => 'toggle', 'label' => 'پادکست', 'default' => true ],
		'product_show_faq'      => [ 'panel' => 'product_sections', 'type' => 'toggle', 'label' => 'سوالات متداول', 'default' => true ],
		'product_show_video'    => [ 'panel' => 'product_sections', 'type' => 'toggle', 'label' => 'ویدیو', 'default' => true ],
		'product_show_reviews'  => [ 'panel' => 'product_sections', 'type' => 'toggle', 'label' => 'نظرات کاربران', 'default' => true ],
		// عنوان بخش نظرات (از 3.8.9؛ قبلا ثابت در inc/woocommerce/product-page.php) و اندازه عنوان‌ها؛ پیش‌فرض‌ها = اندازه قبلی (۲۴ و ۲۲٫۴)
		'product_reviews_title'             => [ 'panel' => 'product_reviews', 'type' => 'text', 'label' => 'عنوان بخش', 'default' => 'نظرات کاربران درباره {product}', 'help_visible' => true, 'help' => '{product} به‌جای نام همان محصول می‌نشیند (عنوان همراه نام محصول برای گوگل و موتورهای پاسخ‌گو گویاتر است). خالی = همین پیش‌فرض.' ],
		'product_reviews_visible'           => [ 'panel' => 'product_reviews', 'type' => 'number', 'label' => 'تعداد نظر پیش از «نمایش همه»', 'default' => 3, 'min' => 1, 'max' => 20, 'help' => 'بقیه نظرها با دکمه «نمایش همه نظرات» باز می‌شوند (همه در صفحه هستند و گوگل آن‌ها را می‌بیند). فرم ثبت نظر همیشه دیده می‌شود.' ],
		'product_reviews_title_size'        => [ 'panel' => 'product_reviews', 'type' => 'number', 'label' => 'اندازه عنوان دسکتاپ (پیکسل)', 'default' => 24, 'min' => 12, 'max' => 48, 'step' => 0.1 ],
		'product_reviews_title_size_mobile' => [ 'panel' => 'product_reviews', 'type' => 'number', 'label' => 'اندازه عنوان موبایل (پیکسل)', 'default' => 24, 'min' => 12, 'max' => 48, 'step' => 0.1 ],
		// نمایش نظرات محصول (3.9.1)
		'product_reviews_summary'  => [ 'panel' => 'product_reviews_display', 'type' => 'toggle', 'label' => 'خلاصه امتیازها', 'default' => true, 'help' => 'میانگین ستاره‌ها، تعداد نظر و نوار درصد هر امتیاز (۵ تا ۱ ستاره)، بالای نظرها. فقط وقتی دست‌کم یک نظر امتیازدار هست.' ],
		'product_reviews_verified' => [ 'panel' => 'product_reviews_display', 'type' => 'toggle', 'label' => 'نشان «خریدار این محصول»', 'default' => true, 'help' => 'کنار نام کسی که این محصول را واقعا از سایت خریده (تشخیص با ووکامرس). خاموش: همان رفتار ووکامرس (ووکامرس ← تنظیمات ← محصولات).' ],
		'product_reviews_sort'     => [ 'panel' => 'product_reviews_display', 'type' => 'toggle', 'label' => 'مرتب‌سازی نظرها', 'default' => true, 'help' => 'جدیدترین، قدیمی‌ترین، بیشترین و کمترین امتیاز؛ در خود صفحه و بدون بارگذاری دوباره. فقط وقتی دست‌کم دو نظر هست.' ],
		'product_reviews_avatars'  => [ 'panel' => 'product_reviews_display', 'type' => 'toggle', 'label' => 'تصویر پروفایل', 'default' => true, 'help' => 'تصویر Gravatar کنار هر نظر. خاموش: صفحه سبک‌تر (بدون دانلود تصویر از Gravatar).' ],
		'product_upsells_title' => [ 'panel' => 'product_upsells', 'type' => 'text', 'label' => 'عنوان بخش', 'default' => 'محصولات مشابه' ],
		'product_upsells_limit' => [ 'panel' => 'product_upsells', 'type' => 'number', 'label' => 'تعداد', 'default' => 6, 'min' => 0, 'max' => 24, 'help' => '۰ = بخش نمایش داده نشود.' ],
		'product_upsells_title_size'        => [ 'panel' => 'product_upsells', 'type' => 'number', 'label' => 'اندازه عنوان دسکتاپ (پیکسل)', 'default' => 22.4, 'min' => 12, 'max' => 48, 'step' => 0.1 ],
		'product_upsells_title_size_mobile' => [ 'panel' => 'product_upsells', 'type' => 'number', 'label' => 'اندازه عنوان موبایل (پیکسل)', 'default' => 22.4, 'min' => 12, 'max' => 48, 'step' => 0.1 ],
		// قیمت اعلام‌شده به گوگل؛ خواننده: Hodima SEO (inc/product-price.php). قیمت و نام ترب از 3.0.7 در «ابزارهای هدیما ← ترب» (Hodima SEO)
		'product_offer_price'   => [ 'panel' => 'product_price_feed', 'type' => 'select', 'label' => 'قیمتی که اعلام شود', 'default' => 'unit', 'options' => [ 'unit' => 'قیمت تک محصول', 'min_order' => 'مبلغ حداقل سفارش' ], 'help' => '«حداقل سفارش» همان مبلغی است که در ویرایش هر محصول (فیلد حداقل مبلغ سفارش) وارد کرده‌اید و صفحه محصول کنار قیمت نشان می‌دهد. محصولی که حداقل سفارش ندارد یا متغیر است، همیشه قیمت تک را اعلام می‌کند. به افزونه Hodima SEO نیاز دارد.' ],

		// ── وبلاگ (قبلا ثابت در archive-blog.php و single-post.php) ──
		'blog_columns'         => [ 'panel' => 'blog_page', 'type' => 'select', 'label' => 'تعداد ستون کارت‌ها (دسکتاپ)', 'default' => '4', 'options' => [ '4' => '۴ ستون (پیش‌فرض)', '3' => '۳ ستون', '2' => '۲ ستون' ], 'help' => 'در تبلت و موبایل خودکار کمتر می‌شود.' ],
		'blog_card_ratio'      => [ 'panel' => 'blog_page', 'type' => 'select', 'label' => 'شکل تصویر کارت', 'default' => '16-9', 'options' => [ '16-9' => 'عریض ۱۶:۹ (پیش‌فرض)', '4-3' => 'معمولی ۴:۳', '1-1' => 'مربع ۱:۱' ] ],
		'blog_per_page'        => [ 'panel' => 'blog_page', 'type' => 'number', 'label' => 'تعداد مقاله در هر صفحه', 'default' => 0, 'min' => 0, 'max' => 60, 'help' => 'برای صفحه وبلاگ، دسته‌ها، برچسب‌ها و آرشیوها. ۰ = همان عدد وردپرس (تنظیمات ← خواندن). با تغییر آن مقاله‌های هر صفحه (/page/N/) جابه‌جا می‌شوند.' ],
		'blog_featured'        => [ 'panel' => 'blog_page', 'type' => 'toggle', 'label' => 'مقاله ویژه بالای صفحه وبلاگ', 'default' => true, 'wide' => true, 'help' => 'اولین مقاله صفحه اول وبلاگ بزرگ و تمام‌عرض نمایش داده می‌شود. مقاله‌ای را که در ویرایش «سنجاق به صفحه اول وبلاگ» زده‌اید وردپرس اول می‌گذارد.' ],
		'blog_search'          => [ 'panel' => 'blog_page', 'type' => 'toggle', 'label' => 'جستجوی مقاله‌ها', 'default' => true, 'wide' => true, 'help' => 'کادر جستجوی مخصوص مقاله‌ها بالای صفحه وبلاگ، دسته‌ها و برچسب‌ها.' ],
		'blog_infinite_scroll' => [ 'panel' => 'blog_page', 'type' => 'toggle', 'label' => 'بارگذاری خودکار مقاله‌های بعدی با اسکرول', 'default' => true, 'help' => 'صفحه‌بندی واقعی برای موتورهای جستجو در هر حال سر جایش است.' ],
		'blog_card_excerpt'    => [ 'panel' => 'blog_page', 'type' => 'toggle', 'label' => 'خلاصه مقاله در کارت‌ها', 'default' => false, 'wide' => true, 'help' => 'چند خط اول هر مقاله (یا «خلاصه» ویرایشگر) زیر عنوان کارت.' ],
		'blog_cats_bar'        => [ 'panel' => 'blog_page', 'type' => 'toggle', 'label' => 'نوار دسته‌های وبلاگ', 'default' => true, 'wide' => true, 'help' => 'دسته‌های اصلی وبلاگ به‌صورت دکمه بالای مقاله‌ها در صفحه وبلاگ، دسته‌ها و برچسب‌ها؛ دسته جاری برجسته است. دسته «بدون دسته‌بندی» نمایش داده نمی‌شود.' ],
		'blog_cats_count'      => [ 'panel' => 'blog_page', 'type' => 'toggle', 'label' => 'تعداد مقاله کنار هر دسته', 'default' => true, 'wide' => true, 'help' => 'عدد کوچک کنار نام دسته در نوار دسته‌ها. اگر دسته‌ای زیردسته دارد، در صفحه آن دسته زیردسته‌هایش هم زیر نوار نمایش داده می‌شوند.' ],
		// صفحه هر مقاله (3.0.0): اطلاعات زیر عنوان و جمع شدن متن بلند
		'blog_meta_date'       => [ 'panel' => 'blog_post_meta', 'type' => 'toggle', 'label' => 'تاریخ انتشار', 'default' => true ],
		'blog_meta_updated'    => [ 'panel' => 'blog_post_meta', 'type' => 'toggle', 'label' => 'تاریخ به‌روزرسانی', 'default' => true, 'help' => 'فقط وقتی مقاله بعد از انتشار ویرایش شده باشد.' ],
		'blog_meta_reading'    => [ 'panel' => 'blog_post_meta', 'type' => 'toggle', 'label' => 'زمان مطالعه', 'default' => true, 'help' => 'تخمینی از تعداد کلمه‌ها؛ در کارت‌های وبلاگ هم.' ],
		'blog_meta_category'   => [ 'panel' => 'blog_post_meta', 'type' => 'toggle', 'label' => 'دسته مقاله (لینک)', 'default' => true, 'help' => 'دسته اصلی مقاله (همان دسته مسیر راهنما) با لینک به صفحه دسته.' ],
		'blog_meta_author'     => [ 'panel' => 'blog_post_meta', 'type' => 'toggle', 'label' => 'نویسنده', 'default' => false ],
		// امکانات صفحه مقاله (3.3.6)
		'blog_toc'             => [ 'panel' => 'blog_post_extra', 'type' => 'toggle', 'label' => 'فهرست مطالب', 'default' => true, 'help' => 'از تیترهای H2 و H3 متن مقاله، بالای متن (دست‌کم سه تیتر). روی لینک‌ها کلیک کنید تا به بخش بروید.' ],
		'blog_progress'        => [ 'panel' => 'blog_post_extra', 'type' => 'toggle', 'label' => 'نوار پیشرفت مطالعه', 'default' => true, 'help' => 'خط باریک بالای صفحه که با اسکرول در متن مقاله پر می‌شود.' ],
		'blog_tags'            => [ 'panel' => 'blog_post_extra', 'type' => 'toggle', 'label' => 'برچسب‌های مقاله', 'default' => true, 'help' => 'زیر متن مقاله، با لینک به صفحه هر برچسب.' ],
		'blog_share'           => [ 'panel' => 'blog_post_extra', 'type' => 'toggle', 'label' => 'اشتراک‌گذاری', 'default' => true, 'help' => 'کپی لینک، تلگرام و واتساپ.' ],
		'blog_author_box'      => [ 'panel' => 'blog_post_extra', 'type' => 'toggle', 'label' => 'کادر نویسنده', 'default' => true, 'help' => 'فقط وقتی نویسنده در پروفایلش «معرفی» نوشته باشد (کاربران ← پروفایل).' ],
		'blog_prev_next'       => [ 'panel' => 'blog_post_extra', 'type' => 'toggle', 'label' => 'مقاله قبلی و بعدی', 'default' => true, 'help' => 'پایین مقاله؛ اول از همان دسته، اگر نبود از کل وبلاگ.' ],
		'blog_content_collapse' => [ 'panel' => 'blog_post_content', 'type' => 'toggle', 'label' => 'متن بلند مقاله جمع شود («نمایش بیشتر»)', 'default' => true, 'wide' => true, 'help' => 'به افزونه Hodima Media نیاز دارد. خاموش: کل متن یک‌جا نمایش داده می‌شود.' ],
		'blog_content_height'  => [ 'panel' => 'blog_post_content', 'type' => 'number', 'label' => 'ارتفاع متن جمع‌شده (پیکسل)', 'default' => 400, 'min' => 200, 'max' => 1500 ],
		// دیدگاه‌های مقاله و برگه (3.9.1)
		'blog_comments_avatars'  => [ 'panel' => 'blog_comments', 'type' => 'toggle', 'label' => 'تصویر پروفایل کنار دیدگاه‌ها', 'default' => true, 'wide' => true, 'help' => 'تصویر Gravatar هر نویسنده دیدگاه (اگر در «تنظیمات ← گفت‌وگو» نمایش آواتار روشن باشد). خاموش: صفحه سبک‌تر.' ],
		'blog_comments_visible'  => [ 'panel' => 'blog_comments', 'type' => 'number', 'label' => 'تعداد دیدگاه پیش از «نمایش همه»', 'default' => 3, 'min' => 1, 'max' => 20, 'help' => 'در مقاله‌ها و برگه‌ها. بقیه با دکمه «نمایش همه دیدگاه‌ها» باز می‌شوند؛ فرم همیشه دیده می‌شود.' ],
		'blog_related_title'   => [ 'panel' => 'blog_related', 'type' => 'text', 'label' => 'عنوان', 'default' => 'مقالات مرتبط' ],
		'blog_related_limit'   => [ 'panel' => 'blog_related', 'type' => 'number', 'label' => 'تعداد', 'default' => 4, 'min' => 0, 'max' => 12, 'help' => '۰ = بخش نمایش داده نشود.' ],
		'blog_related_source'  => [ 'panel' => 'blog_related', 'type' => 'select', 'label' => 'کدام مقاله‌ها', 'default' => 'category', 'options' => [ 'category' => 'هم‌دسته (آخرین مقاله‌های همان دسته)', 'cluster' => 'هم‌خوشه (خوشه موضوعی افزونه سئو)، و اگر کم بود هم‌دسته' ] ],

		// ── صفحه ۴۰۴ (بازسازی قالب، مرحله ۴؛ قبلا ثابت در 404.php) ──
		'notfound_title'       => [ 'panel' => 'notfound_main', 'type' => 'text', 'label' => 'عنوان', 'default' => 'صفحه مورد نظر پیدا نشد!' ],
		'notfound_placeholder' => [ 'panel' => 'notfound_main', 'type' => 'text', 'label' => 'متن داخل کادر جستجو', 'default' => 'دنبال چه چیزی می‌گردید؟' ],
		'notfound_text'        => [ 'panel' => 'notfound_main', 'type' => 'textarea', 'label' => 'توضیح', 'default' => 'متأسفیم، به نظر می‌رسد آدرسی که وارد کرده‌اید اشتباه است یا این صفحه به مکان دیگری منتقل شده است.' ],
		'notfound_shop_button' => [ 'panel' => 'notfound_main', 'type' => 'toggle', 'label' => 'دکمه «رفتن به فروشگاه»', 'default' => true, 'help' => 'دکمه «صفحه اصلی» همیشه هست.' ],

		// ── اطلاعات تماس ───────────────────────────────────────────
		'phone'            => [ 'panel' => 'contact_phone', 'type' => 'tel', 'label' => 'شماره تماس', 'default' => '', 'placeholder' => '09120000000', 'help' => 'دکمه «تماس تلفنی» پنجره پشتیبانی.' ],
		'phone_2'          => [ 'panel' => 'contact_phone', 'type' => 'tel', 'label' => 'شماره تماس دوم', 'default' => '', 'placeholder' => '02100000000', 'help' => 'مثلا تلفن ثابت؛ در نسخه ماشین‌خوان (llms.txt) کنار شماره اصلی می‌آید.' ],
		'whatsapp_url'     => [ 'panel' => 'contact_messengers', 'type' => 'url', 'label' => 'واتس‌اپ', 'default' => '', 'placeholder' => 'https://wa.me/989120000000' ],
		'telegram_url'     => [ 'panel' => 'contact_messengers', 'type' => 'url', 'label' => 'تلگرام', 'default' => '', 'placeholder' => 'https://t.me/username' ],
		'rubika_url'       => [ 'panel' => 'contact_messengers', 'type' => 'url', 'label' => 'روبیکا', 'default' => '', 'placeholder' => 'https://rubika.ir/username' ],
		'address'          => [ 'panel' => 'contact_address', 'type' => 'text', 'label' => 'آدرس', 'default' => '' ],
		'map_url'          => [ 'panel' => 'contact_address', 'type' => 'url', 'label' => 'لینک نقشه', 'default' => '', 'placeholder' => 'https://maps.app.goo.gl/...' ],

		// ── شبکه‌های اجتماعی ─────────────────────────────────────────
		'social_hide_urls' => [ 'panel' => 'social_visibility', 'type' => 'urllist', 'label' => 'صفحه‌هایی که نوار در آن‌ها نمایش داده نشود', 'default' => '', 'placeholder' => "https://example.com/cart/\n/checkout/\n/product/*", 'help' => 'هر آدرس در یک خط؛ آدرس کامل صفحه یا فقط مسیر آن (مثلا /cart/). برای صفحه اصلی / و برای یک صفحه و همه زیرصفحه‌هایش * در انتها (مثلا /blog/*). پارامترهای بعد از ? در نظر گرفته نمی‌شوند.' ],

		// ── Google Analytics ───────────────────────────────────────
		'ga_id'            => [ 'panel' => 'analytics_ga', 'type' => 'ga', 'label' => 'شناسه اندازه‌گیری (Measurement ID)', 'default' => '', 'placeholder' => 'G-XXXXXXXXXX', 'help' => 'خالی = کد آمار چاپ نمی‌شود.' ],
		'ga_skip_editors'  => [ 'panel' => 'analytics_ga', 'type' => 'toggle', 'label' => 'بازدید مدیران و نویسندگان ثبت نشود', 'default' => true ],
		'ga_production'    => [ 'panel' => 'analytics_ga', 'type' => 'toggle', 'label' => 'فقط روی سایت اصلی (Production)', 'default' => true, 'help' => 'روی استیجینگ یا لوکال (WP_ENVIRONMENT_TYPE) کد آمار چاپ نمی‌شود تا آمار واقعی آلوده نشود.' ],
		'ga_load'          => [ 'panel' => 'pagespeed_scripts', 'type' => 'select', 'label' => 'زمان بارگذاری کد آمار', 'default' => 'now', 'options' => [ 'now' => 'همراه صفحه (پیش‌فرض؛ مثل قبل)', 'interaction' => 'بعد از اولین تعامل بازدیدکننده (سریع‌تر)' ], 'help' => 'کد گوگل حدود ۱۰۰ کیلوبایت جاوااسکریپت است و سرعت اولیه صفحه را (گزارش PageSpeed: «شخص ثالث»، «جاوااسکریپت استفاده‌نشده») پایین می‌آورد. با «بعد از اولین تعامل»، کد پس از اولین لمس، کلیک، اسکرول یا تایپ بارگذاری می‌شود (و اگر تعاملی نشد، ۶ ثانیه بعد از بارگذاری صفحه). تعداد بازدیدهای بسیار کوتاه و بی‌تعامل (کمتر از ۶ ثانیه) ثبت نمی‌شود.' ],

		// ── پیج اسپید (بخش ۱۷۸؛ تب جدا از «تنظیمات ویدئو») ─────────────────────────────────
		'defer_jquery'          => [ 'panel' => 'pagespeed_scripts', 'type' => 'toggle', 'label' => 'jQuery بدون مسدود کردن نمایش صفحه', 'default' => true, 'wide' => true, 'help' => 'jQuery با defer بارگذاری می‌شود تا مرورگر منتظر آن نماند و صفحه زودتر نمایش داده شود. فقط برای بازدیدکننده مهمان در صفحه‌های عمومی (سبد، پرداخت و حساب کاربری مستثنا هستند). اگر افزونه‌ای کد jQuery درون‌خطی دارد و بعد از روشن‌شدن خطا داد، این را خاموش کنید.' ],
		'critical_css'          => [ 'panel' => 'pagespeed_css', 'type' => 'toggle', 'label' => 'استفاده از CSS حیاتی برای بازدیدکنندگان', 'default' => false, 'wide' => true, 'help_visible' => true, 'help' => 'روشن: CSS لازم بالای صفحه داخل خود صفحه می‌آید و بقیه CSS بدون معطل کردن نمایش بارگذاری می‌شود (PageSpeed: «Render-blocking requests» و «Speed Index»). فقط نوع‌هایی که «آماده» هستند اثر می‌گیرند؛ بقیه مثل قبل. بعد از ذخیره، کش لایت‌اسپید را پاک کنید. گزینه «Load CSS Asynchronously» لایت‌اسپید (Page Optimization ← CSS) خاموش بماند. خاموش: سایت فورا مثل قبل.' ],
		'card_img_sizes'        => [ 'panel' => 'pagespeed_images', 'type' => 'toggle', 'label' => 'اندازه دقیق تصویر کارت محصولات', 'default' => true, 'wide' => true, 'help' => 'در فروشگاه و دسته‌ها، مرورگر نسخه تصویر را بر اساس عرض واقعی کارت انتخاب می‌کند (به‌جای تصویر ۵۰۰ پیکسلی برای کارت حدود ۲۰۰ پیکسلی). حجم تصویرهای فهرست محصولات کمتر می‌شود و ظاهر عوض نمی‌شود.' ],
		// ── دامنه ویدئو: اتصال زودهنگام به دامنه پخش رسانه (نوسازی قالب، مرحله ۲ و ۴؛ قبلا dl.hodima.com ثابت در کد) ──
		'preconnect_hosts'      => [ 'panel' => 'speed_preconnect', 'type' => 'hostlist', 'label' => 'دامنه ویدئو و پادکست', 'default' => '', 'placeholder' => 'dl.example.com', 'help' => 'دامنه‌ای که فایل‌های ویدیو و پادکست سایت از آن پخش می‌شوند (مثلا هاست دانلود یا CDN)؛ اگر بیش از یکی است هر کدام در یک خط. در هر صفحه‌ای که ویدیو یا پادکستی از این دامنه دارد (کادر «رسانه»، صفحه ویدیو یا داخل متن نوشته)، مرورگر از همان ابتدای بارگذاری به آن وصل می‌شود تا پخش زودتر شروع شود؛ صفحه‌های دیگر اتصال اضافه نمی‌گیرند.' ],
		'code_head'             => [ 'panel' => 'code_main', 'type' => 'code', 'label' => 'کد داخل <head> همه صفحه‌ها', 'default' => '', 'placeholder' => '<meta name="…" content="…">', 'help_visible' => true, 'help' => 'مثلا تأیید مالکیت سایت یا کد ابزارهای آمار. فقط مدیر با دسترسی «HTML نامحدود» می‌تواند ذخیره کند. کد اشتباه ممکن است ظاهر یا کارکرد سایت را خراب کند.' ],
		'code_body_end'         => [ 'panel' => 'code_main', 'type' => 'code', 'label' => 'کد پایان صفحه (پیش از </body>)', 'default' => '', 'placeholder' => '<script>…</script>', 'help_visible' => true, 'help' => 'اسکریپت‌هایی که لازم نیست زود بارگذاری شوند (گفتگوی آنلاین، پیکسل‌ها). برای سرعت بهتر از کادر بالا این‌جاست.' ],
		'preconnect_media_auto' => [ 'panel' => 'speed_preconnect', 'type' => 'toggle', 'label' => 'تشخیص خودکار دامنه هر ویدیو و پادکست', 'default' => true, 'wide' => true, 'help' => 'دامنه ویدیو، کاور و پادکست هر صفحه (حتی آپارات و یوتیوب یا دامنه‌ای که بالا وارد نشده) خودکار تشخیص داده می‌شود. خاموش: فقط دامنه‌های کادر بالا.' ],
	];
}

/**
 * سه نماد اعتماد فوتر؛ هر کدام تصویر و لینک.
 * نماد اول همان کلیدهای قدیمی (trust_image_id / trust_url) را نگه می‌دارد تا
 * نماد ذخیره‌شده سایت بعد از به‌روزرسانی از دست نرود؛ بقیه پسوند _2 و _3 دارند.
 * @return array<string, array<string, mixed>>
 */
function hodima_trust_fields(): array {

	$fields = [];

	for ( $i = 1; $i <= HODIMA_TRUST_SLOTS; $i++ ) {
		$suffix = 1 === $i ? '' : "_{$i}";
		$fields[ "trust_image_id{$suffix}" ] = [ 'panel' => 'footer_trust', 'group' => "trust_{$i}", 'type' => 'image', 'label' => 'تصویر', 'default' => 0 ];
		$fields[ "trust_url{$suffix}" ]      = [ 'panel' => 'footer_trust', 'group' => "trust_{$i}", 'type' => 'url', 'label' => 'لینک', 'default' => '', 'placeholder' => 'https://', 'help' => 'صفحه اعتبارسنجی نماد.' ];
	}

	return $fields;
}

/**
 * فیلدهای تب «تایپوگرافی». پیش‌فرض‌ها دقیقا همان مقدارهای assets/css/tokens.css
 * است؛ hodima_typography_css() فقط مقدار متفاوت با پیش‌فرض را چاپ می‌کند.
 *
 * @return array<string, array<string, mixed>>
 */
function hodima_typography_fields(): array {

	// نام دلخواه فونت آپلودی در فهرست انتخاب (گزینه خام: hodima_setting() این‌جا حلقه بی‌پایان می‌سازد)
	$custom  = (string) ( ( (array) get_option( HODIMA_SETTINGS_OPTION, [] ) )['font_custom_name'] ?? '' );
	$custom  = hodima_typography_clean_font_name( $custom );
	$fonts   = [ 'vazirmatn' => 'وزیرمتن (همراه قالب)', 'custom' => '' !== $custom ? $custom . ' (فونت آپلودی)' : 'فونت آپلودی (قاب «فونت آپلودی»)', 'system' => 'فونت سیستم کاربر (بدون دانلود فونت)' ];
	$weights = hodima_typography_weights();
	$colors  = hodima_typography_color_options();
	$links   = $colors + [ 'custom' => 'دلخواه…' ]; // فقط لینک‌ها: رنگ آزاد با انتخاب‌گر (3.1.9)

	$fields = [
		'font_body'    => [ 'panel' => 'type_fonts', 'type' => 'select', 'label' => 'فونت متن', 'default' => 'vazirmatn', 'options' => $fonts, 'help' => 'متن، منو، دکمه‌ها و فرم‌ها.' ],
		'font_heading' => [ 'panel' => 'type_headings', 'type' => 'select', 'label' => 'فونت تیترها', 'default' => 'body', 'options' => [ 'body' => 'همان فونت متن' ] + $fonts, 'help' => 'H1 تا H6 و هر چه داخل تیتر است (مثلا لینک عنوان کارت‌ها).' ],

		'body_size'              => [ 'panel' => 'type_fonts', 'type' => 'number', 'label' => 'اندازه متن (پیکسل)', 'default' => 16, 'min' => 12, 'max' => 22, 'help' => 'متن مقاله‌ها، برگه‌ها و بدنه سایت. استاندارد خوانایی ۱۶ است.' ],
		'body_line_height'       => [ 'panel' => 'type_fonts', 'type' => 'number', 'label' => 'فاصله خطوط متن', 'default' => 1.8, 'min' => 1.2, 'max' => 2.4, 'step' => 0.05, 'help' => 'ضریب اندازه متن؛ برای فارسی ۱٫۷ تا ۲ خواناتر است.' ],
		'color_text'             => [ 'panel' => 'type_colors', 'type' => 'color', 'label' => 'رنگ متن و تیترها', 'default' => '#111111', 'help' => 'رنگ اصلی نوشته‌ها در کل سایت؛ تیترها جدا هم قابل تنظیم‌اند (قاب «تیترها»).' ],
		'link_color'             => [ 'panel' => 'type_colors', 'type' => 'select', 'label' => 'رنگ لینک‌ها', 'default' => 'secondary', 'options' => $links, 'swatch' => true, 'custom_key' => 'link_color_custom', 'help' => 'از پالت برند (با عوض شدن پالت، لینک‌ها هم عوض می‌شوند) یا «دلخواه»: هر رنگی با انتخاب‌گر یا کد رنگ، مستقل از پالت.' ],
		'link_color_custom'      => [ 'panel' => 'type_colors', 'type' => 'color', 'label' => 'رنگ دلخواه لینک‌ها', 'default' => '#607bbd', 'embedded' => true ], // در کارت «رنگ لینک‌ها» چاپ می‌شود
		'link_hover_color'       => [ 'panel' => 'type_colors', 'type' => 'select', 'label' => 'رنگ لینک با ماوس', 'default' => 'primary', 'options' => $links, 'swatch' => true, 'custom_key' => 'link_hover_color_custom', 'help' => 'وقتی نشانگر ماوس روی لینک می‌رود.' ],
		'link_hover_color_custom' => [ 'panel' => 'type_colors', 'type' => 'color', 'label' => 'رنگ دلخواه لینک با ماوس', 'default' => '#25316a', 'embedded' => true ],
		'content_link_underline' => [ 'panel' => 'type_colors', 'type' => 'toggle', 'label' => 'زیرخط لینک‌های داخل متن', 'default' => true, 'wide' => false, 'help' => 'لینک‌های داخل متن مقاله، برگه و توضیح محصول/دسته. استاندارد دسترس‌پذیری (WCAG): لینک نباید فقط با رنگ از متن جدا شود. دکمه‌ها و کارت‌ها زیرخط نمی‌گیرند.' ],
	];

	foreach ( hodima_typography_heading_defaults() as $level => $default ) {
		$group = "type_h{$level}";
		$fields[ "h{$level}_size" ]        = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'number', 'label' => "اندازه دسکتاپ H{$level} (پیکسل)", 'default' => $default['max'], 'min' => 10, 'max' => 80 ];
		$fields[ "h{$level}_size_mobile" ] = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'number', 'label' => "اندازه موبایل H{$level} (پیکسل)", 'default' => $default['min'], 'min' => 10, 'max' => 80 ];
		$fields[ "h{$level}_weight" ]      = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'select', 'label' => "وزن H{$level}", 'default' => (string) $default['weight'], 'options' => $weights ];
		$fields[ "h{$level}_line_height" ] = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'number', 'label' => "فاصله خطوط H{$level}", 'default' => $default['line_height'], 'min' => 1, 'max' => 2.4, 'step' => 0.05 ];
		$fields[ "h{$level}_color" ]       = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'select', 'label' => "رنگ H{$level}", 'default' => 'text', 'options' => $colors, 'swatch' => true ];
	}

	$fields['font_custom_name'] = [ 'panel' => 'type_custom', 'type' => 'text', 'label' => 'نام فونت', 'default' => '', 'placeholder' => 'مثلا ایران‌سنس', 'help' => 'نامی که در فهرست «فونت متن» و «فونت تیترها» می‌بینید (فقط حرف، عدد، فاصله، - و _). خالی: «فونت آپلودی».' ];

	foreach ( array_keys( $weights ) as $weight ) {
		$fields[ "font_custom_{$weight}" ] = [ 'panel' => 'type_custom', 'group' => "font_w{$weight}", 'type' => 'font', 'label' => 'فایل وزن ' . $weights[ $weight ], 'default' => 0 ];
	}

	return $fields;
}

/**
 * پیش‌فرض تیترها (همان tokens.css): اندازه دسکتاپ (max) و موبایل (min) به پیکسل.
 * تا 2.9.5: H1 ۲۷px، H2 ۱۶px، H3 تا H5 ۱۸px، H6 ۱۶px — H2 کوچک‌تر از H3 بود.
 *
 * @return array<int, array{max:int, min:int, weight:int, line_height:float}>
 */
function hodima_typography_heading_defaults(): array {
	return [
		1 => [ 'max' => 32, 'min' => 26, 'weight' => 700, 'line_height' => 1.5 ],
		2 => [ 'max' => 26, 'min' => 22, 'weight' => 700, 'line_height' => 1.5 ],
		3 => [ 'max' => 22, 'min' => 19, 'weight' => 700, 'line_height' => 1.5 ],
		4 => [ 'max' => 19, 'min' => 17, 'weight' => 700, 'line_height' => 1.5 ],
		5 => [ 'max' => 17, 'min' => 16, 'weight' => 700, 'line_height' => 1.5 ],
		6 => [ 'max' => 15, 'min' => 14, 'weight' => 700, 'line_height' => 1.5 ],
	];
}

/**
 * وزن‌های فونت (فایل‌های Vazirmatn همراه قالب همین هفت وزن‌اند).
 *
 * @return array<int, string>
 */
function hodima_typography_weights(): array {
	return [ 300 => 'نازک (۳۰۰)', 400 => 'معمولی (۴۰۰)', 500 => 'متوسط (۵۰۰)', 600 => 'نیم‌ضخیم (۶۰۰)', 700 => 'ضخیم (۷۰۰)', 800 => 'خیلی ضخیم (۸۰۰)', 900 => 'سیاه (۹۰۰)' ];
}

/**
 * رنگ‌های قابل انتخاب برای لینک و تیتر: متغیرهای پالت (نه رنگ ثابت)، تا با
 * عوض شدن پالت در تب «برند و رنگ‌ها» همه با هم عوض شوند.
 *
 * @return array<string, string>
 */
function hodima_typography_color_options(): array {
	return [ 'text' => 'رنگ متن', 'primary' => 'رنگ اصلی', 'secondary' => 'رنگ دوم', 'accent' => 'رنگ تأکید', 'accent-light' => 'رنگ تأکید روشن' ];
}

/** رنگ ذخیره‌شده یک گزینه hodima_typography_color_options() (text ← color_text، accent-light ← color_accent_light). */
function hodima_settings_palette_hex( string $option ): string {
	return (string) hodima_setting( 'color_' . str_replace( '-', '_', $option ) );
}

/** ارقام فارسی برای برچسب‌ها (مستقل از زبان پیشخوان). */
function hodima_fa_digits( int|string $value ): string {
	return strtr( (string) $value, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/**
 * زیرگروه‌های داخل یک قاب (کارت هر نماد اعتماد، ردیف هر شبکه اجتماعی).
 *
 * @return array<string, array{title:string}>
 */
function hodima_settings_groups(): array {

	$groups = [];

	for ( $i = 1; $i <= HODIMA_TRUST_SLOTS; $i++ ) {
		$groups[ "trust_{$i}" ] = [ 'title' => 'نماد ' . hodima_fa_digits( $i ) ];
	}

	foreach ( array_keys( hodima_typography_heading_defaults() ) as $level ) {
		$groups[ "type_h{$level}" ] = [ 'title' => "H{$level}" ];
	}

	foreach ( hodima_typography_weights() as $weight => $label ) {
		$groups[ "font_w{$weight}" ] = [ 'title' => $label ];
	}

	return $groups;
}
