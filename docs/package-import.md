# ورود بسته (Hodima SEO) — قالب JSON

کادر «ورود بسته» بالای ویرایش **محصول**، **دسته محصول**، **مقاله** و **ویدیو** است:

- **ویرایشگر کلاسیک:** بالای عنوان.
- **ویرایشگر بلوکی:** اولین کادر پایین ویرایشگر.
- **دسته محصول:** بالای جدول فرم دسته.

روند کار: بسته را بچسبانید ← «بررسی بسته» ← «پر کردن فرم» ← دکمه «به‌روزرسانی» خود صفحه. «بازگرداندن مقدارهای قبلی» پیش از ذخیره همه چیز را برمی‌گرداند.

خاموش/روشن: «ابزارهای هدیما ← ماژول‌های سئو ← ورود بسته». کد: `plugins/hodima-seo/core/package-import/` (قانون‌ها: اسکیل `hodima-plugins`).

## قانون‌ها
- هیچ نوشتن مستقیم در پایگاه داده و هیچ ذخیره خودکار. فقط خانه‌های فرم همان صفحه پر می‌شوند و کد ذخیره خود هر کادر ذخیره می‌کند.
- **قفل آدرس:** `url` بسته باید آدرس همین صفحه باشد (بدون فرق http/https، www و اسلش آخر)؛ وگرنه هیچ خانه‌ای پر نمی‌شود.
- **کلید کادر:**
  - کلید نیامده = آن کادر دست نمی‌خورد.
  - کلید آمده = کل کادر جایگزین می‌شود (زیرکلید نیامده = خالی).
  - `[]` = خالی کردن کادر.
- آدرس‌های داخلی کامل می‌آیند (`https://hodhodli.com/103/`). ابزار شناسه را پیدا می‌کند (عدد آدرس محصول شناسه نیست). آدرس پیدانشده فقط همان مورد را کنار می‌گذارد و هشدار می‌دهد.
- **هشدارها جلوی پر کردن را نمی‌گیرند:**
  - شماره `tel:` غیر از 09124093140 و 02177322684؛
  - سرتیتر H1، H4، H5، H6؛
  - لینک به سایت دیگر؛
  - پرسش، ردیف جدول یا کارت تکراری؛
  - طول عنوان و توضیح سئو با خط‌کش پیکسلی سئوباکس (۵۸۰ و ۹۲۰ پیکسل).
- کلیدی که برای آن نوع نیست (مثلا `discover` در دسته) فقط گزارش می‌شود: «دست نخورد».
- **قالب:**
  - `hodima-package/1`: فقط محصول؛ بسته‌های قبلی بی‌تغییر کار می‌کنند.
  - `hodima-package/2`: هر چهار نوع.

## کادرهای هر نوع

| کلید | محصول | دسته | مقاله | ویدیو | شکل |
|---|---|---|---|---|---|
| `body` | ✔ | — | ✔ | ✔ | `{"html": "…"}` یا رشته HTML (شورت‌کدها می‌مانند) |
| `intro_html` | — | ✔ | ✔ | — | رشته HTML («متن معرفی» تنظیمات رسانه، `[hook_intro]`؛ `<details>` می‌ماند) |
| `description_html` | — | ✔ | — | — | رشته HTML («توضیحات» دسته) |
| `upsells`، `cross_sells` | ✔ | — | — | — | `{"urls": ["…"]}` (upsell حداکثر ۶) |
| `video` | — | ✔ | ✔ | ✔ | دسته و مقاله: `{"url","title","description","duration","chapters","transcript"}`؛ ویدیو: `{"url","cover","duration","chapters","transcript"}` |
| `podcast` | — | ✔ | ✔ | — | `{"url","title","duration","transcript"}` |
| `faq` | ✔ | ✔ | ✔ | — | `[{"q","a"}]` |
| `table` | ✔ | ✔ | ✔ | — | `{"headers": […], "rows": [[…]]}` |
| `related_links` | ✔ | ✔ | ✔ | — | `{"products": ["آدرس"], "articles": ["آدرس"]}` |
| `topic_cluster` | ✔ | ✔ | ✔ | — | `{"pillar","exclude","parent_term","parent_post_url"}` (`parent_post_url` فقط مقاله) |
| `discover` | — | — | ✔ | — | `{"title","topics": ["نام" یا "نام آدرس‌ویکی‌داده"],"skip"}` |
| `aeo` | ✔ | ✔ (بدون بخش فروش) | ✔ | — | همان محصول |
| `seobox` | ✔ | ✔ | ✔ | ✔ | `{"title","description"}` |
| `suggestions` | — | — | — | ✔ | `[{"title","url","cover"?}]` (کارت‌های «ویدیوهای مرتبط») |
| `product_button` | — | — | — | ✔ | آدرس محصول یا دسته، یا `null` = خاموش |

## بیرون از ابزار (دستی)
- عکس‌ها و alt (کتابخانه رسانه)، تصویر دیسکاور و نقطه تمرکز، کاور ویدیوی «تنظیمات رسانه»، تصویر دسته.
- ویژگی‌ها، دسته‌ها و برچسب‌های ووکامرس؛ دسته و برچسب مقاله.
- بخش «رسانه‌ها»ی پنل AEO.
- ترتیب و «موضوع» زیرمجموعه‌های پیلار در خوشه‌بندی.
- زیرنویس `.vtt`، «ویدیوهای بیشتر» و نسبت تصویر ویدیو.
- **ویدیو:** دیسکاور (فقط مقاله، SEO 4.0.2)؛ لینک‌های مرتبط، خوشه‌بندی، AEO و تنظیمات رسانه که روی ویدیو نیستند؛ `download_url` (دانلود همان `video.url` است).

