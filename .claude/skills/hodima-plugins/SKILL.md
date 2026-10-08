---
name: hodima-plugins
description: قانون‌های کد افزونه‌های هدیما (پوشه plugins/: Core، SEO، Commerce، Media) — اسکیما JSON-LD و گراف واحد، ماژول‌ها، سیستم رسانه، آدرس تمیز، جدول داینامیک، سئوباکس، انتقال منطق از قالب، منوها و طراحی پیشخوان، واقعیت‌های فروشگاه (تومان، کیلوگرم). پیش از خواندن یا تغییر هر فایل زیر plugins/ یا هر کار اسکیما/پیشخوان به کار ببر.
---

# قانون‌های افزونه‌های هدیما (`plugins/`)

تاریخچه و جزئیات هر بخش: `HODIMA-AUDIT.md` با شماره بخش داده‌شده.

## چهار افزونه
- `plugins/hodima-core/`: پیش‌نیاز بقیه: توابع مشترک (`includes/helpers.php`: IP، محدودیت نرخ، ربات، `hodima_get_canonical_url()`، محتوای محافظت‌شده، `hodima_is_noindex()` — تشخیص واحد noindex)، مدیریت ماژول‌ها (`includes/modules.php`)، پنل «هدیما» (`includes/admin-hub.php`)، **گراف واحد اسکیما** (`includes/schema-graph.php`)، سیاست کش لایت‌اسپید (`includes/litespeed.php`، `hodima_core_litespeed_active()`)، بستن XML-RPC (`includes/hardening.php`)، فونت پیشخوان (`includes/admin-font.php`)، کادر فرم دسته‌ها
- `plugins/hodima-seo/`: سئوباکس، اسکیما (`schema/`)، سایت‌مپ، robots.txt، ریدایرکت، آدرس تمیز، خوشه موضوعی (`core/topiccluster/`)، IndexNow/AEO/llms.txt، Google Indexing
- `plugins/hodima-commerce/`: جلالی، شهرها، جستجوی زنده، فرم تلفن، حداقل سفارش، … (بیشتر به ووکامرس نیاز دارد)
- `plugins/hodima-media/`: استوری، ویدیو، اسلایدر، سیستم رسانه، اعلان، جدول داینامیک

## اسکیما (JSON-LD) — قانون
همه اسکیماها با `hodima_schema_add( $payload, 'منبع' )` به گراف واحد می‌روند و یک بار در `wp_footer` چاپ می‌شوند. **هرگز** `<script type="application/ld+json">` مستقیم echo نکن. نود صفحه (`{canonical}#webpage`) را فقط `schema/homepage-schema.php` می‌سازد؛ بقیه با فیلتر `hodima_schema_webpage_node` غنی‌اش می‌کنند. پایه همه `@id`ها `hodima_get_canonical_url()`. جزئیات: `HODIMA-AUDIT.md` بخش ۱۴.

