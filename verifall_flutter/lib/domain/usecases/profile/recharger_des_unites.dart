import '../../../core/errors/failure.dart';
import '../../entities/wallet_portefeuille.dart';
import '../../repositories/wallet_repository.dart';

/// Le message opposé à une recharge tentée hors ligne.
///
/// POURQUOI ce n'est pas un type d'échec à part : `Failure` est **scellé** par
/// le socle, et le socle est en lecture seule. Un refus produit donc un
/// `ImpossibleActionFailure` — « l'action n'a pas de sens en l'état », dont le
/// contrat dit précisément que « le message porte l'information utile :
/// l'afficher tel quel, jamais un échec muet ». C'est exactement ce cas.
///
/// POURQUOI ce n'est pas un `NetworkFailure` : un échec de transport se rejoue,
/// et la file existe pour cela. Ici le refus est **délibéré** — « un paiement ne
/// se met pas en file, jamais » — et le confondre avec une panne de réseau
/// inviterait, un jour, à le mettre en file comme les autres.
const String messageRechargeHorsLigne =
    'La recharge a besoin du réseau : un paiement ne peut pas attendre en '
    'file. Reessayez des que vous etes connecte.';

/// Ouvrir une demande de recharge Mobile Money.
///
/// # Les cinq règles du paiement, et aucune n'est facultative
///
/// 1. **Le client envoie un code de pack et un numéro.** Jamais un montant,
///    jamais une quantité. Cette signature ne permet rien d'autre.
/// 2. **Le prix et la quantité sont lus en base** côté serveur.
/// 3. **L'opérateur est déduit du numéro** par le serveur (réserve C.7) : rien
///    ne le porte ici.
/// 4. **La réponse est `201` et le solde ne bouge pas.** Ce cas d'usage ne rend
///    aucun solde, et c'est délibéré : il n'y a rien à créditer côté client.
/// 5. **La recharge est refusée hors ligne**, explicitement.
///
/// # Ce que « pas de retour » veut dire
///
/// L'utilisateur peut fermer l'application de l'opérateur, perdre le réseau,
/// abandonner. L'écran affiche alors « en attente de confirmation » et **ne
/// conclut pas à l'échec** : le rappel signé peut arriver plus tard. C'est
/// pourquoi ce cas d'usage rend une [DemandeRecharge] — une demande ouverte —
/// et non un booléen de réussite.
class RechargerDesUnites {
  const RechargerDesUnites({
    required this.repository,
    required this.estHorsLigne,
  });

  // Les dependances sont injectees et PUBLIQUES, comme la file d'ecriture du
  // socle (`PendingOpsQueue`) : un test doit pouvoir relire ce qu'on lui a
  // donne, et un champ prive nomme differemment de son parametre nomme n'est
  // pas exprimable en Dart sans dupliquer l'ecriture.
  final WalletRepository repository;

  /// L'état du réseau est **injecté** plutôt que lu ici : « ne jamais bloquer
  /// l'interface sur cet état » est la règle générale (05-HORS-LIGNE §5.12), et
  /// la recharge est l'exception qui la confirme. La rendre injectable permet de
  /// vérifier le refus en test sans couper une carte réseau.
  final Future<bool> Function() estHorsLigne;

  Future<DemandeRecharge> call({
    required String codePack,
    required String numero,
  }) async {
    if (await estHorsLigne()) {
      throw const ImpossibleActionFailure(
        serverMessage: messageRechargeHorsLigne,
      );
    }

    final String propre = numero.replaceAll(RegExp(r'\s+'), '');
    if (propre.length < 8) {
      // La seule verification locale : elle evite un aller-retour pour une
      // faute evidente. Le format reel — et l'opérateur — sont l'affaire du
      // serveur, qui repond 422 avec un message deja redige.
      throw const ValidationFailure(
        fieldErrors: <String, List<String>>{
          'numero': <String>['Entrez votre numéro Mobile Money complet.'],
        },
      );
    }

    return repository.recharger(codePack: codePack, numero: propre);
  }
}
