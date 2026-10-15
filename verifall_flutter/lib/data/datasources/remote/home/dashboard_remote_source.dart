import '../../../../core/network/api_client.dart';
import '../../../../core/network/api_endpoints.dart';
import '../../../../domain/entities/home_tableau_de_bord.dart';
import '../../../models/api_envelope.dart';

/// La source réseau de l'écran d'accueil.
class DashboardRemoteSource {
  const DashboardRemoteSource(this._client);

  final ApiClient _client;

  /// `GET /me/dashboard` — **l'agrégat**.
  Future<TableauDeBord> tableauDeBord() async {
    final ApiEnvelope enveloppe = await _client.get(ApiEndpoints.dashboard);
    return TableauDeBord.fromJson(
      enveloppe.dataObject,
      luLe: DateTime.now().toUtc(),
    );
  }

  /// `GET /me/courses/resume`.
  ///
  /// **`data: null` n'est pas une erreur**, c'est un état vide : il n'y a rien à
  /// reprendre. Le booléen dit « lu », l'objet dit « il y a quelque chose ».
  Future<({bool lue, RepriseDeCours? reprise})> reprise() async {
    final ApiEnvelope enveloppe = await _client.get(ApiEndpoints.coursesResume);
    final Map<String, Object?> donnee = enveloppe.dataObject;
    if (donnee.isEmpty) {
      return (lue: true, reprise: null);
    }
    return (lue: true, reprise: RepriseDeCours.fromJson(donnee));
  }
}
