/// Les deux pages système du socle.
///
/// # Pourquoi le socle porte ces deux-là alors qu'il n'écrit aucun écran
///
/// Elles n'appartiennent à aucun des trente écrans du simulateur, donc à aucun
/// des cinq parcours. Et elles sont exigées avant que le premier parcours ne
/// livre :
///
/// - le **426** : « un écran bloquant dédié, qui explique et mène au magasin.
///   Il doit exister **avant qu'on en ait besoin** : le jour où le plancher est
///   relevé, on ne peut plus déployer de code sur les téléphones concernés »
///   (04-RESEAU §4.6) ;
/// - le **repli de route** : `go_router` exige un `errorBuilder`, et sans lui
///   une route inconnue rend une page rouge de débogage.
///
/// Elles sont volontairement minimales et n'utilisent que des composants `Vf*`.
library;

import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';
import 'vf_buttons.dart';

/// L'écran bloquant du `426 client_obsolete`.
///
/// POURQUOI il dit « mettez à jour » et pas « erreur réseau » : « c'est la
/// différence entre quelqu'un qui agit et quelqu'un qui désinstalle » (§6.4 du
/// contrat).
class VfUpdateRequiredPage extends StatelessWidget {
  const VfUpdateRequiredPage({
    this.message,
    this.minimumVersion,
    this.onOpenStore,
    super.key,
  });

  /// Le message du serveur, **affiché tel quel** quand il y en a un.
  final String? message;

  /// Lu de l'en-tête `X-Minimum-Client-Version`.
  final String? minimumVersion;

  /// L'ouverture du magasin. Elle dépend de la plateforme et de l'identifiant de
  /// paquet : c'est de la livraison, pas du socle. Tant qu'elle n'est pas
  /// branchée, le bouton n'apparaît pas — un bouton qui ne fait rien est pire
  /// qu'un bouton absent.
  final VoidCallback? onOpenStore;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: VfColors.canvas,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(VfSpacing.x26),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              const Icon(
                Icons.system_update_rounded,
                size: 44,
                color: VfColors.slate2,
              ),
              const SizedBox(height: VfSpacing.x16),
              Text(
                'Mettez a jour l application',
                style: VfTextStyles.hLg,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: VfSpacing.x10),
              Text(
                message ??
                    'Cette version de Vérif All n est plus acceptee par nos '
                        'serveurs. La mise a jour retablira l acces a votre '
                        'compte et a vos vérifications.',
                style: VfTextStyles.tMd,
                textAlign: TextAlign.center,
              ),
              if (minimumVersion != null) ...<Widget>[
                const SizedBox(height: VfSpacing.x8),
                Text(
                  'Version minimale : $minimumVersion',
                  style: VfTextStyles.tXs,
                ),
              ],
              if (onOpenStore != null) ...<Widget>[
                const SizedBox(height: VfSpacing.x22),
                VfPrimaryButton(
                  label: 'Ouvrir le magasin',
                  onPressed: onOpenStore,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Le repli de route.
///
/// POURQUOI le texte ne dit jamais « ce contenu a été supprimé » : le contrat
/// impose que « ce qui n'appartient pas au demandeur réponde 404, jamais 403 »
/// (§9). Un 404 signifie donc « pas pour vous, ou pas là » — affirmer la
/// suppression serait une affirmation que le client n'a pas les moyens de faire
/// (04-RESEAU §4.2).
///
/// C'est aussi l'écran que voit un **lien profond** vers une opportunité
/// archivée à J+7 ou une brève retirée : « les trois doivent afficher un repli,
/// pas une page blanche » (06-TESTS §6.6).
class VfRouteFallbackPage extends StatelessWidget {
  const VfRouteFallbackPage({this.detail, this.onExit, super.key});

  /// Réservé au diagnostic en développement. **Jamais un message d'exception**,
  /// qui peut contenir une adresse de service ou un identifiant.
  final String? detail;

  /// La sortie. Un écran de repli sans sortie est un cul-de-sac.
  final VoidCallback? onExit;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: VfColors.canvas,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(VfSpacing.x26),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              const Icon(
                Icons.explore_off_outlined,
                size: 44,
                color: VfColors.line,
              ),
              const SizedBox(height: VfSpacing.x16),
              Text(
                'Ce contenu n est pas disponible',
                style: VfTextStyles.hSm,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: VfSpacing.x6),
              Text(
                'Il a peut-être ete retire, ou il ne vous est pas accessible.',
                style: VfTextStyles.tSm,
                textAlign: TextAlign.center,
              ),
              if (detail != null) ...<Widget>[
                const SizedBox(height: VfSpacing.x10),
                Text(detail!, style: VfTextStyles.tXs),
              ],
              if (onExit != null) ...<Widget>[
                const SizedBox(height: VfSpacing.x22),
                VfGhostButton(
                  label: 'Revenir a l accueil',
                  onPressed: onExit,
                  expanded: false,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
