# ابزار تست هدیما روی وردپرس واقعی

وردپرس 6.7 + SQLite + PHP 8.4، با قالب و چهار افزونه همین مخزن (لینک نمادین، نه کپی) و داده آزمایشی ثابت. برای اینکه ثابت کنیم یک تغییر خروجی سایت (به‌ویژه اسکیما) را فقط همان‌جا که می‌خواستیم عوض کرده است.

فقط برای توسعه است: در `bin/build.sh` و فایل‌های نصب نیست و هیچ‌وقت روی سایت واقعی نصب نمی‌شود.

## استفاده سریع
```bash
# کار فعلی (تغییرات commit‌نشده) در برابر آخرین commit — رایج‌ترین حالت
tools/wp-harness/compare-with-ref.sh

# در برابر یک commit مشخص
tools/wp-harness/compare-with-ref.sh 7fa8063

# صفحه‌های پیشخوان (دسترسی، عنوان، منوی فعال، هشدار PHP)
tools/wp-harness/admin-check.sh . /tmp/adm                 # صفحه‌های پیش‌فرض
tools/wp-harness/admin-check.sh . /tmp/adm hodima-hub-seo  # صفحه‌های دلخواه
```
بار اول `setup.sh` خودکار اجرا می‌شود. مسیر کار: `$HODIMA_HARNESS` (پیش‌فرض `/tmp/hodima-harness`).

## خروجی `compare-with-ref.sh`
- **JSON-LD:** برای هر صفحه، تعداد تگ‌ها (قبل → بعد) و تفاوت‌ها با «دید گوگل»: نودهای هم‌شناسه (`@id` یکسان) یک موجودیت‌اند و هر ویژگی مجموعه مقادیرش است. `LOST` یعنی چیزی حذف شده — باید عمدی باشد.
  - تفاوت `uploadDate` در `/hair/#video` طبیعی است: تاریخ ویدیوی دسته در اولین بازدید ثبت می‌شود و هر اجرا نصب تازه است.
- **Integrity:** شناسه تکراری در یک تگ، و ارجاع‌های بی‌مقصد. ارجاع خوشه موضوعی به صفحه‌های دیگر (`/metal-clips/#webpage`، `/news/#webpage`، `/guide/#webpage`، `/guide-child/#webpage`) عمدی است.
- **PHP notices:** هشدارهای PHP کد فعلی (`comments.php` وردپرس فیلتر شده).

## صفحه‌هایی که تست می‌شوند (`run.sh`)
بدون ووکامرس، رندر کامل قالب: خانه، وبلاگ و صفحه ۲، مقاله پیلار و فرزند، درباره‌ما، تماس، برگه راهنما (FAQ رسانه + FAQ هوش مصنوعی + جدول + خوشه)، برگه فرزند، ویدئوها، ویدیو، دسته و صفحه ۲، برچسب، نویسنده، جستجو، ۴۰۴، آرشیو تاریخ.
با ووکامرس شبیه‌سازی‌شده (فقط `wp_head`/`wp_footer`): دو محصول ساده، یک محصول متغیر (`/kesh-rangi/`، سه تنوع: ناموجود، با بارکد، بی‌قیمت)، دسته محصول، فروشگاه، برچسب محصول، خانه. واحد پول stub با گزینه `harness_wc_currency` (پیش‌فرض IRR؛ برای تومان `IRT`).

داده آزمایشی در `fixtures.php` است؛ برای پوشش حالت جدید همان‌جا اضافه کنید.

## فایل‌ها
| فایل | کار |
|---|---|
| `setup.sh` | دانلود وردپرس و SQLite (از بسته npm `@wp-playground/wordpress-builds`، چون wordpress.org در محیط ابری بسته است) |
| `link.sh <src>` | وصل کردن قالب و افزونه‌های یک پوشه به سایت تست |
| `fixtures.php` | نصب تازه + داده آزمایشی. `HARNESS_OPTS` = کد PHP اضافه بعد از داده (مثلا خاموش کردن یک گزینه) |
| `run.sh <src> <out>` | رندر همه صفحه‌ها و استخراج JSON-LD |
| `render.php <path> <full\|hooks>` | رندر یک آدرس |
| `admin-render.php <slug>` | رندر یک صفحه پیشخوان با حساب مدیر |
| `wp-eval.php '<php>'` | اجرای کد دلخواه روی سایت تست (`HARNESS=1 HODIMA_WP=… php wp-eval.php '…'`) |
| `extract.py` / `compare.py` / `integrity.py` | استخراج، مقایسه و بررسی گراف |
| `mu-plugins/harness-core.php` | بدون ریدایرکت canonical؛ ورود مدیر با `HARNESS_USER=1` |
| `mu-plugins/harness-wc-stub.php` | ووکامرس حداقلی با `HARNESS_WC=1` (محصول، دسته، برچسب، فروشگاه) |

## نمونه: مقایسه در حالت خاص
```bash
export HODIMA_HARNESS=/tmp/hodima-harness
export HARNESS_OPTS='update_option( "hodima_schema_homepage_enable", "0" );'   # گراف اصلی خاموش
tools/wp-harness/compare-with-ref.sh
unset HARNESS_OPTS
```

## محدودیت‌ها
- ووکامرس واقعی نیست؛ صفحه‌های محصول/فروشگاه را روی سایت با [Rich Results Test](https://search.google.com/test/rich-results) هم بررسی کنید.
- پنل کاربری در مخزن نیست و تست نمی‌شود.
