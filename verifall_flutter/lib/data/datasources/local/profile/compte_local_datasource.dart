import 'dart:convert';

import '../database.dart';

/// Les ressources du compte que l'on garde en local.
///
/// POURQUOI une énumération fermée : la clé de cache est écrite dans une table,
/// et une faute de frappe sur une chaîne libre produirait un cache qui ne se
/// relit jamais — c'est-à-dire un écran qui semble n'avoir jamais rien gardé,
/// sans erreur pour le dire.
///
/// **Le portefeuille n'en fait pas partie, et c'est le point.** Le solde et la
/// grille tarifaire ne se cachent jamais (contrat §7.1) : il n'y a donc pas de
/// valeur `portefeuille` dans cette liste, et aucun code ne peut en demander
/// une.
enum RessourceDeCompte {
  profil('profil'),
  recompenses('recompenses'),
  certificats('certificats'),
  statistiques('statistiques'),
  parrainage('parrainage'),
  reglages('reglages');

  const RessourceDeCompte(this.cle);

  final String cle;
}

/// Ce qu'on a relu du cache : la charge utile **et sa date**.
///
/// POURQUOI la date accompagne toujours la donnée : « une échéance J-2 vieille
/// de trois jours, une statistique périmée, une liste sans les dernières
/// entrées : chacune devient un mensonge sans sa date » (05-HORS-LIGNE §5.4).
/// Rendre la charge utile seule rendrait le bandeau d'horodatage impossible à
/// écrire — donc jamais écrit.
class EntreeDeCache {
  const EntreeDeCache({required this.contenu, required this.recupereLe});

  final Map<String, Object?> contenu;
  final DateTime recupereLe;
}

/// Le cache de lecture du parcours Compte.
abstract interface class CompteLocalDataSource {
  Future<EntreeDeCache?> lire(RessourceDeCompte ressource);

  Future<List<Map<String, Object?>>?> lireListe(RessourceDeCompte ressource);

  Future<void> ecrire(
    RessourceDeCompte ressource,
    Map<String, Object?> contenu,
  );

  Future<void> ecrireListe(
    RessourceDeCompte ressource,
    List<Map<String, Object?>> contenu,
  );
}

/// L'implémentation Drift.
///
/// Elle sérialise en JSON plutôt qu'en colonnes typées, et c'est un choix : les
/// ressources cachées ici sont des agrégats de lecture, jamais interrogés par
/// un filtre. Les mettre en colonnes obligerait à une migration du socle à
/// chaque champ ajouté par l'API — alors que le contrat autorise justement les
/// **ajouts de champ sans préavis** (§6.2).
class DriftCompteLocalDataSource implements CompteLocalDataSource {
  const DriftCompteLocalDataSource(this._db, {DateTime Function()? horloge})
    : _horloge = horloge ?? DateTime.now;

  final AppDatabase _db;
  final DateTime Function() _horloge;

  @override
  Future<EntreeDeCache?> lire(RessourceDeCompte ressource) async {
    final CompteCacheRow? ligne = await _ligne(ressource);
    if (ligne == null) {
      return null;
    }
    final Object? decode = _decoder(ligne.chargeUtile);
    if (decode is! Map) {
      return null;
    }
    return EntreeDeCache(
      contenu: decode.map(
        (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
      ),
      recupereLe: ligne.recupereLe,
    );
  }

  @override
  Future<List<Map<String, Object?>>?> lireListe(
    RessourceDeCompte ressource,
  ) async {
    final CompteCacheRow? ligne = await _ligne(ressource);
    if (ligne == null) {
      return null;
    }
    final Object? decode = _decoder(ligne.chargeUtile);
    if (decode is! List) {
      return null;
    }
    return <Map<String, Object?>>[
      for (final Object? item in decode)
        if (item is Map)
          item.map(
            (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
          ),
    ];
  }

  @override
  Future<void> ecrire(
    RessourceDeCompte ressource,
    Map<String, Object?> contenu,
  ) => _ecrire(ressource, contenu);

  @override
  Future<void> ecrireListe(
    RessourceDeCompte ressource,
    List<Map<String, Object?>> contenu,
  ) => _ecrire(ressource, contenu);

  Future<void> _ecrire(RessourceDeCompte ressource, Object contenu) async {
    await _db
        .into(_db.compteCache)
        .insertOnConflictUpdate(
          CompteCacheCompanion.insert(
            ressource: ressource.cle,
            chargeUtile: jsonEncode(contenu),
            recupereLe: _horloge(),
          ),
        );
  }

  Future<CompteCacheRow?> _ligne(RessourceDeCompte ressource) {
    return (_db.select(_db.compteCache)
          ..where((CompteCache table) => table.ressource.equals(ressource.cle)))
        .getSingleOrNull();
  }

  /// Un cache illisible **n'est pas une erreur d'écran**.
  ///
  /// POURQUOI on avale l'exception : le seul cas où elle survient est un
  /// contenu écrit par une version antérieure du client. Faire échouer l'écran
  /// à cause d'une ligne de cache reviendrait à rendre l'application
  /// inutilisable après une mise à jour, alors que la donnée est à un aller-
  /// retour réseau.
  Object? _decoder(String brut) {
    try {
      return jsonDecode(brut);
    } on FormatException {
      return null;
    }
  }
}
