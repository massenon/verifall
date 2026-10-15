import 'package:flutter/foundation.dart';

import 'verification_type_contenu.dart';

/// Un fichier choisi, décrit par ce que le client peut réellement en savoir.
@immutable
class FichierChoisi {
  const FichierChoisi({
    required this.chemin,
    required this.nom,
    required this.octets,
  });

  /// Le chemin **local**. Il ne quitte jamais l'appareil : le contrat n'expose
  /// aucun `file_path`, et le client n'en construit aucun (§9).
  final String chemin;

  final String nom;
  final int octets;

  /// Le poids en mégaoctets, arrondi à une décimale, pour l'afficher.
  String get poidsLisible {
    final double mo = octets / (1024 * 1024);
    return mo >= 1
        ? '${mo.toStringAsFixed(1)} Mo'
        : '${(octets / 1024).round()} Ko';
  }
}

/// Le brouillon de l'écran « Vérifier ».
///
/// POURQUOI il est immuable et vit dans un `Notifier` : le brouillon doit
/// **survivre à une mise en arrière-plan**. Quelqu'un qui bascule vers WhatsApp
/// pour recopier un message ne doit pas revenir sur un champ vide.
@immutable
class VerificationBrouillon {
  const VerificationBrouillon({
    this.type = TypeContenu.texte,
    this.saisie = '',
    this.contexte = '',
    this.fichier,
  });

  final TypeContenu type;

  /// Le texte ou l'adresse, selon le type. Une seule chaîne : deux champs
  /// séparés finiraient par partir tous les deux.
  final String saisie;

  final String contexte;

  final FichierChoisi? fichier;

  /// Longueurs imposées par le serveur (`VerificationController::soumettre`) :
  /// l'affirmation fait 10 à 2000 caractères, le contexte 140 au plus.
  ///
  /// POURQUOI le simulateur dit 1 000 et le serveur 2 000 : le simulateur borne
  /// sa zone de texte, le serveur accepte plus. On borne à **la valeur du
  /// simulateur** pour l'affichage du compteur, mais on ne refuse rien que le
  /// serveur accepterait — « un formulaire ne doit pas exiger plus que ce qui
  /// sera accepté ».
  static const int longueurTexteAffichee = 1000;
  static const int longueurContexteMax = 140;
  static const int longueurAffirmationMin = 10;

  VerificationBrouillon copyWith({
    TypeContenu? type,
    String? saisie,
    String? contexte,
    FichierChoisi? fichier,
    bool effacerFichier = false,
  }) {
    return VerificationBrouillon(
      type: type ?? this.type,
      saisie: saisie ?? this.saisie,
      contexte: contexte ?? this.contexte,
      fichier: effacerFichier ? null : (fichier ?? this.fichier),
    );
  }

  /// Changer de type **efface le fichier**.
  ///
  /// POURQUOI : c'est ce que fait le simulateur, et c'est juste — un fichier
  /// audio n'a rien à faire dans une analyse d'image, et le laisser en place
  /// ferait partir le mauvais téléversement.
  VerificationBrouillon changerDeType(TypeContenu nouveau) =>
      VerificationBrouillon(type: nouveau, contexte: contexte);
}
