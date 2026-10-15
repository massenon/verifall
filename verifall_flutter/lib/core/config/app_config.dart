import 'app_environment.dart';

/// La configuration de l'application, lue **une seule fois** au démarrage.
///
/// POURQUOI une classe et pas des `String.fromEnvironment` disséminés : le reste
/// du code lit cette classe, jamais l'environnement (§1.7). Un `fromEnvironment`
/// au fond d'un écran est une valeur en dur qui ne dit pas son nom, et qui
/// devient introuvable le jour où l'adresse de l'API change.
///
/// **Aucune valeur n'est écrite en dur ici** : tout arrive par
/// `--dart-define-from-file`, un fichier par environnement, **non versionné**
/// (voir `config/README.md`). Les seules valeurs par défaut présentes sont des
/// valeurs *neutres* — chaîne vide ou faux — jamais une adresse réelle.
///
/// Ce que la configuration porte : l'adresse de base de l'API, celle du service
/// temps réel, le canal de télémétrie, le drapeau d'environnement.
/// Ce qu'elle ne porte pas : les coûts en unités, les prix des packs, les seuils
/// de quiz. **Ces valeurs viennent du serveur**, et les figer côté client
/// reviendrait à accepter un prix venu du téléphone.
class AppConfig {
  const AppConfig({
    required this.environment,
    required this.apiBaseUrl,
    required this.webBaseUrl,
    required this.realtimeHost,
    required this.realtimeKey,
    required this.realtimeCluster,
    required this.realtimeUseTls,
    required this.telemetryDsn,
    required this.devSessionEnabled,
    required this.devSessionToken,
    required this.devSessionUserUuid,
    required this.devSessionUserPrenom,
    required this.devSessionUserEmail,
    required this.devSessionUserUnites,
  });

  /// Construit la configuration depuis les `--dart-define`.
  ///
  /// POURQUOI un constructeur nommé et pas un singleton : en test on veut une
  /// configuration fabriquée à la main, sans toucher à l'environnement de
  /// compilation.
  factory AppConfig.fromEnvironment() {
    final AppEnvironment environment = AppEnvironment.parse(
      const String.fromEnvironment('VF_ENV'),
    );

    // La session de recette est demandée par la configuration ET refusée en
    // production par construction — voir [devSessionEnabled].
    const bool requestedDevSession = bool.fromEnvironment('VF_DEV_SESSION');

    return AppConfig(
      environment: environment,
      apiBaseUrl: const String.fromEnvironment('VF_API_BASE_URL'),
      webBaseUrl: const String.fromEnvironment('VF_WEB_BASE_URL'),
      realtimeHost: const String.fromEnvironment('VF_REVERB_HOST'),
      realtimeKey: const String.fromEnvironment('VF_REVERB_KEY'),
      realtimeCluster: const String.fromEnvironment('VF_REVERB_CLUSTER'),
      realtimeUseTls: const bool.fromEnvironment(
        'VF_REVERB_TLS',
        defaultValue: true,
      ),
      telemetryDsn: const String.fromEnvironment('VF_TELEMETRY_DSN'),
      devSessionEnabled:
          requestedDevSession && environment.allowsDeveloperTools,
      devSessionToken: const String.fromEnvironment('VF_DEV_TOKEN'),
      devSessionUserUuid: const String.fromEnvironment('VF_DEV_USER_UUID'),
      devSessionUserPrenom: const String.fromEnvironment('VF_DEV_USER_PRENOM'),
      devSessionUserEmail: const String.fromEnvironment('VF_DEV_USER_EMAIL'),
      devSessionUserUnites: const int.fromEnvironment('VF_DEV_USER_UNITES'),
    );
  }

  final AppEnvironment environment;

  /// Racine de l'API, **sans** le suffixe de version : `/api/v1` est ajouté par
  /// le client réseau, parce que la version majeure appartient au contrat et pas
  /// à la configuration (API_SPECIFICATION §6.1).
  final String apiBaseUrl;

  /// Racine du **site public**, sans barre finale : `https://…`.
  ///
  /// # Pourquoi elle existe, et pourquoi elle est vide par défaut
  ///
  /// Deux parcours l'ont demandée. M3 affiche l'adresse de vérification publique
  /// d'un certificat **sans pouvoir l'ouvrir** — « construire une URL en devinant
  /// l'hôte public reviendrait à écrire une adresse en dur » (arbitrage n° 104).
  /// M5 a **retiré du menu** la ligne « Politique de confidentialité » plutôt que
  /// de la laisser inerte, et deux autres mentions renvoient au site sans être
  /// cliquables (arbitrage n° 116).
  ///
  /// Vide, tout se comporte comme avant : l'adresse s'affiche, la ligne reste
  /// absente. Renseignée, les trois deviennent des liens. C'est la même règle que
  /// pour l'API — **aucune adresse réelle n'est écrite ici**, elle arrive par
  /// `--dart-define-from-file`. Arbitrage n° 138.
  final String webBaseUrl;

  final String realtimeHost;
  final String realtimeKey;
  final String realtimeCluster;
  final bool realtimeUseTls;

  /// Canal de télémétrie de plantage. Vide = télémétrie éteinte.
  final String telemetryDsn;

  /// La surcharge de session de développement est-elle active ?
  ///
  /// POURQUOI elle existe : M2 à M5 ont besoin d'un compte connecté ; si chacun
  /// attend que M1 livre ses six écrans, il n'y a plus de parallélisme du tout
  /// (§8.0). Elle injecte un jeton de recette et un profil sans passer par les
  /// écrans d'authentification.
  ///
  /// POURQUOI elle est refusée en production **par construction** : c'est le même
  /// raisonnement que l'adaptateur simulé du back, qui « refuse de tourner en
  /// production » (arbitrage n° 19). Ce champ ne peut pas valoir `true` quand
  /// [environment] vaut `production` — le `&&` du constructeur l'interdit, et
  /// [assertNoDeveloperSessionInProduction] le vérifie une seconde fois au
  /// démarrage.
  final bool devSessionEnabled;

