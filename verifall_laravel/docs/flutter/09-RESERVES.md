# 9. Réserves

La règle qui prime est « on transpose, on n'invente pas ». Elle a un corollaire
que la fiche de rôle pose explicitement : *si une maquette paraît perfectible, on
l'écrit dans une section réserves, on ne la corrige pas en silence.*

Cette page est cette section. Elle recense ce que le simulateur dit et que le
contrat d'API ou la documentation du back contredit, ce que l'API ne sert pas, et
ce qui n'a pas pu être documenté. **Rien n'y est corrigé d'autorité** : chaque
point attend un arbitrage, et chacun doit finir dans `docs/ARBITRAGES.md` avec sa
raison.

**Ce qui a changé depuis la première rédaction.** `docs/kb/API_SPECIFICATION.md`
est écrit, figé pour la v1 et implémenté, avec les arbitrages n° 68 à 75. Sept
réserves sont **tranchées** et marquées comme telles ci-dessous ; cinq nouvelles
apparaissent, nées du contrat lui-même.

---

## A. Contradictions

### A.1 — L'ancre du tableau de bord ne fonctionne pas · **ouverte**

Le `README.md` du simulateur donne en exemple `index.html#/dashboard`. Or
l'identifiant de l'écran est `home` : `SCREENS['dashboard']` n'existe pas, et
l'ancre retombe silencieusement sur le rendu par défaut. L'ancre réelle est
`#/home`.

Cette documentation cite `#/home` et le signale à chaque fois. **À corriger** :
soit le README, soit l'identifiant de l'écran. Défaut mineur, mais il fait douter
de toutes les autres ancres.

### A.2 — La 2FA annonce un code par SMS · **devenue sans objet, le libellé reste**

L'écran Réglages (30) décrit l'interrupteur ainsi : *« Code par SMS à chaque
connexion sur un nouvel appareil »*, quand le README du même fichier tranche
l'e-mail et l'explique — couverture universelle, coût nul, aucune dépendance
opérateur.

**Le contrat confirme l'e-mail** : toutes les routes de code prennent un `email`
et rien d'autre. Mais il rend le débat partiellement théorique : **il n'existe
aucun endpoint de double authentification** (voir C.8). Le libellé reste à
corriger le jour où l'interrupteur reviendra.

### A.3 — Longueur du mot de passe · **TRANCHÉE : 12**

Le simulateur annonce « 8 caractères minimum » sur l'inscription (03) et la
réinitialisation (06). Le contrat tranche :

> Le mot de passe suit la politique du site : **12 caractères minimum, casse
> mixte, chiffre, symbole, et absence des fuites connues**. Le client se limite à
> vérifier la longueur avant l'envoi — le reste est serveur.

**Les étiquettes annoncent 12**, et le client ne valide que la longueur. C'est
l'arbitrage n° 10, désormais porté par le contrat.

### A.4 — L'adresse e-mail dans le formulaire de profil · **TRANCHÉE : non modifiable**

L'écran 25 place l'adresse entre la ville et la bio, et l'enregistre avec le
reste. Le contrat l'exclut de `PATCH /me` et donne la raison, la même que
l'arbitrage n° 6 : *« elle sert d'identifiant de connexion et de canal de
récupération, et la changer sans vérifier la nouvelle boîte offrirait une prise
de compte à qui emprunte un téléphone déverrouillé. »*

Le champ reste **affiché en lecture seule**, à sa place. **Aucun endpoint de
changement d'adresse n'existe en v1** : l'opération se fait sur le site web, et
l'écran le dit (voir aussi C.8).

### A.5 — Le compteur d'invitations du parrainage · **TRANCHÉE : ouvertures**

L'écran 29 affiche « Invitations envoyées ». Le contrat est explicite :
`POST /me/referrals/invite` *« ne compte rien. Le seul compteur honnête du
parrainage est le nombre de personnes qui ont **ouvert** une invitation, et il
s'incrémente sur la page publique. »*

**Libellé à corriger en « Invitations ouvertes »**, et « Unités gagnées » vient de
l'API, pas d'une multiplication locale. Le statut « Invité » disparaît : une
ligne naît à l'inscription du filleul, il n'existe pas d'état antérieur
(arbitrage n° 46).

### A.6 — L'avertissement « aucun frais » · **TRANCHÉE : systématique**

