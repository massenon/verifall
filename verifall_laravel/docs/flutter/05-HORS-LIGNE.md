# 5. Hors ligne

## 5.1 Le contrat de synchronisation vit côté serveur

Le produit s'adresse à des gens qui reçoivent une rumeur sur WhatsApp dans un
taxi, qui suivent une leçon dans une cour d'école, qui montrent un certificat à
un recruteur dans un bureau sans wifi. Une application qui affiche « pas de
connexion » à ces trois moments-là n'est pas dégradée : elle est absente.

Le contrat de synchronisation n'est pas une décision du client. Il est écrit
dans `docs/kb/API_SPECIFICATION.md` §7, et le contrat dit pourquoi :

> **Il est ici et pas dans le code du client** : c'est le serveur qui arbitre.

Cette section est la transposition de ce §7. Ce qui suit ne dépasse pas ce que
le contrat autorise, et les durées de vie citées sont les siennes.

## 5.2 Ce qui se met en cache — serveur vers client

Le contrat fixe les durées de vie. Elles ne sont pas des suggestions : elles
équilibrent la fraîcheur d'un contenu et le coût d'un aller-retour sur un réseau
mobile.

| Donnée | Endpoint | Durée de vie |
|---|---|---|
| Catalogue de cours | `GET /courses` | 1 h |
| Fiche d'un cours + leçons | `GET /courses/{slug}` | 1 h |
| Contenu d'une leçon | `GET /courses/{slug}/lessons/{uuid}` | **jusqu'à la fin du cours** |
| Annuaire d'outils | `GET /tools` | 24 h |
| Brèves et opportunités | `GET /hub/*` | 15 min |
| Mes vérifications | `GET /me/verifications` | lecture seule hors ligne |

Ces lectures ne dépendent que de contenu publié. **Le cache est lu d'abord, le
réseau ensuite.**

La durée de vie du contenu d'une leçon — *jusqu'à la fin du cours* — est ce qui
rend le parcours M3 possible : une leçon téléchargée reste lisible tant que le
cours n'est pas terminé, et pas seulement une heure.

Le Hub à **15 minutes** mérite un mot : c'est le contenu le plus périssable de
l'application, parce qu'une opportunité porte une échéance et qu'une échéance
périmée induit en erreur (§5.4).

## 5.3 Ce qui ne doit JAMAIS venir du cache

Le contrat nomme trois données, et l'explication est la meilleure raison de ne
jamais y déroger :

| Donnée | Pourquoi |
|---|---|
| **Le solde d'unités** | On relit cet écran précisément parce qu'on attend un changement. Une valeur d'il y a cinq minutes fait croire à un paiement perdu |
| **L'état d'une analyse en cours** | Même raison : elle fait croire à une analyse bloquée |
| **Le résultat d'un quiz** | Même raison |

Ces trois écrans affichent donc un **chargement**, jamais une valeur périmée, et
disent franchement qu'ils ont besoin du réseau quand il manque. C'est l'un des
rares endroits où un indicateur circulaire est le bon choix.

## 5.4 La règle d'affichage du cache

Cycle de lecture, identique partout :

1. lire le cache et **afficher immédiatement** ;
2. lancer la requête réseau en arrière-plan ;
3. si elle réussit, écrire le cache et rafraîchir ;
4. si elle échoue, garder l'affichage et poser un **bandeau d'horodatage** :
   « Données du <date>, actualisation impossible ».

Ce bandeau n'est pas décoratif. Une échéance d'opportunité « J-2 » vieille de
trois jours, une statistique périmée, une liste de vérifications sans les
dernières : chacune devient un mensonge sans sa date. C'est la même exigence que
le §2 des règles du chantier — un indicateur périmé n'est plus un indicateur
mesuré.

**La purge.** À la déconnexion et à la suppression du compte, la base est effacée
entièrement. Le contrat le rappelle dans son aide-mémoire : *« purger
`flutter_secure_storage` et la base locale à la déconnexion. Sur un téléphone
partagé, un cache de lecture oublié est une fuite de données. »*

