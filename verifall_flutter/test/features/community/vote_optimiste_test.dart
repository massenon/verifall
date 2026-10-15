import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/errors/failure.dart';
import 'package:verifall_app/data/models/auteur.dart';
import 'package:verifall_app/data/repositories/community/community_data_providers.dart';
import 'package:verifall_app/domain/entities/thread_entity.dart';
import 'package:verifall_app/domain/usecases/community/voter_sur_un_sujet.dart';
import 'package:verifall_app/presentation/features/community/providers/forum_providers.dart';

import 'support/doublures_m4.dart';

/// **Le vote est optimiste, puis réconcilié.**
///
/// > « Le vote optimiste est sans danger : la réponse du serveur fait foi,
/// > l'unicité est en base. »
///
/// Et son corollaire pour le client : « accepter que le serveur lui renvoie des
/// pourcentages différents de ceux qu'il vient d'afficher, et se ranger sans
/// clignoter ».
void main() {
  const Auteur auteur = Auteur(
    uuid: 'a-1',
    nomAffichage: 'Aicha K.',
    initiales: 'AK',
  );

  SujetForum sujetDeBase() => const SujetForum(
    slug: 'la-video-du-vaccin',
    titre: 'Cette vidéo montre-t-elle un nouveau vaccin ?',
    corps: '<p>Elle circule depuis ce matin.</p>',
    auteur: auteur,
    votes: RepartitionVotes(
      vraiPourcent: 62,
      doutePourcent: 21,
      fauxPourcent: 17,
    ),
    verdictOfficiel: VerdictOfficiel(valeur: 'false', libelle: 'Faux'),
  );

  group('La projection optimiste', () {
    test('elle ne touche QUE mon vote, jamais les pourcentages', () {
      final SujetForum avant = sujetDeBase();
      final SujetForum apres = VoterSurUnSujet.projectionOptimiste(
        avant,
        AvisCommunaute.faux,
      );

      expect(apres.monVote, AvisCommunaute.faux);
      expect(
        apres.votes,
        avant.votes,
        reason:
            'Redistribuer les trois segments cote client afficherait un '
            'chiffre que personne n a mesure : data.votes est RECALCULE par '
            'le serveur, pas deduit.',
      );
    });
  });

  group('La reconciliation', () {
    test('la réponse du serveur ecrase la projection, sans clignoter', () {
      final SujetForum projete = VoterSurUnSujet.projectionOptimiste(
        sujetDeBase(),
        AvisCommunaute.faux,
      );

      const ResultatVote reponse = ResultatVote(
        // Le serveur renvoie tout autre chose : le client se range.
        votes: RepartitionVotes(
          vraiPourcent: 3,
          doutePourcent: 9,
          fauxPourcent: 88,
        ),
        monVote: AvisCommunaute.faux,
      );

      final SujetForum apres = VoterSurUnSujet.reconcilier(projete, reponse);
      expect(apres.votes!.fauxPourcent, 88);
      expect(apres.monVote, AvisCommunaute.faux);
    });

    test('un résultat sans repartition n efface pas le sondage', () {
      final SujetForum projete = VoterSurUnSujet.projectionOptimiste(
        sujetDeBase(),
        AvisCommunaute.doute,
      );
      const ResultatVote horsLigne = ResultatVote(
        votes: null,
        monVote: AvisCommunaute.doute,
        provisoire: true,
      );

      final SujetForum apres = VoterSurUnSujet.reconcilier(projete, horsLigne);
      expect(
        apres.votes!.vraiPourcent,
        62,
        reason:
            'Un vote parti en file n a rien recalcule. Effacer la repartition '
            'donnerait l impression que le sondage a disparu.',
      );
    });

    test('le verdict officiel traverse la reconciliation intact', () {
      final SujetForum apres = VoterSurUnSujet.reconcilier(
        sujetDeBase(),
        const ResultatVote(
          votes: RepartitionVotes(
            vraiPourcent: 100,
            doutePourcent: 0,
            fauxPourcent: 0,
          ),
          monVote: AvisCommunaute.vrai,
        ),
      );
      expect(apres.verdictOfficiel!.libelle, 'Faux');
      expect(apres.votes!.vraiPourcent, 100);
    });
  });

  group('Le notifier du sujet', () {
    test('il affiche le choix aussitot, puis se range', () async {
      final DepotForumDeTest depot = DepotForumDeTest(
        detail: SujetForumDetail(sujet: sujetDeBase()),
        resultatVote: const ResultatVote(
          votes: RepartitionVotes(
            vraiPourcent: 3,
            doutePourcent: 9,
            fauxPourcent: 88,
          ),
          monVote: AvisCommunaute.faux,
        ),
      );

      final ProviderContainer conteneur = ProviderContainer(
        // Le type `Override` n'est pas exporte par flutter_riverpod :
        // l'inference depuis le parametre fait le travail.
        overrides: [forumRepositoryProvider.overrideWithValue(depot)],
      );
      addTearDown(conteneur.dispose);

      final EtatSujet initial = await conteneur.read(
        sujetProvider('la-video-du-vaccin').future,
      );
      expect(initial.detail.sujet.monVote, isNull);

      await conteneur
          .read(sujetProvider('la-video-du-vaccin').notifier)
          .voter(AvisCommunaute.faux);

      final EtatSujet apres = conteneur
          .read(sujetProvider('la-video-du-vaccin'))
          .requireValue;
      expect(depot.votes, <AvisCommunaute>[AvisCommunaute.faux]);
      expect(apres.detail.sujet.monVote, AvisCommunaute.faux);
      expect(apres.detail.sujet.votes!.fauxPourcent, 88);
      expect(apres.voteProvisoire, isFalse);
    });

    test('un refus ramene l interface a l état serveur', () async {
      final DepotForumDeTest depot = DepotForumDeTest(
        detail: SujetForumDetail(sujet: sujetDeBase()),
        erreurEcriture: const ImpossibleActionFailure(
          serverMessage: 'Ce fil est ferme.',
        ),
      );

      final ProviderContainer conteneur = ProviderContainer(
        // Le type `Override` n'est pas exporte par flutter_riverpod :
        // l'inference depuis le parametre fait le travail.
        overrides: [forumRepositoryProvider.overrideWithValue(depot)],
      );
      addTearDown(conteneur.dispose);

      await conteneur.read(sujetProvider('s').future);

      await expectLater(
        conteneur.read(sujetProvider('s').notifier).voter(AvisCommunaute.vrai),
        throwsA(isA<ImpossibleActionFailure>()),
      );

      final EtatSujet apres = conteneur.read(sujetProvider('s')).requireValue;
      expect(
        apres.detail.sujet.monVote,
        isNull,
        reason:
            'Vote refuse : « l interface revient a l état serveur, avec le '
            'message recu ».',
      );
    });

    test('un vote mis en file est annonce comme provisoire', () async {
      final DepotForumDeTest depot = DepotForumDeTest(
        detail: SujetForumDetail(sujet: sujetDeBase()),
        resultatVote: const ResultatVote(
          votes: null,
          monVote: AvisCommunaute.doute,
          provisoire: true,
        ),
      );

      final ProviderContainer conteneur = ProviderContainer(
        // Le type `Override` n'est pas exporte par flutter_riverpod :
        // l'inference depuis le parametre fait le travail.
        overrides: [forumRepositoryProvider.overrideWithValue(depot)],
      );
      addTearDown(conteneur.dispose);

      await conteneur.read(sujetProvider('s').future);
      await conteneur
          .read(sujetProvider('s').notifier)
          .voter(AvisCommunaute.doute);

      final EtatSujet apres = conteneur.read(sujetProvider('s')).requireValue;
      expect(apres.voteProvisoire, isTrue);
      expect(apres.detail.sujet.votes!.vraiPourcent, 62);
    });
  });
}
