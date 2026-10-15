import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/config/app_config.dart';
import 'package:verifall_app/core/config/app_environment.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/core/logging/log_redactor.dart';

import '../../support/fakes.dart';

/// Les tests de la surcharge de session de développement.
///
/// C'est elle qui rend le parallélisme des cinq parcours possible : M2 à M5 ont
/// besoin d'un compte connecté, et si chacun attend M1, il n'y a plus de
/// parallélisme du tout (§8.0).
///
/// Et c'est elle qui doit être **refusée en production par construction** — le
/// même raisonnement que l'adaptateur simulé du back, qui « refuse de tourner en
/// production » (arbitrage n° 19).
void main() {
  _adresseDApiEnProduction();
  group('Session de developpement', () {
    test(
      'elle s active en developpement quand la configuration le demande',
      () {
        final AppConfig config = testConfig(
          environment: AppEnvironment.development,
          devSession: true,
          devToken: 'jeton-de-recette',
        );
        expect(config.devSessionEnabled, true);
        expect(config.hasUsableDevSession, true);
      },
    );

    test('elle s active en recette', () {
      // La recette a aussi besoin d'un compte injecte : c'est la ou les cinq
      // parcours sont valides ensemble (07-LIVRAISON §7.4).
      final AppConfig config = testConfig(
        environment: AppEnvironment.staging,
        devSession: true,
        devToken: 'jeton-de-recette',
      );
      expect(config.hasUsableDevSession, true);
    });

    test('elle est REFUSEE en production, meme demandee explicitement', () {
      final AppConfig config = testConfig(
        environment: AppEnvironment.production,
        devSession: true,
        devToken: 'jeton-de-recette',
      );
      // Le `&&` du constructeur l'interdit : ce n'est pas un reglage, c'est une
      // regle du socle.
      expect(config.devSessionEnabled, false);
      expect(config.hasUsableDevSession, false);
      // Et le garde-fou de demarrage ne leve donc pas : il n'y a rien a lever.
      expect(config.assertNoDeveloperSessionInProduction, returnsNormally);
    });

    test('copyWith ne peut pas la rouvrir en production', () {
      // `copyWith` est le chemin le plus court pour fabriquer, par inadvertance,
      // une configuration de production ouverte.
      final AppConfig config = testConfig(
        environment: AppEnvironment.development,
        devSession: true,
        devToken: 'jeton',
      ).copyWith(environment: AppEnvironment.production);

      expect(config.devSessionEnabled, false);
    });

    test('elle est inutilisable sans jeton', () {
      // Un jeton vide donnerait une session « connectee » qui echoue en 401 a la
      // premiere requete : pire qu'une session absente.
      final AppConfig config = testConfig(
        environment: AppEnvironment.development,
        devSession: true,
      );
      expect(config.devSessionEnabled, true);
      expect(config.hasUsableDevSession, false);
    });
  });

  group('Environnement', () {
    test('une valeur inconnue retombe sur la production', () {
      // Le repli le plus sur est le plus ferme : une faute de frappe dans un
      // fichier de configuration ne doit jamais OUVRIR des facilites.
      expect(AppEnvironment.parse('typo'), AppEnvironment.production);
      expect(AppEnvironment.parse(null), AppEnvironment.production);
      expect(AppEnvironment.parse(''), AppEnvironment.production);
      expect(AppEnvironment.parse('development'), AppEnvironment.development);
    });
  });

  group('Journalisation', () {
    test('rien ne sort sur la console en production', () {
      final RecordingLogSink sink = RecordingLogSink();
      final AppLogger logger = AppLogger(
        config: testConfig(environment: AppEnvironment.production),
        sink: sink,
      );

      logger.debug(LogChannel.session, 'trace');
      logger.info(LogChannel.session, 'information');
      expect(sink.entries, isEmpty);

      // Les avertissements et les erreurs, eux, partent vers la telemetrie.
      logger.warning(LogChannel.session, 'attention');
      expect(sink.entries.length, 1);
    });

    test('les champs sensibles sont expurges a la source', () {
      final RecordingLogSink sink = RecordingLogSink();
      final AppLogger logger = AppLogger(config: testConfig(), sink: sink);

      logger.info(
        LogChannel.network,
        'appel',
        data: <String, Object?>{
          'token': '17|secret',
          'mot_de_passe': 'p4ssw0rd',
          'code': '123456',
          'email': 'rita@exemple.invalid',
          'affirmation': 'Le ministre aurait declare que...',
          'statut': 'ok',
        },
      );

      final Map<Object?, Object?> data =
          sink.entries.single.data! as Map<Object?, Object?>;
      expect(data['token'], LogRedactor.mask);
      expect(data['mot_de_passe'], LogRedactor.mask);
      expect(data['code'], LogRedactor.mask);
      expect(data['email'], LogRedactor.mask);
      // Le contenu soumis a verification est le plus piegeux : c'est du texte
      // utilisateur, souvent nominatif.
      expect(data['affirmation'], LogRedactor.mask);
      // Ce qui n'est pas sensible reste lisible, sinon le journal ne sert plus.
      expect(data['statut'], 'ok');
    });

    test('le texte libre est expurge aussi', () {
      // Un message d'erreur de bibliotheque tierce recopie volontiers l'URL
      // complete, en-tete compris.
      expect(
        LogRedactor.redactFreeText('contact rita@exemple.invalid'),
        'contact ${LogRedactor.mask}',
      );
      expect(
        LogRedactor.redactFreeText('Authorization: Bearer 17|abcdef'),
        'Authorization: Bearer ${LogRedactor.mask}',
      );
      expect(
        LogRedactor.redactFreeText('code 483920'),
        'code ${LogRedactor.mask}',
      );
    });
  });
}

