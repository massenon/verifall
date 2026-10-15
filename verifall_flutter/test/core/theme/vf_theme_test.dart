import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/theme/vf_colors.dart';
import 'package:verifall_app/core/theme/vf_metrics.dart';
import 'package:verifall_app/core/theme/vf_theme.dart';
import 'package:verifall_app/core/theme/vf_typography.dart';
import 'package:verifall_app/presentation/shared/shared.dart';

/// Les tests du thème.
///
/// POURQUOI on teste des constantes alors que §6.3 dit que « les constantes de
/// thème » ne méritent pas de test unitaire : ce qui est testé ici n'est pas la
/// valeur d'une constante, c'est **la transposition** — que le thème Material
/// rende bien les jetons du design system, et que rien n'ait été « proche ».
/// C'est un test de conformité au prototype, pas un test de valeur.
void main() {
  group('Jetons de couleur', () {
    test('la palette reprend exactement citizen.css', () {
      // Ces valeurs sont celles de la table de conversion (§2.1). Un test qui
      // echoue ici signale une couleur inventee, pas une regression.
      expect(VfColors.primary, const Color(0xFF09B850));
      expect(VfColors.primaryDark, const Color(0xFF079A43));
      expect(VfColors.primarySoft, const Color(0xFFE6F8EE));
      expect(VfColors.forest, const Color(0xFF063B2A));
      expect(VfColors.brandRed, const Color(0xFFD6293E));
      expect(VfColors.ink, const Color(0xFF111827));
      expect(VfColors.slate2, const Color(0xFF64748B));
      expect(VfColors.line, const Color(0xFFE5E7EB));
      expect(VfColors.canvas, const Color(0xFFF6F8F7));
      expect(VfColors.surface, const Color(0xFFFFFFFF));
      expect(VfColors.sidebarBg, const Color(0xFF1F2124));
      expect(VfColors.verdictTrue, const Color(0xFF16A34A));
      expect(VfColors.verdictFalse, const Color(0xFFDC2626));
      expect(VfColors.verdictInvestigate, const Color(0xFFD97706));
      expect(VfColors.info, const Color(0xFF2563EB));
      expect(VfColors.xp, const Color(0xFF7C3AED));
    });
  });

  group('Grille et cibles tactiles', () {
    test('les rayons sont ceux du design system', () {
      expect(VfRadii.sm, 10);
      expect(VfRadii.md, 12);
      expect(VfRadii.lg, 16);
      expect(VfRadii.sheetTop, 22);
    });

    test('les deux gouttieres de pile', () {
      expect(VfSpacing.stack, 14);
      expect(VfSpacing.stackSm, 9);
    });

    test('toutes les cibles tactiles atteignent 44', () {
      // « >= 44 partout, sans exception » (§2.4). Les seules valeurs inferieures
      // sont des elements DANS une cible plus grande (pastille de filtre a 38
      // avec sa gouttiere, petit bouton dans une carte) : elles sont exclues
      // explicitement plutot que tolerees en silence.
      expect(VfTouch.buttonMinHeight, greaterThanOrEqualTo(VfTouch.minimum));
      expect(VfTouch.iconButtonSize, greaterThanOrEqualTo(VfTouch.minimum));
      expect(VfTouch.menuRowMinHeight, greaterThanOrEqualTo(VfTouch.minimum));
      expect(VfTouch.navItemMinHeight, greaterThanOrEqualTo(VfTouch.minimum));
      expect(VfChrome.centerButtonSize, greaterThanOrEqualTo(VfTouch.minimum));
      expect(VfTouch.otpBoxHeight, greaterThanOrEqualTo(VfTouch.minimum));

      // La pastille de filtre fait 38, et sa gouttiere de 7 de chaque cote
      // porte la zone tactile reelle au-dela de 44.
      expect(
        VfTouch.chipHeight + VfTouch.chipGutter * 2,
        greaterThanOrEqualTo(VfTouch.minimum),
      );
    });
  });

  group('Typographie', () {
    test('les titres emploient Instrument Sans, le corps Inter', () {
      expect(VfTextStyles.hLg.fontFamily, VfFonts.display);
      expect(VfTextStyles.hMd.fontFamily, VfFonts.display);
      expect(VfTextStyles.hSm.fontFamily, VfFonts.display);
      expect(VfTextStyles.kpiValue.fontFamily, VfFonts.display);
      expect(VfTextStyles.body.fontFamily, VfFonts.body);
      expect(VfTextStyles.prose.fontFamily, VfFonts.body);
    });

    test(
      'la monospace ne sert qu a ce qui se recopie, en chiffres tabulaires',
      () {
        expect(VfTextStyles.mono.fontFamily, VfFonts.mono);
        expect(
          VfTextStyles.mono.fontFeatures,
          contains(const FontFeature.tabularFigures()),
        );
      },
    );

    test('l echelle typographique reprend les tailles du simulateur', () {
      expect(VfTextStyles.hLg.fontSize, 22);
      expect(VfTextStyles.hMd.fontSize, 17);
      expect(VfTextStyles.hSm.fontSize, 15);
      expect(VfTextStyles.body.fontSize, 15);
      expect(VfTextStyles.tMd.fontSize, 14.5);
      expect(VfTextStyles.tSm.fontSize, 13);
      expect(VfTextStyles.tXs.fontSize, 11.5);
      expect(VfTextStyles.kpiValue.fontSize, 24);
      expect(VfTextStyles.prose.fontSize, 14.5);
    });

    test('le sur-titre convertit .16em en 1.6 point d interlettrage', () {
      expect(VfTextStyles.eyebrow.fontSize, 10);
      expect(VfTextStyles.eyebrow.letterSpacing, closeTo(1.6, 0.001));
      expect(VfTextStyles.eyebrow.color, VfColors.primary);
    });
  });

  group('ThemeData', () {
    final ThemeData theme = VfTheme.light();

    test('le fond general est le canevas, pas une surface Material', () {
      expect(theme.scaffoldBackgroundColor, VfColors.canvas);
    });

    test('la carte n a ni elevation ni teinte de surface', () {
      // Material 3 assombrit une surface a mesure qu'elle s'eleve. Le design
      // system ne connait qu'un blanc, tenu par une bordure de 1.
      expect(theme.cardTheme.elevation, 0);
      expect(theme.cardTheme.surfaceTintColor, VfColors.surface);
    });

    test('la barre superieure est blanche, sans ombre, hauteur 52', () {
      expect(theme.appBarTheme.backgroundColor, VfColors.surface);
      expect(theme.appBarTheme.elevation, 0);
      expect(theme.appBarTheme.scrolledUnderElevation, 0);
      expect(theme.appBarTheme.toolbarHeight, VfChrome.appBarHeight);
    });

    test('la couleur primaire n est pas derivee, elle est imposee', () {
      expect(theme.colorScheme.primary, VfColors.primary);
      expect(theme.colorScheme.error, VfColors.brandRed);
      expect(theme.colorScheme.outline, VfColors.line);
    });

    test('le facteur d echelle du texte est borne a 2,0', () {
      // « les cartes doivent rester lisibles a 200 % » (§2.3).
      expect(VfTheme.maxTextScaleFactor, 2.0);
    });
  });

  group('VfVerdictBadge', () {
    // Test parametre sur TOUTES les valeurs (§6.3) : le back a ete mordu par un
    // `match` sans branche par defaut. Ici, chaque ton doit avoir sa couleur ET
    // son icone, et une valeur inattendue ne doit pas faire tomber l'ecran.
    for (final VfVerdictTone tone in VfVerdictTone.values) {
      testWidgets('le ton ${tone.name} a une couleur et une icone', (
        WidgetTester tester,
      ) async {
        await tester.pumpWidget(
          MaterialApp(
            theme: VfTheme.light(),
            home: Scaffold(
              body: VfVerdictBadge(libelle: 'Libelle serveur', tone: tone),
            ),
          ),
        );

        expect(find.text('LIBELLE SERVEUR'), findsOneWidget);
        final Icon icon = tester.widget<Icon>(find.byType(Icon));
        expect(icon.icon, tone.icon);
        expect(icon.color, tone.color);
      });
    }

    testWidgets('le libelle affiche vient du serveur, jamais du client', (
      WidgetTester tester,
    ) async {
      // Le contrat : « le client affiche le libelle et branche sur la valeur ;
      // il ne fabrique JAMAIS le libelle lui-meme » (§8). Un ton inconnu affiche
      // donc quand meme ce que le serveur a envoye.
      await tester.pumpWidget(
        MaterialApp(
          theme: VfTheme.light(),
          home: const Scaffold(
            body: VfVerdictBadge(
              libelle: 'Catégorie inedite',
              tone: VfVerdictTone.inconnu,
            ),
          ),
        ),
      );
      expect(find.text('CATÉGORIE INEDITE'), findsOneWidget);
    });
  });

  group('VfEmptyState', () {
    testWidgets('les deux etats vides sont distincts', (WidgetTester t) async {
      // « aucun resultat » et « rien encore fait » n'appellent pas la meme
      // action : les confondre revient a proposer « effacer les filtres » a
      // quelqu'un qui n'a jamais rien fait (§2.7).
      await t.pumpWidget(
        MaterialApp(
          theme: VfTheme.light(),
          home: const Scaffold(
            body: Column(
              children: <Widget>[
                Expanded(
                  child: VfEmptyState(
                    kind: VfEmptyKind.aucunResultat,
                    title: 'Aucun résultat',
                    message: 'Elargissez votre recherche.',
                  ),
                ),
                Expanded(
                  child: VfEmptyState(
                    kind: VfEmptyKind.rienEncore,
                    title: 'Rien encore',
                    message: 'Commencez ici.',
                  ),
                ),
              ],
            ),
          ),
        ),
      );

      expect(find.byIcon(Icons.search_off_rounded), findsOneWidget);
      expect(find.byIcon(Icons.inbox_outlined), findsOneWidget);
    });
  });

  group('VfButton', () {
    testWidgets('un bouton desactive reste lisible et ne reagit pas', (
      WidgetTester tester,
    ) async {
      int taps = 0;
      await tester.pumpWidget(
        MaterialApp(
          theme: VfTheme.light(),
          home: Scaffold(
            body: VfPrimaryButton(
              label: 'Envoyer',
              onPressed: null,
              semanticsLabel: 'Envoyer la vérification',
            ),
          ),
        ),
      );

      // Il reste VISIBLE : le simulateur ne masque jamais un bouton desactive.
      expect(find.text('Envoyer'), findsOneWidget);

      final Opacity opacity = tester.widget<Opacity>(find.byType(Opacity));
      expect(opacity.opacity, 0.45);

      await tester.tap(find.text('Envoyer'));
      await tester.pump();
      expect(taps, 0);
    });
  });

  group('VfPointsPill', () {
    testWidgets('un solde inconnu affiche un tiret, jamais zero', (
      WidgetTester tester,
    ) async {
      // Le solde ne se lit JAMAIS depuis le cache (contrat §7.1). Tant qu'il
      // n'a pas ete relu, un zero ferait croire a un compte vide.
      await tester.pumpWidget(
        MaterialApp(
          theme: VfTheme.light(),
          home: Scaffold(body: VfPointsPill(solde: null, onTap: () {})),
        ),
      );
      expect(find.text('—'), findsOneWidget);
      expect(find.text('0'), findsNothing);
    });
  });
}