## 5.5 Ce qui est disponible sans réseau, écran par écran

| Domaine | Disponible hors ligne | Écrans |
|---|---|---|
| **Académie** | Catalogue, fiches, **leçons téléchargées**, certificats émis | 14, 15, 16, 18 |
| Tableau de bord | Dernier état connu, horodaté — **sauf le solde** | 07 |
| Vérifications | Historique, **rapports déjà ouverts** — **sauf une analyse en cours** | 11, 12 |
| Outils | Répertoire entier (24 h) | 13 |
| Hub | Brèves et opportunités du dernier chargement (15 min) | 22, 23, 24 |
| Forum | Fils et sujets déjà lus | 20, 21 |
| Compte | Profil, badges, statistiques, **code et QR de parrainage** — **sauf le solde** | 25, 27, 28, 29 |
| Réglages | Lecture seule + bascules de notification | 30 |

L'écran 08 (Notifications) n'apparaît pas dans ce tableau : il n'a **aucun
endpoint** côté API. Voir section 9.

## 5.6 `Idempotency-Key`

C'est le mécanisme qui rend la file d'écriture sûre, et le contrat en donne la
sémantique exacte (§7.3). La respecter à la lettre.

**Le problème qu'il résout.** Une réponse perdue est indiscernable d'une requête
perdue. Le téléphone qui ne reçoit rien ne sait pas si la vérification a été
lancée — et s'il rejoue, la personne est **débitée deux fois pour une seule
question**.

**La règle.** Le client génère un UUID **par acte, pas par tentative**, et le
place dans l'en-tête `Idempotency-Key`. Toutes les reprises du même acte portent
la même clé.

| Point | Comportement |
|---|---|
| Première tentative | Exécutée, réponse **mémorisée 24 h** |
| Reprise, même clé | La réponse mémorisée est renvoyée **à l'identique**, avec `Idempotent-Replay: true`. **Rien n'est réexécuté** |
| Échecs | **Non mémorisés.** Rejouer un échec figerait une erreur passagère — solde momentanément insuffisant, moteur d'analyse coupé — et le client réessaierait éternellement contre un cache |
| Portée | La clé est portée par le **compte** : deux personnes qui tireraient le même UUID ne se volent pas leur réponse |
| Longueur | 128 caractères maximum |
| Obligation | **Facultatif au contrat.** Sans lui, la requête s'exécute normalement |

Deux conséquences pratiques, faciles à manquer :

1. **Générer la clé au moment où l'utilisateur agit**, la stocker dans la ligne
   de la file, et la réutiliser à chaque rejeu. Une clé regénérée à l'envoi ne
   protège de rien.
2. **`Idempotent-Replay: true` n'est pas une erreur.** C'est la confirmation que
   l'acte a abouti la première fois. L'écran affiche le résultat normalement.

Le contrat n'exige `Idempotency-Key` que sur deux actes — les deux qui coûtent :
`POST /verifications` et `POST /me/quiz/{uuid}/attempt`. Les autres écritures
rejouables sont protégées **en base**, par une contrainte d'unicité, ou
**idempotentes par service**.

## 5.7 Ce qui se met en file — client vers serveur

La table est celle du contrat (§7.2). Ne rien y ajouter sans vérifier comment
l'acte survit à un rejeu.

| Action | Endpoint | Comment elle survit au rejeu |
|---|---|---|
| Terminer une leçon | `POST /me/lessons/{uuid}/complete` | Contrainte d'unicité en base |
| S'inscrire à un cours | `POST /courses/{slug}/enroll` | Idempotent par service |
| Demander un rappel de séance | `POST /live-classes/{uuid}/remind` | Contrainte d'unicité en base |
| Voter, signaler | `/forum/…/vote`, `/…/report` | Contrainte d'unicité en base |
| Soumettre une vérification | `POST /verifications` | **`Idempotency-Key`** |
| Rendre une copie de quiz | `POST /me/quiz/{uuid}/attempt` | **`Idempotency-Key`** |

