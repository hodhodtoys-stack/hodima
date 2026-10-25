#!/usr/bin/env python3
"""کیت UI مشترک پیشخوان: نوشتن کپی هر بسته با پیشوند همان بسته (docs/admin-redesign-plan.md Z2).

  python3 tools/ui-kit/kit.py install <پوشه که hodima/ و plugins/ زیرش است>

منبع: tools/ui-kit/ui-kit.{css,js} با پیشوند «hdk». خروجی هر بسته: <بسته>/ui/ui-kit.{css,js}.
release-check.py همین render() را برای سنجش یکسانی کپی‌ها صدا می‌زند.
"""
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
SOURCE_PREFIX = 'hdk'
FILES = ('ui-kit.css', 'ui-kit.js')
# مسیر بسته (نسبت به ریشه ساخت) → پیشوند کلاس. Core جزء خودش را دارد (hd-…) و کیت نمی‌گیرد
PACKAGES = {
    'hodima': 'hdt',
    'plugins/hodima-seo': 'hds',
    'plugins/hodima-commerce': 'hdc',
    'plugins/hodima-media': 'hdm',
}
_TOKEN = re.compile(r'\b' + SOURCE_PREFIX + r'(?=[-:A-Z])')


def targets():
    return dict(PACKAGES)


# کپی استایل/رفتار مشترک Core (plugins/hodima-core/assets/admin-ui.*) در افزونه‌هایی که
# بدون Core هم باید صفحه‌هایشان کامل و استایل‌دار باشند. فایل‌ها بدون تغییر پیشوند کپی
# می‌شوند (فقط وقتی Core نیست لود می‌شوند: inc/admin-ui-fallback.php)؛ منبع همان Core است.
CORE_SOURCE = 'plugins/hodima-core/assets'
CORE_COPIES = {'admin-ui.css': 'core-ui.css', 'admin-ui.js': 'core-ui.js'}
CORE_TARGETS = ('plugins/hodima-seo', 'plugins/hodima-commerce', 'plugins/hodima-media')


def render(name, prefix):
    with open(os.path.join(HERE, name), encoding='utf-8', newline='') as f:
        text = f.read()
    return _TOKEN.sub(prefix, text).encode('utf-8')


def install(root):
    for pkg, prefix in targets().items():
        folder = os.path.join(root, pkg, 'ui')
        os.makedirs(folder, exist_ok=True)
        for name in FILES:
            with open(os.path.join(folder, name), 'wb') as f:
                f.write(render(name, prefix))
        print(f'✔ {pkg}/ui (پیشوند {prefix}-)')
    for src_name, dst_name in CORE_COPIES.items():
        src = os.path.join(root, CORE_SOURCE, src_name)
        if not os.path.isfile(src):
            continue
        with open(src, 'rb') as f:
            data = f.read()
        for pkg in CORE_TARGETS:
            folder = os.path.join(root, pkg, 'ui')
            os.makedirs(folder, exist_ok=True)
            with open(os.path.join(folder, dst_name), 'wb') as f:
                f.write(data)
    print('✔ کپی استایل مشترک Core در افزونه‌ها (ui/core-ui.*)')


if __name__ == '__main__':
    if len(sys.argv) == 3 and sys.argv[1] == 'install':
        install(sys.argv[2])
    else:
        sys.exit(__doc__)
