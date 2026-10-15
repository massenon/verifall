import '../../../domain/entities/referral_parrainage.dart';
import '../json_reader.dart';

/// La lecture de `GET /me/referrals` et `POST /me/referrals/invite`.
abstract final class ParrainageModel {
  static Parrainage depuisJson(
    Map<String, Object?> json, {
    required DateTime luLe,
  }) {
    final JsonReader lecteur = JsonReader(json);
    return Parrainage(
      code: lecteur.string('code'),
      lien: lecteur.string('lien'),
      indicateurs: _indicateurs(
        lecteur.objectOrNull('indicateurs') ?? const <String, Object?>{},
      ),
      filleuls: <Filleul>[
        for (final Map<String, Object?> filleul in lecteur.objectListOrEmpty(
          'filleuls',
        ))
          _filleul(filleul),
      ],
      luLe: luLe,
    );
  }

  static IndicateursParrainage _indicateurs(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return IndicateursParrainage(
      // `visites` = les OUVERTURES du lien. C'est le seul compteur honnete du
      // parrainage, et le libelle de l'ecran le dit (arbitrage n. 46).
      visites: lecteur.intValue('visites'),
      inscrits: lecteur.intValue('inscrits'),
      actifs: lecteur.intValue('actifs'),
      // `points` vient de l'API, jamais d'une multiplication locale du nombre
      // de filleuls actifs : une multiplication afficherait un gain qui n'a pas
      // ete verse.
      points: lecteur.intValue('points'),
    );
  }

  static Filleul _filleul(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return Filleul(
      uuid: lecteur.string('uuid'),
      statut: StatutFilleul.parse(lecteur.stringOrNull('statut')),
      statutLibelle: lecteur.string('statut_libelle'),
      // Le PRENOM seul. L'adresse e-mail d'un filleul n'est jamais exposee, et
      // le nom de famille n'est pas servi non plus.
      prenom: lecteur.stringOrNull('prenom'),
      inscritLe: lecteur.dateTimeOrNull('inscrit_le'),
      recompenseLe: lecteur.dateTimeOrNull('recompense_le'),
    );
  }

  static Invitation invitationDepuisJson(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return Invitation(
      code: lecteur.string('code'),
      lien: lecteur.string('lien'),
      message: lecteur.stringOrNull('message'),
    );
  }

  /// La forme écrite en cache.
  ///
  /// POURQUOI le parrainage se cache : le code et le QR « doivent marcher hors
  /// ligne, en présentiel » — montrer un code à quelqu'un dans une cour d'école
  /// ne doit pas dépendre du réseau. C'est la seule raison d'être de ce cache.
  static Map<String, Object?> versJson(Parrainage parrainage) =>
      <String, Object?>{
        'code': parrainage.code,
        'lien': parrainage.lien,
        'indicateurs': <String, Object?>{
          'visites': parrainage.indicateurs.visites,
          'inscrits': parrainage.indicateurs.inscrits,
          'actifs': parrainage.indicateurs.actifs,
          'points': parrainage.indicateurs.points,
        },
        'filleuls': <Map<String, Object?>>[
          for (final Filleul filleul in parrainage.filleuls)
            <String, Object?>{
              'uuid': filleul.uuid,
              'statut': filleul.statut.wireValue,
              'statut_libelle': filleul.statutLibelle,
              'prenom': filleul.prenom,
              'inscrit_le': filleul.inscritLe?.toIso8601String(),
              'recompense_le': filleul.recompenseLe?.toIso8601String(),
            },
        ],
      };
}
