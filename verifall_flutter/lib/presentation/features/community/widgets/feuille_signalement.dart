import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../data/models/api_enums.dart';
import '../../../../domain/usecases/community/signaler_un_contenu.dart';
import '../../../shared/shared.dart';

/// Ce que la feuille de signalement renvoie.
@immutable
class SignalementSaisi {
  const SignalementSaisi({required this.motif, this.detail});

  final MotifSignalement motif;
  final String? detail;
}

/// La feuille de signalement, commune au forum et au Hub.
///
/// # Elle dit AVANT l'envoi ce qui va se passer
///
/// L'arbitrage n° 52 change ce que cet écran doit dire : **signaler n'est pas
/// voter**.
///
/// > « Cinq personnes qui signalent le même message ne le rendent pas cinq fois
/// > plus répréhensible ; il n'y a aucun masquage automatique au-delà d'un
/// > seuil, parce que ce serait offrir un bouton de censure à n'importe quel
/// > groupe organisé. Un humain tranche, toujours. »
///
/// Et l'avertissement est **avant** l'envoi, pas après : « promettre un retrait
/// qui n'aura pas lieu décourage celui qui a signalé de bonne foi ».
///
/// # Les motifs sont exactement les six du contrat
///
/// Ils viennent de `MotifSignalement`, l'énumération fermée du socle. Il n'y en
/// a pas un septième, et il n'y a pas de champ libre à la place du motif : « le
/// motif vient d'une liste fermée ».
class FeuilleSignalement extends StatefulWidget {
  const FeuilleSignalement({super.key});

  static Future<SignalementSaisi?> ouvrir(BuildContext context) {
    return VfBottomSheet.show<SignalementSaisi>(
      context: context,
      title: 'Signaler',
      builder: (BuildContext _) => const FeuilleSignalement(),
    );
  }

  @override
  State<FeuilleSignalement> createState() => _FeuilleSignalementState();
}

class _FeuilleSignalementState extends State<FeuilleSignalement> {
  final TextEditingController _detail = TextEditingController();
  MotifSignalement? _motif;

  @override
  void dispose() {
    _detail.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.x16,
        0,
        VfSpacing.x16,
        VfSpacing.x22,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          // L'AVERTISSEMENT EN PREMIER, avant meme la liste des motifs.
          Container(
            padding: const EdgeInsets.all(VfSpacing.x12),
            decoration: BoxDecoration(
              color: VfColors.primarySoft,
              borderRadius: VfRadii.mdAll,
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                const Icon(
                  Icons.info_outline_rounded,
                  size: 18,
                  color: VfColors.primaryDark,
                ),
                const SizedBox(width: VfSpacing.x8),
                Expanded(
                  child: Text(
                    SignalerUnContenu.avertissementAvantEnvoi,
                    style: VfTextStyles.tXs.copyWith(
                      color: VfColors.primaryDark,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: VfSpacing.x14),
          const Text('Motif', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x8),
          VfMenuList(
            children: <Widget>[
              for (final MotifSignalement motif in SignalerUnContenu.motifs)
                _LigneMotif(
                  motif: motif,
                  selectionne: _motif == motif,
                  onTap: () => setState(() => _motif = motif),
                ),
            ],
          ),
          const SizedBox(height: VfSpacing.x14),
          VfTextArea(
            label: 'Détail (facultatif)',
            controller: _detail,
            value: _detail.text,
            maxLength: SignalerUnContenu.longueurMaximaleDetail,
            minLines: 3,
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: VfSpacing.x12),
          VfPrimaryButton(
            label: 'Envoyer le signalement',
            // Le bouton reste VISIBLE et desactive tant qu'aucun motif n'est
            // choisi : « un bouton qui disparait fait chercher une fonction
            // qu'on croit perdue » (§2.7).
            onPressed: _motif == null
                ? null
                : () => Navigator.of(context).pop(
                    SignalementSaisi(
                      motif: _motif!,
                      detail: _detail.text.trim().isEmpty
                          ? null
                          : _detail.text.trim(),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

class _LigneMotif extends StatelessWidget {
  const _LigneMotif({
    required this.motif,
    required this.selectionne,
    required this.onTap,
  });

  final MotifSignalement motif;
  final bool selectionne;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      inMutuallyExclusiveGroup: true,
      selected: selectionne,
      button: true,
      label: motif.libelle,
      excludeSemantics: true,
      child: VfNavTile(
        label: motif.libelle,
        onTap: onTap,
        trailing: selectionne
            ? const Icon(
                Icons.check_circle_rounded,
                size: 20,
                color: VfColors.primary,
              )
            : const Icon(
                Icons.radio_button_unchecked_rounded,
                size: 20,
                color: VfColors.line,
              ),
      ),
    );
  }
}
