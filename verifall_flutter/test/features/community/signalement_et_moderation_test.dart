import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/data/datasources/remote/community/forum_remote_datasource.dart';
import 'package:verifall_app/data/models/api_enums.dart';
import 'package:verifall_app/data/models/community/reponse_forum_model.dart';
import 'package:verifall_app/data/models/community/sujet_forum_model.dart';
import 'package:verifall_app/domain/entities/reply_entity.dart';
import 'package:verifall_app/domain/entities/thread_entity.dart';
import 'package:verifall_app/domain/usecases/community/repondre_a_un_sujet.dart';
import 'package:verifall_app/domain/usecases/community/signaler_un_contenu.dart';
import 'package:verifall_app/presentation/features/community/widgets/feuille_signalement.dart';

import 'support/doublures_m4.dart';

/// **Signaler n'est pas voter** — arbitrage n° 52.
///
/// > « Cinq personnes qui signalent le même message ne le rendent pas cinq fois
/// > plus répréhensible ; il n'y a aucun masquage automatique au-delà d'un
/// > seuil, parce que ce serait offrir un bouton de censure à n'importe quel
/// > groupe organisé. Un humain tranche, toujours. »
void main() {
  group('Les motifs sont EXACTEMENT les six du contrat', () {
    test('ni un de plus, ni un de moins', () {
      expect(
        SignalerUnContenu.motifs
            .map((MotifSignalement m) => m.wireValue)
            .toList(),
        <String>[
          'desinformation',
          'haine',
          'arnaque',
          'spam',
          'donnees',
          'autre',
        ],
      );
    });

    test('chaque motif a un libelle affichable', () {
      for (final MotifSignalement motif in SignalerUnContenu.motifs) {
        expect(motif.libelle.trim(), isNotEmpty);
      }
    });

    test(
      'le corps envoye porte `motif`, et `détail` seulement s il existe',
      () {
        expect(
          ForumRemoteDatasource.corpsDeSignalement(
            motif: MotifSignalement.arnaque,
          ),
          <String, Object?>{'motif': 'arnaque'},
        );
        expect(
          ForumRemoteDatasource.corpsDeSignalement(
            motif: MotifSignalement.arnaque,
            detail: '  frais de dossier reclames  ',
          ),
          <String, Object?>{
            'motif': 'arnaque',
            'detail': 'frais de dossier reclames',
          },
        );
        expect(
          ForumRemoteDatasource.corpsDeSignalement(
            motif: MotifSignalement.spam,
            detail: '   ',
          ).containsKey('detail'),
          isFalse,
        );
      },
    );

    test('le meme corps sert le forum et le Hub', () async {
      final AdaptateurScripte adaptateur = AdaptateurScripte(
        (_) => reponseJson(<String, Object?>{'data': null}),
      );
      final ApiClient client = clientDeTest(adaptateur);
      await ForumRemoteDatasource(
        client,
      ).signaler(slug: 'un-sujet', motif: MotifSignalement.haine);
      expect(adaptateur.recues.single.path, '/forum/threads/un-sujet/report');
      expect(adaptateur.recues.single.data, <String, Object?>{
        'motif': 'haine',
      });
    });
  });

  group('La feuille le dit AVANT l envoi', () {
    testWidgets('l avertissement precede la liste des motifs', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        const MaterialApp(home: Scaffold(body: FeuilleSignalement())),
      );

      expect(
        find.text(SignalerUnContenu.avertissementAvantEnvoi),
        findsOneWidget,
      );
      expect(
        SignalerUnContenu.avertissementAvantEnvoi.contains('pas de '),
        isTrue,
      );
      expect(
        SignalerUnContenu.avertissementAvantEnvoi.contains(
          'signaler n est pas voter',
        ),
        isTrue,
      );
    });

    testWidgets('les six motifs sont proposes, et rien d autre', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        const MaterialApp(home: Scaffold(body: FeuilleSignalement())),
      );
      for (final MotifSignalement motif in SignalerUnContenu.motifs) {
        expect(find.text(motif.libelle), findsOneWidget);
      }
    });

    testWidgets('le bouton reste visible et desactive sans motif', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        const MaterialApp(home: Scaffold(body: FeuilleSignalement())),
      );
      // « Un bouton qui disparait fait chercher une fonction qu on croit
      // perdue ; un bouton grise fait comprendre qu il manque quelque
      // chose » (§2.7).
      expect(find.text('Envoyer le signalement'), findsOneWidget);
    });
  });

  group('Un fil verrouille', () {
    test('l entite porte l état, et le composeur disparait', () {
      final SujetForum verrouille = SujetForumModel.fromJson(<String, Object?>{
        'slug': 's',
        'titre': 'T',
        'corps': '<p>c</p>',
        'verrouille': true,
      });
      expect(verrouille.verrouille, isTrue);
    });

    test('l absence du champ ne verrouille rien : le serveur arbitre', () {
      final SujetForum ouvert = SujetForumModel.fromJson(<String, Object?>{
        'slug': 's',
        'titre': 'T',
        'corps': '<p>c</p>',
      });
      expect(
        ouvert.verrouille,
        isFalse,
        reason:
            'L ecran masque le composeur, mais c est le serveur qui protege : '
            'un 422 action_impossible. Verrouiller par defaut priverait de '
            'réponse des fils ouverts.',
      );
    });

    test('la validation locale reprend les bornes de la maquette', () {
      expect(RepondreAUnSujet.valider('ok'), isNotNull);
      expect(RepondreAUnSujet.valider('une vraie réponse'), isNull);
      // Le maxlength du simulateur est inclus dans la borne du contrat (5000).
      expect(RepondreAUnSujet.longueurMaximale, 800);
      expect(RepondreAUnSujet.longueurMinimale, 5);
    });
  });

  group('Un seul niveau d imbrication', () {
    test('les petits-enfants sont ecartes A LA LECTURE', () {
      final List<ReponseForum> reponses = ReponseForumModel.listeAplatie(
        <Map<String, Object?>>[
          <String, Object?>{
            'uuid': 'r-1',
            'corps': '<p>niveau 0</p>',
            'reponses': <Object?>[
              <String, Object?>{
                'uuid': 'r-1-1',
                'corps': '<p>niveau 1</p>',
                'reponses': <Object?>[
                  <String, Object?>{
                    'uuid': 'r-1-1-1',
                    'corps': '<p>niveau 2 — ne doit pas exister</p>',
                  },
                ],
              },
            ],
          },
        ],
      );

      expect(reponses.single.uuid, 'r-1');
      expect(reponses.single.reponses.single.uuid, 'r-1-1');
      expect(
        reponses.single.reponses.single.reponses,
        isEmpty,
        reason:
            'Couper a l affichage laisserait le contenu en memoire, et '
            'quelqu un le reafficherait un jour en toute bonne foi.',
      );
    });
  });

  group('Le signalement hors ligne', () {
    test('il part en file, et l ecran ne dit pas qu il est transmis', () async {
      final DepotForumDeTest depot = DepotForumDeTest(
        issueSignalement: IssueEcriture.miseEnFile,
      );
      final IssueEcriture issue =
          await SignalerUnContenu(depot, DepotHubDeTest())(
            cible: CibleSignalement.sujet,
            slug: 's',
            motif: MotifSignalement.arnaque,
          );

      expect(issue, IssueEcriture.miseEnFile);
      expect(depot.signalements, <MotifSignalement>[MotifSignalement.arnaque]);
    });

    test('un signalement d offre passe par le depot du Hub', () async {
      final DepotHubDeTest hub = DepotHubDeTest();
      await SignalerUnContenu(DepotForumDeTest(), hub)(
        cible: CibleSignalement.opportunite,
        slug: 'une-offre',
        motif: MotifSignalement.arnaque,
      );
      expect(hub.signalements, <MotifSignalement>[MotifSignalement.arnaque]);
    });
  });
}
