/*
 * Tiroir de filtres (téléphone).
 *
 * Deux listes déroulantes côte à côte tiennent mal sur un petit écran : le
 * prototype les remplace par un panneau qui monte du bas, où les choix sont des
 * pastilles assez grandes pour le pouce.
 *
 * Les choix eux-mêmes passent par Livewire ; ce script ne fait qu'ouvrir et
 * fermer. « Appliquer » ferme simplement — le filtre s'est déjà appliqué au
 * moment du clic sur la pastille, et faire semblant d'attendre serait mentir
 * sur ce qui se passe.
 */
(function () {
  'use strict';

  function tiroir() { return document.querySelector('[data-sheet]'); }

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-sheet-open]')) {
      var t = tiroir();
      if (t) t.classList.remove('hidden');
      return;
    }
    if (e.target.closest('[data-sheet-close]')) {
      var f = tiroir();
      if (f) f.classList.add('hidden');
    }
  });

  /* Échap ferme aussi : c'est le geste attendu de tout panneau modal. */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var t = tiroir();
    if (t && !t.classList.contains('hidden')) t.classList.add('hidden');
  });
})();
