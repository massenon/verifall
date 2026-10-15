import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';

/// Une entrée du tiroir.
@immutable
class VfDrawerEntry {
  const VfDrawerEntry({
    required this.label,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;
}

/// Un groupe d'entrées.
@immutable
class VfDrawerGroup {
  const VfDrawerGroup({required this.entries, this.title});

  /// `null` pour le premier groupe, qui n'a pas de titre (§2.5).
  final String? title;

  final List<VfDrawerEntry> entries;
}

/// `.drawer` — 274 de large, fond `#1F2124`.
///
/// # Ce que le socle fournit, et ce qu'il ne fournit pas
///
/// Le socle fournit **la coque** : largeur, couleurs, en-tête de compte, groupes,
/// bouton de déconnexion rouge en pied. Il ne fournit **pas** la liste des
/// entrées : chacune mène à un écran d'un parcours, et les câbler ici forcerait
/// le socle à connaître les cinq.
///
/// Les quatre groupes attendus sont ceux du §2.5 :
/// (sans titre) Tableau de bord · Vérifier un contenu · Mes vérifications ·
/// Liste des outils — Apprentissage · Communauté · Mon compte.
///
/// # Purge visuelle à la déconnexion
///
/// [onSignOut] doit fermer le tiroir **avant** de déconnecter. Un tiroir qui
/// reste ouvert avec le nom de la personne pendant que la session se ferme est
/// exactement le genre de détail qui fait douter d'une application sur un
/// téléphone partagé.
class VfDrawer extends StatelessWidget {
  const VfDrawer({
    required this.displayName,
    required this.initials,
    required this.groups,
    required this.onSignOut,
    this.role,
    this.xp,
    super.key,
  });

  final String displayName;
  final String initials;

  /// Le rôle affiché dans l'en-tête du compte. C'est **le rôle du titulaire**,
  /// pas celui d'un auteur du forum : le contrat n'expose jamais le rôle d'un
  /// tiers (§9).
  final String? role;

  final int? xp;
  final List<VfDrawerGroup> groups;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    return Drawer(
      width: VfChrome.drawerWidth,
      backgroundColor: VfColors.sidebarBg,
      shape: const RoundedRectangleBorder(),
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            _header(),
            const Divider(color: Color(0x1FFFFFFF)),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: VfSpacing.x8),
                children: <Widget>[
                  for (final VfDrawerGroup group in groups) ...<Widget>[
                    if (group.title != null)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(
                          VfSpacing.x16,
                          VfSpacing.x14,
                          VfSpacing.x16,
                          VfSpacing.x6,
                        ),
                        child: Text(
                          group.title!.toUpperCase(),
                          style: VfTextStyles.eyebrow.copyWith(
                            color: VfColors.surface.withValues(alpha: 0.55),
                          ),
                          semanticsLabel: group.title,
                        ),
                      ),
                    for (final VfDrawerEntry entry in group.entries)
                      _entry(entry),
                  ],
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(VfSpacing.x16),
              child: _signOutButton(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return Padding(
      padding: const EdgeInsets.all(VfSpacing.x16),
      child: Row(
        children: <Widget>[
          Container(
            width: 42,
            height: 42,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: VfColors.surface.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: Text(
              initials,
              style: VfTextStyles.hSm.copyWith(color: VfColors.surface),
            ),
          ),
          const SizedBox(width: VfSpacing.x12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  displayName,
                  style: VfTextStyles.hSm.copyWith(color: VfColors.surface),
                  overflow: TextOverflow.ellipsis,
                ),
                if (role != null || xp != null)
                  Text(
                    <String>[?role, if (xp != null) '$xp XP'].join(' · '),
                    style: VfTextStyles.tXs.copyWith(
                      color: VfColors.surface.withValues(alpha: 0.65),
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _entry(VfDrawerEntry entry) {
    return InkWell(
      onTap: entry.onTap,
      child: Container(
        constraints: const BoxConstraints(minHeight: VfTouch.menuRowMinHeight),
        padding: const EdgeInsets.symmetric(
          horizontal: VfSpacing.x16,
          vertical: VfTouch.menuRowVerticalPadding,
        ),
        child: Row(
          children: <Widget>[
            Icon(entry.icon, size: 20, color: VfColors.surface),
            const SizedBox(width: VfSpacing.x12),
            Expanded(
              child: Text(
                entry.label,
                style: VfTextStyles.tMd.copyWith(color: VfColors.surface),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _signOutButton() {
    return Semantics(
      button: true,
      label: 'Se déconnecter de ce telephone',
      excludeSemantics: true,
      child: InkWell(
        onTap: onSignOut,
        borderRadius: VfRadii.mdAll,
        child: Container(
          constraints: const BoxConstraints(minHeight: VfTouch.buttonMinHeight),
          alignment: Alignment.center,
          decoration: const BoxDecoration(
            color: VfColors.brandRed,
            borderRadius: VfRadii.mdAll,
          ),
          child: Text(
            'Déconnexion',
            style: VfTextStyles.button.copyWith(color: VfColors.surface),
          ),
        ),
      ),
    );
  }
}
