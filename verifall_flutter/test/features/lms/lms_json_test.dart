import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/domain/entities/certificate_summary.dart';
import 'package:verifall_app/domain/entities/course_detail.dart';
import 'package:verifall_app/domain/entities/course_enrollment.dart';
import 'package:verifall_app/domain/entities/course_level.dart';
import 'package:verifall_app/domain/entities/course_summary.dart';
import 'package:verifall_app/domain/entities/lesson_summary.dart';
import 'package:verifall_app/domain/entities/live_class_session.dart';
import 'package:verifall_app/data/models/lms/certificat_json.dart';
import 'package:verifall_app/data/models/lms/cours_json.dart';
import 'package:verifall_app/data/models/lms/lecon_json.dart';
import 'package:verifall_app/data/models/lms/seance_json.dart';
import 'package:verifall_app/domain/repositories/course_repository.dart';

import 'support/lms_fakes.dart';

/// La traduction JSON → domaine.
///
/// Trois pièges que 06-TESTS §6.3 nomme, et qui « cassent en production et
/// jamais en démonstration » :
///
/// 1. un champ **absent** se distingue d'un champ **présent et nul** ;
/// 2. un champ inconnu est ignoré ;
/// 3. une valeur d'énumération inattendue ne fait pas tomber l'écran.
void main() {
  group('Le deverrouillage vient du serveur, et rien d autre', () {
    test(
      'un champ ABSENT n est pas un verrou : c est une absence d information',
      () {
        // `whenHas` cote serveur : `ouverte` et `acquise` sont absents hors du
        // contexte ou le serveur les a calcules.
        final LessonSummary lecon = LeconJson.resume(
          leconJson(avecEtat: false),
        );

        expect(lecon.ouverte, isNull);
        expect(lecon.acquise, isNull);
        // Absent ≠ verrouille. L'ecran ne suppose rien.
        expect(lecon.estVerrouillee, isFalse);
        expect(lecon.estOuverte, isFalse);
      },
    );

    test('`ouverte: false` est un verrou, et il est lu tel quel', () {
      final LessonSummary lecon = LeconJson.resume(leconJson(ouverte: false));

      expect(lecon.estVerrouillee, isTrue);
      expect(lecon.estOuverte, isFalse);
    });

    test('seules les leçons OUVERTES sont telechargeables', () {
      final CourseDetail fiche = CoursJson.detail(
        coursJson(
          lecons: <Map<String, Object?>>[
            leconJson(uuid: 'l1', position: 1, acquise: true),
            leconJson(uuid: 'l2', position: 2),
            leconJson(uuid: 'l3', position: 3, ouverte: false),
          ],
        ),
        const <String, Object?>{},
      );

      // `GET /courses/{slug}/lessons/{uuid}` repond 403 sur une lecon
      // verrouillee : un « telechargement du cours entier » est impossible par
      // construction (reserve C.3).
      expect(
        fiche.leconsTelechargeables.map((LessonSummary l) => l.uuid),
        <String>['l1', 'l2'],
      );
      // « Reprendre » atterrit sur la premiere lecon ouverte NON acquise.
      expect(fiche.prochaineLecon?.uuid, 'l2');
    });
  });

  group('Les enumerations', () {
    test('une valeur inconnue ne fait pas tomber l ecran', () {
      final CourseSummary cours = CoursJson.resume(
        coursJson(niveau: 'expert', niveauLibelle: 'Expert'),
      );

      // Le cas neutre existe justement pour cela (arbitrage n° 38 cote back :
      // « un `match` sans branche par defaut faisait planter une page
      // entiere, a l'execution seulement »).
      expect(cours.niveau.valeur, NiveauCours.inconnu);
      // Et le LIBELLE du serveur est affiche tel quel : le client ne le
      // fabrique pas.
      expect(cours.niveau.libelle, 'Expert');
      expect(cours.niveau.estRenseigne, isTrue);
    });

    test('chaque valeur connue se traduit', () {
      for (final MapEntry<String, NiveauCours> cas in <String, NiveauCours>{
        'beginner': NiveauCours.debutant,
        'intermediate': NiveauCours.intermediaire,
        'advanced': NiveauCours.avance,
      }.entries) {
        expect(NiveauCours.parse(cas.key), cas.value);
      }
      for (final MapEntry<String, StatutInscription> cas
          in <String, StatutInscription>{
            'enrolled': StatutInscription.inscrit,
            'in_progress': StatutInscription.enCours,
            'completed': StatutInscription.termine,
          }.entries) {
        expect(StatutInscription.parse(cas.key), cas.value);
      }
      for (final MapEntry<String, EtatSeance> cas in <String, EtatSeance>{
        'upcoming': EtatSeance.aVenir,
        'live': EtatSeance.enDirect,
        'past': EtatSeance.passee,
      }.entries) {
        expect(EtatSeance.parse(cas.key), cas.value);
      }
    });

    test('le niveau inconnu n est JAMAIS envoye en filtre', () {
      // `level` est une enumeration FERMEE : une valeur hors liste repond 422,
      // et une valeur que ce client ne connait pas ne peut venir que de la.
      expect(
        const FiltreCatalogue(niveau: NiveauCours.inconnu).parametres,
        isEmpty,
      );
      expect(
        const FiltreCatalogue(niveau: NiveauCours.debutant).parametres,
        <String, Object?>{'level': 'beginner'},
      );
      // Omettre le parametre, c'est « aucun filtre » (contrat §3.6).
      expect(FiltreCatalogue.aucun.parametres, isEmpty);
      // Une recherche faite d'espaces n'est pas une recherche.
      expect(const FiltreCatalogue(recherche: '   ').parametres, isEmpty);
    });
  });

  group('Les champs qui n existent pas', () {
    test('aucune note, aucun nombre d inscrits ne sont lus', () {
      // Le simulateur affiche « ★ 4,8 » et « 1 245 inscrits ». Meme si le
      // serveur les ajoutait un jour, l'entite n'a AUCUN champ pour les
      // recevoir : la carte dira « Nouveau ».
      final CourseSummary cours = CoursJson.resume(<String, Object?>{
        ...coursJson(),
        'rating': 4.8,
        'students_count': 1245,
      });

      // Un champ inconnu est IGNORE, il ne fait pas echouer la lecture.
      expect(cours.slug, 'cours-neutre');
    });
  });

  group('L état vide de la reprise', () {
    test('`data: null` rend null, et ce n est pas une erreur', () {
      expect(ReprendreJson.reprise(null), isNull);
      expect(ReprendreJson.reprise(const <String, Object?>{}), isNull);
    });

    test('une reprise complete porte le cours et la prochaine leçon', () {
      final CourseResume? reprise = ReprendreJson.reprise(<String, Object?>{
        'inscription': inscriptionJson(),
        'cours': coursJson(),
        'prochaine_lecon': leconJson(uuid: 'l2', position: 2),
      });

      expect(reprise, isNotNull);
      expect(reprise!.cours.slug, 'cours-neutre');
      expect(reprise.prochaineLecon?.uuid, 'l2');
      expect(reprise.inscription.avancement, 40);
    });
  });

  group('Le certificat', () {
    test('porte les valeurs FIGEES a l emission', () {
      final CertificateSummary certificat = CertificatJson.lire(
        certificatJson(),
      );

      // « Un diplome atteste de ce qui a ete suivi ce jour-la. » Le titre du
      // cours vient du certificat, pas du catalogue d'aujourd'hui.
      expect(certificat.coursTitre, 'Titre au moment de l emission');
      expect(certificat.titulaire, 'Titulaire Neutre');
      // Identifie par son CODE public, pas par un uuid.
      expect(certificat.code, 'VF7KMPQR');
      expect(certificat.revoque, isFalse);
    });
  });

  group('La seance en direct', () {
    test('`lien_visio: null` rend la seance non joignable', () {
      final LiveClassSession seance = SeanceJson.lire(seanceJson());

      // « Le contrat refuse de publier trois semaines a l'avance un lien qui
      // circulerait. » Le bouton « Rejoindre » restera inactif.
      expect(seance.lienVisio, isNull);
      expect(seance.estJoignable, isFalse);
    });

    test('`rappel_demande` absent n est pas « non »', () {
      final LiveClassSession seance = SeanceJson.lire(
        seanceJson(rappelDemande: null),
      );

      // Absent = « le serveur ne s'est pas prononce ». L'ecran ne l'interprete
      // pas comme un refus.
      expect(seance.rappelDemande, isNull);
    });
  });
}
