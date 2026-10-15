# CLAUDE.md — Vérif'All · Implémentation Laravel 12 + Livewire 3

> Le titre disait « Laravel 11 » alors que le corps du document explique depuis
> le début pourquoi c'est Laravel 12. Corrigé le 31 août 2026.
>
> Le `CLAUDE.md` de la racine `C:\Projects\verifall\` était périmé sur quatre
> points (Laravel 11, prototype dans `web/`, `npm ci`, Redis) — et c'est lui
> qui était chargé comme instructions à chaque session. **Réduit à un renvoi
> vers ce fichier le 1er septembre 2026** ; sa version d'origine est archivée
> dans `docs/archives/CLAUDE-racine-perime-2026-09-01.md`.

## Contexte du projet
- **Projet** : Vérif'All — plateforme béninoise de fact-checking + Académie LMS + Hub d'Infos + Forum citoyen. « La boussole contre la désinformation numérique. »
- **Stack réellement installée** : **Laravel 12.67** (PHP 8.3), **Livewire 3.8**, Alpine.js,
  Tailwind CSS (build statique, config du prototype), MySQL 8,
  Quill 2 (éditeur riche), spatie/laravel-permission 6, mews/purifier 3, Sanctum 4.
  - *Laravel 12 et non 11* : **toutes** les versions 11.x sont affectées par l'avis « CRLF
    injection in default email rule », corrigé uniquement en 12.60+/13.10+ ; la branche 11
    est hors support actif et ne recevra pas de correctif. Composer refuse d'ailleurs de
    l'installer. Livewire 3.8 déclare `illuminate/support: ^10|^11|^12|^13` — la cible
    Livewire 3 du pack est donc intégralement respectée.
  - *Pilotes base de données et non Redis* : Redis n'est pas disponible sur le poste (ni
    extension PHP, ni serveur). `CACHE_STORE=database`, `QUEUE_CONNECTION=database`,
    `SESSION_DRIVER=database`. Le basculement vers Redis se fera par `.env` seul, sans
    changement de code.
  - *Pas de Reverb, pas de WebSockets* : **le paquet n'est pas installé.** Absent de
    `composer.json`, aucun événement `ShouldBroadcast`, pas de `routes/channels.php`,
    `BROADCAST_CONNECTION=log`. Le temps réel se fait au **`wire:poll`** de Livewire,
    déjà à six endroits — et c'est la bonne réponse ici : le déploiement visé est un
    **cPanel mutualisé**, où aucun processus long ne peut tourner. Ce document
    annonçait Reverb depuis l'origine et prescrivait `reverb:start` ; vérifié et
    corrigé le 1er septembre 2026.
- **Référence visuelle ABSOLUE** : le prototype HTML, **`resources/prototype/` — 107 pages**
  (46 administration, 13 mentor, le reste public et citoyen). **No redesign, no structural
  modifications, no repositioning.** On transpose, on n'invente pas.
  - Ce document annonçait `web/` **et ce dossier n'existe pas** — même erreur que celle
    corrigée à la racine le même jour. Chemin vérifié et compte recomposé le
    1er septembre 2026 (`find resources/prototype -name '*.html' | wc -l`).
  - Le relevé de fidélité écran par écran est `docs/CONFORMITE_PROTOTYPE.md`.
- **Architecture** : Clean Architecture / DDD simplifié — `Models → Repositories → Services → Livewire Components`. Domaines : FactChecking, Lms, Community, Hub, Identity, Economy, Gamification, Marketing.

## Règles de développement (NON NÉGOCIABLES)
1. **Jamais** de logique métier dans un composant Livewire ou un contrôleur : elle vit dans `app/Core/<Domaine>/Services`.
2. **Dépôt obligatoire dès qu'une lecture est partagée, cachée ou volumineuse** —
   arbitré le 31 août 2026 (lot 0), voir l'encadré « Quand écrire un dépôt » plus bas.
   Interfaces dans `app/Core/Application/Contracts`, implémentations dans
   `app/Infrastructure/Persistence`.
3. **Jamais** `{!! !!}` sur données utilisateur. Contenu Quill : purifié via `mews/purifier` AVANT persistance ET à l'affichage.
4. **Jamais** d'ID auto-incrémenté exposé en URL : trait `HasUuids` sur tout modèle public, route model binding sur `uuid`/`slug`.
5. **Jamais** de secret en dur : tout passe par `.env` + `config/`. Vérifier `git diff` avant commit (aucun token/clé).
6. Enums PHP 8.3 natifs (`app/Core/<Domaine>/Enums`) pour tout statut/verdict — jamais de chaîne magique.
7. `$fillable` explicite sur chaque modèle (jamais `$guarded = []`).
8. Toute action admin sensible → `AuditLogService::record()` (voir SECURITE_RGPD.md).
9. Migrations : jamais modifier une migration déjà poussée ; toujours une nouvelle.
10. Tests avant livraison : `php artisan test` PASS + sanity front (0 lien cassé, 0 erreur JS console, 0 déséquilibre Blade).

## Quand écrire un dépôt — arbitrage du 31 août 2026

La règle 2 disait « **jamais** d'Eloquent hors dépôt ». Elle n'a jamais été
appliquée : **12 dépôts pour 67 modèles**, et les deux écrans les plus récents
— `Staff\Opportunites`, `Staff\Parametres` — requêtent `HubJob` et `Setting`
directement depuis le composant. Un interdit que le code viole partout
n'informe personne ; il apprend seulement à ne pas lire les règles.

L'arbitrage était pressant : il reste **31 écrans d'administration** à écrire.
Les livrer dans le style actuel figerait l'écart ; exiger 31 dépôts doublerait
le coût de chaque écran pour des CRUD de référentiel à dix lignes.

**Un dépôt est OBLIGATOIRE dès qu'un des quatre critères est vrai :**

1. la lecture est **partagée** entre le web et l'API mobile (`app/Http/Api/V1/`) ;
2. elle est **cachée** (`Cache::remember`) — le cache et son invalidation ne se
   dupliquent pas dans deux composants ;
3. elle porte sur un **volume non borné** (utilisateurs, vérifications, journaux,
   copies d'examen) ;
4. elle compose **plusieurs agrégats** ou nourrit un tableau de bord.

**Eloquent direct est toléré** pour un CRUD de référentiel à faible volume,
lu depuis un seul écran : catégories, FAQ, partenaires, témoignages, badges,
mots surveillés. Le jour où l'un d'eux tombe sous un critère ci-dessus, il
gagne son dépôt — et pas avant.

Ce qui n'est **pas** négociable dans les deux cas : `$fillable` explicite,
pagination serveur, purification du HTML, `AuditLogService` à l'écriture.

## Le patron d'écran d'administration

`app/Livewire/Staff/Opportunites.php` (444 l.) et sa vue (302 l.) sont le
**gabarit de référence** des écrans staff. Un nouvel écran s'en clone plutôt
qu'il ne s'invente — c'est ce qui garde 31 écrans cohérents entre eux.

Ce qu'il faut en reprendre : filtres et recherche en `#[Url]` (état dans
l'adresse, donc partageable), `WithPagination`, formulaire en `<x-offcanvas-form>`,
suppression par `<x-delete-modal>`, retour par `InteractsWithToasts`, garde de
route `can:` adossée à une Policy, et **journalisation de chaque écriture**.

