import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/course_enrollment.dart';
import '../../../../domain/entities/course_summary.dart';
import '../../../shared/shared.dart';

/// La carte d'un cours du catalogue.
///
/// # Ce que le simulateur montre et qui n'existe pas
///
/// Le simulateur affiche « ★ 4,8 » et « 1 245 inscrits ». **Ni la note ni le
/// nombre d'inscrits ne sont sérialisés par l'API** : les colonnes existent en
/// base, `CoursResource` ne les rend pas, et « ce qui n'y est pas n'existe
/// pas ».
///
/// La carte affiche donc **« Nouveau »** (arbitrage n° 61) et la ligne perd le
/// nombre d'inscrits (arbitrage n° 67 : les chiffres sont comptés ou retirés).
/// Une étoile fabriquée serait une note que personne n'a donnée.
///
/// # L'accessibilité n'est pas un détail ici
///
/// « Chaque carte est un unique bouton dont le libellé compose titre, niveau,
/// prix et progression. Les trois pastilles seules ne se lisent pas. »
class CourseCard extends StatelessWidget {
  const CourseCard({
    required this.cours,
    required this.onTap,
    this.inscription,
    this.disponibleHorsLigne = false,
    super.key,
  });

  final CourseSummary cours;
  final CourseEnrollment? inscription;
  final VoidCallback onTap;

  /// Le cours a été téléchargé : la carte le dit, pour qu'on sache quoi ouvrir
  /// dans un taxi.
  final bool disponibleHorsLigne;

  @override
  Widget build(BuildContext context) {
    final int? avancement = inscription?.avancement;

    return Semantics(
      button: true,
      label: _libelleComplet(avancement),
      excludeSemantics: true,
      child: VfCard(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            CoverAvecLibelle(
              cleDeDegrade: cours.slug,
              imageUrl: cours.couvertureUrl,
              libelle: cours.categorie,
            ),
            const SizedBox(height: 11),
            Row(
              children: <Widget>[
                if (cours.niveau.estRenseigne) ...<Widget>[
                  VfTag(label: cours.niveau.libelle),
                  const SizedBox(width: VfSpacing.x6),
                ],
                VfTag(
                  label: cours.estGratuit
                      ? 'Gratuit'
                      : '${cours.coutUnites} unités',
                  variant: cours.estGratuit
                      ? VfTagVariant.prim
                      : VfTagVariant.neutre,
                ),
                const Spacer(),
                // « Nouveau » et pas une etoile : aucun avis n'est servi.
                const VfTag(label: 'Nouveau'),
              ],
            ),
            const SizedBox(height: VfSpacing.x6),
            Text(
              cours.titre,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: VfTextStyles.tMd.copyWith(
                color: VfColors.ink,
                fontWeight: FontWeight.w700,
                fontFamily: VfFonts.display,
              ),
            ),
            const SizedBox(height: 5),
            Text(_ligneMeta(), style: VfTextStyles.tXs),
            if (disponibleHorsLigne) ...<Widget>[
              const SizedBox(height: VfSpacing.x6),
              Row(
                children: <Widget>[
                  const Icon(
                    Icons.download_done_rounded,
                    size: 14,
                    color: VfColors.primaryDark,
                  ),
                  const SizedBox(width: VfSpacing.x4),
                  Text(
                    'Disponible hors ligne',
                    style: VfTextStyles.tXs.copyWith(
                      color: VfColors.primaryDark,
                    ),
                  ),
                ],
              ),
            ],
            if (avancement != null) ...<Widget>[
              const SizedBox(height: VfSpacing.x10),
              VfProgressBar(value: avancement / 100),
              const SizedBox(height: 5),
              Text('$avancement % termine', style: VfTextStyles.tXs),
            ],
          ],
        ),
      ),
    );
  }

  /// « `<durée>` · `<n>` étapes ».
  ///
  /// POURQUOI « étapes » et pas « leçons » : la liste du catalogue ne porte pas
  /// les leçons (le contenu entier du cours partirait à chaque ouverture), et
  /// `nombre_d_etapes` compte leçons **et** quiz. Écrire « 6 leçons » quand le
  /// serveur a compté 5 leçons et 1 quiz serait un chiffre faux.
  String _ligneMeta() {
    final List<String> morceaux = <String>[
      if (cours.duree.isNotEmpty) cours.duree,
      if (cours.nombreDEtapes > 0) '${cours.nombreDEtapes} étapes',
    ];
    return morceaux.join(' · ');
  }

  String _libelleComplet(int? avancement) {
    final List<String> morceaux = <String>[
      cours.titre,
      if (cours.niveau.estRenseigne) cours.niveau.libelle,
      cours.estGratuit ? 'gratuit' : '${cours.coutUnites} unités',
      if (avancement != null) '$avancement % termine',
      if (disponibleHorsLigne) 'disponible hors ligne',
    ];
    return morceaux.join(', ');
  }
}