La réserve opposait le simulateur (systématique) à l'arbitrage n° 55
(recrutements seulement). **Le contrat tranche pour le simulateur**, et il
motive : la fiche porte **toujours** `meta.avertissement`, parce qu'*« une offre
frauduleuse ne se signale pas d'elle-même, et c'est la plus dangereuse. »*

Le texte vient du serveur ; le client ne décide pas de l'afficher.

### A.7 — La newsletter : consentement ou préférence · **TRANCHÉE : les deux, distincts**

`PATCH /me/settings` porte `notifications` **et** `newsletter` dans le même
appel, mais le contrat sépare leur nature : *« la lettre d'information est
séparée des préférences : c'est un consentement marketing, horodaté et versionné,
pas un réglage d'affichage. »*

Visuellement rien ne change ; l'écriture va ailleurs, côté serveur. Conforme à
l'arbitrage n° 11.

### A.8 — Des identifiants entiers dans trois routes · **TRANCHÉE : slugs**

Le simulateur route `/forum/:id`, `/hub/opportunites/:id` et `/hub-infos/:id`
avec des entiers. Le contrat n'expose **aucune clé primaire entière** : sujet,
brève et opportunité sont désignés par leur **`slug`**.

Une seule exception assumée dans tout le contrat : l'identifiant d'une **réponse
de quiz** est un entier — la table n'a pas d'uuid, et il n'énumère rien d'utile
puisqu'une réponse n'existe que dans la question qui la porte, servie seulement à
qui est inscrit au cours. Et une exception de forme : le **certificat** se
désigne par son `code` public, fait pour être dicté au téléphone.

### A.9 — Le moment de la correction d'un quiz · **TRANCHÉE, et elle change l'écran → voir C.1**

Le contrat ne rend le corrigé qu'à la copie. Les conséquences sont assez lourdes
pour mériter leur propre entrée : voir **C.1**.

### A.10 — Il n'y a pas de vérification en deux étapes à la connexion · **NOUVELLE**

Le simulateur enchaîne systématiquement `connexion → code → tableau de bord`, via
un paramètre `purpose: login`.

**Le contrat ne le fait pas.** `POST /auth/login` rend un jeton directement ;
`otp/request` et `otp/verify` servent à **activer** un compte neuf ou à
réactiver un compte dont le code n'a jamais été validé. Il n'existe aucune route
qui exigerait un second facteur après une connexion réussie.

Trois lectures possibles, et il faut choisir :

1. **le parcours du simulateur est simplifié** — la connexion mène directement au
   tableau de bord, et l'écran 05 ne sert plus que `signup` et `reset` ;
2. la 2FA facultative de l'écran 30 devait piloter ce second facteur — mais elle
   n'a pas d'endpoint non plus (C.8) ;
3. le contrat devra ajouter la route, ce qui serait un **ajout compatible v1**.

**À trancher avant M1.** En attendant, coder le parcours du contrat : il est
servi, testé, et il ne promet rien de faux.

### A.11 — Le formulaire d'inscription et le corps attendu ne coïncident pas · **NOUVELLE**

| | Simulateur (écrans 03 et 25) | Contrat |
|---|---|---|
| Nom | un champ « Nom et prénom » | `prenom` **et** `nom` |
| Profession | absente | `profession`, attendue à l'inscription |
| Ville | champ à l'inscription | **non acceptée** par `register` ; acceptée par `PATCH /me` |
| Téléphone | présent, facultatif | `telephone` |
| Consentements | trois cases | `cgu`, `confidentialite`, `newsletter?` |
| Parrainage | champ | `code_parrainage?` |

Sur l'écran 25, le même écart : « Nom affiché » recouvre deux champs, et
**profession** comme **téléphone** sont modifiables sans avoir de champ à
l'écran.

**C'est un changement de maquette : il se décide, il ne se fait pas en passant.**
Découper « Nom et prénom » en deux champs et ajouter « Profession » modifie deux
écrans validés. À trancher avant M1 et M5.

### A.12 — La suppression de compte se fait en deux temps · **NOUVELLE**

Le simulateur demande de **taper son nom**, puis supprime. Le contrat impose un
parcours différent sur le même point d'entrée `DELETE /me` :

1. mot de confirmation **+ mot de passe** → `202`, un code part par courriel,
   **rien n'est supprimé** ;
2. mot de confirmation + mot de passe **+ code** → `200`, suppression programmée.

