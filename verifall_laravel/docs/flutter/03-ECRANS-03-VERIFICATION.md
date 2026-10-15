# 3. Les 30 écrans — chapitre 3 : Vérification

Cinq écrans, module `verify`. C'est le cœur du produit : c'est là qu'une unité
se dépense, et donc là que la moindre approximation coûte de l'argent à
quelqu'un. Trois arbitrages du back gouvernent ce chapitre et il faut les avoir
lus : n° 20 (le rapport sort toujours), n° 28 (la barre de progression ne ment
pas), n° 35 (aucun rapport creux).

---

## 3.9 — Écran 09 · Vérifier un contenu

**Ancre** : `#/verify` — **Route** : `/verifier` — **Page** : `VerifyPage`
**État** : `verifyFormProvider` (StateNotifier)
**Origine** : `web/compte-user/verifier.html`

### Ce qui est à l'écran

Barre supérieure : titre **Vérifier**, retour si l'on n'est pas à la racine,
pastille de points. Barre basse, onglet **Vérifier** actif.

1. **Indicateur d'étapes** — quatre libellés reliés par des filets :
   `CONTENU · SOURCES · PREUVES · VERDICT`, le premier en vert. Il situe l'action
   dans la démarche, il ne progresse pas sur cet écran.
2. **Carte de saisie** en trois temps numérotés :
   - **① Quel type de contenu ?** — une grille de 3 × 2 (cinq tuiles) : Texte
     (`article`), URL (`link`), Image (`image`), Vidéo (`videocam`), Audio
     (`mic`). Chaque tuile porte **en haut à droite une pastille rouge indiquant
     son coût** : 1 pt, 1 pt, 2 pts, 5 pts, 5 pts. La tuile choisie passe en vert
     plein.
   - **② Donnez-nous le contenu** — la zone change selon le type :
     - *Texte* : zone de texte, 1 000 caractères maximum, étiquette *« Collez le
       message, la rumeur ou l'affirmation… »*, mention *« Minimum
       10 caractères. »* ;
     - *URL* : champ d'adresse, étiquette d'exemple, mention *« Nous analysons la
       source, le domaine, la date et le contenu. »* ;
     - *Image / Vidéo / Audio* : une **zone de dépôt** (bordure pointillée,
       rayon 16) avec son icône, son invite et sa ligne technique —
       Image : « Prendre une photo ou choisir dans la galerie » / « JPG, PNG,
       WEBP · 10 Mo max · recherche inversée + métadonnées + détection IA » ;
       Vidéo : « Enregistrer ou importer une vidéo » / « Extraction d'images
       clés · OCR · recherche inversée » ; Audio : « Enregistrer une note vocale
       ou importer un fichier » / « Transcription · analyse sémantique ·
       détection de voix clonée ». Une fois le fichier choisi, une ligne verte
       affiche son nom, son poids et « prêt pour l'analyse ».
   - **③ Un contexte à ajouter ? (facultatif)** — un champ court, 140 caractères,
     étiquette *« Où l'avez-vous vu ? Depuis quand circule-t-il ? »*.
3. **Encart de coût** — icône `toll` verte : *« Cette vérification consommera
   <n> unité(s) — il vous restera <m> unités. »*
4. Bouton primaire pleine largeur **Lancer la vérification** (icône
   `travel_explore`).
5. **Encart « Vérifier soi-même ? »** — *« Le répertoire d'outils OSINT vous
   forme à l'autonomie. »* + bouton **Voir** vers l'écran 13.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar
 └ Column
    ├ VfStepIndicator (5)
    ├ VfTypeSelector (GridView 3×2)
    │   └ VfTypeTile (pastille de coût)
    ├ AnimatedSwitcher
    │   ├ VfTextArea | VfUrlField
    │   └ VfMediaPicker (caméra / galerie)
    ├ VfContextField
    └ VfCostNotice + VfPrimaryButton
