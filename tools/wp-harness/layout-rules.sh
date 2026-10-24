#!/usr/bin/env bash
# اندازه‌گیری قاعده‌های چیدمان عددی همه صفحه‌های پیشخوان (Z4؛ اسکیل hodima-admin-ui، «قاعده‌های چیدمان»). فقط گزارش.
#   layout-rules.sh [--strict] [--src ریشه] [outdir] [صفحه ...]
#   هر «صفحه» = slug یا slug?query (مثلا hodima-settings?tab=product). بدون صفحه: فهرست پیش‌فرض (همه صفحه‌های هدیما و ۲۰ تب تنظیمات قالب).
# خروجی: outdir/metrics.jsonl (خام) و outdir/report.txt (جدول ✔/⚠)؛ با --strict کد خروج ۱ اگر هشدار باشد.
# نیازها: CHROME=/opt/pw-browsers/chromium؛ برای پیشخوان فارسی HODIMA_RTL=1 (این اسکریپت خودش می‌گذارد).
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; export HODIMA_WP="$base/wp" HODIMA_HARNESS="$base" HODIMA_RTL=1
strict=""; src="$repo"
while [ $# -gt 0 ]; do
	case "$1" in
		--strict) strict="--strict"; shift ;;
		--src) src="$2"; shift 2 ;;
		*) break ;;
	esac
done
out="${1:-$base/layout-rules}"; [ $# -gt 0 ] && shift
mkdir -p "$out"; rm -f "$out"/*
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
[ -d "$repo/node_modules/playwright-core" ] && [ -d "$repo/node_modules/axe-core" ] || ( cd "$repo" && npm ci --no-audit --no-fund --silent )
pages=( "$@" )
if [ ${#pages[@]} -eq 0 ]; then
	pages=( hodima-hub hodima-hub-seo hodima-hub-commerce hodima-hub-media hodima-schema hodima-schema-homepage hodima-schema-breadcrumb
		hodima-schema-blog hodima-schema-category hodima-schema-product hodima-schema-image hodima-schema-page hodima-sitemap hodima-podcast
		hodima-schema-tables hodima-llms hodima-schema-cleaner hodima-router hodima-related-links hodima-torob hodima-discover hodima-redirects
		hodima-tc-orphans hodima-tc-map hodima-tc-health hodima-tc-settings hodima-core hodima-slider )
	for t in brand typography headings layout extras header footer home shop notfound contact social analytics speed pagespeed ccss code backup product product_more blog blog_posts; do
		pages+=( "hodima-settings?tab=$t" )
	done
fi
"$here/link.sh" "$src" >/dev/null
rm -f "$HODIMA_WP/wp-content/database/.ht.sqlite" "$HODIMA_WP/debug.log"
HARNESS_WC=1 php "$here/fixtures.php" >/dev/null 2>&1
: > "$out/metrics.jsonl"
for p in "${pages[@]}"; do
	slug="${p%%\?*}"; query=""; [ "$slug" != "$p" ] && query="${p#*\?}"
	label="$slug${query:+ ?$query}"; file="$out/$(echo "$label" | tr -c 'A-Za-z0-9_\n' '_').html"
	HARNESS=1 HARNESS_USER=1 php "$here/admin-render.php" "$slug" $query > "$file" 2>/dev/null
	if [ "$(wc -c < "$file")" -lt 20000 ] || ! grep -q 'wpbody-content' "$file"; then
		echo "رندر نشد (نامک نادرست یا صفحه نیازمند افزونه‌ای که در ابزار تست نیست): $label" >&2
		continue
	fi
	( cd "$repo" && HODIMA_MEASURE="$out/metrics.jsonl" HODIMA_LABEL="$label" node "$here/admin-shot.mjs" "$file" "${file%.html}" "$base/wp" "$src" 1300,390 >/dev/null 2>&1 )
done
( cd "$repo" && node "$here/layout-rules-report.mjs" "$out/metrics.jsonl" $strict ) | tee "$out/report.txt"
exit "${PIPESTATUS[0]}"
