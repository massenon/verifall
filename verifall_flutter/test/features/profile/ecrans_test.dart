import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:verifall_app/core/theme/vf_theme.dart';
import 'package:verifall_app/presentation/features/profile/pages/account_being_deleted_page.dart';
import 'package:verifall_app/presentation/features/profile/pages/profile_page.dart';
import 'package:verifall_app/presentation/features/profile/pages/referral_page.dart';
import 'package:verifall_app/presentation/features/profile/pages/rewards_page.dart';
import 'package:verifall_app/presentation/features/profile/pages/settings_page.dart';
import 'package:verifall_app/presentation/features/profile/pages/units_page.dart';
import 'package:verifall_app/presentation/features/profile/widgets/rewards_widgets.dart';

import 'package:verifall_app/data/datasources/local/profile/compte_local_datasource.dart';
import 'package:verifall_app/data/models/profile/moi_model.dart';

import '_fixtures.dart';

/// Les six écrans, montés pour de vrai.
///
/// POURQUOI on monte les pages et pas seulement les providers : les décisions du
/// parcours — un tiret au lieu d'un zéro, un interrupteur retiré, un libellé qui
/// dit ce qu'il compte — sont **des faits d'écran**. Un test de provider ne les
/// verrait pas.
void main() {
  final TestWidgetsFlutterBinding binding =
      TestWidgetsFlutterBinding.ensureInitialized();

  late ServeurScripte serveur;
  late CacheEnMemoire cache;

  setUp(() {
    cache = CacheEnMemoire();
    serveur = ServeurScripte(<String, Object? Function(RequestOptions)>{
      'GET /me': (_) => reponseMoi(),
      'PATCH /me': (_) => reponseMoi(),
      'GET /me/stats': (_) => reponseStats(),
      'GET /me/gamification': (_) => reponseGamification(),
      'GET /me/certificates': (_) => reponseCertificats(),
      'GET /me/wallet': (_) => reponsePortefeuille(),
      'GET /me/wallet/transactions': (_) => reponseMouvements(),
      'GET /me/referrals': (_) => reponseParrainage(),
      'GET /me/settings': (_) => reponseReglages(),
    });
  });

  Future<void> monter(
    WidgetTester tester,
    Widget page, {
    bool horsLigne = false,
  }) async {
    // POURQUOI une surface aussi haute : un `ListView` ne construit que ce qui
    // est visible. Sur les 600 points de haut par defaut, le pied de l'ecran
    // Reglages, la liste des filleuls et celle des certificats ne seraient
    // jamais montes — et un test qui ne trouve rien passerait pour un test qui
    // a constate une absence.
    await binding.setSurfaceSize(const Size(430, 3200));
    addTearDown(() => binding.setSurfaceSize(null));

    final ProviderContainer conteneur = conteneurDeTest(
      serveur: serveur,
      cache: cache,
      horsLigne: horsLigne,
    );
    addTearDown(conteneur.dispose);
    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: conteneur,
        child: MaterialApp(theme: VfTheme.light(), home: page),
      ),
    );
    await tester.pumpAndSettle();
  }

  group('Ecran 25 · Mon profil', () {
    testWidgets('affiche le solde lu du serveur', (WidgetTester tester) async {
      await monter(tester, const ProfilePage());

      expect(find.text('Rita Ahouandjinou'), findsOneWidget);
      // La ligne « profession · ville » : le ROLE au sens RBAC n'est jamais
      // serialise, c'est la profession declaree qui s'affiche.
      expect(find.text('Journaliste · Cotonou'), findsOneWidget);
      expect(find.text('34'), findsWidgets);
    });

    testWidgets('sans réseau, le solde est un tiret — jamais un zero', (
      WidgetTester tester,
    ) async {
      // Le cache est rempli comme le depot le remplit : par la serialisation
      // qui N'ECRIT PAS la cle « unités ». Monter l'ecran deux fois de suite
      // aurait laisse un reessai reseau en attente derriere le premier arbre.
      await cache.ecrire(
        RessourceDeCompte.profil,
        MoiModel.versJson(
          MoiModel.depuisJson(
            reponseMoi()['data']! as Map<String, Object?>,
          ).sansSolde(),
        ),
      );
      serveur = ServeurScripte(<String, Object? Function(RequestOptions)>{});

      await monter(tester, const ProfilePage(), horsLigne: true);

      expect(find.text('Rita Ahouandjinou'), findsOneWidget);
      expect(find.text('34'), findsNothing);
      expect(find.text('—'), findsWidgets);
    });

    testWidgets('l adresse e-mail est affichee mais NON modifiable', (
      WidgetTester tester,
    ) async {
      await monter(tester, const ProfilePage());

      final Finder champ = find.widgetWithText(
        TextField,
        'rita@exemple.invalid',
      );
      expect(champ, findsOneWidget);
      expect(tester.widget<TextField>(champ).enabled, isFalse);
      // La mention dit ou la changer : le bloc reste a sa place, il ne ment
      // simplement pas sur ce qu'il permet.
      expect(find.textContaining('site Vérif All'), findsWidgets);
    });
  });

  group('Ecran 26 · Mes unités', () {
    testWidgets('la carte de solde n annonce aucun montant de dotation', (
      WidgetTester tester,
    ) async {
      await monter(tester, const UnitsPage());

      expect(find.text('SOLDE DISPONIBLE'), findsOneWidget);
      // Le « +10 unites offertes le 1er septembre » du simulateur est une
      // donnee de demonstration : aucun endpoint ne la sert.
      expect(find.textContaining('+10'), findsNothing);
      expect(find.text('Dotation mensuelle'), findsOneWidget);
      expect(find.textContaining('ajoutees chaque mois'), findsOneWidget);
    });

    testWidgets(
      'l encart de cout ne chiffre pas ce que le serveur ne chiffre pas',
      (WidgetTester tester) async {
        await monter(tester, const UnitsPage());
        expect(find.textContaining('1 unite,'), findsNothing);
        expect(find.textContaining('depend du type'), findsOneWidget);
      },
    );

    testWidgets('une ligne non facturee le dit', (WidgetTester tester) async {
      await monter(tester, const UnitsPage());
      expect(find.textContaining('Non facturee'), findsOneWidget);
    });
  });

  group('Ecran 27 · Récompenses', () {
    testWidgets('les badges verrouilles restent visibles a 55 %', (
      WidgetTester tester,
    ) async {
      await monter(tester, const RewardsPage());

      expect(find.text('Eclaireur'), findsWidgets);
      // La condition apparait DEUX fois : sur la tuile verrouillee et sur la
      // carte « Prochaine etape ». C'est voulu — la destination se montre.
      expect(find.text('Série de 7 jours'), findsWidgets);

      final Opacity opacite = tester.widget<Opacity>(
        find
            .ancestor(
              of: find.text('Eclaireur').last,
              matching: find.byType(Opacity),
            )
            .first,
      );
      expect(opacite.opacity, VfBadgeTile.opaciteVerrouillee);
    });

    testWidgets('un certificat n a AUCUN bouton de téléchargement PDF', (
      WidgetTester tester,
    ) async {
      await monter(tester, const RewardsPage());

      expect(find.text('Reconnaitre une intox'), findsOneWidget);
      // L'endpoint n'existe pas (contrat 11) : « un bouton qui echoue a
      // chaque pression est pire que son absence ».
      expect(find.textContaining('PDF'), findsNothing);
      expect(find.textContaining('Télécharger'), findsNothing);
    });
  });

  group('Ecran 29 · Parrainage', () {
    testWidgets('le compteur dit ce qu il compte : des OUVERTURES', (
      WidgetTester tester,
    ) async {
      await monter(tester, const ReferralPage());

      expect(find.text('Invitations ouvertes'), findsOneWidget);
      expect(find.text('Invitations envoyees'), findsNothing);
      // La phrase qui evite le faux bogue « 8 ouvertes, 0 unite ».
      expect(find.textContaining('ouvert votre lien'), findsOneWidget);
    });

    testWidgets('du filleul, seul le prenom est montre', (
      WidgetTester tester,
    ) async {
      await monter(tester, const ReferralPage());
      expect(find.text('Koffi'), findsOneWidget);
      expect(find.textContaining('@'), findsNothing);
    });

    testWidgets('le code est affiche en clair, et le QR est NATIF', (
      WidgetTester tester,
    ) async {
      await monter(tester, const ReferralPage());
      // « Le code en texte reste la voie principale — un QR n'est pas
      // accessible. »
      expect(find.text('RITA7X9'), findsOneWidget);
      // Genere sur le telephone, jamais telecharge : « il doit marcher hors
      // ligne, en presentiel ». `QrImageView` garde sa donnee en prive ; ce
      // qu'on verifie ici, c'est qu'AUCUNE image distante n'est demandee — et
      // le lien encode est deja verifie cote parrainage.
      expect(find.byType(QrImageView), findsOneWidget);
      expect(find.byType(Image), findsNothing);
    });
  });

  group('Ecran 30 · Réglages', () {
    testWidgets('l interrupteur de 2FA est RETIRE, pas desactive', (
      WidgetTester tester,
    ) async {
      await monter(tester, const SettingsPage());

      expect(find.textContaining('Double authentification'), findsNothing);
      // Le renvoi existe, lui : le reglage se fait sur le site.
      expect(find.textContaining('site Vérif All'), findsOneWidget);
    });

    testWidgets('aucun compteur d appareils connectes n est invente', (
      WidgetTester tester,
    ) async {
      await monter(tester, const SettingsPage());
      expect(find.textContaining('Appareils connectes'), findsNothing);
    });

    testWidgets('la lettre d information s annonce comme un consentement', (
      WidgetTester tester,
    ) async {
      await monter(tester, const SettingsPage());

      expect(find.text('Lettre d information'), findsOneWidget);
      expect(find.textContaining('Consentement enregistre'), findsOneWidget);
    });

    testWidgets('les canaux affiches sont ceux du serveur', (
      WidgetTester tester,
    ) async {
      await monter(tester, const SettingsPage());
      expect(find.text('Résultats de vérification'), findsOneWidget);
      expect(find.text('Activité du forum'), findsOneWidget);
    });

    testWidgets('la version en pied vient du paquet installe', (
      WidgetTester tester,
    ) async {
      await monter(tester, const SettingsPage());
      expect(
        find.text('Vérif All Mobile · version 1.0.0 (build 1)'),
        findsOneWidget,
      );
    });
  });

  group('Compte en cours de suppression', () {
    testWidgets(
      'annonce les trente jours ET dit que l annulation passe par le courriel',
      (WidgetTester tester) async {
        serveur.reponses['GET /me'] = (_) =>
            reponseMoi(suppressionDemandeeLe: '2026-08-20T09:00:00+00:00');

        await monter(tester, const AccountBeingDeletedPage());

        expect(find.text('Votre compte est ferme'), findsOneWidget);
        expect(find.textContaining('19/09/2026'), findsOneWidget);
        // Le point que nulle autre page ne dira.
        expect(
          find.textContaining('ne passe pas par l application'),
          findsOneWidget,
        );
        // Se deconnecter reste possible : c'est l'autre route encore servie.
        expect(find.text('Se déconnecter'), findsOneWidget);
      },
    );
  });
}
