import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/network/api_endpoints.dart';
import 'package:verifall_app/core/network/auth_controller.dart';
import 'package:verifall_app/core/network/session_state.dart';
import 'package:verifall_app/core/router/app_router.dart';
import 'package:verifall_app/core/router/vf_routes.dart';
import 'package:verifall_app/presentation/features/auth/providers/auth_providers.dart';
import 'package:verifall_app/presentation/shared/vf_buttons.dart';

import 'support.dart';

/// Le parcours d'intégration de M1, tel que la section 6.6 le décrit :
///
/// > Splash → inscription → code → tableau de bord, puis déconnexion et
/// > **vérification que le cache est vide**.
///
/// POURQUOI il tourne sous `flutter test` et non sous `integration_test` : le
/// paquet `integration_test` n'est pas déclaré dans `pubspec.yaml`, et
/// `pubspec.yaml` appartient au socle. Ce test couvre la même chaîne — routes
/// réelles, gardes réelles, client réseau réel derrière un adaptateur — contre
/// un serveur scripté plutôt qu'un serveur de recette. La passe sur appareil
/// reste à faire à la livraison ; elle est signalée comme telle.
void main() {
  testWidgets(
    'accueil → inscription → code → tableau de bord → déconnexion → cache vide',
    (WidgetTester tester) async {
      final HarnaisAuth banc = HarnaisAuth(
        repondre: (RequestOptions requete) => switch (cheminDe(requete)) {
          ApiEndpoints.register => reponseJson(<String, Object?>{
            'data': <String, Object?>{
              'uuid': 'uuid-neuf',
              'email': 'rita@exemple.bj',
              // `register` NE REND PAS de jeton.
              'etape': 'code_envoye',
            },
          }, statut: 201),
          ApiEndpoints.otpVerify => reponseJson(
            corpsJeton(email: 'rita@exemple.bj', unites: 10),
          ),
          ApiEndpoints.logout => reponseJson(<String, Object?>{'data': null}),
          _ => reponseJson(<String, Object?>{'data': null}),
        },
      );

      // --- 1. L'ecran d'accueil, sans le moindre appel reseau ---------------
      await tester.pumpWidget(banc.application(depart: VfRoutes.splash));
      await tester.pump();
      expect(find.text('IDENTIFIER'), findsOneWidget);
      expect(banc.requetes, isEmpty);

      await toucher(tester, find.text('Créer un compte citoyen'));
      await tester.pumpAndSettle();
      expect(find.text('Rejoignez la boussole'), findsOneWidget);

      // --- 2. L'inscription --------------------------------------------------
      await tester.enterText(find.byType(TextFormField).at(0), 'Rita');
      await tester.enterText(find.byType(TextFormField).at(1), 'Adjovi');
      await tester.enterText(
        find.byType(TextFormField).at(2),
        'rita@exemple.bj',
      );
      await tester.enterText(find.byType(TextFormField).at(3), '+229 97000000');
      await tester.enterText(find.byType(TextFormField).at(4), 'Journaliste');
      await tester.enterText(
        find.byType(TextFormField).at(5),
        'motdepassetreslong',
      );
      await tester.pump();

      // Les deux consentements obligatoires, coches un par un — ils n'etaient
      // pas pre-coches.
      await toucher(
        tester,
        find.textContaining('J’accepte les conditions d’utilisation'),
      );
      await tester.pump();
      await toucher(
        tester,
        find.textContaining('J’autorise le traitement de mes contenus'),
      );
      await tester.pump();

      await toucher(tester, find.text('Créer mon compte'));
      await tester.pumpAndSettle();

      // Aucun jeton n'a ete emis : le compte existe, il n'est pas active.
      expect(banc.jetons.token, isNull);
      expect(find.text('Confirmez votre adresse'), findsOneWidget);
      expect(find.text('rita@exemple.bj'), findsOneWidget);

      // --- 3. Le code --------------------------------------------------------
      await tester.enterText(find.byType(TextField).first, '481200');
      await tester.pump();
      await toucher(tester, find.widgetWithText(VfButton, 'Confirmer'));
      await tester.pumpAndSettle();

      expect(find.text(HarnaisAuth.texteTableauDeBord), findsOneWidget);
      // Les confettis vivent dans la superposition racine pendant trois
      // secondes : on laisse le temps passer pour ne pas terminer le test sur
      // un minuteur en attente.
      await tester.pump(const Duration(seconds: 4));
      expect(banc.jetons.token, jetonDeTest);
      expect(
        banc.conteneur.read(authControllerProvider).status,
        SessionStatus.connecte,
      );
      // Le solde vient du SERVEUR : le client lit `data.utilisateur.unites`, il
      // ne l'additionne pas.
      expect(
        banc.conteneur.read(authControllerProvider).utilisateur?.unites,
        10,
      );

      // Les trois appels, dans l'ordre, et rien d'autre.
      expect(banc.requetes.map(cheminDe).toList(), <String>[
        ApiEndpoints.register,
        ApiEndpoints.otpVerify,
      ]);

      // --- 4. La deconnexion, et la purge -----------------------------------
      // POURQUOI la deconnexion n'est PAS attendue directement : dans un test
      // de widget, le temps et les micro-taches sont simules. Un `await` sur un
      // futur que rien ne pompe ne rend jamais la main — c'est `pumpAndSettle`
      // qui fait avancer l'horloge et vider la file de micro-taches.
      final Future<void> deconnexion = banc.conteneur
          .read(fermerSessionProvider)
          .call();
      await tester.pumpAndSettle();
      await deconnexion;

      expect(cheminDe(banc.requetes.last), ApiEndpoints.logout);
      expect(banc.jetons.token, isNull, reason: 'stockage sécurisé purgé');
      expect(banc.purge.calls, greaterThanOrEqualTo(1), reason: 'base purgée');
      expect(
        banc.conteneur.read(authControllerProvider).status,
        SessionStatus.deconnecte,
      );

      // Et le jeton n'a traverse aucun journal, de bout en bout du parcours.
      expect(banc.journalComplet, isNot(contains(jetonDeTest)));
    },
  );

  group('Les gardes de route redirigent dans les deux sens', () {
    test('un déconnecté hors zone publique repart vers la connexion', () {
      expect(
        evaluateGuard(
          session: const SessionState.deconnecte(),
          location: VfRoutes.dashboard,
        ),
        VfRoutes.login,
      );
    });

    test('les six écrans de M1 restent atteignables sans session', () {
      for (final String chemin in <String>[
        VfRoutes.splash,
        VfRoutes.login,
        VfRoutes.register,
        VfRoutes.forgotPassword,
        VfRoutes.otp,
        VfRoutes.resetPassword,
      ]) {
        expect(
          evaluateGuard(
            session: const SessionState.deconnecte(),
            location: chemin,
          ),
          isNull,
          reason: chemin,
        );
      }
    });

    test('un connecté qui rouvre un écran de M1 repart au tableau de bord', () {
      for (final String chemin in <String>[
        VfRoutes.splash,
        VfRoutes.login,
        VfRoutes.register,
        VfRoutes.forgotPassword,
        VfRoutes.otp,
        VfRoutes.resetPassword,
      ]) {
        expect(
          evaluateGuard(
            session: const SessionState.restauree(),
            location: chemin,
          ),
          VfRoutes.dashboard,
          reason: chemin,
        );
      }
    });

    test(
      'l’état inconnu ne redirige rien : pas de clignotement au lancement',
      () {
        expect(
          evaluateGuard(
            session: const SessionState.inconnu(),
            location: VfRoutes.dashboard,
          ),
          isNull,
        );
      },
    );
  });
}
