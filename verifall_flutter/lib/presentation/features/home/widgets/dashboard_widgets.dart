import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/home_tableau_de_bord.dart';
import '../../../shared/vf_buttons.dart';
import '../../../shared/vf_card.dart';
import '../../../shared/vf_display.dart';
import '../../../../core/format/vf_formats.dart';

/// La salutation, la série, et **la ligne d'état système**.
///
/// # Le détail qui change tout
///
/// Le simulateur écrit « synchronisé il y a 2 min » **en dur**. Ici la ligne
/// affiche l'âge **réel** du cache. Hors ligne, elle devient « la vérité utile de
/// l'écran » : c'est elle qui dit si les compteurs affichés datent de deux
/// minutes ou de trois jours.
class EnTeteDeSalutation extends StatelessWidget {
  const EnTeteDeSalutation({
    required this.prenom,
    required this.serie,
    required this.luLe,
    required this.enLigne,
    super.key,
  });

  final String prenom;
  final SerieDeVigilance serie;
  final DateTime? luLe;

  /// Faux quand l'affichage vient du cache : le point passe alors à l'orange, et
  /// le texte le dit.
  final bool enLigne;

  @override
  Widget build(BuildContext context) {
    final Color point = enLigne
        ? VfColors.verdictTrue
        : VfColors.verdictInvestigate;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text('Bonjour $prenom 👋', style: VfTextStyles.hLg),
        const SizedBox(height: VfSpacing.x4 + 1),
        Text.rich(
          TextSpan(
            style: VfTextStyles.tSm,
            children: <InlineSpan>[
              const TextSpan(
                text:
                    'Votre boussole de l information est active. '
                    'Série actuelle : 🔥 ',
              ),
              TextSpan(
                text: serie.jours == 1 ? '1 jour' : '${serie.jours} jours',
                style: VfTextStyles.tSm.copyWith(
                  color: VfColors.ink,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const TextSpan(text: '.'),
            ],
          ),
        ),
        const SizedBox(height: VfSpacing.x6),
        Row(
          children: <Widget>[
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(color: point, shape: BoxShape.circle),
            ),
            const SizedBox(width: VfSpacing.x6),
            Flexible(
              child: Text(
                enLigne
                    ? 'Systeme operationnel · synchronise '
                          '${VfFormats.age(luLe)}'
                    : 'Hors ligne · derniere synchronisation '
                          '${VfFormats.age(luLe)}',
                style: VfTextStyles.tXs,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

/// La carte « Priorités ».
///
/// # Ce que le simulateur y met, et ce qui reste
///
/// Il y met deux lignes : « Un verdict vous attend », alimentée par **la
/// notification la plus récente**, et « Leçon en attente ».
///
/// La première n'a plus de source : il n'existe aucun magasin de notifications,
/// donc aucun état « lu / non lu » sur un verdict. La fabriquer à partir de la
/// dernière analyse terminée dirait « vous attend » d'un rapport peut-être déjà
/// lu — une affirmation que le client n'a pas les moyens de faire. **Elle
/// disparaît**, comme la documentation l'autorise explicitement.
///
/// La seconde vient de `GET /me/courses/resume`, qui est servi. Et quand il n'y
/// a rien à reprendre, **la carte disparaît entièrement** plutôt que d'afficher
/// un cadre nu.
class CarteDePriorites extends StatelessWidget {
  const CarteDePriorites({
    required this.reprise,
    required this.onOuvrirLeCours,
    super.key,
  });

  final RepriseDeCours? reprise;
  final void Function(String slug) onOuvrirLeCours;

  @override
  Widget build(BuildContext context) {
    final RepriseDeCours? r = reprise;
    if (r == null || r.prochaineLeconTitre == null) {
      return const SizedBox.shrink();
    }

    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const VfEyebrow('Priorites'),
          const SizedBox(height: VfSpacing.x8),
          Text('A traiter maintenant', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x9),
          _LignePriorite(
            icone: Icons.school_outlined,
            couleur: VfColors.info,
            titre: 'Leçon en attente',
            sousTitre: r.prochaineLeconTitre!,
            onTap: () => onOuvrirLeCours(r.coursSlug),
          ),
        ],
      ),
    );
  }
}

class _LignePriorite extends StatelessWidget {
  const _LignePriorite({
    required this.icone,
    required this.couleur,
    required this.titre,
    required this.sousTitre,
    required this.onTap,
  });

  final IconData icone;
  final Color couleur;
  final String titre;
  final String sousTitre;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    // La carte cliquable est un BOUTON ENTIER, pas une zone avec un chevron
    // cliquable (accessibilite de l'ecran 07).
    label: '$titre : $sousTitre',
    excludeSemantics: true,
    child: Material(
      color: Colors.transparent,
      borderRadius: VfRadii.mdAll,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(minHeight: 56),
          padding: const EdgeInsets.symmetric(
            horizontal: VfTouch.menuRowInlinePadding,
            vertical: VfSpacing.x10,
          ),
          decoration: BoxDecoration(
            borderRadius: VfRadii.mdAll,
            border: Border.all(color: VfColors.line),
          ),
          child: Row(
            children: <Widget>[
              Icon(icone, size: 20, color: couleur),
              const SizedBox(width: VfSpacing.x10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      titre,
                      style: VfTextStyles.tSm.copyWith(
                        color: VfColors.ink,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    Text(sousTitre, style: VfTextStyles.tXs),
                  ],
                ),
              ),
              const Icon(
                Icons.chevron_right_rounded,
                size: 20,
                color: VfColors.slate2,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

/// La carte « Offre du mois ».
///
/// **`cours_offert` vaut `null` quand il n'y a pas d'offre**, et ce n'est pas une
/// erreur : « annoncer une offre qui n'existe pas serait une promesse en l'air ».
/// La carte disparaît alors entièrement, elle ne s'affiche pas vide.
class CarteOffreDuMois extends StatelessWidget {
  const CarteOffreDuMois({
    required this.cours,
    required this.onOuvrir,
    super.key,
  });

  final CoursOffert cours;
  final VoidCallback onOuvrir;

  @override
  Widget build(BuildContext context) => VfCard(
    background: VfColors.primarySoft.withValues(alpha: 0.45),
    border: VfColors.primary.withValues(alpha: 0.4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: VfColors.primary,
            borderRadius: BorderRadius.circular(14),
          ),
          alignment: Alignment.center,
          child: const Text('🎁', style: TextStyle(fontSize: 21)),
        ),
        const SizedBox(width: VfSpacing.x12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              // Le sur-titre du simulateur porte le mois (« OFFRE DU MOIS —
              // Aout »). Le serveur ne le sert pas : `cours_offert` porte le
              // cours, pas la periode. On s'en tient a ce qui est servi.
              const VfEyebrow('Offre du mois'),
              const SizedBox(height: VfSpacing.x4 / 2),
              Text(
                'Cours offert : « ${cours.titre} »',
                style: VfTextStyles.hSm,
              ),
              const SizedBox(height: VfSpacing.x4),
              Text(
                'Chaque mois, un cours gratuit pour toute la communauté.',
                style: VfTextStyles.tXs,
              ),
              const SizedBox(height: VfSpacing.x10),
              VfPrimaryButton(
                label: 'S inscrire gratuitement',
                expanded: false,
                onPressed: onOuvrir,
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

/// La carte « Continuer votre apprentissage » — **deux états**.
///
/// POURQUOI elle ne dépend d'aucun code de l'Académie : « le tableau de bord ne
/// dépend pas de M3 ». Elle est alimentée par l'API et navigue **par nom de
/// route**, jamais par un import du dossier `features/lms/`.
///
/// Et le bouton **dit ce qui va se passer** : « Reprendre » sur un cours entamé,
/// « Découvrir » sur un catalogue jamais ouvert.
class CarteDeReprise extends StatelessWidget {
  const CarteDeReprise({
    required this.reprise,
    required this.onReprendre,
    required this.onDecouvrir,
    super.key,
  });

  final RepriseDeCours? reprise;
  final void Function(String slug) onReprendre;
  final VoidCallback onDecouvrir;

  @override
  Widget build(BuildContext context) {
    final RepriseDeCours? r = reprise;

    if (r == null) {
      return VfCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(
              'Continuer votre apprentissage'.toUpperCase(),
              style: VfTextStyles.eyebrow.copyWith(color: VfColors.slate2),
            ),
            const SizedBox(height: VfSpacing.x6),
            Text('Commencez votre premier cours', style: VfTextStyles.hSm),
            const SizedBox(height: VfSpacing.x4),
            Text(
              'Le catalogue vous attend : le premier cours est gratuit.',
              style: VfTextStyles.tXs,
            ),
            const SizedBox(height: VfSpacing.x12),
            VfPrimaryButton(
              label: 'Decouvrir le catalogue',
              onPressed: onDecouvrir,
            ),
          ],
        ),
      );
    }

    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            'Continuer votre apprentissage'.toUpperCase(),
            style: VfTextStyles.eyebrow.copyWith(color: VfColors.slate2),
          ),
          const SizedBox(height: VfSpacing.x6),
          Text(r.coursTitre, style: VfTextStyles.hSm),
          if (r.prochaineLeconTitre != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x4),
            Text(
              'Prochaine leçon : ${r.prochaineLeconTitre}',
              style: VfTextStyles.tXs,
            ),
          ],
          const SizedBox(height: VfSpacing.x10 + 1),
          // L'avancement vient du serveur : le recalculer donnerait deux
          // pourcentages pour un meme cours sur deux ecrans voisins.
          VfProgressBar(
            value: r.avancement / 100,
            semanticsLabel: 'Avancement du cours',
          ),
          const SizedBox(height: VfSpacing.x6),
          Text('${r.avancement} % termine', style: VfTextStyles.tXs),
          const SizedBox(height: VfSpacing.x12),
          VfPrimaryButton(
            label: 'Reprendre',
            onPressed: () => onReprendre(r.coursSlug),
          ),
        ],
      ),
    );
  }
}

/// L'histogramme « Mon activité sur 30 jours ».
///
/// # Une seule série, et la légende le dit
///
/// Le simulateur en dessine deux — Vérifications (vert) et Leçons terminées
/// (bleu) — avec vingt barres tirées d'un tableau écrit en dur. L'agrégat sert
/// **une** série : `activite.valeurs`, le nombre de vérifications par jour. Une
/// courbe de leçons n'existe nulle part dans la réponse, et l'inventer serait
/// fabriquer un chiffre.
///
/// # Les jours vides restent à zéro
///
/// « Un graphique qui saute les jours vides ment sur la régularité, qui est
/// justement ce qu'il montre » (§10.6). Le serveur les renvoie ; on les dessine.
class HistogrammeDActivite extends StatelessWidget {
  const HistogrammeDActivite({required this.activite, super.key});

  final ActiviteTrenteJours activite;

  @override
  Widget build(BuildContext context) {
    final String? jourMax = activite.jourDuMaximum;

    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text('Mon activité sur 30 jours', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x10 + 1),
          if (activite.estVide)
            Text(
              'Aucune vérification sur les trente derniers jours.',
              style: VfTextStyles.tXs,
            )
          else
            Semantics(
              // « L'histogramme porte un resume textuel : vingt barres non
              // etiquetees ne se lisent pas. »
              label: jourMax == null
                  ? 'Activité sur 30 jours'
                  : 'Activité sur 30 jours, maximum le $jourMax avec '
                        '${activite.maximum} vérification(s)',
              excludeSemantics: true,
              child: SizedBox(
                height: 84,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: <Widget>[
                    for (int i = 0; i < activite.valeurs.length; i++)
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 1.5),
                          child: FractionallySizedBox(
                            alignment: Alignment.bottomCenter,
                            heightFactor: activite.maximum == 0
                                ? 0.02
                                : (activite.valeurs[i] / activite.maximum)
                                      .clamp(0.02, 1.0),
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                color: VfColors.primary,
                                borderRadius: BorderRadius.circular(3),
                              ),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: VfSpacing.x10),
          Row(
            children: <Widget>[
              Container(
                width: 11,
                height: 11,
                decoration: BoxDecoration(
                  color: VfColors.primary,
                  borderRadius: BorderRadius.circular(3),
                ),
              ),
              const SizedBox(width: VfSpacing.x6),
              Text('Vérifications', style: VfTextStyles.tXs),
            ],
          ),
        ],
      ),
    );
  }
}
