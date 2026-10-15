import '../../../core/errors/failure.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../domain/entities/settings_reglages.dart';
import '../../../domain/repositories/settings_repository.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/local/profile/compte_local_datasource.dart';
import '../../datasources/remote/profile/compte_remote_datasource.dart';
import '../../models/profile/reglages_model.dart';

/// Réglages, export RGPD, suppression de compte.
///
/// # La règle qui commande la forme de ce fichier
///
/// `PATCH /me/settings` attend une **valeur voulue, jamais une bascule**. Aucune
/// méthode ici ne prend « le canal à inverser » : elles prennent toutes le
/// couple `(clé, valeur)`. C'est ce qui rend le rejeu sûr — « une bascule perd
/// sa cible quand la requête est rejouée après une coupure, et l'utilisateur
/// retrouve le réglage inverse de ce qu'il a demandé ».
///
/// # Ce qui ne se met jamais en file
///
/// Ni l'export, ni la suppression. « Une suppression de compte mise en file et
/// rejouée trois jours plus tard, alors que la personne a changé d'avis, est
/// irrattrapable. » Seules les bascules de préférence y ont droit.
class SettingsRepositoryImpl implements SettingsRepository {
  const SettingsRepositoryImpl({
    required this.distant,
    required this.local,
    required this.file,
  });

  // Les dependances sont injectees et PUBLIQUES, comme la file d'ecriture du
  // socle (`PendingOpsQueue`) : un test doit pouvoir relire ce qu'on lui a
  // donne, et un champ prive nomme differemment de son parametre nomme n'est
  // pas exprimable en Dart sans dupliquer l'ecriture.
  final CompteRemoteDataSource distant;
  final CompteLocalDataSource local;
  final PendingOpsQueue file;

  @override
  Future<Reglages> reglages({bool forcerLeReseau = false}) async {
    try {
      final Reglages frais = await distant.reglages();
      await local.ecrire(
        RessourceDeCompte.reglages,
        ReglagesModel.versJson(frais),
      );
      return frais;
    } on Failure {
      if (forcerLeReseau) {
        rethrow;
      }
      final EntreeDeCache? entree = await local.lire(
        RessourceDeCompte.reglages,
      );
      if (entree == null) {
        rethrow;
      }
      return ReglagesModel.depuisJson(entree.contenu, luLe: entree.recupereLe);
    }
  }

  @override
  Future<Reglages> definirCanal({required String cle, required bool valeur}) =>
      _enregistrer(corpsCanal(cle, valeur));

  @override
  Future<Reglages> definirNewsletter({required bool valeur}) =>
      _enregistrer(corpsNewsletter(valeur));

  @override
  Future<void> definirPlusTard({required Map<String, Object?> corps}) async {
    await file.enqueue(
      kind: PendingOpKind.reglerPreference,
      endpoint: ApiEndpoints.settings,
      method: 'PATCH',
      // Le corps porte la VALEUR VOULUE. Rejoue trois fois, il produit trois
      // fois le meme etat — c'est exactement ce que la file exige d'un acte.
      payload: corps,
    );
  }

  @override
  Future<String> exporterMesDonnees() => distant.exporter();

  @override
  Future<SuppressionCompte> supprimerMonCompte({
    required String confirmation,
    required String motDePasse,
    String? code,
  }) => distant.supprimer(
    confirmation: confirmation,
    motDePasse: motDePasse,
    code: code,
  );

  Future<Reglages> _enregistrer(Map<String, Object?> corps) async {
    final Reglages frais = await distant.enregistrerLesReglages(corps);
    await local.ecrire(
      RessourceDeCompte.reglages,
      ReglagesModel.versJson(frais),
    );
    return frais;
  }

  /// Le corps d'une préférence de notification.
  ///
  /// Exposé pour que l'écran puisse préparer **le même corps** qu'il envoie
  /// immédiatement ou qu'il met en file. Deux constructions séparées auraient
  /// fini par diverger, et c'est la file qui aurait envoyé la mauvaise.
  static Map<String, Object?> corpsCanal(String cle, bool valeur) =>
      <String, Object?>{
        'notifications': <String, Object?>{cle: valeur},
      };

  /// Le corps du consentement à la lettre d'information.
  ///
  /// **Séparé des préférences**, exactement comme le contrat le sépare : « c'est
  /// un consentement marketing, horodaté et versionné, pas un réglage
  /// d'affichage ».
  static Map<String, Object?> corpsNewsletter(bool valeur) => <String, Object?>{
    'newsletter': valeur,
  };
}
