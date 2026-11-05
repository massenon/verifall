# PRD — Pages légales & conformité · Vérif'All

**Version** : 1.0 · 29 octobre 2026  
**Scope** : cinq routes publiques Laravel

---

## Contexte

Vérif'All publie des verdicts de fact-checking pour le public béninois (web + app Flutter). Cinq pages légales structurent le contrat de confiance avec les utilisateurs. Trois existent déjà mais requièrent des corrections ciblées ; deux nécessitent une modification de fond ; une est à créer.

**État actuel des vues :**

| Route | Vue | État |
|---|---|---|
| `/politique-de-confidentialite` | `confidentialite.blade.php` | Conforme — ajouter lien vers `/supprimer-mon-compte` |
| `/conditions-utilisation` | `conditions-generales.blade.php` | À vérifier / compléter |
| `/mentions-legales` | `mentions-legales.blade.php` | Conforme — section accessibilité manquante |
| `/cookies` | `cookies.blade.php` | Correction fond : supprimer "cookie de consentement" (inexistant) + note sans-bandeau |
| `/supprimer-mon-compte` | *(à créer)* | Page inexistante — P0 |

---

## 1. `/politique-de-confidentialite`

### Problème
La politique mentionne le droit à l'effacement et renvoie vers `contact@verifall.com`, mais ne pointe pas vers la page dédiée `/supprimer-mon-compte` qui existe ou sera créée. Un utilisateur qui veut supprimer son compte via la politique de confidentialité est dans une impasse.

### Ce qui change
- Section 8 (Vos droits), item « Effacement » : ajouter le lien `<a href="{{ route('public.account-deletion') }}">Supprimer mon compte</a>` à côté de l'adresse courriel.
- Section 6 (Durées de conservation), item « Compte supprimé » : aligner le délai de 30 jours sur `SuppressionCompteService::JOURS_RETRACTATION` (valeur de référence, ne pas dupliquer en dur).

### Non-goals
- Ne pas réécrire la politique — elle est conforme à la RGPD béninoise telle que déployée.
- Ne pas ajouter de section mobile spécifique tant que le bundle Flutter ne transmet pas de données supplémentaires (voir `05-POLITIQUE-DE-CONFIDENTIALITE.md`).

### Acceptance criteria
- [ ] Le lien vers `/supprimer-mon-compte` est présent dans la section 8 (Effacement).
- [ ] Le délai « 30 jours » est rendu via la constante, pas codé en dur dans la vue.
- [ ] `php artisan test --filter=CouvertureTraduction` passe (clé de traduction ajoutée dans `fr` ET `en`).

---

## 2. `/conditions-utilisation`

### Problème
Les conditions générales d'utilisation (`conditions-generales.blade.php`, 91 lignes) reprennent le prototype. Deux points sont à vérifier avant toute publication définitive : (a) elles ne citent pas de verdicts inexistants (le prototype disait « DOUTEUX ») ; (b) elles ne décrivent pas de fonctionnalités non livrées (ex. fonctionnalité de paiement si absente).

