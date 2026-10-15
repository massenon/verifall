/// Les trois environnements, et rien d'autre (§1.7).
///
/// POURQUOI trois saveurs distinctes et pas un simple drapeau : il faut pouvoir
/// installer la recette **à côté** de la production sur le même téléphone. Sans
/// cela, tester une correction oblige à désinstaller son propre compte.
enum AppEnvironment {
  development('development'),
  staging('staging'),
  production('production');

  const AppEnvironment(this.key);

  /// La valeur attendue dans `VF_ENV`.
  final String key;

  /// Lecture tolérante : une valeur inconnue retombe sur [production].
  ///
  /// POURQUOI le repli est la production et non le développement : une faute de
  /// frappe dans un fichier de configuration ne doit jamais **ouvrir** des
  /// facilités de développement. Le repli le plus sûr est le plus fermé.
  static AppEnvironment parse(String? raw) {
    for (final AppEnvironment env in AppEnvironment.values) {
      if (env.key == raw) {
        return env;
      }
    }
    return AppEnvironment.production;
  }

  bool get isProduction => this == AppEnvironment.production;

  /// Vrai pour développement et recette. Sert de garde unique aux facilités qui
  /// n'ont pas le droit d'exister en production (session de recette, journaux
  /// sur la console).
  bool get allowsDeveloperTools => !isProduction;
}
