#!/usr/bin/env python3
"""قواعد اسکیمای هدیما روی خروجی run.sh (هر صفحه = یک فایل HTML).

compare.py فقط «قبل/بعد» را مقایسه می‌کند؛ اگر خروجی از اول غلط باشد چیزی
نمی‌گوید. این فایل قواعدی را که پروژه با زحمت به دست آورده (و هر کدام یک
باگ واقعی گذشته بوده) روی خروجی فعلی بررسی می‌کند تا دوباره برنگردند:

  گراف      یک تگ JSON-LD (گراف واحد)، @context درست، بدون @id تکراری
  هویت      #organization و #website در همه صفحه‌ها؛ #webpage = canonical
            (۴۰۴ و جستجو بدون #webpage)؛ ارجاع داخل همین صفحه بی‌مقصد نیست
  تاریخ     ISO 8601 با رقم لاتین (نه خروجی مبدل شمسی wp_date)
  آدرس      مطلق، بدون &#038; (esc_url به‌جای esc_url_raw)
  محصول     Offer با قیمت عددی و ارز ISO (هرگز IRT)، موجودی schema.org،
            ارسال و مرجوعی؛ تنوع‌ها inProductGroupWithID = productGroupID؛
            وزن متریک (هرگز پوند/اونس)؛ بدون review تکی (فقط aggregateRating)
  مقاله     headline حداکثر ۱۱۰ نویسه، نویسنده و ناشر با @id
  تصویر     creator تصویرها بدون @id (اعتبارسنج Image Metadata گوگل
            WholesaleStore را برای creator نمی‌پذیرد)
  ویدیو     نام، تصویر، تاریخ و contentUrl یا embedUrl
  بردکرامب  position پشت‌سرهم از ۱، هر پله نام و آدرس
  FAQ       سوال تکراری نه
  فهرست     یک مجموعه آیتم فقط در یک فهرست (ItemList/OfferCatalog) — دسته وبلاگ
            قبلا مقاله‌ها را هم در OfferCatalog و هم در ItemList داشت

  python3 schema-rules.py <outdir>        خروجی ۰ = همه قواعد قبول
"""
import glob
import json
import os
import re
import sys

# re.ASCII: بدون آن \d رقم فارسی («۱۴۰۵») را هم می‌پذیرد
ISO_DATE = re.compile(r'^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)?)?$', re.ASCII)
DATE_KEYS = {'datePublished', 'dateModified', 'uploadDate', 'validFrom', 'priceValidUntil', 'dateCreated'}
URL_KEYS = {'url', 'contentUrl', 'embedUrl', 'thumbnailUrl', 'item', 'logo', 'image', 'sameAs', 'license', 'acquireLicensePage', 'urlTemplate'}
NO_SEARCH_OR_404 = {'404', 'search'}


def scripts(html):
    return re.findall(r'<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>', html, re.S | re.I)


def walk(x, fn, path=''):
    """fn(node, path) برای هر شیء (dict)، در هر عمقی."""
    if isinstance(x, dict):
        fn(x, path)
        for k, v in x.items():
            walk(v, fn, f'{path}.{k}' if path else k)
    elif isinstance(x, list):
        for i, v in enumerate(x):
            walk(v, fn, f'{path}[{i}]')


def types(node):
    t = node.get('@type', [])
    return set(t if isinstance(t, list) else [t])


def as_list(v):
    return v if isinstance(v, list) else [v]