### Ce qui change
- Vérifier chaque référence à un verdict : seuls VRAI, FAUX, TROMPEUR, NON VÉRIFIABLE sont valides.
- Ajouter un paragraphe « Compte et suppression » renvoyant vers `/supprimer-mon-compte` (droit de résiliation par l'utilisateur).
- Mettre à jour la date de dernière révision automatiquement (`now()->translatedFormat('j F Y')`).

### Non-goals
- Ne pas réécrire les CGU entièrement — seules les inexactitudes factuelles et le lien manquant sont dans le scope.
- La section économie / abonnement payant reste en dehors : aucune facturation active n'est déployée.

### Acceptance criteria
- [ ] Aucune référence à « DOUTEUX » ou toute autre valeur de verdict absente de l'enum `Verdict`.
- [ ] Un paragraphe « Résiliation » avec lien vers `/supprimer-mon-compte` est présent.
- [ ] La date de mise à jour s'affiche automatiquement.

---

## 3. `/mentions-legales` — section accessibilité

### Problème
Les mentions légales sont conformes (10 sections, verdicts corrects, signalement illicite, médiation). Il manque la déclaration d'accessibilité numérique, attendue de toute plateforme d'intérêt public au Bénin et exigée lors de la soumission Play Store (IARC évalue l'accessibilité).

### Ce qui change — section 11 (nouvelle)

```
11. Accessibilité numérique

Vérif'All s'engage à rendre son site web et son application mobiles accessibles,
conformément aux recommandations WCAG 2.1 niveau AA.

État de conformité actuel : partiellement conforme.
Des travaux d'amélioration sont en cours, notamment sur les alternatives textuelles
des images, les contrastes de couleur dans certains modules et la navigation
au clavier dans les formulaires interactifs.

Pour signaler un problème d'accessibilité ou demander une version accessible
d'un contenu : [courriel de contact].
Nous accusons réception sous 5 jours ouvrés et traitons votre demande dans les
30 jours.

Voie de recours : si vous n'obtenez pas de réponse dans ce délai, vous pouvez
contacter l'ARCEP Bénin ou le Défenseur des Droits de votre pays de résidence.
```

### Non-goals
- Ne pas déclarer une conformité WCAG totale que l'audit n'a pas confirmée.
- Ne pas lier à un audit PDF externe qui n'existe pas encore.

### User stories
- *En tant que personne utilisant un lecteur d'écran*, je veux trouver le contact accessibilité dans les mentions légales afin de signaler un problème sans chercher dans plusieurs pages.
- *En tant qu'admin Play Store (IARC)*, je veux que la plateforme déclare son niveau d'accessibilité afin que le questionnaire de publication soit complet.

### Acceptance criteria
- [ ] Section 11 « Accessibilité numérique » présente dans `mentions-legales.blade.php`.
- [ ] Le courriel passe par `$legal->contact()` (pas codé en dur).
- [ ] Les clés de traduction sont présentes en `fr` ET `en`.
- [ ] L'ancre `#accessibilite` permet un lien direct depuis d'autres pages.

---

## 4. `/cookies` — note sans bandeau

### Problème
La vue `cookies.blade.php` **section 2** liste quatre cookies parmi lesquels :  
`"réponse au bandeau de consentement — retient votre choix"`.  
Ce cookie n'existe pas : aucun bandeau de consentement n'est déposé (seuls des cookies strictement nécessaires sont utilisés). Décrire un traceur inexistant est aussi fautif qu'en taire un réel. Deuxième problème : nulle part la page n'explique *pourquoi* il n'y a pas de bandeau de consentement, alors que les utilisateurs habitués aux autres sites s'en étonnent.

### Ce qui change

**Supprimer** de la liste le cookie « Réponse au bandeau de consentement ».

**Ajouter** un encadré explicatif en tête de page (avant la liste des traceurs) :

```
Pourquoi ce site n'affiche-t-il pas de bandeau de cookies ?

Vérif'All ne dépose que des cookies strictement nécessaires au fonctionnement
du service (session, sécurité CSRF, langue). Ces traceurs sont exemptés de
l'obligation de recueil du consentement, conformément aux lignes directrices
de la CNIL et des autorités équivalentes. Il n'y a donc rien à accepter ou
refuser : aucun cookie publicitaire, aucun outil de mesure d'audience tiers
n'est actif.
```

**Préciser** la section 3 (Mesure d'audience) : si un outil de mesure est un jour activé depuis les réglages, un bandeau sera ajouté *avant* tout dépôt.

### Non-goals
- Ne pas ajouter un bandeau factice « juste pour rassurer » — ce serait mentir dans l'autre sens.
- Ne pas supprimer la section 3 : elle documente la trajectoire prévue et sa condition d'activation.

### Acceptance criteria
- [ ] Aucune mention de « bandeau de consentement » parmi les cookies listés.
- [ ] L'encadré explicatif « Pourquoi pas de bandeau » est visible en tête de section.
- [ ] La section 3 conditionne explicitement l'ajout d'un bandeau à l'activation d'un outil de mesure.
- [ ] Les trois cookies conservés (session, CSRF, langue) sont listés avec leur durée.

---

## 5. `/supprimer-mon-compte` *(nouvelle page — P0)*

### Problème
L'application Flutter et l'espace citoyen web permettent de supprimer son compte via les Réglages. Mais un utilisateur qui n'a plus accès à son compte (mot de passe oublié, appareil perdu) n'a aucune option documentée. Par ailleurs, le droit à l'effacement (RGPD / loi béninoise 2017-20) impose que l'exercice de ce droit soit accessible sans obstacle.

### Goals
1. Tout utilisateur peut supprimer son compte sans contacter le support, depuis l'app ou le web.
2. Un utilisateur sans accès à son compte peut déclencher la suppression par courriel avec vérification d'identité (lien valable 24 h).
3. La page clarifie ce qui est effacé vs conservé — zéro surprise après suppression.
4. Le délai de rétractation de 30 jours est documenté et actionnable.

### Non-goals
- Ne pas proposer une suppression immédiate et irréversible des données : le délai de 30 jours est une protection pour l'utilisateur, pas une contrainte technique.
- Ne pas créer un formulaire support général : cette page est dédiée à la suppression de compte uniquement.
- Ne pas créer un backoffice admin dédié : `Staff\Utilisateurs` gère déjà la suppression admin.

### User stories

**Utilisateur connecté (app ou web)**
- *En tant que citoyen connecté*, je veux supprimer mon compte depuis les Réglages en tapant SUPPRIMER + un code OTP reçu par courriel, afin de contrôler mes données sans appeler le support.
- *En tant que citoyen en période de rétractation (< 30 j)*, je veux annuler la suppression depuis un lien reçu par courriel, afin de récupérer l'accès à mon compte si j'ai changé d'avis.

**Utilisateur sans accès**
- *En tant qu'utilisateur ayant perdu l'accès à son compte*, je veux soumettre un formulaire avec mon adresse e-mail afin de recevoir un lien de suppression valable 24 h.
- *En tant qu'utilisateur ayant reçu le lien de suppression*, je veux confirmer en un clic afin de déclencher la fermeture sans devoir me reconnecter.

**Visiteur non identifié (lecture)**
- *En tant que prospect évaluant la plateforme*, je veux lire clairement ce qui est effacé vs conservé, afin de prendre une décision éclairée avant de m'inscrire.

### Structure de la page

```
/supprimer-mon-compte
├── Intro : droit à l'effacement garanti, RGPD / loi 2017-20
├── Section 1 — Depuis l'application mobile
│   Réglages → Mon compte → Supprimer mon compte
│   → Saisir le mot de passe
│   → Taper SUPPRIMER (ou DELETE en anglais)
│   → Code OTP envoyé par courriel → valider
│   → Compte fermé immédiatement, données effacées dans 30 jours
├── Section 2 — Depuis le site web
│   Espace citoyen → Réglages → Sécurité → Supprimer le compte
│   → Même flux (mot de passe + OTP)
├── Section 3 — Ce qui est effacé / conservé
│   Effacé : nom, prénom, e-mail, photo, biographie, historique de connexion
│   Conservé (anonymisé) : vérifications publiées, contributions au Forum,
│   commentaires (auteur remplacé par « Utilisateur supprimé »)
│   Délai : fermeture immédiate → anonymisation au bout de 30 jours
├── Section 4 — Annuler avant les 30 jours
│   Un lien d'annulation est envoyé par courriel à la clôture
│   Valable pendant 30 jours − délai calculé par SuppressionCompteService
├── Section 5 — Vous n'avez plus accès à votre compte ?
│   Formulaire : champ e-mail + bouton « Envoyer le lien de suppression »
│   → Un lien signé, valable 24 h, est envoyé à cette adresse
│   → Le lien confirme l'identité (e-mail enregistré), puis déclenche le flux
│   → Si l'adresse n'est pas reconnue : message neutre (ne révèle pas l'existence du compte)
└── Footer légal : lien politique de confidentialité + mentions légales
```

### Requirements

#### P0 — Must-Have

| # | Exigence | Acceptance criteria |
|---|---|---|
| R1 | Page publique `/supprimer-mon-compte` créée et routée | Route nommée `public.account-deletion`, accessible sans connexion |
| R2 | Procédure in-app documentée (iOS + Android) | Instructions en 4 étapes, captures / illustrations optionnelles v1 |
| R3 | Procédure web documentée | Même structure que l'app |
| R4 | Tableau effacé / conservé | Ligne par ligne, sans ambiguïté. Délai = `SuppressionCompteService::JOURS_RETRACTATION` |
| R5 | Formulaire e-mail pour utilisateur sans accès | Champ e-mail validé, bouton, message de confirmation neutre après envoi |
| R6 | Envoi d'un lien signé valable 24 h | `SignedUrl::temporarySignedRoute('account.deletion.confirm', 86400, ['email' => $email])` |
| R7 | Confirmation du lien → fermeture du compte | La route de confirmation déclenche `SuppressionCompteService` (si e-mail connu et compte actif) |
| R8 | Message neutre si e-mail inconnu | La réponse est identique à un e-mail connu — ne révèle pas l'existence d'un compte |
| R9 | Toutes les chaînes localisées (fr + en) | `php artisan test --filter=CouvertureTraduction` passe |
| R10 | Lien depuis politique de confidentialité (§ Effacement) | `route('public.account-deletion')` présent dans `confidentialite.blade.php` |

#### P1 — Nice-to-Have

| # | Exigence | Rationale |
|---|---|---|
| P1-1 | Captures d'écran annotées des étapes in-app | Réduit les tickets support ; pas bloquant si l'app n'est pas encore soumise |
| P1-2 | Compte à rebours du délai de rétractation | Visible dans l'espace citoyen (Réglages), pas sur la page publique |
| P1-3 | Log admin de la demande par formulaire | Traçabilité RGPD ; peut passer par `AuditLogService::record()` déjà en place |

#### P2 — Future

| # | Exigence | Rationale |
|---|---|---|
| P2-1 | Export RGPD avant suppression (lien « Télécharger mes données ») | `PreparerExportDonnees::dispatch()` existe déjà ; ajouter le lien sur cette page |
| P2-2 | Validation d'identité renforcée (2FA TOTP) | Quand 2FA sera activé pour les citoyens |

### Architecture technique

```
routes/web.php
  GET  /supprimer-mon-compte          → Public\SuppressionCompteController@show
  POST /supprimer-mon-compte/demande  → Public\SuppressionCompteController@envoyerLien
  GET  /supprimer-mon-compte/confirmer → Public\SuppressionCompteController@confirmer  (signed)

app/Http/Controllers/Public/SuppressionCompteController.php
  show()       → renvoie la vue (lecture seule, pas d'auth requise)
  envoyerLien()→ valide l'e-mail, cherche le compte, envoie le lien signé QUOI QU'IL ARRIVE
               → rate-limit : 3 envois / heure / IP (middleware throttle)
  confirmer()  → vérifie la signature + expiration, appelle SuppressionCompteService

app/Core/Marketing/Emails/
  LienSuppressionCompteMail  (nouveau, ou réutiliser le catalogue existant)

resources/views/public/supprimer-mon-compte.blade.php
  Hérite de x-layouts.public comme les autres pages légales
```

**Rate-limiting** : le formulaire est protégé par `throttle:3,60` (3 tentatives / heure / IP) pour éviter l'énumération d'adresses.

**Sécurité** : le lien signé contient l'e-mail encodé. À la confirmation, si l'e-mail ne correspond à aucun compte actif → réponse 200 avec message neutre (pas de 404 / 422 qui révèle l'existence).

### Open questions

| # | Question | Responsable | Bloquant |
|---|---|---|---|
| OQ1 | Le lien de suppression doit-il fermer le compte immédiatement ou lancer un délai de 30 j supplémentaire ? (même flux que l'in-app ou délai allongé pour la voie e-mail ?) | Porteur du projet | Oui |
| OQ2 | Faut-il notifier le compte Flutter de la suppression déclenchée par lien web (push notification) ? | Ingénierie | Non |
| OQ3 | Les vérifications anonymisées doivent-elles garder la date de publication ou la masquer aussi ? | Légal / Éditorial | Non |
| OQ4 | Le formulaire doit-il être accessible sans JavaScript (progressive enhancement) ? | Design / Ingénierie | Non |

### Timeline

| Phase | Livrable | Prerequis |
|---|---|---|
| Lot A (corrections) | Cookies, Mentions légales (section accessibilité), liens dans Politique + CGU | Aucun — modifications localisées |
| Lot B (nouvelle page) | `/supprimer-mon-compte` — vue + formulaire + lien signé | Réponse à OQ1 avant de coder `confirmer()` |
| Lot C (lien Play Store) | Lien `/supprimer-mon-compte` dans la fiche Play Store (Data Safety) | Page en production |

> Le Play Store exige, pour toute app qui permet la création de compte, qu'un moyen de suppression du compte soit déclaré dans la fiche **et** accessible depuis l'app. La Data Safety form pointe vers une URL — il faut que `https://verifall.com/supprimer-mon-compte` réponde en production avant la première soumission.

---

## Métriques de succès

| Métrique | Cible | Mesure |
|---|---|---|
| 0 ticket support « comment supprimer mon compte » | Dans les 30 jours post-lancement | Décompte manuel si aucun outil de support instrumenté |
| Taux de complétion du formulaire de suppression par lien | > 80 % des demandes par lien → confirmation reçue dans 24 h | Logs Laravel |
| Temps de traitement d'une demande de suppression par lien | < 2 min (envoi) + 24 h (fenêtre) | Mesure log → mail envoyé |
| Fiche Data Safety Play Store — champ URL suppression rempli | 100 % avant soumission | Checklist manuelle |
| `php artisan test --filter=CouvertureTraduction` | Passe après chaque lot | CI |
