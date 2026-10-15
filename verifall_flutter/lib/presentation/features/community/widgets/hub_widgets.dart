/// Les composants du Hub : brèves et opportunités.
///
/// Comme ceux du forum, ils portent le préfixe `Vf` parce que c'est le
/// vocabulaire des arbres de widgets de la documentation, **sans appartenir au
/// socle** : ils sont la propriété du parcours M4 et aucun autre parcours ne les
/// importe (§8.1, règle 4).
library;

import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/article_entity.dart';
import '../../../../domain/entities/opportunity_entity.dart';
import '../../../shared/shared.dart';
import '../support/echeance_m4.dart';
import '../../../../core/format/vf_formats.dart';

/// `.opp-type` — la pastille de type, colorée par **famille**.
///
/// POURQUOI le libellé vient du serveur et n'est jamais fabriqué ici : « valeur
/// technique et libellé français voyagent ensemble ; ajouter un cas côté serveur
/// casserait tous les `switch` du client » (§8). Le `switch` de ce widget porte
/// sur la **famille visuelle**, qui est une décision de design et non une donnée.
class VfTypeChip extends StatelessWidget {
  const VfTypeChip({required this.libelle, this.famille, super.key})
    : cloturee = false;

  /// La variante « CLOTUREE ».
  ///
  /// POURQUOI un drapeau explicite plutôt qu'une comparaison sur le libellé :
  /// le libellé de type vient du serveur, et rien n'interdit qu'un jour l'un
  /// d'eux ressemble à ce mot. Un style ne doit pas dépendre d'une coïncidence
  /// de texte.
  const VfTypeChip.cloturee({super.key})
    : libelle = 'CLÔTURÉE',
      famille = null,
      cloturee = true;

  final String libelle;

  /// `null` quand le client ne connaît pas la valeur technique reçue : la
  /// pastille prend alors le style de base plutôt que de disparaître.
  final FamilleOpportunite? famille;

  final bool cloturee;

  @override
  Widget build(BuildContext context) {
    const Color rougeCloture = VfColors.brandRed;
    final (Color fond, Color texte) = cloturee
        ? (rougeCloture.withValues(alpha: 0.09), rougeCloture)
        : switch (famille ?? FamilleOpportunite.autre) {
            FamilleOpportunite.contrat => (
              VfColors.info.withValues(alpha: 0.10),
              VfColors.info,
            ),
            FamilleOpportunite.etude => (
              VfColors.xp.withValues(alpha: 0.10),
              VfColors.xp,
            ),
            FamilleOpportunite.evenement => (
              VfColors.verdictInvestigate.withValues(alpha: 0.12),
              VfColors.verdictInvestigate,
            ),
            FamilleOpportunite.autre => (
              VfColors.primarySoft,
              VfColors.primaryDark,
            ),
          };

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: VfSpacing.x8,
        vertical: 3,
      ),
      decoration: BoxDecoration(
        color: fond,
        borderRadius: BorderRadius.circular(7),
      ),
      child: Text(
        libelle.toUpperCase(),
        style: VfTextStyles.tXs.copyWith(
          color: texte,
          fontSize: 10.5,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.4,
        ),
        // Le lecteur d'ecran recoit le libelle d'origine : des capitales sont
        // parfois epelees lettre par lettre.
        semanticsLabel: libelle,
      ),
    );
  }
}

/// La mention « Verifie » d'une carte, ou « EMPLOYEUR VERIFIE » d'une fiche.
///
/// POURQUOI un widget dédié plutôt qu'une condition dans la carte : ce badge est
/// piloté par `data.verifiee` **et par lui seul** — « ça ne se déduit d'aucun
/// autre champ » (§10.5). L'isoler, c'est n'avoir qu'un endroit à relire pour
/// s'en assurer.
class VfVerifiedBadge extends StatelessWidget {
  const VfVerifiedBadge({this.complet = false, super.key});

  /// Variante de fiche : pastille verte pleine avec le mot « EMPLOYEUR ».
  final bool complet;

