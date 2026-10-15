import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';

/// L'encart « cet écran a besoin du réseau », avec la raison.
///
/// « Ce qui ne fonctionne pas hors ligne, il faut le dire » (§5.10). Le message
/// n'est jamais générique : il dit **pourquoi**, parce que « le résultat d'un
/// quiz ne vient jamais du cache » et « une page suivante est une position dans
/// l'ordre du serveur » ne se règlent pas de la même façon.
class EncartReseauRequis extends StatelessWidget {
  const EncartReseauRequis({
    required this.titre,
    required this.raison,
    this.action,
    this.icone = Icons.wifi_off_rounded,
    super.key,
  });

  final String titre;
  final String raison;
  final Widget? action;

  /// L'icône par défaut dit « réseau ». Un refus du serveur — solde
  /// insuffisant, action impossible — n'est pas un problème de réseau, et
  /// l'icône doit le dire.
  final IconData icone;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(VfSpacing.x16),
      decoration: BoxDecoration(
        color: VfColors.surface,
        borderRadius: VfRadii.lgAll,
        border: Border.all(color: VfColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(icone, size: 18, color: VfColors.slate2),
              const SizedBox(width: VfSpacing.x8),
              Expanded(child: Text(titre, style: VfTextStyles.hSm)),
            ],
          ),
          const SizedBox(height: VfSpacing.x6),
          Text(raison, style: VfTextStyles.tSm),
          if (action != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x12),
            action!,
          ],
        ],
      ),
    );
  }
}

/// L'encart pédagogique vert clair — « À retenir » de la leçon, et la même
/// forme pour l'explication d'une question sur l'écran de résultat.
class EncartPedagogique extends StatelessWidget {
  const EncartPedagogique({
    required this.icone,
    required this.texte,
    this.titre,
    super.key,
  });

  final IconData icone;
  final String? titre;
  final String texte;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(VfSpacing.x12),
      decoration: BoxDecoration(
        color: VfColors.primarySoft,
        borderRadius: VfRadii.lgAll,
        border: Border.all(color: VfColors.primary.withValues(alpha: 0.25)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icone, size: 19, color: VfColors.primaryDark),
          const SizedBox(width: VfSpacing.x9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                if (titre != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 3),
                    child: Text(
                      titre!,
                      style: VfTextStyles.tXs.copyWith(
                        color: VfColors.primaryDark,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                Text(
                  texte,
                  style: VfTextStyles.tXs.copyWith(color: VfColors.primaryDark),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
