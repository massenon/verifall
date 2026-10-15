import '../../../domain/entities/opportunity_entity.dart';
import '../json_reader.dart';

/// La lecture d'une opportunité du Hub.
///
/// # Les trois champs qui décident de l'écran, et qui viennent tous du serveur
///
/// | Champ | Rôle |
/// |---|---|
/// | `verifiee` | pilote « EMPLOYEUR VÉRIFIÉ ». « Ça ne se déduit d'aucun autre champ » |
/// | `expiree` | pilote « CLÔTURÉE » et le retrait du bouton de candidature |
/// | `jours_restants` | le compte à rebours, **jamais calculé localement** |
///
/// Le simulateur calcule les trois à partir d'un nombre de jours, parce qu'il
/// tourne sans serveur. Les transposer serait la faute nommée en réserve E de la
/// section 9 : « une horloge de téléphone mal réglée ferait disparaître une
/// offre encore ouverte, ou pire, présenterait comme candidatable une offre
/// fermée ».
abstract final class OpportuniteModel {
  /// Les noms lus pour les champs que le contrat ne nomme pas (réserve F.1).
  static const String champChapo = 'chapo';
  static const String champCorps = 'corps';
  static const String champOrganisation = 'organisation';
  static const String champLieu = 'lieu';
  static const String champDateLimite = 'date_limite';
  static const String champLienCandidature = 'lien_candidature';

  static Opportunite fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return Opportunite(
      slug: reader.string('slug'),
      titre: reader.string('titre'),
      // `expiree` et `verifiee` sont des faits serveur. En leur absence on
      // choisit le defaut le moins dangereux : ni « clôturee » (ce qui
      // retirerait a tort le bouton), ni « verifiee » (ce qui promettrait un
      // controle humain qui n'a peut-etre pas eu lieu).
      expiree: reader.boolValue('expiree'),
      verifiee: reader.boolValue('verifiee'),
      chapo: reader.stringOrNull(champChapo),
      corps: reader.stringOrNull(champCorps),
      organisation: reader.stringOrNull(champOrganisation),
      lieu: reader.stringOrNull(champLieu),
      portee: PorteeOpportunite.parse(reader.stringOrNull('scope')),
      type: TypeOpportunite.parse(reader.stringOrNull('type')),
      typeLibelle: reader.stringOrNull('type_libelle'),
      joursRestants: reader.intOrNull('jours_restants'),
      dateLimite: reader.dateTimeOrNull(champDateLimite),
      lienCandidature: reader.stringOrNull(champLienCandidature),
    );
  }

  /// L'avertissement anti-frais, lu de `meta`.
  ///
  /// **Systématique, pas conditionnel.** Le contrat tranche la réserve A.6 en
  /// faveur du simulateur et le motive : « une offre frauduleuse ne se signale
  /// pas d'elle-même, et c'est la plus dangereuse ».
  ///
  /// POURQUOI le client ne décide pas de l'afficher selon le type de l'offre :
  /// c'est le serveur qui rédige la phrase, donc lui seul qui sait l'adapter à
  /// un recrutement ou à une conférence payante. Un client qui choisirait
  /// écrirait un jour « aucun employeur ne demande de frais » sous une
  /// conférence — un avertissement faux use la crédibilité des vrais.
  static String? avertissementFromMeta(Map<String, Object?> meta) {
    final String? texte = JsonReader(meta).stringOrNull('avertissement');
    return texte == null || texte.trim().isEmpty ? null : texte;
  }

  static Map<String, Object?> toCacheJson(Opportunite offre) {
    return <String, Object?>{
      'slug': offre.slug,
      'titre': offre.titre,
      'expiree': offre.expiree,
      'verifiee': offre.verifiee,
      champChapo: offre.chapo,
      champCorps: offre.corps,
      champOrganisation: offre.organisation,
      champLieu: offre.lieu,
      'scope': offre.portee?.wireValue,
      'type': offre.type?.wireValue,
      'type_libelle': offre.typeLibelle,
      'jours_restants': offre.joursRestants,
      champDateLimite: offre.dateLimite?.toIso8601String(),
      champLienCandidature: offre.lienCandidature,
    };
  }
}
