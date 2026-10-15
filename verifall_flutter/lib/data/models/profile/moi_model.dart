import '../../../domain/entities/profile_moi.dart';
import '../json_reader.dart';

/// La lecture de `MoiResource`.
///
/// # Deux règles du socle sont appliquées ici, et pas ailleurs
///
/// - **Les champs inconnus sont ignorés** (§6.2 du contrat). C'est ce qui rend
///   toute évolution de l'API possible ; un client qui lèverait une exception
///   sur une clé inattendue figerait l'API pour toujours.
/// - **`presentation/` ne connaît que `domain/`.** Ce fichier est la frontière :
///   il absorbe les ajouts de champs sans les propager jusqu'aux widgets.
abstract final class MoiModel {
  /// Depuis le corps de `GET /me` ou `PATCH /me`.
  ///
  /// [avecSolde] est **faux à la relecture depuis le cache** : le solde n'est
  /// jamais servi depuis la base locale (contrat §7.1).
  static Moi depuisJson(Map<String, Object?> json, {bool avecSolde = true}) {
    final JsonReader lecteur = JsonReader(json);
    return Moi(
      uuid: lecteur.string('uuid'),
      prenom: lecteur.string('prenom'),
      nom: lecteur.string('nom'),
      nomAffichage: lecteur.string('nom_affichage'),
      initiales: lecteur.string('initiales'),
      email: lecteur.string('email'),
      telephone: lecteur.stringOrNull('telephone'),
      profession: lecteur.stringOrNull('profession'),
      ville: lecteur.stringOrNull('ville'),
      bio: lecteur.stringOrNull('bio'),
      unites: avecSolde ? lecteur.intOrNull('unites') : null,
      xp: lecteur.intOrNull('xp'),
      serieJours: lecteur.intOrNull('serie_jours'),
      inscritLe: lecteur.dateTimeOrNull('inscrit_le'),
      suppressionDemandeeLe: lecteur.dateTimeOrNull('suppression_demandee_le'),
    );
  }

  /// La forme écrite en cache.
  ///
  /// POURQUOI `unites` n'y figure **jamais** : c'est la garantie mécanique que
  /// la règle « le solde ne vient jamais du cache » ne peut pas être contournée
  /// par distraction. Elle ne dépend pas de la vigilance du lecteur, mais du
  /// fait que la valeur n'a pas été écrite.
  static Map<String, Object?> versJson(Moi moi) => <String, Object?>{
    'uuid': moi.uuid,
    'prenom': moi.prenom,
    'nom': moi.nom,
    'nom_affichage': moi.nomAffichage,
    'initiales': moi.initiales,
    'email': moi.email,
    'telephone': moi.telephone,
    'profession': moi.profession,
    'ville': moi.ville,
    'bio': moi.bio,
    'xp': moi.xp,
    'serie_jours': moi.serieJours,
    'inscrit_le': moi.inscritLe?.toIso8601String(),
    'suppression_demandee_le': moi.suppressionDemandeeLe?.toIso8601String(),
  };
}
