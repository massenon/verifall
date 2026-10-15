/*
 * Le pont entre les modales du design system et Livewire.
 *
 * Deux besoins, et un seul fichier parce qu'ils vont ensemble :
 *
 * 1. Ouvrir ou fermer une modale depuis le serveur. Le composant émet
 *    `ouvrir-modale` / `fermer-modale` ; on relaie à `VF.openModal`.
 *
 * 2. Faire remonter le contenu d'une zone `contenteditable`. Livewire ne sait
 *    pas lier un `contenteditable` : on recopie donc son HTML dans un champ
 *    caché, que `wire:model` observe. Sans ça, la personne écrit son analyse et
 *    le serveur reçoit une chaîne vide — l'erreur la plus décourageante qui
 *    soit, puisqu'elle survient au moment d'envoyer.
 */
(function () {
  'use strict';

  window.addEventListener('ouvrir-modale', function (e) {
    var id = e.detail && e.detail.id;
    if (id && window.VF && typeof window.VF.openModal === 'function') {
      window.VF.openModal(id);
    }
  });

  window.addEventListener('fermer-modale', function (e) {
    var id = e.detail && e.detail.id;
    if (id && window.VF && typeof window.VF.closeModal === 'function') {
      window.VF.closeModal(id);
    }
  });

  function relierLesZonesRiches() {
    document.querySelectorAll('[data-rich][data-cible]').forEach(function (zone) {
      if (zone.dataset.relie === '1') return;
      zone.dataset.relie = '1';

      var editeur = zone.querySelector('.rich-editor');
      var champ = document.getElementById(zone.getAttribute('data-cible'));
      if (!editeur || !champ) return;

      function reporter() {
        champ.value = editeur.innerHTML;
        /* `input` est l'événement que wire:model écoute. */
        champ.dispatchEvent(new Event('input', { bubbles: true }));
      }

      editeur.addEventListener('input', reporter);
      editeur.addEventListener('blur', reporter);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', relierLesZonesRiches);
  } else {
    relierLesZonesRiches();
  }

  document.addEventListener('livewire:navigated', relierLesZonesRiches);
  document.addEventListener('livewire:update', relierLesZonesRiches);
})();
