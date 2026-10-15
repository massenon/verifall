import 'dart:convert';

import 'package:drift/drift.dart';

import '../../../../domain/entities/tool_outil.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../models/json_reader.dart';
import '../database.dart';

/// Le cache Drift du parcours « Vérifier ».
///
/// # Les deux règles qui gouvernent ce fichier
///
/// 1. **L'état d'une analyse en cours ne se met jamais en cache** (§7.1). Le
///    filtre est appliqué à l'écriture, dans [ecrireLaListe] et [ecrireUne] :
///    une vérification non terminée n'entre pas. Le faire à la lecture
///    laisserait une porte ouverte, et un jour quelqu'un l'emprunterait.
/// 2. **`fetched_at` n'est pas décoratif** : il alimente le bandeau
///    « Données du `<date>`, actualisation impossible ». Une liste sans les
///    dernières entrées devient un mensonge sans sa date (05-HORS-LIGNE §5.4).
class VerificationLocalSource {
  const VerificationLocalSource(this._db);

  final AppDatabase _db;

  static const int _ligneUnique = 0;

  /// Remplace la première page en cache.
  ///
  /// POURQUOI seulement la première page : le cache sert la **lecture hors
  /// ligne**, pas la pagination hors ligne. Accumuler toutes les pages ferait
  /// grossir la base sans fin sur un téléphone d'entrée de gamme, et la règle du
  /// contrat ne promet que « lecture seule hors ligne ».
  Future<void> ecrireLaListe(List<Verification> verifications) async {
    final DateTime maintenant = DateTime.now().toUtc();
    await _db.transaction(() async {
      await _db.delete(_db.verificationCache).go();
      for (int i = 0; i < verifications.length; i++) {
        final Verification v = verifications[i];
        if (!v.terminee) {
          // Une analyse EN COURS ne va pas au cache : la relire depuis Drift
          // ferait croire a une analyse bloquee (contrat 7.1).
          continue;
        }
        await _db
            .into(_db.verificationCache)
            .insertOnConflictUpdate(_ligne(v, i, maintenant));
      }
    });
  }

  /// Met à jour **une** vérification, à la lecture d'un rapport.
  ///
  /// « Le rapport est mis en cache : c'est le document que l'on rouvre pour le
  /// montrer à quelqu'un, souvent sans réseau. »
  Future<void> ecrireUne(Verification verification) async {
    if (!verification.terminee) {
      return;
    }
    final VerificationCacheData? existante = await _trouver(verification.uuid);
    await _db
        .into(_db.verificationCache)
        .insertOnConflictUpdate(
          _ligne(
            verification,
            // On conserve le rang deja connu : le retrier localement donnerait
            // un ordre different de celui de la pagination par curseur.
            existante?.rang ?? -1,
            DateTime.now().toUtc(),
          ),
        );
  }

  Future<List<Verification>> lireLaListe() async {
    final List<VerificationCacheData> lignes =
        await (_db.select(_db.verificationCache)
              ..orderBy(<OrderClauseGenerator<VerificationCache>>[
                (VerificationCache t) => OrderingTerm.asc(t.rang),
              ]))
            .get();
    return <Verification>[
      for (final VerificationCacheData ligne in lignes)
        if (_decoder(ligne.chargeJson) case final Verification v) v,
    ];
  }

  Future<Verification?> lireUne(String uuid) async {
    final VerificationCacheData? ligne = await _trouver(uuid);
    return ligne == null ? null : _decoder(ligne.chargeJson);
  }

  /// La date de lecture la plus récente, pour le bandeau d'horodatage.
  Future<DateTime?> dateDeLaListe() async {
    final List<VerificationCacheData> lignes = await (_db.select(
      _db.verificationCache,
    )..limit(1)).get();
    return lignes.isEmpty ? null : lignes.first.fetchedAt;
  }

  // --- Annuaire d'outils ---------------------------------------------------

  /// La durée de vie du cache d'outils, **fixée par le contrat** (§7.1).
  ///
  /// C'est l'un des rares endroits où un cache long est légitime : un
  /// répertoire d'outils ne change pas dans la journée.
  static const Duration dureeDeVieDesOutils = Duration(hours: 24);

  Future<void> ecrireLesOutils(AnnuaireOutils annuaire) async {
    await _db
        .into(_db.annuaireOutilsCache)
        .insertOnConflictUpdate(
          AnnuaireOutilsCacheData(
            id: _ligneUnique,
            chargeJson: jsonEncode(<String, Object?>{
              'outils': <Map<String, Object?>>[
                for (final Outil outil in annuaire.outils) outil.toJson(),
              ],
              'categories': annuaire.categories,
            }),
            fetchedAt: DateTime.now().toUtc(),
          ),
        );
  }

  Future<({AnnuaireOutils? annuaire, DateTime? luLe})> lireLesOutils() async {
    final AnnuaireOutilsCacheData? ligne =
        await (_db.select(_db.annuaireOutilsCache)
              ..where((AnnuaireOutilsCache t) => t.id.equals(_ligneUnique)))
            .getSingleOrNull();
    if (ligne == null) {
      return (annuaire: null, luLe: null);
    }
    final Object? decode = jsonDecode(ligne.chargeJson);
    if (decode is! Map) {
      return (annuaire: null, luLe: null);
    }
    final JsonReader reader = JsonReader(asJsonObject(decode));
    return (
      annuaire: AnnuaireOutils(
        outils: <Outil>[
          for (final Map<String, Object?> o in reader.objectListOrEmpty(
            'outils',
          ))
            Outil.fromJson(o),
        ],
        categories: <String>[
          for (final Object? c in reader.listOrEmpty('categories'))
            if (c != null) '$c',
        ],
      ),
      luLe: ligne.fetchedAt,
    );
  }

  // --- Interne -------------------------------------------------------------

  Future<VerificationCacheData?> _trouver(String uuid) => (_db.select(
    _db.verificationCache,
  )..where((VerificationCache t) => t.uuid.equals(uuid))).getSingleOrNull();

  VerificationCacheData _ligne(Verification v, int rang, DateTime quand) =>
      VerificationCacheData(
        uuid: v.uuid,
        chargeJson: jsonEncode(v.toJson()),
        terminee: v.terminee,
        rang: rang,
        soumiseLe: v.soumiseLe,
        fetchedAt: quand,
      );

  Verification? _decoder(String charge) {
    final Object? decode = jsonDecode(charge);
    if (decode is! Map) {
      return null;
    }
    return Verification.fromJson(asJsonObject(decode));
  }
}
