# Pack de documentation — Implémentation Laravel/Livewire Vérif'All

## Contenu (à placer à la racine du repo Laravel)
| Fichier | Rôle |
|---|---|
| `CLAUDE.md` | **À la racine du projet** — chargé automatiquement par Claude Code : contexte, règles non négociables, flux, préférences |
| `docs/MASTER_INSTRUCTIONS_ET_ARCHITECTURE.md` | Méthodologie, Definition of Done, arborescence DDD complète (Core/Infrastructure/Livewire/Jobs/Listeners/Enums/Notifications/Console/API/Helpers/Entities), contrat de l'Orchestrateur |
| `docs/SECURITE_RGPD.md` | Auth staff (login/logout), OTP, rate limiting, XSS/SQL/CSRF/uploads/SSRF, UUID, APP_DEBUG, audit & security logs, RGPD complet (consentements, export, effacement, rétention, chiffrement) |
| `docs/DESIGN_SYSTEM_NOTIFICATIONS_RBAC.md` | Conformité 100 % au prototype (front + admin), composants Blade/Livewire, toasts, notifications par domaine, RBAC/Policies |
| `docs/SEO_FACTCHECK_MODULES_QA.md` | SEO mondial (meta polymorphique, JSON-LD ClaimReview, sitemap), pipeline orchestrateur, cartographie des 8 sprints, sanity check |
| `docs/ADAPTATEURS_ORCHESTRATEUR_RAPPORT.md` | Les 19 adaptateurs (config API .env + rôles), gabarit de PROMPT d'algorithme fact-checking, scoring pondéré, **résolution des verdicts contradictoires** et de « Synthèse indisponible », Deep Research Harness (fan-out → fetch → vérification adverse → rapport cité), structure du RapportFact avec Sources & Preuves |
| `docs/UX_FLUTTER_LANCEMENT.md` | Pages d'erreurs stylées, seeders de test = données du prototype, confetti, visite guidée, **doc Flutter mobile complète** (Clean Arch, conversion Tailwind→Flutter, sync offline-first Laravel↔Flutter, FCM), et **LE mot de lancement Claude Code local** |

## Sources complémentaires (déjà dans ta base de connaissances — à copier dans docs/kb/)
`ROLE_ET_PERMISSIONS.md`, `03_DATABASE_ENGINEERING.md` (ERD + dictionnaire de données), `API_SPECIFICATION.md`, `GLOSSAIRE_DOMAINE.md`, `05_MODULE_SPECIFICATIONS.md`, `MICROCOPY.md` + `VOICE_AND_TONE.md` (dictionnaire copywriting), `SECURITY_AUDIT.md`, `MIGRATION_RUNBOOK.md`, `DEVOPS_DEPLOYMENT.md` — les docs de ce pack les référencent et ne les dupliquent pas.

## Démarrage Claude Code
1. `laravel new verifall && cd verifall` → copier `CLAUDE.md` à la racine, `docs/` à côté, et le prototype dans `resources/prototype/` (référence visuelle).
2. Première instruction : « Lis CLAUDE.md puis docs/, et exécute le Sprint 1 de MODULES_CARTOGRAPHIE en suivant la Definition of Done. »
3. Chaque fin de sprint : dérouler SANITY_CHECK_QA.md avant de passer au suivant.

## Dossier `instructions/` (27 fichiers granulaires)
Chaque instruction est un fichier autonome référençable par son nom exact dans les prompts Claude Code :
contenu éditorial & SEO (SEO_CONTENT_GUIDE) · modules citoyens écran par écran (MODULE_LMS_CITOYEN, MODULE_FORUM_CITOYEN) · exploitation (CRON_JOBS) · auth (LOGIN_DECONNEXION_USER_INSTRUCTION, SECURITE_LOGIN) · UX système (NOTIFICATIONS_SYSTEMS, TOAST, LAYOUT, PAGES_KPIS_UX_MOBILE_DESKTOP, RESPONSIVE_BEHAVIOR, MODAL_FORMULAIRE_CRUD, MODAL_DELETE_POPUP_WARNING) · conformité (STRICT_RESPECT_PAGE_STRUCTRE, PAGE_SECTION_ORDER, DICTIONNAIRE_COPYWRITING) · qualité (SANITY_CHECK_COMPLET, AUDIT_DE_CONCEPTION_ET_DE_STRUCTURE, REVUE_DE_CODE_SENIOR_QA_ARCHITECT) · données (DICTIONNAIRE_DONNEES_COMPLET, GLOSSAIRE) · projets Claude (SYSTEM_INSTRUCTIONS_*_LARAVEL_LIVEWIRE / *_FLUTTER) · mobile (CONVERT_TAILWIND_IN_DART_FLUTTER, SPRINT_COMPLETE_PROJECT_FLUTTER, SYNC_PROJECT_LARAVEL_LIVEWIRE_FLUTTER) · SPRINT_COMPLETE_PROJECT_LARAVEL_LIVEWIRE (détail des 8 sprints) · exploitation avancée : ENV_EXAMPLE_ANNOTE (45 variables commentées), PAIEMENT_MOBILE_MONEY (MTN/Moov, webhooks idempotents), INTEGRATION_ZOOM_LIVE_CLASSES, GENERATION_CERTIFICAT_PDF (QR + vérification publique), STRATEGIE_DE_TESTS (pyramide Pest, couverture 80 %), CI_CD_PIPELINE (zéro-downtime + rollback), CHANGELOG_ET_VERSIONING · **FILIGRANE_FAKE_DEMENTIS** (tampon FAKE double couche CSS+serveur sur les démentis, contrôle admin) · **SEEDERS_REELS** (reproduction exacte du prototype, certificat VERIF-D-H1ST-1926, filleuls ADDERLY7X9, garde anti-production).
