# MODAL_DELETE_POPUP_WARNING.md — Suppressions

Composant unique `x-delete-modal` (role=alertdialog) : titre « Supprimer “{nom exact}” ? » (nom réel injecté, tronqué 60), corps = conséquences précises (« Cette action est irréversible. Les N leçons et M inscriptions associées seront également supprimées. » — compte réel), footer **Annuler (focus initial)** + « Supprimer définitivement » (rouge, jamais primaire vert).
Flux : soft delete immédiat + toast **« Supprimé — Annuler » 6 s** (undo restaure tout) → Job de purge après expiration. Cascades listées AVANT (le Service calcule les dépendances). Bulk : « Supprimer N éléments ? » + liste des 5 premiers noms « …et N-5 autres ».
Interdits : suppression sans modale, double-clic destructeur (disabled après clic), confirmation par simple « OK », suppression physique directe d'entités référencées (contraintes FK → message clair « Impossible : utilisé par X »). Toute suppression = audit log (avant/après).
