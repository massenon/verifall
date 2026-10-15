import '../../../domain/entities/quiz_attempt.dart';
import '../../../domain/entities/quiz_summary.dart';
import '../json_reader.dart';

/// `QuizResource` et la réponse de `POST /me/quiz/{uuid}/attempt`.
abstract final class QuizJson {
  static QuizSummary resume(Map<String, Object?> json) {
    final JsonReader r = JsonReader(json);
    return QuizSummary(
      uuid: r.string('uuid'),
      titre: r.string('titre'),
      minutes: r.intValue('minutes'),
      scoreRequis: r.intValue('score_requis'),
      xp: r.intValue('xp'),
    );
  }

  static QuizDetail detail(
    Map<String, Object?> json,
    Map<String, Object?> meta,
  ) {
    final JsonReader r = JsonReader(json);
    return QuizDetail(
      quiz: resume(json),
      questions: <QuizQuestion>[
        for (final Map<String, Object?> q in r.objectListOrEmpty('questions'))
          _question(q),
      ],
      // « L'heure qui fait foi, pas celle du telephone. » Absente, le
      // chronometre ne s'affiche pas — il ne se replie pas sur l'horloge locale.
      commenceLe: JsonReader(meta).dateTimeOrNull('commence_le'),
    );
  }

  static QuizQuestion _question(Map<String, Object?> json) {
    final JsonReader r = JsonReader(json);
    return QuizQuestion(
      // `data.questions[].id` porte l'UUID de la question, pas un entier.
      uuid: r.string('id'),
      position: r.intValue('position'),
      intitule: r.string('intitule'),
      reponses: <QuizAnswer>[
        for (final Map<String, Object?> a in r.objectListOrEmpty('reponses'))
          QuizAnswer(
            // L'entier assume du contrat, et le seul.
            id: JsonReader(a).intValue('id'),
            position: JsonReader(a).intValue('position'),
            libelle: JsonReader(a).string('libelle'),
          ),
      ],
    );
  }

  /// Le résultat d'une copie. **Jamais mis en cache** (contrat §7.1).
  static QuizAttemptResult resultat(Map<String, Object?> json) {
    final JsonReader r = JsonReader(json);
    return QuizAttemptResult(
      uuid: r.string('uuid'),
      score: r.intValue('score'),
      reussi: r.boolValue('reussi'),
      dureeSecondes: r.intValue('duree_s'),
      corrige: <QuizCorrection>[
        for (final Map<String, Object?> c in r.objectListOrEmpty('corrige'))
          QuizCorrection(
            questionUuid: JsonReader(c).string('question'),
            bonneReponseId: JsonReader(c).intOrNull('bonne_reponse'),
            explication: JsonReader(c).stringOrNull('explication'),
          ),
      ],
    );
  }
}
