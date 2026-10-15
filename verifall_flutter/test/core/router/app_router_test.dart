import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/network/session_state.dart';
import 'package:verifall_app/core/router/app_router.dart';
import 'package:verifall_app/core/router/routes_auth.dart';
import 'package:verifall_app/core/router/vf_routes.dart';
import 'package:verifall_app/data/models/auth_token.dart';
import 'package:verifall_app/presentation/shared/vf_bottom_nav.dart';
import 'package:verifall_app/presentation/shared/vf_shell_scaffold.dart';

/// Les tests des gardes de route.
///
/// « Les gardes de route redirigent dans les deux sens » est un point de la
/// définition de fini de M1 (§8.2). Le vérifier ici, sur la fonction pure,
/// coûte dix fois moins cher que de monter une application complète — et donne
/// la même certitude.
void main() {
  const UtilisateurResume utilisateur = UtilisateurResume(
    uuid: 'u-1',
    prenom: 'Rita',
    email: 'rita@exemple.invalid',
    unites: 12,
  );

  const SessionState inconnu = SessionState.inconnu();
  const SessionState deconnecte = SessionState.deconnecte();
  const SessionState connecte = SessionState.connecte(utilisateur: utilisateur);

  group('État inconnu', () {
    test('ne redirige jamais', () {
      // C'est ce qui evite le clignotement de l'ecran de connexion au lancement
      // d'une session deja ouverte (§1.3).
      for (final String location in <String>[
        VfRoutes.splash,
        VfRoutes.dashboard,
        VfRoutes.login,
        VfRoutes.profile,
      ]) {
        expect(
          evaluateGuard(session: inconnu, location: location),
          isNull,
          reason: 'aucune redirection depuis $location',
        );
      }
    });
  });

  group('Non connecte', () {
    test('toute route privee renvoie vers la connexion', () {
      for (final String location in <String>[
        VfRoutes.dashboard,
        VfRoutes.verify,
        VfRoutes.catalogue,
        VfRoutes.forum,
        VfRoutes.profile,
        VfRoutes.wallet,
        VfRoutes.report('abc'),
      ]) {
        expect(
          evaluateGuard(session: deconnecte, location: location),
          VfRoutes.login,
          reason: location,
        );
      }
    });

    test('les ecrans d authentification restent accessibles', () {
      for (final String location in VfRoutes.publicPaths) {
        expect(
          evaluateGuard(session: deconnecte, location: location),
          isNull,
          reason: location,
        );
      }
    });
  });

  group('Connecte', () {
    test('rouvrir un ecran d authentification renvoie au tableau de bord', () {
      // Le pendant mobile de `RedirectIfAuthenticated` cote Laravel
      // (arbitrage n° 59).
      for (final String location in VfRoutes.authPaths) {
        expect(
          evaluateGuard(session: connecte, location: location),
          VfRoutes.dashboard,
          reason: location,
        );
      }
    });

    test('le splash ne se rouvre pas non plus', () {
      // « Le splash n'apparaît qu'une fois. »
      expect(
        evaluateGuard(session: connecte, location: VfRoutes.splash),
        VfRoutes.dashboard,
      );
    });

    test('les routes privees passent', () {
      expect(
        evaluateGuard(session: connecte, location: VfRoutes.dashboard),
        isNull,
      );
      expect(
        evaluateGuard(session: connecte, location: VfRoutes.course('un-cours')),
        isNull,
      );
    });
  });

  group('Compte en cours de suppression', () {
    final SessionState enSuppression = SessionState.connecte(
      utilisateur: utilisateur,
      suppressionDemandeeLe: DateTime.utc(2026, 8, 20),
    );

    test('toute route mene a l ecran d état, une seule fois', () {
      // « Toute route repond 403 compte_en_suppression, sauf GET /me et
      // POST /auth/logout » (contrat §1.7). Le client traite donc le cas AU
      // NIVEAU DU ROUTEUR, pas ecran par ecran.
      expect(
        evaluateGuard(session: enSuppression, location: VfRoutes.dashboard),
        VfRoutes.accountBeingDeleted,
      );
      expect(
        evaluateGuard(
          session: enSuppression,
          location: VfRoutes.accountBeingDeleted,
        ),
        isNull,
      );
    });
  });

  group('Client obsolete', () {
    test('le 426 gagne sur tout le reste', () {
      // Il est intercepte GLOBALEMENT : il n'y a rien a faire dans
      // l'application tant qu'elle n'est pas mise a jour (§6.4).
      expect(
        evaluateGuard(
          session: connecte,
          location: VfRoutes.dashboard,
          clientObsolete: true,
        ),
        VfRoutes.updateRequired,
      );
      expect(
        evaluateGuard(
          session: inconnu,
          location: VfRoutes.splash,
          clientObsolete: true,
        ),
        VfRoutes.updateRequired,
      );
      expect(
        evaluateGuard(
          session: deconnecte,
          location: VfRoutes.updateRequired,
          clientObsolete: true,
        ),
        isNull,
      );
    });
  });

  group('Chemins', () {
    test('aucune route ne porte d identifiant auto-incremente', () {
      // La regle du chantier vaut pour les routes mobiles : uuid, slug, code.
      // Le contrat a tranche : sujet, breve et opportunite sont des SLUGS (§4).
      expect(VfRoutes.forumThread('mon-sujet'), '/forum/mon-sujet');
      expect(
        VfRoutes.hubOpportunity('charge-de-communication'),
        '/hub/opportunites/charge-de-communication',
      );
      expect(
        VfRoutes.hubArticle('pas-de-tablettes-gratuites'),
        '/hub-infos/pas-de-tablettes-gratuites',
      );
      // Le certificat fait exception au « uuid partout » : son CODE est concu
      // pour etre lu a voix haute et recopie.
      expect(VfRoutes.certificate('VF7KMPQR'), '/certificat/VF7KMPQR');
    });
  });

  group('Liens profonds', () {
    test('les deux cas nommes par le simulateur se resolvent', () {
      expect(
        VfDeepLinks.resolve(
          Uri.parse('verifall://cours/reconnaitre-une-intox'),
        ),
        VfRoutes.course('reconnaitre-une-intox'),
      );
      expect(
        VfDeepLinks.resolve(Uri.parse('verifall://hub/pas-de-tablettes')),
        VfRoutes.hubArticle('pas-de-tablettes'),
      );
      expect(
        VfDeepLinks.resolve(
          Uri.parse('verifall://hub/opportunites/charge-com'),
        ),
        VfRoutes.hubOpportunity('charge-com'),
      );
    });

    test('un lien qui n appartient pas a l application est refuse', () {
      // Un schema etranger ne doit JAMAIS ouvrir une route interne.
      expect(
        VfDeepLinks.resolve(Uri.parse('https://exemple.invalid/cours/x')),
        isNull,
      );
      expect(VfDeepLinks.resolve(Uri.parse('autre://cours/x')), isNull);
    });

    test('un lien vide ouvre le tableau de bord', () {
      expect(VfDeepLinks.resolve(Uri.parse('verifall://')), VfRoutes.dashboard);
    });
  });

  group('Onglet courant', () {
    test('chaque famille de chemin retombe sur le bon onglet', () {
      expect(VfShellScaffold.tabForLocation(VfRoutes.dashboard), VfTab.accueil);
      expect(
        VfShellScaffold.tabForLocation(VfRoutes.course('x')),
        VfTab.apprendre,
      );
      expect(VfShellScaffold.tabForLocation(VfRoutes.verify), VfTab.verifier);
      expect(
        VfShellScaffold.tabForLocation(VfRoutes.report('x')),
        VfTab.verifier,
      );
      expect(VfShellScaffold.tabForLocation(VfRoutes.forum), VfTab.communaute);
      expect(VfShellScaffold.tabForLocation(VfRoutes.hub), VfTab.communaute);
      expect(VfShellScaffold.tabForLocation(VfRoutes.wallet), VfTab.profil);
      expect(VfShellScaffold.tabForLocation(VfRoutes.settings), VfTab.profil);
    });

    test('chaque onglet a une destination', () {
      for (final VfTab tab in VfTab.values) {
        expect(VfShellScaffold.pathForTab(tab), startsWith('/'));
      }
    });
  });

  group('Les deux 403 de COMPTE se traitent au routeur (n° 136)', () {
    test('un compte en suppression signale par le SERVEUR redirige aussi', () {
      // L'information n'entrait dans l'application que par `GET /me`, appele par
      // le seul parcours du compte : un ecran de l'Academie qui essuyait ce 403
      // affichait un echec ordinaire. L'intercepteur le pose desormais pour
      // tout le monde, SANS fabriquer de date — le 403 ne la porte pas.
      final SessionState signale = connecte.withDeletionReportedByServer();
      expect(signale.isBeingDeleted, isTrue);
      expect(
        signale.suppressionDemandeeLe,
        isNull,
        reason:
            'un DateTime.now() ferait afficher un delai de retractation faux',
      );
      expect(
        evaluateGuard(session: signale, location: VfRoutes.catalogue),
        VfRoutes.accountBeingDeleted,
      );
    });

    test('un compte inactif reprend la saisie du code, AVEC son adresse', () {
      final SessionState inactif = connecte.withInactiveAccount();
      expect(
        evaluateGuard(session: inactif, location: VfRoutes.catalogue),
        cheminActivation(utilisateur.email),
      );
      // Une fois sur l'ecran de code, on ne boucle pas.
      expect(evaluateGuard(session: inactif, location: VfRoutes.otp), isNull);
    });

    test('sans adresse connue, on ne renvoie vers AUCUN ecran de code', () {
      // Une session restauree du stockage securise ne porte pas de profil.
      // Envoyer quelqu'un sur un ecran de code sans savoir a quelle adresse il a
      // ete envoye serait une impasse.
      const SessionState restauree = SessionState.restauree();
      expect(
        evaluateGuard(
          session: restauree.withInactiveAccount(),
          location: VfRoutes.catalogue,
        ),
        isNull,
      );
    });

    test('la suppression l emporte sur le compte inactif', () {
      final SessionState lesDeux = connecte
          .withInactiveAccount()
          .withDeletionReportedByServer();
      expect(
        evaluateGuard(session: lesDeux, location: VfRoutes.catalogue),
        VfRoutes.accountBeingDeleted,
      );
    });
  });
}
