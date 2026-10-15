/* Vérif'All — citizen.js · comportements du shell et composants du compte citoyen. */
(function () {
  'use strict';
  var VF = window.VFC = {};
  function $(s, c) { return (c || document).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }
  VF.$ = $; VF.$$ = $$;

  VF.toast = function (message, type) {
    var t = document.createElement('div');
    t.className = 'vf-toast' + (type === 'danger' ? ' is-danger' : '');
    t.setAttribute('role', 'status');
    t.innerHTML = '<span class="material-symbols-rounded" style="color:' + (type === 'danger' ? '#DC2626' : '#09B850') + '">' + (type === 'danger' ? 'error' : 'check_circle') + '</span><span></span>';
    t.lastElementChild.textContent = message;
    document.body.appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 4200);
    setTimeout(function () { t.remove(); }, 4700);
  };

  /* Confetti (réussite quiz / cours / certificat) */
  VF.confetti = function (duration) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var colors = ['#09B850', '#F7C32E', '#2563EB', '#DC2626', '#7C3AED'];
    var n = 90;
    for (var i = 0; i < n; i++) {
      var p = document.createElement('span');
      p.className = 'confetti-piece';
      p.style.left = (Math.random() * 100) + 'vw';
      p.style.background = colors[i % colors.length];
      p.style.animationDuration = (1.6 + Math.random() * 1.4) + 's';
      p.style.animationDelay = (Math.random() * .6) + 's';
      p.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
      document.body.appendChild(p);
      (function (el) { setTimeout(function () { el.remove(); }, (duration || 2600) + 900); })(p);
    }
  };

  /* Modale générique */
  VF.openModal = function (id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.add('is-open');
    var f = m.querySelector('button, [href], input, select, textarea');
    if (f) f.focus();
  };
  VF.closeModal = function (id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('is-open');
  };

  function initShell() {
    /* Drawer mobile */
    var open = $('[data-drawer-open]');
    var drawer = $('[data-drawer]');
    var backdrop = $('[data-drawer-backdrop]');
    function close() {
      if (drawer) drawer.classList.remove('is-open');
      if (backdrop) backdrop.classList.add('hidden');
      if (open) open.setAttribute('aria-expanded', 'false');
    }
    if (open && drawer) {
      open.addEventListener('click', function () {
        var isOpen = drawer.classList.toggle('is-open');
        open.setAttribute('aria-expanded', String(isOpen));
        if (backdrop) backdrop.classList.toggle('hidden', !isOpen);
      });
    }
    if (backdrop) backdrop.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    /* Dropdowns */
    $$('[data-dropdown]').forEach(function (root) {
      var toggle = $('[data-dropdown-toggle]', root);
      var menu = $('[data-dropdown-menu]', root);
      if (!toggle || !menu) return;
      toggle.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var isOpen = toggle.getAttribute('aria-expanded') === 'true';
        $$('[data-dropdown-menu]').forEach(function (m) { m.classList.add('hidden'); });
        $$('[data-dropdown-toggle]').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
        if (!isOpen) { menu.classList.remove('hidden'); toggle.setAttribute('aria-expanded', 'true'); }
      });
      menu.addEventListener('click', function (ev) { ev.stopPropagation(); });
    });
    document.addEventListener('click', function () {
      $$('[data-dropdown-menu]').forEach(function (m) { m.classList.add('hidden'); });
      $$('[data-dropdown-toggle]').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
    });

    /* Tabs */
    $$('[data-tabs]').forEach(function (group) {
      var btns = $$('[data-tab]', group);
      btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
          btns.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
          var scope = group.closest('[data-tabs-scope]') || document;
          $$('[data-tab-panel]', scope).forEach(function (p) {
            p.classList.toggle('is-active', p.getAttribute('data-tab-panel') === btn.getAttribute('data-tab'));
          });
          var hook = group.getAttribute('data-tabs-hook');
          if (hook && VF[hook]) VF[hook](btn.getAttribute('data-tab'));
        });
      });
    });

    /* Bottom nav actif */
    var file = location.pathname.split('/').pop() || 'dashboard.html';
    $$('.bn-item').forEach(function (a) {
      if (a.getAttribute('data-bn') === file) a.classList.add('is-active');
    });

    /* KPI accordéon mobile — N'EST PLUS LIÉ ICI (14/09/2026).
       Ce bloc du prototype liait le clic une première fois, `verifall-app.js`
       une seconde : chaque clic basculait `is-open` puis le rebasculait, et
       l'accordéon ne s'ouvrait jamais. Les deux seuls gabarits qui chargent ce
       fichier (`layouts/citizen` et `layouts/staff`) chargent aussi
       `verifall-app.js`, qui porte désormais l'unique écouteur (délégué, donc
       insensible aux rendus Livewire). `AccordeonKpiTest` y veille. */

    /* Révélations */
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
      });
    }, { threshold: 0.1 });
    $$('[data-reveal]').forEach(function (el) { io.observe(el); });
  }

  /* Badges dynamiques (unités, XP) + notifications */
  function render() {
    if (!window.VFStore) return;
    var S = window.VFStore.state;
    $$('[data-units-badge]').forEach(function (el) { el.textContent = S.units; });
    $$('[data-xp-badge]').forEach(function (el) { el.textContent = S.xp.toLocaleString('fr-FR'); });
    var dot = $('[data-notif-dot]');
    var unread = S.notifications.filter(function (n) { return !n.read; }).length;
    if (dot) dot.classList.toggle('hidden', unread === 0);
    var list = $('[data-notif-list]');
    if (list) {
      var filter = list.getAttribute('data-filter') || 'all';
      var items = S.notifications.filter(function (n) {
        return filter === 'all' || n.cat === (filter === 'verif' ? 'verif' : 'lms');
      });
      if (!items.length) {
        list.innerHTML = '<p class="px-4 py-6 text-sm text-slate2 text-center">Aucune notification pour le moment.</p>';
      } else {
        list.innerHTML = items.map(function (n) {
          var inner = '<span class="material-symbols-rounded shrink-0 ' + (n.read ? 'text-slate2' : 'text-primary') + '">' + n.icon + '</span>'
            + '<span class="flex-1 min-w-0"><span class="block text-sm ' + (n.read ? 'text-slate2' : 'text-ink font-medium') + '">' + n.text + '</span>'
            + '<span class="block text-xs text-slate2 mt-0.5">' + n.time + '</span></span>';
          return n.href
            ? '<a href="' + n.href + '" class="flex gap-3 px-4 py-3 hover:bg-canvas">' + inner + '</a>'
            : '<div class="flex gap-3 px-4 py-3">' + inner + '</div>';
        }).join('');
      }
    }
  }
  VF.render = render;
  VF.notifFilter = function (tab) {
    var list = $('[data-notif-list]');
    if (!list) return;
    list.setAttribute('data-filter', tab === 'notif-all' ? 'all' : (tab === 'notif-verif' ? 'verif' : 'lms'));
    render();
  };

  function initNotifTabs() {
    /* Les onglets du dropdown notifications pilotent le filtre */
    $$('[data-tab^="notif-"]').forEach(function (btn) {
      btn.addEventListener('click', function () { VF.notifFilter(btn.getAttribute('data-tab')); });
    });
    var clear = $('[data-notif-clear]');
    if (clear) clear.addEventListener('click', function () {
      window.VFStore.state.notifications.forEach(function (n) { n.read = true; });
      window.VFStore.save();
      render();
    });
  }

  /* Recherche globale (vérifications, cours, outils, pages) */
  function initSearch() {
    var root = $('[data-global-search]');
    if (!root || !window.VFStore) return;
    var input = $('input', root);
    var results = $('[data-search-results]', root);
    var INDEX = [];
    window.VFStore.COURSES.forEach(function (c) {
      INDEX.push({ icon: 'school', label: c.title, meta: 'Cours · ' + c.level, href: 'cours.html?c=' + c.id });
    });
    window.VFStore.state.verifications.forEach(function (v) {
      INDEX.push({ icon: 'fact_check', label: v.title, meta: 'Vérification · ' + (v.verdict || 'En cours'), href: 'rapport.html?u=' + v.uuid });
    });
    [['construction', 'Répertoire des outils OSINT', 'Outils', 'outils.html'],
     ['forum', 'Forum de la communauté', 'Communauté', 'forum.html'],
     ['newspaper', 'Hub d\'Infos Bénin', 'Actualités vérifiées', 'hub-infos.html'],
     ['military_tech', 'Mes Récompenses', 'XP, badges, certificats', 'recompenses.html'],
     ['monitoring', 'Mes Statistiques', 'Progression', 'statistiques.html']
    ].forEach(function (x) { INDEX.push({ icon: x[0], label: x[1], meta: x[2], href: x[3] }); });

    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      if (q.length < 2) { results.classList.add('hidden'); return; }
      var found = INDEX.filter(function (i) { return i.label.toLowerCase().indexOf(q) > -1 || i.meta.toLowerCase().indexOf(q) > -1; }).slice(0, 7);
      results.innerHTML = found.length
        ? found.map(function (i) {
            return '<a href="' + i.href + '" class="flex items-center gap-3 px-4 py-2.5 hover:bg-canvas" role="option">'
              + '<span class="material-symbols-rounded text-primary !text-[20px]">' + i.icon + '</span>'
              + '<span class="min-w-0"><span class="block text-sm text-ink font-medium truncate">' + i.label + '</span>'
              + '<span class="block text-xs text-slate2">' + i.meta + '</span></span></a>';
          }).join('')
        : '<p class="px-4 py-4 text-sm text-slate2">Aucun résultat pour « ' + q.replace(/</g, '&lt;') + ' ».</p>';
      results.classList.remove('hidden');
    });
    document.addEventListener('click', function (e) {
      if (!root.contains(e.target)) results.classList.add('hidden');
    });
  }

  /* Rich textarea : toolbar B/I/U/listes/lien + compteur de caractères */
  function initRichAreas() {
    $$('[data-rich]').forEach(function (root) {
      var editor = $('.rich-editor', root);
      var counter = $('[data-char-counter]', root.parentElement) || $('[data-char-counter]', root);
      var max = parseInt(root.getAttribute('data-maxlength') || '0', 10);
      $$('.rich-btn', root).forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
          ev.preventDefault();
          var cmd = btn.getAttribute('data-cmd');
          if (cmd === 'createLink') {
            var url = window.prompt('Adresse du lien (https://…)');
            if (url && /^https?:\/\//.test(url)) document.execCommand('createLink', false, url);
            else if (url) VF.toast('Le lien doit commencer par http(s)://', 'danger');
          } else {
            document.execCommand(cmd, false, null);
          }
          editor.focus();
          syncStates();
        });
      });
      function syncStates() {
        $$('.rich-btn', root).forEach(function (btn) {
          var cmd = btn.getAttribute('data-cmd');
          if (['bold', 'italic', 'underline', 'insertUnorderedList', 'insertOrderedList'].indexOf(cmd) > -1) {
            btn.classList.toggle('is-active', document.queryCommandState(cmd));
          }
        });
      }
      function syncCounter() {
        if (!counter) return;
        var len = editor.textContent.length;
        counter.textContent = max ? (len + ' / ' + max + ' caractères') : (len + ' caractères');
        counter.classList.toggle('is-over', !!max && len > max);
      }
      editor.addEventListener('keyup', function () { syncStates(); syncCounter(); });
      editor.addEventListener('mouseup', syncStates);
      editor.addEventListener('input', syncCounter);
      syncCounter();
    });
    /* Compteurs sur inputs/textarea simples */
    $$('[data-count-input]').forEach(function (input) {
      var counter = document.querySelector('[data-char-counter="' + input.id + '"]');
      var max = parseInt(input.getAttribute('maxlength') || input.getAttribute('data-max') || '0', 10);
      function sync() {
        if (!counter) return;
        var len = input.value.length;
        counter.textContent = max ? (len + ' / ' + max + ' caractères') : (len + ' caractères');
        counter.classList.toggle('is-over', !!max && len > max);
      }
      input.addEventListener('input', sync);
      sync();
    });
  }

  /* Fermeture modales par backdrop / data-modal-close */
  function initModals() {
    $$('.modal-backdrop').forEach(function (bd) {
      bd.addEventListener('click', function (e) {
        if (e.target === bd) bd.classList.remove('is-open');
      });
    });
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-modal-close]');
      if (btn) {
        var bd = btn.closest('.modal-backdrop');
        if (bd) bd.classList.remove('is-open');
      }
      var opener = e.target.closest('[data-modal-open]');
      if (opener) VF.openModal(opener.getAttribute('data-modal-open'));
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initShell();
    initNotifTabs();
    initSearch();
    initRichAreas();
    initModals();
    render();
    if (typeof window.VFPage === 'function') window.VFPage(VF, window.VFStore);
  });
})();


