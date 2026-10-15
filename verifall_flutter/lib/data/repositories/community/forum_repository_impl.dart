import '../../../core/errors/failure.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../domain/entities/thread_entity.dart';
import '../../../domain/repositories/forum_repository.dart';
import '../../datasources/local/community/community_cache_datasource.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/remote/community/forum_remote_datasource.dart';
import '../../models/api_enums.dart';
import '../../models/community/sujet_forum_model.dart';
import '../../models/json_reader.dart';

/// Le dépôt du forum : réseau, cache, et file d'écriture.
///
/// # Les trois politiques qu'il porte, et pourquoi elles sont ici
///
/// 1. **Lecture** : le réseau d'abord, le cache en repli — et le repli est
///    horodaté, pour que l'écran puisse poser le bandeau « Données du `<date>` ».
/// 2. **Vote et signalement** : mis en file quand le réseau manque. Le contrat
///    les nomme tous deux comme rejouables sans risque, « la contrainte
///    d'unicité étant en base ».
/// 3. **Publication et réponse** : **jamais** mises en file. Voir plus bas.
///
/// Les mettre au dépôt plutôt qu'aux écrans, c'est s'assurer qu'un futur écran
/// n'invente pas une quatrième politique.
class ForumRepositoryImpl implements ForumRepository {
  /// POURQUOI des parametres positionnels et non nommes : Dart interdit un
  /// parametre nomme prefixe d'un souligne, donc un champ prive assigne depuis
  /// un parametre nomme oblige a une liste d'initialisation que l'analyseur
  /// signale. Trois dependances dans un ordre stable — distant, cache, file —
  /// se lisent sans difficulte.
  const ForumRepositoryImpl(this._distant, this._cache, this._file);

  final ForumRemoteDatasource _distant;
  final CommunityCacheDatasource _cache;
  final PendingOpsQueue _file;

  @override
  Future<PageSujets> listerSujets({String? categorie, String? curseur}) async {
    final PageSujets page = await _distant.listerSujets(
      categorie: categorie,
      curseur: curseur,
    );
    if (curseur == null) {
      // Seule la PREMIERE page est mise en cache. Cacher toutes les pages d'une
      // liste par curseur ferait grossir la base indefiniment, et hors ligne
      // l'ecran doit montrer ce qu'il a vu en dernier — pas un historique.
      await _cache.ecrire(
        CleCache.pageSujets(categorie: categorie),
        _pageVersJson(page),
      );
    }
    return page;
  }

  @override
  Future<PageSujets?> sujetsEnCache({String? categorie}) async {
    final EntreeCache? entree = await _cache.lire(
      CleCache.pageSujets(categorie: categorie),
    );
    if (entree == null) {
      return null;
    }
    final JsonReader reader = JsonReader(entree.contenu);
    return PageSujets(
      sujets: <SujetForum>[
        for (final Map<String, Object?> item in reader.objectListOrEmpty(
          'sujets',
        ))
          SujetForumModel.fromJson(item),
      ],
      rubriques: <RubriqueForum>[
        for (final Map<String, Object?> item in reader.objectListOrEmpty(
          'rubriques',
        ))
          RubriqueForum(
            slug: JsonReader(item).string('slug'),
            libelle: JsonReader(item).string('libelle'),
          ),
      ],
      // Le curseur du cache n'est PAS repris : un curseur garde d'une session
      // precedente repond « 422 validation » (§3.4), et le contrat demande alors
      // « d'oublier son curseur et de recharger la liste depuis le debut ».
      recupereLe: entree.recupereLe,
    );
  }

  @override
  Future<SujetForumDetail> lireSujet(String slug) async {
    final SujetForumDetail detail = await _distant.lireSujet(slug);
    await _cache.ecrire(
      CleCache.sujet(slug),
      SujetForumModel.detailToCacheJson(detail),
    );
    return detail;
  }

  @override
  Future<SujetForumDetail?> sujetEnCache(String slug) async {
    final EntreeCache? entree = await _cache.lire(CleCache.sujet(slug));
    return entree == null
        ? null
        : SujetForumModel.detailFromJson(entree.contenu);
  }

  /// **Aucune mise en file**, et c'est une décision, pas un oubli.
  ///
  /// La publication d'un sujet ne figure pas dans la liste fermée des actes que
  /// le contrat autorise à mettre en file (§7.2) : elle n'a « ni clé
  /// d'idempotence exigée, ni contrainte d'unicité mentionnée ». La mettre en
  /// file exposerait au doublon — deux sujets identiques publiés par une réponse
  /// perdue.
  ///
  /// L'énumération `PendingOpKind` du socle, gelée, ne porte d'ailleurs aucune
  /// valeur pour cet acte : le socle a déjà tranché en creux.
  ///
  /// Sans réseau, l'échec remonte donc tel quel et l'écran dit ce qui manque —
  /// « ce qui ne fonctionne pas hors ligne, et il faut le dire » (§5.10).
  @override
  Future<SujetForumDetail> publierSujet({
    required String titre,
    required String corps,
    String? categorie,
    String? lienSignale,
  }) {
    return _distant.publierSujet(
      titre: titre,
      corps: corps,
      categorie: categorie,
      lienSignale: lienSignale,
    );
  }

  /// Le vote, optimiste côté écran, **réconcilié ici**.
  ///
  /// POURQUOI la mise en file est sûre : « le vote optimiste est sans danger :
  /// la réponse du serveur fait foi, l'unicité est en base » (§10.5). Deux
  /// rejeux ne créent pas deux votes, donc aucune clé d'idempotence n'est
  /// nécessaire — et `PendingOpKind.voterSujet` le déclare explicitement.
  @override
  Future<ResultatVote> voter(String slug, AvisCommunaute avis) async {
    try {
      return await _distant.voter(slug, avis);
    } on NetworkFailure {
      await _file.enqueue(
        kind: PendingOpKind.voterSujet,
        endpoint: ApiEndpoints.forumThreadVote(slug),
        payload: <String, Object?>{'avis': avis.wireValue},
      );
      // `votes: null` signifie « le serveur n'a rien recalcule » : l'ecran garde
      // la repartition qu'il affichait et signale le caractere provisoire.
      return ResultatVote(votes: null, monVote: avis, provisoire: true);
    }
  }

  /// **Aucune mise en file** — même raison que [publierSujet].
  @override
  Future<void> repondre(String slug, String corps) =>
      _distant.repondre(slug, corps);

  @override
  Future<IssueEcriture> signalerSujet({
    required String slug,
    required MotifSignalement motif,
    String? detail,
  }) async {
    try {
      await _distant.signaler(slug: slug, motif: motif, detail: detail);
      return IssueEcriture.envoyee;
    } on NetworkFailure {
      await _file.enqueue(
        kind: PendingOpKind.signaler,
        endpoint: ApiEndpoints.forumThreadReport(slug),
        payload: ForumRemoteDatasource.corpsDeSignalement(
          motif: motif,
          detail: detail,
        ),
      );
      return IssueEcriture.miseEnFile;
    }
  }

  Map<String, Object?> _pageVersJson(PageSujets page) {
    return <String, Object?>{
      'sujets': <Map<String, Object?>>[
        for (final SujetForum sujet in page.sujets)
          SujetForumModel.toCacheJson(sujet),
      ],
      'rubriques': <Map<String, Object?>>[
        for (final RubriqueForum rubrique in page.rubriques)
          <String, Object?>{'slug': rubrique.slug, 'libelle': rubrique.libelle},
      ],
    };
  }
}
