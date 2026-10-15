# API_SPECIFICATION.md — Contrat de l'API v1 de Vérif'All

> **Pour qui est écrit ce document.** Pour la personne qui va écrire le client
> Flutter **sans avoir accès au serveur**. Tout ce qu'il faut pour coder contre
> cette interface est ici : les URL, les formes, les codes d'erreur, les
> plafonds, et ce qui se passe quand le réseau manque. Ce qui n'y est pas
> n'existe pas.
>
> **Statut.** Contrat **figé** pour la v1. Les évolutions compatibles (§6) se
> font sans préavis ; les ruptures passent par une v2.
>
> Implémentation : `routes/api.php`, `app/Http/Api/V1/`.
> Tests du contrat : `tests/Feature/Api/`.

---

## 0. Le périmètre, et pourquoi il est si étroit

Cette API a **un seul client** : l'application mobile. Son périmètre est
exactement celui du simulateur `prototype_html/mobile/index.html`, dont les
trente écrans déclarent chacun les appels qu'ils attendent.

**Rien n'a été ajouté au-delà.** Une API plus large qu'utile est deux choses à
la fois : une surface d'attaque et une dette d'entretien. Il n'y a donc ni
endpoint « pratique », ni recherche générique, ni accès aux données d'un autre
compte que le sien.

**Il n'y a pas non plus de partie publique.** Tout, y compris le catalogue de
cours et le Hub d'infos, exige un jeton. La consultation sans compte existe
déjà — sur le site web — et dupliquer cette porte sur l'API n'ajouterait qu'un
second endroit à protéger contre l'aspiration de contenu.

---

## 1. Authentification — jetons personnels Sanctum

### 1.1 Pourquoi un jeton et pas un cookie

Une application mobile n'a pas de session de navigateur. Un cookie de session
imposerait à un client qui n'est pas un navigateur : un domaine, une politique
`SameSite`, un jeton CSRF, et un magasin de cookies persistant. Le jeton
porteur supprime les quatre. Il supprime aussi la protection CSRF **par
construction** : sans cookie envoyé automatiquement, il n'y a rien à falsifier.

Conséquence : **le jeton est un secret**. Il se range dans
`flutter_secure_storage`, jamais dans `SharedPreferences`, jamais dans un
journal.

### 1.2 Obtenir un jeton

Trois points d'entrée en émettent un, et **ils renvoient tous exactement la
même forme** :

| Quand | Endpoint |
|---|---|
| Connexion | `POST /api/v1/auth/login` |
| Activation d'un compte neuf | `POST /api/v1/auth/otp/verify` |
| Après réinitialisation du mot de passe | `POST /api/v1/auth/password/reset` |

```json
{
  "data": {
    "token": "17|k3Xj…",
    "type": "Bearer",
    "expire_le": "2026-09-24T10:12:00+00:00",
    "utilisateur": { "uuid": "…", "prenom": "Rita", "email": "…", "unites": 12 }
  }
}
```

Le jeton en clair **n'existe qu'une fois**, dans cette réponse. Le serveur n'en
garde que l'empreinte : il n'y a aucun endpoint pour le relire, et c'est voulu
— un jeton relisible est un jeton qu'une simple faille de lecture suffit à
voler.

### 1.3 L'utiliser

```
Authorization: Bearer <token>
Accept: application/json
X-Client-Version: 1.2.0        (facultatif — voir §6)
Idempotency-Key: <uuid>        (facultatif — voir §7)
```

### 1.4 Durée de vie et renouvellement

Durée : **30 jours** (`API_TOKEN_TTL_MINUTES`), **glissante**.

Chaque appel authentifié repousse l'échéance dès qu'il reste moins de la moitié
de la durée. Concrètement : une application dont on se sert ne redemande jamais
le mot de passe ; un téléphone perdu et oublié se referme tout seul au bout d'un
mois.

**Il n'y a pas de jeton de rafraîchissement, et c'est délibéré.** Un couple
accès/rafraîchissement protège surtout les architectures où le jeton d'accès
traverse des services tiers. Il n'y a ici qu'un serveur. Le couple ajouterait un
second secret à stocker sur le téléphone, une route de plus et un état de plus à
tester, pour un gain nul.

**Que faire sur 401.** Purger le jeton stocké et présenter l'écran de connexion.
Ne **pas** boucler sur une tentative de renouvellement : il n'y a rien à
renouveler.

### 1.5 Révocation

| Événement | Effet |
|---|---|
| `POST /auth/logout` | révoque **le seul jeton présenté** |
| `POST /auth/password/reset` | révoque **tous** les jetons, toutes les sessions web, tous les cookies « se souvenir de moi » |
| `DELETE /me` (2ᵉ temps) | révoque **tous** les jetons |
| Expiration | le jeton cesse d'être accepté ; réponse 401 `non_authentifie` |

Se déconnecter d'un appareil ne ferme pas les autres : ce serait une surprise
désagréable, et cela existe déjà sous un autre nom — la réinitialisation du mot
de passe.

**Un jeton révoqué et un jeton absent se répondent pareil** (401
`non_authentifie`). Distinguer les deux indiquerait à qui essaie qu'un jeton a
bel et bien existé.

### 1.6 Compte non activé

Un compte créé mais dont le code à six chiffres n'a jamais été validé
**ne peut pas se connecter** — et le refus est **identique** à celui d'un mot de
passe faux (401, « Identifiants invalides. »). Distinguer les deux
transformerait l'écran de connexion en annuaire des inscrits.

Si un jeton existait déjà et que le compte devient inactif, toute route répond
**403 `compte_inactif`**.

### 1.7 Compte en cours de suppression

Le RGPD ouvre **trente jours de rétractation** pendant lesquels le compte
survit. Pendant ce délai :

- toute route répond **403 `compte_en_suppression`** ;
- **sauf** `GET /me` — pour que l'application puisse afficher « votre compte
  sera effacé le … » au lieu d'un mur d'erreur incompréhensible ;
- **sauf** `POST /auth/logout` — refuser de fermer un accès reviendrait à
  imposer de garder un jeton vivant sur un téléphone dont on ne veut plus.

`GET /me` porte alors `data.suppression_demandee_le`. **L'annulation ne passe
pas par l'API** : elle se fait par le lien signé reçu par courriel, seule voie
de retour quand on ne peut plus se connecter.

---

## 2. L'enveloppe de réponse — une seule forme, partout

### 2.1 La règle, et il n'y en a qu'une

> **Si la réponse contient `error`, c'est un échec. Sinon `data` fait foi.**

`data` et `error` sont **mutuellement exclusifs**. `meta` peut accompagner les
deux.

**Succès**

```json
{ "data": { … },  "meta": { … } }
```

**Succès sans contenu utile** — une action dont le résultat est « c'est fait » :

```json
{ "data": null }
```

