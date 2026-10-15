import 'package:flutter/foundation.dart';

import 'course_summary.dart';

/// L'état d'une séance — **déduit de l'heure par le serveur**, jamais une
/// colonne : « une séance stockée "planifiée" resterait planifiée le lendemain
/// si personne ne repassait derrière ».
enum EtatSeance {
  aVenir('upcoming'),
  enDirect('live'),
  passee('past'),
  inconnu('');

  const EtatSeance(this.valeurTechnique);

  final String valeurTechnique;

  static EtatSeance parse(String? raw) => switch (raw) {
    'upcoming' => aVenir,
    'live' => enDirect,
    'past' => passee,
    _ => inconnu,
  };
}

/// Une séance en direct — `SeanceResource`.
@immutable
class LiveClassSession {
  const LiveClassSession({
    required this.uuid,
    required this.titre,
    required this.description,
    required this.etat,
    required this.minutes,
    this.debuteLe,
    this.places,
    this.animateur,
    this.coursSlug,
    this.lienVisio,
    this.rappelDemande,
  });

  final String uuid;
  final String titre;
  final String description;

  /// **Lu du serveur.** Les trois onglets se répartissent d'après ce champ ;
  /// la liste n'étant pas paginée (contrat §3.3), la répartition côté client
  /// est ici légitime — et elle marche hors ligne.
  final EtatSeance etat;

  final int minutes;
  final DateTime? debuteLe;
  final int? places;
  final Intervenant? animateur;
  final String? coursSlug;

  /// **`null` tant que la séance n'est pas joignable.**
  ///
  /// Le contrat refuse de publier trois semaines à l'avance un lien qui
  /// circulerait, « et l'animateur découvre une salle pleine d'inconnus ». Le
  /// bouton « Rejoindre » n'est donc actif que si ce champ est présent — c'est
  /// une règle de produit, pas une précaution d'affichage.
  final String? lienVisio;

  /// `rappel_demande` — posé par `whenHas`, donc **absent** hors du contexte où
  /// le serveur l'a calculé. `null` signifie « le serveur ne s'est pas
  /// prononcé », pas « non ».
  final bool? rappelDemande;

  bool get estJoignable => lienVisio != null && lienVisio!.isNotEmpty;
}
