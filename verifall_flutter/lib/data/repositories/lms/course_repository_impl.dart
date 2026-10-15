import '../../../core/errors/failure.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../domain/entities/course_detail.dart';
import '../../../domain/entities/course_enrollment.dart';
import '../../../domain/entities/course_summary.dart';
import '../../../domain/entities/lesson_content.dart';
import '../../../domain/repositories/course_repository.dart';
import '../../datasources/local/lms/lms_local_data_source.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/remote/lms/lms_remote_data_source.dart';
import '../../models/json_reader.dart';
import '../../models/lms/cours_json.dart';
import '../../models/lms/lecon_json.dart';

/// Le dépôt de l'Académie.
///
/// # Le cycle de lecture, et il est le même partout
///
/// 1. lire le cache et **rendre immédiatement** ;
/// 2. lancer la requête réseau ;
/// 3. si elle réussit, écrire le cache et rendre le frais ;
/// 4. si elle échoue, garder le cache et **dire d'où il vient**.
///
/// (05-HORS-LIGNE §5.4.) Le quatrième point est celui qu'on oublie : sans lui,
/// « une échéance J-2 vieille de trois jours » s'affiche comme si elle était
/// d'aujourd'hui.
///
/// POURQUOI le dépôt rend le cache **d'abord** au lieu d'attendre le réseau : le
/// produit s'adresse à des gens « qui suivent une leçon dans une cour d'école,
/// sans réseau. Une application qui affiche "pas de connexion" à ce moment-là
/// n'est pas dégradée : elle est absente. »
class CourseRepositoryImpl implements CourseRepository {
  CourseRepositoryImpl({
    required this.distant,
    required this.local,
    required this.file,
    DateTime Function()? horloge,
  }) : _horloge = horloge ?? DateTime.now;

  /// Les trois dépendances sont **publiques**, comme celles de la file du
  /// socle : « ses tests doivent pouvoir relire ce qu'on lui a donné ». Un
  /// champ privé nommé dans un constructeur nommé n'est de toute façon pas
  /// exprimable en Dart.
  final LmsRemoteDataSource distant;
  final LmsCache local;
  final PendingOpsQueue file;

  /// Injectable pour que les tests de fraîcheur du cache n'attendent pas une
  /// heure réelle.
  final DateTime Function() _horloge;

  @override
  Future<CatalogueLu> catalogue(FiltreCatalogue filtre) async {
    try {
      final ReponseBrute<PageDeCatalogue> reponse = await distant.catalogue(
        filtre,
      );
      final DateTime maintenant = _horloge();
      await local.ecrireCatalogue(
        filtre.cle,
        reponse.data,
        reponse.meta,
        maintenant,
      );
      return CatalogueLu(
        page: reponse.valeur,
        lueLe: maintenant,
        vientDuCache: false,
      );
    } on Failure {
      final CatalogueLu? cache = await _catalogueEnCache(filtre);
      if (cache != null) {
        return cache;
      }
      // Aucun cache pour CETTE combinaison de filtres. On ne se rabat pas sur
      // la liste non filtree : « filtrer localement ne filtre que la page deja
      // chargee », et servir une autre liste sous le meme titre serait pire
      // qu'une erreur — ce serait une erreur invisible (arbitrage n° 82).
      rethrow;
    }
  }

  Future<CatalogueLu?> _catalogueEnCache(FiltreCatalogue filtre) async {
    final EntreeDeCache? entree = await local.lireCatalogue(filtre.cle);
    if (entree == null) {
      return null;
    }
    return CatalogueLu(
      page: PageDeCatalogue(
        cours: <CourseSummary>[
          for (final Map<String, Object?> json in _objets(entree.data))
            CoursJson.resume(json),
        ],
        // Hors ligne, il n'y a pas de page suivante : un curseur est une
        // position dans l'ordre du SERVEUR, il ne se rejoue pas sur un cache.
        curseurSuivant: null,
        aPlus: false,
      ),
      lueLe: entree.lueLe,
      vientDuCache: true,
    );
  }

  @override
  Future<PageDeCatalogue> pageSuivante(
    FiltreCatalogue filtre,
    String curseur,
  ) async {
    // Le filtre repart AVEC le curseur, a l'identique. Sans lui, le serveur
    // rend la page suivante du catalogue non filtre — sans erreur, et donc
    // sans que personne le voie (contrat §3.4).
    final ReponseBrute<PageDeCatalogue> reponse = await distant.catalogue(
      filtre,
      curseur: curseur,
    );
    return reponse.valeur;
  }

  @override
  Future<FicheLue> fiche(String slug) async {
    try {
      final ReponseBrute<CourseDetail> reponse = await distant.fiche(slug);
      final DateTime maintenant = _horloge();
      await local.ecrireCours(slug, reponse.data, reponse.meta, maintenant);
      return FicheLue(
        fiche: reponse.valeur,
        lueLe: maintenant,
        vientDuCache: false,
        leconsHorsLigne: await local.leconsEnCache(slug),
        telechargeLe: await local.dateDeTelechargement(slug),
      );
    } on Failure {
      final EntreeDeCache? entree = await local.lireCours(slug);
      if (entree == null) {
        rethrow;
      }
      final Object? data = entree.data;
      return FicheLue(
        fiche: CoursJson.detail(asJsonObject(data), entree.meta),
        lueLe: entree.lueLe,
        vientDuCache: true,
        leconsHorsLigne: await local.leconsEnCache(slug),
        telechargeLe: await local.dateDeTelechargement(slug),
      );
    }
  }

