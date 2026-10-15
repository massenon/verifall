import '../../../domain/entities/reply_entity.dart';
import '../../../domain/entities/thread_entity.dart';
import '../auteur.dart';
import '../json_reader.dart';
import 'reponse_forum_model.dart';

/// La lecture d'un sujet du forum.
///
/// # Ce que le contrat nomme, et ce qu'il ne nomme pas
///
/// Le contrat fixe l'enveloppe, les endpoints et les champs **structurants** du
/// forum : `slug`, `titre`, `corps`, `category`, `auteur`, `verdict_officiel`,
/// `votes`, `mon_vote`, `lien_signale`. Il ne publie pas la forme détaillée de
/// la ressource — c'est la réserve F.1 de la section 9.
///
/// **Conséquence sur ce fichier** : tout champ non nommé par le contrat est lu
/// de façon tolérante et **vaut `null` quand il est absent**. L'écran fait alors
/// disparaître l'élément concerné. Aucun compteur n'est remplacé par un zéro,
/// aucune date par « à l'instant » : « si une donnée n'est pas servie, l'écran
/// le dit — il ne la remplace pas par une valeur plausible ».
abstract final class SujetForumModel {
  /// Les noms lus pour les champs que le contrat ne nomme pas.
  ///
  /// POURQUOI ils sont regroupés en constantes plutôt qu'écrits dans le code :
  /// le jour où le contrat les publie, la correction tient en cinq lignes et se
  /// trouve du premier coup.
  static const String champPublieLe = 'publie_le';
  static const String champNbReponses = 'nb_reponses';
  static const String champNbJaime = 'nb_j_aime';
  static const String champVerrouille = 'verrouille';
  static const String champCategorieLibelle = 'category_libelle';
  static const String champReponses = 'reponses';

