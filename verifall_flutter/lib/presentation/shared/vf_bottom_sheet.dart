import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_theme.dart';
import '../../core/theme/vf_typography.dart';

/// `.sheet` — la feuille inférieure.
///
/// Poignée de 40 x 4, hauteur maximale 82 %, défilement interne. Elle sert aux
/// filtres, au partage, au choix de média, au paiement et aux confirmations.
///
/// # Deux exigences d'accessibilité qui ne sont pas des détails
///
/// - **La feuille capte le focus et le rend à son ouvrant en se fermant**
///   (§2.9). `showModalBottomSheet` le fait pour nous, à condition de ne pas
///   ré-implémenter la fermeture à la main.
/// - **La barre d'état repasse en clair à la fermeture.** Une feuille assombrit
///   l'arrière-plan, donc la barre d'état passe en blanc ; l'oublier laisse des
///   icônes blanches sur fond blanc (§2.5). C'est le rôle de
///   `AnnotatedRegion` posé sur le contenu **et** du retour au thème ensuite.
abstract final class VfBottomSheet {
  /// Ouvre une feuille et rend ce qu'elle a renvoyé.
  static Future<T?> show<T>({
    required BuildContext context,
    required WidgetBuilder builder,
    String? title,
    bool dismissible = true,
  }) {
    return showModalBottomSheet<T>(
      context: context,
      isScrollControlled: true,
      isDismissible: dismissible,
      enableDrag: dismissible,
      backgroundColor: VfColors.surface,
      barrierColor: VfColors.ink.withValues(alpha: 0.45),
      shape: const RoundedRectangleBorder(borderRadius: VfRadii.sheet),
      constraints: BoxConstraints(
        maxHeight:
            MediaQuery.sizeOf(context).height * VfChrome.sheetMaxHeightFactor,
      ),
      builder: (BuildContext sheetContext) => AnnotatedRegion(
        // Le voile est sombre : la barre d'etat passe en blanc tant que la
        // feuille est ouverte.
        value: VfTheme.statusBarOnDark,
        child: SafeArea(
          top: false,
          child: Padding(
            // Sans ce decalage, un clavier ouvert recouvre le champ de saisie
            // d'une feuille de filtre.
            padding: EdgeInsets.only(
              bottom: MediaQuery.viewInsetsOf(sheetContext).bottom,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                const _SheetHandle(),
                if (title != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(
                      VfSpacing.x16,
                      VfSpacing.x4,
                      VfSpacing.x16,
                      VfSpacing.x12,
                    ),
                    child: Row(
                      children: <Widget>[
                        Expanded(child: Text(title, style: VfTextStyles.hMd)),
                      ],
                    ),
                  ),
                Flexible(child: builder(sheetContext)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _SheetHandle extends StatelessWidget {
  const _SheetHandle();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: VfSpacing.x10),
      child: ExcludeSemantics(
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: VfColors.line,
            borderRadius: VfRadii.pillAll,
          ),
          child: SizedBox(
            width: VfChrome.sheetHandleWidth,
            height: VfChrome.sheetHandleHeight,
          ),
        ),
      ),
    );
  }
}
