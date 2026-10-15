import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../domain/entities/live_class_session.dart';
import '../../../../domain/repositories/live_class_repository.dart';
import '../../../shared/shared.dart';
import '../providers/live_classes_provider.dart';
import '../widgets/live_class_card.dart';
import '../widgets/lms_banners.dart';

/// Écran 19 — Live Classes (`#/liveclasses`).
///
/// # Les trois onglets se répartissent côté client, et c'est légitime ici
///
/// `GET /live-classes` est **non paginée** (contrat §3.3, liste fermée). Le
/// corollaire « on ne filtre jamais côté client » ne vaut que pour les listes
/// **paginées**, où le filtre local ne porterait que sur la page chargée. Ici,
/// charger une fois et répartir sur `data.etat` est ce qui fait marcher les
/// trois onglets **hors ligne**.
///
/// # Trois états vides distincts
///
/// « Un état vide unique et générique sur les trois onglets ferait croire à une
/// panne. »
class LiveClassesPage extends ConsumerStatefulWidget {
  const LiveClassesPage({super.key});

  @override
  ConsumerState<LiveClassesPage> createState() => _LiveClassesPageState();
}

class _LiveClassesPageState extends ConsumerState<LiveClassesPage> {
  int _onglet = 0;

  static const List<String> _libelles = <String>[
    'A venir',
    'En direct',
    'Rediffusions',
  ];

  static const List<EtatSeance> _etats = <EtatSeance>[
    EtatSeance.aVenir,
    EtatSeance.enDirect,
    EtatSeance.passee,
  ];

  @override
  Widget build(BuildContext context) {
    final AsyncValue<SeancesLues> seances = ref.watch(liveClassesProvider);
    // On ECOUTE le controleur de rappel, meme sans lire sa valeur ici : un
    // provider `autoDispose` que personne ne regarde est libere aussitot lu,
    // et son etat disparaitrait au milieu de l'aller-retour reseau.
    ref.watch(rappelProvider);

    return Scaffold(
      appBar: VfAppBar(
        title: 'Live Classes',
        leading: context.canPop()
            ? VfAppBarLeading.retour
            : VfAppBarLeading.aucun,
        onLeadingPressed: context.canPop()
            ? null
            : () => context.go(VfRoutes.catalogue),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(liveClassesProvider),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            VfSpacing.screenInline,
            VfSpacing.screenTopExtra,
            VfSpacing.screenInline,
            VfSpacing.screenBottomExtra,
          ),
          children: <Widget>[
            if (seances.value?.vientDuCache ?? false) ...<Widget>[
              VfStaleDataBanner(lueLe: seances.value!.lueLe),
              const SizedBox(height: VfSpacing.stack),
            ],
            Semantics(
              // « Les trois onglets exposent leur position (onglet 2 sur 3). »
              label: 'Onglet ${_onglet + 1} sur 3 : ${_libelles[_onglet]}',
              child: VfChips(
                labels: _libelles,
                selectedIndex: _onglet,
                onSelected: (int index) => setState(() => _onglet = index),
              ),
            ),
            const SizedBox(height: VfSpacing.stack),
            ...seances.when(
              loading: () => const <Widget>[
                VfSkeleton(height: 150, width: double.infinity),
                SizedBox(height: VfSpacing.stackSm),
                VfSkeleton(height: 150, width: double.infinity),
              ],
              error: (Object _, StackTrace _) => <Widget>[
                EncartReseauRequis(
                  titre: 'Les seances n ont pas pu être chargees',
                  raison:
                      'Aucune liste n est encore enregistree sur cet appareil.',
                  action: VfGhostButton(
                    label: 'Réessayer',
                    onPressed: () => ref.invalidate(liveClassesProvider),
                  ),
                ),
              ],
              data: (SeancesLues lues) => _cartes(lues),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _cartes(SeancesLues lues) {
    final List<LiveClassSession> liste = seancesDeLOnglet(
      lues.seances,
      _etats[_onglet],
    );
    if (liste.isEmpty) {
      return <Widget>[_videDeLOnglet()];
    }
    return <Widget>[
      for (final LiveClassSession seance in liste) ...<Widget>[
        LiveClassCard(
          seance: seance,
          onRappeler: _demanderUnRappel,
          onRejoindre: _rejoindre,
        ),
        const SizedBox(height: VfSpacing.stackSm),
      ],
    ];
  }

  /// Trois états vides, et **chacun dit autre chose**.
  Widget _videDeLOnglet() {
    switch (_etats[_onglet]) {
      case EtatSeance.aVenir:
        return const VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.event_outlined,
          title: 'Aucune seance programmee',
          message: 'Les prochaines dates arrivent bientot.',
        );
      case EtatSeance.enDirect:
        return VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.sensors_outlined,
          title: 'Rien en direct pour l instant',
          message: 'Regardez les seances a venir pour ne rien manquer.',
          action: VfGhostButton(
            label: 'Voir les seances a venir',
            onPressed: () => setState(() => _onglet = 0),
          ),
        );
      case EtatSeance.passee:
      case EtatSeance.inconnu:
        return const VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.video_library_outlined,
          title: 'Aucune rediffusion disponible',
          message: 'Les rediffusions sont publiees après les seances.',
        );
    }
  }

  Future<void> _demanderUnRappel(LiveClassSession seance) async {
    final IssueDeRappel? issue = await ref
        .read(rappelProvider.notifier)
        .demander(seance.uuid);
    if (!mounted || issue == null) {
      return;
    }
    final String? refus = issue.messageDuServeur;
    VfToast.show(
      context,
      // La confirmation NE PROMET PAS de notification : `remind` enregistre
      // l'intention, l'envoi du rappel J-1 attend l'infrastructure de
      // notifications (reserve C.2). « L'inscription est durable : le jour ou
      // le rappel partira, il aura sa liste. »
      message: refus ?? issue.confirmation,
      variant: refus == null ? VfToastVariant.neutre : VfToastVariant.erreur,
    );
  }

  /// « Rejoindre ouvre `data.lien_visio` en **onglet système** », jamais en
  /// WebView maison.
  Future<void> _rejoindre(String lien) async {
    final Uri? cible = Uri.tryParse(lien);
    if (cible == null) {
      return;
    }
    await launchUrl(cible, mode: LaunchMode.externalApplication);
  }
}
