# 4. Couche réseau

## 4.0 L'autorité : le contrat d'API v1

`docs/kb/API_SPECIFICATION.md` est **écrit, figé pour la v1 et implémenté**
(`routes/api.php`, `app/Http/Api/V1/`, tests dans `tests/Feature/Api/`). Il est
l'autorité sur tout ce qui traverse le réseau, et il s'ouvre sur la phrase qui
règle les arbitrages : *« ce qui n'y est pas n'existe pas. »*

Cette section décrit le client Dart qui en découle. Elle ne réinvente pas le
contrat : elle dit ce qu'il faut écrire pour s'y conformer, et où le simulateur
mobile diverge.

Trois choses à savoir avant tout le reste :

1. **L'API a un seul client** : cette application. Son périmètre est exactement
   celui des trente écrans du simulateur. Il n'y a ni endpoint « pratique », ni
   recherche générique, ni partie publique — le catalogue de cours et le Hub
   eux-mêmes exigent un jeton.
2. **Il n'y a pas de jeton de rafraîchissement.** Le renouvellement silencieux
   sur 401 que décrivent les points de vigilance du simulateur **n'existe pas**.
   Voir §4.3 : c'est la divergence la plus importante entre le simulateur et le
   contrat.
3. **53 des 58 appels du simulateur sont servis.** Les cinq manquants sont
   nommés en section 9, avec ce que l'application doit faire à la place.

---

## 4.1 Le client HTTP

Un seul client Dio, construit une fois, fourni par un provider.

**En-têtes de requête.** Le contrat n'en rend qu'**un** obligatoire :

```
Authorization: Bearer <token>       obligatoire sur toute route authentifiée
Accept: application/json
X-Client-Version: <version>         facultatif — voir §4.6
Idempotency-Key: <uuid>             facultatif — voir §5.6
```

**En-têtes de réponse à lire**, tous les trois utiles :

| En-tête | Usage |
|---|---|
| `X-Api-Version` | Version servie. À journaliser, pas à afficher |
| `X-RateLimit-Limit` / `X-RateLimit-Remaining` | Auto-régulation (§4.5) |
| `Retry-After` | Uniquement sur 429 et sur une connexion verrouillée |
| `X-Minimum-Client-Version` | Uniquement sur 426 |
| `Idempotent-Replay: true` | La réponse est un rejeu mémorisé, rien n'a été réexécuté |

Ce qui reste fixé indépendamment du contrat : une adresse de base par
environnement lue dans la configuration (§1.7), des délais explicites — un délai
par défaut de bibliothèque est toujours trop long pour un réseau mobile béninois
—, et aucune confiance accordée à un certificat non valide, en aucun
environnement.

`Accept: application/json` reste systématique par prudence, même si le contrat
garantit que **le serveur ne renvoie jamais de HTML sur `/api/v1/*`**, y compris
sur les 404 de route inconnue et les 405.

## 4.2 L'enveloppe de réponse

Une seule forme, partout, et **une seule règle de lecture** :

> Si la réponse contient `error`, c'est un échec. Sinon `data` fait foi.

`data` et `error` sont **mutuellement exclusifs**. `meta` peut accompagner les
deux.

```
succès              { "data": { … }, "meta": { … } }
succès sans contenu { "data": null }
liste paginée       { "data": [ … ], "meta": { "page": { … } } }
échec               { "error": { "code", "message", "details" }, "meta": { … } }
```

Trois conséquences pour le client Dart :

- **`{ "data": null }` n'est pas une erreur.** C'est « c'est fait », ou un état
  vide légitime — `GET /me/courses/resume` répond exactement cela quand il n'y a
  rien à reprendre. Un client qui traite `null` comme un échec afficherait une
  erreur sur un compte neuf.
- **Il n'y a jamais de corps vide.** Le décodeur JSON n'a pas à gérer le cas
  « rien à décoder ».
- **Le client ignore les champs qu'il ne connaît pas.** C'est écrit dans le
  contrat (§6.2) et c'est ce qui rend toute évolution possible : un client qui
  échoue sur un champ inconnu fige l'API pour toujours. Concrètement : pas de
  désérialisation stricte, pas d'exception sur clé inattendue.