Jamais de corps vide : un client qui décode du JSON ne doit jamais avoir à
gérer le cas « rien à décoder ».

**Échec**

```json
{
  "error": {
    "code": "solde_insuffisant",
    "message": "Vous n'avez pas assez d'unités.",
    "details": { "champ": ["…"] }
  },
  "meta": { "retry_after": 42 }
}
```

- `code` — valeur d'une **liste fermée** (§2.2). C'est là-dessus que le client
  branche son comportement. Ajouter un code est compatible ; en renommer un est
  une rupture.
- `message` — **déjà rédigé en français, affichable tel quel**. Le client ne
  traduit pas : il montre ce qu'on lui donne.
- `details` — présent **uniquement** pour les erreurs de validation. Clé =
  champ envoyé, valeur = liste de messages.

### 2.2 Catalogue des codes d'erreur

| `code` | HTTP | Ce que ça veut dire | Ce que le client doit faire |
|---|---:|---|---|
| `non_authentifie` | 401 | jeton absent, expiré ou révoqué | purger le jeton, écran de connexion |
| `solde_insuffisant` | 402 | pas assez d'unités | **proposer une recharge**, pas afficher une erreur |
| `compte_inactif` | 403 | code de vérification jamais validé | renvoyer vers la saisie du code |
| `compte_en_suppression` | 403 | délai de rétractation en cours | expliquer, renvoyer vers le courriel reçu |
| `droit_manquant` | 403 | l'action existe mais pas pour vous | afficher le message tel quel |
| `introuvable` | 404 | ressource inexistante **ou pas à vous** | écran de repli |
| `methode_refusee` | 405 | mauvais verbe HTTP | bogue client |
| `validation` | 422 | champs invalides — voir `details` | souligner les champs fautifs |
| `action_impossible` | 422 | l'action n'a pas de sens en l'état | afficher le message tel quel |
| `conflit` | 409 | déjà fait / déjà en cours | afficher le message tel quel |
| `client_obsolete` | 426 | version trop ancienne (§6) | **« mettez à jour l'application »** |
| `debit_depasse` | 429 | plafond atteint (§5) | attendre `meta.retry_after` secondes |
| `erreur_serveur` | 500 | anomalie côté serveur | réessayer plus tard |

**Toutes** les erreurs sortent dans cette forme, y compris les 404 de route
inconnue et les 405. Le serveur ne renvoie jamais de HTML sur `/api/v1/*`.

### 2.3 Le cas `402` mérite une phrase

`402 Payment Required` est le seul code HTTP qui dise exactement « il manque de
quoi payer ». Il est employé pour le solde d'unités précisément pour que le
client puisse le distinguer d'un échec et proposer l'écran de recharge. Un 400
générique aurait fait afficher « une erreur est survenue » au moment où la
personne était le plus près d'acheter.

---

## 3. Pagination — par curseur, jamais par décalage

### 3.1 Pourquoi

Sur un fil qui bouge — le forum, les vérifications, le Hub — le décalage ment.
Trois sujets publiés entre la page 1 et la page 2 décalent tout : le client
revoit trois éléments déjà affichés, ou en saute trois qu'il ne verra jamais. Le
curseur désigne une **position dans l'ordre**, pas un rang ; ce qui s'insère
au-dessus ne le déplace pas.

Contrepartie assumée : **on ne peut pas sauter à la page 7, et il n'y a pas de
total**. Aucun écran mobile n'en a besoin — ils défilent tous.

> Les panneaux du simulateur écrivent parfois `?page=`. C'est une abréviation de
> l'auteur du prototype ; **le paramètre `page` n'existe pas**. Voir
> `docs/ARBITRAGES.md` n° 68.

### 3.2 Comment

**Requête** : `?limit=20&cursor=<opaque>`

- `limit` — défaut **20**, plafond **50**. Une valeur plus grande est ramenée à
  50 sans erreur.
- `cursor` — **opaque**. Le client le renvoie tel quel et ne cherche jamais à le
  lire. En faire un identifiant lisible inviterait à le fabriquer, et à
  découvrir le jour où l'ordre de tri change qu'on s'était mis à en dépendre.

**Réponse**

```json
{
  "data": [ … ],
  "meta": {
    "page": {
      "per_page": 20,
      "next_cursor": "eyJpZCI6MTQyfQ",
      "prev_cursor": null,
      "has_more": true
    }
  }
}
```

Fin de liste : `next_cursor: null` **et** `has_more: false`.

### 3.3 Ce qui n'est pas paginé, et pourquoi

`GET /tools`, `GET /live-classes`, `GET /me/certificates`, `GET /me/gamification`
rendent des listes **fermées**, tenues à la main par l'équipe : une vingtaine
d'outils, quelques séances par mois, une poignée de certificats. Y mettre un
curseur compliquerait le client pour une pagination qui ne se déclencherait
jamais.

### 3.4 LE CURSEUR NE PORTE PAS LES FILTRES

C'est le piège le plus coûteux de cette section, et il ne se voit pas.

`next_cursor` encode **une position dans l'ordre de tri**, et rien d'autre. Il ne
mémorise ni `category`, ni `level`, ni `q`. Le client doit donc **renvoyer tous
les paramètres de filtre à l'identique** avec le curseur :

```
GET /api/v1/courses?level=beginner&limit=20
GET /api/v1/courses?level=beginner&limit=20&cursor=eyJpZCI6MTQyfQ    ✅
GET /api/v1/courses?cursor=eyJpZCI6MTQyfQ                            ❌
```

Le second appel fautif **ne renvoie pas d'erreur** : il rend la page suivante du
catalogue **non filtré**. La liste reste plausible, elle est simplement fausse à
partir de la deuxième page — exactement l'échec que personne ne remarque avant
qu'un utilisateur le signale.

**Un curseur périmé ou illisible répond `422 validation`**, avec `cursor` dans
`error.details` — jamais une erreur serveur. C'est un cas ordinaire sur mobile :
curseur gardé d'une session précédente, recopié d'une autre liste, tronqué par
un lien profond. Le client doit alors **oublier son curseur et recharger la
liste depuis le début**, jamais réessayer la même requête.

**Corollaire, et il est important** : il ne faut **jamais filtrer côté client**
sur une liste paginée. Filtrer localement ne filtre que la page déjà chargée ; à
la deuxième page, l'utilisateur voit apparaître des éléments qui ne
correspondent pas à sa sélection. Les trois listes concernées sont **le
catalogue de cours, le forum et les opportunités**. Le serveur sait filtrer :
tous les paramètres du §3.5 sont implémentés côté serveur.

### 3.5 Les filtres, endpoint par endpoint

