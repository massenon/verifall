import 'package:flutter/foundation.dart';

/// Le palier du citoyen — **calculé par le serveur**.
///
/// POURQUOI le client ne calcule rien ici : le simulateur applique « 500 XP par
/// niveau » et six noms de palier. C'est une règle métier ; un client qui la
/// recopie affiche un niveau différent du serveur le jour où le barème change,
/// et les deux se contredisent à l'écran. « Ne jamais recalculer ce que le
/// serveur calcule » (§12 du contrat).
@immutable
class NiveauCitoyen {
  const NiveauCitoyen({
    required this.rang,
    required this.nom,
    required this.xp,
    required this.xpDansLePalier,
    required this.xpRestant,
    required this.pourcentage,
  });

  final int rang;
  final String nom;
  final int xp;
  final int xpDansLePalier;
  final int xpRestant;

  /// De 0 à 100, tel que le serveur le rend.
  final int pourcentage;

  /// La borne haute du palier, lue des deux chiffres servis.
  int get xpDuPalier => xpDansLePalier + xpRestant;

  /// Entre 0 et 1, pour `VfProgressBar`.
  double get avancement => (pourcentage.clamp(0, 100)) / 100;
}

/// La série de vigilance.
@immutable
class SerieDeVigilance {
  const SerieDeVigilance({
    required this.jours,
    required this.vivante,
    this.record,
    this.joursAvantLaPrime,
  });

  final int jours;

  /// Vraie tant que la série n'est pas cassée. Le client ne la déduit pas d'une
  /// date : une série qui « meurt » à minuit sur l'horloge du téléphone est
  /// fausse pour qui voyage.
  final bool vivante;

  /// Le record personnel.
  ///
  /// POURQUOI il est nullable : le contrat ne le nomme pas dans la description
  /// de `GET /me/gamification`, et le simulateur mobile ne l'affiche pas
  /// (réserve D.3). L'agrégat le renvoie aujourd'hui ; s'il cessait, la donnée
  /// serait absente et **elle ne s'inventerait pas**. Le bloc ne s'affiche que
  /// lorsque la valeur arrive.
  final int? record;

  final int? joursAvantLaPrime;
}

/// Un badge, obtenu ou non.
///
/// Les badges **verrouillés arrivent aussi**, avec leur condition en clair : le
/// client ne tient aucune liste de badges, la grille est alimentée entièrement
/// par `GET /me/gamification`.
@immutable
class BadgeCitoyen {
  const BadgeCitoyen({
    required this.slug,
    required this.nom,
    required this.debloque,
    this.description,
    this.icone,
    this.condition,
    this.xp,
    this.unites,
    this.debloqueLe,
  });

  final String slug;
  final String nom;
  final bool debloque;
  final String? description;

  /// Le nom d'icône rendu par le serveur (jeu Material Symbols du prototype).
  final String? icone;

  /// La condition d'obtention, **rédigée par le serveur**. C'est elle qui rend
  /// un cadenas supportable : « un cadenas sans explication n'est qu'une
  /// frustration gratuite ».
  final String? condition;

  final int? xp;
  final int? unites;
  final DateTime? debloqueLe;
}

/// La réponse complète de `GET /me/gamification`.
@immutable
class Recompenses {
  const Recompenses({
    required this.niveau,
    required this.serie,
    required this.badges,
  });

  final NiveauCitoyen niveau;
  final SerieDeVigilance serie;
  final List<BadgeCitoyen> badges;

  int get badgesObtenus => badges.where((BadgeCitoyen b) => b.debloque).length;

  /// Le prochain badge à viser : le premier verrouillé, dans l'ordre rendu par
  /// le serveur.
  ///
  /// POURQUOI il n'y a **aucune progression chiffrée** : l'API rend la condition
  /// en toutes lettres, jamais un couple « `<fait>` / `<objectif>` ». La carte
  /// « Prochaine étape » du simulateur affiche une barre alimentée par un
  /// compteur local ; la reproduire reviendrait à fabriquer un chiffre.
  BadgeCitoyen? get prochaineEtape {
    for (final BadgeCitoyen badge in badges) {
      if (!badge.debloque) {
        return badge;
      }
    }
    return null;
  }
}

/// Un certificat délivré.
///
/// POURQUOI il n'y a **aucun champ ni action de PDF** : `GET /me/certificates/
/// {code}/pdf` n'existe pas en v1 (§11 du contrat) — aucune bibliothèque de
/// génération n'est installée côté serveur. Le bouton est retiré plutôt que
/// laissé inerte ; la vérification passe par la page publique, sur le [code].
@immutable
class Certificat {
  const Certificat({
    required this.code,
    required this.coursTitre,
    this.titulaire,
    this.coursSlug,
    this.delivreLe,
    this.revoque = false,
    this.revoqueLe,
  });

  /// L'identifiant **public**, « conçu pour être lu à voix haute et recopié ».
  final String code;

  final String coursTitre;

  /// Le nom au moment de la délivrance : « un diplôme porte le nom sous lequel
  /// il a été obtenu ».
  final String? titulaire;

  final String? coursSlug;
  final DateTime? delivreLe;
  final bool revoque;
  final DateTime? revoqueLe;
}
