# 3. Les 30 écrans — chapitre 2 : Accueil

Deux écrans, module `home`. Le tableau de bord est la page d'arrivée après
connexion : c'est celle qui décide de la première impression, et c'est celle qui
avait été laissée en gabarit vide six sprints durant côté web (arbitrage n° 63).
Elle mérite qu'on la transpose entièrement.

---

## 3.7 — Écran 07 · Tableau de bord

**Ancre** : `#/home` — **Route** : `/dashboard` — **Page** : `DashboardPage`
**État** : `dashboardProvider` (`FutureProvider.autoDispose`)
**Origine** : `web/compte-user/dashboard.html`

> Le README du simulateur cite l'ancre `index.html#/dashboard`. L'identifiant
> réel de l'écran est `home` : c'est `#/home` qui fonctionne. Réserve A.1 de la
> section 9.
>
> Attention aussi à la numérotation : **le contrat d'API appelle cet écran
> « 06 Accueil »**, parce qu'il ne compte pas le splash. Cette documentation suit
> le rail du simulateur. Réserve D.10.

### Ce qui est à l'écran, dans l'ordre

Barre supérieure : bouton du tiroir à gauche, titre **Vérif'All**, pastille de
points, cloche de notifications avec pastille rouge s'il y a des non-lues. Barre
basse, onglet **Accueil** actif.

> **La cloche n'est pas livrée en v1.** L'API ne sert aucun endpoint de
> notification, et une cloche qui ouvre un écran vide se lit comme un défaut :
> l'action est **masquée** tant que le magasin n'existe pas. Voir §3.8.6.

1. **Salutation** — « Bonjour <prénom> 👋 » en `h-lg`, puis *« Votre boussole de
   l'information est active. Série actuelle : 🔥 <n> jours. »* et, en dessous,
   une ligne d'état système : point vert de 8 + *« Système opérationnel ·
   synchronisé il y a 2 min »*.
2. **Deux actions** côte à côte — un bouton **rouge** `Vérifier` (icône `add`)
   qui bascule sur l'onglet de vérification, et un bouton fantôme portant
   l'éclair vert et le solde d'unités, qui mène à « Mes unités ».
3. **Grille de quatre KPI** (2 × 2) : Vérifications · Cours suivis · Points
   d'expérience (avec le suffixe `XP` en violet) · Unités disponibles.
4. **Carte « Priorités »** — sur-titre `PRIORITÉS`, titre « À traiter
   maintenant », puis une à deux lignes cliquables encadrées : « Un verdict vous
   attend » (icône rouge `fact_check`, sous-titre = le titre de la vérification)
   et, si un cours est en cours, « Leçon en attente » (icône bleue `school`,
   sous-titre = le titre de la prochaine leçon).
   *Dans le simulateur, la première ligne est alimentée par la notification la
   plus récente. Sans magasin de notifications, elle est alimentée par ce que
   `GET /me/dashboard` renvoie — ou elle disparaît (§3.8.6).*
5. **Carte « Offre du mois »** — bordure et fond vert clair, pastille cadeau,
   sur-titre `OFFRE DU MOIS — <mois>`, titre du cours offert, phrase *« Chaque
   mois, un cours gratuit pour toute la communauté. »*, bouton primaire petit
   **S'inscrire gratuitement**.
6. **Carte « Continuer votre apprentissage »** — deux états. S'il existe un cours
   commencé non terminé : titre du cours, « Prochaine leçon : … », barre de
   progression, pourcentage, bouton **Reprendre**. Sinon : « Commencez votre
   premier cours », la phrase du cours d'introduction, bouton **Découvrir le
   catalogue**.
7. **Carte « Mon activité sur 30 jours »** — un histogramme de 20 barres, une
   sur trois en bleu, avec une légende à deux entrées : Vérifications (vert),
   Leçons terminées (bleu).
