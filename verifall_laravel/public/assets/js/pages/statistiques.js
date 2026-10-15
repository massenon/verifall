/*
 * « Mes Statistiques » — les trois canvas.
 *
 * Le dessin est celui du prototype. Ce qui change : les séries ne sont plus
 * lues dans un magasin local simulé, elles sont posées sur le canvas par le
 * serveur. Sur une page qui prétend mesurer une pratique, c'est la seule
 * source acceptable.
 */
(function () {
  'use strict';

  function serie(el, nom) {
    try {
      var brut = el.getAttribute('data-' + nom);
      return brut ? JSON.parse(brut) : [];
    } catch (e) { return []; }
  }

  function surface(canvas, hauteur) {
    var r = window.devicePixelRatio || 1;
    var w = canvas.parentElement.clientWidth;
    canvas.width = w * r;
    canvas.height = hauteur * r;
    canvas.style.width = w + 'px';
    canvas.style.height = hauteur + 'px';
    var ctx = canvas.getContext('2d');
    ctx.scale(r, r);
    return { ctx: ctx, w: w, h: hauteur };
  }

  /* ————————————————————————————— courbe d'activité */
  function courbe() {
    var cv = document.querySelector('[data-line-chart]');
    if (!cv) return;
    var jours = serie(cv, 'jours');
    var pts = serie(cv, 'valeurs').map(Number);
    var n = pts.length;
    if (!n) return;

    var g = surface(cv, 220), ctx = g.ctx;
    ctx.clearRect(0, 0, g.w, g.h);
    if (!pts.some(function (v) { return v > 0; })) { cv.classList.add('opacity-0'); return; }
    cv.classList.remove('opacity-0');

    /* +1 pour que le maximum ne colle pas au plafond du cadre. */
    var max = Math.max.apply(null, pts) + 1;
    var px = function (i) { return 8 + i * ((g.w - 16) / Math.max(1, n - 1)); };
    var py = function (v) { return g.h - 24 - (v / max) * (g.h - 44); };

    ctx.strokeStyle = '#E5E7EB';
    ctx.lineWidth = 1;
    [0.5, 1].forEach(function (f) {
      ctx.beginPath(); ctx.moveTo(0, py(max * f)); ctx.lineTo(g.w, py(max * f)); ctx.stroke();
    });

    var degrade = ctx.createLinearGradient(0, 0, 0, g.h);
    degrade.addColorStop(0, 'rgba(9,184,80,.25)');
    degrade.addColorStop(1, 'rgba(9,184,80,0)');
    ctx.beginPath(); ctx.moveTo(px(0), py(pts[0]));
    for (var j = 1; j < n; j++) ctx.lineTo(px(j), py(pts[j]));
    ctx.lineTo(px(n - 1), g.h - 24); ctx.lineTo(px(0), g.h - 24); ctx.closePath();
    ctx.fillStyle = degrade; ctx.fill();

    ctx.beginPath(); ctx.moveTo(px(0), py(pts[0]));
    for (var k = 1; k < n; k++) ctx.lineTo(px(k), py(pts[k]));
    ctx.strokeStyle = '#09B850'; ctx.lineWidth = 2.5; ctx.stroke();

    ctx.fillStyle = '#64748B'; ctx.font = '11px Inter, sans-serif'; ctx.textAlign = 'center';
    var reperes = n <= 7 ? [0, Math.floor(n / 3), Math.floor(2 * n / 3), n - 1]
      : [0, Math.round(n / 4), Math.round(n / 2), Math.round(3 * n / 4), n - 1];
    reperes.forEach(function (i) {
      if (jours[i]) ctx.fillText(jours[i], px(i), g.h - 6);
    });

    pts.forEach(function (v, i) {
      if (!v) return;
      ctx.beginPath(); ctx.arc(px(i), py(v), 3.5, 0, Math.PI * 2);
      ctx.fillStyle = '#09B850'; ctx.fill();
    });
  }

  /* ————————————————————————————————— anneau des verdicts */
  function anneau() {
    var cv = document.querySelector('[data-donut]');
    if (!cv) return;
    var vrai = +cv.getAttribute('data-vrai') || 0;
    var doute = +cv.getAttribute('data-doute') || 0;
    var faux = +cv.getAttribute('data-faux') || 0;

    var taille = Math.min(190, cv.parentElement.clientWidth || 190);
    var r = window.devicePixelRatio || 1;
    cv.width = taille * r; cv.height = taille * r;
    var ctx = cv.getContext('2d');
    ctx.scale(r, r);
    ctx.clearRect(0, 0, taille, taille);

    if (vrai + doute + faux === 0) { cv.classList.add('opacity-0'); return; }
    cv.classList.remove('opacity-0');

    var total = vrai + doute + faux;
    var parts = [[vrai, '#16A34A'], [doute, '#D6A419'], [faux, '#DC2626']];
    var mid = taille / 2, rayon = mid - 15, epaisseur = Math.max(18, taille * 0.137);
    var angle = -Math.PI / 2;

    parts.forEach(function (p) {
      var arc = (p[0] / total) * Math.PI * 2;
      if (arc <= 0) return;
      ctx.beginPath(); ctx.arc(mid, mid, rayon, angle, angle + arc);
      ctx.strokeStyle = p[1]; ctx.lineWidth = epaisseur; ctx.stroke();
      angle += arc;
    });
  }

  /* ——————————————————————————————— histogramme des types */
  function barres() {
    var cv = document.querySelector('[data-bars]');
    if (!cv) return;
    var types = serie(cv, 'types');
    var valeurs = serie(cv, 'valeurs').map(Number);
    if (!types.length) return;

    var g = surface(cv, 220), ctx = g.ctx;
    ctx.clearRect(0, 0, g.w, g.h);
    var max = Math.max.apply(null, valeurs);
    if (!max) { cv.classList.add('opacity-0'); return; }
    cv.classList.remove('opacity-0');

    /* Sous 560 px les colonnes verticales deviennent illisibles : on bascule
       en barres horizontales, comme le prototype. */
    if (g.w < 560) {
      var hauteurLigne = (g.h - 16) / types.length;
      types.forEach(function (t, i) {
        var y = 8 + i * hauteurLigne;
        ctx.fillStyle = '#64748B'; ctx.font = '11px Inter, sans-serif'; ctx.textAlign = 'left';
        ctx.fillText(t, 0, y + 12);
        var largeur = (valeurs[i] / max) * (g.w - 110);
        ctx.fillStyle = '#09B850';
        if (ctx.roundRect) { ctx.beginPath(); ctx.roundRect(58, y + 4, Math.max(largeur, 2), 12, 6); ctx.fill(); }
        else ctx.fillRect(58, y + 4, Math.max(largeur, 2), 12);
        ctx.fillStyle = '#111827'; ctx.font = 'bold 12px Inter, sans-serif';
        ctx.fillText(String(valeurs[i]), 58 + Math.max(largeur, 2) + 8, y + 14);
      });
      return;
    }

    var pas = (g.w - 40) / types.length;
    var largeurBarre = Math.min(64, pas - 18);
    types.forEach(function (t, i) {
      var x = 20 + i * pas + (pas - largeurBarre) / 2;
      var h = (valeurs[i] / max) * (g.h - 56);
      ctx.fillStyle = '#09B850';
      if (ctx.roundRect) { ctx.beginPath(); ctx.roundRect(x, g.h - 28 - h, largeurBarre, h, 6); ctx.fill(); }
      else ctx.fillRect(x, g.h - 28 - h, largeurBarre, h);
      ctx.fillStyle = '#111827'; ctx.font = 'bold 12px Inter, sans-serif'; ctx.textAlign = 'center';
      ctx.fillText(String(valeurs[i]), x + largeurBarre / 2, g.h - 34 - h);
      ctx.fillStyle = '#64748B'; ctx.font = '11px Inter, sans-serif';
      ctx.fillText(t, x + largeurBarre / 2, g.h - 8);
    });
  }

  function dessiner() { courbe(); anneau(); barres(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', dessiner);
  } else {
    dessiner();
  }

  window.addEventListener('resize', dessiner);
  document.addEventListener('livewire:navigated', dessiner);
  /* Changer de période remplace le canvas : il faut le redessiner. */
  document.addEventListener('livewire:update', dessiner);
})();
