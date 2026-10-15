# Ce qui reste — inventaire

Compté le 25 août 2026, sur l'état réel du dépôt. Chaque chiffre de cette page
se revérifie par une commande ; aucun n'est estimé.

| Repère | Valeur | Comment le revérifier |
|---|---:|---|
| Pages du prototype | 107 | `ls resources/prototype/**/*.html` |
| Écrans mesurés | 43 | `docs/CONFORMITE_PROTOTYPE.md` |
| Fidélité moyenne | 88 % | idem |
| Tests | 831 | `php artisan test` |
| Migrations | 36 | `ls database/migrations` |
| Composants Livewire | 40 | `find app/Livewire -name '*.php'` |
| Écarts consignés | 78 | `grep -c '^## [0-9]' docs/ARBITRAGES.md` |

---

## Sprint 6 — Back-office

**59 écrans au prototype** (46 administration + 13 mentor). **7 routes existent**
aujourd'hui : connexion, déconnexion, tableau de bord, journal d'audit, alertes
d'intrusion, base des faits vérifiés, packs d'unités.

Le **premier accès** au back-office fonctionne enfin : jusqu'au 25 août, un
compte staff neuf ne pouvait pas entrer du tout — la 2FA était exigée sans
qu'aucun écran permette de l'activer (arbitrage n° 78).

Le détail des lots est cadré dans `docs/sprints/SPRINT_6_BACK_OFFICE.md`, ordonné
par valeur débloquante. Ce qui est mesuré à ce jour :

| Écran | Fidélité | Ce qu'il lui manque |
|---|---:|---|
| `compte-admin/dashboard.html` | 46 % | Encore un gabarit vide. Dix panneaux, dont deux qui demandent une analytique inexistante — visites, navigateurs. |
| `compte-admin/alertes.html` | 58 % | Filtres avancés et export. |
| `compte-admin/securite.html` | 58 % | Filtres avancés et export. |
| `compte-admin/base-de-connaissances.html` | non mesuré | Écran livré par agent, jamais ouvert dans un navigateur. |

**Restent sans aucune route** : studio de cours (leçons, quiz, exercices),
correction des copies et des examens, modération (signalements, forum, contenus),
utilisateurs, rôles, KYC, mentors, Hub, blog, campagnes, infolettre, témoignages,
partenaires, paramètres, maintenance, cron, journaux d'e-mails et d'unités.

## Sprint 8 — Transverse

Le contrat d'API est **écrit et implémenté** ; le reste n'est pas commencé.

1. **Contrat d'API v1.** ✅ `docs/kb/API_SPECIFICATION.md` écrit, et servi par
   `routes/api.php` + `app/Http/Api/V1/` : **53 endpoints** sur les 58 déclarés
   par le simulateur mobile, 130 tests dans `tests/Feature/Api/`.
   **Cinq appels ne sont pas servis** et le sont dit franchement — les trois
   `notifications` (aucun magasin côté serveur), `verifications/{uuid}/feedback`
   (aucun stockage) et `certificates/{code}/pdf` (aucune bibliothèque PDF
   installée). Détail et conditions de levée : §11 du contrat, et
   `ARBITRAGES.md` n° 73.
2. **Référencement.** `ClaimReview` sur les analyses publiées, plan de site,
   métadonnées sociales.
3. **Analytique.** Visites, navigateurs, contenus consultés — c'est elle qui
   manque aux deux panneaux du tableau de bord d'administration.
