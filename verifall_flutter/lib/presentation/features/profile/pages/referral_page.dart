import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/referral_parrainage.dart';
import '../../../shared/shared.dart';
import '../providers/referral_provider.dart';
import '../widgets/referral_widgets.dart';

/// Écran 29 · Mon parrainage.
///
/// # Le premier compteur ne dit pas ce que la maquette disait
///
/// « Invitations envoyées » devient **« Invitations ouvertes »**. Le contrat
/// tranche : « un partage WhatsApp ne se constate pas depuis un serveur :
/// compter les ENVOIS serait inventer un chiffre ». Le seul compteur honnête est
/// le nombre de personnes qui ont **ouvert** le lien.
///
/// # Le quatrième indicateur a changé de nature
///
/// La maquette affiche « Unités par filleul », une constante de barème. Le
/// serveur ne la sert pas ; il sert le nombre de **filleuls inscrits**. C'est ce
/// qu'on affiche : un chiffre mesuré à la place d'un chiffre écrit en dur.
class ReferralPage extends ConsumerStatefulWidget {
  const ReferralPage({super.key});

  @override
  ConsumerState<ReferralPage> createState() => _ReferralPageState();
}

class _ReferralPageState extends ConsumerState<ReferralPage> {
  bool _partageEnCours = false;

  @override
  Widget build(BuildContext context) {
    final AsyncValue<Parrainage> etat = ref.watch(referralProvider);

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Parrainage'),
      body: etat.when(
        loading: () => const _Squelette(),
        error: (Object erreur, StackTrace _) => VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.group_add_outlined,
          title: 'Parrainage indisponible',
          message: erreur is Failure
              ? (erreur.serverMessage ??
                    'Votre code de parrainage n a pas pu être lu.')
              : 'Votre code de parrainage n a pas pu être lu.',
          action: VfGhostButton(
            label: 'Réessayer',
            onPressed: () => ref.read(referralProvider.notifier).rafraichir(),
          ),
        ),
        data: _corps,
      ),
    );
  }

  Widget _corps(Parrainage parrainage) {
    return RefreshIndicator(
      onRefresh: () => ref.read(referralProvider.notifier).rafraichir(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: <Widget>[
          VfKpiGrid(
            children: <Widget>[
              // Le libelle dit ce qu'il compte REELLEMENT.
              VfKpiCard(
                value: '${parrainage.indicateurs.visites}',
                label: 'Invitations ouvertes',
              ),
              VfKpiCard(
                value: '${parrainage.indicateurs.actifs}',
                label: 'Filleuls actifs',
                accent: VfColors.verdictTrue,
              ),
              // Vient de l'API, jamais d'une multiplication locale.
              VfKpiCard(
                value: '${parrainage.indicateurs.points}',
                label: 'Unités gagnees',
              ),
              VfKpiCard(
                value: '${parrainage.indicateurs.inscrits}',
                label: 'Filleuls inscrits',
              ),
            ],
          ),
          const SizedBox(height: VfSpacing.stack),
          VfReferralCard(
            parrainage: parrainage,
            partageEnCours: _partageEnCours,
            onCopier: () => _copier(parrainage.lien),
            onWhatsApp: () => _partager(parrainage.lien),
          ),
          const SizedBox(height: VfSpacing.stack),
          const VfReferralHowTo(),
          const SizedBox(height: VfSpacing.stack),
          Text('Mes invitations', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.stackSm),
          if (parrainage.filleuls.isEmpty)
            const VfEmptyState(
              kind: VfEmptyKind.rienEncore,
              icon: Icons.person_add_alt_outlined,
              title: 'Vous n avez pas encore de filleul',
              message:
                  'Une ligne apparaît ici quand quelqu un cree son compte avec '
                  'votre code. Vos unités, elles, arrivent a sa premiere '
                  'verification.',
            )
          else
            VfMenuList(
              children: <Widget>[
                for (final Filleul filleul in parrainage.filleuls)
                  VfInviteeTile(filleul: filleul),
              ],
            ),
        ],
      ),
    );
  }

  Future<void> _copier(String lien) async {
    await Clipboard.setData(ClipboardData(text: lien));
    if (mounted) {
      // Le toast RAPPELLE le lien, comme le simulateur : sans lui, on ne sait
      // pas ce qui a ete copie.
      VfToast.show(context, message: 'Lien de parrainage copie : $lien');
    }
  }

  /// Partage sur WhatsApp.
  ///
  /// # Pourquoi `wa.me` avec un repli sur le partage système
  ///
  /// Le bouton dit « Inviter sur WhatsApp » ; ouvrir la feuille de partage
  /// générique ne tiendrait pas cette promesse. `https://wa.me/?text=` est
  /// l'adresse publique documentée de WhatsApp — ce n'est pas une adresse de
  /// Vérif'All composée côté client. Quand elle ne peut pas s'ouvrir — WhatsApp
  /// absent —, on retombe sur `share_plus`, qui est la dépendance nommée par le
  /// point de vigilance de l'écran.
  ///
  /// Le **message** vient de `POST /me/referrals/invite` : il est rédigé par le
  /// serveur, et l'appel est fait ici, au geste, plutôt qu'à l'ouverture de
  /// l'écran — « il ne compte rien », donc l'émettre plus tôt n'apporterait
  /// qu'un aller-retour de plus sur un écran qu'on ouvre parfois sans réseau.
  Future<void> _partager(String lienDuCache) async {
    setState(() => _partageEnCours = true);
    String lien = lienDuCache;
    String? message;

    try {
      final Invitation invitation = await ref
          .read(referralProvider.notifier)
          .preparerLInvitation();
      lien = invitation.lien;
      message = invitation.message;
    } on Failure {
      // Hors ligne, on partage le lien deja connu : c'est exactement ce que le
      // cache sert a permettre. Le message redige par le serveur, lui, manque —
      // on n'en fabrique pas un a sa place.
      message = null;
    }

    final String texte = message == null ? lien : '$message\n$lien';

    try {
      final Uri whatsapp = Uri.https('wa.me', '/', <String, String>{
        'text': texte,
      });
      final bool ouvert = await launchUrl(
        whatsapp,
        mode: LaunchMode.externalApplication,
      );
      if (!ouvert) {
        await SharePlus.instance.share(ShareParams(text: texte));
      }
    } on Object {
      await SharePlus.instance.share(ShareParams(text: texte));
    } finally {
      if (mounted) {
        setState(() => _partageEnCours = false);
      }
    }
  }
}

class _Squelette extends StatelessWidget {
  const _Squelette();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        VfSkeleton(height: 76, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 300, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 180, borderRadius: VfRadii.lgAll),
      ],
    );
  }
}