*« Mot de passe et code : le premier prouve qu'on connaît le compte, le second
qu'on tient la boîte. Un téléphone volé déverrouillé passe le premier, jamais les
deux. »*

La feuille de confirmation devient donc un parcours en deux temps, et le mot
attendu n'est pas le nom de la personne. **À trancher avant M5.**

---

## B. Données de démonstration — à ne jamais transposer

Le simulateur porte des données parce qu'il doit fonctionner seul. Le §2 des
règles du chantier est catégorique : figer un verdict revient à publier une
vérification qui n'a jamais eu lieu, et c'est la faute la plus grave du projet.

Ce qui suit est de la donnée, pas du design. **Rien n'en est repris.**

| Écran | Ce qui est en dur dans le simulateur |
|---|---|
| 07 | « synchronisé il y a 2 min » ; les 20 barres de l'histogramme ; le contenu de la carte de priorité ; la Live Class annoncée |
| 09 | Le barème des coûts (1/1/2/5/5) |
| 10 | La progression pilotée par minuterie |
| 11 | Les quatre sources, les trois moteurs, l'indice de haine calculé depuis le verdict, le bloc structuré |
| 11 | **Le verdict lui-même** — le simulateur en tire un au hasard à la soumission |
| 12, 25, 27, 28 | Les compteurs (34 unités, 1 280 XP, série de 7 jours, 4 vérifications) |
| 14, 15 | Les notes (« ★ 4,8 »), les nombres d'inscrits, les quatre cours et leurs quiz |
| 16 | Le corps de leçon, identique sur toutes les leçons — c'est une limite du prototype, pas un choix éditorial (arbitrage n° 42) |
| 16 | Les 45 secondes de durée minimale — elles viennent de `meta.secondes_requises` |
| 19 | Les cinq Live Classes |
| 20, 21 | Les cinq sujets, les deux réponses, **les badges de rôle** (voir C.4) |
| 22, 23 | Les onze opportunités et les six brèves ; **le calcul de l'état d'échéance** |
| 26 | Les trois packs et leurs prix ; « +10 unités offertes le 1ᵉʳ septembre » |
| 28 | Les 20 barres d'activité |
| 29 | Le code de parrainage, les trois filleuls |
| 30 | « Appareils connectés : 2 », « Cet appareil · Cotonou » |

**Contre-exemple légitime** : le contenu **éditorial** — les quatre diapositives
du carrousel, les cinq étapes du pipeline d'analyse, les textes de méthode, les
avertissements anti-arnaque, les explications de consentement, l'encart sur
l'usage de l'IA. C'est la parole du produit, et elle se transpose telle quelle.

---

## C. Ce que l'API ne sert pas, et ce que l'application fait à la place

Le simulateur déclare **58 appels** ; l'API en sert **53**. Les cinq manquants
sont nommés par le contrat (§11) et l'arbitrage n° 73, avec ce qu'il faudra pour
lever chaque réserve. S'y ajoutent trois écarts que le contrat crée sans les
qualifier de manques.

**Aucune de ces situations ne se règle par un écran vide.** Un écran vide laisse
croire à une panne ou à un compte sans activité ; c'est un indicateur qu'on ne
mesure pas, et la règle 2 du chantier l'interdit.

### C.1 — Le corrigé d'un quiz n'arrive qu'à la copie · **arbitrage produit**

Le simulateur affiche l'explication XAI et la marque juste/faux **après chaque
réponse**. Le contrat rend les questions **sans corrigé**, et range `is_correct`
et l'explication parmi ce qui n'est jamais exposé avant la copie rendue :
*« un quiz réussi avec un intercepteur de trafic dévalue tous les
certificats. »* Le corrigé arrive **une seule fois**, dans `data.corrige[]` de
`POST /me/quiz/{uuid}/attempt`.

**Ce que l'application peut faire, et qui reste le plus proche de la maquette** :
garder une question à la fois, sans marque ni XAI pendant le parcours, et
**dérouler le corrigé sur l'écran de résultat** — question par question, avec
chaque explication. La pédagogie est préservée, le moment change.

Ce n'est **pas** une décision prise : c'est la proposition la moins coûteuse. Elle
modifie un écran validé, donc elle se décide. **À trancher avant M3.**

### C.2 — Aucune notification : trois promesses d'écran deviennent fausses

