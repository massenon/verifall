import '../../../core/errors/failure.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../domain/entities/article_entity.dart';
import '../../../domain/entities/opportunity_entity.dart';
import '../../../domain/entities/thread_entity.dart' show IssueEcriture;
import '../../../domain/repositories/hub_repository.dart';
import '../../datasources/local/community/community_cache_datasource.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/remote/community/forum_remote_datasource.dart';
import '../../datasources/remote/community/hub_remote_datasource.dart';
import '../../models/api_enums.dart';
import '../../models/community/article_hub_model.dart';
import '../../models/community/opportunite_model.dart';
import '../../models/json_reader.dart';

/// Le dépôt du Hub.
///
/// # Le cache de quinze minutes, et pourquoi il ne périme pas l'affichage
///
/// Le contrat donne au Hub la durée de vie la plus courte de toute
/// l'application (§7.1) : quinze minutes. Passé ce délai, le cache n'est pas
/// effacé — il est **marqué**. L'écran continue de l'afficher et pose le bandeau
/// d'horodatage, parce que « hors ligne, une liste périmée horodatée vaut mieux
/// qu'un écran vide » ; ce qui serait fautif, c'est de la présenter comme
/// fraîche. Une échéance « J-2 » vieille de trois jours induit en erreur, et le
/// client n'a pas le droit de la recalculer.
class HubRepositoryImpl implements HubRepository {
  /// Meme ordre que le depot du forum : distant, cache, file.
  const HubRepositoryImpl(this._distant, this._cache, this._file);

  final HubRemoteDatasource _distant;
  final CommunityCacheDatasource _cache;
  final PendingOpsQueue _file;

  @override
  Future<PageArticles> listerArticles({
    String? categorie,
    String? curseur,
  }) async {
    final PageArticles page = await _distant.listerArticles(
      categorie: categorie,
      curseur: curseur,
    );
    if (curseur == null) {
      await _cache.ecrire(
        CleCache.pageArticles(categorie: categorie),
        <String, Object?>{
          'articles': <Map<String, Object?>>[
            for (final ArticleHub article in page.articles)
              ArticleHubModel.toCacheJson(article),
          ],
        },
      );
    }
    return page;
  }

  @override
  Future<PageArticles?> articlesEnCache({String? categorie}) async {
    final EntreeCache? entree = await _cache.lire(
      CleCache.pageArticles(categorie: categorie),
    );
    if (entree == null) {
      return null;
    }
    return PageArticles(
      articles: <ArticleHub>[
        for (final Map<String, Object?> item in JsonReader(
          entree.contenu,
        ).objectListOrEmpty('articles'))
          ArticleHubModel.fromJson(item),
      ],
      recupereLe: entree.recupereLe,
    );
  }

  @override
  Future<ArticleHub> lireArticle(String slug) async {
    final ArticleHub article = await _distant.lireArticle(slug);
    await _cache.ecrire(
      CleCache.article(slug),
      ArticleHubModel.toCacheJson(article),
    );
    return article;
  }

  @override
  Future<ArticleHub?> articleEnCache(String slug) async {
    final EntreeCache? entree = await _cache.lire(CleCache.article(slug));
    return entree == null ? null : ArticleHubModel.fromJson(entree.contenu);
  }

  @override
  Future<PageOpportunites> listerOpportunites({
    PorteeOpportunite? portee,
    TypeOpportunite? type,
    String? curseur,
  }) async {
    final PageOpportunites page = await _distant.listerOpportunites(
      portee: portee,
      type: type,
      curseur: curseur,
    );
    if (curseur == null) {
      await _cache.ecrire(
        CleCache.pageOpportunites(
          portee: portee?.wireValue,
          type: type?.wireValue,
        ),
        <String, Object?>{
          'opportunites': <Map<String, Object?>>[
            for (final Opportunite offre in page.opportunites)
              OpportuniteModel.toCacheJson(offre),
          ],
        },
      );
    }
    return page;
  }

  @override
  Future<PageOpportunites?> opportunitesEnCache({
    PorteeOpportunite? portee,
    TypeOpportunite? type,
  }) async {
    final EntreeCache? entree = await _cache.lire(
      CleCache.pageOpportunites(
        portee: portee?.wireValue,
        type: type?.wireValue,
      ),
    );
    if (entree == null) {
      return null;
    }
    return PageOpportunites(
      // L'ordre est celui du cache, qui est celui du serveur au moment du
      // chargement. On ne retrie pas : le tri appartient au serveur.
      opportunites: <Opportunite>[
        for (final Map<String, Object?> item in JsonReader(
          entree.contenu,
        ).objectListOrEmpty('opportunites'))
          OpportuniteModel.fromJson(item),
      ],
      recupereLe: entree.recupereLe,
    );
  }

  @override
  Future<FicheOpportunite> lireOpportunite(String slug) async {
    final FicheOpportunite fiche = await _distant.lireOpportunite(slug);
    await _cache.ecrire(CleCache.opportunite(slug), <String, Object?>{
      'offre': OpportuniteModel.toCacheJson(fiche.opportunite),
      // L'avertissement est mis en cache AVEC la fiche : il fait partie de ce
      // qu'on doit pouvoir reafficher hors ligne, et le reconstituer cote client
      // reviendrait a l'ecrire nous-memes.
      'avertissement': fiche.avertissement,
    });
    return fiche;
  }

  @override
  Future<FicheOpportunite?> opportuniteEnCache(String slug) async {
    final EntreeCache? entree = await _cache.lire(CleCache.opportunite(slug));
    if (entree == null) {
      return null;
    }
    final JsonReader reader = JsonReader(entree.contenu);
    final Map<String, Object?>? offre = reader.objectOrNull('offre');
    if (offre == null) {
      return null;
    }
    return FicheOpportunite(
      opportunite: OpportuniteModel.fromJson(offre),
      avertissement: reader.stringOrNull('avertissement'),
    );
  }

  @override
  Future<IssueEcriture> signalerOpportunite({
    required String slug,
    required MotifSignalement motif,
    String? detail,
  }) async {
    try {
      await _distant.signaler(slug: slug, motif: motif, detail: detail);
      return IssueEcriture.envoyee;
    } on NetworkFailure {
      // « Signaler deux fois ne cree qu'une ligne » : l'unicite est en base,
      // l'appel est donc rejouable sans cle d'idempotence.
      await _file.enqueue(
        kind: PendingOpKind.signaler,
        endpoint: ApiEndpoints.hubOpportunityReport(slug),
        payload: ForumRemoteDatasource.corpsDeSignalement(
          motif: motif,
          detail: detail,
        ),
      );
      return IssueEcriture.miseEnFile;
    }
  }
}
