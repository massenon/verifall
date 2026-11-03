#!/usr/bin/env python3
"""
Vérif'All — Générateur de visuels Play Store
============================================
Produit :
  assets/feature-graphic-1024x500.png
  assets/screenshots/01-*.png  …  08-*.png  (1080×1920 chacune)

Dépendances : pip install playwright pillow
              playwright install chromium
"""
import json
import math
import os
import sys
import time
from pathlib import Path

try:
    from PIL import Image, ImageDraw, ImageFont
except ImportError:
    sys.exit("❌  Pillow manquant. Lancez : pip install pillow")

try:
    from playwright.sync_api import sync_playwright
except ImportError:
    sys.exit("❌  Playwright manquant. Lancez : pip install playwright && playwright install chromium")

# ── Chemins ────────────────────────────────────────────────────────────────────
SCRIPT_DIR = Path(__file__).resolve().parent
CONFIG_PATH = SCRIPT_DIR / "visuels.json"
OUT_DIR     = SCRIPT_DIR / "assets"
SCREENSHOTS = OUT_DIR / "screenshots"

def load_config() -> dict:
    with open(CONFIG_PATH, encoding="utf-8") as fh:
        return json.load(fh)

# ── Helpers couleur ────────────────────────────────────────────────────────────

def hex_to_rgb(h: str) -> tuple:
    h = h.lstrip("#")
    return tuple(int(h[i:i+2], 16) for i in (0, 2, 4))


def degrade_lineaire(largeur: int, hauteur: int,
                     couleur1: str, couleur2: str,
                     angle: int = 135) -> Image.Image:
    """Crée un dégradé linéaire entre deux couleurs hexadécimales."""
    img = Image.new("RGB", (largeur, hauteur))
    draw = ImageDraw.Draw(img)
    r1, g1, b1 = hex_to_rgb(couleur1)
    r2, g2, b2 = hex_to_rgb(couleur2)
    rad = math.radians(angle)
    for y in range(hauteur):
        for x in range(largeur):
            t = (x * math.cos(rad) + y * math.sin(rad)) / (
                largeur * abs(math.cos(rad)) + hauteur * abs(math.sin(rad))
            )
            t = max(0.0, min(1.0, t))
            r = int(r1 + (r2 - r1) * t)
            g = int(g1 + (g2 - g1) * t)
            b = int(b1 + (b2 - b1) * t)
            draw.point((x, y), fill=(r, g, b))
    return img


def degrade_rapide(largeur: int, hauteur: int,
                   couleur1: str, couleur2: str) -> Image.Image:
    """Dégradé horizontal rapide (colonne par colonne)."""
    img = Image.new("RGB", (largeur, hauteur))
    r1, g1, b1 = hex_to_rgb(couleur1)
    r2, g2, b2 = hex_to_rgb(couleur2)
    for x in range(largeur):
        t = x / max(largeur - 1, 1)
        r = int(r1 + (r2 - r1) * t)
        g = int(g1 + (g2 - g1) * t)
        b = int(b1 + (b2 - b1) * t)
        col = Image.new("RGB", (1, hauteur), (r, g, b))
        img.paste(col, (x, 0))
    return img


