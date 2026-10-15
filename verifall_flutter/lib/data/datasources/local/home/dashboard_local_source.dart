import 'dart:convert';

import '../../../../domain/entities/home_tableau_de_bord.dart';
import '../../../models/json_reader.dart';
import '../database.dart';

/// Le cache Drift de l'écran d'accueil.
///
/// # La règle qui gouverne ce fichier
///
/// > « Ce qui NE doit jamais être servi depuis le cache : **le solde d'unités**,
/// > l'état d'une analyse en cours, le résultat d'un quiz. Ce sont les trois
/// > écrans qu'on relit *précisément* parce qu'on attend un changement ; y
/// > montrer une valeur d'il y a cinq minutes fait croire à un paiement
/// > perdu. » (§7.1)
///
/// L'écriture passe donc systématiquement par [TableauDeBord.pourLeCache], qui
/// retire le solde. Ce n'est pas une précaution d'écran : c'est appliqué **au
/// point d'écriture**, là où on ne peut pas l'oublier.
class DashboardLocalSource {
  const DashboardLocalSource(this._db);

  final AppDatabase _db;

  /// Une seule ligne, toujours la même : l'agrégat arrive d'un bloc.
  static const int _ligneUnique = 0;

  Future<void> ecrire(TableauDeBord tableau) async {
    final TableauDeBord sansSolde = tableau.pourLeCache();
    await _db
        .into(_db.tableauDeBordCache)
        .insertOnConflictUpdate(
          TableauDeBordCacheData(
            id: _ligneUnique,
            chargeJson: jsonEncode(_encoder(sansSolde)),
            fetchedAt: sansSolde.luLe ?? DateTime.now().toUtc(),
          ),
        );
  }

  Future<TableauDeBord?> lire() async {
    final TableauDeBordCacheData? ligne =
        await (_db.select(_db.tableauDeBordCache)
              ..where((TableauDeBordCache t) => t.id.equals(_ligneUnique)))
            .getSingleOrNull();
    if (ligne == null) {
      return null;
    }
    final Object? decode = jsonDecode(ligne.chargeJson);
    if (decode is! Map) {
      return null;
    }
    return TableauDeBord.fromJson(asJsonObject(decode), luLe: ligne.fetchedAt);
  }

  /// Écrit la reprise de cours, **y compris quand il n'y a rien à reprendre**.
  ///
  /// POURQUOI on écrit aussi l'absence : hors ligne, ne rien avoir écrit et
  /// « avoir lu qu'il n'y a rien » mènent à deux cartes différentes. Sans ce
  /// booléen, un compte neuf verrait un squelette permanent.
  Future<void> ecrireLaReprise(RepriseDeCours? reprise) async {
    await _db
        .into(_db.repriseDeCoursCache)
        .insertOnConflictUpdate(
          RepriseDeCoursCacheData(
            id: _ligneUnique,
            presente: reprise != null,
            chargeJson: reprise == null
                ? null
                : jsonEncode(<String, Object?>{
                    'cours': <String, Object?>{
                      'slug': reprise.coursSlug,
                      'titre': reprise.coursTitre,
                    },
                    'inscription': <String, Object?>{
                      'avancement': reprise.avancement,
                    },
                    if (reprise.prochaineLeconTitre != null)
                      'prochaine_lecon': <String, Object?>{
                        'titre': reprise.prochaineLeconTitre,
                      },
                  }),
            fetchedAt: DateTime.now().toUtc(),
          ),
        );
  }

  Future<({bool lue, RepriseDeCours? reprise})> lireLaReprise() async {
    final RepriseDeCoursCacheData? ligne =
        await (_db.select(_db.repriseDeCoursCache)
              ..where((RepriseDeCoursCache t) => t.id.equals(_ligneUnique)))
            .getSingleOrNull();
    if (ligne == null) {
      return (lue: false, reprise: null);
    }
    final String? charge = ligne.chargeJson;
    if (!ligne.presente || charge == null) {
      return (lue: true, reprise: null);
    }
    final Object? decode = jsonDecode(charge);
    if (decode is! Map) {
      return (lue: true, reprise: null);
    }
    return (lue: true, reprise: RepriseDeCours.fromJson(asJsonObject(decode)));
  }

  /// La forme sérialisée. Elle reprend **exactement** les clés de l'API : le
  /// cache se relit avec le même décodeur que le réseau, sinon on entretient
  /// deux formats et l'un des deux finit par diverger.
  Map<String, Object?> _encoder(TableauDeBord t) => <String, Object?>{
    'salutation': t.salutation,
    'compteurs': <String, Object?>{
      'verifications': t.compteurs.verifications,
      'cours_suivis': t.compteurs.coursSuivis,
      'badges': t.compteurs.badges,
      // `unites` est volontairement ABSENT : le solde ne se cache jamais.
    },
    'niveau': <String, Object?>{
      'rang': t.niveau.rang,
      'nom': t.niveau.nom,
      'pourcentage': t.niveau.pourcentage,
    },
    'serie': <String, Object?>{
      'jours': t.serie.jours,
      'vivante': t.serie.vivante,
    },
    'activite': <String, Object?>{
      'jours': t.activite.jours,
      'valeurs': t.activite.valeurs,
    },
    'cours_offert': t.coursOffert == null
        ? null
        : <String, Object?>{
            'slug': t.coursOffert!.slug,
            'titre': t.coursOffert!.titre,
            'categorie': t.coursOffert!.categorie,
            'duree': t.coursOffert!.duree,
          },
  };
}
