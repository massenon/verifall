import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/tool_outil.dart';
import '../../../shared/vf_app_bar.dart';
import '../../../shared/vf_buttons.dart';
import '../../../shared/vf_card.dart';
import '../../../shared/vf_display.dart';
import '../../../shared/vf_toast.dart';
import '../providers/verify_providers.dart';

/// Écran 13 · **Répertoire d'outils** — `#/tools`.
///
/// # Deux précisions du contrat qui changent l'implémentation
///
/// 1. **La liste n'est pas paginée.** C'est l'une des quatre listes « fermées,
///    tenues à la main par l'équipe » : « y mettre un curseur compliquerait le
///    client pour une pagination qui ne se déclencherait jamais » (§3.3). Il n'y
///    a donc **aucun curseur ici**, et `ApiClient.getPage` refuserait d'ailleurs
///    ce chemin par assertion.
/// 2. **Le filtre par catégorie est local**, sur la liste entière. Le serveur
///    sait filtrer (`GET /tools?category=`), mais un filtre distant ne
///    fonctionnerait pas hors ligne — et l'annuaire est précisément consultable
///    hors ligne pendant 24 h. L'interdiction de filtrer côté client ne vise que
///    les listes **paginées** (arbitrage n° 82), où filtrer localement ne filtre
///    que la page chargée.
///
/// # Les pastilles viennent du contenu, jamais d'une liste écrite à la main
///
/// Elles sont alimentées par `meta.categories`, que le serveur lit en base :
/// « deux listes finissent par diverger, et c'est le filtre qui cesse de trouver
/// quoi que ce soit » (arbitrage n° 55).
class ToolsDirectoryPage extends ConsumerWidget {
  const ToolsDirectoryPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<AnnuaireOutils> etat = ref.watch(toolsProvider);
    final String? categorie = ref.watch(categorieOutilsProvider);

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: VfAppBar(
        title: 'Outils OSINT',
        leading: context.canPop()
            ? VfAppBarLeading.retour
            : VfAppBarLeading.aucun,
      ),
      body: etat.when(
        loading: () => const _Squelettes(),
        error: (Object echec, StackTrace pile) => VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.wifi_off_rounded,
          title: 'Répertoire indisponible',
          message:
              'Aucun outil n a encore ete enregistre sur cet appareil. '
              'Reessayez avec une connexion.',
          action: VfGhostButton(
            label: 'Réessayer',
            expanded: false,
            onPressed: () => ref.invalidate(toolsProvider),
          ),
        ),
        data: (AnnuaireOutils annuaire) =>
            _corps(context, ref, annuaire, categorie),
      ),
    );
  }

  Widget _corps(
    BuildContext context,
    WidgetRef ref,
    AnnuaireOutils annuaire,
    String? categorie,
  ) {
    // « Tous » plus les categories effectivement presentes. Elles viennent de
    // meta.categories ; a defaut, on les deduit des outils recus — jamais d'une
    // liste ecrite en dur.
    final List<String> categories = annuaire.categories.isNotEmpty
        ? annuaire.categories
        : <String>{
            for (final Outil o in annuaire.outils)
              if (o.categorie != null) o.categorie!,
          }.toList();

    final List<Outil> liste = categorie == null
        ? annuaire.outils
        : annuaire.outils
              .where((Outil o) => o.categorie == categorie)
              .toList(growable: false);

    return ListView(
      padding: const EdgeInsets.only(bottom: VfSpacing.screenBottomExtra),
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(
            VfSpacing.screenInline,
            VfSpacing.screenTopExtra,
            VfSpacing.screenInline,
            VfSpacing.stack,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const VfEyebrow('Outils · OSINT · Fact-checking'),
              const SizedBox(height: VfSpacing.x4 + 1),
              Text('Répertoire des outils', style: VfTextStyles.hLg),
              const SizedBox(height: VfSpacing.x6),
              Text(
                'Les instruments que nos verificateurs utilisent au quotidien, '
                'expliques simplement.',
                style: VfTextStyles.tSm,
              ),
            ],
          ),
        ),
        VfChips(
          labels: <String>['Tous', ...categories],
          selectedIndex: categorie == null
              ? 0
              : categories.indexOf(categorie) + 1,
          onSelected: (int i) => ref
              .read(categorieOutilsProvider.notifier)
              .choisir(i == 0 ? null : categories[i - 1]),
        ),
        const SizedBox(height: VfSpacing.stack),
        if (liste.isEmpty)
          VfEmptyState(
            kind: VfEmptyKind.aucunResultat,
            title: 'Aucun outil dans cette catégorie',
            message: 'Revenez a la selection complete pour tout voir.',
            action: VfGhostButton(
              label: 'Tous les outils',
              expanded: false,
              onPressed: () =>
                  ref.read(categorieOutilsProvider.notifier).choisir(null),
            ),
          )
        else
          for (final Outil outil in liste)
            Padding(
              padding: const EdgeInsets.fromLTRB(
                VfSpacing.screenInline,
                0,
                VfSpacing.screenInline,
                VfSpacing.stackSm,
              ),
              child: _CarteDOutil(outil: outil),
            ),
      ],
    );
  }
}

