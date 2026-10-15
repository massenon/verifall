import '../entities/wallet_portefeuille.dart';

/// Le portefeuille d'unités.
///
/// # La règle qui domine tout ce dépôt
///
/// **Le client ne crédite ni ne débite jamais.** Le solde est un fait serveur,
/// et il ne se lit jamais depuis le cache (contrat §7.1). Aucune méthode de ce
/// dépôt n'accepte de montant, et aucune n'écrit un solde en base locale.
abstract interface class WalletRepository {
  /// `GET /me/wallet` — solde **et** grille tarifaire, toujours du réseau.
  Future<Portefeuille> portefeuille();

  /// `POST /me/wallet/topup`.
  ///
  /// Le corps ne porte que [codePack] et [numero]. **Ni montant, ni quantité,
  /// ni opérateur** : le prix et la quantité sont lus en base, et l'opérateur
  /// est déduit du numéro par le serveur (réserve C.7).
  ///
  /// La réponse est `201` et **le solde ne bouge pas** : il ne bougera qu'au
  /// rappel signé de l'opérateur.
  Future<DemandeRecharge> recharger({
    required String codePack,
    required String numero,
  });

  /// `GET /me/wallet/transactions` — **paginé par curseur**, `page` n'existe
  /// pas.
  Future<PageDeMouvements> mouvements({String? curseur});
}
