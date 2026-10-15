# Signature et magasin de clés (keystore)

Référence éditoriale : `docs/flutter/07-LIVRAISON.md` §7.1, dépôt `verifall_laravel`.
Ce document donne les commandes exactes pour ce dépôt précis.

## Ce qui est déjà câblé (rien à écrire)

`android/app/build.gradle.kts` lit `android/key.properties` (non versionné,
`.gitignore:61` et `android/.gitignore:12`) et bascule la signature de
`release` dessus s'il existe, sinon sur la clé de débogage. Un garde-fou lève
une erreur Gradle explicite si quelqu'un lance `flutter build appbundle` **sans**
magasin de clés — impossible de publier un bundle signé par accident avec la
clé de débogage, qui est la cause la plus fréquente d'application qu'on ne peut
plus jamais mettre à jour.

`flutter run --release` continue de fonctionner sans magasin de clés : seul
`bundleRelease` (la tâche derrière `flutter build appbundle`) est bloqué.

Le modèle à recopier est `android/key.properties.exemple`.

## Étape 1 — Générer le magasin de clés (une seule fois, pour toute la vie de l'app)

```bash
keytool -genkey -v -keystore ~/verifall-televersement.jks \
  -keyalg RSA -keysize 2048 -validity 10000 -alias televersement
```

`keytool` fait partie du JDK — déjà installé avec Android Studio ou Flutter.
Répond aux questions interactives (nom, organisation, pays…) ; elles finissent
dans le certificat et ne sont pas secrètes, contrairement au fichier `.jks`
lui-même et à ses mots de passe.

**Ce fichier `.jks` est un secret d'infrastructure, pas un fichier de projet :**

- jamais dans le dépôt Git (il ne l'est pas aujourd'hui — vérifié) ;
- sauvegardé à au moins deux endroits indépendants (ex. gestionnaire de mots de
  passe de l'équipe + stockage hors ligne) ;
- **le perdre, c'est perdre la capacité de publier une mise à jour** — les
  personnes qui ont déjà installé l'application ne recevront plus jamais rien,
  et il n'existe aucune procédure de récupération côté Google pour la clé de
  distribution si la « signature d'application Play » (étape 2) n'a pas été
  activée.

## Étape 2 — Créer `android/key.properties`

```bash
cp android/key.properties.exemple android/key.properties
```

Puis remplir :

```properties
storeFile=/chemin/absolu/vers/verifall-televersement.jks
storePassword=<le mot de passe choisi à l'étape 1>
keyAlias=televersement
keyPassword=<le mot de passe de la clé — souvent le même que storePassword>
```

Ce fichier reste **local à chaque poste qui construit un bundle de
publication** — il n'est pas synchronisé entre machines par Git. Sur une CI,
les mêmes valeurs arrivent par variables d'environnement/secrets du pipeline,
pas par un fichier commité.

## Étape 3 — Activer la « signature d'application Play »

C'est la Console Play qui le demande, à la **première** publication :

1. Console Play → votre application → **Production** (ou tout premier canal de
   test) → **Intégrité de l'application** → **Signature d'application**.
2. Accepter que Google conserve la **clé de distribution**. Le magasin de clés
   généré à l'étape 1 devient la **clé de téléversement** : c'est elle que
   chaque nouveau bundle signe, Google la ré-signe avec la clé de distribution
   avant de la livrer aux téléphones.

**C'est le seul filet contre la perte du fichier `.jks`** : une clé de
téléversement perdue ou compromise se remplace (procédure Google, sur preuve
d'identité) ; une clé de distribution perdue ne se remplace jamais. Sans cette
étape, le fichier généré à l'étape 1 EST la clé de distribution, et sa perte
est définitive.

## Étape 4 — Vérifier

```bash
flutter build appbundle --dart-define-from-file=config/prod.json
```

- **Sans** `android/key.properties` : la construction échoue avec le message
  du garde-fou (`android/app/build.gradle.kts:130-146`), pas un bundle signé
  en débogage silencieusement.
- **Avec** : le bundle sort dans
  `build/app/outputs/bundle/release/app-release.aab`, signé avec la clé de
  téléversement.

## iOS — hors scope de ce document

Vérifié le 9 octobre 2026 : **le dossier `ios/` de ce dépôt n'a pas été
configuré pour la distribution** (pas de profil de provisionnement, pas
d'identifiants App Store Connect présents dans ce dépôt). La demande d'origine
porte sur le Play Store ; la signature iOS (certificats, profils de
distribution, capacités déclarées) reste à traiter séparément le jour où la
publication App Store est engagée — voir §7.1 de `07-LIVRAISON.md` pour ce que
le produit en attend déjà.
