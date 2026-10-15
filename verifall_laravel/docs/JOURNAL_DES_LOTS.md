# Journal des lots — chantier back-office

Le chantier ouvert le 31 août 2026 compte **11 lots**. Ce fichier en tient le
registre : la mesure de départ, les arbitrages pris, et ce que chaque lot a
réellement changé.

Il ne remplace ni `RESTE_A_FAIRE.md` (ce qui reste, chiffré) ni
`CONFORMITE_PROTOTYPE.md` (fidélité écran par écran). Il répond à une autre
question, celle qu'on se pose trois mois plus tard : **pourquoi cette
décision-là ?**

---

## Mesure de départ — 31 août 2026

Relevée **avant** la première ligne écrite. C'est à ces trois chiffres que se
compare tout ce qui suit.

| Barre | État au départ | Commande |
|---|---|---|
| Style | **passe** — 0 fichier à reprendre | `./vendor/bin/pint --test` |
| Analyse statique | **inexistante** — voir ci-dessous | ~~`./vendor/bin/phpstan`~~ |
| Tests | **1 380 passés, 1 ignoré** | `php artisan test --parallel` |

Le relevé complet de fin de lot 0 est de **1 386 passés, 1 ignoré, 3 945
assertions** ; les 6 tests d'écart sont ceux de la commande livrée ci-dessous.
La suite n'a donc, au départ, **aucun test rouge** : tout ce que les lots
suivants casseront sera visible.

### PHPStan n'a jamais tourné

`CLAUDE.md` prescrivait `./vendor/bin/phpstan analyse --level=6` depuis
l'origine. Vérification faite au lot 0 :

- `vendor/bin/phpstan` **n'existe pas** ;
- il n'y a **ni `phpstan.neon` ni `phpstan.dist.neon`** ;
- `composer.json` ne mentionne **pas** le paquet ;
- seul `phpstan/phpdoc-parser` est présent, tiré comme dépendance transitive.

La barre d'analyse statique **n'a donc jamais été franchie une seule fois**, et
la commande, recopiée de bonne foi de document en document, laissait croire le
contraire. C'est le genre d'écart qui ne se voit pas : elle ne cassait rien,
elle ne rapportait rien non plus.

L'installer (`composer require --dev larastan/larastan`) suppose d'absorber une
première passe sur 67 modèles et 55 composants Livewire. **À décider
explicitement**, pas à glisser dans un lot de correctifs. En attendant, la
section « Qualité » de `CLAUDE.md` décrit ce qui tourne réellement.

### Durée de la suite — 11 min, et c'est un problème en soi

**677 s sur 4 processus** (`--parallel`), et **plus de 20 minutes en série** —
l'exécution simple a dû être interrompue sans avoir rendu la main.

Ce n'est pas un détail de confort. `CLAUDE.md` fait de `php artisan test` une
condition de livraison ; une barre qu'on attend onze minutes est une barre qu'on
finit par sauter, et à onze lots la question se posera à chaque fois.

**À faire dès le lot 1** : lancer `php artisan test --parallel` par défaut, et
ne réserver la forme simple qu'au débogage d'un test isolé. `paratest` est déjà
présent dans `vendor/bin` — rien à installer.

---

## Lot 0 — Décisions d'architecture et remise en marche

**Livré le 31 août 2026.** Aucun écran, aucune migration : ce lot existe pour
que les onze suivants n'aient plus à se poser les mêmes questions.

### Arbitrage 1 — « Jamais d'Eloquent hors dépôt » devient conditionnel

**Constat.** La règle 2 était absolue et n'a jamais été appliquée : **12 dépôts
pour 67 modèles**, et les deux écrans les plus récents (`Staff\Opportunites`,
`Staff\Parametres`) requêtent directement leurs modèles. Un interdit que le code
viole partout n'informe personne.

**Enjeu.** Il reste 31 écrans d'administration. Les écrire dans le style actuel
figerait l'écart ; exiger 31 dépôts doublerait le coût de chaque CRUD de
référentiel à dix lignes.

**Décision.** Dépôt **obligatoire** dès qu'un critère est vrai — lecture
partagée web/API, lecture cachée, volume non borné, composition de plusieurs
agrégats. Eloquent direct **toléré** pour un référentiel à faible volume lu
depuis un seul écran. Critères complets dans `CLAUDE.md`, section « Quand
écrire un dépôt ».

### Arbitrage 2 — Un patron d'écran d'administration, et un seul

`app/Livewire/Staff/Opportunites.php` est promu **gabarit de référence** : un
nouvel écran staff s'en clone. C'est ce qui garde 31 écrans cohérents plutôt
que juxtaposés.

Deux constats en découlent, et ils allègent tout le chantier :

- les **neuf composants partagés** existent déjà (`<x-data-table>`,
  `<x-offcanvas-form>`, `<x-delete-modal>`, `<x-staff.menu-actions>`,
  `<x-kpi-grid>`, `<x-kpi>`, `<x-empty-state>`, `<x-badge-statut>`,
  `<x-skeleton>`) — vérifié fichier par fichier ;
- `MatricePermissions` **déclare déjà** les permissions de la quasi-totalité des
  écrans restants, et elles sont semées. Un écran écrit sa Policy et sa garde de
  route ; il n'invente pas de permission.

### Arbitrage 3 — `queue:work` est une dépendance de fonctionnement

**Constat.** `QUEUE_CONNECTION=database`. Le relevé des appels à la file a été
fait au lot 0, et il est **beaucoup plus large que le symptôme signalé** :

- **`AnalyserVerification::dispatch()`** — appelé depuis **6 endroits**
  (`Citizen\Verifier`, `Public\VerifierInvite`, l'API v1, l'orchestrateur pour
  ses reprises, `TransmissionAuLabo`). Sans worker, **aucune vérification n'est
  jamais analysée** : c'est le cœur du produit qui ne tourne pas ;
- les codes OTP — inscription, réinitialisation de mot de passe, suppression de
  compte, changement d'adresse (`Mail::…->queue()`, 6 appels) ;
- `PreparerExportDonnees` (portabilité RGPD) et `EngendrerLaCarteDeDementi` ;
- confirmation d'infolettre, accusé de message de contact.

**Rien ne casse** : pas d'exception, pas de journal d'erreur, page 200, suite
verte. Le job attend simplement en table `jobs`.

C'est l'explication du symptôme signalé — « l'inscription s'arrête à la
deuxième étape » : l'écran affiche « saisissez le code reçu » et la personne
attend un courriel qui ne partira jamais. Le formulaire n'a aucun défaut.

**À ne pas confondre avec le planificateur**, mécanisme distinct : les 6 tâches
de `routes/console.php` (dotation mensuelle, purge RGPD, réconciliation des
paiements, fermeture des opportunités…) exigent une entrée cron appelant
`schedule:run`, et non un worker. Ma première rédaction les avait rangées avec
la file — c'est faux, et la commande de diagnostic les rapporte séparément.

**Décision.** `queue:work` documenté comme **obligatoire** et non facultatif, et
une commande pour ne plus avoir à le deviner.

### Livré

| Élément | Chemin |
|---|---|
| Commande de diagnostic | `app/Console/Commands/Diagnostic.php` |
| Ses tests | `tests/Feature/DiagnosticTest.php` — 6 tests, 13 assertions |
| Arbitrages 1 et 2 | `CLAUDE.md` — deux sections neuves |
| Arbitrage 3 | `CLAUDE.md` — encadré du flux d'exécution |
| Correction du titre | `CLAUDE.md` — annonçait « Laravel 11 » |
| Ce registre | `docs/JOURNAL_DES_LOTS.md` |

**`php artisan verifall:diagnostic`** rapporte l'état de la file (jobs en
attente, âge du plus ancien, jobs échoués), du transport de courriel, du cache,
de la session et du planificateur. Elle ne modifie rien — utilisable en
production sans y réfléchir — et **sort en échec** dès qu'un point est rouge,
donc utilisable comme contrôle avant livraison.

Elle ne peut pas *voir* un worker : rien en base n'enregistre qu'un processus
écoute. Elle en juge par la conséquence — un job disponible depuis plus d'une
minute et toujours pas pris — et le dit comme un indice, non comme un fait.

### Deux dettes ouvertes, nommées et non traitées

