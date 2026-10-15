# Vérif'All — Front-end public (refonte Tailwind fidèle Mizzle)

Reconstruction 1:1 du front public depuis les fichiers source `frontend_*.html`
(design Mizzle validé par les captures). **Zéro Bootstrap, zéro jQuery,
zéro fragment Livewire** : Tailwind (config CDN inline) + `css/verifall.css`
(composants fins) + `js/verifall.js` (vanilla, modulaire).

## Contenu (32 pages)
- Accueil, Pourquoi Vérif'All ?, Fonctionnalités, Comment ça marche ?
- Actualités (facts) + détail d'analyse, Vérifier une information (guest checker)
- Tutoriels + détail de cours, Blog + article WSIS 2026, FAQ (17 Q/R), Médias
- Notre Équipe + 11 fiches membres, Contact
- Connexion, Inscription (stepper 4 étapes + OTP + jauge), Mot de passe oublié
- Mentions légales, Confidentialité, Cookies

## Interactif (JS vanilla partagé)
Burger + sticky navbar · carousel auth (6 slides) · accordéons · tabs ·
dropdowns · compteurs animés · stepper + OTP auto-tab + jauge de mot de passe ·
filtres/recherche (facts, tutoriels, FAQ) · barre de lecture (détails) ·
popup PWA (mémorisée 7 j) · formulaires simulés avec toasts.

## Images
Les chemins d'origine sont conservés (`images/…`). Collez vos fichiers
en suivant `docs/IMAGES_A_COLLER.md` (37 entrées, arborescence déjà créée).

## Prérequis
Tailwind est chargé via CDN + Google Fonts : une connexion internet est
nécessaire pour l'aperçu. Ouvrez simplement `index.html`.


## Espaces staff (vague 10)
- `compte-admin/` — back-office « Trusted Learning OS » (login : n'importe quel e-mail valide + mot de passe ≥ 6 caractères)
- `compte-mentor/` — workspace pédagogique (login.html idem)
- Générateurs : `build_citizen/gen_staff*.py` · design system : `css/staff.css` · éditeur riche : Quill 2 local (`js/vendor/quill.js`)
