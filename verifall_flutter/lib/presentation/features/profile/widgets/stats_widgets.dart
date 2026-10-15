import 'dart:math' as math;

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_motion.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/stats_statistiques.dart';
import '../../../shared/shared.dart';

/// L'histogramme d'activité.
///
/// # La règle de forme qui décide du dessin
///
/// **Les jours sans activité restent dans la série, à zéro, et ils sont
/// dessinés.** Le contrat en donne la raison : « un graphique qui saute les
/// jours vides ment sur la régularité, qui est justement ce qu'il montre ». Ce
/// composant ne filtre donc rien, ne compacte rien, et ne réordonne rien.
///
/// # La lecture en clair
///
/// Elle n'est pas décorative : « un chiffre sans interprétation ne change aucun
/// comportement ». La phrase sous le graphique est composée de chiffres servis —
/// le total et le maximum de la série — jamais d'une tendance déduite.
class VfActivityChart extends StatelessWidget {
  const VfActivityChart({required this.serie, super.key});

  final SerieActivite serie;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const VfEyebrow('Activité'),
          const SizedBox(height: VfSpacing.x6),
          Text(
            'Activité sur ${serie.valeurs.length} jours',
            style: VfTextStyles.hSm,
          ),
          const SizedBox(height: VfSpacing.x12),
          SizedBox(
            height: 92,
            child: Semantics(
              label: _lecture(),
              excludeSemantics: true,
              child: BarChart(
                BarChartData(
                  alignment: BarChartAlignment.spaceBetween,
                  maxY: math.max(1, serie.maximum).toDouble(),
                  minY: 0,
                  barTouchData: BarTouchData(enabled: false),
                  titlesData: const FlTitlesData(show: false),
                  gridData: const FlGridData(show: false),
                  borderData: FlBorderData(show: false),
                  barGroups: <BarChartGroupData>[
                    for (int i = 0; i < serie.valeurs.length; i++)
                      BarChartGroupData(
                        x: i,
                        barRods: <BarChartRodData>[
                          BarChartRodData(
                            toY: serie.valeurs[i].toDouble(),
                            width: 6,
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(3),
                            ),
                            color: VfColors.primary,
                          ),
                        ],
                      ),
                  ],
                ),
                // Le battement d'animation suit le reglage systeme : c'est la
                // meme regle que partout ailleurs (02-DESIGN-SYSTEM 2.6).
                duration: VfMotion.reducedMotion(context)
                    ? Duration.zero
                    : VfMotion.progress,
              ),
            ),
          ),
          const SizedBox(height: VfSpacing.x10),
          Text(_lecture(), style: VfTextStyles.tXs),
        ],
      ),
    );
  }

  String _lecture() {
    if (serie.estVide) {
      return 'Aucune activité mesuree sur cette periode.';
    }
    final int joursActifs = serie.valeurs.where((int v) => v > 0).length;
    return '${serie.total} vérification(s) sur ${serie.valeurs.length} jours, '
        'reparties sur $joursActifs jour(s). Les jours sans activité sont '
        'affiches a zero.';
  }
}

/// Le donut de répartition des verdicts.
///
/// # Pourquoi un `CustomPaint` et pas une bibliothèque
///
/// Le simulateur le dit, et la liste des dépendances le confirme : une
/// bibliothèque de graphiques circulaires fait partie des **deux dépendances
/// volontairement absentes** (01-FONDATIONS §1.8). Un anneau à trois segments se
/// dessine en trente lignes ; en ajouter une dépendance pour cela est une dette
/// qui se paie à chaque montée de version de Flutter.
///
/// # Les couleurs ne sont pas choisies ici
///
/// Elles viennent des jetons de verdict du socle. Le **couple valeur technique /
/// libellé** est rendu par le serveur ; ce composant n'invente aucun libellé et
/// affiche la valeur telle quelle si elle ne correspond à aucun ton connu.
class VfVerdictDonut extends StatelessWidget {
  const VfVerdictDonut({required this.verdicts, super.key});

  final Map<String, int> verdicts;

  /// La correspondance **valeur technique → ton**, réduite à ce que le contrat
  /// publie et à ce que le prototype montre.
  ///
  /// POURQUOI un cas neutre existe : le contrat ne publie qu'une valeur
  /// technique de verdict (`"false"`), et la correspondance complète « se fait
  /// une fois, dans M2, contre le serveur réel ». Une valeur inconnue s'affiche
  /// donc en gris **avec son libellé**, plutôt que d'être écartée du total — un
  /// segment manquant fausserait la lecture de tous les autres.
  static Color tonDe(String valeur) => switch (valeur.toLowerCase()) {
    'true' || 'vrai' => VfColors.verdictTrue,
    'false' || 'faux' => VfColors.verdictFalse,
    'misleading' ||
    'trompeur' ||
    'investigate' ||
    'a_verifier' => VfColors.verdictInvestigate,
    _ => VfColors.slate2,
  };

