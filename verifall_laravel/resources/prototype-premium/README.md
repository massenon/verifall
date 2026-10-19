# Prototype premium — console d'administration et espace mentor

Prototype HTML de validation de la refonte. **Aucun code Laravel n'est modifié** :
ce dossier sert à valider le design et les fonctionnalités avant la transposition
en Blade et Livewire.

## L'ouvrir

Double-cliquez sur `index.html`. Il n'y a ni serveur ni installation à prévoir, et
les bibliothèques sont dans `assets/vendor/`. Seules les polices viennent de Google
Fonts ; hors ligne, la police système prend le relais.

- **Admin / Mentor** : le sélecteur en haut de la colonne bascule d'espace.
- **Ctrl K** (ou ⌘K) : palette de commandes pour aller à n'importe quel écran.
- **Barre du haut** : l'icône plein écran, et l'avatar rond qui ouvre le menu du
  profil (profil, sécurité, préférences, changement d'espace, thème, aide,
  déconnexion). Il se pilote aussi au clavier : flèches, Début, Fin, Échap.
- **Thème** : clair par défaut, quel que soit le réglage du système. L'icône
  lune ou soleil, ou l'onglet Préférences de « Mon compte » ; « Comme le
  système » suit alors le réglage de l'appareil.
- **Connexion** : `connexion.html`. À gauche, le registre des verdicts ; à
  droite, identifiants puis code de double authentification, ou mot de passe
  oublié. Comptes de démonstration dans la page.
- **Actions rapides** : le bouton vert de la barre du haut, ou la touche `A`.
  Les gestes fréquents de l'espace en cours.
- **Verrouillage** : `Ctrl L`, le menu du profil, ou 15 minutes sans geste.
  L'écran garde le travail en cours et redemande le mot de passe ; il survit
  à un rechargement. Cinq échecs ferment la session.
- Les données sont fictives. Rien n'est envoyé : les boutons affichent une
  notification qui dit ce qui se passerait.

## Ce qu'il contient : 83 écrans

| Espace | Écrans |
|---|---|
| Pilotage | Tableau de bord (bureau de vérification en direct), Calendrier mois, semaine, jour et liste avec glisser-déposer |
| Analytique | Business Intelligence (constructeur d'indicateurs, sources de données, rapports partagés), IA de vérification (19 moteurs, matrice de justesse, journal d'usage), Académie (complétion, parcours, engagement, relances), SEO (positions, liens entrants, origines du trafic), Revenus et quotas (tunnel de paiement, classement, points et quotas) |
| Fact-checking | Signalements (fiche d'analyse et publication du verdict), Fact-checks, Sources vérifiées et leurs catégories, Unités IA, Répertoire des outils |
| Académie | Cours (liste ou cartes), Constructeur de cours (plan réordonnable, éditeur Quill), Catégories, Banque de questions, Examens, Correction (barème et commentaire), Inscriptions aux cours et aux examens, Certificats, Modèle de certificat, Ventes premium, Statistiques globales |
| Hub d'infos | Actualités, Portails officiels, Offres d'emploi vérifiées |
| Communauté | Forum, Blog, éditeur d'article (Quill et aperçu Google), Médiathèque (GLightbox), Infolettres, FAQ (recherche et catégories), Témoignages, Partenaires, catégories |
| Utilisateurs | Citoyens, Mentors, **fiche complète d'un citoyen** (9 onglets : vue d'ensemble, vérifications et échecs, rapports, Académie, Hub d'opportunités, communauté, unités et badges, connexions et sécurité, journal complet), **fiche complète d'un mentor** (cours créés, apprenants, statistiques, corrections et vérifications assignées, lives et examens, revenus, connexions, journal), Vérification KYC (pièces en visionneuse), Rôles et permissions (matrice) |
| Système | Réglages (onglets : général, moteurs IA, paiements, sécurité, maintenance), **Modèles de courriels** (CRUD complet : liste, création, modification, duplication, activation, suppression ; éditeur avec variables autorisées en JSON ou en texte, aide-mémoire des variables à copier ou insérer, contrôles avant enregistrement, aperçu ordinateur et téléphone, envoi de test), Campagnes, Badges, Sauvegardes, Tâches planifiées, **Mode maintenance** (état, motifs, heure annoncée, ce qui passe pendant la fermeture, historique ; page publique `maintenance.html`) |
| Journaux | Audit, Courriels, Unités, Alertes d'intrusion |
| Mentor | Tableau de bord du mentor (« Ma journée » de 8 h à 22 h, boîte de correction, vérifications assignées, mes cours et leur tendance, activité, décrochage, questions du forum, sessions), Live classes, Mes cours, Sections et leçons, Mes contenus, Quiz, Devoirs à corriger, Examens, Mes apprenants (et la fiche de chaque apprenant, sans les données sensibles), Progression, Certificats, Ma performance, Mon profil, **Hub d'infos** (Actualités, Portails officiels, Offres d'emploi) et **Communauté** (Forum, catégories, Blog, catégories, Nouvel article, Médiathèque, Infolettres, FAQ, Témoignages, Partenaires) |
| Compte | Profil, sécurité (mot de passe, double authentification, sessions), préférences (langue, thème, notifications) |

