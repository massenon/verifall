import 'package:flutter/foundation.dart';

import '../../data/models/json_reader.dart';

/// Les compteurs de l'accueil — `compteurs` de `GET /me/dashboard`.
///
/// **Quatre valeurs, et ce sont celles que le serveur calcule.** Le simulateur
/// affiche « Points d'expérience » en troisième tuile ; l'agrégat ne sert pas
/// l'XP absolu — seulement un niveau et un pourcentage de palier. Afficher un
/// nombre d'XP exigerait de l'inventer. Voir `docs/ARBITRAGES.md`.
///
/// **`unites` ne se met jamais en cache** (§7.1). C'est la seule valeur de cet
/// agrégat qui soit interdite de cache, et c'est pourquoi elle est nullable :
/// `null` signifie « pas encore relue », et la pastille affiche alors un tiret,
/// jamais un zéro qui ferait croire à un compte vide.
@immutable
class CompteursCitoyen {
  const CompteursCitoyen({
    required this.verifications,
    required this.coursSuivis,
    required this.badges,
    this.unites,
  });

  factory CompteursCitoyen.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return CompteursCitoyen(
      verifications: reader.intValue('verifications'),
      coursSuivis: reader.intValue('cours_suivis'),
      badges: reader.intValue('badges'),
      unites: reader.intOrNull('unites'),
    );
  }

  final int verifications;
  final int coursSuivis;
  final int badges;
  final int? unites;

  /// La copie **sans le solde**, celle qui part au cache Drift.
  CompteursCitoyen sansLeSolde() => CompteursCitoyen(
    verifications: verifications,
    coursSuivis: coursSuivis,
    badges: badges,
  );
}

/// Le niveau du citoyen — `niveau` de l'agrégat.
@immutable
class NiveauCitoyen {
  const NiveauCitoyen({
    required this.rang,
    required this.nom,
    required this.pourcentage,
  });

  factory NiveauCitoyen.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return NiveauCitoyen(
      rang: reader.intValue('rang'),
      nom: reader.string('nom'),
      pourcentage: reader.intValue('pourcentage'),
    );
  }

  final int rang;
  final String nom;

  /// Avancement dans le palier, **recalculé côté serveur** : « deux formules
  /// donneraient deux pourcentages » (§7.4).
  final int pourcentage;
}

/// La série de vigilance — `serie` de l'agrégat.
@immutable
class SerieDeVigilance {
  const SerieDeVigilance({required this.jours, required this.vivante});

  factory SerieDeVigilance.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return SerieDeVigilance(
      jours: reader.intValue('jours'),
      vivante: reader.boolValue('vivante'),
    );
  }

  final int jours;

  /// La série est-elle encore en cours ? **Fait serveur** : une horloge de
  /// téléphone mal réglée casserait ou prolongerait une série à tort.
  final bool vivante;
}

/// L'activité sur 30 jours — `activité` de l'agrégat.
///
/// Deux tableaux de même longueur : les étiquettes de jour et les valeurs.
/// **Les jours sans activité restent à zéro** : « un graphique qui saute les
/// jours vides ment sur la régularité, qui est justement ce qu'il montre »
/// (§10.6).
///
/// Une seule série est servie — les vérifications. La légende du simulateur en
/// annonce deux (Vérifications, Leçons terminées) ; la seconde n'existe pas dans
/// l'agrégat, et une courbe de leçons inventée serait un chiffre fabriqué.
@immutable
class ActiviteTrenteJours {
  const ActiviteTrenteJours({required this.jours, required this.valeurs});

  factory ActiviteTrenteJours.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    final List<String> jours = <String>[
      for (final Object? j in reader.listOrEmpty('jours')) '$j',
    ];
    final List<int> valeurs = <int>[
      for (final Object? v in reader.listOrEmpty('valeurs'))
        if (v is num) v.toInt() else 0,
    ];
    // Les deux tableaux doivent avoir la meme longueur. S'ils divergent, on
    // s'aligne sur le plus court plutôt que d'afficher une barre sans jour :
    // une barre orpheline serait un point de donnee sans date.
    final int n = jours.length < valeurs.length ? jours.length : valeurs.length;
    return ActiviteTrenteJours(
      jours: jours.take(n).toList(growable: false),
      valeurs: valeurs.take(n).toList(growable: false),
    );
  }

  final List<String> jours;
  final List<int> valeurs;

  bool get estVide => valeurs.isEmpty || valeurs.every((int v) => v == 0);

  int get maximum =>
      valeurs.isEmpty ? 0 : valeurs.reduce((int a, int b) => a > b ? a : b);

  /// Le jour du maximum, pour le résumé textuel du lecteur d'écran : « vingt
  /// barres non étiquetées ne se lisent pas ».
  String? get jourDuMaximum {
    if (valeurs.isEmpty || maximum == 0) {
      return null;
    }
    return jours[valeurs.indexOf(maximum)];
  }
}