/// La carte « Continuer », bordée de vert.
///
/// Elle n'apparaît **que** si `GET /me/courses/resume` a rendu quelque chose :
/// l'endpoint rend `data: null` quand il n'y a rien à reprendre, et « ce n'est
/// pas une erreur, c'est un état vide ».
class ResumeCard extends StatelessWidget {
  const ResumeCard({
    required this.reprise,
    required this.onReprendre,
    super.key,
  });

  final CourseResume reprise;
  final VoidCallback onReprendre;

  @override
  Widget build(BuildContext context) {
    final int avancement = reprise.inscription.avancement;
    final String prochaine = reprise.prochaineLecon?.titre ?? 'Quiz final';

    return VfCard(
      border: VfColors.primary.withValues(alpha: 0.4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const VfEyebrow('Continuer'),
          const SizedBox(height: VfSpacing.x6),
          Text(reprise.cours.titre, style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x4),
          Text(
            '$avancement % · prochaine leçon : $prochaine',
            style: VfTextStyles.tXs,
          ),
          const SizedBox(height: VfSpacing.x10),
          VfProgressBar(value: avancement / 100),
          const SizedBox(height: VfSpacing.x12),
          VfPrimaryButton(
            label: 'Reprendre le cours',
            // « Un bouton dit ce qui va se passer » : le libelle lu par le
            // lecteur d'ecran nomme la leçon, pas seulement l'action.
            semanticsLabel: 'Reprendre le cours a $prochaine',
            expanded: true,
            onPressed: onReprendre,
          ),
        ],
      ),
    );
  }
}

/// La carte « Votre progression ».
///
/// # Deux éléments du simulateur qui disparaissent, et pourquoi
///
/// - **La pastille XP.** Aucun endpoint de l'Académie ne rend un total d'XP ;
///   il vit dans `GET /me/gamification`, qui appartient aux écrans de M5.
///   L'afficher ici obligerait à en dupliquer le modèle dans deux parcours, et
///   « un composant dupliqué dans deux dossiers diverge en une semaine ».
/// - **La barre sur l'ensemble du catalogue.** Le catalogue est paginé **par
///   curseur** : « on ne peut pas sauter à la page 7, et il n'y a pas de
///   total » (contrat §3.1). Le dénominateur n'existe donc pas. La barre porte
///   ici sur **les cours suivis**, ce qui est un nombre compté, et le libellé le
///   dit.
class ProgressionSummaryCard extends StatelessWidget {
  const ProgressionSummaryCard({required this.inscriptions, super.key});

  final List<CourseEnrollment> inscriptions;

  @override
  Widget build(BuildContext context) {
    final int termines = inscriptions
        .where((CourseEnrollment i) => i.estTerminee)
        .length;
    final int enCours = inscriptions.length - termines;
    final double part = inscriptions.isEmpty
        ? 0
        : termines / inscriptions.length;

    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const VfEyebrow('Votre progression'),
          const SizedBox(height: VfSpacing.x8),
          Text(
            '$termines termine${termines > 1 ? 's' : ''} · $enCours en cours',
            style: VfTextStyles.hSm,
          ),
          const SizedBox(height: 9),
          VfProgressBar(
            value: part,
            semanticsLabel:
                '$termines cours termines sur ${inscriptions.length} suivis',
          ),
          const SizedBox(height: VfSpacing.x6),
          Text(
            inscriptions.isEmpty
                ? 'Vous ne suivez encore aucun cours.'
                : 'Sur ${inscriptions.length} cours suivis',
            style: VfTextStyles.tXs,
          ),
        ],
      ),
    );
  }
}

/// Une couverture `VfCover` avec un libellé centré par-dessus.
///
/// POURQUOI ce petit assemblage plutôt qu'un paramètre de plus sur `VfCover` :
/// `VfCover` appartient au **socle**, et « un besoin de modification du socle
/// est une demande, pas une modification » (§8.1, règle 2). Le composant expose
/// déjà `overlay` ; on s'en sert.
class CoverAvecLibelle extends StatelessWidget {
  const CoverAvecLibelle({
    required this.cleDeDegrade,
    this.libelle,
    this.enfant,
    this.imageUrl,
    this.hauteurSurchargee,
    super.key,
  });

  final String cleDeDegrade;

  /// Le texte centré sur la couverture — la catégorie au catalogue, le titre du
  /// cours sur sa fiche.
  final String? libelle;

  /// Ce qui remplace le texte quand la couverture sert d'affiche à autre chose
  /// (le lecteur vidéo d'une leçon).
  final Widget? enfant;

  final String? imageUrl;

  /// La fiche d'un cours porte une couverture de 140 (écran 15) ; le catalogue
  /// garde la hauteur du composant.
  final double? hauteurSurchargee;

  @override
  Widget build(BuildContext context) {
    return VfCover(
      gradientKey: cleDeDegrade,
      imageUrl: imageUrl,
      // Le socle porte desormais la hauteur : plus de `SizedBox` autour du
      // composant (arbitrage n° 134).
      height: hauteurSurchargee,
      overlay: Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: VfSpacing.x12),
          child:
              enfant ??
              Text(
                libelle ?? '',
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: VfTextStyles.hSm.copyWith(color: VfColors.surface),
              ),
        ),
      ),
    );
  }
}
