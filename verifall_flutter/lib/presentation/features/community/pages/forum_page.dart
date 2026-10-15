import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/network/auth_controller.dart';
import '../../../../core/network/session_state.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/thread_entity.dart';
import '../../../shared/shared.dart';
import '../providers/forum_providers.dart';
import '../widgets/feuille_nouveau_sujet.dart';
import '../widgets/etats_m4.dart';
import '../widgets/forum_widgets.dart';

/// Écran 20 — **Forum citoyen**.
///
/// # La règle qui structure tout l'écran
///
/// **Deux jugements, jamais mélangés** (arbitrage n° 43) : l'avis de la
/// communauté est un **sondage** — la barre à trois segments ; le verdict de
/// Vérif'All est une **vérification** — le bandeau rouge.
///
/// > « Ce ne sont pas deux affichages du même chiffre. Une majorité qui se
/// > trompe reste une majorité qui se trompe : c'est même le mécanisme central
/// > de la désinformation. Une plateforme de fact-checking qui laisserait le
/// > sondage passer pour un verdict se contredirait dans son écran le plus
/// > fréquenté. »
///
/// Conséquence dans le code : `VfOfficialVerdictBanner` ne reçoit **que** un
/// `VerdictOfficiel`, et `VfVoteBar` **que** une `RepartitionVotes`. Il n'existe
/// aucun chemin par lequel l'un allumerait l'autre.
class ForumPage extends ConsumerStatefulWidget {
  const ForumPage({super.key});

  @override
  ConsumerState<ForumPage> createState() => _ForumPageState();
}

class _ForumPageState extends ConsumerState<ForumPage> {
  final ScrollController _defilement = ScrollController();

  @override
  void dispose() {
    _defilement.dispose();
    super.dispose();
  }

  Future<void> _composer() async {
    final BrouillonDeSujet? brouillon = await FeuilleNouveauSujet.ouvrir(
      context,
    );
    if (brouillon == null || !mounted) {
      return;
    }
    try {
      await ref
          .read(forumProvider.notifier)
          .publier(titre: brouillon.titre, corps: brouillon.corps);
      if (mounted) {
        VfToast.show(
          context,
          message: 'Sujet publie. La communauté peut reagir.',
        );
      }
    } on NetworkFailure {
      // « Ce qui ne fonctionne pas hors ligne, et il faut le dire » (§5.10). La
      // publication ne part PAS en file : la rejouer exposerait au doublon.
      if (mounted) {
        VfToast.error(
          context,
          'Publier un sujet demande une connexion. Reessayez une fois en ligne.',
        );
      }
    } on Failure catch (echec) {
      if (mounted) {
        VfToast.error(
          context,
          // Le message du serveur est « deja redige en francais et affichable
          // tel quel » : le client ne le reformule pas.
          echec.hasServerMessage
              ? echec.serverMessage!
              : 'Le sujet n a pas pu être publie.',
        );
      }
    }
  }

  bool _proche(ScrollNotification notification) {
    final ScrollMetrics m = notification.metrics;
    return m.pixels >= m.maxScrollExtent - 320;
  }

