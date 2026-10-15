/// Lecture **tolérante** d'un objet JSON.
///
/// POURQUOI ces fonctions existent au lieu d'un `as int` direct : le contrat
/// impose au client d'**ignorer les champs qu'il ne connaît pas** (§6.2), et
/// c'est ce qui rend toute évolution de l'API possible. Un client qui lève une
/// exception sur une clé inattendue fige l'API pour toujours.
///
/// Ce que ces fonctions ne font PAS : deviner. Un champ absent reste absent, et
/// [JsonReader.hasKey] permet de distinguer les deux cas que le contrat sépare
/// explicitement (§8) — **absent** signifie « non applicable dans ce contexte »,
/// **présent et nul** signifie « connu, mais vide ».
extension type const JsonReader(Map<String, Object?> raw) {
  bool hasKey(String key) => raw.containsKey(key);

  /// Vrai si la clé est là et vaut `null` : « connu, mais vide ».
  bool isExplicitNull(String key) => raw.containsKey(key) && raw[key] == null;

  String? stringOrNull(String key) {
    final Object? value = raw[key];
    if (value == null) {
      return null;
    }
    return value is String ? value : '$value';
  }

  String string(String key, {String fallback = ''}) =>
      stringOrNull(key) ?? fallback;

  int? intOrNull(String key) {
    final Object? value = raw[key];
    if (value is int) {
      return value;
    }
    // Le contrat garantit des entiers (§8) ; la tolerance ci-dessous couvre un
    // mandataire qui reserialiserait en chaine, pas une supposition sur l'API.
    if (value is num) {
      return value.toInt();
    }
    if (value is String) {
      return int.tryParse(value);
    }
    return null;
  }

  int intValue(String key, {int fallback = 0}) => intOrNull(key) ?? fallback;

  bool? boolOrNull(String key) {
    final Object? value = raw[key];
    return value is bool ? value : null;
  }

  bool boolValue(String key, {bool fallback = false}) =>
      boolOrNull(key) ?? fallback;

  /// Une date ISO 8601 **avec fuseau** (§8). Le serveur émet toujours en UTC ;
  /// l'affichage dans le fuseau du téléphone est du ressort de l'écran.
  DateTime? dateTimeOrNull(String key) {
    final String? value = stringOrNull(key);
    if (value == null || value.isEmpty) {
      return null;
    }
    return DateTime.tryParse(value);
  }

  Map<String, Object?>? objectOrNull(String key) {
    final Object? value = raw[key];
    if (value is Map<String, Object?>) {
      return value;
    }
    if (value is Map) {
      return value.map(
        (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
      );
    }
    return null;
  }

  List<Object?> listOrEmpty(String key) {
    final Object? value = raw[key];
    return value is List ? value : const <Object?>[];
  }

  /// Les objets d'une liste, normalisés. Les éléments qui ne sont pas des objets
  /// sont ignorés plutôt que de faire échouer la lecture complète.
  List<Map<String, Object?>> objectListOrEmpty(String key) {
    return <Map<String, Object?>>[
      for (final Object? item in listOrEmpty(key))
        if (item is Map)
          item.map(
            (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
          ),
    ];
  }
}

/// Normalise n'importe quelle carte issue du décodeur JSON.
Map<String, Object?> asJsonObject(Object? value) {
  if (value is Map<String, Object?>) {
    return value;
  }
  if (value is Map) {
    return value.map(
      (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
    );
  }
  return const <String, Object?>{};
}
