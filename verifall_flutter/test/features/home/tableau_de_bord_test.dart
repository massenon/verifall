import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/domain/entities/home_tableau_de_bord.dart';
import 'package:verifall_app/core/format/vf_formats.dart';

/// L'agrégat tel que `TableauDeBordController::accueil()` le sérialise.
///
/// POURQUOI la fabrique est explicite : « jamais les données du simulateur en
/// dur dans un test ». Chaque clé vient de la réponse réelle.
Map<String, Object?> agregatJson({int? unites = 34}) => <String, Object?>{
  'salutation': 'Aïcha',
  'compteurs': <String, Object?>{
    'verifications': 12,
    'cours_suivis': 2,
    'badges': 5,
    // `unites` ABSENT quand le solde n'a pas ete relu : c'est ce que porte un
    // agregat servi par le cache.
    'unites': ?unites,
  },
  'niveau': <String, Object?>{
    'rang': 2,
    'nom': 'Observateur',
    'pourcentage': 45,
  },
  'serie': <String, Object?>{'jours': 7, 'vivante': true},
  'activite': <String, Object?>{
    'jours': <String>['1/8', '2/8', '3/8'],
    'valeurs': <int>[0, 3, 1],
  },
  'cours_offert': null,
};

void main() {
  group('Le solde d unités ne va JAMAIS au cache', () {
    test('la copie de cache retire `unités`', () {
      final TableauDeBord lu = TableauDeBord.fromJson(agregatJson());
      expect(lu.compteurs.unites, equals(34));

      // « Ce qui NE doit jamais etre servi depuis le cache : le solde d'unites.
      // On relit cet ecran precisement parce qu'on attend un changement ; y
      // montrer une valeur d'il y a cinq minutes fait croire a un paiement
      // perdu. »
      expect(lu.pourLeCache().compteurs.unites, isNull);
    });

    test('un agregat sans `unités` ne fabrique pas un zero', () {
      // Un zero ferait croire a un compte vide. `null` dit « pas encore relu »,
      // et la pastille affiche alors un tiret.
      final TableauDeBord lu = TableauDeBord.fromJson(
        agregatJson(unites: null),
      );
      expect(lu.compteurs.unites, isNull);
    });
  });

  group('L agregat, decode contre la réponse réelle', () {
    test('les quatre compteurs servis sont lus', () {
      final CompteursCitoyen c = TableauDeBord.fromJson(
        agregatJson(),
      ).compteurs;
      // Le serveur sert `verifications`, `cours_suivis`, `badges`, `unites`.
      // Il ne sert PAS l'XP absolu : la troisieme tuile du simulateur affiche
      // donc le compteur reellement servi, jamais un nombre invente.
      expect(c.verifications, equals(12));
      expect(c.coursSuivis, equals(2));
      expect(c.badges, equals(5));
    });

    test('« cours_offert: null » n est pas une erreur, c est une absence', () {
      // « Annoncer une offre qui n'existe pas serait une promesse en l'air. »
      expect(TableauDeBord.fromJson(agregatJson()).coursOffert, isNull);
    });

    test('les jours SANS activité restent a zero', () {
      // « Un graphique qui saute les jours vides ment sur la regularite, qui
      // est justement ce qu'il montre. »
      final ActiviteTrenteJours a = TableauDeBord.fromJson(
        agregatJson(),
      ).activite;
      expect(a.valeurs, equals(<int>[0, 3, 1]));
      expect(a.maximum, equals(3));
      expect(a.jourDuMaximum, equals('2/8'));
    });

    test('deux tableaux de longueurs differentes ne produisent pas de '
        'barre orpheline', () {
      final TableauDeBord lu = TableauDeBord.fromJson(<String, Object?>{
        ...agregatJson(),
        'activite': <String, Object?>{
          'jours': <String>['1/8', '2/8'],
          'valeurs': <int>[1, 2, 3, 4],
        },
      });
      expect(lu.activite.jours.length, equals(lu.activite.valeurs.length));
    });

    test('une activité entierement a zero se reconnait', () {
      final TableauDeBord lu = TableauDeBord.fromJson(<String, Object?>{
        ...agregatJson(),
        'activite': <String, Object?>{
          'jours': <String>['1/8', '2/8'],
          'valeurs': <int>[0, 0],
        },
      });
      expect(lu.activite.estVide, isTrue);
      expect(lu.activite.jourDuMaximum, isNull);
    });
  });

  group('La reprise de cours', () {
    test('elle se lit de `GET /me/courses/resume`', () {
      final RepriseDeCours r = RepriseDeCours.fromJson(<String, Object?>{
        'inscription': <String, Object?>{'avancement': 40},
        'cours': <String, Object?>{
          'slug': 'osint-fondamentaux',
          'titre': 'OSINT — fondamentaux',
        },
        'prochaine_lecon': <String, Object?>{
          'titre': 'La recherche d image inversee',
        },
      });
      expect(r.coursSlug, equals('osint-fondamentaux'));
      expect(r.avancement, equals(40));
      expect(r.prochaineLeconTitre, equals('La recherche d image inversee'));
    });

    test('sans prochaine leçon, l ecran ne fabrique pas « Quiz final »', () {
      final RepriseDeCours r = RepriseDeCours.fromJson(<String, Object?>{
        'inscription': <String, Object?>{'avancement': 90},
        'cours': <String, Object?>{'slug': 's', 'titre': 't'},
        'prochaine_lecon': null,
      });
      expect(r.prochaineLeconTitre, isNull);
    });
  });

  group('L age du cache, qui remplace le « il y a 2 min » en dur', () {
    final DateTime maintenant = DateTime.utc(2026, 8, 25, 12);

    test('moins d une minute', () {
      expect(
        VfFormats.age(
          maintenant.subtract(const Duration(seconds: 20)),
          maintenant: maintenant,
        ),
        equals('a l instant'),
      );
    });

    test('quelques minutes', () {
      expect(
        VfFormats.age(
          maintenant.subtract(const Duration(minutes: 12)),
          maintenant: maintenant,
        ),
        equals('il y a 12 min'),
      );
    });

    test('plusieurs jours — le cas qui rend le « 2 min » en dur mensonger', () {
      expect(
        VfFormats.age(
          maintenant.subtract(const Duration(days: 3)),
          maintenant: maintenant,
        ),
        equals('il y a 3 jours'),
      );
    });

    test('jamais synchronise se dit, plutôt que de se taire', () {
      expect(VfFormats.age(null), equals('jamais synchronise'));
    });

    test('le bandeau porte LA DATE, pas seulement l anciennete', () {
      final String bandeau = VfFormats.bandeauDeFraicheur(
        DateTime.utc(2026, 8, 22, 9, 5),
      );
      expect(bandeau, contains('22/08'));
      expect(bandeau, contains('actualisation impossible'));
    });
  });
}
