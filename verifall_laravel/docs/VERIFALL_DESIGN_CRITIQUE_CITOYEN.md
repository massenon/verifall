# VÉRIF'ALL — CRITIQUE DESIGN · COMPTE CITOYEN
> Périmètre : les 27 pages du compte citoyen (prototype v2). Stade : raffinement pré-implémentation. Grille : première impression → usabilité → hiérarchie → cohérence → accessibilité → mobile. Chaque point est noté 🟢 force / 🟡 à surveiller / 🔴 à corriger.

## 1. Première impression (2 secondes)
🟢 Le dashboard répond immédiatement à « que faire maintenant ? » : hero de salutation avec série 🔥, statut système, CTA rouge « Vérifier un contenu » qui contraste franchement avec l'univers vert — l'action n°1 du produit est la plus visible, c'est juste.
🟢 L'identité est reconnaissable d'écran en écran : eyebrows uppercase, Instrument Sans en display, vert #09B850 réservé au positif/action.
🟡 Le hero + KPI + « À traiter » + activité empilés font ~2,5 écrans mobiles avant le premier contenu « chaud ». Envisager de fusionner statut système et salutation sur une ligne en <768 px.

## 2. Usabilité par flux clé
**Vérification (verifier → en-cours → rapport)** 🟢 Le stepper animé rend l'attente lisible et la redirection automatique évite un clic. 🟡 Le coût en unités par type (1/2/5) n'est confirmé qu'au moment du choix : afficher le solde restant *après* déduction dans le bouton de soumission (« Analyser — il restera 123 u ») éviterait toute surprise.
**LMS (catalogue → cours → leçon → quiz)** 🟢 Les 4 états de carte (nouveau/en cours/terminé/verrouillé premium) sont différenciables sans lire ; Reprendre avec border-left verte est un excellent repère de scan. 🟡 Dans la tab Avis, le formulaire précède les avis existants : inverser (préséance de la preuve sociale) ou replier le formulaire derrière « Donner mon avis » raccourcirait le scroll de moitié.
**Forum** 🟢 La séparation communauté/vérité (« ✓ VÉRIF'ALL CONFIRME — FAUX ») est l'élément UX propriétaire le plus fort du produit. 🟡 Les votes communautaires (vrai/doute/faux) et le verdict officiel cohabitent sur la même carte : un cran hiérarchique de plus (verdict au-dessus du titre plutôt qu'en dessous) éviterait toute ambiguïté sur « qui a tranché ».
**Parrainage** 🟢 QR + partage multi-canal + FAQ anti-abus = flux complet. 🔴 Le lien de parrainage est le seul élément copiable du compte sans bouton « Copier » à côté du champ sur mobile — à ajouter (le partage ne couvre pas le cas coller-dans-une-autre-app).

## 3. Hiérarchie visuelle
🟢 Discipline typographique constante (12 px plancher, valeurs KPI en 800, labels 11 px letter-spacing) ; le triptyque valeur→label→contexte des KPI se lit sans effort.
🟢 Une seule action primaire par écran, tenue partout (Vérifier en rouge, actions LMS en vert).
🟡 statistiques.html : l'insight ✦ (le meilleur élément narratif de la page) arrive sous les graphes ; le remonter sous les KPI le ferait lire par 100 % des visiteurs au lieu des scrolleurs.

## 4. Cohérence du système
🟢 Un composant = une apparence : cards, chips verdict (vtrue/vinvest/vfalse), pills de filtres, toasts, modales et empty states sont identiques d'un module à l'autre — la promesse de la DA est tenue.
🟡 Deux motifs de tabs coexistent : pill-tabs vertes (evaluations) et tabs soulignées (cours). Choisir : pills = filtres de données, soulignées = sections de contenu, et documenter la règle.
🟡 Les émoji-icônes (🔥📚✦) côtoient Material Symbols ; c'est chaleureux mais à cadrer (émoji = célébration/gamification uniquement) pour ne pas diluer l'iconographie.

## 5. Accessibilité (WCAG 2.2 AA)
🟢 Très bon socle : cibles 44 px, focus visible, radiogroup d'étoiles, aria-expanded des accordéons, prefers-reduced-motion respecté jusqu'au count-up, focus déplacé sur la discussion publiée.
🟡 Les verdicts reposent parfois sur couleur + texte mais les micro-barres de confiance (mes-verifications) sont couleur seule : ajouter le % en texte adjacent systématiquement.
🟡 Le donut de statistiques a un aria-label mais pas d'équivalent textuel des valeurs : ajouter un tableau sr-only (3 lignes) pour lecteurs d'écran.

## 6. Mobile
🟢 Bottom-nav 5 entrées + FAB + KPI accordéon + bottom sheets de filtres : le pouce fait tout ; la correction cascade (bottom-nav desktop) est verrouillée par media query.
🟡 Sur cours.html, la barre de tabs (7 items) scrolle horizontalement sans affordance : ajouter un dégradé de fondu en bord droit pour signaler le débordement.

## 7. Synthèse priorisée
| # | Action | Effort | Impact |
|---|---|---|---|
| 1 | 🔴 Bouton « Copier » sur le lien de parrainage | XS | Élevé (viralité) |
| 2 | 🟡 Verdict officiel au-dessus du titre dans le forum | S | Élevé (confiance) |
| 3 | 🟡 % de confiance en texte à côté des micro-barres | XS | Élevé (a11y) |
| 4 | 🟡 Insight ✦ remonté sous les KPI (statistiques) | XS | Moyen |
| 5 | 🟡 Formulaire d'avis replié derrière un bouton | S | Moyen |
| 6 | 🟡 Règle tabs pills vs soulignées documentée | XS | Moyen (dette design) |
| 7 | 🟡 Fondu de débordement sur les tabs de cours | XS | Faible-moyen |

**Verdict global** : un compte citoyen au niveau des meilleurs produits SaaS grand public — système cohérent, accessibilité réelle, et deux signatures UX fortes (Command Center des priorités, label VÉRIF'ALL CONFIRME). Les corrections restantes sont des réglages fins, aucune ne remet en cause l'architecture.
