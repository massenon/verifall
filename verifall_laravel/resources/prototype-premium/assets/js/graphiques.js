/*
 | Graphiques (ApexCharts) — une seule configuration pour toute la console.
 |
 | Palette catégorielle validée (skill dataviz, script validate_palette.js) sur
 | les surfaces réelles : #ffffff en clair, #13211a en sombre. Ordre fixe, jamais
 | recyclé ; la couleur suit l'entité, pas son rang.
 |
 | En clair, trois teintes sont sous 3:1 de contraste : chaque graphique a donc
 | une légende, des étiquettes ciblées et une vue « Tableau » équivalente.
 */
(function (VF) {
  'use strict';
  const { esc, fmt, icones } = VF.ui;

  const sombre = () => document.documentElement.classList.contains('dark');

  const PALETTE = {
    clair: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'],
    sombre: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181'],
  };
  /* Statuts réservés : un verdict, jamais une série ordinaire. */
  const STATUT = { bon: '#0ca30c', alerte: '#fab219', grave: '#ec835a', critique: '#d03b3b', neutre: '#8a978f' };
  /* Rampe séquentielle (bleu) pour les intensités : clair -> foncé. */
  const RAMPE = ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95'];

  const encre = () => (sombre() ? { texte: '#E8F0EB', second: '#B9C8BF', muet: '#7F9187', grille: '#22352B', surface: '#13211A' } :
    { texte: '#142019', second: '#4A5A51', muet: '#7A8981', grille: '#E3E8E4', surface: '#FFFFFF' });

  const couleurs = () => (sombre() ? PALETTE.sombre : PALETTE.clair);

  const base = (type, hauteur) => {
    const e = encre();
    return {
      chart: {
        type, height: hauteur, fontFamily: '"Public Sans", system-ui, sans-serif', foreColor: e.muet,
        toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent',
        animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches, speed: 380 },
        parentHeightOffset: 0,
      },
      theme: { mode: sombre() ? 'dark' : 'light' },
      colors: couleurs(),
      grid: { borderColor: e.grille, strokeDashArray: 0, padding: { left: 4, right: 28, top: -8 }, xaxis: { lines: { show: false } } },
      dataLabels: { enabled: false },
      /* Lignes : trait de 2 px. Barres et cases : un liseré couleur surface de 1 px de chaque côté
         (2 px entre deux marques) les sépare, jamais une bordure foncée. */
      stroke: type === 'bar' || type === 'heatmap' ? { width: 1, colors: [e.surface] } : { width: 2, curve: 'smooth', lineCap: 'round' },
      legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', labels: { colors: e.second }, markers: { size: 5, shape: 'circle' }, itemMargin: { horizontal: 10 } },
      tooltip: { theme: sombre() ? 'dark' : 'light', shared: true, intersect: false, style: { fontSize: '12px' } },
      xaxis: { axisBorder: { color: e.grille }, axisTicks: { show: false }, labels: { rotate: 0, hideOverlappingLabels: true, style: { colors: e.muet, fontSize: '11px' } }, crosshairs: { stroke: { color: e.muet, width: 1, dashArray: 0 } }, tooltip: { enabled: false } },
      yaxis: { labels: { style: { colors: e.muet, fontSize: '11px' }, formatter: (v) => (typeof v !== 'number' || isNaN(v) ? v : Math.abs(v) >= 1000 ? fmt.compact(v) : fmt.nombre(Math.round(v * 10) / 10)), maxWidth: 220 } },
      states: { hover: { filter: { type: 'darken', value: 0.9 } } },
      plotOptions: { bar: { borderRadius: 4, borderRadiusApplication: 'end', columnWidth: '48%', dataLabels: { position: 'top' } } },
      noData: { text: 'Aucune donnée sur cette période' },
    };
  };

  const fusion = (a, b) => {
    const sortie = { ...a };
    Object.keys(b || {}).forEach((k) => {
      sortie[k] = b[k] && typeof b[k] === 'object' && !Array.isArray(b[k]) && a[k] && typeof a[k] === 'object' ? fusion(a[k], b[k]) : b[k];
    });
    return sortie;
  };

  const actifs = [];

  /**
   * Une carte de graphique avec sa bascule Graphique / Tableau.
   * `tableau` : { colonnes: [...], lignes: [[...]] } — l'équivalent lisible
   * sans couleur, exigé pour l'accessibilité.
   */
  const carte = ({ id, titre, aide = '', hauteur = 280, actions = '', classe = '' }) => `
    <section class="carte ${classe}">
      <div class="carte-tete">
        <div><h2 class="carte-titre">${esc(titre)}</h2>${aide ? `<p class="text-xs texte-3 mt-0.5">${esc(aide)}</p>` : ''}</div>
        <div class="flex items-center gap-2">${actions}
          <div class="segments" role="group" aria-label="Affichage">
            <button class="segment" aria-pressed="true" data-vue="graphique" data-cible="${id}" aria-label="Graphique">${VF.ui.icone('chart-column', 'w-4 h-4')}</button>
            <button class="segment" aria-pressed="false" data-vue="tableau" data-cible="${id}" aria-label="Tableau">${VF.ui.icone('table-2', 'w-4 h-4')}</button>
          </div>
        </div>
      </div>
      <div class="carte-corps">
        <div id="${id}" style="min-height:${hauteur}px"></div>
        <div id="${id}-tableau" class="hidden overflow-auto" style="max-height:${hauteur + 40}px"></div>
      </div>
    </section>`;

  const tableauHtml = ({ colonnes, lignes }) => `<table class="tableau"><thead><tr>${colonnes.map((c) => `<th>${esc(c)}</th>`).join('')}</tr></thead><tbody>${lignes.map((l) => `<tr>${l.map((v) => `<td>${esc(v)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;

  const monter = (id, type, options, { hauteur = 280, tableau = null } = {}) => {
    const el = document.getElementById(id);
    if (!el || !window.ApexCharts) return null;
    /* Sur téléphone, moins de graduations : les dates ne se chevauchent pas. */
    if (window.innerWidth < 640 && options.xaxis && options.xaxis.tickAmount) options.xaxis.tickAmount = Math.min(options.xaxis.tickAmount, 4);
    const g = new ApexCharts(el, fusion(base(type, hauteur), options));
    g.render();
    actifs.push(g);
    if (tableau) {
      const t = document.getElementById(id + '-tableau');
      if (t) t.innerHTML = tableauHtml(tableau);
    }
    return g;
  };

  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-vue]');
    if (!b) return;
    const id = b.dataset.cible;
    const enTableau = b.dataset.vue === 'tableau';
    document.getElementById(id).classList.toggle('hidden', enTableau);
    document.getElementById(id + '-tableau').classList.toggle('hidden', !enTableau);
    b.parentElement.querySelectorAll('[data-vue]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
  });

  const detruireTout = () => { while (actifs.length) { try { actifs.pop().destroy(); } catch (e) { /* déjà détruit */ } } };

  /* Courbe miniature d'une tuile : sans axe, sans légende, une seule série. */
  const etincelle = (id, donnees, couleur) => monter(id, 'area', {
    chart: { sparkline: { enabled: true } }, colors: [couleur || couleurs()[0]],
    series: [{ name: 'Valeur', data: donnees }],
    fill: { type: 'gradient', gradient: { opacityFrom: 0.22, opacityTo: 0, stops: [0, 100] } },
    tooltip: { enabled: false },
  }, { hauteur: 44 });

  VF.g = { carte, monter, etincelle, detruireTout, couleurs, STATUT, RAMPE, encre, sombre, icones };
})(window.VF);
