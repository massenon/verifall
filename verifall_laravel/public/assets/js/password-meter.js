/* ==========================================================================
   Témoin de robustesse du mot de passe — Vérif'All
   ==========================================================================

   API demandée : passwordStrengthMeter({ containerElement, passwordInput, … }).
   Le contrat d'origine est respecté — même barème, mêmes paliers 25/50/75/100,
   mêmes événements `onScore0` … `onScore4` — avec trois écarts assumés, chacun
   documenté à l'endroit où il se produit :

     1. l'écoute se fait sur `input` et non sur `keyup` ;
     2. les couleurs par défaut sont celles de la charte, pas le dégradé
        orange → vert saturé de l'implémentation de référence ;
     3. la fonction est IDEMPOTENTE : rappelée sur le même conteneur, elle
        recycle ce qui existe au lieu d'empiler des barres et des écouteurs.

   Le troisième point n'est pas du confort : le champ vit dans une étape
   Livewire qui apparaît et disparaît, donc l'initialisation est rejouée.
   ========================================================================== */

(function () {
  'use strict';

  /* Palette de la charte. Le rouge et le vert sont ceux qui servent déjà aux
     verdicts « faux » et « vrai » : quelqu'un qui a lu un rapport connaît
     déjà ce code couleur, et le réemployer ici lui épargne d'en apprendre un
     second. */
  var PALETTE = {
    1: '#D6293E', // vfalse
    2: '#F7C32E', // attention
    3: '#4C7CF3', // information
    4: '#09B850'  // vtrue
  };

  var MESSAGES = ['', 'Trop faible', 'Faible', "C'est déjà mieux", 'Excellent'];

  /**
   * Le barème, tel que spécifié.
   *
   * Un point par famille présente — minuscule, majuscule, chiffre, longueur
   * minimale —, et un point plancher dès qu'on a tapé quelque chose : un champ
   * commencé n'affiche jamais « rien », sans quoi on ne saurait pas si le
   * témoin fonctionne.
   */
  function score(valeur, longueurMinimale) {
    if (valeur.length === 0) return 0;

    var points = 0;
    if (/[a-z]/.test(valeur)) points++;
    if (/[A-Z]/.test(valeur)) points++;
    if (/[0-9]/.test(valeur)) points++;
    if (valeur.length >= longueurMinimale) points++;

    return points === 0 ? 1 : points;
  }

  /**
   * @param {{containerElement: string, passwordInput: string, messageContainer?: string,
   *          showMessage?: boolean, messagesList?: string[], pswMinLength?: number,
   *          height?: number, borderRadius?: number,
   *          colorScore1?: string, colorScore2?: string, colorScore3?: string, colorScore4?: string}} options
   */
  window.passwordStrengthMeter = function (options) {
    var conteneur = document.querySelector(options.containerElement);
    var champ = document.querySelector(options.passwordInput);

    // Un sélecteur qui ne trouve rien n'est pas une erreur à ce niveau : le
    // champ vit dans une étape qui n'est pas toujours rendue.
    if (!conteneur || !champ) return null;

    var longueurMinimale = options.pswMinLength || 8;
    var messages = options.messagesList || MESSAGES;
    var etiquette = options.showMessage === false
      ? null
      : (options.messageContainer ? document.querySelector(options.messageContainer) : null);

    var couleurs = {
      1: options.colorScore1 || PALETTE[1],
      2: options.colorScore2 || PALETTE[2],
      3: options.colorScore3 || PALETTE[3],
      4: options.colorScore4 || PALETTE[4]
    };

    /* IDEMPOTENCE. Sans elle, revenir à l'étape 2 empilerait une barre de plus
       et un écouteur de plus à chaque passage : le témoin finirait par sauter
       plusieurs paliers d'un coup, et chaque frappe coûterait N calculs. */
    var barre = conteneur.querySelector('[data-psw-bar]');

    if (!barre) {
      barre = document.createElement('span');
      barre.setAttribute('data-psw-bar', '');
      conteneur.appendChild(barre);
    }

    conteneur.classList.add('password-strength-meter');
    barre.style.display = 'block';
    barre.style.height = (options.height || 5) + 'px';
    barre.style.borderRadius = (options.borderRadius === undefined ? 3 : options.borderRadius) + 'px';
    barre.style.width = '0';
    barre.style.transition = 'width .25s ease, background .25s ease';

    function peindre(points) {
      barre.style.width = points === 0 ? '0' : (points * 25) + '%';
      barre.style.background = couleurs[points] || 'transparent';

      if (etiquette) etiquette.textContent = messages[points] || '';

      // Les événements du contrat d'origine, conservés : un écran qui voudrait
      // bloquer son bouton d'envoi sous un certain palier écoute ceux-là.
      champ.dispatchEvent(new CustomEvent('onScore' + points, { bubbles: true }));
    }

    function evaluer() {
      peindre(score(champ.value, longueurMinimale));
    }

    /* `input` et non `keyup`. Un mot de passe COLLÉ, ou versé par un
       gestionnaire de mots de passe, n'émet aucun `keyup` : la barre serait
       restée vide devant un mot de passe excellent — et c'est précisément le
       cas qu'on veut encourager. */
    if (champ.dataset.pswMeterLie !== '1') {
      champ.addEventListener('input', evaluer);
      champ.dataset.pswMeterLie = '1';
    }

    evaluer();

    return { containerElement: conteneur, getScore: function () { return score(champ.value, longueurMinimale); } };
  };
})();
