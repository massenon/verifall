# Dossier Play Store — Vérif'All

> Dernière mise à jour : 5 novembre 2026 · Branche : `claude/keen-wozniak-hq7tv7`

Ce dossier produit et documente les visuels et documents nécessaires à la
publication de l'application sur le Google Play Store.

---

## Contenu du dossier

```
docs/playstore/
├── README.md                               ← ce fichier
├── PUBLICATION_PLAY_STORE.md/.pdf          ← guide complet de publication (avec visuels)
├── Fiche-publication-Play-Store-Verifall.md/.pdf   ← fiche récap prête à coller
├── make_feature_graphic.py                 ← script de génération du feature graphic
├── build_playstore.py                      ← script de génération des screenshots alt
├── visuels.json                            ← métadonnées des visuels
└── assets/
    ├── feature-graphic-1024x500.png        ← bannière Play Store (v3 finale)
    └── screenshots/                        ← 6 captures téléphone numérotées
        ├── 01-verifier.png
        ├── 02-tableau-de-bord.png
        ├── 03-rapport.png
        ├── 04-accueil.png
        ├── 05-forum.png
        └── 06-hub-opportunites.png
```

Les 7 mêmes visuels sont copiés dans le dossier de livraison Flutter :
`verifall_flutter/docs/publication-play-store/visuels/`

---

## Visuels livrés

| Fichier | Dimensions | Usage |
|---|---|---|
| `feature-graphic-1024x500.png` | 1 024 × 500 px | Bannière principale Play Store |
| `01-verifier.png` | 1 080 × 1 920 px | Capture 1 — Page de vérification |
| `02-tableau-de-bord.png` | 1 080 × 1 920 px | Capture 2 — Tableau de bord |
| `03-rapport.png` | 1 080 × 1 920 px | Capture 3 — Rapport de vérification |
| `04-accueil.png` | 1 080 × 1 920 px | Capture 4 — Accueil |
| `05-forum.png` | 1 080 × 1 920 px | Capture 5 — Forum communautaire |
| `06-hub-opportunites.png` | 1 080 × 1 920 px | Capture 6 — Hub opportunités |

---

## Feature graphic — Comment régénérer

```bash
cd docs/playstore
python3 -I make_feature_graphic.py
```

**Prérequis :**
- `playwright` Python installé (`pip install playwright`)
- `Pillow` installé (`pip install Pillow`)
- Chromium Playwright présent (`playwright install chromium`)
- Les prototypes HTML disponibles dans `verifall_laravel/resources/prototype/`

Le script :
1. Capture `compte-user/verifier.html` et `compte-user/rapport.html` via Playwright
2. Dessine deux iPhone blancs (Pillow) avec les captures à l'intérieur
3. Incline les deux phones à ±16°
4. Compose sur fond vignette vert forêt
5. Centre le wordmark « Vérif'All » + tagline
6. Sauvegarde dans `assets/feature-graphic-1024x500.png`

---

## PDFs — Comment régénérer

Les PDFs embarquent les 7 visuels (feature graphic + grille 3×2 des captures).
Script utilisé : `scratchpad/make_pdf_with_visuals.py` (session Claude).

```bash
# Avec pandoc + Playwright Chromium
python3 -I /path/to/make_pdf_with_visuals.py
```

Ou manuellement avec pandoc seul (sans images) :
```bash
cd docs/playstore
pandoc PUBLICATION_PLAY_STORE.md -o PUBLICATION_PLAY_STORE.pdf \
  --pdf-engine=weasyprint -V lang=fr
```

---

## Historique des versions du feature graphic

| Version | Commit | Description |
|---|---|---|
| v1 | `ef2880d` | Premier jet — screenshots `-alt` recadrés, phones inclinés |
| v2 | `81f2cb6` | Redesign expert — vignette radiale, ombre, wordmark |
| v3 | `e21df25` | Réécriture complète — Playwright + Pillow (mockups propres, sans texte natif) |
| v3.1 | `f91ea17` | Suppression icône V du centre (demande utilisateur) |

---

## Commandes pour récupérer les derniers changements

```bash
# Cloner le dépôt (première fois)
git clone https://github.com/massenon/verifall.git
cd verifall
git checkout claude/keen-wozniak-hq7tv7

# Mettre à jour une copie existante
git fetch origin
git checkout claude/keen-wozniak-hq7tv7
git pull origin claude/keen-wozniak-hq7tv7

# Voir uniquement les fichiers Play Store modifiés
git diff main..claude/keen-wozniak-hq7tv7 --name-only | grep -E "playstore|publication-play-store"
```

---

## Checklist avant soumission Play Store

- [ ] Icône 512 × 512 px à créer (voir `Fiche-publication-Play-Store-Verifall.md`)
- [ ] Téléverser `feature-graphic-1024x500.png` dans Play Console → Présentation du Store
- [ ] Téléverser les 6 captures dans Play Console (dans l'ordre 01→06)
- [ ] Renseigner `https://verifall.com/confidentialite` comme politique de confidentialité
- [ ] Remplir le formulaire « Sécurité des données » (voir `PUBLICATION_PLAY_STORE.pdf` §4)
- [ ] Générer le magasin de clés et activer la signature d'application Play