4. **Sauvegardes**, et une restauration réellement éprouvée.
5. **Durcissement — largement fait, deux manques nommés.** Relevé le 25 août :
   - ✅ `SecureHeaders` est branché **globalement** (`bootstrap/app.php`) et pose
     `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
     `Cross-Origin-Opener-Policy`, `X-Permitted-Cross-Domain-Policies`,
     `Permissions-Policy` ; HSTS **seulement sur connexion chiffrée**, et
     `Cache-Control: no-store` sur toute réponse authentifiée.
   - ✅ Limitation de débit : trois seaux d'API (`api-identite`, `api-lecture`,
     `api-ecriture`), plus l'inscription et le renvoi de code côté web.
   - ✅ `composer audit` : **aucune vulnérabilité**.
   - ❌ **Pas de `Content-Security-Policy`**, et l'absence n'est pas expliquée.
     Ce n'est pas un oubli mécanique : Livewire injecte du script en ligne et
     Alpine évalue des expressions, si bien qu'une CSP naïve casse l'application
     **dans le navigateur, sans qu'aucun test ne le voie**. À traiter avec une
     vérification au navigateur sur les 107 pages, pas en ajoutant une ligne.
   - ❌ **`npm audit` est impossible** : il n'existe pas de `package-lock.json`.
     Sans conséquence réelle — la chaîne Vite est dormante (voir `CLAUDE.md`,
     flux d'exécution) et `node_modules/` est vide — mais cela signifie que les
     six dépendances de `package.json` ne sont **revues par personne**.
6. **Audit RGPD** de bout en bout.

## Application mobile — 5 parcours

**La documentation d'implémentation est écrite** : `docs/flutter/`, quinze
fichiers, les 30 écrans du simulateur documentés un par un — arbre de widgets,
état, appels réseau, états de chargement, vide et erreur, comportement hors
ligne, accessibilité.

L'ordre de travail est celui de la section 8 : un **socle** livré d'abord puis
**gelé** — thème, réseau, routeur, base locale, composants partagés — et ensuite
seulement les cinq parcours, en parallèle. Deux fichiers concentrent tout le
risque de conflit, l'agrégateur de routes et le schéma de base ; le socle les
neutralise en important d'avance six fichiers, un par module.

M1 authentification (6 écrans) · M2 accueil et vérification (7) · M3 Académie
hors ligne (6) · M4 Hub et forum (5) · M5 compte (6). Trente au total.

---

## Défauts trouvés et corrigés le 25 août

Aucun de ces points n'était signalé par un test : tous les écrans répondaient
200, et la suite était verte. Ils ont été trouvés en regardant, pas en
exécutant.

### Visibles par n'importe quel visiteur

- **L'état actif du menu public était doublement faux.** `text-primary` était
  FIGÉ sur « Pourquoi Vérif'All ? » — le gabarit ayant été recopié depuis cette
  page-là, ce lien s'affichait actif sur les 107 pages. Et la couleur dynamique
  reposait sur une classe `is-active` **absente du CSS**. Onze occurrences de
  cette classe morte ont été retirées ailleurs.
- **Le favicon mesurait 1 × 1 pixel**, alors que les six gabarits le déclarent :
  aucun onglet de navigateur n'affichait d'icône, nulle part.
- **`maskable-icon-512x512.png` faisait 128 × 128** malgré son nom, et sans
  marge de sécurité : Android aurait rogné le logo.
- **Toute l'application tournait en anglais.** `APP_LOCALE=en` : les dates
  sortaient « 9 July 2026 » sous les articles français. Corrigé — avec les
  fichiers de langue, sans lesquels passer en `fr` affichait des clés brutes
  (`validation.email`) au lieu des messages.
- **La cloche de notifications était allumée en permanence.** La pastille rouge
  était écrite en dur dans le gabarit, et la liste venait du `localStorage`.

### Invisibles, mais coûteux

- **63 Mo d'images servies.** La page d'accueil chargeait un PNG de 12 Mo dans
  une carte de 240 px de haut. Régénérées depuis les originaux : **63 Mo →
  5,1 Mo**, plus 8 Mo d'autres actifs allégés, plus 4 Mo de PNG morts retirés.
- **La purification détruisait la structure des articles.** La configuration par
  défaut de `mews/purifier` supprime `h2`, `h3`, `figure`, `blockquote`,
  l'attribut `title` d'une image et son `loading="lazy"`. Un article écrit avec
  des sous-titres ressortait **en un seul paragraphe**, sans erreur ni trace.
- **Le suivi de visites comptait n'importe quoi.** Un `increment('views')` par
  chargement : robots d'indexation, rafraîchissements, et surtout les aperçus de
  lien — coller une adresse dans WhatsApp faisait « une vue ».
- **Le flux d'installation documenté échouait.** `CLAUDE.md` commençait par
  `composer install && npm ci` : il n'existe aucun `package-lock.json`,
  `node_modules/` est vide, et **aucun gabarit n'appelle `@vite`**.

### Une correction tentée et NON retenue

**Le sous-ensemble de la police d'icônes.** `material-symbols-rounded.woff2`
pèse **5,35 Mo** — le poste le plus lourd du site, devant toutes les images
réunies — pour dessiner **99 icônes**. Deux tentatives, deux échecs mesurés :
par texte, la fermeture GSUB ramène les 4 271 ligatures et le gain est nul
(5,35 → 3,85 Mo) ; par glyphes, le fichier tombe à 137 Ko mais **toutes les
ligatures meurent** et chaque icône s'affiche en toutes lettres.

La police d'origine a été restaurée intacte. Le gain reste le plus gros
disponible sur le site ; il demande une chaîne de sous-ensemble vérifiée, pas
une commande.

---

## Ce que l'intégration des quatre lots a laissé ouvert

*26 août 2026 — après la passe qui a soudé les lots « transparence & diffusion »
au socle. Suite complète : 1 154 tests.*

Les quatre lots ont été écrits en parallèle, chacun interdit d'écrire dans les
fichiers de couture. Ils ont donc livré du code juste que rien n'appelait ; la
passe d'intégration a posé les routes, l'observateur, les liaisons du conteneur
et les deux tâches planifiées, et `tests/Feature/IntegrationDesLotsTest.php`
garde désormais ces soudures.

**Les cinq points ouverts par cette intégration sont refermés** (26 août) :

- **Le formulaire de contact poste enfin.** Table `contact_messages`, dépôt,
  service, contrôleur, limite de cadence à six par minute, piège à robots et
  délai minimal de saisie. Le message est ÉCRIT d'abord, la notification part
  ensuite : un serveur SMTP qui refuse ne perd plus rien. 10 tests.
- **Les signalements de contenu ont un écran**, ouvert au modérateur, toutes
  familles de contenu dans une seule file, les plus anciens en haut. Aucune
  suppression : un signalement se tranche, la trace demeure. 12 tests avec la
  boîte de contact.
- **`DemoSeeder` peuple les trois écrans** — huit abonnés dans les trois états,
  cinq messages dans les quatre statuts, des signalements sur deux types.
  Un écran vide et un écran cassé se ressemblent exactement.
- **Les cinq classes Tailwind « manquantes » n'en sont pas.** Vérification
  faite : le CSS du prototype et le nôtre sont identiques au bit près, et le
  prototype emploie lui-même `py-2.5` dans 95 de ses pages sans le déclarer.
  Il s'affiche donc déjà sans elles. Les ajouter serait une refonte. Deux tests
  gardent l'empreinte du build et l'absence de ces déclarations.
- **La double authentification ne verrouille plus tout le personnel dehors.**
  Un secret chiffré avec une `APP_KEY` révolue levait `DecryptException`
  jusqu'à l'écran de connexion — 500, sans chemin de récupération. Il compte
  désormais comme facteur ABSENT, ce qui renvoie vers l'enrôlement. Commande
  `verifall:code-2fa` pour le développement local. 4 tests.

Reste ouvert, par ordre de coût si on l'oublie :

- **La barre latérale du back-office n'a AUCUNE garde de permission** — pas une
  seule sur la quarantaine de liens du gabarit. Chaque rôle voit donc le menu
  entier, y compris les écrans que sa Policy lui refuse : le clic répond 403, et
  le menu promet ce que le serveur interdit.
  Les deux entrées ajoutées le 26 août (« Contenus signalés », « Messages
  reçus ») sont conditionnées, mais les autres ne le sont pas. Les aligner
  changerait ce que chaque rôle voit — c'est une décision de produit autant que
  de code, pas un correctif de passage. Aucune faille : la Policy tient, seul
  l'affichage ment.

- **Aucune destination configurée pour le courrier** tant que `contact_email`
  n'est pas renseigné dans les réglages : le message est enregistré et l'écran
  le montre, mais personne n'est prévenu. Un avertissement est journalisé
  plutôt qu'une adresse inventée — une boîte que personne ne relève
  ressemblerait à un fonctionnement normal.
- **Pas de réponse depuis le back-office.** Le courriel de notification porte
  un `Reply-To` : on répond depuis son client mail. Bâtir un client de
  messagerie ici serait un projet à part, et un moins bon.
- **`Campaign::$fillable` ne porte pas `edition_key`** ; le dépôt passe par
  `forceFill` (arbitrage 159). Confort, pas faille.
- **La factorisation matricielle de X n'est pas implémentée** : l'accord par
  paires suffit et se relit, mais cessera de tenir dans sa fenêtre horaire
  passé un certain volume de votes.
- ~~**PHPStan n'est pas installé**~~ — **refermé le 1er septembre 2026.**
  `larastan` 3.10 est installé, `phpstan.neon` écrit, le niveau 6 sort vert.
  La ligne est laissée barrée plutôt que supprimée : elle a tenu six jours en
  contradiction avec la section « Dette nommée » juste en dessous, qui décrivait
  déjà la baseline. Voir cette section pour l'état réel.

## Dette nommée

Elle est nommée pour ne pas être découverte plus tard :

- **92 imprécisions de typage figées dans `phpstan-baseline.neon`.** Aucune
  n'est un bug d'exécution : 29 `return.type`, 14 `argument.type`, le reste en
  génériques et itérables manquants, dont l'essentiel dans `app/Livewire`. Le fichier
  a rétréci deux fois depuis sa pose — 95 → 93 → 92 —, chaque fois parce qu'une
  annotation ajoutée a rendu une entrée caduque. C'est le sens de marche voulu.
  est un **cliquet** : tout code nouveau est analysé pleinement au niveau 6, et
  la liste ne doit que rétrécir. Le geste interdit est de le régénérer d'un bloc
  pour absorber une erreur neuve. Elles se paient par tranches, en même temps
  qu'on retouche le fichier concerné — pas en une passe dédiée.
- **13 adaptateurs sur 19** restent à porter. Six le sont, et le contrat est le
  même pour tous — le portage est mécanique, pas conceptuel.
- **Dépôts des sprints 1 et 2.** Ces deux sprints interrogent Eloquent depuis
  leurs services, sans passer par un dépôt. La règle a été posée après ;
  l'aligner demande une passe dédiée.
- **La suite de tests dépend du réseau** dans `uncompromised()` (contrôle des
  mots de passe compromis). Hors ligne, ces tests échouent pour une raison qui
  n'a rien à voir avec le code.
- **Onglet « Détails » et FAQ** de la fiche publique d'un cours : le prototype
  les montre, aucune colonne ne les porte.
- **Tests d'examen** : ni fabrique, ni seeder, ni appel à `AuditLogService`
  dans les services d'examen — signalé par l'agent qui les a écrits.
- **Intermittence observée** le 24 août sur `ProfilTest` (changement d'adresse
  e-mail) : quatre tests en échec sur une exécution complète, verts en isolation
  et verts à la ré-exécution complète.
  **Recherchée le 25 août, non reproduite** : trois exécutions isolées (18 tests
  verts chacune) et une exécution complète en ordre aléatoire —
  `php artisan test --order-by=random`, graine **1787645766** — qui rend
  **865 passés, 1 ignoré, 2 218 assertions**.
  Elle reste donc **ouverte et non expliquée**, pas refermée : un défaut vu une
  fois et non reproduit quatre fois de suite n'est pas un défaut corrigé. La
  piste la plus probable — un test qui dépend de l'horloge ou d'un état laissé
  par un voisin — se confirmera en rejouant d'autres graines.

  **DEUXIÈME OCCURRENCE, le 1er septembre 2026**, sur un autre fichier :
  `FinalisationInscriptionTest` → « le bonus de bienvenue », deux tests en échec
  sur une exécution complète en parallèle (`ModelNotFoundException` : le compte
  créé par le parcours d'inscription n'est pas retrouvé). Verts en isolation
  (14/14), verts au rejeu complet immédiat (1 686 passés).
  
  Le motif se précise donc : **deux fichiers différents, même forme** — un
  enregistrement créé puis introuvable, uniquement en parallèle, jamais
  reproductible. Cela oriente vers un partage d'état entre processus paratest
  plutôt que vers un test qui dépend de l'horloge. À reprendre avec
  `--recreate-databases` et en épinglant le nombre de processus.

## Écarts consignés

78 entrées dans `docs/ARBITRAGES.md`. **Aucune ne reste ouverte.**

La dernière — n° 15, le prix des packs d'unités — s'est fermée le 25 août : la
grille vit désormais en base et se modifie depuis le back-office
(`admin/packs-unites`). Ce n'était pas une décision technique, c'est pour cela
qu'elle a tenu treize entrées : elle appartenait au porteur du projet, et il lui
manquait seulement un écran pour la prendre.

Reste un manque, qui n'est pas un arbitrage :

- **`docs/kb/`** ne contient que l'API, écrite ici. Les deux autres documents de
  la base de connaissances n'ont jamais été livrés ; ils ont chacun un
  substitut, nommé dans `CLAUDE.md`.


---

## Ce que le module Académie laisse ouvert

*2 septembre 2026 — après le lot 14 (champs de cours, constructeur de leçons,
statistiques) et le branchement de la prime d'XP.*

La prime de fin de cours est versée, annoncée avant l'inscription et au moment
où elle tombe ; les prérequis s'affichent. Restent trois manques, aucun
bloquant :

- ~~**La langue du cours n'est pas un filtre de catalogue.**~~ **Refermé le
  2 septembre 2026.** Filtre `#[Url]`, affiché seulement à partir de deux
  langues publiées.
