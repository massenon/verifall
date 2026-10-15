/* Vérif'All — vf-store.js
   Source de vérité de la simulation front : cours (seeders), progression LMS,
   vérifications, unités, XP, badges, notifications. Persisté en localStorage,
   monotone et protégé contre les doublons (achat, XP, certificat). */
(function () {
  'use strict';
  var KEY = 'verifall:citizen:v1';

  /* ————— Seeders ————— */
  var COURSES = [
    {
      id: 'A', slug: 'introduction-fact-checking', free: true, cost: 0,
      title: 'Introduction au fact-checking — CLOM Vérif\'All',
      level: 'Débutant', category: 'Fact-Checking', rating: 4.8, students: 1245,
      duration: '42 min', cover: '../images/courses/placeholder-a.svg',
      desc: 'Ce premier cours constitue votre point d\'entrée dans le parcours de formation au fact-checking : vocabulaire, réflexes et démarche de vérification.',
      outcomes: ['Identifier une fake news', 'Vérifier une source', 'Adopter les bons réflexes avant de partager', 'Comprendre la démarche Vérif\'All'],
      instructor: { name: 'Rhodes Massenon', role: 'Expert Technique', initials: 'RM' },
      lessons: [
        { id: 'A1', title: 'Bienvenue dans le CLOM Vérif\'All', minutes: 1, type: 'text' },
        { id: 'A2', title: 'Les signaux d\'une fake news', minutes: 1, type: 'text' },
        { id: 'A3', title: 'Vérifier une source en 3 questions', minutes: 1, type: 'text' },
        { id: 'A4', title: 'La date et le contexte', minutes: 1, type: 'text' },
        { id: 'A5', title: 'Récapitulatif et bons réflexes', minutes: 1, type: 'text' }
      ],
      quizzes: [{
        id: 'QA', title: 'Quiz final — Les bases', minutes: 5, passScore: 70, xp: 100,
        questions: [
          { q: 'Une information provoque chez vous une émotion très forte. Quel est le bon réflexe ?', options: ['La partager vite pour alerter', 'Se méfier et vérifier avant de partager', 'La commenter', 'L\'ignorer toujours'], answer: 1, xai: 'La désinformation joue sur vos émotions pour court-circuiter votre logique : une émotion forte est un signal de vigilance, pas une preuve.' },
          { q: 'Quel élément renforce la crédibilité d\'une source ?', options: ['Son nombre d\'abonnés', 'Une source primaire identifiable', 'Une publication virale', 'Un logo professionnel'], answer: 1, xai: 'Une source primaire permet de remonter directement à l\'origine de l\'information.' },
          { q: 'Une vidéo ancienne repartagée pendant une crise est…', options: ['Toujours fausse', 'Une preuve valable', 'Un contenu hors contexte à vérifier', 'Interdite'], answer: 2, xai: 'Vérifiez toujours la date : le contexte temporel est clé pour éviter les contenus recyclés.' },
          { q: 'Que signifie un score de confiance de 15 % sur Vérif\'All ?', options: ['Information fiable', 'Fake news ou site dangereux probable', 'Analyse en cours', 'Source officielle'], answer: 1, xai: 'Un score faible (0–20 %) signale une fake news ou un site dangereux.' },
          { q: 'Avant de partager un communiqué officiel, vous devez…', options: ['Vérifier le canal officiel d\'origine', 'Compter les fautes uniquement', 'Regarder le nombre de partages', 'Attendre 24 h'], answer: 0, xai: 'Aucune annonce officielle n\'est crédible si elle ne provient pas d\'un canal officiel ou d\'un média de référence certifié.' }
        ]
      }],
      assignments: [], certificate: true
    },
    {
      id: 'B', slug: 'osint-fondamentaux', free: false, cost: 10,
      title: 'OSINT Fondamentaux — Enquêter en sources ouvertes',
      level: 'Intermédiaire', category: 'OSINT', rating: 4.9, students: 862,
      duration: '1 h 20', cover: '../images/courses/placeholder-b.svg',
      desc: 'Recherche d\'image inversée, métadonnées, géolocalisation : maîtrisez les techniques d\'investigation numérique utilisées par les fact-checkers professionnels.',
      outcomes: ['Faire une recherche d\'image inversée', 'Lire des métadonnées', 'Géolocaliser une photo', 'Documenter une preuve'],
      instructor: { name: 'Atman Bouba', role: 'Formateur OSINT', initials: 'AB' },
      lessons: [
        { id: 'B1', title: 'Qu\'est-ce que l\'OSINT ?', minutes: 1, type: 'text' },
        { id: 'B2', title: 'La recherche d\'image inversée', minutes: 1, type: 'text' },
        { id: 'B3', title: 'Métadonnées : lire l\'invisible', minutes: 1, type: 'text' },
        { id: 'B4', title: 'Géolocalisation par indices visuels', minutes: 1, type: 'text' },
        { id: 'B5', title: 'Archives et versions antérieures', minutes: 1, type: 'text' },
        { id: 'B6', title: 'Croiser les sources', minutes: 1, type: 'text' },
        { id: 'B7', title: 'Constituer un dossier de preuves', minutes: 1, type: 'text' },
        { id: 'B8', title: 'Éthique de l\'enquête en ligne', minutes: 1, type: 'text' }
      ],
      quizzes: [
        {
          id: 'QB1', title: 'Quiz — Recherche inversée', minutes: 4, passScore: 70, xp: 80,
          questions: [
            { q: 'La recherche d\'image inversée sert d\'abord à…', options: ['Améliorer la qualité d\'une photo', 'Retrouver l\'origine et les usages antérieurs d\'une image', 'Compresser une image', 'Créer une image IA'], answer: 1, xai: 'Elle permet de savoir si une image a déjà été publiée ailleurs, quand et dans quel contexte.' },
            { q: 'Une image sans résultat de recherche inversée est…', options: ['Forcément authentique', 'Forcément truquée', 'À analyser avec d\'autres méthodes', 'Sans intérêt'], answer: 2, xai: 'L\'absence de résultat n\'est pas une preuve : croisez avec métadonnées et indices visuels.' },
            { q: 'Quel indice aide à géolocaliser une photo ?', options: ['Le nombre de likes', 'Les panneaux, l\'architecture et la végétation', 'La taille du fichier', 'Le filtre utilisé'], answer: 1, xai: 'Les détails visuels (panneaux, bâtiments, végétation, plaques) sont les clés de la géolocalisation.' },
            { q: 'Les métadonnées EXIF peuvent contenir…', options: ['La date de prise de vue et l\'appareil', 'Le mot de passe du photographe', 'Le verdict Vérif\'All', 'Le nombre de partages'], answer: 0, xai: 'Les EXIF documentent l\'appareil, la date, parfois la position GPS — quand elles n\'ont pas été effacées.' }
          ]
        },
        {
          id: 'QB2', title: 'Quiz final — Méthodologie OSINT', minutes: 5, passScore: 70, xp: 120,
          questions: [
            { q: 'Un bon dossier de preuves contient…', options: ['Des captures datées et sourcées', 'Uniquement des opinions', 'Des liens morts', 'Des rumeurs'], answer: 0, xai: 'Chaque preuve doit être datée, sourcée et vérifiable par un tiers.' },
            { q: 'Croiser les sources signifie…', options: ['Copier une source', 'Confirmer une information par des sources indépendantes', 'Choisir la source la plus populaire', 'Traduire une source'], answer: 1, xai: 'Une information est renforcée quand plusieurs sources indépendantes la confirment.' },
            { q: 'Avant de publier une enquête, vous devez…', options: ['Vérifier la légalité et l\'éthique de vos méthodes', 'Supprimer vos sources', 'Exagérer le titre', 'Attendre un buzz'], answer: 0, xai: 'L\'OSINT éthique respecte la vie privée et la loi : seules les sources ouvertes et licites sont utilisées.' },
            { q: 'Les archives web (Wayback Machine) servent à…', options: ['Accélérer un site', 'Retrouver des versions antérieures d\'une page', 'Créer un site', 'Bloquer une page'], answer: 1, xai: 'Elles permettent de prouver ce qu\'une page affichait à une date donnée, même après modification.' }
          ]
        }
      ],
      assignments: [{ id: 'DB1', title: 'Analyse d\'une publication suspecte', minutes: 30, desc: 'Identifiez les affirmations, retrouvez les sources primaires, vérifiez la date et produisez une conclusion documentée.' }],
      certificate: true
    },
    {
      id: 'C', slug: 'deepfakes-detection', free: false, cost: 15,
      title: 'Détection des Deepfakes & contenus IA',
      level: 'Avancé', category: 'IA', rating: 4.7, students: 534,
      duration: '2 h 05', cover: '../images/courses/placeholder-c.svg',
      desc: 'Textures trop lisses, mains mal formées, incohérences de lumière : apprenez à repérer les images, vidéos et voix générées par intelligence artificielle.',
      outcomes: ['Repérer une image générée par IA', 'Analyser une vidéo suspecte image par image', 'Détecter une voix clonée', 'Utiliser les outils forensiques'],
      instructor: { name: 'Gloria Tchidime', role: 'Formatrice', initials: 'GT' },
      lessons: [
        { id: 'C1', title: 'Panorama des contenus générés par IA', minutes: 1, type: 'text' },
        { id: 'C2', title: 'Les artefacts visuels des images IA', minutes: 1, type: 'text' },
        { id: 'C3', title: 'Mains, textes et reflets : les zones qui trahissent', minutes: 1, type: 'text' },
        { id: 'C4', title: 'Analyser une vidéo image par image', minutes: 1, type: 'text' },
        { id: 'C5', title: 'Synchronisation labiale et micro-expressions', minutes: 1, type: 'text' },
        { id: 'C6', title: 'Voix clonées : les signaux audio', minutes: 1, type: 'text' },
        { id: 'C7', title: 'Outils forensiques (HiveModeration, VeraAI)', minutes: 1, type: 'text' },
        { id: 'C8', title: 'Étude de cas : un deepfake politique', minutes: 1, type: 'text' },
        { id: 'C9', title: 'Limites de la détection automatique', minutes: 1, type: 'text' },
        { id: 'C10', title: 'Méthodologie complète de vérification', minutes: 1, type: 'text' }
      ],
      quizzes: [
        {
          id: 'QC1', title: 'Quiz — Images générées par IA', minutes: 4, passScore: 70, xp: 80,
          questions: [
            { q: 'Quel indice trahit souvent une image générée par IA ?', options: ['Des mains mal formées', 'Une haute résolution', 'Des couleurs vives', 'Un format carré'], answer: 0, xai: 'Mains, textes et reflets sont les zones où les modèles génératifs commettent le plus d\'erreurs.' },
            { q: 'Une texture de peau parfaitement lisse est…', options: ['Une preuve d\'authenticité', 'Un signal possible de génération IA', 'Normale sur toute photo', 'Un filtre Instagram obligatoire'], answer: 1, xai: 'Les textures trop lisses et uniformes sont un artefact fréquent des images IA.' },
            { q: 'Face à une image suspecte, la première étape est…', options: ['La recherche d\'image inversée', 'La retoucher', 'La partager pour demander des avis', 'Zoomer uniquement'], answer: 0, xai: 'La recherche inversée révèle l\'origine et les usages antérieurs, avant l\'analyse forensique.' },
            { q: 'Les incohérences de lumière dans une image indiquent…', options: ['Un possible montage ou une génération IA', 'Une photo de nuit', 'Un bon appareil', 'Rien de particulier'], answer: 0, xai: 'Des ombres incohérentes entre sujets trahissent souvent un assemblage ou une génération.' }
          ]
        },
        {
          id: 'QC2', title: 'Quiz — Vidéos et deepfakes', minutes: 4, passScore: 70, xp: 80,
          questions: [
            { q: 'La synchronisation labiale imparfaite est un signal de…', options: ['Deepfake possible', 'Vidéo authentique', 'Problème de connexion', 'Sous-titrage'], answer: 0, xai: 'Les deepfakes peinent encore à synchroniser parfaitement lèvres et audio.' },
            { q: 'Analyser une vidéo image par image permet de…', options: ['Repérer les artefacts de transition', 'Accélérer la vidéo', 'Améliorer le son', 'Compter les vues'], answer: 0, xai: 'Les défauts apparaissent souvent sur quelques images clés : contours, dents, oreilles.' },
            { q: 'Une voix clonée se détecte notamment par…', options: ['Une prosodie plate et des respirations absentes', 'Un volume élevé', 'Un accent', 'Une langue étrangère'], answer: 0, xai: 'Intonation monotone, absence de respiration et articulation trop régulière sont des signaux.' },
            { q: 'Le watermark TikTok "@eng.davidrapemo" dans une vidéo virale a permis de…', options: ['Remonter à la source originale kenyane', 'Prouver l\'authenticité béninoise', 'Rien', 'Identifier un deepfake'], answer: 0, xai: 'Cas réel Vérif\'All : le watermark a permis de localiser la vidéo à Nairobi, pas à Cotonou.' }
          ]
        },
        {
          id: 'QC3', title: 'Quiz final — Certification', minutes: 6, passScore: 70, xp: 150,
          questions: [
            { q: 'Les outils forensiques (HiveModeration, VeraAI) fournissent…', options: ['Une probabilité de génération IA à interpréter', 'Un verdict juridique définitif', 'Une correction automatique', 'Un certificat'], answer: 0, xai: 'Ils donnent un score de probabilité : l\'interprétation humaine et le croisement restent indispensables.' },
            { q: 'La détection automatique de deepfakes est…', options: ['Utile mais faillible : à croiser avec l\'analyse humaine', 'Infaillible', 'Interdite', 'Réservée aux États'], answer: 0, xai: 'Les modèles évoluent des deux côtés : aucune détection automatique n\'est infaillible.' },
            { q: 'Une méthodologie complète de vérification vidéo commence par…', options: ['Le contexte : qui publie, quand, où', 'Le nombre de vues', 'Les commentaires', 'La durée'], answer: 0, xai: 'Contexte de publication d\'abord, puis analyse technique : c\'est la démarche Vérif\'All.' },
            { q: 'Aucune image d\'arrestation de chef d\'État n\'est crédible si…', options: ['Elle ne provient pas d\'un canal officiel ou média certifié', 'Elle est floue', 'Elle est ancienne', 'Elle vient d\'Afrique'], answer: 0, xai: 'Règle de vigilance issue des analyses Vérif\'All sur les infox de décembre 2025.' }
          ]
        }
      ],
      assignments: [{ id: 'DC1', title: 'Rapport d\'analyse d\'un deepfake', minutes: 45, desc: 'À partir de l\'étude de cas, rédigez un rapport : indices relevés, outils utilisés, verdict argumenté et sources.' }],
      certificate: true
    },
    {
      id: 'D', slug: 'un-siecle-de-fact-checking', free: true, cost: 0,
      title: 'Un siècle de fact-checking',
      level: 'Débutant', category: 'Fact-Checking', rating: 4.6, students: 2340,
      duration: '55 min', cover: '../images/courses/placeholder-d.svg',
      desc: 'Retracez l\'histoire de la vérification des faits, des premières salles de rédaction des années 1920 jusqu\'aux plateformes collaboratives modernes comme Vérif\'All.',
      outcomes: ['Situer les grandes étapes du fact-checking', 'Comprendre le rôle des agences de presse', 'Relier l\'histoire aux pratiques actuelles', 'Utiliser cet héritage dans vos vérifications'],
      instructor: { name: 'René Fadonougbo', role: 'Fondateur, Directeur de la publication', initials: 'RF' },
      lessons: [
        { id: 'D1', title: 'Les pionniers des années 1920', minutes: 8, type: 'video' },
        { id: 'D2', title: 'L\'âge d\'or des services de vérification', minutes: 9, type: 'text' },
        { id: 'D3', title: 'Internet change les règles du jeu', minutes: 10, type: 'video' },
        { id: 'D4', title: 'La naissance du fact-checking en Afrique', minutes: 9, type: 'text' },
        { id: 'D5', title: 'L\'ère des plateformes collaboratives', minutes: 10, type: 'text' },
        { id: 'D6', title: 'Synthèse : un siècle en 10 dates', minutes: 9, type: 'text' }
      ],
      quizzes: [{
        id: 'QD', title: 'Quiz final — Histoire du fact-checking', minutes: 6, passScore: 70, xp: 100,
        questions: [
          { q: 'Dans quel contexte naissent les premiers services de vérification ?', options: ['Les rédactions des années 1920', 'Les réseaux sociaux', 'Les universités des années 1990', 'Les gouvernements'], answer: 0, xai: 'Les premiers « fact-checkers » professionnels apparaissent dans les grandes rédactions américaines des années 1920.' },
          { q: 'Quel bouleversement majeur apporte Internet au fact-checking ?', options: ['Il le rend inutile', 'La vitesse de propagation dépasse celle de la vérification', 'Il supprime les sources', 'Rien ne change'], answer: 1, xai: 'La rumeur circule désormais plus vite que le démenti : c\'est tout l\'enjeu des plateformes comme Vérif\'All.' },
          { q: 'Que permet une plateforme collaborative comme Vérif\'All ?', options: ['Remplacer les journalistes', 'Mettre la vérification dans les mains de chaque citoyen', 'Supprimer les fake news', 'Créer de l\'information'], answer: 1, xai: 'L\'approche collaborative démocratise l\'accès aux outils de vérification, sans remplacer le journalisme professionnel.' }
        ]
      }],
      assignments: [], certificate: true
    }
  ];

  var LESSON_REQUIRED_SECONDS = 45; // durée active minimale par leçon (simulation)
  var VERIF_COSTS = { 'Texte': 1, 'URL': 1, 'Image': 2, 'Vidéo': 5, 'Audio': 5 };

  var DEFAULT_STATE = {
    units: 34, xp: 1280, streak: 7,
    badges: ['Source Hunter', 'Fact Checker', 'Esprit Critique', 'Éclaireur'],
    transactions: [
      { label: 'Bonus de bienvenue', delta: +20, at: '2026-08-01' },
      { label: 'Série de 7 jours', delta: +5, at: '2026-08-08' },
      { label: 'Fact-checking (texte)', delta: -1, at: '2026-08-10' },
      { label: 'Bonus cours terminé', delta: +5, at: '2026-08-11' },
      { label: 'Fact-checking (image)', delta: -1, at: '2026-08-12' }
    ],
    courses: {
      D: {
        enrolled: true, purchased: false,
        completedLessons: ['D1', 'D2', 'D3', 'D4', 'D5', 'D6'],
        quizzes: { QD: { score: 100, passed: true, attempts: 1 } },
        assignments: {}, completedAt: '2026-07-28', certifiedAt: '2026-07-28',
        certId: 'VERIF-D-H1ST-1926', xpGranted: { complete: true }
      }
    },        // id → {enrolled, completedLessons:[], quizzes:{id:{score,passed,attempts}}, assignments:{id:status}, completedAt, certifiedAt, certId, xpGranted:{}}
    verifications: [
      { uuid: 'vf-2f8a11', type: 'Texte', title: 'Rumeur WhatsApp — « promotion » bancaire', verdict: 'FAUX', confidence: 92, date: '2026-08-12', status: 'COMPLETED' },
      { uuid: 'vf-9c31b7', type: 'URL', title: 'Site de concours de la « Police Républicaine »', verdict: 'FAUX', confidence: 96, date: '2026-08-11', status: 'COMPLETED' },
      { uuid: 'vf-6d02e4', type: 'Image', title: 'Photo d\'une foule attribuée à Cotonou', verdict: 'TROMPEUR', confidence: 88, date: '2026-08-09', status: 'COMPLETED' },
      { uuid: 'vf-71aa03', type: 'Vidéo', title: 'Vidéo virale sur le climat datée de 2012', verdict: 'VRAI', confidence: 81, date: '2026-08-05', status: 'COMPLETED' }
    ],
    notifications: [
      { id: 'n1', cat: 'verif', icon: 'fact_check', text: 'Votre vérification est terminée.', time: 'Il y a 4 min', href: 'rapport.html?u=vf-2f8a11', read: false },
      { id: 'n2', cat: 'lms', icon: 'school', text: 'Vous avez terminé une leçon.', time: 'Il y a 2 h', href: 'mes-tutoriels.html', read: false },
      { id: 'n3', cat: 'lms', icon: 'military_tech', text: 'Nouveau badge débloqué : Éclaireur.', time: 'Hier', href: 'recompenses.html', read: true }
    ],
    onboardingQuizDone: false, onboardingQuizSnoozedAt: 0,
    campaignSeenAt: 0
  };

  function load() {
    try {
      var raw = localStorage.getItem(KEY);
      if (raw) return JSON.parse(raw);
    } catch (e) { /* stockage indisponible */ }
    return JSON.parse(JSON.stringify(DEFAULT_STATE));
  }
  var state = load();

  function save() {
    try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* ignoré */ }
  }

  function course(id) {
    for (var i = 0; i < COURSES.length; i++) if (COURSES[i].id === id) return COURSES[i];
    return null;
  }
  function cs(id) {
    if (!state.courses[id]) {
      state.courses[id] = { enrolled: false, completedLessons: [], quizzes: {}, assignments: {}, xpGranted: {}, startedAt: 0, lastActivityAt: 0, completedAt: 0, certifiedAt: 0, certId: '' };
    }
    return state.courses[id];
  }

  /* ————— Progression (monotone, calculée) ————— */
  function progress(id) {
    var c = course(id), s = cs(id);
    if (!c) return 0;
    var totalL = c.lessons.length, doneL = s.completedLessons.length;
    var totalQ = c.quizzes.length, doneQ = 0;
    c.quizzes.forEach(function (q) { if (s.quizzes[q.id] && s.quizzes[q.id].passed) doneQ++; });
    var totalA = c.assignments.length, doneA = 0;
    c.assignments.forEach(function (a) { if (s.assignments[a.id] === 'passed' || s.assignments[a.id] === 'submitted') doneA++; });
    var total = totalL + totalQ + totalA;
    return total ? Math.round(((doneL + doneQ + doneA) / total) * 100) : 0;
  }
  function isComplete(id) {
    var c = course(id), s = cs(id);
    if (!c) return false;
    if (s.completedLessons.length < c.lessons.length) return false;
    for (var i = 0; i < c.quizzes.length; i++) {
      var q = s.quizzes[c.quizzes[i].id];
      if (!q || !q.passed) return false;
    }
    for (var j = 0; j < c.assignments.length; j++) {
      var a = s.assignments[c.assignments[j].id];
      if (a !== 'submitted' && a !== 'passed') return false;
    }
    return true;
  }
  function courseStatus(id) {
    var s = cs(id);
    if (!s.enrolled) return 'NOT_ENROLLED';
    if (isComplete(id)) return 'COMPLETED';
    if (s.completedLessons.length || Object.keys(s.quizzes).length) return 'IN_PROGRESS';
    return 'ENROLLED';
  }
  function nextLesson(id) {
    var c = course(id), s = cs(id);
    for (var i = 0; i < c.lessons.length; i++) {
      if (s.completedLessons.indexOf(c.lessons[i].id) === -1) return c.lessons[i];
    }
    return null;
  }
  function lessonUnlocked(cid, lid) {
    var c = course(cid), s = cs(cid);
    for (var i = 0; i < c.lessons.length; i++) {
      if (c.lessons[i].id === lid) {
        if (i === 0) return true;
        return s.completedLessons.indexOf(c.lessons[i - 1].id) !== -1;
      }
    }
    return false;
  }

  /* ————— Actions (anti-duplication) ————— */
  function addXp(key, amount) {
    var s = state;
    s._xpKeys = s._xpKeys || {};
    if (s._xpKeys[key]) return false;
    s._xpKeys[key] = true;
    s.xp += amount;
    save();
    return true;
  }
  function addUnits(delta, label) {
    state.units += delta;
    state.transactions.unshift({ label: label, delta: delta, at: new Date().toISOString().slice(0, 10) });
    save();
  }
  function notify(cat, iconName, text, href) {
    state.notifications.unshift({ id: 'n' + Date.now(), cat: cat, icon: iconName, text: text, time: 'À l\'instant', href: href || '', read: false });
    state.notifications = state.notifications.slice(0, 20);
    save();
  }

  var api = {
    COURSES: COURSES,
    VERIF_COSTS: VERIF_COSTS,
    LESSON_REQUIRED_SECONDS: LESSON_REQUIRED_SECONDS,
    state: state,
    save: save,
    course: course,
    courseState: cs,
    progress: progress,
    isComplete: isComplete,
    courseStatus: courseStatus,
    nextLesson: nextLesson,
    lessonUnlocked: lessonUnlocked,
    addXp: addXp,
    addUnits: addUnits,
    notify: notify,

    enrollFree: function (id) {
      var c = course(id), s = cs(id);
      if (!c || !c.free || s.enrolled) return { ok: false, reason: s.enrolled ? 'already' : 'invalid' };
      s.enrolled = true; s.startedAt = Date.now();
      notify('lms', 'school', 'Inscription gratuite confirmée : ' + c.title, 'cours.html?c=' + id);
      save();
      return { ok: true };
    },
    buyCourse: function (id) {
      var c = course(id), s = cs(id);
      if (!c || c.free) return { ok: false, reason: 'invalid' };
      if (s.enrolled) return { ok: false, reason: 'already' };
      if (state.units < c.cost) return { ok: false, reason: 'insufficient', missing: c.cost - state.units };
      addUnits(-c.cost, 'Achat cours — ' + c.title);
      s.enrolled = true; s.startedAt = Date.now();
      notify('lms', 'school', 'Cours débloqué avec ' + c.cost + ' unités : ' + c.title, 'cours.html?c=' + id);
      save();
      return { ok: true };
    },
    completeLesson: function (cid, lid) {
      var c = course(cid), s = cs(cid);
      if (!c || !s.enrolled || !lessonUnlocked(cid, lid)) return { ok: false };
      if (s.completedLessons.indexOf(lid) !== -1) return { ok: true, already: true };
      s.completedLessons.push(lid);
      s.lastActivityAt = Date.now();
      addXp('lesson:' + cid + ':' + lid, 20);
      notify('lms', 'play_lesson', 'Leçon terminée (+20 XP).', 'cours.html?c=' + cid);
      this._checkCompletion(cid);
      save();
      return { ok: true };
    },
    submitQuiz: function (cid, qid, answers) {
      var c = course(cid), s = cs(cid);
      var quiz = null;
      c.quizzes.forEach(function (q) { if (q.id === qid) quiz = q; });
      if (!quiz || !s.enrolled) return { ok: false };
      var rec = s.quizzes[qid] || { attempts: 0, passed: false, score: 0 };
      if (rec.passed) return { ok: false, reason: 'already-passed' };
      var correct = 0;
      quiz.questions.forEach(function (q, i) { if (answers[i] === q.answer) correct++; });
      var score = Math.round((correct / quiz.questions.length) * 100);
      rec.attempts += 1; rec.score = score; rec.passed = score >= quiz.passScore;
      s.quizzes[qid] = rec;
      s.lastActivityAt = Date.now();
      if (rec.passed) {
        addXp('quiz:' + cid + ':' + qid, quiz.xp);
        notify('lms', 'quiz', 'Quiz réussi à ' + score + ' % (+' + quiz.xp + ' XP).', 'cours.html?c=' + cid);
        this._checkCompletion(cid);
      } else {
        notify('lms', 'quiz', 'Quiz non réussi (' + score + ' %). Vous pouvez réessayer.', 'quiz.html?c=' + cid + '&q=' + qid);
      }
      save();
      return { ok: true, score: score, correct: correct, total: quiz.questions.length, passed: rec.passed };
    },
    submitAssignment: function (cid, aid) {
      var s = cs(cid);
      if (s.assignments[aid] === 'submitted' || s.assignments[aid] === 'passed') return { ok: false, reason: 'already' };
      s.assignments[aid] = 'submitted';
      notify('lms', 'assignment', 'Exercice soumis — en attente de correction par un mentor.', 'exercices.html');
      this._checkCompletion(cid);
      save();
      return { ok: true };
    },
    _checkCompletion: function (cid) {
      var c = course(cid), s = cs(cid);
      if (!s.completedAt && isComplete(cid)) {
        s.completedAt = Date.now();
        addXp('course:' + cid, 500);
        addUnits(+5, 'Bonus cours terminé — ' + c.title);
        if (c.certificate && !s.certId) {
          s.certId = 'VERIF-' + cid + '-' + Math.random().toString(36).slice(2, 6).toUpperCase() + '-' + Math.random().toString(36).slice(2, 6).toUpperCase();
          s.certifiedAt = Date.now();
          notify('lms', 'workspace_premium', 'Votre certificat est disponible !', 'certificat.html?c=' + cid);
        }
        notify('lms', 'celebration', 'Félicitations, cours terminé à 100 % (+500 XP, +5 unités) !', 'cours.html?c=' + cid);
        save();
      }
    },
    startVerification: function (type, title) {
      var cost = VERIF_COSTS[type] || 1;
      if (state.units < cost) return { ok: false, reason: 'insufficient', missing: cost - state.units, cost: cost };
      addUnits(-cost, 'Fact-checking (' + type.toLowerCase() + ')');
      var uuid = 'vf-' + Math.random().toString(16).slice(2, 8);
      state.verifications.unshift({ uuid: uuid, type: type, title: title, verdict: '', confidence: 0, date: new Date().toISOString().slice(0, 10), status: 'ANALYZING' });
      save();
      return { ok: true, uuid: uuid };
    },
    finishVerification: function (uuid) {
      var v = null;
      state.verifications.forEach(function (x) { if (x.uuid === uuid) v = x; });
      if (!v) return null;
      if (v.status !== 'COMPLETED') {
        var verdicts = [
          { verdict: 'FAUX', confidence: 94 }, { verdict: 'VRAI', confidence: 87 },
          { verdict: 'TROMPEUR', confidence: 82 }, { verdict: 'FAUX', confidence: 91 }
        ];
        var pick = verdicts[(uuid.charCodeAt(3) + uuid.charCodeAt(4)) % verdicts.length];
        v.verdict = pick.verdict; v.confidence = pick.confidence; v.status = 'COMPLETED';
        addXp('verif:' + uuid, 10);
        notify('verif', 'fact_check', 'Votre vérification est terminée : verdict ' + v.verdict + '.', 'rapport.html?u=' + uuid);
        save();
      }
      return v;
    },
    verification: function (uuid) {
      var v = null;
      state.verifications.forEach(function (x) { if (x.uuid === uuid) v = x; });
      return v;
    }
  };

  window.VFStore = api;
})();
