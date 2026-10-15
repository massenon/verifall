import '../../data/models/api_enums.dart';
import '../entities/article_entity.dart';
import '../entities/opportunity_entity.dart';
import '../entities/thread_entity.dart' show IssueEcriture;

/// Le dépôt du Hub : brèves et opportunités.
///
/// **Trois rubriques, et pas quatre.** Les portails officiels sont retirés du
/// parcours citoyen (arbitrage n° 54) : « les données restent en base, l'écran
/// d'administration reste utile — c'est une mise en retrait décidée, pas un
/// abandon ». Aucune méthode ne les expose ici, et c'est ce qui empêche de les
/// réintroduire par inadvertance.
abstract interface class HubRepository {
  /// `GET /hub/articles`.
  Future<PageArticles> listerArticles({String? categorie, String? curseur});

  /// La première page de brèves du dernier chargement, ou `null`.
  ///
  /// Le cache du Hub vit **15 minutes** — « la durée la plus courte de tout le
  /// contrat » (§7.1) — parce que c'est le contenu le plus périssable de
  /// l'application.
  Future<PageArticles?> articlesEnCache({String? categorie});

  /// `GET /hub/articles/{slug}`.
  ///
  /// Une brève non publiée répond `404`, **même en connaissant le slug**.
  Future<ArticleHub> lireArticle(String slug);

  Future<ArticleHub?> articleEnCache(String slug);

  /// `GET /hub/opportunities`.
  ///
  /// [portee] part dans `?scope=` avec les valeurs **`benin`** et
  /// **`international`** — jamais `bj` ni `intl`, qui produiraient un `422`
  /// (§3.5). [type] part dans `?type=`, également en énumération fermée.
  ///
  /// Les deux filtres sont **serveur**. Filtrer localement ne filtrerait que la
  /// page déjà chargée, et « l'utilisateur verrait apparaître, à la deuxième
  /// page, des éléments qui ne correspondent pas à sa sélection » (§3.4).
  Future<PageOpportunites> listerOpportunites({
    PorteeOpportunite? portee,
    TypeOpportunite? type,
    String? curseur,
  });

  Future<PageOpportunites?> opportunitesEnCache({
    PorteeOpportunite? portee,
    TypeOpportunite? type,
  });

  /// `GET /hub/opportunities/{slug}`.
  ///
  /// Rend l'offre **et** `meta.avertissement`, qui accompagne toujours la fiche.
  Future<FicheOpportunite> lireOpportunite(String slug);

  Future<FicheOpportunite?> opportuniteEnCache(String slug);

  /// `POST /hub/opportunities/{slug}/report` — mêmes six motifs que le forum.
  Future<IssueEcriture> signalerOpportunite({
    required String slug,
    required MotifSignalement motif,
    String? detail,
  });
}
