/// Les deux drapeaux de fonctionnalité du parcours M2.
///
/// # Pourquoi des drapeaux plutôt que du code absent
///
/// Cinq appels déclarés par le simulateur ne sont servis par **aucun** endpoint
/// (contrat §11, arbitrage n° 73). Deux d'entre eux tombent dans ce parcours :
/// les notifications, et le retour d'utilité d'un rapport.
///
/// Le choix documenté est net : *« l'écran est à écrire, mais pas à brancher »*.
/// Le jour où les endpoints arrivent, on **lève le drapeau** — on ne réécrit pas
/// l'écran. C'est ce qui distingue « pas branché » de « pas fait », et ces
/// endpoints seront des **ajouts** compatibles v1.
///
/// # Pourquoi `bool.fromEnvironment` et pas un champ d'`AppConfig`
///
/// `AppConfig` appartient au socle, et le socle est en lecture seule pour un
/// parcours : « un besoin de modification du socle est une demande, pas une
/// modification » (§8.1, règle 2). `--dart-define` **est** la configuration —
/// c'est la même source que `config/dev.json` — sans rouvrir un fichier gelé.
/// Le jour où le serveur sert ces routes, la bonne façon de lever le drapeau est
/// de le remonter dans `AppConfig` : c'est une demande consignée au compte rendu.
abstract final class DrapeauxM2 {
  /// L'écran 08 et la cloche de la barre supérieure.
  ///
  /// **Faux par défaut, et la cloche est alors MASQUÉE — pas grisée.** « Une
  /// cloche sans pastille invite à cliquer ; une cloche qui ouvre un écran vide
  /// se lit comme un défaut. Un indicateur qu'on ne mesure pas ne s'affiche
  /// pas. »
  static const bool notifications = bool.fromEnvironment(
    'VF_FEATURE_NOTIFICATIONS',
  );

  /// Le bloc « Ce rapport vous a-t-il été utile ? ».
  ///
  /// **Faux par défaut, et le bloc est alors masqué.** Pas de boutons inertes,
  /// pas de « merci » sans destinataire : « un remerciement pour un avis qui
  /// n'est stocké nulle part est un mensonge poli, et c'est pire qu'une question
  /// non posée. »
  static const bool retourDUtilite = bool.fromEnvironment(
    'VF_FEATURE_RETOUR_UTILITE',
  );
}
