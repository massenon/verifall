import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/theme/vf_theme.dart';
import 'package:verifall_app/domain/entities/tool_outil.dart';
import 'package:verifall_app/domain/entities/verification_entite.dart';
import 'package:verifall_app/domain/entities/verification_rapport.dart';
import 'package:verifall_app/domain/entities/verification_type_contenu.dart';
import 'package:verifall_app/domain/usecases/verify/drapeaux_de_fonctionnalite.dart';
import 'package:verifall_app/presentation/features/verify/widgets/report_blocks.dart';
import 'package:verifall_app/presentation/features/verify/widgets/verification_tile.dart';
import 'package:verifall_app/presentation/features/verify/widgets/verify_form_widgets.dart';
import 'package:verifall_app/presentation/shared/vf_verdict_badge.dart';

import 'support/verify_fakes.dart';

/// Monte un widget dans le thème réel, sans routeur ni réseau.
Widget _sous(Widget enfant) => ProviderScope(
  child: MaterialApp(
    theme: VfTheme.light(),
    home: Scaffold(body: SingleChildScrollView(child: enfant)),
  ),
);

void main() {
  group('La tuile d une vérification', () {
    testWidgets('un verdict rendu affiche LE LIBELLE DU SERVEUR', (
      WidgetTester tester,
    ) async {
      final Verification v = Verification.fromJson(
        verificationJson(rapport: rapportJson(verdictLibelle: 'Faux')),
      );
      await tester.pumpWidget(
        _sous(VerificationTile(verification: v, onTap: () {})),
      );

      // Le libelle vient du serveur : le client ne le fabrique jamais.
      expect(find.text('FAUX'), findsOneWidget);
      expect(find.text('92 % de confiance'), findsNothing);
      expect(find.textContaining('92 % de confiance'), findsOneWidget);
    });

    testWidgets('une analyse EN COURS affiche son statut, jamais un tiret', (
      WidgetTester tester,
    ) async {
      final Verification v = Verification.fromJson(
        verificationJson(
          statut: 'analyzing',
          statutLibelle: 'Analyse en cours',
          terminee: false,
          avancement: 33,
          rapport: null,
        ),
      );
      await tester.pumpWidget(
        _sous(VerificationTile(verification: v, onTap: () {})),
      );

      // « Un tiret laisserait croire a un echec » (arbitrage n. 31).
      expect(find.text('ANALYSE EN COURS'), findsOneWidget);
      expect(find.text('—'), findsNothing);
      // Et surtout : pas de « 0 % de confiance », qui serait un verdict
      // deguise.
      expect(find.textContaining('% de confiance'), findsNothing);
    });

    testWidgets('la tuile est un bouton entier', (WidgetTester tester) async {
      int touches = 0;
      final Verification v = Verification.fromJson(
        verificationJson(rapport: rapportJson()),
      );
      await tester.pumpWidget(
        _sous(VerificationTile(verification: v, onTap: () => touches++)),
      );
      await tester.tap(find.byType(VerificationTile));
      expect(touches, equals(1));
    });
  });

  group('L ecran de saisie', () {
    testWidgets('AUCUNE pastille de cout sur les tuiles de type', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        _sous(SelecteurDeType(choisi: TypeContenu.image, onChoisi: (_) {})),
      );

      // Le simulateur affiche « 1 pt », « 2 pts », « 5 pts ». Le bareme n'est
      // publie par AUCUN endpoint : l'afficher reviendrait a annoncer un prix
      // que le serveur ne pratique peut-etre pas.
      expect(find.textContaining('pt'), findsNothing);
      expect(find.text('Texte'), findsOneWidget);
      expect(find.text('Audio'), findsOneWidget);
    });

    testWidgets('l encart de cout annonce le SOLDE, pas un cout', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(_sous(const EncartDeCout(solde: 34)));
      expect(find.textContaining('Solde actuel : 34'), findsOneWidget);
      expect(find.textContaining('fixe par le serveur'), findsOneWidget);
    });

    testWidgets('un solde inconnu ne devient JAMAIS zero', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(_sous(const EncartDeCout(solde: null)));
      expect(find.textContaining('en cours de lecture'), findsOneWidget);
      expect(find.textContaining('Solde actuel : 0'), findsNothing);
    });

    testWidgets('la zone de depot porte le plafond REEL du contrat', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        _sous(
          ZoneDeDepot(type: TypeContenu.video, fichier: null, onChoisir: () {}),
        ),
      );
      // Le simulateur n'annonce RIEN pour la vidéo : « sans quoi quelqu'un
      // attend trois minutes pour se faire refuser ».
      expect(find.textContaining('100 Mo max'), findsOneWidget);
    });
  });

  group('Le rapport', () {
    testWidgets('la barre de fiabilite prend la couleur du verdict', (
      WidgetTester tester,
    ) async {
      final Verification v = Verification.fromJson(
        verificationJson(
          rapport: rapportJson(
            verdict: 'unverifiable',
            verdictLibelle: 'Non verifiable',
            confiance: 40,
          ),
        ),
      );
      await tester.pumpWidget(_sous(BlocDeVerdict(verification: v)));

      // « Une barre verte sous un non verifiable dit tout va bien la ou il n'y a
      // aucune assurance » (arbitrage n. 32). Le ton doit etre celui du verdict,
      // pas le vert par defaut.
      final VfVerdictBadge badge = tester.widget<VfVerdictBadge>(
        find.byType(VfVerdictBadge),
      );
      expect(badge.tone, equals(VfVerdictTone.enCours));
      expect(badge.libelle, equals('Non verifiable'));
    });

    testWidgets('le bloc « Pourquoi ce résultat ? » ne disparait JAMAIS', (
      WidgetTester tester,
    ) async {
      // Meme sans synthese redigee : « le bloc ne disparait jamais ».
      final VerificationRapport sansSynthese = VerificationRapport.fromJson(
        <String, Object?>{...rapportJson(), 'synthese': null},
      );
      await tester.pumpWidget(
        _sous(CartePourquoiCeResultat(rapport: sansSynthese)),
      );
      expect(find.textContaining('Pourquoi ce résultat'), findsOneWidget);
      expect(find.textContaining('n est pas disponible'), findsOneWidget);
    });

    testWidgets('la reserve DU SERVEUR est affichee telle quelle', (
      WidgetTester tester,
    ) async {
      // C'est elle qui porte « Vos unites vous sont rendues » : la reformuler
      // risquerait d'annoncer un remboursement qui n'a pas eu lieu.
      const String reserve =
          'Analyse partielle : un seul moteur a repondu. '
          'Vos unités vous sont rendues.';
      final VerificationRapport avecReserve = VerificationRapport.fromJson(
        <String, Object?>{...rapportJson(), 'reserve': reserve},
      );
      await tester.pumpWidget(
        _sous(CartePourquoiCeResultat(rapport: avecReserve)),
      );
      expect(find.text(reserve), findsOneWidget);
    });

    testWidgets('le bloc technique ne porte que des champs SERVIS', (
      WidgetTester tester,
    ) async {
      final VerificationRapport r = VerificationRapport.fromJson(rapportJson());
      await tester.pumpWidget(_sous(CartePourquoiCeResultat(rapport: r)));

      // Le simulateur y ajoute des `signals` inventes : les recopier
      // publierait des preuves qui n'ont pas ete consultees.
      expect(find.textContaining('"verdict": "false"'), findsOneWidget);
      expect(find.textContaining('signals'), findsNothing);
    });

    testWidgets(
      'la carte d indice de haine suit la MESURE, pas une phrase rassurante',
      (WidgetTester tester) async {
        final VerificationRapport eleve = VerificationRapport.fromJson(
          <String, Object?>{
            ...rapportJson(),
            'indice_haine': 62,
            'indice_haine_motifs': <String>['ciblage'],
            'indice_haine_note': 'Un ciblage discriminatoire a ete releve.',
          },
        );
        await tester.pumpWidget(_sous(CarteIndiceDeHaine(rapport: eleve)));

        expect(find.text('HSI 62 %'), findsOneWidget);
        // La phrase en dur du simulateur — « Aucun signal de rhetorique
        // hostile » — serait FAUSSE ici. C'est la note du serveur qui parle.
        expect(find.textContaining('Aucun signal'), findsNothing);
        expect(find.textContaining('ciblage discriminatoire'), findsOneWidget);
      },
    );
  });

  group('Les drapeaux de fonctionnalite', () {
    test(
      'les notifications et le retour d utilite sont BAISSES par defaut',
      () {
        // Les endpoints n'existent pas : « pas de boutons inertes, pas de merci
        // sans destinataire ».
        expect(DrapeauxM2.notifications, isFalse);
        expect(DrapeauxM2.retourDUtilite, isFalse);
      },
    );
  });

  group('L annuaire d outils', () {
    test('les catégories viennent du contenu, jamais d une liste ecrite', () {
      const AnnuaireOutils annuaire = AnnuaireOutils(
        outils: <Outil>[],
        categories: <String>['Recherche inversee', 'Archives'],
      );
      // « Proposer une categorie vide mene a une liste vide, et une liste vide
      // derriere un filtre passe pour un bug » (arbitrage n. 55).
      expect(annuaire.categories, hasLength(2));
      expect(annuaire.estVide, isTrue);
    });

    test('un outil se lit de la réponse réelle', () {
      final Outil o = Outil.fromJson(<String, Object?>{
        'slug': 'tineye',
        'titre': 'TinEye',
        'description': 'Recherche d image inversee.',
        'categorie': 'Recherche inversee',
        'etiquette': 'Freemium',
        'icone': 'image_search',
        'url': 'https://tineye.com',
        'libelle_du_lien': 'Ouvrir TinEye',
        'cas_d_usage': <String>[],
        'recommande': true,
      });
      // Le client n'ecrit AUCUNE adresse : il ouvre celle que l'API renvoie.
      expect(o.url, equals('https://tineye.com'));
      expect(o.etiquette, equals('Freemium'));
    });
  });
}
