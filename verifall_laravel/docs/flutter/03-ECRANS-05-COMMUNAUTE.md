# 3. Les 30 écrans — chapitre 5 : Communauté

Cinq écrans, module `community` : le forum, un sujet, le Hub opportunités, une
offre, un article vérifié. Les cinq partagent le même onglet de la barre basse
(**Communauté**), et c'est voulu — l'information vérifiée et la parole citoyenne
sont deux faces du même espace.

Trois arbitrages gouvernent ce chapitre, et ils sont les plus importants du
produit : n° 43 (deux jugements sur le forum, jamais mélangés), n° 44 (le Hub
qualifie plus qu'il ne relaie), n° 54 et n° 55 (le Hub opportunités et le cycle
de vie d'une offre).

---

## 3.20 — Écran 20 · Forum citoyen

**Ancre** : `#/forum` — **Route** : `/forum` — **Page** : `ForumPage`
**État** : `threadListProvider(category)` (PagedNotifier)
**Origine** : `web/compte-user/forum.html`

### Ce qui est à l'écran

Barre supérieure : bouton du tiroir, titre **Communauté**, action `newspaper`
qui mène au Hub. Barre basse, onglet **Communauté** actif.

1. **En-tête** : sur-titre `COMMUNITY FACT-CHECKING HUB`, titre **« Vérif'All
   Forum »**, phrase *« Posez vos doutes, votez, et laissez la rédaction trancher
   les cas sensibles. »*
2. **Déclencheur de composition** — une carte cliquable : avatar vert à
   initiales, texte gris *« Une information vous semble douteuse ? »*, icône
   `edit` verte. Elle ouvre une feuille inférieure **Nouveau sujet** avec un
   champ **Titre de votre sujet** (8 caractères minimum) et une zone **Décrivez
   ce qui vous semble douteux…**, puis un bouton **Publier**.
3. **Pastilles de catégorie** : `Tous · Fake News · Santé · Politique · Société ·
   Technologie`.
4. **Cartes de sujet**, chacune composée ainsi :
   - **si la rédaction a tranché**, un bandeau rouge clair en tête :
     `✓ VÉRIF'ALL CONFIRME : <VERDICT>` avec icône `verified` rouge ;
   - une ligne auteur : avatar à initiales sur fond vert clair, nom, puis
     « <badge de rôle> · <ancienneté> », et la pastille de catégorie à droite ;
   - le titre en Instrument Sans ;
   - le corps sur deux lignes maximum ;
   - **si un sondage existe**, une barre de vote à trois segments (vrai vert /
     doute orange / faux rouge) suivie des trois pourcentages ;
   - une ligne de réactions : cœur + nombre, bulle + nombre de réponses.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (action Hub)
 └ CustomScrollView
    ├ VfComposerTrigger
    ├ VfCategoryChips
    └ SliverList
       └ VfThreadCard
          ├ VfOfficialVerdictBanner
          ├ VfAuthorRow
          └ VfVoteBar
```

### La règle qui structure tout le forum

**Deux jugements, jamais mélangés** (arbitrage n° 43) :

- l'**avis de la communauté** est un sondage — la barre à trois segments ;
- le **verdict de Vérif'All** est une vérification — le bandeau rouge.

Ce ne sont pas deux affichages du même chiffre. Une majorité qui se trompe reste
une majorité qui se trompe : c'est même le mécanisme central de la
désinformation. Une plateforme de fact-checking qui laisserait le sondage passer
pour un verdict se contredirait dans son écran le plus fréquenté.

Conséquence directe pour le client, écrite dans le point de vigilance :
**le bandeau officiel n'apparaît que si le serveur lie un verdict — l'interface
ne le déduit jamais des votes.**

### Appels réseau

**`GET /api/v1/forum/threads`** — **paginé par curseur** (`limit`, `cursor`).
`page` n'existe pas (arbitrage n° 68), et la raison est ici la plus parlante de
tout le contrat : sur un fil qui bouge, *« l'élément sauté peut être précisément
le démenti qu'on cherchait. »*

Le paramètre `category` du simulateur n'apparaît pas au contrat ; le filtre par
pastilles se fait donc côté client sur la page chargée, ou attend une précision.
Réserve C.5 de la section 9.

**`POST /api/v1/forum/threads`** — publication d'un sujet.

L'identifiant d'un sujet est son **`slug`**, jamais un entier — ce qui règle la
réserve A.8 : les liens profonds partagés sur WhatsApp ne révèlent plus le
volume ni n'invitent à l'énumération.

### Chargement, vide, erreur

- Chargement : cartes squelettes.
- Vide après filtre : « Aucune discussion dans cette catégorie » + retour à
  « Tous ».
- Forum entièrement vide : « Personne n'a encore posé de question » + invitation
  à ouvrir le premier sujet.
- Publication refusée : le simulateur exige 8 caractères de titre et le dit par
  un toast rouge. Les refus serveur arrivent en `422 validation` avec `details`,
  et se rendent sous le champ concerné.
- **Le badge de rôle des auteurs n'existe pas.** Les cartes du simulateur
  affichent « 🎓 Mentor Vérif'All », « ◉ Contributeur actif ». L'API sérialise un
  `AuteurResource` réduit à **uuid, nom d'affichage, initiales** : *« publier
  “untel est modérateur” sur chaque message dresserait la liste nominative de
  l'équipe de modération »* (arbitrage n° 74). Le badge est donc **retiré de la
  carte**. Réserve C.4 de la section 9.

### Hors ligne

Liste servie depuis le cache.

**La publication d'un sujet ne figure pas dans la liste des écritures mises en
file par le contrat** (§7.2) : elle n'a ni clé d'idempotence exigée, ni
contrainte d'unicité mentionnée. La mettre en file exposerait au doublon — deux
sujets identiques publiés par une réponse perdue. Deux options, à trancher
(réserve C.6) : la refuser hors ligne avec un message clair, ou lui attribuer une
`Idempotency-Key`, l'en-tête étant accepté partout.

Si elle est mise en file, l'écran doit montrer le sujet comme **« en attente
d'envoi »** et non comme publié. Un sujet qui apparaît publié puis disparaît est
pire qu'un sujet qui attend.

### Accessibilité

Le bandeau officiel est lu **avant** le titre : c'est l'information la plus
importante de la carte. La barre de vote est doublée de son texte (« vrai 62 %,
doute 21 %, faux 17 % ») — trois segments colorés ne se lisent pas.

### Point de vigilance (repris du simulateur)

Le corps des messages est du HTML Quill : **purifié avant persistance et à
l'affichage**, jamais rendu brut. Le bandeau officiel ne se déduit jamais des
votes.

Deux règles du back complètent l'écran : les liens signalés par des utilisateurs
sortent en `nofollow noopener ugc` — une plateforme qui combat la
désinformation ne transmet pas son autorité de référencement à l'arnaque qu'on
lui signale ; et **un seul niveau d'imbrication** dans les réponses, parce
qu'au-delà un fil devient illisible sur téléphone.

---

## 3.21 — Écran 21 · Sujet du forum

**Ancre** : `#/thread` — **Route** : `/forum/:id` — **Page** : `ThreadPage`
**État** : `threadProvider(id)` · `voteProvider(id)`
**Origine** : `web/compte-user/forum-sujet.html`

### Ce qui est à l'écran

Barre supérieure avec retour, **titre = la catégorie du sujet**, action `flag`
**Signaler**. Pas de barre basse.

1. **Bandeau officiel** s'il existe, en tête.
2. Ligne auteur : avatar, nom, badge de rôle, ancienneté.
3. Titre en `h-md`, corps en `t-md`.
4. **Panneau de vote** (si le sujet en porte un) : titre « Que pense la
   communauté ? », barre de 10 de haut, les trois pourcentages, puis soit
   « Votre avis, en un geste : » soit « Votre vote : <choix> », et trois boutons
   **Vrai · Doute · Faux**. Le bouton correspondant au vote émis prend la couleur
   du choix.
5. Ligne de réactions : bouton **cœur** avec le compte (rempli et rouge si
   liké), bouton **Partager**.
6. Titre « <n> réponses », puis les réponses : avatar, auteur, badge,
   ancienneté, corps.
7. **Composeur de réponse** : zone de texte de 90 minimum, 800 caractères, et un
   bouton primaire **Publier ma réponse**.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (signalement)
 └ ListView
    ├ VfOfficialBanner
    ├ VfAuthorRow + VfThreadBody
    ├ VfVotePanel
    ├ VfReactionRow
    ├ VfReplyTile ×N
    └ VfReplyComposer
```

### Appels réseau

Tous portent le **`slug`** du sujet, jamais un entier.

- **`GET /api/v1/forum/threads/{slug}`**
- **`POST /api/v1/forum/threads/{slug}/vote`**
- **`POST /api/v1/forum/threads/{slug}/replies`**
- **`POST /api/v1/forum/threads/{slug}/report`**

Trois champs pilotent l'écran :

| Champ | Ce qu'il porte |
|---|---|
| `data.verdict_officiel` | **N'apparaît que si le serveur a lié un verdict.** Absent = pas de bandeau |
| `data.votes` | La répartition **en pourcentages, recalculée** par le serveur |
| `data.mon_vote` | Mon propre choix, pour marquer le bouton correspondant |

Vote, signalement et — par contrainte d'unicité en base — les deux se rejouent
sans risque depuis la file hors ligne.

**Répondre dans un fil verrouillé** : `422 action_impossible`. Le contrat est
net : *« la modération a décidé ; contourner sa décision par l'API serait une
faille. »* L'écran masque le composeur, mais c'est le serveur qui protège.

### Le vote

Le contrat confirme le comportement : **optimiste puis réconcilié**. *« Le vote
optimiste est sans danger : la réponse du serveur fait foi, l'unicité est en
base. »*

Concrètement, le client doit accepter que le serveur lui renvoie des
pourcentages différents de ceux qu'il vient d'afficher, et se ranger sans
clignoter.

### Le signalement

L'arbitrage n° 52 est explicite et il change ce que l'écran doit dire :
**signaler n'est pas voter**. Cinq personnes qui signalent le même message ne le
rendent pas cinq fois plus répréhensible ; il n'y a **aucun masquage
automatique** au-delà d'un seuil, parce que ce serait offrir un bouton de censure
à n'importe quel groupe organisé. Un humain tranche, toujours.

La fenêtre de signalement doit le dire **avant l'envoi** — promettre un retrait
qui n'aura pas lieu décourage celui qui a signalé de bonne foi.

**La liste fermée des motifs est désormais connue** — le contrat la donne :

```
desinformation · haine · arnaque · spam · donnees · autre
```

Ce sont les valeurs techniques ; les libellés affichables voyagent avec elles
comme pour toute énumération de l'API. **Signaler deux fois ne crée qu'une
ligne**, ce qui rend l'appel rejouable depuis la file.

Le simulateur se contente d'un toast *« Signalement transmis à la modération. »* :
la feuille de motifs se construit sur ces six valeurs, et sur rien d'autre.

### Chargement, vide, erreur

- Chargement : squelettes.
- Sujet fermé par la modération : le composeur disparaît et l'écran dit pourquoi.
  Un envoi forcé revient en `422 action_impossible` : afficher le message tel
  quel.
- Sujet introuvable : `404 introuvable`, qui couvre aussi « pas à vous ». Écran
  de repli qui n'affirme rien — « cette discussion n'est pas disponible ».
- Réponse trop courte : le simulateur exige 5 caractères, toast rouge ; le
  serveur arbitre en `422 validation`.
- Vote refusé : l'interface revient à l'état serveur, avec le message reçu.
- **Le badge de rôle de l'auteur et des répondants n'existe pas** : l'API rend un
  `AuteurResource` sans rôle. Voir écran 20 et réserve C.4.

### Hors ligne

Lecture depuis le cache. Vote et réponse partent en file. Le vote hors ligne doit
afficher son caractère provisoire : le résultat consolidé viendra du serveur.

### Accessibilité

Les trois boutons de vote annoncent leur état sélectionné. Le composeur annonce
le nombre de caractères restants aux paliers, pas à chaque frappe.

### Point de vigilance (repris du simulateur)

Vote optimiste puis réconcilié ; un seul vote par citoyen, garanti en base.

---

## 3.22 — Écran 22 · Hub opportunités

**Ancre** : `#/hub` — **Route** : `/hub?rubrique=` — **Page** : `HubPage`
**État** : `hubFeedProvider(rubrique, type)`
**Origine** : `web/compte-user/hub-infos.html`, renommé **Hub opportunités**

### Ce qui est à l'écran

Barre supérieure : titre **Hub opportunités**, retour si empilé, pastille de
points. Barre basse, onglet Communauté.

En-tête : sur-titre `VÉRIFIÉ PAR LA RÉDACTION`, titre **« Hub opportunités »**,
phrase *« L'information et les occasions à saisir, contrôlées à la source. »*

Puis **trois rubriques** en pastilles : **Actualités · Opportunités Bénin ·
Opportunités Internationales**.

#### Rubrique « Actualités »

- une **carte à la une** : couverture pleine largeur de 120 portant « L'ANALYSE
  DU JOUR », puis, dans le corps, la pastille de verdict, l'ancienneté, le titre
  et le chapô ;
- un titre « Les informations importantes, vérifiées » ;
- des cartes de brève : pastille de verdict + ancienneté sur une ligne, titre,
  chapô sur deux lignes, et **la source primaire en vert** en pied.

#### Rubriques « Opportunités »

- des **pastilles de type** : « Tous » plus les types **réellement présents**
  dans les offres visibles. Le simulateur les calcule à partir du contenu, et
  l'arbitrage n° 55 en fait une règle : proposer un type sans offre mène à une
  liste vide, et une liste vide derrière un filtre passe pour un bug ;
- une ligne de décompte : « <n> opportunité(s) ouverte(s) » à gauche, et à
  droite, s'il y en a, « <m> archivée(s) automatiquement » ;
- les **cartes d'opportunité** : pastille de type (colorée par famille), pastille
  rouge **CLÔTURÉE** si la date limite est passée, mention verte **Vérifié** à
  droite ; titre ; « <organisation> · <lieu> » ; et une ligne d'échéance avec son
  icône `event`, dont le texte et la couleur suivent l'état :

  | Situation | Texte | Couleur |
  |---|---|---|
  | plus de 7 jours | Clôture le JJ/MM/AAAA · J-n | ardoise |
  | 2 à 7 jours | Clôture le JJ/MM/AAAA · J-n | orange |
  | demain | Clôture demain | rouge |
  | aujourd'hui | Dernier jour | rouge |
  | dépassée | Clôturée le JJ/MM/AAAA · archivage dans n j | rouge |

- **l'ordre de la liste** : les offres ouvertes d'abord, échéance la plus proche
  en tête ; les clôturées ensuite, avant archivage ;
- deux encarts de pied, toujours présents : *« Une offre reste consultable
  7 jours après sa date limite, puis elle est archivée automatiquement. »*
  (icône `schedule` orange) et *« Aucun employeur sérieux ne demande de frais de
  dossier. Signalez-nous toute offre qui réclame un paiement. »* (icône
  `gpp_maybe` rouge).

### Arbre de widgets

```
DefaultTabController (3)
 └ Scaffold
    ├ VfAppBar (points)
    ├ VfRubricChips (Actualités · Bénin · International)
    └ TabBarView
       ├ VfNewsFeed (VfLeadArticleCard + VfNewsCard)
       └ VfOpportunityFeed
          ├ VfTypeChips (CDI, CDD, Stage…)
          └ VfOpportunityCard (+ VfDeadlinePill)
```

### La taxonomie

Liste **fermée** : CDI · CDD · Stage · Alternance · Volontariat · Bourse ·
Conférence · Financement. Le back en ajoute deux (appel à candidature, autre).
Sur un champ libre, la même chose finit écrite de six façons et le filtre cesse
de fonctionner sans que personne s'en aperçoive — il rend simplement moins de
résultats.

Les familles visuelles du simulateur : contrat (CDI, CDD, Stage, Alternance) en
bleu ; étude (Bourse, Financement) en violet ; événement (Conférence) en orange ;
Volontariat en vert par défaut.

### L'archivage à J+7 — la règle la plus importante de l'écran

Le contrat reprend le point de vigilance et durcit la formule :

> `data.expiree` et `data.jours_restants` viennent du serveur. L'archivage est
> une commande planifiée ; **le client ne l'applique jamais lui-même** — une
> horloge de téléphone mal réglée ferait disparaître une offre encore ouverte,
> ou pire, présenterait comme candidatable une offre fermée.

Le simulateur calcule l'état localement parce qu'il tourne sans serveur.
L'application ne le fait pas : elle affiche `expiree`, `jours_restants` et les
libellés que l'API lui donne. L'arbitrage n° 55 ajoute que la requête
d'affichage côté serveur **double** la tâche planifiée, pour rester juste même si
le planificateur n'est pas branché.

**Cela vaut aussi pour le tri.** L'ordre décrit plus haut — ouvertes d'abord,
échéance la plus proche en tête — ne doit pas être recalculé localement à partir
de dates : il vient de l'ordre de la liste paginée, sans quoi le curseur et
l'affichage divergeraient.

### Ce qui a été retiré, et qu'il ne faut pas réintroduire

**Les portails officiels ne sont plus affichés côté citoyen** (arbitrage n° 54).
Les données restent en base, l'écran d'administration reste utile : c'est une
mise en retrait décidée, pas un abandon. Ne pas les rajouter au mobile.

Et l'avertissement « aucun frais de dossier » ne concerne que les
**recrutements** : l'écrire sous une conférence payante serait faux, et un
avertissement faux use la crédibilité des vrais.

### Appels réseau

- **`GET /api/v1/hub/articles`** — les brèves, **paginées par curseur**.
- **`GET /api/v1/hub/opportunities`** — les opportunités, **paginées par
  curseur**.

`page` n'existe pas. Et **les paramètres `scope` et `type` que le simulateur
déclare ne sont pas détaillés au contrat** : celui-ci ne documente que
l'endpoint. Tant qu'ils ne sont pas confirmés, le partage entre les deux
rubriques d'opportunités et le filtre par type se font **côté client sur la page
chargée** — ce qui reste cohérent avec le cache de 15 minutes et le filtrage hors
ligne. Réserve C.5 de la section 9.

Deux champs viennent du serveur et **ne se calculent jamais** :
**`data.expiree`** et **`data.jours_restants`**.

### Chargement, vide, erreur

- Chargement : cartes squelettes par rubrique.
- Vide (opportunités) : icône `work_off`, « Aucune offre dans cette catégorie »,
  *« Revenez bientôt : de nouvelles offres sont vérifiées chaque semaine. »*
- Vide (actualités) : l'arbitrage n° 51 donne le ton juste — dire franchement que
  seules les offres et brèves **contrôlées** sont publiées, et qu'aucune ne l'est
  aujourd'hui.
- Erreur : cache si disponible.
- Une brève ou une opportunité **non publiée** répond `404`, même en connaissant
  le slug.

### Hors ligne

Les deux rubriques sont en cache, **15 minutes**, la durée la plus courte de tout
le contrat (§7.1). C'est cohérent : c'est le contenu le plus périssable de
l'application, parce qu'une opportunité porte une échéance.

Les compteurs d'échéance affichés sont ceux du dernier chargement, et le bandeau
d'horodatage devient **indispensable** : `jours_restants` vieux de trois jours
induit en erreur, et le client n'a pas le droit de le recalculer.

### Accessibilité

L'échéance est annoncée en toutes lettres (« clôture dans 4 jours »), la couleur
seule ne suffit pas. La carte à la une annonce qu'elle est l'article principal.

### Point de vigilance (repris du simulateur)

Portails officiels retirés du parcours citoyen ; archivage J+7 tranché par le
serveur.

---

## 3.23 — Écran 23 · Détail d'une offre

**Ancre** : `#/offer` — **Route** : `/hub/opportunites/:id`
**Page** : `OpportunityPage` — **État** : `opportunityProvider(id)`
**Origine** : Hub opportunités, rubriques Bénin / International

### Ce qui est à l'écran

Barre supérieure avec retour, **titre = « Opportunité Bénin » ou « Opportunité
internationale »**, action de partage.

1. Une ligne de pastilles : type, **EMPLOYEUR VÉRIFIÉ** (pastille verte avec
   `verified`) si c'est le cas, **CLÔTURÉE** si l'échéance est passée.
2. Titre en `h-lg`, puis le chapô en `t-md` encre.
3. **Liste de faits** en trois lignes : Organisation (icône `apartment`), Lieu
   (icône `location_on`), **Échéance** (icône `event` colorée par l'état, valeur
   en gras de la même couleur).
4. Le corps de l'offre en prose.
5. **Action principale**, selon l'état :
   - offre ouverte → bouton primaire **Postuler sur <domaine>** avec l'icône
     `open_in_new` ;
   - offre clôturée → un encart rouge clair : *« La date limite est dépassée.
     L'offre reste consultable quelques jours, puis disparaîtra du Hub. »*
     **et aucun bouton de candidature.**
6. **Avertissement anti-arnaque**, icône `gpp_maybe` rouge : *« Jamais de frais.
   Si on vous réclame un paiement pour candidater, c'est une arnaque —
   signalez-la. »*
7. Bouton fantôme **Signaler cette offre**.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (partage)
 └ ListView
    ├ Row (VfTypeChip + VfVerifiedBadge)
    ├ Text.headline + chapô
    ├ VfFactList (organisation · lieu · échéance)
    ├ VfProseBody
    ├ VfApplyButton | VfClosedNotice
    └ VfScamWarning + VfReportButton
```

### Appels réseau

- **`GET /api/v1/hub/opportunities/{slug}`** — l'identifiant est un **slug**.
- **`POST /api/v1/hub/opportunities/{slug}/report`** — mêmes six motifs que le
  forum (`desinformation`, `haine`, `arnaque`, `spam`, `donnees`, `autre`).
  Signaler deux fois ne crée qu'une ligne : l'appel est rejouable depuis la file.

Trois champs décident de l'affichage :

| Champ | Rôle |
|---|---|
| `data.verifiee` | Dit qu'**un humain de l'équipe a contrôlé l'annonce**. Le contrat précise : *« ça ne se déduit d'aucun autre champ »* — c'est ce qui pilote la pastille « EMPLOYEUR VÉRIFIÉ » |
| `data.expiree` | Pilote la pastille « CLÔTURÉE » et le retrait du bouton de candidature |
| `data.jours_restants` | Le compte à rebours, **jamais calculé localement** |

### La candidature

Point de vigilance, mot pour mot : la candidature s'ouvre en **onglet système**,
**jamais en WebView interne** — *« le citoyen doit voir le vrai domaine dans la
barre d'adresse avant de saisir ses données »*.

C'est la règle la plus importante de l'écran. Une WebView masque le domaine ; sur
une page qui met en garde contre les fausses offres, masquer le domaine serait
une contradiction complète.

### L'avertissement — tranché : systématique

La réserve était ouverte entre le simulateur (systématique) et l'arbitrage n° 55
(recrutements seulement). **Le contrat tranche pour le simulateur**, et il
motive :

> La fiche d'une opportunité porte **toujours** `meta.avertissement`.
> Systématique, pas conditionnel : **une offre frauduleuse ne se signale pas
> d'elle-même, et c'est la plus dangereuse.**

Le texte vient donc de `meta.avertissement` et s'affiche sur **toutes** les
fiches. Le client ne décide pas de le montrer ; il montre ce qu'il reçoit.

### Chargement, vide, erreur

- Chargement : squelettes.
- **Offre archivée atteinte par lien profond** : c'est le cas le plus fréquent —
  un lien partagé sur WhatsApp survit à l'offre. Le contrat répond `404` sur tout
  contenu non publié. Écran de repli explicite, formulé sans affirmer : « cette
  offre n'est plus disponible » + retour vers le Hub. Pas une page d'erreur.
- Signalement : le simulateur confirme par *« Offre signalée — la rédaction
  vérifie sous 24 h. »* Ne conserver ce délai que si l'équipe s'y engage.

### Hors ligne

Fiche lue depuis le cache. La candidature demande le réseau et le dit. Le
signalement part en file.

### Accessibilité

L'échéance est annoncée en clair. Le bouton de candidature annonce le domaine
cible et le fait qu'il quitte l'application.

### Point de vigilance (confirmé par le contrat)

Onglet système obligatoire ; avertissement anti-frais **systématique**, servi par
`meta.avertissement`.

---

## 3.24 — Écran 24 · Article vérifié

**Ancre** : `#/article` — **Route** : `/hub-infos/:id` — **Page** : `ArticlePage`
**État** : `articleProvider(id)` — **Origine** : `web/compte-user/hub-article.html`

### Ce qui est à l'écran

Barre supérieure avec retour, **titre = la catégorie**, action de partage.

1. Une couverture de 130 portant la catégorie en capitales.
2. Une ligne : pastille de verdict à gauche, ancienneté à droite.
3. Titre en `h-lg`, chapô en `t-md` encre.
4. **Encart de source** : icône `source` verte, *« Source primaire : <nom> »*.
5. Le corps en prose, suivi d'un paragraphe **fixe** qui explique la méthode :
   *« La rédaction Vérif'All applique la même méthode à chaque cas :
   identification de l'affirmation, recherche de la source primaire, vérification
   de la date, puis recoupement par au moins deux sources indépendantes. »*
   C'est du contenu éditorial — la parole du produit — et il se transpose tel
   quel.
6. **Encart Académie** sur fond forêt : pastille `ACADÉMIE`, titre *« Apprenez à
   faire ce travail vous-même »*, phrase *« Nos formations sont gratuites et
   durent moins d'une heure. »*, bouton fantôme **Voir le catalogue**.
7. Bouton vert WhatsApp **Partager sur WhatsApp**.

### Arbre de widgets

```
Scaffold
 └ CustomScrollView
    ├ SliverAppBar (couverture)
    ├ VfVerdictBadge + VfSourceTile
    ├ VfProseBody
    └ VfAcademyPromo
```

### Appels réseau

**`GET /api/v1/hub/articles/{slug}`** — l'identifiant est un **slug**, jamais un
entier. Le lien profond devient donc `verifall://hub/{slug}`.

Une brève non publiée répond **404**, même en connaissant le slug : c'est le cas
qu'il faut savoir afficher (voir plus bas).

### Ce que le back impose

- **Le verdict vient de l'équipe** (arbitrage n° 44). C'est ce qui sépare le Hub
  d'un agrégateur : on n'y relaie pas une information, on dit ce qu'elle vaut.
  Une brève sans verdict affiche **« EN COURS »** et non un bandeau vide — un
  bandeau vide passerait pour un défaut d'affichage.
- **Le Hub affiche « DÉMENTI » là où le rapport dit « FAUX »**. C'est un registre,
  pas une divergence : on dément une rumeur, on qualifie une affirmation. Le
  contrat rend cela mécanique : **valeur technique et libellé français voyagent
  ensemble** (`verdict` + `verdict_libelle`). Le client affiche le libellé et
  branche sur la valeur — *« il ne fabrique jamais le libellé lui-même : ajouter
  un cas côté serveur casserait tous les `switch` du client. »*
- **Le compteur de lectures est incrémenté par le serveur**, une fois par
  ouverture. Le client ne l'incrémente pas — la leçon du web (§44) vaut ici :
  un rendu rejoué gonflerait les statistiques sans que personne s'en aperçoive.
- **Le temps de lecture est calculé** sur le texte réel (arbitrage n° 67), pas
  affiché depuis une valeur figée.

### Chargement, vide, erreur

- Chargement : squelette de prose.
- **Article dépublié atteint par lien profond** : c'est explicitement le cas
  nommé par le simulateur. Écran de repli qui l'explique, avec retour vers le
  Hub.
- Erreur : cache si disponible.

### Hors ligne

Article en cache : c'est un contenu qu'on rouvre pour le montrer. Le partage
WhatsApp fonctionne hors ligne (le lien est connu) ; l'ouverture de la source
externe non.

### Accessibilité

Le verdict est annoncé avant le titre. Le nom de la source primaire est lu en
entier — c'est l'élément que le lecteur doit retenir.

### Point de vigilance (repris du simulateur)

Lien profond `verifall://hub/{slug}` : `go_router` doit résoudre l'article **même
application fermée**, avec un écran de repli sur `404`. Formuler ce repli sans
affirmer : le 404 du contrat couvre aussi bien « dépublié » que « jamais
existé », et un uuid ou un slug deviné ne doit renseigner sur rien.
