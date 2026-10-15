import '../entities/quiz_attempt.dart';
import '../entities/quiz_summary.dart';

/// Ce qu'une copie rendue a donné.
///
/// Deux issues, et **une seule** porte un résultat : « le résultat n'arrive
/// qu'au retour du réseau, puisque la correction est serveur. L'écran affiche
/// "Résultat en attente de connexion", jamais un score calculé localement. »
sealed class IssueDeCopie {
  const IssueDeCopie();
}

/// Le serveur a corrigé.
class CopieCorrigee extends IssueDeCopie {
  const CopieCorrigee(this.resultat);

  final QuizAttemptResult resultat;
}

/// Le réseau a manqué : la copie est **en file**, avec sa clé d'idempotence.
///
/// « Une copie rendue deux fois par une réponse perdue serait une seconde
/// tentative fantôme. » La clé est générée à l'acte et gardée dans la ligne de
/// la file ; toutes les reprises portent la même.
class CopieEnFile extends IssueDeCopie {
  const CopieEnFile(this.cleIdempotence);

  final String cleIdempotence;
}

/// Le dépôt du quiz.
///
/// **Aucune méthode de lecture d'un résultat mémorisé.** C'est délibéré : le
/// résultat d'un quiz est l'une des trois données que le contrat interdit de
/// servir depuis le cache (§7.1). Il n'y a donc rien à relire — et pas de table
/// pour le stocker.
abstract interface class QuizRepository {
  /// Charge le quiz d'un cours. **Exige le réseau**, et pour deux raisons qui
  /// se cumulent :
  ///
  /// 1. l'appel **démarre le chronomètre serveur** (`meta.commence_le`) ;
  /// 2. une copie ouverte depuis un cache porterait une heure de départ
  ///    périmée, et serait hors délai sans que personne l'ait vu venir.
  Future<QuizDetail> charger(String coursSlug);

  /// Rend la copie. [reponses] est indexé **par uuid de question**.
  ///
  /// Un tableau vide est accepté par le contrat : « c'est la copie de quelqu'un
  /// dont le chronomètre est arrivé au bout », et une question sans réponse
  /// compte comme fausse. Le client n'invente aucun échec, il envoie ce qu'il a.
  Future<IssueDeCopie> rendreLaCopie({
    required String quizUuid,
    required Map<String, int> reponses,
  });
}
