@props(['title' => null])
@php
  /*
   | Connexion à la console premium (arbitrage n° 176) — `connexion.html` du
   | prototype premium. À gauche, le registre des derniers verdicts PUBLIÉS
   | (lus dans `verified_facts`, mis en cache 10 min) ; à droite, le formulaire.
   | Le seul mouvement de la page : un verdict qui se pose, arrêté si les
   | mouvements sont réduits.
   */
  $verdicts = \Illuminate\Support\Facades\Cache::remember('console.connexion.registre', now()->addMinutes(10), fn () =>
      \App\Models\VerifiedFact::query()->where('is_published', true)->latest('updated_at')->limit(8)->get(['title', 'verdict', 'updated_at'])
          ->map(fn ($f): array => ['titre' => (string) $f->title, 'verdict' => $f->verdict?->value, 'libelle' => $f->verdict?->libelle(), 'quand' => $f->updated_at?->locale('fr')->diffForHumans()])
          ->all());
  $tons = ['false' => 'bg-critique text-white', 'misleading' => 'bg-alerte text-foret', 'true' => 'bg-bon text-white', 'unverifiable' => 'bg-white/20 text-white'];
  $icones = ['false' => 'circle-x', 'misleading' => 'triangle-alert', 'true' => 'circle-check', 'unverifiable' => 'circle-help'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? "Administration — Vérif'All" }}</title>
    {{-- Le back-office n'est jamais indexé (SEO_PERFORMANCE : staff disallow). --}}
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0E2A1E">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <script>
      (function () { var t = null; try { t = localStorage.getItem('vf-theme'); } catch (e) {}
        if (t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/premium.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/staff-premium.css') }}">
    @stack('styles')
</head>
<body class="min-h-screen bg-papier dark:bg-nuit text-encre dark:text-white font-sans antialiased">
  <a href="#formulaire" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 btn-primaire">Aller au formulaire</a>
  <div class="min-h-screen grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
    <aside class="relative overflow-hidden bg-foret text-white flex flex-col" aria-label="Derniers verdicts publiés">
      <div class="flex items-center gap-2.5 px-6 sm:px-10 h-16 lg:h-20">
        <span class="inline-flex w-8 h-8 rounded-ctrl bg-brand items-center justify-center" aria-hidden="true"><x-lucide name="compass" class="w-[18px] h-[18px] text-foret" /></span>
        <span class="font-titre font-semibold text-[17px]">Vérif'All</span>
      </div>
      <div class="hidden lg:flex flex-1 flex-col px-10 pb-10">
        <p class="font-titre font-semibold text-[40px] xl:text-[46px] leading-[1.05] tracking-tight max-w-[14ch] mt-6">Chaque rumeur reçoit sa réponse.</p>
        <p class="mt-4 text-white/70 max-w-[42ch] leading-relaxed">Les derniers verdicts publiés par l'équipe. Connectez-vous pour reprendre la file là où vous l'avez laissée.</p>
        @if ($verdicts !== [])
          <ol class="relative mt-10 flex-1 space-y-3 overflow-hidden" data-registre>
            @foreach ($verdicts as $i => $v)
              <li class="flex items-start gap-4 rounded-ctrl border border-white/10 bg-white/[.04] px-4 py-3.5 {{ $i >= 4 ? 'hidden' : '' }}" data-ligne>
                <div class="min-w-0 flex-1"><p class="text-[15px] leading-snug text-white/90">« {{ $v['titre'] }} »</p><p class="mt-1 text-xs text-white/45">{{ $v['quand'] }}</p></div>
                @if ($v['verdict'])
                  <span class="shrink-0 inline-flex items-center gap-1 h-7 px-2.5 rounded-full text-xs font-semibold {{ $tons[$v['verdict']] ?? 'bg-white/20 text-white' }}" data-tampon><x-lucide :name="$icones[$v['verdict']] ?? 'circle'" class="w-3.5 h-3.5" />{{ $v['libelle'] }}</span>
                @endif
              </li>
            @endforeach
          </ol>
        @else
          <div class="flex-1"></div>
        @endif
      </div>
      <div class="lg:hidden px-6 pb-5 -mt-1"><p class="font-titre text-xl font-semibold leading-snug">Chaque rumeur reçoit sa réponse.</p></div>
    </aside>

    <main id="formulaire" class="flex flex-col px-6 sm:px-10 py-10 lg:py-0" tabindex="-1">
      <div class="flex justify-end lg:h-20 lg:items-center -mt-4 lg:mt-0">
        <button type="button" class="btn-icone" data-theme-basculer aria-label="Changer de thème"><x-lucide name="moon" class="w-[18px] h-[18px] block dark:hidden" /><x-lucide name="sun" class="w-[18px] h-[18px] hidden dark:block" /></button>
      </div>
      <div class="flex-1 grid place-items-center"><div class="w-full max-w-[400px]">{{ $slot }}</div></div>
      <footer class="pt-8 lg:pb-8 text-xs texte-3 flex flex-wrap gap-x-5 gap-y-1">
        <span>Vous êtes citoyen ? <a class="underline hover:text-encre dark:hover:text-white" href="{{ route('login') }}">Connectez-vous à votre espace</a>.</span>
        <a class="underline hover:text-encre dark:hover:text-white" href="{{ route('public.home') }}">Retour au site</a>
      </footer>
    </main>
  </div>
  <x-toast variant="staff" />
  <script src="{{ asset('assets/js/verifall-app.js') }}" defer></script>
  {{-- L'œil « voir / cacher » du mot de passe (data-pwd-eye). --}}
  <script src="{{ asset('assets/js/auth-forms.js') }}" defer></script>
  <script>
    (function () {
      document.querySelectorAll('[data-theme-basculer]').forEach(function (b) { b.addEventListener('click', function () { var s = document.documentElement.classList.toggle('dark'); try { localStorage.setItem('vf-theme', s ? 'dark' : 'light'); } catch (e) {} }); });
      /* Le registre : une nouvelle ligne entre toutes les 4,2 s, son verdict se pose ensuite. */
      var r = document.querySelector('[data-registre]');
      if (!r || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      var lignes = Array.prototype.slice.call(r.querySelectorAll('[data-ligne]'));
      if (lignes.length < 5) return;
      var i = 4;
      setInterval(function () {
        if (document.hidden) return;
        var l = lignes[i % lignes.length]; i++;
        r.prepend(l); l.classList.remove('hidden');
        l.animate([{ opacity: 0, transform: 'translateY(-12px)' }, { opacity: 1, transform: 'none' }], { duration: 420, easing: 'cubic-bezier(.2,.7,.2,1)' });
        var t = l.querySelector('[data-tampon]');
        if (t) t.animate([{ opacity: 0, transform: 'scale(1.6) rotate(-8deg)' }, { opacity: 0, offset: .6 }, { opacity: 1, transform: 'scale(1) rotate(0)' }], { duration: 1000, easing: 'cubic-bezier(.3,1.4,.5,1)' });
        Array.prototype.slice.call(r.querySelectorAll('[data-ligne]')).slice(4).forEach(function (x) { x.classList.add('hidden'); });
      }, 4200);
    })();
  </script>
  @livewireScripts
  @stack('scripts')
</body>
</html>
