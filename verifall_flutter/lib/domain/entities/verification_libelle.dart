import 'package:flutter/foundation.dart';

import '../../data/models/json_reader.dart';

/// Le couple **valeur technique / libellé français**, tel que l'API le fait
/// voyager partout (§8 du contrat).
///
/// > « `"verdict": "false"`, `"verdict_libelle": "Faux"`. Le client affiche le
/// > libellé et branche sur la valeur. **Il ne fabrique jamais le libellé
/// > lui-même** : ajouter un cas côté serveur casserait tous les `switch` du
/// > client. »
///
/// POURQUOI un type et pas deux chaînes côte à côte : deux chaînes se
/// désolidarisent. Quelqu'un finit par passer `verdict` là où `verdict_libelle`
/// était attendu, et l'écran affiche `misleading` à un citoyen béninois. Ici la
/// paire est indissociable, et [libelle] ne se calcule jamais depuis [valeur].
@immutable
class ValeurLibelle {
  const ValeurLibelle({required this.valeur, required this.libelle});

  /// Lit la paire `<prefixe>` / `<prefixe>_libelle`.
  ///
  /// Rend `null` quand la valeur technique est absente ou nulle : le contrat
  /// distingue « absent » de « présent et nul » (§8), et les deux signifient ici
  /// « pas encore de verdict », jamais « verdict vide ».
  static ValeurLibelle? lire(JsonReader reader, String prefixe) {
    final String? valeur = reader.stringOrNull(prefixe);
    if (valeur == null || valeur.isEmpty) {
      return null;
    }
    return ValeurLibelle(
      valeur: valeur,
      // Repli sur la valeur technique SEULEMENT si le serveur n'a pas envoyé de
      // libelle. Ce n'est pas une fabrication : c'est afficher ce qu'on a recu,
      // faute de mieux, et cela se voit — ce qui est le but.
      libelle: reader.stringOrNull('${prefixe}_libelle') ?? valeur,
    );
  }

  /// La valeur technique. **C'est là-dessus qu'on branche**, jamais sur le
  /// libellé — un libellé change au gré de la rédaction.
  final String valeur;

  /// Le libellé français **du serveur**, affichable tel quel.
  final String libelle;

  @override
  bool operator ==(Object other) =>
      other is ValeurLibelle &&
      other.valeur == valeur &&
      other.libelle == libelle;

  @override
  int get hashCode => Object.hash(valeur, libelle);

  @override
  String toString() => 'ValeurLibelle($valeur)';
}
