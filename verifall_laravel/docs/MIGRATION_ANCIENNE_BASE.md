# Reprise de l'ancienne base — `massenon_verifalltest.sql`

*Analyse du 3 septembre 2026, sur le fichier réel : 32 Mo, 114 950 lignes,
68 tables.*

> **À lire avant d'exécuter quoi que ce soit.** L'import ne casse rien tant qu'on
> l'a compris ; c'est de l'avoir lancé sans le lire que viennent les dégâts.

---

## 1. La bonne nouvelle, d'abord

**Les mots de passe sont portables tels quels.** L'ancienne base hache en
`$2y$12$` — bcrypt, coût 12. La nouvelle application n'a pas de
`config/hashing.php`, donc emploie le pilote Laravel par défaut, **bcrypt**, avec
`BCRYPT_ROUNDS=12`.

Conséquence pratique : **personne n'a à réinitialiser son mot de passe.** Les
133 comptes se reconnectent avec ce qu'ils connaissent déjà. C'est le point qui
décide de tout le reste — sans lui, une reprise de comptes est une opération de
communication autant que technique.

Vérifiable en une ligne :

```
php artisan verifall:importer-ancienne-base --essai
```

La commande refuse de continuer si elle rencontre une empreinte qui n'est pas du
bcrypt.

---

## 2. Ce qu'il y a réellement dans l'ancienne base

| Table ancienne | Lignes | Devient |
|---|---:|---|
| `users` | 133 | `users` |
| `signalements` | 524 | `verifications` |
| `analysis_reports` | 1 720 | `analysis_reports` — **agrégées**, voir §4 |
| `unit_transactions` | 286 | `unit_logs` |
| `model_has_roles` | 145 | rôles spatie |
| `otps` | 24 | *rien* — codes périmés |
| `course_user`, `lesson_user`, `quiz_attempts` | 29 / 7 / 13 | Académie, hors périmètre §7 |
| `verified_facts` | 7 | `verified_facts` |

Les trois rôles anciens sont `Admin`, `Mentor`, `User`. Le nouveau produit en
compte quatre : `Admin`, `Mentor`, `Modérateur`, `Citoyen`. **`User` devient
`Citoyen`** ; `Modérateur` n'existait pas et ne reçoit personne.

---

## 3. Les six risques réels, et ce qui les neutralise

C'est la partie qui répond à « sans casser notre nouvelle base ».

### 3.1 Collision d'identifiants — **neutralisée par construction**

L'ancienne base a un `users.id = 1`, la nouvelle aussi. **Aucun identifiant
ancien n'est repris.** Chaque compte est créé avec un nouvel auto-incrément, et
la commande tient en mémoire une table de correspondance
`ancien_id → nouvel_id` qui sert à recâbler tout le reste (signalements,
transactions, assignations).

Écrire les anciens `id` aurait été plus simple et aurait détruit les comptes de
démonstration existants.

### 3.2 Collision d'adresses — **le compte existant gagne**

Si `aicha@example.com` existe des deux côtés, l'ancien n'écrase pas le nouveau.
La commande le **saute** et le signale. Écraser reviendrait à remplacer un mot de
passe qu'une personne emploie aujourd'hui par un mot de passe qu'elle employait
il y a six mois.

### 3.3 `otp_verified_at` — **le piège qui verrouillerait tout le monde dehors**

La nouvelle application refuse la connexion tant que `otp_verified_at` est nul
(`User::estActive()`). L'ancienne base n'a pas cette colonne : elle a
`email_verified_at`.

**Sans traitement, les 133 comptes importés seraient inutilisables** — mot de
passe correct, refus systématique, et le message ne dirait même pas pourquoi.

La commande copie donc `email_verified_at` dans les deux colonnes. Un compte
jamais vérifié dans l'ancienne base reste inactif dans la nouvelle : c'est
l'état correct, pas un oubli.

### 3.4 Le solde d'unités — **l'invariant du journal**

`users.remaining_units` porte un solde. La nouvelle application tient un journal
comptable (`unit_logs`) dont **la somme doit égaler le solde** : c'est
l'invariant que `EconomyService` maintient, et l'écart est le signal qu'une
écriture lui a échappé.

Importer un solde sans écrire de ligne le casserait dès le premier compte. La
commande écrit donc, pour chaque compte au solde non nul, **une ligne de report**
au motif `admin_adjustment`, libellée « Report de l'ancienne plateforme ». Même
traitement pour `points` → `xp` et `xp_logs`.

Les 286 `unit_transactions` anciennes sont reprises **en plus**, avec leur date,
mais elles ne servent qu'à l'historique : c'est la ligne de report qui fait
tomber la somme juste.

### 3.5 Les statuts — **aucune valeur ancienne n'est écrite telle quelle**

L'ancien `signalements.status` est un enum de huit valeurs
(`pending`, `processing`, `analyzing`, `investigating`, `verified_true`,
`verified_false`, `failed`, `debunked`) ; le nouveau `StatutVerification` en
compte six, différentes.

Écrire la valeur ancienne dans la colonne nouvelle passerait sans erreur — la
colonne est une chaîne — et **casserait le cast en enum à la première
lecture**. La table de correspondance est explicite dans la commande, et toute
valeur inconnue arrête l'import au lieu de deviner.

Même traitement pour `content_type` → `type`.

### 3.6 Les clés étrangères — **recâblées, jamais copiées**

`signalements.created_by`, `assigned_to`, `updated_by` pointent vers d'anciens
`users.id`. Copiés tels quels, ils désigneraient n'importe qui dans la nouvelle
base. Tous passent par la table de correspondance ; un identifiant introuvable
devient `null` et le cas est compté au rapport.

---

## 4. Les 1 720 rapports d'analyse deviennent 524

L'ancien `analysis_reports` porte **une ligne par OUTIL** : `tool_name`,
`raw_response`, `confidence_score`. Le nouveau porte **une ligne par
vérification**, avec un verdict, un résumé et un tableau `scores`.

L'agrégation est donc :

| Nouveau champ | Source |
|---|---|
| `verdict` | `signalements.verdict` |
| `confidence` | `signalements.confidence_score` |
| `summary` | `signalements.ai_explanation` |
| `scores` | les anciennes lignes, indexées `tool_name => confidence_score` |
| `hate_speech_*` | `signalements.hate_speech_*` |

Rien n'est perdu : les réponses brutes par outil se retrouvent dans `scores`.

---

## 5. Ce qui ne sera PAS repris, et pourquoi

Le dire est plus utile que de le laisser découvrir.

| Donnée ancienne | Raison |
|---|---|
| `country`, `address`, `avatar` | Aucune colonne équivalente. Le nouveau profil a `city` et `bio`, qui ne veulent pas dire la même chose. Inventer une correspondance produirait des adresses fausses. |
| `google_id`, `facebook_id`, `provider` | La nouvelle authentification sociale passe par Google OAuth avec son propre flux. Recopier un identifiant Facebook créerait une liaison vers un fournisseur que le produit n'emploie plus. |
| `otps` (24 lignes) | Des codes à usage unique expirés. Les importer serait au mieux inutile, au pire une porte. |
| `two_factor_enabled` | Un booléen sans secret. La nouvelle 2FA exige un secret TOTP chiffré ; le drapeau seul verrouillerait le compte hors de son back-office. |
| `notify_alerts`, `notify_news` | Repris, mais **traduits** en `notification_preferences` — voir la commande. |
| `sessions`, `cache`, `jobs`, `failed_jobs` | État d'exécution d'une application qui ne tourne plus. |

---

## 6. Comment l'exécuter

```bash
# 1. Lecture seule : compte, signale les collisions, n'écrit RIEN.
php artisan verifall:importer-ancienne-base --essai

# 2. Après relecture du rapport :
php artisan verifall:importer-ancienne-base
```

La commande est **idempotente** : relancée, elle saute ce qu'elle a déjà importé
(repérage par l'adresse pour les comptes, par l'uuid ancien pour les
signalements). Un import interrompu se reprend sans doublon.

Elle travaille **dans une transaction par lot** : une erreur au milieu ne laisse
pas la moitié d'un compte.

> **Sauvegardez la base neuve avant l'étape 2.** Non parce que la commande est
> risquée, mais parce que c'est la seule façon de revenir en arrière si le
> résultat ne vous convient pas — un import réussi est plus difficile à défaire
> qu'un import raté.

