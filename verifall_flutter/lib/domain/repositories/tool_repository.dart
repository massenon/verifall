import '../entities/tool_outil.dart';

/// Le dépôt de l'annuaire d'outils.
///
/// Deux précisions du contrat gouvernent cette interface :
///
/// 1. **`GET /tools` n'est pas paginé.** C'est l'une des quatre listes
///    « fermées, tenues à la main par l'équipe » — une vingtaine d'outils. « Y
///    mettre un curseur compliquerait le client pour une pagination qui ne se
///    déclencherait jamais » (§3.3). Il n'y a donc **aucun curseur ici**.
/// 2. **Le cache vit 24 heures** (§7.1). C'est l'un des rares écrans où un cache
///    long est légitime : un répertoire d'outils ne change pas dans la journée.
///
/// Le filtre par catégorie n'apparaît pas dans cette interface, et c'est
/// délibéré : la liste étant fermée et non paginée, il se fait **côté client**,
/// sur la liste entière — ce qui est la seule façon qu'il marche hors ligne.
/// Voir `docs/ARBITRAGES.md`.
abstract interface class ToolRepository {
  /// L'annuaire complet, cache d'abord puis réseau.
  Future<AnnuaireOutils> annuaire();

  /// L'annuaire tel que le cache le porte, avec sa date de lecture.
  Future<({AnnuaireOutils? annuaire, DateTime? luLe})> annuaireEnCache();
}
