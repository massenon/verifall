import 'package:flutter/foundation.dart';

/// Le corrigé d'**une** question, tel qu'il arrive avec la copie.
///
/// « C'est le seul moment où elles franchissent le réseau. »
@immutable
class QuizCorrection {
  const QuizCorrection({
    required this.questionUuid,
    required this.bonneReponseId,
    required this.explication,
  });

  final String questionUuid;

  /// L'identifiant entier de la bonne réponse. `null` quand la question n'en a
  /// aucune de marquée — cas de données, pas cas nominal : l'écran ne colorie
  /// alors aucune option plutôt que d'en désigner une au hasard.
  final int? bonneReponseId;

  /// L'explication pédagogique — la carte XAI du simulateur, déplacée sur
  /// l'écran de résultat (réserve C.1).
  final String? explication;
}

/// Le résultat d'une copie — `POST /me/quiz/{uuid}/attempt`.
///
/// **Ce type ne se met jamais en cache.** Le contrat (§7.1) nomme trois
/// données qui ne viennent jamais du cache, et le résultat d'un quiz en fait
/// partie : « on relit cet écran précisément parce qu'on attend un
/// changement ». Il n'existe donc aucune table locale pour lui.
@immutable
class QuizAttemptResult {
  const QuizAttemptResult({
    required this.uuid,
    required this.score,
    required this.reussi,
    required this.dureeSecondes,
    required this.corrige,
  });

  final String uuid;

  /// Le score en pourcentage, **calculé par le serveur**. « La correction ne
  /// franchit jamais le réseau avant la copie » (§7.4).
  final int score;

  final bool reussi;
  final int dureeSecondes;
  final List<QuizCorrection> corrige;

  /// Le nombre de bonnes réponses, **déduit du corrigé du serveur** et des
  /// choix envoyés — jamais d'un barème local.
  ///
  /// POURQUOI c'est acceptable alors que « la correction reste serveur » : on
  /// ne corrige rien, on compte des égalités entre deux valeurs que le serveur
  /// a fournies. Le score affiché en grand reste [score], celui du serveur.
  int bonnesReponses(Map<String, int> reponsesEnvoyees) {
    int total = 0;
    for (final QuizCorrection c in corrige) {
      final int? bonne = c.bonneReponseId;
      if (bonne != null && reponsesEnvoyees[c.questionUuid] == bonne) {
        total++;
      }
    }
    return total;
  }
}
