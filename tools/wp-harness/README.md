# ابزار تست هدیما روی وردپرس واقعی

وردپرس 6.7 + SQLite + PHP 8.4، با قالب و چهار افزونه همین مخزن (لینک نمادین، نه کپی) و داده آزمایشی ثابت. برای اینکه ثابت کنیم یک تغییر خروجی سایت (به‌ویژه اسکیما) را فقط همان‌جا که می‌خواستیم عوض کرده است.

فقط برای توسعه است: در `bin/build.sh` و فایل‌های نصب نیست و هیچ‌وقت روی سایت واقعی نصب نمی‌شود.

## استفاده سریع
```bash
# کار فعلی (تغییرات commit‌نشده) در برابر آخرین commit — رایج‌ترین حالت
tools/wp-harness/compare-with-ref.sh

# در برابر یک commit مشخص
tools/wp-harness/compare-with-ref.sh 7fa8063

# ظاهر قبل/بعد: استایل محاسبه‌شده هر عنصر + عکس پیکسل‌به‌پیکسل (دسکتاپ ۱۳۰۰، موبایل ۳۹۰)
tools/wp-harness/visual-compare.sh                 # همه صفحه‌ها، کار فعلی در برابر آخرین commit
tools/wp-harness/visual-compare.sh HEAD home 404   # فقط چند صفحه
tools/wp-harness/visual-compare.sh --built         # طرف «کار» با CSS ساخته‌شده ZIP (minify + پایین‌آوردن)
HODIMA_VISUAL_WIDTHS="1180,1000,800,420" tools/wp-harness/visual-compare.sh   # عرض‌های دیگر (نزدیک نقطه‌های شکست)

# هم‌ارزی CSS قانون‌به‌قانون (بدون صفحه؛ همه فایل‌ها حتی single-product.css که صفحه‌اش در ابزار تست نیست)
tools/wp-harness/css-equiv.sh            # CSS آخرین commit در برابر کار فعلی
tools/wp-harness/css-equiv.sh --built    # سورس در برابر خروجی bin/build-css.mjs (bin/build.sh هم همین را اجبارا اجرا می‌کند)

# بررسی GitHub Actions به‌صورت محلی: بدون هشدار PHP، بدون صفحه ناقص، بدون @id تکراری
tools/wp-harness/ci-check.sh [ref]

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
| `css-equiv.sh` / `css-equiv.mjs` | هم‌ارزی دو نسخه CSS قالب در Chromium، مستقل از HTML: از CSSOM برای هر «@media/@supports + انتخابگر تکی + خصوصیت longhand» مقدار نهایی (important برنده، وگرنه آخرین)؛ nesting مثل مرورگر باز می‌شود (والد چندتایی = `:is()`)، توکن‌های `tokens.css` هر طرف جایگزین می‌شوند و نگارش‌های هم‌معنا یکی (`#fff`/`#ffffff`، `transparent`، `width <= 768px` = `max-width: 768px`، `48rem` = `768px`، `bold` = `700`…). ترتیب نسبی قانون‌های *متفاوت* را نمی‌سنجد. در مرحله ۵ نوسازی، حذف `backdrop-filter` توسط minify را گرفت که عکس‌ها نمی‌دیدند |
| `visual-compare.sh` / `visual-compare.mjs` | مقایسه ظاهر دو نسخه با Chromium (`playwright-core` از `npm ci`): `getComputedStyle` همه عنصرها به ترتیب DOM و عکس کل صفحه؛ CSS/JS هر طرف از پوشه خودش. بازنویسی هم‌ارز CSS (خصوصیات منطقی، nesting، …) باید «یکسان» بدهد. تصویرهای آپلود = PNG خاکستری ثابت، ویدیو/صوت = خطای فوری (بدون چرخنده)، انیمیشن خاموش. عکس‌ها و `*-diff.png` (قرمز = پیکسل متفاوت) در `$HODIMA_HARNESS/visual` |
| `zip-install-check.sh` + `zip-install.php` | نصب و فعال‌سازی واقعی از `dist/*.zip` (اول `bash bin/build.sh`) روی کپی جدای وردپرس، در هر دو ترتیب «اول قالب» و «اول افزونه‌ها»، هر گام یک فرایند PHP جدا؛ «Cannot redeclare» و هر خطای PHP را می‌گیرد، بعد صفحه اصلی را می‌سازد |
| `ci-check.sh [ref]` | کار «render» در GitHub Actions: رد با هشدار PHP، صفحه خالی/ناقص، `@id` تکراری؛ تفاوت اسکیما با ref فقط گزارش |
| `mu-plugins/harness-core.php` | بدون ریدایرکت canonical؛ ورود مدیر با `HARNESS_USER=1` |
| `mu-plugins/harness-wc-stub.php` | ووکامرس حداقلی با `HARNESS_WC=1` (محصول، دسته، برچسب، فروشگاه). ویژگی‌ها از متای `attr_{نام}` (مثل `attr_pa_color`) و وزن از متای `_weight` خوانده می‌شوند |

## نمونه: مقایسه در حالت خاص
```bash
export HODIMA_HARNESS=/tmp/hodima-harness
export HARNESS_OPTS='update_option( "hodima_schema_homepage_enable", "0" );'   # گراف اصلی خاموش
tools/wp-harness/compare-with-ref.sh
unset HARNESS_OPTS
```

## محدودیت‌ها
- مقایسه ظاهر فقط چیزی را می‌بیند که در صفحه‌های نمونه هست: CSS انتخابگری که در داده آزمایشی عنصری ندارد (مثلا پاسخ تو در توی دیدگاه) یا قانونی که قانون دیگری بازنویسی‌اش می‌کند «یکسان» می‌ماند. برای آزمایش یک قانون، اول مطمئن شوید روی صفحه اثر دارد.
- ووکامرس واقعی نیست؛ صفحه‌های محصول/فروشگاه را روی سایت با [Rich Results Test](https://search.google.com/test/rich-results) هم بررسی کنید.
- پنل کاربری در مخزن نیست و تست نمی‌شود.
