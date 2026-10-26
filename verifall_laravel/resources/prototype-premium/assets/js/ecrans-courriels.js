/*
 | Modèles de courriels : liste, création, modification, duplication,
 | suppression — et l'éditeur avec son aide-mémoire des variables.
 |
 | Un modèle déclare les variables qu'il accepte (`variables`, stockées en
 | JSON, ou saisies en texte, une par ligne). L'éditeur refuse d'enregistrer
 | un objet ou un corps qui utilise une variable non déclarée : c'est la même
 | règle que devra appliquer le service Laravel avant l'envoi.
 */
(function (VF) {
  'use strict';
  const { $, $$, esc, fmt, icone, icones, pastille, enTete, carte, toast, tiroir, modale, confirmer, fermerCouche, champ, valider } = VF.ui;
  VF.ecrans = VF.ecrans || {};

  /* ---------- Catalogue des variables dynamiques ---------- */
  const CATALOGUE = [
    ['Destinataire', [['user_name', 'Nom complet du destinataire', 'Kossi Tossou'], ['user_first_name', 'Prénom', 'Kossi'], ['user_email', 'Adresse du destinataire', 'kossi.tossou@mail.bj'], ['user_city', 'Ville du profil', 'Cotonou']]],
    ['Liens', [['reset_link', 'Lien de réinitialisation du mot de passe (valable 60 min)', 'https://verifall.com/reinitialiser/9f2c…'], ['verify_link', 'Lien de confirmation de l\'adresse', 'https://verifall.com/confirmer/71ab…'], ['login_link', 'Page de connexion', 'https://verifall.com/connexion'], ['unsubscribe_link', 'Désinscription en un clic', 'https://verifall.com/desinscription/…'], ['app_url', 'Adresse du site', 'https://verifall.com']]],
    ['Sécurité', [['otp_code', 'Code à usage unique (6 chiffres)', '482 913'], ['otp_expires_minutes', 'Durée de validité du code, en minutes', '10'], ['ip_address', 'Adresse IP de la connexion', '41.85.12.204'], ['device', 'Appareil de la connexion', 'Chrome sur Windows'], ['login_time', 'Date et heure de la connexion', '15 oct. 2026 à 09:42']]],
    ['Vérification', [['verification_id', 'Numéro de la vérification', 'VER-90410'], ['verification_verdict', 'Verdict rendu', 'Faux'], ['report_link', 'Lien vers le rapport PDF', 'https://verifall.com/rapports/RAP-90410'], ['factcheck_title', 'Titre du fact-check publié', 'Non, le pont de Cadjèhoun ne fermera pas six mois'], ['factcheck_link', 'Lien du fact-check', 'https://verifall.com/fact-checks/pont-cadjehoun']]],
    ['Académie', [['course_title', 'Intitulé du cours', 'OSINT Fondamentaux'], ['lesson_title', 'Intitulé de la leçon', 'Whois et archives'], ['mentor_name', 'Nom du mentor', 'Fifamè Houngbédji'], ['certificate_code', 'Code public du certificat', 'VF-482913'], ['certificate_link', 'Lien de téléchargement du certificat', 'https://verifall.com/certificats/VF-482913'], ['days_inactive', 'Jours sans activité', '14']]],
    ['Paiements', [['amount', 'Montant payé', '1 000'], ['currency', 'Devise', 'FCFA'], ['units_credited', 'Unités créditées', '120'], ['transaction_id', 'Référence de la transaction', 'MM-2026-104882'], ['operator', 'Opérateur Mobile Money', 'MTN']]],
    ['Plateforme', [['platform_name', 'Nom de la plateforme', 'Vérif\'All'], ['support_email', 'Adresse du support', 'support@verifall.com'], ['current_year', 'Année en cours', '2026']]],
  ];
  const PAR_CLE = {};
  CATALOGUE.forEach(([groupe, vars]) => vars.forEach(([cle, description, exemple]) => { PAR_CLE[cle] = { cle, description, exemple, groupe }; }));
  const vars = (...cles) => cles.map((cle) => ({ cle, description: PAR_CLE[cle].description, requise: false }));
  const COMMUNES = ['user_name', 'platform_name', 'support_email', 'current_year'];

  const CATEGORIES = ['Compte', 'Sécurité', 'Fact-checking', 'Académie', 'Paiements', 'Communauté'];
  const ETATS = { actif: { classe: 'p-bon', ic: 'circle-check', libelle: 'Actif' }, brouillon: { classe: 'p-neutre', ic: 'file-pen-line', libelle: 'Brouillon' }, inactif: { classe: 'p-alerte', ic: 'circle-pause', libelle: 'Désactivé' } };

  const { entre } = VF.outils;
  let compteur = 0;
  const nouvelId = () => 'tpl_' + Date.now().toString(36) + (compteur++);
  const corps = (...paragraphes) => paragraphes.map((p) => `<p>${p}</p>`).join('');

  /* Les modèles vivent en mémoire pour la session : une modification se retrouve dans la liste. */
  VF.modelesCourriels = VF.modelesCourriels || [
    { cle: 'auth.code_inscription', nom: 'Code d\'inscription', categorie: 'Compte', declencheur: 'Inscription citoyenne', objet: 'Votre code {{platform_name}} : {{otp_code}}', preheader: 'Il expire dans {{otp_expires_minutes}} minutes.', corps: corps('Bonjour {{user_first_name}},', 'Voici votre code pour terminer l\'inscription : <strong>{{otp_code}}</strong>.', 'Il est valable {{otp_expires_minutes}} minutes. Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.'), variables: vars('user_first_name', 'otp_code', 'otp_expires_minutes', 'platform_name').map((v) => ({ ...v, requise: v.cle === 'otp_code' })), systeme: true },
    { cle: 'auth.reinitialisation', nom: 'Réinitialisation du mot de passe', categorie: 'Sécurité', declencheur: 'Mot de passe oublié', objet: 'Réinitialisez votre mot de passe', preheader: 'Le lien est valable 60 minutes.', corps: corps('Bonjour {{user_name}},', 'Pour choisir un nouveau mot de passe, ouvrez ce lien : <a href="{{reset_link}}">réinitialiser mon mot de passe</a>.', 'Demande reçue depuis {{device}} ({{ip_address}}). Si ce n\'est pas vous, écrivez à {{support_email}}.'), variables: vars('user_name', 'reset_link', 'device', 'ip_address', 'support_email').map((v) => ({ ...v, requise: v.cle === 'reset_link' })), systeme: true },
    { cle: 'securite.nouvelle_connexion', nom: 'Nouvelle connexion détectée', categorie: 'Sécurité', declencheur: 'Connexion depuis un nouvel appareil', objet: 'Nouvelle connexion à votre compte', preheader: '{{device}}, {{login_time}}', corps: corps('Bonjour {{user_first_name}},', 'Une connexion a eu lieu le {{login_time}} depuis {{device}} ({{ip_address}}).', 'Si ce n\'est pas vous, changez votre mot de passe : {{reset_link}}'), variables: vars('user_first_name', 'login_time', 'device', 'ip_address', 'reset_link'), systeme: true },
    { cle: 'factcheck.publie', nom: 'Fact-check publié', categorie: 'Fact-checking', declencheur: 'Abonnement à un thème', objet: 'Nouveau fact-check : {{factcheck_title}}', preheader: 'Le verdict de notre équipe.', corps: corps('Bonjour {{user_first_name}},', 'Nous venons de publier : <a href="{{factcheck_link}}">{{factcheck_title}}</a>.', 'Vous recevez ce message car vous suivez ce thème. <a href="{{unsubscribe_link}}">Se désabonner</a>.'), variables: vars('user_first_name', 'factcheck_title', 'factcheck_link', 'unsubscribe_link').map((v) => ({ ...v, requise: v.cle === 'unsubscribe_link' })), systeme: false },
    { cle: 'verification.terminee', nom: 'Vérification terminée', categorie: 'Fact-checking', declencheur: 'Analyse terminée', objet: 'Votre vérification {{verification_id}} est prête', preheader: 'Verdict : {{verification_verdict}}', corps: corps('Bonjour {{user_first_name}},', 'L\'analyse de votre contenu est terminée. Verdict : <strong>{{verification_verdict}}</strong>.', 'Le rapport complet : {{report_link}}'), variables: vars('user_first_name', 'verification_id', 'verification_verdict', 'report_link'), systeme: true },
    { cle: 'academie.certificat', nom: 'Certificat obtenu', categorie: 'Académie', declencheur: 'Fin de cours', objet: 'Félicitations, votre certificat « {{course_title}} »', preheader: 'Code de vérification : {{certificate_code}}', corps: corps('Bravo {{user_first_name}} !', 'Vous avez terminé <strong>{{course_title}}</strong>. Votre certificat porte le code {{certificate_code}}.', 'Téléchargez-le ici : {{certificate_link}}'), variables: vars('user_first_name', 'course_title', 'certificate_code', 'certificate_link'), systeme: false },
    { cle: 'academie.relance', nom: 'Relance d\'un apprenant', categorie: 'Académie', declencheur: 'Inactivité de 14 jours', objet: '{{user_first_name}}, votre cours vous attend', preheader: 'Reprenez là où vous vous êtes arrêté.', corps: corps('Bonjour {{user_first_name}},', 'Cela fait {{days_inactive}} jours que vous n\'avez pas ouvert <strong>{{course_title}}</strong>. Votre prochaine leçon : {{lesson_title}}.', '{{mentor_name}} reste disponible pour vos questions.'), variables: vars('user_first_name', 'days_inactive', 'course_title', 'lesson_title', 'mentor_name'), systeme: false },
    { cle: 'paiement.confirme', nom: 'Confirmation de paiement', categorie: 'Paiements', declencheur: 'Paiement Mobile Money', objet: 'Paiement reçu : {{units_credited}} unités créditées', preheader: 'Référence {{transaction_id}}', corps: corps('Bonjour {{user_name}},', 'Nous avons reçu {{amount}} {{currency}} par {{operator}}. {{units_credited}} unités ont été créditées sur votre compte.', 'Référence : {{transaction_id}}.'), variables: vars('user_name', 'amount', 'currency', 'operator', 'units_credited', 'transaction_id'), systeme: true },
    { cle: 'communaute.bienvenue_forum', nom: 'Bienvenue au forum', categorie: 'Communauté', declencheur: 'Première publication au forum', objet: 'Bienvenue dans la communauté {{platform_name}}', preheader: '', corps: corps('Bonjour {{user_first_name}},', 'Merci pour votre première contribution. Les règles de la communauté tiennent en trois lignes : citer ses sources, rester courtois, signaler plutôt que répondre à une provocation.'), variables: vars('user_first_name', 'platform_name'), systeme: false, statut: 'brouillon' },
  ].map((m, i) => ({ id: 'tpl_' + (101 + i), langue: 'Français', statut: m.statut || (i === 6 ? 'inactif' : 'actif'), envois: m.statut === 'brouillon' ? 0 : entre(30, 9000), ouverture: entre(34, 71), maj: VF.outils.jours(entre(2, 80)), auteur: 'Mawuli Adjovi', version: entre(1, 6), ...m }));

  const trouver = (id) => VF.modelesCourriels.find((m) => m.id === id);
  const variablesUtilisees = (...textes) => [...new Set(textes.join(' ').match(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g) || [])].map((v) => v.replace(/[{}\s]/g, ''));
  const remplacer = (texte, permises) => texte.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (tout, cle) => (permises.includes(cle) && PAR_CLE[cle] ? esc(PAR_CLE[cle].exemple) : `<mark class="bg-red-100 text-red-800 px-0.5 rounded">${esc(tout)}</mark>`));

  const copier = (texte) => {
    const repli = () => { const t = document.createElement('textarea'); t.value = texte; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0'; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (e) { /* copie impossible */ } t.remove(); };
    (navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(texte).catch(repli) : Promise.resolve(repli())).then(() => toast(`${texte} copié.`, 'info'));
  };

  /** L'aperçu rendu avec les valeurs d'exemple ; une variable non autorisée est surlignée. */
  const apercu = (m) => {
    const permises = m.variables.map((v) => v.cle);
    modale({
      titre: `Aperçu — ${m.nom}`, large: true,
      corps: `
        <div class="segments mb-3" role="group" aria-label="Largeur"><button class="segment" aria-pressed="true" data-largeur="600">${icone('monitor', 'w-4 h-4')}Ordinateur</button><button class="segment" aria-pressed="false" data-largeur="360">${icone('smartphone', 'w-4 h-4')}Téléphone</button></div>
        <div class="rounded-ctrl border filet bg-papier dark:bg-nuit p-3 overflow-auto max-h-[60vh]">
          <div data-boite class="mx-auto bg-white text-[#142019] rounded-ctrl shadow-sm transition-all" style="max-width:600px">
            <div class="px-5 py-3 border-b border-[#E3E8E4] text-[13px]"><p><span class="text-[#7A8981]">De :</span> Vérif'All &lt;no-reply@verifall.com&gt;</p><p><span class="text-[#7A8981]">Objet :</span> <strong>${remplacer(esc(m.objet), permises)}</strong></p>${m.preheader ? `<p class="text-[#7A8981] truncate">${remplacer(esc(m.preheader), permises)}</p>` : ''}</div>
            <div class="px-5 py-3 bg-[#0E2A1E] text-white font-semibold">Vérif'All</div>
            <div class="px-5 py-5 text-[14px] leading-relaxed space-y-3 [&_a]:text-[#067a36] [&_a]:underline">${remplacer(m.corps, permises)}</div>
            <div class="px-5 py-4 border-t border-[#E3E8E4] text-[12px] text-[#7A8981]">Vérif'All — la boussole contre la désinformation numérique. Cotonou, Bénin.</div>
          </div>
        </div>
        <p class="aide">Les variables sont remplacées par des valeurs d'exemple. Une variable surlignée en rouge n'est pas autorisée pour ce modèle : l'envoi serait refusé.</p>`,
      actions: `<button class="btn-second" data-fermer>Fermer</button><button class="btn-primaire" data-test>${icone('flask-conical')}Envoyer un test</button>`,
      apres: (c) => {
        $$('[data-largeur]', c).forEach((b) => b.addEventListener('click', () => { $('[data-boite]', c).style.maxWidth = b.dataset.largeur + 'px'; $$('[data-largeur]', c).forEach((x) => x.setAttribute('aria-pressed', String(x === b))); }));
        $('[data-test]', c).addEventListener('click', () => { fermerCouche('modale'); envoyerTest(m); });
      },
    });
  };

  const envoyerTest = (m) => modale({
    titre: 'Envoyer un test',
    corps: `<form id="f-test" class="space-y-3" novalidate>${champ({ nom: 'test_email', libelle: 'Adresse de test', type: 'email', valeur: VF.moi.admin.email, regles: 'requis|email', aide: 'Le test utilise les valeurs d\'exemple et porte la mention [TEST] dans l\'objet.' })}</form>`,
    actions: `<button class="btn-second" data-fermer>Annuler</button><button class="btn-primaire" type="submit" form="f-test">${icone('send')}Envoyer</button>`,
    apres: (c) => $('#f-test', c).addEventListener('submit', (e) => { e.preventDefault(); if (!valider(e.target)) return; fermerCouche('modale'); toast(`Test de « ${m.nom} » envoyé à ${new FormData(e.target).get('test_email')}.`); }),
  });

  const dupliquer = (m) => {
    const copie = { ...m, id: nouvelId(), cle: m.cle + '_copie', nom: m.nom + ' (copie)', statut: 'brouillon', systeme: false, envois: 0, version: 1, maj: new Date(), variables: m.variables.map((v) => ({ ...v })) };
    VF.modelesCourriels.unshift(copie);
    return copie;
  };

  const supprimer = (m, apres) => {
    if (m.systeme) return modale({ titre: 'Ce modèle ne peut pas être supprimé', corps: `<p class="texte-2">« ${esc(m.nom)} » est un modèle système : l'application l'envoie elle-même (${esc(m.declencheur.toLowerCase())}). Sans lui, ce courriel ne partirait plus.</p><p class="texte-2 mt-2">Vous pouvez en modifier le contenu, ou le désactiver pour revenir au texte par défaut.</p>`, actions: '<button class="btn-primaire" data-fermer>Compris</button>' });
    confirmer({ titre: 'Supprimer ce modèle ?', texte: `« ${m.nom} » (${m.cle}) sera supprimé définitivement. L'action est journalisée.`, siOui: () => { VF.modelesCourriels = VF.modelesCourriels.filter((x) => x !== m); toast('Modèle supprimé. L\'action figure dans le journal d\'audit.'); apres && apres(); } });
  };

  /** L'aide-mémoire : la liste des variables, à copier ou insérer. */
  const aideMemoire = ({ permises = null, utilisees = [], inserable = false, verrouille = false }) => `
    <div class="relative mb-3"><span class="absolute left-3 top-1/2 -translate-y-1/2 texte-3">${icone('search', 'w-4 h-4')}</span><input data-am-q class="champ !h-9 pl-9" placeholder="Filtrer les variables…" aria-label="Filtrer les variables"></div>
    ${CATALOGUE.map(([groupe, liste]) => `<div data-am-groupe class="mb-4"><p class="text-[11px] font-semibold uppercase tracking-wide texte-3 mb-1.5">${esc(groupe)}</p><ul class="space-y-1">${liste.map(([cle, description, exemple]) => {
      const permise = !permises || permises.includes(cle);
      const utilisee = utilisees.includes(cle);
      return `<li data-am-var="${cle}" data-am-texte="${esc((cle + ' ' + description).toLowerCase())}" class="group rounded-ctrl border filet px-2.5 py-2 ${permise ? '' : 'opacity-60'}">
        <div class="flex items-center gap-2">
          <button type="button" class="font-mono text-[12.5px] text-brand-700 dark:text-brand hover:underline truncate" data-am-copier="${cle}" title="Copier">{{${cle}}}</button>
          ${utilisee ? `<span class="text-green-700 dark:text-bon" title="Utilisée dans ce modèle">${icone('check', 'w-3.5 h-3.5')}</span>` : ''}
          <span class="ml-auto flex items-center gap-0.5">
            ${inserable && permise ? `<button type="button" class="btn-icone !w-7 !h-7" data-am-inserer="${cle}" aria-label="Insérer {{${cle}}}" title="Insérer à l'emplacement du curseur">${icone('text-cursor-input', '!w-3.5 !h-3.5')}</button>` : ''}
            ${inserable && !permise && !verrouille ? `<button type="button" class="btn-icone !w-7 !h-7" data-am-autoriser="${cle}" aria-label="Autoriser {{${cle}}} pour ce modèle" title="Ajouter aux variables autorisées">${icone('plus', '!w-3.5 !h-3.5')}</button>` : ''}
            <button type="button" class="btn-icone !w-7 !h-7" data-am-copier="${cle}" aria-label="Copier {{${cle}}}" title="Copier">${icone('copy', '!w-3.5 !h-3.5')}</button>
          </span>
        </div>
        <p class="text-xs texte-2 mt-0.5">${esc(description)}</p>
        <p class="text-[11px] texte-3 mt-0.5 truncate">Exemple : ${esc(exemple)}</p>
      </li>`;
    }).join('')}</ul></div>`).join('')}`;

  const brancherAideMemoire = (c, { surInserer, surAutoriser } = {}) => {
    const q = $('[data-am-q]', c);
    if (q && !q.dataset.lie) {
      q.dataset.lie = '1';
      q.addEventListener('input', () => {
        const t = q.value.toLowerCase().trim();
        $$('[data-am-var]', c).forEach((li) => { li.hidden = !!t && !li.dataset.amTexte.includes(t); });
        $$('[data-am-groupe]', c).forEach((g) => { g.hidden = !$$('[data-am-var]', g).some((li) => !li.hidden); });
      });
    }
    if (c.dataset.amLie) return;
    c.dataset.amLie = '1';
    c.addEventListener('click', (e) => {
      const b = e.target.closest('[data-am-copier], [data-am-inserer], [data-am-autoriser]');
      if (!b) return;
      if (b.dataset.amCopier) copier(`{{${b.dataset.amCopier}}}`);
      else if (b.dataset.amInserer) surInserer && surInserer(b.dataset.amInserer);
      else if (b.dataset.amAutoriser) surAutoriser && surAutoriser(b.dataset.amAutoriser);
    });
  };

  /* ======================= Liste ======================= */

  const definition = (rendreDeNouveau) => {
    const modeles = VF.modelesCourriels;
    return {
      id: 'emails-modeles', titre: 'Modèles de courriels', sousTitre: 'Les courriels envoyés par la plateforme, leur contenu et les variables qu\'ils acceptent.', fil: ['Système'], entite: 'modèle',
      actionsEnTete: `<button class="btn-second" data-catalogue>${icone('braces')}Variables disponibles</button><a href="#/email-modele" class="btn-primaire">${icone('plus')}Nouveau modèle</a>`,
      kpis: [
        { libelle: 'Modèles', valeur: String(modeles.length), aide: `${modeles.filter((m) => m.systeme).length} modèles système`, ic: 'mails' },
        { libelle: 'Actifs', valeur: String(modeles.filter((m) => m.statut === 'actif').length), ic: 'circle-check' },
        { libelle: 'Envois (30 j)', valeur: fmt.nombre(modeles.reduce((s, m) => s + m.envois, 0)), delta: 8, ic: 'send' },
        { libelle: 'Taux d\'ouverture', valeur: Math.round(modeles.reduce((s, m) => s + m.ouverture, 0) / Math.max(1, modeles.length)) + ' %', delta: 2, ic: 'mail-open' },
      ],
      colonnes: [
        { cle: 'nom', libelle: 'Modèle', type: 'titre', sous: 'cle' },
        { cle: 'categorie', libelle: 'Catégorie', muet: true },
        { cle: 'declencheur', libelle: 'Déclencheur', muet: true },
        { cle: 'nbVariables', libelle: 'Variables', type: 'nombre' },
        { cle: 'statut', libelle: 'État', type: 'pastille', etats: ETATS },
        { cle: 'envois', libelle: 'Envois (30 j)', type: 'nombre' },
        { cle: 'maj', libelle: 'Modifié le', type: 'date' },
      ],
      filtres: [{ cle: 'categorie', libelle: 'Catégorie', options: CATEGORIES }, { cle: 'statut', libelle: 'État', options: Object.entries(ETATS).map(([valeur, e]) => ({ valeur, libelle: e.libelle })) }],
      placeRecherche: 'Rechercher un modèle, une clé…',
      lignes: modeles.map((m) => ({ id: m.id, nom: m.nom + (m.systeme ? ' · système' : ''), cle: m.cle, categorie: m.categorie, declencheur: m.declencheur, nbVariables: m.variables.length, statut: m.statut, envois: m.envois, maj: m.maj })),
      supprimable: false,
      actionsLigne: (l) => {
        const m = trouver(l.id);
        return [
          { libelle: 'Modifier', ic: 'pencil', action: () => (location.hash = '#/email-modele?id=' + m.id) },
          { libelle: 'Aperçu', ic: 'eye', action: () => apercu(m) },
          { libelle: 'Envoyer un test', ic: 'flask-conical', action: () => envoyerTest(m) },
          { libelle: 'Dupliquer', ic: 'copy', action: () => { const c = dupliquer(m); toast(`Copie créée en brouillon : ${c.nom}.`); rendreDeNouveau(); } },
          { libelle: m.statut === 'actif' ? 'Désactiver' : 'Activer', ic: m.statut === 'actif' ? 'circle-pause' : 'circle-check', action: () => { m.statut = m.statut === 'actif' ? 'inactif' : 'actif'; m.maj = new Date(); toast(m.statut === 'actif' ? 'Modèle activé.' : 'Modèle désactivé : le texte par défaut sera envoyé.', m.statut === 'actif' ? 'bon' : 'alerte'); rendreDeNouveau(); } },
          '-',
          { libelle: 'Supprimer', ic: 'trash-2', danger: true, action: () => supprimer(m, rendreDeNouveau) },
        ];
      },
      actionsGroupees: [{ libelle: 'Activer', ic: 'circle-check' }, { libelle: 'Désactiver', ic: 'circle-pause' }],
      monte: (r) => $('[data-catalogue]', r).addEventListener('click', () => tiroir({
        titre: 'Variables disponibles', sousTitre: 'Copiez une variable et collez-la dans l\'objet ou le corps d\'un modèle.',
        corps: aideMemoire({}),
        apres: (c) => brancherAideMemoire(c),
      })),
    };
  };

  VF.ecrans['emails-modeles'] = (racine) => {
    const rendre = () => { const zone = document.createElement('div'); racine.replaceChildren(zone); VF.gestion.ecran(zone, definition(rendre)); };
    rendre();
  };

  /* ======================= Éditeur (création et modification) ======================= */

  /* Format texte : une variable par ligne, « cle — description », une étoile pour une variable obligatoire. */
  const enTexte = (variables) => variables.map((v) => `${v.cle}${v.requise ? '*' : ''}${v.description ? ' — ' + v.description : ''}`).join('\n');
  const depuisTexte = (texte) => texte.split('\n').map((l) => l.trim()).filter(Boolean).map((l) => {
    const [, tete, description = ''] = l.match(/^(\S+?)\s*(?:(?:[—–]|\s-\s)\s*(.*))?$/) || [, l];
    const requise = tete.endsWith('*');
    const cle = tete.replace(/\*$/, '');
    return { cle, description: description.trim(), requise };
  });
  /** Lit la déclaration des variables. Renvoie la liste, ou l'erreur à afficher sous le champ. */
  const lireVariables = (texte, format) => {
    let liste;
    if (format === 'json') {
      try { liste = JSON.parse(texte || '[]'); } catch (e) { return { erreur: `JSON invalide : ${e.message.replace(/^JSON\.parse: /, '')}` }; }
      if (!Array.isArray(liste)) return { erreur: 'Le JSON doit être un tableau : [ { "cle": "user_name", … } ].' };
      liste = liste.map((v) => (typeof v === 'string' ? { cle: v, requise: false } : v));
    } else liste = depuisTexte(texte);
    for (const v of liste) {
      if (!v || typeof v.cle !== 'string' || !/^[a-z][a-z0-9_]*$/.test(v.cle)) return { erreur: `Clé « ${v && v.cle} » invalide : minuscules, chiffres et _ uniquement, en commençant par une lettre.` };
    }
    const doublon = liste.find((v, i) => liste.findIndex((x) => x.cle === v.cle) !== i);
    if (doublon) return { erreur: `La variable « ${doublon.cle} » est déclarée deux fois.` };
    return { liste: liste.map((v) => ({ cle: v.cle, description: v.description || (PAR_CLE[v.cle] ? PAR_CLE[v.cle].description : ''), requise: !!v.requise })) };
  };

  VF.ecrans['email-modele'] = (racine, { id = '' } = {}) => {
    const existant = id ? trouver(id) : null;
    if (id && !existant) { racine.innerHTML = VF.ui.vide({ ic: 'mail-x', titre: 'Modèle introuvable', texte: 'Il a peut-être été supprimé.', action: '<a href="#/emails-modeles" class="btn-second">Retour à la liste</a>' }); return; }
    const m = existant ? { ...existant, variables: existant.variables.map((v) => ({ ...v })) } : { id: '', cle: '', nom: '', categorie: 'Compte', declencheur: '', langue: 'Français', statut: 'brouillon', objet: '', preheader: '', corps: '', variables: vars(...COMMUNES), systeme: false, version: 0 };
    const systeme = !!(existant && existant.systeme);
    let format = 'json';
    let dernier = { champ: 'corps', index: 0 };

    racine.innerHTML = `
      ${enTete({ titre: existant ? `Modifier « ${existant.nom} »` : 'Nouveau modèle de courriel', fil: ['Système', 'Modèles de courriels'], sousTitre: existant ? `Version ${existant.version} — modifiée le ${fmt.date(existant.maj)} par ${existant.auteur}` : 'Les champs marqués d\'un astérisque sont obligatoires.', actions: `<a href="#/emails-modeles" class="btn-second">Annuler</a><button class="btn-second" data-apercu>${icone('eye')}Aperçu</button><button class="btn-second" data-test>${icone('flask-conical')}Test</button><button class="btn-primaire" type="submit" form="f-modele">${icone('check')}${existant ? 'Enregistrer' : 'Créer le modèle'}</button>` })}
      <div class="grid xl:grid-cols-[1fr_360px] gap-4 items-start">
        <form id="f-modele" class="space-y-4 min-w-0" novalidate>
          ${carte({ titre: 'Identification', corps: `<div class="grid sm:grid-cols-2 gap-4">
            ${champ({ nom: 'nom', libelle: 'Nom du modèle', valeur: m.nom, regles: 'requis|min:4', place: 'Ex. Certificat obtenu' })}
            ${champ({ nom: 'cle', libelle: 'Clé technique', valeur: m.cle, regles: 'requis', place: 'academie.certificat', aide: existant && existant.systeme ? 'Modèle système : la clé est appelée par le code et ne change pas.' : 'Minuscules, chiffres, points et _. Le code appelle le modèle par cette clé.', attrs: existant && existant.systeme ? 'readonly' : '' })}
            ${champ({ nom: 'categorie', libelle: 'Catégorie', valeur: m.categorie, options: CATEGORIES })}
            ${champ({ nom: 'declencheur', libelle: 'Déclencheur', valeur: m.declencheur, regles: 'requis', place: 'Ex. Fin de cours' })}
            ${champ({ nom: 'langue', libelle: 'Langue', valeur: m.langue, options: ['Français', 'English'] })}
            <div><p class="etiquette">État</p><div class="segments" role="group" aria-label="État">${Object.entries(ETATS).map(([k, e]) => `<button type="button" class="segment" data-statut="${k}" aria-pressed="${m.statut === k}">${esc(e.libelle)}</button>`).join('')}</div><p class="aide">Désactivé : la plateforme envoie le texte par défaut.</p></div>
          </div>` })}
          ${carte({ titre: 'Contenu', aide: 'Cliquez une variable de l\'aide-mémoire pour l\'insérer à l\'emplacement du curseur.', corps: `<div class="space-y-4">
            ${champ({ nom: 'objet', libelle: 'Objet', valeur: m.objet, regles: 'requis|min:6', aide: '120 caractères au plus. Les variables y sont permises.', attrs: 'maxlength="120" data-variables' })}
            ${champ({ nom: 'preheader', libelle: 'Pré-en-tête', valeur: m.preheader, aide: 'Le texte gris affiché après l\'objet dans la boîte de réception. 140 caractères au plus.', attrs: 'maxlength="140" data-variables' })}
            <div><p class="etiquette">Corps du message <span class="text-critique" aria-hidden="true">*</span></p><div id="cm-quill"></div><p class="aide" data-aide-corps></p></div>
          </div>` })}
          ${carte({ titre: 'Variables autorisées', aide: 'Celles que ce modèle accepte. Stockées en JSON avec le modèle ; une étoile marque une variable obligatoire.', actions: `<div class="segments" role="group" aria-label="Format"><button type="button" class="segment" data-format="json" aria-pressed="true">JSON</button><button type="button" class="segment" data-format="texte" aria-pressed="false">Texte</button></div>`, corps: `
            <label for="c-variables" class="sr-only">Variables autorisées</label>
            <textarea id="c-variables" rows="8" class="champ font-mono !text-[12.5px] leading-relaxed ${systeme ? 'opacity-80' : ''}" spellcheck="false" ${systeme ? 'readonly' : ''}></textarea>
            ${systeme ? `<p class="aide flex items-start gap-1.5">${icone('lock', 'w-3.5 h-3.5 mt-px shrink-0')}Modèle système : ces variables sont celles que le code fournit à l'envoi (catalogue des courriels). Elles ne se modifient pas ici, sinon le courriel partirait sans son code ou son lien.</p>` : ''}
            <p class="aide" data-aide-variables></p>` })}
          ${carte({ titre: 'Contrôles avant enregistrement', corps: '<ul class="space-y-2 text-sm" data-controles></ul>' })}
        </form>
        <aside class="xl:sticky xl:top-20 space-y-4">
          <section class="carte">
            <div class="carte-tete"><div><h2 class="carte-titre">Aide-mémoire des variables</h2><p class="text-xs texte-3 mt-0.5">Cliquez pour copier ; ${icone('text-cursor-input', 'inline w-3.5 h-3.5')} insère, ${icone('plus', 'inline w-3.5 h-3.5')} autorise.</p></div></div>
            <div class="carte-corps max-h-[calc(100vh-11rem)] overflow-y-auto defile" data-aide-memoire></div>
          </section>
          ${existant ? carte({ titre: 'Historique', corps: `<ul class="space-y-2.5 text-sm">${Array.from({ length: Math.min(existant.version, 4) }, (_, i) => `<li class="flex items-center justify-between gap-2"><span><span class="font-medium">Version ${existant.version - i}</span><span class="block text-xs texte-3">${fmt.date(VF.outils.jours(i * 19 + 2))} — ${i % 2 ? 'Rachida Sanni' : existant.auteur}</span></span>${i ? `<button type="button" class="btn-fantome btn-sm" data-restaurer="${existant.version - i}">Restaurer</button>` : pastille('p-info', 'clock', 'Actuelle')}</li>`).join('')}</ul>` }) : ''}
        </aside>
      </div>`;

    const form = $('#f-modele', racine);
    const q = VF.quill($('#cm-quill', racine), 'Bonjour {{user_first_name}}, …');
    if (m.corps) q.clipboard.dangerouslyPasteHTML(m.corps);
    q.on('selection-change', (r) => { if (r) dernier = { champ: 'corps', index: r.index }; });
    const zoneVars = $('#c-variables', racine);
    const ecrireVars = () => { zoneVars.value = format === 'json' ? JSON.stringify(m.variables, null, 2) : enTexte(m.variables); };
    ecrireVars();

    const champsTexte = () => ({ objet: $('#c-objet', racine).value, preheader: $('#c-preheader', racine).value, corps: q.root.innerHTML });

    /* Contrôles en direct : variables inconnues, obligatoires absentes, clé unique. */
    const controler = () => {
      const lu = lireVariables(zoneVars.value, format);
      const aideV = $('[data-aide-variables]', racine);
      zoneVars.classList.toggle('champ-erreur', !!lu.erreur);
      aideV.className = lu.erreur ? 'aide-erreur' : 'aide';
      aideV.innerHTML = lu.erreur ? icone('circle-alert', 'w-3.5 h-3.5') + esc(lu.erreur) : `${lu.liste.length} variables déclarées.`;
      if (lu.liste) m.variables = lu.liste;
      const t = champsTexte();
      const permises = m.variables.map((v) => v.cle);
      const utilisees = variablesUtilisees(t.objet, t.preheader, t.corps);
      const inconnues = utilisees.filter((v) => !permises.includes(v));
      const horsCatalogue = permises.filter((v) => !PAR_CLE[v]);
      const manquantes = m.variables.filter((v) => v.requise && !utilisees.includes(v.cle)).map((v) => v.cle);
      const cle = $('#c-cle', racine).value.trim();
      const cleOk = /^[a-z0-9_]+(\.[a-z0-9_]+)*$/.test(cle);
      const cleLibre = !VF.modelesCourriels.some((x) => x.cle === cle && x.id !== m.id);
      const corpsVide = !q.getText().trim();
      /* Un courriel de compte, de sécurité ou de paiement est transactionnel : la loi n'y exige pas de désinscription. */
      const transactionnel = ['Compte', 'Sécurité', 'Paiements'].includes(m.categorie) || !!m.systeme;
      const regles = [
        [!lu.erreur, 'La déclaration des variables est valide', lu.erreur],
        [inconnues.length === 0, 'Toutes les variables utilisées sont autorisées', `Non autorisées : ${inconnues.map((v) => `{{${v}}}`).join(', ')}`],
        [manquantes.length === 0, 'Les variables obligatoires sont présentes', `Obligatoires absentes : ${manquantes.map((v) => `{{${v}}}`).join(', ')}`],
        [horsCatalogue.length === 0, 'Chaque variable déclarée est fournie par la plateforme', `Inconnues du catalogue, elles resteraient vides : ${horsCatalogue.join(', ')}`, true],
        [cle && cleOk && cleLibre, 'La clé technique est valide et unique', !cle ? 'Clé technique manquante' : !cleOk ? 'Clé technique : minuscules, chiffres, points et _ uniquement' : 'Cette clé est déjà utilisée par un autre modèle'],
        [!corpsVide, 'Le corps du message est rédigé', 'Le corps du message est vide'],
        [transactionnel || utilisees.includes('unsubscribe_link'), transactionnel ? 'Courriel transactionnel : pas de lien de désinscription requis' : 'Lien de désinscription présent', 'Ajoutez {{unsubscribe_link}} : ce courriel n\'est pas transactionnel', true],
      ];
      $('[data-controles]', racine).innerHTML = regles.map(([ok, bien, mal, simpleAlerte]) => `<li class="flex items-start gap-2">${icone(ok ? 'circle-check' : simpleAlerte ? 'triangle-alert' : 'circle-x', `w-4 h-4 mt-0.5 shrink-0 ${ok ? 'text-green-700 dark:text-bon' : simpleAlerte ? 'text-amber-700 dark:text-amber-300' : 'text-red-700 dark:text-red-400'}`)}<span class="${ok ? '' : 'font-medium'}">${esc(ok ? bien : mal)}</span></li>`).join('');
      $('[data-aide-corps]', racine).textContent = `${utilisees.length} variables utilisées dans l'objet, le pré-en-tête et le corps.`;
      $('[data-aide-memoire]', racine).innerHTML = aideMemoire({ permises, utilisees, inserable: true, verrouille: systeme });
      const filtre = racine.querySelector('[data-am-q]');
      if (filtre && controler.filtre) { filtre.value = controler.filtre; filtre.dispatchEvent(new Event('input')); }
      brancherAideMemoire($('[data-aide-memoire]', racine), { surInserer: inserer, surAutoriser: autoriser });
      icones();
      return regles.every(([ok, , , simpleAlerte]) => ok || simpleAlerte);
    };

    const inserer = (cle) => {
      const jeton = `{{${cle}}}`;
      if (dernier.champ === 'corps') { q.insertText(dernier.index, jeton, 'user'); q.setSelection(dernier.index + jeton.length, 0); dernier.index += jeton.length; }
      else {
        const el = $('#c-' + dernier.champ, racine);
        const debut = el.selectionStart ?? el.value.length, fin = el.selectionEnd ?? el.value.length;
        el.value = el.value.slice(0, debut) + jeton + el.value.slice(fin);
        el.focus(); el.setSelectionRange(debut + jeton.length, debut + jeton.length);
      }
      controler();
    };
    const autoriser = (cle) => { m.variables.push({ cle, description: PAR_CLE[cle].description, requise: false }); ecrireVars(); controler(); toast(`{{${cle}}} est maintenant autorisée pour ce modèle.`, 'info'); };

    $$('[data-variables]', racine).forEach((el) => {
      const noter = () => { dernier = { champ: el.name }; };
      el.addEventListener('focus', noter); el.addEventListener('click', noter); el.addEventListener('keyup', noter);
      el.addEventListener('input', controler);
    });
    q.on('text-change', controler);
    zoneVars.addEventListener('input', controler);
    $('#c-cle', racine).addEventListener('input', controler);
    $('#c-categorie', racine).addEventListener('change', (e) => { m.categorie = e.target.value; controler(); });
    racine.addEventListener('input', (e) => { if (e.target.matches('[data-am-q]')) controler.filtre = e.target.value; });

    $$('[data-format]', racine).forEach((b) => b.addEventListener('click', () => {
      const lu = lireVariables(zoneVars.value, format);
      if (lu.erreur) { toast('Corrigez la déclaration avant de changer de format.', 'critique'); return; }
      m.variables = lu.liste; format = b.dataset.format; ecrireVars();
      $$('[data-format]', racine).forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      controler();
    }));
    $$('[data-statut]', racine).forEach((b) => b.addEventListener('click', () => { m.statut = b.dataset.statut; $$('[data-statut]', racine).forEach((x) => x.setAttribute('aria-pressed', String(x === b))); }));
    $$('[data-restaurer]', racine).forEach((b) => b.addEventListener('click', () => confirmer({ titre: `Restaurer la version ${b.dataset.restaurer} ?`, texte: 'Le contenu actuel sera conservé comme une version de l\'historique.', bouton: 'Restaurer', ton: 'primaire', siOui: () => toast(`Version ${b.dataset.restaurer} restaurée dans l'éditeur. Enregistrez pour la publier.`, 'info') })));

    const etatCourant = () => ({ ...m, ...champsTexte(), nom: $('#c-nom', racine).value.trim() || 'Sans titre' });
    $('[data-apercu]', racine).addEventListener('click', () => apercu(etatCourant()));
    $('[data-test]', racine).addEventListener('click', () => envoyerTest(etatCourant()));

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const champsOk = valider(form);
      const controlesOk = controler();
      if (!champsOk) return;
      if (!controlesOk) { toast('Le modèle ne peut pas être enregistré : voyez les contrôles en rouge.', 'critique'); $('[data-controles]', racine).scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
      const f = Object.fromEntries(new FormData(form));
      const valeurs = { nom: f.nom.trim(), cle: f.cle.trim(), categorie: f.categorie, declencheur: f.declencheur.trim(), langue: f.langue, objet: f.objet.trim(), preheader: f.preheader.trim(), corps: q.root.innerHTML, variables: m.variables, statut: m.statut, maj: new Date(), auteur: VF.moi.admin.nom };
      if (existant) { Object.assign(existant, valeurs, { version: existant.version + 1 }); toast(`« ${valeurs.nom} » enregistré (version ${existant.version}). L'action est journalisée.`); }
      else { VF.modelesCourriels.unshift({ id: nouvelId(), systeme: false, envois: 0, ouverture: 0, version: 1, ...valeurs }); toast(`Modèle « ${valeurs.nom} » créé.`); }
      location.hash = '#/emails-modeles';
    });

    controler();
  };
})(window.VF);