8. **Vérifications récentes** — en-tête de section avec lien **Tout voir** vers
   l'historique, puis **trois** tuiles de vérification.
9. **Carte « Prochaine Live Class »** — fond forêt, pastille verte `PROCHAINE
   LIVE CLASS`, titre de la séance, intervenant, pastille de date, bouton
   fantôme **Voir les Live Classes**.

### Arbre de widgets

```
Scaffold
 ├ appBar: VfAppBar (notifications)
 ├ body: CustomScrollView
 │  ├ VfGreetingHeader
 │  ├ VfKpiGrid (4 tuiles)
 │  ├ VfPriorityCard
 │  ├ VfOfferCard
 │  ├ VfResumeCard
 │  ├ VfActivityChart
 │  └ VfVerificationTile ×3
 └ bottomNavigationBar: VfBottomNav
```

### État

`dashboardProvider` est un `FutureProvider.autoDispose` : une lecture, rejetée
en quittant l'onglet. Il agrège tout ce que l'écran affiche.

Le **solde d'unités** est partagé avec la barre supérieure : il vient de
`walletProvider`, pour qu'un débit mette la pastille à jour partout à la fois
sans recharger le tableau de bord. Et il ne se lit **jamais** depuis le cache
(contrat §7.1).

`notificationsProvider` n'a **pas de source** en v1 : le compteur de non-lues
n'existe pas, et l'action de la barre supérieure est masquée en conséquence.

### Appels réseau

**`GET /api/v1/me/dashboard`** — l'agrégat, et le contrat en fait un point
d'architecture : *« la première image doit tenir sous 400 ms, et six
allers-retours sur un réseau mobile béninois feraient de l'écran d'accueil le
plus lent de l'application — celui qu'on voit à chaque ouverture. »*

Il porte : compteurs, niveau, série, activité sur 30 jours, et **le cours offert
du mois — `null` s'il n'y en a pas**. Ce `null` n'est pas un état d'erreur :
c'est ce qui empêche d'annoncer une offre qui n'existe pas. La carte « Offre du
mois » disparaît alors entièrement, elle ne s'affiche pas vide.

**`GET /api/v1/me/verifications?limit=3`** — les trois vérifications récentes.
Pagination par curseur (`limit`, `cursor`) ; `page` n'existe pas.

**`GET /api/v1/me/courses/resume`** — la carte « Continuer votre apprentissage ».
**Rend `data: null` quand il n'y a rien à reprendre**, et le contrat insiste :
*« ce n'est pas une erreur, c'est un état vide. »* C'est exactement le second
état déjà décrit ci-dessus (« Commencez votre premier cours »).

### Chargement, vide, erreur

- **Chargement** : squelettes à la forme des blocs — jamais un indicateur
  circulaire au centre. On lit d'abord le **cache Drift**, on affiche, puis on
  rafraîchit depuis le réseau.
  **Une exception impérative** : le contrat interdit de servir **le solde
  d'unités** depuis le cache (§7.1). La pastille de points et la tuile « Unités
  disponibles » affichent donc un chargement, jamais une valeur périmée — *« on
  relit cet écran précisément parce qu'on attend un changement. »*
- **Vide** : chaque bloc a son propre état vide et **dit pourquoi**. Aucune
  vérification → « Votre laboratoire est encore vide » + action « Vérifier une
  information ». Aucun cours → la variante déjà prévue par le simulateur. Aucune
  priorité → la carte disparaît entièrement plutôt que d'afficher un cadre nu.
- **Erreur** : si le cache existe, on l'affiche avec un bandeau discret
  « Données du <horodatage>, actualisation impossible ». Si le cache est vide,
  une page d'erreur avec bouton « Réessayer ». **Jamais un tableau de bord
  vide silencieux** : c'est le défaut que la règle du chantier §7 vise.

### Hors ligne

