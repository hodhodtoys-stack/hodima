<?php
/**
 * Theme Settings — برند، اطلاعات تماس، فوتر و Google Analytics
 * Path: inc/theme-settings/theme-settings.php
 *
 * پیش از این، لوگو (با آدرس کامل دامنه سایت اصلی)، شماره تلفن، لینک
 * روبیکا و تلگرام، آدرس، نماد اعتماد، متن‌های فوتر و شناسه Google Analytics
 * مستقیم داخل header.php، footer.php و functions.php نوشته شده بودند:
 *   - قالب روی دامنه یا استیجینگ دیگری هنوز تصاویر را از سایت اصلی می‌کشید؛
 *   - آمار استیجینگ و بازدید مدیران با آمار واقعی سایت قاطی می‌شد؛
 *   - هر تغییر ساده (مثلا شماره تماس) ویرایش کد لازم داشت.
 *
 * حالا همه از «نمایش ← تنظیمات قالب هدیما» در پیشخوان خوانده می‌شوند.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HODIMA_SETTINGS_OPTION = 'hodima_theme_settings';
const HODIMA_SETTINGS_PAGE   = 'hodima-settings';
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
		'logo_invert'      => [ 'panel' => 'brand_logo', 'type' => 'toggle', 'label' => 'لوگو سفید نمایش داده شود', 'default' => true, 'wide' => true, 'help' => 'برای لوگوی رنگی روی پس‌زمینه تیره هدر. نتیجه را در پیش‌نمایش کنار تصویر ببینید.' ],

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
		'product_upsells_title' => [ 'panel' => 'product_upsells', 'type' => 'text', 'label' => 'عنوان بخش', 'default' => 'محصولات مشابه' ],
		'product_upsells_limit' => [ 'panel' => 'product_upsells', 'type' => 'number', 'label' => 'تعداد', 'default' => 6, 'min' => 0, 'max' => 24, 'help' => '۰ = بخش نمایش داده نشود.' ],

		// ── وبلاگ (قبلا ثابت در archive-blog.php و single-post.php) ──
		'blog_title'           => [ 'panel' => 'blog_page', 'type' => 'text', 'label' => 'عنوان صفحه وبلاگ (H1)', 'default' => '', 'help' => 'خالی = عنوان برگه «نوشته‌ها» (تنظیمات ← خواندن). همین نام در مسیر راهنمای مقاله‌ها هم می‌آید.' ],
		'blog_intro'           => [ 'panel' => 'blog_page', 'type' => 'textarea', 'label' => 'متن کوتاه زیر عنوان', 'default' => '' ],
		'blog_infinite_scroll' => [ 'panel' => 'blog_page', 'type' => 'toggle', 'label' => 'بارگذاری خودکار مقاله‌های بعدی با اسکرول', 'default' => true, 'help' => 'صفحه‌بندی واقعی برای موتورهای جستجو در هر حال سر جایش است.' ],
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
		'phone_2'          => [ 'panel' => 'contact_phone', 'type' => 'tel', 'label' => 'شماره تماس دوم (اختیاری)', 'default' => '', 'placeholder' => '02100000000', 'help' => 'مثلا تلفن ثابت؛ در نسخه ماشین‌خوان (llms.txt) کنار شماره اصلی می‌آید.' ],
		'whatsapp_url'     => [ 'panel' => 'contact_messengers', 'type' => 'url', 'label' => 'واتس‌اپ', 'default' => '', 'placeholder' => 'https://wa.me/989120000000' ],
		'telegram_url'     => [ 'panel' => 'contact_messengers', 'type' => 'url', 'label' => 'تلگرام', 'default' => '', 'placeholder' => 'https://t.me/username' ],
		'rubika_url'       => [ 'panel' => 'contact_messengers', 'type' => 'url', 'label' => 'روبیکا', 'default' => '', 'placeholder' => 'https://rubika.ir/username' ],
		'address'          => [ 'panel' => 'contact_address', 'type' => 'text', 'label' => 'آدرس', 'default' => '' ],
		'map_url'          => [ 'panel' => 'contact_address', 'type' => 'url', 'label' => 'لینک نقشه', 'default' => '', 'placeholder' => 'https://maps.app.goo.gl/...' ],

		// ── شبکه‌های اجتماعی ─────────────────────────────────────────
		'social_title'     => [ 'panel' => 'social_bar', 'type' => 'text', 'label' => 'عنوان نوار', 'default' => 'شبکه‌های اجتماعی' ],
		'social_hide_urls' => [ 'panel' => 'social_bar', 'type' => 'urllist', 'label' => 'صفحه‌هایی که نوار در آن‌ها نمایش داده نشود', 'default' => '', 'placeholder' => "https://example.com/cart/\n/checkout/\n/product/*", 'help' => 'هر آدرس در یک خط؛ آدرس کامل صفحه یا فقط مسیر آن (مثلا /cart/). برای صفحه اصلی / و برای یک صفحه و همه زیرصفحه‌هایش * در انتها (مثلا /blog/*). پارامترهای بعد از ? در نظر گرفته نمی‌شوند.' ],
		...hodima_social_fields(),

		// ── Google Analytics ───────────────────────────────────────
		'ga_id'            => [ 'panel' => 'analytics_ga', 'type' => 'ga', 'label' => 'شناسه اندازه‌گیری (Measurement ID)', 'default' => '', 'placeholder' => 'G-XXXXXXXXXX', 'help' => 'خالی = کد آمار چاپ نمی‌شود.' ],
		'ga_skip_editors'  => [ 'panel' => 'analytics_ga', 'type' => 'toggle', 'label' => 'بازدید مدیران و نویسندگان ثبت نشود', 'default' => true ],
		'ga_production'    => [ 'panel' => 'analytics_ga', 'type' => 'toggle', 'label' => 'فقط روی سایت اصلی (Production)', 'default' => true, 'help' => 'روی استیجینگ یا لوکال (WP_ENVIRONMENT_TYPE) کد آمار چاپ نمی‌شود تا آمار واقعی آلوده نشود.' ],

		// ── دامنه ویدئو: اتصال زودهنگام به دامنه پخش رسانه (نوسازی قالب، مرحله ۲ و ۴؛ قبلا dl.hodima.com ثابت در کد) ──
		'preconnect_hosts'      => [ 'panel' => 'speed_preconnect', 'type' => 'hostlist', 'label' => 'دامنه ویدئو و پادکست', 'default' => '', 'placeholder' => 'dl.example.com', 'help' => 'دامنه‌ای که فایل‌های ویدیو و پادکست سایت از آن پخش می‌شوند (مثلا هاست دانلود یا CDN)؛ اگر بیش از یکی است هر کدام در یک خط. در هر صفحه‌ای که ویدیو یا پادکستی از این دامنه دارد (کادر «رسانه»، صفحه ویدیو یا داخل متن نوشته)، مرورگر از همان ابتدای بارگذاری به آن وصل می‌شود تا پخش زودتر شروع شود؛ صفحه‌های دیگر اتصال اضافه نمی‌گیرند.' ],
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
		$fields[ "trust_url{$suffix}" ]      = [ 'panel' => 'footer_trust', 'group' => "trust_{$i}", 'type' => 'url', 'label' => 'لینک (اختیاری)', 'default' => '', 'placeholder' => 'https://', 'help' => 'صفحه اعتبارسنجی نماد.' ];
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

	$fonts   = [ 'vazirmatn' => 'وزیرمتن (همراه قالب)', 'custom' => 'فونت آپلودی (قاب «فونت آپلودی»)', 'system' => 'فونت سیستم کاربر (بدون دانلود فونت)' ];
	$weights = hodima_typography_weights();
	$colors  = hodima_typography_color_options();

	$fields = [
		'font_body'    => [ 'panel' => 'type_fonts', 'type' => 'select', 'label' => 'فونت متن', 'default' => 'vazirmatn', 'options' => $fonts, 'help' => 'متن، منو، دکمه‌ها و فرم‌ها.' ],
		'font_heading' => [ 'panel' => 'type_fonts', 'type' => 'select', 'label' => 'فونت تیترها', 'default' => 'body', 'options' => [ 'body' => 'همان فونت متن' ] + $fonts, 'help' => 'H1 تا H6 و هر چه داخل تیتر است (مثلا لینک عنوان کارت‌ها).' ],

		'body_size'              => [ 'panel' => 'type_body', 'type' => 'number', 'label' => 'اندازه متن (پیکسل)', 'default' => 16, 'min' => 12, 'max' => 22, 'help' => 'متن مقاله‌ها، برگه‌ها و بدنه سایت. استاندارد خوانایی ۱۶ است.' ],
		'body_line_height'       => [ 'panel' => 'type_body', 'type' => 'number', 'label' => 'فاصله خطوط متن', 'default' => 1.8, 'min' => 1.2, 'max' => 2.4, 'step' => 0.05, 'help' => 'ضریب اندازه متن؛ برای فارسی ۱٫۷ تا ۲ خواناتر است.' ],
		'color_text'             => [ 'panel' => 'type_body', 'type' => 'color', 'label' => 'رنگ متن و تیترها', 'default' => '#111111', 'help' => 'رنگ اصلی نوشته‌ها در کل سایت؛ تیترها جدا هم قابل تنظیم‌اند (قاب «تیترها»).' ],
		'link_color'             => [ 'panel' => 'type_body', 'type' => 'select', 'label' => 'رنگ لینک‌ها', 'default' => 'secondary', 'options' => $colors, 'help' => 'از پالت برند؛ با عوض شدن پالت، لینک‌ها هم عوض می‌شوند.' ],
		'link_hover_color'       => [ 'panel' => 'type_body', 'type' => 'select', 'label' => 'رنگ لینک زیر نشانگر ماوس', 'default' => 'primary', 'options' => $colors ],
		'content_link_underline' => [ 'panel' => 'type_body', 'type' => 'toggle', 'label' => 'زیرخط لینک‌های داخل متن', 'default' => true, 'wide' => true, 'help' => 'لینک‌های داخل متن مقاله، برگه و توضیح محصول/دسته. استاندارد دسترس‌پذیری (WCAG): لینک نباید فقط با رنگ از متن جدا شود. دکمه‌ها و کارت‌ها زیرخط نمی‌گیرند.' ],
	];

	foreach ( hodima_typography_heading_defaults() as $level => $default ) {
		$group = "type_h{$level}";
		$fields[ "h{$level}_size" ]        = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'number', 'label' => "اندازه دسکتاپ H{$level} (پیکسل)", 'default' => $default['max'], 'min' => 10, 'max' => 80 ];
		$fields[ "h{$level}_size_mobile" ] = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'number', 'label' => "اندازه موبایل H{$level} (پیکسل)", 'default' => $default['min'], 'min' => 10, 'max' => 80 ];
		$fields[ "h{$level}_weight" ]      = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'select', 'label' => "وزن H{$level}", 'default' => (string) $default['weight'], 'options' => $weights ];
		$fields[ "h{$level}_line_height" ] = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'number', 'label' => "فاصله خطوط H{$level}", 'default' => $default['line_height'], 'min' => 1, 'max' => 2.4, 'step' => 0.05 ];
		$fields[ "h{$level}_color" ]       = [ 'panel' => 'type_headings', 'group' => $group, 'type' => 'select', 'label' => "رنگ H{$level}", 'default' => 'text', 'options' => $colors ];
	}

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

	foreach ( hodima_social_networks() as $key => $label ) {
		$groups[ "social_{$key}" ] = [ 'title' => $label ];
	}

	foreach ( array_keys( hodima_typography_heading_defaults() ) as $level ) {
		$groups[ "type_h{$level}" ] = [ 'title' => "H{$level}" ];
	}

	foreach ( hodima_typography_weights() as $weight => $label ) {
		$groups[ "font_w{$weight}" ] = [ 'title' => $label ];
	}

	return $groups;
}

/**
 * شبکه‌های اجتماعی قابل تنظیم (ترتیب نمایش در صفحه اصلی).
 *
 * @return array<string, string> شناسه => نام نمایشی
 */
