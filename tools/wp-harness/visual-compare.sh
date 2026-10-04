#!/usr/bin/env bash
# مقایسه ظاهر قبل/بعد در یک دستور (مثل compare-with-ref.sh برای اسکیما):
#   visual-compare.sh [git-ref] [page ...]     (پیش‌فرض HEAD = کار commit‌نشده در برابر آخرین commit)
# همه صفحه‌های ابزار تست را برای <ref> و کار فعلی می‌سازد و با Chromium
# (دسکتاپ ۱۳۰۰ و موبایل ۳۹۰) استایل محاسبه‌شده هر عنصر و عکس کل صفحه را
# مقایسه می‌کند. گزارش و عکس‌ها: $HODIMA_HARNESS/visual (پیش‌فرض /tmp/hodima-harness/visual).
# محدودیت: صفحه‌های ووکامرس در ابزار تست فقط head/footer دارند (ووکامرس شبیه‌سازی).
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; ref="${1:-HEAD}"; shift || true
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
[ -d "$repo/node_modules/playwright-core" ] || ( cd "$repo" && npm ci --no-audit --no-fund --silent )
git -C "$repo" worktree remove --force "$base/vref" >/dev/null 2>&1 || true
git -C "$repo" worktree add --detach -f "$base/vref" "$ref" >/dev/null
"$here/run.sh" "$base/vref" "$base/vout-ref" >/dev/null
"$here/run.sh" "$repo" "$base/vout-work" >/dev/null
rm -rf "$base/visual"
( cd "$repo" && node "$here/visual-compare.mjs" "$base/vout-ref" "$base/vref" "$base/vout-work" "$repo" "$base/visual" "$@" )
rc=$?
git -C "$repo" worktree remove --force "$base/vref"
exit $rc
