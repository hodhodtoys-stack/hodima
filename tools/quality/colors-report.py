#!/usr/bin/env python3
"""گزارش رنگ‌های ثابت CSS سایت (بخش ۱۶۰ HODIMA-AUDIT.md) — فقط گزارش است، رد نمی‌کند.

رنگ داخل fallback یک var(--x, رنگ) مجاز است و شمرده نمی‌شود. بقیه دسته‌بندی می‌شوند:
  A  هم‌ارز دقیق یک توکن ← باید با توکن نوشته شود (در حالت سالم ۰ است)
  B  سفید/سیاه (متن روی رنگ برند، پرده و سایه نیمه‌شفاف) ← معمولا عمدی
  C  بدون توکن (خاکستری یک‌بار‌مصرف، رنگ برند خارجی مثل واتس‌اپ، سایه‌ها) ← بررسی دستی
اجرا از ریشه مخزن: python3 tools/quality/colors-report.py [-v]
"""
import collections, glob, os, re, sys

root = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'hodima') + '/'
files = ['style.css'] + sorted(f[len(root):] for f in glob.glob(root + 'assets/css/*.css') if not f.endswith('tokens.css')) + ['home/home.css']

# توکن‌های tokens.css با مقدار ثابت هگز
TOK = {'#25316a': 'primary', '#607bbd': 'secondary', '#b6c2f3': 'third', '#6a2b9a': 'accent', '#a341c8': 'accent-light',
       '#1b244d': 'primary-dark', '#3b4d8f': 'gradient-mid', '#344483': 'gradient-deep', '#f6f8fc': 'bg-light',
       '#eef2f8': 'bg-gray', '#ffffff': 'surface', '#f7f9fd': 'surface-soft', '#111111': 'text-dark', '#5d6785': 'text-muted',
       '#444444': 'text-body', '#b8c4de': 'border', '#d9e1f0': 'border-light', '#2f7a55': 'state-success',
       '#8a6116': 'state-warning', '#a32a2a': 'state-danger', '#eaf3ee': 'state-success-bg', '#fbf4e6': 'state-warning-bg',
       '#f8ecec': 'state-danger-bg', '#eef1fb': 'state-info-bg'}
RGB = {'37, 49, 106': 'primary', '96, 123, 189': 'secondary', '182, 194, 243': 'third', '106, 43, 154': 'accent',
       '163, 65, 200': 'accent-light', '163, 42, 42': 'state-danger'}


def norm_hex(h):
    h = h.lower()
    if len(h) in (4, 5):
        h = '#' + ''.join(c * 2 for c in h[1:4])
    return h


rows = []
for f in files:
    src = open(root + f, encoding='utf-8').read()
    src = re.sub(r'/\*.*?\*/', lambda m: '\n' * m.group(0).count('\n'), src, flags=re.S)
    for i, line in enumerate(src.split('\n'), 1):
        for _ in range(4):
            line = re.sub(r'var\(\s*--[a-z0-9-]+\s*,(?:[^()]|\([^()]*\))*\)', 'var()', line)
        prop = re.match(r'^\s*([a-z-]+|--[a-z0-9-]+)\s*:', line)
        name = prop.group(1) if prop else '?'
        for m in re.finditer(r'#[0-9a-fA-F]{8}\b|#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3,4}\b', line):
            rows.append((f, i, name, m.group(0), norm_hex(m.group(0)), 'hex'))
        for m in re.finditer(r'rgba?\(\s*(\d+)[ ,]+(\d+)[ ,]+(\d+)([^)]*)\)', line):
            rows.append((f, i, name, m.group(0), f'{m.group(1)}, {m.group(2)}, {m.group(3)}', 'rgb'))
        # نام رنگ فقط در مقدار (نه نام متغیر مثل --clr-white)
        value = line.split(':', 1)[1] if prop else ''
        for m in re.finditer(r'(?<![\w-])(white|black)(?![\w-])', value):
            rows.append((f, i, name, m.group(0), m.group(0), 'named'))

cats = collections.defaultdict(list)
for r in rows:
    f, i, p, raw, n, k = r
    if k == 'hex' and n in TOK and n != '#ffffff':
        cats['A'].append(r)
    elif k == 'rgb' and n in RGB:
        cats['A'].append(r)
    elif (k == 'hex' and n in ('#ffffff', '#000000')) or k == 'named' or (k == 'rgb' and n in ('0, 0, 0', '255, 255, 255')):
        cats['B'].append(r)
    else:
        cats['C'].append(r)

print(f"رنگ ثابت (بیرون از fallback var): {len(rows)} — A هم‌ارز توکن: {len(cats['A'])} · B سفید/سیاه: {len(cats['B'])} · C بدون توکن: {len(cats['C'])}")
if '-v' in sys.argv:
    for c in 'ABC':
        print(f'\n== {c}')
        for f, i, p, raw, n, k in cats[c]:
            print(f'  {f}:{i}  {p}: {raw}')
# سفیدِ پس‌زمینه باید --hodima-surface باشد (A منطقی)
bad_bg = [r for r in cats['B'] if r[2] in ('background', 'background-color') and r[4] in ('#ffffff', 'white')
          and 'color-mix' not in open(root + r[0], encoding='utf-8').read().split('\n')[r[1] - 1]]
if bad_bg:
    print(f"\n! {len(bad_bg)} پس‌زمینه سفید ثابت (باید var(--hodima-surface)):")
    for f, i, p, raw, n, k in bad_bg:
        print(f'  {f}:{i}')
