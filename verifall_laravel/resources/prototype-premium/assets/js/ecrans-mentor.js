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

  /*
   | Le tableau de bord du mentor : le sien, pas une variante de celui de
   | l'administration. Ce qu'on retient, c'est « Ma journée » : une frise de
   | 8 h à 22 h où se placent le live, les échéances de correction et les
   | vérifications assignées. Le reste répond à « qui a besoin de moi ? ».
   */
  VF.ecrans['m-tableau-de-bord'] = (racine) => {
    G.detruireTout();
    const DEBUT = 8, FIN = 22, MAINTENANT = 11.5;
    const journee = [
      [9, 0.75, 'pen-line', 'Échéance — copie de Kossi Tossou', 'Corriger avant 9 h 45', 'critique', '#/m-devoirs'],
      [11, 1, 'search-check', 'Vérification SIG-4822 assignée', 'Reste 2 h 20 sur l\'objectif de 6 h', 'alerte', '#/signalements'],
      [14, 1, 'messages-square', 'Permanence forum', '3 questions sans réponse', 'info', '#/m-forum-discussions'],
      [18, 1.5, 'video', 'Live — OSINT pas à pas', '214 inscrits', 'live', '#/m-live'],
    ];
    const tons = { critique: 'bg-red-50 border-red-200 text-red-900 dark:bg-red-950/40 dark:border-red-900/60 dark:text-red-100', alerte: 'bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-950/30 dark:border-amber-900/60 dark:text-amber-100', info: 'bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/30 dark:border-blue-900/60 dark:text-blue-100', live: 'bg-brand-50 border-brand/40 text-foret dark:bg-brand/10 dark:border-brand/40 dark:text-white' };
    const pos = (h) => ((h - DEBUT) / (FIN - DEBUT)) * 100;
    const coursStats = mesCours.map((c, i) => ({ ...c, actifs: Math.round(c.inscrits * [0.31, 0.42, 0.18][i % 3]), tendance: Array.from({ length: 12 }, (_, k) => Math.round(c.inscrits / 40 + k * (i + 1) + VF.outils.entre(0, 9))) }));
    const assignees = VF.signalements.filter((s) => s.etat !== 'publie').slice(0, 4);
    const questions = [['Exercice Whois : je bloque à l\'étape 3', 'Entraide Académie', 'il y a 40 min', 'Gbaguidi Afi'], ['Comment savoir si une image est générée par IA ?', 'Questions de vérification', 'il y a 2 h', 'Ismaël Sanni'], ['Le quiz 4 compte-t-il pour le certificat ?', 'Entraide Académie', 'hier', 'Carine Dossou']];

    racine.innerHTML = `
      <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
          <p class="text-sm texte-2">Jeudi 15 octobre</p>
          <h1 class="text-[28px] sm:text-[32px] font-semibold leading-tight">Bonjour Fifamè, 4 rendez-vous aujourd'hui</h1>
          <p class="mt-1 text-sm texte-2 max-w-2xl">Une copie est urgente avant 9 h 45. Votre live commence à 18 h.</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="#/m-profil" class="btn-second">${icone('id-card')}Mon profil</a><a href="#/m-devoirs" class="btn-primaire">${icone('pen-line')}Corriger maintenant</a></div>
      </div>

      <section class="carte p-5 mb-6" aria-labelledby="t-journee">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4"><h2 id="t-journee" class="carte-titre">Ma journée</h2><p class="text-xs texte-3">De 8 h à 22 h — la ligne verte marque l'heure actuelle</p></div>
        <div class="hidden md:block relative h-[150px]">
          <div class="absolute inset-x-0 top-6 border-t filet"></div>
          ${Array.from({ length: (FIN - DEBUT) / 2 + 1 }, (_, i) => DEBUT + i * 2).map((h) => `<span class="absolute top-0 ${h === FIN ? '-translate-x-full' : h === DEBUT ? '' : '-translate-x-1/2'} text-[11px] texte-3 tabular-nums whitespace-nowrap" style="left:${pos(h)}%">${h} h</span><span class="absolute top-5 h-2 border-l filet" style="left:${pos(h)}%"></span>`).join('')}
          <span class="absolute top-4 bottom-0 w-0.5 bg-brand rounded-full" style="left:${pos(MAINTENANT)}%" aria-hidden="true"><span class="absolute -top-1 -left-[3px] w-2 h-2 rounded-full bg-brand"></span></span>
          ${journee.map(([h, d, ic, titre, detail, ton, lien], i) => `<a href="${lien}" class="absolute top-10 rounded-ctrl border px-2.5 py-2 hover:shadow-flottant transition-shadow ${tons[ton]} ${h + d < MAINTENANT ? 'opacity-60' : ''}" style="left:${pos(h)}%;width:max(${((d) / (FIN - DEBUT)) * 100}%,210px);margin-top:${(i % 2) * 46}px">
            <span class="flex items-center gap-1.5 text-[13px] font-medium leading-tight">${icone(ic, 'w-3.5 h-3.5 shrink-0')}<span class="truncate">${esc(titre)}</span></span>
            <span class="block mt-0.5 text-xs opacity-80 truncate">${String(Math.floor(h)).padStart(2, '0')} h ${String(Math.round((h % 1) * 60)).padStart(2, '0')} — ${esc(detail)}</span></a>`).join('')}
        </div>
        <ol class="md:hidden space-y-2">${journee.map(([h, , ic, titre, detail, ton, lien]) => `<li><a href="${lien}" class="flex items-start gap-3 rounded-ctrl border px-3 py-2.5 ${tons[ton]}"><span class="text-xs font-semibold tabular-nums w-10 shrink-0 pt-0.5">${Math.floor(h)} h${h % 1 ? Math.round((h % 1) * 60) : ''}</span><span class="min-w-0"><span class="flex items-center gap-1.5 text-sm font-medium">${icone(ic, 'w-3.5 h-3.5')}${esc(titre)}</span><span class="block text-xs opacity-80">${esc(detail)}</span></span></a></li>`).join('')}</ol>
      </section>

      <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        ${kpi({ libelle: 'Apprenants actifs', valeur: fmt.nombre(1342), delta: 8, aide: 'sur 30 jours', ic: 'users' })}
        ${kpi({ libelle: 'Copies à corriger', valeur: '5', aide: 'délai moyen : 11 h', ic: 'pen-line' })}
        ${kpi({ libelle: 'Complétion de mes cours', valeur: '60 %', delta: 4, ic: 'circle-check' })}
        ${kpi({ libelle: 'Note des apprenants', valeur: '4,8', aide: 'sur 5 — 412 avis', ic: 'star' })}
      </div>

      <div class="grid lg:grid-cols-3 gap-4 mb-4">
        <section class="carte lg:col-span-2 overflow-hidden">
          <div class="carte-tete"><div><h2 class="carte-titre">Boîte de correction</h2><p class="text-xs texte-3 mt-0.5">Les urgentes et les plus anciennes d'abord</p></div><a href="#/m-devoirs" class="btn-fantome btn-sm">Tout voir</a></div>
          <ul class="divide-y divide-trait dark:divide-nuit-trait">${VF.copies.map((c) => `<li class="flex items-center gap-3 px-5 py-3"><span class="avatar w-9 h-9">${esc(c.initiales)}</span><div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">${esc(c.apprenant)} — ${esc(c.devoir)}</p><p class="text-xs texte-3 truncate">${esc(c.cours)}, soumis ${esc(c.soumis)}</p></div>${c.urgence === 'urgent' ? pastille('p-critique', 'clock-alert', 'Urgent') : c.urgence === 'en_cours' ? pastille('p-info', 'pen-line', 'Entamée') : pastille('p-neutre', 'inbox', 'Nouveau')}<a href="#/m-devoirs" class="btn-second btn-sm hidden sm:inline-flex">Corriger</a></li>`).join('')}</ul>
        </section>
        ${carte({ titre: 'Vérifications assignées', aide: 'Objectif : verdict en 6 h', actions: '<a href="#/signalements" class="btn-fantome btn-sm">File</a>', corps: `<ul class="space-y-3">${assignees.map((s) => `<li class="flex items-start gap-3"><span class="mt-0.5 ${s.echeance < 120 ? 'text-red-700 dark:text-red-400' : 'texte-3'}">${icone(s.echeance < 120 ? 'clock-alert' : 'clock', 'w-4 h-4')}</span><div class="min-w-0"><p class="text-sm font-medium line-clamp-2">${esc(s.texte)}</p><p class="text-xs texte-3 mt-0.5"><span class="${s.echeance < 120 ? 'text-red-700 dark:text-red-400 font-medium' : ''}">reste ${fmt.duree(s.echeance)}</span> — ${esc(s.id)}</p></div></li>`).join('')}</ul>` })}
      </div>

      <section class="carte mb-4">
        <div class="carte-tete"><div><h2 class="carte-titre">Mes cours en un coup d'œil</h2><p class="text-xs texte-3 mt-0.5">Inscriptions des 12 dernières semaines</p></div><a href="#/m-cours" class="btn-fantome btn-sm">Mes cours</a></div>
        <div class="overflow-x-auto"><table class="tableau"><thead><tr><th>Cours</th><th>Inscrits</th><th>Actifs (7 j)</th><th>Complétion</th><th>Note</th><th>Tendance</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
          ${coursStats.map((c, i) => `<tr><td><div class="flex items-center gap-3"><img src="${esc(c.image)}" alt="" class="w-12 h-9 rounded-md object-cover"><div><p class="font-medium">${esc(c.titre)}</p><p class="text-xs texte-3">${esc(c.niveau)} — ${c.lecons} leçons</p></div></div></td><td class="tabular-nums">${fmt.nombre(c.inscrits)}</td><td class="tabular-nums">${fmt.nombre(c.actifs)}</td><td><div class="flex items-center gap-2 min-w-[120px]"><div class="jauge"><span style="width:${c.completion}%"></span></div><span class="text-xs tabular-nums texte-2 whitespace-nowrap">${c.completion} %</span></div></td><td><span class="inline-flex items-center gap-1">${icone('star', 'w-3.5 h-3.5 text-amber-500 fill-amber-400')}${String(c.note).replace('.', ',')}</span></td><td><div id="g-m-tend-${i}" class="w-28"></div></td><td class="text-right"><a href="#/m-sections" class="btn-second btn-sm">Ouvrir</a></td></tr>`).join('')}
        </tbody></table></div>
      </section>

      <div class="grid lg:grid-cols-3 gap-4 mb-4">
        ${G.carte({ id: 'g-m-activite', titre: 'Activité de mes apprenants', aide: 'Leçons terminées par jour', hauteur: 240, classe: 'lg:col-span-2' })}
        ${carte({ titre: 'À risque de décrochage', aide: 'Aucune activité depuis 14 jours', corps: `<ul class="space-y-3">${VF.apprenants.filter((a) => a.etat === 'inactif').slice(0, 5).map((a) => `<li class="flex items-center gap-3"><a href="${VF.lienProfil ? VF.lienProfil('m-profil-citoyen', a) : '#/m-apprenants'}" class="avatar w-8 h-8" aria-label="Fiche de ${esc(a.nom)}">${esc(a.initiales)}</a><div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">${esc(a.nom)}</p><p class="text-xs texte-3">${a.progression} % — ${fmt.date(a.derniere)}</p></div><button class="btn-second btn-sm" data-relancer="${esc(a.nom)}">Relancer</button></li>`).join('')}</ul>` })}
      </div>

      <div class="grid lg:grid-cols-2 gap-4">
        ${carte({ titre: 'Questions du forum sans réponse', aide: 'Dans les groupes que vous animez', actions: '<a href="#/m-forum-discussions" class="btn-fantome btn-sm">Forum</a>', corps: `<ul class="space-y-3">${questions.map(([q, g, quand, qui]) => `<li class="flex items-start gap-3">${icone('message-circle-question', 'w-4 h-4 mt-0.5 texte-3 shrink-0')}<div class="min-w-0 flex-1"><p class="text-sm font-medium">${esc(q)}</p><p class="text-xs texte-3">${esc(qui)} dans ${esc(g)}, ${esc(quand)}</p></div><a href="#/m-forum-discussions" class="btn-second btn-sm">Répondre</a></li>`).join('')}</ul>` })}
        ${carte({ titre: 'Prochaines sessions', actions: '<a href="#/m-live" class="btn-fantome btn-sm">Calendrier</a>', corps: `<ul class="space-y-3">${VF.evenements.filter((e) => e.type === 'live' && new Date(e.start) >= new Date(2026, 9, 15)).sort((a, b) => new Date(a.start) - new Date(b.start)).map((e) => { const d = new Date(e.start); return `<li class="flex items-center gap-3 p-3 rounded-ctrl border filet"><span class="inline-flex w-9 h-9 items-center justify-center rounded-ctrl bg-brand-50 dark:bg-brand/10 text-brand-700 dark:text-brand">${icone('video', 'w-4 h-4')}</span><div class="min-w-0"><p class="text-sm font-medium truncate">${esc(e.title.replace('Live — ', ''))}</p><p class="text-xs texte-3">${new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(d)}</p></div></li>`; }).join('')}</ul>` })}
      </div>`;

    const x = Array.from({ length: 30 }, (_, i) => { const d = new Date(2026, 9, 15); d.setDate(d.getDate() - (29 - i)); return fmt.jourCourt(d); });
    const s = VF.series.inscriptions.map((v) => Math.round(v * 1.6));
    G.monter('g-m-activite', 'area', { series: [{ name: 'Leçons terminées', data: s }], colors: [G.couleurs()[0]], xaxis: { categories: x, tickAmount: 6 }, legend: { show: false }, fill: { type: 'gradient', gradient: { opacityFrom: 0.18, opacityTo: 0, stops: [0, 100] } } }, { hauteur: 240, tableau: { colonnes: ['Jour', 'Leçons terminées'], lignes: x.map((j, i) => [j, s[i]]) } });
    coursStats.forEach((c, i) => G.etincelle('g-m-tend-' + i, c.tendance));
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
