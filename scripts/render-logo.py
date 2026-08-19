#!/usr/bin/env python3
"""Render the M.H CORP wordmark lockup and a square M.H mark (favicon).

One identity: a single crafted wordmark. No badge beside matching letters.
"""
from __future__ import annotations

import os

from PIL import Image, ImageDraw, ImageFont

NAVY = (0x0B, 0x3D, 0x5C, 255)
GOLD = (0xC4, 0xA3, 0x5A, 255)
WHITE = (255, 255, 255, 255)

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
IMG = os.path.join(ROOT, "wp-content/themes/magdi-hilal-adco/assets/img")
SHOT = os.path.join(ROOT, "screenshots")
FONT_FILE = os.path.join(
    ROOT, "wp-content/themes/magdi-hilal-adco/assets/fonts/LiberationSerif-Bold.ttf"
)
FONT_FALLBACKS = (
    FONT_FILE,
    "/usr/share/fonts/truetype/liberation2/LiberationSerif-Bold.ttf",
    "/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf",
)


def serif_path() -> str:
    for path in FONT_FALLBACKS:
        if path and os.path.isfile(path):
            return path
    raise FileNotFoundError("Liberation Serif Bold TTF not found")


def serif_font(size: float) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(serif_path(), size=max(8, int(round(size))))


def glyph(ch: str, font: ImageFont.FreeTypeFont, fill) -> tuple[Image.Image, int, int]:
    """Tight glyph image plus top/bottom offsets relative to the font baseline."""
    dummy = Image.new("RGBA", (8, 8), (0, 0, 0, 0))
    bbox = ImageDraw.Draw(dummy).textbbox((0, 0), ch, font=font)
    w = max(1, bbox[2] - bbox[0])
    h = max(1, bbox[3] - bbox[1])
    img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    ImageDraw.Draw(img).text((-bbox[0], -bbox[1]), ch, font=font, fill=fill)
    return img, bbox[1], bbox[3]


def paste(canvas: Image.Image, piece: Image.Image, x: int, y: int) -> None:
    canvas.alpha_composite(piece, (int(round(x)), int(round(y))))


def crop_pad(img: Image.Image, pad_x: int, pad_y: int, square: bool = False) -> Image.Image:
    bbox = img.getbbox()
    if not bbox:
        return img
    l, t, r, b = bbox
    l = max(0, l - pad_x)
    t = max(0, t - pad_y)
    r = min(img.width, r + pad_x)
    b = min(img.height, b + pad_y)
    if square:
        side = max(r - l, b - t)
        cx = (l + r) / 2.0
        cy = (t + b) / 2.0
        l = int(round(cx - side / 2.0))
        t = int(round(cy - side / 2.0))
        r = l + side
        b = t + side
        out = Image.new("RGBA", (side, side), (0, 0, 0, 0))
        out.alpha_composite(img, (-l, -t))
        return out
    return img.crop((l, t, r, b))


def save(img: Image.Image, path: str) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    img.save(path, "PNG")
    print("wrote", path, img.width, "x", img.height)


