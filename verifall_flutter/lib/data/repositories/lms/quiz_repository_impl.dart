import 'package:uuid/uuid.dart';

import '../../../core/errors/failure.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../domain/entities/quiz_attempt.dart';
import '../../../domain/entities/quiz_summary.dart';
import '../../../domain/repositories/quiz_repository.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/remote/lms/lms_remote_data_source.dart';

/// Le dépôt du quiz.
///
/// # Ce dépôt n'écrit RIEN dans le cache, et c'est le point
///
/// Le contrat nomme trois données qui ne viennent jamais du cache, et « le
/// résultat d'un quiz » en fait partie (§7.1) : « on relit cet écran
/// précisément parce qu'on attend un changement ». Une note affichée depuis un
/// cache serait une note fausse.
///
/// Les **questions** ne sont pas cachées non plus, et pour une raison propre à
/// ce parcours : `GET /courses/{slug}/quiz` **démarre le chronomètre serveur**
/// et pose `meta.commence_le`. Une copie ouverte depuis un cache porterait une
/// heure de départ périmée et serait rendue hors délai sans que personne l'ait
/// vu venir.
///
/// # La clé d'idempotence, et pourquoi elle est générée ici
///
/// C'est **l'un des deux seuls actes que le contrat exige de protéger** par
/// `Idempotency-Key`. La clé est générée **au moment où la personne rend sa
/// copie**, pas au moment de l'envoi : « une clé regénérée à l'envoi ne protège
/// de rien ».
///
/// L'ordre des opérations n'est pas cosmétique : on **met en file d'abord**,
/// puis on tente l'envoi immédiat avec la clé de la ligne de file. C'est ce qui
/// garantit qu'une reprise portera exactement la même clé qu'une tentative
/// perdue — sans quoi le rejeu serait une seconde tentative fantôme.
class QuizRepositoryImpl implements QuizRepository {
  QuizRepositoryImpl({
    required this.distant,
    required this.fileStore,
    Uuid? uuid,
    DateTime Function()? horloge,
  }) : _uuid = uuid ?? const Uuid(),
       _horloge = horloge ?? DateTime.now;

  final LmsRemoteDataSource distant;
  final PendingOpsStore fileStore;

  /// Injectable : c'est la seule façon de vérifier, en test, qu'une reprise
  /// porte **la même** clé qu'une tentative perdue.
  final Uuid _uuid;
  final DateTime Function() _horloge;

  @override
  Future<QuizDetail> charger(String coursSlug) => distant.quiz(coursSlug);

  @override
  Future<IssueDeCopie> rendreLaCopie({
    required String quizUuid,
    required Map<String, int> reponses,
  }) async {
    // 1. La cle de l'ACTE. Une seule valeur, generee une fois, reutilisee a
    //    chaque reprise. C'est aussi l'identifiant de la ligne de file : le
    //    socle fait des deux la meme valeur, precisement pour qu'elles ne
    //    puissent pas diverger.
    final String cle = _uuid.v4();

    final PendingOp acte = PendingOp(
      clientUuid: cle,
      kind: PendingOpKind.rendreCopieQuiz,
      endpoint: ApiEndpoints.quizAttempt(quizUuid),
      payload: <String, Object?>{'reponses': reponses},
      createdAt: _horloge(),
    );
    await fileStore.enqueue(acte);

    try {
      final QuizAttemptResult resultat = await distant.rendreLaCopie(
        quizUuid: quizUuid,
        reponses: reponses,
        cleIdempotence: cle,
      );
      // Le serveur a corrige : la ligne de file n'a plus lieu d'etre.
      await fileStore.remove(cle);
      return CopieCorrigee(resultat);
    } on NetworkFailure {
      // La copie reste en file. Le resultat n'arrivera qu'au retour du reseau,
      // puisque la correction est serveur — l'ecran affiche « Resultat en
      // attente de connexion », jamais un score calcule localement.
      return CopieEnFile(cle);
    } on Failure {
      // Un refus NOMME du serveur (hors delai, quiz retire, droit manquant) ne
      // se rejoue pas : « les echecs ne sont pas memorises. Rejouer un echec
      // figerait une erreur passagere. » On retire la ligne et on laisse
      // l'échec remonter a l'ecran, qui affichera le message du serveur.
      await fileStore.remove(cle);
      rethrow;
    }
  }
}
