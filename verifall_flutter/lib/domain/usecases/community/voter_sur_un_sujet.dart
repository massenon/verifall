import '../../entities/thread_entity.dart';
import '../../repositories/forum_repository.dart';

/// Voter sur un sujet — **optimiste, puis réconcilié**.
///
/// # Ce que ce cas d'usage garantit, et que l'écran ne doit pas refaire
///
/// 1. L'état affiché change **tout de suite** ([projectionOptimiste]) ;
/// 2. la réponse du serveur **écrase** cette projection ([reconcilier]).
///
/// Le contrat autorise l'optimisme et dit pourquoi : « le vote optimiste est
/// sans danger : la réponse du serveur fait foi, l'unicité est en base ». Et il
/// en tire une obligation pour le client : « accepter que le serveur renvoie des
/// pourcentages différents de ceux qu'il vient d'afficher, et se ranger sans
/// clignoter ».
///
/// # Ce qui n'est PAS ici, et pourquoi
///
/// Aucun calcul de pourcentage. La projection optimiste ne touche **que** le
/// bouton sélectionné ; elle ne redistribue pas les trois segments. Recalculer
/// une répartition côté client afficherait un chiffre que personne n'a mesuré —
/// et `data.votes` est « recalculé par le serveur », pas déduit.
class VoterSurUnSujet {
  const VoterSurUnSujet(this._depot);

  final ForumRepository _depot;

  /// Ce que l'écran affiche **avant** la réponse du serveur.
  ///
  /// Seul `mon_vote` bouge : la barre garde ses pourcentages jusqu'à ce que le
  /// serveur en donne de nouveaux.
  static SujetForum projectionOptimiste(
    SujetForum sujet,
    AvisCommunaute avis,
  ) => sujet.copyWith(monVote: avis);

  /// Ce que l'écran affiche **après**. La réponse du serveur fait foi.
  ///
  /// POURQUOI [ResultatVote.votes] à `null` ne remet pas la barre à zéro : un
  /// vote parti en file n'a rien recalculé du tout. Effacer la répartition
  /// donnerait l'impression que le sondage a disparu.
  static SujetForum reconcilier(SujetForum sujet, ResultatVote resultat) {
    return SujetForum(
      slug: sujet.slug,
      titre: sujet.titre,
      corps: sujet.corps,
      auteur: sujet.auteur,
      categorieSlug: sujet.categorieSlug,
      categorieLibelle: sujet.categorieLibelle,
      publieLe: sujet.publieLe,
      votes: resultat.votes ?? sujet.votes,
      monVote: resultat.monVote ?? sujet.monVote,
      // LE VERDICT OFFICIEL N'EST PAS TOUCHE PAR UN VOTE. C'est l'arbitrage
      // n° 43 : un sondage ne fait pas une verification, et rien de ce que la
      // communaute clique ne doit pouvoir modifier ce champ.
      verdictOfficiel: sujet.verdictOfficiel,
      nbReponses: sujet.nbReponses,
      nbJaime: sujet.nbJaime,
      verrouille: sujet.verrouille,
    );
  }

  Future<ResultatVote> call({
    required String slug,
    required AvisCommunaute avis,
  }) => _depot.voter(slug, avis);
}