```

### État

`verifyFormProvider` : type choisi, brouillon de texte ou adresse, fichier
sélectionné, contexte. **Changer de type efface le fichier** — c'est ce que fait
le simulateur, et c'est juste : un fichier audio n'a rien à faire dans une
analyse d'image.

Le brouillon doit survivre à une mise en arrière-plan : quelqu'un qui bascule
vers WhatsApp pour recopier un message ne doit pas revenir sur un champ vide.

### Appels réseau — deux appels, dans cet ordre

Le contrat impose la séquence (arbitrage n° 72) : **on téléverse d'abord, on
soumet ensuite**, jamais un multipart unique.

**1. `POST /api/v1/uploads`** — uniquement pour Image, Vidéo, Audio. Multipart,
champs `type` (`image` | `video` | `audio`) et `fichier`.
Réponse : `201 { "data": { "televersement": "<uuid>", "nom", "octets" } }`.

Plafonds, **identiques à l'écran web** et à valider **avant** l'envoi :

| Type | Plafond | Formats acceptés |
|---|---:|---|
| image | 10 Mo | jpg, jpeg, png, webp |
| video | 100 Mo | mp4, webm, mov |
| audio | 25 Mo | mp3, wav, ogg, m4a |

Le fichier part sur le **disque privé**, sous un nom aléatoire, et **attend deux
heures**. Son identifiant est lié au compte : un identifiant intercepté ne permet
ni de faire analyser le fichier d'un autre, ni d'en découvrir l'existence — les
trois cas (inconnu, expiré, pas à vous) se répondent pareil.

> **Le simulateur annonce « 10 Mo max » pour l'image ; c'est exact. Il n'annonce
> rien pour la vidéo et l'audio.** Les deux lignes techniques de la zone de dépôt
> doivent donc porter les plafonds réels, sans quoi quelqu'un attend trois
> minutes pour se faire refuser.

**2. `POST /api/v1/verifications`** — corps : `type`, `affirmation`,
`contexte?`, `televersement?`. Les types `image`, `video` et `audio` **exigent**
un `televersement`. Réponse `201`, avec **`meta.solde` recalculé**.

Cette soumission ne pèse que quelques centaines d'octets, ce qui la rend
rejouable sans douleur — c'est tout l'intérêt du découpage.

### La règle qui prime sur cet écran

**Le coût n'est jamais envoyé par le client.** Le contrat est explicite : *« il
est lu du type. »* Le simulateur porte un barème (1/1/2/5/5) parce qu'il doit
fonctionner seul ; le client réel ne l'envoie pas, et ne le connaît pas par cœur.

Le solde affiché dans l'encart de coût et après soumission vient de
**`meta.solde`**, recalculé par le serveur — jamais d'une soustraction locale.

### Chargement, vide, erreur

Validations que le simulateur applique **avant** d'envoyer quoi que ce soit :

| Cas | Message |
|---|---|
| Texte de moins de 10 caractères | *Le texte doit contenir au moins 10 caractères.* |
| Adresse mal formée | *Entrez une URL valide (https://…).* |
| Aucun fichier choisi | *Sélectionnez un fichier à analyser.* |
| Solde insuffisant | *Unités insuffisantes : rechargez votre solde.* puis navigation automatique vers « Mes unités » |

Cette dernière est importante : l'écran **ne se contente pas de refuser**, il
emmène vers la solution. Ne pas la retirer.

Les échecs serveur, avec leur code exact :

| Situation | Réponse | Ce que fait l'écran |
|---|---|---|
| Pas assez d'unités | **`402 solde_insuffisant`** | **Ouvrir l'écran de recharge.** C'est le seul code HTTP qui dise « il manque de quoi payer », et le contrat l'emploie exprès pour ça — un message générique s'afficherait au moment où la personne est le plus près d'acheter |
| Plafond horaire atteint | **`429 debit_depasse`** + `Retry-After` | Afficher le message du serveur et le délai. **Aucun débit.** Le plafond est de 20 analyses par heure, **partagé avec le site web** — on ne double pas son quota en alternant les deux clients |
| Champ invalide, téléversement manquant | `422 validation` | Erreur sous le champ concerné |
| Téléversement expiré ou inconnu | `404 introuvable` | Re-téléverser, puis re-soumettre |

Un cas de plus, qui vient du back : **une réponse déjà connue** (arbitrage n° 25)
peut revenir instantanément avec les unités **rendues**. L'écran doit savoir
l'afficher sans passer par la chronologie d'analyse, et l'expliquer : la personne
a vu un coût annoncé, il faut lui dire pourquoi elle n'a rien payé.

**Fichier trop lourd ou d'un format refusé** : rejeté côté client avant l'envoi,
avec le plafond en clair.

### Après soumission

Débit annoncé par un toast (*« <n> unité(s) débitée(s) — analyse lancée. »*),
navigation vers l'écran 10 avec l'identifiant de l'analyse.

### Hors ligne

Le contrat range `POST /verifications` parmi les écritures mises en file, et
c'est **l'un des deux seuls actes qui exigent une `Idempotency-Key`** — parce que
c'est l'un des deux qui coûtent.

- L'écran accepte la saisie et **le dit** : « Sans connexion — votre demande
  partira dès le retour du réseau ». Le contrat insiste : *« ne pas simuler une
  attente »* — pas de chronologie d'analyse tant que rien n'est parti.
- **Aucune unité n'est débitée localement.**
- La clé d'idempotence est un **UUID généré par acte, pas par tentative**, stocké
  dans la ligne de file et réutilisé à chaque rejeu. Une reprise avec la même clé
  renvoie la réponse mémorisée à l'identique, avec `Idempotent-Replay: true`, et
  **rien n'est réexécuté**. C'est ce qui empêche d'être débité deux fois pour une
  seule question.
- Le média est **copié** dans le stockage privé de l'application, pas seulement
  référencé : l'utilisateur peut supprimer la photo de sa galerie entre-temps, et
  surtout le téléversement serveur **expire au bout de deux heures** — un rejeu
  au-delà doit re-téléverser.
- Seuls les **succès** sont mémorisés côté serveur. Un `402` au rejeu n'est donc
  pas figé : il se rejoue légitimement après une recharge.

### Accessibilité

- Chaque tuile de type annonce son nom **et son coût** : « Image, 2 unités ».
  Une pastille rouge de 9 px de haut n'est pas lisible autrement.
- La zone de dépôt est un bouton, avec un libellé qui dit ce qui va s'ouvrir.
- L'encart de coût est annoncé **avant** le bouton d'envoi dans l'ordre de
  lecture : personne ne doit découvrir un débit après l'avoir déclenché.

### Point de vigilance (repris du simulateur)

Fichiers : `image_picker`, compression avant envoi, nom aléatoire, disque
**privé** côté serveur. Le coût vient du serveur. Idempotence par identifiant
client.

---

## 3.10 — Écran 10 · Analyse en cours

**Ancre** : `#/verifying` — **Route** : `/verification/:uuid`
**Page** : `VerificationProgressPage`
**État** : `verificationStreamProvider(uuid)`
**Origine** : `web/compte-user/verification-en-cours.html`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Analyse en cours**. Pas de barre basse.

