# Plan de finalisation — de bout en bout

Écrit le 9 septembre 2026, après un audit du dépôt réel. Chaque chiffre de
cette page se revérifie par une commande ; aucun n'est estimé. Les phases sont
ordonnées : chacune suppose la précédente, et les trois premières referment des
**pannes constatées**, pas des manques.

---

## L'état mesuré, et l'écart avec ce que `docs/` annonce

| Repère | Valeur réelle | Ce que `docs/` annonce encore |
|---|---:|---|
| Modèles | 83 | — |
| Migrations | **71** | 36 |
| Composants Livewire | **103** (Staff 57 · Citizen 32 · Public 10 · Auth 4) | 40 |
| Routes | **205** (staff 59 · api 53 · public 37 · citizen 32) | 7 côté staff |
| Dépôts | 14 | 12 |
| Vues Blade | 203 · prototype 107 | — |
| Tests | **1 936 passés, 1 ignoré, 5 246 assertions** | 831 |
| Arbitrages | **163** | 78 |
| Adaptateurs à porter | **5 sur 12** | 13 sur 19 |
| Flutter | **334 fichiers Dart, 31 écrans, 69 GoRoute** | « le socle, et rien que le socle » |

Barres de qualité, vérifiées le 9 septembre : `phpstan analyse` niveau 6 →
*No errors* · `php artisan test --parallel` → vert en 634 s sur 16 processus ·
`composer audit` → aucune vulnérabilité.

**Le produit est plus avancé que sa documentation ne le dit.** C'est le premier
piège de ce plan : chiffrer le reste à partir de `RESTE_A_FAIRE.md` conduit à
planifier du travail déjà fait.

---

## Phase 0 — Mettre onze lots de travail à l'abri

Le dernier commit est `3b31c88` (« Sprint 3 clos »). **Tout** ce qui suit — lots
5 à 14, les 31 écrans d'administration, l'espace mentor, les 24 courriels, le
mode maintenance, l'analyse statique — vit uniquement dans le répertoire de
travail : 112 fichiers modifiés, 646 non suivis.

`verifall_laravel.zip` (**588 Mo**) est à la racine, non suivi **et non ignoré** :
un `git add -A` réflexe le grave dans l'historique, définitivement.

| | |
|---|---|
| Fichiers | `.gitignore`, puis commits thématiques suivant `JOURNAL_DES_LOTS.md` |
| Migrations | aucune |
| Risque | Le seul irréversible du plan. |
| Vérification | `git status --short \| wc -l` → 0 ; `du -sh .git` |

---

## Phase 1 — Refermer les trois pannes de production

`errors/error1.txt` est un journal de **production** (`/home/massenon/verifall.com`,
2 septembre). Il dit trois choses, et deux sont déjà corrigées ici sans avoir
jamais été déployées.

### 1.1 L'import — traité le 10 septembre 2026, et le diagnostic d'origine était FAUX

`SQLSTATE[23000] … Duplicate entry '527' for key 'analysis_reports_verification_id_unique'`.

Cette section annonçait un import « non idempotent », et donnait
`ImporterAncienneBase.php:737` pour coupable. **Vérification faite, c'est
inexact.** Le rectificatif tient plus de place que l'affirmation, et c'est
normal : une fausse piste écrite dans un plan coûte le temps de tous ceux qui
la suivent.

**Ce qui a été mesuré :**

- Le dump réel a été sondé ligne à ligne : **524 signalements, aucun `uuid`
  vide, aucun `uuid` en double** ; 133 comptes, aucun courriel en double.
- La garde d'idempotence de la commande — `if ($uuid !== '' && Verification::withTrashed()->where('uuid',$uuid)->exists())` —
  couvre donc **chaque ligne** du dump.
- L'import rejoué deux fois de suite rend des compteurs **identiques** et sort
  en succès. C'est désormais un test : « rejouée deux fois, ne duplique rien ».
