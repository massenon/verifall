# Application mobile Flutter — documentation d'implémentation

Cette documentation existe pour une raison précise : permettre à quelqu'un qui
n'a jamais vu Vérif'All d'écrire l'application mobile citoyenne **sans rien
inventer**. Elle ne décrit pas une intention, elle décrit un produit qui existe
déjà — sous la forme d'un simulateur interactif de 30 écrans.

## La source, et la règle qui prime

`prototype_html/mobile/index.html` est un simulateur autonome, interactif, à
état réel. Chaque écran y porte un panneau « Transposition Flutter » qui donne
sa route `go_router`, sa page, son provider Riverpod, son arbre de widgets, ses
appels `/api/v1`, ses composants du design system et son point de vigilance.

**On transpose, on n'invente pas.** Aucun écran, aucun champ, aucun endpoint,
aucun composant de cette documentation ne sort d'ailleurs que du simulateur, du
CSS citoyen (`public/assets/css/citizen.css`) ou des décisions déjà consignées
dans `docs/ARBITRAGES.md`. Là où une maquette paraît perfectible, on l'écrit
dans les réserves (section 9) — on ne la corrige pas en silence.

Ouvrir le simulateur :

```bash
cd prototype_html/mobile
python -m http.server 8777      # puis http://127.0.0.1:8777/index.html
```

Chaque écran a une ancre — `index.html#/home`, `index.html#/verify`,
`index.html#/hub`. Ces ancres sont citées écran par écran en section 3 ; elles
sont la preuve que rien n'a été ajouté.

## La seconde source : le contrat d'API

**`docs/kb/API_SPECIFICATION.md` est écrit, figé pour la v1, et implémenté.**
C'est l'autorité sur tout ce qui traverse le réseau : jetons, enveloppe de
réponse, catalogue d'erreurs, pagination, identifiants, plafonds de débit,
versionnement, et le contrat de synchronisation hors ligne. Il s'ouvre sur la
phrase qui règle tous les arbitrages : *« ce qui n'y est pas n'existe pas. »*

Quand le simulateur et le contrat divergent, **le contrat l'emporte sur le
réseau, le simulateur l'emporte sur l'écran**. Le simulateur écrit `?page=` ;
l'API pagine par curseur, et `page` n'existe nulle part (arbitrage n° 68). Le
simulateur corrige un quiz question par question ; l'API ne rend le corrigé
qu'à la copie. Chacun de ces écarts est signalé à l'écran concerné en section 3,
et récapitulé en section 9.

**Cinq appels déclarés par le simulateur ne sont pas servis** — les trois
notifications, le retour d'utilité d'un rapport, et le PDF d'un certificat. La
section 9 dit ce que l'application doit faire à la place, écran par écran. Ce ne
sont pas des oublis : ce sont des fonctionnalités qui n'existent pas encore
côté serveur, et les inventer côté client produirait un indicateur qu'on ne
mesure pas.

Les décisions qui accompagnent le contrat sont les **arbitrages n° 68 à 75** de
`docs/ARBITRAGES.md`. Elles sont citées là où elles s'appliquent.

## Ordre de lecture

| # | Fichier | Ce qu'il contient |
|---|---|---|
| 1 | [`01-FONDATIONS.md`](01-FONDATIONS.md) | Versions, arborescence, Riverpod, `go_router`, injection, journalisation, environnements |
| 2 | [`02-DESIGN-SYSTEM.md`](02-DESIGN-SYSTEM.md) | Table de conversion des jetons depuis `citizen.css`, puis chaque composant partagé |
| 3 | [`03-ECRANS-01-AUTHENTIFICATION.md`](03-ECRANS-01-AUTHENTIFICATION.md) | Écrans 01 à 06 |
| 3 | [`03-ECRANS-02-ACCUEIL.md`](03-ECRANS-02-ACCUEIL.md) | Écrans 07 et 08 |
| 3 | [`03-ECRANS-03-VERIFICATION.md`](03-ECRANS-03-VERIFICATION.md) | Écrans 09 à 13 |
| 3 | [`03-ECRANS-04-ACADEMIE.md`](03-ECRANS-04-ACADEMIE.md) | Écrans 14 à 19 |
| 3 | [`03-ECRANS-05-COMMUNAUTE.md`](03-ECRANS-05-COMMUNAUTE.md) | Écrans 20 à 24 |
| 3 | [`03-ECRANS-06-COMPTE.md`](03-ECRANS-06-COMPTE.md) | Écrans 25 à 30 |
| 4 | [`04-RESEAU.md`](04-RESEAU.md) | Client, intercepteurs, jetons, réessais, cache — et ce qui reste suspendu au contrat |
| 5 | [`05-HORS-LIGNE.md`](05-HORS-LIGNE.md) | Ce qui vit sans réseau, ce qui se synchronise, qui gagne en cas de conflit |
| 6 | [`06-TESTS.md`](06-TESTS.md) | Unitaires, widgets, intégration, captures de référence |
| 7 | [`07-LIVRAISON.md`](07-LIVRAISON.md) | Signature, magasins, versionnement, télémétrie de plantage |
| 8 | [`08-PARCOURS-M1-M5.md`](08-PARCOURS-M1-M5.md) | Le découpage en cinq parcours parallélisables |
| 9 | [`09-RESERVES.md`](09-RESERVES.md) | Ce que le simulateur ne dit pas, ce qu'il se contredit, ce qu'il faut trancher |

Le découpage de la section 3 en six chapitres n'est pas cosmétique : il suit les
six groupes du rail du simulateur, et il correspond au découpage en parcours de
la section 8. Un agent qui travaille sur M3 lit le chapitre Académie et n'a
besoin d'aucun autre.

## À lire avant, côté dépôt

Ces quatre documents ne sont pas résumés ici, ils sont supposés lus :

- `docs/AGENTS_REGLES_DU_CHANTIER.md` — les onze règles du chantier. Le §7,
  « ce qu'un écran doit toujours faire », s'applique mot pour mot au mobile.
- `docs/ARBITRAGES.md` — les 67 décisions. **Elles valent pour le mobile.** Une
  décision prise sur le web et défaite sur le téléphone donne deux produits, et
  c'est l'utilisateur qui paie la différence.
- `docs/UX_FLUTTER_LANCEMENT.md` — la note d'architecture d'origine, dont cette
  documentation est le développement complet.
- `docs/CONFORMITE_PROTOTYPE.md` — la méthode de mesure de la fidélité au
  prototype, transposable au mobile par captures de référence (section 6).
