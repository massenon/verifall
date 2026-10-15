import 'package:flutter/foundation.dart';

/// Les seules périodes que `GET /me/stats` accepte.
///
/// POURQUOI une énumération fermée et pas une chaîne : le contrat refuse tout
/// le reste en `422`, et « une valeur libre laisserait demander cent mille jours
/// d'historique ».
///
/// POURQUOI aucun sélecteur n'existe à l'écran : le simulateur n'en expose pas
/// (réserve D.4), et en ajouter un serait inventer un contrôle. La période reste
/// [trenteJours], celle des titres de l'écran. L'énumération existe quand même,
/// parce que le paramètre est réel et que le provider le porte.
enum PeriodeStats {
  septJours('7d'),
  trenteJours('30d'),
  quatreVingtDixJours('90d'),
  troisCentSoixanteCinqJours('365d');

  const PeriodeStats(this.wireValue);

  final String wireValue;
}

/// L'activité jour par jour.
///
/// **Les jours sans activité restent dans la série, à zéro.** Le contrat en
/// donne la raison : « un graphique qui saute les jours vides ment sur la
/// régularité, qui est justement ce qu'il montre. » L'histogramme dessine donc
/// toutes les barres, y compris les nulles ; il ne compacte pas.
@immutable
class SerieActivite {
  const SerieActivite({required this.jours, required this.valeurs});

  static const SerieActivite vide = SerieActivite(
    jours: <String>[],
    valeurs: <int>[],
  );

  /// Les étiquettes telles que le serveur les rend (`j/n`).
  final List<String> jours;

  final List<int> valeurs;

  bool get estVide => valeurs.isEmpty;

  int get total => valeurs.fold(0, (int a, int b) => a + b);

  int get maximum =>
      valeurs.isEmpty ? 0 : valeurs.reduce((int a, int b) => a > b ? a : b);
}

/// Un jour du suivi hebdomadaire de série.
@immutable
class JourDeSemaine {
  const JourDeSemaine({
    required this.lettre,
    required this.actif,
    required this.aujourdhui,
    required this.futur,
  });

  /// `L M M J V S D`, rendues par le serveur.
  final String lettre;

  final bool actif;
  final bool aujourdhui;
  final bool futur;
}

/// Ce que `GET /me/stats` rend.
///
/// # Ce qui n'y est pas, et qu'il ne faut pas réintroduire
///
/// L'indicateur « ↗ +3 vs mois dernier » du prototype a été **retiré faute de
/// source** (arbitrage n° 67). Le serveur rend bien une [evolutionMensuelle],
/// mais calculée sur les 30 jours précédant les 30 derniers — pas sur le mois
/// calendaire, « sans quoi on fabrique une chute tous les débuts de mois ».
/// C'est la seule évolution affichable, et seulement avec cette lecture-là.
@immutable
class Statistiques {
  const Statistiques({
    required this.periodeJours,
    required this.verifications,
    required this.intoxDetectees,
    required this.leconsTerminees,
    required this.verdicts,
    required this.types,
    required this.activite,
    required this.semaine,
    required this.calculeLe,
    this.evolutionMensuelle,
    this.leconsCetteSemaine,
  });

  final int periodeJours;

  /// Le total de vérifications du compte, tous verdicts confondus.
  final int verifications;

  /// Faux **et** trompeur : c'est le serveur qui additionne les deux, et le
  /// client ne refait pas la somme. Le simulateur ne comptait que « FAUX » ;
  /// deux formules pour un même chiffre finissent par afficher deux chiffres.
  final int intoxDetectees;

  final int leconsTerminees;
  final int? leconsCetteSemaine;
  final int? evolutionMensuelle;

  /// Valeur technique du verdict → nombre. Les valeurs techniques viennent du
  /// serveur : le client n'en fabrique aucune et n'en suppose aucune.
  final Map<String, int> verdicts;

  /// Type de contenu → nombre. **Tous les types sont représentés, même à
  /// zéro** : « un histogramme dont les colonnes changent de place d'une visite
  /// à l'autre ne se lit pas ».
  final Map<String, int> types;

  final SerieActivite activite;
  final List<JourDeSemaine> semaine;

  /// L'instant du calcul. Affiché quand les statistiques viennent du cache :
  /// « une statistique périmée présentée comme actuelle est un indicateur
  /// faux ».
  final DateTime calculeLe;

  int get totalVerdicts => verdicts.values.fold(0, (int a, int b) => a + b);

  /// Un compte neuf, à distinguer d'une série à zéro.
  ///
  /// POURQUOI la distinction compte : une série de trente jours vides est
  /// **normale** et se dessine ; un compte sans aucune vérification, lui, doit
  /// dire « vos statistiques apparaîtront après votre première vérification ».
  /// « Un donut à zéro et un histogramme plat sont indiscernables d'une panne. »
  bool get compteNeuf => verifications == 0;
}
