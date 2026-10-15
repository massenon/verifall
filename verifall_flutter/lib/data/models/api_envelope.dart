import 'package:flutter/foundation.dart';

import 'api_error_code.dart';
import 'json_reader.dart';

/// L'erreur de l'API, telle que le contrat la fixe (§2.1).
@immutable
class ApiErrorBody {
  const ApiErrorBody({
    required this.code,
    required this.message,
    this.details = const <String, List<String>>{},
    this.rawCode = '',
  });

  factory ApiErrorBody.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    final Map<String, Object?>? details = reader.objectOrNull('details');
    return ApiErrorBody(
      code: ApiErrorCode.parse(reader.stringOrNull('code')),
      message: reader.string('message'),
      rawCode: reader.string('code'),
      details: details == null
          ? const <String, List<String>>{}
          : <String, List<String>>{
              for (final MapEntry<String, Object?> entry in details.entries)
                entry.key: <String>[
                  if (entry.value is List)
                    for (final Object? m in entry.value! as List) '$m'
                  else if (entry.value != null)
                    '${entry.value}',
                ],
            },
    );
  }

  /// La valeur de la liste fermée. C'est **là-dessus que le client branche**.
  final ApiErrorCode code;

  /// La valeur brute, gardée même quand [code] vaut `inconnu` : elle part au
  /// journal, où elle vaut son poids en diagnostic.
  final String rawCode;

  /// Déjà rédigé en français, **affichable tel quel** (§2.1).
  ///
  /// POURQUOI le client ne le reformule pas : il n'a pas le contexte serveur. Le
  /// message d'un `429` sur la connexion contient déjà le délai ; celui d'un
  /// `422 action_impossible` sur une leçon dit combien de secondes manquent.
  final String message;

  /// Présent **uniquement** sur `validation` : clé = champ envoyé, valeur =
  /// liste de messages. C'est ce qui alimente l'erreur sous un champ de
  /// formulaire.
  final Map<String, List<String>> details;

  @override
  String toString() => 'ApiErrorBody($rawCode)';
}

/// La pagination par curseur (§3.2).
///
/// POURQUOI il n'y a ni `page` ni total : sur un fil qui bouge, le décalage ment
/// — trois sujets publiés entre deux pages font revoir trois éléments ou en
/// sauter trois. Le curseur désigne une position dans l'ordre, pas un rang.
@immutable
class PageMeta {
  const PageMeta({
    required this.perPage,
    required this.hasMore,
    this.nextCursor,
    this.prevCursor,
  });

  factory PageMeta.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return PageMeta(
      perPage: reader.intValue('per_page', fallback: defaultLimit),
      hasMore: reader.boolValue('has_more'),
      nextCursor: reader.stringOrNull('next_cursor'),
      prevCursor: reader.stringOrNull('prev_cursor'),
    );
  }

  /// Le défaut du contrat.
  static const int defaultLimit = 20;

  /// Le plafond du contrat. Au-delà, le serveur ramène à 50 **sans erreur** ;
  /// le client borne quand même, pour ne pas demander ce qu'il n'obtiendra pas.
  static const int maxLimit = 50;

  final int perPage;
  final bool hasMore;

  /// **Opaque.** On le renvoie tel quel dans `?cursor=`, on ne le lit jamais, on
  /// ne le fabrique jamais : le jour où l'ordre de tri change, un client qui en
  /// dépendait casse en silence (§3.2).
  final String? nextCursor;
  final String? prevCursor;

  /// Fin de liste : `next_cursor: null` **et** `has_more: false`.
  bool get isLastPage => !hasMore || nextCursor == null;
}

/// Une page de liste : les éléments, plus leur curseur.
@immutable
class CursorPage<T> {
  const CursorPage({required this.items, required this.page});

  final List<T> items;
  final PageMeta page;

  /// Le décompte **chargé**, jamais un total : l'API n'en donne pas.
  /// L'écran 12 affiche « `<n>` résultat(s) » et le libellé doit supporter cette
  /// honnêteté plutôt que promettre ce que l'API ne donne pas (04-RESEAU §4.4).
  int get loadedCount => items.length;
}

/// L'enveloppe de réponse, et **la seule règle de lecture** (§2.1) :
///
/// > Si la réponse contient `error`, c'est un échec. Sinon `data` fait foi.
///
/// `data` et `error` sont mutuellement exclusifs ; `meta` peut accompagner les
/// deux.
///
/// POURQUOI `data == null` n'est pas une erreur : c'est « c'est fait », ou un
/// état vide légitime — `GET /me/courses/resume` répond exactement cela quand il
/// n'y a rien à reprendre. Un client qui traiterait `null` comme un échec
/// afficherait une erreur sur un compte neuf.
@immutable
class ApiEnvelope {
  const ApiEnvelope({
    required this.hasError,
    required this.hasDataKey,
    this.data,
    this.error,
    this.meta = const <String, Object?>{},
  });

  factory ApiEnvelope.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    final Map<String, Object?>? error = reader.objectOrNull('error');
    return ApiEnvelope(
      hasError: error != null,
      hasDataKey: reader.hasKey('data'),
      data: json['data'],
      error: error == null ? null : ApiErrorBody.fromJson(error),
      meta: reader.objectOrNull('meta') ?? const <String, Object?>{},
    );
  }

  final bool hasError;

  /// La clé `data` était-elle présente ? Distinguer « absente » de « présente et
  /// nulle » est exigé par le contrat (§8) et ce booléen est le seul endroit où
  /// l'information survit.
  final bool hasDataKey;

  final Object? data;
  final ApiErrorBody? error;
  final Map<String, Object?> meta;

  /// `data` sous forme d'objet, ou une carte vide.
  Map<String, Object?> get dataObject => asJsonObject(data);

  /// `data` sous forme de liste d'objets.
  List<Map<String, Object?>> get dataList {
    final Object? value = data;
    if (value is! List) {
      return const <Map<String, Object?>>[];
    }
    return <Map<String, Object?>>[
      for (final Object? item in value)
        if (item is Map)
          item.map(
            (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
          ),
    ];
  }

  /// La pagination, quand la réponse en porte une.
  PageMeta? get page {
    final Object? raw = meta['page'];
    if (raw is! Map) {
      return null;
    }
    return PageMeta.fromJson(asJsonObject(raw));
  }

  /// Le délai à respecter sur `429`, en secondes. Il double l'en-tête
  /// `Retry-After` « pour les clients qui lisent le corps plus facilement que
  /// les en-têtes » (§5.3).
  int? get retryAfterSeconds => JsonReader(meta).intOrNull('retry_after');

  /// Le solde recalculé, quand l'appel en renvoie un (`POST /verifications`).
  ///
  /// POURQUOI il vit dans `meta` et pas dans un calcul local : « le client ne
  /// débite ni ne crédite jamais d'unité » (05-HORS-LIGNE §5.9).
  int? get solde => JsonReader(meta).intOrNull('solde');

  /// Construit une page typée à partir d'une réponse de liste.
  CursorPage<T> toPage<T>(T Function(Map<String, Object?> json) fromJson) {
    return CursorPage<T>(
      items: <T>[
        for (final Map<String, Object?> item in dataList) fromJson(item),
      ],
      page:
          page ??
          const PageMeta(perPage: PageMeta.defaultLimit, hasMore: false),
    );
  }
}
