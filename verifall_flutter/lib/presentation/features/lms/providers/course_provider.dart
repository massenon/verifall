import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
// Les types de FAMILLE ne sont pas exportes par l'entree principale de
// Riverpod 3 ; ils vivent dans `misc.dart`. On les importe plutot que de
// laisser l'inference decider : un type ecrit est un type qu'on peut relire.
import 'package:flutter_riverpod/misc.dart';

import '../../../../domain/entities/certificate_summary.dart';
import '../../../../domain/entities/course_enrollment.dart';
import '../../../../domain/repositories/certificate_repository.dart';
import '../../../../domain/repositories/course_repository.dart';
import 'lms_providers.dart';

/// La fiche d'un cours — `courseProvider(slug)` du simulateur.
///
/// Elle passe par le dépôt, donc **par le cache d'abord** : hors ligne, la fiche
/// s'ouvre avec sa date de dernière lecture, et les leçons déjà téléchargées
/// restent ouvrables.
final FutureProviderFamily<FicheLue, String> courseProvider = FutureProvider
    .autoDispose
    .family<FicheLue, String>(
      (Ref ref, String slug) => ref.watch(courseRepositoryProvider).fiche(slug),
    );

/// Mon avancement — `progressProvider(slug)`.
///
/// **Il exige le réseau** : l'avancement est « recalculé depuis les faits » par
/// le serveur (§7.4), et « deux formules donneraient deux pourcentages ». Hors
/// ligne, la fiche porte déjà `meta.inscription`, qui suffit à l'affichage.
final FutureProviderFamily<CourseProgress, String> progressProvider =
    FutureProvider.autoDispose.family<CourseProgress, String>(
      (Ref ref, String slug) =>
          ref.watch(courseRepositoryProvider).avancement(slug),
    );

/// Le certificat d'un cours, s'il existe.
///
/// POURQUOI on passe par la liste plutôt que par un champ de la fiche : le
/// contrat ne pose aucun code de certificat sur `GET /courses/{slug}`. La liste
/// `GET /me/certificates` porte `cours_slug`, et c'est la seule façon **servie**
/// de relier un cours terminé à son certificat. Rendre `null` quand il n'y en a
/// pas est un état, pas une erreur.
final FutureProviderFamily<CertificateSummary?, String>
certificatDuCoursProvider = FutureProvider.autoDispose
    .family<CertificateSummary?, String>((Ref ref, String slug) async {
      final CertificatsLus lus = await ref
          .watch(certificateRepositoryProvider)
          .mesCertificats();
      for (final CertificateSummary c in lus.certificats) {
        if (c.coursSlug == slug) {
          return c;
        }
      }
      return null;
    });

/// Le résultat d'une inscription, tel que l'écran doit le dire.
@immutable
class IssueDInscription {
  const IssueDInscription({required this.inscription, required this.enFile});

  /// L'inscription confirmée par le serveur, ou `null`.
  final CourseEnrollment? inscription;

  /// Vrai quand l'acte est **en file** : le débit n'est pas encore effectué, et
  /// « le solde affiché ne bouge pas avant la réponse du serveur ».
  final bool enFile;
}

/// L'action « s'inscrire ».
///
/// POURQUOI un `Notifier` et pas un simple appel dans l'écran : « jamais de
/// logique dans un composant » vaut ici comme côté Laravel, et l'état
/// « inscription en cours » doit survivre à une reconstruction du widget —
/// sinon un double toucher part deux fois.
class InscriptionController extends Notifier<AsyncValue<IssueDInscription?>> {
  @override
  AsyncValue<IssueDInscription?> build() =>
      const AsyncValue<IssueDInscription?>.data(null);

  Future<IssueDInscription?> sInscrire(String slug) async {
    if (state.isLoading) {
      // Le double toucher est du CONFORT : la vraie protection est la
      // contrainte « une inscription par personne et par cours » en base, et
      // l'idempotence du service. Mais l'inconfort d'un double appel est reel.
      return null;
    }
    state = const AsyncValue<IssueDInscription?>.loading();
    try {
      final CourseEnrollment? inscription = await ref
          .read(courseRepositoryProvider)
          .sInscrire(slug);
      final IssueDInscription issue = IssueDInscription(
        inscription: inscription,
        enFile: inscription == null,
      );
      // Garde-fou : ce provider est `autoDispose`, et l'ecran peut avoir ete
      // quitte pendant l'aller-retour reseau.
      if (!ref.mounted) {
        return issue;
      }
      state = AsyncValue<IssueDInscription?>.data(issue);
      // La fiche est relue : c'est le serveur qui dit desormais `ouverte` sur
      // la premiere lecon, et l'ecran doit refleter, pas deviner.
      ref.invalidate(courseProvider(slug));
      return issue;
    } on Object catch (erreur, pile) {
      if (ref.mounted) {
        state = AsyncValue<IssueDInscription?>.error(erreur, pile);
      }
      return null;
    }
  }
}

final NotifierProvider<InscriptionController, AsyncValue<IssueDInscription?>>
inscriptionProvider =
    NotifierProvider.autoDispose<
      InscriptionController,
      AsyncValue<IssueDInscription?>
    >(InscriptionController.new);
