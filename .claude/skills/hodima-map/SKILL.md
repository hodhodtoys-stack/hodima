---
name: hodima-map
description: نقشه کدبیس هدیما — ساختار قالب و ۴ افزونه، ترتیب بارگذاری، و همه ارتباط‌های بین آن‌ها (کدام تابع/کلاس/فیلتر/شورت‌کد/گزینه از کدام بسته در کدام بسته استفاده می‌شود). پیش از هر کاری که بیش از یک بسته را لمس می‌کند، پیش از انتقال منطق بین قالب و افزونه، یا وقتی می‌خواهی بدانی «این تابع کجا تعریف شده و چه کسی صدایش می‌زند» به کار ببر تا لازم نباشد کل کد را از اول بخوانی.
---

# نقشه کدبیس هدیما

این نقشه جای «خواندن کل مخزن» را می‌گیرد؛ قانون‌های کدنویسی هر بخش در `hodima-theme` و `hodima-plugins` است و اینجا فقط **ساختار و ارتباط‌ها**. نقشه از روی کد ساخته شده (نسخه‌ها: قالب 3.0.4، Core 1.2.1، SEO 2.1.1، Commerce 1.2.2، Media 1.8.3).

**به‌روز نگه داشتن:** `python3 tools/code-map.py` همه ارتباط‌های بین‌بسته‌ای فعلی را از کد درمی‌آورد (تابع، کلاس، فیلتر/اکشن، گزینه، شورت‌کد). اگر کاری ارتباط تازه‌ای ساخت یا حذف کرد (خروجی با جدول‌های پایین فرق دارد)، همین فایل را در همان commit اصلاح کن.

## ۱. پنج بسته و ترتیب بارگذاری
وردپرس اول افزونه‌ها (به ترتیب الفبا: commerce، core، media، seo)، بعد قالب را لود می‌کند.

| بسته | فایل اصلی | چه وقت کدش لود می‌شود |
|---|---|---|
| **Core** `plugins/hodima-core/` | `hodima-core.php` | **بلافاصله** هنگام include (نه در هوک) تا توابعش مستقل از ترتیب الفبا برای بقیه آماده باشد: `includes/helpers.php`، `modules.php`، `schema-graph.php`، `updates.php`، `litespeed.php`، `hardening.php`، `admin-font.php`؛ فقط پیشخوان: `admin-hub.php`، `admin-ui.php`، `admin-term-box.php`. اگر قالب نسخه ۱ فعال است (`hodima_legacy_theme_active()`) فقط updates + اعلان. |
| **SEO** `plugins/hodima-seo/` | `hodima-seo.php` | `plugins_loaded`: بدون Core فالبک‌ها (`inc/schema-fallback.php`، `inc/admin-ui-fallback.php`)؛ همیشه `inc/product-price.php`، `inc/page-intro.php`؛ بعد ماژول‌های روشن از `hodima_seo_modules()` |
| **Commerce** `plugins/hodima-commerce/` | `hodima-commerce.php` | `plugins_loaded`: ماژول‌های `hodima_commerce_modules()`؛ `requires_wc` فقط با ووکامرس |
| **Media** `plugins/hodima-media/` | `hodima-media.php` | `plugins_loaded`: فالبک‌ها، همیشه `inc/video-object.php`، بعد ماژول‌های `hodima_media_modules()` |
| **قالب** `hodima/` | `functions.php` → `hodima_load_theme_files()` | ترتیب ثابت: `inc/classes/*` ← `home/logic.php`، `home/legacy.php` ← `inc/helpers.php` ← `media-sections` ← `theme-settings` ← `typography` ← `appearance` ← `extras` ← `home-layout` ← `blog` ← `breadcrumb` ← `setup` ← `enqueue` ← `header` ← `footer` ← `inc/performance/*` (الفبا، `00-litespeed.php` اول) ← اگر ووکامرس: `inc/woocommerce/*`. ترتیب = ترتیب ثبت هوک‌ها و CSSها؛ عوضش نکن. |

