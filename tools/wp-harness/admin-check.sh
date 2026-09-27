#!/usr/bin/env bash
# Open admin pages as the administrator and report title / active menu / access.
#   admin-check.sh <src-root> <outdir> [page-slug ...]
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; export HODIMA_WP="$base/wp"
src="$1"; out="$2"; shift 2
pages=( "$@" ); [ ${#pages[@]} -eq 0 ] && pages=( hodima-hub hodima-hub-seo hodima-hub-commerce hodima-hub-media hodima-schema hodima-schema-homepage
  hodima-schema-breadcrumb hodima-schema-blog hodima-schema-category hodima-schema-product hodima-schema-image hodima-schema-page
  hodima-sitemap hodima-podcast hodima-schema-tables hodima-llms hodima-schema-cleaner )
mkdir -p "$out"; rm -f "$out"/*
"$here/link.sh" "$src"
rm -f "$HODIMA_WP/wp-content/database/.ht.sqlite" "$HODIMA_WP/debug.log"
HARNESS_WC=1 php "$here/fixtures.php" >/dev/null 2>&1
for p in "${pages[@]}"; do
	HARNESS=1 HARNESS_USER=1 php "$here/admin-render.php" "$p" > "$out/$p.html" 2> "$out/$p.err"
	t=$(grep -o "<title>[^<]*" "$out/$p.html" | head -1 | sed 's/<title>//')
	menu=$(grep -o 'wp-has-current-submenu[^"]*' "$out/$p.html" | grep -o 'toplevel_page_[a-z0-9_-]*' | head -1)
	sub=$(grep -o '<li class="current"><a href=[^>]*>[^<]*' "$out/$p.html" | sed 's/.*>//' | head -1)
	denied=$(grep -c "not allowed\|Cannot load" "$out/$p.html")
	printf "%-26s bytes=%-7s denied=%s menu=%s sub=[%s] title=[%s]\n" "$p" "$(wc -c < "$out/$p.html")" "$denied" "$menu" "$sub" "$t"
done
cp "$HODIMA_WP/debug.log" "$out/debug.log" 2>/dev/null || true
grep -h "PHP " "$out/debug.log" 2>/dev/null | sed 's/^\[[^]]*\] //' | grep -v "wp_update_\|wp_version_check\|sqlite-database-integration" | cut -c1-220 | sort | uniq -c || true
