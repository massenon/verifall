#!/usr/bin/env python3
"""
Vérif'All — Feature graphic 1024×500  (v2 — design expert)
============================================================
Fond vignette radiale sombre, deux téléphones inclinés avec ombre
portée, logo-wordmark centré avec anneau lumineux subtil.
"""
import math, sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter, ImageFont, ImageChops

VISUELS  = Path(__file__).resolve().parents[2] / \
           "verifall_flutter/docs/publication-play-store/visuels"
ASSETS   = Path(__file__).resolve().parent / "assets"
OUT      = ASSETS / "feature-graphic-1024x500.png"

FONT_DIR   = Path(__file__).resolve().parents[2] / "verifall_flutter/assets/fonts"
LOGO_ICON  = Path(__file__).resolve().parents[2] / \
             "verifall_laravel/public/assets/images/logo-icon.png"
LOGO_LIGHT = Path(__file__).resolve().parents[2] / \
             "verifall_laravel/public/assets/images/logo-light.png"

# ── Palette ──────────────────────────────────────────────────────────────────
VERT_VIF     = (9,  184, 80)    # #09B850
VERT_FOND    = (6,   59, 42)    # #063B2A
VERT_PROFOND = (4,   28, 20)    # #041C14  — bords vignette
VERT_GLOW    = (10,  90, 56)    # lueur centre
BLANC        = (255, 255, 255)

W, H = 1024, 500

# Coordonnées exactes du cadre iPhone dans les screenshots 1080×1920
# y1=295 : juste sous les textes titre (y~155-285) — évite le saignement
PHONE_BOX = (195, 295, 905, 1800)   # x1, y1, x2, y2  →  710×1505 px


# ── Utilitaires police ────────────────────────────────────────────────────────
def _font(filename: str, size: int) -> ImageFont.FreeTypeFont:
    p = FONT_DIR / filename
    if p.exists():
        return ImageFont.truetype(str(p), size)
    # fallback système
    for fb in ["/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
               "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf"]:
        if Path(fb).exists():
            return ImageFont.truetype(fb, size)
    return ImageFont.load_default()

def txt_center(draw, text, cx, y, font, fill, anchor="lt"):
    bb = draw.textbbox((0, 0), text, font=font)
    x = cx - (bb[2] - bb[0]) // 2 - bb[0]
    draw.text((x, y - bb[1]), text, font=font, fill=fill)
    return y + (bb[3] - bb[1])   # retourne y bas du texte


