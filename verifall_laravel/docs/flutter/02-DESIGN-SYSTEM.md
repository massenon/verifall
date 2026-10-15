# 2. Design system

Le design system n'est pas à concevoir : il existe. Il vit dans
`public/assets/css/citizen.css` pour le web, et le simulateur mobile en reprend
les jetons **à l'identique** dans le bloc `.screen-shell` de
`prototype_html/mobile/index.html`. Le travail consiste à traduire, pas à
choisir.

Deux conséquences pratiques à garder en tête tout du long :

- **L'application reste en thème clair.** Le simulateur expose un bouton
  « Thème », mais son propre bandeau précise qu'il ne concerne que le banc
  d'essai : *« l'application reste en thème clair, conforme au DS »*. Ne pas
  écrire de palette sombre, pas même « au cas où » — une seconde palette non
  validée finit toujours par s'afficher chez quelqu'un.
- **Les polices sont des ressources locales.** Le pied du panneau de
  transposition l'écrit : *« Polices Inter + Instrument Sans (assets locaux,
  jamais réseau) »*. Un premier lancement qui attend une police distante affiche
  un écran nu sur une connexion lente, c'est-à-dire dans le cas d'usage
  principal du produit.

## 2.1 Couleurs

Les valeurs ci-dessous sont celles de `citizen.css` et du simulateur, sans
exception. Elles se déclarent une seule fois, dans `core/theme/`, sous forme de
constantes nommées ; **aucune couleur littérale ailleurs dans le code**.

| Rôle | Jeton CSS | Valeur | Emploi |
|---|---|---|---|
| Primaire | `--primary` | `#09B850` | Actions, états actifs, progression, onglet courant |
| Primaire foncé | `--primary-dark` | `#079A43` | Texte sur fond primaire clair |
| Primaire clair | `--primary-soft` | `#E6F8EE` | Fonds d'encart pédagogique, pastilles `tag.prim` |
| Forêt | `--forest` | `#063B2A` | Fonds pleins : connexion, carte de solde, encarts sombres, cadre du certificat |
| Rouge de marque | `--brandred` | `#D6293E` | Bouton « Vérifier », zone de danger, échéances dépassées |
| Encre | `--ink` | `#111827` | Titres et texte fort |
| Ardoise | `--slate2` | `#64748B` | Texte secondaire, icônes inactives |
| Filet | `--line` | `#E5E7EB` | Bordures de carte, séparateurs, piste de progression |
| Canevas | `--canvas` | `#F6F8F7` | Fond d'écran général |
| Surface | `--surface` | `#FFFFFF` | Cartes, barres, feuilles |
| Barre latérale | `--sidebarbg` | `#1F2124` | Fond du tiroir, avatar par défaut |
| Verdict vrai | `--vtrue` | `#16A34A` | |
| Verdict faux | `--vfalse` | `#DC2626` | |
| Verdict à investiguer | `--vinvest` | `#D97706` | « TROMPEUR », échéance proche |
| Information | `--vinfo` | `#2563EB` | « EN COURS », séries secondaires des graphiques |
| Expérience | `--xp` | `#7C3AED` | Points d'expérience |

Trois couleurs supplémentaires apparaissent dans le simulateur pour des marques
tierces, et **seulement** là : le vert WhatsApp `#25D366` sur les boutons de
partage, et les couleurs des trois opérateurs Mobile Money dans la feuille de
recharge (écran 26). Elles ne rejoignent pas la palette : ce sont des logos, pas
des jetons.

## 2.2 Rayons, ombre, grille

| Jeton | Valeur | Usage |
|---|---|---|
| `--r-sm` | 10 | Petits boutons, pastilles rectangulaires |
| `--r-md` | 12 | **Boutons**, champs, encarts de liste |
| `--r-lg` | 16 | **Cartes** |
| — | 999 | Pastilles, avatars, interrupteurs, badges de verdict |
| — | 22 / 22 / 0 / 0 | Feuille inférieure (coins hauts seulement) |
| `--card-shadow` | `0 4px 18px rgba(17,24,39,.05)` | Ombre de carte, très basse — le produit se tient par ses bordures, pas par ses ombres |

