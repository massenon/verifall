import 'package:flutter/foundation.dart';

import '../../data/models/auteur.dart';
import 'reply_entity.dart';

/// Les trois avis que `POST /forum/threads/{slug}/vote` accepte.
///
/// POURQUOI une énumération fermée et pas une chaîne : le contrat les donne en
/// toutes lettres — « `avis` : `true` · `doubt` · `false`. **Toute autre
/// valeur : `422`** » (§10.5). Une chaîne magique laisserait passer `vrai`, que
/// le serveur refuserait à l'exécution seulement.
///
/// POURQUOI les valeurs de transport sont en anglais alors que le champ est en
/// français : les valeurs d'énumération viennent des enums serveur, et
/// l'arbitrage n° 81 ne francise que les **noms** de champs du corps.
///
/// POURQUOI [libelleBouton] est écrit ici sans contredire « le client ne
/// fabrique jamais de libellé » : ce ne sont pas les libellés d'une énumération
/// servie par l'API, ce sont **les trois boutons du simulateur**, dont le texte
/// appartient à la maquette. L'API n'envoie jamais ces trois mots.
enum AvisCommunaute {
  vrai('true', 'Vrai'),
  doute('doubt', 'Doute'),
  faux('false', 'Faux');

  const AvisCommunaute(this.wireValue, this.libelleBouton);

  final String wireValue;

  /// Le libellé du bouton, tel que le simulateur l'écrit.
  final String libelleBouton;

  static AvisCommunaute? parse(String? raw) {
    for (final AvisCommunaute avis in AvisCommunaute.values) {
      if (avis.wireValue == raw) {
        return avis;
      }
    }
    return null;
  }
}

/// La répartition des votes, **en pourcentages recalculés par le serveur**.
///
/// POURQUOI le client ne calcule rien ici : « `data.votes` porte la répartition
/// en pourcentages, recalculée » (§10.5). Le simulateur, lui, divise des
/// compteurs bruts — il tourne sans serveur. Transposer ce calcul serait une
/// faute (réserve E de la section 9).
@immutable
class RepartitionVotes {
  const RepartitionVotes({
    required this.vraiPourcent,
    required this.doutePourcent,
    required this.fauxPourcent,
  });

  final int vraiPourcent;
  final int doutePourcent;
  final int fauxPourcent;

  /// Le texte lu par un lecteur d'écran. « Trois segments colorés ne se lisent
  /// pas » (03-ECRANS-05 §3.20, accessibilité).
  String get resumeParle =>
      'vrai $vraiPourcent %, doute $doutePourcent %, faux $fauxPourcent %';

  @override
  bool operator ==(Object other) =>
      other is RepartitionVotes &&
      other.vraiPourcent == vraiPourcent &&
      other.doutePourcent == doutePourcent &&
      other.fauxPourcent == fauxPourcent;

  @override
  int get hashCode => Object.hash(vraiPourcent, doutePourcent, fauxPourcent);
}

/// Le verdict que **la rédaction** a lié au sujet.
///
/// # Pourquoi c'est un type distinct de [RepartitionVotes]
///
/// C'est l'arbitrage n° 43, et c'est la règle qui structure tout le forum :
/// **deux jugements, jamais mélangés**. L'avis de la communauté est un sondage ;
/// le verdict de Vérif'All est une vérification. Les représenter par le même
/// type inviterait, un jour, à déduire l'un de l'autre.
///
/// « Une communauté convaincue à 90 % qu'une rumeur est vraie n'en fait pas une
/// vérification — et laisser l'interface le suggérer serait l'exact inverse de
/// ce que ce produit existe pour faire » (§10.5 du contrat).
///
/// Ce type n'est instancié **que** si `data.verdict_officiel` est présent.
/// Absent = pas de bandeau. Il ne se déduit jamais des votes.
@immutable
class VerdictOfficiel {
  const VerdictOfficiel({required this.valeur, required this.libelle});

  /// La valeur technique, pour brancher (§8 du contrat).
  final String valeur;

  /// Le libellé français **rendu par le serveur**, affiché tel quel.
  final String libelle;

  @override
  bool operator ==(Object other) =>
      other is VerdictOfficiel &&
      other.valeur == valeur &&
      other.libelle == libelle;

  @override
  int get hashCode => Object.hash(valeur, libelle);
}

