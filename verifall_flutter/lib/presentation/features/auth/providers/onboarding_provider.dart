import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/network/auth_controller.dart';
import '../../../../core/network/token_store.dart';

/// Le carrousel a-t-il déjà été vu ?
///
/// # Un seul booléen, et il vit en stockage sécurisé
///
/// Le drapeau `onboarding_seen` est porté par `TokenStore` du socle, aux côtés
/// du jeton. Ce n'est pas un secret — mais le socle n'a alors **qu'un seul
/// magasin à purger** à la déconnexion, et un magasin oublié est une fuite.
///
/// # Pourquoi un `FutureProvider` et pas une valeur lue au démarrage
///
/// `main.dart` appartient au socle et ne précharge pas ce drapeau : le lire
/// avant la première image allongerait le démarrage de toute l'application pour
/// un écran qui ne s'affiche qu'une fois dans la vie de l'installation.
///
/// L'écran d'accueil traite donc les trois états explicitement, et l'état
/// « en cours de lecture » ne montre **pas** d'indicateur d'attente : il montre
/// le fond forêt, c'est-à-dire exactement ce que la première diapositive
/// montrera. Rien ne clignote.
final FutureProvider<bool> onboardingSeenProvider = FutureProvider<bool>(
  (Ref ref) => ref.read(tokenStoreProvider).hasSeenOnboarding(),
);

/// Marque le carrousel comme vu.
///
/// POURQUOI l'écriture est un provider et non un appel direct dans la page : le
/// test « le carrousel ne s'affiche qu'une fois » a besoin de vérifier que
/// l'écriture a bien eu lieu, sans monter le stockage sécurisé du système.
///
/// POURQUOI [onboardingSeenProvider] n'est **pas** invalidé au passage : le
/// drapeau ne sert qu'**au démarrage**, pour décider si l'écran s'ouvre. Le
/// relire pendant que l'écran est encore monté le ferait redécider de sa propre
/// destination — et cette décision-là, « aller à la connexion », écraserait la
/// navigation que la personne vient de demander en touchant « Créer un compte
/// citoyen ». Un drapeau lu deux fois dans la même session est un drapeau qui se
/// contredit.
final Provider<MarquerOnboardingVu> marquerOnboardingVuProvider =
    Provider<MarquerOnboardingVu>(
      (Ref ref) => MarquerOnboardingVu(ref.read(tokenStoreProvider)),
    );

class MarquerOnboardingVu {
  const MarquerOnboardingVu(this._magasin);

  final TokenStore _magasin;

  Future<void> call() => _magasin.markOnboardingSeen();
}
