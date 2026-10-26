/*
 | Les écrans de gestion, décrits par leur définition.
 | Chaque entrée correspond à un écran du prototype d'origine (compte-admin/*.html,
 | compte-mentor/*.html), enrichi : filtres, actions groupées, export, statuts.
 */
(function (VF) {
  'use strict';
  const { esc, fmt, icone, pastille, toast, tiroir, carte } = VF.ui;
  const { entre, choisir, personne, jours } = VF.outils;
  VF.ecrans = VF.ecrans || {};

  /* Jeux de statuts réutilisés. Toujours une icône et un mot. */
  const S = {
    publication: { publie: { classe: 'p-bon', ic: 'circle-check', libelle: 'Publié' }, brouillon: { classe: 'p-neutre', ic: 'file-pen-line', libelle: 'Brouillon' }, revue: { classe: 'p-info', ic: 'eye', libelle: 'En relecture' }, archive: { classe: 'p-neutre', ic: 'archive', libelle: 'Archivé' } },
    activite: { actif: { classe: 'p-bon', ic: 'circle-check', libelle: 'Actif' }, inactif: { classe: 'p-neutre', ic: 'circle-pause', libelle: 'Inactif' }, suspendu: { classe: 'p-critique', ic: 'ban', libelle: 'Suspendu' }, attente: { classe: 'p-alerte', ic: 'clock', libelle: 'En attente' }, revue: { classe: 'p-alerte', ic: 'eye', libelle: 'À revoir' }, termine: { classe: 'p-info', ic: 'flag', libelle: 'Terminé' } },
    paiement: { confirme: { classe: 'p-bon', ic: 'circle-check', libelle: 'Confirmé' }, attente: { classe: 'p-alerte', ic: 'clock', libelle: 'En attente' }, echoue: { classe: 'p-critique', ic: 'circle-x', libelle: 'Échoué' }, rembourse: { classe: 'p-neutre', ic: 'undo-2', libelle: 'Remboursé' } },
    niveau: { info: { classe: 'p-info', ic: 'info', libelle: 'Info' }, alerte: { classe: 'p-alerte', ic: 'triangle-alert', libelle: 'Alerte' }, critique: { classe: 'p-critique', ic: 'siren', libelle: 'Critique' } },
    signalement: { nouveau: { classe: 'p-critique', ic: 'flame', libelle: 'Nouveau' }, en_cours: { classe: 'p-info', ic: 'loader', libelle: 'En analyse' }, revue: { classe: 'p-alerte', ic: 'eye', libelle: 'En relecture' }, publie: { classe: 'p-bon', ic: 'circle-check', libelle: 'Publié' } },
  };
  VF.statuts = S;
  const verdicts = Object.fromEntries(Object.entries(VF.verdicts).map(([k, v]) => [k, { classe: v.classe, ic: v.icone, libelle: v.libelle }]));
  const opts = (etats) => Object.entries(etats).map(([valeur, e]) => ({ valeur, libelle: e.libelle }));
  const n = (k, f) => Array.from({ length: k }, (_, i) => f(i));

  const definir = (id, def) => { VF.ecrans[id] = (racine) => VF.gestion.ecran(racine, { id, ...def }); };

  /* ===================== Fact-checking ===================== */

  const detailSignalement = (s) => tiroir({
    titre: s.id, sousTitre: `Reçu par ${s.canal} — ${s.ville}`, large: true,
    corps: `
      <blockquote class="font-titre text-xl leading-snug">« ${esc(s.texte)} »</blockquote>
      <div class="mt-4 flex flex-wrap gap-2">${pastille(S.signalement[s.etat].classe, S.signalement[s.etat].ic, S.signalement[s.etat].libelle)}${s.verdict ? pastille(verdicts[s.verdict].classe, verdicts[s.verdict].ic, verdicts[s.verdict].libelle) : ''}<span class="p-neutre">${icone('tag')}${esc(s.theme)}</span></div>
      <dl class="mt-6 grid grid-cols-2 gap-4 text-sm">
        <div><dt class="texte-3">Viralité estimée</dt><dd class="mt-1 font-medium">${s.viralite} / 100</dd></div>
        <div><dt class="texte-3">Confiance de l'analyse IA</dt><dd class="mt-1 font-medium">${s.confiance} %</dd></div>
        <div><dt class="texte-3">Signalé par</dt><dd class="mt-1 font-medium">${esc(s.auteur.nom)}</dd></div>
        <div><dt class="texte-3">Analyste assigné</dt><dd class="mt-1 font-medium">${esc(s.analyste.nom)}</dd></div>
      </dl>
      <h3 class="mt-7 font-titre font-semibold">Parcours de l'analyse</h3>
      <ol class="mt-3 space-y-4 border-l filet pl-5">
        ${[['inbox', 'Signalement reçu', 'Classé « ' + s.theme + ' » automatiquement'], ['cpu', '7 moteurs interrogés', 'Google Fact Check, Gemini, TinEye, Wayback Machine…'], ['scale', 'Sources croisées', '3 sources de confiance citées, 1 contradiction résolue'], ['user-check', 'Relecture humaine', s.etat === 'publie' ? 'Validée par ' + s.analyste.nom : 'En attente']].map(([ic, t, d]) => `
          <li class="relative"><span class="absolute -left-[31px] top-0 inline-flex w-6 h-6 items-center justify-center rounded-full bg-white dark:bg-nuit-carte border filet">${icone(ic, 'w-3.5 h-3.5')}</span><p class="font-medium text-sm">${t}</p><p class="text-xs texte-2 mt-0.5">${esc(d)}</p></li>`).join('')}
      </ol>
      <div class="mt-7">
        <label class="etiquette" for="verdict-final">Verdict à publier</label>
        <select id="verdict-final" class="champ">${Object.values(VF.verdicts).map((v) => `<option>${v.libelle}</option>`).join('')}</select>
      </div>`,
    pied: '<button class="btn-second" data-fermer>Fermer</button><button class="btn-primaire" data-publier>' + icone('send') + 'Publier le fact-check</button>',
    apres: (c) => c.querySelector('[data-publier]').addEventListener('click', () => { VF.ui.fermerCouche('tiroir'); toast(`${s.id} publié. Les abonnés du thème « ${s.theme} » sont notifiés.`); }),
  });

  definir('signalements', {
    titre: 'Signalements', sousTitre: 'Ce que les citoyens demandent de vérifier. Les plus viraux remontent en tête.', fil: ['Fact-checking'],
    entite: 'signalement', placeRecherche: 'Chercher une affirmation, un auteur, un numéro…', plage: true,
    kpis: [
      { libelle: 'À traiter', valeur: '27', delta: 12, sens: 'bas-bon', aide: 'sur 7 jours', ic: 'inbox' },
      { libelle: 'Délai médian de traitement', valeur: '5 h 40', delta: -18, sens: 'bas-bon', aide: 'objectif : 6 h', ic: 'timer' },
      { libelle: 'Viraux en attente', valeur: '3', aide: 'viralité > 70', ic: 'flame' },
      { libelle: 'Publiés ce mois', valeur: '412', delta: 9, aide: 'vs septembre', ic: 'file-check' },
    ],
    colonnes: [
      { cle: 'texte', libelle: 'Affirmation', type: 'titre', sous: 'id' },
      { cle: 'canal', libelle: 'Canal', muet: true },
      { cle: 'viralite', libelle: 'Viralité', type: 'jauge' },
      { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.signalement },
      { cle: 'recu', libelle: 'Reçu le', type: 'date' },
    ],
    filtres: [{ cle: 'etat', libelle: 'État', options: opts(S.signalement) }, { cle: 'canal', libelle: 'Canal', options: ['WhatsApp', 'Facebook', 'TikTok', 'Tipline', 'Site web', 'X'] }],
    lignes: VF.signalements, detail: detailSignalement,
    actionsLigne: (l) => [{ libelle: 'Ouvrir l\'analyse', ic: 'scan-search', action: () => detailSignalement(l) }, { libelle: 'Assigner à un analyste', ic: 'user-plus', action: () => toast('Assigné à ' + l.analyste.nom + '.') }, { libelle: 'Marquer comme doublon', ic: 'copy', action: () => toast('Fusionné avec le signalement le plus ancien.', 'info') }],
    actionsGroupees: [{ libelle: 'Assigner', ic: 'user-plus' }, { libelle: 'Changer de thème', ic: 'tag' }, { libelle: 'Supprimer', ic: 'trash-2', danger: true }],
    supprimable: false,
  });

  definir('fact-checks', {
    titre: 'Fact-checks publiés', sousTitre: 'La base de connaissances : chaque verdict publié, ses sources et son audience.', fil: ['Fact-checking'],
    entite: 'fact-check', plage: true,
    colonnes: [
      { cle: 'texte', libelle: 'Affirmation vérifiée', type: 'titre', sous: 'theme' },
      { cle: 'verdict', libelle: 'Verdict', type: 'pastille', etats: verdicts },
      { cle: 'vues', libelle: 'Lectures', type: 'nombre' },
      { cle: 'partages', libelle: 'Partages', type: 'nombre' },
      { cle: 'date', libelle: 'Publié le', type: 'date' },
    ],
    filtres: [{ cle: 'verdict', libelle: 'Verdict', options: opts(verdicts) }, { cle: 'theme', libelle: 'Thème', options: ['Santé', 'Politique', 'Économie', 'Société', 'Éducation', 'Arnaque'] }],
    lignes: VF.signalements.map((s) => ({ texte: s.texte, theme: s.theme, verdict: s.verdict || choisir(['faux', 'trompeur', 'vrai']), vues: entre(800, 48000), partages: entre(40, 6200), date: jours(entre(1, 90)) })),
    champs: [{ nom: 'texte', libelle: 'Affirmation', regles: 'requis|min:15', aide: 'Formulez-la comme elle circule.' }, { nom: 'verdict', libelle: 'Verdict', options: Object.values(VF.verdicts).map((v) => v.libelle) }, { nom: 'theme', libelle: 'Thème', options: ['Santé', 'Politique', 'Économie', 'Société', 'Éducation', 'Arnaque'] }, { nom: 'resume', libelle: 'Résumé du verdict', type: 'textarea', regles: 'requis', aide: 'Deux phrases lisibles par tous.' }],
  });

  definir('sources', {
    titre: 'Sources vérifiées', sousTitre: 'Les sources que l\'orchestrateur cite en priorité, avec leur score de fiabilité.', fil: ['Fact-checking'],
    entite: 'source', feminin: true,
    colonnes: [{ cle: 'nom', libelle: 'Source', type: 'titre', sous: 'categorie' }, { cle: 'score', libelle: 'Fiabilité', type: 'jauge' }, { cle: 'citations', libelle: 'Citations', type: 'nombre' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.activite }, { cle: 'maj', libelle: 'Revue le', type: 'date' }],
    filtres: [{ cle: 'categorie', libelle: 'Catégorie', options: ['Institution', 'Média', 'Fact-checkeur', 'Statistique'] }],
    lignes: VF.sources,
    champs: [{ nom: 'nom', libelle: 'Nom de la source', regles: 'requis' }, { nom: 'url', libelle: 'Adresse du site', type: 'url', regles: 'requis', place: 'https://' }, { nom: 'categorie', libelle: 'Catégorie', options: ['Institution', 'Média', 'Fact-checkeur', 'Statistique'] }, { nom: 'score', libelle: 'Score de fiabilité (0–100)', type: 'number', regles: 'requis|nombre', valeur: '80' }],
  });

  definir('sources-categories', {
    titre: 'Catégories de sources', fil: ['Fact-checking'], entite: 'catégorie', feminin: true,
    colonnes: [{ cle: 'nom', libelle: 'Catégorie', type: 'titre', sous: 'description' }, { cle: 'sources', libelle: 'Sources', type: 'nombre' }, { cle: 'poids', libelle: 'Poids dans le score', type: 'pct' }],
    lignes: [['Institution', 'Ministères, agences publiques', 34, 40], ['Média', 'Presse reconnue', 51, 25], ['Fact-checkeur', 'Membres de l\'IFCN', 12, 25], ['Statistique', 'Instituts et bases de données', 9, 10]].map(([nom, description, sources, poids]) => ({ nom, description, sources, poids })),
    champs: [{ nom: 'nom', libelle: 'Nom', regles: 'requis' }, { nom: 'description', libelle: 'Description', type: 'textarea' }, { nom: 'poids', libelle: 'Poids (%)', type: 'number', regles: 'nombre' }],
  });

  definir('unites-ia', {
    titre: 'Historique des unités IA', sousTitre: 'Chaque analyse consomme des unités. Voici qui consomme, combien, et pour quel moteur.', fil: ['Fact-checking'], entite: 'mouvement', plage: true, supprimable: false,
    kpis: [{ libelle: 'Consommées ce mois', valeur: fmt.compact(96400), delta: 14, aide: 'vs septembre', ic: 'zap' }, { libelle: 'Achetées ce mois', valeur: fmt.compact(118000), delta: 22, ic: 'coins' }, { libelle: 'Dotation gratuite restante', valeur: '31 %', aide: 'remise à zéro le 1er', ic: 'gift' }, { libelle: 'Coût moyen d\'une analyse', valeur: '6,4 u.', delta: -4, sens: 'bas-bon', ic: 'calculator' }],
    colonnes: [{ cle: 'qui', libelle: 'Compte', type: 'personne' }, { cle: 'moteur', libelle: 'Moteur', muet: true }, { cle: 'unites', libelle: 'Unités', type: 'nombre' }, { cle: 'type', libelle: 'Mouvement', type: 'pastille', etats: { debit: { classe: 'p-neutre', ic: 'minus', libelle: 'Débit' }, credit: { classe: 'p-bon', ic: 'plus', libelle: 'Crédit' } } }, { cle: 'date', libelle: 'Date', type: 'date' }],
    filtres: [{ cle: 'type', libelle: 'Mouvement', options: [{ valeur: 'debit', libelle: 'Débit' }, { valeur: 'credit', libelle: 'Crédit' }] }],
    lignes: n(30, () => ({ qui: personne(), moteur: choisir(VF.moteurs).nom, unites: entre(2, 40), type: choisir(['debit', 'debit', 'debit', 'credit']), date: jours(entre(0, 20)) })),
  });

  definir('outils', {
    titre: 'Répertoire des outils', sousTitre: 'Les outils de vérification recommandés aux citoyens et aux mentors.', fil: ['Fact-checking'], entite: 'outil',
    colonnes: [{ cle: 'nom', libelle: 'Outil', type: 'titre', sous: 'usage' }, { cle: 'type', libelle: 'Type', muet: true }, { cle: 'clics', libelle: 'Ouvertures', type: 'nombre' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.publication }],
    lignes: [['InVID-WeVerify', 'Analyser une vidéo', 'Vidéo'], ['TinEye', 'Recherche d\'image inversée', 'Image'], ['Wayback Machine', 'Voir une page dans le passé', 'Archive'], ['Whois', 'Qui possède un domaine', 'Domaine'], ['FotoForensics', 'Détecter une retouche', 'Image'], ['Google Fact Check Explorer', 'Chercher un verdict existant', 'Base de faits']].map(([nom, usage, type], i) => ({ nom, usage, type, clics: entre(120, 4800), etat: i === 4 ? 'brouillon' : 'publie' })),
    champs: [{ nom: 'nom', libelle: 'Nom', regles: 'requis' }, { nom: 'usage', libelle: 'À quoi il sert', regles: 'requis' }, { nom: 'url', libelle: 'Adresse', type: 'url', regles: 'requis' }, { nom: 'type', libelle: 'Type', options: ['Image', 'Vidéo', 'Archive', 'Domaine', 'Base de faits'] }],
  });

  /* ===================== Académie ===================== */

  definir('categories', {
    titre: 'Catégories de cours', fil: ['Académie'], entite: 'catégorie', feminin: true,
    colonnes: [{ cle: 'nom', libelle: 'Catégorie', type: 'titre' }, { cle: 'cours', libelle: 'Cours', type: 'nombre' }, { cle: 'inscrits', libelle: 'Inscrits', type: 'nombre' }],
    lignes: ['Enquête', 'Image', 'Vidéo', 'Esprit critique', 'Sécurité', 'Éducation'].map((nom) => ({ nom, cours: entre(1, 9), inscrits: entre(300, 4000) })),
    champs: [{ nom: 'nom', libelle: 'Nom', regles: 'requis' }, { nom: 'description', libelle: 'Description', type: 'textarea' }],
  });

  const carteCours = (c) => `
    <article class="carte overflow-hidden flex flex-col">
      <img src="${esc(c.image)}" alt="" class="h-36 w-full object-cover">
      <div class="p-4 flex-1 flex flex-col">
        <p class="text-xs texte-3">${esc(c.categorie)} — ${esc(c.niveau)}</p>
        <h3 class="mt-1 font-titre font-semibold leading-snug">${esc(c.titre)}</h3>
        <div class="mt-3 flex items-center gap-3 text-xs texte-2"><span class="inline-flex items-center gap-1">${icone('users', 'w-3.5 h-3.5')}${fmt.nombre(c.inscrits)}</span><span class="inline-flex items-center gap-1">${icone('book-open', 'w-3.5 h-3.5')}${c.lecons} leçons</span><span class="inline-flex items-center gap-1">${icone('star', 'w-3.5 h-3.5')}${c.note}</span></div>
        <div class="mt-auto pt-4"><div class="flex justify-between text-xs mb-1.5"><span class="texte-2">Complétion moyenne</span><span class="font-medium">${c.completion} %</span></div><div class="jauge"><span style="width:${c.completion}%"></span></div></div>
      </div>
    </article>`;
  VF.carteCours = carteCours;

  definir('cours', {
    titre: 'Tous les cours', sousTitre: 'Le catalogue de l\'Académie. Basculez en cartes pour voir les visuels.', fil: ['Académie'], entite: 'cours',
    actionsEnTete: `<a href="#/constructeur" class="btn-second">${icone('layout-template')}Constructeur</a>`,
    kpis: [{ libelle: 'Cours publiés', valeur: '24', delta: 8, ic: 'library' }, { libelle: 'Apprenants actifs', valeur: fmt.nombre(4182), delta: 11, ic: 'users' }, { libelle: 'Complétion moyenne', valeur: '61 %', delta: 3, ic: 'circle-check' }, { libelle: 'Revenus premium (mois)', valeur: fmt.compact(1840000) + ' FCFA', delta: 17, ic: 'wallet' }],
    colonnes: [{ cle: 'image', libelle: '', type: 'image', tri: false }, { cle: 'titre', libelle: 'Cours', type: 'titre', sous: 'mentor' }, { cle: 'inscrits', libelle: 'Inscrits', type: 'nombre' }, { cle: 'completion', libelle: 'Complétion', type: 'jauge' }, { cle: 'note', libelle: 'Note', type: 'etoiles' }, { cle: 'prix', libelle: 'Prix', type: 'fcfa' }],
    filtres: [{ cle: 'niveau', libelle: 'Niveau', options: ['Débutant', 'Intermédiaire', 'Avancé'] }, { cle: 'categorie', libelle: 'Catégorie', options: ['Enquête', 'Image', 'Vidéo', 'Esprit critique', 'Sécurité', 'Éducation'] }],
    lignes: VF.cours, vueCartes: carteCours,
    actionsLigne: () => [{ libelle: 'Ouvrir dans le constructeur', ic: 'layout-template', action: () => (location.hash = '#/constructeur') }, { libelle: 'Voir les statistiques', ic: 'chart-line', action: () => (location.hash = '#/analytique-lms') }, { libelle: 'Dupliquer', ic: 'copy', action: () => toast('Cours dupliqué en brouillon.') }],
    champs: [{ nom: 'titre', libelle: 'Titre du cours', regles: 'requis|min:6' }, { nom: 'categorie', libelle: 'Catégorie', options: ['Enquête', 'Image', 'Vidéo', 'Esprit critique', 'Sécurité', 'Éducation'] }, { nom: 'niveau', libelle: 'Niveau', options: ['Débutant', 'Intermédiaire', 'Avancé'] }, { nom: 'mentor', libelle: 'Mentor responsable', options: VF.equipe.filter((e) => e.role === 'Mentor').map((e) => e.nom) }, { nom: 'prix', libelle: 'Prix en unités (0 = gratuit)', type: 'number', regles: 'nombre', valeur: '0' }],
  });

  definir('questions', {
    titre: 'Banque de questions', sousTitre: 'Les questions réutilisables dans les quiz et les examens.', fil: ['Académie'], entite: 'question', feminin: true,
    colonnes: [{ cle: 'q', libelle: 'Question', type: 'titre', sous: 'cours' }, { cle: 'type', libelle: 'Type', muet: true }, { cle: 'difficulte', libelle: 'Difficulté', type: 'pastille', etats: { facile: { classe: 'p-bon', ic: 'signal-low', libelle: 'Facile' }, moyen: { classe: 'p-alerte', ic: 'signal-medium', libelle: 'Moyen' }, difficile: { classe: 'p-critique', ic: 'signal-high', libelle: 'Difficile' } } }, { cle: 'reussite', libelle: 'Taux de réussite', type: 'jauge' }],
    filtres: [{ cle: 'type', libelle: 'Type', options: ['Choix unique', 'Choix multiple', 'Vrai ou faux', 'Réponse courte'] }],
    lignes: ['Quel outil permet de retrouver la première publication d\'une image ?', 'Une capture d\'écran prouve-t-elle qu\'un tweet a existé ?', 'Que vérifier en premier dans une URL suspecte ?', 'Qu\'est-ce qu\'une source primaire ?', 'Citez deux indices d\'une image générée par IA.', 'Que révèle une recherche Whois ?', 'Pourquoi dater une vidéo avant de la partager ?', 'Quel est le rôle de l\'IFCN ?'].map((q) => ({ q, cours: choisir(VF.cours).titre, type: choisir(['Choix unique', 'Choix multiple', 'Vrai ou faux', 'Réponse courte']), difficulte: choisir(['facile', 'moyen', 'difficile']), reussite: entre(34, 92) })),
    champs: [{ nom: 'q', libelle: 'Énoncé', type: 'textarea', regles: 'requis|min:10' }, { nom: 'type', libelle: 'Type', options: ['Choix unique', 'Choix multiple', 'Vrai ou faux', 'Réponse courte'] }, { nom: 'difficulte', libelle: 'Difficulté', options: ['facile', 'moyen', 'difficile'] }, { nom: 'bonne', libelle: 'Bonne réponse', regles: 'requis' }],
  });

  definir('certificats', {
    titre: 'Certificats délivrés', sousTitre: 'Chaque certificat porte un code vérifiable publiquement.', fil: ['Académie'], entite: 'certificat',
    actionsEnTete: `<a href="#/certificat-modele" class="btn-second">${icone('award')}Modèle</a>`,
    colonnes: [{ cle: 'qui', libelle: 'Titulaire', type: 'personne' }, { cle: 'cours', libelle: 'Cours', muet: true }, { cle: 'code', libelle: 'Code', type: 'code' }, { cle: 'note', libelle: 'Note finale', muet: true }, { cle: 'date', libelle: 'Délivré le', type: 'date' }],
    lignes: n(18, () => ({ qui: personne(), cours: choisir(VF.cours).titre, code: 'VF-' + entre(100000, 999999), note: entre(12, 19) + ' / 20', date: jours(entre(1, 120)) })),
    actionsLigne: (l) => [{ libelle: 'Télécharger le PDF', ic: 'file-down', action: () => toast('PDF du certificat ' + l.code + ' généré.') }, { libelle: 'Copier le lien de vérification', ic: 'link', action: () => toast('Lien copié.') }, { libelle: 'Révoquer', ic: 'ban', action: () => toast('Certificat révoqué. Le lien public l\'indique désormais.', 'alerte') }],
    supprimable: false,
  });

  definir('unites-premium', {
    titre: 'Ventes des cours premium', sousTitre: 'Paiements Mobile Money des cours payants, rapprochés avec l\'opérateur.', fil: ['Académie'], entite: 'paiement', plage: true, supprimable: false,
    kpis: [{ libelle: 'Encaissé ce mois', valeur: fmt.compact(1840000) + ' FCFA', delta: 17, ic: 'wallet' }, { libelle: 'Paiements confirmés', valeur: '479', delta: 9, ic: 'circle-check' }, { libelle: 'Taux d\'échec', valeur: '3,1 %', delta: -0.8, sens: 'bas-bon', ic: 'circle-x' }, { libelle: 'Panier moyen', valeur: '3 840 FCFA', delta: 5, ic: 'shopping-basket' }],
    colonnes: [{ cle: 'qui', libelle: 'Client', type: 'personne' }, { cle: 'cours', libelle: 'Cours', muet: true }, { cle: 'montant', libelle: 'Montant', type: 'fcfa' }, { cle: 'operateur', libelle: 'Opérateur', muet: true }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.paiement }, { cle: 'date', libelle: 'Date', type: 'date' }],
    filtres: [{ cle: 'etat', libelle: 'État', options: opts(S.paiement) }, { cle: 'operateur', libelle: 'Opérateur', options: ['MTN MoMo', 'Moov Money', 'Celtiis Cash'] }],
    lignes: n(26, () => ({ qui: personne(), cours: choisir(VF.cours.filter((c) => c.prix)).titre, montant: choisir([1200, 2500, 4800, 8000]), operateur: choisir(['MTN MoMo', 'Moov Money', 'Celtiis Cash']), etat: choisir(['confirme', 'confirme', 'confirme', 'attente', 'echoue', 'rembourse']), date: jours(entre(0, 25)) })),
    actionsLigne: (l) => [{ libelle: 'Rapprocher avec l\'opérateur', ic: 'refresh-cw', action: () => toast('Statut revérifié auprès de ' + l.operateur + '.', 'info') }, { libelle: 'Rembourser', ic: 'undo-2', action: () => toast('Remboursement demandé.', 'alerte') }],
  });

  const examens = n(9, (i) => ({ titre: ['Examen final — OSINT Fondamentaux', 'Contrôle — Image virale', 'Examen — Deepfakes', 'Évaluation — Arnaques Mobile Money', 'Examen — Anatomie d\'une rumeur', 'Contrôle continu — Esprit critique', 'Examen blanc — OSINT', 'Rattrapage — Image virale', 'Certification — Fact-checkeur'][i], cours: choisir(VF.cours).titre, duree: choisir([45, 60, 90, 120]) + ' min', candidats: entre(20, 380), reussite: entre(48, 91), etat: choisir(['publie', 'publie', 'brouillon', 'archive']) }));
  definir('examens', {
    titre: 'Examens', fil: ['Académie'], entite: 'examen',
    colonnes: [{ cle: 'titre', libelle: 'Examen', type: 'titre', sous: 'cours' }, { cle: 'duree', libelle: 'Durée', muet: true }, { cle: 'candidats', libelle: 'Candidats', type: 'nombre' }, { cle: 'reussite', libelle: 'Réussite', type: 'jauge' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.publication }],
    filtres: [{ cle: 'etat', libelle: 'État', options: opts(S.publication) }],
    lignes: examens,
    champs: [{ nom: 'titre', libelle: 'Intitulé', regles: 'requis' }, { nom: 'cours', libelle: 'Cours', options: VF.cours.map((c) => c.titre) }, { nom: 'debut', libelle: 'Ouverture', attrs: 'data-date-heure', regles: 'requis' }, { nom: 'duree', libelle: 'Durée (minutes)', type: 'number', regles: 'requis|nombre', valeur: '60' }, { nom: 'seuil', libelle: 'Note de réussite (/20)', type: 'number', valeur: '12' }],
  });

  const enrolement = (titre, objet, liste) => ({
    titre, fil: ['Académie'], entite: 'inscription', feminin: true, plage: true,
    colonnes: [{ cle: 'qui', libelle: 'Apprenant', type: 'personne' }, { cle: 'objet', libelle: objet, muet: true }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.activite }, { cle: 'date', libelle: 'Inscrit le', type: 'date' }],
    filtres: [{ cle: 'etat', libelle: 'État', options: opts(S.activite) }],
    lignes: n(24, () => ({ qui: personne(), objet: choisir(liste), etat: choisir(['actif', 'actif', 'attente', 'termine', 'inactif']), date: jours(entre(0, 60)) })),
    actionsLigne: () => [{ libelle: 'Valider l\'inscription', ic: 'check', action: () => toast('Inscription validée.') }, { libelle: 'Envoyer une relance', ic: 'send', action: () => toast('Relance envoyée par courriel.') }],
    actionsGroupees: [{ libelle: 'Valider', ic: 'check' }, { libelle: 'Relancer', ic: 'send' }, { libelle: 'Désinscrire', ic: 'user-minus', danger: true }],
    champs: [{ nom: 'email', libelle: 'Courriel de l\'apprenant', type: 'email', regles: 'requis|email' }, { nom: 'objet', libelle: objet, options: liste }],
  });
  definir('enrolement-cours', enrolement('Inscriptions aux cours', 'Cours', VF.cours.map((c) => c.titre)));
  definir('enrolement-examens', enrolement('Inscriptions aux examens', 'Examen', examens.map((e) => e.titre)));

  /* ===================== Hub d'infos ===================== */

  definir('hub-actualites', {
    titre: 'Actualités', fil: ['Hub d\'infos'], entite: 'actualité', feminin: true,
    colonnes: [{ cle: 'titre', libelle: 'Titre', type: 'titre', sous: 'source' }, { cle: 'vues', libelle: 'Lectures', type: 'nombre' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.publication }, { cle: 'date', libelle: 'Date', type: 'date' }],
    lignes: ['Le Bénin lance une plateforme de signalement des arnaques', 'Élections : la CENA publie le calendrier officiel', 'Paludisme : nouvelle campagne de vaccination dans l\'Atacora', 'Numérique : 2 000 enseignants formés à l\'esprit critique', 'Inondations : les vraies images de la semaine'].map((titre) => ({ titre, source: choisir(['ABP', 'La Nation', 'ORTB', 'Banouto']), vues: entre(300, 18000), etat: choisir(['publie', 'publie', 'brouillon']), date: jours(entre(0, 20)) })),
    champs: [{ nom: 'titre', libelle: 'Titre', regles: 'requis' }, { nom: 'source', libelle: 'Source', regles: 'requis' }, { nom: 'url', libelle: 'Lien d\'origine', type: 'url' }],
  });
  definir('hub-portails', {
    titre: 'Portails officiels', fil: ['Hub d\'infos'], entite: 'portail',
    colonnes: [{ cle: 'nom', libelle: 'Portail', type: 'titre', sous: 'url' }, { cle: 'categorie', libelle: 'Catégorie', muet: true }, { cle: 'clics', libelle: 'Ouvertures', type: 'nombre' }, { cle: 'etat', libelle: 'Lien', type: 'pastille', etats: { actif: { classe: 'p-bon', ic: 'link', libelle: 'Répond' }, revue: { classe: 'p-critique', ic: 'unlink', libelle: 'Lien cassé' } } }],
    lignes: [['Service public', 'service-public.bj', 'Démarches'], ['Gouvernement', 'gouv.bj', 'Institution'], ['ANIP', 'anip.bj', 'Identité'], ['Impôts', 'impots.finances.bj', 'Finances'], ['ARCEP', 'arcep.bj', 'Télécoms'], ['Ministère de la Santé', 'sante.gouv.bj', 'Santé']].map(([nom, url, categorie], i) => ({ nom, url, categorie, clics: entre(80, 3000), etat: i === 4 ? 'revue' : 'actif' })),
    champs: [{ nom: 'nom', libelle: 'Nom', regles: 'requis' }, { nom: 'url', libelle: 'Adresse', regles: 'requis' }, { nom: 'categorie', libelle: 'Catégorie', options: ['Démarches', 'Institution', 'Identité', 'Finances', 'Télécoms', 'Santé'] }],
  });
  definir('hub-jobs', {
    titre: 'Offres d\'emploi vérifiées', sousTitre: 'Chaque offre est contrôlée avant publication : employeur, contact, absence de frais.', fil: ['Hub d\'infos'], entite: 'offre', feminin: true,
    colonnes: [{ cle: 'poste', libelle: 'Poste', type: 'titre', sous: 'employeur' }, { cle: 'ville', libelle: 'Ville', muet: true }, { cle: 'candidatures', libelle: 'Candidatures', type: 'nombre' }, { cle: 'etat', libelle: 'Contrôle', type: 'pastille', etats: { publie: { classe: 'p-bon', ic: 'shield-check', libelle: 'Vérifiée' }, revue: { classe: 'p-alerte', ic: 'eye', libelle: 'À contrôler' }, archive: { classe: 'p-critique', ic: 'shield-x', libelle: 'Refusée' } } }, { cle: 'date', libelle: 'Limite', type: 'date' }],
    filtres: [{ cle: 'etat', libelle: 'Contrôle', options: [{ valeur: 'publie', libelle: 'Vérifiée' }, { valeur: 'revue', libelle: 'À contrôler' }, { valeur: 'archive', libelle: 'Refusée' }] }],
    lignes: [['Chargé de communication', 'ONG Social Watch'], ['Data analyst junior', 'INStaT'], ['Journaliste fact-checkeur', 'Banouto'], ['Formateur en éducation aux médias', 'Ambassade de France'], ['Développeur web', 'Startup Cotonou'], ['Agent de terrain (frais d\'inscription)', 'Annonce WhatsApp']].map(([poste, employeur], i) => ({ poste, employeur, ville: choisir(['Cotonou', 'Porto-Novo', 'Parakou']), candidatures: entre(4, 240), etat: i === 5 ? 'archive' : i === 4 ? 'revue' : 'publie', date: jours(-entre(5, 40)) })),
    champs: [{ nom: 'poste', libelle: 'Intitulé du poste', regles: 'requis' }, { nom: 'employeur', libelle: 'Employeur', regles: 'requis' }, { nom: 'ville', libelle: 'Ville', options: ['Cotonou', 'Porto-Novo', 'Parakou', 'À distance'] }, { nom: 'limite', libelle: 'Date limite', attrs: 'data-date', regles: 'requis' }],
  });

  /* ===================== Communauté ===================== */

  const categoriesSimples = (titre, fil, noms) => ({
    titre, fil: [fil], entite: 'catégorie', feminin: true,
    colonnes: [{ cle: 'nom', libelle: 'Catégorie', type: 'titre' }, { cle: 'contenus', libelle: 'Contenus', type: 'nombre' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.activite }],
    lignes: noms.map((nom) => ({ nom, contenus: entre(4, 220), etat: 'actif' })),
    champs: [{ nom: 'nom', libelle: 'Nom', regles: 'requis' }, { nom: 'description', libelle: 'Description', type: 'textarea' }],
  });
  definir('forum-categories', categoriesSimples('Catégories du forum', 'Communauté', ['Questions de vérification', 'Rumeurs du moment', 'Santé', 'Élections', 'Entraide Académie']));
  definir('blog-categories', categoriesSimples('Catégories du blog', 'Communauté', ['Méthodes', 'Décryptages', 'Vie de l\'association', 'Partenariats', 'Éducation aux médias']));

  definir('forum-discussions', {
    titre: 'Forum citoyen', sousTitre: 'Les discussions, et celles que la modération doit regarder en premier.', fil: ['Communauté'], entite: 'discussion', feminin: true,
    colonnes: [{ cle: 'sujet', libelle: 'Sujet', type: 'titre', sous: 'categorie' }, { cle: 'auteur', libelle: 'Auteur', type: 'personne' }, { cle: 'reponses', libelle: 'Réponses', type: 'nombre' }, { cle: 'etat', libelle: 'Modération', type: 'pastille', etats: { actif: { classe: 'p-bon', ic: 'circle-check', libelle: 'Visible' }, revue: { classe: 'p-alerte', ic: 'flag', libelle: 'Signalée' }, suspendu: { classe: 'p-critique', ic: 'eye-off', libelle: 'Masquée' } } }],
    filtres: [{ cle: 'etat', libelle: 'Modération', options: [{ valeur: 'actif', libelle: 'Visible' }, { valeur: 'revue', libelle: 'Signalée' }, { valeur: 'suspendu', libelle: 'Masquée' }] }],
    lignes: ['Le vaccin et la stérilité : d\'où vient la rumeur ?', 'Comment vérifier un numéro qui m\'appelle ?', 'Cette photo d\'inondation est-elle récente ?', 'Les nouveaux billets ont-ils une puce ?', 'Partagez vos outils préférés', 'Lien suspect reçu sur WhatsApp'].map((sujet, i) => ({ sujet, categorie: choisir(['Rumeurs du moment', 'Santé', 'Questions de vérification']), auteur: personne(), reponses: entre(0, 84), etat: i === 0 ? 'revue' : i === 5 ? 'suspendu' : 'actif' })),
    actionsLigne: () => [{ libelle: 'Épingler', ic: 'pin', action: () => toast('Discussion épinglée.') }, { libelle: 'Masquer', ic: 'eye-off', action: () => toast('Discussion masquée. L\'auteur est prévenu.', 'alerte') }, { libelle: 'Verrouiller', ic: 'lock', action: () => toast('Discussion verrouillée.') }],
  });

  definir('blog', {
    titre: 'Articles du blog', fil: ['Communauté'], entite: 'article',
    actionsEnTete: '',
    colonnes: [{ cle: 'image', libelle: '', type: 'image', tri: false }, { cle: 'titre', libelle: 'Article', type: 'titre', sous: 'auteur' }, { cle: 'vues', libelle: 'Lectures', type: 'nombre' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.publication }, { cle: 'date', libelle: 'Date', type: 'date' }],
    filtres: [{ cle: 'etat', libelle: 'État', options: opts(S.publication) }],
    lignes: [['Vérifier une image en 3 minutes', 'fausse-rumeur.webp'], ['Anatomie d\'une rumeur', 'fakenews_img.webp'], ['Prix WSIS 2026 : Vérif\'All récompensé', '2eme-prix-concours-objectif-developpement-durable-vignette.webp'], ['Esprit critique au collège', 'presentation-officielle-cemaac-porto-novo-vignette.webp'], ['Arnaques Mobile Money : les 5 scénarios', 'anarques.webp'], ['Santé : ces conseils viraux qui mettent en danger', 'sante-danger.webp']].map(([titre, img], i) => ({ titre, image: 'assets/img/' + img, auteur: choisir(['Mawuli Adjovi', 'Rachida Sanni', 'Romaric Zinsou']), vues: entre(400, 22000), etat: i === 3 ? 'brouillon' : i === 5 ? 'revue' : 'publie', date: jours(entre(1, 60)) })),
    actionsLigne: () => [{ libelle: 'Ouvrir dans l\'éditeur', ic: 'pen-line', action: () => (location.hash = '#/editeur') }, { libelle: 'Voir sur le site', ic: 'external-link', action: () => toast('Aperçu ouvert dans un nouvel onglet.', 'info') }],
    monte: (r) => { const b = r.querySelector('[data-creer]'); if (b) { const a = b.cloneNode(true); b.replaceWith(a); a.addEventListener('click', () => (location.hash = '#/editeur')); } },
    champs: [{ nom: 'titre', libelle: 'Titre', regles: 'requis' }],
  });

  definir('partenaires', {
    titre: 'Partenaires', fil: ['Communauté'], entite: 'partenaire',
    colonnes: [{ cle: 'nom', libelle: 'Partenaire', type: 'titre', sous: 'type' }, { cle: 'depuis', libelle: 'Depuis', muet: true }, { cle: 'etat', libelle: 'Affichage', type: 'pastille', etats: S.publication }],
    lignes: [['UNESCO', 'Institution'], ['Ambassade de France au Bénin', 'Institution'], ['Africa Check', 'Fact-checkeur'], ['CDIJ Cotonou', 'Association'], ['Université d\'Abomey-Calavi', 'Académique'], ['MTN Bénin', 'Entreprise']].map(([nom, type], i) => ({ nom, type, depuis: String(2021 + (i % 5)), etat: i === 5 ? 'brouillon' : 'publie' })),
    champs: [{ nom: 'nom', libelle: 'Nom', regles: 'requis' }, { nom: 'type', libelle: 'Type', options: ['Institution', 'Fact-checkeur', 'Association', 'Académique', 'Entreprise'] }, { nom: 'url', libelle: 'Site', type: 'url' }],
  });

  definir('temoignages', {
    titre: 'Témoignages', fil: ['Communauté'], entite: 'témoignage',
    colonnes: [{ cle: 'auteur', libelle: 'Auteur', type: 'personne' }, { cle: 'texte', libelle: 'Témoignage', type: 'titre' }, { cle: 'note', libelle: 'Note', type: 'etoiles' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.publication }],
    lignes: ['J\'ai évité une arnaque à 50 000 FCFA grâce au cours Mobile Money.', 'Mes élèves vérifient maintenant avant de partager.', 'Le certificat OSINT m\'a aidé à décrocher mon stage.', 'La tipline WhatsApp répond vite et clairement.'].map((texte, i) => ({ auteur: personne(), texte, note: choisir([4.5, 5, 5, 4]), etat: i === 3 ? 'revue' : 'publie' })),
    actionsLigne: () => [{ libelle: 'Publier', ic: 'check', action: () => toast('Témoignage publié sur la page d\'accueil.') }],
    champs: [{ nom: 'texte', libelle: 'Témoignage', type: 'textarea', regles: 'requis' }],
  });

  definir('newsletters', {
    titre: 'Infolettres', sousTitre: 'Les envois aux abonnés, leurs ouvertures et leurs clics.', fil: ['Communauté'], entite: 'infolettre', feminin: true,
    kpis: [{ libelle: 'Abonnés confirmés', valeur: fmt.nombre(12840), delta: 6, ic: 'mail-check' }, { libelle: 'Taux d\'ouverture', valeur: '41 %', delta: 3, ic: 'mail-open' }, { libelle: 'Taux de clic', valeur: '7,8 %', delta: 1.2, ic: 'mouse-pointer-click' }, { libelle: 'Désinscriptions (30 j)', valeur: '0,4 %', delta: -0.1, sens: 'bas-bon', ic: 'mail-x' }],
    colonnes: [{ cle: 'objet', libelle: 'Objet', type: 'titre' }, { cle: 'ouverture', libelle: 'Ouverture', type: 'jauge' }, { cle: 'destinataires', libelle: 'Destinataires', type: 'nombre' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: { publie: { classe: 'p-bon', ic: 'send', libelle: 'Envoyée' }, attente: { classe: 'p-info', ic: 'clock', libelle: 'Programmée' }, brouillon: { classe: 'p-neutre', ic: 'file-pen-line', libelle: 'Brouillon' } } }, { cle: 'date', libelle: 'Date', type: 'date' }],
    lignes: ['Les 5 rumeurs de la semaine', 'Nouveau cours : Deepfakes', 'Élections : notre dispositif', 'Bilan de septembre', 'Rentrée : l\'esprit critique en classe'].map((objet, i) => ({ objet, ouverture: i === 4 ? 0 : entre(28, 54), destinataires: 12840, etat: i === 4 ? 'brouillon' : i === 3 ? 'attente' : 'publie', date: jours(i * 7) })),
    actionsLigne: () => [{ libelle: 'Ouvrir dans l\'éditeur', ic: 'pen-line', action: () => (location.hash = '#/editeur?type=infolettre') }, { libelle: 'Envoyer un test', ic: 'flask-conical', action: () => toast('Test envoyé à votre adresse.') }],
    champs: [{ nom: 'objet', libelle: 'Objet', regles: 'requis|min:6' }, { nom: 'envoi', libelle: 'Envoi programmé', attrs: 'data-date-heure' }],
  });

  /* ===================== Utilisateurs ===================== */

  definir('utilisateurs', {
    titre: 'Citoyens', sousTitre: 'Les comptes citoyens, leur activité et leur solde d\'unités.', fil: ['Utilisateurs'], entite: 'compte', plage: true,
    kpis: [{ libelle: 'Comptes', valeur: fmt.nombre(38214), delta: 7, ic: 'users' }, { libelle: 'Actifs sur 30 jours', valeur: fmt.nombre(9120), delta: 12, ic: 'activity' }, { libelle: 'Nouveaux cette semaine', valeur: '842', delta: 4, ic: 'user-plus' }, { libelle: 'Comptes suspendus', valeur: '37', ic: 'ban' }],
    colonnes: [{ cle: 'qui', libelle: 'Citoyen', type: 'personne' }, { cle: 'ville', libelle: 'Ville', muet: true }, { cle: 'unites', libelle: 'Unités', type: 'nombre' }, { cle: 'verifs', libelle: 'Vérifications', type: 'nombre' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.activite }, { cle: 'date', libelle: 'Inscrit le', type: 'date' }],
    filtres: [{ cle: 'etat', libelle: 'État', options: [{ valeur: 'actif', libelle: 'Actif' }, { valeur: 'inactif', libelle: 'Inactif' }, { valeur: 'suspendu', libelle: 'Suspendu' }] }, { cle: 'ville', libelle: 'Ville', options: ['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey-Calavi', 'Bohicon'] }],
    lignes: n(40, (i) => ({ qui: personne(), ville: choisir(['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey-Calavi', 'Bohicon']), unites: entre(0, 900), verifs: entre(0, 140), etat: i % 13 === 0 ? 'suspendu' : i % 4 === 0 ? 'inactif' : 'actif', date: jours(entre(1, 400)) })),
    actionsLigne: (l) => [{ libelle: 'Voir la fiche complète', ic: 'id-card', action: () => (location.hash = VF.lienProfil('profil-citoyen', { ...l.qui, ville: l.ville })) }, { libelle: 'Créditer des unités', ic: 'coins', action: () => toast('50 unités créditées. Journalisé.') }, { libelle: 'Réinitialiser le mot de passe', ic: 'key-round', action: () => toast('Lien de réinitialisation envoyé.') }, { libelle: l.etat === 'suspendu' ? 'Réactiver' : 'Suspendre', ic: l.etat === 'suspendu' ? 'circle-check' : 'ban', action: () => toast(l.etat === 'suspendu' ? 'Compte réactivé.' : 'Compte suspendu. La personne est déconnectée.', 'alerte') }],
    actionsGroupees: [{ libelle: 'Créditer des unités', ic: 'coins' }, { libelle: 'Envoyer un message', ic: 'mail' }, { libelle: 'Suspendre', ic: 'ban', danger: true }],
    champs: [{ nom: 'nom', libelle: 'Nom complet', regles: 'requis' }, { nom: 'email', libelle: 'Courriel', type: 'email', regles: 'requis|email' }, { nom: 'ville', libelle: 'Ville', options: ['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey-Calavi', 'Bohicon'] }],
    nouvelle: (v) => ({ qui: { nom: v.nom, initiales: v.nom.split(' ').map((m) => m[0]).join('').slice(0, 2), email: v.email }, unites: 50, verifs: 0, etat: 'actif', date: new Date() }),
  });

  definir('mentors', {
    titre: 'Mentors', sousTitre: 'Les formateurs de l\'Académie, leurs cours et leur réactivité.', fil: ['Utilisateurs'], entite: 'mentor',
    colonnes: [{ cle: 'qui', libelle: 'Mentor', type: 'personne' }, { cle: 'cours', libelle: 'Cours', type: 'nombre' }, { cle: 'apprenants', libelle: 'Apprenants', type: 'nombre' }, { cle: 'delai', libelle: 'Délai de correction', muet: true }, { cle: 'note', libelle: 'Note', type: 'etoiles' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: S.activite }],
    lignes: VF.equipe.filter((e) => e.role === 'Mentor').concat([{ nom: 'Prudence Akpovi', initiales: 'PA' }, { nom: 'Ulrich Kpadonou', initiales: 'UK' }]).map((e, i) => ({ qui: { nom: e.nom, initiales: e.initiales, email: e.nom.split(' ')[0].toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '') + '@verifall.com' }, cours: entre(1, 6), apprenants: e.apprenants || entre(20, 300), delai: choisir(['5 h', '11 h', '1 j', '2 j']), note: e.satisfaction || 4.4, etat: i === 5 ? 'attente' : 'actif' })),
    actionsLigne: (l) => [{ libelle: 'Voir la fiche complète', ic: 'id-card', action: () => (location.hash = VF.lienProfil('profil-mentor', l.qui)) }, { libelle: 'Envoyer un message', ic: 'message-square', action: () => toast('Message envoyé à ' + l.qui.nom + '.') }],
    champs: [{ nom: 'nom', libelle: 'Nom complet', regles: 'requis' }, { nom: 'email', libelle: 'Courriel', type: 'email', regles: 'requis|email' }, { nom: 'specialite', libelle: 'Spécialité', options: ['OSINT', 'Image', 'Vidéo', 'Éducation', 'Sécurité'] }],
  });

  /* ===================== Système ===================== */

  definir('campagnes', {
    titre: 'Campagnes', sousTitre: 'Bannières et campagnes de sensibilisation, avec leurs performances.', fil: ['Système'], entite: 'campagne', feminin: true, plage: true,
    colonnes: [{ cle: 'nom', libelle: 'Campagne', type: 'titre', sous: 'emplacement' }, { cle: 'impressions', libelle: 'Impressions', type: 'nombre' }, { cle: 'ctr', libelle: 'Taux de clic', type: 'pct' }, { cle: 'etat', libelle: 'État', type: 'pastille', etats: { actif: { classe: 'p-bon', ic: 'play', libelle: 'En cours' }, attente: { classe: 'p-info', ic: 'clock', libelle: 'Programmée' }, termine: { classe: 'p-neutre', ic: 'flag', libelle: 'Terminée' } } }, { cle: 'fin', libelle: 'Fin', type: 'date' }],
    lignes: [['Élections sans rumeur', 'Bandeau d\'accueil'], ['Arnaques de fin d\'année', 'Barre latérale'], ['Rentrée : esprit critique', 'Espace citoyen'], ['Semaine de la vaccination', 'Bandeau d\'accueil']].map(([nom, emplacement], i) => ({ nom, emplacement, impressions: entre(4000, 90000), ctr: +(1 + hasardFixe(i) * 4).toFixed(1), etat: ['actif', 'attente', 'termine', 'actif'][i], fin: jours(-entre(-20, 30)) })),
    champs: [{ nom: 'nom', libelle: 'Nom', regles: 'requis' }, { nom: 'emplacement', libelle: 'Emplacement', options: ['Bandeau d\'accueil', 'Barre latérale', 'Espace citoyen'] }, { nom: 'periode', libelle: 'Période', attrs: 'data-plage', regles: 'requis' }],
  });
  function hasardFixe(i) { return [0.42, 0.71, 0.18, 0.55][i] || 0.3; }

  definir('badges', {
    titre: 'Badges de progression', fil: ['Système'], entite: 'badge',
    colonnes: [{ cle: 'nom', libelle: 'Badge', type: 'titre', sous: 'condition' }, { cle: 'obtenus', libelle: 'Obtenus', type: 'nombre' }, { cle: 'points', libelle: 'Points', type: 'nombre' }],
    lignes: [['Premier pas', 'Première vérification envoyée', 10], ['Œil de lynx', '10 images vérifiées', 50], ['Pédagogue', '5 réponses utiles au forum', 40], ['Certifié OSINT', 'Certificat OSINT obtenu', 120], ['Sentinelle', '50 signalements pertinents', 200]].map(([nom, condition, points]) => ({ nom, condition, points, obtenus: entre(40, 9000) })),
    champs: [{ nom: 'nom', libelle: 'Nom', regles: 'requis' }, { nom: 'condition', libelle: 'Condition d\'obtention', regles: 'requis' }, { nom: 'points', libelle: 'Points', type: 'number', regles: 'nombre' }],
  });

  /* Modèles de courriels : écran à part, avec son éditeur — ecrans-courriels.js. */

  const journalDef = (titre, sous, lignes, colonnes, extra = {}) => ({ titre, sousTitre: sous, fil: ['Journaux'], entite: 'entrée', feminin: true, plage: true, supprimable: false, colonnes, lignes, ...extra });

  definir('audit', journalDef('Journal d\'audit', 'Toute action sensible du personnel, horodatée et non modifiable.',
    n(30, () => ({ qui: personne(), action: choisir(['a modifié un rôle', 'a crédité des unités', 'a publié un fact-check', 'a suspendu un compte', 'a exporté des données', 'a changé un réglage']), ip: `41.${entre(70, 90)}.${entre(0, 255)}.${entre(1, 254)}`, niveau: choisir(['info', 'info', 'alerte']), date: jours(entre(0, 14)) })),
    [{ cle: 'qui', libelle: 'Membre du personnel', type: 'personne' }, { cle: 'action', libelle: 'Action', muet: true }, { cle: 'ip', libelle: 'Adresse IP', type: 'code' }, { cle: 'niveau', libelle: 'Niveau', type: 'pastille', etats: S.niveau }, { cle: 'date', libelle: 'Date', type: 'date' }],
    { filtres: [{ cle: 'niveau', libelle: 'Niveau', options: opts(S.niveau) }] }));

  definir('logs-emails', journalDef('Journal des courriels', 'Chaque courriel envoyé, et ceux qui n\'ont pas pu partir.',
    n(26, () => ({ a: personne(), modele: choisir(['Code d\'inscription', 'Fact-check publié', 'Certificat obtenu', 'Confirmation de paiement']), etat: choisir(['confirme', 'confirme', 'confirme', 'echoue', 'attente']), date: jours(entre(0, 7)) })),
    [{ cle: 'a', libelle: 'Destinataire', type: 'personne' }, { cle: 'modele', libelle: 'Modèle', muet: true }, { cle: 'etat', libelle: 'Distribution', type: 'pastille', etats: { confirme: { classe: 'p-bon', ic: 'mail-check', libelle: 'Distribué' }, attente: { classe: 'p-info', ic: 'clock', libelle: 'En file' }, echoue: { classe: 'p-critique', ic: 'mail-x', libelle: 'Rejeté' } } }, { cle: 'date', libelle: 'Date', type: 'date' }],
    { actionsLigne: () => [{ libelle: 'Renvoyer', ic: 'send', action: () => toast('Courriel remis en file d\'envoi.') }] }));

  definir('logs-unites', journalDef('Journal des unités', 'Les crédits et débits d\'unités, pour le rapprochement comptable.',
    n(26, () => ({ qui: personne(), motif: choisir(['Analyse de vérification', 'Achat Mobile Money', 'Dotation mensuelle', 'Bonus de parrainage', 'Crédit manuel']), montant: (hasardSigne() > 0 ? '+' : '−') + entre(2, 400), date: jours(entre(0, 20)) })),
    [{ cle: 'qui', libelle: 'Compte', type: 'personne' }, { cle: 'motif', libelle: 'Motif', muet: true }, { cle: 'montant', libelle: 'Unités', type: 'code' }, { cle: 'date', libelle: 'Date', type: 'date' }]));
  function hasardSigne() { return VF.outils.hasard() - 0.6; }

  definir('alertes', journalDef('Alertes d\'intrusion', 'Les tentatives bloquées par le pare-feu applicatif et la limitation de débit.',
    n(18, () => ({ type: choisir(['Bourrage d\'identifiants', 'Injection SQL bloquée', 'Débit API dépassé', 'Jeton invalide', 'Chemin sensible sondé']), ip: `${entre(5, 220)}.${entre(0, 255)}.${entre(0, 255)}.${entre(1, 254)}`, pays: choisir(['Bénin', 'Nigeria', 'France', 'Inconnu', 'États-Unis']), niveau: choisir(['info', 'alerte', 'critique']), date: jours(entre(0, 6)) })),
    [{ cle: 'type', libelle: 'Événement', type: 'titre' }, { cle: 'ip', libelle: 'Adresse IP', type: 'code' }, { cle: 'pays', libelle: 'Origine', muet: true }, { cle: 'niveau', libelle: 'Gravité', type: 'pastille', etats: S.niveau }, { cle: 'date', libelle: 'Date', type: 'date' }],
    { filtres: [{ cle: 'niveau', libelle: 'Gravité', options: opts(S.niveau) }], actionsLigne: (l) => [{ libelle: 'Bloquer cette adresse', ic: 'shield-ban', action: () => toast(l.ip + ' bloquée pour 24 h.', 'alerte') }] }));

  definir('sauvegardes', {
    titre: 'Sauvegardes de la base', sousTitre: 'Une sauvegarde complète chaque nuit, conservée 30 jours.', fil: ['Système'], entite: 'sauvegarde', feminin: true, supprimable: false,
    actionsEnTete: `<button class="btn-primaire" onclick="VF.ui.toast('Sauvegarde lancée. Vous serez notifié à la fin.','info')">${icone('database-backup')}Sauvegarder maintenant</button>`,
    exporter: false,
    colonnes: [{ cle: 'nom', libelle: 'Fichier', type: 'code' }, { cle: 'taille', libelle: 'Taille', muet: true }, { cle: 'duree', libelle: 'Durée', muet: true }, { cle: 'etat', libelle: 'Résultat', type: 'pastille', etats: { confirme: { classe: 'p-bon', ic: 'circle-check', libelle: 'Réussie' }, echoue: { classe: 'p-critique', ic: 'circle-x', libelle: 'Échouée' } } }, { cle: 'date', libelle: 'Date', type: 'date' }],
    lignes: n(12, (i) => ({ nom: `massenon_verifall_${VF.outils.iso(jours(i)).replace(/-/g, '')}.sql.gz`, taille: (310 + i * 2) + ' Mo', duree: entre(40, 95) + ' s', etat: i === 6 ? 'echoue' : 'confirme', date: jours(i) })),
    actionsLigne: () => [{ libelle: 'Télécharger', ic: 'download', action: () => toast('Téléchargement préparé.') }, { libelle: 'Restaurer', ic: 'history', action: () => VF.ui.confirmer({ titre: 'Restaurer cette sauvegarde ?', texte: 'Le site passera en maintenance pendant la restauration. Les données saisies depuis seront perdues.', bouton: 'Restaurer', siOui: () => toast('Restauration planifiée.', 'alerte') }) }],
  });

  definir('cron', {
    titre: 'Tâches planifiées', sousTitre: 'Sur cPanel, le planificateur doit être appelé chaque minute par une entrée cron. Sa dernière exécution est indiquée ici.', fil: ['Système'], entite: 'tâche', feminin: true, supprimable: false, exporter: false,
    avant: `<div class="carte p-4 mb-6 flex items-start gap-3">${icone('circle-check', 'w-5 h-5 text-green-700 dark:text-bon mt-0.5')}<div><p class="font-medium">Le planificateur tourne</p><p class="text-sm texte-2 mt-0.5">Dernière exécution il y a 38 secondes. Plus ancien job en file : 12 secondes.</p></div></div>`,
    colonnes: [{ cle: 'commande', libelle: 'Commande', type: 'code' }, { cle: 'frequence', libelle: 'Fréquence', muet: true }, { cle: 'derniere', libelle: 'Dernière exécution', muet: true }, { cle: 'etat', libelle: 'Résultat', type: 'pastille', etats: { confirme: { classe: 'p-bon', ic: 'circle-check', libelle: 'Réussie' }, echoue: { classe: 'p-critique', ic: 'circle-x', libelle: 'Échouée' } } }],
    lignes: [['verifall:dotation-mensuelle', 'Le 1er à 00:05', 'il y a 14 j'], ['verifall:purge-rgpd', 'Chaque jour à 03:00', 'il y a 9 h'], ['verifall:rapprocher-paiements', 'Toutes les 15 min', 'il y a 4 min'], ['verifall:relancer-apprenants', 'Chaque lundi à 08:00', 'il y a 3 j'], ['verifall:sauvegarde', 'Chaque nuit à 02:00', 'il y a 10 h'], ['queue:work --stop-when-empty', 'Chaque minute', 'il y a 38 s']].map(([commande, frequence, derniere], i) => ({ commande, frequence, derniere, etat: i === 3 ? 'echoue' : 'confirme' })),
    actionsLigne: (l) => [{ libelle: 'Exécuter maintenant', ic: 'play', action: () => toast(l.commande + ' lancée.', 'info') }, { libelle: 'Voir la sortie', ic: 'terminal', action: () => toast('Sortie de la dernière exécution affichée.', 'info') }],
  });
})(window.VF);
