#!/usr/bin/env python3
"""
ساخت دوباره فایل‌های کوچک وزیرمتن (`Vazirmatn-<وزن>-base.woff2`) و چاپ قانون‌های @font-face.

    pip install fonttools brotli
    python3 tools/fonts/make-base-fonts.py            # فقط محاسبه و چاپ اندازه‌ها
    python3 tools/fonts/make-base-fonts.py --write    # نوشتن فایل‌های -base در hodima/assets/fonts

هر وزن دو فایل دارد (hodima/style.css، بخش ۱۷۳ HODIMA-AUDIT.md): `-base` برای نویسه‌های رایج و فایل
کامل برای بقیه. unicode-range هر دو را اینجا از cmap همان فایل کامل حساب می‌کنیم تا دو بازه هم‌پوشانی
نداشته باشند و هیچ نویسه‌ای (که فایل کامل دارد) بی‌فونت نماند. بعد از تغییر BASE_RANGES خروجی چاپ‌شده
را جای هفت جفت @font-face بخش «Local fonts» style.css بگذار و آزمون تصویری بخش ۱۷۳ را دوباره اجرا کن.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from fontTools import subset
from fontTools.ttLib import TTFont

FONTS = Path(__file__).resolve().parents[2] / "hodima" / "assets" / "fonts"
WEIGHTS = {"Light": 300, "Regular": 400, "Medium": 500, "SemiBold": 600, "Bold": 700, "ExtraBold": 800, "Black": 900}

# نویسه‌های فایل -base: ASCII، چند نماد رایج لاتین-۱ (nbsp © « » ° ± · × ÷)، بلوک عربی، نشانه‌گذاری
# عمومی، ارزها، № ™ −، علامت ریال ﷼ و BOM. لاتین گسترده/ترکیبی/شکل‌های ارائه‌ای عربی در فایل کامل می‌مانند.
BASE_RANGES: list[tuple[int, int]] = [
    (0x20, 0x7E), (0xA0, 0xA0), (0xA9, 0xA9), (0xAB, 0xAB), (0xB0, 0xB1), (0xB7, 0xB7), (0xBB, 0xBB),
    (0xD7, 0xD7), (0xF7, 0xF7), (0x600, 0x6FF), (0x750, 0x77F), (0x2000, 0x206F), (0x20A0, 0x20CF),
    (0x2116, 0x2116), (0x2122, 0x2122), (0x2212, 0x2212), (0xFDFC, 0xFDFC), (0xFEFF, 0xFEFF),
]
SKIP = {0x0, 0xD, 0xFFFC, 0xFFFD}  # نویسه‌های کنترلی/جایگزین که فایل دارد ولی متن واقعی نیستند


def to_ranges(codes) -> list[tuple[int, int]]:
    out: list[tuple[int, int]] = []
    for c in sorted(codes):
        if out and c == out[-1][1] + 1:
            out[-1] = (out[-1][0], c)
        else:
            out.append((c, c))
    return out


def fmt(ranges) -> str:
    return ", ".join(f"U+{a:X}" if a == b else f"U+{a:X}-{b:X}" for a, b in ranges)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--write", action="store_true", help="فایل‌های -base را بنویس")
    write = ap.parse_args().write

    in_base = lambda c: any(a <= c <= b for a, b in BASE_RANGES)  # noqa: E731
    rest_ranges = None
    for name in WEIGHTS:
        src = FONTS / f"Vazirmatn-{name}.woff2"
        full = set(TTFont(src).getBestCmap())
        options = subset.Options()
        options.layout_features = ["*"]  # اتصال حروف، نیم‌فاصله، kern، mark همه می‌مانند
        options.flavor = "woff2"
        options.notdef_outline = True
        options.name_IDs = ["*"]
        sub = subset.Subsetter(options)
        sub.populate(unicodes=sorted(c for c in full if in_base(c)))
        font = TTFont(src, recalcBBoxes=False)
        sub.subset(font)
        # کادر محدودکننده فونت (head/hhea) همان فونت کامل بماند: Chrome پهنای پیش‌فرض کادرهای ورودی بدون
        # CSS (size=20) را از head.xMax - xMin می‌گیرد و با کوچک‌شدن آن کادرها ۶px باریک‌تر می‌شدند
        original = TTFont(src)
        for table, keys in (("head", ("xMin", "yMin", "xMax", "yMax")), ("hhea", ("advanceWidthMax", "minLeftSideBearing", "minRightSideBearing", "xMaxExtent"))):
            for key in keys:
                setattr(font[table], key, getattr(original[table], key))
        kept = set(font.getBestCmap())
        rest = to_ranges(c for c in full if c not in kept and c not in SKIP)
        if rest_ranges is not None and rest != rest_ranges:
            print(f"هشدار: بازه فایل کامل وزن {name} با وزن‌های دیگر فرق دارد", file=sys.stderr)
        rest_ranges = rest_ranges or rest
        dst = FONTS / f"Vazirmatn-{name}-base.woff2"
        if write:
            font.flavor = "woff2"
            font.save(dst)
        size = dst.stat().st_size if dst.exists() else 0
        print(f"# {name}: کامل {src.stat().st_size} بایت ← base {size} بایت", file=sys.stderr)

    base = fmt(BASE_RANGES)
    rest = fmt(rest_ranges or [])
    for name, weight in WEIGHTS.items():
        for stem, rng in ((f"{name}-base", base), (name, rest)):
            print(
                f"@font-face {{ font-family: 'Vazirmatn'; src: url('assets/fonts/Vazirmatn-{stem}.woff2') format('woff2'); "
                f"font-weight: {weight}; font-style: normal; font-display: swap; unicode-range: {rng}; }}"
            )
    return 0


if __name__ == "__main__":
    sys.exit(main())
