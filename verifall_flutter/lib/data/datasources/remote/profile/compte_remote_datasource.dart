import '../../../../core/network/api_client.dart';
import '../../../../core/network/api_endpoints.dart';
import '../../../../domain/entities/gamification_recompenses.dart';
import '../../../../domain/entities/profile_moi.dart';
import '../../../../domain/entities/referral_parrainage.dart';
import '../../../../domain/entities/settings_reglages.dart';
import '../../../../domain/entities/stats_statistiques.dart';
import '../../../../domain/entities/wallet_portefeuille.dart';
import '../../../models/api_envelope.dart';
import '../../../models/profile/moi_model.dart';
import '../../../models/profile/parrainage_model.dart';
import '../../../models/profile/portefeuille_model.dart';
import '../../../models/profile/recompenses_model.dart';
import '../../../models/profile/reglages_model.dart';
import '../../../models/profile/statistiques_model.dart';

/// Les quatorze appels du parcours « Compte », **et pas un de plus**.
///
/// # Pourquoi une seule source distante pour six écrans
///
/// Les quatorze endpoints partagent le même préfixe `/me`, la même enveloppe et
/// la même règle de fraîcheur. Les répartir en cinq fichiers aurait multiplié
/// les points où un chemin s'écrit à la main — et c'est précisément ainsi
/// qu'apparaît un appel vers un endpoint qui n'existe pas.
///
/// # Ce qui n'y est pas
///
/// Aucun appel de **double authentification**, de **changement de mot de passe
/// authentifié** ni de **changement d'adresse e-mail** : l'API v1 ne les sert
/// pas (réserve C.8). Aucun appel de **PDF de certificat** : il n'existe pas non
/// plus (§11 du contrat). Les écrire « en attendant » produirait un client qui
/// appelle du vide.
class CompteRemoteDataSource {
  CompteRemoteDataSource({required this.client, DateTime Function()? horloge})
    : horloge = horloge ?? DateTime.now;

  // Les dependances sont injectees et PUBLIQUES, comme la file d'ecriture du
  // socle (`PendingOpsQueue`) : un test doit pouvoir relire ce qu'on lui a
  // donne, et un champ prive nomme differemment de son parametre nomme n'est
  // pas exprimable en Dart sans dupliquer l'ecriture.
  final ApiClient client;

  /// L'horloge est injectée pour que les horodatages de fraîcheur soient
  /// vérifiables en test sans attendre une seconde réelle.
  final DateTime Function() horloge;

  // --- Profil ---------------------------------------------------------------

  /// `GET /me`.
  ///
  /// **Reste servi pendant un délai de suppression** — c'est l'une des deux
  /// seules exceptions du contrat (§1.7), et c'est ce qui permet d'afficher
  /// « votre compte sera effacé le … » au lieu d'un mur de 403.
  Future<Moi> moi() async {
    final ApiEnvelope reponse = await client.get(ApiEndpoints.me);
    return MoiModel.depuisJson(reponse.dataObject);
  }

  /// `PATCH /me` — **les champs absents gardent leur valeur**.
  Future<Moi> mettreAJour(ModificationProfil modification) async {
    final ApiEnvelope reponse = await client.patch(
      ApiEndpoints.me,
      body: modification.versCorps(),
    );
    return MoiModel.depuisJson(reponse.dataObject);
  }

  // --- Statistiques ---------------------------------------------------------

  /// `GET /me/stats?period=` — la période voyage **par son code de contrat**,
  /// jamais par un nombre de jours libre.
  Future<Statistiques> statistiques(PeriodeStats periode) async {
    final ApiEnvelope reponse = await client.get(
      ApiEndpoints.stats,
      query: <String, Object?>{'period': periode.wireValue},
    );
    return StatistiquesModel.depuisJson(
      reponse.dataObject,
      calculeLe: horloge(),
    );
  }

  // --- Portefeuille ---------------------------------------------------------

  /// `GET /me/wallet`.
  Future<Portefeuille> portefeuille() async {
    final ApiEnvelope reponse = await client.get(ApiEndpoints.wallet);
    return PortefeuilleModel.depuisJson(reponse.dataObject, luLe: horloge());
  }