- ~~**Aucune écriture du solde d'XP n'est journalisée.**~~ **Refermé le
  2 septembre 2026.** Table `xp_logs`, service `JournalDXp`, calqués sur le
  journal des unités ; les quatre `increment('xp')` dispersés y passent
  désormais tous. Lu sur l'écran Récompenses, panneau « D'où viennent vos XP ».
  `enrollments.xp_awarded_at` reste ce qu'elle était — le garde-fou d'unicité de
  la prime de cours —, mais elle n'est plus le seul témoin d'un gain.
- **`Transcription` reste non portée**, et c'est elle qui rouvrirait les boutons
  Vidéo et Audio de l'écran de vérification citoyen, aujourd'hui inactifs avec
  un message d'indisponibilité.

Manque structurel, plus large que l'Académie : **rien ne garantit qu'une route
déclarée est atteinte depuis au moins un écran.** Quatre fois en deux lots, une
chaîne serveur complète n'a atteint aucun gabarit — documents joints, leçons
audio, SEO du Hub, prime d'XP —, et chaque fois c'est une passe au navigateur
qui l'a vu, jamais la suite. Un test de ce genre rendrait ces passes moins
nécessaires.


---

## Les courriels déclarés mais pas encore expédiés

> **Mis à jour le 2 septembre 2026 : quatre ont été câblés**, et le compte
> d'origine était faux. Il en restait **neuf**, pas huit ; il en reste
> **cinq**. Le tableau ci-dessous est celui d'avant le câblage ; ce qui reste
> est repris juste après.