### Les treize codes d'erreur

`error.code` est une **liste fermée**. C'est là-dessus que le client branche.
`error.message` est **déjà rédigé en français et affichable tel quel** : le
client ne traduit pas, ne reformule pas, n'invente pas de message de repli quand
il en reçoit un.

| `code` | HTTP | Comportement du client |
|---|---:|---|
| `non_authentifie` | 401 | Purger le jeton, écran de connexion. **Aucune tentative de renouvellement** |
| `solde_insuffisant` | 402 | **Proposer la recharge**, pas afficher une erreur |
| `compte_inactif` | 403 | Renvoyer vers la saisie du code d'activation |
| `compte_en_suppression` | 403 | Expliquer, renvoyer vers le courriel reçu |
| `droit_manquant` | 403 | Afficher le message tel quel |
| `introuvable` | 404 | Écran de repli |
| `methode_refusee` | 405 | Bogue client — à faire remonter, pas à afficher |
| `validation` | 422 | Souligner les champs de `details` |
| `action_impossible` | 422 | Afficher le message tel quel |
| `conflit` | 409 | Afficher le message tel quel |
| `client_obsolete` | 426 | Écran « mettez à jour l'application » (§4.6) |
| `debit_depasse` | 429 | Attendre `meta.retry_after` secondes (§4.5) |
| `erreur_serveur` | 500 | Réessayer plus tard |

`details` n'est présent **que** sur `validation` : clé = champ envoyé, valeur =
liste de messages. C'est ce qui alimente les erreurs sous les champs de
formulaire.

**Le 402 mérite d'être traité à part.** C'est le seul code HTTP qui dise « il
manque de quoi payer », et le contrat l'emploie exprès pour que le client
distingue un manque d'unités d'un échec technique. Un message générique
« une erreur est survenue » s'afficherait au moment où la personne est le plus
près d'acheter. La règle : **402 ouvre l'écran de recharge**, sur l'écran 09
comme sur l'écran 15.

### Une règle de forme qui change la manière d'écrire les écrans

> Ce qui n'appartient pas au demandeur répond **404**, jamais 403.

Un uuid deviné ne doit renseigner sur rien, pas même sur l'existence de la
ressource (arbitrage n° 74). Donc : **un 404 ne signifie pas forcément
« supprimé »**. Il signifie « pas pour vous, ou pas là ». Les écrans de repli
doivent être écrits dans ce registre — « ce contenu n'est pas disponible » — et
jamais « ce contenu a été supprimé », qui serait une affirmation.

## 4.3 Jetons et session

Le contrat tranche (arbitrage n° 69) : **jetons personnels Sanctum, pas de
cookie, pas de session, pas de protection CSRF** — sans cookie envoyé
automatiquement, il n'y a rien à falsifier.

### Ce que le client fait

- Le jeton vit dans **`flutter_secure_storage`**. Jamais dans les préférences
  partagées, **jamais dans un journal**. Le contrat le qualifie explicitement de
  secret.
- **Le jeton en clair n'existe qu'une fois**, dans la réponse qui l'émet. Le
  serveur n'en garde que l'empreinte et il n'existe aucun endpoint pour le
  relire. S'il est perdu, il faut se reconnecter — il n'y a pas de récupération.
- **Durée : 30 jours, glissante.** Chaque appel authentifié repousse l'échéance
  dès qu'il reste moins de la moitié de la durée. Une application dont on se sert
  ne redemande jamais le mot de passe ; un téléphone perdu et oublié se referme
  tout seul au bout d'un mois. Le client n'a **rien à faire** pour cela : c'est le
  serveur qui prolonge.
- Trois points d'entrée émettent un jeton, et ils rendent **exactement la même
  forme** — `token`, `type`, `expire_le`, `utilisateur` :
  `POST /auth/login`, `POST /auth/otp/verify`, `POST /auth/password/reset`.
  Un seul chemin de code les traite tous les trois.
- `appareil` est un champ facultatif de ces trois appels : il **nomme** le jeton
  (« Pixel 7 de Rita »). L'envoyer systématiquement, avec le modèle réel de
  l'appareil : une liste de sessions lisible se révoque en connaissance de
  cause, « jeton 47 » non.

