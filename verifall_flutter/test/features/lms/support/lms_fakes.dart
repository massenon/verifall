import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/core/network/token_store.dart';
import 'package:verifall_app/data/datasources/local/lms/lms_local_data_source.dart';

import '../../../support/fakes.dart';

/// Les doublures du parcours M3.
///
/// POURQUOI elles vivent dans `test/` et pas dans `lib/` : « une doublure livrée
/// dans le paquet finit par être utilisée pour de vrai par mégarde » (socle,
/// `test/support/fakes.dart`).
///
/// POURQUOI aucune donnée du simulateur n'apparaît ici : « jamais les données du
/// simulateur en dur dans un test » (06-TESTS §6.8). Les fabriques produisent un
/// cours **neutre**, et chaque test dit lui-même ce qu'il attend.

/// Un adaptateur Dio scripté : il répond ce qu'on lui dit, et garde ce qu'on lui
/// a demandé.
///
/// POURQUOI un adaptateur plutôt qu'un faux dépôt : on veut vérifier le
/// **comportement réel** de la couche réseau — la traduction des échecs, les
/// en-têtes envoyés, `Idempotency-Key` — et pas une simulation de ce
/// comportement.
class AdaptateurScripte implements HttpClientAdapter {
  AdaptateurScripte(this.repondre);

  /// Rend une réponse, ou lève pour simuler une coupure de réseau.
  ResponseBody Function(RequestOptions options) repondre;

  final List<RequestOptions> recues = <RequestOptions>[];