### Ce qui reste — TROIS, au 2 septembre 2026 (soir)

`alerte_intox` et `nouveau_cours` ont été câblés : les deux décisions d'audience
sont prises et documentées dans le job qui expédie.

| Clé | Ce qui manque |
|---|---|
| ~~`compte_suspendu`, `compte_retabli`~~ | **Câblés le 2 septembre 2026.** Colonnes `users.suspended_at / suspension_reason / suspended_by`, service `SuspensionDeCompte` (qui ferme aussi les sessions ouvertes), actions sur l'écran Utilisateurs, et refus de connexion qui DIT sa raison — après vérification du mot de passe, pour ne pas créer d'énumération. |
| ~~`reaction_recue`~~ | **Câblé le 2 septembre 2026**, avec un repos de six heures PAR CONTENU — un billet qui décolle ne produit qu'un message, pas cinquante. Livré éteint. |

### Le tableau d'origine

## Les huit courriels déclarés mais pas encore expédiés

*2 septembre 2026 — après le lot « gabarits d'emails ».*

Le catalogue déclare 24 envois ; **seize partent réellement**. Les huit autres
ont leur texte, leur objet, leurs variables et leur ligne d'écran — et rien ne
les expédie encore. Ce n'est pas un oubli caché : `CatalogueDesCourriels` porte
un drapeau `porte`, et l'écran affiche « Pas encore câblé » sur chacun.

