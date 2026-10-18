/*
 | Coquille de la console : navigation des deux espaces, routage par
 | l'adresse (#/ecran), thème, palette de commandes et notifications.
 */
(function (VF) {
  'use strict';
  const { $, $$, esc, icone, icones, toast } = VF.ui;

  /* La navigation reprend celle du prototype d'origine (compte-admin/,
     compte-mentor/), regroupée par métier, avec les nouveaux modules. */
  const NAV = {
    admin: [
      ['', [['tableau-de-bord', 'layout-dashboard', 'Tableau de bord'], ['calendrier', 'calendar-days', 'Calendrier']]],
      ['Analytique', [['bi', 'blocks', 'Business Intelligence'], ['analytique-ia', 'cpu', 'IA de vérification'], ['analytique-lms', 'graduation-cap', 'Académie'], ['analytique-seo', 'search', 'SEO'], ['economie', 'wallet', 'Revenus et quotas']]],
      ['Fact-checking', [['signalements', 'flame', 'Signalements', '27'], ['fact-checks', 'file-check', 'Fact-checks'], ['sources', 'shield-check', 'Sources vérifiées'], ['sources-categories', 'tags', 'Catégories de sources'], ['unites-ia', 'zap', 'Unités IA'], ['outils', 'wrench', 'Répertoire des outils']]],
      ['Académie', [['cours', 'library', 'Tous les cours'], ['constructeur', 'layout-template', 'Constructeur de cours'], ['categories', 'folder-tree', 'Catégories'], ['questions', 'list-checks', 'Banque de questions'], ['examens', 'file-check-2', 'Examens'], ['correction', 'pen-line', 'Correction', '5'], ['enrolement-cours', 'user-plus', 'Inscriptions aux cours'], ['enrolement-examens', 'clipboard-list', 'Inscriptions aux examens'], ['certificats', 'award', 'Certificats'], ['certificat-modele', 'scroll-text', 'Modèle de certificat'], ['unites-premium', 'badge-dollar-sign', 'Ventes premium'], ['stats-globales', 'chart-pie', 'Statistiques globales']]],
      ['Hub d\'infos', [['hub-actualites', 'newspaper', 'Actualités'], ['hub-portails', 'landmark', 'Portails officiels'], ['hub-jobs', 'briefcase', 'Offres d\'emploi']]],
      ['Communauté', [['forum-discussions', 'messages-square', 'Forum citoyen'], ['forum-categories', 'folder', 'Catégories du forum'], ['blog', 'pen-tool', 'Articles du blog'], ['blog-categories', 'folder', 'Catégories du blog'], ['editeur', 'square-pen', 'Nouvel article'], ['mediatheque', 'images', 'Médiathèque'], ['newsletters', 'mail', 'Infolettres'], ['faq', 'circle-help', 'FAQ'], ['temoignages', 'quote', 'Témoignages'], ['partenaires', 'handshake', 'Partenaires']]],
      ['Utilisateurs', [['utilisateurs', 'users', 'Citoyens'], ['mentors', 'presentation', 'Mentors'], ['kyc', 'id-card', 'Vérification KYC', '6'], ['roles', 'key-round', 'Rôles et permissions']]],
      ['Système', [['parametres', 'settings', 'Réglages'], ['emails-modeles', 'mails', 'Modèles de courriels'], ['campagnes', 'megaphone', 'Campagnes'], ['badges', 'medal', 'Badges'], ['sauvegardes', 'database-backup', 'Sauvegardes'], ['cron', 'timer', 'Tâches planifiées']]],
      ['Journaux', [['audit', 'scroll', 'Journal d\'audit'], ['logs-emails', 'mail-check', 'Courriels'], ['logs-unites', 'receipt', 'Unités'], ['alertes', 'shield-alert', 'Alertes d\'intrusion']]],
    ],
    mentor: [
      ['', [['m-tableau-de-bord', 'layout-dashboard', 'Tableau de bord'], ['m-live', 'video', 'Live classes']]],
      ['Espace pédagogique', [['m-cours', 'library', 'Mes cours'], ['m-sections', 'list-tree', 'Sections et leçons'], ['m-contenus', 'folder-open', 'Mes contenus'], ['m-quiz', 'list-checks', 'Quiz'], ['m-devoirs', 'pen-line', 'Devoirs à corriger', '5'], ['m-examens', 'file-check-2', 'Examens']]],
      ['Suivi', [['m-apprenants', 'users', 'Mes apprenants'], ['m-progression', 'trending-up', 'Progression'], ['m-certificats', 'award', 'Certificats'], ['m-performance', 'chart-line', 'Ma performance'], ['m-profil', 'id-card', 'Mon profil']]],
      ['Hub d\'infos', [['m-hub-actualites', 'newspaper', 'Actualités'], ['m-hub-portails', 'landmark', 'Portails officiels'], ['m-hub-jobs', 'briefcase', 'Offres d\'emploi']]],
      ['Communauté', [['m-forum-discussions', 'messages-square', 'Forum citoyen'], ['m-forum-categories', 'folder', 'Catégories du forum'], ['m-blog', 'pen-tool', 'Articles du blog'], ['m-blog-categories', 'folder', 'Catégories du blog'], ['m-editeur', 'square-pen', 'Nouvel article'], ['m-mediatheque', 'images', 'Médiathèque'], ['m-newsletters', 'mail', 'Infolettres'], ['m-faq', 'circle-help', 'FAQ'], ['m-temoignages', 'quote', 'Témoignages'], ['m-partenaires', 'handshake', 'Partenaires']]],
    ],
  };
  const ACCUEIL = { admin: 'tableau-de-bord', mentor: 'm-tableau-de-bord' };
  const PARTAGES = ['compte'];
  /* Écrans communs aux deux espaces : depuis l'espace mentor, un lien interne
     vers l'un d'eux (« #/blog » depuis l'éditeur, par exemple) reste dans
     l'espace mentor au lieu de basculer dans l'administration. */
  VF.MODULES_MENTOR = ['hub-actualites', 'hub-portails', 'hub-jobs', 'forum-discussions', 'forum-categories', 'blog', 'blog-categories', 'editeur', 'mediatheque', 'newsletters', 'faq', 'temoignages', 'partenaires', 'profil-citoyen'];

  VF.nettoyages = [];
  const replies = (() => { try { return new Set(JSON.parse(localStorage.getItem('vf-replies') || '[]')); } catch (e) { return new Set(); } })();

  const espaceDe = (id) => (id.startsWith('m-') ? 'mentor' : PARTAGES.includes(id) ? VF.espace || 'admin' : 'admin');

  const rendreNav = (actuel) => {
    const espace = VF.espace;
    $('#navigation').innerHTML = NAV[espace].map(([groupe, liens], gi) => {
      const ouvert = !replies.has(espace + gi) || liens.some((l) => l[0] === actuel);
      const items = liens.map(([id, ic, libelle, compte]) => `<li><a href="#/${id}" class="nav-lien" ${id === actuel ? 'aria-current="page"' : ''}>${icone(ic)}<span class="flex-1 truncate">${esc(libelle)}</span>${compte ? `<span class="text-[11px] font-medium px-1.5 rounded-full bg-white/10">${compte}</span>` : ''}</a></li>`).join('');
      if (!groupe) return `<ul class="pt-3 space-y-0.5">${items}</ul>`;
      return `<div><button class="nav-groupe" data-groupe="${espace + gi}" aria-expanded="${ouvert}"><span>${esc(groupe)}</span>${icone('chevron-down', `w-3.5 h-3.5 transition-transform ${ouvert ? '' : '-rotate-90'}`)}</button><ul class="space-y-0.5 ${ouvert ? '' : 'hidden'}">${items}</ul></div>`;
    }).join('');
    $$('[data-espace]').forEach((b) => { b.setAttribute('aria-pressed', String(b.dataset.espace === espace)); b.classList.toggle('!text-white', b.dataset.espace === espace); b.classList.toggle('!bg-white/[.14]', b.dataset.espace === espace); });
    const moi = VF.moi[espace];
    $('#espace-libelle').textContent = espace === 'admin' ? 'Console d\'administration' : 'Espace mentor';
    $('#moi-nom').textContent = moi.nom; $('#moi-role').textContent = moi.role; $('#moi-avatar').textContent = moi.initiales;
    $('#profil-avatar').textContent = moi.initiales;
    $('#profil-nom-court').textContent = moi.nom.split(' ')[0] + ' ' + (moi.nom.split(' ')[1] || '').charAt(0) + '.';
    $('#profil-role-court').textContent = espace === 'admin' ? 'Administrateur' : 'Mentor';
    $$('[data-groupe]').forEach((b) => b.addEventListener('click', () => {
      const ul = b.nextElementSibling, ferme = !ul.classList.toggle('hidden');
      b.setAttribute('aria-expanded', String(ferme));
      b.querySelector('svg').classList.toggle('-rotate-90', !ferme);
      ferme ? replies.delete(b.dataset.groupe) : replies.add(b.dataset.groupe);
      try { localStorage.setItem('vf-replies', JSON.stringify([...replies])); } catch (e) { /* préférence locale seulement */ }
    }));
    icones();
    const lienActif = $('#navigation [aria-current="page"]');
    if (lienActif) lienActif.scrollIntoView({ block: 'nearest' });
  };

  const AUTRES_TITRES = { compte: 'Mon compte', 'profil-citoyen': 'Fiche citoyen', 'profil-mentor': 'Fiche mentor', 'm-profil-citoyen': 'Fiche apprenant', 'email-modele': 'Modèle de courriel' };
  const libelleDe = (id) => { for (const e of Object.values(NAV)) for (const [, liens] of e) for (const l of liens) if (l[0] === id) return l[2]; return AUTRES_TITRES[id] || id; };

  const router = () => {
    const [chemin, requete] = (location.hash.replace(/^#\/?/, '') || '').split('?');
    const params = Object.fromEntries(new URLSearchParams(requete || ''));
    let id = chemin || ACCUEIL[VF.espace || 'admin'];
    if (VF.espace === 'mentor' && VF.MODULES_MENTOR.includes(id) && VF.ecrans['m-' + id]) {
      history.replaceState(null, '', '#/m-' + id + (requete ? '?' + requete : ''));
      id = 'm-' + id;
    }
    if (!VF.ecrans[id]) id = ACCUEIL[VF.espace || 'admin'];
    VF.espace = espaceDe(id);

    VF.g.detruireTout();
    while (VF.nettoyages.length) { try { VF.nettoyages.pop()(); } catch (e) { /* déjà nettoyé */ } }
    const contenu = $('#contenu');
    /* Un conteneur neuf par écran : les écouteurs posés par l'écran précédent
       disparaissent avec lui, au lieu de s'empiler. */
    const zone = document.createElement('div');
    contenu.replaceChildren(zone);
    try {
      VF.ecrans[id](zone, params);
    } catch (e) {
      console.error(e);
      zone.innerHTML = VF.ui.vide({ ic: 'bug', titre: 'Cet écran n\'a pas pu s\'afficher', texte: e.message });
    }
    rendreNav(id);
    document.title = `${libelleDe(id)} — Vérif'All`;
    fermerColonne();
    icones();
    window.scrollTo(0, 0);
    contenu.focus({ preventScroll: true });
  };

  /* ---------- Colonne mobile ---------- */
  const ouvrirColonne = () => { $('#colonne').classList.remove('-translate-x-full'); $('#voile-colonne').classList.remove('hidden'); };
  const fermerColonne = () => { if (window.innerWidth < 1024) { $('#colonne').classList.add('-translate-x-full'); $('#voile-colonne').classList.add('hidden'); } };
  $('[data-ouvrir-colonne]').addEventListener('click', ouvrirColonne);
  $('[data-fermer-colonne]').addEventListener('click', fermerColonne);

  $$('[data-espace]').forEach((b) => b.addEventListener('click', () => { VF.espace = b.dataset.espace; location.hash = '#/' + ACCUEIL[b.dataset.espace]; }));

  /* ---------- Thème ---------- */
  VF.appliquerTheme = (choix) => {
    const sombre = choix === 'sombre' || (choix === 'systeme' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', sombre);
    try { choix === 'systeme' ? localStorage.removeItem('vf-theme') : localStorage.setItem('vf-theme', sombre ? 'dark' : 'light'); } catch (e) { /* thème non mémorisé */ }
    majIconeTheme();
    if (!location.hash.startsWith('#/compte')) router();
  };
  const majIconeTheme = () => { $('#bascule-theme').innerHTML = icone(document.documentElement.classList.contains('dark') ? 'sun' : 'moon'); icones(); };
  $('#bascule-theme').addEventListener('click', () => VF.appliquerTheme(document.documentElement.classList.contains('dark') ? 'clair' : 'sombre'));

  /* ---------- Notifications ---------- */
  const panneau = $('#panneau-notifs');
  panneau.innerHTML = `<div class="flex items-center justify-between px-3 pt-1.5 pb-2"><p class="font-titre font-semibold">Notifications</p><button class="text-xs texte-2 hover:underline" data-tout-lu>Tout marquer comme lu</button></div>` +
    VF.notifications.map((n) => `<a href="${n.lien}" class="flex gap-3 px-3 py-2.5 hover:bg-papier dark:hover:bg-nuit"><span class="mt-0.5 ${{ critique: 'text-red-700 dark:text-red-400', alerte: 'text-amber-700 dark:text-amber-300', info: 'text-blue-700 dark:text-blue-300' }[n.ton]}">${icone(n.icone, 'w-[18px] h-[18px]')}</span><span class="min-w-0"><span class="block text-sm font-medium">${esc(n.titre)}</span><span class="block text-xs texte-2">${esc(n.detail)}</span></span></a>`).join('');
  const bNotifs = $('#bouton-notifs');
  bNotifs.addEventListener('click', (e) => { e.stopPropagation(); $('#menu-profil').classList.add('hidden'); $('#bouton-profil').setAttribute('aria-expanded', 'false'); const ouvert = panneau.classList.toggle('hidden'); bNotifs.setAttribute('aria-expanded', String(!ouvert)); icones(); });
  document.addEventListener('click', (e) => { if (!panneau.contains(e.target)) { panneau.classList.add('hidden'); bNotifs.setAttribute('aria-expanded', 'false'); } });
  panneau.querySelector('[data-tout-lu]').addEventListener('click', () => { bNotifs.querySelector('span')?.remove(); toast('Notifications marquées comme lues.', 'info'); });

  /* ---------- Plein écran ---------- */
  const bPlein = $('#bascule-plein-ecran');
  const pleinPossible = document.fullscreenEnabled || document.webkitFullscreenEnabled;
  const enPlein = () => document.fullscreenElement || document.webkitFullscreenElement;
  const majPlein = () => {
    const actif = !!enPlein();
    bPlein.innerHTML = icone(actif ? 'minimize' : 'maximize');
    bPlein.setAttribute('aria-pressed', String(actif));
    bPlein.setAttribute('aria-label', actif ? 'Quitter le plein écran' : 'Plein écran');
    bPlein.title = actif ? 'Quitter le plein écran (Échap)' : 'Plein écran';
    icones();
  };
  if (!pleinPossible) bPlein.remove();
  else {
    bPlein.addEventListener('click', () => {
      const r = document.documentElement;
      const p = enPlein() ? (document.exitFullscreen || document.webkitExitFullscreen).call(document) : (r.requestFullscreen || r.webkitRequestFullscreen).call(r);
      if (p && p.catch) p.catch(() => toast('Le navigateur a refusé le plein écran.', 'alerte'));
    });
    document.addEventListener('fullscreenchange', majPlein);
    document.addEventListener('webkitfullscreenchange', majPlein);
  }

  /* ---------- Menu du profil ---------- */
  const bProfil = $('#bouton-profil'), mProfil = $('#menu-profil');
  const fermerProfil = (rendreFocus) => { mProfil.classList.add('hidden'); bProfil.setAttribute('aria-expanded', 'false'); if (rendreFocus) bProfil.focus(); };
  const rendreProfil = () => {
    const espace = VF.espace || 'admin', moi = VF.moi[espace], sombre = document.documentElement.classList.contains('dark');
    const lien = (href, ic, libelle, extra = '') => `<a href="${href}" class="menu-item" role="menuitem">${icone(ic)}<span class="flex-1">${esc(libelle)}</span>${extra}</a>`;
    const bouton = (action, ic, libelle, extra = '', classe = '') => `<button class="menu-item ${classe}" role="menuitem" data-profil="${action}">${icone(ic, classe ? '!text-current' : '')}<span class="flex-1 text-left">${esc(libelle)}</span>${extra}</button>`;
    mProfil.innerHTML = `
      <div class="flex items-center gap-3 px-3 pt-2 pb-3">
        <span class="avatar w-11 h-11 !text-sm">${esc(moi.initiales)}</span>
        <div class="min-w-0"><p class="font-medium truncate">${esc(moi.nom)}</p><p class="text-xs texte-3 truncate">${esc(moi.email)}</p>
          <p class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-green-800 dark:text-bon"><span class="w-1.5 h-1.5 rounded-full bg-bon"></span>En ligne — ${esc(moi.role)}</p></div>
      </div>
      <div class="my-1 border-t filet"></div>
      ${lien(espace === 'admin' ? '#/compte' : '#/m-profil', 'circle-user-round', espace === 'admin' ? 'Mon profil' : 'Mon profil et mon activité')}
      ${lien('#/compte?onglet=securite', 'shield-check', 'Sécurité et connexions')}
      ${lien('#/compte?onglet=pref', 'sliders-horizontal', 'Préférences')}
      ${lien(espace === 'admin' ? '#/parametres' : '#/m-performance', espace === 'admin' ? 'settings' : 'chart-line', espace === 'admin' ? 'Réglages de la plateforme' : 'Ma performance')}
      <div class="my-1 border-t filet"></div>
      ${bouton('espace', 'arrow-left-right', espace === 'admin' ? 'Passer à l\'espace mentor' : 'Passer à l\'administration')}
      ${bouton('theme', sombre ? 'sun' : 'moon', sombre ? 'Thème clair' : 'Thème sombre')}
      ${bouton('commande', 'command', 'Raccourcis et recherche', '<span class="kbd">Ctrl K</span>')}
      ${lien(espace === 'admin' ? '#/faq' : '#/m-faq', 'life-buoy', 'Aide et FAQ')}
      <div class="my-1 border-t filet"></div>
      ${bouton('sortir', 'log-out', 'Se déconnecter', '', '!text-red-700 dark:!text-red-400')}`;
    icones();
  };
  bProfil.addEventListener('click', (e) => {
    e.stopPropagation();
    const ouvrir = mProfil.classList.contains('hidden');
    panneau.classList.add('hidden'); bNotifs.setAttribute('aria-expanded', 'false');
    if (!ouvrir) return fermerProfil();
    rendreProfil();
    mProfil.classList.remove('hidden'); bProfil.setAttribute('aria-expanded', 'true');
    const premier = mProfil.querySelector('.menu-item'); if (premier) premier.focus();
  });
  mProfil.addEventListener('keydown', (e) => {
    const items = $$('.menu-item', mProfil), i = items.indexOf(document.activeElement);
    if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length].focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length].focus(); }
    else if (e.key === 'Home') { e.preventDefault(); items[0].focus(); }
    else if (e.key === 'End') { e.preventDefault(); items[items.length - 1].focus(); }
    else if (e.key === 'Escape') { e.stopPropagation(); fermerProfil(true); }
    else if (e.key === 'Tab') fermerProfil();
  });
  mProfil.addEventListener('click', (e) => {
    const t = e.target.closest('.menu-item');
    if (!t) return;
    fermerProfil();
    const action = t.dataset.profil;
    if (action === 'espace') { const cible = VF.espace === 'mentor' ? 'admin' : 'mentor'; VF.espace = cible; location.hash = '#/' + ACCUEIL[cible]; }
    else if (action === 'theme') VF.appliquerTheme(document.documentElement.classList.contains('dark') ? 'clair' : 'sombre');
    else if (action === 'commande') ouvrirCommande();
    else if (action === 'sortir') VF.ui.confirmer({ titre: 'Se déconnecter ?', texte: 'Votre session sera fermée sur cet appareil. Les brouillons non enregistrés seront perdus.', bouton: 'Se déconnecter', siOui: () => toast('Vous êtes déconnecté. Dans l\'application, vous seriez renvoyé vers la page de connexion.', 'info') });
  });
  document.addEventListener('click', (e) => { if (!mProfil.contains(e.target) && !bProfil.contains(e.target)) fermerProfil(); });

  /* ---------- Palette de commandes (Ctrl K) ---------- */
  const ouvrirCommande = () => {
    const c = $('#commande');
    const entrees = [];
    Object.entries(NAV).forEach(([espace, groupes]) => groupes.forEach(([g, liens]) => liens.forEach(([id, ic, l]) => entrees.push({ id, ic, l, g: (espace === 'mentor' ? 'Mentor' : 'Admin') + (g ? ' — ' + g : '') }))));
    VF.signalements.slice(0, 6).forEach((s) => entrees.push({ id: 'signalements', ic: 'flame', l: s.id + ' — ' + s.texte, g: 'Signalements' }));
    VF.cours.forEach((k) => entrees.push({ id: 'cours', ic: 'library', l: k.titre, g: 'Cours' }));
    entrees.push({ id: 'compte', ic: 'user', l: 'Mon compte', g: 'Compte' });

    c.innerHTML = `<div class="voile !z-[55]" data-fermer></div>
      <div class="fixed inset-x-0 top-[12vh] z-[56] px-4"><div class="carte shadow-flottant max-w-xl mx-auto overflow-hidden" role="dialog" aria-modal="true" aria-label="Recherche">
        <div class="flex items-center gap-3 px-4 border-b filet">${icone('search', 'w-[18px] h-[18px] texte-3')}<input class="flex-1 h-14 bg-transparent text-base focus:outline-none" placeholder="Aller à un écran, un cours, un signalement…" aria-label="Rechercher" data-q><span class="kbd">Échap</span></div>
        <ul class="max-h-[50vh] overflow-y-auto py-2" data-res role="listbox"></ul>
      </div></div>`;
    c.classList.remove('hidden');
    let sel = 0, liste = [];
    const rendre = (q) => {
      const t = q.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
      liste = entrees.filter((e) => (e.l + ' ' + e.g).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').includes(t)).slice(0, 12);
      sel = Math.min(sel, Math.max(0, liste.length - 1));
      $('[data-res]', c).innerHTML = liste.length ? liste.map((e, i) => `<li role="option" aria-selected="${i === sel}"><a href="#/${e.id}" class="flex items-center gap-3 px-4 h-11 text-sm ${i === sel ? 'bg-brand-50 dark:bg-brand/10' : ''}">${icone(e.ic, 'w-4 h-4 texte-3')}<span class="flex-1 truncate">${esc(e.l)}</span><span class="text-xs texte-3">${esc(e.g)}</span></a></li>`).join('') : `<li class="px-4 py-6 text-sm texte-2 text-center">Rien ne correspond à « ${esc(q)} ».</li>`;
      icones();
    };
    const fermer = () => { c.classList.add('hidden'); c.innerHTML = ''; };
    const q = $('[data-q]', c);
    q.addEventListener('input', () => { sel = 0; rendre(q.value); });
    q.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, liste.length - 1); rendre(q.value); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); rendre(q.value); }
      else if (e.key === 'Enter' && liste[sel]) { location.hash = '#/' + liste[sel].id; fermer(); }
      else if (e.key === 'Escape') fermer();
    });
    $('[data-fermer]', c).addEventListener('click', fermer);
    $('[data-res]', c).addEventListener('click', (e) => { if (e.target.closest('a')) fermer(); });
    rendre(''); q.focus();
  };
  $('#ouvrir-commande').addEventListener('click', ouvrirCommande);
  $('[data-commande]').addEventListener('click', ouvrirCommande);
  document.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); ouvrirCommande(); } });

  /* Les filtres de période sans traitement propre marquent au moins le choix. */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-periode]');
    if (!b) return;
    b.parentElement.querySelectorAll('[data-periode]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
  });

  window.addEventListener('hashchange', router);
  majIconeTheme();
  router();
})(window.VF);
