import '../../../core/errors/failure.dart';
import '../../../core/logging/app_logger.dart';
import '../../../core/network/auth_controller.dart';
import '../../repositories/auth_repository.dart';

/// La déconnexion, et la purge qui va avec.
///
/// # Pourquoi cette logique appartient à M1 alors que le bouton est dans M5
///
/// Le bouton « Se déconnecter » vit sur l'écran 30 (Réglages), qui appartient à
/// M5. Mais ce qui se passe quand on l'actionne — révoquer, purger, présenter la
/// connexion — est la mécanique de session, donc M1. M5 appelle
/// [FermerSession] ; il ne réécrit pas la purge, sinon il y en aurait deux, et
/// l'une des deux oublierait quelque chose.
///
/// # Pourquoi l'échec réseau n'empêche pas la déconnexion
///
/// `POST /auth/logout` révoque le jeton côté serveur. S'il échoue — hors ligne,
/// serveur en panne, jeton déjà expiré — **on ferme quand même localement**.
/// Refuser de déconnecter quelqu'un parce que le réseau est absent laisserait un
/// jeton vivant sur un téléphone qu'on veut rendre : c'est exactement la
/// situation que la déconnexion sert à éviter.
///
/// Le contrat le permet sans réserve : `POST /auth/logout` est l'une des deux
/// seules routes qui répondent même pendant un délai de suppression de compte
/// (§1.7), précisément parce que « refuser de fermer un accès reviendrait à
/// imposer de garder un jeton vivant sur un téléphone dont on ne veut plus ».
///
/// # Ce que la purge couvre
///
/// `AuthController.signOut()` efface **le stockage sécurisé et la base locale
/// entière** — cache de lecture et file d'écriture comprises. C'est le socle qui
/// le fait, en un seul geste, et c'est ce qui rend la purge vérifiable par un
/// seul test.
class FermerSession {
  const FermerSession(this._repository, this._controller, this._logger);

  final AuthRepository _repository;
  final AuthController _controller;
  final AppLogger _logger;

  Future<void> call() async {
    try {
      await _repository.deconnexion();
    } on Failure catch (echec) {
      // On journalise le CODE, jamais le corps ni le jeton. Un echec ici n'est
      // pas anodin — le jeton reste vivant cote serveur jusqu'a son echeance —
      // mais il ne doit pas retenir la personne sur un ecran connecte.
      _logger.warning(
        LogChannel.session,
        'Revocation distante en échec ; la session est fermee localement',
        data: echec.apiCode?.wireValue ?? echec.runtimeType.toString(),
      );
    }

    // La purge totale : stockage securise + base Drift + file d'ecriture.
    await _controller.signOut();
  }
}
