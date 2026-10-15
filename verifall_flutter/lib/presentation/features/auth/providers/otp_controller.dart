import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../data/models/auth_token.dart';
import '../../../../domain/repositories/auth_repository.dart';
import '../../../../domain/usecases/auth/ouvrir_session.dart';
import '../../../../domain/usecases/auth/regles_de_saisie.dart';
import 'auth_providers.dart';
import 'etat_auth.dart';

/// L'état de l'écran 05.
@immutable
class EtatOtp {
  const EtatOtp({
    this.code = '',
    this.formulaire = const EtatAuth(),
    this.codeRenvoye = false,
  });

  final String code;
  final EtatAuth formulaire;

  /// Vrai après un « Renvoyer » réussi. Sert uniquement à confirmer le geste ;
  /// **il ne déclenche aucun compte à rebours** — voir l'en-tête de
  /// [OtpController].
  final bool codeRenvoye;

  bool get complet => ReglesDeSaisie.codeComplet(code);

  /// Le message à afficher sous les cases, quand le serveur en donne un.
  ///
  /// Le serveur distingue lui-même les trois cas — « Code incorrect. Vérifiez
  /// votre saisie. », « Ce code a expiré. Demandez-en un nouveau. », « Trop de
  /// tentatives. Demandez un nouveau code. » — et le client les affiche tels
  /// quels. Confondre « faux » et « expiré » fait ressaisir en boucle un code
  /// qui ne marchera jamais ; les distinguer soi-même, à partir d'un compteur
  /// local, serait inventer un fait serveur.
  String? get messageDuCode =>
      formulaire.erreurDe('code') ?? formulaire.messageGlobal;

  bool get enErreur => messageDuCode != null;

  EtatOtp copyWith({String? code, EtatAuth? formulaire, bool? codeRenvoye}) =>
      EtatOtp(
        code: code ?? this.code,
        formulaire: formulaire ?? this.formulaire,
        codeRenvoye: codeRenvoye ?? this.codeRenvoye,
      );
}

/// L'écran 05 — saisie du code à six chiffres.
///
/// # Pourquoi il n'y a pas de famille de providers par `purpose`
///
/// Le simulateur note l'état `otpControllerProvider(purpose)`. Or `purpose` ne
/// change **que** deux choses : la copie affichée et la destination après
/// succès. Il ne change ni l'appel — `POST /auth/otp/verify` dans les deux cas —
/// ni les six chiffres, ni la manière d'échouer. Une famille aurait multiplié
/// les instances sans rien séparer.
///
/// # Pourquoi il n'y a **aucun** compte à rebours de renvoi
///
/// Le point de vigilance du simulateur dit que le délai vient du serveur. Le
/// contrat impose l'inverse : `otp/request`, `otp/resend` et `password/forgot`
/// répondent **toujours pareil**, « et même quand le plafond est saturé — un 429
/// visible dirait "cette adresse intéresse quelqu'un" ».
///
/// Il n'y a donc **aucun délai à lire**, et un délai calculé sur l'horloge du
/// téléphone mentirait. Le bouton « Renvoyer » reste disponible en permanence ;
/// le plafond réel — trois demandes par dix minutes et par adresse — est
/// invisible au client par construction.
class OtpController extends Notifier<EtatOtp> {
  @override
  EtatOtp build() => const EtatOtp();

  AuthRepository get _repository => ref.read(authRepositoryProvider);

  OuvrirSession get _ouvrir => ref.read(ouvrirSessionProvider);

  void majCode(String code) {
    // Une nouvelle saisie efface l'erreur precedente : garder « Code incorrect »
    // sous des cases qu'on est en train de reremplir fait douter de la saisie en
    // cours.
    state = state.copyWith(code: code, formulaire: const EtatAuth());
  }

  /// `POST /auth/otp/verify` — active le compte **et** rend le jeton.
  Future<bool> confirmer(String email) async {
    if (state.formulaire.envoiEnCours || !state.complet) {
      return false;
    }
    state = state.copyWith(formulaire: state.formulaire.enCours());

    try {
      final AuthToken jeton = await _repository.verifierLeCode(
        email: email,
        code: state.code,
      );
      // Le meme chemin unique que la connexion et que la reinitialisation.
      await _ouvrir(jeton);
      state = const EtatOtp();
      return true;
    } on Failure catch (echec) {
      state = state.copyWith(
        formulaire: EtatAuth.depuisEchec(
          echec,
          phraseDeRepli: echec is NetworkFailure
              ? EtatAuth.phraseHorsLigne
              : EtatAuth.phraseIndisponible,
        ),
      );
      return false;
    }
  }

  /// `POST /auth/otp/resend`.
  ///
  /// POURQUOI le résultat est le même quoi qu'il arrive, hors panne de réseau :
  /// la route ne dit pas si l'adresse existe, ni si le plafond est atteint.
  /// Afficher un échec là où le serveur reste muet apprendrait au client quelque
  /// chose que le serveur refuse de dire.
  Future<void> renvoyer(String email) async {
    if (state.formulaire.envoiEnCours) {
      return;
    }
    state = state.copyWith(
      formulaire: state.formulaire.enCours(),
      codeRenvoye: false,
    );
    try {
      await _repository.renvoyerLeCode(email);
      state = state.copyWith(formulaire: const EtatAuth(), codeRenvoye: true);
    } on NetworkFailure {
      // Le seul echec honnete a montrer : rien n'est parti, et la personne doit
      // le savoir pour ne pas attendre un courriel qui n'arrivera pas.
      state = state.copyWith(
        formulaire: const EtatAuth(messageGlobal: EtatAuth.phraseHorsLigne),
      );
    } on Failure {
      // Tout le reste — y compris un 429 — reste indifferencie.
      state = state.copyWith(formulaire: const EtatAuth(), codeRenvoye: true);
    }
  }
}

final NotifierProvider<OtpController, EtatOtp> otpControllerProvider =
    NotifierProvider<OtpController, EtatOtp>(OtpController.new);
