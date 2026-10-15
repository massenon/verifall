# VÉRIF'ALL — SPÉCIFICATION DE HANDOFF DÉVELOPPEUR
> Prototype v2 (106 pages) → implémentation Laravel/Livewire. Référence : `web/` + générateurs `build_citizen/`.

## 1. Design tokens (source de vérité)

### Couleurs — définies dans `tailwind.config` (build statique `css/tailwind.css`)
| Token | Valeur | Usage |
|---|---|---|
| `primary` / `primary-dark` / `primary-soft` | #09B850 / #079a43 / #e6f8ee | CTA citoyens, liens actifs, succès doux |
| `forest` | #063B2A | Fonds éditoriaux premium (Hub, chips verdict) |
| `brandred` | #D6293E | CTA « Vérifier » et alertes fortes uniquement |
| `adminbrand` (+`.deep/.emerald/.mint`) | #065F46 / #052E16 / #059669 / #D1FAE5 | Espace staff « Trusted Learning OS » |
| `ink` / `slate2` / `line` / `canvas` / `surface` | #111827 / #64748B / #E5E7EB / #F6F8F7 / #FFF | Neutres citoyens |
| `adminink` / `adminslate` / `adminborder` / `admincanvas` | #0F172A / #475569 / #E2E8F0 / #F8FAFC | Neutres staff |
| `vtrue` / `vfalse` / `vinvest` / `vinfo` / `xp` | #16A34A / #DC2626 / #D97706 / #2563EB / #7C3AED | États fonctionnels — jamais décoratifs |

**Règle absolue** : référencer les tokens (`text-vinvest`), jamais les hex en dur dans les vues Blade.

### Typographie (auto-hébergée, `css/fonts/fonts.css`)
- `font-sans` = Inter 400/500/600/700 — corps, UI. `font-display` = Instrument Sans 500–700 — titres, KPI.
- Échelle : Display 32–40 · Page title 24–28 (mobile 21–24) · Section 18–20 · Card 15–17 · Body 14–15 · Meta 12–13. **Plancher 12 px.**
- Icônes : Material Symbols Rounded variable (`FILL 0`, `.icon-fill` pour rempli), taille par défaut 24, utilitaires `!text-[NN px]`.

### Espacement, rayons, ombres
- Grille 4 px. Cards : radius 16 (`--st-radius`), padding 20–24. Inputs/boutons : radius 12, **min-height 44 px** (48 sur CTA mobiles).
- Ombres : `shadow-card` (0 .35rem 1.2rem rgba(17,24,39,.05)) et `shadow-cardlg` — rien de plus lourd.

