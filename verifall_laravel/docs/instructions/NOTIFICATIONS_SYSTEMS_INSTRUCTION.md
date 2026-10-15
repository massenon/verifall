# NOTIFICATIONS_SYSTEMS_INSTRUCTION.md

Canaux : `database` (cloche), `broadcast` (Reverb temps réel), `mail` (queue), `fcm` (mobile). Chaque classe Notification déclare `via()` selon les préférences user (Réglages, opt-out granulaire RGPD).
Catalogue (namespaces App\Notifications\…) — FactChecking : VerdictReady, AnalysisFailedRefunded, UrgentSignalementQueued(admin) · Lms : CertificateIssued, QuizPassed, CourseCompleted(gains), LiveClassReminder, MonthlyFreeCourse, StalledCourseReminder · Forum : ReplyToThread, OfficialVerdictLinked, ThreadTransmitted · Mentor : AssignmentSubmitted(+Urgent >72 h), NewCourseReview, ExamCopyAssigned · Admin : SecurityAlert, BackupFailed, CronFailed, KycSubmitted.
UI cloche (topbar, identique prototype) : badge décompteur, dropdown 340 px, non-lues surlignées + point, clic = lu, « Tout marquer lu », lien « Voir toute l'activité » ; page notifications complète paginée côté citoyen.
Règles : contenu actionnable (deep-link direct vers l'objet), jamais de PII sensible dans un e-mail, digest anti-spam (regrouper >3 notifications du même type/heure), toutes les notifications mail via queue `notifications`, templates e-mail = page admin Templates Emails.
