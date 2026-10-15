import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../shared/vf_verdict_badge.dart';
import 'verdict_tone.dart';

/// La tuile d'une vérification — `verifRow()` du simulateur.
///
/// # Où elle sert
///
/// Sur l'accueil (« Vérifications récentes ») **et** sur « Mes vérifications ».
/// Les deux écrans appartiennent à M2 ; la tuile vit dans `verify/` parce que
/// c'est là qu'est sa liste principale, et l'accueil l'importe. C'est la seule
/// dépendance entre les deux dossiers de ce parcours, et elle est consignée —
/// **sa place naturelle est le socle**, comme les autres `Vf*`.
///
/// # Ce qu'elle affiche quand il n'y a pas encore de verdict
///
/// **Son statut, pas un tiret.** « Les analyses en cours apparaissent, avec leur
/// état à la place du verdict — un tiret laisserait croire à un échec »
/// (arbitrage n° 31). Et le libellé vient du serveur : le client n'en fabrique
/// aucun.
class VerificationTile extends StatelessWidget {
  const VerificationTile({
    required this.verification,
    required this.onTap,
    super.key,
  });

  final Verification verification;

  /// Mène au rapport s'il existe, au suivi sinon. La décision se prend à
  /// l'appelant, qui connaît les routes.
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final VfVerdictTone tone = verification.aUnRapport
        ? TonDuVerdict.pour(verification.rapport!.verdict)
        : TonDuVerdict.pourLeStatut(verification.statut);

    final String? libelleBadge =
        verification.rapport?.verdict?.libelle ?? verification.statutLibelle;

    return Semantics(
      button: true,
      label: _resume(),
      excludeSemantics: true,
      child: Material(
        color: VfColors.surface,
        borderRadius: VfRadii.lgAll,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Container(
            decoration: BoxDecoration(
              borderRadius: VfRadii.lgAll,
              border: Border.all(color: VfColors.line),
            ),
            padding: const EdgeInsets.all(VfSpacing.x12 + 1),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                // La pastille ronde teintee de la couleur du verdict, avec son
                // icone : « les etats ne reposent jamais sur la seule couleur ».
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: tone.color.withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(tone.icon, size: 17, color: tone.color),
                ),
                const SizedBox(width: VfSpacing.x10 + 1),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        verification.apercu,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: VfTextStyles.tSm.copyWith(
                          color: VfColors.ink,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: VfSpacing.x4 - 1),
                      Text(_ligneSecondaire(), style: VfTextStyles.tXs),
                    ],
                  ),
                ),
                if (libelleBadge != null) ...<Widget>[
                  const SizedBox(width: VfSpacing.x8),
                  VfVerdictBadge(
                    libelle: libelleBadge,
                    tone: tone,
                    compact: true,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  /// `<type>` · `<date>` · `<n> % de confiance`.
  ///
  /// Le pourcentage n'apparaît **que** s'il existe : sur une analyse en cours,
  /// écrire « 0 % de confiance » serait un verdict déguisé.
  String _ligneSecondaire() {
    final List<String> morceaux = <String>[
      if (verification.type.libelle.isNotEmpty) verification.type.libelle,
      if (verification.soumiseLe != null) _date(verification.soumiseLe!),
      if (verification.rapport?.confiance != null)
        '${verification.rapport!.confiance} % de confiance',
    ];
    return morceaux.join(' · ');
  }

  String _resume() {
    final String etat =
        verification.rapport?.verdict?.libelle ??
        verification.statutLibelle ??
        'état inconnu';
    return '${verification.apercu}. $etat. ${_ligneSecondaire()}';
  }

  static String _date(DateTime quand) {
    final DateTime local = quand.toLocal();
    String d(int v) => v < 10 ? '0$v' : '$v';
    return '${d(local.day)}/${d(local.month)}/${local.year}';
  }
}