Les trois appels `GET /me/notifications`,
`POST /me/notifications/read-all` et `POST /me/notifications/{id}/read` **ne sont
pas servis**. Il n'existe ni table, ni classe, ni émetteur ; la cloche du gabarit
citoyen est **décorative**. Ni FCM, ni `toFcm()`.

**Ce que l'application fait :**

| Où | Ce qui est promis | Ce qu'il faut faire |
|---|---|---|
| Écran 07, barre supérieure | Une cloche avec pastille de non-lues | **Masquer l'action.** Une cloche qui ouvre un écran vide se lit comme un défaut |
| Écran 08 | La liste | **Écrire l'écran, ne pas le brancher** — derrière un drapeau de fonctionnalité, prêt à lever |
| Écran 07, carte Priorités | « Un verdict vous attend », issu de la notification la plus récente | L'alimenter depuis `GET /me/dashboard`, ou faire disparaître la ligne |
| Écran 10 | *« Vous pouvez quitter cet écran : une notification vous préviendra »* | **Corriger le texte** : « L'analyse continue même si vous quittez cet écran. Retrouvez-la dans Mes vérifications. » |
| Écran 19 | *« Rappel programmé — vous recevrez une notification »* | **Corriger le texte** : « C'est noté — retrouvez la séance dans Live Classes. » L'intention **est** enregistrée, le rappel ne part pas |

La réserve du rappel J-1 est **partielle et assumée** par le contrat :
`POST /live-classes/{uuid}/remind` enregistre bien, dans une table qui existe
depuis le sprint LMS — *« le jour où le rappel partira, il aura sa liste, et
personne n'aura à redemander aux gens de se réinscrire. »*

Ce qu'il faudra pour lever : la table `notifications`, des classes sur les
événements qui comptent (verdict prêt, réponse au forum, correction rendue), et
`toFcm()` sur chacune.

### C.3 — Ouvrir une leçon démarre le chronomètre serveur · **arbitrage produit**

Le contrat le dit sans détour : *« ouvrir une leçon démarre le chronomètre
serveur »*, et `POST /me/lessons/{uuid}/complete` compare `secondes` à ce que le
serveur a mesuré, en retenant le minimum.

**Le téléchargement d'un cours entier appelle chaque leçon d'avance** — et
démarre donc tous les chronomètres à la fois. Trois heures plus tard, hors ligne,
la personne lit sa première leçon ; le serveur, lui, a compté depuis le
téléchargement.

Le sens du minimum protège dans le bon sens — le client ne peut pas *gonfler* sa
durée — mais l'effet reste indésirable : un cours téléchargé permettrait de
valider des leçons non lues. **À trancher avant M3** : ne télécharger que les
leçons déjà ouvertes, prévoir un mode « télécharger sans ouvrir » côté contrat,
ou accepter l'effet.

### C.4 — Le badge de rôle des auteurs n'existe pas

Les cartes de sujet du simulateur affichent « 🎓 Mentor Vérif'All », « ◉
Contributeur actif », « 🟢 Citoyenne vérifiée ».

**L'API ne sérialise jamais le rôle d'un auteur** (arbitrage n° 74) :
`AuteurResource` porte uuid, nom d'affichage, initiales — rien d'autre. La raison
est bonne : *« publier “untel est modérateur” sur chaque message dresserait la
liste nominative de l'équipe de modération. »*

**Ce que l'application fait : elle retire le badge** des cartes de sujet et des
réponses. La ligne auteur se réduit à nom + ancienneté. **À consigner** : c'est
un élément visible de la maquette qui disparaît.

### C.5 — Trois paramètres de filtre du simulateur ne sont pas au contrat

| Simulateur | Contrat |
|---|---|
| `GET /tools?category=` | `GET /tools` — **non paginé** |
| `GET /courses?level=` | `GET /courses` |
| `GET /hub/opportunities?scope=&type=` | `GET /hub/opportunities` |
| `GET /forum/threads?category=` | `GET /forum/threads` |
| `GET /live-classes?state=` | `GET /live-classes` — **non paginé** |

Pour les deux listes **non paginées** — outils et séances — le filtrage côté
client est la bonne réponse, et il marche hors ligne. Pour les trois listes
**paginées**, filtrer localement ne filtre que la page chargée, ce qui donne un
résultat faux dès la deuxième page.

**À clarifier avec l'auteur du contrat** : soit ces paramètres existent et ne
sont pas documentés, soit ils sont à ajouter — ce serait un **ajout compatible
v1**. En attendant, ne pas construire de filtre paginé qui mentirait.

