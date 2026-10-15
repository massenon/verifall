/* Vérif'All — JS vanilla partagé du front public (remplace Bootstrap/Swiper/Livewire). */
(function () {
  'use strict';
  var VF = window.VF = window.VF || {};

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  VF.$ = $; VF.$$ = $$;

  /* ————— Toast (remplace les alertes Livewire/SweetAlert) ————— */
  VF.toast = function (message, type) {
    var t = document.createElement('div');
    t.className = 'vf-toast' + (type === 'danger' ? ' is-danger' : '');
    t.setAttribute('role', 'status');
    t.innerHTML = '<span aria-hidden="true">' + (type === 'danger' ? '⚠' : '✓') + '</span><span></span>';
    t.lastElementChild.textContent = message;
    document.body.appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 4200);
    setTimeout(function () { t.remove(); }, 4700);
  };

  /* ————— Navbar : burger + sticky ————— */
  function initNavbar() {
    var toggle = $('[data-collapse-toggle]');
    var panel = $('[data-collapse-panel]');
    if (toggle && panel) {
      toggle.addEventListener('click', function () {
        var open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        panel.classList.toggle('hidden', open);
      });
    }
    var header = $('[data-navbar]');
    if (header) {
      var lastState = false;
      window.addEventListener('scroll', function () {
        var sticky = window.scrollY > 400;
        if (sticky !== lastState) {
          lastState = sticky;
          header.classList.toggle('is-sticky', sticky);
        }
      }, { passive: true });
    }
  }

  /* ————— Carousel auth (fondu + pagination lignes, auto 5 s) ————— */
  function initAuthCarousel() {
    var root = $('[data-auth-carousel]');
    if (!root) return;
    var slides = $$('.auth-slide', root);
    var dots = $$('[data-auth-dot]', root);
    var idx = 0, timer;
    function go(i) {
      idx = (i + slides.length) % slides.length;
      slides.forEach(function (s, k) { s.classList.toggle('is-active', k === idx); });
      dots.forEach(function (d, k) { d.classList.toggle('is-active', k === idx); });
    }
    function auto() { timer = setInterval(function () { go(idx + 1); }, 5000); }
    dots.forEach(function (d) {
      d.addEventListener('click', function () {
        clearInterval(timer);
        go(parseInt(d.getAttribute('data-auth-dot'), 10));
        auto();
      });
    });
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) auto();
  }

  /* ————— Accordéons ————— */
  function initAccordions() {
    $$('[data-accordion]').forEach(function (group) {
      $$('.accordion-button', group).forEach(function (btn) {
        btn.addEventListener('click', function () {
          var item = btn.closest('.accordion-item');
          var open = item.classList.contains('is-open');
          if (group.getAttribute('data-accordion') !== 'multi') {
            $$('.accordion-item.is-open', group).forEach(function (o) {
              o.classList.remove('is-open');
              var b = $('.accordion-button', o); if (b) b.setAttribute('aria-expanded', 'false');
            });
          }
          item.classList.toggle('is-open', !open);
          btn.setAttribute('aria-expanded', String(!open));
        });
      });
    });
  }

  /* ————— Tabs pills ————— */
  function initTabs() {
    $$('[data-tabs]').forEach(function (group) {
      var btns = $$('[data-tab]', group);
      btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          btns.forEach(function (b) {
            b.classList.toggle('is-active', b === btn);
            b.setAttribute('aria-selected', String(b === btn));
          });
          var scope = group.getAttribute('data-tabs') ? document : group.parentElement;
          $$('[data-tab-panel]', scope).forEach(function (p) {
            p.classList.toggle('is-active', p.getAttribute('data-tab-panel') === btn.getAttribute('data-tab'));
          });
        });
      });
    });
  }

  /* ————— Compteurs animés (purecounter reproduit) ————— */
  function initCounters() {
    var els = $$('[data-counter]');
    if (!els.length) return;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        io.unobserve(e.target);
        var end = parseFloat(e.target.getAttribute('data-counter'));
        if (reduce) { e.target.textContent = String(end); return; }
        var t0 = performance.now(), dur = 1400;
        (function tick(now) {
          var p = Math.min(1, (now - t0) / dur);
          e.target.textContent = String(Math.round(end * (1 - Math.pow(1 - p, 3))));
          if (p < 1) requestAnimationFrame(tick);
        })(t0);
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ————— Révélations douces ————— */
  function initReveal() {
    var els = $$('[data-reveal]');
    if (!els.length) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
      });
    }, { threshold: 0.12 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ————— Popup PWA (délai 4 s, mémorisée 7 jours) ————— */
  function initPwaPopup() {
    var pop = $('#pwa-popup');
    if (!pop) return;
    try {
      var until = localStorage.getItem('vf.pwaDismissedUntil');
      if (until && Date.now() < parseInt(until, 10)) return;
    } catch (e) { /* stockage indisponible : on affiche quand même */ }
    setTimeout(function () { pop.classList.remove('hidden'); pop.classList.add('flex'); }, 4000);
    var later = $('[data-pwa-later]', pop);
    var install = $('[data-pwa-install]', pop);
    function dismiss(days) {
      pop.classList.add('hidden'); pop.classList.remove('flex');
      try { localStorage.setItem('vf.pwaDismissedUntil', String(Date.now() + days * 864e5)); } catch (e) { /* ignoré */ }
    }
    if (later) later.addEventListener('click', function () { dismiss(7); });
    if (install) install.addEventListener('click', function () {
      dismiss(30);
      VF.toast("Merci ! L'installation de l'application sera proposée par votre navigateur.");
    });
  }

  /* ————— Formulaires statiques (contact, newsletter, guest checker…) ————— */
  function initForms() {
    $$('form[data-vf-form]').forEach(function (form) {
      form.setAttribute('novalidate', 'novalidate');
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!form.checkValidity()) {
          form.classList.add('was-validated');
          var bad = form.querySelector(':invalid');
          if (bad) bad.focus();
          VF.toast('Veuillez compléter les champs requis.', 'danger');
          return;
        }
        var btn = form.querySelector('[type="submit"]');
        var initial = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = 'Envoi en cours…'; }
        setTimeout(function () {
          if (btn) { btn.disabled = false; btn.innerHTML = initial; }
          VF.toast(form.getAttribute('data-vf-form-success') || 'Message envoyé !');
          var redirect = form.getAttribute('data-vf-form-redirect');
          if (redirect) setTimeout(function () { window.location.href = redirect; }, 700);
          else form.reset();
        }, 900);
      });
    });
  }

  /* ————— Stepper d'inscription ————— */
  function initStepper() {
    var stepper = $('[data-stepper]');
    if (!stepper) return;
    var circles = $$('.step', stepper);
    var panels = $$('[data-step-panel]', stepper);
    var current = 1;
    function show(n) {
      current = n;
      panels.forEach(function (p) {
        p.classList.toggle('is-active', parseInt(p.getAttribute('data-step-panel'), 10) === n);
      });
      circles.forEach(function (c, i) {
        c.classList.toggle('active', i + 1 === n);
        c.classList.toggle('done', i + 1 < n);
      });
      var first = $('[data-step-panel].is-active input', stepper);
      if (first) first.focus();
    }
    stepper.addEventListener('click', function (ev) {
      var next = ev.target.closest('[data-step-next]');
      var prev = ev.target.closest('[data-step-prev]');
      if (next) {
        var panel = $('[data-step-panel].is-active', stepper);
        var bad = null;
        $$('input[required]', panel).forEach(function (i) { if (!bad && !i.checkValidity()) bad = i; });
        if (bad) { bad.focus(); VF.toast('Veuillez compléter les champs requis.', 'danger'); return; }
        if (panel.getAttribute('data-step-panel') === '2') {
          var p1 = $('#reg-password', stepper), p2 = $('#reg-password2', stepper);
          if (p1 && p2 && p1.value !== p2.value) {
            p2.focus(); VF.toast('Les deux mots de passe ne correspondent pas.', 'danger'); return;
          }
          var mail = $('#reg-email', stepper);
          var target = $('[data-otp-mail]');
          if (mail && target) target.textContent = mail.value;
        }
        show(Math.min(current + 1, panels.length));
      }
      if (prev) show(Math.max(current - 1, 1));
    });
    /* Jauge de force du mot de passe */
    var pwd = $('#reg-password', stepper);
    if (pwd) {
      var bar = $('[data-psw-bar]', stepper), label = $('[data-psw-label]', stepper);
      pwd.addEventListener('input', function () {
        var v = pwd.value, score = 0;
        if (v.length >= 8) score++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
        if (/\d/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        var levels = [
          { w: '8%', c: '#d6293e', t: 'Trop faible' },
          { w: '30%', c: '#d6293e', t: 'Faible' },
          { w: '55%', c: '#4c7cf3', t: "C'est déjà mieux !" },
          { w: '80%', c: '#f7c32e', t: 'Bon mot de passe' },
          { w: '100%', c: '#09B850', t: 'Excellent !' }
        ];
        var L = levels[score];
        if (bar) { bar.style.width = v ? L.w : '0'; bar.style.background = L.c; }
        if (label) label.textContent = v ? L.t : '';
      });
    }
    /* OTP auto-tab */
    var otps = $$('.otp-input', stepper);
    otps.forEach(function (input, index) {
      input.addEventListener('input', function (e) {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 1);
        if (e.target.value.length === 1 && index < otps.length - 1) otps[index + 1].focus();
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && e.target.value.length === 0 && index > 0) otps[index - 1].focus();
      });
    });
    var validate = $('[data-otp-validate]', stepper);
    if (validate) validate.addEventListener('click', function () {
      var code = otps.map(function (i) { return i.value; }).join('');
      if (code.length < otps.length) { VF.toast('Veuillez saisir les 6 chiffres du code.', 'danger'); return; }
      show(4);
    });
    var resend = $('[data-otp-resend]', stepper);
    if (resend) resend.addEventListener('click', function () {
      otps.forEach(function (i) { i.value = ''; });
      if (otps[0]) otps[0].focus();
      VF.toast('Un nouveau code vient de vous être envoyé par e-mail.');
    });
  }

  /* ————— Voir / cacher le mot de passe ————— */
  function initPasswordToggles() {
    $$('[data-pwd-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-pwd-toggle'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(show));
        btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
      });
    });
  }

  /* ————— Recherche / filtres simples (facts, tutoriels) ————— */
  function initFilters() {
    var search = $('[data-filter-search]');
    var cards = $$('[data-filter-item]');
    function apply() {
      var q = search ? search.value.trim().toLowerCase() : '';
      var checks = $$('[data-filter-check]:checked').map(function (c) { return c.value; });
      var visible = 0;
      cards.forEach(function (card) {
        var text = (card.getAttribute('data-filter-text') || card.textContent).toLowerCase();
        var tags = (card.getAttribute('data-filter-tags') || '').split('|');
        var okQ = !q || text.indexOf(q) > -1;
        var okT = !checks.length || checks.some(function (c) { return tags.indexOf(c) > -1; });
        var show = okQ && okT;
        card.classList.toggle('hidden', !show);
        if (show) visible++;
      });
      var empty = $('[data-filter-empty]');
      if (empty) empty.classList.toggle('hidden', visible !== 0);
    }
    if (search) search.addEventListener('input', apply);
    $$('[data-filter-check]').forEach(function (c) { c.addEventListener('change', apply); });
  }

  /* ————— Dropdowns ————— */
  function initDropdowns() {
    $$('[data-dropdown]').forEach(function (root) {
      var toggle = $('[data-dropdown-toggle]', root);
      var menu = $('[data-dropdown-menu]', root);
      if (!toggle || !menu) return;
      toggle.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        menu.classList.toggle('hidden', open);
      });
    });
    document.addEventListener('click', function () {
      $$('[data-dropdown-menu]').forEach(function (m) { m.classList.add('hidden'); });
      $$('[data-dropdown-toggle]').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        $$('[data-dropdown-menu]').forEach(function (m) { m.classList.add('hidden'); });
        $$('[data-dropdown-toggle]').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
      }
    });
  }

  /* ————— Carrousel générique (prev/next + fraction, 1 ou N colonnes) ————— */
  function initCarousels() {
    $$('[data-carousel]').forEach(function (root) {
      var track = $('.carousel-track', root);
      var slides = $$('.carousel-slide', root);
      if (!track || !slides.length) return;
      var idx = 0;
      var frac = $('[data-carousel-fraction]', root);
      function metrics() {
        var w = slides[0].getBoundingClientRect().width;
        var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || '0') || 0;
        var visible = Math.max(1, Math.round(root.getBoundingClientRect().width / (w + gap)));
        return { step: w + gap, max: Math.max(0, slides.length - visible) };
      }
      function update() {
        var m = metrics();
        if (idx > m.max) idx = m.max;
        track.style.transform = 'translateX(-' + (idx * m.step) + 'px)';
        if (frac) frac.textContent = (idx + 1) + ' / ' + slides.length;
      }
      var prev = $('[data-carousel-prev]', root);
      var next = $('[data-carousel-next]', root);
      if (prev) prev.addEventListener('click', function (e) { e.preventDefault(); idx = Math.max(0, idx - 1); update(); });
      if (next) next.addEventListener('click', function (e) { e.preventDefault(); idx = Math.min(metrics().max, idx + 1); update(); });
      root.addEventListener('vf:carousel-next', function () {
        var m = metrics();
        idx = idx >= m.max ? 0 : idx + 1; /* boucle pour l'autoplay */
        update();
      });
      window.addEventListener('resize', update);
      update();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initNavbar();
    initCarousels();
    initDropdowns();
    initAuthCarousel();
    initAccordions();
    initTabs();
    initCounters();
    initReveal();
    initPwaPopup();
    initForms();
    initStepper();
    initPasswordToggles();
    initFilters();
  });
})();

