/*
 * Parrainage — copie du lien et partage natif.
 *
 * Le prototype simule les deux ; ici ils fonctionnent. Le partage natif n'est
 * pas décoratif au Bénin : c'est la voie normale vers WhatsApp depuis un
 * téléphone, et elle évite de passer par une page intermédiaire.
 */
(function () {
  'use strict';

  function annoncer(message) {
    if (window.VF && typeof window.VF.toast === 'function') window.VF.toast(message);
  }

  function copier(texte, message) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texte).then(
        function () { annoncer(message); },
        function () { annoncer('La copie a échoué — sélectionnez le lien à la main.'); }
      );
      return;
    }
    annoncer('La copie automatique n\'est pas disponible ici — sélectionnez le lien à la main.');
  }

  /* Accordéon de la FAQ — repris du script de page du prototype. */
  document.addEventListener('click', function (e) {
    var bascule = e.target.closest('[data-acc-toggle]');
    if (!bascule) return;
    var ouvert = bascule.getAttribute('aria-expanded') === 'true';
    bascule.setAttribute('aria-expanded', String(!ouvert));
    var panneau = bascule.nextElementSibling;
    if (panneau) panneau.classList.toggle('hidden', ouvert);
    var chevron = bascule.querySelector('.material-symbols-rounded');
    if (chevron) chevron.style.transform = ouvert ? '' : 'rotate(180deg)';
  });

  document.addEventListener('click', function (e) {
    var bouton = e.target.closest('[data-copier]');
    if (bouton) {
      copier(bouton.getAttribute('data-copier'), 'Lien copié — collez-le où vous voulez.');
      return;
    }

    var natif = e.target.closest('[data-partage-natif]');
    if (!natif) return;

    var message = natif.getAttribute('data-message') || '';
    var lien = natif.getAttribute('data-lien') || '';

    if (navigator.share) {
      navigator.share({ title: 'Invitation Vérif\'All', text: message, url: lien })
        .catch(function () { /* partage annulé : rien à signaler */ });
      return;
    }

    /* Sans partage natif — ordinateur de bureau, pour l'essentiel — on copie le
       message entier plutôt que le seul lien : c'est ce qu'on colle. */
    copier(message, 'Message d\'invitation copié — collez-le où vous voulez.');
  });
})();
