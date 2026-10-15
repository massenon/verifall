/*
 * Tableau de bord citoyen — graphique d'activité et modales d'arrivée.
 *
 * Le dessin est celui du prototype (barres empilées, canvas rétina, grille à
 * trois lignes). Ce qui change : les séries ne sont plus fabriquées par une
 * fonction pseudo-aléatoire, elles sont lues sur le canvas, où le serveur les a
 * posées. Un graphique inventé sur un tableau de bord de vérification serait
 * exactement ce que la plateforme combat.
 */
(function () {
  'use strict';

  function lireSerie(canvas, nom) {
    try {
      var brut = canvas.getAttribute('data-' + nom);
      var valeurs = brut ? JSON.parse(brut) : [];
      return Array.isArray(valeurs) ? valeurs.map(Number) : [];
    } catch (e) {
      return [];
    }
  }

  function dessiner(canvas) {
    var jours = lireSerie(canvas, 'jours');
    var verifs = lireSerie(canvas, 'verifications');
    var lecons = lireSerie(canvas, 'lecons');
    var n = jours.length;
    if (!n) return;

    var dpr = window.devicePixelRatio || 1;
    var w = canvas.parentElement.clientWidth;
    var h = 180;
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';

    var ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, w, h);

    /* Plancher à 1 : sans lui, un mois sans activité diviserait par zéro. */
    var max = 1;
    for (var i = 0; i < n; i++) max = Math.max(max, verifs[i] + lecons[i]);

    var pad = 4, gap = 3;
    var bw = (w - pad * 2 - gap * (n - 1)) / n;

    ctx.strokeStyle = '#E5E7EB';
    ctx.lineWidth = 1;
    [0.25, 0.5, 0.75].forEach(function (f) {
      ctx.beginPath();
      ctx.moveTo(0, h - 22 - (h - 30) * f);
      ctx.lineTo(w, h - 22 - (h - 30) * f);
      ctx.stroke();
    });

    for (var j = 0; j < n; j++) {
      var x = pad + j * (bw + gap);
      var hv = ((h - 30) * verifs[j]) / max;
      var hl = ((h - 30) * lecons[j]) / max;
      ctx.fillStyle = '#09B850';
      ctx.fillRect(x, h - 22 - hv, bw, hv);
      ctx.fillStyle = '#2563EB';
      ctx.fillRect(x, h - 22 - hv - hl, bw, hl);
      if (j % 5 === 0 || j === n - 1) {
        ctx.fillStyle = '#94A3B8';
        ctx.font = '10px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(String(jours[j]), x + bw / 2, h - 8);
      }
    }
  }

  function activer() {
    var canvas = document.querySelector('[data-activity-chart]');
    if (canvas) {
      dessiner(canvas);
      window.addEventListener('resize', function () { dessiner(canvas); });
    }

    /* Les modales d'arrivée s'ouvrent après un temps de latence, comme dans le
       prototype : surgir avant que la page soit lue passe pour une erreur. */
    document.querySelectorAll('[data-ouvrir-apres]').forEach(function (modale) {
      var delai = parseInt(modale.getAttribute('data-ouvrir-apres'), 10) || 0;
      setTimeout(function () {
        if (window.VF && typeof window.VF.openModal === 'function') {
          window.VF.openModal(modale.id);
        }
      }, delai);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', activer);
  } else {
    activer();
  }

  /* Le composant ferme la modale après « Me rappeler plus tard ». */
  window.addEventListener('fermer-modale', function (e) {
    var id = e.detail && e.detail.id;
    if (id && window.VF && typeof window.VF.closeModal === 'function') {
      window.VF.closeModal(id);
    }
  });

  /* Livewire remplace le DOM après une action : le canvas doit se redessiner. */
  document.addEventListener('livewire:navigated', activer);
})();