Lecture complète depuis Drift. Les deux boutons d'action restent actifs : la
soumission d'une vérification est mise en file (écran 09), la navigation vers le
catalogue lit le cache. La ligne « synchronisé il y a X » devient la vérité utile
de l'écran — elle doit afficher l'âge réel du cache, pas une valeur figée.

### Accessibilité

- La grille de KPI se lit comme quatre couples valeur/libellé, pas comme huit
  textes.
- L'histogramme porte un résumé textuel (« activité sur 30 jours, maximum le
  <jour> ») : vingt barres non étiquetées ne se lisent pas.
- Les cartes cliquables sont des boutons entiers, pas des zones avec un chevron
  cliquable.

### Point de vigilance (repris du simulateur)

Un seul agrégat plutôt que six appels ; première image sous 400 ms ; cache Drift
lu d'abord, réseau ensuite.

**Deux règles du chantier s'appliquent ici et ne se voient pas** : les compteurs
affichés sont **recalculés** côté serveur, jamais incrémentés à l'aveugle
(arbitrage n° 46) ; et le bouton de reprise dit ce qui va se passer — « Reprendre »
sur un cours entamé, « Découvrir » sur un catalogue jamais ouvert.

---

## 3.8 — Écran 08 · Notifications

**Ancre** : `#/notifications` — **Route** : `/notifications`
**Page** : `NotificationsPage` — **État** : `notificationsProvider` (StreamProvider)
**Origine** : `NOTIFICATIONS_TOASTS.md`

> ## ⚠ Cet écran n'a AUCUN endpoint
>
> Les trois appels que le simulateur déclare — `GET /me/notifications`,
> `POST /me/notifications/read-all`, `POST /me/notifications/{id}/read` — **ne
> sont pas servis par l'API v1**. Le contrat (§11) et l'arbitrage n° 73 disent
> pourquoi, et la raison n'est pas un oubli :
>
> > Il n'existe **aucun magasin de notifications** sur cette plateforme : pas de
> > table `notifications`, aucune classe `Notification`, rien qui en émette. La
> > cloche du gabarit citoyen est **décorative**. Un endpoint qui renverrait
> > éternellement une liste vide serait un indicateur qu'on ne mesure pas, ce que
> > la règle 2 du chantier interdit.
>
> **Ce que l'application doit faire à la place est décrit en §3.8.6.** La suite
> de cette fiche décrit l'écran tel qu'il sera **quand** les endpoints
> existeront : elle reste la référence de transposition, elle n'est pas un
> périmètre de livraison.

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Notifications**. Barre basse conservée,
onglet Accueil actif — on n'a pas quitté la section.

Une ligne d'en-tête : « <n> non lue(s) » à gauche, bouton texte primaire **Tout
marquer lu** à droite. Puis la liste, dans une liste de menu :

- icône de catégorie à gauche — verte si non lue, ardoise si lue ;
- le texte de la notification, en gras si non lue ;
- l'horodatage relatif en dessous ;
- un point vert de 9 à droite si non lue ;
- la ligne entière est teintée en vert très clair si non lue.

Les catégories présentes dans le simulateur : `verif` (icône `fact_check`),
`lms` (icônes `school`, `military_tech`, `workspace_premium`). Chaque
notification porte une **destination** : un rapport, le catalogue, les
récompenses, un certificat.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (retour)
 └ ListView.separated
    └ VfNotificationTile
       ├ leading: Icon (catégorie)
       ├ title: Text
       └ trailing: UnreadDot