- `reporterLesSoldes()` n'est appelé que dans la transaction de création d'un
  compte NEUF. L'invariant `unit_logs` = `users.units` ne risquait rien.

L'orchestrateur reste hors de cause, pour la raison déjà donnée — prise en main
atomique, et le rapport comme le statut sont écrits dans **la même**
transaction.

**La cause du doublon de production n'a donc pas été retrouvée**, et il vaut
mieux l'écrire que de laisser une explication commode. L'hypothèse qui tient le
mieux est un rapport **orphelin** dont l'identifiant a été réattribué par
l'auto-incrément, sur une base nettoyée à la main. Cet état ne s'observe plus.

**Ce qui a quand même été fait**, et ce que cela vaut :

| | |
|---|---|
| Changement | `AnalysisReport::create()` → `firstOrCreate()` sur `verification_id`, plus un compteur « rapports sautés » |
| Ce que ça corrige | Une ligne déjà présente **saute** au lieu d'arrêter les 523 autres. Les transactions étant par enregistrement, un arrêt laisse un import à moitié fait, à reprendre à la main sur un serveur mutualisé. |
| Ce que ça ne corrige pas | Le doublon de production, puisque sa cause est inconnue. Ce n'est pas un correctif, c'est une garde. |
| Testé | L'idempotence, oui. La garde elle-même, **non** : fabriquer un rapport orphelin demande de désactiver la clé étrangère, et sous SQLite `PRAGMA foreign_keys` est sans effet dans une transaction. C'est écrit dans le fichier de test plutôt que contourné. |

### 1.2 Déployer le correctif du plan du site

`/sitemap.xml` rend 500 en production, neuf fois au journal :
`syntax error, unexpected identifier "version"`. Sur l'hébergement mutualisé
`short_open_tag=On`, et `<?xml` en tête de gabarit est lu comme une balise PHP.

**Le correctif est déjà écrit**, avec son commentaire d'explication, en première
ligne de `resources/views/public/plan-du-site.blade.php`. Il n'a jamais été
déployé — c'est la phase 0 qui le rend déployable.

Vérification : `curl -s https://verifall.com/sitemap.xml | head -1`.

### 1.3 Purger le planificateur de production

`There are no commands defined in the "activitylog" namespace.` Le planificateur
déployé appelle une commande d'un paquet **absent de tout le code local** —
`grep` sur `app/`, `config/`, `routes/`, `composer.json` : zéro occurrence.
Comparer le `routes/console.php` déployé aux **7 tâches** d'ici, et remplacer.

### 1.4 Réencoder les secrets de double authentification

`Secret de double authentification illisible : clé d'application changée ?` sur
`user_id 1`. L'`APP_KEY` de production a tourné sans réencodage. Le code sait
déjà traiter un secret illisible comme un facteur **absent** — personne n'est
verrouillé dehors —, mais l'avertissement restera tant que la ligne vit.

---

## Phase 2 — Remettre les moteurs en marche en production

C'est le cœur du produit, et il rend aujourd'hui « analyse reprogrammée ». Le
journal montre `Trop peu de moteurs ont pu répondre pour conclure honnêtement`,
tentatives 1, 2 et 3, sur deux vérifications.

Le comportement du code est le bon — pas de rapport creux, unités remboursées.
Ce sont **les moteurs qui ne répondent pas sur le serveur**.

L'outil existe et n'est pas à écrire : **`php artisan verifall:sonder-les-moteurs`**
et `SanteDesMoteurs`, qui interrogent chaque adaptateur sur `estConfigure()` et
`joignable()`. Écrits précisément parce que l'ancien `etat()` annonçait
« opérationnel » sur une clé vide.

| | |
|---|---|
| Fichiers | `.env` de production, `config/factcheck.php` |
| Migration | non |
| Sortie attendue | sept moteurs verts, ou la liste nommée des refus et leur motif (clé, quota, sortie réseau cPanel) |
| Régression | aucune côté code. Le risque est de conclure trop vite : `config:clear` après chaque changement de `.env`, sinon on mesure l'ancienne configuration. |

