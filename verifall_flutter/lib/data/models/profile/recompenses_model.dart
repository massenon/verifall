import '../../../domain/entities/gamification_recompenses.dart';
import '../json_reader.dart';

/// La lecture de `GET /me/gamification` et `GET /me/certificates`.
abstract final class RecompensesModel {
  static Recompenses depuisJson(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return Recompenses(
      niveau: _niveau(
        lecteur.objectOrNull('niveau') ?? const <String, Object?>{},
      ),
      serie: _serie(lecteur.objectOrNull('serie') ?? const <String, Object?>{}),
      badges: <BadgeCitoyen>[
        for (final Map<String, Object?> badge in lecteur.objectListOrEmpty(
          'badges',
        ))
          badgeDepuisJson(badge),
      ],
    );
  }

  static NiveauCitoyen _niveau(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return NiveauCitoyen(
      rang: lecteur.intValue('rang'),
      nom: lecteur.string('nom'),
      xp: lecteur.intValue('xp'),
      xpDansLePalier: lecteur.intValue('xp_dans_le_palier'),
      xpRestant: lecteur.intValue('xp_restant'),
      pourcentage: lecteur.intValue('pourcentage'),
    );
  }

  static SerieDeVigilance _serie(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return SerieDeVigilance(
      jours: lecteur.intValue('jours'),
      vivante: lecteur.boolValue('vivante'),
      // `record` est lu s'il arrive, et laisse a `null` sinon (reserve D.3) :
      // « si l'agregat le renvoie, la donnee est réelle et peut s'afficher ;
      // sinon, on ne l'invente pas ».
      record: lecteur.intOrNull('record'),
      joursAvantLaPrime: lecteur.intOrNull('jours_avant_la_prime'),
    );
  }

  static BadgeCitoyen badgeDepuisJson(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return BadgeCitoyen(
      slug: lecteur.string('slug'),
      nom: lecteur.string('nom'),
      // `debloque` est rendu par `whenHas` cote serveur : absent, il vaut faux.
      // Un badge dont on ne sait rien est un badge verrouille — jamais l'inverse.
      debloque: lecteur.boolValue('debloque'),
      description: lecteur.stringOrNull('description'),
      icone: lecteur.stringOrNull('icone'),
      condition: lecteur.stringOrNull('condition'),
      xp: lecteur.intOrNull('xp'),
      unites: lecteur.intOrNull('unites'),
      debloqueLe: lecteur.dateTimeOrNull('debloque_le'),
    );
  }

  static Certificat certificatDepuisJson(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return Certificat(
      code: lecteur.string('code'),
      coursTitre: lecteur.string('cours_titre'),
      titulaire: lecteur.stringOrNull('titulaire'),
      coursSlug: lecteur.stringOrNull('cours_slug'),
      delivreLe: lecteur.dateTimeOrNull('delivre_le'),
      revoque: lecteur.boolValue('revoque'),
      revoqueLe: lecteur.dateTimeOrNull('revoque_le'),
    );
  }

  // --- Cache local ---------------------------------------------------------
  //
  // POURQUOI les recompenses se cachent alors que le solde ne se cache pas :
  // le tableau du contrat (05-HORS-LIGNE 5.5) place « badges » parmi ce qui
  // reste lisible hors ligne, et en exclut explicitement le solde. Un badge
  // obtenu hier reste obtenu ; un solde d'hier est faux des qu'on paie.

  static Map<String, Object?> versJson(Recompenses recompenses) =>
      <String, Object?>{
        'niveau': <String, Object?>{
          'rang': recompenses.niveau.rang,
          'nom': recompenses.niveau.nom,
          'xp': recompenses.niveau.xp,
          'xp_dans_le_palier': recompenses.niveau.xpDansLePalier,
          'xp_restant': recompenses.niveau.xpRestant,
          'pourcentage': recompenses.niveau.pourcentage,
        },
        'serie': <String, Object?>{
          'jours': recompenses.serie.jours,
          'vivante': recompenses.serie.vivante,
          'record': recompenses.serie.record,
          'jours_avant_la_prime': recompenses.serie.joursAvantLaPrime,
        },
        'badges': <Map<String, Object?>>[
          for (final BadgeCitoyen badge in recompenses.badges)
            <String, Object?>{
              'slug': badge.slug,
              'nom': badge.nom,
              'debloque': badge.debloque,
              'description': badge.description,
              'icone': badge.icone,
              'condition': badge.condition,
              'xp': badge.xp,
              'unites': badge.unites,
              'debloque_le': badge.debloqueLe?.toIso8601String(),
            },
        ],
      };

  static Map<String, Object?> certificatVersJson(Certificat certificat) =>
      <String, Object?>{
        'code': certificat.code,
        'cours_titre': certificat.coursTitre,
        'titulaire': certificat.titulaire,
        'cours_slug': certificat.coursSlug,
        'delivre_le': certificat.delivreLe?.toIso8601String(),
        'revoque': certificat.revoque,
        'revoque_le': certificat.revoqueLe?.toIso8601String(),
      };
}
