import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/wallet_portefeuille.dart';
import '../../../shared/shared.dart';
import '../providers/profile_dependencies.dart';
import '../providers/wallet_provider.dart';
import '../widgets/wallet_widgets.dart';

/// Écran 26 · Mes unités.
///
/// # Les trois choses que cet écran ne fait jamais
///
/// 1. **Il ne montre pas un solde de cache.** Le contrat l'interdit (§7.1), et
///    hors ligne l'écran le dit franchement au lieu d'afficher un chiffre.
/// 2. **Il ne crédite pas.** `topup` répond `201` et le solde ne bouge pas ; il
///    est **relu** au retour d'application.
/// 3. **Il ne conclut pas à l'échec** quand le retour n'arrive pas : « le rappel
///    peut arriver plus tard », et le bandeau dit « en attente de
///    confirmation ».
class UnitsPage extends ConsumerStatefulWidget {
  const UnitsPage({super.key});

  @override
  ConsumerState<UnitsPage> createState() => _UnitsPageState();
}

class _UnitsPageState extends ConsumerState<UnitsPage> {
  /// Le retour d'application.
  ///
  /// POURQUOI il est branché ici et nulle part ailleurs : c'est **le seul moyen
  /// de refléter un crédit arrivé pendant que l'utilisateur était chez
  /// l'opérateur**. Sans lui, quelqu'un qui revient de son application Mobile
  /// Money verrait son ancien solde et croirait son paiement perdu.
  late final AppLifecycleListener _cycle;

  @override
  void initState() {
    super.initState();
    _cycle = AppLifecycleListener(
      onResume: () => ref.read(walletProvider.notifier).relire(),
    );
  }

