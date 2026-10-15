# Lots « transparence & diffusion » — règles du chantier parallèle

*Écrit le 26 août 2026, pour quatre agents qui écrivent en même temps dans le
même dépôt.*

---

## Pourquoi ces quatre lots, et dans cet ordre

L'analyse produit place la **transparence IFCN** en tête, et la raison est
mesurable : Vérif'All émet déjà du `ClaimReview` correct, mais les panneaux de
fact-checking de Google, Meta et TikTok ne le consomment que pour les
organisations accréditées. Le balisage est là, personne ne le lit. Trois pages
et une table ouvrent cette porte.

La **carte de démenti** vient ensuite parce qu'elle conditionne tout le reste :
au Bénin, une rumeur voyage en image sur WhatsApp. Un démenti qui ne se partage
que sous forme de lien ne circule pas à parité.

L'**infolettre** transforme la plateforme de sujet en source — elle existe déjà
en base, éteinte.

Les **notes de communauté** protègent le forum de lui-même sur des sujets qui
polarisent.

---

## Les six règles de propriété

Elles reprennent, mot pour mot, ce qui a permis à cinq agents d'écrire le
dépôt Flutter en parallèle sans un seul conflit.

### 1. Tu écris dans TES fichiers, et nulle part ailleurs

La liste de tes fichiers est dans ta consigne. Elle fait foi.

### 2. Ces fichiers-là sont EN LECTURE SEULE, pour tout le monde

```
routes/web.php
routes/console.php
resources/views/components/layouts/staff.blade.php
resources/views/components/layouts/public.blade.php
app/Support/Rbac/MatricePermissions.php
app/Providers/*.php
database/seeders/DemoSeeder.php
bootstrap/app.php
config/*.php
```

Ce sont les points de couture. Quatre agents qui y touchent en même temps
produisent quatre versions incompatibles, et le dernier à écrire gagne en
silence.

**Tu ne les modifies pas. Tu écris dans ton compte rendu ce qu'il faut y
ajouter** — la ligne de route exacte, l'entrée de menu, la permission, l'appel
de seeder. L'intégration les posera.

C'est contraignant, et c'est le prix du parallélisme.

### 3. Un composant partagé qui manque ne se crée pas dans un lot

S'il sert à deux lots, il appartient au socle : signale-le, ne le duplique pas.
Un composant dupliqué dans deux dossiers diverge en une semaine.

Les composants existants à réemployer : `x-kpi-grid`, `x-kpi`, `x-data-table`,
`x-card-list`, `x-badge-statut`, `x-empty-state`, `x-offcanvas-form`,
`x-delete-modal`, `x-seo`.

### 4. Toute classe employée dans une vue doit EXISTER dans le CSS

Le build Tailwind est **figé**. Une classe absente ne produit aucune erreur :
elle est écrite, elle est lisible dans le balisage, et elle ne fait rien.

Ce piège a déjà coûté une fenêtre modale sans largeur et des onglets sans
onglet actif. Vérifie chaque classe :

```bash
grep -c "\.ma-classe" public/assets/css/tailwind.css
```

Ce qui manque va dans **ta propre feuille** sous `public/assets/css/pages/`,
chargée par `@push('styles')` — sauf si le composant est rendu dans le CORPS de
la page, auquel cas la pile est déjà vidée et la feuille n'arrivera jamais.
Dans ce cas, signale-le à l'intégration.

### 5. Aucun chiffre inventé

C'est la règle §2 du chantier, et elle est absolue sur ce produit.

Si une donnée n'est pas servie, l'écran le dit — il ne la remplace pas par une
valeur plausible. Pas de note calculée sur zéro avis, pas de « 0 % » là où la
vérité est « pas encore mesuré », pas de date d'événement qu'aucune source
n'établit.

### 6. Tu rends compte de ce qui n'est PAS fait

Un lot livré à 90 % qui se déclare fini bloque les trois autres au moment de
l'intégration.

---

## Ce que tout lot doit respecter

- `declare(strict_types=1);` partout, PHP 8.3 typé.
- **Aucune requête Eloquent hors dépôt ou service** — jamais dans un composant
  Livewire ni un contrôleur.
- **Aucune logique métier dans un composant Livewire** : elle vit dans
  `app/Core/<Domaine>/Services`.
- `$fillable` explicite sur chaque modèle.
- **Jamais `{!! !!}` sur une donnée d'utilisateur** — et si le contenu est
  éditorial riche, il passe par `clean()`.
- **Jamais d'identifiant auto-incrémenté en URL** : `uuid` ou `slug`.
- Toute action d'administration sensible → `AuditLogService::record()`.
- **Jamais modifier une migration déjà jouée** ; toujours une nouvelle.
- Commentaires et libellés **en français**, accents compris.

## Définition de fini

- `php artisan test` intégralement vert — **1 035 au minimum, jamais moins**.
- `./vendor/bin/pint` sans modification.
- Tes propres tests écrits, et ils testent le comportement qui compte — pas la
  présence d'une méthode.
- Un compte rendu qui liste : ce qui est fait, ce qui ne l'est pas et pourquoi,
  et **ce que l'intégration doit poser dans les fichiers verrouillés**.
- Chaque écart au prototype consigné dans `docs/ARBITRAGES.md`, à partir de
  l'entrée que ta consigne t'indique.
