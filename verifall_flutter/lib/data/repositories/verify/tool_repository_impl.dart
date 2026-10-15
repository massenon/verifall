import '../../../core/errors/failure.dart';
import '../../../domain/entities/tool_outil.dart';
import '../../../domain/repositories/tool_repository.dart';
import '../../datasources/local/verify/verification_local_source.dart';
import '../../datasources/remote/verify/verification_remote_source.dart';

/// Le dépôt de l'annuaire d'outils.
///
/// Cache 24 h, **fixé par le contrat** (§7.1). Cycle de lecture identique à
/// partout ailleurs : cache d'abord, réseau ensuite, et si le réseau échoue on
/// garde l'affichage avec sa date.
class ToolRepositoryImpl implements ToolRepository {
  const ToolRepositoryImpl({required this.distant, required this.local});

  final VerificationRemoteSource distant;
  final VerificationLocalSource local;

  @override
  Future<AnnuaireOutils> annuaire() async {
    final ({AnnuaireOutils? annuaire, DateTime? luLe}) cache = await local
        .lireLesOutils();

    final DateTime? luLe = cache.luLe;
    final bool frais =
        luLe != null &&
        DateTime.now().toUtc().difference(luLe) <
            VerificationLocalSource.dureeDeVieDesOutils;

    final AnnuaireOutils? enCache = cache.annuaire;
    if (frais && enCache != null) {
      return enCache;
    }

    try {
      final AnnuaireOutils frais = await distant.outils();
      await local.ecrireLesOutils(frais);
      return frais;
    } on Failure {
      // Le cache perime vaut mieux qu'un ecran vide : l'ecran pose alors son
      // bandeau d'horodatage plutôt que de faire disparaitre l'annuaire.
      if (enCache != null) {
        return enCache;
      }
      rethrow;
    }
  }

  @override
  Future<({AnnuaireOutils? annuaire, DateTime? luLe})> annuaireEnCache() =>
      local.lireLesOutils();
}
