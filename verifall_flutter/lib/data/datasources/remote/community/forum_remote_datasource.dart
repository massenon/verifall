import '../../../../core/network/api_client.dart';
import '../../../../core/network/api_endpoints.dart';
import '../../../../domain/entities/thread_entity.dart';
import '../../../models/api_enums.dart';
import '../../../models/api_envelope.dart';
import '../../../models/community/sujet_forum_model.dart';

/// Les appels du forum.
///
/// POURQUOI cette classe ne contient aucune décision : elle traduit un appel en
/// entités et rien d'autre. Le choix « cache d'abord, réseau ensuite », la mise
/// en file hors ligne et le refus d'une écriture sans réseau appartiennent au
/// dépôt, un cran au-dessus. Un source de données qui déciderait deviendrait le
/// seul endroit où lire la politique de synchronisation — c'est-à-dire un
/// endroit où personne ne pense à la lire.
class ForumRemoteDatasource {
  const ForumRemoteDatasource(this._client);

  final ApiClient _client;

  /// `GET /forum/threads?category=&limit=&cursor=`.
  ///
  /// POURQUOI [categorie] est repassé **à chaque page**, curseur compris : « le
  /// curseur n'encode qu'une position dans l'ordre de tri ; il ne mémorise ni
  /// `category`, ni `level`, ni `q` ». L'appel fautif — curseur seul — ne
  /// renvoie **aucune erreur**, il rend la page suivante de la liste non
  /// filtrée. C'est la catégorie de bogue qu'aucun test ne rattrape et qu'un
  /// utilisateur signale six mois plus tard (§3.4, arbitrage n° 82).
  ///
  /// `ApiClient.getPage` fusionne `filters` et `cursor` dans la même requête :
  /// c'est ce qui rend l'oubli structurellement impossible ici.
  Future<PageSujets> listerSujets({String? categorie, String? curseur}) async {
    final ApiEnvelope reponse = await _client.getPage(
      ApiEndpoints.forumThreads,
      cursor: curseur,
      filters: <String, Object?>{
        if (categorie != null && categorie.isNotEmpty) 'category': categorie,
      },
    );
    final CursorPage<SujetForum> page = reponse.toPage<SujetForum>(
      SujetForumModel.fromJson,
    );
    return PageSujets(
      sujets: page.items,
      rubriques: SujetForumModel.rubriquesFromMeta(reponse.meta),
      curseurSuivant: page.page.isLastPage ? null : page.page.nextCursor,
    );
  }

  /// `GET /forum/threads/{slug}`.
  Future<SujetForumDetail> lireSujet(String slug) async {
    final ApiEnvelope reponse = await _client.get(
      ApiEndpoints.forumThread(slug),
    );
    return SujetForumModel.detailFromJson(reponse.dataObject);
  }

  /// `POST /forum/threads`.
  ///
  /// POURQUOI le champ de rubrique s'appelle `category` et non `categorie` :
  /// arbitrage n° 81. « Un champ mal nommé serait ignoré **en silence** — le
  /// sujet partirait sans rubrique, l'appel répondrait `201`, et personne ne
  /// verrait rien. C'est le pire des échecs, puisqu'il produit un résultat
  /// plausible. »
  Future<SujetForumDetail> publierSujet({
    required String titre,
    required String corps,
    String? categorie,
    String? lienSignale,
  }) async {
    final ApiEnvelope reponse = await _client.post(
      ApiEndpoints.forumThreads,
      body: <String, Object?>{
        'titre': titre,
        'corps': corps,
        if (categorie != null && categorie.isNotEmpty) 'category': categorie,
        if (lienSignale != null && lienSignale.isNotEmpty)
          'lien_signale': lienSignale,
      },
    );
    return SujetForumModel.detailFromJson(reponse.dataObject);
  }

  /// `POST /forum/threads/{slug}/vote`.
  ///
  /// Rend `data.votes` et `data.mon_vote`, **recalculés par le serveur**. C'est
  /// la seconde moitié du vote optimiste : l'écran a déjà affiché son estimation,
  /// et il doit se ranger à celle-ci sans clignoter.
  Future<ResultatVote> voter(String slug, AvisCommunaute avis) async {
    final ApiEnvelope reponse = await _client.post(
      ApiEndpoints.forumThreadVote(slug),
      body: <String, Object?>{'avis': avis.wireValue},
    );
    return SujetForumModel.resultatVoteFromJson(reponse.dataObject);
  }

  /// `POST /forum/threads/{slug}/replies`.
  ///
  /// Un fil verrouillé répond `422 action_impossible`. Le contrat est net : « la
  /// modération a décidé ; contourner sa décision par l'API serait une faille. »
  /// L'échec remonte tel quel, avec le message du serveur.
  Future<void> repondre(String slug, String corps) async {
    await _client.post(
      ApiEndpoints.forumThreadReplies(slug),
      body: <String, Object?>{'corps': corps},
    );
  }

  /// `POST /forum/threads/{slug}/report`.
  Future<void> signaler({
    required String slug,
    required MotifSignalement motif,
    String? detail,
  }) async {
    await _client.post(
      ApiEndpoints.forumThreadReport(slug),
      body: corpsDeSignalement(motif: motif, detail: detail),
    );
  }

  /// Le corps d'un signalement, **identique pour le forum et pour le Hub**.
  ///
  /// POURQUOI il est construit en un seul endroit : les deux `report` du contrat
  /// attendent exactement les mêmes champs et la même liste fermée de motifs.
  /// Deux constructions séparées, c'est deux occasions de diverger.
  static Map<String, Object?> corpsDeSignalement({
    required MotifSignalement motif,
    String? detail,
  }) {
    return <String, Object?>{
      'motif': motif.wireValue,
      if (detail != null && detail.trim().isNotEmpty) 'detail': detail.trim(),
    };
  }
}
