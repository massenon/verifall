# MODULE_LMS_CITOYEN.md — Écran par écran (référence : prototype web/compte-user/)

Modèles : Course(uuid, slug, title, level, type gratuit|premium, price_units, cover, mentor_id, status) → Section → Lesson(type: text|video|document, duration_min, position) · Quiz(→Questions 5 types, pass_score, duration_min) · Assignment · Enrollment(user, course, status, progress %) · LessonCompletion · QuizAttempt(score, passed, duration_s) · Review(rating 1-5, comment ≥10) · Certificate(code public, issued_at).

1. **catalogue** : cards 4 états (nouveau / en cours border-left verte + Reprendre / terminé / verrouillé premium), filtres bottom-sheet mobile, recherche.
2. **cours** (tabs Aperçu · Curriculum · Instructeur · **Avis commentables** (étoiles radiogroup + commentaire ≥10 car., persistés, « Votre avis » surligné) · Quiz · Exercices · FAQ) : hero cover placeholder, CTA contextuel — gratuit : « Commencer gratuitement » ; premium : « Débloquer pour N unités » (modale de confirmation solde, transaction EconomyService) ; enrôlé : « Reprendre le cours » (Smart Resume : 1ʳᵉ leçon non complétée) ; terminé : « Télécharger le certificat » + « Revoir ».
3. **lecon** : chronométrage de lecture réel (timer visible, `LessonCompletion` seulement après `duration_min` écoulées OU « Marquer comme terminée » si ≥50 % du temps — anti-triche), vidéo : progression de lecture comptée + reprise au timestamp, navigation précédent/suivant verrouillée séquentiellement, sidebar curriculum ✓.
4. **quiz** : chrono descendant (duration_min, auto-soumission à 0), 1 question/écran, correction + explication XAI après réponse, score final vs pass_score → **réussi : confetti + gains** ; échoué : « Réessayer » (tentatives tracées). Dernier quiz d'un cours 100 % → écran « Vos gains » (+XP, +unités bonus, badge, bouton certificat).
5. **exercices** : consigne + upload (pdf/jpg ≤10 Mo, disque privé) → statut À rendre / En correction / **Corrigé (note /20 + feedback mentor Quill)** ; échéances sémantiques.
6. **certificat** : PDF généré (gabarit admin), code public vérifiable sur /verify-certificate, partage.
7. **live-classes** : sessions à venir (rappel J-1), bouton Zoom, replays.
8. **mes-tutoriels** : Smart Resume + quiz à reprendre (ambre) + cross-links. Labels En cours/Terminé et **progression évolutive** partout (barres réelles = leçons complétées / total).
Événements → Listeners : LessonCompleted→XP, QuizPassed→XP+streak, CourseCompleted→certificat+gains+notification, ReviewPosted→notification mentor.
