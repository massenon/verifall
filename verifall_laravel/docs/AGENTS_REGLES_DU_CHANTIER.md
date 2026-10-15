# Règles du chantier — socle commun à tous les agents

Ce document est le préambule obligatoire de chaque agent du projet. Il ne
répète pas `CLAUDE.md` : il ajoute **ce qui a été appris en construisant**, et
qui n'est écrit nulle part ailleurs. Un agent qui ne le lit pas refera les mêmes
erreurs — elles ont toutes déjà été commises une fois.

---

## 1. Le principe qui gouverne tout

**On transpose, on n'invente pas.** Le prototype HTML dans `resources/prototype/`
est la référence visuelle absolue. Pas de redesign, pas de repositionnement.

Mais transposer n'est pas recopier aveuglément. Trois cas obligent à s'écarter,
et chacun se **consigne dans `docs/ARBITRAGES.md`** avec son motif :

1. **Le prototype a un bug.** Exemple réel : le catalogue filtre ses
   recommandations sur un état que sa propre fonction ne renvoie jamais — le
   bloc est donc toujours vide. Transposer le bug aurait produit du code mort.
2. **Le prototype fige une donnée qui doit vivre.** Voir la règle 2 ci-dessous.
3. **Le prototype se contredit avec la documentation.** Trancher, motiver,
   consigner.

## 2. Ce qui ne doit JAMAIS être figé dans un gabarit

Le prototype écrit en dur des fact-checks, des offres d'emploi, des articles.
Sur une plateforme de fact-checking, **figer un verdict revient à publier une
vérification qui n'a jamais eu lieu**. C'est la faute la plus grave du projet.

Ne jamais coder en dur :

- un **verdict** ou un fact-check ;
- une **offre d'emploi** ou une annonce nominative ;
- un **article** présenté comme publié ;
- un **indicateur** qu'on ne mesure pas réellement.

Ces contenus lisent la base, avec un **état vide honnête** qui dit pourquoi il
n'y a rien et propose une action utile.

Contre-exemple légitime : le contenu **éditorial** — ce que le produit promet,
comment il marche, qui le fait, ses mentions légales. C'est sa propre parole,
elle se transpose telle quelle.

> Le prototype signale parfois lui-même ses placeholders. Les neuf cartes du
> blog portent `title="Exemple de carte — contenu à venir"`. Cherchez ce genre
> de marqueur avant de semer quoi que ce soit.

## 3. Les pièges de transposition déjà rencontrés

### 3.1 Le CSS de page vit EN LIGNE dans le prototype

Le prototype déclare des règles dans un bloc `<style>` **à l'intérieur de chaque
page**. L'extraction du balisage les laisse derrière, et la classe n'a alors
aucun effet — silencieusement.

- Shell staff : 59 pages partagent le même bloc → extrait dans
  `public/assets/css/staff-shell.css`.
- Pages citoyennes et publiques : 46 règles sur 13 pages → une feuille **par
  page** dans `public/assets/css/pages/`, poussée par la vue via
  `@push('styles')`.
- **Une feuille par page, jamais un fichier commun** : `catalogue.html` et
  `hub-infos.html` définissent `.hub-pill` et `.no-scrollbar` différemment.

Avant de livrer un écran : vérifier que chaque classe utilisée existe vraiment
dans le CSS chargé. `grep` dans `public/assets/css/`.

### 3.2 Le build Tailwind est FIGÉ

`tailwind.css` est un build statique du prototype. **Une classe Tailwind absente
du build n'a aucun effet.** `text-vdoubt` et `btn-primary` n'existent pas ;
`text-vfalse`, `text-vinvest`, `bg-primary` oui. Toujours vérifier.

### 3.3 Ordre de cascade imposé

`fonts → citizen/verifall → staff → quill → tailwind → staff-shell → couche
applicative`. Toute inversion casse la spécificité. Un test le verrouille.

### 3.4 Blade

- `@unless` n'accepte **ni `@else` ni `@elseif`**. Utiliser `@if(! …)`.
- Une directive collée à un mot (`vérificateur@if`) n'est pas reconnue.
- `@` suivi d'une lettre est lu comme une directive : échapper les adresses en
  `&#64;`.
- Blade échappe les apostrophes : dans un test, comparer avec `e($valeur)`.

### 3.5 Le shell public ne charge pas `citizen.css`

Les classes `vbadge` y sortent grises. Utiliser `badgePublic()`.

## 4. Règles de données

- **Jamais d'ID auto-incrémenté en URL.** `uuid` ou `slug`, route model binding.
- **`$fillable` explicite**, jamais `$guarded = []`.
- **Jamais modifier une migration déjà appliquée** — toujours une nouvelle.
- **Enums PHP natifs** pour tout statut, jamais de chaîne magique.
- Un `match` sur un enum **sans branche par défaut est un piège** : ajouter un
  cas fait planter toutes les pages qui l'affichent, à l'exécution seulement.
  Écrire un test paramétré sur `Enum::cases()` pour chaque enum affiché.
- **Une seule source par liste de valeurs.** Deux listes écrites à la main
  finissent par diverger, et c'est le compteur qui ment en premier.

### 4.1 Ce que le schéma doit tenir lui-même

Une règle qui ne vit que dans le code finit contournée par un job, un import ou
une commande de maintenance. Mettre en contrainte d'unicité tout ce qui ne doit
arriver qu'une fois : une inscription par cours, une complétion par leçon, un
vote par sujet, un avis par cours, un badge par personne.

