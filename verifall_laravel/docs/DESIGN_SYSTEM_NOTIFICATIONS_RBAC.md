# DESIGN_SYSTEM_CONFORMITE.md — Transposition prototype → Blade/Livewire

## Règle d'or (RESPECT_100%_TEMPLATE)
Le prototype `web/` est le contrat visuel. **Interdits : redesign, modification structurelle, repositionnement.** On conserve : structure de page, ordre des sections, espacements, conteneurs, hiérarchie typographique, positions des boutons/images/CTA, animations, comportement responsive, structure HTML, layout CSS, psychologie de conversion, funnel. La transposition = remplacer les seeders JS par des données Livewire, rien d'autre.

## Tokens (déjà dans tailwind.config du prototype — reprendre tel quel)
- Citoyen/public : `primary #09B850` (+dark/soft), `forest #063B2A`, `brandred #D6293E` (CTA Vérifier uniquement), neutres ink/slate2/line/canvas, états `vtrue/vfalse/vinvest/vinfo`, `xp #7C3AED`.
- Staff « Trusted Learning OS » : `#065F46 / #052E16 / #059669 / #D1FAE5`, neutres `#0F172A/#475569/#E2E8F0/#F8FAFC`.
- Fonts auto-hébergées : Inter (sans) + Instrument Sans (display) + Material Symbols Rounded — reprendre `css/fonts/`.
- **Ordre de cascade critique** : `fonts.css → citizen.css → staff.css → quill.snow.css → tailwind.css EN DERNIER`.

## Layouts Blade
- `layouts/public.blade.php` = shell du front (menu 6 entrées exactes : Pourquoi Vérif'All ? / Fonctionnalités / Comment ça marche ? / Notre Équipe / Tutoriels / Actualités).
- `layouts/citizen.blade.php` = topbar + sidebar sombre (sans label « MENU PRINCIPAL ») + bottom-nav mobile (masquée ≥1024) + FAB.
- `layouts/staff.blade.php` = sidebar deep-green groupée repliable + topbar Command Center (recherche, +Créer, fullscreen, notifications lues/non-lues, journal d'audit, menu compte).
- Composants partagés (`Livewire/Shared` + `components/`) : `x-kpi` (+accordéon mobile `x-kpi-grid`), `x-badge-statut` (● sémantique), `x-data-table` (desktop) / `x-card-list` (mobile), `x-offcanvas-form`, `x-delete-modal`, `x-bottom-sheet`, `x-empty-state`, `x-skeleton`, `x-toast`.

## Patterns obligatoires par page CRUD (tous modules)
Header (titre+desc+Importer/Exporter/CTA unique) → KPI 4 max **avec accordéon mobile (toutes les pages du projet)** → recherche débouncée 350 ms + filtres (desktop selects / mobile bottom sheet) + chips supprimables + compteur → bulk bar → DataTable (rows 64 px, hover) transformée en cards mobile (jamais de table miniature) → actions ⋮ desktop / bottom sheet mobile (Voir/Modifier/Dupliquer/Archiver/Supprimer) → **modale de suppression avec avertissement nominatif + Annuler** → pagination serveur → empty/loading/error states → footer. Import : modale avec **fichier sample téléchargeable** ; Export : CSV/PDF réels.

## Accessibilité (WCAG 2.2 AA — hérité du prototype, à préserver)
Cibles ≥44 px, focus visible, `aria-expanded/current/live`, labels visibles, erreurs liées aux champs, Échap ferme tout, `prefers-reduced-motion` respecté, contraste AA.

---

# NOTIFICATIONS_TOASTS.md

## Toasts
Composant global `Livewire\Shared\Toast` monté dans chaque layout. API : `$this->dispatch('toast', message: '✓ Cours enregistré', type: 'success')` (types success/error/info/warning — styles `.st-toast` / toast citoyen du prototype). Durée 3 s, empilables (max 3), `aria-live="polite"`, bottom-right desktop / bottom-center mobile. **Chaque action utilisateur produit un feedback** (succès nominatif, erreur actionnable). Undo : les suppressions douces affichent « Supprimé — Annuler » 6 s avant commit du Job.

## Notifications (database + broadcast Reverb + mail)
Namespaces : `Notifications/{FactChecking,Lms,Forum,Mentor,Admin}`. Exemples câblés aux événements :
- FactChecking : `VerdictReady` (citoyen, après pipeline), `UrgentSignalementQueued` (admin).
- Lms : `CertificateIssued`, `LiveClassReminder` (J-1, commande planifiée), `MonthlyFreeCourse` (1er du mois — reprend la carte 🎁 du prototype), `StalledCourseReminder` (7 j d'inactivité, deep-link Smart Resume).
- Mentor : `AssignmentSubmitted` (badge Urgent si >72 h), `NewCourseReview`.
- Forum : `OfficialVerdictLinked` (« ✓ VÉRIF'ALL CONFIRME »), `ReplyToThread`.
Cloche topbar = flux `database` non lues (badge décompteur, « Tout marquer lu ») — comportement identique au prototype. Préférences par canal dans Réglages citoyen (opt-out granulaire, RGPD).

---

# RBAC_ROLES_PERMISSIONS.md (aligné ROLE_ET_PERMISSIONS.md de la base de connaissances)

Rôles : **Admin** (tout), **Mentor** (production pédagogique scoppée à ses ressources), **Modérateur** (forum + signalements), **Citoyen** (aucun back-office).
Permissions par module (modèle `<module>.<action>`) : `course.{view,create,update,publish,archive,delete}`, `signalement.{view,assign,verdict,delete}`, `user.{view,suspend,export}`, `role.manage`, `system.{settings,backup,logs}`, etc.
Implémentation : Spatie Permission ; Policies par modèle (le serveur tranche, l'UI ne fait que refléter) ; middleware `role:` sur les groupes de routes staff ; scope Repository pour le Mentor ; matrice affichée sur la page Rôles (conforme au prototype) ; toute modification de rôle → audit log + notification admin.
Actions admin garanties : **log in / log out** (guard staff, session régénérée, audit), gestion complète des modules front + citoyen (les 45 entrées de la sidebar admin du prototype).