## نمونه — دسته محصول
```json
{
  "format": "hodima-package/2",
  "url": "https://hodhodli.com/accessory/hair-ties/",
  "type": "category",
  "date": "2026-11-14",
  "note": "بازنویسی معرفی و توضیحات دسته کش مو",
  "boxes": {
    "intro_html": "<details><summary>کش موی عمده</summary><p>انواع کش مو برای فروشگاه‌ها. تماس: <a href=\"tel:09124093140\">۰۹۱۲۴۰۹۳۱۴۰</a></p></details>",
    "description_html": "<h2>راهنمای خرید عمده کش مو</h2>\n<p>…</p>\n[hodima_table]\n[hodima_topic_cluster]\n[hook_faq]",
    "video": {"url": "https://www.aparat.com/v/xxxxx", "title": "معرفی کش‌های مو", "description": "", "duration": "2:35", "chapters": ["0:00 معرفی", "0:45 رنگ‌بندی"], "transcript": ""},
    "podcast": [],
    "faq": [{"q": "حداقل سفارش عمده چقدر است؟", "a": "یک کارتن."}],
    "table": {"headers": ["ویژگی", "توضیحات"], "rows": [["جنس", "TPU"], ["بسته‌بندی", "کارتن ۱۲ عددی"]]},
    "related_links": {"products": ["https://hodhodli.com/103/"], "articles": ["https://hodhodli.com/blog/hair-tie-guide/"]},
    "topic_cluster": {"pillar": true, "exclude": false, "parent_term": "https://hodhodli.com/accessory/", "parent_post_url": null},
    "aeo": {"en_entity": "Hair Ties", "fa_aliases": "کش مو، کش سر", "en_aliases": "Hair Elastics", "fa_intent": "خرید عمده کش مو", "en_intent": "Wholesale hair ties", "fa_context": "…", "en_context": "…", "fa_usecases": "", "en_usecases": "", "fa_audience": "", "en_audience": "", "fa_comparison": "", "en_comparison": "", "fa_embedding": "", "en_embedding": "", "en_specs": [{"k": "Material", "v": "TPU"}], "fa_faqs": [], "fa_prompts": ["کش موی عمده از کجا بخرم؟"], "en_faqs": [], "en_prompts": []},
    "seobox": {"title": "خرید عمده کش مو | هدهدلی", "description": "انواع کش مو را عمده و کارتنی با قیمت مستقیم واردکننده بخرید."}
  }
}
```

## نمونه — مقاله
```json
{
  "format": "hodima-package/2",
  "url": "https://hodhodli.com/blog/hair-tie-guide/",
  "type": "article",
  "date": "2026-11-14",
  "note": "مقاله راهنمای کش مو",
  "boxes": {
    "body": {"html": "<h2>کش موی مناسب چیست؟</h2>\n<p>…</p>\n[hook_video]\n[hook_faq]"},
    "intro_html": "<p>در این راهنما …</p>",
    "video": {"url": "https://www.aparat.com/v/yyyyy", "title": "راهنمای انتخاب کش مو", "description": "", "duration": "4:10", "chapters": [], "transcript": ""},
    "podcast": {"url": "https://hodhodli.com/wp-content/uploads/hair-ties.mp3", "title": "پادکست کش مو", "duration": "12:40", "transcript": ""},
    "faq": [{"q": "کش TPU به مو آسیب می‌زند؟", "a": "خیر …"}],
    "table": [],
    "related_links": {"products": ["https://hodhodli.com/103/", "https://hodhodli.com/126/"], "articles": []},
    "topic_cluster": {"pillar": false, "exclude": false, "parent_term": "https://hodhodli.com/blog/guides/", "parent_post_url": "https://hodhodli.com/hair-accessories-guide/"},
    "discover": {"title": "هر آنچه درباره کش مو باید بدانید", "topics": ["کش مو", "اکسسوری مو https://www.wikidata.org/wiki/Q…"], "skip": false},
    "aeo": {"en_entity": "Hair Tie Guide", "fa_context": "…", "en_context": "…"},
    "seobox": {"title": "راهنمای خرید کش مو | هدهدلی", "description": "انتخاب کش موی مناسب برای فروشگاه: جنس، اندازه، بسته‌بندی و قیمت عمده."}
  }
}
```

## نمونه — ویدیو
```json
{
  "format": "hodima-package/2",
  "url": "https://hodhodli.com/video/cherry-hair-tie/",
  "type": "video",
  "date": "2026-11-14",
  "note": "ویدیوی کش موی گیلاسی",
  "boxes": {
    "body": "<p>در این ویدیو کش موی گیلاسی را از نزدیک می‌بینید …</p>",
    "video": {"url": "https://hodhodli.com/wp-content/uploads/cherry.mp4", "cover": "https://hodhodli.com/wp-content/uploads/cherry.jpg", "duration": "1:20", "chapters": "0:00 معرفی\n0:40 بستن مو", "transcript": "…"},
    "suggestions": [{"title": "کش موی ساده", "url": "https://hodhodli.com/video/simple-hair-tie/"}, {"title": "کلیپس فلزی", "url": "https://hodhodli.com/video/metal-clip/", "cover": ""}],
    "product_button": "https://hodhodli.com/124/",
    "seobox": {"title": "ویدیوی کش موی گیلاسی | هدهدلی", "description": "ببینید کش موی گیلاسی چطور بسته می‌شود و برای چه مدل مویی مناسب است."}
  }
}
```

## نمونه — محصول
همان قالب نسخه ۱ (`hodima-package/1`، بخش ۴۳۵ `HODIMA-AUDIT.md`؛ نمونه کامل: `tools/wp-harness/fixtures/package-103.json`). با `hodima-package/2` هم پذیرفته می‌شود.
