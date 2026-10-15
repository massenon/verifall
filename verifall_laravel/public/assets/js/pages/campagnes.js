/* ------------------------------------------------------------------------
   Campagnes d'affichage.

   Trois comportements, et trois raisons.

   1. UNE FENÊTRE ÉCARTÉE NE REVIENT PAS. Le refus est mémorisé par slug. Une
      fenêtre qui réapparaît à chaque page cesse d'être lue : elle se ferme au
      réflexe, et elle abîme la confiance qu'on demande par ailleurs.

   2. RIEN NE S'OUVRE AVANT QUE LA PAGE SOIT LISIBLE. Une fenêtre imposée à la
      seconde du chargement est fermée sans avoir été regardée.

   3. ÉCHAP FERME, ET LE FOCUS REVIENT. Une fenêtre qu'on ne peut pas quitter
      au clavier est un piège.

   Sans dépendance : le prototype n'en a aucune, et une bannière n'est pas une
   raison d'en introduire une.
   ------------------------------------------------------------------------ */
(function () {
  'use strict';

  var CLEF = 'vf.campagnes.ecartees';

  /* `localStorage` peut lever : navigation privée sur certains navigateurs,
     quota plein, stockage désactivé. Une campagne ne doit JAMAIS casser la
     page qui l'accueille — on se rabat sur « rien n'a été écarté ». */
  function ecartees() {
    try {
      return JSON.parse(window.localStorage.getItem(CLEF) || '[]');
    } catch (e) {
      return [];
    }
  }

  function ecarter(slug) {
    try {
      var liste = ecartees();
      if (liste.indexOf(slug) === -1) liste.push(slug);
      window.localStorage.setItem(CLEF, JSON.stringify(liste));
    } catch (e) { /* Sans mémoire, la campagne reparaîtra. Tant pis. */ }
  }

  function dejaEcartee(slug) {
    return ecartees().indexOf(slug) !== -1;
  }

  /* --- fermeture ------------------------------------------------------- */

  var focusPrecedent = null;

  function fermer(element, slug) {
    ecarter(slug);
    element.removeAttribute('data-ouverte');

    /* On attend la fin de la transition avant de retirer l'élément du flux,
       sinon il disparaît d'un coup au lieu de s'estomper. */
    window.setTimeout(function () { element.hidden = true; }, 240);

    if (focusPrecedent && typeof focusPrecedent.focus === 'function') {
      focusPrecedent.focus();
      focusPrecedent = null;
    }

    /* Fermer la barre doit RETIRER le décalage. Sans cela, l'en-tête et le
       contenu resteraient poussés vers le bas d'une barre qui n'existe plus —
       un vide inexpliqué en haut de page. */
    if (element.classList.contains('vf-campaign-banner')) {
      window.setTimeout(mesurerLaBarre, 250);
    }
  }

  document.addEventListener('click', function (evenement) {
    var bouton = evenement.target.closest('[data-campagne-fermer]');
    if (!bouton) return;

    var slug = bouton.getAttribute('data-campagne-fermer');
    var element = document.querySelector('[data-campagne="' + slug + '"]');
    if (element) fermer(element, slug);
  });

  /* Cliquer le fond ferme — mais SEULEMENT le fond. Sans ce test, un clic qui
     démarre dans la fenêtre et se relâche dehors la refermerait. */
  document.addEventListener('click', function (evenement) {
    if (!evenement.target.classList.contains('vf-campaign-overlay')) return;
    var slug = evenement.target.getAttribute('data-campagne');
    if (slug) fermer(evenement.target, slug);
  });

  document.addEventListener('keydown', function (evenement) {
    if (evenement.key !== 'Escape') return;
    var ouverte = document.querySelector('.vf-campaign-overlay[data-ouverte]');
    if (ouverte) fermer(ouverte, ouverte.getAttribute('data-campagne'));
  });

  /* --- ouverture ------------------------------------------------------- */

  function ouvrir(element) {
    element.hidden = false;
    focusPrecedent = document.activeElement;

    /* Deux images successives : `hidden` retiré, puis l'attribut qui déclenche
       la transition. Sans ce délai, le navigateur applique les deux d'un coup
       et la fenêtre apparaît sans animation. */
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        element.setAttribute('data-ouverte', '');
        var premier = element.querySelector('a, button');
        if (premier) premier.focus();
      });
    });
  }

  /*
   | La hauteur de la barre, MESURÉE et publiée en variable CSS.
   |
   | L'en-tête du prototype est en `position: absolute; top: 0`. Une barre
   | au-dessus de lui doit donc le décaler, ainsi que le contenu — et de sa
   | hauteur RÉELLE, pas d'une valeur écrite en dur : dès que le texte passe à
   | la ligne, une constante serait fausse. C'est le cas sur tout téléphone.
   */
  function mesurerLaBarre() {
    var barre = document.querySelector('.vf-campaign-banner:not([hidden])');

    if (!barre) {
      document.documentElement.classList.remove('vf-has-topbar');
      document.documentElement.style.removeProperty('--vf-topbar-h');

      return;
    }

    var hauteur = Math.ceil(barre.getBoundingClientRect().height);

    document.documentElement.style.setProperty('--vf-topbar-h', hauteur + 'px');
    document.documentElement.classList.add('vf-has-topbar');
  }

  /* Au redimensionnement : passer de paysage à portrait fait passer le texte à
     la ligne, donc change la hauteur. Sans cette écoute, l'en-tête resterait
     décalé de l'ancienne valeur et chevaucherait la barre. */
  var minuterie = null;
  window.addEventListener('resize', function () {
    window.clearTimeout(minuterie);
    minuterie = window.setTimeout(mesurerLaBarre, 120);
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.vf-campaign-banner[data-campagne]').forEach(function (bandeau) {
      if (!dejaEcartee(bandeau.getAttribute('data-campagne'))) bandeau.hidden = false;
    });

    mesurerLaBarre();

    document.querySelectorAll('.vf-campaign-overlay[data-campagne]').forEach(function (fenetre) {
      var slug = fenetre.getAttribute('data-campagne');
      if (dejaEcartee(slug)) return;

      var delai = parseInt(fenetre.getAttribute('data-campagne-delai') || '8', 10) * 1000;
      window.setTimeout(function () { ouvrir(fenetre); }, delai);
    });
  });
})();