1. **Carte d'en-tête** centrée : rond vert clair de 64 avec `travel_explore`,
   titre **« Le laboratoire travaille »**, le titre de la soumission en dessous,
   une barre de progression, puis *« Étape <n> sur <N> · environ <s> s
   restantes »*.
2. **Carte « Traçabilité de l'analyse »** : une chronologie verticale de cinq
   étapes, chacune avec son titre et sa ligne d'explication —
   | # | Étape | Détail |
   |---|---|---|
   | 1 | Réception et empreinte du contenu | Hash SHA-256, horodatage, mise en file |
   | 2 | Extraction et normalisation | OCR, transcription, découpage en affirmations |
   | 3 | Recoupement des sources ouvertes | Bases IFCN, portails officiels, archives web |
   | 4 | Analyse par les moteurs IA | Orchestrateur multi-modèles + score de confiance |
   | 5 | Revue éditoriale et verdict | Contrôle humain, rédaction du rapport |

   Les étapes franchies portent une coche sur pastille verte ; l'étape courante
   est cerclée de vert et **bat** ; les suivantes sont numérotées en gris.
3. **Encart d'information** : *« Vous pouvez quitter cet écran : une notification
   vous préviendra dès que le verdict est prêt. »*

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (retour)
 └ Column
    ├ VfPulseHeader + VfProgressBar
    └ VfTimeline
       └ VfTimelineStep (fait | actif | à venir)
