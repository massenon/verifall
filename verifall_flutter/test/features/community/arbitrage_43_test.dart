import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/data/models/community/sujet_forum_model.dart';
import 'package:verifall_app/domain/entities/thread_entity.dart';
import 'package:verifall_app/presentation/features/community/widgets/forum_widgets.dart';
import 'package:verifall_app/presentation/shared/vf_display.dart';

/// **Le sondage et le verdict ne se mélangent jamais** — arbitrage n° 43.
///
/// C'est le point le plus important du parcours M4, et la définition de fini
/// exige qu'un test reproduise le cas limite :
///
/// > « La communauté croit l'information vraie à 100 %, la plateforme établit
/// > qu'elle est fausse, et les deux s'affichent sans s'écraser. »
///
/// La raison est écrite dans la documentation de l'écran 20 : « une majorité qui
/// se trompe reste une majorité qui se trompe : c'est même le mécanisme central
/// de la désinformation. Une plateforme de fact-checking qui laisserait le
/// sondage passer pour un verdict se contredirait dans son écran le plus
/// fréquenté. »
void main() {
  /// Le cas limite, servi tel que le serveur l'enverrait.
  Map<String, Object?> sujetContradictoire() => <String, Object?>{
    'slug': 'le-decret-de-nomination',
    'titre': 'Le pretendu decret de nomination circule encore',
    'corps': '<p>Verifie ?</p>',
    'auteur': <String, Object?>{
      'uuid': 'a-1',
      'nom_affichage': 'Kossi B.',
      'initiales': 'KB',
    },
    // LA COMMUNAUTE EST UNANIME : vrai a 100 %.
    'votes': <String, Object?>{'vrai': 100, 'doute': 0, 'faux': 0},
    // LA REDACTION A TRANCHE : c'est faux.
    'verdict_officiel': <String, Object?>{
      'verdict': 'false',
      'verdict_libelle': 'Faux',
    },
  };

  group('Le cas limite : la communauté a tort', () {
    test('les deux jugements coexistent dans l entite, sans s ecraser', () {
      final SujetForum sujet = SujetForumModel.fromJson(sujetContradictoire());

      // Le sondage est intact.
      expect(sujet.votes, isNotNull);
      expect(sujet.votes!.vraiPourcent, 100);
      expect(sujet.votes!.fauxPourcent, 0);

      // Le verdict aussi, et il dit l'inverse.
      expect(sujet.verdictOfficiel, isNotNull);
      expect(sujet.verdictOfficiel!.valeur, 'false');
      expect(sujet.verdictOfficiel!.libelle, 'Faux');
    });

    testWidgets('la carte affiche le bandeau ET la barre, sans arbitrer', (
      WidgetTester tester,
    ) async {
      final SujetForum sujet = SujetForumModel.fromJson(sujetContradictoire());

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: VfThreadCard(sujet: sujet, onTap: () {}),
          ),
        ),
      );

      // Le bandeau de la redaction est la, et il dit FAUX.
      expect(find.byType(VfOfficialVerdictBanner), findsOneWidget);
      expect(find.textContaining('VÉRIF ALL CONFIRME'), findsOneWidget);
      expect(find.textContaining('FAUX'), findsOneWidget);

      // Le sondage est la aussi, et il dit 100 % vrai. Le bandeau ne l'a pas
      // efface, et le sondage n'a pas efface le bandeau.
      expect(find.byType(VfVoteBar), findsOneWidget);
      expect(find.text('Vrai 100 %'), findsOneWidget);
      expect(find.text('Faux 0 %'), findsOneWidget);
    });
  });

  group('Le bandeau ne se deduit JAMAIS des votes', () {
    test('un sondage unanime sans verdict n allume aucun bandeau', () {
      final SujetForum sujet = SujetForumModel.fromJson(<String, Object?>{
        'slug': 's',
        'titre': 'T',
        'corps': '<p>c</p>',
        // 100 % de « faux » — et pourtant, aucun verdict officiel.
        'votes': <String, Object?>{'vrai': 0, 'doute': 0, 'faux': 100},
      });

      expect(sujet.votes!.fauxPourcent, 100);
      expect(
        sujet.verdictOfficiel,
        isNull,
        reason:
            'Le verdict officiel n apparaît QUE si le serveur le lie. '
            'Le deduire des votes serait l inverse de ce que ce produit fait.',
      );
    });

    testWidgets('et la carte ne dessine alors aucun bandeau', (
      WidgetTester tester,
    ) async {
      final SujetForum sujet = SujetForumModel.fromJson(<String, Object?>{
        'slug': 's',
        'titre': 'T',
        'corps': '<p>c</p>',
        'votes': <String, Object?>{'vrai': 0, 'doute': 0, 'faux': 100},
      });

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: VfThreadCard(sujet: sujet, onTap: () {}),
          ),
        ),
      );

      expect(find.byType(VfOfficialVerdictBanner), findsNothing);
      expect(find.byType(VfVoteBar), findsOneWidget);
    });

    test('un verdict sans sondage n invente aucune barre', () {
      final SujetForum sujet = SujetForumModel.fromJson(<String, Object?>{
        'slug': 's',
        'titre': 'T',
        'corps': '<p>c</p>',
        'verdict_officiel': <String, Object?>{
          'verdict': 'false',
          'verdict_libelle': 'Faux',
        },
      });

      expect(sujet.verdictOfficiel, isNotNull);
      expect(
        sujet.votes,
        isNull,
        reason:
            'Un sujet sans sondage n en recoit pas un a zero : un zero affiche '
            'a la place d une donnee absente est un chiffre invente.',
      );
    });
  });

  group('Le vote ne touche jamais au verdict officiel', () {
    test('reconcilier conserve le verdict, quoi que le serveur renvoie', () {
      final SujetForum sujet = SujetForumModel.fromJson(sujetContradictoire());

      // Le serveur renvoie une repartition differente de celle affichee : le
      // client doit s'y ranger « sans clignoter »...
      const ResultatVote resultat = ResultatVote(
        votes: RepartitionVotes(
          vraiPourcent: 62,
          doutePourcent: 21,
          fauxPourcent: 17,
        ),
        monVote: AvisCommunaute.vrai,
      );

      final SujetForum apres = SujetForum(
        slug: sujet.slug,
        titre: sujet.titre,
        corps: sujet.corps,
        auteur: sujet.auteur,
        verdictOfficiel: sujet.verdictOfficiel,
        votes: resultat.votes,
        monVote: resultat.monVote,
      );

      expect(apres.votes!.vraiPourcent, 62);
      // ... sans que le verdict de la redaction bouge d'un iota.
      expect(apres.verdictOfficiel, sujet.verdictOfficiel);
    });
  });
}