| Endpoint | Paramètre | Valeurs acceptées | Paginé |
|---|---|---|:---:|
| `GET /courses` | `level` | `beginner` · `intermediate` · `advanced` | oui |
| `GET /courses` | `q` | texte libre, 120 car. max — titre **et** description | oui |
| `GET /me/verifications` | `verdict` | `true` · `false` · `misleading` · `unverifiable` | oui |
| `GET /me/verifications` | `q` | texte libre, 120 car. max — affirmation **et** contexte | oui |
| `GET /forum/threads` | `category` | un **slug de rubrique**, listé dans `meta.categories[].slug` | oui |
| `GET /forum/threads` | `q` | texte libre, 120 car. max — **titre seulement** | oui |
| `GET /hub/articles` | `category` | un **nom de catégorie** (`Politique`, `Santé`, …) | oui |
| `GET /hub/opportunities` | `scope` | `benin` · `international` | oui |
| `GET /hub/opportunities` | `type` | `cdi` · `cdd` · `stage` · `alternance` · `volontariat` · `bourse` · `financement` · `conference` · `appel` · `autre` | oui |
| `GET /live-classes` | `state` | `upcoming` · `live` · `past` | non |
| `GET /tools` | `category` | un **nom de catégorie**, listé dans `meta.categories[]` | non |
| `GET /me/stats` | `period` | `7d` · `30d` · `90d` · `365d` | non |

> **Attention aux valeurs de `scope`.** Le panneau du simulateur écrit
> `?scope=bj|intl`. C'est une notation abrégée signifiant « l'un ou l'autre » ;
> les valeurs réelles sont **`benin`** et **`international`**. Envoyer `bj`
> produit un `422` — pas un silence, mais autant le savoir avant d'écrire le
> client.

### 3.6 « Pas de filtre » : comment le dire

**Omettre le paramètre, ou l'envoyer vide (`?level=`), signifie « aucun
filtre ».** C'est la forme universelle, valable pour les douze paramètres du
tableau.

Les **trois filtres `category`** — forum, Hub, outils — acceptent en plus la
valeur sentinelle **`toutes`**, qui vaut « aucun filtre ». L'interface est faite
de pastilles : la pastille « Toutes » envoie une valeur comme les autres, et
exiger qu'elle n'envoie rien obligerait le client à traiter une pastille à part
— ce qu'il finirait par oublier.

```
GET /api/v1/forum/threads?category=toutes    ≡    GET /api/v1/forum/threads
```

### 3.7 Ce qui se passe sur une valeur inconnue

Deux comportements, et la différence n'est pas un oubli.

**Énumération fermée** — `level`, `scope`, `type`, `state`, `verdict`, `period`.
Une valeur hors liste répond **`422 validation`**, avec le champ fautif dans
`error.details`. Ces listes viennent d'enums serveur : une valeur inconnue ne
peut venir que d'un client modifié ou d'une faute de frappe, et l'accepter en
silence rendrait une liste vide sans dire pourquoi.

**Catégorie ouverte** — les trois `category`. Une valeur inconnue rend une
**liste vide** (`200`, `data: []`). Ces catégories sont des données, pas un enum :
elles changent quand l'équipe édite le contenu, et refuser une catégorie
supprimée la veille casserait un écran pour une raison qui n'est pas une erreur
du client. **Le client doit donc afficher un état vide honnête**, jamais un
message d'erreur — et alimenter ses pastilles depuis `meta.categories`, pas
depuis une liste écrite à la main : deux listes finissent par diverger, et c'est
le filtre qui cesse de trouver quoi que ce soit.

### 3.8 Combiner les filtres

Tous les paramètres d'un même endpoint se **cumulent en ET**. `?level=beginner&q=intox`
rend les cours débutants dont le titre ou la description contient « intox ».

`q` n'est **pas** un moteur de recherche : c'est un `LIKE` insensible à la
casse, sans pondération ni correction orthographique. Il est destiné au champ de
recherche débouncé de l'écran, pas à une recherche documentaire.

---

## 4. Identifiants — jamais un entier auto-incrémenté

Aucune URL ne porte de clé primaire. Un entier séquentiel dans une URL révèle le
volume (« il y a 412 cours ») et invite à l'énumération (« et le 413 ? »).

| Ressource | Identifiant | Exemple |
|---|---|---|
| Vérification | `uuid` | `/verifications/9b1c…` |
| Cours | `slug` | `/courses/reconnaitre-une-intox` |
| Leçon | `uuid` | `/courses/…/lessons/4f2a…` |
| Quiz | `uuid` | `/me/quiz/7c8d…/attempt` |
| Sujet du forum | `slug` | `/forum/threads/cette-video-montre-t-elle…` |
| Réponse du forum | `uuid` | — |
| Brève du Hub | `slug` | `/hub/articles/pas-de-tablettes-gratuites` |
| Opportunité | `slug` | `/hub/opportunities/charge-de-communication` |
| Séance en direct | `uuid` | `/live-classes/2e5b…/remind` |
| Certificat | `code` public | `/me/certificates/VF7KMPQR` |
| Compte | `uuid` | — |

**Le certificat fait exception au « uuid partout »**, et c'est voulu : son `code`
est conçu pour être **lu à voix haute et recopié** (alphabet sans O/0, I/1,
S/5). C'est lui qu'un recruteur saisit. Un uuid de trente-six caractères ne se
dicte pas au téléphone.

**Une seule exception à « aucun entier »** : l'identifiant d'une *réponse de
quiz* (`data.questions[].reponses[].id`). La table n'a pas d'uuid, et lui en
ajouter un migrerait une table de production pour un besoin que rien d'autre
n'a. Il n'énumère rien d'utile — une réponse n'existe que dans la question qui
la porte, et la question n'est servie qu'à qui est inscrit au cours.

---

## 5. Limitation de débit

### 5.1 Trois plafonds, parce qu'il y a trois risques

| Groupe | Défaut | Clé de comptage | Ce qu'il protège |
|---|---:|---|---|
| **lecture** | 120 / min | jeton | la charge d'un client qui boucle |
| **écriture** | 30 / min | jeton | l'abus applicatif (spam, signalements en rafale) |
| **identité** | 10 / min | **adresse IP** | le forçage de mot de passe, l'envoi massif de courriels |

La lecture est **généreuse** à dessein : l'écran de suivi d'analyse interroge
l'avancement toutes les trois secondes, et se faire éconduire à ce moment-là
ferait croire à une panne.

La clé est **l'identifiant du jeton**, pas celui du compte : un même compte peut
avoir un téléphone et une tablette, et l'usage de l'un ne doit pas fermer
l'autre. Sur les routes d'identité il n'y a pas encore de jeton — la clé est
l'adresse IP.

### 5.2 Deux plafonds métier qui s'ajoutent

| Action | Plafond | Où |
|---|---|---|
| Lancer une analyse | 20 / heure / compte | `POST /verifications` |
| Demander un code (activation, mot de passe oublié) | 3 / 10 min / adresse e-mail | `POST /auth/otp/*`, `/auth/password/forgot` |
| Tentatives de connexion | verrouillage progressif par compte + IP | `POST /auth/login` |