---

## 7. Ce que cette première passe ne fait pas

Volontairement borné à ce qui a été demandé — **comptes, identifiants de
connexion, détails, et historique de vérifications** :

- l'Académie (`course_user`, `lesson_user`, `quiz_attempts`, 49 lignes au total) ;
- le forum (`forum_threads`, `forum_replies`) ;
- le Hub (`hub_items`, `news_articles`).

Ces reprises sont possibles et suivraient exactement le même patron. Elles n'ont
pas été écrites parce qu'une commande d'import qu'on n'a pas relue est une
commande qu'on ne devrait pas lancer, et que celle-ci est déjà longue.

> **Mise à jour du 14 septembre 2026** : Académie, forum et Hub ont désormais
> leur reprise — voir la « Seconde passe du 14 septembre 2026 », §24 à §35.

---

# Passe du 2 septembre 2026 — nettoyage, incidents, exécution réelle

Cette section a été écrite APRÈS l'exécution. Elle dit ce qui a été nettoyé, les
**deux arrêts** qu'a connus l'import, et la procédure de production.

## 8. Le relevé de propreté de l'ancienne base

Le dump a été chargé dans une base d'analyse séparée (`verifall_ancienne`) pour
être interrogé en SQL sans jamais toucher à la cible.

### Ce qui était déjà propre

| Contrôle | Résultat |
|---|---|
| Doublons d'adresse (insensible à la casse) | **0** |
| Adresses invalides ou vides | **0** |
| Adresses avec majuscules ou espaces | **0** (vérifié en `BINARY`) |
| Empreintes de mot de passe | **133/133 en bcrypt coût 12** |
| Lignes supprimées en douceur | **0** |
| Clés étrangères pendantes | **0** |
| Signalements vides des trois côtés | **0** |
| Balises HTML ou `<script>` dans le contenu | **0** |
| Double encodage (mojibake) | **0** |

### Ce qui a dû être nettoyé

| Défaut | Volume | Traitement |
|---|---|---|
| Noms avec espace de bord | **41 / 133** | `Str::squish` |
| Noms avec espace double interne | **40 / 133** | `Str::squish` |
| Titres contenant un retour à la ligne | **28 / 524** | `Str::squish` — un titre est UNE ligne |
| Contenus avec espaces de bord | **64 / 524** | `trim` seul — la mise en forme interne est celle de l'auteur |
| Descriptions dépassant `varchar(200)` | **23 / 524** | voir l'incident n° 2 |

### Deux pièges de méthode, et ils ont failli produire de faux rapports

**La collation `utf8mb4_unicode_ci` est PAD SPACE.** Les espaces de fin sont
ignorées à la comparaison : `name <> TRIM(name)` est donc **toujours faux**. Le
premier relevé a annoncé « 0 nom sali » alors que 41 l'étaient. Il faut comparer
les **longueurs**. La même collation étant insensible à la casse,
`email <> LOWER(email)` ne prouve rien non plus — d'où le `BINARY` du tableau
ci-dessus.

**Un motif d'expression régulière mal transmis peut matcher TOUT.** Un premier
contrôle a annoncé « 491 signalements et 133 comptes en mojibake ». C'était le
motif qui avait dégénéré au passage du client, pas les données. Le contrôle juste
porte sur les **octets** : la signature d'un double encodage est la séquence
`C383`, et elle est absente du dump. Un contrôle qui accuse **100 %** des lignes
accuse d'abord son auteur.

## 9. Les deux arrêts de l'import, et ce qu'ils ont appris

### Incident n° 1 — les verdicts sont en français

L'ancienne base rend `vrai` (294), `douteux` (143), `faux` (45), `inconnu` (4),
quand `VerdictFactCheck` est adossé à l'anglais. La valeur passait l'écriture
sans broncher — c'est une chaîne — puis levait un `ValueError` au premier cast.

**L'import s'est arrêté après avoir écrit les 133 comptes**, sur la toute
première vérification.

Deux raisons l'avaient caché, et les deux comptent au-delà de ce projet :

1. la fixture de test employait des verdicts **anglais** : elle ne représentait
   pas le dump réel, et un jeu d'essai infidèle ne prouve rien ;
2. **l'essai à blanc ne construit aucun modèle, donc ne caste rien.** Il ne
   pouvait structurellement pas l'attraper. *Un essai vert ne prouve pas qu'un
   import passera.*

Réparé par une table `VERDICTS` symétrique de `STATUTS`, et surtout par un
**contrôle préalable** (`verifierLesValeursContraintes`) qui parcourt tout le
dump et refuse AVANT la première écriture — comme le faisait déjà le contrôle des
empreintes. C'est la vraie leçon : une valeur adossée à un enum se vérifie sur la
totalité du dump, pas au fil de l'eau.

L'appariement `douteux` → `Trompeur` est **le seul qui demandait un arbitrage**,
et il porte sur 143 vérifications : `inconnu` désigne l'absence de conclusion,
donc `NonVerifiable`, ce qui laisse à `douteux` la place intermédiaire.

### Incident n° 2 — `context` est un `varchar(200)`

L'ancienne `description` monte à **4 770 caractères**, et 23 lignes dépassent la
limite : `Data too long for column 'context'`, de nouveau au milieu de l'import.

En cherchant la bonne correspondance, un défaut plus grave est apparu :

- **`source_url` n'était repris nulle part.** 138 vérifications de type URL en
  portaient une.
- Le `title` ancien, pour ces lignes, est un libellé **engendré et coupé à 60
  caractères** — « Analyse Invité : https://www.inserm.fr/c-est-quoi/ » quand
  l'adresse continue par « /debout-cest-quoi-le-systeme-vestibulaire ». Le
  reprendre comme affirmation aurait donné **138 liens morts**.
- **221 signalements ont un `content_text` plus riche que leur titre**, et seuls
  83 ont le titre pour préfixe : retenir le titre jetait le texte soumis.

Ce qui tranche, c'est la définition du champ dans le produit :
`SoumissionVerificationService` écrit `claim` à partir de l'affirmation soumise —
**`claim` EST le texte soumis**, il n'existe pas de titre à côté, et l'affichage
tronque déjà de lui-même. La correspondance fidèle est donc :

| Champ nouveau | Source | Règle |
|---|---|---|
| `claim` (`text`) | `source_url` si type URL, sinon `content_text`, sinon `title` | ce qui a été RÉELLEMENT soumis |
| `context` (`varchar(200)`) | `title`, sinon `description` | l'étiquette que la personne reconnaîtra, rognée à 197 par garde |

### Incident n° 3 — un doublon en PRODUCTION, dont la cause reste inconnue

*Ajouté le 10 septembre 2026. Contrairement aux deux précédents, celui-ci n'est
pas refermé — et c'est justement pour cela qu'il est écrit.*

Le journal de production du 2 septembre porte :

```
SQLSTATE[23000]: Integrity constraint violation: 1062
Duplicate entry '527' for key 'analysis_reports.analysis_reports_verification_id_unique'
```

Une première analyse a conclu que la commande n'était pas idempotente, et l'a
écrit dans `docs/PLAN_FINALISATION.md`. **Cette conclusion était fausse**, et la
vérification qui l'a défaite mérite d'être conservée :

- le dump a été sondé ligne à ligne — **524 signalements, aucun `uuid` vide,
  aucun `uuid` en double** ; 133 comptes, aucun courriel en double ;
- la garde d'idempotence `if ($uuid !== '' && Verification::withTrashed()->where('uuid',$uuid)->exists())`
  couvre donc **chaque ligne** du dump ;
- l'import rejoué deux fois rend des compteurs **identiques** — c'est désormais
  un test, « rejouée deux fois, ne duplique rien » ;
- `reporterLesSoldes()` n'est appelé que dans la transaction de création d'un
  compte NEUF : l'invariant `unit_logs` = `users.units` ne risquait rien.

L'hypothèse qui reste est un rapport **orphelin** — une ligne de
`analysis_reports` dont le `verification_id` ne désignait plus rien, réattribué
ensuite par l'auto-incrément à une vérification que l'import créait. Sur une
base nettoyée à la main, c'est possible ; et cet état ne s'observe plus.

