#!/usr/bin/env bash
# بررسی کیفیت کد هدیما — همان چیزی که GitHub Actions روی هر push اجرا می‌کند.
#
#   bash bin/lint.sh                    همه بررسی‌ها
#   bash bin/lint.sh php|css|js         فقط یک بخش
#   bash bin/lint.sh --update-baseline  ثبت وضعیت فعلی به‌عنوان baseline (بعد از رفع خطاهای قدیمی)
#
# ۱. php -l (PHP 8.4) همه فایل‌های قالب و افزونه‌ها؛ هشدار Deprecated هم خطاست
# ۲. PHPCS (phpcs.xml.dist)        ← tools/quality/baseline/phpcs.json
# ۳. PHPStan (phpstan.neon.dist)   ← tools/quality/baseline/phpstan.neon
# ۴. Stylelint (stylelint.config.mjs) ← tools/quality/baseline/stylelint.json
# ۵. ESLint (eslint.config.mjs)    ← tools/quality/baseline/eslint.json
# خطاهای قدیمی ثبت‌شده در baseline رد نمی‌شوند؛ هر خطای جدید رد می‌شود.
#
# پیش‌نیاز: composer install و npm ci در ریشه مخزن. در محیط ابری Claude که
# composer به گیت‌هاب دسترسی ندارد: bash tools/quality/install-offline.sh
set -uo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root" || exit 2
q="$root/tools/quality"; base="$q/baseline"; tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

update=0; only=""
for a in "$@"; do
	case "$a" in
		--update-baseline) update=1 ;;
		php|css|js) only="$a" ;;
		*) echo "گزینه ناشناخته: $a"; exit 2 ;;
	esac
done
want() { [ -z "$only" ] || [ "$only" = "$1" ]; }

fail=0
need() {
	if [ ! -x "$1" ]; then
		echo "  ✘ $2 نصب نیست ($1). اول: $3"
		fail=1
		return 1
	fi
}
ratchet() { # tool report baseline
	if [ "$update" = 1 ]; then python3 "$q/ratchet.py" "$1" "$2" "$3" --update
	else python3 "$q/ratchet.py" "$1" "$2" "$3" || fail=1; fi
}

if want php; then
	echo "== PHP"
	# ۱. نحو PHP 8.4 (+ Deprecated زمان کامپایل، مثل nullable ضمنی)
	php_ver="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
	[ "$php_ver" = "8.4" ] || echo "  ! PHP $php_ver (نه 8.4) — نتیجه php -l ممکن است با سایت فرق کند"
	find hodima plugins -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0 \
		| xargs -0 -n1 -P8 php -d error_reporting=E_ALL -d display_errors=stderr -d log_errors=0 -l > "$tmp/php-l.txt" 2>&1
	if grep -vE '^No syntax errors detected' "$tmp/php-l.txt" | grep -q .; then
		echo "  ✘ php -l:"; grep -vE '^No syntax errors detected' "$tmp/php-l.txt" | sed 's/^/    /' | head -40; fail=1
	else
		echo "  ✔ php -l: $(wc -l < "$tmp/php-l.txt") فایل بدون خطا"
	fi

	# ۲. PHPCS
	if need vendor/bin/phpcs PHPCS "composer install"; then
		vendor/bin/phpcs --report=json -q > "$tmp/phpcs.json" 2> "$tmp/phpcs.err"
		if ! python3 -c 'import json,sys; json.load(open(sys.argv[1]))' "$tmp/phpcs.json" 2>/dev/null; then
			echo "  ✘ PHPCS اجرا نشد:"; cat "$tmp/phpcs.json" "$tmp/phpcs.err" | head -20; fail=1
		else
			ratchet phpcs "$tmp/phpcs.json" "$base/phpcs.json"
		fi
	fi

	# ۳. PHPStan (baseline خودش)
	if need vendor/bin/phpstan PHPStan "composer install"; then
		if [ "$update" = 1 ]; then
			vendor/bin/phpstan analyse --no-progress --memory-limit=2G --generate-baseline "$base/phpstan.neon" -q --allow-empty-baseline \
				&& echo "  phpstan: baseline به‌روز شد"
		elif vendor/bin/phpstan analyse --no-progress --memory-limit=2G --error-format=raw > "$tmp/phpstan.txt" 2>/dev/null; then
			echo "  ✔ phpstan: بدون خطای جدید"
		else
			echo "  ✘ phpstan: خطای جدید:"; grep -v '^ *$' "$tmp/phpstan.txt" | head -40 | sed 's/^/    /'; fail=1
		fi
	fi
fi

if want css; then
	echo "== CSS"
	if need node_modules/.bin/stylelint Stylelint "npm ci"; then
		node_modules/.bin/stylelint "hodima/**/*.css" -f json > /dev/null 2> "$tmp/stylelint.json"
		ratchet stylelint "$tmp/stylelint.json" "$base/stylelint.json"
	fi
fi

if want js; then
	echo "== JS"
	if need node_modules/.bin/eslint ESLint "npm ci"; then
		node_modules/.bin/eslint hodima -f json > "$tmp/eslint.json" 2> "$tmp/eslint.err"
		ratchet eslint "$tmp/eslint.json" "$base/eslint.json"
	fi
fi

echo
if [ "$fail" = 0 ]; then echo "✔ همه بررسی‌ها قبول"; else echo "✘ بررسی رد شد (بالا را ببینید)"; fi
exit "$fail"
