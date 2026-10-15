import 'package:flutter/foundation.dart';

/// Le type d'une leçon — `TypeLecon` côté serveur.
enum TypeLecon {
  texte('text'),
  video('video'),
  inconnu('');

  const TypeLecon(this.valeurTechnique);

  final String valeurTechnique;

  static TypeLecon parse(String? raw) => switch (raw) {
    'text' => texte,
    'video' => video,
    _ => inconnu,
  };
}

/// Une leçon telle que la liste d'un cours la rend — **sans son contenu**.
///
/// « Le `contenu` d'une leçon n'accompagne jamais cette liste — sinon c'est le
/// corps entier du cours qui partirait à chaque ouverture du catalogue. »
@immutable
class LessonSummary {
  const LessonSummary({
    required this.uuid,
    required this.position,
    required this.titre,
    required this.type,
    required this.minutes,
    required this.ouverte,
    required this.acquise,
    this.videoUrl,
  });

  /// **L'identifiant d'une leçon est un uuid** (§4 du contrat).
  final String uuid;

  final int position;
  final String titre;
  final TypeLecon type;
  final int minutes;
  final String? videoUrl;

  /// `data.lecons[].ouverte` — **tranché par le serveur**.
  ///
  /// POURQUOI il est nullable : le champ est posé par `whenHas`, donc il est
  /// **absent** hors du contexte où le serveur l'a calculé. Le contrat sépare
  /// explicitement « absent » (non applicable ici) de « présent et nul »
  /// (connu, mais vide) — §8. `null` signifie donc « le serveur ne s'est pas
  /// prononcé », et l'écran ne le remplace pas par une supposition.
  ///
  /// « Un client qui la recalcule ouvrirait un jour une leçon qui ne devait pas
  /// l'être » (table d'arbitrage §7.4). Le client ne recalcule rien.
  final bool? ouverte;

  /// `data.lecons[].acquise` — idem, calculé par le serveur.
  final bool? acquise;

  bool get estOuverte => ouverte ?? false;
  bool get estAcquise => acquise ?? false;

  /// Verrouillée = le serveur a dit non. Un champ absent n'est **pas** un
  /// verrou : c'est une absence d'information.
  bool get estVerrouillee => ouverte == false;
}