Ce qui a été fait malgré tout : `poserLeRapport()` emploie `firstOrCreate` sur
`verification_id`. **Ce n'est pas un correctif, c'est une garde.** Elle ne
répare rien puisque la cause est inconnue ; elle transforme une ligne déjà
présente en saut compté au lieu d'un arrêt des 523 autres — les transactions
étant par enregistrement, un arrêt laisse un import à moitié fait.

La leçon rejoint celle de l'incident n° 1, et elle est plus générale :
**expliquer une trace d'erreur par le suspect le plus plausible n'est pas la
diagnostiquer.** Ici le suspect était crédible, il était nommé dans un plan, et
il était innocent.

## 10. Le résultat de l'exécution réelle

| Mesure | Valeur |
|---|---|
| Comptes importés | **133** (base : 12 → 145) |
| Vérifications importées | **524** (base : 1 → 525) |
| Rapports d'analyse | **486** (1 720 rapports d'outil agrégés) |
| Lignes de report d'unités | **125** |
| Auteurs introuvables | **0** |
| Empreintes identiques à l'octet près | **133 / 133** |
| Invariant `unit_logs` = `users.units` sur les importés | **1 238 = 1 238 = source** |
| Relecture Eloquent complète | **525 vérifications, 145 comptes, 0 faute de cast** |
| URL tronquées | **0 / 178** |
| Noms encore sales | **0** |

### Ce que les anciens utilisateurs retrouveront

- **Aucune réinitialisation de mot de passe.** Les 133 empreintes sont identiques
  à l'octet près ; `password_verify` se moque du coût configuré.
- **129 comptes sur 145 peuvent se connecter.** Les 16 restants n'avaient NI
  vérifié leur adresse NI jamais ouvert de session : le parcours OTP normal les
  attend. Six comptes qui se connectaient encore sans avoir vérifié leur courriel
  — dont un `@verifall.com` trois jours avant l'extraction — ont été sauvés du
  verrouillage : **une connexion réussie prouve la connaissance du mot de passe,
  un clic dans un courriel prouve seulement l'accès à la boîte.**
- **61 comptes ont un historique**, pour 299 vérifications rattachées.

### Ce qu'il faut savoir dire au porteur

**226 vérifications sur 524 (43 %) sont anonymes** — `created_by` était nul dans
l'ancienne base. Elles arrivent avec `user_id` nul et **n'apparaîtront dans aucun
tableau de bord**. Ce n'est pas un défaut de la reprise : elles n'appartenaient
déjà à personne. Elles restent l'histoire publique de la plateforme.

### Deux résidus assumés

1. **Un nom et une affirmation portent une apostrophe doublée.** Le défaut est
   dans la SOURCE — l'ancienne application échappait deux fois à l'enregistrement
   — et le lecteur de dump l'a recopié fidèlement. Un remplacement global a été
   écarté : il abîmerait un texte citant légitimement deux apostrophes, pour deux
   lignes sur 649.
2. **L'écart de 541 unités entre soldes et journal** vient des **12 comptes de
   démonstration préexistants** (546 unités pour 5 lignes de journal). Il est
   antérieur à cette reprise et ne la concerne pas ; sur les comptes importés,
   l'invariant est exact.

## 11. La procédure pour verifall.com

Rien de ce qui précède ne s'applique tout seul en production. Dans l'ordre, sans
en sauter :

```bash
# 1. Sauvegarde de la base CIBLE. Non négociable : l'import n'a pas d'annulation.
mysqldump --single-transaction --routines -u UTILISATEUR -p BASE > avant-import.sql

# 2. Essai à blanc. Il compte, et il refuse tout statut ou verdict inconnu.
php artisan verifall:importer-ancienne-base --essai --fichier=/chemin/dump.sql

# 3. Import réel, seulement si l'essai est vert.
php artisan verifall:importer-ancienne-base --fichier=/chemin/dump.sql

# 4. Contrôles d'après-import.
php artisan verifall:diagnostic
```

**Trois points de vigilance propres à la production :**

- **Le dump de production sera plus récent que celui-ci.** Il peut contenir des
  statuts ou des verdicts absents des tables de traduction. C'est précisément ce
  que l'essai à blanc dira, en nommant la valeur et son nombre d'occurrences — et
  rien ne sera écrit.
- **La commande est idempotente** (test à l'appui) : relancée, elle ne duplique
  rien, et un compte déjà présent n'est jamais écrasé. En cas d'arrêt, on corrige
  et on relance.
- **Le cron `queue:work` doit tourner** avant d'annoncer la reprise aux
  utilisateurs : sans lui, aucun code OTP ne partira pour les 16 comptes qui
  doivent encore vérifier leur adresse.

---

# Passe du 14 septembre 2026 — les outils

La reprise du répertoire d'outils de fact-checking. Elle suit le patron des
comptes, et elle a été écrite comme une **option de la même commande** :

```bash
php artisan verifall:importer-ancienne-base --seulement=outils --essai
php artisan verifall:importer-ancienne-base --seulement=outils
```

**Pourquoi une option et non une commande sœur.** Le lecteur de dump
(`lireTable`, `decouper`), les nettoyeurs (`propre`, `propreTexte`, `date`) et
le rendu des énumérations existent déjà dans `ImporterAncienneBase`. Une
commande sœur aurait dû les recopier ou les extraire, donc retoucher un code
qui a déjà écrit en production. L'option ne lit **ni** `users` **ni**
`signalements`. La commande sans option garde exactement son comportement,
et un test le vérifie.

## 12. Ce que contient l'ancienne base

| Table ancienne | Lignes | Sort |
|---|---:|---|
| `tools` | **21** | 20 vrais outils + 1 déchet de test |
| `fact_check_tools` | **0** | Jamais lue. Elle portait des `api_key` : un secret se configure dans `.env`, il ne s'importe pas. |
| `verified_sources` | **143** | **Hors périmètre de cette passe**, voir §16 |
| `verified_source_categories` | **3** | Idem |

## 13. Les correspondances

Le schéma cible est `2026_08_24_180000_create_tools` : **pas de
`created_by`, pas de `updated_by`, pas de `deleted_at`**, et une catégorie
prise dans une liste fermée de sept valeurs (`Citizen\Outils::CATEGORIES`).
Ces trois absences décident d'une bonne part de ce qui suit.

| Ancienne colonne | Nouvelle colonne | Règle |
|---|---|---|
| `id` | — | **Jamais repris.** Nouvel auto-incrément, nouvel `uuid`. |
| `name` | `title` | `Str::squish` |
| `slug` | `slug` | renormalisé par `Str::slug`, ou tiré du nom |
| `category` | `category` | **Table `CATEGORIES_OUTILS`, par slug.** Voir plus bas. |
| `category` | `tag` | **Table `ETIQUETTES_OUTILS`.** L'ancienne catégorie devient la pastille de la carte (`SITE WEB` → `WEB`, les autres à l'identique). |
| — | `icon` | Table `ICONES_OUTILS`, déduite de la catégorie nouvelle, avec les icônes du prototype |
| `description` | `description` | `trim`. Refus si elle dépasse 500 (colonne) ou fait moins de 20 caractères (écran staff). |
| `url_official` | `url` | validée : `url`, **`https://` obligatoire**, ≤ 255 |
| `main_use_case` | `use_cases` | la phrase **entière**, en une seule pastille. La couper aux virgules fabriquerait des pastilles que personne n'a écrites. |
| `content_type` + catégories + usage | `keywords` | en minuscules, ≤ 255 (limite de l'écran staff). Le type de contenu n'a pas de colonne et va nourrir la recherche. |
| — | `is_published` | **`false` : les fiches arrivent en brouillon**, voir §15 |
| — | `is_recommended` | `false` |
| — | `sort_order` | `100 + rang dans le dump`, après les quinze fiches du prototype (0 à 14) |
| `created_at`, `updated_at` | idem | conservés |

### La catégorie se traduit par outil, et non par catégorie

L'ancienne base classait par **type de média** (`IMAGE`, `VIDÉO`, `AUDIO`,
`TEXTE`, `AFRIQUE`, `OSINT`, `SITE WEB`). La nouvelle classe par **usage**. Les
deux axes ne se recouvrent pas : `TEXTE` réunit GPTZero, un détecteur d'IA, et
Snopes, une rédaction de fact-checking. `IMAGE` réunit ExifTool, PimEyes et
Google Fact Check Explorer. Une table catégorie → catégorie aurait mal rangé
la moitié des fiches.

