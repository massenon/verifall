# 3. Les 30 écrans — chapitre 4 : Académie

Six écrans, module `lms`. C'est le chapitre qui doit fonctionner **hors ligne** :
le parcours M3 (section 8) en fait sa raison d'être. Quelqu'un qui apprend le
fait dans un transport, dans une cour, sans réseau — et une leçon qui exige la
connexion est une leçon qui ne se suit pas.

Quatre arbitrages du back gouvernent ce chapitre : n° 40 (ce que le schéma tient
lui-même), n° 42 (le contenu est extrait, pas retapé), n° 61 (pas d'avis, pas de
note), n° 67 (les chiffres sont comptés ou retirés).

---

## 3.14 — Écran 14 · Catalogue de cours

**Ancre** : `#/academy` — **Route** : `/catalogue` — **Page** : `CourseCatalogPage`
**État** : `catalogProvider` · `enrollmentProvider`
**Origine** : `web/compte-user/catalogue.html`

### Ce qui est à l'écran

Barre supérieure : titre **Académie**, retour si empilé, pastille de points.
Barre basse, onglet **Apprendre** actif.

1. **Carte « Votre progression »** — sur-titre, puis « <n> terminé(s) · <m> en
   cours » et, à droite, une pastille violette avec le total d'XP ; en dessous,
   une barre de progression sur l'ensemble du catalogue.
