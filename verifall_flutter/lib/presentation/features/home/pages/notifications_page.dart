import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/notification_citoyen.dart';
import '../../../../domain/usecases/verify/drapeaux_de_fonctionnalite.dart';
import '../../../shared/vf_app_bar.dart';
import '../../../shared/vf_buttons.dart';
import '../../../shared/vf_display.dart';
import '../providers/dashboard_provider.dart';

/// Écran 08 · **Notifications** — `#/notifications`.
///
/// # ⚠ Cet écran n'a AUCUN endpoint, et il est écrit quand même
///
/// Les trois appels du simulateur — `GET /me/notifications`,
/// `POST /me/notifications/read-all`, `POST /me/notifications/{id}/read` — **ne
/// sont pas servis par l'API v1** (§11, arbitrage n° 73) :
///
/// > « Il n'existe **aucun magasin de notifications** sur cette plateforme : pas
/// > de table `notifications`, aucune classe `Notification`, rien qui en émette.
/// > La cloche du gabarit citoyen est **décorative**. »
///
/// La décision documentée est double, et les deux moitiés comptent :
///
/// - **la route existe et l'écran est écrit**, derrière un drapeau de
///   fonctionnalité. « Le jour où les endpoints arrivent, on lève le drapeau —
///   on ne réécrit pas l'écran. C'est ce qui distingue *pas branché* de *pas
///   fait*. » Le jour venu, ce seront des **ajouts** compatibles v1 ;
/// - **rien n'y mène.** La cloche du tableau de bord est masquée, et cette page
///   n'est atteignable que par un lien profond. Elle dit alors franchement
///   qu'elle n'est pas branchée, plutôt que d'afficher une liste vide — « un
///   écran vide laisse croire à une panne ou à un compte sans activité, ce qui
///   est faux ».
class NotificationsPage extends ConsumerWidget {
  const NotificationsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Notifications'),
      body: DrapeauxM2.notifications
          ? const _ListeBranchee()
          : _NonBranchee(onSortir: () => context.pop()),
    );
  }
}

/// Ce que l'écran affiche **en v1** : la vérité.
class _NonBranchee extends StatelessWidget {
  const _NonBranchee({required this.onSortir});

  final VoidCallback onSortir;

  @override
  Widget build(BuildContext context) => VfEmptyState(
    kind: VfEmptyKind.rienEncore,
    icon: Icons.notifications_off_rounded,
    title: 'Les notifications n existent pas encore',
    // On ne promet PAS « vous serez prevenu des qu'un verdict est pret » : rien
    // n'emettra cette notification. On dit ce qui remplace l'attente.
    message:
        'Aucune notification n est envoyee pour l instant. Une analyse lancee '
        'continue cote serveur : retrouvez-la dans Mes vérifications.',
    action: VfGhostButton(
      label: 'Revenir',
      expanded: false,
      onPressed: onSortir,
    ),
  );
}

/// La liste, telle qu'elle sera **quand les endpoints existeront**.
///
/// Elle reste la référence de transposition : icône de catégorie, texte en gras
/// si non lue, horodatage relatif, point vert à droite, ligne teintée. Le
/// marquage sera **optimiste** — la ligne se dégrise immédiatement — et
/// réconcilié par la réponse du serveur.
class _ListeBranchee extends ConsumerWidget {
  const _ListeBranchee();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<NotificationCitoyen>> etat = ref.watch(
      notificationsProvider,
    );

    return etat.when(
      loading: () => const Padding(
        padding: EdgeInsets.all(VfSpacing.screenInline),
        child: Column(
          children: <Widget>[
            VfSkeleton(height: 62),
            SizedBox(height: VfSpacing.stackSm),
            VfSkeleton(height: 62),
            SizedBox(height: VfSpacing.stackSm),
            VfSkeleton(height: 62),
          ],
        ),
      ),
      error: (Object echec, StackTrace pile) => const _NonBrancheeStatique(),
      data: (List<NotificationCitoyen> liste) {
        if (liste.isEmpty) {
          return VfEmptyState(
            kind: VfEmptyKind.rienEncore,
            icon: Icons.notifications_off_rounded,
            title: 'Aucune notification',
            message: 'Vous serez prevenu des qu un verdict est pret.',
          );
        }
        final int nonLues = liste
            .where((NotificationCitoyen n) => !n.lue)
            .length;

        return ListView(
          padding: const EdgeInsets.all(VfSpacing.screenInline),
          children: <Widget>[
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: <Widget>[
                Text('$nonLues non lue(s)', style: VfTextStyles.tSm),
                TextButton(
                  onPressed: () {},
                  child: Text(
                    'Tout marquer lu',
                    style: VfTextStyles.tSm.copyWith(
                      color: VfColors.primary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: VfSpacing.x10),
            VfMenuList(
              children: <Widget>[
                for (final NotificationCitoyen n in liste)
                  VfNavTile(
                    label: n.texte,
                    description: n.horodatageRelatif,
                    icon: n.categorie == 'lms'
                        ? Icons.school_outlined
                        : Icons.fact_check_outlined,
                    trailing: n.lue
                        ? null
                        : Container(
                            width: 9,
                            height: 9,
                            decoration: const BoxDecoration(
                              color: VfColors.primary,
                              shape: BoxShape.circle,
                            ),
                          ),
                    onTap: () {},
                  ),
              ],
            ),
          ],
        );
      },
    );
  }
}

class _NonBrancheeStatique extends StatelessWidget {
  const _NonBrancheeStatique();

  @override
  Widget build(BuildContext context) => VfEmptyState(
    kind: VfEmptyKind.rienEncore,
    icon: Icons.notifications_off_rounded,
    title: 'Notifications indisponibles',
    message: 'Le service de notifications n est pas encore en place.',
  );
}
