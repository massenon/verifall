import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../data/models/auth_token.dart';
import '../../../../domain/repositories/auth_repository.dart';
import '../../../../domain/usecases/auth/ouvrir_session.dart';
import 'auth_providers.dart';
import 'etat_auth.dart';

/// L'écran 02 — connexion.
///
/// # Ce que cet écran ne distingue pas, et c'est délibéré
///
/// Un mot de passe faux et **un compte jamais activé** reçoivent tous deux
/// `401 non_authentifie`, avec le même message. Le contrat en donne la raison :
/// « distinguer les deux transformerait l'écran de connexion en annuaire des
/// inscrits » (§1.6). Il n'y a donc ici **aucune branche** qui regarde si le
/// compte existe : il n'y a rien à regarder, et le client ne peut pas savoir.
///
/// # Ce qu'il fait d'un verrouillage
///
/// Trop de tentatives donne `429 debit_depasse`, et **le message du serveur
/// contient déjà le délai** (« Réessayez dans 3 minute(s). »). L'écran l'affiche
/// tel quel. Il ne fabrique pas de compte à rebours : une horloge locale
/// dériverait, et le serveur est seul à savoir où en est le verrou.
class LoginController extends Notifier<EtatAuth> {
  @override
  EtatAuth build() => const EtatAuth();

  AuthRepository get _repository => ref.read(authRepositoryProvider);

  OuvrirSession get _ouvrir => ref.read(ouvrirSessionProvider);

  /// Rend `true` quand la session est ouverte.
  ///
  /// POURQUOI le contrôleur ne navigue pas lui-même : un contrôleur qui appelle
  /// `context.go` ne se teste plus sans monter un routeur, et il devient
  /// impossible de réutiliser le même appel depuis un autre point d'entrée.
  Future<bool> connexion({
    required String email,
    required String motDePasse,
  }) async {
    if (state.envoiEnCours) {
      return false;
    }
    state = state.enCours();

    try {
      final AuthToken jeton = await _repository.connexion(
        email: email,
        motDePasse: motDePasse,
      );
      // LE chemin unique : c'est ici, et seulement ici, qu'un jeton entre dans
      // le stockage securise depuis cet ecran.
      await _ouvrir(jeton);
      state = const EtatAuth();
      return true;
    } on Failure catch (echec) {
      state = EtatAuth.depuisEchec(
        echec,
        phraseDeRepli: echec is NetworkFailure
            ? EtatAuth.phraseHorsLigne
            : EtatAuth.phraseIndisponible,
      );
      return false;
    }
  }
}

final NotifierProvider<LoginController, EtatAuth> loginControllerProvider =
    NotifierProvider<LoginController, EtatAuth>(LoginController.new);
