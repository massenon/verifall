import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/network/api_endpoints.dart';
import 'package:verifall_app/core/router/routes_auth.dart';
import 'package:verifall_app/core/router/vf_routes.dart';
import 'package:verifall_app/domain/entities/auth_otp_purpose.dart';
import 'package:verifall_app/presentation/features/auth/pages/forgot_password_page.dart';
import 'package:verifall_app/presentation/features/auth/providers/password_reset_provider.dart';
import 'package:verifall_app/presentation/shared/vf_buttons.dart';

import 'support.dart';

/// Les six écrans, dans leurs états. **Les états, pas la mise en page** — la
/// mise en page est du ressort des captures de référence.
void main() {
  /// Un script qui refuse tout : sert aux écrans qu'on ne fait qu'afficher.
  ResponseBody muet(RequestOptions r) =>
      reponseJson(<String, Object?>{'data': null});

  group('Écran 01 · accueil', () {
    testWidgets('affiche la première diapositive et la trace complète', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(banc.application(depart: VfRoutes.splash));
      await tester.pump();

      expect(find.text('01'), findsOneWidget);
      expect(find.text('IDENTIFIER'), findsOneWidget);
      // Les quatre etapes de la methode sont a l'ecran, pas quatre pastilles
      // muettes.
      for (final String mot in <String>[
        'CLAIM',
        'SOURCE',
        'EVIDENCE',
        'VERDICT',
      ]) {
        expect(find.text(mot), findsOneWidget);
      }
      expect(find.text('Se connecter'), findsOneWidget);
      expect(find.text('Créer un compte citoyen'), findsOneWidget);
      expect(find.text('Passer'), findsOneWidget);
    });

    testWidgets('n’émet AUCUN appel réseau au premier lancement', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(banc.application(depart: VfRoutes.splash));
      await tester.pump();
      await tester.pump(const Duration(seconds: 5));

      // C'est le premier ecran que voit quelqu'un qui vient d'installer
      // l'application, souvent sur une connexion mediocre.
      expect(banc.requetes, isEmpty);
    });

    testWidgets('ne s’affiche pas une seconde fois', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      // Le drapeau est deja pose : l'application ouvre directement sur la
      // connexion.
      banc.jetons.onboardingSeen = true;

      await tester.pumpWidget(banc.application(depart: VfRoutes.splash));
      await tester.pumpAndSettle();

      expect(find.text('IDENTIFIER'), findsNothing);
      expect(find.text('Content de vous revoir.'), findsOneWidget);
    });

    testWidgets('pose le drapeau en sortant', (WidgetTester tester) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(banc.application(depart: VfRoutes.splash));
      await tester.pump();
      expect(banc.jetons.onboardingSeen, isFalse);

      await toucher(tester, find.text('Passer'));
      await tester.pumpAndSettle();

      expect(banc.jetons.onboardingSeen, isTrue);
      expect(find.text('Content de vous revoir.'), findsOneWidget);
    });
  });

  group('Écran 02 · connexion', () {
    testWidgets('n’affiche AUCUNE case « Rester connecté »', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(banc.application());
      await tester.pump();

      // Le contrat n'a aucun parametre correspondant : le jeton dure 30 jours
      // de facon glissante, pour tout le monde. Une case qui ne pilote rien est
      // un mensonge (arbitrage n° 86).
      expect(find.text('Rester connecté'), findsNothing);
      expect(find.text('Mot de passe oublié ?'), findsOneWidget);
    });

    testWidgets('refuse une adresse mal formée sans appeler le réseau', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(banc.application());
      await tester.pump();

      await tester.enterText(find.byType(TextFormField).first, 'rita');
      await toucher(tester, find.text('Se connecter'));
      await tester.pump();

      expect(find.text('Entrez une adresse e-mail valide.'), findsOneWidget);
      expect(banc.requetes, isEmpty);
    });

    testWidgets('affiche le message du serveur TEL QUEL sur un verrouillage', (
      WidgetTester tester,
    ) async {
      const String messageDuServeur =
          'Trop de tentatives. Réessayez dans 3 minute(s).';
      final HarnaisAuth banc = HarnaisAuth(
        repondre: (RequestOptions r) => reponseJson(<String, Object?>{
          ...corpsErreur('debit_depasse', messageDuServeur),
          'meta': <String, Object?>{'retry_after': 180},
        }, statut: 429),
      );
      await tester.pumpWidget(banc.application());
      await tester.pump();

      await tester.enterText(
        find.byType(TextFormField).first,
        'rita@exemple.bj',
      );
      await tester.enterText(find.byType(TextFormField).last, 'motdepasse');
      await toucher(tester, find.text('Se connecter'));
      await tester.pumpAndSettle();

      // Le message du serveur contient DEJA le delai : on l'affiche tel quel,
      // on ne fabrique pas de compte a rebours local.
      expect(find.text(messageDuServeur), findsOneWidget);
    });
  });

  group('Écran 03 · inscription', () {
    testWidgets('porte les sept champs du contrat, et pas « Ville »', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(banc.application(depart: VfRoutes.register));
      await tester.pump();

      for (final String etiquette in <String>[
        'Prénom',
        'Nom',
        'Adresse e-mail',
        'Téléphone',
        'Profession',
        'Code de parrainage (facultatif)',
      ]) {
        expect(find.text(etiquette), findsOneWidget, reason: etiquette);
      }
      // Reserve A.11 : `register` n'accepte pas `ville`.
      expect(find.text('Ville'), findsNothing);
      // L'etiquette annonce 12, jamais 8.
      expect(find.text('Mot de passe (12 caractères minimum)'), findsOneWidget);
    });

    testWidgets('les deux consentements obligatoires bloquent l’envoi', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(banc.application(depart: VfRoutes.register));
      await tester.pump();

      await tester.enterText(find.byType(TextFormField).at(0), 'Rita');
      await tester.enterText(find.byType(TextFormField).at(1), 'Adjovi');
      await tester.enterText(find.byType(TextFormField).at(2), 'r@exemple.bj');
      await tester.enterText(find.byType(TextFormField).at(3), '+229 97000000');
      await tester.enterText(find.byType(TextFormField).at(4), 'Journaliste');
      await tester.enterText(
        find.byType(TextFormField).at(5),
        'motdepassetreslong',
      );
      await tester.pump();

      await toucher(tester, find.text('Créer mon compte'));
      await tester.pump();

      expect(
        find.text('Les deux consentements obligatoires sont requis.'),
        findsOneWidget,
      );
      expect(banc.requetes, isEmpty);
    });
  });

  group('Écran 04 · mot de passe oublié', () {
    testWidgets('n’affiche AUCUN compte à rebours', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(
        banc.application(depart: VfRoutes.forgotPassword),
      );
      await tester.pump();

      // Le plafond de 3 demandes / 10 min est invisible au client PAR
      // CONSTRUCTION : un compte a rebours fabrique localement mentirait.
      expect(find.textContaining('seconde'), findsNothing);
      expect(find.textContaining('Réessayez dans'), findsNothing);
      expect(
        find.textContaining('la réponse est toujours la même'),
        findsOneWidget,
      );
    });

    testWidgets(
      'répond la même chose sur un compte inconnu et sur un plafond saturé',
      (WidgetTester tester) async {
        // Deux scenarios serveur radicalement differents, un seul rendu client.
        for (final ResponseBody Function(RequestOptions) scenario
            in <ResponseBody Function(RequestOptions)>[
              (RequestOptions r) => reponseJson(<String, Object?>{
                'data': <String, Object?>{
                  'message':
                      'Si un compte existe pour cette adresse, un code à 6 '
                      'chiffres vient d’être envoyé.',
                },
              }),
              (RequestOptions r) => reponseJson(
                corpsErreur('debit_depasse', 'Trop de demandes.'),
                statut: 429,
              ),
            ]) {
          final HarnaisAuth banc = HarnaisAuth(repondre: scenario);
          await tester.pumpWidget(
            banc.application(depart: VfRoutes.forgotPassword),
          );
          await tester.pump();

          await tester.enterText(
            find.byType(TextFormField).first,
            'rita@exemple.bj',
          );
          await toucher(tester, find.text('Envoyer le code'));
          // Le plancher de temps de reponse court : il egalise la latence, pour
          // qu'un chronometre ne renseigne pas plus qu'un message.
          await tester.pump(PasswordResetController.delaiPlancher);
          await tester.pumpAndSettle();

          expect(
            find.text(ForgotPasswordPage.messageIndifferencie),
            findsOneWidget,
          );
          // Et dans les deux cas, on passe a la saisie du code.
          expect(find.text('Vérifions que c’est bien vous'), findsOneWidget);
        }
      },
    );
  });

  group('Écran 05 · code de vérification', () {
    testWidgets('sert ses deux usages par son paramètre', (
      WidgetTester tester,
    ) async {
      for (final AuthOtpPurpose purpose in AuthOtpPurpose.values) {
        final HarnaisAuth banc = HarnaisAuth(repondre: muet);
        await tester.pumpWidget(
          banc.application(
            depart: cheminOtp(purpose: purpose, email: 'rita@exemple.bj'),
          ),
        );
        await tester.pump();

        expect(find.text(purpose.titre), findsOneWidget);
        expect(find.text(purpose.phrase), findsOneWidget);
        // L'adresse destinataire est rappelee : la redemander serait absurde.
        expect(find.text('rita@exemple.bj'), findsOneWidget);
      }
    });

    testWidgets('garde « Confirmer » inactif tant que le code est incomplet', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(
        banc.application(
          depart: cheminOtp(
            purpose: AuthOtpPurpose.signup,
            email: 'rita@exemple.bj',
          ),
        ),
      );
      await tester.pump();

      final Finder bouton = find.widgetWithText(VfButton, 'Confirmer');
      expect(tester.widget<VfButton>(bouton).onPressed, isNull);

      // Le collage d'un code complet remplit les six cases.
      await tester.enterText(find.byType(TextField).first, '481200');
      await tester.pump();

      expect(tester.widget<VfButton>(bouton).onPressed, isNotNull);
    });

    testWidgets('affiche le message du serveur, pas un compteur inventé', (
      WidgetTester tester,
    ) async {
      const String messageDuServeur =
          'Ce code a expiré. Demandez-en un nouveau.';
      final HarnaisAuth banc = HarnaisAuth(
        repondre: (RequestOptions r) => reponseJson(
          corpsErreur(
            'validation',
            messageDuServeur,
            details: <String, List<String>>{
              'code': <String>[messageDuServeur],
            },
          ),
          statut: 422,
        ),
      );
      await tester.pumpWidget(
        banc.application(
          depart: cheminOtp(
            purpose: AuthOtpPurpose.signup,
            email: 'rita@exemple.bj',
          ),
        ),
      );
      await tester.pump();

      await tester.enterText(find.byType(TextField).first, '481200');
      await tester.pump();
      await toucher(tester, find.widgetWithText(VfButton, 'Confirmer'));
      await tester.pumpAndSettle();

      expect(find.text(messageDuServeur), findsOneWidget);
      // Aucun « il vous reste N essais » : le serveur ne sert pas ce chiffre.
      expect(find.textContaining('essai'), findsNothing);
      expect(find.textContaining('tentative'), findsNothing);
      expect(cheminDe(banc.requetes.single), ApiEndpoints.otpVerify);
    });
  });

  group('Écran 06 · nouveau mot de passe', () {
    testWidgets('renvoie à la demande de code quand on arrive sans code', (
      WidgetTester tester,
    ) async {
      final HarnaisAuth banc = HarnaisAuth(repondre: muet);
      await tester.pumpWidget(banc.application(depart: VfRoutes.resetPassword));
      await tester.pumpAndSettle();

      // Un formulaire qui ne peut pas aboutir ne s'affiche pas.
      expect(find.text('Réinitialiser mon mot de passe'), findsOneWidget);
    });
  });
}
