/*
 * Saisie d'un code à six cases.
 *
 * Le prototype découpe le code en six champs : c'est ce que le clavier des
 * téléphones attend, et ça évite de compter les chiffres à voix basse. Le
 * curseur avance tout seul, la touche d'effacement revient en arrière, et un
 * collage de six chiffres se répartit sur les six cases — parce que le code
 * arrive par courriel et qu'on le copie, on ne le retape pas.
 *
 * Le code assemblé est recopié dans un champ caché : Livewire ne sait pas lier
 * six champs à une seule propriété.
 */
(function () {
  'use strict';

  function relier(groupe) {
    if (groupe.dataset.relie === '1') return;
    groupe.dataset.relie = '1';

    var cases = Array.prototype.slice.call(groupe.querySelectorAll('.otp-input'));
    var champ = document.getElementById(groupe.getAttribute('data-otp-cible'));
    if (!cases.length || !champ) return;

    function reporter() {
      champ.value = cases.map(function (c) { return c.value; }).join('');
      champ.dispatchEvent(new Event('input', { bubbles: true }));
    }

    cases.forEach(function (uneCase, rang) {
      uneCase.addEventListener('input', function (e) {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 1);
        if (e.target.value && rang < cases.length - 1) cases[rang + 1].focus();
        reporter();
      });

      uneCase.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !e.target.value && rang > 0) cases[rang - 1].focus();
      });

      uneCase.addEventListener('paste', function (e) {
        var colle = (e.clipboardData || window.clipboardData).getData('text') || '';
        var chiffres = colle.replace(/\D/g, '').slice(0, cases.length);
        if (!chiffres) return;
        e.preventDefault();
        chiffres.split('').forEach(function (chiffre, i) { cases[i].value = chiffre; });
        cases[Math.min(chiffres.length, cases.length - 1)].focus();
        reporter();
      });
    });

    /*
     * Focus sur la premiere case, MAIS pas si l'on est deja en train de
     * saisir. Sans cette garde, chaque frappe declencherait un rendu Livewire,
     * donc une re-liaison, donc un retour au debut : on ne pourrait taper
     * qu'un seul chiffre.
     */
    var dejaDansLeGroupe = cases.indexOf(document.activeElement) !== -1;
    if (cases[0] && !dejaDansLeGroupe) cases[0].focus();
  }

  function activer() {
    document.querySelectorAll('[data-otp-groupe]').forEach(relier);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', activer);
  } else {
    activer();
  }

  /*
   * ── POURQUOI CE BLOC A ETE REECRIT ──────────────────────────────────────
   *
   * Il ecoutait `livewire:update`, un evenement qui N'EXISTE PAS en
   * Livewire 3. Consequence : les six cases n'etaient jamais cablees, parce
   * que l'etape 3 n'apparait pas au chargement de la page mais APRES un rendu
   * Livewire — et `activer()` avait alors deja tourne, sur un DOM qui ne
   * contenait pas encore le groupe.
   *
   * A l'ecran, on tapait le code et les six chiffres s'entassaient dans la
   * premiere case : le curseur n'avancait pas, le champ cache restait vide, et
   * « Valider le code » repondait « Saisissez le code recu par courriel ».
   *
   * Aucun test ne pouvait le voir : le balisage est present et correct, c'est
   * le JavaScript qui ne s'y attachait pas.
   */
  document.addEventListener('livewire:navigated', activer);

  document.addEventListener('livewire:init', function () {
    if (window.Livewire && typeof window.Livewire.hook === 'function') {
      // `morphed` se declenche apres CHAQUE rendu d'un composant : c'est le
      // seul moment ou l'on sait que le nouveau DOM est en place.
      window.Livewire.hook('morphed', activer);
    }
  });

  // Repli si le crochet n'est pas disponible : Livewire emet cet evenement
  // une fois son demarrage acheve.
  document.addEventListener('livewire:initialized', activer);
})();
