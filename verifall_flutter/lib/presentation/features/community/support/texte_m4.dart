/// L'extrait en clair d'un corps HTML.
///
/// # Pourquoi une carte de liste n'affiche pas de HTML
///
/// Le corps d'un sujet ou d'une brève est du HTML Quill. Sur la fiche complète,
/// il est rendu par `VfProseBody`, qui applique une **liste blanche de balises**.
/// Sur une carte de liste, le simulateur n'en montre que deux lignes : un rendu
/// riche tronqué à deux lignes n'a pas de sens — un titre `h2` ou une liste à
/// puces y occuperaient toute la place.
///
/// La carte affiche donc du **texte**, obtenu en retirant les balises. C'est
/// aussi le plus sûr : un `Text` de Flutter n'interprète jamais de balisage, quoi
/// qu'on lui donne. La règle « jamais `{!! !!}` sur données utilisateur » est
/// respectée par construction.
abstract final class TexteM4 {
  static final RegExp _balises = RegExp('<[^>]*>');
  static final RegExp _espaces = RegExp(r'\s+');

  /// Les cinq entités que Quill produit couramment.
  ///
  /// POURQUOI seulement cinq et pas une table complète : au-delà, il faudrait
  /// une bibliothèque, et une entité rare non décodée s'affiche telle quelle —
  /// c'est disgracieux, jamais dangereux. Un décodage maison approximatif de
  /// deux cents entités, lui, finit par se tromper.
  static const Map<String, String> _entites = <String, String>{
    '&amp;': '&',
    '&lt;': '<',
    '&gt;': '>',
    '&quot;': '"',
    '&#39;': "'",
    '&nbsp;': ' ',
  };

  static String extrait(String html) {
    // Les separateurs de bloc deviennent des espaces : sans cela, « fin de
    // paragraphe</p><p>Debut » se lirait « paragrapheDebut ».
    String texte = html
        .replaceAll('</p>', ' ')
        .replaceAll('<br>', ' ')
        .replaceAll('<br/>', ' ')
        .replaceAll('<br />', ' ')
        .replaceAll('</li>', ' ')
        .replaceAll(_balises, '');
    for (final MapEntry<String, String> entite in _entites.entries) {
      texte = texte.replaceAll(entite.key, entite.value);
    }
    return texte.replaceAll(_espaces, ' ').trim();
  }
}