Chaque écran de liste offre : recherche, filtres, période, tri, pagination,
sélection multiple et actions groupées, menu d'actions rapides, export CSV,
formulaire latéral avec validation, et confirmation de suppression.

## Choix de design

Le design suit le skill `frontend-design`, installé dans `.claude/skills/` à la
racine du dépôt.

- **Concept** : une salle de vérification. Ce qu'on retient, c'est le *bureau de
  vérification* du tableau de bord, la file des signalements triée par le temps
  qui reste avant l'objectif de 6 h. Le reste de l'interface reste sobre.
- **Couleurs** : une colonne vert forêt `#0E2A1E`, le vert de la marque `#09B850`
  réservé aux actions et à l'état actif, un fond papier `#F6F8F5`. Le mode sombre
  utilise les mêmes teintes, avec ses propres valeurs.
- **Typographie** : Bricolage Grotesque pour les titres, Public Sans pour le texte
  et les chiffres.
- **Graphiques** : ils suivent le skill `dataviz`, palette validée par son script
  sur les vraies surfaces (`#ffffff` et `#13211a`). Chaque graphique a un seul axe,
  une couleur qui suit l'entité et une vue « Tableau » équivalente. Les verdicts
  utilisent les couleurs de statut, toujours accompagnées d'une icône et d'un mot.
- **Accessibilité** : focus clavier visible, mouvements réduits respectés, Échap
  ferme les couches, contrastes vérifiés en clair et en sombre, mise en page
  testée jusqu'à 390 px de large.

## Organisation

```
index.html                 la coquille : colonne, barre du haut, couches
connexion.html             la page de connexion (assets/js/connexion.js)
maintenance.html           la page publique de maintenance (« la boussole se recalibre »)
tailwind.config.js         les jetons de design
assets/css/input.css       les composants (boutons, champs, pastilles, tableaux…)
assets/css/app.css         le CSS compilé, à régénérer après modification
assets/js/donnees.js       les données fictives
assets/js/ui.js            les briques : toast, tiroir, modale, menu, validation…
assets/js/graphiques.js    la configuration ApexCharts commune
assets/js/gestion.js       le moteur des écrans de liste
assets/js/ecrans-*.js      les écrans (ecrans-profils.js : les fiches ;
                           ecrans-courriels.js : les modèles de courriels)
assets/js/app.js           navigation, routage, thème, palette de commandes
assets/vendor/             ApexCharts 3.54.1, Flatpickr 4.6.13, Lucide 0.468.0,
                           GLightbox 3.3.0, Quill 2.0.3, FullCalendar 6.1.15
```

Régénérer le CSS après une modification :

```
npx tailwindcss@3.4.17 -c tailwind.config.js -i assets/css/input.css -o assets/css/app.css --minify
```

## Après validation : la transposition

Le prototype est découpé pour se transposer brique par brique :

1. `assets/css/app.css` et les bibliothèques passent dans `public/assets/`, comme
   DataTables aujourd'hui (sans Vite, compatible cPanel).
2. Les briques de `ui.js` deviennent les composants Blade existants ou nouveaux :
   `<x-kpi>`, `<x-offcanvas-form>`, `<x-delete-modal>`, `<x-toast>`, `<x-data-table>`.
3. `gestion.js` correspond au gabarit `Staff\Opportunites` : chaque définition
   d'écran devient un composant Livewire qui suit ce gabarit.
4. Les écrans d'analyse lisent les dépôts existants (`app/Infrastructure/Persistence`).
   Le SEO demande en plus de brancher Search Console et GA4.

La transposition contredit la règle « référence visuelle absolue : le prototype »
de `CLAUDE.md` pour l'admin et le mentor : il faudra mettre ce document à jour au
moment de la valider.

## Deux écarts avec le code Laravel, à trancher

- **Fiches profil.** `Staff\FicheUtilisateur` et `FicheDuCompte` existent déjà,
  avec 16 onglets. Le prototype montre ce qu'il faudrait leur **ajouter**. La
  spécification est `docs/specs/PRD_PROFILS_CITOYEN_MENTOR.md`. Deux onglets
  attendent une décision RGPD : le Hub consulté et l'historique des connexions.
- **Modèles de courriels.** Le prototype permet de modifier le corps d'un
  modèle et de déclarer des variables pour un modèle créé à la main. Dans le
  code, `EmailTemplate` ne surcharge que le **sujet** : son commentaire explique
  qu'un corps modifiable perdrait le code à usage unique ou le lien signé. Les
  `variables` y sont écrites par le semis depuis le catalogue, jamais saisies à
  la main. Le prototype respecte cette règle pour les modèles système : leurs
  variables y sont en lecture seule, et ils ne se suppriment pas. Ouvrir le
  corps à l'édition reste une décision à prendre avant toute transposition.