La grille est de **4**. Les espacements du simulateur s'y rangent : 4, 6, 8, 9,
10, 12, 14, 16, 22, 26. Les deux valeurs de pile à connaître par cœur, parce
qu'elles reviennent sur presque chaque écran : `stack` = colonne à **14** de
gouttière, `stack-sm` = colonne à **9**.

Marges de la zone défilante : **16** à gauche et à droite, `status-bar + 52 + 16`
en haut, `nav + 26` en bas. Les pastilles de filtre débordent volontairement de
ces 16 px (`margin-inline:-16px; padding-inline:16px`) pour que le défilement
horizontal aille bord à bord — c'est un détail visuel, mais le reproduire évite
la sensation de liste tronquée.

## 2.3 Typographie

Deux familles, et une règle simple qui vient du CSS : **`Instrument Sans` pour
tout ce qui est titre** (`h1..h4`), `Inter` pour tout le reste. `IBM Plex Mono`
n'apparaît que sur trois éléments : la référence d'une vérification, l'identifiant
d'un certificat et le code de parrainage — c'est-à-dire partout où un humain
devra recopier des caractères sans se tromper.

| Rôle | Classe du simulateur | Taille / interligne | Graisse |
|---|---|---|---|
| Titre d'écran | `.h-lg` | 22 / 1.15 | 700 |
| Titre de section forte | `.h-md` | 17 / 1.25 | 700 |
| Titre de section | `.h-sm` | 15 / 1.3 | 700 |
| Corps | (par défaut) | 15 / 1.5 | 400 |
| Corps moyen | `.t-md` | 14.5 | 400 |
| Corps petit | `.t-sm` | 13 | 400 |
| Légende | `.t-xs` | 11.5 | 400 |
| Sur-titre | `.eyebrow` | 10, interlettrage `.16em`, capitales | 700, couleur primaire |
| Valeur de KPI | `.kpi .v` | 24 / 1 | 800, Instrument Sans |
| Libellé de KPI | `.kpi .l` | 11 | 400, ardoise |
| Texte long | `.prose` | 14.5 / 1.65, couleur `#374151` | 400 |

Les dimensions sont en pixels CSS dans le simulateur et se transposent en
**points logiques** Flutter à l'identique — le châssis du simulateur est un
iPhone 14 (390 × 844) puis un Pixel 8 (412 × 880), c'est-à-dire déjà des points
logiques.

**Ne jamais mettre à l'échelle par une police système hors contrôle.** La taille
du texte doit suivre le réglage d'accessibilité du téléphone, mais les cartes
doivent rester lisibles à 200 % : les hauteurs fixes du simulateur (KPI, tuiles
de type, boutons) deviennent des hauteurs **minimales**.

## 2.4 Cibles tactiles

`≥ 44` partout, sans exception. Le simulateur le tient déjà et les valeurs
exactes valent d'être reprises, parce qu'elles ont été choisies pour tenir dans
390 px de large :

| Élément | Hauteur | Note |
|---|---|---|
| Bouton principal (`.btn`) | 46 min | 18 de marge horizontale |
| Petit bouton (`.btn-sm`) | 38 | Uniquement dans une carte, jamais comme action principale |
| Bouton-icône (`.iconbtn`) | 44 × 44 | Rond, dans la barre supérieure |
| Ligne de menu (`.mrow`) | 52 min | 13/15 de marge |
| Pastille de filtre (`.chip`) | 38 | Zone tactile élargie par la gouttière de 7 |
| Entrée de la barre basse | 46 min | Hauteur totale de la barre : 76 |
| Bouton central « Vérifier » | 56 × 56 | Remonte de 26 au-dessus de la barre, cerclé de 4 de blanc |
| Case de code OTP | 46 × 56 | |
| Interrupteur | 50 × 29 | Pastille de 23, course de 21 |

## 2.5 Chrome de l'application

**Barre supérieure** — hauteur `safe-area + 52`, fond blanc, filet bas. À
gauche : le bouton « retour » **ou** le bouton du tiroir, jamais les deux. À
droite, dans cet ordre imposé : **la pastille de points (unités) puis la cloche
de notifications**. Ce n'est pas une préférence — le README du simulateur le
consigne comme un alignement fait exprès sur le prototype web mis à jour, et
l'inverser recréerait l'écart qui vient d'être corrigé.