  /// Jeton de recette. Il vient d'un fichier **non versionné** : recopier un
  /// jeton dans le code reviendrait à committer un secret (§5 des règles du
  /// chantier).
  final String devSessionToken;

  final String devSessionUserUuid;
  final String devSessionUserPrenom;
  final String devSessionUserEmail;
  final int devSessionUserUnites;

  /// Vrai si la configuration est exploitable pour joindre le serveur.
  ///
  /// POURQUOI c'est un booléen et pas une exception au démarrage : on veut que
  /// l'application se lance et affiche une erreur lisible, plutôt que de mourir
  /// sur un écran noir avant même d'avoir peint une image.
  bool get isComplete => apiBaseUrl.isNotEmpty;

  /// Vrai quand un lien vers le site public peut être composé.
  ///
  /// POURQUOI l'écran doit le tester plutôt que d'ouvrir une adresse vide : un
  /// bouton qui ouvre le navigateur sur rien est pire que pas de bouton.
  bool get hasWebSite => webBaseUrl.isNotEmpty;

  /// Vrai si la session de recette est réellement utilisable : activée **et**
  /// pourvue d'un jeton.
  bool get hasUsableDevSession =>
      devSessionEnabled && devSessionToken.isNotEmpty;

  /// Le garde-fou de dernier recours, appelé au démarrage.
  ///
  /// POURQUOI un second contrôle alors que le constructeur l'interdit déjà : une
  /// configuration peut aussi être construite à la main (tests, prototypes). Un
  /// seul point de vérité vaut mieux qu'un seul point de vérification.
  void assertNoDeveloperSessionInProduction() {
    if (environment.isProduction && devSessionEnabled) {
      throw StateError(
        'La session de developpement est refusee en production. '
        'Ce n est pas un reglage : c est une regle du socle.',
      );
    }

    /*
     | UNE APPLICATION DE PRODUCTION SANS ADRESSE D'API NE SERT A RIEN.
     |
     | `--dart-define-from-file` est facile a oublier : la construction
     | REUSSIT sans lui, produit un paquet d'apparence normale, et
     | `apiBaseUrl` vaut la chaine vide. L'application s'installe, s'ouvre, et
     | chaque appel echoue — sans que rien n'explique pourquoi.
     |
     | C'est la meme famille de defaut que celle qui traverse tout ce projet :
     | une chaine complete qui ne mene nulle part, et qui ne dit rien. On la
     | ferme ici, au demarrage, ou elle est encore lisible.
     |
     | La verification ne porte que sur la PRODUCTION : un banc d'essai ou un
     | prototype construit une configuration a la main, et n'a pas a fournir
     | une adresse.
     */
    if (environment.isProduction && apiBaseUrl.trim().isEmpty) {
      throw StateError(
        'Aucune adresse d API en production. '
        'Avez-vous oublie --dart-define-from-file=config/prod.json ?',
      );
    }

    /*
     | Et elle doit etre en HTTPS. Un jeton porteur qui voyage en clair est
     | lisible par n'importe quel intermediaire du reseau — sur un reseau
     | mobile partage, ce n'est pas une hypothese d'ecole.
     */
    if (environment.isProduction && !apiBaseUrl.startsWith('https://')) {
      throw StateError(
        'L adresse d API de production doit etre en HTTPS : $apiBaseUrl',
      );
    }
  }

  AppConfig copyWith({
    AppEnvironment? environment,
    String? apiBaseUrl,
    String? webBaseUrl,
    String? realtimeHost,
    String? realtimeKey,
    String? realtimeCluster,
    bool? realtimeUseTls,
    String? telemetryDsn,
    bool? devSessionEnabled,
    String? devSessionToken,
    String? devSessionUserUuid,
    String? devSessionUserPrenom,
    String? devSessionUserEmail,
    int? devSessionUserUnites,
  }) {
    final AppEnvironment nextEnvironment = environment ?? this.environment;
    final bool nextDevSession = devSessionEnabled ?? this.devSessionEnabled;
    return AppConfig(
      environment: nextEnvironment,
      apiBaseUrl: apiBaseUrl ?? this.apiBaseUrl,
      webBaseUrl: webBaseUrl ?? this.webBaseUrl,
      realtimeHost: realtimeHost ?? this.realtimeHost,
      realtimeKey: realtimeKey ?? this.realtimeKey,
      realtimeCluster: realtimeCluster ?? this.realtimeCluster,
      realtimeUseTls: realtimeUseTls ?? this.realtimeUseTls,
      telemetryDsn: telemetryDsn ?? this.telemetryDsn,
      // Le clamp est répété ici : `copyWith` est le chemin le plus court pour
      // fabriquer, par inadvertance, une configuration de production ouverte.
      devSessionEnabled: nextDevSession && nextEnvironment.allowsDeveloperTools,
      devSessionToken: devSessionToken ?? this.devSessionToken,
      devSessionUserUuid: devSessionUserUuid ?? this.devSessionUserUuid,
      devSessionUserPrenom: devSessionUserPrenom ?? this.devSessionUserPrenom,
      devSessionUserEmail: devSessionUserEmail ?? this.devSessionUserEmail,
      devSessionUserUnites: devSessionUserUnites ?? this.devSessionUserUnites,
    );
  }
}