### C.6 — Publier un sujet ou une réponse hors ligne

Ces deux écritures **ne figurent pas** dans la liste des actes mis en file par le
contrat (§7.2) : elles n'ont ni `Idempotency-Key` exigée, ni contrainte
d'unicité mentionnée. Les mettre en file expose au doublon — deux sujets
identiques publiés par une réponse perdue.

Deux options : les **refuser hors ligne** avec un message clair, ou leur
attribuer une `Idempotency-Key`, l'en-tête étant accepté partout et sa
sémantique étant générale. La seconde est probablement correcte, mais elle
suppose que le serveur mémorise la réponse pour ces routes — ce que le contrat ne
promet que pour les deux actes qu'il nomme. **À confirmer.**

### C.7 — L'opérateur Mobile Money est déduit du numéro

La feuille de recharge du simulateur (écran 26) fait **choisir** entre MTN MoMo,
Moov Money et Celtiis Cash. Le contrat attend `pack` et `numero`, et précise :
*« l'opérateur est déduit du numéro par le serveur. »*

Le choix affiché n'a donc aucun effet fonctionnel. Trois options : garder les
trois logos comme repère visuel et demander le numéro, remplacer la feuille par
une simple saisie de numéro, ou envoyer l'opérateur choisi en indication —
ce que le contrat n'accepte pas. **À trancher avant M5**, sachant que la
première option est la moins éloignée de la maquette.

### C.8 — Trois réglages de sécurité n'ont aucun endpoint

L'écran 30 propose trois actions que l'API v1 ne sert pas :

| Action | État |
|---|---|
| **Activer / désactiver la 2FA** | Aucun endpoint. `PATCH /me/settings` refuse en `422` toute clé inconnue |
| **Changer son mot de passe** (authentifié) | Aucun endpoint. `POST /auth/password/reset` existe, mais il exige un code reçu par courriel |
| **Changer son adresse e-mail** | Aucun endpoint. `PATCH /me` ne l'accepte pas (voir A.4) |

**Ce que l'application fait :** elle **retire l'interrupteur de 2FA**, elle
**renvoie « Changer mon mot de passe » vers le parcours de code** (l'écran 04
existe déjà et il fonctionne), et elle affiche l'adresse en lecture seule. Les
trois réglages complets se font sur le site web, et l'écran doit le dire.

Un interrupteur qui échoue à chaque pression est pire que son absence ; et un
interrupteur de sécurité qui semblerait marcher sans rien activer serait un
mensonge sur une garantie — la faute la plus grave possible sur cet écran.

### Récapitulatif des cinq appels non servis

| Appel | Ce que fait l'application | Ce qu'il faut pour lever |
|---|---|---|
| `GET /me/notifications` | Cloche masquée, écran écrit non branché | Table `notifications` + classes + `toFcm()` |
| `POST /me/notifications/read-all` | idem | idem |
| `POST /me/notifications/{id}/read` | idem | idem |
| `POST /verifications/{uuid}/feedback` | **Bloc « ce rapport vous a-t-il été utile ? » masqué** — pas de boutons inertes, pas de remerciement sans destinataire | Table `verification_feedback` (compte, vérification, utile oui/non, unicité sur le couple) + service `FactChecking` |
| `GET /me/certificates/{code}/pdf` | **Bouton retiré** ; partage d'image côté client + renvoi vers la page publique de vérification | Bibliothèque PDF, gabarit avec filigrane, route signée à durée limitée |

Le contrat garantit que ces endpoints, quand ils existeront, seront des
**ajouts** : compatibles v1, sans rupture pour une application déjà installée.

---

## D. Manques et petits défauts

### D.1 — Ce que l'arrivée du contrat a levé

Pour mémoire, et parce que ces points ont été des blocages : le contrat fixe
désormais l'authentification, l'enveloppe, les treize codes d'erreur, la
pagination par curseur, les identifiants, les plafonds de débit, le
versionnement, et le contrat de synchronisation hors ligne. Les sept questions
qui étaient ouvertes en section 4 sont toutes répondues, sauf la nuance des
paramètres de filtre (C.5).

### D.2 — Une cible tactile sous les 44

