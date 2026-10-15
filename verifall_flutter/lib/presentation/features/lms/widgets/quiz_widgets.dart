import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/quiz_attempt.dart';
import '../../../../domain/entities/quiz_summary.dart';
import 'lms_banners.dart';
import '../../../../core/format/vf_formats.dart';

/// Une option de réponse.
///
/// # Ce que cette option ne fait JAMAIS : dire si elle est juste
///
/// Pendant le quiz, [etat] vaut toujours [EtatDOption.neutre] ou
/// [EtatDOption.choisie]. Les deux états `juste` et `fausse` ne sont employés
/// que sur **l'écran de résultat**, après que le serveur a rendu `data.corrige[]`.
///
/// C'est la réserve C.1, tranchée : le contrat ne livre le corrigé qu'à la
/// copie — « un quiz réussi avec un intercepteur de trafic dévalue tous les
/// certificats ». Le client ne sait pas, donc il ne colorie pas.
enum EtatDOption { neutre, choisie, juste, fausse }

class QuizOption extends StatelessWidget {
  const QuizOption({
    required this.lettre,
    required this.libelle,
    required this.etat,
    required this.onTap,
    super.key,
  });

  /// A, B, C, D — la pastille ronde du simulateur.
  final String lettre;
  final String libelle;
  final EtatDOption etat;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final (Color bordure, Color fond, Color pastille) = switch (etat) {
      EtatDOption.neutre => (VfColors.line, VfColors.surface, VfColors.line),
      EtatDOption.choisie => (
        VfColors.primary,
        VfColors.primarySoft,
        VfColors.primary,
      ),
      EtatDOption.juste => (
        VfColors.verdictTrue,
        VfColors.primarySoft,
        VfColors.verdictTrue,
      ),
      EtatDOption.fausse => (
        VfColors.verdictFalse,
        VfColors.surface,
        VfColors.verdictFalse,
      ),
    };

    return Semantics(
      button: onTap != null,
      selected: etat == EtatDOption.choisie,
      label: _libelleLu(),
      excludeSemantics: true,
      child: InkWell(
        borderRadius: VfRadii.mdAll,
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(minHeight: 52),
          padding: const EdgeInsets.all(VfSpacing.x12),
          decoration: BoxDecoration(
            color: fond,
            borderRadius: VfRadii.mdAll,
            border: Border.all(color: bordure),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 26,
                height: 26,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: pastille,
                  borderRadius: VfRadii.pillAll,
                ),
                child: Text(
                  lettre,
                  style: VfTextStyles.tXs.copyWith(
                    color: etat == EtatDOption.neutre
                        ? VfColors.slate2
                        : VfColors.surface,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: VfSpacing.x10),
              Expanded(
                child: Text(
                  libelle,
                  style: VfTextStyles.tSm.copyWith(color: VfColors.ink),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// « Après réponse, l'état juste/faux est annoncé **en mots** » — une couleur
  /// seule ne se lit pas.
  String _libelleLu() => switch (etat) {
    EtatDOption.neutre => '$lettre, $libelle',
    EtatDOption.choisie => '$lettre, $libelle, choisie',
    EtatDOption.juste => '$lettre, $libelle, bonne réponse',
    EtatDOption.fausse => '$lettre, $libelle, réponse fausse',
  };
}

/// La pastille du chronomètre.
///
/// L'heure de référence est **`meta.commence_le`**, renvoyée par le serveur.
/// Quand elle manque, la pastille **ne s'affiche pas** : elle ne se replie pas
/// sur l'horloge du téléphone.
class TimerChip extends StatelessWidget {
  const TimerChip({required this.reste, super.key});

  final Duration reste;

  /// Les paliers annoncés au lecteur d'écran — 5 min, 1 min, 30 s —, « pas en
  /// continu » : un chronomètre lu chaque seconde rend l'écran inutilisable.
  static const List<int> paliersEnSecondes = <int>[300, 60, 30];

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Temps restant ${VfFormats.chronometre(reste)}',
      excludeSemantics: true,
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: VfSpacing.x10,
          vertical: VfSpacing.x4,
        ),
        decoration: BoxDecoration(
          color: VfColors.line,
          borderRadius: VfRadii.pillAll,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(
              Icons.timer_outlined,
              size: 14,
              color: VfColors.verdictInvestigate,
            ),
            const SizedBox(width: VfSpacing.x4),
            Text(
              VfFormats.chronometre(reste),
              style: VfTextStyles.tXs.copyWith(
                color: VfColors.verdictInvestigate,
                fontWeight: FontWeight.w700,
                fontFeatures: const <FontFeature>[FontFeature.tabularFigures()],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Le corrigé **déroulé sur l'écran de résultat** — la forme retenue par la
/// réserve C.1.
///
/// Une carte par question : l'énoncé, les options marquées d'après
/// `data.corrige[]`, et l'explication du serveur dans l'encart pédagogique du
/// simulateur (icône `psychology`, « Pourquoi cette réponse »).
class CorrigeDeQuestion extends StatelessWidget {
  const CorrigeDeQuestion({
    required this.question,
    required this.correction,
    required this.choix,
    required this.numero,
    super.key,
  });

  final QuizQuestion question;
  final QuizCorrection? correction;

  /// Ce que la personne avait choisi, ou `null` si elle n'a pas répondu — « une
  /// question sans réponse compte comme fausse », et l'écran le dit.
  final int? choix;

  final int numero;

  @override
  Widget build(BuildContext context) {
    final int? bonne = correction?.bonneReponseId;
    final String? explication = correction?.explication;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'Question $numero',
          style: VfTextStyles.eyebrow.copyWith(color: VfColors.slate2),
        ),
        const SizedBox(height: VfSpacing.x4),
        Text(question.intitule, style: VfTextStyles.hSm),
        const SizedBox(height: VfSpacing.x10),
        for (int i = 0; i < question.reponses.length; i++) ...<Widget>[
          QuizOption(
            lettre: String.fromCharCode(65 + i),
            libelle: question.reponses[i].libelle,
            etat: _etat(question.reponses[i].id, bonne),
            onTap: null,
          ),
          const SizedBox(height: VfSpacing.x8),
        ],
        if (choix == null)
          Padding(
            padding: const EdgeInsets.only(bottom: VfSpacing.x8),
            child: Text(
              'Vous n avez pas repondu a cette question.',
              style: VfTextStyles.tXs,
            ),
          ),
        if (explication != null && explication.isNotEmpty)
          EncartPedagogique(
            icone: Icons.psychology_rounded,
            titre: 'Pourquoi cette réponse',
            texte: explication,
          ),
      ],
    );
  }

  EtatDOption _etat(int idReponse, int? bonne) {
    if (bonne != null && idReponse == bonne) {
      return EtatDOption.juste;
    }
    if (choix == idReponse) {
      return EtatDOption.fausse;
    }
    return EtatDOption.neutre;
  }
}
