/*
 * Barre supérieure du back-office — comportements (refonte du 14/09/2026).
 *
 * Trois choses, et rien qui duplique `citizen.js` (qui garde l'ouverture du
 * tiroir et des menus déroulants) :
 *
 *   1. ALLER À UN ÉCRAN. Le champ de recherche existait et ne faisait rien :
 *      aucun script ne l'écoutait. Une recherche « globale » d'utilisateurs ou
 *      de cours n'existe pas côté serveur ; plutôt que de promettre ce qui n'est
 *      pas là, le champ cherche parmi les écrans du menu latéral — ceux que
 *      cette personne a le droit d'ouvrir, puisque le menu est déjà filtré par
 *      le serveur.
 *   2. REPLI AU DÉFILEMENT (mobile). La seconde ligne — la recherche — se replie
 *      quand on descend, revient quand on remonte.
 *   3. TIROIR. Icône « fermer » quand il est ouvert, page figée derrière, focus
 *      posé dans le menu puis rendu au bouton.
 *
 * Aucune donnée n'est injectée en HTML : les libellés passent par textContent.
 */
(function () {
  'use strict';

  var barre = document.querySelector('[data-topbar]');
  if (!barre) { return; }

  /* ───────────────────────────── 1. Aller à un écran ───────────────────── */

  function normaliser(texte) {
    return (texte || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  var champ = document.getElementById('st-search');
  var liste = document.getElementById('st-search-resultats');

  if (champ && liste) {
    var index = Array.prototype.map.call(
      document.querySelectorAll('#sidebar a.sidebar-link[href]'),
      function (lien) {
        var groupe = lien.closest('details');
        var titreGroupe = groupe ? groupe.querySelector('summary span') : null;
        var libelle = (lien.textContent || '').trim();
        var nomGroupe = titreGroupe ? titreGroupe.textContent.trim() : '';
        return {
          libelle: libelle,
          groupe: nomGroupe,
          href: lien.getAttribute('href'),
          cle: normaliser(libelle + ' ' + nomGroupe)
        };
      }
    );

    var resultats = [];
    var actif = -1;

    function fermer() {
      liste.hidden = true;
      champ.setAttribute('aria-expanded', 'false');
      champ.removeAttribute('aria-activedescendant');
      actif = -1;
    }

    function surligner(rang) {
      var options = liste.querySelectorAll('[role="option"]');
      actif = rang;
      Array.prototype.forEach.call(options, function (option, i) {
        option.setAttribute('aria-selected', i === rang ? 'true' : 'false');
        if (i === rang) {
          champ.setAttribute('aria-activedescendant', option.id);
          option.scrollIntoView({ block: 'nearest' });
        }
      });
    }

    function afficher() {
      var requete = normaliser(champ.value);
      liste.textContent = '';

      if (requete === '') { fermer(); return; }

      var mots = requete.split(' ');
      resultats = index.filter(function (entree) {
        return mots.every(function (mot) { return entree.cle.indexOf(mot) !== -1; });
      }).slice(0, 8);

      if (resultats.length === 0) {
        var vide = document.createElement('li');
        vide.className = 'st-topbar-resultat is-vide';
        vide.setAttribute('role', 'presentation');
        vide.textContent = 'Aucun écran ne correspond.';
        liste.appendChild(vide);
      }

      resultats.forEach(function (entree, i) {
        var option = document.createElement('li');
        option.className = 'st-topbar-resultat';
        option.id = 'st-search-option-' + i;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');

        if (entree.groupe) {
          var groupe = document.createElement('small');
          groupe.textContent = entree.groupe;
          option.appendChild(groupe);
        }

        var libelle = document.createElement('span');
        libelle.textContent = entree.libelle;
        option.appendChild(libelle);

        option.addEventListener('mousedown', function (e) {
          e.preventDefault();
          window.location.assign(entree.href);
        });

        liste.appendChild(option);
      });

      liste.hidden = false;
      champ.setAttribute('aria-expanded', 'true');
      if (resultats.length > 0) { surligner(0); }
    }

    champ.addEventListener('input', afficher);
    champ.addEventListener('focus', function () { if (champ.value) { afficher(); } });
    champ.addEventListener('blur', function () { setTimeout(fermer, 120); });

    champ.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' && resultats.length) {
        e.preventDefault();
        surligner((actif + 1) % resultats.length);
      } else if (e.key === 'ArrowUp' && resultats.length) {
        e.preventDefault();
        surligner((actif - 1 + resultats.length) % resultats.length);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        var cible = resultats[actif] || resultats[0];
        if (cible) { window.location.assign(cible.href); }
      } else if (e.key === 'Escape') {
        champ.value = '';
        fermer();
      }
    });

    /* « / » donne le focus à la recherche, hors saisie. */
    document.addEventListener('keydown', function (e) {
      var saisie = e.target && (e.target.closest('input, textarea, select, [contenteditable="true"]'));
      if (e.key === '/' && !saisie && !e.ctrlKey && !e.metaKey && !e.altKey) {
        e.preventDefault();
        barre.classList.remove('is-compact');
        champ.focus();
      }
    });
  }

  /* ─────────────────────────────── 3. Tiroir ────────────────────────────── */

  var bouton = barre.querySelector('[data-drawer-open]');
  var icone = barre.querySelector('[data-topbar-menu-icone]');
  var tiroir = document.getElementById('sidebar');
  var tiroirOuvert = false;

  if (bouton && tiroir) {
    new MutationObserver(function () {
      var ouvert = bouton.getAttribute('aria-expanded') === 'true';
      if (ouvert === tiroirOuvert) { return; }
      tiroirOuvert = ouvert;

      if (icone) { icone.textContent = ouvert ? 'close' : 'menu'; }
      bouton.setAttribute('aria-label', ouvert ? 'Fermer le menu' : 'Ouvrir le menu');
      document.documentElement.classList.toggle('st-tiroir-ouvert', ouvert);

      if (ouvert) {
        barre.classList.remove('is-compact');
        var premier = tiroir.querySelector('a[href], button, summary');
        if (premier) { premier.focus({ preventScroll: true }); }
      } else if (tiroir.contains(document.activeElement)) {
        bouton.focus({ preventScroll: true });
      }
    }).observe(bouton, { attributes: true, attributeFilter: ['aria-expanded'] });

    /* Passé en disposition bureau, le tiroir n'a plus de sens : on libère la page. */
    window.matchMedia('(min-width: 1024px)').addEventListener('change', function (m) {
      if (m.matches) { document.documentElement.classList.remove('st-tiroir-ouvert'); }
    });
  }

  /* ─────────────────────────── 2. Repli au défilement ──────────────────── */

  var mobile = window.matchMedia('(max-width: 767px)');
  var dernier = window.scrollY;
  var enAttente = false;

  function menuOuvert() {
    return tiroirOuvert
      || !!barre.querySelector('[data-dropdown-toggle][aria-expanded="true"]')
      || (champ && document.activeElement === champ);
  }

  window.addEventListener('scroll', function () {
    if (enAttente) { return; }
    enAttente = true;

    window.requestAnimationFrame(function () {
      enAttente = false;
      var y = window.scrollY;

      if (!mobile.matches || menuOuvert()) {
        barre.classList.remove('is-compact');
      } else if (y > 80 && y > dernier + 4) {
        barre.classList.add('is-compact');
      } else if (y < dernier - 4 || y <= 80) {
        barre.classList.remove('is-compact');
      }

      dernier = y;
    });
  }, { passive: true });
})();