```

### État

`verificationStreamProvider(uuid)` : un flux. Le simulateur simule la progression
par minuteries ; **l'application ne doit surtout pas le faire**. L'arbitrage
n° 28 est catégorique et c'est la règle la plus importante de cet écran :

> Le prototype fait défiler ses étapes sur des minuteurs aléatoires. Ici elles
> sont posées par l'orchestrateur au moment où il les franchit : si l'analyse
> cale sur les moteurs, l'affichage cale avec elle. Une barre qui avance toute
> seule pendant qu'un service est en panne ment à qui la regarde.

La progression affichée est donc **exactement** l'étape reçue du serveur. Le
temps restant annoncé vient lui aussi du serveur, ou n'est pas affiché.

### Appels réseau

**`GET /api/v1/verifications/{uuid}`** — et c'est **le même endpoint que le
rapport** (écran 11). Le contrat l'explique : *« c'est le même objet à deux
moments de sa vie, et le client n'a pas à savoir lequel appeler avant d'avoir lu
l'état. »*

Deux champs pilotent cet écran : **`data.avancement`** et **`data.etape`**.
Et un troisième décide de la bascule : **`data.rapport`** est `null` tant
qu'aucun verdict n'a été rendu.

> **La clé `rapport` est toujours présente.** Le contrat précise pourquoi elle
> vaut `null` et non `{}` : *« `rapport: {}` ferait croire à un verdict vide, ce
> qui est le pire malentendu possible sur une plateforme de fact-checking. »*
> Le client teste donc la **nullité**, jamais la vacuité.

**Canal Reverb privé `private-verification.{uuid}`** — les transitions d'étape.

Le contrat pose la hiérarchie sans ambiguïté : *« l'avancement se suit par
sondage, **ou** par Reverb quand le canal est branché. »* Le sondage est donc le
mode **nominal**, pas le repli. Le plafond de lecture — 120 par minute — a été
dimensionné exprès pour un sondage toutes les trois secondes : *« se faire
éconduire à ce moment-là ferait croire à une panne. »*

Le client lit malgré tout `X-RateLimit-Remaining` et espace de lui-même sous
10 % : c'est précisément ici que l'auto-régulation sert.

### Chargement, vide, erreur

- **Chargement initial** : chronologie en squelette, jamais une étape 1 supposée.
  Et jamais depuis le cache : le contrat interdit explicitement de servir
  **l'état d'une analyse en cours** depuis le cache — *« on relit cet écran
  précisément parce qu'on attend un changement »*.
- **Analyse terminée** (`data.rapport` non nul) : navigation **en remplacement**
  vers le rapport (écran 11). Remplacer et non empiler : revenir en arrière sur
  une analyse achevée n'aurait pas de sens.
- **Blocage prolongé** : l'arbitrage n° 28 demande que l'écran le dise et propose
  de revenir plus tard. L'arbitrage n° 35 ajoute le cas réel : trop peu de
  moteurs disponibles → statut « Reprise programmée », rendez-vous annoncé, et
  un bouton **Relancer maintenant** qui apparaît une fois le délai écoulé. Le
  client doit savoir afficher ce statut : c'est un état à part entière, pas une
  erreur.
- **Échec définitif** : après épuisement des tentatives, le serveur rend les
  unités. L'écran l'annonce explicitement — *« vos unités vous ont été
  rendues »*. Un écran d'échec sans mention du remboursement est proscrit par la
  règle du chantier §7.
- **Perte du temps réel** : bascule silencieuse sur le sondage, avec un indice
  discret de reconnexion. Ne pas afficher d'erreur pour une coupure de socket.

### Hors ligne

L'écran reste consultable avec le dernier état connu, horodaté. La reprise du
flux se fait au retour du réseau. Le message *« vous pouvez quitter cet écran »*
prend ici tout son sens : c'est la promesse qui rend l'attente supportable, et
elle repose sur la notification (écran 08).

### Accessibilité

La chronologie s'annonce comme une liste ordonnée d'étapes avec leur état
(« étape 3 sur 5, en cours »). Le battement du point actif est purement
décoratif et disparaît sous `disableAnimations` — l'état doit donc être porté par
le texte, pas par l'animation.

### Point de vigilance — et la promesse qui ne tient plus en v1

Le simulateur affiche : *« Vous pouvez quitter cet écran : une notification vous
préviendra dès que le verdict est prêt. »*

**Aucune notification ne partira en v1.** Il n'existe ni magasin de
notifications, ni FCM (voir écran 08 et section 9). La promesse est donc fausse
telle quelle, et l'afficher serait exactement le « rapport creux » que la règle
du chantier §7 proscrit.

Deux options, et c'est un **arbitrage produit** — réserve C.2 de la section 9 :

1. **corriger le texte** — « L'analyse continue même si vous quittez cet écran.
   Retrouvez-la dans Mes vérifications. » C'est vrai, l'analyse tourne côté
   serveur, et l'écran 12 sait afficher une analyse en cours ;
2. maintenir l'écran au premier plan pendant l'analyse — ce qui contredit le
   principe suivant.

Le principe, lui, ne change pas : **ne jamais bloquer la navigation sur une
opération serveur.** L'option 1 le respecte, l'option 2 non.

---

## 3.11 — Écran 11 · Rapport de vérification

**Ancre** : `#/report` — **Route** : `/rapport/:uuid` — **Page** : `ReportPage`
**État** : `verificationProvider(uuid)` — **Origine** : `web/compte-user/rapport.html`

