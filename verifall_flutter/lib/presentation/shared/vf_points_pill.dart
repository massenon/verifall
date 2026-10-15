import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';

/// `.pointspill` — la pastille de points (§2.7, paragraphe dédié).
///
/// C'est le composant le plus vu de l'application : hauteur 38, pastille blanche
/// cerclée, éclair primaire rempli, nombre en **chiffres tabulaires**.
///
/// POURQUOI les chiffres tabulaires : un solde qui passe de 9 à 10 changerait la
/// largeur de la pastille et ferait sauter toute la barre supérieure.
///
/// # Où elle apparaît, et où elle n'apparaît pas
///
/// Elle apparaît sur : tableau de bord, vérifier, mes vérifications, catalogue,
/// hub, profil. Elle **n'apparaît pas** sur les écrans de lecture — rapport,
/// leçon, article, certificat : « montrer un solde pendant une lecture n'apporte
/// rien et ajoute une cible tactile parasite ».
///
/// # Ce qu'elle affiche
///
/// Le solde vient du serveur et **ne se lit jamais depuis le cache** (contrat
/// §7.1). [solde] à `null` signifie « pas encore lu » et affiche un tiret : mieux
/// vaut un tiret honnête qu'un zéro qui ferait croire à un compte vide.
class VfPointsPill extends StatelessWidget {
  const VfPointsPill({
    required this.solde,
    required this.onTap,
    this.onForest = false,
    super.key,
  });

  final int? solde;

  /// Mène à « Mes unités ». La pastille est **cliquable** : c'est le raccourci
  /// le plus court vers la recharge.
  final VoidCallback? onTap;

  /// Variante `on-forest` : pastille translucide sur fond vert profond.
  final bool onForest;

  @override
  Widget build(BuildContext context) {
    final String value = solde?.toString() ?? '—';
    return Semantics(
      button: onTap != null,
      // Le libelle annonce le solde EN TOUTES LETTRES, pas seulement le chiffre :
      // « 12 » seul, lu par une synthese vocale, ne veut rien dire.
      label: solde == null
          ? 'Solde d unités non disponible. Ouvrir mes unités.'
          : 'Solde : $value unités. Ouvrir mes unités.',
      excludeSemantics: true,
      child: Material(
        color: Colors.transparent,
        borderRadius: VfRadii.pillAll,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Container(
            height: VfChrome.pointsPillHeight,
            padding: const EdgeInsets.symmetric(horizontal: VfSpacing.x12),
            decoration: BoxDecoration(
              color: onForest
                  ? VfColors.surface.withValues(alpha: 0.14)
                  : VfColors.surface,
              borderRadius: VfRadii.pillAll,
              border: Border.all(
                color: onForest
                    ? VfColors.surface.withValues(alpha: 0.24)
                    : VfColors.line,
              ),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                // L'eclair est REMPLI (axe FILL de Material Symbols, §2.8) :
                // c'est l'une des deux seules icones remplies de l'application,
                // avec l'onglet actif.
                const Icon(Icons.bolt, size: 18, color: VfColors.primary),
                const SizedBox(width: VfSpacing.x6),
                Text(
                  value,
                  style: VfTextStyles.mono.copyWith(
                    fontWeight: FontWeight.w600,
                    color: onForest ? VfColors.surface : VfColors.ink,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
