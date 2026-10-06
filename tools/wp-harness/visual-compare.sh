#!/usr/bin/env bash
# مقایسه ظاهر قبل/بعد در یک دستور (مثل compare-with-ref.sh برای اسکیما):
#   visual-compare.sh [--built] [--wc] [git-ref] [page ...]     (پیش‌فرض HEAD = کار commit‌نشده در برابر آخرین commit)
#   --built: طرف «کار» با CSS ساخته‌شده ZIP (bin/build-css.mjs: پایین‌آوردن + minify)؛
#            «visual-compare.sh --built» بدون تغییر سورس = آزمون هم‌ارزی مرحله ساخت.
#   HODIMA_VISUAL_WIDTHS="1300,1100,800,390": عرض‌های دیگر به‌جای دسکتاپ/موبایل.
# همه صفحه‌های ابزار تست را برای <ref> و کار فعلی می‌سازد و با Chromium
# (دسکتاپ ۱۳۰۰ و موبایل ۳۹۰) استایل محاسبه‌شده هر عنصر و عکس کل صفحه را
# مقایسه می‌کند. گزارش و عکس‌ها: $HODIMA_HARNESS/visual (پیش‌فرض /tmp/hodima-harness/visual).
# محدودیت: صفحه‌های ووکامرس در ابزار تست اصلی فقط head/footer دارند (ووکامرس شبیه‌سازی).
#   --wc: صفحه‌های ووکامرس *واقعی* (محصول، فروشگاه، دسته، سبد، پرداخت؛ wc-setup.sh/wc-run.sh)؛
#         گزارش در $HODIMA_HARNESS_WC/visual (پیش‌فرض /tmp/hodima-harness-wc). با --built هم می‌آید.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; runner="$here/run.sh"
built=0
while [ "${1:-}" = "--built" ] || [ "${1:-}" = "--wc" ]; do
	[ "$1" = "--built" ] && built=1
	if [ "$1" = "--wc" ]; then
		base="${HODIMA_HARNESS_WC:-/tmp/hodima-harness-wc}"; runner="$here/wc-run.sh"
		[ -d "$base/wp/wp-content/plugins/woocommerce" ] || HODIMA_HARNESS_WC="$base" "$here/wc-setup.sh"
		export HODIMA_HARNESS_WC="$base"
	fi
	shift
done
export HODIMA_HARNESS="$base"
ref="${1:-HEAD}"; shift || true
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
[ -d "$repo/node_modules/playwright-core" ] || ( cd "$repo" && npm ci --no-audit --no-fund --silent )
git -C "$repo" worktree remove --force "$base/vref" >/dev/null 2>&1 || true
git -C "$repo" worktree add --detach -f "$base/vref" "$ref" >/dev/null
"$runner" "$base/vref" "$base/vout-ref" >/dev/null
"$runner" "$repo" "$base/vout-work" >/dev/null
work_root="$repo"
if [ "$built" = 1 ]; then
	# همان HTML کار، با CSS ساخته‌شده (کپی قالب؛ افزونه‌ها پیوند به مخزن)
	rm -rf "$base/vbuilt" && mkdir -p "$base/vbuilt"
	cp -r "$repo/hodima" "$base/vbuilt/hodima" && ln -s "$repo/plugins" "$base/vbuilt/plugins"
	( cd "$repo" && node bin/build-css.mjs "$base/vbuilt/hodima" ) || exit 1
	work_root="$base/vbuilt"
	export HODIMA_VISUAL_IGNORE_VARS=1
fi
rm -rf "$base/visual"
( cd "$repo" && node "$here/visual-compare.mjs" "$base/vout-ref" "$base/vref" "$base/vout-work" "$work_root" "$base/visual" "$@" )
rc=$?
git -C "$repo" worktree remove --force "$base/vref"
exit $rc
