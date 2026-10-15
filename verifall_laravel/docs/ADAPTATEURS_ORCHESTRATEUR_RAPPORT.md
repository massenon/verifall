# ADAPTATEURS_ORCHESTRATEUR_RAPPORT.md — Le moteur de vérification de bout en bout

## 1. Contrat commun des adaptateurs
```php
interface AnalysisAdapterInterface {
    public function supports(SignalementType $type): bool;
    public function analyze(EvidenceBag $input): AdapterVerdict; // jamais d'exception non catchée
    public function name(): AnalysisEngine;                      // Enum
    public function costUnits(): int;                            // débité via EconomyService
}
// AdapterVerdict { engine, verdict: ?SignalementVerdict, confidence: 0-100,
//                  evidences: Evidence[], rawExcerpt, latencyMs, failed: bool, failReason }
// Evidence { sourceUrl, sourceName, publishedAt, snippet ≤ 300, stance: SUPPORTS|REFUTES|NEUTRAL, reliability: 0-100 }
```
Règles : timeout 20 s, retry ×2 backoff (1 s/4 s), circuit-breaker (3 échecs → OFF 5 min, visible « Santé du Système »), réponse toujours retournée (`failed=true` plutôt qu'exception), aucun secret loggé, coût tracé dans `unit_logs`.

## 2. Catalogue des adaptateurs — config API + rôle + prompt
Config : `config/services.php` ← `.env`. Variables listées ; jamais commitées.

| Adaptateur | ENV | Rôle | Types |
|---|---|---|---|
| GeminiAdapter | `GEMINI_API_KEY`, `GEMINI_MODEL` | Analyse sémantique principale + grounding | tous |
| DeepSeekAdapter | `DEEPSEEK_API_KEY` | 2ᵉ LLM indépendant (croisement anti-hallucination) | texte/URL |
| OpenAIAdapter / OpenAISearchAdapter | `OPENAI_API_KEY` | Raisonnement + recherche web outillée | texte/URL |
| PerplexitySearchAdapter | `PERPLEXITY_API_KEY` | Recherche web citée temps réel | texte/URL |
| GoogleFactCheckAdapter | `GOOGLE_FACTCHECK_KEY` | **Claim Search API** : fact-checks antérieurs (ClaimReview mondial) | texte |
| GoogleCustomSearchAdapter | `GOOGLE_CUSTOM_SEARCH_KEY`, `GOOGLE_CSE_ID` | Recherche ciblée presse béninoise + sources officielles | tous |
| KnowledgeGraphAdapter | `GOOGLE_KG_KEY` | Entités (personnes/institutions) — vérifie titres/fonctions | texte |
| GoogleSafeBrowsingAdapter | `GOOGLE_SB_KEY` | URL malveillante/phishing | URL |
| TechnicalAnalysisAdapter | — (WHOIS/DNS/Wayback) | Âge domaine, registrant, archives | URL |
| GoogleVisionAdapter | `GOOGLE_VISION_KEY` | OCR + logos + SafeSearch | image/vidéo |
| TinEyeAdapter | `TINEYE_KEY` | Antériorité d'image (1ʳᵉ apparition) | image |
| GoogleReverseImageAdapter | `GOOGLE_CUSTOM_SEARCH_KEY` | Recherche inversée complémentaire | image |
| AiOrNotAdapter | `AIORNOT_KEY` | Détection deepfake image/audio | image/audio/vidéo |
| GptZeroAdapter · WinstonAiAdapter | `GPTZERO_KEY` · `WINSTON_KEY` | Texte généré par IA (double avis) | texte |
| GoogleSTTAdapter · WhisperAdapter | `GOOGLE_STT_KEY` · `OPENAI_API_KEY` | Transcription (fallback l'un de l'autre) | audio/vidéo |
| GeminiSummaryAdapter | `GEMINI_API_KEY` | Résumé citoyen du rapport (jamais de verdict) | post-pipeline |
| SearchConsoleAdapter | OAuth service account | Ping sitemap / perf SEO | hors pipeline |

## 3. ALGORITHME PROMPT des adaptateurs LLM (gabarit obligatoire)
Chaque LLM adapter construit son prompt ainsi (versionné dans `resources/prompts/factcheck/*.md`, jamais en dur) :
```
SYSTEM : Tu es un moteur d'analyse de Vérif'All (fact-checking, Bénin).
RÈGLES : (1) Tu ne réponds QUE le JSON du schéma. (2) verdict ∈ {VRAI,FAUX,TROMPEUR,NON_VERIFIABLE}.
(3) Chaque affirmation de ton raisonnement DOIT citer une evidence fournie [E1..En] — sinon NON_VERIFIABLE.
(4) Interdiction d'inventer une source, une date, un chiffre. (5) confidence honnête : preuves faibles ⇒ ≤40.
(6) Contexte béninois prioritaire (sources gouv.bj, INStaD, presse locale évaluée).
USER : CLAIM: <claim normalisé> | CONTEXTE: <type, date, langue>
EVIDENCES: [E1] {source, date, extrait ≤300} … (fournies par le harness, § 5)
SCHÉMA JSON: { "verdict": "...", "confidence": 0-100, "reasoning_fr": "≤120 mots, cite [Ex]",
  "evidence_stances": [{"id":"E1","stance":"SUPPORTS|REFUTES|NEUTRAL"}], "missing_info": "..." }
```
Parsing strict (json_decode + validation Enum) ; JSON invalide après 1 relance = `failed=true`. **Un LLM ne « sait » jamais : il évalue des preuves.**

## 4. Scoring, verdict & résolution des contradictions
`FactCheckScoringService` :
1. **Pondération par moteur** (config `factcheck.weights`) : GoogleFactCheck (antériorité) 1.0 · TinEye/TechnicalAnalysis (factuel dur) 0.9 · Gemini/DeepSeek 0.7 · recherche 0.6 · détecteurs IA 0.5 (signal, pas verdict).
2. Score par verdict = Σ(poids × confidence) des adaptateurs concordants ; normalisé 0-100.
3. **Décision** : écart ≥25 pts entre le 1ᵉʳ et le 2ᵉ verdict ET score ≥60 → verdict retenu, confiance = score.
4. **Contradiction** (écart <25 pts, ex. Gemini=FAUX vs GoogleFactCheck=VRAI) → protocole :
   a. Round d'arbitrage : re-prompt des 2 LLM avec les évidences **de la thèse adverse** injectées (« défends ou révise ») ;
   b. Toujours contradictoire → verdict = `TROMPEUR` si les stances se partagent sur des sous-claims (claim partiellement vrai), sinon `NON_VERIFIABLE` ;
   c. Statut rapport = `REVUE_HUMAINE` → file admin prioritaire (page Signalements, badge « Arbitrage ») ; le rapport public affiche les deux thèses avec leurs sources — **jamais un faux consensus**.
5. **« Verdict : Synthèse indisponible »** — causes & solutions codées :
   - GeminiSummary en échec → fallback DeepSeek summary → fallback synthèse template (verdict + top 3 évidences formatées server-side). La synthèse LLM est un **enrichissement, jamais un bloquant** : le rapport sort toujours.
   - <2 adaptateurs réussis → verdict NON_VERIFIABLE, bandeau honnête « Analyse partielle — moteurs indisponibles », **remboursement automatique des unités**, re-run planifié (Job retry 30 min, max 3).
   - Incohérence verdict/rapport détectée (post-check : le verdict Enum doit être ⊇ des stances majoritaires) → blocage publication + REVUE_HUMAINE. Test de non-régression dédié.

## 5. DEEP RESEARCH HARNESS (Core\FactChecking\Services\DeepResearchHarness)
Utilisé pour les claims textuels complexes (et le mode « Analyse approfondie » premium) :
1. **Plan** : décomposer le claim en sous-questions vérifiables (Gemini, schéma JSON `questions[]`, max 5).
2. **Fan-out searches** : pour chaque sous-question, requêtes parallèles (OpenAISearch + Perplexity + GoogleCustomSearch), 3 variantes de formulation (dont une **adverse** : « X démenti », « X faux »).
3. **Fetch sources** : télécharger le contenu des N meilleurs résultats via UrlSandbox (dédupliqués par domaine, cap 12 sources), extraire texte + date + auteur ; score de fiabilité par domaine depuis la table `trusted_sources` (page admin Sources vérifiées).
4. **Adversarial verify** : chaque évidence est notée SUPPORTS/REFUTES par 2 LLM **séparément** ; désaccord de stance → évidence marquée CONTESTED (pondération ÷2).
5. **Synthesize a cited report** : rapport final où **chaque phrase porte [E#]** ; les évidences deviennent la section « Sources et Preuves consultées » (nom, URL, date, fiabilité %, stance, extrait) — exactement la section du rapport premium du prototype. Coût plafonné (budget unités), tout tracé dans `unit_logs`.

## 6. Structure du rapport (RapportFact — table `analysis_reports`)
`{ uuid, signalement_uuid, verdict, confidence, summary_citizen (fallback chain §4.5), claims[] (sous-claims + verdicts), engines[] (moteur, statut, latence, confidence), evidences[] (§5.5), timeline[] (étapes pipeline horodatées), hsi_flags (santé/sécurité → bandeau rouge du prototype), reviewed_by (si arbitrage humain), published_at }` — rend 1:1 la page rapport premium du prototype (hero verdict, score, sources consultées, méthodologie, partage).
