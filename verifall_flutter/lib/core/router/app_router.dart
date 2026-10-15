import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../presentation/shared/vf_shell_scaffold.dart';
import '../../presentation/shared/vf_system_pages.dart';
import '../logging/app_logger.dart';
import '../logging/logging_providers.dart';
import '../network/auth_controller.dart';
import '../network/session_state.dart';
// Les SIX fichiers de routes, importes DES LE SOCLE.
//
// POURQUOI ils sont tous la alors que cinq d'entre eux sont vides : cet
// agregateur est l'un des deux seuls endroits ou les cinq parcours devraient
// normalement ecrire tous ensemble. En important les six d'avance, personne ne
// rouvre ce fichier, et les cinq branches se fusionnent sans conflit (§8.0).
import 'routes_auth.dart';
import 'routes_community.dart';
import 'routes_home.dart';
import 'routes_lms.dart';
import 'routes_profile.dart';
import 'routes_verify.dart';
import 'vf_routes.dart';

/// Rafraîchit `go_router` quand la session change.
///
/// POURQUOI un `ChangeNotifier` plutôt que reconstruire le routeur : un routeur
/// reconstruit perd sa pile de navigation. On veut que la **garde** se rejoue,
/// pas que l'historique disparaisse.
class _SessionRefresh extends ChangeNotifier {
  void touch() => notifyListeners();
}

final Provider<_SessionRefresh> _sessionRefreshProvider =
    Provider<_SessionRefresh>((Ref ref) {
      final _SessionRefresh notifier = _SessionRefresh();
      ref.listen<SessionState>(
        authControllerProvider,
        (SessionState? previous, SessionState next) => notifier.touch(),
      );
      ref.onDispose(notifier.dispose);
      return notifier;
    });

/// Le routeur de l'application.
///
/// # Ce qu'il contient, et ce qu'il ne contient pas
///
/// Il contient la coque à cinq onglets, la garde unique, le repli de route et
/// l'écran bloquant du `426`. Il ne contient **aucun écran de produit** : chaque
/// parcours pose les siens dans son propre `routes_*.dart`.
///
/// # La garde, en une seule redirection
///
/// « Une redirection unique, centralisée » (§1.4) : non connecté vers
/// `/connexion` ; connecté qui rouvre un écran d'authentification vers son
/// tableau de bord. C'est le pendant mobile de `RedirectIfAuthenticated` côté
/// Laravel (arbitrage n° 59).
///
/// Trois précisions qui expliquent la forme du code :
///
/// - **l'état inconnu ne redirige pas.** C'est ce qui évite le clignotement de
///   l'écran de connexion au lancement d'une session déjà ouverte ;
/// - **le `426` gagne sur tout le reste.** Il est intercepté globalement, pas
///   écran par écran (04-RESEAU §4.6) ;
/// - **le compte en cours de suppression est traité ici**, et pas dans trente
///   écrans : « toute route répond `403 compte_en_suppression`, sauf `GET /me` et
///   `POST /auth/logout` » (§1.7 du contrat).
final Provider<GoRouter> appRouterProvider = Provider<GoRouter>((Ref ref) {
  final AppLogger logger = ref.watch(appLoggerProvider);

  return GoRouter(
    initialLocation: VfRoutes.splash,
    refreshListenable: ref.watch(_sessionRefreshProvider),
    debugLogDiagnostics: false,
    redirect: (_, GoRouterState state) => _guard(ref, state, logger),
    // Un lien profond doit se resoudre APPLICATION FERMEE, et retomber sur un
    // repli explicite si le contenu a ete depublie — ce qui arrive pour une
    // opportunite archivee a J+7 ou une breve retiree (§1.4).
    onException: (BuildContext context, GoRouterState state, GoRouter router) {
      logger.warning(LogChannel.router, 'Route inconnue, repli affiche');
      router.go(VfRoutes.dashboard);
    },
    errorBuilder: (BuildContext context, GoRouterState state) =>
        VfRouteFallbackPage(
          detail: kReleaseMode ? null : state.matchedLocation,
        ),
    routes: <RouteBase>[
      // --- L'ecran bloquant du 426, porte par le socle -------------------
      GoRoute(
        path: VfRoutes.updateRequired,
        builder: (BuildContext context, GoRouterState state) =>
            const VfUpdateRequiredPage(),
      ),

      // --- La coque a cinq onglets ---------------------------------------
      ShellRoute(
        builder: (BuildContext context, GoRouterState state, Widget child) =>
            VfShellScaffold(child: child),
        routes: <RouteBase>[
          ...authShellRoutes,
          ...homeShellRoutes,
          ...verifyShellRoutes,
          ...lmsShellRoutes,
          ...communityShellRoutes,
          ...profileShellRoutes,
        ],
      ),

      // --- Les ecrans sans barre basse ------------------------------------
      // Lecture d'un rapport, d'une lecon, d'un certificat : « ceux qui ne la
      // portent pas ne doivent pas la faire reapparaitre a mi-parcours » (§1.4).
      ...authRootRoutes,
      ...homeRootRoutes,
      ...verifyRootRoutes,
      ...lmsRootRoutes,
      ...communityRootRoutes,
      ...profileRootRoutes,
    ],
  );
});

