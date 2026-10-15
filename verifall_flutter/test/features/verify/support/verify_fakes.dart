import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/core/network/token_store.dart';

import '../../../support/fakes.dart';

/// Un adaptateur scripté, comme celui du socle.
///
/// POURQUOI on le réécrit ici plutôt que de l'importer : il vit dans un fichier
/// de test du socle, et un test n'exporte rien. Le dupliquer est le prix d'une
/// frontière propre — c'est douze lignes, pas un composant.
class ScriptedAdapter implements HttpClientAdapter {
  ScriptedAdapter(this.respond);

  final ResponseBody Function(RequestOptions options) respond;
  final List<RequestOptions> recues = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    recues.add(options);
    return respond(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody jsonReponse(
  Object body, {
  int status = 200,
  Map<String, List<String>> headers = const <String, List<String>>{},
}) {
  return ResponseBody.fromString(
    jsonEncode(body),
    status,
    headers: <String, List<String>>{
      Headers.contentTypeHeader: <String>[Headers.jsonContentType],
      ...headers,
    },
  );
}

/// Un client d'API branché sur un adaptateur scripté.
ApiClient clientDeTest(ScriptedAdapter adaptateur) => ApiClient.create(
  baseUrl: 'https://exemple.invalid',
  tokenStore: InMemoryTokenStore(token: 'jeton-de-test'),
  logger: AppLogger(config: testConfig(), sink: RecordingLogSink()),
  clientVersion: '1.0.0',
  onUnauthenticated: () async {},
  onClientObsolete: () {},
  onAccountInactive: () {},
  onAccountBeingDeleted: () {},
  adapter: adaptateur,
);

/// Une vérification servie par l'API, **telle que `VerificationResource` la
/// sérialise**.
///
/// POURQUOI la fabrique est explicite et paramétrée : « jamais les données du
/// simulateur en dur dans un test » (§6.8). Ici, chaque clé vient de la
/// ressource réelle, et le test dit lui-même de quel cas il parle.
Map<String, Object?> verificationJson({
  String uuid = 'uuid-1',
  String statut = 'completed',
  String statutLibelle = 'Terminée',
  bool terminee = true,
  int avancement = 100,
  int? etape = 6,
  String? etapeLibelle = 'Verdict',
  bool estUneReprise = false,
  int? coutUnites = 2,
  Map<String, Object?>? rapport,
}) => <String, Object?>{
  'uuid': uuid,
  'reference': 'V-000123',
  'type': 'text',
  'type_libelle': 'Texte',
  'statut': statut,
  'statut_libelle': statutLibelle,
  'terminee': terminee,
  'affirmation': 'Une affirmation soumise a vérification.',
  'apercu': 'Une affirmation soumise a vérification.',
  'contexte': null,
  'cout_unites': coutUnites,
  'avancement': avancement,
  'etape': etape,
  'etape_libelle': etapeLibelle,
  'est_une_reprise': estUneReprise,
  'soumise_le': '2026-08-25T10:12:00+00:00',
  // La cle est TOUJOURS presente, et vaut `null` tant qu'aucun verdict n'a ete
  // rendu. Jamais `{}`.
  'rapport': rapport,
};

/// Un rapport servi, **tel que `RapportResource` le sérialise**.
Map<String, Object?> rapportJson({
  String verdict = 'false',
  String verdictLibelle = 'Faux',
  int confiance = 92,
  List<Map<String, Object?>> preuves = const <Map<String, Object?>>[],
}) => <String, Object?>{
  'uuid': 'rapport-1',
  'verdict': verdict,
  'verdict_libelle': verdictLibelle,
  'confiance': confiance,
  'synthese': 'Les sources consultees contredisent cette information.',
  'reserve': null,
  'scores': <String, Object?>{'false': 92.0, 'true': 8.0},
  'deroule': <Map<String, Object?>>[
    <String, Object?>{
      'etape': 'Reception',
      'libelle': 'Reception du contenu',
      'at': '2026-08-25T10:12:00+00:00',
    },
  ],
  'indice_haine': 12,
  'indice_haine_motifs': <String>[],
  'indice_haine_note': null,
  'preuves': preuves,
  'publie_le': '2026-08-25T10:15:00+00:00',
};

/// Une preuve servie, **telle que `PreuveResource` la sérialise** (Phase 11).
Map<String, Object?> preuveJson({
  String nom = 'Agence Fact-Check',
  String? url = 'https://exemple.org/article',
  String? domaine = 'exemple.org',
  String? editeur,
  String position = 'refutes',
  String positionLibelle = 'Contredit',
  int fiabilite = 88,
  bool recuperee = true,
  String? publieeLe = '2026-08-24T09:00:00+00:00',
}) => <String, Object?>{
  'nom': nom,
  'url': url,
  'domaine': domaine,
  'editeur': editeur,
  'position': position,
  'position_libelle': positionLibelle,
  'fiabilite': fiabilite,
  'recuperee': recuperee,
  'publiee_le': publieeLe,
};
