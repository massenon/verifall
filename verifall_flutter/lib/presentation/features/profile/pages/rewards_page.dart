import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/gamification_recompenses.dart';
import '../../../../domain/entities/stats_statistiques.dart';
import '../../../shared/shared.dart';
import '../providers/gamification_provider.dart';
import '../providers/profile_dependencies.dart';
import '../widgets/rewards_widgets.dart';

/// Écran 27 · Récompenses & badges.
///
/// # La grille de badges vient entièrement du serveur
///
/// `GET /me/gamification` **recalcule les badges avant de répondre** et renvoie
/// aussi les verrouillés, avec leur condition en clair. Le client ne tient
/// aucune liste de badges : il ne peut donc ni en oublier un, ni en inventer un.
///
/// # Le suivi de série a besoin de `GET /me/stats`
///
/// L'agrégat de gamification rend le **nombre** de jours de série, pas les sept
/// jours nommés ; ceux-ci viennent de `data.semaine` de `GET /me/stats`. Les
/// déduire de l'horloge du téléphone ferait « casser » une série à minuit pour
/// qui voyage. La lecture est **au mieux** : sans elle, la carte affiche le
/// compte de jours sans les sept pastilles, plutôt que sept pastilles fausses.
class RewardsPage extends ConsumerWidget {
  const RewardsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<EtatRecompenses> etat = ref.watch(gamificationProvider);

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Récompenses'),
      body: etat.when(
        loading: () => const _Squelette(),
        error: (Object erreur, StackTrace _) => VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.military_tech_outlined,
          title: 'Récompenses indisponibles',
          message: erreur is Failure
              ? (erreur.serverMessage ??
                    'Vos récompenses n ont pas pu être lues.')
              : 'Vos récompenses n ont pas pu être lues.',
          action: VfGhostButton(
            label: 'Réessayer',
            onPressed: () =>
                ref.read(gamificationProvider.notifier).rafraichir(),
          ),
        ),
        data: (EtatRecompenses donnees) => _Corps(etat: donnees),
      ),
    );
  }
}

class _Corps extends ConsumerWidget {
  const _Corps({required this.etat});

  final EtatRecompenses etat;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final Recompenses recompenses = etat.recompenses;
    final BadgeCitoyen? prochain = recompenses.prochaineEtape;

    return RefreshIndicator(
      onRefresh: () => ref.read(gamificationProvider.notifier).rafraichir(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: <Widget>[
          VfLevelCard(niveau: recompenses.niveau),
          const SizedBox(height: VfSpacing.stack),
          _CarteDeSerie(serie: recompenses.serie),
          if (prochain != null) ...<Widget>[
            const SizedBox(height: VfSpacing.stack),
            VfNextBadgeCard(badge: prochain),
          ],
          const SizedBox(height: VfSpacing.stack),
          Text(
            'Mes badges · ${recompenses.badgesObtenus} / '
            '${recompenses.badges.length}',
            style: VfTextStyles.hSm,
          ),
          const SizedBox(height: VfSpacing.stackSm),
          GridView.count(
            crossAxisCount: 3,
            crossAxisSpacing: VfSpacing.x10,
            mainAxisSpacing: VfSpacing.x10,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            childAspectRatio: 0.62,
            children: <Widget>[
              for (final BadgeCitoyen badge in recompenses.badges)
                VfBadgeTile(badge: badge),
            ],
          ),
          const SizedBox(height: VfSpacing.stack),
          Text('Mes certificats', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.stackSm),
          if (etat.certificatsIndisponibles)
            const VfEmptyState(
              kind: VfEmptyKind.rienEncore,
              icon: Icons.cloud_off_rounded,
              title: 'Certificats indisponibles',
              message:
                  'Vos certificats n ont pas pu être lus. Ils reapparaitront '
                  'des le retour de la connexion.',
            )
          else if (etat.certificats.isEmpty)
            const VfEmptyState(
              kind: VfEmptyKind.rienEncore,
              icon: Icons.workspace_premium_outlined,
              title: 'Aucun certificat',
              message:
                  'Terminez un cours pour obtenir votre premier certificat.',
            )
          else
            for (final Certificat certificat in etat.certificats) ...<Widget>[
              VfCertificateTile(
                certificat: certificat,
                // Ecran de M3 : on y va par la NAVIGATION, sur le code public.
                onTap: () =>
                    context.push(VfRoutes.certificate(certificat.code)),
              ),
              const SizedBox(height: VfSpacing.stackSm),
            ],
        ],
      ),
    );
  }
}

/// La carte de série, avec ses sept jours **quand le serveur les rend**.
class _CarteDeSerie extends ConsumerWidget {
  const _CarteDeSerie({required this.serie});

  final SerieDeVigilance serie;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // La lecture est AU MIEUX : sans les sept jours, on affiche le compte seul
    // plutot que sept pastilles deduites de l'horloge du telephone.
    final AsyncValue<Statistiques> stats = ref.watch(statsPourLaSerieProvider);
    return VfStreakTracker(
      serie: serie,
      semaine: stats.value?.semaine ?? const <JourDeSemaine>[],
    );
  }
}

/// Les sept jours de la semaine, lus de `GET /me/stats`.
///
/// POURQUOI un provider dédié plutôt que `statsProvider` : l'écran des
/// statistiques a son propre cycle de rafraîchissement, et faire dépendre la
/// carte de série d'un provider d'un autre écran ferait recharger l'un en
/// quittant l'autre.
final FutureProvider<Statistiques> statsPourLaSerieProvider =
    FutureProvider<Statistiques>(
      (Ref ref) => ref.read(profileRepositoryProvider).statistiques(),
    );

class _Squelette extends StatelessWidget {
  const _Squelette();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        VfSkeleton(height: 120, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 140, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 220, borderRadius: VfRadii.lgAll),
      ],
    );
  }
}
