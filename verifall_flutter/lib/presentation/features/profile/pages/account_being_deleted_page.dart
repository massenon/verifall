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
import '../../../../domain/entities/profile_moi.dart';
import '../../../shared/shared.dart';
import '../providers/profile_dependencies.dart';
import '../../../../core/session/deconnexion.dart';

/// L'écran d'état « compte en cours de suppression ».
///
/// # Pourquoi cette page existe, et pourquoi elle est à M5
///
/// La garde de route est dans le socle — « toute route répond
/// `403 compte_en_suppression`, sauf `GET /me` et `POST /auth/logout` » (§1.7),
/// donc c'est le routeur qui tranche, une fois, et pas trente écrans. Mais **la
/// page appartient au parcours qui la dessine**, exactement comme `/connexion`
/// appartient à M1.
///
/// # Pourquoi ce n'est pas un mur d'erreur
///
/// Le contrat est explicite : `GET /me` reste servi pendant le délai de
/// rétractation *précisément* pour permettre cet écran-là. Sans lui, « un
/// utilisateur qui ne comprend pas pourquoi tout est refusé ne se rétracte pas,
/// il désinstalle ».
///
/// # Ce que la page doit dire, et qu'aucune autre ne dira
///
/// **L'annulation ne passe pas par l'application.** Elle se fait par le lien
/// signé reçu par courriel, « seule voie de retour quand on ne peut plus se
/// connecter ». Une personne qui chercherait un bouton « Annuler » ici le
/// chercherait indéfiniment ; la page le dit tout de suite.
class AccountBeingDeletedPage extends ConsumerStatefulWidget {
  const AccountBeingDeletedPage({super.key});

  @override
  ConsumerState<AccountBeingDeletedPage> createState() =>
      _AccountBeingDeletedPageState();
}

class _AccountBeingDeletedPageState
    extends ConsumerState<AccountBeingDeletedPage> {
  late final Future<Moi?> _profil = _lire();

  /// `GET /me` — l'une des deux seules routes encore servies.
  ///
  /// L'échec ne bloque pas la page : la date est un confort, le message
  /// essentiel — trente jours, annulation par courriel — ne dépend d'aucune
  /// lecture.
  Future<Moi?> _lire() async {
    try {
      return await ref.read(profileRepositoryProvider).moi();
    } on Failure {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final SessionState session = ref.watch(authControllerProvider);

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(
        title: 'Compte en cours de suppression',
        leading: VfAppBarLeading.aucun,
      ),
      body: FutureBuilder<Moi?>(
        future: _profil,
        builder: (BuildContext context, AsyncSnapshot<Moi?> instantane) {
          final DateTime? demandeeLe =
              instantane.data?.suppressionDemandeeLe ??
              session.suppressionDemandeeLe;
          final DateTime? effacementLe =
              instantane.data?.effacementPrevuLe ??
              demandeeLe?.add(const Duration(days: 30));

          return ListView(
            padding: const EdgeInsets.fromLTRB(
              VfSpacing.screenInline,
              VfSpacing.x26,
              VfSpacing.screenInline,
              VfSpacing.screenBottomExtra,
            ),
            children: <Widget>[
              const Icon(
                Icons.hourglass_bottom_rounded,
                size: 44,
                color: VfColors.slate2,
              ),
              const SizedBox(height: VfSpacing.x14),
              Text(
                'Votre compte est ferme',
                style: VfTextStyles.hMd,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: VfSpacing.x8),
              Text(
                effacementLe == null
                    // On ne fabrique pas de date : sans la donnee, on dit la
                    // regle, qui est vraie dans tous les cas.
                    ? 'Vos donnees seront effacees trente jours après votre '
                          'demande. Jusque-la, vous pouvez encore revenir en '
                          'arriere.'
                    : 'Vos donnees seront effacees le '
                          '${_date(effacementLe)}. Jusque-la, vous pouvez '
                          'encore revenir en arriere.',
                style: VfTextStyles.tSm,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: VfSpacing.stack),
              VfCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    const VfEyebrow('Comment annuler'),
                    const SizedBox(height: VfSpacing.x8),
                    Text(
                      'L annulation ne passe pas par l application. Un '
                      'courriel vous a ete envoye avec un lien signe : c est '
                      'lui qui retablit votre compte. Ce choix est volontaire — '
                      'il faut pouvoir revenir en arriere meme quand on ne peut '
                      'plus se connecter.',
                      style: VfTextStyles.tSm,
                    ),
                    if (demandeeLe != null) ...<Widget>[
                      const SizedBox(height: VfSpacing.x8),
                      Text(
                        'Demande enregistree le ${_date(demandeeLe)}.',
                        style: VfTextStyles.tXs,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: VfSpacing.stack),
              VfGhostButton(
                label: 'Se déconnecter',
                icon: Icons.logout_rounded,
                expanded: true,
                onPressed: _deconnecter,
              ),
              const SizedBox(height: VfSpacing.stackSm),
              Text(
                // `POST /auth/logout` est l'autre route encore servie, et pour
                // une raison precise : « refuser de fermer un acces reviendrait
                // a imposer de garder un jeton vivant sur un telephone dont on
                // ne veut plus ».
                'Vous déconnecter reste possible, et efface toutes vos donnees '
                'de ce telephone.',
                style: VfTextStyles.tXs,
                textAlign: TextAlign.center,
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _deconnecter() async {
    await ref.read(deconnexionProvider)();
    if (mounted) {
      context.go(VfRoutes.login);
    }
  }

  String _date(DateTime date) {
    final DateTime local = date.toLocal();
    String deux(int n) => n.toString().padLeft(2, '0');
    return '${deux(local.day)}/${deux(local.month)}/${local.year}';
  }
}