### Ce qui est à l'écran, dans l'ordre

Barre supérieure avec retour, titre **Rapport**, action de partage à droite.
Pas de barre basse.

1. **Bloc de verdict** — carte centrée bordée de la couleur du verdict : rond de
   62 teinté, icône du verdict, pastille de verdict pleine, titre de l'analyse,
   puis un bloc étroit **Indice de fiabilité** avec son pourcentage et une barre
   **de la couleur du verdict**. En pied : date, type, et la référence en
   monospace.
2. **📥 Votre contenu soumis** — le contenu, en citation (filet gauche,
   italique).
3. **🛡️ Hate Speech Indicator** — carte verte : titre, pastille `HSI <n> %`,
   puis *« Aucun signal de rhétorique hostile, de ciblage discriminatoire ou de
   langage déshumanisant envers un groupe protégé. »* et une seconde ligne :
   *« 🔒 Privacy Protected — zéro donnée sur l'auteur, analyse sans état. Le
   contenu est jugé, jamais la personne. »*
4. **💡 Pourquoi ce résultat ?** — deux paragraphes de prose, puis un bloc de
   code sombre reprenant le verdict, la confiance, les signaux et l'indice de
   haine sous forme structurée.
5. **🤖 Moteurs d'analyse mobilisés** — une ligne par moteur : nom, score en
   pourcentage, barre fine.
6. **🔍 Sources et preuves consultées** — une ligne par source : icône verte
   `check_circle` ou orange `error`, nom, domaine.
7. **ℹ️ Avertissement sur l'usage de l'IA** — carte grise : *« Ce rapport est
   produit par un orchestrateur croisant recherche web et bases de
   fact-checking. En cas de doute majeur, demandez une contre-expertise
   humaine. »*
8. **Contestation** — *« Ce verdict ne vous semble pas correct ? »* + bouton
   encre **Demander un expert**, qui ouvre une feuille inférieure avec une zone
   de texte (20 caractères minimum) et un bouton **Envoyer la demande**.
9. **Utilité** — *« Ce rapport vous a-t-il été utile ? »* + deux boutons
   « 👍 Oui » / « 👎 Pas vraiment ».
10. **📣 Diffuser la vérité** — bouton vert WhatsApp **Partager sur WhatsApp**,
    puis bouton fantôme **Autres options de partage** qui ouvre la feuille de
    partage (WhatsApp · Facebook · Copier le lien · **Image 9:16**).

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (partage)
 └ ListView
    ├ VfVerdictHero
    │   └ VfConfidenceGauge
    ├ VfQuoteCard
    ├ VfHsiCard
    ├ VfExplainCard (+ VfJsonBlock)
    ├ VfModelBars
    ├ VfSourceList
    └ VfShareActions
