#!/usr/bin/env bash
# بررسی خودکار (GitHub Actions، کار «render»): همه ۲۵ صفحه ابزار تست را با کد
# فعلی می‌سازد و رد می‌کند اگر:
#   - هشدار/خطای PHP (Warning، Notice، Deprecated، Fatal) از کد هدیما در debug.log باشد؛
#   - صفحه‌ای خالی یا ناقص باشد (بدون </html> در رندر کامل، بدون JSON-LD)؛
#   - در یک تگ JSON-LD شناسه (@id) تکراری باشد؛
#   - قواعد اسکیما (schema-rules.py: گراف واحد، هویت، تاریخ، آدرس، محصول، …) رد شوند؛
#   - آزمون‌های خودکار سیستم رسانه و Google Discover (media-tests.php) رد شوند؛
#   - آزمون‌های ماژول استوری (stories-tests.php) رد شوند؛
#   - آزمون‌های ماژول اعلان‌ها (notification-tests.php) رد شوند؛
#   - آزمون‌های Commerce (commerce-tests.php: تاریخ شمسی، شهرها، فرم آدرس) رد شوند.
# با [ref] (مثلا HEAD^) تفاوت اسکیما با آن commit را فقط گزارش می‌کند (رد نمی‌کند):
# تغییر اسکیما ممکن است عمدی باشد و تصمیمش با انسان است.
#   ci-check.sh [ref]
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; ref="${1:-}"
summary="${GITHUB_STEP_SUMMARY:-/dev/null}"
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"

"$here/run.sh" "$repo" "$base/out-ci" >/dev/null
out="$base/out-ci"; fail=0

echo "=== PHP (debug.log)"
if grep -h "PHP " "$out/debug.log" 2>/dev/null | grep -v "comments.php" | sed 's/^\[[^]]*\] //' | sort | uniq -c | grep .; then
	fail=1
else
	echo "  ✔ بدون هشدار PHP"
fi

echo "=== صفحه‌ها"
pages=0
for f in "$out"/*.html; do
	name="$(basename "$f" .html)"; pages=$((pages + 1))
	err="${f%.html}.err"
	if [ -s "$err" ]; then echo "  ✘ $name: خروجی خطا"; head -5 "$err" | sed 's/^/      /'; fail=1; fi
	if [ ! -s "$f" ]; then echo "  ✘ $name: صفحه خالی"; fail=1; continue; fi
	grep -q 'application/ld+json' "$f" || { echo "  ✘ $name: بدون JSON-LD"; fail=1; }
	# رندر کامل (قالب) با </html> تمام می‌شود؛ صفحه‌های ووکامرس شبیه‌سازی‌شده فقط head/footer دارند
	if grep -q '<body' "$f" && ! grep -q '</html>' "$f"; then echo "  ✘ $name: صفحه ناقص (بدون </html>؛ احتمالا Fatal)"; fail=1; fi
done
echo "  $pages صفحه ساخته شد"

echo "=== یکپارچگی اسکیما"
python3 "$here/integrity.py" "$out" > "$out/integrity.txt"
if grep -E "dup=\[[^]]" "$out/integrity.txt"; then fail=1; else echo "  ✔ بدون @id تکراری"; fi
grep -E "dangling=\[[^]]" "$out/integrity.txt" | sed 's/^/  (ارجاع به صفحه دیگر — معمولا عمدی) /' || true

echo "=== قواعد اسکیما (schema-rules.py)"
python3 "$here/schema-rules.py" "$out" || fail=1

echo "=== آزمون رسانه و Discover (media-tests.php)"
if HARNESS=1 HODIMA_WP="$base/wp" php "$here/media-tests.php" > "$out/media-tests.txt" 2>&1; then
	tail -1 "$out/media-tests.txt" | sed 's/^/  /'
else
	grep -E "✘|انتظار|واقعی|Fatal|Error" "$out/media-tests.txt" | head -30 | sed 's/^/  /'; fail=1
fi

echo "=== آزمون اعلان‌ها (notification-tests.php: کش، ذخیره، ستون وضعیت، خروجی)"
if HARNESS=1 HODIMA_WP="$base/wp" php "$here/notification-tests.php" > "$out/notification-tests.txt" 2>&1; then
	tail -1 "$out/notification-tests.txt" | sed 's/^/  /'
else
	grep -E "✘|انتظار|واقعی|Fatal|Error" "$out/notification-tests.txt" | head -30 | sed 's/^/  /'; fail=1
fi

echo "=== آزمون استوری (stories-tests.php: ذخیره پنل، آمار، nonce کهنه، زمان‌بندی، نوار)"
if HARNESS=1 HODIMA_WP="$base/wp" php "$here/stories-tests.php" > "$out/stories-tests.txt" 2>&1; then
	tail -1 "$out/stories-tests.txt" | sed 's/^/  /'
else
	grep -E "✘|انتظار|واقعی|Fatal|Error" "$out/stories-tests.txt" | head -30 | sed 's/^/  /'; fail=1
fi

echo "=== آزمون Commerce (commerce-tests.php: تاریخ شمسی، شهرها، فرم آدرس)"
if HARNESS=1 HODIMA_WP="$base/wp" php "$here/commerce-tests.php" > "$out/commerce-tests.txt" 2>&1; then
	tail -1 "$out/commerce-tests.txt" | sed 's/^/  /'
else
	grep -E "✘|انتظار|واقعی|Fatal|Error" "$out/commerce-tests.txt" | head -30 | sed 's/^/  /'; fail=1
fi

echo "=== آزمون حریم خصوصی (privacy-tests.php: خروجی/حذف داده لید، متن سیاست، یادداشت فرم؛ ووکامرس واقعی جدا)"
if HARNESS=1 HODIMA_WP="$base/wp" php "$here/privacy-tests.php" > "$out/privacy-tests.txt" 2>&1; then
	tail -1 "$out/privacy-tests.txt" | sed 's/^/  /'
else
	grep -E "✘|انتظار|واقعی|Fatal|Error" "$out/privacy-tests.txt" | head -30 | sed 's/^/  /'; fail=1
fi

if [ -n "$ref" ] && git -C "$repo" rev-parse -q --verify "$ref^{commit}" >/dev/null; then
	echo "=== تفاوت اسکیما با $ref (فقط گزارش)"
	git -C "$repo" worktree remove --force "$base/ref" >/dev/null 2>&1 || true
	git -C "$repo" worktree add --detach -f "$base/ref" "$ref" >/dev/null
	"$here/run.sh" "$base/ref" "$base/out-ref" >/dev/null
	git -C "$repo" worktree remove --force "$base/ref"
	python3 "$here/compare.py" "$base/out-ref" "$out" | tee "$out/compare.txt"
	{
		echo "### تفاوت اسکیما با \`$ref\`"
		echo '```'
		grep -v '(identical)' "$out/compare.txt" || echo "همه صفحه‌ها یکسان"
		echo '```'
	} >> "$summary"
fi

echo
if [ "$fail" = 0 ]; then echo "✔ رندر سالم"; else echo "✘ رندر خطا دارد"; fi
exit "$fail"
