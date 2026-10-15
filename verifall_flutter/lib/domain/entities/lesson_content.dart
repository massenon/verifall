import 'package:flutter/foundation.dart';

import 'lesson_summary.dart';

/// Une leçon **avec** son contenu — `GET /courses/{slug}/lessons/{uuid}`.
///
/// C'est le seul appel qui porte `contenu`, et c'est aussi celui qui
/// **démarre le chronomètre serveur**. Les deux faits sont liés et ils
/// gouvernent tout le téléchargement hors ligne (réserve C.3).
@immutable
class LessonContent {
  const LessonContent({
    required this.lecon,
    required this.coursSlug,
    required this.contenuHtml,
    required this.secondesRequises,
  });

  final LessonSummary lecon;
  final String coursSlug;

  /// HTML issu de Quill, **purifié côté serveur avant persistance**, et rendu
  /// ici en liste blanche de balises (`VfProseBody`).
  ///
  /// `null` signifie « le serveur n'a pas joint de contenu » — à distinguer
  /// d'une chaîne vide. L'écran le dit plutôt que d'afficher une page blanche.
  final String? contenuHtml;

  /// `meta.secondes_requises`.
  ///
  /// POURQUOI ce n'est pas une constante : le simulateur annonce 45 s, mais
  /// « le contrat rend la valeur par le serveur. Ne pas coder 45. » Une durée
  /// figée dans le client cesse d'être la règle du produit le jour où
  /// l'Académie la change.
  final int secondesRequises;
}
