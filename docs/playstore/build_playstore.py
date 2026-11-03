#!/usr/bin/env python3
"""
Vérif'All — Générateur de visuels Play Store
============================================
Style : fond vert #063B2A · maquette iPhone blanche · titre bold en haut
         (reproduit l'esthétique de verifall_flutter/docs/publication-play-store/visuels/)

Produit :
  assets/feature-graphic-1024x500.png   (dégradé + 2 phones + logo)
  assets/screenshots/01-*.png … 08-*.png (1080×1920, ratio 9:16)

Dépendances : pip install playwright pillow
              playwright install chromium   (chromium déjà présent sur ce poste)
"""
import io
import math
import os
import sys
import time
from pathlib import Path

try:
    from PIL import Image, ImageDraw, ImageFilter, ImageFont
except ImportError:
    sys.exit("❌  Pillow manquant : pip install pillow")

try:
    from playwright.sync_api import sync_playwright
except ImportError:
    sys.exit("❌  Playwright manquant : pip install playwright && playwright install chromium")

# ── Chemins ────────────────────────────────────────────────────────────────────
SCRIPT_DIR  = Path(__file__).resolve().parent
CONFIG_DIR  = SCRIPT_DIR
OUT_DIR     = SCRIPT_DIR / "assets"
SCREENSHOTS = OUT_DIR / "screenshots"
PROTO_BASE  = (SCRIPT_DIR / "../../verifall_laravel/resources/prototype").resolve()
LOGO_PNG    = (SCRIPT_DIR / "../../verifall_laravel/public/assets/images/logo.png").resolve()
ICON_PNG    = (SCRIPT_DIR / "../../verifall_laravel/public/assets/images/logo-icon.png").resolve()

# ── Palette ────────────────────────────────────────────────────────────────────
VERT_SOMBRE  = (6,  59, 42)    # #063B2A
VERT_CENTRE  = (11, 85, 58)    # légèrement plus clair pour l'effet radial
VERT_VIF     = (9, 184, 80)    # #09B850
BLANC        = (255, 255, 255)
BLANC_DEMI   = (255, 255, 255, 180)
GRIS_CADRE   = (242, 244, 240) # cadre téléphone (blanc cassé)
GRIS_BORD    = (190, 195, 188) # bord extérieur du cadre
NOIR_NOTCH   = (10, 10, 10)    # Dynamic Island

# ── Typographie ────────────────────────────────────────────────────────────────
CANDIDATS_GRAS = [
    "/home/user/verifall/verifall_laravel/resources/prototype/css/fonts/InstrumentSans-Bold.ttf",
    "/home/user/verifall/verifall_laravel/resources/prototype/css/fonts/InstrumentSans-ExtraBold.ttf",
    "/home/user/verifall/verifall_flutter/assets/fonts/InstrumentSans-Bold.ttf",
    "/home/user/verifall/verifall_flutter/assets/fonts/InstrumentSans-ExtraBold.ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
    "/usr/share/fonts/truetype/freefont/FreeSansBold.ttf",
    "/usr/share/fonts/truetype/ubuntu/Ubuntu-B.ttf",
    "/usr/share/fonts/liberation/LiberationSans-Bold.ttf",
]
CANDIDATS_NORMAL = [
    "/home/user/verifall/verifall_laravel/resources/prototype/css/fonts/Inter-Regular.ttf",
    "/home/user/verifall/verifall_flutter/assets/fonts/Inter-Regular.ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
    "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
    "/usr/share/fonts/truetype/freefont/FreeSans.ttf",
    "/usr/share/fonts/truetype/ubuntu/Ubuntu-R.ttf",
]

def _charger_police(candidats: list, taille: int) -> ImageFont.FreeTypeFont:
    for p in candidats:
        if Path(p).exists():
            try:
                return ImageFont.truetype(p, taille)
            except Exception:
                continue
    return ImageFont.load_default()

def police_gras(taille: int) -> ImageFont.FreeTypeFont:
    return _charger_police(CANDIDATS_GRAS, taille)

def police_normale(taille: int) -> ImageFont.FreeTypeFont:
    return _charger_police(CANDIDATS_NORMAL, taille)


# ── Fond vert foncé radial ─────────────────────────────────────────────────────