/// Une rubrique du forum, telle que `meta.categories[]` la donne.
///
/// POURQUOI les pastilles s'alimentent d'ici et jamais d'une liste écrite à la
/// main : « deux listes finissent par diverger, et c'est le filtre qui cesse de
/// trouver quoi que ce soit » (§3.7 du contrat).
@immutable
class RubriqueForum {
  const RubriqueForum({required this.slug, required this.libelle});

  /// La valeur envoyée dans `?category=`.
  final String slug;

  final String libelle;

  /// La sentinelle « aucun filtre » du contrat (§3.6).
  ///
  /// POURQUOI une valeur plutôt que l'absence de paramètre : « l'interface est
  /// faite de pastilles ; la pastille "Toutes" envoie une valeur comme les
  /// autres, et exiger qu'elle n'envoie rien obligerait le client à traiter une
  /// pastille à part — ce qu'il finirait par oublier. »
  static const String slugToutes = 'toutes';

  /// La pastille de tête. Son libellé vient du simulateur (« Tous »).
  static const RubriqueForum toutes = RubriqueForum(
    slug: slugToutes,
    libelle: 'Tous',
  );

  @override
  bool operator ==(Object other) =>
      other is RubriqueForum && other.slug == slug && other.libelle == libelle;

  @override
  int get hashCode => Object.hash(slug, libelle);
}

/// Un sujet du forum.
///
/// # Ce qui est nullable, et pourquoi ce n'est pas de la prudence gratuite
///
/// Le contrat fixe l'enveloppe et nomme les champs **structurants** d'un sujet
/// (`verdict_officiel`, `votes`, `mon_vote`, `slug`, `titre`, `corps`,
/// `category`, `auteur`) mais ne publie pas la forme complète de la ressource
/// (réserve F.1 de la section 9). Tout le reste est donc lu de façon tolérante
/// et **disparaît de l'écran quand il n'est pas servi**.
///
/// C'est la règle §2 du chantier transposée : « si une donnée n'est pas servie,
/// l'écran le dit — il ne la remplace pas par une valeur plausible ». Un zéro
/// affiché à la place d'un compteur absent est un chiffre inventé.
@immutable
class SujetForum {
  const SujetForum({
    required this.slug,
    required this.titre,
    required this.corps,
    required this.auteur,
    this.categorieSlug,
    this.categorieLibelle,
    this.publieLe,
    this.votes,
    this.monVote,
    this.verdictOfficiel,
    this.nbReponses,
    this.nbJaime,
    this.verrouille = false,
  });

  /// **L'identifiant est un slug, jamais un entier** (§4 du contrat) : « un
  /// entier séquentiel dans une URL révèle le volume et invite à
  /// l'énumération » — et les liens profonds partagés sur WhatsApp sont
  /// publics.
  final String slug;

  final String titre;

  /// Du HTML Quill, **purifié avant persistance côté serveur** et rendu ici sur
  /// liste blanche (`VfProseBody`), jamais brut.
  final String corps;

  final Auteur auteur;

  final String? categorieSlug;
  final String? categorieLibelle;

  final DateTime? publieLe;

  /// Le sondage. `null` = ce sujet n'en porte pas ; l'écran n'affiche alors
  /// aucune barre — il n'en fabrique pas une à zéro.
  final RepartitionVotes? votes;

  final AvisCommunaute? monVote;

  /// **Présent uniquement si le serveur a lié un verdict.** Voir
  /// [VerdictOfficiel].
  final VerdictOfficiel? verdictOfficiel;

  final int? nbReponses;

  /// POURQUOI ce compteur est en lecture seule : **aucun endpoint du contrat ne
  /// permet d'aimer un sujet**. Le cœur du simulateur est un état local de
  /// démonstration. Un bouton qui n'appelle rien ferait croire à un geste
  /// enregistré. Voir `docs/ARBITRAGES.md`.
  final int? nbJaime;

  /// Fil fermé par la modération. L'écran masque le composeur ; **c'est le
  /// serveur qui protège** (`422 action_impossible`).
  final bool verrouille;

