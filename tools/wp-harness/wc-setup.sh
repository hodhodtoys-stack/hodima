#!/usr/bin/env bash
# یک بار: نسخه دوم ابزار تست با ووکامرس *واقعی* در $HODIMA_HARNESS_WC (پیش‌فرض /tmp/hodima-harness-wc).
# ابزار اصلی ووکامرس را شبیه‌سازی می‌کند (صفحه‌های فروشگاه فقط head/footer)؛ این یکی صفحه محصول،
# فروشگاه، دسته و سبد خرید را کامل با قالب‌های ووکامرس قالب می‌سازد تا ظاهر CSS آن‌ها دیده شود
# (نوسازی قالب، مرحله ۶). وردپرس همان ابزار اصلی؛ ووکامرس از گیت‌هاب (wordpress.org در محیط ابری بسته است).
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
main="${HODIMA_HARNESS:-/tmp/hodima-harness}"; base="${HODIMA_HARNESS_WC:-/tmp/hodima-harness-wc}"; wp="$base/wp"
wc_ver="${HODIMA_WC_VERSION:-9.9.5}"
[ -f "$main/pkg/package/src/wordpress/wp-nightly.zip" ] || HODIMA_HARNESS="$main" "$here/setup.sh"
mkdir -p "$base/pkg"
zip="$base/pkg/woocommerce-$wc_ver.zip"
[ -s "$zip" ] || curl -fsSL -o "$zip" "https://github.com/woocommerce/woocommerce/releases/download/$wc_ver/woocommerce.zip"
HODIMA_HARNESS="$base" bash -c "
	mkdir -p '$base/pkg/package/src' && cp -r '$main/pkg/package/src/.' '$base/pkg/package/src/'
	'$here/setup.sh'
"
( cd "$wp/wp-content/plugins" && unzip -q "$zip" )
echo "✔ WooCommerce $wc_ver ready in $wp"
