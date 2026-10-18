/*
 | Fiches complètes d'un citoyen et d'un mentor : l'identité, puis chaque
 | action de la personne, regroupée par domaine (vérifications, Académie, Hub,
 | forum, unités, connexions) et réunie dans un journal unique.
 |
 | La spécification est docs/specs/PRD_PROFILS_CITOYEN_MENTOR.md. Les données
 | sont fictives mais stables : elles dérivent du nom de la personne, si bien
 | qu'une fiche rouverte montre les mêmes chiffres.
 */
(function (VF) {
  'use strict';
  const { $, $$, esc, fmt, icone, icones, pastille, kpi, enTete, carte, vide, toast, confirmer, onglets } = VF.ui;
  const G = VF.g;
  VF.ecrans = VF.ecrans || {};

  /** Lien vers une fiche : la personne voyage dans l'adresse, la fiche se partage. */
  VF.lienProfil = (ecran, p) => {
    const q = new URLSearchParams({ nom: p.nom || '' });
    if (p.email) q.set('email', p.email);
    if (p.ville) q.set('ville', p.ville);
    return `#/${ecran}?${q}`;
  };

  /* ---------- Hasard stable, propre à chaque fiche ---------- */
  const generateur = (texte) => {
    let g = 0;
    for (const c of String(texte)) g = (g * 31 + c.charCodeAt(0)) >>> 0;
    const h = () => { g = (g * 1664525 + 1013904223) % 4294967296; return g / 4294967296; };
    return { h, entre: (a, b) => Math.round(a + h() * (b - a)), choisir: (l) => l[Math.floor(h() * l.length)] };
  };
  const auJour = (j, heure = 9, minute = 0) => { const d = new Date(2026, 9, 15, heure, minute); d.setDate(d.getDate() - j); return d; };
  const dateHeure = (d) => new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(d);
  const MAINTENANT = new Date(2026, 9, 15, 23, 10);
  const ilYa = (d) => {
    /* « Maintenant », dans le prototype : le 15 octobre 2026 en fin de soirée, après la dernière action fictive. */
    const min = Math.round((MAINTENANT - d) / 60000);
    if (min < 60) return `il y a ${Math.max(1, min)} min`;
    if (min < 1440) return `il y a ${Math.round(min / 60)} h`;
    const j = Math.round(min / 1440);
    return j === 1 ? 'hier' : `il y a ${j} jours`;
  };
  const initialesDe = (nom) => nom.split(' ').map((m) => m[0]).join('').slice(0, 2).toUpperCase();
  const emailDe = (nom) => nom.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, '.') + '@mail.bj';

  /* ---------- Briques des fiches ---------- */

  const P = {
    reussie: ['p-bon', 'circle-check', 'Réussie'], echouee: ['p-critique', 'circle-x', 'Échouée'], bloquee: ['p-grave', 'shield-ban', 'Bloquée'],
    terminee: ['p-bon', 'circle-check', 'Terminée'], echec: ['p-critique', 'circle-x', 'Échec'], en_cours: ['p-info', 'loader', 'En cours'],
    termine: ['p-bon', 'flag', 'Terminé'], abandonne: ['p-neutre', 'circle-pause', 'Abandonné'], encours: ['p-info', 'play', 'En cours'],
    corrige: ['p-bon', 'check-check', 'Corrigé'], a_corriger: ['p-alerte', 'pen-line', 'À corriger'], retard: ['p-critique', 'clock-alert', 'En retard'],
    publie: ['p-bon', 'circle-check', 'Publié'], brouillon: ['p-neutre', 'file-pen-line', 'Brouillon'], revue: ['p-info', 'eye', 'En relecture'],
    oui: ['p-bon', 'check', 'Oui'], non: ['p-neutre', 'minus', 'Non'],
  };
  const etat = (cle) => { const e = P[cle] || ['p-neutre', 'circle', cle]; return pastille(e[0], e[1], e[2]); };
  const verdict = (cle) => { if (!cle) return '<span class="texte-3">—</span>'; const v = VF.verdicts[cle]; return pastille(v.classe, v.icone, v.libelle); };
  const jauge = (v) => `<div class="flex items-center gap-2 min-w-[120px]"><div class="jauge"><span style="width:${v}%"></span></div><span class="text-xs tabular-nums texte-2 w-11 text-right">${v} %</span></div>`;

  /**
   * Une liste filtrable et paginée, montée dans `cible`. Plus légère que le
   * moteur des écrans de gestion : elle vit dans un onglet de la fiche.
   */
  const liste = (cible, { colonnes, lignes, filtres = [], parPage = 8, nom = 'liste', vide: texteVide = 'Rien à afficher pour l\'instant.' }) => {
    const e = { q: '', f: {}, page: 1 };
    cible.innerHTML = `
      <div class="flex flex-wrap items-center gap-2 p-3 border-b filet">
        <label class="relative flex-1 min-w-[180px] max-w-xs"><span class="sr-only">Rechercher</span><span class="absolute left-3 top-1/2 -translate-y-1/2 texte-3">${icone('search', 'w-4 h-4')}</span><input data-l-q class="champ !h-9 pl-9" placeholder="Rechercher…"></label>
        ${filtres.map((f) => `<label class="sr-only" for="lf-${nom}-${f.cle}">${esc(f.libelle)}</label><select id="lf-${nom}-${f.cle}" data-l-f="${f.cle}" class="champ !h-9 !w-auto min-w-[140px]"><option value="">${esc(f.libelle)} : tous</option>${f.options.map(([v, l]) => `<option value="${esc(v)}">${esc(l)}</option>`).join('')}</select>`).join('')}
        <button class="btn-second btn-sm ml-auto" data-l-csv>${icone('download')}CSV</button>
      </div>
      <div data-l-corps></div>
      <div data-l-pied class="flex items-center justify-between gap-3 px-4 py-3 border-t filet text-sm"></div>`;
    const filtrees = () => lignes.filter((l) => (!e.q || colonnes.some((c) => String(c.texte ? c.texte(l) : '').toLowerCase().includes(e.q))) && Object.entries(e.f).every(([k, v]) => !v || String(l[k]) === v));
    const rendre = () => {
      const l = filtrees(), pages = Math.max(1, Math.ceil(l.length / parPage));
      e.page = Math.min(e.page, pages);
      const tranche = l.slice((e.page - 1) * parPage, e.page * parPage);
      $('[data-l-corps]', cible).innerHTML = l.length
        ? `<div class="overflow-x-auto"><table class="tableau"><thead><tr>${colonnes.map((c) => `<th>${esc(c.libelle)}</th>`).join('')}</tr></thead><tbody>${tranche.map((x) => `<tr>${colonnes.map((c) => `<td>${c.rendu(x)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`
        : vide({ ic: 'search-x', titre: 'Aucun résultat', texte: texteVide });
      $('[data-l-pied]', cible).innerHTML = `<p class="texte-2">${l.length ? (e.page - 1) * parPage + 1 : 0}–${Math.min(e.page * parPage, l.length)} sur ${fmt.nombre(l.length)}</p>
        <div class="flex items-center gap-1"><button class="btn-icone !w-8 !h-8" data-l-page="${e.page - 1}" ${e.page <= 1 ? 'disabled' : ''} aria-label="Page précédente">${icone('chevron-left')}</button><span class="px-2 tabular-nums texte-2">${e.page} / ${pages}</span><button class="btn-icone !w-8 !h-8" data-l-page="${e.page + 1}" ${e.page >= pages ? 'disabled' : ''} aria-label="Page suivante">${icone('chevron-right')}</button></div>`;
      icones();
    };
    $('[data-l-q]', cible).addEventListener('input', (ev) => { e.q = ev.target.value.toLowerCase(); e.page = 1; rendre(); });
    $$('[data-l-f]', cible).forEach((s) => s.addEventListener('change', () => { e.f[s.dataset.lF] = s.value; e.page = 1; rendre(); }));
    cible.addEventListener('click', (ev) => {
      const p = ev.target.closest('[data-l-page]');
      if (p) { e.page = +p.dataset.lPage; rendre(); }
      if (ev.target.closest('[data-l-csv]')) {
        const l = filtrees();
        const csv = [colonnes.map((c) => c.libelle).join(';')].concat(l.map((x) => colonnes.map((c) => `"${String(c.texte ? c.texte(x) : '').replace(/"/g, '""')}"`).join(';'))).join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
        a.download = `${nom}.csv`; a.click();
        toast(`${l.length} lignes exportées en CSV.`);
      }
    });
    rendre();
  };

  /** Le fil des actions : une icône par type, la date, le détail. */
  const TYPES = {
    verification: ['search-check', 'Vérification'], rapport: ['file-text', 'Rapport'], signalement: ['flame', 'Signalement'], academie: ['graduation-cap', 'Académie'],
    hub: ['compass', 'Hub d\'opportunités'], forum: ['messages-square', 'Forum'], unites: ['coins', 'Unités'], connexion: ['log-in', 'Connexion'], compte: ['user-cog', 'Compte'],
    cours: ['library', 'Cours'], correction: ['pen-line', 'Correction'], live: ['video', 'Live'],
  };
  const fil = (evenements) => `<ol class="relative border-l filet ml-2 space-y-5">${evenements.map((ev) => `
    <li class="pl-6 relative">
      <span class="absolute -left-[13px] top-0 inline-flex w-6 h-6 items-center justify-center rounded-full bg-white dark:bg-nuit-carte border filet ${ev.ton === 'critique' ? 'text-red-700 dark:text-red-400' : 'texte-2'}">${icone((TYPES[ev.type] || ['circle'])[0], 'w-3.5 h-3.5')}</span>
      <p class="text-sm"><span class="font-medium">${esc(ev.action)}</span>${ev.objet ? ` <span class="texte-2">— ${esc(ev.objet)}</span>` : ''}</p>
      <p class="text-xs texte-3 mt-0.5">${esc((TYPES[ev.type] || [, ev.type])[1])} · ${dateHeure(ev.date)} · ${ilYa(ev.date)}</p>
    </li>`).join('')}</ol>`;

  const fiche = (lignes) => `<dl class="divide-y divide-trait dark:divide-nuit-trait text-sm">${lignes.map(([k, v]) => `<div class="flex items-start justify-between gap-4 py-2.5"><dt class="texte-3 shrink-0">${esc(k)}</dt><dd class="text-right font-medium min-w-0 break-words">${v}</dd></div>`).join('')}</dl>`;

  const enTeteProfil = ({ p, sous, pastilles, meta, actions, fil: chemin }) => `
    ${chemin ? `<nav class="flex items-center gap-1.5 text-[13px] texte-3 mb-3" aria-label="Fil d'Ariane">${chemin.map(([l, h], i) => (i ? icone('chevron-right', 'w-3.5 h-3.5') : '') + (h ? `<a href="${h}" class="hover:underline">${esc(l)}</a>` : `<span>${esc(l)}</span>`)).join('')}</nav>` : ''}
    <section class="carte p-5 mb-6">
      <div class="flex flex-wrap items-start gap-5">
        <span class="relative"><span class="avatar w-20 h-20 !text-2xl">${esc(p.initiales)}</span>${p.enLigne ? '<span class="absolute bottom-1 right-1 w-3.5 h-3.5 rounded-full bg-bon ring-2 ring-white dark:ring-nuit-carte" title="En ligne"></span>' : ''}</span>
        <div class="flex-1 min-w-[220px]">
          <h1 class="text-[26px] font-semibold leading-tight">${esc(p.nom)}</h1>
          <p class="mt-0.5 texte-2">${esc(sous)}</p>
          <div class="mt-3 flex flex-wrap gap-2">${pastilles.join('')}</div>
          <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-[13px] texte-2">${meta.map(([ic, t]) => `<li class="inline-flex items-center gap-1.5">${icone(ic, 'w-4 h-4 texte-3')}${t}</li>`).join('')}</ul>
        </div>
        <div class="flex flex-wrap gap-2">${actions}</div>
      </div>
    </section>`;

  const barreOnglets = (liste, nom) => `<div class="onglets mb-6" role="tablist" aria-label="${esc(nom)}">${liste.map(([id, libelle, compte], i) => `<button class="onglet" role="tab" id="t-${id}" aria-selected="${i === 0}" aria-controls="o-${id}" data-onglet="${id}">${esc(libelle)}${compte !== undefined ? ` <span class="ml-1 text-[11px] px-1.5 rounded-full bg-papier dark:bg-nuit texte-2 tabular-nums">${compte}</span>` : ''}</button>`).join('')}</div>`;
  const panneau = (id, corps, premier) => `<div id="o-${id}" role="tabpanel" aria-labelledby="t-${id}" ${premier ? '' : 'hidden'}>${corps}</div>`;

  /** Monte les graphiques d'un onglet la première fois qu'il s'affiche (un graphique caché n'a pas de largeur). */
  const graphiquesParOnglet = (racine, montages) => {
    const faits = new Set();
    const monter = (id) => { if (faits.has(id) || !montages[id]) return; faits.add(id); montages[id](); icones(); };
    /* L'onglet ouvert s'inscrit dans l'adresse : un lien copié rouvre la fiche au même endroit. */
    onglets(racine, (id) => {
      monter(id);
      const [chemin, requete] = location.hash.split('?');
      const q = new URLSearchParams(requete || '');
      q.set('onglet', id);
      try { history.replaceState(null, '', `${chemin}?${q}`); } catch (e) { /* adresse non mise à jour */ }
    });
    return monter;
  };

  const xJours = Array.from({ length: 30 }, (_, i) => fmt.jourCourt(auJour(29 - i)));

  /* =================================================================
     Fiche citoyen
     ================================================================= */

  const donneesCitoyen = (nom, email, ville) => {
    const r = generateur(nom);
    const { entre, choisir, h } = r;
    const villes = ['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey-Calavi', 'Bohicon'];
    ville = ville || choisir(villes);
    const appareils = [['smartphone', 'Application Vérif\'All, Android 14'], ['monitor', 'Chrome 129 sur Windows 11'], ['smartphone', 'Safari sur iPhone'], ['laptop', 'Firefox sur Ubuntu']];
    const ip = () => `41.${entre(70, 90)}.${entre(0, 255)}.${entre(1, 254)}`;

    const connexions = Array.from({ length: 16 }, (_, i) => {
      const [ic, appareil] = i < 9 ? appareils[0] : choisir(appareils);
      const resultat = i === 3 || i === 11 ? 'echouee' : i === 12 ? 'bloquee' : 'reussie';
      return { date: auJour(i === 0 ? 0 : Math.round(i * 1.7), entre(7, 22), entre(0, 59)), ip: resultat === 'bloquee' ? `102.${entre(80, 99)}.${entre(0, 255)}.${entre(1, 254)}` : ip(), ic, appareil, lieu: resultat === 'bloquee' ? 'Lagos, Nigeria' : ville + ', Bénin', resultat, methode: choisir(['Mot de passe', 'Mot de passe', 'Google', 'Code par courriel']), motif: resultat === 'echouee' ? 'Mot de passe incorrect' : resultat === 'bloquee' ? '5 échecs consécutifs : verrou de 15 min' : '' };
    });

    const types = ['Texte', 'Image', 'Lien', 'Vidéo', 'Audio'];
    const motifsEchec = ['Unités insuffisantes', 'Moteur d\'analyse indisponible (quota)', 'Fichier illisible ou corrompu', 'Délai d\'analyse dépassé (120 s)', 'Lien inaccessible (erreur 403)'];
    const affirmations = VF.signalements.map((s) => s.texte).concat(['Une capture d\'écran attribuée au ministre de l\'Éducation', 'Un audio WhatsApp annonçant un couvre-feu à Parakou', 'Une vidéo de manifestation présentée comme récente']);
    const verifications = Array.from({ length: 22 }, (_, i) => {
      const statut = i === 2 || i === 9 || i === 15 ? 'echec' : i === 0 ? 'en_cours' : 'terminee';
      const v = statut === 'terminee' ? choisir(['faux', 'faux', 'faux', 'trompeur', 'trompeur', 'vrai', 'vrai', 'invérifiable']) : null;
      return { id: 'VER-' + (90410 - i * 7), contenu: affirmations[i % affirmations.length], type: choisir(types), canal: choisir(['Site web', 'Application', 'Tipline WhatsApp']), statut, verdict: v, confiance: v ? entre(64, 97) : null, unites: statut === 'echec' ? 0 : entre(2, 14), moteurs: statut === 'echec' ? entre(0, 3) : entre(5, 11), motif: statut === 'echec' ? motifsEchec[i % motifsEchec.length] : '', date: auJour(Math.round(i * 2.4), entre(8, 22), entre(0, 59)), rapport: statut === 'terminee' };
    });
    const rapports = verifications.filter((v) => v.rapport).map((v, i) => ({ id: 'RAP-' + v.id.slice(4), verif: v.id, titre: v.contenu, verdict: v.verdict, telechargements: entre(0, 6), partage: i % 3 === 0, vues: i % 3 === 0 ? entre(12, 900) : 0, date: v.date }));
    const signalements = VF.signalements.slice(0, 5).map((s, i) => ({ id: s.id, texte: s.texte, canal: s.canal, etat: s.etat, verdict: s.verdict, date: auJour(entre(1, 40)) }));

    const cours = VF.cours.slice(0, 4).map((c, i) => {
      const progression = [100, 72, 34, 8][i];
      const faites = Math.round(c.lecons * progression / 100);
      return { titre: c.titre, mentor: c.mentor, progression, lecons: `${faites} / ${c.lecons}`, temps: entre(40, 600), statut: progression === 100 ? 'termine' : i === 3 ? 'abandonne' : 'encours', derniere: i === 3 ? 'Leçon 2 — Pourquoi une rumeur circule' : progression === 100 ? 'Quiz final' : `Leçon ${faites + 1}`, inscrit: auJour(entre(30, 160)), actif: auJour(i === 3 ? 41 : entre(0, 9)), note: progression === 100 ? 5 : null, certificat: progression === 100 };
    });
    const quiz = ['Quiz d\'entrée OSINT', 'Recherche inversée', 'Métadonnées et EXIF', 'Comptes et domaines', 'Image virale — contrôle', 'Anatomie d\'une rumeur — étape 1'].map((titre, i) => { const score = entre(38, 100); return { titre, cours: cours[i % 3].titre, score, tentatives: score < 60 ? entre(2, 3) : 1, reussi: score >= 70, date: auJour(entre(2, 60)) }; });
    const devoirs = [['Enquête guidée sur un domaine', 'corrige', 16.5], ['Analyse d\'une image virale', 'corrige', 14], ['Cartographier une rumeur', 'a_corriger', null], ['Rapport d\'enquête final', 'retard', null]].map(([titre, statut, note], i) => ({ titre, cours: cours[i % 3].titre, statut, note, correcteur: statut === 'corrige' ? cours[i % 3].mentor : '—', date: auJour(entre(1, 30)) }));
    const examens = [{ titre: 'Examen final — OSINT Fondamentaux', score: 82, resultat: 'Admis', date: auJour(22) }, { titre: 'Contrôle — Image virale', score: 58, resultat: 'Ajourné, 2e tentative ouverte', date: auJour(6) }];
    const certificats = cours.filter((c) => c.certificat).map((c) => ({ cours: c.titre, code: 'VF-' + entre(100000, 999999), date: auJour(20) }));

    const hub = [
      ['actualite', 'Le Bénin lance une plateforme de signalement des arnaques', 'Lu en entier'], ['offre', 'Journaliste fact-checkeur — Banouto', 'Candidature envoyée'],
      ['portail', 'Service public — service-public.bj', 'Portail ouvert'], ['offre', 'Data analyst junior — INStaT', 'Ajoutée aux favoris'],
      ['actualite', 'Élections : la CENA publie le calendrier officiel', 'Lu en entier'], ['portail', 'ANIP — anip.bj', 'Portail ouvert'],
      ['offre', 'Chargé de communication — ONG Social Watch', 'Offre consultée'], ['actualite', 'Paludisme : nouvelle campagne de vaccination', 'Partagé sur WhatsApp'],
      ['offre', 'Formateur en éducation aux médias', 'Offre signalée comme suspecte'], ['portail', 'Impôts — impots.finances.bj', 'Portail ouvert'],
    ].map(([type, titre, action], i) => ({ type, titre, action, date: auJour(Math.round(i * 2.2), entre(8, 21), entre(0, 59)) }));

    const groupes = [['Questions de vérification', 'membre actif'], ['Rumeurs du moment', 'membre'], ['Entraide Académie', 'membre actif'], ['Élections', 'abonné']].map(([nomG, role]) => ({ nom: nomG, role, messages: entre(1, 48), reactions: entre(0, 160), depuis: auJour(entre(20, 300)) }));
    const forum = [
      ['discussion', 'A ouvert la discussion', 'Cette photo d\'inondation est-elle récente ?', 'Questions de vérification'], ['reponse', 'A répondu', 'Comment vérifier un numéro qui m\'appelle ?', 'Entraide Académie'],
      ['solution', 'Réponse acceptée comme solution', 'Les nouveaux billets ont-ils une puce ?', 'Rumeurs du moment'], ['reaction', 'A réagi « utile »', 'Partagez vos outils préférés', 'Entraide Académie'],
      ['reponse', 'A répondu', 'Le vaccin et la stérilité : d\'où vient la rumeur ?', 'Rumeurs du moment'], ['signale', 'Message signalé par un membre, puis rétabli par la modération', 'Liste noire des numéros d\'arnaque', 'Questions de vérification'],
      ['discussion', 'A ouvert la discussion', 'Exercice Whois : je bloque à l\'étape 3', 'Entraide Académie'], ['groupe', 'A rejoint le groupe', 'Élections', 'Élections'],
    ].map(([type, action, sujet, groupe], i) => ({ type, action, sujet, groupe, date: auJour(Math.round(i * 3.1), entre(8, 22), entre(0, 59)) }));

    const transactions = [['Dotation mensuelle', 50], ['Analyse de vérification', -6], ['Achat Mobile Money (MTN) — 1 000 FCFA', 120], ['Analyse de vérification', -9], ['Bonus de parrainage', 20], ['Analyse de vérification', -4], ['Badge « Œil de lynx »', 15], ['Analyse de vérification', -11]].map(([motif, montant], i) => ({ motif, montant, date: auJour(Math.round(i * 3.5)) }));
    const badges = [['Premier pas', 'footprints'], ['Œil de lynx', 'eye'], ['Pédagogue', 'presentation'], ['Certifié OSINT', 'award']].slice(0, entre(2, 4)).map(([n, ic]) => ({ nom: n, ic, date: auJour(entre(5, 200)) }));

    const statut = { kyc: choisir(['valide', 'valide', 'attente', 'aucun']), deuxFA: h() > 0.55, emailOk: true, telOk: h() > 0.25 };
    const solde = 50 + transactions.reduce((s, t) => s + t.montant, 0) + entre(0, 300);

    const journal = [
      ...connexions.map((c) => ({ type: 'connexion', action: c.resultat === 'reussie' ? 'Connexion réussie' : c.resultat === 'bloquee' ? 'Connexion bloquée' : 'Échec de connexion', objet: `${c.appareil}, ${c.lieu} (${c.ip})`, date: c.date, ton: c.resultat === 'reussie' ? '' : 'critique' })),
      ...verifications.map((v) => ({ type: 'verification', action: v.statut === 'echec' ? 'Vérification en échec' : v.statut === 'en_cours' ? 'Vérification envoyée' : `Vérification terminée : ${VF.verdicts[v.verdict].libelle}`, objet: `${v.id} — ${v.contenu}`, date: v.date, ton: v.statut === 'echec' ? 'critique' : '' })),
      ...rapports.filter((x) => x.partage).map((x) => ({ type: 'rapport', action: 'Rapport partagé par lien public', objet: x.id, date: new Date(+x.date + 3600e3) })),
      ...signalements.map((s) => ({ type: 'signalement', action: 'A signalé un contenu', objet: s.texte, date: s.date })),
      ...cours.map((c) => ({ type: 'academie', action: c.statut === 'termine' ? 'A terminé le cours' : 'A repris le cours', objet: c.titre, date: c.actif })),
      ...quiz.map((q) => ({ type: 'academie', action: `Quiz ${q.reussi ? 'réussi' : 'échoué'} (${q.score} %)`, objet: q.titre, date: q.date, ton: q.reussi ? '' : 'critique' })),
      ...devoirs.map((d) => ({ type: 'academie', action: d.statut === 'corrige' ? `Devoir corrigé : ${String(d.note).replace('.', ',')} / 20` : 'Devoir rendu', objet: d.titre, date: d.date })),
      ...hub.map((x) => ({ type: 'hub', action: x.action, objet: x.titre, date: x.date })),
      ...forum.map((f) => ({ type: 'forum', action: f.action, objet: `${f.sujet} (${f.groupe})`, date: f.date, ton: f.type === 'signale' ? 'critique' : '' })),
      ...transactions.map((t) => ({ type: 'unites', action: `${t.montant > 0 ? '+' : '−'}${Math.abs(t.montant)} unités`, objet: t.motif, date: t.date })),
      { type: 'compte', action: 'Mot de passe changé', objet: 'Depuis Mon compte', date: auJour(47, 19, 12) },
      { type: 'compte', action: 'Compte créé', objet: 'Inscription par le site web, code confirmé par courriel', date: auJour(entre(200, 380)) },
    ].sort((a, b) => b.date - a.date);

    const activite = ['verification', 'academie', 'forum', 'hub'].map((t) => xJours.map((_, i) => journal.filter((ev) => ev.type === t && Math.round((auJour(29 - i) - ev.date) / 864e5) === 0).length + (t === 'academie' ? Math.round(h() * 2) : 0)));

    return { nom, email: email || emailDe(nom), ville, initiales: initialesDe(nom), tel: `+229 01 ${entre(40, 99)} ${entre(10, 99)} ${entre(10, 99)} ${entre(10, 99)}`, inscrit: journal[journal.length - 1].date, source: choisir(['Site web', 'Application Android', 'Tipline WhatsApp']), langue: choisir(['Français', 'Français', 'Fɔ̀ngbè']), parrain: choisir(['—', 'Aïcha Bello', 'Kossi Tossou']), filleuls: entre(0, 7), points: entre(120, 2400), niveau: choisir(['Curieux', 'Vigilant', 'Sentinelle']), solde, statut, connexions, verifications, rapports, signalements, cours, quiz, devoirs, examens, certificats, hub, groupes, forum, transactions, badges, journal, activite };
  };

  const ecranCitoyen = (racine, params, { mentor = false } = {}) => {
    G.detruireTout();
    const d = donneesCitoyen(params.nom || 'Kossi Tossou', params.email, params.ville);
    const derniere = d.connexions.find((c) => c.resultat === 'reussie');
    const echecs = d.verifications.filter((v) => v.statut === 'echec');
    const terminees = d.verifications.filter((v) => v.statut === 'terminee');
    const faux = terminees.filter((v) => v.verdict === 'faux' || v.verdict === 'trompeur').length;
    const moyenneQuiz = Math.round(d.quiz.reduce((s, q) => s + q.score, 0) / d.quiz.length);
    const messages = d.groupes.reduce((s, g) => s + g.messages, 0);
    const kycP = { valide: pastille('p-bon', 'badge-check', 'KYC validé'), attente: pastille('p-alerte', 'clock', 'KYC en attente'), aucun: pastille('p-neutre', 'id-card', 'KYC non soumis') }[d.statut.kyc];

    const ONGLETS = mentor
      ? [['ensemble', 'Vue d\'ensemble'], ['academie', 'Académie', d.cours.length], ['communaute', 'Communauté', d.forum.length]]
      : [['ensemble', 'Vue d\'ensemble'], ['verifications', 'Vérifications', d.verifications.length], ['rapports', 'Rapports', d.rapports.length], ['academie', 'Académie', d.cours.length], ['hub', 'Hub d\'opportunités', d.hub.length], ['communaute', 'Communauté', d.forum.length], ['unites', 'Unités et badges'], ['securite', 'Connexions et sécurité', d.connexions.length], ['journal', 'Journal complet', d.journal.length]];
    const demande = ONGLETS.some((o) => o[0] === params.onglet) ? params.onglet : 'ensemble';

    const actions = mentor
      ? `<button class="btn-second" data-act="message">${icone('message-square')}Écrire</button><button class="btn-primaire" data-act="relancer">${icone('send')}Relancer</button>`
      : `<button class="btn-second" data-act="message">${icone('message-square')}Écrire</button><button class="btn-second" data-act="crediter">${icone('coins')}Créditer</button><button class="btn-icone border filet" data-act="plus" aria-label="Autres actions">${icone('ellipsis')}</button>`;

    const ensemble = `
      <div class="grid grid-cols-2 ${mentor ? 'xl:grid-cols-4' : 'md:grid-cols-3 2xl:grid-cols-6'} gap-4 mb-6">
        ${mentor ? '' : kpi({ libelle: 'Vérifications', valeur: fmt.nombre(d.verifications.length), aide: `dont ${echecs.length} en échec`, ic: 'search-check' })}
        ${mentor ? '' : kpi({ libelle: 'Contenus faux ou trompeurs', valeur: Math.round(faux / Math.max(1, terminees.length) * 100) + ' %', aide: `${faux} sur ${terminees.length} terminées`, ic: 'circle-x' })}
        ${kpi({ libelle: 'Cours suivis', valeur: String(d.cours.length), aide: `${d.cours.filter((c) => c.statut === 'termine').length} terminé, ${d.certificats.length} certificat`, ic: 'graduation-cap' })}
        ${kpi({ libelle: 'Moyenne aux quiz', valeur: moyenneQuiz + ' %', aide: `${d.quiz.filter((q) => q.reussi).length} quiz réussis sur ${d.quiz.length}`, ic: 'list-checks' })}
        ${kpi({ libelle: 'Messages au forum', valeur: String(messages), aide: `${d.groupes.length} groupes`, ic: 'messages-square' })}
        ${mentor ? kpi({ libelle: 'Temps d\'apprentissage', valeur: fmt.duree(d.cours.reduce((s, c) => s + c.temps, 0)), aide: 'tous cours confondus', ic: 'clock' }) : kpi({ libelle: 'Solde d\'unités', valeur: fmt.nombre(d.solde), aide: `${fmt.nombre(d.points)} points`, ic: 'coins' })}
      </div>
      <div class="grid xl:grid-cols-3 gap-4 mb-4">
        ${G.carte({ id: 'g-pc-activite', titre: 'Activité sur 30 jours', aide: 'Actions par jour et par domaine', hauteur: 250, classe: 'xl:col-span-2' })}
        ${mentor ? carte({ titre: 'Progression par cours', corps: `<ul class="space-y-4">${d.cours.map((c) => `<li><p class="text-sm font-medium truncate">${esc(c.titre)}</p><div class="mt-1.5">${jauge(c.progression)}</div></li>`).join('')}</ul>` }) : G.carte({ id: 'g-pc-verdicts', titre: 'Verdicts obtenus', aide: `${terminees.length} vérifications terminées`, hauteur: 250 })}
      </div>
      <div class="grid xl:grid-cols-3 gap-4">
        ${carte({ titre: 'Dernières actions', aide: 'Tous domaines confondus', classe: 'xl:col-span-2', actions: mentor ? '' : `<button class="btn-fantome btn-sm" data-aller="journal">Journal complet</button>`, corps: fil((mentor ? d.journal.filter((e) => ['academie', 'forum'].includes(e.type)) : d.journal).slice(0, 8)) })}
        ${carte({ titre: 'Identité et compte', corps: fiche([
          ['Courriel', `${esc(d.email)} ${d.statut.emailOk ? icone('badge-check', 'inline w-4 h-4 text-green-700 dark:text-bon') : ''}`],
          ...(mentor ? [] : [['Téléphone', `${esc(d.tel)} ${d.statut.telOk ? icone('badge-check', 'inline w-4 h-4 text-green-700 dark:text-bon') : '<span class="texte-3 font-normal">non vérifié</span>'}`]]),
          ['Ville', esc(d.ville)], ['Langue', esc(d.langue)], ['Inscrit le', fmt.date(d.inscrit)], ['Inscription par', esc(d.source)],
          ['Niveau', esc(d.niveau)],
          ...(mentor ? [] : [['Parrain', esc(d.parrain)], ['Filleuls', String(d.filleuls)], ['Double authentification', d.statut.deuxFA ? 'Activée' : 'Désactivée'], ['Vérification d\'identité', kycP]]),
          ['Dernière connexion', `${dateHeure(derniere.date)}<span class="block text-xs texte-3 font-normal">${esc(derniere.appareil)}</span>`],
        ]) })}
      </div>`;

    const html = {
      ensemble,
      verifications: `
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
          ${kpi({ libelle: 'Terminées', valeur: String(terminees.length), ic: 'circle-check' })}
          ${kpi({ libelle: 'En échec', valeur: String(echecs.length), aide: 'aucune unité débitée', ic: 'circle-x' })}
          ${kpi({ libelle: 'Unités consommées', valeur: fmt.nombre(d.verifications.reduce((s, v) => s + v.unites, 0)), ic: 'coins' })}
          ${kpi({ libelle: 'Confiance moyenne', valeur: Math.round(terminees.reduce((s, v) => s + v.confiance, 0) / terminees.length) + ' %', aide: 'de l\'analyse IA', ic: 'gauge' })}
        </div>
        ${echecs.length ? `<section class="carte p-4 mb-4 border-red-200 dark:border-red-900/50"><div class="flex items-start gap-3">${icone('triangle-alert', 'w-5 h-5 text-red-700 dark:text-red-400 mt-0.5 shrink-0')}<div class="min-w-0"><p class="font-medium">${echecs.length} vérifications en échec</p><ul class="mt-2 space-y-1.5 text-sm">${echecs.map((v) => `<li class="flex flex-wrap gap-x-2"><code class="text-xs px-1.5 py-0.5 rounded bg-papier dark:bg-nuit">${v.id}</code><span class="font-medium">${esc(v.motif)}</span><span class="texte-3">— ${fmt.date(v.date)}</span></li>`).join('')}</ul></div></div></section>` : ''}
        <section class="carte" data-liste="verifications"></section>`,
      rapports: `<section class="carte" data-liste="rapports"></section>`,
      academie: `
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
          ${kpi({ libelle: 'Progression moyenne', valeur: Math.round(d.cours.reduce((s, c) => s + c.progression, 0) / d.cours.length) + ' %', ic: 'trending-up' })}
          ${kpi({ libelle: 'Temps passé', valeur: fmt.duree(d.cours.reduce((s, c) => s + c.temps, 0)), ic: 'clock' })}
          ${kpi({ libelle: 'Devoirs rendus', valeur: `${d.devoirs.length}`, aide: `${d.devoirs.filter((x) => x.statut === 'corrige').length} corrigés`, ic: 'pen-line' })}
          ${kpi({ libelle: 'Certificats', valeur: String(d.certificats.length), ic: 'award' })}
        </div>
        <section class="carte mb-4"><div class="carte-tete"><h2 class="carte-titre">Cours suivis</h2></div><div class="overflow-x-auto"><table class="tableau"><thead><tr><th>Cours</th><th>Progression</th><th>Leçons</th><th>Temps</th><th>Où il en est</th><th>Dernière activité</th><th>État</th></tr></thead><tbody>${d.cours.map((c) => `<tr><td><p class="font-medium">${esc(c.titre)}</p><p class="text-xs texte-3">${esc(c.mentor)} — inscrit le ${fmt.date(c.inscrit)}</p></td><td>${jauge(c.progression)}</td><td class="tabular-nums">${c.lecons}</td><td class="whitespace-nowrap">${fmt.duree(c.temps)}</td><td class="texte-2">${esc(c.derniere)}</td><td class="whitespace-nowrap texte-2">${fmt.date(c.actif)}</td><td>${etat(c.statut)}</td></tr>`).join('')}</tbody></table></div></section>
        <div class="grid xl:grid-cols-2 gap-4 mb-4">
          ${G.carte({ id: 'g-pc-quiz', titre: 'Résultats aux quiz', aide: 'Score de la meilleure tentative — seuil de réussite 70 %', hauteur: 250 })}
          <section class="carte"><div class="carte-tete"><h2 class="carte-titre">Devoirs</h2></div><div class="overflow-x-auto"><table class="tableau"><thead><tr><th>Devoir</th><th>Note</th><th>Correcteur</th><th>État</th></tr></thead><tbody>${d.devoirs.map((x) => `<tr><td><p class="font-medium">${esc(x.titre)}</p><p class="text-xs texte-3">${esc(x.cours)} — rendu le ${fmt.date(x.date)}</p></td><td class="tabular-nums">${x.note ? String(x.note).replace('.', ',') + ' / 20' : '—'}</td><td class="texte-2">${esc(x.correcteur)}</td><td>${etat(x.statut)}</td></tr>`).join('')}</tbody></table></div></section>
        </div>
        <div class="grid xl:grid-cols-2 gap-4">
          ${carte({ titre: 'Examens', corps: `<ul class="space-y-3">${d.examens.map((x) => `<li class="flex items-center justify-between gap-3"><div class="min-w-0"><p class="text-sm font-medium truncate">${esc(x.titre)}</p><p class="text-xs texte-3">${fmt.date(x.date)} — ${esc(x.resultat)}</p></div><span class="font-semibold tabular-nums">${x.score} %</span></li>`).join('')}</ul>` })}
          ${carte({ titre: 'Certificats', corps: d.certificats.length ? `<ul class="space-y-3">${d.certificats.map((c) => `<li class="flex items-center justify-between gap-3"><div><p class="text-sm font-medium">${esc(c.cours)}</p><p class="text-xs texte-3">Délivré le ${fmt.date(c.date)} — code <code>${c.code}</code></p></div><button class="btn-second btn-sm" data-toast="Certificat ${c.code} téléchargé.">${icone('file-down')}PDF</button></li>`).join('')}</ul>` : '<p class="text-sm texte-2">Aucun certificat pour l\'instant.</p>' })}
        </div>`,
      hub: `
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
          ${kpi({ libelle: 'Actualités lues', valeur: String(d.hub.filter((x) => x.type === 'actualite').length), ic: 'newspaper' })}
          ${kpi({ libelle: 'Portails ouverts', valeur: String(d.hub.filter((x) => x.type === 'portail').length), ic: 'landmark' })}
          ${kpi({ libelle: 'Offres consultées', valeur: String(d.hub.filter((x) => x.type === 'offre').length), ic: 'briefcase' })}
          ${kpi({ libelle: 'Candidatures', valeur: String(d.hub.filter((x) => /Candidature/.test(x.action)).length), aide: 'via le lien de l\'offre', ic: 'send' })}
        </div>
        <section class="carte" data-liste="hub"></section>`,
      communaute: `
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
          ${kpi({ libelle: 'Messages', valeur: String(messages), ic: 'messages-square' })}
          ${kpi({ libelle: 'Réactions reçues', valeur: String(d.groupes.reduce((s, g) => s + g.reactions, 0)), ic: 'heart' })}
          ${kpi({ libelle: 'Solutions acceptées', valeur: String(d.forum.filter((f) => f.type === 'solution').length), ic: 'check-check' })}
          ${kpi({ libelle: 'Signalements reçus', valeur: String(d.forum.filter((f) => f.type === 'signale').length), aide: 'par d\'autres membres', ic: 'flag' })}
        </div>
        <div class="grid xl:grid-cols-5 gap-4">
          <section class="carte xl:col-span-2"><div class="carte-tete"><h2 class="carte-titre">Groupes du forum</h2></div><div class="overflow-x-auto"><table class="tableau"><thead><tr><th>Groupe</th><th>Messages</th><th>Réactions</th><th>Depuis</th></tr></thead><tbody>${d.groupes.map((g) => `<tr><td><p class="font-medium">${esc(g.nom)}</p><p class="text-xs texte-3">${esc(g.role)}</p></td><td class="tabular-nums">${g.messages}</td><td class="tabular-nums">${g.reactions}</td><td class="whitespace-nowrap texte-2">${fmt.date(g.depuis)}</td></tr>`).join('')}</tbody></table></div></section>
          ${carte({ titre: 'Interactions récentes', classe: 'xl:col-span-3', corps: fil(d.forum.map((f) => ({ type: 'forum', action: f.action, objet: `${f.sujet} (${f.groupe})`, date: f.date, ton: f.type === 'signale' ? 'critique' : '' }))) })}
        </div>`,
      unites: `
        <div class="grid xl:grid-cols-3 gap-4">
          <section class="carte xl:col-span-2"><div class="carte-tete"><div><h2 class="carte-titre">Mouvements d'unités</h2><p class="text-xs texte-3 mt-0.5">Solde actuel : ${fmt.nombre(d.solde)} unités</p></div></div><div class="overflow-x-auto"><table class="tableau"><thead><tr><th>Motif</th><th>Unités</th><th>Date</th></tr></thead><tbody>${d.transactions.map((t) => `<tr><td>${esc(t.motif)}</td><td class="tabular-nums font-medium ${t.montant > 0 ? 'text-green-700 dark:text-bon' : ''}">${t.montant > 0 ? '+' : '−'}${Math.abs(t.montant)}</td><td class="texte-2 whitespace-nowrap">${fmt.date(t.date)}</td></tr>`).join('')}</tbody></table></div></section>
          ${carte({ titre: 'Badges et points', aide: `${fmt.nombre(d.points)} points — niveau ${d.niveau}`, corps: `<ul class="grid grid-cols-2 gap-3">${d.badges.map((b) => `<li class="rounded-ctrl border filet p-3 text-center"><span class="inline-flex w-10 h-10 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand/10 dark:text-brand">${icone(b.ic, 'w-5 h-5')}</span><p class="mt-2 text-sm font-medium">${esc(b.nom)}</p><p class="text-xs texte-3">${fmt.date(b.date)}</p></li>`).join('')}</ul>` })}
        </div>`,
      securite: `
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
          ${kpi({ libelle: 'Dernière connexion', valeur: `<span class="text-lg">${ilYa(derniere.date)}</span>`, aide: derniere.lieu, ic: 'log-in' })}
          ${kpi({ libelle: 'Connexions (30 j)', valeur: String(d.connexions.filter((c) => c.resultat === 'reussie').length), ic: 'activity' })}
          ${kpi({ libelle: 'Échecs et blocages', valeur: String(d.connexions.filter((c) => c.resultat !== 'reussie').length), aide: 'sur 30 jours', ic: 'shield-alert' })}
          ${kpi({ libelle: 'Double authentification', valeur: `<span class="text-lg">${d.statut.deuxFA ? 'Activée' : 'Désactivée'}</span>`, ic: 'shield-check' })}
        </div>
        <section class="carte mb-4" data-liste="connexions"></section>
        <div class="grid xl:grid-cols-2 gap-4">
          ${carte({ titre: 'Sessions ouvertes', actions: `<button class="btn-second btn-sm" data-toast="Toutes les sessions de ${esc(d.nom)} sont fermées.">Fermer toutes les sessions</button>`, corps: `<ul class="space-y-3 text-sm">${[d.connexions[0], d.connexions.find((c) => c.appareil !== d.connexions[0].appareil && c.resultat === 'reussie')].filter(Boolean).map((c) => `<li class="flex items-center gap-3">${icone(c.ic, 'w-5 h-5 texte-3')}<div><p class="font-medium">${esc(c.appareil)}</p><p class="text-xs texte-3">${esc(c.lieu)} — ${ilYa(c.date)}</p></div></li>`).join('')}</ul>` })}
          ${carte({ titre: 'Confidentialité (RGPD)', corps: fiche([['Infolettre', etat('oui')], ['Cookies de mesure d\'audience', etat('non')], ['Profil visible au forum', etat('oui')], ['Dernier export de données', fmt.date(auJour(63))], ['Demande de suppression', 'Aucune']]) })}
        </div>`,
      journal: `<section class="carte" data-liste="journal"></section>`,
    };

    racine.innerHTML = `
      ${enTeteProfil({
        p: { ...d, enLigne: (MAINTENANT - derniere.date) < 15 * 60000 },
        sous: mentor ? `Apprenant — ${d.ville}` : `Citoyen — ${d.ville} — inscrit le ${fmt.date(d.inscrit)}`,
        pastilles: [pastille('p-bon', 'circle-check', 'Compte actif'), ...(mentor ? [] : [kycP]), pastille('p-info', 'medal', d.niveau), d.statut.deuxFA ? pastille('p-bon', 'shield-check', 'Double authentification') : pastille('p-neutre', 'shield', 'Sans double authentification')],
        meta: [['mail', esc(d.email)], ['map-pin', esc(d.ville)], ['clock', `Dernière connexion ${ilYa(derniere.date)}`], ['smartphone', esc(derniere.appareil)]],
        actions,
        fil: mentor ? [['Suivi', ''], ['Mes apprenants', '#/m-apprenants'], [d.nom, '']] : [['Utilisateurs', ''], ['Citoyens', '#/utilisateurs'], [d.nom, '']],
      })}
      ${barreOnglets(ONGLETS, 'Sections de la fiche')}
      ${ONGLETS.map(([id], i) => panneau(id, html[id], i === 0)).join('')}`;

    /* Listes des onglets */
    const listes = {
      verifications: () => liste($('[data-liste="verifications"]', racine), { nom: 'verifications-' + d.initiales, colonnes: [
        { libelle: 'Vérification', rendu: (v) => `<div class="max-w-[360px]"><p class="font-medium truncate">${esc(v.contenu)}</p><p class="text-xs texte-3"><code>${v.id}</code> — ${esc(v.type)} via ${esc(v.canal)}</p></div>`, texte: (v) => `${v.id} ${v.contenu}` },
        { libelle: 'Statut', rendu: (v) => etat(v.statut) + (v.motif ? `<p class="text-xs text-red-700 dark:text-red-400 mt-1 max-w-[200px]">${esc(v.motif)}</p>` : ''), texte: (v) => P[v.statut][2] + (v.motif ? ' — ' + v.motif : '') },
        { libelle: 'Verdict', rendu: (v) => verdict(v.verdict), texte: (v) => (v.verdict ? VF.verdicts[v.verdict].libelle : '') },
        { libelle: 'Confiance', rendu: (v) => (v.confiance ? v.confiance + ' %' : '—'), texte: (v) => v.confiance || '' },
        { libelle: 'Moteurs', rendu: (v) => `<span class="tabular-nums">${v.moteurs}</span>`, texte: (v) => v.moteurs },
        { libelle: 'Unités', rendu: (v) => `<span class="tabular-nums">${v.unites}</span>`, texte: (v) => v.unites },
        { libelle: 'Date', rendu: (v) => `<span class="whitespace-nowrap texte-2">${dateHeure(v.date)}</span>`, texte: (v) => dateHeure(v.date) },
      ], lignes: d.verifications, filtres: [{ cle: 'statut', libelle: 'Statut', options: [['terminee', 'Terminée'], ['echec', 'Échec'], ['en_cours', 'En cours']] }, { cle: 'verdict', libelle: 'Verdict', options: Object.entries(VF.verdicts).map(([k, v]) => [k, v.libelle]) }] }),
      rapports: () => liste($('[data-liste="rapports"]', racine), { nom: 'rapports-' + d.initiales, colonnes: [
        { libelle: 'Rapport', rendu: (x) => `<div class="max-w-[380px]"><p class="font-medium truncate">${esc(x.titre)}</p><p class="text-xs texte-3"><code>${x.id}</code> — vérification ${x.verif}</p></div>`, texte: (x) => `${x.id} ${x.titre}` },
        { libelle: 'Verdict', rendu: (x) => verdict(x.verdict), texte: (x) => VF.verdicts[x.verdict].libelle },
        { libelle: 'Partage public', rendu: (x) => (x.partage ? pastille('p-info', 'link', `${fmt.nombre(x.vues)} vues`) : '<span class="texte-3">Privé</span>'), texte: (x) => (x.partage ? x.vues + ' vues' : 'Privé') },
        { libelle: 'Téléchargé', rendu: (x) => `<span class="tabular-nums">${x.telechargements} fois</span>`, texte: (x) => x.telechargements },
        { libelle: 'Date', rendu: (x) => `<span class="whitespace-nowrap texte-2">${fmt.date(x.date)}</span>`, texte: (x) => fmt.date(x.date) },
        { libelle: '', rendu: (x) => `<button class="btn-second btn-sm" data-toast="Rapport ${x.id} téléchargé (PDF).">${icone('file-down')}PDF</button>`, texte: () => '' },
      ], lignes: d.rapports, filtres: [{ cle: 'verdict', libelle: 'Verdict', options: Object.entries(VF.verdicts).map(([k, v]) => [k, v.libelle]) }] }),
      hub: () => liste($('[data-liste="hub"]', racine), { nom: 'hub-' + d.initiales, colonnes: [
        { libelle: 'Contenu', rendu: (x) => `<div class="flex items-center gap-2.5">${icone({ actualite: 'newspaper', portail: 'landmark', offre: 'briefcase' }[x.type], 'w-4 h-4 texte-3 shrink-0')}<span class="font-medium">${esc(x.titre)}</span></div>`, texte: (x) => x.titre },
        { libelle: 'Rubrique', rendu: (x) => `<span class="texte-2">${{ actualite: 'Actualité', portail: 'Portail officiel', offre: 'Offre d\'emploi' }[x.type]}</span>`, texte: (x) => x.type },
        { libelle: 'Action', rendu: (x) => (/suspecte/.test(x.action) ? pastille('p-alerte', 'flag', x.action) : /Candidature/.test(x.action) ? pastille('p-bon', 'send', x.action) : esc(x.action)), texte: (x) => x.action },
        { libelle: 'Date', rendu: (x) => `<span class="whitespace-nowrap texte-2">${dateHeure(x.date)}</span>`, texte: (x) => dateHeure(x.date) },
      ], lignes: d.hub, filtres: [{ cle: 'type', libelle: 'Rubrique', options: [['actualite', 'Actualités'], ['portail', 'Portails'], ['offre', 'Offres d\'emploi']] }] }),
      connexions: () => liste($('[data-liste="connexions"]', racine), { nom: 'connexions-' + d.initiales, colonnes: [
        { libelle: 'Date', rendu: (c) => `<span class="whitespace-nowrap">${dateHeure(c.date)}</span>`, texte: (c) => dateHeure(c.date) },
        { libelle: 'Résultat', rendu: (c) => etat(c.resultat) + (c.motif ? `<p class="text-xs texte-3 mt-1">${esc(c.motif)}</p>` : ''), texte: (c) => P[c.resultat][2] },
        { libelle: 'Méthode', rendu: (c) => `<span class="texte-2">${esc(c.methode)}</span>`, texte: (c) => c.methode },
        { libelle: 'Appareil', rendu: (c) => `<span class="inline-flex items-center gap-2">${icone(c.ic, 'w-4 h-4 texte-3')}${esc(c.appareil)}</span>`, texte: (c) => c.appareil },
        { libelle: 'Lieu', rendu: (c) => esc(c.lieu), texte: (c) => c.lieu },
        { libelle: 'Adresse IP', rendu: (c) => `<code class="text-xs px-1.5 py-0.5 rounded bg-papier dark:bg-nuit">${c.ip}</code>`, texte: (c) => c.ip },
      ], lignes: d.connexions, filtres: [{ cle: 'resultat', libelle: 'Résultat', options: [['reussie', 'Réussie'], ['echouee', 'Échouée'], ['bloquee', 'Bloquée']] }] }),
      journal: () => liste($('[data-liste="journal"]', racine), { nom: 'journal-' + d.initiales, parPage: 12, colonnes: [
        { libelle: 'Date', rendu: (e) => `<span class="whitespace-nowrap texte-2">${dateHeure(e.date)}</span>`, texte: (e) => dateHeure(e.date) },
        { libelle: 'Domaine', rendu: (e) => `<span class="inline-flex items-center gap-1.5 texte-2">${icone(TYPES[e.type][0], 'w-4 h-4')}${TYPES[e.type][1]}</span>`, texte: (e) => TYPES[e.type][1] },
        { libelle: 'Action', rendu: (e) => `<span class="font-medium ${e.ton === 'critique' ? 'text-red-700 dark:text-red-400' : ''}">${esc(e.action)}</span>`, texte: (e) => e.action },
        { libelle: 'Détail', rendu: (e) => `<span class="texte-2 block max-w-[420px] truncate" title="${esc(e.objet)}">${esc(e.objet)}</span>`, texte: (e) => e.objet },
      ], lignes: d.journal, filtres: [{ cle: 'type', libelle: 'Domaine', options: Object.entries(TYPES).filter(([k]) => d.journal.some((e) => e.type === k)).map(([k, v]) => [k, v[1]]) }] }),
    };
    Object.keys(listes).forEach((k) => { if ($(`[data-liste="${k}"]`, racine)) listes[k](); });

    /* Graphiques, montés à l'affichage de leur onglet */
    const coul = G.couleurs();
    const monter = graphiquesParOnglet(racine, {
      ensemble: () => {
        const noms = ['Vérifications', 'Académie', 'Forum', 'Hub'];
        const series = mentor ? [1, 2] : [0, 1, 2, 3];
        G.monter('g-pc-activite', 'bar', { chart: { stacked: true }, series: series.map((i) => ({ name: noms[i], data: d.activite[i] })), colors: series.map((i) => coul[i]), xaxis: { categories: xJours, tickAmount: 6 }, plotOptions: { bar: { columnWidth: '62%', borderRadius: 2 } } }, { hauteur: 250, tableau: { colonnes: ['Jour', ...series.map((i) => noms[i])], lignes: xJours.map((j, k) => [j, ...series.map((i) => d.activite[i][k])]) } });
        if (!mentor) {
          const ordre = ['faux', 'trompeur', 'vrai', 'invérifiable'];
          const n = ordre.map((k) => terminees.filter((v) => v.verdict === k).length);
          const couleurV = { faux: G.STATUT.critique, trompeur: G.STATUT.alerte, vrai: G.STATUT.bon, invérifiable: G.STATUT.neutre };
          G.monter('g-pc-verdicts', 'bar', { series: [{ name: 'Vérifications', data: n }], colors: ordre.map((k) => couleurV[k]), plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '56%' } }, xaxis: { categories: ordre.map((k) => VF.verdicts[k].libelle) }, legend: { show: false }, dataLabels: { enabled: true, textAnchor: 'start', offsetX: 6, style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] } }, tooltip: { shared: false, intersect: true } }, { hauteur: 250, tableau: { colonnes: ['Verdict', 'Vérifications'], lignes: ordre.map((k, i) => [VF.verdicts[k].libelle, n[i]]) } });
        }
      },
      academie: () => G.monter('g-pc-quiz', 'bar', { series: [{ name: 'Score', data: d.quiz.map((q) => q.score) }], colors: [coul[0]], plotOptions: { bar: { horizontal: true, barHeight: '56%' } }, xaxis: { categories: d.quiz.map((q) => q.titre), max: 100 }, legend: { show: false }, annotations: { xaxis: [{ x: 70, borderColor: G.encre().muet, strokeDashArray: 4, label: { text: 'Seuil 70 %', borderWidth: 0, orientation: 'horizontal', style: { background: 'transparent', color: G.encre().second, fontSize: '11px' } } }] }, tooltip: { shared: false, intersect: true, y: { formatter: (v) => v + ' %' } } }, { hauteur: 250, tableau: { colonnes: ['Quiz', 'Score', 'Tentatives', 'Résultat'], lignes: d.quiz.map((q) => [q.titre, q.score + ' %', q.tentatives, q.reussi ? 'Réussi' : 'Échoué']) } }),
    });
    monter('ensemble');
    if (demande !== 'ensemble') $(`#t-${demande}`, racine).click();

    racine.addEventListener('click', (e) => {
      const b = e.target.closest('[data-act], [data-aller], [data-toast]');
      if (!b) return;
      if (b.dataset.toast) return toast(b.dataset.toast);
      if (b.dataset.aller) return $(`#t-${b.dataset.aller}`, racine).click();
      const a = b.dataset.act;
      if (a === 'message') toast(`Message envoyé à ${d.nom}.`);
      else if (a === 'relancer') { b.disabled = true; toast(`Relance envoyée à ${d.nom}.`); }
      else if (a === 'crediter') toast(`50 unités créditées à ${d.nom}. L'action est journalisée.`);
      else if (a === 'plus') VF.ui.menu(b, [
        { libelle: 'Réinitialiser le mot de passe', ic: 'key-round', action: () => toast('Lien de réinitialisation envoyé.') },
        { libelle: 'Fermer toutes les sessions', ic: 'log-out', action: () => toast('Sessions fermées.', 'alerte') },
        { libelle: 'Exporter ses données (RGPD)', ic: 'file-archive', action: () => toast('Export préparé. Il sera envoyé par courriel.', 'info') },
        { libelle: 'Voir dans le journal d\'audit', ic: 'scroll', action: () => (location.hash = '#/audit') },
        '-',
        { libelle: 'Suspendre le compte', ic: 'ban', danger: true, action: () => confirmer({ titre: `Suspendre ${d.nom} ?`, texte: 'La personne est déconnectée et ne peut plus se connecter. Ses contenus restent visibles. L\'action est journalisée.', bouton: 'Suspendre', siOui: () => toast('Compte suspendu.', 'alerte') }) },
      ]);
    });
    icones();
  };

  VF.ecrans['profil-citoyen'] = (r, p = {}) => ecranCitoyen(r, p);
  VF.ecrans['m-profil-citoyen'] = (r, p = {}) => ecranCitoyen(r, p, { mentor: true });

  /* =================================================================
     Fiche mentor
     ================================================================= */

  const donneesMentor = (nom, email) => {
    const r = generateur(nom + '#mentor');
    const { entre, choisir, h } = r;
    const equipe = VF.equipe.find((e) => e.nom === nom) || {};
    const siens = VF.cours.filter((c) => c.mentor === nom);
    const base = siens.length ? siens : VF.cours.slice(2, 4).map((c) => ({ ...c, mentor: nom }));
    const cours = base.map((c, i) => ({ titre: c.titre, categorie: c.categorie, statut: 'publie', lecons: c.lecons, inscrits: c.inscrits, completion: c.completion, note: c.note, revenus: c.prix * Math.round(c.inscrits * 0.3), prix: c.prix, maj: auJour(entre(1, 40)), quiz: entre(3, 8) }))
      .concat([{ titre: 'Fact-checking des chiffres officiels', categorie: 'Enquête', statut: 'revue', lecons: 11, inscrits: 0, completion: 0, note: null, revenus: 0, prix: 150, maj: auJour(3), quiz: 4 }, { titre: 'Vérifier un audio WhatsApp', categorie: 'Audio', statut: 'brouillon', lecons: 5, inscrits: 0, completion: 0, note: null, revenus: 0, prix: 0, maj: auJour(1), quiz: 1 }]);
    const publies = cours.filter((c) => c.statut === 'publie');
    const apprenants = VF.apprenants.map((a) => ({ ...a, cours: choisir(publies).titre }));
    const mois = ['Mai', 'Juin', 'Juil.', 'Août', 'Sept.', 'Oct.'];
    const inscriptionsMois = publies.map((c) => mois.map((_, i) => Math.round(c.inscrits / 9 * (0.6 + i * 0.12) * (0.8 + h() * 0.4))));
    const corrections = VF.copies.map((c) => ({ ...c, statut: c.urgence === 'en_cours' ? 'a_corriger' : c.urgence === 'urgent' ? 'retard' : 'a_corriger', delai: null }))
      .concat(Array.from({ length: 9 }, () => { const p = VF.outils.personne(); return { apprenant: p.nom, initiales: p.initiales, devoir: choisir(['Enquête guidée sur un domaine', 'Analyse d\'une image virale', 'Vérifier une déclaration publique']), cours: choisir(publies).titre, soumis: fmt.date(auJour(entre(4, 40))), statut: 'corrige', delai: entre(3, 30), note: entre(9, 19) }; }));
    const assignees = VF.signalements.slice(2, 10).map((s, i) => ({ id: s.id, texte: s.texte, etat: i < 2 ? 'en_cours' : i === 2 ? 'revue' : 'publie', verdict: i < 3 ? null : choisir(['faux', 'trompeur', 'vrai', 'invérifiable']), echeance: i < 2 ? [95, 210][i] : null, temps: i < 2 ? null : entre(35, 400), date: auJour(entre(0, 30)) }));
    const lives = [['OSINT pas à pas', -1, 'attente'], ['Deepfakes : les indices', 6, 'termine'], ['Questions-réponses : image virale', 13, 'termine'], ['Whois et archives en direct', 27, 'termine']].map(([titre, j, statut]) => { const inscrits = entre(60, 340); return { titre, date: auJour(j, 18), inscrits, presents: statut === 'termine' ? Math.round(inscrits * (0.5 + h() * 0.3)) : null, replay: statut === 'termine' ? entre(40, 900) : null, statut }; });
    const examens = [['Examen final — ' + publies[0].titre, 'publie', 74], ['Contrôle — ' + (publies[1] || publies[0]).titre, 'publie', 81], ['Examen blanc', 'brouillon', null]].map(([titre, statut, reussite]) => ({ titre, statut, reussite, inscrits: statut === 'publie' ? entre(40, 300) : 0 }));
    const ventes = mois.map((_, i) => Math.round((60000 + i * 22000) * (0.8 + h() * 0.4)));
    const ip = () => `41.${entre(70, 90)}.${entre(0, 255)}.${entre(1, 254)}`;
    const connexions = Array.from({ length: 12 }, (_, i) => ({ date: auJour(i === 0 ? 0 : i * 2, entre(7, 21), entre(0, 59)), ip: ip(), ic: i % 3 ? 'monitor' : 'smartphone', appareil: i % 3 ? 'Chrome 129 sur macOS' : 'Application Vérif\'All, Android 14', lieu: 'Cotonou, Bénin', resultat: i === 5 ? 'echouee' : 'reussie', methode: 'Mot de passe + code 2FA', motif: i === 5 ? 'Code 2FA expiré' : '' }));
    const journal = [
      ...connexions.map((c) => ({ type: 'connexion', action: c.resultat === 'reussie' ? 'Connexion réussie' : 'Échec de connexion', objet: `${c.appareil} (${c.ip})`, date: c.date, ton: c.resultat === 'reussie' ? '' : 'critique' })),
      ...cours.map((c) => ({ type: 'cours', action: c.statut === 'brouillon' ? 'A créé le brouillon' : c.statut === 'revue' ? 'A soumis à la relecture' : 'A mis à jour le cours', objet: c.titre, date: c.maj })),
      ...corrections.filter((c) => c.statut === 'corrige').map((c) => ({ type: 'correction', action: `A corrigé un devoir (${c.note} / 20, en ${c.delai} h)`, objet: `${c.apprenant} — ${c.devoir}`, date: auJour(entre(2, 40)) })),
      ...assignees.filter((s) => s.etat === 'publie').map((s) => ({ type: 'verification', action: `A publié le verdict : ${VF.verdicts[s.verdict].libelle}`, objet: `${s.id} — ${s.texte}`, date: s.date })),
      ...lives.filter((l) => l.statut === 'termine').map((l) => ({ type: 'live', action: `A animé un live (${l.presents} présents)`, objet: l.titre, date: l.date })),
      { type: 'forum', action: 'A répondu en tant que mentor', objet: 'Exercice Whois : je bloque à l\'étape 3', date: auJour(2, 20, 4) },
      { type: 'compte', action: 'Dossier KYC validé', objet: 'Pièce d\'identité et contrat de mentor', date: auJour(310) },
    ].sort((a, b) => b.date - a.date);
    return { nom, initiales: initialesDe(nom), email: email || nom.split(' ')[0].toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '') + '@verifall.com', specialite: choisir(['OSINT et vérification', 'Image et vidéo', 'Éducation aux médias', 'Sécurité numérique']), depuis: auJour(entre(320, 700)), note: equipe.satisfaction || 4.6, points: equipe.points || entre(2000, 8000), quota: 8000, cours, publies, apprenants, mois, inscriptionsMois, corrections, assignees, lives, examens, ventes, connexions, journal };
  };

  const ecranMentor = (racine, params, { soiMeme = false } = {}) => {
    G.detruireTout();
    const moi = VF.moi.mentor;
    const d = donneesMentor(soiMeme ? moi.nom : (params.nom || 'Fifamè Houngbédji'), soiMeme ? moi.email : params.email);
    const derniere = d.connexions.find((c) => c.resultat === 'reussie');
    const totalInscrits = d.publies.reduce((s, c) => s + c.inscrits, 0);
    const aCorriger = d.corrections.filter((c) => c.statut !== 'corrige');
    const corriges = d.corrections.filter((c) => c.statut === 'corrige');
    const delaiMoyen = Math.round(corriges.reduce((s, c) => s + c.delai, 0) / corriges.length);
    const revenus = d.cours.reduce((s, c) => s + c.revenus, 0);
    const plusSuivis = d.publies.slice().sort((a, b) => b.inscrits - a.inscrits);

    const ONGLETS = [['ensemble', 'Vue d\'ensemble'], ['cours', 'Cours créés', d.cours.length], ['apprenants', 'Apprenants', d.apprenants.length], ['stats', 'Statistiques'], ['corrections', 'Corrections et vérifications', aCorriger.length + d.assignees.filter((s) => s.etat !== 'publie').length], ['lives', 'Lives et examens'], ['revenus', 'Revenus et points'], ['securite', 'Connexions et sécurité'], ['journal', 'Journal complet', d.journal.length]];
    const demande = ONGLETS.some((o) => o[0] === params.onglet) ? params.onglet : 'ensemble';
    const lienApprenant = soiMeme ? 'm-profil-citoyen' : 'profil-citoyen';

    const html = {
      ensemble: `
        <div class="grid grid-cols-2 md:grid-cols-3 2xl:grid-cols-6 gap-4 mb-6">
          ${kpi({ libelle: 'Cours créés', valeur: String(d.cours.length), aide: `${d.publies.length} publiés`, ic: 'library' })}
          ${kpi({ libelle: 'Inscrits à ses cours', valeur: fmt.nombre(totalInscrits), delta: 9, ic: 'users' })}
          ${kpi({ libelle: 'Complétion moyenne', valeur: Math.round(d.publies.reduce((s, c) => s + c.completion, 0) / d.publies.length) + ' %', ic: 'circle-check' })}
          ${kpi({ libelle: 'Note des apprenants', valeur: String(d.note).replace('.', ','), aide: 'sur 5', ic: 'star' })}
          ${kpi({ libelle: 'Délai de correction', valeur: delaiMoyen + ' h', sens: 'bas-bon', delta: -12, aide: 'objectif 24 h', ic: 'timer' })}
          ${kpi({ libelle: 'Revenus générés', valeur: fmt.compact(revenus) + ' FCFA', ic: 'wallet' })}
        </div>
        <div class="grid xl:grid-cols-3 gap-4 mb-4">
          ${G.carte({ id: 'g-pm-inscriptions', titre: 'Inscriptions par cours', aide: 'Nouveaux inscrits par mois', hauteur: 260, classe: 'xl:col-span-2' })}
          ${G.carte({ id: 'g-pm-top', titre: 'Cours les plus suivis', aide: 'Inscrits au total', hauteur: 260 })}
        </div>
        <div class="grid xl:grid-cols-3 gap-4">
          ${carte({ titre: 'Dernières actions', classe: 'xl:col-span-2', actions: `<button class="btn-fantome btn-sm" data-aller="journal">Journal complet</button>`, corps: fil(d.journal.slice(0, 8)) })}
          ${carte({ titre: 'Identité et compte', corps: fiche([['Courriel', esc(d.email)], ['Spécialité', esc(d.specialite)], ['Mentor depuis', fmt.date(d.depuis)], ['Vérification d\'identité', pastille('p-bon', 'badge-check', 'KYC validé')], ['Contrat', 'Signé — renouvelé en janvier'], ['Double authentification', 'Activée (obligatoire)'], ['Copies à corriger', String(aCorriger.length)], ['Vérifications assignées', String(d.assignees.filter((s) => s.etat !== 'publie').length) + ' en cours'], ['Dernière connexion', `${dateHeure(derniere.date)}<span class="block text-xs texte-3 font-normal">${esc(derniere.appareil)}</span>`]]) })}
        </div>`,
      cours: `<section class="carte" data-liste="cours"></section>`,
      apprenants: `<section class="carte" data-liste="apprenants"></section>`,
      stats: `
        <div class="grid xl:grid-cols-2 gap-4 mb-4">
          ${G.carte({ id: 'g-pm-entonnoir', titre: 'Parcours des inscrits', aide: 'Tous ses cours publiés', hauteur: 250 })}
          ${G.carte({ id: 'g-pm-completion', titre: 'Complétion par cours', aide: 'Part des inscrits ayant terminé', hauteur: 250 })}
        </div>
        <div class="grid xl:grid-cols-2 gap-4">
          ${G.carte({ id: 'g-pm-quiz', titre: 'Réussite aux quiz', aide: 'Part des tentatives au-dessus du seuil', hauteur: 250 })}
          ${G.carte({ id: 'g-pm-notes', titre: 'Avis des apprenants', aide: 'Répartition des notes', hauteur: 250 })}
        </div>`,
      corrections: `
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
          ${kpi({ libelle: 'Copies à corriger', valeur: String(aCorriger.length), aide: `${aCorriger.filter((c) => c.statut === 'retard').length} en retard`, ic: 'pen-line' })}
          ${kpi({ libelle: 'Copies corrigées (30 j)', valeur: String(corriges.length), ic: 'check-check' })}
          ${kpi({ libelle: 'Vérifications assignées', valeur: String(d.assignees.length), aide: `${d.assignees.filter((s) => s.etat !== 'publie').length} en cours`, ic: 'search-check' })}
          ${kpi({ libelle: 'Temps moyen d\'un verdict', valeur: fmt.duree(Math.round(d.assignees.filter((s) => s.temps).reduce((s, x) => s + x.temps, 0) / d.assignees.filter((s) => s.temps).length)), aide: 'objectif 6 h', ic: 'timer' })}
        </div>
        <section class="carte mb-4" data-liste="corrections"></section>
        <section class="carte" data-liste="assignees"></section>`,
      lives: `
        <div class="grid xl:grid-cols-2 gap-4">
          <section class="carte"><div class="carte-tete"><h2 class="carte-titre">Live classes</h2><a href="#/${soiMeme ? 'm-live' : 'calendrier'}" class="btn-fantome btn-sm">Calendrier</a></div><div class="overflow-x-auto"><table class="tableau"><thead><tr><th>Session</th><th>Inscrits</th><th>Présence</th><th>Replay</th></tr></thead><tbody>${d.lives.map((l) => `<tr><td><p class="font-medium">${esc(l.titre)}</p><p class="text-xs texte-3">${dateHeure(l.date)}</p></td><td class="tabular-nums">${l.inscrits}</td><td>${l.presents ? jauge(Math.round(l.presents / l.inscrits * 100)) : pastille('p-info', 'calendar-clock', 'À venir')}</td><td class="tabular-nums texte-2">${l.replay ? fmt.nombre(l.replay) + ' vues' : '—'}</td></tr>`).join('')}</tbody></table></div></section>
          <section class="carte"><div class="carte-tete"><h2 class="carte-titre">Examens créés</h2></div><div class="overflow-x-auto"><table class="tableau"><thead><tr><th>Examen</th><th>Inscrits</th><th>Réussite</th><th>État</th></tr></thead><tbody>${d.examens.map((x) => `<tr><td class="font-medium">${esc(x.titre)}</td><td class="tabular-nums">${x.inscrits}</td><td>${x.reussite ? jauge(x.reussite) : '—'}</td><td>${etat(x.statut)}</td></tr>`).join('')}</tbody></table></div></section>
        </div>`,
      revenus: `
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
          ${kpi({ libelle: 'Ventes des 6 derniers mois', valeur: fmt.compact(d.ventes.reduce((s, v) => s + v, 0)) + ' FCFA', ic: 'wallet' })}
          ${kpi({ libelle: 'Part du mentor', valeur: '60 %', aide: 'selon le contrat', ic: 'percent' })}
          ${kpi({ libelle: 'Points du mois', valeur: fmt.nombre(d.points), aide: `quota : ${fmt.nombre(d.quota)}`, ic: 'trophy' })}
          ${kpi({ libelle: 'Prochain versement', valeur: '5 nov.', aide: 'par Mobile Money', ic: 'calendar-check' })}
        </div>
        ${G.carte({ id: 'g-pm-ventes', titre: 'Ventes de ses cours premium', aide: 'FCFA par mois', hauteur: 250 })}`,
      securite: `
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
          ${kpi({ libelle: 'Dernière connexion', valeur: `<span class="text-lg">${ilYa(derniere.date)}</span>`, aide: derniere.lieu, ic: 'log-in' })}
          ${kpi({ libelle: 'Connexions (30 j)', valeur: String(d.connexions.filter((c) => c.resultat === 'reussie').length), ic: 'activity' })}
          ${kpi({ libelle: 'Échecs', valeur: String(d.connexions.filter((c) => c.resultat !== 'reussie').length), ic: 'shield-alert' })}
          ${kpi({ libelle: 'Double authentification', valeur: '<span class="text-lg">Activée</span>', aide: 'obligatoire pour le personnel', ic: 'shield-check' })}
        </div>
        <section class="carte" data-liste="connexions"></section>`,
      journal: `<section class="carte" data-liste="journal"></section>`,
    };

    racine.innerHTML = `
      ${enTeteProfil({
        p: { ...d, enLigne: true },
        sous: `Mentor — ${d.specialite}`,
        pastilles: [pastille('p-bon', 'circle-check', 'Actif'), pastille('p-bon', 'badge-check', 'KYC validé'), pastille('p-info', 'star', `${String(d.note).replace('.', ',')} / 5`), pastille('p-bon', 'shield-check', 'Double authentification')],
        meta: [['mail', esc(d.email)], ['calendar', `Mentor depuis le ${fmt.date(d.depuis)}`], ['clock', `Dernière connexion ${ilYa(derniere.date)}`], ['library', `${d.publies.length} cours publiés`]],
        actions: soiMeme ? `<a href="#/compte" class="btn-second">${icone('pencil')}Modifier mon profil</a><a href="#/m-cours" class="btn-primaire">${icone('library')}Mes cours</a>` : `<button class="btn-second" data-act="message">${icone('message-square')}Écrire</button><button class="btn-second" data-act="assigner">${icone('user-plus')}Assigner une vérification</button><button class="btn-icone border filet" data-act="plus" aria-label="Autres actions">${icone('ellipsis')}</button>`,
        fil: soiMeme ? [['Suivi', ''], ['Mon profil', '']] : [['Utilisateurs', ''], ['Mentors', '#/mentors'], [d.nom, '']],
      })}
      ${barreOnglets(ONGLETS, 'Sections de la fiche')}
      ${ONGLETS.map(([id], i) => panneau(id, html[id], i === 0)).join('')}`;

    liste($('[data-liste="cours"]', racine), { nom: 'cours-' + d.initiales, colonnes: [
      { libelle: 'Cours', rendu: (c) => `<p class="font-medium">${esc(c.titre)}</p><p class="text-xs texte-3">${esc(c.categorie)} — ${c.lecons} leçons, ${c.quiz} quiz</p>`, texte: (c) => c.titre },
      { libelle: 'État', rendu: (c) => etat(c.statut), texte: (c) => P[c.statut][2] },
      { libelle: 'Inscrits', rendu: (c) => `<span class="tabular-nums">${fmt.nombre(c.inscrits)}</span>`, texte: (c) => c.inscrits },
      { libelle: 'Complétion', rendu: (c) => (c.statut === 'publie' ? jauge(c.completion) : '—'), texte: (c) => c.completion },
      { libelle: 'Note', rendu: (c) => (c.note ? `<span class="inline-flex items-center gap-1">${icone('star', 'w-3.5 h-3.5 text-amber-500 fill-amber-400')}${String(c.note).replace('.', ',')}</span>` : '—'), texte: (c) => c.note || '' },
      { libelle: 'Prix', rendu: (c) => `<span class="whitespace-nowrap">${c.prix ? fmt.fcfa(c.prix) : 'Gratuit'}</span>`, texte: (c) => c.prix },
      { libelle: 'Revenus', rendu: (c) => `<span class="tabular-nums whitespace-nowrap">${c.revenus ? fmt.fcfa(c.revenus) : '—'}</span>`, texte: (c) => c.revenus },
      { libelle: 'Mis à jour', rendu: (c) => `<span class="texte-2 whitespace-nowrap">${fmt.date(c.maj)}</span>`, texte: (c) => fmt.date(c.maj) },
    ], lignes: d.cours, filtres: [{ cle: 'statut', libelle: 'État', options: [['publie', 'Publié'], ['revue', 'En relecture'], ['brouillon', 'Brouillon']] }] });
    liste($('[data-liste="apprenants"]', racine), { nom: 'apprenants-' + d.initiales, colonnes: [
      { libelle: 'Citoyen', rendu: (a) => `<a href="${VF.lienProfil(lienApprenant, a)}" class="flex items-center gap-2.5 hover:underline"><span class="avatar w-8 h-8">${esc(a.initiales)}</span><span><span class="block font-medium">${esc(a.nom)}</span><span class="block text-xs texte-3">${esc(a.ville)}</span></span></a>`, texte: (a) => a.nom },
      { libelle: 'Cours suivi', rendu: (a) => `<span class="texte-2">${esc(a.cours)}</span>`, texte: (a) => a.cours },
      { libelle: 'Progression', rendu: (a) => jauge(a.progression), texte: (a) => a.progression + ' %' },
      { libelle: 'Moyenne', rendu: (a) => `${a.moyenne} / 20`, texte: (a) => a.moyenne },
      { libelle: 'État', rendu: (a) => pastille(VF.statuts.activite[a.etat].classe, VF.statuts.activite[a.etat].ic, VF.statuts.activite[a.etat].libelle), texte: (a) => VF.statuts.activite[a.etat].libelle },
      { libelle: 'Dernière activité', rendu: (a) => `<span class="texte-2 whitespace-nowrap">${fmt.date(a.derniere)}</span>`, texte: (a) => fmt.date(a.derniere) },
    ], lignes: d.apprenants, filtres: [{ cle: 'etat', libelle: 'État', options: [['actif', 'Actif'], ['inactif', 'Inactif'], ['termine', 'Terminé']] }, { cle: 'cours', libelle: 'Cours', options: d.publies.map((c) => [c.titre, c.titre]) }] });
    liste($('[data-liste="corrections"]', racine), { nom: 'corrections-' + d.initiales, colonnes: [
      { libelle: 'Copie', rendu: (c) => `<p class="font-medium">${esc(c.apprenant)} — ${esc(c.devoir)}</p><p class="text-xs texte-3">${esc(c.cours)}, soumis ${esc(c.soumis)}</p>`, texte: (c) => `${c.apprenant} ${c.devoir}` },
      { libelle: 'État', rendu: (c) => etat(c.statut), texte: (c) => P[c.statut][2] },
      { libelle: 'Note', rendu: (c) => (c.note ? c.note + ' / 20' : '—'), texte: (c) => c.note || '' },
      { libelle: 'Délai', rendu: (c) => (c.delai ? c.delai + ' h' : '—'), texte: (c) => c.delai || '' },
    ], lignes: d.corrections, filtres: [{ cle: 'statut', libelle: 'État', options: [['a_corriger', 'À corriger'], ['retard', 'En retard'], ['corrige', 'Corrigé']] }] });
    $('[data-liste="corrections"]', racine).insertAdjacentHTML('afterbegin', '<div class="carte-tete"><h2 class="carte-titre">Devoirs assignés</h2></div>');
    liste($('[data-liste="assignees"]', racine), { nom: 'verifications-assignees-' + d.initiales, colonnes: [
      { libelle: 'Signalement', rendu: (s) => `<div class="max-w-[380px]"><p class="font-medium truncate">${esc(s.texte)}</p><p class="text-xs texte-3"><code>${s.id}</code> — assigné le ${fmt.date(s.date)}</p></div>`, texte: (s) => `${s.id} ${s.texte}` },
      { libelle: 'État', rendu: (s) => pastille(VF.statuts.signalement[s.etat].classe, VF.statuts.signalement[s.etat].ic, VF.statuts.signalement[s.etat].libelle), texte: (s) => VF.statuts.signalement[s.etat].libelle },
      { libelle: 'Verdict', rendu: (s) => verdict(s.verdict), texte: (s) => (s.verdict ? VF.verdicts[s.verdict].libelle : '') },
      { libelle: 'Échéance / durée', rendu: (s) => (s.echeance ? `<span class="font-medium ${s.echeance < 120 ? 'text-red-700 dark:text-red-400' : ''}">reste ${fmt.duree(s.echeance)}</span>` : `<span class="texte-2">traité en ${fmt.duree(s.temps)}</span>`), texte: (s) => (s.echeance ? 'reste ' + fmt.duree(s.echeance) : fmt.duree(s.temps)) },
    ], lignes: d.assignees });
    $('[data-liste="assignees"]', racine).insertAdjacentHTML('afterbegin', '<div class="carte-tete"><h2 class="carte-titre">Vérifications assignées</h2></div>');
    liste($('[data-liste="connexions"]', racine), { nom: 'connexions-' + d.initiales, colonnes: [
      { libelle: 'Date', rendu: (c) => `<span class="whitespace-nowrap">${dateHeure(c.date)}</span>`, texte: (c) => dateHeure(c.date) },
      { libelle: 'Résultat', rendu: (c) => etat(c.resultat) + (c.motif ? `<p class="text-xs texte-3 mt-1">${esc(c.motif)}</p>` : ''), texte: (c) => P[c.resultat][2] },
      { libelle: 'Méthode', rendu: (c) => `<span class="texte-2">${esc(c.methode)}</span>`, texte: (c) => c.methode },
      { libelle: 'Appareil', rendu: (c) => `<span class="inline-flex items-center gap-2">${icone(c.ic, 'w-4 h-4 texte-3')}${esc(c.appareil)}</span>`, texte: (c) => c.appareil },
      { libelle: 'Adresse IP', rendu: (c) => `<code class="text-xs px-1.5 py-0.5 rounded bg-papier dark:bg-nuit">${c.ip}</code>`, texte: (c) => c.ip },
    ], lignes: d.connexions });
    liste($('[data-liste="journal"]', racine), { nom: 'journal-' + d.initiales, parPage: 12, colonnes: [
      { libelle: 'Date', rendu: (e) => `<span class="whitespace-nowrap texte-2">${dateHeure(e.date)}</span>`, texte: (e) => dateHeure(e.date) },
      { libelle: 'Domaine', rendu: (e) => `<span class="inline-flex items-center gap-1.5 texte-2">${icone(TYPES[e.type][0], 'w-4 h-4')}${TYPES[e.type][1]}</span>`, texte: (e) => TYPES[e.type][1] },
      { libelle: 'Action', rendu: (e) => `<span class="font-medium ${e.ton === 'critique' ? 'text-red-700 dark:text-red-400' : ''}">${esc(e.action)}</span>`, texte: (e) => e.action },
      { libelle: 'Détail', rendu: (e) => `<span class="texte-2 block max-w-[420px] truncate" title="${esc(e.objet)}">${esc(e.objet)}</span>`, texte: (e) => e.objet },
    ], lignes: d.journal, filtres: [{ cle: 'type', libelle: 'Domaine', options: Object.entries(TYPES).filter(([k]) => d.journal.some((e) => e.type === k)).map(([k, v]) => [k, v[1]]) }] });

    const coul = G.couleurs();
    const monter = graphiquesParOnglet(racine, {
      ensemble: () => {
        G.monter('g-pm-inscriptions', 'line', { series: d.publies.map((c, i) => ({ name: c.titre, data: d.inscriptionsMois[i] })), colors: d.publies.map((_, i) => coul[i % coul.length]), xaxis: { categories: d.mois }, markers: { size: 3, strokeColors: G.encre().surface, strokeWidth: 2 } }, { hauteur: 260, tableau: { colonnes: ['Mois', ...d.publies.map((c) => c.titre)], lignes: d.mois.map((m, k) => [m, ...d.inscriptionsMois.map((s) => s[k])]) } });
        G.monter('g-pm-top', 'bar', { series: [{ name: 'Inscrits', data: plusSuivis.map((c) => c.inscrits) }], colors: [coul[0]], plotOptions: { bar: { horizontal: true, barHeight: '56%' } }, xaxis: { categories: plusSuivis.map((c) => c.titre) }, legend: { show: false }, dataLabels: { enabled: true, textAnchor: 'start', offsetX: 6, style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] } }, tooltip: { shared: false, intersect: true } }, { hauteur: 260, tableau: { colonnes: ['Cours', 'Inscrits'], lignes: plusSuivis.map((c) => [c.titre, c.inscrits]) } });
      },
      stats: () => {
        const etapes = [['Inscrits', 100], ['Ont commencé', 86], ['À mi-parcours', 64], ['Ont terminé', 52], ['Certifiés', 41]].map(([l, p]) => [l, Math.round(totalInscrits * p / 100)]);
        G.monter('g-pm-entonnoir', 'bar', { series: [{ name: 'Apprenants', data: etapes.map((e) => e[1]) }], colors: [coul[0]], plotOptions: { bar: { horizontal: true, barHeight: '60%' } }, xaxis: { categories: etapes.map((e) => e[0]) }, legend: { show: false }, dataLabels: { enabled: true, textAnchor: 'start', offsetX: 6, formatter: (v) => fmt.nombre(v), style: { fontSize: '11px', fontWeight: 500, colors: [G.encre().second] } }, tooltip: { shared: false, intersect: true } }, { hauteur: 250, tableau: { colonnes: ['Étape', 'Apprenants'], lignes: etapes } });
        G.monter('g-pm-completion', 'bar', { series: [{ name: 'Complétion', data: d.publies.map((c) => c.completion) }], colors: [coul[2]], xaxis: { categories: d.publies.map((c) => c.titre.length > 22 ? c.titre.slice(0, 21) + '…' : c.titre) }, yaxis: { max: 100, labels: { formatter: (v) => v + ' %' } }, legend: { show: false }, tooltip: { shared: false, intersect: true, y: { formatter: (v) => v + ' %' } } }, { hauteur: 250, tableau: { colonnes: ['Cours', 'Complétion'], lignes: d.publies.map((c) => [c.titre, c.completion + ' %']) } });
        const quiz = ['Quiz d\'entrée', 'Recherche inversée', 'Métadonnées', 'Comptes et domaines', 'Quiz final'].map((q, i) => [q, [88, 74, 61, 69, 77][i]]);
        G.monter('g-pm-quiz', 'bar', { series: [{ name: 'Réussite', data: quiz.map((q) => q[1]) }], colors: [coul[0]], plotOptions: { bar: { horizontal: true, barHeight: '56%' } }, xaxis: { categories: quiz.map((q) => q[0]), max: 100, labels: { formatter: (v) => v + ' %' } }, legend: { show: false }, tooltip: { shared: false, intersect: true, y: { formatter: (v) => v + ' %' } } }, { hauteur: 250, tableau: { colonnes: ['Quiz', 'Réussite'], lignes: quiz.map((q) => [q[0], q[1] + ' %']) } });
        const notes = [['5 étoiles', 301], ['4 étoiles', 82], ['3 étoiles', 21], ['2 étoiles', 6], ['1 étoile', 2]];
        G.monter('g-pm-notes', 'bar', { series: [{ name: 'Avis', data: notes.map((n) => n[1]) }], colors: [coul[0]], plotOptions: { bar: { horizontal: true, barHeight: '56%' } }, xaxis: { categories: notes.map((n) => n[0]) }, legend: { show: false }, tooltip: { shared: false, intersect: true } }, { hauteur: 250, tableau: { colonnes: ['Note', 'Avis'], lignes: notes } });
      },
      revenus: () => G.monter('g-pm-ventes', 'bar', { series: [{ name: 'Ventes', data: d.ventes }], colors: [coul[0]], xaxis: { categories: d.mois }, legend: { show: false }, tooltip: { shared: false, intersect: true, y: { formatter: (v) => fmt.fcfa(v) } } }, { hauteur: 250, tableau: { colonnes: ['Mois', 'Ventes (FCFA)'], lignes: d.mois.map((m, i) => [m, fmt.nombre(d.ventes[i])]) } }),
    });
    monter('ensemble');
    if (demande !== 'ensemble') $(`#t-${demande}`, racine).click();

    racine.addEventListener('click', (e) => {
      const b = e.target.closest('[data-act], [data-aller]');
      if (!b) return;
      if (b.dataset.aller) return $(`#t-${b.dataset.aller}`, racine).click();
      const a = b.dataset.act;
      if (a === 'message') toast(`Message envoyé à ${d.nom}.`);
      else if (a === 'assigner') location.hash = '#/signalements';
      else if (a === 'plus') VF.ui.menu(b, [
        { libelle: 'Modifier les permissions', ic: 'key-round', action: () => (location.hash = '#/roles') },
        { libelle: 'Créditer des points', ic: 'trophy', action: () => toast('200 points crédités. Journalisé.') },
        { libelle: 'Fermer toutes les sessions', ic: 'log-out', action: () => toast('Sessions fermées.', 'alerte') },
        '-',
        { libelle: 'Retirer le rôle de mentor', ic: 'user-x', danger: true, action: () => confirmer({ titre: `Retirer le rôle de mentor à ${d.nom} ?`, texte: 'Ses cours restent en ligne et passent sous la responsabilité de l\'équipe de l\'Académie. L\'action est journalisée.', bouton: 'Retirer le rôle', siOui: () => toast('Rôle retiré.', 'alerte') }) },
      ]);
    });
    icones();
  };

  VF.ecrans['profil-mentor'] = (r, p = {}) => ecranMentor(r, p);
  VF.ecrans['m-profil'] = (r, p = {}) => ecranMentor(r, p, { soiMeme: true });
})(window.VF);
