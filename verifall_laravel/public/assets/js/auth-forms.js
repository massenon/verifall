/* ==========================================================================
   Formulaires d'authentification — œil « voir / cacher » et témoin de force
   ==========================================================================

   ## Le défaut que ce fichier corrige

   Le prototype attache ses comportements UNE FOIS, au `DOMContentLoaded` :

       document.addEventListener('DOMContentLoaded', function () {
         initPasswordToggles();      // verifall.js
       });

   C'est juste pour une page statique, et faux pour un écran Livewire. Trois
   comportements en mouraient, tous pour la même raison :

     - **L'œil de l'inscription n'a JAMAIS fonctionné.** Le champ vit dans
       `@if ($etape === 2)` ; au chargement, l'écran affiche l'étape 1, et
       `document.getElementById('reg-password')` ne trouve rien. L'écouteur
       n'était donc jamais posé sur un élément qui apparaîtrait plus tard.
     - **Le témoin de force non plus**, et pour exactement la même raison — il
       visait de surcroît `#reg-password2`, l'identifiant du prototype, quand la
       transposition Livewire nomme le champ `#reg-password-confirm`.
     - **L'œil de la connexion se désynchronisait.** Le champ existe au
       chargement, donc le premier clic marchait ; mais le morph de Livewire
       recompare le HTML du serveur (`type="password"`) au DOM vivant
       (`type="text"`) et remet le masque, en laissant `aria-pressed="true"`.
       Après une simple validation de champ, l'icône disait « visible » devant
       un mot de passe masqué.

   ## Les trois réponses

     1. **Délégation** sur `document` : un bouton créé à l'étape 2, dix minutes
        après le chargement, est pris en charge sans qu'on ait rien à réamorcer.
     2. **Ré-application après chaque morph** : l'état vit dans l'attribut
        `aria-pressed` du bouton, qui est la source de vérité, et le `type` du
        champ est réaligné dessus après chaque mise à jour de composant.
     3. **Attribut distinct** (`data-pwd-eye`, pas `data-pwd-toggle`) : le
        gestionnaire du prototype reste en place pour les pages statiques et
        n'attrape jamais nos boutons. Deux écouteurs sur le même bouton, ce
        serait deux bascules par clic — donc aucun effet visible, le défaut le
        plus difficile à diagnostiquer de la famille.
   ========================================================================== */

(function () {
  'use strict';

  var SELECTEUR_OEIL = '[data-pwd-eye]';

  function champDe(bouton) {
    return document.getElementById(bouton.getAttribute('data-pwd-eye'));
  }

  /** Aligne le champ sur ce que le bouton annonce. */
  function appliquer(bouton) {
    var champ = champDe(bouton);
    if (!champ) return;

    var visible = bouton.getAttribute('aria-pressed') === 'true';

    champ.type = visible ? 'text' : 'password';
    bouton.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');

    // Les deux icônes sont dans le bouton ; on montre celle qui correspond.
    var oeil = bouton.querySelector('[data-pwd-icon="voir"]');
    var barre = bouton.querySelector('[data-pwd-icon="cacher"]');
    if (oeil) oeil.hidden = visible;
    if (barre) barre.hidden = !visible;
  }

  function toutAppliquer(racine) {
    (racine || document).querySelectorAll(SELECTEUR_OEIL).forEach(appliquer);
  }

  /* Délégation : un seul écouteur, pour tous les boutons présents ou à venir. */
  document.addEventListener('click', function (evenement) {
    var bouton = evenement.target.closest ? evenement.target.closest(SELECTEUR_OEIL) : null;
    if (!bouton) return;

    evenement.preventDefault();
    bouton.setAttribute('aria-pressed', bouton.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
    appliquer(bouton);
  });

  /* ————— Le témoin de force, sur l'inscription ————— */

  function amorcerLeTemoin() {
    if (typeof window.passwordStrengthMeter !== 'function') return;

    // La fonction est idempotente : la rappeler sur un conteneur déjà équipé
    // recycle la barre et n'ajoute pas d'écouteur. C'est ce qui permet de
    // l'appeler sans condition après chaque rendu.
    window.passwordStrengthMeter({
      containerElement: '#psw-meter',
      passwordInput: '#reg-password',
      messageContainer: '#psw-meter-message',
      pswMinLength: 8
    });
  }

  function tout() {
    toutAppliquer();
    amorcerLeTemoin();
  }

  document.addEventListener('DOMContentLoaded', tout);

  // `wire:navigate` ne rejoue pas `DOMContentLoaded`.
  document.addEventListener('livewire:navigated', tout);

  /*
   | Après CHAQUE mise à jour d'un composant. C'est ce crochet qui répare la
   | désynchronisation : le morph vient de réécrire `type="password"` par-dessus
   | un champ que la personne avait choisi de voir.
   */
  document.addEventListener('livewire:init', function () {
    window.Livewire.hook('morph.updated', function (donnees) {
      // La signature a changé entre les versions de Livewire ; on prend l'élément
      // s'il est fourni, et on repart du document sinon.
      var el = donnees && donnees.el ? donnees.el : null;
      toutAppliquer(el && el.querySelectorAll ? el : document);
      amorcerLeTemoin();
    });
  });
})();
