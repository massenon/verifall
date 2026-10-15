import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/core/telemetry/incident.dart';
import 'package:verifall_app/core/telemetry/puits_de_telemetrie.dart';
import 'package:verifall_app/core/telemetry/rapporteur_de_plantage.dart';

import '../../support/fakes.dart';

/// La télémétrie de plantage.
///
/// # Ce qui manquait, et que ces tests verrouillent
///
/// L'application n'installait **aucun** gestionnaire d'erreur global. Une
/// exception non capturée s'imprimait dans la console de développement et
/// disparaissait ; sur le téléphone de quelqu'un, elle ne laissait aucune trace
/// nulle part. Le champ `telemetryDsn` était lu depuis l'environnement et aucun
/// fichier ne le consommait.
///
/// Les tests portent sur la **frontière** : ce qui reste sur l'appareil, ce qui
/// peut en sortir, et ce qui ne doit jamais y figurer.
class _PuitsEspion implements PuitsDeTelemetrie {
  final List<Incident> recus = <Incident>[];

  @override
  Future<void> envoyer(Incident incident) async => recus.add(incident);
}

void main() {
  late RecordingLogSink journalLocal;
  late AppLogger journal;

  setUp(() {
    journalLocal = RecordingLogSink();
    journal = AppLogger(config: testConfig(), sink: journalLocal);
  });

  RapporteurDePlantage rapporteur({
    PuitsDeTelemetrie? puits,
    bool consent = true,
  }) => RapporteurDePlantage(
    journal: journal,
    identifiantDInstallation: 'inst-123',
    versionApplication: '1.0.0',
    plateforme: 'android',
    consentement: () => consent,
    puits: puits,
  );

  group('la frontière de l’appareil', () {
    test('journalise TOUJOURS, même sans puits ni consentement', () async {
      /*
       | Journaliser est local : rien ne quitte l'appareil, donc aucun
       | consentement n'est en jeu. C'est exactement ce qui manquait — avant ce
       | lot, l'incident n'atteignait ni journal, ni écran, ni serveur.
       */
      await rapporteur(
        puits: null,
        consent: false,
      ).signaler(NatureDIncident.erreurNonCapturee, 'quelque chose a cassé');

      expect(journalLocal.entries, hasLength(1));
      expect(journalLocal.entries.single.message, contains('a cassé'));
    });

    test('n’envoie RIEN sans consentement', () async {
      /*
       | Le §7.6 : « la collecte est soumise au consentement ». Aucune case de
       | télémétrie n'existe aujourd'hui à l'écran 03 — la porte est donc fermée
       | par défaut, et ce test empêche qu'on l'ouvre par distraction.
       */
      final _PuitsEspion espion = _PuitsEspion();

      await rapporteur(
        puits: espion,
        consent: false,
      ).signaler(NatureDIncident.erreurNonCapturee, 'quelque chose a cassé');

      expect(espion.recus, isEmpty);
      // Mais la trace locale existe : on ne perd pas le diagnostic.
      expect(journalLocal.entries, hasLength(1));
    });

    test(
      'envoie quand le consentement est donné ET le puits branché',
      () async {
        final _PuitsEspion espion = _PuitsEspion();

        await rapporteur(
          puits: espion,
          consent: true,
        ).signaler(NatureDIncident.echecDeRejeu, 'la file n’est pas partie');

        expect(espion.recus, hasLength(1));
        expect(espion.recus.single.nature, NatureDIncident.echecDeRejeu);
      },
    );
  });

  group('ce qui ne sort JAMAIS (§7.6)', () {
    test(
      'expurge l’adresse e-mail, le jeton et les suites de chiffres',
      () async {
        /*
       | La liste du §7.6 est absolue : adresse, téléphone, jetons, codes à
       | usage unique. La rédaction s'applique DÈS LA CONSTRUCTION, donc au
       | journal local aussi — un journal local finit dans un rapport de bogue
       | que la personne joint elle-même.
       */
        final _PuitsEspion espion = _PuitsEspion();

        await rapporteur(puits: espion).signaler(
          NatureDIncident.erreurNonCapturee,
          'échec pour rene@verifall.com avec Bearer abcdef123456 et le code 448211',
        );

        final Incident sorti = espion.recus.single;

        expect(sorti.message, isNot(contains('rene@verifall.com')));
        expect(sorti.message, isNot(contains('abcdef123456')));
        expect(sorti.message, isNot(contains('448211')));

        // Et la trace locale est expurgée de la même façon.
        expect(
          journalLocal.entries.single.message,
          isNot(contains('rene@verifall.com')),
        );
      },
    );

    test(
      'ne transporte qu’un identifiant d’installation, jamais un compte',
      () async {
        final _PuitsEspion espion = _PuitsEspion();

        await rapporteur(
          puits: espion,
        ).signaler(NatureDIncident.erreurDeCadriciel, 'oups');

        final Map<String, Object?> charge = espion.recus.single.versJson();

        expect(charge['installation'], 'inst-123');
        // La forme transmise est close : aucune clé nominative ne s'y glisse.
        expect(charge.keys.toSet(), <String>{
          'nature',
          'message',
          'installation',
          'version',
          'plateforme',
          'survenu_le',
        });
      },
    );
  });

  test('ne lève JAMAIS, même si le puits échoue', () async {
    /*
     | Une télémétrie qui plante en rapportant un plantage masquerait le défaut
     | d'origine et en ajouterait un.
     */
    await expectLater(
      rapporteur(
        puits: _PuitsCasse(),
      ).signaler(NatureDIncident.erreurNonCapturee, 'oups'),
      completes,
    );
  });
}

class _PuitsCasse implements PuitsDeTelemetrie {
  @override
  Future<void> envoyer(Incident incident) async =>
      throw StateError('le réseau est tombé');
}
