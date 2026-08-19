#!/usr/bin/env python3
"""Render MH CORP logos: navy rounded-square badge, classical MH, one-line wordmark."""
from __future__ import annotations

import math
import os
from io import BytesIO

import cairo
from PIL import Image, ImageDraw, ImageFont

NAVY = (0x0B / 255, 0x3D / 255, 0x5C / 255)
GOLD = (0xC4 / 255, 0xA3 / 255, 0x5A / 255)
WHITE = (1.0, 1.0, 1.0)
NAVY_RGBA = (0x0B, 0x3D, 0x5C, 255)
GOLD_RGBA = (0xC4, 0xA3, 0x5A, 255)
WHITE_RGBA = (255, 255, 255, 255)

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
IMG = os.path.join(ROOT, "wp-content/themes/magdi-hilal-adco/assets/img")
SHOT = os.path.join(ROOT, "screenshots")
FONT_FILE = os.path.join(ROOT, "wp-content/themes/magdi-hilal-adco/assets/fonts/LiberationSerif-Bold.ttf")
FONT_FALLBACKS = (
    FONT_FILE,
    "/usr/share/fonts/truetype/liberation2/LiberationSerif-Bold.ttf",
    "/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf",
)


def serif_font(size: int) -> ImageFont.FreeTypeFont:
    """TTF only — Type1 faces like C059 render as broken Latin glyphs in cairo."""
    for path in FONT_FALLBACKS:
        if path and os.path.isfile(path):
            return ImageFont.truetype(path, size=max(8, int(round(size))))
    raise FileNotFoundError("Liberation Serif Bold TTF not found")


def rgb_to_rgba(rgb) -> tuple[int, int, int, 255]:
    return (int(rgb[0] * 255), int(rgb[1] * 255), int(rgb[2] * 255), 255)


def text_image(text: str, size: float, fill, tracking: float = 0.0) -> Image.Image:
    font = serif_font(size)
    dummy = Image.new("RGBA", (8, 8), (0, 0, 0, 0))
    draw = ImageDraw.Draw(dummy)
    if tracking <= 0:
        bbox = draw.textbbox((0, 0), text, font=font)
        w = max(1, bbox[2] - bbox[0])
        h = max(1, bbox[3] - bbox[1])
        img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
        ImageDraw.Draw(img).text((-bbox[0], -bbox[1]), text, font=font, fill=fill)
        return img
    widths = []
    height = 1
    for ch in text:
        b = draw.textbbox((0, 0), ch, font=font)
        widths.append(b[2] - b[0])
        height = max(height, b[3] - b[1])
    total = int(sum(widths) + tracking * (len(text) - 1))
    img = Image.new("RGBA", (max(1, total), height), (0, 0, 0, 0))
    painter = ImageDraw.Draw(img)
    x = 0.0
    for i, ch in enumerate(text):
        b = draw.textbbox((0, 0), ch, font=font)
        painter.text((x - b[0], -b[1]), ch, font=font, fill=fill)
        x += widths[i] + tracking
    return img


def paint_pil(ctx: cairo.Context, img: Image.Image, x: float, y: float) -> None:
    buf = BytesIO()
    img.save(buf, format="PNG")
    buf.seek(0)
    surf = cairo.ImageSurface.create_from_png(buf)
    ctx.save()
    ctx.set_source_surface(surf, x, y)
    ctx.paint()
    ctx.restore()


def set_rgb(ctx: cairo.Context, rgb) -> None:
    ctx.set_source_rgb(*rgb)


def rounded_rect(ctx: cairo.Context, x: float, y: float, w: float, h: float, r: float) -> None:
    r = min(max(0.0, r), w / 2.0, h / 2.0)
    ctx.new_path()
    ctx.move_to(x + r, y)
    ctx.line_to(x + w - r, y)
    ctx.arc(x + w - r, y + r, r, -math.pi / 2, 0)
    ctx.line_to(x + w, y + h - r)
    ctx.arc(x + w - r, y + h - r, r, 0, math.pi / 2)
    ctx.line_to(x + r, y + h)
    ctx.arc(x + r, y + h - r, r, math.pi / 2, math.pi)
    ctx.line_to(x, y + r)
    ctx.arc(x + r, y + r, r, math.pi, 3 * math.pi / 2)
    ctx.close_path()


def apply_font_options(ctx: cairo.Context) -> None:
    """No hinting — keeps M stems straight at every size instead of bowing into a crescent."""
    opts = cairo.FontOptions()
    opts.set_antialias(cairo.ANTIALIAS_BEST)
    opts.set_hint_style(cairo.HINT_STYLE_NONE)
    opts.set_hint_metrics(cairo.HINT_METRICS_OFF)
    ctx.set_font_options(opts)


