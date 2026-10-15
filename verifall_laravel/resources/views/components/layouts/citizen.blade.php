@php
  /*
   | Le compte connecté, lu une seule fois pour toute la barre supérieure.
   |
   | Ces valeurs étaient recopiées du prototype et figées : chaque personne
   | connectée voyait le solde, l'expérience et le nom d'Adderly. Sur l'écran
   | des unités, le bandeau annonçait 34 quand la carte en annonçait 49.
   */
  $compte = auth()->user();
  $initiales = \Illuminate\Support\Str::of(trim(($compte?->first_name ?? '').' '.($compte?->last_name ?? '')) ?: ($compte?->name ?? '?'))
      ->explode(' ')
      ->filter()
      ->take(2)
      ->map(fn (string $mot): string => mb_strtoupper(mb_substr($mot, 0, 1)))
      ->implode('');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? "Vérif'All" }}</title>
    <meta name="description" content="{{ $description ?? "Vérif'All — la boussole contre la désinformation numérique." }}">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <x-pwa />

    {{-- Ordre de cascade IMPOSÉ (DESIGN_SYSTEM §Tokens) : tailwind.css EN DERNIER.
         Toute inversion casse la spécificité et donc le rendu du prototype. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/citizen.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tailwind.css') }}">
    {{-- Couche applicative : additive, jamais correctrice du design system. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/verifall-app.css') }}">
    {{-- Sélecteur de langue. Feuille dédiée : le build Tailwind est figé, et
         une classe absente ne produirait aucune erreur, seulement deux boutons
         sans style dans la barre. --}}
    <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/pages/langues.css') }}">
    @stack('styles')
</head>
<body class="font-sans text-slate2 bg-canvas antialiased" @if(auth()->check() && auth()->user()->tour_done_at === null) data-tour-auto="citizen"@endif>
<a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-surface focus:text-ink focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-card">{{ __('cit_citizen.aller_au_contenu') }}</a>

<header class="fixed top-0 inset-x-0 z-40 h-[72px] bg-surface border-b border-line shadow-[0_1px_0_rgba(17,24,39,.02)]">
  <div class="h-full flex items-center gap-3 lg:gap-5 px-4 lg:px-6">
    <button type="button" class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-canvas text-ink" data-drawer-open aria-label="{{ __('cit_citizen.ouvrir_le_menu') }}" aria-expanded="false"><span class="material-symbols-rounded " aria-hidden="true">{{ __('cit_citizen.menu') }}</span></button>
    <a href="{{ route('citizen.dashboard') }}" class="shrink-0" aria-label="{{ __('cit_citizen.verif_all_tableau_de_bord') }}" @if(request()->routeIs('citizen.dashboard')) aria-current="page" @endif>
      <img src="{{ asset('assets/images/logo.png') }}" alt="Vérif'All" width="171" height="60" class="h-10 w-auto">
    </a>
    <div class="hidden md:flex flex-1 min-w-0 max-w-xl relative mr-auto ml-4 lg:ml-8" data-global-search>
      <label for="global-search" class="sr-only">{{ __('cit_citizen.recherche_globale') }}</label>
      <span class="material-symbols-rounded absolute left-4 top-1/2 -translate-y-1/2 text-slate2 !text-[20px]" aria-hidden="true">search</span>
      <input id="global-search" type="search" autocomplete="off" placeholder="{{ __('cit_citizen.rechercher_une_verification_un_cours_un_outil') }}"
             class="w-full bg-canvas border border-line rounded-full pl-11 pr-4 py-3 text-sm text-ink outline-none focus:bg-surface focus:border-primary focus:ring-4 focus:ring-primary/10 transition-colors">
      <div class="hidden absolute top-full left-0 right-0 mt-2 bg-surface border border-line rounded-2xl shadow-cardlg py-2 max-h-80 overflow-auto z-30" data-search-results role="listbox"></div>
    </div>
    <div class="flex-1 md:hidden"></div>
    <div class="flex items-center gap-2 lg:gap-3 shrink-0 ml-auto md:ml-0">
    <a data-tour="verifier" href="{{ route('citizen.verify') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-xl bg-brandred text-white text-sm font-semibold px-5 py-3 hover:opacity-90 transition-opacity shadow-[0_2px_8px_rgba(214,41,62,.35)]" @if(request()->routeIs('citizen.verify')) aria-current="page" @endif>
      <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span> {{ __('cit_citizen.verifier') }}
    </a>
    {{-- La cloche vient d'un composant : sa liste et sa pastille sortent de la
         base, plus de `localStorage`. Le marquage rendu est identique à celui
         que ce gabarit portait — seule la source des données change. --}}
    {{-- Le sélecteur de langue, à gauche de la cloche : il se règle une fois
         et ne se reconsulte pas, alors que la cloche s'ouvre plusieurs fois par
         session. Le geste fréquent reste le plus proche du bord. --}}
    <div class="hidden sm:block"><x-selecteur-langue /></div>
    {{-- Le témoin d'analyse (B-03). Une vérification prend des dizaines de
         secondes et se paie en unités ; quitter l'écran de suivi ne faisait
         plus rien paraître, et l'on ne savait plus si elle tournait encore.
         Il ne rend rien — et n'interroge rien — hors analyse en cours. --}}
    <livewire:citizen.analyse-en-cours />
    <livewire:citizen.cloche />
    <a data-tour="unites" href="{{ route('citizen.units') }}" class="hidden sm:inline-flex items-center gap-1 rounded-xl bg-surface border border-line px-3.5 py-2.5 hover:border-primary transition-colors" title="{{ __('cit_citizen.mes_unites_de_verification') }}" @if(request()->routeIs('citizen.units')) aria-current="page" @endif>
      <span class="material-symbols-rounded !text-[21px] text-primary icon-fill" aria-hidden="true">bolt</span> <span class="text-base font-bold text-ink" data-units-badge>{{ number_format((int) ($compte?->units ?? 0), 0, ',', ' ') }}</span>
    </a>
    <div class="relative" data-dropdown>
      <button data-tour="menu-utilisateur" type="button" class="flex items-center gap-1.5" data-dropdown-toggle aria-expanded="false" aria-haspopup="true" aria-label="{{ __('cit_citizen.menu_utilisateur') }}">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-sidebarbg text-white font-bold text-sm ring-2 ring-line">{{ $initiales }}</span>
        <span class="material-symbols-rounded hidden sm:block text-slate2 !text-[22px]" aria-hidden="true">expand_more</span>
      </button>
      <div class="hidden absolute right-0 top-full mt-2 w-64 bg-surface border border-line rounded-2xl shadow-cardlg py-2 z-30" data-dropdown-menu>
        <div class="px-4 py-3 border-b border-line">
          <p class="font-display font-bold text-ink leading-tight mb-0">{{ $compte?->name ?? __('cit_citizen.mon_compte') }}</p>
          <p class="text-xs text-slate2 mb-0">{{ __('cit_citizen.citoyen_verificateur') }}</p>
          <p class="text-xs font-bold text-xp mt-1 mb-0"><span data-xp-badge>{{ number_format((int) ($compte?->xp ?? 0), 0, ',', ' ') }}</span> XP</p>
        </div>
        <nav class="py-1">
          <a class="user-menu-link" href="{{ route('citizen.evaluations') }}" @if(request()->routeIs('citizen.evaluations')) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">grading</span> {{ __('cit_citizen.mes_evaluations') }}</a>
          <a class="user-menu-link" href="{{ route('citizen.live-classes') }}" @if(request()->routeIs('citizen.live-classes')) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">videocam</span> {{ __('cit_citizen.live_classes') }}</a>
          <a data-tour="statistiques" class="user-menu-link" href="{{ route('citizen.stats') }}" @if(request()->routeIs('citizen.stats')) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">monitoring</span> {{ __('cit_citizen.mes_statistiques') }}</a>
          <a class="user-menu-link" href="{{ route('citizen.units') }}" @if(request()->routeIs('citizen.units')) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">bolt</span> {{ __('cit_citizen.mes_unites') }}</a>
          <a class="user-menu-link" href="{{ route('citizen.rewards') }}" @if(request()->routeIs('citizen.rewards')) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">military_tech</span> {{ __('cit_citizen.mes_recompenses') }}</a>
          {{-- ── PORTAILS OFFICIELS ─────────────────────────────────────
               Annoncée AVANT d'être ouverte, à la demande du porteur du projet :
               le CRUD d'administration existe, la rubrique citoyenne attend son
               contenu. La case `hub_portals_actif` des réglages l'ouvre.

               Tant qu'elle est fermée : pas de `href` — un lien sans
               destination n'est pas un lien — et la route rend 404, pour qu'une
               adresse devinée ne contourne pas la décision. --}}
          @php($portailsOuverts = app(\App\Core\Application\Reglages\Reglages::class)->actif('hub_portals_actif'))
          @if($portailsOuverts)
            <a class="user-menu-link" href="{{ route('citizen.portals') }}" @if(request()->routeIs('citizen.portals')) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">account_balance</span> {{ __('cit_citizen.portails_officiels') }}</a>
          @else
            <span class="user-menu-link is-a-venir" aria-disabled="true"><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">account_balance</span> {{ __('cit_citizen.portails_officiels') }}<span class="vf-bientot">{{ __('cit_citizen.bientot') }}</span></span>
          @endif
          <a data-tour="profil" class="user-menu-link" href="{{ route('citizen.profile') }}" @if(request()->routeIs('citizen.profile')) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">person</span> {{ __('cit_citizen.mon_profil') }}</a>
          <a class="user-menu-link" href="{{ route('citizen.settings') }}" @if(request()->routeIs('citizen.settings')) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">settings</span> {{ __('cit_citizen.reglages') }}</a>
        </nav>
        <div class="border-t border-line pt-1">
          {{-- Formulaire et non lien : un GET vers la page de connexion donne
               l'illusion d'une déconnexion sans détruire la session. --}}
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="user-menu-link text-brandred w-full text-left">{!! __('cit_citizen.logout_se_deconnecter') !!}</button>
          </form>
        </div>
      </div>
    </div>
    </div>
  </div>
</header>

<aside id="sidebar" class="sidebar" data-drawer aria-label="{{ __('cit_citizen.navigation_principale') }}">
  <div class="flex flex-col h-full overflow-y-auto px-4 py-6">
    <nav class="flex-1">
      <div class="mb-4">
        <a class="sidebar-link {{ request()->routeIs('citizen.dashboard') ? 'is-active' : '' }}" href="{{ route('citizen.dashboard') }}" @if(request()->routeIs('citizen.dashboard')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">space_dashboard</span><span>{{ __('cit_citizen.tableau_de_bord') }}</span></a>
        <a class="sidebar-link {{ request()->routeIs('citizen.verify') ? 'is-active' : '' }}" href="{{ route('citizen.verify') }}" @if(request()->routeIs('citizen.verify')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">search</span><span>{{ __('cit_citizen.verifier_un_contenu') }}</span></a>
        <a class="sidebar-link {{ request()->routeIs('citizen.verifications') ? 'is-active' : '' }}" href="{{ route('citizen.verifications') }}" @if(request()->routeIs('citizen.verifications')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">fact_check</span><span>{{ __('cit_citizen.mes_verifications') }}</span></a>
        <a class="sidebar-link {{ request()->routeIs('citizen.tools') ? 'is-active' : '' }}" href="{{ route('citizen.tools') }}" @if(request()->routeIs('citizen.tools')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">construction</span><span>{{ __('cit_citizen.liste_des_outils') }}</span></a>
      </div>
      <div class="mb-4"><p class="sidebar-label">{{ __('cit_citizen.apprentissage') }}</p>
        <a class="sidebar-link {{ request()->routeIs('citizen.tutorials') ? 'is-active' : '' }}" href="{{ route('citizen.tutorials') }}" @if(request()->routeIs('citizen.tutorials')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">play_lesson</span><span>{{ __('cit_citizen.mes_tutoriels') }}</span></a>
        <a data-tour="academie" class="sidebar-link {{ request()->routeIs('citizen.catalogue') ? 'is-active' : '' }}" href="{{ route('citizen.catalogue') }}" @if(request()->routeIs('citizen.catalogue')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">school</span><span>{{ __('cit_citizen.catalogue_de_cours') }}</span></a>
        <a class="sidebar-link {{ request()->routeIs('citizen.rewards') ? 'is-active' : '' }}" href="{{ route('citizen.rewards') }}" @if(request()->routeIs('citizen.rewards')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">workspace_premium</span><span>{{ __('cit_citizen.certificats_badges') }}</span></a>
      </div>
      <div class="mb-4"><p class="sidebar-label">{{ __('cit_citizen.communaute') }}</p>
        <a data-tour="forum" class="sidebar-link {{ request()->routeIs('citizen.forum') ? 'is-active' : '' }}" href="{{ route('citizen.forum') }}" @if(request()->routeIs('citizen.forum')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">forum</span><span>{{ __('cit_citizen.forum') }}</span></a>
        <a class="sidebar-link {{ request()->routeIs('citizen.hub') ? 'is-active' : '' }}" href="{{ route('citizen.hub') }}" @if(request()->routeIs('citizen.hub')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">newspaper</span><span>{{ __('cit_citizen.hub_opportunites') }}</span></a>
        <a class="sidebar-link {{ request()->routeIs('citizen.referral') ? 'is-active' : '' }}" href="{{ route('citizen.referral') }}" @if(request()->routeIs('citizen.referral')) aria-current="page" @endif><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">group_add</span><span>{{ __('cit_citizen.mon_parrainage') }}</span></a>
      </div>
    </nav>
    <form method="POST" action="{{ route('logout') }}" class="mt-4">
      @csrf
      <button type="submit" class="w-full flex items-center justify-center gap-2 rounded-xl bg-brandred text-white text-sm font-semibold px-3 py-3 hover:opacity-90 transition-opacity">{!! __('cit_citizen.logout_deconnexion') !!}</button>
    </form>
  </div>
</aside>
<div class="drawer-backdrop hidden lg:hidden" data-drawer-backdrop aria-hidden="true"></div>
<main id="contenu" class="app-main">

{{ $slot }}

</main>

<nav class="bottom-nav lg:hidden" aria-label="{{ __('cit_citizen.navigation_mobile') }}">
  <a href="{{ route('citizen.dashboard') }}" class="bn-item {{ request()->routeIs('citizen.dashboard') ? 'is-active' : '' }}" data-bn="dashboard.html" @if(request()->routeIs('citizen.dashboard')) aria-current="page" @endif><span class="material-symbols-rounded !text-[24px]" aria-hidden="true">home</span><span>{{ __('cit_citizen.accueil') }}</span></a>
  <a href="{{ route('citizen.catalogue') }}" class="bn-item {{ request()->routeIs('citizen.catalogue') ? 'is-active' : '' }}" data-bn="catalogue.html" @if(request()->routeIs('citizen.catalogue')) aria-current="page" @endif><span class="material-symbols-rounded !text-[24px]" aria-hidden="true">school</span><span>{{ __('cit_citizen.apprendre') }}</span></a>
  <a href="{{ route('citizen.verify') }}" class="bn-verify" aria-label="{{ __('cit_citizen.verifier_un_contenu') }}" @if(request()->routeIs('citizen.verify')) aria-current="page" @endif><span class="material-symbols-rounded !text-[26px]" aria-hidden="true">search</span></a>
  <a href="{{ route('citizen.forum') }}" class="bn-item {{ request()->routeIs('citizen.forum') ? 'is-active' : '' }}" data-bn="forum.html" @if(request()->routeIs('citizen.forum')) aria-current="page" @endif><span class="material-symbols-rounded !text-[24px]" aria-hidden="true">forum</span><span>{{ __('cit_citizen.communaute_nav') }}</span></a>
  <a href="{{ route('citizen.profile') }}" class="bn-item {{ request()->routeIs('citizen.profile') ? 'is-active' : '' }}" data-bn="profil.html" @if(request()->routeIs('citizen.profile')) aria-current="page" @endif><span class="material-symbols-rounded !text-[24px]" aria-hidden="true">person</span><span>{{ __('cit_citizen.profil') }}</span></a>
</nav>
<a href="{{ route('citizen.verify') }}" class="fab lg:hidden" aria-label="{{ __('cit_citizen.verifier_un_contenu') }}" @if(request()->routeIs('citizen.verify')) aria-current="page" @endif><span class="material-symbols-rounded !text-[28px]" aria-hidden="true">add</span></a>
{{--
  | `vf-store.js` du prototype n'est PAS servi, et ne doit pas l'être.
  |
  | C'est la simulation localStorage du prototype : cours, verdicts, XP et
  | badges écrits en dur. Le copier réintroduirait des données fabriquées dans
  | un produit qui lit désormais sa base. La balise restait pourtant dans le
  | shell et produisait un 404 sur CHAQUE page de l'espace citoyen.
--}}
<script src="{{ asset('assets/js/citizen.js') }}" defer></script>
<x-toast variant="citizen" />
<script src="{{ asset('assets/js/verifall-app.js') }}" defer></script>
<script src="{{ asset('assets/js/guided-tour.js') }}" defer></script>
@stack('scripts')
</body>
</html>