نتیجه مهم: **توابع قالب هنگام لود افزونه‌ها هنوز تعریف نشده‌اند** — افزونه فقط داخل هوک (بعد از `after_setup_theme`) و با `function_exists` تابع قالب را صدا بزند. توابع Core برای قالب همیشه آماده‌اند (ولی قالب بدون Core هم باید کار کند).

### ماژول‌ها (روشن/خاموش در «هدیما ← ماژول‌ها»؛ تعریف در `includes/modules.php` Core)
- **SEO:** `google-indexing` (`components/google-indexing-api/`)، `indexnow` (`components/indexnow-sync/` — AEO و llms.txt هم اینجاست)، `schema` (`schema/*.php` + پیشخوان `schema/admin/`)، `podcast`، `sitemap`، `manual-links` (`inc/manual_related_link/`)، `router` (`core/router/`)، `redirects`، `seobox`، `discover`، `robots` (`core/seobox/robots-txt.php`)، `topic-cluster` (`core/topiccluster/`)، `cat-blog`
- **Commerce:** `phone`، `search`، `product-fields` (`inc/woocommerce/product-fields.php`)، `min-order` (`cart.php`)، `specs-table` (`inc/hodima-woo-table/`)، `store-optimizer`، `catalog-sorting`، `jalali` (`core/time-jalali/`)
- **Media:** `expandable-boxes`، `slider`، `stories`، `notifications`، `video` (`components/video-watch/`)، `media-system`، `dynamic-table` (`inc/hodima-table/`)

ماژول خاموش اصلا لود نمی‌شود ← هر فراخوانی بین ماژول‌ها/بسته‌ها با گارد `function_exists`/`class_exists`/`shortcode_exists`.

## ۲. توابعی که چند بسته عمدا با گارد تعریف می‌کنند
`if ( ! function_exists() )` — اولی که لود شود برنده است؛ امضا باید یکی بماند:
- `hodima_schema_add()`: Core (اصلی، گراف واحد)، فالبک در SEO و Media (`inc/schema-fallback.php`) و قالب (`inc/helpers.php`).
- `hodima_wc_active()`: Core و قالب (`inc/helpers.php`).
- `hodima_admin_header/tabs/notice/flash/icon/menu_parent/page_open/page_close()`: Core (`includes/admin-ui.php`) و فالبک در SEO/Commerce/Media (`inc/admin-ui-fallback.php`).

## ۳. قالب ← افزونه‌ها (قالب از افزونه می‌خواند؛ همه با گارد)
| از | چه | کجای قالب |
|---|---|---|
| Core | `hodima_core_litespeed_active()` | `inc/performance/00-litespeed.php` (قالب `hodima_litespeed_active()` خودش را روی آن می‌سازد) |
| Media | `hodima_media_is_enabled()`، `hodima_media_get_data()`، `hodima_media_player_url()` | `page.php`، `inc/enqueue.php`، `inc/performance/ui-performance.php` |
| Media | `hodima_media_videos_page_query()` | `template-page-videos.php` |
| Media | `hodima_media_external_hosts()` | `inc/theme-settings/theme-settings.php` |
| Media | شورت‌کدهای `hook_intro/video/voice/faq` فقط از راه `hodima_theme_media_html()` | `inc/media-sections.php`، `template-parts/media/*` |
| Media | شورت‌کدهای `expand_*` (expandable-boxes) | `single-post.php`، `woocommerce/content-single-product.php`، `taxonomy-product_cat.php` |
| Media ← قالب | فیلتر `hodima_media_displayed_taxonomies` (قالب اعلام می‌کند فقط `product_cat` بخش رسانه دارد) | `inc/media-sections.php` |
| Commerce | `hodima_product_stock_location()`، `hodima_product_stock_texts()`، `hodima_product_schema_availability()` (`inc/woocommerce/product-fields.php`)، کلاس `Hodima_Product_Specs_Table` (`inc/hodima-woo-table/woo-table.php`) | `woocommerce/content-single-product.php`، `inc/woocommerce/product-page.php` |
| Commerce | شورت‌کد `hodima_phone_form` (`components/phone/`) و `woo_live_search` (`components/search/`) | `footer.php`، `header.php` |
| SEO | `hodima_breadcrumb_items()` (`schema/breadcrumb-schema.php`؛ مسیر راهنما = اسکیما) | `inc/breadcrumb.php` |
| SEO | کلاس `Hodima_TC_Helper` (خوشه موضوعی، `core/topiccluster/helper.php`) | `inc/blog.php` (مقالات مرتبط) |
| SEO ← قالب | فیلترهای وردپرسی `the_content` و `woocommerce_short_description` که قالب اجرا می‌کند و خوشه/لینک دستی SEO به آن وصل‌اند | `single-post.php`، `content-single-product.php` |

