import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../database.dart';

/// Ce qu'on a trouvé en cache : le contenu **et** sa date.
///
/// POURQUOI la date voyage avec le contenu : sans elle, l'écran ne peut pas
/// poser le bandeau « Données du `<date>`, actualisation impossible », et « une
/// échéance J-2 vieille de trois jours devient un mensonge sans sa date »
/// (05-HORS-LIGNE §5.4). Un cache qui rendrait seulement le contenu rendrait ce
/// bandeau impossible à écrire — donc, en pratique, jamais écrit.
@immutable
class EntreeCache {
  const EntreeCache({required this.contenu, required this.recupereLe});

  final Map<String, Object?> contenu;
  final DateTime recupereLe;
}

/// Le cache de lecture du parcours community.
///
/// # Ce qui est mis en cache, et pour combien de temps
///
/// | Donnée | Durée de vie |
/// |---|---|
/// | Brèves et opportunités (`GET /hub/*`) | **15 min** |
/// | Fils et sujets du forum déjà lus | lecture seule hors ligne |
///
/// Les 15 minutes du Hub sont « la durée la plus courte de tout le contrat »
/// (§7.1), et c'est cohérent : c'est le contenu le plus périssable de
/// l'application, parce qu'une opportunité porte une échéance.
///
/// # Ce qui n'y entre jamais
///
/// Rien de ce que le contrat interdit de cacher — solde d'unités, état d'une
/// analyse en cours, résultat d'un quiz. Aucun des trois ne passe par ce
/// parcours ; la règle est rappelée pour que personne n'en ajoute.
///
/// # Pourquoi seule la première page est mise en cache
///
/// Une liste paginée par curseur n'a pas de fin connue, et cacher toutes les
/// pages ferait grossir la base indéfiniment pour un contenu qui périme en
/// quinze minutes. Hors ligne, l'écran montre donc **la première page du dernier
/// chargement**, horodatée, et le défilement infini s'arrête là — ce qui est
/// exact, plutôt que plausible.
abstract interface class CommunityCacheDatasource {
  Future<EntreeCache?> lire(String cle);

  Future<void> ecrire(String cle, Map<String, Object?> contenu);

  Future<void> oublier(String cle);
}

/// Les clés du cache, construites en un seul endroit.
///
/// POURQUOI une classe de fabrication plutôt que des chaînes composées à la
/// volée : deux endroits qui composent la même clé finissent par la composer
/// différemment, et le cache cesse silencieusement de servir.
abstract final class CleCache {
  /// La première page d'une liste de sujets, **pour un filtre donné**.
  ///
  /// POURQUOI le filtre fait partie de la clé : sans lui, la page « Santé »
  /// écraserait la page « Tous » et l'écran servirait, hors ligne, une liste qui
  /// ne correspond pas à la pastille sélectionnée.
  static String pageSujets({String? categorie}) =>
      'forum:threads:${categorie ?? ''}';

  static String sujet(String slug) => 'forum:thread:$slug';

  static String pageArticles({String? categorie}) =>
      'hub:articles:${categorie ?? ''}';

  static String article(String slug) => 'hub:article:$slug';

  static String pageOpportunites({String? portee, String? type}) =>
      'hub:opportunities:${portee ?? ''}:${type ?? ''}';

  static String opportunite(String slug) => 'hub:opportunity:$slug';
}

/// L'implémentation Drift.
class DriftCommunityCacheDatasource implements CommunityCacheDatasource {
  const DriftCommunityCacheDatasource(this._db, {DateTime Function()? horloge})
    : _horloge = horloge ?? DateTime.now;

  final AppDatabase _db;
  final DateTime Function() _horloge;

  @override
  Future<EntreeCache?> lire(String cle) async {
    final CommunityCacheRow? row = await (_db.select(
      _db.communityCache,
    )..where((CommunityCache t) => t.cle.equals(cle))).getSingleOrNull();
    if (row == null) {
      return null;
    }
    final Object? decode = jsonDecode(row.chargeUtile);
    if (decode is! Map) {
      // Une charge utile illisible n'est pas une donnee : on prefere rendre
      // `null` et relire le reseau plutot que de faire tomber un ecran sur un
      // cache corrompu.
      return null;
    }
    return EntreeCache(
      contenu: decode.map(
        (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
      ),
      recupereLe: row.recupereLe,
    );
  }

  @override
  Future<void> ecrire(String cle, Map<String, Object?> contenu) async {
    await _db
        .into(_db.communityCache)
        .insertOnConflictUpdate(
          CommunityCacheRow(
            cle: cle,
            chargeUtile: jsonEncode(contenu),
            recupereLe: _horloge(),
          ),
        );
  }

  @override
  Future<void> oublier(String cle) async {
    await (_db.delete(
      _db.communityCache,
    )..where((CommunityCache t) => t.cle.equals(cle))).go();
  }
}
