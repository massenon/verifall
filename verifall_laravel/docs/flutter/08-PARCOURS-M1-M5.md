# 8. Découpage en cinq parcours

Ce découpage existe pour que **cinq agents travaillent en même temps sans se
marcher dessus**. Il est donc écrit autour d'une contrainte : aucun fichier n'est
en écriture dans deux parcours à la fois. Tout ce qui suit découle de cette
règle.

Les cinq parcours reprennent ceux de `docs/UX_FLUTTER_LANCEMENT.md` et de
`docs/RESTE_A_FAIRE.md` : *« M1 authentification · M2 vérifier (caméra, suivi,
partage) · M3 Académie hors ligne · M4 Hub et forum · M5 profil, unités,
parrainage. »*

---

## 8.0 Le socle — à livrer AVANT les cinq, et à geler

Le socle n'appartient à aucun parcours. Il est écrit une fois, validé, **gelé**,
et les cinq agents le lisent sans le modifier. Le livrer d'abord coûte quelques
jours ; ne pas le livrer coûte cinq conflits de fusion par jour, tous les jours.

### Ce que le socle contient

| Zone | Contenu | État |
|---|---|---|
| `core/theme/` | Jetons, typographie, thème clair complet (section 2) | Gelé |
| `core/config/` | Configuration par environnement, trois saveurs | Gelé |
| `core/network/` | Client, intercepteurs, jetons, réessais, erreurs (section 4) | Gelé |
| `core/logging/` | Logger et redaction | Gelé |
| `core/router/app_router.dart` | `ShellRoute` à cinq onglets, gardes, liens profonds, **et les six imports de routes déjà en place** | Gelé |
| `data/datasources/local/database.dart` | Base Drift, **et les six imports de schémas déjà en place** | Gelé |
| `data/datasources/local/pending_ops.dart` | La file d'écriture, `Idempotency-Key` comprise (section 5) | Gelé |
| `data/models/envelope.dart` | L'enveloppe `data` / `error` / `meta`, la page par curseur, les 13 codes d'erreur | Gelé |
| `data/models/auth_*.dart` | La réponse commune d'émission de jeton, identique sur les trois points d'entrée | Gelé |
| `presentation/shared/` | Les composants `Vf*` de la section 2.7 | Gelé |

### Ce que la passe d'intégration y a ajouté

Le gel a fait son travail : les cinq parcours ont livré sans conflit. Il a aussi
produit une file d'attente — chaque fois qu'un parcours butait sur une limite du
socle, il la **signalait** au lieu de la corriger. La passe d'intégration l'a
traitée, et le socle porte depuis :

| Zone | Contenu | Pourquoi |
|---|---|---|
| `core/format/vf_formats.dart` | Dates, durées, tailles, âge d'un cache | Trois parcours en avaient écrit une version (arbitrage n° 130) |
| `core/session/solde_unites.dart` | La source du solde affiché par `VfPointsPill` | Quatre écrans en affichaient quatre valeurs (n° 135) |
| `core/session/deconnexion.dart` | Le point d'entrée unique de la déconnexion | Le bouton du tiroir en a besoin, et le socle ne peut pas importer un parcours (n° 137) |
| `core/state/riverpod_retry.dart` | La politique de réessai des providers | Deux parcours l'écrivaient, trois ne l'écrivaient pas (n° 132) |
| `core/config/vf_web_links.dart` | Les adresses du site public | M3 et M5 (n° 138) |
| `presentation/shared/vf_stale_banner.dart` | Le bandeau « Données du `<date>` » | Exigé des cinq (n° 129) |
| `presentation/shared/vf_app_drawer.dart` | Le tiroir assemblé, et son hôte dans la coque | Trois écrans posent le bouton (n° 137) |

**La règle ne change pas** : le socle reste en lecture seule pour un parcours, et
un besoin de modification reste une demande. Ce qui change, c'est qu'une file
d'attente existe maintenant comme forme reconnue — et qu'elle se traite entre deux
vagues de parcours, pas pendant.

### Les deux fichiers d'agrégation, et pourquoi ils sont gelés

`app_router.dart` et `database.dart` sont les deux seuls endroits où les cinq
parcours devraient normalement écrire. On l'évite ainsi :

- chaque parcours écrit **son propre fichier** de routes
  (`core/router/routes_auth.dart`, `routes_home.dart`, `routes_verify.dart`,
  `routes_lms.dart`, `routes_community.dart`, `routes_profile.dart`) ;
