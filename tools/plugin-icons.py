#!/usr/bin/env python3
"""ساخت آیکون افزونه‌های هدیما برای صفحه «پیشخوان ← به‌روزرسانی‌ها».

وردپرس آیکون افزونه را از اطلاعات به‌روزرسانی (release/<slug>.json، کلید
icons) می‌خواند؛ bin/build-meta.py هر فایلی که اینجا ساخته شود را خودکار در
JSON می‌گذارد. اندازه‌ها همان استاندارد وردپرس: 128×128 (1x) و 256×256 (2x).
فقط PNG (بدون SVG): دو اندازه برای صفحه‌نمایش معمولی و پرتراکم کافی است.

طرح: مربع یک‌رنگ از پالت برند + حرف H سفید وسط (فونت Inter ExtraBold).
رنگ #b6c2f3 پالت عمدا استفاده نشده: H سفید رویش خوانا نیست.

اجرا (فقط وقتی طرح یا رنگ عوض شود؛ نیاز: Pillow و فونت Inter):
    python3 tools/plugin-icons.py
"""
import os
import sys

from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
OUT = os.path.join(ROOT, 'release', 'icons')
FONT = os.environ.get('HODIMA_ICON_FONT', '/usr/share/fonts/opentype/inter/Inter-ExtraBold.otf')

# نامک افزونه => رنگ پس‌زمینه (پالت برند)
COLORS = {
    'hodima-core': '#25316a',      # اصلی برند — Core پایه بقیه است
    'hodima-seo': '#607bbd',
    'hodima-commerce': '#6a2b9a',
    'hodima-media': '#a341c8',
}
SIZES = (128, 256)
SCALE = 4  # بزرگ کشیدن و کوچک کردن ← لبه‌های نرم‌تر


def icon(color: str, size: int) -> Image.Image:
    big = size * SCALE
    im = Image.new('RGB', (big, big), color)
    draw = ImageDraw.Draw(im)
    font = ImageFont.truetype(FONT, round(big * 0.66))
    # وسط‌چین بر اساس کادر واقعی حرف (نه خط پایه فونت)
    left, top, right, bottom = draw.textbbox((0, 0), 'H', font=font)
    draw.text(((big - (right - left)) / 2 - left, (big - (bottom - top)) / 2 - top), 'H', font=font, fill='#ffffff')
    return im.resize((size, size), Image.Resampling.LANCZOS)


def main() -> None:
    if not os.path.exists(FONT):
        sys.exit(f'فونت پیدا نشد: {FONT} (HODIMA_ICON_FONT=... را بده)')
    os.makedirs(OUT, exist_ok=True)
    for slug, color in COLORS.items():
        for size in SIZES:
            path = os.path.join(OUT, f'{slug}-{size}.png')
            icon(color, size).save(path, optimize=True)
            print(f'✔ release/icons/{slug}-{size}.png')


if __name__ == '__main__':
    main()
