import 'package:flutter/material.dart';

import '../../core/format/vf_formats.dart';
import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';

/// Le ton du bandeau d'horodatage.
///
/// # Pourquoi deux tons, et pas un
///
/// M3 et M4 avaient écrit chacun leur bandeau, et ils ne se ressemblaient pas :
/// l'un discret (filet gris, icône « nuage barré »), l'autre appuyé (fond ambré,
/// icône d'historique). Garder « le plus complet » aurait effacé une raison.
///
/// La raison de M4 est écrite noir sur blanc : « sur le Hub, le cache vit quinze
/// minutes parce que le contenu porte une **échéance**, et le client n'a pas le
/// droit de recalculer cette échéance. La date d'extraction est la seule chose
/// qui rende le compte à rebours affiché honnête. » Un bandeau discret y aurait
/// été insuffisant ; un bandeau ambré sur un catalogue de cours aurait alarmé
/// pour rien.
///
/// Le ton suit donc **ce que la donnée périmée risque de faire croire**, pas une
/// préférence d'écran.
enum VfStaleTone {
  /// La donnée vieillit sans mentir : un catalogue, une liste de certificats.
  neutre,

  /// La donnée porte une **échéance** ou un compte à rebours que le client ne
  /// recalcule pas : une opportunité, une séance, un délai.
  echeance,
}

/// Le bandeau « Données du `<date>`, actualisation impossible ».
///
/// # Pourquoi il appartient au socle
///
/// Il est exigé de **tous** les écrans qui lisent un cache (05-HORS-LIGNE §5.4
/// et §5.5), donc des cinq parcours. Trois d'entre eux en avaient écrit une
/// version ; elles sont fusionnées ici (arbitrage n° 129).
///
/// # Pourquoi il n'est pas décoratif
///
/// > « Une échéance d'opportunité "J-2" vieille de trois jours, une statistique
/// > périmée, une liste de vérifications sans les dernières : chacune devient un
/// > mensonge sans sa date. »
class VfStaleDataBanner extends StatelessWidget {
  const VfStaleDataBanner({
    required this.lueLe,
    this.tone = VfStaleTone.neutre,
    this.onRetry,
    super.key,
  });

  /// Quand les données affichées ont été récupérées.
  final DateTime lueLe;

  final VfStaleTone tone;

  /// La reprise, quand l'écran en offre une.
  ///
  /// POURQUOI elle est facultative et non systématique : M4 l'avait — « un
  /// bandeau qui constate sans offrir de sortie est une impasse » — et M3 ne
  /// l'avait pas, parce que ses écrans se rafraîchissent en tirant vers le bas.
  /// Deux boutons « Réessayer » pour le même geste seraient une invitation à se
  /// demander lequel marche.
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final bool appuye = tone == VfStaleTone.echeance;
    final Color teinte = appuye ? VfColors.verdictInvestigate : VfColors.slate2;

    return Semantics(
      liveRegion: true,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(
          horizontal: VfSpacing.x12,
          vertical: VfSpacing.x8,
        ),
        decoration: BoxDecoration(
          color: appuye
              ? VfColors.verdictInvestigate.withValues(alpha: 0.10)
              : VfColors.canvas,
          borderRadius: VfRadii.mdAll,
          border: appuye ? null : Border.all(color: VfColors.line),
        ),
        child: Row(
          children: <Widget>[
            Icon(
              appuye ? Icons.history_rounded : Icons.cloud_off_rounded,
              size: appuye ? 18 : 16,
              color: teinte,
            ),
            const SizedBox(width: VfSpacing.x8),
            Expanded(
              child: Text(
                VfFormats.bandeauDeFraicheur(lueLe),
                style: appuye
                    ? VfTextStyles.tXs.copyWith(color: teinte)
                    : VfTextStyles.tXs,
              ),
            ),
            if (onRetry != null)
              TextButton(
                onPressed: onRetry,
                child: Text(
                  'Réessayer',
                  style: VfTextStyles.tXs.copyWith(
                    color: appuye ? teinte : VfColors.primary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
