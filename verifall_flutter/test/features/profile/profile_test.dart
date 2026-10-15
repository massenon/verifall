import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/core/network/auth_controller.dart';
import 'package:verifall_app/data/datasources/local/profile/compte_local_datasource.dart';
import 'package:verifall_app/domain/entities/profile_moi.dart';
import 'package:verifall_app/presentation/features/profile/providers/profile_provider.dart';

import '_fixtures.dart';

/// Mon profil — et la règle qui compte : le solde n'entre jamais en cache.
void main() {
  late ServeurScripte serveur;
  late CacheEnMemoire cache;

  const String cleMoi = 'GET /me';
  const String cleMaj = 'PATCH /me';
  const String cleStats = 'GET /me/stats';
  const String cleGamification = 'GET /me/gamification';

  setUp(() {
    cache = CacheEnMemoire();
    serveur = ServeurScripte(<String, Object? Function(RequestOptions)>{
      cleMoi: (_) => reponseMoi(),
      cleStats: (_) => reponseStats(),
      cleGamification: (_) => reponseGamification(),
      cleMaj: (_) => reponseMoi(),
    });
  });

  group('Le solde n entre jamais en cache', () {
    test('la charge utile ecrite ne porte pas la cle « unités »', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(profileProvider, (_, _) {});

      await conteneur.read(profileProvider.future);

      final Object? ecrit = cache.contenus[RessourceDeCompte.profil];
      expect(ecrit, isA<Map<String, Object?>>());
      // La garantie est MECANIQUE : la cle n'est pas ecrite, donc elle ne peut
      // pas etre relue (contrat 7.1).
      expect((ecrit! as Map<String, Object?>).containsKey('unites'), isFalse);
    });

    test('relu du cache, le profil porte un solde NUL', () async {
      final ProviderContainer premier = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      premier.listen(profileProvider, (_, _) {});
      await premier.read(profileProvider.future);
      premier.dispose();

      // Deuxieme ouverture, serveur muet : c'est le cas hors ligne.
      final ProviderContainer horsLigne = conteneurDeTest(
        serveur: ServeurScripte(<String, Object? Function(RequestOptions)>{}),
        cache: cache,
      );
      addTearDown(horsLigne.dispose);
      horsLigne.listen(profileProvider, (_, _) {});

      final EtatProfil etat = await horsLigne.read(profileProvider.future);
      expect(etat.moi.nomAffichage, 'Rita Ahouandjinou');
      // Un tiret, jamais un zero : « un zero ferait croire a un compte vide ».
      expect(etat.solde, isNull);
      expect(etat.vientDuCache, isTrue);
    });
  });

  group('L enregistrement du profil', () {
    test('n envoie QUE les champs modifies', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(profileProvider, (_, _) {});

      await conteneur.read(profileProvider.future);
      await conteneur
          .read(profileProvider.notifier)
          .enregistrer(const ModificationProfil(ville: 'Porto-Novo'));

      // `PATCH` veut dire « ne change QUE ce que j'envoie » : envoyer les six
      // champs effacerait la profession de quelqu'un qui n'a change que sa ville.
      expect(serveur.corpsDe(cleMaj), <String, Object?>{'ville': 'Porto-Novo'});
    });

    test('l adresse e-mail ne peut PAS partir', () {
      // Elle n'existe pas dans le type : ce qui n'est pas exprimable ne peut pas
      // etre envoye par distraction (arbitrage n. 6).
      const ModificationProfil modification = ModificationProfil(
        prenom: 'Rita',
        nom: 'A.',
        telephone: '97000000',
        profession: 'Journaliste',
        ville: 'Cotonou',
        bio: 'Bio',
      );
      expect(modification.versCorps().containsKey('email'), isFalse);
    });

    test('un nom vide est refuse avant l envoi', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(profileProvider, (_, _) {});
      await conteneur.read(profileProvider.future);

      await expectLater(
        conteneur
            .read(profileProvider.notifier)
            .enregistrer(const ModificationProfil(prenom: '   ')),
        throwsA(isA<ValidationFailure>()),
      );
      expect(serveur.comptePour(cleMaj), 0);
    });
  });

  group('Le compte en cours de suppression', () {
    test('GET /me alimente l état de session, sans mur d erreur', () async {
      serveur.reponses[cleMoi] = (_) =>
          reponseMoi(suppressionDemandeeLe: '2026-08-20T09:00:00+00:00');

      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(profileProvider, (_, _) {});

      final EtatProfil etat = await conteneur.read(profileProvider.future);

      expect(etat.moi.estEnSuppression, isTrue);
      // Trente jours plus tard : la regle est ecrite au contrat, la date de
      // la demande vient du serveur.
      expect(
        etat.moi.effacementPrevuLe,
        DateTime.parse('2026-09-19T09:00:00+00:00'),
      );
      // C'est ICI que l'information entre dans l'application : le routeur
      // s'en sert pour aiguiller, une seule fois, au lieu de trente ecrans.
      expect(
        conteneur.read(authControllerProvider).suppressionDemandeeLe,
        isNotNull,
      );
    });
  });

  test('les compteurs absents de GET /me valent « rien », pas zero', () async {
    // Les deux endpoints d'appoint sont muets : l'ecran doit afficher un tiret.
    final ServeurScripte partiel = ServeurScripte(
      <String, Object? Function(RequestOptions)>{cleMoi: (_) => reponseMoi()},
    );
    final ProviderContainer conteneur = conteneurDeTest(
      serveur: partiel,
      cache: cache,
    );
    addTearDown(conteneur.dispose);
    conteneur.listen(profileProvider, (_, _) {});

    final EtatProfil etat = await conteneur.read(profileProvider.future);
    expect(etat.verifications, isNull);
    expect(etat.badges, isNull);
    expect(etat.niveau, isNull);
    // Le profil, lui, a bien ete lu : un appoint manquant ne fait pas echouer
    // l'ecran.
    expect(etat.moi.uuid, 'uuid-du-compte');
  });
}