Une variante `on-forest` existe : fond vert profond, texte et icônes blancs,
pastille de points translucide. Elle n'est employée par aucun écran du simulateur
dans sa barre supérieure — c'est une capacité du composant, pas un état actuel.

**Barre basse** — cinq entrées, identiques au prototype :
Accueil · Apprendre · **[Vérifier]** · Communauté · Profil. L'entrée courante est
primaire et son icône passe en variante remplie. Le bouton central est un rond
primaire remonté au-dessus de la barre.

**Tiroir latéral** — 274 de large, fond `#1F2124`, ouvert depuis le bouton de
gauche de la barre supérieure. Il porte l'en-tête du compte (initiales, nom,
rôle, XP), quatre groupes et un bouton de déconnexion rouge en pied :

| Groupe | Entrées |
|---|---|
| (sans titre) | Tableau de bord · Vérifier un contenu · Mes vérifications · Liste des outils |
| Apprentissage | Catalogue de cours · Live Classes · Certificats & badges |
| Communauté | Forum · Hub opportunités · Mon parrainage |
| Mon compte | Mes unités · Mes statistiques · Mon profil · Réglages |

**Barre d'état** — claire sur fond clair, blanche sur fond sombre. Le simulateur
bascule explicitement (`statusDark`) sur le splash, la connexion, et dès qu'une
superposition sombre est ouverte (tiroir, feuille, voile). En Flutter, cela se
pilote par `SystemUiOverlayStyle`, et il faut le **remettre** en fermant la
superposition — un oubli laisse des icônes blanches sur fond blanc.

## 2.6 Transitions

Trois seulement, et elles suffisent :

| Nom | Effet | Durée / courbe |
|---|---|---|
| `push` | Entrée depuis la droite (+22) avec fondu | 260 ms, `cubic-bezier(.2,.7,.3,1)` |
| `pop` | Entrée depuis la gauche (−16) avec fondu | 260 ms, même courbe |
| `fade` | Fondu montant (+8 en Y) | 220 ms, `ease` |

`push` pour un empilement, `pop` pour un retour, `fade` pour un changement
d'onglet ou un rendu sur place. Les feuilles montent de 40 en 260 ms ; le tiroir
glisse de −100 % en 240 ms.

**`prefers-reduced-motion` est respecté par le simulateur et doit l'être par
l'application.** En Flutter, c'est `MediaQuery.disableAnimations`. Ce qu'il
coupe : l'avance automatique du carrousel du splash, les confettis, le
battement du point actif de la chronologie d'analyse.

## 2.7 Composants partagés

La liste ci-dessous ne couvre que les composants employés par **plusieurs**
écrans. Ceux qui n'appartiennent qu'à un écran sont décrits avec lui en
section 3. Tous vivent dans `presentation/shared/` et **aucun parcours ne les
possède** — voir la règle de propriété en section 8.

### Structure

| Composant | Origine CSS | Équivalent Flutter | Points d'attention |
|---|---|---|---|
| `VfAppBar` | `.appbar` | `PreferredSizeWidget` maison | Trois paramètres : titre, mode gauche (retour \| tiroir), actions. La pastille de points passe **avant** les actions. |
| `VfBottomNav` | `.bottomnav` | `Row` dans un `Container`, pas `BottomNavigationBar` | Le bouton central déborde vers le haut : `Stack` + `clipBehavior: none`. |
| `VfDrawer` | `.drawer` | `Drawer` custom | Purge visuelle à la déconnexion ; le bouton de déconnexion est rouge plein, en pied. |
| `VfCard` | `.card` | `Container` + `DecoratedBox` | Blanc, filet 1, rayon 16, marge 16 (13 en variante serrée). |
| `VfBottomSheet` | `.sheet` | `showModalBottomSheet` | Poignée de 40 × 4, hauteur max 82 %, défilement interne. Sert aux filtres, au partage, au choix de média, au paiement, aux confirmations. |
| `VfSectionHeader` | `sectionHead()` | `Row` | Titre `.h-sm` + lien texte primaire à droite, optionnel. |

