#!/usr/bin/env bash
# Point the harness site at a source tree (repo root containing hodima/ and plugins/).
set -euo pipefail
src="$(cd "$1" && pwd)"; wp="${HODIMA_HARNESS:-/tmp/hodima-harness}/wp"
rm -f "$wp/wp-content/themes/hodima" "$wp"/wp-content/plugins/hodima-{core,seo,commerce,media}
ln -s "$src/hodima" "$wp/wp-content/themes/hodima"
for p in core seo commerce media; do ln -s "$src/plugins/hodima-$p" "$wp/wp-content/plugins/hodima-$p"; done
