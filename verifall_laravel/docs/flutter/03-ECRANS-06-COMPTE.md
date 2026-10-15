# 3. Les 30 écrans — chapitre 6 : Compte

Six écrans, module `profile`. C'est le chapitre le plus exposé sur le plan
réglementaire — consentements, export, effacement — et le plus exposé
commercialement : la recharge Mobile Money y vit. Cinq arbitrages y sont
directement applicables : n° 6 (adresse e-mail), n° 8 (2FA), n° 9 (sessions),
n° 12 et 13 (suppression), n° 17 et 18 (paiement).

---

## 3.25 — Écran 25 · Mon profil

**Ancre** : `#/profile` — **Route** : `/profil` — **Page** : `ProfilePage`
**État** : `profileProvider` (AsyncNotifier)
**Origine** : `web/compte-user/profil.html`

### Ce qui est à l'écran

Barre supérieure : bouton du tiroir, titre **Mon profil**, pastille de points,
action `settings`. Barre basse, onglet **Profil** actif.

1. **En-tête** centré : avatar de 72 vert à initiales, nom en `h-md`, ligne
   « <rôle> · <ville> », puis deux pastilles — **Niveau <n> · <nom du niveau>**
   (vert clair) et **<XP> XP** (violet).
2. **Quatre KPI** : Vérifications · Badges · Jours de série · Unités.
3. **Liste de menu** vers six destinations : Mes unités (avec le solde à droite),
   Récompenses & certificats, Mes statistiques, Mon parrainage, Mes
   vérifications, Réglages.
4. **Carte « Informations personnelles »** : quatre champs — Nom affiché,
   Adresse e-mail, Ville, Bio (240 caractères) — et un bouton primaire
   **Enregistrer**.
5. Bouton fantôme **Se déconnecter**, en rouge.

### Les paliers de niveau

Le simulateur les calcule : 500 XP par niveau, et six noms —
`Novice · Éveillé · Vigilant · Sentinelle · Gardien · Boussole`. **Le calcul
appartient au serveur** : c'est un compteur affiché, donc recalculé depuis les
faits et jamais incrémenté à l'aveugle (arbitrage n° 46). Le client affiche le
niveau, son nom, l'XP courante dans le palier et le reste à parcourir.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (réglages)
 └ ListView
    ├ VfProfileHeader (avatar, niveau)
    ├ VfKpiGrid
    ├ VfMenuList
    ├ VfProfileForm
    └ VfLogoutButton