Trois écritures que le simulateur suggérait de mettre en file **n'y vont pas** :

- **le marquage des notifications** — les endpoints n'existent pas (section 9) ;
- **`POST /me/wallet/topup`** — un paiement ne se met pas en file, jamais ;
- **`DELETE /me`** — une suppression rejouée trois jours plus tard, alors que la
  personne a changé d'avis, est irrattrapable.

**Publier un sujet ou une réponse** n'est pas dans la liste du contrat : ces
actes n'ont ni clé d'idempotence exigée ni contrainte d'unicité mentionnée. Les
mettre en file exposerait au doublon. Deux options, à trancher — les refuser
hors ligne, ou leur attribuer une `Idempotency-Key` puisque l'en-tête est accepté
partout. **Réserve C.6** de la section 9.

### Structure de la file

`uuid client`, `op`, `payload`, `created_at`, plus `attempts`, `last_error`,
`status` pour qu'elle soit exploitable. Pour une soumission de vérification, la
ligne garde en plus **la copie locale du fichier** : le téléversement expire au
bout de deux heures côté serveur, un rejeu au-delà doit re-téléverser.

### Le rejeu

- Déclenché par le retour du réseau, par le passage au premier plan, et par une
  action manuelle depuis le bandeau.
- **Dans l'ordre de création**, une opération à la fois. Une réponse publiée
  avant le sujet qui la porte n'a pas de sens.
- **Recul exponentiel**, et arrêt net sur `429` en respectant `retry_after`.
- Sur `402 solde_insuffisant` au rejeu : l'acte échoue, **et l'écran le dit** avec
  un renvoi vers la recharge. C'est le cas réel où le solde a changé entre la
  mise en file et l'envoi.
- Sur `404` : la ressource a disparu (offre archivée, sujet supprimé). Retirer
  l'opération avec un message, ne pas la rejouer indéfiniment.
- Après épuisement des tentatives, l'opération passe en échec **et le dit**. Une
  file qui abandonne en silence fait disparaître le travail de quelqu'un.

### Ce que l'utilisateur voit

Un bandeau discret mais permanent tant que la file n'est pas vide : « <n>
action(s) en attente d'envoi », qui ouvre le détail avec réessai et annulation
par opération.

Sur les écrans concernés, une marque locale : un vote en attente n'est pas
présenté comme comptabilisé, une leçon en attente n'est pas présentée comme
validée. **Ne jamais faire croire qu'une écriture a abouti.**

## 5.8 Qui gagne en cas de conflit

Le contrat pose la règle en une phrase (§7.4) :

> **Le serveur fait foi sur tout ce qu'il calcule.** Le client fait foi sur ce
> qu'il a observé localement, et sur rien d'autre.

| Donnée | Autorité | Pourquoi |
|---|---|---|
| Avancement d'un cours, statut d'inscription | **serveur** | Recalculés depuis les faits ; deux formules donneraient deux pourcentages |
| Déverrouillage d'une leçon (`ouverte`) | **serveur** | C'est une règle métier ; un client qui la recalcule ouvrirait un jour une leçon qui ne devait pas l'être |
| Score et réussite d'un quiz | **serveur** | La correction ne franchit jamais le réseau avant la copie |
| Solde d'unités, coût d'une action | **serveur** | Accepter un prix venu du téléphone viderait les quotas en une nuit |
| Verdict d'une vérification | **serveur** | Il n'y a pas d'autre source |
| **Durée de lecture d'une leçon** | **le plus petit des deux** | Voir ci-dessous |
| Préférences de notification | **client** (dernière écriture) | Ce sont des booléens sans conséquence |

### La durée de lecture, et pourquoi c'est un minimum

Le serveur note l'heure d'ouverture d'une leçon quand elle est demandée, et
retient le **minimum** entre ce que le client annonce (`secondes` dans
`POST /me/lessons/{uuid}/complete`) et ce qu'il a lui-même mesuré.