- l'agrégateur importe les six **dès le socle**, avec des listes vides pour
  celles qui n'existent pas encore ;
- même mécanique pour les tables Drift : un fichier de schéma par parcours,
  référencé d'avance.

Résultat : personne ne rouvre l'agrégateur, et les cinq branches se fusionnent
sans conflit.

### La session de développement

C'est ce qui rend le parallélisme réellement possible. M2 à M5 ont besoin d'un
compte connecté ; si chacun attend M1, il n'y a plus de parallélisme du tout.

Le socle fournit donc une **surcharge de provider d'authentification** activable
par la configuration de développement : elle injecte un jeton de recette et un
profil, sans passer par les écrans de M1. Elle est refusée en production par
construction — le même raisonnement que l'adaptateur simulé du back, qui *« refuse
de tourner en production »* (arbitrage n° 19).

### Le socle contient MAINTENANT les modèles transverses

C'est le changement qu'apporte le contrat d'API. Tant qu'il n'était pas écrit,
figer des modèles aurait été décider à sa place ; **il est écrit et figé pour la
v1**, donc tout ce qu'il fixe de transverse appartient au socle et se code une
seule fois :

- **l'enveloppe** — `data` et `error` mutuellement exclusifs, `meta` qui
  accompagne les deux, `{ "data": null }` qui n'est pas une erreur ;
- **les treize codes d'erreur**, en énumération fermée, avec la traduction vers
  les échecs du domaine. Aucun parcours ne réécrit ce mappage ;
- **`meta.page`** — `per_page`, `next_cursor`, `prev_cursor`, `has_more` — et
  l'adaptateur vers `infinite_scroll_pagination` en mode curseur ;
- **la réponse d'émission de jeton**, identique sur `login`, `otp/verify` et
  `password/reset` : un seul modèle, un seul chemin de code ;
- **le décodage tolérant** : les champs inconnus sont ignorés, et un champ absent
  se distingue d'un champ présent et nul ;
- **le couple valeur technique / libellé** des énumérations (`verdict` +
  `verdict_libelle`), avec un type générique qui interdit de fabriquer un libellé
  côté client ;
- **l'écran bloquant du `426 client_obsolete`**, global.

Ce qui reste à chaque parcours : ses **entités de domaine** et ses **modèles de
ressource** propres — une vérification, un cours, un sujet, une opportunité, un
portefeuille. Ils dépendent tous de l'enveloppe du socle, jamais l'inverse.

---

## 8.1 Règles de propriété, valables pour les cinq

1. **Un parcours écrit dans son dossier et nulle part ailleurs.**
2. **Le socle est en lecture seule.** Un besoin de modification du socle est une
   demande adressée au responsable du socle, pas une modification directe. C'est
   contraignant, et c'est le prix du parallélisme.
3. **Un composant partagé qui manque ne se crée pas dans un parcours.** Si deux
   parcours ont besoin du même widget, il appartient au socle. Un composant
   dupliqué dans deux dossiers diverge en une semaine.
4. **Aucun parcours ne dépend d'un autre au niveau du code.** Les dépendances
   fonctionnelles se résolvent par la navigation (`go_router` par nom de route)
   et par l'API, jamais par un import croisé entre deux dossiers `features/`.
5. **Chaque écart au simulateur se consigne** dans `docs/ARBITRAGES.md`, avec sa
   raison — et non dans un commentaire de code que personne ne relira.
6. **On rend compte de ce qui n'est pas fait.** Un parcours livré à 90 % qui se
   déclare fini bloque les quatre autres au moment de l'intégration.

---

## 8.2 M1 — Authentification

### Périmètre

Le parcours d'entrée complet : onboarding, connexion, inscription avec
consentements RGPD, code à usage unique par e-mail, réinitialisation du mot de
passe. Plus la mécanique de session : garde de route, purge à la déconnexion.

### Écrans

| # | Écran | Ancre |
|---|---|---|
| 01 | Écran d'accueil (splash) | `#/splash` |
| 02 | Connexion | `#/login` |
| 03 | Créer un compte | `#/signup` |
| 04 | Mot de passe oublié | `#/forgot` |
| 05 | Code de vérification | `#/otp` |
| 06 | Nouveau mot de passe | `#/reset` |

Documentation : `03-ECRANS-01-AUTHENTIFICATION.md`.

### Fichiers en écriture