## قانون‌ها
- ماژول‌ها: هر افزونه در `hodima_{seo|commerce|media}_modules()` ماژول‌هایش را تعریف می‌کند؛ کاربر در «هدیما ← ماژول‌های …» روشن/خاموش می‌کند. فراخوانی بین ماژول‌ها همیشه با `function_exists`/`class_exists` گارد شود.
- **سیستم رسانه** (`plugins/hodima-media/media-system/`): توابع با پیشوند `hodima_media_*`؛ نام‌های قدیمی `hook_*` فقط در `media-legacy.php` برای سازگاری‌اند — کد جدید آن‌ها را صدا نزند (اول نام جدید، با فالبک نام قدیمی). VideoObject فقط با `hodima_media_video_node()` ساخته شود. کلیدهای متا (`_hook_*` نوشته، `hook_*` ترم) و نام شورت‌کدها عوض نشوند.
- **آدرس تمیز** (`plugins/hodima-seo/core/router/`): توابع `hodima_router_*`؛ نام‌های `arian_*` فقط در `legacy.php`. ماژول‌های دیگر آدرس → شیء را فقط با `hodima_router_resolve_url()` و حذف پایه را با `hodima_router_clean_url()` (با `function_exists`؛ بدون روتر آدرس را دست نزنید). روتر فقط آدرس‌هایی را حل می‌کند که وردپرس نشناخته؛ هر شیء یک آدرس اصلی (`get_permalink`/`get_term_link`) دارد. جزئیات: `HODIMA-AUDIT.md` بخش ۴۲.
- **جدول داینامیک** (`plugins/hodima-media/inc/hodima-table/`): داده جدول را فقط با `Hodima_Dynamic_Table::get_table()` بخوان (یکدست‌شده با `normalize_table()`؛ ستون خالی حذف)؛ مشخصات اسکیما فقط از `get_property_values()` (فقط جدول دوستونه). نمایش و اسکیما از `get_public_table()`: در محصول، ردیف‌های woo-table (جنس، سایز، وزن، تعداد، رنگ، تولید، بروزرسانی — `woo_table_names()` + `Hodima_Product_Specs_Table::property_names()`) را hodima-table نمی‌سازد (بخش ۴۷). کادر کل جدول را در فیلد `hodima_table_json` می‌فرستد؛ نبودن فیلد = دست نزدن به داده. جزئیات: `HODIMA-AUDIT.md` بخش ۴۶.
- **سئوباکس** (`plugins/hodima-seo/core/seobox/`): متاتگ robots فقط از فیلتر `wp_robots` (هرگز متاتگ جدا)؛ وضعیت ایندکس هر شیء فقط با `seobox_object_robots()` (= `_seobox_robots` + `hodima_is_noindex()`)؛ صفحه فعلی و جای متای آن با `seobox_get_query_context()` (برگه وبلاگ/فروشگاه/صفحه اصلی هم). جزئیات: `HODIMA-AUDIT.md` بخش ۳۹.
- **قالب فقط نمایش است.** منطق جدید (اسکیما، داده، امنیت، پیشخوان، کش) را در افزونه بنویس. انتقال از قالب به افزونه: نام تابع/کلاس **جدید** در افزونه (قالب قدیمی همان را بدون گارد دارد → «Cannot redeclare»)، و تا قالب قدیمی فعال است کد افزونه کاری نکند: `function_exists( 'hodima_theme_has_legacy_logic' ) && hodima_theme_has_legacy_logic()` (Core؛ مرز نسخه را هنگام انتقال بعدی بالا ببر) + کمترین نسخه افزونه‌ها در اعلان `functions.php` قالب. جزئیات: `HODIMA-AUDIT.md` بخش ۵۲.
- افزونه‌ها `Requires Plugins: hodima-core` دارند ولی کد باید بدون Core هم Fatal ندهد (الگوی فالبک موجود را دنبال کن).
- منوها: پنل «ابزارهای هدیما» (`hodima-hub`)؛ «اسکیما» (۱۳ صفحه، فقط یکی در منو — `schema/admin/init.php`)، ریدایرکت‌ها، خوشه‌بندی، اسلایدر و نوتیفیکیشن‌ها زیر آن‌اند. زیرمنوی جدید با `hodima_admin_menu_parent()` (بدون Core رشته خالی = منوی سطح اول).
- **طراحی پیشخوان:** هر صفحه افزونه `<div class="wrap hd-wrap">` + `hodima_admin_header([...])` (هدر، تب‌ها زیر هدر، اعلان‌ها بعد از آن) و اجزای `hd-*` در `plugins/hodima-core/assets/admin-ui.css` (کارت، فیلد، `hd-switch`، `hd-actions`...). فوتر خودکار است. پیام نتیجه: `hodima_admin_notice()` یا بعد از ریدایرکت/رفرش `hodima_admin_flash()` — هرگز کادر پیام اختصاصی (دکمه X وردپرس فقط روی `.notice.is-dismissible` کار می‌کند). CSS هر صفحه فقط روی همان صفحه و زیر کلاس ظرفش؛ هرگز `:root` یا قانون سراسری وردپرس (`.form-table`، `.widefat`). `font-family` فقط `inherit`؛ آیکون فقط Dashicons؛ ایموجی و لینک خارجی ممنوع. عرض ۹۵٪ دسکتاپ.

## واقعیت‌های فروشگاه
- **قیمت محصول برای بیرون** (Offer اسکیما، متاتگ‌های قیمت، پیش‌نمایش لینک، AEO) فقط با `hodima_seo_product_feed_price()` (`plugins/hodima-seo/inc/product-price.php`؛ گزینه «قیمت تک / حداقل سفارش» تنظیمات قالب؛ بخش ۷۲)، نه مستقیم `get_price()`. وب‌سرویس ترب/ایمالز (REST یا admin-ajax با این نام‌ها) را همان فایل فیلتر می‌کند (بخش ۷۳)؛ صفحه HTML هرگز.
- اسکیما: مرحله‌های ۲۷.۴ انجام شد (بخش ۲۸). واقعیت‌های فروشگاه: واحد پول سایت و ووکامرس **تومان** (اسکیما برای گوگل ×۱۰ و IRR؛ مبلغ‌های پنل به تومان)، ارسال با مشتری و متغیر، بارکد به‌زودی در فیلد GTIN ووکامرس. سایت فارسی است و **وزن کیلوگرم** (هرگز پوند/اونس در اسکیما یا متن؛ بخش ۴۹). Rank Math از سایت حذف شده و **همه پشتیبانی‌اش از کد برداشته شد** (بخش ۳۰) — دوباره اضافه نکن؛ دسته اصلی نوشته/محصول در متای `_hodima_primary_{taxonomy}` است.
