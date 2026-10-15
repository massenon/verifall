# Conformité au prototype — relevé mesuré

Le prototype est le gabarit maître. Cette page dit, écran par écran, **à quelle
distance** on en est — pas à l'estime, mesuré.

## Comment c'est mesuré

`scratchpad/comparer.py` réduit chaque page à trois listes et les confronte :

1. **Squelette** — la suite des balises porteuses de mise en page avec leurs
   classes, dans l'ordre du document. Une classe Tailwind absente est un
   espacement perdu, et ça se voit ici.
2. **Repères** — titres, libellés de boutons, entrées de menu, en-têtes de
   colonne, intitulés de champs : le copywriting et la navigation.
3. **Ressources** — feuilles, scripts, images. Une référence qui ne résout pas
   est un fond qui ne s'affiche pas.

Deux scores en découlent :

- **Motifs** — les mêmes sortes de conteneurs, avec les mêmes classes, sont-elles
  présentes ? C'est le score de référence. Plusieurs pages du prototype
  construisent leurs listes en JavaScript : leur HTML statique contient un
  conteneur vide là où le rendu contient dix cartes, et comparer les suites
  brutes pénaliserait la page qui affiche vraiment quelque chose.
- **Suite** — la comparaison ordonnée, utile pour repérer un bloc déplacé.

Les écrans authentifiés sont rendus par `tests/Feature/Audit/CaptureDesEcransTest.php`
(`AUDIT_CAPTURE=<dossier> php artisan test --filter=CaptureDesEcrans`), les
écrans publics par requête HTTP sur le serveur de développement.

**Ce qui ne compte pas comme écart** : le nombre de cartes d'une liste alimentée
par la base, la classe d'état d'un lien actif, le conteneur `<main>` du gabarit,
l'hôte des notifications, et les écarts DÉCIDÉS et consignés dans
`ARBITRAGES.md` — déconnexion en POST, portails officiels retirés du Hub,
« Hub d'Infos Bénin » devenu « Hub opportunités ».

## Relevé

| Écran | Motifs | Suite | Nœuds proto/rendu |
|---|---:|---:|---:|
| `compte-admin/dashboard.html` | 46% | 58% | 634/274 |
| `compte-admin/alertes.html` | 58% | 63% | 543/380 |
| `compte-admin/securite.html` | 58% | 60% | 585/405 |
| `compte-user/forum.html` | 74% | 64% | 223/364 |
| `compte-user/parrainage.html` | 75% | 76% | 345/298 |
| `compte-user/recompenses.html` | 76% | 71% | 211/248 |
| `inscription.html` | 76% | 82% | 176/127 |
| `tutoriels-details.html` | 78% | 70% | 315/238 |
| `compte-user/catalogue.html` | 80% | 72% | 211/280 |
| `compte-user/unites.html` | 84% | 86% | 192/161 |
| `blog.html` | 84% | 65% | 218/122 |
| `compte-user/evaluations.html` | 85% | 64% | 173/339 |
| `compte-user/reglages.html` | 87% | 87% | 215/192 |
| `compte-user/quiz.html` | 88% | 93% | 119/130 |
| `compte-user/live-classes.html` | 88% | 84% | 150/201 |
| `compte-user/hub-infos.html` | 88% | 54% | 388/363 |
| `verifier-une-information.html` | 88% | 84% | 131/114 |
| `compte-user/mes-verifications.html` | 90% | 91% | 190/175 |
| `compte-user/verifier.html` | 90% | 88% | 220/201 |
| `compte-user/statistiques.html` | 91% | 87% | 208/223 |
| `compte-user/exercices.html` | 91% | 91% | 154/178 |
| `compte-user/mes-tutoriels.html` | 91% | 93% | 211/203 |
| `compte-user/profil.html` | 92% | 92% | 167/177 |
| `facts-details.html` | 93% | 86% | 133/112 |
| `compte-user/dashboard.html` | 93% | 95% | 263/259 |
| `connexion.html` | 93% | 94% | 120/117 |
| `compte-user/outils.html` | 94% | 98% | 449/450 |
| `facts.html` | 94% | 87% | 173/173 |
| `blog-details.html` | 94% | 77% | 201/133 |
| `mot-de-passe-oublie.html` | 96% | 97% | 105/109 |
| `verify-certificate.html` | 97% | 97% | 15/14 |
| `confidentialite.html` | 98% | 98% | 124/126 |
| `cookies.html` | 98% | 98% | 109/111 |
| `mentions-legales.html` | 98% | 98% | 110/112 |
| `contact.html` | 98% | 98% | 126/128 |
| `faq.html` | 98% | 99% | 193/195 |
| `notre-equipe.html` | 98% | 99% | 181/183 |
| `medias.html` | 98% | 99% | 156/158 |
| `tutoriels.html` | 98% | 89% | 189/188 |
| `comment-ca-marche.html` | 100% | 98% | 174/176 |
| `fonctionnalites.html` | 100% | 99% | 211/213 |
| `index.html` | 100% | 100% | 441/443 |
| `pourquoi-verifall.html` | 100% | 99% | 169/171 |

## Lecture

**Les écrans en attente ont disparu.** Neuf écrans affichaient encore « Le
contenu de cette page arrive à son sprint », posé au sprint 1A quand seul le
gabarit avait été monté — dont le tableau de bord citoyen, page d'arrivée après
connexion. Huit sont transposés ; il reste le **tableau de bord du
back-office**, qui appartient au sprint 6.

**Trois pages n'existaient pas.** `tutoriels-details.html` n'avait aucune route
— la fiche publique d'un cours est désormais servie sur `/tutoriels/{slug}`.
`facts-details.html` et `blog-details.html` avaient été redessinés ; ils
reprennent la structure du prototype.

**Ce qui reste sous 80 %** — et pourquoi :

| Écran | Reste à faire |
|---|---|
| `compte-admin/dashboard.html` | Écran en attente. Dix panneaux, dont plusieurs demandent une collecte d'analytique qui n'existe pas encore (navigateurs, visites). Sprint 6. |
| `compte-admin/alertes.html`, `securite.html` | Journaux livrés au sprint 2 ; les filtres avancés et l'export du prototype restent à transposer. Sprint 6. |
| `inscription.html` | Écart de MESURE : le prototype garde ses quatre étapes dans le DOM et les bascule en JavaScript ; ici une seule est rendue à la fois, ce que la vérification du code par le serveur impose. |
| `tutoriels-details.html` | L'onglet « Détails » et le bloc FAQ du prototype ne sont pas repris : leur contenu n'existe dans aucune colonne. |
| `compte-user/parrainage.html` | Le bouton de partage de l'en-tête et les variantes mobiles du prototype. |
| `compte-user/forum.html` | Écart de MESURE en partie : le prototype construit son fil en JavaScript (223 nœuds statiques contre 364 rendus). Restent l'éditeur riche du composeur et le sondage. |
| `compte-user/recompenses.html` | La modale de détail d'un badge. |

**Moyenne sur les 43 écrans mesurés : 88 % de motifs communs**, huit encore sous
80 % — dont trois qui relèvent du sprint 6 et deux qui sont des écarts de mesure
expliqués ci-dessus.

## Ordre de reprise

1. Le tableau de bord du back-office et les deux journaux — sprint 6, cadré dans
   `docs/sprints/SPRINT_6_BACK_OFFICE.md`.
2. Le reste des 46 écrans d'administration et des 13 écrans mentor.
