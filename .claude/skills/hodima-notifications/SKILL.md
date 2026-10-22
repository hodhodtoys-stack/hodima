---
name: hodima-notifications
description: ماژول نوتیفیکیشن‌ها (پاپ‌آپ اعلان) افزونه Hodima Media — ساختار فایل‌ها، قانون‌ها، آمار، پیشخوان (فهرست، ویرایش تب‌دار، تب آمار)، آزمون‌ها، تاریخچه نسخه‌های ۲.۰.۲ تا ۲.۰.۷ و کارهای باقی‌مانده. پیش از خواندن یا تغییر هر فایل زیر plugins/hodima-media/components/notification/ یا هر کار مربوط به پاپ‌آپ، آمار نوتیفیکیشن یا صفحه‌های پیشخوان آن به کار ببر.
---

# نوتیفیکیشن‌ها (Hodima Media)

مسیر: `plugins/hodima-media/components/notification/`؛ ماژول `notifications` در `hodima_media_modules()` (`hodima-media.php`). زیر PHPCS/PHPStan/ESLint/Stylelint **بدون baseline** (کد تازه باید تمیز باشد). قانون‌های عمومی افزونه‌ها (طراحی پیشخوان، حریم خصوصی، نسخه) در اسکیل `hodima-plugins`؛ انتشار با `/release`. تاریخچه کامل: `HODIMA-AUDIT.md` بخش‌های ۲۰۹ تا ۲۱۴.

## فایل‌ها (ترتیب لود در فهرست ماژول)
| فایل | کار |
|---|---|
| `notification-settings.php` | **تنها منبع فیلدها**: `hodima_notification_fields()` (نوع، پیش‌فرض، محدوده/گزینه)، `hodima_notification_sanitize()`، `hodima_notification_meta()`، `hodima_notification_ids()`، `hodima_notification_normalize_position()`، `hodima_notification_clear_cache()`، ثابت‌های `HODIMA_NOTIFICATION_POST_TYPE` (`hd_notification`) و `HODIMA_NOTIFICATION_CACHE` (`hd_active_notifications`) |
| `notification-cpt.php` | پست‌تایپ (زیر «ابزارهای هدیما»؛ بدون Core منوی سطح اول)، ستون‌های «وضعیت نمایش» (`hodima_notification_display_state()`) و «جای نمایش»، `hodima_notification_format_date()` (شمسی با Core)، آیکون منو |
| `notification-metabox.php` | کادر ویرایش (`Hodima_Notification_Metabox`)؛ require می‌کند `notification-save.php` |
| `notification-save.php` | ذخیره کادر (فقط فیلدهای فرستاده‌شده؛ کلید روشن/خاموش نیامده = خاموش) و پاک کردن کش (`transition_post_status`، حذف، زباله‌دان) |
| `notification-front.php` | نمایش در سایت (`Hodima_Notification_Front`): کش فعال‌ها، قوانین سرور، خروجی `<dialog>` |
| `notification-stats.php` | آمار: هندلر عمومی، شمارنده‌ها، جدول روزانه، ستون آمار فهرست، کپی گرفتن، متن حریم خصوصی |
| `notification-stats-page.php` | صفحه «آمار» (`admin.php?page=hodima-notification-stats`) و `hodima_notification_admin_tabs()` |
| `notification-admin.php` | سربرگ و تب‌ها روی فهرست/افزودن/ویرایش، ستون عکس کوچک، کلید روشن/خاموش فهرست (admin-ajax) |
| `assets/css/notification-front.css`، `assets/js/notification-front.js` | سایت |
| `assets/css/notification-metabox.css`، `assets/js/notification-metabox.js` | کادر ویرایش (تب‌ها، پیش‌نمایش، انتخاب عکس، تاریخ شمسی) |
| `assets/css/notification-admin.css`، `assets/js/notification-list.js` | سربرگ و فهرست |
| `assets/css/notification-stats.css`، `assets/js/notification-stats.js` | صفحه آمار |

## داده (عوض نشوند، بدون مهاجرت)
- پست‌تایپ `hd_notification`؛ متاهای تنظیمات `_hd_notif_*` (کلید فیلد با `_`)؛ کش `hd_active_notifications` (۱۲ ساعت؛ آرایه `{id,title,meta,image}`، قالب قدیمی خودکار بازسازی).
- آمار کل: `_hd_notif_stats_views|_clicks|_closes|_since`. آمار روزانه: جدول `{prefix}hodima_notif_daily` (`post_id`, `day`, `views`, `clicks`, `closes`؛ کلید اصلی `(post_id, day)`)، نسخه ساختار در گزینه `hodima_notif_daily_db`، نگهداری ۴۰۰ روز.
- مرورگر: sessionStorage `hd_notif_session` (pv، utm، ref، nv، dismissed)، localStorage `hd_notif_first`، `hd_notif_count_{id}`، `hd_notif_hide_{id}`.