  @override
  Widget build(BuildContext context) {
    final String libelle = complet ? 'EMPLOYEUR VERIFIE' : 'Verifie';
    return Semantics(
      label: complet ? 'Employeur verifie par la redaction' : 'Offre vérifiée',
      excludeSemantics: true,
      child: Container(
        padding: complet
            ? const EdgeInsets.symmetric(
                horizontal: VfSpacing.x8,
                vertical: VfSpacing.x4,
              )
            : EdgeInsets.zero,
        decoration: complet
            ? BoxDecoration(
                color: VfColors.verdictTrue.withValues(alpha: 0.12),
                borderRadius: VfRadii.pillAll,
              )
            : null,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(
              Icons.verified_rounded,
              size: 14,
              color: VfColors.verdictTrue,
            ),
            const SizedBox(width: 3),
            Text(
              libelle,
              style: VfTextStyles.tXs.copyWith(
                color: VfColors.verdictTrue,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// `.deadline` — l'échéance, texte et couleur.
///
/// **Aucun calcul.** Le texte et la couleur sortent de [LibelleEcheance], qui ne
/// fait que mettre en forme `data.expiree` et `data.jours_restants`.
class VfDeadlinePill extends StatelessWidget {
  const VfDeadlinePill({required this.libelle, this.gras = false, super.key});

  final LibelleEcheance libelle;

  /// Variante de la liste de faits, où la valeur est en gras.
  final bool gras;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: libelle.pourLecteurDEcran,
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(Icons.event_rounded, size: 15, color: libelle.couleur),
          const SizedBox(width: VfSpacing.x4),
          Flexible(
            child: Text(
              libelle.texte,
              style: VfTextStyles.tXs.copyWith(
                color: libelle.couleur,
                fontWeight: gras ? FontWeight.w700 : FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// La carte d'une opportunité dans la liste.
class VfOpportunityCard extends StatelessWidget {
  const VfOpportunityCard({
    required this.offre,
    required this.onTap,
    super.key,
  });

  final Opportunite offre;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final LibelleEcheance? echeance = LibelleEcheance.pour(offre);

    return VfCard(
      tight: true,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              // La pastille de type n'apparait que si le serveur envoie un
              // libelle : le fabriquer a partir de la valeur technique serait
              // exactement ce que le contrat interdit.
              if (offre.typeLibelle != null)
                VfTypeChip(
                  libelle: offre.typeLibelle!,
                  famille: offre.type?.famille,
                ),
              if (offre.typeLibelle != null && offre.expiree)
                const SizedBox(width: VfSpacing.x6),
              // « CLOTUREE » est pilote par data.expiree, jamais par une
              // comparaison de dates locale.
              if (offre.expiree) const VfTypeChip.cloturee(),
              const Spacer(),
              if (offre.verifiee) const VfVerifiedBadge(),
            ],
          ),
          const SizedBox(height: VfSpacing.x8),
          Text(
            offre.titre,
            style: VfTextStyles.tMd.copyWith(
              fontFamily: VfFonts.display,
              fontWeight: FontWeight.w700,
              height: 1.3,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          if (offre.organisation != null || offre.lieu != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x6),
            Text(
              <String>[
                if (offre.organisation != null) offre.organisation!,
                if (offre.lieu != null) offre.lieu!,
              ].join(' · '),
              style: VfTextStyles.tXs,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          if (echeance != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x9),
            VfDeadlinePill(libelle: echeance),
          ],
        ],
      ),
    );
  }
}

/// La ligne de faits d'une fiche d'offre : organisation, lieu, échéance.
class VfFactList extends StatelessWidget {
  const VfFactList({required this.offre, super.key});

  final Opportunite offre;

  @override
  Widget build(BuildContext context) {
    final LibelleEcheance? echeance = LibelleEcheance.pour(offre);

    return VfMenuList(
      children: <Widget>[
        if (offre.organisation != null)
          _Fait(
            icone: Icons.apartment_rounded,
            libelle: 'Organisation',
            valeur: Text(
              offre.organisation!,
              style: VfTextStyles.tSm.copyWith(
                color: VfColors.ink,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        if (offre.lieu != null)
          _Fait(
            icone: Icons.location_on_rounded,
            libelle: 'Lieu',
            valeur: Text(
              offre.lieu!,
              style: VfTextStyles.tSm.copyWith(
                color: VfColors.ink,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        if (echeance != null)
          _Fait(
            icone: Icons.event_rounded,
            couleurIcone: echeance.couleur,
            libelle: 'Échéance',
            valeur: Text(
              echeance.texte,
              style: VfTextStyles.tSm.copyWith(
                color: echeance.couleur,
                fontWeight: FontWeight.w700,
              ),
              semanticsLabel: echeance.pourLecteurDEcran,
            ),
          ),
      ],
    );
  }
}

class _Fait extends StatelessWidget {
  const _Fait({
    required this.icone,
    required this.libelle,
    required this.valeur,
    this.couleurIcone,
  });

  final IconData icone;
  final String libelle;
  final Widget valeur;
  final Color? couleurIcone;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: VfTouch.menuRowMinHeight),
      padding: const EdgeInsets.symmetric(
        horizontal: VfTouch.menuRowInlinePadding,
        vertical: VfTouch.menuRowVerticalPadding,
      ),
      child: Row(
        children: <Widget>[
          Icon(icone, size: 20, color: couleurIcone ?? VfColors.slate2),
          const SizedBox(width: VfSpacing.x12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(libelle, style: VfTextStyles.tXs),
                valeur,
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// L'encart d'une offre clôturée.
///
/// POURQUOI il **remplace** le bouton de candidature au lieu de l'accompagner :
/// « une offre clôturée n'affiche aucun bouton de candidature ». Un bouton grisé
/// aurait laissé croire qu'il suffisait d'insister.
class VfClosedNotice extends StatelessWidget {
  const VfClosedNotice({super.key});

  @override
  Widget build(BuildContext context) {
    return VfCard(
      tight: true,
      background: VfColors.brandRed.withValues(alpha: 0.06),
      border: VfColors.brandRed.withValues(alpha: 0.30),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Icon(
            Icons.event_busy_rounded,
            size: 19,
            color: VfColors.brandRed,
          ),
          const SizedBox(width: VfSpacing.x9),
          Expanded(
            child: Text(
              'La date limite est depassee. L offre reste consultable quelques '
              'jours, puis disparaitra du Hub.',
              style: VfTextStyles.tXs.copyWith(color: VfColors.brandRed),
            ),
          ),
        ],
      ),
    );
  }
}

/// L'avertissement anti-frais de la fiche d'une offre.
///
/// # Le texte vient du serveur, et c'est le point
///
/// Le contrat tranche la réserve A.6 : « la fiche d'une opportunité porte
/// **toujours** `meta.avertissement`. Systématique, pas conditionnel : une offre
/// frauduleuse ne se signale pas d'elle-même, et c'est la plus dangereuse. »
///
/// POURQUOI le client n'adapte pas le texte au type de l'offre, alors que
/// l'arbitrage n° 55 disait « recrutements seulement » : c'est le serveur qui
/// rédige la phrase, donc lui seul qui sait écrire autre chose sous une
/// conférence payante. Un client qui choisirait finirait par écrire « aucun
/// employeur ne demande de frais » sous une conférence — et un avertissement
/// faux use la crédibilité des vrais.
class VfScamWarning extends StatelessWidget {
  const VfScamWarning({required this.texte, super.key});

  /// `meta.avertissement`, affiché tel quel.
  final String texte;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      tight: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Icon(
            Icons.gpp_maybe_rounded,
            size: 19,
            color: VfColors.brandRed,
          ),
          const SizedBox(width: VfSpacing.x9),
          Expanded(child: Text(texte, style: VfTextStyles.tXs)),
        ],
      ),
    );
  }
}

/// Les deux encarts de pied de la liste d'opportunités.
///
/// Ils sont **toujours présents** sous les deux rubriques d'opportunités, et
/// leur texte vient du simulateur.
///
/// POURQUOI l'avertissement anti-frais est ici un texte fixe alors que la fiche
/// prend le sien du serveur : il ne porte sur aucune offre en particulier. C'est
/// une consigne de prudence adressée au lecteur d'une liste, pas une
/// qualification d'un contenu — la nuance qui rendrait faux « aucun employeur ne
/// demande de frais » écrit sous une conférence payante ne s'applique pas à un
/// pied de liste.
class VfHubFooterNotices extends StatelessWidget {
  const VfHubFooterNotices({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        VfCard(
          tight: true,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const Icon(
                Icons.schedule_rounded,
                size: 19,
                color: VfColors.verdictInvestigate,
              ),
              const SizedBox(width: VfSpacing.x9),
              Expanded(
                child: Text(
                  'Une offre reste consultable 7 jours après sa date limite, '
                  'puis elle est archivee automatiquement.',
                  style: VfTextStyles.tXs,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: VfSpacing.stackSm),
        VfCard(
          tight: true,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const Icon(
                Icons.gpp_maybe_rounded,
                size: 19,
                color: VfColors.brandRed,
              ),
              const SizedBox(width: VfSpacing.x9),
              Expanded(
                child: Text(
                  'Aucun employeur serieux ne demande de frais de dossier. '
                  'Signalez-nous toute offre qui reclame un paiement.',
                  style: VfTextStyles.tXs,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// La pastille de verdict d'une brève, ou « EN COURS » si elle n'en a pas.
class VfHubVerdictBadge extends StatelessWidget {
  const VfHubVerdictBadge({
    required this.article,
    this.compact = true,
    super.key,
  });

  final ArticleHub article;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return article.aUnVerdict
        ? VfVerdictBadge(
            libelle: article.verdictLibelle!,
            tone: VfVerdictTones.pour(article.verdict),
            compact: compact,
          )
        : VfVerdictBadge(
            // « Une breve sans verdict affiche EN COURS et non un bandeau vide :
            // un bandeau vide passerait pour un defaut d'affichage. »
            libelle: ArticleHub.libelleVerdictAbsent,
            tone: VfVerdictTone.enCours,
            compact: compact,
          );
  }
}

/// La carte d'une brève.
class VfNewsCard extends StatelessWidget {
  const VfNewsCard({
    required this.article,
    required this.onTap,
    this.maintenant,
    super.key,
  });

  final ArticleHub article;
  final VoidCallback onTap;
  final DateTime? maintenant;

  @override
  Widget build(BuildContext context) {
    final String? anciennete = VfFormats.anciennete(
      article.publieLe,
      maintenant: maintenant,
    );

    return VfCard(
      tight: true,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              VfHubVerdictBadge(article: article),
              const Spacer(),
              if (anciennete != null) Text(anciennete, style: VfTextStyles.tXs),
            ],
          ),
          const SizedBox(height: VfSpacing.x8),
          Text(
            article.titre,
            style: VfTextStyles.tMd.copyWith(
              fontFamily: VfFonts.display,
              fontWeight: FontWeight.w700,
              height: 1.3,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          if (article.chapo != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x6),
            Text(
              article.chapo!,
              style: VfTextStyles.tXs,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          if (article.sourcePrimaire != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x8),
            Text(
              article.sourcePrimaire!,
              style: VfTextStyles.tXs.copyWith(
                color: VfColors.primary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// La carte à la une des actualités.
///
/// # DEMANDE ADRESSÉE AU RESPONSABLE DU SOCLE
///
/// Le simulateur donne à cette couverture une hauteur de **120**, et à celle de
/// l'article une hauteur de **130**. `VfCover` n'expose que deux hauteurs figées
/// (112 et 86). Plutôt que de dupliquer le composant pour huit points d'écart,
/// on emploie `VfCover` tel quel et l'on demande un paramètre de hauteur. L'écart
/// est consigné dans `docs/ARBITRAGES.md`.
class VfLeadArticleCard extends StatelessWidget {
  const VfLeadArticleCard({
    required this.article,
    required this.onTap,
    this.maintenant,
    super.key,
  });

  final ArticleHub article;
  final VoidCallback onTap;
  final DateTime? maintenant;

  @override
  Widget build(BuildContext context) {
    final String? anciennete = VfFormats.anciennete(
      article.publieLe,
      maintenant: maintenant,
    );

    return Semantics(
      // « La carte a la une annonce qu'elle est l'article principal »
      // (accessibilite de l'ecran 22).
      label: 'Article principal : ${article.titre}',
      button: true,
      child: VfCard(
        padding: EdgeInsets.zero,
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            VfCover(
              gradientKey: article.slug,
              imageUrl: article.couvertureUrl,
              // 120 : la mesure de la carte a la une (ecran 22). L'ecart de 8
              // points etait assume faute de parametre ; le socle en a un
              // maintenant (arbitrage n° 134).
              height: 120,
              overlay: const Center(
                child: Text(
                  'L ANALYSE DU JOUR',
                  style: TextStyle(
                    fontFamily: VfFonts.display,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: VfColors.surface,
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(VfSpacing.x14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      VfHubVerdictBadge(article: article),
                      const Spacer(),
                      if (anciennete != null)
                        Text(anciennete, style: VfTextStyles.tXs),
                    ],
                  ),
                  const SizedBox(height: VfSpacing.x8),
                  Text(
                    article.titre,
                    style: VfTextStyles.hSm.copyWith(height: 1.3),
                  ),
                  if (article.chapo != null) ...<Widget>[
                    const SizedBox(height: VfSpacing.x6),
                    Text(
                      article.chapo!,
                      style: VfTextStyles.tSm,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// L'encart de source primaire d'un article.
///
/// POURQUOI le nom de la source est en gras et lu en entier : « c'est l'élément
/// que le lecteur doit retenir » (accessibilité de l'écran 24). C'est aussi ce
/// qui sépare le Hub d'un agrégateur — arbitrage n° 44.
class VfSourceTile extends StatelessWidget {
  const VfSourceTile({required this.source, super.key});

  final String source;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      tight: true,
      child: Row(
        children: <Widget>[
          const Icon(Icons.source_rounded, size: 19, color: VfColors.primary),
          const SizedBox(width: VfSpacing.x9),
          Expanded(
            child: Text.rich(
              TextSpan(
                children: <InlineSpan>[
                  const TextSpan(text: 'Source primaire : '),
                  TextSpan(
                    text: source,
                    style: VfTextStyles.tXs.copyWith(
                      color: VfColors.ink,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
              style: VfTextStyles.tXs,
              semanticsLabel: 'Source primaire : $source',
            ),
          ),
        ],
      ),
    );
  }
}

/// L'encart Académie du bas d'un article.
///
/// POURQUOI il navigue **par chemin** et non par un import du parcours M3 :
/// « les dépendances fonctionnelles se résolvent par la navigation, jamais par
/// un import croisé entre deux dossiers `features/` » (§8.1, règle 4).
class VfAcademyPromo extends StatelessWidget {
  const VfAcademyPromo({required this.onVoirCatalogue, super.key});

  final VoidCallback onVoirCatalogue;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      background: VfColors.forest,
      border: VfColors.forest,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            padding: const EdgeInsets.symmetric(
              horizontal: VfSpacing.x8,
              vertical: VfSpacing.x4,
            ),
            decoration: const BoxDecoration(
              color: VfColors.primary,
              borderRadius: VfRadii.pillAll,
            ),
            child: Text(
              'ACADÉMIE',
              style: VfTextStyles.tXs.copyWith(
                color: VfColors.surface,
                fontWeight: FontWeight.w700,
              ),
              semanticsLabel: 'Académie',
            ),
          ),
          const SizedBox(height: VfSpacing.x9),
          Text(
            'Apprenez a faire ce travail vous-meme',
            style: VfTextStyles.hSm.copyWith(color: VfColors.surface),
          ),
          const SizedBox(height: VfSpacing.x6),
          Text(
            'Nos formations sont gratuites et durent moins d une heure.',
            style: VfTextStyles.tXs.copyWith(color: VfColors.onForestMuted),
          ),
          const SizedBox(height: VfSpacing.x12),
          VfGhostButton(label: 'Voir le catalogue', onPressed: onVoirCatalogue),
        ],
      ),
    );
  }
}

/// Le bouton vert « Partager sur WhatsApp » du bas d'un article.
///
/// # Un écart levé, un écart qui tient
///
/// **La couleur** n'est plus dessinée ici : `VfWhatsAppButton` est au socle
/// depuis que M5 s'est révélé avoir écrit le même bouton (arbitrage n° 139).
///
/// **La cible** reste la feuille de partage du système, pas WhatsApp
/// directement : ouvrir l'application exigerait un schéma d'URL que ni le
/// contrat ni le simulateur ne fournissent, et un bouton qui échouerait sur un
/// téléphone sans WhatsApp serait pire. La feuille système propose WhatsApp en
/// premier là où il est installé, ce qui est le cas au Bénin.
class VfWhatsAppShareButton extends StatelessWidget {
  const VfWhatsAppShareButton({required this.onPressed, super.key});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => VfWhatsAppButton(
    label: 'Partager sur WhatsApp',
    semanticsLabel: 'Partager cet article, WhatsApp compris',
    onPressed: onPressed,
  );
}