  SujetForum copyWith({
    RepartitionVotes? votes,
    AvisCommunaute? monVote,
    int? nbReponses,
  }) {
    return SujetForum(
      slug: slug,
      titre: titre,
      corps: corps,
      auteur: auteur,
      categorieSlug: categorieSlug,
      categorieLibelle: categorieLibelle,
      publieLe: publieLe,
      votes: votes ?? this.votes,
      monVote: monVote ?? this.monVote,
      verdictOfficiel: verdictOfficiel,
      nbReponses: nbReponses ?? this.nbReponses,
      nbJaime: nbJaime,
      verrouille: verrouille,
    );
  }

  @override
  String toString() => 'SujetForum($slug)';
}

/// Un sujet **et ses réponses**, tel que `GET /forum/threads/{slug}` le rend.
@immutable
class SujetForumDetail {
  const SujetForumDetail({
    required this.sujet,
    this.reponses = const <ReponseForum>[],
  });

  final SujetForum sujet;

  /// **Un seul niveau d'imbrication** : le contrat de l'écran l'impose, « parce
  /// qu'au-delà un fil devient illisible sur téléphone ». L'aplatissement se
  /// fait à la lecture du JSON, pas à l'affichage : un widget qui se contente
  /// de ne pas dessiner le troisième niveau laisse quand même le contenu en
  /// mémoire, et le fera réapparaître le jour d'une refonte.
  final List<ReponseForum> reponses;

  /// Le décompte affiché en tête de la liste des réponses.
  ///
  /// POURQUOI on préfère `nb_reponses` du serveur à la longueur de la liste :
  /// la liste peut être paginée un jour, la longueur mentirait alors.
  int get nbReponsesAffichees => sujet.nbReponses ?? reponses.length;

  SujetForumDetail copyWith({SujetForum? sujet, List<ReponseForum>? reponses}) {
    return SujetForumDetail(
      sujet: sujet ?? this.sujet,
      reponses: reponses ?? this.reponses,
    );
  }
}

/// Une page de sujets, plus ce que `meta` porte avec elle.
@immutable
class PageSujets {
  const PageSujets({
    required this.sujets,
    required this.rubriques,
    this.curseurSuivant,
    this.recupereLe,
  });

  final List<SujetForum> sujets;

  /// `meta.categories[]`. Vide quand le serveur n'en envoie pas : l'écran
  /// affiche alors la seule pastille « Tous », plutôt qu'une liste inventée.
  final List<RubriqueForum> rubriques;

  /// **Opaque.** On le renvoie tel quel, on ne le lit jamais (§3.2).
  final String? curseurSuivant;

  /// Quand ces données ont été récupérées. Renseigné **uniquement** quand la
  /// page vient du cache : c'est ce qui alimente le bandeau « Données du
  /// `<date>`, actualisation impossible » (05-HORS-LIGNE §5.4).
  final DateTime? recupereLe;

  bool get vientDuCache => recupereLe != null;
}

/// Ce que le serveur renvoie après un vote : les deux champs qui font foi.
@immutable
class ResultatVote {
  const ResultatVote({
    required this.votes,
    required this.monVote,
    this.provisoire = false,
  });

  /// **Recalculés par le serveur.** Le client doit accepter des pourcentages
  /// différents de ceux qu'il vient d'afficher, et « se ranger sans clignoter ».
  ///
  /// `null` signifie « le serveur n'a rien recalculé » — cas d'un vote parti en
  /// file hors ligne. L'écran garde alors la répartition qu'il affichait :
  /// l'effacer donnerait l'impression que le sondage a disparu.
  final RepartitionVotes? votes;

  final AvisCommunaute? monVote;

  /// Vrai quand le vote est parti en file et n'a pas encore été confirmé.
  ///
  /// POURQUOI l'écran doit le dire : « le vote hors ligne doit afficher son
  /// caractère provisoire — le résultat consolidé viendra du serveur »
  /// (03-ECRANS-05 §3.21). Un vote affiché comme acquis alors qu'il attend
  /// encore est la même faute qu'un sujet affiché comme publié.
  final bool provisoire;
}

/// L'issue d'une écriture qui peut partir en file d'attente.
///
/// POURQUOI l'appelant doit connaître la différence : « ne jamais faire croire
/// qu'une écriture a abouti » (05-HORS-LIGNE §5.7). Le message affiché n'est pas
/// le même selon que le serveur a répondu ou que l'acte attend son tour.
enum IssueEcriture { envoyee, miseEnFile }