def fill_rect(ctx: cairo.Context, x: float, y: float, w: float, h: float) -> None:
    ctx.rectangle(x, y, w, h)
    ctx.fill()


def thick_segment(ctx: cairo.Context, x1: float, y1: float, x2: float, y2: float, width: float) -> None:
    dx, dy = x2 - x1, y2 - y1
    length = math.hypot(dx, dy) or 1.0
    nx, ny = (-dy / length) * (width / 2.0), (dx / length) * (width / 2.0)
    ctx.new_path()
    ctx.move_to(x1 + nx, y1 + ny)
    ctx.line_to(x2 + nx, y2 + ny)
    ctx.line_to(x2 - nx, y2 - ny)
    ctx.line_to(x1 - nx, y1 - ny)
    ctx.close_path()
    ctx.fill()


def draw_mh_letters(ctx: cairo.Context, cx: float, cy: float, letter_h: float, ink) -> None:
    """
    Classical MH: vertical rectangular stems, straight V, slab serifs.
    Outer edges of M are vertical — it cannot read as a crescent or half-moon.
    """
    sw = letter_h * 0.130
    st = sw * 0.40
    ser = sw * 0.72
    m_w = letter_h * 1.08
    h_w = letter_h * 0.84
    gap = letter_h * 0.30
    total = m_w + gap + h_w
    x0 = cx - total / 2.0
    y0 = cy - letter_h / 2.0

    set_rgb(ctx, ink)

    def stem(x: float) -> None:
        fill_rect(ctx, x, y0, sw, letter_h)

    def serifs(x: float, top_l: bool, top_r: bool, bot_l: bool, bot_r: bool) -> None:
        tw = sw + (ser if top_l else 0.0) + (ser if top_r else 0.0)
        tx = x - (ser if top_l else 0.0)
        fill_rect(ctx, tx, y0, tw, st)
        bw = sw + (ser if bot_l else 0.0) + (ser if bot_r else 0.0)
        bx = x - (ser if bot_l else 0.0)
        fill_rect(ctx, bx, y0 + letter_h - st, bw, st)

    ml = x0
    mr = x0 + m_w - sw
    stem(ml)
    stem(mr)
    serifs(ml, True, False, True, True)
    serifs(mr, False, True, True, True)
    # Straight diagonals into a sharp valley — open counters, not a filled bow.
    mid_x = x0 + m_w / 2.0
    valley_y = y0 + letter_h * 0.74
    thick_segment(ctx, ml + sw, y0 + st * 0.2, mid_x, valley_y, sw)
    thick_segment(ctx, mr, y0 + st * 0.2, mid_x, valley_y, sw)

    hx = x0 + m_w + gap
    hl = hx
    hr = hx + h_w - sw
    stem(hl)
    stem(hr)
    serifs(hl, True, True, True, True)
    serifs(hr, True, True, True, True)
    bar_h = sw * 0.92
    bar_y = y0 + letter_h * 0.48 - bar_h / 2.0
    fill_rect(ctx, hl + sw - 0.4, bar_y, (hr - hl - sw) + 0.8, bar_h)


def draw_monogram(ctx: cairo.Context, cx: float, cy: float, s: float, ink) -> None:
    """Centered MH monogram, optically centered, fully inside the gold ring."""
    letter_h = s * 0.34
    draw_mh_letters(ctx, cx, cy - s * 0.008, letter_h, ink)


def draw_badge(ctx: cairo.Context, x: float, y: float, s: float, fill, ring, ink, filled: bool) -> None:
    """Rounded-square corporate seal. Frame geometry is unchanged — only the inner mark differs."""
    ctx.save()
    corner = s * 0.16
    ring_w = max(2.4, s * 0.038)
    if filled and fill is not None:
        set_rgb(ctx, fill)
        rounded_rect(ctx, x, y, s, s, corner)
        ctx.fill()

    set_rgb(ctx, ring)
    ctx.set_line_width(ring_w)
    ctx.set_line_join(cairo.LINE_JOIN_ROUND)
    inset = ring_w * 0.5 + s * 0.042
    inner_r = max(4.0, corner - inset * 0.55)
    rounded_rect(ctx, x + inset, y + inset, s - 2 * inset, s - 2 * inset, inner_r)
    ctx.stroke()

    draw_monogram(ctx, x + s * 0.50, y + s * 0.505, s, ink)
    ctx.restore()