```
lib/presentation/features/auth/**
lib/domain/entities/auth_*.dart
lib/domain/repositories/auth_repository.dart
lib/domain/usecases/auth/**
lib/data/**/auth/**
lib/core/router/routes_auth.dart
test/features/auth/**
assets/onboarding/**            (les quatre visuels du carrousel)
```

### Dépendances

- **Amont** : le socle. Rien d'autre.
- **Aval** : les quatre autres parcours consomment la session qu'il produit —
  mais via la surcharge de développement tant que M1 n'est pas fini, donc **sans
  attendre**.
- **Contrat d'API** : sept routes, toutes servies —
  `POST /auth/login` · `POST /auth/register` · `POST /auth/otp/request` ·
  `POST /auth/otp/resend` · `POST /auth/otp/verify` ·
  `POST /auth/password/forgot` · `POST /auth/password/reset`.
  Plus `POST /auth/logout`, appelé par M5 mais dont la logique de purge
  appartient à M1.

### Définition de fini

- [ ] Les six écrans transposés, capture de référence comparée au simulateur.
- [ ] Le carrousel ne s'affiche qu'une fois ; le drapeau vit en stockage sécurisé.
- [ ] Les visuels du carrousel sont des ressources locales — **aucun appel réseau
      au premier lancement**.
- [ ] Le jeton est en `flutter_secure_storage`. Un test le vérifie. Il n'est
      **jamais** journalisé.
- [ ] Les trois points d'entrée qui émettent un jeton (`login`, `otp/verify`,
      `password/reset`) passent par **un seul chemin de code**, la forme de
      réponse étant identique.
- [ ] **Aucun code de renouvellement de jeton.** Sur `401 non_authentifie` :
      purge et écran de connexion, sans boucle. Un test vérifie qu'aucune
      seconde requête n'est émise.
- [ ] `appareil` est envoyé avec le modèle réel du téléphone sur les trois
      appels qui l'acceptent.
- [ ] `POST /auth/register` **ne rend pas de jeton** : le parcours enchaîne
      obligatoirement sur `otp/verify`.
- [ ] La déconnexion purge stockage sécurisé, base Drift, file d'écriture,
      brouillons et médias copiés. Un test d'intégration le vérifie.
- [ ] Les trois consentements sont séparés, **jamais pré-cochés**, et les deux
      obligatoires bloquent l'envoi.
- [ ] La réponse de « mot de passe oublié » est indifférenciée — message **et**
      temps de réponse — **et l'écran n'affiche aucun compte à rebours** : le
      plafond de 3 demandes par 10 minutes est invisible au client par
      construction, un 429 visible dirait « cette adresse intéresse quelqu'un ».
- [ ] Un compte non activé reçoit à la connexion **la même réponse** qu'un mot de
      passe faux ; l'écran ne les distingue pas.
- [ ] `403 compte_inactif` renvoie vers la saisie du code, avec l'adresse connue.
- [ ] Les étiquettes de mot de passe annoncent **12 caractères**, et le client ne
      valide **que la longueur**.
- [ ] Le corps de `register` correspond au contrat (`prenom`, `nom`, `telephone`,
      `profession`, `email`, `mot_de_passe`, `cgu`, `confidentialite`,
      `newsletter?`, `code_parrainage?`) — **ou** la réserve A.11 est tranchée et
      consignée.
- [ ] L'écran de code sert ses usages par son paramètre, sans duplication.
- [ ] Les gardes de route redirigent dans les deux sens.
- [ ] `flutter analyze` à 0 ; tests unitaires et de widget verts ; parcours
      d'intégration M1 vert.
