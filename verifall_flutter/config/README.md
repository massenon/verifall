# Configuration par environnement

Trois environnements — développement, recette, production — et **aucune valeur en
dur dans le code**, exactement comme le back interdit les secrets hors `.env`.

## Comment ça marche

Les valeurs arrivent par `--dart-define-from-file`, **un fichier par
environnement, non versionné**. `lib/core/config/app_config.dart` les lit **une
seule fois** au démarrage ; le reste du code lit cette classe, jamais
l'environnement.

```bash
flutter run --dart-define-from-file=config/dev.json
flutter build appbundle --dart-define-from-file=config/prod.json
```

Copiez `exemple.json` vers `dev.json`, `staging.json`, `prod.json`. Les trois
sont dans `.gitignore` : **un fichier de configuration commité est un secret
commité**.

## Ce que la configuration porte

| Clé | Rôle |
|---|---|
| `VF_ENV` | `development`, `staging` ou `production`. Une valeur inconnue retombe sur `production` — le repli le plus sûr est le plus fermé |
| `VF_API_BASE_URL` | La racine de l'API, **sans** `/api/v1` : la version majeure appartient au contrat, pas à la configuration |
| `VF_WEB_BASE_URL` | La racine du **site public**, sans barre finale. Vide, deux écrans se dégradent proprement : la page de vérification d'un certificat s'affiche sans être cliquable, et la ligne « Politique de confidentialité » n'apparaît pas dans les Réglages. Renseignée, les deux deviennent des liens (arbitrage n° 138) |
| `VF_FEATURE_NOTIFICATIONS`, `VF_FEATURE_RETOUR_UTILITE` | Les deux drapeaux de M2. Faux par défaut : les endpoints correspondants n'existent pas en v1, et l'écran masque plutôt que de promettre |
| `VF_REVERB_*` | Le service temps réel |
| `VF_TELEMETRY_DSN` | Le canal de télémétrie de plantage. Vide = éteinte |

## Ce qu'elle ne porte PAS

**Les coûts en unités, les prix des packs, les seuils de quiz.** Ces valeurs
viennent du serveur, et le contrat est catégorique : « accepter un prix venu du
téléphone viderait les quotas en une nuit » (API_SPECIFICATION §7.4).

**Aucune clé d'API tierce.** Tout ce qui est facturé à l'appel — moteurs
d'analyse, recherche — passe par le serveur. Une clé embarquée dans un paquet
installable est une clé publique.

## La session de développement

C'est elle qui rend le parallélisme des cinq parcours possible : M2 à M5 ont
besoin d'un compte connecté, et si chacun attend que M1 livre ses six écrans, il
n'y a plus de parallélisme du tout.

| Clé | Rôle |
|---|---|
| `VF_DEV_SESSION` | `true` pour injecter une session sans passer par M1 |
| `VF_DEV_TOKEN` | Un jeton **de recette**, émis par le serveur de recette |
| `VF_DEV_USER_*` | Le profil affiché tant que `GET /me` n'est pas branché |

**Elle est refusée en production par construction.** `AppConfig` ne peut pas
produire `devSessionEnabled: true` quand `VF_ENV` vaut `production` — le clamp
est dans le constructeur, dans `copyWith`, et un garde-fou lève au démarrage. Le
même raisonnement que l'adaptateur simulé du back, qui « refuse de tourner en
production » (arbitrage n° 19).

Quand elle est active, un bandeau violet **SESSION DE RECETTE** s'affiche en
permanence : une capture d'écran prise sur un compte de recette et prise pour un
compte réel fait chercher un défaut qui n'existe pas.

## Trois saveurs d'application

Identifiants de paquet distincts, icônes et noms distincts. Il faut pouvoir
installer la recette **à côté** de la production sur le même téléphone ; sans
cela, tester une correction oblige à désinstaller son propre compte.

Le paramétrage des saveurs (Gradle, Xcode) relève de la livraison
(`docs/flutter/07-LIVRAISON.md`) et n'est pas encore fait — voir le README du
dépôt, section « Ce qui reste ouvert ».
