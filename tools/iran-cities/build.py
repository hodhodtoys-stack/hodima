#!/usr/bin/env python3
"""ساخت فهرست کامل شهرهای ایران برای ماژول «استان‌ها و شهرها» (Hodima Commerce).

خروجی: plugins/hodima-commerce/core/time-jalali/data/iran-cities.php
(آرایه PHP: کد استان هدیما ← [مرکز استان، بقیه به ترتیب الفبای فارسی]).

منبع‌ها (هر دو MIT؛ فقط هنگام ساخت لازم‌اند، در مخزن نیستند):
  ب) npm «iran» 1.0.2 — خزش سایت وزارت کشور (تقسیمات کشوری ۱۳۹۲): پایه و املای اصلی
  الف) npm «iran-cities» 0.0.5 — شهرهای تازه‌تر که در «ب» نیستند
دو منبع روی مجموعه شهرها یکی‌اند؛ تفاوت‌ها املایی است و در CORRECT تصمیمش ثبت شده.

  d=$(mktemp -d); (cd $d && npm pack iran iran-cities && for f in *.tgz; do mkdir ${f%.tgz}; tar xzf $f -C ${f%.tgz}; done)
  python3 -I tools/iran-cities/build.py "$d"/iran-1.0.2/package/dist/iran.json "$d"/iran-cities-0.0.5/package/json
"""
import collections
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'plugins/hodima-commerce/core/time-jalali/data/iran-cities.php'

# کد استان هدیما (همان کدهای ذخیره‌شده در سفارش‌ها؛ عوض نشوند) ← نام استان
PROVINCES = {
    'ALZ': 'البرز', 'ARD': 'اردبیل', 'BHR': 'بوشهر', 'CHB': 'چهارمحال و بختیاری',
    'EAZ': 'آذربایجان شرقی', 'FRS': 'فارس', 'GIL': 'گیلان', 'GLS': 'گلستان',
    'HDN': 'همدان', 'HRZ': 'هرمزگان', 'ILM': 'ایلام', 'ESF': 'اصفهان',
    'KRN': 'کرمان', 'KRH': 'کرمانشاه', 'KHZ': 'خوزستان', 'KBD': 'کهگیلویه و بویراحمد',
    'KRD': 'کردستان', 'LRS': 'لرستان', 'MKZ': 'مرکزی', 'MZN': 'مازندران',
    'NKH': 'خراسان شمالی', 'QZV': 'قزوین', 'QOM': 'قم', 'RKH': 'خراسان رضوی',
    'SMN': 'سمنان', 'SBN': 'سیستان و بلوچستان', 'SKH': 'خراسان جنوبی', 'THR': 'تهران',
    'WAZ': 'آذربایجان غربی', 'YZD': 'یزد', 'ZJN': 'زنجان',
}
CENTERS = {
    'ALZ': 'کرج', 'ARD': 'اردبیل', 'BHR': 'بوشهر', 'CHB': 'شهرکرد', 'EAZ': 'تبریز',
    'FRS': 'شیراز', 'GIL': 'رشت', 'GLS': 'گرگان', 'HDN': 'همدان', 'HRZ': 'بندرعباس',
    'ILM': 'ایلام', 'ESF': 'اصفهان', 'KRN': 'کرمان', 'KRH': 'کرمانشاه', 'KHZ': 'اهواز',
    'KBD': 'یاسوج', 'KRD': 'سنندج', 'LRS': 'خرم آباد', 'MKZ': 'اراک', 'MZN': 'ساری',
    'NKH': 'بجنورد', 'QZV': 'قزوین', 'QOM': 'قم', 'RKH': 'مشهد', 'SMN': 'سمنان',
    'SBN': 'زاهدان', 'SKH': 'بیرجند', 'THR': 'تهران', 'WAZ': 'ارومیه', 'YZD': 'یزد',
    'ZJN': 'زنجان',
}
# املای نادرست/قدیمی منبع ← نام درست (تفاوت‌های دو منبع، یکی‌یکی بررسی‌شده)
CORRECT = {
    'سگزی': 'سجزی', 'نظنز': 'نطنز', 'درچه پیاز': 'درچه', 'علویچه': 'علویجه', 'کمشجه': 'کمشچه',
    'بوئین میاندشت': 'بوئین و میاندشت', 'ارمخانخانه': 'ارمغانخانه', 'چاه بهار': 'چابهار',
    'مبارک آباد': 'مبارک آباد دیز', 'کوخردهرنگ': 'کوخرد', 'تازیان پائین': 'تازیان پایین',
    'سردشت لردگان': 'سردشت', 'مراوه تپه': 'مراوه', 'دستناء': 'دستنا', 'فرادبنه': 'فرادنبه',
    # املای منبع دوم برای شهری که در منبع اول هست
    'شهرجدیدهشتگرد': 'هشتگرد', 'کوت عبداله': 'کوت عبدالله', 'شهرک علی اکبر': 'علی اکبر',
    'شهرصدرا': 'صدرا', 'کرند': 'کرند غرب', 'بیکاء': 'بیکاه',
}
# نام رایجی که مشتری می‌نویسد (شهرستان یا نام قدیمی) ← فقط برای جستجو؛ مقدار ذخیره‌شده همان نام شهر است
ALIASES = {
    'ALZ': {'هشتگرد': 'ساوجبلاغ'}, 'FRS': {'لار': 'لارستان'}, 'KHZ': {'بندر ماهشهر': 'ماهشهر'},
    'GIL': {'هشتپر': 'تالش'}, 'HRZ': {'دهبارز': 'رودان'}, 'YZD': {'هرات': 'خاتم'},
    'MKZ': {'مامونیه': 'زرندیه'}, 'BHR': {'بندر کنگان': 'کنگان', 'بندر دیلم': 'دیلم'},
    'GLS': {'علی آباد': 'علی آباد کتول'},
}
ALPHABET = 'اآبپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی'