  @override
  Future<List<CourseEnrollment>> mesInscriptions() async {
    final List<CourseEnrollment> toutes = <CourseEnrollment>[];
    String? curseur;
    // La liste est courte par nature — on suit les curseurs jusqu'au bout.
    // La borne existe pour qu'un serveur qui repondrait toujours `has_more`
    // ne fasse pas boucler l'application indefiniment.
    for (int page = 0; page < 20; page++) {
      final PageDInscriptions lue = await distant.mesInscriptions(
        curseur: curseur,
      );
      toutes.addAll(lue.inscriptions);
      curseur = lue.curseurSuivant;
      if (!lue.aPlus || curseur == null || curseur.isEmpty) {
        break;
      }
    }
    return toutes;
  }

  @override
  Future<CourseResume?> reprendre() => distant.reprendre();

  @override
  Future<CourseProgress> avancement(String slug) => distant.avancement(slug);

  @override
  Future<CourseEnrollment?> sInscrire(String slug) async {
    try {
      return await distant.sInscrire(slug);
    } on NetworkFailure {
      // Le reseau manque : l'acte part en file. Il est IDEMPOTENT PAR SERVICE
      // cote serveur, donc un rejeu rend l'inscription existante plutot que de
      // debiter deux fois.
      //
      // On rend `null` — et surtout pas une inscription fabriquee : « le solde
      // affiche ne bouge pas avant la reponse du serveur » (§5.9).
      await file.enqueue(
        kind: PendingOpKind.sInscrireAuCours,
        endpoint: ApiEndpoints.courseEnroll(slug),
      );
      return null;
    }
  }

  @override
  Future<LeconLue> lecon(String coursSlug, String leconUuid) async {
    try {
      final ReponseBrute<LessonContent> reponse = await distant.lecon(
        coursSlug,
        leconUuid,
      );
      final DateTime maintenant = _horloge();
      await local.ecrireLecon(
        uuid: leconUuid,
        coursSlug: coursSlug,
        data: reponse.data,
        meta: reponse.meta,
        secondesRequises: reponse.valeur.secondesRequises,
        lueLe: maintenant,
      );
      return LeconLue(
        lecon: reponse.valeur,
        lueLe: maintenant,
        vientDuCache: false,
      );
    } on Failure {
      final EntreeDeCache? entree = await local.lireLecon(leconUuid);
      if (entree == null) {
        rethrow;
      }
      // C'est LE cas d'usage central de M3 : le contrat donne au contenu d'une
      // lecon la duree de vie la plus longue de tout le cache — « jusqu'a la
      // fin du cours » — precisement pour cela.
      final Object? data = entree.data;
      return LeconLue(
        lecon: LeconJson.contenu(asJsonObject(data), entree.meta, coursSlug),
        lueLe: entree.lueLe,
        vientDuCache: true,
      );
    }
  }

  @override
  Future<bool> terminerLecon(
    String coursSlug,
    String leconUuid,
    int secondes,
  ) async {
    try {
      await distant.terminerLecon(leconUuid, secondes);
      return true;
    } on NetworkFailure {
      // Rejouable par CONTRAINTE D'UNICITE en base : pas de cle d'idempotence
      // a porter, et un rejeu ne fait pas depasser 100 %.
      await file.enqueue(
        kind: PendingOpKind.terminerLecon,
        endpoint: ApiEndpoints.lessonComplete(leconUuid),
        payload: <String, Object?>{'secondes': secondes},
      );
      // `false` : l'ecran NE MARQUE PAS la lecon comme validee. « Ne jamais
      // marquer localement une completion non confirmee : la progression
      // serait fausse au prochain chargement. »
      return false;
    }
  }

  @override
  Future<void> marquerTelecharge(String slug) =>
      local.marquerTelecharge(slug, _horloge());

  @override
  Future<void> supprimerTelechargement(String slug) =>
      local.supprimerTelechargement(slug);

  @override
  Future<int> octetsStockes(String slug) => local.octetsDuCours(slug);

  @override
  Future<int> secondesLues(String leconUuid) =>
      local.lireSecondesActives(leconUuid);

  @override
  Future<void> enregistrerSecondesLues(String leconUuid, int secondes) =>
      local.ecrireSecondesActives(leconUuid, secondes);

  /// Les objets d'une liste JSON gardée en cache.
  ///
  /// POURQUOI une fonction plutôt que la conversion recopiée à trois endroits :
  /// c'est exactement le genre de duplication qui diverge, et la divergence ici
  /// se voit sous la forme d'une liste vide inexpliquée.
  static List<Map<String, Object?>> _objets(Object? data) =>
      <Map<String, Object?>>[
        for (final Object? item in data is List ? data : const <Object?>[])
          if (item is Map) asJsonObject(item),
      ];
}
