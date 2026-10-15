import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/article_entity.dart';
import '../../../../domain/entities/opportunity_entity.dart';
import '../../../shared/shared.dart';
import '../providers/hub_providers.dart';
import '../widgets/etats_m4.dart';
import '../widgets/hub_widgets.dart';
import '../../../../core/session/solde_unites.dart';

/// Écran 22 — **Hub opportunités**.
///
/// # Trois rubriques, et pas quatre
///
/// Actualités · Opportunités Bénin · Opportunités Internationales.
///
/// **Les portails officiels ne sont plus affichés côté citoyen** (arbitrage
/// n° 54) : « les données restent en base, l'écran d'administration reste utile
/// — c'est une mise en retrait décidée, pas un abandon. Ne pas les rajouter au
/// mobile. »
///
/// # L'archivage à J+7 est affiché, jamais calculé
///
/// `data.expiree` et `data.jours_restants` viennent du serveur ; **l'ordre de la
/// liste aussi**. Le simulateur trie et compare des dates parce qu'il tourne
/// sans serveur : « l'ordre ne doit pas être recalculé localement à partir de
/// dates, sans quoi le curseur et l'affichage divergeraient ».
class HubPage extends ConsumerWidget {
  const HubPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final RubriqueHub rubrique = ref.watch(rubriqueHubProvider);