function hodima_social_networks(): array {
	return (array) apply_filters( 'hodima_social_networks', [
		'instagram' => 'اینستاگرام',
		'telegram'  => 'تلگرام',
		'whatsapp'  => 'واتس‌اپ',
		'rubika'    => 'روبیکا',
		'aparat'    => 'آپارات',
		'eitaa'     => 'ایتا',
		'bale'      => 'بله',
		'youtube'   => 'یوتیوب',
		'linkedin'  => 'لینکدین',
	] );
}

/**
 * برای هر شبکه دو فیلد در یک ردیف: آدرس صفحه و آیکون (اختیاری).
 *
 * @return array<string, array<string, mixed>>
 */
function hodima_social_fields(): array {

	$fields = [];

	foreach ( hodima_social_networks() as $key => $label ) {
		$fields[ "social_{$key}_url" ]  = [ 'panel' => 'social_networks', 'group' => "social_{$key}", 'type' => 'url', 'label' => $label, 'default' => '', 'placeholder' => 'https://' ];
		$fields[ "social_{$key}_icon" ] = [ 'panel' => 'social_networks', 'group' => "social_{$key}", 'type' => 'image', 'label' => 'آیکون ' . $label, 'default' => 0 ];
	}

	return $fields;
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
		'tools'   => 'آمار و ویدئو',
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
		'header'    => [ 'nav' => 'look', 'title' => 'هدر', 'icon' => 'dashicons-editor-kitchensink', 'description' => 'نوار بالای همه صفحه‌ها. متن خالی به پیش‌فرض برمی‌گردد.' ],
		'footer'    => [ 'nav' => 'look', 'title' => 'فوتر', 'icon' => 'dashicons-table-row-after', 'description' => 'ستون‌های پایین همه صفحه‌ها. ستونی که محتوا نداشته باشد نمایش داده نمی‌شود.' ],
		'home'      => [ 'nav' => 'pages', 'title' => 'صفحه اصلی', 'icon' => 'dashicons-admin-home', 'description' => 'بخش‌های صفحه اصلی سایت: ترتیب، روشن/خاموش و تنظیمات هر بخش.' ],
		'shop'      => [ 'nav' => 'pages', 'title' => 'فروشگاه و دسته‌ها', 'icon' => 'dashicons-store', 'description' => 'سربرگ صفحه فروشگاه و تعداد محصول صفحه‌های فهرست محصولات.' ],
		'product'   => [ 'nav' => 'pages', 'title' => 'صفحه محصول', 'icon' => 'dashicons-products', 'description' => 'متن‌ها و بخش‌های صفحه هر محصول.' ],
		'blog'      => [ 'nav' => 'pages', 'title' => 'وبلاگ', 'icon' => 'dashicons-welcome-write-blog', 'description' => 'صفحه وبلاگ، آرشیو دسته‌ها و «مقالات مرتبط» زیر هر مقاله.' ],
		'notfound'  => [ 'nav' => 'pages', 'title' => 'صفحه ۴۰۴', 'icon' => 'dashicons-warning', 'description' => 'صفحه‌ای که بازدیدکننده با آدرس اشتباه یا حذف‌شده می‌بیند. متن خالی به پیش‌فرض برمی‌گردد.' ],
		'contact'   => [ 'nav' => 'contact', 'title' => 'اطلاعات تماس', 'icon' => 'dashicons-phone', 'description' => 'در پنجره «پشتیبانی» هدر و فوتر نمایش داده می‌شود. هر گزینه خالی نمایش داده نمی‌شود.' ],
		'social'    => [ 'nav' => 'contact', 'title' => 'شبکه‌های اجتماعی', 'icon' => 'dashicons-share', 'description' => 'نوار شبکه‌های اجتماعی بالای فوتر همه صفحه‌ها. بدون هیچ شبکه‌ای نوار نمایش داده نمی‌شود.' ],
		'analytics' => [ 'nav' => 'tools', 'title' => 'Google Analytics', 'icon' => 'dashicons-chart-area', 'description' => 'کد آمار GA4 با بارگذاری async و بدون مسدود کردن رندر صفحه اضافه می‌شود.' ],
		'speed'     => [ 'nav' => 'tools', 'title' => 'دامنه ویدئو', 'icon' => 'dashicons-video-alt3', 'description' => 'دامنه‌ای که ویدیو و پادکست‌های سایت از آن پخش می‌شوند؛ مرورگر در صفحه‌های دارای ویدیو از ابتدا به آن وصل می‌شود تا پخش زودتر شروع شود.' ],
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
		'brand_logo'         => [ 'section' => 'brand', 'title' => 'لوگو', 'icon' => 'dashicons-format-image', 'help' => 'لوگوی هدر سایت؛ پیش‌نمایش روی رنگ هدر است.' ],
		'brand_palette'      => [ 'section' => 'brand', 'title' => 'پالت رنگ برند', 'icon' => 'dashicons-admin-appearance', 'layout' => 'swatches', 'help' => 'رنگ‌های کل سایت (و صفحه‌های تنظیمات پیشخوان) از این پنج رنگ ساخته می‌شوند. بعد از ذخیره، کش لایت‌اسپید خودکار پاک می‌شود.' ],

		'type_preview'       => [ 'section' => 'typography', 'title' => 'پیش‌نمایش', 'icon' => 'dashicons-visibility', 'layout' => 'typepreview', 'help' => 'با هر تغییر پایین همین‌جا به‌روز می‌شود (پیش از ذخیره). اندازه تیترها در عرض همین کادر نمایش داده می‌شود.' ],
		'type_fonts'         => [ 'section' => 'typography', 'title' => 'فونت‌ها', 'icon' => 'dashicons-editor-textcolor', 'help' => 'فایل‌های وزیرمتن (هفت وزن ۳۰۰ تا ۹۰۰) همراه قالب‌اند و از همین سایت بارگذاری می‌شوند. مرورگر هر وزن را فقط وقتی دانلود می‌کند که در صفحه به کار رفته باشد.' ],
		'type_body'          => [ 'section' => 'typography', 'title' => 'متن و لینک‌ها', 'icon' => 'dashicons-editor-paragraph' ],
		'type_headings'      => [ 'section' => 'typography', 'title' => 'تیترها (H1 تا H6)', 'icon' => 'dashicons-heading', 'layout' => 'rows', 'columns' => [ 'تیتر', 'اندازه دسکتاپ (px)', 'اندازه موبایل (px)', 'وزن', 'فاصله خطوط', 'رنگ' ], 'help' => 'اندازه از «موبایل» (صفحه ۳۹۰ پیکسل) تا «دسکتاپ» (۱۲۰۰ پیکسل و بیشتر) نرم تغییر می‌کند. پایه تیترهای داخل متن مقاله، برگه، توضیح محصول و دسته است؛ تیترهای طراحی‌شده بخش‌ها (کارت محصول، فوتر، عنوان صفحه‌ها) اندازه خودشان را دارند. ترتیب درست: هر سطح کوچک‌تر از سطح بالاتر.' ],
		'type_custom'        => [ 'section' => 'typography', 'title' => 'فونت آپلودی', 'icon' => 'dashicons-upload', 'layout' => 'rows', 'columns' => [ 'وزن', 'فایل فونت (woff2)' ], 'help' => 'برای فونتی که مجوز استفاده در وب را دارید (مثلا ایران‌سنس یا یکان‌بخ). فایل woff2 هر وزن را از کتابخانه رسانه انتخاب یا آپلود کنید؛ وزن «معمولی (۴۰۰)» لازم است و وزن خالی از نزدیک‌ترین وزن موجود ساخته می‌شود. بعد در قاب «فونت‌ها» گزینه «فونت آپلودی» را انتخاب کنید.' ],

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
		'product_upsells'    => [ 'section' => 'product', 'title' => 'محصولات پیشنهادی (Upsell)', 'icon' => 'dashicons-cart', 'help' => 'محصولاتی که در ویرایش محصول، بخش «محصولات مرتبط ← افزایش فروش» انتخاب می‌کنید.' ],

		'blog_page'          => [ 'section' => 'blog', 'title' => 'صفحه وبلاگ', 'icon' => 'dashicons-welcome-write-blog' ],
		'blog_related'       => [ 'section' => 'blog', 'title' => 'مقالات مرتبط', 'icon' => 'dashicons-admin-links', 'help' => 'فهرست مقاله‌های دیگر زیر هر مقاله، بعد از دیدگاه‌ها.' ],

		'notfound_main'      => [ 'section' => 'notfound', 'title' => 'متن‌ها و دکمه‌ها', 'icon' => 'dashicons-editor-textcolor' ],

		'contact_phone'      => [ 'section' => 'contact', 'title' => 'تلفن', 'icon' => 'dashicons-phone' ],
		'contact_messengers' => [ 'section' => 'contact', 'title' => 'پیام‌رسان‌ها', 'icon' => 'dashicons-format-chat', 'help' => 'دکمه‌های پنجره پشتیبانی هدر.' ],
		'contact_address'    => [ 'section' => 'contact', 'title' => 'آدرس', 'icon' => 'dashicons-location' ],

		'social_bar'         => [ 'section' => 'social', 'title' => 'نوار شبکه‌ها', 'icon' => 'dashicons-admin-settings' ],
		'social_networks'    => [ 'section' => 'social', 'title' => 'شبکه‌ها', 'icon' => 'dashicons-share', 'layout' => 'rows', 'columns' => [ 'شبکه', 'آدرس صفحه', 'آیکون (اختیاری)' ], 'help' => 'شبکه‌ای که آدرس نداشته باشد نمایش داده نمی‌شود؛ بدون آیکون، نام شبکه نمایش داده می‌شود. آیکون مربعی و شفاف (SVG یا PNG) بهترین نتیجه را دارد.' ],

		'analytics_ga'       => [ 'section' => 'analytics', 'title' => 'Google Analytics 4', 'icon' => 'dashicons-chart-area' ],

		'speed_preconnect'   => [ 'section' => 'speed', 'title' => 'دامنه پخش ویدیو و پادکست', 'icon' => 'dashicons-video-alt3', 'help' => 'اگر ویدیو و پادکست‌ها روی دامنه جدا (مثلا dl.hodima.com) هستند، اتصال زودهنگام چند صدم ثانیه از شروع پخش کم می‌کند.' ],
	];
}

