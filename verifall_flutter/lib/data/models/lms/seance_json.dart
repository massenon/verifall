import '../../../domain/entities/live_class_session.dart';
import '../json_reader.dart';
import 'cours_json.dart';

/// `SeanceResource`.
abstract final class SeanceJson {
  static LiveClassSession lire(Map<String, Object?> json) {
    final JsonReader r = JsonReader(json);
    return LiveClassSession(
      uuid: r.string('uuid'),
      titre: r.string('titre'),
      description: r.string('description'),
      etat: EtatSeance.parse(r.stringOrNull('etat')),
      minutes: r.intValue('minutes'),
      debuteLe: r.dateTimeOrNull('debute_le'),
      places: r.intOrNull('places'),
      animateur: CoursJson.intervenant(r.objectOrNull('animateur')),
      coursSlug: r.stringOrNull('cours_slug'),
      // `null` tant que la seance n'est pas joignable : le bouton
      // « Rejoindre » reste inactif, et c'est une regle de produit.
      lienVisio: r.stringOrNull('lien_visio'),
      rappelDemande: r.hasKey('rappel_demande')
          ? r.boolOrNull('rappel_demande')
          : null,
    );
  }
}
