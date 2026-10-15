import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/data/datasources/remote/community/forum_remote_datasource.dart';
import 'package:verifall_app/data/datasources/remote/community/hub_remote_datasource.dart';
import 'package:verifall_app/domain/entities/opportunity_entity.dart';
import 'package:verifall_app/domain/entities/thread_entity.dart';

import 'support/doublures_m4.dart';

/// **Le curseur ne porte pas les filtres** — contrat §3.4, arbitrage n° 82.
///
/// > « L'appel fautif — curseur seul, sans filtre — **ne renvoie aucune
/// > erreur**. Il rend la page suivante de la liste non filtrée. L'écran reste
/// > plausible ; il est simplement faux à partir de la deuxième page. C'est la
/// > catégorie de bogue qu'aucun test client ne rattrape et qu'un utilisateur
/// > finit par signaler six mois plus tard. »
///
/// Ce fichier est ce test-là.
void main() {
  late AdaptateurScripte adaptateur;
  late ApiClient client;

  Map<String, Object?> pageVide({String? curseur}) => <String, Object?>{
    'data': <Object?>[],
    'meta': <String, Object?>{
      'page': <String, Object?>{
        'per_page': 20,
        'next_cursor': curseur,
        'prev_cursor': null,
        'has_more': curseur != null,
      },
    },
  };

  setUp(() {
    adaptateur = AdaptateurScripte(
      (RequestOptions _) => reponseJson(pageVide(curseur: 'eyJpZCI6MTQyfQ')),
    );
    client = clientDeTest(adaptateur);
  });

  group('Forum', () {
    test('le filtre de rubrique repart AVEC le curseur', () async {
      final ForumRemoteDatasource source = ForumRemoteDatasource(client);

      await source.listerSujets(categorie: 'sante');
      await source.listerSujets(categorie: 'sante', curseur: 'eyJpZCI6MTQyfQ');

      expect(adaptateur.recues, hasLength(2));

      final Map<String, dynamic> premiere =
          adaptateur.recues[0].queryParameters;
      final Map<String, dynamic> seconde = adaptateur.recues[1].queryParameters;

      expect(premiere['category'], 'sante');
      expect(
        seconde['category'],
        'sante',
        reason:
            'Sans le filtre, la page 2 serait celle de la liste NON filtree, '
            'et aucune erreur ne le signalerait.',
      );
      expect(seconde['cursor'], 'eyJpZCI6MTQyfQ');
    });

    test('aucune requete ne porte le parametre `page`', () async {
      await ForumRemoteDatasource(client).listerSujets();
      expect(
        adaptateur.recues.single.queryParameters.containsKey('page'),
        isFalse,
        reason:
            'Les panneaux du simulateur ecrivent parfois ?page= : c est une '
            'abreviation de leur auteur. Le parametre n existe pas (n° 68).',
      );
    });

    test('`limit` est borne au plafond du contrat', () async {
      await ForumRemoteDatasource(client).listerSujets();
      expect(adaptateur.recues.single.queryParameters['limit'], 20);
    });

    test('la fin de liste rend un curseur nul', () async {
      adaptateur = AdaptateurScripte(
        (RequestOptions _) => reponseJson(pageVide()),
      );
      final PageSujets page = await ForumRemoteDatasource(
        clientDeTest(adaptateur),
      ).listerSujets();
      expect(page.curseurSuivant, isNull);
    });

    test(
      'les pastilles viennent de meta.catégories, pas d une liste ecrite',
      () async {
        adaptateur = AdaptateurScripte(
          (RequestOptions _) => reponseJson(<String, Object?>{
            'data': <Object?>[],
            'meta': <String, Object?>{
              'categories': <Object?>[
                <String, Object?>{'slug': 'sante', 'libelle': 'Sante'},
                <String, Object?>{'slug': 'politique', 'libelle': 'Politique'},
              ],
            },
          }),
        );
        final PageSujets page = await ForumRemoteDatasource(
          clientDeTest(adaptateur),
        ).listerSujets();

        expect(page.rubriques.map((RubriqueForum r) => r.slug), <String>[
          'sante',
          'politique',
        ]);
        // « Tous » vient du client, mais sa valeur est la sentinelle du contrat.
        expect(RubriqueForum.toutes.slug, 'toutes');
      },
    );
  });

  group('Opportunités — le piege de `scope`', () {
    test('la portee part en `benin`, jamais en `bj`', () async {
      await HubRemoteDatasource(
        client,
      ).listerOpportunites(portee: PorteeOpportunite.benin);

      expect(
        adaptateur.recues.single.queryParameters['scope'],
        'benin',
        reason:
            'Le panneau du simulateur ecrit ?scope=bj|intl. Ce sont des '
            'abreviations : `scope` est une enumeration fermee, et `bj` '
            'produirait un 422 (contrat §3.5 et §3.7).',
      );
    });

    test('la portee internationale part en `international`', () async {
      await HubRemoteDatasource(
        client,
      ).listerOpportunites(portee: PorteeOpportunite.international);
      expect(
        adaptateur.recues.single.queryParameters['scope'],
        'international',
      );
    });

    test('les dix valeurs de `type` sont celles du contrat', () {
      expect(
        TypeOpportunite.values.map((TypeOpportunite t) => t.wireValue).toSet(),
        <String>{
          'cdi',
          'cdd',
          'stage',
          'alternance',
          'volontariat',
          'bourse',
          'financement',
          'conference',
          'appel',
          'autre',
        },
      );
    });

    test('portee ET type repartent avec le curseur', () async {
      final HubRemoteDatasource source = HubRemoteDatasource(client);
      await source.listerOpportunites(
        portee: PorteeOpportunite.benin,
        type: TypeOpportunite.stage,
        curseur: 'eyJpZCI6OTl9',
      );

      final Map<String, dynamic> q = adaptateur.recues.single.queryParameters;
      expect(q['scope'], 'benin');
      expect(q['type'], 'stage');
      expect(q['cursor'], 'eyJpZCI6OTl9');
      expect(q.containsKey('page'), isFalse);
    });

    test('sans filtre, aucun parametre vide n est envoye', () async {
      await HubRemoteDatasource(client).listerOpportunites();
      final Map<String, dynamic> q = adaptateur.recues.single.queryParameters;
      expect(q.containsKey('scope'), isFalse);
      expect(q.containsKey('type'), isFalse);
    });
  });
}