/// Le cours offert du mois — `cours_offert`, **`null` s'il n'y en a pas**.
///
/// Ce `null` n'est pas un état d'erreur : c'est ce qui empêche d'annoncer une
/// offre qui n'existe pas. La carte disparaît alors entièrement ; elle ne
/// s'affiche pas vide.
@immutable
class CoursOffert {
  const CoursOffert({
    required this.slug,
    required this.titre,
    this.categorie,
    this.duree,
  });

  factory CoursOffert.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return CoursOffert(
      slug: reader.string('slug'),
      titre: reader.string('titre'),
      categorie: reader.stringOrNull('categorie'),
      duree: reader.stringOrNull('duree'),
    );
  }

  final String slug;
  final String titre;
  final String? categorie;
  final String? duree;
}

/// La reprise de cours — `GET /me/courses/resume`.
///
/// **Rend `data: null` quand il n'y a rien à reprendre**, et le contrat insiste :
/// « ce n'est pas une erreur, c'est un état vide ». D'où l'existence de la
/// seconde variante de la carte, « Commencez votre premier cours ».
///
/// POURQUOI ce type vit dans M2 et non dans l'Académie : le tableau de bord **ne
/// dépend pas de M3**. La dépendance se résout par l'API et par la navigation
/// nommée, jamais par un import croisé entre deux dossiers `features/`.
@immutable
class RepriseDeCours {
  const RepriseDeCours({
    required this.coursSlug,
    required this.coursTitre,
    required this.avancement,
    this.prochaineLeconTitre,
  });

  factory RepriseDeCours.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    final JsonReader inscription = JsonReader(
      reader.objectOrNull('inscription') ?? const <String, Object?>{},
    );
    final JsonReader cours = JsonReader(
      reader.objectOrNull('cours') ?? const <String, Object?>{},
    );
    final Map<String, Object?>? lecon = reader.objectOrNull('prochaine_lecon');
    return RepriseDeCours(
      coursSlug: cours.string('slug'),
      coursTitre: cours.string('titre'),
      // L'avancement vient de la colonne recalculee par le serveur : « deux
      // formules de progression finiraient par afficher deux pourcentages
      // differents pour le meme cours sur deux ecrans voisins ».
      avancement: inscription.intValue('avancement'),
      prochaineLeconTitre: lecon == null
          ? null
          : JsonReader(lecon).stringOrNull('titre'),
    );
  }

  final String coursSlug;
  final String coursTitre;
  final int avancement;

  /// `null` quand il ne reste que le quiz : le serveur ne rend alors aucune
  /// leçon suivante, et l'écran ne fabrique pas « Quiz final ».
  final String? prochaineLeconTitre;
}

/// L'agrégat de l'écran d'accueil.
///
/// **Un seul appel, et c'est le point** : « la première image doit tenir sous
/// 400 ms, et six allers-retours sur un réseau mobile béninois feraient de
/// l'écran d'accueil le plus lent de l'application — celui qu'on voit à chaque
/// ouverture » (§10.6).
@immutable
class TableauDeBord {
  const TableauDeBord({
    required this.salutation,
    required this.compteurs,
    required this.niveau,
    required this.serie,
    required this.activite,
    this.coursOffert,
    this.luLe,
  });

  factory TableauDeBord.fromJson(Map<String, Object?> json, {DateTime? luLe}) {
    final JsonReader reader = JsonReader(json);
    final Map<String, Object?>? offert = reader.objectOrNull('cours_offert');
    return TableauDeBord(
      salutation: reader.string('salutation'),
      compteurs: CompteursCitoyen.fromJson(
        reader.objectOrNull('compteurs') ?? const <String, Object?>{},
      ),
      niveau: NiveauCitoyen.fromJson(
        reader.objectOrNull('niveau') ?? const <String, Object?>{},
      ),
      serie: SerieDeVigilance.fromJson(
        reader.objectOrNull('serie') ?? const <String, Object?>{},
      ),
      activite: ActiviteTrenteJours.fromJson(
        reader.objectOrNull('activite') ?? const <String, Object?>{},
      ),
      coursOffert: offert == null ? null : CoursOffert.fromJson(offert),
      luLe: luLe,
    );
  }

  /// Le prénom, tel que le serveur le rend. Le client ne le déduit pas du
  /// profil : deux sources pour un même mot finissent par ne plus dire pareil.
  final String salutation;

  final CompteursCitoyen compteurs;
  final NiveauCitoyen niveau;
  final SerieDeVigilance serie;
  final ActiviteTrenteJours activite;
  final CoursOffert? coursOffert;

  /// L'instant où ces données ont été lues.
  ///
  /// POURQUOI il est indispensable : la ligne « synchronisé il y a X » doit
  /// afficher **l'âge réel du cache**. Le simulateur écrit « il y a 2 min » en
  /// dur ; une valeur figée est un mensonge dès la seconde ouverture.
  final DateTime? luLe;

  /// La copie qui part au cache : **sans le solde d'unités**.
  TableauDeBord pourLeCache() => TableauDeBord(
    salutation: salutation,
    compteurs: compteurs.sansLeSolde(),
    niveau: niveau,
    serie: serie,
    activite: activite,
    coursOffert: coursOffert,
    luLe: luLe,
  );
}
