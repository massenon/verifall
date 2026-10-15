import 'package:flutter/foundation.dart';

/// Une notification citoyenne — **écrite, pas branchée**.
///
/// # Pourquoi ce type existe alors qu'aucun endpoint ne le sert
///
/// Les trois appels du simulateur — `GET /me/notifications`,
/// `POST /me/notifications/read-all`, `POST /me/notifications/{id}/read` — **ne
/// sont servis par aucun endpoint** (§11 du contrat, arbitrage n° 73). La raison
/// n'est pas un oubli :
///
/// > « Il n'existe **aucun magasin de notifications** sur cette plateforme : pas
/// > de table `notifications`, aucune classe `Notification`, rien qui en émette.
/// > La cloche du gabarit citoyen est **décorative**. »
///
/// L'écran est donc écrit derrière un drapeau de fonctionnalité, prêt à être
/// levé le jour où les endpoints existeront — ce seront des **ajouts**,
/// compatibles v1. C'est ce qui distingue « pas branché » de « pas fait ».
///
/// **Et la cloche est masquée**, pas grisée : une cloche sans pastille invite à
/// cliquer, une cloche qui ouvre un écran vide se lit comme un défaut.
@immutable
class NotificationCitoyen {
  const NotificationCitoyen({
    required this.id,
    required this.categorie,
    required this.texte,
    required this.horodatageRelatif,
    required this.lue,
    this.destination,
  });

  final String id;

  /// `verif`, `lms`… La catégorie choisit l'icône. Elle viendra du serveur.
  final String categorie;

  final String texte;

  /// L'horodatage relatif sera **rendu par le serveur** : « il y a 2 h » calculé
  /// côté client sur une horloge de téléphone mal réglée dit n'importe quoi.
  final String horodatageRelatif;

  final bool lue;

  /// Le chemin interne à ouvrir. Chaque notification en porte un : une
  /// notification qui ne mène nulle part est une interruption gratuite.
  final String? destination;

  NotificationCitoyen marquerLue() => NotificationCitoyen(
    id: id,
    categorie: categorie,
    texte: texte,
    horodatageRelatif: horodatageRelatif,
    lue: true,
    destination: destination,
  );
}
