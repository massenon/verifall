/*
 | Console premium : la coquille (arbitrage n° 176).
 | Tiroir mobile, groupes de navigation mémorisés, menus déroulants, palette
 | Ctrl K, Actions rapides (touche A), thème et plein écran.
 | Aucune dépendance ; Livewire n'y touche pas (la coquille n'est pas morphée).
 */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var lire = function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } };
  var ecrire = function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* préférence locale seulement */ } };
  var champSaisi = function () { var a = document.activeElement; return a && (/input|textarea|select/i.test(a.tagName) || a.isContentEditable); };

  /* ---------- Colonne : tiroir sur téléphone ---------- */
  var colonne = $('#colonne'), voile = $('[data-colonne-fermer]'), ouvrirCol = $('[data-colonne-ouvrir]');
  var fermerColonne = function () {
    if (!colonne) return;
    colonne.classList.add('-translate-x-full');
    if (voile) voile.classList.add('hidden');
    if (ouvrirCol) ouvrirCol.setAttribute('aria-expanded', 'false');
  };
  if (ouvrirCol) ouvrirCol.addEventListener('click', function () {
    colonne.classList.remove('-translate-x-full');
    voile.classList.remove('hidden');
    ouvrirCol.setAttribute('aria-expanded', 'true');
    var lien = $('a', colonne); if (lien) lien.focus();
  });
  if (voile) voile.addEventListener('click', fermerColonne);

  /* ---------- Groupes : l'état replié se mémorise ---------- */
  var replies = [];
  try { replies = JSON.parse(lire('vf-groupes-replies') || '[]'); } catch (e) { replies = []; }
  $$('details.groupe-nav').forEach(function (d) {
    var cle = d.getAttribute('data-groupe');
    if (!d.hasAttribute('data-courant')) d.open = replies.indexOf(cle) === -1;
    d.addEventListener('toggle', function () {
      replies = replies.filter(function (x) { return x !== cle; });
      if (!d.open) replies.push(cle);
      ecrire('vf-groupes-replies', JSON.stringify(replies));
    });
  });
  var actif = $('#colonne [aria-current="page"]');
  if (actif) actif.scrollIntoView({ block: 'nearest' });

  /* ---------- Menus déroulants (profil, actions rapides) ---------- */
  var fermerMenus = function (sauf) {
    $$('[data-menu-console]').forEach(function (m) {
      if (m === sauf) return;
      $('[data-menu-panneau]', m).classList.add('hidden');
      $('[data-menu-bouton]', m).setAttribute('aria-expanded', 'false');
    });
  };
  var ouvrirMenu = function (racine) {
    var panneau = $('[data-menu-panneau]', racine);
    fermerMenus(racine);
    panneau.classList.remove('hidden');
    $('[data-menu-bouton]', racine).setAttribute('aria-expanded', 'true');
    var premier = $('[role="menuitem"]', panneau); if (premier) premier.focus();
  };
  $$('[data-menu-console]').forEach(function (racine) {
    var bouton = $('[data-menu-bouton]', racine), panneau = $('[data-menu-panneau]', racine);
    bouton.addEventListener('click', function (e) {
      e.stopPropagation();
      panneau.classList.contains('hidden') ? ouvrirMenu(racine) : fermerMenus();
    });
    panneau.addEventListener('keydown', function (e) {
      var items = $$('[role="menuitem"]', panneau), i = items.indexOf(document.activeElement);
      if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); items[(i + 1) % items.length].focus(); }
      else if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); items[(i - 1 + items.length) % items.length].focus(); }
      else if (e.key === 'Home') { e.preventDefault(); items[0].focus(); }
      else if (e.key === 'End') { e.preventDefault(); items[items.length - 1].focus(); }
      else if (e.key === 'Escape') { e.stopPropagation(); fermerMenus(); bouton.focus(); }
      else if (e.key === 'Tab') fermerMenus();
    });
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('[data-menu-console]')) fermerMenus(); });

  /* ---------- Palette Ctrl K ---------- */
  var palette = $('#palette'), ecrans = [];
  try { ecrans = JSON.parse(($('#palette-ecrans') || {}).textContent || '[]'); } catch (e) { ecrans = []; }
  var sansAccent = function (t) { return t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
  var sel = 0, liste = [];
  /* Résultats de la recherche dans les données, demandés au serveur (350 ms
     après la dernière frappe). Le serveur ne rend que ce que le droit de la
     personne permet de lister. */
  var donnees = [], minuterieRecherche = null;
  var chercherDonnees = function (q) {
    clearTimeout(minuterieRecherche);
    var url = document.body.getAttribute('data-search-url');
    if (!url || !window.fetch || q.trim().length < 2) { if (donnees.length) { donnees = []; rendrePalette(q); } return; }
    minuterieRecherche = setTimeout(function () {
      fetch(url + '?q=' + encodeURIComponent(q.trim()), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : { resultats: [] }; })
        .then(function (j) { donnees = j.resultats || []; rendrePalette(q); })
        .catch(function () {});
    }, 350);
  };
  var rendrePalette = function (q) {
    var t = sansAccent(q);
    liste = ecrans.filter(function (e) { return sansAccent(e.libelle + ' ' + e.groupe).indexOf(t) !== -1; }).slice(0, 12).concat(donnees);
    sel = Math.min(sel, Math.max(0, liste.length - 1));
    var ul = $('[data-palette-liste]', palette);
    ul.innerHTML = '';
    if (!liste.length) {
      var vide = document.createElement('li');
      vide.className = 'px-4 py-6 text-sm texte-2 text-center';
      vide.textContent = 'Aucun écran ne correspond.';
      ul.appendChild(vide);
      return;
    }
    liste.forEach(function (e, i) {
      var li = document.createElement('li');
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', String(i === sel));
      var a = document.createElement('a');
      a.href = e.url;
      a.className = 'flex items-center gap-3 px-4 h-11 text-sm ' + (i === sel ? 'bg-brand-50 dark:bg-brand/10' : '');
      var l = document.createElement('span'); l.className = 'flex-1 truncate'; l.textContent = e.libelle;
      var g = document.createElement('span'); g.className = 'text-xs texte-3'; g.textContent = e.groupe;
      a.appendChild(l); a.appendChild(g); li.appendChild(a); ul.appendChild(li);
    });
  };
  var fermerPalette = function () { palette.classList.add('hidden'); palette.setAttribute('aria-hidden', 'true'); };
  var ouvrirPalette = function () {
    if (!palette) return;
    fermerMenus();
    palette.classList.remove('hidden'); palette.setAttribute('aria-hidden', 'false');
    var champ = $('[data-palette-champ]', palette);
    champ.value = ''; sel = 0; donnees = []; rendrePalette(''); champ.focus();
  };
  $$('[data-palette-ouvrir]').forEach(function (b) { b.addEventListener('click', ouvrirPalette); });
  if (palette) {
    $('[data-palette-fermer]', palette).addEventListener('click', fermerPalette);
    var champ = $('[data-palette-champ]', palette);
    champ.addEventListener('input', function () { sel = 0; rendrePalette(champ.value); chercherDonnees(champ.value); });
    champ.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, liste.length - 1); rendrePalette(champ.value); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); rendrePalette(champ.value); }
      else if (e.key === 'Enter' && liste[sel]) { window.location.href = liste[sel].url; }
      else if (e.key === 'Escape') fermerPalette();
    });
  }

  /* ---------- Thème : clair par défaut ---------- */
  $$('[data-theme-basculer]').forEach(function (b) {
    b.addEventListener('click', function () {
      var sombre = document.documentElement.classList.toggle('dark');
      ecrire('vf-theme', sombre ? 'dark' : 'light');
      /* Gardé aussi avec le compte, pour le retrouver ailleurs. Sans réponse
         attendue : un échec réseau ne gêne pas, le navigateur a déjà retenu. */
      var url = document.body.getAttribute('data-theme-url');
      var jeton = document.querySelector('meta[name="csrf-token"]');
      if (url && jeton && window.fetch) {
        fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': jeton.content }, body: JSON.stringify({ theme: sombre ? 'dark' : 'light' }) }).catch(function () {});
      }
      fermerMenus();
    });
  });

  /* ---------- Plein écran ---------- */
  var plein = $('[data-plein-ecran]');
  if (plein) {
    if (!(document.fullscreenEnabled || document.webkitFullscreenEnabled)) plein.remove();
    else {
      plein.addEventListener('click', function () {
        var r = document.documentElement;
        var actifPlein = document.fullscreenElement || document.webkitFullscreenElement;
        var p = actifPlein ? (document.exitFullscreen || document.webkitExitFullscreen).call(document) : (r.requestFullscreen || r.webkitRequestFullscreen).call(r);
        if (p && p.catch) p.catch(function () { /* refusé par le navigateur */ });
      });
      document.addEventListener('fullscreenchange', function () {
        var a = !!document.fullscreenElement;
        plein.setAttribute('aria-pressed', String(a));
        plein.setAttribute('aria-label', a ? 'Quitter le plein écran' : 'Plein écran');
      });
    }
  }

  /* ---------- Verrouillage de session (arbitrage n° 180) ----------
     Le verrou est SERVEUR : ce script ne fait qu'envoyer le formulaire. */
  var formVerrou = $('#form-verrou');
  var verrouiller = function (raison) {
    if (!formVerrou) return;
    formVerrou.querySelector('[name="raison"]').value = raison;
    formVerrou.submit();
  };
  $$('[data-verrouiller]').forEach(function (b) { b.addEventListener('click', function () { verrouiller('manuel'); }); });
  var DELAI = 15 * 60 * 1000, minuteur;
  var relancer = function () { clearTimeout(minuteur); minuteur = setTimeout(function () { verrouiller('inactivite'); }, DELAI); };
  ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(function (ev) { document.addEventListener(ev, relancer, { passive: true }); });
  if (formVerrou) relancer();

  /* ---------- Raccourcis ---------- */
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); ouvrirPalette(); return; }
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 'l') { e.preventDefault(); verrouiller('manuel'); return; }
    if (e.key === 'Escape') { fermerMenus(); fermerColonne(); if (palette && !palette.classList.contains('hidden')) fermerPalette(); return; }
    if (e.key.toLowerCase() === 'a' && !e.ctrlKey && !e.metaKey && !e.altKey && !champSaisi()) {
      var actions = $('[data-actions-rapides]');
      if (actions) { e.preventDefault(); ouvrirMenu(actions.closest('[data-menu-console]')); }
    }
  });
})();
