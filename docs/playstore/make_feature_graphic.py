#!/usr/bin/env python3
"""
Vérif'All — Feature graphic 1024×500  (v3 — mockups propres)
==============================================================
Pipeline :
  1. Playwright capture l'écran app depuis les prototypes HTML
  2. telephone() dessine un iPhone blanc Pillow + screen app dedans
  3. Les deux phones sont inclinés et composités sur fond vignette

Usage : python3 -I make_feature_graphic.py
"""
import io, sys
from pathlib import Path
from playwright.sync_api import sync_playwright
from PIL import Image, ImageDraw, ImageFilter, ImageFont

# ── Chemins ──────────────────────────────────────────────────────────────────
ROOT     = Path(__file__).resolve().parents[2]
PROTO    = ROOT / "verifall_laravel/resources/prototype"
FONT_DIR = ROOT / "verifall_flutter/assets/fonts"
LOGO_LIGHT = ROOT / "verifall_laravel/public/assets/images/logo-light.png"
LOGO_ICON  = ROOT / "verifall_laravel/public/assets/images/logo-icon.png"
OUT        = Path(__file__).resolve().parent / "assets/feature-graphic-1024x500.png"

# ── Palette ──────────────────────────────────────────────────────────────────
VERT_VIF     = (9,  184,  80)   # #09B850
VERT_FOND    = (6,   59,  42)   # #063B2A
VERT_PROFOND = (3,   22,  15)   # #03160F  bords vignette
VERT_GLOW    = (8,   75,  50)   # centre lueur
GRIS_CADRE   = (248, 248, 246)  # cadre iPhone
GRIS_BORD    = (200, 202, 198)  # bord fin
NOIR_NOTCH   = (10,  10,  10)
BLANC        = (255, 255, 255)

W, H = 1024, 500


# ── Utilitaire police ─────────────────────────────────────────────────────────
def _font(name: str, size: int) -> ImageFont.FreeTypeFont:
    for path in [
        str(FONT_DIR / name),
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
    ]:
        if Path(path).exists():
            try:
                return ImageFont.truetype(path, size)
            except Exception:
                continue
    return ImageFont.load_default()


