# DataTables — copie locale (pilote du 14 septembre 2026)

Déploiement cible : cPanel mutualisé, **aucun build**. Ces fichiers sont servis
tels quels. Rien n'est chargé depuis un CDN à l'exécution.

Chargés **uniquement** par les écrans staff qui les empilent (`@push('styles')`
/ `@push('scripts')`). Aujourd'hui : `resources/views/livewire/staff/utilisateurs.blade.php`.
Contrat d'intégration : `docs/DATATABLES_CONTRAT.md`.

## Versions épinglées

| Composant | Version | Source | Fichiers |
|---|---|---|---|
| jQuery | 3.7.1 | https://code.jquery.com/jquery-3.7.1.min.js | `js/jquery-3.7.1.min.js` |
| DataTables (cœur, style « DataTables default ») | 2.3.8 | https://cdn.datatables.net/2.3.8/ | `js/dataTables-2.3.8.min.js`, `css/dataTables.dataTables-2.3.8.min.css` |
| Buttons (+ html5, print, colVis) | 3.2.6 | https://cdn.datatables.net/buttons/3.2.6/ | `js/dataTables.buttons-3.2.6.min.js`, `js/buttons.html5-3.2.6.min.js`, `js/buttons.print-3.2.6.min.js`, `js/buttons.colVis-3.2.6.min.js`, `css/buttons.dataTables-3.2.6.min.css` |
| Select | 3.1.3 | https://cdn.datatables.net/select/3.1.3/ | `js/dataTables.select-3.1.3.min.js`, `css/select.dataTables-3.1.3.min.css` |
| DateTime | 1.6.3 | https://cdn.datatables.net/datetime/1.6.3/ | `js/dataTables.dateTime-1.6.3.min.js`, `css/dataTables.dateTime-1.6.3.min.css` |
| SearchBuilder | 1.8.4 | https://cdn.datatables.net/searchbuilder/1.8.4/ | `js/dataTables.searchBuilder-1.8.4.min.js`, `css/searchBuilder.dataTables-1.8.4.min.css` |
| Responsive | 3.0.8 | https://cdn.datatables.net/responsive/3.0.8/ | `js/dataTables.responsive-3.0.8.min.js`, `css/responsive.dataTables-3.0.8.min.css` |
| Scroller | 2.4.3 | https://cdn.datatables.net/scroller/2.4.3/ | `js/dataTables.scroller-2.4.3.min.js`, `css/scroller.dataTables-2.4.3.min.css` |
| JSZip (export Excel) | 3.10.1 | https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js | `js/jszip-3.10.1.min.js` |
| pdfmake (export PDF) | 0.2.12 | https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.12/ | `js/pdfmake-0.2.12.min.js`, `js/vfs_fonts-0.2.12.min.js` |
| Traduction française | 2.3.8 | https://cdn.datatables.net/plug-ins/2.3.8/i18n/fr-FR.json | `i18n/fr-FR-2.3.8.json` |

**Absents volontairement** : AutoFill et RowReorder (interdits sur les
utilisateurs par la directive ; à ajouter seulement pour un écran dont l'ordre
est une donnée métier), Bootstrap (interdit).

## Pourquoi la branche 2.x et non 3.0

DataTables 3.0.0 est sorti le 24 juillet 2026 (3.0.4 le 11 septembre) et retire
la dépendance à jQuery ; ses extensions ont toutes changé de version majeure
(Buttons 4, Select 4, SearchBuilder 2…), la dernière publiée trois jours avant
ce pilote. Le pilote fixe le contrat de ~58 écrans : il est posé sur la dernière
version de la branche 2 (2.3.8, avril 2026), éprouvée, et dont chaque extension
déclare `datatables.net: ^2`. **La migration vers 3.x est un chantier à part**,
à mener quand la branche se sera stabilisée — la couche
`public/assets/js/verifall-datatables.js` est le seul endroit à adapter.

## Poids, et ce qui est chargé quand

- Chargé avec la page : jQuery, cœur, Buttons (+html5/print/colVis), DateTime,
  SearchBuilder, Responsive ≈ 370 Ko non compressés (JS) + ≈ 70 Ko (CSS).
- **Chargé au clic seulement** : JSZip (98 Ko, bouton « Excel ») et pdfmake +
  polices (2,2 Mo, bouton « PDF »). `verifall-datatables.js` les injecte à la
  demande ; un administrateur qui n'exporte pas ne les télécharge jamais.
- Vendorisés mais **non chargés** sur Utilisateurs : Select (aucune action
  groupée n'existe — critère AC07 de la directive) et Scroller (la pagination
  serveur suffit — « ne pas l'activer simplement parce qu'il est disponible »).

## Empreintes SHA-384 (contrôle d'intégrité)

Recalcul : `openssl dgst -sha384 -binary <fichier> | openssl base64 -A`

