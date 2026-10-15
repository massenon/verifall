import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:infinite_scroll_pagination/infinite_scroll_pagination.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../data/datasources/remote/verify/verification_remote_source.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../shared/vf_app_bar.dart';
import '../../../shared/vf_bottom_sheet.dart';
import '../../../shared/vf_buttons.dart';
import '../../../shared/vf_display.dart';
import '../../../shared/vf_inputs.dart';
import '../../../shared/vf_points_pill.dart';
import '../providers/verify_providers.dart';
import '../widgets/verification_tile.dart';
import '../../../../core/format/vf_formats.dart';
import '../../../../core/session/solde_unites.dart';

/// Écran 12 · **Mes vérifications** — `#/history`.
///
/// # Pagination par curseur, et rien d'autre
///
/// `page` **n'existe pas** : « sur un fil qui bouge, le décalage ment — trois
/// analyses lancées entre deux pages décalent tout, et l'élément sauté peut être
/// précisément le rapport qu'on cherchait » (arbitrage n° 68).
///
/// Conséquences visibles à l'écran : **il n'y a pas de total**. Le décompte
/// compte les éléments **chargés**, et le libellé le dit honnêtement plutôt que
/// de promettre un total que l'API ne donne pas.
///
/// # Les filtres partent au serveur
///
/// Jamais en local : « filtrer localement ne filtre que la page déjà chargée ; à
/// la deuxième page, l'utilisateur voit apparaître des éléments qui ne
/// correspondent pas à sa sélection » (arbitrage n° 82).
class VerificationHistoryPage extends ConsumerWidget {
  const VerificationHistoryPage({super.key});

  /// Les pastilles du simulateur. « Tous » n'envoie **rien** — omettre le
  /// paramètre signifie « aucun filtre » (§3.6).
  static const List<({String libelle, String? verdict})> pastilles =
      <({String libelle, String? verdict})>[
        (libelle: 'Tous', verdict: null),
        (libelle: 'FAUX', verdict: 'false'),
        (libelle: 'TROMPEUR', verdict: 'misleading'),
        (libelle: 'VRAI', verdict: 'true'),
      ];

