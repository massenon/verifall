import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/home_tableau_de_bord.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../../domain/repositories/dashboard_repository.dart';
import '../../../../domain/usecases/verify/drapeaux_de_fonctionnalite.dart';
import '../../../shared/vf_app_bar.dart';
import '../../../shared/vf_buttons.dart';
import '../../../shared/vf_card.dart';
import '../../../shared/vf_display.dart';
import '../../../shared/vf_points_pill.dart';
import '../../verify/widgets/verification_tile.dart';
import '../providers/dashboard_provider.dart';
import '../widgets/dashboard_widgets.dart';
import '../../../../core/format/vf_formats.dart';
import '../../../../core/session/solde_unites.dart';
import '../../../shared/vf_shell_scaffold.dart';

/// Écran 07 · **Tableau de bord** — `#/home`.
///
/// # Ce qui gouverne cet écran
///
/// **Un seul agrégat**, et c'est un point d'architecture : « la première image
/// doit tenir sous 400 ms, et six allers-retours sur un réseau mobile béninois
/// feraient de l'écran d'accueil le plus lent de l'application — celui qu'on
/// voit à chaque ouverture ».
///
/// **La cloche est masquée.** Aucun endpoint ne sert de notification :
/// l'action de la barre supérieure ne s'affiche pas. « Une cloche sans pastille
/// invite à cliquer ; une cloche qui ouvre un écran vide se lit comme un
/// défaut. »
///
/// **Le solde ne vient jamais du cache.** Servi par Drift, l'agrégat n'en porte
/// pas : la pastille et la tuile « Unités disponibles » affichent alors un
/// tiret. « On relit cet écran précisément parce qu'on attend un changement. »
///
/// # Et la dépendance qui n'existe pas
///
/// La carte « Continuer votre apprentissage » **ne dépend pas de M3** : elle est
/// alimentée par `GET /me/courses/resume` et navigue par nom de route.
class DashboardPage extends ConsumerWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<AccueilCitoyen> etat = ref.watch(dashboardProvider);
    final int? solde = ref.watch(soldeUnitesProvider);

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: VfAppBar(
        title: 'Vérif All',
        // Le bouton du tiroir, que le simulateur pose ici (§3, ecran 07). Le
        // socle en porte l'hote depuis l'arbitrage n° 137.
        leading: VfShellScope.isInsideShell(context)
            ? VfAppBarLeading.tiroir
            : VfAppBarLeading.aucun,
        pointsPill: VfPointsPill(
          solde: solde,
          onTap: () => context.push(VfRoutes.wallet),
        ),
        actions: <Widget>[
          // LA CLOCHE EST MASQUEE, pas grisee. Voir DrapeauxM2 : tant qu'aucun
          // endpoint n'emet de compteur, l'action ne s'affiche pas.
          if (DrapeauxM2.notifications)
            VfIconButton(
              icon: Icons.notifications_none_rounded,
              tooltip: 'Ouvrir les notifications',
              badge: (ref.watch(compteurNonLuesProvider) ?? 0) > 0,
              onPressed: () => context.push(VfRoutes.notifications),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(dashboardProvider);
          ref.invalidate(verificationsRecentesProvider);
        },
        child: etat.when(
          loading: () => const _Squelettes(),
          // « Jamais un tableau de bord vide silencieux » : sans cache, on
          // affiche une page d'erreur avec un bouton « Reessayer ».
          error: (Object echec, StackTrace pile) => ListView(
            children: <Widget>[
              const SizedBox(height: VfSpacing.x26),
              VfEmptyState(
                kind: VfEmptyKind.rienEncore,
                icon: Icons.cloud_off_rounded,
                title: 'Tableau de bord indisponible',
                message:
                    'Aucune donnee locale a afficher pour l instant. '
                    'Verifiez votre connexion.',
                action: VfGhostButton(
                  label: 'Réessayer',
                  expanded: false,
                  onPressed: () => ref.invalidate(dashboardProvider),
                ),
              ),
            ],
          ),
          data: (AccueilCitoyen accueil) => _corps(context, ref, accueil),
        ),
      ),
    );
  }

  Widget _corps(BuildContext context, WidgetRef ref, AccueilCitoyen accueil) {
    final TableauDeBord t = accueil.tableau;

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        if (accueil.venuDuCache) ...<Widget>[
          _BandeauCache(luLe: t.luLe),
          const SizedBox(height: VfSpacing.stack),
        ],
        EnTeteDeSalutation(
          prenom: t.salutation,
          serie: t.serie,
          luLe: t.luLe,
          enLigne: !accueil.venuDuCache,
        ),
        const SizedBox(height: VfSpacing.stack),
        Row(
          children: <Widget>[
            Expanded(
              child: VfDangerButton(
                label: 'Vérifier',
                icon: Icons.add_rounded,
                onPressed: () => context.go(VfRoutes.verify),
              ),
            ),
            const SizedBox(width: VfSpacing.x9),
            VfGhostButton(
              label: t.compteurs.unites?.toString() ?? '—',
              icon: Icons.bolt,
              expanded: false,
              onPressed: () => context.push(VfRoutes.wallet),
            ),
          ],
        ),
        const SizedBox(height: VfSpacing.stack),
        VfKpiGrid(
          children: <Widget>[
            VfKpiCard(
              value: '${t.compteurs.verifications}',
              label: 'Vérifications',
            ),
            VfKpiCard(
              value: '${t.compteurs.coursSuivis}',
              label: 'Cours suivis',
            ),
            // TROISIEME TUILE : le simulateur y met les points d'experience.
            // L'agregat ne sert PAS l'XP absolu — seulement un niveau et un
            // pourcentage de palier. Afficher un nombre d'XP exigerait de
            // l'inventer. On affiche donc le compteur reellement servi, avec son
            // vrai libelle. Voir docs/ARBITRAGES.md.
            VfKpiCard(
              value: '${t.compteurs.badges}',
              label: 'Badges obtenus',
              accent: VfColors.xp,
            ),
            // Le solde ne vient jamais du cache : un tiret, jamais un zero.
            VfKpiCard(
              value: t.compteurs.unites?.toString() ?? '—',
              label: 'Unités disponibles',
            ),
          ],
        ),
        const SizedBox(height: VfSpacing.stack),
        CarteDePriorites(
          reprise: accueil.reprise,
          onOuvrirLeCours: (String slug) => context.push(VfRoutes.course(slug)),
        ),
        if (accueil.reprise?.prochaineLeconTitre != null)
          const SizedBox(height: VfSpacing.stack),
        if (t.coursOffert != null) ...<Widget>[
          CarteOffreDuMois(
            cours: t.coursOffert!,
            onOuvrir: () => context.push(VfRoutes.course(t.coursOffert!.slug)),
          ),
          const SizedBox(height: VfSpacing.stack),
        ],
        // La carte de reprise n'est affichee que si l'endpoint a REPONDU :
        // « pas encore lue » et « lue, il n'y a rien a reprendre » menent a deux
        // cartes differentes, et seule la seconde dit « Commencez votre premier
        // cours ».
        if (accueil.repriseLue) ...<Widget>[
          CarteDeReprise(
            reprise: accueil.reprise,
            onReprendre: (String slug) => context.push(VfRoutes.course(slug)),
            onDecouvrir: () => context.go(VfRoutes.catalogue),
          ),
          const SizedBox(height: VfSpacing.stack),
        ],
        HistogrammeDActivite(activite: t.activite),
        const SizedBox(height: VfSpacing.stack),
        VfSectionHeader(
          title: 'Vérifications recentes',
          actionLabel: 'Tout voir',
          onAction: () => context.push(VfRoutes.myVerifications),
        ),
        const SizedBox(height: VfSpacing.x8),
        const _VerificationsRecentes(),
        // LA CARTE << PROCHAINE LIVE CLASS >> N'EST PAS LA. Elle affiche le
        // titre d'une seance, son intervenant et sa date : trois donnees que
        // l'agregat d'accueil ne porte pas. Les tirer de GET /live-classes
        // ajouterait un appel que la fiche de l'ecran ne declare pas, pour un
        // module qui n'est pas le notre. Une carte sans donnee serait un cadre
        // nu ; avec des donnees inventees, un mensonge. Voir ARBITRAGES.md.
      ],
    );
  }
}