1. **`CLAUDE.md` de la racine `C:\Projects\verifall\` est périmé** : il annonce
   Laravel 11, un prototype dans `web/`, `npm ci` et Redis — tout cela faux.
   Celui de `verifall_laravel/` fait foi. À retirer ou à réduire à un renvoi.
2. **PHPStan** — voir la mesure de départ ci-dessus.

---

## Lot 1 — Les parcours cassés

**Livré le 31 août 2026.** Quatre défauts qui arrêtaient quelqu'un en cours de
route. Un écran manquant se contourne ; un parcours cassé ne se contourne pas.

Un fil commun les relie, et il vaut d'être nommé parce qu'il resservira :
**dans les quatre cas, le serveur savait exactement ce qui n'allait pas et ne
le disait pas.** Un 403 nu, un bouton de repli, un écran muet, un témoin absent.
Aucun n'était un bug de logique — la logique était juste, c'est la réponse qui
manquait.

### B-04 — l'inscription « s'arrête à la deuxième étape »

Le formulaire est **intact** : quatre étapes, chaque bouton câblé, validation en
place. `InscriptionCitoyen::envoyerLeCode()` met le courriel à la file ; sans
`queue:work`, le code n'arrive jamais et l'écran attend indéfiniment.

Trois gestes, aucun ne touche à la sécurité :

- l'étape 3 annonce le délai et les 10 minutes de validité — devant six cases
  vides, on ne savait pas s'il fallait patienter, renvoyer ou tout reprendre ;
- « Renvoyer le code » a un état de chargement, et **cesse d'être muet** quand
  la session a expiré (il sortait sans un mot ; on recliquait jusqu'à épuiser la
  limite de débit, qui refusait alors les renvois légitimes) ;
- `verifall:code-otp <email>` émet un code en local.

> **Écart avec le plan, assumé.** Le plan prévoyait « un affichage du code en
> clair quand `APP_ENV=local` ». Écarté : un gabarit qui rend un secret sous
> condition d'environnement est à une variable mal réglée de le rendre partout,
> **sans que rien ne casse pour le signaler**. L'écran affiche donc la
> *commande*, jamais le code. C'est aussi le précédent de `verifall:code-2fa`.

### B-05 — « Passer le quiz » renvoyait une page 403 nue

La règle est juste et **reste en place** : un quiz vérifie ce que le cours a
enseigné. C'est la réponse qui était fautive — `prochaineLecon()` rendait la
leçon bloquante au moment même où le code abandonnait. L'information existait,
on la jetait.

L'écran explique désormais, nomme la leçon et offre le lien. **Pas de
redirection** : les toasts sont des événements de navigateur, ils ne survivent
pas à un changement de page — on aurait atterri sur une leçon quelconque sans un
mot, un second mystère à la place du premier.

Second défaut trouvé en chemin : `<x-evaluation.action-quiz>` ne consultait que
« inscrit », donc offrait le bouton à qui n'avait pas fini le cours. Les deux
appelants de `cours.blade.php` tenaient déjà ce compte ; celui-là non.

### B-06 — « Voir le replay » ramenait à la liste des tutoriels

Le réflexe est de corriger le `href`. **Il n'y avait rien à corriger** :
`live_classes` ne portait aucune colonne d'enregistrement. Le lien de repli
était honnête faute de quoi que ce soit à ouvrir ; le défaut vivait dans le
schéma.

Trois colonnes, et le rôle distinct de chacune est documenté dans la migration.
`replay_published_at` mérite un mot : une séance s'enregistre le mardi et se
publie le jeudi, une fois relue — sans cette date, déposer l'adresse publierait
aussitôt.

Le point le moins spectaculaire est le plus important : **une séance sans
rediffusion le dit**, au lieu d'offrir un bouton qui déçoit. C'est le cas de la
majorité des séances.

**Arbitrage.** La rediffusion est ouverte à **tout inscrit au cours**, pas aux
seuls inscrits à la séance. Le contraire punirait la bonne raison de ne pas être
venu, et fermerait la séance à qui découvre le cours trois semaines plus tard.

### B-03 — rien n'indiquait qu'une analyse tournait

Le suivi existait et était honnête, mais **seulement sur son écran**. Une
analyse prend des dizaines de secondes et se paie en unités : partir lire le
forum ne laissait plus aucun signe.

Le témoin est monté par le gabarit citoyen, donc présent sur les vingt-cinq
écrans. D'où la précaution qui fait tout l'intérêt de l'implémentation : **le
drapeau de session passe avant la requête**. Sans analyse en cours, le composant
ne rend rien et n'interroge rien — un test le prouve en comptant les requêtes.
Le drapeau n'est pas la vérité pour autant : c'est la base qui tranche, et le
drapeau qu'on efface quand elle dit que tout est fini.

Rythme : 10 s, contre 3 s sur l'écran dédié. Là-bas on regarde la progression ;
ici on veut seulement savoir que ça tourne encore.

### Livré

| Élément | Chemin |
|---|---|
| Commande de déblocage OTP | `app/Console/Commands/CodeDeVerification.php` |
| Témoin d'analyse | `app/Livewire/Citizen/AnalyseEnCours.php` + sa vue |
| Écran de rediffusion | `app/Livewire/Citizen/Rediffusion.php` + sa vue |
| Migration | `2026_08_31_100000_rediffusion_des_live_classes.php` |
| Route | `citizen.live-class.replay` |
| Modèle | `LiveClass::rediffusionDisponible()`, `::rediffusionVisiblePar()` |
| Écrans repris | `PasserQuiz`, `Evaluations`, `Register`, `Verifier`, layout citoyen, cartes quiz et live-class |
| Traductions | 25 clés, `fr` et `en` à parité |
| Tests | 29, dans 4 fichiers neufs |

### La suite a rattrapé une clé oubliée — et c'est la bonne nouvelle

Première passe complète : **1 échec sur 1 415**. `CouvertureTraductionTest`
signalait `composants.quiz_terminez_les_lecons`, appelée par
`action-quiz.blade.php` et absente de `lang/fr`.

Le défaut est banal, sa détection ne l'est pas. La parité `fr`/`en` avait été
vérifiée fichier par fichier après chaque ajout — et elle était bonne : la clé
manquait **des deux côtés**, une comparaison des deux langues ne pouvait donc
rien voir. Seul un test qui part des VUES et remonte vers les fichiers de langue
pouvait l'attraper. Sans lui, l'écran aurait affiché `composants.quiz_terminez_les_lecons`
en clair à qui n'a pas terminé ses leçons.

À retenir pour les lots suivants : après toute chaîne ajoutée dans une vue,
`php artisan test --filter=CouvertureTraduction` avant la suite complète — c'est
quelques secondes contre vingt minutes. Consigne portée dans `CLAUDE.md`.

### Vérification de fin de lot

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 415 passés, 1 ignoré, 0 échec** (3 998 assertions, 618 s) |
| `./vendor/bin/pint --test` | passe |
| Migration | appliquée |
| Gabarits touchés | les 6 compilent |
| `fr` ↔ `en` | à parité sur les 4 fichiers touchés |

Le compte tombe juste : **1 380** au départ, **+6** au lot 0, **+29** au lot 1,
soit 1 415. Aucun test préexistant n'a été retiré ni neutralisé pour faire
passer ce lot.

### Ce qui n'a délibérément PAS bougé

`SeanceResource` (API v1) est une liste blanche explicite : les trois colonnes
neuves n'y sont pas, et **ne fuient donc pas** vers l'application mobile. Le
défaut ferme, plutôt qu'ouvert — vérifié, pas supposé. Exposer la rediffusion à
l'API demandera d'y porter aussi la règle d'accès (`rediffusionVisiblePar`), ce
qui est un geste à part et non un oubli de ce lot.

### Une dette ouverte

`<x-toast>` **ne survit pas à une redirection** : il n'écoute qu'un événement de
navigateur, sans reprise depuis un `session()->flash()`. Deux écrans de ce lot
ont dû se contourner à cause de cela. Ajouter cette reprise profiterait à toute
redirection de l'application — mais le composant est monté par les trois
gabarits, la modification n'est donc pas anodine et n'avait pas sa place dans un
lot de correctifs.

---

## Lot 2 — La navigation du back-office

**Livré le 31 août 2026.** Cinq défauts, tous dans `layouts/staff.blade.php`.
Aucun n'était visible en test : **un lien qui mène au mauvais endroit répond
200 lui aussi**. On les a trouvés en lisant le gabarit, pas en l'exécutant.

Le menu latéral compte 46 entrées. Avant d'en ajouter 31 autres au fil des lots
suivants, il fallait qu'il cesse de mentir.

### Deux entrées du back-office menaient à l'espace citoyen

`Live Classes` → `citizen.live-classes`, et `Répertoire des outils` →
`citizen.tools`. L'administrateur basculait du guard `staff` au guard `web` :
sans session citoyenne ouverte — le cas ordinaire — il tombait sur l'écran de
connexion **citoyen**, depuis le back-office.

Seul le premier avait été signalé. **Le second a été trouvé en auditant le
gabarit** : c'est exactement le genre de lien qu'on n'essaie jamais soi-même.

Le correctif n'est pas un `href` : les deux écrans d'administration n'existaient
pas. Ils ont été écrits, sur le patron `Staff\Opportunites` promu au lot 0.

> **`Staff\LiveClasses` referme une dette du lot 1.** Les trois colonnes de
> rediffusion existaient et l'écran citoyen savait les lire, mais **rien ne
> permettait de les remplir** : la carte aurait annoncé « Rediffusion à venir »
> pour toujours. Une porte posée sans poignée. Le second bloc du formulaire est
> la poignée.

### Deux groupes en double

« SYSTÈME › Paramètres » doublait « CONFIG SYSTÈME › Réglages du Système » ;
« HUB D'OPPORTUNITÉS › Opportunités » doublait « HUB OPPORTUNITÉS › Jobs
Vérifiés ». Dans les deux cas c'est le groupe le plus **court** qui disparaît :
le survivant porte plusieurs entrées et donne son contexte à l'écran partagé.

« Portails Officiels » retiré, comme demandé — et cohérent avec une décision
antérieure : les portails ne sont plus affichés côté citoyen
(`Citizen\HubInfos` le documente).

### La cloche du back-office était un décor

Quatre notifications écrites en dur dans le gabarit et une pastille rouge
portant le chiffre **3**, littéralement tapé. Chaque membre de l'équipe, sur
chaque page, voyait « Dossier KYC-2214 soumis · il y a 2 h ».

Le même défaut avait été corrigé côté citoyen ; l'administration était restée
sur son décor de prototype. **Il est plus grave ici** : une pastille allumée en
permanence désapprend à la regarder. Le jour où douze signalements arrivent
réellement, plus personne ne distingue ce chiffre de celui d'hier — et sur un
back-office de modération, la cloche est un instrument de travail.

`Staff\Cloche` lit la table `notifications`. Elle est vide aujourd'hui, et
**elle le dit** : c'est un état correct, pas un manque. Les producteurs de
notifications pour le personnel arriveront avec les écrans qui les concernent.

### La règle, gardée pour toujours

Un test lit le gabarit et refuse toute occurrence de `route('citizen.` dans le
menu du back-office. Il lit le FICHIER et non une page rendue, pour attraper
aussi les entrées cachées derrière un `@can`.

Deux défauts de cette famille en un seul audit ; un troisième arriverait au
prochain écran ajouté vite fait. Autant l'empêcher.

### Un troisième doublon, constaté et NON traité

Le menu utilisateur de la barre du haut renvoie deux fois sur `staff.settings` :
« Mon profil » **et** « Paramètres ». C'est réel, et ce n'est pas de ce lot —
« Mon profil » pointe sur les réglages **faute d'écran de profil du personnel**.
C'est B-13, avec l'espace mentor. Le test de doublon ne compte donc que les
entrées `sidebar-link`, et son commentaire dit pourquoi.

### Livré

| Élément | Chemin |
|---|---|
| Écran Live Classes (avec rediffusion) | `app/Livewire/Staff/LiveClasses.php` + sa vue |
| Écran Répertoire des outils | `app/Livewire/Staff/Outils.php` + sa vue |
| Cloche du personnel | `app/Livewire/Staff/Cloche.php` + sa vue |
| Policies | `LiveClassPolicy`, `ToolPolicy` — auto-découvertes |
| Routes | `staff.live-classes`, `staff.tools` |
| Menu latéral | 2 groupes retirés, 2 liens repointés, 1 entrée supprimée, cloche remplacée |
| Tests | 22, dans 2 fichiers neufs |

**Aucune migration, aucune permission nouvelle** : `live_class.*` et `tool.*`
étaient déjà déclarées et semées. C'est la première fois que la découverte du
lot 0 sur la matrice RBAC paie comptant, et elle paiera à chaque lot suivant.

### Vérification de fin de lot

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 437 passés, 1 ignoré, 0 échec** (4 045 assertions, 703 s) |
| `--filter=CouvertureTraduction` | passe — lancé AVANT la suite, comme la consigne du lot 1 le prévoit |
| `./vendor/bin/pint --test` | passe |
| Migration | aucune |

1 415 + 22 = 1 437. Le compte tombe juste.

> **La consigne du lot 1 a servi dès le lot 2.** Les deux écrans neufs
> n'emploient aucune clé de langue — le back-office est en français en dur,
> comme le reste des écrans staff — mais le réflexe a coûté une seconde et
> aurait rattrapé le contraire.

### Ce qui manque encore à la Definition of Done

Le point 3 — **ouverture réelle au navigateur** de chaque écran touché — n'a
**pas** été fait pour les lots 1 et 2. Sept écrans sont concernés : quiz bloqué,
rediffusion citoyenne, témoin d'analyse, étape 3 de l'inscription, Live Classes
et Outils côté staff, cloche du personnel.

Ce n'est pas un détail de procédure. Les défauts du 25 août — favicon de 1 px,
état actif figé, cloche toujours allumée — **ont tous été trouvés en regardant,
aucun en exécutant**, et la suite était verte à chaque fois. Les tests de ces
deux lots prouvent que les données et les droits sont justes ; ils ne prouvent
pas qu'un tiroir s'ouvre ni qu'une classe Tailwind existe dans le build figé.

À faire avant de considérer les lots 1 et 2 clos.

---

## Lot 3 — Barème du parrainage réglable, et création de comptes

**Livré le 31 août 2026.** Deux chantiers qui n'ont l'air d'avoir rien en
commun, et qui partagent pourtant le même défaut de fond : **une décision qui
appartient au porteur du projet était enfermée dans le code.**

### Le barème passe de 100/50 à 10/5 — et devient réglable

`Parrainage::POINTS_PARRAIN` et `POINTS_FILLEUL`, deux constantes de classe,
modifiables par un développeur et par lui seul. Même arbitrage que la grille
tarifaire des packs d'unités, tranché de la même façon : ça va aux réglages.

> **Le piège de ce lot était d'écrire 10 et 5 à la place de 100 et 50.** Le
> barème serait resté aussi figé, et les trois tests existants auraient
> continué de passer sans rien éprouver. Ils lisaient la constante — donc ils
> auraient validé n'importe quelle valeur, y compris une valeur non lue.
>
> Les tests règlent maintenant **42 et 7** — des valeurs qu'aucun défaut ne
> produit par hasard — et vérifient les soldes. C'est le seul montage qui
> prouve que la lecture est branchée.

Les constantes **survivent**, rétrogradées en repli documenté : elles rendent le
service utilisable hors requête HTTP, nomment ce que le code attend, et
doublent le défaut du catalogue si quelqu'un vide la ligne en base. Un test
éprouve ce dernier cas — un réglage vidé ne doit pas créditer zéro.

**Les montants sont historisés** sur `referrals.referrer_points` et
`referred_points` : c'est ce qui rend le réglage sûr à modifier. Baisser le
barème demain ne réécrit pas le passé, et les totaux de « Mon parrainage » —
qui somment ces colonnes — continuent de dire la vérité.

Les quatre écrans qui **annoncent** le barème lisent désormais les mêmes
méthodes que le code qui le **verse**. Une page promettant 100 pendant qu'on
crédite 10 n'est pas un bug d'affichage : c'est une promesse rompue.

### On peut enfin créer un compte d'administration

Le trou le plus long du back-office. Les deux guards partagent la table
`users` : un administrateur est un `User` portant le rôle `Admin` — et **aucun
écran ne permettait d'en créer un**. Le seul chemin était `StaffSeeder`,
c'est-à-dire un développeur avec un accès console. Sur un produit de
modération, où l'équipe change, c'était un blocage permanent.

**Trois garde-fous**, et chacun couvre une façon de se verrouiller dehors
qui **ne lève aucune erreur** — la requête réussit, et on le découvre quand
plus personne ne peut entrer :

1. on ne modifie pas ses propres rôles (la case est désactivée dans la vue ; le
   test existe parce qu'un champ désactivé se réactive dans un navigateur) ;
2. le dernier administrateur ne perd pas son rôle ;
3. tout passe au journal, nominativement.

La **2FA n'est pas posée à la place de la personne** : le guard l'exige, et
`StaffLogin` conduit à l'enrôlement à la première connexion. La poser ici
supposerait de transmettre le secret par un canal qui annulerait son intérêt.
Un indicateur « Sans 2FA confirmée » évite qu'on prenne ça pour une panne.

**« Rôles & permissions » est en lecture seule**, et l'écran le dit. La matrice
est du code : elle se relit en revue et porte la raison de chaque arbitrage —
pourquoi un modérateur rédige un fait vérifié sans le publier. Une grille de
cases perdrait ces raisons et ferait diverger deux vérités, celle du dépôt et
celle de la base ; au prochain semis, l'une écraserait l'autre.

Hors périmètre, et dit franchement : ni suspension (elle demande une colonne qui
n'existe pas), ni suppression (geste RGPD déjà servi en deux temps avec
rétractation — en faire un bouton créerait un second chemin, immédiat et
irréversible). Le module utilisateurs complet reste au **lot 7**.

### Une régression introduite par ce lot, et rattrapée par la suite

Le passage de la constante au réglage a **mis la page d'accueil à la merci de
la base**. Avant : zéro requête, la valeur venait d'une constante. Après : un
appel aux réglages sur la page la plus visitée du site — et le premier passage
complet a rendu `ExampleTest` en 500 sur « no such table: settings ».

Le test ne migrait pas la base, mais c'est le symptôme, pas le problème : la
même chose se produirait en production **pendant une migration ou sur un
déploiement neuf**. Un chiffre promotionnel ne doit pas pouvoir faire tomber
l'accueil.

`unitesParrain()` et `unitesFilleul()` ne lèvent donc plus : elles retombent sur
la constante. C'est la conduite que `Reglages::dechiffrer()` tenait déjà pour
les secrets — « laisser filer l'exception mettrait le site entier à terre pour
une clé d'API ». Le rôle de repli était **annoncé** par les constantes depuis le
début de ce lot ; encore fallait-il que le code le tienne.

Un test le garde, en supprimant la table `settings` et en demandant l'accueil.

### Un défaut trouvé par un test qui échouait

Le contrôle `email:rfc,dns` s'appliquait aussi à l'adresse **pré-remplie** d'un
compte existant. Un compte dont le domaine a cessé de résoudre — organisation
renommée, domaine expiré — devenait **totalement inéditable** : on ne pouvait
même plus lui retirer ses rôles, ce qui est précisément le geste qu'on veut
faire dans ce cas.

Le contrôle DNS ne s'applique donc plus qu'à une adresse **qui change**. En
production, le défaut aurait mis des mois à se manifester et se serait présenté
comme « le formulaire refuse mon enregistrement sans rien dire d'utile ».

### Livré

| Élément | Chemin |
|---|---|
| Barème réglable | `CatalogueDesReglages` (2 clés) · `Parrainage::unitesParrain/Filleul()` |
| Écran Utilisateurs | `app/Livewire/Staff/Utilisateurs.php` + sa vue |
| Écran Rôles | `app/Livewire/Staff/Roles.php` + sa vue |
| Policies | `UserPolicy` (auto-découverte), `RolePolicy` (**enregistrée à la main**) |
| Enum | `UserRole::description()` — source unique pour les deux écrans |
| Routes | `staff.users`, `staff.roles` |
| Menu latéral | 3 entrées câblées, 2 sorties de « à venir » |
| Tests | 16, dans 2 fichiers neufs · 3 tests existants réoutillés |

> **`RolePolicy` ne pouvait pas être auto-découverte** : `Role` vient de
> `Spatie\Permission\Models\`, la convention `App\Models\X → App\Policies\XPolicy`
> ne s'applique pas. Sans l'enregistrement explicite dans `AppServiceProvider`,
> `can:viewAny,Role` serait tombé sur le Gate par défaut — qui REFUSE. On aurait
> cherché une permission manquante alors qu'elle était bien accordée.

**Aucune migration.** Les permissions `user.*` et `role.*` étaient déjà semées.

### Vérification de fin de lot

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 454 passés, 1 ignoré, 0 échec** (4 084 assertions, 552 s) |
| `--filter=CouvertureTraduction` | passe |
| `./vendor/bin/pint --test` | passe |
| Migration | aucune |

1 437 + 17 = 1 454. Le compte tombe juste.

**Trois lots, trois passages complets, trois défauts rattrapés par la suite** —
une clé de langue manquante (lot 1), rien (lot 2), une régression sur la page
d'accueil (lot 3). Aucun n'aurait été vu autrement : tous rendaient une page
qui répond, ou une suite qui reste verte ailleurs.

---

## Lot 4 — Le certificat premium, et son gabarit administrable

**Livré le 31 août 2026.** Le document était une carte simple : bordure verte,
nom, cours, date, code. Aucun décor, pas de QR, pas de signature.

### Le groupe de réglages « Certificats » n'était lu par personne

C'était l'autre moitié de l'écart **É-02** relevé au lot 0. Cinq clés
enregistrables, zéro lecteur : on réglait une position, l'écran confirmait,
rien ne changeait jamais.

**Trois d'entre elles étaient inapplicables**, et pas seulement débranchées.
`cert_name_coords`, `cert_date_coords`, `cert_qr_coords` décrivaient des
positions en points — « x:100,y:200 » — sur un fond A4. C'est le modèle d'une
composition **PDF** : on pose un gabarit, on écrit dessus à des coordonnées
absolues.

Ce modèle n'existe pas et n'est pas prévu. Aucune bibliothèque PDF n'est
installée — c'est d'ailleurs pourquoi `certificates/{code}/pdf` n'est pas servi
par l'API. Le certificat est une **page HTML** que le navigateur imprime : elle
se recompose selon la largeur, et des coordonnées en points n'y veulent rien
dire.

Les trois clés sont donc **retirées**, pas rebranchées. Les laisser aurait été
pire que de ne rien faire. Elles reviendront le jour où une composition PDF est
décidée, avec la bibliothèque qui les exécute.

À leur place, ce qui s'applique réellement à un document HTML imprimable :
couleur du décor, signature numérisée, nom et qualité du signataire, filigrane,
mention de bas de page. **Six réglages, six lecteurs.**

### Deux contraintes dures, et ce sont elles qui ont dicté le code

**Le build Tailwind est figé.** Écrire les vagues en `bg-gradient-to-br from-…`
n'aurait produit RIEN — sans erreur, sans avertissement, juste un document
blanc. Le décor vit donc dans `public/assets/css/pages/user-certificat.css`,
qui n'appartient pas au build et est servi tel quel.

**Le document est fait pour être imprimé.** Les navigateurs retirent par défaut
fonds et dégradés à l'impression, pour épargner l'encre : sans
`print-color-adjust: exact`, un certificat à vagues sort **blanc**. Le défaut ne
se voit pas à l'écran — seulement une fois la feuille sortie. La règle est posée
sur chaque élément porteur de couleur, pas seulement sur `body` : elle ne
s'hérite pas de façon fiable entre moteurs.

Un `@supports not (background: color-mix…)` couvre Safari < 16.2 et les Android
anciens — une part réelle du public visé. Sans lui, la fonction inconnue
invalide la déclaration entière et la vague disparaît : le document sort correct
mais nu, et personne ne comprend pourquoi.

### Un seul document, deux écrans

`components/lms/certificat.blade.php` sert le titulaire ET le recruteur. Deux
gabarits séparés finiraient par montrer deux documents différents — et c'est
ici que l'écart coûterait le plus cher, puisque le recruteur compare sa page à
la feuille qu'on vient de lui remettre.

La page publique garde son **relevé de faits** et reçoit le document **en
dessous**. Les deux répondent à deux questions successives : « ce code
existe-t-il ? », puis « est-ce bien celui que je tiens ? ».

Le QR est engendré côté serveur en SVG — `bacon/bacon-qr-code` était déjà
installé pour le parrainage. Un QR dessiné en JavaScript **ne s'imprime pas**,
et un QR flou ne se scanne pas. Il mène à la page publique, jamais à l'espace
citoyen : le recruteur qui scanne n'a pas de compte.

### La couleur est revalidée côté service

Elle est interpolée dans un attribut `style`. Le formulaire la valide déjà, mais
**il n'est pas le seul chemin d'écriture** : un `Reglages::set()` depuis une
commande ou un seeder ne passe par aucune validation. Le service revalide contre
`^#[0-9A-Fa-f]{6}$` et retombe sur la teinte du produit. Un test lui envoie
`red; background:url(//pirate)`.

### L'écran d'administration n'a PAS de formulaire

Les six réglages se saisissent aux Paramètres, avec les soixante autres, et ils
y restent. Un second formulaire pour les mêmes clés, c'est deux endroits où
écrire la même valeur — donc un endroit où l'on oublie de la changer.

Ce qui manquait n'était pas la saisie mais **le rendu** : on réglait une
couleur, on déposait une signature, et l'on découvrait le résultat en délivrant
un vrai diplôme à une vraie personne.

L'exemple affiché n'est **jamais enregistré** : construit en mémoire, sans `id`,
avec un code `VERIF-EXEMPLE-0000` qui ne validera rien sur la page publique. Un
aperçu oublié en base finirait par ressembler à une fraude — deux tests le
gardent.

### Livré

| Élément | Chemin |
|---|---|
| Service de mise en page | `app/Core/Lms/Services/GabaritCertificat.php` |
| Document partagé | `resources/views/components/lms/certificat.blade.php` |
| Décor et règles d'impression | `public/assets/css/pages/user-certificat.css` |
| Écran d'aperçu | `app/Livewire/Staff/GabaritCertificat.php` + sa vue |
| Réglages | 3 retirés, 4 ajoutés, 2 conservés — **tous lus** |
| Route | `staff.certificate-template` |
| Tests | 14 neufs · 1 test de réglages réoutillé |

**Aucune migration**, aucune dépendance nouvelle.

### Le garde-fou XSS a refusé mon code, et il avait raison

Premier passage complet : **1 échec**, `ChecklistSecuriteTest` →
« Sorties non échappées et non purifiées : certificat.blade.php : `$qr` ».

Le test balaie les vues à la recherche de `{!! !!}` et n'accepte qu'une liste
d'exceptions **nommées**. Les QR en font partie — mais la règle reconnaît les
expressions contenant `qrCode`, parce que le reste du dépôt écrit
`{!! $parrainage->qrCode(…) !!}`. Ma prop s'appelait `$qr` : elle ne
correspondait pas.

Deux façons de résoudre, et une seule est correcte. **Élargir la règle** pour
qu'elle accepte `$qr` aurait affaibli un garde-fou réel pour ma commodité — et
le commentaire du test dit explicitement pourquoi l'exception est étroite :
« c'est la seule exception du dépôt, et elle est nommée plutôt que tolérée ».
La prop est donc **renommée** `$qrCode`, ce qui la range dans la convention
existante au lieu de la contourner.

C'est la troisième fois en quatre lots que la suite attrape quelque chose
qu'aucune relecture n'avait vu.

### Ce que ces tests NE prouvent pas

**Qu'une feuille sorte correctement d'une imprimante.** Les tests vérifient que
les règles CSS sont écrites ; ils ne peuvent pas vérifier qu'un moteur les
honore. `print-color-adjust` est justement la propriété dont le comportement
varie le plus d'un navigateur à l'autre.

L'épreuve papier prévue au plan **n'a pas été faite**. L'écran d'administration
porte un bouton « Épreuve d'impression » et un avertissement qui le dit. C'est,
avec la passe navigateur, le point 3 de la DoD qui reste ouvert.

### Vérification de fin de lot

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 468 passés, 1 ignoré, 0 échec** (4 110 assertions, 560 s) |
| `--filter=CouvertureTraduction` | passe |
| `./vendor/bin/pint --test` | passe |
| Migration | aucune |

1 454 + 14 = 1 468. Le compte tombe juste.

---

## Le point 3 de la DoD, quatre lots plus tard

**Onze écrans** ont été livrés ou repris sans qu'aucun n'ait été ouvert dans un
navigateur : quiz bloqué, rediffusion, témoin d'analyse, étape 3 de
l'inscription, Live Classes et Outils côté staff, cloche du personnel,
Utilisateurs, Rôles, certificat citoyen et public, gabarit de certificat.

L'écart grandit à chaque lot, et il porte sur ce que les tests ne savent pas
voir. Les défauts du 25 août — favicon de 1 px, état actif figé sur 107 pages,
cloche toujours allumée — **ont tous été trouvés en regardant**. La suite était
verte à chaque fois.

Deux risques précis pèsent sur les lots 1 à 4 :

- **une classe Tailwind absente du build figé** est silencieusement inerte. Les
  écrans staff clonent un gabarit existant, donc le risque y est faible ; les
  vues neuves du certificat et du témoin d'analyse ne sont pas dans ce cas ;
- **un tiroir, une modale ou un menu déroulant** qui ne s'ouvre pas. Aucun test
  Livewire ne l'attrape : ils vérifient l'état du composant, pas le JavaScript
  du gabarit.

À traiter en une passe unique plutôt que lot par lot.

---

## La passe navigateur — 31 août 2026

**Faite.** Onze écrans ouverts dans Chrome, sur une base peuplée par les
seeders de démonstration. Elle a trouvé **quatre défauts** dont aucun n'était
visible en test : la suite était verte et chaque page répondait 200.

### 1. Sept classes Tailwind du certificat n'existaient pas — le QR sortait double

Le défaut que `CLAUDE.md` annonce depuis le début, rencontré pour de vrai.
`w-[110px]`, `pb-28`, `sm:gap-16`, `sm:text-5xl`, `w-24`, `tracking-[.28em]`,
`tracking-[.18em]` : **absentes du build figé**, donc sans le moindre effet. Le
QR sortait à sa taille intrinsèque, deux fois trop grand, et poussait la
signature hors de son axe.

Un script de contrôle a ensuite balayé toutes les vues des lots 1 à 4 contre le
build. Beaucoup d'absences relevées sont **préexistantes** — le prototype
lui-même emploie des classes que son propre CSS ne définit pas, ce que
`BuildTailwindFigeTest` documente déjà. Celles du certificat, elles, étaient les
miennes.

**Correctif de principe, pas de rustine** : toute la métrique du document passe
en classes maison dans `user-certificat.css`. *La mise en page d'un écran neuf
ne doit pas dépendre d'un build qu'on ne régénère pas.*

### 2. Les vagues n'étaient pas des vagues

Les dégradés s'évanouissaient en `transparent` bien avant le bas de l'élément :
le `border-radius` ne découpait plus rien, et l'on voyait un rectangle en fondu.
Un fond plein sous les radiaux donne à la forme de quoi être découpée.

### 3. Le bandeau du bas avalait le code de vérification

À 190 px et 130 px, les vagues occupaient près de la moitié d'un document qui en
fait 700 — et le pied recouvrait **le code**, en texte sombre sur vert sombre.
Sur le seul élément qui donne sa valeur au diplôme. Hauteurs ramenées à 128 et
88 px, marges du contenu accordées.

### 4. « Voir le replay » offert à qui ne peut pas le voir

Le plus instructif des quatre, parce que c'est **le défaut du lot 1 reproduit
par le lot 1 lui-même**. La carte n'interrogeait que `rediffusionDisponible()`,
un fait sur la SÉANCE : elle offrait donc un bouton vert et engageant à
quelqu'un de non inscrit, qui tombait ensuite sur un refus. Exactement l'impasse
que ce lot s'était donné pour but de supprimer.

Trois états désormais — « Rediffusion à venir », « Réservée aux inscrits » (qui
mène au cours), « Voir le replay » — vérifiés à l'écran. Un test les garde.

### Ce que la passe a CONFIRMÉ

- les deux groupes en double du menu ont bien disparu, et « Répertoire des
  outils » est devenu un lien réel ;
- la cloche du personnel n'affiche **aucune pastille** — la fausse « 3 » est
  partie ;
- les tiroirs s'ouvrent (`<x-offcanvas-form>` sur Live Classes), le fieldset
  Rediffusion s'affiche et se remplit ;
- « Rôles » liste les quatre rôles avec leurs comptes réels (Administrateur :
  127 permissions, 1 compte) ; « Utilisateurs » liste 12 comptes ;
  « Répertoire des outils » en liste 15 ;
- le certificat rend correctement dans le shell citoyen **et** dans le shell
  staff, et aussi à 605 px de large (vérification mobile obtenue par accident,
  gardée).

### Ce qu'elle n'a toujours pas prouvé

**L'épreuve papier.** `print-color-adjust` ne se vérifie qu'en imprimant.

### Vérification après correctifs

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 469 passés, 1 ignoré, 0 échec** (4 113 assertions, 661 s) |
| `--filter=CouvertureTraduction` | passe |
| `./vendor/bin/pint --test` | passe |

Un test de plus qu'au lot 4 : celui qui garde le troisième état de la carte de
rediffusion.

### Comment refaire cette passe

```bash
php artisan migrate && php artisan db:seed && php artisan db:seed --class=DemoSeeder
php artisan serve --port=8199          # un port NEUF : le zoom de Chrome est
                                       # mémorisé par origine, et un zoom hérité
                                       # fausse toute lecture d'écran
php artisan verifall:code-2fa rene@verifall.com   # le second facteur du staff
```

Comptes : `adderly@verifall.com` (citoyen) · `rene@verifall.com` (admin), mot de
passe `DEMO_PASSWORD` du `.env`.

### Deux défauts préexistants, relevés sans être traités

- **Les libellés flottants chevauchent les valeurs auto-remplies** par le
  navigateur, sur l'écran de connexion staff. Antérieur à ces lots.
- La **visite guidée** se rouvre à chaque page tant que `tour_done_at` est nul,
  y compris après un « Passer » — à vérifier, hors périmètre ici.

---

## Reprise hors lots — 31 août 2026

Trois demandes arrivées après le lot 4, traitées ensemble.

### Les icônes de « Mes Tutoriels » passent en SVG

Nouveau composant `<x-icone>`. Les tracés restent ceux de Material Symbols —
le prototype est la référence visuelle — mais ils sont **dessinés dans la
page**.

Trois défauts condamnaient la police d'icônes, et le deuxième est celui qui
décide : elle pèse des centaines de kilo-octets pour six glyphes réellement
employés ; **tant qu'elle n'est pas arrivée, le navigateur affiche le NOM du
glyphe** — on lit « arrow_forward » à l'écran, et hors ligne c'est l'état
permanent alors que l'application a un service worker ; enfin elle ne
s'imprime pas toujours.

Trouvé en chemin : une **chaîne de langue portait du balisage**
(`resume_de_mon_apprentissage_expand_more` contenait le `<span>` de l'icône,
rendu par `{!! !!}`). Un fichier de langue porte de la copie, pas de la
structure — c'est ce que son propre en-tête annonce. Sorti de là, il n'y a plus
de sortie non échappée à cet endroit.

**Hors périmètre, et dit franchement** : le shell citoyen (en-tête, sidebar,
barre du bas) emploie encore la police. Il est partagé par les 25 écrans ; le
convertir est un chantier à part.

### Les guides pratiques s'ouvrent SUR PLACE

« Lire le guide » pointait sur `route('public.tutorials')` : depuis son espace,
le citoyen était éjecté vers le site vitrine — sans son en-tête, sans son solde,
sans sa cloche. **Et le guide n'y était même pas** : la page publique liste des
cours, pas ces quatre fiches. Le lien ne menait donc pas au mauvais endroit par
erreur — il ne menait à rien.

Les quatre guides et leurs quatre étapes sont désormais rendus par le composant
lui-même. `#[Url]` donne à chacun une adresse partageable
(`?guide=verifier-une-image`) et fait fonctionner le bouton Précédent **sans
recharger la page**.

> **Les slugs ne sont PAS dans les fichiers de langue**, et c'est le test de
> couverture qui l'a imposé — à juste titre, mais pour une raison plus profonde
> que la parité : traduits, `?guide=verifier-une-image` deviendrait
> `?guide=check-an-image`. Le même guide aurait deux adresses, et un lien
> partagé cesserait de fonctionner pour qui n'a pas la même langue. Le fichier
> de langue porte la copie ; le composant porte l'identité.

### Le certificat devient un SVG

Transposé de `certificat.xml`, fourni par le porteur du projet, et câblé aux
données réelles. Les **six niveaux de preuve** sont annotés dans le code :
identité, cours terminé, progression à 100 %, compétences, numéro unique, QR de
contrôle.

Le passage au SVG referme au passage le défaut du build figé : la version HTML
dépendait de sept classes Tailwind inexistantes. **Un SVG porte sa géométrie
dans son `viewBox`** — il ne peut pas subir cela. La feuille
`user-certificat.css` a fondu en conséquence : il n'y reste que l'affichage et
les règles d'impression (`A4 landscape`, `print-color-adjust`).

Trois écarts assumés avec le gabarit fourni :

- **`holder_name` / `course_title`** au lieu de `$certificate->user->name` :
  dénormalisés à la délivrance. Un cours renommé ne doit pas réécrire un diplôme
  déjà remis.
- **Logo local** au lieu de `verifall.com/landing/…` : un document dont
  l'en-tête dépend d'un domaine extérieur perd son logo quand ce domaine bouge,
  et ne s'imprime pas hors ligne.
- **Pas d'asset « icône seule »** dans le dépôt : le gabarit posait une icône
  carrée puis le mot « Vérif'All » à côté. Avec le logotype complet, les deux se
  chevauchaient. Le logotype porte le nom ; le texte redondant a sauté.

La grille QR factice du gabarit est remplacée par un **vrai QR**, engendré par
`bacon/bacon-qr-code` et posé en `data:` — le document reste autonome une fois
enregistré ou envoyé.

### Trois défauts visuels, tous trouvés à l'œil

Logo et texte superposés · baseline débordant du panneau courbe, en pâle sur
crème · libellés du badge posés sur l'anneau doré. Aucun n'était visible en
test.

### Vérification

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 478 passés, 1 ignoré, 0 échec** (4 135 assertions, 745 s) |
| `--filter=CouvertureTraduction` | passe |
| `./vendor/bin/pint --test` | passe |
| Passage navigateur | guides et certificat vérifiés à l'écran |

1 469 + 9 = 1 478 (8 tests de guides, et deux tests de certificat réécrits pour
le SVG contre un supprimé).

---

## Finalisation de l'inscription — 31 août 2026

Le lot 1 avait traité B-04 en croyant l'affaire close : le code OTP ne partait
pas faute de `queue:work`. **Le diagnostic était juste et insuffisant.** En
parcourant le formulaire au navigateur, on a trouvé la cause réelle du symptôme
signalé — et deux autres défauts derrière.

### 1. Les étapes 2, 3 et 4 étaient INVISIBLES

`verifall.css` pose `[data-step-panel] { display: none }` et ne montre que
`.is-active`. Le gabarit ne portait la classe **que sur l'étape 1**. Les trois
autres panneaux étaient rendus dans le DOM et invisibles à l'écran : on
remplissait l'identité, on cliquait « Continuer », le stepper avançait — et la
page devenait blanche.

C'est le symptôme signalé, mot pour mot : « je me suis arrêté à l'étape 2 ».

Le correctif de la file (lot 1) était juste mais **ne pouvait pas se
manifester** : personne n'atteignait jamais l'étape 3.

> **Aucun test ne pouvait l'attraper.** Le balisage EST présent et correct :
> `assertSee` le trouve, `assertOk` passe. C'est du CSS qui le cachait. C'est
> le cas le plus net, depuis le début du chantier, de ce que la suite ne sait
> pas voir.

### 2. Les six cases du code n'étaient jamais câblées

`saisie-code.js` écoutait `livewire:update` — **un évènement qui n'existe pas en
Livewire 3**. L'étape 3 n'apparaissant pas au chargement de la page mais après
un rendu Livewire, `activer()` tournait sur un DOM qui ne contenait pas encore
le groupe, et rien ne le rappelait ensuite.

À l'écran : les six chiffres s'entassaient dans la première case, le champ caché
restait vide, et « Valider le code » répondait « Saisissez le code reçu par
courriel » — sur un code pourtant correctement tapé.

Remplacé par `Livewire.hook('morphed')`, avec repli sur `livewire:initialized`.
Le script ne vole plus le focus quand on est déjà en train de saisir — sans
cette garde, chaque frappe ramènerait à la première case. `maxlength="1"` et
`autocomplete="one-time-code"` ajoutés en défense.

### 3. Un compte neuf arrivait avec ZÉRO unité

`default_user_units` (« Bonus de bienvenue », 5) n'était lu par personne :
**quatrième cas de l'écart É-02**, après le barème de parrainage, le certificat
et les coordonnées PDF.

On terminait les quatre étapes pour découvrir que la première action du produit
— vérifier une information — était fermée. C'est ce qui rendait l'inscription
« pas finie » même une fois franchie.

Crédité désormais par `EconomyService` sous un motif neuf,
`MotifMouvementUnite::Bienvenue`, distinct de la dotation mensuelle : celle-ci
revient chaque mois, celui-là ne s'accorde qu'une fois, et les confondre rendrait
le journal comptable incapable de répondre à « pourquoi deux fois ? ».

Le crédit ne peut pas faire échouer une inscription : un solde à zéro est
regrettable, un compte qu'on ne peut pas créer l'est davantage. L'échec est
journalisé — un compte sans bonus est un ticket de support.

### 4. Les consentements renvoyaient à la FAQ

Les **deux** cases pointaient sur `public.faq`. On ne peut pas consentir à un
document qu'on ne peut pas lire, et c'est précisément la case dont le RGPD exige
qu'elle soit éclairée. La confidentialité renvoie maintenant à sa vraie page.

### 5. L'invitation était consommée en silence

Le code déposé par `/invitation/{code}` n'apparaissait nulle part : quelqu'un
arrivant d'un lien WhatsApp voyait une inscription ordinaire. Le maillon le plus
fragile du parrainage — celui qui partage a promis quelque chose, et c'est lui
qui paraît avoir menti.

Le formulaire nomme désormais le parrain et annonce ce qui attend ; l'étape 4 le
redit, et mène à la première vérification plutôt qu'au seul tableau de bord.

### Vérification

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 492 passés, 1 ignoré, 0 échec** (4 162 assertions, 340 s) |
| Parcours réel au navigateur | invitation → 4 étapes → compte actif |
| En base | `units=5`, journal `welcome delta=5`, parrainage rattaché |
| `--filter=CouvertureTraduction` · Pint | passent |

1 478 + 14 = 1 492.

### UNE DÉCISION QUI N'EST PAS TECHNIQUE : les CGU n'existent pas

Le formulaire fait accepter des « conditions générales d'utilisation » et
enregistre le consentement `ConsentType::Cgu`. **Aucune page de CGU n'existe sur
le site** — ni route, ni vue, et les mentions légales n'en parlent pas.

Le lien pointe pour l'instant vers `public.legal`, le document le moins éloigné.
Ce n'est pas satisfaisant : on consent à un texte qu'on ne peut pas lire.

Deux issues, et le choix appartient au porteur du projet : fournir le texte des
CGU (une page à créer), ou retirer la case et le type de consentement. Aucune
clause juridique ne doit être inventée ici.

---

## Les CGU sont écrites — 31 août 2026

### La décision, et le fait qu'elle renverse la précédente

L'entrée ci-dessus concluait : « Aucune clause juridique ne doit être inventée
ici », et renvoyait le choix au porteur du projet. Celui-ci l'a rendu : *« à toi
de décider s'il faut écrire de vraies CGU »*.

**Décision : oui, elles sont écrites.** Le raisonnement qui a changé est celui-ci
— ne rien écrire n'est pas une position neutre. Le formulaire fait déjà cocher la
case et enregistre déjà `ConsentType::Cgu` ; tant que la page n'existe pas, la
plateforme fait consentir à un document introuvable, n'a **aucune règle
opposable** pour écarter un compte abusif, et **aucune limite de responsabilité**
sur ses verdicts. Un texte imparfait mais fondé sur le produit réel vaut mieux
que ce vide. L'autre issue — retirer la case — reviendrait à exploiter une
plateforme de fact-checking sans conditions d'usage.

### Relecture — **levée le 1er septembre 2026 par le porteur du projet**

La réserve d'origine était : *« ce texte n'a pas été relu par un juriste ; une
relecture juridique est requise avant une mise en production réelle »*, avec
trois points signalés — la qualification des « unités » (§5), la limitation de
responsabilité (§13) et le droit applicable (§16).

**Le porteur du projet a lu et validé le texte le 1er septembre 2026.** La
réserve est donc levée, et cette entrée en garde la trace : la validation est
celle du responsable de la plateforme, non celle d'un conseil juridique
extérieur. Si un avis d'avocat est produit plus tard, c'est ici qu'il se
consigne.

### Les trois garde-fous que je me suis imposés

1. **Ne rien écrire que le produit ne fasse.** Chaque clause décrit un
   comportement vérifiable dans le code : les quatre verdicts réels, le code OTP
   d'activation, la révocation d'un certificat, le délai de rétractation.
2. **Position conservatrice là où il fallait deviner** — c'est-à-dire favorable
   au membre : recréditement en cas d'échec technique, modération motivée et
   contestable, avertissement préalable à la fermeture, et §13 alinéa 2 qui
   refuse expressément de limiter la responsabilité en cas de faute lourde.
3. **Aucun trou publié.** L'éditeur passe par `<x-mention-legale>`, qui affiche
   « Non communiqué » au visiteur et signale le réglage manquant au personnel.

### Ce qui est branché sur le produit plutôt que recopié

| Valeur dans le texte | Source réelle |
|---|---|
| Âge minimum (§4) | réglage `legal_minimum_age`, nouveau, défaut `15` |
| Délai de rétractation (§11) | `SuppressionCompteService::JOURS_RETRACTATION` |
| Identité de l'éditeur | réglage `legal_entity`, via `<x-mention-legale>` |
| Date d'entrée en vigueur | **constante** du contrôleur, pas `now()` |

Ce dernier point est un défaut existant qu'on n'a pas reproduit :
`mentions-legales` affiche `now()` en « dernière mise à jour », donc prétend
avoir été révisée chaque jour. Sur un document contractuel, c'est une fausse
information — et c'est justement la date qu'on invoque pour savoir quelle
version on a acceptée.

### Deux défauts trouvés, tous deux les miens

- **Le test de la date se contredisait** : il exigeait à la fois « pas la date
  du jour » et « le 31 août 2026 », alors que le texte entrait en vigueur *ce
  jour-là*. L'assertion utile n'était pas celle-là : le test avance désormais de
  trois mois et vérifie que la date **ne bouge pas** — c'est là que `now()` se
  trahirait.
- **La page publiait `:entite` en clair.** Le contrôleur laissait passer ce jeton
  à dessein, au motif que `<x-mention-legale>` nomme déjà l'éditeur en tête. Le
  raisonnement tenait, la conséquence non. Le jeton est retiré du texte, et le
  test des espaces réservés — qui ne cherchait que la forme `[Mot]` du prototype
  — cherche maintenant aussi les jetons `:orphelins`. **Il n'a rien vu passer :
  c'est le rendu au navigateur qui l'a attrapé.**

### Vérification

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 501 passés, 1 ignoré, 0 échec** (4 199 assertions, 762 s) |
| Rendu au navigateur | 16 sections, sommaire, 0 jeton, 0 espace réservé |
| `--filter=CouvertureTraduction` · Pint | passent |

---

## Le plan des lots 5 à 10 — reconstitué le 31 août 2026

**Ce plan n'existait nulle part sur le disque.** Il vivait dans une
conversation, et l'en-tête de ce journal annonçait « 11 lots » sans dire
lesquels. Une seule ligne y faisait référence (« le module utilisateurs complet
reste au lot 7 »), et elle était devenue invérifiable.

Le voici, reconstitué non pas de mémoire mais **depuis le menu latéral** — les
entrées portant encore la mention « à venir » au 31 août 2026, groupées comme le
back-office les groupe :

| Lot | Périmètre | Écrans |
|---|---|---|
| **5** | Fact-checking — référentiel des sources, journal des unités | 3 |
| 6 | Académie — catégories, cours, constructeur, banque de questions, certificats, unités premium | 6 |
| 7 | Examens, enrôlement, utilisateurs — dont mentors et KYC | 7 |
| 8 | Hub, communauté, blog | 5 |
| 9 | Ressources et communication — partenaires, témoignages, FAQ, gabarits d'e-mails, campagnes, badges | 6 |
| 10 | Config, sécurité, maintenance — journaux, sauvegardes, cron — **plus la refonte des réglages en onglets latéraux et l'espace du personnel** | 4 + 2 chantiers |

---

## Lot 5 — Le référentiel des sources, et le journal des unités

**Livré le 31 août 2026.** Trois écrans, deux tables de plus, et un branchement
qui distingue ce lot d'un CRUD.

### Le défaut trouvé en chemin, et il était dans le MOTEUR

`AnalyseTechniqueAdapter` pénalise un domaine qui « évoque une institution sans
en être une ». La règle cherchait `gouv` ou `gov` **n'importe où** dans le
domaine, et n'exemptait qu'un seul cas : `gouv.bj`.

Elle frappait donc exactement les sources qu'un vérificateur consulte en
premier :

| Domaine | Ce que le moteur en faisait |
|---|---|
| `cdc.gov` | +35 de suspicion, usurpation présumée |
| `usa.gov` | idem |
| `sante.gouv.fr` | idem |
| `www.gov.uk` | idem |

Trente-cinq points infligés à l'institution elle-même. La règle n'était pas mal
réglée, elle était **mal posée** : `gouv` dans `actu-gouv-benin.info` est un
signal, `gouv` dans `sante.gouv.fr` en est l'inverse. Ce qui les sépare n'est
pas la présence du mot mais sa POSITION — un suffixe institutionnel s'obtient
auprès d'un registre qui vérifie la qualité du demandeur ; il ne s'invente pas
dans un nom de domaine acheté douze euros.

Deux tests tiennent le correctif dans les deux sens : les institutions passent,
`actu-gouv-benin.info` reste soupçonné. Sans le second, on aurait « corrigé » en
désarmant la règle.

### Pourquoi ce référentiel n'est pas un annuaire de plus

Le lot 2 a livré une porte sans poignée — les colonnes de rediffusion, sans
formulaire pour les remplir — et l'a consigné. Le risque symétrique ici était
une poignée sans porte : un CRUD de sources que rien ne lit.

`AnalyseTechniqueAdapter` consulte donc la table à chaque analyse de lien. Une
fiche active y **désarme** le soupçon d'usurpation et **ajoute son nom** au
raisonnement rendu au citoyen. L'écran affiche cet effet en colonne, pour qu'un
référentiel ne devienne pas un tas de fiches que plus personne n'ose retirer.

Le dépôt était obligatoire, et par deux critères plutôt qu'un : la lecture est
cachée (à chaque lien) et partagée (le moteur lit, le back-office écrit).

### Le geste que le produit déclarait sans l'offrir

`MotifMouvementUnite::AjustementAdmin` existait dans l'énumération depuis
l'origine. **Rien ne l'émettait.** Corriger le solde d'un compte demandait
`php artisan tinker` en production — alors que les CGU §6, écrites la veille,
s'y engagent noir sur blanc : « la plateforme procède au crédit ou au
remboursement ».

L'écran l'offre, sous `ai_unit.adjust` seul, avec justification obligatoire, par
`EconomyService`, et sans jamais réécrire une ligne : un mouvement se corrige
par un mouvement inverse.

### La passe au navigateur, faite autrement — et ce qu'elle a rapporté

L'extension Chrome n'était pas connectée. Plutôt que de sauter l'étape, le
contrôle a été porté en script : **toute classe absente du build Tailwind figé
est inerte**, et c'est le défaut qui a le plus coûté au chantier.

Trois trouvailles, dont deux dépassent ce lot :

1. **Sept classes de mes trois vues étaient absentes** — `text-[#94A3B8]`,
   `bg-[#ECFDF5]`, `border-[#A7F3D0]`, `tabular-nums`… Transposées vers des
   classes qui existent.
2. **`st-icon-btn` n'existe pas.** La vraie classe est `st-icnbtn`. **Six vues**
   l'écrivaient — tous les boutons d'action du back-office (modifier, supprimer,
   publier) étaient sans style : ni cadre de 40 px, ni arrondi, ni survol.
   Corrigé partout.
3. **`min-w-[220px]` n'existe nulle part** au build. Retiré de toutes les vues.

Et un quatrième constat, **non traité, qui appartient au lot 10** :
`parametres.blade.php` porte neuf classes inertes, dont
`lg:grid-cols-[240px_1fr]`, `lg:top-6`, `lg:self-start` et `lg:overflow-visible`.
Ce sont **celles du panneau d'onglets latéraux collant demandé par le porteur du
projet**. Aucune n'existe : le panneau ne peut pas coller, la grille à deux
colonnes ne peut pas se former. L'écran a été écrit contre un Tailwind qui n'est
pas là — ce qui explique que la demande paraisse non satisfaite alors que le
gabarit, lui, dit la bonne chose.

### Le contrôle devient permanent

`tests/Feature/ClassesTailwindPresentesTest.php` lit toutes les vues staff et
refuse toute classe absente des feuilles chargées. C'est un **cliquet** : les
douze vues qui portent encore de la dette sont figées dans `detteConnue()`, et
un second test refuse qu'une entrée y devienne périmée. La liste ne peut que
rétrécir.

Deux pièges ont dû être franchis pour que ce test dise vrai plutôt que de crier
au loup — Tailwind **échappe** les valeurs arbitraires dans le CSS produit
(`.text-\[\#64748B\]`), et un attribut `x-bind:class` porte du JavaScript et non
des classes. Les deux sont documentés dans le test, avec le faux positif exact
qu'ils produisaient.

### Livré

| Élément | Chemin |
|---|---|
| Migration | `2026_08_31_140000_referentiel_des_sources.php` — 2 tables |
| Modèles | `SourceCategory`, `TrustedSource` (+ `normaliserLeDomaine()`) |
| Dépôt | `DepotSourcesFiables` + `DepotSourcesFiablesEloquent` (cache 15 min) |
| Écrans | `Staff\SourcesVerifiees`, `Staff\CategoriesDeSources`, `Staff\JournalDesUnites` |
| Policies | `TrustedSourcePolicy`, `SourceCategoryPolicy` |
| Moteur | `AnalyseTechniqueAdapter` — référentiel consulté, suffixes institutionnels exemptés |
| Dépôt étendu | `UnitLogRepositoryInterface::journalGlobal()` + `totauxGlobaux()` |
| Semis | `SourcesFiablesSeeder` — 5 familles, 22 sources |
| Correctifs transverses | `st-icnbtn` (6 vues), `min-w-[220px]` retiré (toutes) |
| Tests | 38, dans 3 fichiers neufs |

**Aucune permission nouvelle** : `trusted_source.*` et `ai_unit.*` étaient déjà
déclarées et semées. Troisième lot d'affilée où la découverte du lot 0 sur la
matrice RBAC paie comptant.

> **`journalGlobal()` est une méthode à part, et surtout pas un
> `?User $utilisateur = null`.** Les cinq méthodes existantes bornent la lecture
> au propriétaire, et l'implémentation dit pourquoi : « c'est ici, et non dans
> l'appelant, que la fuite se produirait ». Rendre le paramètre facultatif
> ferait de l'oubli d'un argument une fuite du journal complet de la plateforme,
> sans erreur pour le signaler. Une méthode qui s'appelle `journalGlobal` ne
> s'appelle pas par accident.

### Sur le jeu de sources semé

Vingt-deux fiches : institutions intergouvernementales, agences de presse
mondiales, portails de l'État béninois, presse nationale, réseaux de
fact-checking. **Ce n'est pas une position éditoriale de Vérif'All** — aucun
média partisan, aucun classement de qualité, et des crédibilités volontairement
prudentes qui n'excèdent pas 92. Un référentiel qui décerne des 100 se fait lire
comme un jugement absolu, alors qu'il ne dit qu'une chose : cette source est
identifiée et suivie. La rédaction est censée l'amender depuis l'écran.

### Vérification

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 539 passés, 1 ignoré, 0 échec** (4 287 assertions, 648 s) |
| Classes Tailwind | 0 absente sur les 3 vues neuves ; garde permanente posée |
| `--filter=CouvertureTraduction` · Pint | passent |
| Migration | 1, deux tables neuves |

### Ce qui reste ouvert après ce lot

- Les **neuf classes inertes de `parametres.blade.php`** — lot 10, et c'est la
  cause d'une demande qui paraît non satisfaite.
- L'**ouverture réelle au navigateur** reste due pour les lots 1, 2 et 5 :
  l'extension Chrome n'était pas connectée. Le contrôle par script couvre le
  défaut le plus fréquent, pas un tiroir qui ne s'ouvre pas.
- Le doublon `Historiques des Unités IA` (fact-checking) / `Logs Unités IA`
  (sécurité) du menu : les deux entrées viennent du prototype. La première est
  livrée ici avec l'ajustement ; la seconde, en lot 10, doit être un journal
  d'audit en lecture seule — ou disparaître. **À trancher au lot 10.**

---

## Lot 6 — L'Académie côté administration

**Livré le 31 août 2026.** Six écrans, une table de plus, un service, et deux
défauts de données trouvés en route.

### Le défaut de données : deux conventions dans le même menu

`courses.category` était une chaîne libre. Sept valeurs distinctes coexistaient
déjà en base, écrites selon **deux conventions** :

    Fact-Checking · OSINT · IA                    ← capitales, accents, tirets
    investigation · securite · sante · methode    ← minuscules, sans accents

Deux semeurs ont écrit chacun dans son style, et rien ne les a arrêtés.
`Citizen\Catalogue` construit son menu de filtres par
`distinct()->pluck('category')` : le citoyen voyait donc les deux conventions
côte à côte dans la même liste déroulante. Le filtre fonctionnait — il compare
la chaîne exacte — mais rien n'empêchait « Sante » de rejoindre « sante ».

**La migration ADOPTE l'existant, elle ne le remplace pas.** Le réflexe aurait
été de semer une liste propre et de réaffecter les cours ; ce serait décider à
la place de la rédaction quelle catégorie remplace « methode ». Chaque valeur
distincte devient donc une catégorie, telle quelle : aucun cours n'a changé de
rayon. C'est depuis l'écran que l'unification se fait — en un point, et pour
tous les cours du rayon à la fois.

> **La colonne texte est TENUE À JOUR à chaque écriture, et c'est le point
> délicat.** Trois lecteurs la consultent encore : le catalogue citoyen, la
> fiche de cours, l'API v1. Renommer sans propager laisserait le rayon affiché
> sous l'ancien nom, sans qu'aucune erreur ne le signale — et le seul symptôme
> serait « le renommage n'a pas marché ». La propagation est dans la même
> transaction que le renommage, et un test la tient.

### Le piège du constructeur : `unique(course_id, position)`

Échanger deux leçons en écrivant l'une puis l'autre **viole la contrainte au
milieu de l'opération** :

    leçon A : position 2 → 3     ← 3 est déjà pris par B. Échec.

Le réflexe est de retirer la contrainte. Ce serait perdre la seule chose qui
garantit qu'un cours n'a pas deux leçons n° 3 — et un plan de cours dont l'ordre
dépend de l'ordre d'insertion ne se répare pas. `ConstructionDeCours` passe donc
par une position tampon (65 535, le maximum du `smallInteger`), en trois temps.

C'est précisément le genre d'invariant qui ne survit pas dans un composant
d'écran : d'où le service, et le test qui l'exerce.

### `publish` séparé de `update` — la matrice l'avait décidé, l'écran s'y tient

`course => ['view', 'create', 'update', 'publish', 'archive', 'delete']`. Le
mentor porte `create` et `update`, jamais `publish` : publier engage la
plateforme — le cours s'affiche au catalogue, se vend en unités et délivre un
certificat.

La conséquence est concrète : **la case « publier » ne figure PAS au formulaire
d'édition.** L'y mettre rendrait le droit contournable par quiconque peut
modifier. Un test lit le composant — et non la vue — pour attraper aussi une
propriété ajoutée sans champ.

> **Un cours sans leçon ne se publie pas.** Le catalogue l'afficherait,
> quelqu'un s'y inscrirait — en dépensant peut-être des unités — et tomberait
> sur une page vide.

### Trois écrans qui n'ajoutent aucune règle, et c'est le sujet

`DepotBanqueQuestions` (10 méthodes) et `Core\Exams\Services\BanqueDeQuestions`
(7 méthodes) existaient **déjà, complets et testés** : création, propositions,
publication sous condition, mise en révision, archivage, statistiques. Il
manquait un écran — le fonds ne pouvait s'alimenter que par la console.

Le composant branche, il n'invente pas. Seule addition : rattraper le refus du
service plutôt que de laisser remonter une 500. `publier()` lève si la question
ne tient pas debout, et c'est une réponse à l'utilisateur, pas une panne — une
question sans bonne réponse posée en examen note faux tout le monde.

### Un certificat ne s'efface pas

`certificate => ['view', 'issue', 'revoke']`. Pas de `delete`, et l'écran n'en
offre aucun. Un test vérifie l'ABSENCE des méthodes de suppression.

Chaque certificat porte un code que n'importe qui contrôle sur
`/verifier-un-certificat`. Effacer une ligne rendrait ce contrôle **négatif** :
le porteur d'un certificat authentique passerait pour un fraudeur, sans recours
et sans trace de ce qui s'est passé.

La révocation est la réponse — motivée, datée, et le motif est **public** : la
page de contrôle l'affiche, et le formulaire le dit à celui qui le rédige
(« un employeur le lira »). Les CGU §7, écrites l'avant-veille, s'y engagent par
écrit. Une révocation se défait, et le rétablissement est journalisé lui aussi :
sur un document opposable, on doit pouvoir raconter l'aller ET le retour.

**Aucune délivrance manuelle.** `certificate.issue` existe, mais un bouton
« délivrer » ici court-circuiterait les six niveaux de preuve que le certificat
affiche — progression, cours terminé, compétences — et produirait une
attestation que rien n'adosse.

### Le revenu d'un cours se LIT, il ne se recalcule pas

L'écran des tarifs affiche ce que chaque cours a réellement rapporté, d'après
`UnitLog`. Multiplier le prix actuel par le nombre d'inscrits appliquerait le
tarif d'aujourd'hui à des achats d'hier : **un chiffre faux qui a l'air juste**.
Un test achète deux fois à 10 unités, passe le tarif à 50, et vérifie que
l'écran dit 20 et non 100.

L'écran existe séparément de la fiche de cours parce que fixer des prix est un
geste **comparatif** : on ne décide pas qu'un cours vaut 30 unités dans
l'absolu. D'où une grille éditable en place, et non quinze tiroirs.

### Un défaut que j'ai introduit et corrigé

`Staff\Cours::formateurs()` passait `'staff'` au scope `role()`. Les rôles sont
tous semés sur le guard `web` — `User::getDefaultGuardName()` le documente
depuis l'origine. Le résultat n'était pas un 403 mais un **écran blanc** :
`RoleDoesNotExist` levée au rendu. Quatre tests l'ont attrapé.

### Livré

| Élément | Chemin |
|---|---|
| Migration | `2026_08_31_160000_categories_de_cours.php` — 1 table, 1 clé étrangère, adoption de l'existant |
| Modèle | `CourseCategory` (+ relation `Course::categorie()`) |
| Service | `Core\Lms\Services\ConstructionDeCours` — positions, purification, propositions |
| Écrans | `CategoriesDeCours`, `Cours`, `ConstructeurDeCours`, `BanqueDeQuestions`, `Certificats`, `UnitesDesCoursPremium` |
| Policies | `CourseCategoryPolicy`, `CoursePolicy`, `LessonPolicy`, `QuizPolicy`, `QuestionBankItemPolicy`, `CertificatePolicy` |
| Tests | 32, dans 2 fichiers neufs |

**Aucune permission nouvelle.** `course.*`, `lesson.*`, `quiz.*`,
`question_bank.*`, `certificate.*` et `lms_category.*` étaient toutes déjà
déclarées et semées. Quatrième lot d'affilée.

> **`lms_category` ne déclare que `view` et `manage`.** Laravel appelle
> `create`, `update` et `delete` : les trois se rabattent sur `manage`. Ce n'est
> pas un contournement mais la lecture fidèle de la matrice — elle a choisi un
> verbe unique parce que créer, renommer et supprimer un rayon relèvent d'une
> seule responsabilité. Inventer `lms_category.create` aurait contredit cette
> décision, et la règle du chantier l'interdit.

### Le menu

**32 entrées « à venir » au début du chantier ; 23 après ce lot.** Le
constructeur n'a pas d'entrée propre, et c'est délibéré : il travaille SUR un
cours, il s'ouvre depuis une ligne. Une entrée au menu mènerait à une page qui
demanderait « quel cours ? ».


### Une garde de sécurité a mordu, et le correctif n'est pas celui qu'on croit

`ChecklistSecuriteTest` a signalé le modèle `Question` dès l'ajout de la route
`Staff\BanqueDeQuestions` : la garde rapproche les modèles des routes PAR LEUR
NOM, et « BanqueDeQuestions » contient « Question ». Le rapprochement est
approximatif — ce n'est pas ce modèle-là que la route sert, mais
`QuestionBankItem`.

La tentation était de desserrer la garde. Elle aurait alors cessé de voir les
modèles RÉELLEMENT liés dont le nom ressemble à celui d'un écran, c'est-à-dire
exactement les cas pour lesquels elle existe.

La vraie question était ailleurs : `Question` doit-il se lier par `id` ? Non.
La règle 4 du chantier est sans exception, la colonne `uuid` existe depuis la
migration d'origine, et cet énoncé est servi aux citoyens qui passent le quiz.
Une ligne ajoutée au modèle ferme la porte avant qu'une route l'ouvre.

C'est la deuxième fois de ce chantier qu'une garde de sécurité signale de biais
quelque chose de vrai — au lot 4, l'interdiction de `{!! !!}` avait imposé de
renommer une propriété plutôt que d'élargir la liste blanche.

### Vérification

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 571 passés, 1 ignoré, 0 échec** (4 364 assertions) |
| Classes Tailwind | garde permanente : 0 absente sur les 6 vues neuves |
| `--filter=CouvertureTraduction` · Pint | passent |
| Migration | 1, appliquée — 7 catégories adoptées, 0 cours déplacé |

### Ce qui reste ouvert

- L'**ouverture réelle au navigateur** : toujours due pour les lots 1, 2, 5
  et 6. L'extension Chrome n'est pas connectée sur ce poste. Le contrôle par
  script couvre le défaut le plus fréquent — une classe inerte — pas un tiroir
  qui refuse de s'ouvrir.
- Le **pont Quill du constructeur** n'a donc pas été vu tourner. Il est copié
  de `faits-verifies.blade.php`, qui fonctionne, mais un second éditeur sur une
  autre page n'est pas la même chose qu'un éditeur éprouvé.
- Les neuf classes inertes de `parametres.blade.php` — toujours lot 10.

---

## Lot 7 — Examens, enrôlement, mentors, KYC

**Livré le 31 août 2026.** Sept écrans, **aucune migration**, et un constat qui
appelle une décision du porteur du projet.

### Presque rien à écrire, encore

Comme au lot 6 pour la banque de questions, le domaine était **déjà là et
complet** :

| Ce qui existait | Volume |
|---|---|
| `DepotExamens` | 18 méthodes |
| `PlanificationExamen` | 11 méthodes |
| `DepotCopiesExamen` | 12 méthodes |
| `CorrectionExamen` | 5 méthodes |
| `DepotInscriptionsExamen` | 7 méthodes |
| `EnrolementExamen` | 8 méthodes |

**Rien de tout cela n'était atteignable sans la console.** Les écrans branchent ;
ils n'ajoutent aucune règle métier. Ce que les tests tiennent, ce sont les
endroits où un écran pourrait DÉFAIRE ce que le domaine avait soigneusement
posé.

### Les refus du domaine sont des réponses, jamais des 500

Six services lèvent des exceptions métier — épreuve sans question, session sur
épreuve non publiée, session dans le passé, copie déjà close, copie incomplète,
session complète. Toutes sont rattrapées et affichées avec leur message.

Le cas le plus parlant est celui de la correction : le service refuse de rendre
une copie et **dit combien de réponses attendent encore une note**. C'est
exactement ce dont un correcteur a besoin ; une 500 ne l'aiderait pas à trouver
laquelle.

> **Un champ de points vide n'est pas un zéro.** L'écran distingue « pas encore
> noté » de « zéro point » — les confondre rendrait une copie incomplète comme
> une copie nulle. Et « enregistrer sans rendre » existe séparément parce
> qu'une correction longue s'interrompt : perdre une heure de notation faute
> d'avoir fini n'est pas acceptable.

> **Un abandon n'est ni une réussite ni un échec.** Le service le pose, l'écran
> reprend ses mots plutôt que « recaler », qui laisserait croire à un zéro.

### `exam_answers` n'a pas d'uuid — et ça change le formulaire

Les champs de notes sont indexés par **position**, pas par identifiant. Publier
un `id` auto-incrémenté dans un `wire:model` le rendrait lisible dans le DOM, ce
que la règle 4 proscrit — même hors d'une URL. La position est unique par copie,
et la copie est elle-même adressée par son uuid : rien de global ne fuit.

### L'accès offert doit rester reconnaissable

Inscrire quelqu'un à un cours depuis l'administration **contourne le débit
d'unités**. C'est voulu — geste commercial, rattrapage d'incident — mais
`paid_units = 0` sur un cours payant est alors la seule trace qui distingue un
accès donné d'un accès acheté. Y écrire le tarif ferait apparaître un
encaissement qui n'a pas eu lieu dans toute mesure ultérieure.

D'où : justification obligatoire, journalisation, et un indicateur « accès
offerts » que rien d'autre ne compte — c'est le chiffre qu'on cherchera le jour
où les recettes ne tomberont pas juste.

> **La progression n'est PAS modifiable**, et un test vérifie l'absence du
> champ. Elle se calcule sur les leçons réellement terminées ; forcée à 100 %,
> elle déclencherait un certificat que rien n'adosse.

### ⚠️ LE KYC N'A AUCUNE CHAÎNE DE DÉPÔT — décision à prendre

C'est le constat le plus important de ce lot.

La permission `kyc => ['view', 'review']` existe. La colonne
`users.kyc_verified_at` existe. Le profil citoyen affiche « Identité vérifiée »
ou « Identité non vérifiée ».

**Il n'y a rien d'autre.** Pas de table de dossiers, pas de pièce téléversée,
pas de motif de refus, pas d'historique. Et surtout : **aucun écran citoyen ne
permet de soumettre quoi que ce soit.** La personne lit « Identité non
vérifiée » et n'a aucun moyen d'agir — c'est une promesse d'écran sans
mécanisme derrière, exactement le défaut de famille du bouton « Voir le
replay » du lot 1.

Deux issues étaient possibles :

1. **Construire la chaîne complète** — dépôt de pièce, stockage sur le disque
   privé, file d'examen, motifs de refus, notification. C'est un module à part
   entière, pas le septième écran d'un lot d'administration.
2. **Ne rien livrer**, et laisser le drapeau inaccessible autrement que par
   `tinker`.

**Ce qui est livré est la moitié qui peut exister honnêtement** : lever et
retirer le drapeau, journalisés, avec justification obligatoire d'au moins
15 caractères. C'est ce que faisait `tinker`, en traçable.

**Et l'écran ANNONCE sa limite en tête**, dans un encadré : aucune pièce n'est
déposée sur la plateforme, la vérification repose sur des documents reçus hors
plateforme, et la justification saisie est la seule trace de ce sur quoi la
décision s'appuie. Un test vérifie la présence de cette phrase.

Un écran qui prétendrait examiner des dossiers inexistants serait **pire que pas
d'écran du tout** : il ferait croire à un contrôle qui n'a pas lieu.

> **À arbitrer par le porteur du projet.** Soit le module KYC complet est
> ouvert comme un chantier à part, soit la mention « Identité vérifiée » est
> retirée du profil citoyen — parce qu'elle promet aujourd'hui quelque chose
> que personne ne peut demander.

### Ce que « Mentors » ajoute à « Utilisateurs »

Un second écran ne se justifierait pas s'il montrait les mêmes lignes filtrées.
Il montre **ce que chaque mentor porte** : cours, cours publiés, copies
assignées. Ce sont les seules questions qu'on se pose sur un mentor — « à qui
confier cette correction ? », « qui est débordé ? », « qui n'a rien produit
depuis six mois ? » — et aucune ne se répond depuis un annuaire de comptes.

Le retrait du rôle affiche **le nombre exact de copies qui perdront leur
correcteur**. Rien ne casse en base ; simplement, personne ne le saurait.

Aucune création ici : elle reste sur `Staff\Utilisateurs`, qui porte les trois
garde-fous du lot 3. Les dupliquer les ferait diverger au premier correctif.

### Une relation manquait sur `User`

`courses.instructor_id` existait depuis la migration du catalogue, mais sans
relation dans ce sens : on allait du cours au formateur, jamais l'inverse.
`User::coursEnseignes()` la pose — sans elle, `withCount()` était impossible et
chaque appelant aurait recopié un `where('instructor_id', …)`.

### Livré

| Élément | Chemin |
|---|---|
| Écrans | `Examens`, `CorrectionExamens`, `StatistiquesExamens`, `EnrolementAuxCours`, `EnrolementAuxExamens`, `Mentors`, `VerificationKyc` |
| Policies | `ExamPolicy`, `ExamSessionPolicy` |
| Modèle | `User::coursEnseignes()` |
| Tests | 18, dans 1 fichier neuf |

**Aucune migration, aucune permission nouvelle.** `exam.*`, `exam_stat.*`,
`enrolment.*`, `user.*` et `kyc.*` étaient toutes déclarées et semées.
Cinquième lot d'affilée.

> **`ExamSession` n'a pas de permissions propres, et ce n'est pas un oubli** :
> une session n'existe que par son épreuve. `ExamSessionPolicy` s'adosse
> explicitement à `exam.*` plutôt que de laisser deviner — `create` sur
> `exam.update`, parce qu'ajouter une date à une épreuve, c'est la modifier.

> **Pas de borne « ses ressources » sur les examens**, contrairement aux cours.
> Une épreuve appartient à l'institution : c'est elle qui délivre le
> certificat, et plusieurs mentors la corrigent. Reprendre la borne des cours
> empêcherait un mentor de corriger l'épreuve d'un autre — soit le
> fonctionnement normal d'un jury.

### Le menu

**32 entrées « à venir » au début du chantier ; 16 après ce lot.** La moitié.

### Vérification

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 589 passés, 1 ignoré, 0 échec** (4 419 assertions, 318 s) |
| Classes Tailwind | garde permanente : 2 absentes corrigées avant livraison (`sm:col-span-2`, `border-[#D1FAE5]`) |
| `--filter=CouvertureTraduction` · Pint | passent |
| Migration | aucune |

### Ce qui reste ouvert

- **Le module KYC**, ci-dessus. C'est une décision, pas une tâche.
- L'**ouverture réelle au navigateur** : due pour les lots 1, 2, 5, 6 et 7.
  L'extension Chrome n'est pas connectée sur ce poste.
- Les neuf classes inertes de `parametres.blade.php` — lot 10.

---

## Lot 8 — Hub, communauté, blog

**Livré le 1er septembre 2026.** Cinq écrans, un service, **aucune migration**.

### La question du dépôt, tranchée avec des chiffres

`CLAUDE.md` rend un dépôt obligatoire dès que la lecture est partagée entre le
web et l'API mobile. C'est le cas ici — `ForumController` et `HubController`
servent les sujets et les articles. Le réflexe était donc d'écrire deux dépôts.

Le comptage a changé la réponse :

| Modèle | Lecteurs existants |
|---|---|
| `HubArticle` | **21** — 4 services marketing, le flux ClaimReview, l'infolettre, l'API, 5 écrans, 2 commandes, le plan du site, un observateur… |
| `Thread` | **8** — l'animation du forum, la réputation, l'API, 4 écrans citoyens |

Écrire un dépôt et ne l'utiliser que sur le nouvel écran donnerait un
**vingt-deuxième lecteur avec un vingt-deuxième style**. Unifier les vingt et
un est un chantier à part entière, dont la taille est maintenant mesurée.

Et surtout, dans le cas du Hub, **un dépôt ne protégerait rien de plus** :
l'invariant qui compte — l'invalidation du cache du flux ClaimReview et la
fabrication de la carte de démenti — vit dans
`#[ObservedBy(ObservateurHubArticle)]`, **sur le modèle**. L'observateur se
déclenche sur TOUT chemin d'écriture, y compris `tinker`, un seeder ou une
commande. C'est plus fort qu'un dépôt qu'on pourrait contourner.

Pour le forum, la conclusion diffère : les ÉCRITURES passent toutes par
`ModerationDuForum`, service neuf. C'est là que vivent les invariants, et c'est
ce qui comptait le plus.

> **À faire un jour, et c'est chiffré** : unifier les 21 lecteurs de
> `HubArticle` et les 8 de `Thread` derrière un dépôt. Pas dans un lot d'écrans.

### Trois distinctions que le code ne doit jamais perdre

**Verrouiller n'est pas supprimer.** Un sujet verrouillé reste LISIBLE : on voit
ce qui a été dit, et qu'un terme y a été mis. La suppression fait disparaître la
discussion entière, réponses de bonne foi et notes de communauté comprises. La
matrice sépare d'ailleurs `forum.moderate` de `forum.delete`. La confirmation
annonce le nombre exact de réponses emportées — une discussion de quarante
messages ne se supprime pas comme un message isolé.

**Escalader n'est pas trancher.** `Thread` distingue `escalated_by` de
`verified_by`, et le modèle le documente lui-même : « escalader, c'est
demander ; apposer un verdict, c'est répondre ». Le service refuse d'escalader
deux fois — la seconde écraserait la date d'origine, donc l'ancienneté de la
demande, seule chose qui ordonne la file du fact-checking.

**Le Hub n'est pas le blog.** Un article du Hub porte une affirmation vérifiée
et un verdict, et alimente le flux ClaimReview que les moteurs ingèrent. Un
billet de blog est du texte de fond, sans verdict. La confusion serait grave
dans un sens : **un texte d'opinion entré dans le flux ClaimReview y serait lu
comme une vérification.** Deux tables, deux écrans, deux jeux de droits — et un
lien croisé sur chaque écran pour renvoyer vers l'autre.

### Deux écrans de modération, et ils ne font pas la même chose

`Staff\SignalementsContenu` (déjà livré) traite ce que les citoyens ont porté à
la modération. `Staff\ForumCitoyen` est la modération **à l'initiative du
modérateur**, sur ce qu'il lit en parcourant le forum.

Les confondre ferait passer à côté de ce que personne ne signale — c'est-à-dire
de la majorité de ce qui pose problème. Chaque écran renvoie vers l'autre.

### Le tri du forum n'est pas chronologique

Épinglés d'abord, puis **dernière activité**. Un modérateur cherche ce qui
BOUGE, pas ce qui vient d'être créé : un sujet ancien qui reçoit vingt réponses
en une heure est ce qu'il doit voir en premier.

### La couleur d'une rubrique est une donnée, pas une classe

La pastille est rendue par un attribut `style`, jamais par une classe Tailwind.
Le build est figé — aucune classe arbitraire ne s'y ajoute, et
`bg-[{{ $couleur }}]` serait inerte. La validation impose la forme hexadécimale
stricte : la valeur entre dans un attribut de style côté citoyen, et une chaîne
libre y serait injectée telle quelle. Un test le vérifie avec une charge
`red; background:url(javascript:…)`.

### Le slug d'une rubrique de forum sert DEUX lecteurs

`ForumController` cherche la rubrique par slug, et l'écran citoyen filtre par
slug. Le laisser suivre un nom corrigé casserait le web et l'API mobile d'un
coup, sans qu'aucune erreur ne le signale. Il est figé à la création, et un test
le tient.

### Deux raccourcis du menu utilisateur ressuscitent

La barre du haut portait « Nouveau cours » et « Nouvel article » en `à venir`
depuis l'origine, pointant sur `builder.html` et `blog.html`. Les deux écrans
existent désormais : les raccourcis sont branchés.

### Livré

| Élément | Chemin |
|---|---|
| Service | `Core\Community\Services\ModerationDuForum` — verrou, épingle, escalade, suppression |
| Écrans | `ActualitesHub`, `ForumCitoyen`, `CategoriesDuForum`, `ArticlesDuBlog`, `CategoriesDuBlog` |
| Tests | 15, dans 1 fichier neuf |

**Aucune migration, aucune permission nouvelle.** `hub_article.*`, `forum.*`,
`forum_category.*`, `post.*` et `blog_category.*` étaient toutes déclarées et
semées. Sixième lot d'affilée.

### Le menu

**32 entrées « à venir » au début du chantier ; 9 après ce lot.**

### Vérification

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 604 passés, 1 ignoré, 0 échec** (4 463 assertions, 496 s) |
| Classes Tailwind | 0 absente sur les 5 vues neuves, du premier coup |
| `--filter=CouvertureTraduction` · Pint | passent |
| Migration | aucune |

### Ce qui reste ouvert

- **Le module KYC** (lot 7) — décision du porteur du projet.
- **L'unification des 21 lecteurs de `HubArticle`** et des 8 de `Thread`
  derrière un dépôt — chantier chiffré ci-dessus.
- L'**ouverture réelle au navigateur** : due pour les lots 1, 2, 5, 6, 7 et 8.
  Deux ponts Quill de plus n'ont pas été vus tourner (Hub, blog), copiés sur
  celui de `faits-verifies` qui fonctionne.
- Les neuf classes inertes de `parametres.blade.php` — lot 10.

---

## Lot 9 — Ressources et communication

**Livré le 1er septembre 2026.** Six écrans, quatre tables neuves, un service,
et le défaut signalé par le porteur du projet enfin corrigé.

### Le défaut signalé : « Gestion FAQ » menait à la page publique

L'entrée du menu d'administration pointait sur `route('public.faq')` : on
cliquait pour gérer la FAQ et l'on atterrissait sur la page publique, en
lecture, sans rien pouvoir modifier.

C'est **la même famille que les deux défauts du lot 2** — `Live Classes` et
`Répertoire des outils` menaient à l'espace citoyen. Et comme eux, le correctif
n'était pas un `href` : l'écran n'existait pas, et les dix-sept questions
vivaient dans `lang/{fr,en}/faq.php`, déroulées à la main dans le gabarit.

Un test lit désormais le GABARIT et refuse `route('public.faq')` dans le menu du
back-office — au même titre que le test du lot 2 refuse `route('citizen.`.

### La FAQ garde ses deux langues, et c'était le point délicat

Passer d'un fichier de langue à une table, c'est risquer de perdre la
traduction. Le site expose un sélecteur FR/EN, et les dix-sept questions
existent dans les deux langues.

D'où la colonne `locale` et la clé `group_key` qui relie les traductions. **Le
semeur reprend les deux fichiers existants** — pas un copier-coller : il apparie
mécaniquement `faq.x` (la question) et `faq.reponse_x` (la réponse), comme
`OutilsSeeder` extrait ses quinze URL du prototype. Recopier dix-sept réponses à
la main, c'est dix-sept occasions de faire une faute dans un texte relu.

Résultat : **17 questions × 2 langues, rien de perdu, rien de retapé.**

Le formulaire édite les deux langues d'un coup. Les séparer garantirait qu'on
corrige la française et qu'on oublie l'anglaise — et une FAQ à moitié corrigée
est pire qu'une FAQ pas corrigée, parce qu'on la croit à jour.

> **Vider une traduction la SUPPRIME.** Garder une ligne anglaise devenue vide
> laisserait une question sans réponse — pire qu'un repli sur le français, qui
> dit au moins quelque chose.

### Les gabarits d'e-mails : la chaîne complète, ou rien

Le réflexe, en écrivant cet écran, est d'offrir **sujet et corps**. C'eût été
une fausse promesse : aucun Mailable ne lisait de gabarit en base, et les six
courriels portent de la LOGIQUE dans leur corps — un code à usage unique et son
délai, un lien signé d'export RGPD, un tableau hebdomadaire, une date de
suppression calculée. Un corps modifiable perdrait ces variables au premier
enregistrement, et le seul symptôme serait **un courriel envoyé sans son code**,
c'est-à-dire une inscription qui ne peut plus aboutir.

Deux issues, comme pour le KYC au lot 7 : livrer une décoration, ou livrer ce
qui peut l'être honnêtement. Ici la chaîne était petite, elle est **faite** :

- `GabaritsDEmail` — service, liste fermée de six clés, cache 30 min ;
- **les six `envelope()` le consultent** ;
- un test envoie un `OtpCodeMail` et vérifie que la surcharge s'applique.

Une ligne absente n'est pas une anomalie : elle signifie « le sujet écrit en
code convient ». La plateforme fonctionne table vide, et un test le tient.

> **Réinitialiser SUPPRIME la ligne**, plutôt que d'y écrire le défaut. « Pas de
> surcharge » et « une surcharge qui vaut le défaut » sont deux états
> différents : le second se fige quand le défaut change.

### Un défaut trouvé en chemin : le « 30 jours » recopié

`SuppressionProgrammeeMail` annonçait « supprimé dans 30 jours » **en dur dans
son sujet**, alors que le corps du même courriel, dix lignes plus bas, calcule
l'échéance depuis `SuppressionCompteService::JOURS_RETRACTATION`.

Le jour où la constante changerait, le sujet annoncerait un délai et le corps un
autre — dans le même message, sur la décision la plus irréversible que la
plateforme propose. Même défaut que celui corrigé dans les CGU la semaine
dernière : un nombre recopié à la main ment au premier changement.

### Le schéma a corrigé trois de mes formulaires

Trois colonnes NOT NULL que j'avais traitées comme facultatives, attrapées par
les tests avant livraison :

| Colonne | Ce que le schéma dit | Ce que j'avais écrit |
|---|---|---|
| `marketing_campaigns.cta` | obligatoire | facultatif |
| `marketing_campaigns.url` | obligatoire | facultatif |
| `badges.icon` | `string(16)` obligatoire | facultatif, `max:60` |

Dans les trois cas le schéma avait raison, et le formulaire a été aligné plutôt
que la colonne assouplie : une campagne sans bouton ni destination occuperait le
haut de chaque page pour dire quelque chose sans permettre d'y donner suite.

Le `string(16)` de `badges.icon` disait autre chose encore : c'est un **emoji**,
pas un nom d'icône Material Symbols — qui dépasse seize caractères. La vue le
rendait dans la police d'icônes, qui aurait tenté de lire l'emoji comme une
ligature. Corrigé.

### Deux carrousels rebranchés sur la base

Le carrousel de partenaires de l'accueil était écrit en dur — noms, URL, chemins
d'images. Ajouter un partenaire, geste ordinaire d'un projet financé et souvent
une obligation contractuelle, exigeait un développeur.

**Un composeur de vue plutôt qu'un contrôleur** : l'accueil est servi par
`Route::view` délibérément — sa route dit qu'on n'y met pas de calcul, c'est la
page la plus visitée. Le composeur cache une heure et, comme celui des campagnes
dans `SeoServiceProvider`, **rattrape l'erreur** : un carrousel de logos ne vaut
pas une page d'erreur. Si la base est indisponible, l'accueil fonctionne sans
partenaires.

### Ce que les écrans disent de ce qui NE se passe pas

Un fil de ce lot, et des précédents : le message d'après-geste dit ce qui ne
change pas.

- Un badge dont on modifie la récompense : « la nouvelle récompense ne vaut que
  pour les prochains déblocages ». C'est exactement ce qu'on croit en changeant
  le chiffre.
- Un témoignage : **non publié par défaut**. L'erreur n'a pas la même
  conséquence dans les deux sens — un témoignage qui attend un jour ne coûte
  rien ; publié par inadvertance, il engage la plateforme sur des propos non
  relus.
- Sa note est **nullable, jamais 5**. Cinq par défaut fabriquerait des avis
  élogieux que personne n'a donnés, et une moyenne calculée dessus serait un
  chiffre inventé qui a l'air mesuré.

### Livré

| Élément | Chemin |
|---|---|
| Migration | `2026_09_01_100000_ressources_et_communication.php` — 4 tables |
| Modèles | `Faq`, `Partner`, `Testimonial`, `EmailTemplate` |
| Service | `Core\Marketing\Services\GabaritsDEmail` + branchement des 6 Mailables |
| Contrôleur | `Public\FaqController` — la FAQ n'est plus statique |
| Écrans | `GestionFaq`, `Partenaires`, `Temoignages`, `GabaritsEmails`, `CampagnesPublicitaires`, `BadgesDeProgression` |
| Semis | `RessourcesEtCommunicationSeeder` — 34 lignes de FAQ, 6 partenaires |
| Composeur | partenaires sur l'accueil, caché 1 h, tolérant à la panne |
| Tests | 20, dans 1 fichier neuf |

**Aucune permission nouvelle.** `faq.*`, `partner.*`, `testimonial.*`,
`campaign.*`, `system.email_template` et `system.badge` étaient toutes déclarées
et semées. Septième lot d'affilée.

### Le menu

**32 entrées « à venir » au début du chantier ; 4 après ce lot.** Les quatre
restantes sont celles du lot 10 : logs e-mails, logs unités IA, sauvegardes BD,
tâches cron.


### Une garde d'accessibilité a mordu, et son motif était trop naïf pour Blade

`ActifsReferencesTest` a déclaré deux images du carrousel sans texte
alternatif. Elles en ont un. Le motif `<img[^>]*>` s'arrête au premier `>` —
y compris celui d'une flèche d'objet : `{{ asset($partenaire->logo_path) }}`
coupait la balise AVANT son `alt`.

Le faux positif est apparu parce que le carrousel a cessé d'être écrit en dur.
Il se serait reproduit à chaque image servie depuis une table, c'est-à-dire de
plus en plus souvent.

La garde retire désormais les expressions Blade avant de découper les balises :
la vérification de `alt` reste identique, et aucune image ne peut s'y
soustraire. **Troisième fois de ce chantier qu'une garde signale de biais
quelque chose de vrai** — et troisième fois qu'on la resserre plutôt que de
l'élargir.

### Vérification

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 624 passés, 1 ignoré, 0 échec** (4 512 assertions) |
| Classes Tailwind | 0 absente sur les 6 vues neuves, du premier coup |
| Courriels | 104 tests d'envoi passent après le branchement des six Mailables |
| `--filter=CouvertureTraduction` · Pint | passent |
| Migration | 1, quatre tables neuves |

### Ce qui reste ouvert

- **Le module KYC** (lot 7) — décision du porteur du projet.
- **L'unification des 21 lecteurs de `HubArticle`** et des 8 de `Thread`
  (lot 8) — chantier chiffré.
- L'**ouverture réelle au navigateur** : due pour les lots 1, 2, 5 à 9.
- Les neuf classes inertes de `parametres.blade.php` — lot 10.

---

## Lot 10 — Sécurité, maintenance, et la refonte des réglages

**Livré le 1er septembre 2026. Dernier lot du chantier.**

**Le menu ne porte plus AUCUNE entrée « à venir ».** Trente-deux au départ,
zéro à l'arrivée.

### Votre demande sur les réglages : le diagnostic était faux, la cause était ailleurs

Vous aviez demandé « un panneau d'onglets sur le côté, collant », parce que
l'affichage des réglages était trop long. Le lot 5 avait trouvé pourquoi ça ne
marchait pas : neuf classes inertes. Ce que ce lot ajoute, c'est **la
compréhension de ce qu'il fallait faire**.

Le gabarit demandait DÉJÀ la bonne chose : `lg:grid-cols-[240px_1fr]`,
`lg:top-6`, `lg:self-start`, `lg:flex-col`. Aucune n'existe dans le build
Tailwind figé, donc aucune n'avait d'effet.

Le réflexe aurait été de chercher des utilitaires équivalents. **Il n'y en a
pas, et il n'y en aura jamais** — le build ne se regénère pas. La vraie réponse
tenait à une distinction qu'on n'avait pas faite :

> `public/assets/css/tailwind.css` est un build FIGÉ de 48 Ko.
> `public/assets/css/staff.css` est du **CSS de projet écrit à la main**, avec
> ses propres tokens. **Il peut grandir.** C'est Tailwind qui est gelé, pas la
> feuille du back-office.

La règle a donc été écrite là : un composant `.st-settings` de soixante lignes,
mobile d'abord, qui bascule en deux colonnes à 1024 px avec un panneau
réellement collant.

> **`align-self: start` est LE détail qui fait la différence.** Sans lui, la
> colonne de navigation s'étire sur toute la hauteur de la grille — et
> `position: sticky` n'a plus rien contre quoi coller. C'est ce qui distingue un
> panneau « collant » d'un panneau qui ne colle pas, et ça ne se voit pas en
> lisant le code.

`parametres.blade.php` a quitté la dette du cliquet. **Onze vues restent.**

### Deux journaux qui rendent visible ce qui échoue en silence

`CLAUDE.md` porte un encadré entier sur le sujet : sans `queue:work`, le code
OTP ne part jamais, la page répond 200, aucune erreur n'est levée, la suite de
tests reste verte. **Le symptôme « l'inscription s'arrête à la deuxième
étape » a coûté un lot entier à diagnostiquer**, faute de pouvoir répondre à
« le courriel est-il parti ? ».

**`mail_logs`** répond. Un écouteur sur le TRANSPORT — pas sur chaque
Mailable : ce qu'on ajoutera demain est journalisé sans qu'on y pense.

L'ordre d'écriture est contre-intuitif et c'est le cœur du dispositif : la
ligne est écrite AVANT l'envoi, en « échoué », et bascule après confirmation.
L'inverse ne journaliserait que ce qui a marché — c'est-à-dire exactement ce
dont personne n'a besoin. Vérifié en conditions réelles : un envoi vers un SMTP
injoignable laisse bien sa trace.

> **Le corps n'est jamais stocké.** `OtpCodeMail` le documente : le code « ne
> doit apparaître NULLE PART ailleurs — ni en journal, ni en base, ni dans une
> exception ». Un test envoie un message contenant `CODE-SECRET-424242` et
> vérifie qu'aucune colonne ne le porte.

**`scheduled_task_runs`** répond à l'autre question. `verifall:diagnostic`
comptait les tâches déclarées et **admettait dans son propre commentaire** ne
pas savoir si un cron les appelle. Six tâches en dépendent — dotation
mensuelle, purge RGPD, réconciliation des paiements.

L'écran croise le DÉCLARÉ et le RÉEL. Et son message le plus utile est celui
qu'il affiche quand rien ne s'est jamais exécuté : **« ce n'est pas six pannes :
c'est l'entrée cron qui manque »**. Le diagnostic, pas les symptômes.

### Les sauvegardes : ce que l'écran refuse de faire

**Il n'existe aucun mécanisme de sauvegarde dans ce projet** — pas de paquet,
pas de commande, pas de tâche planifiée, et le guide de déploiement n'en dit
pas un mot.

Le réflexe aurait été un bouton « sauvegarder maintenant » appelant
`mysqldump`. Trois raisons de ne pas le faire, et chacune suffirait :

- **`mysqldump` n'est pas garanti disponible.** La cible est un hébergement
  cPanel mutualisé, où `exec()` est souvent désactivé. Un bouton qui échoue en
  silence sur un écran de sauvegarde est pire que pas de bouton : on se croit
  protégé.
- **Une sauvegarde qu'on ne peut pas restaurer ne sert à rien**, et personne ne
  s'en aperçoit avant le jour où il faut le faire.
- **Un dump contient tout** : adresses, mots de passe hachés, journaux d'audit,
  dossiers de vérification. Le proposer au téléchargement depuis un navigateur
  créerait une fuite bien pire que l'absence de sauvegarde.

L'écran LIT donc le disque et rend compte. Son indicateur central n'est pas le
nombre de fichiers mais **l'âge du plus récent** : « 12 sauvegardes » ne dit
rien si la plus récente date de six mois.

Et il annonce sa limite en tête, comme le KYC au lot 7 : **la plateforme ne
fabrique aucune sauvegarde.** Un test vérifie cette phrase.

### Le doublon des unités IA, arbitré comme promis

Le prototype porte « Historiques des Unités IA » (fact-checking) et « Logs
Unités IA » (sécurité) : deux entrées, **une seule table**. Deux écrans
identiques auraient donné deux habitudes, donc un des deux qu'on finit par ne
plus relever — le lot 2 a tranché deux doublons de la même façon.

L'entrée de sécurité RESTE au menu mais mène à l'écran unique : la retirer
priverait le responsable sécurité d'un chemin depuis son propre groupe. Et
`Staff\JournalDesUnites` reconnaît désormais `security.unit_logs` en plus de
`ai_unit.view` — la lecture passe, le formulaire d'ajustement reste derrière
`ai_unit.adjust`.

### Un défaut que j'ai introduit, attrapé par un test

`TachesCron::dernieresExecutions()` mémorisait dans un `static $cache`. Un
`static` de méthode vit aussi longtemps que le **processus PHP** : il aurait
survécu d'une requête Livewire à la suivante sous un serveur persistant, et
servi indéfiniment l'état du premier rendu — **un écran de surveillance qui se
fige**, soit l'inverse de son travail.

Deux cas du même fichier de test partageaient la mémoire, et le second voyait
les données du premier. Corrigé en propriété d'instance.

### Livré

| Élément | Chemin |
|---|---|
| Migration | `2026_09_01_140000_journaux_de_maintenance.php` — 2 tables |
| Modèles | `MailLog`, `ScheduledTaskRun` |
| Écouteurs | `JournaliserLesCourriels`, `JournaliserLePlanificateur` |
| Écrans | `JournalDesEmails`, `TachesCron`, `SauvegardesBaseDeDonnees` |
| CSS | `.st-settings` dans `staff.css` — le panneau d'onglets collant |
| Arbitrage | doublon des unités IA — un écran, deux entrées, deux droits |
| Tests | 16, dans 1 fichier neuf |

**Aucune permission nouvelle.** `security.email_logs`, `security.unit_logs`,
`maintenance.cron` et `maintenance.backup` étaient déclarées et semées depuis
l'origine. **Huitième lot d'affilée** — la matrice RBAC découverte au lot 0
n'aura pas eu besoin d'une seule ligne de plus en dix lots.


### Un test antérieur exigeait qu'il RESTE des entrées « à venir »

`TableauDeBordTest` vérifiait la PRÉSENCE de `is-a-venir` dans le menu, sur un
principe qui tenait à l'époque : « un écran manquant est un fait ; un lien qui
fait semblant est un mensonge ».

Les trente-deux écrans livrés, sa prémisse a expiré — pas son intention. Le test
se retourne : plus rien n'est « à venir », donc plus aucun lien ne doit être
mort. Il refuse désormais `is-a-venir`, refuse `data-todo=`, et vérifie qu'aucune
entrée `sidebar-link` n'est dépourvue de `href`. Il lit le FICHIER et non une
page rendue, pour attraper aussi les entrées cachées derrière un `@can` — même
précaution qu'au test anti-`citizen.` du lot 2.

**Quatrième garde de ce chantier qui signale quelque chose de vrai en le disant
de travers**, et quatrième fois qu'on la réécrit plutôt que de la supprimer.

### Vérification

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 640 passés, 1 ignoré, 0 échec** (4 566 assertions) |
| Entrées « à venir » | **0** — trente-deux au départ |
| Classes Tailwind | dette réduite de douze vues à onze |
| `--filter=CouvertureTraduction` · Pint | passent |
| Migration | 1, deux tables neuves |

---

## La passe au navigateur — 1er septembre 2026

**Due depuis le lot 1, faite enfin.** L'extension Chrome n'était pas connectée
pendant dix lots ; la Definition of Done portait donc un point ouvert à chaque
livraison. Il est refermé.

Elle a rapporté **deux défauts réels que la suite ne pouvait pas voir**, et
évité **un faux défaut** que j'ai failli déclarer.

### Défaut 1 — « Tâches planifiées » annonçait 0 tâche sur 6

`routes/console.php` déclare six tâches. L'écran en affichait **zéro** :
« 0 tâche déclarée · Aucune tâche déclarée ».

La cause : `render(Schedule $planificateur)` recevait le planificateur par
injection. Dans une requête WEB, il est **vide** — `routes/console.php` n'est
chargé que par le noyau console.

C'est le pire endroit possible pour ce défaut : sur l'écran même dont le travail
est de dire la vérité sur le planificateur, il disait l'inverse. Et son message
d'alerte — « ce n'est pas six pannes, c'est l'entrée cron qui manque » — ne
s'affichait pas, puisqu'il n'y avait aucune tâche à compter.

**Aucun test ne pouvait le voir** : Pest amorce le noyau console, donc `Schedule`
y est peuplé et l'assertion passait. Correction : amorcer le noyau console avant
de lire le planificateur, plus un test qui garde le symptôme.

### Défaut 2 — la FAQ affichait du balisage en clair

L'écran d'administration montrait, en colonne « question » :

    <button class="accordion-button" type="button" aria-expanded="false"…

Les valeurs de `lang/{fr,en}/faq.php` ne portaient pas le TEXTE des questions
mais **le bouton d'accordéon entier** — d'où le `{!! !!}` de la version
d'origine du gabarit.

Le lot 9 avait remplacé ce `{!! !!}` par `{{ }}`, à raison : le texte vient
désormais de la base, saisi depuis l'administration, et la règle 3 du chantier
s'applique. Mais le semeur reprenait la valeur telle quelle. Résultat : du
balisage échappé, illisible des deux côtés.

La correction sépare ce qui n'aurait jamais dû être mêlé : **la base porte la
question, la vue porte l'accordéon.** Le semeur extrait le texte nu ; le
gabarit rend le `<button class="accordion-button">` autour. C'est aussi ce qui
permet à un rédacteur d'écrire une question sans connaître `aria-controls`.

### Le faux défaut évité

Le tiroir d'édition d'une leçon ne s'ouvrait pas au clic dans le constructeur de
cours. J'ai failli le déclarer cassé.

Vérification faite : `btn.click()` en JavaScript ouvre le tiroir, `tiroirLecon`
passe à vrai, le titre se charge, **et l'éditeur Quill se monte correctement
avec le contenu de la leçon** — le pont jamais vu tourner depuis le lot 6
fonctionne.

C'est le clic synthétique de l'outil d'automatisation qui n'atteint pas
l'écouteur de Livewire, pas l'application. **À retenir pour les passes
suivantes** : un clic sans effet dans cet outil n'est pas une preuve.

### Ce que la passe a CONFIRMÉ

| Écran | Verdict |
|---|---|
| Réglages en onglets latéraux | **Le panneau colle.** La demande du porteur du projet est satisfaite, vérifiée au défilement |
| Journal des e-mails | Les deux envois d'essai s'y trouvent, le bandeau conditionnel s'affiche avec le bon diagnostic |
| Constructeur de cours + pont Quill | Éditeur monté, contenu chargé, flèches masquées en bout de liste |
| Sauvegardes | L'avertissement en tête est lisible et sans ambiguïté |
| FAQ publique | Lit la base, accordéon fonctionnel, ouverture vérifiée |
| Tableau de bord | Bandeau « mode SIMULÉ » affiché, indicateurs justes |

### Un défaut d'ENVIRONNEMENT, à ne pas confondre avec un défaut du produit

Le premier essai a **figé le rendu du navigateur**. Cause : `php artisan serve`
est mono-processus, et le service worker (`/sw.js`) occupait la connexion —
`/livewire/update` a mis **34 secondes**.

Relancé avec `PHP_CLI_SERVER_WORKERS=8`, les mêmes pages répondent en une
seconde. **Ce n'est pas un défaut du produit**, mais c'est à savoir pour toute
passe future : sans workers, le serveur de développement et le service worker
se bloquent l'un l'autre.

### Deux défauts pré-existants, revus

- **Les libellés flottants de la connexion staff** se superposent bien aux
  valeurs — mais **uniquement à l'auto-remplissage du navigateur**. À la saisie,
  ils flottent correctement. Le défaut est réel et plus étroit que noté.
- **La visite guidée** s'est ouverte une fois sur les réglages ; après
  « Passer », elle n'est **pas revenue** sur les écrans suivants. Le défaut noté
  au chantier ne s'est pas reproduit.

### Vérification après la passe

| Barre | Résultat |
|---|---|
| `php artisan test --parallel` | **1 641 passés, 1 ignoré, 0 échec** (4 569 assertions, 327 s) |
| Écrans ouverts au navigateur | 8, dont les 3 du lot 10 |
| Défauts trouvés | 2, corrigés |
| Faux défauts évités | 1 |

**Le point « ouverture réelle au navigateur » de la Definition of Done est
refermé.** Il était ouvert depuis le lot 1.

---

## L'analyse statique — 1er septembre 2026

**La barre que `CLAUDE.md` prescrivait depuis l'origine, et qui n'avait jamais
été franchie une seule fois.** La commande `./vendor/bin/phpstan analyse
--level=6` y figurait dès le premier jour ; le binaire n'existait pas, il n'y
avait pas de `phpstan.neon`, et `composer.json` ne mentionnait pas le paquet.
Recopiée de bonne foi, la ligne laissait croire le contraire.

Après ~45 classes ajoutées en dix lots, c'était le moment ou jamais.

### Un seul réglage valait 173 erreurs

Première passe : **353 erreurs**. Le réflexe aurait été d'en corriger 353.

Un échantillon rendait le motif suspect : « Cannot call method `format()` on
`string` » sur des `datetime`, « match toujours faux entre `string` et
`StatutCopieExamen` » sur des enums. `\PHPStan\dumpType()` sur
`ExamAttempt::$status` a tranché : l'analyseur voyait `string`, pas l'enum. Il
ne lisait donc **pas** les casts.

La cause tient dans `vendor/larastan/larastan/extension.neon:34` :
`parseModelCastsMethod` vaut **`false`** par défaut. Larastan lit alors les
`$casts` sous leur forme **propriété** ; ce projet déclare partout la forme
**méthode** de Laravel 11 (`protected function casts(): array`). Faute de les
trouver, larastan se rabat sur le schéma de la base — où un enum n'est qu'une
chaîne.

Réglage activé : **353 → 180**. Corriger 250 symptômes d'une seule mauvaise
configuration aurait été corriger cette configuration *dans le code*.

### Quatre exceptions, chacune vérifiée avant d'être posée

180 → **101**, par quatre entrées `ignoreErrors`, toutes justifiées en
commentaire dans `phpstan.neon`. Deux d'entre elles ont été vérifiées ligne à
ligne parce que « corriger » l'erreur aurait **cassé le produit** :

- **`catch (DecryptException)` dans `TwoFactorService`** — déclaré mort par
  l'analyseur. Il ne l'est pas : le déchiffrement a lieu **à l'accès à
  l'attribut** casté `encrypted`, donc bien à l'intérieur du `try`. PHPStan ne
  modélise pas les casts comme sources d'exception. Le retirer aurait laissé une
  clé d'application changée faire tomber la connexion à deux facteurs en 500.
- **Les 69 `?->` « toujours non nuls »** — l'analyseur les prouve redondants
  d'après le schéma. Ils protègent aussi le cas d'un modèle chargé par un
  `select()` partiel, où l'attribut est absent sans être nul. Soixante-neuf
  retraits pour un gain nul et un risque non nul ne se justifient pas.

Les deux autres sont de purs artefacts : `$this->maPropriete` sur une méthode
`#[Computed]` de Livewire (résolue à l'exécution), et les agrégats SQL nommés
(`selectRaw('MIN(x) as premiere')`), qu'aucun analyseur ne peut connaître et
que déclarer en `@property` ferait mentir sur le schéma.

### Le cliquet, pas le tapis

Restaient **101** imprécisions de typage réelles. **Aucune n'est un bug
d'exécution** : ce sont des annotations trop larges ou trop étroites — 29
`return.type`, 14 `argument.type`, le reste en génériques et itérables manquants.

Elles sont figées dans `phpstan-baseline.neon`, sur le modèle déjà adopté par
`ClassesTailwindPresentesTest` : **la liste ne doit que rétrécir.** Tout code
nouveau est analysé pleinement, et `reportUnmatchedIgnoredErrors: true` force à
retirer une entrée dès qu'elle ne correspond plus. Le seul geste interdit est de
régénérer le fichier d'un bloc pour absorber une erreur neuve.

Et le cliquet a tourné **dans le même mouvement**, pour ne pas le laisser à
l'état de promesse : **101 → 95**.

- `DonneesSeo::$publieLe` / `$modifieLe` étaient typés `Illuminate\Support\Carbon`
  alors que le seul appel qu'ils reçoivent est `toIso8601String()`. Typés
  `Carbon\CarbonInterface`, ils disent la vérité **et** acceptent les deux
  implémentations. *(4 erreurs)*
- `ConnexionSocialeController::rediriger()` annonçait
  `Illuminate\Http\RedirectResponse`. `Socialite\Contracts\Provider::redirect()`
  ne déclare **aucun** type de retour : rien dans le contrat ne le promet. Le
  pilote OAuth2 en rend une aujourd'hui, un pilote tiers n'y est pas tenu. Une
  route accepte n'importe quelle réponse Symfony — rien n'est perdu. *(1)*
- Un `@phpstan-ignore-next-line` dans `Staff\JournalDesUnites` ne couvrait
  **aucune** erreur : posé à l'aveugle, à une époque où PHPStan n'était pas
  installé et ne pouvait donc pas le contredire. Retiré. *(1)*

Ce dernier point résume l'intérêt de l'exercice : une barre qu'on ne peut pas
franchir ne laisse pas le code en l'état, elle laisse s'accumuler des gestes
qui ont l'air d'être des précautions.

---

## Lot 11 — moteurs réels et tableau de bord qui mesure · 1er septembre 2026

### Les clés livrées, testées une par une auprès de leur émetteur

Le porteur du projet a fourni un bloc de clés en demandant « un seeder ». Il n'y
en avait pas besoin : **elles étaient déjà toutes dans `.env`**, et
`config/services.php` était déjà entièrement câblé, rotation Gemini à cinq clés
comprise. Un seeder aurait par ailleurs violé la règle 5 — un secret gravé dans
un fichier du dépôt.

Ce qu'il fallait, c'était les **vérifier**. Sonde en lecture seule sur chaque
fournisseur :

| Fournisseur | Verdict de l'émetteur |
|---|---|
| Gemini ×5 | les cinq valides ; `gemini-2.5-flash` confirmé présent |
| OpenAI, DeepSeek | valides |
| **Google** (`AIzaSy…`) | **« API Key not found »** sur FactCheck, CSE **et** Safe Browsing |
| **Winston AI** | **401 `ERROR_RETRIEVING_USER`** |
| **AI or Not** | **401 `failed to decode JWT`** |

La clé Google est la plus coûteuse : elle alimente cinq entrées de
`services.php`, dont `google_factcheck` — **poids 1.0**, le plus élevé du
barème, parce qu'un fact-check déjà publié est une PREUVE là où un modèle ne
produit qu'une inférence.

Au passage, un défaut bloquant dans le bloc fourni : `GOOGLE_REDIRECT_URI`
pointait sur `/auth/google/callback` alors que la route déclarée est
`/auth/{fournisseur}/retour`. Le retour de Google serait tombé en 404 à chaque
connexion.

### Le registre annonçait « disponible » sur des moteurs morts

`RegistreDesMoteurs::etat()` répondait à une seule question : *une CLASSE
d'adaptateur existe-t-elle ?* Ni la clé, ni le service. Sur la configuration de
ce jour-là, il annonçait **Perplexity opérationnel avec une clé vide** et
**Google Fact Check opérationnel avec une clé que Google refuse**.

La carte « Santé du système » demandée par le porteur du projet aurait affiché
du vert sur deux moteurs morts — une panne transformée en confiance, sur la
première page après connexion.

`etat()` est **retiré** (aucun appelant : l'écran pour lequel il avait été écrit
n'existait pas) et remplacé par `SanteDesMoteurs`, qui pose trois questions
distinctes et rend cinq états. `Configure` dit franchement « clé présente,
personne n'a vérifié » — Perplexity n'expose aucun point d'appel gratuit, et
inventer un verdict là-dessus serait refaire l'erreur qu'on corrige.

**Les sondes ne tournent JAMAIS à l'affichage.** Le quota gratuit de Google
Custom Search est de cent requêtes par jour : un tableau de bord qui sonde à
chaque ouverture épuiserait avant midi le quota du moteur qu'il surveille. Cache
d'une heure, rafraîchi par le planificateur, plus un bouton explicite. Un test
le garde — `Http::assertNothingSent()` sur le rendu de l'écran.

### Hébergement mutualisé : trois lignes de la maquette écartées

Le porteur du projet est en **cPanel mutualisé**. Trois lignes de
`compte-admin/dashboard.html` ont été retirées après examen, et non transposées :

- **« Reverb (WebSockets) — Déconnecté »** : Reverb **n'existe pas dans ce
  projet**. Absent de `composer.json`, aucun événement `ShouldBroadcast`, pas de
  `routes/channels.php`, `BROADCAST_CONNECTION=log`. Le temps réel se fait au
  `wire:poll`, déjà à six endroits. La ligne aurait rapporté l'état d'un
  composant jamais installé. *(`CLAUDE.md` liste toujours `reverb:start` dans le
  flux de dev : à corriger.)*
- **« Charge CPU 46 % »** : `sys_getloadavg()` rend la charge de TOUT le nœud —
  des centaines de comptes — et l'hébergeur la désactive souvent.
- **« Espace disque 89 % · 300,6 Go libres sur 2 697 Go »** : c'est la partition
  du serveur, pas le quota cPanel. Le chiffre décrirait l'espace du voisin.

Remplacées par ce qui casse réellement en mutualisé, où aucun démon ne tourne :
**planificateur** (dernière exécution), **file d'attente** (âge du plus ancien
job — un compteur à 216 ne dit pas si ça avance, un job de trois heures dit que
le worker est mort), **courriel** (taux d'échec 24 h), **stockage applicatif**.

Sur le poste, la carte annonce en ce moment : *planificateur jamais exécuté*,
*8 jobs bloqués depuis 22 h*. C'est exact, et c'était invisible.

### Six panneaux qui n'avaient aucune colonne derrière eux

« Général 427 », « Chrome 68 % » étaient **écrits en dur dans le HTML** du
prototype. Une migration leur donne une base :

- **`verifications.theme`** — aucune taxonomie du projet ne portait « Arnaque » :
  ni `hub_articles.category`, ni `forum_categories`, ni `hub_portals.category`.
  Classée par `ClassificationThematique`, **heuristique lexicale déterministe et
  non un appel de modèle** : une thématique ne vaut pas une requête facturée, un
  modèle qui rend « Politiqu3 » une fois sur cent ruinerait l'agrégat, et
  surtout « pourquoi ce dossier est-il en Arnaque ? » doit avoir une réponse
  vérifiable — ici « parce qu'il contient *mobile money* ». Posée par un
  OBSERVATEUR : les vérifications naissent par trois chemins, plus les fabriques
  et les seeders, et un appel posé dans un seul produirait un classement partiel
  — qui ressemble à un classement.
- **`verifications.assigned_to`** — la colonne « Assigné à » était décorative.
- **`hub_articles.source_clicks`** — « clics » n'existait pas et n'a de sens que
  défini : départs vers `source_url`, ce qui distingue un article lu d'un
  article qui a renvoyé quelqu'un à la preuve.
- **`visitor_hits`** — une ligne par famille de navigateur et par jour, **et
  rien d'autre**. Pas d'IP même hachée, pas d'identifiant, pas d'en-tête brut
  (un User-Agent complet redevient identifiant), pas de page consultée, aucun
  tiers. Aucun parcours n'est reconstituable, y compris par qui obtiendrait la
  table entière : c'est ce qui répond à la question sans rien ajouter aux
  obligations de `SECURITE_RGPD.md`.

### L'assignation, avec ses trois garanties

**Zéro permission nouvelle** : `signalement.assign` et `signalement.verdict`
existaient déjà dans `MatricePermissions`. Onzième lot d'affilée sans en ajouter.

1. **On ne confie pas du travail à qui butera sur un 403.** Le destinataire doit
   détenir `signalement.verdict`. La liste se construit sur la PERMISSION et non
   sur le rôle : une liste « Mentor ou Modérateur » se désynchroniserait le jour
   où un rôle change de périmètre.
2. **Une réassignation prévient les DEUX personnes.** Reprendre un dossier sans
   le dire laisse quelqu'un enquêter sur une analyse qui ne lui appartient plus,
   et le découvrir en publiant.
3. **La course est tranchée en base** (`lockForUpdate`), sinon deux responsables
   cliquant à la même seconde s'écrasent et le premier croit avoir assigné.

Le destinataire reçoit **notification ET courriel** — la base
`NotificationCitoyenne` ne diffuse qu'en `database`, ce qui convient à ce qu'on
découvre en revenant, pas à un travail confié. Le retrait, lui, n'a qu'un canal :
un courriel « vous n'avez rien à faire » userait l'attention gardée pour
l'assignation.

Septième clé ajoutée à `GabaritsDEmail::CLES`, dont la liste est fermée parce
qu'elle prétend énumérer **tout** ce que la plateforme envoie. C'est le seul
courriel destiné au personnel, et il y figure quand même.

### Deux défauts trouvés par les tests que j'écrivais

- **`iconv('ASCII//TRANSLIT')` rendait `pr'esident`** pour « président ».
  L'apostrophe devenait une espace au nettoyage, le mot se cassait en
  « pr sident », et **aucun mot-clé accentué n'était plus jamais reconnu**. Le
  classement marchait sur « vaccin » et « carburant » et échouait en silence sur
  la moitié de la taxonomie. `Str::ascii()` s'appuie sur une table embarquée :
  même résultat sur toutes les machines.
- **« Aucun titulaire précédent » confondu avec « rien n'a changé »** : la
  transaction ne rendait que le précédent, donc réassigner quelqu'un à son
  propre dossier lui renvoyait un courriel. Elle rend maintenant les deux
  séparément.

Et deux listes de mots-clés complétées : « économie » et « sécurité » ne
figuraient pas dans leur propre liste, et « fausse offre » manquait alors que le
Hub porte l'exemple `fausse-offre-demploi-au-port-autonome-de-cotonou` — que
« port » faisait basculer en Économie.

### La suite dépendait du `.env` du poste — vingt-cinq tests l'ont dit

Poser `FAKE_ADAPTERS=false`, ce que la mise en production **exige**, a fait
tomber **25 tests d'orchestration** d'un coup.

La cause n'est pas dans le code : `phpunit.xml` n'épinglait pas
`FAKE_ADAPTERS`, et il n'existe pas de `.env.testing`. La suite lisait donc la
valeur du `.env` de la machine, et n'était verte que parce que ce fichier
portait `true`. Le premier à basculer en production aurait vu vingt-cinq échecs
et accusé le code, alors que seule la configuration avait bougé.

La variable est désormais épinglée dans `phpunit.xml`, avec les autres
(`CACHE_STORE`, `QUEUE_CONNECTION`, `MAIL_MAILER`). Les rares tests qui veulent
de vrais adaptateurs le déclarent eux-mêmes par `config()->set()`.

**Une suite de tests ne doit pas changer de comportement selon la machine qui
l'exécute.** Le défaut était là depuis l'origine ; il fallait toucher au réglage
pour le voir.

### Décisions du porteur du projet, consignées

- **CGU** : lues et validées le 1er septembre 2026. La réserve « non relu par un
  juriste » est levée ; la validation est celle du responsable de la plateforme,
  non d'un conseil extérieur.
- **KYC** : la chaîne documentaire sera **construite**. L'autre issue — retirer
  « Identité vérifiée » du profil citoyen — est écartée.

### Déconnexion en rouge — par spécificité, pas par ordre

`staff-shell.css` est un extrait **verbatim** du prototype et n'est pas
modifiable ; il est chargé APRÈS `staff.css`. Une règle de même poids aurait
perdu. Le préfixe `#sidebar` porte les sélecteurs à (0,1,2,0) : ils l'emportent
quel que soit l'ordre de chargement.

---

## Lot 13 — l'aside du rapport, le KYC, les portails, les interrupteurs · 1er septembre 2026

### L'aside du rapport : trois boutons qui n'écrivaient nulle part

Le prototype dessine six cartes à droite du rapport. Trois portaient des
boutons reliés à **rien** — « Demander un expert » ouvrait une fenêtre dont le
formulaire n'allait nulle part, « 👍 / 👎 » n'avait aucun stockage, « WhatsApp »
n'avait pas d'URL. Un bouton qui ne fait rien est pire qu'un bouton absent : il
consomme la bonne volonté de quelqu'un qui voulait aider.

L'ordre du prototype a été respecté, et il raconte quelque chose : **les preuves
d'abord, l'aveu de faillibilité ensuite, le recours humain juste après.** On ne
demande l'avis du lecteur qu'après lui avoir montré sur quoi on s'est fondé.

- **`verification_feedback`** — la table est dessinée mot pour mot par
  `docs/kb/API_SPECIFICATION.md` §11, qui déclarait
  `POST /verifications/{uuid}/feedback` **absent** *faute de stockage* et en
  donnait le plan : « compte, vérification, utile oui/non, unicité sur le
  couple ». **L'endpoint mobile cesse d'être une réserve.** Le vote se CHANGE :
  sans l'unicité, un pouce cliqué douze fois mesurerait l'agacement.
- **`counter_expertises`** — quatre issues explicites, dont **« Vous aviez
  raison : le rapport a été corrigé »**. Réutiliser `content_reports` aurait
  enterré un appel éditorial dans une file de modération : signaler un message
  injurieux et contester NOTRE verdict sont deux gestes sans rapport, traités
  par des personnes différentes. Une plateforme qui ne sait pas compter ses
  propres corrections ne peut pas dire si sa méthode s'améliore.
- **Le partage** avec repli `execCommand` : `navigator.clipboard` exige HTTPS et
  manque à bien des navigateurs mobiles d'ici. Sans le repli, le bouton ne
  ferait RIEN, sans rien dire.

La file atterrit dans un endroit réel — panneau « Verdicts contestés » au
tableau de bord, compteur dans « à traiter » — parce qu'une table qu'on écrit et
que personne ne lit a déjà été refusée deux fois sur ce chantier.

### La chaîne KYC, décidée par le porteur du projet

Le lot 7 avait livré une demi-mesure assumée : un drapeau que seule
l'administration pouvait lever, et un citoyen qui lisait « Identité non
vérifiée » **sans aucune action possible**. Deux issues étaient posées.
Construire, a-t-il tranché.

Quatre décisions qui tiennent le module :

1. **Le numéro n'est jamais conservé en clair.** HMAC seulement, pour
   reconnaître une pièce présentée par deux comptes. L'examinateur lit le numéro
   sur l'image ; le stocker créerait un fichier de numéros de pièces d'identité
   que rien n'oblige à détenir.
2. **Aucune image n'a d'URL.** Disque privé, nom aléatoire, et quatre gardes —
   signature à dix minutes, session du personnel, `kyc.review`, présence du
   fichier. Servie en `inline` : un téléchargement laisserait des copies de
   cartes d'identité dans le dossier « Téléchargements » de chaque poste.
3. **Les pièces s'effacent, le dossier reste.** Trente jours après la décision,
   par la purge RGPD déjà planifiée. Type, décision, motif, examinateur : de quoi
   **prouver** le contrôle sans garder le scan.
4. **Le drapeau ne se lève plus à la main.** Approuver passe par un dossier ;
   seul le retrait reste possible sans, parce qu'une vérification obtenue par
   fraude doit pouvoir tomber tout de suite.

Le badge « Identité non vérifiée » du profil est devenu un LIEN. Il annonçait un
état sans offrir aucun moyen d'en changer.

### Portails Officiels : une table écrite que personne ne lisait

`hub_portals` existait depuis les premiers lots, cinq lignes semées, **aucun
lecteur** — ni CRUD, ni écran, ni API. Le CRUD est livré ; la rubrique citoyenne
attend derrière un interrupteur fermé, comme demandé.

Le point tenu par un test : **la route rend 404 tant que c'est fermé.** Un lien
désactivé dont l'adresse répond quand même n'est désactivé que pour la forme —
l'adresse se devine, se copie, se retrouve dans un historique.

Un test antérieur exigeait l'inverse — que l'entrée soit RETIRÉE — et il avait
raison à l'époque. Sa prémisse a expiré : il vérifie maintenant que l'entrée
existe ET mène quelque part. La mise en retrait d'origine était explicitement
« décidée, pas un abandon » ; c'est le retour qu'elle prévoyait.

### Les moteurs : un interrupteur par moteur, engendré depuis l'enum

Décisions d'exploitation du porteur du projet, chacune avec sa raison :

| Moteur | État | Pourquoi |
|---|---|---|
| **TinEye** | éteint | service payant, budget insuffisant |
| **Vision** | éteint | dépend de `GOOGLE_API_KEY`, refusée par Google |
| **Transcription** | éteint | aucun fournisseur souscrit |
| **AI or Not** | allumé | fournisseur retenu ; GPTZero et HuggingFace écartés |

Douze interrupteurs **engendrés depuis l'enum** : douze lignes recopiées, ce
serait douze occasions d'oublier un moteur le jour où l'enum en gagne un
treizième — et un moteur sans interrupteur est un moteur qu'on ne peut plus
éteindre, découvert le jour où il coûte.

Trois propriétés, dont la troisième est celle qu'on oublie :

- un moteur éteint **n'est pas appelé**, même avec une clé valide — le contrôle
  est dans le REGISTRE, pas dans chaque adaptateur ;
- il **n'est pas sondé** non plus : sur un service payant, la sonde EST la
  facture ;
- la carte dit **« Éteint »** et non « Clé absente ». La confusion coûterait une
  demi-journée à quelqu'un parti chercher une panne qui n'existe pas.

### OpenAI porté — « activer » n'était pas une case à cocher

L'adaptateur n'existait pas : il faisait partie des six moteurs non portés. La
clé était valide, le poids de 0,70 le justifiait. Température à **zéro** : un
verdict de fact-checking doit être REPRODUCTIBLE, sans quoi la même affirmation
rendrait VRAI puis DOUTEUX et le journal des corrections perdrait son sens. Ses
« sources » entrent avec une fiabilité de 45, comme DeepSeek — sans accès web,
ce sont des souvenirs d'entraînement, pas des pages ouvertes.

**Quatre moteurs comptent désormais dans un verdict**, contre trois.

### Vidéo et audio fermés côté citoyen — et la fermeture est DÉDUITE

Boutons visibles mais inactifs, toast au clic. Les retirer laisserait croire que
la plateforme n'analyse pas la vidéo : c'est une indisponibilité, pas un
périmètre.

Ce n'était pas qu'une question d'affichage. Sans transcription, le registre
rendait quand même Gemini et la recherche web sur un fichier audio : l'analyse
serait partie, **l'unité aurait été débitée**, et le rapport serait sorti vide.

`TypeContenu::moteurIndispensable()` déclare la dépendance ; le jour où un
fournisseur est souscrit et le moteur rallumé, **les deux boutons se rouvrent
seuls**. Aucune liste à maintenir, et un test le garde.

### Deux pièges de Blade, payés comptant

- **`@php(...)` en ligne et `@php … @endphp` en bloc ne cohabitent pas dans un
  même gabarit.** Blade apparie le PREMIER `@php` du fichier — la forme en ligne
  comprise — avec le premier `@endphp`, et met tout l'intervalle en bloc BRUT :
  plus rien ne s'y compile, les `{{ }}` sortent en clair, et le seul symptôme
  est un « Undefined variable » à cent lignes de la cause. Une heure. Le calcul
  est remonté dans le composant, où la règle 1 le voulait de toute façon.
- Un `{{-- --}}` **à l'intérieur** d'un bloc `@php` est du PHP invalide.

### Et un piège de test

`actingAs()` persiste pour tout le reste du test. Un contrôle « sans session »
placé après une assertion authentifiée hérite de la connexion et rend un **faux
vert**. C'est arrivé sur la garde des pièces KYC : l'ordre a été inversé, et le
commentaire dit pourquoi.

### Zéro permission nouvelle

`hub_portal.view/manage`, `kyc.view/review`, `signalement.assign/verdict`
existaient toutes dans `MatricePermissions`. Douzième lot d'affilée.

---

## La passe au navigateur du lot 13 — 1er septembre 2026

Elle a rapporté **trois défauts réels que la suite ne pouvait pas voir**, dont
un qui rendait un écran entier inopérant.

### Défaut 1 — les boutons du KYC ne faisaient RIEN

Cliquer « Approuver » sur un dossier d'identité : aucune erreur, aucun toast,
aucun changement. Le dossier restait en attente.

La cause : les trois boutons étaient `type="submit"` dans un
`<form wire:submit="statuer">`, avec un `wire:click="$set('geste', …)"` pour
dire lequel avait été pressé. **Les deux mécanismes Livewire se marchent
dessus** — le `$set` part, la soumission n'atteint jamais `statuer()`.

**Les tests ne pouvaient pas le voir** : ils appellent `->call('statuer')`
directement et ne touchent jamais aux boutons. Un écran peut être vert de bout
en bout côté serveur et totalement inopérant dans un navigateur.

Correction : un bouton, une action, le geste en argument. Plus de `<form>`, plus
de `$set`.

### Défaut 2 — « V-000000 » sur toutes les lignes

Le panneau « Dernières activités de fact-checking » affichait la même référence
sur chaque dossier. `reference()` compose « V-000123 » à partir de la clé
primaire, et mon `select()` l'omettait : l'attribut absent donnait `V-000000`.

Un identifiant qui n'identifie rien, sur l'écran où l'on confie du travail — et
la page se rendait sans la moindre erreur. Le panneau voisin, qui charge
`verification:id,uuid,claim`, affichait la bonne référence : c'est la
comparaison à l'écran qui a rendu l'écart visible.

### Défaut 3 — un « @ » orphelin

« Nouveaux citoyens » affichait `@` seul sous chaque nom : le pseudonyme est
facultatif et vaut nul pour tous les comptes semés.

### Ce que la passe a CONFIRMÉ, en conditions réelles

| Ce qui a été éprouvé | Verdict |
|---|---|
| Contre-expertise tranchée depuis le tableau de bord | Issue enregistrée, panneau vidé, toast juste, journal `verification.contre_expertise.corrected` |
| Notification au demandeur | « Vous aviez raison : le rapport a été corrigé », en base |
| Assignation d'une vérification | « Confié à Sandrine Houngbo », notification **et** courriel, `mail_logs` à `sent`, corps du message vérifié |
| Pièces KYC | Lien **signé et expirant**, image servie `inline` avec `noindex/noarchive` et `no-store` — **adresse nue en 403** |
| Approbation KYC | Drapeau levé, journal `kyc.approuve`, notification en file |
| Portails officiels — rubrique fermée | Entrée présente dans le menu du compte, `<span>` sans `href`, `aria-disabled`, mention « Bientôt » — **et la route rend 404** |
| Vidéo / audio | Boutons `is-ferme`, toast « Module vidéo indisponible pour le moment », type inchangé, **aucun débit** |
| Aside du rapport | Les six cartes dans l'ordre du prototype, 👍 enregistré et persistant (`aria-pressed`), partage WhatsApp porteur du verdict et du lien |
| Onglet « Moteurs d'analyse » | 12 interrupteurs, 3 éteints, chaque explication lisible à l'écran |

### Un défaut d'environnement, à ne pas confondre avec un défaut du produit

`MAIL_MAILER=smtp` pointe sur `mail.verifall.com`, injoignable depuis le poste :
**chaque courriel bloque 63 secondes puis échoue**, et la file n'avance plus.
`CLAUDE.md` recommande `MAIL_MAILER=log` en local précisément pour cela. La
valeur d'origine a été rétablie après la passe — c'est un choix de
configuration qui appartient au porteur du projet.

### Une observation non expliquée

À deux reprises, la page a **navigué seule** vers un autre écran
d'administration entre deux appels de l'outil. Pas reproductible à la demande,
et l'outil d'automatisation a déjà montré des clics erratiques lors de la passe
précédente. Noté comme observation, pas comme défaut : à re-regarder lors d'une
passe manuelle.

---

## Lot 14 — la fiche de cours, les supports, et ce qu'un cours produit · 2 septembre 2026

### Le référencement du Hub — et une erreur de diagnostic, corrigée

**Ce paragraphe rectifie ce qui a d'abord été écrit ici.** J'avais annoncé un
défaut latent : « `Referencement.php` lit `meta_title` sur `HubArticle` alors
que la colonne n'existe pas ». **C'était faux, et l'erreur venait d'une lecture
trop rapide** : la ligne incriminée appartient à `pourArticleDeBlog(BlogPost
$article)`, où la colonne existe bel et bien. Aucun bug silencieux, donc.

Le fait réel est plus simple : **`pourFaitVerifie()` — la méthode des actualités
du Hub — n'a jamais offert de surcharge SEO.** Elle composait titre et
description à partir des seuls champs éditoriaux. Ce n'était pas un défaut, mais
un manque, et c'est précisément ce que la demande venait combler.

En ajoutant les colonnes et le formulaire sans toucher à cette méthode, j'ai
créé pour quelques heures ce que ce chantier refuse : des champs qu'un rédacteur
remplit et que rien ne lit. **Trouvé à la passe navigateur**, en vérifiant que
la valeur enregistrée ressortait bien du service — elle ne ressortait pas.

### L'audit avant l'écriture

Sur sept demandes de champs, **quatre existaient déjà** et n'étaient
qu'inexposées : `cover_path` sur les cours, le Hub et le blog ; `cost_units`
pour le premium ; `outcomes` en JSON ; `instructor_id`. Les créer à nouveau
aurait produit des doublons silencieux.

Manquaient réellement : prérequis, langue, prime d'XP, SEO des cours et du Hub,
mots-clés du blog, support audio, documents joints — et l'écran de statistiques.

### Trois décisions, et leur raison

**Les documents joints vont sur le disque PRIVÉ, derrière une route signée qui
vérifie l'inscription.** Un cours payant dont les PDF sont servis par une URL
devinable est un cours vendu une fois et diffusé cent. Même dispositif que les
pièces KYC, pour une raison différente : là c'était la vie privée, ici la valeur
du cours.

Deux différences délibérées avec le KYC, et chacune s'explique par l'usage :
la signature **n'expire pas** — un support se consulte tout au long de la
formation, et un lien qui périme au milieu d'un module ferait recharger pour
rien ; et le fichier part en `attachment` et non `inline` — un support de cours
se garde pour être relu hors ligne, ce qui compte quand la connexion n'est pas
acquise.

**La progression moyenne écarte ceux qui n'ont jamais commencé**, et les compte
à part. Un inscrit à 0 % n'a pas abandonné le cours : il ne l'a pas ouvert. Les
compter tirerait la moyenne vers le bas sans rien apprendre du CONTENU — et
masquerait le décrochage qu'on cherche. Le second chiffre est une information en
soi : s'il est élevé, le problème est à l'inscription, pas dans le cours.

**On ne relance pas deux fois en moins de sept jours**, ni jamais quelqu'un qui
a terminé. Une relance est un service ; deux relances en trois jours font se
désabonner de tout. C'est l'APPLICATION qui tient le délai — pas la mémoire de
qui clique, ni la coordination de deux membres de l'équipe ouvrant le même écran
le même matin. D'où `enrollments.last_reminder_at`.

Et la relance ne culpabilise pas : « reprenez quand cela vous arrange, votre
progression est conservée ». Sur une plateforme dont l'objet est d'apprendre à
résister à la manipulation, jouer sur l'urgence serait particulièrement mal venu.

### Ce qui a été rangé où, et pourquoi

| Fichier | Disque | Pourquoi |
|---|---|---|
| Couverture d'un cours, d'un article | **public** | faite pour être vue — catalogue, partages, aperçus sociaux. La cacher la rendrait invisible là où elle sert |
| Document joint à une leçon | **privé** | c'est la valeur du cours |
| Pièce d'identité (lot 13) | **privé** | c'est la vie privée |

### Le support audio suit exactement la règle de la vidéo

`audio_url` est vidé quand la leçon change de type, comme `video_url` l'était
déjà. Sans cela, une leçon repassée en « texte » garderait une adresse
invisible à l'écran, prête à ressurgir au prochain changement de type. Un test
garde le croisement dans les deux sens.

À noter : l'audio d'une leçon est une **adresse**, pas un fichier déposé.
Héberger la lecture ferait porter la bande passante à un hébergement mutualisé
— celui-là même où aucun démon ne tourne. Les documents joints, eux, sont
déposés : on les télécharge une fois, on ne les diffuse pas en continu.

### Le cliquet PHPStan a rétréci : 93 → 92

Annoter `Cours::rules()` a rendu une entrée du cliquet caduque, et
`reportUnmatchedIgnoredErrors` l'a signalée comme non ignorable. C'est
exactement le dispositif qui fonctionne : une exception qui ne sert plus est une
exception qu'on retire.

Six autres écarts relevés au passage et corrigés, dont une méthode privée morte
dans le composant de statistiques — écrite par distraction, jamais appelée.

### Zéro permission nouvelle

`lesson.view/update`, `course.view/update` existaient. Treizième lot d'affilée.

---

## La passe au navigateur du lot 14 — 2 septembre 2026

Trois défauts réels, et **une erreur de diagnostic de ma part, rectifiée**.

### Défaut 1 — trois chaînes complètes que RIEN n'affichait

Le plus grave, et le même motif trois fois :

- **Les documents joints.** Service, contrôleur, route signée, dépôt depuis le
  constructeur : toute la chaîne existait. **Aucun écran citoyen ne montrait le
  moindre lien.** Une leçon pouvait porter cinq PDF que personne ne pouvait
  ouvrir.
- **Les leçons audio.** Le gabarit citoyen ne connaissait que la vidéo. Un
  enregistrement déposé s'affichait comme… rien du tout.
- **La surcharge SEO d'une actualité du Hub.** `pourFaitVerifie()` composait
  titre et description à partir des seuls champs éditoriaux : le bloc
  « Référencement » enregistrait des valeurs que le service ne lisait pas.

Aucun test ne pouvait les voir : ils éprouvent le stockage, le service et la
garde d'accès — pas le fait qu'un gabarit affiche quelque chose. C'est
exactement le trou que ce chantier a déjà refusé deux fois, et que j'ai recréé
trois fois dans un seul lot.

### L'erreur de diagnostic, et sa rectification

J'avais annoncé, ici et dans la migration, un « défaut latent » :
`Referencement.php` lirait `meta_title` sur `HubArticle` sans que la colonne
existe. **C'était faux.** La ligne citée appartient à
`pourArticleDeBlog(BlogPost $article)`, où la colonne existe bel et bien —
j'avais attribué la ligne à la mauvaise méthode en lisant trop vite un `grep`.

Le fait réel était plus simple et moins spectaculaire : le Hub n'avait
simplement jamais eu de surcharge SEO. Les deux textes ont été corrigés.

### Défaut 2 — trois fautes d'accord, dont deux misgenrent la personne

- « **Marie Akpovi est relancé.** » — l'accord impose un genre qu'on ne connaît
  pas. Devenu « Relance envoyée à Marie Akpovi. »
- « **relancé** il y a 14 minutes » — même problème. Devenu « relance il y a… ».
- « **Les 1 inscrits** restés à 0 % » — accord au nombre. Devenu un
  `trans_choice` à trois branches, dont une pour zéro.

Sur une plateforme francophone, supposer le genre de quelqu'un parce que la
phrase l'exige est un défaut, pas un détail de style. La tournure impersonnelle
l'évite et se lit aussi bien.

### Ce que la passe a CONFIRMÉ

| Éprouvé | Verdict |
|---|---|
| Les huit nouveaux champs de la fiche de cours | Saisis, enregistrés, relus — prérequis et objectifs bien en LISTE |
| Statistiques du cours | **60 % de moyenne** sur (15+45+80+100)/4 : le 0 % est bien écarté ; tri par les moins avancés ; « Rien à relancer » sur celui qui a fini |
| Relance | Toast, notification, courriel, date posée |
| Refus de double relance | « Déjà relancé il y a 4 minutes. On attend 7 jours » |
| Dépôt d'un document | Rangé sur le disque PRIVÉ sous un uuid, nom d'origine gardé pour l'affichage seul, inaccessible en public |
| Type Audio dans le constructeur | Le champ d'adresse remplace bien celui de la vidéo |
| Action « Statistiques » | Présente sur chacune des neuf lignes de cours |

### Un faux défaut, évité de justesse

J'ai d'abord conclu que le toast de refus de relance ne s'affichait pas : mon
sélecteur ne visait pas le bon conteneur (`#vf-toast-host`). Le toast était bien
là. **Un élément que mon sélecteur ne trouve pas n'est pas un élément absent** —
pendant du « clic sans effet » de la passe précédente.

### Sur l'outil, deux notes pour la prochaine fois

- La session du personnel expire au bout de 30 minutes d'inactivité
  (`StaffIdleTimeout`), et une passe longue impose donc de se reconnecter. Le
  code TOTP, lui, ne vit que 30 secondes : il faut le calculer et l'employer
  dans le même souffle.
- Les URL signées sont refusées par l'outil d'automatisation (chaîne de requête
  jugée sensible). Cette garde-là se vérifie par les tests, qui passent par la
  vraie route HTTP.


---

## La prime d'XP de fin de cours — 2 septembre 2026

`courses.xp_reward` existait depuis le lot 14, le mentor la saisissait, et
**rien ne la versait**. Trois chaînes manquaient, pas une.

### 1. Le versement, et la marque qui le rend unique

`ProgressionAcademie::rafraichir()` est l'entonnoir unique de l'avancement : la
prime s'y branche à côté de la délivrance du certificat, sur le même
déclencheur. Mais `rafraichir()` s'exécute **à chaque leçon lue, chaque quiz
corrigé, chaque exercice remis** — et quelqu'un qui relit une leçon après avoir
fini repasse par la branche d'achèvement.

Le quiz, lui, déduit son unicité de `quiz_attempts`. **Un cours n'a pas de
journal équivalent**, et `completed_at` dit quand on a fini, pas si la prime a
été payée. D'où `enrollments.xp_awarded_at`. Sans elle, cinq relectures d'un
cours à 250 XP donnaient 1 250 XP — deux paliers de niveau gagnés en rouvrant
une page. C'est le test qui compte de la nouvelle suite : dix
`rafraichir()` d'affilée, 250 XP au total.

**La marque est posée même quand la prime vaut zéro.** Elle enregistre que le
moment de l'attribution est passé, pas qu'une somme a été versée : un mentor qui
fixe la récompense après coup ne paie donc pas rétroactivement ceux qui avaient
déjà terminé. La prime est celle qui était annoncée quand ils ont suivi le
cours, ce qui est la seule règle explicable — et la seule qui empêche une
inflation d'XP déclenchée par une simple modification de fiche.

### 2. Ce qu'aucun écran n'en disait

Le versement seul aurait été à moitié livré. Le quiz affiche sa prime, le badge
la sienne ; **le cours restait muet des deux côtés** :

| Chaîne | Avant | Maintenant |
|---|---|---|
| La promesse | La récompense n'apparaissait nulle part avant de s'inscrire | Badge `+250 XP à la fin` dans l'en-tête de la fiche |
| L'annonce | « Leçon terminée. » — les 250 XP tombaient en silence | « Cours terminé ! +250 XP. » sur la leçon qui achève le cours |
| Les **prérequis** | Saisis par le mentor depuis le lot 14, affichés nulle part, là où les objectifs l'étaient | Liste dans l'onglet « Aperçu », au-dessus des objectifs |

Une récompense qu'on ne promet pas ne motive personne ; une récompense versée
sans un mot ne se remarque pas. Les prérequis muets, eux, faisaient découvrir à
la troisième leçon qu'il manquait une base.

L'annonce compare la marque **avant et après** le geste, pas son état : sans
cette comparaison, rouvrir la dernière leçon d'un cours déjà fini rejouerait la
félicitation à chaque passage.

### Le même motif, une quatrième fois

C'est la quatrième fois en deux lots qu'une chaîne complète côté serveur
n'atteint aucun gabarit — après les documents joints, les leçons audio et le SEO
du Hub. Les tests de service ne peuvent pas le voir : ils appellent les
composants **par leurs méthodes**. Les trois tests d'affichage ajoutés ici
passent par la route HTTP, et le couple voir / ne pas voir sur la **même
chaîne** est ce qui les rend discriminants.

### Barre

`pint` vert · `phpstan` niveau 6 vert (baseline toujours à 92) ·
**1 745 tests, 4 838 assertions, 1 ignoré, 0 échec** en 8 min 57 sur 4 processus.
L'intermittence de `FinalisationInscriptionTest` n'a pas reparu sur cette passe.


---

## Les deux manques du module Académie — 2 septembre 2026

Les deux étaient du même genre : **une donnée écrite que rien ne lisait.**

### 1. Le journal des XP — le compteur qui ne s'expliquait pas

`app/Models/User.php` plaçait `units`, `xp` et `streak_days` hors `$fillable`
avec cette justification : « l'économie ne s'écrit que par EconomyService, de
façon transactionnelle ». C'était vrai des unités, qui tiennent `unit_logs`
depuis le 20 août. **Ce ne l'était pas de l'XP** : quatre services appelaient
`increment('xp', …)` directement — quiz de cours, quiz d'accueil, badges, et la
prime de fin de cours ajoutée la veille.

On savait donc combien de points un compte possédait, jamais d'où ils venaient.
Une correction de solde était impossible à justifier ; un litige — « j'ai fini
ce cours et je n'ai rien reçu » — impossible à trancher autrement qu'en croyant
l'un ou l'autre.

Le journal est **calqué sur celui des unités**, délibérément : `delta` signé,
`balance_after` pour rendre chaque ligne vérifiable sans rejouer l'historique,
`reason` en valeur d'enum, `label` canonique — donc écrit dans la langue de
repli, parce qu'un journal panaché ne se cherche plus. Deux journaux qui se
ressemblent se relisent avec la même habitude.

Trois points méritent d'être notés :

- **`increment()` composait mal avec un journal.** Il écrit en SQL sans relire,
  donc sans jamais connaître le solde obtenu — et une ligne sans
  `balance_after` ne se vérifie pas.
- **Le verrou n'est pas décoratif.** Finir un cours peut décrocher un badge dans
  la foulée : deux crédits sur le même compte dans la même requête. Sans
  `lockForUpdate`, le second écrase le premier, et le compteur perd des points
  que le journal, lui, aurait enregistrés.
- **Le dépôt ne porte que deux méthodes**, là où celui des unités en porte
  sept. Les sept servent sept écrans ; l'XP n'a qu'un lecteur. Déclarer un
  `journalGlobal()` que rien n'appelle aurait recréé le défaut corrigé quatre
  fois en deux lots — et j'en ai écrit une troisième, `derniers()`, destinée à
  l'activité récente où elle aurait doublé les lignes « Badge débloqué » et
  « Certificat obtenu » déjà présentes. Retirée avant livraison : **la règle
  vaut aussi pour qui l'écrit**, et c'est la cinquième fois en trois lots que ce
  réflexe se rattrape en relecture plutôt qu'à l'écriture.

### La ligne « points antérieurs au journal »

Le journal date d'aujourd'hui ; les comptes ouverts avant ont un total qu'aucune
ligne n'explique. Sans aveu, quelqu'un affichant 1 780 XP verrait une
répartition qui en totalise 250 et conclurait à une perte. L'écart est donc
**affiché sous son propre libellé**, et disparaîtra de lui-même pour les comptes
créés ensuite, sans qu'aucun code ne change.

### 2. La langue du cours — un champ qu'on faisait remplir pour rien

`courses.language` était saisie par le mentor depuis le lot 14 et n'agissait
nulle part. Elle est devenue un filtre de catalogue, sur le patron des trois
voisins : `#[Url]`, donc partageable.

**Le sélecteur n'apparaît qu'à partir de deux langues.** Les neuf cours sont en
français : un menu déroulant dont tous les choix mènent au même résultat occupe
une place, invite à un clic et ne change rien. Il apparaîtra tout seul le jour
où un cours anglophone sera publié.

### Un défaut trouvé par un test que je croyais mal écrit

`filtresActifs()` ignorait la nouvelle langue. Conséquence : filtrer sur
« Anglais » laissait le bloc « Recommandés » proposer des cours français juste
en dessous de la liste filtrée. J'ai d'abord tenu mon assertion pour fautive —
elle ne l'était pas. **Un test rouge est une hypothèse sur le code avant d'être
une erreur de test.**

### Barre

`pint` vert · `phpstan` niveau 6 vert (baseline toujours à 92) ·
**1 763 tests, 4 879 assertions, 1 ignoré, 0 échec** en 7 min 18 sur 4 processus.
17 tests neufs : 10 sur le journal, 7 sur les deux lecteurs.


---

## Formulaires, mot de passe, et la chaîne des courriels — 2 septembre 2026

Quatre chantiers. Les deux premiers n'en faisaient qu'un.

### 1. L'œil et le témoin de force — UNE seule cause pour trois pannes

Le prototype attache ses comportements au `DOMContentLoaded`. C'est juste pour
une page statique, et faux pour un écran Livewire :

| Ce qui était cassé | Pourquoi |
|---|---|
| **L'œil de l'inscription n'a jamais fonctionné** | Le champ vit dans `@if ($etape === 2)`. Au chargement, l'écran affiche l'étape 1 : `getElementById('reg-password')` ne trouve rien, et l'écouteur n'est jamais posé. |
| **Le témoin de force non plus** | Même cause — et il visait de surcroît `#reg-password2`, l'identifiant du prototype, quand la transposition nomme le champ `#reg-password-confirm`. |
| **L'œil de la connexion se désynchronisait** | Le morph Livewire recompare le HTML du serveur (`type="password"`) au DOM vivant (`type="text"`) et remet le masque, en laissant `aria-pressed="true"`. Après une validation de champ, l'icône disait « visible » devant un mot de passe masqué. |

Trois réponses, dans `public/assets/js/auth-forms.js` : **délégation** sur
`document`, **ré-application après chaque morph** (`aria-pressed` devient la
source de vérité, le `type` s'aligne dessus), et un **attribut distinct**
(`data-pwd-eye`) pour que le gestionnaire du prototype n'attrape jamais nos
boutons. Deux écouteurs sur un même bouton, ce serait deux bascules par clic —
donc aucun effet visible, le défaut le plus pénible de la famille.

**Quatre champs sur six n'avaient aucun œil** : la confirmation d'inscription et
les deux champs de la réinitialisation. C'est pourtant là qu'il sert le plus :
on ne relit pas un mot de passe qu'on connaît, on relit celui qu'on invente.
Un composant `<x-champ-mot-de-passe>` remplace les six copies.

### Le témoin de robustesse

Le contrat demandé est respecté — barème à quatre familles, paliers
25/50/75/100, événements `onScore0` à `onScore4`. Trois écarts, assumés :

- **`input` et non `keyup`.** Un mot de passe COLLÉ, ou versé par un
  gestionnaire de mots de passe, n'émet aucun `keyup` : la barre serait restée
  vide devant un mot de passe excellent — le cas qu'on veut précisément
  encourager.
- **Les couleurs de la charte**, et non le dégradé orange → vert saturé de
  l'implémentation de référence. Le rouge et le vert sont ceux des verdicts
  « faux » et « vrai » : qui a lu un rapport connaît déjà ce code.
- **Idempotence.** L'étape 2 apparaît et disparaît ; sans elle, chaque passage
  empilait une barre et un écouteur de plus.

### 2. Diffuser une infolettre — arbitrage 156 RENVERSÉ

L'écran portait un refus explicite : « un bouton envoyer maintenant derrière un
simple clic expédierait plusieurs milliers de courriels sans retour possible ».
L'argument reste juste — il condamnait le **simple clic**, pas la
fonctionnalité. La diffusion est ouverte avec quatre gardes, une par morceau de
l'objection :

1. **Une confirmation qui annonce le nombre** — « sera envoyé à 25 abonnés ».
   C'est ce chiffre qui fait hésiter, pas un avertissement.
2. **La permission `newsletter.send`**, distincte de `newsletter.view` : la
   matrice les séparait déjà, l'écran n'avait rien à refléter.
3. **La file**, pas la requête : le départ ne dépend pas d'un onglet resté
   ouvert, et le job reprend où il s'est arrêté.
4. **Le journal d'audit**, avec l'objet et le nombre de destinataires.

L'idempotence n'est pas réécrite : `reserverEnvoi()` insère la ligne de
livraison AVANT l'envoi, donc deux exécutions ne peuvent pas servir deux fois la
même adresse. Un test le tient en rejouant le job.

### 3. Les gabarits d'emails — l'écran tenait la moitié de sa promesse

`email_templates` portait `key` et `subject`. **Rien d'autre.** On pouvait donc
changer la ligne lue dans la boîte de réception, et rien du message qu'on ouvre :
le corps vivait dans un gabarit Blade. Un écran nommé « Gabarits d'emails » qui
ne laisse pas toucher au corps promet plus qu'il ne tient — et le rédacteur qui
vient corriger une formulation repart en écrivant au développeur.

Ce qui a été posé :

- **`CatalogueDesCourriels`** — 24 événements, chacun avec son déclencheur réel,
  ses variables, et **deux drapeaux qui comptent** : `porte` (est-ce câblé ?) et
  `verrouille` (peut-on l'éteindre ?).
- **`RenduDeGabarit`** — objet, titre, corps, bouton, interrupteur. Substitution
  de `{{ marqueur }}`, et **retrait** de ceux qui restent : « Bonjour
  {{ prenom }}, » est pire que « Bonjour, », et il part tel quel dans toutes les
  boîtes.
- **Le thème `verifall`** — `config/mail.php` n'avait AUCUNE clé `markdown`,
  donc tout partait sous le thème du framework : gris ardoise, bouton bleu. Un
  démenti reçu sous une identité générique perd ce qui le rend croyable.
- **Le semis des 24 textes**, idempotent : il ne remplit que les champs VIDES,
  donc rejouable en production sans effacer une correction.
- **Le CRUD**, avec la table demandée — Événement, Sujet, Variables, Dernière
  modif., Actions — plus une colonne que l'écran DOIT porter : **« Pas encore
  câblé »**.

### Trois décisions qui méritent d'être dites

**Le corps est en Markdown, pas en HTML libre.** Un courriel part hors de
l'application : aucun filtre ne rattrapera une balise mal fermée, et les clients
de messagerie retirent la moitié du HTML sans prévenir. L'habillage reste au
gabarit de mise en page, donc identique partout.

**Les bavards sont livrés ÉTEINTS.** Badge débloqué, réaction reçue, alerte
intox, nouveau cours : déclarés, mais à `false`. Un courriel par réaction sur un
message de forum est ce qui fait cliquer sur « se désabonner de tout », et
emporte ensuite les messages qui comptent. Les transactionnels, eux, sont
**verrouillés** : un code à usage unique qu'un réglage peut couper est un compte
qu'on ne peut plus créer.

**Les gabarits non câblés sont annoncés à l'écran.** Huit des vingt-quatre ne
sont pas encore expédiés. Un texte qu'on peut soigner sans que rien ne l'envoie
est exactement le piège corrigé quatre fois sur ce chantier ; le taire sur
l'écran qui prétend tout lister l'aurait recréé une cinquième.

### Ce qu'un test a corrigé contre moi

`reinitialiser` supprimait la ligne — c'était juste tant que seul l'objet était
surchargeable, « pas de surcharge » se disant par l'absence de ligne. Ça ne l'est
plus depuis que la ligne porte le corps : la supprimer laisserait le courriel
sans texte du tout. La ligne existe désormais toujours, et c'est `updated_by`
qui dit si un être humain y a touché.

### Le troisième piège Blade du chantier

`{{ '{{ '.$variable.' }}' }}` ne compile pas : le compilateur ferme l'écho sur
le `}}` intérieur. Les marqueurs sont composés dans le composant. À ranger avec
les deux autres — `@php(...)` et `@php…@endphp` qui ne coexistent pas, et
`{{-- --}}` invalide dans un bloc `@php`.

### Trois défauts trouvés par les gardes du dépôt, pas par moi

La suite complète a refusé le lot trois fois, et chaque refus était juste :

- **`ClassesTailwindPresentesTest`** a attrapé `text-[#CBD5E1]`,
  `text-[#92400E]` et `border-[#CBD5E1]` — des couleurs inventées, absentes du
  build figé. Les formulaires seraient partis **sans bordure, en silence**.
  Transposés sur `.ffield`, le patron de tous les autres écrans staff ;
  l'avertissement réemploie `st-badge-warn` plutôt qu'un ambre neuf.
- **`ChecklistSecuriteTest`** a refusé les deux `{!! $corps !!}` des gabarits de
  courriel. J'avais écrit en commentaire que « la garde se joue à l'écriture » :
  c'est vrai de la base, faux du message. **Un courriel part hors de
  l'application, là où plus rien ne le rattrape.** Les deux vues purifient.
- Le test d'API tombait en cascade sur l'erreur de vue ; il passe seul.

**L'ordre s'est révélé décisif** sur le second point : purifier le Markdown brut
encode le `>` des citations en `&gt;`, et le motif d'un refus KYC serait devenu
une ligne commençant par une entité. Le mailable convertit d'abord en HTML, la
vue purifie ensuite. Vérifié sur un rendu réel — `<blockquote>` conservé, vert
de la charte présent, bouton présent, aucun marqueur nu.

### Une bizarrerie d'outil, à connaître

`php artisan test --parallel` a rendu **le code de sortie 0 avec trois tests en
échec**. Une supervision qui ne lit que le code de sortie aurait conclu au vert.
Sur ce dépôt, il faut lire la ligne `Tests:`.

### Barre

`pint` vert · `phpstan` niveau 6 vert, baseline toujours à 92 ·
**1 799 tests, 4 958 assertions, 1 ignoré, 0 échec** sur 4 processus ·
24 gabarits semés, 24 déclarés, **15** réellement câblés.

> Le compte annoncé d'abord — « 16 câblés, 8 à faire » — était faux d'une
> unité dans les deux sens. Recompté sur le fichier : **15 et 9**.


---

## Câblage de quatre courriels — 2 septembre 2026

Quatre des envois déclarés partent désormais réellement : **cours terminé**,
**badge débloqué**, **unités créditées**, **contre-expertise tranchée**.
Le catalogue passe de 15 câblés à 19.

### Ce que l'inventaire a démenti

L'annonce parlait de « six mécaniques ». Il y en avait **quatre**.

`compte_suspendu` et `compte_retabli` ne sont pas câblables, et pas faute de
déclencheur mal placé : **la colonne de suspension n'existe pas**.
`Staff\Utilisateurs` le documente lui-même — il crée un compte, assigne un rôle
et liste, rien de plus, et son propre commentaire dit que « la suspension demande
une colonne qui n'existe pas ». Ma ligne de `RESTE_A_FAIRE`, qui affirmait que
« la modération change le statut sans notifier », était fausse : la modération ne
change rien du tout. Les deux courriels attendent l'écran, pas l'inverse.

### Le garde-fou qui manquait : la préférence individuelle

`notification_preferences` existe depuis le 20 août — quatre interrupteurs par
compte. L'écran d'administration, lui, allume ou éteint un envoi pour toute la
plateforme. **Les deux ne disent pas la même chose**, et les confondre ferait
qu'un réglage d'administration écrase un choix individuel — ce qui, sur un envoi
non transactionnel, est précisément ce que le RGPD interdit.

`ExpediteurDeCourriels` applique donc les deux, dans cet ordre : le global
d'abord (une lecture en cache), le personnel ensuite. Quand aucune préférence ne
correspond — une prime d'unités, un badge — on passe `null` et seul le global
s'applique. Inventer une cinquième préférence aurait créé un réglage qu'aucun
écran n'affiche, ce qui est pire qu'un réglage absent.

### Trois décisions d'envoi

| | Décision | Pourquoi |
|---|---|---|
| **Crédit d'unités** | Les crédits seulement, jamais les débits | Un débit, c'est une vérification qu'on vient de lancer soi-même, écran ouvert. L'écrire ferait un courriel par usage du produit. |
| **Bonus de bienvenue** | EXCLU du courriel d'unités | Il est déjà annoncé par le message de bienvenue, qui part au même instant. Deux messages en une seconde pour dire la même chose apprend à nous filtrer. |
| **Badge** | Câblé, livré ÉTEINT | Le câblage existe pour que l'administration puisse en décider ; il n'est pas le défaut. |

### Hors transaction, systématiquement

`Mail::queue()` écrit dans la table `jobs`, **qui participe à la transaction en
cours**. Un `queue()` à l'intérieur laisserait, après un rollback, un courriel
annonçant un cours qui n'est plus terminé ou des unités que le compte n'a jamais
reçues. Les deux envois concernés partent donc après le `commit`. L'inverse — un
courriel perdu sur un fait bel et bien acquis — est le moindre mal.

### Le témoin de la félicitation

`completed_at` reste vrai indéfiniment ; `xp_awarded_at` n'est posé qu'une fois.
C'est donc lui, relevé AVANT la transaction, qui distingue « ce cours vient de
s'achever » de « ce cours est achevé ». Sans ce relevé, rouvrir la dernière leçon
renverrait la félicitation à chaque passage — le test l'exige sur cinq appels
consécutifs.

### Un défaut réel, attrapé par un test

`route('citizen.certificate', ['course' => $cours])` levait une
`UrlGenerationException` : la route attend un **certificat**, et
`delivrer()` rend `null` dès qu'un cours ne diplôme pas. Ce n'est pas le
courriel qui aurait échoué — **c'est la fin de la leçon**, l'exception partant
depuis `rafraichir()`. Le lien pointe désormais sur le certificat quand il
existe, sur la fiche du cours sinon.

### Ce qui reste : cinq, chacun pour une raison nommée

| Clé | Ce qui manque |
|---|---|
| `compte_suspendu`, `compte_retabli` | La colonne de suspension, et l'écran qui va avec. |
| `alerte_intox` | Un moyen de marquer un démenti « urgent » à la publication. |
| `nouveau_cours` | Une décision de produit : quelle audience ? Tous les inscrits, ou les seuls abonnés ? |
| `reaction_recue` | Choix éditorial — le déclencheur existe, mais un courriel par réaction est ce qui fait filtrer tout le reste. |

Un test tient la liste dans les deux sens : ce que le catalogue annonce comme
câblé, et ce qu'il annonce comme ne l'étant pas. La colonne « Pas encore câblé »
de l'écran ne peut donc plus mentir dans un sens ni dans l'autre.


---

## Les deux derniers blocages produit, tranchés — 2 septembre 2026

`nouveau_cours` et `alerte_intox` n'attendaient pas du code : elles attendaient
une **décision d'audience**. Les deux sont prises, et écrites là où elles
s'appliquent — dans le docblock du job qui expédie, pas dans un fichier de
spécification que personne ne rouvre.

Le catalogue passe de 19 câblés à **21 sur 24**.

### « Nouveau cours » → les citoyens déjà inscrits à un cours

Deux audiences ont été écartées, chacune pour une raison distincte :

- **Tous les comptes.** Annoncer un cours à quelqu'un qui n'a jamais ouvert
  l'Académie est de la prospection, pas un service. Le premier message de ce
  genre est toléré ; le troisième fait perdre le compte.
- **La liste d'infolettre.** Son consentement porte sur les fact-checks
  hebdomadaires. Y verser des annonces de cours étendrait un consentement à un
  objet qu'il ne couvre pas — exactement le reproche qu'on adresse aux
  plateformes qu'on démystifie.

Une inscription antérieure est un signal d'intérêt donné par la personne
elle-même : c'est le fondement le plus solide dont on dispose sans lui poser une
question de plus. La préférence « rappels d'apprentissage » s'applique par-dessus,
et l'envoi reste **éteint par défaut** — l'audience dit qui recevrait, pas qu'il
faille envoyer.

`whereExists` et non une jointure : une jointure aurait rendu un compte autant
de fois qu'il a d'inscriptions, et la personne aurait reçu l'annonce une fois par
cours suivi.

### « Alerte intox » → les abonnés de l'infolettre, par la machinerie de campagne

Même liste, même sujet, **cadence exceptionnelle** : au lieu du lundi, tout de
suite. Étendre à tous les comptes aurait transformé un service demandé en
notification imposée.

Cette décision en entraîne une seconde, techniquement : l'alerte ne peut pas
passer par le mailable générique, qui s'adresse à un compte et n'a donc ni lien
de désabonnement ni en-tête `List-Unsubscribe`. Un envoi de masse sans lien de
retrait est une infraction — et le seul geste restant à qui n'en veut plus
serait « signaler comme indésirable », que la réputation du domaine paierait
jusque sur les codes de connexion. `AlerteIntox` emprunte donc la forme de
`DiffusionInfolettre` et son CONTENU au gabarit : objet, titre et corps restent
modifiables depuis l'administration.

### Le marqueur d'urgence : une case, pas une catégorie

`hub_articles.is_urgent`. La distinction n'est pas cosmétique : **la catégorie
décrit le SUJET** (santé, politique), **l'urgence décrit la CIRCULATION**. Une
intox sur la santé n'est pas urgente parce qu'elle parle de santé ; elle l'est
parce qu'elle se propage maintenant.

Séparée aussi de « à la une » : mettre en avant sur le site et écrire à trois
mille personnes ne sont pas le même engagement.

À l'écran, la case est annoncée **pour ce qu'elle fait** — « un courriel part à
tous les abonnés de l'infolettre » — et non pour ce qu'elle est. Une case nommée
« Urgent » sans plus d'explication se coche par réflexe ; celle-ci dit son prix.

### Trois garde-fous qui se ressemblent, et ne font pas la même chose

| Garde | Ce dont il protège |
|---|---|
| `announced_at` / `alerted_at` | Du **geste humain** : on dépublie pour corriger une leçon, on republie une heure après. Sans marqueur, la troisième correction du mentor est le troisième courriel de la journée. |
| `reserverEnvoi()` | De la **reprise technique** : à l'intérieur d'un même envoi, une adresse ne peut pas être servie deux fois. |
| L'interrupteur relu **dans le job** | De l'**écart de temps** : entre la mise en file et l'exécution, l'administration a pu éteindre l'envoi. Sur le message le plus intrusif du produit, on relit plutôt que de faire confiance à la décision prise au clic. |

Les marqueurs sont posés **avant** la boucle, jamais après. Posés après, un job
interrompu au milieu — timeout, redémarrage — serait rejoué depuis le début et
servirait deux fois les premiers destinataires. Posés avant, une interruption
prive au pire les derniers d'une annonce : le moindre mal, et de loin.

### Un défaut trouvé par un test

`(string) $cours->level` levait `Object of class NiveauCours could not be
converted to string` : la colonne est castée en enum. Corrigé en
`$cours->level->libelle()` — ce qui règle au passage un second défaut que le cast
masquait, puisque le courriel aurait annoncé « beginner » à quelqu'un qui lit en
français.

### Ce qui reste : trois, et deux d'entre eux attendent la même chose

`compte_suspendu` et `compte_retabli` attendent la **colonne de suspension**, qui
n'existe pas — et l'écran qui va avec. `reaction_recue` est câblable dès demain,
mais reste un choix éditorial : un courriel par réaction sur un message de forum
est ce qui fait filtrer tout le reste.

Un test tient la liste des non-câblés à l'unité près. La colonne « Pas encore
câblé » de l'écran ne peut mentir ni dans un sens, ni dans l'autre.


---

## L'intermittence des tests, diagnostiquée — 2 septembre 2026

Trois fois consignée, jamais expliquée : `ProfilTest` le 24 août,
`FinalisationInscriptionTest` puis `AuthentificationApiTest` le 2 septembre.
Toujours la même forme — un test qui vérifie un rôle après une inscription,
rouge en parallèle, **vert dès qu'on rejoue le fichier seul**.

### La cause

`phpunit.xml` fixe `CACHE_STORE=array`. Le cache vit donc **dans le processus
PHP**, ni en base ni sur disque. Or paratest enchaîne des dizaines de fichiers de
test dans le même processus, et `LazilyRefreshDatabase` remet la base à zéro
entre eux — **sans toucher au cache**.

Spatie range rôles et permissions sous une seule clé, `spatie.permission.cache`,
avec leurs identifiants numériques. Un test qui sème les rôles remplit ce cache ;
le suivant, dans le même processus, hérite d'identifiants qui ne désignent plus
rien depuis que la base a été vidée. D'où un `hasRole()` qui répond faux sur un
rôle pourtant assigné, ou un `ModelNotFoundException`. Et rien du tout en
isolation, puisque le processus repart vierge.

Ce n'est donc pas « paratest partage un état », comme le supposait la note
d'origine : c'est **le cache applicatif qui survit à une base qu'on vide sous
lui**. La nuance compte, parce qu'elle désigne le correctif.

### Le correctif

`forgetCachedPermissions()` dans le `setUp()` de `tests/TestCase.php`. Une
écriture de tableau par test ; l'absence coûtait une demi-journée d'enquête à
chaque réapparition.

**Une passe verte ne prouve rien** sur un défaut intermittent par nature : la
suite était déjà passée deux fois entre deux occurrences. Ce qui est acquis,
c'est le mécanisme — il s'explique entièrement, et le correctif s'adresse
exactement à lui.

### Barre

`pint` vert · `phpstan` niveau 6 vert, baseline toujours à 92 ·
**1 818 tests, 4 983 assertions, 1 ignoré, 0 échec** sur 4 processus ·
**21 courriels câblés sur 24**.


---

## « Une réaction sur votre message » — 2 septembre 2026

Le dernier courriel câblable, et **le plus facile à rendre insupportable**. Le
catalogue passe à **22 câblés sur 24**.

### Deux façons de noyer quelqu'un, pas une

Le réflexe est de se protéger du va-et-vient : on aime, on se ravise, on réaime
— trois courriels pour un seul avis. C'est réel, et ce n'est pas le pire.

**Le pire est le billet qui décolle.** Cinquante personnes réagissent en une
heure, l'auteur reçoit cinquante courriels, et il désactive TOUTES ses
notifications d'un coup — y compris celles qui comptaient. C'est le mode de
défaillance classique de cette fonctionnalité, et il ne se répare pas après.

Le garde-fou est donc **un repos par CONTENU**, pas par personne :
`threads.reaction_notified_at` et `replies.reaction_notified_at`, six heures. Un
seul message par sujet, quel que soit le nombre de gens qui réagissent — et le
va-et-vient est réglé du même coup, sans règle supplémentaire.

**Pas sur `content_likes`** : la ligne est SUPPRIMÉE quand on retire son
« j'aime ». La marque doit survivre au geste qu'elle limite.

### La marque n'est posée QUE si le courriel est parti

Détail qui n'en est pas un. La poser dans tous les cas ferait qu'un envoi éteint
au catalogue — et il l'est par défaut — consommerait quand même le repos : le
jour où l'administration l'allume, les six premières heures seraient muettes
sans raison visible. C'est le genre de défaut qu'on ne trouve qu'en production ;
un test le tient.

### Les quatre gardes

| Garde | Ce qu'elle évite |
|---|---|
| Seulement à la création | Se raviser n'est pas un événement dont on prévient qui que ce soit. |
| Jamais à soi-même | Aimer son propre billet est courant. On ne s'écrit pas pour se l'annoncer. |
| Un repos par contenu | Le billet qui décolle. |
| La préférence « activité du forum » | C'est exactement ce que cet interrupteur désigne ; l'ignorer en ferait un réglage décoratif. |

Et, comme les autres : **hors transaction**. `Mail::queue()` écrit dans `jobs`,
qui participerait au `commit` du « j'aime ».

### Ce qui reste : deux, et ils attendent la même chose

`compte_suspendu` et `compte_retabli`. La colonne de suspension n'existe pas, et
l'écran non plus — c'est le module utilisateurs du lot 7. **Le courriel attend
l'écran, pas l'inverse.**

### Barre

`pint` vert · `phpstan` niveau 6 vert, baseline toujours à 92 ·
**22 courriels câblés sur 24**.


---

## La suspension de compte — 2 septembre 2026

Les deux derniers courriels attendaient une colonne. **24 câblés sur 24.**

### Trois déclarations, aucun stockage

`MatricePermissions` déclarait `user.suspend` depuis l'origine. Le catalogue
déclarait `compte_suspendu` et `compte_retabli`. `Staff\Utilisateurs` écrivait
noir sur blanc que « la suspension demande une colonne qui n'existe pas ». Trois
endroits parlaient d'une fonctionnalité qui n'existait nulle part.

### Une suspension qui ne suspend pas n'est pas une suspension

C'est tout le sujet de ce lot. Poser une date en base ne bloque rien : la
personne reste connectée, continue de publier, et découvre la sanction en lisant
son courriel. Le geste n'est complet qu'à quatre conditions, et
`SuspensionDeCompte` les tient ensemble : poser l'état, **fermer les sessions
ouvertes**, journaliser, prévenir.

La fermeture des sessions est la moins évidente et la plus importante. Sans
elle, la modération croit avoir agi pendant que la personne publie encore.

### Le refus se DIT, et cela ne crée pas d'énumération

Deux mauvaises réponses possibles, et il fallait éviter les deux :

- **Taire la suspension** enverrait la personne réinitialiser un mot de passe
  qui n'est pas en cause, échouer encore, puis écrire au support en colère. La
  sanction devient une panne, de son point de vue.
- **L'annoncer au premier champ** ferait du formulaire de connexion un annuaire
  des inscrits.

La sortie tient en un mot : **l'ordre**. `CompteSuspendu` n'est levée
qu'**après** la vérification du mot de passe. Le message nommé n'apparaît donc
qu'à quelqu'un qui connaît déjà le mot de passe — il n'apprend rien. Un compte
inconnu, un mot de passe faux ou un compte jamais activé continuent de rendre la
même réponse indistincte. Deux tests tiennent les deux branches.

`estSuspendu()` est volontairement **séparé** de `estActive()` : les fondre
aurait fait hériter la suspension du comportement muet réservé aux comptes non
activés, où le silence est justement la bonne réponse.

### Côté personnel, le refus reste muet

Choix inverse, et assumé : un membre du personnel suspendu l'apprend par son
responsable, et l'écran d'administration est celui où l'on refuse de distinguer
les causes d'échec — il est exposé, et chaque nuance y devient un indice.

### Sixième chaîne muette du chantier

`compte_inactif` était posé en session par le middleware **depuis l'origine**,
et affiché nulle part. La personne était renvoyée à la connexion sans un mot et
réessayait le même mot de passe. Corrigé en même temps que le nouveau drapeau —
deux bandeaux distincts, parce que l'écran n'a pas la même chose à dire à qui
n'a jamais validé son code qu'à qui vient d'être sanctionné.

### Ce que la Policy refuse, en plus du droit

`user.suspend` ne suffit pas. `UserPolicy::suspend()` refuse aussi **soi-même**
— c'est la ligne du dessus dans une liste triée, et le geste se ferait depuis un
compte qu'on vient de fermer — et **un compte du personnel** quand on ne détient
pas `role.manage` : sans cette borne, quiconque modère les citoyens pourrait
fermer le compte d'un administrateur. Le service pose la même garde sur le cas
« soi-même » ; le doublon est voulu, la Policy protège l'écran et le service
protège l'appel.

### Deux défauts trouvés en chemin

- **`LazyLoadingViolationException`** sur `auteurDeLaSuspension` : le dépôt
  interdit le chargement paresseux. La liste aurait levé au premier compte
  suspendu — jamais en développement, toujours en production. Relation chargée
  explicitement.
- **Un docblock orphelin** : mes méthodes se sont insérées entre
  `@param list<string> $rolesDemandes` et la méthode qu'il documentait. PHPStan
  l'a vu immédiatement ; à l'œil nu, personne.

### Un test refait, parce qu'il m'a lâché trois fois

Deux assertions énuméraient à la main les clés câblées et les autres. Elles ont
dû être corrigées **à chaque lot** — trois fois — pour la même raison : une liste
recopiée dans un test est une seconde source de vérité, et elle diverge dès qu'on
touche à la première. Depuis que les vingt-quatre sont câblés, l'énumération
était en plus devenue tautologique : elle répétait `CatalogueDesCourriels::cles()`.

Remplacées par une garde qui dit la RÈGLE plutôt que son résultat : « aucun envoi
déclaré n'est laissé sans câblage ». Elle ne se périme pas, et elle est plus
forte — un vingt-cinquième événement à `porte: false` la fera échouer, ce qui
obligera à le câbler ou à écrire pourquoi on ne le fait pas.

Le test d'écran qui exigeait le badge « Pas encore câblé » a été inversé, en
disant dans son propre commentaire qu'il attendait l'inverse la veille et qu'il
avait alors raison.

### Barre

`pint` vert · `phpstan` niveau 6 vert, baseline toujours à 92 ·
**1 838 tests, 5 022 assertions, 1 ignoré, 0 échec** sur 4 processus ·
**24 courriels déclarés, 24 câblés**. La colonne « Pas encore câblé » de l'écran
des gabarits n'a plus une seule ligne à afficher.

L'intermittence du cache de permissions n'a pas reparu — troisième passe verte
depuis le correctif. Toujours insuffisant pour la déclarer morte, mais le
faisceau s'épaissit.


---

## L'espace mentor, et Statistiques Globales — 2 septembre 2026

### Ce que l'inventaire a démenti, deux fois

Le relevé de départ annonçait « six écrans mentor manquants » et « Statistiques
Globales sans route ». **Les deux étaient faux, et pas dans le même sens.**

- **Deux des six existaient déjà.** « Mes Cours » est `staff.courses`, « Sections
  & Leçons » est `staff.course-builder` — aucune table `sections` n'existe au
  modèle. Il n'y avait pas d'écran à écrire : il y avait un **cadrage** à poser.
- **Statistiques Globales a une route depuis longtemps** : `staff.exam-stats`,
  dont le composant s'appelle littéralement « Statistiques Globales » et qui
  porte déjà ce libellé au menu. La comparaison par nom de fichier
  (`stats-globales.html`) ne l'avait pas vu.

La leçon vaut d'être notée : **comparer des noms de fichiers de prototype à des
noms de routes produit des faux manques.** Le seul relevé qui a tenu est celui
des permissions orphelines — parce qu'il part du code, pas des étiquettes.

### Le cadrage qui manquait vraiment

`CoursePolicy::update()` empêchait un mentor de modifier le cours d'un collègue.
**Mais la liste les affichait tous** : il voyait les inscrits, la note et le
tarif de cours qui ne le regardent pas, et découvrait le refus au clic. Une
Policy tranche sur UN modèle ; elle ne sait pas filtrer une requête. Le filtre a
donc été posé dans la liste, sur le même discriminant que la Policy
(`course.publish`).

### Quatre écrans, et deux permissions qui attendaient depuis l'origine

`student.view` et `student.progress` étaient attribuées au rôle Mentor et
**employées nulle part**. Elles gardent enfin quelque chose.

| Écran | Ce qu'il répond |
|---|---|
| **Mes Étudiants** | Qui est inscrit, où il en est, quand il est passé |
| **Progression** | Qui décroche et où : score de quiz, état, relance, export |
| **Performance** | Ce que chaque cours produit |
| **Mes contenus** | Ce que j'ai produit, et ce qui traîne en brouillon |

### Le cloisonnement vit dans le service, pas dans les écrans

Quatre écrans partagent la même question de départ : « quels cours
m'appartiennent ? ». Répéter ce filtre dans chacun garantirait qu'un jour l'un
des quatre l'oublie. **Une fuite de ce genre ne fait aucun bruit** : l'écran
s'affiche, les chiffres sont plausibles, et personne ne remarque qu'ils
appartiennent à quelqu'un d'autre. Tout part donc de `EspaceDuMentor`.

Le discriminant est le **droit** (`user.view`), jamais la présence de données :
le piège aurait été de se dire « il n'a aucun cours, donc montrons tout ». Un
test tient exactement ce cas.

### Trois choses refusées, et pourquoi

- **La colonne « Tendance »** du prototype, sur Performance : aucune série
  temporelle ne la soutient. Une flèche verte inventée est pire qu'une colonne
  absente.
- **Un statut « décroché » en base** : dérivé à l'affichage. Stocké, il devient
  faux le jour où l'on change le seuil, et il faut le recalculer sur toute la
  table.
- **Fusionner « Mes Étudiants » et « Progression »** : mêmes données, questions
  différentes. Les fondre donnerait un écran qui répond mal aux deux.

### Statistiques Globales : trois écarts, pas un écran

L'écran existait ; trois choses lui manquaient face au prototype, et toutes trois
ont été posées : **Candidats** et **Réussite** par épreuve, et le KPI
**Certificats d'examen**.

Le nombre de candidats est mis à côté de la note moyenne, délibérément : une
moyenne de 82 % sur trois copies et sur trois cents ne se lisent pas de la même
façon. Les certificats sont comptés sur les COPIES réussies et non sur la table
des certificats, qui mêle diplômes de cours et d'examen.

### Deux défauts trouvés par les tests

- `route('staff.course-stats', ['course' => …])` : la route attend `slug`. Le
  lien du « Rapport de cours » aurait levé au premier affichage.
- PHPStan a refusé `$ligne->course_id` sur `QuizAttempt` : la colonne vient
  d'une jointure et n'existe pas sur le modèle. Lire une agrégation depuis un
  modèle marche à l'exécution et ment au typage — les deux requêtes sont passées
  en `DB::table()`. **Une agrégation n'est pas une entité.**


### Un troisième défaut, celui qui a fait tomber la suite entière

`inscritA()` existait déjà dans `RediffusionTest`. **Les fonctions d'aide de Pest
vivent dans l'espace de noms global**, partagé par toute la suite : le fichier
passait seul, et la suite complète tombait sur une `FatalException` avant même
d'avoir compté un test.

Deux enseignements, notés parce qu'ils se reproduiront :

- **Un fichier de test vert en isolation ne prouve rien de la suite.** Le
  contraire de l'intuition, et c'est la deuxième fois sur ce chantier — après
  l'intermittence du cache de permissions.
- **Le code de sortie de `php artisan test` ne survit pas à un tube.**
  `test | grep` rend le code de `grep`, donc toujours 0. La suite était rouge et
  la commande annonçait vert. Ce n'est qu'en écrivant la sortie dans un FICHIER,
  puis en lisant `$?`, que l'échec est apparu.

`grep -rhoE "^function [a-zA-Z_]+" tests/ | sort | uniq -d` liste les collisions
en une seconde. À passer avant d'ajouter un fichier de test qui déclare des
aides.

### Barre

`pint` vert · `phpstan` niveau 6 vert, baseline toujours à 92 ·
**1 849 tests, 5 049 assertions, 1 ignoré, 0 échec** en 9 min 12 sur 4 processus.


---

## Le cliquet des permissions orphelines — 2 septembre 2026

Neuf permissions déclarées, attribuées à un rôle, et employées **nulle part**.
Une telle permission est pire qu'une permission absente : on la retire à
quelqu'un en croyant lui retirer un accès, et on ne lui retire rien.

### Un cliquet à cinq entrées n'aurait été qu'une liste d'exemptions

L'action annoncée était « le cliquet contre les cinq orphelines ». Poser le
cliquet d'abord aurait figé les cinq. Chacune a donc été examinée, et **sept sur
neuf se sont refermées** — il en reste deux.

| Permission | Ce qui se passait | Décision |
|---|---|---|
| `mentor.view`, `mentor.manage` | `staff.mentors` se gardait sur `user.view` / `user.update` | **Adoptées.** Promouvoir un mentor n'est pas modifier un compte ; la matrice le prévoyait, le code l'ignorait. Aucun changement d'accès : l'Admin détient les deux. |
| `system.settings`, `system.certificate_template` | Les deux écrans se gardaient sur la Policy `Setting` (`setting.manage`) | **Adoptées en plus.** La Policy garde le MODÈLE, la route garde l'ÉCRAN — ce ne sont pas les mêmes questions. |
| `signalement.view` | Rien | **Adoptée**, et elle a révélé un défaut — voir ci-dessous. |
| `signalement.publish`, `signalement.delete` | Rien | **Tolérées**, avec leur raison : elles attendent un écran qui n'existe pas. |

### Le défaut que `signalement.view` a révélé

Le panneau « Dernières activités de fact-checking » du tableau de bord
s'affichait **sans aucune condition**. La file des vérifications citoyennes —
l'affirmation soumise, et par qui — était donc visible d'un mentor ou d'un
rédacteur, qui n'ont rien à y faire.

C'est le genre de fuite qui ne fait aucun bruit : le panneau s'affiche, les
données sont réelles, et personne ne se demande qui les regarde. La permission
existait exactement pour cela depuis l'origine.

### Ce que le cliquet tient, et qu'une simple liste ne tiendrait pas

Trois assertions, et la deuxième est celle qui empêche la liste de se figer :

1. **Aucune orpheline NOUVELLE.** Une permission déclarée sans garde fait
   échouer la suite.
2. **Aucune orpheline REFERMÉE ne reste dans la liste.** Le cliquet mord dans
   les deux sens : sans cela, la liste garde sa taille d'origine et cesse de
   dire quoi que ce soit — c'est ce qui arrive à toutes les listes d'exemptions
   qu'on n'oblige pas à maigrir.
3. **Chaque entrée porte une RAISON écrite**, d'au moins vingt caractères. Une
   liste sans motifs se transforme en liste qu'on allonge : ajouter une ligne ne
   coûte rien si l'on n'a pas à la justifier.

Une quatrième garde le protège de lui-même : une entrée qui ne correspond plus à
aucune permission déclarée fait échouer. Elle ne protégerait rien tout en donnant
l'illusion que le sujet est suivi.

### Le balayage, et l'erreur qu'il corrige

Il cherche la MENTION du nom, quelle que soit sa forme — `'p'`, `"p"`, `can:p`,
`@can('p')`. Le premier relevé ne cherchait que la forme entre apostrophes et
avait annoncé **douze** orphelines là où il y en avait neuf : les gardes de
route (`can:kyc.view`) lui échappaient. Le test livré n'a pas cette faiblesse.


### Barre

`pint` vert · `phpstan` niveau 6 vert, baseline toujours à 92 ·
**1 852 tests, 5 056 assertions, 1 ignoré, 0 échec** (`exit=0` lu sur un fichier,
pas derrière un tube) · **127 permissions déclarées, 2 orphelines** contre 9 le
matin même.


---

## La file des vérifications citoyennes — 3 septembre 2026

Le dernier écran que la matrice promettait sans le tenir. **127 permissions
déclarées, zéro orpheline.**

### Ce que l'écran débloque, au-delà des deux permissions

Aucune page ne listait les vérifications. Le tableau de bord en montrait les six
dernières, et son lien « Tout voir » menait à la **base de connaissances** — un
autre écran, un autre objet. Le lien existait, il pointait à côté ; septième
chaîne muette du chantier, et la première qui pointait ailleurs plutôt que nulle
part.

### « Verser » n'est pas « publier », et l'écran le dit

L'action que `signalement.publish` autorise verse une vérification tranchée dans
le fonds que **les analyses suivantes consultent AVANT d'appeler un moteur**.
C'est l'empreinte de contenu qui fait le travail : la même intox soumise par
quelqu'un d'autre trouve la réponse sans repasser par l'IA — sans coûter d'unité,
et instantanément.

L'entrée est versée **en brouillon**. Sauter la relecture ferait d'un verdict
automatique une référence opposable, ce que la plateforme refuse partout
ailleurs. Le bouton dit « Verser à la base » et le toast précise « en brouillon,
à relire » : appeler cela « publier » aurait laissé croire l'inverse.

Le doublon se contrôle sur l'**empreinte**, pas sur l'identifiant : deux
personnes soumettant le même message produisent deux vérifications, et le fonds
n'a besoin que d'une entrée.

### La suppression devient douce, et c'était nécessaire

`signalement.delete` portait sur un objet particulier : une vérification a été
**demandée par quelqu'un**, elle lui a coûté une unité, elle figure dans son
historique. L'effacer franchement, c'est retirer une ligne du compte d'un citoyen
depuis le back-office sans qu'il puisse le constater autrement que par une
absence.

D'où `verifications.deleted_at`, et un **motif obligatoire** — une suppression
sans raison écrite n'est pas contestable. Le motif vit au journal d'audit et non
sur la table : deux vérités sur le même geste finiraient par diverger.

### Les plus anciennes en tête

À l'inverse de presque toutes les listes du back-office. Cet écran sert à VIDER
une file : trier par date décroissante enterrerait exactement ce qui attend
depuis le plus longtemps — c'est-à-dire ce dont quelqu'un attend encore la
réponse.

Même logique pour le KPI « Non assignées », mis en avant : une vérification que
personne n'a prise n'avance pas, quel que soit son statut technique.

### Réemployer plutôt que réécrire

Les mots-clés du fait versé viennent de `GestionDesFaitsVerifies::motsClesSuggeres()`,
le service qui alimente déjà la base à la main. Ils sont normalisés comme les
affirmations, et **c'est cette normalisation partagée qui fait que le
rapprochement fonctionne** : en écrire une seconde ici aurait produit des entrées
que les recherches existantes ne trouveraient pas.

### Deux gardes mortes, signalées par l'analyse statique

`$rapport->verdict === null` et `$rapport->verdict instanceof BackedEnum` : la
colonne est NOT NULL et castée en enum, donc **un rapport EST un verdict**. Les
deux tests étaient inertes. PHPStan les a vus ; à la lecture, ils passaient pour
de la prudence.

### Le cliquet est vide

`ORPHELINES_CONNUES` ne contient plus une seule entrée. Le test reste, et c'est
lui qui compte désormais : une 128ᵉ permission déclarée sans garde fera échouer
la suite, et l'ajouter à la liste coûtera une raison écrite.

### Barre

`pint` vert · `phpstan` niveau 6 vert, baseline toujours à 92 ·
**1 863 tests, 5 079 assertions, 1 ignoré, 0 échec** (`exit=0` lu sur un fichier) ·
**127 permissions déclarées, 0 orpheline**.


---

## L'œil du personnel — 3 septembre 2026

Le sixième et dernier champ de mot de passe de l'application.

### Un renoncement qui était une supposition, pas une contrainte

J'avais écrit, en refermant le lot précédent : « `staff-login` emploie `.ffield`
du design system staff là où le composant porte `.form-floating` du citoyen ; y
plaquer le composant aurait changé l'apparence sans vérification. »

Vérification faite : **`.ffield` EST `position: relative`** — exactement ce qu'un
bouton en position absolue exige — et la règle vient de `citizen.css`, que la
mise en page du personnel charge aussi. Le renoncement reposait sur une
supposition que trente secondes de `grep` démentaient.

Le composant reçoit donc une variante `staff` : **seule la classe du conteneur
change**, le bouton, les icônes et le comportement restent partagés. Un second
composant aurait dupliqué deux SVG et une logique d'accessibilité.

### Huitième chaîne muette, attrapée avant livraison

`components/layouts/staff-auth.blade.php` **ne chargeait pas `auth-forms.js`** :
la mise en page citoyenne le charge depuis le 2 septembre, celle du personnel
n'en avait jamais eu besoin. Le bouton se serait affiché et un clic n'aurait rien
fait.

C'est la huitième fois sur ce chantier qu'une chaîne complète n'atteint pas son
gabarit — mais la première où je la trouve **avant** de dire que c'est livré. Le
réflexe qui a servi : après avoir posé un balisage qui dépend d'un script,
vérifier que la mise en page concernée le sert. Le test le tient désormais.

### Barre

`pint` vert · **130 tests d'authentification, 328 assertions, 0 échec**.
Les six champs de mot de passe portent l'œil.


---

## La barre latérale par rôle — 3 septembre 2026

Elle affichait **56 liens à tout le monde**. Quatre étaient conditionnés ; les
cinquante-deux autres promettaient à un mentor des écrans que son rôle lui
refuse — le clic répondait 403. Un menu qui promet ce que le serveur interdit
fait perdre confiance dans TOUT le menu : on cesse de savoir ce qu'on a le droit
de faire, et on clique pour voir.

### Ce que j'ai refusé de faire

Envelopper cinquante-deux liens dans autant de `@can` à la main, dans un gabarit
de 348 lignes. Ça aurait marché, ce ne serait vérifiable par rien, et le premier
lien ajouté ensuite aurait recommencé à mentir.

### La décision qui porte tout : la garde n'est pas déclarée, elle est LUE

Le réflexe suivant aurait été d'écrire une permission à côté de chaque entrée du
menu. **Ce serait une seconde source de vérité**, et elle divergerait — comme a
divergé la liste des courriels câblés, corrigée trois fois avant d'être remplacée
par une règle.

`MenuDuBackOffice` ne déclare donc que la ROUTE de chaque entrée. La visibilité
se calcule en relisant le middleware `can:` de cette route et en l'évaluant.

Le menu montre par construction ce que le serveur autorise. **Changer la garde
d'une route change le menu, sans que personne n'y pense.** Et « l'admin a sa
barre, le mentor la sienne » n'a demandé aucune ligne : c'est le même menu,
filtré.

| Rôle | Entrées visibles | Groupes |
|---|---:|---:|
| Admin | 53 | 14 |
| Mentor | 16 | 4 |
| Modérateur | 11 | 5 |
| Citoyen | 1 | 1 |

### Deux défauts que la déclaration a fait apparaître — et une erreur de ma part

Écrire le menu comme une donnée l'a rendu inspectable, et l'inspection a trouvé :

- **`staff.content-reports` figurait DEUX fois** — « Signalements » en
  fact-checking, « Contenus signalés » en communauté. Il ne liste que les
  contenus signalés du forum : la seconde place est la bonne. La première
  attendait en fait `staff.verifications`, écrit la veille.
- **`staff.unit-journal` n'avait AUCUNE garde de route.** Sa garde vivait dans le
  composant : un OU entre `ai_unit.view` et `security.unit_logs`, que le
  middleware `can:` ne sait pas exprimer. Invisible du routeur comme du menu, qui
  affichait donc le lien à tout le monde. Devenue une **Gate nommée**,
  `journal-des-unites` : le routeur la lit, le menu aussi.

### L'erreur : un doublon délibéré pris pour une négligence

J'avais aussi retiré la double entrée de `staff.unit-journal` — « Historiques des
Unités IA » en fact-checking, « Logs Unités IA » en sécurité — en la comptant
comme un troisième défaut.

**Elle est délibérée, et un test la documentait déjà** : le prototype porte les
deux, et `SecuriteEtMaintenanceTest` explique pourquoi — « deux écrans identiques
donneraient deux habitudes, donc un des deux qu'on ne relève plus ». Une seule
page servie par deux portes évite cela ; le responsable sécurité et le
gestionnaire d'unités y arrivent chacun par son groupe.

La suite complète l'a rattrapé, et c'est exactement son rôle. Le doublon est
restauré et désormais **nommé** dans `DOUBLONS_ASSUMES`, avec sa raison — pour
que le prochain qui le voit n'ait pas à la redécouvrir. Le test de doublons ne
dit plus « aucun doublon » mais « aucun doublon NON DÉCLARÉ », ce qui est la
règle utile.

Les autres doublons du menu, eux, étaient bien des négligences — deux chemins
vers les réglages, deux vers les opportunités du Hub — et avaient été retirés au
lot 10.

### Quatre tests dont le MÉCANISME a expiré

Ils comptaient des lignes `sidebar-link` dans le gabarit. Le menu n'y étant plus
écrit, ils rendaient zéro. **Ce qu'ils protègent n'a pas changé** — pas deux
chemins vers la même page, la FAQ qui pointe sur l'écran d'administration et non
sur la page publique — seul l'endroit où la réponse se lit a bougé. Ils lisent
désormais la déclaration, et le disent dans leur propre commentaire.

### Le gabarit passe de 348 à 159 lignes

Et le test qui compte n'est pas celui qui vérifie la classe : c'est celui qui
charge une page réelle en tant que mentor et vérifie qu'aucun lien interdit n'y
figure. Un gabarit qui oublierait le filtre passerait tous les autres.

### Barre

`pint` vert · `phpstan` niveau 6 vert, baseline toujours à 92 ·
**1 892 tests, 5 148 assertions, 1 ignoré, 0 échec** · gabarit de la barre
latérale ramené de **348 à 159 lignes**.


---

## Le mode maintenance — 3 septembre 2026

### Pourquoi pas `php artisan down`

Le réflexe. Il pose un fichier et coupe TOUT, back-office compris. Trois
conséquences, chacune rédhibitoire :

1. **on ne peut plus rouvrir depuis l'administration** — il faut un accès SSH,
   que l'équipe éditoriale n'a pas ;
2. **le formulaire « prévenez-moi » ne peut pas fonctionner** : aucune route ne
   répond, donc aucun POST ;
3. le décompte n'a nulle part où lire sa date de fin.

Le mode vit donc dans les réglages, et un middleware l'applique. Trois exemptions
nommées — le personnel connecté, la connexion du personnel, la page de
maintenance et son formulaire — et la propriété qui domine tout : **on ne peut
pas s'enfermer dehors**. Quatre tests ne tiennent que cela.

### La fin annoncée est une PROMESSE, pas une minuterie

Elle n'éteint rien. Une maintenance qui se rouvrirait au chronomètre rouvrirait
un site à moitié migré si l'opération a pris dix minutes de plus — précisément
le moment où il ne faut pas laisser entrer. Un test l'exige : deux heures après
l'échéance, le site est toujours fermé.

Ce que l'heure apporte : un décompte, un en-tête `Retry-After` pour les robots et
les sondes, et la capacité de **dire le retard**. Un compteur figé sur « 00:00 »
pendant vingt minutes fait rafraîchir en boucle et se lit comme une panne ;
« l'opération dure plus longtemps que prévu » se lit comme du travail en cours.

### 503, et non 200

Un moteur d'indexation qui reçoit un 200 sur une page de maintenance conclut que
le site a été remplacé par ce texte, et il désindexe.

### La liste d'attente n'est PAS la liste d'infolettre

Quelqu'un qui laisse son adresse sur une page de maintenance demande **un
message, une fois, sur un sujet précis**. L'inscrire à l'infolettre hebdomadaire
détournerait son consentement — exactement ce qu'on reproche aux plateformes dont
ce produit démonte les rumeurs. Table séparée, `notified_at` plutôt qu'une
suppression, adresse unique parce que rafraîchir et resoumettre est le geste le
plus naturel quand on attend.

Les avertis sont prévenus **à la réouverture**, jamais à l'heure annoncée : ils
arriveraient sur un site encore fermé, et le courriel qui promet le retour
deviendrait le deuxième mensonge de la journée.

### Deux décisions techniques qui méritent d'être dites

**`email:rfc` et non `email:rfc,dns`.** La règle `dns` interroge le résolveur. Sur
cette page-ci, c'est la dépendance à ne pas ajouter : elle s'affiche justement
quand l'infrastructure est dégradée. Le test le montrait sans le vouloir — il
mettait **56 secondes** avant de refuser une adresse parfaitement valable.

**La page est autonome.** Ni build Tailwind figé, ni mise en page citoyenne : son
style est en ligne. C'est le seul écran dont on est certain qu'il sera vu un jour
où quelque chose ne va pas.

### La première permission créée depuis quatorze lots

`maintenance.mode`. Elle ne se confond avec aucune autre : `backup` sauvegarde la
base, `cron` regarde des tâches planifiées, `mode` **ferme le site**. La
rattacher à l'une des deux aurait donné ce pouvoir à qui n'a demandé qu'à lancer
une sauvegarde. Le catalogue passe à 128 permissions, 25 gabarits de courriel.

### Deux défauts que la suite complète a trouvés — dont un sérieux

Les tests du lot passaient tous. C'est la suite ENTIÈRE qui a parlé :

- **La page d'accueil rendait 500.** Mon middleware interroge les réglages à
  chaque requête, y compris celle d'un visiteur anonyme. Quand la table
  `settings` est injoignable — déploiement dont les migrations n'ont pas encore
  tourné, base momentanément indisponible — l'exception remontait et **tout le
  site tombait**.

  Le correctif est une règle, pas un rustine : **un drapeau illisible ne ferme
  pas le site.** Au pire on sert un site qu'on croyait fermé, au lieu de fermer
  un site qui allait bien.

- **Un favicon inventé.** J'avais écrit `favicon.svg` ; le dépôt porte
  `favicon.png`. `ActifsReferencesTest` l'a vu immédiatement — c'est le genre de
  chose qu'on ne remarque jamais à l'œil, un carré vide dans un onglet.

### Barre

`pint` vert · `phpstan` niveau 6 vert, baseline toujours à 92 ·
**1 909 tests, 5 182 assertions, 1 ignoré, 0 échec** sur la suite complète ·
17 tests de maintenance, 128 permissions, 25 gabarits de courriel.

---

## Le document Word sur les adaptateurs — 3 septembre 2026

`Adaptateurs restants - alternatives.docx`, à la racine de `verifall/`.

Ni `python-docx` ni `phpoffice/phpword` n'étant installés, le fichier est écrit
en **OOXML brut** — un `.docx` est un ZIP de XML normalisé. Validité vérifiée :
six entrées, intégrité ZIP bonne, quatre fichiers XML qui parsent.

### Ce que l'inventaire a montré

Le compte de « 13 restants sur 19 » est juste, **mais il mélange trois situations
qui n'appellent pas la même décision** :

| Situation | Combien |
|---|---:|
| Écrit et opérationnel | 6 |
| Non écrit, clé DISPONIBLE | 3 |
| Non écrit, clé absente ou payante | 6 |
| Hors pipeline | 3 |

Écrire les treize d'un bloc n'a donc pas de sens : **six attendent une décision
budgétaire, pas du développement.**

Le document propose trois alternatives chiffrées — le strict nécessaire (trois
adaptateurs, aucune dépense), la réouverture de la vidéo et de l'audio (Whisper
sur la clé OpenAI déjà payée), la couverture complète (abonnement TinEye + trois
clés à réémettre) — avec un comparatif et une recommandation argumentée.

Une réserve y est écrite noir sur blanc : **les détecteurs de texte généré par IA
se trompent surtout contre les personnes qui n'écrivent pas dans leur langue
maternelle.** Sur une plateforme béninoise, un faux positif accuserait à tort un
rédacteur francophone non natif. Je recommande de n'afficher ce signal qu'en
indication interne, jamais dans le rapport public.
