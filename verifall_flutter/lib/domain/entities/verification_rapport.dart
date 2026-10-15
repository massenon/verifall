import 'package:flutter/foundation.dart';

import '../../data/models/json_reader.dart';
import 'verification_libelle.dart';

/// Une étape franchie, telle que le serveur l'a inscrite au moment où il l'a
/// franchie (`data.rapport.deroule[]`).
///
/// POURQUOI elle porte son libellé : c'est le serveur qui nomme ses étapes. Le
/// client n'en connaît ni la liste ni l'ordre — et il n'a pas à les connaître,
/// sans quoi une étape ajoutée côté serveur ne s'afficherait jamais.
@immutable
class EtapeFranchie {
  const EtapeFranchie({
    required this.code,
    required this.libelle,
    required this.franchieLe,
  });

  factory EtapeFranchie.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return EtapeFranchie(
      code: reader.string('etape'),
      libelle: reader.string('libelle'),
      franchieLe: reader.dateTimeOrNull('at'),
    );
  }

  final String code;
  final String libelle;
  final DateTime? franchieLe;
}

/// Une source consultée (`ReportEvidence` → `PreuveResource`).
///
/// Ajoutée le 9 octobre 2026 : la Phase 11 côté API a exposé `preuves` sur
/// `RapportResource` — audit Flutter/Laravel du 8 octobre 2026, qui avait
/// relevé que le client ne lisait pas ce champ pourtant déjà servi.
@immutable
class Preuve {
  const Preuve({
    required this.nom,
    this.url,
    this.domaine,
    this.editeur,
    this.position,
    required this.fiabilite,
    required this.recuperee,
    this.publieeLe,
  });

  factory Preuve.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return Preuve(
      nom: reader.string('nom'),
      url: reader.stringOrNull('url'),
      domaine: reader.stringOrNull('domaine'),
      editeur: reader.stringOrNull('editeur'),
      position: ValeurLibelle.lire(reader, 'position'),
      fiabilite: reader.intValue('fiabilite'),
      recuperee: reader.boolValue('recuperee'),
      publieeLe: reader.dateTimeOrNull('publiee_le'),
    );
  }

  final String nom;
  final String? url;
  final String? domaine;

  /// Le média éditeur, quand distinct du nom affiché (ex. une tribune signée).
  final String? editeur;

  /// La position de la source vis-à-vis de l'affirmation : corrobore,
  /// contredit, neutre... Valeur technique + libellé du serveur, comme
  /// [VerificationRapport.verdict] — jamais fabriqué côté client.
  final ValeurLibelle? position;

  /// Pourcentage de fiabilité de la source, tel que noté par le serveur.
  final int fiabilite;

  /// Vrai si la page a réellement pu être ouverte par le collecteur de
  /// preuves, faux si la source est citée sans avoir pu être consultée.
  final bool recuperee;

  final DateTime? publieeLe;

  Map<String, Object?> toJson() => <String, Object?>{
    'nom': nom,
    'url': url,
    'domaine': domaine,
    'editeur': editeur,
    'position': position?.valeur,
    'position_libelle': position?.libelle,
    'fiabilite': fiabilite,
    'recuperee': recuperee,
    'publiee_le': publieeLe?.toIso8601String(),
  };
}

/// Le rapport d'analyse — `RapportResource`.
///
/// # La règle qui gouverne ce type
///
/// > « `data.rapport` est **`null`** tant qu'aucun verdict n'a été rendu. La clé
/// > est toujours présente : `rapport: {}` ferait croire à un verdict vide, ce
/// > qui est le pire malentendu possible sur une plateforme de
/// > fact-checking. » (§10.3)
///
/// Le client teste donc la **nullité** de l'objet, jamais sa vacuité. C'est
/// pourquoi il n'existe **aucun** constructeur de rapport vide ici : on ne peut
/// pas en fabriquer un par accident.
///
/// # Ce que le contrat ne sert pas, et qui manque donc ici
///
/// - **le détail des moteurs** (`moteurs`, servi depuis la Phase 11) : reçu par
///   l'API mais délibérément NON lu ici — « publier quel moteur a répondu quoi
///   revient à publier notre grille d'achat » (§9). C'est une décision
///   éditoriale, pas un oubli : ne pas le câbler simplement parce que le champ
///   existe. `scores` existe aussi mais porte une **répartition par verdict**,
///   dont les clés sont des valeurs techniques sans libellé : les afficher
///   obligerait à fabriquer un libellé, ce que le contrat interdit (§8).
///
/// Voir `docs/ARBITRAGES.md`.
@immutable
class VerificationRapport {
  const VerificationRapport({
    required this.uuid,
    this.verdict,
    this.confiance,
    this.synthese,
    this.reserve,
    this.indiceHaine,
    this.indiceHaineMotifs = const <String>[],
    this.indiceHaineNote,
    this.deroule = const <EtapeFranchie>[],
    this.preuves = const <Preuve>[],
    this.publieLe,
  });

