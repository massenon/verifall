import 'package:flutter/foundation.dart';

/// Le niveau d'un cours — `data.niveau` + `data.niveau_libelle`.
///
/// POURQUOI la valeur technique et le libellé voyagent ensemble : c'est la
/// règle du contrat reprise par le socle (« le couple valeur technique /
/// libellé des énumérations, avec un type générique qui interdit de fabriquer
/// un libellé côté client »). Le client **n'écrit jamais** « Débutant » : il
/// affiche ce que le serveur a écrit.
///
/// POURQUOI il existe malgré tout une énumération : le filtre `level` du
/// catalogue est une **énumération fermée** côté serveur (contrat §3.7) — une
/// valeur hors liste répond `422`. C'est donc le client qui doit garantir de
/// n'envoyer que `beginner`, `intermediate` ou `advanced`.
///
/// POURQUOI un cas [inconnu] : le back a été mordu par un `match` sans branche
/// par défaut (arbitrage n° 38), et l'équivalent Dart est un `switch` non
/// exhaustif. Une valeur ajoutée par le serveur ne doit pas faire tomber
/// l'écran ; elle s'affiche avec le libellé reçu (06-TESTS §6.3).
enum NiveauCours {
  debutant('beginner'),
  intermediaire('intermediate'),
  avance('advanced'),

  /// Une valeur que cette version du client ne connaît pas encore.
  ///
  /// Elle n'est **jamais envoyée** en filtre : l'envoyer produirait un `422`.
  inconnu('');

  const NiveauCours(this.valeurTechnique);

  final String valeurTechnique;

  /// Les trois valeurs que le filtre `level` accepte, dans l'ordre du
  /// simulateur (`Tous · Débutant · Intermédiaire · Avancé`).
  static const List<NiveauCours> filtrables = <NiveauCours>[
    debutant,
    intermediaire,
    avance,
  ];

  static NiveauCours parse(String? raw) {
    for (final NiveauCours niveau in filtrables) {
      if (niveau.valeurTechnique == raw) {
        return niveau;
      }
    }
    return inconnu;
  }
}

/// Le niveau tel qu'il s'affiche : la valeur **et** le libellé du serveur.
@immutable
class NiveauAffiche {
  const NiveauAffiche({required this.valeur, required this.libelle});

  final NiveauCours valeur;

  /// `niveau_libelle`, rédigé par le serveur. Vide quand le cours n'a pas de
  /// niveau — auquel cas la pastille ne s'affiche pas, plutôt que d'afficher
  /// un mot inventé.
  final String libelle;

  bool get estRenseigne => libelle.isNotEmpty;

  @override
  String toString() => 'NiveauAffiche(${valeur.valeurTechnique})';
}
