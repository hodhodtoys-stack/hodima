#!/usr/bin/env python3
"""
ساخت خط‌کش پیکسلی مشترک سئوباکس و دستار سئو (SEO 3.5.4).

ورودی: tools/fonts/Vazirmatn[wght].woff2 (وزیرمتن، فونت قالب هدیما)
خروجی:
  plugins/hodima-seo/core/seobox/assets/vazirmatn-ref.woff2   زیرمجموعه وزن ۴۰۰ برای اندازه‌گیری در مرورگر (canvas)
  plugins/hodima-seo/core/seobox/seobox-metrics-table.php     جدول عرض (واحد: یک‌ده‌هزارم em) برای PHP

چرا جدول چهارشکلی: عرض حرف فارسی به اتصال به حرف قبل و بعد بستگی دارد؛ جمع ساده عرض حرف‌ها ~۲۱٪ غلط بود،
جدول چهارشکلی (تنها/اول/وسط/آخر) + لیگاتور «لا» روی ۴۰ متن نمونه میانگین ۰٫۶٪ خطا دارد (بدون کرنینگ).
اجرا (از ریشه مخزن):  pip install fonttools brotli uharfbuzz && python3 tools/fonts/make-seo-metrics.py
"""
import io, sys, os
import uharfbuzz as hb
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont
from fontTools import subset

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
SRC = os.path.join(ROOT, 'tools/fonts/Vazirmatn[wght].woff2')
OUT_FONT = os.path.join(ROOT, 'plugins/hodima-seo/core/seobox/assets/vazirmatn-ref.woff2')
OUT_PHP = os.path.join(ROOT, 'plugins/hodima-seo/core/seobox/seobox-metrics-table.php')

font = instantiateVariableFont(TTFont(SRC), {'wght': 400})
font.flavor = None
buf = io.BytesIO(); font.save(buf)
data = buf.getvalue()

# ── زیرمجموعه برای مرورگر ──
opts = subset.Options(); opts.flavor = 'woff2'; opts.layout_features = ['*']; opts.notdef_outline = True
opts.name_IDs = [1, 2]; opts.drop_tables += ['DSIG']
sub = subset.Subsetter(opts)
sub.populate(unicodes=list(range(0x20, 0x7F)) + list(range(0xA0, 0x100)) + list(range(0x2000, 0x2070)) + list(range(0x600, 0x700))
             + list(range(0x750, 0x780)) + list(range(0xFB50, 0xFE00)) + list(range(0xFE70, 0xFF00)))
f2 = TTFont(io.BytesIO(data)); sub.subset(f2)
os.makedirs(os.path.dirname(OUT_FONT), exist_ok=True)
f2.flavor = 'woff2'; f2.save(OUT_FONT)

# ── جدول عرض ──
face = hb.Face(hb.Blob(data)); hbf = hb.Font(face); upm = face.upem
cmap = font.getBestCmap()

def adv(s):
    b = hb.Buffer(); b.add_str(s); b.guess_segment_properties()
    hb.shape(hbf, b, {'kern': False})
    return sum(p.x_advance for p in b.glyph_positions) / upm

ZWJ = '‍'
zw = adv(ZWJ)
unit = lambda v: int(round(v * 10000))

DUAL = 'بپتثجچحخسشصضطظعغفقکگلمنهیئكيۀہھ'
RIGHT = 'آاأإٱدذرزژوؤةۆۇ'
joined = {}
for c in DUAL + RIGHT:
    if ord(c) not in cmap: continue
    iso = adv(c); fin = adv(ZWJ + c) - zw
    if c in DUAL:
        ini = adv(c + ZWJ) - zw; med = adv(ZWJ + c + ZWJ) - 2 * zw
        joined[ord(c)] = [unit(iso), unit(ini), unit(med), unit(fin)]
    else:
        joined[ord(c)] = [unit(iso), unit(fin)]
lig = {}
for a in 'اآأإ':
    lig[ord(a)] = [unit(adv('ل' + a)), unit(adv(ZWJ + 'ل' + a) - zw)]

plain = {}
for cp in list(range(0x20, 0x7F)) + [0xA0] + list(range(0xA1, 0x100)) + list(range(0x2010, 0x2030)) + [0x2026, 0x2039, 0x203A] \
        + list(range(0x600, 0x700)) + [0x200C, 0x200D, 0x200E, 0x200F]:
    if cp in joined or cp not in cmap and cp not in (0x200C, 0x200D, 0x200E, 0x200F): continue
    ch = chr(cp)
    w = 0 if cp in (0x200C, 0x200D, 0x200E, 0x200F) else adv(ch)
    plain[cp] = unit(w)

def php_map(d):
    return '[ ' + ', '.join('0x%X => %s' % (k, ('[ ' + ', '.join(map(str, v)) + ' ]') if isinstance(v, list) else v) for k, v in sorted(d.items())) + ' ]'

with open(OUT_PHP, 'w', encoding='utf-8', newline='\n') as fh:
    fh.write("<?php\n/**\n * جدول عرض وزیرمتن ۴۰۰ (واحد: یک‌ده‌هزارم em). تولیدشده با tools/fonts/make-seo-metrics.py؛ دستی ویرایش نکن.\n"
             " * Path: core/seobox/seobox-metrics-table.php\n */\n\ndeclare(strict_types=1);\n\ndefined( 'ABSPATH' ) || exit;\n\n")
    fh.write("return [\n")
    fh.write("\t'plain'  => %s,\n" % php_map(plain))
    fh.write("\t// حرف چسبان: [تنها، اول، وسط، آخر] یا برای حرف‌های فقط‌راست‌چسب [تنها، آخر]\n")
    fh.write("\t'joined' => %s,\n" % php_map(joined))
    fh.write("\t'dual'   => %s,\n" % php_map({ord(c): 1 for c in DUAL if ord(c) in cmap}))
    fh.write("\t// لیگاتور «ل» + الف: [تنها، آخر]\n")
    fh.write("\t'lam_alef' => %s,\n" % php_map(lig))
    fh.write("\t'default' => %d,\n];\n" % unit(adv('ا') * 1.0 + 0.2))
print('font', os.path.getsize(OUT_FONT), 'bytes; plain', len(plain), 'joined', len(joined))
