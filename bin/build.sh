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

# قالب: CSS سورس مدرن است؛ در کپی قالب برای مرورگرهای قدیمی پایین آورده و
# minify می‌شود (bin/build-css.mjs، Lightning CSS) و هم‌ارزی هر قانون با سورس
# در Chromium بررسی می‌شود (tools/wp-harness/css-equiv.mjs). بدون node_modules:
#   npm ci   (یک بار؛ در محیط ابری مثل GitHub Actions خودکار)
[ -d "$root/node_modules/lightningcss" ] || ( cd "$root" && npm ci --no-audit --no-fund --silent )
stage="$dist/.stage"
mkdir -p "$stage" && cp -r "$root/hodima" "$stage/hodima"
( cd "$root" && node bin/build-css.mjs "$stage/hodima" )
( cd "$root" && node tools/wp-harness/css-equiv.mjs "$root/hodima" "$stage/hodima" >"$dist/css-equiv.log" ) || {
	cat "$dist/css-equiv.log"
	echo "✘ CSS ساخته‌شده با سورس هم‌ارز نیست (بالا)؛ ZIP قالب ساخته نشد" >&2
	exit 1
}
echo "✔ CSS ساخته‌شده با سورس هم‌ارز است"
package "$stage" hodima
rm -rf "$stage"
for plugin in hodima-core hodima-seo hodima-commerce hodima-media; do
	package "$root/plugins" "$plugin"
done

# انتشار: ZIPها و فایل‌های JSON به‌روزرسانی در release/
mkdir -p "$root/release"
cp "$dist"/*.zip "$root/release/"
python3 "$root/bin/build-meta.py"