| Clé | Ce qu'il manque |
|---|---|
| `compte_suspendu` / `compte_retabli` | La modération change le statut sans notifier. Verrouillés au catalogue : une sanction dont on n'informe pas la personne est, de son point de vue, une panne. |
| `alerte_intox` | Il faut d'abord un moyen de marquer un démenti « urgent » à la publication — sans quoi le déclencheur n'existe pas. |
| `contre_expertise_tranchee` | La notification interne existe (`ContreExpertiseTranchee`), le canal `mail` non. |
| `cours_termine` | Le déclencheur existe déjà : la branche d'achèvement de `ProgressionAcademie::rafraichir()`, à côté du certificat et de la prime d'XP. |
| `nouveau_cours` | Suppose une audience : tous les inscrits ? les abonnés ? C'est une décision de produit avant d'être du code. |
| `badge_debloque` | Déclencheur existant (`AttributionDesBadges`). Livré ÉTEINT de toute façon. |
| `unites_creditees` | Déclencheur existant (`EconomyService::crediter`). |
| `reaction_recue` | Livré ÉTEINT : un courriel par réaction est ce qui fait filtrer tout le reste. |

Le câblage de chacun est mécanique — `Mail::to(...)->send(new CourrielDeGabarit(...))`
sous une garde `estAllume()`. Ce qui ne l'est pas : décider l'audience de
`nouveau_cours`, et poser le marqueur d'urgence pour `alerte_intox`.

