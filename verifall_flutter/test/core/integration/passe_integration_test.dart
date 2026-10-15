import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/config/app_config.dart';
import 'package:verifall_app/core/config/vf_web_links.dart';
import 'package:verifall_app/core/format/vf_formats.dart';
import 'package:verifall_app/core/network/local_data_purger.dart';
import 'package:verifall_app/core/state/riverpod_retry.dart';
import 'package:verifall_app/core/theme/vf_colors.dart';
import 'package:verifall_app/presentation/shared/shared.dart';

import '../../support/fakes.dart';

/// Les tests de la **passe d'intégration** : ce que le socle a repris aux cinq
/// parcours, et les défauts qu'aucun d'eux ne pouvait corriger seul.
///
/// POURQUOI ils sont regroupés : chacun verrouille une décision consignée dans
/// `docs/ARBITRAGES.md`, entrées 129 à 142. Un test qui échoue ici ne signale pas
/// une régression de fonctionnalité — il signale qu'une décision a été défaite.
void main() {
  group('Le bouton ne peut plus forcer une largeur infinie (n° 133)', () {
    testWidgets('deux boutons pleine largeur dans une Row se disposent', (
      WidgetTester tester,
    ) async {
      // LE DEFAUT, mot pour mot : « deux de ces boutons dans une Row produisent
      // une mise en page infinie — plantage a l'execution, invisible a
      // l'analyse ». Aucun `Expanded` ici : c'est tout l'objet du test.
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Row(
              children: <Widget>[
                VfGhostButton(label: 'Annuler', onPressed: () {}),
                VfPrimaryButton(label: 'Continuer', onPressed: () {}),
              ],
            ),
          ),
        ),
      );

      expect(tester.takeException(), isNull);
      expect(find.text('Annuler'), findsOneWidget);
      expect(find.text('Continuer'), findsOneWidget);
    });

    testWidgets('la correction ne casse pas les dimensions intrinseques', (
      WidgetTester tester,
    ) async {
      // La PREMIERE correction employait un `LayoutBuilder`, et quatorze tests
      // sont tombes : `IntrinsicHeight` ne peut pas mesurer a travers lui.
      // L'ecran de connexion de M1 en pose un.
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Expanded(
                    child: VfGhostButton(label: 'Retour', onPressed: () {}),
                  ),
                  Expanded(
                    child: VfPrimaryButton(label: 'Valider', onPressed: () {}),
                  ),
                ],
              ),
            ),
          ),
        ),
      );

      expect(tester.takeException(), isNull);
    });

    testWidgets('dans une largeur bornee, il prend TOUTE la largeur', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SizedBox(
              width: 300,
              child: VfPrimaryButton(label: 'Envoyer', onPressed: () {}),
            ),
          ),
        ),
      );

      expect(
        tester.getSize(find.byType(VfButton)).width,
        equals(300),
        reason: 'le defaut `expanded` doit rester un vrai plein-largeur',
      );
    });
  });

  group('Le verdict a UNE seule correspondance de ton (n° 131)', () {
    test('les quatre valeurs servies ont chacune leur ton', () {
      expect(VfVerdictTones.pour('true'), VfVerdictTone.vrai);
      expect(VfVerdictTones.pour('false'), VfVerdictTone.faux);
      expect(VfVerdictTones.pour('misleading'), VfVerdictTone.trompeur);
      // LE point ou les deux parcours divergeaient. Le serveur publie le bleu :
      // `VerdictFactCheck::couleurBarre()` rend #2563EB et `badge()` rend
      // `vbadge-progress`. C'est le libelle, servi par l'API, qui distingue
      // « Non verifiable » de « En cours ».
      expect(VfVerdictTones.pour('unverifiable'), VfVerdictTone.enCours);
      expect(VfVerdictTone.enCours.color, VfColors.info);
    });

    test('une valeur inattendue ne fait pas planter l ecran', () {
      expect(
        VfVerdictTones.pour('quelque_chose_de_neuf'),
        VfVerdictTone.inconnu,
      );
      expect(VfVerdictTones.pour(null), VfVerdictTone.inconnu);
    });

    test('la liste des valeurs servies est celle du serveur', () {
      expect(VfVerdictTones.valeursServies, <String>[
        'true',
        'false',
        'misleading',
        'unverifiable',
      ]);
    });
  });

  group('Les mises en forme sont uniques (n° 130)', () {
    test('la date, l heure et le bandeau', () {
      final DateTime le = DateTime(2026, 8, 20, 9, 30);
      expect(VfFormats.dateCourte(le), '20/08/2026');
      expect(VfFormats.heure(le), '09:30');
      expect(VfFormats.dateEtHeure(le), '20/08/2026 à 09:30');
      expect(
        VfFormats.bandeauDeFraicheur(le),
        'Données du 20/08/2026 à 09:30, actualisation impossible.',
      );
    });

    test('le bandeau porte l ANNEE, sans quoi un cache de six mois ment', () {
      expect(
        VfFormats.bandeauDeFraicheur(DateTime(2026, 2, 3, 8)),
        contains('2026'),
      );
    });

    test('sans date, le bandeau ne fabrique pas une date', () {
      expect(
        VfFormats.bandeauDeFraicheur(null),
        'Données non synchronisées, actualisation impossible.',
      );
    });

    test('les DEUX anciennetes sont conservees, et elles different', () {
      final DateTime maintenant = DateTime(2026, 8, 20, 12);
      final DateTime ilYAQuinzeJours = DateTime(2026, 8, 5, 12);

      // « Quand ce message a-t-il ete ecrit ? » veut une date passe une semaine.
      expect(
        VfFormats.anciennete(ilYAQuinzeJours, maintenant: maintenant),
        '05/08/2026',
      );
      // « A quand remonte ma derniere synchronisation ? » veut une duree,
      // toujours : c'est elle qui dit s'il faut se mefier de ce qu'on lit.
      expect(
        VfFormats.age(ilYAQuinzeJours, maintenant: maintenant),
        'il y a 15 jours',
      );
    });

    test('une taille est mesuree, jamais estimee', () {
      expect(VfFormats.octets(512), '512 o');
      expect(VfFormats.octets(2048), '2 Ko');
      expect(VfFormats.octets(1258291), '1,2 Mo');
    });
  });

  group('Le bandeau d horodatage est unique (n° 129)', () {
    testWidgets('les deux tons rendent la meme phrase', (
      WidgetTester tester,
    ) async {
      for (final VfStaleTone ton in VfStaleTone.values) {
        await tester.pumpWidget(
          MaterialApp(
            home: Scaffold(
              body: VfStaleDataBanner(
                lueLe: DateTime(2026, 8, 20, 9, 30),
                tone: ton,
              ),
            ),
          ),
        );
        expect(
          find.textContaining('Données du 20/08/2026 à 09:30'),
          findsOneWidget,
          reason: 'ton ${ton.name}',
        );
      }
    });

    testWidgets('la reprise est OPTIONNELLE : M3 n en avait pas', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: VfStaleDataBanner(lueLe: DateTime(2026, 8, 20))),
        ),
      );
      expect(find.text('Réessayer'), findsNothing);

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: VfStaleDataBanner(
              lueLe: DateTime(2026, 8, 20),
              onRetry: () {},
            ),
          ),
        ),
      );
      expect(find.text('Réessayer'), findsOneWidget);
    });
  });

  group('La couverture accepte une hauteur de maquette (n° 134)', () {
    testWidgets('112 par defaut, 86 en variante breve, libre sur demande', (
      WidgetTester tester,
    ) async {
      Future<double> hauteurDe(VfCover cover) async {
        await tester.pumpWidget(
          MaterialApp(
            home: Scaffold(body: Column(children: <Widget>[cover])),
          ),
        );
        return tester.getSize(find.byType(VfCover)).height;
      }

      expect(await hauteurDe(const VfCover(gradientKey: 'a')), 112);
      expect(await hauteurDe(const VfCover(gradientKey: 'a', brief: true)), 86);
      expect(
        await hauteurDe(const VfCover(gradientKey: 'a', height: 140)),
        140,
      );
    });
  });

  group(
    'La couverture distante choisit son decodeur par extension (audit Flutter/Laravel du 8 octobre 2026)',
    () {
      // LE DEFAUT : les couvertures de cours et d'articles servies par l'API
      // sont, de facon generalisee, des SVG (`assets/images/{courses,hub}/*.svg`).
      // `CachedNetworkImage` seul ne decode pas ce format : la couverture ne
      // s'affichait JAMAIS sur mobile, degradation silencieuse vers le
      // degrade de repli.
      //
      // Verrouille la DECISION de routage (`estSvgPourCouverture`), pas le
      // rendu complet : `TestWidgetsFlutterBinding` fausse tout HTTP en 400,
      // et un widget test qui pousserait un vrai `SvgPicture.network`/
      // `CachedNetworkImage` jusqu'au decodage echouerait sur une erreur
      // async sans rapport avec ce qui est verrouille ici.
      test(
        'une adresse .svg est detectee, meme avec requete ou majuscules',
        () {
          expect(
            estSvgPourCouverture(
              'https://verifall.com/assets/images/courses/placeholder-e.svg',
            ),
            isTrue,
          );
          expect(
            estSvgPourCouverture(
              'https://verifall.com/assets/images/hub/hub-news-1.SVG?v=2',
            ),
            isTrue,
          );
        },
      );

      test('une adresse raster ou illisible n est pas prise pour du SVG', () {
        expect(
          estSvgPourCouverture(
            'https://verifall.com/storage/courses/photo.jpg',
          ),
          isFalse,
        );
        expect(estSvgPourCouverture('pas une adresse'), isFalse);
      });
    },
  );

  group('Le reessai des providers est tranche une fois (n° 132)', () {
    test('la politique du socle ne rejoue JAMAIS', () {
      // Riverpod 3 rejouerait avec un recul exponentiel. La reprise du produit
      // vit dans les intercepteurs ; une seconde couche multiplierait les
      // appels — cinq lectures de `/me/wallet` la ou on en attendait une.
      for (int tentative = 0; tentative < 5; tentative++) {
        expect(
          sansReessaiAutomatique(tentative, Exception('peu importe')),
          isNull,
        );
      }
    });
  });

  group('Les liens du site public (n° 138)', () {
    test('sans adresse configuree, aucun lien n est fabrique', () {
      final AppConfig config = testConfig();
      expect(VfWebLinks.confidentialite(config), isNull);
      expect(VfWebLinks.verificationDeCertificat(config, 'ABCD-1234'), isNull);
    });

    test('avec adresse, les chemins sont ceux que le SERVEUR sert', () {
      final AppConfig config = testConfig(webBaseUrl: 'https://verifall.test');

      // `routes/web.php` : `/verifier-un-certificat` et `/confidentialite`. Le
      // simulateur ecrivait `verify-certificate`, qui n'existe pas.
      expect(
        VfWebLinks.verificationDeCertificat(config, 'ABCD-1234').toString(),
        'https://verifall.test/verifier-un-certificat?code=ABCD-1234',
      );
      expect(
        VfWebLinks.confidentialite(config).toString(),
        'https://verifall.test/confidentialite',
      );
    });

    test('une adresse illisible ne produit pas un lien a moitie', () {
      final AppConfig config = testConfig(webBaseUrl: 'pas-une-adresse');
      expect(VfWebLinks.confidentialite(config), isNull);
    });
  });

  group('La purge couvre les medias copies (n° 140)', () {
    test('le nom du dossier a UNE seule source', () {
      // M2 ecrit les copies, le socle les efface. Deux constantes ecrites
      // separement auraient fini par diverger, et la moitie oubliee d'une purge
      // est une fuite sur un telephone partage.
      expect(dossierDesMediasEnFile, 'medias');
    });

    test('le dossier de medias est efface, avec son contenu', () async {
      final Directory support = Directory.systemTemp.createTempSync(
        'vf-purge-',
      );
      addTearDown(() {
        if (support.existsSync()) {
          support.deleteSync(recursive: true);
        }
      });
      final Directory medias = Directory(
        '${support.path}${Platform.pathSeparator}$dossierDesMediasEnFile',
      )..createSync(recursive: true);
      File(
        '${medias.path}${Platform.pathSeparator}une-photo.jpg',
      ).writeAsStringSync('contenu');

      await PurgeDeTest(support).purgeAll();

      expect(
        medias.existsSync(),
        isFalse,
        reason: 'une copie locale oubliee est lisible par le compte suivant',
      );
    });
  });

  group('Le tiroir a enfin un hote (n° 137)', () {
    testWidgets('les quatre groupes du §2.5 sont la, et rien de plus', (
      WidgetTester tester,
    ) async {
      // Une surface haute : le tiroir porte quatorze entrees, et la fenetre de
      // test par defaut en laisserait la moitie hors de l'arbre.
      tester.view.physicalSize = const Size(420, 1600);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);

      await tester.pumpWidget(
        const ProviderScope(
          child: MaterialApp(
            home: Scaffold(drawer: VfAppDrawer(), body: Text('.')),
          ),
        ),
      );
      final ScaffoldState scaffold = tester.state(find.byType(Scaffold));
      scaffold.openDrawer();
      await tester.pumpAndSettle();

      for (final String titre in <String>[
        'APPRENTISSAGE',
        'COMMUNAUTÉ',
        'MON COMPTE',
      ]) {
        expect(find.text(titre), findsOneWidget, reason: titre);
      }
      for (final String entree in <String>[
        'Tableau de bord',
        'Vérifier un contenu',
        'Mes vérifications',
        'Liste des outils',
        'Catalogue de cours',
        'Live Classes',
        'Certificats & badges',
        'Forum',
        'Hub opportunités',
        'Mon parrainage',
        'Mes unités',
        'Mes statistiques',
        'Mon profil',
        'Réglages',
      ]) {
        expect(find.text(entree), findsOneWidget, reason: entree);
      }
      expect(find.text('Déconnexion'), findsOneWidget);
    });

    testWidgets('sans profil lu, il n INVENTE ni nom ni role ni XP', (
      WidgetTester tester,
    ) async {
      // Une session restauree du stockage securise porte le jeton, pas le
      // profil : « ne pas le fabriquer — c'est `GET /me` qui le rend ».
      await tester.pumpWidget(
        const ProviderScope(
          child: MaterialApp(
            home: Scaffold(drawer: VfAppDrawer(), body: Text('.')),
          ),
        ),
      );
      final ScaffoldState scaffold = tester.state(find.byType(Scaffold));
      scaffold.openDrawer();
      await tester.pumpAndSettle();

      expect(find.text('Mon compte'), findsOneWidget);
      expect(find.textContaining(' XP'), findsNothing);
    });

    testWidgets('hors de la coque, le bouton de tiroir n est PAS pose', (
      WidgetTester tester,
    ) async {
      // « Un bouton qui n'ouvre rien serait pire qu'un bouton absent » : c'est
      // l'argument que M4 avait employe pour le retirer, et il tient toujours
      // pour les ecrans hors coque — rapport, lecon, certificat.
      late bool dansLaCoque;
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (BuildContext context) {
              dansLaCoque = VfShellScope.isInsideShell(context);
              return const SizedBox.shrink();
            },
          ),
        ),
      );
      expect(dansLaCoque, isFalse);
    });
  });
}

/// La purge, sans la base : le dossier de médias est ce qu'on vérifie ici.
///
/// POURQUOI une doublure plutôt que `DriftLocalDataPurger` : ouvrir une base
/// Drift demande la bibliothèque native SQLite, « qui n'est pas forcément
/// installée sur la machine hôte » — c'est la raison même pour laquelle
/// `PendingOpsStore` existe au socle.
class PurgeDeTest implements LocalDataPurger {
  PurgeDeTest(this.support);

  final Directory support;

  @override
  Future<void> purgeAll() async {
    final Directory medias = Directory(
      '${support.path}${Platform.pathSeparator}$dossierDesMediasEnFile',
    );
    if (medias.existsSync()) {
      await medias.delete(recursive: true);
    }
  }
}
