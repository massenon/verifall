#!/usr/bin/env python3
"""
Vérif'All — Feature graphic 1024×500
======================================
Prend deux screenshots -alt existants (verifier + rapport),
en extrait la zone téléphone, les incline et les compose
sur un dégradé vert, avec le logo centré.
Style : Xomiton (phones inclinés émergeant du bas).

Usage : python3 make_feature_graphic.py
"""
import math, sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter, ImageFont

VISUELS  = Path(__file__).resolve().parent.parent.parent / \
           "verifall_flutter/docs/publication-play-store/visuels"
OUT      = Path(__file__).resolve().parent / "assets/feature-graphic-1024x500.png"
LOGO_PNG = Path(__file__).resolve().parent.parent.parent / \
           "verifall_laravel/public/assets/images/logo-light.png"

# Couleurs
VERT_VIF   = (9, 184, 80)    # #09B850
VERT_FOND  = (6,  59, 42)    # #063B2A
VERT_CLAIR = (10, 80, 56)    # centre radial
BLANC      = (255, 255, 255)

W, H = 1024, 500

# ── Fond dégradé diagonal vert vif → vert sombre ─────────────────────────────
def fond() -> Image.Image:
    img = Image.new("RGB", (W, H), VERT_FOND)
    # Dégradé horizontal gauche→droite
    r1,g1,b1 = VERT_VIF
    r2,g2,b2 = VERT_FOND
    for x in range(W):
        t = x / (W - 1)
        col = Image.new("RGB", (1, H), (
            int(r1 + (r2-r1)*t),
            int(g1 + (g2-g1)*t),
            int(b1 + (b2-b1)*t),
        ))
        img.paste(col, (x, 0))
    # Lueur radiale centrale
    glow = Image.new("RGBA", (W, H), (0,0,0,0))
    d = ImageDraw.Draw(glow)
    cx, cy = W//2, H//2
    for r, a in [(350, 20), (220, 38), (120, 55)]:
        d.ellipse([(cx-r, cy-r),(cx+r, cy+r)], fill=(*VERT_CLAIR, a))
    glow = glow.filter(ImageFilter.GaussianBlur(radius=70))
    result = Image.alpha_composite(img.convert("RGBA"), glow)
    return result.convert("RGB")


# ── Extraction de la zone téléphone ──────────────────────────────────────────
# Coordonnées approximatives du phone dans les screenshots 1080×1920
# mesurées sur les -alt : phone ≈ (155, 450, 825, 1875)
PHONE_BOX = (155, 450, 825, 1875)

def extraire_phone(src_path: Path, target_h: int) -> Image.Image:
    """Crop la zone téléphone, redimensionne à target_h, retourne RGBA."""
    img = Image.open(src_path).convert("RGBA")
    phone = img.crop(PHONE_BOX)
    bw, bh = phone.size
    target_w = int(bw * target_h / bh)
    return phone.resize((target_w, target_h), Image.LANCZOS)


def rotation_avec_fond(img: Image.Image, angle: float,
                        bg: tuple) -> Image.Image:
    """
    Tourne img de `angle` degrés.
    Le fond transparent est remplacé par bg pour que les coins
    se fondent dans le dégradé vert.
    """
    # Fond de la même couleur que le dégradé
    bg_img = Image.new("RGBA", img.size, (*bg, 255))
    bg_img.paste(img, mask=img.split()[3])  # alpha mask
    # Rotation avec expand=True pour ne pas couper les coins
    rotated = bg_img.rotate(-angle, expand=True, resample=Image.BICUBIC)
    return rotated.convert("RGBA")


# ── Police ───────────────────────────────────────────────────────────────────
def police(taille: int, gras: bool = False) -> ImageFont.FreeTypeFont:
    candidats = []
    if gras:
        candidats += [
            str(Path(__file__).parent.parent.parent /
                "verifall_flutter/assets/fonts/InstrumentSans-Bold.ttf"),
            str(Path(__file__).parent.parent.parent /
                "verifall_laravel/resources/prototype/css/fonts/InstrumentSans-Bold.ttf"),
            "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
            "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
        ]
    else:
        candidats += [
            str(Path(__file__).parent.parent.parent /
                "verifall_flutter/assets/fonts/Inter-Regular.ttf"),
            "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
            "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
        ]
    for p in candidats:
        if Path(p).exists():
            try:
                return ImageFont.truetype(p, taille)
            except Exception:
                continue
    return ImageFont.load_default()


