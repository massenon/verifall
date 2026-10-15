import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/network/auth_controller.dart';
import '../../data/models/auth_token.dart';
import '../../core/network/session_state.dart';
import '../../core/router/vf_routes.dart';
import '../../core/session/deconnexion.dart';
import 'vf_drawer.dart';

/// Le tiroir de l'application, **assemblé**.
///
/// # Pourquoi il a fini au socle, alors que `VfDrawer` disait le contraire
///
/// `VfDrawer` — la coque — était au socle depuis le début, mais avec cette
/// réserve : « le socle ne fournit pas la liste des entrées : chacune mène à un
/// écran d'un parcours, et les câbler ici forcerait le socle à connaître les
/// cinq ». L'argument ne tient plus pour deux raisons :
///
/// 1. **`VfShellScaffold` connaît déjà les cinq.** Il déduit l'onglet courant du
///    chemin, et il nomme `/catalogue`, `/cours`, `/certificat`, `/unites`,
///    `/recompenses`, `/statistiques`, `/parrainage`, `/reglages`. Le socle
///    connaît les cinq parcours **par leurs routes**, et c'est précisément à cela
///    que sert `VfRoutes`.
/// 2. **Trois parcours voulaient le bouton.** Le tableau de bord (M2), le forum
///    (M4) et Mon profil (M5) posent tous les trois un bouton de tiroir dans le
///    simulateur (§3 des écrans 07, 20 et 25) — et aucun des trois ne pouvait
///    l'ouvrir, faute d'hôte. M4 avait retiré le bouton plutôt que d'en poser un
///    qui n'ouvre rien (arbitrage n° 92). Assembler le menu dans l'un des trois
///    l'aurait dupliqué dans les deux autres.
///
/// Arbitrage n° 137.
///
/// # Les entrées ne sont pas inventées
///
/// Ce sont **les quatre groupes du §2.5**, mot pour mot. Une seule y manque, et
/// c'est délibéré : le simulateur ne montre aucun bouton de tiroir sur les écrans
/// qui n'en ont pas, et rien n'est ajouté ici qui n'y figure.
///
/// « Certificats & badges » mène à l'écran 27, qui porte les deux : la grille de
/// badges **et** la liste « Mes certificats ». Ce n'est pas un rapprochement de
/// confort — c'est là que les deux sont rendus.
class VfAppDrawer extends ConsumerWidget {
  const VfAppDrawer({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final SessionState session = ref.watch(authControllerProvider);
    final UtilisateurResume? moi = session.utilisateur;

    void aller(String chemin) {
      // Fermer le tiroir AVANT de naviguer : un tiroir qui reste ouvert
      // par-dessus l'ecran d'arrivee se lit comme une navigation ratee.
      Navigator.of(context).pop();
      context.go(chemin);
    }

    return VfDrawer(
      // Le prenom seul : le contrat ne rend pas le nom de famille dans le resume
      // de compte, et en fabriquer un serait afficher ce qu'on n'a pas.
      displayName: moi?.prenom ?? 'Mon compte',
      initials: moi?.initiale ?? '?',
      // POURQUOI ni role ni XP : l'API n'expose le role de personne dans ce
      // resume, et le XP n'est servi par aucun agregat que le socle lise
      // (arbitrages n° 87 et 122). `VfDrawer` les rend facultatifs pour cette
      // raison ; les remplir demanderait de les inventer.
      groups: <VfDrawerGroup>[
        VfDrawerGroup(
          entries: <VfDrawerEntry>[
            VfDrawerEntry(
              label: 'Tableau de bord',
              icon: Icons.dashboard_outlined,
              onTap: () => aller(VfRoutes.dashboard),
            ),
            VfDrawerEntry(
              label: 'Vérifier un contenu',
              icon: Icons.search_rounded,
              onTap: () => aller(VfRoutes.verify),
            ),
            VfDrawerEntry(
              label: 'Mes vérifications',
              icon: Icons.fact_check_outlined,
              onTap: () => aller(VfRoutes.myVerifications),
            ),
            VfDrawerEntry(
              label: 'Liste des outils',
              icon: Icons.build_outlined,
              onTap: () => aller(VfRoutes.tools),
            ),
          ],
        ),
        VfDrawerGroup(
          title: 'Apprentissage',
          entries: <VfDrawerEntry>[
            VfDrawerEntry(
              label: 'Catalogue de cours',
              icon: Icons.school_outlined,
              onTap: () => aller(VfRoutes.catalogue),
            ),
            VfDrawerEntry(
              label: 'Live Classes',
              icon: Icons.videocam_outlined,
              onTap: () => aller(VfRoutes.liveClasses),
            ),
            VfDrawerEntry(
              label: 'Certificats & badges',
              icon: Icons.workspace_premium_outlined,
              onTap: () => aller(VfRoutes.rewards),
            ),
          ],
        ),
        VfDrawerGroup(
          title: 'Communauté',
          entries: <VfDrawerEntry>[
            VfDrawerEntry(
              label: 'Forum',
              icon: Icons.forum_outlined,
              onTap: () => aller(VfRoutes.forum),
            ),
            VfDrawerEntry(
              label: 'Hub opportunités',
              icon: Icons.newspaper_rounded,
              onTap: () => aller(VfRoutes.hub),
            ),
            VfDrawerEntry(
              label: 'Mon parrainage',
              icon: Icons.card_giftcard_outlined,
              onTap: () => aller(VfRoutes.referrals),
            ),
          ],
        ),
        VfDrawerGroup(
          title: 'Mon compte',
          entries: <VfDrawerEntry>[
            VfDrawerEntry(
              label: 'Mes unités',
              icon: Icons.bolt_outlined,
              onTap: () => aller(VfRoutes.wallet),
            ),
            VfDrawerEntry(
              label: 'Mes statistiques',
              icon: Icons.insights_outlined,
              onTap: () => aller(VfRoutes.stats),
            ),
            VfDrawerEntry(
              label: 'Mon profil',
              icon: Icons.person_outline_rounded,
              onTap: () => aller(VfRoutes.profile),
            ),
            VfDrawerEntry(
              label: 'Réglages',
              icon: Icons.settings_outlined,
              onTap: () => aller(VfRoutes.settings),
            ),
          ],
        ),
      ],
      onSignOut: () async {
        // Fermer le tiroir AVANT de deconnecter : « un tiroir qui reste ouvert
        // avec le nom de la personne pendant que la session se ferme est
        // exactement le genre de detail qui fait douter d'une application sur un
        // telephone partage » (§2.5).
        Navigator.of(context).pop();
        await ref.read(deconnexionProvider)();
      },
    );
  }
}
