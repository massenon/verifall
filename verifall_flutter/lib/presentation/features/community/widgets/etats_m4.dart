/// Les deux affichages transverses des écrans M4 : le squelette de carte et
/// l'échec.
///
/// POURQUOI ils sont ici et pas dupliqués dans les cinq pages : cinq copies d'un
/// même état d'erreur divergent en une semaine, exactement comme un composant
/// partagé recopié dans deux parcours. La différence, c'est qu'ils ne servent
/// qu'à **ce** parcours : ils n'ont donc rien à faire au socle.
library;

import 'package:flutter/material.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../shared/shared.dart';

/// Le squelette d'une carte pendant le chargement.
///
/// POURQUOI un squelette plutôt qu'un indicateur circulaire : « il annonce la
/// forme de ce qui arrive » (§2.7). Le circulaire reste légitime aux trois
/// endroits où le cache est interdit — aucun d'eux n'est dans ce parcours.
class SqueletteDeCarte extends StatelessWidget {
  const SqueletteDeCarte({this.lignes = 3, super.key});

  final int lignes;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const VfSkeleton(width: 140, height: 14),
          for (int i = 0; i < lignes; i++) ...<Widget>[
            const SizedBox(height: VfSpacing.x8),
            VfSkeleton(height: i == 0 ? 18 : 12),
          ],
        ],
      ),
    );
  }
}

/// L'affichage d'un échec **quand il ne reste rien à montrer**.
///
/// POURQUOI il ne remplace jamais un contenu en cache : « si la requête échoue,
/// garder l'affichage et poser un bandeau d'horodatage » (05-HORS-LIGNE §5.4).
/// Ce widget n'apparaît donc que sur une liste ou une fiche réellement vide.
///
/// POURQUOI le message hors ligne dit **ce qui reste possible** au lieu de
/// proposer « Réessayer » dans le vide : c'est la raison d'être de
/// `NetworkFailure` comme catégorie distincte d'une panne serveur.
class EtatEchec extends StatelessWidget {
  const EtatEchec({
    required this.echec,
    required this.onReessayer,
    this.messageHorsLigne,
    super.key,
  });

  final Failure echec;
  final Future<void> Function() onReessayer;

  /// Ce qui reste faisable sans réseau, propre à l'écran.
  final String? messageHorsLigne;

  @override
  Widget build(BuildContext context) {
    final bool horsLigne = echec is NetworkFailure;
    return VfEmptyState(
      kind: VfEmptyKind.aucunResultat,
      icon: horsLigne ? Icons.wifi_off_rounded : Icons.cloud_off_rounded,
      title: horsLigne ? 'Vous etes hors ligne' : 'Chargement impossible',
      message: echec.hasServerMessage
          ? echec.serverMessage!
          : horsLigne
          ? (messageHorsLigne ??
                'Le contenu déjà consulte reste lisible depuis cet appareil.')
          : 'Nos serveurs repondent mal. Reessayez dans un instant.',
      action: VfGhostButton(
        label: 'Réessayer',
        expanded: false,
        onPressed: onReessayer,
      ),
    );
  }
}

/// Le repli d'un contenu introuvable, atteint le plus souvent par lien profond.
///
/// # Il n'affirme rien, et c'est la règle
///
/// « Le `404` du contrat couvre aussi bien "dépublié" que "jamais existé", et
/// un uuid ou un slug deviné ne doit renseigner sur rien » — « ce qui
/// n'appartient pas au demandeur répond `404`, jamais `403` » (§9).
///
/// Écrire « ce contenu a été supprimé » serait une affirmation que le client n'a
/// pas les moyens de faire. C'est le cas le plus fréquent du Hub : « un lien
/// partagé sur WhatsApp survit à l'offre ».
class ReplIntrouvable extends StatelessWidget {
  const ReplIntrouvable({
    required this.titre,
    required this.libelleSortie,
    required this.onSortir,
    super.key,
  });

  final String titre;
  final String libelleSortie;
  final VoidCallback onSortir;

  @override
  Widget build(BuildContext context) {
    return VfEmptyState(
      kind: VfEmptyKind.aucunResultat,
      icon: Icons.explore_off_outlined,
      title: titre,
      message: 'Il a peut-être ete retire, ou il ne vous est pas accessible.',
      action: VfGhostButton(
        label: libelleSortie,
        expanded: false,
        onPressed: onSortir,
      ),
    );
  }
}
