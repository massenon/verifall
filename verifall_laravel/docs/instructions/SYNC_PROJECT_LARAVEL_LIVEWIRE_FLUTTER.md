# SYNC_PROJECT_LARAVEL_LIVEWIRE_FLUTTER.md — Contrat de synchronisation

Auth : Sanctum tokens (POST /api/v1/auth/login → token+abilities ; refresh silencieux ; révocation à la déconnexion et au changement de mdp — liste des appareils dans Réglages).
Lecture (offline-first) : GET paginés avec `?updated_since=` ; réponses `{data, meta{server_time}}` ; le mobile stocke dans Drift et n'affiche jamais un spinner s'il a un cache (stale-while-revalidate).
Écriture (file d'opérations) : le mobile POST avec header `Idempotency-Key: {uuid client}` ; Laravel : table idempotency_keys(key uq, response json, ttl 24 h) — re-jeu = même réponse, jamais de doublon (inscriptions, votes, avis, soumissions). File locale pending_ops rejouée FIFO à la reconnexion ; échec métier (409/422) → op marquée en erreur + résolution UI.
Conflits : last-write-wins sur les préférences ; données serveur-autoritaires (unités, progression, verdicts) : le mobile ne calcule jamais, il affiche. Horloge : timestamps serveur uniquement.
Temps réel : canaux privés Reverb `user.{uuid}` (verdict, notification, correction) ; fallback polling 30 s si socket down. Push FCM : payload minimal (type+uuid) → l'app fetch le détail (jamais de PII dans le push).
Versionnage : /api/v1 gelé ; champs additifs seulement ; dépréciation = header Sunset + 90 j. Erreurs : enveloppe {error{code, message_fr, fields}} — codes stables consommés par le mobile.
