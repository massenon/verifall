<div class="w-full max-w-sm text-center">
  <p class="font-titre text-[72px] sm:text-[96px] leading-none font-semibold tabular-nums tracking-tight" data-horloge>{{ now()->format('H:i') }}</p>
  <p class="mt-2 text-white/70 first-letter:uppercase">{{ now()->locale('fr')->translatedFormat('l j F') }}</p>

  <div class="mt-10 flex flex-col items-center">
    <span class="avatar w-16 h-16 !text-lg ring-4 ring-white/10">{{ $initiales }}</span>
    <h1 class="mt-3 font-titre text-xl font-semibold">{{ $nom }}</h1>
    <p class="mt-1 text-sm text-white/60">
      @if ($inactivite) Session verrouillée à {{ $depuis }} après 15 minutes sans activité. @else Session verrouillée à {{ $depuis }}. @endif
      Votre travail est conservé.
    </p>
  </div>

  <form wire:submit="deverrouiller" class="mt-6 text-left" novalidate>
    <label for="verrou-mdp" class="sr-only">Mot de passe</label>
    <div class="relative">
      <input id="verrou-mdp" type="password" wire:model="motDePasse" autocomplete="current-password" autofocus required
             class="w-full h-12 pl-4 pr-36 rounded-ctrl bg-white/10 border border-white/20 text-white placeholder:text-white/50 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/40"
             placeholder="Mot de passe" @if ($erreur) aria-invalid="true" aria-describedby="verrou-erreur" @endif>
      <button type="submit" class="absolute right-1.5 top-1.5 h-9 px-3 rounded-[6px] bg-brand text-foret font-medium text-sm inline-flex items-center gap-1.5 hover:bg-brand-600 hover:text-white" wire:loading.attr="disabled">
        <x-lucide name="lock-open" />Déverrouiller
      </button>
    </div>
    <p id="verrou-erreur" class="mt-2 min-h-[20px] text-sm text-red-200 flex items-center gap-1.5" aria-live="polite">
      @if ($erreur)<x-lucide name="circle-alert" />{{ $erreur }}@endif
    </p>
  </form>

  <form method="POST" action="{{ route('staff.logout') }}" class="mt-4">
    @csrf
    <button type="submit" class="text-sm text-white/70 hover:text-white underline underline-offset-4">Ce n'est pas vous ? Se déconnecter</button>
  </form>
</div>
