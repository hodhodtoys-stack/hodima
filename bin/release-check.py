#!/usr/bin/env python3
"""بررسی خودکار انتشار هدیما (اسکیل /release — .claude/skills/release/SKILL.md).

   python3 bin/release-check.py plan   کدام بسته‌ها تغییر کرده‌اند و نسخه بعدی پیشنهادی
   python3 bin/release-check.py        (= pre) پیش از commit: همه قانون‌های انتشار
   python3 bin/release-check.py post   بعد از push: چیزی جا نمانده، release/ گیت‌هاب = محلی

پایه مقایسه: origin/claude/hodima (همان چیزی که سایت‌ها الان می‌بینند)؛ با
`--base REF` عوض می‌شود. «تغییر بسته» = هر فایل زیر hodima/ یا plugins/<بسته>/
که با پایه فرق دارد (commit‌شده، commit‌نشده یا تازه).

خروجی: ✔ درست، ! هشدار (تصمیم با انسان)، ✘ خطا (کد خروج ۱ — انتشار نکن).
فقط می‌خواند؛ هیچ فایلی را عوض نمی‌کند.
"""
import json
import os
import re
import subprocess
import sys
import zipfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
BRANCH = 'claude/hodima'
REPO_URL = 'https://github.com/hodhodtoys-stack/hodima'

# نامک → (پوشه، فایل سربرگ، ثابت نسخه یا None، برچسب عنوان CHANGELOG)
PACKAGES = {
    'hodima': ('hodima', 'hodima/style.css', None, 'قالب'),
    'hodima-core': ('plugins/hodima-core', 'plugins/hodima-core/hodima-core.php', 'HODIMA_CORE_PLUGIN_VERSION', 'Hodima Core'),
    'hodima-seo': ('plugins/hodima-seo', 'plugins/hodima-seo/hodima-seo.php', 'HODIMA_SEO_VERSION', 'Hodima SEO'),
    'hodima-commerce': ('plugins/hodima-commerce', 'plugins/hodima-commerce/hodima-commerce.php', 'HODIMA_COMMERCE_VERSION', 'Hodima Commerce'),
    'hodima-media': ('plugins/hodima-media', 'plugins/hodima-media/hodima-media.php', 'HODIMA_MEDIA_VERSION', 'Hodima Media'),
}
FA_DIGITS = str.maketrans('0123456789', '۰۱۲۳۴۵۶۷۸۹')

errors, warnings = [], []


def ok(msg):
    print(f'  ✔ {msg}')


def warn(msg):
    warnings.append(msg)
    print(f'  ! {msg}')


def fail(msg):
    errors.append(msg)
    print(f'  ✘ {msg}')


def git(*args, check=True):
    r = subprocess.run(['git', *args], cwd=ROOT, capture_output=True)
    if check and r.returncode != 0:
        sys.exit(f'git {" ".join(args)}: {r.stderr.decode("utf-8", "replace").strip()}')
    return r.stdout.decode('utf-8', 'replace')


def git_show(ref, path):
    r = subprocess.run(['git', 'show', f'{ref}:{path}'], cwd=ROOT, capture_output=True)
    return r.stdout.decode('utf-8', 'replace') if r.returncode == 0 else None


def read(path):
    with open(os.path.join(ROOT, path), encoding='utf-8') as f:
        return f.read()


def header_version(text):
    m = re.search(r'^[ \t/*#@]*Version:\s*([0-9][0-9.]*)', text or '', re.M)
    return m.group(1) if m else None


def const_version(text, const):
    m = re.search(r'\b(?:const\s+' + const + r"\s*=|define\(\s*'" + const + r"'\s*,)\s*'([^']+)'", text or '')
    return m.group(1) if m else None


def vtuple(v):
    return tuple(int(x) for x in v.split('.'))


def fits_rule(v):
    """شماره در قاعده هست؟ سه رقم و رقم دوم و سوم هر کدام ۰ تا ۹ (مثلا 1.17.0 نه)."""
    parts = v.split('.')
    return len(parts) == 3 and all(p.isdigit() for p in parts) and int(parts[1]) <= 9 and int(parts[2]) <= 9


