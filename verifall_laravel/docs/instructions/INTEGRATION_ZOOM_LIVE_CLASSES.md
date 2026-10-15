# INTEGRATION_ZOOM_LIVE_CLASSES.md

Auth : Server-to-Server OAuth (ZOOM_ACCOUNT_ID/CLIENT_ID/SECRET), token caché 55 min.
Création : à la publication d'une LiveClass (admin/mentor), `ZoomService::createMeeting()` → stocke meeting_id, join_url, password chiffrés. Modif/annulation synchronisées (update/delete API) + notification aux inscrits.
Accès citoyen : bouton « Rejoindre » actif T-15 min → route signée `/live/{uuid}/join` (vérifie inscription + fenêtre horaire) qui redirige vers join_url — l'URL Zoom n'est jamais affichée en dur (anti-partage).
Présence : webhook Zoom `meeting.participant_joined/left` → table live_attendances (durée) → compte pour la progression si ≥70 % de présence ; export CSV présence côté mentor.
Rappels : notification J-1 + T-1 h (CRON_JOBS live-class-reminders). Replays : champ replay_url (upload/lien) affiché post-séance. Fallback dev : FAKE_ZOOM=true → liens factices + présence simulée.
