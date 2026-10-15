import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/certificate_summary.dart';
import '../../../../core/format/vf_formats.dart';

/// Le filigrane « VÉRIF'ALL ».
///
/// **Un `CustomPaint`, jamais une image** — c'est le point de vigilance du
/// simulateur, et cela évite une ressource de plus à charger sur un premier
/// lancement en réseau lent.
///
/// Il est purement décoratif, donc **masqué au lecteur d'écran** : il vit sous
/// un `ExcludeSemantics`.
class WatermarkPainter extends CustomPainter {
  const WatermarkPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final TextPainter peintre = TextPainter(
      text: TextSpan(
        text: 'VÉRIF ALL',
        style: TextStyle(
          fontFamily: VfFonts.display,
          fontSize: 44,
          fontWeight: FontWeight.w800,
          // Vert a 6 % d'opacite : la valeur du design system, pas une nuance
          // choisie a l'oeil.
          color: VfColors.primary.withValues(alpha: 0.06),
          letterSpacing: 44 * 0.16,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout(maxWidth: size.width * 2);

    peintre.paint(
      canvas,
      Offset(
        (size.width - peintre.width) / 2,
        (size.height - peintre.height) / 2,
      ),
    );
  }

  @override
  bool shouldRepaint(covariant WatermarkPainter oldDelegate) => false;
}

/// Le certificat lui-même.
///
/// # La règle qui ne se négocie pas
///
/// **Le nom du titulaire et le titre du cours sont figés à l'émission**
/// (arbitrage n° 40). L'écran affiche ce que porte le certificat, **pas** les
/// valeurs courantes du compte : « un diplôme atteste de ce qui a été suivi ce
/// jour-là — il ne doit ni mentir après un renommage du cours, ni redevenir
/// nominatif après un effacement RGPD ».
///
/// # L'accessibilité
///
/// « Le certificat est lu comme un texte suivi : titulaire, cours, date,
/// identifiant — l'identifiant **épelé caractère par caractère**. »
class CertificateCard extends StatelessWidget {
  const CertificateCard({required this.certificat, super.key});

  final CertificateSummary certificat;

  @override
  Widget build(BuildContext context) {
    final String date = certificat.delivreLe == null
        ? ''
        : VfFormats.dateCourte(certificat.delivreLe!);

    return Semantics(
      label: _lectureSuivie(date),
      excludeSemantics: true,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: VfColors.surface,
          borderRadius: VfRadii.lgAll,
          border: Border.all(color: VfColors.forest, width: 3),
        ),
        child: Stack(
          children: <Widget>[
            Positioned.fill(
              child: ExcludeSemantics(
                child: CustomPaint(painter: const WatermarkPainter()),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: VfSpacing.x22,
                vertical: VfSpacing.x26,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: <Widget>[
                  Text(
                    'VÉRIF ALL ACADÉMIE',
                    style: VfTextStyles.eyebrow,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: VfSpacing.x12),
                  Text(
                    'Certificat de réussite decerne a',
                    style: VfTextStyles.tXs,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 7),
                  Text(
                    certificat.titulaire,
                    style: VfTextStyles.hLg,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: VfSpacing.x12),
                  Text(
                    'pour avoir termine avec succes',
                    style: VfTextStyles.tSm,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 7),
                  Text(
                    certificat.coursTitre,
                    style: VfTextStyles.hSm,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: VfSpacing.x16),
                  const Divider(height: 1, color: VfColors.line),
                  const SizedBox(height: 13),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Expanded(
                        child: _Colonne(
                          titre: 'Delivre le',
                          // Pas de date de repli : « pas de chiffre qui ne
                          // vienne pas du serveur ». Une date absente se dit.
                          valeur: date.isEmpty ? 'Non communiquee' : date,
                        ),
                      ),
                      Expanded(
                        child: _Colonne(
                          titre: 'Identifiant',
                          valeur: certificat.code,
                          monospace: true,
                          alignement: CrossAxisAlignment.end,
                        ),
                      ),
                    ],
                  ),
                  if (certificat.revoque) ...<Widget>[
                    const SizedBox(height: VfSpacing.x12),
                    Text(
                      'Ce certificat a ete revoque.',
                      style: VfTextStyles.tXs.copyWith(
                        color: VfColors.verdictFalse,
                        fontWeight: FontWeight.w700,
                      ),
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

  String _lectureSuivie(String date) {
    final String codeEpele = certificat.code.split('').join(' ');
    return <String>[
      'Certificat Vérif All Académie',
      'decerne a ${certificat.titulaire}',
      'pour le cours ${certificat.coursTitre}',
      if (date.isNotEmpty) 'delivre le $date',
      'identifiant $codeEpele',
      if (certificat.revoque) 'ce certificat a ete revoque',
    ].join(', ');
  }
}

class _Colonne extends StatelessWidget {
  const _Colonne({
    required this.titre,
    required this.valeur,
    this.monospace = false,
    this.alignement = CrossAxisAlignment.start,
  });

  final String titre;
  final String valeur;
  final bool monospace;
  final CrossAxisAlignment alignement;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: alignement,
      children: <Widget>[
        Text(titre, style: VfTextStyles.tXs),
        const SizedBox(height: 2),
        Text(
          valeur,
          // La monospace ne sert qu'a ce qu'un humain devra RECOPIER : la
          // reference d'une vérification, l'identifiant d'un certificat, le
          // code de parrainage (§2.3).
          style: monospace
              ? VfTextStyles.mono.copyWith(
                  fontSize: 10.5,
                  color: VfColors.ink,
                  fontWeight: FontWeight.w600,
                )
              : VfTextStyles.tXs.copyWith(
                  color: VfColors.ink,
                  fontWeight: FontWeight.w700,
                ),
        ),
      ],
    );
  }
}
