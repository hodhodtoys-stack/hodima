#!/usr/bin/env bash
# هم‌ارزی CSS قالب، قانون‌به‌قانون (css-equiv.mjs؛ بدون نیاز به صفحه‌های ابزار تست)
#   css-equiv.sh [git-ref] [file.css ...]   CSS قالب در <ref> (پیش‌فرض HEAD) در برابر کار فعلی
#   css-equiv.sh --built [file.css ...]     سورس کار فعلی در برابر خروجی bin/build-css.mjs (همان ZIP)
# خروجی ۰ = هم‌ارز. برای بازنویسی‌های هم‌ارز (nesting، minify) باید «همه … هم‌ارز» بدهد؛
# تغییر عمدی تفاوت نشان می‌دهد و باید با visual-compare.sh دیده و در گزارش آورده شود.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
[ -d "$repo/node_modules/lightningcss" ] || ( cd "$repo" && npm ci --no-audit --no-fund --silent )
if [ "${1:-}" = "--built" ]; then
	shift
	cp -r "$repo/hodima" "$tmp/b"
	( cd "$repo" && node bin/build-css.mjs "$tmp/b" ) >/dev/null || exit 1
	a="$repo/hodima"; b="$tmp/b"
else
	ref="${1:-HEAD}"; shift || true
	mkdir -p "$tmp/a" && git -C "$repo" archive "$ref" hodima | tar -x -C "$tmp/a"
	a="$tmp/a/hodima"; b="$repo/hodima"
fi
cd "$repo" && node "$here/css-equiv.mjs" "$a" "$b" "$@"
