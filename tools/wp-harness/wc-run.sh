#!/usr/bin/env bash
# صفحه‌های ووکامرس *واقعی* یک سورس را می‌سازد (نسخه دوم ابزار تست، wc-setup.sh).
#   wc-run.sh <src-root> <outdir>
# هر اجرا نصب تازه با wc-fixtures.php است تا اجراها مقایسه‌پذیر باشند.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
base="${HODIMA_HARNESS_WC:-/tmp/hodima-harness-wc}"; export HODIMA_WP="$base/wp"
[ -d "$HODIMA_WP/wp-content/plugins/woocommerce" ] || HODIMA_HARNESS_WC="$base" "$here/wc-setup.sh"
out="$2"; mkdir -p "$out"; rm -f "$out"/*
HODIMA_HARNESS="$base" "$here/link.sh" "$1"
# stub ووکامرس ابزار اصلی اینجا نباید باشد (تابع WC() تکراری)
rm -f "$HODIMA_WP/wp-content/mu-plugins/harness-wc-stub.php" "$HODIMA_WP/wp-content/database/.ht.sqlite" "$HODIMA_WP/debug.log"
php "$here/wc-fixtures.php" install >/dev/null 2>&1
php "$here/wc-fixtures.php" data >/dev/null 2>&1
HARNESS=1 HARNESS_FLUSH=1 php "$here/render.php" / hooks >/dev/null 2>&1
rm -f "$HODIMA_WP/debug.log"
main="$(python3 -c "import json;print(json.load(open('$base/ids.json'))['main'])")"
pages=( "/103/ product" "/kesh-rangi/ product-var" "/pin-out/ product-out" "/tel-sale/ product-sale" "/shop/ shop" "/shop/page/2/ shop-p2"
        "/hair/ cat" "/hair/clips/ subcat" "/hair/?orderby=price cat-sorted" "/product-tag/best/ tag" "/?s=محصول&post_type=product search" "/ home" "/cart/ cart-empty" )
for s in "${pages[@]}"; do set -- $s; HARNESS=1 php "$here/render.php" "$1" full > "$out/$2.html" 2> "$out/$2.err"; done
HARNESS=1 HARNESS_CART="$main:12" php "$here/render.php" /cart/ full > "$out/cart.html" 2> "$out/cart.err"
HARNESS=1 HARNESS_CART="$main:12" php "$here/render.php" /checkout/ full > "$out/checkout.html" 2> "$out/checkout.err"
# شناسه تصادفی فیلد تعداد ووکامرس (uniqid) در هر اجرا فرق می‌کند
sed -i -E 's/quantity_[0-9a-f]{13}/quantity_X/g' "$out"/*.html
cp "$HODIMA_WP/debug.log" "$out/debug.log" 2>/dev/null || true
echo "✔ $out"
