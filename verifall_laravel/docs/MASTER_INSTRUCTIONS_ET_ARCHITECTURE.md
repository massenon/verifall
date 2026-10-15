# MASTER_INSTRUCTIONS.md — Méthodologie & Définition of Done

## Rôle de Claude Code
Développeur senior Laravel/Livewire du projet Vérif'All. Tu transposes le prototype HTML validé (106 pages) en application fonctionnelle, sans jamais dévier du design ni de l'architecture DDD. Tu documentes, tu testes, tu es honnête sur ce qui est partiel.

## Méthodologie par tâche
1. **Lire** la spec du module (MODULES_CARTOGRAPHIE.md + la page prototype correspondante dans `web/`).
2. **Planifier** si >3 fichiers : lister fichiers, ordre, migrations nécessaires, risques.
3. **Implémenter** couche par couche : Migration → Model+Enum → Repository (interface+impl) → Service → Livewire Component → Blade (copie conforme du prototype) → Tests.
4. **Vérifier** : sanity check complet (voir SANITY_CHECK_QA.md) avant de déclarer terminé.
5. **Documenter** : chaque Service a un docblock métier ; chaque module ajoute sa section au CHANGELOG.

## Définition of Done (par module)
- [ ] Migrations + seeders réalistes (données béninoises du prototype)
- [ ] CRUD complet : liste (recherche débouncée + filtres + chips + bulk + export CSV), création/édition (offcanvas ou modale conforme), suppression (modale d'avertissement nominative + annulation), import avec sample
- [ ] Sous-onglets fonctionnels, KPI avec accordéon mobile, DataTable → cards mobile
- [ ] Toasts sur chaque action ; états loading (skeleton) / empty (CTA) / error (retry)
- [ ] Policies + tests d'autorisation (un Mentor ne voit QUE ses ressources)
- [ ] Audit log sur créer/modifier/supprimer/publier
- [ ] `php artisan test` PASS · Pint clean · PHPStan level 6 clean
- [ ] Zéro erreur JS console · zéro lien cassé · Lighthouse ≥ 90 sur les pages publiques

## Règles de qualité transverses
- Validation : FormRequest ou `rules()` Livewire avec messages français ; validation temps réel (`wire:model.blur`).
- Jamais de N+1 : `with()` systématique, vérifié par `Model::preventLazyLoading()` en local.
- Une PR/commit = un module ou un correctif, message conventionnel (`feat(lms): …`).

---

# ARCHITECTURE_DDD.md — Clean Architecture Vérif'All

## Arborescence
```
app/
├── Core/                              # LE DOMAINE (aucune dépendance framework sauf contrats)
│   ├── Application/Contracts/         # Interfaces Repositories (SignalementRepositoryInterface…)
│   ├── FactChecking/
│   │   ├── Entities/                  # Objets métier purs si besoin (ValueObjects : Verdict, ConfidenceScore)
│   │   ├── Enums/                     # SignalementStatus, SignalementVerdict, AnalysisEngine
│   │   ├── Services/                  # FactCheckOrchestrator, TextPreProcessor, ImageHasher, FactCheckScoringService
│   │   └── Events/                    # SignalementCreated, VerdictPublished
│   ├── Lms/          (Enums: CourseStatus, EnrollmentStatus, QuizAttemptResult · Services: EnrollmentService, ProgressionService, CertificateService, QuizGradingService)
│   ├── Community/    (Forum : ThreadStatus, ModerationLevel · Services: ThreadService, OfficialVerdictLinker)
│   ├── Hub/          (ArticleVerdict · Services: HubEditorialService)
│   ├── Identity/     (Services: OtpService, KycService, ConsentService, AccountErasureService)
│   ├── Economy/      (Services: EconomyService — unités, recharges Mobile Money, remboursements atomiques)
│   ├── Gamification/ (Services: XpService, BadgeService, StreakService)
│   └── Marketing/    (Services: ReferralService, CampaignService, NewsletterService)
├── Infrastructure/
│   ├── Persistence/                   # EloquentSignalementRepository… (implémente les Contracts)
│   ├── Adapters/FactChecking/         # GeminiAdapter, DeepSeekAdapter, OpenAISearchAdapter, GoogleFactCheckAdapter,
│   │                                  # KnowledgeGraphAdapter, GoogleSafeBrowsingAdapter, TechnicalAnalysisAdapter,
│   │                                  # GptZeroAdapter, WinstonAiAdapter, GoogleVisionAdapter, TinEyeAdapter,
│   │                                  # GoogleReverseImageAdapter, AiOrNotAdapter, GoogleSTTAdapter, WhisperAdapter,
│   │                                  # PerplexitySearchAdapter, GeminiSummaryAdapter, GoogleCustomSearchAdapter,
│   │                                  # SearchConsoleAdapter — TOUS implémentent AnalysisAdapterInterface
│   └── Security/                      # UrlSandbox, AuditLogger, RateLimiterConfig
├── Models/                            # Eloquent (HasUuids, $fillable, casts vers Enums)
├── Livewire/
│   ├── Citizen/  (Dashboard, Verifier, MesVerifications, Catalogue, Cours, Lecon, Quiz, Forum, Statistiques…)
│   ├── Staff/Admin/  et  Staff/Mentor/
│   └── Shared/ (Toast, KpiAccordion, DataTable, OffcanvasForm, DeleteModal, SearchFilters)
├── Http/Controllers/Api/V1/           # API mobile Flutter (Sanctum) — mêmes Services, jamais de logique dupliquée
├── Jobs/         (RunFactCheckAnalysis, GenerateCertificatePdf, SendWeeklyNewsletter, GenerateImageHashes)
├── Listeners/    (AwardXpOnLessonCompleted, NotifyMentorOnSubmission, LinkOfficialVerdictToThread)
├── Notifications/ (FactChecking/, Lms/, Forum/, Mentor/, Admin/ — database + mail + broadcast)
├── Console/Commands/ (CleanupEvidences, LmsRemindStalledUsers, ResetMonthlyUnits, SimulateFactCheck)
├── Helpers/      (format_units(), verdict_badge(), fr_date())
├── Enums/        (Global : UserRole, NotificationChannel)
└── Policies/     (CoursePolicy, SignalementPolicy… — LE contrôle définitif)
```

## Règles de dépendance
- `Core` ne connaît ni Eloquent ni Livewire : il parle aux **Contracts**. Binding dans `AppServiceProvider`.
- `Livewire` appelle **uniquement** des Services. Un composant = orchestration UI + validation + toast.
- Les Adaptateurs sont **interchangeables** : `AnalysisAdapterInterface { analyze(EvidenceBag $input): AdapterVerdict }` ; timeouts + retry (2×) + circuit-breaker simple (désactivation 5 min après 3 échecs, statut visible dans Santé du Système).

## FactCheckOrchestrator — contrat
Pipeline atomique : `lock (Cache::lock signalement:{uuid}, 120s)` → préprocessing (TextPreProcessor/ImageHasher) → sandbox URL (UrlSandbox) → fan-out adaptateurs pertinents par type (Job queue `analysis`, parallèle) → agrégation FactCheckScoringService (pondération par fiabilité moteur) → verdict Enum validé → rapport persisté → événement `VerdictPublished` → débit unités via EconomyService (transaction DB, remboursement si échec pipeline). Race conditions interdites : toute mutation de statut passe par `SignalementStatus::transitions()` (machine à états).

## API (app/Http/Controllers/Api/V1)
Sanctum tokens (mobile Flutter), versionnée `/api/v1`, Resources JSON, mêmes Policies. Rate limit `throttle:api` 60/min + limiteurs dédiés (login 5/min/IP, OTP 3/10min/identité). Voir API_SPECIFICATION.md de la base de connaissances pour les endpoints.
