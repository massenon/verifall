# Contrat DataTables — back-office Vérif'All

> Établi le 14 septembre 2026 à partir du **pilote « Utilisateurs »**, qui en est
> l'implémentation de référence. Directive fonctionnelle : `datatables.txt`
> (racine du dossier `verifall`). Décisions du porteur : traitement **côté
> serveur** ; les directives priment sur le prototype pour l'admin/mentor
> (style DataTables par défaut) ; un écran pilote avant les autres.
>
> Tout ce qui suit est vérifié sur le code du pilote. La matrice de la fin est
> tirée de `MenuDuBackOffice::GROUPES`, des gardes `can:` réelles des routes et
> de la lecture des composants — pas de la directive.

## 1. Fichiers du pilote — à cloner

| Rôle | Fichier | Réutilisable tel quel ? |
|---|---|---|
| Lecture et bornage de la requête (listes blanches) | `app/Support/DataTables/RequeteDataTables.php` | **Oui** |
| Résultat (total, filtré, page) | `app/Support/DataTables/PageDeResultats.php` | **Oui** |
| SearchBuilder → Eloquent | `app/Infrastructure/Persistence/DataTables/FiltreSearchBuilder.php` | **Oui** |
| Contrat du dépôt (clés publiques) | `app/Core/Application/Contracts/DepotUtilisateurs.php` | Modèle |
| Dépôt Eloquent (clés → SQL, filtres, eager loading) | `app/Infrastructure/Persistence/DepotUtilisateursEloquent.php` | Modèle |
| Service (Policy, export, audit, forme des lignes) | `app/Core/Identity/Services/TableauDesUtilisateurs.php` | Modèle |
| Contrôleur JSON mince | `app/Http/Controllers/Staff/DonneesDesUtilisateursController.php` | Modèle |
| Route `staff.users.data` | `routes/web.php` | Modèle |
| Couche JS commune | `public/assets/js/verifall-datatables.js` | **Oui** |
| Description de la table (colonnes, rendus, actions) | `public/assets/js/pages/staff-utilisateurs.js` | Modèle |
| Ajustements CSS (sous `[data-vdt]`) | `public/assets/css/pages/datatables-staff.css` | **Oui** |
| Bibliothèques vendorisées + versions + empreintes | `public/assets/vendor/datatables/` (voir `README.md`) | **Oui** |
| Tests | `tests/Feature/Staff/TableDesUtilisateursTest.php` | Modèle |

## 2. Structure HTML attendue

Dans la vue Livewire de l'écran, **à l'intérieur de l'élément racine** du
composant (voir §6, piège n° 1) :

```blade
<div wire:ignore
     data-vdt="<ecran>"
     data-vdt-source="{{ route('staff.<ecran>.data') }}"
     data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
     data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
     data-vdt-evenement="{{ \App\Livewire\Staff\<Composant>::EVENEMENT_TABLE_A_RECHARGER }}"
     @can('<module>.export') data-vdt-export="1" @endcan>

    {{-- Niveau 1 : recherche --}}
    <input id="…" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche">

    {{-- Niveau 2 : filtres rapides (listes fermées) --}}
    <select data-vdt-filtre="statut" data-vdt-url="statut">…</select>
    {{-- une propriété #[Url] Livewire existante se garde : data-vdt-livewire="filtreStatut" --}}

    {{-- Période : DateTime, format AAAA-MM-JJ --}}
    <input type="text" data-vdt-filtre="cree_du" data-vdt-url="du" data-vdt-date>
    <input type="text" data-vdt-filtre="cree_au" data-vdt-url="au" data-vdt-date>

    <button type="button" data-vdt-reinitialiser>Réinitialiser</button>

    {{-- État « erreur » --}}
    <div role="alert" hidden data-vdt-erreur>
        <p data-vdt-erreur-message>Impossible de charger les données.</p>
        <button type="button" data-vdt-reessayer>Réessayer</button>
    </div>

    <table id="table-<ecran>" style="width:100%" data-vdt-table>
        <thead><tr><th scope="col">…</th>…<th scope="col">Actions</th></tr></thead>
        <tbody></tbody>
    </table>
</div>

@push('styles') … CSS vendorisés + assets/css/pages/datatables-staff.css … @endpush
@push('scripts') … JS vendorisés dans l'ordre, puis verifall-datatables.js, puis pages/staff-<ecran>.js … @endpush
```