def fond_vert(w: int, h: int) -> Image.Image:
    """Fond vert #063B2A avec lueur centrale légèrement plus claire."""
    base = Image.new("RGB", (w, h), VERT_SOMBRE)
    glow = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    draw = ImageDraw.Draw(glow)
    cx, cy = w // 2, int(h * 0.4)
    r = int(max(w, h) * 0.55)
    # Plusieurs ellipses de plus en plus opaques vers le centre
    for i, alpha in [(1.0, 18), (0.65, 35), (0.35, 50)]:
        ri = int(r * i)
        draw.ellipse([(cx - ri, cy - ri), (cx + ri, cy + ri)],
                     fill=(*VERT_CENTRE, alpha))
    glow = glow.filter(ImageFilter.GaussianBlur(radius=int(min(w, h) * 0.18)))
    result = Image.alpha_composite(base.convert("RGBA"), glow)
    return result.convert("RGB")


# ── Icône logo V ──────────────────────────────────────────────────────────────

def icone_v(taille: int) -> Image.Image:
    """Cercle vert avec V blanc — logo icon de Vérif'All."""
    if ICON_PNG.exists():
        try:
            img = Image.open(ICON_PNG).convert("RGBA")
            img = img.resize((taille, taille), Image.LANCZOS)
            return img
        except Exception:
            pass
    # Fallback dessiné
    img = Image.new("RGBA", (taille, taille), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    draw.ellipse([(0, 0), (taille - 1, taille - 1)], fill=(*VERT_VIF, 255))
    # V blanc simple
    m = taille // 2
    pts_gauche = [(int(taille * 0.22), int(taille * 0.30)),
                  (int(taille * 0.44), int(taille * 0.68))]
    pts_droite = [(int(taille * 0.44), int(taille * 0.68)),
                  (int(taille * 0.78), int(taille * 0.27))]
    lw = max(2, taille // 9)
    draw.line(pts_gauche, fill=BLANC, width=lw)
    draw.line(pts_droite, fill=BLANC, width=lw)
    return img


# ── Badge pill "VÉRIF'ALL" ─────────────────────────────────────────────────────

def badge_label(texte: str = "VÉRIF'ALL", taille_font: int = 28) -> Image.Image:
    font = police_gras(taille_font)
    dummy = ImageDraw.Draw(Image.new("RGB", (1, 1)))
    bb = dummy.textbbox((0, 0), texte, font=font)
    tw, th = bb[2] - bb[0], bb[3] - bb[1]
    pad_h, pad_v = 22, 12
    w, h = tw + pad_h * 2, th + pad_v * 2
    img = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    draw.rounded_rectangle([(0, 0), (w - 1, h - 1)],
                            radius=h // 2,
                            outline=BLANC, width=2)
    draw.text((pad_h - bb[0], pad_v - bb[1]), texte, font=font, fill=BLANC)
    return img


# ── Texte centré / gauche ──────────────────────────────────────────────────────

def coller_image(canvas: Image.Image, overlay: Image.Image, x: int, y: int) -> None:
    """Colle overlay (RGBA) sur canvas à (x, y)."""
    if overlay.mode != "RGBA":
        overlay = overlay.convert("RGBA")
    canvas.paste(overlay, (x, y), overlay)


def texte_gauche(draw: ImageDraw.ImageDraw, lignes: list[str],
                 x: int, y: int, font: ImageFont.FreeTypeFont,
                 couleur: tuple, interligne: int = 8) -> int:
    """Écrit plusieurs lignes alignées à gauche ; retourne le bas du dernier texte."""
    for ligne in lignes:
        bb = draw.textbbox((0, 0), ligne, font=font)
        draw.text((x - bb[0], y - bb[1]), ligne, font=font, fill=couleur)
        y += bb[3] - bb[1] + interligne
    return y


def texte_centre(draw: ImageDraw.ImageDraw, texte: str,
                 cx: int, y: int, font: ImageFont.FreeTypeFont,
                 couleur: tuple) -> int:
    """Texte centré sur cx ; retourne le bas."""
    bb = draw.textbbox((0, 0), texte, font=font)
    draw.text((cx - (bb[2] - bb[0]) // 2 - bb[0],
               y - bb[1]), texte, font=font, fill=couleur)
    return y + bb[3] - bb[1]


# ── Maquette téléphone (iPhone style) ─────────────────────────────────────────

def telephone(app_screenshot: bytes, tel_w: int, tel_h: int) -> Image.Image:
    """
    Crée une maquette iPhone blanc avec la capture app à l'intérieur.
    Retourne une image RGBA de taille (tel_w, tel_h).
    """
    CADRE    = 14          # épaisseur du cadre (px)
    COIN_R   = 54          # rayon des coins extérieurs
    COIN_SCR = COIN_R - CADRE + 2  # rayon des coins de l'écran

    img  = Image.new("RGBA", (tel_w, tel_h), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    # — Ombre portée subtile —
    ombre = Image.new("RGBA", (tel_w + 20, tel_h + 20), (0, 0, 0, 0))
    d_ombre = ImageDraw.Draw(ombre)
    d_ombre.rounded_rectangle([(10, 10), (tel_w + 9, tel_h + 9)],
                               radius=COIN_R + 4,
                               fill=(0, 0, 0, 45))
    ombre = ombre.filter(ImageFilter.GaussianBlur(radius=8))
    img_avec_ombre = Image.new("RGBA", (tel_w + 20, tel_h + 20), (0, 0, 0, 0))
    img_avec_ombre.paste(ombre, (0, 0), ombre)

    # — Cadre téléphone —
    draw.rounded_rectangle([(0, 0), (tel_w - 1, tel_h - 1)],
                            radius=COIN_R,
                            fill=GRIS_CADRE,
                            outline=GRIS_BORD, width=2)

    # — Écran (zone intérieure) —
    sx1, sy1 = CADRE, CADRE
    sx2, sy2 = tel_w - CADRE - 1, tel_h - CADRE - 1
    scr_w    = sx2 - sx1
    scr_h    = sy2 - sy1

    # Coller le screenshot app dans l'écran
    app_img = Image.open(io.BytesIO(app_screenshot)).convert("RGB")
    app_img = app_img.resize((scr_w, scr_h), Image.LANCZOS)
    # Masque arrondi
    masque = Image.new("L", (scr_w, scr_h), 0)
    ImageDraw.Draw(masque).rounded_rectangle(
        [(0, 0), (scr_w - 1, scr_h - 1)], radius=COIN_SCR, fill=255)
    img.paste(app_img, (sx1, sy1), masque)

    # — Barre de statut (blanche) —
    sb_h = 48
    sb = Image.new("RGBA", (scr_w, sb_h), (255, 255, 255, 255))
    db = ImageDraw.Draw(sb)
    f_sb = police_normale(20)
    db.text((18, 14), "08:24", font=f_sb, fill=(10, 10, 10))
    # Icônes signal, wifi, batterie (simplifiées)
    for i, sym in enumerate(["▪▪▪", "◈", "▭"]):
        db.text((scr_w - 90 + i * 26, 14), sym, font=f_sb, fill=(30, 30, 30))
    # Masque arrondi pour la barre de statut
    sb_masque = Image.new("L", (scr_w, sb_h), 0)
    ImageDraw.Draw(sb_masque).rounded_rectangle(
        [(0, 0), (scr_w - 1, sb_h - 1)], radius=COIN_SCR, fill=255)
    img.paste(sb, (sx1, sy1), sb_masque)

    # — Dynamic Island —
    di_w, di_h = int(tel_w * 0.35), 26
    di_x = (tel_w - di_w) // 2
    di_y = sy1 + 10
    draw.rounded_rectangle([(di_x, di_y), (di_x + di_w, di_y + di_h)],
                            radius=di_h // 2,
                            fill=NOIR_NOTCH)

    # — Home indicator —
    hi_w, hi_h = int(tel_w * 0.32), 5
    hi_x = (tel_w - hi_w) // 2
    hi_y = tel_h - CADRE - 16
    draw.rounded_rectangle([(hi_x, hi_y), (hi_x + hi_w, hi_y + hi_h)],
                            radius=hi_h // 2,
                            fill=(140, 140, 140, 200))

    # — Boutons latéraux —
    btn_color = (160, 162, 158, 255)
    draw.rounded_rectangle([(-3, tel_h // 3), (3, tel_h // 3 + 80)],
                            radius=2, fill=btn_color)
    for by in [tel_h // 4, tel_h // 4 + 95]:
        draw.rounded_rectangle([(tel_w - 3, by), (tel_w + 3, by + 65)],
                                radius=2, fill=btn_color)

    # Intégrer dans l'image avec ombre
    img_avec_ombre.paste(img, (10, 10), img)
    return img_avec_ombre


# ── Composition d'une capture 1080×1920 ──────────────────────────────────────

def composer_capture(screenshot_bytes: bytes, cfg: dict) -> Image.Image:
    """
    Fond vert · icône logo · badge · titre bold · sous-titre · téléphone centré.
    """
    W, H = 1080, 1920
    canvas = fond_vert(W, H).convert("RGBA")
    draw   = ImageDraw.Draw(canvas)

    MARGE   = 68   # marge gauche
    ICONE_S = 58   # taille icône V
    Y_DEBUT = 68   # y du premier élément

    # — Icône V —
    icone = icone_v(ICONE_S)
    coller_image(canvas, icone, MARGE, Y_DEBUT)

    # — Badge "VÉRIF'ALL" —
    badge = badge_label("VÉRIF'ALL", taille_font=26)
    coller_image(canvas, badge, MARGE, Y_DEBUT + ICONE_S + 24)

    y_texte = Y_DEBUT + ICONE_S + 24 + badge.height + 30

    # — Titre bold (2 lignes max) —
    titre_font  = police_gras(84)
    sous_font   = police_normale(42)
    tagline_font = police_normale(32)

    titre_lignes = cfg.get("titre_lignes", [cfg.get("titre", "Vérif'All")])
    y_texte = texte_gauche(draw, titre_lignes, MARGE, y_texte,
                           titre_font, BLANC, interligne=6)
    y_texte += 22

    sous_titre = cfg.get("sous_titre", "")
    if sous_titre:
        y_texte = texte_gauche(draw, [sous_titre], MARGE, y_texte,
                               sous_font, (200, 225, 210), interligne=0)

    # — Téléphone —
    TEL_W = 694
    TEL_H = 1188
    TEL_X = (W - TEL_W) // 2
    TEL_Y = max(y_texte + 40, 660)   # positionné juste sous le texte

    tel_img = telephone(screenshot_bytes, TEL_W, TEL_H)
    # tel_img a une ombre qui agrandit de 20px de chaque côté
    canvas.paste(tel_img, (TEL_X - 10, TEL_Y - 10), tel_img)

    # — Tagline en bas —
    tagline = "La boussole contre la désinformation numérique"
    texte_centre(draw, tagline, W // 2, H - 58, tagline_font,
                 (150, 190, 160))

    return canvas.convert("RGB")


# ── Feature Graphic 1024×500 (style Xomiton : 2 phones + logo centré) ─────────

def feature_graphic_xomiton(screen_gauche: bytes, screen_droite: bytes) -> Image.Image:
    """
    Dégradé vert · téléphone gauche · logo centré · téléphone droit.
    """
    W, H = 1024, 500

    # Dégradé gauche→droite : vert vif → vert sombre
    canvas = Image.new("RGB", (W, H))
    r1, g1, b1 = VERT_VIF
    r2, g2, b2 = VERT_SOMBRE
    for x in range(W):
        t = x / (W - 1)
        col = Image.new("RGB", (1, H), (
            int(r1 + (r2 - r1) * t),
            int(g1 + (g2 - g1) * t),
            int(b1 + (b2 - b1) * t),
        ))
        canvas.paste(col, (x, 0))

    # Lueur centrale
    glow = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(glow)
    cx, cy = W // 2, H // 2
    for r, a in [(300, 25), (200, 40), (120, 55)]:
        d.ellipse([(cx - r, cy - r), (cx + r, cy + r)],
                  fill=(*VERT_CENTRE, a))
    glow = glow.filter(ImageFilter.GaussianBlur(radius=60))
    canvas = Image.alpha_composite(canvas.convert("RGBA"), glow).convert("RGB")

    # — Maquettes téléphone (tailles réduites pour la feature graphic) —
    TEL_W_FG, TEL_H_FG = 195, 380

    tel_g = telephone(screen_gauche,  TEL_W_FG, TEL_H_FG).convert("RGBA")
    tel_d = telephone(screen_droite,  TEL_W_FG, TEL_H_FG).convert("RGBA")

    # Positionnement : légèrement bas pour effet "émerge du bas"
    y_tel = H - TEL_H_FG + 55
    canvas_rgba = canvas.convert("RGBA")
    # Téléphone gauche (légèrement visible, partiellement à gauche)
    coller_image(canvas_rgba, tel_g, 28, y_tel)
    # Téléphone droit (dépasse à droite)
    coller_image(canvas_rgba, tel_d, W - TEL_W_FG - 48, y_tel)

    # — Logo Vérif'All centré —
    draw = ImageDraw.Draw(canvas_rgba)
    cx = W // 2

    if LOGO_PNG.exists():
        logo = Image.open(LOGO_PNG).convert("RGBA")
        # Rendre le logo blanc (pour le thème sombre)
        logo_w = 280
        logo_h = int(logo.height * logo_w / logo.width)
        logo = logo.resize((logo_w, logo_h), Image.LANCZOS)
        # Appliquer un filtre pour passer en blanc
        r_ch, g_ch, b_ch, a_ch = logo.split()
        logo_blanc = Image.merge("RGBA", [
            Image.new("L", logo.size, 255),
            Image.new("L", logo.size, 255),
            Image.new("L", logo.size, 255),
            a_ch,
        ])
        y_logo = int(H * 0.22)
        canvas_rgba.paste(logo_blanc, (cx - logo_w // 2, y_logo), logo_blanc)
        y_slogan = y_logo + logo_h + 18
    else:
        titre_font = police_gras(72)
        y_slogan = texte_centre(draw, "Vérif'All", cx, int(H * 0.22),
                                titre_font, BLANC) + 14

    # — Slogan —
    slogan_font = police_normale(34)
    y_slogan = texte_centre(draw, "La boussole contre la désinformation", cx,
                             y_slogan, slogan_font, (210, 250, 225))
    texte_centre(draw, "numérique", cx, y_slogan + 8, slogan_font,
                 (210, 250, 225))

    return canvas_rgba.convert("RGB")


# ── Capture Playwright ─────────────────────────────────────────────────────────

def capturer(page, fichier_html: str, viewport_w: int, viewport_h: int,
             scroll_y: int = 0) -> bytes:
    page.set_viewport_size({"width": viewport_w, "height": viewport_h})
    page.goto(f"file://{fichier_html}", wait_until="networkidle", timeout=30_000)
    time.sleep(1.0)
    if scroll_y:
        page.evaluate(f"window.scrollTo(0, {scroll_y})")
        time.sleep(0.4)
    page.evaluate("""
        document.querySelectorAll(
            '[data-cookie-banner],[data-modal-backdrop],[data-drawer-backdrop]'
        ).forEach(e => e.style.display = 'none');
    """)
    return page.screenshot(clip={"x": 0, "y": 0,
                                  "width": viewport_w, "height": viewport_h})


# ── Configuration des 8 captures ──────────────────────────────────────────────

CAPTURES = [
    {
        "fichier":    "01-verifier.png",
        "url":        "compte-user/verifier.html",
        "titre_lignes": ["Texte, image ou vidéo,", "vérifiez tout"],
        "sous_titre": "Soumettez un contenu douteux et obtenez un verdict sourcé.",
        "alt":        "Écran de soumission Vérif'All : choisissez le type de contenu à analyser",
        "scroll_y":   0,
    },
    {
        "fichier":    "02-analyse.png",
        "url":        "compte-user/verification-en-cours.html",
        "titre_lignes": ["L'IA analyse", "pour vous"],
        "sous_titre": "5 moteurs · Sources croisées · En quelques secondes.",
        "alt":        "Écran d'analyse Vérif'All : progression en temps réel des 5 étapes de vérification",
        "scroll_y":   0,
    },
    {
        "fichier":    "03-rapport.png",
        "url":        "compte-user/rapport.html",
        "titre_lignes": ["Un verdict clair", "et vérifiable"],
        "sous_titre": "Preuves et moteurs d'analyse à l'appui du résultat.",
        "alt":        "Rapport de vérification Vérif'All : verdict, score de fiabilité et analyse complète",
        "scroll_y":   0,
    },
    {
        "fichier":    "04-historique.png",
        "url":        "compte-user/mes-verifications.html",
        "titre_lignes": ["Toutes vos", "vérifications"],
        "sous_titre": "Historique · Filtres · Partage du rapport.",
        "alt":        "Historique des vérifications Vérif'All : liste filtrable avec verdicts et dates",
        "scroll_y":   0,
    },
    {
        "fichier":    "05-dashboard.png",
        "url":        "compte-user/dashboard.html",
        "titre_lignes": ["Votre tableau", "de bord"],
        "sous_titre": "Statistiques personnelles · Badges · Progression.",
        "alt":        "Tableau de bord Vérif'All : KPI personnels, badges et activité récente",
        "scroll_y":   0,
    },
    {
        "fichier":    "06-catalogue.png",
        "url":        "compte-user/catalogue.html",
        "titre_lignes": ["Apprenez à repérer", "les fausses infos"],
        "sous_titre": "Cours · Quizz · Certificats officiels reconnus.",
        "alt":        "Catalogue de cours Vérif'All : formations à la vérification d'information",
        "scroll_y":   0,
    },
    {
        "fichier":    "07-outils.png",
        "url":        "compte-user/outils.html",
        "titre_lignes": ["Posez vos questions,", "la communauté répond"],
        "sous_titre": "Forum citoyen · Échanges vérifiés · Entraide.",
        "alt":        "Outils et détecteurs Vérif'All : IA, deepfakes, géolocalisation, sources",
        "scroll_y":   0,
    },
    {
        "fichier":    "08-accueil.png",
        "url":        "compte-user/dashboard.html",
        "titre_lignes": ["Des opportunités vérifiées,", "saisir les avantages"],
        "sous_titre": "Bourses · Emplois · Informations fiables à portée.",
        "alt":        "Page d'accueil Vérif'All : plateforme béninoise de fact-checking citoyen",
        "scroll_y":   200,
    },
]


# ── Vérification Google Play ───────────────────────────────────────────────────

MOTS_INTERDITS = ["meilleur", "n°1", "gratuit", "nouveau", "remise",
                  "télécharger", "installer", "essayer"]

def verifier(cfg: dict, img: Image.Image) -> list[str]:
    errs = []
    w, h = img.size
    if abs(w / h - 9 / 16) > 0.01:
        errs.append(f"Ratio {w}×{h} ≠ 9:16")
    for champ in ("titre_lignes", "sous_titre", "alt"):
        val = cfg.get(champ, "")
        texte = " ".join(val) if isinstance(val, list) else val
        for mot in MOTS_INTERDITS:
            if mot in texte.lower():
                errs.append(f"Mot interdit « {mot} » dans {champ}")
    if len(cfg.get("alt", "")) > 140:
        errs.append(f"alt trop long ({len(cfg['alt'])} > 140)")
    return errs


# ── Point d'entrée ────────────────────────────────────────────────────────────

def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    SCREENSHOTS.mkdir(parents=True, exist_ok=True)

    print("\n🎨  Vérif'All — Play Store · génération des visuels")
    print(f"   Proto   : {PROTO_BASE}")
    print(f"   Sortie  : {OUT_DIR}\n")

    # Collecte de toutes les captures brutes
    raw: dict[str, bytes] = {}
    erreurs: list[str] = []

    with sync_playwright() as pw:
        browser = pw.chromium.launch(
            executable_path="/opt/pw-browsers/chromium",
            args=["--no-sandbox", "--disable-web-security",
                  "--allow-file-access-from-files"],
        )
        ctx  = browser.new_context(color_scheme="light")
        page = ctx.new_page()

        for cfg in CAPTURES:
            url_abs = str(PROTO_BASE / cfg["url"])
            print(f"  📸  {cfg['fichier'][:20]:22} {cfg['titre_lignes'][0]}")
            try:
                shot = capturer(page, url_abs, 390, 844, cfg.get("scroll_y", 0))
            except Exception as exc:
                print(f"       ⚠️  capture échouée : {exc}")
                shot = bytes()
            raw[cfg["fichier"]] = shot

        ctx.close()
        browser.close()

    # — Feature Graphic —
    print(f"\n  🖼   feature-graphic-1024x500.png")
    screen_g = raw.get(CAPTURES[0]["fichier"], b"")
    screen_d = raw.get(CAPTURES[2]["fichier"], b"")
    if not screen_g:
        screen_g = list(raw.values())[0] if raw else b""
    if not screen_d:
        screen_d = list(raw.values())[-1] if raw else b""

    if screen_g and screen_d:
        fg = feature_graphic_xomiton(screen_g, screen_d)
    else:
        fg = fond_vert(1024, 500)
    fg_path = OUT_DIR / "feature-graphic-1024x500.png"
    fg.save(fg_path, "PNG", optimize=True)
    print(f"       ✓  {fg_path.relative_to(SCRIPT_DIR.parent)}")

    # — Captures 1080×1920 —
    print()
    for cfg in CAPTURES:
        shot = raw.get(cfg["fichier"], b"")
        if shot:
            img = composer_capture(shot, cfg)
        else:
            img = fond_vert(1080, 1920)

        errs = verifier(cfg, img)
        for e in errs:
            print(f"       ⚠️  {e}")
            erreurs.append(f"{cfg['fichier']}: {e}")

        sortie = SCREENSHOTS / cfg["fichier"]
        img.save(sortie, "PNG", optimize=True)
        print(f"  ✓  {sortie.relative_to(SCRIPT_DIR.parent)}")

    print()
    if erreurs:
        print("⚠️  Règles Google non respectées :")
        for e in erreurs:
            print(f"   • {e}")
        sys.exit(1)
    else:
        print("✅  Tous les visuels sont conformes aux règles Google Play.\n")


if __name__ == "__main__":
    main()
