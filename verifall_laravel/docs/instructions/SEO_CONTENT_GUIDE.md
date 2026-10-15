# SEO_CONTENT_GUIDE.md — Titres, métadonnées, mots-clés, maillage interne

## Titres (H1 unique par page, hiérarchie stricte H1→H4)
- Formule H1 public : [Bénéfice] + [mot-clé primaire] + [Bénin si local]. Ex. « Vérifiez une information en 3 minutes — fact-checking Bénin ».
- Title ≤60 car. : `{H1 court} | Vérif'All` ; page d'accueil : `Vérif'All — La boussole contre la désinformation numérique au Bénin`.
- Ne JAMAIS dupliquer un title ; test automatisé d'unicité sur le sitemap.

## Métadonnées
- Description 140-160 car., verbe d'action + bénéfice + CTA implicite. Fallback auto : 1ʳᵉ phrase du contenu nettoyée.
- OG/Twitter dynamiques : og:image 1200×630 générée par gabarit (verdict coloré pour les fact-checks — reprend le partage 9:16).
- Canonical absolu ; `noindex` sur : espaces connectés, pages de filtres, pagination >1 (rel prev/next conservés).

## Mots-clés (priorité Bénin/Afrique francophone)
Primaires : fact-checking Bénin, vérifier une information, fausse nouvelle Bénin, désinformation Afrique.
Secondaires par module — LMS : formation fact-checking, cours OSINT gratuit, certificat vérification d'information ; Hub : actualités vérifiées Bénin, offres d'emploi vérifiées, portails officiels Bénin ; Fact-checks : « [rumeur] vrai ou faux », démenti officiel.
Règle : 1 mot-clé primaire / page, densité naturelle (jamais de bourrage), présent dans H1 + title + 1ᵉʳ paragraphe + slug.

## Maillage interne (obligatoire, vérifié en revue)
- Chaque fact-check → 2-3 fact-checks liés (même catégorie) + 1 cours de l'Académie pertinent (« Apprenez à vérifier vous-même ») + outil du répertoire.
- Chaque article de blog → 1 fact-check + 1 page pilier ; chaque cours → catalogue + certificat public.
- Pages piliers (comment-ca-marche, pourquoi-verifall) reçoivent des liens de toutes les pages filles ; ancres descriptives (jamais « cliquez ici »).
- Fil d'Ariane BreadcrumbList partout ; footer = liens utilitaires seulement (pas de sur-maillage).