  /// `POST /me/wallet/topup`.
  ///
  /// **Le corps ne porte que ces deux champs.** Il n'y a volontairement aucun
  /// paramètre de montant, de quantité ni d'opérateur dans cette signature :
  /// ce qui n'existe pas dans le code ne peut pas être envoyé par erreur.
  Future<DemandeRecharge> recharger({
    required String codePack,
    required String numero,
  }) async {
    final ApiEnvelope reponse = await client.post(
      ApiEndpoints.walletTopUp,
      body: <String, Object?>{'pack': codePack, 'numero': numero},
    );
    return PortefeuilleModel.demandeDepuisJson(reponse.dataObject);
  }

  /// `GET /me/wallet/transactions` — par curseur.
  Future<PageDeMouvements> mouvements({String? curseur}) async {
    final ApiEnvelope reponse = await client.getPage(
      ApiEndpoints.walletTransactions,
      cursor: curseur,
    );
    final CursorPage<MouvementUnite> page = reponse.toPage<MouvementUnite>(
      PortefeuilleModel.mouvementDepuisJson,
    );
    return PageDeMouvements(
      mouvements: page.items,
      // Le curseur est OPAQUE : renvoye tel quel, jamais lu, jamais fabrique.
      curseurSuivant: page.page.nextCursor,
      encoreDesPages: page.page.hasMore,
    );
  }

  // --- Recompenses ----------------------------------------------------------

  /// `GET /me/gamification` — non paginé.
  Future<Recompenses> recompenses() async {
    final ApiEnvelope reponse = await client.get(ApiEndpoints.gamification);
    return RecompensesModel.depuisJson(reponse.dataObject);
  }

  /// `GET /me/certificates` — non paginé.
  Future<List<Certificat>> certificats() async {
    final ApiEnvelope reponse = await client.get(ApiEndpoints.certificates);
    return <Certificat>[
      for (final Map<String, Object?> item in reponse.dataList)
        RecompensesModel.certificatDepuisJson(item),
    ];
  }

  // --- Parrainage -----------------------------------------------------------

  /// `GET /me/referrals`.
  Future<Parrainage> parrainage() async {
    final ApiEnvelope reponse = await client.get(ApiEndpoints.referrals);
    return ParrainageModel.depuisJson(reponse.dataObject, luLe: horloge());
  }

  /// `POST /me/referrals/invite` — rend le code et le lien.
  Future<Invitation> inviter() async {
    final ApiEnvelope reponse = await client.post(ApiEndpoints.referralsInvite);
    return ParrainageModel.invitationDepuisJson(reponse.dataObject);
  }

  // --- Reglages, export, suppression ---------------------------------------

  /// `GET /me/settings`.
  Future<Reglages> reglages() async {
    final ApiEnvelope reponse = await client.get(ApiEndpoints.settings);
    return ReglagesModel.depuisJson(reponse.dataObject, luLe: horloge());
  }

  /// `PATCH /me/settings` — le corps est **construit par l'appelant**, avec la
  /// valeur voulue. Cette méthode ne sait pas inverser un réglage, et c'est
  /// exactement ce qu'on veut d'elle.
  Future<Reglages> enregistrerLesReglages(Map<String, Object?> corps) async {
    final ApiEnvelope reponse = await client.patch(
      ApiEndpoints.settings,
      body: corps,
    );
    return ReglagesModel.depuisJson(reponse.dataObject, luLe: horloge());
  }

  /// `POST /me/export` — `202`. Rend le message du serveur, affiché tel quel.
  Future<String> exporter() async {
    final ApiEnvelope reponse = await client.post(ApiEndpoints.export);
    return reponse.dataObject['message'] as String? ?? '';
  }

  /// `DELETE /me` — un seul point d'entrée, deux temps.
  ///
  /// Sans [code], le serveur répond `202` et envoie un code par courriel :
  /// **rien n'est supprimé**. Avec [code], il répond `200` et programme la
  /// suppression.
  Future<SuppressionCompte> supprimer({
    required String confirmation,
    required String motDePasse,
    String? code,
  }) async {
    final ApiEnvelope reponse = await client.delete(
      ApiEndpoints.me,
      body: <String, Object?>{
        'confirmation': confirmation,
        'mot_de_passe': motDePasse,
        if (code != null && code.isNotEmpty) 'code': code,
      },
    );
    return ReglagesModel.suppressionDepuisJson(reponse.dataObject);
  }
}
