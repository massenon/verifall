import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/domain/entities/settings_reglages.dart';
import 'package:verifall_app/domain/usecases/profile/exporter_mes_donnees.dart';
import 'package:verifall_app/domain/usecases/profile/supprimer_mon_compte.dart';
import 'package:verifall_app/presentation/features/profile/providers/profile_dependencies.dart';
import 'package:verifall_app/presentation/features/profile/providers/settings_provider.dart';

import '_fixtures.dart';

/// Les réglages — l'écran le plus exposé sur le plan réglementaire.
void main() {
  late ServeurScripte serveur;
  late CacheEnMemoire cache;

  const String cleLecture = 'GET /me/settings';
  const String cleEcriture = 'PATCH /me/settings';
  const String cleExport = 'POST /me/export';
  const String cleSuppression = 'DELETE /me';

  setUp(() {
    cache = CacheEnMemoire();
    serveur = ServeurScripte(<String, Object? Function(RequestOptions)>{
      cleLecture: (_) => reponseReglages(),
      cleEcriture: (RequestOptions o) {
        final Map<String, Object?> corps = (o.data as Map<Object?, Object?>)
            .map((Object? k, Object? v) => MapEntry<String, Object?>('$k', v));
        final Object? notifications = corps['notifications'];
        final bool forum = notifications is Map
            ? (notifications['forum_activity'] as bool? ?? true)
            : true;
        return reponseReglages(forumActif: forum);
      },
      cleExport: (_) => <String, Object?>{
        'data': <String, Object?>{
          'uuid': 'export-1',
          'message': 'Preparation lancee. Vous recevrez un courriel.',
        },
      },
    });
    serveur.statuts[cleExport] = 202;
  });

  group('Une valeur voulue, jamais une bascule', () {
    test('le corps porte la valeur demandee, pas un ordre d inversion', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(settingsProvider, (_, _) {});

      await conteneur.read(settingsProvider.future);
      await conteneur
          .read(settingsProvider.notifier)
          .definirCanal(cle: 'forum_activity', valeur: false);

      final Map<String, Object?> corps = serveur.corpsDe(cleEcriture);
      expect(corps['notifications'], <String, Object?>{
        'forum_activity': false,
      });
      // Aucun verbe d'inversion : le corps est declaratif, et c'est ce qui rend
      // le rejeu sur : « une bascule perd sa cible quand la requete est rejouee
      // apres une coupure ».
      expect(corps.toString(), isNot(contains('toggle')));
    });

    test('un rejeu du MEME corps ne renverse pas le reglage', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(settingsProvider, (_, _) {});

      await conteneur.read(settingsProvider.future);
      await conteneur
          .read(settingsProvider.notifier)
          .definirCanal(cle: 'forum_activity', valeur: false);

      final Map<String, Object?> premierEnvoi = Map<String, Object?>.from(
        serveur.corpsDe(cleEcriture),
      );

      // Le rejeu : exactement le meme corps, une seconde fois. C'est ce que
      // fait la file d'ecriture au retour du reseau.
      await conteneur
          .read(settingsProvider.notifier)
          .definirCanal(cle: 'forum_activity', valeur: false);

      expect(serveur.corpsDe(cleEcriture), premierEnvoi);
      final Reglages apres = conteneur.read(settingsProvider).value!;
      final CanalNotification forum = apres.notifications.firstWhere(
        (CanalNotification c) => c.cle == 'forum_activity',
      );
      // Le reglage vaut TOUJOURS false. Avec une bascule, il serait revenu a
      // true — et l'utilisateur retrouverait l'inverse de ce qu'il a demande.
      expect(forum.active, isFalse);
    });

    test('la lettre d information est SEPAREE des preferences', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(settingsProvider, (_, _) {});

      await conteneur.read(settingsProvider.future);
      await conteneur
          .read(settingsProvider.notifier)
          .definirNewsletter(valeur: true);

      final Map<String, Object?> corps = serveur.corpsDe(cleEcriture);
      // Un consentement marketing, horodate et versionne (arbitrage n. 11) :
      // il ne voyage pas parmi les canaux de notification.
      expect(corps.keys.toSet(), <String>{'newsletter'});
      expect(corps['newsletter'], isTrue);
    });

    test('un échec ramene l interrupteur a son état anterieur', () async {
      final ServeurScripte refus = ServeurScripte(
        <String, Object? Function(RequestOptions)>{
          cleLecture: (_) => reponseReglages(),
          cleEcriture: (_) => <String, Object?>{
            'error': <String, Object?>{
              'code': 'validation',
              'message': 'Canal inconnu.',
            },
          },
        },
      );
      refus.statuts[cleEcriture] = 422;

      final ProviderContainer conteneur = conteneurDeTest(
        serveur: refus,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(settingsProvider, (_, _) {});

      await conteneur.read(settingsProvider.future);
      await expectLater(
        conteneur
            .read(settingsProvider.notifier)
            .definirCanal(cle: 'forum_activity', valeur: false),
        throwsA(isA<ValidationFailure>()),
      );

      final CanalNotification forum = conteneur
          .read(settingsProvider)
          .value!
          .notifications
          .firstWhere((CanalNotification c) => c.cle == 'forum_activity');
      // « Un interrupteur qui reste allume apres un echec est un mensonge. »
      expect(forum.active, isTrue);
    });
  });

  group('La suppression se fait en deux temps', () {
    test(
      'premier temps : mot de confirmation + mot de passe, rien de plus',
      () async {
        serveur.reponses[cleSuppression] = (_) => <String, Object?>{
          'data': <String, Object?>{'etape': 'code_envoye'},
        };
        serveur.statuts[cleSuppression] = 202;

        final ProviderContainer conteneur = conteneurDeTest(
          serveur: serveur,
          cache: cache,
        );
        addTearDown(conteneur.dispose);

        final SuppressionCompte etape = await conteneur
            .read(supprimerMonCompteProvider)
            .demanderLeCode(
              confirmation: motDeConfirmationSuppression,
              motDePasse: 'un-mot-de-passe',
            );

        expect(etape.etape, EtapeSuppression.codeEnvoye);
        final Map<String, Object?> corps = serveur.corpsDe(cleSuppression);
        expect(corps.keys.toSet(), <String>{'confirmation', 'mot_de_passe'});
        // Rien n'est supprime a ce stade : le serveur a repondu 202 et envoie
        // un code par courriel.
      },
    );

    test('second temps : le code s ajoute au meme corps', () async {
      serveur.reponses[cleSuppression] = (_) => <String, Object?>{
        'data': <String, Object?>{
          'etape': 'suppression_programmee',
          'suppression_demandee_le': '2026-08-25T09:00:00+00:00',
        },
      };

      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);

      final SuppressionCompte resultat = await conteneur
          .read(supprimerMonCompteProvider)
          .confirmer(
            confirmation: motDeConfirmationSuppression,
            motDePasse: 'un-mot-de-passe',
            code: '123456',
          );

      expect(resultat.etape, EtapeSuppression.suppressionProgrammee);
      expect(resultat.demandeeLe, isNotNull);
      expect(serveur.corpsDe(cleSuppression).keys.toSet(), <String>{
        'confirmation',
        'mot_de_passe',
        'code',
      });
    });

    test(
      'le mot attendu est celui du CONTRAT, pas le nom de la personne',
      () async {
        final ProviderContainer conteneur = conteneurDeTest(
          serveur: serveur,
          cache: cache,
        );
        addTearDown(conteneur.dispose);

        // La maquette demandait de taper son nom (reserve A.12). Le contrat
        // fait foi, et le nom ne passe pas.
        await expectLater(
          conteneur
              .read(supprimerMonCompteProvider)
              .demanderLeCode(
                confirmation: 'Rita Ahouandjinou',
                motDePasse: 'un-mot-de-passe',
              ),
          throwsA(isA<ValidationFailure>()),
        );
        expect(serveur.comptePour(cleSuppression), 0);
      },
    );

    test('hors ligne, la suppression est refusee', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
        horsLigne: true,
      );
      addTearDown(conteneur.dispose);

      await expectLater(
        conteneur
            .read(supprimerMonCompteProvider)
            .demanderLeCode(
              confirmation: motDeConfirmationSuppression,
              motDePasse: 'un-mot-de-passe',
            ),
        throwsA(
          isA<ImpossibleActionFailure>().having(
            (ImpossibleActionFailure e) => e.serverMessage,
            'la raison',
            messageSuppressionHorsLigne,
          ),
        ),
      );
      expect(serveur.comptePour(cleSuppression), 0);
    });
  });

  group('L export RGPD', () {
    test('rend le message du serveur et n attend rien', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);

      final String message = await conteneur.read(exporterMesDonneesProvider)();
      expect(message, contains('courriel'));
      // Aucune archive dans la reponse : « elle contient tout ce que la
      // plateforme detient d'une personne ».
      expect(message, isNot(contains('http')));
    });

    test('un 409 remonte tel quel, sans relance', () async {
      final ServeurScripte conflit = ServeurScripte(
        <String, Object? Function(RequestOptions)>{
          cleExport: (_) => <String, Object?>{
            'error': <String, Object?>{
              'code': 'conflit',
              'message': 'Une demande est déjà en cours.',
            },
          },
        },
      );
      conflit.statuts[cleExport] = 409;

      final ProviderContainer conteneur = conteneurDeTest(
        serveur: conflit,
        cache: cache,
      );
      addTearDown(conteneur.dispose);

      await expectLater(
        conteneur.read(exporterMesDonneesProvider)(),
        throwsA(
          isA<ConflictFailure>().having(
            (ConflictFailure e) => e.serverMessage,
            'le message du serveur',
            'Une demande est déjà en cours.',
          ),
        ),
      );
      // Une seule tentative : le client ne relance pas.
      expect(conflit.comptePour(cleExport), 1);
    });

    test('hors ligne, l export est refuse', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
        horsLigne: true,
      );
      addTearDown(conteneur.dispose);

      await expectLater(
        conteneur.read(exporterMesDonneesProvider)(),
        throwsA(
          isA<ImpossibleActionFailure>().having(
            (ImpossibleActionFailure e) => e.serverMessage,
            'la raison',
            messageExportHorsLigne,
          ),
        ),
      );
      expect(serveur.comptePour(cleExport), 0);
    });
  });

  test('les canaux affiches sont ceux du serveur, avec ses libelles', () async {
    final ProviderContainer conteneur = conteneurDeTest(
      serveur: serveur,
      cache: cache,
    );
    addTearDown(conteneur.dispose);
    conteneur.listen(settingsProvider, (_, _) {});

    final Reglages reglages = await conteneur.read(settingsProvider.future);
    expect(reglages.notifications.map((CanalNotification c) => c.cle), <String>[
      'verification_results',
      'forum_activity',
    ]);
    // Le libelle vient du serveur : le client n'en tient aucune table.
    expect(reglages.notifications.first.libelle, 'Résultats de vérification');
  });
}