Le lien « Mot de passe oublié ? » de l'écran 02 est déclaré à **32** de hauteur
minimale. Les liens de bas de carte (« Créer un compte citoyen » sur l'écran 02,
« Se connecter » sur l'écran 03) n'en déclarent aucune et retombent sur la
hauteur de leur ligne de texte. La règle du chantier §7 exige **≥ 44**.

Ce sont des liens de texte au sein d'une phrase, ce qui explique le choix ; il
n'empêche que la règle est écrite, et que ces trois liens sont des sorties de
parcours — les manquer coûte cher. **À corriger côté mobile** en élargissant la
zone tactile sans changer l'apparence : c'est possible en Flutter et cela ne
modifie pas la maquette d'un pixel.

### D.3 — Le record de série n'apparaît pas

L'arbitrage n° 67 a ajouté `users.streak_best` côté back parce que le prototype
web affichait « Record personnel : 12 jours » sans colonne pour le soutenir.
L'écran 27 du simulateur mobile **n'affiche pas** ce record, et le contrat ne le
nomme pas dans la description de `GET /me/gamification`.

La règle est celle des champs inconnus : **si l'agrégat le renvoie, la donnée est
réelle et peut s'afficher ; sinon, on ne l'invente pas.** Ne pas ajouter le bloc
d'initiative. **À trancher** — l'omettre crée un écart avec le web.

### D.4 — Un paramètre de période sans contrôle

L'écran 28 déclare `statsProvider(period)`, et le contrat accepte **`7d`, `30d`,
`90d`, `365d` et rien d'autre**. Mais le simulateur **n'expose aucun sélecteur de
période**.

Le paramètre est donc réel et le contrôle absent. **Ne pas ajouter le sélecteur
de sa propre initiative** : ce serait inventer un élément d'interface. La période
reste `30d`, qui est celle des titres de l'écran. À clarifier.

### D.5 — Les analyses en cours dans l'historique

L'arbitrage n° 31 exige que « Mes Vérifications » affiche aussi les analyses **en
cours**, avec leur état à la place du verdict, et que le filtre mélange verdicts
et états. Le simulateur mobile (écran 12) ne montre que des analyses terminées et
ne propose que quatre filtres de verdict.

Le contrat rend le point concret : `GET /verifications/{uuid}` sert les deux
écrans, et `data.rapport` vaut `null` tant qu'aucun verdict n'est rendu. **L'écran
doit être écrit pour accueillir ces lignes**, et le filtre `verdict` de
`GET /me/verifications` devra accepter les états en cours — à confirmer.

### D.6 — « Recommandé pour vous » n'existe pas côté mobile

La section existe côté web (arbitrage n° 41, où elle a d'ailleurs été réparée).
Le catalogue mobile (écran 14) ne la porte pas, et le contrat n'expose aucun
endpoint de recommandation.

**Ne pas l'ajouter.** Mais l'écart web/mobile mérite d'être connu du porteur.

### D.7 — Terminologie : unités ou points ?

Le simulateur appelle la même chose « unités » dans le corps des écrans, « points »
dans le nom du composant (`pointspill`, « widget des points »), et « pts » dans
les pastilles de coût de l'écran 09. Le libellé d'accessibilité, lui, dit
« unités de vérification ». Le contrat, enfin, nomme le champ `unites`.

Trois mots pour une seule chose, sur l'élément le plus vu de l'application. **Un
seul terme visible doit être retenu** — « unités », qui est celui des écrans, du
back et du contrat.

### D.8 — Le thème sombre du banc d'essai

Le bandeau du simulateur porte un bouton « Thème ». Il ne concerne **que le banc
d'essai** ; l'application reste en thème clair. Le risque est qu'un lecteur
pressé en conclue que l'application doit gérer les deux.

Écrit en section 2, rappelé ici : **pas de palette sombre**.

### D.9 — L'indicateur d'étapes annonce cinq étapes et en montre quatre

L'écran 09 dessine quatre libellés — `CONTENU · SOURCES · PREUVES · VERDICT` —
tandis que son arbre de widgets déclare `VfStepIndicator(5)`. L'écran 10, lui,
déroule bien **cinq** étapes de pipeline.

L'écart n'a aucun effet visuel, mais il pose une question réelle : la barre de
l'écran 09 doit-elle refléter les cinq étapes du pipeline, ou rester un repère en
quatre temps ? **Transposer ce qui est à l'écran** — quatre libellés — et
signaler l'écart, c'est ce que fait cette documentation.

### D.10 — Deux numérotations d'écrans coexistent

Le rail du simulateur numérote **30 écrans**, splash compris : `01 Splash`,
`02 Connexion`, …, `30 Réglages`. **Le contrat d'API numérote autrement** — il
ne compte pas le splash et décale tout d'un rang : `01 Connexion`, `06 Accueil`,
`08 Vérifier`, `13 Catalogue`, `19 Forum`, `24 Profil`, `29 Réglages`.

Cette documentation suit **la numérotation du rail**, qui est celle du
simulateur. Quand le contrat écrit « écran 08 Vérifier », il désigne notre écran
**09**. Le piège est réel et il coûtera une confusion à quelqu'un : **à unifier**,
de préférence sur le rail, qui est la source visuelle.

Deux coquilles mineures dans le code du simulateur : les commentaires de section
numérotent `hub` et `thread` tous deux « 20/21 ». Sans effet — le rail affiche
bien 30 écrans dans le bon ordre : 6, 2, 5, 6, 5, 6.

---

## E. Ce que le simulateur calcule et qui appartient au serveur

Ces calculs sont légitimes dans un simulateur autonome. Les transposer serait une
faute, et le contrat les attribue tous explicitement (§7.4).

| Calcul | Écran | Champ / autorité |
|---|---|---|
| État d'une offre et compte à rebours | 22, 23 | `data.expiree`, `data.jours_restants` — *« une horloge de téléphone mal réglée présenterait comme candidatable une offre fermée »* |
| Progression d'une analyse | 10 | `data.avancement`, `data.etape` |
| Coût d'une vérification, solde après débit | 09 | `meta.solde` — le coût n'est **jamais envoyé** par le client |
| Correction d'un quiz, score, seuil | 17 | `data.corrige[]`, à la copie seulement |
| Chronomètre d'un quiz | 17 | `meta.commence_le` |
| Durée de lecture d'une leçon | 16 | **minimum** des deux mesures ; `meta.secondes_requises` |
| Déverrouillage séquentiel | 15 | `data.lecons[].ouverte` |
| Émission d'un certificat et de son code | 17, 18 | Fait serveur, alphabet imposé |
| Niveau, XP, série, badges | 25, 27 | `GET /me/gamification`, **recalculé** avant réponse |
| Indice de haine | 11 | Produit par l'orchestrateur |
| Pourcentages de vote | 21 | `data.votes`, recalculés ; unicité en base |
| Libellé d'une énumération | partout | Le serveur envoie valeur **et** libellé ; le client n'en fabrique aucun |

---

## F. Ce qui n'a pas pu être documenté, faute de source

1. **La forme détaillée des ressources.** Le contrat fixe l'enveloppe, les
   endpoints, les champs structurants (`data.rapport`, `data.lecons[].ouverte`,
   `meta.solde`, `data.expiree`…) mais ne publie pas le schéma complet de chaque
   ressource. Les modèles Dart se déduiront de l'implémentation
   (`app/Http/Api/V1/`) et des tests de contrat (`tests/Feature/Api/`), pas de
   suppositions.
2. **La liste des canaux de notification acceptés par `PATCH /me/settings`.** Le
   contrat en donne un exemple (`forum_activity`) et précise qu'un canal inconnu
   est refusé en `422`. Les quatre interrupteurs de l'écran 30 doivent donc être
   mappés sur des clés réelles, à relever dans l'implémentation.
3. **Les codes de pack** attendus par `POST /me/wallet/topup`.
4. **La forme des événements Reverb** sur `private-verification.{uuid}`, au-delà
   du nom du canal.
5. **Le parcours d'enrôlement de la double authentification** : l'arbitrage n° 8
   dit qu'il existe et pourquoi, aucune maquette mobile ne le montre, et aucun
   endpoint ne le sert. À concevoir avec le porteur — donc, en attendant, à ne
   pas improviser.
6. **Les visuels du carrousel** en tant que ressources : embarqués en données
   dans le simulateur, originaux dans `web/images/slider/`. Leur extraction et
   leur intégration au paquet Flutter restent à faire.
7. **Les couvertures de cours et d'articles** : le simulateur les remplace par
   quatre dégradés nommés. Aucune maquette ne montre le rendu avec image réelle.
8. **La politique d'épinglage de certificat** : elle relève de l'infrastructure,
   et la trancher sans procédure de rotation écrite créerait un risque
   d'indisponibilité plus grand que celui qu'elle couvre.
