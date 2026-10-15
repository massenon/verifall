# CLAUDE.md — Vérif'All · Implémentation Laravel 11 + Livewire 3

## Contexte du projet
- **Projet** : Vérif'All — plateforme béninoise de fact-checking + Académie LMS + Hub d'Infos + Forum citoyen. « La boussole contre la désinformation numérique. »
- **Stack** : Laravel 12 (PHP 8.3+), Livewire 3, Alpine.js. **Laravel 11 exclu** : CVE d'injection CRLF (règle e-mail par défaut) sans correctif en 11.x — corrigée seulement en 12.60+/13.10+ ; l'installer exigerait de désactiver la politique de sécurité de Composer, contraire à SECURITE_RGPD et STRICT_AVOID.
- **Infra locale sans Redis** : pilotes base de données actés — `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database` (+ tables via `php artisan make:queue-table` etc.). Redis reste la cible en production (bascule = variables d'env uniquement, aucun code) ; Reverb fonctionne sans Redis en mono-serveur., Alpine.js, Tailwind CSS (build statique, config du prototype), MySQL 8, Redis (queues + cache), Laravel Reverb (temps réel), Quill 2 (éditeur riche).
- **Référence visuelle ABSOLUE** : le prototype HTML dans `web/` (106 pages). **No redesign, no structural modifications, no repositioning.** On transpose, on n'invente pas.
- **Architecture** : Clean Architecture / DDD simplifié — `Models → Repositories → Services → Livewire Components`. Domaines : FactChecking, Lms, Community, Hub, Identity, Economy, Gamification, Marketing.

## Règles de développement (NON NÉGOCIABLES)
1. **Jamais** de logique métier dans un composant Livewire ou un contrôleur : elle vit dans `app/Core/<Domaine>/Services`.
2. **Jamais** de requête Eloquent hors Repository. Interfaces dans `app/Core/Application/Contracts`, implémentations dans `app/Infrastructure/Persistence`.
3. **Jamais** `{!! !!}` sur données utilisateur. Contenu Quill : purifié via `mews/purifier` AVANT persistance ET à l'affichage.
4. **Jamais** d'ID auto-incrémenté exposé en URL : trait `HasUuids` sur tout modèle public, route model binding sur `uuid`/`slug`.
5. **Jamais** de secret en dur : tout passe par `.env` + `config/`. Vérifier `git diff` avant commit (aucun token/clé).
6. Enums PHP 8.3 natifs (`app/Core/<Domaine>/Enums`) pour tout statut/verdict — jamais de chaîne magique.
7. `$fillable` explicite sur chaque modèle (jamais `$guarded = []`).
8. Toute action admin sensible → `AuditLogService::record()` (voir SECURITE_RGPD.md).
9. Migrations : jamais modifier une migration déjà poussée ; toujours une nouvelle.
10. Tests avant livraison : `php artisan test` PASS + sanity front (0 lien cassé, 0 erreur JS console, 0 déséquilibre Blade).

## Flux d'exécution
- Install : `composer install && npm ci && cp .env.example .env && php artisan key:generate`
- Dev : `php artisan serve` + `npm run dev` + `php artisan queue:work` + `php artisan reverb:start`
- Qualité : `./vendor/bin/pint` (style) · `./vendor/bin/phpstan analyse --level=6` · `php artisan test`
- Tâches >3 fichiers : établir un plan AVANT d'écrire (lister fichiers touchés, ordre, risques).

## Préférences techniques
- `npm ci` (pas yarn). PHP 8.3 typé strict (`declare(strict_types=1);` partout).
- Toasts : composant Livewire global `<x-toast>` (voir NOTIFICATIONS_TOASTS.md) — PAS SweetAlert2 côté citoyen (le DS du prototype prime) ; SweetAlert2 autorisé UNIQUEMENT dans l'admin si conforme au style staff.
- Pagination serveur partout (`->paginate()`), recherche débouncée 350 ms (`wire:model.live.debounce.350ms`).
- Cache : Repositories avec `Cache::remember()` (TTL 5-15 min), invalidation par tags au write.
- Fichiers uploadés : disque `private`, nom aléatoire (`Str::uuid()`), validation `mimes|max`, jamais servis directement (route signée).

## Ordre de lecture des docs (chemins RÉELS du pack, à la racine + instructions/ + kb/)
1. `MASTER_INSTRUCTIONS_ET_ARCHITECTURE.md` — méthodologie + DoD + arborescence DDD
2. `SECURITE_RGPD.md` — sécurité + RGPD
3. `DESIGN_SYSTEM_NOTIFICATIONS_RBAC.md` + `RBAC_MATRICE_DERIVEE.md` + `kb/ROLE_ET_PERMISSIONS.md`
4. `SEO_FACTCHECK_MODULES_QA.md` — SEO, pipeline, cartographie des 8 sprints, sanity check
5. `ADAPTATEURS_ORCHESTRATEUR_RAPPORT.md` · `UX_FLUTTER_LANCEMENT.md`
6. `kb/03_DATABASE_ENGINEERING.md` + `instructions/DICTIONNAIRE_DONNEES_COMPLET.md` — données
7. `kb/API_SPECIFICATION.md` — contrat API (Sprint 8 + Flutter)
8. `instructions/*.md` — au fil des modules concernés (LMS, forum, toasts, modales, seeders, filigrane…)

## Ce qu'il faut éviter (STRICT_AVOID)
Vulnérabilités · bugs cachés/edge cases ignorés · spaghetti/architecture pauvre · dette technique · patterns incohérents · absence de tests · doc manquante · goulots de performance · conflits de dépendances · implémentations hallucinées · code dupliqué/mort · secrets commités · mauvaise scalabilité.
