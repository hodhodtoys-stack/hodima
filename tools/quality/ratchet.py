#!/usr/bin/env python3
"""Ratchet (جغجغه) برای خطاهای قدیمی ابزارهای کیفیت کد.

کد فعلی صدها هشدار قدیمی دارد (مثلا ۷۳۵ !important). اگر ابزار با اولین هشدار
رد شود، یا باید همه را یک‌جا عوض کرد (خطرناک برای ظاهر سایت) یا ابزار را
خاموش کرد. اینجا شمار هشدارهای هر «فایل + قاعده» در یک baseline ثبت می‌شود و:
  - اگر شمار در جایی بیشتر شد  → رد (کد جدید باید تمیز باشد)؛
  - اگر کمتر شد                → قبول + پیشنهاد به‌روزرسانی baseline (جغجغه فقط پایین می‌رود).
شماره خط ذخیره نمی‌شود تا جابه‌جایی کد باعث هشدار اشتباه نشود.

  ratchet.py <phpcs|stylelint|eslint> <report.json> <baseline.json> [--update]
"""
import json
import os
import sys
from collections import Counter, defaultdict

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))


def rel(path: str) -> str:
    path = os.path.abspath(path)
    return os.path.relpath(path, ROOT) if path.startswith(ROOT + os.sep) else path


def load(tool: str, report_path: str):
    """{file: Counter(rule)} و فهرست پیام‌ها برای نمایش."""
    with open(report_path, encoding='utf-8') as fh:
        raw = fh.read().strip() or ('[]' if tool != 'phpcs' else '{"files": {}}')
    data = json.loads(raw)
    counts, messages = defaultdict(Counter), defaultdict(list)

    if tool == 'phpcs':
        for path, info in data.get('files', {}).items():
            for m in info.get('messages', []):
                rule = m.get('source') or 'unknown'
                counts[rel(path)][rule] += 1
                messages[(rel(path), rule)].append((m.get('line', 0), m.get('message', '')))
    elif tool == 'stylelint':
        for item in data:
            path = rel(item.get('source', ''))
            for w in item.get('warnings', []):
                rule = w.get('rule') or 'syntax'
                counts[path][rule] += 1
                messages[(path, rule)].append((w.get('line', 0), w.get('text', '')))
            for d in item.get('deprecations', []) + item.get('invalidOptionWarnings', []):
                counts[path]['config'] += 1
                messages[(path, 'config')].append((0, d.get('text', '')))
    elif tool == 'eslint':
        for item in data:
            path = rel(item.get('filePath', ''))
            for m in item.get('messages', []):
                rule = m.get('ruleId') or 'syntax'
                counts[path][rule] += 1
                messages[(path, rule)].append((m.get('line', 0), m.get('message', '')))
    else:
        sys.exit(f'ابزار ناشناخته: {tool}')
    return counts, messages


def main() -> int:
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    update = '--update' in sys.argv
    if len(args) != 3:
        print(__doc__)
        return 2
    tool, report, baseline_path = args
    counts, messages = load(tool, report)

    total = sum(sum(c.values()) for c in counts.values())
    if update:
        out = {f: dict(sorted(c.items())) for f, c in sorted(counts.items()) if c}
        with open(baseline_path, 'w', encoding='utf-8') as fh:
            json.dump(out, fh, ensure_ascii=False, indent='\t', sort_keys=True)
            fh.write('\n')
        print(f'  {tool}: baseline به‌روز شد ({total} مورد قدیمی ثبت شد)')
        return 0

    try:
        with open(baseline_path, encoding='utf-8') as fh:
            baseline = json.load(fh)
    except FileNotFoundError:
        baseline = {}

    worse, better = [], 0
    for path, rules in counts.items():
        for rule, n in rules.items():
            old = int(baseline.get(path, {}).get(rule, 0))
            if n > old:
                worse.append((path, rule, old, n))
            elif n < old:
                better += old - n
    for path, rules in baseline.items():
        for rule, old in rules.items():
            if rule not in counts.get(path, {}):
                better += int(old)

    if worse:
        print(f'  ✘ {tool}: خطای جدید (بیشتر از baseline):')
        for path, rule, old, n in sorted(worse):
            print(f'    {path}  [{rule}]  {old} → {n}')
            for line, msg in sorted(messages[(path, rule)])[:8]:
                print(f'        خط {line}: {msg}')
        return 1

    note = f'؛ {better} مورد کمتر از baseline — bash bin/lint.sh --update-baseline' if better else ''
    print(f'  ✔ {tool}: بدون خطای جدید ({total} مورد قدیمی{note})')
    return 0


if __name__ == '__main__':
    sys.exit(main())