---

## Phase 3 — Refermer la chaîne d'exécution

Sans elle, l'inscription s'arrête à l'étape 2 et personne ne voit d'erreur.

Les **deux entrées cron** de `docs/CRONS.md` : `schedule:run` et
`queue:work --stop-when-empty --max-time=55`, chaque minute.

En local, la file porte **6 jobs en attente depuis 8 jours et 2 échoués**
(`OtpCodeMail`, SMTP `mail.verifall.com:587` en timeout). `MAIL_MAILER=log` en
développement supprime ce faux signal.

Vérification : `php artisan verifall:diagnostic` **vert**. Il sait déjà lire la
file, le courriel, le cache, la session, le planificateur et la mise en ligne.

---

## Phase 4 — Compléter l'API v1

53 endpoints servis, 130 tests. Cinq appels du simulateur mobile ne le sont pas,
et `verifall_app_flutter/lib/core/network/api_endpoints.dart:10` le déclare noir
sur blanc plutôt que d'écrire un client qui appelle du vide.

**Le motif du refus a changé pour trois d'entre eux :**

| Appel | Motif d'origine | Aujourd'hui |
|---|---|---|
| 3 × `notifications` | aucun magasin côté serveur | **la table `notifications` existe** (25 août) |
| `verifications/{uuid}/feedback` | aucun stockage | **`2026_09_01_200000_retour_et_contre_expertise` existe**, service `RetoursSurLeRapport` aussi |
| `certificates/{code}/pdf` | aucune bibliothèque PDF | **inchangé** — ni dompdf ni mpdf dans `composer.lock` |

| | |
|---|---|
| Fichiers | nouveau `NotificationController` + `NotificationResource` dans `app/Http/Api/V1/` ; `VerificationController` ; `routes/api.php` ; `api_endpoints.dart` ; `docs/kb/API_SPECIFICATION.md` §11 |
| Tables | `notifications`, `users.notification_preferences`, `retours_sur_le_rapport` |
| Migration | **non** |
| Réutilise | `PageCurseur`, `Enveloppe`, `CurseurValide`, `Idempotence`, seaux `api-lecture` / `api-ecriture` |
| Régression | Un **dépôt est obligatoire** ici — critère 1 (lecture partagée web/mobile) et critère 3 (volume non borné). Pas d'Eloquent depuis le contrôleur. Et le contrat §11 doit bouger dans le même commit, sinon document et code divergent. |

---

## Phase 5 — Durcissement et conformité

### 5.1 Content-Security-Policy

Le point le plus délicat du plan, et le seul qu'aucun test ne protège. Livewire
injecte du script en ligne, Alpine évalue des expressions : une CSP naïve casse
l'application **dans le navigateur, avec une suite verte**.

La méthode est imposée par la nature du problème :
`Content-Security-Policy-Report-Only` d'abord, collecte des violations, puis
passage en application — et une **passe au navigateur sur les 107 pages**. Jamais
une ligne ajoutée à `SecureHeaders` et un commit.

