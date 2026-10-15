import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/semantics.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_motion.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/certificate_summary.dart';
import '../../../../domain/entities/quiz_attempt.dart';
import '../../../../domain/entities/quiz_summary.dart';
import '../../../../domain/usecases/lms/machine_du_quiz.dart';
import '../../../shared/shared.dart';
import '../providers/course_provider.dart';
import '../providers/quiz_controller.dart';
import '../widgets/lms_banners.dart';
import '../widgets/quiz_widgets.dart';

/// Écran 17 — Quiz (`#/quiz`).
///
/// # Réserve C.1, tranchée : le corrigé s'affiche À LA COPIE
///
/// Le simulateur marque juste/faux et déroule la carte XAI **après chaque
/// réponse**. Le contrat l'interdit : les questions arrivent **sans corrigé**,
/// et `is_correct` comme l'explication n'existent que dans `data.corrige[]` de
/// la copie rendue — « c'est le seul moment où elles franchissent le réseau ».
///
/// **Décision retenue** : l'option 1 de la réserve. Une question à la fois,
/// **sans marque ni XAI pendant le parcours**, et le corrigé **déroulé sur
/// l'écran de résultat**, question par question, avec chaque explication. La
/// pédagogie est préservée ; le moment change. Consigné dans `ARBITRAGES.md`.
///
/// # Deux autres règles portées ici
///
/// - **Le chronomètre s'appuie sur `meta.commence_le`**, l'heure du serveur.
///   L'horloge locale ne sert qu'à mesurer l'écart depuis cette heure-là.
/// - **Le résultat ne vient jamais du cache** : il n'y a aucune table pour lui.
class QuizPage extends ConsumerStatefulWidget {
  const QuizPage({required this.slug, super.key});

  final String slug;

  @override
  ConsumerState<QuizPage> createState() => _QuizPageState();
}

class _QuizPageState extends ConsumerState<QuizPage> {
  Timer? _battement;
  DateTime _maintenant = DateTime.now();
  int _dernierPalierAnnonce = 1 << 30;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(
      () => ref.read(quizControllerProvider.notifier).charger(widget.slug),
    );
    _battement = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) {
        setState(() => _maintenant = DateTime.now());
      }
    });
  }

  @override
  void dispose() {
    _battement?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final EtatDuQuiz etat = ref.watch(quizControllerProvider);
    _surveillerLeChronometre(etat);

    return Scaffold(
      appBar: VfAppBar(title: _titre(etat)),
      body: switch (etat.phase) {
        PhaseDuQuiz.chargement => const _SqueletteDeQuiz(),
        PhaseDuQuiz.echec => _Echec(etat: etat, slug: widget.slug),
        PhaseDuQuiz.question || PhaseDuQuiz.envoi => _Question(
          etat: etat,
          slug: widget.slug,
          maintenant: _maintenant,
        ),
        PhaseDuQuiz.enAttenteDeReseau => _EnAttente(slug: widget.slug),
        PhaseDuQuiz.resultat => _Resultat(etat: etat, slug: widget.slug),
      },
    );
  }

  String _titre(EtatDuQuiz etat) => switch (etat.phase) {
    PhaseDuQuiz.question || PhaseDuQuiz.envoi =>
      etat.questions.isEmpty
          ? 'Quiz'
          : 'Question ${etat.indexCourant + 1}/${etat.questions.length}',
    PhaseDuQuiz.resultat => 'Résultat',
    _ => 'Quiz',
  };

  /// Le chronomètre : expiration automatique, et annonce **aux paliers**.
  ///
  /// « Le chronomètre est annoncé aux paliers (5 min, 1 min, 30 s), pas en
  /// continu » : un compte à rebours lu chaque seconde rend l'écran
  /// inutilisable au lecteur d'écran.
  void _surveillerLeChronometre(EtatDuQuiz etat) {
    if (etat.phase != PhaseDuQuiz.question) {
      return;
    }
    final Duration? reste = etat.tempsRestant(_maintenant);
    if (reste == null) {
      return;
    }
    if (reste == Duration.zero) {
      // « A l'expiration, la copie se rend vide : le contrat accepte
      // explicitement un tableau de reponses vide. Le client n'invente aucun
      // echec, il envoie ce qu'il a. »
      Future<void>.microtask(
        () => ref
            .read(quizControllerProvider.notifier)
            .rendreLaCopie(coursSlug: widget.slug, tempsEcoule: true),
      );
      return;
    }
    for (final int palier in TimerChip.paliersEnSecondes) {
      if (reste.inSeconds <= palier && _dernierPalierAnnonce > palier) {
        _dernierPalierAnnonce = palier;
        // `sendAnnouncement` et non `announce` : cette derniere est depreciee
        // et « incompatible avec plusieurs fenetres ».
        SemanticsService.sendAnnouncement(
          View.of(context),
          'Il reste ${_libellePalier(palier)}',
          Directionality.of(context),
        );
        break;
      }
    }
  }

  static String _libellePalier(int secondes) => switch (secondes) {
    300 => 'cinq minutes',
    60 => 'une minute',
    _ => 'trente secondes',
  };
}

