import 'package:flutter/foundation.dart';

import '../../../data/models/televersement.dart';
import '../../entities/verification_brouillon.dart';

/// Le résultat d'une validation locale : rien, ou un message affichable.
@immutable
class ErreurDeSaisie {
  const ErreurDeSaisie(this.message);

  final String message;
}

/// Les validations que l'écran applique **avant** d'envoyer quoi que ce soit.
///
/// # Pourquoi elles existent alors que le serveur valide aussi
///
/// Deux raisons, et aucune n'est la commodité :
///
/// - **le poids d'un fichier.** « Envoyer 120 Mo pour se faire refuser après
///   trois minutes est le pire scénario possible sur un réseau mobile »
///   (04-RESEAU §4.10). Les plafonds viennent du socle (`TypeTeleversement`),
///   qui les tient du contrat : les redéclarer ici en ferait une seconde source
///   de vérité ;
/// - **le sens.** « Sélectionnez un fichier à analyser » est plus utile qu'un
///   422 traduit.
///
/// # Ce qu'elles ne font pas
///
/// Elles n'exigent **jamais plus que le serveur**. Le simulateur demande 20
/// caractères sur une contestation là où le contrat en demande 10 : on aligne
/// sur 10, parce qu'« un formulaire ne doit pas exiger plus que ce qui sera
/// accepté ».
abstract final class ValiderLaSaisie {
  /// Valide un brouillon complet. Rend `null` quand tout va bien.
  static ErreurDeSaisie? brouillon(VerificationBrouillon brouillon) {
    final TypeTeleversement? typeFichier = brouillon.type.televersement;

    if (typeFichier == null) {
      final String saisie = brouillon.saisie.trim();
      if (brouillon.type.wireValue == 'url') {
        return _adresse(saisie);
      }
      if (saisie.length < VerificationBrouillon.longueurAffirmationMin) {
        return const ErreurDeSaisie(
          'Le texte doit contenir au moins 10 caractères.',
        );
      }
      return null;
    }

    final FichierChoisi? fichier = brouillon.fichier;
    if (fichier == null) {
      return const ErreurDeSaisie('Selectionnez un fichier a analyser.');
    }
    return fichierChoisi(typeFichier, fichier);
  }

  /// Format et poids, avec le plafond **en clair** dans le message.
  ///
  /// POURQUOI le plafond est cité : un refus qui ne dit pas la limite oblige à
  /// réessayer à l'aveugle, chaque essai coûtant du forfait.
  static ErreurDeSaisie? fichierChoisi(
    TypeTeleversement type,
    FichierChoisi fichier,
  ) {
    final int point = fichier.nom.lastIndexOf('.');
    final String extension = point < 0
        ? ''
        : fichier.nom.substring(point + 1).toLowerCase();

    if (!type.acceptsExtension(extension)) {
      return ErreurDeSaisie(
        'Ce format n est pas accepte. Formats attendus : '
        '${type.extensions.join(', ')}.',
      );
    }
    if (!type.acceptsSize(fichier.octets)) {
      final int plafondMo = type.maxBytes ~/ (1024 * 1024);
      return ErreurDeSaisie(
        'Ce fichier est trop volumineux : $plafondMo Mo au maximum.',
      );
    }
    return null;
  }

  /// La contestation : **10 caractères minimum**, comme le contrat.
  static ErreurDeSaisie? contestation(String detail) {
    final int longueur = detail.trim().length;
    if (longueur < detailMinimum) {
      final int manquants = detailMinimum - longueur;
      return ErreurDeSaisie(
        'Détaillez un peu plus : $manquants caractère(s) manquant(s).',
      );
    }
    return null;
  }

  /// Le minimum exigé par `POST /verifications/{uuid}/contest`.
  static const int detailMinimum = 10;

  /// Le maximum accepté par le serveur. Le champ **borne la saisie** plutôt que
  /// de refuser après coup.
  static const int detailMaximum = 1000;

  static ErreurDeSaisie? _adresse(String saisie) {
    final Uri? uri = Uri.tryParse(saisie);
    final bool valide =
        uri != null &&
        uri.hasScheme &&
        (uri.scheme == 'http' || uri.scheme == 'https') &&
        uri.host.isNotEmpty &&
        uri.host.contains('.');
    return valide
        ? null
        : const ErreurDeSaisie('Entrez une URL valide (https://...).');
  }
}