Le reste des en-têtes est déjà posé globalement (`bootstrap/app.php`) :
`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
`Cross-Origin-Opener-Policy`, `Permissions-Policy`, HSTS sur connexion chiffrée,
`Cache-Control: no-store` sur toute réponse authentifiée.

### 5.2 Sauvegardes

Dump MySQL + `storage/app/private`, chiffrés, hors serveur, et **une restauration
réellement rejouée sur une copie**. Une sauvegarde jamais restaurée n'est pas une
sauvegarde. `docs/MISE_EN_LIGNE.md` §4 décrit la répétition sur copie.

### 5.3 Audit RGPD

De bout en bout, contre `docs/SECURITE_RGPD.md`. L'essentiel est en place —
export, suppression en deux temps avec rétractation, purge planifiée,
`AuditLogService`. C'est une passe de vérification, pas de construction.

---

## Phase 6 — Les cinq moteurs restants *(décision avant code)*

Cinq restent à porter : `KnowledgeGraph`, `TinEye`, `Vision`, `DetecteurIA`,
`Transcription`. `Adaptateurs restants - alternatives.docx` chiffre déjà trois
scénarios. Le découpage qui commande la décision :

- **3 moteurs, clé disponible, aucune dépense** → portage mécanique : le contrat
  `AnalysisAdapterInterface` est le même pour tous, `AdaptateurHttp` porte déjà
  `estConfigure()` et `joignable()`.
- **`Transcription`** → rouvre les boutons **Vidéo et Audio** de l'écran de
  vérification citoyen, aujourd'hui inactifs avec un message d'indisponibilité.
  Whisper sur la clé OpenAI déjà payée.
- **6 moteurs** → abonnement ou clé à réémettre : **décision du porteur**, pas du
  développement.
- **`DetecteurIA`** → réserve écrite au dossier, et elle tient : ces détecteurs se
  trompent surtout contre les personnes qui n'écrivent pas dans leur langue
  maternelle. Sur une plateforme béninoise, à n'afficher qu'en indication
  interne, jamais dans le rapport public.

Même nature de décision pour le **PDF de certificat** de la phase 4.

---

## Phase 7 — Remettre les relevés d'aplomb

`docs/CONFORMITE_PROTOTYPE.md` mesure **43 écrans sur 107**, tous d'avant les
lots d'administration : ses pourcentages ne décrivent plus le produit.
`docs/RESTE_A_FAIRE.md` porte six compteurs faux (tableau en tête de page).

- Rejouer `scratchpad/comparer.py` et
  `AUDIT_CAPTURE=… php artisan test --filter=CaptureDesEcrans` sur les **107** pages.
- Recompter les six repères de `RESTE_A_FAIRE.md`.
- Redresser l'état Flutter.

**Le manque structurel à combler ici**, nommé par le projet lui-même : *rien ne
garantit qu'une route déclarée est atteinte depuis au moins un écran*. Quatre
fois en deux lots, une chaîne serveur complète n'a atteint aucun gabarit —
documents joints, leçons audio, SEO du Hub, prime d'XP — et chaque fois c'est
une passe au navigateur qui l'a vu, jamais la suite.

Sur le modèle des deux cliquets qui marchent déjà
(`PermissionsSansGardeTest`, `ClassesTailwindPresentesTest`), un test qui échoue
dès qu'une route nommée n'est référencée par aucune vue rendrait ces passes moins
nécessaires. **C'est le meilleur rapport valeur/coût du plan.**

---

## Phase 8 — Mise en ligne

`docs/MISE_EN_LIGNE.md` §0 impose un ordre, et une seule inversion annule tout le
reste : **`APP_DEBUG=false` AVANT de renouveler les clés**. Avec
`APP_DEBUG=true`, la première 500 divulgue les nouvelles clés le jour même.

Aujourd'hui `APP_ENV=local` avec `APP_URL=https://verifall.com` : l'un des deux
est faux, et le diagnostic le signale.

Point de sortie du plan : **`php artisan verifall:diagnostic` vert sur le serveur
de production**, section « Mise en ligne » comprise.

---

## Ordre résumé

**0** versionner → **1** trois pannes prod → **2** moteurs → **3** crons et file
→ **4** API v1 → **5** CSP, sauvegardes, RGPD → **6** moteurs restants *(décision)*
→ **7** relevés + cliquet des routes → **8** mise en ligne.

Les phases **6** et une partie de la **4** attendent des arbitrages qui
appartiennent au porteur du projet — budget des moteurs, bibliothèque PDF. Elles
ne bloquent aucune des autres.