### Ce que le client ne fait plus

> **Il n'y a pas de jeton de rafraîchissement.**

C'est la divergence la plus importante avec les points de vigilance du
simulateur, qui décrivent un « refresh silencieux sur 401 » (écran 02). Le
contrat l'écarte délibérément : un couple accès/rafraîchissement protège les
architectures où le jeton traverse des services tiers ; il n'y a ici qu'un
serveur, et le couple aurait ajouté un second secret sur le téléphone, une route
et un état de plus, pour un gain nul.

**Sur 401 : purger le jeton, purger la base locale, présenter la connexion. Ne
pas boucler sur une tentative de renouvellement — il n'y a rien à renouveler.**

L'intercepteur d'authentification se réduit donc à : poser l'en-tête, et sur 401,
déclencher une déconnexion propre. Le verrou de renouvellement concurrent, les
requêtes mises en attente et rejouées avec un nouveau jeton : tout cela
disparaît. C'est nettement plus simple, et c'était l'intention.

### Révocation

| Événement | Effet |
|---|---|
| `POST /auth/logout` | Révoque **le seul jeton présenté** |
| `POST /auth/password/reset` | Révoque **tous** les jetons, toutes les sessions web, tous les cookies « se souvenir de moi » |
| `DELETE /me` (2ᵉ temps) | Révoque **tous** les jetons |
| Expiration | 401 `non_authentifie` |

Se déconnecter d'un appareil ne ferme pas les autres : ce serait une surprise
désagréable, et cela existe déjà sous un autre nom — la réinitialisation du mot
de passe. L'écran 30 doit donc dire la vérité sur ce que fait chaque bouton.

**Un jeton révoqué et un jeton absent se répondent pareil.** Le client ne peut
pas les distinguer, et n'a pas à essayer.

### Deux états de compte qui ne sont pas des erreurs de réseau

- **`403 compte_inactif`** — le code à six chiffres n'a jamais été validé. Le
  client renvoie vers l'écran 05 avec l'adresse connue. À noter : à la
  *connexion*, un compte non activé reçoit **401, identique à un mot de passe
  faux** (§1.6 du contrat) — distinguer les deux transformerait l'écran de
  connexion en annuaire des inscrits. `compte_inactif` n'apparaît donc que sur un
  jeton déjà émis.
- **`403 compte_en_suppression`** — le délai de rétractation de trente jours
  court. **Toute** route répond 403, **sauf deux** : `GET /me`, pour que
  l'application affiche « votre compte sera effacé le … » plutôt qu'un mur
  d'erreur, et `POST /auth/logout`, parce que refuser de fermer un accès
  reviendrait à imposer de garder un jeton vivant sur un téléphone dont on ne
  veut plus (arbitrage n° 75).

  Le client doit donc traiter ce cas **au niveau du routeur**, pas écran par
  écran : lire `data.suppression_demandee_le` de `GET /me` et présenter un écran
  d'état unique. **L'annulation ne passe pas par l'API** — elle se fait par le
  lien signé reçu par courriel, et l'écran doit le dire.

## 4.4 Pagination — par curseur

`page` n'existe pas. Les panneaux du simulateur qui écrivent `?page=` sont une
abréviation de leur auteur (arbitrage n° 68).

**Requête** : `?limit=20&cursor=<opaque>`. `limit` vaut 20 par défaut, plafonne à
50, et une valeur plus grande est ramenée à 50 **sans erreur**.

**Réponse** : `meta.page` porte `per_page`, `next_cursor`, `prev_cursor`,
`has_more`. Fin de liste : `next_cursor: null` **et** `has_more: false`.

Trois règles côté client :

1. **Le curseur est opaque.** On le renvoie tel quel, on ne le lit jamais, on ne
   le fabrique jamais. Le jour où l'ordre de tri change, un client qui en
   dépendait casse en silence.
2. **Il n'y a pas de total et on ne peut pas sauter à la page 7.** C'est assumé :
   aucun des trente écrans n'en a besoin, ils défilent tous. Conséquence
   concrète : `infinite_scroll_pagination` s'emploie en mode curseur, et **aucun
   écran n'affiche « page 2 sur 14 »**.
