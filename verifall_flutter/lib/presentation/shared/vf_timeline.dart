import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_motion.dart';
import '../../core/theme/vf_typography.dart';

/// Les trois états d'une étape de chronologie (§2.7).
enum VfTimelineStepState {
  /// Plein, avec une coche.
  fait,

  /// Cerclé, avec un battement.
  actif,

  /// Numéroté.
  aVenir,
}

/// Une étape.
@immutable
class VfTimelineStep {
  const VfTimelineStep({required this.label, required this.state, this.detail});

  final String label;
  final String? detail;
  final VfTimelineStepState state;
}

/// `.vtimeline` — trait vertical, pastilles 26.
///
/// Employée par l'écran d'analyse en cours (10). Les étapes viennent du serveur
/// (`data.etape`, `data.avancement`) : le client **ne les déduit jamais** d'un
/// chronomètre local, sinon une analyse rapide semblerait bloquée.
class VfTimeline extends StatelessWidget {
  const VfTimeline({required this.steps, super.key});

  final List<VfTimelineStep> steps;

  static const double _dotSize = 26;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        for (int i = 0; i < steps.length; i++)
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Column(
                  children: <Widget>[
                    _Dot(step: steps[i], index: i),
                    if (i < steps.length - 1)
                      const Expanded(
                        child: SizedBox(
                          width: 2,
                          child: ColoredBox(color: VfColors.line),
                        ),
                      ),
                  ],
                ),
                const SizedBox(width: VfSpacing.x12),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: VfSpacing.x16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          steps[i].label,
                          style: VfTextStyles.body.copyWith(
                            fontWeight:
                                steps[i].state == VfTimelineStepState.actif
                                ? FontWeight.w700
                                : FontWeight.w400,
                          ),
                        ),
                        if (steps[i].detail != null)
                          Text(steps[i].detail!, style: VfTextStyles.tSm),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _Dot extends StatefulWidget {
  const _Dot({required this.step, required this.index});

  final VfTimelineStep step;
  final int index;

  @override
  State<_Dot> createState() => _DotState();
}

class _DotState extends State<_Dot> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  );

  @override
  void initState() {
    super.initState();
    if (widget.step.state == VfTimelineStepState.actif) {
      _controller.repeat(reverse: true);
    }
  }

  @override
  void didUpdateWidget(_Dot oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.step.state == VfTimelineStepState.actif) {
      if (!_controller.isAnimating) {
        _controller.repeat(reverse: true);
      }
    } else {
      _controller.stop();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final VfTimelineStepState state = widget.step.state;
    // « Le battement du point actif de la chronologie d'analyse » est l'une des
    // trois choses que `disableAnimations` coupe (§2.6).
    final bool animate =
        state == VfTimelineStepState.actif && !VfMotion.reducedMotion(context);

    final Widget dot = Container(
      width: VfTimeline._dotSize,
      height: VfTimeline._dotSize,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: switch (state) {
          VfTimelineStepState.fait => VfColors.primary,
          VfTimelineStepState.actif => VfColors.surface,
          VfTimelineStepState.aVenir => VfColors.surface,
        },
        shape: BoxShape.circle,
        border: Border.all(
          color: state == VfTimelineStepState.aVenir
              ? VfColors.line
              : VfColors.primary,
          width: 2,
        ),
      ),
      child: switch (state) {
        VfTimelineStepState.fait => const Icon(
          Icons.check_rounded,
          size: 15,
          color: VfColors.surface,
        ),
        VfTimelineStepState.actif => const DecoratedBox(
          decoration: BoxDecoration(
            color: VfColors.primary,
            shape: BoxShape.circle,
          ),
          child: SizedBox.square(dimension: 10),
        ),
        VfTimelineStepState.aVenir => Text(
          '${widget.index + 1}',
          style: VfTextStyles.tXs.copyWith(color: VfColors.slate2),
        ),
      },
    );

    if (!animate) {
      return dot;
    }

    return FadeTransition(
      opacity: Tween<double>(begin: 0.55, end: 1).animate(_controller),
      child: dot,
    );
  }
}

/// `.tline` — variante légère, points de 12.
///
/// Employée pour « comment ça marche » du parrainage (écran 29).
class VfActivityTimeline extends StatelessWidget {
  const VfActivityTimeline({required this.entries, super.key});

  /// Titre et description de chaque étape.
  final List<({String title, String description})> entries;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        for (int i = 0; i < entries.length; i++)
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Column(
                  children: <Widget>[
                    const Padding(
                      padding: EdgeInsets.only(top: VfSpacing.x4),
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: VfColors.primary,
                          shape: BoxShape.circle,
                        ),
                        child: SizedBox.square(dimension: 12),
                      ),
                    ),
                    if (i < entries.length - 1)
                      const Expanded(
                        child: SizedBox(
                          width: 2,
                          child: ColoredBox(color: VfColors.line),
                        ),
                      ),
                  ],
                ),
                const SizedBox(width: VfSpacing.x12),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: VfSpacing.x14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          entries[i].title,
                          style: VfTextStyles.body.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        Text(entries[i].description, style: VfTextStyles.tSm),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