  /// Coupe le réseau : toute requête lève une erreur de connexion, que le socle
  /// traduit en `NetworkFailure`.
  void couperLeReseau() {
    repondre = (RequestOptions options) => throw DioException(
      requestOptions: options,
      type: DioExceptionType.connectionError,
    );
  }

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    recues.add(options);
    return repondre(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody reponseJson(
  Object corps, {
  int statut = 200,
  Map<String, List<String>> entetes = const <String, List<String>>{},
}) {
  return ResponseBody.fromString(
    jsonEncode(corps),
    statut,
    headers: <String, List<String>>{
      Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      ...entetes,
    },
  );
}

/// Un journal de test : il enregistre au lieu d'écrire, et il ne porte ni
/// corps, ni en-tête, ni chemin brut — comme celui du socle.
AppLogger loggerDeTest() =>
    AppLogger(config: testConfig(), sink: RecordingLogSink());

/// Un client d'API branché sur un adaptateur scripté.
ApiClient clientDeTest(AdaptateurScripte adaptateur) {
  return ApiClient.create(
    baseUrl: 'https://exemple.invalid',
    tokenStore: InMemoryTokenStore(),
    logger: loggerDeTest(),
    clientVersion: '1.0.0',
    onUnauthenticated: () async {},
    onClientObsolete: () {},
    onAccountInactive: () {},
    onAccountBeingDeleted: () {},
    adapter: adaptateur,
  );
}

/// Le cache de l'Académie, **en mémoire**.
///
/// POURQUOI il existe alors que Drift a un mode « en mémoire » : `flutter test`
/// tourne sur la machine hôte, où la bibliothèque native SQLite n'est pas
/// forcément installée — et le test qui coupe le réseau est le test central de
/// ce parcours. Il ne peut pas dépendre de cela.
class CacheEnMemoire implements LmsCache {
  final Map<String, EntreeDeCache> catalogues = <String, EntreeDeCache>{};
  final Map<String, EntreeDeCache> cours = <String, EntreeDeCache>{};
  final Map<String, DateTime> telechargements = <String, DateTime>{};
  final Map<String, EntreeDeCache> lecons = <String, EntreeDeCache>{};
  final Map<String, String> coursDeLaLecon = <String, String>{};
  final Map<String, int> secondes = <String, int>{};
  final Map<String, EntreeDeCache> certificats = <String, EntreeDeCache>{};
  EntreeDeCache? seancesEnCache;

  @override
  Future<EntreeDeCache?> lireCatalogue(String cle) async => catalogues[cle];

  @override
  Future<void> ecrireCatalogue(
    String cle,
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  ) async {
    catalogues[cle] = EntreeDeCache(data: data, meta: meta, lueLe: lueLe);
  }

  @override
  Future<EntreeDeCache?> lireCours(String slug) async => cours[slug];

  @override
  Future<DateTime?> dateDeTelechargement(String slug) async =>
      telechargements[slug];

  @override
  Future<void> ecrireCours(
    String slug,
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  ) async {
    cours[slug] = EntreeDeCache(data: data, meta: meta, lueLe: lueLe);
  }

  @override
  Future<void> marquerTelecharge(String slug, DateTime quand) async {
    telechargements[slug] = quand;
  }

  @override
  Future<void> supprimerTelechargement(String slug) async {
    telechargements.remove(slug);
    cours.remove(slug);
    coursDeLaLecon.removeWhere((String uuid, String s) {
      if (s == slug) {
        lecons.remove(uuid);
        secondes.remove(uuid);
        return true;
      }
      return false;
    });
  }

  @override
  Future<EntreeDeCache?> lireLecon(String uuid) async => lecons[uuid];

  @override
  Future<Set<String>> leconsEnCache(String coursSlug) async => coursDeLaLecon
      .entries
      .where((MapEntry<String, String> e) => e.value == coursSlug)
      .map((MapEntry<String, String> e) => e.key)
      .toSet();

  @override
  Future<int> ecrireLecon({
    required String uuid,
    required String coursSlug,
    required Object? data,
    required Map<String, Object?> meta,
    required int secondesRequises,
    required DateTime lueLe,
  }) async {
    final String charge = jsonEncode(<String, Object?>{
      'data': data,
      'meta': meta,
    });
    lecons[uuid] = EntreeDeCache(
      data: data,
      meta: <String, Object?>{...meta, 'secondes_requises': secondesRequises},
      lueLe: lueLe,
      octets: utf8.encode(charge).length,
    );
    coursDeLaLecon[uuid] = coursSlug;
    return utf8.encode(charge).length;
  }

  @override
  Future<int> octetsDuCours(String slug) async {
    int total = cours[slug]?.octets ?? 0;
    for (final MapEntry<String, String> e in coursDeLaLecon.entries) {
      if (e.value == slug) {
        total += lecons[e.key]?.octets ?? 0;
      }
    }
    return total;
  }

  @override
  Future<int> lireSecondesActives(String leconUuid) async =>
      secondes[leconUuid] ?? 0;

  @override
  Future<void> ecrireSecondesActives(String leconUuid, int valeur) async {
    secondes[leconUuid] = valeur;
  }

  @override
  Future<EntreeDeCache?> lireCertificat(String code) async => certificats[code];

  @override
  Future<void> ecrireCertificat(
    String code,
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  ) async {
    certificats[code] = EntreeDeCache(data: data, meta: meta, lueLe: lueLe);
  }

  @override
  Future<EntreeDeCache?> lireSeances() async => seancesEnCache;

  @override
  Future<void> ecrireSeances(
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  ) async {
    seancesEnCache = EntreeDeCache(data: data, meta: meta, lueLe: lueLe);
  }
}

// ---------------------------------------------------------------------------
// Fabriques : un cours neutre, une leçon neutre, un quiz neutre.
//
// « Une fabrique produit une vérification neutre, un cours neutre, une offre
// neutre — et le test dit lui-même ce qu'il attend » (06-TESTS §6.8).
// ---------------------------------------------------------------------------

Map<String, Object?> coursJson({
  String slug = 'cours-neutre',
  String niveau = 'beginner',
  String niveauLibelle = 'Debutant',
  int coutUnites = 0,
  bool estOffert = false,
  int nombreDEtapes = 2,
  List<Map<String, Object?>>? lecons,
  List<Map<String, Object?>>? quiz,
}) {
  return <String, Object?>{
    'slug': slug,
    'titre': 'Titre neutre',
    'description': 'Description neutre',
    'categorie': 'Catégorie neutre',
    'niveau': niveau,
    'niveau_libelle': niveauLibelle,
    'duree': '1 h',
    'couverture_url': null,
    'cout_unites': coutUnites,
    'est_offert': estOffert,
    'objectifs': <String>['Objectif neutre'],
    'delivre_un_certificat': true,
    'nombre_d_etapes': nombreDEtapes,
    'lecons': ?lecons,
    'quiz': ?quiz,
  };
}

Map<String, Object?> leconJson({
  String uuid = 'lecon-1',
  int position = 1,
  bool? ouverte = true,
  bool? acquise = false,
  String? contenu,
  bool avecEtat = true,
}) {
  return <String, Object?>{
    'uuid': uuid,
    'position': position,
    'titre': 'Leçon neutre $position',
    'type': 'text',
    'minutes': 5,
    'video_url': null,
    'contenu': ?contenu,
    // `whenHas` cote serveur : la cle est ABSENTE hors contexte. Le test doit
    // pouvoir produire les deux formes.
    if (avecEtat) 'ouverte': ouverte,
    if (avecEtat) 'acquise': acquise,
  };
}

Map<String, Object?> quizJson({
  String uuid = 'quiz-1',
  int minutes = 10,
  int scoreRequis = 70,
  int xp = 30,
  List<Map<String, Object?>>? questions,
}) {
  return <String, Object?>{
    'uuid': uuid,
    'titre': 'Quiz neutre',
    'minutes': minutes,
    'score_requis': scoreRequis,
    'xp': xp,
    'questions': ?questions,
  };
}

Map<String, Object?> questionJson({
  String uuid = 'question-1',
  int position = 1,
  List<int> idsDeReponse = const <int>[11, 12],
}) {
  return <String, Object?>{
    'id': uuid,
    'position': position,
    'intitule': 'Intitule neutre',
    'reponses': <Map<String, Object?>>[
      for (int i = 0; i < idsDeReponse.length; i++)
        <String, Object?>{
          'id': idsDeReponse[i],
          'position': i + 1,
          'libelle': 'Réponse ${i + 1}',
        },
    ],
  };
}

Map<String, Object?> inscriptionJson({
  String slug = 'cours-neutre',
  String statut = 'in_progress',
  int avancement = 40,
}) {
  return <String, Object?>{
    'uuid': 'inscription-1',
    'cours_slug': slug,
    'cours_titre': 'Titre neutre',
    'statut': statut,
    'statut_libelle': 'En cours',
    'avancement': avancement,
    'unites_payees': 0,
    'commencee_le': '2026-01-01T08:00:00+00:00',
    'derniere_activite_le': '2026-01-02T08:00:00+00:00',
    'achevee_le': null,
  };
}

Map<String, Object?> certificatJson({
  String code = 'VF7KMPQR',
  String slug = 'cours-neutre',
}) {
  return <String, Object?>{
    'code': code,
    'titulaire': 'Titulaire Neutre',
    'cours_titre': 'Titre au moment de l emission',
    'cours_slug': slug,
    'delivre_le': '2026-01-03T08:00:00+00:00',
    'revoque': false,
    'revoque_le': null,
  };
}

Map<String, Object?> seanceJson({
  String uuid = 'seance-1',
  String etat = 'upcoming',
  String? lienVisio,
  bool? rappelDemande = false,
}) {
  return <String, Object?>{
    'uuid': uuid,
    'titre': 'Seance neutre',
    'description': 'Description neutre',
    'etat': etat,
    'debute_le': '2026-02-01T14:00:00+00:00',
    'minutes': 60,
    'places': 40,
    'cours_slug': null,
    'lien_visio': lienVisio,
    'rappel_demande': ?rappelDemande,
  };
}

/// L'enveloppe du contrat : `data` seul, ou `data` + `meta`.
Map<String, Object?> enveloppe(Object? data, {Map<String, Object?>? meta}) =>
    <String, Object?>{'data': data, 'meta': ?meta};

/// Une page par curseur.
Map<String, Object?> enveloppePage(
  List<Object?> data, {
  String? curseurSuivant,
  bool aPlus = false,
}) => <String, Object?>{
  'data': data,
  'meta': <String, Object?>{
    'page': <String, Object?>{
      'per_page': 20,
      'next_cursor': curseurSuivant,
      'prev_cursor': null,
      'has_more': aPlus,
    },
  },
};
