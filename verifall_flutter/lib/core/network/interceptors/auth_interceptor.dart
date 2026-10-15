import 'package:dio/dio.dart';

import '../../../data/models/api_envelope.dart';
import '../../../data/models/api_error_code.dart';
import '../../../data/models/json_reader.dart';

/// Troisième intercepteur : la session.
///
/// **Il est volontairement minuscule, et c'était l'intention du contrat.**
///
/// Le simulateur décrivait un « renouvellement silencieux sur 401 » avec verrou
/// de concurrence, file d'attente et rejeu. Le contrat l'écarte : « **il n'y a
/// pas de jeton de rafraîchissement**, et c'est délibéré » (§1.4). Un couple
/// accès/rafraîchissement protège les architectures où le jeton traverse des
/// services tiers ; il n'y a ici qu'un serveur, et le couple aurait ajouté un
/// second secret sur le téléphone, une route et un état de plus, pour un gain
/// nul.
///
/// Ce qui reste donc à faire sur `401` : **purger, et présenter la connexion.
/// Ne pas boucler sur une tentative de renouvellement — il n'y a rien à
/// renouveler.**
///
/// # Les deux `403` qui ne sont pas des refus d'écran
///
/// Le contrat distingue trois `403` par leur **code métier** (§2.2), et deux
/// d'entre eux décrivent l'état du **compte**, pas celui de la requête :
///
/// - `compte_en_suppression` — « **toute** route répond ainsi, sauf `GET /me` et
///   `POST /auth/logout` » (§1.7). Ce n'est donc pas une erreur d'écran : c'est
///   une destination, et elle vaut pour les trente écrans à la fois ;
/// - `compte_inactif` — le jeton est valable, l'adresse n'a jamais été prouvée.
///   La réponse est partout la même : reprendre la saisie du code.
///
/// Tant qu'ils n'étaient pas ici, l'information n'entrait dans l'application que
/// par `GET /me`, appelé par le seul parcours du compte : un écran de l'Académie
/// ou du forum qui essuyait ce 403 affichait un échec ordinaire. M1 et M5 l'ont
/// tous deux signalé ; arbitrage n° 136.
///
/// Le **troisième** `403`, `droit_manquant`, reste ce qu'il est : un refus qui
/// appartient à l'écran, parce que lui seul sait dire ce que la personne peut
/// faire à la place.
class AuthInterceptor extends Interceptor {
  const AuthInterceptor({
    required this.onUnauthenticated,
    required this.onClientObsolete,
    required this.onAccountInactive,
    required this.onAccountBeingDeleted,
  });

  /// Purge le jeton, purge la base locale, presente la connexion.
  final Future<void> Function() onUnauthenticated;

  /// Leve l'ecran bloquant du 426.
  final void Function() onClientObsolete;

  /// `403 compte_inactif` : reprendre la saisie du code d'activation.
  final void Function() onAccountInactive;

  /// `403 compte_en_suppression` : ouvrir l'ecran d'etat du compte.
  final void Function() onAccountBeingDeleted;

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    if (_isUnauthenticated(err)) {
      // La deconnexion purge le stockage securise ET la base locale. Elle est
      // attendue avant de propager l'erreur : sinon l'ecran pourrait relire un
      // cache que la purge n'a pas encore vide.
      await onUnauthenticated();
    } else if (_codeMetierDu403(err) == ApiErrorCode.compteInactif) {
      onAccountInactive();
    } else if (_codeMetierDu403(err) == ApiErrorCode.compteEnSuppression) {
      onAccountBeingDeleted();
    } else if (_isObsoleteClient(err)) {
      // Le 426 est intercepte GLOBALEMENT, pas ecran par ecran (§6.4) : le jour
      // ou le plancher est releve, toutes les requetes echouent en meme temps,
      // et trente ecrans ne peuvent pas chacun decider d'ouvrir le magasin.
      onClientObsolete();
    }
    handler.next(err);
  }

  bool _isObsoleteClient(DioException err) => err.response?.statusCode == 426;

  /// Le code metier d'un `403`, ou `null` si ce n'en est pas un.
  ///
  /// On lit le CODE et pas le seul statut : `droit_manquant` porte le meme 403
  /// et n'a rien a voir, et un 403 pose par un mandataire d'infrastructure ne
  /// doit pas envoyer quelqu'un sur un ecran de suppression de compte.
  ApiErrorCode? _codeMetierDu403(DioException err) {
    final Response<dynamic>? response = err.response;
    if (response == null || response.statusCode != 403) {
      return null;
    }
    final Map<String, Object?> body = asJsonObject(response.data);
    if (body.isEmpty) {
      return null;
    }
    return ApiEnvelope.fromJson(body).error?.code;
  }

  /// Un jeton révoqué et un jeton absent se répondent pareil (§1.5) : le client
  /// ne peut pas les distinguer et n'a pas à essayer.
  bool _isUnauthenticated(DioException err) {
    final Response<dynamic>? response = err.response;
    if (response == null) {
      return false;
    }
    if (response.statusCode != 401) {
      return false;
    }
    // On confirme par le code du contrat plutot que par le seul statut : un 401
    // pose par un mandataire d'infrastructure ne doit pas fermer la session de
    // l'utilisateur.
    final Map<String, Object?> body = asJsonObject(response.data);
    if (body.isEmpty) {
      return true;
    }
    final ApiErrorBody? error = ApiEnvelope.fromJson(body).error;
    return error == null || error.code == ApiErrorCode.nonAuthentifie;
  }
}
