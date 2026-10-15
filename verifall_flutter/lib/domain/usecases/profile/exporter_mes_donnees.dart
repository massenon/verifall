import '../../../core/errors/failure.dart';
import '../../repositories/settings_repository.dart';

/// Le message opposé à un export tenté hors ligne.
///
/// POURQUOI un `ImpossibleActionFailure` et pas un type d'échec dédié :
/// `Failure` est scellé par le socle, et le socle est en lecture seule.
const String messageExportHorsLigne =
    'L export de vos donnees a besoin du réseau. Reessayez des que vous etes '
    'connecte.';

/// Demander mon archive RGPD (article 20).
///
/// # Ce que l'appel ne rend pas, et pourquoi c'est essentiel
///
/// **L'archive n'arrive jamais dans la réponse.** Le contrat en donne la raison
/// la plus concrète : « elle contient tout ce que la plateforme détient d'une
/// personne, et un corps HTTP se retrouve dans les journaux d'un mandataire ».
/// Elle est préparée en tâche de fond (`202`) et arrive par courriel, sur route
/// signée.
///
/// L'écran confirme et **n'attend rien**. Il n'y a pas de barre de progression à
/// écrire : il n'y a rien à suivre.
///
/// # Le `409` n'est pas une erreur à réessayer
///
/// « Une seconde demande dans le quart d'heure répond `409 conflit` » — chaque
/// archive est une copie complète de données personnelles, et en produire à la
/// chaîne multiplie les objets à protéger. Le message du serveur s'affiche tel
/// quel, **sans relance**. Ce cas d'usage laisse donc remonter le
/// [ConflictFailure] au lieu de le rattraper : l'écran doit le montrer, pas le
/// masquer.
class ExporterMesDonnees {
  const ExporterMesDonnees({
    required this.repository,
    required this.estHorsLigne,
  });

  // Les dependances sont injectees et PUBLIQUES, comme la file d'ecriture du
  // socle (`PendingOpsQueue`) : un test doit pouvoir relire ce qu'on lui a
  // donne, et un champ prive nomme differemment de son parametre nomme n'est
  // pas exprimable en Dart sans dupliquer l'ecriture.
  final SettingsRepository repository;
  final Future<bool> Function() estHorsLigne;

  /// Rend le message du serveur, à afficher tel quel.
  Future<String> call() async {
    if (await estHorsLigne()) {
      throw const ImpossibleActionFailure(
        serverMessage: messageExportHorsLigne,
      );
    }
    return repository.exporterMesDonnees();
  }
}