```

### Appels réseau

**`GET /api/v1/verifications/{uuid}`** — le même endpoint que l'écran 10.
`data.rapport` porte le contenu de cette fiche ; il est `null` tant qu'aucun
verdict n'a été rendu, auquel cas on renvoie vers le suivi.

**`POST /api/v1/verifications/{uuid}/contest`** — corps : `detail`
(**obligatoire**, 10 à 1000 caractères) et `motif?`.

Le contrat tranche ce que fait réellement ce bouton (arbitrage n° 71), et c'est
important : **la contestation est un signalement, pas un changement d'état.**

> Laisser un citoyen basculer son propre rapport en « arbitrage » offrirait à
> quiconque un bouton pour effacer un verdict qui le dérange.

La demande atterrit dans la file de modération, **le rapport ne bouge pas**, et
l'appel est **idempotent** — dix appels ne créent qu'une demande, ce qui le rend
rejouable depuis la file hors ligne. `detail` est obligatoire ici alors qu'il est
facultatif sur le forum : « ce verdict est faux » sans un mot d'explication ne
donne à la rédaction rien sur quoi travailler.

> Le simulateur exige 20 caractères ; **le contrat en exige 10**. Aligner sur 10
> et laisser le serveur arbitrer — un formulaire ne doit pas exiger plus que ce
> qui sera accepté.

**`POST /api/v1/verifications/{uuid}/feedback` — cet appel n'existe pas.**
Voir ci-dessous.

Le simulateur porte des sources et des moteurs **d'illustration**. Ils ne se
transposent pas : ce sont des données, et les figer reviendrait à publier des
preuves qui n'ont pas été consultées.

À noter : le contrat n'expose **jamais** le détail des exécutions de moteurs —
coûts, clés employées, réponses brutes des fournisseurs — parce que *« publier
quel moteur a répondu quoi revient à publier notre grille d'achat »*
(arbitrage n° 74). Le bloc « Moteurs d'analyse mobilisés » affiche ce que
`data.rapport` en dit, et le client n'en déduit rien de plus.

### ⚠ « Ce rapport vous a-t-il été utile ? » n'est pas servi

`POST /verifications/{uuid}/feedback` **n'a aucun endpoint**. Le contrat (§11) et
l'arbitrage n° 73 expliquent : la question n'a **aucun stockage**, et elle n'est
transposée sur aucun écran web. L'implémenter aurait exigé de créer une table et
un service, *« c'est-à-dire de concevoir une fonctionnalité plutôt que d'en
transposer une »*.

**Ce que l'application fait : elle masque le bloc.** Pas de boutons inertes, pas
de « merci » sans destinataire — un remerciement pour un avis qui n'est stocké
nulle part est un mensonge poli, et c'est pire qu'une question non posée. Le bloc
est écrit derrière le même drapeau de fonctionnalité que l'écran 08, prêt à être
levé quand la table existera.

### Ce que l'écran doit savoir afficher, et que le simulateur ne montre pas

Le simulateur ne montre qu'un rapport abouti. Le back en produit trois autres
formes, toutes prévues par l'arbitrage n° 20, et l'écran doit les couvrir :

| Cas | Ce qui change à l'écran |
|---|---|
| **Arbitrage humain** (moteurs contradictoires) | Le rapport n'est pas publié mais reste consultable par son auteur ; **les deux thèses sont affichées**. Pas de verdict unique en tête. |
| **Non vérifiable** (moins de deux moteurs exploitables) | Verdict « non vérifiable », bandeau d'analyse partielle, mention du **remboursement automatique**. |
| **Synthèse rédigée absente** | Repli sur la synthèse composée par le serveur. Le bloc « Pourquoi ce résultat ? » ne disparaît jamais. |

Et une règle de couleur qui a déjà coûté une correction côté web (arbitrage
n° 32) : **la barre de fiabilité prend la couleur du verdict.** Une barre verte
sous un « non vérifiable » dit « tout va bien » là où il n'y a aucune assurance ;
sous un « faux », elle félicite le lecteur d'avoir trouvé une intox.

### Chargement, vide, erreur

- **Chargement** : squelettes par bloc.
- **Rapport introuvable** (lien profond vers une analyse effacée) : écran de
  repli qui l'explique, avec retour vers l'historique.
- **Analyse non terminée** : redirection vers l'écran 10, jamais un rapport à
  moitié rempli.
- **Contestation** : moins de 10 caractères → refus local avec le compte exact ;
  au-delà de 1000, le champ borne la saisie. Succès → feuille fermée + toast
  *« Demande transmise à la rédaction. »*
  Le simulateur promet « réponse sous 48 h » : **ne conserver ce délai que si
  l'équipe s'y engage**, sinon c'est une promesse que l'écran ne peut pas tenir.
- **`404 introuvable`** ne veut pas dire « supprimé » : le contrat répond 404 sur
  ce qui n'appartient pas au demandeur, pour qu'un uuid deviné ne renseigne sur
  rien. L'écran de repli dit donc « ce rapport n'est pas disponible », jamais
  « ce rapport a été supprimé ».

### Hors ligne

Le rapport est **mis en cache** : c'est le document que l'on rouvre pour le
montrer à quelqu'un, souvent sans réseau. Le partage d'image fonctionne hors
ligne (rendu local) ; le PDF non, il vient du serveur.

### Partage

Deux chemins distincts, et il ne faut pas les confondre :

- **Image 9:16** — rendue **côté client** depuis un `RepaintBoundary`, puis
  passée à `share_plus`. La carte doit rester lisible hors application : verdict,
  affirmation, indice de fiabilité, référence, marque. C'est ce que les gens
  reposteront ;
- **PDF** — il n'y en a pas sur cet écran ; le simulateur n'en propose pas ici.
  (Le PDF du certificat, lui, est déclaré à l'écran 18 — et il **n'est pas
  servi** : voir ce chapitre.)

### Accessibilité

Le verdict est annoncé en premier, en toutes lettres. Le bloc de code structuré
est marqué comme contenu technique et peut être sauté. Les barres de moteurs
portent leur valeur en texte.

### Point de vigilance (repris du simulateur)

Image 9:16 rendue côté client, depuis un `RepaintBoundary` : elle doit rester
lisible hors application, sans dépendre du réseau. Le client ne construit jamais
d'adresse vers un fichier du disque privé — `file_path` n'est pas exposé, et rien
ne se sert autrement que par route signée.

---

## 3.12 — Écran 12 · Mes vérifications

**Ancre** : `#/history` — **Route** : `/mes-verifications`
**Page** : `VerificationHistoryPage`
**État** : `verificationListProvider` (PagedNotifier)
**Origine** : `web/compte-user/mes-verifications.html`

