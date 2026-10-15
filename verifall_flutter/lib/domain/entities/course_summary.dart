import 'package:flutter/foundation.dart';

import 'course_level.dart';

/// L'auteur d'un contenu, tel que `AuteurResource` le rend.
///
/// POURQUOI ce type est redéclaré ici plutôt qu'importé de `data/models` :
/// « `presentation/` ne connaît que `domain/` ; un écran qui importe un modèle
/// JSON est un écran qui cassera au premier changement de contrat » (§1.2). Le
/// modèle de transport `Auteur` du socle est **converti** vers ce type par le
/// dépôt, il n'est pas exposé aux écrans.
///
/// **Trois champs, et pas de rôle.** L'API ne sérialise jamais le rôle d'un
/// auteur : « publier "untel est modérateur" sur chaque message dresserait la
/// liste nominative de l'équipe de modération » (§9 du contrat). La ligne
/// formateur de l'écran 15 perd donc le rôle que montre le simulateur — c'est
/// une divergence consignée, pas un oubli.
@immutable
class Intervenant {
  const Intervenant({
    required this.uuid,
    required this.nomAffichage,
    required this.initiales,
  });

  final String uuid;
  final String nomAffichage;

  /// Les initiales viennent du serveur : les recalculer donnerait deux
  /// résultats différents sur un nom composé.
  final String initiales;
}

/// Un cours du catalogue — `CoursResource` en contexte de liste.
///
/// # Ce que ce type ne porte PAS, et pourquoi
///
/// **Ni note, ni nombre d'avis, ni nombre d'inscrits.** Ces trois colonnes
/// existent en base mais `CoursResource` ne les sérialise pas : elles ne sont
/// donc pas au contrat, et « ce qui n'y est pas n'existe pas ». La carte
/// affiche **« Nouveau »** (arbitrage n° 61) et la ligne « `<m>` inscrits » du
/// simulateur disparaît (arbitrage n° 67 : les chiffres sont comptés ou
/// retirés). Fabriquer une étoile à 4,8 serait publier une note que personne
/// n'a donnée.
@immutable
class CourseSummary {
  const CourseSummary({
    required this.slug,
    required this.titre,
    required this.description,
    required this.categorie,
    required this.niveau,
    required this.duree,
    required this.couvertureUrl,
    required this.coutUnites,
    required this.estOffert,
    required this.objectifs,
    required this.delivreUnCertificat,
    required this.nombreDEtapes,
    this.formateur,
  });

  /// **L'identifiant d'un cours est son `slug`**, jamais un entier (§4 du
  /// contrat).
  final String slug;

  final String titre;
  final String description;
  final String categorie;
  final NiveauAffiche niveau;

  /// `duration_label` — un libellé rédigé côté serveur (« 2 h 30 »), pas un
  /// nombre de minutes à mettre en forme ici.
  final String duree;

  final String? couvertureUrl;

  /// Le coût **actuel**, `coutActuel()` côté serveur — pas le prix en colonne.
  ///
  /// POURQUOI la distinction compte : « un cours peut être offert jusqu'à une
  /// date. Renvoyer `cost_units` brut ferait annoncer un prix là où le serveur
  /// ne débitera rien — et l'écran mentirait dans le sens qui fâche. »
  final int coutUnites;

  /// Gratuité **temporaire** en cours. Le « cours offert du mois » est une date
  /// de fin côté serveur, pas un drapeau (arbitrage n° 40) : le client ne
  /// calcule rien, il reflète.
  final bool estOffert;

  final List<String> objectifs;
  final bool delivreUnCertificat;

  /// Leçons + quiz + exercices. C'est le dénominateur de l'avancement.
  final int nombreDEtapes;

  final Intervenant? formateur;

  bool get estGratuit => coutUnites == 0;
}
