# MODAL_FORMULAIRE_CRUD_INSTRUCTION.md

Deux contenants (composants uniques) :
- **Offcanvas** (`x-offcanvas-form`, 480 px droite, plein écran mobile) : création/édition des entités riches (celles du prototype). Header titre+×, corps scrollable sections labellisées, footer Annuler + Enregistrer (primaire). Quill lazy-init à la 1ʳᵉ ouverture.
- **Modale** (`x-modal`) : confirmations, formulaires ≤4 champs, import (avec **fichier sample téléchargeable** + validation + rapport de lignes).
Comportement commun : focus trap + focus 1ᵉʳ champ, Échap et backdrop ferment (sauf si dirty → confirmation « Modifications non enregistrées »), aria-modal + labelledby, retour du focus à l'élément déclencheur.
Formulaires : labels toujours visibles, aide contextuelle sous le champ, **validation temps réel** (wire:model.blur puis live après 1ʳᵉ erreur), erreurs près du champ (aria-invalid + describedby), états loading (spinner bouton + disabled) / success (fermeture + toast nominatif) / disabled cohérents. Enregistrer reste visible (footer sticky si corps long). Jamais deux modales empilées (l'offcanvas peut ouvrir UNE modale de confirmation max).