La table `CATEGORIES_OUTILS` donne donc la catégorie **des 20 outils réels**,
un par un. Pour un outil qu'elle ne connaît pas (dump plus récent), il existe
un repli, limité aux deux catégories anciennes **homogènes** du dump :
`AUDIO` → Intelligence artificielle, `AFRIQUE` → Fact-checking. Pour les cinq
autres, l'outil inconnu **arrête l'import en se nommant**.

| Outil | Catégorie nouvelle |
|---|---|
| Forensic, ExifTool, Fake Image Detector | Médias & métadonnées |
| PimEyes, InVID / WeVerify Plugin, Bellingcat Toolkit | Recherche |
| Deepware Scanner, Microsoft Video Authenticator, ElevenLabs Speech Classifier, Resemble AI Detect, GPTZero, CopyLeaks AI Detector | Intelligence artificielle |
| Google Fact Check Explorer, Snopes, Africa Check, Dubawa / CJID, PesaCheck | Fact-checking |
| Google Earth Pro | Géolocalisation |
| Wayback Machine | Archives |
| VirusTotal | Web & domaines |

### Ce qui n'est pas repris, faute de colonne d'accueil

| Ancienne colonne | Pourquoi |
|---|---|
| `pricing_type` | Aucune colonne. C'est la perte la plus sensible : « Freemium / Payant » (PimEyes, CopyLeaks) et « Payant (API) » (Resemble AI) ne sont plus signalés. |
| `special_notes` | Aucune colonne. Elle contenait des mises en garde utiles (« sensible vie privée ; usage responsable » pour PimEyes). |
| `languages`, `region`, `status_label` | Constantes sur les 21 lignes (`Toutes`, `Mondial`, `Outil externe`) : aucune information. |
| `api_status`, `reliability_level` | Aucune colonne. |
| `logo_url` | Le nouveau modèle affiche une **icône** Material Symbols, pas d'image. La seule valeur non nulle est celle du déchet (`tools-logos/…png`), un chemin de disque de l'ancien site, absent ici. |
| `created_by`, `updated_by` | **Aucune colonne.** Le recâblage par adresse de compte n'a nulle part où s'écrire. Les 21 lignes portent toutes `created_by = 1`. Ajouter les colonnes serait une migration de schéma, hors de cette passe. |
| `deleted_at` | Pas de suppression douce sur `tools`. Voir §14. |

## 14. Le tri : déchets, fiches supprimées, collisions

### Écartés

| Outil | Raison |
|---|---|
| **Test1test** | Déchet de test : slug `test1test`, adresse `https://test.com`, type de contenu `Test`, notes `testtesttesttesttest`, description recopiée de VirusTotal, **supprimé le 24 mai 2026** |

Aucun autre déchet dans le dump. Les 20 autres lignes ont été relues une à une.

Une **fiche supprimée** (`deleted_at` non nul) est **écartée**, et non reprise
en brouillon. `tools` n'a pas de suppression douce, donc la reprendre la
ressusciterait : un outil que la rédaction avait retiré reviendrait dans le
répertoire. Dans le dump, la seule fiche supprimée est Test1test.

### Sautés : l'existant gagne

Même règle que les comptes (§3.2) : **aucune fiche existante n'est écrasée.**
Deux critères de repérage, parce qu'aucun ne suffit seul :

- **nom ou slug** identique ;
- **adresse normalisée** identique (hôte en minuscules sans `www.`, chemin sans
  `/` final), ou chemin qui **prolonge** l'autre sur le même hôte.

| Outil ancien | Fiche existante | Critère |
|---|---|---|
| Google Fact Check Explorer | Google Fact Check Explorer | même nom |
| Wayback Machine | Wayback Machine | même nom |
| Deepware Scanner | Deepware Scanner | même nom. L'adresse diffère (`scanner.deepware.ai` / `deepware.ai`) et ne l'aurait pas repéré. |
| InVID / WeVerify Plugin | InVID / WeVerify | même page (`www.invid-project.eu/` = `www.invid-project.eu`) |
| Google Earth Pro | Google Earth | `earth.google.com/web/` prolonge `earth.google.com` |

Le critère du chemin prolongé est large **à dessein**. Il ne peut que faire
sauter une fiche, jamais en écraser une, et chaque saut est nommé au rapport.