Les neuf composants partagés existent déjà et ne sont pas à recréer :
`<x-data-table>`, `<x-offcanvas-form>`, `<x-delete-modal>`, `<x-staff.menu-actions>`,
`<x-kpi-grid>`, `<x-kpi>`, `<x-empty-state>`, `<x-badge-statut>`, `<x-skeleton>`.

Les permissions non plus : `app/Support/Rbac/MatricePermissions.php` **déclare
déjà** celles de la quasi-totalité des écrans restants (`faq.*`, `partner.*`,
`testimonial.*`, `user.*`, `kyc.*`, `role.*`, `system.*`, `maintenance.*`…),
et elles sont semées. Un nouvel écran écrit sa Policy et sa garde de route —
il n'invente pas de permission.

## Flux d'exécution
- Install : `composer install && cp .env.example .env && php artisan key:generate`
- Dev : `php artisan serve` + `php artisan queue:work`

  > **`PHP_CLI_SERVER_WORKERS=8` devant `serve`**, sinon le service worker
  > (`/sw.js`) occupe l'unique processus et `/livewire/update` met trente
  > secondes. Ce n'est pas un défaut du produit, mais il fige le navigateur.

  > **En hébergement MUTUALISÉ (cPanel), aucun démon ne tourne.** `queue:work`
  > en processus permanent est impossible ; il faut deux entrées cron, chaque
  > minute :
  >
  > ```
  > * * * * * cd /chemin/du/site && php artisan schedule:run >/dev/null 2>&1
  > * * * * * cd /chemin/du/site && php artisan queue:work --stop-when-empty --max-time=55 >/dev/null 2>&1
  > ```
  >
  > Sans elles, **tout ce que décrit l'encadré ci-dessous s'arrête en silence**.
  > La carte « Santé du système » du tableau de bord affiche justement la
  > dernière exécution du planificateur et l'âge du plus ancien job en attente :
  > c'est là que le manque se voit.

  > **`queue:work` n'est pas facultatif — sans lui, le produit ne fonctionne pas.**
  >
  > `QUEUE_CONNECTION=database`. Tout ce qui suit passe par la file et **s'arrête
  > net** sans worker, sans qu'aucune erreur ne le signale :
  >
  > | Ce qui tombe | Point de départ |
  > |---|---|
  > | **L'analyse d'une vérification** — le cœur du produit | `AnalyserVerification::dispatch()`, appelé depuis 6 endroits (`Citizen\Verifier`, `Public\VerifierInvite`, l'API, l'orchestrateur, la tipline) |
  > | Le code OTP d'inscription | `InscriptionCitoyen::envoyerLeCode()` |
  > | Le code de réinitialisation de mot de passe | `ReinitialisationMotDePasse` |
  > | Les codes de suppression de compte et de changement d'adresse | `Citizen\Reglages`, `Citizen\Profil` |
  > | L'export RGPD | `PreparerExportDonnees::dispatch()` |
  > | La carte de démenti | `EngendrerLaCarteDeDementi::dispatch()` |
  > | Confirmation d'infolettre, accusé de message de contact | `ConfirmationAbonnement`, `MessageDeContactRecu` |
  >
  > **Rien ne casse :** pas d'exception, pas de journal d'erreur, la page répond
  > 200, la suite de tests reste verte. Le job attend simplement en table `jobs`.
  >
  > C'est l'explication du symptôme « l'inscription s'arrête à la deuxième
  > étape » : l'écran affiche « saisissez le code reçu » et la personne attend un
  > courriel qui ne partira jamais. Le formulaire n'a aucun défaut.
  >
  > À ne pas confondre avec le **planificateur**, qui est un second mécanisme
  > indépendant : les 6 tâches de `routes/console.php` (dotation mensuelle, purge
  > RGPD, réconciliation des paiements…) exigent une entrée cron appelant
  > `schedule:run`, et non un worker.
  >
  > En cas de doute : **`php artisan verifall:diagnostic`**. Elle dit l'état de
  > la file, du transport de courriel, du cache, de la session et du
  > planificateur, ne modifie rien, et sort en échec dès qu'un point est rouge.
  >
  > En local, `MAIL_MAILER=log` évite en outre d'attendre un SMTP distant : les
  > messages atterrissent dans `storage/logs/laravel.log`, lisibles tout de suite.

  > **Pas de `npm ci`, pas de `npm run dev` — et ce n'est pas un oubli.**
  > Ces deux commandes figuraient ici et **échouaient** : il n'existe aucun
  > `package-lock.json`, et `node_modules/` est vide. Vérifié le 25 août : aucun
  > gabarit n'appelle `@vite`. Les six gabarits chargent
  > `public/assets/css/tailwind.css` — **un build statique de 48 Ko, figé, repris
  > du prototype**, comme l'annonce le contexte du projet. La chaîne Vite du
  > squelette Laravel est donc dormante, et `package.json` n'est que du décor.
  >
  > La conséquence pratique tient en une phrase, et elle vaut d'être connue :
  > **une classe Tailwind absente de ce fichier n'a aucun effet.** On ne peut pas
  > « ajouter une classe » — on transpose avec celles qui existent. C'est
  > exactement ce que la référence visuelle absolue exige.
  >
  > Ranimer Vite serait un chantier à part entière (regénérer le CSS depuis la
  > config du prototype, et prouver que le rendu n'a pas bougé sur 107 pages).
  > Tant qu'il n'est pas fait, cette section décrit ce qui marche.
- Qualité : `./vendor/bin/pint` (style) · **`./vendor/bin/phpstan analyse`** (niveau 6, vert) · **`php artisan test --parallel`** · `php artisan verifall:diagnostic`

  > **`--parallel`, et pas par coquetterie** : la suite tourne en **11 min sur
  > 4 processus** et dépasse **20 min en série** (mesuré le 31 août 2026, 1 386
  > tests). `paratest` est déjà dans `vendor/bin` — rien à installer. Réservez la
  > forme simple au débogage d'un test isolé : une barre qu'on attend vingt
  > minutes est une barre qu'on saute.

  > **Après toute chaîne ajoutée dans une vue** :
  > `php artisan test --filter=CouvertureTraduction` — quelques secondes, avant
  > la suite complète. Ce test part des VUES et remonte vers `lang/` ; il attrape
  > ce qu'une comparaison `fr` ↔ `en` ne peut pas voir, à savoir une clé absente
  > **des deux** langues. Sans lui, l'écran affiche la clé brute en clair. Il a
  > cadré exactement ce cas au lot 1.

  > **`./vendor/bin/phpstan analyse` — la barre est franchie depuis le 1er
  > septembre 2026.** Elle ne l'avait **jamais** été : la commande figurait ici
  > depuis l'origine, le binaire n'existait pas, et personne ne pouvait s'en
  > apercevoir. `larastan/larastan` 3.10 est installé, `phpstan.neon` est écrit,
  > le niveau est bien **6**, et la commande sort **verte**.
  >
  > Un réglage a décidé de tout, et il mérite d'être connu avant de toucher au
  > fichier : **`parseModelCastsMethod: true`**. Larastan vaut `false` par
  > défaut et lit alors les `$casts` sous leur forme **propriété** ; ce projet
  > déclare partout la forme **méthode** de Laravel 11
  > (`protected function casts(): array`). Sans ce réglage, larastan se rabat
  > sur le schéma : un enum redevient `string`, un `datetime` redevient
  > `string`. La première passe a rendu **353 erreurs, dont 173 n'étaient que
  > cela**. Les corriger une à une aurait été corriger une mauvaise
  > configuration **dans le code**.
  >
  > **`phpstan-baseline.neon` est un cliquet, pas un tapis.** Il fige **95**
  > imprécisions de typage préexistantes — aucune n'est un bug d'exécution : ce
  > sont des annotations trop larges ou trop étroites, exactement ce que le
  > niveau 6 sert à débusquer. Même règle que `ClassesTailwindPresentesTest` :
  > **la liste ne doit que rétrécir.** Tout code nouveau est analysé pleinement,
  > et `reportUnmatchedIgnoredErrors: true` force à retirer une entrée dès
  > qu'elle ne correspond plus. **Régénérer le fichier d'un bloc pour faire
  > passer une erreur neuve viderait le dispositif de son sens** — c'est le seul
  > geste interdit ici.
  >
  > Les quatre exceptions de `ignoreErrors` sont d'une autre nature : ce sont
  > des **artefacts d'outil**, chacun vérifié sur le code réel avant d'être posé
  > et justifié en commentaire dans `phpstan.neon`. Deux méritent d'être citées,
  > parce que « corriger » l'erreur aurait cassé le produit : le `catch
  > (DecryptException)` de `TwoFactorService` **n'est pas mort** — le
  > déchiffrement a lieu à l'accès à l'attribut casté `encrypted`, donc bien
  > dans le `try` ; et les `?->` que l'analyseur juge redondants protègent aussi
  > le cas d'un modèle chargé par un `select()` partiel.
- Tâches >3 fichiers : établir un plan AVANT d'écrire (lister fichiers touchés, ordre, risques).

## Préférences techniques
- Si la chaîne Vite est un jour ranimée : `npm ci` (pas yarn) — aujourd'hui
  elle est dormante, voir le flux d'exécution ci-dessus.
- PHP 8.3 typé strict (`declare(strict_types=1);` partout).
- Toasts : composant Livewire global `<x-toast>` (voir NOTIFICATIONS_TOASTS.md) — PAS SweetAlert2 côté citoyen (le DS du prototype prime) ; SweetAlert2 autorisé UNIQUEMENT dans l'admin si conforme au style staff.
- Pagination serveur partout (`->paginate()`), recherche débouncée 350 ms (`wire:model.live.debounce.350ms`).
- Cache : Repositories avec `Cache::remember()` (TTL 5-15 min), invalidation par tags au write.
- Fichiers uploadés : disque `private`, nom aléatoire (`Str::uuid()`), validation `mimes|max`, jamais servis directement (route signée).

## Ordre de lecture des docs (dans docs/)
Les 10 documents logiques du pack sont regroupés dans 6 fichiers physiques.
Les noms ci-dessous sont les **chemins réels** — ceux qui existent sur le disque.

1. `docs/MASTER_INSTRUCTIONS_ET_ARCHITECTURE.md` — méthodologie + Definition of Done,
   puis arborescence DDD complète et contrat de l'Orchestrateur *(= MASTER_INSTRUCTIONS + ARCHITECTURE_DDD)*
2. `docs/SECURITE_RGPD.md` — checklist sécurité + conformité RGPD
3. `docs/DESIGN_SYSTEM_NOTIFICATIONS_RBAC.md` — transposition prototype → Blade/Livewire,
   toasts et notifications, rôles et permissions *(= DESIGN_SYSTEM_CONFORMITE + NOTIFICATIONS_TOASTS + RBAC_ROLES_PERMISSIONS)*
4. `docs/SEO_FACTCHECK_MODULES_QA.md` — SEO, cerveau IA, cartographie des 8 sprints,
   barre de qualité *(= SEO_PERFORMANCE + FACTCHECK_ORCHESTRATOR + MODULES_CARTOGRAPHIE + SANITY_CHECK_QA)*
5. `docs/ADAPTATEURS_ORCHESTRATEUR_RAPPORT.md` — les 19 adaptateurs, gabarit de prompt,
   scoring, résolution des contradictions, Deep Research Harness
6. `docs/UX_FLUTTER_LANCEMENT.md` — pages d'erreurs, seeders, confetti, visite guidée,
   doc Flutter, mot de lancement

Puis, **selon le module en cours**, l'instruction granulaire correspondante dans
`docs/instructions/` (36 fichiers, index dans `docs/README_PACK.md`). Chacune est
autonome et se cite par son nom exact dans un prompt.

> `docs/kb/` ne contient qu'un document, et il a été ÉCRIT ICI :
> `API_SPECIFICATION.md`, le contrat d'API v1 (sprint 8), servi par
> `routes/api.php` et `app/Http/Api/V1/`. Les deux autres documents de la base
> de connaissances n'ont jamais été fournis ; chacun a un substitut :
> `docs/instructions/DICTIONNAIRE_DONNEES_COMPLET.md` pour
> `03_DATABASE_ENGINEERING.md`, et la section RBAC de
> `docs/DESIGN_SYSTEM_NOTIFICATIONS_RBAC.md` pour `ROLE_ET_PERMISSIONS.md`.
>
> Deux relevés tiennent l'état réel du chantier, et se refont par commande :
> `docs/CONFORMITE_PROTOTYPE.md` (fidélité au prototype, écran par écran) et
> `docs/RESTE_A_FAIRE.md` (ce qui reste, chiffré).

## Ce qu'il faut éviter (STRICT_AVOID)
Vulnérabilités · bugs cachés/edge cases ignorés · spaghetti/architecture pauvre · dette technique · patterns incohérents · absence de tests · doc manquante · goulots de performance · conflits de dépendances · implémentations hallucinées · code dupliqué/mort · secrets commités · mauvaise scalabilité.
