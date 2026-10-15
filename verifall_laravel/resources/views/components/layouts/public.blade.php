<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Titre, description, canonique, OpenGraph, Twitter et JSON-LD.
         Posés par SeoServiceProvider à partir du nom de la route ; une page
         qui a mieux à dire (article, fact-check, cours) fournit son propre
         $seo et prend le pas. --}}
    <x-seo :seo="$seo" />
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <x-pwa />

    {{-- Ordre de cascade IMPOSÉ (DESIGN_SYSTEM §Tokens) : tailwind.css EN DERNIER.
         Toute inversion casse la spécificité et donc le rendu du prototype. --}}
    <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/verifall.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/tailwind.css') }}">
    {{-- Couche applicative : additive, jamais correctrice du design system. --}}
    <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/verifall-app.css') }}">
    {{-- Sélecteur de langue. Feuille dédiée : le build Tailwind est figé, et
         une classe absente ne produirait aucune erreur, seulement deux boutons
         sans style collés au reste de l'en-tête. --}}
    <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/pages/langues.css') }}">
    {{-- Campagnes : déclarée ICI et non par `@push` depuis le composant.
         `@stack('styles')` est vidée avant que le corps ne s'exécute, donc un
         `@push` fait depuis un composant du corps n'arrive jamais. --}}
    @if (($campagnes ?? collect())->isNotEmpty())
      <link rel="stylesheet" href="{{ \App\Support\Assets\ActifVersionne::url('assets/css/pages/campagnes.css') }}">
    @endif
    @stack('styles')
</head>
<body class="font-sans text-body bg-white antialiased ">
<a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:text-heading focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-card">{{ __('commun.nav.aller_contenu') }}</a>

<header class="absolute inset-x-0 top-0 z-40" data-navbar>
  <nav class="max-w-page mx-auto pr-4 pl-0">
    <div class="flex items-center justify-between gap-3 py-3">
      <a class="shrink-0" href="{{ route('public.home') }}" aria-label="{{ __('commun.marque.accueil_aria') }}" @if(request()->routeIs('public.home')) aria-current="page" @endif>
        <img src="{{ asset('assets/images/logo.png') }}" alt="{{ __('commun.marque.logo_alt') }}" width="171" height="60" class="h-[52px] w-auto" loading="eager">
      </a>
      <div id="navbarCollapse" class="hidden xl:flex xl:items-center xl:static absolute left-0 right-0 top-full mx-4 xl:mx-0 bg-white xl:bg-transparent rounded-xl xl:rounded-none shadow-cardlg xl:shadow-none p-3 xl:p-0" data-collapse-panel>
        <ul class="flex flex-col xl:flex-row xl:items-center xl:mx-auto">
          <li><a class="block px-4 py-2 xl:py-6 text-[15px] font-medium text-{{ request()->routeIs('public.why') ? 'primary' : 'heading' }} hover:text-primary transition-colors" href="{{ route('public.why') }}" @if(request()->routeIs('public.why')) aria-current="page" @endif>{{ __('commun.nav.pourquoi') }}</a></li>
          <li><a class="block px-4 py-2 xl:py-6 text-[15px] font-medium text-{{ request()->routeIs('public.features') ? 'primary' : 'heading' }} hover:text-primary transition-colors" href="{{ route('public.features') }}" @if(request()->routeIs('public.features')) aria-current="page" @endif>{{ __('commun.nav.fonctionnalites') }}</a></li>
          <li><a class="block px-4 py-2 xl:py-6 text-[15px] font-medium text-{{ request()->routeIs('public.how') ? 'primary' : 'heading' }} hover:text-primary transition-colors" href="{{ route('public.how') }}" @if(request()->routeIs('public.how')) aria-current="page" @endif>{{ __('commun.nav.comment') }}</a></li>
          <li><a class="block px-4 py-2 xl:py-6 text-[15px] font-medium text-{{ request()->routeIs('public.tutorials') ? 'primary' : 'heading' }} hover:text-primary transition-colors" href="{{ route('public.tutorials') }}" @if(request()->routeIs('public.tutorials')) aria-current="page" @endif>{{ __('commun.nav.tutoriels') }}</a></li>
          <li><a class="block px-4 py-2 xl:py-6 text-[15px] font-medium text-{{ request()->routeIs('public.facts') ? 'primary' : 'heading' }} hover:text-primary transition-colors" href="{{ route('public.facts') }}" @if(request()->routeIs('public.facts')) aria-current="page" @endif>{{ __('commun.nav.actualites') }}</a></li>
          <li class="xl:hidden border-t border-line mt-2 pt-2">
            @auth
              <a class="block px-4 py-2 text-[15px] font-medium text-primary" href="{{ route('citizen.dashboard') }}"><i class="bi bi-person-circle me-2"></i>{{ __('commun.compte.mon_compte') }}</a>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full text-left px-4 py-2 text-[15px] font-medium text-heading hover:text-primary transition-colors">
                  <i class="bi bi-box-arrow-right me-2"></i>{{ __('commun.compte.deconnexion') }}
                </button>
              </form>
            @else
              <a class="block px-4 py-2 text-[15px] font-medium text-primary" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif><i class="bi bi-person-circle me-2"></i>{{ __('commun.compte.connexion') }}</a>
            @endauth
          </li>
        </ul>
      </div>
      <div class="flex items-center gap-2">
        {{-- Le sélecteur de langue, à gauche du bouton de compte : c'est le
             réglage le moins souvent employé des deux, et le placer en premier
             éloignerait de la marge le geste le plus fréquent. --}}
        <x-selecteur-langue />
        {{--
          | CONNECTÉ : le bouton devient « Mon Compte » + une déconnexion.
          |
          | Le même emplacement, le même style, la même icône : c'est l'INTITULÉ
          | et la destination qui changent, jamais la position. Proposer
          | « Connexion » à quelqu'un qui l'est déjà est le signe le plus sûr
          | qu'un site ne sait pas qui le consulte.
          |
          | La déconnexion est un formulaire POST et non un lien : un GET
          | déconnectant se déclenche par un préchargement de navigateur ou une
          | image distante, et c'est une faille CSRF de manuel.
        --}}
        @auth
          <a class="inline-flex items-center gap-1.5 rounded-lg bg-dark text-white text-sm font-semibold px-3.5 py-2 hover:bg-black transition-colors" href="{{ route('citizen.dashboard') }}">
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/><path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1"/></svg>
            <span class="hidden sm:inline-block">{{ __('commun.compte.mon_compte') }}</span>
          </a>
          <form method="POST" action="{{ route('logout') }}" class="hidden xl:block">
            @csrf
            <button type="submit" class="inline-flex items-center rounded-lg border border-line text-heading text-sm font-semibold px-3.5 py-2 hover:text-primary transition-colors" aria-label="{{ __('commun.compte.se_deconnecter') }}" title="{{ __('commun.compte.se_deconnecter') }}">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z"/><path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z"/></svg>
            </button>
          </form>
        @else
          <a class="inline-flex items-center gap-1.5 rounded-lg bg-dark text-white text-sm font-semibold px-3.5 py-2 hover:bg-black transition-colors" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>
            <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/><path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1"/></svg>
            <span class="hidden sm:inline-block">{{ __('commun.compte.connexion') }}</span>
          </a>
        @endauth
        <button class="xl:hidden navbar-toggler" type="button" data-collapse-toggle aria-controls="navbarCollapse" aria-expanded="false" aria-label="{{ __('commun.nav.ouvrir_menu') }}">
          <span class="navbar-toggler-animation"><span></span><span></span><span></span></span>
        </button>
      </div>
    </div>
  </nav>
</header>

{{-- Campagnes d'affichage. Alimentées par CampagnesComposer, elles ne
     coûtent aucune requête quand aucune n'est diffusable. --}}
<x-campagnes :campagnes="$campagnes ?? null" />

<main id="contenu" class="overflow-hidden">

{{ $slot }}

</main>

<footer class="pb-6">
  <div class="max-w-page mx-auto px-4">
    <div class="bg-dark text-[#c4c5c7] relative rounded-none p-6 sm:p-12 mb-3 sm:mb-8">
      <div class="max-w-3xl mx-auto text-center">
        <a class="inline-block" href="{{ route('public.home') }}" @if(request()->routeIs('public.home')) aria-current="page" @endif>
          <img src="{{ asset('assets/images/logo-light.png') }}" alt="{{ __('commun.marque.logo_alt') }}" width="171" height="60" class="h-[60px] w-auto mx-auto" loading="lazy">
        </a>
        <p class="mt-6 mb-3 leading-relaxed">{{ __('commun.marque.description') }}</p>
        <ul class="flex flex-wrap justify-center gap-1 mt-6">
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.why') }}" @if(request()->routeIs('public.why')) aria-current="page" @endif>{{ __('commun.nav.pourquoi') }}</a></li>
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.features') }}" @if(request()->routeIs('public.features')) aria-current="page" @endif>{{ __('commun.nav.fonctionnalites') }}</a></li>
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.tutorials') }}" @if(request()->routeIs('public.tutorials')) aria-current="page" @endif>{{ __('commun.pied.apprentissage') }}</a></li>
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.facts') }}" @if(request()->routeIs('public.facts')) aria-current="page" @endif>{{ __('commun.nav.actualites') }}</a></li>
          {{-- AJOUT au prototype, consigné : la page existe, porte le mot du
               fondateur, et AUCUN lien du site n'y menait. --}}
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.team') }}" @if(request()->routeIs('public.team')) aria-current="page" @endif>{{ __('commun.pied.equipe') }}</a></li>
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.referral') }}" @if(request()->routeIs('public.referral')) aria-current="page" @endif>{{ __('commun.pied.parrainage') }}</a></li>
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.corrections') }}" @if(request()->routeIs('public.corrections')) aria-current="page" @endif>{{ __('commun.pied.corrections') }}</a></li>
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.methodology') }}" @if(request()->routeIs('public.methodology')) aria-current="page" @endif>{{ __('commun.pied.methodologie') }}</a></li>
          {{-- Juste après la méthodologie, dont elle est le pendant : celle-là
               dit COMMENT une affirmation devient un verdict, celle-ci dit SUR
               QUOI. Une page de transparence qu'aucun lien n'atteint ne
               transparaît rien. --}}
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.sources') }}" @if(request()->routeIs('public.sources')) aria-current="page" @endif>{{ __('commun.pied.nos_sources') }}</a></li>
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.funding') }}" @if(request()->routeIs('public.funding')) aria-current="page" @endif>{{ __('commun.pied.financement') }}</a></li>
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.faq') }}" @if(request()->routeIs('public.faq')) aria-current="page" @endif>{{ __('commun.pied.faq') }}</a></li>
          <li><a class="px-3 py-1 text-[15px] hover:text-white transition-colors" href="{{ route('public.contact') }}" @if(request()->routeIs('public.contact')) aria-current="page" @endif>{{ __('commun.pied.contact') }}</a></li>
        </ul>
        <ul class="flex justify-center gap-2 mt-6">
          <li><a class="inline-flex items-center justify-center w-8 h-8 rounded-md text-white" style="background:#c13584" href="https://www.instagram.com/verif_all" target="_blank" rel="noopener" aria-label="{{ __('commun.reseaux.instagram') }}">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334"/></svg></a></li>
          <li><a class="inline-flex items-center justify-center w-8 h-8 rounded-md text-white" style="background:#5d82d1" href="https://www.facebook.com/share/17nJws5pMX/" target="_blank" rel="noopener" aria-label="{{ __('commun.reseaux.facebook') }}">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.94 16v-7.32h2.46l.37-2.86H8.94V4c0-.83.23-1.39 1.42-1.39h1.51V.06C11.61.03 10.71 0 9.66 0 7.47 0 5.96 1.34 5.96 3.8v2.02H3.5v2.86h2.46V16z"/></svg></a></li>
          <li><a class="inline-flex items-center justify-center w-8 h-8 rounded-md text-white" style="background:#238cc8" href="https://www.linkedin.com/company/v-rif-all/" target="_blank" rel="noopener" aria-label="{{ __('commun.reseaux.linkedin') }}">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z"/></svg></a></li>
        </ul>
        {{--
          | LES TROIS LIENS LÉGAUX MENAIENT NULLE PART.
          |
          | Ils portaient `href="#"` et un `data-todo` hérité du prototype,
          | alors que les trois pages existent et sont routées. Sur un pied de
          | page, ce sont précisément les liens qu'on suit quand on veut savoir
          | à qui l'on a affaire, et un lien mort y vaut pire qu'une absence :
          | il donne l'impression qu'on se dérobe.
        --}}
        <div class="mt-6 text-sm">© 2026 Vérif'All, {{ __('commun.pied.droits') }} ·
          <a href="{{ route('public.legal') }}" class="underline hover:text-white" @if(request()->routeIs('public.legal')) aria-current="page" @endif>{{ __('commun.pied.mentions') }}</a> ·
          <a href="{{ route('public.terms') }}" class="underline hover:text-white" @if(request()->routeIs('public.terms')) aria-current="page" @endif>{{ __('commun.pied.conditions') }}</a> ·
          <a href="{{ route('public.cookies') }}" class="underline hover:text-white" @if(request()->routeIs('public.cookies')) aria-current="page" @endif>{{ __('commun.pied.politique_cookies') }}</a> ·
          <a href="{{ route('public.privacy') }}" class="underline hover:text-white" @if(request()->routeIs('public.privacy')) aria-current="page" @endif>{{ __('commun.pied.politique_confidentialite') }}</a>
        </div>
      </div>
    </div>
  </div>
</footer>

<div id="pwa-popup" class="fixed bottom-4 right-4 z-50 hidden max-w-[340px] w-[calc(100%-2rem)] bg-white rounded-2xl shadow-cardlg p-4 items-center gap-3" role="dialog" aria-label="{{ __('commun.pwa.dialogue_aria') }}">
  <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-primary-soft shrink-0" aria-hidden="true">
    <svg width="24" height="24" viewBox="0 0 40 40" fill="none"><path d="M8 10 20 32 32 10h-7l-5 9-5-9z" fill="#09B850"/></svg>
  </span>
  <div class="flex-1 min-w-0">
    <p class="font-display font-bold text-heading leading-tight">{{ __('commun.pwa.installer_verifall') }}</p>
      </div>
  <button type="button" class="text-sm font-semibold text-heading bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1.5" data-pwa-later>{{ __('commun.pwa.plus_tard') }}</button>
  <button type="button" class="text-sm font-semibold text-white bg-primary hover:bg-primary-dark rounded-full px-3.5 py-1.5" data-pwa-install>{{ __('commun.pwa.installer') }}</button>
</div>

<div id="cookie-banner" class="fixed bottom-4 left-4 z-[70] hidden max-w-md w-[calc(100%-2rem)] bg-white rounded-2xl shadow-cardlg border border-line p-5" role="dialog" aria-modal="false" aria-labelledby="cookie-title">
  <div class="flex items-start gap-3">
    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-primary-soft shrink-0" aria-hidden="true">
      <svg width="20" height="20" viewBox="0 0 16 16" fill="#09B850"><path d="M8 0a7.96 7.96 0 0 0-4.075 1.114q-.245.14-.478.298C2.24 2.234 1.23 3.36.633 4.71A7.96 7.96 0 0 0 0 8c0 4.418 3.582 8 8 8s8-3.582 8-8c0-.244-.011-.485-.032-.723-.512.26-1.107.34-1.7.183a2.5 2.5 0 0 1-1.755-1.756 2.5 2.5 0 0 1-2.915-2.915A2.5 2.5 0 0 1 7.843.037 8 8 0 0 0 8 0M5.5 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2m5 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0m-5.75.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0"/></svg>
    </span>
    <div>
      <h6 id="cookie-title" class="font-display font-bold text-heading mb-1">{{ __('commun.cookies_banniere.titre') }}</h6>
      <p class="text-sm text-body/85 mb-0">{{ __('commun.cookies_banniere.texte') }}</p>
    </div>
  </div>
  <div id="cookie-prefs" class="hidden mt-4 pt-4 border-t border-line space-y-2">
    <label class="flex items-center justify-between gap-3 text-sm">
      <span class="font-medium text-heading">{{ __('commun.cookies_banniere.essentiels') }} <span class="text-body/60 font-normal">{{ __('commun.cookies_banniere.toujours_actifs') }}</span></span>
      <input type="checkbox" class="form-check-input" checked disabled aria-label="{{ __('commun.cookies_banniere.essentiels_aria') }}">
    </label>
    <label class="flex items-center justify-between gap-3 text-sm cursor-pointer">
      <span class="font-medium text-heading">{{ __('commun.cookies_banniere.mesure_audience') }}</span>
      <input type="checkbox" class="form-check-input" id="cookie-analytics" aria-label="{{ __('commun.cookies_banniere.autoriser_mesure_aria') }}">
    </label>
  </div>
  <div class="flex flex-wrap justify-end gap-2 mt-4">
    <button type="button" data-cookie-custom class="text-sm font-semibold text-heading rounded-full border border-line px-4 py-2 hover:bg-gray-50">{{ __('commun.cookies_banniere.personnaliser') }}</button>
    <button type="button" data-cookie-cancel class="text-sm font-semibold text-heading rounded-full bg-gray-100 px-4 py-2 hover:bg-gray-200">{{ __('commun.cookies_banniere.annuler') }}</button>
    <button type="button" data-cookie-accept class="text-sm font-semibold text-white rounded-full bg-primary px-4 py-2 hover:bg-primary-dark">{{ __('commun.cookies_banniere.accepter') }}</button>
  </div>
</div>
<script src="{{ \App\Support\Assets\ActifVersionne::url('assets/js/verifall.js') }}" defer></script>
<x-toast variant="citizen" />
<script src="{{ \App\Support\Assets\ActifVersionne::url('assets/js/verifall-app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
