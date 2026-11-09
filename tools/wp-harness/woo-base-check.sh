#!/usr/bin/env bash
# woo-base.css قالب در برابر CSS کامل ووکامرس، راست‌به‌چپ (woo-base-check.mjs؛ ووکامرس واقعی wc-run.sh)
#   woo-base-check.sh [page ...]      پیش‌فرض: محصول ساده/متغیر/ناموجود/حراج، دسته، زیردسته، فروشگاه، برچسب، جستجو
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="$(cd "$here/../.." && pwd)"
base="${HODIMA_HARNESS_WC:-/tmp/hodima-harness-wc}"; export HODIMA_HARNESS_WC="$base"
"$here/wc-run.sh" "$repo" "$base/woo-base-out" >/dev/null
( cd "$repo" && node "$here/woo-base-check.mjs" "$base/woo-base-out" "$repo" "$@" )
