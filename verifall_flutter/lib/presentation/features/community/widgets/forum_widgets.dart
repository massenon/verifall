/// Les composants de l'espace forum.
///
/// # Ils portent le préfixe `Vf` sans appartenir au socle
///
/// Les noms viennent des arbres de widgets de `03-ECRANS-05-COMMUNAUTE.md`, qui
/// sont le vocabulaire de la maquette. **Ils restent la propriété du parcours
/// M4** : aucun autre parcours ne les importe — « aucun import croisé entre deux
/// dossiers `features/` » (§8.1, règle 4). Le jour où l'un d'eux servirait à un
/// second parcours, il devrait remonter au socle, pas être copié.
library;

import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../data/models/auteur.dart';
import '../../../../domain/entities/reply_entity.dart';
import '../../../../domain/entities/thread_entity.dart';
import '../../../shared/shared.dart';
import '../support/texte_m4.dart';
import '../../../../core/format/vf_formats.dart';

/// Le bandeau « VERIF ALL CONFIRME ».
///
/// # La règle la plus importante du forum
///
/// Ce bandeau **n'apparaît que si `data.verdict_officiel` est présent**. Il ne
/// se déduit jamais des votes — arbitrage n° 43.
///
/// > « Une communauté convaincue à 90 % qu'une rumeur est vraie n'en fait pas
/// > une vérification — et laisser l'interface le suggérer serait l'exact
/// > inverse de ce que ce produit existe pour faire. »
///
/// C'est pourquoi ce widget prend un [VerdictOfficiel] et **jamais** une
/// [RepartitionVotes] : il n'y a aucun chemin de code par lequel un sondage
/// pourrait l'allumer.
///
/// POURQUOI il est le premier enfant de la carte : « le bandeau officiel est lu
/// **avant** le titre — c'est l'information la plus importante de la carte »
/// (accessibilité de l'écran 20). Flutter lit dans l'ordre de l'arbre ; le
/// placer en tête suffit, et c'est plus robuste qu'un ordre de tri explicite que
/// personne ne maintiendrait.
class VfOfficialVerdictBanner extends StatelessWidget {
  const VfOfficialVerdictBanner({
    required this.verdict,
    this.compact = false,
    super.key,
  });

  final VerdictOfficiel verdict;

