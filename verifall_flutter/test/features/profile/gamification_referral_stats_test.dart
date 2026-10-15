import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/domain/entities/gamification_recompenses.dart';
import 'package:verifall_app/domain/entities/referral_parrainage.dart';
import 'package:verifall_app/domain/entities/stats_statistiques.dart';
import 'package:verifall_app/presentation/features/profile/providers/gamification_provider.dart';
import 'package:verifall_app/presentation/features/profile/providers/referral_provider.dart';
import 'package:verifall_app/presentation/features/profile/providers/stats_provider.dart';

import '_fixtures.dart';

/// Récompenses, parrainage, statistiques — les trois écrans où l'on fabriquerait
/// le plus facilement un chiffre.
void main() {
  late ServeurScripte serveur;
  late CacheEnMemoire cache;

  const String cleGamification = 'GET /me/gamification';
  const String cleCertificats = 'GET /me/certificates';
  const String cleParrainage = 'GET /me/referrals';
  const String cleInvite = 'POST /me/referrals/invite';
  const String cleStats = 'GET /me/stats';

  setUp(() {
    cache = CacheEnMemoire();
    serveur = ServeurScripte(<String, Object? Function(RequestOptions)>{
      cleGamification: (_) => reponseGamification(),
      cleCertificats: (_) => reponseCertificats(),
      cleParrainage: (_) => reponseParrainage(),
      cleStats: (_) => reponseStats(),
      cleInvite: (_) => <String, Object?>{
        'data': <String, Object?>{
          'code': 'RITA7X9',
          'lien': 'https://exemple.invalid/r/RITA7X9',
          'message': 'Partagez ce lien.',
        },
      },
    });
  });

  group('Récompenses', () {
    test('les badges verrouilles arrivent AVEC leur condition', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(gamificationProvider, (_, _) {});

      final EtatRecompenses etat = await conteneur.read(
        gamificationProvider.future,
      );

      final BadgeCitoyen verrouille = etat.recompenses.badges.firstWhere(
        (BadgeCitoyen b) => !b.debloque,
      );
      // « Un cadenas sans explication n'est qu'une frustration gratuite. »
      expect(verrouille.condition, isNotNull);
      expect(verrouille.condition, isNotEmpty);
      expect(etat.recompenses.badgesObtenus, 1);
      expect(etat.recompenses.badges.length, 2);
    });

    test('le record de série n apparaît QUE si l agregat le rend', () async {
      final ProviderContainer sansRecord = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(sansRecord.dispose);
      sansRecord.listen(gamificationProvider, (_, _) {});
      final EtatRecompenses etat = await sansRecord.read(
        gamificationProvider.future,
      );
      // Reserve D.3 : « si l'agregat le renvoie, la donnee est reelle et peut
      // s'afficher ; sinon, on ne l'invente pas ».
      expect(etat.recompenses.serie.record, isNull);

      final ServeurScripte avecRecord =
          ServeurScripte(<String, Object? Function(RequestOptions)>{
            cleGamification: (_) => reponseGamification(record: 12),
            cleCertificats: (_) => reponseCertificats(),
          });
      final ProviderContainer autre = conteneurDeTest(
        serveur: avecRecord,
        cache: CacheEnMemoire(),
      );
      addTearDown(autre.dispose);
      autre.listen(gamificationProvider, (_, _) {});
      final EtatRecompenses avec = await autre.read(
        gamificationProvider.future,
      );
      expect(avec.recompenses.serie.record, 12);
    });

    test('la prochaine étape n a aucune progression chiffree', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(gamificationProvider, (_, _) {});

      final EtatRecompenses etat = await conteneur.read(
        gamificationProvider.future,
      );
      final BadgeCitoyen? prochain = etat.recompenses.prochaineEtape;
      expect(prochain, isNotNull);
      expect(prochain!.debloque, isFalse);
      // L'API ne rend AUCUN couple fait/objectif : la barre du simulateur
      // serait alimentee par un compteur local, donc par un chiffre fabrique.
      expect(prochain.condition, isNotNull);
    });

    test('les certificats se cachent, et sont relisibles hors ligne', () async {
      final ProviderContainer premier = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      premier.listen(gamificationProvider, (_, _) {});
      await premier.read(gamificationProvider.future);
      premier.dispose();

      final ProviderContainer horsLigne = conteneurDeTest(
        serveur: ServeurScripte(<String, Object? Function(RequestOptions)>{}),
        cache: cache,
      );
      addTearDown(horsLigne.dispose);
      horsLigne.listen(gamificationProvider, (_, _) {});

      final EtatRecompenses etat = await horsLigne.read(
        gamificationProvider.future,
      );
      // « Montrer un certificat a un recruteur dans un bureau sans wifi » est
      // l'un des trois cas d'usage nommes par la documentation.
      expect(etat.certificats, hasLength(1));
      expect(etat.certificats.first.code, 'VA-2026-ABCD');
      expect(etat.certificatsIndisponibles, isFalse);
    });
  });

  group('Parrainage', () {
    test('le premier compteur mesure les OUVERTURES du lien', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(referralProvider, (_, _) {});

      final Parrainage parrainage = await conteneur.read(
        referralProvider.future,
      );
      // 8 ouvertures, 2 inscrits, 1 actif, 0 unite : c'est le cas exact que
      // l'ecran doit savoir expliquer (arbitrage n. 48).
      expect(parrainage.indicateurs.visites, 8);
      expect(parrainage.indicateurs.inscrits, 2);
      expect(parrainage.indicateurs.points, 0);
    });

    test(
      'les unités gagnees viennent de l API, pas d une multiplication',
      () async {
        final ServeurScripte autre = ServeurScripte(
          <String, Object? Function(RequestOptions)>{
            cleParrainage: (_) => reponseParrainage(points: 100),
          },
        );
        final ProviderContainer conteneur = conteneurDeTest(
          serveur: autre,
          cache: cache,
        );
        addTearDown(conteneur.dispose);
        conteneur.listen(referralProvider, (_, _) {});

        final Parrainage parrainage = await conteneur.read(
          referralProvider.future,
        );
        // 1 filleul actif, 100 unites : aucune multiplication locale ne
        // donnerait ce resultat, et c'est le serveur qui a raison.
        expect(parrainage.indicateurs.actifs, 1);
        expect(parrainage.indicateurs.points, 100);
      },
    );

    test('d un filleul, on ne connait que le prenom', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(referralProvider, (_, _) {});

      final Parrainage parrainage = await conteneur.read(
        referralProvider.future,
      );
      final Filleul filleul = parrainage.filleuls.single;
      expect(filleul.prenom, 'Koffi');
      expect(filleul.initiale, 'K');
      // Aucun etat « invite » : une ligne nait a l'INSCRIPTION du filleul.
      expect(filleul.statut, StatutFilleul.inscrit);
      expect(
        StatutFilleul.values.map((StatutFilleul s) => s.wireValue),
        isNot(contains('invited')),
      );
    });

    test('le code et le lien survivent au hors ligne', () async {
      final ProviderContainer premier = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      premier.listen(referralProvider, (_, _) {});
      await premier.read(referralProvider.future);
      premier.dispose();

      final ProviderContainer horsLigne = conteneurDeTest(
        serveur: ServeurScripte(<String, Object? Function(RequestOptions)>{}),
        cache: cache,
      );
      addTearDown(horsLigne.dispose);
      horsLigne.listen(referralProvider, (_, _) {});

      final Parrainage parrainage = await horsLigne.read(
        referralProvider.future,
      );
      // C'est toute la raison d'etre de ce cache : « montrer un code a quelqu'un
      // dans une cour d'ecole ne doit pas dependre du reseau ».
      expect(parrainage.code, 'RITA7X9');
      expect(parrainage.lien, 'https://exemple.invalid/r/RITA7X9');
    });

    test(
      'l ecran n appelle PAS invite a l ouverture — il ne compte rien',
      () async {
        final ProviderContainer conteneur = conteneurDeTest(
          serveur: serveur,
          cache: cache,
        );
        addTearDown(conteneur.dispose);
        conteneur.listen(referralProvider, (_, _) {});

        await conteneur.read(referralProvider.future);
        expect(serveur.comptePour(cleInvite), 0);

        // Il est appele AU GESTE de partage, pour le message redige par le
        // serveur.
        final Invitation invitation = await conteneur
            .read(referralProvider.notifier)
            .preparerLInvitation();
        expect(invitation.message, isNotNull);
        expect(serveur.comptePour(cleInvite), 1);
      },
    );
  });

  group('Statistiques', () {
    test('la periode part par son code de contrat', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(statsProvider(PeriodeStats.trenteJours), (_, _) {});

      await conteneur.read(statsProvider(PeriodeStats.trenteJours).future);

      expect(serveur.requetePour(cleStats).queryParameters['period'], '30d');
      // La liste est FERMEE : quatre valeurs, « et rien d'autre ».
      expect(PeriodeStats.values.map((PeriodeStats p) => p.wireValue), <String>[
        '7d',
        '30d',
        '90d',
        '365d',
      ]);
    });

    test('les jours a zero RESTENT dans la série', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(statsProvider(PeriodeStats.trenteJours), (_, _) {});

      final Statistiques stats = await conteneur.read(
        statsProvider(PeriodeStats.trenteJours).future,
      );
      // « Un graphique qui saute les jours vides ment sur la regularite, qui
      // est justement ce qu'il montre. »
      expect(stats.activite.valeurs, <int>[2, 0, 1]);
      expect(stats.activite.jours, hasLength(3));
    });

    test('tous les types sont representes, meme a zero', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(statsProvider(PeriodeStats.trenteJours), (_, _) {});

      final Statistiques stats = await conteneur.read(
        statsProvider(PeriodeStats.trenteJours).future,
      );
      expect(stats.types['video'], 0);
      // L'ordre du serveur est preserve : « un histogramme dont les colonnes
      // changent de place d'une visite a l'autre ne se lit pas ».
      expect(stats.types.keys.toList(), <String>[
        'text',
        'link',
        'image',
        'video',
        'audio',
      ]);
    });

    test('un compte neuf se distingue d une série a zero', () async {
      final ServeurScripte neuf = ServeurScripte(
        <String, Object? Function(RequestOptions)>{
          cleStats: (_) => reponseStats(verifications: 0),
        },
      );
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: neuf,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(statsProvider(PeriodeStats.trenteJours), (_, _) {});

      final Statistiques stats = await conteneur.read(
        statsProvider(PeriodeStats.trenteJours).future,
      );
      // « Un donut a zero et un histogramme plat sont indiscernables d'une
      // panne » : le compte neuf a son propre etat vide.
      expect(stats.compteNeuf, isTrue);
    });

    test(
      'venues du cache, les statistiques gardent leur date de calcul',
      () async {
        final ProviderContainer premier = conteneurDeTest(
          serveur: serveur,
          cache: cache,
        );
        premier.listen(statsProvider(PeriodeStats.trenteJours), (_, _) {});
        await premier.read(statsProvider(PeriodeStats.trenteJours).future);
        premier.dispose();

        final ProviderContainer horsLigne = conteneurDeTest(
          serveur: ServeurScripte(<String, Object? Function(RequestOptions)>{}),
          cache: cache,
        );
        addTearDown(horsLigne.dispose);
        horsLigne.listen(statsProvider(PeriodeStats.trenteJours), (_, _) {});

        final Statistiques stats = await horsLigne.read(
          statsProvider(PeriodeStats.trenteJours).future,
        );
        // La date du CACHE, pas celle d'aujourd'hui : « une statistique perimee
        // presentee comme actuelle est un indicateur faux ».
        expect(stats.calculeLe, DateTime(2026, 8, 25));
      },
    );
  });
}