  @override
  Widget build(BuildContext context) {
    final int total = verdicts.values.fold(0, (int a, int b) => a + b);
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const VfEyebrow('Verdicts'),
          const SizedBox(height: VfSpacing.x6),
          Text('Repartition de mes verdicts', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: <Widget>[
              Semantics(
                label: _lecture(total),
                excludeSemantics: true,
                child: SizedBox(
                  width: 132,
                  height: 132,
                  child: CustomPaint(
                    painter: _DonutPainter(verdicts: verdicts, total: total),
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          Text(
                            '$total',
                            style: const TextStyle(
                              fontFamily: VfFonts.display,
                              fontSize: 22,
                              fontWeight: FontWeight.w700,
                              color: VfColors.ink,
                            ),
                          ),
                          Text('analyses', style: VfTextStyles.tXs),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: VfSpacing.x16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    for (final MapEntry<String, int> entree in verdicts.entries)
                      Padding(
                        padding: const EdgeInsets.only(bottom: VfSpacing.x6),
                        child: Row(
                          children: <Widget>[
                            Container(
                              width: 10,
                              height: 10,
                              decoration: BoxDecoration(
                                color: tonDe(entree.key),
                                borderRadius: VfRadii.pillAll,
                              ),
                            ),
                            const SizedBox(width: VfSpacing.x8),
                            Expanded(
                              child: Text(
                                entree.key.toUpperCase(),
                                style: VfTextStyles.tXs.copyWith(
                                  color: VfColors.ink,
                                ),
                              ),
                            ),
                            Text(
                              '${entree.value}',
                              style: VfTextStyles.tXs.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: VfSpacing.x6),
          Text(_lecture(total), style: VfTextStyles.tXs),
        ],
      ),
    );
  }

  String _lecture(int total) {
    if (total == 0) {
      return 'Aucun verdict rendu pour l instant.';
    }
    final List<String> parts = <String>[
      for (final MapEntry<String, int> e in verdicts.entries)
        '${e.value} ${e.key}',
    ];
    return 'Repartition des verdicts sur $total analyse(s) : '
        '${parts.join(', ')}.';
  }
}

class _DonutPainter extends CustomPainter {
  const _DonutPainter({required this.verdicts, required this.total});

  final Map<String, int> verdicts;
  final int total;

  static const double _epaisseur = 16;

  @override
  void paint(Canvas canvas, Size size) {
    final Offset centre = Offset(size.width / 2, size.height / 2);
    final double rayon = (math.min(size.width, size.height) - _epaisseur) / 2;
    final Rect boite = Rect.fromCircle(center: centre, radius: rayon);

    final Paint piste = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = _epaisseur
      ..color = VfColors.line;
    canvas.drawCircle(centre, rayon, piste);

    if (total == 0) {
      return;
    }

    // On demarre a midi, comme le simulateur (rotate -90).
    double depart = -math.pi / 2;
    verdicts.forEach((String valeur, int nombre) {
      if (nombre <= 0) {
        return;
      }
      final double angle = nombre / total * 2 * math.pi;
      canvas.drawArc(
        boite,
        depart,
        angle,
        false,
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = _epaisseur
          ..color = VfVerdictDonut.tonDe(valeur),
      );
      depart += angle;
    });
  }

  @override
  bool shouldRepaint(_DonutPainter previous) =>
      previous.total != total || previous.verdicts != verdicts;
}

/// Les barres fines par type de contenu.
class VfTypeBars extends StatelessWidget {
  const VfTypeBars({required this.types, super.key});

  final Map<String, int> types;

  @override
  Widget build(BuildContext context) {
    final int total = types.values.fold(0, (int a, int b) => a + b);
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const VfEyebrow('Contenus'),
          const SizedBox(height: VfSpacing.x6),
          Text('Types de contenus verifies', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x12),
          // TOUS les types sont affiches, y compris a zero : « un histogramme
          // dont les colonnes changent de place d'une visite a l'autre ne se lit
          // pas ». L'ordre est celui du serveur.
          for (final MapEntry<String, int> entree in types.entries)
            Padding(
              padding: const EdgeInsets.only(bottom: VfSpacing.x9),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          entree.key,
                          style: VfTextStyles.tXs.copyWith(
                            color: VfColors.ink,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      Text('${entree.value}', style: VfTextStyles.tXs),
                    ],
                  ),
                  const SizedBox(height: VfSpacing.x4),
                  VfProgressBar(
                    value: total == 0 ? 0 : entree.value / total,
                    thin: true,
                    semanticsLabel: '${entree.key} : ${entree.value}',
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// La carte d'enseignement — `DATA → INTERPRÉTATION → ACTION`.
///
/// # Pourquoi elle ne se supprime pas pour gagner de la place
///
/// C'est le seul bloc de l'écran qui agit sur le comportement : « un chiffre
/// sans interprétation ne change aucun comportement ». Le pourcentage affiché
/// est une **lecture de chiffres servis** — les verdicts et leur total — jamais
/// une tendance déduite ni une comparaison à un mois calendaire, que
/// l'arbitrage n° 67 a précisément retirée.
class VfInsightCard extends StatelessWidget {
  const VfInsightCard({required this.stats, super.key});

  final Statistiques stats;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      background: VfColors.primarySoft,
      border: VfColors.primary.withValues(alpha: 0.25),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const VfEyebrow('Ce que disent vos donnees'),
          const SizedBox(height: VfSpacing.x6),
          Text(
            _phrase(),
            style: VfTextStyles.tSm.copyWith(color: VfColors.primaryDark),
          ),
        ],
      ),
    );
  }

  String _phrase() {
    final int total = stats.totalVerdicts;
    if (total == 0) {
      return 'Vos analyses n ont pas encore de verdict rendu. Des qu un '
          'rapport est pret, cette lecture apparaît ici.';
    }
    final int pourcent = (stats.intoxDetectees / total * 100).round();
    return '$pourcent % des contenus que vous avez soumis se sont reveles faux '
        'ou trompeurs. Votre vigilance protege directement votre entourage — '
        'continuez a vérifier avant de partager.';
  }
}
