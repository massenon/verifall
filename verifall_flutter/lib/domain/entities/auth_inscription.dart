import 'package:flutter/foundation.dart';

import 'auth_consentements.dart';

/// Ce qu'une inscription envoie, **exactement ce que le contrat attend** (§10.1).
///
/// # Pourquoi il n'y a pas de champ `ville`
///
/// Le simulateur en a un, pré-rempli à Cotonou. `POST /auth/register` **ne
/// l'accepte pas** : la ville se règle ensuite par `PATCH /me`. L'envoyer
/// n'aurait pas produit d'erreur — Laravel ignore un champ non validé — mais la
/// ville aurait été silencieusement perdue, ce qui est le pire des deux mondes :
/// un formulaire qui répond `201` et jette une saisie.
///
/// Réserve A.11, tranchée par l'arbitrage n° 85.
///
/// # Pourquoi `telephone` et `profession` ne sont pas facultatifs
///
/// Le contrat les liste sans `?`, et la validation du serveur les exige
/// (`telephone` en `min:8`, `profession` en `required`). Le simulateur annonçait
/// « Téléphone (facultatif) » et n'avait pas de profession : l'étiquette a été
/// corrigée et le champ ajouté, parce qu'un formulaire ne doit pas promettre
/// moins que ce qu'il exigera.
@immutable
class DemandeInscription {
  const DemandeInscription({
    required this.prenom,
    required this.nom,
    required this.telephone,
    required this.profession,
    required this.email,
    required this.motDePasse,
    required this.consentements,
    this.codeParrainage,
  });

  final String prenom;
  final String nom;
  final String telephone;
  final String profession;
  final String email;

  /// **Ne sort jamais d'ici vers un journal.** Le corps d'une requête contient
  /// des jetons, des mots de passe et des codes à usage unique : le logger du
  /// socle ne porte ni corps, ni en-tête, ni chemin brut, et cette classe n'a
  /// volontairement pas de `toString()` qui l'exposerait.
  final String motDePasse;

  final AuthConsentements consentements;

  /// Facultatif au contrat, et facultatif ici.
  final String? codeParrainage;

  /// Le corps de `POST /auth/register`, tel quel.
  ///
  /// POURQUOI la traduction vit dans le domaine et pas dans le dépôt : elle est
  /// la seule chose qui garantisse la correspondance champ à champ avec le
  /// contrat, et elle se teste sans réseau.
  Map<String, Object?> toBody() => <String, Object?>{
    'prenom': prenom.trim(),
    'nom': nom.trim(),
    'telephone': telephone.trim(),
    'profession': profession.trim(),
    'email': email.trim(),
    'mot_de_passe': motDePasse,
    'cgu': consentements.cgu,
    'confidentialite': consentements.confidentialite,
    // Le refus est trace comme l'accord (arbitrage n° 11) : le champ part
    // TOUJOURS, y compris a false. L'omettre laisserait le serveur decider a la
    // place de la personne.
    'newsletter': consentements.newsletter,
    if (codeParrainage != null && codeParrainage!.trim().isNotEmpty)
      'code_parrainage': codeParrainage!.trim(),
  };

  /// POURQUOI aucun champ n'est repris ici : un `toString()` d'objet de
  /// formulaire finit toujours dans un journal ou dans un rapport de plantage.
  @override
  String toString() => 'DemandeInscription(<masquée>)';
}

/// La réponse de `POST /auth/register` : `201 { uuid, email, etape }`.
///
/// **Aucun jeton n'est rendu.** Le compte existe mais n'est pas activé ; c'est
/// `POST /auth/otp/verify` qui l'ouvre, une fois la maîtrise de la boîte
/// prouvée. Cette classe n'a donc volontairement pas de champ `token` — en
/// ajouter un « au cas où » aurait invité un écran à en chercher un.
@immutable
class AuthInscription {
  const AuthInscription({
    required this.uuid,
    required this.email,
    required this.etape,
  });

  final String uuid;
  final String email;

  /// La seule valeur que le contrat publie est `code_envoye`. Elle est gardée
  /// telle quelle, en chaîne : en faire une énumération fermée ferait tomber le
  /// client le jour où le serveur en ajoute une, ce que §6.2 déclare compatible.
  final String etape;

  @override
  String toString() => 'AuthInscription($uuid)';
}
