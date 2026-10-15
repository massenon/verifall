# Vérif'All — Prototype complet (verifall_prototype.zip)

## Contenu

```
web/                      Front public Tailwind (22 pages) — corrections appliquées
web/verify-certificate.html   Vérification publique d'un certificat (par identifiant)
web/compte-user/          Citizen OS (23 pages : + Live Classes, Mes Évaluations) + css/citizen.css + js/vf-store.js + js/citizen.js
web/compte-mentor/        Dashboard, correction des devoirs (grading), Mes Étudiants
                          (DataTable progression + export), Mes Cours (stats + liens grading)
web/compte-admin/         Login, dashboard KPIs/charts, Utilisateurs, KYC (revue de
                          pièces, rejet motivé obligatoire), Rôles & Permissions
                          (matrice switches persistée + export JSON), Cours LMS,
                          Course Builder (sections/leçons/quiz réordonnables, autosave),
                          Catégories, Signalements, Base de connaissances (verdicts +
                          justification riche), Sources de confiance (scores visuels),
                          Badges & Certificats, Paramètres (économie des unités,
                          switches, export JSON), Newsletters (composer riche avec
                          aperçu boîte de réception, brouillon/programmation),
                          Logs sécurité (sévérités, filtres, export), Portails Hub
                          (annuaire services publics, validation https), Blog
                          (publier/dépublier, éditeur riche), FAQ (réordonnable ↑↓
                          avec aperçu accordéon live), Maintenance (sauvegardes avec
                          progression réelle + archive téléchargeable, cron avec
                          switches persistés et exécution manuelle) — tous avec
                          DataTables triables, offcanvas CRUD, import/export CSV réels
web/docs/IMAGES_A_COLLER.md   Manifest des 39 images à coller (arborescence images/ pré-créée)
build/ · build_citizen/   Générateurs Python + QA automatisée (qa.py, qa_comptes.py)
```

## Corrections front public appliquées ce lot

- `maxWidth: { page: '1140px' }` dans tailwind.config de toutes les pages.
- « Notre Équipe » retiré du menu ; la page reste accessible via le bouton
  « Découvrir l'équipe Vérif'All » de la section distinctions.
- **Bandeau cookies** (copywriting exact « Protection de vos données… »),
  boutons Personnaliser / Accepter / Annuler, panneau essentiels + audience,
  persistance `vf.cookieConsent`.
- **Section distinctions** sur l'accueil : Prix WSIS 2026 (UIT) + 2ᵉ prix
  « Objectif : Développement Durable ! » (AMU), boutons Lire la suite → blog.html
  et Découvrir l'équipe → notre-equipe.html.
- **6 partenaires** : Fondation de France, AMU, UIT, CEMAAC BENIN, Fondation evz, OFAJ
  (logos evz/ofaj à coller : `images/storage/partners/fondation-evz.png`, `ofaj.png`).
- **Actualités** : l'état vide est remplacé par un carrousel de 5 analyses
  démenties avec filigrane « FAKE NEWS ».
- **Équipe** : cartes non cliquables, plus de fiches individuelles.
- **SEO** : canonical + OG + Twitter Card par page, JSON-LD
  (Organization+WebSite, FAQPage 17 Q/R, ClaimReview, NewsArticle, BreadcrumbList).
- **Auth « Verification Trace »** : story 4 étapes CLAIM → SOURCE → EVIDENCE → VERDICT
  (Identifier / Questionner / Vérifier / Comprendre), images slider conservées.
- Zéro `href="#"` sur tout le prototype.

## Simulations réelles (compte-user, localStorage `verifall:citizen:v1`)

- **LMS A→Z** : 3 cours seedés (A gratuit 5 leçons + quiz ; B premium 10 unités,
  8 leçons + 2 quiz + devoir ; C premium 15 unités, 10 leçons + 3 quiz + devoir +
  certificat). Inscription gratuite / achat avec solde-coût-solde après, refus
  solde insuffisant et double achat, verrouillage séquentiel des leçons,
  **reading timer actif 45 s** (pause onglet caché), progression calculée
  cross-page, **quiz chronométré à scoring réel** (70 %), corrections XAI en cas
  d'échec, **confetti + 500 XP + 5 unités + certificat** à 100 %,
  téléchargement PDF (impression) et vérification publique par identifiant.
- **Vérification** : 5 types (texte/URL/image/vidéo/audio), débit d'1 unité,
  timeline animée 6 étapes avec état réel, auto-redirection vers le rapport
  Evidence Report (sections 01–07, partager/copier/contester).
- **Forum** : composer riche + mode 🚨 Fake News (source obligatoire), barres de
  crédibilité, likes optimistes, thread avec réponse certifiée Mentor.
- Notifications 3 onglets, recherche globale, KPI accordéons mobile,
  bottom nav + FAB, unités/XP dynamiques partout, anti-duplication
  (XP, achat, certificat).
- **Admin/mentor (lots 1 à 4, 23 pages)** : composants avancés exigés partout —
  rich textarea à toolbar, compteurs de caractères, floating labels (aucun
  champ simple). Import CSV réel (FileReader), export CSV/JSON réels (Blob),
  tri par colonne, filtres combinés, confirmations de suppression, Course
  Builder avec sauvegarde automatique localStorage et prévisualisation qui
  ouvre le vrai cours côté citoyen. Côté citoyen : Live Classes avec ajout au
  calendrier (.ics réellement téléchargé) et Mes Évaluations dont les scores,
  tentatives et statuts proviennent du store de progression réel.

## Limites du prototype (assumées)

