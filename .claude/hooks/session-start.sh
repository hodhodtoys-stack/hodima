#!/bin/bash
# قلاب شروع جلسه Claude Code (فقط محیط ابری): آماده کردن محیط کار هدیما.
#   ۱. PHP 8.4 (محیط ابری 8.3 دارد؛ کد و ابزار تست 8.4 می‌خواهند — HODIMA-AUDIT.md بخش ۳۵)
#   ۲. ابزارهای کیفیت کد: PHPCS/PHPStan (tools/quality/install-offline.sh) و npm (Stylelint، ESLint، Lightning CSS)
#   ۳. ابزار تست وردپرس: tools/wp-harness/setup.sh و wc-setup.sh (فقط دانلود و باز کردن)
# هر مرحله اگر قبلا انجام شده رد می‌شود (چند ثانیه). محیط ابری وضعیت کانتینر را بعد از
# این قلاب کش می‌کند، پس نصب سنگین فقط بار اول است. خطای یک مرحله جلسه را نمی‌بندد:
# در خروجی (که Claude می‌بیند) گزارش می‌شود و بقیه مرحله‌ها ادامه می‌یابند.
# گزارش کامل: ~/.cache/hodima/session-start.log
set -uo pipefail

[ "${CLAUDE_CODE_REMOTE:-}" = "true" ] || exit 0

repo="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
cache="$HOME/.cache/hodima"
log="$cache/session-start.log"
mkdir -p "$cache"
: > "$log"
problems=()

step() { # نام، فرمان…
	local name="$1"; shift
	echo "== $name" >> "$log"
	if ! "$@" >> "$log" 2>&1; then
		problems+=("$name")
	fi
}

php_version() { php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null; }

install_php84() {
	if [ ! -x /usr/bin/php8.4 ]; then
		# PHP 8.4 از مخزن Ubuntu 25.04 (plucky)؛ pin فقط برای php8.4* تا بقیه سیستم دست نخورد
		echo "deb https://archive.ubuntu.com/ubuntu plucky main universe" > /etc/apt/sources.list.d/plucky.list
		printf '%s\n' 'Package: *' 'Pin: release n=plucky' 'Pin-Priority: 100' '' \
			'Package: php8.4* php-common' 'Pin: release n=plucky' 'Pin-Priority: 600' > /etc/apt/preferences.d/plucky-php
		apt-get update -qq || return 1
		DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php8.4-cli php8.4-sqlite3 php8.4-mbstring \
			php8.4-xml php8.4-gd php8.4-intl php8.4-curl php8.4-zip || return 1
	fi
	update-alternatives --set php /usr/bin/php8.4
	[ "$(php_version)" = "8.4" ]
}

link_tmp() { # /tmp/hodima-<نام> ← ~/.cache/hodima/<نام>؛ پوشه واقعی موجود را دست نمی‌زند
	mkdir -p "$cache/$1"
	if [ ! -e "/tmp/hodima-$1" ] || [ -L "/tmp/hodima-$1" ]; then
		ln -sfn "$cache/$1" "/tmp/hodima-$1"
	fi
}

install_quality() {
	# پوشه ثابت در ~/.cache (نه /tmp) + لینک /tmp/hodima-quality برای مسیرهای مستندات
	link_tmp quality
	if [ -x "$repo/vendor/bin/phpcs" ] && [ -x "$repo/vendor/bin/phpstan" ] && [ -d "$repo/node_modules/lightningcss" ]; then
		return 0
	fi
	HODIMA_QT="$cache/quality" bash "$repo/tools/quality/install-offline.sh"
}

install_harness() {
	# ابزار تست با مسیرهای پیش‌فرض خودش (/tmp/…)، ولی داده در ~/.cache تا در کش کانتینر بماند
	link_tmp harness
	link_tmp harness-wc
	[ -f /tmp/hodima-harness/wp/wp-load.php ] || bash "$repo/tools/wp-harness/setup.sh" || return 1
	[ -d /tmp/hodima-harness-wc/wp/wp-content/plugins/woocommerce ] || bash "$repo/tools/wp-harness/wc-setup.sh"
}

step "PHP 8.4" install_php84
step "ابزارهای کیفیت کد" install_quality
step "ابزار تست وردپرس" install_harness

# خلاصه کوتاه برای Claude (خروجی قلاب شروع جلسه وارد گفتگو می‌شود)
if [ ${#problems[@]} -eq 0 ]; then
	echo "محیط هدیما آماده است: PHP $(php_version)، ابزارهای کیفیت (bash bin/lint.sh)، ابزار تست وردپرس (tools/wp-harness)."
else
	echo "قلاب شروع جلسه هدیما: این مرحله‌ها انجام نشد: ${problems[*]} — گزارش: $log (روش دستی: CLAUDE.md «ابزار تست» و «کنترل کیفیت کد»)."
fi
exit 0