### Ce qui est à l'écran

Barre supérieure : titre **Mes vérifications**, retour si empilé, pastille de
points. Barre basse, onglet Accueil.

1. **Quatre KPI** : Total · **Intox démasquées** (en rouge) · **Confirmées
   fiables** (en vert) · Unités restantes.
2. Une ligne : **champ de recherche** (« Rechercher une vérification… ») et un
   bouton carré `tune` qui ouvre la **feuille de filtres**.
3. **Pastilles de filtre** défilantes : `Tous · FAUX · TROMPEUR · VRAI`.
4. Le décompte : « <n> résultat(s) ».
5. La liste de tuiles de vérification : pastille ronde teintée de la couleur du
   verdict avec son icône, titre sur deux lignes maximum, ligne secondaire
   « <type> · <date> · <n> % de confiance », et la pastille de verdict à droite.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar
 └ CustomScrollView
    ├ VfKpiGrid
    ├ VfSearchField (débounce 350 ms)
    ├ VfFilterChips + VfFilterSheet
    └ SliverList
       └ VfVerificationTile
```

### Appels réseau

**`GET /api/v1/me/verifications`** — filtres `verdict` et `q`, plus `limit` et
`cursor`.

> **`page` n'existe pas.** Le panneau du simulateur écrit `?page=` ; c'est une
> abréviation de son auteur, et le contrat pagine **par curseur**
> (arbitrage n° 68). Sur un fil qui bouge, le décalage ment : trois analyses
> lancées entre deux pages décalent tout, et l'élément sauté peut être
> précisément le rapport qu'on cherchait.

Conséquences concrètes sur cet écran :

- `infinite_scroll_pagination` s'emploie **en mode curseur** : on renvoie
  `meta.page.next_cursor` tel quel, sans jamais le lire ;
- fin de liste = `next_cursor: null` **et** `has_more: false` ;
- **il n'y a pas de total.** Le décompte « <n> résultat(s) » compte les éléments
  **chargés** — le libellé doit le supporter honnêtement plutôt que de promettre
  un total que l'API ne donne pas ;
- `limit` vaut 20 par défaut et plafonne à 50 ; une valeur plus grande est
  ramenée sans erreur.

La recherche est débouncée à **350 ms**, exactement comme côté web.

### Trois règles venues de l'arbitrage n° 31

Elles ne se voient pas dans le simulateur et elles changent l'écran :

1. **L'historique montre aussi ce qui n'est pas fini.** Les analyses en cours
   apparaissent, avec leur **état** à la place du verdict — un tiret laisserait
   croire à un échec. Le filtre mélange donc verdicts et état « en cours », et
   c'est le bon point de vue : on vient ici chercher « où en est ma demande ».
   Toucher une ligne mène au rapport s'il existe, au suivi sinon.
2. **« Intox démasquées » réunit faux et trompeur.** Du point de vue de
   quelqu'un qui a failli partager, les deux sont des pièges.
3. **Les indicateurs portent sur tout l'historique**, jamais sur la page filtrée.
   Ce sont des repères sur son activité ; les faire suivre les filtres leur
   ferait changer de sens.

### Chargement, vide, erreur

**Deux états vides distincts**, et c'est une exigence, pas une élégance :

- *aucun résultat* — icône `search_off`, « Aucun résultat », *« Modifiez la
  recherche ou retirez les filtres. »*, action : réinitialiser les filtres ;
- *rien encore fait* — « Votre laboratoire est encore vide », action : lancer une
  première vérification.

Chargement : squelettes de tuiles. Erreur de page suivante : une ligne
« Réessayer » en pied de liste, jamais un écran d'erreur qui efface ce qui était
déjà affiché.

Les libellés de verdict viennent du serveur : l'API fait voyager la **valeur
technique et le libellé français ensemble** (`verdict` + `verdict_libelle`). Le
client affiche le libellé et branche sur la valeur — **il ne fabrique jamais le
libellé lui-même**. C'est ce qui fait qu'un verdict ajouté côté serveur n'écroule
plus l'écran.

### Hors ligne

Liste servie depuis Drift, avec bandeau d'horodatage. La recherche et les filtres
s'appliquent **localement sur le cache** — dégradé assumé, à dire à l'écran.

### Accessibilité

Le champ de recherche annonce le nombre de résultats après chaque frappe
stabilisée. Les pastilles de filtre exposent leur état pressé.

### Point de vigilance (repris du simulateur)

Pagination serveur obligatoire. Les filtres passent par un **bottom sheet** sur
mobile, **jamais par des menus déroulants alignés**. Liste en cache Drift pour la
lecture hors ligne.

---

## 3.13 — Écran 13 · Répertoire d'outils

**Ancre** : `#/tools` — **Route** : `/outils` — **Page** : `ToolsDirectoryPage`
**État** : `toolsProvider` (`FutureProvider`, cache 24 h)
**Origine** : `web/compte-user/outils.html`

