#!/usr/bin/env bash
# کدام !important قالب لازم نیست؟ (important-audit.mjs؛ نوسازی قالب، مرحله ۶)
#   important-audit.sh            فقط گزارش
#   important-audit.sh --apply    برداشتن !important‌های قابل حذف از سورس
# صفحه‌های ابزار تست اصلی (۲۵) و ووکامرس واقعی (wc-run.sh) کار فعلی ساخته می‌شوند و
# در ۱۰ عرض بررسی می‌شوند. بعد از --apply حتما visual-compare.sh و visual-compare.sh --wc.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; wcbase="${HODIMA_HARNESS_WC:-/tmp/hodima-harness-wc}"
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
[ -d "$wcbase/wp/wp-content/plugins/woocommerce" ] || HODIMA_HARNESS_WC="$wcbase" "$here/wc-setup.sh"
"$here/run.sh" "$repo" "$base/aout" >/dev/null
HODIMA_HARNESS_WC="$wcbase" "$here/wc-run.sh" "$repo" "$wcbase/aout" >/dev/null
cd "$repo" && HODIMA_AUDIT_WIDTHS="${HODIMA_AUDIT_WIDTHS:-1400,1300,1170,1100,1000,800,700,600,450,390}" \
	node "$here/important-audit.mjs" "$@" "$base/aout=$base/wp" "$wcbase/aout=$wcbase/wp"
