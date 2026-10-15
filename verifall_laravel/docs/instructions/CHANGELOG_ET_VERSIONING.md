# CHANGELOG_ET_VERSIONING.md

Commits : Conventional Commits — `feat(lms): …`, `fix(factcheck): …`, `sec(auth): …` (type sec pour la sécurité), `docs`, `refactor`, `test`, `chore`. Un commit = un sujet ; corps = pourquoi.
Branches : `main` (déployable), `feat/<module>-<sujet>`, `fix/…` ; PR obligatoire + revue (REVUE_DE_CODE_SENIOR_QA_ARCHITECT) + CI verte.
Versions : SemVer `vMAJEUR.MINEUR.PATCH` — MAJEUR : rupture API mobile ou schéma incompatible ; MINEUR : module/fonctionnalité ; PATCH : correctifs. Tag = déploiement (CI_CD).
CHANGELOG.md : généré par sprint, sections Ajouté/Modifié/Corrigé/Sécurité, en français, orienté produit (« Les mentors peuvent désormais… »), lien vers les PR.
Migrations de données entre versions : toute migration transformant des données = commande artisan dédiée idempotente + entrée MIGRATION_RUNBOOK + test ; jamais dans la migration de schéma. API mobile : dépréciation annoncée (header Sunset, 90 j) et notée au CHANGELOG MAJEUR.