```

### Appels réseau

**`GET /api/v1/me`** — sérialisé par `MoiResource` : adresse, téléphone, ville,
solde. Cette ressource n'est employée **que** là où le sujet de la requête est
aussi son propriétaire (arbitrage n° 74) ; partout ailleurs, un compte est un
`AuteurResource` réduit à uuid, nom d'affichage et initiales.

**`PATCH /api/v1/me`** — accepte `prenom`, `nom`, `telephone`, `profession`,
`ville`, `bio`. **Les champs absents gardent leur valeur.**

**`POST /api/v1/auth/logout`** — révoque **le seul jeton présenté**. Se
déconnecter d'un appareil ne ferme pas les autres : *« ce serait une surprise
désagréable, et cela existe déjà sous un autre nom — la réinitialisation du mot
de passe. »*

### L'adresse e-mail — tranché : elle n'est pas modifiable ici

La réserve A.4 est **réglée par le contrat**, et dans le sens de l'arbitrage
n° 6 :

> **L'adresse e-mail n'est pas modifiable ici** : elle sert d'identifiant de
> connexion *et* de canal de récupération, et la changer sans vérifier la
> nouvelle boîte offrirait une prise de compte à qui emprunte un téléphone
> déverrouillé.

Le champ « Adresse e-mail » du formulaire reste donc **affiché mais en lecture
seule**, à sa place dans la page — le prototype fait foi sur la mise en page.
Une mention discrète dit pourquoi et où le faire.

**Aucun endpoint de changement d'adresse n'existe dans l'API v1.** L'opération se
fait sur le site web. Ne pas construire d'écran pour un parcours que le contrat
ne sert pas.

### Le formulaire et le contrat ne coïncident pas tout à fait

Le simulateur montre : **Nom affiché**, Adresse e-mail, Ville, Bio.
Le contrat accepte : `prenom`, `nom`, `telephone`, `profession`, `ville`, `bio`.

« Nom affiché » recouvre donc deux champs, et **profession** comme **téléphone**
n'ont pas de champ à l'écran alors qu'ils sont modifiables. C'est le pendant de
l'écart déjà relevé sur l'inscription (écran 03). Réserve A.11 de la section 9 —
c'est un changement de maquette, il se décide.

### La déconnexion

Point de vigilance, textuel : *« La déconnexion doit purger `flutter_secure_storage`
et la base Drift : sur un téléphone partagé, un cache de lecture oublié est une
fuite de données. »*

Ce n'est pas une précaution théorique. L'arbitrage n° 59 rappelle le contexte
réel — cybercafé, téléphone prêté, poste commun — et le nomme « le cas ordinaire
au Bénin ». Purger veut dire : jetons, cache de lecture, file d'écriture en
attente, brouillons, médias copiés dans le stockage de l'application.

Elle est aussi **appelée depuis le tiroir**, sur les deux emplacements — l'écran
et le menu. Un seul chemin de code pour les deux.

### Chargement, vide, erreur

- Chargement : en-tête et champs en squelette. **Le solde ne vient jamais du
  cache** (contrat §7.1).
- Enregistrement : le simulateur refuse un nom vide. Les autres validations
  arrivent en `422 validation`, `details` indexé par champ envoyé, et se rendent
  sous le champ concerné.
- Erreur réseau à l'enregistrement : les valeurs saisies **restent à l'écran**.
  Rien de plus décourageant qu'un formulaire vidé par un échec.
- **`403 compte_en_suppression`** : le compte est dans son délai de rétractation.
  `GET /me` reste servi — c'est l'une des deux seules exceptions du contrat — et
  porte `data.suppression_demandee_le`. L'écran affiche « votre compte sera
  effacé le … » **au lieu d'un mur d'erreur** : *« un utilisateur qui ne comprend
  pas pourquoi tout est refusé ne se rétracte pas, il désinstalle. »*
  L'annulation ne passe pas par l'API : elle se fait par le lien signé reçu par
  courriel, et l'écran doit le dire.

### Hors ligne

Lecture depuis le cache, **sauf le solde**. L'enregistrement peut partir en
file ; l'adresse e-mail n'est de toute façon pas modifiable par l'API.

### Accessibilité

Les initiales de l'avatar sont décoratives ; le nom complet est lu à côté. Chaque
ligne de menu annonce sa destination et, le cas échéant, sa valeur (« Mes
unités, 34 »).

### Point de vigilance (repris du simulateur, confirmé par le contrat)

Purge complète du stockage sécurisé **et** de la base locale à la déconnexion.
C'est le dernier point de l'aide-mémoire du contrat : *« sur un téléphone
partagé, un cache de lecture oublié est une fuite de données. »*

---

## 3.26 — Écran 26 · Mes unités

**Ancre** : `#/units` — **Route** : `/unites` — **Page** : `UnitsPage`
**État** : `walletProvider` (AsyncNotifier)
**Origine** : `web/compte-user/unites.html`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Mes unités**. Barre basse, onglet Profil.

1. **Carte de solde** — fond forêt, centrée : `SOLDE DISPONIBLE` en capitales
   blanc à 60 %, puis le solde en Instrument Sans **50/800** blanc, la mention
   « unités de vérification », et une pastille verte : *« 🎁 +10 unités offertes
   le 1ᵉʳ <mois suivant> »*.
2. Titre **Recharger**, puis **trois packs** en lignes cliquables : pastille
   verte à éclair, « <n> unités » en gras, « <prix> FCFA · <p> FCFA l'unité »,
   une pastille d'accroche à droite le cas échéant (« Populaire », « Meilleur
   prix »), chevron.
3. **Encart d'information** : *« Coût par vérification : texte et lien 1 unité,
   image 2 unités, vidéo et audio 5 unités. »*
4. Titre **Historique des transactions**, puis une liste : icône `add_circle`
   verte ou `remove_circle` grise, libellé, date, et le delta signé à droite —
   vert s'il est positif.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar
 └ ListView
    ├ VfBalanceCard (forest)
    ├ VfPackTile ×3
    ├ VfCostNotice
    └ VfTransactionList