  static SujetForum fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return SujetForum(
      slug: reader.string('slug'),
      titre: reader.string('titre'),
      corps: reader.string('corps'),
      auteur: _auteur(reader),
      categorieSlug: reader.stringOrNull('category'),
      categorieLibelle: reader.stringOrNull(champCategorieLibelle),
      publieLe: reader.dateTimeOrNull(champPublieLe),
      votes: votesFromJson(reader.objectOrNull('votes')),
      monVote: AvisCommunaute.parse(reader.stringOrNull('mon_vote')),
      verdictOfficiel: verdictFromJson(json['verdict_officiel']),
      nbReponses: reader.intOrNull(champNbReponses),
      nbJaime: reader.intOrNull(champNbJaime),
      verrouille: reader.boolValue(champVerrouille),
    );
  }

  static SujetForumDetail detailFromJson(Map<String, Object?> json) {
    return SujetForumDetail(
      sujet: fromJson(json),
      reponses: ReponseForumModel.listeAplatie(
        JsonReader(json).objectListOrEmpty(champReponses),
      ),
    );
  }

  /// L'auteur — `AuteurResource` du socle.
  ///
  /// POURQUOI la ligne auteur n'affichera aucun badge de rôle : l'API ne
  /// sérialise **jamais** le rôle (arbitrage n° 74). « Publier "untel est
  /// modérateur" sur chaque message dresserait la liste nominative de l'équipe
  /// de modération. » Voir réserve C.4 et `docs/ARBITRAGES.md`.
  static Auteur _auteur(JsonReader reader) {
    final Map<String, Object?>? brut = reader.objectOrNull('auteur');
    if (brut == null) {
      return const Auteur(uuid: '', nomAffichage: '', initiales: '');
    }
    return Auteur.fromJson(brut);
  }

  /// La répartition des votes.
  ///
  /// # Pourquoi deux jeux de clés sont acceptés
  ///
  /// Le contrat nomme le champ `data.votes` et fixe les valeurs de `avis` en
  /// anglais (`true` · `doubt` · `false`), mais ne publie pas les clés de la
  /// répartition. Le simulateur, lui, écrit `{vrai, doute, faux}`.
  ///
  /// Lire les deux n'est pas une supposition sur l'API : c'est du décodage
  /// tolérant, exactement ce que le contrat demande au client (§6.2). Le jour où
  /// la forme est publiée, la lecture inutile disparaît sans rien casser.
  ///
  /// **Ces valeurs ne sont jamais recalculées** : elles sont déjà en
  /// pourcentages, recalculés par le serveur.
  static RepartitionVotes? votesFromJson(Map<String, Object?>? json) {
    if (json == null) {
      return null;
    }
    final JsonReader reader = JsonReader(json);
    final int? vrai = reader.intOrNull('vrai') ?? reader.intOrNull('true');
    final int? doute = reader.intOrNull('doute') ?? reader.intOrNull('doubt');
    final int? faux = reader.intOrNull('faux') ?? reader.intOrNull('false');
    if (vrai == null && doute == null && faux == null) {
      // Un objet `votes` present mais illisible n'est pas un sondage a zero :
      // c'est un sondage qu'on ne sait pas lire. On n'en dessine aucun.
      return null;
    }
    return RepartitionVotes(
      vraiPourcent: vrai ?? 0,
      doutePourcent: doute ?? 0,
      fauxPourcent: faux ?? 0,
    );
  }

  /// Le verdict officiel.
  ///
  /// **Il n'existe que si le serveur l'envoie.** Il ne se déduit jamais des
  /// votes — c'est l'arbitrage n° 43, et c'est la règle la plus importante du
  /// forum.
  ///
  /// La valeur peut arriver sous deux formes : l'objet `{verdict,
  /// verdict_libelle}` de la convention d'énumération (§8), ou une chaîne seule.
  /// Dans les deux cas, **le libellé affiché est celui reçu** ; le client n'en
  /// fabrique aucun.
  static VerdictOfficiel? verdictFromJson(Object? brut) {
    if (brut == null) {
      return null;
    }
    if (brut is String) {
      final String valeur = brut.trim();
      return valeur.isEmpty
          ? null
          : VerdictOfficiel(valeur: valeur, libelle: valeur);
    }
    final Map<String, Object?> json = asJsonObject(brut);
    if (json.isEmpty) {
      return null;
    }
    final JsonReader reader = JsonReader(json);
    final String? valeur = reader.stringOrNull('verdict');
    final String? libelle = reader.stringOrNull('verdict_libelle');
    if (valeur == null && libelle == null) {
      return null;
    }
    return VerdictOfficiel(
      valeur: valeur ?? '',
      // Sans libelle serveur, on affiche la valeur technique plutot qu'un mot
      // francais fabrique ici : « le client ne fabrique jamais le libelle ».
      libelle: libelle ?? valeur ?? '',
    );
  }

  /// `meta.categories[]` — les pastilles de rubrique.
  ///
  /// POURQUOI elles viennent d'ici et jamais d'une liste écrite à la main :
  /// « deux listes finissent par diverger, et c'est le filtre qui cesse de
  /// trouver quoi que ce soit » (§3.7).
  static List<RubriqueForum> rubriquesFromMeta(Map<String, Object?> meta) {
    final List<Map<String, Object?>> brutes = JsonReader(
      meta,
    ).objectListOrEmpty('categories');
    return <RubriqueForum>[
      for (final Map<String, Object?> item in brutes)
        if (JsonReader(item).stringOrNull('slug') case final String slug
            when slug.isNotEmpty)
          RubriqueForum(
            slug: slug,
            // Sans libelle, la pastille porte son slug : il vient du serveur,
            // il est donc vrai, meme s'il est moins joli.
            libelle: JsonReader(item).stringOrNull('libelle') ?? slug,
          ),
    ];
  }

  /// Le couple `data.votes` / `data.mon_vote` renvoyé par un vote.
  static ResultatVote resultatVoteFromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return ResultatVote(
      votes: votesFromJson(reader.objectOrNull('votes')),
      monVote: AvisCommunaute.parse(reader.stringOrNull('mon_vote')),
    );
  }

  /// La sérialisation d'un sujet vers le cache local.
  ///
  /// POURQUOI on remet en JSON plutôt que de créer une table par champ : le
  /// cache est **jetable**, et une table par champ obligerait à une migration
  /// Drift — donc à une demande au responsable du socle — à chaque champ que le
  /// contrat publiera.
  static Map<String, Object?> toCacheJson(SujetForum sujet) {
    return <String, Object?>{
      'slug': sujet.slug,
      'titre': sujet.titre,
      'corps': sujet.corps,
      'category': sujet.categorieSlug,
      champCategorieLibelle: sujet.categorieLibelle,
      champPublieLe: sujet.publieLe?.toIso8601String(),
      'auteur': <String, Object?>{
        'uuid': sujet.auteur.uuid,
        'nom_affichage': sujet.auteur.nomAffichage,
        'initiales': sujet.auteur.initiales,
      },
      'votes': sujet.votes == null
          ? null
          : <String, Object?>{
              'vrai': sujet.votes!.vraiPourcent,
              'doute': sujet.votes!.doutePourcent,
              'faux': sujet.votes!.fauxPourcent,
            },
      'mon_vote': sujet.monVote?.wireValue,
      'verdict_officiel': sujet.verdictOfficiel == null
          ? null
          : <String, Object?>{
              'verdict': sujet.verdictOfficiel!.valeur,
              'verdict_libelle': sujet.verdictOfficiel!.libelle,
            },
      champNbReponses: sujet.nbReponses,
      champNbJaime: sujet.nbJaime,
      champVerrouille: sujet.verrouille,
    };
  }

  static Map<String, Object?> detailToCacheJson(SujetForumDetail detail) {
    return <String, Object?>{
      ...toCacheJson(detail.sujet),
      champReponses: <Map<String, Object?>>[
        for (final ReponseForum reponse in detail.reponses)
          ReponseForumModel.toCacheJson(reponse),
      ],
    };
  }
}
