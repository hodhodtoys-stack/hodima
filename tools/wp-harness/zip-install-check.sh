#!/usr/bin/env bash
# نصب و فعال‌سازی واقعی از ZIPهای dist/ (همان فایل‌هایی که سایت‌ها نصب می‌کنند)
# در هر دو ترتیب «اول قالب، بعد افزونه‌ها» و «اول افزونه‌ها، بعد قالب»، روی یک
# کپی جدا از وردپرس ابزار تست (بدون لینک نمادین). خطای «Cannot redeclare» هنگام
# فعال‌سازی را فقط همین می‌بیند (ابزار تست اصلی افزونه‌ها را از قبل فعال می‌کند؛
# CLAUDE.md، بخش ۲۲). بعد از هر ترتیب صفحه اصلی ساخته می‌شود.
#   zip-install-check.sh        (اول bash bin/build.sh)
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; zw="$base/zipwp"; dist="$repo/dist"
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
[ -f "$dist/hodima.zip" ] || { echo "اول: bash bin/build.sh"; exit 2; }
fail=0
run() { HODIMA_WP="$zw" php "$here/zip-install.php" "$@" 2>&1 | grep -v '^$'; }
for order in theme-first plugins-first; do
	echo "=== $order"
	rm -rf "$zw"; cp -a "$base/wp" "$zw"
	rm -rf "$zw/wp-content/themes/hodima" "$zw"/wp-content/plugins/hodima-* "$zw/wp-content/database/.ht.sqlite" "$zw/debug.log"
	rm -f "$zw/wp-content/mu-plugins/harness-wc-stub.php"
	{
		run install "$dist"
		if [ "$order" = theme-first ]; then run theme; run plugins; else run plugins; run theme; fi
	} > "$zw/steps.txt"
	sed 's/^/  /' "$zw/steps.txt"
	HODIMA_WP="$zw" HARNESS=1 php "$here/render.php" / full > "$zw/home.html" 2>&1
	n=$(grep -c 'application/ld+json' "$zw/home.html"); end=$(grep -c '</html>' "$zw/home.html")
	errs=$(grep -h "PHP \(Fatal\|Warning\|Parse\|Deprecated\|Notice\)" "$zw/debug.log" 2>/dev/null | grep -v "sqlite-database-integration" | sed 's/^\[[^]]*\] //' | sort | uniq -c)
	echo "  صفحه اصلی: JSON-LD=$n </html>=$end"
	if [ -n "$errs" ]; then echo "$errs" | sed 's/^/  ✘ /'; fail=1; fi
	{ [ "$n" = 1 ] && [ "$end" = 1 ]; } || fail=1
	grep -q "ERROR\|install[^:]*: false" "$zw/steps.txt" && fail=1
done
rm -rf "$zw"
[ "$fail" = 0 ] && echo "✔ نصب از ZIP در هر دو ترتیب سالم" || echo "✘ نصب از ZIP خطا دارد"
exit "$fail"
