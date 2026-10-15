import 'package:flutter/foundation.dart';

import '../entities/home_tableau_de_bord.dart';

/// Ce que l'écran d'accueil affiche, et d'où chaque morceau vient.
///
/// Trois appels, pas six — et les trois sont nommés par la fiche de l'écran 07 :
/// l'agrégat, les trois vérifications récentes, la reprise de cours.
///
/// [repriseLue] distingue « pas encore demandée » de « demandée, il n'y a rien à
/// reprendre ». Le contrat insiste sur ce point : `GET /me/courses/resume` rend
/// `data: null` et **ce n'est pas une erreur**, c'est l'état vide qui fait
/// basculer la carte sur « Commencez votre premier cours ».
@immutable
class AccueilCitoyen {
  const AccueilCitoyen({
    required this.tableau,
    required this.repriseLue,
    this.reprise,
    this.venuDuCache = false,
  });

  final TableauDeBord tableau;

  final bool repriseLue;
  final RepriseDeCours? reprise;

  /// Vrai quand ces données viennent de Drift et non du réseau. L'écran pose
  /// alors le bandeau d'horodatage — « une liste sans les dernières entrées
  /// devient un mensonge sans sa date » (05-HORS-LIGNE §5.4).
  final bool venuDuCache;
}

/// Le dépôt de l'écran d'accueil.
///
/// POURQUOI il vit dans M2 et pas dans l'Académie, alors qu'il lit une reprise
/// de cours : **le tableau de bord ne dépend pas de M3**. La carte « Continuer
/// votre apprentissage » se résout par l'API et par la navigation nommée, jamais
/// par un import du dossier `features/lms/`.
abstract interface class DashboardRepository {
  /// Le cache d'abord (**sans le solde**), le réseau ensuite.
  Future<AccueilCitoyen> lire();

  /// Ce que Drift porte, sans toucher au réseau. Rend `null` au premier
  /// lancement — et un premier lancement sans réseau doit le dire, pas afficher
  /// un tableau de bord vide en silence.
  Future<AccueilCitoyen?> lireLeCache();
}
