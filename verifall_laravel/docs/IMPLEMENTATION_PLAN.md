# Plan d'implémentation — console admin et mentor premium

> Rédigé le 18 octobre 2026, au passage du prototype à l'implémentation.
> Source visuelle et fonctionnelle : `resources/prototype-premium/` (85 écrans,
> page de connexion `connexion.html`, verrouillage de session).
> Ce document se tient à jour : chaque tâche terminée est cochée et datée.

## 0. Point de départ (cartographie du 18 octobre)

| Sujet | État réel |
|---|---|
| Back-office | 59 composants `app/Livewire/Staff`, environ 69 routes `staff.*` sous `/admin`, un seul layout `components/layouts/staff.blade.php` pour l'admin et le mentor |
| Design system staff | Palette `adminbrand` (`#065F46`), Inter, Material Symbols, `staff.css` et `staff-shell.css`. Aucun mode sombre. Pas d'ApexCharts : le tableau de bord trace en SVG |
| CSS | Build Tailwind **figé** `public/assets/css/tailwind.css` (empreinte MD5 contrôlée par `BuildTailwindFigeTest`), commun au citoyen et au staff. Pas de Vite, déploiement cPanel |
| Listes | DataTables côté serveur (`docs/DATATABLES_CONTRAT.md`), pilote Utilisateurs, briques `app/Support/DataTables/*` |
| Menu | `app/Support/Rbac/MenuDuBackOffice.php` : la visibilité est **lue sur le `can:` de la route**, jamais déclarée à part |
| Authentification staff | `Auth\StaffLogin` (2FA intégrée, garde `staff`), `StaffIdleTimeout` (30 min). **Absents** : mot de passe oublié staff, `password.confirm`, écran de verrouillage, « Mon compte » |
| Tableaux de bord | Un seul `Staff\TableauDeBord` pour tous (services `TableauDeBordPersonnel`, `PanneauxDuTableauDeBord`, `CentreDePilotage`). **Aucun tableau de bord mentor dédié** ; les données mentor existent dans `Core/Lms/Services/EspaceDuMentor` |
| Fiche d'un compte | `Staff\FicheUtilisateur` et `FicheDuCompte` (16 onglets) existent et sont testés, mais **aucune route ne les sert** |
| Permissions mentor | `MatricePermissions::pourRole(Mentor)` : cours, leçons, quiz, lives, examens, banque, certificats, inscriptions, apprenants. **Rien** sur le Hub, le forum, le blog, la FAQ, les partenaires, les témoignages ni les infolettres |
| Qualité | 173 fichiers de tests, pint, phpstan niveau 6 avec baseline à cliquet, `php artisan test --parallel` |

Écrans du prototype **sans équivalent** Laravel : calendrier, BI, analytique IA,
Académie et SEO, économie, médiathèque, éditeur pleine page, fiche mentor,
Mon compte, Mon profil mentor, Hub et Communauté côté mentor, palette Ctrl K,
Actions rapides, thème sombre, verrouillage.

Écrans Laravel **absents** du prototype, à garder et à habiller : Maintenance,
Tipline, Packs d'unités, Parrainages, Signalements de contenu, Mots surveillés,
Messages de contact, Statistiques du cours.

## 1. Décisions à prendre avant de coder

