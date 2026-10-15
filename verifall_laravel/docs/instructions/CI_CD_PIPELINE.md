# CI_CD_PIPELINE.md — GitHub Actions

## Workflow `ci.yml` (push + PR)
jobs séquentiels : 1) lint : `pint --test` + `npm run lint` · 2) static : `phpstan --level=6` · 3) tests : MySQL+Redis services, `php artisan test --parallel --coverage --min=80` · 4) audit : `composer audit` + `npm audit --audit-level=high` + scan secrets (gitleaks) · 5) build : `npm ci && npm run build` (artefact). PR bloquée si un job échoue ; badge statut dans le README.
## Workflow `deploy.yml` (tag v*)
Zéro-downtime type Deployer : upload release → `composer install --no-dev --optimize-autoloader` → liens `storage/` + `.env` partagés → `php artisan migrate --force` (jamais destructif : voir MIGRATION_RUNBOOK kb) → `config:cache route:cache view:cache event:cache` → redémarrage `queue:restart` + Reverb → bascule symlink → smoke tests (endpoint /up + login page 200 + une page publique) → rollback automatique (symlink précédent) si smoke KO. Notifications déploiement → canal admin. Secrets : uniquement via GitHub Environments (production protégé, approbation requise). Sauvegarde BD pré-migration systématique.
