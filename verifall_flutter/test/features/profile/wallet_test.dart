import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/data/datasources/local/profile/compte_local_datasource.dart';
import 'package:verifall_app/domain/entities/wallet_portefeuille.dart';
import 'package:verifall_app/domain/usecases/profile/recharger_des_unites.dart';
import 'package:verifall_app/presentation/features/profile/providers/profile_dependencies.dart';
import 'package:verifall_app/presentation/features/profile/providers/wallet_provider.dart';

import '_fixtures.dart';

/// Le portefeuille — l'écran où une erreur coûte de l'argent à quelqu'un.
void main() {
  late ServeurScripte serveur;
  late CacheEnMemoire cache;

  const String cleWallet = 'GET /me/wallet';
  const String cleTopup = 'POST /me/wallet/topup';
  const String cleMouvements = 'GET /me/wallet/transactions';

  setUp(() {
    cache = CacheEnMemoire();
    serveur = ServeurScripte(<String, Object? Function(RequestOptions)>{
      cleWallet: (_) => reponsePortefeuille(),
      cleMouvements: (_) => reponseMouvements(),
      cleTopup: (_) => <String, Object?>{
        'data': <String, Object?>{
          'uuid': 'paiement-1',
          'statut': 'pending',
          'statut_libelle': 'En attente de confirmation',
          'operateur': 'mtn',
          'montant_fcfa': 4000,
          'unites': 50,
        },
      },
    });
    serveur.statuts[cleTopup] = 201;
  });

  group('La recharge n envoie que ce que le contrat accepte', () {
    test('le corps ne porte QUE pack et numéro', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);

      await conteneur.read(rechargerDesUnitesProvider)(
        codePack: 'pack-50',
        numero: '97 00 00 00',
      );

      final Map<String, Object?> corps = serveur.corpsDe(cleTopup);
      expect(corps.keys.toSet(), <String>{'pack', 'numero'});
      expect(corps['pack'], 'pack-50');
      // Les espaces sont retires : un numero dicte se recopie avec des espaces.
      expect(corps['numero'], '97000000');
      // Ni montant, ni quantite, ni operateur : « un client modifie qui
      // enverrait 99 999 unites n'obtient rien de plus », et « l'operateur est
      // deduit du numero par le serveur » (reserve C.7).
      expect(corps.containsKey('montant_fcfa'), isFalse);
      expect(corps.containsKey('unites'), isFalse);
      expect(corps.containsKey('operateur'), isFalse);
    });

    test('un numéro trop court est refuse avant l envoi', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);

      await expectLater(
        conteneur.read(rechargerDesUnitesProvider)(
          codePack: 'pack-50',
          numero: '970',
        ),
        throwsA(isA<ValidationFailure>()),
      );
      expect(serveur.comptePour(cleTopup), 0);
    });

    test('hors ligne, la recharge est refusee AVEC sa raison', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
        horsLigne: true,
      );
      addTearDown(conteneur.dispose);

      await expectLater(
        conteneur.read(rechargerDesUnitesProvider)(
          codePack: 'pack-50',
          numero: '97000000',
        ),
        throwsA(
          isA<ImpossibleActionFailure>().having(
            (ImpossibleActionFailure e) => e.serverMessage,
            'la raison',
            messageRechargeHorsLigne,
          ),
        ),
      );
      // Aucun paiement ne part, et surtout aucun ne se met en file.
      expect(serveur.comptePour(cleTopup), 0);
    });
  });

  group('Le client ne credite jamais', () {
    test('après une recharge, le solde est RELU et non additionne', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(walletProvider, (_, _) {});

      await conteneur.read(walletProvider.future);
      expect(conteneur.read(walletProvider).value!.solde, 34);

      final DemandeRecharge demande = await conteneur.read(
        rechargerDesUnitesProvider,
      )(codePack: 'pack-50', numero: '97000000');

      await conteneur.read(walletProvider.notifier).demandeOuverte(demande);

      // Le serveur rend toujours 34 : le solde N'A PAS BOUGE cote client,
      // meme si la demande porte 50 unites. « Il ne bougera qu'au rappel
      // signe de l'operateur. »
      expect(conteneur.read(walletProvider).value!.solde, 34);
      // Et il a bien ete RELU : c'est le seul moyen de refleter un credit
      // arrive pendant qu'on etait chez l'operateur.
      expect(serveur.comptePour(cleWallet), greaterThanOrEqualTo(2));
    });

    test(
      'une demande sans retour affiche l attente, jamais un échec',
      () async {
        final ProviderContainer conteneur = conteneurDeTest(
          serveur: serveur,
          cache: cache,
        );
        addTearDown(conteneur.dispose);
        conteneur.listen(walletProvider, (_, _) {});

        await conteneur.read(walletProvider.future);
        final DemandeRecharge demande = await conteneur.read(
          rechargerDesUnitesProvider,
        )(codePack: 'pack-50', numero: '97000000');
        await conteneur.read(walletProvider.notifier).demandeOuverte(demande);

        final EtatPortefeuille etat = conteneur.read(walletProvider).value!;
        expect(etat.demandeEnAttente, isNotNull);
        expect(
          etat.demandeEnAttente!.statutLibelle,
          'En attente de confirmation',
        );
        // L'état reste une DONNEE, pas une erreur : rien n'a echoue.
        expect(conteneur.read(walletProvider).hasError, isFalse);
      },
    );
  });

  group('Le solde ne vient jamais du cache', () {
    test('aucune ecriture de cache après lecture du portefeuille', () async {
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: serveur,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(walletProvider, (_, _) {});

      await conteneur.read(walletProvider.future);

      // Le cache est intact : ni solde, ni grille tarifaire, ni releve. Et
      // l'enumeration des ressources cachables ne comporte meme pas de valeur
      // pour le portefeuille — c'est mecanique, pas declaratif.
      expect(cache.contenus, isEmpty);
      expect(
        RessourceDeCompte.values.map((RessourceDeCompte r) => r.cle),
        isNot(contains('portefeuille')),
      );
    });

    test('sans réseau, l ecran n a rien a montrer et le dit', () async {
      final ServeurScripte muet = ServeurScripte(
        <String, Object? Function(RequestOptions)>{},
      );
      final ProviderContainer conteneur = conteneurDeTest(
        serveur: muet,
        cache: cache,
      );
      addTearDown(conteneur.dispose);
      conteneur.listen(walletProvider, (_, _) {});

      await expectLater(
        conteneur.read(walletProvider.future),
        throwsA(isA<Failure>()),
      );
    });
  });

  test('une ligne a zero est lisible comme « non facturee »', () async {
    final ProviderContainer conteneur = conteneurDeTest(
      serveur: serveur,
      cache: cache,
    );
    addTearDown(conteneur.dispose);
    conteneur.listen(walletProvider, (_, _) {});

    final EtatPortefeuille etat = await conteneur.read(walletProvider.future);
    final MouvementUnite gratuite = etat.mouvements.last;
    expect(gratuite.delta, 0);
    // Arbitrage n. 25 : sans cette mention, une gratuite passerait pour un
    // bogue.
    expect(gratuite.estNonFacturee, isTrue);
  });

  test('le releve est demande PAR CURSEUR, jamais par page', () async {
    final ProviderContainer conteneur = conteneurDeTest(
      serveur: serveur,
      cache: cache,
    );
    addTearDown(conteneur.dispose);
    conteneur.listen(walletProvider, (_, _) {});

    await conteneur.read(walletProvider.future);

    final Map<String, dynamic> parametres = serveur
        .requetePour(cleMouvements)
        .queryParameters;
    expect(parametres.containsKey('page'), isFalse);
    expect(parametres['limit'], isNotNull);
  });
}
