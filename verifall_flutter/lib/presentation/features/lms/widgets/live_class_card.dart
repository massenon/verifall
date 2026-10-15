import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/live_class_session.dart';
import '../../../shared/shared.dart';
import '../../../../core/format/vf_formats.dart';

/// La carte d'une séance en direct.
///
/// # Le libellé du bouton change avec l'onglet, et ce n'est pas cosmétique
///
/// | Onglet | Bouton | Style |
/// |---|---|---|
/// | À venir | **Me le rappeler** | primaire |
/// | En direct | **Rejoindre maintenant** | primaire |
/// | Rediffusions | **Voir la rediffusion** | fantôme |
///
/// « C'est l'application directe de "un bouton dit ce qui va se passer". »
///
/// # `lien_visio` décide de l'action
///
/// Le champ est **`null` tant que la séance n'est pas joignable** : le contrat
/// refuse de publier trois semaines à l'avance un lien qui circulerait, « et
/// l'animateur découvre une salle pleine d'inconnus ». Le bouton « Rejoindre »
/// n'est donc actif que si le lien est présent — **et il dit pourquoi** quand il
/// ne l'est pas.
class LiveClassCard extends StatelessWidget {
  const LiveClassCard({
    required this.seance,
    required this.onRappeler,
    required this.onRejoindre,
    super.key,
  });

  final LiveClassSession seance;
  final void Function(LiveClassSession seance) onRappeler;
  final void Function(String lien) onRejoindre;

  @override
  Widget build(BuildContext context) {
    final bool rappelDejaDemande = seance.rappelDemande ?? false;
    final String quand = seance.debuteLe == null
        ? ''
        : VfFormats.dateEtHeure(seance.debuteLe!);
    final String animateur = seance.animateur?.nomAffichage ?? '';
    final String sousTitre = <String>[
      if (animateur.isNotEmpty) animateur,
      if (quand.isNotEmpty) quand,
      if (seance.minutes > 0) '${seance.minutes} min',
    ].join(' · ');

    return VfCard(
      tight: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              if (seance.coursSlug != null)
                VfTag(label: seance.coursSlug!, variant: VfTagVariant.prim),
              const Spacer(),
              if (seance.etat == EtatSeance.enDirect)
                // La pastille est DOUBLEE d'un mot dans le libelle de la carte :
                // une couleur seule ne se lit pas.
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: VfSpacing.x8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: VfColors.verdictFalse,
                    borderRadius: VfRadii.pillAll,
                  ),
                  child: Text(
                    'EN DIRECT',
                    style: VfTextStyles.tXs.copyWith(
                      color: VfColors.surface,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 7),
          Semantics(
            header: true,
            label: seance.etat == EtatSeance.enDirect
                ? '${seance.titre}, en direct'
                : seance.titre,
            child: Text(seance.titre, style: VfTextStyles.hSm),
          ),
          if (sousTitre.isNotEmpty) ...<Widget>[
            const SizedBox(height: 5),
            Text(sousTitre, style: VfTextStyles.tXs),
          ],
          const SizedBox(height: 11),
          _bouton(rappelDejaDemande),
          if (!seance.estJoignable &&
              seance.etat != EtatSeance.aVenir) ...<Widget>[
            const SizedBox(height: VfSpacing.x6),
            Text(
              seance.etat == EtatSeance.enDirect
                  ? 'Le lien de la seance n est pas encore ouvert.'
                  : 'Aucune rediffusion n a ete publiee pour cette seance.',
              style: VfTextStyles.tXs,
            ),
          ],
        ],
      ),
    );
  }

  Widget _bouton(bool rappelDejaDemande) {
    switch (seance.etat) {
      case EtatSeance.aVenir:
        return VfPrimaryButton(
          label: rappelDejaDemande ? 'Rappel demande' : 'Me le rappeler',
          expanded: true,
          onPressed: rappelDejaDemande ? null : () => onRappeler(seance),
        );
      case EtatSeance.enDirect:
        return VfPrimaryButton(
          label: 'Rejoindre maintenant',
          expanded: true,
          // « Rejoindre exige le reseau et le dit » : le bouton reste visible
          // mais inactif, jamais masque.
          onPressed: seance.estJoignable
              ? () => onRejoindre(seance.lienVisio!)
              : null,
        );
      case EtatSeance.passee:
      case EtatSeance.inconnu:
        return VfGhostButton(
          label: 'Voir la rediffusion',
          expanded: true,
          onPressed: seance.estJoignable
              ? () => onRejoindre(seance.lienVisio!)
              : null,
        );
    }
  }
}