/// L'application est-elle bloquée sur un `426` ?
///
/// POURQUOI un provider séparé : l'intercepteur réseau le lève depuis n'importe
/// quelle requête, et la garde le lit. Sans lui, chaque écran devrait décider
/// quoi faire d'un `client_obsolete`.
final NotifierProvider<ObsoleteClientController, bool> obsoleteClientProvider =
    NotifierProvider<ObsoleteClientController, bool>(
      ObsoleteClientController.new,
    );

class ObsoleteClientController extends Notifier<bool> {
  @override
  bool build() => false;

  /// Une fois levé, l'état ne se baisse pas : une nouvelle version de
  /// l'application est nécessaire, et elle passe par un redémarrage.
  void markObsolete() => state = true;
}

String? _guard(Ref ref, GoRouterState state, AppLogger logger) {
  final String location = state.matchedLocation;

  // 1. Le 426 gagne sur tout. Il n'y a rien a faire dans l'application tant
  //    qu'elle n'est pas mise a jour.
  if (ref.read(obsoleteClientProvider)) {
    return location == VfRoutes.updateRequired ? null : VfRoutes.updateRequired;
  }

  final SessionState session = ref.read(authControllerProvider);

  // 2. Etat inconnu : on ne redirige PAS. Le stockage securise est encore en
  //    cours de lecture, et rediriger maintenant ferait clignoter l'ecran de
  //    connexion a chaque lancement d'une session deja ouverte.
  if (session.isUnknown) {
    return null;
  }

  // 3. Compte en cours de suppression : une seule destination, dictee par le
  //    contrat. L'annulation NE PASSE PAS par l'API — elle se fait par le lien
  //    signe recu par courriel, et l'ecran doit le dire.
  if (session.isSignedIn && session.isBeingDeleted) {
    return location == VfRoutes.accountBeingDeleted
        ? null
        : VfRoutes.accountBeingDeleted;
  }

  // 3 bis. Compte jamais active : le jeton est valable, l'adresse n'a jamais ete
  //        prouvee. Le contrat repond `403 compte_inactif` partout, et la
  //        reponse est partout la meme — reprendre la saisie du code, AVEC
  //        l'adresse connue (arbitrage n° 136).
  //
  //        Sans adresse connue — une session restauree du stockage securise ne
  //        porte pas de profil — on ne redirige PAS : envoyer quelqu'un sur un
  //        ecran de code sans savoir a quelle adresse il a ete envoye serait
  //        une impasse. L'ecran affiche alors le message du serveur, comme
  //        avant.
  if (session.isSignedIn && session.compteInactif) {
    final String? email = session.utilisateur?.email;
    if (email != null && email.isNotEmpty) {
      return location == VfRoutes.otp ? null : cheminActivation(email);
    }
  }

  final bool isPublic = VfRoutes.publicPaths.contains(location);

  // 4. Non connecte hors zone publique -> connexion.
  if (!session.isSignedIn && !isPublic) {
    logger.info(LogChannel.router, 'Garde : redirection vers la connexion');
    return VfRoutes.login;
  }

  // 5. Connecte qui rouvre un ecran d'authentification -> son tableau de bord.
  //    Le splash en fait partie : « il n'apparaît qu'une fois ».
  if (session.isSignedIn &&
      (VfRoutes.authPaths.contains(location) || location == VfRoutes.splash)) {
    return VfRoutes.dashboard;
  }

  return null;
}

/// La garde, exposée pour les tests.
///
/// POURQUOI elle est testable seule : « les gardes de route redirigent dans les
/// deux sens » est un point de la définition de fini de M1, et le vérifier en
/// montant une application complète coûterait dix fois plus cher pour la même
/// certitude.
@visibleForTesting
String? evaluateGuard({
  required SessionState session,
  required String location,
  bool clientObsolete = false,
}) {
  if (clientObsolete) {
    return location == VfRoutes.updateRequired ? null : VfRoutes.updateRequired;
  }
  if (session.isUnknown) {
    return null;
  }
  if (session.isSignedIn && session.isBeingDeleted) {
    return location == VfRoutes.accountBeingDeleted
        ? null
        : VfRoutes.accountBeingDeleted;
  }

  // 3 bis. Compte jamais active : le jeton est valable, l'adresse n'a jamais ete
  //        prouvee. Le contrat repond `403 compte_inactif` partout, et la
  //        reponse est partout la meme — reprendre la saisie du code, AVEC
  //        l'adresse connue (arbitrage n° 136).
  //
  //        Sans adresse connue — une session restauree du stockage securise ne
  //        porte pas de profil — on ne redirige PAS : envoyer quelqu'un sur un
  //        ecran de code sans savoir a quelle adresse il a ete envoye serait
  //        une impasse. L'ecran affiche alors le message du serveur, comme
  //        avant.
  if (session.isSignedIn && session.compteInactif) {
    final String? email = session.utilisateur?.email;
    if (email != null && email.isNotEmpty) {
      return location == VfRoutes.otp ? null : cheminActivation(email);
    }
  }
  if (!session.isSignedIn && !VfRoutes.publicPaths.contains(location)) {
    return VfRoutes.login;
  }
  if (session.isSignedIn &&
      (VfRoutes.authPaths.contains(location) || location == VfRoutes.splash)) {
    return VfRoutes.dashboard;
  }
  return null;
}
