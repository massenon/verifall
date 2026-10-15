# Vérif'All — application mobile

L'application citoyenne de Vérif'All. Flutter 3.44 / Dart 3.12, Material 3,
Riverpod, `go_router`, Dio, Drift.

Ce dépôt contient **le socle, et rien que le socle**. Les trente écrans du
produit seront écrits par cinq parcours parallèles, décrits en section
[« Comment les cinq parcours s'y greffent »](#comment-les-cinq-parcours-sy-greffent).

---

## Les deux sources, et laquelle l'emporte

| Source | Autorité sur |
|---|---|
| `prototype_html/mobile/index.html` (dépôt Laravel) | **Les écrans.** 30 écrans interactifs, chacun avec son panneau « Transposition Flutter » : route, page, provider, arbre de widgets, appels API, composants du DS |
| `docs/kb/API_SPECIFICATION.md` (dépôt Laravel) | **Le réseau.** Contrat v1 figé et implémenté. « Ce qui n'y est pas n'existe pas » |

Quand les deux divergent : **le contrat l'emporte sur le réseau, le simulateur
l'emporte sur l'écran.** La documentation d'implémentation est dans
`docs/flutter/` du dépôt Laravel, en quinze fichiers.

**On transpose, on n'invente pas.** Aucune couleur, aucun rayon, aucun
espacement, aucune forme de réponse ne sort d'ailleurs que de ces deux sources.

---

## Démarrer

```bash
# 1. Dependances
flutter pub get

# 2. Generation Drift (apres toute modification d'un fichier .drift ou d'une table)
dart run build_runner build

# 3. Configuration : copier l'exemple et remplir
cp config/exemple.json config/dev.json      # voir config/README.md

# 4. Lancer
flutter run --dart-define-from-file=config/dev.json
```

### Qualité

```bash
dart format .            # aucune difference attendue
flutter analyze          # 0 issue — c'est la barre, pas un objectif
flutter test             # tous les tests, pas seulement les nouveaux
```

---

## Ce qui est GELÉ

Le socle est écrit une fois, validé, et **les cinq parcours le lisent sans le
modifier**. Ce n'est pas de la rigidité : cinq parcours écrits en parallèle sur
une base qui bouge encore, c'est cinq conflits de fusion par jour, tous les
jours.

| Zone | Contenu |
|---|---|
| `lib/core/theme/` | Jetons de couleur, rayons, grille, cibles tactiles, typographie, transitions, `ThemeData` clair complet |
| `lib/core/config/` | Configuration par environnement, trois saveurs, session de recette |
| `lib/core/network/` | Client Dio, cinq intercepteurs, jetons, réessais, débit, contrôleur de session |
| `lib/core/errors/` | Hiérarchie d'échecs du domaine + traduction des treize codes du contrat |
| `lib/core/logging/` | Logger unique et expurgation |
| `lib/core/router/app_router.dart` | `ShellRoute` à cinq onglets, gardes, liens profonds, **six imports de routes en place** |
| `lib/data/models/` | Enveloppe, pagination par curseur, catalogue d'erreurs, jeton, auteur, téléversement |
| `lib/data/datasources/local/database.dart` | Base Drift, **six imports de schémas en place** |
| `lib/data/datasources/local/pending_ops.dart` | La file d'écriture et son moteur de rejeu |
| `lib/presentation/shared/` | Les composants `Vf*` |

### La règle de propriété

> **Un besoin de modification du socle est une demande adressée au responsable du
> socle, pas une modification directe.** C'est contraignant, et c'est le prix du
> parallélisme.

Et son corollaire : **un composant partagé qui manque ne se crée pas dans un
parcours.** Si deux parcours ont besoin du même widget, il appartient au socle.
Un composant dupliqué dans deux dossiers diverge en une semaine.

---

## Ce qui n'est PAS gelé

Tout le reste, et c'est là que le travail commence :

```
lib/presentation/features/{auth,home,verify,lms,community,profile}/
lib/domain/{entities,repositories,usecases}/
lib/data/{datasources/remote,repositories}/
lib/core/router/routes_{auth,home,verify,lms,community,profile}.dart
lib/data/datasources/local/schema/schema_{auth,home,verify,lms,community,profile}.drift
test/features/**
```

---

## Comment les cinq parcours s'y greffent

### Les deux fichiers d'agrégation, et pourquoi ils ne se rouvrent jamais

`app_router.dart` et `database.dart` sont les deux **seuls** endroits où les cinq
parcours devraient normalement écrire tous ensemble. On l'évite ainsi :

- chaque parcours écrit **son propre fichier** de routes, et l'agrégateur importe
  les six **dès le socle**, avec des listes vides pour ceux qui n'existent pas
  encore ;
- même mécanique pour Drift : un fichier `.drift` par parcours, référencé
  d'avance par `include:`. Les tables manquantes sont créées à l'ouverture, sans
  bump de version.

Résultat : **personne ne rouvre l'agrégateur, et les cinq branches se fusionnent
sans conflit.**

### Où pose-t-on une route ?

Chaque `routes_*.dart` expose **deux** listes, et le choix entre elles n'est pas
cosmétique :

| Liste | Pour |
|---|---|
| `<module>ShellRoutes` | Les écrans qui portent **la barre basse** |
| `<module>RootRoutes` | Ceux qui ne la portent pas — lecture d'un rapport, d'une leçon, d'un certificat. Ils « ne doivent pas la faire réapparaître à mi-parcours » |

Les chemins se prennent dans `VfRoutes`, jamais écrits à la main : c'est ce qui
permet aux cinq parcours de se renvoyer les uns aux autres **par la navigation**,
sans jamais d'import croisé entre deux dossiers `features/`.

### Et la session ? M2 à M5 n'attendent pas M1

C'est ce qui rend le parallélisme réellement possible. `config/dev.json` avec
`VF_DEV_SESSION: true` injecte un jeton de recette et un profil sans passer par
les six écrans de M1. Un bandeau violet **SESSION DE RECETTE** le rappelle en
permanence, et elle est **refusée en production par construction**.

### Le découpage

| Parcours | Dossier | Écrans |
|---|---|---|
| M1 Authentification | `features/auth/` | 01 à 06 |
| M2 Vérifier | `features/home/`, `features/verify/` | 07 à 13 |
| M3 Académie hors ligne | `features/lms/` | 14 à 19 |
| M4 Hub et forum | `features/community/` | 20 à 24 |
| M5 Profil, unités, parrainage | `features/profile/` | 25 à 30 |

---

## Ce que le socle n'a pas fait, et pourquoi

### Aucun écran de produit

C'est la règle. Deux pages **système** existent malgré tout, et elles
n'appartiennent à aucun des cinq :

- **`VfUpdateRequiredPage`** — l'écran bloquant du `426 client_obsolete`. Le
  contrat exige qu'il existe **avant qu'on en ait besoin** : « le jour où le
  plancher est relevé, on ne peut plus déployer de code sur les téléphones
  concernés » ;
- **`VfRouteFallbackPage`** — le repli de route, exigé par `go_router` et par les
  liens profonds vers un contenu dépublié. Son texte ne dit jamais « supprimé » :
  un 404 signifie « pas pour vous, ou pas là ».

### Ce qui reste ouvert

| Sujet | État | Qui tranche |
|---|---|---|
| **Les fichiers de police** | Non déposés. Flutter retombe sur la police système et **les captures de référence ne sont pas exploitables** tant qu'ils manquent | Porteur du projet (licences). Voir `assets/fonts/README.md` |
| **Les valeurs techniques de `verdict`** | Le contrat n'en publie qu'une (`"false"`). Le socle fournit `VfVerdictTone` avec un cas neutre ; la correspondance `valeur → ton` se fait **une fois**, dans M2, contre le serveur réel | M2 |
| **L'écran « compte en cours de suppression »** | La garde de route existe et pointe vers `/compte-en-suppression` ; **la page reste à écrire** — comme `/connexion` appartient à M1 | M5 |
| **Les trois saveurs (Gradle / Xcode)** | Le code les gère (`VF_ENV`), le paramétrage natif n'est pas fait | Livraison |
| **L'épinglage de certificat** | Volontairement absent. « Soit on l'implémente avec une clé de secours et une procédure de rotation écrite, soit on s'en passe » | Infrastructure |
| **Les couleurs Mobile Money de l'écran 26** | Le design system n'en donne pas les valeurs. Les deviner reviendrait à inventer un jeton | M5, avec une maquette |
| **Notifications, retour d'utilité, PDF de certificat** | **Cinq appels du simulateur ne sont servis par aucun endpoint** (contrat §11). Ils ne figurent pas dans `ApiEndpoints` : les écrire « en attendant » produirait un client qui appelle du vide | Serveur |

---

## Quelques décisions du socle, et leur raison

**Il n'y a pas de renouvellement de jeton.** Le simulateur décrit un « refresh
silencieux sur 401 » ; le contrat l'écarte délibérément (§1.4). Sur 401 :
purger le jeton, purger la base locale, présenter la connexion. L'intercepteur
d'authentification tient en trente lignes, et c'était l'intention.

**Le solde d'unités ne vient jamais du cache.** Avec l'état d'une analyse en
cours et le résultat d'un quiz, ce sont les trois écrans qu'on relit
*précisément* parce qu'on attend un changement. `VfPointsPill` affiche un tiret
tant que le solde n'a pas été relu — jamais un zéro, qui ferait croire à un
compte vide.

**La clé d'idempotence est générée à l'acte, pas à la tentative.** C'est toute la
sécurité de la file d'écriture : « une réponse perdue est indiscernable d'une
requête perdue », et sans clé stable, la personne est débitée deux fois pour une
seule question.

**La purge à la déconnexion est totale.** Stockage sécurisé **et** base locale.
Sur un téléphone partagé — le cas ordinaire au Bénin — un cache de lecture oublié
est une fuite de données. Un tri « ce qui est sensible / ce qui ne l'est pas » se
trompe toujours au moins une fois.

**Le journal ne porte ni corps, ni en-tête, ni chemin brut.** Méthode, gabarit de
chemin, code, durée. Un slug de sujet de forum est une phrase écrite par
quelqu'un ; un corps de requête contient des jetons, des mots de passe, des codes
à usage unique et du contenu soumis à vérification.

**L'application reste en thème clair.** Pas de palette sombre, « pas même au cas
où » : une seconde palette non validée finit toujours par s'afficher chez
quelqu'un.
