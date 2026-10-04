#!/usr/bin/env bash
# نصب ابزارهای PHP کنترل کیفیت (PHPCS، WPCS، PHPStan و stubs وردپرس/ووکامرس)
# وقتی composer نمی‌تواند از گیت‌هاب دانلود کند (محیط ابری Claude: دانلود ZIP
# از github.com بسته است ولی git clone باز است).
#
#   bash tools/quality/install-offline.sh
#
# هر بسته با git clone (نسخه ثابت پایین) در $HODIMA_QT (پیش‌فرض /tmp/hodima-quality)
# گرفته و با «path repository» به composer داده می‌شود؛ vendor/ ریشه مخزن یک
# لینک نمادین به آن است. همچنین npm ci (Stylelint/ESLint از npm).
# در GitHub Actions لازم نیست (composer install معمولی کار می‌کند).
# نسخه‌ها باید با محدوده‌های composer.json سازگار باشند.
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
qt="${HODIMA_QT:-/tmp/hodima-quality}"
mkdir -p "$qt/proj"

# repo  tag  dir  composer-name  version
pkgs=(
	"PHPCSStandards/PHP_CodeSniffer 3.13.6 phpcs squizlabs/php_codesniffer 3.13.6"
	"WordPress/WordPress-Coding-Standards 3.4.1 wpcs wp-coding-standards/wpcs 3.4.1"
	"PHPCSStandards/PHPCSUtils 1.2.3 phpcsutils phpcsstandards/phpcsutils 1.2.3"
	"PHPCSStandards/PHPCSExtra 1.5.1 phpcsextra phpcsstandards/phpcsextra 1.5.1"
	"PHPCSStandards/composer-installer v1.2.1 composer-installer dealerdirect/phpcodesniffer-composer-installer 1.2.1"
	"phpstan/phpstan 2.2.17 phpstan phpstan/phpstan 2.2.17"
	"szepeviktor/phpstan-wordpress v2.0.4 phpstan-wordpress szepeviktor/phpstan-wordpress 2.0.4"
	"php-stubs/wordpress-stubs v6.9.4 wordpress-stubs php-stubs/wordpress-stubs 6.9.4"
	"php-stubs/woocommerce-stubs v11.1.2 woocommerce-stubs php-stubs/woocommerce-stubs 11.1.2"
)

repos="["
for p in "${pkgs[@]}"; do
	set -- $p
	[ -d "$qt/$3" ] || git -c advice.detachedHead=false clone -q --depth 1 --branch "$2" "https://github.com/$1.git" "$qt/$3"
	repos+="{\"type\":\"path\",\"url\":\"$qt/$3\",\"options\":{\"symlink\":true,\"versions\":{\"$4\":\"$5\"}}},"
done
repos+="{\"packagist.org\":false}]"

python3 - "$root/composer.json" "$qt/proj/composer.json" "$repos" <<'PY'
import json, sys
d = json.load(open(sys.argv[1], encoding='utf-8'))
d['repositories'] = json.loads(sys.argv[3])
json.dump(d, open(sys.argv[2], 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
PY

( cd "$qt/proj" && rm -f composer.lock && COMPOSER_ALLOW_SUPERUSER=1 composer install -n --no-progress -q )
# مسیر استانداردها مطلق (installed_paths نسبی composer با لینک نمادین درست نمی‌شود)
"$qt/proj/vendor/bin/phpcs" --config-set installed_paths "$qt/wpcs,$qt/phpcsutils,$qt/phpcsextra" >/dev/null

if [ -e "$root/vendor" ] && [ ! -L "$root/vendor" ]; then
	echo "vendor/ در ریشه مخزن پوشه واقعی است؛ دست نزدم (composer install معمولی کار کرده)."
else
	ln -sfn "$qt/proj/vendor" "$root/vendor"
fi

( cd "$root" && npm ci --no-audit --no-fund --silent )
echo "✔ ابزارها آماده‌اند: bash bin/lint.sh"
