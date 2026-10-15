import 'package:flutter/foundation.dart';

/// Une donnée lue, **avec la date à laquelle elle a été lue**.
///
/// POURQUOI la date accompagne la donnée jusque dans le domaine, au lieu de
/// rester un détail de la couche `data` : le cycle de lecture du hors ligne
/// (05-HORS-LIGNE §5.4) impose un bandeau « Données du `<date>`, actualisation
/// impossible » quand le rafraîchissement échoue. Sans sa date, « une échéance
/// J-2 vieille de trois jours devient un mensonge ». L'écran ne peut poser ce
/// bandeau que si la date lui parvient — donc elle voyage avec la donnée.
///
/// POURQUOI le fichier s'appelle `course_snapshot.dart` alors qu'il ne parle
/// pas de cours : les fichiers en écriture de ce parcours sont listés sous la
/// forme `domain/entities/{course,lesson,quiz,certificate,live_class}_*.dart`
/// (§8.4). Ce type est transverse à M3 mais il doit tenir dans ce gabarit ;
/// s'il devient utile à un second parcours, il appartiendra au socle et ne se
/// dupliquera pas ici (§8.1, règle 3).
@immutable
class Snapshot<T> {
  const Snapshot({
    required this.valeur,
    required this.lueLe,
    required this.origine,
  });

  /// Une lecture réseau : fraîche par construction.
  Snapshot.duReseau(T valeur, {DateTime? maintenant})
    : this(
        valeur: valeur,
        lueLe: maintenant ?? DateTime.now(),
        origine: OrigineDonnee.reseau,
      );

  /// Une lecture du cache local, avec la date de sa capture.
  const Snapshot.duCache(T valeur, DateTime lueLe)
    : this(valeur: valeur, lueLe: lueLe, origine: OrigineDonnee.cache);

  final T valeur;

  /// L'instant où le **serveur** a été lu, pas celui où l'écran s'est construit.
  final DateTime lueLe;

  final OrigineDonnee origine;

  bool get vientDuCache => origine == OrigineDonnee.cache;

  /// La donnée a-t-elle dépassé la durée de vie que le contrat lui donne
  /// (§7.1) ?
  ///
  /// Une donnée périmée reste **affichée** — sinon le hors ligne ne servirait à
  /// rien — mais elle s'affiche avec sa date. « Le cache est lu d'abord, le
  /// réseau ensuite. »
  bool estPerimee(Duration dureeDeVie, {DateTime? maintenant}) =>
      (maintenant ?? DateTime.now()).difference(lueLe) > dureeDeVie;

  Snapshot<R> transformer<R>(R Function(T valeur) f) =>
      Snapshot<R>(valeur: f(valeur), lueLe: lueLe, origine: origine);

  @override
  String toString() => 'Snapshot<$T>(${origine.name}, $lueLe)';
}

enum OrigineDonnee { reseau, cache }

/// Les durées de vie du cache, **telles que le contrat les fixe** (§7.1),
/// reprises en 05-HORS-LIGNE §5.2.
///
/// POURQUOI elles sont des constantes nommées et pas des nombres posés dans un
/// dépôt : « elles ne sont pas des suggestions — elles équilibrent la fraîcheur
/// d'un contenu et le coût d'un aller-retour sur un réseau mobile ».
abstract final class DureesDeVieLms {
  /// `GET /courses` — 1 h.
  static const Duration catalogue = Duration(hours: 1);

  /// `GET /courses/{slug}` — 1 h.
  static const Duration ficheDeCours = Duration(hours: 1);

  /// `GET /courses/{slug}/lessons/{uuid}` — **jusqu'à la fin du cours**.
  ///
  /// C'est la durée de vie la plus longue de tout le cache, et « c'est ce qui
  /// rend le parcours M3 possible : une leçon téléchargée reste lisible tant
  /// que le cours n'est pas terminé, et pas seulement une heure ». Elle n'est
  /// donc pas exprimée en heures : le contenu d'une leçon est effacé par la fin
  /// du cours ou par la suppression du téléchargement, jamais par une horloge.
  static const Duration? contenuDeLecon = null;

  /// Les séances en direct n'ont **pas** de durée de vie au contrat : elles ne
  /// figurent pas dans le tableau du §7.1. Plutôt que d'en inventer une, la
  /// liste est toujours accompagnée de sa date dès qu'elle vient du cache —
  /// c'est le comportement le plus honnête que le contrat autorise.
  static const Duration? seances = null;
}
