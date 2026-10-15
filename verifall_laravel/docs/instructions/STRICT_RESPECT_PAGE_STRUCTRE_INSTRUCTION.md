# STRICT_RESPECT_PAGE_STRUCTRE_INSTRUCTION.md

Le prototype `resources/prototype/` est le **contrat**. Aucune interprétation.
Interdits absolus : redesign, modification structurelle, repositionnement d'éléments, ajout de sections non prévues, suppression d'éléments « jugés inutiles », changement de wording, autre lib UI.
On conserve 100 % : structure de page, ordre des sections, espacements, conteneurs, hiérarchie typographique, positions des boutons, placement des images, placement des CTA, animations, comportement responsive, structure HTML, layout CSS, psychologie de conversion, funnel.
Méthode de transposition : copier le HTML de la page prototype → remplacer les données seedées par les variables Livewire/Blade → remplacer les handlers JS simulés par wire:* → comparer visuellement côte à côte (diff screenshot si doute). Tout écart nécessaire (contrainte technique réelle) = documenté dans la PR + validation explicite d'Adderly AVANT merge.