اعلان «افزونه قدیمی/غایب»: `$required` در `hodima/functions.php` (کمترین نسخه هر افزونه).

## ۴. افزونه‌ها ← قالب (افزونه از قالب می‌خواند؛ همه با `function_exists`/`defined`)
| بسته | چه از قالب | کجا |
|---|---|---|
| Core | `function_exists( 'hodima_settings' )` = «قالب هدیما فعال است» ← لینک «تنظیمات قالب هدیما» در پنل و فوتر | `includes/admin-hub.php`، `admin-ui.php` |
| Core و قالب | فیلتر مشترک `hodima_litespeed_active` (هر دو اجرا می‌کنند؛ نام تابع Core عمدا `hodima_core_litespeed_active` است تا با قالب قدیمی تداخل نکند) | `includes/litespeed.php`، `inc/performance/00-litespeed.php` |
| SEO | `hodima_setting( 'phone' / 'phone_2' / 'logo_id' / product_offer_price / product_torob_price )` | AEO (`class-hodima-aeo-generator.php`)، `core/seobox/admin-ui.php`، `inc/product-price.php` |
| SEO | ثابت `hodima_VERSION` (حروف کوچک؛ نامش عوض نشود) | `google-indexing-api/modules/etag-handler.php` |
| SEO | `hodima_litespeed_active()` | `class-hodima-bot-shield.php`، `google-indexing-api/core/helper.php` |
| Media | (فقط نام هم‌خانواده `hodima_table_exists` در قالب؛ hodima-table عمدا پیشوند دیگری دارد) | `inc/hodima-table/hodima-table.php` |

گزینه تنظیمات قالب: `hodima_theme_settings` (ثابت `HODIMA_SETTINGS_OPTION`) — افزونه مستقیم `get_option` نکند، `hodima_setting()` با گارد.
مرز انتقال منطق از قالب: `hodima_theme_has_legacy_logic()` (Core؛ قالب < 2.3.0) در SEO (`cat-blog`، `breadcrumb-schema`، `collection-lists-schema`، `front-page-extra-schema`)، Commerce (`catalog-sorting`، `store-optimizer`)، Media (`video-watch/videos-page.php`).

## ۵. افزونه ↔ افزونه
**همه ← Core:** `hodima_get_client_ip()`، `hodima_rate_limit_key()`، `hodima_is_search_bot()`، `hodima_ip_in_cidr()`، `hodima_get_canonical_url()`، `hodima_is_noindex()`، `hodima_post_content_is_visible()`، `hodima_page_has_schema_identity()`، `hodima_get_archive_itemlist_elements()`، `hodima_schema_has()`، `hodima_video_player_url()`، `hodima_core_font_url()`، `hodima_core_litespeed_active()`، `hodima_legacy_theme_active()`/`_notice()`، کلاس ماژول‌ها. (همه در `includes/helpers.php` جز آخرها: `admin-font.php`، `litespeed.php`، `modules.php`.)

**SEO ← Media** (اسکیما/سایت‌مپ داده رسانه را می‌خوانند): `hodima_media_get_data()`، `hodima_media_video_node()`، `hodima_media_video()`، `hodima_media_extra_videos()`/`_extra_video_nodes()`، `hodima_media_is_displayed()`، `hodima_media_part_shown()`، `hodima_media_schema_page_shows_media()`، `hodima_media_post_types()`، `hodima_media_is_direct_video()`، `hodima_media_iso_date()`/`_stable_date()`/`_duration_iso()`، `hodima_media_parse_entities()`، `hodima_media_has_legacy_data()`؛ با فالبک نام‌های قدیمی `hook_*` (`media-legacy.php`). فایل‌ها: `schema/sitemap-core.php`، `product-schema-pro.php`، `category-schema-pro.php`، `front-page-extra-schema.php`، `blog-schema.php`، `podcast-feed-core.php`، `schema-helpers.php`، `core/discover/discover-init.php`، `core/seobox/front-output.php`، `inc/page-intro.php`.

