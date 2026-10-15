import 'package:flutter_riverpod/flutter_riverpod.dart';
// Les types de FAMILLE ne sont pas exportes par l'entree principale de
// Riverpod 3 ; ils vivent dans `misc.dart`. On les importe plutot que de
// laisser l'inference decider : un type ecrit est un type qu'on peut relire.
import 'package:flutter_riverpod/misc.dart';

import '../../../../domain/repositories/certificate_repository.dart';
import 'lms_providers.dart';

/// Le certificat — `certificateProvider(certId)` du simulateur.
///
/// L'identifiant est le **code public**, pas un uuid.
///
/// Le cache est ici une exigence : « c'est ce qu'on montre à un recruteur,
/// souvent hors ligne ». Le PDF, lui, non — et il n'a de toute façon aucun
/// endpoint.
final FutureProviderFamily<CertificatLu, String> certificateProvider =
    FutureProvider.autoDispose.family<CertificatLu, String>(
      (Ref ref, String code) =>
          ref.watch(certificateRepositoryProvider).certificat(code),
    );

/// Mes certificats — la liste, **non paginée**.
final FutureProvider<CertificatsLus> mesCertificatsProvider =
    FutureProvider.autoDispose<CertificatsLus>(
      (Ref ref) => ref.watch(certificateRepositoryProvider).mesCertificats(),
    );