def compose_wordmark(mh_fill, corp_fill, hairline_fill, *, size: float = 220.0) -> Image.Image:
    """Single-line lockup: navy (or white) M.H, gold hairline, gold CORP."""
    mh_font = serif_font(size)
    corp_font = serif_font(size)

    m_img, m_top, m_bot = glyph("M", mh_font, mh_fill)
    p_img, p_top, p_bot = glyph(".", mh_font, mh_fill)
    h_img, h_top, h_bot = glyph("H", mh_font, mh_fill)

    corp_glyphs = [glyph(ch, corp_font, corp_fill) for ch in "CORP"]

    cap_top = min(m_top, h_top)
    cap_bot = max(m_bot, h_bot)
    cap_h = cap_bot - cap_top

    # Tight, even air around the periods; slight optical lift so the dots do not sit heavy.
    gap_letter_dot = size * 0.034
    period_lift = size * 0.05
    gap_mh_line = size * 0.185
    hair_w = max(5, int(round(size * 0.028)))
    hair_h = cap_h * 0.70
    gap_line_corp = size * 0.185
    corp_tracking = size * 0.048

    corp_w = sum(g[0].width for g in corp_glyphs) + corp_tracking * (len(corp_glyphs) - 1)
    tops = [m_top, p_top - period_lift, h_top] + [g[1] for g in corp_glyphs]
    bots = [m_bot, p_bot - period_lift, h_bot] + [g[2] for g in corp_glyphs]
    top, bot = min(tops), max(bots)

    width = (
        m_img.width
        + gap_letter_dot
        + p_img.width
        + gap_letter_dot
        + h_img.width
        + gap_mh_line
        + hair_w
        + gap_line_corp
        + corp_w
    )
    height = bot - top
    pad = int(round(size * 0.55))
    canvas = Image.new("RGBA", (int(width) + pad * 2 + 8, int(height) + pad * 2 + 8), (0, 0, 0, 0))

    origin_y = pad - top
    x = float(pad)

    paste(canvas, m_img, x, origin_y + m_top)
    x += m_img.width + gap_letter_dot
    paste(canvas, p_img, x, origin_y + p_top - period_lift)
    x += p_img.width + gap_letter_dot
    paste(canvas, h_img, x, origin_y + h_top)
    x += h_img.width + gap_mh_line

    hair_x = int(round(x))
    hair_y = int(round(origin_y + cap_top + (cap_h - hair_h) / 2.0))
    draw = ImageDraw.Draw(canvas)
    draw.rectangle(
        [hair_x, hair_y, hair_x + hair_w - 1, hair_y + int(round(hair_h)) - 1],
        fill=hairline_fill,
    )
    x += hair_w + gap_line_corp

    for i, (g_img, g_top, _g_bot) in enumerate(corp_glyphs):
        paste(canvas, g_img, x, origin_y + g_top)
        x += g_img.width
        if i < len(corp_glyphs) - 1:
            x += corp_tracking

    # Generous side padding, tighter vertical so CSS height maps to the letters.
    return crop_pad(canvas, pad_x=int(round(size * 0.20)), pad_y=int(round(size * 0.055)))


def compose_mark(*, size: int = 1024) -> Image.Image:
    """Square M.H mark for favicon — same letters, not a second competing seal."""
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    inset = int(round(size * 0.06))
    box = [inset, inset, size - inset - 1, size - inset - 1]
    radius = int(round(size * 0.18))
    draw.rounded_rectangle(box, radius=radius, fill=NAVY)

    font_size = size * 0.34
    font = serif_font(font_size)
    m_img, m_top, m_bot = glyph("M", font, GOLD)
    p_img, p_top, p_bot = glyph(".", font, GOLD)
    h_img, h_top, h_bot = glyph("H", font, GOLD)
    gap = font_size * 0.04
    lift = font_size * 0.05
    run_w = m_img.width + gap + p_img.width + gap + h_img.width
    cap_top = min(m_top, h_top)
    cap_bot = max(m_bot, h_bot)
    x0 = (size - run_w) / 2.0
    # Optical center: serif caps sit slightly heavy at the bottom.
    y_base = (size / 2.0) - (cap_top + cap_bot) / 2.0 - size * 0.012

    paste(img, m_img, x0, y_base + m_top)
    x0 += m_img.width + gap
    paste(img, p_img, x0, y_base + p_top - lift)
    x0 += p_img.width + gap
    paste(img, h_img, x0, y_base + h_top)
    return img


def make_header() -> Image.Image:
    surf = compose_wordmark(NAVY, GOLD, GOLD, size=220)
    save(surf, os.path.join(IMG, "logo.png"))
    save(surf, os.path.join(IMG, "logo-header.png"))
    save(surf, os.path.join(SHOT, "00-logo.png"))
    return surf


def make_footer() -> Image.Image:
    surf = compose_wordmark(WHITE, GOLD, GOLD, size=220)
    save(surf, os.path.join(IMG, "logo-white.png"))
    save(surf, os.path.join(SHOT, "00-logo-white.png"))
    return surf


def make_mark() -> Image.Image:
    surf = compose_mark(size=1024)
    save(surf, os.path.join(IMG, "logo-mark.png"))
    save(surf, os.path.join(SHOT, "00-logo-mark.png"))
    return surf


if __name__ == "__main__":
    header = make_header()
    make_footer()
    make_mark()
    display_h = 56
    display_w = round(header.width * display_h / header.height)
    print(f"header display attrs: width={display_w} height={display_h}")
