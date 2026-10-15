# 6. Tests

## 6.1 Le principe

La règle du chantier §8 est directe : un module n'est pas terminé tant que cinq
points ne sont pas verts, et le troisième est **ouvrir l'écran pour de vrai**.
Elle a été écrite après avoir découvert que neuf écrans web répondaient HTTP 200
en affichant « le contenu de cette page arrive à son sprint », suite de tests
verte du début à la fin.

La transposition mobile de cette leçon tient en une phrase : **un widget qui se
monte sans lever d'exception n'est pas un écran transposé.** Ce qui le mesure,
c'est la capture de référence (§6.5), pas le test de widget.

Deuxième règle héritée, et elle est de discipline : **on n'adapte jamais un test
pour qu'il passe.** C'est la charte de l'agent `redacteur-tests` côté web ; elle
vaut ici.

## 6.2 Barre de qualité

Ce qui doit être vert avant toute livraison :

| Contrôle | Exigence |
|---|---|
| `dart format` | Aucune différence |
| `flutter analyze` | **0 issue** — la note d'architecture le fixe explicitement |
| `flutter test` | Tous les tests, pas seulement les nouveaux |
| Captures de référence | Aucune dérive non expliquée |
| Fluidité des listes | **≥ 60 fps** — pagination et `cached_network_image` |
| Ouverture réelle sur un appareil | Un écran par parcours, au minimum |

## 6.3 Tests unitaires

Ils portent sur `domain/` et sur les parties de `data/` qui transforment.

Ce qui mérite vraiment un test unitaire, parce que l'erreur y est silencieuse :

- **les machines à états** — quiz, formulaire de vérification, contrôleur
  d'authentification. Une transition manquante ne se voit pas, elle bloque ;
- **la file `pending_ops`** — ordre, recul, abandon, retrait d'une opération
  orpheline. C'est le composant le plus difficile à reproduire à la main ;
- **la traduction des erreurs** — chaque code produit la bonne catégorie ;
- **le compteur de durée active** de la leçon : il doit se figer en arrière-plan.
  C'est un test de cycle de vie, pas un test d'horloge ;
- **les énumérations affichées.** Le back a été mordu par un `match` sans branche
  par défaut (arbitrage n° 38) : ajouter un cas faisait planter une page entière,
  à l'exécution seulement. L'équivalent Dart est un `switch` non exhaustif sur un
  `enum`.
  Le contrat désamorce en partie le piège — **valeur technique et libellé français
  voyagent ensemble**, et le client affiche le libellé sans le fabriquer. Il reste
  à tester deux choses : qu'**une valeur inconnue ne fait pas tomber l'écran**
  (elle affiche le libellé reçu et retombe sur un style neutre), et que chaque
  valeur connue a bien sa couleur et son icône. **Test paramétré sur toutes les
  valeurs**, plus un cas « valeur inattendue ».
- **le décodage de l'enveloppe** — `{ "data": null }` n'est pas une erreur ; un
  champ inconnu est ignoré ; un champ absent se distingue d'un champ présent et
  nul. Ces trois-là cassent en production et jamais en démonstration.
- **la pagination par curseur** — `next_cursor` renvoyé tel quel, fin de liste sur
  `has_more: false`, aucun usage de `page`.
- **`Idempotency-Key`** — une clé par acte et non par tentative, réutilisée à
  chaque rejeu ; un `Idempotent-Replay: true` traité comme un succès.

Ce qui **ne mérite pas** de test unitaire : la sérialisation générée, les
constantes de thème, et tout ce qui ne fait que déléguer.

## 6.4 Tests de widget

Un par écran, au minimum, et ils vérifient **les états, pas la mise en page** —
la mise en page est du ressort des captures.

Pour chaque écran, quatre scénarios obligatoires :

1. **chargement** — les squelettes sont là, l'écran ne plante pas sur des données
   absentes ;
2. **succès** — le contenu attendu est présent ;
3. **vide** — le bon état vide s'affiche. Là où il y en a deux (« aucun résultat »
   et « rien encore fait »), les deux sont testés séparément : c'est justement
   parce qu'ils sont faciles à confondre qu'ils cassent ;
4. **erreur** — le message correspond à la catégorie, et une sortie existe.

Trois scénarios supplémentaires, spécifiques et à ne pas oublier :

- **un élément verrouillé affiche sa condition** (leçon, quiz, badge). Un cadenas
  nu doit faire échouer un test ;
- **un bouton dit ce qui va se passer** : « Reprendre » sur un cours entamé,
  « Commencer » sur un cours neuf ;
- **la barre de fiabilité prend la couleur du verdict** (arbitrage n° 32). Ce
  défaut exact a existé côté web, il a été corrigé, et il est verrouillé par un
  test. Faire de même ici.

