import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../domain/entities/referral_parrainage.dart';
import 'profile_dependencies.dart';
import '../../../../core/state/riverpod_retry.dart';

/// `referralProvider` — le nom vient du simulateur.
///
/// # Pourquoi l'écran n'appelle pas `POST /me/referrals/invite` à l'ouverture
///
/// `GET /me/referrals` rend **déjà** le code et le lien. L'appel `invite` rend
/// les mêmes valeurs et « ne compte rien » ; l'émettre à chaque ouverture de
/// l'écran ne changerait rien au serveur, mais ferait un aller-retour de plus
/// sur un écran qu'on ouvre justement pour montrer un QR — parfois sans réseau.
///
/// Il est donc appelé **au geste de partage**, quand on veut le message rédigé
/// par le serveur en même temps que le lien.
final AsyncNotifierProvider<ReferralNotifier, Parrainage> referralProvider =
    AsyncNotifierProvider<ReferralNotifier, Parrainage>(
      ReferralNotifier.new,
      isAutoDispose: true,
      // Voir [sansReessaiAutomatique] : la reprise est visible, jamais
      // silencieuse.
      retry: sansReessaiAutomatique,
    );

class ReferralNotifier extends AsyncNotifier<Parrainage> {
  @override
  Future<Parrainage> build() =>
      ref.read(referralRepositoryProvider).parrainage();

  Future<void> rafraichir() async {
    state = await AsyncValue.guard(
      () =>
          ref.read(referralRepositoryProvider).parrainage(forcerLeReseau: true),
    );
  }

  /// Le lien et le message à partager.
  Future<Invitation> preparerLInvitation() =>
      ref.read(referralRepositoryProvider).inviter();
}
