# UX_TRANSVERSE_SEEDERS.md — Pages d'erreurs, seeders, confetti, visite guidée

## Pages d'erreurs (resources/views/errors/)
`403, 404, 419, 429, 500, 503` — style du front public (logo, canvas #F6F8F7, Instrument Sans), copywriting Vérif'All :
- 404 : « Cette page s'est égarée… mais pas vous. » + recherche + liens Dashboard/Accueil/Vérifier.
- 419 : « Session expirée par sécurité » + bouton Reconnecter. 429 : « Doucement ! Réessayez dans un instant. »
- 500/503 : « Nos équipes sont dessus » + statut système ; **jamais de stack trace** (APP_DEBUG=false).
Layout minimal autonome (pas de dépendance session), testé par `tests/Feature/ErrorPagesTest`.

## Seeders de test (database/seeders/) — reprennent LES données du prototype
`RolesAndPermissionsSeeder` → `StaffSeeder` (René admin, Mentor Koffi, mdp .env.testing) → `CitizenSeeder` (Adderly + 8 profils, unités 34/128…) → `TrustedSourcesSeeder` (gouv.bj, ARCEP, AFP Factuel…) → `CourseSeeder` (les 4 cours A-D : sections, leçons, quiz, exercices, cours D terminé + certificat VERIF-D-H1ST-1926) → `SignalementSeeder` (taxe WhatsApp, faux décret, 27 en file dont 4 prioritaires) → `HubSeeder` (6 actualités + portails + jobs) → `ForumSeeder` (5 threads, verdict officiel sur le décret) → `GamificationSeeder` (badges, streak 7 j) → `AuditLogSeeder`. `DemoSeeder` = tout ; `php artisan migrate:fresh --seed` reproduit le prototype à l'identique. Tests Pest : factories dédiées, jamais les seeders démo.

## Confetti (célébrations)
Module JS unique `resources/js/confetti.js` (canvas maison du prototype, zéro lib). Déclencheurs : quiz réussi, cours 100 % (+ écran « Vos gains » : +XP, +unités, badge, certificat), badge débloqué, certificat émis. Événement Livewire `celebrate` → `window.vfConfetti(preset)`. **Coupé par `prefers-reduced-motion`** ; jamais sur les échecs.

## Visite guidée (onboarding)
`resources/js/guided-tour.js` (spotlight maison du prototype) : 6 étapes citoyen (Vérifier → unités → Académie → forum → statistiques → profil), 5 étapes staff (sidebar → +Créer → notifications → À traiter → audit). Déclenchée au 1ᵉʳ login (`users.tour_done_at` null), relançable depuis Réglages/Aide, navigable clavier, Échap = quitter, progression `2/6` persistée.

---

# FLUTTER_MOBILE.md — Application mobile Vérif'All

## Stack & architecture (miroir du back)
Flutter 3.x (Dart 3, Material 3) · **Clean Architecture** : `lib/{core (theme, errors, network Dio+interceptors), domain (entities, repositories abstraits, usecases), data (models JSON, datasources API+local Drift, repositories impl), presentation (features/<module>/{bloc, pages, widgets})}` · Riverpod (state) · go_router (deep links `verifall://cours/{slug}`) · flutter_secure_storage (tokens — **jamais SharedPreferences**).

## CONVERT_TAILWIND_IN_FLUTTER (table de correspondance)
`AppColors` = les tokens exacts (primary 0xFF09B850, forest 0xFF063B2A, brandred 0xFFD6293E, ink/slate2/line/canvas, vtrue/vfalse/vinvest, staff 0xFF065F46/0xFF052E16/0xFF059669/0xFFD1FAE5) · `AppText` = Inter + Instrument Sans (google_fonts en assets locaux), échelle du DS · spacing = grille 4 (`Insets.x4=16`) · radius 16 cards / 12 boutons · cibles ≥44 (`minimumSize: Size(44,44)`) · composants 1:1 : VfKpiCard (+ExpansionTile accordéon), VfBadge (● sémantique), VfToast (SnackBar stylé), VfBottomSheet actions, VfOffcanvas (showModalBottomSheet plein), VfEmptyState, skeleton (shimmer), bottom-nav 5 entrées citoyenne.

## API & SYNC (SYNC_PROJECT_LARAVEL_FLUTTER)
API `/api/v1` (Sanctum Bearer, refresh silencieux, 401 → re-login). **Offline-first lecture** : cache Drift des GET (cours, hub, mes vérifications) avec `updated_at` ; écriture en file locale `pending_ops (uuid client, op, payload, created_at)` rejouée à la reconnexion — idempotence par uuid client côté Laravel (contrainte unique). Conflits : last-write-wins + journal. Temps réel : Reverb via `pusher_channels_flutter` (verdict prêt, notification). Push : FCM ; chaque notification Laravel implémente `toFcm()`.

## Parcours mobiles prioritaires (sprints)
1 Auth+OTP+consentements RGPD · 2 Vérifier (upload caméra/galerie, coût annoncé, suivi temps réel, rapport + partage image 9:16) · 3 LMS (catalogue, lecture leçons + vidéo, quiz chronométré, confetti, certificat PDF, reprise) · 4 Hub+Forum · 5 Profil/unités (recharge Mobile Money via deep-link opérateur)/statistiques/parrainage (QR natif). Qualité : golden tests des composants DS, `flutter analyze` 0 issue, ≥60 fps listes (pagination + cached_network_image).

---

# LANCEMENT_CLAUDE_CODE.md — Démarrage local

## Prérequis (une fois)
```bash
# Node ≥18, PHP 8.3, Composer, MySQL 8, Redis
npm install -g @anthropic-ai/claude-code
laravel new verifall && cd verifall
# Copier : CLAUDE.md (racine) · docs/ (ce pack) · docs/kb/ (les .md de la base de connaissances)
# · resources/prototype/ (le contenu de web/ du zip prototype — référence visuelle)
cp .env.example .env && php artisan key:generate   # remplir DB_*, REDIS_*, MAIL_* ; clés API des adaptateurs plus tard
```

## Lancer Claude Code
```bash
cd verifall
claude
```
(`claude` lit automatiquement `CLAUDE.md`. Utile : `/init` pour l'enrichir, `/memory` pour les préférences, `/plan` avant toute tâche >3 fichiers, `/clear` entre modules.)

## LE MOT DE LANCEMENT (première instruction à coller)
```
Lis CLAUDE.md puis tous les fichiers de docs/ dans l'ordre indiqué, ainsi que docs/kb/03_DATABASE_ENGINEERING.md et docs/kb/ROLE_ET_PERMISSIONS.md. Ne code rien avant d'avoir terminé la lecture.

Ensuite exécute le SPRINT 1 (Socle) de docs/SEO_FACTCHECK_MODULES_QA.md §MODULES_CARTOGRAPHIE, en mode plan d'abord :
1) liste les fichiers à créer, migrations, et risques ; attends ma validation ;
2) implémente couche par couche selon MASTER_INSTRUCTIONS (DDD strict, sécurité SECURITE_RGPD.md, design 100 % conforme à resources/prototype/) ;
3) termine par la Definition of Done complète + SANITY_CHECK_QA.md et un rapport honnête de ce qui est fait/partiel.

Règles permanentes : jamais de secret en dur, jamais de redesign, toasts sur chaque action, tests avant livraison.
```
Puis, sprint après sprint : `Exécute le SPRINT N en suivant la même procédure. Commence par le plan.`