```

### La feuille de recharge

Toucher un pack ouvre une feuille inférieure **Recharger <n> unités** : *« Montant :
<prix> FCFA. Choisissez votre opérateur Mobile Money. »*, puis trois lignes
d'opérateur — **MTN MoMo**, **Moov Money**, **Celtiis Cash** — chacune avec sa
pastille de couleur. En pied : *« Redirection vers l'application de l'opérateur,
puis retour automatique. »*

### Les règles du paiement, et aucune n'est facultative

Le contrat les fixe pour `POST /me/wallet/topup`, et elles décident de la
sécurité de tout le module économique :

1. **Le client envoie `pack` (un code) et `numero`.** Jamais un montant, jamais
   une quantité. Un client modifié qui enverrait « 99 999 unités » n'obtient rien
   de plus.
2. **Le prix et la quantité sont lus en base** côté serveur.
3. **L'opérateur est déduit du numéro par le serveur.** La feuille à trois
   opérateurs du simulateur devient donc, au mieux, un aide-mémoire visuel : ce
   n'est pas le choix de l'utilisateur qui détermine le canal, c'est son numéro.
   Réserve C.7 de la section 9.
4. **La réponse est `201` et le solde ne bouge pas.** Le contrat est explicite :
   *« il ne bougera qu'au rappel signé de l'opérateur. »*
5. **Le solde est relu par `GET /me/wallet` après chaque retour d'application**
   (`AppLifecycleState.resumed`). C'est le seul moyen de refléter un crédit
   arrivé pendant que l'utilisateur était chez l'opérateur — *« ne jamais
   créditer côté client. »*
6. **Le retour peut ne pas arriver.** L'utilisateur peut fermer l'application de
   l'opérateur, perdre le réseau, abandonner. L'écran affiche « en attente de
   confirmation » et **ne conclut pas à l'échec** : le rappel peut arriver plus
   tard.

Les prix affichés viennent du serveur. Les valeurs du simulateur reflètent la
décision de l'arbitrage n° 33 (celles du prototype l'emportent), mais cette
décision reste **commerciale et ouverte** — c'est la seule réserve encore ouverte
du projet. Un barème en dur dans l'application obligerait à publier une mise à
jour sur les magasins pour changer un prix.

### La dotation mensuelle

Elle **crédite, elle ne remet pas à zéro** (arbitrage n° 16). L'écran l'annonce
comme un ajout (« +10 unités offertes »), jamais comme une remise à niveau.

### Appels réseau

- **`GET /api/v1/me/wallet`** — le solde. **Jamais servi depuis le cache**
  (contrat §7.1) : c'est l'un des trois écrans qu'on relit précisément parce
  qu'on attend un changement, et *« y montrer une valeur d'il y a cinq minutes
  fait croire à un paiement perdu »*.
- **`POST /api/v1/me/wallet/topup`** — corps : `pack`, `numero`. Réponse `201`
  avec l'état de la demande.
- **`GET /api/v1/me/wallet/transactions`** — **paginé par curseur** (`limit`,
  `cursor`) ; `page` n'existe pas.

### Chargement, vide, erreur

- Chargement : solde et lignes en squelette, **jamais une valeur en cache**.
- Historique vide : « Aucune transaction pour l'instant » + rappel de la dotation
  mensuelle.
- Échec de l'initiation du paiement : `422 validation` sur le numéro, ou message
  serveur affiché tel quel. **Aucun débit**, et possibilité de réessayer.
- Une transaction peut porter la mention **« non facturée »** (arbitrage n° 25,
  réponse déjà connue) : l'afficher telle quelle. C'est l'explication d'une ligne
  à zéro, et son absence ferait passer une gratuité pour un bug.

### Hors ligne

Solde et historique en cache, avec horodatage. **Aucune recharge hors ligne** :
un paiement ne se met pas en file. Le refuser explicitement.

### Accessibilité

Le solde est annoncé en toutes lettres avec son unité. Chaque pack annonce
quantité, prix et prix unitaire. Chaque transaction annonce le signe du delta,
qui ne repose pas sur la seule couleur.

### Point de vigilance (confirmé par le contrat)

`pack` et `numero` seulement ; opérateur déduit du numéro ; **le solde ne bouge
qu'au rappel signé de l'opérateur** ; relire `GET /me/wallet` au retour
d'application ; **ne jamais créditer côté client**.

---

## 3.27 — Écran 27 · Récompenses & badges

**Ancre** : `#/rewards` — **Route** : `/recompenses` — **Page** : `RewardsPage`
**État** : `gamificationProvider`
**Origine** : `web/compte-user/recompenses.html`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Récompenses**. Barre basse, onglet Profil.

1. **Carte de niveau** — sur-titre `VOTRE PROGRESSION`, « Niveau <n> — <nom> » en
   `h-md`, pastille violette d'XP, barre de progression du palier, puis « <cur> /
   <max> XP · encore <reste> XP pour le niveau <n+1> ».
2. **Carte de série** — sur-titre `SÉRIE`, « 🔥 <n> jours d'affilée », puis un
   **suivi jour par jour** : sept pastilles rondes de 28 étiquetées
   `L M M J V S D`, vertes avec une coche pour les jours tenus, et la pastille
   du jour cerclée d'un double halo. En pied : *« Vérifiez ou apprenez
   aujourd'hui pour ne pas casser la série. »*
