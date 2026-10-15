import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/stats_statistiques.dart';
import '../../../shared/shared.dart';
import '../providers/profile_provider.dart';
import '../providers/stats_provider.dart';
import '../widgets/stats_widgets.dart';

/// Écran 28 · Mes statistiques.
///
/// # Pourquoi il n'y a pas de sélecteur de période
///
/// Le provider s'appelle `statsProvider(period)` et le contrat accepte quatre
/// valeurs. Mais **le simulateur n'expose aucun sélecteur** (réserve D.4), et en
/// ajouter un serait inventer un contrôle. La période reste `30d`, celle des
/// titres de l'écran.
///
/// # La distinction qui évite de faire passer un compte neuf pour une panne
///
/// Une série de trente jours à zéro est **normale** et se dessine — c'est la
/// règle du contrat, et elle dit quelque chose sur la régularité. Un compte
/// **neuf**, lui, se reconnaît à l'absence totale de vérification : « un donut à
/// zéro et un histogramme plat sont indiscernables d'une panne ». L'écran dit
/// alors quoi faire, et propose l'action.
class StatsPage extends ConsumerWidget {
  const StatsPage({super.key});

  /// La période affichée. Fixe, faute de contrôle à l'écran.
  static const PeriodeStats periode = PeriodeStats.trenteJours;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Statistiques> etat = ref.watch(statsProvider(periode));

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Statistiques'),
      body: etat.when(
        loading: () => const _Squelette(),
        error: (Object erreur, StackTrace _) => VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.insights_outlined,
          title: 'Statistiques indisponibles',
          message: erreur is Failure
              ? (erreur.serverMessage ??
                    'Vos statistiques n ont pas pu être lues.')
              : 'Vos statistiques n ont pas pu être lues.',
          action: VfGhostButton(
            label: 'Réessayer',
            onPressed: () =>
                ref.read(statsProvider(periode).notifier).rafraichir(),
          ),
        ),
        data: (Statistiques stats) => _corps(context, ref, stats),
      ),
    );
  }

  Widget _corps(BuildContext context, WidgetRef ref, Statistiques stats) {
    if (stats.compteNeuf) {
      return VfEmptyState(
        kind: VfEmptyKind.rienEncore,
        icon: Icons.insights_outlined,
        title: 'Rien a mesurer pour l instant',
        message:
            'Vos statistiques apparaitront après votre premiere vérification.',
        action: VfPrimaryButton(
          label: 'Vérifier un contenu',
          onPressed: () => context.push(VfRoutes.verify),
        ),
      );
    }

    final int? serieJours = ref.watch(profileProvider).value?.moi.serieJours;

    return RefreshIndicator(
      onRefresh: () => ref.read(statsProvider(periode).notifier).rafraichir(),
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
              VfKpiCard(
                value: '${stats.verifications}',
                label: 'Vérifications',
              ),
              VfKpiCard(
                value: '${stats.intoxDetectees}',
                label: 'Intox demasquees',
                accent: VfColors.verdictFalse,
              ),
              VfKpiCard(
                value: '${stats.leconsTerminees}',
                label: 'Leçons terminées',
              ),
              // `GET /me/stats` ne rend pas la serie : elle vient de `GET /me`.
              // Sans elle, un tiret — jamais un zero, qui ferait croire a une
              // serie cassee.
              VfKpiCard(
                value: serieJours?.toString() ?? '—',
                label: 'Jours de série',
              ),
            ],
          ),
          const SizedBox(height: VfSpacing.stack),
          VfActivityChart(serie: stats.activite),
          const SizedBox(height: VfSpacing.stack),
          VfVerdictDonut(verdicts: stats.verdicts),
          const SizedBox(height: VfSpacing.stack),
          VfTypeBars(types: stats.types),
          const SizedBox(height: VfSpacing.stack),
          VfInsightCard(stats: stats),
          const SizedBox(height: VfSpacing.stackSm),
          // La date de CALCUL, toujours affichee : « une statistique perimee
          // presentee comme actuelle est un indicateur faux ».
          Text(
            'Calcule le ${_dateEtHeure(stats.calculeLe)}.',
            style: VfTextStyles.tXs,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  String _dateEtHeure(DateTime date) {
    final DateTime local = date.toLocal();
    String deux(int n) => n.toString().padLeft(2, '0');
    return '${deux(local.day)}/${deux(local.month)}/${local.year} a '
        '${deux(local.hour)}h${deux(local.minute)}';
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
        VfSkeleton(height: 180, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 200, borderRadius: VfRadii.lgAll),
      ],
    );
  }
}