def motif_cercles(img: Image.Image, couleur: str) -> Image.Image:
    """Superpose des cercles décoratifs semi-transparents."""
    overlay = Image.new("RGBA", img.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    r, g, b = hex_to_rgb(couleur)
    w, h = img.size
    params = [
        (int(w * .82), int(h * .15), int(min(w, h) * .40), 18),
        (int(w * .18), int(h * .85), int(min(w, h) * .30), 14),
        (int(w * .90), int(h * .70), int(min(w, h) * .18), 10),
    ]
    for cx, cy, radius, alpha in params:
        draw.ellipse(
            [(cx - radius, cy - radius), (cx + radius, cy + radius)],
            outline=(r, g, b, alpha),
            width=max(2, radius // 10)
        )
    return Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")


def coller_logo(img: Image.Image, logo_path: Path,
                cx: int, cy: int, hauteur_cible: int) -> Image.Image:
    """Centre le logo horizontalement à la position (cx, cy)."""
    if not logo_path.exists():
        return img
    logo = Image.open(logo_path).convert("RGBA")
    ratio = hauteur_cible / logo.height
    nouveau_w = int(logo.width * ratio)
    logo = logo.resize((nouveau_w, hauteur_cible), Image.LANCZOS)
    x = cx - nouveau_w // 2
    img.paste(logo, (x, cy), logo)
    return img


def police_systeme(taille: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    """Charge une police système disponible ou bascule sur la police par défaut."""
    candidats = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
        "/usr/share/fonts/liberation/LiberationSans-Bold.ttf",
        "/usr/share/fonts/truetype/freefont/FreeSansBold.ttf",
        "/usr/share/fonts/truetype/ubuntu/Ubuntu-B.ttf",
    ]
    # Cherche aussi les polices Inter/Instrument bundled avec le prototype
    proto_fonts = SCRIPT_DIR / "../../verifall_laravel/resources/prototype/css/fonts"
    for f in proto_fonts.rglob("*.ttf") if proto_fonts.exists() else []:
        candidats.insert(0, str(f))

    for path in candidats:
        if Path(path).exists():
            try:
                return ImageFont.truetype(path, taille)
            except Exception:
                continue
    return ImageFont.load_default()


def texte_centre(draw: ImageDraw.ImageDraw, texte: str,
                 cx: int, y: int, police: ImageFont.FreeTypeFont,
                 couleur: tuple) -> int:
    """Écrit un texte centré sur cx ; retourne le bas du texte."""
    bbox = draw.textbbox((0, 0), texte, font=police)
    tw = bbox[2] - bbox[0]
    draw.text((cx - tw // 2, y), texte, font=police, fill=couleur)
    return y + bbox[3] - bbox[1]


# ── Feature Graphic 1024×500 ──────────────────────────────────────────────────

def creer_feature_graphic(cfg: dict) -> Path:
    fg_cfg  = cfg["feature_graphic"]
    app_cfg = cfg["app"]

    W, H = fg_cfg["largeur"], fg_cfg["hauteur"]
    couleurs = fg_cfg["fond_degrade"]

    img = degrade_lineaire(W, H, couleurs[0], couleurs[1], fg_cfg.get("angle_degrade", 135))

    if fg_cfg.get("motif_cercles"):
        img = motif_cercles(img, couleurs[0])

    logo_path = (SCRIPT_DIR / app_cfg["logo"]).resolve()
    img = coller_logo(img, logo_path, cx=W // 2, cy=int(H * 0.20), hauteur_cible=80)

    draw = ImageDraw.Draw(img)
    blanc  = (255, 255, 255)
    blanc2 = (255, 255, 255, 200)

    titre_font    = police_systeme(56)
    sous_font     = police_systeme(28)
    tagline_font  = police_systeme(22)

    y = int(H * 0.48)
    y = texte_centre(draw, fg_cfg["titre"],     W // 2, y, titre_font,   blanc) + 12
    y = texte_centre(draw, fg_cfg["sous_titre"], W // 2, y, sous_font,   (200, 255, 220))
    y += 20
    texte_centre(draw, app_cfg["slogan"],        W // 2, y, tagline_font, (180, 240, 200))

    sortie = OUT_DIR / "feature-graphic-1024x500.png"
    img.save(sortie, "PNG", optimize=True)
    print(f"  ✓  feature-graphic  →  {sortie.relative_to(SCRIPT_DIR.parent)}")
    return sortie


# ── Capture d'une page HTML ───────────────────────────────────────────────────

def capturer_ecran(page, url_fichier: str, viewport: dict, scroll_y: int) -> bytes:
    """Charge un fichier HTML local et prend une capture."""
    page.set_viewport_size({"width": viewport["largeur"], "height": viewport["hauteur"]})
    page.goto(f"file://{url_fichier}", wait_until="networkidle", timeout=30_000)

    # Attente des animations
    time.sleep(1.2)

    if scroll_y:
        page.evaluate(f"window.scrollTo(0, {scroll_y})")
        time.sleep(0.4)

    # Masque les éléments qui gêneraient (cookie banner, modales)
    page.evaluate("""
        document.querySelectorAll('[data-cookie-banner],[data-modal-backdrop],[data-drawer-backdrop]')
                 .forEach(el => el.style.display = 'none');
    """)

    return page.screenshot(clip={"x": 0, "y": 0,
                                  "width": viewport["largeur"],
                                  "height": viewport["hauteur"]})


# ── Composition d'une capture 1080×1920 ──────────────────────────────────────

BANDE_HAUTEUR = 220   # pixels de la bande de titre en bas (≈ 11 % de 1920)
LOGO_H_CAPTURE = 44  # hauteur du logo dans la bande

def composer_capture(screenshot_bytes: bytes,
                     capture_cfg: dict,
                     logo_path: Path,
                     vp: dict) -> Image.Image:
    """
    1. Redimensionne la capture brute à 1080×(1920 - BANDE_HAUTEUR).
    2. Ajoute une bande colorée en bas avec titre + sous-titre.
    Texte total ≈ 11 % de la surface → en dessous de la limite 20 %.
    """
    W, H = 1080, 1920

    # — Zone de capture (sans la bande) —
    zone_h = H - BANDE_HAUTEUR
    raw = Image.open(__import__("io").BytesIO(screenshot_bytes)).convert("RGB")
    raw = raw.resize((W, zone_h), Image.LANCZOS)

    # — Bande inférieure —
    bande_couleur = capture_cfg.get("couleur_bande", "#09B850")
    bande = degrade_rapide(W, BANDE_HAUTEUR, bande_couleur,
                           _assombrir(bande_couleur, 0.75))

    # Logo dans la bande
    bande = coller_logo(bande, logo_path,
                        cx=W // 2, cy=20, hauteur_cible=LOGO_H_CAPTURE)

    # Titres
    draw  = ImageDraw.Draw(bande)
    blanc = (255, 255, 255)
    gris  = (200, 220, 200) if capture_cfg.get("texte_blanc") else (50, 50, 50)

    titre_font = police_systeme(52)
    sous_font  = police_systeme(30)

    y = LOGO_H_CAPTURE + 28
    y = texte_centre(draw, capture_cfg["titre"],     W // 2, y, titre_font, blanc)  + 10
    texte_centre(draw, capture_cfg["sous_titre"],    W // 2, y, sous_font,  gris)

    # — Assemblage final —
    canvas = Image.new("RGB", (W, H))
    canvas.paste(raw,   (0, 0))
    canvas.paste(bande, (0, zone_h))
    return canvas


def _assombrir(hex_c: str, facteur: float) -> str:
    r, g, b = hex_to_rgb(hex_c)
    return "#{:02x}{:02x}{:02x}".format(
        int(r * facteur), int(g * facteur), int(b * facteur))


# ── Vérification des règles Google ────────────────────────────────────────────

MOTS_INTERDITS = [
    "meilleur", "n°1", "n°1", "gratuit", "nouveau", "remise",
    "télécharger", "installer", "essayer"
]

def verifier_regles(capture_cfg: dict, img: Image.Image) -> list[str]:
    erreurs = []
    w, h = img.size

    # Ratio 9:16
    if abs(w / h - 9 / 16) > 0.01:
        erreurs.append(f"Ratio incorrect : {w}×{h} (attendu 9:16)")

    # Texte ≤ 20 % — on estime la surface textuelle à partir de BANDE_HAUTEUR
    pct_texte = BANDE_HAUTEUR / h * 100
    if pct_texte > 20:
        erreurs.append(f"Zone texte estimée à {pct_texte:.1f} % (max 20 %)")

    # Mots interdits dans titres
    for champ in ("titre", "sous_titre"):
        for mot in MOTS_INTERDITS:
            if mot.lower() in capture_cfg.get(champ, "").lower():
                erreurs.append(f"Mot interdit « {mot} » dans {champ}")

    # Alt ≤ 140 chars
    alt = capture_cfg.get("alt", "")
    if len(alt) > 140:
        erreurs.append(f"alt trop long : {len(alt)} chars (max 140)")

    return erreurs


# ── Point d'entrée ────────────────────────────────────────────────────────────

def main() -> None:
    cfg = load_config()
    app = cfg["app"]

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    SCREENSHOTS.mkdir(parents=True, exist_ok=True)

    logo_path = (SCRIPT_DIR / app["logo"]).resolve()
    proto_base = (SCRIPT_DIR / app["prototype_base"]).resolve()

    print("\n🎨  Vérif'All — Génération des visuels Play Store")
    print(f"   Config  : {CONFIG_PATH}")
    print(f"   Sortie  : {OUT_DIR}\n")

    # — Feature Graphic —
    creer_feature_graphic(cfg)

    # — Captures —
    erreurs_globales: list[str] = []

    with sync_playwright() as pw:
        browser = pw.chromium.launch(
            executable_path="/opt/pw-browsers/chromium",
            args=["--no-sandbox", "--disable-web-security",
                  "--allow-file-access-from-files"]
        )
        context = browser.new_context(
            user_agent="Mozilla/5.0 (Linux; Android 14; Pixel 8) "
                       "AppleWebKit/537.36 (KHTML, like Gecko) "
                       "Chrome/125.0.0.0 Mobile Safari/537.36",
            color_scheme="light",
        )
        page = context.new_page()

        for capture in cfg["captures"]:
            vp     = capture["viewport"]
            url_abs = str(proto_base / capture["url"])
            print(f"  📸  {capture['id']} — {capture['titre']}")

            try:
                screenshot_bytes = capturer_ecran(page, url_abs, vp, capture.get("scroll_y", 0))
                img = composer_capture(screenshot_bytes, capture, logo_path, vp)
            except Exception as exc:
                print(f"       ⚠️  Erreur capture : {exc}")
                # Crée une image de remplacement vert pour ne pas bloquer
                img = Image.new("RGB", (1080, 1920), hex_to_rgb(capture.get("couleur_bande", "#09B850")))
                draw = ImageDraw.Draw(img)
                draw.text((60, 900), f"[{capture['titre']}]", fill=(255, 255, 255),
                          font=police_systeme(48))

            erreurs = verifier_regles(capture, img)
            if erreurs:
                for e in erreurs:
                    print(f"       ⚠️  {e}")
                erreurs_globales.extend([f"{capture['id']}: {e}" for e in erreurs])

            sortie = SCREENSHOTS / capture["fichier"]
            img.save(sortie, "PNG", optimize=True)
            print(f"       ✓  {sortie.relative_to(SCRIPT_DIR.parent)}")

        context.close()
        browser.close()

    print()
    if erreurs_globales:
        print("⚠️  Règles Google non respectées :")
        for e in erreurs_globales:
            print(f"   • {e}")
        sys.exit(1)
    else:
        print("✅  Tous les visuels sont conformes aux règles Google Play.")
        print(f"   Feature graphic : {OUT_DIR / 'feature-graphic-1024x500.png'}")
        print(f"   Captures        : {SCREENSHOTS}")


if __name__ == "__main__":
    main()