  @override
  Widget build(BuildContext context) {
    final EtatForum etat = ref.watch(forumProvider);
    final SessionState session = ref.watch(authControllerProvider);
    final List<RubriqueForum> pastilles = etat.pastilles;
    final int indexActif = pastilles.indexWhere(
      (RubriqueForum r) => r.slug == etat.categorieActive,
    );

    return Column(
      children: <Widget>[
        VfAppBar(
          title: 'Communauté',
          // LE BOUTON DU TIROIR, que le simulateur pose ici (§3, ecran 20). Il
          // etait absent faute d'hote : le socle en a un depuis l'arbitrage
          // n° 137, et un bouton qui n'ouvre rien reste pire qu'un bouton
          // absent — d'ou le repli sur « aucun » hors de la coque.
          leading: VfShellScope.isInsideShell(context)
              ? VfAppBarLeading.tiroir
              : VfAppBarLeading.aucun,
          actions: <Widget>[
            VfIconButton(
              icon: Icons.newspaper_rounded,
              tooltip: 'Ouvrir le Hub opportunités',
              onPressed: () => context.push(VfRoutes.hub),
            ),
          ],
        ),
        Expanded(
          child: NotificationListener<ScrollNotification>(
            onNotification: (ScrollNotification notification) {
              if (_proche(notification) && etat.aUneSuite) {
                ref.read(forumProvider.notifier).chargerLaSuite();
              }
              return false;
            },
            child: RefreshIndicator(
              onRefresh: ref.read(forumProvider.notifier).charger,
              color: VfColors.primary,
              child: CustomScrollView(
                controller: _defilement,
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
                        if (etat.recupereLe != null) ...<Widget>[
                          VfStaleDataBanner(
                            lueLe: etat.recupereLe!,
                            tone: VfStaleTone.echeance,
                            onRetry: ref.read(forumProvider.notifier).charger,
                          ),
                          const SizedBox(height: VfSpacing.stack),
                        ],
                        const VfEyebrow('Community fact-checking hub'),
                        const SizedBox(height: VfSpacing.x4),
                        const Text('Vérif All Forum', style: VfTextStyles.hLg),
                        const SizedBox(height: VfSpacing.x6),
                        const Text(
                          'Posez vos doutes, votez, et laissez la redaction '
                          'trancher les cas sensibles.',
                          style: VfTextStyles.tSm,
                        ),
                        const SizedBox(height: VfSpacing.stack),
                        VfComposerTrigger(
                          initiales: session.utilisateur?.initiale ?? '?',
                          onTap: _composer,
                        ),
                        const SizedBox(height: VfSpacing.stack),
                      ],
                    ),
                  ),
                  SliverToBoxAdapter(
                    child: VfChips(
                      labels: <String>[
                        for (final RubriqueForum r in pastilles) r.libelle,
                      ],
                      selectedIndex: indexActif < 0 ? 0 : indexActif,
                      // LE FILTRE PART AU SERVEUR. Filtrer localement ne
                      // filtrerait que la page chargee (arbitrage n° 82).
                      onSelected: (int index) => ref
                          .read(forumProvider.notifier)
                          .choisirCategorie(pastilles[index].slug),
                    ),
                  ),
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(
                      VfSpacing.screenInline,
                      VfSpacing.stack,
                      VfSpacing.screenInline,
                      VfSpacing.screenBottomExtra,
                    ),
                    sliver: _corps(etat),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _corps(EtatForum etat) {
    if (etat.chargementInitial) {
      return SliverList.separated(
        itemCount: 3,
        separatorBuilder: (_, _) => const SizedBox(height: VfSpacing.stackSm),
        itemBuilder: (_, _) => const SqueletteDeCarte(),
      );
    }

    if (etat.sujets.isEmpty) {
      return SliverToBoxAdapter(child: _vide(etat));
    }

    return SliverList.separated(
      itemCount: etat.sujets.length + (etat.aUneSuite ? 1 : 0),
      separatorBuilder: (_, _) => const SizedBox(height: VfSpacing.stackSm),
      itemBuilder: (BuildContext context, int index) {
        if (index >= etat.sujets.length) {
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: VfSpacing.x16),
            child: Center(
              child: SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
          );
        }
        final SujetForum sujet = etat.sujets[index];
        return VfThreadCard(
          sujet: sujet,
          onTap: () => context.push(VfRoutes.forumThread(sujet.slug)),
        );
      },
    );
  }

  /// Les deux états vides, **et ils sont deux** (§2.7).
  ///
  /// « Aucun résultat » et « rien encore fait » n'appellent pas la même action :
  /// le premier invite à élargir, le second à commencer. Les confondre, c'est
  /// proposer « effacer les filtres » à quelqu'un qui n'a jamais rien fait.
  Widget _vide(EtatForum etat) {
    if (etat.echec != null) {
      return EtatEchec(
        echec: etat.echec!,
        onReessayer: ref.read(forumProvider.notifier).charger,
        messageHorsLigne:
            'Les discussions déjà consultees restent lisibles depuis cet '
            'appareil.',
      );
    }
    final bool filtre = etat.categorieActive != RubriqueForum.slugToutes;
    return filtre
        ? VfEmptyState(
            kind: VfEmptyKind.aucunResultat,
            title: 'Aucune discussion dans cette catégorie',
            message: 'Revenez a la liste complete pour voir tous les sujets.',
            action: VfGhostButton(
              label: 'Voir tous les sujets',
              expanded: false,
              onPressed: () => ref
                  .read(forumProvider.notifier)
                  .choisirCategorie(RubriqueForum.slugToutes),
            ),
          )
        : VfEmptyState(
            kind: VfEmptyKind.rienEncore,
            title: 'Personne n a encore pose de question',
            message:
                'Ouvrez le premier sujet : une information qui vous semble '
                'douteuse suffit.',
            icon: Icons.forum_outlined,
            action: VfPrimaryButton(
              label: 'Ouvrir le premier sujet',
              expanded: false,
              onPressed: _composer,
            ),
          );
  }
}
