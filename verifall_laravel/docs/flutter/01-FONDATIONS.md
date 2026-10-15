# 1. Fondations

## 1.1 Versions et raison de ce choix

| Élément | Version visée | Pourquoi |
|---|---|---|
| Flutter | 3.x, canal stable | `docs/UX_FLUTTER_LANCEMENT.md` fixe « Flutter 3.x (Dart 3, Material 3) ». La version mineure exacte se fige dans un fichier de version d'outillage au premier commit et ne bouge qu'à une décision explicite : une mise à jour de Flutter en cours de parcours change le rendu des `Text` et fait échouer toutes les captures de référence en même temps, ce qui donne cent régressions apparentes pour zéro régression réelle. |
| Dart | 3.x, mode nul-sûr strict | Les types non-nuls sont ce qui remplace, côté mobile, le `declare(strict_types=1)` du back. |
| Material | Material 3 | Retenu par la note d'architecture. Attention : Material 3 n'est pas le design du produit — il n'est que le socle de widgets par-dessus lequel le thème Vérif'All est posé (section 2). |

Le dépôt cible s'appelle `verifall_flutter_app`. Il **n'existe pas encore**
(`docs/RESTE_A_FAIRE.md`) : le premier parcours qui démarre le crée.

## 1.2 Arborescence

L'architecture est celle qu'impose la note d'origine — Clean Architecture, en
miroir du back Laravel. Le miroir n'est pas décoratif : il fait qu'une personne
qui connaît `app/Core/<Domaine>/Services` sait où chercher dans `lib/`.

```
lib/
  core/
    theme/          jetons, typographie, thème clair (section 2)
    errors/         types d'échec du domaine, jamais des exceptions nues
    network/        client Dio + intercepteurs (section 4)
    router/         go_router, gardes, liens profonds
    logging/        journalisation, redaction des champs sensibles
    config/         lecture de l'environnement (jamais de valeur en dur)
  domain/
    entities/       objets métier immuables, sans annotation JSON
    repositories/   interfaces seulement
    usecases/       un cas d'usage = une action du produit
  data/
    models/         objets de transport, sérialisation JSON
    datasources/
      remote/       appels API
      local/        Drift (cache de lecture, file d'écriture)
    repositories/   implémentations des interfaces du domaine
  presentation/
    features/
      auth/         { providers, pages, widgets }
      home/
      verify/
      lms/
      community/
      profile/
    shared/         composants du design system (Vf*)
```

Les six dossiers de `presentation/features/` portent **exactement** les noms des
six modules du rail du simulateur (`auth`, `home`, `verify`, `lms`, `community`,
`profile`). C'est ce qui permet, en section 8, de donner un dossier à chaque
parcours sans qu'aucun n'écrive dans celui d'un autre.

**Deux règles de dépendance, non négociables**, transposées du back :

1. `domain/` ne dépend de rien. Ni de Flutter, ni de Dio, ni de Drift. Un cas
   d'usage doit être testable sans démarrer un widget.
2. `presentation/` ne connaît que `domain/`. Un écran qui importe un modèle JSON
   est un écran qui cassera au premier changement de contrat. Le contrat est figé
   pour la v1, mais il autorise explicitement les **ajouts sans préavis** — champ,
   paramètre, code d'erreur, endpoint — et c'est cette frontière qui absorbe les
   ajouts sans les propager jusqu'aux widgets.

## 1.3 Gestion d'état — Riverpod

Le simulateur nomme un provider par écran, et ces noms sont des engagements :
`dashboardProvider`, `verifyFormProvider`, `quizControllerProvider`,
`hubFeedProvider`… Ils sont repris tels quels en section 3. Les reprendre évite
la dérive où deux développeurs nomment deux fois la même chose.

Types de provider et quand les employer :

- **`FutureProvider.autoDispose`** — une lecture ponctuelle qui se rejette en
  quittant l'écran. C'est le cas du tableau de bord : le simulateur l'annote
  explicitement `dashboardProvider (FutureProvider.autoDispose)`.
- **`StreamProvider`** — un flux poussé par le serveur. Les notifications et le
  suivi d'une analyse en cours, qui reçoivent des événements Reverb.
- **`StateNotifier` / `Notifier`** — un formulaire ou une machine à états dont
  l'utilisateur pilote les transitions : saisie d'une vérification, quiz,
  inscription.
- **`AsyncNotifier`** — un état serveur que l'écran modifie aussi : profil,
  portefeuille d'unités, réglages. Le simulateur les désigne tous les trois
  ainsi, et c'est cohérent : ce sont les trois écrans où l'on lit **et** écrit.
