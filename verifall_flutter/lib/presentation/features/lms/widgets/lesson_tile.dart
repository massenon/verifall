import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/lesson_summary.dart';
import '../../../../domain/entities/quiz_summary.dart';

/// Une ligne du programme d'un cours.
///
/// # Le déverrouillage est LU, jamais recalculé
///
/// L'état vient de `data.leçons[].ouverte` et `.acquise`. « C'est une règle
/// métier ; un client qui la recalcule ouvrirait un jour une leçon qui ne
/// devait pas l'être » (table d'arbitrage §7.4).
///
/// # Un élément verrouillé affiche sa CONDITION
///
/// « Un cadenas nu est une frustration gratuite. La ligne doit dire pourquoi. »
/// C'est aussi un point de la définition de fini de M3, et un test dédié le
/// vérifie : **un cadenas sans phrase doit faire échouer un test**.
class LessonTile extends StatelessWidget {
  const LessonTile({
    required this.lecon,
    required this.numero,
    required this.onTap,
    this.disponibleHorsLigne = false,
    this.horsLigne = false,
    super.key,
  });

  final LessonSummary lecon;

  /// Le rang affiché, 1..N.
  final int numero;

  final VoidCallback onTap;

  /// Le contenu de cette leçon est déjà dans le cache local.
  final bool disponibleHorsLigne;

  /// L'application est en lecture de cache : une leçon non téléchargée porte
  /// « Disponible avec une connexion », **pas un cadenas** — le verrou est une
  /// décision du serveur, l'absence de réseau n'en est pas une.
  final bool horsLigne;

  bool get _bloqueeParLeReseau =>
      horsLigne && !disponibleHorsLigne && !lecon.estVerrouillee;

  bool get _activable => !lecon.estVerrouillee && !_bloqueeParLeReseau;

  @override
  Widget build(BuildContext context) {
    final String? condition = _condition();

    return Semantics(
      button: _activable,
      enabled: _activable,
      label: _libelle(condition),
      excludeSemantics: true,
      child: InkWell(
        onTap: _activable ? onTap : null,
        child: Opacity(
          opacity: _activable ? 1 : 0.5,
          child: Container(
            constraints: const BoxConstraints(
              minHeight: VfTouch.menuRowMinHeight,
            ),
            padding: const EdgeInsets.symmetric(
              horizontal: VfTouch.menuRowInlinePadding,
              vertical: VfTouch.menuRowVerticalPadding,
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Icon(_icone(), size: 20, color: _couleurDIcone()),
                const SizedBox(width: VfSpacing.x12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        lecon.titre,
                        style: VfTextStyles.tSm.copyWith(color: VfColors.ink),
                      ),
                      const SizedBox(height: 2),
                      Text('${lecon.minutes} min', style: VfTextStyles.tXs),
                      if (condition != null) ...<Widget>[
                        const SizedBox(height: 3),
                        Text(
                          condition,
                          style: VfTextStyles.tXs.copyWith(
                            color: VfColors.slate2,
                            fontStyle: FontStyle.italic,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                if (disponibleHorsLigne)
                  const Icon(
                    Icons.download_done_rounded,
                    size: 16,
                    color: VfColors.primaryDark,
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  IconData _icone() {
    if (lecon.estAcquise) {
      return Icons.check_circle_rounded;
    }
    if (lecon.estVerrouillee) {
      return Icons.lock_rounded;
    }
    if (_bloqueeParLeReseau) {
      return Icons.cloud_off_rounded;
    }
    return Icons.play_circle_rounded;
  }

  Color _couleurDIcone() =>
      lecon.estAcquise ? VfColors.primary : VfColors.slate2;

  /// La phrase qui dit **pourquoi** la ligne n'est pas ouvrable.
  String? _condition() {
    if (lecon.estVerrouillee) {
      return numero <= 1
          ? 'Inscrivez-vous pour ouvrir cette leçon'
          : 'Terminez la leçon precedente';
    }
    if (_bloqueeParLeReseau) {
      return 'Disponible avec une connexion';
    }
    return null;
  }

  String _libelle(String? condition) {
    final String etat = lecon.estAcquise
        ? 'terminee'
        : (lecon.estVerrouillee ? 'verrouillee' : 'a lire');
    return <String>[
      'Leçon $numero',
      lecon.titre,
      etat,
      '${lecon.minutes} minutes',
      ?condition,
      if (disponibleHorsLigne) 'disponible hors ligne',
    ].join(', ');
  }
}

/// La ligne du quiz final, en bas du programme.
///
/// # Pourquoi elle n'est jamais verrouillée
///
/// Le simulateur verrouille le quiz tant que toutes les leçons ne sont pas
/// faites. **Le contrat ne porte aucun champ correspondant** :
/// `GET /courses/{slug}/quiz` ne pose ni `ouverte`, ni condition, et le
/// contrôleur serveur ne refuse pas l'accès. Poser un verrou ici reviendrait à
/// **recalculer côté client une règle de déverrouillage** — exactement ce que
/// la table d'arbitrage §7.4 interdit pour les leçons.
///
/// La ligne est donc toujours ouvrable, et l'écart est consigné.
class QuizTile extends StatelessWidget {
  const QuizTile({required this.quiz, required this.onTap, super.key});

  final QuizSummary quiz;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final String meta =
        '${quiz.minutes} min · ${quiz.scoreRequis} % pour valider · '
        '+${quiz.xp} XP';

    return Semantics(
      button: true,
      label: '${quiz.titre}, $meta',
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(
            minHeight: VfTouch.menuRowMinHeight,
          ),
          padding: const EdgeInsets.symmetric(
            horizontal: VfTouch.menuRowInlinePadding,
            vertical: VfTouch.menuRowVerticalPadding,
          ),
          child: Row(
            children: <Widget>[
              const Icon(Icons.quiz_rounded, size: 20, color: VfColors.slate2),
              const SizedBox(width: VfSpacing.x12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      quiz.titre,
                      style: VfTextStyles.tSm.copyWith(color: VfColors.ink),
                    ),
                    const SizedBox(height: 2),
                    Text(meta, style: VfTextStyles.tXs),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