/**
 * همه تنظیمات، ادغام‌شده با پیش‌فرض‌ها.
 *
 * @return array<string, mixed>
 */
function hodima_settings(): array {

	static $cache = null;

	if ( null !== $cache ) {
		return $cache;
	}

	$defaults = array_map( static fn( array $field ): mixed => $field['default'], hodima_settings_fields() );
	$stored   = get_option( HODIMA_SETTINGS_OPTION, [] );
	$settings = array_merge( $defaults, is_array( $stored ) ? array_intersect_key( $stored, $defaults ) : [] );

	// عنوان خالی (مثلا عنوان ستون فوتر) به پیش‌فرض برمی‌گردد تا تیتر خالی چاپ نشود
	foreach ( $defaults as $key => $default ) {
		if ( is_string( $default ) && '' !== $default && '' === trim( (string) $settings[ $key ] ) ) {
			$settings[ $key ] = $default;
		}
	}

	$cache = $settings;

	return $cache;
}

/** یک مقدار از تنظیمات. */
function hodima_setting( string $key ): mixed {
	return hodima_settings()[ $key ] ?? null;
}

/**
 * تب یک فیلد (از قابش).
 *
 * @param array<string, mixed> $field
 */
function hodima_settings_field_section( array $field ): string {
	return (string) ( hodima_settings_panels()[ $field['panel'] ?? '' ]['section'] ?? '' );
}

/**
 * نام کامل فیلد برای پیام خطا، مثل «فوتر › نماد ۲ › لینک (اختیاری)».
 * برچسب‌ها داخل قاب کوتاه‌اند («لینک (اختیاری)»)؛ بیرون از قاب مبهم می‌شدند.
 */
function hodima_settings_field_label( string $key ): string {

	$field = hodima_settings_fields()[ $key ] ?? null;
	if ( null === $field ) {
		return $key;
	}

	$panel = hodima_settings_panels()[ $field['panel'] ] ?? [];
	$parts = [
		hodima_settings_sections()[ hodima_settings_field_section( $field ) ]['title'] ?? '',
		isset( $field['group'] ) ? ( hodima_settings_groups()[ $field['group'] ]['title'] ?? '' ) : ( $panel['title'] ?? '' ),
		$field['label'],
	];

	// بدون تکه خالی و تکرار پشت‌سرهم (ردیف «اینستاگرام» با برچسب «اینستاگرام»)
	$parts = array_values( array_filter( $parts, static fn( string $part ): bool => '' !== $part ) );
	$parts = array_filter( $parts, static fn( string $part, int $i ): bool => 0 === $i || $part !== $parts[ $i - 1 ], ARRAY_FILTER_USE_BOTH );

	return implode( ' › ', $parts );
}

