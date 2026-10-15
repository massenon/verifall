import '../entities/notification_citoyen.dart';

/// Le dépôt des notifications — **déclaré, non branché**.
///
/// # Pourquoi il existe sans implémentation réseau
///
/// Aucun des trois appels du simulateur n'est servi par l'API v1 (§11 du
/// contrat, arbitrage n° 73). Les écrire « en attendant » produirait un client
/// qui appelle du vide — c'est précisément pourquoi ils **ne figurent pas dans
/// `ApiEndpoints`**.
///
/// Cette interface est là pour que le jour où la table `notifications` existera,
/// on **branche** une implémentation au lieu de réécrire un écran. C'est ce qui
/// distingue « pas branché » de « pas fait ».
abstract interface class NotificationRepository {
  /// `GET /me/notifications` — **absent de l'API v1**.
  Future<List<NotificationCitoyen>> lister();

  /// `POST /me/notifications/{id}/read` — **absent de l'API v1**.
  Future<void> marquerLue(String id);

  /// `POST /me/notifications/read-all` — **absent de l'API v1**.
  Future<void> marquerToutLu();
}

/// L'implémentation qui dit la vérité : il n'y a rien à appeler.
///
/// POURQUOI elle lève plutôt que de rendre une liste vide : « un endpoint qui
/// renverrait éternellement une liste vide serait un indicateur qu'on ne mesure
/// pas », et une liste vide ferait croire à un compte sans activité. Personne ne
/// l'appelle tant que le drapeau de fonctionnalité est baissé ; si quelqu'un
/// l'appelait malgré tout, l'échec doit être bruyant et non silencieux.
class NotificationsNonServies implements NotificationRepository {
  const NotificationsNonServies();

  static const String _raison =
      'Aucun magasin de notifications n existe cote serveur (contrat API v1, '
      'section 11). L ecran 08 est ecrit derriere un drapeau de fonctionnalite '
      'et n est pas branche.';

  @override
  Future<List<NotificationCitoyen>> lister() =>
      Future<List<NotificationCitoyen>>.error(UnsupportedError(_raison));

  @override
  Future<void> marquerLue(String id) =>
      Future<void>.error(UnsupportedError(_raison));

  @override
  Future<void> marquerToutLu() => Future<void>.error(UnsupportedError(_raison));
}