**Un rapprochement non retenu, à connaître** : *ExifTool* (`exiftool.org`, le
logiciel en ligne de commande) et *ExifTool en ligne* (`exif.tools`, un
service web qui l'emploie) sont deux produits distincts. Les deux fiches
coexistent, l'ancienne en brouillon.

## 15. Pourquoi en brouillon

La sélection publiée est une **recommandation faite aux citoyens**, et ces
fiches arrivent avec trois fragilités :

1. leur catégorie a été **arbitrée ici**, elle ne vient pas de l'ancienne base ;
2. leur **tarification et leurs mises en garde sont perdues** (colonnes absentes) ;
3. **une adresse ne mène pas à l'outil** : *Microsoft Video Authenticator*
   pointe vers `https://news.microsoft.com/`, la page d'accueil des actualités
   de Microsoft. L'outil n'a jamais été diffusé au public.

La rédaction les relit et les publie d'un clic depuis *Répertoire des outils*,
filtre « Brouillons ». Chaque fiche importée passe les règles de validation de
cet écran (test à l'appui) et s'enregistre donc sans retouche.

## 16. Les sources vérifiées : hors périmètre, et non couvertes

> **Mise à jour du même jour** : la reprise des sources est écrite et exécutée —
> `--seulement=sources`, §19 à §23. Les deux points à trancher ci-dessous y sont
> tranchés (§21).

Rien n'a été repris. Voici l'état, pour la passe qui s'en chargera :

| Mesure | Valeur |
|---|---:|
| `verified_sources` anciennes | **143** lignes, **135** domaines distincts |
| dont en liste noire (`is_blacklisted = 1`) | **1** (un site d'hameçonnage) |
| catégories anciennes (`category`) | `fact-checking` 89, `reference` 50, `institution` 2, `fact-checker` 1, `social_account` 1 |
| `verified_source_categories` | 3 (Medias Publics, Reference, Fact-checking) |
| Domaines de `SourcesFiablesSeeder` (22) présents dans l'ancienne liste | **8** : gouv.bj, sante.gouv.bj, reuters.com, apnews.com, afp.com, rfi.fr, africacheck.org, poynter.org |
| `trusted_sources` sur la base locale le 14 septembre | **0** (le seeder n'y a pas été lancé) |

**Le nouveau référentiel ne couvre donc pas l'ancien.** Le seeder en reprend 8
domaines sur 135. Deux points à trancher avant d'écrire la reprise :

- l'ancienne base porte **8 domaines en double** (africacheck.org, lemonde.fr,
  reuters.com…), alors que `trusted_sources.domain` est **unique** ;
- l'ancienne liste noire n'a **pas d'équivalent direct** : `trusted_sources`
  n'a qu'un `is_active` et une `credibility`.

## 17. Le résultat de l'exécution réelle (base locale `verifall`)

Préalable vérifié : l'import des comptes était terminé. Deux lectures espacées
de plusieurs minutes ont rendu les mêmes chiffres, **145 comptes, 524
vérifications, 486 rapports**, et aucun processus d'import ne tournait. On
attendait environ 525 vérifications : l'écart d'une ligne est antérieur à
cette passe, qui ne l'a pas touché.

Sauvegarde préalable : `mysqldump --single-transaction --routines`, 2,4 Mo,
complète.

| Mesure | Essai | Import réel |
|---|---:|---:|
| Outils lus | 21 | 21 |
| Importés (brouillon) | 15 | **15** |
| Sautés (collision) | 5 | **5** |
| Écartés : déchet | 1 | **1** |
| Écartés : supprimés | 0 | **0** |

| Contrôle d'après-import | Résultat |
|---|---|
| `tools` | 15 → **30** (15 publiées, 15 brouillons) |
| Relecture Eloquent complète | **30 fiches, 0 faute de cast**, 0 catégorie hors liste, 0 fiche rejetée par les règles de l'écran staff |
| Rendu | carte citoyenne des 30 fiches, écran staff (filtre brouillons), écran citoyen : **sans erreur**. Les brouillons restent invisibles côté citoyen. |
| Rejeu à blanc | **0 importé, 20 sautés** : l'import est idempotent |
| Comptes, vérifications, rapports | **inchangés** (145 / 524 / 486) |

## 18. La procédure pour verifall.com

```bash
# 1. Sauvegarde de la base CIBLE.
mysqldump --single-transaction --routines -u UTILISATEUR -p BASE > avant-outils.sql

# 2. Les quinze fiches du prototype doivent être là AVANT : ce sont elles qui
#    gagnent les collisions. Sans elles, les doublons seraient importés.
php artisan tinker --execute="echo App\Models\Tool::count();"   # attendu : ≥ 15
#    sinon : php artisan db:seed --class=OutilsSeeder

# 3. Essai à blanc : il refuse toute catégorie ou tout outil inconnu, toute
#    adresse non https, toute fiche que l'écran staff refuserait.
php artisan verifall:importer-ancienne-base --seulement=outils --essai --fichier=/chemin/dump.sql

# 4. Import réel, seulement si l'essai est vert et que la liste des sautés
#    est celle attendue.
php artisan verifall:importer-ancienne-base --seulement=outils --fichier=/chemin/dump.sql

# 5. Relecture éditoriale dans « Répertoire des outils », filtre Brouillons,
#    puis publication fiche par fiche.
```

**Un outil ajouté depuis ce dump** arrêtera l'essai avec son nom, son slug et
son ancienne catégorie. On ajoute alors sa ligne dans `CATEGORIES_OUTILS`,
après décision, et on relance. **Ce n'est pas une panne, c'est le contrôle
qui fait son travail.**

---

# Seconde passe du 14 septembre 2026 — sources, Académie, forum, Hub

Quatre nouvelles valeurs pour la même option :

```bash
php artisan verifall:importer-ancienne-base --seulement=sources  --essai
php artisan verifall:importer-ancienne-base --seulement=academie --essai
php artisan verifall:importer-ancienne-base --seulement=forum    --essai
php artisan verifall:importer-ancienne-base --seulement=hub      --essai
```

**Où vit le code.** La commande dépassait 1 600 lignes. Chaque reprise a sa
classe dans `app/Console/Commands/AncienneBase/` (`RepriseDesSources`,
`RepriseDeLAcademie`, `RepriseDuForum`, `RepriseDuHub`). Le lecteur de dump et
les nettoyeurs (`lireTable`, `decouper`, `propre`, `propreTexte`, `date`,
`enumerer`) ont été **déplacés à l'identique** dans le trait
`LitLAncienneBase`, que la commande et les quatre classes emploient : cinq
copies du lecteur auraient été cinq lecteurs à tenir d'accord. Les tests des
comptes, vérifications et outils, **inchangés**, restent verts : le déplacement
n'a rien modifié. Seule addition au trait : `comptesParAdresse()`, qui recâble
un ancien `users.id` sur le compte nouveau **par l'adresse**.

Le patron est celui des outils, sans exception : essai à blanc, contrôle
préalable de **tout** le dump avant la première écriture, aucun identifiant
ancien, clés étrangères recâblées, l'existant gagne, une transaction par
enregistrement, rejouable sans doublon.

Une règle a été ajoutée, faute de mieux : **une table ancienne vide dans le
dump, pour laquelle aucune règle n'a pu être écrite sur des données réelles,
arrête l'import si elle ne l'est plus** (`TABLES_SANS_REGLE` dans chaque classe).
Écrire une correspondance à l'aveugle serait deviner.

## 19. Sources — ce que contient l'ancienne base

| Mesure | Valeur |
|---|---:|
| `verified_sources` | **143** lignes, **135** domaines distincts |
| Catégories (`category`) | `fact-checking` 89, `reference` 50, `institution` 2, `fact-checker` 1, `social_account` 1 |
| Liste noire (`is_blacklisted = 1`) | **1** : « Site de Phishing "Concours-Benin.top" » |
| `trust_index` | **100** sur 141 lignes, 95 (Africa Check), 0 (le site d'hameçonnage) |
| URL sans schéma | 139 sur 143 (`lemonde.fr/les-decodeurs`) |
| `verified_source_categories` | 3 (Medias Publics, Reference, Fact-checking) — **non reprise**, voir §20 |

## 20. Sources — les correspondances

| Ancienne colonne | Nouvelle colonne | Règle |
|---|---|---|
| `id`, `category_id` | — | Jamais repris |
| `url` | `domain` | `TrustedSource::normaliserLeDomaine()` — la règle de l'écran et des seeders : hôte en minuscules, sans `www.`, sans chemin |
| `url` | `url` | `https://` ajouté s'il manque (comme les seeders), sinon la règle `url` de l'écran refuserait la fiche |
| `name` | `name` | `Str::squish`, ≤ 160 |
| `category` | `source_category_id` | table `CATEGORIES`, voir ci-dessous |
| `trust_index` | `credibility` | **seulement s'il diffère de 100**, voir ci-dessous |
| `is_blacklisted` | `is_active` = faux, `credibility` = 0 | §21 |
| — | `notes` | nom, adresse et catégorie d'origine, provenance de la crédibilité, lignes absorbées |
| `created_at`, `updated_at` | idem | conservés |

**La catégorie.** C'est la chaîne `category`, portée par chaque ligne, qui est
traduite — et non `verified_source_categories`, qui range le site
d'hameçonnage parmi les « Medias Publics » et ne distingue pas une institution
d'un titre de presse.

| Ancienne catégorie | Famille nouvelle | Crédibilité par défaut |
|---|---|---:|
| `fact-checking`, `fact-checker` | Réseaux de fact-checking | 88 |
| `reference` | Publications de référence | 78 |
| `institution` | Institutions béninoises — **bornée aux domaines `.bj`** ; une institution étrangère arrête l'import | 85 |
| `social_account` | aucune — seule ligne : la liste noire | — |
| toute autre | **arrêt avant écriture** | — |

Les familles sont créées si elles manquent, avec les valeurs **exactes** de
`SourcesFiablesSeeder` et `SourcesVeraSeeder` (un test compare) ; si elles
existent, elles ne sont pas touchées.

**La crédibilité.** Une fiche importée change le score (§22) : elle n'en reçoit
une que si l'ancienne ligne en porte une **réelle**. Or 100 est la valeur par
défaut de la colonne, et 139 des 141 lignes à 100 ont été insérées d'un bloc le
6 mai 2026 sans jamais être retouchées : un 100 ne se distingue pas d'un « jamais
renseigné ». Il est donc remplacé par la crédibilité de la famille — la règle
même de `SourcesVeraSeeder`. Seuls **95** (Africa Check) et **0** (liste noire)
sont repris. Un `updated_at` postérieur n'a pas été retenu comme preuve : les
deux lignes gouvernementales ont été retouchées à la minute où les catégories
ont été créées, très probablement pour poser `category_id`.

## 21. Sources — doublons et liste noire

### Huit domaines en double : une fiche par domaine

`trusted_sources.domain` est unique, et le moteur interroge **par domaine**.
Sept doublons sur huit sont une rubrique de fact-checking à côté du titre
entier. **Règle** : la ligne dont l'adresse **n'a pas de chemin** gagne (elle
décrit le site entier, et c'est le site entier que le moteur créditera) ; à
égalité, **la première du dump**. La ligne absorbée est citée dans la note.
Une fusion de champs a été écartée : mélanger le nom de l'une et la catégorie
de l'autre produirait une fiche que personne n'a écrite.

| Domaine | Fiche retenue | Ligne absorbée |
|---|---|---|
| africacheck.org | Africa Check (Partenaire IFCN), 95 | Africa Check (100, défaut) — égalité : la première |
| 20minutes.fr | 20 Minutes | 20 Minutes - Fake off |
| francetvinfo.fr | France Info | France Info - Vrai ou Fake |
| lefigaro.fr | Le Figaro | Le Figaro - Le Vrai du Faux |
| liberation.fr | Libération | Libération - CheckNews |
| lemonde.fr | Le Monde | Le Monde - Les Décodeurs |
| reuters.com | Reuters | Reuters Fact Check |
| washingtonpost.com | The Washington Post | The Washington Post Fact Checker |

Conséquence à connaître : ces sept titres passent dans la famille « Publications
de référence » (78), leur rubrique de fact-checking n'ayant pas de domaine
propre. Un domaine à la fois en liste noire et fiable **arrête l'import**
(collision douteuse, test à l'appui).

### La liste noire : fiche inactive, crédibilité 0 — aucune migration

`trusted_sources` est un référentiel de **confiance** : il n'a pas d'équivalent
de liste noire. **Aucune colonne n'a été inventée, aucune migration ajoutée** :
une colonne `is_blacklisted` que le moteur ne lirait pas serait décorative, et la
lui faire lire demanderait de modifier le scoring, hors de cette reprise.

La traduction retenue — **fiche inactive, crédibilité 0, « LISTE NOIRE » en
tête de note** — est la seule sans effet de bord. *Active*, même à 0, la fiche
serait trouvée par le moteur, annoncée « Source référencée par la rédaction »
par `AnalyseTechniqueAdapter` (qui sauterait alors l'heuristique d'usurpation),
et **listée sur la page publique des sources**. *Inactive*, elle ne change aucun
score, garde la décision, et bloque un référencement par mégarde (l'écran
refuse un domaine déjà présent). Le domaine reste pénalisé par l'heuristique
ordinaire (extension `.top` : +25 de suspicion).

## 22. Sources — l'effet sur le score des analyses futures

Depuis le commit `0993de9`, `AdaptateurHttp::fiabiliteDeLaSource()` rend la
crédibilité de la fiche **active** du domaine (ou de son domaine parent :
`edition.cnn.com` → `cnn.com`) à la place du défaut de l'adaptateur. La
fiabilité pondère chaque preuve (`Preuve::fiabilite / 100`). Sur la base
locale, où le référentiel était **vide** :

| Domaine cité en preuve | Avant (Gemini cité / ancré · Google CSE) | Après |
|---|---|---:|
| 81 rédactions de fact-checking (lessurligneurs.eu, fullfact.org…) | 70 / 75 · 55 | **88** |
| 50 titres de référence (lemonde.fr, cnn.com, nytimes.com…) | 70 / 75 · 55 | **78** |
| africacheck.org | 70 / 75 · 90 | **95** |
| **gouv.bj, sante.gouv.bj** | 70 / 75 · **95** | **85** — *baisse* pour Google CSE |
| **afp.com** | 70 / 75 · **90** | **78** — *baisse* pour Google CSE |
| factuel.afp.com | 70 / 75 · 90 | 88 |
| concours-benin.top (inactive) | inchangé | inchangé |

Et deux effets hors score :

- `AnalyseTechniqueAdapter`, pour un **lien soumis** sur l'un de ces domaines,
  ajoute « Source référencée par la rédaction : … » au raisonnement et saute
  l'heuristique d'usurpation (sans effet ici : aucun des 134 domaines ne
  contient « gouv », « presidence »… hors suffixe institutionnel déjà exempté) ;
- la page publique « Nos sources » liste désormais **134 sources** en trois
  familles.

Le cache du moteur (15 minutes) est vidé à la fin de l'import : l'effet est
immédiat, test à l'appui.

**En production**, les seeders du référentiel ont été lancés (le document de
`0993de9` compte 408 fiches) : les domaines déjà référencés sont **sautés**, et
leur crédibilité, éventuellement amendée, reste celle de la rédaction. L'essai
les nomme un par un.

## 23. Sources — résultat réel et procédure

| Mesure | Essai | Import réel | Rejeu à blanc |
|---|---:|---:|---:|
| Lignes lues | 143 | 143 | 143 |
| Fiches (domaines) | 135 | 135 | 135 |
| Lignes fusionnées | 8 | 8 | 8 |
| Importées actives | 134 | **134** | 0 |
| Importées en liste noire | 1 | **1** | 0 |
| Sautées (domaine présent) | 0 | 0 | **135** |
| Crédibilité reprise / par famille | 2 / 133 | 2 / 133 | — |

Relecture : **135 fiches, 3 familles, 0 faute de cast, 0 fiche refusée par la
règle de domaine de l'écran**. Crédibilités : 88 ×81, 78 ×50, 85 ×2, 95, 0.
Le moteur trouve `www.lemonde.fr` (78), `edition.cnn.com` → CNN (78), et ne
trouve pas `concours-benin.top`.

```bash
mysqldump --single-transaction --routines -u UTILISATEUR -p BASE > avant-sources.sql
php artisan verifall:importer-ancienne-base --seulement=sources --essai --fichier=/chemin/dump.sql
#   → relire la liste des « Sautée » : ce sont les fiches que la rédaction garde.
php artisan verifall:importer-ancienne-base --seulement=sources --fichier=/chemin/dump.sql
```

**Ordre à respecter** : lancer `SourcesFiablesSeeder` **après** la reprise
écraserait les fiches communes (il fait un `updateOrCreate`) ; `SourcesVeraSeeder`,
en insertion seule, ne les touche pas.

## 24. Académie — ce que contient l'ancienne base

| Table | Lignes | Sort |
|---|---:|---|
| `courses` | 6 | 2 rapprochés, 4 écartés (§25, §26) |
| `sections`, `lessons` | 6, 10 | lues pour situer leçons et quiz ; **aucune créée** |
| `quizzes`, `questions` | 2, 9 | **aucune option ni bonne réponse** (`options_data`, `correct_answer` NULL partout) |
| `lesson_resources` | 2 | PDF de l'ancien disque, absents ici : non repris |
| `course_user` | 29 | **18 reprises** |
| `lesson_user` | 7 | sautées (§27) |
| `quiz_attempts` | 13 | sautées (§27) — 11 d'un seul compte de l'équipe, toutes « failed » ou « in_progress », sans réponses enregistrées |
| `certificates`, `assignment_submissions`, `user_progress`, `course_faqs`, `live_classes` | **0** | non vides, elles **arrêtent** l'import |
| `categories` (type `course`) | 3 | non lue : aucun cours n'est créé |

## 25. Académie — la correspondance des cours

**Le point décisif : deux cours anciens existent déjà dans le catalogue.**
`AcademieSeeder` (extrait de `lms-courses.json`) porte les mêmes cours sous un
titre à peine retouché — la description d'ouverture est la même mot pour mot
pour le premier.

| Cours ancien | Cours nouveau | Garde |
|---|---|---|
| « Introduction au fact-checking -  CLOM Vérif'All » | `introduction-fact-checking` — « Introduction au fact-checking, CLOM Vérif'All » | titres identiques une fois normalisés (`Str::slug`) |
| « Un siècle de fact-checking » | `un-siecle-de-fact-checking` | même slug |

La table `COURS` est **explicite** (ancien slug → slug nouveau) et chaque ligne
est **gardée** : l'import s'arrête si la cible est absente, non publiée, payante,
ou si son titre normalisé ne ressemble plus au titre ancien. **Aucun cours n'est
créé, aucun doublon.** Un cours ancien qui n'est ni dans `COURS` ni dans
`COURS_ECARTES` arrête l'import en se nommant.

**Mais le titre est commun, pas le contenu.** L'« Introduction » ancienne avait
2 leçons ; la nouvelle en a 5 et un quiz. « Un siècle » avait une vidéo et un
quiz ; le nouveau a 6 leçons et un autre quiz. Aucune leçon, aucun quiz n'a de
jumeau (§27).

## 26. Académie — les cours écartés, et leur contenu

| Cours ancien | Raison |
|---|---|
| « TEST TEST TEST TEST TEST » | déchet de test, supprimé le 24 mai 2026 |
| « MLJKHGFDXCGVBJKLMLKJGHGXGFCHJK » | déchet de test, supprimé le 23 mai 2026 |
| « Généralités sur les fake news - Comprendre pour mieux détecter » | sans équivalent ; **une seule leçon restante** (deux supprimées), 243 caractères de consignes, ni vidéo ni quiz |
| « Journalistes vs Infox » | sans équivalent ; **cassé une fois repris** : vidéo YouTube que le lecteur de leçon (`<video src>`) ne lit pas, quiz sans options ni réponses, PDF absent |

**Pourquoi l'écart plutôt que le brouillon.** Publier un cours est une décision
éditoriale, pas une reprise. Et un cours en brouillon **ne peut pas porter
d'inscriptions** : le produit les refuse (`InscriptionAuCours::inscrire`). Un
brouillon aurait donc importé du contenu cassé ou quasi vide, sans ses 12
inscrits.

**Inventaire, pour réécriture dans le studio :**

- *Généralités sur les fake news* (formateur : ancien compte n° 14) — leçon
  « Fake news, hoax, infox ! De quoi parle-t-on ? » (texte, 10 min : prendre une
  info virale, se demander qui publie, pourquoi, quelles preuves, chercher si des
  médias fiables en parlent, la classer). Leçons supprimées : « Anatomie d'une
  fausse information », « Typologie des fake news ».
- *Journalistes vs Infox* (formateur : ancien compte n° 21) — leçon vidéo
  « Journalistes vs Infox - Vidéo CFI » (`https://youtu.be/qkvKmy6xXUY`, source :
  Conseils de Journalistes, CFI et France Médias Monde) avec le PDF « Journalistes
  vs Infox.pdf » ; quiz « Évaluation : Quiz - Journalistes vs Infox », seuil 70 %,
  deux questions (l'eau salée contre la Covid-19 ; le premier devoir d'un
  journaliste selon la Charte de Munich) avec leurs explications, **sans les
  réponses proposées**. Une leçon supprimée de type quiz portait les options
  dans son HTML : c'est là, dans le dump, qu'il faudra les rechercher.

Une fois un cours réécrit et publié : ajouter sa ligne à `COURS`, relancer
`--seulement=academie`. Les inscriptions déjà reprises sont sautées, celles du
cours nouveau rattachées.

## 27. Académie — inscriptions, progression, tentatives, certificats

| Ancienne colonne (`course_user`) | Nouvelle (`enrollments`) | Règle |
|---|---|---|
| `user_id` | `user_id` | par l'adresse ; introuvable → **sautée et comptée** |
| `course_id` | `course_id` | table `COURS` ; cours écarté → sautée et comptée |
| `progress_percent` | `progress` | **0**, voir ci-dessous |
| — | `status` | `enrolled` |
| `enrolled_at` | `started_at` | conservé |
| `updated_at` | `last_activity_at` | conservé |
| `completed_at` | — | non transposé, compté (0 sur les cours rapprochés) |
| `expires_at` | — | aucune colonne ; NULL partout |
| — | `paid_units` | 0 : les deux cours cibles sont gratuits, et le contrôle préalable l'exige |
| — | `courses.students_count` | **incrémenté** dans la même transaction, comme `InscriptionAuCours` |

Une inscription déjà présente (même compte, même cours) **gagne** — c'est aussi
ce qui rend l'import idempotent.

**L'avancement repart à 0.** `ProgressionAcademie` dérive l'avancement des
leçons lues, jamais d'un pourcentage reçu. Aucune leçon ancienne n'ayant de
jumeau, les faits donnent 0 ; écrire l'ancien « 50 % » (3 inscriptions à « Un
siècle de fact-checking ») ferait mentir la jauge au premier recalcul.

**Leçons lues et tentatives : sautées, comptées, jamais rattachées au hasard.**

| | Cours écarté | Cours rapproché, sans équivalent |
|---|---:|---:|
| Leçons lues (7) | 3 | 4 (la vidéo « Un siècle de fact-checking ») |
| Tentatives de quiz (13) | 11 | 2 (quiz ancien sans réponses ; le quiz nouveau a d'autres questions) |

**Certificats.** La table ancienne est **vide** : aucun n'est repris, aucun n'est
fabriqué. Un certificat porte un code vérifiable publiquement ; en émettre un
pour une formation qu'on n'a pas vu s'achever serait un faux. Si un dump plus
récent en contient, **l'import s'arrête** (test à l'appui).

## 28. Académie — résultat réel et procédure

| Mesure | Essai | Import réel | Rejeu à blanc |
|---|---:|---:|---:|
| Cours rapprochés / écartés / créés | 2 / 4 / 0 | 2 / 4 / **0** | 2 / 4 / 0 |
| Inscriptions importées | 18 | **18** | 0 (18 « déjà présentes ») |
| Sautées : cours écarté | 11 | 11 | 11 |
| Leçons lues / tentatives sautées | 7 / 13 | 7 / 13 | 7 / 13 |
| Certificats | 0 | 0 | 0 |

Relecture : **18 inscriptions, 0 faute de cast, 0 orpheline, 0 sur un cours
payant** ; « Introduction au fact-checking » 10 inscrits, « Un siècle de
fact-checking » 8, `students_count` égal. Rendu sans erreur : *Enrôlement aux
cours*, *Progression des apprenants* (staff), *Mes tutoriels* et *Catalogue*
(citoyen, « 2 en cours » pour un apprenant repris).

```bash
php artisan tinker --execute="echo App\Models\Course::whereIn('slug',['introduction-fact-checking','un-siecle-de-fact-checking'])->where('is_published',true)->count();"   # attendu : 2
php artisan verifall:importer-ancienne-base --seulement=academie --essai --fichier=/chemin/dump.sql
php artisan verifall:importer-ancienne-base --seulement=academie --fichier=/chemin/dump.sql
```

**Point de vigilance** : `AcademieSeeder` et `CoursReelsSeeder` remettent
`students_count` à 0 et **remplacent** les leçons de leurs cours. Relancés après
la reprise, ils fausseraient le compteur des deux cours rapprochés (les
inscriptions, elles, restent). Ce comportement est antérieur à cette passe.

## 29. Forum — ce que contient l'ancienne base

| Table | Lignes | Sort |
|---|---:|---|
| `forum_categories` | 5 | 3 rapprochées, 2 écartées |
| `forum_threads` | 5 | **tous sautés** : sans titre |
| `forum_polls`, `forum_poll_options` | 2, 5 | sautés avec leur sujet |
| `forum_replies` | 0 | règle écrite et testée |
| `forum_comments`, `forum_reactions`, `poll_votes`, `forum_course_threads` | **0** | non vides, elles **arrêtent** l'import |

Les cinq sujets sont des messages d'essai de quelques mots — « Dites moi »,
« Hello », « Hello... », « Salut a tous », « Hello everyone here » — et le
second sondage a pour réponses « 1 » et « 1 ».

## 30. Forum — les correspondances et les décisions

**Un sujet est repris s'il est un sujet que le forum accepterait** : les règles
de `Citizen\Forum` (titre 10 à 180 caractères, message 20 à 5 000). L'ancien
forum avait le titre **facultatif**, et il est vide sur les cinq sujets ;
`threads.title` est obligatoire. **Le titre n'est pas inventé** — ni recopié du
message : « Hello » ferait un titre et un corps identiques, que le formulaire
refuserait encore. Sujets et sondages sont donc sautés et comptés.

| Ancienne colonne | Nouvelle | Règle |
|---|---|---|
| `user_id` | `user_id` | par l'adresse ; introuvable → **NULL, compté** (la colonne l'autorise ; le forum affiche déjà un auteur disparu) |
| `category_id` | `forum_category_id` | par slug vers une rubrique existante (l'existante gagne) ; sans équivalent → créée **seulement si un sujet repris s'y trouve** |
| `title`, `content` | `title`, `body` | règles du formulaire ; corps **purifié avant persistance** par `Purifier::clean(…, 'default')`, comme `AnimationDuForum::assainir()` |
| `source_link` | `source_url` | gardé seulement si URL http(s) valide ≤ 500 ; sinon NULL, compté |
| `media_path`, `type` | — | fichiers de l'ancien disque : non référencés, comptés (règle d'`ActifsReferencesTest`) |
| `is_pinned`, `is_locked`, `deleted_at` | idem | conservés |
| `status` | — | seul `public` est accepté ; toute autre valeur **arrête** l'import (un sujet masqué ne doit pas devenir public) |
| `is_sensitive` | — | aucune colonne : un sujet sensible est **sauté**, pour ne pas publier sans avertissement |
| `forum_replies.*` | `replies` | même règles ; `is_mentor_reply` → `is_official` ; `replies_count` et `last_activity_at` **recalculés** |
| `forum_polls` + options | `polls`, `poll_options` | règles de `Sondages` : question 5 à 300, 2 à 6 réponses distinctes ≤ 120 ; compteurs de votes à 0 |

L'idempotence repose sur le titre et la date de création (un sujet ancien n'a
pas d'uuid). Toutes ces règles sont éprouvées en test sur des lignes réelles
**modifiées** (titre ajouté, `<script>` injecté), puisque le dump n'en contient
aucune de valide.

## 31. Forum — résultat réel et procédure

| Mesure | Essai | Import réel | Rejeu à blanc |
|---|---:|---:|---:|
| Sujets / réponses / sondages importés | 0 / 0 / 0 | **0 / 0 / 0** | 0 / 0 / 0 |
| Sautés : sans titre | 5 | 5 | 5 |
| Sondages sautés (sujet non repris) | 2 | 2 | 2 |
| Rubriques rapprochées / créées / écartées | 3 / 0 / 2 | 3 / **0** / 2 | 3 / 0 / 2 |

**Rien n'a été écrit**, et c'est le résultat juste. Les rubriques « Débats » et
« Election » ne sont pas créées : elles n'auraient porté aucun sujet. Rendu sans
erreur : *Forum citoyen* (staff), *Catégories du forum*, *Forum* (citoyen).

```bash
php artisan verifall:importer-ancienne-base --seulement=forum --essai --fichier=/chemin/dump.sql
php artisan verifall:importer-ancienne-base --seulement=forum --fichier=/chemin/dump.sql
```

Un dump de production plus récent peut contenir de vrais sujets titrés : ce sont
eux que la règle reprendra.

## 32. Hub — ce que contient l'ancienne base

| Table | Lignes | Sort |
|---|---:|---|
| `e_services` | 36 | **35 portails en brouillon**, 1 sauté |
| `news_articles`, `news_sources` | 3, 2 | **écartés**, §34 |
| `job_offers` | 0 | règle écrite, §34 |
| `hub_items` | 0 | non vide, elle **arrête** l'import |

## 33. Hub — les e-services deviennent des portails officiels

| Ancienne colonne | Nouvelle (`hub_portals`) | Règle |
|---|---|---|
| `name` | `name` | `Str::squish` (deux noms portaient une espace de tête), ≤ 120 |
| `url` | `url` | `https://` obligatoire, ≤ 255 — règles de `Staff\PortailsOfficiels` |
| `category` | `category` | **reprise telle quelle** : Administration 30, Éducation 4, Justice 1, Santé 1 ; toute autre **arrête** l'import |
| `is_verified` | `is_official` | « vérifié » par l'ancienne équipe = service public authentique |
| — | `description` | **NULL** : aucune colonne ancienne, voir ci-dessous |
| — | `is_published` | **faux** : brouillon |
| — | `position` | 100 + rang, après les portails existants |

**La rubrique n'est pas traduite.** `hub_portals.category` est une liste ouverte ;
rebaptiser « Administration » en « Démarches » (la rubrique du portail existant)
serait un arbitrage éditorial.

**Collision : l'adresse EXACTE** (hôte sans `www.`, chemin sans `/` de bord,
requête gardée) ou le même nom. Pas le « chemin qui prolonge » des outils :
trente-trois e-services sont des pages profondes de `service-public.bj`
(`/public/services/service/PS00373`, l'extrait de casier judiciaire), qui
auraient toutes sauté devant le portail « Service Public Bénin » alors que
chacune mène à une démarche distincte. Seul
« Portail National des services publics du Bénin » (`https://service-public.bj/`)
recouvre le portail existant : il est sauté, l'existant gagne.

**Pourquoi en brouillon, et sans descriptif.** Trente-cinq liens d'un coup
noieraient les cinq portails de la page ; et l'écran staff **exige un
descriptif** que l'ancienne table n'a jamais eu. Il n'est pas inventé : la
rédaction l'écrit en ouvrant la fiche (l'enregistrement le demandera), ou publie
d'un clic sans l'ouvrir (`basculerLaPublication` ne valide pas). C'est le seul
point où une fiche importée ne passe pas les règles de son écran, et il est
voulu.

## 34. Hub — ce qui est écarté

**Les actualités.** `news_articles` relayait des communiqués — « Discours
d'investiture du Président de la République, Monsieur Romuald WADAGNI », « Bénin :
le Plan national de développement 2026-2035 approuvé », « Santé : le Bénin se dote
d'une Agence de lutte contre la malaria et les moustiques » — avec un verdict
« En attente ». `hub_articles` est une **brève de vérification** : l'écran exige
une affirmation, un corps de 50 caractères, un verdict. Reprendre un communiqué
comme brève afficherait un bandeau « EN COURS » sur une vérification que
personne n'a menée.

**Les offres d'emploi.** Table vide ; la décision est écrite et testée sur des
lignes synthétiques. Une offre **expirée** est **écartée** : le produit ne
publie pas une offre dont l'échéance est passée (l'écran exige une échéance ≥
aujourd'hui) et la clôt passé le délai de grâce — l'importer n'ajouterait qu'une
archive. Une offre **encore ouverte arrête l'import** : lieu, type et portée,
obligatoires dans `Staff\Opportunites`, n'existent pas dans l'ancienne table.

## 35. Hub — résultat réel et procédure

| Mesure | Essai | Import réel | Rejeu à blanc |
|---|---:|---:|---:|
| E-services lus | 36 | 36 | 36 |
| Portails importés (brouillon) | 35 | **35** | 0 |
| Portails sautés | 1 | 1 | **36** |
| Actualités écartées | 3 | 3 | 3 |

Relecture : **40 portails (5 publiés, 35 brouillons), 0 faute de cast, 0 adresse
non https, 0 nom sale**. `hub_articles` (6) et `hub_jobs` (6) inchangés. Rendu
sans erreur de *Portails officiels* (staff). La page citoyenne répond 404 sur la
base locale : le réglage `hub_portals_actif` y est fermé — c'est la configuration,
pas la reprise.

```bash
php artisan verifall:importer-ancienne-base --seulement=hub --essai --fichier=/chemin/dump.sql
php artisan verifall:importer-ancienne-base --seulement=hub --fichier=/chemin/dump.sql
#   puis « Portails officiels » : écrire le descriptif, publier fiche par fiche.
```

## 36. Exécution sur la base locale `verifall` — synthèse

**Préalable vérifié** : 145 comptes, 524 vérifications, 486 rapports, 30 outils ;
`trusted_sources` et `enrollments` vides.

**Sauvegarde** : `mysqldump --single-transaction --routines`, 2,4 Mo, complète
(« Dump completed »), fichier
`verifall-avant-reprise-sources-academie-forum-hub-2026-09-14.sql` dans le
dossier temporaire de la session
(`C:\Users\tempuser\AppData\Local\Temp\claude\C--Projects-verifall\1607217d-95dc-496f-829a-4808c9168ec2\scratchpad\`).
À déplacer si on veut la garder : ce dossier n'est pas pérenne.

**Ordre** : essais à blanc des quatre périmètres (verts, chiffres ci-dessus),
import réel dans l'ordre sources → Académie → forum → Hub, relecture Eloquent,
rendu des écrans, rejeu à blanc.

| Contrôle | Résultat |
|---|---|
| Relecture Eloquent | 135 sources, 3 familles, 18 inscriptions, 9 cours, 40 portails, 5 rubriques, 5 sujets : **0 faute de cast** |
| Rendu Livewire sur la base réelle | 13 écrans (sources staff ×2, familles, page publique, enrôlement, progression, mes tutoriels, catalogue, portails staff et citoyen, forum staff, catégories du forum, forum citoyen) : **0 erreur** |
| Rejeu à blanc | **0 importé** sur les quatre périmètres |
| Tables voisines | comptes 145, vérifications 524, rapports 486, outils 30, sujets 5, brèves 6, offres 6, leçons lues 0, tentatives 0, certificats 0 : **inchangées** |

**Qualité** : 84 tests dans `ImporterAncienneBaseTest` (dont les 43 préexistants
des comptes, vérifications et outils, restés verts), `ReferentielDesSourcesTest`
et `SourcesPubliquesTest` verts ; `phpstan` vert, **rien ajouté** à la baseline.
Un seul test existant a été retouché : « refuse une valeur inconnue pour
--seulement » employait `forum` comme exemple de valeur inconnue.

**La fixture** porte les lignes réelles des 16 tables concernées, extraites du
dump par numéro de ligne. Les comptes 26 à 136 qu'elles référencent y ont une
adresse **fictive** : les adresses réelles des apprenants et membres du forum
sont des données personnelles, qui n'ont rien à faire dans un dépôt de code.
