/*
 | Données fictives du prototype.
 |
 | Elles imitent ce que la base contient réellement (verdicts, unités IA, cours,
 | mentors) pour qu'on juge l'interface sur du contenu crédible, pas sur du
 | « Lorem ipsum ». Rien n'est envoyé nulle part.
 */
window.VF = window.VF || {};

(function (VF) {
  'use strict';

  /* Générateur pseudo-aléatoire stable : les graphiques ne changent pas à
     chaque rechargement, ce qui rend les captures comparables. */
  let graine = 20261015;
  const hasard = () => { graine = (graine * 1664525 + 1013904223) % 4294967296; return graine / 4294967296; };
  const entre = (a, b) => Math.round(a + hasard() * (b - a));
  const choisir = (liste) => liste[Math.floor(hasard() * liste.length)];

  const prenoms = ['Kossi', 'Aïcha', 'Sèdjro', 'Mawuli', 'Fifamè', 'Romaric', 'Nafissatou', 'Gildas', 'Afi', 'Ulrich', 'Mariam', 'Bienvenu', 'Rachida', 'Hermann', 'Chimène', 'Yao', 'Prudence', 'Ismaël', 'Carine', 'Abdou'];
  const noms = ['Tossou', 'Bello', 'Houngbédji', 'Adjovi', 'Agossou', 'Dossou', 'Soglo', 'Zinsou', 'Kpadonou', 'Ahouansou', 'Gbaguidi', 'Alapini', 'Sanni', 'Akpovi', 'Hounkpè', 'Yessoufou'];
  const villes = ['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey-Calavi', 'Bohicon', 'Natitingou', 'Ouidah', 'Lokossa', 'Djougou', 'Kandi'];

  const personne = () => {
    const p = choisir(prenoms), n = choisir(noms);
    return { nom: `${p} ${n}`, initiales: p[0] + n[0], email: `${p}.${n}`.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '') + '@mail.bj' };
  };

  const jours = (n) => { const d = new Date(2026, 9, 15); d.setDate(d.getDate() - n); return d; };
  const iso = (d) => d.toISOString().slice(0, 10);

  VF.outils = { hasard, entre, choisir, personne, jours, iso };

  VF.moi = {
    admin: { nom: 'Mawuli Adjovi', initiales: 'MA', role: 'Super administrateur', email: 'mawuli.adjovi@verifall.com' },
    mentor: { nom: 'Fifamè Houngbédji', initiales: 'FH', role: 'Mentor — OSINT et vérification', email: 'fifame.h@verifall.com' },
  };

  /* --- Fact-checking --- */
  VF.verdicts = {
    vrai: { libelle: 'Vrai', classe: 'p-bon', icone: 'circle-check' },
    trompeur: { libelle: 'Trompeur', classe: 'p-alerte', icone: 'triangle-alert' },
    faux: { libelle: 'Faux', classe: 'p-critique', icone: 'circle-x' },
    invérifiable: { libelle: 'Non vérifiable', classe: 'p-neutre', icone: 'circle-help' },
  };

  const affirmations = [
    'Une coupure générale d\'électricité est prévue à Cotonou du 20 au 25 octobre',
    'Le vaccin contre le paludisme rendrait stériles les enfants de moins de 5 ans',
    'Le gouvernement offre 150 000 FCFA à chaque étudiant inscrit sur ce lien',
    'Une vidéo montrerait une bagarre au marché Dantokpa ce matin',
    'La CEDEAO aurait suspendu le Bénin de ses instances',
    'Boire de l\'eau chaude citronnée guérit le choléra',
    'Le pont de Cadjèhoun sera fermé pendant six mois',
    'Les nouveaux billets de 10 000 FCFA contiendraient une puce de traçage',
    'Une photo d\'inondation à Porto-Novo daterait de cette semaine',
    'Le baccalauréat 2026 serait reporté au mois de décembre',
    'Un recrutement de 5 000 enseignants serait ouvert sans concours',
    'Le prix du carburant baisserait de 40 % dès lundi',
  ];
  const canaux = ['WhatsApp', 'Facebook', 'TikTok', 'Tipline', 'Site web', 'X'];
  const themes = ['Santé', 'Politique', 'Économie', 'Société', 'Éducation', 'Arnaque'];

  VF.signalements = affirmations.map((texte, i) => {
    const auteur = personne();
    const etats = ['nouveau', 'en_cours', 'en_cours', 'revue', 'publie', 'publie'];
    const etat = i < 3 ? 'nouveau' : choisir(etats);
    return {
      id: 'SIG-' + (4820 + i),
      texte, canal: choisir(canaux), theme: choisir(themes), auteur,
      ville: choisir(villes),
      viralite: entre(12, 98),
      recu: jours(i < 4 ? 0 : entre(1, 12)),
      echeance: i < 4 ? [38, 72, 140, 210][i] : entre(240, 2800),
      etat,
      verdict: etat === 'publie' ? choisir(['faux', 'faux', 'trompeur', 'vrai', 'invérifiable']) : null,
      confiance: entre(61, 97),
      analyste: personne(),
    };
  });

  /* Les 19 moteurs d'analyse de l'orchestrateur. */
  VF.moteurs = [
    ['Gemini Flash-Lite', 'Texte'], ['DeepSeek', 'Texte'], ['OpenAI', 'Texte'], ['Perplexity', 'Recherche'],
    ['Google Fact Check', 'Base de faits'], ['Google Custom Search', 'Recherche'], ['TinEye', 'Image inverse'],
    ['SauceNAO', 'Image inverse'], ['AI or Not', 'Image générée'], ['GPTZero', 'Texte généré'],
    ['Winston AI', 'Texte généré'], ['Hugging Face', 'Classification'], ['Whois', 'Domaine'],
    ['Wayback Machine', 'Archive'], ['Analyse EXIF', 'Métadonnées'], ['Détection de deepfake', 'Vidéo'],
    ['Transcription audio', 'Audio'], ['Sources de confiance', 'Base interne'], ['Score de viralité', 'Réseaux'],
  ].map(([nom, type], i) => ({
    nom, type,
    precision: i < 5 ? entre(86, 96) : entre(71, 93),
    latence: entre(380, 4200),
    appels: entre(800, 21000),
    erreurs: +(hasard() * (i === 7 ? 9 : 3)).toFixed(1),
    unites: entre(200, 9400),
    etat: i === 7 ? 'degrade' : i === 15 ? 'quota' : 'ok',
  }));

  VF.sources = ['ABP — Agence Bénin Presse', 'La Nation', 'Ministère de la Santé', 'INStaT', 'Banouto', 'ORTB', 'Africa Check', 'AFP Factuel', 'Commission électorale (CENA)', 'Banque mondiale — Bénin'].map((nom, i) => ({
    nom, categorie: choisir(['Institution', 'Média', 'Fact-checkeur', 'Statistique']), score: entre(72, 99), citations: entre(14, 380), maj: jours(entre(0, 40)), etat: i === 4 ? 'revue' : 'actif',
  }));

  /* --- Académie --- */
  VF.cours = [
    { titre: 'OSINT Fondamentaux', categorie: 'Enquête', niveau: 'Débutant', inscrits: 1284, completion: 64, note: 4.8, prix: 0, mentor: 'Fifamè Houngbédji', lecons: 24, image: 'assets/img/lancement-officiel-cdij-cotonou-vignette.webp' },
    { titre: 'Vérifier une image virale en 3 minutes', categorie: 'Image', niveau: 'Débutant', inscrits: 2310, completion: 78, note: 4.9, prix: 0, mentor: 'Fifamè Houngbédji', lecons: 9, image: 'assets/img/fausse-rumeur.webp' },
    { titre: 'Anatomie d\'une rumeur', categorie: 'Esprit critique', niveau: 'Intermédiaire', inscrits: 864, completion: 52, note: 4.6, prix: 120, mentor: 'Romaric Zinsou', lecons: 16, image: 'assets/img/fakenews_img.webp' },
    { titre: 'Deepfakes : détecter et expliquer', categorie: 'Vidéo', niveau: 'Avancé', inscrits: 412, completion: 38, note: 4.7, prix: 250, mentor: 'Fifamè Houngbédji', lecons: 20, image: 'assets/img/sante-danger.webp' },
    { titre: 'Arnaques en ligne et Mobile Money', categorie: 'Sécurité', niveau: 'Débutant', inscrits: 1902, completion: 71, note: 4.8, prix: 0, mentor: 'Rachida Sanni', lecons: 12, image: 'assets/img/anarques.webp' },
    { titre: 'Esprit critique au collège', categorie: 'Éducation', niveau: 'Débutant', inscrits: 655, completion: 59, note: 4.5, prix: 80, mentor: 'Gildas Agossou', lecons: 14, image: 'assets/img/presentation-officielle-cemaac-porto-novo-vignette.webp' },
  ];

  VF.apprenants = Array.from({ length: 28 }, (_, i) => {
    const p = personne();
    const progression = entre(4, 100);
    return {
      ...p, ville: choisir(villes), cours: choisir(VF.cours).titre,
      progression, moyenne: entre(9, 19),
      derniere: jours(progression > 90 ? entre(0, 3) : entre(0, 26)),
      etat: progression === 100 ? 'termine' : (i % 5 === 0 ? 'inactif' : 'actif'),
    };
  });

  VF.copies = [
    { apprenant: 'Kossi Tossou', initiales: 'KT', devoir: 'Enquête guidée sur un domaine', cours: 'OSINT Fondamentaux', soumis: 'il y a 12 min', urgence: 'urgent' },
    { apprenant: 'Aïcha Bello', initiales: 'AB', devoir: 'Analyse d\'une image virale', cours: 'Vérifier une image virale', soumis: 'il y a 2 h', urgence: 'nouveau' },
    { apprenant: 'Samuel Akpovi', initiales: 'SA', devoir: 'Vérifier une déclaration publique', cours: 'OSINT Fondamentaux', soumis: 'il y a 5 h', urgence: 'nouveau' },
    { apprenant: 'Marie Alapini', initiales: 'MA', devoir: 'Rapport d\'enquête final', cours: 'Deepfakes : détecter et expliquer', soumis: 'hier', urgence: 'en_cours' },
    { apprenant: 'Ismaël Yessoufou', initiales: 'IY', devoir: 'Cartographier une rumeur', cours: 'Anatomie d\'une rumeur', soumis: 'hier', urgence: 'nouveau' },
  ];

  /* --- Séries temporelles (30 jours) --- */
  const serie = (base, variation, tendance = 0) => Array.from({ length: 30 }, (_, i) => Math.max(0, Math.round(base + tendance * i + (hasard() - 0.5) * variation)));
  VF.series = {
    jours: Array.from({ length: 30 }, (_, i) => iso(jours(29 - i))),
    signalements: serie(140, 60, 2.2),
    verifications: serie(110, 40, 2.6),
    inscriptions: serie(60, 30, 1.1),
    unites: serie(3200, 900, 40),
    precision: Array.from({ length: 30 }, (_, i) => +(86 + i * 0.12 + (hasard() - 0.5) * 2).toFixed(1)),
    sessionsOrganiques: serie(2400, 500, 28),
  };

  /* --- SEO --- */
  VF.motsCles = [
    ['vérifier une information bénin', 3, 2, 2900], ['fake news bénin', 1, 0, 6600], ['arnaque mobile money', 5, -3, 4400],
    ['vérifier une image', 8, 4, 12100], ['fact checking afrique', 4, 1, 1900], ['formation osint gratuite', 11, 6, 1300],
    ['rumeur whatsapp', 7, -1, 2400], ['deepfake détection', 14, 9, 3600], ['cours esprit critique', 6, 2, 880],
  ].map(([mot, position, delta, volume]) => ({ mot, position, delta, volume, url: '/' + mot.split(' ').slice(0, 3).join('-') }));
  VF.backlinks = [
    ['afrique-check.org', 74, 'Article', 'gagne'], ['lanation.bj', 61, 'Actualité', 'gagne'], ['banouto.bj', 48, 'Dossier', 'gagne'],
    ['unesco.org', 92, 'Ressource', 'gagne'], ['forum-etudiants.bj', 22, 'Forum', 'perdu'], ['wikipedia.org', 95, 'Référence', 'gagne'],
  ].map(([domaine, autorite, type, etat]) => ({ domaine, autorite, type, etat, vu: jours(entre(0, 30)) }));

  /* --- Économie : unités, quotas, pipeline --- */
  VF.pipeline = [
    { etape: 'Paniers créés', valeur: 4120000, nombre: 812 },
    { etape: 'Paiement initié', valeur: 2980000, nombre: 590 },
    { etape: 'Confirmé opérateur', valeur: 2410000, nombre: 488 },
    { etape: 'Unités créditées', valeur: 2365000, nombre: 479 },
  ];
  VF.equipe = ['Fifamè Houngbédji', 'Romaric Zinsou', 'Rachida Sanni', 'Gildas Agossou', 'Chimène Gbaguidi', 'Hermann Dossou'].map((nom, i) => ({
    nom, initiales: nom.split(' ').map((m) => m[0]).join(''),
    role: i < 4 ? 'Mentor' : 'Partenaire', points: entre(1800, 9400), quota: 8000,
    ventes: entre(180000, 1900000), apprenants: entre(60, 900), satisfaction: +(4 + hasard()).toFixed(1),
  }));

  /* --- Journaux et système --- */
  VF.journal = [
    ['Mawuli Adjovi', 'a publié le fact-check', 'SIG-4815 — « Pont de Cadjèhoun fermé six mois »', 'il y a 4 min', 'file-check'],
    ['Système', 'a basculé sur le moteur de repli', 'Gemini Flash-Lite → 3.1 Flash-Lite (quota)', 'il y a 18 min', 'refresh-cw'],
    ['Rachida Sanni', 'a validé le dossier KYC', 'Hermann Dossou', 'il y a 41 min', 'badge-check'],
    ['Fifamè Houngbédji', 'a ajouté 3 leçons', 'Deepfakes : détecter et expliquer', 'il y a 1 h', 'book-open'],
    ['Système', 'a crédité 2 400 unités', '48 paiements Mobile Money confirmés', 'il y a 2 h', 'coins'],
    ['Gildas Agossou', 'a modéré une discussion', 'Forum — « Le vaccin et la stérilité »', 'il y a 3 h', 'shield-check'],
  ].map(([qui, action, objet, quand, icone]) => ({ qui, action, objet, quand, icone }));

  VF.notifications = [
    { titre: '3 signalements dépassent 70 % de viralité', detail: 'Le plus ancien attend depuis 38 min.', icone: 'flame', ton: 'critique', lien: '#/signalements' },
    { titre: 'Le moteur SauceNAO est dégradé', detail: '9 % d\'erreurs sur la dernière heure.', icone: 'triangle-alert', ton: 'alerte', lien: '#/analytique-ia' },
    { titre: '5 copies à corriger', detail: 'Dont une signalée comme urgente.', icone: 'pen-line', ton: 'info', lien: '#/correction-examens' },
    { titre: '4 dossiers KYC en attente', detail: 'Délai moyen : 6 h.', icone: 'id-card', ton: 'info', lien: '#/kyc' },
  ];

  VF.evenements = (function () {
    const base = new Date(2026, 9, 1);
    const d = (j, h = 0, m = 0) => { const x = new Date(base); x.setDate(j); x.setHours(h, m, 0, 0); return x.toISOString(); };
    return [
      { title: 'Live — OSINT pas à pas', start: d(15, 18), end: d(15, 19, 30), type: 'live' },
      { title: 'Examen — Image virale', start: d(17, 9), end: d(17, 11), type: 'examen' },
      { title: 'Campagne « Élections sans rumeur »', start: d(19), end: d(26), allDay: true, type: 'campagne' },
      { title: 'Maintenance base de données', start: d(18, 23), end: d(19, 1), type: 'maintenance' },
      { title: 'Live — Deepfakes', start: d(21, 17), end: d(21, 18, 30), type: 'live' },
      { title: 'Réunion des mentors', start: d(22, 10), end: d(22, 11), type: 'reunion' },
      { title: 'Examen — OSINT Fondamentaux', start: d(24, 9), end: d(24, 12), type: 'examen' },
      { title: 'Live — Arnaques Mobile Money', start: d(28, 18), end: d(28, 19), type: 'live' },
      { title: 'Atelier collège Ouidah', start: d(9, 14), end: d(9, 16), type: 'reunion' },
      { title: 'Live — Esprit critique', start: d(7, 18), end: d(7, 19), type: 'live' },
    ];
  })();

  VF.faq = [
    { categorie: 'Vérifications', q: 'Combien de temps prend une vérification ?', r: 'Une analyse automatique rend un premier avis en moins de deux minutes. Un fact-check publié par l\'équipe prend en moyenne 6 h, et moins d\'une heure pour un signalement viral.' },
    { categorie: 'Vérifications', q: 'Que signifie « Non vérifiable » ?', r: 'Les sources disponibles ne permettent ni de confirmer ni d\'infirmer l\'affirmation. Le rapport indique ce qu\'il faudrait pour trancher.' },
    { categorie: 'Unités IA', q: 'Comment acheter des unités ?', r: 'Depuis « Mes unités », par Mobile Money (MTN, Moov, Celtiis). Les unités sont créditées dès la confirmation de l\'opérateur.' },
    { categorie: 'Unités IA', q: 'Mes unités expirent-elles ?', r: 'Non. La dotation mensuelle gratuite, elle, est remise à zéro le premier du mois.' },
    { categorie: 'Académie', q: 'Les certificats sont-ils reconnus ?', r: 'Chaque certificat porte un code vérifiable publiquement. Plusieurs partenaires institutionnels les acceptent dans leurs recrutements.' },
    { categorie: 'Académie', q: 'Puis-je suivre un cours hors ligne ?', r: 'Les supports PDF se téléchargent depuis chaque leçon. Les vidéos et les quiz demandent une connexion.' },
    { categorie: 'Compte', q: 'Comment activer la double authentification ?', r: 'Dans Mon compte, onglet Sécurité. Une application d\'authentification suffit ; aucun SMS n\'est envoyé.' },
    { categorie: 'Compte', q: 'Comment supprimer mon compte ?', r: 'Dans Réglages. Un code de confirmation est envoyé par courriel, et les données sont effacées sous 30 jours.' },
  ];

  VF.medias = [
    'lancement-officiel-cdij-cotonou-vignette.webp', 'presentation-officielle-cemaac-porto-novo-vignette.webp',
    '2eme-prix-concours-objectif-developpement-durable-vignette.webp', '2eme-prix-concours-objectif-developpement-durable-1-vignette.webp',
    '2eme-prix-concours-objectif-developpement-durable-2-vignette.webp', 'fausse-rumeur.webp', 'sante-danger.webp', 'anarques.webp',
    '2eme-prix-concours-objectif-developpement-durable-3-vignette.webp', 'fakenews_img.webp',
  ].map((f, i) => ({ fichier: 'assets/img/' + f, titre: ['Lancement CDIJ Cotonou', 'Présentation CEMAAC Porto-Novo', 'Prix ODD — cérémonie', 'Prix ODD — lauréats', 'Prix ODD — jury', 'Rumeur virale', 'Alerte santé', 'Arnaques', 'Prix ODD — public', 'Désinformation'][i], poids: entre(80, 900) + ' Ko' }));
})(window.VF);
