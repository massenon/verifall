/*
 | L'espace mentor. Il réutilise les mêmes briques que l'administration, mais
 | ne montre que ce qui appartient au mentor connecté : ses cours, ses
 | apprenants, ses copies.
 */
(function (VF) {
  'use strict';
  const { $, $$, esc, fmt, icone, icones, pastille, kpi, enTete, carte, toast } = VF.ui;
  const G = VF.g;
  const { entre, choisir, personne, jours } = VF.outils;
  VF.ecrans = VF.ecrans || {};
  const S = VF.statuts;
  const mesCours = VF.cours.filter((c) => c.mentor === 'Fifamè Houngbédji');

  VF.ecrans['m-tableau-de-bord'] = (racine) => {
    G.detruireTout();
    racine.innerHTML = `
      ${enTete({ titre: 'Bonjour Fifamè', sousTitre: '5 copies attendent votre correction, dont une urgente. Votre prochain live commence ce soir à 18 h.', actions: `<a href="#/m-devoirs" class="btn-primaire">${icone('pen-line')}Corriger maintenant</a>` })}
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        ${kpi({ libelle: 'Mes apprenants', valeur: fmt.nombre(1342), delta: 8, aide: 'actifs sur 30 jours', ic: 'users' })}
        ${kpi({ libelle: 'Copies à corriger', valeur: '5', aide: 'délai moyen : 11 h', ic: 'pen-line' })}
        ${kpi({ libelle: 'Complétion de mes cours', valeur: '60 %', delta: 4, ic: 'circle-check' })}
        ${kpi({ libelle: 'Note des apprenants', valeur: '4,8', aide: 'sur 5 — 412 avis', ic: 'star' })}
      </div>
      <div class="grid lg:grid-cols-3 gap-4 mb-4">
        <section class="carte lg:col-span-2 overflow-hidden">
          <div class="carte-tete"><div><h2 class="carte-titre">Boîte de correction</h2><p class="text-xs texte-3 mt-0.5">Les plus anciennes et les urgentes d'abord</p></div><a href="#/m-devoirs" class="btn-fantome btn-sm">Tout voir</a></div>
          <ul class="divide-y divide-trait dark:divide-nuit-trait">${VF.copies.map((c) => `<li class="flex items-center gap-3 px-5 py-3"><span class="avatar w-9 h-9">${esc(c.initiales)}</span><div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">${esc(c.apprenant)} — ${esc(c.devoir)}</p><p class="text-xs texte-3 truncate">${esc(c.cours)}, soumis ${esc(c.soumis)}</p></div>${c.urgence === 'urgent' ? pastille('p-critique', 'clock-alert', 'Urgent') : c.urgence === 'en_cours' ? pastille('p-info', 'pen-line', 'Entamée') : pastille('p-neutre', 'inbox', 'Nouveau')}<a href="#/m-devoirs" class="btn-second btn-sm hidden sm:inline-flex">Ouvrir</a></li>`).join('')}</ul>
        </section>
        ${carte({ titre: 'Prochaines sessions', actions: `<a href="#/m-live" class="btn-fantome btn-sm">Calendrier</a>`, corps: `<ul class="space-y-3">${VF.evenements.filter((e) => e.type === 'live' && new Date(e.start) >= new Date(2026, 9, 15)).sort((a, b) => new Date(a.start) - new Date(b.start)).map((e) => { const d = new Date(e.start); return `<li class="flex items-center gap-3 p-3 rounded-ctrl border filet"><span class="inline-flex w-9 h-9 items-center justify-center rounded-ctrl bg-brand-50 dark:bg-brand/10 text-brand-700 dark:text-brand">${icone('video', 'w-4 h-4')}</span><div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">${esc(e.title.replace('Live — ', ''))}</p><p class="text-xs texte-3">${new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(d)}</p></div></li>`; }).join('')}</ul>` })}
      </div>
      <div class="grid lg:grid-cols-3 gap-4">
        ${G.carte({ id: 'g-m-activite', titre: 'Activité de mes apprenants', aide: 'Leçons terminées par jour', hauteur: 240, classe: 'lg:col-span-2' })}
        ${carte({ titre: 'À risque de décrochage', aide: 'Aucune activité depuis 14 jours', corps: `<ul class="space-y-3">${VF.apprenants.filter((a) => a.etat === 'inactif').slice(0, 5).map((a) => `<li class="flex items-center gap-3"><span class="avatar w-8 h-8">${esc(a.initiales)}</span><div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">${esc(a.nom)}</p><p class="text-xs texte-3">${a.progression} % — ${fmt.date(a.derniere)}</p></div><button class="btn-second btn-sm" data-relancer="${esc(a.nom)}">Relancer</button></li>`).join('')}</ul>` })}
      </div>`;
    const x = Array.from({ length: 30 }, (_, i) => { const d = new Date(2026, 9, 15); d.setDate(d.getDate() - (29 - i)); return fmt.jourCourt(d); });
    const s = VF.series.inscriptions.map((v) => Math.round(v * 1.6));
    G.monter('g-m-activite', 'area', { series: [{ name: 'Leçons terminées', data: s }], colors: [G.couleurs()[0]], xaxis: { categories: x, tickAmount: 6 }, legend: { show: false }, fill: { type: 'gradient', gradient: { opacityFrom: 0.18, opacityTo: 0, stops: [0, 100] } } }, { hauteur: 240, tableau: { colonnes: ['Jour', 'Leçons terminées'], lignes: x.map((j, i) => [j, s[i]]) } });
    $$('[data-relancer]', racine).forEach((b) => b.addEventListener('click', () => { b.disabled = true; b.textContent = 'Relancé'; toast(`Relance envoyée à ${b.dataset.relancer}.`); }));
    icones();
  };

  VF.ecrans['m-cours'] = (racine) => {
    racine.innerHTML = `
      ${enTete({ titre: 'Mes cours', sousTitre: 'Les cours dont vous êtes responsable.', fil: ['Espace pédagogique'] })}
      <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">${mesCours.map((c) => `<a href="#/m-sections" class="block hover:-translate-y-0.5 transition-transform">${VF.carteCours(c)}</a>`).join('')}
        <a href="#/m-sections" class="carte border-dashed flex flex-col items-center justify-center gap-2 min-h-[300px] texte-2 hover:border-brand/60 transition-colors">${icone('plus', 'w-6 h-6')}<span class="font-medium">Proposer un nouveau cours</span><span class="text-xs texte-3">Il sera relu par l'équipe de l'Académie</span></a>
      </div>`;
    icones();
  };

  const definir = (id, def) => { VF.ecrans[id] = (racine) => VF.gestion.ecran(racine, { id, fil: ['Espace pédagogique'], ...def }); };

  definir('m-contenus', {
    titre: 'Mes contenus', sousTitre: 'Vidéos, supports PDF et images utilisés dans vos leçons.', entite: 'contenu',
    colonnes: [{ cle: 'nom', libelle: 'Fichier', type: 'titre', sous: 'cours' }, { cle: 'type', libelle: 'Type', type: 'pastille', etats: { video: { classe: 'p-info', ic: 'circle-play', libelle: 'Vidéo' }, pdf: { classe: 'p-neutre', ic: 'file-text', libelle: 'PDF' }, image: { classe: 'p-bon', ic: 'image', libelle: 'Image' } } }, { cle: 'taille', libelle: 'Taille', muet: true }, { cle: 'date', libelle: 'Ajouté le', type: 'date' }],
    filtres: [{ cle: 'type', libelle: 'Type', options: [{ valeur: 'video', libelle: 'Vidéo' }, { valeur: 'pdf', libelle: 'PDF' }, { valeur: 'image', libelle: 'Image' }] }],
    lignes: [['intro-osint.mp4', 'video'], ['fiche-methode-recherche-inversee.pdf', 'pdf'], ['exemple-image-sortie-de-contexte.webp', 'image'], ['whois-pas-a-pas.mp4', 'video'], ['grille-evaluation.pdf', 'pdf'], ['capture-exif.webp', 'image'], ['deepfake-indices.mp4', 'video']].map(([nom, type]) => ({ nom, type, cours: choisir(mesCours).titre, taille: type === 'video' ? entre(40, 380) + ' Mo' : entre(120, 2400) + ' Ko', date: jours(entre(1, 90)) })),
    champs: [{ nom: 'nom', libelle: 'Nom du fichier', regles: 'requis' }, { nom: 'cours', libelle: 'Cours', options: mesCours.map((c) => c.titre) }, { nom: 'type', libelle: 'Type', options: ['video', 'pdf', 'image'] }],
    actionsLigne: (l) => [{ libelle: 'Aperçu', ic: 'eye', action: () => { const lb = GLightbox({ elements: [l.type === 'image' ? { href: VF.medias[5].fichier, type: 'image', title: l.nom } : { content: `<div style="padding:2rem;font-family:Public Sans,sans-serif;background:#fff;color:#142019;border-radius:12px"><p style="font-weight:600">${esc(l.nom)}</p><p style="margin-top:.5rem">Aperçu ${l.type === 'video' ? 'vidéo' : 'PDF'} servi par lien signé.</p></div>`, width: '480px', height: 'auto' }] }); lb.open(); } }, { libelle: 'Copier le lien signé', ic: 'link', action: () => toast('Lien signé copié. Il expire dans 30 minutes.') }],
  });

  definir('m-quiz', {
    titre: 'Quiz', entite: 'quiz',
    colonnes: [{ cle: 'titre', libelle: 'Quiz', type: 'titre', sous: 'cours' }, { cle: 'questions', libelle: 'Questions', type: 'nombre' }, { cle: 'tentatives', libelle: 'Tentatives', type: 'nombre' }, { cle: 'reussite', libelle: 'Réussite', type: 'jauge' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.publication }],
    lignes: ['Quiz d\'entrée OSINT', 'Recherche inversée', 'Métadonnées et EXIF', 'Comptes et domaines', 'Indices de deepfake', 'Quiz final OSINT'].map((titre, i) => ({ titre, cours: choisir(mesCours).titre, questions: entre(5, 15), tentatives: entre(40, 900), reussite: entre(52, 90), etat: i === 4 ? 'brouillon' : 'publie' })),
    champs: [{ nom: 'titre', libelle: 'Titre', regles: 'requis' }, { nom: 'cours', libelle: 'Cours', options: mesCours.map((c) => c.titre) }, { nom: 'seuil', libelle: 'Score de réussite (%)', type: 'number', valeur: '70' }],
    actionsLigne: () => [{ libelle: 'Choisir les questions', ic: 'list-checks', action: () => toast('Banque de questions ouverte.', 'info') }, { libelle: 'Voir les résultats', ic: 'chart-column', action: () => (location.hash = '#/m-progression') }],
  });

  definir('m-examens', {
    titre: 'Mes examens', entite: 'examen',
    colonnes: [{ cle: 'titre', libelle: 'Examen', type: 'titre', sous: 'cours' }, { cle: 'date', libelle: 'Date', type: 'date' }, { cle: 'inscrits', libelle: 'Inscrits', type: 'nombre' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: { attente: { classe: 'p-info', ic: 'calendar-clock', libelle: 'Programmé' }, actif: { classe: 'p-bon', ic: 'play', libelle: 'En cours' }, termine: { classe: 'p-neutre', ic: 'flag', libelle: 'Terminé' } } }],
    lignes: [['Examen final — OSINT Fondamentaux', -9, 'attente'], ['Contrôle — Image virale', -2, 'attente'], ['Examen — Deepfakes', 12, 'termine'], ['Examen blanc — OSINT', 30, 'termine']].map(([titre, j, etat]) => ({ titre, cours: choisir(mesCours).titre, date: jours(j), inscrits: entre(30, 300), etat })),
    champs: [{ nom: 'titre', libelle: 'Intitulé', regles: 'requis' }, { nom: 'date', libelle: 'Date et heure', attrs: 'data-date-heure', regles: 'requis' }, { nom: 'duree', libelle: 'Durée (minutes)', type: 'number', valeur: '90' }],
  });

  definir('m-apprenants', {
    titre: 'Mes apprenants', sousTitre: 'Leur progression, leur moyenne, et ceux à relancer.', entite: 'apprenant',
    colonnes: [{ cle: 'qui', libelle: 'Apprenant', type: 'personne' }, { cle: 'cours', libelle: 'Cours', muet: true }, { cle: 'progression', libelle: 'Progression', type: 'jauge' }, { cle: 'moyenne', libelle: 'Moyenne', muet: true }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.activite }, { cle: 'derniere', libelle: 'Dernière activité', type: 'date' }],
    filtres: [{ cle: 'etat', libelle: 'État', options: [{ valeur: 'actif', libelle: 'Actif' }, { valeur: 'inactif', libelle: 'Inactif' }, { valeur: 'termine', libelle: 'Terminé' }] }],
    lignes: VF.apprenants.map((a) => ({ qui: a, cours: a.cours, progression: a.progression, moyenne: a.moyenne + ' / 20', etat: a.etat, derniere: a.derniere })),
    actionsLigne: (l) => [{ libelle: 'Voir la fiche de l\'apprenant', ic: 'id-card', action: () => (location.hash = VF.lienProfil('m-profil-citoyen', l.qui)) }, { libelle: 'Envoyer un message', ic: 'message-square', action: () => toast('Message envoyé à ' + l.qui.nom + '.') }, { libelle: 'Relancer', ic: 'send', action: () => toast('Relance envoyée.') }],
    actionsGroupees: [{ libelle: 'Relancer', ic: 'send' }, { libelle: 'Envoyer un message', ic: 'message-square' }],
    supprimable: false,
  });

  definir('m-certificats', {
    titre: 'Certificats de mes apprenants', entite: 'certificat', supprimable: false,
    colonnes: [{ cle: 'qui', libelle: 'Titulaire', type: 'personne' }, { cle: 'cours', libelle: 'Cours', muet: true }, { cle: 'code', libelle: 'Code', type: 'code' }, { cle: 'date', libelle: 'Délivré le', type: 'date' }],
    lignes: Array.from({ length: 12 }, () => ({ qui: personne(), cours: choisir(mesCours).titre, code: 'VF-' + entre(100000, 999999), date: jours(entre(1, 90)) })),
    actionsLigne: (l) => [{ libelle: 'Télécharger', ic: 'file-down', action: () => toast('Certificat ' + l.code + ' téléchargé.') }],
  });

  VF.ecrans['m-performance'] = (racine) => {
    G.detruireTout();
    racine.innerHTML = `
      ${enTete({ titre: 'Ma performance', sousTitre: 'Comment vos apprenants vivent vos cours, et votre réactivité.', fil: ['Espace pédagogique'] })}
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        ${kpi({ libelle: 'Note moyenne', valeur: '4,8 / 5', delta: 2, ic: 'star' })}
        ${kpi({ libelle: 'Délai de correction', valeur: '11 h', delta: -14, sens: 'bas-bon', aide: 'objectif : 24 h', ic: 'timer' })}
        ${kpi({ libelle: 'Présence aux lives', valeur: '68 %', delta: 5, ic: 'video' })}
        ${kpi({ libelle: 'Points du mois', valeur: fmt.nombre(7420), aide: 'quota : 8 000', ic: 'trophy' })}
      </div>
      <div class="grid lg:grid-cols-2 gap-4">
        ${G.carte({ id: 'g-m-notes', titre: 'Répartition des avis', aide: '412 avis', hauteur: 230 })}
        ${G.carte({ id: 'g-m-delai', titre: 'Délai de correction', aide: 'Heures, par semaine', hauteur: 230 })}
      </div>`;
    const notes = [['5 étoiles', 301], ['4 étoiles', 82], ['3 étoiles', 21], ['2 étoiles', 6], ['1 étoile', 2]];
    G.monter('g-m-notes', 'bar', { series: [{ name: 'Avis', data: notes.map((n) => n[1]) }], colors: [G.couleurs()[0]], plotOptions: { bar: { horizontal: true, barHeight: '56%' } }, xaxis: { categories: notes.map((n) => n[0]) }, legend: { show: false }, dataLabels: { enabled: true, textAnchor: 'start', offsetX: 6, style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] } }, tooltip: { shared: false, intersect: true } }, { hauteur: 230, tableau: { colonnes: ['Note', 'Avis'], lignes: notes } });
    const semaines = ['S36', 'S37', 'S38', 'S39', 'S40', 'S41', 'S42'], delai = [26, 22, 19, 17, 14, 12, 11];
    G.monter('g-m-delai', 'line', { series: [{ name: 'Délai (h)', data: delai }], colors: [G.couleurs()[0]], xaxis: { categories: semaines }, legend: { show: false }, markers: { size: 4, strokeColors: G.encre().surface, strokeWidth: 2 }, annotations: { yaxis: [{ y: 24, borderColor: G.encre().muet, label: { text: 'Objectif 24 h', borderWidth: 0, style: { background: 'transparent', color: G.encre().second, fontSize: '11px' } } }] } }, { hauteur: 230, tableau: { colonnes: ['Semaine', 'Délai (h)'], lignes: semaines.map((s, i) => [s, delai[i]]) } });
    icones();
  };

  /* Écrans partagés, vus depuis l'espace mentor. */
  VF.ecrans['m-sections'] = (r) => VF.ecrans['constructeur'](r, { mentor: true });
  VF.ecrans['m-devoirs'] = (r) => VF.ecrans['correction'](r, { mentor: true });
  VF.ecrans['m-progression'] = (r) => VF.ecrans['analytique-lms'](r, { mentor: true });
  VF.ecrans['m-live'] = (r) => VF.ecrans['calendrier'](r, { mentor: true });

  /* Hub d'infos et Communauté : les mêmes écrans que l'administration, ouverts
     depuis l'espace mentor (la liste est dans VF.MODULES_MENTOR, app.js). */
  ['hub-actualites', 'hub-portails', 'hub-jobs', 'forum-discussions', 'forum-categories', 'blog', 'blog-categories', 'editeur', 'mediatheque', 'newsletters', 'faq', 'temoignages', 'partenaires']
    .forEach((id) => { VF.ecrans['m-' + id] = (r, p) => VF.ecrans[id](r, p); });
})(window.VF);