/// L'adresse d'API en production.
///
/// # Le défaut que ces tests ferment
///
/// `--dart-define-from-file` est facile à oublier, et la construction RÉUSSIT
/// sans lui : elle produit un paquet d'apparence normale dont `apiBaseUrl` vaut
/// la chaîne vide. L'application s'installe, s'ouvre, et chaque appel échoue —
/// sans que rien n'explique pourquoi.
///
/// C'est la famille de défaut qui traverse tout ce projet : une chaîne complète
/// qui ne mène nulle part, et qui ne dit rien. On la ferme au démarrage, là où
/// elle est encore lisible.
void _adresseDApiEnProduction() {
  group('Adresse d’API en production', () {
    test('REFUSE de démarrer sans adresse', () {
      final AppConfig config = testConfig(
        environment: AppEnvironment.production,
        apiBaseUrl: '',
      );

      expect(
        config.assertNoDeveloperSessionInProduction,
        throwsA(
          isA<StateError>().having(
            (StateError e) => e.message,
            'message',
            contains('dart-define-from-file'),
          ),
        ),
      );
    });

    test('REFUSE une adresse qui n’est pas en HTTPS', () {
      /*
       | Un jeton porteur qui voyage en clair est lisible par n'importe quel
       | intermédiaire du réseau. Sur un réseau mobile partagé, ce n'est pas une
       | hypothèse d'école.
       */
      final AppConfig config = testConfig(
        environment: AppEnvironment.production,
        apiBaseUrl: 'http://verifall.com',
      );

      expect(
        config.assertNoDeveloperSessionInProduction,
        throwsA(isA<StateError>()),
      );
    });

    test('accepte une adresse HTTPS renseignée', () {
      final AppConfig config = testConfig(
        environment: AppEnvironment.production,
        apiBaseUrl: 'https://verifall.com',
      );

      expect(config.assertNoDeveloperSessionInProduction, returnsNormally);
    });

    test('ne contraint RIEN hors production', () {
      // Un banc d'essai ou un prototype construit une configuration à la main,
      // et n'a pas à fournir d'adresse.
      final AppConfig config = testConfig(
        environment: AppEnvironment.development,
        apiBaseUrl: '',
      );

      expect(config.assertNoDeveloperSessionInProduction, returnsNormally);
    });
  });
}