# ── Fond vignette radiale ─────────────────────────────────────────────────────
def make_background() -> Image.Image:
    """
    Vignette radiale : #041C14 aux coins → #063B2A au centre.
    Séparé en deux passes pour un effet naturel.
    """
    # 1. Base uniforme vert foncé
    img = Image.new("RGB", (W, H), VERT_PROFOND)

    # 2. Ellipse vert centre sur grand layer flou
    layer = Image.new("RGBA", (W * 4, H * 4), (0, 0, 0, 0))
    ld = ImageDraw.Draw(layer)
    cx, cy = W * 2, H * 2
    for (rx, ry, alpha) in [
        (W * 2.2, H * 2.2, 210),   # grande ellipse — couleur fond principale
        (W * 1.3, H * 1.3, 180),   # milieu
        (W * 0.7, H * 0.7, 140),   # centre légèrement plus clair
    ]:
        cols = [VERT_FOND, VERT_FOND, VERT_GLOW]
        i = [(W * 2.2, H * 2.2, 210), (W * 1.3, H * 1.3, 180), (W * 0.7, H * 0.7, 140)].index(
            (rx, ry, alpha))
        color = cols[i]
        ld.ellipse([cx - rx, cy - ry, cx + rx, cy + ry],
                   fill=(*color, alpha))
    layer = layer.filter(ImageFilter.GaussianBlur(radius=W // 2))
    layer = layer.resize((W, H), Image.LANCZOS)

    result = Image.alpha_composite(img.convert("RGBA"), layer)
    return result.convert("RGB")


# ── Extraction et préparation du téléphone ────────────────────────────────────
def extract_phone(src: Path, target_h: int) -> Image.Image:
    """Crop le cadre iPhone exact, redimensionne, retourne RGBA."""
    img = Image.open(src).convert("RGBA")
    phone = img.crop(PHONE_BOX)
    bw, bh = phone.size
    target_w = int(bw * target_h / bh)
    return phone.resize((target_w, target_h), Image.LANCZOS)


def add_shadow(phone_rgba: Image.Image, blur: int = 28,
               offset: tuple = (0, 18)) -> Image.Image:
    """
    Ajoute une ombre portée sombre sous le téléphone.
    Retourne un RGBA élargi (taille phone + marge pour l'ombre).
    """
    pw, ph = phone_rgba.size
    margin = blur * 2 + abs(offset[0]) + abs(offset[1])
    canvas = Image.new("RGBA", (pw + margin * 2, ph + margin * 2), (0, 0, 0, 0))

    # Shadow layer : silhouette noire décalée
    shadow = Image.new("RGBA", (pw, ph), (0, 0, 0, 0))
    # Utiliser l'alpha du téléphone comme silhouette
    _, _, _, alpha = phone_rgba.split()
    shadow_solid = Image.new("RGBA", (pw, ph), (0, 0, 0, 200))
    shadow_solid.putalpha(alpha)
    canvas.paste(shadow_solid,
                 (margin + offset[0], margin + offset[1]),
                 shadow_solid)
    canvas = canvas.filter(ImageFilter.GaussianBlur(radius=blur))

    # Coller le téléphone par-dessus
    canvas.paste(phone_rgba, (margin, margin), phone_rgba)
    return canvas


def tilt_phone(phone_rgba: Image.Image, angle_deg: float) -> Image.Image:
    """
    Incline le téléphone de angle_deg degrés (PIL = sens anti-horaire).
    expand=True pour ne pas couper les coins.
    Fond transparent conservé.
    """
    return phone_rgba.rotate(angle_deg, expand=True,
                             resample=Image.BICUBIC)


# ── Composition centrale ──────────────────────────────────────────────────────
def compose_center(canvas: Image.Image, cx: int) -> None:
    """
    Dessine sur canvas : anneau lumineux + logo-icon + wordmark + tagline.
    """
    draw = ImageDraw.Draw(canvas)

    # 1. Anneau lumineux derrière le logo (très subtil)
    ring_layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    rd = ImageDraw.Draw(ring_layer)
    ring_r = 150
    ring_x, ring_y = cx, H // 2 - 10
    for (r, a) in [(ring_r, 14), (ring_r - 30, 22), (ring_r - 70, 30)]:
        rd.ellipse([ring_x - r, ring_y - r, ring_x + r, ring_y + r],
                   fill=(*VERT_VIF, a))
    ring_layer = ring_layer.filter(ImageFilter.GaussianBlur(radius=40))
    canvas.alpha_composite(ring_layer)
    draw = ImageDraw.Draw(canvas)   # rafraîchir le draw

    # Position de départ (centrage vertical)
    # Bloc total ≈ icon(56) + gap(10) + wordmark(60) + gap(14) + rule(1) + gap(12) + tagline(2×26)
    BLOCK_H = 56 + 10 + 60 + 14 + 1 + 12 + 26 + 6 + 26
    y_start = (H - BLOCK_H) // 2

    y = y_start

    # 2. Logo-icon (V dans cercle)
    icon_size = 56
    if LOGO_ICON.exists():
        icon = Image.open(LOGO_ICON).convert("RGBA")
        # Rendre blanc : remplacer la teinte verte par blanc
        r, g, b, a = icon.split()
        white = Image.new("L", icon.size, 255)
        # Garder l'alpha, forcer RGB blanc
        icon_white = Image.merge("RGBA", [white, white, white, a])
        icon_white = icon_white.resize((icon_size, icon_size), Image.LANCZOS)
        canvas.paste(icon_white, (cx - icon_size // 2, y), icon_white)

    y += icon_size + 10

    # 3. Wordmark (logo-light.png — version blanche)
    wm_w = 210
    if LOGO_LIGHT.exists():
        logo = Image.open(LOGO_LIGHT).convert("RGBA")
        r, g, b, a = logo.split()
        white = Image.new("L", logo.size, 255)
        logo_w = Image.merge("RGBA", [white, white, white, a])
        wm_h = int(logo_w.height * wm_w / logo_w.width)
        logo_w = logo_w.resize((wm_w, wm_h), Image.LANCZOS)
        canvas.paste(logo_w, (cx - wm_w // 2, y), logo_w)
        y += wm_h
    else:
        f_name = _font("InstrumentSans-ExtraBold.ttf", 68)
        y = txt_center(draw, "Vérif'All", cx, y, f_name, BLANC)

    y += 14

    # 4. Séparateur fin
    rule_w = 180
    rule_color = (255, 255, 255, 80)
    sep = Image.new("RGBA", (rule_w, 1), rule_color)
    canvas.alpha_composite(sep, (cx - rule_w // 2, y))
    draw = ImageDraw.Draw(canvas)
    y += 1 + 12

    # 5. Tagline (2 lignes, InstrumentSans-Bold 22px)
    f_tag = _font("InstrumentSans-Bold.ttf", 22)
    tag_color = (200, 238, 218, 195)   # blanc légèrement teinté vert-pale
    for line in ["La boussole contre", "la désinformation numérique"]:
        y = txt_center(draw, line, cx, y,
                       f_tag, (*tag_color[:3], tag_color[3]))
        y += 6


# ── Assemblage principal ──────────────────────────────────────────────────────
def main():
    # Vérification fichiers sources
    src_left  = VISUELS / "screenshot-3-verifier-alt.png"
    src_right = VISUELS / "screenshot-4-rapport-alt.png"
    for p in [src_left, src_right]:
        if not p.exists():
            sys.exit(f"❌  Introuvable : {p}")

    PHONE_H = 440   # hauteur cible téléphone — équilibre avec espace central
    TILT    = 16    # degrés d'inclinaison

    # 1. Fond
    canvas = make_background().convert("RGBA")

    # 2. Téléphone gauche — incliné (top vers la gauche, angle positif PIL)
    ph_l = extract_phone(src_left, PHONE_H)
    ph_l = add_shadow(ph_l, blur=20, offset=(5, 18))
    ph_l = tilt_phone(ph_l, +TILT)
    pw_l, ph_l_h = ph_l.size
    # offset 0.18 : phone déborde légèrement à gauche, visible à 82%
    x_l = -int(pw_l * 0.18)
    y_l = H - ph_l_h + int(ph_l_h * 0.07)   # bottom 7% sous le canvas
    canvas.alpha_composite(ph_l, (x_l, y_l))

    # 3. Téléphone droit — incliné (top vers la droite, angle négatif PIL)
    ph_r = extract_phone(src_right, PHONE_H)
    ph_r = add_shadow(ph_r, blur=20, offset=(-5, 18))
    ph_r = tilt_phone(ph_r, -TILT)
    pw_r, ph_r_h = ph_r.size
    x_r = W - pw_r + int(pw_r * 0.18)
    y_r = H - ph_r_h + int(ph_r_h * 0.07)
    canvas.alpha_composite(ph_r, (x_r, y_r))

    # 4. Composition centrale (logo, wordmark, tagline)
    cx = W // 2
    compose_center(canvas, cx)

    # 5. Export
    ASSETS.mkdir(parents=True, exist_ok=True)
    canvas.convert("RGB").save(OUT, "PNG", optimize=True)
    print(f"✅  {OUT}  ({OUT.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
