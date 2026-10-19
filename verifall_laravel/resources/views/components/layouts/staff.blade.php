@php
  /*
   | La console premium (administration et espace mentor) — arbitrage n° 176.
   | Elle remplace l'ancien gabarit staff : les 59 écrans qui l'appellent
   | (`#[Layout('components.layouts.staff')]`) en héritent sans changer.
   |
   | Le membre connecté est lu une fois pour toute la coquille : savoir sous
   | quel compte on agit rend le journal d'audit lisible par qui l'alimente.
   */
  $membre = auth('staff')->user();
  $qualite = $membre?->getRoleNames()->first() ?? 'Équipe';
  $estMentorSeul = $membre !== null && $membre->hasRole(\App\Enums\UserRole::Mentor->value) && ! $membre->hasRole(\App\Enums\UserRole::Admin->value);
  $nomComplet = trim(($membre?->first_name ?? '').' '.($membre?->last_name ?? '')) ?: ($membre?->name ?? 'Mon compte');
  $initialesStaff = \Illuminate\Support\Str::of($nomComplet)->explode(' ')->filter()->take(2)
      ->map(fn (string $mot): string => mb_strtoupper(mb_substr($mot, 0, 1)))->implode('');

  $menuDuBackOffice = \App\Support\Rbac\MenuDuBackOffice::pour($membre ?? auth()->user());

  // Les actions rapides : celles dont la route est ouverte à la personne.
  $actionsRapides = $membre === null ? [] : collect(config('console.actions_rapides', []))
      ->filter(fn (array $a): bool => \Illuminate\Support\Facades\Route::has($a[0]) && \App\Support\Rbac\MenuDuBackOffice::autorise($membre, $a[0]))
      ->take((int) config('console.actions_rapides_max', 8))
      ->values();

  // La palette Ctrl K : les écrans du menu, à plat.
  $ecransDeLaPalette = collect($menuDuBackOffice)->flatMap(fn (array $entrees, string $groupe) => collect($entrees)
      ->map(fn (array $e): array => ['url' => route($e['route']), 'libelle' => $e['libelle'], 'groupe' => $groupe ?: 'Accueil']))
      ->unique('url')->values();

  // Le site est-il fermé au public ? Lu dans les réglages, déjà en cache.
  // Le dire sur CHAQUE écran : on oublie vite qu'on a fermé, et le public, lui,
  // attend devant la porte.
  $maintenance = app(\App\Core\Application\Services\ModeMaintenance::class);
  $siteFerme = $membre !== null && $maintenance->estActif();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0E2A1E">
    <title>{{ $title ?? "Vérif'All" }}</title>
    <meta name="description" content="{{ $description ?? "Vérif'All — la boussole contre la désinformation numérique." }}">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    {{-- Thème avant le premier rendu : clair par défaut, sombre seulement sur
         choix explicite (ou « comme le système »). Pas de flash. --}}
    <script>
      (function () {
        var t = null;
        try { t = localStorage.getItem('vf-theme'); } catch (e) {}
        if (t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
      })();
    </script>

    {{-- Les écrans pas encore réhabillés gardent leur feuille d'origine pendant
         la transposition (IMPLEMENTATION_PLAN, phase 5). La console premium
         passe EN DERNIER : la coquille l'emporte. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/citizen.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/staff.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/quill.snow.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/staff-shell.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/verifall-app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/premium.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/staff-premium.css') }}">
    @stack('styles')
</head>
<body class="console min-h-screen bg-papier dark:bg-nuit text-encre dark:text-white font-sans antialiased" @if($membre !== null && $membre->tour_done_at === null) data-tour-auto="staff" @endif>
<a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[80] btn-primaire">Aller au contenu</a>