3. **Carte « Prochaine étape »** — bordure verte : « 🎯 Badge <nom> », « <n> /
   <objectif> vérifications terminées », barre de progression.
4. **Grille de badges** — trois colonnes. Chaque tuile : rond de 42 (vert clair
   si obtenu, canevas sinon), l'icône du badge ou un **cadenas**, le nom, et la
   **condition d'obtention** en petit.
5. **Mes certificats** — une ligne par certificat : avatar forêt à
   `workspace_premium`, titre du cours, « <identifiant> · <date> », chevron.

### Arbre de widgets

```
Scaffold
 └ ListView
    ├ VfLevelCard (barre d'XP)
    ├ VfStreakTracker (7 jours)
    ├ VfNextBadgeCard
    ├ GridView
    │   └ VfBadgeTile (verrouillé | obtenu)
    └ VfCertificateTile ×N
```

### Les badges verrouillés restent visibles

C'est le point de vigilance, et il est explicite : **55 % d'opacité**, pas de
masquage. *« Montrer la destination motive davantage que la masquer. »*

Cela rejoint la règle du chantier §7 : un élément verrouillé **affiche sa
condition**. C'est pourquoi chaque tuile porte sa phrase d'obtention (« 10
sources vérifiées », « Série de 7 jours »…) — un cadenas sans explication est une
frustration gratuite.

### Le déblocage

Il joue `VfBadgeUnlockDialog` et des confettis, **une seule fois** —
l'anti-doublon est côté serveur. Le client ne décide pas qu'un badge est
nouveau : il reçoit l'information.

Et la règle de fond (arbitrage n° 46) : **les conditions de badge sont
recalculées depuis les faits**, jamais tenues par un compteur incrémenté. Un
compteur dérive au premier événement rejoué, et un badge qui apparaît sans raison
décrédibilise tous les autres.

### Le record de série

Le back a ajouté `streak_best` (arbitrage n° 67) parce que le prototype affichait
un « record personnel » que rien ne soutenait. Le simulateur mobile **ne l'affiche
pas**, et le contrat ne le nomme pas non plus dans la description de
`GET /me/gamification`.

La règle est donc simple, et c'est celle des champs inconnus : **si l'agrégat le
renvoie, la donnée est réelle et peut s'afficher ; sinon, on ne l'invente pas.**
Ne pas ajouter le bloc d'initiative — à trancher, réserve D.3.

### Appels réseau

- **`GET /api/v1/me/gamification`** — **non paginé** (liste fermée). Le contrat
  précise qu'il **recalcule les badges avant de répondre**, et qu'il renvoie
  aussi **les badges verrouillés, avec leur condition en clair** : *« montrer la
  destination motive davantage que la masquer, et un cadenas sans explication
  n'est qu'une frustration gratuite. »* La grille de l'écran est donc alimentée
  entièrement par cet appel — le client ne tient aucune liste de badges.
- **`GET /api/v1/me/certificates`** — **non paginé** également.

### Chargement, vide, erreur

- Chargement : squelettes.
- Aucun certificat : icône `workspace_premium`, *« Terminez un cours pour obtenir
  votre premier certificat. »* — un état vide qui dit quoi faire.
- Aucun badge obtenu : la grille reste affichée, entièrement en état verrouillé,
  chaque tuile portant sa condition telle que l'API la rend. C'est précisément
  le cas où montrer la destination sert.

### Hors ligne

Tout est en cache. Les confettis d'un déblocage arrivé hors ligne ne se jouent
pas rétroactivement — le badge est simplement là au retour.

### Accessibilité

Chaque tuile de badge annonce son état et sa condition (« Éclaireur, verrouillé,
série de 7 jours »). Le suivi de série est lu comme sept jours nommés avec leur
état, pas comme sept points.

### Point de vigilance (repris du simulateur)

Badges verrouillés visibles à 55 % ; déblocage joué une seule fois, anti-doublon
serveur.

---

## 3.28 — Écran 28 · Mes statistiques

**Ancre** : `#/stats` — **Route** : `/statistiques` — **Page** : `StatsPage`
**État** : `statsProvider(period)`
**Origine** : `web/compte-user/statistiques.html`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Statistiques**. Barre basse, onglet Profil.

1. **Quatre KPI** : Vérifications · Intox démasquées (rouge) · Leçons terminées ·
   Jours de série.
2. **Carte « Activité sur 30 jours »** — un histogramme de 20 barres, une sur
   quatre en bleu.
3. **Carte « Répartition de mes verdicts »** — un **donut** de 132 avec, au
   centre, le nombre total et le mot « analyses » ; à droite, une légende à trois
   entrées (VRAI vert, TROMPEUR orange, FAUX rouge) avec leur compte.