## 2. Ordre de cascade CSS (critique)
`fonts.css` → CSS composants (`citizen.css`, puis `staff.css`, `quill.snow.css` côté staff) → **`tailwind.css` en dernier**. Les utilitaires doivent gagner (sémantique CDN d'origine). `.bottom-nav` et `.fab` sont en plus blindés par `@media (min-width:1024px){display:none}` dans `citizen.css`.

## 3. Breakpoints & comportements
| Plage | Comportement |
|---|---|
| ≥1280 Desktop | Sidebar fixe 260 px, tables complètes, grilles dashboard 12 col (8/4, 6/6) |
| 1024–1279 Laptop | Idem, paddings réduits, filtres wrap |
| 768–1023 Tablet | Sidebar → offcanvas (burger), grilles 2 col, KPI 2×2 |
| <768 Mobile | Bottom-nav 5 entrées (citoyen), FAB, **KPI en accordéon** (`data-kpi-accordion`), tables → cards (`st-table-wrap` masqué / `st-cards-wrap` affiché), actions ligne → bottom sheet, filtres → bottom sheet |

## 4. Composants — contrats d'interface (extraits clés)

### KPI (`.vf-kpi` citoyen / `.st-kpi` staff)
Structure : valeur (`-v`, Instrument 800 1.8rem) → label uppercase 11 px letter-spacing .12em → tendance. Mobile : conteneur `data-kpi-accordion` avec `.kpi-toggle` (`aria-expanded` togglé par `citizen.js`).

### Badges staff (`.st-badge-*`)
Point ● + libellé, une info par badge. Variantes : `pub`(vert) `draft` `arch` `warn` `danger` `info` `premium`. Jamais deux sémantiques dans un badge.

### DataTable CRUD (pattern unique — factory `gen_staff_crud.py`)
Recherche `data-search-input` (debounce 350 ms) filtrant sur l'attribut `data-search` de chaque `[data-row-el]` (tr **et** card ; compteur = visibles/2) · selects `data-filter` + chips `data-chips` supprimables · bulk bar `data-bulk-bar` (apparaît si ≥1 `[data-sel]` coché) · actions ⋮ `data-row-menu` desktop / `data-sheet-for` bottom sheet mobile · export CSV réel (Blob, BOM UTF-8, `;`) · suppression : modal `#modal-del` nominative → retire les 2 éléments jumeaux du DOM · offcanvas `.st-oc` avec Quill 2 lazy-init (`ensureQuill()` au 1er open).

### Offcanvas / Bottom sheet / Modales
Offcanvas : translateX 220 ms, backdrop cliquable, `role="dialog" aria-modal`, focus au 1er champ, Échap ferme tout (`oc`, `sheet`, `mfilters`). Bottom sheet : radius 20 haut, grab-bar, items 48 px. Modales destructives : `role="alertdialog"`, nom de l'élément injecté, action rouge à droite.

### Quill (staff)
Toolbar : header 2/3, B/I/U/S, listes, quote, code, link, image, clean. Mobile : toolbar scrollable (`white-space:nowrap`). **Sanitizer HTML côté serveur obligatoire avant persistance.**

### Course Studio (`builder.html`)
3 panneaux (Navigator sticky / Workspace Quill / Properties+Health). Drag & drop : `dragstart` marque `.is-dragging`, `dragover` insère `.st-drop-line` (3 px émeraude) avant l'élément médian, `dragend` déplace. Mobile : tabs Structure/Contenu/Options + sticky action bar (safe-area). Autosave : états ✓/↻/⚠ (`data-autosave`), Ctrl/Cmd+S intercepté. Publish : modal checklist avec avertissements non bloquants.

## 5. Motion
120–220 ms max interactions (translate hover 1 px, fades, slides) ; 400 ms plafond (count-up KPI). Skeleton shimmer 1.2 s. **Tout est coupé par `prefers-reduced-motion`** (déjà câblé : shimmer, count-up, offcanvas, carets).

## 6. États obligatoires par écran
Loading (skeleton du composant, jamais de spinner plein écran) · Empty (illustration + phrase + CTA de sortie : « Réinitialiser les filtres », « Découvrir l'Académie ») · Error (message + Réessayer) · Success (toast non bloquant `.st-toast`, 3 s) · Destructive (modal de confirmation nominative).

## 7. Accessibilité (WCAG 2.2 AA — implémenté, à préserver)
Focus visible partout · navigation clavier (Échap, tabs `role=tablist/tab/aria-selected`, radiogroup étoiles) · `aria-expanded` (accordéons, dropdowns, burger) · `aria-current="page"` (sidebar, pagination) · cibles ≥44 px · contrastes AA (slate2 sur blanc = 4.6:1) · `aria-live` (tooltips de graphes, compteurs) · labels de formulaires toujours visibles, erreurs liées au champ (`aria-invalid` + message adjacent).

## 8. Edge cases spécifiés
Textes longs : `truncate` sur titres de cards/notifications, wrap sur tables · données vides : chaque liste a son empty state · store absent/corrompu : `vf-store.js` régénère l'état par défaut (clé `verifall:citizen:v1`) · offline : aucune dépendance CDN · i18n : prévoir +30 % de longueur (boutons en `inline-flex` extensibles).

## 9. Mapping prototype → Laravel
| Prototype | Implémentation |
|---|---|
| Seeders JS des pages CRUD | Contrôleurs + pagination serveur (les colonnes/filtres du prototype = contrat d'API) |
| `vf-store.js` (localStorage) | État serveur Livewire + événements |
| Export CSV client | Export serveur (mêmes en-têtes `EXPORT_HEADERS`) |
| Visibilité RBAC (menus par rôle) | **Policies Spatie — le contrôle définitif reste serveur** |
| Toasts simulés | Notifications Laravel (+ Reverb/Echo pour le temps réel) |
