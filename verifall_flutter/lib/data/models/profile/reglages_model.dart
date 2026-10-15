import '../../../domain/entities/settings_reglages.dart';
import '../json_reader.dart';

/// La lecture de `GET /me/settings` et de `DELETE /me`.
abstract final class ReglagesModel {
  /// `data.notifications` est un **objet indexé par clé de canal**, chaque
  /// entrée portant `libelle` et `active`.
  ///
  /// POURQUOI on préserve l'ordre du serveur : c'est l'ordre de l'énumération
  /// côté back, et c'est celui de la section « Notifications » du prototype. Un
  /// tri alphabétique côté client réordonnerait les interrupteurs à chaque
  /// évolution du libellé.
  static Reglages depuisJson(
    Map<String, Object?> json, {
    required DateTime luLe,
  }) {
    final JsonReader lecteur = JsonReader(json);
    final Map<String, Object?> canaux =
        lecteur.objectOrNull('notifications') ?? const <String, Object?>{};

    final List<CanalNotification> notifications = <CanalNotification>[];
    canaux.forEach((String cle, Object? valeur) {
      if (valeur is! Map) {
        return;
      }
      final JsonReader canal = JsonReader(
        valeur.map(
          (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
        ),
      );
      notifications.add(
        CanalNotification(
          cle: cle,
          // Le libelle vient du serveur. Le client ne tient aucune table de
          // correspondance : un canal ajoute cote back apparait tout seul, et
          // un canal retire disparait tout seul.
          libelle: canal.string('libelle', fallback: cle),
          active: canal.boolValue('active'),
        ),
      );
    });

    return Reglages(
      notifications: notifications,
      newsletter: lecteur.boolValue('newsletter'),
      luLe: luLe,
    );
  }

  static SuppressionCompte suppressionDepuisJson(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return SuppressionCompte(
      etape: EtapeSuppression.parse(lecteur.stringOrNull('etape')),
      demandeeLe: lecteur.dateTimeOrNull('suppression_demandee_le'),
    );
  }

  static Map<String, Object?> versJson(Reglages reglages) => <String, Object?>{
    'notifications': <String, Object?>{
      for (final CanalNotification canal in reglages.notifications)
        canal.cle: <String, Object?>{
          'libelle': canal.libelle,
          'active': canal.active,
        },
    },
    'newsletter': reglages.newsletter,
  };
}
