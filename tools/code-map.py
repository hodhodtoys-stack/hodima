#!/usr/bin/env python3
"""نقشه ارتباط‌های بین‌بسته‌ای هدیما (قالب ↔ ۴ افزونه) از روی کد.

   python3 tools/code-map.py

خروجی را با .claude/skills/hodima-map/SKILL.md مقایسه کن؛ اگر ارتباطی اضافه یا
حذف شده، اسکیل را در همان commit به‌روز کن. فقط می‌خواند.

تشخیص با regex است (نه تحلیل کامل PHP): تابع سطح بالا و کلاس از تعریف،
فراخوانی از نام، فیلتر/اکشن از apply_filters/do_action در برابر add_filter/
add_action، گزینه از get_option/update_option، شورت‌کد از add_shortcode در
برابر «[نام» یا do_shortcode. متدهای کلاس (->name / ::name) کنار گذاشته می‌شوند.
"""
import collections
import os
import re

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
PACKAGES = {
    'theme': 'hodima',
    'core': 'plugins/hodima-core',
    'seo': 'plugins/hodima-seo',
    'commerce': 'plugins/hodima-commerce',
    'media': 'plugins/hodima-media',
}
# هوک‌ها و گزینه‌های خود وردپرس/ووکامرس که «ارتباط بین بسته‌ها» نیستند
WP_HOOKS = {'the_content', 'woocommerce_short_description'}
WP_OPTIONS = {'page_for_posts', 'page_on_front', 'show_on_front', 'custom_logo', 'rewrite_rules'}
# فقط توابع با پیشوند پروژه؛ نام‌های عمومی (enqueue_assets، status، …) تابع‌های
# namespace‌دار یا متد هم‌نام‌اند و ارتباط واقعی بین بسته‌ها نیستند
PREFIX = re.compile(r'^(?:hodima|hook|arian|seobox)_')


def load():
    files = {}
    for pkg, folder in PACKAGES.items():
        for dirpath, dirnames, filenames in os.walk(os.path.join(ROOT, folder)):
            dirnames[:] = [d for d in dirnames if d not in ('vendor', 'node_modules')]
            for name in filenames:
                if name.endswith(('.php', '.js')):
                    path = os.path.join(dirpath, name)
                    with open(path, encoding='utf-8', errors='replace') as f:
                        files[os.path.relpath(path, ROOT)] = (pkg, f.read())
    return files


def short(path):
    return path.removeprefix('plugins/')


def main():
    files = load()
    funcs, classes = collections.defaultdict(set), collections.defaultdict(set)
    for path, (pkg, text) in files.items():
        if not path.endswith('.php'):
            continue
        # فقط تابع سطح بالا (بدون تورفتگی یا داخل if ( ! function_exists ) با یک تورفتگی)
        for m in re.finditer(r'^(?:\t| {4})?function\s+&?(\w+)\s*\(', text, re.M):
            if PREFIX.match(m.group(1).lower()):
                funcs[m.group(1).lower()].add(pkg)
        for m in re.finditer(r'^\s*(?:final\s+|abstract\s+)?(?:class|enum|trait|interface)\s+(\w+)', text, re.M):
            classes[m.group(1).lower()].add(pkg)

    links = collections.defaultdict(lambda: collections.defaultdict(set))
    for path, (pkg, text) in files.items():
        for m in re.finditer(r"(?<![\w>:$\\])(\w+)\s*\(|function_exists\(\s*'(\w+)'", text):
            name = (m.group(1) or m.group(2)).lower()
            if name in funcs and pkg not in funcs[name]:
                for owner in funcs[name]:
                    links[(pkg, owner)][name + '()'].add(short(path))
        for m in re.finditer(r"\b(\w+)::|new\s+\\?(\w+)|class_exists\(\s*'\\?(\w+)", text):
            name = next(g for g in m.groups() if g).lower()
            if name in classes and pkg not in classes[name]:
                for owner in classes[name]:
                    links[(pkg, owner)]['class ' + name].add(short(path))

    print('# فراخوانی تابع/کلاس بین بسته‌ها (استفاده‌کننده ← صاحب)')
    for (user, owner), names in sorted(links.items()):
        print(f'\n## {user} ← {owner}')
        for name, paths in sorted(names.items()):
            print(f'  {name}: ' + '، '.join(sorted(paths)[:4]) + (' …' if len(paths) > 4 else ''))

    print('\n# تابع‌هایی که در چند بسته (با گارد function_exists) تعریف شده‌اند')
    for name, pkgs in sorted(funcs.items()):
        if len(pkgs) > 1:
            print(f'  {name}(): ' + '، '.join(sorted(pkgs)))

    fire, listen, opts = (collections.defaultdict(lambda: collections.defaultdict(set)) for _ in range(3))
    sc_def, sc_use = collections.defaultdict(set), collections.defaultdict(set)
    for path, (pkg, text) in files.items():
        if not path.endswith('.php'):
            continue
        for m in re.finditer(r"(?:apply_filters|do_action)(?:_ref_array)?\(\s*'([\w-]+)'", text):
            fire[m.group(1)][pkg].add(short(path))
        for m in re.finditer(r"(?:add|has|remove)_(?:filter|action)\(\s*'([\w-]+)'", text):
            listen[m.group(1)][pkg].add(short(path))
        for m in re.finditer(r"(?:get|update|delete|add)_option\(\s*'([\w-]+)'", text):
            opts[m.group(1)][pkg].add(short(path))
        for m in re.finditer(r"add_shortcode\(\s*['\"]([\w{}$-]+)['\"]", text):
            sc_def[m.group(1)].add(pkg)
        for m in re.finditer(r"\[([a-z][\w-]+)", text):
            sc_use[m.group(1)].add((pkg, short(path)))

    print('\n# فیلتر/اکشن‌های بین بسته‌ها (اجراکننده → شنونده)')
    for hook in sorted(fire):
        others = {p: v for p, v in listen.get(hook, {}).items() if p not in fire[hook]}
        if others:
            tag = ' (هوک وردپرس)' if hook in WP_HOOKS else ''
            print(f'  {hook}{tag}: ' + '، '.join(sorted(fire[hook])) + ' → '
                  + '؛ '.join(f'{p} ({"، ".join(sorted(v)[:2])})' for p, v in sorted(others.items())))

    print('\n# گزینه‌های (wp_options) مشترک بین بسته‌ها')
    for name in sorted(opts):
        if len(opts[name]) > 1 and name not in WP_OPTIONS:
            print(f'  {name}: ' + '؛ '.join(f'{p} ({"، ".join(sorted(v)[:2])})' for p, v in sorted(opts[name].items())))

    print('\n# شورت‌کدهایی که بسته دیگری استفاده می‌کند')
    for name in sorted(sc_def):
        pattern = re.compile('^' + re.sub(r'\\\{\\\$\w+\\\}', r'\\w+', re.escape(name)) + '$')
        users = sorted({(p, f) for tag, uses in sc_use.items() if pattern.match(tag) for p, f in uses if p not in sc_def[name]})
        if users:
            print(f'  [{name}] ({"، ".join(sorted(sc_def[name]))}) ← ' + '، '.join(f'{p}:{f}' for p, f in users[:4]))


if __name__ == '__main__':
    main()