### Saisie

| Composant | Origine | Équivalent Flutter | Points d'attention |
|---|---|---|---|
| `VfTextField` | `.field` | `TextFormField` + `InputDecoration` | **Étiquette flottante** : posée dans le champ, elle remonte et rétrécit (0.76) au focus ou dès qu'il y a du texte. Bordure primaire + halo 3 px à 12 % au focus. |
| `VfTextArea` | `.field textarea` | idem, `maxLines` | Hauteur minimale 120, non redimensionnable, compteur de caractères aligné à droite. |
| `VfOtpField` | `.otp-box` | `Row` de six champs | Un chiffre par case, 46 × 56 ; passage automatique au suivant, retour arrière vers le précédent, collage d'un code complet réparti sur les six. `autofillHints: oneTimeCode`. |
| `VfSwitchTile` | `.switch` + `.mrow` | `Row` + `Switch` maison | Rôle d'accessibilité `switch`, état annoncé. |
| `VfConsentTile` | `.consent` | `Row` + case maison | Case carrée de 22, rayon 6. **Jamais pré-cochée.** Rôle `checkbox`. |
| `VfSearchField` | `.field input[type=search]` | `TextField` | Débounce **350 ms**, valeur identique au web. |

### Actions

| Composant | Origine | Équivalent Flutter |
|---|---|---|
| `VfPrimaryButton` | `.btn-primary` | `FilledButton` thémé — fond primaire, texte blanc |
| `VfDangerButton` | `.btn-red` | idem, fond rouge de marque, ombre portée rouge |
| `VfInkButton` | `.btn-ink` | idem, fond encre |
| `VfGhostButton` | `.btn-ghost` | `OutlinedButton` — fond blanc, filet gris, texte encre |
| `VfOutlinedButton` | `.btn-outline` | `OutlinedButton` — fond transparent, filet et texte primaires |
| `VfIconButton` | `.iconbtn` | `IconButton` 44 × 44, pastille rouge optionnelle |

Tous passent à 82 % d'opacité au contact et à 45 % désactivés. Un bouton
désactivé ne réagit pas au toucher — mais **il reste lisible** : le simulateur
ne le masque jamais.

### Affichage