- [ ] Les réserves A.10 (pas de vérification en deux étapes à la connexion) et
      A.11 (champs d'inscription) sont tranchées **ou** consignées comme
      ouvertes dans `docs/ARBITRAGES.md`.

---

## 8.3 M2 — Vérifier

### Périmètre

Le cœur du produit, et l'accueil qui y mène : tableau de bord, notifications,
saisie d'une vérification (caméra, galerie, fichiers), suivi temps réel, rapport
et partage, historique, répertoire d'outils.

### Écrans

| # | Écran | Ancre |
|---|---|---|
| 07 | Tableau de bord | `#/home` |
| 08 | Notifications | `#/notifications` |
| 09 | Vérifier un contenu | `#/verify` |
| 10 | Analyse en cours | `#/verifying` |
| 11 | Rapport de vérification | `#/report` |
| 12 | Mes vérifications | `#/history` |
| 13 | Répertoire d'outils | `#/tools` |

Documentation : `03-ECRANS-02-ACCUEIL.md` et `03-ECRANS-03-VERIFICATION.md`.

### Fichiers en écriture

```
lib/presentation/features/home/**
lib/presentation/features/verify/**
lib/domain/entities/verification_*.dart, notification_*.dart, tool_*.dart
lib/domain/repositories/{verification,notification,tool}_repository.dart
lib/domain/usecases/{home,verify}/**
lib/data/**/{home,verify}/**
lib/core/router/routes_home.dart, routes_verify.dart
test/features/{home,verify}/**
```

### Dépendances

- **Amont** : le socle. Session par la surcharge de développement.
- **Le tableau de bord ne dépend pas de M3.** Sa carte « Continuer votre
  apprentissage » est alimentée par l'agrégat du tableau de bord, **pas** par les
  providers de l'Académie. C'est la condition pour que M2 et M3 avancent
  séparément — et c'est aussi ce que recommande le simulateur (un seul agrégat).
- **Contrat d'API** : servi, sauf les notifications. Endpoints —
  `GET /me/dashboard` · `GET /me/verifications` · `POST /uploads` ·
  `POST /verifications` · `GET /verifications/{uuid}` ·
  `POST /verifications/{uuid}/contest` · `GET /tools`.
- **Trois appels ne sont pas servis** : les trois routes de notification.
  L'écran 08 est écrit derrière un drapeau de fonctionnalité, la cloche est
  masquée, et le texte de l'écran 10 est à corriger (réserve C.2).
- **Un quatrième n'est pas servi** : `POST /verifications/{uuid}/feedback`. Le
  bloc « Ce rapport vous a-t-il été utile ? » est masqué.

### Définition de fini

- [ ] Les sept écrans transposés, captures comparées.
- [ ] **Le coût n'est jamais envoyé** : aucun barème d'unités dans le code
      client. Un test le vérifie par l'absence de constante. Le solde affiché
      vient de `meta.solde`, jamais d'une soustraction locale.
- [ ] **Deux appels pour un média** : `POST /uploads` puis `POST /verifications`
      avec `televersement`. Jamais un multipart unique.
- [ ] Format et poids validés **avant** l'envoi, avec les plafonds du contrat
      (10 / 100 / 25 Mo).
- [ ] `POST /verifications` porte une **`Idempotency-Key` générée par acte**,
      réutilisée à chaque rejeu. Un test simule une réponse perdue et vérifie
      qu'aucun second débit n'a lieu.
- [ ] `402 solde_insuffisant` **ouvre la recharge**, n'affiche pas une erreur.
- [ ] `429` respecte `meta.retry_after` : un seul essai ensuite, aucun débit.
- [ ] **La barre de progression ne ment pas** : aucune minuterie locale ; l'étape
      vient de `data.avancement` / `data.etape` (arbitrage n° 28).
- [ ] La bascule suivi → rapport se fait sur la **nullité** de `data.rapport`,
      jamais sur sa vacuité.
- [ ] Le sondage lit `X-RateLimit-Remaining` et **s'espace sous 10 %**.
- [ ] L'état d'une analyse en cours, comme le solde, **ne vient jamais du
      cache**.
- [ ] Les statuts « Reprise programmée », « non vérifiable » et « arbitrage
      humain » s'affichent correctement, avec la mention du remboursement quand
      il a lieu (arbitrages n° 20 et 35).
- [ ] **La barre de fiabilité prend la couleur du verdict** (arbitrage n° 32),
      verrouillé par un test.
- [ ] L'écran de suivi est abandonnable ; la notification y ramène, application
      fermée comprise.
- [ ] Trois canaux de notification cohabitent sans doublon (déduplication par
      identifiant).
- [ ] Soumission hors ligne : mise en file, média copié localement, identifiant
      client, rejeu ordonné, **aucun débit local d'unité**.
- [ ] Deux états vides distincts sur l'historique.
- [ ] Recherche débouncée à 350 ms ; **pagination par curseur** (`limit`,
      `cursor`), aucun usage de `page`, aucun affichage de total ; ≥ 60 fps sur
      la liste.
- [ ] Les libellés de verdict viennent de l'API (`verdict_libelle`) ; aucun
      libellé fabriqué côté client.
- [ ] Le filtre de catégorie des outils est **local** : `GET /tools` n'est ni
      paginé ni filtré.
- [ ] Partage : image 9:16 rendue côté client et lisible hors application ; PDF
      par route signée uniquement.
- [ ] Les outils s'ouvrent en **onglet système**, jamais en WebView.
- [ ] `flutter analyze` à 0 ; tests verts ; parcours d'intégration M2 vert, mode
      avion compris.

---

## 8.4 M3 — Académie hors ligne

### Périmètre

Le catalogue, la fiche d'un cours, la lecture d'une leçon, le quiz chronométré,
le certificat, les Live Classes. Et surtout : **le hors ligne**, qui est la
raison d'être de ce parcours.

### Écrans

| # | Écran | Ancre |
|---|---|---|
| 14 | Catalogue de cours | `#/academy` |
| 15 | Fiche de cours | `#/course` |
| 16 | Leçon | `#/lesson` |
| 17 | Quiz | `#/quiz` |
| 18 | Certificat | `#/certificate` |
| 19 | Live Classes | `#/liveclasses` |

Documentation : `03-ECRANS-04-ACADEMIE.md`.

### Fichiers en écriture

```
lib/presentation/features/lms/**
lib/domain/entities/{course,lesson,quiz,certificate,live_class}_*.dart
lib/domain/repositories/{course,quiz,certificate,live_class}_repository.dart
lib/domain/usecases/lms/**
lib/data/**/lms/**
lib/core/router/routes_lms.dart
test/features/lms/**
```

### Dépendances

- **Amont** : le socle, en particulier la file `pending_ops` et le cache Drift.
  C'est le parcours qui les sollicite le plus ; s'il trouve une lacune, elle
  remonte au responsable du socle et profite aux autres.
- **Aval** : aucune. La carte de reprise du tableau de bord passe par l'agrégat,
  pas par ce parcours.
- **Contrat d'API** : entièrement servi, sauf le PDF du certificat. Endpoints —
  `GET /courses` · `GET /courses/{slug}` · `POST /courses/{slug}/enroll` ·
  `GET /courses/{slug}/lessons/{uuid}` · `GET /courses/{slug}/quiz` ·
  `POST /me/lessons/{uuid}/complete` · `POST /me/quiz/{uuid}/attempt` ·
  `GET /me/enrollments` · `GET /me/courses/resume` ·
  `GET /me/courses/{slug}/progress` · `GET /me/certificates` ·
  `GET /me/certificates/{code}` · `GET /live-classes` ·
  `POST /live-classes/{uuid}/remind`.
- **Deux réserves à trancher avant de coder** : la forme du quiz, puisque le
  corrigé n'arrive qu'à la copie (C.1), et le fait qu'ouvrir une leçon démarre
  le chronomètre serveur, ce qui complique le téléchargement d'un cours entier
  (C.3).

### Définition de fini

- [ ] Les six écrans transposés, captures comparées.
- [ ] **Le déverrouillage séquentiel est lu du serveur** — `data.lecons[].ouverte`
      et `.acquise` —, jamais recalculé.
- [ ] Un élément verrouillé **affiche sa condition** — leçon, quiz, badge. Test
      dédié.
- [ ] La durée minimale vient de **`meta.secondes_requises`**, pas d'une
      constante. Le compteur **se met en pause en arrière-plan** ; test de cycle
      de vie.
- [ ] Le refus de validation (`422 action_impossible`) affiche **le message du
      serveur**, qui dit combien de secondes manquent.
- [ ] Le bouton de validation dit ce qui manque (« encore 20 s »), jamais un
      bouton grisé muet.
- [ ] La **correction du quiz reste serveur** : aucune bonne réponse dans le
      paquet installable. Un test le vérifie.
- [ ] Le corrigé s'affiche **à la copie**, depuis `data.corrige[]` — la forme
      retenue est celle tranchée par la réserve C.1, et elle est consignée.
- [ ] Les réponses partent **indexées par uuid de question**, avec une
      **`Idempotency-Key`**. Une copie vide est acceptée.
- [ ] Le chronomètre s'appuie sur **`meta.commence_le`**, jamais sur l'horloge du
      téléphone.
- [ ] Le résultat d'un quiz **ne vient jamais du cache**.
- [ ] Confettis à la réussite, **jamais à l'échec**, coupés par
      `disableAnimations`.
- [ ] Le HTML de leçon est rendu en **liste blanche de balises** ; les liens du
      corps s'ouvrent en onglet système.
- [ ] Le certificat affiche le nom et le titre **figés à l'émission**, pas les
      valeurs courantes du compte.
- [ ] **Le bouton PDF est retiré** : l'endpoint n'existe pas. Le partage d'image
      et le renvoi vers la page publique de vérification le remplacent, et
      l'écran ne promet rien qu'il ne tienne.
- [ ] Le certificat s'ouvre par son **`code` public**, pas par un uuid.
- [ ] Le catalogue affiche **« Nouveau »** en l'absence d'avis, jamais une note
      fabriquée (arbitrage n° 61).
- [ ] **Un cours se télécharge, se lit entièrement hors ligne et se supprime.**
      Taille annoncée avant téléchargement. Parcours d'intégration en mode avion.
- [ ] Trois états vides distincts sur les Live Classes.
- [ ] « Rejoindre » n'est actif que si **`data.lien_visio`** est présent.
- [ ] La confirmation du rappel **ne promet pas de notification** : elle n'est
      pas envoyée en v1 (réserve C.2).
- [ ] `flutter analyze` à 0 ; tests verts ; parcours d'intégration M3 vert.

---

## 8.5 M4 — Hub et forum

### Périmètre

L'espace communautaire : forum et sujet, Hub opportunités à trois rubriques,
détail d'une offre, article vérifié.

### Écrans

| # | Écran | Ancre |
|---|---|---|
| 20 | Forum citoyen | `#/forum` |
| 21 | Sujet du forum | `#/thread` |
| 22 | Hub opportunités | `#/hub` |
| 23 | Détail d'une offre | `#/offer` |
| 24 | Article vérifié | `#/article` |

Documentation : `03-ECRANS-05-COMMUNAUTE.md`.

### Fichiers en écriture

```
lib/presentation/features/community/**
lib/domain/entities/{thread,reply,opportunity,article}_*.dart
lib/domain/repositories/{forum,hub}_repository.dart
lib/domain/usecases/community/**
lib/data/**/community/**
lib/core/router/routes_community.dart
test/features/community/**
```

### Dépendances

- **Amont** : le socle.
- **Aval** : aucune.
- **Contrat d'API** : entièrement servi. Endpoints —
  `GET /forum/threads` · `POST /forum/threads` · `GET /forum/threads/{slug}` ·
  `POST /forum/threads/{slug}/vote` · `POST /forum/threads/{slug}/replies` ·
  `POST /forum/threads/{slug}/report` · `GET /hub/articles` ·
  `GET /hub/articles/{slug}` · `GET /hub/opportunities` ·
  `GET /hub/opportunities/{slug}` · `POST /hub/opportunities/{slug}/report`.
- **Les deux questions ouvertes sont tranchées** : tous les identifiants sont des
  **slugs**, et l'API fait voyager **valeur technique et libellé français
  ensemble** — le client affiche le libellé et n'en fabrique aucun.
- **Un écart à traiter** : l'API n'expose pas le **rôle** d'un auteur, que les
  cartes du simulateur affichent en badge (réserve C.4).

### Définition de fini

- [ ] Les cinq écrans transposés, captures comparées.
- [ ] **Sondage et verdict ne se mélangent jamais** (arbitrage n° 43). Un test
      reproduit le cas limite : la communauté croit l'information vraie à 100 %,
      la plateforme établit qu'elle est fausse, et les deux s'affichent sans
      s'écraser.
- [ ] Le bandeau officiel s'affiche **si et seulement si** `data.verdict_officiel`
      est présent. Il n'est **jamais déduit des votes**.
- [ ] Le vote est optimiste puis réconcilié sur `data.votes` et `data.mon_vote` ;
      un vote par citoyen, arbitré en base.
- [ ] Les motifs de signalement sont **exactement les six** de la liste fermée du
      contrat.
- [ ] Répondre dans un fil verrouillé affiche le message du `422
      action_impossible`, sans le reformuler.
- [ ] **Le badge de rôle des auteurs est retiré** des cartes, ou la réserve C.4
      est tranchée et consignée.
- [ ] Le corps des messages est rendu en liste blanche ; les liens signalés
      sortent en `nofollow noopener ugc`.
- [ ] **Un seul niveau d'imbrication** dans les réponses.
- [ ] Le signalement dit **avant l'envoi** qu'un humain tranche et qu'il n'y a
      pas de masquage automatique ; le motif vient d'une liste fermée.
- [ ] Les trois rubriques du Hub sont là ; **les portails officiels ne sont pas
      réintroduits** (arbitrage n° 54).
- [ ] **L'archivage à J+7 est affiché, jamais calculé** : `data.expiree` et
      `data.jours_restants` viennent du serveur, l'ordre de la liste aussi.
- [ ] `data.verifiee` pilote seule la pastille « employeur vérifié » ; elle ne se
      déduit d'aucun autre champ.
- [ ] `meta.avertissement` est affiché sur **toutes** les fiches d'opportunité.
- [ ] Les pastilles de type sont lues du contenu réel, jamais codées en dur.
- [ ] **Pagination par curseur** partout ; aucun usage de `page`.
- [ ] Une offre clôturée n'affiche **aucun bouton de candidature**.
- [ ] La candidature s'ouvre en **onglet système**, avec le domaine visible.
- [ ] Un lien profond vers un article dépublié ou une offre archivée affiche un
      repli **qui n'affirme rien** — le `404` du contrat couvre aussi « pas à
      vous ». Test d'intégration application fermée.
- [ ] `flutter analyze` à 0 ; tests verts ; parcours d'intégration M4 vert.

---

## 8.6 M5 — Profil, unités, parrainage

### Périmètre

L'espace compte : profil, portefeuille d'unités et recharge Mobile Money,
récompenses et badges, statistiques, parrainage, réglages avec ses obligations
RGPD.

### Écrans

| # | Écran | Ancre |
|---|---|---|
| 25 | Mon profil | `#/profile` |
| 26 | Mes unités | `#/units` |
| 27 | Récompenses & badges | `#/rewards` |
| 28 | Mes statistiques | `#/stats` |
| 29 | Mon parrainage | `#/referral` |
| 30 | Réglages | `#/settings` |

Documentation : `03-ECRANS-06-COMPTE.md`.

### Fichiers en écriture

```
lib/presentation/features/profile/**
lib/domain/entities/{profile,wallet,gamification,stats,referral,settings}_*.dart
lib/domain/repositories/{profile,wallet,gamification,referral,settings}_repository.dart
lib/domain/usecases/profile/**
lib/data/**/profile/**
lib/core/router/routes_profile.dart
test/features/profile/**
```

### Dépendances

- **Amont** : le socle. Une seule dépendance fonctionnelle vers M1 — la
  déconnexion, dont la logique de purge appartient à M1 ; M5 l'**appelle**, il ne
  la réécrit pas. Tant que M1 n'a pas livré, M5 code contre l'interface et
  branche à l'intégration.
- **Aval** : la pastille de points du socle lit le portefeuille exposé par M5.
  Elle est donc écrite dans le socle **contre une interface**, et M5 en fournit
  l'implémentation. Aucun des deux ne modifie le fichier de l'autre.
- **Contrat d'API** : servi. Endpoints — `GET /me` · `PATCH /me` ·
  `DELETE /me` · `GET /me/settings` · `PATCH /me/settings` · `POST /me/export` ·
  `POST /auth/logout` · `GET /me/wallet` · `POST /me/wallet/topup` ·
  `GET /me/wallet/transactions` · `GET /me/gamification` · `GET /me/stats` ·
  `GET /me/referrals` · `POST /me/referrals/invite`.
- **Trois manques à traiter** : aucun endpoint de **double authentification**,
  aucun **changement de mot de passe authentifié**, aucun **changement d'adresse
  e-mail** (réserve C.8). Ces trois réglages se font sur le site web.

### Définition de fini

- [ ] Les six écrans transposés, captures comparées.
- [ ] **Le client n'envoie que `pack` et `numero`** : aucun montant, aucune
      quantité, aucun opérateur — il est déduit du numéro par le serveur. Un test
      le vérifie.
- [ ] **Le client ne crédite jamais.** `topup` répond `201` et le solde ne bouge
      pas ; il est relu par `GET /me/wallet` après retour d'application
      (`AppLifecycleState.resumed`).
- [ ] **Le solde n'est jamais servi depuis le cache**, sur aucun écran.
- [ ] Un paiement sans retour affiche « en attente de confirmation », **jamais un
      échec**.
- [ ] La recharge est **refusée hors ligne**, avec sa raison.
- [ ] Le champ « Adresse e-mail » est **en lecture seule** : `PATCH /me` ne
      l'accepte pas, et aucun endpoint de changement n'existe. Le bloc reste à sa
      place, avec la mention de l'endroit où le faire.
- [ ] **L'interrupteur de 2FA est retiré**, faute d'endpoint — ou la réserve C.8
      est tranchée et consignée.
- [ ] `PATCH /me/settings` envoie une **valeur voulue**, jamais une bascule. Un
      test simule un rejeu et vérifie que le réglage ne s'inverse pas.
- [ ] Les sessions n'affichent **ni IP ni géolocalisation** (arbitrage n° 9).
- [ ] La suppression se fait **en deux temps sur `DELETE /me`** : mot de
      confirmation + mot de passe (`202`), puis code (`200`). Elle ferme le
      compte immédiatement et annonce les 30 jours de rétractation.
- [ ] L'écran dit que **l'annulation ne passe pas par l'application**, mais par
      le lien signé reçu par courriel.
- [ ] `403 compte_en_suppression` affiche l'écran d'état — pas un mur d'erreur —
      alimenté par `GET /me`, qui reste servi.
- [ ] L'export RGPD est asynchrone (`202`) et n'attend rien à l'écran ; un
      `409 conflit` affiche le message du serveur sans relancer.
- [ ] La lettre d'information est un **consentement horodaté**, pas une
      préférence (arbitrage n° 11).
- [ ] Les badges verrouillés restent visibles à 55 % **avec leur condition**.
- [ ] Chaque graphique porte sa **lecture en clair** ; aucun indicateur non
      mesuré n'est affiché (arbitrages n° 46 et 67).
- [ ] Le QR de parrainage est généré en natif à partir du lien rendu par l'API,
      mis en cache, et **fonctionne hors ligne**.
- [ ] Le libellé du compteur d'invitations dit ce qu'il compte réellement :
      **« Invitations ouvertes »**, jamais « envoyées ».
- [ ] Le statut « Invité » disparaît : une ligne de parrainage naît à
      l'inscription du filleul, il n'existe pas d'état antérieur.
- [ ] `GET /me/stats` n'envoie que `7d`, `30d`, `90d` ou `365d` ; les jours à
      zéro **restent dans la série**.
- [ ] `GET /me/gamification` alimente seule la grille de badges, verrouillés
      compris, avec leur condition telle qu'elle est rendue.
- [ ] La version affichée en pied de Réglages est lue du paquet installé.
- [ ] `flutter analyze` à 0 ; tests verts ; parcours d'intégration M5 vert.

---

## 8.7 Ordre conseillé, si l'on ne peut pas tout paralléliser

Les cinq parcours sont indépendants **par construction**. Si les effectifs
obligent à les enchaîner, cet ordre minimise le risque :

1. **Socle**, puis **M1** — il valide l'enveloppe, les codes d'erreur et le
   chemin d'émission de jeton sur le cas le plus simple, et il produit la vraie
   session.
2. **M2** — le cœur du produit, et le parcours qui exerce le plus le contrat :
   idempotence, téléversement en deux appels, sondage et auto-régulation. Ses
   découvertes profitent aux trois suivants.
3. **M3** — le hors ligne, qui exerce la file et le cache jusqu'au bout.
4. **M4** puis **M5**, dans n'importe quel ordre.

## 8.8 Ce qui reste à trancher avant de coder

**Le contrat d'API n'est plus un blocage** : il est écrit, figé pour la v1 et
implémenté. Les cinq parcours peuvent démarrer, socle livré.

Ce qui reste, ce sont des **arbitrages produit** — huit, tous consignés en
section 9, tous à trancher par le porteur et non par le développement. Ils sont
répartis ainsi :

| Réserve | Sujet | Parcours concerné |
|---|---|---|
| A.10 | Pas de vérification en deux étapes à la connexion | M1 |
| A.11 | Champs d'inscription et de profil | M1, M5 |
| A.12 | Suppression de compte en deux temps | M5 |
| C.1 | Forme du quiz : le corrigé n'arrive qu'à la copie | M3 |
| C.2 | Aucune notification : trois promesses d'écran à corriger | M2, M3 |
| C.3 | Ouvrir une leçon démarre le chronomètre serveur | M3 |
| C.4 | Le badge de rôle des auteurs n'existe pas | M4 |
| C.8 | Ni 2FA, ni changement de mot de passe, ni changement d'adresse | M5 |

**Aucune ne bloque le démarrage** : chacune peut être traitée d'abord dans le
sens le plus conservateur — masquer, dégrader, renvoyer vers le web — et révisée
ensuite. Ce qui bloquerait, en revanche, c'est de les trancher en silence : la
règle du chantier est que **tout écart au prototype se consigne** dans
`docs/ARBITRAGES.md`, avec sa raison.