3. **Le décompte affiché n'est pas un total.** L'écran 12 affiche « <n>
   résultat(s) » : c'est le nombre d'éléments **chargés**, et le libellé doit le
   supporter honnêtement plutôt que de promettre un total que l'API ne donne pas.

**Quatre listes ne sont pas paginées** parce qu'elles sont fermées et tenues à la
main : `GET /tools`, `GET /live-classes`, `GET /me/certificates`,
`GET /me/gamification`. Ne pas leur écrire de pagination — un curseur qui ne se
déclenche jamais est du code mort.

## 4.5 Limitation de débit

Trois plafonds, parce qu'il y a trois risques :

| Groupe | Défaut | Clé de comptage | Ce qu'il protège |
|---|---:|---|---|
| Lecture | 120 / min | jeton | La charge d'un client qui boucle |
| Écriture | 30 / min | jeton | L'abus applicatif (spam, signalements en rafale) |
| Identité | 10 / min | **adresse IP** | Le forçage de mot de passe, l'envoi massif de courriels |

La lecture est généreuse à dessein : l'écran 10 interroge l'avancement toutes les
trois secondes, et se faire éconduire à ce moment-là ferait croire à une panne.
La clé est **l'identifiant du jeton**, pas celui du compte : un téléphone et une
tablette ne se ferment pas l'un l'autre.

Deux plafonds métier s'ajoutent, et ils changent des écrans :

- **20 analyses par heure et par compte** (`POST /verifications`), **partagé avec
  le site web** — on ne double pas son quota en alternant les deux clients ;
- **3 demandes de code par 10 minutes et par adresse** (`/auth/otp/*`,
  `/auth/password/forgot`). Point important : ces routes **répondent toujours
  pareil**, même quand le plafond est saturé. Un 429 visible dirait « cette
  adresse intéresse quelqu'un ». Le client ne peut donc pas afficher de compte à
  rebours ici ;
- **verrouillage progressif** sur `POST /auth/login`, par compte et par IP. Le
  message du serveur contient déjà le délai : l'afficher tel quel.

### Ce que le client doit faire, précisément

1. **Lire `X-RateLimit-Remaining` à chaque réponse.** Sous 10 % du plafond,
   espacer de soi-même. Le contrat le dit sans détour : *« un plafond sans
   en-têtes est un mur invisible. »* Concrètement, c'est ce qui doit ralentir le
   sondage de l'écran 10 avant qu'il ne se fasse éconduire.
2. **Sur 429 : attendre `meta.retry_after` secondes, puis un seul essai.** Pas de
   boucle serrée, pas de réessai immédiat. `meta.retry_after` porte le même délai
   que l'en-tête `Retry-After`, en secondes, pour les clients qui lisent le corps
   plus facilement que les en-têtes.
3. **Sur le plafond d'analyses**, afficher le message du serveur et **ne pas
   débiter** : le quota n'est pas de l'argent, et l'écran 09 doit dire quand ce
   sera de nouveau possible.

## 4.6 Versionnement et plancher de version

- La version majeure est **dans l'URL** : `/api/v1/…`. Une v2 se servira en
  parallèle sans casser les téléphones restés en v1.
- Le client annonce sa version dans **`X-Client-Version`** — **facultatif au
  contrat**, mais à envoyer systématiquement : c'est ce qui rend le plancher
  utilisable le jour où il faudra couper une version dangereuse.
- Le serveur annonce la sienne dans `X-Api-Version` sur chaque réponse.
- Si la version annoncée passe sous `API_MINIMUM_CLIENT` : **426
  `client_obsolete`**, avec l'en-tête `X-Minimum-Client-Version`.

**426 et non 400, et ce n'est pas un détail.** L'application doit afficher
« mettez à jour » plutôt que « erreur réseau » — *« c'est la différence entre
quelqu'un qui agit et quelqu'un qui désinstalle. »*

Ce que cela impose côté client, dès la première version livrée :

- un **écran bloquant** dédié au 426, qui explique et mène au magasin. Il doit
  exister avant qu'on en ait besoin : le jour où le plancher est relevé, on ne
  peut plus déployer de code sur les téléphones concernés ;
