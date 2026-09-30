#!/usr/bin/env bash
# Render every page type of a source tree and extract its JSON-LD.
#   run.sh <src-root> <outdir>
# Each run reinstalls the site with the fixtures (fixtures.php), so runs are comparable.
# Pages without WooCommerce are rendered fully (templates + body schema); WooCommerce
# pages use the stub (mu-plugins/harness-wc-stub.php) and fire only wp_head/wp_footer.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; export HODIMA_WP="$base/wp"
out="$2"; mkdir -p "$out"; rm -f "$out"/*
"$here/link.sh" "$1"
rm -f "$HODIMA_WP/wp-content/database/.ht.sqlite" "$HODIMA_WP/debug.log"
HARNESS_WC=1 php "$here/fixtures.php" >/dev/null 2>&1
HARNESS=1 HARNESS_WC=1 HARNESS_FLUSH=1 php "$here/render.php" / hooks >/dev/null 2>&1
rm -f "$HODIMA_WP/debug.log"
full=( "/ home" "/blog/ blog" "/blog/page/2/ blog-p2" "/clips-guide/ pillar" "/metal-clips/ child" "/about-us/ about" "/contact-us/ contact"
       "/guide/ guide" "/guide-child/ guide-child" "/videos/ videos" "/video/hairpin-video/ video" "/news/ category" "/news/page/2/ category-p2"
       "/tag/clips/ tag" "/author/admin/ author" "/?s=test search" "/no-such-page/ 404" "/2025/03/ date" )
wc=( "/103/ product" "/pin-simple/ product2" "/kesh-rangi/ product3" "/hair/ product-cat" "/shop/ shop" "/product-tag/best/ product-tag" "/ home-wc" )
for s in "${full[@]}"; do set -- $s; HARNESS=1 php "$here/render.php" "$1" full > "$out/$2.html" 2> "$out/$2.err"; done
for s in "${wc[@]}";   do set -- $s; HARNESS=1 HARNESS_WC=1 php "$here/render.php" "$1" hooks > "$out/$2.html" 2> "$out/$2.err"; done
cp "$HODIMA_WP/debug.log" "$out/debug.log" 2>/dev/null || true
for f in "$out"/*.html; do python3 "$here/extract.py" "$f" > "${f%.html}.json"; done
echo "✔ $out"
