#!/usr/bin/env bash
# مقایسه استایل محاسبه‌شده عنصرهای نمونه (important-audit-inject.html) قبل/بعد، با حالت‌های hover/focus/active اجباری.
#   important-compare.sh [git-ref] [page ...]     (پیش‌فرض HEAD = کار commit‌نشده در برابر آخرین commit)
# برای تغییر !important عنصرهایی که در داده آزمایشی نیستند (پاپ‌آپ هدر، ویدیوی embed، فرم دیدگاه…):
# همان نمونه‌ها به همه صفحه‌های هر دو طرف تزریق می‌شوند. هر تفاوت باید عمدی باشد.
# HODIMA_COMPARE_CSS: فایل‌های CSS اضافه (پیش‌فرض همه CSSهای صفحه‌ای قالب) تا قانون‌های عنصر نمونه لود شوند.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"
ref="${1:-HEAD}"; shift || true
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
git -C "$repo" worktree remove --force "$base/vref" >/dev/null 2>&1 || true
git -C "$repo" worktree add --detach -f "$base/vref" "$ref" >/dev/null
"$here/run.sh" "$base/vref" "$base/vout-ref" >/dev/null
"$here/run.sh" "$repo" "$base/vout-work" >/dev/null
css="${HODIMA_COMPARE_CSS:-home/home.css,assets/css/header.css,assets/css/footer.css,assets/css/cart-page.css,assets/css/taxonomy-product_cat.css,assets/css/single-product.css,assets/css/product-card.css,assets/css/template-page-videos.css}"
python3 - "$base" "$here/important-audit-inject.html" "$css" <<'PY'
import sys,glob
base,inj,css=sys.argv[1:4]
snippet=open(inj,encoding='utf8').read()
links=''.join('<link rel="stylesheet" href="/wp-content/themes/hodima/%s">'%c for c in css.split(',') if c)
for d in ('vout-ref','vout-work'):
    for f in glob.glob('%s/%s/*.html'%(base,d)):
        s=open(f,encoding='utf8').read()
        s=s.replace('</head>',links+'</head>',1).replace('</body>',snippet+'</body>',1)
        open(f,'w',encoding='utf8').write(s)
PY
rm -rf "$base/visual-inject"
( cd "$repo" && HODIMA_VISUAL_FORCE='#hximp-sample *' node "$here/visual-compare.mjs" "$base/vout-ref" "$base/vref" "$base/vout-work" "$repo" "$base/visual-inject" "$@" )
rc=$?
git -C "$repo" worktree remove --force "$base/vref"
exit $rc
