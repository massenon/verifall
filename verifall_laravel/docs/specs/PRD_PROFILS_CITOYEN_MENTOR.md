# PRD — Fiche complète du citoyen et du mentor

> Rédigé le 18 octobre 2026. Maquette de référence : le prototype premium,
> écrans `profil-citoyen`, `profil-mentor`, `m-profil` et `m-profil-citoyen`
> (`resources/prototype-premium/assets/js/ecrans-profils.js`).
> Statut : **à valider**. Aucune ligne de code Laravel n'est écrite.

## 1. Le problème

Quand un citoyen écrit au support (« ma vérification n'a pas abouti », « je
n'ai pas reçu mon certificat »), l'administrateur doit aujourd'hui ouvrir
quatre ou cinq écrans : Vérifications, Journal des unités, Inscriptions,
Journal d'audit. Le mentor, lui, ne voit de ses apprenants qu'une ligne de
tableau : progression et dernière activité. Personne ne voit **la personne
entière**, ni l'enchaînement de ses actions.

Ce n'est pas un écran à créer de zéro. `Staff\FicheUtilisateur` et son service
`App\Core\Identity\Services\FicheDuCompte` existent déjà : carte d'identité à
gauche, et **16 onglets DataTables** (vérifications, cours, quiz, devoirs,
examens, certificats, unités, points, paiements, forum, journal, et pour le
personnel : vérifications assignées, cours encadrés, corrections de devoirs et
d'examens, faits rédigés). Ce PRD décrit **ce qui manque** à cette fiche, et non
une seconde fiche.

**Ce qui manque :**

1. une vue d'ensemble qui résume la personne en dix secondes ;
2. les échecs de vérification et leur motif ;
3. les rapports produits et partagés ;
4. le Hub d'opportunités consulté ;
5. les groupes du forum et les interactions reçues ;
6. l'historique des connexions ;
7. les statistiques et graphiques des cours d'un mentor ;
8. une fiche que le mentor peut ouvrir pour ses propres apprenants, sans les
   données sensibles.

## 2. Objectifs

| # | Objectif | Mesure |
|---|---|---|
| O1 | Répondre à une demande de support depuis un seul écran | Temps médian de traitement d'un ticket « compte » : de ~12 min à moins de 4 min |
| O2 | Repérer un compte compromis ou abusif | Toute connexion bloquée ou venant d'un pays inhabituel est visible sur la fiche, au plus tard 1 min après l'événement |
| O3 | Donner au mentor de quoi relancer un apprenant à bon escient | Taux de reprise après relance : de 18 % à 30 % |
| O4 | Piloter les cours d'un mentor sur des chiffres | 100 % des cours publiés ont leur entonnoir, leur complétion et leurs quiz visibles sur la fiche |

## 3. Hors périmètre

- **Modifier l'identité depuis la fiche.** Le bouton renvoie au tiroir existant
  de l'écran Utilisateurs (`?modifier=<uuid>`), qui porte les garde-fous du
  lot 3. Une copie divergerait au premier correctif.
- **Le suivi de navigation hors Hub.** On ne trace pas chaque page vue :
  le gain ne justifie pas le coût RGPD.
- **La fiche publique d'un membre du forum.** C'est une autre fonctionnalité,
  avec d'autres règles de visibilité.
- **L'export PDF de la fiche.** L'export RGPD (`PreparerExportDonnees`) couvre
  déjà le droit d'accès.
- **Le temps réel.** La fiche se rafraîchit au chargement. Pas de `wire:poll`
  sur un écran consulté occasionnellement.

## 4. Récits utilisateurs

### Administrateur, modérateur

- En tant qu'**administrateur**, je veux voir sur une seule ligne le statut du
  compte, son KYC, son niveau et sa double authentification, **pour savoir en
  un coup d'œil à qui j'ai affaire**.
- En tant qu'**agent du support**, je veux lister les vérifications en échec
  avec leur motif (unités insuffisantes, quota d'un moteur, fichier illisible,
  délai dépassé, lien inaccessible), **pour répondre à « ma vérification n'a
  pas abouti » sans ouvrir les journaux techniques**.
- En tant qu'**administrateur**, je veux voir l'historique des connexions
  (date, résultat, méthode, appareil, lieu approximatif, IP), **pour repérer
  une prise de contrôle de compte**.
- En tant qu'**administrateur**, je veux un journal unique de toutes les
  actions, filtrable par domaine et exportable en CSV, **pour reconstituer ce
  qui s'est passé**.
- En tant que **modérateur**, je veux voir les groupes du forum d'un membre et
  les signalements reçus par ses messages, **pour décider d'une sanction en
  connaissant l'historique**.

### Mentor

- En tant que **mentor**, je veux ouvrir la fiche d'un de mes apprenants
  (progression, quiz, devoirs, interactions au forum), **pour adapter ma
  relance**, sans voir ses unités, ses paiements, son KYC ni ses connexions.
- En tant que **mentor**, je veux voir mes cours les plus suivis, l'entonnoir de
  mes inscrits et la réussite à mes quiz, **pour savoir quelle leçon
  retravailler**.
- En tant que **mentor**, je veux retrouver sur ma fiche les vérifications qui
  me sont assignées et leur échéance, **pour tenir l'objectif de 6 h**.

### Cas limites

- Un compte **sans aucune activité** : chaque onglet affiche un état vide qui
  dit pourquoi il est vide (« Aucune vérification envoyée »), jamais un tableau
  sans ligne.
- Un **ancien mentor rétrogradé** garde ses onglets de mentor tant qu'il a un
  historique. C'est la règle qu'applique déjà `FicheDuCompte`.
- Un **compte supprimé** (RGPD) : la fiche n'existe plus. Les lignes d'audit
  qui le citent affichent « compte supprimé ».
- Un **mentor qui ouvre la fiche d'un citoyen hors de ses cours** : refus 403,
  et non une fiche vide.

## 5. Exigences

### P0 — indispensables

| Exigence | Critères d'acceptation |
|---|---|
| **Vue d'ensemble** en premier onglet | 6 chiffres clés (vérifications et échecs, part de faux et de trompeurs, cours suivis, moyenne aux quiz, messages au forum, solde d'unités), le graphique « Activité sur 30 jours » empilé par domaine, les verdicts obtenus, les 8 dernières actions et la carte d'identité. Chaque graphique a sa vue Tableau. |
| **Vérifications** enrichies | Colonnes : contenu, type, canal, statut, **motif d'échec**, verdict, confiance, moteurs sollicités, unités débitées, date. Filtres statut et verdict. Encadré rouge listant les échecs. Un échec ne débite aucune unité, et la fiche l'affiche. |
| **Connexions et sécurité** | Historique des connexions sur 90 jours : date, résultat (réussie, échouée, bloquée et motif), méthode (mot de passe, Google, code), appareil, ville et pays, IP. Sessions ouvertes avec « Fermer toutes les sessions ». État de la double authentification et des consentements. |
| **Journal complet** | Toutes les actions de la personne, triées de la plus récente à la plus ancienne, filtrables par domaine, paginées côté serveur, exportables en CSV. |
| **Fiche apprenant côté mentor** | 3 onglets (vue d'ensemble, Académie, Communauté). Policy : le mentor ne voit que les apprenants inscrits à **ses** cours. Aucune donnée d'unités, de paiement, de KYC ni de connexion. |
| **Fiche mentor : statistiques des cours** | Inscriptions par cours et par mois, cours les plus suivis, entonnoir (inscrits, commencé, mi-parcours, terminé, certifié), complétion par cours, réussite par quiz, répartition des avis. |
| **Fiche mentor : corrections et vérifications assignées** | Devoirs à corriger, en retard et corrigés, avec le délai de correction. Vérifications assignées avec leur échéance ou leur durée de traitement. |
| **Dernière connexion** visible dans l'en-tête et dans la liste des citoyens | Date exacte et relative, appareil. |
| **Lien vers la fiche** depuis Citoyens, Mentors, Mes apprenants et le journal d'audit | Action « Voir la fiche complète » dans le menu de chaque ligne. |

### P1 — à suivre de près

| Exigence | Critères d'acceptation |
|---|---|
| **Rapports** | Liste des rapports PDF : verdict, partage public (vues), nombre de téléchargements. Le bouton PDF passe par une route signée. |
| **Hub d'opportunités consulté** | Actualités lues, portails ouverts, offres consultées, candidatures (clic sur le lien de l'offre), favoris, offres signalées comme suspectes. |
| **Communauté** | Groupes du forum (rôle, messages, réactions, ancienneté), interactions récentes, solutions acceptées, signalements reçus. |
| **Onglet dans l'adresse** | `?onglet=verifications` rouvre la fiche au même endroit. Un lien collé dans un ticket suffit. |
| **Fiche mentor : lives, examens, revenus** | Présence aux lives, vues des replays, examens créés et leur taux de réussite, ventes premium par mois, points et quota. |

### P2 — à prévoir sans les construire

- Une **chronologie** commune web et mobile : l'API v1 pourra servir le même
  journal. Le dépôt doit donc être partagé (critère 1 de la règle des dépôts).
- Le **score de risque** d'un compte : connexions bloquées, signalements reçus
  et vérifications abusives. Les données de la fiche le permettront.
- Les **notes internes** du support sur un compte.

## 6. Données : d'où vient chaque onglet

| Onglet | Source existante | À créer |
|---|---|---|
| Vérifications et échecs | `Verification` (`status`, `attempts`, `retry_reason`, `cost_units`), `EngineRun` | Rien : le motif est dans `retry_reason` ; le libellé passe par un enum |
| Rapports | `AnalysisReport` | Un compteur de téléchargements |
| Académie | `Enrollment`, `LessonCompletion`, `QuizAttempt`, `AssignmentSubmission`, `ExamAttempt`, `Certificate` | Rien |
| Hub consulté | `ContentVisit`, **mais anonyme** (`visitor_hash`) | Une table `hub_interactions` (user_id, type, cible, action, date), **soumise à consentement** — voir Q1 |
| Communauté | `Thread`, `Reply`, `ThreadVote`, `ContentReport`, `ForumCategory` | Une notion de « groupe rejoint », si elle diffère de la catégorie — voir Q3 |
| Unités, paiements, badges | `UnitLog`, `Payment`, `UserBadge` | Rien |
| Connexions | `SecurityLog` (`event`, `user_id`, `ip_hash`, `user_agent`) ; `LoginAttempt` ne garde que des empreintes | Un événement `connexion.reussie` journalisé avec l'appareil et le pays ; IP hachée, pays en clair — voir Q2 |
| Journal complet | `AuditLog`, plus l'union des sources ci-dessus | Un dépôt `DepotChronologieDuCompte` (volume non borné : dépôt obligatoire, règle 2) |
| Statistiques mentor | `Course`, `Enrollment`, `QuizAttempt`, `Review`, `LiveClassRegistration`, `Payment` | Un dépôt mis en cache 10 min (tableau de bord : dépôt obligatoire) |

Les règles du projet s'appliquent sans exception : pagination serveur,
`Cache::remember` avec invalidation par tags, Policy sur chaque onglet,
`AuditLogService::record()` pour toute action lancée depuis la fiche
(suspendre, créditer, fermer les sessions).

## 7. Mesures de succès

**Avancées (premier mois)**

- Part des tickets « compte » où l'agent ouvre la fiche : au moins 80 %.
- Part des fiches ouvertes au-delà de la vue d'ensemble : moins de 50 %. Au-delà,
  la vue d'ensemble ne remplit pas son rôle.
- Temps de chargement de la vue d'ensemble : moins de 600 ms au 95e centile.

**Différées (trimestre)**

- Temps médian de traitement d'un ticket « compte » : moins de 4 min (O1).
- Taux de reprise après une relance de mentor : 30 % (O3).
- Comptes compromis repérés par le support avant la plainte du citoyen : au
  moins 1 sur 2 (O2).

## 8. Questions ouvertes

| # | Question | Qui tranche | Bloquante ? |
|---|---|---|---|
| Q1 | Peut-on lier une consultation du Hub à un compte identifié ? Il faut un consentement explicite et une durée de conservation (90 jours ?) dans le registre RGPD. | DPO, juridique | **Oui**, pour l'onglet Hub |
| Q2 | Que garde-t-on d'une connexion ? IP hachée et pays en clair, ou IP en clair 30 jours pour les enquêtes ? | DPO, sécurité | **Oui**, pour l'onglet Connexions |
| Q3 | Un « groupe » du forum est-il une catégorie, ou une notion nouvelle (adhésion, rôle de membre actif) ? | Produit | Non, P1 |
| Q4 | Le mentor voit-il la moyenne aux quiz de ses apprenants **dans les autres cours** que les siens ? | Produit, pédagogie | Non |
| Q5 | Faut-il journaliser la **consultation** d'une fiche, comme on le fait pour une pièce KYC ? | Sécurité | Non, mais recommandé |

## 9. Calendrier et découpage

Trois lots, chacun livrable seul :

1. **Lot A (P0, côté admin)** : vue d'ensemble, vérifications et échecs,
   journal complet, connexions (après la réponse à Q2), liens depuis les
   listes. Il étend `FicheDuCompte` et ne la remplace pas.
2. **Lot B (P0, côté mentor)** : fiche apprenant avec sa Policy, puis
   statistiques des cours, corrections et vérifications assignées sur la fiche
   mentor.
3. **Lot C (P1)** : rapports, Hub (après Q1), communauté, lives, examens et
   revenus.

Dépendance : la mise à jour de la règle « référence visuelle absolue » de
`CLAUDE.md` pour l'admin et le mentor, le jour où le prototype premium est
validé.