def txt_center(draw, text, cx, y, font, fill):
    bb = draw.textbbox((0, 0), text, font=font)
    draw.text((cx - (bb[2]-bb[0])//2 - bb[0], y - bb[1]), text,
              font=font, fill=fill)
    return y + (bb[3] - bb[1])


# ── 1. Capture Playwright ─────────────────────────────────────────────────────
def capturer_ecran(url_path: str, viewport_w=390, viewport_h=844) -> bytes:
    """
    Capture le prototype HTML en file:// et retourne les bytes PNG.
    Résolution 2× pour la netteté.
    """
    file_url = f"file://{PROTO}/{url_path}"
    with sync_playwright() as p:
        browser = p.chromium.launch(
            executable_path="/opt/pw-browsers/chromium",
            args=["--no-sandbox", "--disable-dev-shm-usage"],
        )
        ctx = browser.new_context(
            viewport={"width": viewport_w, "height": viewport_h},
            device_scale_factor=2,
        )
        page = ctx.new_page()
        page.goto(file_url, wait_until="networkidle", timeout=15_000)
        page.wait_for_timeout(600)
        data = page.screenshot(type="png")
        browser.close()
    return data


# ── 2. Mockup iPhone propre ───────────────────────────────────────────────────
def telephone(app_bytes: bytes, tel_w: int, tel_h: int) -> Image.Image:
    """
    iPhone blanc Pillow.
    app_bytes : PNG de la capture Playwright (taille quelconque).
    Retourne RGBA (tel_w × tel_h).
    """
    CADRE   = max(12, tel_w // 17)
    COIN_R  = max(40, tel_w // 4)
    COIN_SCR = max(COIN_R - CADRE + 2, 8)

    img  = Image.new("RGBA", (tel_w, tel_h), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    # Ombre portée
    ombre_layer = Image.new("RGBA", (tel_w + 24, tel_h + 24), (0, 0, 0, 0))
    ImageDraw.Draw(ombre_layer).rounded_rectangle(
        [(12, 12), (tel_w + 11, tel_h + 11)],
        radius=COIN_R + 4, fill=(0, 0, 0, 55))
    ombre_layer = ombre_layer.filter(ImageFilter.GaussianBlur(radius=10))
    img_s = Image.new("RGBA", (tel_w + 24, tel_h + 24), (0, 0, 0, 0))
    img_s.alpha_composite(ombre_layer)

    # Cadre
    draw.rounded_rectangle([(0, 0), (tel_w - 1, tel_h - 1)],
                            radius=COIN_R,
                            fill=GRIS_CADRE,
                            outline=GRIS_BORD, width=2)

    # Écran
    sx1, sy1 = CADRE, CADRE
    sx2, sy2 = tel_w - CADRE - 1, tel_h - CADRE - 1
    scr_w, scr_h = sx2 - sx1, sy2 - sy1

    app_img = Image.open(io.BytesIO(app_bytes)).convert("RGB")
    app_img = app_img.resize((scr_w, scr_h), Image.LANCZOS)
    masque  = Image.new("L", (scr_w, scr_h), 0)
    ImageDraw.Draw(masque).rounded_rectangle(
        [(0, 0), (scr_w-1, scr_h-1)], radius=COIN_SCR, fill=255)
    img.paste(app_img, (sx1, sy1), masque)

    # Status bar blanche
    sb_h = max(42, tel_h // 20)
    sb   = Image.new("RGBA", (scr_w, sb_h), (255, 255, 255, 240))
    db   = ImageDraw.Draw(sb)
    f_sb = _font("IBMPlexMono-Regular.ttf", max(14, tel_w // 14))
    db.text((14, (sb_h - 14) // 2), "08:24", font=f_sb, fill=(20, 20, 20))
    sb_m = Image.new("L", (scr_w, sb_h), 0)
    ImageDraw.Draw(sb_m).rounded_rectangle(
        [(0, 0), (scr_w-1, sb_h-1)], radius=COIN_SCR, fill=255)
    img.paste(sb, (sx1, sy1), sb_m)

    # Dynamic Island
    di_w = int(tel_w * 0.33)
    di_h = max(20, tel_w // 11)
    di_x = (tel_w - di_w) // 2
    di_y = sy1 + 8
    draw.rounded_rectangle([(di_x, di_y), (di_x + di_w, di_y + di_h)],
                            radius=di_h // 2, fill=NOIR_NOTCH)

    # Home indicator
    hi_w = int(tel_w * 0.30)
    hi_h = 4
    draw.rounded_rectangle(
        [(tel_w//2 - hi_w//2, tel_h - CADRE - 14),
         (tel_w//2 + hi_w//2, tel_h - CADRE - 10)],
        radius=2, fill=(150, 150, 150, 200))

    # Boutons côté
    bc = (170, 172, 168, 255)
    draw.rounded_rectangle([(-3, tel_h//3), (3, tel_h//3 + 72)],
                            radius=2, fill=bc)
    for by in [tel_h//4, tel_h//4 + 90]:
        draw.rounded_rectangle([(tel_w-3, by), (tel_w+3, by+58)],
                                radius=2, fill=bc)

    # Coller sur fond ombre
    img_s.alpha_composite(img, (12, 12))
    return img_s   # (tel_w+24) × (tel_h+24), RGBA


# ── 3. Fond vignette radiale ──────────────────────────────────────────────────
def make_background() -> Image.Image:
    base = Image.new("RGB", (W, H), VERT_PROFOND)

    # Couches concentriques floutées
    for (rx, ry, col, alpha) in [
        (W * 2.0, H * 2.0, VERT_FOND,  220),
        (W * 1.1, H * 1.1, VERT_FOND,  180),
        (W * 0.6, H * 0.6, VERT_GLOW,  110),
    ]:
        layer = Image.new("RGBA", (W * 4, H * 4), (0, 0, 0, 0))
        cx, cy = W * 2, H * 2
        ImageDraw.Draw(layer).ellipse(
            [cx-rx, cy-ry, cx+rx, cy+ry], fill=(*col, alpha))
        layer = layer.filter(ImageFilter.GaussianBlur(radius=W // 1.8))
        layer = layer.resize((W, H), Image.LANCZOS)
        base = Image.alpha_composite(base.convert("RGBA"), layer).convert("RGB")

    return base


# ── 4. Rotation phone avec alpha propre ──────────────────────────────────────
def rotation(img_rgba: Image.Image, angle_deg: float) -> Image.Image:
    """PIL rotate, expand=True, fond transparent, LANCZOS."""
    return img_rgba.rotate(angle_deg, expand=True,
                            resample=Image.BICUBIC,
                            fillcolor=(0, 0, 0, 0))


# ── 5. Centre : logo + wordmark + tagline ────────────────────────────────────
def compose_center(canvas: Image.Image) -> None:
    cx = W // 2
    draw = ImageDraw.Draw(canvas)

    # Anneau lumineux très subtil
    ring = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    rd   = ImageDraw.Draw(ring)
    for (r, a) in [(160, 12), (110, 20), (65, 28)]:
        rd.ellipse([cx-r, H//2-r, cx+r, H//2+r], fill=(*VERT_VIF, a))
    ring = ring.filter(ImageFilter.GaussianBlur(radius=45))
    canvas.alpha_composite(ring)
    draw = ImageDraw.Draw(canvas)

    # Bloc vertical total pour centrage
    icon_h   = 54
    wm_w     = 206
    wm_h     = int(wm_w * 533/1600) if LOGO_LIGHT.exists() else 60
    gap1, gap2, gap3 = 8, 14, 10
    tag_h    = 26 + 6 + 26          # 2 lignes tagline
    sep_h    = 12                   # séparateur + espace
    total_h  = icon_h + gap1 + wm_h + gap2 + 1 + sep_h + tag_h
    y_start  = (H - total_h) // 2
    y = y_start

    # Icon (V blanc)
    if LOGO_ICON.exists():
        ico = Image.open(LOGO_ICON).convert("RGBA")
        r, g, b, a = ico.split()
        w255 = Image.new("L", ico.size, 255)
        ico_w = Image.merge("RGBA", [w255, w255, w255, a])
        ico_w = ico_w.resize((icon_h, icon_h), Image.LANCZOS)
        canvas.paste(ico_w, (cx - icon_h//2, y), ico_w)
    y += icon_h + gap1

    # Wordmark (logo-light blanc)
    if LOGO_LIGHT.exists():
        logo = Image.open(LOGO_LIGHT).convert("RGBA")
        r, g, b, a = logo.split()
        w255 = Image.new("L", logo.size, 255)
        logo_w = Image.merge("RGBA", [w255, w255, w255, a])
        wm_h_actual = int(logo_w.height * wm_w / logo_w.width)
        logo_w = logo_w.resize((wm_w, wm_h_actual), Image.LANCZOS)
        canvas.paste(logo_w, (cx - wm_w//2, y), logo_w)
        y += wm_h_actual
    else:
        f = _font("InstrumentSans-ExtraBold.ttf", 68)
        y = txt_center(draw, "Vérif'All", cx, y, f, BLANC)

    y += gap2

    # Séparateur fin
    sep_layer = Image.new("RGBA", (176, 1), (255, 255, 255, 70))
    canvas.alpha_composite(sep_layer, (cx - 88, y))
    draw = ImageDraw.Draw(canvas)
    y += 1 + sep_h

    # Tagline 2 lignes
    f_tag = _font("InstrumentSans-Bold.ttf", 22)
    tag_col = (205, 240, 220)
    for line in ["La boussole contre", "la désinformation numérique"]:
        y = txt_center(draw, line, cx, y, f_tag, tag_col)
        y += 6


# ── 6. Assemblage ─────────────────────────────────────────────────────────────
def main():
    print("📸  Capture Playwright : verifier.html …")
    scr_gauche = capturer_ecran("compte-user/verifier.html")

    print("📸  Capture Playwright : rapport.html …")
    scr_droite = capturer_ecran("compte-user/rapport.html")

    TEL_W  = 210   # largeur phone dans le feature graphic
    TEL_H  = 455   # hauteur phone  (~2.17 ratio, proportions iPhone)
    TILT   = 16    # degrés d'inclinaison

    print("🖼   Composition …")
    canvas = make_background().convert("RGBA")

    # Phone gauche — tilt positif (top vers la gauche)
    ph_g = telephone(scr_gauche, TEL_W, TEL_H)
    ph_g = rotation(ph_g, +TILT)
    pw_g, ph_g_h = ph_g.size
    # 17% déborde à gauche, bas sous le canvas
    x_g = -int(pw_g * 0.17)
    y_g = H - ph_g_h + int(ph_g_h * 0.07)
    canvas.alpha_composite(ph_g, (x_g, y_g))

    # Phone droit — tilt négatif (top vers la droite)
    ph_d = telephone(scr_droite, TEL_W, TEL_H)
    ph_d = rotation(ph_d, -TILT)
    pw_d, ph_d_h = ph_d.size
    x_d = W - pw_d + int(pw_d * 0.17)
    y_d = H - ph_d_h + int(ph_d_h * 0.07)
    canvas.alpha_composite(ph_d, (x_d, y_d))

    # Composition centrale
    compose_center(canvas)

    OUT.parent.mkdir(parents=True, exist_ok=True)
    canvas.convert("RGB").save(OUT, "PNG", optimize=True)
    print(f"✅  {OUT}  ({OUT.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