- **`PagedNotifier`** — les listes paginées (mes vérifications, forum). La
  pagination serveur est une règle du chantier, pas une optimisation.

**Un `autoDispose` par défaut, une exception motivée.** Un provider qui survit à
son écran garde en mémoire des données de compte ; sur un téléphone partagé —
le cas ordinaire au Bénin, rappelé par l'arbitrage n° 59 — c'est une fuite.

> **Attention : c'est une règle du chantier, PAS le comportement de Riverpod.**
> Avec l'API classique de `flutter_riverpod` 3 — celle qu'emploie ce dépôt —
> **un provider n'est pas auto-disposé** : il est gardé en vie jusqu'à la fin de
> l'application. Le `.autoDispose` s'écrit, à chaque déclaration, sans exception.
> (Ce n'est vrai que de l'API par génération de code, `@riverpod`, que le projet
> n'emploie pas.)
>
> La phrase ci-dessus a été lue comme une description, et pas comme une
> consigne : M3 a rencontré un vrai `UnmountedRefException` en croyant que ses
> providers se libéraient seuls. Un relevé fait à l'intégration compte
> **vingt-sept providers d'écran déclarés sans `.autoDispose`**, dans les cinq
> parcours — certains délibérément (le brouillon d'une vérification doit
> survivre à une mise en arrière-plan), la plupart non. Voir l'arbitrage n° 143.

**L'état d'authentification est global et unique.** `authControllerProvider`
est la seule source de vérité sur « suis-je connecté ». Il expose trois états et
pas davantage : inconnu (au démarrage, le temps de lire le stockage sécurisé),
connecté, déconnecté. Le troisième état est ce qui évite le clignotement de
l'écran de connexion au lancement.

## 1.4 Navigation — `go_router`

Les routes sont dictées par le simulateur ; elles sont listées écran par écran
en section 3. Vue d'ensemble :

```
/                              splash (une seule fois)
/connexion  /inscription  /mot-de-passe-oublie  /otp  /mot-de-passe/reinitialiser
/dashboard  /notifications
/verifier   /verification/:uuid   /rapport/:uuid   /mes-verifications   /outils
/catalogue  /cours/:slug  /cours/:slug/lecon/:id  /cours/:slug/quiz
            /certificat/:certId   /live-classes
/forum      /forum/:id     /hub     /hub/opportunites/:id    /hub-infos/:id
/profil     /unites        /recompenses   /statistiques   /parrainage   /reglages
```

Points structurants :

- **`ShellRoute` pour les cinq onglets.** La barre basse a cinq entrées —
  Accueil · Apprendre · [Vérifier] · Communauté · Profil — et elle est identique
  au prototype web. Les écrans qui la portent le disent en section 3 ; ceux qui
  ne la portent pas (lecture d'un rapport, d'une leçon, d'un certificat) ne
  doivent pas la faire réapparaître à mi-parcours.
- **Aucun identifiant auto-incrémenté dans une URL.** La règle du chantier vaut
  pour les routes mobiles : `:uuid`, `:slug`, `:certId`. Les trois routes du
  simulateur qui portent un entier (`/forum/:id`, `/hub/opportunites/:id`,
  `/hub-infos/:id`) tiennent à ce que le simulateur numérote ses données de
  démonstration ; le contrat d'API devra y placer des UUID ou des slugs, et la
  section 9 en fait une réserve explicite.
- **Gardes.** Une redirection unique, centralisée : non connecté → `/connexion` ;
  connecté qui rouvre un écran d'authentification → son tableau de bord. C'est le
  pendant mobile de `RedirectIfAuthenticated` côté Laravel (arbitrage n° 59).
- **Le splash n'apparaît qu'une fois.** Le simulateur le dit sans ambiguïté :
  drapeau `onboarding_seen` en stockage sécurisé ; ensuite l'application ouvre
  directement sur la connexion ou le tableau de bord.
- **Liens profonds.** Schéma `verifall://`, plus les liens universels du domaine
  quand ils seront configurés. Deux cas sont nommés par le simulateur :
  `verifall://cours/{slug}` et `verifall://hub/{id}`. Un lien profond doit se
  résoudre **application fermée**, et retomber sur un écran de repli explicite si
  le contenu a été dépublié — ce qui arrive pour une opportunité archivée à J+7
  ou une brève retirée.

## 1.5 Injection de dépendances

Riverpod porte l'injection ; aucun conteneur supplémentaire. Concrètement :

- un provider par dépendance externe (client HTTP, base Drift, stockage
  sécurisé, client temps réel) ;
- les implémentations de dépôt sont fournies par un provider qui renvoie
  l'**interface** du domaine, jamais la classe concrète ;
- en test, on remplace le provider, pas la classe. C'est ce qui rend un test
  d'écran possible sans réseau ni base.

**Aucun singleton statique.** Un objet global survit à la déconnexion et garde
en mémoire ce qui devait être purgé.

## 1.6 Journalisation

Un logger unique dans `core/logging/`, avec trois exigences dont aucune n'est
optionnelle :

1. **Rien de sensible ne sort.** Jeton, mot de passe, code OTP, contenu soumis à
   vérification, adresse e-mail : redaction à la source, pas au moment d'écrire.
   Le contenu soumis est le plus piégeux — c'est du texte utilisateur, souvent
   nominatif, et il transiterait volontiers dans un log de requête HTTP.
2. **Rien en production sur la console.** Les journaux de diffusion partent vers
   la télémétrie de plantage (section 7), pas vers la sortie standard.
3. **Un identifiant de corrélation par requête**, propagé en en-tête, pour qu'un
   incident se retrouve des deux côtés. Le nom exact de cet en-tête relève du
   contrat d'API — à fixer là, pas ici.

Ce que l'on journalise et qui sert vraiment : transitions d'authentification,
échecs réseau avec leur code, rejeu de la file hors ligne, et le cycle de vie de
l'application (`AppLifecycleState`) qui explique la moitié des bizarreries.

## 1.7 Configuration par environnement

Trois environnements — développement, recette, production — et **aucune valeur
en dur dans le code**, exactement comme le back interdit les secrets hors `.env`.

- Les valeurs arrivent par `--dart-define-from-file`, un fichier par
  environnement, **non versionné**.
- `core/config/` expose une classe de configuration typée, lue une seule fois au
  démarrage. Le reste du code lit cette classe, jamais l'environnement.
- Trois saveurs d'application (`flavor`) : identifiants de paquet distincts,
  icônes et noms distincts. Il faut pouvoir installer la recette **à côté** de la
  production sur le même téléphone ; sans cela, tester une correction oblige à
  désinstaller son propre compte.
- Aucune clé d'API tierce n'a de raison de vivre dans le client. Tout ce qui est
  facturé à l'appel — moteurs d'analyse, recherche — passe par le serveur. Une
  clé embarquée dans un paquet installable est une clé publique.

Ce que la configuration porte : l'adresse de base de l'API, celle du service
temps réel, le canal de télémétrie, le drapeau d'environnement. Ce qu'elle ne
porte pas : les coûts en unités, les prix des packs, les seuils de quiz — **ces
valeurs viennent du serveur**, et le simulateur y insiste écran par écran.

## 1.8 Dépendances nommées par le simulateur

Le simulateur cite des bibliothèques dans ses points de vigilance. Elles ne sont
pas des suggestions : ce sont les choix déjà faits, et les reprendre évite de
rouvrir des débats tranchés.

| Besoin | Bibliothèque citée | Où le simulateur la nomme |
|---|---|---|
| Jetons | `flutter_secure_storage` | Connexion (02), Profil (25) — **jamais** les préférences partagées |
| Navigation | `go_router` | Tous les écrans |
| État | Riverpod | Tous les écrans |
| Cache local | Drift | Tableau de bord (07), Mes vérifications (12), Catalogue (14) |
| Pagination | `infinite_scroll_pagination` | Mes vérifications (12) |
| Images distantes | `cached_network_image` | Catalogue (14) |
| Prise de média | `image_picker` | Vérifier (09) |
| Lecture vidéo | `chewie` | Leçon (16) |
| Rendu HTML | `flutter_html` | Leçon (16) |
| Graphiques | `fl_chart` | Statistiques (28) — et `CustomPaint` pour le donut |
| QR | `qr_flutter` | Parrainage (29) |
| Partage | `share_plus` | Rapport (11), Certificat (18), Article (24), Parrainage (29) |
| Ouverture externe | `url_launcher` | Outils (13), Offre (23), Live Classes (19) |
| Temps réel | `pusher_channels_flutter` (Reverb) | Notifications (08), Analyse en cours (10) |
| Push | FCM | Notifications (08) |

Deux dépendances sont **volontairement absentes** et il ne faut pas les ajouter :
une bibliothèque de graphiques circulaires (le donut est un `CustomPaint`, dit
l'écran 28) et une bibliothèque de confettis (le back a déjà son module maison,
et le mobile fait de même — voir le composant `VfConfetti` en section 2).
