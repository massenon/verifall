import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// L'état du réseau, tel que le socle le voit.
///
/// POURQUOI trois valeurs et pas deux : « un téléphone connecté à un portail
/// captif se déclare en ligne et ne joint rien » (05-HORS-LIGNE §5.12).
/// [probablementEnLigne] dit exactement ce qu'on sait : l'interface est active,
/// la joignabilité réelle n'est pas prouvée.
enum NetworkStatus {
  /// Aucune interface active. Le rejeu de la file est inutile.
  horsLigne,

  /// Une interface est active. On **tente**, et on traite l'échec.
  probablementEnLigne,

  /// Un appel a abouti récemment : la joignabilité est prouvée.
  joignable,
}

/// Le flux de connectivité, injecté pour être remplaçable en test.
final Provider<Stream<List<ConnectivityResult>>> connectivityStreamProvider =
    Provider<Stream<List<ConnectivityResult>>>(
      (Ref ref) => Connectivity().onConnectivityChanged,
    );

/// L'état du réseau, provider global consommé par le bandeau et par la file.
///
/// POURQUOI il ne doit **jamais** bloquer l'interface : « une application qui
/// refuse d'essayer parce qu'elle se croit hors ligne est inutilisable sur un
/// réseau instable, c'est-à-dire dans le cas courant » (§5.12). Cet état est un
/// indice pour décider **quand rejouer**, pas une autorisation d'appeler.
final StreamProvider<NetworkStatus> networkStatusProvider =
    StreamProvider<NetworkStatus>((Ref ref) {
      return ref
          .watch(connectivityStreamProvider)
          .map(
            (List<ConnectivityResult> results) =>
                results.every(
                  (ConnectivityResult r) => r == ConnectivityResult.none,
                )
                ? NetworkStatus.horsLigne
                : NetworkStatus.probablementEnLigne,
          );
    });