Le plafond horaire d'analyses est **partagé avec le site web** : la clé est la
même, on ne double pas son quota en alternant les deux clients.

### 5.3 Les en-têtes, et pourquoi ils comptent

```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 118
Retry-After: 42          (uniquement sur 429)
```

Ils permettent au client de **s'auto-réguler** — d'espacer ses appels de
lui-même — plutôt que de se faire éconduire et d'afficher une erreur. **Un
plafond sans en-têtes est un mur invisible.**

Sur 429, `meta.retry_after` porte le même délai que l'en-tête, en secondes, pour
les clients qui lisent le corps plus facilement que les en-têtes.

### 5.4 Ce que le client doit faire

1. Lire `X-RateLimit-Remaining`. Sous 10 %, espacer.
2. Sur 429 : **attendre `retry_after`**, puis un seul essai. Pas de boucle
   serrée, pas de réessai immédiat.
3. Sur `login` verrouillé, afficher le délai en clair. Le message du serveur le
   contient déjà.

---

## 6. Versionnement

### 6.1 La version majeure est dans l'URL

`/api/v1/…`. Un préfixe se lit dans un journal d'accès, se route par un
mandataire, et se sert **en parallèle** de la suivante — ce qu'un en-tête de
négociation ne permet pas aussi simplement. C'est ce qui autorisera à publier
une v2 sans casser les téléphones qui tournent encore en v1.

### 6.2 Ce qui est compatible (sans préavis)

- **ajouter** un champ à une réponse ;
- **ajouter** un paramètre facultatif ;
- **ajouter** une valeur au catalogue des `code` d'erreur ;
- **ajouter** un endpoint ;
- assouplir une validation.

> **Le client doit ignorer les champs qu'il ne connaît pas.** Un client qui
> échoue sur un champ inconnu rend toute évolution impossible.

### 6.3 Ce qui est une rupture (v2 obligatoire)

- retirer ou renommer un champ, un endpoint, un paramètre ;
- renommer une valeur de `code` d'erreur ou d'énumération ;
- changer un type (`int` → `string`), un format de date, un statut HTTP ;
- rendre obligatoire un paramètre qui ne l'était pas ;
- resserrer une validation.

### 6.4 Le plancher de version client

Une application installée **ne se met pas à jour parce qu'on le souhaite**. Le
jour où une version ancienne devient dangereuse — protocole de paiement changé,
faille corrigée côté client — il faut pouvoir l'arrêter **sans déployer de
code** et sans casser les autres.

- Le client annonce sa version dans **`X-Client-Version`** (facultatif).
- Le serveur annonce la sienne dans **`X-Api-Version`** sur chaque réponse.
- Si la version annoncée est inférieure au plancher (`API_MINIMUM_CLIENT`) :
  **426 `client_obsolete`** + en-tête `X-Minimum-Client-Version`.
- **Un client qui n'annonce rien est servi.** L'en-tête est facultatif au
  contrat ; punir un client tiers pour l'avoir cru serait un piège.

426 et non 400 : l'application doit afficher « mettez à jour » plutôt que
« erreur réseau ». C'est la différence entre quelqu'un qui agit et quelqu'un qui
désinstalle.

Par défaut, **aucun plancher n'est fixé**. Le couper est une mesure grave et doit
rester l'exception.

---

## 7. Hors ligne — ce qui se synchronise, dans quel sens, qui gagne

Le parcours **M3 (Académie hors ligne)** exige qu'on puisse lire un cours et
valider une leçon sans réseau. Voici le contrat de synchronisation. **Il est ici
et pas dans le code du client** : c'est le serveur qui arbitre.

### 7.1 Ce qui se met en cache localement (serveur → client)

| Donnée | Endpoint | Durée de vie raisonnable |
|---|---|---|
| Catalogue de cours | `GET /courses` | 1 h |
| Fiche d'un cours + leçons | `GET /courses/{slug}` | 1 h |
| Contenu d'une leçon | `GET /courses/{slug}/lessons/{uuid}` | jusqu'à la fin du cours |
| Annuaire d'outils | `GET /tools` | 24 h |
| Brèves et opportunités | `GET /hub/*` | 15 min |
| Mes vérifications | `GET /me/verifications` | lecture seule hors ligne |

Ces lectures ne dépendent que de contenu publié. **Le cache est lu d'abord, le
réseau ensuite** (offline-first).

**Ce qui NE doit jamais être servi depuis le cache** : le solde d'unités, l'état
d'une analyse en cours, le résultat d'un quiz. Ce sont les trois écrans qu'on
relit *précisément* parce qu'on attend un changement ; y montrer une valeur
d'il y a cinq minutes fait croire à un paiement perdu ou à une analyse bloquée.

### 7.2 Ce qui se met en file d'attente (client → serveur)

Les écritures suivantes partent dans une file locale (`pending_ops`) et sont
rejouées à la reconnexion :

| Action | Endpoint | Comment elle survit au rejeu |
|---|---|---|
| Terminer une leçon | `POST /me/lessons/{uuid}/complete` | contrainte d'unicité en base |
| S'inscrire à un cours | `POST /courses/{slug}/enroll` | idempotent par service |
| Demander un rappel de séance | `POST /live-classes/{uuid}/remind` | contrainte d'unicité en base |
| Voter, signaler | `/forum/…/vote`, `/…/report` | contrainte d'unicité en base |
| Soumettre une vérification | `POST /verifications` | **`Idempotency-Key`** |
| Rendre une copie de quiz | `POST /me/quiz/{uuid}/attempt` | **`Idempotency-Key`** |

### 7.3 `Idempotency-Key`

**Le problème.** Une réponse perdue est indiscernable d'une requête perdue. Le
téléphone qui ne reçoit rien ne sait pas si la vérification a été lancée — et
s'il rejoue, la personne est **débitée deux fois pour une seule question**.

**La règle.** Le client génère un UUID **par acte, pas par tentative**, et le
place dans `Idempotency-Key`. Toutes les reprises du même acte portent la même
clé.

- Première tentative : exécutée, réponse mémorisée **24 h**.
- Reprise avec la même clé : la réponse mémorisée est renvoyée **à
  l'identique**, avec l'en-tête `Idempotent-Replay: true`. Rien n'est
  réexécuté.
- **Seuls les succès sont mémorisés.** Rejouer un échec figerait une erreur
  passagère — un solde momentanément insuffisant, une coupure d'un moteur
  d'analyse — et le client réessaierait éternellement contre un cache.
- La clé est portée par le **compte** : deux personnes qui tireraient le même
  UUID ne se volent pas leur réponse.
- Longueur maximale : 128 caractères.
- L'en-tête est **facultatif**. Sans lui, la requête s'exécute normalement.

### 7.4 Qui gagne en cas de conflit