/* ═══ Topbar staff : plein écran + notifications lues/non-lues (délégation globale) ═══ */
document.addEventListener('click', function (e) {
  var fs = e.target.closest('[data-fullscreen]');
  if (fs) {
    if (!document.fullscreenElement && document.documentElement.requestFullscreen) document.documentElement.requestFullscreen();
    else if (document.exitFullscreen) document.exitFullscreen();
  }
  var one = e.target.closest('[data-notif]');
  if (one && one.getAttribute('data-unread') === 'true') {
    one.setAttribute('data-unread', 'false');
    one.classList.remove('bg-primary-soft/40');
    var dot = one.querySelector('[data-unread-dot]');
    if (dot) dot.remove();
    syncNotifCount();
  }
  if (e.target.closest('[data-notif-readall]')) {
    document.querySelectorAll('[data-notif][data-unread="true"]').forEach(function (n) {
      n.setAttribute('data-unread', 'false');
      n.classList.remove('bg-primary-soft/40');
      var dot = n.querySelector('[data-unread-dot]');
      if (dot) dot.remove();
    });
    syncNotifCount();
  }
  function syncNotifCount() {
    var badge = document.querySelector('[data-notif-count]');
    if (!badge) return;
    var n = document.querySelectorAll('[data-notif][data-unread="true"]').length;
    badge.textContent = n;
    badge.classList.toggle('hidden', n === 0);
    var btn = badge.closest('button');
    if (btn) btn.setAttribute('aria-label', n ? 'Notifications — ' + n + ' non lues' : 'Notifications — tout est lu');
  }
});
