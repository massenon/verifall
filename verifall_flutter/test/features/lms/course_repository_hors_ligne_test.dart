import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/data/datasources/local/pending_ops.dart';
import 'package:verifall_app/data/datasources/remote/lms/lms_remote_data_source.dart';
import 'package:verifall_app/data/repositories/lms/course_repository_impl.dart';
import 'package:verifall_app/domain/entities/course_enrollment.dart';
import 'package:verifall_app/domain/entities/course_level.dart';
import 'package:verifall_app/domain/repositories/course_repository.dart';

import '../../support/fakes.dart';
import 'support/lms_fakes.dart';

/// **Le test central du parcours M3.**
///
/// « Le produit s'adresse à des gens qui suivent une leçon dans une cour
/// d'école, sans réseau. Une application qui affiche "pas de connexion" à ce
/// moment-là n'est pas dégradée : elle est absente. »
///
/// Chaque groupe coupe le réseau **après** un chargement réussi, et vérifie que
/// l'écran a de quoi s'afficher — avec sa date.
void main() {
  late AdaptateurScripte adaptateur;
  late CacheEnMemoire cache;
  late InMemoryPendingOpsStore fileStore;
  late PendingOpsQueue file;
  late CourseRepositoryImpl depot;

  final DateTime lundi = DateTime.utc(2026, 3, 2, 9);

  void construire() {
    final ApiClient client = clientDeTest(adaptateur);
    file = PendingOpsQueue(
      store: fileStore,
      client: client,
      logger: loggerDeTest(),
    );
    depot = CourseRepositoryImpl(
      distant: LmsRemoteDataSource(client),
      local: cache,
      file: file,
      horloge: () => lundi,
    );
  }

  setUp(() {
    cache = CacheEnMemoire();
    fileStore = InMemoryPendingOpsStore();
    adaptateur = AdaptateurScripte(
      (_) => reponseJson(enveloppe(<String, Object?>{})),
    );
    construire();
  });

  group('Le catalogue', () {
    test('reste consultable une fois le réseau coupe, avec sa date', () async {
      adaptateur.repondre = (_) =>
          reponseJson(enveloppePage(<Object?>[coursJson(slug: 'cours-a')]));
      final CatalogueLu enLigne = await depot.catalogue(FiltreCatalogue.aucun);
      expect(enLigne.vientDuCache, isFalse);
      expect(enLigne.page.cours.single.slug, 'cours-a');

      adaptateur.couperLeReseau();

      final CatalogueLu horsLigne = await depot.catalogue(
        FiltreCatalogue.aucun,
      );
      expect(horsLigne.page.cours.single.slug, 'cours-a');
      // Le bandeau « Donnees du <date> » ne peut exister que si ces deux
      // informations remontent jusqu'a l'ecran.
      expect(horsLigne.vientDuCache, isTrue);
      expect(horsLigne.lueLe, lundi);
    });

    test('hors ligne, un niveau jamais charge echoue plutôt que de servir '
        'une autre liste', () async {
      adaptateur.repondre = (_) =>
          reponseJson(enveloppePage(<Object?>[coursJson(slug: 'cours-a')]));
      await depot.catalogue(FiltreCatalogue.aucun);

      adaptateur.couperLeReseau();

      // « On ne filtre jamais cote client une liste paginee » : servir la
      // liste non filtree sous le titre « Debutant » serait plausible et
      // faux — exactement le bogue que personne ne remarque.
      await expectLater(
        depot.catalogue(const FiltreCatalogue(niveau: NiveauCours.debutant)),
        throwsA(isA<NetworkFailure>()),
      );
    });

    test('le filtre repart AVEC le curseur sur la page suivante', () async {
      adaptateur.repondre = (_) => reponseJson(enveloppePage(<Object?>[]));
      await depot.pageSuivante(
        const FiltreCatalogue(niveau: NiveauCours.debutant),
        'curseur-opaque',
      );

      final Map<String, dynamic> parametres =
          adaptateur.recues.last.queryParameters;
      // Sans `level`, le serveur rendrait la page suivante du catalogue NON
      // filtre — sans erreur (arbitrage n° 82).
      expect(parametres['level'], 'beginner');
      expect(parametres['cursor'], 'curseur-opaque');
    });
  });

  group('La fiche et la leçon', () {
    test('un cours telecharge reste lisible entierement hors ligne', () async {
      adaptateur.repondre = (RequestOptions options) {
        if (options.path.endsWith('/lessons/lecon-1')) {
          return reponseJson(
            enveloppe(
              leconJson(contenu: '<p>Le corps de la leçon.</p>'),
              meta: <String, Object?>{'secondes_requises': 45},
            ),
          );
        }
        return reponseJson(
          enveloppe(
            coursJson(lecons: <Map<String, Object?>>[leconJson()]),
            meta: <String, Object?>{'inscription': inscriptionJson()},
          ),
        );
      };

      await depot.fiche('cours-neutre');
      await depot.lecon('cours-neutre', 'lecon-1');

      // Mode avion.
      adaptateur.couperLeReseau();

      final FicheLue fiche = await depot.fiche('cours-neutre');
      expect(fiche.vientDuCache, isTrue);
      expect(fiche.fiche.lecons.single.uuid, 'lecon-1');
      // La lecon telechargee est signalee comme telle : les autres portent
      // « Disponible avec une connexion », jamais un cadenas nu.
      expect(fiche.leconsHorsLigne, contains('lecon-1'));

      final LeconLue lecon = await depot.lecon('cours-neutre', 'lecon-1');
      expect(lecon.vientDuCache, isTrue);
      expect(lecon.lecon.contenuHtml, contains('Le corps de la leçon'));
      // `meta.secondes_requises` survit au cache : sinon le client se
      // retrouverait a coder 45 quelque part.
      expect(lecon.lecon.secondesRequises, 45);
    });

    test('une leçon jamais ouverte n est pas inventee hors ligne', () async {
      adaptateur.couperLeReseau();
      await expectLater(
        depot.lecon('cours-neutre', 'lecon-jamais-vue'),
        throwsA(isA<NetworkFailure>()),
      );
    });
  });

  group('Les ecritures hors ligne', () {
    test(
      'terminer une leçon part en file et NE MARQUE PAS la leçon validee',
      () async {
        adaptateur.couperLeReseau();

        final bool confirmee = await depot.terminerLecon(
          'cours-neutre',
          'lecon-1',
          60,
        );

        // « Ne jamais marquer localement une completion non confirmee : la
        // progression serait fausse au prochain chargement. »
        expect(confirmee, isFalse);

        final List<PendingOp> file = await fileStore.all();
        expect(file.single.kind, PendingOpKind.terminerLecon);
        expect(file.single.payload['secondes'], 60);
        // Rejouable par CONTRAINTE D'UNICITE : pas de cle d'idempotence.
        expect(file.single.idempotencyKey, isNull);
      },
    );

    test('s inscrire part en file et ne fabrique aucune inscription', () async {
      adaptateur.couperLeReseau();

      final CourseEnrollment? inscription = await depot.sInscrire(
        'cours-neutre',
      );

      // `null` et pas une inscription plausible : « le solde affiche ne bouge
      // pas avant la reponse du serveur ».
      expect(inscription, isNull);

      final List<PendingOp> enFile = await fileStore.all();
      expect(enFile.single.kind, PendingOpKind.sInscrireAuCours);
      // Idempotent PAR SERVICE : un rejeu rend l'inscription existante.
      expect(enFile.single.idempotencyKey, isNull);
    });
  });
}
