/*
 | Mode maintenance (Système) : fermer la plateforme au public pendant une mise
 | à jour importante, l'équipe connectée gardant tout son accès. La page vue
 | par le public est maintenance.html.
 |
 | Implémenté dans Laravel : App\Livewire\Staff\Maintenance (arbitrage n° 181).
 */
(function (VF) {
  'use strict';
  const { $, $$, esc, icone, pastille, enTete, toast, confirmer } = VF.ui;

  const MOTIFS = [
    ['Mise à jour majeure', 'rocket', 'Nous installons une nouvelle version de Vérif’All. Vos vérifications, vos cours et vos certificats sont conservés.'],
    ['Migration des données', 'database', 'Nous déplaçons la base de données vers une infrastructure plus solide. Aucune donnée n’est perdue pendant l’opération.'],
    ['Intervention de sécurité', 'shield-check', 'Nous appliquons un correctif de sécurité. Le service revient dès que le correctif est vérifié.'],
    ['Travaux de l’hébergeur', 'server', 'Notre hébergeur intervient sur ses serveurs. Nous rouvrons dès qu’il nous rend la main.'],
  ];

  const PASSAGES = [
    ['user-check', 'Le personnel connecté', 'Administration et espace mentor, sans restriction.', true],
    ['log-in', 'La connexion à la console', 'Mot de passe, double authentification et écran de verrouillage.', true],
    ['webhook', 'Les rappels Mobile Money', 'Signés, ils créditent les unités déjà payées.', true],
    ['activity', 'La sonde de disponibilité', '/up répond toujours aux outils de supervision.', true],
    ['timer', 'Les tâches planifiées et la file', 'Elles continuent de tourner : suspendez-les si la mise à jour l’exige.', true],
    ['globe', 'Le public et les citoyens', 'Page de maintenance en 503, avec Retry-After quand une heure est annoncée.', false],
    ['smartphone', 'L’application mobile (API)', 'Réponse JSON 503 : l’application affiche son propre message.', false],
  ];

  const etat = VF.maintenance = VF.maintenance || {
    actif: false, debut: null, fin: null, message: '', attente: 14,
    historique: [{ debut: '12 sept. 2026, 23:05', duree: '52 min', message: 'Migration de la base vers le nouvel hébergeur.', par: 'Mawuli Adjovi' }],
  };

  const hhmm = (d) => d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

  VF.ecrans['maintenance'] = (racine) => {
    const rendre = () => {
      const minutes = etat.debut ? Math.max(1, Math.round((Date.now() - etat.debut) / 60000)) : 0;
      const ecoule = etat.actif && etat.fin ? Math.min(100, Math.round((Date.now() - etat.debut) / (etat.fin - etat.debut) * 100)) : null;

      const hero = etat.actif ? `
        <section class="carte overflow-hidden !border-amber-300 dark:!border-amber-900/70 mb-6">
          <div class="p-5 sm:p-6 bg-gradient-to-br from-amber-50 to-white dark:from-amber-950/40 dark:to-nuit-carte">
            <div class="flex flex-wrap items-start gap-4">
              <span class="inline-flex w-12 h-12 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">${icone('construction', 'w-6 h-6')}</span>
              <div class="flex-1 min-w-[220px]">
                ${pastille('p-alerte', 'lock-keyhole', 'Fermé au public')}
                <h2 class="mt-1.5 font-titre text-xl sm:text-2xl font-semibold">Le site est fermé depuis ${minutes} min</h2>
                <p class="mt-1 text-sm texte-2">${etat.fin ? `Réouverture annoncée à ${hhmm(etat.fin)} — une promesse, pas une minuterie : rien ne rouvre tout seul.` : 'Aucune heure de fin annoncée : la page propose seulement d’être prévenu par courriel.'}</p>
              </div>
              <button class="btn-primaire !h-10 !px-4" data-rouvrir>${icone('power')}Rouvrir le site</button>
            </div>
            ${ecoule !== null ? `<div class="mt-5"><div class="flex justify-between text-xs texte-3 mb-1.5"><span>Fermé à ${hhmm(new Date(etat.debut))}</span><span>Temps annoncé écoulé : ${ecoule} %</span><span>Annoncé pour ${hhmm(etat.fin)}</span></div><div class="jauge !h-2"><span class="!bg-amber-500" style="width:${ecoule}%"></span></div></div>` : ''}
          </div>
          <dl class="grid sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-trait dark:divide-nuit-trait border-t filet">
            <div class="p-4"><dt class="text-xs texte-3">Personnes à prévenir</dt><dd class="font-titre text-2xl font-semibold">${etat.attente}</dd></div>
            <div class="p-4"><dt class="text-xs texte-3">Déjà prévenues</dt><dd class="font-titre text-2xl font-semibold">236</dd></div>
            <div class="p-4"><dt class="text-xs texte-3">Code renvoyé au public</dt><dd class="font-titre text-2xl font-semibold">503</dd></div>
          </dl>
        </section>` : `
        <section class="carte p-5 sm:p-6 mb-6">
          <div class="flex flex-wrap items-center gap-4">
            <span class="inline-flex w-12 h-12 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand/15 dark:text-brand">${icone('radio', 'w-6 h-6')}</span>
            <div class="flex-1 min-w-[220px]">${pastille('p-bon', 'circle-check', 'En service')}<h2 class="mt-1.5 font-titre text-xl sm:text-2xl font-semibold">Le site est ouvert à tout le monde</h2><p class="mt-1 text-sm texte-2">Dernière maintenance le ${esc(etat.historique[0].debut)}, pendant ${esc(etat.historique[0].duree)}.</p></div>
          </div>
        </section>`;

      const formulaire = etat.actif ? `
        <section class="carte"><div class="carte-tete"><h2 class="carte-titre">Ce que voit le public</h2></div>
          <div class="carte-corps"><blockquote class="rounded-ctrl border filet bg-papier dark:bg-nuit p-4 text-sm">« ${esc(etat.message || 'Nous mettons Vérif’All à jour. Le service revient dès que l’opération est terminée.')} »</blockquote></div></section>` : `
        <section class="carte">
          <div class="carte-tete"><h2 class="carte-titre">Préparer la fermeture</h2><span class="text-xs texte-3">Rien n’est fermé avant la confirmation</span></div>
          <div class="carte-corps space-y-5">
            <div><p class="etiquette">Motif</p><div class="flex flex-wrap gap-2">${MOTIFS.map(([l, ic], i) => `<button class="btn-second btn-sm" data-motif="${i}">${icone(ic)}${l}</button>`).join('')}</div><p class="aide">Un clic reprend un texte de départ ; précisez-le ensuite.</p></div>
            <div><div class="flex justify-between"><label class="etiquette" for="mt-message">Message affiché au public</label><span class="text-xs texte-3" id="mt-compte">0 / 500</span></div>
              <textarea id="mt-message" class="champ !h-auto py-2.5" rows="3" maxlength="500" placeholder="Nous mettons Vérif’All à jour…">${esc(etat.message)}</textarea>
              <p class="aide">Dites ce qui se passe et ce qui est préservé, pas « nous améliorons votre expérience ».</p></div>
            <div><p class="etiquette">Réouverture annoncée <span class="texte-3 font-normal">(facultative)</span></p>
              <div class="segments" role="group">${[[0, 'Sans heure'], [30, '+30 min'], [60, '+1 h'], [120, '+2 h'], [240, '+4 h']].map(([m, l]) => `<button class="segment" data-duree="${m}" aria-pressed="${m === 0}">${l}</button>`).join('')}</div>
              <p class="aide">Une <strong>promesse, pas une minuterie</strong> : elle n’ouvre rien toute seule. Elle alimente le décompte public et l’en-tête Retry-After.</p></div>
            <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t filet"><p class="text-xs texte-3 max-w-md">Votre session et celle de l’équipe connectée restent ouvertes.</p><button class="btn-danger !h-10 !px-4" data-fermer-site>${icone('lock-keyhole')}Fermer le site…</button></div>
          </div>
        </section>`;

      racine.innerHTML = `
        ${enTete({ titre: 'Mode maintenance', sousTitre: 'Ferme la plateforme au public pendant une mise à jour importante. L’équipe connectée garde tout son accès.', fil: ['Système'], actions: `<a class="btn-second" href="maintenance.html" target="_blank" rel="noopener">${icone('external-link')}Voir la page publique</a>` })}
        ${hero}
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
          <div class="space-y-6 min-w-0">
            ${formulaire}
            <section class="carte"><div class="carte-tete"><h2 class="carte-titre">Dernières maintenances</h2><span class="text-xs texte-3">Relues dans le journal d’audit</span></div>
              <div class="overflow-x-auto"><table class="tableau"><thead><tr><th>Fermeture</th><th>Durée</th><th>Motif annoncé</th><th>Par</th></tr></thead><tbody>
              ${etat.historique.map((h) => `<tr><td class="whitespace-nowrap">${esc(h.debut)}</td><td>${h.duree ? esc(h.duree) : pastille('p-alerte', 'clock', 'En cours')}</td><td class="texte-2">${esc(h.message)}</td><td class="whitespace-nowrap">${esc(h.par)}</td></tr>`).join('')}
              </tbody></table></div></section>
          </div>
          <aside class="space-y-6 min-w-0">
            <section class="carte"><div class="carte-tete"><h2 class="carte-titre">Pendant la maintenance</h2></div>
              <ul class="carte-corps space-y-3">${PASSAGES.map(([ic, t, d, ok]) => `<li class="flex gap-3"><span class="inline-flex w-8 h-8 shrink-0 items-center justify-center rounded-ctrl ${ok ? 'bg-brand-50 text-brand-700 dark:bg-brand/15 dark:text-brand' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}">${icone(ic, 'w-4 h-4')}</span><span><span class="flex items-center gap-1.5 text-sm font-medium">${t}${icone(ok ? 'check' : 'x', `w-3.5 h-3.5 ${ok ? 'text-bon' : 'text-critique'}`)}</span><span class="block text-xs texte-3">${d}</span></span></li>`).join('')}</ul></section>
          </aside>
        </div>`;

      let duree = 0;
      const zone = $('#mt-message', racine);
      const compter = () => { if (zone) $('#mt-compte', racine).textContent = `${zone.value.length} / 500`; };
      compter();
      zone?.addEventListener('input', compter);
      $$('[data-motif]', racine).forEach((b) => b.addEventListener('click', () => { zone.value = MOTIFS[+b.dataset.motif][2]; compter(); }));
      $$('[data-duree]', racine).forEach((b) => b.addEventListener('click', () => {
        duree = +b.dataset.duree;
        $$('[data-duree]', racine).forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      }));

      $('[data-fermer-site]', racine)?.addEventListener('click', () => confirmer({
        titre: 'Fermer le site au public ?',
        texte: 'Plus personne ne pourra soumettre une vérification, suivre un cours ou lire un démenti. Votre accès à la console est préservé.',
        bouton: 'Fermer maintenant',
        siOui: () => {
          Object.assign(etat, { actif: true, debut: Date.now(), fin: duree ? new Date(Date.now() + duree * 60000) : null, message: zone.value.trim() });
          etat.historique.unshift({ debut: 'Maintenant', duree: '', message: etat.message || 'Message par défaut', par: 'Mawuli Adjovi' });
          toast('Site fermé au public. Le personnel connecté garde son accès.', 'alerte');
          rendre();
        },
      }));

      $('[data-rouvrir]', racine)?.addEventListener('click', () => confirmer({
        titre: 'Rouvrir le service ?',
        texte: `Le site redevient accessible immédiatement. ${etat.attente} courriel(s) partiront vers les personnes qui ont demandé à être prévenues. Un courriel parti ne se rappelle pas.`,
        bouton: 'Rouvrir et prévenir', ton: 'primaire',
        siOui: () => {
          etat.historique[0].duree = `${Math.max(1, Math.round((Date.now() - etat.debut) / 60000))} min`;
          Object.assign(etat, { actif: false, debut: null, fin: null });
          toast(`Site rouvert. ${etat.attente} personne(s) prévenue(s) par courriel.`);
          etat.attente = 0;
          rendre();
        },
      }));
    };

    rendre();
  };
})(window.VF);