/* =========================================================================
 * ۲. ثبت تنظیمات و صفحه پیشخوان
 * ========================================================================= */

add_action( 'admin_init', static function (): void {
	register_setting( 'hodima_theme_settings_group', HODIMA_SETTINGS_OPTION, [
		'type'              => 'array',
		'sanitize_callback' => 'hodima_settings_sanitize',
		'default'           => [],
		'show_in_rest'      => false,
	] );
} );

add_action( 'admin_menu', static function (): void {
	add_theme_page(
		'تنظیمات قالب هدیما',
		'تنظیمات قالب هدیما',
		'manage_options',
		HODIMA_SETTINGS_PAGE,
		'hodima_settings_render_page'
	);
} );

/**
 * پاک‌سازی و اعتبارسنجی همه فیلدها.
 * مقدار نامعتبر رد و به کاربر اطلاع داده می‌شود؛ مقدار قبلی حفظ نمی‌شود
 * تا خطا بی‌صدا پنهان نماند.
 *
 * @param mixed $input
 * @return array<string, mixed>
 */
function hodima_settings_sanitize( $input ): array {

	$input  = is_array( $input ) ? wp_unslash( $input ) : [];
	$clean  = [];

	foreach ( hodima_settings_fields() as $key => $field ) {

		// رفتار هر نوع: enum Hodima_Setting_Type (inc/classes)؛ نوع ناشناخته مثل قبل = متن
		$clean[ $key ] = hodima_setting_type( $field )->sanitize( $key, $field, $input[ $key ] ?? null );
	}

	// «فونت آپلودی» بدون فایل (inc/typography.php)
	return function_exists( 'hodima_typography_validate' ) ? hodima_typography_validate( $clean ) : $clean;
}

/**
 * نوع یک فیلد تنظیمات.
 *
 * @param array<string, mixed> $field
 */
function hodima_setting_type( array $field ): Hodima_Setting_Type {
	return Hodima_Setting_Type::tryFrom( (string) ( $field['type'] ?? '' ) ) ?? Hodima_Setting_Type::Text;
}

/** رنگ: #abc → #aabbcc (input type=color فقط شش رقمی می‌پذیرد)؛ نامعتبر = پیش‌فرض. */
function hodima_settings_sanitize_color( mixed $raw, string $fallback ): string {

	$hex = sanitize_hex_color( is_string( $raw ) ? trim( $raw ) : '' );

	return $hex ? hodima_rgb_hex( hodima_hex_rgb( $hex ) ) : strtolower( $fallback );
}

function hodima_settings_sanitize_image( mixed $raw ): int {
	$id = absint( is_scalar( $raw ) ? $raw : 0 );
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/**
 * عدد در بازه min/max؛ خالی/نامعتبر = پیش‌فرض. با step اعشاری (مثلا ۰٫۰۵ برای
 * فاصله خطوط) عدد اعشاری گرد‌شده به همان گام، وگرنه صحیح (رفتار قبلی همه
 * فیلدهای عددی). ارقام فارسی هم پذیرفته می‌شوند.
 *
 * @param array<string, mixed> $field
 */
function hodima_settings_sanitize_number( array $field, mixed $raw ): int|float {

	$step    = (float) ( $field['step'] ?? 1 );
	$decimal = $step > 0 && floor( $step ) !== $step;
	$raw     = is_string( $raw ) ? strtr( trim( $raw ), [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.', '/' => '.' ] ) : $raw;

	if ( ! is_numeric( $raw ) ) {
		return $decimal ? (float) $field['default'] : (int) $field['default'];
	}

	if ( ! $decimal ) {
		return min( (int) ( $field['max'] ?? PHP_INT_MAX ), max( (int) ( $field['min'] ?? 0 ), (int) $raw ) );
	}

	$value = min( (float) ( $field['max'] ?? PHP_FLOAT_MAX ), max( (float) ( $field['min'] ?? 0 ), (float) $raw ) );

	return round( round( $value / $step ) * $step, 4 );
}

/** عدد برای نمایش در فیلد و CSS: «1.80» → «1.8»، «16.0» → «16». */
function hodima_number_text( int|float $value ): string {
	return rtrim( rtrim( number_format( (float) $value, 4, '.', '' ), '0' ), '.' );
}

/** فایل فونت (woff2/woff) از کتابخانه رسانه؛ هر چیز دیگر = ۰. */
function hodima_settings_sanitize_font( mixed $raw ): int {
	$id = absint( is_scalar( $raw ) ? $raw : 0 );
	return ( $id && hodima_is_font_attachment( $id ) ) ? $id : 0;
}

function hodima_settings_sanitize_url( string $key, mixed $raw ): string {

	$raw = is_string( $raw ) ? trim( $raw ) : '';
	if ( '' === $raw ) {
		return '';
	}

	$url = esc_url_raw( $raw, [ 'https', 'http' ] );
	if ( '' === $url || ! wp_http_validate_url( $url ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, "invalid_{$key}", sprintf( 'آدرس وارد شده برای «%s» معتبر نیست و ذخیره نشد.', hodima_settings_field_label( $key ) ) );
		return '';
	}

	return $url;
}

/**
 * فهرست آدرس (هر خط یکی): آدرس کامل http(s) یا مسیر با / ابتدایی، و * فقط در انتها.
 * «hodhodli.com/cart» به آدرس کامل و «cart/» به مسیر تبدیل می‌شود؛ خط تکراری یا
 * نامعتبر حذف و به کاربر اطلاع داده می‌شود.
 */
function hodima_settings_sanitize_url_list( mixed $raw ): string {

	$lines   = preg_split( '/\R/u', is_string( $raw ) ? $raw : '' ) ?: [];
	$clean   = [];
	$invalid = [];

	foreach ( $lines as $line ) {

		// sanitize_text_field نه: کدهای %XX نامک فارسی کپی‌شده از نوار آدرس را پاک می‌کند
		$line = trim( wp_strip_all_tags( $line ) );
		if ( '' === $line ) {
			continue;
		}

		$wildcard = str_ends_with( $line, '*' );
		$line     = rtrim( $line, '*' );

		if ( ! preg_match( '#^https?://#i', $line ) ) {
			// دامنه بدون http یا مسیر بدون / ابتدایی
			$line = preg_match( '#^[^/\s]+\.[a-z]{2,}(/|$)#i', $line ) ? 'https://' . $line : '/' . ltrim( $line, '/' );
		}

		$url = esc_url_raw( $line, [ 'https', 'http' ] );

		if ( '' === $url || ( ! str_starts_with( $url, '/' ) && ! wp_parse_url( $url, PHP_URL_HOST ) ) ) {
			$invalid[] = $line;
			continue;
		}

		$clean[] = $url . ( $wildcard ? '*' : '' );
	}

	if ( $invalid ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, 'invalid_social_hide_urls', sprintf( 'این آدرس‌ها در فهرست صفحه‌های بدون شبکه‌های اجتماعی معتبر نبودند و ذخیره نشدند: %s', implode( '، ', $invalid ) ) );
	}

	return implode( "\n", array_values( array_unique( $clean ) ) );
}

/**
 * فهرست دامنه (هر خط یکی): «dl.example.com»، «https://dl.example.com/a.mp4» یا
 * «//cdn.example.com» → «https://dl.example.com» (فقط origin). تکراری حذف؛ نامعتبر
 * رد و به کاربر اطلاع داده می‌شود.
 */
function hodima_settings_sanitize_host_list( string $key, mixed $raw ): string {

	$lines   = preg_split( '/\R/u', is_string( $raw ) ? $raw : '' ) ?: [];
	$clean   = [];
	$invalid = [];

	foreach ( $lines as $line ) {

		$line = trim( wp_strip_all_tags( $line ) );
		if ( '' === $line ) {
			continue;
		}

		$url  = preg_match( '#^https?://#i', $line ) ? $line : 'https://' . ltrim( $line, '/' );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		if ( ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $host ) ) {
			$invalid[] = $line;
			continue;
		}

		$port    = wp_parse_url( $url, PHP_URL_PORT );
		$clean[] = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) . '://' . $host . ( $port ? ':' . (int) $port : '' );
	}

	if ( $invalid ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, "invalid_{$key}", sprintf( 'این دامنه‌ها در «%1$s» معتبر نبودند و ذخیره نشدند: %2$s', hodima_settings_field_label( $key ), implode( '، ', $invalid ) ) );
	}

	return implode( "\n", array_values( array_unique( $clean ) ) );
}