**Media ← SEO:** `hodima_seo_schema_organization_node()` (`media-schema.php`، `media-video.php`)، `hodima_schema_webpage_emitted()` (`video-watch/videos-page.php`)؛ و Media به فیلترهای SEO وصل است: `hodima_breadcrumb_items`، `hodima_schema_webpage_node` (`video-watch/vid-w-schema.php`)، `hodima_product_additional_properties` (`hodima-table/hodima-table.php` ← مشخصات محصول در اسکیما).

**SEO و Media ← Commerce:** کلاس `Hodima_Product_Specs_Table` (ردیف‌های جدول مشخصات؛ `product-schema-pro.php`، `hodima-table.php`)؛ SEO: `hodima_product_schema_availability()`، `hodima_product_stock_location()`.

**شورت‌کدها:** SEO `[hook_intro]` (Media) را در `inc/page-intro.php` و `[woo_specs_table]` (Commerce) را در صفحه جدول‌های پیشخوان اسکیما (`schema/admin/views/view-tables.php`) به کار می‌برد.

**Commerce ← بقیه:** فقط Core؛ Commerce از SEO و Media چیزی نمی‌خواند.

## ۶. داده و گزینه‌های مشترک (عوض نکن — قانون ۵)
- `hodima_theme_settings`: تنظیمات قالب؛ خواننده‌ها بالا.
- `arian_router_flushed`، `rewrite_rules`: روتر SEO؛ پنل Core و فعال‌سازی SEO/Media بازسازی آدرس‌ها را درخواست می‌کنند.
- `hodima_schema_graph_debug`: Core (گراف) و صفحه «پاک‌کننده» اسکیما در SEO.
- متاهای رسانه `_hook_*` / `hook_*` (Media)؛ Discover `_hook_discover_*` (SEO)؛ سئوباکس `_seobox_*`؛ دسته اصلی `_hodima_primary_{taxonomy}`.

## ۷. انتشار و به‌روزرسانی خودکار
`plugins/hodima-core/includes/updates.php` (Plugin Update Checker) هر ۱۲ ساعت `release/<slug>.json` را از `raw.githubusercontent.com/hodhodtoys-stack/hodima/HEAD/release/` (شاخه پیش‌فرض = `claude/hodima`) می‌خواند و با نسخه نصب‌شده مقایسه می‌کند (`version_compare`؛ پس نسخه فقط باید بالا برود). نسخه هر بسته از سربرگ `Version` (+ ثابت `HODIMA_*_VERSION` در افزونه‌ها) — قاعده شماره‌گذاری: قانون ۷ `CLAUDE.md`. ساخت: `bin/build.sh` ← `dist/` و `release/`؛ متن «جزئیات» از `CHANGELOG.md` (`bin/build-meta.py`).
ثابت‌های نسخه داخلی اجزا (`HODIMA_GI_VERSION`، `HODIMA_CORE_VERSION` داخل IndexNow، `*_DB_VERSION`) نسخه پایگاه داده/جزء‌اند، نه نسخه انتشار؛ با قاعده انتشار عوضشان نکن.

## ۸. ابزارها و مستندات
- کیفیت: `bin/lint.sh` (+ `tools/quality/`، `phpcs.xml.dist`، `phpstan.neon.dist`، `eslint.config.mjs`، `stylelint.config.mjs`)؛ CI: `.github/workflows/quality.yml`.
- تست وردپرس واقعی: `tools/wp-harness/` (راهنما `README.md` همان پوشه؛ آزمون رسانه `media-tests.php`).
- ساخت CSS قالب: `bin/build-css.mjs` (Lightning CSS) + `tools/wp-harness/css-equiv.mjs`.
- تاریخچه: `HODIMA-AUDIT.md` (`grep -n '^## '`)؛ راهنمای پیام کاربر: `docs/prompt-guide.md`.
