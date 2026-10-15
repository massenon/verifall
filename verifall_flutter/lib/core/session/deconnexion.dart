import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/auth_controller.dart';

/// **Fermer la session**, en un seul point d'entrée pour toute l'application.
///
/// # Pourquoi il a rejoint le socle
///
/// Trois endroits déclenchent une déconnexion : l'écran Réglages et l'écran
/// d'état d'un compte en suppression (M5), et le **bouton rouge du tiroir**, qui
/// appartient au socle depuis qu'il a un hôte (arbitrage n° 137). Un composant du
/// socle ne peut pas importer un provider d'un parcours ; et si chacun refaisait
/// la purge, « deux purges écrites séparément en oublieraient chacune une
/// moitié, et sur un téléphone partagé la moitié oubliée est une fuite ».
///
/// # Ce que fait le défaut, et pourquoi il n'est pas neutre
///
/// Par défaut, il **ferme et purge localement** : stockage sécurisé, base Drift,
/// file d'écriture. C'est le geste qui protège, et il ne doit jamais manquer —
/// contrairement à `localDataPurgerProvider`, un défaut qui lèverait ici
/// laisserait quelqu'un enfermé dans une session qu'il veut quitter.
///
/// Ce que le défaut ne fait **pas** : révoquer le jeton côté serveur. C'est le
/// travail de `POST /auth/logout`, dont la logique appartient à M1 (§8.2). La
/// racine de composition surcharge donc ce provider avec `FermerSession`, qui
/// révoque **puis** appelle la même purge. Un test qui ne surcharge rien obtient
/// la fermeture locale, jamais une session laissée ouverte.
final Provider<Future<void> Function()> deconnexionProvider =
    Provider<Future<void> Function()>((Ref ref) {
      return () => ref.read(authControllerProvider.notifier).signOut();
    });
