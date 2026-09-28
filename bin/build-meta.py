#!/usr/bin/env python3
"""ساخت فایل‌های JSON به‌روزرسانی (Plugin Update Checker) در release/.

برای هر بسته (قالب و ۴ افزونه) از سربرگ فایل اصلی نسخه، نام و پیش‌نیازها را
می‌خواند و release/<slug>.json را می‌سازد. وردپرس سایت‌ها این JSON را از
گیت‌هاب می‌خواند و اگر version بزرگ‌تر از نسخه نصب‌شده باشد، به‌روزرسانی
را نشان می‌دهد. پس **هر انتشار باید شماره نسخه بسته تغییرکرده را بالا ببرد.**

آدرس پایه همان DEFAULT_BASE_URL در plugins/hodima-core/includes/updates.php
است؛ برای سرور دیگر: HODIMA_UPDATE_BASE_URL=... bin/build.sh
"""
import html
import json
import os
import re
import sys
from datetime import datetime, timezone

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
BASE = os.environ.get('HODIMA_UPDATE_BASE_URL', 'https://raw.githubusercontent.com/hodhodtoys-stack/hodima/HEAD/release/')
BASE = BASE if BASE.endswith('/') else BASE + '/'
REPO = 'https://github.com/hodhodtoys-stack/hodima'
TESTED = '6.7'  # آخرین نسخه وردپرس که با tools/wp-harness تست شده

PLUGINS = {
    'hodima-core': 'plugins/hodima-core/hodima-core.php',
    'hodima-seo': 'plugins/hodima-seo/hodima-seo.php',
    'hodima-commerce': 'plugins/hodima-commerce/hodima-commerce.php',
    'hodima-media': 'plugins/hodima-media/hodima-media.php',
}
THEME = ('hodima', 'hodima/style.css')


def headers(path):
    text = open(os.path.join(ROOT, path), encoding='utf-8').read(8192)
    out = {}
    for key in ('Plugin Name', 'Theme Name', 'Version', 'Requires at least', 'Requires PHP', 'Description', 'Author', 'Author URI'):
        m = re.search(r'^[ \t/*#@]*' + re.escape(key) + r':\s*(.+)$', text, re.M)
        if m:
            out[key] = m.group(1).strip().rstrip('*/').strip()
    if 'Version' not in out:
        sys.exit(f'Version header not found in {path}')
    return out


def changelog_html(slug):
    """بخش‌های CHANGELOG.md که به این بسته (یا «همه») مربوط‌اند، به HTML ساده."""
    path = os.path.join(ROOT, 'CHANGELOG.md')
    if not os.path.exists(path):
        return ''
    parts, in_list = [], False
    for line in open(path, encoding='utf-8'):
        line = line.rstrip()
        if line.startswith('## '):
            if in_list:
                parts.append('</ul>'); in_list = False
            elif parts and parts[-1].startswith('<h4>'):
                parts.pop()  # نسخه‌ای که هیچ خطی برای این بسته ندارد، عنوان خالی نگیرد
            parts.append(f'<h4>{html.escape(line[3:])}</h4>')
        elif line.startswith('- '):
            item = line[2:]
            tag = re.match(r'^\[([^\]]+)\]\s*', item)
            if tag and slug not in [t.strip() for t in tag.group(1).split(',')] and tag.group(1).strip() != 'همه':
                continue
            item = item[tag.end():] if tag else item
            if not in_list:
                parts.append('<ul>'); in_list = True
            parts.append(f'<li>{html.escape(item)}</li>')
    if in_list:
        parts.append('</ul>')
    return ''.join(parts)


def main():
    out_dir = os.path.join(ROOT, 'release')
    os.makedirs(out_dir, exist_ok=True)
    now = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')

    for slug, path in PLUGINS.items():
        h = headers(path)
        data = {
            'name': h.get('Plugin Name', slug),
            'slug': slug,
            'version': h['Version'],
            'download_url': BASE + slug + '.zip',
            'homepage': REPO,
            'requires': h.get('Requires at least', ''),
            'requires_php': h.get('Requires PHP', ''),
            'tested': TESTED,
            'last_updated': now,
            'author': h.get('Author', ''),
            'author_homepage': h.get('Author URI', ''),
            'sections': {
                'description': html.escape(h.get('Description', '')),
                'changelog': changelog_html(slug),
            },
        }
        write(out_dir, slug, data)

    slug, path = THEME
    h = headers(path)
    write(out_dir, slug, {
        'version': h['Version'],
        'details_url': REPO + '/blob/HEAD/CHANGELOG.md',
        'download_url': BASE + slug + '.zip',
    })


def write(out_dir, slug, data):
    with open(os.path.join(out_dir, slug + '.json'), 'w', encoding='utf-8', newline='\n') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
        f.write('\n')
    print(f'✔ release/{slug}.json  (version {data["version"]})')


if __name__ == '__main__':
    main()
