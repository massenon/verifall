# Spécifications visuels — Google Play Store

> Charte graphique Vérifall : Forest `#063B2A` · Vert `#09B850` · Rouge `#D6293E`
> Polices : **Instrument Sans** (titres) · **Inter** (corps)

---

## Structure du dossier

```
docs/visuals/
├── specs-visuels-playstore.md    ← ce fichier
├── banner-playstore.html         ← Feature Graphic (1 024 × 500) — à exporter
├── icon/
│   └── icon-512.png              ← à créer (512 × 512 px)
└── screenshots/
    ├── 01-accueil.png
    ├── 02-resultat-vrai.png
    ├── 03-resultat-faux.png
    └── 04-reglages.png
```

---

## 1. Feature Graphic (Bannière) — OBLIGATOIRE

| Spécification | Valeur |
|---|---|
| Taille | **1 024 × 500 px** |
| Format | JPEG ou PNG 24-bit (sans transparence) |
| Taille max. | 1 Mo |
| Fichier source | `docs/visuals/banner-playstore.html` |

### Export

1. Ouvrir `banner-playstore.html` dans Chrome
2. Zoom → **100 %** (Ctrl+0)
3. Capture d'écran de la zone bannière exacte (1 024 × 500 px)
4. Ou : Imprimer → Enregistrer en PDF → extraire l'image

### Contenu de la bannière

- **Fond** : Forest `#063B2A` avec halos verts
- **Gauche** : logo Vérif All + headline « La boussole citoyenne de l'information » + badges (Gratuit · Sans pub · RGPD)
- **Droite** : 3 cartes de résultat (VRAI / FAUX / TROMPEUR) avec exemples réalistes

---

## 2. Icône haute résolution — OBLIGATOIRE

| Spécification | Valeur |
|---|---|
| Taille | **512 × 512 px** |
| Format | PNG 32-bit |
| Taille max. | 1 Mo |
| Fond | Plein (pas de transparence) |

### Brief iconographie

- Fond : Forest `#063B2A` ou vert `#09B850`
- Motif : bouclier avec coche (déjà utilisé dans la bannière)
- Style : flat, sans texte (illisible en petite taille), coins arrondis
- Outil suggéré : Figma, Adobe Illustrator, ou Inkscape

---

## 3. Captures d'écran — 2 minimum, 4–6 recommandées

| Spécification | Valeur |
|---|---|
| Nombre | 2 min · 8 max · **4 recommandées** |
| Format | JPEG ou PNG 24-bit |
| Résolution recommandée | **1 080 × 1 920 px** (portrait 9:16) |
| Côté court min. | 320 px |
| Côté long max. | 3 840 px |

### 4 captures recommandées

| # | Écran | Ce qui doit apparaître |
|---|---|---|
| 01 | **Accueil / Dashboard** | Interface principale, barre de recherche, dernières vérifications |
| 02 | **Résultat VRAI** | Carte verdict verte, source citée, explication courte |
| 03 | **Résultat FAUX** | Carte verdict rouge, démontage de la rumeur |
| 04 | **Réglages / RGPD** | Consentements granulaires, interrupteur Rapports de diagnostic |

### Procédure de capture

1. Lancer l'app sur un émulateur Pixel 8 (412 × 915 dp) ou appareil physique
2. Résolution d'export : 1 080 × 1 920 px (facteur d'échelle ×2.625)
3. Ajouter un cadre de téléphone (optionnel) avec [screenhance.com](https://screenhance.com/play-store-templates) ou Figma
4. Ajouter du texte explicatif sur chaque capture (ex. « Verdict en 30 secondes »)

---

## 4. Checklist visuels avant soumission

- [ ] Bannière 1 024 × 500 exportée en PNG/JPEG < 1 Mo
- [ ] Icône 512 × 512 px PNG sans transparence < 1 Mo
- [ ] Capture 01 — Accueil (1 080 × 1 920 px)
- [ ] Capture 02 — Résultat VRAI
- [ ] Capture 03 — Résultat FAUX
- [ ] Capture 04 — Réglages
- [ ] Tous les textes sur les captures sont en français
- [ ] Aucun contenu fictif ou inventé visible (données d'exemple clairement marquées)
- [ ] Aucune marque tierce visible sans accord

---

## Palette de référence

| Rôle | Hex | Usage |
|---|---|---|
| Forest | `#063B2A` | Fond principal, cartes sombres |
| Primary | `#09B850` | Actions, accents, logo |
| Primary Dark | `#079A43` | Texte sur fond clair |
| Brand Red | `#D6293E` | Verdict FAUX, zone de danger |
| Warn | `#D97706` | Verdict TROMPEUR |
| Ink | `#111827` | Texte fort |
| Canvas | `#F6F8F7` | Fond d'écran général |
