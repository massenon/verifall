# Le système de courriels — état au 9 septembre 2026

Écrit en exécutant la directive « ULTRA PREMIUM — HTML Email & Newsletter
Vérif'All ». Cette page dit ce qui est en place, ce qui a été ajouté ce jour-là,
et ce que la directive demande encore. Les numéros entre parenthèses renvoient
à ses sections.

---

## Ce qui existait déjà, et n'a pas été refait

La directive décrit un système à bâtir. Une bonne partie était là :

| Ce que la directive demande | Ce qui existait |
|---|---|
| Structure en tables HTML (§23), CSS conservateur (§24), inlining (§25) | `resources/views/vendor/mail/html/` + `themes/verifall.css`, inliné par Laravel |
| Identité visuelle (§04) | Le thème portait déjà le vert `#09B850` et le rouge `#D6293E` |
| Système de gabarits (§42) | `CatalogueDesCourriels` — **24 envois déclarés, 24 câblés** |
| Composants réutilisables (§43) | `button`, `panel`, `table`, `header`, `footer`, `subcopy` |
| Versionnement (§59) | `email_templates` en base, éditable depuis le back-office, `MailLog` pour le journal |
| Version texte brut (§37) | **Déjà produite** par Laravel pour tout courriel Markdown — vérifié, pas supposé |

Le §37 méritait cette vérification : il était donné pour manquant. Le test
`SocleDesCourrielsTest` expédie un vrai courriel par le transport `array` et
lit son message MIME — `text/plain` et `text/html` y sont tous les deux.

---

## Ce qui a été ajouté

### Les jetons (§44)

Un bloc de jetons en fin de `themes/verifall.css` : preheader, eyebrow, badge,
cartes, progression, action secondaire. « Un nouveau template n'invente pas ses
propres couleurs. »

La palette de la directive y entre en entier — Forest `#063B2A`, Charcoal
`#1F2124`, XP `#7C3AED` manquaient.

**Une exception, et elle est délibérée : les couleurs de VERDICT ne sont pas
dans le thème.** Elles vivent dans `VerdictFactCheck::couleurBarre()`, d'où la
carte de démenti et les pages publiques les tirent déjà. Les recopier créerait
une seconde source : le jour où un verdict change de teinte, l'une suivrait et
l'autre non, et le courriel dirait autre chose que la page. Un test interdit la
recopie.

### Le preheader (§38)

Slot facultatif sur `<x-mail::message>`, posé en tête de corps par
`layout.blade.php`. Câblé sur le courriel d'OTP, qui sert de démonstration :
l'objet annonce un code, le preheader dit combien de temps il vaut.

**Le code n'y figure pas**, et un test l'interdit : l'aperçu s'affiche sur un
écran verrouillé, en notification, sans que personne ait eu à déverrouiller le
téléphone.

### Le mode sombre (§40)

`layout.blade.php` déclarait `color-scheme: light` — un choix défendable, mais
qui empêchait le client d'appliquer la moindre règle `prefers-color-scheme`. Il
déclare maintenant `light dark`, et pose des surfaces sombres **choisies**.

**Chaque déclaration du bloc porte `!important`, et ce n'est pas de la
superstition** : Laravel inline le thème dans des attributs `style`, qui
l'emportent sur toute règle de feuille. Sans `!important`, le bloc entier
serait mort — silencieusement, puisque aucun test ne regarde un rendu de client
de messagerie. Un test vérifie donc, déclaration par déclaration, qu'aucune n'a
été oubliée.

### Huit composants (§43)

Dans `resources/views/vendor/mail/html/` :

| Composant | Ce qu'il rend |
|---|---|
| `verdict` | Le badge de verdict, **mot toujours écrit en clair** (§12) |
| `carte-preuve` | Une information encadrée |
| `carte-source` | Une source citée — nom, domaine, lien |
| `carte-alerte` | Un avertissement |
| `progression` | Une barre, avec son pourcentage **en chiffres** |
| `carte-cours` | Un cours et sa reprise |
| `carte-certificat` | Un certificat obtenu |
| `cta-secondaire` | Une action en retrait du bouton principal (§14) |

