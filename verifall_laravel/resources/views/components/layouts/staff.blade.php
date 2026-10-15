@php
  /*
   | Le membre de l'équipe connecté, lu une fois pour toute la barre.
   |
   | Ces valeurs étaient recopiées du prototype et figées : chaque compte staff
   | voyait le nom et la qualité de René. Sur un back-office, savoir sous quel
   | compte on agit n'est pas un confort — c'est ce qui rend le journal d'audit
   | lisible par celui qui l'alimente.
   */
  $membre = auth('staff')->user();
  $qualite = $membre?->getRoleNames()->first() ?? 'Équipe';
  $initialesStaff = \Illuminate\Support\Str::of(trim(($membre?->first_name ?? '').' '.($membre?->last_name ?? '')) ?: ($membre?->name ?? '?'))
      ->explode(' ')
      ->filter()
      ->take(2)
      ->map(fn (string $mot): string => mb_strtoupper(mb_substr($mot, 0, 1)))
      ->implode('');
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? "Vérif'All" }}</title>
    <meta name="description" content="{{ $description ?? "Vérif'All — la boussole contre la désinformation numérique." }}">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">

    {{-- Ordre de cascade IMPOSÉ (DESIGN_SYSTEM §Tokens) : tailwind.css EN DERNIER.
         Toute inversion casse la spécificité et donc le rendu du prototype. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/citizen.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/staff.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/quill.snow.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tailwind.css') }}">
    {{-- Shell staff : bloc <style> que le prototype répète en ligne dans ses 59
         pages admin et mentor. Même place dans la cascade — juste après
         tailwind.css — donc même rendu. Sans lui, les rubriques repliables de
         la sidebar sortent à 24 px au lieu de 42. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/staff-shell.css') }}">
    {{-- Couche applicative : additive, jamais correctrice du design system. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/verifall-app.css') }}">
    @stack('styles')
</head>
<body class="font-sans text-slate2 bg-canvas antialiased" @if(auth('staff')->check() && auth('staff')->user()->tour_done_at === null) data-tour-auto="staff"@endif>
<header class="fixed top-0 inset-x-0 z-40 h-[72px] bg-surface border-b border-line">
  <div class="h-full flex items-center gap-3 px-4 lg:px-6">
    <button type="button" class="lg:hidden inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-canvas text-ink" data-drawer-open aria-label="Ouvrir le menu" aria-expanded="false"><span class="material-symbols-rounded " aria-hidden="true">menu</span></button>
    <a href="{{ route('staff.dashboard') }}" @if(request()->routeIs('staff.dashboard')) aria-current="page" @endif><img src="{{ asset('assets/images/logo.png') }}" alt="Vérif'All" class="h-10 w-auto" width="171" height="60"></a>
    <span class="rounded-full text-white text-[10px] font-bold tracking-widest uppercase px-2.5 py-1" style="background:var(--st-brand)">Admin</span>
    <div class="hidden md:block relative flex-1 max-w-md ml-4">
      <label class="sr-only" for="st-search">Rechercher</label>
      <span class="material-symbols-rounded absolute left-3 top-1/2 -translate-y-1/2 text-slate2 !text-[19px]" aria-hidden="true">search</span>
      <input id="st-search" type="search" placeholder="Rechercher un module, un utilisateur, un cours…" class="w-full bg-canvas border border-line rounded-xl pl-10 pr-3 py-2.5 text-sm outline-none focus:border-[#059669]">
    </div>
    <div class="flex-1 md:hidden"></div>
    
    <div class="relative hidden sm:block" data-dropdown>
      <button data-tour="creer" type="button" class="inline-flex items-center gap-1.5 rounded-xl bg-[#059669] text-white text-sm font-semibold px-4 py-2.5 hover:opacity-90 min-h-[44px]" data-dropdown-toggle aria-expanded="false" aria-haspopup="true"><span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span> Créer <span class="material-symbols-rounded !text-[17px]" aria-hidden="true">expand_more</span></button>
      <div class="hidden absolute right-0 top-full mt-2 w-56 bg-surface border border-line rounded-2xl shadow-cardlg py-2 z-30" data-dropdown-menu>
        <a class="user-menu-link" href="{{ route('staff.courses') }}"><span class="material-symbols-rounded !text-[19px]" aria-hidden="true">school</span> Nouveau cours</a>
        <a class="user-menu-link" href="{{ route('staff.posts') }}"><span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit_note</span> Nouvel article</a>
        <a class="user-menu-link" href="{{ route('staff.content-reports') }}"><span class="material-symbols-rounded !text-[19px]" aria-hidden="true">report</span> Nouveau signalement</a>
        <a class="user-menu-link" href="{{ route('staff.users') }}"><span class="material-symbols-rounded !text-[19px]" aria-hidden="true">person_add</span> Nouvel utilisateur</a>
      </div>
    </div>
<a data-tour="audit" href="{{ route('staff.audit') }}" class="hidden md:inline-flex st-icnbtn !w-11 !h-11" title="Journal d&#39;audit" aria-label="Journal d&#39;audit"><span class="material-symbols-rounded !text-[22px]" aria-hidden="true">history_edu</span></a>
    <button type="button" class="hidden sm:inline-flex st-icnbtn !w-11 !h-11" data-fullscreen title="Plein écran" aria-label="Basculer le plein écran"><span class="material-symbols-rounded !text-[22px]" aria-hidden="true">fullscreen</span></button>
    {{-- La cloche vient d'un composant : sa liste et sa pastille sortent de la
         table `notifications`. Le gabarit portait jusqu'ici QUATRE notifications
         écrites en dur et une pastille figée sur « 3 » — chaque membre de
         l'équipe, sur chaque page, voyait « Dossier KYC-2214 soumis · il y a
         2 h ». Une pastille toujours allumée désapprend à la regarder. --}}
    <livewire:staff.cloche />
    <div class="relative" data-dropdown>
      <button type="button" class="flex items-center gap-2" data-dropdown-toggle aria-expanded="false" aria-haspopup="true" aria-label="Menu utilisateur">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full text-white font-bold text-sm" style="background:var(--st-brand)">{{ $initialesStaff }}</span>
      </button>
      <div class="hidden absolute right-0 top-full mt-2 w-56 bg-surface border border-line rounded-2xl shadow-cardlg py-2 z-30" data-dropdown-menu>
        <div class="px-4 py-3 border-b border-line"><p class="font-display font-bold text-ink leading-tight mb-0">{{ $membre?->name ?? "Mon compte" }}</p><p class="text-xs text-slate2 mb-0">{{ $qualite }}</p></div>
        <a class="user-menu-link" href="{{ route('staff.settings') }}"><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">person</span> Mon profil</a><a class="user-menu-link" href="{{ route('staff.settings') }}"><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">settings</span> Paramètres</a>
        <div class="h-px bg-line my-1.5 mx-3" role="separator"></div>
        <form method="POST" action="{{ route('staff.logout') }}">
          @csrf
          <button type="submit" class="user-menu-link text-vfalse w-full text-left"><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">logout</span> Se déconnecter</button>
        </form>
      </div>
    </div>
  </div>
</header>
@php($menuDuBackOffice = \App\Support\Rbac\MenuDuBackOffice::pour(auth('staff')->user() ?? auth()->user()))

<aside data-tour="sidebar" id="sidebar" class="sidebar" data-drawer aria-label="Navigation Admin">
  <div class="flex flex-col h-full overflow-y-auto px-4 py-7">
    {{--
      | La barre latérale, rendue depuis `MenuDuBackOffice`.
      |
      | Elle affichait auparavant **56 liens à tout le monde** : quatre étaient
      | conditionnés, les cinquante-deux autres promettaient à un mentor des
      | écrans que son rôle lui refuse — le clic répondait 403. Un menu qui
      | promet ce que le serveur interdit fait perdre confiance dans TOUT le
      | menu.
      |
      | La visibilité n'est pas écrite ici, ni dans la classe : elle est LUE sur
      | le middleware `can:` de chaque route, puis évaluée. Le menu montre donc
      | par construction ce que le serveur autorise, et changer la garde d'une
      | route change le menu sans que personne n'y pense.
      |
      | Conséquence directe : l'administrateur et le mentor ont chacun leur
      | barre, sans qu'aucune des deux n'ait été écrite séparément.
    --}}
    <nav class="flex-1">
      @foreach ($menuDuBackOffice as $groupe => $entrees)
        @if ($groupe === '')
          {{-- Sans intitulé : le tableau de bord, avant le premier groupe. --}}
          @foreach ($entrees as $entree)
            <a class="sidebar-link {{ request()->routeIs($entree['route']) ? 'is-active' : '' }}"
               href="{{ route($entree['route']) }}"
               @if (request()->routeIs($entree['route'])) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">{{ $entree['icone'] }}</span><span>{{ $entree['libelle'] }}</span></a>
          @endforeach
        @else
          {{-- Le groupe s'ouvre tout seul quand on est dedans : sans cela, on
               arrive sur une page dont l'entrée de menu est repliée, et l'on
               ne voit pas où l'on se trouve. --}}
          @php($ouvert = collect($entrees)->contains(fn ($e) => request()->routeIs($e['route'])))
          <details class="st-group" @if ($ouvert) open @endif>
            <summary class="sidebar-label flex items-center justify-between pr-2 select-none">
              <span>{{ $groupe }}</span><span class="material-symbols-rounded st-caret !text-[18px] opacity-60" aria-hidden="true">expand_more</span>
            </summary>
            <div class="st-sub">
              @foreach ($entrees as $entree)
                <a class="sidebar-link {{ request()->routeIs($entree['route']) ? 'is-active' : '' }}"
                   href="{{ route($entree['route']) }}"
                   @if (request()->routeIs($entree['route'])) aria-current="page" @endif><span class="material-symbols-rounded !text-[20px]" aria-hidden="true">{{ $entree['icone'] }}</span><span>{{ $entree['libelle'] }}</span></a>
              @endforeach
            </div>
          </details>
        @endif
      @endforeach
    </nav>
    <form method="POST" action="{{ route('staff.logout') }}" class="mt-4">
      @csrf
      <button type="submit" class="sidebar-link is-danger w-full"><span class="material-symbols-rounded !text-[21px]" aria-hidden="true">logout</span><span>Déconnexion</span></button>
    </form>
  </div>
</aside>
<div class="drawer-backdrop hidden lg:hidden" data-drawer-backdrop aria-hidden="true"></div>
<main id="contenu" class="app-main">

{{ $slot }}

</main>
<script src="{{ asset('assets/js/citizen.js') }}" defer></script>
<x-toast variant="staff" />
<script src="{{ asset('assets/js/verifall-app.js') }}" defer></script>
<script src="{{ asset('assets/js/guided-tour.js') }}" defer></script>
@stack('scripts')
</body>
</html>
