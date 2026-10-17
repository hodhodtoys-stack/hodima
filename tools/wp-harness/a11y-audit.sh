#!/usr/bin/env bash
# بررسی دسترس‌پذیری (WCAG 2.2 AA) در یک دستور — اسکیل hodima-theme:
#   a11y-audit.sh [--wc] [--ref git-ref] [page ...]
#   پیش‌فرض: کار فعلی. --ref: همان بررسی روی commit دیگر (مثلا HEAD) برای مقایسه قبل/بعد.
#   --wc: صفحه‌های ووکامرس *واقعی* (wc-run.sh)؛ بدون آن ابزار تست اصلی (ووکامرس شبیه‌سازی).
# axe-core (برچسب‌های wcag2a/aa/21a/21aa/22aa) + بررسی صفحه‌کلید (فوکوس نامرئی/پوشیده)؛
# گزارش JSON در $base/a11y-<work|ref>.json. جزئیات: tools/wp-harness/a11y-audit.mjs
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; runner="$here/run.sh"; ref=""
while [ $# -gt 0 ]; do
	case "$1" in
		--wc) base="${HODIMA_HARNESS_WC:-/tmp/hodima-harness-wc}"; runner="$here/wc-run.sh"
			[ -d "$base/wp/wp-content/plugins/woocommerce" ] || HODIMA_HARNESS_WC="$base" "$here/wc-setup.sh"
			export HODIMA_HARNESS_WC="$base"; shift ;;
		--ref) ref="$2"; shift 2 ;;
		*) break ;;
	esac
done
export HODIMA_HARNESS="$base" HODIMA_HARNESS_WP="$base"
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
[ -d "$repo/node_modules/playwright-core" ] && [ -d "$repo/node_modules/axe-core" ] || ( cd "$repo" && npm ci --no-audit --no-fund --silent )
src="$repo"; tag=work
if [ -n "$ref" ]; then
	git -C "$repo" worktree remove --force "$base/a11yref" >/dev/null 2>&1 || true
	git -C "$repo" worktree add --detach -f "$base/a11yref" "$ref" >/dev/null; src="$base/a11yref"; tag=ref
fi
HARNESS_HOME=1 "$runner" "$src" "$base/out-a11y-$tag" >/dev/null 2>&1
( cd "$repo" && node "$here/a11y-audit.mjs" "$base/out-a11y-$tag" "$src" "$base/a11y-$tag.json" "$@" )
rc=$?
[ -n "$ref" ] && git -C "$repo" worktree remove --force "$base/a11yref"
exit $rc