    return Column(
      children: <Widget>[
        VfAppBar(
          title: 'Hub opportunités',
          leading: context.canPop()
              ? VfAppBarLeading.retour
              : VfAppBarLeading.aucun,
          // Le solde ne vient JAMAIS du cache (05-HORS-LIGNE §5.3) : il vient
          // de la source unique du socle, alimentee par les seules lectures
          // reseau (arbitrage n° 135). Tant qu'aucune n'a eu lieu, la pastille
          // affiche un tiret — « jamais un zero, qui ferait croire a un compte
          // vide ».
          pointsPill: VfPointsPill(
            solde: ref.watch(soldeUnitesProvider),
            onTap: () => context.push(VfRoutes.wallet),
          ),
        ),
        Expanded(
          child: CustomScrollView(
            slivers: <Widget>[
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(
                  VfSpacing.screenInline,
                  VfSpacing.screenTopExtra,
                  VfSpacing.screenInline,
                  0,
                ),
                sliver: const SliverToBoxAdapter(child: _EnTeteHub()),
              ),
              SliverPadding(
                padding: const EdgeInsets.only(top: VfSpacing.stack),
                sliver: SliverToBoxAdapter(
                  child: VfChips(
                    labels: <String>[
                      for (final RubriqueHub r in RubriqueHub.values) r.libelle,
                    ],
                    selectedIndex: RubriqueHub.values.indexOf(rubrique),
                    onSelected: (int index) => ref
                        .read(rubriqueHubProvider.notifier)
                        .choisir(RubriqueHub.values[index]),
                  ),
                ),
              ),
              // POURQUOI cette zone n'a PAS de marge laterale : les pastilles
              // de type vont bord a bord, comme dans le simulateur — « un
              // detail visuel, mais le reproduire evite la sensation de liste
              // tronquee » (§2.2). Chaque autre bloc pose sa propre marge.
              SliverPadding(
                padding: const EdgeInsets.only(
                  top: VfSpacing.stack,
                  bottom: VfSpacing.screenBottomExtra,
                ),
                sliver: rubrique == RubriqueHub.actualites
                    ? const _RubriqueActualites()
                    : _RubriqueOpportunites(portee: rubrique.portee!),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _EnTeteHub extends StatelessWidget {
  const _EnTeteHub();

  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        VfEyebrow('Verifie par la redaction'),
        SizedBox(height: VfSpacing.x4),
        Text('Hub opportunités', style: VfTextStyles.hLg),
        SizedBox(height: VfSpacing.x6),
        Text(
          'L information et les occasions a saisir, controlees a la source.',
          style: VfTextStyles.tSm,
        ),
      ],
    );
  }
}

/// La rubrique « Actualités » : une carte à la une, puis les brèves.
class _RubriqueActualites extends ConsumerWidget {
  const _RubriqueActualites();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final EtatArticles etat = ref.watch(hubArticlesProvider);
    final HubArticlesNotifier notifier = ref.read(hubArticlesProvider.notifier);

    if (etat.chargementInitial) {
      return const SliverPadding(
        padding: EdgeInsets.symmetric(horizontal: VfSpacing.screenInline),
        sliver: SliverToBoxAdapter(child: SqueletteDeCarte(lignes: 4)),
      );
    }

    if (etat.articles.isEmpty) {
      return SliverToBoxAdapter(
        child: etat.echec != null
            ? EtatEchec(
                echec: etat.echec!,
                onReessayer: notifier.charger,
                messageHorsLigne:
                    'Les breves déjà consultees restent lisibles depuis cet '
                    'appareil.',
              )
            // L'arbitrage n° 51 donne le ton juste : dire franchement que
            // seules les breves CONTROLEES sont publiees, et qu'aucune ne l'est
            // aujourd'hui.
            : const VfEmptyState(
                kind: VfEmptyKind.rienEncore,
                icon: Icons.article_outlined,
                title: 'Aucune breve publiee pour le moment',
                message:
                    'Le Hub ne publie que ce que la redaction a verifie. Rien '
                    'n a encore ete valide aujourd hui.',
              ),
      );
    }

    final ArticleHub? uneArticle = etat.aLaUne;
    final List<ArticleHub> suite = etat.suite;

    return SliverPadding(
      // La rubrique « Actualites » ne porte pas de pastilles de type : elle
      // reprend donc la marge laterale ordinaire de l'ecran.
      padding: const EdgeInsets.symmetric(horizontal: VfSpacing.screenInline),
      sliver: SliverList.list(
        children: <Widget>[
          if (etat.recupereLe != null) ...<Widget>[
            VfStaleDataBanner(
              lueLe: etat.recupereLe!,
              tone: VfStaleTone.echeance,
              onRetry: notifier.charger,
            ),
            const SizedBox(height: VfSpacing.stack),
          ],
          if (uneArticle != null) ...<Widget>[
            VfLeadArticleCard(
              article: uneArticle,
              onTap: () => context.push(VfRoutes.hubArticle(uneArticle.slug)),
            ),
            const SizedBox(height: VfSpacing.stack),
          ],
          const VfSectionHeader(
            title: 'Les informations importantes, vérifiées',
          ),
          const SizedBox(height: VfSpacing.x10),
          for (final ArticleHub article in suite)
            Padding(
              padding: const EdgeInsets.only(bottom: VfSpacing.stackSm),
              child: VfNewsCard(
                article: article,
                onTap: () => context.push(VfRoutes.hubArticle(article.slug)),
              ),
            ),
          if (etat.aUneSuite)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: VfSpacing.x12),
              child: VfGhostButton(
                label: 'Voir plus de breves',
                onPressed: notifier.chargerLaSuite,
              ),
            ),
        ],
      ),
    );
  }
}

/// Une rubrique d'opportunités.
class _RubriqueOpportunites extends ConsumerWidget {
  const _RubriqueOpportunites({required this.portee});

  final PorteeOpportunite portee;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final EtatOpportunites etat = ref.watch(hubOpportunitesProvider(portee));
    final HubOpportunitesNotifier notifier = ref.read(
      hubOpportunitesProvider(portee).notifier,
    );

    if (etat.chargementInitial) {
      return const SliverPadding(
        padding: EdgeInsets.symmetric(horizontal: VfSpacing.screenInline),
        sliver: SliverToBoxAdapter(child: SqueletteDeCarte(lignes: 3)),
      );
    }

    // Les pastilles de type sont lues du CONTENU REEL, jamais codees en dur :
    // « proposer un type sans offre mene a une liste vide, et une liste vide
    // derriere un filtre passe pour un bug » (arbitrage n° 55).
    final List<TypeVu> types = etat.typesVus;
    final int indexType = etat.typeActif == null
        ? 0
        : types.indexWhere((TypeVu t) => t.type == etat.typeActif) + 1;