Les providers sont remplacés par des surcharges Riverpod ; **aucun test de widget
ne touche le réseau ni la base**.

## 6.5 Captures de référence — la mesure de fidélité

C'est l'équivalent mobile de `docs/CONFORMITE_PROTOTYPE.md`, et c'est ce qui
répond à la onzième règle du chantier : **la fidélité au prototype se mesure**.

### Ce qu'on capture

- **chaque composant partagé** (`presentation/shared/`) dans chacun de ses
  états : un bouton dans ses quatre variantes, une pastille de verdict dans ses
  quatre valeurs, un état vide, un squelette. La note d'architecture les nomme
  « golden tests des composants DS » ;
- **chaque écran** dans son état nominal et dans son état vide.

### Sous quelles conditions

Fixer l'environnement, sinon la capture dérive sans raison :

- deux tailles, celles des deux châssis du simulateur : **390 × 844** et
  **412 × 880** ;
- densité de pixels fixée ;
- **polices embarquées** — c'est indispensable : un test de capture qui charge
  une police depuis le réseau rend des rectangles ;
- facteur d'échelle du texte à 1,0 pour la référence, plus **une capture par
  écran à 2,0** pour attraper les débordements. C'est le pendant de l'audit
  responsive du web, qui a trouvé de vrais défauts que les tests ne voyaient pas.

### Comment on s'en sert

La comparaison se fait contre le **simulateur ouvert côte à côte**, pas contre la
capture précédente. Une capture précédente valide une régression ; elle ne valide
pas une transposition.

Et comme côté web, tout écart au simulateur se **consigne** dans
`docs/ARBITRAGES.md`, avec sa raison. Un écart non écrit est un bug qui attend
d'être « corrigé » par quelqu'un qui ne saura pas qu'il était voulu.

## 6.6 Tests d'intégration

Sur appareil ou émulateur, contre un serveur de recette. Ils couvrent les
parcours de bout en bout, un par parcours de la section 8 :

| Parcours | Chemin testé |
|---|---|
| M1 | Splash → inscription → code → tableau de bord, puis déconnexion et **vérification que le cache est vide** |
| M2 | Saisie d'un texte → coût annoncé → soumission → suivi → rapport → partage d'image |
| M3 | Inscription à un cours → leçon avec sa durée minimale → quiz → certificat |
| M4 | Hub : trois rubriques, filtre par type, ouverture d'une offre clôturée ; forum : publication, vote |
| M5 | Profil → unités → feuille de recharge → retour d'application → relecture du solde |

Trois scénarios transverses, qui trouvent plus de défauts que tous les autres
réunis :

1. **Mode avion au milieu du parcours** — la file se remplit, l'écran le dit, et
   le retour du réseau rejoue dans le bon ordre.
2. **Réponse perdue sur une soumission de vérification** — le rejeu porte la même
   `Idempotency-Key`, le serveur renvoie la réponse mémorisée, et **le solde n'a
   été débité qu'une fois**. C'est le test qui protège l'argent de quelqu'un.
3. **Application tuée puis relancée** pendant une analyse en cours — l'écran 12
   la retrouve avec son état. (Pas de notification en v1 : voir réserve C.2.)
4. **Lien profond application fermée** — vers un article dépublié, une offre
   archivée, un cours retiré. Les trois doivent afficher un repli, pas une page
   blanche.
5. **401 en cours de session** — purge du jeton **et** de la base locale, retour à
   la connexion, **sans boucle de renouvellement**.
6. **426 `client_obsolete`** — l'écran bloquant s'affiche, quel que soit l'écran
   en cours.

## 6.7 Accessibilité

Un contrôle automatisé par écran : cibles tactiles ≥ 44, contraste, présence
d'un libellé sur chaque élément interactif. Ces trois-là se testent
mécaniquement et attrapent l'essentiel.

Ce qui ne se teste qu'à la main, et qui doit être fait une fois par parcours :
parcourir un écran entier au lecteur d'écran, dans l'ordre, et vérifier que
l'information arrive dans le bon ordre — le verdict avant le titre, l'état vide
avant son bouton, le coût avant l'envoi.

## 6.8 Données de test

**Jamais les données du simulateur en dur dans un test.** Elles portent des
verdicts, des offres nominatives et des articles ; le §2 des règles du chantier
l'interdit — figer un verdict revient à publier une vérification qui n'a jamais
eu lieu.

Des fabriques, comme côté back : *« factories dédiées, jamais les seeders
démo »*. Une fabrique produit une vérification neutre, un cours neutre, une offre
neutre — et le test dit lui-même ce qu'il attend.