/* ————— Bandeau cookies (essentiels + audience optionnelle) ————— */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var banner = document.getElementById('cookie-banner');
    if (!banner) return;
    try {
      if (localStorage.getItem('vf.cookieConsent')) return;
    } catch (e) { /* stockage indisponible */ }
    setTimeout(function () { banner.classList.remove('hidden'); }, 1200);
    var prefs = document.getElementById('cookie-prefs');
    function save(analytics) {
      try {
        localStorage.setItem('vf.cookieConsent', JSON.stringify({ essential: true, analytics: !!analytics, at: Date.now() }));
      } catch (e) { /* ignoré */ }
      banner.classList.add('hidden');
      if (window.VF && window.VF.toast) window.VF.toast('Vos préférences de cookies ont été enregistrées.');
    }
    var btnCustom = banner.querySelector('[data-cookie-custom]');
    var btnAccept = banner.querySelector('[data-cookie-accept]');
    var btnCancel = banner.querySelector('[data-cookie-cancel]');
    if (btnCustom) btnCustom.addEventListener('click', function () {
      var open = !prefs.classList.contains('hidden');
      prefs.classList.toggle('hidden', open);
      if (!open) {
        btnAccept.textContent = 'Enregistrer mes choix';
      }
    });
    if (btnAccept) btnAccept.addEventListener('click', function () {
      var analytics = prefs.classList.contains('hidden') ? true : document.getElementById('cookie-analytics').checked;
      save(analytics);
    });
    if (btnCancel) btnCancel.addEventListener('click', function () { save(false); });
  });
})();

