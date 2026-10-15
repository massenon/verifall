# RESPONSIVE_BEHAVIOR_INSTRUCTION.md

Breakpoints : <768 mobile · 768-1023 tablet · 1024-1279 laptop · ≥1280 desktop. Mobile-first.
Transformations obligatoires au passage mobile : sidebar → drawer offcanvas (burger, backdrop, Échap) · KPI → accordéon · DataTable → cards (jamais de table réduite/scroll horizontal imposé) · actions de ligne → bottom sheet · filtres → bottom sheet · topbar simplifiée · bottom-nav citoyenne visible (masquée ≥1024, blindée media query) · FAB visible · sticky action bars avec safe-area (env(safe-area-inset-bottom)).
Tactile : cibles ≥44 px, une main+un pouce+une action, pas de hover-dépendant (tout accessible au tap), swipe non obligatoire (toujours un bouton équivalent).
Typo mobile : page title 21-24, body 14, plancher 12. Images : dimensions déclarées (anti-CLS), srcset si >800 px.
Tests : chaque écran validé aux 4 largeurs (375, 820, 1100, 1440) avant DoD.