/** شماره تلفن: ارقام فارسی/عربی به لاتین؛ فقط ارقام و + ابتدایی. */
function hodima_settings_sanitize_phone( string $key, mixed $raw ): string {

	$raw = is_string( $raw ) ? $raw : '';
	$raw = strtr( $raw, [
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	] );

	$phone = (string) preg_replace( '/(?!^\+)[^\d]/', '', trim( $raw ) );

	if ( '' !== $phone && ! preg_match( '/^\+?\d{5,15}$/', $phone ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, "invalid_{$key}", sprintf( '«%s» معتبر نیست و ذخیره نشد.', hodima_settings_field_label( $key ) ) );
		return '';
	}

	return $phone;
}

function hodima_settings_sanitize_ga( mixed $raw ): string {

	$id = strtoupper( trim( is_string( $raw ) ? $raw : '' ) );

	if ( '' !== $id && ! preg_match( '/^G-[A-Z0-9]{4,16}$/', $id ) ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, 'invalid_ga', 'شناسه Google Analytics باید به شکل G-XXXXXXXXXX باشد و ذخیره نشد.' );
		return '';
	}

	return $id;
}

/* =========================================================================
 * ۳. رندر صفحه تنظیمات
 * ========================================================================= */

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {

	if ( 'appearance_page_' . HODIMA_SETTINGS_PAGE !== $hook ) {
		return;
	}

	wp_enqueue_media();

	hodima_enqueue_asset( 'hodima-theme-settings', 'inc/theme-settings/admin.css' );
	hodima_enqueue_asset( 'hodima-theme-settings', 'inc/theme-settings/admin.js', [ 'media-editor' ], [ 'in_footer' => true, 'strategy' => 'defer' ] );
} );

/**
 * صفحه «نمایش ← تنظیمات قالب هدیما»: منوی کناری گروه‌بندی‌شده + پنل هر تب.
 *
 * تاریخچه: اول همه بخش‌ها زیر هم بودند؛ بعد ده تب در یک ردیف افقی (بی‌ترتیب و
 * در صفحه کوچک‌تر بیرون‌زده)؛ حالا منوی کناری با چهار گروه (ظاهر سایت، صفحه‌ها،
 * ارتباط با مشتری، آمار) و در موبایل یک ردیف لغزنده.
 *   - بدون جاوااسکریپت: هر تب لینک ?tab=… است و سرور پنل همان تب را نشان می‌دهد؛
 *   - با جاوااسکریپت (admin.js): جابه‌جایی فوری، تب فعال بعد از «ذخیره» حفظ
 *     می‌شود (آدرس بازگشت فرم به‌روز می‌شود)، نشانه «ذخیره‌نشده» و Ctrl+S.
 * همه فیلدها در همان یک فرم و یک گزینه (hodima_theme_settings) می‌مانند؛ پنل‌های
 * پنهان هم ارسال می‌شوند، پس ذخیره یک تب مقدار تب‌های دیگر را پاک نمی‌کند.
 */
function hodima_settings_render_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = hodima_settings();
	$fields   = hodima_settings_fields();
	$sections = hodima_settings_sections();
	$panels   = hodima_settings_panels();
	$version  = (string) wp_get_theme( get_template() )->get( 'Version' );

	$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب تب
	$current   = isset( $sections[ $requested ] ) ? $requested : (string) array_key_first( $sections );
	?>
	<div class="wrap hodima-settings">
		<header class="hodima-settings__header">
			<span class="dashicons dashicons-admin-appearance hodima-settings__header-icon" aria-hidden="true"></span>
			<div class="hodima-settings__header-body">
				<?php if ( '' !== $version ) : ?>
					<span class="hodima-settings__badge">قالب هدیما · نسخه <bdi><?php echo esc_html( $version ); ?></bdi></span>
				<?php endif; ?>
				<h1>تنظیمات قالب هدیما</h1>
				<p>ظاهر سایت، صفحه‌ها و راه‌های ارتباطی. هر تغییر بعد از «ذخیره» روی همه صفحه‌های سایت اعمال می‌شود.</p>
			</div>
			<a class="hodima-settings__visit" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
				مشاهده سایت<span class="screen-reader-text"> (در زبانه جدید)</span>
			</a>
		</header>

		<hr class="wp-header-end">

		<?php
		/*
		 * بدون آرگومان: پیام «تنظیمات ذخیره شد» را options.php زیر نامک general
		 * ثبت می‌کند. نسخه قبلی فقط خطاهای همین گزینه را چاپ می‌کرد و بعد از
		 * ذخیره موفق هیچ پیامی دیده نمی‌شد.
		 */
		settings_errors();
		?>

		<div class="hodima-settings__layout">
			<nav class="hodima-settings__nav" aria-label="بخش‌های تنظیمات" data-hodima-nav>
				<?php foreach ( hodima_settings_nav_groups() as $nav_key => $nav_label ) : ?>
					<?php $nav_sections = array_filter( $sections, static fn( array $section ): bool => $section['nav'] === $nav_key ); ?>
					<?php if ( $nav_sections ) : ?>
						<div class="hodima-settings__nav-group">
							<p class="hodima-settings__nav-title" id="hodima-nav-<?php echo esc_attr( $nav_key ); ?>"><?php echo esc_html( $nav_label ); ?></p>
							<ul aria-labelledby="hodima-nav-<?php echo esc_attr( $nav_key ); ?>">
								<?php foreach ( $nav_sections as $section_key => $section ) : ?>
									<li>
										<a
											class="hodima-settings__tab"
											href="<?php echo esc_url( add_query_arg( [ 'page' => HODIMA_SETTINGS_PAGE, 'tab' => $section_key ], admin_url( 'themes.php' ) ) ); ?>"
											aria-controls="hodima-section-<?php echo esc_attr( $section_key ); ?>"
											data-tab="<?php echo esc_attr( $section_key ); ?>"
											<?php echo $section_key === $current ? 'aria-current="page"' : ''; ?>
										>
											<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>" aria-hidden="true"></span>
											<span class="hodima-settings__tab-label"><?php echo esc_html( $section['title'] ); ?></span>
											<span class="hodima-settings__tab-dirty" data-hodima-dirty hidden><span class="screen-reader-text">(تغییر ذخیره‌نشده)</span></span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" class="hodima-settings__form" novalidate data-hodima-form>
				<?php settings_fields( 'hodima_theme_settings_group' ); ?>

				<?php foreach ( $sections as $section_key => $section ) : ?>
					<section
						class="hodima-settings__section"
						id="hodima-section-<?php echo esc_attr( $section_key ); ?>"
						data-section="<?php echo esc_attr( $section_key ); ?>"
						aria-labelledby="hodima-section-<?php echo esc_attr( $section_key ); ?>-title"
						<?php echo $section_key === $current ? '' : 'hidden'; ?>
					>
						<header class="hodima-settings__section-head">
							<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>" aria-hidden="true"></span>
							<div>
								<h2 id="hodima-section-<?php echo esc_attr( $section_key ); ?>-title" tabindex="-1"><?php echo esc_html( $section['title'] ); ?></h2>
								<p><?php echo esc_html( $section['description'] ); ?></p>
							</div>
						</header>

						<div class="hodima-settings__panels">
							<?php
							foreach ( $panels as $panel_key => $panel ) {
								if ( $panel['section'] === $section_key ) {
									hodima_settings_render_panel(
										$panel_key,
										$panel,
										array_filter( $fields, static fn( array $field ): bool => ( $field['panel'] ?? '' ) === $panel_key ),
										$settings
									);
								}
							}

							// تب «صفحه اصلی»: فهرست بخش‌ها (گزینه جدا، همین فرم)
							if ( 'home' === $section_key && function_exists( 'hodima_home_admin_render' ) ) {
								hodima_home_admin_render();
							}
							?>
						</div>
					</section>
				<?php endforeach; ?>

				<footer class="hodima-settings__actions">
					<p class="hodima-settings__status" data-hodima-status aria-live="polite">
						<span class="hodima-settings__status-dirty">تغییرهای ذخیره‌نشده دارید</span>
						<span class="hodima-settings__status-hint">میان‌بر ذخیره: <kbd dir="ltr">Ctrl + S</kbd></span>
					</p>
					<?php submit_button( 'ذخیره تنظیمات', 'primary hodima-settings__save', 'submit', false ); ?>
				</footer>
			</form>
		</div>
	</div>
	<?php
}