> **Le serveur fait foi sur tout ce qu'il calcule.** Le client fait foi sur ce
> qu'il a observé localement, et sur rien d'autre.

| Donnée | Autorité | Pourquoi |
|---|---|---|
| Avancement d'un cours, statut d'inscription | **serveur** | recalculés depuis les faits ; deux formules donneraient deux pourcentages |
| Déverrouillage d'une leçon (`ouverte`) | **serveur** | c'est une règle métier ; un client qui la recalcule ouvrirait un jour une leçon qui ne devait pas l'être |
| Score et réussite d'un quiz | **serveur** | la correction ne franchit jamais le réseau avant la copie |
| Solde d'unités, coût d'une action | **serveur** | accepter un prix venu du téléphone viderait les quotas en une nuit |
| Verdict d'une vérification | **serveur** | il n'y a pas d'autre source |
| Durée de lecture d'une leçon | **le plus petit des deux** | voir ci-dessous |
| Préférences de notification | **client** (dernière écriture) | ce sont des booléens sans conséquence |

**La durée de lecture** mérite un mot. Le serveur note l'heure d'ouverture d'une
leçon quand elle est demandée, et retient le **minimum** entre ce que le client
annonce et ce qu'il a lui-même mesuré. Le client peut annoncer *moins* —
l'application met son compteur en pause quand elle passe en arrière-plan, et
c'est légitime. Il ne peut **jamais annoncer plus** : sinon il suffirait
d'envoyer « j'ai lu pendant deux heures ».

Le même mécanisme s'applique au chronomètre d'un quiz : `meta.commence_le`
renvoyé par `GET /courses/{slug}/quiz` est l'heure qui fait foi, pas celle du
téléphone.

### 7.5 Ce qui ne fonctionne pas hors ligne, et le dire

Soumettre une vérification hors ligne **met l'acte en file** mais ne produit
aucun verdict : l'analyse a besoin du réseau. L'écran doit le dire — « votre
demande partira dès le retour du réseau » — et non simuler une attente.

---

## 8. Formats et conventions

- **Dates** : ISO 8601 avec fuseau, `2026-08-25T10:12:00+00:00`. Toujours en
  UTC côté serveur ; le client affiche dans le fuseau du téléphone.
- **Nombres** : entiers pour les unités, les scores, les pourcentages. Jamais de
  décimal pour de l'argent.
