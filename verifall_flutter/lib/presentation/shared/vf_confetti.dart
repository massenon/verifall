import 'dart:math';

import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_motion.dart';

/// `.confetti` — 46 pièces, cinq couleurs, chute de 900 en 1,5 à 2,7 s.
///
/// # Deux règles, et elles ne se négocient pas
///
/// - **Coupé par `disableAnimations`** (§2.6). Une pluie de confettis chez
///   quelqu'un qui a demandé à couper les animations n'est pas une célébration.
/// - **Jamais sur un échec.** C'est une règle explicite du pack UX et du
///   simulateur. Un quiz raté qui déclenche des confettis est un bogue qui se
///   voit à l'écran et qui blesse.
///
/// POURQUOI un `CustomPaint` maison plutôt qu'une bibliothèque : le pack le
/// nomme parmi les **deux dépendances volontairement absentes** (§1.8) — le back
/// a déjà son module maison, et le mobile fait de même.
class VfConfetti extends StatefulWidget {
  const VfConfetti({this.pieces = 46, super.key});

  final int pieces;

  /// Les cinq couleurs du simulateur.
  static const List<Color> colors = <Color>[
    VfColors.primary,
    VfColors.xp,
    VfColors.verdictInvestigate,
    VfColors.info,
    VfColors.verdictTrue,
  ];

  static const double fallDistance = 900;

  @override
  State<VfConfetti> createState() => _VfConfettiState();
}

class _VfConfettiState extends State<VfConfetti>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    // La piece la plus lente met 2,7 s ; le controleur porte la duree la plus
    // longue et chaque piece avance a sa propre vitesse.
    duration: const Duration(milliseconds: 2700),
  );

  late final List<_Piece> _pieces;

  @override
  void initState() {
    super.initState();
    final Random random = Random();
    _pieces = List<_Piece>.generate(
      widget.pieces,
      (int index) => _Piece(
        startX: random.nextDouble(),
        drift: random.nextDouble() * 0.24 - 0.12,
        // Entre 1,5 s et 2,7 s : la fraction du controleur a laquelle la piece
        // a fini sa chute.
        speed: 1.5 / 2.7 + random.nextDouble() * (1 - 1.5 / 2.7),
        rotation: random.nextDouble() * pi * 4,
        color: VfConfetti.colors[index % VfConfetti.colors.length],
        size: 6 + random.nextDouble() * 6,
      ),
    );
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (VfMotion.reducedMotion(context)) {
      return const SizedBox.shrink();
    }
    return IgnorePointer(
      child: ExcludeSemantics(
        child: AnimatedBuilder(
          animation: _controller,
          builder: (BuildContext context, _) => CustomPaint(
            painter: _ConfettiPainter(
              pieces: _pieces,
              progress: _controller.value,
            ),
            size: Size.infinite,
          ),
        ),
      ),
    );
  }
}

@immutable
class _Piece {
  const _Piece({
    required this.startX,
    required this.drift,
    required this.speed,
    required this.rotation,
    required this.color,
    required this.size,
  });

  final double startX;
  final double drift;
  final double speed;
  final double rotation;
  final Color color;
  final double size;
}

class _ConfettiPainter extends CustomPainter {
  const _ConfettiPainter({required this.pieces, required this.progress});

  final List<_Piece> pieces;
  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    for (final _Piece piece in pieces) {
      final double t = (progress / piece.speed).clamp(0, 1);
      if (t >= 1) {
        continue;
      }
      final double y = t * VfConfetti.fallDistance;
      final double x = (piece.startX + piece.drift * t) * size.width;

      canvas.save();
      canvas.translate(x, y);
      canvas.rotate(piece.rotation * t);
      canvas.drawRect(
        Rect.fromCenter(
          center: Offset.zero,
          width: piece.size,
          height: piece.size * 0.6,
        ),
        Paint()..color = piece.color.withValues(alpha: 1 - t * 0.3),
      );
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_ConfettiPainter oldDelegate) =>
      oldDelegate.progress != progress;
}
