import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../domain/entities/live_class_session.dart';
import '../../../../domain/repositories/live_class_repository.dart';
import 'lms_providers.dart';

/// Les séances — `liveClassesProvider(tab)` du simulateur.
///
/// POURQUOI un seul provider pour les trois onglets, plutôt qu'un par onglet :
/// la liste est **fermée et non paginée** (contrat §3.3). La charger une fois et
/// la répartir sur `etat` est ce qui fait marcher les trois onglets **hors
/// ligne** — et l'interdiction de filtrer côté client ne vaut que pour les
/// listes paginées, où le filtre local ne porterait que sur la page chargée.
final FutureProvider<SeancesLues> liveClassesProvider =
    FutureProvider.autoDispose<SeancesLues>(
      (Ref ref) => ref.watch(liveClassRepositoryProvider).seances(),
    );

/// Le résultat d'une demande de rappel.
@immutable
class IssueDeRappel {
  const IssueDeRappel({
    required this.enregistre,
    required this.enFile,
    this.messageDuServeur,
  });

  /// Le serveur a enregistré l'intention.
  final bool enregistre;

  /// L'acte est en file. Il est protégé par une contrainte d'unicité en base,
  /// donc rejouable sans doublon.
  final bool enFile;

  /// Le message du serveur — `422 action_impossible` sur une séance passée.
  final String? messageDuServeur;

  /// La confirmation **honnête**.
  ///
  /// Le simulateur promet « Rappel programmé — vous recevrez une
  /// notification. » **La v1 ne tient pas cette promesse** : `remind` enregistre
  /// l'intention, dans une table qui existe, mais l'envoi attend
  /// l'infrastructure de notifications (réserve C.2). La formulation dit donc ce
  /// qui est vrai — et rien de plus.
  String get confirmation => enFile
      ? 'C est note — la demande partira des le retour du réseau.'
      : 'C est note — retrouvez la seance dans Live Classes.';
}

/// La demande de rappel.
class RappelController extends Notifier<AsyncValue<IssueDeRappel?>> {
  @override
  AsyncValue<IssueDeRappel?> build() =>
      const AsyncValue<IssueDeRappel?>.data(null);

  /// POURQUOI le garde-fou `ref.mounted` apres chaque attente : ces providers
  /// sont `autoDispose`, et l'ecran peut être quitte pendant l'aller-retour
  /// reseau. Ecrire dans l'état d'un provider deja libere leve — et c'est
  /// exactement ce qui arrive quand quelqu'un touche un bouton puis revient en
  /// arriere, geste banal sur un reseau lent.
  Future<IssueDeRappel?> demander(String uuid) async {
    if (state.isLoading) {
      return null;
    }
    state = const AsyncValue<IssueDeRappel?>.loading();
    try {
      final bool enregistre = await ref
          .read(liveClassRepositoryProvider)
          .demanderUnRappel(uuid);
      final IssueDeRappel issue = IssueDeRappel(
        enregistre: enregistre,
        enFile: !enregistre,
      );
      if (!ref.mounted) {
        return issue;
      }
      state = AsyncValue<IssueDeRappel?>.data(issue);
      if (enregistre) {
        // On relit : `rappel_demande` est un fait serveur, et le bouton doit
        // refleter ce que le serveur sait, pas ce que le client a tente.
        ref.invalidate(liveClassesProvider);
      }
      return issue;
    } on Failure catch (echec) {
      // `422 action_impossible` sur une seance passee : le message du serveur
      // dit pourquoi. On l'affiche tel quel.
      final IssueDeRappel issue = IssueDeRappel(
        enregistre: false,
        enFile: false,
        messageDuServeur: echec.serverMessage,
      );
      if (ref.mounted) {
        state = AsyncValue<IssueDeRappel?>.data(issue);
      }
      return issue;
    }
  }
}

final NotifierProvider<RappelController, AsyncValue<IssueDeRappel?>>
rappelProvider =
    NotifierProvider.autoDispose<RappelController, AsyncValue<IssueDeRappel?>>(
      RappelController.new,
    );

/// Les séances d'un onglet, réparties sur l'état **rendu par le serveur**.
List<LiveClassSession> seancesDeLOnglet(
  List<LiveClassSession> toutes,
  EtatSeance etat,
) => toutes
    .where((LiveClassSession s) => s.etat == etat)
    .toList(growable: false);