  @override
  void dispose() {
    _cycle.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<EtatPortefeuille> etat = ref.watch(walletProvider);

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Mes unités'),
      body: etat.when(
        loading: () => const _SqueletteUnites(),
        error: (Object erreur, StackTrace _) => _Indisponible(
          message: erreur is Failure ? erreur.serverMessage : null,
          onReessayer: () => ref.read(walletProvider.notifier).relire(),
        ),
        data: _corps,
      ),
    );
  }

  Widget _corps(EtatPortefeuille etat) {
    return RefreshIndicator(
      onRefresh: () => ref.read(walletProvider.notifier).relire(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: <Widget>[
          VfBalanceCard(solde: etat.solde),
          if (etat.demandeEnAttente != null) ...<Widget>[
            const SizedBox(height: VfSpacing.stackSm),
            _EnAttenteDeConfirmation(demande: etat.demandeEnAttente!),
          ],
          const SizedBox(height: VfSpacing.stack),
          Text('Recharger', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.stackSm),
          for (final PackUnites pack in etat.portefeuille.packs) ...<Widget>[
            VfPackTile(pack: pack, onTap: () => _ouvrirLaFeuille(pack)),
            const SizedBox(height: VfSpacing.stackSm),
          ],
          const VfCostNotice(),
          const SizedBox(height: VfSpacing.stack),
          Text('Historique des transactions', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.stackSm),
          if (etat.mouvements.isEmpty)
            const VfEmptyState(
              kind: VfEmptyKind.rienEncore,
              icon: Icons.receipt_long_outlined,
              title: 'Aucune transaction pour l instant',
              message:
                  'Vos recharges et vos vérifications apparaitront ici. Des '
                  'unités vous sont egalement offertes chaque mois.',
            )
          else
            VfMenuList(
              children: <Widget>[
                for (final MouvementUnite mouvement in etat.mouvements)
                  VfTransactionTile(mouvement: mouvement),
              ],
            ),
          if (etat.encoreDesMouvements) ...<Widget>[
            const SizedBox(height: VfSpacing.stackSm),
            VfGhostButton(
              label: 'Voir plus',
              expanded: true,
              onPressed: etat.chargeDavantage ? null : _chargerPlus,
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _chargerPlus() async {
    try {
      await ref.read(walletProvider.notifier).chargerPlus();
    } on Failure catch (echec) {
      if (mounted) {
        VfToast.error(
          context,
          echec.serverMessage ?? 'La suite du releve n a pas pu être lue.',
        );
      }
    }
  }

  Future<void> _ouvrirLaFeuille(PackUnites pack) async {
    await VfBottomSheet.show<void>(
      context: context,
      title: 'Recharger ${pack.unites} unités',
      builder: (BuildContext sheetContext) => VfTopUpSheet(
        pack: pack,
        onPayer: (String numero) => _payer(sheetContext, pack, numero),
      ),
    );
  }

  Future<void> _payer(
    BuildContext sheetContext,
    PackUnites pack,
    String numero,
  ) async {
    try {
      final DemandeRecharge demande = await ref.read(
        rechargerDesUnitesProvider,
      )(codePack: pack.code, numero: numero);
      if (sheetContext.mounted) {
        Navigator.of(sheetContext).pop();
      }
      await ref.read(walletProvider.notifier).demandeOuverte(demande);
      if (mounted) {
        VfToast.show(
          context,
          // Ni « credite », ni « paye » : la demande est OUVERTE, et c'est le
          // rappel signe de l'operateur qui la conclura.
          message:
              'Demande envoyee. Votre solde sera mis a jour des que l opérateur '
              'confirme le paiement.',
        );
      }
    } on Failure catch (echec) {
      if (sheetContext.mounted) {
        VfToast.error(
          sheetContext,
          echec.serverMessage ??
              'La demande de recharge n a pas pu être ouverte.',
        );
      }
    }
  }
}

/// Le bandeau « en attente de confirmation ».
///
/// **Ce n'est pas un échec.** L'utilisateur peut avoir fermé l'application de
/// l'opérateur, perdu le réseau, abandonné ; le rappel signé peut arriver plus
/// tard. Annoncer un échec ferait relancer un paiement déjà en cours.
class _EnAttenteDeConfirmation extends StatelessWidget {
  const _EnAttenteDeConfirmation({required this.demande});

  final DemandeRecharge demande;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      tight: true,
      background: VfColors.primarySoft,
      border: VfColors.primary.withValues(alpha: 0.25),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Icon(
            Icons.hourglass_top_rounded,
            size: 18,
            color: VfColors.primaryDark,
          ),
          const SizedBox(width: VfSpacing.x8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'En attente de confirmation',
                  style: VfTextStyles.tSm.copyWith(
                    color: VfColors.primaryDark,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  // Le libelle de statut vient du serveur : le client n'a pas
                  // de table de correspondance des statuts de paiement.
                  '${demande.statutLibelle}. Votre solde bougera des que '
                  'l opérateur aura confirme.',
                  style: VfTextStyles.tXs.copyWith(color: VfColors.primaryDark),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// L'état « pas de réseau » de cet écran-là.
///
/// POURQUOI il est plus franc qu'ailleurs : les autres écrans du compte savent
/// se replier sur le cache. Celui-ci n'a **rien** à montrer, par construction, et
/// le dire vaut mieux que d'afficher une page vide qui ressemble à une panne.
class _Indisponible extends StatelessWidget {
  const _Indisponible({required this.onReessayer, this.message});

  final String? message;
  final VoidCallback onReessayer;

  @override
  Widget build(BuildContext context) {
    return VfEmptyState(
      kind: VfEmptyKind.rienEncore,
      icon: Icons.cloud_off_rounded,
      title: 'Solde indisponible',
      message:
          message ??
          'Votre solde ne se lit jamais depuis votre telephone : il doit venir '
              'du serveur, sans quoi il pourrait être perime au moment ou vous '
              'decidez d acheter.',
      action: VfGhostButton(label: 'Réessayer', onPressed: onReessayer),
    );
  }
}

class _SqueletteUnites extends StatelessWidget {
  const _SqueletteUnites();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        // Un squelette, jamais une valeur en cache : c'est l'un des trois
        // endroits ou « il n'y a rien a esquisser » et ou le chargement doit
        // etre visible.
        VfSkeleton(height: 156, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 64, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 64, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 64, borderRadius: VfRadii.lgAll),
      ],
    );
  }
}
