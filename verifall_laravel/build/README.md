# build/ — outillage de transposition

## `build_layouts.py`
Régénère les 3 layouts Blade **par extraction** depuis `resources/prototype/`.

```bash
python build/build_layouts.py
```

Le HTML des shells n'est jamais écrit à la main : il est découpé dans les pages
du prototype, puis quatre passes seulement le modifient —

1. `rewrite_assets` : chemins relatifs → `asset('assets/...')` ; le CDN Tailwind
   est retiré (`SEO_PERFORMANCE` : aucun CDN tiers) ;
2. `rewrite_links` : `href="x.html"` → `route('...')`, avec une table de
   surcharge **par shell** — `dashboard.html` existe côté citoyen ET admin, et
   sans elle le « Tableau de Bord » du staff renvoyait dans l'espace citoyen ;
3. `dynamise_active_state` : `is-active` / `aria-current` figés dans le
   prototype → condition sur la route courante, à classes CSS identiques ;
4. injection du `{{ $slot }}` à la place de la zone de contenu.

À relancer après toute évolution du prototype. Les liens vers des pages non
encore portées deviennent `href="#" data-todo="<fichier>"` : le compteur affiché
en fin d'exécution mesure la dette de transposition restante.
