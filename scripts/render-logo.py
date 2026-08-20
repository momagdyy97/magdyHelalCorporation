#!/usr/bin/env python3
"""Render HELAL CORP wordmark logos (header/footer) and a square H mark.

Header/footer PNGs are only the one-line wordmark: navy (or white) HELAL + gold CORP.
No rounded-square badge, no MH letters. Liberation Serif Bold TTF only
(Type1 faces like C059 garble Latin in cairo).
Favicon mark: gold serif H on a navy rounded square.
"""
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
    """TTF only — Type1 faces like C059 render as broken Latin glyphs."""
    return ImageFont.truetype(serif_path(), size=max(8, int(round(size))))


def rgb_to_rgba(rgb) -> tuple[int, int, int, int]:
    return (int(round(rgb[0] * 255)), int(round(rgb[1] * 255)), int(round(rgb[2] * 255)), 255)


def glyph(ch: str, font: ImageFont.FreeTypeFont, fill) -> tuple[Image.Image, int, int]:
    dummy = Image.new("RGBA", (8, 8), (0, 0, 0, 0))
    bbox = ImageDraw.Draw(dummy).textbbox((0, 0), ch, font=font)
    w = max(1, bbox[2] - bbox[0])
    h = max(1, bbox[3] - bbox[1])
    img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    ImageDraw.Draw(img).text((-bbox[0], -bbox[1]), ch, font=font, fill=fill)
    return img, bbox[1], bbox[3]


def paste(canvas: Image.Image, piece: Image.Image, x: float, y: float) -> None:
    canvas.alpha_composite(piece, (int(round(x)), int(round(y))))


def compose_wordmark(helal_fill, corp_fill, *, size: float) -> Image.Image:
    """One line: HELAL + CORP. No divider, no M.H, no badge."""
    font = serif_font(size)
    helal = [glyph(ch, font, helal_fill) for ch in "HELAL"]
    corp = [glyph(ch, font, corp_fill) for ch in "CORP"]
    tracking = size * 0.012
    word_gap = size * 0.16

    tops = [g[1] for g in helal + corp]
    bots = [g[2] for g in helal + corp]
    top, bot = min(tops), max(bots)
    run_w = (
        sum(g[0].width for g in helal)
        + tracking * (len(helal) - 1)
        + word_gap
        + sum(g[0].width for g in corp)
        + tracking * (len(corp) - 1)
    )
    pad = int(round(size * 0.06))
    canvas = Image.new(
        "RGBA",
        (int(math.ceil(run_w)) + pad * 2 + 4, int(math.ceil(bot - top)) + pad * 2 + 4),
        (0, 0, 0, 0),
    )
    origin_y = pad - top
    x = float(pad)

    def paint(glyphs):
        nonlocal x
        for i, (g_img, g_top, _g_bot) in enumerate(glyphs):
            paste(canvas, g_img, x, origin_y + g_top)
            x += g_img.width
            if i < len(glyphs) - 1:
                x += tracking

    paint(helal)
    x += word_gap
    paint(corp)
    bbox = canvas.getbbox()
    return canvas.crop(bbox) if bbox else canvas


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


def render_surface(w: int, h: int) -> cairo.ImageSurface:
    return cairo.ImageSurface(cairo.FORMAT_ARGB32, w, h)


def crop_alpha(surf: cairo.ImageSurface, pad_x: int = 16, pad_y: int = 8, square: bool = False) -> cairo.ImageSurface:
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
    minx = max(0, minx - pad_x)
    miny = max(0, miny - pad_y)
    maxx = min(w - 1, maxx + pad_x)
    maxy = min(h - 1, maxy + pad_y)
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


def save(surf: cairo.ImageSurface, path: str, crop: bool = True, square: bool = False) -> cairo.ImageSurface:
    os.makedirs(os.path.dirname(path), exist_ok=True)
    if crop:
        surf = crop_alpha(surf, square=square)
    surf.write_to_png(path)
    print("wrote", path, surf.get_width(), "x", surf.get_height())
    return surf


def make_wordmark(helal_color, corp_color) -> cairo.ImageSurface:
    """Wordmark only — HELAL CORP on a transparent canvas."""
    line = compose_wordmark(rgb_to_rgba(helal_color), rgb_to_rgba(corp_color), size=280)
    pad_x, pad_y = 24, 16
    w = line.width + pad_x * 2
    h = line.height + pad_y * 2
    surf = render_surface(w, h)
    ctx = cairo.Context(surf)
    ctx.set_antialias(cairo.ANTIALIAS_BEST)
    paint_pil(ctx, line, pad_x, pad_y)
    return surf


def make_header() -> cairo.ImageSurface:
    surf = make_wordmark(NAVY, GOLD)
    save(surf, os.path.join(IMG, "logo.png"))
    save(surf, os.path.join(IMG, "logo-header.png"))
    save(surf, os.path.join(SHOT, "00-logo.png"))
    return save(surf, os.path.join(SHOT, "00b-logo-header.png"))


def make_footer() -> cairo.ImageSurface:
    surf = make_wordmark(WHITE, GOLD)
    save(surf, os.path.join(IMG, "logo-white.png"))
    save(surf, os.path.join(SHOT, "00-logo-white.png"))
    return save(surf, os.path.join(SHOT, "00c-logo-footer.png"))


def make_mark() -> cairo.ImageSurface:
    """Gold serif H on a navy rounded square — not the old MH badge."""
    s = 1024
    surf = render_surface(s, s)
    ctx = cairo.Context(surf)
    ctx.set_antialias(cairo.ANTIALIAS_BEST)

    corner = s * 0.16
    set_rgb(ctx, NAVY)
    rounded_rect(ctx, 0, 0, s, s, corner)
    ctx.fill()

    ring_w = max(2.4, s * 0.038)
    set_rgb(ctx, GOLD)
    ctx.set_line_width(ring_w)
    ctx.set_line_join(cairo.LINE_JOIN_ROUND)
    inset = ring_w * 0.5 + s * 0.042
    inner_r = max(4.0, corner - inset * 0.55)
    rounded_rect(ctx, inset, inset, s - 2 * inset, s - 2 * inset, inner_r)
    ctx.stroke()

    h_img, _top, _bot = glyph("H", serif_font(s * 0.54), GOLD_RGBA)
    paint_pil(ctx, h_img, (s - h_img.width) / 2.0, (s - h_img.height) / 2.0 - s * 0.01)
    save(surf, os.path.join(IMG, "logo-mark.png"), crop=False)
    return save(surf, os.path.join(SHOT, "00-logo-mark.png"), crop=False)


if __name__ == "__main__":
    header = make_header()
    make_footer()
    make_mark()
    display_h = 40
    display_w = round(header.get_width() * display_h / header.get_height())
    print(f"header display attrs: width={display_w} height={display_h}")
    print(f"aspect width/height={header.get_width() / header.get_height():.3f} (css max 240/40=6.0)")