- **Booléens** : vrais booléens JSON, jamais `"0"` / `"1"`.
- **Champs absents vs nuls** : un champ optionnel **présent et nul** signifie
  « connu, mais vide ». Un champ **absent** signifie « non applicable dans ce
  contexte » (par exemple `contenu` d'une leçon dans une liste). Les deux se
  distinguent, et le client ne doit pas les confondre.
- **Énumérations** : la valeur technique et le libellé français voyagent
  ensemble (`"verdict": "false"`, `"verdict_libelle": "Faux"`). Le client
  affiche le libellé et branche sur la valeur. Il ne fabrique jamais le libellé
  lui-même : ajouter un cas côté serveur casserait tous les `switch` du client.
- **HTML** : les champs `corps`, `contenu`, `description` peuvent contenir du
  HTML issu de Quill, **purifié avant persistance**. Le client le rend en
  **liste blanche de balises** (`flutter_html`) : la purification à l'écriture
  protège de ce qui est en base, pas de ce qui y entrerait un jour par une autre
  porte.
- **Langue** : tous les messages sont en français. Il n'y a pas de négociation
  de langue en v1.

---

## 9. Ce que l'API n'expose PAS — délibérément

Une ressource qui renvoie tout le modèle finit par renvoyer une adresse e-mail à
qui n'y a pas droit. Ce qui suit n'est **jamais** sérialisé :

| Jamais exposé | Pourquoi |
|---|---|
| Clés primaires entières | volume et énumération (§4) |
| Hachage de mot de passe, secret 2FA, codes de récupération | aucune raison de traverser le réseau |
| Adresse e-mail ou téléphone **de quelqu'un d'autre** | l'auteur d'un message du forum est un `AuteurResource` : uuid, nom d'affichage, initiales. Rien d'autre |
| Rôle d'un auteur | publier « untel est modérateur » sur chaque message dresserait la liste nominative de l'équipe de modération |
| `content_fingerprint` d'une vérification | permettrait de tester si une rumeur a déjà été soumise |
| `guest_hash` | empreinte d'adresse d'un visiteur ; donnée de sécurité |
| `file_path` | chemin sur le disque privé. Le fichier ne se sert que par route signée |
| Détail des exécutions de moteurs (`engine_runs`) | coûts, clés employées, réponses brutes des fournisseurs — publier quel moteur a répondu quoi revient à publier notre grille d'achat |
| `is_correct` et `explication` d'un quiz **avant** la copie | un quiz réussi avec un intercepteur de trafic dévalue tous les certificats |
| `phone_encrypted`, `provider_ref`, `failure_reason` d'un paiement | rapprochement interne |
| Adresse e-mail d'un filleul | il n'a pas consenti à ce qu'elle soit rendue à qui l'a invité |
| `join_url` d'une séance encore lointaine | un lien publié trois semaines à l'avance circule, et l'animateur découvre une salle pleine d'inconnus |
| Contenu non publié (cours, brève, opportunité) | 404, même en connaissant le slug |
| Message brut d'une exception en production | noms de classes, chemins, fragments SQL |

**Et une règle de forme** : ce qui n'appartient pas au demandeur répond **404**,
jamais 403. Un uuid deviné ne doit renseigner sur rien — pas même sur
l'existence de la ressource.

---

## 10. Les endpoints

Tous préfixés par `/api/v1`. La colonne « écran » renvoie au simulateur mobile.

### 10.1 Identité — sans jeton

| Verbe | Chemin | Écran | Corps attendu |
|---|---|---|---|
| POST | `/auth/login` | 01 Connexion | `email`, `mot_de_passe`, `appareil?` |
| POST | `/auth/register` | 02 Inscription | `prenom`, `nom`, `telephone`, `profession`, `email`, `mot_de_passe`, `cgu`, `confidentialite`, `newsletter?`, `code_parrainage?` |
| POST | `/auth/otp/request` | 01, 02 | `email` |
| POST | `/auth/otp/resend` | 04 Code | `email` |
| POST | `/auth/otp/verify` | 04 Code | `email`, `code`, `appareil?` |
| POST | `/auth/password/forgot` | 03 Mot de passe oublié | `email` |
| POST | `/auth/password/reset` | 05 Nouveau mot de passe | `email`, `code`, `mot_de_passe`, `appareil?` |

**Notes essentielles.**

- `register` ne rend **pas** de jeton : le compte existe mais n'est pas activé.
  C'est `otp/verify` qui l'ouvre, une fois la maîtrise de la boîte prouvée.
  Réponse : `201 { "data": { "uuid", "email", "etape": "code_envoye" } }`.
- `otp/request` et `otp/resend` sont **le même geste** ; deux URI parce que
  l'écran a un bouton « renvoyer ».
- `otp/request` et `password/forgot` répondent **toujours pareil**, que le
  compte existe ou non, et même quand le plafond est saturé. Un 429 visible
  dirait « cette adresse intéresse quelqu'un ».
- Le mot de passe suit la politique du site : **12 caractères minimum, casse
  mixte, chiffre, symbole, et absence des fuites connues**. Le client se limite
  à vérifier la longueur avant l'envoi — le reste est serveur.
- `appareil` nomme le jeton (« Pixel 7 de Rita »). Une liste d'appareils lisible
  se révoque en connaissance de cause ; « jeton 47 » non.

### 10.2 Mon compte

| Verbe | Chemin | Écran | Paramètres |
|---|---|---|---|
| POST | `/auth/logout` | 24 Profil | — |
| GET | `/me` | 24 Profil | — |
| PATCH | `/me` | 24 Profil | `prenom?`, `nom?`, `telephone?`, `profession?`, `ville?`, `bio?` |
| DELETE | `/me` | 29 Réglages | `confirmation`, `mot_de_passe`, `code?` |
| GET | `/me/settings` | 29 Réglages | — |
| PATCH | `/me/settings` | 29 Réglages | `notifications?`, `newsletter?` |
| POST | `/me/export` | 29 Réglages | — |

**`PATCH /me`** accepte `prenom`, `nom`, `telephone`, `profession`, `ville`,
`bio`. **L'adresse e-mail n'est pas modifiable ici** : elle sert d'identifiant
de connexion *et* de canal de récupération, et la changer sans vérifier la
nouvelle boîte offrirait une prise de compte à qui emprunte un téléphone
déverrouillé. Les champs absents gardent leur valeur.

**`PATCH /me/settings`** attend une **valeur voulue**, jamais une bascule :

```json
{ "notifications": { "forum_activity": false }, "newsletter": true }
```

Une bascule perd sa cible quand la requête est rejouée après une coupure, et
l'utilisateur retrouve le réglage inverse de ce qu'il a demandé. Un canal
inconnu est **refusé** (422), pas ignoré en silence.

La lettre d'information est **séparée** des préférences : c'est un consentement
marketing, horodaté et versionné, pas un réglage d'affichage.

**`DELETE /me` se fait en deux temps, sur un seul point d'entrée.**

1. `{ "confirmation": "SUPPRIMER", "mot_de_passe": "…" }`
   → `202 { "data": { "etape": "code_envoye" } }`, un code part par courriel.
   **Rien n'est supprimé.**
2. `{ "confirmation": "SUPPRIMER", "mot_de_passe": "…", "code": "123456" }`
   → `200 { "data": { "etape": "suppression_programmee", "suppression_demandee_le": "…" } }`

Mot de passe **et** code : le premier prouve qu'on connaît le compte, le second
qu'on tient la boîte. Un téléphone volé déverrouillé passe le premier, jamais
les deux. Effacer un compte est irréversible passé trente jours ; c'est le geste
qui mérite le plus de friction de toute l'application.

Un seul point d'entrée parce que le simulateur ne déclare que `DELETE /me` :
ouvrir une route « demander un code de suppression » ajouterait une surface que
l'écran n'appelle pas.

**`POST /me/export`** lance une préparation en tâche de fond : `202`. L'archive
n'est **jamais** renvoyée dans la réponse — elle contient tout ce que la
plateforme détient d'une personne, et un corps HTTP se retrouve dans les
journaux d'un mandataire. Elle arrive par courriel, sur route signée. Une
seconde demande dans le quart d'heure répond `409 conflit`.

### 10.3 Vérifier

| Verbe | Chemin | Écran | Paramètres |
|---|---|---|---|
| POST | `/uploads` | 08 Vérifier | `type`, `fichier` |
| POST | `/verifications` | 08 Vérifier | `type`, `affirmation`, `contexte?`, `televersement?` |
| GET | `/verifications/{uuid}` | 09 Analyse en cours · 10 Rapport | — |
| POST | `/verifications/{uuid}/contest` | 10 Rapport | `detail`, `motif?` |
| GET | `/me/verifications` | 06 Accueil · 11 Mes vérifications | `verdict`, `q`, `limit`, `cursor` |

**`POST /uploads`** — multipart, champs `type` (`image` \| `video` \| `audio`) et
`fichier`. Plafonds identiques à l'écran web : image 10 Mo (jpg/jpeg/png/webp),
vidéo 100 Mo (mp4/webm/mov), audio 25 Mo (mp3/wav/ogg/m4a). Réponse :
`201 { "data": { "televersement": "<uuid>", "nom", "octets" } }`. Le fichier va
sur le **disque privé**, sous un nom aléatoire, et attend **2 heures**.

*Pourquoi deux appels au lieu d'un multipart unique* : une vidéo de vingt
mégaoctets sur une connexion instable échoue une fois sur trois. Si le fichier
voyageait avec la soumission, chaque reprise le renverrait en entier — et une
soumission rejouée après une réponse perdue le renverrait encore. Découpé, le
téléversement se reprend seul, et la soumission qui suit ne pèse que quelques
centaines d'octets, ce qui la rend rejouable sans douleur.

**`POST /verifications`** — `type`, `affirmation`, `contexte?`,
`televersement?`. **Le coût n'est jamais envoyé par le client** : il est lu du
type. Les types `image`, `video`, `audio` **exigent** un `televersement`.
Réponse `201`, avec `meta.solde` recalculé. Erreurs notables : `402
solde_insuffisant`, `429 debit_depasse` (+ `Retry-After`).

**`GET /verifications/{uuid}`** sert **les deux écrans** — le suivi et le
rapport — parce que c'est le même objet à deux moments de sa vie, et que le
client n'a pas à savoir lequel appeler avant d'avoir lu l'état.
`data.rapport` est **`null`** tant qu'aucun verdict n'a été rendu. La clé est
toujours présente : `rapport: {}` ferait croire à un verdict vide, ce qui est le
pire malentendu possible sur une plateforme de fact-checking.

L'avancement se suit par sondage (`data.avancement`, `data.etape`), ou par
Reverb sur `private-verification.{uuid}` quand le canal est branché.

**`POST /verifications/{uuid}/contest`** — `detail` (obligatoire, 10 à 1000
caractères), `motif?`. Enregistre une **demande de contre-expertise** dans la
file de modération. **Elle ne change pas l'état du rapport** : laisser un citoyen
basculer son propre rapport en « arbitrage » offrirait à quiconque un bouton
pour effacer un verdict qui le dérange. Idempotent : dix appels ne créent qu'une
demande. Voir `docs/ARBITRAGES.md` n° 71.

**`GET /me/verifications`** — filtres `verdict`, `q`, plus `limit` / `cursor`.

### 10.4 Académie

| Verbe | Chemin | Écran | Paramètres |
|---|---|---|---|
| GET | `/courses` | 13 Catalogue | `level`, `q`, `limit`, `cursor` |
| GET | `/courses/{slug}` | 14 Cours | — |
| POST | `/courses/{slug}/enroll` | 13, 14 | — |
| GET | `/courses/{slug}/lessons/{uuid}` | 15 Leçon | — |
| GET | `/courses/{slug}/quiz` | 16 Quiz | — |
| POST | `/me/lessons/{uuid}/complete` | 15 Leçon | `secondes` |
| POST | `/me/quiz/{uuid}/attempt` | 16 Quiz | `reponses` |
| GET | `/me/enrollments` | 13 Catalogue | `limit`, `cursor` |
| GET | `/me/courses/resume` | 06 Accueil · 13 Catalogue | — |
| GET | `/me/courses/{slug}/progress` | 14 Cours | — |
| GET | `/me/certificates` | 17 Certificat · 26 Récompenses | — |
| GET | `/me/certificates/{code}` | 17 Certificat | — |

- La fiche d'un cours porte `data.lecons[].ouverte` et `.acquise` : **le serveur
  tranche le déverrouillage**, l'UI ne fait que le refléter.
- Le **contenu** d'une leçon n'accompagne que `GET /courses/…/lessons/{uuid}` —
  jamais la liste, sinon c'est le corps entier du cours qui part à chaque
  ouverture du catalogue.
- Ouvrir une leçon **démarre le chronomètre serveur**. `meta.secondes_requises`
  dit combien de temps de lecture active est exigé.
- `POST /me/lessons/{uuid}/complete` attend `secondes`. Refus : `422
  action_impossible` avec un message qui dit combien de secondes sont
  nécessaires — jamais un échec muet.
- `GET /courses/{slug}/quiz` rend les questions **sans corrigé** et pose
  `meta.commence_le`.
- `POST /me/quiz/{uuid}/attempt` attend `reponses`, **indexé par uuid de
  question** : `{ "reponses": { "<uuid-question>": <id-réponse> } }`. Un tableau
  **vide est accepté** — c'est la copie de quelqu'un dont le chronomètre est
  arrivé au bout. Une question sans réponse compte comme **fausse**. La réponse
  porte `data.corrige[]` avec la bonne réponse et l'explication : c'est le
  **seul** moment où elles franchissent le réseau.
- `GET /me/courses/resume` rend **`data: null`** quand il n'y a rien à
  reprendre. Ce n'est pas une erreur, c'est un état vide.
- Les certificats sont bornés au titulaire. La **vérification publique** d'un
  certificat existe sur le site ; cette route ne doit pas devenir un second
  oracle où l'on énumère des codes.

### 10.5 Communauté, Hub, outils, séances

| Verbe | Chemin | Écran | Paramètres |
|---|---|---|---|
| GET | `/forum/threads` | 19 Forum | `category`, `q`, `limit`, `cursor` |
| POST | `/forum/threads` | 19 Forum | `titre`, `corps`, `category?`, `lien_signale?` |
| GET | `/forum/threads/{slug}` | 20 Sujet | — |
| POST | `/forum/threads/{slug}/vote` | 20 Sujet | `avis` |
| POST | `/forum/threads/{slug}/replies` | 20 Sujet | `corps` |
| POST | `/forum/threads/{slug}/report` | 20 Sujet | `motif`, `detail?` |
| GET | `/hub/articles` | 21 Hub | `category`, `limit`, `cursor` |
| GET | `/hub/articles/{slug}` | 23 Brève | — |
| GET | `/hub/opportunities` | 21 Hub | `scope`, `type`, `limit`, `cursor` |
| GET | `/hub/opportunities/{slug}` | 22 Opportunité | — |
| POST | `/hub/opportunities/{slug}/report` | 22 Opportunité | `motif`, `detail?` |
| GET | `/tools` | 12 Outils | `category` |
| GET | `/live-classes` | 18 Séances | `state` |
| POST | `/live-classes/{uuid}/remind` | 18 Séances | — |

**Corps de `POST /forum/threads`** : `titre` (8 à 160 car.), `corps` (20 à
5000 car., HTML Quill), `category` (slug de rubrique, facultatif),
`lien_signale` (URL, facultatif — la source qu'on signale).

> **`category` et non `categorie`.** Le nom canonique est `category`, aligné sur
> les cinq filtres de liste : un client qui a appris `category` sur cinq écrans
> ne doit pas se tromper sur le sixième, d'autant qu'un champ mal nommé serait
> ignoré **en silence** — le sujet partirait sans rubrique, l'appel répondrait
> `201`, et personne ne verrait rien. L'ancien nom `categorie` reste accepté le
> temps d'une version ; il disparaîtra en v2. Voir `ARBITRAGES.md` n° 81.

**Corps de `POST /forum/threads/{slug}/vote`** : `avis` — `true` · `doubt` ·
`false`. Toute autre valeur : `422`.

**Corps de `POST /forum/threads/{slug}/replies`** : `corps` (2 à 5000 car.).

**Corps des deux `report`** : `motif` (**obligatoire**, liste fermée ci-dessous)
et `detail` (facultatif, 1000 car. max).

- **`data.verdict_officiel` n'apparaît que si le serveur a lié un verdict.** Le
  bandeau officiel ne se déduit **jamais** des votes. Une communauté convaincue
  à 90 % qu'une rumeur est vraie n'en fait pas une vérification — et laisser
  l'interface le suggérer serait l'exact inverse de ce que ce produit existe
  pour faire.
- `data.votes` porte la répartition **en pourcentages, recalculée**, et
  `data.mon_vote` mon propre choix. Le vote optimiste est sans danger : la
  réponse du serveur fait foi, l'unicité est en base.
- Répondre dans un fil **verrouillé** : `422 action_impossible`. La modération a
  décidé ; contourner sa décision par l'API serait une faille.
- Les motifs de signalement sont une **liste fermée** :
  `desinformation`, `haine`, `arnaque`, `spam`, `donnees`, `autre`.
  Signaler deux fois ne crée qu'une ligne.
- La fiche d'une opportunité porte **toujours** `meta.avertissement`
  (anti-frais). Systématique, pas conditionnel : une offre frauduleuse ne se
  signale pas d'elle-même, et c'est la plus dangereuse. `data.verifiee` dit
  qu'un humain de l'équipe a contrôlé l'annonce — ça ne se déduit d'aucun autre
  champ.
- `data.expiree` et `data.jours_restants` viennent du serveur. L'archivage est
  une commande planifiée ; le client ne l'applique **jamais** lui-même — une
  horloge de téléphone mal réglée ferait disparaître une offre encore ouverte,
  ou pire, présenterait comme candidatable une offre fermée.
- `data.lien_visio` d'une séance est **`null`** tant qu'elle n'est pas
  joignable.
- `POST /live-classes/{uuid}/remind` enregistre l'intention d'assister
  (`201`). Séance passée : `422 action_impossible`. **Réserve honnête** :
  l'envoi du rappel J-1 attend l'infrastructure de notifications (§11).
- Les outils sont des liens **externes**. Le client les ouvre en **onglet
  système** (Custom Tabs / SFSafariViewController), jamais en WebView maison :
  moins de surface d'attaque, session isolée, et le citoyen voit le vrai domaine
  dans la barre d'adresse avant de saisir quoi que ce soit.

### 10.6 Accueil, statistiques, unités, récompenses, parrainage

| Verbe | Chemin | Écran | Paramètres |
|---|---|---|---|
| GET | `/me/dashboard` | 06 Accueil | — |
| GET | `/me/stats` | 27 Statistiques | `period` |
| GET | `/me/wallet` | 25 Unités | — |
| POST | `/me/wallet/topup` | 25 Unités | `pack`, `numero` |
| GET | `/me/wallet/transactions` | 25 Unités | `limit`, `cursor` |
| GET | `/me/gamification` | 26 Récompenses | — |
| GET | `/me/referrals` | 28 Parrainage | — |
| POST | `/me/referrals/invite` | 28 Parrainage | — |

- **`GET /me/dashboard` est un agrégat**, et c'est le point : la première image
  doit tenir sous 400 ms, et six allers-retours sur un réseau mobile béninois
  feraient de l'écran d'accueil le plus lent de l'application — celui qu'on voit
  à chaque ouverture. Il porte compteurs, niveau, série, activité sur 30 jours,
  et le cours offert du mois (`null` s'il n'y en a pas — annoncer une offre qui
  n'existe pas serait une promesse en l'air).
- `GET /me/stats?period=` accepte **`7d`, `30d`, `90d`, `365d`** et rien
  d'autre. Une valeur libre laisserait demander cent mille jours d'historique.
  Les jours sans activité **restent dans la série à zéro** : un graphique qui
  saute les jours vides ment sur la régularité, qui est justement ce qu'il
  montre.
- `POST /me/wallet/topup` attend **`pack`** (un code) et **`numero`**. Le prix
  et la quantité sont lus en base ; l'opérateur est **déduit du numéro** par le
  serveur. Réponse `201` avec l'état de la demande. **Le solde ne bouge pas** :
  il ne bougera qu'au rappel signé de l'opérateur. Après un retour
  d'application (`AppLifecycleState.resumed`), **relire `GET /me/wallet`** — ne
  jamais créditer côté client.
- `GET /me/gamification` **recalcule** les badges avant de répondre. Les badges
  verrouillés sont renvoyés eux aussi, avec leur condition en clair : montrer la
  destination motive davantage que la masquer, et un cadenas sans explication
  n'est qu'une frustration gratuite.
- `POST /me/referrals/invite` rend le code et le lien à partager. **Il ne compte
  rien** : le seul compteur honnête du parrainage est le nombre de personnes qui
  ont *ouvert* une invitation, et il s'incrémente sur la page publique. Le QR se
  génère **nativement** côté client (`qr_flutter`) : il doit marcher hors ligne,
  en présentiel.

---

## 11. Réserves — ce que le simulateur appelle et que l'API ne sert PAS

Cinq appels déclarés par le simulateur n'ont **pas** d'endpoint. Les taire aurait
été pire qu'un retard annoncé.

| Appel du simulateur | État | Pourquoi | Ce qu'il faut pour lever la réserve |
|---|---|---|---|
| `GET /me/notifications` | **absent** | Il n'existe **aucun magasin de notifications** sur cette plateforme : pas de table `notifications`, aucune classe `Notification`, rien qui en émette. Un endpoint qui renverrait éternellement une liste vide serait un indicateur qu'on ne mesure pas — ce que la règle 2 du chantier interdit | la table `notifications`, des classes `Notification` sur les événements qui comptent (verdict prêt, réponse au forum, correction rendue), et `toFcm()` sur chacune |
| `POST /me/notifications/read-all` | **absent** | idem | idem |
| `POST /me/notifications/{id}/read` | **absent** | idem | idem |
| `POST /verifications/{uuid}/feedback` | **absent** | « Ce rapport vous a-t-il été utile ? » n'a **aucun stockage** et n'est transposé sur aucun écran web. L'implémenter exigeait de créer une table et un service, c'est-à-dire de *concevoir une fonctionnalité* plutôt que d'en *transposer* une | une table `verification_feedback` (compte, vérification, utile oui/non, unicité sur le couple) et un service dans `FactChecking` |
| `GET /me/certificates/{code}/pdf` | **absent** | Aucune bibliothèque de génération PDF n'est installée, et en ajouter une est une décision de dépendance qui appartient au porteur du projet | une bibliothèque PDF, un gabarit avec filigrane, et une **route signée à durée limitée** — le client ne compose jamais le PDF |

Quand ces endpoints existeront, ils seront des **ajouts** : compatibles v1, sans
rupture pour l'application déjà installée.

**Autre réserve, partielle** : `POST /live-classes/{uuid}/remind` enregistre
l'intention mais **n'envoie pas** le rappel J-1 — même cause que les
notifications. L'inscription est durable : le jour où le rappel partira, il aura
sa liste, et personne n'aura à redemander aux gens de se réinscrire.

---

## 12. Aide-mémoire pour le client Flutter

**Le minimum vital, en dix lignes.**

1. Un seul en-tête obligatoire : `Authorization: Bearer <token>`.
2. Une seule règle de lecture : `error` présent ⇒ échec.
3. Brancher sur `error.code`, afficher `error.message` tel quel.
4. **Ignorer les champs inconnus.** C'est ce qui rend les évolutions possibles.
5. Pagination : renvoyer `next_cursor` tel quel dans `?cursor=` — **et
   renvoyer AUSSI tous les filtres**. Le curseur ne les porte pas (§3.4).
   Ne jamais filtrer côté client sur une liste paginée : ça ne filtre que la
   page chargée, et la deuxième page dément la première.
6. `402` ⇒ proposer une recharge. `426` ⇒ proposer la mise à jour. `401` ⇒
   purger le jeton.
7. `429` ⇒ attendre `meta.retry_after` secondes. Un seul essai ensuite.
8. Écritures rejouables : une `Idempotency-Key` **par acte**, réutilisée à
   chaque reprise.
9. Ne jamais recalculer ce que le serveur calcule : avancement, verrou de leçon,
   score, solde, verdict.
10. Purger `flutter_secure_storage` **et** la base locale à la déconnexion. Sur
    un téléphone partagé, un cache de lecture oublié est une fuite de données.
