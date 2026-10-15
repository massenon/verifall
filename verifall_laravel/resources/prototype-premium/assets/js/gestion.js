/*
 | Le moteur des écrans de gestion.
 |
 | Une quarantaine d'écrans de la console sont la même chose : une liste
 | filtrable, des statuts, des actions rapides et un formulaire latéral. Ils
 | se décrivent ici par une définition plutôt que de se recopier — c'est
 | exactement le rôle que tient `Staff\Opportunites` côté Laravel.
 */
(function (VF) {
  'use strict';
  const { $, $$, esc, fmt, icone, icones, pastille, kpi, enTete, vide, toast, tiroir, confirmer, fermerCouche, menu, valider, champ } = VF.ui;

  const PAR_PAGE = 8;

  const cellule = (col, ligne) => {
    const v = ligne[col.cle];
    switch (col.type) {
      case 'personne': {
        const p = typeof v === 'object' ? v : { nom: v, initiales: String(v).split(' ').map((m) => m[0]).join('').slice(0, 2) };
        return `<div class="flex items-center gap-2.5 min-w-0"><span class="avatar w-8 h-8">${esc(p.initiales)}</span><div class="min-w-0"><p class="font-medium truncate">${esc(p.nom)}</p>${p.email ? `<p class="text-xs texte-3 truncate">${esc(p.email)}</p>` : ''}</div></div>`;
      }
      case 'titre':
        return `<div class="min-w-0 max-w-[420px]"><p class="font-medium truncate">${esc(v)}</p>${col.sous ? `<p class="text-xs texte-3 truncate">${esc(ligne[col.sous])}</p>` : ''}</div>`;
      case 'pastille': {
        const e = col.etats[v] || { classe: 'p-neutre', ic: 'circle', libelle: v };
        return pastille(e.classe, e.ic, e.libelle);
      }
      case 'date': return `<span class="whitespace-nowrap texte-2">${fmt.date(v)}</span>`;
      case 'nombre': return `<span class="tabular-nums">${fmt.nombre(v)}</span>`;
      case 'fcfa': return `<span class="tabular-nums whitespace-nowrap">${v ? fmt.fcfa(v) : 'Gratuit'}</span>`;
      case 'pct': return `<span class="tabular-nums">${fmt.pct(v)}</span>`;
      case 'jauge': return `<div class="flex items-center gap-2 min-w-[120px]"><div class="jauge"><span style="width:${v}%"></span></div><span class="text-xs tabular-nums texte-2 w-11 text-right whitespace-nowrap">${v} %</span></div>`;
      case 'image': return `<img src="${esc(v)}" alt="" class="w-12 h-9 rounded-md object-cover">`;
      case 'code': return `<code class="text-xs px-1.5 py-0.5 rounded bg-papier dark:bg-nuit texte-2">${esc(v)}</code>`;
      case 'etoiles': return `<span class="inline-flex items-center gap-1 tabular-nums">${icone('star', 'w-3.5 h-3.5 text-amber-500 fill-amber-400')}${esc(v)}</span>`;
      default: return `<span class="${col.muet ? 'texte-2' : ''}">${esc(v)}</span>`;
    }
  };

  const texteDe = (col, ligne) => {
    const v = ligne[col.cle];
    if (v && typeof v === 'object' && v.nom) return v.nom;
    if (col.type === 'pastille') return (col.etats[v] || {}).libelle || v;
    if (col.type === 'date') return fmt.date(v);
    return v;
  };

  const exporterCsv = (def, lignes) => {
    const cols = def.colonnes;
    const csv = [cols.map((c) => c.libelle).join(';')]
      .concat(lignes.map((l) => cols.map((c) => `"${String(texteDe(c, l) ?? '').replace(/"/g, '""')}"`).join(';')))
      .join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
    a.download = `${def.id}.csv`;
    a.click();
    toast(`${lignes.length} lignes exportées en CSV.`);
  };

  /** Ouvre le formulaire latéral, en création ou en modification. */
  const formulaire = (def, ligne, surEnregistrer) => {
    const creation = !ligne;
    const nom = def.entite;
    tiroir({
      titre: creation ? `${def.feminin ? 'Nouvelle' : 'Nouveau'} ${nom}` : `Modifier ${def.feminin ? 'la' : 'le'} ${nom}`,
      sousTitre: creation ? 'Les champs marqués d\'un astérisque sont obligatoires.' : '',
      large: !!def.formLarge,
      corps: `<form id="form-gestion" class="space-y-4" novalidate>${(def.champs || []).map((c) => champ({ ...c, valeur: ligne ? (typeof ligne[c.nom] === 'object' ? ligne[c.nom].nom : ligne[c.nom]) ?? c.valeur ?? '' : c.valeur ?? '' })).join('')}</form>`,
      pied: `<button class="btn-second" data-fermer>Annuler</button><button class="btn-primaire" form="form-gestion" type="submit">${icone('check')}${creation ? 'Créer' : 'Enregistrer'}</button>`,
      apres: (c) => {
        $('#form-gestion', c).addEventListener('submit', (e) => {
          e.preventDefault();
          if (!valider(e.target)) return;
          fermerCouche('tiroir');
          surEnregistrer && surEnregistrer(Object.fromEntries(new FormData(e.target)));
          toast(creation ? `${def.entite.charAt(0).toUpperCase() + def.entite.slice(1)} ${def.feminin ? 'créée' : 'créé'}.` : 'Modifications enregistrées.');
        });
        $$('input, select, textarea', c).forEach((el) => el.addEventListener('blur', () => { if (el.dataset.regles && el.value) valider(c.querySelector('form')); }));
      },
    });
  };

  /**
   * Rend un écran de gestion complet dans `racine`.
   */
  const ecran = (racine, def) => {
    const etat = { q: '', filtres: {}, tri: null, sens: 1, page: 1, choix: new Set(), lignes: def.lignes.map((l, i) => ({ _id: i, ...l })), vue: 'liste' };

    const actionsEnTete = `
      ${def.exporter !== false ? `<button class="btn-second" data-exporter>${icone('download')}Exporter</button>` : ''}
      ${def.actionsEnTete || ''}
      ${def.champs ? `<button class="btn-primaire" data-creer>${icone('plus')}${def.feminin ? 'Nouvelle' : 'Nouveau'} ${esc(def.entite)}</button>` : ''}`;

    racine.innerHTML = `
      ${enTete({ titre: def.titre, sousTitre: def.sousTitre, fil: def.fil, actions: actionsEnTete })}
      ${def.kpis ? `<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">${def.kpis.map(kpi).join('')}</div>` : ''}
      ${def.avant || ''}
      <section class="carte">
        <div class="flex flex-wrap items-center gap-2 p-3 border-b filet">
          <label class="relative flex-1 min-w-[200px] max-w-sm">
            <span class="sr-only">Rechercher</span>
            <span class="absolute left-3 top-1/2 -translate-y-1/2 texte-3">${icone('search', 'w-4 h-4')}</span>
            <input data-recherche class="champ !h-9 pl-9" placeholder="${esc(def.placeRecherche || 'Rechercher…')}">
          </label>
          ${(def.filtres || []).map((f) => `<label class="sr-only" for="f-${f.cle}">${esc(f.libelle)}</label><select id="f-${f.cle}" data-filtre="${f.cle}" class="champ !h-9 !w-auto min-w-[150px]"><option value="">${esc(f.libelle)} : tous</option>${f.options.map((o) => `<option value="${esc(o.valeur ?? o)}">${esc(o.libelle ?? o)}</option>`).join('')}</select>`).join('')}
          ${def.plage ? `<input data-plage class="champ !h-9 !w-[220px]" placeholder="Période" aria-label="Période">` : ''}
          <div class="ml-auto flex items-center gap-2">
            ${def.vueCartes ? `<div class="segments" role="group" aria-label="Affichage"><button class="segment" aria-pressed="true" data-mode="liste" aria-label="Liste">${icone('list', 'w-4 h-4')}</button><button class="segment" aria-pressed="false" data-mode="cartes" aria-label="Cartes">${icone('layout-grid', 'w-4 h-4')}</button></div>` : ''}
          </div>
        </div>
        <div data-groupe class="hidden flex items-center gap-3 px-4 py-2.5 bg-brand-50 dark:bg-brand/10 border-b filet text-sm">
          <span data-groupe-compte class="font-medium"></span>
          ${(def.actionsGroupees || [{ libelle: 'Supprimer', ic: 'trash-2', danger: true }]).map((a, i) => `<button class="btn-fantome btn-sm ${a.danger ? '!text-red-700 dark:!text-red-400' : ''}" data-groupe-action="${i}">${icone(a.ic)}${esc(a.libelle)}</button>`).join('')}
          <button class="btn-fantome btn-sm ml-auto" data-groupe-vider>Désélectionner</button>
        </div>
        <div data-corps></div>
        <div data-pagination class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t filet text-sm"></div>
      </section>
      ${def.apres || ''}`;

    const filtrees = () => {
      const q = etat.q.toLowerCase();
      let l = etat.lignes.filter((ligne) => {
        if (q && !def.colonnes.some((c) => String(texteDe(c, ligne) ?? '').toLowerCase().includes(q))) return false;
        return Object.entries(etat.filtres).every(([cle, v]) => !v || String(ligne[cle]) === v);
      });
      if (etat.tri) {
        const col = def.colonnes.find((c) => c.cle === etat.tri);
        l = l.slice().sort((a, b) => {
          const x = texteDe(col, a), y = texteDe(col, b);
          const va = col.type === 'date' ? +new Date(a[col.cle]) : x, vb = col.type === 'date' ? +new Date(b[col.cle]) : y;
          return (typeof va === 'number' ? va - vb : String(va).localeCompare(String(vb), 'fr')) * etat.sens;
        });
      }
      return l;
    };

    const actionsLigne = (ligne) => [
      ...(def.actionsLigne ? def.actionsLigne(ligne, rendre) : [{ libelle: 'Voir le détail', ic: 'eye', action: () => (def.detail ? def.detail(ligne) : formulaire(def, ligne)) }]),
      ...(def.champs ? [{ libelle: 'Modifier', ic: 'pencil', action: () => formulaire(def, ligne, (v) => { Object.assign(ligne, v); rendre(); }) }] : []),
      ...(def.supprimable !== false ? ['-', { libelle: 'Supprimer', ic: 'trash-2', danger: true, action: () => confirmer({
        titre: `Supprimer ${def.feminin ? 'cette' : 'ce'} ${def.entite} ?`,
        texte: `« ${texteDe(def.colonnes[0], ligne)} » sera supprimé${def.feminin ? 'e' : ''} définitivement. L'action est journalisée.`,
        siOui: () => { etat.lignes = etat.lignes.filter((x) => x !== ligne); rendre(); toast('Supprimé. L\'action figure dans le journal d\'audit.'); },
      }) }] : []),
    ];

    const rendre = () => {
      const liste = filtrees();
      const pages = Math.max(1, Math.ceil(liste.length / PAR_PAGE));
      etat.page = Math.min(etat.page, pages);
      const tranche = liste.slice((etat.page - 1) * PAR_PAGE, etat.page * PAR_PAGE);
      const corps = $('[data-corps]', racine);

      if (!liste.length) {
        corps.innerHTML = vide({ ic: 'search-x', titre: 'Aucun résultat', texte: 'Aucune ligne ne correspond à ces filtres. Élargissez la recherche ou réinitialisez les filtres.', action: '<button class="btn-second" data-reinit>Réinitialiser les filtres</button>' });
      } else if (etat.vue === 'cartes' && def.vueCartes) {
        corps.innerHTML = `<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 p-4">${tranche.map((l) => def.vueCartes(l)).join('')}</div>`;
      } else {
        corps.innerHTML = `<div class="overflow-x-auto"><table class="tableau">
          <thead><tr>
            <th class="w-10"><input type="checkbox" class="case" data-tout aria-label="Tout sélectionner"></th>
            ${def.colonnes.map((c) => `<th>${c.tri === false ? esc(c.libelle) : `<button class="tri" data-tri="${c.cle}">${esc(c.libelle)}${etat.tri === c.cle ? icone(etat.sens > 0 ? 'arrow-up' : 'arrow-down', 'w-3.5 h-3.5') : icone('chevrons-up-down', 'w-3.5 h-3.5 opacity-40')}</button>`}</th>`).join('')}
            <th class="w-12"><span class="sr-only">Actions</span></th>
          </tr></thead>
          <tbody>${tranche.map((l) => `<tr>
            <td><input type="checkbox" class="case" data-choix="${l._id}" ${etat.choix.has(l._id) ? 'checked' : ''} aria-label="Sélectionner"></td>
            ${def.colonnes.map((c) => `<td>${cellule(c, l)}</td>`).join('')}
            <td class="text-right"><button class="btn-icone !w-8 !h-8" data-actions="${l._id}" aria-label="Actions">${icone('ellipsis')}</button></td>
          </tr>`).join('')}</tbody></table></div>`;
      }

      const debut = liste.length ? (etat.page - 1) * PAR_PAGE + 1 : 0;
      $('[data-pagination]', racine).innerHTML = `
        <p class="texte-2">${debut}–${Math.min(etat.page * PAR_PAGE, liste.length)} sur ${fmt.nombre(liste.length)}</p>
        <div class="flex items-center gap-1">
          <button class="btn-icone !w-8 !h-8" data-page="${etat.page - 1}" ${etat.page <= 1 ? 'disabled' : ''} aria-label="Page précédente">${icone('chevron-left')}</button>
          ${Array.from({ length: pages }, (_, i) => `<button class="w-8 h-8 rounded-ctrl text-sm ${i + 1 === etat.page ? 'bg-foret text-white dark:bg-brand dark:text-foret font-medium' : 'hover:bg-papier dark:hover:bg-nuit'}" data-page="${i + 1}" ${i + 1 === etat.page ? 'aria-current="page"' : ''}>${i + 1}</button>`).join('')}
          <button class="btn-icone !w-8 !h-8" data-page="${etat.page + 1}" ${etat.page >= pages ? 'disabled' : ''} aria-label="Page suivante">${icone('chevron-right')}</button>
        </div>`;

      const groupe = $('[data-groupe]', racine);
      groupe.classList.toggle('hidden', etat.choix.size === 0);
      $('[data-groupe-compte]', racine).textContent = `${etat.choix.size} sélectionné${etat.choix.size > 1 ? 's' : ''}`;
      icones();
    };

    racine.addEventListener('click', (e) => {
      const t = e.target.closest('button, input');
      if (!t) return;
      if (t.matches('[data-tri]')) { const c = t.dataset.tri; etat.sens = etat.tri === c ? -etat.sens : 1; etat.tri = c; rendre(); }
      else if (t.matches('[data-page]')) { etat.page = +t.dataset.page; rendre(); }
      else if (t.matches('[data-actions]')) { const l = etat.lignes.find((x) => x._id === +t.dataset.actions); menu(t, actionsLigne(l)); }
      else if (t.matches('[data-choix]')) { const id = +t.dataset.choix; t.checked ? etat.choix.add(id) : etat.choix.delete(id); rendre(); }
      else if (t.matches('[data-tout]')) { const ids = filtrees().slice((etat.page - 1) * PAR_PAGE, etat.page * PAR_PAGE).map((l) => l._id); ids.forEach((id) => (t.checked ? etat.choix.add(id) : etat.choix.delete(id))); rendre(); }
      else if (t.matches('[data-groupe-vider]')) { etat.choix.clear(); rendre(); }
      else if (t.matches('[data-groupe-action]')) {
        const a = (def.actionsGroupees || [{ libelle: 'Supprimer', danger: true }])[+t.dataset.groupeAction];
        const n = etat.choix.size;
        const faire = () => { if (a.danger) etat.lignes = etat.lignes.filter((l) => !etat.choix.has(l._id)); etat.choix.clear(); rendre(); toast(`${a.libelle} : ${n} élément${n > 1 ? 's' : ''}.`); };
        a.danger ? confirmer({ titre: `${a.libelle} ${n} élément${n > 1 ? 's' : ''} ?`, texte: 'L\'action est définitive et sera journalisée.', bouton: a.libelle, siOui: faire }) : faire();
      }
      else if (t.matches('[data-creer]')) formulaire(def, null, (v) => { etat.lignes.unshift({ _id: Date.now(), ...def.nouvelle?.(v), ...v }); rendre(); });
      else if (t.matches('[data-exporter]')) exporterCsv(def, filtrees());
      else if (t.matches('[data-reinit]')) { etat.q = ''; etat.filtres = {}; $('[data-recherche]', racine).value = ''; $$('[data-filtre]', racine).forEach((s) => (s.value = '')); rendre(); }
      else if (t.matches('[data-mode]')) { etat.vue = t.dataset.mode; $$('[data-mode]', racine).forEach((b) => b.setAttribute('aria-pressed', String(b === t))); rendre(); }
    });

    let minuteur;
    $('[data-recherche]', racine).addEventListener('input', (e) => { clearTimeout(minuteur); minuteur = setTimeout(() => { etat.q = e.target.value; etat.page = 1; rendre(); }, 250); });
    $$('[data-filtre]', racine).forEach((s) => s.addEventListener('change', () => { etat.filtres[s.dataset.filtre] = s.value; etat.page = 1; rendre(); }));

    VF.ui.dates(racine);
    rendre();
    if (def.monte) def.monte(racine);
  };

  VF.gestion = { ecran, formulaire, cellule };
})(window.VF);
