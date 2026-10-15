# TOAST_INSTRUCTION.md

Composant unique `Livewire\Shared\Toast` monté dans les 3 layouts. Déclenchement : `$this->dispatch('toast', message: '…', type: 'success|error|info|warning', undoEvent: null)`.
Style : prototype exact (.st-toast staff / toast citoyen) — fond ink, radius 12, 3 s (erreurs 5 s), bottom-right desktop / bottom-center mobile, pile max 3 (FIFO), `aria-live="polite"` (erreurs `assertive`), pause au survol.
Contenu : succès nominatif (« ✓ Cours “OSINT Fondamentaux” enregistré. »), erreur actionnable (quoi + comment corriger), jamais de jargon technique.
Undo : suppressions douces → toast « Supprimé — Annuler » 6 s avec bouton ; le Job de commit n'est dispatché qu'après expiration ; Annuler restaure + toast confirmation.
Interdits : toast bloquant, toast pour les erreurs de validation de champ (elles restent près du champ), double toast pour une même action, alert() natif.
Chaque action utilisateur (CRUD, toggle, envoi, export, import) produit exactement un toast.
