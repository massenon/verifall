import '../../../../core/network/api_client.dart';
import '../../../../core/network/api_endpoints.dart';
import '../../../../domain/entities/article_entity.dart';
import '../../../../domain/entities/opportunity_entity.dart';
import '../../../models/api_enums.dart';
import '../../../models/api_envelope.dart';
import '../../../models/community/article_hub_model.dart';
import '../../../models/community/opportunite_model.dart';
import 'forum_remote_datasource.dart';

/// Les appels du Hub : brèves et opportunités.
class HubRemoteDatasource {
  const HubRemoteDatasource(this._client);

  final ApiClient _client;

  /// `GET /hub/articles?category=&limit=&cursor=`.
  ///
  /// POURQUOI `category` est ici un **nom** de catégorie et non un slug : c'est
  /// la ligne du §3.5 pour ce seul endpoint. Et c'est une catégorie **ouverte** :
  /// une valeur inconnue rend une liste vide, pas un `422`. L'écran doit donc
  /// afficher un état vide honnête, jamais un message d'erreur (§3.7).
  Future<PageArticles> listerArticles({
    String? categorie,
    String? curseur,
  }) async {
    final ApiEnvelope reponse = await _client.getPage(
      ApiEndpoints.hubArticles,
      cursor: curseur,
      filters: <String, Object?>{
        if (categorie != null && categorie.isNotEmpty) 'category': categorie,
      },
    );
    final CursorPage<ArticleHub> page = reponse.toPage<ArticleHub>(
      ArticleHubModel.fromJson,
    );
    return PageArticles(
      articles: page.items,
      curseurSuivant: page.page.isLastPage ? null : page.page.nextCursor,
    );
  }

  /// `GET /hub/articles/{slug}`.
  ///
  /// Une brève non publiée répond `404`, **même en connaissant le slug**.
  Future<ArticleHub> lireArticle(String slug) async {
    final ApiEnvelope reponse = await _client.get(
      ApiEndpoints.hubArticle(slug),
    );
    return ArticleHubModel.fromJson(reponse.dataObject);
  }

  /// `GET /hub/opportunities?scope=&type=&limit=&cursor=`.
  ///
  /// # Les deux valeurs de `scope`, et le piège qu'elles ferment
  ///
  /// Le panneau du simulateur écrit `?scope=bj|intl`. **Ce n'est pas la valeur
  /// réelle** : ce sont `benin` et `international`, et `scope` est une
  /// énumération fermée — donc `bj` produit un `422`, pas une liste vide (§3.5,
  /// §3.7). Le passage par [PorteeOpportunite] rend l'erreur impossible à
  /// écrire.
  ///
  /// # Pourquoi le filtre n'est pas fait côté client
  ///
  /// « Filtrer localement ne filtre que la page déjà chargée ; à la deuxième
  /// page, l'utilisateur voit apparaître des éléments qui ne correspondent pas à
  /// sa sélection » (§3.4, arbitrage n° 82). Les opportunités sont l'une des
  /// trois listes explicitement nommées.
  Future<PageOpportunites> listerOpportunites({
    PorteeOpportunite? portee,
    TypeOpportunite? type,
    String? curseur,
  }) async {
    final ApiEnvelope reponse = await _client.getPage(
      ApiEndpoints.hubOpportunities,
      cursor: curseur,
      filters: <String, Object?>{
        if (portee != null) 'scope': portee.wireValue,
        if (type != null) 'type': type.wireValue,
      },
    );
    final CursorPage<Opportunite> page = reponse.toPage<Opportunite>(
      OpportuniteModel.fromJson,
    );
    return PageOpportunites(
      // L'ORDRE EST CELUI DU SERVEUR. Ouvertes d'abord, echeance la plus proche
      // en tete, cloturees ensuite : « il ne doit pas etre recalcule localement
      // a partir de dates, sans quoi le curseur et l'affichage divergeraient »
      // (03-ECRANS-05 §3.22).
      opportunites: page.items,
      curseurSuivant: page.page.isLastPage ? null : page.page.nextCursor,
    );
  }

  /// `GET /hub/opportunities/{slug}`.
  ///
  /// Rend l'offre **et** `meta.avertissement` — qui accompagne toujours la
  /// fiche, sans condition.
  Future<FicheOpportunite> lireOpportunite(String slug) async {
    final ApiEnvelope reponse = await _client.get(
      ApiEndpoints.hubOpportunity(slug),
    );
    return FicheOpportunite(
      opportunite: OpportuniteModel.fromJson(reponse.dataObject),
      avertissement: OpportuniteModel.avertissementFromMeta(reponse.meta),
    );
  }

  /// `POST /hub/opportunities/{slug}/report`.
  ///
  /// Mêmes six motifs que le forum, même corps : voir
  /// [ForumRemoteDatasource.corpsDeSignalement].
  Future<void> signaler({
    required String slug,
    required MotifSignalement motif,
    String? detail,
  }) async {
    await _client.post(
      ApiEndpoints.hubOpportunityReport(slug),
      body: ForumRemoteDatasource.corpsDeSignalement(
        motif: motif,
        detail: detail,
      ),
    );
  }
}
