import '../../../domain/entities/wallet_portefeuille.dart';
import '../../../domain/repositories/wallet_repository.dart';
import '../../datasources/remote/profile/compte_remote_datasource.dart';

/// Le portefeuille — **sans aucune source locale**.
///
/// # Pourquoi ce dépôt n'a pas de source locale du tout
///
/// Ce n'est pas un oubli : c'est la forme que prend la règle. Le contrat range
/// le solde parmi les trois données qui ne viennent **jamais** du cache (§7.1),
/// et le raisonnement est celui-ci : « on relit cet écran précisément parce
/// qu'on attend un changement ; y montrer une valeur d'il y a cinq minutes fait
/// croire à un paiement perdu ».
///
/// La grille tarifaire voyage dans la même réponse et suit le même sort — un
/// prix périmé affiché avant un achat est une décision d'achat prise sur un faux
/// chiffre.
///
/// Conséquence assumée : **hors ligne, cet écran n'a rien à montrer**, et il le
/// dit. Le relevé des transactions, lui, n'est pas caché non plus : il porte des
/// soldes intermédiaires, et un relevé partiel raconterait une histoire fausse.
///
/// # Ce que ce dépôt ne fait jamais
///
/// Il ne crédite pas, ne débite pas, ne met aucun paiement en file. « Un
/// paiement ne se met pas en file, jamais » (05-HORS-LIGNE §5.7). Le refus hors
/// ligne est explicite et il vit dans le cas d'usage, pas ici : c'est là qu'il
/// peut porter une phrase adressée à quelqu'un.
class WalletRepositoryImpl implements WalletRepository {
  const WalletRepositoryImpl({required this.distant});

  // Les dependances sont injectees et PUBLIQUES, comme la file d'ecriture du
  // socle (`PendingOpsQueue`) : un test doit pouvoir relire ce qu'on lui a
  // donne, et un champ prive nomme differemment de son parametre nomme n'est
  // pas exprimable en Dart sans dupliquer l'ecriture.
  final CompteRemoteDataSource distant;

  @override
  Future<Portefeuille> portefeuille() => distant.portefeuille();

  @override
  Future<DemandeRecharge> recharger({
    required String codePack,
    required String numero,
  }) => distant.recharger(codePack: codePack, numero: numero);

  @override
  Future<PageDeMouvements> mouvements({String? curseur}) =>
      distant.mouvements(curseur: curseur);
}