def norm(s: str) -> str:
    s = s.replace('ي', 'ی').replace('ك', 'ک').replace('‌', ' ')
    s = re.sub(r'\s+', ' ', s).strip()
    return re.sub(r'^شهر جدید ', '', s)


def key(s: str) -> str:
    """کلید مقایسه دو منبع: بی‌فاصله، بی‌همزه و بی‌پرانتز."""
    s = re.sub(r'\(.*?\)', '', norm(s))
    for a, b in (('آ', 'ا'), ('أ', 'ا'), ('ئ', 'ی'), ('ء', ''), ('ؤ', 'و'), ('ڈ', ''), (' ', '')):
        s = s.replace(a, b)
    return s


def sort_key(s: str):
    # «آ» هم‌رتبه «ا» (آران کنار اردستان، نه بعد از همه الف‌ها)
    return [ALPHABET.index('ا' if c == 'آ' else c) if c in ALPHABET else (-1 if c == ' ' else 99) for c in s], s


def main(moi_json: str, ic_dir: str) -> None:
    by_key = {key(v): k for k, v in PROVINCES.items()}
    cities = collections.defaultdict(dict)  # کد ← کلید ← نام
    for c in json.load(open(moi_json, encoding='utf-8')):
        code = by_key[key(c['province_name'])]
        name = norm(c['city_name'])
        name = CORRECT.get(name, name)
        cities[code].setdefault(key(name), name)
    ic_prov = {p['id']: by_key[key(p['name'])] for p in json.load(open(f'{ic_dir}/province.json', encoding='utf-8'))}
    added = []
    for c in json.load(open(f'{ic_dir}/city.json', encoding='utf-8')):
        code = ic_prov[c['province_id']]
        name = norm(c['name']).replace('اباد', 'آباد')
        name = CORRECT.get(name, name)
        if key(name) not in cities[code] and key(name) not in {key(k) for k in CORRECT}:
            cities[code][key(name)] = name
            added.append(f'{code}:{name}')
    out = {}
    for code in PROVINCES:
        names = sorted(set(cities[code].values()), key=sort_key)
        center = cities[code].get(key(CENTERS[code]))
        assert center, (code, CENTERS[code])
        out[code] = [center] + [n for n in names if n != center]
    total = sum(map(len, out.values()))
    lines = [
        '<?php',
        '/**',
        ' * فهرست کامل شهرهای ایران به تفکیک استان — ساخته‌شده با tools/iran-cities/build.py',
        ' * (تقسیمات کشوری وزارت کشور + شهرهای تازه‌تر). دستی ویرایش نکنید؛ اسکریپت را اجرا کنید.',
        ' * cities: کد استان هدیما (ALZ، ARD، …) ← شهرها؛ اولین شهر هر استان مرکز آن است.',
        ' * aliases: کد استان ← [شهر ← نام رایج دیگر]؛ فقط برای جستجوی فهرست.',
        ' *',
        f' * {len(out)} استان، {total} شهر.',
        ' */',
        '',
        '',
        'declare(strict_types=1);',
        '',
        "defined( 'ABSPATH' ) || exit;",
        '',
        'return [',
        "\t'cities'  => [",
    ]
    q = lambda n: "'" + n.replace("'", "\\'") + "'"
    for code, names in out.items():
        lines.append(f"\t\t'{code}' => [ " + ', '.join(map(q, names)) + ' ],')
    lines += ['\t],', "\t'aliases' => ["]
    for code, pairs in ALIASES.items():
        for city in pairs:
            assert city in out[code], (code, city)
        lines.append(f"\t\t'{code}' => [ " + ', '.join(f'{q(c)} => {q(a)}' for c, a in pairs.items()) + ' ],')
    lines += ['\t],', '];']
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text('\n'.join(lines) + '\n', encoding='utf-8')
    print(f'✔ {OUT.relative_to(ROOT)}: {len(out)} استان، {total} شهر؛ از منبع دوم: {", ".join(added)}')


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
