import '../../data/models/api_enums.dart';
import '../entities/thread_entity.dart';

/// Le dépôt du forum.
///
/// POURQUOI une interface dans `domain/` et son implémentation dans `data/` :
/// c'est la règle de dépendance du chantier — les écrans ne connaissent que ce
/// contrat, jamais Dio, jamais Drift. Le jour où la lecture hors ligne change de
/// stratégie, aucun écran ne bouge.
///
/// **Aucune méthode ne prend de numéro de page.** `page` n'existe pas au contrat
/// (§3.1) : sur un fil qui bouge, « l'élément sauté peut être précisément le
/// démenti qu'on cherchait ».
abstract interface class ForumRepository {
  /// `GET /forum/threads`.
  ///
  /// [categorie] est un **slug de rubrique** ou la sentinelle `toutes` (§3.6).
  ///
  /// POURQUOI le filtre repart avec le curseur, à chaque page : « le curseur
  /// n'encode qu'une position dans l'ordre de tri. L'appel fautif ne renvoie
  /// pas d'erreur — il rend la page suivante de la liste **non filtrée** »
  /// (§3.4, arbitrage n° 82).
  ///
  /// POURQUOI `?q=` n'apparaît pas alors que le contrat le sert : **l'écran 20
  /// n'a pas de champ de recherche.** Le simulateur fait foi sur l'écran, et
  /// exposer un paramètre qu'aucune interface n'alimente produirait du code que
  /// personne n'appelle.
  Future<PageSujets> listerSujets({String? categorie, String? curseur});

  /// La première page telle qu'elle a été vue la dernière fois, ou `null`.
  ///
  /// POURQUOI cette méthode existe à côté de la précédente : le cycle de lecture
  /// du hors ligne est en quatre temps — « lire le cache et **afficher
  /// immédiatement**, lancer la requête en arrière-plan, rafraîchir si elle
  /// réussit, poser un bandeau d'horodatage si elle échoue » (05-HORS-LIGNE
  /// §5.4). Sans lecture séparée du cache, le premier temps est impossible et
  /// l'écran reste sur un squelette pendant tout l'aller-retour.
  ///
  /// [PageSujets.recupereLe] y est **toujours** renseigné : c'est ce qui
  /// alimente le bandeau.
  Future<PageSujets?> sujetsEnCache({String? categorie});

  /// `GET /forum/threads/{slug}`.
  Future<SujetForumDetail> lireSujet(String slug);

  /// Le sujet tel qu'il a été lu la dernière fois, ou `null`.
  Future<SujetForumDetail?> sujetEnCache(String slug);

  /// `POST /forum/threads`.
  ///
  /// POURQUOI le champ s'appelle `category` et non `categorie` dans le corps,
  /// par exception à la règle du corps français : « un client qui a appris
  /// `category` sur cinq écrans ne doit pas se tromper sur le sixième, d'autant
  /// qu'un champ mal nommé serait ignoré **en silence** » (arbitrage n° 81).
  ///
  /// **Ne part jamais en file d'attente.** Voir la réserve C.6 et l'entrée
  /// correspondante de `docs/ARBITRAGES.md` : la publication d'un sujet ne
  /// figure pas dans la liste fermée des actes que le contrat autorise à mettre
  /// en file, et la rejouer exposerait au doublon. Sans réseau, l'appel échoue
  /// et l'écran le dit.
  Future<SujetForumDetail> publierSujet({
    required String titre,
    required String corps,
    String? categorie,
    String? lienSignale,
  });

  /// `POST /forum/threads/{slug}/vote`.
  ///
  /// Rend **ce que le serveur recalcule**. L'écran a pu afficher autre chose
  /// entre-temps : c'est le vote optimiste, et « la réponse du serveur fait foi,
  /// l'unicité est en base ».
  ///
  /// Sans réseau, le vote part en file : l'unicité en base le rend rejouable
  /// sans clé d'idempotence. Le résultat rendu porte alors
  /// [ResultatVote.provisoire].
  Future<ResultatVote> voter(String slug, AvisCommunaute avis);

  /// `POST /forum/threads/{slug}/replies`.
  ///
  /// Un fil verrouillé répond `422 action_impossible` : le message du serveur
  /// s'affiche **tel quel**, sans reformulation.
  ///
  /// **Ne part jamais en file d'attente** — même raison que [publierSujet].
  Future<void> repondre(String slug, String corps);

  /// `POST /forum/threads/{slug}/report`.
  ///
  /// « Signaler deux fois ne crée qu'une ligne » : l'unicité est en base, donc
  /// l'appel est rejouable depuis la file hors ligne sans clé d'idempotence.
  Future<IssueEcriture> signalerSujet({
    required String slug,
    required MotifSignalement motif,
    String? detail,
  });
}