Deux détails de fabrication qui ont décidé de leur forme :

- **Les barres de progression sont deux cellules de tableau** dont la largeur
  est un pourcentage, jamais un `div` et une `width` — qu'Outlook ignore.
- **`.vf-cta-secondaire` est posé sur le `<td>`, pas sur le `<a>`.** Outlook
  ignore le rembourrage d'un lien et respecte celui d'une cellule : le mettre
  sur le lien donnerait un bouton sans marge intérieure, donc hors de la zone
  tactile de 44-48 px (§13).

---

## Ce que les tests garantissent

`tests/Feature/Marketing/SocleDesCourrielsTest.php` et
`ComposantsDeCourrielTest.php` — 12 tests.

Une note sur leur méthode, parce qu'une première version était fausse **en
passant** : elle rendait les composants par `Blade::render()`. Le résolveur de
vues mémorise la première résolution d'un nom, si bien qu'un test exécuté après
un envoi restait accroché au gabarit **du framework** — celui qui n'a ni
preheader ni mode sombre. Le test validait un fichier qui n'était pas le nôtre.

Ils passent donc tous par le chemin de production : un envoi réel par le
transport `array`, ou `Illuminate\Mail\Markdown::render()`.

Ce qu'ils couvrent, au-delà du rendu : les données de bord exigées par §51 et
§52 — `null`, chaîne vide, 0 %, 100 %, pourcentage aberrant, nom interminable —
et l'échappement du HTML reçu.

> Sur ce dernier point, une leçon de test : une assertion `not->toContain('onerror=')`
> échouait sur un courriel **correct**, parce que le texte échappé
> `&lt;img src=x onerror=alert(1)&gt;` contient cette sous-chaîne tout en étant
> parfaitement inerte. Un test de sécurité qui crie au loup sur du code sain est
> un test qu'on finit par désactiver. Il cherche maintenant la forme dangereuse
> — une balise réellement ouverte.

---

## Ce que la directive demande encore

Rien de ce qui suit n'est commencé, et le dire vaut mieux que de le laisser
découvrir.

1. **Les neuf templates maîtres (§42).** Transactionnelle, Newsletter,
   Fact-Checking, LMS, Certification, Sécurité, Communauté, Marketing,
   Administration. Aujourd'hui les 24 courriels passent tous par le même
   gabarit générique (`emails/gabarit.blade.php`), dont le corps vient de la
   base en Markdown.

2. **L'architecture du courriel de fact-checking (§29)** — verdict, résumé,
   niveau de confiance, sources, puis le rapport complet. Les composants
   existent ; l'assemblage n'est pas fait. `analyse_terminee` passe encore par
   le gabarit générique.

3. **Le preheader sur les 24 courriels (§38).** Il est câblé sur un seul. Les
   autres tirent leur contenu de `email_templates`, qui **n'a pas de colonne
   `preheader`** : les servir tous demande une migration et un champ de plus
   sur l'écran des gabarits.

4. **Les tests de compatibilité clients (§48, §63)** — Gmail, Outlook, Apple
   Mail, Yahoo, Android, iOS. Aucun test automatisé ne peut les remplacer :
   c'est un rendu à regarder, pas une assertion à écrire.

5. **La newsletter éditoriale (§32)** dans sa forme complète — édito, à la une,
   vérification de la semaine, à surveiller, formation, communauté.
   `DiffusionInfolettre` et `RecapitulatifHebdomadaire` existent, mais ne
   suivent pas encore cette architecture.

### Un point qui appartient au porteur du projet

Les huit composants ne sont employés par **aucun des 24 courriels** à ce jour :
seul le gabarit d'essai des tests les rend. C'est délibéré — les brancher
signifie réécrire des courriels qui partent aujourd'hui, et cela se décide.
Mais c'est aussi exactement la forme du défaut que ce projet a payé quatre
fois : une chaîne complète qui n'atteint aucun gabarit. Les tests de rendu
empêchent qu'ils pourrissent en silence ; ils ne les rendent pas utiles.