4. **Carte « Types de contenus vérifiés »** — une barre fine par type, avec son
   compte.
5. **Carte d'enseignement** — fond vert clair, sur-titre `CE QUE DISENT VOS
   DONNÉES`, puis une phrase composée : *« <n> % des contenus que vous avez
   soumis se sont révélés faux. Votre vigilance protège directement votre
   entourage — continuez à vérifier avant de partager. »*

### Arbre de widgets

```
Scaffold
 └ ListView
    ├ VfKpiGrid
    ├ VfActivityChart (fl_chart BarChart)
    ├ VfVerdictDonut (CustomPaint)
    ├ VfTypeBars
    └ VfInsightCard (données → lecture → action)
```

### La règle qui fait la valeur de cet écran

Point de vigilance, mot pour mot : **chaque graphique porte une lecture en
clair**. *« Un chiffre sans interprétation ne change aucun comportement. »*

C'est ce que porte la carte d'enseignement, et c'est le motif
`DATA → INTERPRÉTATION → ACTION` déjà formalisé côté web (`vf-insight` dans
`citizen.css`). Ne pas la supprimer pour gagner de la place : c'est le seul bloc
de l'écran qui agit sur le comportement.

Le donut est un `CustomPaint`, **pas une dépendance de plus**. L'histogramme, lui,
passe par `fl_chart`.

### Ce que le back a retiré, et qu'il ne faut pas réintroduire

L'arbitrage n° 67 est sévère et il s'applique ici : *« ↗ +3 vs mois dernier »* a
été **retiré** faute de source. L'évolution, quand elle existe, se calcule sur les
30 jours précédant les 30 derniers — pas sur le mois calendaire, sans quoi on
fabrique une chute tous les débuts de mois.

Autrement dit : **aucun indicateur affiché qui ne soit réellement mesuré.**

### Appels réseau

**`GET /api/v1/me/stats?period=`** — le contrat accepte **`7d`, `30d`, `90d`,
`365d` et rien d'autre** : *« une valeur libre laisserait demander cent mille
jours d'historique. »*

Une règle de forme, et elle décide du graphique : **les jours sans activité
restent dans la série à zéro.** Le contrat en donne la raison — *« un graphique
qui saute les jours vides ment sur la régularité, qui est justement ce qu'il
montre. »* L'histogramme dessine donc toutes les barres, y compris les nulles ;
il ne compacte pas.

Le paramètre existe dans la signature du provider (`statsProvider(period)`) mais
**le simulateur n'expose aucun sélecteur à l'écran**. Ne pas en ajouter un : ce
serait inventer un contrôle. La période reste fixée à `30d`, qui est celle des
titres de l'écran. Réserve D.4.

### Chargement, vide, erreur

- Chargement : squelettes de graphiques.
- **Compte neuf, aucune donnée** : c'est le cas important. Attention à ne pas le
  confondre avec la série à zéro décrite plus haut — celle-ci est **normale** et
  se dessine. Le cas « compte neuf », lui, se reconnaît à l'absence totale de
  vérifications : l'écran dit alors « Vos statistiques apparaîtront après votre
  première vérification » et propose l'action. Un donut à zéro et un histogramme
  plat sont indiscernables d'une panne.
- Erreur : cache si disponible.

### Hors ligne

Statistiques en cache, avec la date de calcul affichée. Une statistique périmée
présentée comme actuelle est un indicateur faux.

### Accessibilité

Chaque graphique a un résumé textuel équivalent — c'est ce qui remplace le
dessin, et la carte d'enseignement en fournit déjà une bonne part. Le donut porte
un libellé « Répartition des verdicts » et détaille ses trois segments.

### Point de vigilance (repris du simulateur)

Lecture en clair sous chaque graphique ; donut en `CustomPaint`.

---

## 3.29 — Écran 29 · Mon parrainage

**Ancre** : `#/referral` — **Route** : `/parrainage` — **Page** : `ReferralPage`
**État** : `referralProvider`
**Origine** : `web/compte-user/parrainage.html`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Parrainage**. Barre basse, onglet Profil.

1. **Quatre KPI** : Invitations envoyées · Filleuls actifs (vert) · Unités
   gagnées · Unités par filleul.
2. **Carte du code** — centrée : titre « Votre code de parrainage », le code en
   **monospace 26**, interlettrage `.1em`, vert foncé ; puis un **QR code** de
   128 sur fond blanc encadré ; puis un bouton primaire **Copier le lien** et un
   bouton vert WhatsApp **Inviter sur WhatsApp**.
