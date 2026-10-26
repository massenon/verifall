/*
 | Page de connexion : le registre des verdicts, puis le parcours
 | identifiants → code de double authentification → console.
 |
 | Mêmes règles que le serveur : 5 essais manqués verrouillent 15 minutes
 | (LoginAttempt) ; le message d'échec ne dit jamais si l'adresse existe.
 */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const icones = () => window.lucide && window.lucide.createIcons({ attrs: { 'stroke-width': 1.75 } });
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const calme = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Le registre des verdicts ---------- */
  const VERDICTS = {
    faux: ['Faux', 'bg-critique text-white', 'circle-x'],
    trompeur: ['Trompeur', 'bg-alerte text-foret', 'triangle-alert'],
    vrai: ['Vrai', 'bg-bon text-white', 'circle-check'],
    invérifiable: ['Non vérifiable', 'bg-white/20 text-white', 'circle-help'],
  };
  const ENTREES = [
    ['Une coupure générale d\'électricité est prévue à Cotonou du 20 au 25 octobre', 'faux', 'il y a 12 min'],
    ['Le pont de Cadjèhoun sera fermé pendant six mois', 'faux', 'il y a 41 min'],
    ['Une photo d\'inondation à Porto-Novo daterait de cette semaine', 'trompeur', 'il y a 1 h'],
    ['Un recrutement de 5 000 enseignants serait ouvert sans concours', 'faux', 'il y a 2 h'],
    ['La campagne de vaccination contre le paludisme démarre dans l\'Atacora', 'vrai', 'il y a 3 h'],
    ['Les nouveaux billets de 10 000 FCFA contiendraient une puce de traçage', 'faux', 'il y a 4 h'],
    ['Le baccalauréat 2026 serait reporté au mois de décembre', 'invérifiable', 'il y a 5 h'],
    ['Le prix du carburant baisserait de 40 % dès lundi', 'trompeur', 'il y a 6 h'],
  ];
  const ligne = ([texte, v, quand], tamponne = true) => {
    const [libelle, classe, ic] = VERDICTS[v];
    return `<li class="registre-ligne flex items-start gap-4 rounded-ctrl border border-white/10 bg-white/[.04] px-4 py-3.5">
      <div class="min-w-0 flex-1"><p class="text-[15px] leading-snug text-white/90">« ${esc(texte)} »</p><p class="mt-1 text-xs text-white/45">${esc(quand)}</p></div>
      <span class="tampon shrink-0 inline-flex items-center gap-1 h-7 px-2.5 rounded-full text-xs font-semibold ${classe} ${tamponne ? '' : 'opacity-0 scale-150'}" >${`<i data-lucide="${ic}" class="w-3.5 h-3.5"></i>`}${libelle}</span>
    </li>`;
  };
  const registre = $('#registre');
  if (registre) {
    let i = 4;
    registre.innerHTML = ENTREES.slice(0, 4).map((e) => ligne(e)).join('');
    icones();
    /* Le seul mouvement de la page : une nouvelle affirmation entre en tête,
       puis son verdict se pose comme un tampon. */
    if (!calme) setInterval(() => {
      if (document.hidden) return;
      const e = ENTREES[i % ENTREES.length]; i += 1;
      registre.insertAdjacentHTML('afterbegin', ligne([e[0], e[1], 'à l\'instant'], false));
      const nouvelle = registre.firstElementChild;
      nouvelle.animate([{ opacity: 0, transform: 'translateY(-12px)' }, { opacity: 1, transform: 'none' }], { duration: 420, easing: 'cubic-bezier(.2,.7,.2,1)' });
      icones();
      setTimeout(() => {
        const t = nouvelle.querySelector('.tampon');
        t.classList.remove('opacity-0', 'scale-150');
        t.animate([{ opacity: 0, transform: 'scale(1.6) rotate(-8deg)' }, { opacity: 1, transform: 'scale(1) rotate(0)' }], { duration: 260, easing: 'cubic-bezier(.3,1.4,.5,1)' });
      }, 700);
      while (registre.children.length > 6) registre.lastElementChild.remove();
    }, 4200);
  }

  /* ---------- Thème ---------- */
  const majTheme = () => { const s = document.documentElement.classList.contains('dark'); $('#theme').innerHTML = `<i data-lucide="${s ? 'sun' : 'moon'}"></i>`; $('#theme').setAttribute('aria-label', s ? 'Passer en thème clair' : 'Passer en thème sombre'); icones(); };
  $('#theme').addEventListener('click', () => { const s = document.documentElement.classList.toggle('dark'); try { localStorage.setItem('vf-theme', s ? 'dark' : 'light'); } catch (e) { /* non mémorisé */ } majTheme(); });
  majTheme();

  /* ---------- Toasts ---------- */
  const toast = (msg) => {
    const el = document.createElement('div');
    el.className = 'carte shadow-flottant flex items-start gap-3 p-3.5 text-sm';
    el.setAttribute('role', 'status');
    el.innerHTML = `<i data-lucide="info" class="w-[18px] h-[18px] text-blue-700 dark:text-blue-300 mt-0.5"></i><p class="flex-1">${esc(msg)}</p>`;
    $('#toasts').appendChild(el); icones();
    setTimeout(() => el.remove(), 4200);
  };

  /* ---------- Étapes ---------- */
  const aller = (etape) => {
    $$('[data-etape]').forEach((s) => { s.hidden = s.dataset.etape !== etape; });
    const section = $(`[data-etape="${etape}"]`);
    const cible = $('input', section) || $('button:not([data-aller])', section) || $('button', section);
    if (cible) cible.focus();
    icones();
  };
  document.addEventListener('click', (e) => { const b = e.target.closest('[data-aller]'); if (b) aller(b.dataset.aller); });

  const bandeau = (ton, ic, html) => {
    const b = $('#bandeau');
    b.className = `mt-6 rounded-ctrl border px-3.5 py-3 text-sm flex items-start gap-2.5 ${ton === 'critique' ? 'bg-red-50 border-red-200 text-red-900 dark:bg-red-950/40 dark:border-red-900/60 dark:text-red-100' : 'bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/30 dark:border-blue-900/60 dark:text-blue-100'}`;
    b.innerHTML = `<i data-lucide="${ic}" class="w-4 h-4 mt-0.5 shrink-0"></i><p>${html}</p>`;
    icones();
  };
  const params = new URLSearchParams(location.search);
  if (params.has('sortie')) bandeau('info', 'log-out', 'Vous êtes déconnecté. À bientôt.');
  if (params.has('expiree')) bandeau('info', 'clock', 'Votre session a expiré. Reconnectez-vous pour continuer.');

  /* ---------- Identifiants ---------- */
  const email = $('#email'), mdp = $('#mdp');
  const erreur = (champ, aide, msg) => {
    champ.classList.toggle('champ-erreur', !!msg);
    champ.setAttribute('aria-invalid', String(!!msg));
    aide.className = msg ? 'aide-erreur' : 'aide';
    aide.innerHTML = msg ? `<i data-lucide="circle-alert" class="w-3.5 h-3.5"></i>${esc(msg)}` : '';
    icones();
  };
  $('#voir-mdp').addEventListener('click', (e) => {
    const b = e.currentTarget, visible = mdp.type === 'password';
    mdp.type = visible ? 'text' : 'password';
    b.setAttribute('aria-pressed', String(visible)); b.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    b.innerHTML = `<i data-lucide="${visible ? 'eye-off' : 'eye'}"></i>`; icones();
  });
  mdp.addEventListener('keyup', (e) => { const on = e.getModifierState && e.getModifierState('CapsLock'); $('#majuscules').classList.toggle('hidden', !on); $('#majuscules').classList.toggle('flex', on); });
  $$('[data-demo]').forEach((b) => b.addEventListener('click', () => { email.value = b.dataset.demo; mdp.value = 'demonstration'; email.focus(); }));

  let echecs = 0, verrouJusqua = 0;
  $('#f-connexion').addEventListener('submit', (e) => {
    e.preventDefault();
    const v = email.value.trim();
    let ok = true;
    if (!v) { erreur(email, $('[data-aide-email]'), 'Saisissez votre adresse électronique.'); ok = false; }
    else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) { erreur(email, $('[data-aide-email]'), 'Saisissez une adresse du type prenom.nom@verifall.com.'); ok = false; }
    else erreur(email, $('[data-aide-email]'), '');
    if (!mdp.value) { erreur(mdp, $('[data-aide-mdp]'), 'Saisissez votre mot de passe.'); ok = false; } else erreur(mdp, $('[data-aide-mdp]'), '');
    if (!ok) { $('.champ-erreur').focus(); return; }

    if (Date.now() < verrouJusqua) { bandeau('critique', 'lock', `Trop d'essais. Réessayez dans ${Math.ceil((verrouJusqua - Date.now()) / 60000)} minutes, ou réinitialisez le mot de passe.`); return; }
    const bouton = $('#b-connexion');
    bouton.disabled = true; bouton.innerHTML = '<i data-lucide="loader-circle" class="animate-spin"></i><span>Vérification…</span>'; icones();
    setTimeout(() => {
      bouton.disabled = false; bouton.innerHTML = '<span>Se connecter</span>';
      if (mdp.value.length < 8) {
        echecs += 1;
        mdp.value = ''; mdp.focus();
        if (echecs >= 5) { verrouJusqua = Date.now() + 15 * 60000; bandeau('critique', 'lock', 'Cinq essais manqués : la connexion est bloquée pendant 15 minutes. Un courriel d\'alerte a été envoyé au titulaire du compte.'); return; }
        /* Le même message que l'adresse existe ou non : on ne révèle pas les comptes. */
        bandeau('critique', 'circle-alert', `Adresse ou mot de passe incorrect. Il reste ${5 - echecs} essai${5 - echecs > 1 ? 's' : ''} avant un blocage de 15 minutes.`);
        return;
      }
      $('#bandeau').classList.add('hidden');
      $('[data-qui]').textContent = v;
      aller('code');
    }, 650);
  });

  /* ---------- Code à 6 chiffres ---------- */
  const cases = $$('[data-cases] input');
  cases.forEach((c, i) => {
    c.addEventListener('input', () => { c.value = c.value.replace(/\D/g, '').slice(-1); if (c.value && cases[i + 1]) cases[i + 1].focus(); if (cases.every((x) => x.value)) $('#f-code').requestSubmit(); });
    c.addEventListener('keydown', (e) => { if (e.key === 'Backspace' && !c.value && cases[i - 1]) cases[i - 1].focus(); if (e.key === 'ArrowLeft' && cases[i - 1]) cases[i - 1].focus(); if (e.key === 'ArrowRight' && cases[i + 1]) cases[i + 1].focus(); });
    c.addEventListener('paste', (e) => { const t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (!t) return; e.preventDefault(); t.split('').forEach((d, k) => { if (cases[k]) cases[k].value = d; }); (cases[t.length] || cases[5]).focus(); if (t.length === 6) $('#f-code').requestSubmit(); });
  });
  $('#f-code').addEventListener('submit', (e) => {
    e.preventDefault();
    const code = cases.map((c) => c.value).join('');
    const aide = $('[data-aide-code]');
    if (code.length < 6) { aide.className = 'aide-erreur'; aide.innerHTML = '<i data-lucide="circle-alert" class="w-3.5 h-3.5"></i>Saisissez les 6 chiffres du code.'; icones(); cases.find((c) => !c.value).focus(); return; }
    aide.className = 'aide-ok'; aide.innerHTML = '<i data-lucide="circle-check" class="w-3.5 h-3.5"></i>Code accepté. Ouverture de la console…'; icones();
    const mentor = /fifame|mentor/i.test(email.value);
    setTimeout(() => { location.href = 'index.html#/' + (mentor ? 'm-tableau-de-bord' : 'tableau-de-bord'); }, 600);
  });
  $('[data-secours]').addEventListener('click', () => toast('Saisissez l\'un de vos 8 codes de secours à la place du code. Chacun ne sert qu\'une fois.'));

  /* ---------- Mot de passe oublié ---------- */
  $('#f-oubli').addEventListener('submit', (e) => {
    e.preventDefault();
    const c = $('#email-oubli'), v = c.value.trim();
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) { erreur(c, $('[data-aide-oubli]'), 'Saisissez une adresse du type prenom.nom@verifall.com.'); c.focus(); return; }
    erreur(c, $('[data-aide-oubli]'), '');
    $('[data-qui-oubli]').textContent = v;
    aller('envoye');
  });

  icones();
})();
