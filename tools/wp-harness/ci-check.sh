#!/usr/bin/env bash
# بررسی خودکار (GitHub Actions، کار «render»): همه ۲۵ صفحه ابزار تست را با کد
# فعلی می‌سازد و رد می‌کند اگر:
#   - هشدار/خطای PHP (Warning، Notice، Deprecated، Fatal) از کد هدیما در debug.log باشد؛
#   - صفحه‌ای خالی یا ناقص باشد (بدون </html> در رندر کامل، بدون JSON-LD)؛
#   - در یک تگ JSON-LD شناسه (@id) تکراری باشد.
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