## قانون‌ها
- **فیلد تازه فقط در `hodima_notification_fields()`**؛ کادر، ذخیره و نمایش همه از آن می‌خوانند. پیش‌فرض فیلد تازه = رفتار قبلی نوتیفیکیشن‌های موجود (پیشنهاد تازه فقط برای `auto-draft` در کادر، مثل `hd_notif_mobile_mode = corner`). فیلد را در تب مناسب کادر بگذار؛ اگر روی ظاهر اثر دارد، پیش‌نمایش (`renderPreview` در `notification-metabox.js`) را هم به‌روز کن. نوع `ids` برای چک‌باکس‌ها با ورودی پنهان خالی (برداشتن همه تیک‌ها هم ذخیره شود).
- **کش:** هر مسیر تازه‌ای که روی نمایش اثر دارد `hodima_notification_clear_cache()` بزند.
- **کش لایت‌اسپید:** صفحه‌ها برای همه یکسان کش می‌شوند؛ پس قوانین وابسته به بازدیدکننده/زمان (تاریخ دقیق، UTM، ارجاع‌دهنده، دستگاه، «تازه از جستجو»، حالت موبایل، بازدیدکننده تازه/برگشتی، حداقل صفحه، فاصله روزها) **در مرورگر** با `data-*`. سرور فقط قوانین آدرس/کاربر/دسته محصول و حذف اعلان منقضی.
- **سایت:** هر اعلان `<dialog tabindex="-1">`. پنجره وسط `showModal()` و فوکوس روی خود dialog (نه دکمه بستن؛ باز شدن خودکار حلقه فوکوس نشان می‌داد)؛ جعبه گوشه با ویژگی `open` (نه `show()` که فوکوس می‌برد)؛ Escape با `cancel` و انیمیشن بستن؛ قفل اسکرول `html.hd-notif-lock`؛ حالت بدون dialog (`hd-notif-fallback`، Safari زیر 15.4) نگه داشته شود. حداکثر یک پنجره وسط در هر صفحه. پاپ‌آپ نباید فقط با انیمیشن دیده شود (opacity پایه ۱؛ باگ «کاهش حرکت»). موقعیت فقط `center|bottom_right|bottom_left` (قدیمی‌ها به گوشه). «شفاف» = PNG بی‌پس‌زمینه (`hd-notif-transparent`: بدون کادر، `drop-shadow`). عکس با `_hd_notif_bg_image_id` (srcset/sizes و ابعاد؛ `data-src` تا لحظه نمایش). کلاس‌های قدیمی (`hd-notification-popup`، `hd-pos-*`، `hd-notif-close`، `hd-notif-main-area`، `hd-notif-half-link`) عوض نشوند. CSS سایت بدون nesting و `!important` (مرحله ساخت ندارد)؛ «راست/چپ» گوشه‌ها فیزیکی با `stylelint-disable` و دلیل.
- **آمار:** فقط با `hodima_notification_stat_increment()` (اتمی؛ کل در متا + روز در جدول با UPDATE ← INSERT IGNORE ← UPDATE، بدون ON DUPLICATE KEY تا SQLite ابزار تست هم کار کند). هندلر عمومی `hodima_notif_event` عمدا بدون nonce (صفحه کش‌شده)؛ محافظت: فقط منتشرشده، رد ربات، ۶۰ رویداد در دقیقه با `hodima_rate_limit_key()`، و در مرورگر هر رویداد یک بار در هر صفحه. GA4 فقط رویدادهای استاندارد `view_promotion`/`select_promotion` (با `gtag` اگر هست، وگرنه `dataLayer`). نمایش آمار فقط در تب «آمار» (نه کادر ویرایش؛ خواست کاربر). هر داده تازه در مرورگر یا سرور را به `hodima_notification_privacy_text()` اضافه کن.
- **پیشخوان (خواست کاربر):** سربرگ هدیما + تب‌های «نوتیفیکیشن‌ها | افزودن | آمار» روی همه صفحه‌ها (`hodima_notification_admin_tabs()`؛ در `all_admin_notices` اولویت ۰ و `wp-header-end` سربرگ برداشته شود؛ `clear: both` زیر Screen Options). صفحه آمار زیر منوی هدیما ثبت و در `admin_head` از نمایش منو برداشته می‌شود (حذف در admin_menu دسترسی را می‌برد). کادر ویرایش: نوار وضعیت (روشن/اولویت/آمار) بیرون از تب‌ها، شش تب ARIA «عکس و لینک | جا و اندازه | زمان و دفعات | مخاطب | دکمه و انیمیشن | **پیش‌نمایش**» — پیش‌نمایش تب آخر و تمام‌عرض (ستون کناری نه). بدون JS همه بخش‌ها زیر هم. پیش‌نمایش = صفحه مجازی ۱۲۸۰×۸۰۰ / ۳۹۰×۷۸۰ با CSS خود سایت که با `transform: scale` جا می‌شود؛ کلاس تازه سایت را در صحنه (`notification-metabox.css`) هم ببین. فهرست: عکس کوچک و کلید روشن/خاموش (nonce `hodima_notif_toggle`، `edit_post`). CSS هر صفحه زیر کلاس خودش (`.hdn`، `.hdn-screen`، `.hdn-stats-page`)؛ هرگز `:root` یا `.form-table` سراسری.
- **نمودار آمار:** SVG سمت سرور، دو نمودار تک‌سری کنار هم (نمایش و کلیک؛ دو مقیاس ← دو نمودار، نه محور دوم)، رنگ `--hd-secondary`، عدد محور در HTML (SVG کش می‌آید)، راست‌به‌چپ امروز سمت چپ، راهنما با ماوس/لمس/فلش و «جدول روز به روز» برای دسترسی.

