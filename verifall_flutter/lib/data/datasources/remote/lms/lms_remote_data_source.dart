import '../../../../core/network/api_client.dart';
import '../../../../core/network/api_endpoints.dart';
import '../../../../domain/entities/certificate_summary.dart';
import '../../../../domain/entities/course_detail.dart';
import '../../../../domain/entities/course_enrollment.dart';
import '../../../../domain/entities/course_summary.dart';
import '../../../../domain/entities/lesson_content.dart';
import '../../../../domain/entities/live_class_session.dart';
import '../../../../domain/entities/quiz_attempt.dart';
import '../../../../domain/entities/quiz_summary.dart';
import '../../../../domain/repositories/course_repository.dart';
import '../../../models/api_envelope.dart';
import '../../../models/lms/certificat_json.dart';
import '../../../models/lms/cours_json.dart';
import '../../../models/lms/lecon_json.dart';
import '../../../models/lms/quiz_json.dart';
import '../../../models/lms/seance_json.dart';

/// Ce qu'une réponse brute a rendu, **avec son enveloppe**.
///
/// POURQUOI on garde le JSON à côté de l'entité : le cache local stocke la
/// charge utile telle quelle. « Le contrat autorise explicitement les ajouts de
/// champs sans préavis » — recomposer du JSON depuis une entité perdrait ce que
/// cette version du client ne sait pas encore lire, et le perdrait
/// définitivement au premier passage par le cache.
class ReponseBrute<T> {
  const ReponseBrute({
    required this.valeur,
    required this.data,
    required this.meta,
  });

  final T valeur;
  final Object? data;
  final Map<String, Object?> meta;
}

/// Les appels de l'Académie.
///
/// Les chemins viennent **tous** de `ApiEndpoints` : « un chemin écrit à la
/// main dans un écran est un chemin qu'on ne peut plus auditer contre le
/// contrat, et c'est ainsi qu'apparaît un appel à un endpoint qui n'a jamais
/// existé ».
class LmsRemoteDataSource {
  const LmsRemoteDataSource(this._client);

  final ApiClient _client;

  /// `GET /courses` — paginé par curseur.
  ///
  /// Les paramètres de filtre repartent **à l'identique** avec le curseur : le
  /// curseur ne les porte pas (arbitrage n° 82), et l'appel fautif ne produit
  /// aucune erreur — il rend simplement la page suivante du catalogue non
  /// filtré.
  Future<ReponseBrute<PageDeCatalogue>> catalogue(
    FiltreCatalogue filtre, {
    String? curseur,
  }) async {
    final ApiEnvelope reponse = await _client.getPage(
      ApiEndpoints.courses,
      cursor: curseur,
      filters: filtre.parametres,
    );
    final PageMeta? page = reponse.page;
    return ReponseBrute<PageDeCatalogue>(
      valeur: PageDeCatalogue(
        cours: <CourseSummary>[
          for (final Map<String, Object?> c in reponse.dataList)
            CoursJson.resume(c),
        ],
        curseurSuivant: page?.nextCursor,
        aPlus: page?.hasMore ?? false,
      ),
      data: reponse.data,
      meta: reponse.meta,
    );
  }

  /// `GET /courses/{slug}` — l'identifiant est le **slug**.
  Future<ReponseBrute<CourseDetail>> fiche(String slug) async {
    final ApiEnvelope reponse = await _client.get(ApiEndpoints.course(slug));
    return ReponseBrute<CourseDetail>(
      valeur: CoursJson.detail(reponse.dataObject, reponse.meta),
      data: reponse.data,
      meta: reponse.meta,
    );
  }

  /// `POST /courses/{slug}/enroll` — idempotent par service.
  Future<CourseEnrollment> sInscrire(String slug) async {
    final ApiEnvelope reponse = await _client.post(
      ApiEndpoints.courseEnroll(slug),
    );
    return InscriptionJson.lire(reponse.dataObject);
  }

  /// `GET /me/enrollments` — paginé. Le dépôt suit les curseurs.
  Future<PageDInscriptions> mesInscriptions({String? curseur}) async {
    final ApiEnvelope reponse = await _client.getPage(
      ApiEndpoints.enrollments,
      cursor: curseur,
    );
    return PageDInscriptions(
      inscriptions: <CourseEnrollment>[
        for (final Map<String, Object?> i in reponse.dataList)
          InscriptionJson.lire(i),
      ],
      curseurSuivant: reponse.page?.nextCursor,
      aPlus: reponse.page?.hasMore ?? false,
    );
  }

  /// `GET /me/courses/resume` — rend **`data: null`** quand il n'y a rien à
  /// reprendre. Ce n'est pas une erreur.
  Future<CourseResume?> reprendre() async {
    final ApiEnvelope reponse = await _client.get(ApiEndpoints.coursesResume);
    if (reponse.data == null) {
      return null;
    }
    return ReprendreJson.reprise(reponse.dataObject);
  }