## L'œil du personnel

`staff-login.blade.php` n'a pas reçu le composant `<x-champ-mot-de-passe>` : il
emploie `.ffield`, la classe du design system staff, là où le composant porte
`.form-floating` du design citoyen. Y plaquer le composant aurait changé
l'apparence de l'écran de connexion du personnel sans qu'on l'ait demandé, et
sans vérification au navigateur. Le comportement délégué, lui, est déjà en place :
poser `data-pwd-eye` sur un bouton suffira le jour où la variante staff du
composant sera écrite.


---

## L'intermittence des tests — refermée le 2 septembre 2026

~~Paratest partage un état ; à retenter avec `--recreate-databases`.~~
**Diagnostiquée, et la supposition d'origine était fausse.**

`CACHE_STORE=array` fait vivre le cache DANS le processus PHP. Paratest enchaîne
des dizaines de fichiers dans le même processus, `LazilyRefreshDatabase` vide la
base entre eux, et le cache de spatie garde des identifiants de rôles qui ne
désignent plus rien. D'où un `hasRole()` faux ou un `ModelNotFoundException`,
uniquement en parallèle.

Correctif : `forgetCachedPermissions()` dans le `setUp()` de `tests/TestCase.php`.

**À surveiller malgré tout.** Un défaut intermittent ne se déclare pas mort sur
une passe verte : la suite était déjà passée deux fois entre deux occurrences.
Si le symptôme réapparaît, le mécanisme décrit ci-dessus n'est pas le bon — ou
pas le seul.


---

## Les courriels : plus aucun en attente

*2 septembre 2026, fin de journée.* **24 déclarés, 24 câblés.** La section
ci-dessus est conservée pour l'historique des décisions ; elle n'a plus d'objet
opérationnel.

Ce qui reste du module utilisateurs, en revanche, n'a pas bougé :
`Staff\Utilisateurs` crée un compte, assigne un rôle, liste, suspend et rétablit.
Il ne supprime pas et n'exporte pas — la suppression est un geste RGPD servi côté
citoyen par `SuppressionCompteService`, en deux temps et avec rétractation.


---

## L'espace mentor — livré le 2 septembre 2026

Quatre écrans (`staff.my-students`, `staff.progression`,
`staff.mentor-analytics`, `staff.my-content`), plus le cadrage de la liste des
cours sur le mentor. `student.view` et `student.progress`, déclarées depuis
l'origine et employées nulle part, gardent enfin quelque chose.

### Deux faux manques, corrigés

L'inventaire précédent annonçait six écrans mentor absents et « Statistiques
Globales » sans route. **Deux des six existaient** (« Mes Cours » = `staff.courses`,
« Sections & Leçons » = `staff.course-builder`), et **Statistiques Globales est
`staff.exam-stats`** depuis longtemps — le composant porte littéralement ce nom.

