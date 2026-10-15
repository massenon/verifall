import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../shared/vf_display.dart';
import 'verdict_tone.dart';

/// Le texte partagé d'un rapport.
///
/// # Pourquoi il n'y a pas de lien
///
/// Un rapport de vérification est **privé** : `GET /verifications/{uuid}` est
/// borné au propriétaire, et « ce qui n'appartient pas au demandeur répond 404 ».
/// Il n'existe donc aucune adresse publique à partager. Le simulateur propose
/// « Copier le lien » et « Facebook » — les deux supposent une URL publique que
/// l'API ne fournit pas. Voir `docs/ARBITRAGES.md`.
///
/// Ce qui se partage, c'est **le contenu du verdict** : c'est d'ailleurs ce que
/// les gens repostent.
String texteDePartage(Verification verification) {
  final String verdict =
      verification.rapport?.verdict?.libelle ??
      verification.statutLibelle ??
      'analyse en cours';
  final int? confiance = verification.rapport?.confiance;
  return <String>[
    'Vérif All — verdict : $verdict',
    if (confiance != null) 'Indice de fiabilite : $confiance %',
    '',
    verification.apercu,
    '',
    'Reference ${verification.reference}',
  ].join('\n');
}

/// Ouvre WhatsApp avec le texte du rapport.
///
/// POURQUOI `wa.me` et pas la feuille de partage du système : le bouton du
/// simulateur dit « Partager sur WhatsApp », et une feuille générique ne tient
/// pas cette promesse. Si WhatsApp n'est pas installé, on **retombe** sur la
/// feuille système plutôt que d'échouer en silence.
Future<void> partagerSurWhatsApp(
  BuildContext context,
  Verification verification,
) async {
  final String texte = texteDePartage(verification);
  final Uri lien = Uri.parse(
    'https://wa.me/?text=${Uri.encodeComponent(texte)}',
  );
  final bool ouvert = await launchUrl(
    lien,
    mode: LaunchMode.externalApplication,
  );
  if (!ouvert) {
    await SharePlus.instance.share(ShareParams(text: texte));
  }
}

/// La carte 9:16 partagée en image.
///
/// # Pourquoi elle est rendue côté client
///
/// « Image 9:16 rendue côté client, depuis un `RepaintBoundary` : elle doit
/// rester lisible hors application, **sans dépendre du réseau** » — c'est ce qui
/// la rend partageable hors ligne, et le rapport est justement le document qu'on
/// rouvre pour le montrer à quelqu'un, souvent sans réseau.
///
/// Elle ne porte que des champs servis : verdict, affirmation, indice de
/// fiabilité, référence, marque.
class CarteDePartage extends StatelessWidget {
  const CarteDePartage({required this.verification, super.key});

  final Verification verification;

  /// 1080 × 1920 : le format d'un statut. La carte est dessinée à cette échelle
  /// pour rester nette une fois exportée.
  static const Size dimensions = Size(1080, 1920);

  @override
  Widget build(BuildContext context) {
    final tone = TonDuVerdict.pour(verification.rapport?.verdict);
    final int? confiance = verification.rapport?.confiance;

    return SizedBox(
      width: dimensions.width,
      height: dimensions.height,
      child: ColoredBox(
        color: VfColors.forest,
        child: Padding(
          padding: const EdgeInsets.all(96),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                'VÉRIF ALL',
                style: VfTextStyles.eyebrow.copyWith(
                  fontSize: 34,
                  color: VfColors.primary,
                  letterSpacing: 6,
                ),
              ),
              const SizedBox(height: 48),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 44,
                  vertical: 22,
                ),
                decoration: BoxDecoration(
                  color: tone.color,
                  borderRadius: VfRadii.pillAll,
                ),
                child: Text(
                  (verification.rapport?.verdict?.libelle ??
                          verification.statutLibelle ??
                          '')
                      .toUpperCase(),
                  style: VfTextStyles.hLg.copyWith(
                    fontSize: 54,
                    color: VfColors.surface,
                  ),
                ),
              ),
              const SizedBox(height: 64),
              Expanded(
                child: Text(
                  verification.apercu,
                  style: VfTextStyles.hLg.copyWith(
                    fontSize: 76,
                    height: 1.25,
                    color: VfColors.surface,
                  ),
                ),
              ),
              if (confiance != null) ...<Widget>[
                Text(
                  'Indice de fiabilite : $confiance %',
                  style: VfTextStyles.body.copyWith(
                    fontSize: 40,
                    color: VfColors.onForestMuted,
                  ),
                ),
                const SizedBox(height: 24),
              ],
              Text(
                'Reference ${verification.reference}',
                style: VfTextStyles.mono.copyWith(
                  fontSize: 32,
                  color: VfColors.onForestMuted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Capture la carte 9:16 et la passe à la feuille de partage du système.
Future<void> partagerLImage(
  GlobalKey cleDeCapture, {
  required String texte,
}) async {
  final RenderObject? objet = cleDeCapture.currentContext?.findRenderObject();
  if (objet is! RenderRepaintBoundary) {
    return;
  }
  final ui.Image image = await objet.toImage(pixelRatio: 1);
  final ByteData? octets = await image.toByteData(
    format: ui.ImageByteFormat.png,
  );
  image.dispose();
  if (octets == null) {
    return;
  }
  await SharePlus.instance.share(
    ShareParams(
      text: texte,
      files: <XFile>[
        XFile.fromData(
          octets.buffer.asUint8List(),
          mimeType: 'image/png',
          name: 'verifall-rapport.png',
        ),
      ],
    ),
  );
}

/// Le contenu de la feuille « Partager ».
///
/// **Trois entrées, pas quatre.** Le simulateur en propose quatre : WhatsApp,
/// Facebook, Copier le lien, Image 9:16. Les deux du milieu supposent une
/// adresse publique du rapport, que l'API ne sert pas — le rapport est privé.
/// « Copier le lien » devient donc « Copier le texte », et Facebook disparaît :
/// un bouton qui ouvrirait un partage sans contenu est un bouton inerte.
class FeuilleDePartage extends StatelessWidget {
  const FeuilleDePartage({
    required this.onWhatsApp,
    required this.onCopier,
    required this.onImage,
    super.key,
  });

  final VoidCallback onWhatsApp;
  final VoidCallback onCopier;
  final VoidCallback onImage;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(
      VfSpacing.x16,
      0,
      VfSpacing.x16,
      VfSpacing.x16,
    ),
    child: VfMenuList(
      children: <Widget>[
        VfNavTile(
          label: 'WhatsApp',
          icon: Icons.chat_rounded,
          onTap: onWhatsApp,
        ),
        VfNavTile(
          label: 'Copier le texte',
          icon: Icons.copy_rounded,
          onTap: onCopier,
        ),
        VfNavTile(
          label: 'Image 9:16',
          icon: Icons.image_rounded,
          onTap: onImage,
        ),
      ],
    ),
  );
}

/// Copie le texte du rapport dans le presse-papiers.
Future<void> copierLeTexte(Verification verification) =>
    Clipboard.setData(ClipboardData(text: texteDePartage(verification)));
