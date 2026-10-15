# SANITY_CHECK_COMPLET.md — Grille exécutable avant chaque livraison

## Automatique (CI locale)
`pint --test` ✓ `phpstan --level=6` ✓ `php artisan test` ✓ `composer audit` + `npm audit` (0 high) ✓ crawler maison (routes GET publiques + citoyennes seedées : 0 lien 404, 0 asset manquant) ✓ validator HTML (0 déséquilibre) ✓ tests navigateur (Dusk/Pest browser) des parcours : sous-onglets, ajout, édition, **suppression avec annulation**, export PDF/CSV, import avec sample, quiz complet chronométré, enrôlement premium, OTP, RGPD export+effacement.

## Console & runtime
0 erreur JS console sur chaque layout (public, citoyen, staff) desktop ET mobile ; 0 requête N+1 (preventLazyLoading) ; 0 exception dans laravel.log après le parcours de démo.

## UX/DS
KPI accordéon mobile partout ✓ tables→cards mobile ✓ toasts sur chaque action ✓ modales suppression nominatives + Annuler ✓ empty/loading/error présents ✓ bottom-nav masquée desktop ✓ ordre CSS tailwind dernier ✓.

## Perf/SEO/A11y
Lighthouse ≥90 (public), titles/descriptions uniques, JSON-LD valide (Rich Results Test), axe-core AA sans bloquant, alt sur 100 % des images.

## Sécurité
APP_DEBUG=false préprod ✓ limiteurs testés ✓ aucun secret dans `git log -p` ✓ headers sécurité présents ✓ upload malveillant rejeté ✓.
**Livraison = ce fichier coché + rapport honnête des écarts.**