def wordmark_layers(name_color, corp_color, scale: float):
    """One-line TTF wordmark: MH CORP. corp_color kept for lockup API; line uses name_color."""
    del corp_color
    size = 54 * scale
    line = text_image("MH CORP", size, rgb_to_rgba(name_color), tracking=3.2 * scale)
    return line


def measure_wordmark(scale: float) -> tuple[float, float]:
    line = wordmark_layers(NAVY, GOLD, scale)
    return float(line.width), float(line.height)


def draw_wordmark(ctx: cairo.Context, x: float, y: float, name_color, corp_color, scale: float) -> None:
    line = wordmark_layers(name_color, corp_color, scale)
    paint_pil(ctx, line, x, y)


def render_surface(w: int, h: int) -> cairo.ImageSurface:
    return cairo.ImageSurface(cairo.FORMAT_ARGB32, w, h)


def crop_alpha(surf: cairo.ImageSurface, pad: int = 28, square: bool = False) -> cairo.ImageSurface:
    surf.flush()
    w, h = surf.get_width(), surf.get_height()
    buf = surf.get_data()
    stride = surf.get_stride()
    minx, miny, maxx, maxy = w, h, 0, 0
    found = False
    for y in range(h):
        row = y * stride
        for x in range(w):
            a = buf[row + x * 4 + 3]
            if a > 12:
                found = True
                if x < minx:
                    minx = x
                if y < miny:
                    miny = y
                if x > maxx:
                    maxx = x
                if y > maxy:
                    maxy = y
    if not found:
        return surf
    minx = max(0, minx - pad)
    miny = max(0, miny - pad)
    maxx = min(w - 1, maxx + pad)
    maxy = min(h - 1, maxy + pad)
    if square:
        side = max(maxx - minx + 1, maxy - miny + 1)
        cx = (minx + maxx) // 2
        cy = (miny + maxy) // 2
        minx = max(0, cx - side // 2)
        miny = max(0, cy - side // 2)
        maxx = min(w - 1, minx + side - 1)
        maxy = min(h - 1, miny + side - 1)
        minx = max(0, maxx - side + 1)
        miny = max(0, maxy - side + 1)
    cw, ch = maxx - minx + 1, maxy - miny + 1
    out = cairo.ImageSurface(cairo.FORMAT_ARGB32, cw, ch)
    ctx = cairo.Context(out)
    ctx.set_source_surface(surf, -minx, -miny)
    ctx.paint()
    return out


def save(surf: cairo.ImageSurface, path: str, crop: bool = True, square: bool = False) -> None:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    if crop:
        surf = crop_alpha(surf, square=square)
    surf.write_to_png(path)
    print("wrote", path, surf.get_width(), "x", surf.get_height())


def make_lockup(fill, ring, ink, name_color, corp_color, filled: bool) -> cairo.ImageSurface:
    w, h = 2400, 640
    surf = render_surface(w, h)
    ctx = cairo.Context(surf)
    ctx.set_antialias(cairo.ANTIALIAS_BEST)
    apply_font_options(ctx)
    scale = 2.55
    name_w, block_h = measure_wordmark(scale)
    del name_w
    # Badge taller than the one-line wordmark so the header stays ~64px without overflowing.
    mark = block_h * 1.62
    mx = 40.0
    my = (h - mark) / 2.0
    draw_badge(ctx, mx, my, mark, fill, ring, ink, filled)
    draw_wordmark(ctx, mx + mark + 40, my + (mark - block_h) / 2.0, name_color, corp_color, scale)
    return surf


def make_header() -> None:
    surf = make_lockup(NAVY, GOLD, GOLD, NAVY, GOLD, True)
    save(surf, os.path.join(IMG, "logo.png"))
    save(surf, os.path.join(IMG, "logo-header.png"))
    save(surf, os.path.join(SHOT, "00-logo.png"))


def make_footer() -> None:
    surf = make_lockup(None, GOLD, GOLD, WHITE, GOLD, False)
    save(surf, os.path.join(IMG, "logo-white.png"))
    save(surf, os.path.join(SHOT, "00-logo-white.png"))


def make_mark() -> None:
    s = 1024
    surf = render_surface(s, s)
    ctx = cairo.Context(surf)
    ctx.set_antialias(cairo.ANTIALIAS_BEST)
    apply_font_options(ctx)
    pad = 36
    draw_badge(ctx, pad, pad, s - 2 * pad, NAVY, GOLD, GOLD, True)
    save(surf, os.path.join(IMG, "logo-mark.png"), square=True)
    save(surf, os.path.join(SHOT, "00-logo-mark.png"), square=True)


if __name__ == "__main__":
    make_header()
    make_footer()
    make_mark()
