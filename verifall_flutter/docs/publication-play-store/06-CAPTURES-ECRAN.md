# Captures d'écran et visuels de la fiche Play Store

## Livré — 6 fichiers dans `visuels/`

| Fichier | Dimensions | Format | Poids |
|---|---|---|---|
| `screenshot-1-accueil.png` | 1080 × 1920 | PNG 24 bits, sans alpha | ~0,9 Mo — **montage** 3×2 (Vérification, Académie, Quiz, Certificat, Forum, Opportunités), pas le carrousel de connexion |
| `screenshot-2-tableau-de-bord.png` | 1080 × 1920 | idem | ~0,9 Mo |
| `screenshot-3-verifier.png` | 1080 × 1920 | idem | ~0,8 Mo |
| `screenshot-4-rapport.png` | 1080 × 1920 | idem | ~0,9 Mo |
| `screenshot-5-academie.png` | 1080 × 1920 | idem | ~0,9 Mo |
| `feature-graphic.png` | 1024 × 500 | idem | ~0,3 Mo |

Vérifié : chaque fichier est un vrai PNG 24 bits **sans** canal alpha (`System.Drawing.Image.PixelFormat` = `Format24bppRgb`), sous la limite de 8 Mo par image exigée par le magasin.

## D'où vient le contenu des téléphones

**Pas du binaire Flutter.** Deux voies ont été tentées pour capturer l'app réellement construite, et les deux étaient bloquées dans cet environnement :

- **Émulateur Android** : le téléchargement de l'image système échouait systématiquement (connexion coupée par le logiciel hôte, probablement un antivirus/pare-feu local) ;
- **Flutter Web** : `flutter_html 3.0.0` (utilisé par `VfProseBody` pour le rendu des leçons et du rapport) ne compile pas pour le web — `Method not found: 'matches'` dans son propre code, un bug amont indépendant de ce projet.

**La source réelle est `prototype_html/mobile/index.html`**, le prototype interactif à 30 écrans qui fait déjà foi pour la transposition Flutter (« on transpose, on n'invente pas », README du dépôt Flutter). Chaque écran a été ouvert via son lien profond (`#/dashboard`, `#/verify`, `#/report`, `#/academy`, `#/quiz`, `#/certificate`, `#/forum`, `#/hub`, `#/` pour le splash), capturé avec le binaire `chrome-headless-shell` (installé via le skill `hyperframes`, indépendant de l'extension Chrome qui s'est montrée instable dans cette session), puis détouré à la silhouette exacte du châssis de téléphone déjà dessiné par le prototype (coins arrondis, encoche, boutons).

**`screenshot-1-accueil.png` capture le carrousel d'accueil de façon déterministe** via `chrome-headless-shell --virtual-time-budget=N` : le carrousel défile tout seul (4,2 s/diapositive), donc une capture sans ce réglage tombe sur une diapositive aléatoire selon la vitesse de chargement — `budget=0` pour la diapositive 1, `12600`+ pour la 4ᵉ. C'est ce qui a révélé, lors d'une capture précédente tombée par hasard sur la diapositive 4, que son texte avait besoin d'être retravaillé (voir plus bas).

**Ce qui a été corrigé dans le prototype au passage**, à la demande explicite de l'utilisateur :
- le vrai logo et le vrai favicon de Vérif'All ont remplacé le glyphe texte « V » et l'absence de favicon (`<link rel="icon">` ajouté, image embarquée en base64 depuis `verifall_laravel/public/assets/images/logo-icon.png` et `favicon.png`) ;
- la diapositive 04 du carrousel d'accueil a été réécrite le 9 octobre 2026 : le texte d'origine (« Un communiqué officiel truffé de fautes… ») répétait son propre titre (« La forme trahit souvent le fond » en double) et tombait, selon le moment de capture, sur un bloc trop dense. Nouveau texte, même thème (la forme comme indice), même gabarit que les diapositives 1 à 3 — vérifié sans débordement via une capture déterministe (`--virtual-time-budget=13000`). La fonction de rendu a aussi été corrigée pour ne plus afficher de guillemets vides quand les champs sont absents (robustesse, indépendante du contenu).

## Ce qui habille chaque capture

Gabarit HTML/CSS propre (pas les skills « hyperframes »/« ui-ux-pro-max-skill-main » de la demande d'origine — ni l'une ni l'autre ne produit directement un visuel de fiche Play Store ; la composition a été faite à la main avec les jetons de couleur réels du design system Flutter, `VfColors.forest`/`primary`) : fond dégradé vert forêt de marque, pastille « VÉRIF'ALL », titre et sous-titre sans tiret ni tiret cadratin/demi-cadratin (contrainte explicite), logo réel en haut à gauche, téléphone capturé au centre, signature « La boussole contre la désinformation numérique » en pied de page.

## Reproduire ou régénérer

Les fichiers de travail (gabarits HTML, captures brutes, scripts de recadrage PowerShell) ne sont pas versionnés dans ce dépôt — ils ont vécu dans un répertoire de travail temporaire le temps de la session. Pour regénérer :

1. Servir `prototype_html/mobile/index.html` en local (`python -m http.server`).
2. Capturer chaque écran avec `chrome-headless-shell --headless --screenshot=out.png --window-size=1400,1150 "http://localhost:PORT/index.html#/ROUTE"` pour `ROUTE` ∈ `{(vide)=splash, dashboard, verify, report, academy}`.
3. Recadrer la zone du châssis (`392,235` à `+438,+880` dans cette mise en page) avec un masque de coins arrondis (rayon 58 px) pour obtenir un détourage propre.
4. Composer dans un gabarit 1080 × 1920 (téléphone) ou 1024 × 500 (feature graphic) avec le fond de marque et le texte.

## Captures tablette / Chromebook

**Non produites.** L'application ne déclare aucun support tablette ou Chromebook explicite à ce jour (`03-PERMISSIONS.md`, aucune configuration de form factor trouvée) — à constituer séparément le jour où ce périmètre est confirmé.

## Ce qui reste vrai de l'avertissement d'origine

Le contenu affiché (verdicts, cours, KPI) vient du prototype de référence, pas d'un compte réel ni de données de production en direct — c'est la même donnée de démonstration que celle que l'équipe Flutter utilise déjà pour valider chaque écran contre ce prototype. Avant la soumission définitive, vérifier que rien ne ressemble à un engagement produit non tenu (ex. chiffres de démonstration trop spécifiques) plutôt qu'à une illustration.
