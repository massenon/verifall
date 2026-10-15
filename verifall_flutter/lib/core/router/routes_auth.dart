/// Routes du parcours M1 - Authentification (auth).
///
/// # Pourquoi ce fichier existe
///
/// `app_router.dart` et `database.dart` sont les deux seuls endroits ou les cinq
/// parcours devraient normalement ecrire tous ensemble. On l'evite ainsi : chaque
/// parcours ecrit **son propre fichier** de routes, l'agregateur importe les six
/// **des le socle**, et personne ne rouvre l'agregateur.
///
/// # Les six ecrans sont TOUS des routes racine
///
/// Aucun ne porte la barre basse : le simulateur les rend avec `no-bar no-nav`,
/// et une barre a cinq onglets sur un ecran de connexion offrirait quatre
/// destinations que la garde refuserait aussitot. [authShellRoutes] reste donc
/// vide, et ce n'est pas un oubli.
///
/// # Le contrat de navigation offert aux quatre autres parcours
///
/// `403 compte_inactif` signifie « le code a six chiffres n'a jamais ete
/// valide ». Il peut arriver sur **n'importe quelle** route authentifiee, donc
/// dans n'importe quel parcours. La destination est l'ecran de saisie du code,
/// avec l'adresse deja connue : [cheminActivation] la construit.
///
/// C'est volontairement une fonction de `core/router/` et non un export de
/// `features/auth/` : « aucun import croise entre deux dossiers `features/` »
/// (section 8.1, regle 4). Les parcours s'appellent par la navigation.
library;

import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

import '../../domain/entities/auth_otp_purpose.dart';
import '../../presentation/features/auth/pages/forgot_password_page.dart';
import '../../presentation/features/auth/pages/login_page.dart';
import '../../presentation/features/auth/pages/otp_page.dart';
import '../../presentation/features/auth/pages/reset_password_page.dart';
import '../../presentation/features/auth/pages/signup_page.dart';
import '../../presentation/features/auth/pages/splash_carousel_page.dart';
import 'vf_page_transitions.dart';
import 'vf_routes.dart';

/// Le nom du parametre de requete qui porte l'usage du code.
const String parametreOtpPurpose = 'purpose';

/// Le nom du parametre de requete qui porte l'adresse destinataire.
///
/// POURQUOI l'adresse voyage dans l'URL interne et pas dans un provider partage :
/// un provider global survivrait a la sortie de l'ecran et ferait reapparaitre
/// l'adresse de quelqu'un d'autre sur un telephone partage. Le chemin, lui,
/// meurt avec la page.
const String parametreOtpEmail = 'email';

/// Le chemin de l'ecran de code, pour un usage et une adresse donnes.
String cheminOtp({required AuthOtpPurpose purpose, required String email}) {
  final Uri uri = Uri(
    path: VfRoutes.otp,
    queryParameters: <String, String>{
      parametreOtpPurpose: purpose.wireValue,
      parametreOtpEmail: email,
    },
  );
  return uri.toString();
}

/// La destination d'un `403 compte_inactif`, quel que soit le parcours qui le
/// recoit.
///
/// Le compte existe, le mot de passe est bon, mais l'adresse n'a jamais ete
/// prouvee. L'ecran reprend la saisie du code **avec l'adresse connue** : la
/// redemander serait absurde, et se tromper de casse relancerait le parcours a
/// zero.
String cheminActivation(String email) =>
    cheminOtp(purpose: AuthOtpPurpose.signup, email: email);

/// Ecrans de auth portant la barre basse. Il n'y en a aucun — voir l'en-tete.
final List<RouteBase> authShellRoutes = <RouteBase>[];

/// Les six ecrans de M1.
final List<RouteBase> authRootRoutes = <RouteBase>[
  GoRoute(
    path: VfRoutes.splash,
    name: 'splash',
    // `fade` et non `push` : c'est le premier ecran, il n'entre pas par la
    // droite — il est deja la.
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.fade<void>(
          key: state.pageKey,
          name: state.name,
          child: const SplashCarouselPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.login,
    name: 'connexion',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.fade<void>(
          key: state.pageKey,
          name: state.name,
          child: const LoginPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.register,
    name: 'inscription',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const SignupPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.forgotPassword,
    name: 'mot-de-passe-oublie',
    pageBuilder: (BuildContext context, GoRouterState state) =>
        VfPageTransitions.push<void>(
          key: state.pageKey,
          name: state.name,
          child: const ForgotPasswordPage(),
        ),
  ),
  GoRoute(
    path: VfRoutes.otp,
    name: 'otp',
    pageBuilder: (BuildContext context, GoRouterState state) {
      final Map<String, String> parametres = state.uri.queryParameters;
      return VfPageTransitions.push<void>(
        key: state.pageKey,
        name: state.name,
        child: OtpPage(
          purpose: AuthOtpPurpose.parse(parametres[parametreOtpPurpose]),
          email: parametres[parametreOtpEmail] ?? '',
        ),
      );
    },
  ),
  GoRoute(
    path: VfRoutes.resetPassword,
    name: 'nouveau-mot-de-passe',
    pageBuilder: (BuildContext context, GoRouterState state) {
      // Le code voyage par `extra`, JAMAIS par l'URL : un code a usage unique
      // dans une barre d'adresse interne finirait dans un lien profond partage.
      // `extra` ne survit pas a un lien profond, et c'est exactement ce qu'on
      // veut — l'ecran renvoie alors vers la demande de code.
      final NouveauMotDePasseArguments? arguments =
          state.extra is NouveauMotDePasseArguments
          ? state.extra! as NouveauMotDePasseArguments
          : null;
      return VfPageTransitions.push<void>(
        key: state.pageKey,
        name: state.name,
        child: ResetPasswordPage(arguments: arguments),
      );
    },
  ),
];
