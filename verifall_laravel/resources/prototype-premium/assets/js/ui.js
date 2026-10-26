/*
 | Briques d'interface partagées : chaque écran les assemble, aucun ne les
 | réécrit. Ce sont, une à une, les futurs composants Blade (`<x-kpi>`,
 | `<x-offcanvas-form>`, `<x-delete-modal>`, `<x-toast>`…).
 */
(function (VF) {
  'use strict';

  const $ = (sel, racine = document) => racine.querySelector(sel);
  const $$ = (sel, racine = document) => Array.from(racine.querySelectorAll(sel));

  /** Échappe tout texte injecté dans du HTML : le prototype montre déjà la règle. */
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const fmt = {
    nombre: (n) => new Intl.NumberFormat('fr-FR').format(n),
    compact: (n) => new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 }).format(n),
    fcfa: (n) => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA',
    pct: (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(n) + ' %',
    date: (d) => new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(d)),
    jourCourt: (d) => new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(new Date(d)),
    duree: (min) => (min < 60 ? `${min} min` : min < 1440 ? `${Math.floor(min / 60)} h ${String(min % 60).padStart(2, '0')}` : `${Math.floor(min / 1440)} j`),
  };

  const icones = () => { if (window.lucide) window.lucide.createIcons({ attrs: { 'stroke-width': 1.75 } }); };
  const icone = (nom, classe = '') => `<i data-lucide="${nom}" class="${classe}"></i>`;

  /* ---------- Fragments ---------- */

  const pastille = (classe, ic, libelle) => `<span class="${classe}">${icone(ic)}${esc(libelle)}</span>`;

  /**
   * Une tuile de chiffre clé. Le delta porte son sens dans le texte (« +12 % »)
   * et une flèche : la couleur ne le dit jamais seule.
   */
  const kpi = ({ libelle, valeur, delta = null, sens = 'haut-bon', aide = '', ic = null }) => {
    let deltaHtml = '';
    if (delta !== null) {
      const monte = delta >= 0;
      const bon = sens === 'haut-bon' ? monte : !monte;
      deltaHtml = `<span class="inline-flex items-center gap-0.5 text-xs font-medium ${bon ? 'text-green-700 dark:text-bon' : 'text-red-700 dark:text-red-400'}">
        ${icone(monte ? 'trending-up' : 'trending-down', 'w-3.5 h-3.5')}${monte ? '+' : ''}${fmt.pct(delta)}</span>`;
    }
    return `<div class="carte p-4">
      <div class="flex items-center justify-between gap-2">
        <p class="text-[13px] texte-2">${esc(libelle)}</p>
        ${ic ? `<span class="texte-3">${icone(ic, 'w-4 h-4')}</span>` : ''}
      </div>
      <p class="mt-2 text-[26px] leading-none font-semibold tracking-tight">${valeur}</p>
      <div class="mt-2 flex items-center gap-2 min-h-[18px]">${deltaHtml}<span class="text-xs texte-3">${esc(aide)}</span></div>
    </div>`;
  };

  const enTete = ({ titre, sousTitre = '', actions = '', fil = [] }) => `
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
      <div class="min-w-0">
        ${fil.length ? `<nav class="flex items-center gap-1.5 text-[13px] texte-3 mb-1.5" aria-label="Fil d'Ariane">${fil.map((f, i) => (i ? icone('chevron-right', 'w-3.5 h-3.5') : '') + `<span>${esc(f)}</span>`).join('')}</nav>` : ''}
        <h1 class="text-[26px] sm:text-[28px] font-semibold leading-tight">${esc(titre)}</h1>
        ${sousTitre ? `<p class="mt-1 text-sm texte-2 max-w-2xl">${esc(sousTitre)}</p>` : ''}
      </div>
      ${actions ? `<div class="flex flex-wrap items-center gap-2">${actions}</div>` : ''}
    </div>`;

  const carte = ({ titre = '', aide = '', actions = '', corps = '', classe = '' }) => `
    <section class="carte ${classe}">
      ${titre ? `<div class="carte-tete"><div><h2 class="carte-titre">${esc(titre)}</h2>${aide ? `<p class="text-xs texte-3 mt-0.5">${esc(aide)}</p>` : ''}</div>${actions}</div>` : ''}
      <div class="${titre ? 'carte-corps' : 'p-5'}">${corps}</div>
    </section>`;

  const vide = ({ ic = 'inbox', titre, texte, action = '' }) => `
    <div class="text-center py-12 px-6">
      <span class="inline-flex w-11 h-11 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand/10 dark:text-brand">${icone(ic, 'w-5 h-5')}</span>
      <p class="mt-3 font-medium">${esc(titre)}</p>
      <p class="mt-1 text-sm texte-2 max-w-sm mx-auto">${esc(texte)}</p>
      ${action ? `<div class="mt-4">${action}</div>` : ''}
    </div>`;

  /* ---------- Toasts ---------- */

  const toast = (message, ton = 'bon') => {
    const tons = { bon: ['circle-check', 'text-green-700 dark:text-bon'], critique: ['circle-x', 'text-red-700 dark:text-red-400'], info: ['info', 'text-blue-700 dark:text-blue-300'], alerte: ['triangle-alert', 'text-amber-700 dark:text-amber-300'] };
    const [ic, couleur] = tons[ton] || tons.bon;
    const el = document.createElement('div');
    el.className = 'carte shadow-flottant flex items-start gap-3 p-3.5 pr-2 translate-y-2 opacity-0 transition duration-200';
    el.setAttribute('role', 'status');
    el.innerHTML = `<span class="${couleur} mt-0.5">${icone(ic, 'w-[18px] h-[18px]')}</span><p class="flex-1 text-sm">${esc(message)}</p><button class="btn-icone !w-7 !h-7" aria-label="Fermer">${icone('x', '!w-4 !h-4')}</button>`;
    $('#toasts').appendChild(el);
    icones();
    requestAnimationFrame(() => el.classList.remove('translate-y-2', 'opacity-0'));
    const partir = () => { el.classList.add('opacity-0'); setTimeout(() => el.remove(), 200); };
    el.querySelector('button').addEventListener('click', partir);
    setTimeout(partir, 4200);
  };

  /* ---------- Couches : tiroir, modale ---------- */

  let dernierFocus = null;
  const pieger = (couche) => {
    const focusables = $$('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])', couche);
    if (focusables.length) focusables[0].focus();
  };

  const fermerCouche = (id) => {
    const c = document.getElementById(id);
    c.classList.add('hidden'); c.setAttribute('aria-hidden', 'true'); c.innerHTML = '';
    document.body.classList.remove('overflow-hidden');
    if (dernierFocus) dernierFocus.focus();
  };

  /** Le formulaire latéral (`<x-offcanvas-form>`). */
  const tiroir = ({ titre, sousTitre = '', corps, pied = '', large = false, apres = null }) => {
    dernierFocus = document.activeElement;
    const c = $('#tiroir');
    c.innerHTML = `
      <div class="voile" data-fermer></div>
      <aside class="fixed inset-y-0 right-0 z-50 w-full ${large ? 'sm:max-w-2xl' : 'sm:max-w-md'} bg-white dark:bg-nuit-carte shadow-flottant flex flex-col" role="dialog" aria-modal="true" aria-labelledby="tiroir-titre">
        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b filet">
          <div><h2 id="tiroir-titre" class="font-titre text-lg font-semibold">${esc(titre)}</h2>${sousTitre ? `<p class="text-sm texte-2 mt-0.5">${esc(sousTitre)}</p>` : ''}</div>
          <button class="btn-icone" data-fermer aria-label="Fermer">${icone('x')}</button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-5">${corps}</div>
        ${pied ? `<div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t filet">${pied}</div>` : ''}
      </aside>`;
    c.classList.remove('hidden'); c.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
    $$('[data-fermer]', c).forEach((b) => b.addEventListener('click', () => fermerCouche('tiroir')));
    icones(); dates(c); bascules(c); pieger(c);
    if (apres) apres(c);
    return c;
  };

  const modale = ({ titre, corps, actions = '', apres = null, large = false }) => {
    dernierFocus = document.activeElement;
    const c = $('#modale');
    c.innerHTML = `
      <div class="voile !z-[55]" data-fermer></div>
      <div class="fixed inset-0 z-[56] flex items-center justify-center p-4 pointer-events-none">
        <div class="carte shadow-flottant w-full ${large ? 'max-w-2xl' : 'max-w-md'} pointer-events-auto" role="dialog" aria-modal="true" aria-labelledby="modale-titre">
          <div class="flex items-start justify-between gap-3 px-5 pt-4">
            <h2 id="modale-titre" class="font-titre text-lg font-semibold">${esc(titre)}</h2>
            <button class="btn-icone -mr-2" data-fermer aria-label="Fermer">${icone('x')}</button>
          </div>
          <div class="px-5 py-4 text-sm">${corps}</div>
          ${actions ? `<div class="flex justify-end gap-2 px-5 pb-4">${actions}</div>` : ''}
        </div>
      </div>`;
    c.classList.remove('hidden'); c.setAttribute('aria-hidden', 'false');
    $$('[data-fermer]', c).forEach((b) => b.addEventListener('click', () => fermerCouche('modale')));
    icones(); dates(c); pieger(c);
    if (apres) apres(c);
    return c;
  };

  /** La confirmation de suppression (`<x-delete-modal>`) : elle nomme ce qui part. */
  const confirmer = ({ titre, texte, bouton = 'Supprimer', ton = 'danger', siOui }) => {
    modale({
      titre,
      corps: `<p class="texte-2">${esc(texte)}</p>`,
      actions: `<button class="btn-second" data-fermer>Annuler</button><button class="${ton === 'danger' ? 'btn-danger' : 'btn-primaire'}" data-oui>${esc(bouton)}</button>`,
      apres: (c) => {
        $$('[data-fermer]', c).forEach((b) => b.addEventListener('click', () => fermerCouche('modale')));
        $('[data-oui]', c).addEventListener('click', () => { fermerCouche('modale'); siOui && siOui(); });
      },
    });
  };

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (!$('#modale').classList.contains('hidden')) fermerCouche('modale');
    else if (!$('#tiroir').classList.contains('hidden')) fermerCouche('tiroir');
  });

  /* ---------- Menu d'actions rapides ---------- */

  let menuOuvert = null;
  const fermerMenu = () => { if (menuOuvert) { menuOuvert.remove(); menuOuvert = null; } };
  document.addEventListener('click', (e) => { if (menuOuvert && !menuOuvert.contains(e.target)) fermerMenu(); });

  const menu = (ancre, items) => {
    fermerMenu();
    const r = ancre.getBoundingClientRect();
    const m = document.createElement('div');
    m.className = 'menu fixed';
    m.setAttribute('role', 'menu');
    m.innerHTML = items.map((it) => it === '-' ? '<div class="my-1 border-t filet"></div>' :
      `<button class="menu-item ${it.danger ? '!text-red-700 dark:!text-red-400' : ''}" role="menuitem">${icone(it.ic, it.danger ? '!text-current' : '')}${esc(it.libelle)}</button>`).join('');
    document.body.appendChild(m);
    const largeur = m.offsetWidth;
    m.style.top = Math.min(r.bottom + 6, window.innerHeight - m.offsetHeight - 8) + 'px';
    m.style.left = Math.max(8, r.right - largeur) + 'px';
    icones();
    const vrais = items.filter((it) => it !== '-');
    $$('.menu-item', m).forEach((b, i) => b.addEventListener('click', (e) => { e.stopPropagation(); fermerMenu(); vrais[i].action && vrais[i].action(); }));
    setTimeout(() => (menuOuvert = m));
  };

  /* ---------- Onglets, bascules, dates ---------- */

  const onglets = (racine, surChange) => {
    $$('[role="tablist"]', racine).forEach((liste) => {
      const tabs = $$('[role="tab"]', liste);
      tabs.forEach((t) => t.addEventListener('click', () => {
        tabs.forEach((x) => { x.setAttribute('aria-selected', String(x === t)); const p = document.getElementById(x.getAttribute('aria-controls')); if (p) p.hidden = x !== t; });
        surChange && surChange(t.dataset.onglet);
      }));
    });
  };

  const bascules = (racine) => {
    $$('.bascule', racine).forEach((b) => {
      if (b.dataset.lie) return; b.dataset.lie = '1';
      b.addEventListener('click', () => {
        const v = b.getAttribute('aria-checked') !== 'true';
        b.setAttribute('aria-checked', String(v));
        if (b.dataset.toast) toast(`${b.dataset.toast} ${v ? 'activé' : 'désactivé'}`, 'info');
      });
    });
  };

  const dates = (racine) => {
    if (!window.flatpickr) return;
    $$('[data-date]', racine).forEach((el) => flatpickr(el, { locale: 'fr', dateFormat: 'd/m/Y', allowInput: true }));
    $$('[data-date-heure]', racine).forEach((el) => flatpickr(el, { locale: 'fr', enableTime: true, time_24hr: true, dateFormat: 'd/m/Y H:i' }));
    $$('[data-plage]', racine).forEach((el) => flatpickr(el, { locale: 'fr', mode: 'range', dateFormat: 'd/m/Y', defaultDate: el.dataset.plage ? el.dataset.plage.split(',') : null }));
  };

  /* ---------- Validation des formulaires ----------
     Les règles se lisent sur le champ : data-regles="requis|email|min:8".
     Le message dit quoi corriger, jamais « champ invalide ». */
  const valider = (form) => {
    let ok = true;
    $$('[data-regles]', form).forEach((champ) => {
      const regles = champ.dataset.regles.split('|');
      const v = (champ.value || '').trim();
      const nom = champ.dataset.nom || 'Ce champ';
      let msg = '';
      for (const r of regles) {
        const [cle, arg] = r.split(':');
        if (cle === 'requis' && !v) { msg = `${nom} est obligatoire.`; break; }
        if (cle === 'email' && v && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) { msg = 'Saisissez une adresse du type nom@domaine.bj.'; break; }
        if (cle === 'min' && v && v.length < +arg) { msg = `${nom} doit compter au moins ${arg} caractères.`; break; }
        if (cle === 'nombre' && v && isNaN(+v.replace(/\s/g, ''))) { msg = `${nom} doit être un nombre.`; break; }
      }
      const aide = champ.parentElement.querySelector('[data-aide]');
      champ.classList.toggle('champ-erreur', !!msg);
      champ.classList.toggle('champ-ok', !msg && !!v);
      champ.setAttribute('aria-invalid', String(!!msg));
      if (aide) {
        aide.className = msg ? 'aide-erreur' : 'aide';
        aide.innerHTML = msg ? icone('circle-alert', 'w-3.5 h-3.5') + esc(msg) : esc(aide.dataset.aide || '');
      }
      if (msg) ok = false;
    });
    icones();
    if (!ok) { const premier = $('.champ-erreur', form); if (premier) premier.focus(); }
    return ok;
  };

  /** Un champ de formulaire complet : étiquette, contrôle, aide. */
  const champ = ({ nom, libelle, type = 'text', valeur = '', regles = '', aide = '', options = null, place = '', attrs = '' }) => {
    const id = 'c-' + nom;
    const commun = `id="${id}" name="${nom}" class="champ" data-nom="${esc(libelle)}" ${regles ? `data-regles="${regles}"` : ''} ${attrs}`;
    let ctrl;
    if (options) ctrl = `<select ${commun}>${options.map((o) => `<option ${o === valeur ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
    else if (type === 'textarea') ctrl = `<textarea rows="4" ${commun} placeholder="${esc(place)}">${esc(valeur)}</textarea>`;
    else ctrl = `<input type="${type}" ${commun} value="${esc(valeur)}" placeholder="${esc(place)}">`;
    return `<div><label for="${id}" class="etiquette">${esc(libelle)}${regles.includes('requis') ? ' <span class="text-critique" aria-hidden="true">*</span>' : ''}</label>${ctrl}<p class="aide" data-aide="${esc(aide)}">${esc(aide)}</p></div>`;
  };

  VF.ui = { $, $$, esc, fmt, icones, icone, pastille, kpi, enTete, carte, vide, toast, tiroir, modale, confirmer, fermerCouche, menu, onglets, bascules, dates, valider, champ };
})(window.VF);