| # | Décision | Proposition | Bloque |
|---|---|---|---|
| D1 | La « référence visuelle absolue » de `CLAUDE.md` passe à `resources/prototype-premium/` pour l'admin et le mentor ; le citoyen garde `resources/prototype/` | **Adoptée le 18 octobre** (consigne « on passe du prototype à l'implémentation ») : `CLAUDE.md` et `ARBITRAGES.md` n° 176 | Tout |
| D2 | Une feuille CSS **staff** séparée, compilée depuis la config du prototype premium ; `tailwind.css` (citoyen) reste figé | Feuille `public/assets/css/staff-premium.css`, construite par `tools/staff-premium/build.sh`, empreinte contrôlée comme la citoyenne | Phase 1 |
| D3 | Icônes : Lucide (vendorisé en SVG via un composant `<x-lucide>`) à la place de Material Symbols, côté staff seulement | Oui ; Material Symbols reste chargé pour le citoyen | Phase 1 |
| D4 | Graphiques : ApexCharts 3.54.1 vendorisé, avec la vue « Tableau » obligatoire | Oui, pour les nouveaux écrans d'analyse ; les SVG existants restent tant qu'ils ne sont pas refaits | Phases 4 et 7 |
| D5 | Hub et Communauté dans l'espace mentor | **Tranché le 18 oct.** : CRUD sur les permissions existantes, sans publier, sans les abonnés (arbitrage n° 177) | — |
| D6 | Copywriting des courriels système | **Tranché le 18 oct.** : objet, titre, corps, bouton modifiables ; la logique reste au code. Déjà en place (arbitrage n° 179) | — |
| D7 | RGPD : connexions et Hub consulté par compte | **Tranché et implémenté le 18 oct.** : connexions en intérêt légitime (12 mois, IP tronquée), Hub sur consentement (90 jours). `docs/RGPD_HISTORIQUES.md`, arbitrage n° 178 | — |
| D8 | Délai du verrouillage | Verrou côté client à 15 min, `StaffIdleTimeout` inchangé à 30 min | Phase 3 |
| D9 | Thème | Clair par défaut. Préférence par personne (`users.preferences->theme` : `light`, `dark` ou `system`) | Phase 2 |

## 2. Principes de transposition

1. **On réutilise la logique, on change l'habit.** Aucun service, dépôt, Policy
   ni test métier n'est réécrit pour un changement visuel. Les composants Livewire
   gardent leurs propriétés publiques et leurs actions.
2. **Les briques d'abord.** `ui.js` et `input.css` du prototype deviennent des
   composants Blade (`<x-premium.*>`) avant tout écran. Un écran assemble des
   briques ; il n'écrit pas de CSS.
3. **Le menu reste lu sur les routes** (`MenuDuBackOffice`). Le prototype
   fournit l'ordre, les groupes et les icônes, pas les droits.
4. **DataTables reste le moteur des listes.** Il est habillé aux jetons premium
   (`verifall-datatables.css`). Le contrat de `DATATABLES_CONTRAT.md` ne change pas.
5. **Une phase se livre seule**, derrière un drapeau `config('verifall.console_premium')`
   tant que la coquille n'est pas complète, pour pouvoir revenir en arrière par
   `.env` sur cPanel.
6. Barre de qualité à chaque tâche : `pint`, `phpstan`, `php artisan test --parallel`,
   `--filter=CouvertureTraduction` dès qu'un texte est ajouté, et la mesure de
   fidélité de l'écran touché.

## 3. Phases et tâches

Légende : **E** = estimation en jours de travail. Chaque tâche indique ce qui
prouve qu'elle est finie.

### Phase 0 — Cadrage (E 0,5)

- [x] **P0-1** Cartographie de l'existant (section 0). *18 oct.*
- [x] **P0-2** Ce plan. *18 oct.*
- [x] **P0-3** D1 consignée : `CLAUDE.md` (référence visuelle) et `ARBITRAGES.md` n° 176.
- [ ] **P0-4** Décisions D5, D6 et D7 tranchées par le porteur du projet, puis consignées dans `ARBITRAGES.md`.

### Phase 1 — Fondations du design system staff (E 3)

- [x] **P1-1 Chaîne de construction CSS.** *18 oct.* (`build.sh --check` pour vérifier la feuille commitée) `tools/staff-premium/` : `tailwind.config.js`
  (jetons du prototype, `content` = vues staff et composants premium),
  `input.css` (composants du prototype), `build.sh` (Tailwind CLI 3.4.17 autonome,
  sans `node_modules` commité). Sortie : `public/assets/css/staff-premium.css`.
  *Fini quand* : le build est reproductible (même empreinte deux fois de suite)
  et un test `BuildStaffPremiumFigeTest` contrôle l'empreinte.