/// Les trois vérifications récentes.
class _VerificationsRecentes extends ConsumerWidget {
  const _VerificationsRecentes();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<Verification>> etat = ref.watch(
      verificationsRecentesProvider,
    );

    return etat.when(
      loading: () => const Column(
        children: <Widget>[
          VfSkeleton(height: 76),
          SizedBox(height: VfSpacing.stackSm),
          VfSkeleton(height: 76),
        ],
      ),
      // L'echec de CE bloc ne fait pas tomber le tableau de bord : chaque bloc
      // a son propre etat, et dit pourquoi il est vide.
      error: (Object echec, StackTrace pile) =>
          Text('Liste indisponible pour le moment.', style: VfTextStyles.tXs),
      data: (List<Verification> liste) => liste.isEmpty
          ? VfEmptyState(
              kind: VfEmptyKind.rienEncore,
              icon: Icons.science_outlined,
              title: 'Votre laboratoire est encore vide',
              message: 'Lancez une premiere vérification pour le remplir.',
              action: VfPrimaryButton(
                label: 'Vérifier une information',
                expanded: false,
                onPressed: () => context.go(VfRoutes.verify),
              ),
            )
          : Column(
              children: <Widget>[
                for (final Verification v in liste) ...<Widget>[
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
      '${VfFormats.bandeauDeFraicheur(luLe)} Le solde d unités n est pas affiche : il ne '
      'se lit jamais depuis le cache.',
      style: VfTextStyles.tXs,
    ),
  );
}

/// Le chargement : **des squelettes à la forme des blocs**, jamais un
/// indicateur circulaire au centre.
class _Squelettes extends StatelessWidget {
  const _Squelettes();

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(VfSpacing.screenInline),
    children: const <Widget>[
      VfSkeleton(height: 72),
      SizedBox(height: VfSpacing.stack),
      VfSkeleton(height: 46),
      SizedBox(height: VfSpacing.stack),
      VfSkeleton(height: 164),
      SizedBox(height: VfSpacing.stack),
      VfSkeleton(height: 140),
      SizedBox(height: VfSpacing.stack),
      VfSkeleton(height: 160),
    ],
  );
}