  /// La feuille de filtres porte **les quatre verdicts servis**, dont
  /// « Non vérifiable » que les pastilles du simulateur omettent. C'est une
  /// valeur réelle de l'énumération serveur : la taire rendrait ces analyses
  /// introuvables par filtre.
  static const List<({String libelle, String? verdict})> filtresComplets =
      <({String libelle, String? verdict})>[
        (libelle: 'Tous les verdicts', verdict: null),
        (libelle: 'Faux', verdict: 'false'),
        (libelle: 'Trompeur', verdict: 'misleading'),
        (libelle: 'Vrai', verdict: 'true'),
        (libelle: 'Non verifiable', verdict: 'unverifiable'),
      ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PagingState<String, Verification> page = ref.watch(
      historiqueProvider,
    );
    final FiltresHistorique filtres = ref.watch(filtresHistoriqueProvider);
    final int? solde = ref.watch(soldeUnitesProvider);
    final List<Verification> elements =
        page.pages?.expand((List<Verification> p) => p).toList() ??
        const <Verification>[];

    final bool aucunReseau = page.error != null && elements.isEmpty;

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: VfAppBar(
        title: 'Mes vérifications',
        leading: context.canPop()
            ? VfAppBarLeading.retour
            : VfAppBarLeading.aucun,
        pointsPill: VfPointsPill(
          solde: solde,
          onTap: () => context.push(VfRoutes.wallet),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.read(historiqueProvider.notifier).recharger();
          ref.invalidate(indicateursProvider);
        },
        child: CustomScrollView(
          slivers: <Widget>[
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(
                VfSpacing.screenInline,
                VfSpacing.screenTopExtra,
                VfSpacing.screenInline,
                0,
              ),
              sliver: SliverList.list(
                children: <Widget>[
                  const _Indicateurs(),
                  const SizedBox(height: VfSpacing.stack),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: VfSearchField(
                          hint: 'Rechercher une vérification...',
                          initialValue: filtres.recherche,
                          // 350 ms, exactement comme cote web.
                          debounce: const Duration(milliseconds: 350),
                          onSearch: ref
                              .read(filtresHistoriqueProvider.notifier)
                              .rechercher,
                        ),
                      ),
                      const SizedBox(width: VfSpacing.x8),
                      // Un bouton-icone plutot qu'un bouton a libelle vide : a
                      // 200 % de taille de texte, un carre de 52 ne contient
                      // plus l'icone ET ses marges, et la ligne deborde.
                      VfIconButton(
                        icon: Icons.tune_rounded,
                        tooltip: 'Ouvrir les filtres',
                        onPressed: () => _ouvrirLesFiltres(context, ref),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SliverToBoxAdapter(child: SizedBox(height: VfSpacing.x12)),
            SliverToBoxAdapter(
              child: VfChips(
                labels: <String>[
                  for (final ({String libelle, String? verdict}) p in pastilles)
                    p.libelle,
                ],
                selectedIndex: pastilles.indexWhere(
                  (({String libelle, String? verdict}) p) =>
                      p.verdict == filtres.verdict,
                ),
                onSelected: (int i) => ref
                    .read(filtresHistoriqueProvider.notifier)
                    .verdict(pastilles[i].verdict),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(
                VfSpacing.screenInline,
                VfSpacing.x12,
                VfSpacing.screenInline,
                VfSpacing.x8,
              ),
              sliver: SliverToBoxAdapter(
                child: Semantics(
                  liveRegion: true,
                  child: Text(
                    // « Il n'y a PAS de total » : le libelle compte ce qui est
                    // charge, et le dit. Promettre un total que l'API ne donne
                    // pas serait un chiffre invente.
                    elements.length <= 1
                        ? '${elements.length} résultat charge'
                        : '${elements.length} résultats charges',
                    style: VfTextStyles.tXs,
                  ),
                ),
              ),
            ),
            if (aucunReseau)
              SliverToBoxAdapter(child: _HorsLigne(page: page))
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(
                  VfSpacing.screenInline,
                  0,
                  VfSpacing.screenInline,
                  VfSpacing.screenBottomExtra,
                ),
                sliver: PagedSliverList<String, Verification>.separated(
                  state: page,
                  fetchNextPage: ref
                      .read(historiqueProvider.notifier)
                      .pageSuivante,
                  separatorBuilder: (BuildContext context, int index) =>
                      const SizedBox(height: VfSpacing.stackSm),
                  builderDelegate: PagedChildBuilderDelegate<Verification>(
                    itemBuilder:
                        (BuildContext context, Verification v, int index) =>
                            VerificationTile(
                              verification: v,
                              // Toucher une ligne mene AU RAPPORT s'il existe,
                              // AU SUIVI sinon (arbitrage n. 31).
                              onTap: () => context.push(
                                v.aUnRapport
                                    ? VfRoutes.report(v.uuid)
                                    : VfRoutes.verification(v.uuid),
                              ),
                            ),
                    firstPageProgressIndicatorBuilder: (BuildContext _) =>
                        const _Squelettes(),
                    // Une erreur de page suivante est UNE LIGNE en pied de
                    // liste, jamais un ecran d'erreur qui efface ce qui etait
                    // deja affiche.
                    newPageErrorIndicatorBuilder: (BuildContext _) => Padding(
                      padding: const EdgeInsets.all(VfSpacing.x16),
                      child: VfGhostButton(
                        label: 'Réessayer',
                        onPressed: ref
                            .read(historiqueProvider.notifier)
                            .pageSuivante,
                      ),
                    ),
                    // LES DEUX ETATS VIDES, et c'est une exigence : « Aucun
                    // resultat » et « rien encore fait » n'appellent pas la
                    // meme action.
                    noItemsFoundIndicatorBuilder: (BuildContext _) =>
                        _aucunElement(context, ref, filtres),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _aucunElement(
    BuildContext context,
    WidgetRef ref,
    FiltresHistorique filtres,
  ) {
    final bool filtre =
        filtres.verdict != null || filtres.recherche.trim().isNotEmpty;

    return filtre
        ? VfEmptyState(
            kind: VfEmptyKind.aucunResultat,
            title: 'Aucun résultat',
            message: 'Modifiez la recherche ou retirez les filtres.',
            action: VfGhostButton(
              label: 'Reinitialiser les filtres',
              expanded: false,
              onPressed: ref
                  .read(filtresHistoriqueProvider.notifier)
                  .reinitialiser,
            ),
          )
        : VfEmptyState(
            kind: VfEmptyKind.rienEncore,
            icon: Icons.science_outlined,
            title: 'Votre laboratoire est encore vide',
            message: 'Lancez votre premiere vérification.',
            action: VfPrimaryButton(
              label: 'Vérifier une information',
              expanded: false,
              onPressed: () => context.go(VfRoutes.verify),
            ),
          );
  }

  /// « Les filtres passent par un **bottom sheet** sur mobile, jamais par des
  /// menus déroulants alignés » (point de vigilance du simulateur).
  Future<void> _ouvrirLesFiltres(BuildContext context, WidgetRef ref) async {
    final FiltresHistorique courant = ref.read(filtresHistoriqueProvider);
    await VfBottomSheet.show<void>(
      context: context,
      title: 'Filtrer',
      builder: (BuildContext sheet) => Padding(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.x16,
          0,
          VfSpacing.x16,
          VfSpacing.x16,
        ),
        child: VfMenuList(
          children: <Widget>[
            for (final ({String libelle, String? verdict}) f in filtresComplets)
              VfNavTile(
                label: f.libelle,
                trailing: f.verdict == courant.verdict
                    ? const Icon(
                        Icons.check_rounded,
                        size: 18,
                        color: VfColors.primary,
                      )
                    : null,
                onTap: () {
                  ref
                      .read(filtresHistoriqueProvider.notifier)
                      .verdict(f.verdict);
                  Navigator.of(sheet).pop();
                },
              ),
          ],
        ),
      ),
    );
  }
}

/// Les quatre KPI — **sur tout l'historique**, jamais sur la page filtrée.
///
/// « Ce sont des repères sur son activité ; les faire suivre les filtres leur
/// ferait changer de sens » (arbitrage n° 31). Ils viennent donc de
/// `GET /me/stats`, pas d'un comptage sur la liste — que la pagination par
/// curseur rendrait de toute façon impossible.
class _Indicateurs extends ConsumerWidget {
  const _Indicateurs();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<IndicateursHistorique> etat = ref.watch(
      indicateursProvider,
    );
    final int? solde = ref.watch(soldeUnitesProvider);

    return VfKpiGrid(
      children: <Widget>[
        VfKpiCard(
          value: etat.hasValue ? '${etat.requireValue.total}' : '—',
          label: 'Total',
        ),
        VfKpiCard(
          value: etat.hasValue ? '${etat.requireValue.intoxDemasquees}' : '—',
          label: 'Intox demasquees',
          accent: VfColors.verdictFalse,
        ),
        VfKpiCard(
          value: etat.hasValue ? '${etat.requireValue.confirmeesFiables}' : '—',
          label: 'Confirmees fiables',
          accent: VfColors.verdictTrue,
        ),
        // Le solde ne vient JAMAIS du cache : un tiret tant qu'il n'a pas ete
        // relu, jamais un zero.
        VfKpiCard(value: solde?.toString() ?? '—', label: 'Unités restantes'),
      ],
    );
  }
}

/// La liste servie depuis Drift, avec son bandeau d'horodatage.
///
/// **La recherche et les filtres ne s'appliquent pas ici**, et l'écran le dit :
/// filtrer un cache partiel donnerait une liste plausible et fausse. Le
/// dégradé est assumé et annoncé.
class _HorsLigne extends ConsumerWidget {
  const _HorsLigne({required this.page});

  final PagingState<String, Verification> page;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<({List<Verification> liste, DateTime? luLe})> cache = ref
        .watch(historiqueEnCacheProvider);

    return cache.when(
      loading: () => const _Squelettes(),
      error: (Object echec, StackTrace pile) => VfEmptyState(
        kind: VfEmptyKind.rienEncore,
        icon: Icons.wifi_off_rounded,
        title: 'Liste indisponible',
        message: 'Aucune donnee locale a afficher.',
        action: VfGhostButton(
          label: 'Réessayer',
          expanded: false,
          onPressed: ref.read(historiqueProvider.notifier).recharger,
        ),
      ),
      data: (({List<Verification> liste, DateTime? luLe}) c) {
        if (c.liste.isEmpty) {
          return VfEmptyState(
            kind: VfEmptyKind.rienEncore,
            icon: Icons.wifi_off_rounded,
            title: 'Pas de connexion',
            message:
                'Aucune vérification n a encore ete enregistree sur cet '
                'appareil.',
            action: VfGhostButton(
              label: 'Réessayer',
              expanded: false,
              onPressed: ref.read(historiqueProvider.notifier).recharger,
            ),
          );
        }
        return Padding(
          padding: const EdgeInsets.fromLTRB(
            VfSpacing.screenInline,
            0,
            VfSpacing.screenInline,
            VfSpacing.screenBottomExtra,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              _BandeauCache(luLe: c.luLe),
              const SizedBox(height: VfSpacing.stack),
              for (final Verification v in c.liste) ...<Widget>[
                VerificationTile(
                  verification: v,
                  onTap: () => context.push(
                    v.aUnRapport
                        ? VfRoutes.report(v.uuid)
                        : VfRoutes.verification(v.uuid),
                  ),
                ),
                const SizedBox(height: VfSpacing.stackSm),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _BandeauCache extends StatelessWidget {
  const _BandeauCache({required this.luLe});

  final DateTime? luLe;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(VfSpacing.x12),
    decoration: BoxDecoration(
      color: VfColors.canvas,
      borderRadius: VfRadii.mdAll,
      border: Border.all(color: VfColors.line),
    ),
    child: Text(
      '${VfFormats.bandeauDeFraicheur(luLe)} La recherche et les filtres demandent le '
      'réseau : cette liste est celle du dernier chargement.',
      style: VfTextStyles.tXs,
    ),
  );
}

class _Squelettes extends StatelessWidget {
  const _Squelettes();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.symmetric(horizontal: VfSpacing.screenInline),
    child: Column(
      children: <Widget>[
        VfSkeleton(height: 76),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 76),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 76),
      ],
    ),
  );
}
