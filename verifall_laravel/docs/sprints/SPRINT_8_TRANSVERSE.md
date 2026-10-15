# Sprint 8 — Transverse final

SEO, analytics, sauvegardes et cron, API v1 mobile, durcissement sécurité,
audit RGPD de bout en bout.

Préambule obligatoire : `docs/AGENTS_REGLES_DU_CHANTIER.md`.

C'est le sprint où l'on cesse d'ajouter des écrans pour rendre solide ce qui
existe. Rien n'y est spectaculaire ; tout y est ce qui distingue un produit
livrable d'une démonstration.

---

## 1. Le contrat d'API — à écrire AVANT le code

`docs/kb/API_SPECIFICATION.md` **n'a jamais été fourni**. C'est la réserve la
plus ancienne du projet, et elle bloque deux choses à la fois : l'API v1 et
l'application Flutter.

Écrire d'abord le contrat, le faire valider, coder ensuite. Une API dessinée en
codant devient la forme du code, pas celle du besoin — et l'application mobile
en hérite pour des années.

Le contrat doit fixer :

- l'authentification (Sanctum : jetons d'appareil, révocation, expiration) ;
- la pagination, le filtrage, le tri — une seule convention, partout ;
- la forme des erreurs — une seule, y compris pour la validation ;
- le versionnement et ce qui constitue une rupture ;
- les limites de cadence, par jeton et par adresse ;
- **ce que l'API n'expose pas** : pas d'identifiant auto-incrémenté, pas
  d'adresse en clair, pas de champ interne.

## 2. SEO

Le site public est en ligne, il n'est pas encore trouvable.

- **Balises par page** : `<title>`, meta description, canonique, Open Graph,
  Twitter Card. Les modèles portent déjà `meta_title` et `meta_description` —
  les utiliser, avec repli sur le titre et le chapô.
- **Données structurées** : `ClaimReview` sur les fact-checks publiés,
  `Article` sur le blog, `Course` sur l'Académie, `Organization` sur l'accueil.
  `ClaimReview` est le format que Google attend d'un fact-checker : c'est ce qui
  fait apparaître le démenti à côté de la rumeur. Pour ce produit, c'est le
  gain SEO le plus utile qui existe.
- **`sitemap.xml`** généré, **`robots.txt`** cohérent avec lui.
- **Pages d'erreur** 404, 403, 419, 429, 500, 503 — transposées du pack UX, pas
  laissées à Laravel.
- **Performance** : Lighthouse ≥ 90 sur le public. Images en `loading="lazy"`
  et dimensions déclarées (déjà fait par la transposition), polices
  auto-hébergées (déjà fait).

Ne pas indexer : l'espace citoyen, le back-office, les rapports de vérification
personnels.

## 3. Analytics

Un tableau de bord d'usage, alimenté par ce que la plateforme **mesure
réellement**. Relire le §2 des règles du chantier : un indicateur inventé est
pire qu'un indicateur absent.

Ce qui est mesurable aujourd'hui : vérifications par type et par verdict, taux
de reprise, économie d'appels réalisée par la base de faits, inscriptions,
progression LMS, certificats délivrés, activité du forum, ouvertures
d'invitations.

Mesure d'audience côté visiteur : **soumise au consentement** déjà recueilli par
la bannière cookies. Pas de traceur avant acceptation.

## 4. Sauvegardes et tâches planifiées

Quatre tâches sont déjà planifiées (purge RGPD, réconciliation des paiements,
dotation mensuelle, fermeture des opportunités expirées). Il en manque :
`LmsRemindStalledUsers`, `SendWeeklyNewsletter`.

**Le rappel qui compte** : ces tâches n'existent que si un
`php artisan schedule:run` tourne chaque minute sur le serveur. Une obligation
de rétention confiée à un planificateur qui n'est pas branché n'est pas une
obligation remplie. Le vérifier, et le documenter dans les instructions de
déploiement.

Sauvegardes : base + disque privé, chiffrées, restauration **testée** — une
sauvegarde jamais restaurée n'est pas une sauvegarde. Table `backups` et
`cron_runs` pour que l'écran d'administration dise la vérité sur ce qui a
réellement tourné.

## 5. Durcissement sécurité

- **En-têtes** : CSP réelle (aujourd'hui absente), HSTS, `X-Frame-Options`
  (déjà posé), `Referrer-Policy`, `Permissions-Policy`. La CSP est le morceau
  délicat : Livewire et Alpine demandent un réglage précis.
- **`APP_DEBUG=false`** en préproduction et en production, vérifié par un test.
- **`composer audit` et `npm audit`** dans la chaîne de livraison.
- **Limites de cadence** relues de bout en bout : connexion, inscription, OTP,
  vérification invité, vérification de certificat, API.
- **Téléversements** : disque privé, nom aléatoire, `mimes|max`, servis par
  route signée. Déjà en place — le vérifier une dernière fois.
- **Réserve ouverte** : `SameSite=Strict` contre le rappel OAuth. Elle bloque le
  sprint Socialite et doit être tranchée ici.

## 6. Audit RGPD de bout en bout

L'export et l'effacement en deux temps existent. Ce sprint les **vérifie
réellement** :

- l'export contient-il **tout** ce que la plateforme détient sur une personne —
  y compris ses votes au forum, ses tentatives de quiz, ses parrainages ?
- l'anonymisation laisse-t-elle des données nominatives quelque part ? Les
  certificats recopient le nom **exprès** : c'est un arbitrage rendu, il doit
  être défendable et écrit.
- les consentements sont-ils tous horodatés, versionnés, révocables ?
- les durées de rétention sont-elles appliquées, et par quoi ?

Produire une **cartographie des données personnelles** : où elles vivent,
combien de temps, pourquoi.

## 7. Dette à rembourser avant de clore

Nommée franchement, elle sera oubliée sinon :

- **Repositories manquants** sur les domaines des sprints 1 et 2 : les services
  y interrogent Eloquent directement.
- **`docs/kb/` est vide** — trois documents de la base de connaissances n'ont
  jamais été fournis.
- **Sept images** référencées par le prototype et jamais livrées.
- **Treize adaptateurs** sur dix-neuf restent à porter.
- La suite de tests **dépend du réseau** (`uncompromised()`) et échoue hors
  ligne.

## 8. Définition de terminé

1. `pint`, `phpstan --level=6`, `php artisan test` — tous verts.
2. `composer audit` et `npm audit` sans vulnérabilité connue.
3. Lighthouse ≥ 90 sur les pages publiques.
4. axe-core AA sans erreur bloquante.
5. Une restauration de sauvegarde **effectuée**, pas seulement configurée.
6. Le contrat d'API validé et respecté par l'implémentation.
7. La cartographie RGPD écrite et vérifiée.
8. `docs/ARBITRAGES.md` à jour, et **la liste de ce qui reste** — honnête.
