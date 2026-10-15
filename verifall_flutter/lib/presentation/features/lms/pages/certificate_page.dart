import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/repositories/certificate_repository.dart';
import '../../../shared/shared.dart';
import '../providers/certificate_provider.dart';
import '../widgets/certificate_card.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../core/config/app_config.dart';
import '../../../../core/config/config_providers.dart';
import '../../../../core/config/vf_web_links.dart';

/// Écran 18 — Certificat (`#/certificate`).
///
/// # Le bouton PDF est RETIRÉ, et c'est une décision, pas un oubli
///
/// `GET /me/certificates/{code}/pdf` **n'a aucun endpoint** (contrat §11,
/// arbitrage n° 73) : aucune bibliothèque de génération PDF n'est installée
/// côté serveur, et « en ajouter une est une décision de dépendance qui
/// appartient au porteur du projet ».
///
/// « Un bouton qui échoue systématiquement est pire que son absence. » Deux
/// chemins restent, et ils suffisent :
///
/// 1. **le partage d'image**, rendu depuis un `RepaintBoundary` — c'est ce qu'on
///    envoie sur WhatsApp, et c'est le geste réel ;
/// 2. **le renvoi vers la page publique de vérification**, dont l'encart porte
///    déjà l'adresse. C'est une dégradation **honnête**, pas un contournement.
///
/// Et le client ne composera **jamais** le PDF lui-même : « un certificat
/// composé côté client serait falsifiable par quiconque décompile
/// l'application ».
class CertificatePage extends ConsumerStatefulWidget {
  const CertificatePage({required this.code, super.key});

  /// Le **code public**, pas un uuid.
  final String code;

  @override
  ConsumerState<CertificatePage> createState() => _CertificatePageState();
}

class _CertificatePageState extends ConsumerState<CertificatePage> {
  final GlobalKey _cadre = GlobalKey();

  @override
  Widget build(BuildContext context) {
    final AsyncValue<CertificatLu> certificat = ref.watch(
      certificateProvider(widget.code),
    );

    return Scaffold(
      appBar: VfAppBar(
        title: 'Certificat',
        // Pas de pastille de points : c'est un ecran de lecture.
        actions: <Widget>[
          VfIconButton(
            icon: Icons.ios_share_rounded,
            tooltip: 'Partager mon certificat',
            onPressed: certificat.hasValue ? _partager : null,
          ),
        ],
      ),
      body: certificat.when(
        loading: () => const _Squelette(),
        error: (Object erreur, StackTrace _) => _Introuvable(erreur: erreur),
        data: (CertificatLu lu) => ListView(
          padding: const EdgeInsets.fromLTRB(
            VfSpacing.screenInline,
            VfSpacing.screenTopExtra,
            VfSpacing.screenInline,
            VfSpacing.screenBottomExtra,
          ),
          children: <Widget>[
            if (lu.vientDuCache) ...<Widget>[
              // Un certificat lu hors ligne reste exact : titulaire et titre du
              // cours sont FIGES a l'emission. Le bandeau dit seulement que la
              // liste n'a pas ete revue — une revocation recente, par exemple,
              // pourrait ne pas encore etre connue.
              VfStaleDataBanner(lueLe: lu.lueLe),
              const SizedBox(height: VfSpacing.stack),
            ],
            RepaintBoundary(
              key: _cadre,
              child: CertificateCard(certificat: lu.certificat),
            ),
            const SizedBox(height: VfSpacing.stack),
            _EncartDeVerifiabilite(code: lu.certificat.code),
            const SizedBox(height: VfSpacing.stack),
            VfPrimaryButton(
              label: 'Partager mon certificat',
              icon: Icons.ios_share_rounded,
              expanded: true,
              onPressed: _partager,
            ),
          ],
        ),
      ),
    );
  }

  /// Le partage d'image, depuis le `RepaintBoundary`.
  ///
  /// POURQUOI une image et pas un fichier composé : c'est ce que le contrat
  /// laisse faire honnêtement, et c'est le geste réel — on envoie une capture
  /// sur WhatsApp, pas un PDF.
  Future<void> _partager() async {
    final RenderObject? objet = _cadre.currentContext?.findRenderObject();
    if (objet is! RenderRepaintBoundary) {
      return;
    }
    final ui.Image image = await objet.toImage(pixelRatio: 3);
    final ByteData? donnees = await image.toByteData(
      format: ui.ImageByteFormat.png,
    );
    if (donnees == null) {
      return;
    }
    await SharePlus.instance.share(
      ShareParams(
        files: <XFile>[
          XFile.fromData(
            donnees.buffer.asUint8List(),
            mimeType: 'image/png',
            name: 'certificat.png',
          ),
        ],
      ),
    );
  }
}

