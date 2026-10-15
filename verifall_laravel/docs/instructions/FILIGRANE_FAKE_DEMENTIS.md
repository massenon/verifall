# FILIGRANE_FAKE_DEMENTIS.md — Tampon « FAKE » sur les démentis

## Principe (front public facts.html / facts-details.html + partages)
Tout fact-check publié avec verdict **FAUX** affiche son visuel principal avec un **filigrane tampon « FAKE »** — signature visuelle de Vérif'All : l'image de la rumeur ne peut plus circuler sans son démenti incrusté.

## Spécification visuelle (identique au prototype)
Tampon incliné **-12°**, texte « FAKE » (Instrument Sans 800, tracking large), rouge brandred #D6293E à **82 % d'opacité**, double bordure 6 px + 2 px arrondie 8 px, léger grain, centré sur l'image, largeur ≈ 55 % de l'image (min 180 px), ombre portée douce. Variante TROMPEUR : tampon ambre « TROMPEUR » même construction. VRAI/NON VÉRIFIABLE : **aucun filigrane**.

## Implémentation double couche
1. **CSS (affichage site)** : composant `x-fact-cover :verdict` — l'image originale reste intacte, le tampon est un overlay (::after) → léger, retina, traduit, accessible (`aria-hidden`, le verdict est déjà annoncé en texte).
2. **Serveur (fichiers partagés — la vraie protection)** : à la publication d'un verdict FAUX/TROMPEUR, Job `StampFactCheckImage` (Intervention Image) génère `cover_stamped.webp` : tampon incrusté pixel + bandeau bas « Démenti par Vérif'All — verifall.com/facts/{slug} ». **Toutes les sorties partageables utilisent la version tamponnée** : og:image, image 9:16 WhatsApp, téléchargement. L'original n'est jamais exposé publiquement pour un démenti.

## Contrôle admin
Dans l'offcanvas fact-check admin : toggle « Filigrane FAKE » (auto-coché si verdict FAUX, décochable avec motif — audit loggé), aperçu live du tampon, bouton « Régénérer l'image tamponnée ». Changement de verdict → régénération automatique + purge cache/CDN. Seeder : les démentis seedés (arnaque avalonbuyers, faux décret) livrés avec leur version tamponnée générée.
Tests : présence du filigrane sur FAUX (assertion pixel/hash), absence sur VRAI, og:image = version tamponnée, toggle admin audité.
