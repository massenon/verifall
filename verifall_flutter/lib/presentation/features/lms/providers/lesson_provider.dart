import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
// Les types de FAMILLE ne sont pas exportes par l'entree principale de
// Riverpod 3 ; ils vivent dans `misc.dart`. On les importe plutot que de
// laisser l'inference decider : un type ecrit est un type qu'on peut relire.
import 'package:flutter_riverpod/misc.dart';

import '../../../../core/errors/failure.dart';
import '../../../../domain/repositories/course_repository.dart';
import '../../../../domain/usecases/lms/compteur_de_lecture.dart';
import 'course_provider.dart';
import 'lms_providers.dart';

/// L'identité d'une leçon : son cours et son uuid.
///
/// POURQUOI un couple et pas l'uuid seul : `GET /courses/{slug}/lessons/{uuid}`
/// porte les deux, et le slug sert aussi à ranger la leçon dans le bon cours.
@immutable
class CleDeLecon {
  const CleDeLecon(this.coursSlug, this.leconUuid);

  final String coursSlug;
  final String leconUuid;

  @override
  bool operator ==(Object other) =>
      other is CleDeLecon &&
      other.coursSlug == coursSlug &&
      other.leconUuid == leconUuid;

  @override
  int get hashCode => Object.hash(coursSlug, leconUuid);
}

/// La leçon — `lessonProvider` du simulateur.
///
/// **Attention** : le premier appel réseau **démarre le chronomètre serveur**.
/// C'est voulu ici — la personne ouvre bien la leçon — et c'est ce qui rend le
/// téléchargement d'un cours entier délicat (réserve C.3, tranchée dans
/// `TelechargerCours`).
final FutureProviderFamily<LeconLue, CleDeLecon> lessonProvider = FutureProvider
    .autoDispose
    .family<LeconLue, CleDeLecon>(
      (Ref ref, CleDeLecon cle) => ref
          .watch(courseRepositoryProvider)
          .lecon(cle.coursSlug, cle.leconUuid),
    );

/// Le compteur de durée active — `lessonTimerProvider`.
///
/// Il est créé **par leçon**, et libéré avec l'écran.
final ProviderFamily<CompteurDeLecture, ({CleDeLecon cle, int requises})>
lessonTimerProvider = Provider.autoDispose
    .family<CompteurDeLecture, ({CleDeLecon cle, int requises})>((
      Ref ref,
      ({CleDeLecon cle, int requises}) arg,
    ) {
      final CompteurDeLecture compteur = CompteurDeLecture(
        depot: ref.watch(courseRepositoryProvider),
        leconUuid: arg.cle.leconUuid,
        // `meta.secondes_requises` — du serveur. Ne pas coder 45.
        secondesRequises: arg.requises,
      );
      ref.onDispose(compteur.liberer);
      return compteur;
    });

/// Ce que la validation d'une leçon a produit.
@immutable
class IssueDeValidation {
  const IssueDeValidation({
    required this.confirmee,
    required this.enFile,
    this.messageDuServeur,
  });

  /// Le serveur a accepté. **C'est le seul cas** où l'écran affiche « Leçon
  /// terminée ».
  final bool confirmee;

  /// L'acte est en file : l'écran dit « Validation en attente d'envoi », et
  /// **ne marque pas** la leçon comme faite. « La progression serait fausse au
  /// prochain chargement. »
  final bool enFile;

  /// Le message du serveur sur un `422 action_impossible` — il dit combien de
  /// secondes sont nécessaires. **Affiché tel quel**, jamais reformulé : « le
  /// client n'a pas le contexte serveur ».
  final String? messageDuServeur;
}

/// La validation d'une leçon.
class ValidationDeLeconController
    extends Notifier<AsyncValue<IssueDeValidation?>> {
  @override
  AsyncValue<IssueDeValidation?> build() =>
      const AsyncValue<IssueDeValidation?>.data(null);

  Future<IssueDeValidation?> valider({
    required CleDeLecon cle,
    required int secondes,
  }) async {
    if (state.isLoading) {
      return null;
    }
    state = const AsyncValue<IssueDeValidation?>.loading();
    try {
      final bool confirmee = await ref
          .read(courseRepositoryProvider)
          .terminerLecon(cle.coursSlug, cle.leconUuid, secondes);
      final IssueDeValidation issue = IssueDeValidation(
        confirmee: confirmee,
        enFile: !confirmee,
      );
      // Garde-fou : provider `autoDispose`, ecran peut-etre deja quitte.
      if (!ref.mounted) {
        return issue;
      }
      state = AsyncValue<IssueDeValidation?>.data(issue);
      if (confirmee) {
        // Le deverrouillage de la lecon suivante est TRANCHE PAR LE SERVEUR :
        // on relit la fiche plutot que d'ouvrir la suivante nous-memes.
        ref.invalidate(courseProvider(cle.coursSlug));
        ref.invalidate(lessonProvider(cle));
      }
      return issue;
    } on ImpossibleActionFailure catch (echec) {
      // `422 action_impossible` : le message du serveur dit combien de secondes
      // manquent. On l'affiche tel quel — « jamais un echec muet ».
      final IssueDeValidation issue = IssueDeValidation(
        confirmee: false,
        enFile: false,
        messageDuServeur: echec.serverMessage,
      );
      if (ref.mounted) {
        state = AsyncValue<IssueDeValidation?>.data(issue);
      }
      return issue;
    } on Failure catch (echec, pile) {
      if (ref.mounted) {
        state = AsyncValue<IssueDeValidation?>.error(echec, pile);
      }
      return IssueDeValidation(
        confirmee: false,
        enFile: false,
        messageDuServeur: echec.serverMessage,
      );
    }
  }
}

final NotifierProvider<
  ValidationDeLeconController,
  AsyncValue<IssueDeValidation?>
>
validationDeLeconProvider =
    NotifierProvider.autoDispose<
      ValidationDeLeconController,
      AsyncValue<IssueDeValidation?>
    >(ValidationDeLeconController.new);