def next_version(v):
    """قانون ۷ CLAUDE.md برای همه بسته‌ها (قالب و افزونه‌ها): فقط یک پله.

    رقم سوم +۱؛ بعد از ۹ رقم دوم +۱ و سوم ۰؛ بعد از x.9.9 رقم اول +۱ و بقیه ۰
    (3.0.9 ← 3.1.0، 3.9.9 ← 4.0.0 ← 4.0.1). None فقط وقتی شماره منتشرشده خودش
    خارج از قاعده است (مثل SEO 1.17.0 که به خواست کاربر 2.1.1 شد): کاربر تعیین می‌کند.
    """
    if not fits_rule(v):
        return None
    a, b, c = vtuple(v)
    if c < 9:
        return f'{a}.{b}.{c + 1}'
    if b < 9:
        return f'{a}.{b + 1}.0'
    return f'{a + 1}.0.0'


def ask_hint(v):
    """متن «بپرس» برای شماره منتشرشده خارج از قاعده (next_version() = None)."""
    return f'{v} خارج از قاعده است؛ شماره تازه را کاربر تعیین می‌کند (بپرس)'


def changed_files(base):
    files = set(git('diff', '--name-only', base).split('\n'))
    files |= set(git('ls-files', '--others', '--exclude-standard').split('\n'))
    return {f for f in files if f}


def changed_packages(files):
    out = {}
    for slug, (folder, *_rest) in PACKAGES.items():
        hits = sorted(f for f in files if f.startswith(folder + '/'))
        if hits:
            out[slug] = hits
    return out


def versions(slug, base):
    folder, head_file, const, _label = PACKAGES[slug]
    now = read(head_file)
    old = git_show(base, head_file)
    return header_version(old), header_version(now), (const_version(now, const) if const else None)


def resolve_base(args):
    base = None
    if '--base' in args:
        i = args.index('--base')
        base = args[i + 1] if i + 1 < len(args) else None
    if not base:
        base = 'origin/' + BRANCH
    if subprocess.run(['git', 'rev-parse', '--verify', '-q', base], cwd=ROOT, capture_output=True).returncode != 0:
        sys.exit(f'پایه «{base}» پیدا نشد (اول: git fetch origin {BRANCH})')
    return base


# ---------------------------------------------------------------- plan

def cmd_plan(base):
    files = changed_files(base)
    pkgs = changed_packages(files)
    print(f'پایه: {base} ({git("rev-parse", "--short", base).strip()})')
    if not pkgs:
        print('هیچ بسته‌ای (قالب/افزونه) تغییر نکرده: نسخه، CHANGELOG و bin/build.sh لازم نیست.')
        other = sorted(files)
        if other:
            print('فایل‌های تغییرکرده (فقط مخزن/ابزار):', *other[:30], sep='\n  ')
        return
    for slug, hits in pkgs.items():
        old, now, _c = versions(slug, base)
        nxt = next_version(old) or ask_hint(old)
        state = f'الان {now}' + (' (بالا رفته)' if old and now and vtuple(now) > vtuple(old) else '')
        print(f'- {slug}: {len(hits)} فایل؛ نسخه منتشرشده {old} ← پیشنهاد {nxt}؛ {state}')
        for f in hits[:8]:
            print(f'    {f}')
        if len(hits) > 8:
            print(f'    … و {len(hits) - 8} فایل دیگر')


# ---------------------------------------------------------------- pre

