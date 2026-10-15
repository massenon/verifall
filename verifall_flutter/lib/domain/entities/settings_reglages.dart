import 'package:flutter/foundation.dart';

/// Un canal de notification, **tel que le serveur le déclare**.
///
/// # Pourquoi le client ne tient aucune liste de canaux
///
/// `GET /me/settings` rend les canaux avec leur clé **et leur libellé**. Un
/// canal inconnu est refusé en `422` par `PATCH /me/settings`, « pas ignoré en
/// silence » : le client ne peut donc pas inventer de clé de préférence. En
/// conséquence, la section « Notifications » se dessine à partir de la réponse,
/// et non d'une liste écrite en dur qui divergerait à la première évolution.
@immutable
class CanalNotification {
  const CanalNotification({
    required this.cle,
    required this.libelle,
    required this.active,
  });

  /// La clé technique (`verification_results`, `forum_activity`…).
  final String cle;

  /// Le libellé rédigé par le serveur.
  ///
  /// POURQUOI il n'y a pas de sous-titre : le simulateur en affiche un sous
  /// chaque interrupteur (« Quand une analyse est terminée »), mais
  /// `GET /me/settings` ne sert pas de description. La recopier côté client la
  /// ferait mentir le jour où le canal change de portée.
  final String libelle;

  final bool active;

  CanalNotification avecValeur(bool valeur) =>
      CanalNotification(cle: cle, libelle: libelle, active: valeur);
}

/// Les réglages du compte.
@immutable
class Reglages {
  const Reglages({
    required this.notifications,
    required this.newsletter,
    required this.luLe,
  });

  final List<CanalNotification> notifications;

  /// La lettre d'information — **un consentement, pas une préférence**
  /// (arbitrage n° 11). Chaque bascule ajoute une ligne horodatée plutôt que
  /// d'en modifier une : l'historique des accords et des retraits est exactement
  /// ce qu'il faut pouvoir produire en cas de contrôle. Visuellement,
  /// l'interrupteur ne change pas ; sémantiquement, il écrit ailleurs.
  final bool newsletter;

  final DateTime luLe;

  /// Applique **une valeur voulue** à un canal, en local, pour l'affichage
  /// optimiste. L'envoi, lui, porte la même valeur — jamais « inverse-le ».
  Reglages avecCanal(String cle, bool valeur) {
    return Reglages(
      notifications: <CanalNotification>[
        for (final CanalNotification canal in notifications)
          canal.cle == cle ? canal.avecValeur(valeur) : canal,
      ],
      newsletter: newsletter,
      luLe: luLe,
    );
  }

  Reglages avecNewsletter(bool valeur) =>
      Reglages(notifications: notifications, newsletter: valeur, luLe: luLe);
}

/// Les deux temps de `DELETE /me`, sur un **seul** point d'entrée.
///
/// > 1. mot de confirmation + mot de passe → `202`, un code part par courriel,
/// >    **rien n'est supprimé** ;
/// > 2. mot de confirmation + mot de passe + code → `200`, suppression
/// >    programmée.
///
/// « Mot de passe et code : le premier prouve qu'on connaît le compte, le second
/// qu'on tient la boîte. Un téléphone volé déverrouillé passe le premier, jamais
/// les deux. »
enum EtapeSuppression {
  codeEnvoye('code_envoye'),
  suppressionProgrammee('suppression_programmee'),
  inconnue('');

  const EtapeSuppression(this.wireValue);

  final String wireValue;

  static EtapeSuppression parse(String? raw) {
    for (final EtapeSuppression etape in EtapeSuppression.values) {
      if (etape.wireValue == raw && etape != EtapeSuppression.inconnue) {
        return etape;
      }
    }
    return EtapeSuppression.inconnue;
  }
}

/// Le résultat d'un des deux temps de la suppression.
@immutable
class SuppressionCompte {
  const SuppressionCompte({required this.etape, this.demandeeLe});

  final EtapeSuppression etape;

  /// Rendue au second temps seulement. L'effacement effectif a lieu trente
  /// jours plus tard, et **l'annulation ne passe pas par l'API** : elle se fait
  /// par le lien signé reçu par courriel, « seule voie de retour quand on ne
  /// peut plus se connecter ».
  final DateTime? demandeeLe;
}

/// Le mot exact attendu par `DELETE /me`.
///
/// POURQUOI il est ici et pas dans un écran : c'est une **valeur du contrat**,
/// pas un texte d'interface. Le simulateur demandait de taper son nom ; le
/// contrat attend ce mot-là (réserve A.12). Un écran qui comparerait au nom de
/// la personne enverrait un `confirmation` que le serveur refuserait en `422`.
const String motDeConfirmationSuppression = 'SUPPRIMER';
