# Sprint 6 — Back-office

**Volume : le plus gros du projet.** 45 pages d'administration, 12 pages mentor.
À découper en lots livrables, jamais en un bloc.

Préambule obligatoire : `docs/AGENTS_REGLES_DU_CHANTIER.md`.

---

## 0. Ce qui est déjà fait

- Le **shell staff** est transposé et fidèle au pixel (sidebar, topbar,
  rubriques repliables, tiroir mobile). Le CSS de shell vit dans
  `public/assets/css/staff-shell.css` — ne pas le refaire.
- Le **guard `staff`** avec 2FA TOTP obligatoire, le journal d'audit et les
  alertes d'intrusion fonctionnent.
- La **matrice RBAC** : 107 permissions sur 34 modules, 4 rôles.
- La **base des faits vérifiés** a sa couche métier complète — `DepotFaitsVerifies`,
  `GestionDesFaitsVerifies`, permission `knowledge_base.create`, sources
  multiples. **Il ne lui manque que son écran.**

## 1. Ordre de livraison

L'ordre n'est pas arbitraire : il suit ce qui **débloque le produit**.

### Lot 1 — La saisie des faits vérifiés (priorité absolue)

`compte-admin/base-de-connaissances.html`

Le mécanisme d'économie d'appels existe et fonctionne : un fait tranché par
l'équipe court-circuite douze moteurs payants. **Mais personne ne peut alimenter
la base autrement que par la console.** C'est le seul écran du sprint dont
l'absence coûte de l'argent tous les jours.

L'écran doit porter :

- la liste filtrable (recherche, verdict, statut) et ses quatre indicateurs ;
- le **tiroir de saisie** — affirmation, verdict, sources multiples, analyse
  riche (Quill) ;
- les **mots-clés**, proposés automatiquement depuis l'affirmation et
  modifiables. C'est le champ qui décide si le fait sera retrouvé : le laisser
  vide, c'est remplir une base que personne ne consultera ;
- **publier** comme geste distinct d'enregistrer : publier engage la plateforme.

Quill est déjà en vendor (`public/assets/js/vendor/quill.js`). Le contenu se
purifie **avant persistance et à l'affichage**. Le champ vit dans un
`wire:ignore` avec un pont vers l'état Livewire.

### Lot 2 — Les modules d'administration du Hub

Les opportunités et les brèves n'ont **aucun écran de saisie**. Même problème
que les faits vérifiés : la couche métier existe, l'alimentation manque.

- `hub-actualites.html` — brèves, avec leur verdict.
- `hub-jobs.html` — opportunités : portée (Bénin / International), nature
  (CDI, CDD, stage, alternance, volontariat, bourse, financement, conférence,
  appel à candidature), date limite, **case « vérifiée »**.
- `hub-portails.html` — portails officiels. Ils sont masqués côté citoyen mais
  les données restent : l'écran d'administration reste utile.

Rappel : `is_verified` n'est pas décoratif. Une offre non contrôlée ne doit
jamais atteindre le Hub.

### Lot 3 — Modération et signalements

- `signalements.html` — la file des `content_reports`. Un signalement se
  **traite**, il ne se supprime pas : statut, agent, date, note.
- `forum-discussions.html`, `forum-categories.html`.

Les actions de modération existent déjà sur la page du sujet côté citoyen
(`Sujet::basculerVerrou`, `apposerLeVerdict`, `transmettreAuLabo`). Le
back-office les reprend en lot, il ne les réinvente pas.

### Lot 4 — Utilisateurs, rôles, sécurité

`utilisateurs.html`, `roles.html`, `kyc.html`, `securite.html`, `alertes.html`,
`logs-unites.html`, `logs-emails.html`.

`securite.html` et `alertes.html` **existent déjà** — ne pas les refaire.

Toute action sensible → `AuditLogService::record()`. Le journal d'audit reste
**non modifiable par personne**, administrateur compris.

### Lot 5 — Académie et examens

`cours.html`, `builder.html` (Course Studio), `enrolement-cours.html`,
`examens.html`, `questions.html`, `correction-examens.html`,
`enrolement-examens.html`, `certificats.html`, `certificate-template.html`,
`live-classes.html`, `categories.html`, `badges.html`.

Le Course Studio est le morceau le plus lourd. Le schéma LMS est déjà en place —
`courses`, `lessons`, `quizzes`, `questions`, `answers`, `assignments`.

### Lot 6 — Blog, médias, marketing, système

`blog.html`, `blog-categories.html`, `campagnes.html`, `newsletters.html`,
`emails-templates.html`, `temoignages.html`, `partenaires.html`,
`sources-confiance.html`, `sources-categories.html`, `outils.html`,
`unites-ia.html`, `unites-premium.html`, `parametres.html`, `cron.html`,
`maintenance.html`, `stats-globales.html`, `faq.html`, `mentors.html`.

### Lot 7 — Espace mentor (12 pages)

`compte-mentor/*.html`. Le mentor est **scopé à ses propres ressources** : le
filtrage se fait au niveau du Repository, jamais dans la vue.

Rappel d'un arbitrage déjà rendu : **le mentor ne publie pas**, il soumet. La
publication engage la plateforme.

## 2. Le patron CRUD

Les 45 pages partagent une structure. La factoriser en composants Blade
partagés — en-tête, indicateurs, barre de filtres, tableau, cartes mobiles,
tiroir de saisie, modale de suppression — **avant** d'écrire la dixième page,
pas après la quarantième.

Chaque écran de liste doit avoir :

- pagination serveur, recherche débouncée 350 ms ;
- **tableau sur grand écran, cartes sur mobile** — le prototype le fait, le
  transposer aussi ;
- deux états vides distincts : « aucun résultat » et « rien encore créé » ;
- une **suppression avec annulation** (fenêtre de 6 secondes, toast d'annulation)
  plutôt qu'une confirmation modale, conformément au design system.

## 3. Ce qui fera échouer ce sprint si on l'oublie

- **Écrire quarante pages avant d'en vérifier une seule à l'écran.** Livrer par
  lots, vérifier chaque lot.
- **Réinventer une logique métier déjà écrite** côté citoyen. Chercher d'abord
  dans `app/Core/`.
- **Oublier `AuditLogService::record()`** sur une action sensible.
- **Se fier à l'absence de bouton** au lieu d'autoriser côté serveur.
- **Inventer du contenu** pour remplir un écran de démonstration.

## 4. Définition de terminé, par lot

1. `pint` propre, `php artisan test` intégralement vert.
2. Chaque écran ouvert dans un navigateur et comparé au prototype.
3. Audit responsive sur 5 largeurs, 0 débordement de page.
4. 0 lien cassé, 0 image manquante.
5. Tests d'autorisation : chaque action sensible appelée **sans droit** rend 403.
6. Écarts consignés dans `docs/ARBITRAGES.md`.