2. **Carte « Continuer »** (seulement s'il existe un cours entamé non terminé) —
   bordure verte, titre du cours, « <n> % · prochaine leçon : <titre> », barre,
   bouton primaire **Reprendre le cours**.
3. **Pastilles de niveau** : `Tous · Débutant · Intermédiaire · Avancé`.
4. **Cartes de cours**, une par cours : couverture (dégradé ou image) portant le
   nom de la catégorie, puis une ligne de trois pastilles — niveau, `Gratuit` ou
   « <n> unités », et la note à droite —, le titre sur deux lignes, une ligne
   « <durée> · <n> leçons · <m> inscrits », et, si l'on est inscrit, une barre de
   progression avec son pourcentage.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar
 └ CustomScrollView
    ├ VfProgressSummary
    ├ VfResumeCard (reprise intelligente)
    ├ VfLevelChips
    └ SliverList
       └ VfCourseCard
          ├ VfCourseCover
          └ VfProgressBar
```

### Appels réseau

- **`GET /api/v1/courses`** — le catalogue. **Paginé par curseur** (`limit`,
  `cursor`). Le paramètre `level` du simulateur **n'apparaît pas au contrat** :
  le filtre par niveau se fait côté client, ce qui est de toute façon nécessaire
  pour qu'il marche hors ligne. Réserve C.5 de la section 9.
- **`GET /api/v1/me/enrollments`** — mes inscriptions.
- **`POST /api/v1/courses/{slug}/enroll`** — inscription. **Idempotent par
  service** : c'est ce qui la rend rejouable depuis la file hors ligne sans
  débiter deux fois.
- **`GET /api/v1/me/courses/resume`** — la carte « Continuer ». **Rend
  `data: null`** quand il n'y a rien à reprendre ; ce n'est pas une erreur, c'est
  l'état vide.

L'identifiant d'un cours est son **`slug`**, jamais un entier.

### Ce que le back impose et que le simulateur ne peut pas montrer

- **Pas de note fabriquée** (arbitrage n° 61). Le simulateur affiche « ★ 4,8 » et
  « 1 245 inscrits » parce qu'il lui faut des données ; en base, `rating` et
  `students_count` sont semés à zéro. La carte affiche donc **« Nouveau »** tant
  qu'aucun avis n'existe, et l'étoile n'apparaît que le jour où quelqu'un a noté.
  L'écran doit gérer les deux formes.
- **Le « cours offert du mois » est une date de fin**, pas un drapeau
  (arbitrage n° 40) : un drapeau qu'il faut penser à retirer reste allumé.
  L'affichage de la gratuité temporaire suit donc la date renvoyée par l'API.
- **La section « Recommandé pour vous »** existe côté web (arbitrage n° 41) mais
  **pas dans le simulateur mobile**. Ne pas l'ajouter : ce serait inventer un
  écran.

### Chargement, vide, erreur

- Chargement : cartes squelettes avec couverture en dégradé de chargement.
- Vide après filtre : « Aucun cours à ce niveau » + retour à « Tous ».
- Catalogue entièrement vide : cas réel tant que la rédaction n'a rien publié —
  le dire, et proposer le Hub ou la vérification.
- Erreur : cache Drift si disponible.

### Hors ligne

**Le catalogue est en cache Drift, une heure**, durée fixée par le contrat
(§7.1). L'inscription est mise en file si le réseau manque : elle est
**idempotente par service**, donc un rejeu ne débite pas deux fois. L'écran doit
malgré tout dire clairement que le débit n'est pas encore effectué — le solde
affiché ne bouge pas avant la réponse du serveur.

Un refus au rejeu prend la forme d'un **`402 solde_insuffisant`** si le solde a
changé entre-temps : l'écran propose alors la recharge, il n'affiche pas une
erreur.

### Accessibilité

Chaque carte est un unique bouton dont le libellé compose titre, niveau, prix et
progression : « Introduction au fact-checking, débutant, gratuit, 40 % terminé ».
Les trois pastilles seules ne se lisent pas.

### Point de vigilance (repris du simulateur)

Couvertures via `cached_network_image` avec placeholder shimmer. Catalogue en
cache Drift ; inscription mise en file si le réseau manque.

---

## 3.15 — Écran 15 · Fiche de cours

**Ancre** : `#/course` — **Route** : `/cours/:slug` — **Page** : `CourseDetailPage`
**État** : `courseProvider(slug)` · `progressProvider(slug)`
**Origine** : `web/compte-user/cours.html`

### Ce qui est à l'écran

Barre supérieure avec retour, **titre = la catégorie du cours** (« Fact-Checking »,
« OSINT »…). Pas de barre basse.

1. **Couverture** de 140 de haut portant le titre du cours.
2. **En-tête** : trois pastilles (niveau · gratuit ou prix · note et nombre
   d'inscrits), titre en `h-lg`, description.
3. **Ligne formateur** : avatar à initiales, nom, rôle, durée du cours à droite.
4. **Bloc d'action**, selon l'état :
   - non inscrit → bouton primaire **S'inscrire gratuitement** ou **S'inscrire —
     <n> unités** ;
   - inscrit, non terminé → carte « Progression <n> % » + barre + bouton
     **Reprendre** ;
   - terminé avec certificat → même carte + bouton **Voir mon certificat**.
5. **« Ce que vous saurez faire »** — une liste à coches vertes.
6. **« Programme · <n> leçons »** — une liste de menu, une ligne par leçon :
   icône `check_circle` verte si faite, `lock` si verrouillée, `play_circle`
   sinon ; titre ; durée en minutes. Une ligne finale pour le **quiz** : icône
   selon l'état, titre du quiz, et la ligne « <n> min · <seuil> % pour valider ·
   +<n> XP ».

### Arbre de widgets

```
Scaffold
 └ CustomScrollView
    ├ SliverAppBar.large (couverture)
    ├ VfCourseMeta + VfInstructorTile
    ├ VfProgressCard | VfEnrollButton
    ├ VfOutcomeList
    └ VfLessonList
       └ VfLessonTile (faite | verrouillée | ouverte)
```

### Le déverrouillage séquentiel

Le simulateur verrouille une leçon tant que la précédente n'est pas faite, et le
quiz tant que toutes les leçons ne le sont pas. **Cette règle est tranchée par le
serveur** : le champ s'appelle **`ouverte`** dans le contrat, et la table
d'arbitrage (§7.4) désigne le serveur comme autorité — *« un client qui la
recalcule ouvrirait un jour une leçon qui ne devait pas l'être »*. Le client ne
recalcule rien.

Corollaire de la règle du chantier §7 : **un élément verrouillé affiche sa
condition.** Un cadenas nu est une frustration gratuite. La ligne doit dire
pourquoi : « Terminez la leçon précédente », « Terminez les 5 leçons ».

### Appels réseau

- **`GET /api/v1/courses/{slug}`** — la fiche. Elle porte
  **`data.lecons[].ouverte`** et **`data.lecons[].acquise`** : le serveur tranche
  le déverrouillage, l'UI ne fait que le refléter.
  **Le `contenu` d'une leçon n'accompagne jamais cette liste** — sinon c'est le
  corps entier du cours qui partirait à chaque ouverture du catalogue. Un champ
  **absent** signifie ici « non applicable dans ce contexte », à distinguer d'un
  champ présent et nul.
- **`POST /api/v1/courses/{slug}/enroll`**
- **`GET /api/v1/me/courses/{slug}/progress`**

Un cours non publié répond **404, même en connaissant le slug**.

### Ce que le schéma garantit (arbitrage n° 40)

- **Une inscription par personne et par cours** — contrainte en base, pas dans le
  code : un double toucher sur « S'inscrire » ne débite pas deux fois. Le client
  doit tout de même désactiver le bouton pendant l'appel, mais c'est du confort,
  pas la protection.
- **Une complétion par leçon** — relire ne fait pas dépasser 100 %.
- **`paid_units` est figé sur l'inscription** : le prix peut changer, ce qui a
  été déboursé non.

### Chargement, vide, erreur

- Chargement : couverture et blocs en squelette.
- Inscription refusée faute d'unités : le simulateur affiche *« Il vous faut
  <n> unités pour ce cours. »* ; l'écran doit, comme celui de vérification,
  **proposer la recharge** plutôt que de s'arrêter au refus.
- Cours dépublié atteint par lien profond : écran de repli explicite.

### Hors ligne

Fiche lue depuis le cache. Les leçons déjà téléchargées sont ouvrables ; les
autres portent la mention « Disponible avec une connexion ». L'inscription part
en file.

### Accessibilité

L'état de chaque leçon est dans son libellé (« Leçon 3, terminée », « Leçon 4,
verrouillée : terminez la leçon précédente »). Le bouton d'action principal
annonce ce qui va se passer — « Reprendre à la leçon 4 » plutôt que « Reprendre ».

### Point de vigilance (repris du simulateur)

Déverrouillage tranché par le serveur ; une inscription payante passe par la même
route idempotente que le web.

---

## 3.16 — Écran 16 · Leçon

**Ancre** : `#/lesson` — **Route** : `/cours/:slug/lecon/:id` — **Page** : `LessonPage`
**État** : `lessonProvider` · `lessonTimerProvider`
**Origine** : `web/compte-user/lecon.html`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **« Leçon <n>/<N> »**. Pas de barre basse.

1. Une barre de progression fine du cours.
2. Sur-titre « <catégorie> · <n> min », titre de la leçon en `h-lg`.
3. **Lecteur vidéo** — dans le simulateur, un bloc de 150 en dégradé avec une
   icône `play_circle` et la ligne « Vidéo de la leçon · <n> min ». En production,
   c'est `chewie`.
4. **Corps de la leçon** en prose : un chapô, un paragraphe, un sous-titre
   « Les trois questions », une liste à puces, un paragraphe de conclusion.
5. **Encart « À retenir »** — fond vert clair, icône `lightbulb`.
6. **Bouton de validation** — primaire **Marquer comme terminée**, ou fantôme
   désactivé « ✓ Leçon terminée » si c'est déjà fait.
7. **Bouton de suite** — fantôme **Leçon suivante →**, ou **Passer au quiz
   final** sur la dernière leçon. Il est **désactivé tant que la leçon courante
   n'est pas validée**.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (progression)
 └ ListView
    ├ VfLinearProgress
    ├ VfVideoPlayer (chewie)
    ├ VfProseBody (flutter_html)
    ├ VfCalloutCard
    └ VfCompleteButton (conditionné par une durée)
```

### La durée active minimale — et qui en décide

C'est la particularité de cet écran. Le simulateur annonce 45 secondes ; **le
contrat rend la valeur par le serveur** : `meta.secondes_requises`, sur la
réponse de la leçon. Ne pas coder 45.

**Ouvrir une leçon démarre le chronomètre serveur.** Le client envoie ensuite
`secondes` à la validation, et le serveur retient **le plus petit des deux** —
le sien et celui annoncé. La table d'arbitrage (§7.4) l'explique :

> Le client peut annoncer *moins* — l'application met son compteur en pause quand
> elle passe en arrière-plan, et c'est légitime. Il ne peut **jamais annoncer
> plus** : sinon il suffirait d'envoyer « j'ai lu pendant deux heures ».

Implémentation : `lessonTimerProvider` écoute `AppLifecycleState` et ne compte
que sur `resumed`. Le compteur local est donc une **déclaration**, pas la vérité.
Le bouton reste visible mais désactivé, avec un libellé qui dit **pourquoi** —
« Encore 20 s de lecture » — jamais un bouton grisé muet.

### Appels réseau

**`GET /api/v1/courses/{slug}/lessons/{uuid}`** — l'identifiant d'une leçon est
un **uuid**. C'est le seul appel qui porte le `contenu` de la leçon ; il pose
aussi **`meta.secondes_requises`** et **démarre le chronomètre serveur**.

**`POST /api/v1/me/lessons/{uuid}/complete`** — corps : `secondes`.

Le refus est nommé par le contrat : **`422 action_impossible`, avec un message
qui dit combien de secondes sont nécessaires — jamais un échec muet**. L'afficher
tel quel, sans le reformuler.

La validation crédite de l'XP (+20 dans le simulateur). **Le crédit est un fait
serveur** ; le client relit, il n'additionne pas.

Cette écriture est **rejouable par contrainte d'unicité en base** : elle part en
file hors ligne sans risque de double comptage, et sans exiger de clé
d'idempotence.

### Le contenu HTML

Le point de vigilance est net : le HTML de leçon vient de Quill, **purifié côté
serveur avant persistance**, puis rendu avec `flutter_html` sur une **liste
blanche de balises**. Deux règles côté client :

1. Aucune balise hors liste blanche n'est rendue — même venue de notre propre
   API. Le principe « jamais `{!! !!}` sur donnée utilisateur » du back n'a pas
   d'équivalent syntaxique en Dart, il faut donc l'imposer par la configuration
   du rendu.
2. Les liens du corps s'ouvrent en **onglet système**, pas dans une vue interne.

### Chargement, vide, erreur

- Chargement : squelette de prose.
- Leçon verrouillée atteinte par lien profond : retour à la fiche du cours avec
  la raison.
- Échec de validation : toast rouge, la leçon reste non validée. **Ne jamais
  marquer localement une complétion non confirmée** : la progression serait
  fausse au prochain chargement.

### Hors ligne

C'est le cas d'usage central du parcours M3. Le contrat donne au contenu d'une
leçon la durée de vie la plus longue de tout le cache — **jusqu'à la fin du
cours** — précisément pour cela. La validation part en file.

**Un piège à arbitrer avant d'écrire M3** : ouvrir une leçon **démarre le
chronomètre serveur**. Télécharger un cours entier revient donc à appeler toutes
les leçons d'avance, et donc à démarrer tous les chronomètres à la fois. Réserve
C.3 de la section 9.

### Accessibilité

La vidéo porte des sous-titres quand ils existent, et le lecteur reste pilotable
au clavier externe. Le compteur de durée minimale est annoncé une fois, pas à
chaque seconde.

### Point de vigilance (repris du simulateur)

HTML Quill purifié **avant persistance** côté serveur, et rendu ici en **liste
blanche de balises**. Le contrat justifie la double protection : *« la
purification à l'écriture protège de ce qui est en base, pas de ce qui y
entrerait un jour par une autre porte. »*

Durée active minimale rendue par `meta.secondes_requises` ; compteur en pause en
arrière-plan ; le serveur retient le minimum des deux.

---

## 3.17 — Écran 17 · Quiz

**Ancre** : `#/quiz` — **Route** : `/cours/:slug/quiz` — **Page** : `QuizPage`
**État** : `quizControllerProvider` (StateNotifier)
**Origine** : `web/compte-user/quiz.html`

### Ce qui est à l'écran — phase question

Barre supérieure avec retour, titre **« Question <n>/<N> »**.

1. Barre de progression fine.
2. Une ligne : pastille avec le titre du quiz à gauche, pastille orange
   « ⏱ <n>:00 » à droite.
3. L'énoncé en `h-md`.
4. **Quatre options**, chacune une grande ligne de 52 minimum avec une pastille
   ronde portant sa lettre (A, B, C, D) et le texte. États : neutre, sélectionnée
   (bordure et fond verts), **juste** (vert `vtrue`), **fausse** (rouge
   `vfalse`).
5. Après réponse : un **encart XAI** vert clair, icône `psychology`, titre
   « Pourquoi cette réponse » et l'explication ; puis un bouton primaire
   **Question suivante** — ou **Voir mon résultat** à la dernière.

### Ce qui est à l'écran — phase résultat

1. Carte centrée : rond de 70 teinté (vert si réussi, rouge sinon), icône
   `workspace_premium` ou `refresh` ; titre **« Quiz réussi ! »** ou **« Encore
   un effort »** ; ligne « <n> bonnes réponses sur <N> · seuil <p> % » ; le score
   en 46/800 dans la couleur du résultat ; et, si réussi, deux pastilles
   « +<n> XP » et « +5 unités ».
2. Bouton primaire **Continuer** (si réussi) ou **Réessayer le quiz**.
3. Bouton fantôme **Retour au cours**.

Sur une réussite, des **confettis**. Jamais sur un échec — c'est écrit dans le
pack UX et dans le point de vigilance du simulateur.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (question n/N)
 └ Column
    ├ VfLinearProgress + VfTimerChip
    ├ Text.question
    ├ VfQuizOption ×4
    ├ VfXaiCard (après réponse)
    └ VfPrimaryButton
```

### La règle centrale : la correction reste serveur — et elle arrive à la copie

Le simulateur corrige localement, question par question. **Le contrat ne le
permet pas**, et c'est la divergence la plus structurante de ce chapitre.

`GET /courses/{slug}/quiz` rend les questions **sans corrigé**. Le contrat range
`is_correct` et l'explication parmi ce qui n'est **jamais** exposé *avant* la
copie rendue (§9, arbitrage n° 74) — *« un quiz réussi avec un intercepteur de
trafic dévalue tous les certificats. »*

Le corrigé arrive **une seule fois** : dans la réponse à
`POST /me/quiz/{uuid}/attempt`, sous `data.corrige[]`, avec la bonne réponse et
l'explication de chaque question. Le contrat le souligne : *« c'est le seul
moment où elles franchissent le réseau. »*

**Conséquence sur l'écran** : l'encart XAI ne peut pas s'afficher après chaque
réponse. Il n'existe pas d'appel par question. Deux formes possibles, et c'est un
**arbitrage produit** — réserve C.1 de la section 9 :

1. garder une question à la fois, sans marque juste/faux ni XAI pendant le
   parcours, et **dérouler le corrigé sur l'écran de résultat**, question par
   question, avec chaque explication. La pédagogie est préservée, le moment
   change ;
2. renoncer à l'écran de résultat compact au profit d'une correction déroulée.

L'option 1 est la moins éloignée de la maquette : elle conserve l'écran de
question, l'écran de résultat, et déplace l'encart XAI. Elle n'a pas été retenue
d'autorité — elle est proposée, et elle attend une décision.

### Le chronomètre

Il s'appuie sur **`meta.commence_le`**, renvoyé par `GET /courses/{slug}/quiz`,
« l'heure qui fait foi, pas celle du téléphone ». À l'expiration, la copie se
rend vide : le contrat accepte explicitement un tableau de réponses **vide** —
*« c'est la copie de quelqu'un dont le chronomètre est arrivé au bout »* — et
**une question sans réponse compte comme fausse**. Le client n'invente aucun
échec, il envoie ce qu'il a.

### Appels réseau

**`GET /api/v1/courses/{slug}/quiz`** — questions sans corrigé, plus
`meta.commence_le`.

**`POST /api/v1/me/quiz/{uuid}/attempt`** — corps :

```json
{ "reponses": { "<uuid-question>": <id-réponse> } }
```

Les réponses sont **indexées par uuid de question**, pas par position — une
position se décale, un uuid non. L'identifiant d'une réponse est un **entier**,
et c'est **la seule exception** du contrat à la règle « aucun entier » : la table
n'a pas d'uuid, et il n'énumère rien d'utile puisqu'une réponse n'existe que dans
la question qui la porte, servie seulement à qui est inscrit au cours.

C'est **l'un des deux seuls actes qui exigent une `Idempotency-Key`** — l'autre
étant la soumission d'une vérification. Une copie rendue deux fois par une
réponse perdue serait une seconde tentative fantôme.

### Ce que le back ajoute

- **Les tentatives sont volontairement multiples** (arbitrage n° 40) : garder
  l'historique est ce qui permet de dire « vous progressez » plutôt que « vous
  avez échoué ». Le bouton **Réessayer** est donc un vrai parcours, pas une
  faveur.
- **Le certificat est émis** quand toutes les leçons sont faites **et** le quiz
  réussi. Dans le simulateur, la réussite du dernier quiz produit l'identifiant
  du certificat, une notification, et le bouton « Continuer » mène directement au
  certificat. C'est le serveur qui émet ; le client suit.

### Chargement, vide, erreur

- Chargement : squelette d'énoncé et d'options.
- Perte de réseau en cours de quiz : les réponses déjà données sont conservées
  localement, et la copie part en file avec sa clé d'idempotence. Si la reprise
  renvoie `Idempotent-Replay: true`, **ce n'est pas une erreur** : c'est la
  confirmation que la copie était bien arrivée, et l'écran affiche le résultat
  mémorisé.
- **Le résultat d'un quiz ne se lit jamais depuis le cache** (contrat §7.1) :
  c'est l'un des trois écrans qu'on relit précisément parce qu'on attend un
  changement.
- Quiz verrouillé atteint par lien profond : retour à la fiche du cours.

### Hors ligne

Le contrat range la copie de quiz parmi les écritures mises en file. Un quiz peut
donc se passer hors ligne — mais **le résultat n'arrive qu'au retour du réseau**,
puisque la correction est serveur. L'écran affiche « Résultat en attente de
connexion », jamais un score calculé localement.

À noter : le chronomètre s'appuie sur `meta.commence_le`. Une copie commencée
hors ligne et rendue une heure plus tard sera **hors délai**, et le serveur
tranchera. Le dire à l'écran avant de commencer un quiz sans réseau.

### Accessibilité

Chaque option est un bouton dont le libellé compose la lettre et le texte. Après
réponse, l'état juste/faux est annoncé en mots. Le chronomètre est annoncé aux
paliers (5 min, 1 min, 30 s), pas en continu.

### Point de vigilance

Correction serveur, rendue **à la copie** et non question par question ;
chronomètre appuyé sur `meta.commence_le` ; `Idempotency-Key` obligatoire ;
confettis coupés par `MediaQuery.disableAnimations`, **jamais sur un échec**.

---

## 3.18 — Écran 18 · Certificat

**Ancre** : `#/certificate` — **Route** : `/certificat/:certId`
**Page** : `CertificatePage` — **État** : `certificateProvider(certId)`
**Origine** : `web/compte-user/certificat.html` + l'instruction sur le filigrane

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Certificat**, action de partage.

1. **Le certificat** — cadre de 3 px vert forêt, rayon 16, fond blanc, et un
   **filigrane** « VÉRIF'ALL » centré, en Instrument Sans 44, interlettrage
   `.16em`, vert à 6 % d'opacité. Contenu centré : sur-titre « Vérif'All
   Académie », « Certificat de réussite décerné à », **le nom du titulaire** en
   `h-lg`, « pour avoir terminé avec succès », **le titre du cours** en `h-sm`,
   puis un pied séparé d'un filet avec, à gauche, « Délivré le » + la date et, à
   droite, « Identifiant » + le code en monospace.
2. **Encart de vérifiabilité** — icône `verified_user` : *« Vérifiable
   publiquement sur verifall.com/verify-certificate avec l'identifiant
   ci-dessus. »*
3. Bouton primaire **Télécharger le PDF** (icône `picture_as_pdf`).
4. Bouton fantôme **Partager mon certificat**.

### Arbre de widgets

```
Scaffold
 └ Column
    ├ VfCertificateCard
    │   ├ WatermarkPainter (CustomPaint)
    │   └ VfCertMeta (identifiant, date)
    ├ VfVerifyHint
    └ VfPrimaryButton (PDF)
```

### Appels réseau

**`GET /api/v1/me/certificates/{code}`** — l'identifiant est le **`code` public**,
et c'est **l'exception assumée** du contrat à la règle « uuid partout » :

> Son `code` est conçu pour être **lu à voix haute et recopié** (alphabet sans
> O/0, I/1, S/5). C'est lui qu'un recruteur saisit. Un uuid de trente-six
> caractères ne se dicte pas au téléphone.

**`GET /api/v1/me/certificates`** — la liste, **non paginée** (liste fermée).

La route est bornée au titulaire. Le contrat ajoute une précision qui interdit
un raccourci tentant : *« la vérification publique d'un certificat existe sur le
site ; cette route ne doit pas devenir un second oracle où l'on énumère des
codes. »* L'application ne vérifie donc **pas** le certificat d'un tiers.

### ⚠ Le PDF n'est pas servi

**`GET /api/v1/me/certificates/{code}/pdf` n'a aucun endpoint.** Le contrat
(§11) et l'arbitrage n° 73 en donnent la raison : aucune bibliothèque de
génération PDF n'est installée, et *« en ajouter une est une décision de
dépendance qui appartient au porteur du projet, pas à l'agent qui écrit le
contrat. »*

**Ce que l'application fait à la place.** Le bouton « Télécharger le PDF » est
**retiré** — un bouton qui échoue systématiquement est pire que son absence. Deux
chemins restent, et ils suffisent :

1. **le partage d'image**, rendu côté client depuis un `RepaintBoundary`, comme
   pour le rapport (écran 11). C'est ce qu'on envoie sur WhatsApp, et c'est le
   geste réel ;
2. **le renvoi vers le web** : la page publique de vérification d'un certificat
   existe déjà côté Laravel, et le code s'y saisit. L'encart de vérifiabilité de
   l'écran porte déjà cette adresse — c'est donc une dégradation **honnête**, pas
   un contournement.

Le bouton est écrit derrière un drapeau de fonctionnalité, comme l'écran 08 et le
bloc d'utilité de l'écran 11. Le jour où le serveur génère le PDF, ce sera un
**ajout compatible v1**, et le client ne le composera jamais lui-même — un
certificat composé côté client serait falsifiable par quiconque décompile
l'application.

### La règle qui ne se négocie pas

**Le nom du titulaire et le titre du cours sont figés** au moment de
l'émission (arbitrage n° 40, colonnes `holder_name` et `course_title`). Un
diplôme atteste de ce qui a été suivi **ce jour-là** : il ne doit ni mentir après
un renommage du cours, ni redevenir nominatif après un effacement RGPD. L'écran
affiche donc ce que porte le certificat, **pas** le nom courant du compte.

Le filigrane à l'écran est un `CustomPaint`, pas une image — c'est le point de
vigilance du simulateur, et cela évite une ressource de plus à charger.

### Le code du certificat

Alphabet **sans O/0, I/1, S/5**, tirage par générateur cryptographique (règles du
chantier §5). Le client n'en génère aucun : il l'affiche en monospace, avec une
action de copie, et il l'épelle au lecteur d'écran.

### Chargement, vide, erreur

- Chargement : cadre en squelette.
- Certificat introuvable : `404 introuvable`. Le mot du prototype web est
  *« Certificat introuvable »* (arbitrage n° 65). **On n'écrit jamais « faux »** —
  un code mal recopié n'est pas une fraude, et l'accusation serait injuste comme
  inexacte. Et le 404 du contrat couvre aussi « pas à vous » : raison de plus
  pour ne rien affirmer.

### Hors ligne

Le certificat lui-même est en cache et **doit** l'être : c'est ce qu'on montre à
un recruteur, souvent hors ligne. Le PDF, non.

### Accessibilité

Le filigrane est purement décoratif et masqué au lecteur d'écran. Le certificat
est lu comme un texte suivi : titulaire, cours, date, identifiant — l'identifiant
épelé caractère par caractère.

### Point de vigilance

Filigrane en `CustomPaint`, jamais une image. PDF absent en v1 : bouton retiré,
partage d'image et renvoi vers la page publique à la place.

---

## 3.19 — Écran 19 · Live Classes

**Ancre** : `#/liveclasses` — **Route** : `/live-classes` — **Page** : `LiveClassesPage`
**État** : `liveClassesProvider(tab)`
**Origine** : `web/compte-user/live-classes.html`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Live Classes**. Barre basse, onglet
Apprendre.

Trois pastilles-onglets : **À venir · En direct · Rediffusions**. Puis, pour
chaque séance, une carte : pastille verte du thème à gauche ; sur l'onglet « en
direct », une pastille rouge **● EN DIRECT** à droite ; titre en `h-sm` ;
« <intervenant> · <quand> » ; et un bouton pleine largeur dont le libellé change
avec l'onglet :

| Onglet | Bouton | Style |
|---|---|---|
| À venir | **Me le rappeler** | primaire |
| En direct | **Rejoindre maintenant** | primaire |
| Rediffusions | **Voir la rediffusion** | fantôme |

Le libellé qui change n'est pas cosmétique : c'est l'application directe de
« un bouton dit ce qui va se passer ».

### Arbre de widgets

```
DefaultTabController
 └ Scaffold
    ├ VfAppBar + VfSegmentedTabs
    └ TabBarView
       └ ListView
          └ VfLiveClassCard
             └ VfJoinButton
```

### Appels réseau

**`GET /api/v1/live-classes`** — **non paginée** (liste fermée : quelques séances
par mois). Le paramètre `state` du simulateur n'apparaît pas au contrat : les
trois onglets se répartissent **côté client** à partir de ce que la liste rend.

Un champ décide de l'action : **`data.lien_visio` est `null` tant que la séance
n'est pas joignable** — le contrat refuse de publier trois semaines à l'avance un
lien qui circulerait, *« et l'animateur découvre une salle pleine d'inconnus »*.
Le bouton « Rejoindre maintenant » n'est donc actif que si le lien est présent.

**`POST /api/v1/live-classes/{uuid}/remind`** — enregistre l'intention
d'assister, `201`. **Séance passée : `422 action_impossible`.** L'écriture est
protégée par une **contrainte d'unicité en base**, donc rejouable depuis la file
hors ligne.

### Le rappel J-1 — enregistré, mais pas envoyé

Le point de vigilance du simulateur annonce une notification planifiée côté
Laravel, et le raisonnement reste juste : *« elle doit survivre à une
réinstallation »*.

**Mais elle ne part pas en v1.** Le contrat le dit franchement (§11, arbitrage
n° 73) : `remind` enregistre l'intention — la table existe depuis le sprint LMS,
avec son unicité — mais **n'envoie pas** le rappel, faute d'infrastructure de
notifications. La réserve est explicitement partielle et assumée :

> L'inscription est durable : le jour où le rappel partira, il aura sa liste, et
> personne n'aura à redemander aux gens de se réinscrire.

**Conséquence sur le libellé.** Le simulateur confirme par *« Rappel programmé —
vous recevrez une notification. »* C'est une promesse que la v1 ne tient pas.
Formulation honnête : **« C'est noté — retrouvez la séance dans Live Classes. »**
Le bouton peut garder son libellé « Me le rappeler » : l'intention est bien
enregistrée. C'est la confirmation qui doit dire la vérité. Réserve C.2 de la
section 9.

### Chargement, vide, erreur

Trois états vides distincts, un par onglet, et chacun dit autre chose :

- *À venir* : « Aucune séance programmée » + « Les prochaines dates arrivent
  bientôt. »
- *En direct* : « Rien en direct pour l'instant » + un renvoi vers l'onglet
  « À venir ».
- *Rediffusions* : « Aucune rediffusion disponible ».

Un état vide unique et générique sur les trois onglets ferait croire à une panne.

### Hors ligne

Les listes sont en cache. « Me le rappeler » part en file ; « Rejoindre » exige
le réseau et le dit.

### Accessibilité

La pastille « EN DIRECT » est doublée d'un mot dans le libellé de la carte. Les
trois onglets exposent leur position (« onglet 2 sur 3 »).

### Point de vigilance

Rappel **enregistré** côté serveur, jamais en notification locale — et pas encore
envoyé en v1. « Rejoindre » ouvre `data.lien_visio` en **onglet système**, et le
bouton reste inactif tant que ce champ est `null`.
