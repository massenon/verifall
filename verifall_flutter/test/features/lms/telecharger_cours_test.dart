import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/data/datasources/local/pending_ops.dart';
import 'package:verifall_app/data/datasources/remote/lms/lms_remote_data_source.dart';
import 'package:verifall_app/data/repositories/lms/course_repository_impl.dart';
import 'package:verifall_app/domain/repositories/course_repository.dart';
import 'package:verifall_app/domain/usecases/lms/telecharger_cours.dart';

import '../../support/fakes.dart';
import 'support/lms_fakes.dart';

/// Le téléchargement d'un cours — **réserve C.3**.
///
/// La réserve craignait qu'un téléchargement du cours entier ne démarre tous les
/// chronomètres serveur à la fois. Le contrat l'empêche déjà :
/// `GET /courses/{slug}/lessons/{uuid}` répond `403` sur une leçon verrouillée.
///
/// Ces tests verrouillent la conséquence : **on ne demande que ce que le serveur
/// a ouvert**, et jamais le reste.
void main() {
  late AdaptateurScripte adaptateur;
  late CacheEnMemoire cache;
  late CourseRepositoryImpl depot;
  late TelechargerCours telecharger;

  setUp(() {
    cache = CacheEnMemoire();
    adaptateur = AdaptateurScripte(
      (_) => reponseJson(enveloppe(<String, Object?>{})),
    );
    final ApiClient client = clientDeTest(adaptateur);
    depot = CourseRepositoryImpl(
      distant: LmsRemoteDataSource(client),
      local: cache,
      file: PendingOpsQueue(
        store: InMemoryPendingOpsStore(),
        client: client,
        logger: loggerDeTest(),
      ),
    );
    telecharger = TelechargerCours(depot);
  });

  void repondreAvec({required List<Map<String, Object?>> lecons}) {
    adaptateur.repondre = (RequestOptions options) {
      if (options.path.contains('/lessons/')) {
        final String uuid = options.path.split('/lessons/').last;
        return reponseJson(
          enveloppe(
            leconJson(uuid: uuid, contenu: '<p>Contenu de $uuid.</p>'),
            meta: <String, Object?>{'secondes_requises': 45},
          ),
        );
      }
      return reponseJson(
        enveloppe(
          coursJson(lecons: lecons),
          meta: <String, Object?>{'inscription': inscriptionJson()},
        ),
      );
    };
  }

  test(
    'seules les leçons ouvertes sont demandees — jamais une verrouillee',
    () async {
      repondreAvec(
        lecons: <Map<String, Object?>>[
          leconJson(uuid: 'l1', position: 1, acquise: true),
          leconJson(uuid: 'l2', position: 2),
          leconJson(uuid: 'l3', position: 3, ouverte: false),
          leconJson(uuid: 'l4', position: 4, ouverte: false),
        ],
      );

      final EtatTelechargement fin = await telecharger.executer(
        slug: 'cours-neutre',
        onProgres: (_) {},
        estAnnule: () => false,
      );

      expect(fin.termine, isTrue);
      expect(fin.leconsAFaire, 2);
      expect(fin.leconsFaites, 2);

      final Iterable<String> chemins = adaptateur.recues
          .map((RequestOptions o) => o.path)
          .where((String p) => p.contains('/lessons/'));
      // Deux appels, pas quatre : essayer les verrouillees produirait un 403
      // par lecon, « c'est-a-dire une rafale d'appels refuses sur un reseau
      // facture au megaoctet ».
      expect(chemins.length, 2);
      expect(chemins.any((String p) => p.endsWith('l3')), isFalse);
      expect(chemins.any((String p) => p.endsWith('l4')), isFalse);

      // Et le cache le sait : la fiche dira quelles lecons sont lisibles.
      expect(await cache.leconsEnCache('cours-neutre'), <String>{'l1', 'l2'});
    },
  );

  test('le téléchargement s annule, et ce qui est déjà la reste', () async {
    repondreAvec(
      lecons: <Map<String, Object?>>[
        leconJson(uuid: 'l1', position: 1, acquise: true),
        leconJson(uuid: 'l2', position: 2, acquise: true),
        leconJson(uuid: 'l3', position: 3),
      ],
    );

    int faites = 0;
    final EtatTelechargement fin = await telecharger.executer(
      slug: 'cours-neutre',
      onProgres: (EtatTelechargement etat) => faites = etat.leconsFaites,
      // « Le telechargement est visible, chiffre en taille avant de commencer,
      // et ANNULABLE. »
      estAnnule: () => faites >= 1,
    );

    expect(fin.annule, isTrue);
    expect(fin.termine, isFalse);
    expect(fin.leconsFaites, 1);
    // Ce qui est deja la reste lisible hors ligne.
    expect(await cache.leconsEnCache('cours-neutre'), <String>{'l1'});
  });

  test('la taille annoncee est MESUREE, jamais estimee', () async {
    repondreAvec(
      lecons: <Map<String, Object?>>[
        leconJson(uuid: 'l1', position: 1, acquise: true),
      ],
    );

    await telecharger.executer(
      slug: 'cours-neutre',
      onProgres: (_) {},
      estAnnule: () => false,
    );

    // Le poids vient du cache, qui a compte les octets reellement ecrits.
    // Annoncer « environ 2 Mo » serait un chiffre invente.
    expect(await depot.octetsStockes('cours-neutre'), greaterThan(0));
  });

  test('un cours telecharge se supprime entierement', () async {
    repondreAvec(
      lecons: <Map<String, Object?>>[
        leconJson(uuid: 'l1', position: 1, acquise: true),
      ],
    );
    await telecharger.executer(
      slug: 'cours-neutre',
      onProgres: (_) {},
      estAnnule: () => false,
    );
    await depot.marquerTelecharge('cours-neutre');

    final FicheLue avant = await depot.fiche('cours-neutre');
    expect(avant.telechargeLe, isNotNull);

    await depot.supprimerTelechargement('cours-neutre');

    // « Un cours telecharge doit pouvoir se supprimer » — sa fiche, ses lecons,
    // ses compteurs.
    expect(await cache.leconsEnCache('cours-neutre'), isEmpty);
    expect(await depot.octetsStockes('cours-neutre'), 0);
  });

  test('un cours sans leçon ouverte ne telecharge rien', () async {
    repondreAvec(
      lecons: <Map<String, Object?>>[
        leconJson(uuid: 'l1', position: 1, ouverte: false),
      ],
    );

    final EtatTelechargement fin = await telecharger.executer(
      slug: 'cours-neutre',
      onProgres: (_) {},
      estAnnule: () => false,
    );

    expect(fin.leconsAFaire, 0);
    expect(
      adaptateur.recues.any((RequestOptions o) => o.path.contains('/lessons/')),
      isFalse,
    );
  });
}
