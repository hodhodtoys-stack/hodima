#!/usr/bin/env bash
# ساخت فایل‌های ZIP قابل نصب از پیشخوان وردپرس
#   قالب:    نمایش ← پوسته‌ها ← افزودن ← بارگذاری پوسته
#   افزونه‌ها: افزونه‌ها ← افزودن ← بارگذاری افزونه
# خروجی: dist/*.zip
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
dist="$root/dist"
rm -rf "$dist" && mkdir -p "$dist"

package() {
	local src_parent="$1" name="$2"
	( cd "$src_parent" && zip -qr "$dist/$name.zip" "$name" -x '*.DS_Store' '*/.git*' )
	echo "✔ dist/$name.zip"
}

package "$root" hodima
for plugin in hodima-core hodima-seo hodima-commerce hodima-media; do
	package "$root/plugins" "$plugin"
done