  /// Variante de carte de liste : fond seul, sans bordure.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final String libelle = verdict.libelle.toUpperCase();
    return Semantics(
      // Le lecteur d'ecran recoit une phrase, pas un pictogramme suivi de
      // capitales epelees lettre par lettre.
      label: 'Vérification Vérif All : $libelle',
      excludeSemantics: true,
      child: Container(
        width: double.infinity,
        padding: EdgeInsets.symmetric(
          horizontal: compact ? VfSpacing.x9 : VfSpacing.x12,
          vertical: VfSpacing.x8,
        ),
        decoration: BoxDecoration(
          color: VfColors.verdictFalse.withValues(alpha: compact ? 0.08 : 0.07),
          borderRadius: VfRadii.smAll,
          border: compact
              ? null
              : Border.all(
                  color: VfColors.verdictFalse.withValues(alpha: 0.30),
                ),
        ),
        child: Row(
          children: <Widget>[
            Icon(
              Icons.verified_rounded,
              size: compact ? 16 : 20,
              color: VfColors.verdictFalse,
            ),
            const SizedBox(width: VfSpacing.x6),
            Expanded(
              child: Text(
                'VÉRIF ALL CONFIRME : $libelle',
                style: VfTextStyles.tXs.copyWith(
                  color: VfColors.verdictFalse,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.4,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// La ligne auteur : avatar, nom, ancienneté.
///
/// # Ce qui manque ici, et pourquoi c'est volontaire
///
/// Le simulateur affiche un badge de rôle — « Mentor Verif All », « Contributeur
/// actif », « Citoyenne verifiee ». **Il n'y en a pas ici.**
///
/// L'API ne sérialise jamais le rôle d'un auteur : `AuteurResource` porte uuid,
/// nom d'affichage et initiales, rien d'autre. La raison est bonne — « publier
/// "untel est modérateur" sur chaque message dresserait la liste nominative de
/// l'équipe de modération » (arbitrage n° 74).
///
/// Fabriquer un badge côté client aurait été pire que de le retirer : il aurait
/// affiché une qualité que personne n'a attribuée. Réserve C.4, tranchée et
/// consignée dans `docs/ARBITRAGES.md`.
class VfAuthorRow extends StatelessWidget {
  const VfAuthorRow({
    required this.auteur,
    this.publieLe,
    this.trailing,
    this.petit = false,
    this.maintenant,
    super.key,
  });

  final Auteur auteur;
  final DateTime? publieLe;

  /// La pastille de catégorie, à droite, sur les cartes de sujet.
  final Widget? trailing;

  final bool petit;

  /// Injectée par les tests : une mise en forme de date qui lit l'horloge
  /// elle-même n'est pas vérifiable.
  final DateTime? maintenant;

  @override
  Widget build(BuildContext context) {
    final String? anciennete = VfFormats.anciennete(
      publieLe,
      maintenant: maintenant,
    );

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: <Widget>[
        VfAvatar(
          // Les initiales viennent du serveur : les recalculer donnerait deux
          // resultats differents sur un nom compose.
          initials: auteur.initiales,
          size: petit ? VfAvatarSize.petit : VfAvatarSize.moyen,
          prim: true,
        ),
        const SizedBox(width: VfSpacing.x9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Text(
                auteur.nomAffichage,
                style: VfTextStyles.tSm.copyWith(
                  color: VfColors.ink,
                  fontWeight: FontWeight.w600,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              // L'anciennete disparait quand le serveur n'envoie pas de date.
              // Ecrire « a l instant » par defaut serait inventer un fait.
              if (anciennete != null)
                Text(
                  anciennete,
                  style: VfTextStyles.tXs,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
            ],
          ),
        ),
        if (trailing != null) ...<Widget>[
          const SizedBox(width: VfSpacing.x8),
          trailing!,
        ],
      ],
    );
  }
}

/// Les trois pourcentages sous la barre de vote.
///
/// POURQUOI le texte double systématiquement la barre : « la barre de vote est
/// doublée de son texte — trois segments colorés ne se lisent pas »
/// (accessibilité de l'écran 20).
class VfVotePourcentages extends StatelessWidget {
  const VfVotePourcentages({required this.votes, super.key});

  final RepartitionVotes votes;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: <Widget>[
        Text('Vrai ${votes.vraiPourcent} %', style: VfTextStyles.tXs),
        Text('Doute ${votes.doutePourcent} %', style: VfTextStyles.tXs),
        Text('Faux ${votes.fauxPourcent} %', style: VfTextStyles.tXs),
      ],
    );
  }
}

/// La carte d'un sujet dans la liste du forum.
class VfThreadCard extends StatelessWidget {
  const VfThreadCard({
    required this.sujet,
    required this.onTap,
    this.maintenant,
    super.key,
  });

  final SujetForum sujet;
  final VoidCallback onTap;
  final DateTime? maintenant;

  @override
  Widget build(BuildContext context) {
    final RepartitionVotes? votes = sujet.votes;

    return VfCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          // LE BANDEAU EN PREMIER : il est lu avant le titre.
          if (sujet.verdictOfficiel != null) ...<Widget>[
            VfOfficialVerdictBanner(
              verdict: sujet.verdictOfficiel!,
              compact: true,
            ),
            const SizedBox(height: VfSpacing.x9),
          ],
          VfAuthorRow(
            auteur: sujet.auteur,
            publieLe: sujet.publieLe,
            petit: true,
            maintenant: maintenant,
            // La pastille de categorie ne s'affiche que si le serveur envoie un
            // libelle : un slug brut (« fake-news ») n'est pas un libelle.
            trailing: sujet.categorieLibelle == null
                ? null
                : VfTag(label: sujet.categorieLibelle!),
          ),
          const SizedBox(height: VfSpacing.x9),
          Text(
            sujet.titre,
            style: VfTextStyles.tMd.copyWith(
              fontFamily: VfFonts.display,
              fontWeight: FontWeight.w700,
              height: 1.3,
            ),
          ),
          const SizedBox(height: VfSpacing.x6),
          Text(
            TexteM4.extrait(sujet.corps),
            style: VfTextStyles.tSm,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          // LE SONDAGE, SEPARE DU BANDEAU. Deux zones, deux libelles : c'est
          // l'arbitrage n° 43, et c'est visible dans la structure meme.
          if (votes != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x12),
            VfVoteBar(
              vraiPourcent: votes.vraiPourcent,
              doutePourcent: votes.doutePourcent,
              fauxPourcent: votes.fauxPourcent,
            ),
            const SizedBox(height: VfSpacing.x6),
            VfVotePourcentages(votes: votes),
          ],
          if (sujet.nbJaime != null || sujet.nbReponses != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x12),
            Row(
              children: <Widget>[
                if (sujet.nbJaime != null)
                  _Compteur(
                    icone: Icons.favorite_rounded,
                    valeur: sujet.nbJaime!,
                    libelle: 'j aime',
                  ),
                if (sujet.nbJaime != null && sujet.nbReponses != null)
                  const SizedBox(width: VfSpacing.x16),
                if (sujet.nbReponses != null)
                  _Compteur(
                    icone: Icons.chat_bubble_outline_rounded,
                    valeur: sujet.nbReponses!,
                    libelle: 'reponses',
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// Un compteur de réaction, **en lecture seule**.
///
/// POURQUOI il n'est pas un bouton : aucun endpoint du contrat ne permet
/// d'aimer un sujet. Le cœur cliquable du simulateur est un état local de
/// démonstration. Un bouton qui n'appelle rien ferait croire à un geste
/// enregistré — la même faute que la cloche de notifications (arbitrage n° 80).
class _Compteur extends StatelessWidget {
  const _Compteur({
    required this.icone,
    required this.valeur,
    required this.libelle,
  });

  final IconData icone;
  final int valeur;
  final String libelle;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: '$valeur $libelle',
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icone, size: 16, color: VfColors.slate2),
          const SizedBox(width: VfSpacing.x4),
          Text('$valeur', style: VfTextStyles.tXs),
        ],
      ),
    );
  }
}

/// Le déclencheur de composition, en tête du forum.
class VfComposerTrigger extends StatelessWidget {
  const VfComposerTrigger({
    required this.initiales,
    required this.onTap,
    super.key,
  });

  /// Les initiales du compte connecté.
  final String initiales;

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Ouvrir un nouveau sujet',
      child: VfCard(
        tight: true,
        onTap: onTap,
        child: Row(
          children: <Widget>[
            VfAvatar(initials: initiales, size: VfAvatarSize.petit, prim: true),
            const SizedBox(width: VfSpacing.x10),
            const Expanded(
              child: Text(
                'Une information vous semble douteuse ?',
                style: VfTextStyles.tSm,
              ),
            ),
            const Icon(Icons.edit_rounded, size: 20, color: VfColors.primary),
          ],
        ),
      ),
    );
  }
}

/// Le panneau de vote de la fiche d'un sujet.
///
/// # Deux zones, deux libellés — et ils ne se rencontrent jamais
///
/// Ce panneau s'intitule **« Que pense la communaute ? »**. Le bandeau, lui, dit
/// **« VERIF ALL CONFIRME »**. Ce ne sont pas deux affichages du même chiffre :
/// l'un est un sondage, l'autre une vérification (arbitrage n° 43).
///
/// Ce widget ne reçoit **aucun** verdict officiel, et le bandeau ne reçoit
/// **aucune** répartition de votes. Il n'existe donc aucun chemin de code où
/// l'un pourrait allumer l'autre.
class VfVotePanel extends StatelessWidget {
  const VfVotePanel({
    required this.votes,
    required this.monVote,
    required this.onVoter,
    this.provisoire = false,
    super.key,
  });

  final RepartitionVotes votes;
  final AvisCommunaute? monVote;
  final ValueChanged<AvisCommunaute> onVoter;

  /// Vrai quand le vote attend son envoi. « Ne jamais faire croire qu'une
  /// écriture a abouti » (05-HORS-LIGNE §5.7).
  final bool provisoire;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Text('Que pense la communauté ?', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x12),
          VfVoteBar(
            vraiPourcent: votes.vraiPourcent,
            doutePourcent: votes.doutePourcent,
            fauxPourcent: votes.fauxPourcent,
            tall: true,
          ),
          const SizedBox(height: VfSpacing.x8),
          VfVotePourcentages(votes: votes),
          const SizedBox(height: VfSpacing.x12),
          Text(
            monVote == null
                ? 'Votre avis, en un geste :'
                : 'Votre vote : ${monVote!.libelleBouton}',
            style: VfTextStyles.tXs,
          ),
          if (provisoire) ...<Widget>[
            const SizedBox(height: VfSpacing.x4),
            Text(
              'En attente d envoi. Le résultat consolide viendra du serveur.',
              style: VfTextStyles.tXs.copyWith(
                color: VfColors.verdictInvestigate,
              ),
            ),
          ],
          const SizedBox(height: VfSpacing.x9),
          Row(
            children: <Widget>[
              for (final AvisCommunaute avis
                  in AvisCommunaute.values) ...<Widget>[
                Expanded(
                  child: _BoutonVote(
                    avis: avis,
                    selectionne: monVote == avis,
                    onPressed: () => onVoter(avis),
                  ),
                ),
                if (avis != AvisCommunaute.values.last)
                  const SizedBox(width: VfSpacing.x8),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

/// Un des trois boutons de vote.
///
/// # DEMANDE ADRESSÉE AU RESPONSABLE DU SOCLE
///
/// `VfButton` ne sait pas faire ces deux choses, que la maquette exige ici : la
/// hauteur `.btn-sm` (38, déjà déclarée en `VfTouch.smallButtonHeight` mais non
/// câblée) et **une couleur de bordure et de texte par instance** — « le bouton
/// correspondant au vote émis prend la couleur du choix » (écran 21).
///
/// Ce bouton est donc dessiné ici, avec les seuls jetons du design system, et la
/// demande est consignée. Ce n'est pas un composant partagé qui manque : c'est
/// une variante d'un composant du socle, et seul le socle peut l'ajouter.
class _BoutonVote extends StatelessWidget {
  const _BoutonVote({
    required this.avis,
    required this.selectionne,
    required this.onPressed,
  });

  final AvisCommunaute avis;
  final bool selectionne;
  final VoidCallback onPressed;

  /// La couleur du choix. Ce sont les trois couleurs de verdict du design
  /// system, celles-là mêmes qu'emploie la barre de vote — elles ne sont pas
  /// choisies ici, elles sont reprises.
  Color get _couleur => switch (avis) {
    AvisCommunaute.vrai => VfColors.verdictTrue,
    AvisCommunaute.doute => VfColors.verdictInvestigate,
    AvisCommunaute.faux => VfColors.verdictFalse,
  };

  @override
  Widget build(BuildContext context) {
    final Color contour = selectionne ? _couleur : VfColors.line;
    final Color texte = selectionne ? _couleur : VfColors.ink;

    return Semantics(
      button: true,
      // « Les trois boutons de vote annoncent leur etat selectionne »
      // (accessibilite de l'ecran 21).
      selected: selectionne,
      label: 'Voter ${avis.libelleBouton}',
      excludeSemantics: true,
      child: Material(
        color: VfColors.surface,
        borderRadius: VfRadii.mdAll,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onPressed,
          child: Container(
            constraints: const BoxConstraints(
              // Le plancher d'accessibilite du chantier : 44, sans exception
              // (§2.4). Le `.btn-sm` du simulateur mesure 38 ; on garde la
              // cible tactile a 44 et l'apparence compacte par la marge.
              minHeight: VfTouch.minimum,
            ),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              borderRadius: VfRadii.mdAll,
              border: Border.all(color: contour),
            ),
            child: Text(
              avis.libelleBouton,
              style: VfTextStyles.tSm.copyWith(
                color: texte,
                fontWeight: selectionne ? FontWeight.w700 : FontWeight.w600,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// La ligne de réactions de la fiche d'un sujet : compteur et partage.
class VfReactionRow extends StatelessWidget {
  const VfReactionRow({required this.onPartager, this.nbJaime, super.key});

  final int? nbJaime;
  final VoidCallback onPartager;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        // LE COEUR N'EST PAS UN BOUTON. Aucun endpoint ne permet d'aimer un
        // sujet ; il n'apparait que si le serveur sert un compteur.
        if (nbJaime != null) ...<Widget>[
          _Compteur(
            icone: Icons.favorite_rounded,
            valeur: nbJaime!,
            libelle: 'j aime',
          ),
          const SizedBox(width: VfSpacing.x16),
        ],
        Expanded(
          child: VfGhostButton(
            label: 'Partager',
            icon: Icons.ios_share_rounded,
            onPressed: onPartager,
          ),
        ),
      ],
    );
  }
}

/// Une réponse dans le fil.
class VfReplyTile extends StatelessWidget {
  const VfReplyTile({
    required this.reponse,
    this.maintenant,
    this.onLienExterne,
    super.key,
  });

  final ReponseForum reponse;
  final DateTime? maintenant;

  /// L'ouverture d'un lien du corps, **en onglet système**. Voir
  /// `support/liens_m4.dart`.
  final void Function(String url)? onLienExterne;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      tight: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          VfAuthorRow(
            auteur: reponse.auteur,
            publieLe: reponse.publieLe,
            petit: true,
            maintenant: maintenant,
          ),
          const SizedBox(height: VfSpacing.x8),
          VfProseBody(html: reponse.corps, onLinkTap: onLienExterne),
          // UN SEUL NIVEAU. Les enfants d'une reponse imbriquee ont deja ete
          // ecartes a la lecture du JSON ; ce retrait n'est donc pas une
          // troncature d'affichage mais l'application d'une regle de donnees.
          if (reponse.reponses.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(
                left: VfSpacing.x16,
                top: VfSpacing.x10,
              ),
              child: Column(
                children: <Widget>[
                  for (final ReponseForum enfant in reponse.reponses)
                    Padding(
                      padding: const EdgeInsets.only(bottom: VfSpacing.x9),
                      child: VfReplyTile(
                        reponse: enfant,
                        maintenant: maintenant,
                        onLienExterne: onLienExterne,
                      ),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
