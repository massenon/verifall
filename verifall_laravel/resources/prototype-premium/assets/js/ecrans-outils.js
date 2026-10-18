/*
 | Les écrans-outils : calendrier, éditeur, constructeur de cours, FAQ,
 | réglages du compte, rôles, KYC, médiathèque, réglages système,
 | modèle de certificat et correction.
 */
(function (VF) {
  'use strict';
  const { $, $$, esc, fmt, icone, icones, pastille, enTete, carte, toast, tiroir, modale, confirmer, fermerCouche, champ, valider, onglets, bascules, dates } = VF.ui;
  VF.ecrans = VF.ecrans || {};

  /* ======================= Calendrier ======================= */

  const TYPES = {
    live: { libelle: 'Live class', couleur: '#2a78d6', ic: 'video' },
    examen: { libelle: 'Examen', couleur: '#eb6834', ic: 'file-check-2' },
    campagne: { libelle: 'Campagne', couleur: '#1baf7a', ic: 'megaphone' },
    reunion: { libelle: 'Réunion ou atelier', couleur: '#e87ba4', ic: 'users' },
    maintenance: { libelle: 'Maintenance', couleur: '#8a978f', ic: 'wrench' },
  };

  let calendrier = null;
  VF.ecrans['calendrier'] = (racine, { mentor = false } = {}) => {
    const actifs = new Set(Object.keys(TYPES));
    const evts = VF.evenements.map((e, i) => ({ id: String(i), ...e }));
    racine.innerHTML = `
      ${enTete({ titre: mentor ? 'Mes live classes' : 'Calendrier des activités', sousTitre: 'Glissez un événement pour le déplacer, tirez son bord pour changer sa durée, cliquez sur un jour vide pour en créer un.', fil: [mentor ? 'Espace pédagogique' : 'Planning'], actions: `<button class="btn-primaire" data-nouvel>${icone('plus')}Nouvel événement</button>` })}
      <div class="grid xl:grid-cols-[240px_1fr] gap-4">
        <aside class="space-y-4">
          ${carte({ titre: 'Afficher', corps: `<ul class="space-y-2.5">${Object.entries(TYPES).map(([k, t]) => `<li><label class="flex items-center gap-2.5 text-sm cursor-pointer"><input type="checkbox" class="case" data-type="${k}" checked><span class="w-2.5 h-2.5 rounded-full" style="background:${t.couleur}"></span>${icone(t.ic, 'w-4 h-4 texte-3')}${t.libelle}</label></li>`).join('')}</ul>` })}
          ${carte({ titre: 'Cette semaine', corps: `<ul class="space-y-3 text-sm">${evts.filter((e) => { const d = new Date(e.start); return d >= new Date(2026, 9, 13) && d < new Date(2026, 9, 20); }).map((e) => `<li class="flex gap-2.5"><span class="mt-1.5 w-2 h-2 rounded-full shrink-0" style="background:${TYPES[e.type].couleur}"></span><div><p class="font-medium leading-snug">${esc(e.title)}</p><p class="text-xs texte-3">${new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric' }).format(new Date(e.start))}</p></div></li>`).join('')}</ul>` })}
        </aside>
        <section class="carte p-4"><div id="cal"></div></section>
      </div>`;

    const creer = (debut, fin, toutJour) => {
      modale({
        titre: 'Nouvel événement',
        corps: `<form id="f-evt" class="space-y-4" novalidate>
          ${champ({ nom: 'titre', libelle: 'Titre', regles: 'requis', place: 'Ex. Live — Vérifier une vidéo' })}
          ${champ({ nom: 'type', libelle: 'Type', options: Object.values(TYPES).map((t) => t.libelle) })}
          <div class="grid grid-cols-2 gap-3">
            ${champ({ nom: 'debut', libelle: 'Début', regles: 'requis', attrs: 'data-date-heure', valeur: debut ? new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(debut).replace(',', '') : '' })}
            ${champ({ nom: 'fin', libelle: 'Fin', attrs: 'data-date-heure' })}
          </div>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="case" name="rappel" checked>Envoyer un rappel aux participants la veille</label>
        </form>`,
        actions: `<button class="btn-second" data-fermer>Annuler</button><button class="btn-primaire" form="f-evt" type="submit">${icone('check')}Créer l'événement</button>`,
        apres: (c) => $('#f-evt', c).addEventListener('submit', (e) => {
          e.preventDefault();
          if (!valider(e.target)) return;
          const d = new FormData(e.target);
          const type = Object.keys(TYPES).find((k) => TYPES[k].libelle === d.get('type'));
          calendrier.addEvent({ title: d.get('titre'), start: debut || new Date(2026, 9, 16, 18), end: fin || undefined, allDay: !!toutJour, type, backgroundColor: TYPES[type].couleur });
          fermerCouche('modale');
          toast(`« ${d.get('titre')} » ajouté au calendrier.`);
        }),
      });
    };

    calendrier = new FullCalendar.Calendar($('#cal', racine), {
      locale: 'fr', firstDay: 1, initialDate: '2026-10-15', initialView: window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth',
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
      buttonText: { today: 'Aujourd\'hui', month: 'Mois', week: 'Semaine', day: 'Jour', list: 'Liste' },
      height: 'auto', editable: true, selectable: true, nowIndicator: true, dayMaxEvents: 3,
      slotMinTime: '07:00:00', slotMaxTime: '23:00:00', allDayText: 'Journée',
      events: evts.map((e) => ({ ...e, backgroundColor: TYPES[e.type].couleur, extendedProps: { type: e.type } })),
      eventDrop: (i) => toast(`« ${i.event.title} » déplacé au ${fmt.date(i.event.start)}. Les participants sont prévenus.`),
      eventResize: (i) => toast(`Durée de « ${i.event.title} » modifiée.`),
      select: (i) => creer(i.start, i.end, i.allDay),
      eventClick: (i) => confirmer({ titre: i.event.title, texte: `${TYPES[i.event.extendedProps.type || 'live'].libelle} — ${fmt.date(i.event.start)}. Voulez-vous annuler cet événement ?`, bouton: 'Annuler l\'événement', siOui: () => { i.event.remove(); toast('Événement annulé. Les participants sont prévenus.', 'alerte'); } }),
    });
    calendrier.render();

    $$('[data-type]', racine).forEach((c) => c.addEventListener('change', () => {
      c.checked ? actifs.add(c.dataset.type) : actifs.delete(c.dataset.type);
      calendrier.getEvents().forEach((ev) => ev.setProp('display', actifs.has(ev.extendedProps.type) ? 'auto' : 'none'));
    }));
    $('[data-nouvel]', racine).addEventListener('click', () => creer(null, null, false));
    icones();
  };

  /* ======================= Éditeur (Quill) ======================= */

  const quill = (el, place) => new Quill(el, {
    theme: 'snow', placeholder: place,
    modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'image', 'video'], ['clean']] },
  });
  VF.quill = quill;

  VF.ecrans['editeur'] = (racine, { type = 'article' } = {}) => {
    const libelles = { article: ['Nouvel article', 'Articles du blog', 'blog'], infolettre: ['Composer une infolettre', 'Infolettres', 'newsletters'], courriel: ['Modèle de courriel', 'Modèles de courriels', 'emails-modeles'] }[type] || ['Nouvel article', 'Articles du blog', 'blog'];
    racine.innerHTML = `
      ${enTete({ titre: libelles[0], fil: ['Communauté', libelles[1]], actions: `<span data-etat-sauvegarde class="text-xs texte-3 mr-1">Brouillon non enregistré</span><a href="#/${libelles[2]}" class="btn-second">Annuler</a><button class="btn-second" data-apercu>${icone('eye')}Aperçu</button><button class="btn-primaire" data-publier>${icone('send')}${type === 'infolettre' ? 'Programmer l\'envoi' : 'Publier'}</button>` })}
      <div class="grid xl:grid-cols-[1fr_340px] gap-4">
        <div class="space-y-4">
          <section class="carte p-5 space-y-4">
            <input id="ed-titre" class="w-full bg-transparent font-titre text-[28px] font-semibold leading-tight placeholder:text-encre-3 dark:placeholder:text-nuit-3 focus:outline-none" placeholder="${type === 'infolettre' ? 'Objet de l\'infolettre' : 'Titre de l\'article'}" value="${type === 'article' ? 'Vérifier une vidéo virale avant de la partager' : ''}" aria-label="Titre">
            <textarea id="ed-chapo" rows="2" class="w-full bg-transparent text-base texte-2 resize-none focus:outline-none" placeholder="Le chapô : ce que le lecteur retiendra en une phrase." aria-label="Chapô">${type === 'article' ? 'Trois gestes simples suffisent à démasquer la plupart des vidéos sorties de leur contexte.' : ''}</textarea>
            <div id="ed-quill"></div>
            ${type === 'infolettre' ? '<p class="text-xs texte-3">Variables disponibles : {prenom}, {lien_desinscription}, {derniers_fact_checks}.</p>' : ''}
          </section>
        </div>
        <aside class="space-y-4">
          ${carte({ titre: 'Publication', corps: `<div class="space-y-4">
            ${champ({ nom: 'categorie', libelle: 'Catégorie', options: ['Méthodes', 'Décryptages', 'Vie de l\'association', 'Éducation aux médias'] })}
            ${champ({ nom: 'quand', libelle: type === 'infolettre' ? 'Envoi programmé' : 'Date de publication', attrs: 'data-date-heure', place: 'Maintenant' })}
            <div class="flex items-center justify-between"><span class="text-sm">Mettre en avant sur l'accueil</span><button class="bascule" role="switch" aria-checked="false" aria-label="Mettre en avant"><span></span></button></div>
            <div class="flex items-center justify-between"><span class="text-sm">Autoriser les commentaires</span><button class="bascule" role="switch" aria-checked="true" aria-label="Commentaires"><span></span></button></div>
          </div>` })}
          ${carte({ titre: 'Image de couverture', corps: `<button class="block w-full rounded-ctrl overflow-hidden border filet" data-couverture><img src="assets/img/fausse-rumeur.webp" alt="Couverture actuelle" class="w-full h-36 object-cover"></button><p class="aide">Format 16:9, 1 200 px de large minimum.</p>` })}
          ${carte({ titre: 'Référencement', aide: 'Aperçu du résultat sur Google', corps: `<div class="rounded-ctrl border filet p-3.5"><p class="text-xs texte-3 truncate">verifall.com › blog › <span data-slug></span></p><p class="mt-1 text-[15px] text-blue-800 dark:text-blue-300 font-medium leading-snug" data-seo-titre></p><p class="mt-1 text-[13px] texte-2 leading-snug" data-seo-desc></p></div>
            <div class="mt-3 space-y-2 text-sm" data-controles-seo></div>` })}
        </aside>
      </div>`;

    const q = quill($('#ed-quill', racine), 'Écrivez ici. Les titres, listes, citations et images sont dans la barre d\'outils.');
    if (type === 'article') q.clipboard.dangerouslyPasteHTML('<h2>1. Chercher la première publication</h2><p>Faites une capture d\'une image clé et lancez une <strong>recherche inversée</strong>. Une vidéo « d\'aujourd\'hui » apparaît souvent des années plus tôt.</p><h2>2. Lire les détails</h2><ul><li>Les panneaux et la langue parlée</li><li>La météo et la saison</li><li>Les plaques d\'immatriculation</li></ul><blockquote>Une vidéo vraie peut mentir par son contexte.</blockquote>');

    const titre = $('#ed-titre', racine), chapo = $('#ed-chapo', racine);
    const maj = () => {
      const t = titre.value.trim(), d = chapo.value.trim();
      $('[data-slug]', racine).textContent = t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60) || 'titre-de-l-article';
      $('[data-seo-titre]', racine).textContent = (t || 'Titre de l\'article') + ' — Vérif\'All';
      $('[data-seo-desc]', racine).textContent = d || 'Le chapô sert de description dans les résultats de recherche.';
      const mots = q.getText().trim().split(/\s+/).filter(Boolean).length;
      const regles = [[t.length >= 30 && t.length <= 65, `Titre de ${t.length} caractères`, '30 à 65 recommandés'], [d.length >= 70 && d.length <= 160, `Chapô de ${d.length} caractères`, '70 à 160 recommandés'], [mots >= 300, `${mots} mots`, '300 au moins pour être bien classé'], [/<h2>/.test(q.root.innerHTML), 'Intertitres présents', 'ajoutez des intertitres']];
      $('[data-controles-seo]', racine).innerHTML = regles.map(([ok, l, conseil]) => `<p class="flex items-start gap-2">${icone(ok ? 'circle-check' : 'circle-alert', `w-4 h-4 mt-0.5 shrink-0 ${ok ? 'text-green-700 dark:text-bon' : 'text-amber-700 dark:text-amber-300'}`)}<span>${l}${ok ? '' : `<span class="texte-3"> — ${conseil}</span>`}</span></p>`).join('');
      $('[data-etat-sauvegarde]', racine).textContent = 'Enregistré automatiquement à ' + new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date());
      icones();
    };
    [titre, chapo].forEach((el) => el.addEventListener('input', maj));
    q.on('text-change', maj);
    maj();
    $('[data-publier]', racine).addEventListener('click', () => { if (!titre.value.trim()) { toast('Donnez un titre avant de publier.', 'critique'); titre.focus(); return; } toast(type === 'infolettre' ? 'Infolettre programmée pour 12 840 abonnés.' : 'Article publié. Il apparaît sur le blog.'); });
    $('[data-apercu]', racine).addEventListener('click', () => modale({ titre: 'Aperçu', large: true, corps: `<article class="max-w-none"><h1 class="font-titre text-2xl font-semibold">${esc(titre.value)}</h1><p class="mt-2 texte-2">${esc(chapo.value)}</p><div class="ql-snow mt-4"><div class="ql-editor !p-0 !min-h-0">${q.root.innerHTML}</div></div></article>` }));
    $('[data-couverture]', racine).addEventListener('click', () => (location.hash = '#/mediatheque'));
    dates(racine); bascules(racine); icones();
  };

  /* ======================= Constructeur de cours ======================= */

  VF.ecrans['constructeur'] = (racine, { mentor = false } = {}) => {
    const plan = [
      { titre: 'Introduction à l\'enquête en ligne', lecons: [['Pourquoi vérifier ?', 'video', '6 min'], ['Le vocabulaire de l\'OSINT', 'texte', '8 min'], ['Quiz d\'entrée', 'quiz', '5 questions']] },
      { titre: 'Les images', lecons: [['Recherche inversée', 'video', '11 min'], ['Lire les métadonnées EXIF', 'texte', '9 min'], ['Exercice : dater une photo', 'devoir', 'à rendre']] },
      { titre: 'Les comptes et les domaines', lecons: [['Qui se cache derrière un compte ?', 'video', '14 min'], ['Whois et archives', 'texte', '10 min'], ['Quiz de section', 'quiz', '8 questions']] },
    ];
    const icTypes = { video: 'circle-play', texte: 'file-text', quiz: 'list-checks', devoir: 'pen-line' };
    let actif = [1, 0];

    racine.innerHTML = `
      ${enTete({ titre: 'OSINT Fondamentaux', sousTitre: 'Réorganisez sections et leçons par glisser-déposer. Le contenu de la leçon s\'édite à droite.', fil: [mentor ? 'Espace pédagogique' : 'Académie', mentor ? 'Sections et leçons' : 'Constructeur de cours'], actions: `<button class="btn-second" onclick="VF.ui.toast('Aperçu apprenant ouvert.','info')">${icone('eye')}Voir comme un apprenant</button><button class="btn-primaire" onclick="VF.ui.toast('Cours publié. Les inscrits sont prévenus des nouvelles leçons.')">${icone('upload')}Publier les changements</button>` })}
      <div class="grid xl:grid-cols-[360px_1fr] gap-4">
        <section class="carte">
          <div class="carte-tete"><h2 class="carte-titre">Plan du cours</h2><button class="btn-fantome btn-sm" data-ajout-section>${icone('plus')}Section</button></div>
          <div id="plan" class="px-3 pb-3 space-y-3"></div>
        </section>
        <section class="carte p-5" id="lecon"></section>
      </div>`;

    const rendrePlan = () => {
      $('#plan', racine).innerHTML = plan.map((s, si) => `
        <div class="rounded-ctrl border filet" data-section="${si}">
          <div class="flex items-center gap-2 px-3 h-11 border-b filet">
            ${icone('grip-vertical', 'w-4 h-4 texte-3')}
            <p class="flex-1 text-sm font-semibold truncate">${si + 1}. ${esc(s.titre)}</p>
            <span class="text-xs texte-3">${s.lecons.length} leçons</span>
          </div>
          <ul class="p-1.5 space-y-0.5" data-liste="${si}">
            ${s.lecons.map(([t, ty, d], li) => `<li draggable="true" data-lecon="${si}-${li}" class="flex items-center gap-2.5 h-10 px-2 rounded-[6px] cursor-grab text-sm ${actif[0] === si && actif[1] === li ? 'bg-brand-50 dark:bg-brand/10 font-medium' : 'hover:bg-papier dark:hover:bg-nuit'}">
              ${icone('grip-vertical', 'w-3.5 h-3.5 texte-3')}${icone(icTypes[ty], 'w-4 h-4 texte-2')}<span class="flex-1 truncate">${esc(t)}</span><span class="text-xs texte-3">${esc(d)}</span></li>`).join('')}
            <li><button class="flex items-center gap-2 h-9 px-2 w-full rounded-[6px] text-[13px] texte-2 hover:bg-papier dark:hover:bg-nuit" data-ajout-lecon="${si}">${icone('plus', 'w-3.5 h-3.5')}Ajouter une leçon</button></li>
          </ul>
        </div>`).join('');
      icones();

      let source = null;
      $$('[data-lecon]', racine).forEach((li) => {
        li.addEventListener('click', () => { actif = li.dataset.lecon.split('-').map(Number); rendrePlan(); rendreLecon(); });
        li.addEventListener('dragstart', (e) => { source = li.dataset.lecon.split('-').map(Number); e.dataTransfer.effectAllowed = 'move'; li.classList.add('opacity-40'); });
        li.addEventListener('dragend', () => li.classList.remove('opacity-40'));
        li.addEventListener('dragover', (e) => { e.preventDefault(); li.classList.add('ring-2', 'ring-brand/40'); });
        li.addEventListener('dragleave', () => li.classList.remove('ring-2', 'ring-brand/40'));
        li.addEventListener('drop', (e) => {
          e.preventDefault();
          const [ds, dl] = li.dataset.lecon.split('-').map(Number);
          if (!source) return;
          const [element] = plan[source[0]].lecons.splice(source[1], 1);
          plan[ds].lecons.splice(dl, 0, element);
          actif = [ds, dl]; source = null;
          rendrePlan(); rendreLecon();
          toast(`« ${element[0]} » déplacée. L'ordre est enregistré.`);
        });
      });
      $$('[data-ajout-lecon]', racine).forEach((b) => b.addEventListener('click', () => { const si = +b.dataset.ajoutLecon; plan[si].lecons.push(['Nouvelle leçon', 'texte', 'brouillon']); actif = [si, plan[si].lecons.length - 1]; rendrePlan(); rendreLecon(); }));
    };

    const rendreLecon = () => {
      const [t, ty, d] = plan[actif[0]].lecons[actif[1]];
      $('#lecon', racine).innerHTML = `
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <p class="text-xs texte-3">Section ${actif[0] + 1} — leçon ${actif[1] + 1}</p>
            <input class="mt-1 w-full bg-transparent font-titre text-2xl font-semibold focus:outline-none" value="${esc(t)}" aria-label="Titre de la leçon" data-titre-lecon>
          </div>
          <div class="segments" role="group" aria-label="Type de leçon">${Object.entries({ video: 'Vidéo', texte: 'Texte', quiz: 'Quiz', devoir: 'Devoir' }).map(([k, l]) => `<button class="segment" data-type-lecon="${k}" aria-pressed="${k === ty}">${l}</button>`).join('')}</div>
        </div>
        <div class="mt-5 grid sm:grid-cols-3 gap-3">
          ${champ({ nom: 'duree', libelle: 'Durée estimée', valeur: d })}
          ${champ({ nom: 'acces', libelle: 'Accès', options: ['Inscrits', 'Aperçu gratuit', 'Premium uniquement'] })}
          ${champ({ nom: 'ouverture', libelle: 'Ouverture', attrs: 'data-date', place: 'Immédiate' })}
        </div>
        ${ty === 'video' ? `<div class="mt-4">${champ({ nom: 'video', libelle: 'Adresse de la vidéo', type: 'url', valeur: 'https://www.youtube.com/watch?v=verifall-osint', aide: 'YouTube, Vimeo ou fichier hébergé sur le disque privé.' })}</div>` : ''}
        <div class="mt-4"><p class="etiquette">Contenu de la leçon</p><div id="lecon-quill"></div></div>
        <div class="mt-4 flex items-center justify-between gap-3 rounded-ctrl border border-dashed filet p-4">
          <div class="flex items-center gap-3">${icone('paperclip', 'w-5 h-5 texte-3')}<div><p class="text-sm font-medium">Supports téléchargeables</p><p class="text-xs texte-3">PDF servis par lien signé, jamais en accès public.</p></div></div>
          <button class="btn-second btn-sm" onclick="VF.ui.toast('Support ajouté : fiche-methode.pdf')">${icone('upload')}Ajouter</button>
        </div>`;
      const q = quill($('#lecon-quill', racine), 'Le contenu de la leçon…');
      q.clipboard.dangerouslyPasteHTML(`<p>Objectif de la leçon : <strong>${esc(t)}</strong>.</p><ol><li>Observez</li><li>Cherchez la source</li><li>Recoupez</li></ol>`);
      $('[data-titre-lecon]', racine).addEventListener('change', (e) => { plan[actif[0]].lecons[actif[1]][0] = e.target.value; rendrePlan(); });
      $$('[data-type-lecon]', racine).forEach((b) => b.addEventListener('click', () => { plan[actif[0]].lecons[actif[1]][1] = b.dataset.typeLecon; rendrePlan(); rendreLecon(); }));
      dates($('#lecon', racine)); icones();
    };

    $('[data-ajout-section]', racine).addEventListener('click', () => { plan.push({ titre: 'Nouvelle section', lecons: [] }); rendrePlan(); toast('Section ajoutée en fin de plan.'); });
    rendrePlan(); rendreLecon();
  };

  /* ======================= FAQ ======================= */

  VF.ecrans['faq'] = (racine) => {
    const categories = [...new Set(VF.faq.map((f) => f.categorie))];
    let cat = 'Toutes', q = '';
    racine.innerHTML = `
      ${enTete({ titre: 'Questions fréquentes', sousTitre: 'Les réponses publiées sur le site. Cherchez, filtrez, modifiez sur place.', fil: ['Communauté'], actions: `<button class="btn-primaire" data-ajout>${icone('plus')}Nouvelle question</button>` })}
      <div class="max-w-3xl">
        <label class="relative block"><span class="sr-only">Chercher dans la FAQ</span><span class="absolute left-3.5 top-1/2 -translate-y-1/2 texte-3">${icone('search', 'w-[18px] h-[18px]')}</span><input data-q class="champ !h-12 pl-11 text-base" placeholder="Chercher une question, par exemple « unités » ou « certificat »"></label>
        <div class="mt-4 flex flex-wrap gap-2" role="group" aria-label="Catégories">${['Toutes', ...categories].map((c) => `<button class="h-8 px-3 rounded-full border filet text-sm aria-pressed:bg-foret aria-pressed:text-white aria-pressed:border-foret dark:aria-pressed:bg-brand dark:aria-pressed:text-foret" data-cat="${esc(c)}" aria-pressed="${c === 'Toutes'}">${esc(c)}</button>`).join('')}</div>
        <div data-liste class="mt-6 space-y-6"></div>
      </div>`;

    const surligner = (texte) => !q ? esc(texte) : esc(texte).replace(new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'), '<mark class="bg-brand-100 text-foret rounded px-0.5">$1</mark>');
    const rendre = () => {
      const liste = VF.faq.filter((f) => (cat === 'Toutes' || f.categorie === cat) && (!q || (f.q + f.r).toLowerCase().includes(q.toLowerCase())));
      const groupes = categories.filter((c) => liste.some((f) => f.categorie === c));
      $('[data-liste]', racine).innerHTML = liste.length ? groupes.map((g) => `
        <section><h2 class="font-titre text-base font-semibold mb-2">${esc(g)}</h2>
          <div class="carte divide-y divide-trait dark:divide-nuit-trait">${liste.filter((f) => f.categorie === g).map((f) => `
            <details class="group">
              <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                <span class="flex-1 font-medium">${surligner(f.q)}</span>
                ${icone('chevron-down', 'w-[18px] h-[18px] texte-3 transition-transform group-open:rotate-180')}
              </summary>
              <div class="px-5 pb-4 -mt-1"><p class="text-sm texte-2 leading-relaxed">${surligner(f.r)}</p>
                <div class="mt-3 flex gap-1"><button class="btn-fantome btn-sm" onclick="VF.ui.toast('Ouvert en modification.','info')">${icone('pencil')}Modifier</button><button class="btn-fantome btn-sm" onclick="VF.ui.toast('Question masquée du site.','alerte')">${icone('eye-off')}Masquer</button></div>
              </div>
            </details>`).join('')}</div></section>`).join('') : VF.ui.vide({ ic: 'search-x', titre: 'Aucune question ne correspond', texte: 'Essayez un autre mot, ou ajoutez cette question à la FAQ.', action: `<button class="btn-primaire" data-ajout>${icone('plus')}Ajouter la question</button>` });
      icones();
    };
    $('[data-q]', racine).addEventListener('input', (e) => { q = e.target.value.trim(); rendre(); });
    $$('[data-cat]', racine).forEach((b) => b.addEventListener('click', () => { cat = b.dataset.cat; $$('[data-cat]', racine).forEach((x) => x.setAttribute('aria-pressed', String(x === b))); rendre(); }));
    racine.addEventListener('click', (e) => { if (e.target.closest('[data-ajout]')) VF.gestion.formulaire({ entite: 'question', feminin: true, champs: [{ nom: 'q', libelle: 'Question', regles: 'requis|min:10' }, { nom: 'categorie', libelle: 'Catégorie', options: categories }, { nom: 'r', libelle: 'Réponse', type: 'textarea', regles: 'requis' }] }, null, (v) => { VF.faq.unshift(v); rendre(); }); });
    rendre();
  };

  /* ======================= Mon compte (onglets) ======================= */

  VF.ecrans['compte'] = (racine, { onglet = 'profil' } = {}) => {
    const moi = VF.moi[VF.espace || 'admin'];
    racine.innerHTML = `
      ${enTete({ titre: 'Mon compte', sousTitre: 'Votre profil, la sécurité de votre accès et vos préférences.' })}
      <div class="onglets mb-6" role="tablist" aria-label="Sections du compte">
        <button class="onglet" role="tab" aria-selected="true" aria-controls="o-profil" data-onglet="profil">Profil</button>
        <button class="onglet" role="tab" aria-selected="false" aria-controls="o-securite" data-onglet="securite">Sécurité</button>
        <button class="onglet" role="tab" aria-selected="false" aria-controls="o-pref" data-onglet="pref">Préférences</button>
      </div>
      <div id="o-profil" role="tabpanel" class="max-w-3xl">
        <form class="carte p-5 space-y-5" id="f-profil" novalidate>
          <div class="flex items-center gap-4"><span class="avatar w-16 h-16 !text-lg">${esc(moi.initiales)}</span><div><button type="button" class="btn-second btn-sm">${icone('upload')}Changer la photo</button><p class="aide">JPG ou PNG, 2 Mo au plus.</p></div></div>
          <div class="grid sm:grid-cols-2 gap-4">
            ${champ({ nom: 'nom', libelle: 'Nom complet', valeur: moi.nom, regles: 'requis' })}
            ${champ({ nom: 'email', libelle: 'Courriel', type: 'email', valeur: moi.email, regles: 'requis|email', aide: 'Un code de confirmation sera envoyé à la nouvelle adresse.' })}
            ${champ({ nom: 'tel', libelle: 'Téléphone', type: 'tel', valeur: '+229 01 97 00 00 00' })}
            ${champ({ nom: 'ville', libelle: 'Ville', options: ['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey-Calavi'], valeur: 'Cotonou' })}
          </div>
          ${champ({ nom: 'bio', libelle: 'Présentation', type: 'textarea', valeur: 'Responsable de la vérification et de l\'Académie.', aide: 'Visible sur vos fact-checks et vos cours.' })}
          <div class="flex justify-end"><button class="btn-primaire" type="submit">${icone('check')}Enregistrer le profil</button></div>
        </form>
      </div>
      <div id="o-securite" role="tabpanel" class="max-w-3xl space-y-4" hidden>
        <form class="carte p-5 space-y-4" id="f-mdp" novalidate>
          <h2 class="carte-titre">Mot de passe</h2>
          ${champ({ nom: 'actuel', libelle: 'Mot de passe actuel', type: 'password', regles: 'requis' })}
          <div class="grid sm:grid-cols-2 gap-4">${champ({ nom: 'nouveau', libelle: 'Nouveau mot de passe', type: 'password', regles: 'requis|min:12', aide: '12 caractères au moins.' })}${champ({ nom: 'confirmation', libelle: 'Confirmation', type: 'password', regles: 'requis' })}</div>
          <div class="flex justify-end"><button class="btn-primaire" type="submit">Changer le mot de passe</button></div>
        </form>
        <section class="carte p-5 flex items-start justify-between gap-4">
          <div><h2 class="carte-titre">Double authentification</h2><p class="text-sm texte-2 mt-1">Un code d'une application d'authentification est demandé à chaque connexion. Obligatoire pour les administrateurs.</p></div>
          ${pastille('p-bon', 'shield-check', 'Activée')}
        </section>
        <section class="carte">
          <div class="carte-tete"><h2 class="carte-titre">Sessions ouvertes</h2><button class="btn-second btn-sm" onclick="VF.ui.toast('Les autres sessions sont fermées.','alerte')">Fermer les autres sessions</button></div>
          <ul class="carte-corps space-y-3 text-sm">${[['monitor', 'Chrome sur Windows', 'Cotonou — cette session', true], ['smartphone', 'Application Vérif\'All, Android', 'Cotonou — il y a 2 h', false], ['laptop', 'Firefox sur Ubuntu', 'Porto-Novo — il y a 3 j', false]].map(([ic, a, b, ici]) => `<li class="flex items-center gap-3">${icone(ic, 'w-5 h-5 texte-3')}<div class="flex-1"><p class="font-medium">${a}</p><p class="text-xs texte-3">${b}</p></div>${ici ? pastille('p-bon', 'circle-dot', 'Actuelle') : '<button class="btn-fantome btn-sm" onclick="VF.ui.toast(\'Session fermée.\')">Fermer</button>'}</li>`).join('')}</ul>
        </section>
      </div>
      <div id="o-pref" role="tabpanel" class="max-w-3xl space-y-4" hidden>
        <section class="carte p-5 space-y-4">
          <h2 class="carte-titre">Affichage</h2>
          <div class="grid sm:grid-cols-2 gap-4">${champ({ nom: 'langue', libelle: 'Langue', options: ['Français', 'English', 'Fɔ̀ngbè (bientôt)'] })}${champ({ nom: 'fuseau', libelle: 'Fuseau horaire', options: ['Afrique/Porto-Novo (UTC+1)'] })}</div>
          <div><p class="etiquette">Thème</p><div class="segments" role="group" aria-label="Thème">${[['clair', 'Clair'], ['sombre', 'Sombre'], ['systeme', 'Comme le système']].map(([k, l]) => `<button class="segment" data-theme-choix="${k}" aria-pressed="${(k === 'sombre') === document.documentElement.classList.contains('dark') && k !== 'systeme'}">${l}</button>`).join('')}</div></div>
        </section>
        <section class="carte p-5">
          <h2 class="carte-titre mb-3">Notifications</h2>
          <ul class="divide-y divide-trait dark:divide-nuit-trait">${[['Signalement viral (viralité > 70)', true], ['Moteur d\'analyse dégradé', true], ['Copie à corriger', true], ['Nouveau dossier KYC', false], ['Résumé hebdomadaire par courriel', true]].map(([l, v]) => `<li class="flex items-center justify-between gap-3 py-3"><span class="text-sm">${l}</span><button class="bascule" role="switch" aria-checked="${v}" aria-label="${esc(l)}" data-toast="Notification « ${esc(l)} »"><span></span></button></li>`).join('')}</ul>
        </section>
      </div>`;
    onglets(racine); bascules(racine);
    const ongletDemande = $(`[role="tab"][data-onglet="${onglet}"]`, racine);
    if (ongletDemande && onglet !== 'profil') ongletDemande.click();
    $('#f-profil', racine).addEventListener('submit', (e) => { e.preventDefault(); if (valider(e.target)) toast('Profil enregistré.'); });
    $('#f-mdp', racine).addEventListener('submit', (e) => {
      e.preventDefault();
      if (!valider(e.target)) return;
      const f = new FormData(e.target);
      if (f.get('nouveau') !== f.get('confirmation')) { const c = $('#c-confirmation', racine); c.classList.add('champ-erreur'); const a = c.parentElement.querySelector('[data-aide]'); a.className = 'aide-erreur'; a.textContent = 'Les deux mots de passe ne sont pas identiques.'; return; }
      e.target.reset(); toast('Mot de passe changé. Les autres sessions sont fermées.');
    });
    $$('[data-theme-choix]', racine).forEach((b) => b.addEventListener('click', () => { $$('[data-theme-choix]', racine).forEach((x) => x.setAttribute('aria-pressed', String(x === b))); VF.appliquerTheme(b.dataset.themeChoix); }));
  };

  /* ======================= Rôles et permissions ======================= */

  VF.ecrans['roles'] = (racine) => {
    const roles = ['Super admin', 'Admin', 'Modérateur', 'Analyste', 'Mentor', 'Comptable'];
    const groupes = [
      ['Fact-checking', ['Voir les signalements', 'Publier un fact-check', 'Gérer les sources']],
      ['Académie', ['Créer un cours', 'Corriger les examens', 'Délivrer un certificat']],
      ['Utilisateurs', ['Voir les comptes', 'Suspendre un compte', 'Valider un KYC', 'Modifier les rôles']],
      ['Économie', ['Voir les paiements', 'Créditer des unités', 'Rembourser']],
      ['Système', ['Modifier les réglages', 'Restaurer une sauvegarde', 'Lire le journal d\'audit']],
    ];
    const accorde = (r, p) => r === 0 || (r === 1 && !/rôles|Restaurer/.test(p)) || (r === 2 && /signalements|Suspendre|comptes/.test(p)) || (r === 3 && /signalements|fact-check|sources/.test(p)) || (r === 4 && /cours|Corriger|certificat/.test(p)) || (r === 5 && /paiements|Rembourser|Créditer/.test(p));
    racine.innerHTML = `
      ${enTete({ titre: 'Rôles et permissions', sousTitre: 'Qui peut faire quoi. Chaque changement est journalisé et prend effet à la prochaine requête.', fil: ['Utilisateurs'], actions: `<button class="btn-primaire" onclick="VF.ui.toast('Rôle créé. Attribuez-lui des permissions.')">${icone('plus')}Nouveau rôle</button>` })}
      <section class="carte overflow-x-auto">
        <table class="tableau">
          <thead><tr><th class="min-w-[240px]">Permission</th>${roles.map((r, i) => `<th class="text-center"><span class="block">${r}</span><span class="block text-[11px] font-normal">${[2, 5, 4, 9, 6, 2][i]} membres</span></th>`).join('')}</tr></thead>
          <tbody>${groupes.map(([g, perms]) => `<tr class="!bg-papier dark:!bg-nuit"><td colspan="${roles.length + 1}" class="!py-2 text-xs font-semibold texte-2">${g}</td></tr>${perms.map((p) => `<tr><td>${p}</td>${roles.map((r, ri) => `<td class="text-center"><input type="checkbox" class="case" ${accorde(ri, p) ? 'checked' : ''} ${ri === 0 ? 'disabled title="Le super admin a toutes les permissions"' : ''} aria-label="${esc(p)} — ${r}" data-perm="${esc(p)}" data-role="${r}"></td>`).join('')}</tr>`).join('')}`).join('')}</tbody>
        </table>
      </section>`;
    $$('[data-perm]', racine).forEach((c) => c.addEventListener('change', () => toast(`${c.dataset.role} : « ${c.dataset.perm} » ${c.checked ? 'accordée' : 'retirée'}. Journalisé.`, c.checked ? 'bon' : 'alerte')));
  };

  /* ======================= KYC (GLightbox) ======================= */

  VF.ecrans['kyc'] = (racine) => {
    const dossiers = Array.from({ length: 6 }, (_, i) => ({ qui: VF.outils.personne(), piece: ['CNI biométrique', 'Passeport', 'Carte CIP'][i % 3], depuis: ['il y a 2 h', 'il y a 5 h', 'hier', 'hier', 'il y a 2 j', 'il y a 3 j'][i], role: i % 2 ? 'Mentor' : 'Partenaire', images: [VF.medias[i].fichier, VF.medias[i + 2].fichier] }));
    racine.innerHTML = `
      ${enTete({ titre: 'Vérification d\'identité', sousTitre: 'Les pièces sont stockées sur le disque privé et ne s\'ouvrent qu\'ici. Chaque consultation est journalisée.', fil: ['Utilisateurs'] })}
      <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4" data-dossiers>${dossiers.map((d, i) => `
        <article class="carte p-4" data-dossier="${i}">
          <div class="flex items-center gap-3"><span class="avatar w-10 h-10">${esc(d.qui.initiales)}</span><div class="min-w-0 flex-1"><p class="font-medium truncate">${esc(d.qui.nom)}</p><p class="text-xs texte-3">${d.role} — déposé ${d.depuis}</p></div>${pastille('p-alerte', 'clock', 'À examiner')}</div>
          <p class="mt-4 text-sm texte-2">${d.piece}, recto et verso</p>
          <div class="mt-2 grid grid-cols-2 gap-2">${d.images.map((src, k) => `<a href="${src}" class="glightbox block rounded-ctrl overflow-hidden border filet" data-gallery="kyc-${i}" data-title="${esc(d.qui.nom)} — ${k ? 'verso' : 'recto'}" data-description="${d.piece}"><img src="${src}" alt="${d.piece} ${k ? 'verso' : 'recto'}" class="h-24 w-full object-cover"></a>`).join('')}</div>
          <div class="mt-4 flex gap-2"><button class="btn-second btn-sm flex-1" data-refuser="${i}">${icone('x')}Refuser</button><button class="btn-primaire btn-sm flex-1" data-valider="${i}">${icone('check')}Valider</button></div>
        </article>`).join('')}</div>`;
    const lb = GLightbox({ selector: '.glightbox', touchNavigation: true, loop: true });
    VF.nettoyages.push(() => lb.destroy());
    const retirer = (i, texte, ton) => { const a = $(`[data-dossier="${i}"]`, racine); a.style.opacity = '0'; a.style.transition = 'opacity .2s'; setTimeout(() => a.remove(), 200); toast(texte, ton); };
    $$('[data-valider]', racine).forEach((b) => b.addEventListener('click', () => retirer(b.dataset.valider, `Identité de ${dossiers[b.dataset.valider].qui.nom} validée.`)));
    $$('[data-refuser]', racine).forEach((b) => b.addEventListener('click', () => modale({
      titre: 'Refuser le dossier',
      corps: `<form id="f-refus" class="space-y-3" novalidate>${champ({ nom: 'motif', libelle: 'Motif communiqué à la personne', options: ['Pièce illisible', 'Pièce expirée', 'Nom différent du compte', 'Recto ou verso manquant'] })}${champ({ nom: 'note', libelle: 'Précision', type: 'textarea', place: 'Facultatif' })}</form>`,
      actions: `<button class="btn-second" data-fermer>Annuler</button><button class="btn-danger" form="f-refus" type="submit">Refuser le dossier</button>`,
      apres: (c) => $('#f-refus', c).addEventListener('submit', (e) => { e.preventDefault(); fermerCouche('modale'); retirer(b.dataset.refuser, 'Dossier refusé. La personne peut en déposer un nouveau.', 'alerte'); }),
    })));
  };

  /* ======================= Médiathèque ======================= */

  VF.ecrans['mediatheque'] = (racine) => {
    racine.innerHTML = `
      ${enTete({ titre: 'Médiathèque', sousTitre: 'Images des articles, des cours et des campagnes. Cliquez pour agrandir.', fil: ['Communauté'] })}
      <label class="flex flex-col items-center justify-center gap-2 p-8 mb-6 rounded-carte border-2 border-dashed filet text-center cursor-pointer hover:border-brand/60 transition-colors" data-depot>
        ${icone('image-up', 'w-7 h-7 texte-3')}
        <span class="font-medium">Déposez des images ici, ou cliquez pour les choisir</span>
        <span class="text-xs texte-3">JPG, PNG ou WebP, 5 Mo au plus. Converties en WebP à l'envoi.</span>
        <input type="file" accept="image/*" multiple class="sr-only">
      </label>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">${VF.medias.map((m) => `
        <figure class="carte overflow-hidden">
          <a href="${m.fichier}" class="glightbox block" data-gallery="medias" data-title="${esc(m.titre)}"><img src="${m.fichier}" alt="${esc(m.titre)}" class="aspect-[4/3] w-full object-cover" loading="lazy"></a>
          <figcaption class="flex items-center gap-2 p-2.5"><div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">${esc(m.titre)}</p><p class="text-xs texte-3">${m.poids}</p></div><button class="btn-icone !w-8 !h-8" aria-label="Copier le lien" onclick="VF.ui.toast('Lien de l\\'image copié.')">${icone('link')}</button></figcaption>
        </figure>`).join('')}</div>`;
    const lb = GLightbox({ selector: '.glightbox', loop: true });
    VF.nettoyages.push(() => lb.destroy());
    const depot = $('[data-depot]', racine);
    ['dragover', 'dragenter'].forEach((t) => depot.addEventListener(t, (e) => { e.preventDefault(); depot.classList.add('border-brand', 'bg-brand-50/50'); }));
    ['dragleave', 'drop'].forEach((t) => depot.addEventListener(t, () => depot.classList.remove('border-brand', 'bg-brand-50/50')));
    depot.addEventListener('drop', (e) => { e.preventDefault(); toast(`${e.dataTransfer.files.length} image(s) envoyée(s).`); });
    $('input', depot).addEventListener('change', (e) => toast(`${e.target.files.length} image(s) envoyée(s).`));
  };

  /* ======================= Réglages du système ======================= */

  VF.ecrans['parametres'] = (racine) => {
    const cle = (nom, valeur) => `<div><label class="etiquette" for="k-${nom}">${nom}</label><div class="flex gap-2"><input id="k-${nom}" type="password" class="champ font-mono text-xs" value="${valeur}" autocomplete="off"><button class="btn-second" type="button" data-voir="k-${nom}" aria-label="Afficher">${icone('eye')}</button></div></div>`;
    racine.innerHTML = `
      ${enTete({ titre: 'Réglages du système', sousTitre: 'Les clés sont lues depuis le fichier .env du serveur et ne sont jamais affichées en clair.', fil: ['Système'] })}
      <div class="onglets mb-6" role="tablist">
        ${[['general', 'Général'], ['moteurs', 'Moteurs IA'], ['paiements', 'Paiements'], ['securite', 'Sécurité'], ['maintenance', 'Maintenance']].map(([k, l], i) => `<button class="onglet" role="tab" aria-selected="${!i}" aria-controls="p-${k}">${l}</button>`).join('')}
      </div>
      <div id="p-general" role="tabpanel" class="carte p-5 max-w-3xl grid sm:grid-cols-2 gap-4">
        ${champ({ nom: 'site', libelle: 'Nom du site', valeur: 'Vérif\'All' })}${champ({ nom: 'contact', libelle: 'Courriel de contact', type: 'email', valeur: 'contact@verifall.com', regles: 'email' })}
        ${champ({ nom: 'dotation', libelle: 'Dotation mensuelle gratuite (unités)', type: 'number', valeur: '50' })}${champ({ nom: 'langue', libelle: 'Langue par défaut', options: ['Français', 'English'] })}
      </div>
      <div id="p-moteurs" role="tabpanel" class="max-w-3xl space-y-4" hidden>
        <div class="carte p-5 space-y-4">
          <div class="grid sm:grid-cols-2 gap-4">${champ({ nom: 'modele', libelle: 'Modèle Gemini principal', valeur: 'gemini-3.5-flash-lite', aide: 'Variable GEMINI_MODEL.' })}${champ({ nom: 'repli', libelle: 'Modèle de repli', valeur: 'gemini-3.1-flash-lite', aide: 'Utilisé quand le principal refuse (quota).' })}</div>
          ${cle('GEMINI_API_KEY', '••••••••••••••••')}${cle('OPENAI_API_KEY', '••••••••••••••••')}${cle('DEEPSEEK_API_KEY', '••••••••••••••••')}
          <p class="text-xs texte-3">Une clé modifiée ici est écrite dans .env puis le cache de configuration est vidé. Pensez à révoquer l'ancienne chez le fournisseur.</p>
        </div>
        <div class="carte p-5"><h2 class="carte-titre mb-3">Moteurs activés</h2><ul class="grid sm:grid-cols-2 gap-x-6">${VF.moteurs.map((m) => `<li class="flex items-center justify-between gap-3 py-2.5 border-b filet text-sm"><span>${esc(m.nom)}</span><button class="bascule" role="switch" aria-checked="${m.etat !== 'quota'}" aria-label="${esc(m.nom)}" data-toast="Moteur ${esc(m.nom)}"><span></span></button></li>`).join('')}</ul></div>
      </div>
      <div id="p-paiements" role="tabpanel" class="carte p-5 max-w-3xl space-y-4" hidden>
        <div class="flex items-start gap-3 rounded-ctrl bg-amber-50 dark:bg-amber-950 p-3.5 text-sm">${icone('triangle-alert', 'w-[18px] h-[18px] mt-0.5 text-amber-700 dark:text-amber-300')}<p>Le pilote Mobile Money est en mode simulation (<code>MOMO_DRIVER=fake</code>). Aucun paiement réel n'est encaissé.</p></div>
        <div class="grid sm:grid-cols-2 gap-4">${champ({ nom: 'pilote', libelle: 'Pilote', options: ['Simulation', 'MTN MoMo', 'Moov Money', 'Celtiis Cash'] })}${champ({ nom: 'prix', libelle: 'Prix de 100 unités (FCFA)', type: 'number', valeur: '1000' })}</div>
        ${cle('PAYMENT_WEBHOOK_SECRET', '')}
      </div>
      <div id="p-securite" role="tabpanel" class="carte p-5 max-w-3xl" hidden>
        <ul class="divide-y divide-trait dark:divide-nuit-trait">${[['Double authentification obligatoire pour les administrateurs', true], ['Double authentification pour les mentors', false], ['Verrouiller un compte après 5 échecs de connexion', true], ['Journaliser chaque consultation de pièce KYC', true], ['Bloquer les connexions hors du Bénin pour le personnel', false]].map(([l, v]) => `<li class="flex items-center justify-between gap-3 py-3.5"><span class="text-sm">${l}</span><button class="bascule" role="switch" aria-checked="${v}" aria-label="${esc(l)}" data-toast="${esc(l)} :"><span></span></button></li>`).join('')}</ul>
      </div>
      <div id="p-maintenance" role="tabpanel" class="carte p-5 max-w-3xl space-y-4" hidden>
        <div class="flex items-center justify-between gap-3"><div><p class="font-medium">Mode maintenance</p><p class="text-sm texte-2">Le public voit une page d'attente ; le personnel connecté continue de travailler.</p></div><button class="bascule" role="switch" aria-checked="false" aria-label="Mode maintenance" data-toast="Mode maintenance"><span></span></button></div>
        <div class="flex flex-wrap gap-2 pt-2 border-t filet"><button class="btn-second" onclick="VF.ui.toast('Cache de configuration vidé.')">${icone('eraser')}Vider le cache</button><button class="btn-second" onclick="location.hash='#/sauvegardes'">${icone('database-backup')}Sauvegardes</button><button class="btn-second" onclick="VF.ui.toast('Diagnostic : tout est vert sauf Google Analytics (retard de 26 h).','info')">${icone('stethoscope')}Lancer le diagnostic</button></div>
      </div>
      <div class="max-w-3xl mt-5 flex justify-end"><button class="btn-primaire" onclick="VF.ui.toast('Réglages enregistrés. Journalisé.')">${icone('check')}Enregistrer les réglages</button></div>`;
    onglets(racine); bascules(racine);
    $$('[data-voir]', racine).forEach((b) => b.addEventListener('click', () => toast('Pour des raisons de sécurité, une clé ne se réaffiche jamais. Saisissez-en une nouvelle pour la remplacer.', 'info')));
  };

  /* ======================= Modèle de certificat ======================= */

  VF.ecrans['certificat-modele'] = (racine) => {
    racine.innerHTML = `
      ${enTete({ titre: 'Modèle de certificat', sousTitre: 'Ce que reçoit un apprenant qui termine un cours. L\'aperçu suit vos réglages.', fil: ['Académie', 'Certificats'], actions: `<button class="btn-primaire" onclick="VF.ui.toast('Modèle enregistré. Il s\\'applique aux prochains certificats.')">${icone('check')}Enregistrer</button>` })}
      <div class="grid xl:grid-cols-[1fr_340px] gap-4">
        <section class="carte p-6 sm:p-10 bg-[#fbfcfa] dark:bg-nuit-carte">
          <div id="certif" class="mx-auto max-w-[760px] aspect-[1.414/1] bg-white text-foret rounded-[6px] border-[10px] border-double p-8 sm:p-12 flex flex-col justify-between" style="border-color:#0E2A1E">
            <div class="flex items-center justify-between"><img src="assets/img/logo.png" alt="Vérif'All" class="h-8"><span class="text-xs">Code VF-482193</span></div>
            <div class="text-center">
              <p class="font-titre text-sm" data-c-sur>Certificat de réussite</p>
              <p class="mt-4 font-titre text-[34px] sm:text-[42px] font-semibold leading-tight">Aïcha Bello</p>
              <p class="mt-3 text-sm max-w-md mx-auto">a suivi et validé le cours <strong data-c-cours>OSINT Fondamentaux</strong> avec la note de 17 / 20.</p>
            </div>
            <div class="flex items-end justify-between text-xs"><div><p class="font-titre text-base" data-c-signataire>Mawuli Adjovi</p><p>Directeur de l'Académie</p></div><p>Cotonou, le 15 octobre 2026<br>verifall.com/certificat/VF-482193</p></div>
          </div>
        </section>
        <aside class="carte p-5 space-y-4">
          ${champ({ nom: 'sur', libelle: 'Intitulé', valeur: 'Certificat de réussite' })}
          ${champ({ nom: 'signataire', libelle: 'Signataire', valeur: 'Mawuli Adjovi' })}
          ${champ({ nom: 'cours', libelle: 'Cours de l\'aperçu', options: VF.cours.map((c) => c.titre) })}
          <div><p class="etiquette">Couleur du cadre</p><div class="flex gap-2">${['#0E2A1E', '#09B850', '#256abf', '#8a6d1d'].map((c, i) => `<button class="w-8 h-8 rounded-full ring-offset-2 ring-offset-white dark:ring-offset-nuit-carte ${i ? '' : 'ring-2 ring-brand'}" style="background:${c}" data-cadre="${c}" aria-label="Couleur ${c}"></button>`).join('')}</div></div>
          <div class="flex items-center justify-between"><span class="text-sm">QR code de vérification</span><button class="bascule" role="switch" aria-checked="true" aria-label="QR code"><span></span></button></div>
        </aside>
      </div>`;
    const lier = (nom, cible) => $('#c-' + nom, racine).addEventListener('input', (e) => { $(cible, racine).textContent = e.target.value; });
    lier('sur', '[data-c-sur]'); lier('signataire', '[data-c-signataire]');
    $('#c-cours', racine).addEventListener('change', (e) => { $('[data-c-cours]', racine).textContent = e.target.value; });
    $$('[data-cadre]', racine).forEach((b) => b.addEventListener('click', () => { $('#certif', racine).style.borderColor = b.dataset.cadre; $$('[data-cadre]', racine).forEach((x) => x.classList.toggle('ring-2', x === b)); $$('[data-cadre]', racine).forEach((x) => x.classList.toggle('ring-brand', x === b)); }));
    bascules(racine);
  };

  /* ======================= Correction (admin et mentor) ======================= */

  VF.ecrans['correction'] = (racine, { mentor = false } = {}) => {
    let i = 0;
    const criteres = [['Méthode de recherche', 6], ['Fiabilité des sources citées', 6], ['Conclusion argumentée', 5], ['Clarté de la rédaction', 3]];
    const rendre = () => {
      const c = VF.copies[i];
      racine.innerHTML = `
        ${enTete({ titre: mentor ? 'Devoirs à corriger' : 'Correction des examens', sousTitre: `${VF.copies.length} copies en attente. Le barème est commun à tous les correcteurs.`, fil: [mentor ? 'Espace pédagogique' : 'Académie'] })}
        <div class="grid xl:grid-cols-[300px_1fr_320px] gap-4">
          <section class="carte">
            <div class="carte-tete"><h2 class="carte-titre">File de correction</h2></div>
            <ul class="pb-2">${VF.copies.map((x, k) => `<li><button class="w-full text-left flex items-start gap-3 px-5 py-3 ${k === i ? 'bg-brand-50 dark:bg-brand/10' : 'hover:bg-papier dark:hover:bg-nuit'}" data-copie="${k}">
              <span class="avatar w-8 h-8">${esc(x.initiales)}</span><span class="min-w-0 flex-1"><span class="block text-sm font-medium truncate">${esc(x.apprenant)}</span><span class="block text-xs texte-3 truncate">${esc(x.devoir)}</span><span class="block text-xs texte-3">${esc(x.soumis)}</span></span>
              ${x.urgence === 'urgent' ? pastille('p-critique', 'clock-alert', 'Urgent') : x.urgence === 'en_cours' ? pastille('p-info', 'pen-line', 'Entamée') : ''}</button></li>`).join('')}</ul>
          </section>
          <section class="carte p-5">
            <p class="text-xs texte-3">${esc(c.cours)} — soumis ${esc(c.soumis)}</p>
            <h2 class="mt-1 font-titre text-xl font-semibold">${esc(c.devoir)}</h2>
            <div class="mt-4 prose-sm text-sm leading-relaxed space-y-3 texte-2">
              <p>J'ai commencé par une recherche inversée de l'image sur TinEye et Google Images. La plus ancienne occurrence date du <strong class="text-encre dark:text-white">12 mars 2019</strong>, sur un site d'information nigérian, soit plus de sept ans avant le partage viral.</p>
              <p>Les panneaux visibles à gauche sont rédigés en anglais, ce qui contredit la localisation annoncée à Cotonou. La météo (saison sèche) ne correspond pas non plus à la date prétendue.</p>
              <p>Conclusion : l'image est <strong class="text-encre dark:text-white">authentique mais sortie de son contexte</strong>. Verdict proposé : Trompeur.</p>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-2">${[VF.medias[5], VF.medias[6]].map((m) => `<a href="${m.fichier}" class="glightbox block rounded-ctrl overflow-hidden border filet" data-gallery="copie"><img src="${m.fichier}" alt="Pièce jointe" class="h-32 w-full object-cover"></a>`).join('')}</div>
            <div class="mt-5"><p class="etiquette">Commentaire pour l'apprenant</p><div id="retour"></div></div>
          </section>
          <section class="carte p-5 xl:sticky xl:top-20 self-start">
            <h2 class="carte-titre">Barème</h2>
            <div class="mt-4 space-y-4" data-bareme>${criteres.map(([n, max], k) => `<div><div class="flex justify-between text-sm mb-1.5"><label for="crit-${k}">${n}</label><span class="tabular-nums texte-2"><output data-note="${k}">${Math.round(max * 0.75)}</output> / ${max}</span></div><input id="crit-${k}" type="range" min="0" max="${max}" step="0.5" value="${Math.round(max * 0.75)}" class="w-full accent-[#09B850]" data-critere="${k}"></div>`).join('')}</div>
            <div class="mt-5 pt-4 border-t filet flex items-end justify-between"><span class="text-sm texte-2">Note finale</span><span class="text-3xl font-semibold" data-total></span></div>
            <button class="btn-primaire w-full mt-4" data-rendre>${icone('send')}Rendre la copie</button>
            <button class="btn-fantome w-full mt-2" data-suivante>Passer à la suivante</button>
          </section>
        </div>`;
      const q = VF.quill($('#retour', racine), 'Ce qui est réussi, ce qui manque, et comment progresser.');
      const total = () => { const s = $$('[data-critere]', racine).reduce((a, r) => a + +r.value, 0); $('[data-total]', racine).textContent = String(s).replace('.', ',') + ' / 20'; };
      $$('[data-critere]', racine).forEach((r) => r.addEventListener('input', () => { $(`[data-note="${r.dataset.critere}"]`, racine).textContent = String(r.value).replace('.', ','); total(); }));
      total();
      $$('[data-copie]', racine).forEach((b) => b.addEventListener('click', () => { i = +b.dataset.copie; rendre(); }));
      $('[data-suivante]', racine).addEventListener('click', () => { i = (i + 1) % VF.copies.length; rendre(); });
      $('[data-rendre]', racine).addEventListener('click', () => { if (q.getText().trim().length < 10) { toast('Ajoutez un commentaire pour l\'apprenant avant de rendre la copie.', 'critique'); q.focus(); return; } toast(`Copie de ${c.apprenant} rendue : ${$('[data-total]', racine).textContent}.`); i = (i + 1) % VF.copies.length; rendre(); });
      const lb = GLightbox({ selector: '.glightbox' });
      VF.nettoyages.push(() => lb.destroy());
      icones();
    };
    rendre();
  };
})(window.VF);
