import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../data/models/auth_token.dart';
import '../../../../domain/repositories/auth_repository.dart';
import '../../../../domain/usecases/auth/ouvrir_session.dart';
import 'auth_providers.dart';
import 'etat_auth.dart';

/// Les écrans 04 et 06 — demande de code, puis nouveau mot de passe.
///
/// # La règle qui gouverne l'écran 04, et elle est inhabituelle
///
/// `POST /auth/password/forgot` répond **toujours pareil**, que le compte existe
/// ou non, et **même quand le plafond de trois demandes par dix minutes est
/// saturé**. Deux conséquences, écrites dans le contrat et appliquées ici :
///
/// 1. **Ne jamais brancher l'affichage sur l'existence du compte**, ni sur le
///    temps de réponse — « une différence de latence énumère les comptes aussi
///    sûrement qu'un message ».
/// 2. **Aucun compte à rebours de renvoi.** Le plafond est invisible au client
///    par construction ; un compte à rebours fabriqué localement mentirait.
///
/// La première conséquence a une traduction concrète et facile à manquer : le
/// client **égalise aussi son propre temps de réponse**. Un serveur qui répond
/// en 40 ms pour une adresse inconnue et en 900 ms pour une adresse à qui il
/// envoie un courriel se lit sur le chronomètre, quel que soit le message. On
/// attend donc [delaiPlancher] avant de rendre la main, dans tous les cas.
class PasswordResetController extends Notifier<EtatAuth> {
  @override
  EtatAuth build() => const EtatAuth();

  /// Le plancher de temps de réponse de l'écran 04.
  ///
  /// POURQUOI un plancher et non un délai fixe : un plancher n'ajoute rien quand
  /// le réseau est déjà lent, et il masque l'écart quand il est rapide. C'est la
  /// forme la moins coûteuse d'une réponse indifférenciée en temps.
  static const Duration delaiPlancher = Duration(milliseconds: 700);

  AuthRepository get _repository => ref.read(authRepositoryProvider);

  OuvrirSession get _ouvrir => ref.read(ouvrirSessionProvider);

  /// `POST /auth/password/forgot`.
  ///
  /// Rend `true` dès que l'écran peut passer à la saisie du code — c'est-à-dire
  /// **dans tous les cas sauf l'absence de réseau**. Un `429`, un compte
  /// inconnu, une adresse jamais inscrite : trois situations que le serveur
  /// refuse de distinguer, et que l'écran ne distingue donc pas non plus.
  Future<bool> demanderUnCode(String email) async {
    if (state.envoiEnCours) {
      return false;
    }
    state = state.enCours();

    // Les deux attentes partent ENSEMBLE : le plancher court pendant l'appel, il
    // ne s'y ajoute pas.
    final Future<void> plancher = Future<void>.delayed(delaiPlancher);
    Failure? echec;
    try {
      await _repository.motDePasseOublie(email);
    } on Failure catch (e) {
      echec = e;
    }
    await plancher;

    if (echec is NetworkFailure) {
      // Le refus hors ligne est immediat et explicite : rien n'est parti, et
      // une demande de code ne se met pas en file.
      state = const EtatAuth(messageGlobal: EtatAuth.phraseHorsLigne);
      return false;
    }

    state = const EtatAuth();
    return true;
  }

  /// `POST /auth/password/reset` — le troisième point d'entrée qui rend un
  /// jeton.
  ///
  /// Le serveur révoque **tous** les jetons, toutes les sessions web et tous les
  /// cookies « se souvenir de moi » : l'ancien jeton de ce téléphone est mort au
  /// moment où celui-ci arrive. `OuvrirSession` purge avant d'installer, ce qui
  /// est exactement ce que le contrat demande ici.
  Future<bool> reinitialiser({
    required String email,
    required String code,
    required String motDePasse,
  }) async {
    if (state.envoiEnCours) {
      return false;
    }
    state = state.enCours();

    try {
      final AuthToken jeton = await _repository.reinitialiserLeMotDePasse(
        email: email,
        code: code,
        motDePasse: motDePasse,
      );
      await _ouvrir(jeton);
      state = const EtatAuth();
      return true;
    } on Failure catch (e) {
      state = EtatAuth.depuisEchec(
        e,
        phraseDeRepli: e is NetworkFailure
            ? EtatAuth.phraseHorsLigne
            : EtatAuth.phraseIndisponible,
      );
      return false;
    }
  }
}

final NotifierProvider<PasswordResetController, EtatAuth>
passwordResetProvider = NotifierProvider<PasswordResetController, EtatAuth>(
  PasswordResetController.new,
);