/**
 * یک قاب: سرتیتر (آیکون، عنوان، توضیح)، فیلدهای آزاد در شبکه، بعد زیرگروه‌ها
 * (کارت‌های نماد اعتماد یا ردیف‌های شبکه‌ها).
 *
 * @param array<string, mixed>                $panel
 * @param array<string, array<string, mixed>> $fields فیلدهای همین قاب، به ترتیب تعریف
 * @param array<string, mixed>                $settings
 */
function hodima_settings_render_panel( string $key, array $panel, array $fields, array $settings ): void {

	$layout  = (string) ( $panel['layout'] ?? 'grid' );
	$groups  = hodima_settings_groups();
	$loose   = array_filter( $fields, static fn( array $field ): bool => ! isset( $field['group'] ) );
	$grouped = [];

	foreach ( $fields as $field_key => $field ) {
		if ( isset( $field['group'] ) ) {
			$grouped[ $field['group'] ][ $field_key ] = $field;
		}
	}

	$classes = 'hodima-panel hodima-panel--' . $layout . ' hodima-panel-' . $key . ( ! empty( $panel['half'] ) ? ' hodima-panel--half' : '' );
	$title   = 'hodima-panel-' . $key . '-title';
	?>
	<section class="<?php echo esc_attr( $classes ); ?>" aria-labelledby="<?php echo esc_attr( $title ); ?>">
		<?php hodima_settings_panel_head( $title, $panel['title'], $panel['help'] ?? '', $panel['icon'] ?? '' ); ?>

		<div class="hodima-panel__body">
			<?php if ( 'swatches' === $layout ) : ?>
				<?php hodima_settings_palette_preview( $settings ); ?>
			<?php elseif ( 'typepreview' === $layout && function_exists( 'hodima_typography_preview' ) ) : ?>
				<?php hodima_typography_preview(); ?>
			<?php elseif ( 'lookpreview' === $layout && function_exists( 'hodima_appearance_preview' ) ) : ?>
				<?php hodima_appearance_preview(); ?>
			<?php endif; ?>

			<?php if ( $loose ) : ?>
				<div class="hodima-panel__fields">
					<?php foreach ( $loose as $field_key => $field ) : ?>
						<?php hodima_settings_render_field( $field_key, $field, $settings[ $field_key ] ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $grouped ) : ?>
				<?php if ( ! empty( $panel['columns'] ) ) : ?>
					<div class="hodima-panel__columns" aria-hidden="true">
						<?php foreach ( $panel['columns'] as $column ) : ?>
							<span><?php echo esc_html( $column ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="hodima-panel__items">
					<?php foreach ( $grouped as $group => $group_fields ) : ?>
						<fieldset class="hodima-group" data-group="<?php echo esc_attr( $group ); ?>">
							<legend class="hodima-group__title"><?php echo esc_html( $groups[ $group ]['title'] ?? $group ); ?></legend>
							<div class="hodima-group__fields">
								<?php foreach ( $group_fields as $field_key => $field ) : ?>
									<?php hodima_settings_render_field( $field_key, $field, $settings[ $field_key ] ); ?>
								<?php endforeach; ?>
							</div>
						</fieldset>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/** سرتیتر قاب (مشترک با قاب «بخش‌های صفحه اصلی» در home-layout-admin.php). */
function hodima_settings_panel_head( string $id, string $title, string $help = '', string $icon = '', string $extra = '' ): void {
	?>
	<header class="hodima-panel__head">
		<?php if ( '' !== $icon ) : ?>
			<span class="hodima-panel__icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
		<?php endif; ?>
		<div class="hodima-panel__heading">
			<h3 class="hodima-panel__title" id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></h3>
			<?php if ( '' !== $help ) : ?>
				<p class="hodima-panel__help"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</div>
		<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput -- نشانه‌گذاری ثابت فراخواننده ?>
	</header>
	<?php
}

/**
 * نمونه کوچک سایت با پالت فعلی (تب «برند و رنگ‌ها»). admin.js با تغییر هر رنگ
 * متغیرهای همین ظرف را عوض می‌کند تا نتیجه پیش از ذخیره دیده شود.
 * @param array<string, mixed> $settings
 */
function hodima_settings_palette_preview( array $settings ): void {

	$vars = '';
	foreach ( [ 'primary', 'secondary', 'third', 'accent', 'accent_light' ] as $name ) {
		$value = sanitize_hex_color( (string) ( $settings[ 'color_' . $name ] ?? '' ) );
		if ( $value ) {
			$vars .= '--pv-' . str_replace( '_', '-', $name ) . ':' . $value . ';';
		}
	}
	?>
	<div class="hodima-palette-preview" style="<?php echo esc_attr( $vars ); ?>" data-hodima-palette-preview aria-hidden="true">
		<div class="hodima-palette-preview__header">
			<span class="hodima-palette-preview__logo"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<span class="hodima-palette-preview__menu"><span></span><span></span><span></span></span>
			<span class="hodima-palette-preview__support">پشتیبانی</span>
		</div>
		<div class="hodima-palette-preview__body">
			<span class="hodima-palette-preview__chip">دسته‌بندی</span>
			<strong class="hodima-palette-preview__title">عنوان نمونه محصول</strong>
			<span class="hodima-palette-preview__line"></span>
			<span class="hodima-palette-preview__buttons">
				<span class="hodima-palette-preview__button">افزودن به سبد</span>
				<span class="hodima-palette-preview__button hodima-palette-preview__button--accent">خرید عمده</span>
			</span>
		</div>
	</div>
	<?php
}

/**
 * @param array{panel:string, type:string, label:string, default:mixed, group?:string, help?:string, placeholder?:string, wide?:bool, preview?:string, options?:array<string, string>, min?:int, max?:int} $field
 */
function hodima_settings_render_field( string $key, array $field, mixed $value ): void {

	$id        = 'hodima-setting-' . $key;
	$name      = HODIMA_SETTINGS_OPTION . '[' . $key . ']';
	$help      = $field['help'] ?? '';
	$help_id   = $id . '-help';
	$described = '' !== $help ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : '';
	$type      = hodima_setting_type( $field );
	$wide      = ( $field['wide'] ?? $type->is_wide() ) && ! isset( $field['group'] ) ? ' hodima-field--wide' : '';
	?>
	<div class="hodima-field hodima-field--<?php echo esc_attr( $field['type'] . $wide ); ?>">
		<?php if ( Hodima_Setting_Type::Toggle === $type ) : ?>
			<?php /* کاشی کلید: کل کادر قابل کلیک؛ توضیح داخل همان کادر */ ?>
			<label class="hodima-toggle" for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" role="switch" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( (bool) $value ); ?><?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>>
				<span class="hodima-toggle__text">
					<span class="hodima-toggle__label"><?php echo esc_html( $field['label'] ); ?></span>
					<?php if ( '' !== $help ) : ?>
						<span class="hodima-field__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></span>
					<?php endif; ?>
				</span>
				<span class="hodima-toggle__track" aria-hidden="true"></span>
			</label>
			<?php $help = ''; // توضیح بالاتر چاپ شد ?>

		<?php elseif ( Hodima_Setting_Type::Color === $type ) : ?>
			<?php $default = strtolower( (string) $field['default'] ); ?>
			<label class="hodima-swatch" for="<?php echo esc_attr( $id ); ?>">
				<input type="color" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" data-hodima-color="<?php echo esc_attr( str_replace( [ 'color_', '_' ], [ '', '-' ], $key ) ); ?>"<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>>
				<span class="hodima-swatch__name"><?php echo esc_html( $field['label'] ); ?></span>
				<code class="hodima-swatch__value" dir="ltr" data-hodima-color-value><?php echo esc_html( (string) $value ); ?></code>
			</label>
			<?php if ( '' !== $help ) : ?>
				<p class="hodima-field__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
			<button type="button" class="hodima-swatch__reset" data-hodima-color-reset="<?php echo esc_attr( $default ); ?>" <?php echo strtolower( (string) $value ) === $default ? 'hidden' : ''; ?>>
				<span class="dashicons dashicons-undo" aria-hidden="true"></span>
				بازگشت به پیش‌فرض <code dir="ltr"><?php echo esc_html( $default ); ?></code>
			</button>
			<?php $help = ''; // توضیح بالاتر چاپ شد ?>

		<?php elseif ( Hodima_Setting_Type::Image === $type ) : ?>
			<?php
			$image_id = (int) $value;
			$header   = 'header' === ( $field['preview'] ?? '' );
			$inverted = $header && hodima_setting( 'logo_invert' );
			?>
			<span class="hodima-field__label" id="<?php echo esc_attr( $id ); ?>-label"><?php echo esc_html( $field['label'] ); ?></span>
			<div class="hodima-media" data-hodima-media>
				<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-hodima-media-input>
				<figure
					class="hodima-media__preview<?php echo $header ? ' hodima-media__preview--header' : ''; ?><?php echo $inverted ? ' is-inverted' : ''; ?><?php echo $image_id ? '' : ' is-empty'; ?>"
					data-hodima-media-preview
					<?php echo $header ? 'data-hodima-invert-source="hodima-setting-logo_invert"' : ''; ?>
				>
					<?php echo $image_id ? wp_get_attachment_image( $image_id, 'medium', false, [ 'alt' => '' ] ) : ''; ?>
					<span class="hodima-media__empty" aria-hidden="true"><span class="dashicons dashicons-format-image"></span><span class="hodima-media__empty-text">بدون تصویر</span></span>
				</figure>
				<div class="hodima-media__buttons">
					<button type="button" class="button" data-hodima-media-select aria-describedby="<?php echo esc_attr( $id ); ?>-label" data-title="<?php echo esc_attr( $field['label'] ); ?>"><?php echo $image_id ? 'تغییر تصویر' : 'انتخاب تصویر'; ?></button>
					<button type="button" class="button-link hodima-media__remove" data-hodima-media-remove aria-describedby="<?php echo esc_attr( $id ); ?>-label" <?php echo $image_id ? '' : 'hidden'; ?>>حذف</button>
				</div>
			</div>

		<?php elseif ( Hodima_Setting_Type::Font === $type ) : ?>
			<?php
			$font_id   = (int) $value;
			$font_name = $font_id ? wp_basename( (string) get_attached_file( $font_id ) ) : '';
			?>
			<span class="hodima-field__label screen-reader-text" id="<?php echo esc_attr( $id ); ?>-label"><?php echo esc_html( $field['label'] ); ?></span>
			<div class="hodima-media hodima-media--file" data-hodima-media data-hodima-media-kind="font">
				<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $font_id ); ?>" data-hodima-media-input>
				<span class="hodima-media__file<?php echo $font_id ? '' : ' is-empty'; ?>" data-hodima-media-preview>
					<span class="dashicons dashicons-media-default" aria-hidden="true"></span>
					<bdi dir="ltr" data-hodima-media-name><?php echo esc_html( $font_name ); ?></bdi>
					<span class="hodima-media__empty-text">بدون فایل</span>
				</span>
				<div class="hodima-media__buttons">
					<button type="button" class="button" data-hodima-media-select aria-describedby="<?php echo esc_attr( $id ); ?>-label" data-title="<?php echo esc_attr( $field['label'] ); ?>"><?php echo $font_id ? 'تغییر فایل' : 'انتخاب فایل'; ?></button>
					<button type="button" class="button-link hodima-media__remove" data-hodima-media-remove aria-describedby="<?php echo esc_attr( $id ); ?>-label" <?php echo $font_id ? '' : 'hidden'; ?>>حذف</button>
				</div>
			</div>

		<?php else : ?>
			<label class="hodima-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<?php if ( Hodima_Setting_Type::Textarea === $type ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4"<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php elseif ( Hodima_Setting_Type::Number === $type ) : ?>
				<input
					type="number"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( hodima_number_text( is_numeric( $value ) ? 0 + $value : 0 ) ); ?>"
					min="<?php echo esc_attr( (string) ( $field['min'] ?? 0 ) ); ?>"
					max="<?php echo esc_attr( (string) ( $field['max'] ?? '' ) ); ?>"
					<?php if ( isset( $field['step'] ) ) : ?>
						step="<?php echo esc_attr( (string) $field['step'] ); ?>"
						inputmode="decimal"
					<?php else : ?>
						inputmode="numeric"
					<?php endif; ?>
					<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
				>
			<?php elseif ( Hodima_Setting_Type::Select === $type ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>>
					<?php foreach ( (array) ( $field['options'] ?? [] ) as $option => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( (string) $value, (string) $option ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( $type->is_list() ) : ?>
				<textarea
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					rows="<?php echo Hodima_Setting_Type::HostList === $type ? '3' : '5'; // یک یا دو دامنه؛ فهرست آدرس بلندتر ?>"
					dir="ltr"
					spellcheck="false"
					autocomplete="off"
					<?php echo isset( $field['placeholder'] ) ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
					<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
				><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php else : ?>
				<input
					type="<?php echo esc_attr( $type->input_type() ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( (string) $value ); ?>"
					<?php echo isset( $field['placeholder'] ) ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
					<?php echo $type->is_ltr() ? 'dir="ltr"' : ''; ?>
					<?php echo Hodima_Setting_Type::Ga === $type ? 'pattern="G-[A-Za-z0-9]{4,16}" autocomplete="off" spellcheck="false"' : ''; ?>
					<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
				>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( '' !== $help ) : ?>
			<p class="hodima-field__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/* =========================================================================
 * ۴. توابع نمایشی برای قالب
 * ========================================================================= */

/** HTML لوگوی هدر: تصویر تنظیمات ← لوگوی سفارشی وردپرس ← نام سایت. */
function hodima_logo_html(): string {

	$site_name = get_bloginfo( 'name' );
	$logo_id   = (int) hodima_setting( 'logo_id' );

	if ( ! $logo_id ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
	}

	if ( $logo_id && wp_attachment_is_image( $logo_id ) ) {
		return (string) wp_get_attachment_image( $logo_id, 'medium', false, [
			'alt'           => $site_name,
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
			'class'         => 'header__logo-img',
		] );
	}

	return '<span class="header__logo-text">' . esc_html( $site_name ) . '</span>';
}

/**
 * کانال‌های تماس پیکربندی‌شده برای پنجره پشتیبانی، به ترتیب enum
 * Hodima_Contact_Channel (تماس، واتس‌اپ، روبیکا، تلگرام). کانال بدون مقدار رد می‌شود.
 *
 * @return list<Hodima_Contact_Link>
 */
function hodima_contact_channels(): array {

	$links = [];

	foreach ( Hodima_Contact_Channel::cases() as $channel ) {
		$value = (string) hodima_setting( $channel->setting() );
		if ( '' !== $value ) {
			$links[] = new Hodima_Contact_Link( $channel, $value );
		}
	}

	return $links;
}

/**
 * شبکه‌های اجتماعی پیکربندی‌شده، به ترتیب hodima_social_networks().
 *
 * @return list<array{key:string, label:string, url:string, icon_id:int}>
 */
function hodima_social_links(): array {

	$links = [];

	foreach ( hodima_social_networks() as $key => $label ) {
		$url = (string) hodima_setting( "social_{$key}_url" );
		if ( '' !== $url ) {
			$links[] = [
				'key'     => $key,
				'label'   => $label,
				'url'     => $url,
				'icon_id' => (int) hodima_setting( "social_{$key}_icon" ),
			];
		}
	}

	return $links;
}

/**
 * نمادهای اعتماد دارای تصویر، به ترتیب ۱ تا ۳ (نماد بدون تصویر رد می‌شود).
 *
 * @return list<array{image_id:int, url:string}>
 */
function hodima_trust_badges(): array {

	$badges = [];

	for ( $i = 1; $i <= HODIMA_TRUST_SLOTS; $i++ ) {
		$suffix   = 1 === $i ? '' : "_{$i}";
		$image_id = (int) hodima_setting( "trust_image_id{$suffix}" );

		if ( $image_id && wp_attachment_is_image( $image_id ) ) {
			$badges[] = [ 'image_id' => $image_id, 'url' => (string) hodima_setting( "trust_url{$suffix}" ) ];
		}
	}

	return $badges;
}

/**
 * مسیر یک آدرس برای مقایسه: بدون دامنه، پارامتر و پوشه نصب وردپرس، رمزگشایی‌شده
 * (نامک فارسی کپی‌شده از نوار آدرس کدگذاری شده است)، با / در ابتدا و انتها.
 */
function hodima_normalize_path( string $url ): string {

	$path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

	if ( '' !== $base && str_starts_with( $path, $base . '/' ) ) {
		$path = substr( $path, strlen( $base ) );
	}

	$path = trim( strtolower( $path ), '/' ); // strtolower در PHP 8 فقط ASCII را تغییر می‌دهد؛ نامک فارسی سالم می‌ماند

	return '' === $path ? '/' : '/' . $path . '/';
}

/**
 * آیا نوار شبکه‌های اجتماعی در صفحه فعلی نمایش داده شود؟
 * صفحه‌های فهرست «social_hide_urls» مستثنا هستند؛ خط با * در انتها همه زیرصفحه‌ها را هم می‌گیرد.
 */
function hodima_socials_visible_here(): bool {

	$rules = trim( (string) hodima_setting( 'social_hide_urls' ) );
	$show  = true;

	if ( '' !== $rules ) {
		$current = hodima_normalize_path( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- فقط مقایسه مسیر

		foreach ( preg_split( '/\R/u', $rules ) ?: [] as $rule ) {

			$rule = trim( $rule );
			if ( '' === $rule ) {
				continue;
			}

			$prefix = str_ends_with( $rule, '*' );
			$path   = hodima_normalize_path( rtrim( $rule, '*' ) );

			if ( $prefix ? str_starts_with( $current, $path ) : $current === $path ) {
				$show = false;
				break;
			}
		}
	}

	/** برای استثنای برنامه‌نویسی (مثلا بر اساس نوع صفحه). */
	return (bool) apply_filters( 'hodima_show_socials', $show );
}

/* =========================================================================
 * ۵. پالت رنگ برند (تب «برند و رنگ‌ها»)
 * ------------------------------------------------------------------------
 * فقط رنگ‌هایی که با پیش‌فرض فرق دارند به‌صورت :root{…} بعد از tokens.css
 * چاپ می‌شوند (سایت با پالت پیش‌فرض هیچ CSS اضافه‌ای ندارد). مشتق‌ها هم
 * دوباره ساخته می‌شوند: «R, G, B» برای رنگ‌های نیمه‌شفاف، نسخه تیره رنگ
 * اصلی (hover؛ ×۰٫۷۳ همان نسبت #1b244d به #25316a) و نقطه میانی گرادیان.
 * ========================================================================= */

/**
 * «#rrggbb» → [r, g, b]
 *
 * @return array{int, int, int}
 */
function hodima_hex_rgb( string $hex ): array {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return array_map( 'hexdec', str_split( substr( str_pad( $hex, 6, '0' ), 0, 6 ), 2 ) );
}

/**
 * [r, g, b] → «#rrggbb»
 *
 * @param array<int, int|float> $rgb
 */
function hodima_rgb_hex( array $rgb ): string {
	return '#' . implode( '', array_map( static fn( $c ): string => str_pad( dechex( max( 0, min( 255, (int) round( $c ) ) ) ), 2, '0', STR_PAD_LEFT ), $rgb ) );
}

/** CSS پالت تنظیم‌شده، یا رشته خالی برای پالت پیش‌فرض. */
function hodima_palette_css(): string {

	$fields = hodima_settings_fields();
	$vars   = [];
	$map    = [
		'color_primary'      => 'primary',
		'color_secondary'    => 'secondary',
		'color_third'        => 'third',
		'color_accent'       => 'accent',
		'color_accent_light' => 'accent-light',
	];
	$changed = [];

	foreach ( $map as $key => $token ) {
		$value = strtolower( (string) hodima_setting( $key ) );
		if ( $value !== strtolower( (string) $fields[ $key ]['default'] ) && sanitize_hex_color( $value ) ) {
			$changed[ $token ]            = $value;
			$vars[ "--hodima-{$token}" ]     = $value;
			$vars[ "--hodima-{$token}-rgb" ] = implode( ', ', hodima_hex_rgb( $value ) );
		}
	}

	if ( ! $changed ) {
		return '';
	}

	$primary   = hodima_hex_rgb( (string) hodima_setting( 'color_primary' ) );
	$secondary = hodima_hex_rgb( (string) hodima_setting( 'color_secondary' ) );

	if ( isset( $changed['primary'] ) ) {
		$vars['--hodima-primary-dark'] = hodima_rgb_hex( array_map( static fn( $c ) => $c * 0.73, $primary ) );
	}

	if ( isset( $changed['primary'] ) || isset( $changed['secondary'] ) ) {
		$vars['--hodima-gradient-mid']  = hodima_rgb_hex( array_map( static fn( $a, $b ) => $a + ( $b - $a ) * 0.4, $primary, $secondary ) );
		$vars['--hodima-gradient-deep'] = hodima_rgb_hex( array_map( static fn( $a, $b ) => $a + ( $b - $a ) * 0.26, $primary, $secondary ) );
	}

	$css = '';
	foreach ( $vars as $name => $value ) {
		$css .= $name . ':' . $value . ';';
	}

	return ':root{' . $css . '}';
}

/** بعد از tokens.css در سایت و پیشخوان (همان handle). */
function hodima_print_palette(): void {
	$css = hodima_palette_css();
	if ( '' !== $css && wp_style_is( 'hodima-tokens', 'enqueued' ) ) {
		wp_add_inline_style( 'hodima-tokens', $css );
	}
}
add_action( 'wp_enqueue_scripts', 'hodima_print_palette', 21 );
add_action( 'admin_enqueue_scripts', 'hodima_print_palette', 2 );

/* =========================================================================
 * ۶. Google Analytics 4
 * ========================================================================= */

add_action( 'wp_enqueue_scripts', 'hodima_enqueue_google_analytics', 1 );

function hodima_enqueue_google_analytics(): void {

	$ga_id = (string) hodima_setting( 'ga_id' );

	if ( '' === $ga_id || ! preg_match( '/^G-[A-Z0-9]{4,16}$/', $ga_id ) ) {
		return;
	}

	if ( hodima_setting( 'ga_production' ) && 'production' !== wp_get_environment_type() ) {
		return;
	}

	if ( hodima_setting( 'ga_skip_editors' ) && current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_enqueue_script(
		'hodima-gtag',
		'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga_id ),
		[],
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- پارامتر نسخه به آدرس گوگل اضافه نشود
		[ 'in_footer' => false, 'strategy' => 'async' ]
	);

	wp_add_inline_script(
		'hodima-gtag',
		sprintf(
			'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",%s);',
			wp_json_encode( $ga_id )
		),
		'before'
	);
}

add_filter( 'wp_resource_hints', static function ( array $urls, string $relation ): array {
	if ( 'preconnect' === $relation && '' !== (string) hodima_setting( 'ga_id' ) ) {
		$urls[] = [ 'href' => 'https://www.googletagmanager.com', 'crossorigin' => 'anonymous' ];
	}
	return $urls;
}, 10, 2 );

/* =========================================================================
 * ۷. باطل کردن کش‌هایی که از این تنظیمات استفاده می‌کنند
 * ========================================================================= */

add_action( 'update_option_' . HODIMA_SETTINGS_OPTION, static function (): void {

	// llms.txt شماره‌های تماس را در خود دارد
	foreach ( [ 'fa', 'en' ] as $lang ) {
		foreach ( [ 20, 500 ] as $limit ) {
			delete_transient( "hodima_llms_txt_cache_{$lang}_{$limit}_siloed" );
		}
	}

	// هدر و فوتر در همه صفحات کش‌شده تغییر کرده‌اند
	do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API خود لایت‌اسپید
} );