```
js/jquery-3.7.1.min.js                     1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs
js/dataTables-2.3.8.min.js                 vKSHfmUIK/B5LfOmzrP+424efQhNcgZm+sqxgq/DNCicqQRp/V6pVc4HCaRzeQZw
js/dataTables.buttons-3.2.6.min.js         ygalescEL33A7N+7n2CN65lzwzfLzQNVWNhHDnAUGLiE1RyuaOQ+kI2PhRsTzEGV
js/buttons.html5-3.2.6.min.js              OFL+acRxgxRUKgJTHEQrtuwzydyoSE/AN10JClRMQjjfnXKoF89juO7p8HyCYxxO
js/buttons.print-3.2.6.min.js              xz689SnmbcYldwmnoN4kscmH+O4a+pWUfd1SAWx53AO+Q3dkr6em2TRj0wdrFY52
js/buttons.colVis-3.2.6.min.js             6PvleYLDrhaHXPEEDa5bIibZ1NDrdrUVDjfXgYJ25vmQFDwJ/CebbAIuZnebzXRO
js/dataTables.select-3.1.3.min.js          9ceogJPaiFMiA4W7aSziydS2KDSWP3MJv9CwTuE3kBcD2CKxPZ3+4+VmacptsMDH
js/dataTables.dateTime-1.6.3.min.js        wD+ZUfD5LoqSlD8enZt0moeygvLBcUn5KWzTP2m7ugl3110q96pR/aNZ0KTVLBl8
js/dataTables.searchBuilder-1.8.4.min.js   5huBQ/mfeKyn+hIST/C6o5x4W9VntlvCNZY4usMuWOFB0s8ABLE65hOtXqhB+AKJ
js/dataTables.responsive-3.0.8.min.js      jp1JS4vMvRmld4ZK9Co4AZftrm1tt3FbEZrCdFZIcoRiazQGkTqOS1QqI060oG2F
js/dataTables.scroller-2.4.3.min.js        nakznh5a15VRD4Q2jpl+vqMjMCL666OX944SaU8OzAaROCqoFwpPRuVzjDt3HR6d
js/jszip-3.10.1.min.js                     +mbV2IY1Zk/X1p/nWllGySJSUN8uMs+gUAN10Or95UBH0fpj6GfKgPmgC5EXieXG
js/pdfmake-0.2.12.min.js                   UbICcZf4B6+FB/cmTNLOqZzUc40rHayX14EhEDfAnFhmte7yAAA7s081OeuC8VVT
js/vfs_fonts-0.2.12.min.js                 nXUiXap6sIVQQCtbYdJPsTOTJwv3rZjOdv8Ckh+3udRCVp3YLpdgHnjpPHD/LaC0
css/dataTables.dataTables-2.3.8.min.css    iRTWPXZquQySBE9K96fgJXUIrnm8TpdtPAbiCNR+qjpP24qFZw9PHFA91JtWy7Ul
css/buttons.dataTables-3.2.6.min.css       FVy8GBV36NhoIGEFkjxjod8jwtNK6kvWYlVLrG1cPJq+qCgg0GhCx1ejKH2FOY16
css/select.dataTables-3.1.3.min.css        CO0KeXuvELGnIy2sPnwyDxPFB1kIKP9jAQrs+IZOBvzUhbLXXUXXfNPAYYj5gNoH
css/dataTables.dateTime-1.6.3.min.css      D7NxnGDCenlF3DKZjQZVOCNgP2q5MeXyf/ykoMWee4fZGUqmh4GoTN3S7xiNcNYI
css/searchBuilder.dataTables-1.8.4.min.css S0010AceXCDUzltQVGLJUTIOlxjbyW28LfFyr+a4kjNZIQwR7nHiFkt+rqM8O1ag
css/responsive.dataTables-3.0.8.min.css    kz9bozrCHP/y+wTJV8P+n/dMBOh00rqNmmIAgHckzFWpoSB49V5ornW1aY+uYTyA
css/scroller.dataTables-2.4.3.min.css      iszZFel2EIfnGc1OBSiwLR3BB5TZ27aHIJ1DQx1wqzvo1xzUFFJVsNqNpfobe1GL
i18n/fr-FR-2.3.8.json                      y6YW2mYAyqPCG4d/mdN0z5Gwv83yvg/VcrQ87Rq7e2P1JCLjJuxI5xu+fjS2rOaB
```

## Mettre à jour

1. Télécharger les nouvelles versions depuis les mêmes URL, **renommer avec le
   numéro de version** (un nom versionné casse le cache navigateur sans
   manipulation serveur).
2. Mettre à jour les `@push` des écrans et ce tableau, recalculer les empreintes.
3. Relancer `php artisan test --filter=TableDesUtilisateurs` et vérifier l'écran
   au navigateur (recherche, tri, export, action de ligne).