Comparer des noms de fichiers de prototype à des noms de routes produit des faux
manques. Le seul relevé fiable est celui des permissions orphelines : il part du
code.

### Les permissions orphelines — refermées le 2 septembre 2026

> **Sept sur neuf ont été refermées**, et un cliquet
> (`tests/Feature/PermissionsSansGardeTest.php`) empêche la liste de regrandir.
> Il exige aussi qu'une entrée refermée SORTE de la liste, et qu'une raison soit
> écrite pour chaque tolérée.
>
> Il en reste **deux**, `signalement.publish` et `signalement.delete`, qui
> attendent le même écran : la file des vérifications citoyennes côté personnel.
> Le tableau de bord en montre les dernières lignes ; publier une vérification
> en fact-check public ou en supprimer une sont des gestes qu'aucune page
> n'offre.
>
> Le relevé ci-dessous est celui d'avant, conservé pour l'historique.

### Le relevé d'origine

Cinq, toutes de la même nature — des **étiquettes mortes**, pas des
fonctionnalités absentes :

| Permission | Situation |
|---|---|
| `mentor.view`, `mentor.manage` | L'écran `staff.mentors` existe, mais se garde sur `user.view` / `user.update` |
| `signalement.view`, `.publish`, `.delete` | `SignalementsContenu` existe, mais se garde sur la Policy `ContentReport` |
| `system.settings`, `system.certificate_template` | Les écrans existent, gardés par la Policy `Setting` |

`MatricePermissions` promet donc un contrôle d'accès qui n'existe pas : retirer
`signalement.delete` à quelqu'un ne lui retire rien. **Deux issues, une seule à
choisir par cas** — soit l'écran adopte la permission, soit on la supprime.

Un cliquet ferait le travail, sur le modèle de celui des courriels : un test qui
échoue dès qu'une permission déclarée n'est employée nulle part. Il empêcherait
la liste de regrandir.


---

## Permissions : zéro orpheline — 3 septembre 2026

`signalement.publish` et `signalement.delete` ont trouvé leur écran : la file des
vérifications citoyennes (`staff.verifications`). **Les 127 permissions déclarées
gardent toutes quelque chose.**

`tests/Feature/PermissionsSansGardeTest.php` maintient l'état : une permission
déclarée sans garde fait échouer la suite, et la tolérer coûte une raison écrite.

### Ce que l'écran a corrigé au passage

- Le lien « Tout voir » du panneau de fact-checking pointait vers la **base de
  connaissances**, faute de destination — deux objets différents.
- `verifications` n'avait pas de suppression douce. Elle en a une : une
  vérification appartient à quelqu'un et lui a coûté une unité.


---

## L'œil du personnel — refermé le 3 septembre 2026

~~`staff-login.blade.php` n'a pas reçu le composant : il emploie `.ffield`.~~

**La contrainte n'existait pas.** `.ffield` est `position: relative`, et la règle
vient de `citizen.css` que cette mise en page charge déjà. Le composant a reçu
une variante `staff` ; seule la classe du conteneur change.

Au passage : `components/layouts/staff-auth.blade.php` ne chargeait pas
`auth-forms.js`. Le bouton aurait été du balisage mort.


---

## La barre latérale — refermée le 3 septembre 2026

~~Aucune garde de permission sur la quarantaine de liens ; chaque rôle voit le
menu entier.~~

Le menu est déclaré (`app/Support/Rbac/MenuDuBackOffice.php`) et **sa visibilité
est lue sur le middleware `can:` de chaque route**, jamais écrite à côté. Il ne
peut donc pas diverger de ce que le serveur autorise.

Admin 53 entrées, Mentor 16, Modérateur 11. Dix tests, dont un qui charge une
page réelle — c'est celui-là qui compte, un gabarit oublieux passerait les autres.

Trouvé au passage : deux routes déclarées deux fois sous des libellés différents,
et `staff.unit-journal` sans aucune garde de route (sa garde vivait dans le
composant, invisible du menu). Devenue la Gate `journal-des-unites`.