### 4.2 Le lazy loading est INTERDIT en test

`Model::preventLazyLoading()` est actif. Tout accès paresseux fait échouer le
test — et c'est voulu : il révèle les requêtes N+1 avant la production.
Utiliser `with()`, `loadMissing()`, ou une requête explicite.

## 5. Sécurité — non négociable

- **Jamais `{!! !!}` sur donnée utilisateur.** Contenu riche : purifié via
  `mews/purifier` **avant persistance ET à l'affichage**.
- **Jamais de secret en dur.** `.env` + `config/`.
- **L'absence de bouton n'est pas une protection.** Toute action sensible
  vérifie l'autorisation **côté serveur**, à chaque appel. Un test doit appeler
  la méthode sans droit et attendre un 403.
- **Toute action admin sensible → `AuditLogService::record()`.**
- Liens sortants signalés par des utilisateurs : `rel="noopener nofollow ugc"`.
- Jamais d'adresse IP en clair : `IdentityHasher`.
- Codes publics (certificat, parrainage) : alphabet **sans O/0, I/1, S/5**, et
  `random_int`, jamais `rand()`.

## 6. Architecture

```
Models → Repositories → Services → Composants Livewire
```

- **Jamais de logique métier** dans un composant Livewire ou un contrôleur :
  elle vit dans `app/Core/<Domaine>/Services`.
- **Jamais de requête Eloquent hors Repository** pour les nouveaux domaines.
  Interface dans `app/Core/Application/Contracts`, implémentation dans
  `app/Infrastructure/Persistence`. (Dette assumée sur les sprints 1–2.)
- **Un domaine ne dépend pas d'un autre en amont.** Le fact-checking ne connaît
  pas les badges : il émet `VerificationAchevee`, la gamification écoute. Un
  listener journalise ses échecs sans jamais les propager — un badge manqué est
  ennuyeux, une analyse perdue est inacceptable.

## 7. Ce qu'un écran doit toujours faire

- **Dire ce qui se passe**, y compris quand ça se passe mal. Jamais de rapport
  creux, jamais d'écran « échec » sans explication ni remboursement.
- **Un état vide dit pourquoi c'est vide** et propose une action.
- **Deux états vides distincts** : « aucun résultat » et « rien encore fait ».
- **Un bouton dit ce qui va se passer.** « Commencer » sur un cours à moitié
  fait laisse croire à une progression perdue.
- **Un élément verrouillé affiche sa condition.** Un cadenas sans explication
  est une frustration gratuite.
- **Les compteurs affichés sont recalculés, jamais incrémentés à l'aveugle.**
- Pagination serveur partout, recherche débouncée 350 ms.
- Cible tactile ≥ 44 px.

## 8. Vérification avant de livrer

Un module n'est **pas terminé** tant que ces cinq points ne sont pas verts.

1. `./vendor/bin/pint` — style.
2. `php artisan test` — **tous** les tests, pas seulement les nouveaux.
3. **Ouvrir l'écran dans un vrai navigateur.** Les tests n'ont jamais vu : une
   sidebar écrasée de moitié, des pastilles sans style, des vues servies sans
   layout, 220 images jamais copiées, une page qui plante sur un `match`.
4. **Audit responsive** sur 390 / 768 / 1024 / 1280 / 1512 px, avec le harnais
   CDP du projet. Attention : `--window-size` de Chrome subit la mise à
   l'échelle Windows, et l'application envoie `X-Frame-Options: DENY` — seul
   `Emulation.setDeviceMetricsOverride` donne la largeur demandée.
5. **Crawl des liens** : 0 lien cassé, 0 image manquante.

## 9. Écrire du code lisible

- `declare(strict_types=1);` partout, PHP 8.3 typé.
- **Nommage en français** dans le domaine — c'est la langue du projet.
- Les commentaires expliquent **pourquoi**, jamais **quoi**. Un commentaire qui
  paraphrase le code est du bruit ; un commentaire qui dit ce qu'on a évité et
  pourquoi vaut de l'or six mois plus tard.
- Pas de code mort, pas de duplication, pas de dette laissée sans note.

## 10. Rendre compte

- **Dire ce qui n'est pas fait.** Un rapport qui tait une réserve est pire
  qu'un retard annoncé.
- Tout écart au prototype ou à la documentation → `docs/ARBITRAGES.md`.
- Ne jamais annoncer « terminé » sans avoir exécuté les cinq points du §8.

---

## 11. La fidélité au prototype se MESURE

Un écran qui répond HTTP 200 n'est pas un écran transposé. Neuf écrans ont
affiché « Le contenu de cette page arrive à son sprint » pendant six sprints,
sept autres avaient été redessinés, trois pages n'avaient aucune route — et la
suite de tests était verte tout du long.

Avant de déclarer un écran fini, on le mesure :

```
AUDIT_CAPTURE=<dossier> php artisan test --filter=CaptureDesEcrans
python scratchpad/audit-prive.py      # écrans authentifiés
python scratchpad/audit-public.py     # écrans publics
```

Le relevé vit dans `docs/CONFORMITE_PROTOTYPE.md`. Sous 90 % de motifs communs,
on regarde `motifs_absents` dans le JSON du comparateur : il nomme les conteneurs
du prototype que le rendu ne produit pas.

**Et tout écart au prototype se consigne** dans `ARBITRAGES.md`, avec sa raison.
Un écart non écrit est un bug qui attend d'être « corrigé » par quelqu'un qui ne
saura pas qu'il était voulu.
