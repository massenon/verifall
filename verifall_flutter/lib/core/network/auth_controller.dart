import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/models/auth_token.dart';
import '../session/solde_unites.dart';
import '../config/app_config.dart';
import '../config/config_providers.dart';
import '../logging/app_logger.dart';
import '../logging/logging_providers.dart';
import 'local_data_purger.dart';
import 'session_state.dart';
import 'token_store.dart';

/// La seule source de vérité sur « suis-je connecté » (§1.3).
///
/// Le socle possède ce contrôleur parce que trois choses en dépendent et
/// qu'aucune n'appartient à un parcours : la garde de route, l'intercepteur qui
/// pose l'en-tête `Authorization`, et la purge à la déconnexion.
///
/// M1 ne le réécrit pas : il appelle [signIn] après `POST /auth/login`,
/// `POST /auth/otp/verify` ou `POST /auth/password/reset` — les **trois** points
/// d'entrée qui rendent exactement la même forme (§1.2 du contrat), donc un
/// seul chemin de code.
class AuthController extends Notifier<SessionState> {
  @override
  SessionState build() => const SessionState.inconnu();

  TokenStore get _tokens => ref.read(tokenStoreProvider);

  AppLogger get _log => ref.read(appLoggerProvider);

  /// Lit le stockage sécurisé au démarrage et tranche entre connecté et
  /// déconnecté.
  ///
  /// POURQUOI l'état reste « inconnu » tant que cette lecture n'a pas rendu : le
  /// stockage sécurisé est asynchrone, et afficher `/connexion` pendant ce temps
  /// fait clignoter l'écran de connexion à chaque lancement.
  Future<void> restore() async {
    final AppConfig config = ref.read(appConfigProvider);

    // La surcharge de developpement court-circuite la lecture : c'est ce qui
    // rend M2 a M5 independants de la livraison de M1 (§8.0). Elle est refusee
    // en production par construction — `hasUsableDevSession` ne peut pas etre
    // vrai quand l'environnement est `production`.
    if (config.hasUsableDevSession) {
      await _tokens.write(config.devSessionToken);
      state = SessionState.connecte(
        utilisateur: UtilisateurResume(
          uuid: config.devSessionUserUuid,
          prenom: config.devSessionUserPrenom,
          email: config.devSessionUserEmail,
          unites: config.devSessionUserUnites,
        ),
        viaDeveloperOverride: true,
      );
      ref.read(soldeUnitesProvider.notifier).relu(config.devSessionUserUnites);
      _log.warning(
        LogChannel.session,
        'Session de developpement active : ce n est pas un compte réel.',
      );
      return;
    }

    final String? token = await _tokens.read();
    state = token == null || token.isEmpty
        ? const SessionState.deconnecte()
        : const SessionState.restauree();
    _log.info(LogChannel.session, 'Session restauree : ${state.status.name}');
  }

  /// Ouvre la session à partir de la réponse d'un des trois points d'entrée.
  Future<void> signIn(AuthToken token) async {
    await _tokens.write(token.token);
    state = SessionState.connecte(
      utilisateur: token.utilisateur,
      expireLe: token.expireLe,
    );
    // Le resume rendu AVEC le jeton est une lecture serveur, a l'instant de
    // l'emission : c'est une source legitime pour la pastille. Elle vieillira,
    // et c'est le tableau de bord ou le portefeuille qui la remplacera.
    ref.read(soldeUnitesProvider.notifier).relu(token.utilisateur.unites);
    _log.info(LogChannel.session, 'Session ouverte');
  }

  /// Enregistre le profil relu par `GET /me`.
  void updateUtilisateur(UtilisateurResume utilisateur) {
    if (!state.isSignedIn) {
      return;
    }
    state = state.withUtilisateur(utilisateur);
    ref.read(soldeUnitesProvider.notifier).relu(utilisateur.unites);
  }

  /// Enregistre le délai de rétractation RGPD lu par `GET /me` (§1.7).
  void setDeletionRequestedAt(DateTime? at) {
    state = state.withDeletionRequestedAt(at);
  }

  /// Un `403 compte_en_suppression` a été essuyé par une requête quelconque.
  ///
  /// POURQUOI c'est traité ici et pas dans l'écran qui a reçu le 403 : **toute**
  /// route répond ainsi, sauf `GET /me` et `POST /auth/logout`. Tant que
  /// l'information n'entrait dans l'application que par `GET /me` — appelé par le
  /// seul parcours du compte —, un écran de l'Académie ou du forum qui essuyait
  /// ce 403 affichait un échec ordinaire (arbitrage n° 136).
  void markAccountBeingDeleted() {
    if (!state.isSignedIn || state.suppressionSignaleeParLeServeur) {
      return;
    }
    state = state.withDeletionReportedByServer();
  }

  /// Un `403 compte_inactif` a été essuyé par une requête quelconque.
  ///
  /// Le compte existe, le jeton est valable, mais l'adresse n'a jamais été
  /// prouvée. La garde de route reprend alors la saisie du code **avec l'adresse
  /// connue** : la redemander serait absurde.
  void markAccountInactive() {
    if (!state.isSignedIn || state.compteInactif) {
      return;
    }
    state = state.withInactiveAccount();
  }

  /// Ferme la session et **purge tout**.
  ///
  /// [revoked] distingue une déconnexion volontaire d'un `401`. Dans les deux
  /// cas la purge est identique : le contrat ne permet pas de distinguer un
  /// jeton révoqué d'un jeton absent (§1.5), et le client n'a pas à essayer.
  ///
  /// POURQUOI la purge est totale et non sélective : sur un téléphone partagé —
  /// le cas ordinaire au Bénin (arbitrage n° 59) — un cache de lecture oublié
  /// est une fuite de données. Un tri « ce qui est sensible / ce qui ne l'est
  /// pas » se trompe toujours au moins une fois.
  Future<void> signOut({bool revoked = false}) async {
    await _tokens.clear();
    try {
      await ref.read(localDataPurgerProvider).purgeAll();
    } on Object catch (error, stackTrace) {
      // Une purge qui echoue doit se voir : c'est une fuite potentielle, pas un
      // detail. Mais elle ne doit pas empecher la deconnexion elle-meme.
      _log.error(
        LogChannel.session,
        'Purge locale en échec a la déconnexion',
        cause: error,
        stackTrace: stackTrace,
      );
    }
    state = const SessionState.deconnecte();
    // Un solde qui survivrait a la purge s'afficherait au compte suivant sur un
    // telephone partage.
    ref.read(soldeUnitesProvider.notifier).oublier();
    _log.info(
      LogChannel.session,
      revoked ? 'Session revoquee par le serveur' : 'Session fermee',
    );
  }
}

/// Le nom vient du simulateur, qui le cite écran par écran (§1.3). Le reprendre
/// évite la dérive où deux personnes nomment deux fois la même chose.
final NotifierProvider<AuthController, SessionState> authControllerProvider =
    NotifierProvider<AuthController, SessionState>(AuthController.new);

/// Le magasin de jetons, injecté pour être remplaçable en test (§1.5).
final Provider<TokenStore> tokenStoreProvider = Provider<TokenStore>(
  (Ref ref) => SecureTokenStore(),
);