/* ————— Autoplay des carrousels marqués data-carousel-autoplay ————— */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-carousel-autoplay]').forEach(function (root) {
      var delay = parseInt(root.getAttribute('data-carousel-autoplay'), 10) || 3000;
      var next = root.querySelector('[data-carousel-next]');
      var timer = null;
      function tick() {
        if (next) next.click();
        else {
          /* pas de flèches : avancer la piste manuellement via l'API interne si exposée */
          var ev = new CustomEvent('vf:carousel-next', { bubbles: true });
          root.dispatchEvent(ev);
        }
      }
      function start() { if (!timer) timer = setInterval(tick, delay); }
      function stop() { clearInterval(timer); timer = null; }
      start();
      root.addEventListener('mouseenter', stop);
      root.addEventListener('mouseleave', start);
      document.addEventListener('visibilitychange', function () {
        if (document.hidden) stop(); else start();
      });
    });
  });
})();

/* ————— Social auth (simulation prototype) ————— */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-social]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var provider = btn.getAttribute('data-social');
        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = 'Connexion ' + provider + '…';
        setTimeout(function () {
          window.location.href = 'compte-user/dashboard.html';
        }, 900);
        setTimeout(function () { btn.innerHTML = original; btn.disabled = false; }, 4000);
      });
    });
  });
})();