def check_page(name, html):
    errors = []
    err = errors.append

    tags = scripts(html)
    if not tags:
        return ['بدون JSON-LD']
    if len(tags) != 1:
        err(f'{len(tags)} تگ JSON-LD (گراف واحد باید یک تگ باشد)')

    data = []
    for t in tags:
        try:
            data.append(json.loads(t))
        except ValueError as e:
            err(f'JSON نامعتبر: {e}')
    if not data:
        return errors

    graph = data[0]
    if graph.get('@context') != 'https://schema.org':
        err(f'@context: {graph.get("@context")!r}')
    nodes = [n for d in data for n in (d.get('@graph', [d]) if isinstance(d, dict) else d) if isinstance(n, dict)]

    m = re.search(r'<link rel="canonical" href="([^"]+)"', html)
    canonical = m.group(1) if m else ''
    site = re.match(r'^(https?://[^/]+/)', canonical).group(1) if canonical else ''

    # --- گراف و هویت ---
    top_ids = [n['@id'] for n in nodes if '@id' in n]
    for dup in sorted({i for i in top_ids if top_ids.count(i) > 1}):
        err(f'@id تکراری در سطح بالا: {dup}')

    defined, refs = set(), set()

    def collect(node, _path):
        if '@id' in node:
            (refs if len(node) == 1 else defined).add(node['@id'])
    for n in nodes:
        walk(n, collect)

    if site:
        for need in ('#organization', '#website'):
            if site + need not in defined:
                err(f'نود {need} نیست')
        for ref in sorted(refs - defined):
            if ref.startswith(site + '#') or (canonical and ref.startswith(canonical + '#')):
                err(f'ارجاع بی‌مقصد در همین صفحه: {ref}')

    pages = [n for n in nodes if str(n.get('@id', '')).endswith('#webpage')]
    if name in NO_SEARCH_OR_404:
        if pages:
            err('صفحه ۴۰۴/جستجو نباید #webpage داشته باشد')
    elif canonical:
        if len(pages) != 1:
            err(f'{len(pages)} نود #webpage (باید یکی باشد)')
        elif pages[0]['@id'] != canonical + '#webpage' or pages[0].get('url') != canonical:
            err(f'#webpage با canonical یکی نیست: {pages[0]["@id"]} / {canonical}')

    for n in nodes:
        if str(n.get('@id', '')).endswith('#organization'):
            if not n.get('name') or not n.get('url') or not isinstance(n.get('logo'), dict) or not n['logo'].get('url'):
                err('#organization بدون نام/آدرس/لوگو')

    # --- قواعد هر شیء در هر عمق ---
    def rules(node, path):
        t = types(node)
        where = f'{"/".join(sorted(map(str, t)))} {node.get("@id", path)}'

        for k, v in node.items():
            if k in DATE_KEYS:
                for x in as_list(v):
                    if not isinstance(x, str) or not ISO_DATE.match(x):
                        err(f'{where}: تاریخ {k} ISO 8601 لاتین نیست: {x!r}')
            if k in URL_KEYS:
                for x in as_list(v):
                    if isinstance(x, str):
                        if '&#038;' in x or '&amp;' in x:
                            err(f'{where}: آدرس {k} HTML-escape شده: {x}')
                        if not re.match(r'^https?://', x):
                            err(f'{where}: آدرس {k} مطلق نیست: {x!r}')
            if k == 'priceCurrency' or k == 'currency':
                if not (isinstance(v, str) and re.fullmatch(r'[A-Z]{3}', v)) or v == 'IRT':
                    err(f'{where}: ارز {k} نامعتبر: {v!r}')
            if k == 'unitCode' and v in ('LBR', 'ONZ'):
                err(f'{where}: واحد غیرمتریک {v}')

        if 'Offer' in t:
            if not re.fullmatch(r'\d+(\.\d+)?', str(node.get('price', '')), re.ASCII) or not node.get('priceCurrency'):
                err(f'{where}: Offer بدون قیمت عددی/ارز')
            if not str(node.get('availability', '')).startswith('https://schema.org/'):
                err(f'{where}: availability نامعتبر')
            for need in ('shippingDetails', 'hasMerchantReturnPolicy', 'url'):
                if need not in node:
                    err(f'{where}: Offer بدون {need}')

        if t & {'Product', 'ProductGroup'} and 'inProductGroupWithID' not in node:
            for need in ('name', 'image', 'sku'):
                if not node.get(need):
                    err(f'{where}: محصول بدون {need}')
            if 'ProductGroup' in t:
                variants = as_list(node.get('hasVariant', []))
                if not variants:
                    err(f'{where}: ProductGroup بدون hasVariant')
                for var in variants:
                    if var.get('inProductGroupWithID') != node.get('productGroupID'):
                        err(f'{where}: تنوع {var.get("@id")} به گروه وصل نیست')
                    if 'offers' not in var:
                        err(f'{where}: تنوع {var.get("@id")} بدون Offer')
            if 'offers' in node and 'ProductGroup' in t:
                err(f'{where}: ProductGroup خودش Offer دارد')
            # فقط aggregateRating؛ نظرهای تکی هر کدام یک مورد جدا در گزارش گوگل می‌شدند (SEO 2.2.2)
            if 'review' in node:
                err(f'{where}: نظر تکی (review) در اسکیمای محصول؛ فقط aggregateRating')

        if t & {'BlogPosting', 'Article', 'NewsArticle'}:
            if len(str(node.get('headline', ''))) > 110:
                err(f'{where}: headline بیش از ۱۱۰ نویسه')
            for need in ('author', 'publisher'):
                if not (isinstance(node.get(need), dict) and node[need].get('@id')):
                    err(f'{where}: {need} با @id نیست')
            if not node.get('image'):
                err(f'{where}: مقاله بدون تصویر')

        if 'ImageObject' in t and isinstance(node.get('creator'), dict) and '@id' in node['creator']:
            err(f'{where}: creator تصویر @id دارد (Image Metadata گوگل رد می‌کند)')

        if 'VideoObject' in t and len(node) > 1:
            for need in ('name', 'thumbnailUrl', 'uploadDate'):
                if not node.get(need):
                    err(f'{where}: ویدیو بدون {need}')
            if not (node.get('contentUrl') or node.get('embedUrl')):
                err(f'{where}: ویدیو بدون contentUrl/embedUrl')

        if 'BreadcrumbList' in t:
            items = as_list(node.get('itemListElement', []))
            if [i.get('position') for i in items] != list(range(1, len(items) + 1)):
                err(f'{where}: position بردکرامب پشت‌سرهم نیست')
            for i in items:
                if not i.get('name') or not i.get('item'):
                    err(f'{where}: پله بردکرامب بدون نام/آدرس')

        if 'FAQPage' in t:
            names = [q.get('name') for q in as_list(node.get('mainEntity', [])) if isinstance(q, dict)]
            for dup in sorted({q for q in names if names.count(q) > 1}):
                err(f'{where}: سوال تکراری: {dup}')

    for n in nodes:
        walk(n, rules)

    # یک مجموعه آیتم در دو فهرست
    lists = []

    def collect_lists(node, _path):
        if types(node) & {'ItemList', 'OfferCatalog'} and node.get('itemListElement'):
            urls = frozenset(str(i.get('url') or i.get('item') or (i.get('@id') if len(i) > 1 else '')) for i in as_list(node['itemListElement']) if isinstance(i, dict))
            if urls - {''}:
                lists.append((node.get('@id', '/'.join(sorted(types(node)))), urls))
    for n in nodes:
        walk(n, collect_lists)
    for i in range(len(lists)):
        for j in range(i + 1, len(lists)):
            if lists[i][1] == lists[j][1]:
                err(f'یک فهرست دو بار: {lists[i][0]} و {lists[j][0]}')

    return errors


def main():
    if len(sys.argv) != 2:
        print(__doc__)
        return 2
    failed = 0
    files = sorted(glob.glob(os.path.join(sys.argv[1], '*.html')))
    for f in files:
        name = os.path.basename(f)[:-5]
        problems = check_page(name, open(f, encoding='utf-8').read())
        if problems:
            failed += 1
            print(f'  ✘ {name}')
            for p in dict.fromkeys(problems):
                print(f'      {p.replace("https://hodima.test", "")}')
    if not files:
        print('  ✘ صفحه‌ای پیدا نشد')
        return 1
    print(f'  {"✔" if not failed else "✘"} قواعد اسکیما: {len(files) - failed}/{len(files)} صفحه قبول')
    return 1 if failed else 0


if __name__ == '__main__':
    sys.exit(main())
