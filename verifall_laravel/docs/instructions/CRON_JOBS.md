# CRON_JOBS.md — Planification (routes/console.php ou Kernel schedule)

| Commande | Fréquence | Rôle |
|---|---|---|
| `backup:run` (spatie) | daily 23:00 | Sauvegarde BD chiffrée, rotation 30 j — page Maintenance |
| `verifall:cleanup-evidences` | daily 02:00 | Purge preuves d'analyse >90 j (RGPD rétention) |
| `verifall:generate-image-hashes` | hourly | pHash des nouvelles images (déduplication) |
| `verifall:lms-remind-stalled` | daily 09:00 | Relance cours inactifs 7 j (deep-link Smart Resume) |
| `verifall:reset-monthly-units` | monthly 1st 00:10 | Allocation mensuelle + notification « cours offert du mois » |
| `verifall:send-weekly-newsletter` | monday 07:00 | Digest hebdo (queue SMTP) |
| `verifall:live-class-reminders` | daily 08:00 | Rappels J-1 live classes |
| `sitemap:generate` | daily 04:00 | Sitemap + ping Search Console |
| `verifall:anonymize-erasures` | daily 03:00 | Effacements RGPD arrivés à 30 j |
| `verifall:retry-failed-analyses` | everyThirtyMinutes | Re-run pipelines en échec (max 3) |
| `queue:prune-failed --hours=168` | daily | Hygiène queues |
Règles : `withoutOverlapping()` + `onOneServer()` partout ; chaque run journalisé (durée, statut) → page **Tâches Cron** admin ; échec → notification Admin + badge rouge (comme le prototype sitemap en échec).
