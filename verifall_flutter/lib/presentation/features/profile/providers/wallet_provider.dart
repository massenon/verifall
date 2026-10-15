import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../domain/entities/wallet_portefeuille.dart';
import '../../../../domain/repositories/wallet_repository.dart';
import 'profile_dependencies.dart';
import '../../../../core/state/riverpod_retry.dart';
import '../../../../core/session/solde_unites.dart';

/// L'état de l'écran 26.
///
/// # Ce que cet écran n'a pas le droit de faire
///
/// Il ne crédite **jamais**. `POST /me/wallet/topup` répond `201` et le solde ne
/// bouge pas : « il ne bougera qu'au rappel signé de l'opérateur ». Après un
/// retour d'application, l'écran **relit** `GET /me/wallet` — il ne calcule
/// rien.
///
/// Il ne montre **jamais** une valeur de cache. Cet état n'est donc jamais
/// persisté, et hors ligne l'écran dit franchement qu'il a besoin du réseau.
@immutable
class EtatPortefeuille {
  const EtatPortefeuille({
    required this.portefeuille,
    required this.mouvements,
    required this.curseurSuivant,
    required this.encoreDesMouvements,
    this.chargeDavantage = false,
    this.demandeEnAttente,
  });

  final Portefeuille portefeuille;
  final List<MouvementUnite> mouvements;
  final String? curseurSuivant;
  final bool encoreDesMouvements;
  final bool chargeDavantage;

  /// La dernière demande de recharge ouverte, s'il y en a une.
  ///
  /// Elle alimente le bandeau « en attente de confirmation ». **Ce n'est pas un
  /// échec** : « le retour peut ne pas arriver — l'utilisateur peut fermer
  /// l'application de l'opérateur, perdre le réseau, abandonner — et le rappel
  /// peut arriver plus tard ».
  final DemandeRecharge? demandeEnAttente;

  int get solde => portefeuille.solde;

  EtatPortefeuille copyWith({
    Portefeuille? portefeuille,
    List<MouvementUnite>? mouvements,
    String? curseurSuivant,
    bool effacerCurseur = false,
    bool? encoreDesMouvements,
    bool? chargeDavantage,
    DemandeRecharge? demandeEnAttente,
  }) {
    return EtatPortefeuille(
      portefeuille: portefeuille ?? this.portefeuille,
      mouvements: mouvements ?? this.mouvements,
      curseurSuivant: effacerCurseur
          ? null
          : (curseurSuivant ?? this.curseurSuivant),
      encoreDesMouvements: encoreDesMouvements ?? this.encoreDesMouvements,
      chargeDavantage: chargeDavantage ?? this.chargeDavantage,
      demandeEnAttente: demandeEnAttente ?? this.demandeEnAttente,
    );
  }
}

/// `walletProvider` — le nom vient du simulateur.
/// Le réessai automatique de Riverpod est désactivé — voir
/// `profile_provider.dart` pour la raison : la politique de réessai de
/// l'application est écrite dans la couche réseau, et une seconde couche
/// invisible contredirait « un seul essai » du contrat.
final AsyncNotifierProvider<WalletNotifier, EtatPortefeuille> walletProvider =
    AsyncNotifierProvider<WalletNotifier, EtatPortefeuille>(
      WalletNotifier.new,
      isAutoDispose: true,
      retry: sansReessaiAutomatique,
    );

class WalletNotifier extends AsyncNotifier<EtatPortefeuille> {
  WalletRepository get _portefeuilles => ref.read(walletRepositoryProvider);

  @override
  Future<EtatPortefeuille> build() => _charger();

  /// Relit le solde depuis le serveur.
  ///
  /// Appelé au retour d'application (`AppLifecycleState.resumed`) : « c'est le
  /// seul moyen de refléter un crédit arrivé pendant que l'utilisateur était
  /// chez l'opérateur ».
  ///
  /// [conserverLaDemande] garde le bandeau « en attente de confirmation » tant
  /// que le solde n'a pas bougé : le rappel signé peut prendre du temps, et
  /// faire disparaître le bandeau ferait croire que la demande a été perdue.
  Future<void> relire({bool conserverLaDemande = true}) async {
    final DemandeRecharge? demande = conserverLaDemande
        ? state.value?.demandeEnAttente
        : null;
    state = await AsyncValue.guard<EtatPortefeuille>(
      () => _charger(demande: demande),
    );
  }

  /// Enregistre la demande ouverte par la recharge.
  ///
  /// **Le solde n'est pas modifié ici.** Il est relu, et c'est le serveur qui
  /// tranche : le client ne crédite jamais.
  Future<void> demandeOuverte(DemandeRecharge demande) async {
    final EtatPortefeuille? courant = state.value;
    if (courant != null) {
      state = AsyncValue<EtatPortefeuille>.data(
        courant.copyWith(demandeEnAttente: demande),
      );
    }
    await relire();
  }

  /// La page suivante du relevé.
  ///
  /// Le curseur est renvoyé **tel quel** ; il n'est ni lu, ni fabriqué. Il n'y a
  /// aucun filtre à réémettre sur ce relevé — s'il en apparaissait un, il
  /// devrait accompagner le curseur (§3.4 du contrat).
  Future<void> chargerPlus() async {
    final EtatPortefeuille? courant = state.value;
    if (courant == null ||
        courant.chargeDavantage ||
        courant.curseurSuivant == null) {
      return;
    }
    state = AsyncValue<EtatPortefeuille>.data(
      courant.copyWith(chargeDavantage: true),
    );
    try {
      final PageDeMouvements page = await _portefeuilles.mouvements(
        curseur: courant.curseurSuivant,
      );
      state = AsyncValue<EtatPortefeuille>.data(
        courant.copyWith(
          mouvements: <MouvementUnite>[
            ...courant.mouvements,
            ...page.mouvements,
          ],
          curseurSuivant: page.curseurSuivant,
          effacerCurseur: page.curseurSuivant == null,
          encoreDesMouvements: page.encoreDesPages,
          chargeDavantage: false,
        ),
      );
    } on Failure {
      // Un echec de page suivante ne doit pas effacer la premiere : on rend la
      // main sans rien perdre, et le bouton reste disponible.
      state = AsyncValue<EtatPortefeuille>.data(
        courant.copyWith(chargeDavantage: false),
      );
      rethrow;
    }
  }

  Future<EtatPortefeuille> _charger({DemandeRecharge? demande}) async {
    // Le solde D'ABORD, et sans repli sur le cache : c'est la seule lecture de
    // l'application qui n'a pas de valeur de secours acceptable.
    final Portefeuille portefeuille = await _portefeuilles.portefeuille();

    // La lecture la plus autorisee du solde : elle alimente la pastille de tous
    // les autres ecrans (arbitrage n° 135).
    ref.read(soldeUnitesProvider.notifier).relu(portefeuille.solde);

    PageDeMouvements page = PageDeMouvements.vide;
    try {
      page = await _portefeuilles.mouvements();
    } on Failure {
      // Le releve peut manquer sans que l'ecran soit inutilisable : le solde et
      // les packs, eux, sont la. Un releve vide se distingue d'un releve absent
      // par le fait que le solde, lui, a bien ete lu.
      page = PageDeMouvements.vide;
    }

    return EtatPortefeuille(
      portefeuille: portefeuille,
      mouvements: page.mouvements,
      curseurSuivant: page.curseurSuivant,
      encoreDesMouvements: page.encoreDesPages,
      demandeEnAttente: demande,
    );
  }
}