3. **Carte « Comment ça marche »** — une chronologie légère à trois étapes :
   - *Vous partagez votre code* — « Par WhatsApp, SMS ou QR en présentiel. »
   - *Votre filleul crée son compte* — « Il reçoit 10 unités de bienvenue. »
   - *Il fait sa première vérification* — « Vous recevez 5 unités, créditées
     automatiquement. »
4. **Mes invitations** — une ligne par filleul : avatar à initiale, nom, date, et
   une pastille de statut (**Actif** en vert, **Invité** en bleu).

### Arbre de widgets

```
Scaffold
 └ ListView
    ├ VfKpiGrid
    ├ VfReferralCard
    │   ├ VfCodeDisplay
    │   └ QrImageView (qr_flutter)
    ├ VfTimeline (3 étapes)
    └ VfInviteeTile ×N
```

### Le QR

Le contrat reprend le point de vigilance mot pour mot : **généré nativement par
`qr_flutter`**, jamais une image serveur, *« il doit marcher hors ligne, en
présentiel »*. Montrer un code à quelqu'un dans une cour d'école ne doit pas
dépendre du réseau.

Le simulateur dessine un faux QR déterministe ; l'application en génère un vrai à
partir du **lien rendu par `POST /me/referrals/invite`**, mis en cache pour
survivre à une coupure.

### Ce que le back a corrigé, et qui change les KPI — tranché

L'arbitrage n° 46 est direct, et le contrat le confirme :

> Le prototype affiche « Invitations : 12 ». Un partage WhatsApp ne se constate
> pas depuis un serveur : compter les ENVOIS serait inventer un chiffre.

L'indicateur compte les **ouvertures du lien**, seule mesure honnête, et **le
libellé le dit** : « Invitations ouvertes ». Le libellé du simulateur
(« Invitations envoyées ») est donc à corriger — la réserve A.5 est levée sur le
fond, il reste le mot à changer.

Deux autres conséquences :

- **il n'existe pas d'état « invitation envoyée »** : une ligne de parrainage naît
  à l'**inscription** du filleul. Avant, il n'y a personne à nommer, et fabriquer
  des lignes remplirait la page de fantômes ;
- **la récompense arrive à la première vérification du filleul**, pas à son
  inscription — payer l'inscription seule, c'est payer les faux comptes. Le
  troisième point de la chronologie l'écrit déjà correctement.

Enfin, le code public suit les règles du chantier §5 : alphabet **sans O/0, I/1,
S/5**, tiré par générateur cryptographique. Il est fait pour être dicté au
téléphone.

### Appels réseau

- **`GET /api/v1/me/referrals`** — la liste des filleuls. **L'adresse e-mail d'un
  filleul n'est jamais exposée** (arbitrage n° 74) : *« il n'a pas consenti à ce
  qu'elle soit rendue à qui l'a invité. »*
- **`POST /api/v1/me/referrals/invite`** — rend le **code** et le **lien** à
  partager.

Le contrat ajoute la phrase qui règle la réserve A.5 : *« il ne compte rien. Le
seul compteur honnête du parrainage est le nombre de personnes qui ont **ouvert**
une invitation, et il s'incrémente sur la page publique. »*

Le libellé du premier KPI doit donc dire ce qu'il compte : **« Invitations
ouvertes »**, pas « envoyées ». Et « Unités gagnées » vient de l'API, pas d'une
multiplication locale du nombre de filleuls actifs.

### Chargement, vide, erreur

- Chargement : squelettes.
- Aucun filleul : « Vous n'avez pas encore de filleul » + rappel du gain. Ne
  jamais afficher trois lignes d'exemple — l'arbitrage n° 67 les a précisément
  retirées côté web. Et rappel de l'arbitrage n° 46 : **il n'existe pas d'état
  « invitation envoyée »**, une ligne naît à l'inscription du filleul. Le statut
  « Invité » du simulateur n'a donc pas de source.
- Copie du lien : le simulateur confirme par un toast qui **rappelle le lien**.

### Hors ligne

Code et QR fonctionnent hors ligne, c'est leur raison d'être. La liste des
filleuls vient du cache.

### Accessibilité

Le code est épelé caractère par caractère par le lecteur d'écran. Le QR porte le
libellé « QR code de parrainage » et le code en texte reste la voie principale —
un QR n'est pas accessible.

### Point de vigilance (repris du simulateur)

QR natif ; partage via `share_plus` avec le lien de parrainage universel.

---

## 3.30 — Écran 30 · Réglages