def check_env():
    print('== محیط')
    r = subprocess.run(['php', '-r', 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'], capture_output=True, text=True)
    if r.stdout.strip() == '8.4':
        ok('PHP 8.4')
    else:
        fail(f'PHP {r.stdout.strip() or "?"} (نه 8.4) — روش نصب: HODIMA-AUDIT.md بخش ۳۵')
    for path, hint in (('vendor/bin/phpcs', 'bash tools/quality/install-offline.sh'), ('node_modules/lightningcss', 'npm ci')):
        if os.path.exists(os.path.join(ROOT, path)):
            ok(f'{path} نصب است')
        else:
            fail(f'{path} نیست — اول: {hint}')
    branch = git('rev-parse', '--abbrev-ref', 'HEAD').strip()
    if branch == BRANCH:
        ok(f'شاخه {BRANCH}')
    else:
        fail(f'شاخه فعلی {branch} است؛ انتشار فقط از {BRANCH} (git checkout -B {BRANCH} origin/{BRANCH})')
    behind = git('rev-list', '--count', f'HEAD..origin/{BRANCH}', check=False).strip()
    if behind and behind != '0':
        fail(f'{behind} commit تازه در گیت‌هاب هست که اینجا نیست: git pull --no-rebase origin {BRANCH}، بعد دوباره تست')


def check_versions(base, pkgs):
    print('== نسخه‌ها')
    bumped = {}
    for slug in PACKAGES:
        old, now, const = versions(slug, base)
        folder, head_file, const_name, _label = PACKAGES[slug]
        if not now:
            fail(f'{slug}: سربرگ Version در {head_file} پیدا نشد')
            continue
        if const_name and const != now:
            fail(f'{slug}: سربرگ Version {now} ≠ ثابت {const_name} {const} ({head_file})')
        is_bumped = bool(old) and vtuple(now) > vtuple(old)
        if old and vtuple(now) < vtuple(old):
            fail(f'{slug}: نسخه پایین آمده ({old} ← {now})')
        if slug in pkgs:
            if not is_bumped:
                fail(f'{slug}: {len(pkgs[slug])} فایل تغییر کرده ولی نسخه همان {now} است — سایت‌ها به‌روزرسانی را نمی‌بینند')
                continue
            nxt = next_version(old)
            if not fits_rule(now):
                fail(f'{slug} {old} ← {now}: شماره تازه باید در قاعده باشد (رقم دوم و سوم ۰ تا ۹)')
            elif nxt is None:
                warn(f'{slug} {old} ← {now}: {ask_hint(old)} — مطمئن شو کاربر همین را گفته')
            elif now != nxt:
                fail(f'{slug} {old} ← {now}: قاعده «یک پله» — باید {nxt} باشد')
            else:
                ok(f'{slug} {old} ← {now} (یک پله)')
            bumped[slug] = (old, now)
        elif is_bumped:
            warn(f'{slug}: نسخه بالا رفته ({old} ← {now}) ولی هیچ فایلی از بسته تغییر نکرده')
            bumped[slug] = (old, now)
    if not pkgs:
        ok('هیچ بسته‌ای تغییر نکرده؛ نسخه لازم نیست')
    return bumped


def changelog_sections():
    """[(عنوان، [خط‌های فهرست])] به ترتیب فایل."""
    sections, cur = [], None
    for line in read('CHANGELOG.md').splitlines():
        if line.startswith('## '):
            cur = (line[3:].strip(), [])
            sections.append(cur)
        elif cur and line.startswith('- '):
            cur[1].append(line[2:])
    return sections


def applies(item, slug):
    tag = re.match(r'^\[([^\]]+)\]', item)
    if not tag:
        return True
    tags = [t.strip() for t in tag.group(1).split(',')]
    return slug in tags or 'همه' in tags


def check_changelog(bumped):
    print('== CHANGELOG.md')
    if not bumped:
        ok('نسخه‌ای بالا نرفته؛ ورودی تازه لازم نیست')
        return
    sections = changelog_sections()
    for slug, (_old, now) in bumped.items():
        label = PACKAGES[slug][3]
        want = f'{now.translate(FA_DIGITS)} ({label})'
        idx = next((i for i, (title, _items) in enumerate(sections) if want in title), None)
        if idx is None:
            fail(f'{slug}: عنوانی با «{want}» نیست (مثلا «## {want}»؛ ارقام فارسی)')
            continue
        items = [it for it in sections[idx][1] if applies(it, slug)]
        if not items:
            fail(f'{slug}: زیر «{sections[idx][0]}» هیچ خطی با [{slug}] (یا [همه]) نیست — پنجره جزئیات سایت خالی می‌ماند')
        elif idx > len(bumped) + 1:
            warn(f'{slug}: عنوان «{sections[idx][0]}» بالای فایل نیست (ردیف {idx + 1}) — نسخه تازه اول بیاید')
        else:
            ok(f'{slug}: «{sections[idx][0]}» با {len(items)} خط')


def zip_entries(path):
    with zipfile.ZipFile(path) as z:
        return {i.filename: z.read(i.filename) for i in z.infolist() if not i.is_dir()}


def source_entries(folder):
    """همان فایل‌هایی که bin/build.sh در ZIP می‌گذارد (بدون .DS_Store و .git*)."""
    parent, name = os.path.split(folder)
    return source_entries_at(os.path.join(ROOT, parent), name)


def source_entries_at(parent, name):
    out = {}
    for dirpath, dirnames, filenames in os.walk(os.path.join(parent, name)):
        dirnames[:] = [d for d in dirnames if not d.startswith('.git')]
        for name in filenames:
            if name == '.DS_Store' or name.startswith('.git'):
                continue
            full = os.path.join(dirpath, name)
            rel = os.path.relpath(full, parent).replace(os.sep, '/')
            with open(full, 'rb') as f:
                out[rel] = f.read()
    return out


def built_theme_entries():
    """فایل‌های قالب همان‌طور که bin/build.sh در ZIP می‌گذارد: کپی موقت + bin/build-css.mjs
    (CSS پایین‌آورده/minify و hodima-common.css؛ کمتر از یک ثانیه). بدون node: None."""
    import shutil
    import tempfile
    if not os.path.isdir(os.path.join(ROOT, 'node_modules', 'lightningcss')):
        return None
    tmp = tempfile.mkdtemp(prefix='hodima-release-check-')
    try:
        shutil.copytree(os.path.join(ROOT, 'hodima'), os.path.join(tmp, 'hodima'))
        r = subprocess.run(['node', 'bin/build-css.mjs', os.path.join(tmp, 'hodima')], cwd=ROOT, capture_output=True, text=True)
        if r.returncode != 0:
            fail('ساخت CSS قالب رد شد: ' + (r.stderr or r.stdout).strip()[:300])
            return None
        return source_entries_at(tmp, 'hodima')
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


def check_release(base, pkgs, bumped):
    print('== release/ (ZIP و JSON به‌روزرسانی)')
    before = len(errors)
    for slug, (folder, head_file, _c, _l) in PACKAGES.items():
        now = header_version(read(head_file))
        zpath = os.path.join(ROOT, 'release', slug + '.zip')
        jpath = os.path.join(ROOT, 'release', slug + '.json')
        if not os.path.exists(zpath) or not os.path.exists(jpath):
            fail(f'{slug}: release/{slug}.zip یا .json نیست — bash bin/build.sh')
            continue
        try:
            jver = json.load(open(jpath, encoding='utf-8')).get('version')
        except ValueError:
            fail(f'release/{slug}.json خراب است — bash bin/build.sh')
            continue
        if jver != now:
            fail(f'{slug}: release/{slug}.json نسخه {jver} دارد، کد {now} — bash bin/build.sh')
        is_theme = slug == 'hodima'
        src = built_theme_entries() if is_theme else source_entries(folder)
        loose = src is None  # بدون node: CSS قالب مقایسه نمی‌شود
        if loose:
            src = source_entries(folder)
            warn('node_modules نیست: CSS داخل ZIP قالب بررسی نشد (npm ci)')
        zipped = zip_entries(zpath)
        stale = []
        for rel, data in src.items():
            if rel not in zipped:
                stale.append(f'نیست: {rel}')
            elif not (loose and rel.endswith('.css')) and zipped[rel] != data:
                stale.append(f'قدیمی: {rel}')
        for rel in zipped:
            if rel not in src and not (loose and rel.endswith('.css')):
                stale.append(f'اضافه: {rel}')
        if is_theme and 'hodima/style.css' in zipped and header_version(zipped['hodima/style.css'].decode('utf-8', 'replace')) != now:
            stale.append('نسخه style.css داخل ZIP')
        if stale:
            fail(f'{slug}: ZIP با کد فعلی یکی نیست ({len(stale)} مورد، مثلا {stale[0]}) — bash bin/build.sh')
        elif slug in pkgs or slug in bumped:
            ok(f'{slug}: ZIP و JSON با کد فعلی ({now}) یکی‌اند')
        # تغییر بی‌دلیل فایل‌های انتشار (فقط زمان ساخت عوض شده) = سر و صدای diff
        for ext in ('zip', 'json'):
            rel = f'release/{slug}.{ext}'
            if slug not in bumped and subprocess.run(['git', 'diff', '--quiet', base, '--', rel], cwd=ROOT).returncode != 0:
                warn(f'{rel} عوض شده ولی نسخه {slug} همان است — git checkout {base} -- {rel}')
    if len(errors) == before:
        ok('ZIP و JSON همه بسته‌ها با کد فعلی یکی‌اند')


def check_eol():
    print('== پایان خطوط')
    normal = git('diff', 'HEAD', '--numstat')
    ignore = git('diff', 'HEAD', '--numstat', '--ignore-cr-at-eol')
    if normal == ignore:
        ok('فقط خطوط واقعا تغییرکرده در diff‌اند')
        return
    a = {l.split('\t')[2]: l for l in normal.splitlines() if l.count('\t') >= 2}
    b = {l.split('\t')[2]: l for l in ignore.splitlines() if l.count('\t') >= 2}
    bad = sorted(f for f in a if a[f] != b.get(f) and not f.endswith(('.zip', '.png', '.jpg', '.woff2')))
    if bad:
        fail('پایان خط عوض شده (diff کل فایل) در: ' + '، '.join(bad[:6]) + ' — python3 tools/eol.py')
    else:
        ok('فقط خطوط واقعا تغییرکرده در diff‌اند')


def check_docs(base, files, pkgs, bumped):
    print('== مستندات')
    # CLAUDE.md در هر گفتگو کامل خوانده می‌شود؛ قانون جزئی جایش اسکیل‌های .claude/skills/ است (بخش ۷۱)
    size = len(read('CLAUDE.md'))
    if size > 15000:
        warn(f'CLAUDE.md {size} حرف شده (حد ۱۵۰۰۰) — قانون جزئی را به اسکیل همان بخش ببر (hodima-theme / hodima-plugins)')
    else:
        ok(f'CLAUDE.md کوتاه است ({size} حرف)')
    heads = [l for l in read('HODIMA-AUDIT.md').splitlines() if l.startswith('## ')]
    if heads and 'پیوست' in heads[-1]:
        ok('HODIMA-AUDIT.md: پیوست آخرین بخش است')
    else:
        fail('HODIMA-AUDIT.md: آخرین بخش باید «پیوست» باشد (بخش تازه قبل از پیوست)')
    nums = [l.split('.')[0][3:].translate(str.maketrans('۰۱۲۳۴۵۶۷۸۹', '0123456789')) for l in heads]
    nums = [int(n) for n in nums if n.isdigit()]
    if nums and nums != sorted(nums):
        fail('HODIMA-AUDIT.md: شماره بخش‌ها به ترتیب نیست')
    if pkgs and 'HODIMA-AUDIT.md' not in files:
        warn('بسته‌ها تغییر کرده‌اند ولی HODIMA-AUDIT.md نه — بخش تازه «## N. … — مرحله M» لازم است')
    if pkgs and 'CHANGELOG.md' not in files:
        fail('بسته‌ها تغییر کرده‌اند ولی CHANGELOG.md نه')
    # کمترین نسخه افزونه‌ها در قالب (اعلان پیشخوان)
    plugin_bumps = [s for s in bumped if s != 'hodima']
    if 'hodima' in pkgs and plugin_bumps:
        warn('قالب و افزونه با هم تغییر کرده‌اند: اگر قالب به امکان تازه افزونه نیاز دارد، '
             'کمترین نسخه را در $required در hodima/functions.php بالا ببر (' + '، '.join(plugin_bumps) + ')')
    php_changed = [f for f in files if f.endswith('.php') and f.split('/')[0] in ('hodima', 'plugins')]
    toplevel = []
    for f in php_changed:
        d = subprocess.run(['git', 'diff', base, '--', f], cwd=ROOT, capture_output=True, text=True).stdout
        if re.search(r'^[+-](?:function |final class |class |enum |interface |trait )', d, re.M):
            toplevel.append(f)
    if toplevel:
        warn('تعریف تابع/کلاس سطح بالا عوض شده (' + '، '.join(toplevel[:4]) + ') — '
             'bash bin/build.sh && tools/wp-harness/zip-install-check.sh (هر دو ترتیب فعال‌سازی)')


def cmd_pre(base):
    files = changed_files(base)
    pkgs = changed_packages(files)
    print(f'پایه: {base}؛ بسته‌های تغییرکرده: ' + ('، '.join(pkgs) or 'هیچ'))
    check_env()
    bumped = check_versions(base, pkgs)
    check_changelog(bumped)
    check_release(base, pkgs, bumped)
    check_eol()
    check_docs(base, files, pkgs, bumped)
    untracked = [f for f in git('ls-files', '--others', '--exclude-standard').split('\n') if f]
    if untracked:
        warn('فایل تازه (git add را فراموش نکن): ' + '، '.join(untracked[:8]))


# ---------------------------------------------------------------- post

def cmd_post():
    print('== بعد از push')
    git('fetch', '-q', 'origin', BRANCH)
    head = git('rev-parse', 'HEAD').strip()
    remote = git('rev-parse', 'origin/' + BRANCH).strip()
    if head == remote:
        ok(f'HEAD = origin/{BRANCH} ({head[:7]})')
    else:
        ahead = git('rev-list', '--count', f'origin/{BRANCH}..HEAD').strip()
        fail(f'{ahead} commit push نشده — git push origin {BRANCH}')
    dirty = [l for l in git('status', '--porcelain', '--untracked-files=no').splitlines() if l]
    if dirty:
        fail('تغییر commit‌نشده مانده: ' + '، '.join(l[3:] for l in dirty[:6]))
    else:
        ok('تغییر commit‌نشده‌ای نمانده')
    for slug in PACKAGES:
        rel = f'release/{slug}.json'
        local = json.load(open(os.path.join(ROOT, rel), encoding='utf-8')).get('version')
        on_github = git_show('origin/' + BRANCH, rel)
        remote_ver = json.loads(on_github).get('version') if on_github else None
        if local != remote_ver:
            fail(f'{rel}: گیت‌هاب {remote_ver}، محلی {local}')
    if not errors:
        ok('release/*.json گیت‌هاب = محلی (نسخه‌ای که سایت‌ها می‌بینند)')
    print(f'  commit: {REPO_URL}/commit/{head}')
    print(f'  بررسی خودکار (Quality): {REPO_URL}/actions/workflows/quality.yml')


def main():
    args = sys.argv[1:]
    cmd = next((a for a in args if a in ('plan', 'pre', 'post')), 'pre')
    if cmd == 'post':
        cmd_post()
    else:
        base = resolve_base(args)
        if cmd == 'plan':
            cmd_plan(base)
            return
        cmd_pre(base)
    print()
    if errors:
        print(f'✘ {len(errors)} خطا، {len(warnings)} هشدار — انتشار نکن تا خطاها رفع شوند.')
        sys.exit(1)
    print(f'✔ همه بررسی‌ها قبول' + (f' ({len(warnings)} هشدار — بالا را بخوان)' if warnings else ''))


if __name__ == '__main__':
    main()
