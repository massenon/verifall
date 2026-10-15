import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';

/// Les deux variantes de toast (§2.7).
enum VfToastVariant {
  /// Fond encre.
  neutre(VfColors.ink, Icons.check_circle_rounded),

  /// Fond rouge.
  erreur(VfColors.brandRed, Icons.error_rounded);

  const VfToastVariant(this.background, this.icon);

  final Color background;
  final IconData icon;
}

/// `.toast` — fond encre, ou rouge en variante erreur.
///
/// # Trois contraintes qui viennent du design system
///
/// - **Ancré au-dessus de la barre basse** (`nav + 14`). Un toast qui recouvre
///   l'onglet actif masque la sortie de l'écran.
/// - **Trois au maximum, trois secondes.**
/// - **Rôle `status`** pour que le lecteur d'écran l'annonce sans voler le
///   focus.
///
/// POURQUOI un `SnackBar` thémé plutôt qu'une superposition maison : le
/// `ScaffoldMessenger` gère déjà la file d'attente, l'empilement et le retrait à
/// la navigation — trois choses qu'une superposition maison réimplémente mal.
abstract final class VfToast {
  static void show(
    BuildContext context, {
    required String message,
    VfToastVariant variant = VfToastVariant.neutre,
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);

    // Trois au maximum : au-dela, on retire le plus ancien plutot que de faire
    // patienter un message derriere une file invisible.
    messenger.removeCurrentSnackBar();

    messenger.showSnackBar(
      SnackBar(
        duration: VfChrome.toastDuration,
        backgroundColor: variant.background,
        behavior: SnackBarBehavior.floating,
        elevation: 0,
        // L'ancrage au-dessus de la barre basse.
        margin: const EdgeInsets.fromLTRB(
          VfSpacing.x16,
          0,
          VfSpacing.x16,
          VfChrome.toastLift,
        ),
        shape: const RoundedRectangleBorder(borderRadius: VfRadii.mdAll),
        content: Semantics(
          liveRegion: true,
          child: Row(
            children: <Widget>[
              Icon(variant.icon, size: 18, color: VfColors.surface),
              const SizedBox(width: VfSpacing.x10),
              Expanded(
                child: Text(
                  message,
                  style: VfTextStyles.tMd.copyWith(color: VfColors.surface),
                ),
              ),
            ],
          ),
        ),
        action: actionLabel == null || onAction == null
            ? null
            : SnackBarAction(
                label: actionLabel,
                textColor: VfColors.surface,
                onPressed: onAction,
              ),
      ),
    );
  }

  /// Raccourci pour un échec.
  ///
  /// POURQUOI [message] et pas un `Failure` : le message affiché vient du
  /// serveur quand il y en a un — « déjà rédigé en français et affichable tel
  /// quel » (§2.1 du contrat) — et de l'écran sinon. Le composant ne choisit
  /// pas ; il montre.
  static void error(BuildContext context, String message) =>
      show(context, message: message, variant: VfToastVariant.erreur);
}