  /// `GET /me/courses/{slug}/progress`.
  Future<CourseProgress> avancement(String slug) async {
    final ApiEnvelope reponse = await _client.get(
      ApiEndpoints.courseProgress(slug),
    );
    return ReprendreJson.avancement(reponse.dataObject);
  }

  /// `GET /courses/{slug}/lessons/{uuid}`.
  ///
  /// **Cet appel démarre le chronomètre serveur.** Il n'est donc jamais émis
  /// « pour voir » : le téléchargement d'un cours le sait et le dit (réserve
  /// C.3).
  Future<ReponseBrute<LessonContent>> lecon(
    String coursSlug,
    String leconUuid,
  ) async {
    final ApiEnvelope reponse = await _client.get(
      ApiEndpoints.courseLesson(coursSlug, leconUuid),
    );
    return ReponseBrute<LessonContent>(
      valeur: LeconJson.contenu(reponse.dataObject, reponse.meta, coursSlug),
      data: reponse.data,
      meta: reponse.meta,
    );
  }

  /// `POST /me/lessons/{uuid}/complete` — corps : `secondes`.
  ///
  /// Rejouable **par contrainte d'unicité en base** : pas de clé d'idempotence
  /// à porter.
  Future<void> terminerLecon(String leconUuid, int secondes) async {
    await _client.post(
      ApiEndpoints.lessonComplete(leconUuid),
      body: <String, Object?>{'secondes': secondes},
    );
  }

  /// `GET /courses/{slug}/quiz` — questions **sans corrigé**, plus
  /// `meta.commence_le`.
  Future<QuizDetail> quiz(String coursSlug) async {
    final ApiEnvelope reponse = await _client.get(
      ApiEndpoints.courseQuiz(coursSlug),
    );
    return QuizJson.detail(reponse.dataObject, reponse.meta);
  }

  /// `POST /me/quiz/{uuid}/attempt`.
  ///
  /// [cleIdempotence] est **la clé de l'acte**, pas de la tentative : elle est
  /// générée au moment où la personne rend sa copie et réutilisée identique à
  /// chaque reprise. « Une copie rendue deux fois par une réponse perdue serait
  /// une seconde tentative fantôme. »
  Future<QuizAttemptResult> rendreLaCopie({
    required String quizUuid,
    required Map<String, int> reponses,
    required String cleIdempotence,
  }) async {
    final ApiEnvelope reponse = await _client.post(
      ApiEndpoints.quizAttempt(quizUuid),
      body: <String, Object?>{'reponses': reponses},
      idempotencyKey: cleIdempotence,
    );
    return QuizJson.resultat(reponse.dataObject);
  }

  /// `GET /me/certificates` — **non paginée**.
  Future<ReponseBrute<List<CertificateSummary>>> mesCertificats() async {
    final ApiEnvelope reponse = await _client.get(ApiEndpoints.certificates);
    return ReponseBrute<List<CertificateSummary>>(
      valeur: <CertificateSummary>[
        for (final Map<String, Object?> c in reponse.dataList)
          CertificatJson.lire(c),
      ],
      data: reponse.data,
      meta: reponse.meta,
    );
  }

  /// `GET /me/certificates/{code}` — par le **code public**.
  Future<ReponseBrute<CertificateSummary>> certificat(String code) async {
    final ApiEnvelope reponse = await _client.get(
      ApiEndpoints.certificate(code),
    );
    return ReponseBrute<CertificateSummary>(
      valeur: CertificatJson.lire(reponse.dataObject),
      data: reponse.data,
      meta: reponse.meta,
    );
  }

  /// `GET /live-classes` — **non paginée** (liste fermée).
  Future<ReponseBrute<List<LiveClassSession>>> seances() async {
    final ApiEnvelope reponse = await _client.get(ApiEndpoints.liveClasses);
    return ReponseBrute<List<LiveClassSession>>(
      valeur: <LiveClassSession>[
        for (final Map<String, Object?> s in reponse.dataList)
          SeanceJson.lire(s),
      ],
      data: reponse.data,
      meta: reponse.meta,
    );
  }

  /// `POST /live-classes/{uuid}/remind` — enregistre l'intention. `201`.
  ///
  /// **Séance passée : `422 action_impossible`.** L'écriture est protégée par
  /// une contrainte d'unicité en base, donc rejouable depuis la file.
  Future<void> demanderUnRappel(String uuid) async {
    await _client.post(ApiEndpoints.liveClassRemind(uuid));
  }
}

class PageDInscriptions {
  const PageDInscriptions({
    required this.inscriptions,
    required this.curseurSuivant,
    required this.aPlus,
  });

  final List<CourseEnrollment> inscriptions;
  final String? curseurSuivant;
  final bool aPlus;
}