    return SliverList.list(
      children: <Widget>[
        // Chaque bloc pose sa marge laterale ; seules les pastilles vont bord
        // a bord.
        if (etat.recupereLe != null) ...<Widget>[
          _marge(
            VfStaleDataBanner(
              lueLe: etat.recupereLe!,
              tone: VfStaleTone.echeance,
              onRetry: notifier.charger,
            ),
          ),
          const SizedBox(height: VfSpacing.stack),
        ],
        if (types.isNotEmpty) ...<Widget>[
          VfChips(
            labels: <String>['Tous', for (final TypeVu t in types) t.libelle],
            selectedIndex: indexType < 0 ? 0 : indexType,
            // LE FILTRE PART AU SERVEUR : `?type=`. Filtrer localement ne
            // filtrerait que la page chargee (arbitrage n° 82).
            onSelected: (int index) =>
                notifier.choisirType(index == 0 ? null : types[index - 1].type),
          ),
          const SizedBox(height: VfSpacing.stack),
        ],
        _marge(_LigneDeDecompte(etat: etat)),
        const SizedBox(height: VfSpacing.x10),
        if (etat.opportunites.isEmpty)
          _marge(
            etat.echec != null
                ? EtatEchec(
                    echec: etat.echec!,
                    onReessayer: notifier.charger,
                    messageHorsLigne:
                        'Les offres déjà consultees restent lisibles depuis '
                        'cet appareil.',
                  )
                : const VfEmptyState(
                    kind: VfEmptyKind.aucunResultat,
                    icon: Icons.work_off_outlined,
                    title: 'Aucune offre dans cette catégorie',
                    message:
                        'Revenez bientot : de nouvelles offres sont vérifiées '
                        'chaque semaine.',
                  ),
          )
        else
          for (final Opportunite offre in etat.opportunites)
            _marge(
              Padding(
                padding: const EdgeInsets.only(bottom: VfSpacing.stackSm),
                child: VfOpportunityCard(
                  offre: offre,
                  onTap: () =>
                      context.push(VfRoutes.hubOpportunity(offre.slug)),
                ),
              ),
            ),
        if (etat.aUneSuite)
          _marge(
            Padding(
              padding: const EdgeInsets.symmetric(vertical: VfSpacing.x12),
              child: VfGhostButton(
                label: 'Voir plus d offres',
                onPressed: notifier.chargerLaSuite,
              ),
            ),
          ),
        const SizedBox(height: VfSpacing.stack),
        // Les deux encarts de pied, TOUJOURS presents sous une rubrique
        // d'opportunites.
        _marge(const VfHubFooterNotices()),
      ],
    );
  }

  /// La marge laterale ordinaire de l'ecran.
  static Widget _marge(Widget enfant) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: VfSpacing.screenInline),
    child: enfant,
  );
}

/// La ligne de décompte au-dessus des cartes.
///
/// # Ce qu'elle dit, et ce qu'elle ne dit plus
///
/// Le simulateur affiche « `<n>` opportunité(s) ouverte(s) » **et** « `<m>`
/// archivée(s) automatiquement ». Le second compteur **a disparu**, et c'est
/// une décision : une offre archivée n'est plus servie par l'API — « une
/// opportunité non publiée répond `404`, même en connaissant le slug ». Le
/// client ne peut donc pas les compter, et aucun champ ne les compte pour lui.
/// L'afficher aurait voulu dire fabriquer un chiffre.
///
/// Le premier compteur, lui, est un décompte **chargé**, jamais un total : « il
/// n'y a pas de total » au contrat (§3.1), et le libellé le dit quand une suite
/// existe plutôt que de promettre ce que l'API ne donne pas.
class _LigneDeDecompte extends StatelessWidget {
  const _LigneDeDecompte({required this.etat});

  final EtatOpportunites etat;

  @override
  Widget build(BuildContext context) {
    final int n = etat.nbOuvertesChargees;
    final String pluriel = n > 1 ? 's' : '';
    final String texte = etat.aUneSuite
        ? '$n opportunite$pluriel ouverte$pluriel affichee$pluriel'
        : '$n opportunite$pluriel ouverte$pluriel';

    return Row(
      children: <Widget>[Expanded(child: Text(texte, style: VfTextStyles.tXs))],
    );
  }
}