- Aperçu nécessitant Internet : Tailwind CDN, Google Fonts, Material Symbols.
- Les formulaires simulent l'enregistrement (toasts + localStorage) — aucun backend.
- Admin/mentor = lots 1 à 4 (23 pages), couvrant l'ensemble des familles
  d'écrans du back-office source.

## QA

`python3 build/qa.py` et `python3 build_citizen/qa_comptes.py` : équilibrage des
balises, `node --check` sur chaque script, JSON-LD validés, liens internes
crawlés, motifs interdits (bootstrap, jquery, wire:, data-bs-) — **0 erreur**.

## Tests de bout en bout — Scénarios A, C, E, F (125 assertions, PASS ✓)

### Scénario C (56 assertions)

`node build_citizen/test_scenario_c.js` exécute la chaîne complète sur le vrai
`vf-store.js` (shim localStorage) : **56 assertions, 0 échec**.

Chaîne validée : achat premium (34 − 15 = 19 unités, transaction journalisée,
double achat refusé) → verrouillage séquentiel (C5 hors ordre refusé) →
10 leçons avec progression exacte 7 → 71 % et +20 XP chacune (re-complétion
sans effet) → quiz QC1 échoué à 25 % (0 XP, XAI présent, tentative comptée)
puis réussi à 100 % (+80 XP, re-soumission refusée) → QC2 validé au seuil
exact 75 % ≥ 70 % → QC3 réussi mais complétion bloquée à 93 % tant que le
devoir n'est pas soumis (certificat non émis) → devoir DC1 → 100 %, +500 XP,
+5 unités, certificat unique `VERIF-C-XXXX-XXXX` (rappel de complétion sans
re-bonus ni nouveau certId) → notification émise → **vérification publique**
avec la logique exacte de `verify-certificate.html` (identifiant retrouvé,
identifiant falsifié rejeté) → persistance après rechargement simulé →
garde-fou solde insuffisant (Scénario B : manque 5 unités, aucun débit partiel).

### Scénarios A + E (38 assertions) — `node build_citizen/test_scenarios_a_e.js`

**A (cours gratuit)** : inscription sans débit ni transaction, double inscription
refusée, enrollFree sur un cours premium refusé, 5 leçons = 83 % (5/6 éléments),
quiz final → 100 %, +700 XP cumulés, +5 unités, certificat VERIF-A retrouvé par
le lookup public. **E (vérification)** : débit d'1 unité journalisé, UUID
vf-xxxxxx, statut ANALYZING sans verdict, fin d'analyse → verdict déterministe
dans {VRAI, FAUX, TROMPEUR}, confiance 80–100 %, +10 XP unique (rappel sans
double attribution), notification liée au rapport, UUID inconnu → null, solde
nul → refus sans débit. **Invariants** : somme des transactions = variation du
solde, transactions datées ISO, notifications plafonnées à 20, persistance
après rechargement.

### Scénario F (31 assertions, jsdom) — `node build_citizen/test_scenario_f.js`

Forum réel monté dans jsdom (vf-store + citizen.js + DOMContentLoaded) :
pagination 3/page respectée, titre < 8 et message < 15 caractères refusés,
publication → post en tête avec composer réinitialisé, mode 🚨 Fake News avec
source http(s) **obligatoire et validée** (deux refus testés), catégorie forcée
« Fake News », barres de crédibilité à 0%, **lien source affiché** sur le post,
mode désarmé et champ vidé après envoi, like optimiste 0→1→0 avec état visuel,
filtre par catégorie exact, « Charger plus » 3→6.

Le test F a d'ailleurs révélé et fait corriger deux vrais défauts : le lien
source d'un signalement n'était ni stocké ni affiché, et le mode Fake News
restait armé après publication.

## Révision UI compte citoyen (retours captures)

- Sidebar : fond **#1F2124** ; la carte « Unités restantes » est retirée de la
  navigation (le wallet reste sur le dashboard et la page Mes Unités).
- Bouton **Déconnexion** en rouge charte **#D6293E** pleine largeur.
- Topbar revu : recherche large arrondie (pill) avec focus vert, bouton
  + Vérifier avec ombre portée, cloche 44px avec pastille rouge détourée,
  pill unités avec **éclair rempli** (`bolt`, FILL 1 — équivalent Material de
  `lightning-charge-fill`), avatar AD sombre cerclé + chevron.
- Mes Vérifications : 4 KPIs calculés en tête (**Total soumis / En cours /
  Intox détectées / Infos fiables**, accordéon sur mobile) et, sur chaque ligne
  et carte terminée, boutons **PDF** (ouvre le rapport avec impression
  automatique via `?print=1`) et **Partager** (Web Share API, repli copie
  message + lien pour WhatsApp/Facebook/X).

## Vague de corrections design & conformité charte (retours captures 2)

**Front public** : chevauchement label/placeholder corrigé sur TOUS les
formulaires (placeholder invisible au repos, révélé au focus — connexion,
inscription, mot de passe oublié, réinitialisation) ; boutons **social auth
Google + Facebook** (SVG officiels) sur connexion et inscription avec
simulation de redirection ; ligne « Installez l'app maintenant » retirée du
popup PWA ; boutons héro à 0.9rem ; hero de tutoriels-details passé en fond
clair avec textes noirs ; **section PARTENAIRES en slider** auto-défilant
(2,8 s, pause au survol, boucle, 3/2/1 logos par vue, dots invisibles) avec
les 6 nouveaux logos ; **4 guides rapides** pilotés par `?id=`
(verifier-image, reperer-arnaque, lire-rapport, audio-suspect) avec contenu
éditorial complet et CTA vers la vérification.

