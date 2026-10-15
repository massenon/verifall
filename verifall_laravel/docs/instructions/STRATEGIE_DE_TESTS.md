# STRATEGIE_DE_TESTS.md — Pyramide Pest

## Répartition cible
Unit (Services Core, ~50 %) : EconomyService (débit/crédit/remboursement transactionnels, concurrence), FactCheckScoringService (pondérations, contradictions §4, seuils), ProgressionService (%, Smart Resume), OtpService (TTL, usage unique, rejeu), machines à états (transitions interdites). Adaptateurs : testés contre des fixtures JSON réelles anonymisées (`tests/Fixtures/adapters/`), jamais d'appel réseau (Http::fake).
Feature (~40 %) : par module — CRUD complet (dont suppression+undo), autorisations (matrice rôle×action : le Mentor ne touche PAS le cours d'un autre — test nominal), flux OTP/2FA/limiteurs, enrôlement gratuit/premium (solde insuffisant inclus), quiz chrono (auto-soumission), pipeline orchestrateur avec FAKE_ADAPTERS (verdict, contradiction→REVUE_HUMAINE, échec→remboursement), webhooks paiement (signature invalide refusée, rejeu idempotent), RGPD export+effacement, filigrane FAKE (présence sur démenti, absence sinon).
Browser Dusk (~10 %, les parcours d'or) : inscription→OTP→visite guidée ; vérifier→rapport ; cours complet→confetti→certificat ; admin login→CRUD cours→publication ; suppression avec annulation ; export PDF/CSV.
## Règles
Factories partout (les seeders démo ne servent QUE la démo) ; `RefreshDatabase` ; temps figé (`travelTo`) pour chronos/TTL ; couverture minimale 80 % sur `app/Core` (rapporté en CI) ; mutation testing (Infection) sur Economy + Scoring avant chaque release majeure ; chaque bug corrigé = un test de non-régression nommé `regression_…`.
