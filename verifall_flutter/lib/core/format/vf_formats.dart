/// Les mises en forme d'affichage — dates, durées, tailles.
///
/// # Pourquoi elles vivent dans le socle
///
/// Trois parcours en avaient écrit chacun leur version : `LmsFormats` (M3),
/// `DatesM4` (M4) et `AgeDuCache` (M2). Trois implémentations d'une même règle,
/// c'est exactement le symptôme que la règle 3 du parallélisme annonce — « un
/// composant dupliqué dans deux dossiers diverge en une semaine ». Elles sont
/// fusionnées ici, et les trois divergences réelles sont **conservées**, parce
/// que chacune avait sa raison (arbitrage n° 130).
///
/// # Ce qui se met en forme ici, et ce qui ne s'y calcule JAMAIS
///
/// Mettre en forme une date reçue est le travail d'un écran : le contrat émet en
/// ISO 8601 avec fuseau, et l'affichage dans le fuseau du téléphone est du
/// ressort du client.
///
/// **Le compte à rebours d'une opportunité n'est pas de cette famille.** Il vient
/// de `data.jours_restants` et ne se recalcule pas : « une horloge de téléphone
/// mal réglée ferait disparaître une offre encore ouverte, ou pire, présenterait
/// comme candidatable une offre fermée. »
///
/// # Pourquoi aucune bibliothèque de localisation
///
/// Ajouter `intl` est une décision de dépendance. Ces six fonctions couvrent le
/// besoin des cinq parcours, et le produit n'a qu'une langue.
abstract final class VfFormats {
  /// `28/07/2026` — la forme du simulateur, partout.
  static String dateCourte(DateTime date) {
    final DateTime locale = date.toLocal();
    return '${_deuxChiffres(locale.day)}/${_deuxChiffres(locale.month)}/'
        '${locale.year}';
  }

  /// `14:05`.
  static String heure(DateTime date) {
    final DateTime locale = date.toLocal();
    return '${_deuxChiffres(locale.hour)}:${_deuxChiffres(locale.minute)}';
  }

  /// `28/07/2026 à 14:05`.
  ///
  /// L'heure compte : « une échéance J-2 vieille de trois jours devient un
  /// mensonge sans sa date », et une donnée du matin relue le soir n'est pas la
  /// même chose qu'une donnée d'il y a une minute.
  static String dateEtHeure(DateTime date) =>
      '${dateCourte(date)} à ${heure(date)}';

  /// `1:23` — un temps restant de chronomètre.
  static String chronometre(Duration reste) {
    final int minutes = reste.inMinutes;
    final int secondes = reste.inSeconds % 60;
    return '$minutes:${_deuxChiffres(secondes)}';
  }

  /// `1,2 Mo` — une taille **mesurée**, jamais estimée.
  static String octets(int valeur) {
    if (valeur < 1024) {
      return '$valeur o';
    }
    if (valeur < 1024 * 1024) {
      return '${(valeur / 1024).toStringAsFixed(0)} Ko';
    }
    return '${(valeur / (1024 * 1024)).toStringAsFixed(1)} Mo'.replaceAll(
      '.',
      ',',
    );
  }

  /// « il y a 12 min », « hier », puis **la date** au-delà d'une semaine.
  ///
  /// C'est la forme des contenus **datés** : un message du forum, une brève, une
  /// séance. POURQUOI la formule bascule sur une date au-delà d'une semaine :
  /// « il y a 43 jours » n'aide personne à situer un message ; une date, si.
  ///
  /// POURQUOI [maintenant] est un paramètre : une fonction qui lit l'horloge
  /// elle-même n'est pas testable, et une mise en forme de date non testée finit
  /// toujours par afficher « il y a -1 min ».
  static String? anciennete(DateTime? date, {DateTime? maintenant}) {
    if (date == null) {
      return null;
    }
    final DateTime reference = maintenant ?? DateTime.now();
    final Duration ecart = reference.difference(date.toLocal());

    // Une date dans le futur ne se raconte pas au passe. Cela arrive avec une
    // horloge de telephone en avance : on affiche la date, qui reste vraie.
    if (ecart.isNegative) {
      return dateCourte(date);
    }
    if (ecart.inMinutes < 1) {
      return 'a l instant';
    }
    if (ecart.inMinutes < 60) {
      return 'il y a ${ecart.inMinutes} min';
    }
    if (ecart.inHours < 24) {
      return 'il y a ${ecart.inHours} h';
    }
    if (ecart.inDays == 1) {
      return 'hier';
    }
    if (ecart.inDays < 7) {
      return 'il y a ${ecart.inDays} jours';
    }
    return dateCourte(date);
  }

  /// L'âge d'une **synchronisation**, qui ne bascule jamais sur une date.
  ///
  /// POURQUOI elle coexiste avec [anciennete] au lieu de la remplacer : les deux
  /// répondent à deux questions différentes. « Quand ce message a-t-il été
  /// écrit ? » veut une date passé une semaine ; « à quand remonte ma dernière
  /// synchronisation ? » veut une durée, toujours — c'est la durée qui dit s'il
  /// faut se méfier de ce qu'on lit. Les fusionner aurait fait perdre l'une des
  /// deux (arbitrage n° 130).
  static String age(DateTime? luLe, {DateTime? maintenant}) {
    if (luLe == null) {
      return 'jamais synchronise';
    }
    final DateTime now = (maintenant ?? DateTime.now()).toUtc();
    final Duration ecart = now.difference(luLe.toUtc());

    if (ecart.isNegative || ecart.inSeconds < 60) {
      return 'a l instant';
    }
    if (ecart.inMinutes < 60) {
      return 'il y a ${ecart.inMinutes} min';
    }
    if (ecart.inHours < 24) {
      return 'il y a ${ecart.inHours} h';
    }
    final int jours = ecart.inDays;
    return jours == 1 ? 'il y a 1 jour' : 'il y a $jours jours';
  }

  /// La phrase du bandeau d'horodatage — **une seule**, pour les cinq parcours.
  ///
  /// Le libellé est celui de la documentation, mot pour mot : « Données du
  /// `<date>`, actualisation impossible » (05-HORS-LIGNE §5.4). Elle porte
  /// **l'année** : un cache de plusieurs mois affiché en `20/08` laisserait
  /// croire à un cache du mois dernier.
  ///
  /// Elle est exposée en plus du widget `VfStaleDataBanner` parce qu'un écran
  /// l'insère parfois dans une phrase plus longue — « … Le solde d'unités n'est
  /// pas affiché : il ne vient jamais du cache. »
  static String bandeauDeFraicheur(DateTime? lueLe) => lueLe == null
      ? 'Données non synchronisées, actualisation impossible.'
      : 'Données du ${dateEtHeure(lueLe)}, actualisation impossible.';

  static String _deuxChiffres(int valeur) => valeur.toString().padLeft(2, '0');
}