### Ce qui est à l'écran

Barre supérieure, titre **Outils OSINT**, retour si empilé. Barre basse, onglet
Accueil.

Un en-tête : sur-titre `OUTILS · OSINT · FACT-CHECKING`, titre **« Répertoire des
outils »**, phrase *« Les instruments que nos vérificateurs utilisent au
quotidien, expliqués simplement. »*

Des pastilles de catégorie déduites **du contenu réel** — « Tous » plus les
catégories effectivement présentes. Puis une carte par outil : pastille de
catégorie à gauche, pastille `Gratuit` (verte) ou `Freemium` à droite, nom en
`h-sm`, description, bouton fantôme pleine largeur **Ouvrir l'outil**.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar
 └ Column
    ├ VfPageHeader
    ├ VfCategoryChips (défilement horizontal)
    └ ListView
       └ VfToolCard
          └ VfOutlinedButton (url_launcher)
```

### Appels réseau

**`GET /api/v1/tools`** — et **rien d'autre**.

Deux précisions du contrat qui changent l'implémentation :

1. **Cette liste n'est pas paginée.** Elle fait partie des quatre listes
   « fermées, tenues à la main par l'équipe » — une vingtaine d'outils. *« Y
   mettre un curseur compliquerait le client pour une pagination qui ne se
   déclencherait jamais. »* Ne pas écrire de pagination ici.
2. **Le paramètre `category` n'apparaît pas au contrat.** Le simulateur écrit
   `?category=` ; l'endpoint documenté est `GET /tools` nu. **Le filtre par
   pastilles se fait donc côté client, sur la liste entière** — ce qui est
   cohérent avec une liste fermée et non paginée, et ce qui est de toute façon
   nécessaire pour que le filtre marche hors ligne. Réserve C.5 de la section 9.

Durée de vie du cache : **24 heures**, fixée par le contrat (§7.1). C'est l'un
des rares écrans où un cache long est légitime — un répertoire d'outils ne change
pas dans la journée.

### Une règle qui vient du back

Les pastilles de catégorie sont **lues du contenu**, jamais codées en dur. C'est
l'arbitrage n° 55, écrit pour le Hub mais valable ici : proposer une catégorie
vide mène à une liste vide, et une liste vide derrière un filtre passe pour un
bug.

Par ailleurs, l'arbitrage n° 64 rappelle que les quinze outils du back ont été
**extraits** du prototype, avec leurs adresses — *« quinze URL recopiées à la
main, c'est quinze occasions d'envoyer quelqu'un ailleurs que là où on le
croit »*. Le client ne recopie donc aucune adresse : il ouvre celle que l'API
renvoie.

### Chargement, vide, erreur

Chargement : cartes squelettes. Vide : « Aucun outil dans cette catégorie » avec
retour à « Tous ». Erreur : cache si disponible, sinon message et bouton
« Réessayer ».

### Hors ligne

Consultable intégralement depuis le cache. L'ouverture d'un outil, elle, demande
le réseau : le dire plutôt que d'ouvrir un onglet sur une page d'erreur.

### Accessibilité

Le bouton « Ouvrir l'outil » annonce le nom de l'outil et le fait qu'il ouvre
une page externe.

### Point de vigilance (repris du simulateur)

Ouverture via `url_launcher` en **onglet système** (Custom Tabs /
`SFSafariViewController`), **pas dans une WebView maison** : moins de surface
d'attaque, session isolée. C'est le même raisonnement que pour la candidature à
une offre (écran 23) — le citoyen doit voir le vrai domaine dans la barre
d'adresse.