class _Question extends ConsumerWidget {
  const _Question({
    required this.etat,
    required this.slug,
    required this.maintenant,
  });

  final EtatDuQuiz etat;
  final String slug;
  final DateTime maintenant;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final QuizQuestion? question = etat.questionCourante;
    if (question == null) {
      return const _SqueletteDeQuiz();
    }
    final Duration? reste = etat.tempsRestant(maintenant);
    final int? choix = etat.choixCourant;

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        VfProgressBar(
          value: (etat.indexCourant + 1) / etat.questions.length,
          thin: true,
        ),
        const SizedBox(height: VfSpacing.stack),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: <Widget>[
            VfTag(label: etat.quiz?.quiz.titre ?? 'Quiz'),
            // Pas de chronometre quand `meta.commence_le` manque : il ne se
            // replie JAMAIS sur l'horloge du telephone.
            if (reste != null) TimerChip(reste: reste),
          ],
        ),
        const SizedBox(height: VfSpacing.stack),
        Text(question.intitule, style: VfTextStyles.hMd),
        const SizedBox(height: VfSpacing.stack),
        for (int i = 0; i < question.reponses.length; i++) ...<Widget>[
          QuizOption(
            lettre: String.fromCharCode(65 + i),
            libelle: question.reponses[i].libelle,
            // Deux etats seulement pendant l'epreuve : neutre ou choisie. Le
            // client ne sait pas ce qui est juste, donc il ne le montre pas.
            etat: choix == question.reponses[i].id
                ? EtatDOption.choisie
                : EtatDOption.neutre,
            onTap: etat.phase == PhaseDuQuiz.envoi
                ? null
                : () => ref
                      .read(quizControllerProvider.notifier)
                      .repondre(question.uuid, question.reponses[i].id),
          ),
          const SizedBox(height: VfSpacing.stackSm),
        ],
        const SizedBox(height: VfSpacing.x8),
        if (etat.estDerniereQuestion)
          VfPrimaryButton(
            label: 'Rendre ma copie',
            expanded: true,
            busy: etat.phase == PhaseDuQuiz.envoi,
            onPressed: etat.phase == PhaseDuQuiz.envoi
                ? null
                : () => ref
                      .read(quizControllerProvider.notifier)
                      .rendreLaCopie(coursSlug: slug),
          )
        else
          VfPrimaryButton(
            label: 'Question suivante',
            expanded: true,
            onPressed: () =>
                ref.read(quizControllerProvider.notifier).questionSuivante(),
          ),
        if (etat.indexCourant > 0) ...<Widget>[
          const SizedBox(height: VfSpacing.stackSm),
          VfGhostButton(
            label: 'Question precedente',
            expanded: true,
            onPressed: () =>
                ref.read(quizControllerProvider.notifier).questionPrecedente(),
          ),
        ],
      ],
    );
  }
}