Le client peut annoncer **moins** — l'application met son compteur en pause quand
elle passe en arrière-plan, et c'est légitime. Il ne peut **jamais annoncer
plus** : sinon il suffirait d'envoyer « j'ai lu pendant deux heures ».

Conséquence pour l'écran 16 : le compteur local n'est pas la vérité, c'est une
**déclaration**. Le refus arrive du serveur, sous la forme d'un `422
action_impossible` **dont le message dit combien de secondes sont nécessaires**.
L'afficher tel quel, jamais un échec muet.

Le même mécanisme vaut pour le chronomètre d'un quiz : `meta.commence_le`,
renvoyé par `GET /courses/{slug}/quiz`, est l'heure qui fait foi — pas celle du
téléphone.

## 5.9 Les unités et le hors ligne

> **Le client ne débite ni ne crédite jamais d'unité.** Le solde est un fait
> serveur, et il ne se lit jamais depuis le cache (§5.3).

Conséquences :

- une vérification mise en file n'entame pas le solde affiché ;
- `POST /verifications` renvoie `meta.solde` **recalculé** : c'est cette valeur
  qui met le portefeuille à jour, pas une soustraction locale ;
- `POST /me/wallet/topup` répond `201` et **le solde ne bouge pas** — il ne
  bougera qu'au rappel signé de l'opérateur. Après un retour d'application, on
  relit `GET /me/wallet` ;
- si le solde est devenu insuffisant au moment du rejeu, le serveur répond `402`
  et le client propose la recharge.

## 5.10 Ce qui ne fonctionne pas hors ligne, et qu'il faut dire

Le contrat le formule pour la vérification, et la formule vaut partout :
soumettre hors ligne **met l'acte en file** mais ne produit aucun verdict.
*« L'écran doit le dire — “votre demande partira dès le retour du réseau” — et
non simuler une attente. »*

| Action | Raison du refus |
|---|---|
| Connexion, inscription, code, réinitialisation | Une identité ne se crée ni ne se vérifie hors ligne |
| **Recharge d'unités** | Un paiement ne se met pas en file |
| Modification du profil incluant une vérification | `PATCH /me` peut partir en file ; l'adresse e-mail n'est de toute façon **pas modifiable** par l'API |
| Export RGPD, **suppression de compte** | Irrattrapable si rejoué |
| Ouverture d'un outil, candidature, visioconférence | Ouvrent une page externe |
| PDF d'un certificat | Aucun endpoint (section 9) |

## 5.11 Le téléchargement des leçons

C'est le seul cache **explicitement demandé** par l'utilisateur.

- Un cours téléchargé emporte sa fiche, la liste de ses leçons, le **contenu** de
  chacune (`GET /courses/{slug}/lessons/{uuid}`, un appel par leçon — le contenu
  n'accompagne jamais la liste) et ses médias.
- **Attention** : ouvrir une leçon **démarre le chronomètre serveur**. Télécharger
  un cours entier en appelant les leçons d'avance démarre donc tous les
  chronomètres à la fois. C'est une conséquence du contrat qu'il faut arbitrer
  avant d'écrire M3 — voir la réserve en section 9.
- Le téléchargement est **visible, chiffré en taille avant de commencer, et
  annulable**. Sur un forfait facturé au mégaoctet, un téléchargement silencieux
  est une facture.
- Une leçon non téléchargée porte « Disponible avec une connexion », pas un
  cadenas nu.
- Les fichiers vivent dans le stockage **privé** de l'application.
- Un cours téléchargé doit pouvoir se supprimer.

## 5.12 Détection de l'état du réseau

- Écouter la connectivité **et** vérifier une joignabilité réelle. Un téléphone
  branché à un portail captif se déclare en ligne et ne joint rien.
- L'état est un provider global, consommé par le bandeau et par la file.
- **Ne jamais bloquer l'interface sur cet état** : on tente, et on traite l'échec.
  Une application qui refuse d'essayer parce qu'elle se croit hors ligne est
  inutilisable sur un réseau instable, c'est-à-dire dans le cas courant.
