import 'package:flutter/foundation.dart';

import '../../data/models/json_reader.dart';
import 'verification_libelle.dart';
import 'verification_rapport.dart';
import 'verification_statut.dart';

/// Une vérification — `VerificationResource`, l'objet central du produit.
///
/// **Un seul objet pour deux écrans.** `GET /verifications/{uuid}` sert le suivi
/// (écran 10) et le rapport (écran 11) : *« c'est le même objet à deux moments
/// de sa vie, et le client n'a pas à savoir lequel appeler avant d'avoir lu
/// l'état »* (§10.3).
///
/// La bascule entre les deux écrans se fait sur **la nullité de [rapport]**,
/// jamais sur sa vacuité. Voir [VerificationRapport].
@immutable
class Verification {
  const Verification({
    required this.uuid,
    required this.reference,
    required this.type,
    required this.statut,
    required this.terminee,
    required this.affirmation,
    required this.apercu,
    required this.avancement,
    required this.estUneReprise,
    this.statutLibelle,
    this.contexte,
    this.coutUnites,
    this.etape,
    this.etapeLibelle,
    this.soumiseLe,
    this.rapport,
  });

  factory Verification.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    final Map<String, Object?>? rapport = reader.objectOrNull('rapport');
    return Verification(
      uuid: reader.string('uuid'),
      reference: reader.string('reference'),
      type:
          ValeurLibelle.lire(reader, 'type') ??
          const ValeurLibelle(valeur: '', libelle: ''),
      statut: StatutVerification.parse(reader.stringOrNull('statut')),
      statutLibelle: reader.stringOrNull('statut_libelle'),
      terminee: reader.boolValue('terminee'),
      affirmation: reader.string('affirmation'),
      apercu: reader.string('apercu'),
      contexte: reader.stringOrNull('contexte'),
      coutUnites: reader.intOrNull('cout_unites'),
      avancement: reader.intValue('avancement'),
      etape: reader.intOrNull('etape'),
      etapeLibelle: reader.stringOrNull('etape_libelle'),
      estUneReprise: reader.boolValue('est_une_reprise'),
      soumiseLe: reader.dateTimeOrNull('soumise_le'),
      // `rapport` est TOUJOURS present dans la reponse ; il vaut `null` tant
      // qu'aucun verdict n'a ete rendu. On ne construit donc jamais un rapport
      // a partir d'un objet vide : `{}` ferait croire a un verdict vide.
      rapport: rapport == null || rapport.isEmpty
          ? null
          : VerificationRapport.fromJson(rapport),
    );
  }

  final String uuid;

  /// « V-000123 », **calculée par le serveur** et affichée en monospace sur le
  /// rapport. Le client ne la compose pas : elle dérive d'un identifiant entier
  /// qui n'est jamais exposé.
  final String reference;

  /// Le type, avec son libellé serveur (`type` + `type_libelle`).
  final ValeurLibelle type;

  final StatutVerification statut;

  /// Le libellé du statut, **rédigé par le serveur**. C'est lui qui s'affiche à
  /// la place du verdict sur une analyse en cours — « un tiret laisserait croire
  /// à un échec » (arbitrage n° 31).
  final String? statutLibelle;

  /// L'analyse est-elle dans un état final ? Calculé par le serveur.
  final bool terminee;

  final String affirmation;

  /// L'aperçu tronqué par le serveur, pour les listes. Le client ne retronque
  /// pas : deux troncatures donneraient deux textes différents.
  final String apercu;

  final String? contexte;

  /// Le coût **effectivement débité**, connu seulement après la soumission.
  /// Avant l'action, il n'existe pas côté client — c'est voulu.
  final int? coutUnites;

  /// De 0 à 100, **posé par l'orchestrateur** au moment où il franchit une
  /// étape. Aucune minuterie locale ne l'anime : « une barre qui avance toute
  /// seule pendant qu'un service est en panne ment à qui la regarde »
  /// (arbitrage n° 28).
  final int avancement;

  /// Le rang de l'étape courante. Le client ne connaît ni la liste complète ni
  /// son cardinal : le serveur ne les publie pas.
  final int? etape;

  /// Le libellé de l'étape courante, rédigé par le serveur.
  final String? etapeLibelle;

  /// Vrai quand la réponse a été reprise d'une analyse antérieure.
  ///
  /// Ce cas mérite un traitement à part (arbitrage n° 25) : la réponse arrive
  /// **instantanément** et les unités **ne sont pas débitées**. Une personne à
  /// qui l'on a annoncé un coût doit savoir pourquoi elle n'a rien payé.
  final bool estUneReprise;

  final DateTime? soumiseLe;

  /// `null` tant qu'aucun verdict n'a été rendu. **Jamais `{}`.**
  final VerificationRapport? rapport;

  /// L'écran à ouvrir : le rapport s'il existe, le suivi sinon.
  bool get aUnRapport => rapport != null;

  Verification copyWith({VerificationRapport? rapport}) => Verification(
    uuid: uuid,
    reference: reference,
    type: type,
    statut: statut,
    statutLibelle: statutLibelle,
    terminee: terminee,
    affirmation: affirmation,
    apercu: apercu,
    contexte: contexte,
    coutUnites: coutUnites,
    avancement: avancement,
    etape: etape,
    etapeLibelle: etapeLibelle,
    estUneReprise: estUneReprise,
    soumiseLe: soumiseLe,
    rapport: rapport ?? this.rapport,
  );

  /// La forme sérialisée pour le cache local — **mêmes clés que l'API**, pour
  /// que `fromJson` serve les deux sources.
  Map<String, Object?> toJson() => <String, Object?>{
    'uuid': uuid,
    'reference': reference,
    'type': type.valeur,
    'type_libelle': type.libelle,
    'statut': statut.wireValue,
    'statut_libelle': statutLibelle,
    'terminee': terminee,
    'affirmation': affirmation,
    'apercu': apercu,
    'contexte': contexte,
    'cout_unites': coutUnites,
    'avancement': avancement,
    'etape': etape,
    'etape_libelle': etapeLibelle,
    'est_une_reprise': estUneReprise,
    'soumise_le': soumiseLe?.toIso8601String(),
    'rapport': rapport?.toJson(),
  };

  @override
  String toString() => 'Verification($uuid, ${statut.wireValue})';
}