- l'intercepteur intercepte le 426 **globalement**, pas écran par écran ;
- un client qui n'annonce rien est servi normalement. Ne pas s'appuyer là-dessus,
  mais le savoir évite de croire à un bogue si l'en-tête manque.

Par défaut, **aucun plancher n'est fixé**. Le couper est une mesure grave.

## 4.7 Ce que le client doit ignorer, et ce qu'il ne verra jamais

Le contrat liste (§9, arbitrage n° 74) ce qui n'est **jamais** sérialisé. Deux
points ont des conséquences directes sur les écrans :

- **L'auteur d'un message du forum est un `AuteurResource`** : uuid, nom
  d'affichage, initiales. **Ni adresse, ni rôle.** Or les cartes de sujet du
  simulateur affichent un badge de rôle (« 🎓 Mentor Vérif'All », « ◉ Contributeur
  actif »). Publier le rôle dresserait la liste nominative de l'équipe de
  modération. Voir la réserve correspondante en section 9.
- **Le détail des exécutions de moteurs n'est pas exposé** — coûts, clés,
  réponses brutes. L'écran 11 affiche des « moteurs mobilisés » avec leur score :
  ce que l'API rend à ce sujet est ce qu'elle rend, et le client n'en déduit rien
  de plus.

## 4.8 Formats et conventions

- **Dates** : ISO 8601 avec fuseau, toujours UTC côté serveur. Le client affiche
  dans le fuseau du téléphone. **Aucune date affichée ne se calcule sur l'horloge
  locale** quand le serveur en fournit une.
- **Nombres** : entiers pour les unités, les scores, les pourcentages. Jamais de
  décimal pour de l'argent.
- **Booléens** : vrais booléens JSON.
- **Absent ≠ nul.** Un champ **présent et nul** signifie « connu, mais vide » ; un
  champ **absent** signifie « non applicable ici » — le `contenu` d'une leçon dans
  une liste, par exemple. Le modèle Dart doit distinguer les deux, ce qui écarte
  la facilité de tout traiter en nullable indifférencié.
- **Énumérations : valeur technique et libellé français voyagent ensemble.**
  `"verdict": "false"` et `"verdict_libelle": "Faux"`. **Le client affiche le
  libellé et branche sur la valeur ; il ne fabrique jamais le libellé
  lui-même.** C'est ce qui met fin, côté mobile, au piège du `match` sans branche
  par défaut (arbitrage n° 38) : ajouter un cas côté serveur n'écroule plus
  l'écran, il affiche simplement un libellé que le client n'avait pas prévu. Le
  `switch` sur la valeur technique garde malgré tout un cas par défaut, et un test
  paramétré (§6.3).
- **HTML** : les champs `corps`, `contenu`, `description` peuvent contenir du HTML
  issu de Quill, **purifié avant persistance**. Le client le rend en **liste
  blanche de balises** (`flutter_html`) : la purification à l'écriture protège de
  ce qui est en base, pas de ce qui y entrerait un jour par une autre porte.
- **Langue** : tout est en français, il n'y a pas de négociation de langue en v1.
  Ne pas construire d'infrastructure de traduction pour l'instant.

## 4.9 Réessais

**Ce qui se réessaie automatiquement** : les `GET` et les échecs de transport
(délai dépassé, connexion perdue, 502/503/504). Trois tentatives au plus, recul
exponentiel, avec une part d'aléa — sans l'aléa, tous les clients reviennent à la
même seconde après une panne et achèvent le serveur.

**Ce qui ne se réessaie jamais automatiquement** :

- un **429** — on respecte `retry_after`, un seul essai ensuite ;
- un **4xx de validation** — c'est la saisie qui est en cause ;
- un **426** — il faut une nouvelle version, pas une nouvelle tentative ;
- une **écriture sans `Idempotency-Key`** — elle produirait un doublon.

Le rejeu des écritures passe par la file `pending_ops` (section 5), pas par
l'intercepteur : l'intercepteur réessaie dans la seconde, la file rejoue dans
l'heure. Ce sont deux mécanismes distincts et il ne faut pas les confondre.

## 4.10 Envoi de fichiers — deux appels, jamais un