**Ancre** : `#/settings` — **Route** : `/reglages` — **Page** : `SettingsPage`
**État** : `settingsProvider` (AsyncNotifier)
**Origine** : `web/compte-user/reglages.html` + `SECURITE_RGPD.md`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Réglages**. Barre basse, onglet Profil.
Quatre sections, chacune précédée de son titre.

**🔐 Sécurité**
- interrupteur **Double authentification** — sous-titre du simulateur : *« Code
  par SMS à chaque connexion sur un nouvel appareil »* (voir la réserve : le
  canal retenu est l'e-mail) ;
- ligne **Changer mon mot de passe** → feuille à trois champs (actuel, nouveau,
  confirmation) ;
- ligne **Appareils connectés**, avec le nombre à droite → feuille listant les
  sessions, avec un bouton rouge **Déconnecter les autres appareils**.

**🔔 Notifications** — quatre interrupteurs :
- **Verdicts de vérification** — « Quand une analyse est terminée »
- **Académie** — « Rappels de cours, quiz et Live Classes »
- **Forum** — « Réponses à mes sujets et verdicts officiels »
- **Résumé par e-mail** — « Une synthèse hebdomadaire, le lundi »

**🛡️ Données personnelles**
- ligne **Exporter mes données** — « Archive JSON de tout votre compte (RGPD
  art. 20) »
- ligne **Politique de confidentialité**

**⚠ Zone de danger** (titre en rouge)
- ligne rouge **Supprimer définitivement mon compte** → feuille de confirmation
  demandant de **taper son nom exact**, avec deux boutons Annuler / Supprimer.

En pied, centré et discret : « Vérif'All Mobile · version <x.y.z> (build <n>) ».

### Arbre de widgets

```
Scaffold
 └ ListView
    ├ VfSectionHeader ×4
    ├ VfSwitchTile ×5
    ├ VfNavTile ×3
    └ VfDangerZone
       └ VfDeleteAccountDialog (saisie nominative)
```

### Quatre écarts imposés par le back, tous documentés

Cet écran est celui où le simulateur simplifie le plus, et où le back a déjà
tranché. Les quatre écarts sont à implémenter, pas à discuter.

**1. La double authentification n'a aucun endpoint en v1.** L'arbitrage n° 8
décrit un parcours complet — enrôlement à l'activation, mot de passe **et** code
à la désactivation — mais l'API ne l'expose pas, et `PATCH /me/settings` refuse
en `422` toute clé qu'elle ne connaît pas.

**Ce que l'application fait : elle retire l'interrupteur de la section
Sécurité.** Un interrupteur qui échoue à chaque pression est pire que son
absence ; et un interrupteur qui semblerait marcher sans rien activer serait un
mensonge sur une garantie de sécurité — la faute la plus grave possible sur cet
écran. Le réglage se fait sur le site web. Réserve C.8 de la section 9.

**2. La lettre d'information est un consentement, pas une préférence**
(arbitrage n° 11). Chaque bascule **ajoute une ligne** horodatée plutôt que d'en
modifier une : l'historique des accords et des retraits est exactement ce qu'il
faut pouvoir produire en cas de contrôle. Visuellement, l'interrupteur ne change
pas ; sémantiquement, il écrit ailleurs.

**3. Les sessions n'affichent ni IP ni géolocalisation** (arbitrage n° 9). Le
simulateur montre « Cet appareil · Cotonou » ; nous ne disposons d'aucune
géolocalisation, et l'inventer serait mentir. Afficher l'IP brute ferait de la
page un annuaire d'adresses à la portée de qui emprunte l'écran quelques
secondes. **Seuls le navigateur ou l'appareil, le système et l'ancienneté sont
montrés** — assez pour reconnaître ses propres appareils, et rien de plus.

**4. La suppression se fait en DEUX TEMPS, sur un seul point d'entrée.** Le
contrat fixe la mécanique exacte, et elle diverge de la maquette :

| Temps | Corps envoyé | Réponse |
|---|---|---|
| 1 | `confirmation` = le mot exact, `mot_de_passe` | `202`, `data.etape = "code_envoye"` — un code part par courriel. **Rien n'est supprimé** |
| 2 | `confirmation`, `mot_de_passe`, `code` | `200`, `data.etape = "suppression_programmee"` + `suppression_demandee_le` |

Trois écarts à la maquette, tous à appliquer :

- **le mot exact attendu est le mot de confirmation du contrat**, pas le nom de
  la personne. Le simulateur demande de taper son nom ;
- **le mot de passe est exigé** en plus, dès le premier temps ;
- **un code par courriel** vient ensuite. *« Mot de passe et code : le premier
  prouve qu'on connaît le compte, le second qu'on tient la boîte. Un téléphone
  volé déverrouillé passe le premier, jamais les deux. »*

La feuille de confirmation devient donc un parcours en deux temps. Réserve A.12
de la section 9 — **c'est un changement de maquette, il se décide.**

Ensuite : le compte est **fermé sur-le-champ**, les données ne disparaissent
qu'au bout de **30 jours**, la rétractation passe par un **lien signé reçu par
courriel** — *« seule voie de retour quand on ne peut plus se connecter »* — et à
l'échéance c'est une **anonymisation**, pas une suppression de ligne.

Le simulateur affiche déjà le bon message final : *« Suppression programmée —
30 jours pour changer d'avis. »*

Un seul point d'entrée, et le contrat dit pourquoi : *« le simulateur ne déclare
que `DELETE /me` ; ouvrir une route “demander un code de suppression” ajouterait
une surface que l'écran n'appelle pas. »*

### L'export RGPD

**Job asynchrone**, `202`. L'archive n'est **jamais** renvoyée dans la réponse —
le contrat en donne la raison la plus concrète : *« elle contient tout ce que la
plateforme détient d'une personne, et un corps HTTP se retrouve dans les journaux
d'un mandataire. »* Elle arrive par courriel, sur route signée.

L'arbitrage n° 14 précise les trois conditions du téléchargement — signature,
session, vérification du propriétaire — dont aucune n'est redondante : un lien
transféré ne doit pas livrer les données d'autrui à qui se trouve connecté.

L'écran confirme par *« Export RGPD lancé — vous le recevrez par e-mail. »* et
n'attend rien. **Une seconde demande dans le quart d'heure répond `409
conflit`** : afficher le message du serveur, sans relancer.

### Appels réseau

**`GET /api/v1/me/settings`** — les préférences.

**`PATCH /api/v1/me/settings`** — et le contrat impose une forme précise :

```json
{ "notifications": { "forum_activity": false }, "newsletter": true }
```

> Il attend une **valeur voulue, jamais une bascule**. *« Une bascule perd sa
> cible quand la requête est rejouée après une coupure, et l'utilisateur retrouve
> le réglage inverse de ce qu'il a demandé. »*

C'est directement applicable : l'interrupteur envoie `false` ou `true`, jamais
« inverse ce réglage ». Un **canal inconnu est refusé en `422`**, pas ignoré en
silence — le client ne peut donc pas inventer de clé de préférence.

La **lettre d'information est séparée** des préférences : c'est un consentement
marketing, horodaté et versionné, pas un réglage d'affichage. La réserve A.7 est
levée — le contrat porte bien les deux mécanismes distincts, dans le même appel.

**`POST /api/v1/me/export`** — `202`, préparation en tâche de fond. **Une seconde
demande dans le quart d'heure répond `409 conflit`** : l'écran affiche le message
du serveur tel quel, il ne relance pas.

**`DELETE /api/v1/me`** — voir ci-dessous.

**Aucun endpoint de double authentification n'existe**, ni de changement de mot
de passe authentifié. Voir la réserve C.8.

### Chargement, vide, erreur

- Chargement : lignes en squelette, interrupteurs inactifs.
- Bascule : optimiste, avec toast (*« Préférence activée. »* / *« Préférence
  désactivée. »*) et retour à l'état antérieur en cas d'échec — un interrupteur
  qui reste allumé après un échec est un mensonge. **On envoie la valeur voulue,
  jamais une bascule.**
- Changement de mot de passe : **aucun endpoint dédié en v1**. Le contrat sert
  `POST /auth/password/reset`, qui exige un code reçu par courriel, mais pas un
  changement authentifié depuis les réglages. Voir la réserve C.8.
- Mot de confirmation mal saisi à la suppression : `422 validation`, et rien ne
  part.

### Hors ligne

Lecture depuis le cache. **Aucune opération sensible hors ligne** : ni 2FA, ni
changement de mot de passe, ni export, ni suppression. Les bascules de
notification peuvent partir en file. Ce n'est pas de la prudence excessive : une
suppression de compte mise en file et rejouée trois jours plus tard, alors que
la personne a changé d'avis, est irrattrapable.

### Accessibilité

Chaque interrupteur expose le rôle `switch` et annonce son nouvel état. La zone
de danger est annoncée comme telle avant ses actions. Le champ de confirmation
nominative précise ce qui est attendu.

### Point de vigilance (aligné sur le contrat)

Suppression en **deux temps** — mot de confirmation + mot de passe, puis code —
suivie d'un délai de rétractation de 30 jours dont l'annulation **ne passe pas
par l'API** ; export RGPD asynchrone servi par route signée ; préférences
envoyées en **valeur voulue**, jamais en bascule.
