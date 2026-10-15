import '../../../core/errors/failure.dart';
import '../../entities/settings_reglages.dart';
import '../../repositories/settings_repository.dart';

/// Le message opposé à une suppression tentée hors ligne.
///
/// « Une suppression de compte mise en file et rejouée trois jours plus tard,
/// alors que la personne a changé d'avis, est irrattrapable. » Ce n'est pas de
/// la prudence excessive : c'est le seul acte de l'application qu'on ne peut pas
/// défaire depuis l'application.
///
/// POURQUOI un `ImpossibleActionFailure` et pas un type d'échec dédié :
/// `Failure` est scellé par le socle, et le socle est en lecture seule.
const String messageSuppressionHorsLigne =
    'La suppression du compte a besoin du réseau. Elle ne peut pas attendre en '
    'file : une suppression envoyee trois jours plus tard ne serait plus celle '
    'que vous avez demandee.';

/// Supprimer mon compte — **en deux temps, sur un seul point d'entrée**.
///
/// # Ce que la maquette montrait, et ce que le contrat impose
///
/// Le simulateur demande de **taper son nom**, puis supprime. Le contrat impose
/// un parcours différent sur le même `DELETE /me` (réserve A.12) :
///
/// | Temps | Envoyé | Réponse |
/// |---|---|---|
/// | 1 | `confirmation` = le mot exact, `mot_de_passe` | `202`, un code part par courriel. **Rien n'est supprimé** |
/// | 2 | `confirmation`, `mot_de_passe`, `code` | `200`, suppression programmée |
///
/// > « Mot de passe et code : le premier prouve qu'on connaît le compte, le
/// > second qu'on tient la boîte. Un téléphone volé déverrouillé passe le
/// > premier, jamais les deux. »
///
/// Trois écarts à la maquette, tous appliqués ici : le mot attendu est
/// [motDeConfirmationSuppression] et non le nom de la personne ; le mot de passe
/// est exigé dès le premier temps ; un code par courriel vient ensuite.
///
/// # Ce qui se passe ensuite, et que l'écran doit dire
///
/// Le compte est **fermé sur-le-champ**, les données ne disparaissent qu'au bout
/// de **trente jours**, et **l'annulation ne passe pas par l'application** :
/// elle se fait par le lien signé reçu par courriel, « seule voie de retour
/// quand on ne peut plus se connecter ». À l'échéance, c'est une anonymisation,
/// pas une suppression de ligne.
class SupprimerMonCompte {
  const SupprimerMonCompte({
    required this.repository,
    required this.estHorsLigne,
  });

  // Les dependances sont injectees et PUBLIQUES, comme la file d'ecriture du
  // socle (`PendingOpsQueue`) : un test doit pouvoir relire ce qu'on lui a
  // donne, et un champ prive nomme differemment de son parametre nomme n'est
  // pas exprimable en Dart sans dupliquer l'ecriture.
  final SettingsRepository repository;
  final Future<bool> Function() estHorsLigne;

  /// Premier temps : mot de confirmation + mot de passe. **Rien n'est
  /// supprimé** ; un code part par courriel.
  Future<SuppressionCompte> demanderLeCode({
    required String confirmation,
    required String motDePasse,
  }) => _envoyer(confirmation: confirmation, motDePasse: motDePasse);

  /// Second temps : le même couple, **plus le code reçu**.
  Future<SuppressionCompte> confirmer({
    required String confirmation,
    required String motDePasse,
    required String code,
  }) =>
      _envoyer(confirmation: confirmation, motDePasse: motDePasse, code: code);

  Future<SuppressionCompte> _envoyer({
    required String confirmation,
    required String motDePasse,
    String? code,
  }) async {
    if (await estHorsLigne()) {
      throw const ImpossibleActionFailure(
        serverMessage: messageSuppressionHorsLigne,
      );
    }

    final String mot = confirmation.trim();
    if (mot != motDeConfirmationSuppression) {
      // Verifie AVANT l'envoi : inutile de transmettre un mot de passe pour
      // apprendre que le mot de confirmation n'est pas le bon. Le serveur le
      // revalide de toute facon — « l'absence de bouton n'est pas une
      // protection » (04-RESEAU 4.13).
      throw ValidationFailure(
        fieldErrors: <String, List<String>>{
          'confirmation': <String>[
            'Tapez exactement $motDeConfirmationSuppression.',
          ],
        },
      );
    }
    if (motDePasse.isEmpty) {
      throw const ValidationFailure(
        fieldErrors: <String, List<String>>{
          'mot_de_passe': <String>['Entrez votre mot de passe.'],
        },
      );
    }

    return repository.supprimerMonCompte(
      confirmation: mot,
      motDePasse: motDePasse,
      code: code,
    );
  }
}
