import '../../data/models/televersement.dart';

/// Les cinq types de contenu soumissibles — `POST /verifications`, champ `type`
/// (§10.3), relevés sur l'implémentation réelle (`App\Enums\TypeContenu`).
///
/// # Ce que cette énumération ne porte PAS, et c'est le point
///
/// **Aucun coût.** Le contrat est catégorique : *« le coût n'est jamais envoyé
/// par le client : il est lu du type »*. Le simulateur affiche un barème
/// (1/1/2/5/5) parce qu'il doit fonctionner seul ; le recopier ici, ce serait
/// afficher un prix que le serveur ne pratique pas forcément — et une plateforme
/// qui annonce 2 unités puis en débite 5 a menti, même sans le vouloir.
///
/// Le seul chiffre affiché avant l'action est le **solde**, et il vient de
/// `meta.solde`. Un test vérifie qu'aucun barème n'existe dans le code client.
enum TypeContenu {
  texte('text', 'Texte'),
  lien('url', 'URL'),
  image('image', 'Image'),
  video('video', 'Vidéo'),
  audio('audio', 'Audio');

  const TypeContenu(this.wireValue, this.libelleSimulateur);

  final String wireValue;

  /// Le libellé de la **tuile de sélection**, repris du simulateur.
  ///
  /// POURQUOI il est légitime alors que « le client ne fabrique jamais de
  /// libellé » : ce n'est pas la restitution d'une valeur reçue, c'est le
  /// libellé d'un contrôle de saisie — le même statut que « Envoyer » sur un
  /// bouton. Le libellé **restitué** d'une vérification existante vient, lui,
  /// toujours de `type_libelle`.
  final String libelleSimulateur;

  static TypeContenu? parse(String? raw) {
    for (final TypeContenu type in TypeContenu.values) {
      if (type.wireValue == raw) {
        return type;
      }
    }
    return null;
  }

  /// Le type exige-t-il un téléversement préalable ?
  ///
  /// Le contrat l'impose pour `image`, `video` et `audio` : deux appels, jamais
  /// un multipart unique (arbitrage n° 72).
  bool get exigeUnFichier => televersement != null;

  /// Le type de téléversement correspondant, ou `null` pour une saisie.
  ///
  /// C'est le socle qui porte plafonds et extensions (`TypeTeleversement`) :
  /// les redéclarer ici en ferait une seconde source de vérité, et la première
  /// fois qu'elles divergeraient, ce serait pour laisser partir 120 Mo.
  TypeTeleversement? get televersement => switch (this) {
    TypeContenu.image => TypeTeleversement.image,
    TypeContenu.video => TypeTeleversement.video,
    TypeContenu.audio => TypeTeleversement.audio,
    TypeContenu.texte || TypeContenu.lien => null,
  };
}