/// La phase de résultat.
///
/// Le score en grand est **celui du serveur**. Le nombre de bonnes réponses est
/// compté en comparant deux valeurs que le serveur a fournies — le corrigé et
/// les choix envoyés —, jamais par un barème local.
class _Resultat extends ConsumerWidget {
  const _Resultat({required this.etat, required this.slug});

  final EtatDuQuiz etat;
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final QuizAttemptResult? resultat = etat.resultat;
    if (resultat == null) {
      return const _SqueletteDeQuiz();
    }
    final bool reussi = resultat.reussi;
    final int bonnes = resultat.bonnesReponses(etat.reponses);
    final int seuil = etat.quiz?.quiz.scoreRequis ?? 0;
    final AsyncValue<CertificateSummary?> certificat = ref.watch(
      certificatDuCoursProvider(slug),
    );

    final Widget corps = ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        VfCard(
          child: Column(
            children: <Widget>[
              Container(
                width: 70,
                height: 70,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: (reussi ? VfColors.verdictTrue : VfColors.verdictFalse)
                      .withValues(alpha: 0.12),
                  borderRadius: VfRadii.pillAll,
                ),
                child: Icon(
                  reussi
                      ? Icons.workspace_premium_rounded
                      : Icons.refresh_rounded,
                  size: 36,
                  color: reussi ? VfColors.verdictTrue : VfColors.verdictFalse,
                ),
              ),
              const SizedBox(height: 13),
              Text(
                reussi ? 'Quiz réussi !' : 'Encore un effort',
                style: VfTextStyles.hLg,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 7),
              Text(
                '$bonnes bonnes réponses sur ${etat.questions.length} · '
                'seuil $seuil %',
                style: VfTextStyles.tSm,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: VfSpacing.x12),
              Text(
                '${resultat.score} %',
                style: TextStyle(
                  fontFamily: VfFonts.display,
                  fontSize: 46,
                  fontWeight: FontWeight.w800,
                  color: reussi ? VfColors.verdictTrue : VfColors.verdictFalse,
                ),
              ),
              if (reussi && (etat.quiz?.quiz.xp ?? 0) > 0) ...<Widget>[
                const SizedBox(height: VfSpacing.x12),
                // Le simulateur ajoute « +5 unites ». Aucun champ du contrat ne
                // porte ce credit : il disparait plutot que d'annoncer un gain
                // que le serveur n'a pas confirme (arbitrage n° 67).
                VfTag(
                  label: '+${etat.quiz!.quiz.xp} XP',
                  variant: VfTagVariant.xp,
                ),
              ],
              if (etat.tempsEcouleAvantEnvoi) ...<Widget>[
                const SizedBox(height: VfSpacing.x12),
                Text(
                  'Le temps etait ecoule : la copie est partie telle quelle.',
                  style: VfTextStyles.tXs,
                  textAlign: TextAlign.center,
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: VfSpacing.stack),
        if (reussi && certificat.value != null)
          VfPrimaryButton(
            label: 'Voir mon certificat',
            expanded: true,
            onPressed: () =>
                context.push(VfRoutes.certificate(certificat.value!.code)),
          )
        else if (reussi)
          VfPrimaryButton(
            label: 'Continuer',
            expanded: true,
            onPressed: () => context.go(VfRoutes.course(slug)),
          )
        else
          VfPrimaryButton(
            label: 'Réessayer le quiz',
            expanded: true,
            // « Les tentatives sont volontairement multiples » (arbitrage
            // n° 40) : garder l'historique permet de dire « vous progressez »
            // plutot que « vous avez echoue ».
            onPressed: () =>
                ref.read(quizControllerProvider.notifier).recommencer(slug),
          ),
        const SizedBox(height: VfSpacing.stackSm),
        VfGhostButton(
          label: 'Retour au cours',
          expanded: true,
          onPressed: () => context.go(VfRoutes.course(slug)),
        ),
        const SizedBox(height: VfSpacing.x22),
        Text('Le corrige', style: VfTextStyles.hMd),
        const SizedBox(height: VfSpacing.x6),
        Text(
          'Les explications arrivent avec la copie corrigee : elles ne '
          'circulent qu une fois, après l epreuve.',
          style: VfTextStyles.tXs,
        ),
        const SizedBox(height: VfSpacing.stack),
        for (int i = 0; i < etat.questions.length; i++) ...<Widget>[
          CorrigeDeQuestion(
            question: etat.questions[i],
            correction: _correctionDe(etat.questions[i].uuid, resultat),
            choix: etat.reponses[etat.questions[i].uuid],
            numero: i + 1,
          ),
          const SizedBox(height: VfSpacing.x22),
        ],
      ],
    );

    // Confettis a la reussite, JAMAIS a l'echec — et coupes par
    // `disableAnimations` (§2.6 : « un oubli signifie ici qu'une personne
    // sujette au mal des transports ferme l'application »).
    if (!reussi || VfMotion.reducedMotion(context)) {
      return corps;
    }
    return Stack(
      children: <Widget>[
        corps,
        const Positioned.fill(child: IgnorePointer(child: VfConfetti())),
      ],
    );
  }