**Compte citoyen** : topbar recalibré (recherche flex-1 jusqu'à 3xl, groupe
droit compact sans espace mort, visible aussi sur mobile) ; sidebar conforme
à la maquette — MENU PRINCIPAL (Tableau de bord, **Vérifier un contenu**
[icône search], Mes Vérifications, **Liste des Outils**), **APPRENTISSAGE**
(**Mes Tutoriels**, **Catalogue de Cours**, **Certificats & Badges**),
COMMUNAUTÉ (Forum, Hub d'Infos Bénin) ; bottom nav mobile avec loupe
centrale ; toasts et cards sans border-left.

**Vérifier une information** : coût par type affiché en exposant rouge
(Texte 1 pt, URL 1 pt, Image 2 pts, Vidéo 5 pts, Audio 5 pts) — le store
débite désormais le **coût réel par type** (VERIF_COSTS) ; layout 8/4 avec
3 cards latérales (💡 Le saviez-vous, 🪙 Votre solde, 🧰 Vérifier soi-même).

**Mes unités** : refonte complète — Solde actuel, **Dotation mensuelle**
(10 unités chaque 1ᵉʳ du mois, date et compte à rebours calculés
dynamiquement), **Recharge Mobile Money** (packs 10/25/60 unités à
500/1 000/2 000 FCFA, numéro validé, crédit réel + confetti), historique
en DataTable : réf T-xxx, recherche, filtre Crédit/Débit, 10/25/50 par
page avec pagination, **Exporter CSV**, **Imprimer**.

**Mes Tutoriels** : accès rapides (Live Class, Quiz, Mes Exercices,
Catalogue), KPIs accordéon (**Moyenne Quiz, Unités investies, Unités
gagnées, Certificats obtenus** — tous calculés depuis le store), onglets
En cours / **Terminés avec bouton « Télécharger le certificat »**, section
**Guides rapides** (4 cartes vers les guides publics).

**Hub d'Infos Bénin** : 3 onglets — Actualités Vérifiées, Portails
Officiels, **Jobs Vérifiés** (4 offres à employeur vérifié, badge, lien
officiel, rappel anti-arnaque).

**Dashboard « Command Center »** : ajout du graphique **Mon activité sur
30 jours** (canvas vanilla rétina, barres empilées vérifications + leçons,
alimenté par le store), du widget **Ça discute sur le forum** (3 fils
cliquables) et de **Prochaine Live Class** dans la colonne latérale.

**Compte admin** : bouton **+ Vérifier** rouge charte (#D6293E) dans le
topbar, menant aux signalements.

## Vague 3 — Redesigns majeurs (retours captures 3)

**Compte citoyen** : topbar recalibré (recherche max-w-xl avec respiration après le
logo, bouton **+ Vérifier rouge charte #D6293E**) ; sidebar : **Mes Évaluations**
ajouté dans APPRENTISSAGE ; dashboard : sous-titre « Votre boussole de
l'information est active », bouton **Acheter des unités**, colonne latérale
réordonnée (Prochaine Live Class conforme maquette → Ça discute sur le forum →
Unités restantes → Vos récompenses avec lien Achievement Center) ; badges de
coût des types de vérification repositionnés en haut à droite des boutons.

**Réglages** : refonte conforme maquette — 4 cartes (🔐 Sécurité avec 2FA,
alertes, sessions actives et déconnexion de session ; 🔔 Notifications 4
interrupteurs ; 🛡️ Données personnelles avec export JSON réel du store ;
⚠ Zone de danger bordée rouge avec suppression confirmée par saisie).

**Mon profil** : refonte conforme maquette — carte identité (avatar, badge
KYC, ancienneté, stats) + formulaire à labels apparents (Prénom*/Nom*,
e-mail validé, téléphone, ville en liste, bio avec compteur).

**Leçon (lecon.html)** : layout 2 colonnes — contenu avec libellé
« MODULE X — … · LEÇON n », illustration, encadré « À retenir », **ressources
multi-format réelles** (lecteur vidéo simulé, support PDF généré, grille CSV,
notes TXT), footer « ← Leçon précédente / ✓ Leçon terminée / Terminer le
cours → » ; colonne latérale avec progression du cours et **curriculum groupé
par modules** (leçon active surlignée, verrouillage visible).

**LMS** : nouveau cours seedé **« Un siècle de fact-checking » (D), déjà
terminé** avec certificat VERIF-D-H1ST-1926 — démontre le cycle complet
téléchargement de certificat dès l'ouverture du prototype.

**Certificat** : **2 modèles brandés Vérif'All** commutables (Classique blanc
à liseré vert / Premium forest & or), logo, signature René Fadonougbo,
identifiant vérifiable → lien direct vers la page publique
verify-certificate.html.

**Statistiques** : refonte conforme maquette — bande 4 KPI avec deltas,
**courbe d'activité 30 jours** (aire dégradée + points), **donut de
répartition des verdicts** (total au centre), **barres par type de contenu**,
« Ma semaine de série » (5/7 actifs).

**Rapport** : refonte premium conforme maquette — hero verdict thématisé
(FIABLE / FAKE NEWS / TROMPEUR), indice de fiabilité, **Hate Speech Indicator
(HSI 0 %)**, note d'analyse avec synthèse JSON, **rapport éditorial en 5
sections adapté au verdict**, sources et preuves consultées (AFP, AFP
Factuel, France 24, Ministère de la Santé Bénin, Inserm), **5 moteurs IA avec
statut et score**, avertissement IA, contre-expertise, vote d'utilité,
diffusion WhatsApp, carte Académie.

**Front public** : notre-equipe.html redesigné — breadcrumb, grille par
pôles conservée, **« Le mot du fondateur »** avec citation intégrale et
portrait (images/landing/team/rene-fadonougbo.jpeg), CTA « Nous contacter ».

## Vague 4 — Corrections + module Mon Parrainage

- **evaluations.html** : entrée de sidebar désormais active sur la page ;
  **seeders visibles** — TOUS les quiz des 4 cours listés avec statuts
  (RÉUSSI / À REFAIRE / À PASSER / VERROUILLÉ, score « — » tant que non
  tenté) et TOUS les exercices avec échéances seedées (DB1 : 24 août,
  DC1 : 31 août), actions « Rendre l'exercice » / « S'inscrire au cours ».
- **dashboard.html** : doublon « Achievement Center » supprimé.
- **Hub d'Infos Bénin** : layout réparé (équilibre des conteneurs restauré),
  **barre de recherche globale** (filtre les 3 univers), **filtres**
  catégorie + verdict, **partage social par actualité** (Web Share / copie),
  et **actualités cliquables** vers la nouvelle page **hub-article.html**
  (6 articles complets : chapo, corps en 3 paragraphes, source, CTA
  laboratoire, partage WhatsApp / lien / impression).
- **verifier.html** : badge de coût en **rouge charte** positionné en haut à
  droite des boutons de type.
- **Catalogue / cours** : les 4 vignettes remplacées par des **placeholders
  SVG de démonstration brandés** (dégradés, libellé du cours, mention
  « remplacez par le visuel final ») dans images/courses/.
- **rapport.html** : carte 🛡️ Hate Speech Indicator passée en **rouge**
  (bordure, fond, badge).
- **Nouveau module « Mon Parrainage »** (entrée COMMUNAUTÉ, icône
  group_add) : page complète full copywriting — hero « Partagez Vérif'All.
  Gagnez ensemble. », carte invitation (lien verifall.com/invite/ADDERLY7X9,
  code copiable, **QR Code canvas brandé** avec **export PNG en carte
  premium** logo + code), récompense actuelle (Vous +100 pts / Filleul
  +50 pts, accent ambre discret), « Comment ça marche » en 4 étapes, KPIs
  accordéon (12 / 8 / 6 / +650), **Mes invitations** avec les 4 états du
  programme, **historique des récompenses** + progression vers le palier
  +300, « Pourquoi inviter vos proches ? », **Share Center** (WhatsApp,
  Facebook, X, LinkedIn, E-mail avec messages préremplis, copie, partage
  natif mobile), FAQ 6 questions en accordéon accessible, règles
  anti-abus / transparence.

## Vague 5 — Hub d'Infos Bénin : direction artistique « Trusted Benin Hub »

Refonte éditoriale complète, page continue (plus de panneaux séparés) :
hero compact avec recherche centrale 640px (h 50px), navigation en pills
scrollables vers les 3 univers, **À la Une en 8/12 + 2 stories 4/12**,
Actualités vérifiées en 3 colonnes avec **images 16:9 uniformes** (6 visuels
SVG de démonstration brandés, motif géométrique discret), filtres catégorie +
verdict avec **empty state et bouton Réinitialiser**, Portails officiels en
**utility cards 4 colonnes** (8 portails, iconographie unifiée), Jobs vérifiés
en opportunity cards (3 badges max : vérifiée / contrat / ville), **widget
plein-largeur « Vérifier une information »** (fond forest) qui reboucle vers
le laboratoire, et **section Trust / méthodologie** en pied de page.
Grammaire visuelle unique : trust badge « certification » (brand/8 %),
cards radius 16px flex-col avec CTA aligné par mt-auto, hover -2px desktop
uniquement, touch targets ≥ 44px, espacement inter-sections 64px.

## Vague 6 — Directives de direction artistique (6 pages du compte citoyen)

Application fidèle des quatre documents de la base de connaissances.

**Socle commun (citizen.css)** : tokens formalisés `--vf-*` (couleurs par rôle,
rayons, ombre), classes standardisées `.vf-kpi` / `.vf-kpi-label` /
`.vf-kpi-value` / `.vf-kpi-trend`, `.vf-eyebrow`, `.vf-insight`,
`.vf-streak-day`, `.vf-badge-tile`, `.vf-timeline`, progression animée
0→valeur (450 ms) et prefers-reduced-motion.

**mes-tutoriels.html — « Learning & Trust »** : hero + actions [Continuer mon
apprentissage / Catalogue], quick actions scrollables (Live class · Quiz ·
Exercices · Catalogue), KPI accordéon « Résumé de mon apprentissage », section
CONTINUER avec cours prioritaire dominant (image, barre + % + x/y leçons,
prochaine leçon, Smart Resume contextuel Commencer/Reprendre/Revoir), cours en
cours (lg:3), Quiz à reprendre en ambre doux (« Vous pouvez progresser »),
Exercices à terminer avec échéances, Guides pratiques 2×2, Tutoriels terminés
(date + score final + Voir le cours + Télécharger le certificat + cross-link
« Voir ma récompense »), empty states conformes.

**recompenses.html — Achievement Center** : Progression Overview (Niveau
nommé, barre XP x/1000 animée, « Encore n XP »), KPI accordéon, widget
Prochaine étape (badge Vérificateur confirmé + CTA Continuer un tutoriel),
streak discret, grille de badges 4/3/2 avec verrouillés visibles mais
secondaires (◌ + condition), modal détail au clic, certificats en documents de
confiance (✓ authentifié + Vérifier l'authenticité vers la page publique),
Activité récente en timeline verticale.

**outils.html — OSINT & Fact-Checking Tool Hub** : eyebrow OUTILS · OSINT ·
FACT-CHECKING, compteur éditorial « 15 outils référencés · Sélection
Vérif'All », recherche 720px h52, pills catégories (état actif #09B850),
3 outils recommandés, répertoire groupé selon la taxonomie (Recherche, Web &
domaines, Archives, Médias & métadonnées, IA, Géolocalisation, Fact-checking)
avec fiches normalisées (icône contextuelle 44px, badge catégorie 10px,
description 2 lignes, tags « Idéal pour », CTA texte « Ouvrir l'outil ↗ »),
empty state riche + Effacer la recherche, Learning Bridge vers le cours OSINT
Fondamentaux, disclaimer de confiance.

**catalogue.html — Laboratoire d'apprentissage éditorial** : hero LMS sobre
avec motif ✓ Evidence · Sources · Context · Verification, widget Ma
progression (« Que dois-je faire maintenant ? »), carte Reprendre (bord gauche
vert 4px + fond primary-soft léger), pills catégories, recherche élargie
(titre, description, niveau, catégorie), filtres desktop en selects + bouton
Filtres mobile ouvrant un bottom sheet (niveau/prix, Réinitialiser/Appliquer),
Recommandé pour vous, grille 1/2/3/4 avec un composant unique gérant les
4 états (Commencer / Continuer+barre / ✓ Terminé Revoir / ✓ Certifié Voir
certificat), parcours certifiant, empty state + réinitialisation.

**evaluations.html — Performance Snapshot** : 4 KPI signés (✓ vert, ◉ vert si
≥ 70 %, ↻ neutre, □ bleu) en vf-kpi homogènes, accordéon « Aperçu de vos
évaluations », badge VERROUILLÉ avec mention « Disponible après inscription au
cours ».

**statistiques.html — Editorial Trust Dashboard** : bande unique remplacée par
4 KPI cards homogènes (avec tendances), eyebrows de widgets (Activité /
Verdicts / Contenus / Habitudes), donut avec centre « n vérifications »,
valeurs affichées au-dessus des barres, streak tracker jour par jour
(L M M J V S D, aujourd'hui cerclé, « Actif : 5 jours »), widget Insight final
✦ VOTRE PROGRESSION (DATA → INTERPRÉTATION → ACTION) avec CTA Faire une
vérification.

## Vague 7 — Nouvelles directives DA (statistiques, evaluations, mes-verifications, forum)

**statistiques.html — Vérif'All Insight.** KPI restructurés (valeur dominante →
label → context marker : « ↗ +3 vs mois dernier », « Partages potentiellement
évités », « Record personnel : 12 jours »), grille 2×2 mobile. **Suppression de
toutes les données artificielles** : la courbe d'activité est désormais
construite sur les dates réelles des vérifications du store (points marqués,
filtre 7/30 jours, empty state avec CTA), le bar chart n'ajoute plus aucun
compte fictif et passe en **barres horizontales sur mobile** avec valeurs,
empty state dédié. Donut avec **pourcentages** dans la légende et état vide.
Streak Week Card : « 🔥 5 jours consécutifs — encore 2 jours pour battre votre
record ». Insight final « ✦ Votre insight » avec prochaine étape et CTA
« Continuer à vérifier ». Fonction seeded() supprimée (code mort interdit).

**evaluations.html — Trusted Learning Control Center.** Correction du bug §20 :
le score moyen est calculé uniquement sur les scores réellement obtenus (plus
jamais de NaN/null %). Score moyen neutre (encre, non coloré). KPI Tentatives
en bleu info, Exercices en violet, et le compteur « Exercices soumis » ne
compte plus que les soumissions réelles. **Échéances sémantiques** (>7 j gris,
3–7 j orange, ≤48 h orange fort, dépassée rouge + mention). Sidebar sans état
actif artificiel (pas de fausse localisation). Tabs actives en vert Vérif'All
plein. Description du header conforme.

**mes-verifications.html — Personal Verification Lab.** Sous-titre
« laboratoire personnel », CTA **+ Vérifier un contenu en rouge brandred**
(séparation action/confiance), KPI « Votre activité de vérification » en
vf-kpi 2×2 mobile, **chips de verdict scrollables** sur mobile (actif forest),
**compteur de résultats** sous les filtres, **micro-barre de confiance** dans
la table, et **deux empty states distincts** : « Aucun résultat » (avec
Réinitialiser les filtres) vs « Votre laboratoire est encore vide » (avec CTA
rouge Vérifier un contenu).

**forum.html — Community Verification Desk.** Micro-étiquette éditoriale
« COMMUNITY FACT-CHECKING HUB » au-dessus du titre, layout desktop
**70/30 (1fr / 320px)**, catégories actives en **forest**, composer en
**progressive disclosure mobile** (bouton + Nouvelle discussion qui déplie le
formulaire), panneau Fake News léger avec la mention « Votre signalement sera
transmis au processus de fact-checking Vérif'All », widgets sidebar refondus :
tendances avec **rangs éditoriaux 01/02/03**, contributeurs avec **avatars
initiales + XP**, dernières vérifications en **mini-timeline** liée aux
articles du Hub avec la précision « verdicts officiels du laboratoire —
distincts des avis de la communauté ».

## Vague 8 — Complétion des directives (priorités moyennes + polish)

**statistiques.html** : mention « Dernière mise à jour : aujourd'hui à 14:32 »
sous le header, filtre temporel complet (7 j / 30 j / 3 mois / cette année)
avec titre et axes adaptés, **tooltip tactile sur la courbe** (toucher un point
affiche « 13 août — 3 vérifications », aria-live), **count-up animé des KPI**
(400 ms, désactivé si prefers-reduced-motion).

**evaluations.html** : cards mobiles enrichies (« 2 tentatives · minimum
70 % » + séparateur avant l'action), lignes de table à hauteur 64 px, actions
primaires (« Passer le quiz » / « Réessayer ») en **pill verte** conformément
à la hiérarchie des actions.

**mes-verifications.html** : « Dernière vérification : il y a n jours »
calculée depuis les dates réelles du store (jamais de donnée inventée).

**forum.html** : label éditorial **« ✓ VÉRIF'ALL CONFIRME — FAUX »** sur les
discussions tranchées par le laboratoire (matérialisation de la séparation
communauté / vérité §48), et flux de publication complété : après insertion en
haut du feed, **focus accessible + défilement doux** vers la nouvelle
discussion.

## Vague 9 — Assets locaux, refontes citoyennes, rebuild staff « Trusted Learning OS »

**Assets auto-hébergés (plus aucun CDN).** Polices Inter (400–700),
Instrument Sans (500–700) et Material Symbols Rounded téléchargées en woff2
dans web/css/fonts/files/ avec web/css/fonts/fonts.css (font-display swap).
Tailwind compilé en **build statique** web/css/tailwind.css (~44 Ko minifié)
via la CLI v3 avec une config unifiée (palette citoyenne + publique + nouvelle
palette staff) scannant les 70 pages ; le script cdn.tailwindcss.com et les
liens Google Fonts sont supprimés de tous les shells.

**Compte citoyen.**
- Sidebar : le label « MENU PRINCIPAL » est retiré sur toutes les pages.
- dashboard.html : statut système sous le hero, CTA « Vérifier un contenu » en
  rouge, **widget « À traiter maintenant »** alimenté par le store (quiz à
  reprendre, exercices à rendre, vérifications en cours) avec état « tout est
  à jour ».
- cours.html : **la tab Avis devient interactive** — notation 1–5 étoiles
  (radiogroup accessible, cibles 44 px), commentaire (min. 10 caractères),
  publication persistée dans le store (visible après rechargement), rendu
  immédiat « Votre avis » surligné + toast de confirmation.
- statistiques.html : **fix du donut qui débordait** — conteneur responsive
  (max-w 190, aspect-square, overflow-hidden) et redessin adaptatif à la
  largeur réelle avec resize.
- exercices.html : prochaine échéance affichée dès le header.
- verifier.html : grille de types 2/3/5 colonnes selon l'écran.

**Staff « Trusted Learning OS » (admin + mentor).**
- Nouveau shell : sidebar **vert profond #052E16** avec groupes repliables
  (details/summary accessibles, caret animé, groupe actif ouvert), liens
  actifs émeraude #059669 ; topbar avec **recherche globale**, bouton
  **« + Créer »** (Nouveau cours / article / signalement / utilisateur),
  cloche de notifications à pastille, profil.
- Sidebar admin restructurée d'après **l'ancienne sidebar de la base de
  connaissances** (admin_dashboard.html) : Tableau de Bord, FACT-CHECKING
  (Signalements, Fact checks, Sources vérifiées), ACADÉMIE LMS (Catégories,
  Cours, Builder, Badges & Certificats), HUB & CONTENU (Portails, Blog, FAQ,
  Newsletters), UTILISATEURS (Citoyens & Mentors, KYC, Rôles), CONFIG SYSTÈME
  (Réglages, Sécurité & Logs, Maintenance) — chaque entrée mappée sur une page
  existante (zéro lien mort).
- Dashboard admin : hero dégradé deep-green « Bonjour, René » avec statut
  services, raccourcis signalements/nouveau cours, KPI vf-kpi avec tendances,
  **widget « À traiter maintenant »** (signalements prioritaires, KYC, blog,
  sauvegarde).
- Dashboard mentor : hero « Espace pédagogique » (devoirs en attente +
  prochaine live class) et CTA émeraude « Corriger maintenant ».

Note honnête : les documents « ancienne sidebar » et « directive master
admin/mentor » joints au message sont arrivés vides ; la structure de la
sidebar a été reconstituée depuis admin_dashboard.html de la base de
connaissances, et le style depuis le brief texte du message. Les pages CRUD
staff existantes héritent du nouveau shell ; leur refonte interne écran par
écran (Quill, DataTables avancées) reste à mener sur directive détaillée.

## Vague 10 — Rebuild complet staff « TRUSTED LEARNING OS » (admin + mentor)

Directives appliquées : DIRECTIVE MASTER admin/mentor (59 sections), directive
Constructeur de Cours + Inventaire des Cours (58 sections), ancienne sidebar.

**Design system dédié `web/css/staff.css`** : tokens #065F46/#052E16/#059669/
#D1FAE5, familles de composants uniques (st-card, st-kpi, badges ● sémantiques,
st-btn, st-input avec focus ring, st-table 64 px + hover, chips, menus ⋮,
offcanvas, bottom sheets, skeleton shimmer, tabs, arbre de curriculum, toasts,
pagination, footer) — un composant = une apparence, tous modules.

**Sidebar admin = ancienne structure intégrale** (14 groupes, 42 entrées) :
Fact-Checking (5), Répertoire des outils, Académie LMS (7), Examens (3),
Enrôlement (2), Hub Bénin (3), Communauté (2), Blog (2), Ressources & Com (4),
Utilisateurs (4 dont Mentors), Config Système (5 dont Templates Emails,
Campagnes, Certificat Template), Sécurité (4 types de logs), Maintenance
(Sauvegardes + Cron). **Sidebar mentor §7 distincte** (7 groupes orientés
production pédagogique). 100 % des entrées pointent vers des pages réelles.

**Factory CRUD uniforme** (`gen_staff_crud.py` + `gen_staff_pages.py`) : 43
pages admin + 9 mentor générées sur le même pattern — breadcrumb, header avec
Importer/Exporter/CTA unique, 4 KPI avec accordéon mobile, recherche débouncée
350 ms, filtres desktop + bottom sheet mobile, chips supprimables, compteur de
résultats, DataTable desktop → cards mobile, sélection + bulk bar
(publier/archiver/exporter/supprimer séparé), actions ⋮ desktop / bottom sheet
mobile, offcanvas de création avec **Quill 2 auto-hébergé** (npm, zéro CDN),
modal de suppression contextualisée (suppression réelle du DOM), **export CSV
réel** (Blob téléchargé), import simulé, empty state orienté action,
pagination, footer, Échap partout, WCAG (aria-expanded, roles, labels,
focus, 44 px).

**Inventaire des cours** (cours.html) : colonne Cours riche
(vignette + titre + catégorie·niveau·type), 5 filtres métier, complétion en
micro-barres, KPI catalogue.

**Course Studio** (builder.html) : 3 panneaux (Curriculum Navigator avec
**drag & drop à ligne d'insertion** et fallback toast, Workspace **Quill**,
Properties + **Course Health 82 %** + aperçu), tabs Informations/Curriculum/
Quiz/Exercices/Paramètres, **tabs mobiles Structure/Contenu/Options** +
sticky action bar, **indicateur d'autosave** (✓/↻/⚠), **Publish Check** avec
avertissement leçon vide, modal nouvelle section, **Ctrl/Cmd+S**.

**Dashboards** : admin = hero deep-green + 4 KPI + « À traiter » + grille 8/4
(courbes inscriptions/complétions canvas avec périodes 7/30/90/365 + activité
temps réel) + Top Courses cliquables + Santé Fact-Checking (répartition des
verdicts) ; mentor = workspace avec **Correction Inbox** (urgent/nouveau/en
cours, avatars, « Ouvrir »). Login mentor créé (miroir sécurisé).

Validation : 106 pages, 0 lien critique cassé (les 41 chemins d'images
publiques sont conservés volontairement pour collage), sidebars 100 % mappées,
57 scripts staff passés à node --check (0 erreur), QA citoyenne + publique
0 erreur, tests C 56/56 · A/E 38/38 · F 31/31 PASS.

## Vague 10b — Interface de Correction Mentor (§21)

**grading.html mentor entièrement reconstruite** selon le layout de la
directive : **file de correction latérale** (inbox sticky avec avatars, badges
Urgent/Nouveau/En cours, navigation entre copies qui recharge énoncé, copie et
note), **énoncé et copie apprenant côte à côte** (consignes + critères barémés
/8 /6 /6 d'un côté ; copie scrollable, métadonnées de soumission et
**téléchargement sécurisé** simulé de l'autre), zone **Évaluation** avec note
/20 à validation inline (0–20, message d'erreur accessible), sous-notes par
critère, **feedback Quill** pré-rédigé constructif, **indicateur de brouillon**
(✓ enregistré à HH:MM / ↻ / ⚠), bouton « Enregistrer le brouillon » et
**« Valider la correction »** avec modal de confirmation nominative
(note + apprenant) — la validation marque la copie « Corrigé » dans la file et
décrémente le compteur. Mobile : colonnes empilées, actions pleine largeur.

---

## ÉTAT FINAL DU PROTOTYPE (vague 10 — clôture)

### Périmètre livré
| Espace | Pages | Contenu |
|---|---|---|
| Front public | 24 | Marketing complet, menu 6 entrées, blog, facts, légales, auth, verify-certificate |
| Compte citoyen | 27 | Dashboard Command Center, Verification Lab, LMS complet (catalogue→cours→leçons→quiz→exercices→certificats), Hub Bénin, forum, statistiques, unités, parrainage, profil, réglages |
| Compte admin | 46 | Trusted Learning OS : 14 groupes de sidebar / 45 entrées, factory CRUD uniforme, Inventaire des cours, Course Studio, dashboard 8/4 |
| Compte mentor | 13 | Workspace pédagogique §7, Correction Inbox, interface de correction §21, analytics |
| **Total** | **106** | |

### Garanties de qualité (vérifiées à la clôture)
- **0** `href="#"` · **0** lien html/css/js cassé · **0** entrée de sidebar non mappée (Admin 45, Mentor 12, Citoyen 10)
- **86 scripts inline** validés `node --check` · **0** fonction onclick orpheline · **0** déséquilibre de balises
- Tests jsdom : **C 56/56 · A/E 38/38 · F 31/31 PASS**
- **0 CDN** : Inter, Instrument Sans, Material Symbols, Tailwind (build statique 47 Ko) et Quill 2 auto-hébergés
- 40 chemins d'images publiques conservés volontairement (à coller dans `web/images/`)

### Reproduire le build
```bash
cd build && python3 build.py                 # front public
cd ../build_citizen && python3 - <<'PY'
import gen_citizen_a, gen_citizen_b, gen_citizen_c, gen_citizen_d
import gen_staff, gen_staff2, gen_staff3, gen_staff4
import gen_staff_pages, gen_staff_builder, gen_staff_grading
for m in [gen_citizen_a, gen_citizen_b, gen_citizen_c, gen_citizen_d]:
    m.write_all('../web/compte-user')
for m in [gen_staff, gen_staff2, gen_staff3, gen_staff4,
          gen_staff_pages, gen_staff_builder, gen_staff_grading]:
    m.write_all('..')
PY
python3 qa_comptes.py && node test_scenario_c.js && node test_scenarios_a_e.js && node test_scenario_f.js
```

### Prochaines étapes suggérées (hors périmètre de cette vague)
- Course Details Admin à onglets (§40) accessible depuis l'action « Voir » de l'inventaire
- Quiz Builder (§41) et Question Editor XAI (§42) en pages dédiées
- Branchement Laravel : remplacer les seeders par les endpoints (la factory CRUD isole déjà données et rendu)

## Correctif — bottom-nav visible en desktop (cascade CSS)

**Cause racine** : en passant du CDN Tailwind au build statique, l'ordre de
cascade avait changé. Le CDN injectait ses utilitaires *après* citizen.css →
`lg:hidden` gagnait ; le build statique était chargé *avant* citizen.css →
`.bottom-nav { display:grid }` reprenait le dessus à spécificité égale, et la
bottom-nav restait visible en desktop.

**Correctif double** :
1. **Ordre restauré partout** : `tailwind.css` est désormais chargé **en
   dernier** dans les 3 shells (citoyen : citizen.css → tailwind ; staff :
   citizen.css → staff.css → quill.snow.css → tailwind ; public :
   verifall.css → tailwind) — la sémantique « utilitaires gagnants » du CDN,
   sous laquelle les 106 pages ont été écrites, est rétablie et vérifiée
   automatiquement sur chaque page.
2. **Blindage des composants mobiles** : `.bottom-nav` et `.fab` portent
   maintenant leur propre `@media (min-width:1024px){ display:none }` dans
   citizen.css — ils resteront masqués en desktop quel que soit un futur
   changement d'ordre de chargement.

## Vague 11 — Sidebar staff aérée + Topbar Command Center complète

**Sidebar admin/mentor aérée** : groupes espacés (marge inter-groupes, en-têtes
cliquables 42 px avec hover émeraude, padding vertical du panneau augmenté),
sous-menus indentés avec **filet vertical mint** et gap entre items, liens
40–44 px, labels 10,5 px avec letter-spacing .16em — la densité condensée est
remplacée par une respiration régulière.

**Topbar staff complétée** (les deux rôles) :
- **Plein écran** : bouton fullscreen fonctionnel (requestFullscreen/exit) ;
- **Notifications réelles** : badge numérique (3 non lues), dropdown avec
  liste seedée par rôle (admin : signalements/KYC/certificat/sauvegarde ;
  mentor : devoir soumis/live class/avis 5★), état non-lu surligné avec point
  vert, **clic = marquer lu**, bouton **« Tout marquer lu »**, badge qui se
  met à jour et disparaît à zéro, aria-label dynamique, lien « Voir toute
  l'activité » ;
- **Journal d'audit** : bouton dédié dans la topbar admin → securite.html ;
- **Menu compte enrichi** : admin = Mon profil + Paramètres + séparateur +
  Déconnexion ; mentor = Mon espace + Ma performance + Déconnexion ;
- Quick Create « + Créer » et recherche globale conservés.

**Compte citoyen (état des lieux du lot LMS demandé)** : les couvertures
placeholder des cours (dont C — Détection des Deepfakes) existaient déjà en
SVG (`images/courses/placeholder-a..d.svg`) et s'affichent sur catalogue,
page cours (hero) et mes-tutoriels (Smart Resume). Restent planifiés pour la
prochaine vague, car ils touchent le flux LMS couvert par 56 tests : nouveaux
cours e-learning avec simulation de lecture vidéo, cours premium
supplémentaires, toast de gains (+XP/+unités) à la complétion cours+quiz, et
notification mensuelle « 1 cours gratuit offert ».

## Vague 12 — Dashboard admin conforme à la maquette + recommandations produit

**Dashboard admin reconstruit d'après la maquette fournie** : header « Bon
retour, René 👋 » avec **Exporter Rapport** (CSV réellement téléchargé) et
**Actualiser** (état disabled + redraw + toast), 4 KPI iconés (Citoyens /
Abonnés newsletter / Signalements 474 avec 22 en attente / Cours publiés),
**courbe d'aire indigo des signalements sur 7 jours** (canvas responsive,
grille, labels Jeu→Mer), liste **Nouveaux Citoyens** (avatars, e-mails, badges
temporels), **Performance Articles** (titre/vues/clics), **Journal d'Audit**
en timeline pointée, **Navigateurs** avec parts et micro-barres, panneau
sombre **Live Fact-Checking** (1 / 140 / 41 / 22), **Santé du Système** temps
réel (4 moteurs d'analyse opérationnels, Reverb « Déconnecté », 216 jobs en
attente, CPU 46 %, disque 89 % avec 300,6 Go libres / 2 697 Go), **Top
Thématiques** (Général 427…), et table **Dernières Activités de
Fact-Checking** (sujets datés, badges verdicts, assignation, « Pris en
charge », pagination 1…95 sur 474 entrées).

**Recommandations produit appliquées (P0/P1 sûres)** :
- **F3+ coût annoncé** (verifier.html) : le libellé devient « Cette
  vérification consommera X — **il vous restera N unités** », recalculé à
  chaque changement de type ;
- **F5 notification mensuelle** (dashboard citoyen) : carte « 🎁 Cours offert
  du mois — Un siècle de fact-checking » avec inscription 1 clic vers
  cours.html?c=D ;
- Audit des recos restantes : le **bouton Copier du parrainage** et
  l'**annonce du coût** existaient déjà (vérifiés) ; F1 (cérémonie de gains),
  F4 (cours vidéo) et F2 (rapport partageable image) restent planifiés en
  vague dédiée car ils modifient le flux LMS couvert par les 56 tests.

Corrections en route : 3 bugs JS attrapés par la QA (apostrophes échappées,
BOM/saut de ligne littéraux dans l'export CSV, variable `cost`→`c`) — tous
résolus, 0 erreur au node --check final.
