# SEO_PERFORMANCE.md — SEO de rang mondial

## Système de métadonnées polymorphique
Table `seo_meta` (metable_type, metable_id, title ≤60, description ≤160, canonical, og_image, robots, json_ld) + trait `HasSeoMeta`. Composant `<x-seo>` dans le layout public : title/description/canonical, **OG + Twitter dynamiques**, hreflang fr. Fallbacks intelligents par type (article → titre+extrait+cover).

## Données structurées JSON-LD
- `ClaimReview` sur chaque fact-check public (claim, verdict, auteur Vérif'All, date) — c'est LE levier Google Fact Check.
- `Course` (Académie), `Article`/`NewsArticle` (blog + Hub), `FAQPage`, `Organization` + `WebSite` (SearchAction), `BreadcrumbList` partout.

## Technique
- Sitemap automatisé (spatie/laravel-sitemap, commande quotidienne, ping Search Console via SearchConsoleAdapter) + robots.txt (staff disallow).
- Hiérarchie H1-H4 stricte (déjà dans le prototype — la préserver), alt/title obligatoires sur chaque image (validation à l'upload admin), slugs propres, 301 sur changements de slug (table `redirects`).
- Perf (Lighthouse >90, chargement <2 s) : fonts préchargées + `font-display: swap` (déjà fait), Tailwind statique minifié, lazy loading images (`loading="lazy"` + dimensions), cache Repository `remember`, HTTP cache public sur pages froides, images WebP (Intervention au upload), Vite build minifié, pas de CDN tiers.
- Tracking vues blog **unique par IP** (hash IP salé, RGPD-safe) via middleware `TrackPageVisit` → Dashboard Analytics (stats visites/jour/page — alimente les widgets du dashboard admin).

---

# FACTCHECK_ORCHESTRATOR.md — Le cerveau hybride

## Contrat
`FactCheckOrchestrator` (Core\FactChecking\Services) reçoit un `Signalement` (uuid, type Texte|URL|Image|Vidéo|Audio, payload) et produit un `AnalysisReport` + verdict `SignalementVerdict` (Enum : VRAI, FAUX, TROMPEUR, NON_VERIFIABLE).

## Pipeline (Clean Pipeline + Atomic Locking)
1. `Cache::lock("factcheck:{uuid}", 120)` — anti race condition ; échec = job re-dispatché.
2. Machine à états `SignalementStatus` (EN_FILE → EN_ANALYSE → TERMINE|ECHEC) — transitions validées, jamais d'update direct.
3. Pré-traitement : `TextPreProcessor` (normalisation, langue, claims extraits), `ImageHasher` (pHash → déduplication contre la base), `UrlSandbox` (anti-SSRF, snapshot).
4. Routage par type → fan-out **parallèle en queue `analysis`** des adaptateurs pertinents :
   - Texte : Gemini + DeepSeek (grounding croisé), OpenAISearch/PerplexitySearch (recherche), GoogleFactCheck (**Claim Search API** — antériorité), KnowledgeGraph, GptZero+WinstonAI (texte généré par IA).
   - URL : + GoogleSafeBrowsing, TechnicalAnalysis (WHOIS/DNS/archives), GoogleCustomSearch.
   - Image : + GoogleVision (OCR), TinEye + GoogleReverseImage (antériorité), AiOrNot (deepfake).
   - Audio/Vidéo : + GoogleSTT/Whisper (transcription → repasse texte), AiOrNot.
5. Agrégation `FactCheckScoringService` : pondération par fiabilité moteur + concordance ; score de confiance 0-100 ; verdict seulement si seuil sinon NON_VERIFIABLE + revue humaine (file admin).
6. Persistance rapport structuré (sections du rapport premium du prototype), `GeminiSummaryAdapter` pour le résumé citoyen.
7. `VerdictPublished` → notifications, liaison forum (« VÉRIF'ALL CONFIRME »), Hub si éditorialisé.
8. Économie : débit d'unités **transactionnel** via EconomyService au lancement ; remboursement automatique si pipeline en échec (déjà le contrat des Logs Unités IA du prototype).

## Résilience & coûts
Timeout par adaptateur 20 s, retry ×2 backoff, circuit-breaker (3 échecs → OFF 5 min, visible dans « Santé du Système »), budget d'unités par analyse plafonné, tous les appels tracés dans `unit_logs` (page admin dédiée). Clés API : `config/services.php` ← `.env` uniquement. `SimulateFactCheck` (commande) rejoue le pipeline avec des adaptateurs mockés pour les tests et la démo.

---

# MODULES_CARTOGRAPHIE.md — Quoi construire, dans quel ordre

Sprints Laravel (chaque item = DoD complet de MASTER_INSTRUCTIONS) :
1. **Socle** : layouts 3 shells, composants partagés, RBAC+Policies, login/logout/OTP (citoyen+staff), audit/security logs, toasts, seeders globaux.
2. **Identity & Economy** : profil, réglages (2FA, consentements, export RGPD, suppression), unités + recharge Mobile Money simulée, ResetMonthlyUnits.
3. **Fact-Checking citoyen** : verifier (coût annoncé + solde restant), pipeline orchestrateur (adaptateurs mockables), verification-en-cours (polling Livewire → Reverb), rapport premium, mes-verifications (Verification Lab).
4. **LMS** : catalogue 4 états, cours (Aperçu/Curriculum/Instructeur/Avis commentables/Quiz/Exercices/FAQ), enrôlement gratuit/premium (unités), leçons + chronométrage lecture, quiz chronométré + notation + confetti, exercices (upload → correction mentor), progression évolutive + labels en cours/terminé, certificat PDF + vérification publique, live classes Zoom, reprendre/terminer, cours offert du mois.
5. **Community & Hub** : forum (composer, votes, transmission labo, verdict officiel), hub éditorial + article, blog public.
6. **Staff** : dashboards (admin = maquette validée ; mentor = inbox), les 45 pages admin + 12 mentor sur le pattern CRUD factory, Course Studio (builder), Correction mentor (§21), grading examens.
7. **Gamification & Marketing** : XP/badges/streak/niveaux, récompenses, parrainage (QR, tracking), campagnes, newsletters (queue SMTP), LmsRemindStalledUsers, SendWeeklyNewsletter.
8. **Transverse final** : SEO complet, analytics dashboard, sauvegardes/cron, API v1 mobile, durcissement sécurité, audit RGPD de bout en bout.

---

# SANITY_CHECK_QA.md — avant CHAQUE livraison

## Automatique
`./vendor/bin/pint --test` ✓ `phpstan --level=6` ✓ `php artisan test` (Feature par module : CRUD complet, autorisations, flux OTP, pipeline mocké, RGPD export/erasure) ✓ `composer audit` + `npm audit` ✓ Dusk (ou Pest browser) sur les parcours critiques : sous-onglets, ajout, édition, **suppression avec annulation**, export PDF/CSV, quiz complet, enrôlement.

## Manuel (grille du prototype)
0 lien cassé (crawler maison sur routes publiques) · 0 erreur JS console sur chaque layout · 0 déséquilibre HTML (validator) · KPI accordéon mobile sur TOUTES les pages · tables→cards mobile · toasts sur chaque action · empty/loading/error présents · Lighthouse ≥90 (public) · axe-core AA sans erreur bloquante · logins/limiteurs testés · APP_DEBUG=false en préprod.
**Un module n'est « terminé » que si cette page est verte. Signaler honnêtement tout écart.**