  static QuizCorrection? _correctionDe(
    String questionUuid,
    QuizAttemptResult resultat,
  ) {
    for (final QuizCorrection c in resultat.corrige) {
      if (c.questionUuid == questionUuid) {
        return c;
      }
    }
    return null;
  }
}

/// La copie est **en file**.
///
/// « Le résultat n'arrive qu'au retour du réseau, puisque la correction est
/// serveur. L'écran affiche "Résultat en attente de connexion", jamais un score
/// calculé localement. »
class _EnAttente extends StatelessWidget {
  const _EnAttente({required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          EncartReseauRequis(
            titre: 'Résultat en attente de connexion',
            raison:
                'Votre copie est enregistree et partira des le retour du '
                'réseau. La correction est faite par le serveur : aucun score '
                'ne peut être calcule sur le telephone.',
            icone: Icons.cloud_upload_outlined,
            action: VfGhostButton(
              label: 'Retour au cours',
              onPressed: () => context.go(VfRoutes.course(slug)),
            ),
          ),
          const SizedBox(height: VfSpacing.stack),
          Text(
            'A noter : le chronometre du serveur a continue de courir. Une '
            'copie rendue longtemps après pourra être hors delai — c est le '
            'serveur qui tranchera.',
            style: VfTextStyles.tXs,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}

class _Echec extends ConsumerWidget {
  const _Echec({required this.etat, required this.slug});

  final EtatDuQuiz etat;
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Padding(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          EncartReseauRequis(
            titre: 'Le quiz n a pas pu s ouvrir',
            // Le message du SERVEUR quand il y en a un.
            raison:
                etat.messageDErreur ??
                'Le quiz demande une connexion : les questions ne sont pas '
                    'mises en cache, et le chronometre part de l heure du '
                    'serveur.',
            // `Expanded` sur chacun : les boutons du design system prennent
            // toute la largeur par defaut, et deux d'entre eux dans une `Row`
            // sans contrainte de largeur produisent une mise en page infinie.
            action: Row(
              children: <Widget>[
                Expanded(
                  child: VfGhostButton(
                    label: 'Réessayer',
                    onPressed: () =>
                        ref.read(quizControllerProvider.notifier).charger(slug),
                  ),
                ),
                const SizedBox(width: VfSpacing.x8),
                Expanded(
                  child: VfGhostButton(
                    label: 'Retour au cours',
                    onPressed: () => context.go(VfRoutes.course(slug)),
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

class _SqueletteDeQuiz extends StatelessWidget {
  const _SqueletteDeQuiz();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        VfSkeleton(height: 6, width: double.infinity),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 52, width: double.infinity),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 52, width: double.infinity),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 52, width: double.infinity),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 52, width: double.infinity),
      ],
    );
  }
}