Règles :

- **Aucune classe inventée dans la vue.** `ClassesTailwindPresentesTest` refuse
  toute classe absente des CSS chargés ; la classe `display` de DataTables est
  posée par le JS. Les styles propres vont dans `datatables-staff.css`, sous
  `[data-vdt]`.
- **Aucune donnée dans les attributs `data-vdt-*`** : des adresses et un drapeau
  d'habilitation que le serveur revérifie. Jamais d'identifiant, jamais de nom.
- Les ids existants se gardent (le pilote a conservé `users-q`).
- Les gabarits `staff.blade.php` ont les piles `styles` et `scripts` : on
  empile, on ne modifie pas le gabarit. DataTables n'est chargé que là.
- Ordre des scripts (sans `defer`) : jQuery → cœur → Buttons → html5 → print →
  colVis → DateTime → SearchBuilder → Responsive → (Select/Scroller si
  l'écran les justifie) → `verifall-datatables.js` → fichier de page. JSZip et
  pdfmake **ne s'empilent pas** : la couche commune les charge au clic.

## 3. Côté JS : le fichier de page

```js
VerifallDataTables.monter(racine, {
    titreExport: "…", fichierExport: '…',
    ordre: [[<indice>, 'desc']],
    searchBuilder: [<indices des colonnes interrogeables>],   // omis = pas de bouton
    actions: ['<méthodeLivewire1>', '<méthodeLivewire2>'],    // liste blanche
    langue: { emptyTable: '…', zeroRecords: '…' },
    colonnes: [ { data, name, type, orderable, responsivePriority, className, render }, … ],
});
```

- `data` et `name` = la **clé publique** de la colonne (celle des listes
  blanches serveur). `type` : `string`, `num` ou `date` (utile à SearchBuilder).
- `render(donnee, type, ligne)` : `display` → HTML **échappé** par
  `VerifallDataTables.echapper()` ; `export` → texte brut. Le serveur n'envoie
  jamais de HTML.
- Colonne d'actions : `className: 'vdt-sans-export vdt-actions'`,
  `orderable: false`, `responsivePriority: 2`. Boutons :
  `<button type="button" class="st-icnbtn" data-vdt-action="<méthode>" data-vdt-uuid="<uuid>" title="…" aria-label="…">`.
- Priorités Responsive : identité 1, actions 2, statut 3, puis l'information
  essentielle ; le reste ≥ 5.

## 4. Requête et réponse JSON

**Route** : `GET /admin/<écran>/donnees`, nom `staff.<écran>.data`, **exactement
la même garde `can:` que l'écran** (un test le vérifie). Contrôleur invocable
mince ; tout passe par un service de `app/Core/<Domaine>/Services`.

### Paramètres acceptés

| Paramètre | Traitement serveur |
|---|---|
| `draw` | Renvoyé **en entier** (protection XSS exigée par DataTables) |
| `start` | Entier ≥ 0 ; forcé à 0 pour un export |
| `length` | 1–100 ; `-1`, `0` ou invalide → 25 ; au-delà de 100 → 100. Export : 5 000 |
| `search[value]` | Tronqué à 100 caractères, `LIKE %…%` sur les colonnes texte choisies par le dépôt |
| `order[i][name]` ou `order[i][column]` + `columns[i][data]` | Confronté à `COLONNES_TRIABLES` ; hors liste → **ignoré**. `dir` ≠ `desc` → `asc`. 3 tris au plus. Départage `id desc` |
| `filtres[<clé>]` | Confronté à `filtresAutorises()` : liste fermée de valeurs, ou `'date'` (AAAA-MM-JJ valide). Inconnu → ignoré |
| `searchBuilder` | ≤ 8 Ko sérialisés, profondeur ≤ 3, ≤ 20 critères ; `origData` confronté à la liste blanche, **type décidé par le serveur** ; conditions `=, !=, contains, !contains, starts, !starts, ends, !ends, null, !null` (texte), `=, !=, <, <=, >=, >, between, !between, null, !null` (nombre), `=, !=, <, >, between, !between, null, !null` (date) |
| `export=1` | Exige la permission d'export ; sinon **403** |

### Réponse

```json
{
  "draw": 3,
  "recordsTotal": 142,
  "recordsFiltered": 8,
  "data": [ { "uuid": "…", "<clé publique>": "texte ou nombre", …, "actions": { "<action>": true } } ],
  "exportTronque": false
}
```

- En-tête `Cache-Control: no-store` (données personnelles).
- Chaque ligne est construite **champ par champ**, jamais `toArray()` : pas
  d'`id`, pas de hachage, pas de secret, pas de clé étrangère. Le dépôt ne
  `select` que les colonnes lues.
- `actions` : un booléen par action, **réponse de la Policy pour cette ligne**.
  Le composant Livewire ré-autorise au clic.
- 401 sans session, 403 sans permission : la couche JS affiche un message
  distinct pour chacun (§5).

## 5. Comportements pris en charge par `verifall-datatables.js`

| Besoin (directive) | Mise en œuvre |
|---|---|
| Recherche instantanée, insensible à la casse | Débounce 350 ms (même valeur que `wire:model.live.debounce.350ms`) ; collation MySQL |
| Filtres rapides | `change` → redessin serveur, page 1 |
| Période | `DataTable.DateTime`, format AAAA-MM-JJ ; saisie clavier relancée seulement sur date complète |
| Filtres avancés | Bouton `searchBuilder` (« Filtres avancés »), **fermé par défaut**, `depthLimit: 2` |
| Colonnes | Bouton `colvis` (hors colonne d'actions) |
| Export | Collection Copier / CSV (`;`, BOM) / Excel / PDF / Imprimer, **affichée seulement avec `data-vdt-export="1"`**. Porte sur le **jeu filtré entier** : requête `export=1`, export avant dessin (dessin annulé), rechargement de la page où l'on était. Colonnes exportées = colonnes visibles moins actions |
| État dans l'adresse | Chaque champ `data-vdt-url` suit `history.replaceState` ; les propriétés `#[Url]` Livewire existantes sont tenues à jour par `$wire.$set(…, false)` pour qu'un aller-retour Livewire ne les efface pas |
| Chargement | `processing` DataTables |
| Vide | `emptyTable` (recordsTotal = 0) |
| Aucun résultat | `zeroRecords` + bouton « Réinitialiser les filtres » (recordsTotal > 0) |
| Erreur | Bandeau `[data-vdt-erreur]` + « Réessayer ». Lu sur l'événement **`xhr`** (JSON nul) : avec `errMode = 'none'`, DataTables 2.3.8 ne déclenche **pas** `error` sur un échec HTTP (constaté au navigateur). Messages distincts 401/419, 403, réseau, autre |
| Mobile | Responsive + priorités ; filtres pleine largeur sous 640 px |

## 6. Livewire ↔ DataTables

1. **La zone DataTables est en `wire:ignore`, et DANS la racine du composant.**
   Livewire ne suit que le premier élément racine : tout ce qui est écrit après
   la `</div>` fermante n'est jamais rendu au navigateur. Le pilote l'a
   découvert sur les modales de suspension de l'écran Utilisateurs — le clic
   posait l'état côté serveur, rien ne s'affichait, et les tests Livewire
   passaient (ils lisent l'état, pas le DOM). **Vérifier ce point sur chaque
   écran migré.**
2. **Actions de ligne** : délégation de clic sur la racine →
   `Livewire.find(wire:id).$call(méthode, uuid)`. La méthode doit figurer dans
   `actions` (liste blanche JS) et l'UUID passer le motif ; le composant
   ré-autorise. On appelle les **méthodes existantes** (`ouvrirEdition`,
   `ouvrirLaSuspension`…) : tiroirs et modales restent ceux de l'écran.
3. **Rechargement après écriture** : le composant déclare
   `public const EVENEMENT_TABLE_A_RECHARGER = '<écran>-modifies';` et appelle
   `$this->dispatch(self::EVENEMENT_TABLE_A_RECHARGER)` après chaque écriture
   réussie ; la couche JS écoute et fait `table.ajax.reload(null, false)`
   (page conservée). Un test `assertDispatched` le tient.
4. **`render()` ne calcule plus la liste** : la requête paginée migre dans le
   dépôt. La garder coûterait une requête par aller-retour Livewire pour une
   table que personne n'affiche.

## 7. Sécurité et performance — la liste de contrôle

- [ ] Route JSON : garde `can:` **identique** à l'écran (test).
- [ ] Service : `authorize('viewAny')` rejoué ; export sous permission dédiée
      **si elle existe** dans `MatricePermissions` — sinon export **non
      proposé** et signalé (ne pas inventer de permission).
- [ ] Export journalisé via `AuditLogService::record('<module>.export', …)`
      avec les critères.
- [ ] Listes blanches : tri, filtres, SearchBuilder. Aucun nom de colonne SQL
      ne vient du navigateur.
- [ ] Lignes construites champ par champ ; test « pas d'`id`, pas de secret ».
- [ ] Eager loading de tout ce que la ligne affiche ; drapeaux de Policy sans
      requête par ligne (le pilote a fait lire à `UserPolicy::suspend` les rôles
      déjà chargés) ; test « le nombre de requêtes ne dépend pas de la longueur ».
- [ ] `length` ≤ 100, export ≤ 5 000 avec `exportTronque`.
- [ ] Rendu JS échappé ; test manuel avec un nom `<img src=x onerror=…>`.
- [ ] Mentor : jeu **filtré côté requête** (Policy/scope), jamais « la table
      admin sans quelques boutons ».
- [ ] Vérification navigateur : 0 erreur console, recherche, tri, filtre,
      pagination, export, action de ligne, rechargement après écriture.

## 8. Permissions d'export disponibles

`MatricePermissions` ne déclare **qu'une** permission d'export : `user.export`
(Admin). Pour tout autre écran, la règle « permission d'accès ≠ permission
d'export » ne peut pas être tenue sans une décision du porteur :

- soit on crée `<module>.export` (nouvelle permission — hors du périmètre d'un
  agent, et soumise au cliquet `PermissionsSansGardeTest`) ;
- soit on n'affiche **pas** d'export sur l'écran (défaut recommandé) ;
- soit on accepte explicitement de l'adosser à la permission de consultation.

## 9. Matrice page par page

Légende — **Volume** : B = borné (référentiel, < quelques centaines) ; M =
moyen (croît avec l'activité) ; N = non borné. **Mode** : S = server-side
(route JSON + dépôt) ; C = client (DataTables sur les lignes déjà rendues,
pagination Livewire conservée) ; — = pas de DataTable. **Ext.** : B = Buttons
(colVis ; export seulement si §8 le permet), D = DateTime, SB = SearchBuilder,
R = Responsive, Sel = Select (seulement si une action groupée existe), Sc =
Scroller, RR = RowReorder (ordre métier). « Mentor » = accessible au rôle Mentor
d'après sa garde et `MatricePermissions::pourRole(Mentor)`.

Le relevé « état actuel » vient du code : `<x-data-table>` dans la vue,
`paginate()` dans le composant, dépôt injecté.

### Tableau de bord, Fact-checking, Parrainage

| Entrée | Route · garde | État actuel | Mentor | DataTable | Volume · mode | Ext. | Filtres rapides |
|---|---|---|---|---|---|---|---|
| Tableau de Bord | `staff.dashboard` · aucune (assumée) | panneaux | oui | **Non** (directive §9) | — | — | — |
| Signalements | `staff.verifications` · `signalement.view` | x-data-table, sans paginate | non | Oui | N · **S** | B D SB R | statut, verdict, type, mentor assigné, période |
| Fact checks | `staff.knowledge` · `viewAny VerifiedFact` + `staff.knowledge.data` | **DataTables S, `DepotFaitsVerifies::pourTableau`** | non | **Fait** | M · S | B (colVis, pas d'export — mais le CSV d'en-tête historique n'exige que `viewAny`, à arbitrer) D SB R | verdict, statut, période de mise à jour — *catégorie, visibilité : sans colonne* |
| Sources vérifiées | `staff.trusted-sources` · `viewAny TrustedSource` + `staff.trusted-sources.data` | **DataTables S, `DepotSourcesFiables::pourTableau`** | non | **Fait** | M · S | B (colVis) R | famille (dont « sans famille »), état, crédibilité en tranches — *type : sans colonne* |
| Catégories de sources | `staff.source-categories` · `viewAny SourceCategory` | x-data-table, paginate | non | Recommandée | B · C | B R | statut |
| Tipline WhatsApp | `staff.tipline` · `viewAny Setting` | écran de réglage, pas de liste | non | **Non** tant que l'écran reste une configuration (§20) | — | — | — |
| Historiques des Unités IA | `staff.unit-journal` · Gate `journal-des-unites` + `staff.unit-journal.data` | **DataTables S, `UnitLogRepositoryInterface::pourTableau`** | non | **Fait** | N · S | B (colVis) D SB R, sans Scroller ; aucune action de ligne | sens, motif, période — *outil, statut : sans colonne* |
| Packs d'unités | `staff.unit-packs` · `viewAny UnitPack` | x-data-table, paginate | non | Oui | B · C | B R | statut, disponibilité |
| Suivi des parrainages | `staff.referrals` · `viewAny Referral` | x-data-table, paginate | non | Oui | N · S | B D R | statut, période |
| Répertoire des outils | `staff.tools` · `viewAny Tool` | x-data-table, paginate | non | Oui | B · C | B R | catégorie, statut, visibilité |

### Académie, Espace mentor, Examens

| Entrée | Route · garde | État actuel | Mentor | DataTable | Volume · mode | Ext. | Filtres rapides |
|---|---|---|---|---|---|---|---|
| Catégories | `staff.course-categories` · `viewAny CourseCategory` | x-data-table, paginate | oui (`lms_category.view`) | Oui | B · C | R (RR si l'ordre est métier) | statut |
| Tous les Cours | `staff.courses` · `viewAny Course` | x-data-table, paginate | oui — **Policy borne à ses cours** | Oui | M · S | B R | catégorie, niveau, statut, premium, auteur (admin) |
| Banque de Questions | `staff.question-bank` · `viewAny QuestionBankItem` + `staff.question-bank.data` | **DataTables S, `DepotBanqueQuestions::pourTableau`** | oui — **aucune limite par personne** (Policy : fonds commun) | **Fait** | N · S | B (colVis) SB R ; ni propositions ni bonne réponse dans le JSON | cours, type, statut — *difficulté : sans colonne* |
| Live Classes | `staff.live-classes` · `viewAny LiveClass` | x-data-table, paginate | oui | Oui | M · C→S | B D R | cours, statut, période |
| Certificats | `staff.certificates` · `viewAny Certificate` | x-data-table, paginate | oui | Oui | N · S | B D R | cours, statut, période |
| Unités des Cours Premium | `staff.course-units` · `viewAny Course` | x-data-table, paginate | oui — Policy | Oui | B · C | R — **pas d'AutoFill (financier)** | cours, statut |
| Mes Étudiants | `staff.my-students` · `student.view` | x-data-table, sans paginate | oui | Oui | N · **S** (jeu borné au mentor par requête) | B D R | cours, statut, période |
| Progression | `staff.progression` · `student.progress` | x-data-table, sans paginate | oui | Oui | N · S | B R | cours, progression, période |
| Performance | `staff.mentor-analytics` · `student.progress` | x-data-table, sans paginate | oui | Optionnelle (graphes d'abord) | M · C | R | cours, période |
| Mes contenus | `staff.my-content` · `lesson.view` | x-data-table, sans paginate | oui | Oui | M · C | R | type, statut, cours |
| Liste des Examens | `staff.exams` · `viewAny Exam` + `staff.exams.data` | **DataTables S, `DepotExamens::pourTableau`** | oui — **aucune limite par personne** (Policy) | **Fait** | M · S | B (colVis) D R | cours, statut, période de création |
| Correction Examens | `staff.exam-grading` · `exam.grade` + `staff.exam-grading.data` | **DataTables S, `DepotCopiesExamen::pourTableau`** | oui — file commune, « Les miennes » = filtre | **Fait** | N · S | B (colVis) D R ; aucune réponse ni appréciation dans le JSON | examen, statut, correcteur (`moi` ou uuid), période de remise |
| Statistiques Globales | `staff.exam-stats` · `exam_stat.view` | x-data-table, `DepotCopiesExamen` | oui | Optionnelle | M · C | R | examen, période |
| Enrôlement aux Cours | `staff.course-enrolment` · `enrolment.view` | x-data-table, paginate | oui | Oui | N · **S** | B D R (Sel si action groupée) | cours, statut, période |
| Enrôlement aux Examens | `staff.exam-enrolment` · `enrolment.view` + `staff.exam-enrolment.data` | **DataTables S, `DepotInscriptionsExamen::pourTableau`** (et non `DepotExamens`) | oui — aucune limite par personne | **Fait** | N · S | B (colVis) D R | session, examen, statut, période de session — la session devient un filtre (avant : rien sans session choisie) |

### Hub, Communauté, Blog, Ressources & com

| Entrée | Route · garde | État actuel | Mentor | DataTable | Volume · mode | Ext. | Filtres rapides |
|---|---|---|---|---|---|---|---|
| Actualités | `staff.hub-news` · `hub_article.view` | x-data-table, paginate | non | Oui | M · S | B D R | catégorie, statut, verdict, période |
| Jobs Vérifiés | `staff.hub-jobs` · `viewAny HubJob` | x-data-table, paginate (patron d'écran) | non | Oui | M · C | B D R | secteur, statut, période |
| Portails Officiels | `staff.hub-portals` · `hub_portal.view` | x-data-table, paginate | non | Oui | B · C | R | catégorie, statut |
| Catégories du Forum | `staff.forum-categories` · `forum_category.view` | x-data-table, paginate | non | Oui | B · C | R (RR si ordre métier) | statut |
| Forum Citoyen | `staff.forum` · `forum.view` | x-data-table, paginate | non | Oui | N · **S** | B D SB R | catégorie, statut, signalé, période |
| Contenus signalés | `staff.content-reports` · `viewAny ContentReport` + `staff.content-reports.data` | **DataTables S, `DepotSignalementsContenu::pourTableau`** | non (Modérateur : oui) | **Fait** | N · S | B (colVis) D R ; départage par ancienneté croissante (la file se vide par le bas) | statut, motif, type, période |
| Mots surveillés | `staff.moderation-keywords` · `viewAny ModerationKeyword` | x-data-table, paginate | non | Oui | B · C | R | catégorie, niveau |
| Catégories du Blog | `staff.blog-categories` · `blog_category.view` | x-data-table, paginate | non | Oui | B · C | R | statut |
| Articles du Blog | `staff.posts` · `post.view` | x-data-table, paginate | non | Oui | M · S | B D R | catégorie, auteur, statut, période |
| Nos Partenaires | `staff.partners` · `partner.view` | x-data-table, paginate | non | Oui | B · C | R | type, statut |
| Gestion FAQ | `staff.faq` · `faq.view` | x-data-table, paginate | non | Oui | B · C | R (RR si ordre métier) | catégorie, statut |
| Témoignages | `staff.testimonials` · `testimonial.view` | x-data-table, paginate | non | Oui | B · C | R | statut, publication |
| Newsletters — onglet Abonnés | `staff.newsletter` · `viewAny NewsletterSubscriber` + `staff.newsletter.data` | **DataTables S, `DepotAbonnes::pourTableau`** ; montage à la première ouverture de l'onglet | non | **Fait** | N · S | B (colVis) D R ; jamais jetons ni `consent_ip_hash` | état, période d'inscription |
| Messages reçus | `staff.contact-messages` · `viewAny ContactMessage` + `staff.contact-messages.data` | **DataTables S, `DepotMessagesContact::pourTableau`** | non | **Fait** | N · S | B (colVis) D R ; ni téléphone, ni `ip_hash`, ni note interne | statut, nature, période |

### Utilisateurs, Config système, Sécurité, Maintenance

| Entrée | Route · garde | État actuel | Mentor | DataTable | Volume · mode | Ext. | Filtres rapides |
|---|---|---|---|---|---|---|---|
| **Utilisateurs** (pilote) | `staff.users` · `viewAny User` + `staff.users.data` | **DataTables S, `DepotUtilisateurs`** | **non** (pas de `user.view`) | **Fait** | N · S | B (export `user.export`) D SB R | type, rôle, statut, KYC, activité, période d'inscription |
| Mentors | `staff.mentors` · `mentor.view` | x-data-table, paginate | non | Oui | M · S | B R | statut, KYC |
| Vérification KYC | `staff.kyc` · `kyc.view` | x-data-table, paginate | non | Oui | N · **S** | D R — **aucune pièce dans le JSON**, métadonnées seules | statut, type de pièce, période |
| Rôles & Permissions | `staff.roles` · `viewAny Role` | lecture seule, pas de x-data-table | non | **Non** (4 rôles, matrice en lecture) | — | — | — |
| Réglages du Système | `staff.settings` · `system.settings` + `viewAny Setting` | formulaire à onglets | non | **Non** (§20 : formulaire) | — | — | — |
| Templates Emails | `staff.email-templates` · `system.email_template` | x-data-table | non | Oui | B · C | R | type, statut |
| Campagnes Publicitaires | `staff.campaigns` · `campaign.view` | x-data-table, paginate | non | Oui | B · C | D R | statut, période |
| Badges de progression | `staff.badges` · `system.badge` | x-data-table, paginate | non | Oui | B · C | R | type, statut |
| Certificat Template | `staff.certificate-template` · `system.certificate_template` + `viewAny Setting` | aperçu | non | **Non** (un seul gabarit) | — | — | — |
| Logs d'Audit | `staff.audit` · `viewAny AuditLog` | x-data-table, paginate | non | Oui — **critique** | N · **S** | D SB R (Sc si besoin) ; **export non proposé** (§8) | action, module, acteur, période |
| Logs Emails | `staff.email-logs` · `security.email_logs` | x-data-table, paginate | non | Oui | N · **S** | D R | statut, gabarit, période |
| Logs Unités IA | = `staff.unit-journal` (doublon assumé) | voir Fact-checking | non | — (même écran) | — | — | — |
| Alertes Intrusion | `staff.alerts` · `viewAny SecurityLog` | x-data-table, paginate | non | Oui | N · **S** | D R | niveau, type, période |
| Mode maintenance | `staff.maintenance-mode` · `maintenance.mode` | formulaire | non | **Non** | — | — | — |
| Sauvegardes BD | `staff.backups` · `maintenance.backup` | x-data-table | non | Oui | B · C | R | statut |
| Tâches Cron | `staff.cron` · `maintenance.cron` | x-data-table | non | Oui | B · C | R | statut |

**Décompte** : 55 entrées de menu, 54 routes distinctes (`staff.unit-journal`
en doublon assumé). DataTable **non** : 6 écrans (tableau de bord, tipline,
rôles, réglages, gabarit de certificat, mode maintenance). Sur les 48 écrans
restants, le pilote en fait 1 : **47 à migrer, 26 en server-side (dont Live
Classes, à basculer quand le volume le demandera) et 21 en client**.

**Après la vague 1 (14 septembre 2026)** : 11 écrans faits (pilote + 10 en
server-side), **37 restent — 16 en server-side, 21 en client**. Constat de la
vague : aucune des listes Académie/Examens ne bornait le Mentor à « ses »
lignes ; les Policies en font un fonds commun. La migration l'a reproduit tel
quel ; toute restriction serait une décision produit, pas un détail d'écran.

> Les écrans en mode **C** peuvent garder leur requête Livewire paginée et
> n'ajouter que DataTables sur le DOM déjà rendu **à condition** que la table ne
> soit plus redessinée par Livewire (`wire:ignore` + rechargement complet de la
> zone après écriture). Si ce compromis se révèle fragile au deuxième écran, les
> passer en S : le coût marginal est faible une fois le contrat en place.

## 9 bis. Fiche profil — plusieurs tables sur une page (14 septembre 2026)

`admin/utilisateurs/{uuid}` (`staff.users.show`, garde `can:view,user`) est le
premier écran à porter **plusieurs** DataTables. Le contrat ci-dessus vaut tel
quel pour chacune ; trois points s'y ajoutent.

| Point | Règle |
|---|---|
| Une route JSON paramétrée | `staff.users.show.data` = `utilisateurs/{user}/donnees/{onglet}`, **même garde** que la fiche, `{onglet}` borné par `whereIn` à `DepotFicheDuCompte::COLONNES_TRIABLES` (liste fermée), revérifié par le service. Une permission propre à un onglet (Journal → `security.logs`) est exigée par le service, pas par la route. |
| Un dépôt, N onglets | `DepotFicheDuCompte` : requête de base (les lignes de CE compte), tris, recherche/filtres, colonnes lues et eager loading **par onglet** ; pagination et compteurs communs. Les filtres rapides et leurs libellés viennent d'une seule source (`FicheDuCompte::optionsDesFiltres`). |
| Montage paresseux | Chaque onglet est une racine `[data-vdt]` en `wire:ignore`, montée par `VerifallDataTables.monter()` **à la première ouverture** (une table mesurée dans un panneau `hidden` calcule mal ses colonnes) ; à la réouverture, `columns.adjust()` + `responsive.recalc()`. En-têtes déclarés par `title` dans le fichier de page. Pas de `data-vdt-url` sur les champs d'onglet : plusieurs tables se disputeraient les mêmes clés d'adresse ; seul `?onglet=` est écrit. |
| Pas d'export | L'historique détaillé d'une personne n'est pas la liste des comptes : `user.export` ne s'y étend pas sans décision (§8). `export=1` → 403. |
| Lien depuis une table | Adresse-gabarit sur la racine (`data-vdt-fiche`, uuid fictif `00000000-…` remplacé côté JS après contrôle du motif) plutôt qu'un champ ajouté au JSON. |

## 10. Coût estimé par écran

| Type | Travail | Estimation |
|---|---|---|
| **S** avec dépôt existant | méthode `pourTableau` + service + contrôleur + route + fichier de page + test | ½ à ¾ de journée |
| **S** sans dépôt | idem + contrat et implémentation du dépôt, déplacement de la requête hors du composant | ¾ à 1 journée |
| **C** (référentiel borné) | zone `wire:ignore`, fichier de page, rechargement après écriture, vérification navigateur | 2 à 3 heures |
| Toujours | vérifier que tiroirs et modales sont DANS la racine (§6.1), passer la liste de contrôle §7 | inclus |

Le pilote lui-même a coûté davantage (couche commune, vendorisation, contrat).
Hypothèse : aucune nouvelle permission — les exports hors `user.export`
attendent une décision (§8).
