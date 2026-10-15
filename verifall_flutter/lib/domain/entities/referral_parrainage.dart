import 'package:flutter/foundation.dart';

/// Où en est un parrainage.
///
/// **Trois états, et pas de quatrième.** Il n'existe pas d'état « invitation
/// envoyée » : un partage WhatsApp ne se constate pas depuis un serveur, et une
/// ligne de parrainage naît à l'**inscription** du filleul (arbitrage n° 46).
/// Le statut « Invité » du simulateur n'a donc aucune source ; il disparaît.
enum StatutFilleul {
  inscrit('registered'),
  eligible('qualified'),
  recompense('rewarded'),

  /// Une valeur que cette version du client ne connaît pas. Le contrat autorise
  /// l'ajout d'une valeur sans préavis (§6.2) ; planter dessus figerait l'API.
  inconnu('');

  const StatutFilleul(this.wireValue);

  final String wireValue;

  static StatutFilleul parse(String? raw) {
    for (final StatutFilleul statut in StatutFilleul.values) {
      if (statut.wireValue == raw && statut != StatutFilleul.inconnu) {
        return statut;
      }
    }
    return StatutFilleul.inconnu;
  }

  /// Le filleul a-t-il fait l'action éligible ?
  bool get estActif =>
      this == StatutFilleul.eligible || this == StatutFilleul.recompense;
}

/// Un filleul.
///
/// # Ce que l'on montre, et pourquoi c'est si peu
///
/// Le serveur ne rend que le **prénom** : « l'adresse e-mail d'un filleul n'est
/// jamais exposée — il n'a pas consenti à ce qu'elle soit rendue à qui l'a
/// invité » (arbitrage n° 74). Le nom de famille n'est pas servi non plus.
///
/// L'écran affiche donc le prénom seul, et l'avatar en tire son initiale. C'est
/// **moins** que ce que la maquette montrait (nom complet), et c'est voulu :
/// afficher le nom complet livrerait au parrain des données que son filleul ne
/// lui a pas confiées.
@immutable
class Filleul {
  const Filleul({
    required this.uuid,
    required this.statut,
    required this.statutLibelle,
    this.prenom,
    this.inscritLe,
    this.recompenseLe,
  });

  final String uuid;
  final StatutFilleul statut;

  /// Rédigé par le serveur, affiché tel quel.
  final String statutLibelle;

  final String? prenom;
  final DateTime? inscritLe;
  final DateTime? recompenseLe;

  /// L'initiale de l'avatar. Vide si le prénom n'est pas rendu — on n'invente
  /// pas une lettre.
  String get initiale => (prenom == null || prenom!.isEmpty)
      ? ''
      : prenom!.substring(0, 1).toUpperCase();
}

/// Les quatre indicateurs de la page.
///
/// # Le premier compteur ne dit pas ce que la maquette prétendait
///
/// Le prototype affichait « Invitations : 12 ». Le contrat tranche : *« un
/// partage WhatsApp ne se constate pas depuis un serveur : compter les ENVOIS
/// serait inventer un chiffre. »* Ce qui est compté, ce sont les **ouvertures
/// du lien** — [visites] — et le libellé de l'écran le dit : « Invitations
/// ouvertes », jamais « envoyées ».
///
/// [points] vient de l'API. Ce n'est **pas** le nombre de filleuls actifs
/// multiplié par un barème : une multiplication locale afficherait un gain qui
/// n'a pas été versé.
@immutable
class IndicateursParrainage {
  const IndicateursParrainage({
    required this.visites,
    required this.inscrits,
    required this.actifs,
    required this.points,
  });

  /// Les ouvertures du lien d'invitation, incrémentées sur la page publique.
  final int visites;

  final int inscrits;
  final int actifs;

  /// Les unités **réellement créditées** par les parrainages.
  final int points;
}

/// La page de parrainage.
@immutable
class Parrainage {
  const Parrainage({
    required this.code,
    required this.lien,
    required this.indicateurs,
    required this.filleuls,
    required this.luLe,
  });

  /// Le code public. Alphabet sans O/0, I/1, S/5, tiré par un générateur
  /// cryptographique côté serveur : il est fait pour être dicté au téléphone.
  final String code;

  /// Le lien à partager, **rendu par l'API**. C'est lui, et pas le code seul,
  /// qui alimente le QR : composer une adresse côté client serait inventer une
  /// URL de production.
  final String lien;

  final IndicateursParrainage indicateurs;
  final List<Filleul> filleuls;

  /// L'instant de la lecture, pour dater un affichage venu du cache. Le code et
  /// le lien, eux, doivent fonctionner hors ligne : c'est leur raison d'être.
  final DateTime luLe;
}

/// Ce que rend `POST /me/referrals/invite`.
///
/// **Il ne compte rien et n'envoie rien.** Le partage est un geste du
/// téléphone ; l'appel ne fait que rendre le code et le lien.
@immutable
class Invitation {
  const Invitation({required this.code, required this.lien, this.message});

  final String code;
  final String lien;

  /// Le message de partage suggéré par le serveur. Affiché et partagé tel quel
  /// quand il arrive.
  final String? message;
}