Le contrat impose (arbitrage n° 72) : **`POST /uploads` d'abord, `POST
/verifications` ensuite**, avec l'identifiant de téléversement.

*Pourquoi* : une vidéo de vingt mégaoctets sur une connexion instable échoue une
fois sur trois. Si le fichier voyageait avec la soumission, chaque reprise le
renverrait en entier — et une soumission rejouée après une réponse perdue le
renverrait encore. Découpé, le téléversement se reprend seul, et la soumission
qui suit ne pèse que quelques centaines d'octets, ce qui la rend rejouable sans
douleur.

`POST /uploads` — multipart, champs `type` (`image` | `video` | `audio`) et
`fichier`. Plafonds **identiques à l'écran web** :

| Type | Plafond | Formats |
|---|---:|---|
| image | 10 Mo | jpg, jpeg, png, webp |
| video | 100 Mo | mp4, webm, mov |
| audio | 25 Mo | mp3, wav, ogg, m4a |

Réponse `201` : `data.televersement` (uuid), `data.nom`, `data.octets`.

Ce que le client doit en faire :

- **valider format et poids avant d'envoyer**, avec les valeurs ci-dessus. Envoyer
  120 Mo pour se faire refuser après trois minutes est le pire scénario possible ;
- **compresser** avant l'envoi (point de vigilance de l'écran 09) ;
- afficher la **progression** et permettre l'**annulation** ;
- savoir que le fichier **attend deux heures** sur le disque privé. Une soumission
  mise en file et rejouée au-delà devra **re-téléverser** — la file doit donc
  garder la copie locale du fichier, pas seulement l'identifiant ;
- ne jamais construire d'adresse vers le fichier : `file_path` n'est pas exposé,
  et rien ne se sert autrement que par route signée.

## 4.11 Temps réel

Reverb, via `pusher_channels_flutter`, sur le canal privé
`private-verification.{uuid}` (écran 10).

Le contrat pose la hiérarchie : *« l'avancement se suit par sondage
(`data.avancement`, `data.etape`), ou par Reverb quand le canal est branché. »*
Autrement dit, **le sondage est le mode nominal et le temps réel une
amélioration** — et le plafond de lecture à 120/min a été dimensionné pour un
sondage toutes les trois secondes.

Côté client :

- connexion à l'ouverture de session, **fermeture à la déconnexion** — un socket
  ouvert après déconnexion continue de recevoir les événements d'un compte qui
  n'est plus le sien ;
- reconnexion avec recul, et **rattrapage par un `GET`** au retour : un socket
  rétabli ne rejoue pas ce qui est passé pendant la coupure ;
- **suspension en arrière-plan** — on ferme le socket. Sans notifications
  serveur (section 9), il n'y a pas de relais FCM : l'application doit donc
  rattraper l'état à son retour au premier plan, et c'est le seul mécanisme
  disponible.

## 4.12 Cache

Deux caches distincts, à ne pas confondre :

- le **cache applicatif** (Drift), qui rend la lecture hors ligne possible. Ses
  durées de vie sont **fixées par le contrat** (§7.1) et reprises en section 5 ;
- le **cache HTTP**, réservé aux ressources immuables : couvertures et images, via
  `cached_network_image`. Pas de mise en cache des réponses JSON par le transport
  — la cohérence deviendrait ingérable entre deux caches.

## 4.13 Sécurité de la couche réseau

- **Aucune clé tierce embarquée.** Tout ce qui est facturé à l'appel passe par le
  serveur.
- **Aucune adresse construite côté client** vers un disque privé.
- **Le jeton ne sort jamais dans un journal.** L'intercepteur de journalisation
  écrit méthode, chemin, code, durée — **jamais les en-têtes, jamais le corps**.
- **Épinglage de certificat** : à décider avec l'infrastructure. Un épinglage mal
  géré rend l'application inutilisable le jour d'un renouvellement, et une mise à
  jour de magasin prend plusieurs jours. Soit on l'implémente avec une clé de
  secours et une procédure de rotation écrite, soit on s'en passe.
- **L'absence de bouton n'est pas une protection.** Toute action sensible est
  autorisée côté serveur, à chaque appel. Le client qui masque un bouton fait du
  confort.
