import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../config/app_config.dart';
import '../config/config_providers.dart';
import '../logging/app_logger.dart';
import '../logging/logging_providers.dart';
import '../router/app_router.dart';
import 'api_client.dart';
import 'auth_controller.dart';
import 'token_store.dart';

/// La version du client, **lue du paquet installé**.
///
/// POURQUOI jamais écrite en dur : « une version figée dans une chaîne de
/// caractères finit toujours par mentir, et c'est la première chose qu'on
/// demande à quelqu'un qui signale un défaut » (07-LIVRAISON §7.3). Elle sert à
/// la fois à l'en-tête `X-Client-Version` et au pied de l'écran Réglages.
///
/// Surchargé au démarrage par `main.dart` : le lire de façon asynchrone dans le
/// constructeur du client rendrait le client asynchrone à son tour, et donc tous
/// les dépôts.
final Provider<String> clientVersionProvider = Provider<String>(
  (Ref ref) => throw StateError(
    'clientVersionProvider doit être surcharge au demarrage.',
  ),
);

/// Lecture de la version depuis le paquet, appelée une fois par `main.dart`.
Future<String> readClientVersion() async {
  final PackageInfo info = await PackageInfo.fromPlatform();
  return info.version;
}

/// **Le modèle réel du téléphone**, lu une fois au démarrage.
///
/// # À quoi il sert, et à quoi il ne sert pas
///
/// Il **nomme le jeton** dans la liste des sessions : « une liste d'appareils
/// lisible se révoque en connaissance de cause ; "jeton 47" non » (§10.1). C'est
/// de l'ergonomie de sécurité, pas de la télémétrie — rien d'autre n'en dépend,
/// et il n'est envoyé que sur les trois appels qui acceptent `appareil`.
///
/// # Pourquoi la lecture est ici et le point d'injection ailleurs
///
/// `device_info_plus` est **asynchrone**. Rendre `NomAppareil.valeur()`
/// asynchrone aurait contaminé la source de données d'authentification puis le
/// dépôt : c'est le même raisonnement que pour la version du client, lue ici et
/// injectée par surcharge. Le point d'injection de M1 attendait exactement cela
/// — « c'est le point d'injection qui accueillera `device_info_plus` le jour où
/// le socle le déclarera ». Arbitrage n° 141.
///
/// Rend `null` quand la plateforme ne dit rien d'utile. Le champ est facultatif
/// au contrat, et « envoyer *inconnu* remplirait la liste des sessions de lignes
/// identiques, ce qui est pire que de la laisser vide ». **Aucun nom de modèle
/// n'est fabriqué** : un « Samsung Galaxy » par défaut ferait révoquer le mauvais
/// appareil.
Future<String?> readDeviceName() async {
  try {
    final DeviceInfoPlugin plugin = DeviceInfoPlugin();
    if (Platform.isAndroid) {
      final AndroidDeviceInfo info = await plugin.androidInfo;
      return _propre('${info.manufacturer} ${info.model}');
    }
    if (Platform.isIOS) {
      final IosDeviceInfo info = await plugin.iosInfo;
      // `utsname.machine` rend `iPhone14,5` : le nom donne par la personne a son
      // telephone est plus reconnaissable dans une liste de sessions.
      return _propre(info.name);
    }
  } on Object {
    // Un nom d'appareil ne doit JAMAIS faire echouer une connexion.
    return null;
  }
  return null;
}

/// La borne du contrat : `appareil` est validé en `max:60`.
String? _propre(String brut) {
  final String valeur = brut.trim().replaceAll(RegExp(r'\s+'), ' ');
  if (valeur.isEmpty) {
    return null;
  }
  return valeur.length > 60 ? valeur.substring(0, 60).trim() : valeur;
}

/// Le client de l'API. **Un seul, construit une fois** (04-RESEAU §4.1).
final Provider<ApiClient> apiClientProvider = Provider<ApiClient>((Ref ref) {
  final AppConfig config = ref.watch(appConfigProvider);
  final AppLogger logger = ref.watch(appLoggerProvider);
  final TokenStore tokenStore = ref.watch(tokenStoreProvider);

  final ApiClient client = ApiClient.create(
    baseUrl: config.apiBaseUrl,
    tokenStore: tokenStore,
    logger: logger,
    clientVersion: ref.watch(clientVersionProvider),
    // Sur 401 : purger le jeton, purger la base locale, presenter la connexion.
    // Il n'y a AUCUN renouvellement a tenter — pas de jeton de rafraichissement
    // dans ce contrat (§1.4).
    onUnauthenticated: () =>
        ref.read(authControllerProvider.notifier).signOut(revoked: true),
    // 426 : l'ecran bloquant existe des la premiere version livree, parce que
    // le jour ou le plancher est releve on ne peut plus deployer de code sur
    // les telephones concernes (04-RESEAU 4.6).
    onClientObsolete: () =>
        ref.read(obsoleteClientProvider.notifier).markObsolete(),
    // Les deux 403 qui decrivent l'etat du COMPTE, pas celui de la requete. Ils
    // valent pour les trente ecrans a la fois : les traiter ici evite que
    // chacun ecrive son message, et que ceux qui ne le font pas affichent un
    // echec ordinaire (arbitrage n° 136).
    onAccountInactive: () =>
        ref.read(authControllerProvider.notifier).markAccountInactive(),
    onAccountBeingDeleted: () =>
        ref.read(authControllerProvider.notifier).markAccountBeingDeleted(),
  );

  ref.onDispose(client.close);
  return client;
});
