# PAGES_KPIS_UX_MOBILE_DESKTOP_INSTRUCTIONS.md

**Règle globale (TOUTES les pages du projet)** : la grille KPI supérieure est repliée derrière un **accordéon en vue mobile** (<1024 px) — bouton `.kpi-toggle` pleine largeur (« Vue d'ensemble » + chevron animé, aria-expanded), grille masquée par défaut, état mémorisé par page (localStorage UI). Desktop : grille visible, toggle masqué.
Anatomie KPI (composant unique x-kpi) : valeur (Instrument 800) → label uppercase 11 px → tendance/contexte (↗ vert, ↘ rouge, texte neutre sinon). 4 KPI max desktop (2×2 mobile) ; chaque KPI cliquable si une page de détail existe. Count-up 400 ms coupé par prefers-reduced-motion. Valeurs = données réelles (jamais de chiffre inventé) ; états loading (skeleton), empty (« — »).
Desktop : grilles 12 col (KPI 3×4, contenus 8/4 ou 6/6). Mobile : 1 colonne, ordre = contexte → KPI (accordéon) → actions prioritaires → contenu → secondaire.