## آزمون
- `HARNESS=1 php tools/wp-harness/notification-tests.php` (در `ci-check.sh`؛ ۶۷ آزمون: کش، ذخیره، ستون وضعیت، خروجی dialog/srcset، آمار کل و روزانه، محدودیت نرخ، صفحه آمار، صفر کردن، کپی، دسته محصول، سربرگ و کلید فهرست). سهمیه محدودیت نرخ و جدول روزانه را خود آزمون آماده می‌کند.
- `node tools/wp-harness/a11y-dynamic.mjs <out> . notification` (نمونه `notif()` باید با خروجی `notification-front.php` هماهنگ بماند) و `target-size-fixtures.mjs`.
- پیشخوان: `HARNESS=1 HARNESS_USER=1 php tools/wp-harness/admin-render-screen.php edit.php "post_type=hd_notification"` (یا `post.php "post=ID&action=edit"`، `admin-render.php hodima-notification-stats`) سپس `HODIMA_RTL=1 node tools/wp-harness/admin-shot.mjs <html> <out> /tmp/hodima-harness/wp . 1300,390 [انتخابگر]` (عکس + axe؛ CSS پیشخوان وردپرس 6.7 در `~/.cache/hodima/wp-core-6.7`). `wp.media` و jQuery در ابزار تست نیستند.
- `ci-check.sh` پایگاه داده را از نو می‌سازد: نوتیفیکیشن آزمایشی را بعد از آن دوباره بساز.

## تاریخچه (Hodima Media)
- **۲.۰.۲** (بخش ۲۰۹): پاپ‌آپ نامرئی با «کاهش حرکت»، کش ۱۲ ساعته با ویرایش سریع/زمان‌بندی، «بستن خودکار» و «نقش کاربر» بی‌فرم، لینک نیمه پایین، ستون وضعیت، **PNG شفاف**.
- **۲.۰.۳** (بخش ۲۱۰): منبع واحد تنظیمات، `<dialog>`، srcset، حالت موبایل (جعبه گوشه، سئو)، «تازه از جستجو»، طراحی پیشخوان hd-*، ابزارهای کیفیت کد.
- **۲.۰.۴** (بخش ۲۱۱): آمار نمایش/کلیک/بستن، GA4، «تا چند روز نشان نده»، دسته محصول، بازدیدکننده تازه/برگشتی، حداقل صفحه، یک پنجره وسط در صفحه، کپی، متن حریم خصوصی.
- **۲.۰.۵** (بخش ۲۱۲): تب «آمار» با بازه، مقایسه دوره قبل، نمودار و جدول؛ آمار روزانه.
- **۲.۰.۶** (بخش ۲۱۳): سربرگ و تب‌ها، عکس کوچک و کلید فهرست، کادر ویرایش تب‌دار.
- **۲.۰.۷** (بخش ۲۱۴): پیش‌نمایش تب آخر و تمام‌عرض.

## پیشنهادهای باقی‌مانده (کاربر هنوز انتخاب نکرده)
- پاپ‌آپ متنی: متن + دکمه، شمارش معکوس، کد تخفیف (الان فقط عکسی).
- آزمایش A/B دو نسخه یک پاپ‌آپ.
- هدف‌گیری با سبد خرید (مثلا مبلغ کمتر از حداقل سفارش).
- نمایش در روزهای هفته یا ساعت‌های خاص.
- نوار متنی بالای صفحه.
- حذف جدول `hodima_notif_daily` هنگام حذف کامل افزونه (Media هنوز uninstall ندارد).
