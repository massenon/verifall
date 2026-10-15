import '../../../domain/entities/lesson_content.dart';
import '../../../domain/entities/lesson_summary.dart';
import '../json_reader.dart';

/// `LeconResource`.
abstract final class LeconJson {
  static LessonSummary resume(Map<String, Object?> json) {
    final JsonReader r = JsonReader(json);
    return LessonSummary(
      uuid: r.string('uuid'),
      position: r.intValue('position'),
      titre: r.string('titre'),
      type: TypeLecon.parse(r.stringOrNull('type')),
      minutes: r.intValue('minutes'),
      videoUrl: r.stringOrNull('video_url'),
      // `whenHas` cote serveur : la cle est ABSENTE hors du contexte ou le
      // serveur s'est prononce. On garde donc `null` — « absent signifie non
      // applicable dans ce contexte », et le client ne suppose pas.
      ouverte: r.hasKey('ouverte') ? r.boolOrNull('ouverte') : null,
      acquise: r.hasKey('acquise') ? r.boolOrNull('acquise') : null,
    );
  }

  /// Le contenu, avec `meta.secondes_requises`.
  ///
  /// POURQUOI `secondes_requises` n'a pas de repli codé en dur : le simulateur
  /// annonce 45 s, « le contrat rend la valeur par le serveur. Ne pas coder
  /// 45 ». Une valeur absente vaut 0, et le bouton de validation devient
  /// immédiatement actif — c'est le serveur qui tranchera par son `422`, pas
  /// une constante que le client aurait inventée.
  static LessonContent contenu(
    Map<String, Object?> json,
    Map<String, Object?> meta,
    String coursSlug,
  ) {
    final JsonReader r = JsonReader(json);
    return LessonContent(
      lecon: resume(json),
      coursSlug: coursSlug,
      // `contenu` absent et `contenu` nul ne disent pas la meme chose : absent
      // = « pas dans ce contexte », nul = « connu, mais vide ». Les deux
      // donnent `null` ici, mais l'ecran affiche le meme message honnete.
      contenuHtml: r.stringOrNull('contenu'),
      secondesRequises: JsonReader(meta).intValue('secondes_requises'),
    );
  }
}
