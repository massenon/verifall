import '../../../domain/entities/reply_entity.dart';
import '../auteur.dart';
import '../json_reader.dart';

/// La lecture d'une réponse du forum.
abstract final class ReponseForumModel {
  static const String champPublieLe = 'publie_le';
  static const String champReponses = 'reponses';

  /// La liste des réponses, **bornée à un seul niveau d'imbrication**.
  ///
  /// POURQUOI la coupe se fait ici, à la lecture, et pas au dessin : « un seul
  /// niveau d'imbrication, parce qu'au-delà un fil devient illisible sur
  /// téléphone » (point de vigilance de l'écran 20). Un widget qui se
  /// contenterait de ne pas dessiner le troisième niveau garderait le contenu
  /// en mémoire — et quelqu'un le réafficherait un jour, en toute bonne foi.
  static List<ReponseForum> listeAplatie(List<Map<String, Object?>> brutes) {
    return <ReponseForum>[
      for (final Map<String, Object?> item in brutes)
        _fromJson(item, niveau: 0),
    ];
  }

  static ReponseForum _fromJson(
    Map<String, Object?> json, {
    required int niveau,
  }) {
    final JsonReader reader = JsonReader(json);
    final Map<String, Object?>? auteur = reader.objectOrNull('auteur');
    return ReponseForum(
      uuid: reader.string('uuid'),
      corps: reader.string('corps'),
      auteur: auteur == null
          ? const Auteur(uuid: '', nomAffichage: '', initiales: '')
          : Auteur.fromJson(auteur),
      publieLe: reader.dateTimeOrNull(champPublieLe),
      reponses: niveau >= 1
          // Au-dela du premier niveau, les enfants sont ecartes. Ce n'est pas
          // une perte : le serveur ne devrait pas en produire, et s'il en
          // produit, l'ecran ne saurait pas les rendre lisibles.
          ? const <ReponseForum>[]
          : <ReponseForum>[
              for (final Map<String, Object?> enfant
                  in reader.objectListOrEmpty(champReponses))
                _fromJson(enfant, niveau: niveau + 1),
            ],
    );
  }

  static Map<String, Object?> toCacheJson(ReponseForum reponse) {
    return <String, Object?>{
      'uuid': reponse.uuid,
      'corps': reponse.corps,
      champPublieLe: reponse.publieLe?.toIso8601String(),
      'auteur': <String, Object?>{
        'uuid': reponse.auteur.uuid,
        'nom_affichage': reponse.auteur.nomAffichage,
        'initiales': reponse.auteur.initiales,
      },
      champReponses: <Map<String, Object?>>[
        for (final ReponseForum enfant in reponse.reponses) toCacheJson(enfant),
      ],
    };
  }
}