- [~] **P1-2 Polices et bibliothèques.** *18 oct. : polices locales (`css/fonts/premium.css`), ApexCharts, Flatpickr et GLightbox vendorisés ; Lucide rendu en SVG serveur (`<x-lucide>`, `App\Support\Icones\Lucide`, catalogue `resources/icones/lucide.php` régénéré par `tools/staff-premium/generer-icones.mjs`). Reste : `ActifsReferencesTest` une fois le layout branché.* Bricolage Grotesque et Public Sans en
  local (`public/assets/fonts/`, pas de Google Fonts en production, comme
  `fonts.css`). ApexCharts, Lucide, Flatpickr et GLightbox dans
  `public/assets/vendor/`, chacun avec sa licence. *Fini quand* : `ActifsReferencesTest` est vert.
- [ ] **P1-3 Composants Blade premium.** `resources/views/components/premium/` :
  `kpi`, `carte`, `en-tete` (titre, fil d'Ariane, actions), `pastille` (toujours
  icône et mot), `jauge`, `vide`, `onglets`, `menu-actions`, `tiroir`
  (remplace l'habit de `offcanvas-form`), `modale-suppression`, `toast`,
  `graphique` (ApexCharts et vue Tableau), `lucide`. Les composants actuels gardent
  leur API : seul leur rendu change derrière le drapeau.
  *Fini quand* : `ComposantsPartagesTest` est étendu et le guide `ds.showcase`
  présente chaque brique en clair et en sombre.
- [ ] **P1-4 Habillage DataTables.** `public/assets/css/verifall-datatables-premium.css` :
  en-têtes triables, pagination, recherche, sélection et actions groupées comme
  le moteur `gestion.js` du prototype. *Fini quand* : l'écran pilote Utilisateurs
  rend comme le prototype `utilisateurs`.
- [ ] **P1-5 Tests de classes.** `ClassesTailwindPresentesTest` lit aussi
  `staff-premium.css` pour les vues staff. *Fini quand* : aucune dette nouvelle.

### Phase 2 — Coquille de la console (E 4)

- [x] **P2-1 Layout de la console.** *18 oct. : il REMPLACE `components/layouts/staff.blade.php` (consigne « on remplace l'ancienne vue »), sans drapeau ; les écrans gardent leur habillage jusqu'à la phase 5.* Colonne vert forêt
  de 264 px, groupes repliables mémorisés, zone principale de 1 480 px au plus,
  pied de page. Sur téléphone, colonne en tiroir. Remplace `staff.blade.php`
  derrière le drapeau.
- [x] **P2-2 Navigation.** *18 oct.* `MenuDuBackOffice::GROUPES` reprend l'ordre, les
  libellés et les icônes Lucide du prototype (`app.js`, `NAV`) ; les compteurs
  (signalements, corrections, KYC) viennent d'un service mis en cache 60 s.
  *Fini quand* : `MenuDuBackOfficeTest` et `NavigationBackOfficeTest` sont verts.
- [x] **P2-3 Barre du haut.** *18 oct.* Recherche (ouvre la palette), **Actions rapides**,
  calendrier, cloche (`Staff\Cloche` réhabillée), thème, plein écran, avatar et
  menu du profil (profil, sécurité, préférences, réglages ou performance, espace,
  thème, aide, verrouiller, se déconnecter en POST).
- [x] **P2-4 Actions rapides.** *18 oct.* `config/console.php` déclare les actions de
  chaque espace (icône, libellé, aide, **route**). Comme le menu, chaque action
  n'apparaît que si la route est autorisée. Raccourci clavier `A`.
- [~] **P2-5 Palette Ctrl K.** *18 oct. : les écrans autorisés ; la recherche dans les données reste à faire.* Composant Livewire `Staff\Palette` : écrans
  autorisés, plus recherche dans les signalements, cours et comptes (dépôts
  existants, 8 résultats par type, débounce 250 ms).
- [~] **P2-6 Thème.** *18 oct. : clair par défaut, choix mémorisé sur l'appareil ; la préférence en base reste à faire (Mon compte).* Clair par défaut. Colonne `preferences` (JSON) sur `users`,
  lue au rendu du layout pour éviter le flash. Le choix « comme le système »
  suit `prefers-color-scheme`. *Fini quand* : le test du layout vérifie que
  `class="dark"` est absent par défaut.
- [ ] **P2-7 Espace admin ou mentor.** Le sélecteur n'apparaît que pour un
  compte qui a les deux rôles. Le mentor n'a que l'espace mentor.

### Phase 3 — Connexion, verrouillage, compte (E 4)

- [x] **P3-1 Page de connexion premium.** *18 oct.* `Auth\StaffLogin` réhabillé sur
  `connexion.html` : registre des verdicts (alimenté par les 6 derniers
  fact-checks publiés, mis en cache 10 min, statique si mouvements réduits),
  formulaire, étape 2FA à 6 cases avec collage, compteur d'essais lu dans
  `LoginAttempt`. Aucun texte ne révèle si l'adresse existe.
  *Fini quand* : les tests `Auth/*Staff*` sont verts et le rendu est mesuré.
- [ ] **P3-2 Mot de passe oublié staff.** `Auth\StaffMotDePasseOublie` et la
  réinitialisation (broker `staff`), avec un modèle de courriel du catalogue.
- [x] **P3-3 Verrouillage de session.** *18 oct. : verrouiller DÉCONNECTE la garde (arbitrage n° 180).* Route `staff.verrou` (GET et POST),
  middleware `SessionVerrouillee` : tant que `session('staff.verrouillee_a')`
  est posée, toute route staff redirige vers l'écran de verrouillage.
  Déverrouillage par `Hash::check` ; 5 échecs ferment la session et la
  journalisent (`SecurityLog`). Verrou manuel (menu, Ctrl L) et automatique
  après 15 min d'inactivité côté client ; l'expiration serveur de 30 min reste.
  *Fini quand* : test de fonctionnalité verrou → refus → déverrouillage → 5 échecs.
- [ ] **P3-4 Mon compte.** `Staff\MonCompte` en onglets : profil, sécurité
  (mot de passe, 2FA, codes de secours, sessions ouvertes et « fermer les
  autres »), préférences (langue, fuseau, thème, notifications). « Mon profil »
  du menu y mène, et non plus vers les réglages.

### Phase 4 — Tableaux de bord (E 5)

- [ ] **P4-1 Tableau de bord admin.** Réhabiller `Staff\TableauDeBord` : bureau
  de vérification (file triée par temps restant avant l'objectif de 6 h),
  chiffres clés, verdicts, signalements et fact-checks, état des moteurs,
  journal. Mêmes services, ApexCharts à la place des SVG.
- [x] **P4-2 Tableau de bord mentor dédié.** *18 oct. : route `staff.mentor.dashboard`.* Nouveau composant
  `Staff\Mentor\TableauDeBord` (route `staff.mentor.dashboard`, redirection
  du mentor à la connexion) :
  - « Ma journée » : frise de 8 h à 22 h avec lives, échéances de correction et vérifications assignées ;
  - chiffres clés ;
  - boîte de correction ;
  - vérifications assignées et leur échéance ;
  - mes cours en un coup d'œil, avec tendance ;
  - activité des apprenants ;
  - apprenants à risque de décrochage, avec relance ;
  - questions du forum sans réponse ;
  - prochaines sessions.

  Lecture dans un **dépôt** `DepotTableauDeBordMentor` (tableau de bord : dépôt
  obligatoire), borné par `EspaceDuMentor::idsDesCours`, mis en cache 5 min.
  *Fini quand* : un mentor ne voit que ses cours (test), et la relance est journalisée.

### Phase 5 — Écrans existants à réhabiller (E 12)

Par lots de domaine. Pour chaque écran : briques premium, état vide,
chargement et erreur, mobile, sombre, mesure de fidélité, tests existants verts.

- [ ] **P5-1 Fact-checking** (6) : Signalements (avec la fiche d'analyse en tiroir large), Fact-checks, Sources, Catégories de sources, Unités IA (journal), Outils.
- [ ] **P5-2 Académie** (12) : Cours (liste et cartes), Constructeur, Catégories, Banque de questions, Examens, Correction, Inscriptions aux cours et aux examens, Certificats, Modèle de certificat, Ventes premium, Statistiques.
- [ ] **P5-3 Espace mentor** (6) : Live classes, Mes contenus, Mes apprenants, Progression, Performance, Certificats.
- [ ] **P5-4 Hub** (3) et **Communauté** (8) : Actualités, Portails, Offres ; Forum, catégories, Blog, catégories, Infolettres, FAQ, Témoignages, Partenaires.
- [ ] **P5-5 Utilisateurs** (4) : Citoyens, Mentors, KYC (visionneuse), Rôles (matrice).
- [ ] **P5-6 Système** (6) : Réglages en onglets, Modèles de courriels (le **sujet** seulement, selon D6, avec l'aide-mémoire des variables du catalogue), Campagnes, Badges, Sauvegardes, Tâches planifiées.
- [ ] **P5-7 Journaux** (4) : Audit, Courriels, Unités, Alertes.
- [ ] **P5-8 Hors prototype** (7) : Tipline, Packs d'unités, Parrainages, Signalements de contenu, Mots surveillés, Messages de contact, Statistiques du cours. Habillés avec les mêmes briques, sans maquette.

### Phase 6 — Fonctionnalités nouvelles (E 10)

- [ ] **P6-1 Fiche d'un compte.** Router `Staff\FicheUtilisateur` (`staff.users.show`, garde `view,User`), la réhabiller, puis ajouter les onglets du PRD `docs/specs/PRD_PROFILS_CITOYEN_MENTOR.md` (lot A : vue d'ensemble, échecs, journal ; lot B : fiche apprenant côté mentor, statistiques des cours du mentor).
- [ ] **P6-2 Mon profil mentor** (`m-profil`) : la fiche mentor vue par le mentor lui-même.
- [ ] **P6-3 Hub et Communauté dans l'espace mentor**, selon D5.
- [ ] **P6-4 Éditeur pleine page** (article et infolettre) : Quill purifié, aperçu Google, enregistrement automatique en brouillon.
- [ ] **P6-5 Médiathèque** : disque `private`, routes signées, visionneuse.
- [ ] **P6-6 Calendrier** : lives, examens, campagnes, maintenance ; glisser-déposer pour les droits `update`.

- [ ] **P6-7 Mode maintenance premium** (E 2) — demandé le 19 octobre ; arbitrage n° 181. Fermer le site au public pendant une mise à jour majeure, l'équipe gardant tout son accès.
  - [x] **P6-7a Exemptions sûres** : connexion console par Livewire (`/livewire/update` limité aux composants `auth.staff-login` et `auth.verrou-de-session`), écran de verrouillage, rappels Mobile Money, état `maintenance.etat`, réponse JSON 503 pour l'API.
  - [x] **P6-7b Écran console** (`staff.maintenance-mode`) : état en grand, motifs de départ, durées d'un clic, aperçu, « pendant la maintenance », avant de fermer, avant de rouvrir, historique relu dans le journal d'audit.
  - [x] **P6-7c Bandeau** dans la coquille tant que le site est fermé, sur tous les écrans.
  - [x] **P6-7d Page publique premium** « La boussole se recalibre » : autonome, décompte honnête sur l'anneau de la boussole, retard dit franchement, réouverture annoncée sans rafraîchir.
  - [x] **P6-7e Prototype** : écran `#/maintenance` et `maintenance.html`.
  - [ ] **P6-7f** Fermeture programmée et annonce préalable aux citoyens (bandeau public « maintenance prévue à… ») — à arbitrer : fermer à heure fixe peut couper une saisie en cours.
  - [ ] **P6-7g** Traduction anglaise de la page publique (`lang/en`), aujourd'hui en français seul.

### Phase 7 — Analytique (E 8)

- [ ] **P7-1 Académie** (complétion, entonnoir, engagement, relances).
- [ ] **P7-2 IA de vérification** (19 moteurs, justesse, usage) à partir de `EngineRun`.
- [ ] **P7-3 Revenus et quotas.**
- [ ] **P7-4 Business Intelligence** (constructeur d'indicateurs) — à reconsidérer après P7-1 à P7-3.
- [ ] **P7-5 SEO** : exige Search Console et GA4 ; hors plan tant que les accès ne sont pas fournis.

### Phase 8 — Bascule et finition (E 3)

- [ ] **P8-1** Mesure de fidélité rejouée sur tous les écrans premium (`CaptureDesEcransTest`) ; `CONFORMITE_PROTOTYPE.md` régénéré.
- [ ] **P8-2** Audit d'accessibilité (contraste clair et sombre, clavier, lecteurs d'écran) sur la coquille et 5 écrans types.
- [ ] **P8-3** Drapeau `console_premium` activé par défaut ; suppression de l'ancien layout et des feuilles staff mortes ; `ActifsReferencesTest` vert.
- [ ] **P8-4** Documentation : `DESIGN_SYSTEM_NOTIFICATIONS_RBAC.md`, `VERIFALL_DESIGN_HANDOFF.md`, `RESTE_A_FAIRE.md` mis à jour.

**Total estimé : environ 50 jours de travail**, dont 23 pour obtenir une
console complète au nouvel habit (phases 1 à 5).

## 4. Risques

| Risque | Parade |
|---|---|
| Deux feuilles Tailwind divergent sur une même classe | Les vues citoyennes ne chargent jamais `staff-premium.css`, et les vues staff ne chargent plus `tailwind.css` une fois la phase 8 faite |
| Le réhabillage casse un test qui lit le HTML | Garder les `wire:` et les `id` existants ; lancer la suite complète par lot de domaine |
| Les polices et bibliothèques alourdissent le premier chargement | Polices en `woff2` sous-ensemble latin, ApexCharts chargé seulement sur les écrans à graphiques (`@push('scripts')`) |
| Le verrou client contourné en désactivant JavaScript | Le verrou est **serveur** (session et middleware) ; le JavaScript ne fait que le déclencher |
| Écrans nouveaux sans données réelles (BI, SEO) | Ne rien figer dans un gabarit : un chiffre non mesuré ne s'affiche pas (règle du chantier) |

## 5. Journal d'avancement

| Date | Tâche | Résultat |
|---|---|---|
| 18 oct. 2026 | P0-1, P0-2 | Cartographie et plan |
| 18 oct. 2026 | P0-3 | `CLAUDE.md` et arbitrage n° 176 |
| 18 oct. 2026 | D5, D6, D7 | Arbitrages n° 177 à 179, historiques RGPD implémentés |
| 18 oct. 2026 | P2, P3-1, P3-3, P4-2 | Coquille premium à la place de l'ancien layout, connexion, verrouillage serveur (n° 180), tableau de bord mentor |
| 19 oct. 2026 | P6-7a à e | Mode maintenance premium : exemptions (n° 181), écran console, bandeau, page publique, prototype ; `tests/Feature/Console/ModeMaintenanceConsoleTest.php` |
| 18 oct. 2026 | P1-1, P1-2 | Feuille `staff-premium.css`, polices, bibliothèques, icônes Lucide côté serveur, `tests/Feature/Console/FondationsPremiumTest.php` |