  factory VerificationRapport.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return VerificationRapport(
      uuid: reader.string('uuid'),
      verdict: ValeurLibelle.lire(reader, 'verdict'),
      confiance: reader.intOrNull('confiance'),
      synthese: reader.stringOrNull('synthese'),
      reserve: reader.stringOrNull('reserve'),
      indiceHaine: reader.intOrNull('indice_haine'),
      indiceHaineMotifs: <String>[
        for (final Object? motif in reader.listOrEmpty('indice_haine_motifs'))
          if (motif != null) '$motif',
      ],
      indiceHaineNote: reader.stringOrNull('indice_haine_note'),
      deroule: <EtapeFranchie>[
        for (final Map<String, Object?> etape in reader.objectListOrEmpty(
          'deroule',
        ))
          EtapeFranchie.fromJson(etape),
      ],
      preuves: <Preuve>[
        for (final Map<String, Object?> preuve in reader.objectListOrEmpty(
          'preuves',
        ))
          Preuve.fromJson(preuve),
      ],
      publieLe: reader.dateTimeOrNull('publie_le'),
    );
  }

  final String uuid;

  /// `null` sur un rapport rendu sans verdict tranché. L'écran ne le remplace
  /// **jamais** par une valeur plausible.
  final ValeurLibelle? verdict;

  /// L'indice de fiabilité, en pourcentage entier. `null` = non calculé.
  final int? confiance;

  /// La prose « Pourquoi ce résultat ? ». Peut manquer.
  final String? synthese;

  /// La réserve rédigée par le serveur — c'est elle qui porte, en toutes
  /// lettres, « Vos unités vous sont rendues » sur une analyse partielle, et
  /// « ce rapport est soumis à un arbitrage humain » sur une contradiction.
  ///
  /// POURQUOI l'écran l'affiche telle quelle : le remboursement et l'arbitrage
  /// sont des faits serveur. Les reformuler côté client, c'est risquer
  /// d'annoncer un remboursement qui n'a pas eu lieu.
  final String? reserve;

  /// L'indice de discours haineux, en pourcentage. `null` = non mesuré, et la
  /// carte disparaît alors — « un indicateur qu'on ne mesure pas ne s'affiche
  /// pas ».
  final int? indiceHaine;

  final List<String> indiceHaineMotifs;

  /// La note rédigée par l'analyseur. Quand elle manque, l'écran n'invente pas
  /// la phrase rassurante du simulateur : elle serait fausse dès que l'indice
  /// dépasse zéro.
  final String? indiceHaineNote;

  /// Les étapes réellement franchies, horodatées par le serveur.
  final List<EtapeFranchie> deroule;

  /// Les sources consultées, les plus fiables d'abord — même ordre que la
  /// carte web « Sources et preuves consultées ».
  final List<Preuve> preuves;

  /// `null` sur un rapport **non publié** — celui d'un arbitrage humain, qui
  /// « existe, est consultable par son auteur, mais n'engage pas encore la
  /// plateforme ».
  final DateTime? publieLe;

  /// La forme sérialisée pour le cache local.
  ///
  /// POURQUOI elle reprend **exactement** les clés de l'API : le cache se relit
  /// avec `fromJson`, le même décodeur que le réseau. Deux formats, c'est deux
  /// occasions de diverger, et la divergence se voit d'abord hors ligne — le
  /// moment où l'on peut le moins la corriger.
  Map<String, Object?> toJson() => <String, Object?>{
    'uuid': uuid,
    'verdict': verdict?.valeur,
    'verdict_libelle': verdict?.libelle,
    'confiance': confiance,
    'synthese': synthese,
    'reserve': reserve,
    'indice_haine': indiceHaine,
    'indice_haine_motifs': indiceHaineMotifs,
    'indice_haine_note': indiceHaineNote,
    'deroule': <Map<String, Object?>>[
      for (final EtapeFranchie etape in deroule)
        <String, Object?>{
          'etape': etape.code,
          'libelle': etape.libelle,
          'at': etape.franchieLe?.toIso8601String(),
        },
    ],
    'preuves': <Map<String, Object?>>[
      for (final Preuve preuve in preuves) preuve.toJson(),
    ],
    'publie_le': publieLe?.toIso8601String(),
  };

  @override
  String toString() => 'VerificationRapport($uuid)';
}
