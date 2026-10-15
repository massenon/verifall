import '../../../domain/entities/course_detail.dart';
import '../../../domain/entities/course_enrollment.dart';
import '../../../domain/entities/course_level.dart';
import '../../../domain/entities/course_summary.dart';
import '../../../domain/entities/lesson_summary.dart';
import '../../../domain/entities/quiz_summary.dart';
import '../auteur.dart';
import '../json_reader.dart';
import 'lecon_json.dart';
import 'quiz_json.dart';

/// La traduction JSON → domaine des ressources de l'Académie.
///
/// POURQUOI des fonctions et pas des classes `XxxModel extends XxxEntity` : le
/// seul rôle de cette couche est de **traduire**, et une classe qui n'ajoute
/// qu'un constructeur de fabrique ajoute surtout un nom de plus à retenir. La
/// frontière que §1.2 exige — « `presentation/` ne connaît que `domain/` » —
/// est tenue par le fait que ces fonctions rendent des entités, pas par
/// l'existence d'une classe intermédiaire.
///
/// POURQUOI la lecture est **tolérante** partout : le contrat autorise
/// explicitement les ajouts de champs sans préavis (§6.2), et « un client qui
/// lève une exception sur une clé inattendue fige l'API pour toujours ».
abstract final class CoursJson {
  static CourseSummary resume(Map<String, Object?> json) {
    final JsonReader r = JsonReader(json);
    return CourseSummary(
      slug: r.string('slug'),
      titre: r.string('titre'),
      description: r.string('description'),
      categorie: r.string('categorie'),
      niveau: NiveauAffiche(
        valeur: NiveauCours.parse(r.stringOrNull('niveau')),
        libelle: r.string('niveau_libelle'),
      ),
      duree: r.string('duree'),
      couvertureUrl: r.stringOrNull('couverture_url'),
      coutUnites: r.intValue('cout_unites'),
      estOffert: r.boolValue('est_offert'),
      objectifs: <String>[
        for (final Object? o in r.listOrEmpty('objectifs'))
          if (o != null) '$o',
      ],
      delivreUnCertificat: r.boolValue('delivre_un_certificat'),
      nombreDEtapes: r.intValue('nombre_d_etapes'),
      formateur: _intervenant(r.objectOrNull('formateur')),
    );
  }

  /// La fiche complète. [meta] porte `meta.inscription`, que le contrôleur pose
  /// **à côté** de `data` — et qui vaut `null` quand on n'est pas inscrit.
  static CourseDetail detail(
    Map<String, Object?> json,
    Map<String, Object?> meta,
  ) {
    final JsonReader r = JsonReader(json);
    final Map<String, Object?>? inscription = JsonReader(
      meta,
    ).objectOrNull('inscription');

    return CourseDetail(
      cours: resume(json),
      lecons: <LessonSummary>[
        for (final Map<String, Object?> l in r.objectListOrEmpty('lecons'))
          LeconJson.resume(l),
      ],
      quiz: <QuizSummary>[
        for (final Map<String, Object?> q in r.objectListOrEmpty('quiz'))
          QuizJson.resume(q),
      ],
      inscription: inscription == null
          ? null
          : InscriptionJson.lire(inscription),
    );
  }

  static Intervenant? _intervenant(Map<String, Object?>? json) {
    if (json == null || json.isEmpty) {
      return null;
    }
    // On passe par le modèle de transport du socle : les initiales viennent du
    // serveur, et les recalculer donnerait deux résultats sur un nom composé.
    final Auteur auteur = Auteur.fromJson(json);
    if (auteur.uuid.isEmpty && auteur.nomAffichage.isEmpty) {
      return null;
    }
    return Intervenant(
      uuid: auteur.uuid,
      nomAffichage: auteur.nomAffichage,
      initiales: auteur.initiales,
    );
  }

  static Intervenant? intervenant(Map<String, Object?>? json) =>
      _intervenant(json);
}

/// `InscriptionResource`.
abstract final class InscriptionJson {
  static CourseEnrollment lire(Map<String, Object?> json) {
    final JsonReader r = JsonReader(json);
    return CourseEnrollment(
      uuid: r.string('uuid'),
      statut: StatutInscription.parse(r.stringOrNull('statut')),
      statutLibelle: r.string('statut_libelle'),
      avancement: r.intValue('avancement'),
      unitesPayees: r.intValue('unites_payees'),
      coursSlug: r.stringOrNull('cours_slug'),
      coursTitre: r.stringOrNull('cours_titre'),
      commenceeLe: r.dateTimeOrNull('commencee_le'),
      derniereActiviteLe: r.dateTimeOrNull('derniere_activite_le'),
      acheveeLe: r.dateTimeOrNull('achevee_le'),
    );
  }
}

/// `GET /me/courses/resume` et `GET /me/courses/{slug}/progress` : deux
/// enveloppes qui partagent trois clés.
abstract final class ReprendreJson {
  /// Rend `null` quand `data` est nul — « ce n'est pas une erreur, c'est un
  /// état vide ».
  static CourseResume? reprise(Map<String, Object?>? json) {
    if (json == null || json.isEmpty) {
      return null;
    }
    final JsonReader r = JsonReader(json);
    final Map<String, Object?>? inscription = r.objectOrNull('inscription');
    final Map<String, Object?>? cours = r.objectOrNull('cours');
    if (inscription == null || cours == null) {
      return null;
    }
    final Map<String, Object?>? prochaine = r.objectOrNull('prochaine_lecon');
    return CourseResume(
      inscription: InscriptionJson.lire(inscription),
      cours: CoursJson.resume(cours),
      prochaineLecon: prochaine == null ? null : LeconJson.resume(prochaine),
    );
  }

  static CourseProgress avancement(Map<String, Object?> json) {
    final JsonReader r = JsonReader(json);
    final Map<String, Object?> inscription =
        r.objectOrNull('inscription') ?? const <String, Object?>{};
    final Map<String, Object?>? prochaine = r.objectOrNull('prochaine_lecon');
    return CourseProgress(
      inscription: InscriptionJson.lire(inscription),
      avancement: r.intValue('avancement'),
      acheve: r.boolValue('acheve'),
      prochaineLecon: prochaine == null ? null : LeconJson.resume(prochaine),
    );
  }
}
