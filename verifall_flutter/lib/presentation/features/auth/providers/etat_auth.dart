import 'package:flutter/foundation.dart';

import '../../../../core/errors/failure.dart';

/// L'état commun aux quatre formulaires de M1.
///
/// # Pourquoi un seul état pour quatre écrans
///
/// Les quatre font la même chose : ils envoient, ils attendent, et ils échouent
/// de trois façons — un champ invalide, un message global, ou rien du tout parce
/// que le réseau est absent. Quatre états distincts auraient donné quatre
/// manières de dire « en cours », et l'un des quatre aurait fini par oublier de
/// verrouiller son bouton.
///
/// # Les deux formes d'erreur, et pourquoi elles ne se mélangent pas
///
/// - [erreursDeChamp] vient de `ValidationFailure.details` : clé = **champ
///   envoyé**, et le message se rend **sous le champ concerné**. « Cette adresse
///   est déjà prise » se dit sur le champ de l'adresse, pas dans un toast qui
///   aura disparu quand la personne cherchera où corriger.
/// - [messageGlobal] est tout le reste : identifiants invalides, verrouillage,
///   panne. Il s'affiche en toast.
///
/// Un `422` remplit le premier ; un `401` remplit le second. Les deux au même
/// moment n'arrive pas, mais rien ne l'interdit et rien ne casse si cela arrive.
@immutable
class EtatAuth {
  const EtatAuth({
    this.envoiEnCours = false,
    this.messageGlobal,
    this.erreursDeChamp = const <String, List<String>>{},
  });

  /// Pendant l'envoi : le bouton passe en indicateur et les champs se
  /// verrouillent. **Pas de voile plein écran** — l'écran doit rester lisible.
  final bool envoiEnCours;

  /// Le message du serveur, **affiché tel quel**. Le contrat garantit qu'il est
  /// déjà rédigé en français (§2.1) ; le client ne le traduit pas, ne le
  /// reformule pas, et n'invente pas de repli quand il en reçoit un.
  final String? messageGlobal;

  final Map<String, List<String>> erreursDeChamp;

  bool get aUneErreurGlobale =>
      messageGlobal != null && messageGlobal!.trim().isNotEmpty;

  String? erreurDe(String champ) {
    final List<String>? messages = erreursDeChamp[champ];
    return messages == null || messages.isEmpty ? null : messages.first;
  }

  EtatAuth enCours() => const EtatAuth(envoiEnCours: true);

  /// L'état qui suit un échec.
  ///
  /// POURQUOI la traduction se fait ici plutôt que dans chaque écran : quatre
  /// écrans qui décideraient chacun quoi montrer sur un `NetworkFailure`
  /// donneraient quatre phrases pour la même panne.
  factory EtatAuth.depuisEchec(Failure echec, {required String phraseDeRepli}) {
    if (echec is ValidationFailure) {
      return EtatAuth(
        erreursDeChamp: echec.fieldErrors,
        // Le message global du 422 n'est repris que s'il n'y a AUCUN champ a
        // souligner : sinon on afficherait deux fois la meme chose, une fois
        // sous le champ et une fois en toast.
        messageGlobal: echec.fieldErrors.isEmpty ? echec.serverMessage : null,
      );
    }
    if (echec is NetworkFailure) {
      // Le serveur n'a rien dit : c'est a l'ecran d'ecrire sa phrase, et elle
      // ne contient JAMAIS de code HTTP brut.
      return EtatAuth(messageGlobal: phraseDeRepli);
    }
    return EtatAuth(
      messageGlobal: echec.hasServerMessage
          ? echec.serverMessage
          : phraseDeRepli,
    );
  }

  /// La phrase d'absence de réseau, écrite une seule fois pour les six écrans.
  ///
  /// POURQUOI elle ne propose pas « Réessayer plus tard » au sens de la file
  /// d'écriture : « une connexion ne se met pas en file — rejouer une
  /// authentification plus tard n'a aucun sens, et la personne resterait devant
  /// un écran figé ».
  static const String phraseHorsLigne =
      'Vous êtes hors ligne. Reconnectez-vous à Internet pour continuer.';

  /// La phrase de panne, quand le serveur n'en fournit pas.
  static const String phraseIndisponible =
      'Le service est momentanément indisponible. Réessayez dans un instant.';
}
