import '../../../core/errors/failure.dart';
import '../../entities/settings_reglages.dart';
import '../../repositories/settings_repository.dart';

/// Ce que le réglage d'une préférence a produit.
enum ResultatReglage { enregistre, misEnFile }

/// Régler une préférence — **par sa valeur voulue**.
///
/// # Pourquoi la signature prend une valeur et jamais « inverse »
///
/// C'est la règle du contrat, et elle décide de la sûreté du rejeu :
///
/// > « Une bascule perd sa cible quand la requête est rejouée après une coupure,
/// > et l'utilisateur retrouve le réglage inverse de ce qu'il a demandé. »
///
/// Un interrupteur qui appellerait `basculer(cle)` produirait, sur un réseau
/// instable, exactement ce défaut. La cible est donc calculée **une fois**, dans
/// l'écran, au moment du geste — et elle voyage identique à chaque reprise.
///
/// # Pourquoi la lettre d'information passe par une autre méthode
///
/// Ce n'est pas une préférence d'affichage mais un **consentement marketing,
/// horodaté et versionné** (arbitrage n° 11). Chaque bascule ajoute une ligne
/// plutôt que d'en modifier une : l'historique des accords et des retraits est
/// exactement ce qu'il faut pouvoir produire en cas de contrôle. Le corps envoyé
/// est différent, et le mélanger aux canaux ferait écrire le consentement au
/// mauvais endroit.
class ReglerUnePreference {
  const ReglerUnePreference(this._repository);

  final SettingsRepository _repository;

  /// Un canal de notification.
  Future<({ResultatReglage resultat, Reglages? reglages})> canal({
    required String cle,
    required bool valeur,
  }) => _appliquer(
    () => _repository.definirCanal(cle: cle, valeur: valeur),
    corpsDeRepli: <String, Object?>{
      'notifications': <String, Object?>{cle: valeur},
    },
  );

  /// Le consentement à la lettre d'information.
  Future<({ResultatReglage resultat, Reglages? reglages})> newsletter({
    required bool valeur,
  }) => _appliquer(
    () => _repository.definirNewsletter(valeur: valeur),
    corpsDeRepli: <String, Object?>{'newsletter': valeur},
  );

  Future<({ResultatReglage resultat, Reglages? reglages})> _appliquer(
    Future<Reglages> Function() envoi, {
    required Map<String, Object?> corpsDeRepli,
  }) async {
    try {
      final Reglages reglages = await envoi();
      return (resultat: ResultatReglage.enregistre, reglages: reglages);
    } on NetworkFailure {
      // Seul l'echec de TRANSPORT part en file. Un `422 validation` sur un canal
      // inconnu vient forcement d'un client modifie : le rejouer produirait le
      // meme refus, indefiniment.
      await _repository.definirPlusTard(corps: corpsDeRepli);
      return (resultat: ResultatReglage.misEnFile, reglages: null);
    }
  }
}
