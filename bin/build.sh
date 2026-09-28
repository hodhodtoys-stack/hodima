#!/usr/bin/env bash
# ساخت فایل‌های ZIP قابل نصب از پیشخوان وردپرس
#   قالب:    نمایش ← پوسته‌ها ← افزودن ← بارگذاری پوسته
#   افزونه‌ها: افزونه‌ها ← افزودن ← بارگذاری افزونه
# خروجی: dist/*.zip و نسخه انتشار در release/ (ZIP + JSON به‌روزرسانی خودکار)
#
# به‌روزرسانی خودکار سایت‌ها (Hodima Core → includes/updates.php) فایل‌های
# release/*.json را از شاخه پیش‌فرض گیت‌هاب می‌خواند؛ فقط وقتی نسخه جدید
# نشان داده می‌شود که «Version» بسته تغییرکرده بالا رفته باشد.
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

# انتشار: ZIPها و فایل‌های JSON به‌روزرسانی در release/
mkdir -p "$root/release"
cp "$dist"/*.zip "$root/release/"
python3 "$root/bin/build-meta.py"
