import 'dart:io';

/// Le champ `appareil` des trois appels qui émettent un jeton.
///
/// # À quoi il sert
///
/// Il **nomme** le jeton. Le contrat le dit en une phrase : « une liste
/// d'appareils lisible se révoque en connaissance de cause ; "jeton 47" non »
/// (§10.1). C'est donc de l'ergonomie de sécurité, pas de la télémétrie : rien
/// d'autre ne dépend de cette valeur.
///
/// # Ce que cette classe rend aujourd'hui, et pourquoi ce n'est pas le modèle
///
/// La définition de fini de M1 demande **le modèle réel du téléphone** —
/// « Pixel 7 ». Or Flutter ne l'expose pas : il faut `device_info_plus`, qui
/// n'est **pas** déclaré dans `pubspec.yaml`. Or `pubspec.yaml` appartient au
/// socle, et « un besoin de modification du socle est une demande, pas une
/// modification » (§8.1, règle 2).
///
/// En attendant cette dépendance, on envoie ce que `dart:io` sait réellement :
/// le système et sa version — « Android 13 (API 33) », « iOS 17.4 ». C'est
/// **moins précis, mais ce n'est pas faux** ; et c'est déjà beaucoup plus
/// lisible que « jeton 47 » dans une liste de sessions.
///
/// POURQUOI ce n'est pas non plus une valeur inventée : on ne fabrique aucun
/// nom de modèle plausible. Un « Samsung Galaxy » écrit par défaut aurait fait
/// révoquer le mauvais appareil.
///
/// **Depuis la passe d'intégration**, `device_info_plus` est déclaré et la racine
/// de composition lit le modèle réel au démarrage, puis surcharge
/// `nomAppareilProvider` (arbitrage n° 141). [NomAppareilSysteme] reste le repli
/// quand la plateforme ne dit rien : moins précis, jamais faux. Rien d'autre
/// dans M1 ne connaît la façon dont ce nom est obtenu — c'était l'intention du
/// point d'injection, et elle a tenu.
abstract interface class NomAppareil {
  /// Le nom à envoyer, ou `null` si l'on ne sait rien dire.
  ///
  /// POURQUOI `null` est une réponse acceptable : le champ est facultatif au
  /// contrat. Envoyer « inconnu » remplirait la liste des sessions de lignes
  /// identiques, ce qui est pire que de la laisser vide.
  String? valeur();
}

/// L'implémentation réelle, bornée à ce que la plateforme sait dire.
class NomAppareilSysteme implements NomAppareil {
  const NomAppareilSysteme();

  /// La borne du contrat : `appareil` est validé en `max:60`. Tronquer ici évite
  /// un `422 validation` sur un champ que la personne n'a jamais saisi.
  static const int longueurMax = 60;

  @override
  String? valeur() {
    // `Platform` leve sur le web ; l'application est mobile, mais un test de
    // widget peut tourner ailleurs, et un nom d'appareil ne doit jamais faire
    // echouer une connexion.
    final String brut;
    try {
      brut = Platform.operatingSystemVersion.trim().isEmpty
          ? _nomSysteme()
          : '${_nomSysteme()} ${Platform.operatingSystemVersion.trim()}';
    } on Object {
      return null;
    }

    final String propre = brut.trim();
    if (propre.isEmpty) {
      return null;
    }
    return propre.length > longueurMax
        ? propre.substring(0, longueurMax).trim()
        : propre;
  }

  String _nomSysteme() => switch (Platform.operatingSystem) {
    'android' => 'Android',
    'ios' => 'iOS',
    final String autre => autre,
  };
}

/// Un nom **déjà connu** : celui que la racine de composition a lu au démarrage
/// avec `device_info_plus`, ou celui qu'un test impose.
///
/// POURQUOI il vit dans `lib/` : le test du parcours M1 vérifie que `appareil`
/// **part** sur les trois appels qui l'acceptent, et il lui faut une valeur
/// stable. Une doublure écrite dans `test/` aurait obligé chaque test à la
/// réécrire.
class NomAppareilFixe implements NomAppareil {
  const NomAppareilFixe(this._valeur);

  final String? _valeur;

  @override
  String? valeur() => _valeur;
}
