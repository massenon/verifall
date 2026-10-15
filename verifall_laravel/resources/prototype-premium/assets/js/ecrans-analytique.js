/*
 | Tableau de bord et écrans d'analyse.
 |
 | Règles de visualisation appliquées (skill dataviz) : un seul axe par
 | graphique, couleur qui suit l'entité, verdicts en couleurs de statut
 | toujours doublées d'un mot, une vue Tableau pour chaque graphique, et un
 | filtre de période unique au-dessus de tout ce qu'il pilote.
 */
(function (VF) {
  'use strict';
  const { $, $$, esc, fmt, icone, icones, pastille, kpi, enTete, carte, toast } = VF.ui;
  const G = VF.g;
  VF.ecrans = VF.ecrans || {};

  const PERIODES = [[7, '7 jours'], [30, '30 jours'], [90, '90 jours']];
  const filtrePeriode = (actuelle) => `<div class="segments" role="group" aria-label="Période">${PERIODES.map(([j, l]) => `<button class="segment" data-periode="${j}" aria-pressed="${j === actuelle}">${l}</button>`).join('')}</div>`;
  const etendre = (serie, jours) => {
    if (jours <= serie.length) return serie.slice(-jours);
    const avant = Array.from({ length: jours - serie.length }, (_, i) => Math.round(serie[i % serie.length] * (0.72 + (i / jours) * 0.25)));
    return avant.concat(serie);
  };
  const etiquettesJours = (jours) => Array.from({ length: jours }, (_, i) => { const d = new Date(2026, 9, 15); d.setDate(d.getDate() - (jours - 1 - i)); return fmt.jourCourt(d); });
  const brancherPeriode = (racine, rendre) => $$('[data-periode]', racine).forEach((b) => b.addEventListener('click', () => rendre(+b.dataset.periode)));

  /* ======================= Tableau de bord admin ======================= */

  /* Le bureau de vérification : l'élément qu'on retient de la console.
     Ce qui brûle passe devant, avec le temps qu'il reste avant l'objectif. */
  const bureau = () => {
    const file = VF.signalements.filter((s) => s.etat !== 'publie').sort((a, b) => a.echeance - b.echeance).slice(0, 5);
    return `
    <section class="carte overflow-hidden lg:col-span-2">
      <div class="px-5 pt-5 pb-4 bg-foret text-white">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="font-titre text-xl font-semibold">Bureau de vérification</h2>
            <p class="text-sm text-white/65 mt-0.5">Ce qui circule maintenant, trié par le temps qu'il reste avant l'objectif de 6 h.</p>
          </div>
          <a href="#/signalements" class="btn bg-white/10 text-white hover:bg-white/15">${icone('list')}Tous les signalements</a>
        </div>
        <dl class="mt-5 grid grid-cols-3 gap-4 max-w-lg">
          <div><dt class="text-xs text-white/55">En file</dt><dd class="mt-1 text-2xl font-semibold">27</dd></div>
          <div><dt class="text-xs text-white/55">Hors délai</dt><dd class="mt-1 text-2xl font-semibold">2</dd></div>
          <div><dt class="text-xs text-white/55">Analystes en ligne</dt><dd class="mt-1 text-2xl font-semibold">6</dd></div>
        </dl>
      </div>
      <ul class="divide-y divide-trait dark:divide-nuit-trait">
        ${file.map((s) => {
          const urgent = s.echeance < 90;
          return `<li class="flex items-center gap-4 px-5 py-3.5">
            <div class="w-[74px] shrink-0">
              <p class="text-[13px] font-semibold tabular-nums ${urgent ? 'text-red-700 dark:text-red-400' : ''}">${fmt.duree(s.echeance)}</p>
              <p class="text-[11px] texte-3">${urgent ? 'avant échéance' : 'restantes'}</p>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium truncate">« ${esc(s.texte)} »</p>
              <p class="text-xs texte-3 mt-0.5">${esc(s.id)} — ${esc(s.canal)}, ${esc(s.ville)} — viralité ${s.viralite}/100</p>
            </div>
            <span class="hidden md:inline-flex">${pastille(VF.statuts.signalement[s.etat].classe, VF.statuts.signalement[s.etat].ic, VF.statuts.signalement[s.etat].libelle)}</span>
            <button class="btn-second btn-sm hidden sm:inline-flex" data-ouvrir-sig="${esc(s.id)}">Analyser</button>
          </li>`;
        }).join('')}
      </ul>
    </section>`;
  };

  VF.ecrans['tableau-de-bord'] = (racine) => {
    const rendre = (jours) => {
      G.detruireTout();
      const sig = etendre(VF.series.signalements, jours), ver = etendre(VF.series.verifications, jours);
      const total = (a) => a.reduce((x, y) => x + y, 0);
      racine.innerHTML = `
        ${enTete({ titre: 'Bonjour Mawuli', sousTitre: 'Jeudi 15 octobre. 3 signalements viraux attendent une analyse, et un moteur est dégradé.', actions: filtrePeriode(jours) + `<a href="#/bi" class="btn-second">${icone('layout-dashboard')}Personnaliser</a>` })}
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
          ${kpi({ libelle: 'Signalements reçus', valeur: fmt.nombre(total(sig)), delta: 12.4, aide: `sur ${jours} jours`, ic: 'inbox' })}
          ${kpi({ libelle: 'Fact-checks publiés', valeur: fmt.nombre(total(ver)), delta: 9.1, aide: `sur ${jours} jours`, ic: 'file-check' })}
          ${kpi({ libelle: 'Précision des verdicts IA', valeur: '89,6 %', delta: 1.8, aide: 'confirmés par un analyste', ic: 'target' })}
          ${kpi({ libelle: 'Apprenants actifs', valeur: fmt.nombre(4182), delta: 11, aide: 'Académie', ic: 'graduation-cap' })}
        </div>
        <div class="grid lg:grid-cols-3 gap-4 mb-4">
          ${bureau()}
          ${carte({ titre: 'Verdicts publiés', aide: `Sur ${jours} jours`, corps: `<div id="g-verdicts-liste" class="space-y-3.5"></div>` })}
        </div>
        <div class="grid lg:grid-cols-3 gap-4 mb-4">
          ${G.carte({ id: 'g-flux', titre: 'Signalements et fact-checks', aide: 'Par jour', hauteur: 270, classe: 'lg:col-span-2' })}
          ${carte({ titre: 'État des moteurs', aide: '19 moteurs d\'analyse', actions: `<a href="#/analytique-ia" class="btn-fantome btn-sm">Détails</a>`, corps: `<ul class="space-y-3">${VF.moteurs.slice(0, 3).concat(VF.moteurs.filter((m) => m.etat !== 'ok')).map((m) => `<li class="flex items-center justify-between gap-3 text-sm"><span class="truncate">${esc(m.nom)}</span>${m.etat === 'ok' ? pastille('p-bon', 'circle-check', 'Normal') : m.etat === 'degrade' ? pastille('p-alerte', 'triangle-alert', `${String(m.erreurs).replace('.', ',')} % d'erreurs`) : pastille('p-grave', 'gauge', 'Quota atteint')}</li>`).join('')}<li class="pt-2 border-t filet text-xs texte-3">14 autres moteurs fonctionnent normalement.</li></ul>` })}
        </div>
        <div class="grid lg:grid-cols-3 gap-4">
          ${carte({ titre: 'Activité de l\'équipe', actions: `<a href="#/audit" class="btn-fantome btn-sm">Journal complet</a>`, classe: 'lg:col-span-2', corps: `<ul class="space-y-4">${VF.journal.map((j) => `<li class="flex gap-3"><span class="inline-flex w-8 h-8 shrink-0 items-center justify-center rounded-full bg-papier dark:bg-nuit texte-2">${icone(j.icone, 'w-4 h-4')}</span><div class="min-w-0 text-sm"><p><span class="font-medium">${esc(j.qui)}</span> ${esc(j.action)}</p><p class="texte-2 truncate">${esc(j.objet)}</p></div><span class="ml-auto text-xs texte-3 whitespace-nowrap">${esc(j.quand)}</span></li>`).join('')}</ul>` })}
          ${carte({ titre: 'À venir', actions: `<a href="#/calendrier" class="btn-fantome btn-sm">Calendrier</a>`, corps: `<ul class="space-y-3">${VF.evenements.filter((e) => new Date(e.start) >= new Date(2026, 9, 15)).sort((a, b) => new Date(a.start) - new Date(b.start)).slice(0, 5).map((e) => { const d = new Date(e.start); return `<li class="flex items-center gap-3"><div class="w-11 text-center shrink-0 rounded-ctrl border filet py-1"><p class="text-[11px] texte-3">${new Intl.DateTimeFormat('fr-FR', { month: 'short' }).format(d)}</p><p class="font-semibold leading-none">${d.getDate()}</p></div><div class="min-w-0"><p class="text-sm font-medium truncate">${esc(e.title)}</p><p class="text-xs texte-3">${e.allDay ? 'Toute la journée' : new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(d)}</p></div></li>`; }).join('')}</ul>` })}
        </div>`;

      /* Verdicts : peu de catégories, valeurs à comparer -> barres horizontales en
         HTML, couleur de statut + icône + mot + valeur. Pas de donut. */
      const repartition = [['faux', 46], ['trompeur', 27], ['vrai', 19], ['invérifiable', 8]];
      $('#g-verdicts-liste', racine).innerHTML = repartition.map(([k, p]) => {
        const v = VF.verdicts[k];
        const couleur = { faux: G.STATUT.critique, trompeur: G.STATUT.alerte, vrai: G.STATUT.bon, invérifiable: G.STATUT.neutre }[k];
        return `<div><div class="flex items-center justify-between text-sm mb-1.5"><span class="inline-flex items-center gap-1.5">${icone(v.icone, 'w-4 h-4 texte-2')}${v.libelle}</span><span class="font-medium tabular-nums">${p} %</span></div><div class="jauge !h-2"><span style="width:${p}%;background:${couleur}"></span></div></div>`;
      }).join('') + `<p class="pt-1 text-xs texte-3">${fmt.nombre(total(ver))} verdicts. La part de « Faux » baisse de 3 points sur la période.</p>`;

      const x = etiquettesJours(jours);
      G.monter('g-flux', 'area', {
        series: [{ name: 'Signalements reçus', data: sig }, { name: 'Fact-checks publiés', data: ver }],
        xaxis: { categories: x, tickAmount: Math.min(8, jours) },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.16, opacityTo: 0, stops: [0, 100] } },
        markers: { size: 0, hover: { size: 5 } },
      }, { hauteur: 270, tableau: { colonnes: ['Jour', 'Signalements', 'Fact-checks'], lignes: x.map((j, i) => [j, sig[i], ver[i]]) } });

      brancherPeriode(racine, rendre);
      $$('[data-ouvrir-sig]', racine).forEach((b) => b.addEventListener('click', () => { location.hash = '#/signalements'; }));
      icones();
    };
    rendre(30);
  };

  /* ======================= Analytique IA ======================= */

  VF.ecrans['analytique-ia'] = (racine) => {
    const rendre = (jours) => {
      G.detruireTout();
      const precision = etendre(VF.series.precision, jours), unites = etendre(VF.series.unites, jours);
      const moteurs = VF.moteurs.slice().sort((a, b) => b.appels - a.appels);
      racine.innerHTML = `
        ${enTete({ titre: 'Analytique de l\'IA de vérification', sousTitre: 'Performance des 19 moteurs, justesse des verdicts et consommation d\'unités.', fil: ['Analytique'], actions: filtrePeriode(jours) })}
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
          ${kpi({ libelle: 'Verdicts IA confirmés', valeur: '89,6 %', delta: 1.8, aide: 'par la relecture humaine', ic: 'target' })}
          ${kpi({ libelle: 'Latence médiane', valeur: '1,9 s', delta: -6, sens: 'bas-bon', aide: 'par analyse complète', ic: 'timer' })}
          ${kpi({ libelle: 'Appels aux moteurs', valeur: fmt.compact(moteurs.reduce((s, m) => s + m.appels, 0)), delta: 14, ic: 'cpu' })}
          ${kpi({ libelle: 'Taux d\'erreur', valeur: '1,4 %', delta: 0.3, sens: 'bas-bon', aide: 'tous moteurs', ic: 'triangle-alert' })}
        </div>
        <div class="grid lg:grid-cols-2 gap-4 mb-4">
          ${G.carte({ id: 'g-precision', titre: 'Verdicts IA confirmés par un analyste', aide: 'Part quotidienne, en %', hauteur: 250 })}
          ${G.carte({ id: 'g-unites-moteur', titre: 'Unités consommées par moteur', aide: 'Les 8 plus gros consommateurs', hauteur: 250 })}
        </div>
        <div class="grid lg:grid-cols-3 gap-4 mb-4">
          ${G.carte({ id: 'g-confusion', titre: 'Verdict proposé par l\'IA et verdict publié', aide: 'Nombre de fact-checks — la diagonale est l\'accord', hauteur: 260, classe: 'lg:col-span-2' })}
          ${carte({ titre: 'Journal d\'usage', aide: 'En direct', corps: `<ul class="space-y-3 text-sm">${[['Gemini Flash-Lite', 'Extraction des affirmations', '0,8 s', 'ok'], ['TinEye', 'Image inverse — 14 correspondances', '2,1 s', 'ok'], ['SauceNAO', 'Expiration après 8 s', '8,0 s', 'erreur'], ['Google Fact Check', '2 verdicts existants trouvés', '0,6 s', 'ok'], ['Détection de deepfake', 'Quota du jour atteint', '—', 'quota'], ['Wayback Machine', 'Première capture : 2019', '1,4 s', 'ok']].map(([m, d, t, e]) => `<li class="flex items-start gap-2.5">${e === 'ok' ? icone('circle-check', 'w-4 h-4 mt-0.5 text-green-700 dark:text-bon') : e === 'erreur' ? icone('circle-x', 'w-4 h-4 mt-0.5 text-red-700 dark:text-red-400') : icone('gauge', 'w-4 h-4 mt-0.5 text-orange-700 dark:text-orange-300')}<div class="min-w-0 flex-1"><p class="font-medium">${m}</p><p class="text-xs texte-2">${d}</p></div><span class="text-xs texte-3 tabular-nums">${t}</span></li>`).join('')}</ul>` })}
        </div>
        ${carte({ titre: 'Les 19 moteurs', aide: 'Triés par volume d\'appels', corps: `<div class="overflow-x-auto -mx-5"><table class="tableau"><thead><tr><th>Moteur</th><th>Type</th><th>Précision</th><th>Latence médiane</th><th>Appels</th><th>Erreurs</th><th>État</th></tr></thead><tbody>${moteurs.map((m) => `<tr><td class="font-medium">${esc(m.nom)}</td><td class="texte-2">${esc(m.type)}</td><td><div class="flex items-center gap-2 min-w-[110px]"><div class="jauge"><span style="width:${m.precision}%"></span></div><span class="text-xs w-11 text-right whitespace-nowrap">${m.precision} %</span></div></td><td>${(m.latence / 1000).toFixed(1).replace('.', ',')} s</td><td>${fmt.nombre(m.appels)}</td><td>${String(m.erreurs).replace('.', ',')} %</td><td>${m.etat === 'ok' ? pastille('p-bon', 'circle-check', 'Normal') : m.etat === 'degrade' ? pastille('p-alerte', 'triangle-alert', 'Dégradé') : pastille('p-grave', 'gauge', 'Quota')}</td></tr>`).join('')}</tbody></table></div>` })}`;

      const x = etiquettesJours(jours);
      G.monter('g-precision', 'line', {
        series: [{ name: 'Confirmés', data: precision }], colors: [G.couleurs()[0]],
        xaxis: { categories: x, tickAmount: Math.min(8, jours) }, yaxis: { min: 80, max: 100, tickAmount: 4, labels: { formatter: (v) => v + ' %' } },
        annotations: { yaxis: [{ y: 90, borderColor: G.encre().muet, label: { text: 'Objectif 90 %', style: { background: 'transparent', color: G.encre().second, fontSize: '11px' }, borderWidth: 0 } }] },
        legend: { show: false },
      }, { hauteur: 250, tableau: { colonnes: ['Jour', 'Confirmés (%)'], lignes: x.map((j, i) => [j, precision[i]]) } });

      const top = VF.moteurs.slice().sort((a, b) => b.unites - a.unites).slice(0, 8);
      G.monter('g-unites-moteur', 'bar', {
        series: [{ name: 'Unités', data: top.map((m) => Math.round(m.unites * jours / 30)) }], colors: [G.couleurs()[0]],
        plotOptions: { bar: { horizontal: true, barHeight: '58%' } }, xaxis: { categories: top.map((m) => m.nom) }, legend: { show: false },
        dataLabels: { enabled: true, offsetX: 6, style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] }, formatter: (v) => fmt.compact(v), textAnchor: 'start' },
        tooltip: { shared: false, intersect: true },
      }, { hauteur: 250, tableau: { colonnes: ['Moteur', 'Unités'], lignes: top.map((m) => [m.nom, Math.round(m.unites * jours / 30)]) } });

      /* Matrice : intensité séquentielle à une teinte, avec la valeur écrite. */
      const lignes = ['Faux', 'Trompeur', 'Vrai', 'Non vérifiable'];
      const m = [[412, 38, 6, 9], [44, 221, 17, 12], [5, 21, 168, 7], [11, 15, 9, 61]];
      G.monter('g-confusion', 'heatmap', {
        series: lignes.map((nom, i) => ({ name: 'Publié : ' + nom, data: lignes.map((c, j) => ({ x: 'IA : ' + c, y: m[i][j] })) })).reverse(),
        plotOptions: { heatmap: { radius: 4, enableShades: false, colorScale: { ranges: [{ from: 0, to: 20, color: G.RAMPE[0] }, { from: 21, to: 60, color: G.RAMPE[1] }, { from: 61, to: 150, color: G.RAMPE[3] }, { from: 151, to: 300, color: G.RAMPE[4] }, { from: 301, to: 1000, color: G.RAMPE[5] }] } } },
        dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: 600, colors: ['#0d366b'] } },
        stroke: { width: 2, colors: [G.encre().surface] }, legend: { show: false },
        tooltip: { shared: false, intersect: true },
      }, { hauteur: 260, tableau: { colonnes: ['Publié \\ IA', ...lignes], lignes: lignes.map((l, i) => [l, ...m[i]]) } });
      brancherPeriode(racine, rendre);
      icones();
      /* Étiquettes lisibles sur les cases foncées. */
      setTimeout(() => $$('#g-confusion .apexcharts-datalabel', racine).forEach((t) => { if (+t.textContent > 60) t.setAttribute('fill', '#ffffff'); }), 450);
    };
    rendre(30);
  };

  /* ======================= Analytique LMS ======================= */

  VF.ecrans['analytique-lms'] = (racine, { mentor = false } = {}) => {
    G.detruireTout();
    const inactifs = VF.apprenants.filter((a) => a.etat === 'inactif' || (a.progression < 40 && +a.derniere < +new Date(2026, 9, 1)));
    racine.innerHTML = `
      ${enTete({ titre: mentor ? 'Progression de mes apprenants' : 'Analytique de l\'Académie', sousTitre: 'Complétion, progression, engagement et résultats. Relancez ceux qui décrochent.', fil: [mentor ? 'Espace pédagogique' : 'Analytique'], actions: filtrePeriode(30) })}
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        ${kpi({ libelle: 'Taux de complétion', valeur: '61 %', delta: 3.2, aide: 'cours commencés', ic: 'circle-check' })}
        ${kpi({ libelle: 'Apprenants actifs', valeur: fmt.nombre(mentor ? 1342 : 4182), delta: 11, aide: 'sur 30 jours', ic: 'users' })}
        ${kpi({ libelle: 'Temps d\'apprentissage', valeur: '38 min', delta: 6, aide: 'par apprenant et par semaine', ic: 'clock' })}
        ${kpi({ libelle: 'Réussite aux examens', valeur: '74 %', delta: -1.5, aide: 'première tentative', ic: 'award' })}
      </div>
      <div class="grid lg:grid-cols-2 gap-4 mb-4">
        ${G.carte({ id: 'g-entonnoir', titre: 'Parcours des inscrits', aide: 'De l\'inscription au certificat', hauteur: 250 })}
        ${G.carte({ id: 'g-completion', titre: 'Complétion par cours', aide: 'Part des inscrits ayant terminé', hauteur: 250 })}
      </div>
      <div class="grid lg:grid-cols-3 gap-4 mb-4">
        ${G.carte({ id: 'g-engagement', titre: 'Quand les apprenants travaillent', aide: 'Sessions par jour et créneau horaire', hauteur: 250, classe: 'lg:col-span-2' })}
        ${G.carte({ id: 'g-inscriptions', titre: 'Nouvelles inscriptions', aide: 'Par jour', hauteur: 250 })}
      </div>
      <div class="grid lg:grid-cols-5 gap-4">
        ${carte({ titre: 'À relancer', aide: `${inactifs.length} apprenants sans activité depuis plus de 14 jours`, classe: 'lg:col-span-3', actions: `<button class="btn-primaire btn-sm" data-relancer-tous>${icone('send')}Relancer tout le monde</button>`, corps: `<div class="overflow-x-auto -mx-5"><table class="tableau"><thead><tr><th>Apprenant</th><th class="hidden 2xl:table-cell">Cours</th><th>Progression</th><th>Dernière activité</th><th></th></tr></thead><tbody>${inactifs.slice(0, 6).map((a) => `<tr><td><div class="flex items-center gap-2.5"><span class="avatar w-8 h-8">${esc(a.initiales)}</span><span class="font-medium whitespace-nowrap">${esc(a.nom)}</span></div></td><td class="texte-2 max-w-[200px] truncate hidden 2xl:table-cell">${esc(a.cours)}</td><td><div class="flex items-center gap-2 min-w-[100px]"><div class="jauge"><span style="width:${a.progression}%"></span></div><span class="text-xs w-11 text-right whitespace-nowrap">${a.progression} %</span></div></td><td class="texte-2 whitespace-nowrap">${fmt.date(a.derniere)}</td><td class="text-right"><button class="btn-second btn-sm" data-relancer="${esc(a.nom)}">${icone('send')}Relancer</button></td></tr>`).join('')}</tbody></table></div>` })}
        ${carte({ titre: 'Résultats par cours', classe: 'lg:col-span-2', corps: `<ul class="space-y-3.5">${VF.cours.map((c) => `<li><div class="flex justify-between gap-3 text-sm"><span class="truncate">${esc(c.titre)}</span><span class="font-medium tabular-nums whitespace-nowrap">${(11 + (c.note - 4) * 8).toFixed(1).replace('.', ',')} / 20</span></div><p class="text-xs texte-3 mt-0.5">${c.completion} % de complétion — ${fmt.nombre(c.inscrits)} inscrits</p></li>`).join('')}</ul>` })}
      </div>`;

    const etapes = ['Inscrits', 'Ont commencé', 'Mi-parcours', 'Terminé', 'Certifiés'];
    const valeurs = [9427, 7810, 5240, 3980, 3120];
    /* Étapes ordonnées -> rampe ordinale (la plus claire reste > 2:1). */
    const ordinale = G.sombre() ? ['#6da7ec', '#3987e5', '#2a78d6', '#256abf', '#1c5cab'] : ['#86b6ef', '#5598e7', '#2a78d6', '#1c5cab', '#104281'];
    G.monter('g-entonnoir', 'bar', {
      series: [{ name: 'Apprenants', data: valeurs }], colors: ordinale,
      plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '62%' } }, xaxis: { categories: etapes, max: 12500 }, legend: { show: false },
      dataLabels: { enabled: true, textAnchor: 'start', offsetX: 6, style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] }, formatter: (v, o) => `${fmt.nombre(v)} — ${Math.round((v / valeurs[0]) * 100)} %` },
      tooltip: { shared: false, intersect: true },
    }, { hauteur: 250, tableau: { colonnes: ['Étape', 'Apprenants', 'Part des inscrits'], lignes: etapes.map((e, i) => [e, fmt.nombre(valeurs[i]), Math.round((valeurs[i] / valeurs[0]) * 100) + ' %']) } });

    const cours = VF.cours.slice().sort((a, b) => b.completion - a.completion);
    G.monter('g-completion', 'bar', {
      series: [{ name: 'Complétion', data: cours.map((c) => c.completion) }], colors: [G.couleurs()[0]],
      plotOptions: { bar: { horizontal: true, barHeight: '56%' } }, xaxis: { categories: cours.map((c) => c.titre.length > 28 ? c.titre.slice(0, 27) + '…' : c.titre), min: 0, max: 100, tickAmount: 5, labels: { formatter: (v) => Math.round(v) + ' %' } }, legend: { show: false },
      dataLabels: { enabled: true, textAnchor: 'start', offsetX: 6, style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] }, formatter: (v) => v + ' %' },
      tooltip: { shared: false, intersect: true },
    }, { hauteur: 250, tableau: { colonnes: ['Cours', 'Complétion'], lignes: cours.map((c) => [c.titre, c.completion + ' %']) } });

    const joursSem = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    const creneaux = ['6–9 h', '9–12 h', '12–15 h', '15–18 h', '18–21 h', '21–24 h'];
    const intensite = joursSem.map((j, d) => creneaux.map((c, h) => Math.round((h === 4 ? 90 : h === 5 ? 60 : h === 1 ? 45 : 25) * (d > 4 ? 1.25 : 1) + VF.outils.hasard() * 25)));
    G.monter('g-engagement', 'heatmap', {
      series: joursSem.map((j, d) => ({ name: j, data: creneaux.map((c, h) => ({ x: c, y: intensite[d][h] })) })).reverse(),
      plotOptions: { heatmap: { radius: 4, enableShades: false, colorScale: { ranges: [{ from: 0, to: 35, color: G.RAMPE[0] }, { from: 36, to: 55, color: G.RAMPE[1] }, { from: 56, to: 80, color: G.RAMPE[2] }, { from: 81, to: 110, color: G.RAMPE[3] }, { from: 111, to: 200, color: G.RAMPE[5] }] } } },
      stroke: { width: 2, colors: [G.encre().surface] }, legend: { show: false }, tooltip: { shared: false, intersect: true, y: { formatter: (v) => v + ' sessions' } },
    }, { hauteur: 250, tableau: { colonnes: ['Jour', ...creneaux], lignes: joursSem.map((j, d) => [j, ...intensite[d]]) } });

    const x = etiquettesJours(30);
    G.monter('g-inscriptions', 'bar', {
      series: [{ name: 'Inscriptions', data: VF.series.inscriptions }], colors: [G.couleurs()[0]], xaxis: { categories: x, tickAmount: 5 }, legend: { show: false },
      plotOptions: { bar: { columnWidth: '62%' } }, tooltip: { shared: false, intersect: false },
    }, { hauteur: 250, tableau: { colonnes: ['Jour', 'Inscriptions'], lignes: x.map((j, i) => [j, VF.series.inscriptions[i]]) } });

    $$('[data-relancer]', racine).forEach((b) => b.addEventListener('click', () => { b.disabled = true; b.innerHTML = icone('check') + 'Relancé'; icones(); toast(`Relance envoyée à ${b.dataset.relancer}.`); }));
    $('[data-relancer-tous]', racine).addEventListener('click', () => VF.ui.confirmer({ titre: `Relancer ${inactifs.length} apprenants ?`, texte: 'Chacun reçoit un courriel personnalisé avec sa prochaine leçon. Un apprenant n\'est jamais relancé deux fois en 7 jours.', bouton: 'Envoyer les relances', ton: 'primaire', siOui: () => toast(`${inactifs.length} relances envoyées.`) }));
    icones();
  };

  /* ======================= SEO ======================= */

  VF.ecrans['analytique-seo'] = (racine) => {
    G.detruireTout();
    racine.innerHTML = `
      ${enTete({ titre: 'Analytique SEO', sousTitre: 'Positions sur Google, liens entrants et origine du trafic.', fil: ['Analytique'], actions: filtrePeriode(30) + `<button class="btn-second" onclick="VF.ui.toast('Données Search Console resynchronisées.','info')">${icone('refresh-cw')}Synchroniser</button>` })}
      <div class="carte p-3.5 mb-6 flex items-start gap-3 text-sm">${icone('info', 'w-[18px] h-[18px] mt-0.5 text-blue-700 dark:text-blue-300')}<p class="texte-2">Source prévue : l'API Google Search Console et Google Analytics 4. Elles ne sont pas encore branchées : les chiffres ci-dessous sont des exemples.</p></div>
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        ${kpi({ libelle: 'Sessions organiques', valeur: fmt.compact(VF.series.sessionsOrganiques.reduce((a, b) => a + b, 0)), delta: 18, aide: '30 jours', ic: 'mouse-pointer-click' })}
        ${kpi({ libelle: 'Position moyenne', valeur: '6,6', delta: -9, sens: 'bas-bon', aide: 'plus bas est meilleur', ic: 'list-ordered' })}
        ${kpi({ libelle: 'Mots-clés dans le top 10', valeur: '142', delta: 12, ic: 'key-round' })}
        ${kpi({ libelle: 'Domaines référents', valeur: '318', delta: 4, ic: 'link' })}
      </div>
      <div class="grid lg:grid-cols-3 gap-4 mb-4">
        ${G.carte({ id: 'g-organique', titre: 'Sessions venant de Google', aide: 'Par jour', hauteur: 250, classe: 'lg:col-span-2' })}
        ${G.carte({ id: 'g-sources', titre: 'Origine du trafic', aide: 'Part des sessions', hauteur: 250 })}
      </div>
      <div class="grid lg:grid-cols-5 gap-4">
        ${carte({ titre: 'Positions des mots-clés', classe: 'lg:col-span-3', corps: `<div class="overflow-x-auto -mx-5"><table class="tableau"><thead><tr><th>Mot-clé</th><th>Position</th><th>Évolution</th><th>Volume mensuel</th></tr></thead><tbody>${VF.motsCles.map((k) => `<tr><td><p class="font-medium">${esc(k.mot)}</p><p class="text-xs texte-3">${esc(k.url)}</p></td><td class="font-semibold">${k.position}</td><td>${k.delta === 0 ? `<span class="texte-3 inline-flex items-center gap-1">${icone('minus', 'w-3.5 h-3.5')}stable</span>` : k.delta > 0 ? `<span class="inline-flex items-center gap-1 text-green-700 dark:text-bon">${icone('arrow-up', 'w-3.5 h-3.5')}gagne ${k.delta}</span>` : `<span class="inline-flex items-center gap-1 text-red-700 dark:text-red-400">${icone('arrow-down', 'w-3.5 h-3.5')}perd ${-k.delta}</span>`}</td><td>${fmt.nombre(k.volume)}</td></tr>`).join('')}</tbody></table></div>` })}
        ${carte({ titre: 'Liens entrants récents', classe: 'lg:col-span-2', corps: `<ul class="space-y-3">${VF.backlinks.map((b) => `<li class="flex items-center gap-3 text-sm"><span class="inline-flex w-9 h-9 shrink-0 items-center justify-center rounded-ctrl bg-papier dark:bg-nuit text-xs font-semibold" title="Autorité du domaine">${b.autorite}</span><div class="min-w-0 flex-1"><p class="font-medium truncate">${esc(b.domaine)}</p><p class="text-xs texte-3">${esc(b.type)} — vu le ${fmt.date(b.vu)}</p></div>${b.etat === 'gagne' ? pastille('p-bon', 'plus', 'Nouveau') : pastille('p-critique', 'unlink', 'Perdu')}</li>`).join('')}</ul>` })}
      </div>`;

    const x = etiquettesJours(30);
    G.monter('g-organique', 'area', {
      series: [{ name: 'Sessions organiques', data: VF.series.sessionsOrganiques }], colors: [G.couleurs()[0]], xaxis: { categories: x, tickAmount: 8 },
      fill: { type: 'gradient', gradient: { opacityFrom: 0.18, opacityTo: 0, stops: [0, 100] } }, legend: { show: false },
    }, { hauteur: 250, tableau: { colonnes: ['Jour', 'Sessions'], lignes: x.map((j, i) => [j, VF.series.sessionsOrganiques[i]]) } });

    const sources = [['Recherche Google', 46], ['Accès direct', 21], ['WhatsApp et réseaux', 18], ['Sites référents', 10], ['Infolettre', 5]];
    G.monter('g-sources', 'bar', {
      series: [{ name: 'Part', data: sources.map((s) => s[1]) }], colors: [G.couleurs()[0]], legend: { show: false },
      plotOptions: { bar: { horizontal: true, barHeight: '56%' } }, xaxis: { categories: sources.map((s) => s[0]), max: 60, labels: { formatter: (v) => v + ' %' } },
      dataLabels: { enabled: true, textAnchor: 'start', offsetX: 6, style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] }, formatter: (v) => v + ' %' },
      tooltip: { shared: false, intersect: true },
    }, { hauteur: 250, tableau: { colonnes: ['Origine', 'Part'], lignes: sources.map((s) => [s[0], s[1] + ' %']) } });
    icones();
  };

  /* ======================= Économie : pipeline, points et quotas ======================= */

  VF.ecrans['economie'] = (racine) => {
    G.detruireTout();
    const p = VF.pipeline;
    racine.innerHTML = `
      ${enTete({ titre: 'Revenus, points et quotas', sousTitre: 'La valeur du tunnel de paiement, la performance des mentors et partenaires, et leurs objectifs.', fil: ['Analytique'], actions: filtrePeriode(30) })}
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        ${kpi({ libelle: 'Valeur du tunnel', valeur: fmt.compact(p[0].valeur) + ' FCFA', delta: 21, aide: 'paniers créés', ic: 'filter' })}
        ${kpi({ libelle: 'Encaissé', valeur: fmt.compact(p[3].valeur) + ' FCFA', delta: 17, aide: 'unités créditées', ic: 'wallet' })}
        ${kpi({ libelle: 'Conversion du tunnel', valeur: fmt.pct((p[3].nombre / p[0].nombre) * 100), delta: 2.4, ic: 'percent' })}
        ${kpi({ libelle: 'Objectif mensuel', valeur: '79 %', aide: 'de 3 M FCFA', ic: 'flag' })}
      </div>
      <div class="grid lg:grid-cols-2 gap-4 mb-4">
        ${G.carte({ id: 'g-tunnel', titre: 'Tunnel de paiement', aide: 'Valeur à chaque étape, en FCFA', hauteur: 240 })}
        ${G.carte({ id: 'g-revenus', titre: 'Encaissements quotidiens', aide: 'En FCFA', hauteur: 240 })}
      </div>
      ${carte({ titre: 'Classement et quotas', aide: 'Points gagnés sur le mois et objectif de 8 000 points', corps: `<div class="overflow-x-auto -mx-5"><table class="tableau"><thead><tr><th>Rang</th><th>Membre</th><th>Rôle</th><th>Ventes générées</th><th>Apprenants</th><th>Points / quota</th><th>Satisfaction</th></tr></thead><tbody>${VF.equipe.slice().sort((a, b) => b.points - a.points).map((e, i) => { const t = Math.min(100, Math.round((e.points / e.quota) * 100)); return `<tr><td class="font-semibold">${i + 1}</td><td><div class="flex items-center gap-2.5"><span class="avatar w-8 h-8">${esc(e.initiales)}</span><span class="font-medium">${esc(e.nom)}</span></div></td><td class="texte-2">${e.role}</td><td>${fmt.fcfa(e.ventes)}</td><td>${fmt.nombre(e.apprenants)}</td><td><div class="min-w-[170px]"><div class="flex justify-between text-xs mb-1"><span>${fmt.nombre(e.points)} pts</span><span class="${t >= 100 ? 'text-green-700 dark:text-bon font-medium' : 'texte-3'}">${t >= 100 ? 'Quota atteint' : t + ' %'}</span></div><div class="jauge"><span style="width:${t}%"></span></div></div></td><td>${icone('star', 'w-3.5 h-3.5 inline text-amber-500')} ${String(e.satisfaction).replace('.', ',')}</td></tr>`; }).join('')}</tbody></table></div>` })}`;

    const ordinale = G.sombre() ? ['#6da7ec', '#3987e5', '#256abf', '#1c5cab'] : ['#86b6ef', '#3987e5', '#256abf', '#104281'];
    G.monter('g-tunnel', 'bar', {
      series: [{ name: 'Valeur', data: p.map((e) => e.valeur) }], colors: ordinale, legend: { show: false },
      plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '60%' } }, xaxis: { categories: p.map((e) => e.etape), labels: { formatter: (v) => fmt.compact(v) } },
      dataLabels: { enabled: true, textAnchor: 'start', offsetX: 6, style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] }, formatter: (v, o) => `${fmt.compact(v)} — ${p[o.dataPointIndex].nombre} paiements` },
      tooltip: { shared: false, intersect: true, y: { formatter: (v) => fmt.fcfa(v) } },
    }, { hauteur: 240, tableau: { colonnes: ['Étape', 'Valeur', 'Paiements'], lignes: p.map((e) => [e.etape, fmt.fcfa(e.valeur), e.nombre]) } });

    const x = etiquettesJours(30), rev = VF.series.verifications.map((v) => v * 640);
    G.monter('g-revenus', 'bar', {
      series: [{ name: 'Encaissé', data: rev }], colors: [G.couleurs()[0]], xaxis: { categories: x, tickAmount: 6 }, legend: { show: false },
      plotOptions: { bar: { columnWidth: '62%' } }, tooltip: { y: { formatter: (v) => fmt.fcfa(v) } },
    }, { hauteur: 240, tableau: { colonnes: ['Jour', 'Encaissé'], lignes: x.map((j, i) => [j, fmt.fcfa(rev[i])]) } });
    icones();
  };

  /* ======================= Business Intelligence ======================= */

  const METRIQUES = {
    signalements: { libelle: 'Signalements reçus', serie: () => VF.series.signalements, equipe: 'Fact-checking' },
    verifications: { libelle: 'Fact-checks publiés', serie: () => VF.series.verifications, equipe: 'Fact-checking' },
    inscriptions: { libelle: 'Inscriptions aux cours', serie: () => VF.series.inscriptions, equipe: 'Académie' },
    unites: { libelle: 'Unités consommées', serie: () => VF.series.unites, equipe: 'Économie' },
    organique: { libelle: 'Sessions organiques', serie: () => VF.series.sessionsOrganiques, equipe: 'Communication' },
  };
  const lireWidgets = () => { try { return JSON.parse(localStorage.getItem('vf-widgets') || '[]'); } catch (e) { return []; } };
  const ecrireWidgets = (w) => { try { localStorage.setItem('vf-widgets', JSON.stringify(w)); } catch (e) { /* stockage indisponible : l'espace reste en mémoire */ } };

  VF.ecrans['bi'] = (racine) => {
    G.detruireTout();
    let widgets = lireWidgets();
    if (!widgets.length) widgets = [{ metrique: 'verifications', forme: 'area', agregat: 'somme' }, { metrique: 'inscriptions', forme: 'bar', agregat: 'somme' }];
    const choix = { metrique: 'signalements', forme: 'area', agregat: 'somme', comparer: false };

    racine.innerHTML = `
      ${enTete({ titre: 'Business Intelligence', sousTitre: 'Composez vos indicateurs, surveillez vos sources de données, partagez des rapports entre équipes.', fil: ['Analytique'] })}
      <div class="grid xl:grid-cols-3 gap-4 mb-4">
        <section class="carte xl:col-span-1">
          <div class="carte-tete"><div><h2 class="carte-titre">Constructeur d'indicateur</h2><p class="text-xs texte-3 mt-0.5">L'aperçu se met à jour à chaque choix.</p></div></div>
          <div class="carte-corps space-y-4">
            <div><label class="etiquette" for="kb-metrique">Mesure</label><select id="kb-metrique" class="champ">${Object.entries(METRIQUES).map(([k, m]) => `<option value="${k}">${m.libelle}</option>`).join('')}</select></div>
            <div><p class="etiquette">Calcul</p><div class="segments" role="group" aria-label="Calcul">${[['somme', 'Total'], ['moyenne', 'Moyenne'], ['max', 'Maximum']].map(([k, l]) => `<button class="segment" data-agregat="${k}" aria-pressed="${k === 'somme'}">${l}</button>`).join('')}</div></div>
            <div><p class="etiquette">Forme</p><div class="segments" role="group" aria-label="Forme">${[['area', 'Courbe'], ['bar', 'Barres'], ['tuile', 'Chiffre seul']].map(([k, l]) => `<button class="segment" data-forme="${k}" aria-pressed="${k === 'area'}">${l}</button>`).join('')}</div></div>
            <div class="flex items-center justify-between gap-3"><span class="text-sm">Comparer au mois précédent</span><button class="bascule" role="switch" aria-checked="false" data-comparer aria-label="Comparer au mois précédent"><span></span></button></div>
            <button class="btn-primaire w-full" data-ajouter>${icone('plus')}Ajouter à mon espace</button>
          </div>
        </section>
        <div class="xl:col-span-2" id="kb-apercu-zone"></div>
      </div>
      <div class="flex items-center justify-between gap-3 mb-3 mt-8"><h2 class="font-titre text-lg font-semibold">Mon espace</h2><button class="btn-fantome btn-sm" data-vider>${icone('rotate-ccw')}Réinitialiser</button></div>
      <div id="kb-espace" class="grid md:grid-cols-2 gap-4 mb-8"></div>
      <div class="grid lg:grid-cols-2 gap-4">
        ${carte({ titre: 'Sources de données', aide: 'Fraîcheur et volume de chaque source', corps: `<ul class="divide-y divide-trait dark:divide-nuit-trait -my-2">${[['Base MySQL — production', 'Toutes les tables métier', 'il y a 1 min', '4,2 M lignes', 'ok'], ['File d\'attente (jobs)', 'Analyses, courriels, exports', 'il y a 12 s', '38 en attente', 'ok'], ['Mobile Money — rapprochement', 'MTN, Moov, Celtiis', 'il y a 4 min', '479 paiements', 'ok'], ['Google Search Console', 'Positions et clics', 'jamais', 'non branchée', 'alerte'], ['Journaux des moteurs IA', '19 adaptateurs', 'il y a 40 s', '182 k appels', 'ok'], ['Google Analytics 4', 'Audience du site', 'il y a 26 h', '1,2 M événements', 'grave']].map(([n, d, f, v, e]) => `<li class="flex items-center gap-3 py-3"><span class="inline-flex w-9 h-9 shrink-0 items-center justify-center rounded-ctrl bg-papier dark:bg-nuit texte-2">${icone('database', 'w-4 h-4')}</span><div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">${n}</p><p class="text-xs texte-3 truncate">${d} — ${v}</p></div>${e === 'ok' ? pastille('p-bon', 'circle-check', f) : e === 'alerte' ? pastille('p-alerte', 'plug-zap', 'À brancher') : pastille('p-grave', 'clock-alert', 'Retard : ' + f)}</li>`).join('')}</ul>` })}
        ${carte({ titre: 'Rapports partagés', aide: 'Envoyés automatiquement aux équipes', actions: `<button class="btn-second btn-sm" onclick="VF.ui.toast('Nouveau rapport créé en brouillon.')">${icone('plus')}Nouveau rapport</button>`, corps: `<div class="overflow-x-auto -mx-5"><table class="tableau"><thead><tr><th>Rapport</th><th>Équipes</th><th>Fréquence</th><th></th></tr></thead><tbody>${[['Bilan hebdomadaire de la vérification', 'Fact-checking, Direction', 'Lundi 8 h'], ['Santé de l\'Académie', 'Académie, Mentors', 'Chaque jour'], ['Revenus et paiements', 'Direction, Finances', '1er du mois'], ['Audience et SEO', 'Communication', 'Vendredi 17 h'], ['Coûts des moteurs IA', 'Technique, Finances', 'Chaque semaine']].map(([r, e, f]) => `<tr><td class="font-medium">${r}</td><td class="texte-2">${e}</td><td class="texte-2 whitespace-nowrap">${f}</td><td class="text-right whitespace-nowrap"><button class="btn-fantome btn-sm" onclick="VF.ui.toast('Rapport généré et envoyé.')">${icone('play')}Exécuter</button></td></tr>`).join('')}</tbody></table></div>` })}
      </div>`;

    const calc = (serie, agregat) => agregat === 'somme' ? serie.reduce((a, b) => a + b, 0) : agregat === 'moyenne' ? Math.round(serie.reduce((a, b) => a + b, 0) / serie.length) : Math.max(...serie);
    const x = etiquettesJours(30);

    const widgetHtml = (w, id, apercu) => {
      const m = METRIQUES[w.metrique];
      const valeur = calc(m.serie(), w.agregat);
      const suffixe = { somme: 'au total sur 30 jours', moyenne: 'en moyenne par jour', max: 'au plus haut sur 30 jours' }[w.agregat];
      if (w.forme === 'tuile') {
        return `<section class="carte p-5 ${apercu ? 'h-full' : ''}"><div class="flex justify-between gap-2"><p class="text-sm texte-2">${m.libelle}</p>${apercu ? '' : `<button class="btn-icone !w-7 !h-7" data-retirer="${id}" aria-label="Retirer">${icone('x', '!w-4 !h-4')}</button>`}</div><p class="mt-3 text-[40px] leading-none font-semibold tracking-tight">${fmt.nombre(valeur)}</p><p class="mt-2 text-xs texte-3">${suffixe} — équipe ${m.equipe}</p></section>`;
      }
      return G.carte({ id, titre: m.libelle, aide: `${fmt.nombre(valeur)} ${suffixe}`, hauteur: 220, actions: apercu ? '' : `<button class="btn-icone !w-8 !h-8" data-retirer="${id}" aria-label="Retirer">${icone('x')}</button>` });
    };
    const graphique = (w, id) => {
      if (w.forme === 'tuile') return;
      const m = METRIQUES[w.metrique];
      const serie = m.serie();
      const series = [{ name: 'Ce mois', data: serie }];
      if (w.comparer) series.push({ name: 'Mois précédent', data: serie.map((v) => Math.round(v * 0.84)) });
      G.monter(id, w.forme, {
        series, xaxis: { categories: x, tickAmount: 6 }, legend: { show: series.length > 1 },
        fill: w.forme === 'area' ? { type: 'gradient', gradient: { opacityFrom: 0.16, opacityTo: 0, stops: [0, 100] } } : { opacity: 1 },
        plotOptions: { bar: { columnWidth: series.length > 1 ? '70%' : '58%' } },
        stroke: w.forme === 'bar' ? { width: 2, colors: [G.encre().surface] } : { width: 2 },
      }, { hauteur: 220, tableau: { colonnes: ['Jour', ...series.map((s) => s.name)], lignes: x.map((j, i) => [j, ...series.map((s) => s.data[i])]) } });
    };

    const rendreApercu = () => {
      G.detruireTout();
      $('#kb-apercu-zone', racine).innerHTML = widgetHtml(choix, 'kb-apercu', true);
      graphique(choix, 'kb-apercu');
      rendreEspace(false);
      icones();
    };
    const rendreEspace = (detruire = true) => {
      if (detruire) { G.detruireTout(); $('#kb-apercu-zone', racine).innerHTML = widgetHtml(choix, 'kb-apercu', true); graphique(choix, 'kb-apercu'); }
      $('#kb-espace', racine).innerHTML = widgets.map((w, i) => widgetHtml(w, 'kb-w' + i, false)).join('');
      widgets.forEach((w, i) => graphique(w, 'kb-w' + i));
      $$('[data-retirer]', racine).forEach((b) => b.addEventListener('click', () => { widgets.splice(+b.dataset.retirer.replace('kb-w', ''), 1); ecrireWidgets(widgets); rendreEspace(); toast('Indicateur retiré de votre espace.', 'info'); }));
      icones();
    };

    $('#kb-metrique', racine).addEventListener('change', (e) => { choix.metrique = e.target.value; rendreApercu(); });
    $$('[data-agregat]', racine).forEach((b) => b.addEventListener('click', () => { choix.agregat = b.dataset.agregat; $$('[data-agregat]', racine).forEach((x2) => x2.setAttribute('aria-pressed', String(x2 === b))); rendreApercu(); }));
    $$('[data-forme]', racine).forEach((b) => b.addEventListener('click', () => { choix.forme = b.dataset.forme; $$('[data-forme]', racine).forEach((x2) => x2.setAttribute('aria-pressed', String(x2 === b))); rendreApercu(); }));
    $('[data-comparer]', racine).addEventListener('click', (e) => { const b = e.currentTarget; const v = b.getAttribute('aria-checked') !== 'true'; b.setAttribute('aria-checked', String(v)); choix.comparer = v; rendreApercu(); });
    $('[data-ajouter]', racine).addEventListener('click', () => { widgets.push({ ...choix }); ecrireWidgets(widgets); rendreEspace(); toast(`« ${METRIQUES[choix.metrique].libelle} » ajouté à votre espace.`); });
    $('[data-vider]', racine).addEventListener('click', () => { widgets = []; ecrireWidgets(widgets); rendreEspace(); });
    rendreApercu();
  };

  /* Statistiques globales : la vue d'ensemble, toutes équipes confondues. */
  VF.ecrans['stats-globales'] = (racine) => {
    G.detruireTout();
    racine.innerHTML = `
      ${enTete({ titre: 'Statistiques globales', sousTitre: 'Les chiffres de la plateforme depuis son lancement.', fil: ['Académie'] })}
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        ${kpi({ libelle: 'Comptes créés', valeur: fmt.nombre(38214), ic: 'users' })}
        ${kpi({ libelle: 'Vérifications réalisées', valeur: fmt.nombre(214380), ic: 'scan-search' })}
        ${kpi({ libelle: 'Certificats délivrés', valeur: fmt.nombre(6912), ic: 'award' })}
        ${kpi({ libelle: 'Villes représentées', valeur: '77', aide: 'sur 77 communes', ic: 'map-pin' })}
      </div>
      <div class="grid lg:grid-cols-3 gap-4">
        ${G.carte({ id: 'g-villes', titre: 'Comptes par ville', aide: 'Les 8 premières', hauteur: 280, classe: 'lg:col-span-2' })}
        ${carte({ titre: 'Raccourcis', corps: `<div class="grid gap-2">${[['analytique-lms', 'graduation-cap', 'Analytique de l\'Académie'], ['analytique-ia', 'cpu', 'Analytique de l\'IA'], ['economie', 'wallet', 'Revenus et quotas'], ['analytique-seo', 'search', 'SEO'], ['bi', 'layout-dashboard', 'Business Intelligence']].map(([r, ic, l]) => `<a href="#/${r}" class="flex items-center gap-3 p-3 rounded-ctrl border filet hover:border-brand/50 transition-colors">${icone(ic, 'w-4 h-4 texte-2')}<span class="text-sm font-medium">${l}</span></a>`).join('')}</div>` })}
      </div>`;
    const villes = [['Cotonou', 14210], ['Abomey-Calavi', 7120], ['Porto-Novo', 4980], ['Parakou', 3310], ['Bohicon', 1720], ['Ouidah', 1180], ['Natitingou', 940], ['Djougou', 810]];
    G.monter('g-villes', 'bar', {
      series: [{ name: 'Comptes', data: villes.map((v) => v[1]) }], colors: [G.couleurs()[0]], legend: { show: false },
      xaxis: { categories: villes.map((v) => v[0]) }, plotOptions: { bar: { columnWidth: '52%', dataLabels: { position: 'top' } } },
      dataLabels: { enabled: true, offsetY: -18, style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] }, formatter: (v) => fmt.compact(v) },
      tooltip: { shared: false, intersect: true },
    }, { hauteur: 280, tableau: { colonnes: ['Ville', 'Comptes'], lignes: villes.map((v) => [v[0], fmt.nombre(v[1])]) } });
    icones();
  };
})(window.VF);
