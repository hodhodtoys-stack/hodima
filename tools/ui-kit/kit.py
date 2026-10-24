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


if __name__ == '__main__':
    if len(sys.argv) == 3 and sys.argv[1] == 'install':
        install(sys.argv[2])
    else:
        sys.exit(__doc__)
