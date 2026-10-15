import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../data/models/api_envelope.dart';
import '../../../../data/repositories/home/dashboard_providers.dart';
import '../../../../data/repositories/verify/verification_providers.dart';
import '../../../../domain/entities/notification_citoyen.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../../domain/repositories/dashboard_repository.dart';
import '../../../../domain/repositories/notification_repository.dart';
import '../../../../domain/usecases/verify/drapeaux_de_fonctionnalite.dart';
import '../../../../core/state/riverpod_retry.dart';
import '../../../../core/session/solde_unites.dart';

/// L'agrégat de l'écran d'accueil.
///
/// POURQUOI `autoDispose` : « une lecture, rejetée en quittant l'onglet ». On
/// relit à chaque retour sur l'accueil, ce qui est exactement le comportement
/// attendu d'un écran de compteurs.
// Le type `AutoDisposeFutureProvider` n'est pas exporte par `riverpod` : on
// laisse l'inference faire plutôt que d'importer un chemin `src/` interne.
final dashboardProvider = FutureProvider.autoDispose<AccueilCitoyen>((
  Ref ref,
) async {
  final AccueilCitoyen accueil = await ref
      .read(dashboardRepositoryProvider)
      .lire();
  // Le solde vient de `compteurs.unites`, lu du RESEAU. Quand l'affichage vient
  // du cache, `unites` est absent — et la pastille affiche un tiret, jamais une
  // valeur perimee (contrat 7.1).
  ref.read(soldeUnitesProvider.notifier).relu(accueil.tableau.compteurs.unites);
  return accueil;
}, retry: sansReessaiAutomatique);

// `_sansReessai` vivait ici. Le reglage vit desormais au socle
// (`core/state/riverpod_retry.dart`), pose une fois sur le conteneur :
// M5 ecrivait la meme ligne, et trois parcours ne l'ecrivaient pas du tout.
// Voir l'arbitrage n° 132.

/// Les **trois** vérifications récentes de l'accueil.
///
/// `GET /me/verifications?limit=3` — pagination par curseur, `page` n'existe
/// pas. On ne réutilise pas le provider de l'écran 12 : celui-là porte les
/// filtres, et l'accueil doit voir la liste entière.
final verificationsRecentesProvider =
    FutureProvider.autoDispose<List<Verification>>((Ref ref) async {
      final CursorPage<Verification> page = await ref
          .read(verificationRepositoryProvider)
          .lister(limit: 3);
      return page.items;
    }, retry: sansReessaiAutomatique);

/// Le compteur de non-lues de la cloche.
///
/// **Il n'a aucune source en v1**, et c'est pourquoi il vaut toujours `null` :
/// aucun endpoint ne sert de notification. `null` ne veut pas dire « zéro » — il
/// veut dire « on ne mesure pas », et c'est ce qui fait **masquer** la cloche
/// plutôt que de l'afficher sans pastille.
///
/// > « Un indicateur qu'on ne mesure pas ne s'affiche pas. »
final Provider<int?> compteurNonLuesProvider = Provider<int?>(
  (Ref ref) => null,
);

/// La liste de notifications — lue **uniquement** si le drapeau est levé.
///
/// Le dépôt branché refuse par construction (`NotificationsNonServies`) : ce
/// provider n'est donc jamais consommé tant que [DrapeauxM2.notifications] est
/// faux, et l'écran 08 affiche à la place son état « non branché ».
final notificationsProvider =
    FutureProvider.autoDispose<List<NotificationCitoyen>>((Ref ref) {
      if (!DrapeauxM2.notifications) {
        return Future<List<NotificationCitoyen>>.value(
          const <NotificationCitoyen>[],
        );
      }
      final NotificationRepository depot = ref.read(
        notificationRepositoryProvider,
      );
      return depot.lister();
    }, retry: sansReessaiAutomatique);