| Composant | Origine | Équivalent Flutter | Points d'attention |
|---|---|---|---|
| `VfVerdictBadge` | `.vbadge-*` | `Container` + `Row` | Quatre états seulement : VRAI, FAUX, TROMPEUR, EN COURS. Fond à 12 % de la couleur, texte à 100 %, icône dédiée (`verified`, `gpp_bad`, `warning`, `pending`). **Un `switch` sur cet ensemble doit couvrir tous les cas** : c'est l'équivalent mobile du piège du `match` sans défaut (arbitrage n° 38). |
| `VfTag` | `.tag` | `Container` | Trois variantes : neutre, `xp` (violet), `prim` (vert clair). |
| `VfKpiCard` | `.kpi` | `Container` | Valeur 24/800, libellé 11. Toujours en grille de deux colonnes, gouttière 10. |
| `VfProgressBar` | `.progress` | `LinearProgressIndicator` maison | Hauteur 8 (6 en variante fine), piste `--line`, rayon plein. Animation 450 ms sur la même courbe que les transitions. |
| `VfAvatar` | `.avatar` | `CircleAvatar` | Trois tailles : 34, 42, 72. Initiales. Variante `prim` sur fond vert clair. |
| `VfMenuList` / `VfNavTile` | `.mlist` / `.mrow` | `Column` de `InkWell` | Filet entre les lignes, aucun après la dernière ; variante `danger` en rouge. |
| `VfEmptyState` | `.empty` | `Column` centrée | Icône 44 couleur `--line`, titre, phrase. **Deux états vides distincts** — « aucun résultat » et « rien encore fait » — parce qu'ils n'appellent pas la même action (règle du chantier §7, arbitrage n° 31). |
| `VfSkeleton` | `.skeleton` | `ShaderMask` animé | Dégradé 200 % qui balaie en 1,4 s. C'est le placeholder par défaut de tout chargement, y compris des couvertures distantes. |
| `VfTimeline` | `.vtimeline` | `Column` + `CustomPaint` | Trait vertical, pastilles 26. Trois états : fait (plein, coche), actif (cerclé, battement), à venir (numéroté). |
| `VfActivityTimeline` | `.tline` | `Column` | Variante légère, points de 12, employée pour « comment ça marche » du parrainage. |
| `VfChips` | `.chips` / `.chip` | `SingleChildScrollView` horizontal | Défilement bord à bord, sans barre. L'état pressé est un fond primaire plein. |
| `VfProseBody` | `.prose` | `flutter_html` en liste blanche | 14.5 / 1.65. Contenu venu de Quill : **purifié côté serveur avant persistance**, et rendu ici sur une liste blanche de balises — jamais en HTML brut. |
| `VfCover` | `.cover` | `Container` à dégradé | Quatre dégradés nommés a/b/c/d, hauteur 112 (86 en variante brève). Ils **remplacent des images** dans le simulateur ; en production ce sont des couvertures distantes via `cached_network_image`, avec le dégradé en repli. |
| `VfVoteBar` | `.votebar` | `Row` de `Expanded` | Trois segments vrai/doute/faux, hauteur 8 (10 sur la fiche d'un sujet). |
| `VfToast` | `.toast` | `SnackBar` thémé | Fond encre, ou rouge en variante erreur ; icône, texte, rayon 12. Ancré **au-dessus de la barre basse** (`nav + 14`), trois au maximum, trois secondes. Rôle `status` pour que le lecteur d'écran l'annonce. |
| `VfConfetti` | `.confetti` | `CustomPaint` animé maison | 46 pièces, cinq couleurs, chute de 900 en 1,5 à 2,7 s. **Coupé par `disableAnimations`, et jamais sur un échec** — c'est une règle explicite du pack UX et du simulateur. |

### Pastille de points

`VfPointsPill` (`.pointspill`) mérite son paragraphe : c'est le composant le
plus vu de l'application. Hauteur 38, pastille blanche cerclée, éclair primaire
rempli, nombre en chiffres tabulaires. Elle est **cliquable** et mène à
« Mes unités ». Son libellé d'accessibilité annonce le solde en toutes lettres,
pas seulement le chiffre.

Elle apparaît sur : tableau de bord, vérifier, mes vérifications, catalogue,
hub, profil. Elle n'apparaît **pas** sur les écrans de lecture (rapport, leçon,
article, certificat) — montrer un solde pendant une lecture n'apporte rien et
ajoute une cible tactile parasite.

## 2.8 Iconographie

Material Symbols Rounded, avec l'axe `FILL` piloté : contour par défaut, rempli
pour l'onglet actif et pour l'éclair des unités. Les noms d'icônes employés par
chaque écran sont donnés en section 3 ; les reprendre est ce qui garantit que la
barre basse mobile et la barre latérale web montrent le même pictogramme pour la
même chose.

## 2.9 Accessibilité — règles transverses

Ce qui suit vaut pour **tous** les écrans, et n'est donc pas répété écran par
écran ; la section 3 ne mentionne que ce qui s'y ajoute.

- Toute icône décorative est masquée au lecteur d'écran ; toute icône porteuse
  de sens a un libellé.
- Tout bouton-icône a un libellé qui dit **ce qui va se passer**, pas ce qu'il
  représente.
- Le contraste du texte sur fond respecte au moins 4,5:1. Attention à deux
  endroits du simulateur : le texte à 65 % de blanc sur fond forêt (splash,
  cartes sombres) et l'ardoise sur canevas — les deux passent, mais toute
  variation les fait tomber.
- L'ordre de focus suit l'ordre visuel. Une feuille inférieure capte le focus et
  le rend à son ouvrant en se fermant.
- Les états ne reposent jamais sur la seule couleur : un verdict porte une icône
  **et** un mot, une réponse de quiz juste ou fausse porte un symbole.
- Le texte respecte le facteur d'échelle du système ; aucune hauteur fixe ne doit
  couper une ligne à 200 %.