/// L'encart de vérifiabilité.
///
/// # Le lien est cliquable — quand l'adresse du site est configurée
///
/// L'adresse publique de vérification est une **adresse de production** ; la
/// configuration du socle n'exposait que celle de l'API, et « construire une URL
/// en devinant l'hôte public reviendrait à écrire une adresse en dur ». Elle
/// porte `VF_WEB_BASE_URL` depuis l'arbitrage n° 138.
///
/// Quand elle est renseignée, le bouton ouvre la page **en onglet système**, avec
/// le code déjà rempli. Quand elle ne l'est pas, l'écran retombe exactement sur
/// ce qu'il faisait : l'adresse en toutes lettres et la copie du code. Aucun
/// bouton n'ouvre jamais le vide.
///
/// POURQUOI le chemin n'est plus celui du simulateur : `verify-certificate`
/// n'existe pas côté serveur, qui sert `/verifier-un-certificat`. Un lien qui
/// tombe sur un 404 sur un certificat est pire que du texte.
class _EncartDeVerifiabilite extends ConsumerWidget {
  const _EncartDeVerifiabilite({required this.code});

  final String code;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppConfig config = ref.watch(appConfigProvider);
    final Uri? page = VfWebLinks.verificationDeCertificat(config, code);

    return VfCard(
      tight: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const Icon(
                Icons.verified_user_rounded,
                size: 19,
                color: VfColors.primary,
              ),
              const SizedBox(width: VfSpacing.x9),
              Expanded(
                child: Text(
                  'Verifiable publiquement sur '
                  '${VfWebLinks.affichageVerificationDeCertificat(config)} '
                  'avec l identifiant ci-dessus.',
                  style: VfTextStyles.tXs,
                ),
              ),
            ],
          ),
          const SizedBox(height: VfSpacing.x10),
          VfGhostButton(
            label: 'Copier l identifiant',
            icon: Icons.copy_rounded,
            expanded: true,
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: code));
              if (context.mounted) {
                VfToast.show(context, message: 'Identifiant copie.');
              }
            },
          ),
          if (page != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x8),
            VfOutlinedButton(
              label: 'Ouvrir la page de vérification',
              icon: Icons.open_in_new_rounded,
              onPressed: () async {
                // Onglet systeme, jamais une WebView : l'adresse doit rester
                // visible, c'est tout l'interet d'une page de verification.
                final bool ouvert = await launchUrl(
                  page,
                  mode: LaunchMode.externalApplication,
                );
                if (!ouvert && context.mounted) {
                  VfToast.error(
                    context,
                    'La page de vérification n a pas pu être ouverte.',
                  );
                }
              },
            ),
          ],
        ],
      ),
    );
  }
}

class _Squelette extends StatelessWidget {
  const _Squelette();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.all(VfSpacing.screenInline),
      child: VfSkeleton(height: 300, width: double.infinity),
    );
  }
}

/// Le certificat n'a pas été trouvé.
///
/// « Le mot du prototype web est *Certificat introuvable*. **On n'écrit jamais
/// "faux"** — un code mal recopié n'est pas une fraude, et l'accusation serait
/// injuste comme inexacte. Et le 404 du contrat couvre aussi "pas à vous" :
/// raison de plus pour ne rien affirmer. » (arbitrage n° 65)
class _Introuvable extends StatelessWidget {
  const _Introuvable({required this.erreur});

  final Object erreur;

  @override
  Widget build(BuildContext context) {
    final bool introuvable = erreur is NotFoundFailure;
    return Padding(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      child: VfEmptyState(
        kind: VfEmptyKind.aucunResultat,
        icon: Icons.workspace_premium_outlined,
        title: introuvable ? 'Certificat introuvable' : 'Chargement impossible',
        message: introuvable
            ? 'Verifiez l identifiant : l alphabet ne contient ni O, ni I, ni S.'
            : 'Le certificat n a pas pu être relu, et il n est pas encore '
                  'enregistre sur cet appareil.',
        action: VfGhostButton(
          label: 'Revenir au catalogue',
          onPressed: () => context.go(VfRoutes.catalogue),
        ),
      ),
    );
  }
}
