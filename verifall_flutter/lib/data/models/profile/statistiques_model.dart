import '../../../domain/entities/stats_statistiques.dart';
import '../json_reader.dart';

/// La lecture de `GET /me/stats`.
abstract final class StatistiquesModel {
  static Statistiques depuisJson(
    Map<String, Object?> json, {
    required DateTime calculeLe,
  }) {
    final JsonReader lecteur = JsonReader(json);
    return Statistiques(
      periodeJours: lecteur.intValue('periode_jours', fallback: 30),
      verifications: lecteur.intValue('verifications'),
      intoxDetectees: lecteur.intValue('intox_detectees'),
      leconsTerminees: lecteur.intValue('lecons_terminees'),
      leconsCetteSemaine: lecteur.intOrNull('lecons_cette_semaine'),
      evolutionMensuelle: lecteur.intOrNull('evolution_mensuelle'),
      verdicts: _compteurs(lecteur.objectOrNull('verdicts')),
      types: _compteurs(lecteur.objectOrNull('types')),
      activite: _activite(lecteur.objectOrNull('activite')),
      semaine: <JourDeSemaine>[
        for (final Map<String, Object?> jour in lecteur.objectListOrEmpty(
          'semaine',
        ))
          _jour(jour),
      ],
      calculeLe: calculeLe,
    );
  }

  /// Une carte « clé → entier », en préservant **l'ordre du serveur**.
  ///
  /// POURQUOI l'ordre compte : « tous les types sont représentés, même à zéro :
  /// un histogramme dont les colonnes changent de place d'une visite à l'autre
  /// ne se lit pas ». Le serveur les rend dans l'ordre de l'énumération ; un tri
  /// côté client, par valeur décroissante par exemple, ferait sauter les
  /// colonnes d'une visite à l'autre.
  static Map<String, int> _compteurs(Map<String, Object?>? json) {
    if (json == null) {
      return const <String, int>{};
    }
    final Map<String, int> sortie = <String, int>{};
    json.forEach((String cle, Object? valeur) {
      final int? nombre = JsonReader(<String, Object?>{
        'v': valeur,
      }).intOrNull('v');
      if (nombre != null) {
        sortie[cle] = nombre;
      }
    });
    return sortie;
  }

  static SerieActivite _activite(Map<String, Object?>? json) {
    if (json == null) {
      return SerieActivite.vide;
    }
    final JsonReader lecteur = JsonReader(json);
    return SerieActivite(
      jours: <String>[
        for (final Object? jour in lecteur.listOrEmpty('jours')) '$jour',
      ],
      // Les jours SANS activite restent dans la serie, a zero. Le client ne
      // filtre rien : « un graphique qui saute les jours vides ment sur la
      // regularite, qui est justement ce qu'il montre ».
      valeurs: <int>[
        for (final Object? valeur in lecteur.listOrEmpty('valeurs'))
          JsonReader(<String, Object?>{'v': valeur}).intValue('v'),
      ],
    );
  }

  static JourDeSemaine _jour(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return JourDeSemaine(
      lettre: lecteur.string('lettre'),
      actif: lecteur.boolValue('actif'),
      aujourdhui: lecteur.boolValue('aujourdhui'),
      futur: lecteur.boolValue('futur'),
    );
  }

  static Map<String, Object?> versJson(Statistiques stats) => <String, Object?>{
    'periode_jours': stats.periodeJours,
    'verifications': stats.verifications,
    'intox_detectees': stats.intoxDetectees,
    'lecons_terminees': stats.leconsTerminees,
    'lecons_cette_semaine': stats.leconsCetteSemaine,
    'evolution_mensuelle': stats.evolutionMensuelle,
    'verdicts': stats.verdicts,
    'types': stats.types,
    'activite': <String, Object?>{
      'jours': stats.activite.jours,
      'valeurs': stats.activite.valeurs,
    },
    'semaine': <Map<String, Object?>>[
      for (final JourDeSemaine jour in stats.semaine)
        <String, Object?>{
          'lettre': jour.lettre,
          'actif': jour.actif,
          'aujourdhui': jour.aujourdhui,
          'futur': jour.futur,
        },
    ],
  };
}