```

### Appels réseau — aucun n'existe

| Appel du simulateur | État | Ce qu'il faudra pour le lever |
|---|---|---|
| `GET /api/v1/me/notifications` | **absent** | Une table `notifications` |
| `POST /api/v1/me/notifications/read-all` | **absent** | idem |
| `POST /api/v1/me/notifications/{id}/read` | **absent** | idem |

Le contrat précise que le jour où ils existeront, ce seront des **ajouts** :
compatibles v1, sans rupture pour une application déjà installée. L'écran est
donc à écrire, mais pas à brancher.

### 3.8.6 Ce que l'application fait en attendant

Quatre décisions, et aucune n'est « afficher une liste vide » — un écran vide
laisse croire à une panne ou à un compte sans activité, ce qui est faux.

**1. La cloche est masquée, pas grisée.** Tant qu'aucun endpoint n'émet de
compteur, l'action `notifications` de la barre supérieure du tableau de bord
**ne s'affiche pas**. Une cloche sans pastille invite à cliquer ; une cloche qui
ouvre un écran vide se lit comme un défaut. La règle du chantier §2 est directe :
un indicateur qu'on ne mesure pas ne s'affiche pas.

**2. La route existe et l'écran est écrit**, derrière un drapeau de
fonctionnalité lu de la configuration. Le jour où les endpoints arrivent, on
lève le drapeau — on ne réécrit pas l'écran. C'est ce qui distingue « pas
branché » de « pas fait ».

**3. La carte « Priorités » du tableau de bord perd son entrée « Un verdict vous
attend ».** Elle est alimentée, dans le simulateur, par la notification la plus
récente. Sans magasin de notifications, elle serait fabriquée. Ce que
`GET /me/dashboard` renvoie réellement fait foi : si l'agrégat porte une
vérification récemment achevée, la carte l'affiche ; sinon elle disparaît.

**4. Le retour d'une analyse passe par le sondage, pas par une notification.**
C'est la conséquence la plus lourde, et elle change l'écran 10 : le simulateur
promet *« vous pouvez quitter cet écran, une notification vous préviendra »*.
**Cette promesse ne peut pas être tenue en v1.** Voir la réserve C.2 de la
section 9 : ou bien le texte de l'écran 10 est corrigé, ou bien l'écran est
maintenu au premier plan pendant l'analyse. C'est un arbitrage produit, pas une
décision de développement.

### Chargement, vide, erreur — quand les endpoints existeront

- **Chargement** : trois lignes squelettes.
- **Vide** : icône `notifications_off`, titre « Aucune notification », phrase
  *« Vous serez prévenu dès qu'un verdict est prêt. »* — l'état vide dit
  **pourquoi** c'est vide et ce qui le remplira.
- **Erreur** : la liste en cache reste affichée ; l'échec du marquage se dit par
  un toast rouge et la ligne revient à son état non lu. Un marquage qui échoue
  en silence fait réapparaître la notification au prochain lancement, sans
  explication.

Le marquage sera optimiste — la ligne se dégrise immédiatement — et réconcilié
par la réponse du serveur.

### Hors ligne

Sans endpoint, il n'y a rien à mettre en cache ni en file. La table du §5.7 ne
porte donc **aucune** opération de notification : les y inscrire d'avance
créerait une file qui échouerait à chaque rejeu.

### Accessibilité

Chaque ligne annonce son état (« non lue ») avant son texte, et non par la seule
couleur du point. « Tout marquer lu » indique combien d'éléments sont concernés.

### Point de vigilance du simulateur — et ce qu'il en reste

Le simulateur annonce **trois canaux convergents** : `database` pour la liste,
**Reverb** pour le temps réel et **FCM** quand l'application est fermée, chaque
notification Laravel implémentant `toFcm()`.

En v1, **un seul des trois existe** : Reverb, et uniquement sur le canal
`private-verification.{uuid}` de l'écran 10. Ni magasin, ni FCM. Les trois
mécanismes que le client devait prévoir — déduplication par identifiant, ouverture
sur la destination, filtrage par les préférences de l'écran 30 — sont donc à
**écrire mais pas à brancher**, pour la même raison que l'écran lui-même.

À noter pour plus tard : `PATCH /me/settings` accepte bien des canaux de
notification et **refuse un canal inconnu (422)**. Les préférences se règlent
donc déjà, même si rien ne les consomme encore.
