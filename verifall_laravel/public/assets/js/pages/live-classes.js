/*
 * Live Classes — la modale « Rejoindre ».
 *
 * Le lien Zoom n'est pas posé en `href` : on le montre d'abord, on le copie
 * ensuite. Une visioconférence qui s'ouvre sans prévenir surprend, surtout
 * depuis un téléphone partagé.
 *
 * Le fichier calendrier, lui, est fabriqué par le serveur — le prototype le
 * composait en JavaScript, mais la date exacte de la séance appartient à la
 * base, pas au navigateur.
 */
(function () {
  'use strict';

  document.addEventListener('click', function (e) {
    var bouton = e.target.closest('[data-join]');
    if (!bouton) return;

    var titre = document.querySelector('[data-join-title]');
    var lien = document.querySelector('[data-join-link]');
    if (titre) titre.textContent = bouton.getAttribute('data-join-titre') || '';
    if (lien) lien.textContent = bouton.getAttribute('data-join-lien') || '';

    if (window.VF && typeof window.VF.openModal === 'function') {
      window.VF.openModal('modal-join');
    }
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-join-copy]')) return;

    var lien = document.querySelector('[data-join-link]');
    var texte = lien ? lien.textContent : '';

    function termine() {
      if (window.VF) {
        if (typeof window.VF.closeModal === 'function') window.VF.closeModal('modal-join');
        if (typeof window.VF.toast === 'function') {
          window.VF.toast('Lien copié — ouvrez-le dans Zoom pour rejoindre la classe.');
        }
      }
      if (texte) window.open(texte, '_blank', 'noopener');
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texte).then(termine, termine);
    } else {
      termine();
    }
  });
})();