{{-- ═══════════════════════════════════════ Colonne de navigation --}}
<aside id="colonne" data-tour="sidebar" class="fixed inset-y-0 left-0 z-40 w-[264px] -translate-x-full lg:translate-x-0 transition-transform bg-foret text-white flex flex-col" aria-label="Navigation de la console">
  <div class="flex items-center gap-2.5 h-16 px-5 border-b border-white/10 shrink-0">
    <a href="{{ route('staff.dashboard') }}" class="flex items-center gap-2.5" @if (request()->routeIs('staff.dashboard')) aria-current="page" @endif>
      <span class="inline-flex w-8 h-8 rounded-ctrl bg-brand items-center justify-center" aria-hidden="true"><x-lucide name="compass" class="w-[18px] h-[18px] text-foret" /></span>
      <span class="leading-tight">
        <span class="block font-titre font-semibold text-[16px]">Vérif'All</span>
        <span class="block text-xs text-white/55">{{ $estMentorSeul ? 'Espace mentor' : "Console d'administration" }}</span>
      </span>
    </a>
  </div>

  <nav class="flex-1 overflow-y-auto defile px-3 pb-6" aria-label="Navigation principale">
    {{-- La visibilité de chaque lien est LUE sur le `can:` de sa route
         (`MenuDuBackOffice`) : le menu montre ce que le serveur autorise. --}}
    @foreach ($menuDuBackOffice as $groupe => $entrees)
      @if ($groupe === '')
        <ul class="pt-3 space-y-0.5">
          @foreach ($entrees as $entree)
            @php($actif = request()->routeIs($entree['route']))
            <li><a href="{{ route($entree['route']) }}" class="nav-lien" @if ($actif) aria-current="page" @endif><x-lucide :name="$entree['icone']" /><span class="flex-1 truncate">{{ $entree['libelle'] }}</span></a></li>
          @endforeach
        </ul>
      @else
        {{-- Le groupe de la page courante s'ouvre seul ; les autres gardent
             l'état choisi par la personne (mémorisé par console.js). --}}
        @php($ouvert = collect($entrees)->contains(fn ($e) => request()->routeIs($e['route'])))
        <details class="groupe-nav" data-groupe="{{ \Illuminate\Support\Str::slug($groupe) }}" @if ($ouvert) open data-courant @endif>
          <summary class="nav-groupe list-none cursor-pointer"><span>{{ $groupe }}</span><x-lucide name="chevron-down" class="w-3.5 h-3.5 transition-transform chevron-groupe" /></summary>
          <ul class="space-y-0.5">
            @foreach ($entrees as $entree)
              @php($actif = request()->routeIs($entree['route']))
              <li><a href="{{ route($entree['route']) }}" class="nav-lien" @if ($actif) aria-current="page" @endif><x-lucide :name="$entree['icone']" /><span class="flex-1 truncate">{{ $entree['libelle'] }}</span></a></li>
            @endforeach
          </ul>
        </details>
      @endif
    @endforeach
  </nav>

  <div class="p-3 border-t border-white/10 shrink-0">
    <a href="{{ route('staff.settings') }}" class="flex items-center gap-3 p-2 rounded-ctrl hover:bg-white/[.06]">
      <span class="avatar w-8 h-8">{{ $initialesStaff }}</span>
      <span class="min-w-0 leading-tight">
        <span class="block text-sm font-medium truncate">{{ $nomComplet }}</span>
        <span class="block text-xs text-white/55 truncate">{{ $qualite }}</span>
      </span>
    </a>
  </div>
</aside>
<div class="voile hidden lg:hidden" data-colonne-fermer aria-hidden="true"></div>

{{-- ═══════════════════════════════════════ Zone principale --}}
<div class="lg:pl-[264px] flex flex-col min-h-screen">
  <header class="sticky top-0 z-30 h-16 bg-papier/90 dark:bg-nuit/90 backdrop-blur border-b filet">
    <div class="h-full flex items-center gap-2 px-4 sm:px-6">
      <button type="button" class="btn-icone lg:hidden" data-colonne-ouvrir aria-label="Ouvrir la navigation" aria-expanded="false" aria-controls="colonne"><x-lucide name="menu" /></button>

      <button type="button" class="hidden sm:flex items-center gap-2 h-9 w-full max-w-md px-3 rounded-ctrl border filet bg-white dark:bg-nuit-carte text-sm texte-3 hover:border-brand/50 transition-colors" data-palette-ouvrir>
        <x-lucide name="search" />
        <span class="flex-1 text-left">Aller à un écran…</span>
        <span class="kbd">Ctrl K</span>
      </button>

      <div class="ml-auto flex items-center gap-1">
        <button type="button" class="btn-icone sm:hidden" data-palette-ouvrir aria-label="Rechercher un écran"><x-lucide name="search" /></button>

        @if ($actionsRapides->isNotEmpty())
          <div class="relative" data-menu-console>
            <button type="button" data-tour="creer" class="btn-primaire !h-9 !px-2.5 lg:!px-3.5 mr-1" data-menu-bouton aria-haspopup="menu" aria-expanded="false" title="Actions rapides (A)">
              <x-lucide name="zap" /><span class="hidden lg:inline">Actions rapides</span>
            </button>
            <div class="menu right-0 mt-2 w-[min(92vw,420px)] hidden !p-2" role="menu" data-menu-panneau data-actions-rapides>
              <p class="px-2 pt-1 pb-2 text-xs texte-3">Actions rapides</p>
              <div class="grid sm:grid-cols-2 gap-1">
                @foreach ($actionsRapides as [$route, $icone, $libelle, $aide])
                  <a href="{{ route($route) }}" role="menuitem" class="flex items-start gap-3 rounded-ctrl p-2.5 hover:bg-papier dark:hover:bg-nuit focus:bg-papier dark:focus:bg-nuit focus:outline-none">
                    <span class="inline-flex w-8 h-8 shrink-0 items-center justify-center rounded-ctrl bg-brand-50 text-brand-700 dark:bg-brand/10 dark:text-brand"><x-lucide :name="$icone" /></span>
                    <span class="min-w-0"><span class="block text-sm font-medium leading-snug">{{ $libelle }}</span><span class="block text-xs texte-3 truncate">{{ $aide }}</span></span>
                  </a>
                @endforeach
              </div>
            </div>
          </div>
        @endif

        <livewire:staff.cloche />

        <button type="button" class="btn-icone" data-theme-basculer aria-label="Passer en thème sombre"><x-lucide name="moon" class="w-[18px] h-[18px] block dark:hidden" /><x-lucide name="sun" class="w-[18px] h-[18px] hidden dark:block" /></button>
        <button type="button" class="btn-icone hidden sm:inline-flex" data-plein-ecran aria-label="Plein écran" aria-pressed="false"><x-lucide name="maximize" /></button>

        <div class="relative ml-1" data-menu-console>
          <button type="button" class="flex items-center gap-2 h-10 pl-1 pr-1 sm:pr-2 rounded-full hover:bg-white dark:hover:bg-nuit-carte transition-colors" data-menu-bouton aria-haspopup="menu" aria-expanded="false" aria-label="Menu du profil">
            <span class="relative"><span class="avatar w-8 h-8">{{ $initialesStaff }}</span><span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-bon ring-2 ring-papier dark:ring-nuit" aria-hidden="true"></span></span>
            <span class="hidden md:block text-left leading-tight">
              <span class="block text-[13px] font-medium">{{ \Illuminate\Support\Str::of($nomComplet)->explode(' ')->first() }}</span>
              <span class="block text-[11px] texte-3">{{ $qualite }}</span>
            </span>
            <x-lucide name="chevron-down" class="hidden md:block w-3.5 h-3.5 texte-3" />
          </button>
          <div class="menu right-0 mt-2 w-[290px] hidden" role="menu" data-menu-panneau>
            <div class="flex items-center gap-3 px-3 pt-2 pb-3">
              <span class="avatar w-11 h-11 !text-sm">{{ $initialesStaff }}</span>
              <div class="min-w-0"><p class="font-medium truncate">{{ $nomComplet }}</p><p class="text-xs texte-3 truncate">{{ $membre?->email }}</p></div>
            </div>
            <div class="my-1 border-t filet"></div>
            <a href="{{ route('staff.settings') }}" class="menu-item" role="menuitem"><x-lucide name="circle-user-round" /><span class="flex-1">Mon profil</span></a>
            @if (\App\Support\Rbac\MenuDuBackOffice::autorise($membre, 'staff.audit'))
              <a href="{{ route('staff.audit') }}" class="menu-item" role="menuitem" data-tour="audit"><x-lucide name="scroll" /><span class="flex-1">Journal d'audit</span></a>
            @endif
            <button type="button" class="menu-item" role="menuitem" data-theme-basculer><x-lucide name="sun-moon" /><span class="flex-1 text-left">Changer de thème</span></button>
            <button type="button" class="menu-item" role="menuitem" data-palette-ouvrir><x-lucide name="command" /><span class="flex-1 text-left">Aller à un écran</span><span class="kbd">Ctrl K</span></button>
            <div class="my-1 border-t filet"></div>
            <button type="button" class="menu-item" role="menuitem" data-verrouiller><x-lucide name="lock" /><span class="flex-1 text-left">Verrouiller la session</span><span class="kbd">Ctrl L</span></button>
            <form method="POST" action="{{ route('staff.logout') }}">
              @csrf
              <button type="submit" class="menu-item !text-red-700 dark:!text-red-400" role="menuitem"><x-lucide name="log-out" class="w-4 h-4 !text-current" /><span class="flex-1 text-left">Se déconnecter</span></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </header>

  @if ($siteFerme)
    <div data-bandeau-maintenance role="status" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 sm:px-6 py-2 text-[13px] bg-amber-50 text-amber-900 border-b border-amber-200 dark:bg-amber-950/60 dark:text-amber-100 dark:border-amber-900/60">
      <span class="relative flex w-2 h-2 shrink-0" aria-hidden="true"><span class="absolute inset-0 rounded-full bg-amber-500 animate-ping opacity-60"></span><span class="relative w-2 h-2 rounded-full bg-amber-500"></span></span>
      <span class="font-medium">Site fermé au public</span>
      <span class="opacity-80">
        depuis {{ $maintenance->debut()?->locale('fr')->diffForHumans() ?? 'quelques instants' }}{{ $maintenance->enRetard() ? ' — l’heure annoncée est dépassée' : ($maintenance->finAnnoncee() ? ' — réouverture annoncée à '.$maintenance->finAnnoncee()->format('H:i') : '') }}. Seule l’équipe connectée voit la plateforme.
      </span>
      @if (\App\Support\Rbac\MenuDuBackOffice::autorise($membre, 'staff.maintenance-mode') && ! request()->routeIs('staff.maintenance-mode'))
        <a href="{{ route('staff.maintenance-mode') }}" class="ml-auto inline-flex items-center gap-1 font-medium underline underline-offset-2 hover:no-underline"><x-lucide name="construction" class="w-3.5 h-3.5" />Gérer la maintenance</a>
      @endif
    </div>
  @endif

  <main id="contenu" class="console-contenu flex-1 w-full max-w-[1480px] px-4 sm:px-6 py-6" tabindex="-1">
    {{ $slot }}
  </main>
</div>

{{-- Palette : les écrans ouverts à la personne, filtrés au clavier. --}}
<div id="palette" class="hidden" aria-hidden="true">
  <div class="voile !z-[55]" data-palette-fermer></div>
  <div class="fixed inset-x-0 top-[12vh] z-[56] px-4">
    <div class="carte shadow-flottant max-w-xl mx-auto overflow-hidden" role="dialog" aria-modal="true" aria-label="Aller à un écran">
      <div class="flex items-center gap-3 px-4 border-b filet"><x-lucide name="search" class="w-[18px] h-[18px] texte-3" /><input class="flex-1 h-14 bg-transparent text-base focus:outline-none" placeholder="Aller à un écran…" aria-label="Rechercher un écran" data-palette-champ><span class="kbd">Échap</span></div>
      <ul class="max-h-[50vh] overflow-y-auto py-2" role="listbox" data-palette-liste></ul>
    </div>
  </div>
</div>
{{-- Verrouillage (arbitrage n° 180) : POST, déclenché par console.js. --}}
<form method="POST" action="{{ route('staff.lock') }}" id="form-verrou" class="hidden">@csrf<input type="hidden" name="raison" value="manuel"></form>
<script type="application/json" id="palette-ecrans">@json($ecransDeLaPalette)</script>

<script src="{{ asset('assets/js/citizen.js') }}" defer></script>
<x-toast variant="staff" />
<script src="{{ asset('assets/js/verifall-app.js') }}" defer></script>
<script src="{{ asset('assets/js/guided-tour.js') }}" defer></script>
<script src="{{ asset('assets/js/console/console.js') }}" defer></script>
@stack('scripts')
</body>
</html>