def texte_centre(draw, texte, cx, y, font, couleur):
    bb = draw.textbbox((0,0), texte, font=font)
    tw = bb[2] - bb[0]
    draw.text((cx - tw//2 - bb[0], y - bb[1]), texte, font=font, fill=couleur)
    return y + bb[3] - bb[1]


# ── Assemblage ───────────────────────────────────────────────────────────────
def main():
    verifier_path = VISUELS / "screenshot-3-verifier-alt.png"
    rapport_path  = VISUELS / "screenshot-4-rapport-alt.png"

    if not verifier_path.exists() or not rapport_path.exists():
        sys.exit(f"❌  Fichiers introuvables dans {VISUELS}")

    canvas = fond().convert("RGBA")
    draw   = ImageDraw.Draw(canvas)

    PHONE_H  = 460   # hauteur du téléphone dans le feature graphic
    ANGLE    = 18    # degrés d'inclinaison

    # — Téléphone gauche (écran vérifier, incliné vers la droite) —
    ph_g = extraire_phone(verifier_path, PHONE_H)
    ph_g_rot = rotation_avec_fond(ph_g, -ANGLE, VERT_VIF)   # top left = left tilt
    # Position : émerge du bord gauche et du bas
    pw_g, ph_g_h = ph_g_rot.size
    x_g = -int(pw_g * 0.20)    # dépasse à gauche
    y_g = H - ph_g_h + int(ph_g_h * 0.10)   # dépasse en bas
    canvas.paste(ph_g_rot, (x_g, y_g), ph_g_rot)

    # — Téléphone droit (écran rapport, incliné vers la gauche) —
    ph_d = extraire_phone(rapport_path, PHONE_H)
    ph_d_rot = rotation_avec_fond(ph_d, ANGLE, VERT_FOND)   # top right = right tilt
    pw_d, ph_d_h = ph_d_rot.size
    x_d = W - pw_d + int(pw_d * 0.20)   # dépasse à droite
    y_d = H - ph_d_h + int(ph_d_h * 0.10)
    canvas.paste(ph_d_rot, (x_d, y_d), ph_d_rot)

    # Redessiner le draw après les pastes
    draw = ImageDraw.Draw(canvas)

    # — Logo centré —
    cx = W // 2

    # Essaye d'abord logo-light.png (fond blanc sur transparent = blanc sur vert)
    logo_chargé = False
    for logo_path in [
        Path(__file__).parent.parent.parent /
            "verifall_laravel/public/assets/images/logo-light.png",
        Path(__file__).parent.parent.parent /
            "verifall_laravel/public/assets/images/logo.png",
    ]:
        if logo_path.exists():
            logo = Image.open(logo_path).convert("RGBA")
            # Rendre les pixels sombres blancs (logo sur fond sombre)
            r, g, b, a = logo.split()
            # logo-light.png est déjà blanc ; logo.png est vert/sombre
            logo_blanc = Image.merge("RGBA", [
                Image.new("L", logo.size, 255),
                Image.new("L", logo.size, 255),
                Image.new("L", logo.size, 255),
                a,
            ])
            target_w = 280
            target_h = int(logo_blanc.height * target_w / logo_blanc.width)
            logo_blanc = logo_blanc.resize((target_w, target_h), Image.LANCZOS)
            y_logo = int(H * 0.20)
            canvas.paste(logo_blanc, (cx - target_w//2, y_logo), logo_blanc)
            y_slogan = y_logo + target_h + 16
            logo_chargé = True
            draw = ImageDraw.Draw(canvas)
            break

    if not logo_chargé:
        f_titre = police(64, gras=True)
        y_slogan = texte_centre(draw, "Vérif'All", cx, int(H * 0.20),
                                f_titre, BLANC) + 12

    # — Slogan —
    f_slogan = police(32)
    y_slogan = texte_centre(draw,
        "La boussole contre la désinformation numérique",
        cx, y_slogan, f_slogan, (200, 240, 218))

    OUT.parent.mkdir(parents=True, exist_ok=True)
    canvas.convert("RGB").save(OUT, "PNG", optimize=True)
    print(f"✅  {OUT}")


if __name__ == "__main__":
    main()
