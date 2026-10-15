# Version et construction du bundle (AAB)

Référence éditoriale : `docs/flutter/07-LIVRAISON.md` §7.3, dépôt `verifall_laravel`.

## Où vit le numéro de version — une seule source

```yaml
# pubspec.yaml, ligne 9
version: 1.0.0+1
```

`1.0.0` est le **nom de version** (sémantique, visible par les utilisateurs
dans le Play Store et dans l'écran Réglages). `1` après le `+` est le **numéro
de build** (`versionCode` côté Android) — invisible pour l'utilisateur, mais
c'est lui que Play compare pour savoir si un bundle est plus récent.

`android/app/build.gradle.kts:57-60` lit ces deux valeurs via
`flutter.versionName`/`flutter.versionCode` — **il n'y a rien à dupliquer ni à
synchroniser à la main dans `android/`**. `android/local.properties` contient
aujourd'hui `flutter.versionName=1.0.0` et `flutter.versionCode=1` : ce sont
des valeurs **mises en cache par l'outillage Flutter** à partir de
`pubspec.yaml`, pas une seconde source de vérité — ce fichier n'est de toute
façon pas versionné (`android/.gitignore`) et se régénère tout seul.

L'écran Réglages (30, M5) lit la version depuis le paquet installé via
`package_info_plus`, jamais une chaîne écrite en dur — vérifié dans
`pubspec.yaml:29` et sa justification en commentaire. Une version qui ment à
l'écran Réglages est la première chose qu'on demande à quelqu'un qui signale
un défaut ; ce risque est déjà fermé.

## Faire avancer la version

Avant **chaque** soumission, même pour un correctif mineur :

```bash
# Exemple : correctif sans changement visible
# 1.0.0+1 -> 1.0.0+2
#
# Exemple : nouvelle fonctionnalité visible
# 1.0.0+1 -> 1.1.0+2
```

Éditer `pubspec.yaml`, rien d'autre. **Le `versionCode` (après le `+`) doit
être strictement croissant et jamais réutilisé** — les deux magasins (Play et
App Store) refusent un numéro déjà vu, y compris celui d'un build jamais
publié mais déjà téléversé en interne. En cas de doute sur le dernier numéro
réellement soumis, vérifier dans la Console Play → Production → Historique des
versions plutôt que de deviner.

## Construire le bundle de publication

```bash
flutter build appbundle --dart-define-from-file=config/prod.json
```

- Exige `android/key.properties` (voir `01-SIGNATURE-ET-KEYSTORE.md`) —
  échoue sinon, volontairement.
- `--dart-define-from-file=config/prod.json` est **obligatoire** : sans lui,
  `AppConfig.apiBaseUrl` est une chaîne vide, et
  `assertNoDeveloperSessionInProduction()` (`lib/core/config/app_config.dart`)
  refuse de démarrer l'application en environnement `production` sans adresse
  d'API — la construction réussirait, l'app s'installerait, et chaque appel
  échouerait sans qu'aucun message n'explique pourquoi, exactement le défaut
  que ce garde-fou existe pour fermer.
- Sortie : `build/app/outputs/bundle/release/app-release.aab`.

### Ne pas confondre avec l'APK

```bash
flutter build apk --dart-define-from-file=config/prod.json
```

Produit un `.apk`, pas un `.aab`. Utile pour un test manuel sur un appareil
physique sans passer par Play, **mais le Play Store exige le format App
Bundle** pour toute soumission — voir `07-LIVRAISON.md` §7.1. La vérification
`bundleRelease` du garde-fou de signature ne se déclenche que sur la tâche de
bundle, précisément pour ne pas bloquer ce cas d'usage local.

## Avant de construire pour de vrai

```bash
flutter analyze          # 0 issue
flutter test              # tous les tests passent
dart format . --set-exit-if-changed   # aucun fichier reformate
```

Ces trois commandes sont déjà la barre de qualité du projet (voir le README
racine) — les relancer juste avant une construction de publication coûte
quelques minutes et évite de découvrir un défaut après la revue Google, qui
prend plusieurs jours à chaque aller-retour.
