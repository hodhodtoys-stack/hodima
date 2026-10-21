#!/usr/bin/env bash
# بررسی یک تب «تنظیمات قالب هدیما» (پیشخوان) با CSS/JS واقعی راست‌به‌چپ — اسکیل hodima-theme، بخش «طراحی پیشخوان تنظیمات»:
#   settings-tab-check.sh <tab> [--ref git-ref] [outdir]      مثلا: settings-tab-check.sh product --ref HEAD
# خروجی در outdir (پیش‌فرض /tmp/hodima-harness/settings-<tab>): <tab>-work-1300.png و -390.png (و -ref-… با --ref)،
# گزارش axe و آزمون رفتار آیکون ؟ (هاور، کلیک، Esc، کلید روی کاشی، نام قابل‌دسترس، هم‌ارتفاعی)؛ کد خروج ۱ = رد.
# محدودیت: وردپرس ابزار تست JS رسانه (wp.media) ندارد؛ فونت dashicons با یک شبیه‌ساز از dashicons.svg جایگزین می‌شود.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; export HODIMA_WP="$base/wp" HODIMA_HARNESS="$base"
tab="${1:?نام تب (مثلا product)}"; shift; ref=""
if [ "${1:-}" = "--ref" ]; then ref="$2"; shift 2; fi
out="${1:-$base/settings-$tab}"; mkdir -p "$out"; rm -f "$out"/*
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
[ -d "$repo/node_modules/playwright-core" ] && [ -d "$repo/node_modules/axe-core" ] || ( cd "$repo" && npm ci --no-audit --no-fund --silent )
render() { # $1=src $2=فایل خروجی
	"$here/link.sh" "$1"
	rm -f "$HODIMA_WP/wp-content/database/.ht.sqlite" "$HODIMA_WP/debug.log"
	HARNESS_WC=1 php "$here/fixtures.php" >/dev/null 2>&1
	HARNESS=1 HARNESS_USER=1 php "$here/admin-render.php" hodima-settings "tab=$tab" > "$2" 2> "$2.err"
	[ -s "$2.err" ] && { echo "هشدار/خطای PHP:"; head -5 "$2.err"; }
	[ "$(wc -c < "$2")" -gt 100000 ] || { echo "صفحه تنظیمات کامل رندر نشد: $2"; return 1; }
}
refsrc=""
if [ -n "$ref" ]; then
	refsrc="$base/settings-ref-src"
	git -C "$repo" worktree remove --force "$refsrc" >/dev/null 2>&1 || true
	git -C "$repo" worktree add --detach -f "$refsrc" "$ref" >/dev/null
	render "$refsrc" "$out/$tab-ref.html" || exit 1
fi
render "$repo" "$out/$tab-work.html" || exit 1
"$here/link.sh" "$repo"
( cd "$repo" && node "$here/settings-tab-check.mjs" "$tab" "$out" "$base/wp" "$repo" ${refsrc:+"$refsrc"} )
rc=$?
[ -n "$refsrc" ] && git -C "$repo" worktree remove --force "$refsrc" >/dev/null 2>&1
exit $rc