class _CarteDOutil extends StatelessWidget {
  const _CarteDOutil({required this.outil});

  final Outil outil;

  @override
  Widget build(BuildContext context) => VfCard(
    tight: true,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: <Widget>[
            if (outil.categorie != null) VfTag(label: outil.categorie!),
            // « Gratuit » / « Freemium » : c'est le serveur qui l'ecrit
            // (`etiquette`). Le deduire du nom de l'outil serait une
            // affirmation commerciale inventee.
            if (outil.etiquette != null)
              VfTag(label: outil.etiquette!, variant: VfTagVariant.prim),
          ],
        ),
        const SizedBox(height: VfSpacing.x6),
        Text(outil.titre, style: VfTextStyles.hSm),
        const SizedBox(height: VfSpacing.x4 + 1),
        Text(outil.description, style: VfTextStyles.tSm),
        const SizedBox(height: VfSpacing.x10 + 1),
        VfGhostButton(
          label: outil.libelleDuLien ?? 'Ouvrir l outil',
          onPressed: () => _ouvrir(context),
        ),
      ],
    ),
  );

  /// Ouvre l'outil en **onglet système**.
  ///
  /// POURQUOI jamais une WebView maison : « moins de surface d'attaque, session
  /// isolée, et le citoyen voit le vrai domaine dans la barre d'adresse avant de
  /// saisir quoi que ce soit » (§10.5). `LaunchMode.inAppBrowserView` est
  /// exactement Custom Tabs sur Android et `SFSafariViewController` sur iOS.
  ///
  /// L'adresse **vient de l'API**. Le client n'en recopie aucune : « quinze URL
  /// recopiées à la main, c'est quinze occasions d'envoyer quelqu'un ailleurs
  /// que là où on le croit » (arbitrage n° 64).
  Future<void> _ouvrir(BuildContext context) async {
    final Uri? adresse = Uri.tryParse(outil.url);
    if (adresse == null) {
      return;
    }
    final bool ouvert = await launchUrl(
      adresse,
      mode: LaunchMode.inAppBrowserView,
    );
    if (!ouvert && context.mounted) {
      // « L'ouverture d'un outil demande le reseau : le dire plutot que d'ouvrir
      // un onglet sur une page d'erreur. »
      VfToast.error(
        context,
        'Impossible d ouvrir ${outil.titre} : une connexion est necessaire.',
      );
    }
  }
}

class _Squelettes extends StatelessWidget {
  const _Squelettes();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.all(VfSpacing.screenInline),
    child: Column(
      children: <Widget>[
        VfSkeleton(height: 150),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 150),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 150),
      ],
    ),
  );
}
