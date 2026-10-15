import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/core/network/api_endpoints.dart';
import 'package:verifall_app/core/network/auth_controller.dart';
import 'package:verifall_app/core/network/network_providers.dart';
import 'package:verifall_app/core/network/session_state.dart';
import 'package:verifall_app/data/models/auth_token.dart';
import 'package:verifall_app/domain/entities/auth_consentements.dart';
import 'package:verifall_app/domain/entities/auth_inscription.dart';
import 'package:verifall_app/domain/repositories/auth_repository.dart';
import 'package:verifall_app/domain/usecases/auth/fermer_session.dart';
import 'package:verifall_app/domain/usecases/auth/ouvrir_session.dart';
import 'package:verifall_app/presentation/features/auth/providers/auth_providers.dart';

import 'support.dart';

/// La mécanique de session — c'est ici que se prouvent les points les plus
/// coûteux à rater de la définition de fini de M1.
void main() {
  group('Le jeton', () {
    test('part en stockage sécurisé, et nulle part ailleurs', () async {
      final HarnaisAuth banc = HarnaisAuth(
        repondre: (RequestOptions r) => reponseJson(corpsJeton()),
      );

      await banc.conteneur
          .read(authRepositoryProvider)
          .connexion(email: 'rita@exemple.invalid', motDePasse: 'motdepasse')
          .then(banc.conteneur.read(ouvrirSessionProvider).call);

      expect(banc.jetons.token, jetonDeTest);
      expect(
        banc.conteneur.read(authControllerProvider).status,
        SessionStatus.connecte,
      );
    });

    test('n’apparaît JAMAIS dans le journal', () async {
      final HarnaisAuth banc = HarnaisAuth(
        repondre: (RequestOptions r) => reponseJson(corpsJeton()),
      );

      final AuthToken jeton = await banc.conteneur
          .read(authRepositoryProvider)
          .connexion(email: 'rita@exemple.invalid', motDePasse: 'motdepasse');
      await banc.conteneur.read(ouvrirSessionProvider)(jeton);

      // Un second appel, authentifie cette fois : il pose l'en-tete
      // `Authorization`, que l'intercepteur de journalisation voit passer.
      await banc.conteneur.read(apiClientProvider).get(ApiEndpoints.me);

      expect(banc.journal.entries, isNotEmpty, reason: 'le journal doit vivre');
      expect(banc.journalComplet, isNot(contains(jetonDeTest)));
      expect(banc.journalComplet, isNot(contains('Bearer')));
      expect(banc.journalComplet, isNot(contains('motdepasse')));
    });
  });

  group('Les trois points d’entrée qui émettent un jeton', () {
    /// Le point de la définition de fini : « ils passent par UN SEUL chemin de
    /// code, la forme de réponse étant identique ».
    test(
      'login, otp/verify et password/reset installent le jeton de la même façon',
      () async {
        for (final String chemin in <String>[
          ApiEndpoints.login,
          ApiEndpoints.otpVerify,
          ApiEndpoints.passwordReset,
        ]) {
          final HarnaisAuth banc = HarnaisAuth(
            repondre: (RequestOptions r) =>
                reponseJson(corpsJeton(token: 'jeton-pour-$chemin')),
          );
          final AuthRepository depot = banc.conteneur.read(
            authRepositoryProvider,
          );
          final OuvrirSession ouvrir = banc.conteneur.read(
            ouvrirSessionProvider,
          );

          final AuthToken jeton = switch (chemin) {
            ApiEndpoints.login => await depot.connexion(
              email: 'rita@exemple.invalid',
              motDePasse: 'motdepasse',
            ),
            ApiEndpoints.otpVerify => await depot.verifierLeCode(
              email: 'rita@exemple.invalid',
              code: '481200',
            ),
            _ => await depot.reinitialiserLeMotDePasse(
              email: 'rita@exemple.invalid',
              code: '481200',
              motDePasse: 'motdepassetreslong',
            ),
          };
          await ouvrir(jeton);

          expect(cheminDe(banc.requetes.single), chemin);
          expect(banc.jetons.token, 'jeton-pour-$chemin');
          expect(
            banc.conteneur.read(authControllerProvider).utilisateur?.prenom,
            'Rita',
          );
          // La purge a lieu AVANT l'installation, sur les trois : sur un
          // telephone partage, la personne qui se connecte n'herite pas du
          // cache de la precedente.
          expect(banc.purge.calls, 1, reason: chemin);
        }
      },
    );

    test('`appareil` part sur les trois, et sur eux seulement', () async {
      final HarnaisAuth banc = HarnaisAuth(
        appareil: 'Android 13 (API 33)',
        repondre: (RequestOptions r) => cheminDe(r) == ApiEndpoints.register
            ? reponseJson(<String, Object?>{
                'data': <String, Object?>{
                  'uuid': 'u',
                  'email': 'rita@exemple.invalid',
                  'etape': 'code_envoye',
                },
              }, statut: 201)
            : reponseJson(corpsJeton()),
      );
      final AuthRepository depot = banc.conteneur.read(authRepositoryProvider);

      await depot.connexion(
        email: 'rita@exemple.invalid',
        motDePasse: 'motdepasse',
      );
      await depot.verifierLeCode(email: 'rita@exemple.invalid', code: '481200');
      await depot.reinitialiserLeMotDePasse(
        email: 'rita@exemple.invalid',
        code: '481200',
        motDePasse: 'motdepassetreslong',
      );
      await depot.demanderUnCode('rita@exemple.invalid');
      await depot.motDePasseOublie('rita@exemple.invalid');
      await depot.inscription(
        const DemandeInscription(
          prenom: 'Rita',
          nom: 'Adjovi',
          telephone: '+229 97 00 00 00',
          profession: 'Journaliste',
          email: 'rita@exemple.invalid',
          motDePasse: 'motdepassetreslong',
          consentements: AuthConsentements(cgu: true, confidentialite: true),
        ),
      );

      for (final RequestOptions requete in banc.requetes) {
        final bool emetUnJeton = <String>[
          ApiEndpoints.login,
          ApiEndpoints.otpVerify,
          ApiEndpoints.passwordReset,
        ].contains(cheminDe(requete));
        expect(
          corpsDe(requete)['appareil'],
          emetUnJeton ? 'Android 13 (API 33)' : isNull,
          reason: cheminDe(requete),
        );
      }
    });
  });

  group('Sur 401', () {
    /// Le point de la définition de fini : « aucun code de renouvellement de
    /// jeton ; un test vérifie qu'aucune seconde requête n'est émise ».
    test('purge et déconnexion, sans AUCUNE seconde requête', () async {
      final HarnaisAuth banc = HarnaisAuth(
        repondre: (RequestOptions r) => reponseJson(
          corpsErreur('non_authentifie', 'Identifiants invalides.'),
          statut: 401,
        ),
      );
      // On part d'une session ouverte : c'est le cas qui compte, celui d'un
      // jeton revoque en cours d'usage.
      banc.jetons.token = jetonDeTest;
      await banc.conteneur.read(authControllerProvider.notifier).restore();
      expect(
        banc.conteneur.read(authControllerProvider).status,
        SessionStatus.connecte,
      );

      await expectLater(
        banc.conteneur.read(apiClientProvider).get(ApiEndpoints.me),
        throwsA(isA<UnauthenticatedFailure>()),
      );

      expect(
        banc.requetes.length,
        1,
        reason: 'il n’y a rien à renouveler : une seule requête, jamais deux',
      );
      expect(banc.jetons.token, isNull);
      expect(banc.purge.calls, 1);
      expect(
        banc.conteneur.read(authControllerProvider).status,
        SessionStatus.deconnecte,
      );
    });

    test(
      'un compte non activé reçoit la MÊME réponse qu’un mot de passe faux',
      () async {
        // Le serveur repond 401 « Identifiants invalides. » dans les deux cas. Le
        // client ne peut pas les distinguer, et ce test verrouille le fait qu'il
        // n'essaie pas : la meme entree produit le meme echec.
        Failure? echecMotDePasseFaux;
        Failure? echecCompteInactif;

        for (int i = 0; i < 2; i++) {
          final HarnaisAuth banc = HarnaisAuth(
            repondre: (RequestOptions r) => reponseJson(
              corpsErreur('non_authentifie', 'Identifiants invalides.'),
              statut: 401,
            ),
          );
          try {
            await banc.conteneur
                .read(authRepositoryProvider)
                .connexion(
                  email: 'rita@exemple.invalid',
                  motDePasse: i == 0 ? 'faux' : 'bon-mais-compte-inactif',
                );
          } on Failure catch (e) {
            if (i == 0) {
              echecMotDePasseFaux = e;
            } else {
              echecCompteInactif = e;
            }
          }
        }

        expect(echecMotDePasseFaux, isA<UnauthenticatedFailure>());
        expect(echecCompteInactif, isA<UnauthenticatedFailure>());
        expect(
          echecCompteInactif!.serverMessage,
          echecMotDePasseFaux!.serverMessage,
        );
      },
    );
  });

  group('La déconnexion', () {
    test(
      'révoque, puis purge le stockage sécurisé ET la base locale',
      () async {
        final HarnaisAuth banc = HarnaisAuth(
          repondre: (RequestOptions r) =>
              reponseJson(<String, Object?>{'data': null}),
        );
        banc.jetons.token = jetonDeTest;
        await banc.conteneur.read(authControllerProvider.notifier).restore();

        await banc.conteneur.read(fermerSessionProvider)();

        expect(cheminDe(banc.requetes.single), ApiEndpoints.logout);
        expect(banc.jetons.token, isNull);
        expect(banc.purge.calls, 1);
        expect(
          banc.conteneur.read(authControllerProvider).status,
          SessionStatus.deconnecte,
        );
      },
    );

    test('ferme la session même quand le réseau est absent', () async {
      final HarnaisAuth banc = HarnaisAuth(
        repondre: (RequestOptions r) => throw DioException.connectionError(
          requestOptions: r,
          reason: 'hors ligne',
        ),
      );
      banc.jetons.token = jetonDeTest;
      await banc.conteneur.read(authControllerProvider.notifier).restore();

      final FermerSession fermer = banc.conteneur.read(fermerSessionProvider);
      await fermer();

      // Refuser de deconnecter quelqu'un parce que le reseau est absent
      // laisserait un jeton vivant sur un telephone qu'on veut rendre.
      expect(banc.jetons.token, isNull);
      expect(banc.purge.calls, 1);
      expect(
        banc.conteneur.read(authControllerProvider).status,
        SessionStatus.deconnecte,
      );
    });
  });

  group('L’inscription', () {
    test('ne rend PAS de jeton et n’ouvre aucune session', () async {
      final HarnaisAuth banc = HarnaisAuth(
        repondre: (RequestOptions r) => reponseJson(<String, Object?>{
          'data': <String, Object?>{
            'uuid': 'uuid-neuf',
            'email': 'rita@exemple.invalid',
            'etape': 'code_envoye',
          },
        }, statut: 201),
      );

      final AuthInscription inscription = await banc.conteneur
          .read(authRepositoryProvider)
          .inscription(
            const DemandeInscription(
              prenom: 'Rita',
              nom: 'Adjovi',
              telephone: '+229 97 00 00 00',
              profession: 'Journaliste',
              email: 'rita@exemple.invalid',
              motDePasse: 'motdepassetreslong',
              consentements: AuthConsentements(
                cgu: true,
                confidentialite: true,
              ),
            ),
          );

      expect(inscription.etape, 'code_envoye');
      expect(banc.jetons.token, isNull);
      expect(
        banc.conteneur.read(authControllerProvider).status,
        SessionStatus.inconnu,
      );
    });

    test('envoie exactement les champs du contrat, et pas `ville`', () async {
      final HarnaisAuth banc = HarnaisAuth(
        repondre: (RequestOptions r) => reponseJson(<String, Object?>{
          'data': <String, Object?>{
            'uuid': 'u',
            'email': 'rita@exemple.invalid',
            'etape': 'code_envoye',
          },
        }, statut: 201),
      );

      await banc.conteneur
          .read(authRepositoryProvider)
          .inscription(
            const DemandeInscription(
              prenom: 'Rita',
              nom: 'Adjovi',
              telephone: '+229 97 00 00 00',
              profession: 'Journaliste',
              email: 'rita@exemple.invalid',
              motDePasse: 'motdepassetreslong',
              consentements: AuthConsentements(
                cgu: true,
                confidentialite: true,
              ),
            ),
          );

      final Map<String, Object?> corps = corpsDe(banc.requetes.single);
      expect(
        corps.keys,
        containsAll(<String>[
          'prenom',
          'nom',
          'telephone',
          'profession',
          'email',
          'mot_de_passe',
          'cgu',
          'confidentialite',
          'newsletter',
        ]),
      );
      // `ville` n'est pas acceptee par `register` : l'envoyer perdrait la
      // saisie en silence (reserve A.11, arbitrage n° 85).
      expect(corps.containsKey('ville'), isFalse);
      // Le refus de la lettre d'information est trace comme l'accord : le champ
      // part meme a `false`.
      expect(corps['newsletter'], isFalse);
      // Pas de code de parrainage saisi : la cle est absente, pas nulle.
      expect(corps.containsKey('code_parrainage'), isFalse);
    });
  });
}
